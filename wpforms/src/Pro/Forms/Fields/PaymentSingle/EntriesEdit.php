<?php

namespace WPForms\Pro\Forms\Fields\PaymentSingle;

/**
 * Editing Payment Single Item entries.
 *
 * @since 1.8.4
 */
class EntriesEdit extends \WPForms\Pro\Forms\Fields\Base\EntriesEdit {

	/**
	 * Constructor.
	 *
	 * @since 1.8.4
	 */
	public function __construct() {

		parent::__construct( 'payment-single' );
	}

	/**
	 * Display the field on the Edit Entry page.
	 *
	 * @since 1.8.4
	 *
	 * @param array $entry_field Entry field data.
	 * @param array $field       Field data and settings.
	 * @param array $form_data   Form data and settings.
	 */
	public function field_display( $entry_field, $field, $form_data ) {

		// We need to update the price key to match the field display method.
		$field['price'] = isset( $entry_field['amount_raw'] ) ? $entry_field['amount_raw'] : '';

		$this->field_object->field_display( $field, null, $form_data );
	}
}
