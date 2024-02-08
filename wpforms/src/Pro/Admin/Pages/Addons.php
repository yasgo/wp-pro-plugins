<?php

namespace WPForms\Pro\Admin\Pages;

use WPForms\Admin\Notice;

/**
 * Addons page for Pro.
 *
 * @since 1.6.7
 */
class Addons {

	/**
	 * Page slug.
	 *
	 * @since 1.6.7
	 *
	 * @type string
	 */
	const SLUG = 'addons';

	/**
	 * WPForms addons data.
	 *
	 * @since 1.6.7
	 *
	 * @var array
	 */
	public $addons;

	/**
	 * Determine if the plugin/addon installations are allowed.
	 *
	 * @since 1.6.7
	 *
	 * @var bool
	 */
	private $can_install;

	/**
	 * Determine if we need to refresh addons cache.
	 *
	 * @since 1.6.7
	 *
	 * @var bool
	 */
	private $refresh;

	/**
	 * Current license type.
	 *
	 * @since 1.6.7
	 *
	 * @var string|bool
	 */
	private $license_type;

	/**
	 * Class constructor.
	 *
	 * Please note, the constructor is only needed for backward compatibility.
	 *
	 * @since 1.6.7
	 */
	public function __construct() {

		// Maybe load addons page.
		add_action( 'admin_init', [ $this, 'init' ] );
	}

	/**
	 * Determine if the current class is allowed to load.
	 *
	 * @since 1.6.7
	 *
	 * @return bool
	 */
	public function allow_load() {

		return wpforms_is_admin_page( self::SLUG );
	}

	/**
	 * Init.
	 *
	 * @since 1.6.7
	 */
	public function init() {

		static $is_loaded = false;

		if ( ! $this->allow_load() || $is_loaded ) {
			return;
		}

		$this->can_install  = wpforms_can_install( 'addon' );
		$this->license_type = wpforms_get_license_type();

		$this->init_addons();

		$this->hooks();

		$is_loaded = true;
	}

	/**
	 * Hooks.
	 *
	 * @since 1.6.7
	 */
	public function hooks() {

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueues' ] );
		add_action( 'admin_notices', [ $this, 'notices' ] );
		add_action( 'wpforms_admin_page', [ $this, 'output' ] );
	}

	/**
	 * Init addons data.
	 *
	 * @since 1.6.7
	 */
	public function init_addons() {

		$this->refresh = ! empty( $_GET['wpforms_refresh_addons'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->addons = wpforms()->get( 'addons' )->get_all( $this->refresh );
	}

	/**
	 * Enqueue assets for the addons page.
	 *
	 * @since 1.6.7
	 */
	public function enqueues() {

		// JavaScript.
		wp_enqueue_script(
			'listjs',
			WPFORMS_PLUGIN_URL . 'assets/lib/list.min.js',
			[ 'jquery' ],
			'1.5.0'
		);
	}

	/**
	 * Build the output for the plugin addons page.
	 *
	 * @since 1.6.7
	 */
	public function output() {

		// The last attempt to obtain the addons data.
		if ( empty( $this->addons ) ) {
			$this->init_addons();
		}

		?>
		<div id="wpforms-admin-addons" class="wrap wpforms-admin-wrap wpforms-addons">
			<h1 class="wpforms-addons-header">
				<span class="wpforms-addons-header-title">
					<?php esc_html_e( 'WPForms Addons', 'wpforms' ); ?>

					<a href="<?php echo esc_url( add_query_arg( [ 'wpforms_refresh_addons' => '1' ] ) ); ?>" class="wpforms-addons-link" data-action="update">
						<?php esc_html_e( 'Refresh Addons', 'wpforms' ); ?>
					</a>
				</span>

				<span class="wpforms-addons-header-search">
					<input type="search" placeholder="<?php esc_attr_e( 'Search Addons', 'wpforms' ); ?>" id="wpforms-addons-search">
				</span>
			</h1>

			<?php
			if ( empty( $this->addons ) ) {
				echo '</div>';

				return;
			}

			$installed_addons = $this->get_addons_grid( 'activated' );
			$all_addons       = $this->get_addons_grid( 'all' );

			?>

				<div class="wpforms-admin-content">
					<?php if ( $installed_addons ) : ?>
						<div id="wpforms-addons-list-section-installed" class="wpforms-addons-list-section">
							<h3 class="wpforms-addons-list-section-title">
								<?php esc_html_e( 'Activated Addons', 'wpforms' ); ?>
							</h3>

							<div class="list wpforms-addons-list">
								<?php echo $installed_addons; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( $all_addons ) : ?>
						<div id="wpforms-addons-list-section-all" class="wpforms-addons-list-section">
							<h3 class="wpforms-addons-list-section-title">
								<?php esc_html_e( 'All Addons', 'wpforms' ); ?>
							</h3>

							<div class="list wpforms-addons-list">
								<?php echo $all_addons; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
					<?php endif; ?>

					<div id="wpforms-addons-no-results">
						<?php esc_html_e( 'Sorry, we didn\'t find any addons that match your criteria.', 'wpforms' ); ?>
					</div>
				</div>
			</div>
		<?php
	}

	/**
	 * Notices.
     *
     * @since 1.6.7
     */
	public function notices() {

		$errors = wpforms()->get( 'license' )->get_errors();

		if ( empty( $this->addons ) ) {
			Notice::error( esc_html__( 'There was an issue retrieving Addons for this site. Please click on the button above to refresh.', 'wpforms' ) );

			return;
		}

		if ( ! empty( $errors ) ) {
			Notice::error( esc_html__( 'In order to get access to Addons, you need to resolve your license key errors.', 'wpforms' ) );

			return;
		}

		if ( $this->refresh ) {
			Notice::success( esc_html__( 'Addons have successfully been refreshed.', 'wpforms' ) );
		}
	}

	/**
	 * Render grid of addons.
	 *
     * @since 1.6.7
	 *
	 * @param string $section Section name.
	 */
	public function get_addons_grid( string $section ) {

		ob_start();

		foreach ( $this->addons as $id => $addon ) {
			$addon = (array) $addon;
			$addon = wpforms()->get( 'addons' )->get_addon( $addon['slug'] );

			if ( ! $this->should_display_addon( $addon, $section ) ) {
				continue;
			}

			if ( $section === 'activated' ) {
				unset( $this->addons[ $id ] );
			}

			$this->print_addon( $addon );
		}

		return ob_get_clean();
	}

	/**
	 * Determine if addon should be displayed.
	 *
	 * @since 1.8.6
	 *
	 * @param array  $addon   Addon information.
	 * @param string $section Section name.
	 */
	private function should_display_addon( array $addon, string $section ): bool {

		$should_display = false;

		if ( $section === 'activated' ) {
			$allowed_statuses     = [ 'active' ];
			$current_license_type = wpforms_get_license_type();

			$should_display = in_array( $addon['status'], $allowed_statuses, true ) && in_array( $current_license_type, $addon['license'], true );
		} elseif ( $section === 'all' ) {
			$allowed_statuses = [ 'active', 'installed', 'missing' ];

			$should_display = in_array( $addon['status'], $allowed_statuses, true );
		}

		return $should_display;
	}

	/**
	 * Print addon.
	 *
	 * @since 1.6.7
	 *
	 * @param array $addon Addon information.
	 */
	private function print_addon( $addon ) {

		$image = ! empty( $addon['icon'] ) ? $addon['icon'] : 'sullie.png';
		$url   = add_query_arg(
			[
				'utm_source'   => 'WordPress',
				'utm_campaign' => 'plugin',
				'utm_medium'   => 'addons',
				'utm_content'  => $addon['title'],
			],
			! empty( $addon['status'] ) && $addon['status'] === 'active' && $addon['plugin_allow'] ? $addon['doc_url'] : $addon['page_url']
		);

		if ( $addon['slug'] === 'wpforms-stripe' ) {
			$addon['recommended'] = true;
		}

		echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'admin/addons-item',
			[
				'addon'             => $addon,
				'image'             => WPFORMS_PLUGIN_URL . 'assets/images/' . $image,
				'url'               => $url,
				'button'            => $this->get_addon_button_html( $addon ),
				'recommended'       => isset( $addon['recommended'] ) ? $addon['recommended'] : false,
				'has_settings_link' => $this->has_settings_link( $addon['slug'] ),
				'settings_url'      => $this->get_settings_link( $addon['slug'] ),
			],
			true
		);
	}

	/**
	 * Get addon button HTML.
	 *
	 * @since 1.6.7
	 *
	 * @param array $addon Prepared addon data.
 	 *
 	 * @return string
	 */
	private function get_addon_button_html( $addon ) {

		if ( $addon['action'] === 'upgrade' || $addon['action'] === 'license' || ! $addon['plugin_allow'] ) {
			return sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer" class="button button-secondary wpforms-upgrade-modal">%2$s</a>',
				add_query_arg(
					[
						'utm_content' => $addon['title'],
					],
					'https://wpforms.com/account/licenses/?utm_source=WordPress&utm_campaign=plugin&utm_medium=addons'
				),
				esc_html__( 'Upgrade Now', 'wpforms' )
			);
		}

		ob_start();

		?>
			<span class="wpforms-toggle-control">
				<label for="wpforms-addons-toggle-<?php echo esc_attr( $addon['slug'] ); ?>" class="wpforms-toggle-control-status" data-on="<?php esc_attr_e( 'Activated', 'wpforms' ); ?>" data-off="<?php esc_attr_e( 'Deactivated', 'wpforms' ); ?>">
					<?php if ( $addon['status'] === 'active' ) : ?>
						<?php esc_html_e( 'Activated', 'wpforms' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Deactivated', 'wpforms' ); ?>
					<?php endif; ?>
				</label>
				<input type="checkbox" id="wpforms-addons-toggle-<?php echo esc_attr( $addon['slug'] ); ?>" name="wpforms-addons-toggle" value="1" <?php echo checked( $addon['status'] === 'active' ); ?>>
				<label class="wpforms-toggle-control-icon" for="wpforms-addons-toggle-<?php echo esc_attr( $addon['slug'] ); ?>"></label>
			</span>

			<?php if ( $addon['status'] === 'missing' && $this->can_install ) : ?>
				<button class="status-missing button button-secondary">
					<?php esc_html_e( 'Install Addon', 'wpforms' ); ?>
				</button>
			<?php endif; ?>
		<?php

		return ob_get_clean();
	}

	/**
	 * Determine if addon has settings link.
	 *
	 * @since 1.8.6
	 *
	 * @param string $slug Addon slug.
	 *
	 * @return bool
	 */
	private function has_settings_link( string $slug ): bool {

		$addons_with_link = $this->get_addons_with_settings_link();

		$has_settings_link = false;

		foreach ( $addons_with_link as $addons ) {
			if ( in_array( $slug, $addons, true ) ) {
				$has_settings_link = true;

				break;
			}
		}

		return $has_settings_link;
	}

	/**
	 * Get addons with settings link.
	 *
	 * @since 1.8.6
	 *
	 * @return array
	 */
	private function get_addons_with_settings_link(): array {

		return [ // phpcs:ignore WPForms.Formatting.EmptyLineBeforeReturn.RemoveEmptyLineBeforeReturnStatement
			'tab'          => [
				'wpforms-geolocation',
			],
			'integrations' => [
				'wpforms-aweber',
				'wpforms-mailchimp',
				'wpforms-google-sheets',
				'wpforms-sendinblue',
				'wpforms-zapier',
				'wpforms-activecampaign',
				'wpforms-campaign-monitor',
				'wpforms-drip',
				'wpforms-getresponse',
				'wpforms-hubspot',
				'wpforms-mailerlite',
				'wpforms-salesforce',
			],
			'payments'     => [
				'wpforms-stripe',
				'wpforms-paypal-commerce',
				'wpforms-square',
				'wpforms-authorize-net',
			],
		];
	}

	/**
	 * Get addon settings link.
	 *
	 * @since 1.8.6
	 *
	 * @param string $slug Addon slug.
	 *
	 * @return string
	 */
	private function get_settings_link( string $slug ): string {

		$addons_with_link = $this->get_addons_with_settings_link();
		$addon_section    = $this->get_settings_link_section( $slug, $addons_with_link );
		$clear_slug       = $this->get_clean_slug( $slug );

		switch ( $addon_section ) {
			case 'tab':
				$link = add_query_arg(
					[
						'page' => 'wpforms-settings',
						'view' => $clear_slug,
					],
					admin_url( 'admin.php' )
				);
				break;

			case 'integrations':
				$link = add_query_arg(
					[
						'page'  => 'wpforms-settings',
						'view'  => 'integrations',
						'addon' => $clear_slug,
					],
					admin_url( 'admin.php' )
				);
				break;

			case 'payments':
				$link = add_query_arg(
					[
						'page' => 'wpforms-settings',
						'view' => 'payments',
					],
					admin_url( "admin.php#wpforms-setting-row-{$clear_slug}-heading" )
				);
				break;

			default:
				$link = '';
				break;
		}

		return $link;
	}

	/**
	 * Get addon settings link section.
	 *
	 * @since 1.8.6
	 *
	 * @param string $slug             Addon slug.
	 * @param array  $addons_with_link Addons with settings link.
	 *
	 * @return string
	 */
	private function get_settings_link_section( string $slug, array $addons_with_link ): string {

		$addon_section = '';

		foreach ( $addons_with_link as $section => $addons ) {
			if ( in_array( $slug, $addons, true ) ) {
				$addon_section = $section;

				break;
			}
		}

		return $addon_section;
	}

	/**
	 * Get clean addon slug.
	 *
	 * @since 1.8.6
	 *
	 * @param string $slug Addon slug.
	 *
	 * @return string
	 */
	private function get_clean_slug( string $slug ): string {

		$clean_slug = str_replace( 'wpforms-', '', $slug );

		if ( $clean_slug === 'authorize-net' ) {
			$clean_slug = 'authorize_net';
		}

		return $clean_slug;
	}
}
