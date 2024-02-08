<?php

namespace WPForms\Pro\Forms\Fields\DateTime;

use WPForms\Forms\Fields\Base\Frontend as FrontendBase;

/**
 * Frontend class for the Date / Time field.
 *
 * @since 1.8.1
 */
class Frontend extends FrontendBase {

	/**
	 * Hooks.
	 *
	 * @since 1.8.1
	 */
	protected function hooks() {

		if ( wpforms_get_render_engine() !== 'modern' ) {
			return;
		}

		// Hooks for Modern Markup mode only.
		add_filter( "wpforms_frontend_modern_is_field_requires_fieldset_{$this->field_obj->type}", [ $this, 'is_field_requires_fieldset' ], PHP_INT_MAX, 2 );
		add_filter( "wpforms_field_properties_{$this->field_obj->type}", [ $this, 'field_properties_modern' ], 10, 3 );
	}

	/**
	 * Determine whether the field is requires fieldset+legend markup on the frontend.
	 *
	 * @since 1.8.1
	 *
	 * @param bool  $requires_fieldset True if requires. Defaults to false.
	 * @param array $field             Field data.
	 *
	 * @return bool
	 */
	public function is_field_requires_fieldset( $requires_fieldset, $field ) {

		if (
			isset( $field['format'], $field['date_type'] ) &&
			$field['date_type'] !== 'dropdown' &&
			in_array( $field['format'], [ 'date', 'time' ], true )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Additional field properties for the modern markup mode.
	 *
	 * @since 1.8.1
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Field settings.
	 * @param array $form_data  Form data and settings.
	 *
	 * @return array
	 */
	public function field_properties_modern( $properties, $field, $form_data ) {

		// Adjust field label's `for` attribute to match input Id if selected `Time` field format.
		if (
			isset( $field['format'] ) &&
			$field['format'] === 'time'
		) {
			$properties['label']['attr']['for'] .= '-time';
		}

		if (
			isset( $field['date_type'] ) &&
			$field['date_type'] === 'dropdown'
		) {
			$properties['inputs']['date']['d']['attr']['aria-label'] = esc_html__( 'Day', 'wpforms' );
			$properties['inputs']['date']['m']['attr']['aria-label'] = esc_html__( 'Month', 'wpforms' );
			$properties['inputs']['date']['y']['attr']['aria-label'] = esc_html__( 'Year', 'wpforms' );
		}

		return $properties;
	}

	/**
	 * Display Date dropdown element.
	 *
	 * @since 1.8.1
	 *
	 * @param string $label          Field label.
	 * @param string $short          Single char element name: 'd', 'm', 'y'.
	 * @param array  $numbers        Numbers range.
	 * @param int    $current        Current number.
	 * @param array  $atts           Element attributes.
	 * @param string $field_required Is this field required or not, has a HTML attribute or empty.
	 * @param array  $field          Field data and settings.
	 *
	 * @noinspection HtmlUnknownAttribute
	 */
	public function display_date_dropdown_element( $label, $short, $numbers, $current, $atts, $field_required, $field ) {

		printf(
			'<select name="wpforms[fields][%1$d][date][%2$s]" %3$s %4$s>',
			(int) $field['id'],
			esc_attr( $short ),
			wpforms_html_attributes( $atts['id'], $atts['class'], $atts['data'], $atts['attr'] ),
			esc_attr( $field_required )
		);

		echo '<option value="" class="placeholder" selected disabled>' . esc_html( $label ) . '</option>';

		foreach ( $numbers as $num ) {
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $num,
				selected( $num, $current, false ),
				(int) $num
			);
		}

		echo '</select>';
	}
}
