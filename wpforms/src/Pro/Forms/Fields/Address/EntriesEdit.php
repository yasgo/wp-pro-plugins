<?php

namespace WPForms\Pro\Forms\Fields\Address;

/**
 * Editing Address field entries.
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

		parent::__construct( 'address' );
	}

	/**
	 * Enqueues for the Edit Entry page.
	 *
	 * @since 1.6.0
	 */
	public function enqueues() {

		// Load jQuery input mask library - https://github.com/RobinHerbots/jquery.inputmask.
		wp_enqueue_script(
			'wpforms-maskedinput',
			WPFORMS_PLUGIN_URL . 'assets/lib/jquery.inputmask.min.js',
			[ 'jquery' ],
			'5.0.7-beta.29',
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

		$inputs = [ 'address1', 'address2', 'city', 'state', 'postal', 'country' ];
		foreach ( $inputs as $input ) {
			if ( isset( $entry_field[ $input ] ) ) {
				$field['properties'] = $this->field_object->get_field_populated_single_property_value_public( $entry_field[ $input ], $input, $field['properties'], $field );
			}
		}

		$this->field_object->field_display( $field, null, $form_data );
	}
}
