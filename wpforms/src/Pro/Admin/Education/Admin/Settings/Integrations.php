<?php

namespace WPForms\Pro\Admin\Education\Admin\Settings;

use \WPForms\Admin\Education\Admin\Settings;

/**
 * Admin/Integrations Education for Pro.
 *
 * @since 1.6.6
 */
class Integrations extends Settings\Integrations {

	/**
	 * Hooks.
	 *
	 * @since 1.6.6
	 */
	public function hooks() {

		parent::hooks();

		add_filter( 'wpforms_admin_education_strings', [ $this, 'js_strings' ] );
	}

	/**
	 * Localize strings.
	 *
	 * @since 1.6.6
	 *
	 * @param array $strings Array of strings.
	 *
	 * @return array
	 */
	public function js_strings( $strings ) {

		$strings['save_prompt']  = esc_html__( 'Almost done! Would you like to refresh the page?', 'wpforms' );
		$strings['save_confirm'] = esc_html__( 'Refresh page', 'wpforms' );

		$license_key = wpforms_get_license_key();

		if ( ! empty( $license_key ) ) {
			$strings['upgrade']['pro']['url'] = add_query_arg(
				[ 'license_key' => sanitize_text_field( $license_key ) ],
				'https://wpforms.com/pricing/?utm_source=WordPress&utm_medium=settings-modal&utm_campaign=plugin'
			);
		}

		return $strings;
	}
}
