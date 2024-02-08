<?php

namespace WPForms\Pro\Helpers;

/**
 * CSV related helper methods.
 *
 * @since 1.7.7
 */
class CSV {

	/**
	 * Formulas start characters.
	 *
	 * @since 1.7.7
	 *
	 * @var array
	 */
	const FORMULAS_START_CHARS = [ '=', '-', '+', '@', "\t", "\r" ];

	/**
	 * Escape string for CSV.
	 *
	 * @since 1.7.7
	 *
	 * @param mixed $value Value to escape.
	 *
	 * @return string
	 */
	public function escape_value( $value ) {

		// Prevent formulas in spreadsheet applications.
		if ( in_array( substr( (string) $value, 0, 1 ), self::FORMULAS_START_CHARS, true ) ) {
			$value = "'" . $value;
		}

		return html_entity_decode( $value, ENT_QUOTES );
	}
}
