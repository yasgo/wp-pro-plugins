<?php
/**
 * Image choice template for the Entry Print page.
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

$image_url = ! empty( $choice['image'] ) ? $choice['image'] : null;
$image     = ! empty( $image_url ) ? sprintf( '<img src="%s" alt="%s"/>', esc_url( $image_url ), esc_attr( $choice['label'] ) ) : '';

printf(
	'<div class="field-value-choice field-value-choice-image field-value-choice-%1$s%2$s"><div class="field-value-choice-image-wrapper">%3$s</div><div>%4$s</div></div>',
	esc_attr( $choice_type ),
	$is_checked ? ' field-value-choice-checked' : '',
	wp_kses_post( $image ),
	wp_kses_post( $choice['label'] )
);
