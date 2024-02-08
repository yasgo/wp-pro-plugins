<?php

use WPForms\Helpers\Transient;
use WPForms\Admin\Notice;

/**
 * License key fun.
 *
 * @since 1.0.0
 */
class WPForms_License {

	/**
	 * License update time option name.
	 *
	 * @since 1.8.6
	 */
	const LICENSE_UPDATE_TIME_OPTION = 'wpforms_license_updates';

	/**
	 * Store any license error messages.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $errors = [];

	/**
	 * Store any license success messages.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $success = [];

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.8.1.2
	 */
	public function hooks() {

		// Admin notices.
		add_action( 'admin_notices', [ $this, 'notices' ] );

		// Periodic background license check.
		$this->maybe_validate_key();
	}

	/**
	 * Retrieve the license key.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get() {

		return wpforms_get_license_key();
	}

	/**
	 * Check how license key is provided.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_key_location() {

		$key = wpforms_setting( 'key', '', 'wpforms_license' );

		if ( ! empty( $key ) ) {
			return 'option';
		}

		if ( defined( 'WPFORMS_LICENSE_KEY' ) && WPFORMS_LICENSE_KEY ) {
			return 'constant';
		}

		return 'missing';
	}

	/**
	 * Load the license key level.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function type() {

		return wpforms_setting( 'type', '', 'wpforms_license' );
	}

	/**
	 * Verify a license key entered by the user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key  License key.
	 * @param bool   $ajax True if this is an ajax request.
	 *
	 * @return bool
	 */
	public function verify_key( $key = '', $ajax = false ) {

		if ( empty( $key ) ) {
			return false;
		}

		// Perform a request to verify the key.
		$verify = $this->perform_remote_request( 'verify-key', [ 'tgm-updater-key' => $key ] );

		// If the verification request returns false, send back a generic error message and return.
		if ( ! $verify ) {
			$msg = esc_html__( 'There was an error connecting to the remote key API. Please try again later.', 'wpforms' );

			if ( $ajax ) {
				wp_send_json_error( $msg );
			} else {
				$this->errors[] = $msg;

				return false;
			}
		}

		// If an error is returned, set the error and return.
		if ( ! empty( $verify->error ) ) {
			if ( $ajax ) {
				wp_send_json_error( $verify->error );
			} else {
				$this->errors[] = $verify->error;

				return false;
			}
		}

		$success = isset( $verify->success ) ? $verify->success : esc_html__( 'Congratulations! This site is now receiving automatic updates.', 'wpforms' );

		// Otherwise, user's license has been verified successfully, update the option and set the success message.
		$option                = (array) get_option( 'wpforms_license', [] );
		$option['key']         = $key;
		$option['type']        = isset( $verify->type ) ? $verify->type : $option['type'];
		$option['is_expired']  = false;
		$option['is_disabled'] = false;
		$option['is_invalid']  = false;
		$this->success[]       = $success;

		update_option( 'wpforms_license', $option );

		$this->clear_cache();

		if ( $ajax ) {
			wp_send_json_success(
				[
					'type' => $option['type'],
					'msg'  => $success,
				]
			);
		}
	}

	/**
	 * Clear license cache routine.
	 *
	 * @since 1.6.8
	 */
	private function clear_cache() {

		Transient::delete( 'addons' );
		Transient::delete( 'addons_urls' );

		wp_clean_plugins_cache();
	}

	/**
	 * Maybe validates a license key entered by the user.
	 *
	 * @since 1.0.0
	 *
	 * @return void Return early if the license update time has not expired yet.
	 */
	public function maybe_validate_key() {

		$key = $this->get();

		if ( ! $key ) {
			// Prevent one extra DB request on delete_option() when it does not exist.
			if ( false !== get_option( self::LICENSE_UPDATE_TIME_OPTION ) ) {
				// Flush timestamp interval when key is missing or not available.
				delete_option( self::LICENSE_UPDATE_TIME_OPTION );
			}

			return;
		}

		// Perform a request to validate the key once a day.
		$time = time();

		if ( $time < (int) get_option( self::LICENSE_UPDATE_TIME_OPTION ) ) {
			return;
		}

		$update = update_option( self::LICENSE_UPDATE_TIME_OPTION, strtotime( '+24 hours' ) );

		// Get option again to check that update_option was successful, and the option was not altered by any filters.
		if (
			$update &&
			$time < (int) get_option( self::LICENSE_UPDATE_TIME_OPTION )
		) {
			$this->validate_key( $key );
		}
	}

	/**
	 * Validate a license key entered by the user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key           Key.
	 * @param bool   $forced        Force to set contextual messages (false by default).
	 * @param bool   $ajax          AJAX.
	 * @param bool   $return_status Option to return the license status.
	 *
	 * @return string|bool
	 */
	public function validate_key( $key = '', $forced = false, $ajax = false, $return_status = false ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$validate = $this->perform_remote_request( 'validate-key', [ 'tgm-updater-key' => $key ] );

		// If there was a basic API error in validation, only set the transient for 10 minutes before retrying.
		if ( ! $validate ) {
			// If forced, set contextual success message.
			if ( $forced ) {
				$msg = esc_html__( 'There was an error connecting to the remote key API. Please try again later.', 'wpforms' );
				if ( $ajax ) {
					wp_send_json_error( $msg );
				} else {
					$this->errors[] = $msg;
				}
			}

			return false;
		}

		$option = (array) get_option( 'wpforms_license' );
		// If a key or author error is returned, the license no longer exists or the user has been deleted, so reset license.
		if ( isset( $validate->key ) || isset( $validate->author ) ) {
			$option['is_expired']  = false;
			$option['is_disabled'] = false;
			$option['is_invalid']  = true;
			update_option( 'wpforms_license', $option );
			if ( $ajax ) {
				wp_send_json_error( esc_html__( 'Your license key for WPForms is invalid. The key no longer exists or the user associated with the key has been deleted. Please use a different key to continue receiving automatic updates.', 'wpforms' ) );
			}

			return $return_status ? 'invalid' : false;
		}

		// If the license has expired, set the transient and expired flag and return.
		if ( isset( $validate->expired ) ) {
			$option['is_expired']  = true;
			$option['is_disabled'] = false;
			$option['is_invalid']  = false;
			update_option( 'wpforms_license', $option );
			if ( $ajax ) {
				wp_send_json_error( esc_html__( 'Your license key for WPForms has expired. Please renew your license key on WPForms.com to continue receiving automatic updates.', 'wpforms' ) );
			}

			return $return_status ? 'expired' : false;
		}

		// If the license is disabled, set the transient and disabled flag and return.
		if ( isset( $validate->disabled ) ) {
			$option['is_expired']  = false;
			$option['is_disabled'] = true;
			$option['is_invalid']  = false;
			update_option( 'wpforms_license', $option );
			if ( $ajax ) {
				wp_send_json_error( esc_html__( 'Your license key for WPForms has been disabled. Please use a different key to continue receiving automatic updates.', 'wpforms' ) );
			}

			return $return_status ? 'disabled' : false;
		}

		// Otherwise, our check has returned successfully. Set the transient and update our license type and flags.
		$option['type']        = isset( $validate->type ) ? $validate->type : $option['type'];
		$option['is_expired']  = false;
		$option['is_disabled'] = false;
		$option['is_invalid']  = false;

		update_option( 'wpforms_license', $option );

		// If forced, set a contextual success message.
		if ( $forced ) {
			$msg             = esc_html__( 'Your key has been refreshed successfully.', 'wpforms' );
			$this->success[] = $msg;
			if ( $ajax ) {
				wp_send_json_success(
					[
						'type' => $option['type'],
						'msg'  => $msg,
					]
				);
			}
		}

		return $return_status ? 'valid' : true;
	}

	/**
	 * Deactivate a license key entered by the user.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $ajax True if this is an ajax request.
	 */
	public function deactivate_key( $ajax = false ) {

		$key = $this->get();

		if ( ! $key ) {
			return;
		}

		// Perform a request to deactivate the key.
		$deactivate = $this->perform_remote_request( 'deactivate-key', [ 'tgm-updater-key' => $key ] );

		// If the deactivation request returns false, send back a generic error message and return.
		if ( ! $deactivate ) {

			$msg = esc_html__( 'There was an error connecting to the remote key API. Please try again later.', 'wpforms' );

			if ( $ajax ) {
				wp_send_json_error(
					[
						'msg' => $msg,
					]
				);
			}

			$this->errors[] = $msg;

			return;
		}

		$success_message = esc_html__( 'You have deactivated the key from this site successfully.', 'wpforms' );

		// If an error is returned, set the error and return.
		if ( ! empty( $deactivate->error ) ) {

			// If the license key is invalid, delete the option to ensure the user doesn't get stuck with a filled input.
			// Doing this here will ensure that the connection to the server is already established successfully.
			if ( $this->get_errors() ) {
				$this->remove_key();
			}

			if ( $ajax ) {
				$has_key = ! empty( $this->get() );

				if ( $has_key ) {
					wp_send_json_error(
						[
							'info' => $this->get_info_message_escaped(),
							'msg'  => $deactivate->error,
						]
					);
				}

				wp_send_json_success(
					[
						'info' => $this->get_info_message_escaped(),
						'msg'  => $success_message,
					]
				);
			}

			$this->errors[] = $deactivate->error;

			return;
		}

		// Otherwise, user's license has been deactivated successfully, reset the option and set the success message.
		$success         = isset( $deactivate->success ) ? $deactivate->success : $success_message;
		$this->success[] = $success;

		$this->remove_key();

		if ( $ajax ) {
			wp_send_json_success(
				[
					'info' => $this->get_info_message_escaped(),
					'msg'  => $success,
				]
			);
		}
	}

	/**
	 * Empty out the license key option and flush the cache.
	 *
	 * @since 1.8.0
	 */
	private function remove_key() {

		update_option( 'wpforms_license', '' );
		$this->clear_cache();
	}

	/**
	 * Return possible license key error flag.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if there are license key errors, false otherwise.
	 */
	public function get_errors() {

		$option = get_option( 'wpforms_license' );

		return ! empty( $option['is_expired'] ) || ! empty( $option['is_disabled'] ) || ! empty( $option['is_invalid'] );
	}

	/**
	 * Return license key message if applicable.
	 *
	 * @since 1.7.9
	 *
	 * @return string Returns proper info (error) message depending on the state of the license.
	 */
	public function get_info_message_escaped() {

		if ( ! $this->get() ) {
			return sprintf(
				wp_kses( /* translators: %1$s - WPForms.com Account dashboard URL, %2$s - WPForms.com pricing URL. */
					__( 'Your license key can be found in your <a href="%1$s" target="_blank" rel="noopener noreferrer">WPForms Account Dashboard</a>. Don’t have a license? <a href="%2$s" target="_blank" rel="noopener noreferrer">Sign up today!</a>', 'wpforms' ),
					[
						'a' => [
							'href'   => [],
							'target' => [],
							'rel'    => [],
						],
					]
				),
				wpforms_utm_link( 'https://wpforms.com/account/', 'settings-license', 'Account Dashboard' ),
				wpforms_utm_link( 'https://wpforms.com/pricing/', 'settings-license', 'License Key Sign Up' )
			);
		}

		if ( $this->is_expired() ) {
			return wp_kses(
				__( '<strong>Your license has expired.</strong> An active license is needed to create new forms and edit existing forms. It also provides access to new features & addons, plugin updates (including security improvements), and our world class support!', 'wpforms' ),
				[ 'strong' => [] ]
			);
		}

		if ( $this->is_disabled() ) {
			return wp_kses(
				__( '<strong>Your license key has been disabled.</strong> Please use a different key to continue receiving automatic updates.', 'wpforms' ),
				[ 'strong' => [] ]
			);
		}

		if ( $this->is_invalid() ) {
			return wp_kses(
				__( '<strong>Your license key is invalid.</strong> The key no longer exists or the user associated with the key has been deleted. Please use a different key to continue receiving automatic updates.', 'wpforms' ),
				[ 'strong' => [] ]
			);
		}

		return '';
	}

	/**
	 * Output any notices generated by the class.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $below_h2 Whether to display a notice below H2.
	 */
	public function notices( $below_h2 = false ) {

		// Do not display notices if the user does not have permission or is on the settings page.
		if ( ! wpforms_current_user_can() || wpforms_is_admin_page( 'settings' ) ) {
			return;
		}

		// Grab the option and output any nag dealing with license keys.
		$key    = $this->get();
		$option = get_option( 'wpforms_license' );
		$class  = $below_h2 ? 'below-h2 ' : '';
		$class .= 'wpforms-license-notice';

		// If there is no license key, output nag about ensuring key is set for automatic updates.
		if ( ! $key ) {
			$notice = sprintf(
				wp_kses( /* translators: %s - Link to the Settings > General screen in the plugin, where users can enter their license key. */
					__( 'To access addons and enable automatic updates, please <a href="%s" target="_blank">enter and activate your license key.</a>', 'wpforms' ),
					[
						'a' => [
							'href'   => [],
							'target' => [],
							'rel'    => [],
						],
					]
				),
				esc_url( admin_url( 'admin.php?page=wpforms-settings' ) )
			);

			Notice::error(
				$notice,
				[ 'class' => $class ]
			);

			return; // Bail early, there is no point in going through the rest of the conditional statements, as the key is already missing.
		}

		// Set the renew now url.
		$renew_now_url = add_query_arg(
			[
				'utm_source'   => 'WordPress',
				'utm_medium'   => 'Admin Notice',
				'utm_campaign' => 'plugin',
				'utm_content'  => 'Renew Now',
			],
			'https://wpforms.com/account/licenses/'
		);

		// Set the "Learn more" url.
		$learn_more_url = add_query_arg(
			[
				'utm_source'   => 'WordPress',
				'utm_medium'   => 'Admin Notice',
				'utm_campaign' => 'plugin',
				'utm_content'  => 'Learn More',
			],
			'https://wpforms.com/docs/how-to-renew-your-wpforms-license/'
		);

		// If a key has expired, output nag about renewing the key.
		if ( isset( $option['is_expired'] ) && $option['is_expired'] ) :

				$notice = sprintf(
					'<h3 style="margin: .75em 0 0 0;">
						<img src="%1$s" style="vertical-align: text-top; width: 20px; margin-right: 7px;">%2$s
					</h3>
					<p>%3$s</p>
					<p>
						<a href="%4$s" class="button-primary">%5$s</a> &nbsp
						<a href="%6$s" class="button-secondary">%7$s</a>
					</p>',
					esc_url( WPFORMS_PLUGIN_URL . 'assets/images/exclamation-triangle.svg' ),
					esc_html__( 'Heads up! Your WPForms license has expired.', 'wpforms' ),
					esc_html__( 'An active license is needed to create new forms and edit existing forms. It also provides access to new features & addons, plugin updates (including security improvements), and our world class support!', 'wpforms' ),
					esc_url( $renew_now_url ),
					esc_html__( 'Renew Now', 'wpforms' ),
					esc_url( $learn_more_url ),
					esc_html__( 'Learn More', 'wpforms' )
				);

				$this->print_error_notices( $notice, 'license-expired', $class );

		endif;

		// If a key has been disabled, output nag about using another key.
		if ( isset( $option['is_disabled'] ) && $option['is_disabled'] ) {
			$notice = sprintf(
				'<h3 style="margin: .75em 0 0 0;">
					<img src="%1$s" style="vertical-align: text-top; width: 20px; margin-right: 7px;">%2$s
				</h3>
				<p>%3$s</p>
				<p>
					<a href="%4$s" class="button-primary">%5$s</a> &nbsp
					<a href="%6$s" class="button-secondary">%7$s</a>
				</p>',
				esc_url( WPFORMS_PLUGIN_URL . 'assets/images/exclamation-triangle.svg' ),
				esc_html__( 'Heads up! Your WPForms license has been disabled.', 'wpforms' ),
				esc_html__( 'Your license key for WPForms has been disabled. Please use a different key to continue receiving automatic updates', 'wpforms' ),
				esc_url( $renew_now_url ),
				esc_html__( 'Renew Now', 'wpforms' ),
				esc_url( $learn_more_url ),
				esc_html__( 'Learn More', 'wpforms' )
			);

			$this->print_error_notices( $notice, 'license-diabled', $class );
		}

		// If a key is invalid, output nag about using another key.
		if ( isset( $option['is_invalid'] ) && $option['is_invalid'] ) {

			$notice = sprintf(
				'<h3 style="margin: .75em 0 0 0;">
					<img src="%1$s" style="vertical-align: text-top; width: 20px; margin-right: 7px;">%2$s
				</h3>
				<p>%3$s</p>
				<p>
					<a href="%4$s" class="button-primary">%5$s</a> &nbsp
					<a href="%6$s" class="button-secondary">%7$s</a>
				</p>',
				esc_url( WPFORMS_PLUGIN_URL . 'assets/images/exclamation-triangle.svg' ),
				esc_html__( 'Heads up! Your WPForms license is invalid.', 'wpforms' ),
				esc_html__( 'The key no longer exists or the user associated with the key has been deleted. Please use a different key to continue receiving automatic updates.', 'wpforms' ),
				esc_url( $renew_now_url ),
				esc_html__( 'Renew Now', 'wpforms' ),
				esc_url( $learn_more_url ),
				esc_html__( 'Learn More', 'wpforms' )
			);

			$this->print_error_notices( $notice, 'license-invalid', $class );

		}

		// If there are any license errors, output them now.
		if ( ! empty( $this->errors ) ) {
			Notice::error(
				implode( '<br>', $this->errors ),
				[ 'class' => $class ]
			);
		}

		// If there are any success messages, output them now.
		if ( ! empty( $this->success ) ) {
			Notice::info(
				implode( '<br>', $this->success ),
				[ 'class' => $class ]
			);
		}
	}

	/**
	 * Print error notices generated by the class.
	 *
	 * @since 1.8.2.3
	 *
	 * @param string $notice Notice html.
	 * @param string $id     Notice id.
	 * @param string $class  Notice classes.
	 */
	public function print_error_notices( $notice, $id, $class = '' ) {

		if ( empty( $notice ) || empty( $id ) ) {
			return;
		}

		Notice::error(
			$notice,
			[
				'class' => $class,
				'autop' => false,
				'slug'  => 'license-expired',
			]
		);
	}
	/**
	 * Retrieve addons from the stored transient or remote server.
	 *
	 * @since 1.0.0
	 * @deprecated 1.8.0
	 *
	 * @param bool $force Whether to force the addons retrieval or re-use transient cache.
	 *
	 * @return array
	 */
	public function addons( $force = false ) {

		_deprecated_function( __METHOD__, '1.8.0 of the WPForms plugin', __CLASS__ . '::get_addons()' );

		if ( $force ) {
			Transient::delete( 'addons' );
		}

		return $this->get_addons();
	}

	/**
	 * Ping the remote server for addons data.
	 *
	 * @since 1.0.0
	 * @since 1.8.0 Added transient cache check and license validation.
	 *
	 * @return array Addons data, maybe an empty array if an error occurred.
	 */
	public function get_addons() {

		$key = $this->get();

		if ( empty( $key ) || ! $this->is_active() ) {
			return [];
		}

		$addons = Transient::get( 'addons' );

		// We store an empty array if the request isn't valid to prevent spam requests.
		if ( is_array( $addons ) ) {
			return $addons;
		}

		$addons = $this->perform_remote_request( 'get-addons-data', [ 'tgm-updater-key' => $key ] );

		if ( empty( $addons ) || isset( $addons->error ) ) {
			Transient::set( 'addons', [], 10 * MINUTE_IN_SECONDS );

			return [];
		}

		Transient::set( 'addons', $addons, DAY_IN_SECONDS );

		return $addons;
	}

	/**
	 * Request the remote URL via wp_remote_get() and return a json decoded response.
	 *
	 * @since 1.0.0
	 * @since 1.7.2 Switch from POST to GET request.
	 *
	 * @param string $action        The name of the request action var.
	 * @param array  $body          The GET query attributes.
	 * @param array  $headers       The headers to send to the remote URL.
	 * @param string $return_format The format for returning content from the remote URL.
	 *
	 * @return mixed Json decoded response on success, false on failure.
	 */
	public function perform_remote_request( $action, $body = [], $headers = [], $return_format = 'json' ) {

		// Request query parameters.
		$query_params = wp_parse_args(
			$body,
			[
				'tgm-updater-action'      => $action,
				'tgm-updater-key'         => $body['tgm-updater-key'],
				'tgm-updater-wp-version'  => get_bloginfo( 'version' ),
				'tgm-updater-php-version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION,
				'tgm-updater-referer'     => site_url(),
			]
		);

		$args = [
			'headers' => $headers,
		];

		// Perform the query and retrieve the response.
		$response      = wp_remote_get( add_query_arg( $query_params, WPFORMS_UPDATER_API ), $args );
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Bail out early if there are any errors.
		if ( (int) $response_code !== 200 || is_wp_error( $response_body ) ) {
			return false;
		}

		// Return the json decoded content.
		return json_decode( $response_body );
	}

	/**
	 * Whether the site is using an active license.
	 *
	 * @since 1.5.0
	 *
	 * @return bool
	 */
	public function is_active() {

		$license = get_option( 'wpforms_license', false );

		if (
			empty( $license ) ||
			! empty( $license['is_expired'] ) ||
			! empty( $license['is_disabled'] ) ||
			! empty( $license['is_invalid'] )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Whether the site is using an expired license.
	 *
	 * @since 1.7.2
	 *
	 * @return bool
	 */
	public function is_expired() {

		return $this->has_status( 'is_expired' );
	}

	/**
	 * Whether the site is using a disabled license.
	 *
	 * @since 1.7.2
	 *
	 * @return bool
	 */
	public function is_disabled() {

		return $this->has_status( 'is_disabled' );
	}

	/**
	 * Whether the site is using an invalid license.
	 *
	 * @since 1.7.2
	 *
	 * @return bool
	 */
	public function is_invalid() {

		return $this->has_status( 'is_invalid' );
	}

	/**
	 * Check whether there is a specific license status.
	 *
	 * @since 1.7.2
	 *
	 * @param string $status License status.
	 *
	 * @return bool
	 */
	private function has_status( $status ) {

		$license = get_option( 'wpforms_license', false );

		return ( isset( $license[ $status ] ) && $license[ $status ] );
	}
}
