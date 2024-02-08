<?php
/**
 * Admin Fancy Notice template for Pro.
 *
 * @since 1.7.4
 *
 * @var string  $slug       Notice message slug.
 * @var string  $icon       Icon as SVG string.
 * @var string  $title      Message title.
 * @var string  $desc       Message body.
 * @var string  $btn_title  Button title.
 * @var string  $btn_url    Button URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$icon     = empty( $icon ) ? '' : $icon;
$icons    = [
	// Default is the Bulb icon.
	'default'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 352 512" fill="none" ><path d="M176 0C73.05 0-.12 83.54 0 176.24c.06 44.28 16.5 84.67 43.56 115.54C69.21 321.03 93.85 368.68 96 384l.06 75.18c0 3.15.94 6.22 2.68 8.84l24.51 36.84c2.97 4.46 7.97 7.14 13.32 7.14h78.85c5.36 0 10.36-2.68 13.32-7.14l24.51-36.84c1.74-2.62 2.67-5.7 2.68-8.84L256 384c2.26-15.72 26.99-63.19 52.44-92.22C335.55 260.85 352 220.37 352 176 352 78.8 273.2 0 176 0zm47.94 454.31L206.85 480h-61.71l-17.09-25.69-.01-6.31h95.9v6.31zm.04-38.31h-95.97l-.07-32h96.08l-.04 32zm60.4-145.32c-13.99 15.96-36.33 48.1-50.58 81.31H118.21c-14.26-33.22-36.59-65.35-50.58-81.31C44.5 244.3 32.13 210.85 32.05 176 31.87 99.01 92.43 32 176 32c79.4 0 144 64.6 144 144 0 34.85-12.65 68.48-35.62 94.68zM176 64c-61.75 0-112 50.25-112 112 0 8.84 7.16 16 16 16s16-7.16 16-16c0-44.11 35.88-80 80-80 8.84 0 16-7.16 16-16s-7.16-16-16-16z"/></svg>',
	'cloud_download' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 14" fill="none" ><path d="M16.7812 6.09375C16.9062 5.75 17 5.40625 17 5C17 3.34375 15.6562 2 14 2C13.375 2 12.7812 2.1875 12.3125 2.53125C11.4688 1.03125 9.84375 0 8 0C5.21875 0 3 2.25 3 5C3 5.09375 3 5.1875 3 5.28125C1.25 5.875 0 7.5625 0 9.5C0 12 2 14 4.5 14H16C18.1875 14 20 12.2188 20 10C20 8.09375 18.625 6.46875 16.7812 6.09375ZM12.4062 9L9.53125 11.9062C9.21875 12.1875 8.75 12.1875 8.46875 11.9062L5.5625 9C5.28125 8.71875 5.28125 8.21875 5.5625 7.9375L5.90625 7.59375C6.1875 7.3125 6.6875 7.3125 6.96875 7.625L8 8.6875V4.75C8 4.34375 8.3125 4 8.75 4H9.25C9.65625 4 10 4.34375 10 4.75V8.6875L11 7.625C11.2812 7.3125 11.7812 7.3125 12.0938 7.59375L12.4062 7.9375C12.7188 8.21875 12.7188 8.71875 12.4062 9Z" fill="white"/></svg>',
	'cloud_upload'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 14" fill="none" ><path fill="#fff" d="M16.78 6.1a3 3 0 0 0-4.47-3.56A4.97 4.97 0 0 0 3 5v.28A4.48 4.48 0 0 0 4.5 14H16a4 4 0 0 0 4-4c0-1.9-1.38-3.53-3.22-3.9Zm-4.37 2-.35.34A.75.75 0 0 1 11 8.4l-1-1.07v3.91c0 .44-.34.75-.75.75h-.5a.72.72 0 0 1-.75-.75v-3.9L6.97 8.4a.75.75 0 0 1-1.06.03l-.35-.35c-.31-.3-.31-.78 0-1.06l2.9-2.9a.74.74 0 0 1 1.04 0l2.9 2.9c.32.28.32.75 0 1.06Z"/></svg>',
	'check'          => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 14" fill="none"><path d="M6.40625 12.75C6.71875 13.0625 7.25 13.0625 7.5625 12.75L16.75 3.5625C17.0625 3.25 17.0625 2.71875 16.75 2.40625L15.625 1.28125C15.3125 0.96875 14.8125 0.96875 14.5 1.28125L7 8.78125L3.46875 5.28125C3.15625 4.96875 2.65625 4.96875 2.34375 5.28125L1.21875 6.40625C0.90625 6.71875 0.90625 7.25 1.21875 7.5625L6.40625 12.75Z" fill="white"/></svg>',
];
$icon_svg = empty( $icons[ $icon ] ) ? $icons['default'] : $icons[ $icon ];

?>
<div class="wpforms-fancy-notice wpforms-fancy-notice-<?php echo esc_attr( $slug ); ?>">
	<div class="wpforms-fancy-notice-icon <?php echo esc_attr( $icon ); ?>">
		<?php echo $icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<div>
		<div class="wpforms-fancy-notice-title"><?php echo esc_html( $title ); ?></div>
		<div class="wpforms-fancy-notice-desc"><?php echo wp_kses( $desc, [ 'a' => [ 'href' => [] ] ] ); ?></div>
	</div>
	<div class="wpforms-fancy-notice-buttons">
		<?php if ( ! empty( $btn_url ) ) : ?>
			<a href="<?php echo esc_url( $btn_url ); ?>" class="button button-primary"><?php echo esc_html( $btn_title ); ?></a>
		<?php endif; ?>
	</div>
</div>
