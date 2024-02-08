<?php

namespace WPForms\Pro\Admin\Entries\Overview;

use WPForms\Admin\Helpers\Chart;
use WPForms\Admin\Helpers\Datepicker;
use WP_Post;
use WPForms\Pro\AntiSpam\SpamEntry;

/**
 * "Entries" overview page inside the admin, which lists all forms.
 * This page will be accessible via "WPForms" → "Entries".
 *
 * When requested data is sent via Ajax, this class is responsible for exchanging datasets.
 *
 * @since 1.8.2
 */
class Ajax {

	/**
	 * Hooks.
	 *
	 * @since 1.8.2
	 */
	public function hooks() {

		add_action( 'wp_ajax_wpforms_entries_overview_refresh_chart_dataset_data', [ $this, 'get_chart_dataset_data' ] );
		add_action( 'wp_ajax_wpforms_entries_overview_save_chart_preference_settings', [ $this, 'save_chart_preference_settings' ] );
		add_action( 'wp_ajax_wpforms_entries_overview_flush_chart_active_form_id', [ $this, 'flush_chart_active_form_id' ] );
	}

	/**
	 * Generate and return the data for our dataset data.
	 *
	 * @global wpdb $wpdb Instantiation of the wpdb class.
	 *
	 * @since 1.8.2
	 */
	public function get_chart_dataset_data() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		check_ajax_referer( 'wpforms_entries_overview_nonce' );

		$form_id  = ! empty( $_POST['form'] ) ? absint( $_POST['form'] ) : null;
		$dates    = ! empty( $_POST['dates'] ) ? sanitize_text_field( wp_unslash( $_POST['dates'] ) ) : null;
		$fallback = [
			'total' => 0,
			'data'  => [],
			'name'  => '',
		];

		// If dates for the timespan is missing, leave early.
		if ( ! $dates ) {
			wp_send_json_error( $fallback );
		}

		global $wpdb;

		// Validates and creates date objects of given timespan string.
		$timespans = Datepicker::process_string_timespan( $dates );

		// If the timespan is not validated, leave early.
		if ( ! $timespans ) {
			wp_send_json_error( $fallback );
		}

		// Extract start and end timespans in local (site) and UTC timezones.
		list( $start_date, $end_date, $utc_start_date, $utc_end_date ) = $timespans;

		// WHERE clause for items query statement.
		list( $form_name, $where_clause ) = $this->get_form_where_clause( $form_id );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$table_name = wpforms()->get( 'entry' )->table_name;
		$results    = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT date as day, COUNT(entry_id) as count
				FROM {$table_name}
				WHERE {$where_clause} date BETWEEN %s AND %s
				AND status NOT IN ( %s, %s )
				GROUP BY day
				ORDER BY day ASC",
				[
					$utc_start_date->format( Datepicker::DATETIME_FORMAT ),
					$utc_end_date->format( Datepicker::DATETIME_FORMAT ),
					SpamEntry::ENTRY_STATUS,
					'trash',
				]
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// In case the database's results were empty, leave early.
		if ( empty( $results ) ) {
			wp_send_json_error(
				wp_parse_args(
					[
						'name' => $form_name,
					],
					$fallback
				)
			);
		}

		list( $total_entries, $data ) = Chart::process_chart_dataset_data( $results, $start_date, $end_date );

		wp_send_json_success(
			[
				'total' => $total_entries,
				'data'  => $data,
				'name'  => $form_name,
			]
		);
	}

	/**
	 * Save the user's preferred graph style and color scheme.
	 *
	 * @since 1.8.2
	 */
	public function save_chart_preference_settings() {

		check_ajax_referer( 'wpforms_entries_overview_nonce' );

		$user_id      = get_current_user_id();
		$graph_style  = isset( $_POST['graphStyle'] ) ? absint( $_POST['graphStyle'] ) : 2; // Line.
		$color_scheme = isset( $_POST['colorScheme'] ) ? absint( $_POST['colorScheme'] ) : 1; // WPForms.

		update_user_meta( $user_id, 'wpforms_dash_widget_graph_style', $graph_style );
		update_user_meta( $user_id, 'wpforms_dash_widget_color_scheme', $color_scheme );

		exit();
	}

	/**
	 * Flushes existing active form id from the user metadata.
	 *
	 * @since 1.8.2
	 */
	public function flush_chart_active_form_id() {

		check_ajax_referer( 'wpforms_entries_overview_nonce' );

		$user_id = get_current_user_id();

		delete_user_meta( $user_id, 'wpforms_dash_widget_active_form_id' );

		exit();
	}

	/**
	 * Helper method to build where clause for action select statement.
	 *
	 * Includes:
	 * 1. Optional. Form name.
	 * 2. Where clause for SQL select statement.
	 *
	 * @since 1.8.2
	 *
	 * @param null|int $form_id Optional. Form id.
	 *
	 * @return array
	 */
	private function get_form_where_clause( $form_id ) {

		global $wpdb;

		// Retrieve all forms from which a user can access their entries when no form id is specified.
		$form = wpforms()->get( 'form' )->get( $form_id, [ 'fields' => 'ids' ] );

		// A single form object could be returned, so check that.
		if ( $form instanceof WP_Post && $form->post_status === 'publish' ) {

			$user_id = get_current_user_id();

			// To display statistics in the chart when the page loads, update the active form id provided.
			update_user_meta( $user_id, 'wpforms_dash_widget_active_form_id', (string) $form_id );

			$form_name    = ! empty( $form->post_title ) ? $form->post_title : $form->post_name;
			$where_clause = $wpdb->prepare( 'form_id = %d AND', (int) $form_id );

			return [ $form_name, $where_clause ];
		}

		$form = wpforms()->get( 'access' )->filter_forms_by_current_user_capability( $form, 'view_entries_form_single' );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
		$where_clause = $wpdb->prepare( 'form_id IN ( %1$s ) AND', implode( ',', $form ) );

		return [ '', $where_clause ];
	}
}
