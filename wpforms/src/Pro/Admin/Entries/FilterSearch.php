<?php

namespace WPForms\Pro\Admin\Entries;

/**
 * Entries FilterSearch trait.
 *
 * @since 1.6.9
 */
trait FilterSearch {

	/**
	 * Array of filtering arguments.
	 *
	 * @since 1.6.9
	 *
	 * @var array
	 */
	protected $filter = [];

	/**
	 * Watch for filtering requests from a search field.
	 *
	 * @since 1.6.9
	 */
	public function process_filter_search() {

		$form_id = $this->get_filtered_form_id();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended

		// Check for run switch and that all data is present.
		if (
			! $form_id ||
			! isset( $_REQUEST['search'] ) ||
			(
				! isset( $_REQUEST['search']['term'] ) ||
				! isset( $_REQUEST['search']['field'] ) ||
				empty( $_REQUEST['search']['comparison'] )
			)
		) {
			return;
		}

		$term = sanitize_text_field( wp_unslash( $_REQUEST['search']['term'] ) );

		/*
		 * Because empty fields were not migrated to a fields table in 1.4.3, we don't have that data
		 * and can't filter those with empty values.
		 * The current workaround - display all entries (instead of none at all).
		 */
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( wpforms_is_empty_string( $term ) && wpforms_is_empty_string( $_REQUEST['search']['term'] ) ) {
			return;
		}

		// Prepare the data.
		$field      = sanitize_text_field( wp_unslash( $_REQUEST['search']['field'] ) ); // We must use as a string for the work field with id equal 0.
		$comparison = sanitize_text_field( wp_unslash( $_REQUEST['search']['comparison'] ) );
		$comparison = in_array( $comparison, [ 'contains', 'contains_not', 'is', 'is_not' ], true ) ? $comparison : 'contains';

		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$args = [
			'select'        => 'entry_ids',
			'form_id'       => $form_id,
			'value'         => $term,
			'value_compare' => $comparison,
		];

		if ( is_numeric( $field ) ) {
			$args['field_id'] = $field;
		} else {
			$args['advanced_search'] = $field !== 'any' ? $field : '';
		}

		$this->filter = array_merge(
			$this->filter,
			$args,
			[
				'is_filtered' => true,
				'select'      => 'all',
			]
		);

		// We shouldn't limit searching by the fields.
		// Limiting the results will be done later in `WPForms_Entry_Handler::get_entries()`.
		$args['number'] = -1;

		$entries = '';

		if ( empty( $args['advanced_search'] ) && $field !== 'any' ) {
			$entries = wpforms()->get( 'entry_fields' )->get_fields( $args );
		}

		$this->prepare_entry_ids_for_get_entries_args( $entries );

		add_filter( 'wpforms_entry_handler_get_entries_args', [ $this, 'get_filtered_entry_table_args' ] );
	}

	/**
	 * Get the entry IDs based on the entries array and pass it further to the
	 * WPForms_Entry_Handler::get_entries() method via a filter.
	 *
	 * @since 1.6.9
	 *
	 * @param array $entries Entries search by form fields result set.
	 */
	protected function prepare_entry_ids_for_get_entries_args( $entries ) {

		$entry_ids = [];

		if ( is_array( $entries ) ) {
			$entry_ids = wp_list_pluck( $entries, 'entry_id' );
		}

		$entry_ids = array_unique( $entry_ids );

		$this->filter['entry_id'] = $entry_ids;

		// In case of Advanced Search and if some html entered to the search box we need to return nothing.
		if (
			empty( $this->filter['value'] ) &&
			! empty( $_REQUEST['search']['term'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			! empty( $this->filter['advanced_search'] )
		) {
			$this->filter['entry_id'] = '0';
		}
	}

	/**
	 * Merge default arguments to entries retrieval with the one we send to filter.
	 *
	 * @since 1.6.9
	 *
	 * @param array $args Arguments.
	 *
	 * @return array Filtered arguments.
	 */
	public function get_filtered_entry_table_args( $args ) {

		if ( empty( $this->filter['is_filtered'] ) ) {
			return $args;
		}

		return array_merge( $args, $this->filter );
	}

	/**
	 * Get filtered form ID.
	 *
	 * @since 1.6.9
	 *
	 * @return int
	 */
	private function get_filtered_form_id() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_REQUEST['form_id'] ) ) {
			return absint( $_REQUEST['form_id'] );
		}

		return ! empty( $_REQUEST['form'] ) ? absint( $_REQUEST['form'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
