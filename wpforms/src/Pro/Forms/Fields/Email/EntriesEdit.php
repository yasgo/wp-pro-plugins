<?php

namespace WPForms\Pro\Forms\Fields\Email;

/**
 * Editing Email field entries.
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

		parent::__construct( 'email' );
	}

	/**
	 * Enqueues for the Edit Entry page.
	 *
	 * @since 1.6.0
	 */
	public function enqueues() {

		wp_enqueue_script(
			'wpforms-mailcheck',
			WPFORMS_PLUGIN_URL . 'assets/lib/mailcheck.min.js',
			false,
			'1.1.2',
			true
		);
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

		// Disable Email confirmation subfield.
		unset( $field['confirmation'] );
		$field['properties']['inputs']['primary']['class'][] = 'wpforms-field-' . sanitize_html_class( $field['size'] );

		parent::field_display( $entry_field, $field, $form_data );
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

		$form_id = (int) $form_data['id'];

		$field_submit = ! empty( $field_submit['primary'] ) ? $field_submit['primary'] : $field_submit;

		$this->field_object->validate( $field_id, $field_submit, $form_data );

		// Tweak error for the email fields without confirmation.
		if ( empty( $form_data['fields'][ $field_id ]['confirmation'] ) && ! empty( wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['primary'] ) ) {
			wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ] = wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['primary'];
		}
	}
}
