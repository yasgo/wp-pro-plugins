<?php

namespace WPForms\Pro\Admin\Education;

/**
 * Education core for Pro.
 *
 * @since 1.6.6
 */
class Core extends \WPForms\Admin\Education\Core {

	/**
	 * Load enqueues.
	 *
	 * @since 1.6.6
	 */
	public function enqueues() {

		parent::enqueues();

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-pro-admin-education-core',
			WPFORMS_PLUGIN_URL . "assets/pro/js/admin/education/core{$min}.js",
			[ 'wpforms-admin-education-core' ],
			WPFORMS_VERSION,
			true
		);
	}

	/**
	 * Localize strings.
	 *
	 * @since 1.6.6
	 *
	 * @return array
	 */
	protected function get_js_strings() {

		$strings = parent::get_js_strings();

		$strings['addon_error'] = sprintf(
			wp_kses( /* translators: %1$s - addon download URL, %2$s - link to manual installation guide, %3$s - link to contact support. */
				__( 'Could not install the addon. Please <a href="%1$s" target="_blank" rel="noopener noreferrer">download it from wpforms.com</a> and <a href="%2$s" target="_blank" rel="noopener noreferrer">install it manually</a>, or <a href="%3$s" target="_blank" rel="noopener noreferrer">contact support</a> for assistance.', 'wpforms' ),
				[
					'a' => [
						'href'   => true,
						'target' => true,
						'rel'    => true,
					],
				]
			),
			esc_url( wpforms_utm_link( 'https://wpforms.com/account/licenses/', 'builder-modal', 'Addon Install Failure' ) ),
			esc_url( wpforms_utm_link( 'https://wpforms.com/docs/how-to-manually-install-addons-in-wpforms/', 'builder-modal', 'Addon Install Failure' ) ),
			esc_url( wpforms_utm_link( 'https://wpforms.com/contact/', 'builder-modal', 'Addon Install Failure' ) )
		);

		$license_key = wpforms_get_license_key();

		if ( ! empty( $license_key ) ) {
			$strings['upgrade']['pro']['url'] = add_query_arg(
				[ 'license_key' => sanitize_text_field( $license_key ) ],
				$strings['upgrade']['pro']['url']
			);
		}

		$strings['license'] = [
			'title'    => esc_html__( 'Heads up!', 'wpforms' ),
			'prompt'   => esc_html__( 'To access the %name%, please enter and activate your WPForms license key in the plugin settings.', 'wpforms' ),
			'button'   => esc_html__( 'Enter License Key', 'wpforms' ),
			'url'      => admin_url( 'admin.php?page=wpforms-settings' ),
			'is_empty' => empty( $license_key ),
		];

		$strings['activate_license'] = [
			'prompt_part1'  => esc_html__( 'To access the %name%, please enter your WPForms license key.', 'wpforms' ),
			'prompt_part2'  => sprintf(
				wp_kses(
					/* translators: %s - WPForms.com account licenses page URL. */
					__( 'Your key can be found inside the <a href="%s" target="_blank" rel="noopener noreferrer">WPForms.com Account Dashboard</a>.', 'wpforms' ),
					[
						'a' => [
							'href'   => [],
							'target' => [],
							'rel'    => [],
						],
					]
				),
				esc_url( wpforms_utm_link( 'https://wpforms.com/account/licenses/', 'Builder Modal Activate License', '~utm-content~ Field' ) )
			),
			'success_title' => esc_html__( 'All set', 'wpforms' ),
			'success_part1' => esc_html__( 'Your license was activated! All fields will be available once the form builder is refreshed.', 'wpforms' ),
			'success_part2' => esc_html__( 'Would you like to save and refresh the form builder?', 'wpforms' ),
			'placeholder'   => esc_html__( 'Enter License Key', 'wpforms' ),
			'button'        => esc_html__( 'Activate License', 'wpforms' ),
			'enter_key'     => esc_html__( 'Please enter a license key.', 'wpforms' ),
		];

		$license = (array) get_option( 'wpforms_license', [] );

		if ( ! empty( $license['is_expired'] ) ) {
			$strings['license']['prompt'] = esc_html__( 'Your WPForms license is expired. To access the %name%, please renew your license.', 'wpforms' );
			$strings['license']['button'] = esc_html__( 'Renew Now', 'wpforms' );
			$strings['license']['url']    = esc_url_raw( wpforms_utm_link( 'https://wpforms.com/account/licenses/', 'Builder Modal Expired License', '~utm-content~' ) );
		}

		if ( ! empty( $license['is_disabled'] ) || ! empty( $license['is_invalid'] ) ) {
			$strings['license']['prompt'] = esc_html__( 'Your WPForms license is not active. To access the %name%, please contact support for more details.', 'wpforms' );
			$strings['license']['button'] = esc_html__( 'Contact Support', 'wpforms' );
			$strings['license']['url']    = esc_url_raw( wpforms_utm_link( 'https://wpforms.com/account/support/', 'Builder Modal Disabled License', '~utm-content~' ) );
		}

		return $strings;
	}
}
