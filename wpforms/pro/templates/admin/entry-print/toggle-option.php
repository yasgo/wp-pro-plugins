<?php
/**
 * Toggle option template for the Entry Print page.
 *
 * @var string $slug  Slug.
 * @var string $label Option label.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="switch-container toggle-mode" data-mode="<?php echo esc_attr( $slug ); ?>">
	<a href="#" title="<?php echo esc_attr( $label ); ?>">
		<i class="switch" aria-hidden="true"></i><span><?php echo esc_attr( $label ); ?></span>
	</a>
</div>
