<?php
/**
 * Geolocation product education template.
 *
 * @var bool   $plugin_allow  Determine if user's license level has access to the addon.
 * @var string $clear_slug    Clear slug (without `wpforms-` prefix).
 * @var string $modal_name    Name of the addon used in modal window.
 * @var string $license_level License level.
 * @var string $name          Name of the addon.
 * @var string $icon          Addon icon.
 * @var string $action        Action.
 * @var string $path          Plugin path.
 * @var string $nonce         Nonce.
 * @var string $url           Download URL.
 */

use WPForms\Admin\Education\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<!-- Entry Geolocation metabox -->
<div id="wpforms-entry-geolocation" class="postbox wpforms-dismiss-container wpforms-addon-container">

	<div class="postbox-header">
		<h2 class="hndle">
			<span><?php esc_html_e( 'Location', 'wpforms' ); ?></span>
			<a class="wpforms-education-hide wpforms-dismiss-button"
				data-section="admin-geolocation-metabox"
				data-nonce="<?php echo esc_attr( $nonce ); ?>">
				<span class="dashicons dashicons-no"></span>
			</a>
		</h2>
	</div>

	<div class="inside">
		<div class="wpforms-geolocation-preview">
			<div class="wpforms-geolocation-map"></div>
			<ul>
				<li>
					<span class="wpforms-geolocation-meta"><?php esc_html_e( 'Location', 'wpforms' ); ?></span>
					<span class="wpforms-geolocation-value"><span class="wpforms-flag wpforms-flag-us"></span>United States</span>
				</li>
				<li>
					<span class="wpforms-geolocation-meta"><?php esc_html_e( 'Zipcode', 'wpforms' ); ?></span>
					<span class="wpforms-geolocation-value">12345</span>
				</li>
				<li>
					<span class="wpforms-geolocation-meta"><?php esc_html_e( 'Country', 'wpforms' ); ?></span>
					<span class="wpforms-geolocation-value">US</span>
				</li>
				<li>
					<span class="wpforms-geolocation-meta"><?php esc_html_e( 'Lat/Long', 'wpforms' ); ?></span>
					<span class="wpforms-geolocation-value">56, -78</span>
				</li>
			</ul>
			<div class="overlay"></div>
			<div class="wpforms-addon-form wpforms-geolocation-form">
				<h2>
					<?php
					esc_html_e( 'Geolocation', 'wpforms' );
					if ( ! $plugin_allow ) {
						Helpers::print_badge( 'Pro', 'sm', 'inline', 'platinum' );
					}
					?>
				</h2>
				<p><?php esc_html_e( 'Geolocation allows you to quickly see where your visitors are located!', 'wpforms' ); ?></p>
				<?php if ( $plugin_allow ) { ?>
					<p><?php esc_html_e( 'You can install the Geolocation addon with just a few clicks!', 'wpforms' ); ?></p>
					<a
						class="<?php echo esc_attr( $action === 'activate' ? 'status-installed' : 'status-missing' ); ?> wpforms-btn wpforms-btn-lg wpforms-btn-blue wpforms-education-toggle-plugin-btn"
						data-plugin="<?php echo $action === 'activate' ? esc_attr( $path ) : esc_url( $url ); ?>"
						data-action="<?php echo esc_attr( $action ); ?>"
						data-type="addon"
						href="#">
						<?php
						$action === 'activate' ?
							esc_html_e( 'Activate', 'wpforms' ) :
							esc_html_e( 'Install & Activate', 'wpforms' );
						?>
					</a>
				<?php } else { ?>
					<p><?php esc_html_e( 'Please upgrade to the PRO plan to unlock Geolocation and more awesome features.', 'wpforms' ); ?></p>
					<a
						href="<?php echo esc_url( wpforms_admin_upgrade_link( 'Entries Single', 'Geolocation' ) ); ?>"
						class="wpforms-btn wpforms-btn-lg wpforms-btn-orange"><?php esc_html_e( 'Upgrade to WPForms Pro', 'wpforms' ); ?></a>
				<?php } ?>
			</div>
		</div>
	</div>

</div>
