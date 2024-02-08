<?php
/**
 * Choice template for the Entry Print page.
 *
 * @var object $entry       Entry.
 * @var array  $form_data   Form data and settings.
 * @var array  $field       Entry field.
 * @var string $choice_type Checkbox or radio.
 * @var bool   $is_checked  Is the choice checked?
 * @var array  $choice      Choice data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

printf(
	'<div class="field-value-choice field-value-choice-%1$s%2$s"><label><input type="%1$s"%3$s disabled>%4$s</label></div>',
	esc_attr( $choice_type ),
	$is_checked ? ' field-value-choice-checked' : '',
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$is_checked ? ' checked' : '',
	wp_kses_post( $choice['label'] )
);
