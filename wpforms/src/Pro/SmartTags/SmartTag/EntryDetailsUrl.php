<?php

namespace WPForms\Pro\SmartTags\SmartTag;

use WPForms\SmartTags\SmartTag\SmartTag;

/**
 * Class EntryDetailsLink.
 *
 * @since 1.7.4
 */
class EntryDetailsUrl extends SmartTag {

	/**
	 * Get smart tag value.
	 *
	 * @since 1.7.4
	 *
	 * @param array  $form_data Form data.
	 * @param array  $fields    List of fields.
	 * @param string $entry_id  Entry ID.
	 *
	 * @return string
	 */
	public function get_value( $form_data, $fields = [], $entry_id = '' ) {

		$entry_id = absint( $entry_id );

		if ( empty( $entry_id ) ) {
			return '';
		}

		return add_query_arg(
			[
				'page'     => 'wpforms-entries',
				'view'     => 'details',
				'entry_id' => $entry_id,
			],
			admin_url( 'admin.php' )
		);
	}
}
