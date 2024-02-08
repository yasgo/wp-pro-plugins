<?php

namespace WPForms\Pro\Forms\Fields\PaymentTotal;

use WPForms\Forms\Fields\Base\Frontend as FrontendBase;

/**
 * Frontend class for the Payment Total field.
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
		add_filter( 'wpforms_field_properties_payment-total', [ $this, 'field_properties_modern' ], 0, 3 );
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

		// Screen readers must read the updated value.
		$properties['container']['attr']['aria-live']   = 'polite';
		$properties['container']['attr']['aria-atomic'] = 'true';

		return $properties;
	}
}
