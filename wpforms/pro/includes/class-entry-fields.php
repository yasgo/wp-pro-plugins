<?php

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection AutoloadingIssuesInspection */

/**
 * Entry fields DB class.
 *
 * @since 1.4.3
 */
class WPForms_Entry_Fields_Handler extends WPForms_DB {

	/**
	 * Primary class constructor.
	 *
	 * @since 1.4.3
	 */
	public function __construct() {

		global $wpdb;

		$this->table_name  = $wpdb->prefix . 'wpforms_entry_fields';
		$this->primary_key = 'id';
		$this->type        = 'entry_fields';
	}

	/**
	 * Get table columns.
	 *
	 * @since 1.4.3
	 */
	public function get_columns() {

		return [
			'id'       => '',
			'entry_id' => '%d',
			'form_id'  => '%d',
			'field_id' => '%d',
			'value'    => '%s',
			'date'     => '%s',
		];
	}

	/**
	 * Default column values.
	 *
	 * @since 1.4.3
	 */
	public function get_column_defaults() {

		return [
			'entry_id' => '',
			'form_id'  => '',
			'field_id' => '',
			'value'    => '',
			'date'     => date( 'Y-m-d H:i:s' ),
		];
	}

	/**
	 * Get entry meta rows from the database.
	 *
	 * @since 1.4.3
	 *
	 * @param array $args  Modify the query with these params.
	 * @param bool  $count Whether to return only the number of rows, or rows themselves.
	 *
	 * @return array|int
	 */
	public function get_fields( $args = [], $count = false ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		global $wpdb;

		$defaults = [
			'select'        => 'all',
			'number'        => 30,
			'offset'        => 0,
			'id'            => 0,
			'entry_id'      => 0,
			'form_id'       => 0,
			'field_id'      => 0,
			'value'         => '',
			'value_compare' => '',
			'date'          => '',
			'orderby'       => 'id',
			'order'         => 'DESC',
		];

		$args = apply_filters(
			'wpforms_entry_fields_get_fields_args',
			wp_parse_args( $args, $defaults )
		);

		if ( $args['number'] < 1 ) {
			$args['number'] = PHP_INT_MAX;
		}

		/*
		 * Modify the SELECT.
		 */
		$select = '*';

		$possible_select_values = apply_filters(
			'wpforms_entries_fields_get_fields_select',
			[
				'all'       => '*',
				'entry_ids' => '`entry_id`',
			]
		);

		if ( array_key_exists( $args['select'], $possible_select_values ) ) {
			$select = esc_sql( $possible_select_values[ $args['select'] ] );
		}

		/*
		 * Modify the WHERE.
		 */
		$where = [
			'default' => '1=1',
		];

		// Allowed int arg items.
		$keys = [ 'id', 'entry_id', 'form_id', 'field_id' ];

		foreach ( $keys as $key ) {
			// Value `$args[ $key ]` can be a natural number and a numeric string.
			// We should skip empty string values, but continue working with '0'.
			if (
				! is_array( $args[ $key ] ) &&
				( ! is_numeric( $args[ $key ] ) || $args[ $key ] === 0 )
			) {
				continue;
			}

			if ( is_array( $args[ $key ] ) && ! empty( $args[ $key ] ) ) {
				$ids = implode( ',', array_map( 'intval', $args[ $key ] ) );
			} else {
				$ids = (int) $args[ $key ];
			}

			$where[ 'arg_' . $key ] = "`$key` IN ( $ids )";
		}

		// Processing value and value_compare.
		if ( isset( $args['value'] ) && ! wpforms_is_empty_string( $args['value'] ) ) {

			$escaped_value   = esc_sql( $args['value'] );
			$condition_value = '';

			switch ( $args['value_compare'] ) {
				case '': // Preserving backward compatibility.
				case 'is':
					$condition_value = " = '$escaped_value'";
					break;

				case 'is_not':
					$condition_value = " <> '$escaped_value'";
					break;

				case 'contains':
					$condition_value = " LIKE '%$escaped_value%'";
					break;

				case 'contains_not':
					$condition_value = " NOT LIKE '%$escaped_value%'";
					break;
			}

			$where['arg_value'] = '`value`' . $condition_value;

		// Empty value should be allowed in case certain comparisons are used.
		} elseif ( $args['value_compare'] === 'is' ) {

			$where['arg_value'] = "`value` = ''";

		} elseif ( $args['value_compare'] === 'is_not' ) {

			$where['arg_value'] = "`value` <> ''";

		}

		// Process dates.
		if ( ! empty( $args['date'] ) ) {
			// We can pass array and treat it as a range from:to.
			if ( is_array( $args['date'] ) && count( $args['date'] ) === 2 ) {
				$date_start = wpforms_get_day_period_date( 'start_of_day', strtotime( $args['date'][0] ), 'Y-m-d H:i:s', true );
				$date_end   = wpforms_get_day_period_date( 'end_of_day', strtotime( $args['date'][1] ), 'Y-m-d H:i:s', true );

				if ( ! empty( $date_start ) && ! empty( $date_end ) ) {
					$where['arg_date_start'] = "`date` >= '$date_start'";
					$where['arg_date_end']   = "`date` <= '$date_end'";
				}
			} elseif ( is_string( $args['date'] ) ) {
				/*
				 * If we pass the only string representation of a date -
				 * that means we want to get records of that day only.
				 * So we generate start and end MySQL dates for the specified day.
				 */
				$timestamp  = strtotime( $args['date'] );
				$date_start = wpforms_get_day_period_date( 'start_of_day', $timestamp, 'Y-m-d H:i:s', true );
				$date_end   = wpforms_get_day_period_date( 'end_of_day', $timestamp, 'Y-m-d H:i:s', true );

				if ( ! empty( $date_start ) && ! empty( $date_end ) ) {
					$where['arg_date_start'] = "`date` >= '$date_start'";
					$where['arg_date_end']   = "`date` <= '$date_end'";
				}
			}
		}

		// Give developers an ability to modify WHERE (unset clauses, add new, etc).
		$where     = (array) apply_filters( 'wpforms_entry_fields_get_fields_where', $where, $args );
		$where_sql = implode( ' AND ', $where );

		/*
		 * Modify the ORDER BY.
		 */
		$args['orderby'] = ! array_key_exists( $args['orderby'], $this->get_columns() ) ? $this->primary_key : $args['orderby'];

		if ( 'ASC' === strtoupper( $args['order'] ) ) {
			$args['order'] = 'ASC';
		} else {
			$args['order'] = 'DESC';
		}

		/*
		 * Modify the OFFSET / NUMBER.
		 */
		$args['offset'] = absint( $args['offset'] );
		$args['number'] = absint( $args['number'] );

		/*
		 * Retrieve the results.
		 */

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count === true ) {

			$results = absint(
				$wpdb->get_var(
					"SELECT COUNT( $this->primary_key )
					FROM $this->table_name
					WHERE $where_sql;"
				)
			);

		} else {

			$results = $wpdb->get_results(
				"SELECT $select
				FROM $this->table_name
				WHERE $where_sql
				ORDER BY {$args['orderby']} {$args['order']}
				LIMIT {$args['offset']}, {$args['number']};"
			);

		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $results;
	}

	/**
	 * Save all fields of the entry.
	 *
	 * @since 1.7.3
	 *
	 * @param array $fields    Fields.
	 * @param array $form_data Form data.
	 * @param int   $entry_id  Entry id.
	 * @param bool  $update    Update field if it exists.
	 *
	 * @return void
	 */
	public function save( $fields, $form_data, $entry_id, $update = false ) {

		if ( ! $entry_id ) {
			return;
		}

		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$date = isset( $form_data['date'] ) ? $form_data['date'] : date( 'Y-m-d H:i:s' );

		foreach ( $fields as $field ) {
			$this->save_field( $field, $form_data, $entry_id, $update, $date );
		}
	}

	/**
	 * Save one field of the entry.
	 *
	 * @since 1.7.3
	 *
	 * @param array  $field     Field.
	 * @param array  $form_data Form data.
	 * @param int    $entry_id  Entry id.
	 * @param bool   $update    Update field if it exists.
	 * @param string $date      Date.
	 *
	 * @return void
	 */
	private function save_field( $field, $form_data, $entry_id, $update, $date ) {

		// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
		/**
		 * Filter entry field before saving.
		 *
		 * @since 1.4.3
		 *
		 * @param array $fields    Fields data array.
		 * @param array $form_data Form data.
		 * @param int   $entry_id  Entry id.
		 */
		$field = apply_filters( 'wpforms_entry_save_fields', $field, $form_data, $entry_id );
		// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

		if ( ! isset( $field['id'], $field['value'] ) || $field['value'] === '' ) {
			return;
		}

		$form_id        = $form_data['id'];
		$field_id       = $field['id'];
		$entry_field_id = null;

		if ( $update ) {
			$entry_field_id = $this->get_entry_field_id( $entry_id, $form_id, $field_id );
		}

		$data = [
			'entry_id' => $entry_id,
			'form_id'  => absint( $form_id ),
			'field_id' => absint( $field_id ),
			'value'    => $field['value'],
			'date'     => $date,
		];

		if ( $entry_field_id ) {
			$this->update( $entry_field_id, $data );

			return;
		}

		$this->add( $data );
	}

	/**
	 * Get entry field id.
	 *
	 * @since 1.7.3
	 *
	 * @param int $entry_id Entry id.
	 * @param int $form_id  Form id.
	 * @param int $field_id Field id.
	 *
	 * @return string|null
	 */
	private function get_entry_field_id( $entry_id, $form_id, $field_id ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM $this->table_name WHERE entry_id = %d AND form_id = %d AND field_id = %d LIMIT 1",
				$entry_id,
				$form_id,
				$field_id
			)
		);
	}

	/**
	 * Create custom entry meta database table.
	 *
	 * @since 1.4.3
	 */
	public function create_table() {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			entry_id bigint(20) NOT NULL,
			form_id bigint(20) NOT NULL,
			field_id int(11) NOT NULL,
			value longtext NOT NULL,
			date datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY entry_id (entry_id),
			KEY form_id (form_id),
			KEY field_id (field_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
