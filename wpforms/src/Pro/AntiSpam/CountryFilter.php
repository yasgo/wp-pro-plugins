<?php

namespace WPForms\Pro\AntiSpam;

/**
 * Country Filter class.
 *
 * @since 1.7.8
 */
class CountryFilter {

	/**
	 * User IP.
	 *
	 * @since 1.7.8
	 *
	 * @var string $ip
	 */
	private $ip;

	/**
	 * A list of known CDN/Proxy headers which contain country code.
	 *
	 * @since 1.7.8
	 */
	const CDN_HEADERS = [
		'HTTP_CF_IPCOUNTRY',
		'HTTP_X_GEOIP_COUNTRY',
		'CloudFront-Viewer-Country-Region',
		'X-Country-Code',
	];

	/**
	 * Available sources for retrieving country by API.
	 *
	 * @since 1.7.8
	 *
	 * @var string[]
	 */
	private $available_geo_ips = [
		'wpforms' => 'https://geo.wpforms.com/v3/geolocate/json/%s',
		'ipapi'   => 'https://ipapi.co/%s/json',
		'keycdn'  => 'https://tools.keycdn.com/geo.json?host=%s',
	];

	/**
	 * Init class.
	 *
	 * @since 1.7.8
	 */
	public function init() {

		$this->ip = wpforms_get_ip();

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.7.8
	 */
	private function hooks() {

		add_filter( 'wpforms_process_initial_errors', [ $this, 'process' ], 10, 2 );
		add_filter( 'wpforms_pro_anti_spam_country_filter_request_keycdn_args', [ $this, 'modify_keycdn_request_args' ], 10, 2 );
		add_action( 'wpforms_admin_builder_anti_spam_panel_content', [ $this, 'get_settings' ], 10, 1 );
		add_filter( 'wpforms_save_form_args', [ $this, 'save' ], 10, 3 );
	}

	/**
	 * Add content to the 'Spam Protection and Security' panel in the Form Builder.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form_data Form data and settings.
	 */
	public function get_settings( $form_data ) {

		ob_start();

		wpforms_panel_field(
			'toggle',
			'anti_spam',
			'enable',
			$form_data,
			__( 'Enable country filter', 'wpforms' ),
			[
				'parent'      => 'settings',
				'subsection'  => 'country_filter',
				'tooltip'     => __( 'Allow or deny entries from the countries you select.', 'wpforms' ),
				'input_class' => 'wpforms-panel-field-toggle-next-field',
			]
		);
		?>
		<div class="wpforms-panel-field wpforms-panel-field-country-filter-body">
			<div class="wpforms-panel-field-country-filter-block">
				<label><?php esc_html_e( 'Country Filter', 'wpforms' ); ?></label>
				<div class="wpforms-panel-field-country-filter-block-row">
					<?php
					wpforms_panel_field(
						'select',
						'anti_spam',
						'action',
						$form_data,
						'',
						[
							'parent'     => 'settings',
							'subsection' => 'country_filter',
							'options'    => [
								'allow' => __( 'Allow', 'wpforms' ),
								'deny'  => __( 'Deny', 'wpforms' ),
							],
							'class'      => 'wpforms-panel-field-country-filter-block-row-action',
						]
					);
					?>
					<span class="wpforms-panel-field-country-filter-block-row-separator"><?php esc_html_e( 'entries from', 'wpforms' ); ?></span>
					<?php
					wpforms_panel_field(
						'select',
						'anti_spam',
						'country_codes',
						$form_data,
						'',
						[
							'parent'     => 'settings',
							'subsection' => 'country_filter',
							'options'    => wpforms_countries(),
							'multiple'   => true,
							'class'      => 'wpforms-panel-field-country-filter-block-row-countries',
						]
					);
					?>
				</div>
			</div>
			<input type="hidden" name="settings[anti_spam][country_filter][country_codes]" class="wpforms-panel-field-country-filter-country-codes-json">
			<?php
			wpforms_panel_field(
				'text',
				'anti_spam',
				'message',
				$form_data,
				__( 'Country Filter Message', 'wpforms' ),
				[
					'parent'     => 'settings',
					'subsection' => 'country_filter',
					'default'    => $this->get_default_error_message( $form_data ),
					'tooltip'    => __( 'Displayed if a visitor from a restricted country tries to submit your form.', 'wpforms' ),
				]
			);
			?>
		</div>
		<?php

		wpforms_panel_fields_group(
			ob_get_clean(),
			[
				'description' => __( 'Restrict form entries based on customizable filters or conditions.', 'wpforms' ),
				'title'       => __( 'Filtering', 'wpforms' ),
				'borders'     => [ 'top' ],
			]
		);
	}

	/**
	 * Do not show the form for users from countries covered by the filter.
	 *
	 * @since 1.7.8
	 *
	 * @param array $errors    Form submit errors.
	 * @param array $form_data Form data and settings.
	 *
	 * @uses is_allow_submission, is_deny_submission
	 *
	 * @return array
	 */
	public function process( $errors, $form_data ) {

		if ( ! $this->is_enabled( $form_data ) ) {
			return $errors;
		}

		// Stop processing for local environment.
		if ( $this->is_local() ) {
			return $errors;
		}

		// Stop processing when country code field is empty.
		if ( empty( $this->get_selected_countries( $form_data ) ) ) {
			return $errors;
		}

		$action = $this->get_selected_action( $form_data );

		$method = "is_{$action}_submission";

		if ( ! $this->$method( $form_data ) ) {
			$form_id                      = ! empty( $form_data['id'] ) ? $form_data['id'] : 0;
			$errors[ $form_id ]['footer'] = $this->get_error_message( $form_data );
		}

		return $errors;
	}

	/**
	 * Process condition for allow action.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	private function is_allow_submission( $form_data ) {

		return in_array( $this->get_country_code(), $this->get_selected_countries( $form_data ), true );
	}

	/**
	 * Process condition for deny action.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	private function is_deny_submission( $form_data ) {

		return ! in_array( $this->get_country_code(), $this->get_selected_countries( $form_data ), true );
	}

	/**
	 * Get selected action.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return string
	 */
	private function get_selected_action( $form_data ) {

		return ! empty( $form_data['settings']['anti_spam']['country_filter']['action'] ) && $form_data['settings']['anti_spam']['country_filter']['action'] === 'deny' ? 'deny' : 'allow';
	}

	/**
	 * Get selected country codes.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return array
	 */
	private function get_selected_countries( $form_data ) {

		return ! empty( $form_data['settings']['anti_spam']['country_filter']['country_codes'] ) && is_array( $form_data['settings']['anti_spam']['country_filter']['country_codes'] ) ?
			$form_data['settings']['anti_spam']['country_filter']['country_codes'] :
			[];
	}

	/**
	 * Get CDN/Proxy country related headers from $_SERVER array.
	 *
	 * @since 1.7.8
	 *
	 * @return string
	 */
	private function get_country_from_cdn_headers() {

		/**
		 * Filter CDN/Proxy headers with country code.
		 *
		 * @since 1.7.8
		 *
		 * @param array $cdn_headers Array of available country code CDN/Proxy headers.
		 */
		$headers = apply_filters( 'wpforms_pro_anti_spam_country_filter_get_country_from_cdn_headers', self::CDN_HEADERS );

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				return strtoupper( sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
			}
		}

		return '';
	}

	/**
	 * Get user country code from 3rd party API by IP.
	 *
	 * @since 1.7.8
	 *
	 * @return string
	 */
	private function get_country_from_api() {

		foreach ( array_keys( $this->available_geo_ips ) as $source ) {
			if ( ! is_string( $source ) ) {
				continue;
			}

			if ( ! empty( $this->available_geo_ips[ $source ] ) ) {
				$country_code = $this->request( $source, $this->available_geo_ips[ $source ], $this->ip );
			}

			if ( ! empty( $country_code ) ) {
				return $country_code;
			}
		}

		return '';
	}

	/**
	 * Get user country code from the fastest available source.
	 *
	 * @since 1.7.8
	 *
	 * @return string
	 */
	private function get_country_code() {

		/**
		 * Allow to modify user's country code before our logic.
		 *
		 * @since 1.7.8
		 *
		 * @param string $code Country code.
		 *
		 * @return string
		 */
		$code = apply_filters( 'wpforms_pro_anti_spam_country_filter_get_country_code_before_processing', '' );

		if ( ! empty( $code ) ) {
			return $code;
		}

		$country_code = $this->get_country_from_cdn_headers();

		if ( empty( $country_code ) ) {
			$country_code = $this->get_country_from_api();
		}

		/**
		 * Allow to modify user's country code after our logic.
		 *
		 * @since 1.7.8
		 *
		 * @param string $code Country code.
		 *
		 * @return string
		 */
		return apply_filters( 'wpforms_pro_anti_spam_country_filter_get_country_code_after_processing', $country_code );
	}

	/**
	 * Is local IP Address.
	 *
	 * @since 1.7.8
	 *
	 * @return bool
	 */
	private function is_local() {

		return empty( $this->ip ) || in_array( $this->ip, [ '127.0.0.1', '::1' ], true );
	}

	/**
	 * Make request for getting country code from 3rd party API.
	 *
	 * @since 1.7.8
	 *
	 * @param string $source   Source name.
	 * @param string $endpoint Endpoint.
	 * @param string $ip       IP address.
	 *
	 * @uses  wpforms_response, ipapi_response, keycdn_response
	 *
	 * @return string
	 */
	private function request( $source, $endpoint, $ip ) {

		$endpoint = sprintf( $endpoint, $ip );

		/**
		 * Allow modifying request arguments.
		 *
		 * @since 1.7.8
		 *
		 * @param array  $args Request arguments.
		 * @param string $ip   IP address.
		 *
		 * @return array
		 */
		$args = apply_filters( 'wpforms_pro_anti_spam_country_filter_request_args', [], $ip );

		/**
		 * Allow modifying request arguments for each CDN header.
		 *
		 * @since 1.7.8
		 *
		 * @param array  $args Request arguments.
		 * @param string $ip   IP address.
		 *
		 * @return array
		 */
		$args = (array) apply_filters( "wpforms_pro_anti_spam_country_filter_request_{$source}_args", $args, $ip );

		$request = wp_remote_get( $endpoint, $args );

		if ( is_wp_error( $request ) ) {
			return '';
		}

		$request      = json_decode( wp_remote_retrieve_body( $request ), true );
		$method       = $source . '_response';
		$country_code = $this->{$method}( $request );

		return sanitize_text_field( wp_unslash( $country_code ) );
	}

	/**
	 * Processing request from WPForms.
	 *
	 * @since 1.7.8
	 *
	 * @param array $request_body Request body.
	 *
	 * @return string
	 */
	private function wpforms_response( $request_body ) {

		return ! empty( $request_body['country_iso'] ) ? $request_body['country_iso'] : '';
	}

	/**
	 * Processing request from IpAPI.
	 *
	 * @since 1.7.8
	 *
	 * @param array $request_body Request body.
	 *
	 * @return string
	 */
	private function ipapi_response( $request_body ) {

		return ! empty( $request_body['country'] ) ? $request_body['country'] : '';
	}

	/**
	 * Processing request from KeyCDN.
	 *
	 * @since 1.7.8
	 *
	 * @param array $request_body Request body.
	 *
	 * @return string
	 */
	private function keycdn_response( $request_body ) {

		return ! empty( $request_body['data']['geo']['country_code'] ) ? $request_body['data']['geo']['country_code'] : '';
	}

	/**
	 * Is Country filter enabled in settings?
	 *
	 * @since 1.7.8
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	private function is_enabled( $form_data ) {

		return ! empty( $form_data['settings']['anti_spam']['country_filter']['enable'] );
	}

	/**
	 * Get error message.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return string
	 */
	private function get_error_message( $form_data ) {

		return ! empty( $form_data['settings']['anti_spam']['country_filter']['message'] ) ?
			$form_data['settings']['anti_spam']['country_filter']['message'] :
			$this->get_default_error_message( $form_data );
	}

	/**
	 * Return default error message.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form_data Form Data.
	 *
	 * @return string
	 */
	private function get_default_error_message( $form_data ) {

		/**
		 * Modify default error message.
		 *
		 * @since 1.7.8
		 *
		 * @param string $message   Request arguments.
		 * @param array  $form_data Form Data.
		 *
		 * @return string
		 */
		return apply_filters( 'wpforms_pro_anti_spam_country_filter_get_default_error_message', esc_html__( 'Sorry, this form does not accept submissions from your country.', 'wpforms' ), $form_data );
	}

	/**
	 * Modify request arguments for the KeyCDN geolocation provider.
	 *
	 * @since 1.7.8
	 *
	 * @param array  $args Request arguments.
	 * @param string $ip   IP address.
	 *
	 * @return array
	 */
	public function modify_keycdn_request_args( $args, $ip ) {

		$args['user-agent'] = sprintf( 'keycdn-tools:%s', site_url() );

		return $args;
	}

	/**
	 * Save country settings.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form Form array which is usable with `wp_update_post()`.
	 * @param array $data Data retrieved from $_POST and processed.
	 * @param array $args Empty by default, may contain custom data not intended to be saved, but used for processing.
	 *
	 * @return array
	 */
	public function save( $form, $data, $args ) {

		if ( empty( $args['context'] ) || $args['context'] !== 'save_form' ) {
			return $form;
		}

		// Get a filtered form content.
		$form_data     = json_decode( stripslashes( $form['post_content'] ), true );
		$country_codes = isset( $data['settings']['anti_spam']['country_filter'] ) ?
			json_decode( $data['settings']['anti_spam']['country_filter']['country_codes'], true ) :
			[];

		if ( ! is_array( $country_codes ) ) {
			$country_codes = [];
		}

		$form_data['settings']['anti_spam']['country_filter']['country_codes'] = array_filter(
			array_map(
				static function ( $country_code ) {

					$country_code = strtoupper( $country_code );

					return strlen( $country_code ) === 2 ? $country_code : '';
				},
				$country_codes
			)
		);

		$form['post_content'] = wpforms_encode( $form_data );

		return $form;
	}
}
