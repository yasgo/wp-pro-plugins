<?php
/**
 * Builder/Settings Education template for Pro.
 *
 * @since 1.6.6
 *
 * @var string $clear_slug    Clear slug (without `wpforms-` prefix).
 * @var string $modal_name    Name of the addon used in modal window.
 * @var string $license_level License level.
 * @var string $name          Name of the addon.
 * @var string $action        Action.
 * @var string $path          Plugin path.
 * @var string $nonce         Nonce.
 * @var string $url           Download URL.
 * @var string $video         Video URL.
 * @var string $utm_content   UTM content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<a href="#" class="wpforms-panel-sidebar-section wpforms-panel-sidebar-section-<?php echo esc_attr( $clear_slug ); ?> education-modal"
	data-name="<?php echo esc_attr( $modal_name ); ?>"
	data-slug="<?php echo esc_attr( $clear_slug ); ?>"
	data-action="<?php echo esc_attr( $action ); ?>"
	data-path="<?php echo esc_attr( $path ); ?>"
	data-url="<?php echo esc_attr( $url ); ?>"
	data-nonce="<?php echo esc_attr( $nonce ); ?>"
	data-video="<?php echo esc_url( $video ); ?>"
	data-license="<?php echo esc_attr( $license_level ); ?>"
	data-utm-content="<?php echo esc_attr( $utm_content ); ?>">
		<?php echo esc_html( $name ); ?>
		<i class="fa fa-angle-right wpforms-toggle-arrow"></i>
</a>
