<?php
/**
 * Image choice template for the Entry view page.
 *
 * @var string $choice_type Checkbox or radio.
 * @var bool   $is_checked  Is the choice checked?
 * @var array  $choice      Choice data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$image_url = ! empty( $choice['image'] ) ? $choice['image'] : null;
$image     = ! empty( $image_url ) ? sprintf( '<img src="%s" alt="%s"/>', esc_url( $image_url ), esc_attr( $choice['label'] ) ) : '';

?>
<div class="field-value-choice field-value-choice-image field-value-choice-<?php echo esc_attr( $choice_type ); ?> <?php echo $is_checked ? ' field-value-choice-checked' : ''; ?>">
	<div class="field-value-choice-image-wrapper">
		<?php if ( ! empty( $image ) ) : ?>
			<?php echo wp_kses_post( $image ); ?>
		<?php endif; ?>
	</div>
	<div><?php echo wp_kses_post( $choice['label'] ); ?></div>
</div>
