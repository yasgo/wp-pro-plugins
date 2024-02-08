<?php

namespace WPForms\Pro\SmartTags\SmartTag;

use WPForms\SmartTags\SmartTag\SmartTag;

/**
 * Class EntryDate.
 *
 * @since 1.6.7
 */
class EntryDate extends SmartTag {

	/**
	 * Get smart tag value.
	 *
	 * @since 1.6.7
	 *
	 * @param array  $form_data Form data.
	 * @param array  $fields    List of fields.
	 * @param string $entry_id  Entry ID.
	 *
	 * @return string
	 */
	public function get_value( $form_data, $fields = [], $entry_id = '' ) {

		if ( empty( $entry_id ) ) {
			return '';
		}

		$entry = wpforms()->get( 'entry' )->get( $entry_id );

		if ( ! $entry || ! property_exists( $entry, 'date' ) ) {
			return '';
		}

		$attributes = $this->get_attributes();

		if ( empty( $attributes['format'] ) ) {
			return wpforms_date_format( $entry->date, '', true );
		}

		return wpforms_datetime_format( $entry->date,  $attributes['format'], true );
	}
}
