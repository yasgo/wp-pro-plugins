<?php

namespace WPForms\Pro\Forms;

/**
 * Class Submission.
 *
 * @since 1.7.4
 */
class Submission extends \WPForms\Forms\Submission {

	/**
	 * Create the fields.
	 *
	 * @since 1.7.4
	 *
	 * @param int $entry_id The entry ID.
	 */
	public function create_fields( $entry_id ) {

		if ( ! $entry_id ) {
			return;
		}

		// Save entry fields.
		wpforms()->get( 'entry_fields' )->save( $this->fields, $this->form_data, $entry_id );

		// Save entry ID.
		wpforms()->get( 'process' )->entry_id = $entry_id;
	}
}
