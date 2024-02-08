<?php

namespace WPForms\Pro\Forms\Fields\CreditCard;

use WPForms\Forms\Fields\Base\Frontend as FrontendBase;

/**
 * Modern Frontend class for the Payment Credit Card field.
 *
 * @since 1.8.1
 */
class Frontend extends FrontendBase {

	/**
	 * Field display on the form front-end.
	 *
	 * @since 1.8.1
	 *
	 * @param array $field     Field data and settings.
	 * @param array $form_data Form data and settings.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function field_display_modern( $field, $form_data ) {

		// Display warning for non SSL pages.
		if ( ! is_ssl() ) {
			$this->display_ssl_warning();
		}

		// Row wrapper.
		printf(
			'<div class="wpforms-field-row wpforms-field-%s">',
			sanitize_html_class( $field['size'] )
		);

		$this->display_card_number( $field );
		$this->display_cvc( $field );

		echo '</div>';

		// Row wrapper.
		printf(
			'<div class="wpforms-field-row wpforms-field-%s">',
			sanitize_html_class( $field['size'] )
		);

		$this->display_name( $field );
		$this->display_expiration_block( $field );

		echo '</div>';
	}

	/**
	 * Display SSL warning.
	 *
	 * @since 1.8.1
	 */
	private function display_ssl_warning() {

		echo '<div class="wpforms-cc-warning wpforms-error-alert">';
		esc_html_e( 'This page is insecure. Credit Card field should be used for testing purposes only.', 'wpforms' );
		echo '</div>';
	}

	/**
	 * Display Card Number.
	 *
	 * @since 1.8.1
	 *
	 * @param array $field Field data and settings.
	 */
	private function display_card_number( $field ) {

		$number = ! empty( $field['properties']['inputs']['number'] ) ? $field['properties']['inputs']['number'] : [];

		echo '<div ' . wpforms_html_attributes( false, $number['block'] ) . '>';

		$this->field_obj->field_display_sublabel( 'number', 'before', $field );

		printf(
			'<input type="text" %s %s>',
			wpforms_html_attributes( $number['id'], $number['class'], $number['data'], $number['attr'] ),
			! empty( $number['required'] ) ? 'required' : ''
		);

		$this->field_obj->field_display_sublabel( 'number', 'after', $field );
		$this->field_obj->field_display_error( 'number', $field );

		echo '</div>';
	}

	/**
	 * Display CVC.
	 *
	 * @since 1.8.1
	 *
	 * @param array $field Field data and settings.
	 */
	private function display_cvc( $field ) {

		$cvc = ! empty( $field['properties']['inputs']['cvc'] ) ? $field['properties']['inputs']['cvc'] : [];

		echo '<div ' . wpforms_html_attributes( false, $cvc['block'] ) . '>';

		$this->field_obj->field_display_sublabel( 'cvc', 'before', $field );

		printf(
			'<input type="text" %s %s>',
			wpforms_html_attributes( $cvc['id'], $cvc['class'], $cvc['data'], $cvc['attr'] ),
			! empty( $cvc['required'] ) ? 'required' : ''
		);

		$this->field_obj->field_display_sublabel( 'cvc', 'after', $field );

		$this->field_obj->field_display_error( 'cvc', $field );

		echo '</div>';
	}

	/**
	 * Display Name.
	 *
	 * @since 1.8.1
	 *
	 * @param array $field Field data and settings.
	 */
	private function display_name( $field ) {

		$name = ! empty( $field['properties']['inputs']['name'] ) ? $field['properties']['inputs']['name'] : [];

		echo '<div ' . wpforms_html_attributes( false, $name['block'] ) . '>';

		$this->field_obj->field_display_sublabel( 'name', 'before', $field );

		printf(
			'<input type="text" %s %s>',
			wpforms_html_attributes( $name['id'], $name['class'], $name['data'], $name['attr'] ),
			! empty( $name['required'] ) ? 'required' : ''
		);

		$this->field_obj->field_display_sublabel( 'name', 'after', $field );
		$this->field_obj->field_display_error( 'name', $field );

		echo '</div>';
	}

	/**
	 * Display Expiration block.
	 *
	 * @since 1.8.1
	 *
	 * @param array $field Field data and settings.
	 */
	private function display_expiration_block( $field ) {

		echo '<div class="wpforms-field-credit-card-expiration">';

		// Month.
		$this->display_expiration_month( $field );

		// Year.
		$this->display_expiration_year( $field );

		// Sub labels.
		$this->field_obj->field_display_sublabel( 'month', 'after', $field );
		$this->field_obj->field_display_error( 'month', $field );

		echo '</div>';
	}

	/**
	 * Display Expiration Month.
	 *
	 * @since 1.8.1
	 *
	 * @param array $field Field data and settings.
	 */
	private function display_expiration_month( $field ) {

		$month = ! empty( $field['properties']['inputs']['month'] ) ? $field['properties']['inputs']['month'] : [];

		$this->field_obj->field_display_sublabel( 'month', 'before', $field );

		printf(
			'<select %1$s %2$s aria-label="%3$s">',
			wpforms_html_attributes( $month['id'], $month['class'], $month['data'], $month['attr'] ),
			! empty( $month['required'] ) ? 'required' : '',
			esc_attr__( 'Expiration month', 'wpforms' )
		);

		echo '<option class="placeholder" selected disabled>MM</option>';

		foreach ( range( 1, 12 ) as $number ) {
			printf( '<option value="%1$d">%1$d</option>', absint( $number ) );
		}

		echo '</select>';
	}

	/**
	 * Display Expiration Year.
	 *
	 * @since 1.8.1
	 *
	 * @param array $field Field data and settings.
	 */
	private function display_expiration_year( $field ) {

		$year = ! empty( $field['properties']['inputs']['year'] ) ? $field['properties']['inputs']['year'] : [];

		$this->field_obj->field_display_sublabel( 'year', 'before', $field );

		printf(
			'<select %1$s %2$s aria-label="%3$s">',
			wpforms_html_attributes( $year['id'], $year['class'], $year['data'], $year['attr'] ),
			! empty( $year['required'] ) ? 'required' : '',
			esc_attr__( 'Expiration year', 'wpforms' )
		);

		echo '<option class="placeholder" selected disabled>YY</option>';

		$start_year = gmdate( 'y' );
		$end_year   = $start_year + 11;

		for ( $i = $start_year; $i < $end_year; $i++ ) {
			printf( '<option value="%1$d">%1$d</option>', absint( $i ) );
		}

		echo '</select>';
	}
}
