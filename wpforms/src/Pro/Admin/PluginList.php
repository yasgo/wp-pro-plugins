<?php

namespace WPForms\Pro\Admin;

use WPForms_Updater;

/**
 * Class PluginList.
 *
 * Notice displayed in the Plugins page for Pro users.
 *
 * @since 1.8.6
 */
class PluginList {

	/**
	 * License Statuses.
	 *
	 * @since 1.8.6
	 *
	 * @var string
	 */
	const LICENSE_STATUS_EMPTY    = 'empty';
	const LICENSE_STATUS_VALID    = 'valid';
	const LICENSE_STATUS_EXPIRED  = 'expired';
	const LICENSE_STATUS_DISABLED = 'disabled';
	const LICENSE_STATUS_INVALID  = 'invalid';

	/**
	 * Plugin slug.
	 *
	 * @since 1.8.6
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Plugin path (relative to the plugins' dir).
	 *
	 * @since 1.8.6
	 *
	 * @var string
	 */
	private $plugin_path;

	/**
	 * Updater class instance.
	 *
	 * @since 1.8.6
	 *
	 * @var WPForms_Updater
	 */
	private $updater;

	/**
	 * The license status.
	 *
	 * @since 1.8.6
	 *
	 * @var string|null
	 */
	private $license_status;

	/**
	 * The latest version fetched from a remote source.
	 *
	 * @since 1.8.6
	 *
	 * @var null|string
	 */
	private $remote_latest_version;

	/**
	 * Whether this site is using the latest version of WPForms Pro.
	 *
	 * @since 1.8.6
	 *
	 * @var bool
	 */
	private $is_using_latest_version;

	/**
	 * Init.
	 *
	 * @since 1.8.6
	 *
	 * @return void
	 */
	public function init() {

		$this->plugin_slug = defined( 'WPFORMS_PLUGIN_DIR' ) ?
			plugin_basename( WPFORMS_PLUGIN_DIR ) :
			'wpforms';
		$this->plugin_path = $this->plugin_slug . '/wpforms.php';
		$this->updater     = wpforms()->get( 'updater' );

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.8.6
	 *
	 * @return void
	 */
	private function hooks() {

		global $pagenow;

		if ( empty( $pagenow ) || $pagenow !== 'plugins.php' ) {
			return;
		}

		add_filter( 'site_transient_update_plugins', [ $this, 'site_transient_update_plugins' ] );
		add_action( 'admin_head', [ $this, 'add_style' ] );
		add_action( 'after_plugin_row', [ $this, 'show_plugin_notice' ] );
	}

	/**
	 * Remove the border between the WPForms Pro and custom notice.
	 *
	 * @since 1.8.6
	 *
	 * @return void
	 */
	public function add_style() {

		printf(
			'<style>
				#wpforms-update td.plugin-update {
					box-shadow: 0 1px 0 0 rgba(0,0,0,.1);
					transform: translateY(-1px);
				}

				.plugins tr.update[data-slug="%1$s"] .second,
				.plugins tr.update[data-slug="%1$s"] .row-actions {
					padding-bottom: 0;
				}

				.wpforms-wrong-license {
					color: #dc3232;
					font-weight: bold;
				}

				@media screen and (max-width: 782px) {
					.plugins tr[data-slug="%1$s"].plugin-update-tr.active:before {
						background-color: #f0f6fc;
						border-left: 4px solid #72aee6;
					}
				}
			</style>',
			esc_attr( $this->plugin_slug )
		);
	}

	/**
	 * Filters `update_plugins` transient in Admin Plugins page.
	 *
	 * This method removes the "update" of the Pro plugin.
	 * Doing this fixes the edge case where both the default WP plugin update notice
	 * and our custom notice are displayed at the same time.
	 *
	 * @since 1.8.6
	 *
	 * @param mixed $value Value of site transient.
	 *
	 * @return object $value Amended WordPress update object.
	 */
	public function site_transient_update_plugins( $value ) {

		global $current_screen;

		// We only want this filter in the Dashboard -> Plugins page.
		if ( is_null( $current_screen ) || $current_screen->id !== 'plugins' ) {
			return $value;
		}

		if ( empty( $value->response[ $this->plugin_path ] ) ) {
			return $value;
		}

		unset( $value->response[ $this->plugin_path ] );

		$value->no_update[ $this->plugin_path ] = $this->updater->get_no_update();

		return $value;
	}

	/**
	 * Get the license status.
	 *
	 * @since 1.8.6
	 *
	 * @return string
	 */
	private function get_license_status(): string {

		if ( ! is_null( $this->license_status ) ) {
			return $this->license_status;
		}

		$license_type = wpforms_get_license_type();
		$license_key  = wpforms_get_license_key();

		$this->license_status = self::LICENSE_STATUS_VALID;

		if ( empty( $license_key ) || empty( $license_type ) ) {
			$this->license_status = self::LICENSE_STATUS_EMPTY;

			return $this->license_status;
		}

		$this->license_status = wpforms_setting( 'is_expired', false, 'wpforms_license' ) ?
			self::LICENSE_STATUS_EXPIRED :
			$this->license_status;
		$this->license_status = wpforms_setting( 'is_disabled', false, 'wpforms_license' ) ?
			self::LICENSE_STATUS_DISABLED :
			$this->license_status;
		$this->license_status = wpforms_setting( 'is_invalid', false, 'wpforms_license' ) ?
			self::LICENSE_STATUS_INVALID :
			$this->license_status;

		return $this->license_status;
	}

	/**
	 * Adds custom plugin notice for Pro users without a valid license.
	 *
	 * @since 1.8.6
	 *
	 * @param string|mixed $plugin_file Path to the plugin file relative to the `plugins` directory.
	 *
	 * @return void
	 */
	public function show_plugin_notice( $plugin_file ) {

		if ( $plugin_file !== $this->plugin_path ) {
			return;
		}

		$update_notice = $this->get_update_notice();

		if ( empty( $update_notice ) ) {
			return;
		}

		global $wp_list_table;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wpforms_render(
			'admin/plugins-list/update-notice',
			[
				'plugin_slug'   => $this->plugin_slug,
				'plugin_path'   => $this->plugin_path,
				'columns_count' => empty( $wp_list_table ) ? 4 : $wp_list_table->get_column_count(),
				'update_notice' => $update_notice,
			],
			true
		);
	}

	/**
	 * Get the update notice.
	 *
	 * @since 1.8.6
	 *
	 * @return string
	 */
	private function get_update_notice(): string {

		$status = $this->get_license_status();

		if ( $status === self::LICENSE_STATUS_VALID ) {
			return $this->is_using_latest_version() ? '' : $this->get_new_version_available_notice();
		}

		return $status === self::LICENSE_STATUS_EMPTY ?
			$this->get_no_license_notice() :
			$this->get_wrong_license_notice( $status );
	}

	/**
	 * Get the notice for users without a license key.
	 *
	 * @since 1.8.6
	 *
	 * @return string
	 */
	private function get_no_license_notice(): string {

		$activate_url = add_query_arg( [ 'page' => 'wpforms-settings' ], admin_url( 'admin.php' ) );
		$purchase_url = wpforms_utm_link( 'https://wpforms.com/pricing/', 'no license', 'purchase one now' );

		if ( $this->is_using_latest_version() ) {
			return sprintf( /* translators: %1$s - WPForms Pro URL; %2$s - WPForms Pro purchase link. */
				__(
					'<a href="%1$s">Activate WPForms Pro</a> to receive features, updates, and support. Don\'t have a license? <a target="_blank" href="%2$s" rel="noopener noreferrer">Purchase one now</a>.',
					'wpforms'
				),
				esc_url( $activate_url ),
				esc_url( $purchase_url )
			);
		}

		return $this->get_new_version_available_notice()
			. '<br />'
			. sprintf( /* translators: %1$s - WPForms Pro URL; %2$s - WPForms Pro purchase link. */
				__(
					'<a href="%1$s">Activate</a> your license to access this update, new features, and support. Don\'t have a license? <a target="_blank" href="%2$s" rel="noopener noreferrer">Purchase one now</a>.',
					'wpforms'
				),
				esc_url( $activate_url ),
				esc_url( $purchase_url )
			);
	}

	/**
	 * Get the notice for users with expired license key.
	 *
	 * @since 1.8.6
	 *
	 * @param string $status License status.
	 *
	 * @return string
	 */
	private function get_wrong_license_notice( string $status ): string {

		$message = $this->is_using_latest_version() ? '' : $this->get_new_version_available_notice() . '<br>';

		$renew_msg = sprintf( /* translators: %s - WPForms Pro renew link. */
			__(
				'<a target="_blank" href="%1$s" rel="noopener noreferrer">Renew now</a> to receive new features, updates, and support.',
				'wpforms'
			),
			esc_url( wpforms_utm_link( 'https://wpforms.com/account/licenses/', 'plugins list expired license', 'WPForms' ) )
		);

		$status_messages = [
			self::LICENSE_STATUS_EXPIRED  => esc_html__( 'Your WPForms Pro license is expired.', 'wpforms' ),
			self::LICENSE_STATUS_DISABLED => esc_html__( 'Your WPForms Pro license is disabled.', 'wpforms' ),
			self::LICENSE_STATUS_INVALID  => esc_html__( 'Your WPForms Pro license is invalid.', 'wpforms' ),
		];

		$status_message = $status_messages[ $status ] ?? '';

		return "$message<span class='wpforms-wrong-license'>$status_message</span> $renew_msg";
	}

	/**
	 * Get the notice to show when a new version of WPForms Pro is available.
	 *
	 * @since 1.8.6
	 *
	 * @return string
	 */
	private function get_new_version_available_notice(): string {

		$details_url = wpforms_utm_link(
			'https://wpforms.com/docs/how-to-view-recent-changes-to-the-wpforms-plugin-changelog/',
			'WPForms',
			'view version details'
		);
		$upgrade_url = wp_nonce_url(
			self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . $this->plugin_path ),
			'upgrade-plugin_' . $this->plugin_path
		);
		$new_version = $this->get_latest_version();

		// Message without "update now" link.
		$new_version_message = sprintf( /* translators: %1$s - WPForms Pro Changelog link; %2$s - WPForms Pro latest version. */
			__(
				'There is a new version of WPForms available. <a target="_blank" href="%1$s" rel="noopener noreferrer">View version %2$s details</a>',
				'wpforms'
			),
			esc_url( $details_url ),
			esc_html( $new_version )
		);

		if ( $this->get_license_status() !== self::LICENSE_STATUS_VALID ) {
			return $new_version_message . '.';
		}

		// Message with "update now" link.
		return $new_version_message . ' ' .
			sprintf( /* translators: %1$s - WPForms Pro upgrade URL; %2$s - Update now link aria-label attribute. */
				__(
					'or <a href="%1$s" class="update-link" aria-label="%2$s">update now</a>.',
					'wpforms'
				),
				esc_url( $upgrade_url ),
				esc_attr__( 'Update Now', 'wpforms' )
			);
	}

	/**
	 * Whether this site is using the latest version of WPForms Pro.
	 *
	 * @since 1.8.6
	 *
	 * @return bool
	 */
	private function is_using_latest_version(): bool {

		if ( ! is_null( $this->is_using_latest_version ) ) {
			return $this->is_using_latest_version;
		}

		$this->is_using_latest_version = version_compare( WPFORMS_VERSION, $this->get_latest_version(), '>=' );

		return $this->is_using_latest_version;
	}

	/**
	 * Get the latest version.
	 *
	 * @since 1.8.6
	 *
	 * @return string
	 */
	private function get_latest_version() {

		if ( ! is_null( $this->remote_latest_version ) ) {
			return $this->remote_latest_version;
		}

		// This transient is updated by WP core. We use it to get the latest version of WPForms Pro.
		// We have a hook on `pre_set_site_transient_update_plugins` in the `WPForms_Updater` class
		// that checks the remote API and adds the update for WPForms Pro to this transient.
		$option = get_site_option( '_site_transient_update_plugins' );

		$this->remote_latest_version = $option->response[ $this->plugin_path ]->new_version ?? WPFORMS_VERSION;

		return $this->remote_latest_version;
	}
}
