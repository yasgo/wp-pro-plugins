<?php

namespace WPForms\Pro\Forms\Fields\Traits;

/**
 * Editing choice field entries trait.
 *
 * @since 1.7.4
 */
trait ChoicesEntriesEdit {

	/**
	 * Display the field on the Edit Entry page.
	 *
	 * @since 1.7.4
	 *
	 * @param array $entry_field Entry field data.
	 * @param array $field       Field data and settings.
	 * @param array $form_data   Form data and settings.
	 */
	public function field_display( $entry_field, $field, $form_data ) {

		$this->field_object->field_prefill_remove_choices_defaults( $field, $field['properties'] );

		$value_delimiter = ! empty( $field['dynamic_choices'] ) ? ',' : "\n";
		$value_choices   = isset( $entry_field['value_raw'] ) && $entry_field['value_raw'] !== '' ? explode( $value_delimiter, $entry_field['value_raw'] ) : [];

		foreach ( $value_choices as $input => $single_value ) {
			$field['properties'] = $this->field_object->get_field_populated_single_property_value_public( $single_value, sanitize_key( $input ), $field['properties'], $field );
		}

		$this->field_object->field_display( $field, null, $form_data );
	}
}
