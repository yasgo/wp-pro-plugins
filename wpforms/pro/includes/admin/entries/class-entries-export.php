<?php

/**
 * Export entries to CSV.
 *
 * Inspired by Easy Digital Download's EDD_Export class.
 *
 * @since 1.1.5
 *
 * @deprecated 1.5.5
 */
class WPForms_Entries_Export {

	/**
	 * Entries to export.
	 *
	 * Accepted values:
	 * "all"   - all entries are exported
	 * (int)   - ID of specific entry to export
	 * (array) - an array of IDs to export
	 *
	 * @since 1.1.5
	 * @var string
	 */
	public $entry_type = 'all';

	/**
	 * Entry object, when exporting a single entry.
	 *
	 * @since 1.1.5
	 * @var object
	 */
	public $entry;

	/**
	 * Specific fields to export.
	 *
	 * Default is blank which exports all fields.
	 * Also accepts array of field IDs.
	 *
	 * @since 1.1.5
	 * @var mixed
	 */
	public $fields = '';

	/**
	 * Form ID.
	 *
	 * @since 1.1.5
	 * @var int
	 */
	public $form_id;

	/**
	 * Form data and settings.
	 *
	 * @since 1.1.5
	 * @var int
	 */
	public $form_data;

	/**
	 * File pointer resource.
	 *
	 * @since 1.4.0
	 * @var null
	 */
	public $file;

	/**
	 * All fields.
	 *
	 * @since 1.5.5
	 *
	 * @return array
	 */
	public function all_fields() {

		return [
			'text',
			'textarea',
			'number-slider',
			'select',
			'radio',
			'checkbox',
			'gdpr-checkbox',
			'email',
			'address',
			'url',
			'name',
			'hidden',
			'date-time',
			'phone',
			'number',
			'file-upload',
			'rating',
			'likert_scale',
			'payment-single',
			'payment-multiple',
			'payment-checkbox',
			'payment-select',
			'payment-total',
			'signature',
			'net_promoter_score',
		];
	}

	/**
	 * Field types that are allowed in entry exports.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function allowed_fields() {

		$fields = (array) apply_filters_deprecated(
			'wpforms_export_fields_allowed',
			[ $this->all_fields() ],
			'1.5.5 of the WPForms plugin',
			'wpforms_pro_admin_entries_export_configuration'
		);

		return $fields;
	}

	/**
	 * Are we exporting a single entry or multiple.
	 *
	 * @since 1.1.5
	 *
	 * @return bool
	 */
	public function is_single_entry() {

		if ( 'all' === $this->entry_type || is_array( $this->entry_type ) ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Set the export headers.
	 *
	 * @since 1.1.5
	 */
	public function headers() {

		$this->form_id = absint( $_GET['form_id'] );

		ignore_user_abort( true );

		wpforms_set_time_limit();

		if ( ! $this->is_single_entry() ) {
			$file_name = 'wpforms-' . sanitize_file_name( get_the_title( $this->form_id ) ) . '-' . date( 'm-d-Y' ) . '.csv';
		} else {
			$file_name = 'wpforms-' . sanitize_file_name( get_the_title( $this->form_id ) ) . '-entry' . absint( $this->entry_type ) . '-' . date( 'm-d-Y' ) . '.csv';
		}

		// Headers to send.
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $file_name );
		header( 'Content-Transfer-Encoding: binary' );

		// Create file pointer connected to the output stream.
		$this->file = fopen( 'php://output', 'w' );

		// Hack for MS Excel to correctly read UTF8 CSVs.
		// See https://www.skoumal.net/en/making-utf-8-csv-excel/.
		$bom = chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF );
		fputs( $this->file, $bom );
	}

	/**
	 * Retrieve the CSV columns.
	 *
	 * @since 1.1.5
	 *
	 * @return array $cols Array of the columns
	 */
	public function get_csv_cols() {

		$cols = [];

		// If we are exporting a single entry we do not need to reference the
		// form and can export by looking at the field contained within the
		// entry object. For multiple entry export we get the fields from the
		// form.
		if ( $this->is_single_entry() ) {
			$this->entry  = wpforms()->get( 'entry' )->get( $this->entry_type );
			$this->fields = wpforms_decode( $this->entry->fields );
		} else {
			$this->form_data = wpforms()->get( 'form' )->get(
				$this->form_id,
				[
					'content_only' => true,
					'cap'          => 'view_entries_form_single',
				]
			);

			$this->fields = $this->form_data['fields'];
		}

		// Get field types now allowed (eg exclude page break, divider, etc).
		$allowed = $this->allowed_fields();

		// Add whitelisted fields to export columns.
		foreach ( $this->fields as $id => $field ) {
			if ( in_array( $field['type'], $allowed, true ) ) {
				if ( $this->is_single_entry() ) {
					$cols[ $field['id'] ] = wpforms_decode_string( $field['name'] );
				} else {
					$cols[ $field['id'] ] = wpforms_decode_string( $field['label'] );
				}
			}
		}

		$cols['date']     = esc_html__( 'Date', 'wpforms' );
		$cols['date_gmt'] = esc_html__( 'Date GMT', 'wpforms' );
		$cols['entry_id'] = esc_html__( 'ID', 'wpforms' );

		return apply_filters( 'wpforms_export_get_csv_cols', $cols, $this->entry_type );
	}

	/**
	 * Output the CSV columns.
	 *
	 * @since 1.1.5
	 */
	public function csv_cols_out() {

		$sep  = $this->get_csv_export_separator();
		$cols = $this->get_csv_cols();

		fputcsv( $this->file, $cols, $sep );
	}

	/**
	 * Get the data being exported.
	 *
	 * @since 1.1.5
	 *
	 * @return array $data Data for Export
	 */
	public function get_data() {

		$allowed = $this->allowed_fields();
		$data    = [];

		if ( $this->is_single_entry() ) :

			// For single entry exports we have the needed fields already
			// and no more queries are necessary.
			foreach ( $this->fields as $id => $field ) {
				if ( in_array( $field['type'], $allowed, true ) ) {
					$data[1][ $field['id'] ] = wpforms_decode_string( $field['value'] );
				}
			}
			$data[1]['date']     = wpforms_datetime_format( $this->entry->date, '', true );
			$data[1]['date_gmt'] = wpforms_datetime_format( $this->entry->date, '' );
			$data[1]['entry_id'] = absint( $this->entry->entry_id );

		else :

			// All or multiple entry export.
			$args        = [
				'number'  => - 1,
				//'entry_id' => is_array( $this->entry_type ) ? $this->entry_type : '', @todo
				'form_id' => $this->form_id,
			];
			$entries     = wpforms()->get( 'entry' )->get_entries( $args );
			$form_fields = $this->form_data['fields'];

			foreach ( $entries as $entry ) {

				$fields = wpforms_decode( $entry->fields );

				foreach ( $form_fields as $form_field ) {
					if ( in_array( $form_field['type'], $allowed, true ) ) {
						if ( array_key_exists( $form_field['id'], $fields ) ) {
							$data[ $entry->entry_id ][ $form_field['id'] ] = wpforms_decode_string( $fields[ $form_field['id'] ]['value'] );
						} else {
							$data[ $entry->entry_id ][ $form_field['id'] ] = '';
						}
					}
				}
				$data[ $entry->entry_id ]['date']     = wpforms_datetime_format( $entry->date, '', true );
				$data[ $entry->entry_id ]['date_gmt'] = wpforms_datetime_format( $entry->date, '' );
				$data[ $entry->entry_id ]['entry_id'] = absint( $entry->entry_id );
			}

		endif;

		$data = apply_filters( 'wpforms_export_get_data', $data, $this->entry_type );

		return $data;
	}

	/**
	 * Get a data separator, used for CSV export file.
	 *
	 * @since 1.4.1
	 *
	 * @return string
	 */
	public function get_csv_export_separator() {

		$separator = apply_filters_deprecated(
			'wpforms_csv_export_seperator',
			[ ',' ],
			'1.4.1 of the WPForms plugin',
			'wpforms_csv_export_separator'
		);

		return apply_filters( 'wpforms_csv_export_separator', $separator );
	}

	/**
	 * Output the CSV rows.
	 *
	 * @since 1.1.5
	 */
	public function csv_rows_out() {

		$sep  = $this->get_csv_export_separator();
		$data = $this->get_data();
		$cols = $this->get_csv_cols();
		$rows = [];
		$i    = 0;

		// First, compile each row.
		foreach ( $data as $row ) {

			foreach ( $row as $col_id => $column ) {
				// Make sure the column is valid.
				if ( array_key_exists( $col_id, $cols ) ) {
					$data         = str_replace( "\n", "\r\n", trim( $column ) );
					$rows[ $i ][] = $data;
				}
			}
			$i ++;
		}

		// Second, now write each row.
		foreach ( $rows as $row ) {
			fputcsv( $this->file, $row, $sep );
		}
	}

	/**
	 * Perform the export.
	 *
	 * @since 1.1.5
	 */
	public function export() {

		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $form_id ) || ! wpforms_current_user_can( 'view_entries_form_single', $form_id ) ) {
			wp_die(
				esc_html__( 'You do not have permission to export entries.', 'wpforms' ),
				esc_html__( 'Error', 'wpforms' ),
				[
					'response' => 403,
				]
			);
		}

		// Set headers.
		$this->headers();

		// Output CSV columns (headers).
		$this->csv_cols_out();

		// Output CSV rows.
		$this->csv_rows_out();

		die();
	}
}
