<?php

namespace WPForms\Pro\Reports;

use DateTime;
use WPForms\Pro\AntiSpam\SpamEntry;

/**
 * Generate form submissions reports.
 *
 * @since 1.5.4
 */
class EntriesCount {

	/**
	 * Get entries count grouped by $param.
	 * Main point of entry to fetch form entry count data from DB.
	 *
	 * @since 1.5.4
	 *
	 * @param string $param        Could be 'date' or 'form'.
	 * @param int    $form_id      Form ID to fetch the data for.
	 * @param int    $days         Timespan (in days) to fetch the data for.
	 * @param string $date_end_str End date of the timespan (PHP DateTime supported string, see http://php.net/manual/en/datetime.formats.php).
	 *
	 * @return array
	 */
	public function get_by( $param, $form_id = 0, $days = 0, $date_end_str = 'yesterday' ) {

		if ( ! in_array( $param, [ 'date', 'form' ], true ) ) {
			return [];
		}

		try {
			$now            = new DateTime();
			$utc_date_end   = new DateTime( $date_end_str );
			$utc_date_start = new DateTime( $date_end_str );
		} catch ( \Exception $e ) {
			return [];
		}

		$modify_offset  = (float) get_option( 'gmt_offset' ) * 60 . ' minutes';
		$utc_date_end   = $utc_date_end
			->setTime( $now->format( 'H' ), $now->format( 'i' ), $now->format( 's' ) )
			->modify( $modify_offset )
			->setTime( 23, 59, 59 );
		$utc_date_start = $utc_date_start
			->setTime( $now->format( 'H' ), $now->format( 'i' ), $now->format( 's' ) )
			->modify( $modify_offset )
			->modify( '-' . ( absint( $days ) - 1 ) . 'days' )
			->setTime( 0, 0 );

		if ( $param === 'date' ) {
			return $this->get_by_date_sql( $form_id, $utc_date_start, $utc_date_end );
		}

		if ( $param === 'form' ) {
			return $this->get_by_form_sql( $form_id, $utc_date_start, $utc_date_end );
		}

		return [];
	}

	/**
	 * Get entries count grouped by date.
	 * In most cases it's better to use `get_by( 'date' )` instead.
	 *
	 * Warning! Avoid GTM offsets: we are searching with offset by default.
	 *
	 * @since 1.5.4
	 * @since 1.6.5 Fixed GTM offset.
	 * @since 1.7.6 Count entries only for published forms.
	 *
	 * @param int           $form_id        Form ID to fetch the data for.
	 * @param DateTime|null $utc_date_start Start date for the search. Leave it empty to restrict the starting day.
	 * @param DateTime|null $utc_date_end   End date for the search. Leave it empty to restrict the ending day.
	 *
	 * @return array
	 */
	public function get_by_date_sql( $form_id = 0, $utc_date_start = null, $utc_date_end = null ) {

		global $wpdb;

		$table_name = wpforms()->get( 'entry' )->table_name;
		$forms      = $this->get_allowed_forms( $form_id );

		$access_obj = wpforms()->get( 'access' );

		if ( $access_obj ) {
			$forms = $access_obj->filter_forms_by_current_user_capability( $forms, 'view_entries_form_single' );
		}

		if ( empty( $forms ) ) {
			return [];
		}

		$sql = $wpdb->prepare(
			"SELECT CAST(DATE_ADD(date, INTERVAL %d MINUTE) AS DATE) as day, COUNT( entry_id ) as count FROM $table_name", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			(float) get_option( 'gmt_offset' ) * 60
		);

		$sql .= $this->prepare_where_conditions( $forms, $utc_date_start, $utc_date_end );
		$sql .= ' GROUP BY day ORDER BY day;';

		return (array) $wpdb->get_results( $sql, OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get entries count grouped by form.
	 * In most cases it's better to use `get_by( 'form' )` instead.
	 *
	 * Warning! Avoid GTM offsets! We are searching with offset by default.
	 *
	 * @since 1.5.4
	 * @since 1.6.5 Fixed GTM offset.
	 * @since 1.7.6 Count entries only for published forms.
	 *
	 * @param int           $form_id        Form ID to fetch the data for.
	 * @param DateTime|null $utc_date_start Start date for the search. Leave it empty to restrict the starting day.
	 * @param DateTime|null $utc_date_end   End date for the search. Leave it empty to restrict the ending day.
	 *
	 * @return array
	 */
	public function get_by_form_sql( $form_id = 0, $utc_date_start = null, $utc_date_end = null ) {

		global $wpdb;

		$table_name = wpforms()->get( 'entry' )->table_name;
		$forms      = $this->get_allowed_forms( $form_id );

		if ( empty( $forms ) ) {
			return [];
		}

		$sql = "SELECT form_id, COUNT( entry_id ) as count FROM $table_name";

		$sql .= $this->prepare_where_conditions( $forms, $utc_date_start, $utc_date_end );
		$sql .= ' GROUP BY form_id ORDER BY count DESC;';

		$results = (array) $wpdb->get_results( $sql, OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		return $this->fill_forms_list_form_data( $results );
	}

	/**
	 * Fill a forms list with the data needed for a frontend display.
	 *
	 * @since 1.5.4
	 *
	 * @param array $results DB results from `$wpdb->prepare()`.
	 *
	 * @return array
	 */
	public function fill_forms_list_form_data( $results ) {

		if ( ! is_array( $results ) ) {
			return [];
		}

		$processed = [];

		foreach ( $results as $form_id => $result ) {
			$form = wpforms()->get( 'form' )->get( $form_id );

			if ( empty( $form ) ) {
				continue;
			}

			$edit_url = add_query_arg(
				[
					'page'    => 'wpforms-entries',
					'view'    => 'list',
					'form_id' => absint( $form->ID ),
				],
				admin_url( 'admin.php' )
			);

			$processed[ $form->ID ] = [
				'form_id'  => $form->ID,
				'count'    => isset( $results[ $form->ID ]->count ) ? absint( $results[ $form->ID ]->count ) : 0,
				'title'    => $form->post_title,
				'edit_url' => $edit_url,
			];
		}

		return $processed;
	}

	/**
	 * Prepare where conditions.
	 *
	 * @since 1.6.5
	 * @since 1.7.6 Changed $form_id argument to the $forms.
	 *
	 * @param array         $forms          List of form IDs.
	 * @param DateTime|null $utc_date_start Start date for the search. Leave it empty to restrict the starting day.
	 * @param DateTime|null $utc_date_end   End date for the search. Leave it empty to restrict the ending day.
	 *
	 * @return string
	 */
	private function prepare_where_conditions( $forms = [], $utc_date_start = null, $utc_date_end = null ) {

		global $wpdb;

		$format        = 'Y-m-d H:i:s';
		$placeholders  = $forms;
		$sql           = ' WHERE form_id IN ( ' . implode( ', ', array_fill( 0, count( $forms ), '%d' ) ) . ' )';
		$modify_offset = (float) get_option( 'gmt_offset' ) * 60 . ' minutes';

		if ( $utc_date_start !== null ) {
			$sql .= ' AND date >= %s';

			$utc_date_start = clone $utc_date_start;

			$utc_date_start->modify( $modify_offset );

			$placeholders[] = $utc_date_start->format( $format );
		}

		if ( $utc_date_end !== null ) {
			$sql .= ' AND date <= %s';

			$utc_date_end = clone $utc_date_end;

			$utc_date_end->modify( $modify_offset );

			$placeholders[] = $utc_date_end->format( $format );
		}

		// Exclude spam entries.
		$sql           .= ' AND status NOT IN ( %s, %s )';
		$placeholders[] = SpamEntry::ENTRY_STATUS;
		$placeholders[] = 'trash';

		return $wpdb->prepare( $sql, $placeholders ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get list of forms with needed access control capabilities and published post status.
	 *
	 * @since 1.7.6
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array
	 */
	private function get_allowed_forms( $form_id = 0 ) {

		if ( $form_id ) {
			return wpforms()->get( 'form' )->get( $form_id ) && get_post_status( $form_id ) === 'publish' ? [ $form_id ] : [];
		}

		return wpforms()->get( 'form' )->get( '', [ 'fields' => 'ids' ] );
	}
}
