<?php

namespace WPForms\Pro\Forms\Fields\Base;

/**
 * Editing field entries.
 *
 * @since 1.6.0
 */
class EntriesEdit {

	/**
	 * WPForms Field object.
	 *
	 * @since 1.6.0
	 *
	 * @var \WPForms_Field
	 */
	protected $field_object;

	/**
	 * Constructor.
	 *
	 * @since 1.6.0
	 *
	 * @param string $type Field type.
	 */
	public function __construct( $type = '' ) {

		if ( ! empty( $type ) ) {
			$this->field_object = apply_filters( "wpforms_fields_get_field_object_{$type}", null );
		}
	}

	/**
	 * Enqueues for the Edit Entry page.
	 *
	 * @since 1.6.0
	 */
	public function enqueues() {}

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

		$value = isset( $entry_field['value'] ) ? $entry_field['value'] : '';

		if ( $value !== '' ) {
			$field['properties'] = $this->field_object->get_field_populated_single_property_value_public( (string) $value, 'primary', $field['properties'], $field );
		}

		$this->field_object->field_display( $field, null, $form_data );
	}

	/**
	 * Validate submitted field data on edit entry page.
	 *
	 * @since 1.6.0
	 *
	 * @param int   $field_id     Field ID.
	 * @param mixed $field_submit Field value that was submitted.
	 * @param mixed $field_data   Existing field data.
	 * @param array $form_data    Form data and settings.
	 */
	public function validate( $field_id, $field_submit, $field_data, $form_data ) {

		$this->field_object->validate( $field_id, $field_submit, $form_data );
	}

	/**
	 * Format and sanitize field while processing edit entry.
	 *
	 * @since 1.6.0
	 *
	 * @param int   $field_id     Field ID.
	 * @param mixed $field_submit Field value that was submitted.
	 * @param mixed $field_data   Existing field data.
	 * @param array $form_data    Form data and settings.
	 */
	public function format( $field_id, $field_submit, $field_data, $form_data ) {

		$this->field_object->format( $field_id, $field_submit, $form_data );
	}
}
