<?php

namespace WPForms\Pro\Admin\Entries\Overview;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table', false ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

use WPForms\Admin\Helpers\Datepicker;
use WP_List_Table;
use WP_Post;
use WPForms\Pro\AntiSpam\SpamEntry;
use WPForms_Entries_List;
use WPForms_Entry_Handler;

/**
 * "Entries" overview table which lists all forms.
 * This table can be seen within "WPForms" → "Entries" page.
 *
 * @since 1.8.2
 */
class Table extends WP_List_Table {

	/**
	 * Array of start and end dates
	 * along with number of days in between.
	 *
	 * Responsible for generating "Last X Days".
	 *
	 * @since 1.8.2
	 *
	 * @var array
	 */
	private $timespan;

	/**
	 * An array of start and end dates for database queries.
	 * In the database, all datetime are stored in UTC. It is not possible to change this global setting.
	 *
	 * @since 1.8.2
	 *
	 * @var array
	 */
	private $timespan_mysql;

	/**
	 * Cached object of "entry".
	 *
	 * @since 1.8.2
	 *
	 * @var WPForms_Entry_Handler
	 */
	private $entry_handler;

	/**
	 * An array of entire SQL result set cached for further data sorting and modifications.
	 * The array contains form ids associated with number of entries count.
	 *
	 * @since 1.8.2
	 *
	 * @var array
	 */
	private $total_entry_counts;

	/**
	 * The purpose of this variable is to determine whether
	 * the chart could display the queried form entries
	 * according to the chosen or specified time period.
	 *
	 * The result of the initial database query will also
	 * be used in the "Graph" column in order to avoid
	 * running the database query more than once when
	 * the "timespan" (Last X Days) column is present.
	 *
	 * @since 1.8.2
	 *
	 * @var bool
	 */
	private $form_has_entries_timespan;

	/**
	 * Placeholder character in place of actual data.
	 *
	 * @since 1.8.2
	 */
	const PLACEHOLDER = '&ndash;';

	/**
	 * Initialize the "Overview" table list.
	 *
	 * @since 1.8.2
	 */
	public function __construct() {

		parent::__construct(
			[
				'singular' => 'entry-overview', // Singular name of the listed records.
				'plural'   => 'entries-overview', // Plural name of the listed records.
				'ajax'     => false,
			]
		);

		$this->entry_handler = wpforms()->get( 'entry' );
	}

	/**
	 * Make private timespan properties settable for access within this class.
	 *
	 * @since 1.8.2
	 *
	 * @param array $timespan Array of start and end dates.
	 */
	public function set_timespans( $timespan ) {

		$this->timespan       = $timespan;
		$this->timespan_mysql = Datepicker::process_timespan_mysql( $timespan );
	}

	/**
	 * Determines whether current query has forms to loop over.
	 *
	 * @since 1.8.2
	 *
	 * @return bool
	 */
	public function has_items() {

		if ( $this->items !== null ) {
			return ! empty( $this->items );
		}

		// Check to see if at least one form with respect to user access control has been published.
		$one_published_form = wpforms()->get( 'form' )->get(
			'',
			[
				'fields'                 => 'ids',
				'post_status'            => 'publish',
				'numberposts'            => 1,
				'nopaging'               => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		return ! empty( $one_published_form );
	}

	/**
	 * No forms found text.
	 *
	 * @since 1.8.2
	 */
	public function no_items() {

		esc_html_e( 'No forms found.', 'wpforms' );
	}

	/**
	 * Get list columns.
	 *
	 * @since 1.8.2
	 *
	 * @return array
	 */
	public function get_columns() {

		return [
			'name'       => __( 'Form Name', 'wpforms' ),
			'created'    => __( 'Created', 'wpforms' ),
			'last_entry' => __( 'Last Entry', 'wpforms' ),
			'all_time'   => __( 'All Time', 'wpforms' ),
			'timespan'   => isset( $this->timespan[3] ) ? esc_html( $this->timespan[3] ) : '', // 4th item in the array is always a label.
			'graph'      => __( 'Graph', 'wpforms' ),
		];
	}

	/**
	 * Return "Form Name" column.
	 *
	 * @since 1.8.2
	 *
	 * @param WP_Post $form Form object.
	 *
	 * @return string
	 */
	public function column_name( $form ) {

		$name = ! empty( $form->post_title ) ? $form->post_title : $form->post_name;

		return $this->get_form_entries_url( $form, $name );
	}

	/**
	 * Return "Created" column.
	 *
	 * @since 1.8.2
	 *
	 * @param WP_Post $form Form object.
	 *
	 * @return string
	 */
	public function column_created( $form ) {

		return get_the_date( get_option( 'date_format' ), $form );
	}

	/**
	 * Return "Last Entry" column.
	 *
	 * @since 1.8.4
	 *
	 * @param WP_Post $form Form object.
	 *
	 * @return string
	 */
	public function column_last_entry( $form ) {

		$last_entry = wpforms()->get( 'entry' )->get_last( $form->ID, '', 'date' );

		if ( ! $last_entry ) {
			return self::PLACEHOLDER;
		}

		$entry_url = add_query_arg(
			[
				'page'     => 'wpforms-entries',
				'view'     => 'details',
				'entry_id' => $last_entry->entry_id,
			],
			admin_url( 'admin.php' )
		);

		$label = wpforms_date_format( $last_entry->date, get_option( 'date_format' ) );

		if ( wpforms_current_user_can( 'edit_entry_single', $last_entry->entry_id ) ) {
			return sprintf(
				'<a href="%s">%s</a>',
				esc_url( $entry_url ),
				$label
			);
		}

		return $label;
	}

	/**
	 * Return "All Time" column.
	 *
	 * @since 1.8.2
	 *
	 * @param WP_Post $form Form object.
	 *
	 * @return string
	 */
	public function column_all_time( $form ) {

		$form_id       = $form->ID;
		$total_entries = isset( $this->total_entry_counts[ $form_id ] ) ? absint( $this->total_entry_counts[ $form_id ]->count ) : 0;

		$form_data = wpforms_decode( $form->post_content );

		if ( $total_entries === 0 && ! empty( $form_data['settings']['disable_entries'] ) ) {
			return '&mdash;';
		}

		return $this->get_form_entries_url( $form, $total_entries );
	}

	/**
	 * Return "Last 30 (x) Days" column.
	 *
	 * @since 1.8.2
	 *
	 * @param WP_Post $form Form object.
	 *
	 * @return string
	 */
	public function column_timespan( $form ) {

		list( $start_date, $end_date ) = $this->timespan;

		$total_entries = $this->get_entries_count_by_form( $form );
		$query_string  = [
			'action' => 'filter_date',
			'date'   => sprintf( '%s - %s', $start_date->format( Datepicker::DATE_FORMAT ), $end_date->format( Datepicker::DATE_FORMAT ) ),
		];

		$form_data = wpforms_decode( $form->post_content );

		if ( $total_entries === 0 && ! empty( $form_data['settings']['disable_entries'] ) ) {
			return '&mdash;';
		}

		return $this->get_form_entries_url( $form, $total_entries, $query_string );
	}

	/**
	 * Return "Graph" column.
	 *
	 * @since 1.8.2
	 *
	 * @param WP_Post $form Form object.
	 *
	 * @return string
	 */
	public function column_graph( $form ) {

		// Bail early, if the total number of entries is not available.
		if ( ! $this->form_has_entries_timespan ) {
			return '';
		}

		$buttons   = [];
		$buttons[] = sprintf(
			'<button type="button" class="wpforms-reset-chart dashicons dashicons-dismiss wpforms-hide" title="%s"></button>',
			esc_attr__( 'Reset chart to display all forms', 'wpforms' )
		);
		$buttons[] = sprintf(
			'<button type="button" class="wpforms-show-chart dashicons dashicons-chart-bar" title="%s" data-form="%d"></button>',
			esc_attr__( 'Display only this form data in the graph', 'wpforms' ),
			absint( $form->ID )
		);

		return implode( '', $buttons );
	}

	/**
	 * Remove the pagination links from the top navigation.
	 *
	 * @since 1.8.2
	 *
	 * @param string $which Top or bottom.
	 */
	public function display_tablenav( $which ) {

		// Bail early, if the position is not "bottom".
		if ( $which !== 'bottom' ) {
			return;
		}

		parent::display_tablenav( $which );
	}

	/**
	 * Set _column_headers property for table list.
	 *
	 * @since 1.8.2
	 */
	private function prepare_column_headers() {

		$this->_column_headers = [
			$this->get_columns(),
			get_hidden_columns( $this->screen ),
			$this->get_sortable_columns(),
		];
	}

	/**
	 * List of CSS classes for the "WP_List_Table" table tag.
	 *
	 * @global string $mode List table view mode.
	 *
	 * @since 1.8.2
	 *
	 * @return array
	 */
	protected function get_table_classes() {

		global $mode;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$mode       = get_user_setting( 'posts_list_mode', 'list' );
		$mode_class = esc_attr( 'table-view-' . $mode );
		$classes    = [
			'widefat',
			'striped',
			'wpforms-table-list',
			$mode_class,
		];

		// For styling purposes, we'll add a dedicated class name for determining the number of visible columns.
		// The ideal threshold for applying responsive styling is set at "5" columns based on the need for "Tablet" view.
		$columns_class = $this->get_column_count() > 5 ? 'many' : 'few';

		$classes[] = "has-{$columns_class}-columns";

		return $classes;
	}

	/**
	 * Get a list of sortable columns.
	 *
	 * @since 1.8.2
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {

		return [
			'name'       => [ 'title', false ],
			'created'    => [ 'date', false ],
			'last_entry' => [ 'entry', false ],
			'all_time'   => [ 'entries', false ],
			'timespan'   => [ 'timespan', false ],
		];
	}

	/**
	 * Returns the number of forms to show per page.
	 *
	 * @since 1.8.2
	 *
	 * @return int
	 */
	private function get_per_page() {

		return $this->get_items_per_page( 'wpforms_entries_per_page', $this->entry_handler->get_count_per_page() );
	}

	/**
	 * Returns the `offset` based on the current pagination query.
	 *
	 * @since 1.8.2
	 *
	 * @return int
	 */
	private function get_offset() {

		$per_page     = $this->get_per_page();
		$current_page = $this->get_pagenum();

		if ( 1 < $current_page ) {
			return $per_page * ( $current_page - 1 );
		}

		return 0;
	}

	/**
	 * Prepare table list items.
	 *
	 * @since 1.8.2
	 */
	public function prepare_items() {

		$this->prepare_column_headers();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_GET['order'] ) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'ID';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$offset                   = $this->get_offset();
		$per_page                 = $this->get_per_page();
		$form_ids                 = $this->get_form_ids( $order, $orderby );
		$total_items              = count( $form_ids );
		$in_query_form_ids        = array_splice( $form_ids, $offset, $per_page );
		$this->total_entry_counts = $this->get_total_entry_counts_by_form_ids( $in_query_form_ids );
		$this->items              = $this->build_query( $in_query_form_ids );

		// Set the pagination.
		$this->set_pagination_args(
			[
				'per_page'    => $per_page,
				'total_items' => $total_items,
			]
		);
	}

	/**
	 * Retrieves an array of the latest forms, or forms matching the given criteria.
	 *
	 * @since 1.8.2
	 *
	 * @param array $form_ids An array of post IDs to retrieve.
	 *
	 * @return array
	 */
	private function build_query( $form_ids ) {

		// Bail early, if no forms were found to initiate the query.
		if ( empty( $form_ids ) ) {
			return [];
		}

		return wpforms()->get( 'form' )->get(
			'',
			[
				'orderby'                => 'post__in',
				'post__in'               => $form_ids,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);
	}

	/**
	 * Returns an array of form IDs (int[]).
	 *
	 * @since 1.8.2
	 *
	 * @param string $order   Designates ascending or descending order of forms. Default 'DESC'.
	 * @param string $orderby Sort retrieved forms by parameter. Default 'ID'.
	 *
	 * @return array
	 */
	private function get_form_ids( $order = 'DESC', $orderby = 'ID' ) {

		$exclude = [];

		// Sort the results by the overall number of entries.
		if ( $orderby === 'entries' ) {
			$exclude = $this->sort_by_all_time_entries( $order );
		}

		// Sort by the overall number of entries within a specified timeframe.
		if ( $orderby === 'timespan' ) {
			$exclude = $this->sort_by_entries_in_timespan( $order );
		}

		// Sort by the last entry.
		if ( $orderby === 'entry' ) {
			$exclude = $this->sort_by_last_entry( $order );
		}

		$form_ids = (array) wpforms()->get( 'form' )->get(
			'',
			[
				'fields'                 => 'ids',
				'order'                  => $order,
				'orderby'                => $orderby,
				'exclude'                => $exclude,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		// Form ids from the entries table should be combined with the main query.
		$form_ids = $order === 'ASC' ? array_merge( $form_ids, $exclude ) : array_merge( $exclude, $form_ids );

		return wpforms()->get( 'access' )->filter_forms_by_current_user_capability( $form_ids, 'view_entries_form_single' );
	}

	/**
	 * Retrieves an array of sorted forms based on number of entries.
	 *
	 * @global wpdb $wpdb Instantiation of the wpdb class.
	 *
	 * @since 1.8.2
	 *
	 * @param string $order Designates ascending or descending order of forms. Default 'DESC'.
	 *
	 * @return array
	 */
	private function sort_by_all_time_entries( $order = 'DESC' ) {

		global $wpdb;

		$spam_status  = SpamEntry::ENTRY_STATUS;
		$trash_status = 'trash';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$form_ids = $wpdb->get_col(
			"SELECT DISTINCT form_id, COUNT(entry_id) as count
			FROM {$this->entry_handler->table_name}
			WHERE status NOT IN ( '{$spam_status}', '{$trash_status}' )
			GROUP BY form_id
			ORDER BY count {$order}"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $this->filter_published_form_ids( $form_ids );
	}

	/**
	 * Retrieves an array of sorted forms based on last entry.
	 *
	 * @global wpdb $wpdb Instantiation of the wpdb class.
	 *
	 * @since 1.8.4
	 *
	 * @param string $order Designates ascending or descending order of forms. Default 'DESC'.
	 *
	 * @return array
	 */
	private function sort_by_last_entry( $order = 'DESC' ) {

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$form_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT form_id
				FROM {$this->entry_handler->table_name}
				WHERE status NOT IN ( %s, %s )
				GROUP BY form_id
				ORDER BY MAX(date) {$order}",
				[
					SpamEntry::ENTRY_STATUS,
					WPForms_Entries_List::TRASH_ENTRY_STATUS,
				]
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $this->filter_published_form_ids( $form_ids );
	}

	/**
	 * Retrieves an array of sorted forms based on number of entries.
	 *
	 * @global wpdb $wpdb Instantiation of the wpdb class.
	 *
	 * @since 1.8.2
	 *
	 * @param string $order Designates ascending or descending order of forms. Default 'DESC'.
	 *
	 * @return array
	 */
	private function sort_by_entries_in_timespan( $order = 'DESC' ) {

		global $wpdb;

		list( $start_date, $end_date ) = $this->timespan_mysql;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$form_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT form_id, COUNT(entry_id) as count
				FROM {$this->entry_handler->table_name}
				WHERE date >= %s
				AND date <= %s
				AND status NOT IN ( %s, %s )
				GROUP BY form_id
				ORDER BY count {$order}",
				[
					$start_date->format( Datepicker::DATETIME_FORMAT ),
					$end_date->format( Datepicker::DATETIME_FORMAT ),
					SpamEntry::ENTRY_STATUS,
					'trash',
				]
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $this->filter_published_form_ids( $form_ids );
	}

	/**
	 * Counts the number of entries for a specific form id within the specified timespan.
	 *
	 * @global wpdb $wpdb Instantiation of the wpdb class.
	 *
	 * @since 1.8.2
	 *
	 * @param WP_Post $form Form object.
	 *
	 * @return int
	 */
	private function get_entries_count_by_form( $form ) {

		global $wpdb;

		list( $start_date, $end_date ) = $this->timespan_mysql;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_entries = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(entry_id) as count
				FROM {$this->entry_handler->table_name}
				WHERE form_id = %d
				AND date >= %s
				AND date <= %s
				AND status NOT IN ( %s, %s )",
				[
					$form->ID,
					$start_date->format( Datepicker::DATETIME_FORMAT ),
					$end_date->format( Datepicker::DATETIME_FORMAT ),
					SpamEntry::ENTRY_STATUS,
					'trash',
				]
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->form_has_entries_timespan = $total_entries > 0;

		return $total_entries;
	}

	/**
	 * Retrieves an entire SQL result set from the entries table database (i.e., all applicable rows).
	 * Executes a SQL query and returns the entire SQL result.
	 *
	 * @global wpdb $wpdb Instantiation of the wpdb class.
	 *
	 * @since 1.8.2
	 *
	 * @param array $form_ids An array of post IDs to retrieve.
	 *
	 * @return array
	 */
	private function get_total_entry_counts_by_form_ids( $form_ids ) {

		// Bail early, if no forms were found to initiate the query.
		if ( empty( $form_ids ) ) {
			return [];
		}

		global $wpdb;

		$form_ids_in  = wpforms_wpdb_prepare_in( $form_ids, '%d' );
		$spam_status  = SpamEntry::ENTRY_STATUS;
		$trash_status = 'trash';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results(
			"SELECT DISTINCT form_id, COUNT(entry_id) as count
			FROM {$this->entry_handler->table_name}
			WHERE form_id IN ({$form_ids_in})
			AND status NOT IN ( '{$spam_status}', '{$trash_status}' )
			GROUP BY form_id",
			OBJECT_K
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Removes form ids that are being deleted or that are no longer published from the given stack.
	 *
	 * @since 1.8.2
	 *
	 * @param array $form_ids An array of post IDs to retrieve.
	 *
	 * @return array
	 */
	private function filter_published_form_ids( $form_ids ) {

		// Bail early, if no forms were found to initiate the query.
		if ( empty( $form_ids ) ) {
			return [];
		}

		$form_ids = array_filter(
			$form_ids,
			static function( $form_id ) {

				return get_post_status( $form_id ) === 'publish';
			}
		);

		return array_map( 'absint', $form_ids );
	}

	/**
	 * Get the given form entries page URL.
	 *
	 * @since 1.8.2
	 *
	 * @param WP_Post $form         Form object.
	 * @param string  $text         If provided, displays the given text inside the link element.
	 * @param array   $query_string If provided, merge user defined arguments into defaults query parameters.
	 *
	 * @return string
	 */
	private function get_form_entries_url( $form, $text = self::PLACEHOLDER, $query_string = [] ) {

		// When display text is not provided, leave early.
		if ( $text === self::PLACEHOLDER ) {
			return $text;
		}

		return sprintf(
			'<a href="%s">%s</a>',
			add_query_arg(
				wp_parse_args(
					$query_string,
					[
						'view'    => 'list',
						'form_id' => $form->ID,
						'page'    => 'wpforms-entries',
					]
				),
				admin_url( 'admin.php' )
			),
			esc_html( $text )
		);
	}
}
