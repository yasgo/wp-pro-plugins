<?php

namespace WPForms\Pro\Admin\Addons;

use WPForms\Admin\Notice;

/**
 * Google Sheets addon update admin notices.
 *
 * @since 1.8.5.3
 */
class GoogleSheets {

	/**
	 * Indicate if current class is allowed to load.
	 *
	 * @since 1.8.5.3
	 *
	 * @return bool
	 */
	private function allow_load(): bool {

		if ( ! is_admin() || wp_doing_ajax() ) {
			return false;
		}

		// Addon is activated.
		if ( ! function_exists( 'wpforms_google_sheets' ) || ! defined( 'WPFORMS_GOOGLE_SHEETS_VERSION' ) ) {
			return false;
		}

		// Only for v1.x.x.
		if ( version_compare( WPFORMS_GOOGLE_SHEETS_VERSION, '2.0', '>=' ) ) {
			return false;
		}

		// The credentials are not set.
		if ( empty( wpforms_google_sheets()->get( 'account' )->get_credentials() ) ) {
			return false;
		}

		global $pagenow;

		// Load only on certain admin pages.
		return in_array( $pagenow, [ 'index.php', 'plugins.php' ], true ) ||
			wpforms_is_admin_page();
	}

	/**
	 * Init.
	 *
	 * @since 1.8.5.3
	 */
	public function init() {

		if ( ! $this->allow_load() ) {
			return;
		}

		// The Google Sheets addon v2.0 admin notice.
		if ( $this->is_v2_released() ) {
			$this->v2_update_is_released_notice();
		} else {
			$this->v2_update_is_expected_notice();
		}
	}

	/**
	 * Detect if the v2.0 is released.
	 *
	 * @since 1.8.5.3
	 *
	 * @return bool
	 */
	private function is_v2_released(): bool {

		$updates = (array) get_site_transient( 'update_plugins' );

		if ( empty( $updates['response'] ) ) {
			return false;
		}

		$addon_slug   = plugin_basename( WPFORMS_GOOGLE_SHEETS_FILE );
		$addon_update = (array) ( $updates['response'][ $addon_slug ] ?? [] );
		$new_version  = $addon_update['new_version'] ?? false;

		if ( ! $new_version ) {
			return false;
		}

		return version_compare( $new_version, '2.0', '>=' );
	}

	/**
	 * The addon v2.0 is expected notice.
	 *
	 * @since 1.8.5.3
	 */
	private function v2_update_is_expected_notice() {

		$title = __( 'Important Update for Google Sheets Addon Users', 'wpforms' );

		$message = sprintf( /* translators: %1$s - Google Sheets Re-Authentication doc link. */
			__( 'The Google Sheets addon for WPForms will be updated soon. All users who are sending entries to Google Sheets will need to <a href="%1$s" target="_blank" rel="noopener noreferrer">update the addon and re-authenticate their Google connection</a> as soon as version 2.0 becomes available to avoid interruptions in service.', 'wpforms' ),
			esc_url(
				wpforms_utm_link(
					'https://wpforms.com/docs/google-sheets-2-0-update-requirements/',
					'Google Sheets Update Alert 1',
					'Google Sheets Re-Authentication doc'
				)
			)
		);

		Notice::info( $this->get_compiled_notice( $title, $message ) );
	}

	/**
	 * The addon v2.0 is released notice.
	 *
	 * @since 1.8.5.3
	 */
	private function v2_update_is_released_notice() {

		$title = __( 'Urgent Action Required for Google Sheets Addon Users', 'wpforms' );

		$message = sprintf( /* translators: %1$s - Google Sheets Re-Authentication doc link. */
			__( 'WPForms Google Sheets addon version 2.0 is now available. All users who are sending entries to Google Sheets need to <a href="%1$s" target="_blank" rel="noopener noreferrer">update the addon and re-authenticate their Google connection</a> as soon as possible to avoid interruptions in service.', 'wpforms' ),
			esc_url(
				wpforms_utm_link(
					'https://wpforms.com/docs/google-sheets-2-0-update-requirements/',
					'Google Sheets Update Alert 2',
					'Google Sheets Re-Authentication doc'
				)
			)
		);

		Notice::error( $this->get_compiled_notice( $title, $message ) );
	}

	/**
	 * Get compiled notice.
	 *
	 * @since 1.8.5.3
	 *
	 * @param string $title   Notice title.
	 * @param string $message Notice message.
	 *
	 * @return string
	 */
	private function get_compiled_notice( $title, $message ): string {

		$notice = sprintf(
			'<h3 style="margin: 0.75em 0 0; padding: 0 2px;">%1$s</h3><p>%2$s</p>',
			$title,
			$message
		);

		return wp_kses(
			$notice,
			[
				'a'  => [
					'href'   => [],
					'rel'    => [],
					'target' => [],
				],
				'h3' => [
					'style' => [],
				],
				'p'  => [],
			]
		);
	}
}
