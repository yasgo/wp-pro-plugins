<?php
/**
 * Choice template for the Entry view page.
 *
 * @var string $choice_type Checkbox or radio.
 * @var bool   $is_checked  Is the choice checked?
 * @var array  $choice      Choice data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="field-value-choice field-value-choice-<?php echo esc_attr( $choice_type ); ?> <?php echo $is_checked ? ' field-value-choice-checked' : ''; ?>">
	<label>
		<input type="<?php echo esc_attr( $choice_type ); ?>"<?php echo $is_checked ? ' checked' : ''; ?> disabled>
		<?php echo wp_kses_post( $choice['label'] ); ?>
	</label>
</div>
