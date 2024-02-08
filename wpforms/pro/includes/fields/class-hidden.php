<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hidden text field.
 *
 * @since 1.0.0
 */
class WPForms_Field_Hidden extends WPForms_Field {

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Define field type information.
		$this->name  = esc_html__( 'Hidden Field', 'wpforms' );
		$this->type  = 'hidden';
		$this->icon  = 'fa-eye-slash';
		$this->order = 210;
		$this->group = 'fancy';

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.8.4
	 */
	private function hooks() {

		add_filter( 'wpforms_field_new_default', [ $this, 'field_new_default' ] );
		add_filter( 'wpforms_field_new_class', [ $this, 'preview_field_new_class' ], 10, 2 );
	}

	/**
	 * Field options panel inside the builder.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field Field data and settings.
	 */
	public function field_options( $field ) {
		/*
		 * Basic field options.
		 */

		// Options open markup.
		$args = [
			'markup' => 'open',
		];

		$this->field_option( 'basic-options', $field, $args );

		// Label.
		$this->field_option(
			'label',
			$field,
			[
				'tooltip' => esc_html__( 'Enter text for the form field label. Never displayed on the front-end.', 'wpforms-lite' ),
			]
		);

		// Set label to disabled.
		$this->field_element(
			'text',
			$field,
			[
				'type'  => 'hidden',
				'slug'  => 'label_disable',
				'value' => '1',
			]
		);

		// Options close markup.
		$args = [
			'markup' => 'close',
		];

		$this->field_option( 'basic-options', $field, $args );

		// Advanced options open markup.
		$this->field_option(
			'advanced-options',
			$field,
			[
				'markup' => 'open',
			]
		);

		// Default value.
		$this->field_option( 'default_value', $field );

		// Custom CSS classes.
		$this->field_option( 'css', $field );

		// Hide Label.
		$this->field_option(
			'label_hide',
			$field,
			[
				'class' => 'wpforms-disabled',
			]
		);

		// Advanced options close markup.
		$this->field_option(
			'advanced-options',
			$field,
			[
				'markup' => 'close',
			]
		);
	}

	/**
	 * New field default settings in the form builder.
	 *
	 * @since 1.8.4
	 *
	 * @param array $field Field settings.
	 *
	 * @return array
	 */
	public function field_new_default( $field ): array {

		if ( empty( $field['type'] ) || $field['type'] !== $this->type ) {
			return $field;
		}

		$field['label_hide'] = '1';

		return $field;
	}

	/**
	 * Get new field CSS class.
	 *
	 * @since 1.8.4
	 *
	 * @param string $css_class Preview new field CSS class.
	 * @param array  $field     Field data.
	 *
	 * @return string
	 */
	public function preview_field_new_class( $css_class, $field ): string {

		if ( empty( $field['type'] ) || $field['type'] !== $this->type ) {
			return $css_class;
		}

		return trim( $css_class . ' label_hide' );
	}

	/**
	 * Field preview inside the builder.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field Field data and settings.
	 */
	public function field_preview( $field ) {

		// Define data.
		$default_value = ! empty( $field['default_value'] ) ? $field['default_value'] : '';

		// The Hidden field label is always hidden.
		$field['label_hide'] = '1';

		// Label.
		$this->field_preview_option( 'label', $field );

		// Primary input.
		echo '<input type="text" class="primary-input"  value="' . esc_attr( $default_value ) . '" readonly>';
	}

	/**
	 * Field display on the form front-end.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field      Field data and settings.
	 * @param array $deprecated Not used any more field attributes.
	 * @param array $form_data  Form data and settings.
	 */
	public function field_display( $field, $deprecated, $form_data ) {

		// Define data.
		$primary = $field['properties']['inputs']['primary'];

		// Primary field.
		printf(
			'<input type="hidden" %s>',
			wpforms_html_attributes( $primary['id'], $primary['class'], $primary['data'], $primary['attr'] )
		);
	}
}

new WPForms_Field_Hidden();
