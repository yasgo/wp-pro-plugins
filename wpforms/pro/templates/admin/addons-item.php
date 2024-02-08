<?php
/**
 * Admin > Addons page for Pro.
 * Template of the single addon item.
 *
 * @since 1.6.7
 *
 * @var string $image             Image URL.
 * @var array  $addon             Addon data.
 * @var string $url               Addon page URL.
 * @var string $button            Button HTML.
 * @var bool   $recommended       Flag for recommended addons.
 * @var bool   $has_settings_link Flag for addons with settings link.
 * @var string $settings_url      Addon settings link.
 */

use WPForms\Admin\Education\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$addon['title']           = str_replace( ' Addon', '', $addon['title'] );
$licenses                 = [ 'basic', 'plus', 'pro', 'elite', 'agency', 'ultimate' ];
$addon_licenses           = $addon['license'];
$common_licenses          = array_intersect( $licenses, $addon_licenses );
$minimum_required_license = reset( $common_licenses );
$image_alt                = sprintf( /* translators: %s - addon title. */
	__( '%s logo', 'wpforms' ),
	$addon['title']
);
?>
<div class="wpforms-addons-list-item">
	<div class="wpforms-addons-list-item-header">
		<img src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/' . $addon['icon'] ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>">

		<div class="wpforms-addons-list-item-header-meta">
			<div class="wpforms-addons-list-item-header-meta-title">
				<?php
				printf(
					'<a href="%1$s" title="%2$s" target="_blank" rel="noopener noreferrer" class="addon-link">%3$s</a>',
					esc_url( $url ),
					esc_attr__( 'Learn more', 'wpforms' ),
					esc_html( $addon['title'] )
				);
				?>

				<?php if ( ! empty( $addon['recommended'] ) ) : ?>
					<?php Helpers::print_badge( esc_html__( 'Recommended', 'wpforms' ), 'sm', 'inline', 'green', 'rounded', 'fa-star' ); ?>
				<?php endif; ?>
			</div>

			<div class="wpforms-addons-list-item-header-meta-excerpt">
				<?php echo esc_html( $addon['excerpt'] ); ?>
			</div>
		</div>
	</div>

	<div class="wpforms-addons-list-item-footer wpforms-addons-list-item-footer-<?php echo esc_attr( $addon['status'] ); ?>" data-plugin="<?php echo esc_attr( $addon['status'] === 'missing' ? $addon['url'] : $addon['path'] ); ?>" data-type="addon">
		<div>
			<?php if ( $addon['action'] === 'upgrade' ) : ?>
				<?php Helpers::print_badge( $minimum_required_license, 'lg' ); ?>
			<?php endif; ?>

			<?php if ( $has_settings_link && $addon['action'] !== 'upgrade' ) : ?>
				<a href="<?php echo esc_url( $settings_url ); ?>" class="wpforms-addons-list-item-footer-settings-link">
					<?php esc_html_e( 'Settings', 'wpforms' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<div class="wpforms-addons-list-item-footer-actions">
			<?php echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
</div>
