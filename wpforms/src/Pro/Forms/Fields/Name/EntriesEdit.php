<?php

namespace WPForms\Pro\Forms\Fields\Name;

/**
 * Editing Name field entries.
 *
 * @since 1.6.0
 */
class EntriesEdit extends \WPForms\Pro\Forms\Fields\Base\EntriesEdit {

	/**
	 * Constructor.
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		parent::__construct( 'name' );
	}

	/**
	 * Display the field on the Edit Entry page.
	 *
	 * @since 1.6.0
	 *
	 * @param array $entry_field Entry field data.
	 * @param array $field       Field data and settings.
	 * @param array $form_data   Form data and settings.
	 */
	public function field_display( $entry_field, $field, $form_data ) {

		foreach ( [ 'primary', 'first', 'middle', 'last' ] as $input ) {
			if ( $input === 'primary' ) {
				$entry_field[ $input ] = isset( $entry_field['value'] ) ? $entry_field['value'] : '';
			}
			if ( isset( $entry_field[ $input ] ) ) {
				$field['properties'] = $this->field_object->get_field_populated_single_property_value_public( $entry_field[ $input ], $input, $field['properties'], $field );
			}
		}

		$this->field_object->field_display( $field, null, $form_data );
	}
}
