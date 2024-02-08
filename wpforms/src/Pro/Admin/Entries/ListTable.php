<?php

namespace WPForms\Pro\Admin\Entries;

use WP_List_Table;
use WPForms\Admin\Notice;
use WPForms\Db\Payments\ValueValidator;
use WPForms\Pro\Admin\DashboardWidget;
use WPForms\Pro\Admin\Entries\Table\Facades\Columns;

// IMPORTANT NOTICE:
// This line is needed to prevent fatal errors in the third-party plugins.
// We know about Jetpack (probably others also) can load WP classes during cron jobs or something similar.
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Generate the table on the entries overview page.
 *
 * @since 1.8.6
 */
class ListTable extends WP_List_Table {

	// @todo: remove those phpcs lines when deprecated version will be set as a real number, it is a bug in phpcs.
	// phpcs:disable WPForms.Comments.DeprecatedTag.InvalidDeprecatedVersion
	/**
	 * The ID of the table column called "Entry ID".
	 *
	 * @since 1.8.6
	 * @deprecated 1.8.6. Use \WPForms\Pro\Admin\Entries\Table\Facades\Columns::COLUMN_ENTRY_ID instead.
	 *
	 * @var int
	 */
	const COLUMN_ENTRY_ID = -1;

	/**
	 * The ID of the table column called "Entry Notes".
	 *
	 * @since 1.8.6
	 * @deprecated 1.8.6. Use \WPForms\Pro\Admin\Entries\Table\Facades\Columns::COLUMN_NOTES_COUNT instead.
	 *
	 * @var int
	 */
	const COLUMN_NOTES_COUNT = -2;
	// phpcs:enable WPForms.Comments.DeprecatedTag.InvalidDeprecatedVersion

	/**
	 * Number of entries to show per page.
	 *
	 * @since 1.8.6
	 *
	 * @var int
	 */
	public $per_page;

	/**
	 * Form data as an array.
	 *
	 * @since 1.8.6
	 *
	 * @var array
	 */
	public $form_data;

	/**
	 * Form id.
	 *
	 * @since 1.8.6
	 *
	 * @var string|integer
	 */
	public $form_id;

	/**
	 * Number of different entry types.
	 *
	 * @since 1.8.6
	 *
	 * @var int
	 */
	public $counts;

	/**
	 * Date and time format.
	 *
	 * @since 1.8.6
	 *
	 * @var array
	 */
	private $datetime_format;

	/**
	 * Primary class constructor.
	 *
	 * @since 1.8.6
	 */
	public function __construct() {

		// Utilize the parent constructor to build the main class properties.
		parent::__construct(
			[
				'singular' => 'entry',
				'plural'   => 'entries',
				'ajax'     => false,
				'screen'   => 'entries',
			]
		);

		// Default number of forms to show per page.
		$this->per_page = wpforms()->get( 'entry' )->get_count_per_page();

		// Date and time formats.
		$this->datetime_format = [
			'date' => get_option( 'date_format' ),
			'time' => get_option( 'time_format' ),
		];

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.8.6
	 */
	private function hooks() {

		// Add trashed views.
		add_filter( 'wpforms_entries_table_views', [ $this, 'add_trashed_views' ] );
	}

	/**
	 * List of CSS classes for the "WP_List_Table" table tag.
	 *
	 * @global string $mode List table view mode.
	 *
	 * @since 1.8.6
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
			$mode_class,
		];

		// For styling purposes, we'll add a dedicated class name for determining the number of visible columns.
		// The ideal threshold for applying responsive styling is set at "5" columns based on the need for "Tablet" view.
		$columns_class = $this->get_column_count() > 5 ? 'many' : 'few';

		$classes[] = "has-{$columns_class}-columns";

		// Add trash class.
		if ( $this->is_trash_list() ) {
			$classes[] = 'wpforms-entries-table-trash';
		}

		/**
		 * Filters the list of CSS classes for the WP_List_Table table tag.
		 *
		 * @since 1.8.3
		 *
		 * @param string[] $classes   An array of CSS classes for the table tag.
		 * @param array    $form_data Form data.
		 */
		return apply_filters( 'wpforms_entries_table_get_table_classes', $classes, $this->form_data ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Get the entry counts for various types of entries.
	 *
	 * @since 1.8.6
	 */
	public function get_counts() {

		$this->counts = [];
		$entry_obj    = wpforms()->get( 'entry' );

		$this->counts['total'] = $entry_obj->get_entries(
			[
				'form_id' => $this->form_id,
			],
			true
		);

		$this->counts['unread'] = $entry_obj->get_entries(
			[
				'form_id' => $this->form_id,
				'viewed'  => '0',
			],
			true
		);

		$this->counts['starred'] = $entry_obj->get_entries(
			[
				'form_id' => $this->form_id,
				'starred' => '1',
			],
			true
		);

		$this->counts['trash'] = $entry_obj->get_entries(
			[
				'form_id' => $this->form_id,
				'status'  => Page::TRASH_ENTRY_STATUS,
			],
			true
		);

		// Only show the payment view if the form has a payment field.
		if ( wpforms_has_payment( 'form', $this->form_data ) ) {
			$this->counts['payment'] = wpforms()->get( 'entry' )->get_entries(
				[
					'form_id' => $this->form_id,
					'type'    => 'payment',
				],
				true
			);
		}

		/**
		 * Filters the array of entries counts in different views.
		 *
		 * @since 1.3.3
		 *
		 * @param int[] $counts    An array of entries' counts.
		 * @param array $form_data Form data.
		 */
		$this->counts = apply_filters( 'wpforms_entries_table_counts', $this->counts, $this->form_data ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Retrieve the view types.
	 *
	 * @since 1.8.6
	 */
	public function get_views() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$base = remove_query_arg( [ 'type', 'status', 'paged', 'message' ] );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		$total   = '&nbsp;<span class="count">(<span class="total-num">' . $this->counts['total'] . '</span>)</span>';
		$unread  = '&nbsp;<span class="count">(<span class="unread-num">' . $this->counts['unread'] . '</span>)</span>';
		$starred = '&nbsp;<span class="count">(<span class="starred-num">' . $this->counts['starred'] . '</span>)</span>';
		$all     = empty( $_GET['status'] ) && ( $current === 'all' || empty( $current ) ) ? ' class="current"' : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$views = [
			'all'    => sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( $base ),
				$all,
				esc_html__( 'All', 'wpforms' ) . $total
			),
			'unread' => sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( add_query_arg( 'type', 'unread', $base ) ),
				$current === 'unread' ? ' class="current"' : '',
				esc_html__( 'Unread', 'wpforms' ) . $unread
			),
		];

		// Only show the payment view if the form has a payment field.
		// Add the "payment" view after the "unread" view.
		if ( isset( $this->counts['payment'] ) ) {
			$payment          = '&nbsp;<span class="count">(<span class="payment-num">' . $this->counts['payment'] . '</span>)</span>';
			$views['payment'] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( add_query_arg( 'type', 'payment', $base ) ),
				$current === 'payment' ? ' class="current"' : '',
				_n( 'Payment', 'Payments', $this->counts['payment'], 'wpforms' ) . $payment
			);
		}

		$views['starred'] = sprintf(
			'<a href="%s"%s>%s</a>',
			esc_url( add_query_arg( 'type', 'starred', $base ) ),
			$current === 'starred' ? ' class="current"' : '',
			esc_html__( 'Starred', 'wpforms' ) . $starred
		);

		/**
		 * Filters the array of entries counts in different views.
		 *
		 * @since 1.3.3
		 *
		 * @param array $views     An array of views.
		 * @param array $form_data Form data.
		 * @param int[] $counts    An array of entries' counts.
		 *
		 * @return array
		 */
		return apply_filters( 'wpforms_entries_table_views', $views, $this->form_data, $this->counts ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Add Trashed views to the list of views.
	 * We've separated it to use the filter because we need it to be after spam, which uses the filter.
	 *
	 * @since 1.8.5
	 *
	 * @param array $views Entries table views.
	 *
	 * @return array $views Array of all the list table views.
	 */
	public function add_trashed_views( $views ) {

		if (
			! $this->counts['trash'] &&
			( ! isset( $_GET['status'] ) || $_GET['status'] !== 'trash' ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			! wpforms()->get( 'entry' )->get_trash_count( $this->form_id )
		) {
			return $views;
		}

		$trashed = '&nbsp;<span class="count">(<span class="trashed-num">' . $this->counts['trash'] . '</span>)</span>';
		$base    = remove_query_arg( [ 'type', 'status', 'paged', 'message' ] );

		$views['trashed'] = sprintf(
			'<a href="%1s"%2s>%3s</a>',
			esc_url( add_query_arg( 'status', Page::TRASH_ENTRY_STATUS, $base ) ),
			$this->is_trash_list() ? ' class="current"' : '',
			esc_html__( 'Trash', 'wpforms' ) . $trashed
		);

		return $views;
	}

	/**
	 * Retrieve the table columns.
	 *
	 * @since 1.8.6
	 *
	 * @return array Array of all the list table columns.
	 */
	public function get_columns(): array {
		// phpcs:ignore WPForms.Formatting.EmptyLineBeforeReturn.RemoveEmptyLineBeforeReturnStatement
		return Columns::get_list_table_columns( $this );
	}

	/**
	 * Retrieve the table's sortable columns.
	 *
	 * @since 1.8.6
	 *
	 * @return array Array of all the sortable columns
	 */
	public function get_sortable_columns() {

		$sortable = [
			'entry_id'    => [ 'id', false ],
			'notes_count' => [ 'notes_count', false ],
			'id'          => [ 'title', false ],
			'date'        => [ 'date', false ],
		];

		/**
		 * Filters the Entries list table sortable columns.
		 *
		 * @since 1.3.3
		 *
		 * @param array $sortable  Sortable columns.
		 * @param array $form_data Form data.
		 *
		 * @return bool
		 */
		return apply_filters( 'wpforms_entries_table_sortable', $sortable, $this->form_data ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Get the list of fields, that are disallowed to be displayed as column in a table.
	 *
	 * @since 1.8.6
	 *
	 * @return array
	 */
	public static function get_columns_form_disallowed_fields() {

		/**
		 * Filter the list of the disallowed fields in the entries table.
		 *
		 * @since 1.4.4
		 *
		 * @param array $fields Field types list.
		 */
		return (array) apply_filters( 'wpforms_entries_table_fields_disallow', [ 'captcha', 'divider', 'entry-preview', 'html', 'pagebreak', 'layout' ] ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Logic to determine which fields are displayed in the table columns.
	 *
	 * @since 1.8.6
	 * @deprecated 1.8.6
	 *
	 * @param array $columns List of columns.
	 * @param int   $display Number of columns to display.
	 *
	 * @return array
	 */
	public function get_columns_form_fields( array $columns = [], int $display = 3 ): array {

		// We don't need current method anymore.
		// All the logic is refactored and moved to the \WPForms\Pro\Admin\Entries\Table\Facades\Columns::get_list_table_columns() method.
		_deprecated_function( __METHOD__, '1.8.6 of the WPForms plugin', Columns::class . '::get_list_table_columns()' );

		return array_filter(
			$columns,
			static function ( $slug ) {

				return strpos( $slug, 'wpforms_field_' ) === 0;
			}
		);
	}

	/**
	 * Render the checkbox column.
	 *
	 * @since 1.8.6
	 *
	 * @param object $entry Entry data from DB.
	 *
	 * @return string
	 */
	public function column_cb( $entry ) {

		return '<input type="checkbox" name="entry_id[]" value="' . absint( $entry->entry_id ) . '" />';
	}

	/**
	 * Show `status` value.
	 *
	 * @since 1.8.6
	 * @deprecated 1.8.2.1
	 *
	 * @param object $entry       Current entry data.
	 * @param string $column_name Current column name.
	 *
	 * @return string
	 */
	public function column_status_field( $entry, $column_name ) {

		_deprecated_function( __METHOD__, '1.8.2.1 of the WPForms plugin' );

		// If the entry is a payment, show the payment status.
		if ( $entry->type === 'payment' ) {
			list( $status_label ) = $this->get_payment_status_by_entry_id( (int) $entry->entry_id );

			return $status_label;
		}

		// If the entry has a status, show it.
		if ( ! empty( $entry->status ) ) {
			return ucwords( sanitize_text_field( $entry->status ) );
		}

		// Otherwise, show "N/A" as a placeholder.
		return esc_html__( 'N/A', 'wpforms' );
	}

	/**
	 * Show `payment_total` value.
	 *
	 * @since 1.8.6
	 * @deprecated 1.8.2
	 *
	 * @param object $entry       Current entry data.
	 * @param string $column_name Current column name.
	 *
	 * @return string
	 */
	public function column_payment_total_field( $entry, $column_name ) {

		_deprecated_function( __METHOD__, '1.8.2 of the WPForms plugin' );

		$entry_meta = json_decode( $entry->meta, true );

		if ( $entry->type === 'payment' && isset( $entry_meta['payment_total'] ) ) {
			$amount = wpforms_sanitize_amount( $entry_meta['payment_total'], $entry_meta['payment_currency'] );
			$total  = wpforms_format_amount( $amount, true, $entry_meta['payment_currency'] );
			$value  = $total;

			if ( ! empty( $entry_meta['payment_subscription'] ) ) {
				$value .= ' <i class="fa fa-refresh" aria-hidden="true" style="color:#ccc;margin-left:4px;" title="' . esc_html__( 'Recurring', 'wpforms' ) . '"></i>';
			}
		} else {
			$value = '-';
		}

		return $value;
	}

	/**
	 * Display "Type" column.
	 *
	 * @since 1.8.6
	 *
	 * @param object $entry       Current entry data.
	 * @param string $column_name Current column name.
	 *
	 * @return string
	 */
	public function column_type_field( $entry, $column_name ) {

		// Show the original type if is trash.
		if ( isset( $_GET['status'] ) && $_GET['status'] === Page::TRASH_ENTRY_STATUS ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$meta = wpforms()->get( 'entry_meta' )->get_meta(
				[
					'entry_id' => $entry->entry_id,
					'type'     => 'status_prev',
				]
			);

			if ( isset( $meta[0] ) && ! empty( $meta[0]->status ) ) {
				return ucwords( sanitize_text_field( $meta[0]->status ) );
			}
		}

		// If the entry has a status, show it.
		if ( ! empty( $entry->status ) && $entry->type !== 'payment' && $entry->status !== Page::TRASH_ENTRY_STATUS ) {
			return ucwords( sanitize_text_field( $entry->status ) );
		}

		// Otherwise, show "Completed" as a placeholder.
		return esc_html__( 'Completed', 'wpforms' );
	}

	/**
	 * Display payment status and total amount.
	 *
	 * @since 1.8.6
	 *
	 * @param object $entry Current entry data.
	 *
	 * @return string
	 */
	private function column_payment_field( $entry ) {

		list( $status_label, $status_slug, $payment ) = $this->get_payment_status_by_entry_id( (int) $entry->entry_id );

		// If payment data is not found, return customized N/A.
		if ( ! $payment ) {
			return sprintf(
				'<span class="payment-status-%s">%s</span>',
				$status_slug,
				$status_label
			);
		}

		if ( ! $payment->is_published ) {
			return sprintf(
				'<span class="payment-status-%s" title="%s">%s</a>',
				sanitize_html_class( $status_slug ),
				esc_html__( 'The payment in the Trash.', 'wpforms' ),
				wpforms_format_amount( $payment->total_amount, true, $payment->currency )
			);
		}

		$payment_url = '';

		if ( wpforms_current_user_can() ) {
			// Generate the single payment URL.
			$payment_url = add_query_arg(
				[
					'page'       => 'wpforms-payments',
					'view'       => 'payment',
					'payment_id' => absint( $payment->id ),
				],
				admin_url( 'admin.php' )
			);
		}

		return sprintf(
			'<a href="%s" class="payment-status-%s" title="%s">%s</a>',
			esc_url( $payment_url ),
			sanitize_html_class( $status_slug ),
			esc_html( $status_label ),
			wpforms_format_amount( $payment->total_amount, true, $payment->currency )
		);
	}

	/**
	 * Show specific form fields.
	 *
	 * @since 1.8.6
	 *
	 * @param object $entry       Entry data from DB.
	 * @param string $column_name Column unique name.
	 *
	 * @return string
	 */
	public function column_form_field( $entry, $column_name ) {

		if ( strpos( $column_name, 'wpforms_field_' ) === false ) {
			/**
			 * Filters the entry table column value in case it is not a form field.
			 *
			 * @since 1.8.6
			 *
			 * @param string $value       Value.
			 * @param object $entry       Current entry data.
			 * @param string $column_name Current column name.
			 *
			 * @return string
			 */
			return apply_filters( 'wpforms_pro_admin_entries_list_table_column_form_field_meta_field_value', '', $entry, $column_name );
		}

		$field_id     = (int) str_replace( 'wpforms_field_', '', $column_name );
		$entry_fields = (array) wpforms_decode( $entry->fields );

		if (
			isset( $entry_fields[ $field_id ]['value'] ) &&
			! wpforms_is_empty_string( $entry_fields[ $field_id ]['value'] )
		) {

			$field_type = $entry_fields[ $field_id ]['type'] ?? '';
			$value      = wp_strip_all_tags( trim( $entry_fields[ $field_id ]['value'] ) );
			$value      = $this->truncate_long_value( $value, $field_type );
			$value      = nl2br( $value );

			/** This filter is documented in src/SmartTags/SmartTag/FieldHtmlId.php.*/
			return apply_filters( 'wpforms_html_field_value', $value, $entry_fields[ $field_id ], $this->form_data, 'entry-table' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		}

		return '-';
	}

	/**
	 * Render the columns.
	 *
	 * @since 1.8.6
	 * @since 1.5.7 Added an `Entry Notes` column.
	 *
	 * @param object $entry       Current entry data.
	 * @param string $column_name Current column name.
	 *
	 * @return string
	 */
	public function column_default( $entry, $column_name ): string { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$field_type = $this->get_field_type( $entry, $column_name );

		switch ( strtolower( $column_name ) ) {
			case 'entry_id':
			case 'id':
				$value = absint( $entry->entry_id );
				break;

			case 'notes_count':
				$value = absint( $entry->notes_count );
				break;

			case 'date':
				$value = sprintf(
					'<span class="date">%1$s</span> <span class="time">%3$s %2$s</span>',
					wpforms_datetime_format( $entry->date, $this->datetime_format['date'], true ),
					wpforms_datetime_format( $entry->date, $this->datetime_format['time'], true ),
					esc_html__( /* translators: date and time separator. */ 'at', 'wpforms' )
				);
				break;

			case 'type':
				$value = $this->column_type_field( $entry, $column_name );
				break;

			case 'payment':
				$value = $this->column_payment_field( $entry );
				break;

			case 'user_ip':
				$value = esc_html( $entry->ip_address );
				break;

			case 'user_agent':
				$value = esc_html( $entry->user_agent );
				break;

			case 'user_uuid':
				$value = esc_html( $entry->user_uuid );
				break;

			default:
				$value = $this->column_form_field( $entry, $column_name );
		}

		// Adds a wrapper with a field type in data attribute.
		if ( ! empty( $value ) && ! empty( $field_type ) ) {
			$value = sprintf( '<div data-field-type="%s">%s</div>', esc_attr( $field_type ), $value );
		}

		/**
		 * Allow filtering entry table column value.
		 *
		 * @since 1.0.0
		 * @since 1.7.0 Added Field type.
		 *
		 * @param string $value       Value.
		 * @param object $entry       Current entry data.
		 * @param string $column_name Current column name.
		 * @param string $field_type  Field type.
		 */
		return apply_filters( 'wpforms_entry_table_column_value', $value, $entry, $column_name, $field_type ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Retrieve a field type.
	 *
	 * @since 1.8.6
	 *
	 * @param object $entry       Current entry data.
	 * @param string $column_name Current column name.
	 *
	 * @return string
	 */
	public function get_field_type( $entry, $column_name ) {

		$field_id     = str_replace( 'wpforms_field_', '', $column_name );
		$entry_fields = wpforms_decode( $entry->fields );
		$field_type   = '';

		if (
			! empty( $entry_fields[ $field_id ] ) &&
			isset( $entry_fields[ $field_id ]['type'] ) &&
			! wpforms_is_empty_string( $entry_fields[ $field_id ]['type'] )
		) {
			$field_type = $entry_fields[ $field_id ]['type'];
		}

		return $field_type;
	}

	/**
	 * Render the indicators column.
	 *
	 * @since 1.8.6
	 *
	 * @param object $entry Entry data from DB.
	 *
	 * @return string
	 */
	public function column_indicators( $entry ) {

		// Stars.
		$star_action = ! empty( $entry->starred ) ? 'unstar' : 'star';
		$star_title  = ! empty( $entry->starred ) ? esc_html__( 'Unstar entry', 'wpforms' ) : esc_html__( 'Star entry', 'wpforms' );
		$star_icon   = '<a href="#" class="indicator-star ' . $star_action . '" data-id="' . absint( $entry->entry_id ) . '" data-form-id="' . absint( $entry->form_id ) . '" title="' . esc_attr( $star_title ) . '"><span class="dashicons dashicons-star-filled"></span></a>';

		// Viewed.
		$read_action = ! empty( $entry->viewed ) ? 'unread' : 'read';
		$read_title  = ! empty( $entry->viewed ) ? esc_html__( 'Mark entry unread', 'wpforms' ) : esc_html__( 'Mark entry read', 'wpforms' );
		$read_icon   = '<a href="#" class="indicator-read ' . $read_action . '" data-id="' . absint( $entry->entry_id ) . '" data-form-id="' . absint( $entry->form_id ) . '" title="' . esc_attr( $read_title ) . '"></a>';

		return $star_icon . $read_icon;
	}

	/**
	 * Render the actions column.
	 *
	 * @since 1.8.6
	 *
	 * @param object $entry Entry data from DB.
	 *
	 * @return string
	 */
	public function column_actions( $entry ) {

		$actions = [];

		// Show the delete action only on trash and spam page.
		if ( $this->should_delete( $entry->entry_id ) ) {
			if ( wpforms_current_user_can( 'delete_entries_form_single', $this->form_id ) ) {
				// Restore.
				$actions['restore'] = sprintf(
					'<a href="%s" title="%s" class="restore">%s</a>',
					esc_url(
						wp_nonce_url(
							add_query_arg(
								[
									'view'     => 'list',
									'action'   => 'restore',
									'form_id'  => $this->form_id,
									'entry_id' => $entry->entry_id,
								]
							),
							'bulk-entries'
						)
					),
					esc_attr__( 'Restore Form Entry', 'wpforms' ),
					esc_html__( 'Restore', 'wpforms' )
				);
				// Delete.
				$actions['delete'] = sprintf(
					'<a href="%s" title="%s" class="delete">%s</a>',
					esc_url(
						wp_nonce_url(
							add_query_arg(
								[
									'view'     => 'list',
									'action'   => 'delete',
									'form_id'  => $this->form_id,
									'entry_id' => $entry->entry_id,
								]
							),
							'bulk-entries'
						)
					),
					esc_attr__( 'Delete Form Entry', 'wpforms' ),
					esc_html__( 'Delete', 'wpforms' )
				);

			}
		} else {
			// View.
			$actions['view'] = sprintf(
				'<a href="%s" title="%s" class="view">%s</a>',
				esc_url(
					add_query_arg(
						[
							'view'     => 'details',
							'entry_id' => $entry->entry_id,
						],
						admin_url( 'admin.php?page=wpforms-entries' )
					)
				),
				esc_attr__( 'View Form Entry', 'wpforms' ),
				esc_html__( 'View', 'wpforms' )
			);

			if (
				wpforms_current_user_can( 'edit_entries_form_single', $this->form_id ) &&
				wpforms()->get( 'entry' )->has_editable_fields( $entry )
			) {
				// Edit.
				$actions['edit'] = sprintf(
					'<a href="%s" title="%s" class="edit">%s</a>',
					esc_url(
						add_query_arg(
							[
								'view'     => 'edit',
								'entry_id' => $entry->entry_id,
							],
							admin_url( 'admin.php?page=wpforms-entries' )
						)
					),
					esc_attr__( 'Edit Form Entry', 'wpforms' ),
					esc_html__( 'Edit', 'wpforms' )
				);
			}

			if ( wpforms_current_user_can( 'delete_entries_form_single', $this->form_id ) ) {
				// Trash can share the same capabilites as delete.
				$actions['trash'] = sprintf(
					'<a href="%s" title="%s" class="trash">%s</a>',
					esc_url(
						wp_nonce_url(
							add_query_arg(
								[
									'view'     => 'list',
									'action'   => 'trash',
									'form_id'  => $this->form_id,
									'entry_id' => $entry->entry_id,
								]
							),
							'bulk-entries'
						)
					),
					esc_attr__( 'Trash Form Entry', 'wpforms' ),
					esc_html__( 'Trash', 'wpforms' )
				);
			}
		}

		/**
		 * Filters the list of actions available for each entry in the table.
		 *
		 * @since 1.0.0
		 *
		 * @param array $actions An array of actions.
		 * @param array $entry   Entry data.
		 */
		$actions = apply_filters( 'wpforms_entry_table_actions', $actions, $entry ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return implode( ' <span class="sep">|</span> ', $actions );
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination.
	 *
	 * @since 1.8.6
	 *
	 * @param string $which Either top or bottom of the page.
	 */
	protected function extra_tablenav( $which ) {

		if ( $which === 'top' ) {
			$this->display_date_range_filter();
		}

		$this->display_entry_trash_button();

		/**
		 * Fires after the filter controls, before the table.
		 *
		 * @since 1.8.3
		 */
		do_action( 'wpforms_entries_table_extra_tablenav' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Display Empty Trash Button.
	 *
	 * @since 1.8.6
	 */
	private function display_entry_trash_button() {

		if ( ! $this->is_trash_list() ) {
			return;
		}

		$base = add_query_arg(
			[
				'page'    => 'wpforms-entries',
				'view'    => 'list',
				'form_id' => absint( $this->form_id ),
			],
			admin_url( 'admin.php' )
		);

		?>

		<a href="<?php echo esc_url( wp_nonce_url( $base, 'bulk-entries' ) ); ?>" class="button delete-all form-details-actions-removeall" data-page="trash">
			<?php esc_html_e( 'Empty Trash', 'wpforms' ); ?>
		</a>

		<?php
	}

	/**
	 * Display date range filter.
	 *
	 * @since 1.8.6
	 */
	private function display_date_range_filter() {

		/**
		 * Filter to disable date range filter.
		 *
		 * @since 1.8.3
		 *
		 * @param bool $disable_date_range_filter Whether to disable date range filter.
		 */
		if ( apply_filters( 'wpforms_entries_table_display_date_range_filter_disable', false ) ) { // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			return;
		}

		?>

		<div class="alignleft actions wpforms-filter-date">

			<input type="text" name="date" class="regular-text wpforms-filter-date-selector"
				placeholder="<?php esc_attr_e( 'Select a date range', 'wpforms' ); ?>"
				style="cursor: pointer">

			<button type="submit" name="action" value="filter_date" class="button">
				<?php esc_html_e( 'Filter', 'wpforms' ); ?>
			</button>

		</div>

		<?php
	}

	/**
	 * Define bulk actions available for our table listing.
	 *
	 * @since 1.8.6
	 *
	 * @return array
	 */
	public function get_bulk_actions() {

		$bulk_actions = [
			'read'   => esc_html__( 'Mark Read', 'wpforms' ),
			'unread' => esc_html__( 'Mark Unread', 'wpforms' ),
			'star'   => esc_html__( 'Star', 'wpforms' ),
			'unstar' => esc_html__( 'Unstar', 'wpforms' ),
			'print'  => esc_html__( 'Print', 'wpforms' ),
		];

		$bulk_actions = $this->get_addtional_bulk_actions( $bulk_actions );

		/**
		 * Filters bulk actions.
		 *
		 * @since 1.8.3
		 *
		 * @param array $bulk_actions Bulk actions.
		 *
		 * @return array
		 */
		return apply_filters( 'wpforms_entries_table_get_bulk_actions', $bulk_actions ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Define additional bulk actions available for our table listing.
	 * Additional settings are all related to delete/restore action.
	 *
	 * @since 1.8.5
	 *
	 * @param array $bulk_actions Bulk actions.
	 *
	 * @return array
	 */
	private function get_addtional_bulk_actions( $bulk_actions ) {

		if ( ! wpforms_current_user_can( 'delete_entries_form_single', $this->form_id ) ) {
			return $bulk_actions;
		}

		$bulk_actions['null'] = esc_html__( '----------', 'wpforms' );

		if ( ! $this->is_trash_list() ) {

			// Add Trash before delete.
			$bulk_actions['trash'] = esc_html__( 'Move to Trash', 'wpforms' );
		} else {

			// Add Restore before delete.
			$bulk_actions['restore'] = esc_html__( 'Restore', 'wpforms' );

		}

		$bulk_actions['delete'] = esc_html__( 'Delete', 'wpforms' );

		return $bulk_actions;
	}

	/**
	 * Process the bulk actions.
	 *
	 * @since 1.8.6
	 */
	public function process_bulk_actions() {

		$this->display_bulk_action_message();

		if ( empty( $_REQUEST['_wpnonce'] ) ) {
			return;
		}

		if (
			! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'bulk-entries' ) &&
			! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'bulk-entries-nonce' )
		) {
			return;
		}

		$this->process_bulk_action_single();
	}

	/**
	 * Get current action.
	 *
	 * @since 1.8.6
	 *
	 * @return string
	 */
	public function current_action() {

		if ( isset( $_REQUEST['empty_spam'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'empty_spam';
		}

		return parent::current_action();
	}

	/**
	 * Process single bulk action.
	 *
	 * @since 1.8.6
	 */
	protected function process_bulk_action_single() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$doaction = $this->current_action();
		$status   = '';

		if ( empty( $doaction ) || $doaction === 'filter_date' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
		$ids = isset( $_GET['entry_id'] ) ? wp_unslash( $_GET['entry_id'] ) : false;

		if ( ! is_array( $ids ) ) {
			$ids = [ $ids ];
		}

		$ids = array_map( 'absint', $ids );

		if ( empty( $ids ) ) {
			return;
		}

		// check if it is trash list.
		if ( $this->is_trash_list() ) {
			$status = Page::TRASH_ENTRY_STATUS;
		}

		$args = [
			'entry_id'    => $ids,
			'is_filtered' => true,
			'number'      => $this->get_items_per_page( 'wpforms_entries_per_page', $this->per_page ),
			'status'      => $status,
		];

		// Get entries, that would be affected.
		$entries_list = wpforms()->get( 'entry' )->get_entries( $args );

		/**
		 * Filter entries list.
		 *
		 * @since 1.8.3
		 *
		 * @param array $entries_list List of entries.
		 * @param array $args         Arguments.
		 */
		$entries_list = apply_filters( 'wpforms_entries_table_process_actions_entries_list', $entries_list, $args ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$sendback = remove_query_arg( [ 'read', 'unread', 'starred', 'unstarred', 'print', 'deleted', 'empty_spam', 'trashed', 'restored', 'paged' ], wp_get_referer() );

		switch ( $doaction ) {
			// Mark as read.
			case 'read':
				$sendback = $this->process_bulk_action_single_read( $entries_list, $ids, $sendback );
				break;

			// Mark as unread.
			case 'unread':
				$sendback = $this->process_bulk_action_single_unread( $entries_list, $ids, $sendback );
				break;

			// Star entry.
			case 'star':
				$sendback = $this->process_bulk_action_single_star( $entries_list, $ids, $sendback );
				break;

			// Unstar entry.
			case 'unstar':
				$sendback = $this->process_bulk_action_single_unstar( $entries_list, $ids, $sendback );
				break;

			// Print entries.
			case 'print':
				$this->process_bulk_action_single_print( $ids );
				break;

			// Trash entries.
			case 'trash':
				$sendback = $this->process_bulk_action_single_trash( $ids, $sendback );
				break;

			// Delete entries.
			case 'delete':
				$sendback = $this->process_bulk_action_single_delete( $ids, $sendback );
				break;

			// Restore entries.
			case 'restore':
				$sendback = $this->process_bulk_action_single_restore( $ids, $sendback );
				break;

			// Empty spam.
			case 'empty_spam':
				$sendback = $this->process_bulk_action_empty_spam( $sendback );
				break;
		}

		$sendback = remove_query_arg( [ 'action', 'action2', 'entry_id' ], $sendback );

		wp_safe_redirect( $sendback );
		exit();
	}

	/**
	 * Process the bulk action read.
	 *
	 * @since 1.5.7
	 *
	 * @param array  $entries_list Filtered entries list.
	 * @param array  $ids          IDs to process.
	 * @param string $sendback     URL query string.
	 *
	 * @return string
	 */
	protected function process_bulk_action_single_read( $entries_list, $ids, $sendback ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = ! empty( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : false;

		if ( empty( $form_id ) ) {
			return $sendback;
		}

		$user_id = get_current_user_id();
		$entries = wp_list_pluck( $entries_list, 'viewed', 'entry_id' );
		$read    = 0;

		foreach ( $ids as $id ) {

			if ( ! array_key_exists( $id, $entries ) ) {
				continue;
			}

			if ( $entries[ $id ] === '1' ) {
				continue;
			}

			$success = wpforms()->get( 'entry' )->update(
				$id,
				[
					'viewed' => '1',
				]
			);

			if ( $success ) {

				wpforms()->get( 'entry_meta' )->add(
					[
						'entry_id' => $id,
						'form_id'  => $form_id,
						'user_id'  => $user_id,
						'type'     => 'log',
						'data'     => wpautop( sprintf( '<em>%s</em>', esc_html__( 'Entry read.', 'wpforms' ) ) ),
					],
					'entry_meta'
				);

				++$read;
			}
		}

		return add_query_arg( 'read', $read, $sendback );
	}

	/**
	 * Process the bulk action unread.
	 *
	 * @since 1.8.6
	 *
	 * @param array  $entries_list Filtered entries list.
	 * @param array  $ids          IDs to process.
	 * @param string $sendback     URL query string.
	 *
	 * @return string
	 */
	protected function process_bulk_action_single_unread( $entries_list, $ids, $sendback ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = ! empty( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : false;

		if ( empty( $form_id ) ) {
			return $sendback;
		}

		$user_id = get_current_user_id();
		$entries = wp_list_pluck( $entries_list, 'viewed', 'entry_id' );
		$unread  = 0;

		foreach ( $ids as $id ) {

			if ( ! array_key_exists( $id, $entries ) ) {
				continue;
			}

			if ( $entries[ $id ] === '0' ) {
				continue;
			}

			$success = wpforms()->get( 'entry' )->update(
				$id,
				[
					'viewed' => '0',
				]
			);

			if ( $success ) {
				wpforms()->get( 'entry_meta' )->add(
					[
						'entry_id' => $id,
						'form_id'  => $form_id,
						'user_id'  => $user_id,
						'type'     => 'log',
						'data'     => wpautop( sprintf( '<em>%s</em>', esc_html__( 'Entry unread.', 'wpforms' ) ) ),
					],
					'entry_meta'
				);

				++$unread;
			}
		}

		return add_query_arg( 'unread', $unread, $sendback );
	}

	/**
	 * Process the bulk action star.
	 *
	 * @since 1.8.6
	 *
	 * @param array  $entries_list Filtered entries list.
	 * @param array  $ids          IDs to process.
	 * @param string $sendback     URL query string.
	 *
	 * @return string
	 */
	protected function process_bulk_action_single_star( $entries_list, $ids, $sendback ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity

		$form_id = ! empty( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $form_id ) ) {
			return $sendback;
		}

		$user_id = get_current_user_id();
		$entries = wp_list_pluck( $entries_list, 'starred', 'entry_id' );
		$starred = 0;

		foreach ( $ids as $id ) {

			if ( ! array_key_exists( $id, $entries ) ) {
				continue;
			}

			if ( $entries[ $id ] === '1' ) {
				continue;
			}

			$success = wpforms()->get( 'entry' )->update(
				$id,
				[
					'starred' => '1',
				]
			);

			if ( $success ) {
				wpforms()->get( 'entry_meta' )->add(
					[
						'entry_id' => $id,
						'form_id'  => $form_id,
						'user_id'  => $user_id,
						'type'     => 'log',
						'data'     => wpautop( sprintf( '<em>%s</em>', esc_html__( 'Entry starred.', 'wpforms' ) ) ),
					],
					'entry_meta'
				);

				++$starred;
			}
		}

		return add_query_arg( 'starred', $starred, $sendback );
	}

	/**
	 * Process the bulk action unstar.
	 *
	 * @since 1.8.6
	 *
	 * @param array  $entries_list Filtered entries list.
	 * @param array  $ids          IDs to process.
	 * @param string $sendback     URL query string.
	 *
	 * @return string
	 */
	protected function process_bulk_action_single_unstar( $entries_list, $ids, $sendback ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = ! empty( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : false;

		if ( empty( $form_id ) ) {
			return $sendback;
		}

		$user_id   = get_current_user_id();
		$entries   = wp_list_pluck( $entries_list, 'starred', 'entry_id' );
		$unstarred = 0;

		foreach ( $ids as $id ) {

			if ( ! array_key_exists( $id, $entries ) ) {
				continue;
			}

			if ( $entries[ $id ] === '0' ) {
				continue;
			}

			$success = wpforms()->get( 'entry' )->update(
				$id,
				[
					'starred' => '0',
				]
			);

			if ( $success ) {
				wpforms()->get( 'entry_meta' )->add(
					[
						'entry_id' => $id,
						'form_id'  => $form_id,
						'user_id'  => $user_id,
						'type'     => 'log',
						'data'     => wpautop( sprintf( '<em>%s</em>', esc_html__( 'Entry unstarred.', 'wpforms' ) ) ),
					],
					'entry_meta'
				);

				++$unstarred;
			}
		}

		return add_query_arg( 'unstarred', $unstarred, $sendback );
	}

	/**
	 * Process the bulk action print.
	 *
	 * @since 1.8.6
	 *
	 * @param array $ids IDs to process.
	 *
	 * @return void
	 */
	private function process_bulk_action_single_print( $ids ) {

		$print_url = add_query_arg(
			[
				'page'     => 'wpforms-entries',
				'view'     => 'print',
				'entry_id' => implode( ',', $ids ),
			],
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $print_url );
		exit();
	}

	/**
	 * Process the bulk action delete.
	 *
	 * @since 1.8.5
	 *
	 * @param array  $ids      IDs to process.
	 * @param string $sendback URL query string.
	 *
	 * @return string
	 */
	private function process_bulk_action_single_trash( $ids, $sendback ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$trashed = 0;
		$form_id = ! empty( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user_id = get_current_user_id();

		foreach ( $ids as $id ) {
			// Get the entry first.
			$entry = wpforms()->get( 'entry' )->get( $id );

			if ( ! $entry ) {
				continue;
			}

			$status = $entry->status;

			/**
			 * TODO :: After the support for PHP 7 ends,
			 * we can update the following code to use named arguments and skip the optional params.
			 */
			$success = wpforms()->get( 'entry' )->update(
				$id,
				[ 'status' => Page::TRASH_ENTRY_STATUS ],
				'',
				'',
				[ 'cap' => 'delete_entry_single' ] // Force the cap to trash the entry, since we cant provide edit cap here.
			);

			// If it didn't work continue.
			if ( ! $success ) {
				continue;
			}

			if ( $status !== '' ) {
				wpforms()->get( 'entry_meta' )->add(
					[
						'entry_id' => $id,
						'form_id'  => $form_id,
						'user_id'  => $user_id,
						'type'     => 'status_prev',
						'data'     => '',
						'status'   => $status,
					],
					'entry_meta'
				);
			}

			++$trashed;
		}

		// If trashed entries are more than 1, then clear widget cache.
		if ( $trashed >= 1 ) {
			DashboardWidget::clear_widget_cache();
		}

		return add_query_arg(
			[
				'trashed' => $trashed,
				'view'    => 'list', // force the list view to make sure we go to the right page.
				'form_id' => $form_id,
			],
			$sendback
		);
	}

	/**
	 * Process the bulk action restore.
	 *
	 * @since 1.8.5
	 *
	 * @param array  $ids      IDs to process.
	 * @param string $sendback URL query string.
	 *
	 * @return string
	 */
	private function process_bulk_action_single_restore( $ids, $sendback ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$restored = 0;

		foreach ( $ids as $id ) {
			// Reset or set initial status.
			$status = '';

			// Get the entry first.
			$entry = wpforms()->get( 'entry' )->get( $id );

			if ( ! $entry ) {
				continue;
			}

			$meta = wpforms()->get( 'entry_meta' )->get_meta(
				[
					'entry_id' => $id,
					'type'     => 'status_prev',
				]
			);

			// check meta for status log to restore the status.
			if ( $meta ) {
				$status = $meta[0]->status;

				// After taking status from meta, delete the meta.
				wpforms()->get( 'entry_meta' )->delete_by( 'id', $meta[0]->id );
			}

			/**
			 * TODO :: After the support for PHP 7 ends,
			 * we can update the following code to use named arguments and skip the optional params.
			 */
			$success = wpforms()->get( 'entry' )->update(
				$id,
				[ 'status' => $status ],
				'',
				'',
				[ 'cap' => 'delete_entry_single' ] // Force the cap to trash the entry, since we cant provide edit cap here.
			);

			// If it didn't work continue.
			if ( ! $success ) {
				continue;
			}

			++$restored;
		}

		$trash_count = wpforms()->get( 'entry' )->get_entries(
			[
				'form_id' => $this->form_id,
				'status'  => Page::TRASH_ENTRY_STATUS,
			],
			true
		);

		// If trash is emptied.
		if ( $trash_count < 1 ) {
			$sendback = remove_query_arg( 'status', $sendback );
		}

		// If restored entries are more than 1, then clear widget cache.
		if ( $restored >= 1 ) {
			DashboardWidget::clear_widget_cache();
		}

		return add_query_arg( 'restored', $restored, $sendback );
	}

	/**
	 * Process the bulk action delete.
	 *
	 * @since 1.8.6
	 *
	 * @param array  $ids      IDs to process.
	 * @param string $sendback URL query string.
	 *
	 * @return string
	 */
	protected function process_bulk_action_single_delete( $ids, $sendback ) {

		$deleted = 0;
		$form_id = ! empty( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		foreach ( $ids as $id ) {
			if ( wpforms()->get( 'entry' )->delete( $id ) ) {
				++$deleted;
			}
		}

		return add_query_arg(
			[
				'deleted' => $deleted,
				'view'    => 'list', // force the list view to make sure we go to the right page.
				'form_id' => $form_id,
			],
			$sendback
		);
	}

	/**
	 * Process the bulk action empty spam.
	 *
	 * @since 1.8.6
	 *
	 * @param string $sendback URL query string.
	 *
	 * @return string
	 */
	protected function process_bulk_action_empty_spam( $sendback ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = ! empty( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : false;

		if ( empty( $form_id ) ) {
			return $sendback;
		}

		$entries = wpforms()->get( 'entry' )->get_entries(
			[
				'form_id' => $form_id,
				'status'  => 'spam',
			]
		);

		if ( ! $entries ) {
			return $sendback;
		}

		foreach ( $entries as $entry ) {
			wpforms()->get( 'entry' )->delete( $entry->entry_id );
		}

		return add_query_arg( 'deleted', count( $entries ), $sendback );
	}

	/**
	 * Display bulk action result message.
	 *
	 * @since 1.8.6
	 */
	protected function display_bulk_action_message() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$bulk_counts = [
			'read'      => isset( $_REQUEST['read'] ) ? absint( $_REQUEST['read'] ) : 0,
			'unread'    => isset( $_REQUEST['unread'] ) ? absint( $_REQUEST['unread'] ) : 0,
			'starred'   => isset( $_REQUEST['starred'] ) ? absint( $_REQUEST['starred'] ) : 0,
			'unstarred' => isset( $_REQUEST['unstarred'] ) ? absint( $_REQUEST['unstarred'] ) : 0,
			'deleted'   => isset( $_REQUEST['deleted'] ) ? (int) $_REQUEST['deleted'] : 0,
			'trashed'   => isset( $_REQUEST['trashed'] ) ? (int) $_REQUEST['trashed'] : 0,
			'restored'  => isset( $_REQUEST['restored'] ) ? (int) $_REQUEST['restored'] : 0,
		];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$bulk_messages = [
			/* translators: %d - number of processed entries. */
			'read'      => _n( '%d entry was successfully marked as read.', '%d entries were successfully marked as read.', $bulk_counts['read'], 'wpforms' ),
			/* translators: %d - number of processed entries. */
			'unread'    => _n( '%d entry was successfully marked as unread.', '%d entries were successfully marked as unread.', $bulk_counts['unread'], 'wpforms' ),
			/* translators: %d - number of processed entries. */
			'starred'   => _n( '%d entry was successfully starred.', '%d entries were successfully starred.', $bulk_counts['starred'], 'wpforms' ),
			/* translators: %d - number of processed entries. */
			'unstarred' => _n( '%d entry was successfully unstarred.', '%d entries were successfully unstarred.', $bulk_counts['unstarred'], 'wpforms' ),
			/* translators: %d - number of processed entries. */
			'deleted'   => _n( '%d entry was successfully deleted.', '%d entries were successfully deleted.', $bulk_counts['deleted'], 'wpforms' ),
			/* translators: %d - number of processed entries. */
			'trashed'   => _n( '%d entry was successfully trashed.', '%d entries were successfully trashed.', $bulk_counts['trashed'], 'wpforms' ),
			/* translators: %d - number of processed entries. */
			'restored'  => _n( '%d entry was successfully restored.', '%d entries were successfully restored.', $bulk_counts['restored'], 'wpforms' ),
		];

		if ( $bulk_counts['deleted'] === -1 ) {
			$bulk_messages['deleted'] = esc_html__( 'All entries for the currently selected form were successfully deleted.', 'wpforms' );
		}

		if ( $bulk_counts['trashed'] === -1 ) {
			$bulk_messages['trashed'] = esc_html__( 'All entries for the currently selected form were successfully trashed.', 'wpforms' );
		}

		// Leave only non-zero counts, so only those that were processed are left.
		$bulk_counts = array_filter( $bulk_counts );

		// If we have bulk messages to display.
		$messages = [];

		foreach ( $bulk_counts as $type => $count ) {
			if ( isset( $bulk_messages[ $type ] ) ) {
				$messages[] = sprintf( $bulk_messages[ $type ], $count );
			}
		}

		if ( $messages ) {
			Notice::success( implode( '<br>', array_map( 'esc_html', $messages ) ) );
		}
	}

	/**
	 * Message to be displayed when there are no entries.
	 *
	 * @since 1.8.6
	 */
	public function no_items() {

		printf(
			'<div class="wpforms-no-entries-found">%1$s</div>',
			esc_html__( 'No entries found.', 'wpforms' )
		);
	}

	/**
	 * Entries list form search.
	 *
	 * @since 1.8.6
	 *
	 * @param string $text     The 'submit' button label.
	 * @param string $input_id ID attribute value for the search input field.
	 */
	public function search_box( $text, $input_id ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity

		$input_id .= '-search-input';

		/**
		 * Fires before output the search box on the Entries list page.
		 *
		 * @since 1.4.4
		 *
		 * @param array $forms_data Form data.
		 */
		do_action( 'wpforms_entries_list_form_filters_before', $this->form_data ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$filter_fields = [];

		if ( ! empty( $this->form_data['fields'] ) ) {
			foreach ( $this->form_data['fields'] as $id => $field ) {
				if ( in_array( $field['type'], self::get_columns_form_disallowed_fields(), true ) ) {
					continue;
				}

				$filter_fields[ $id ] = ! empty( $field['label'] ) ? wp_strip_all_tags( $field['label'] ) : esc_html__( 'Field', 'wpforms' );
			}
		}

		/**
		 * Filters fields displayed in the search box.
		 *
		 * @since 1.5.5.1
		 *
		 * @param array     $fields Search box fields.
		 * @param ListTable $fields Instance of the ListTable class.
		 *
		 * @return array
		 */
		$filter_fields = (array) apply_filters( 'wpforms_entries_list_form_filters_search_fields', $filter_fields, $this ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$cur_field = 'any';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['search']['field'] ) ) {
			if ( is_numeric( $_GET['search']['field'] ) ) {
				$cur_field = (int) $_GET['search']['field'];
			} else {
				$cur_field = sanitize_key( $_GET['search']['field'] );
			}
		}

		$advanced_options = Helpers::get_search_fields_advanced_options();
		$cur_comparison   = ! empty( $_GET['search']['comparison'] ) ? sanitize_key( $_GET['search']['comparison'] ) : 'contains';
		$cur_term         = '';

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( isset( $_GET['search']['term'] ) && ! wpforms_is_empty_string( $_GET['search']['term'] ) ) {
			$cur_term = sanitize_text_field( wp_unslash( $_GET['search']['term'] ) );
			$cur_term = empty( $cur_term ) ? htmlspecialchars( wp_unslash( $_GET['search']['term'] ) ) : $cur_term; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$this->search_box_output( $text, $input_id, $filter_fields, $advanced_options, $cur_field, $cur_comparison, $cur_term );

		/**
		 * Allows developers output some HTML after the filter forms on the entries list page.
		 *
		 * @since 1.4.4
		 *
		 * @param array $form_data Form data.
		 */
		do_action( 'wpforms_entries_list_form_filters_after', $this->form_data ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Entries list form search.
	 *
	 * @since 1.8.6
	 *
	 * @param string $text                    The 'submit' button label.
	 * @param string $input_id                ID attribute value for the search input field.
	 * @param array  $filter_fields           Filter fields options.
	 * @param array  $search_advanced_options Advanced options.
	 * @param mixed  $cur_field               Current (selected) field or advanced option.
	 * @param string $cur_comparison          Current comparison.
	 * @param string $cur_term                Current search term.
	 */
	private function search_box_output( $text, $input_id, $filter_fields, $search_advanced_options, $cur_field, $cur_comparison, $cur_term ) {

		?>
		<p class="search-box wpforms-form-search-box">

			<select name="search[field]" class="wpforms-form-search-box-field">
				<optgroup label="<?php esc_attr_e( 'Form fields', 'wpforms' ); ?>">
					<option value="any" <?php selected( 'any', $cur_field ); ?>><?php esc_html_e( 'Any form field', 'wpforms' ); ?></option>
					<?php
					if ( ! empty( $filter_fields ) ) {
						foreach ( $filter_fields as $id => $name ) {
							printf(
								'<option value="%1$s" %2$s>%3$s</option>',
								esc_attr( $id ),
								selected( $id, $cur_field, false ),
								esc_html( $name )
							);
						}
					}
					?>
				</optgroup>
				<?php if ( ! empty( $search_advanced_options ) ) : ?>
					<optgroup label="<?php esc_attr_e( 'Advanced Options', 'wpforms' ); ?>">
						<?php
						foreach ( $search_advanced_options as $val => $name ) {
							printf(
								'<option value="%1$s" %2$s>%3$s</option>',
								esc_attr( $val ),
								selected( $val, $cur_field, false ),
								esc_html( $name )
							);
						}
						?>
					</optgroup>
				<?php endif; // Advanced options group. ?>
			</select>

			<select name="search[comparison]" class="wpforms-form-search-box-comparison">
				<option value="contains" <?php selected( 'contains', $cur_comparison ); ?>>
					<?php esc_html_e( 'contains', 'wpforms' ); ?>
				</option>
				<option value="contains_not" <?php selected( 'contains_not', $cur_comparison ); ?>>
					<?php esc_html_e( 'does not contain', 'wpforms' ); ?>
				</option>
				<option value="is" <?php selected( 'is', $cur_comparison ); ?>>
					<?php esc_html_e( 'is', 'wpforms' ); ?>
				</option>
				<option value="is_not" <?php selected( 'is_not', $cur_comparison ); ?>>
					<?php esc_html_e( 'is not', 'wpforms' ); ?>
				</option>
			</select>

			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>">
				<?php echo esc_html( $text ); ?>:
			</label>
			<input type="search" name="search[term]" class="wpforms-form-search-box-term" value="<?php echo esc_attr( wp_unslash( $cur_term ) ); ?>" id="<?php echo esc_attr( $input_id ); ?>">

			<button type="submit" class="button"><?php echo esc_html( $text ); ?></button>
		</p>
		<?php
	}

	/**
	 * Fetch and setup the final data for the table.
	 *
	 * @since 1.8.6
	 */
	public function prepare_items() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// Retrieve count.
		$this->get_counts();

		// Setup the columns.
		$columns = $this->get_columns();

		// Hidden columns (none).
		$hidden = [];

		// Define which columns can be sorted.
		$sortable = $this->get_sortable_columns();

		// Get a primary column. It's will be a 3-rd column.
		$primary = key( array_slice( $columns, 2, 1 ) );

		// Set column headers.
		$this->_column_headers = [ $columns, $hidden, $sortable, $primary ];

		// Get entries.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$total_items = $this->counts['total'];
		$page        = $this->get_pagenum();
		$order       = isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'DESC';
		$orderby     = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'entry_id';
		$per_page    = $this->get_items_per_page( 'wpforms_entries_per_page', $this->per_page );
		$data_args   = [
			'form_id' => $this->form_id,
			'number'  => $per_page,
			'offset'  => $per_page * ( $page - 1 ),
			'order'   => $order,
			'orderby' => $orderby,
		];

		if ( ! empty( $_GET['type'] ) && $_GET['type'] === 'starred' ) {
			$data_args['starred'] = '1';
			$total_items          = $this->counts['starred'];
		}
		if ( ! empty( $_GET['type'] ) && $_GET['type'] === 'unread' ) {
			$data_args['viewed'] = '0';
			$total_items         = $this->counts['unread'];
		}

		if ( ! empty( $_GET['type'] ) && $_GET['type'] === 'payment' ) {
			$data_args['type'] = 'payment';
			$total_items       = $this->counts['payment'];
		}

		if ( ! empty( $_GET['status'] ) ) {
			$data_args['status'] = sanitize_text_field( $_GET['status'] ); // phpcs:ignore WordPress.Security
			$total_items         = ! empty( $this->counts[ $data_args['status'] ] ) ? $this->counts[ $data_args['status'] ] : 0;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( array_key_exists( 'notes_count', $columns ) ) {
			$data_args['notes_count'] = true;
		}

		/**
		 * Filters get entries arguments array.
		 *
		 * @since 1.4.0
		 *
		 * @param array $args Arguments.
		 *
		 * @return array
		 */
		$data_args = apply_filters( 'wpforms_entry_table_args', $data_args ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		$data      = wpforms()->get( 'entry' )->get_entries( $data_args );

		// Giddy up.
		$this->items = $data;

		// Finalize pagination.
		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'total_pages' => ceil( $total_items / $per_page ),
				'per_page'    => $per_page,
			]
		);
	}

	/**
	 * Sort by payment total.
	 *
	 * @since 1.8.6
	 * @deprecated 1.7.6
	 *
	 * @param object $a First entry to sort.
	 * @param object $b Second entry to sort.
	 *
	 * @return int
	 * @noinspection PhpUnused
	 */
	public function payment_total_sort( $a, $b ) {

		_deprecated_function( __METHOD__, '1.7.6 of the WPForms plugin' );

		$a_meta  = json_decode( $a->meta, true );
		$a_total = ! empty( $a_meta['payment_total'] ) ? wpforms_sanitize_amount( $a_meta['payment_total'] ) : 0;
		$b_meta  = json_decode( $b->meta, true );
		$b_total = ! empty( $b_meta['payment_total'] ) ? wpforms_sanitize_amount( $b_meta['payment_total'] ) : 0;

		if ( (float) $a_total === (float) $b_total ) {
			return 0;
		}

		return ( $a_total < $b_total ) ? - 1 : 1;
	}

	/**
	 * Extending the `display_rows()` method in order to add hooks.
	 *
	 * @since 1.8.6
	 */
	public function display_rows() {

		/**
		 * Fires before displaying the table rows.
		 *
		 * @since 1.5.6.2
		 *
		 * @param ListTable $list_table_obj ListTable instance.
		 */
		do_action( 'wpforms_admin_entries_before_rows', $this ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		parent::display_rows();

		/**
		 * Fires after displaying the table rows.
		 *
		 * @since 1.5.6.2
		 *
		 * @param ListTable $list_table_obj ListTable instance.
		 */
		do_action( 'wpforms_admin_entries_after_rows', $this ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Truncate long text value to X lines and Y characters.
	 *
	 * @since 1.8.6
	 *
	 * @param string $value      The value to truncate, if needed.
	 * @param string $field_type Field type.
	 *
	 * @return string
	 */
	private function truncate_long_value( $value, $field_type ) {

		// Limit multiline text to 4 lines, 5 for Address field, and overall length to 75 characters.
		$lines_limit = $field_type === 'address' ? 5 : 4;
		$chars_limit = 75;

		// Decode HTML entities to avoid truncating on &euro; and similar.
		$value = html_entity_decode( $value, ENT_COMPAT, 'UTF-8' );

		$lines = preg_split( '/\r\n|\r|\n/', $value );
		$value = array_slice( $lines, 0, $lines_limit );
		$value = implode( PHP_EOL, $value );

		// Encode HTML entities back to prevent XSS.
		$value = htmlentities( $value, ENT_COMPAT, 'UTF-8' );

		if ( strlen( $value ) > $chars_limit ) {
			return mb_substr( $value, 0, $chars_limit ) . '&hellip;';
		}

		// Ellipsis should be on a new line if the value is multiline, and extra lines were truncated.
		if ( count( $lines ) > $lines_limit ) {
			return $value . PHP_EOL . '&hellip;';
		}

		return $value;
	}

	/**
	 * Returns payment status label, slug and payment object by given entry ID.
	 * The returned data includes:
	 * - label: payment status label.
	 * - slug: payment status slug.
	 * - payment: payment object.
	 *
	 * @since 1.8.6
	 *
	 * @param int $entry_id Entry ID.
	 *
	 * @return array
	 */
	private function get_payment_status_by_entry_id( $entry_id ) {

		// Get payment data.
		$payment = wpforms()->get( 'payment' )->get_by( 'entry_id', $entry_id );

		// If payment data is not found, return N/A.
		if ( ! $payment ) {
			return [
				__( 'N/A', 'wpforms' ),
				'n-a',
				null,
			];
		}

		$allowed_statuses = ValueValidator::get_allowed_statuses();
		$payment_status   = ! empty( $payment->subscription_id ) ? $payment->subscription_status : $payment->status;
		$status_slug      = ! empty( $payment_status ) ? $payment_status : 'n-a';
		$status_label     = $allowed_statuses[ $payment_status ] ?? __( 'N/A', 'wpforms' );

		return [ $status_label, $status_slug, $payment ];
	}

	/**
	 * Check the status of entries to check if they are trashable.
	 *
	 * @since 1.8.5
	 *
	 * @param int $entry_id Entry id to check.
	 *
	 * @return boolean
	 */
	private function should_delete( $entry_id ) {

		$entry = wpforms()->get( 'entry' )->get( $entry_id );

		if ( ! $entry ) {
			return false;
		}

		return $entry->status === Page::TRASH_ENTRY_STATUS;
	}

	/**
	 * Check if the current page is a trash list.
	 *
	 * @since 1.8.5
	 *
	 * @return bool
	 */
	public function is_trash_list(): bool {

		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return $status === Page::TRASH_ENTRY_STATUS;
	}

	/**
	 * Displays the table.
	 *
	 * @since 1.8.6
	 */
	public function display() {

		$singular = $this->_args['singular'];

		$this->display_tablenav( 'top' );

		$this->screen->render_screen_reader_content( 'heading_list' );
		?>
		<div class="wpforms-table-container">
			<table class="wp-list-table <?php echo esc_attr( implode( ' ', $this->get_table_classes() ) ); ?>">
				<?php $this->print_table_description(); ?>
				<thead>
				<tr>
					<?php $this->print_column_headers(); ?>
				</tr>
				</thead>

				<tbody id="the-list"
					<?php
					if ( $singular ) {
						echo ' data-wp-lists="list:' . esc_attr( $singular ) . '"';
					}
					?>
				>
				<?php $this->display_rows_or_placeholder(); ?>
				</tbody>

				<tfoot>
				<tr>
					<?php $this->print_column_headers( false ); ?>
				</tr>
				</tfoot>
			</table>
		</div>
		<?php
		$this->display_tablenav( 'bottom' );
	}

	/**
	 * Print column headers but without the `actions` column if this is bottom table header.
	 *
	 * @since 1.8.6
	 *
	 * @param bool $with_id Whether to print the `<tr>` element with an `id` attribute.
	 */
	public function print_column_headers( $with_id = true ) {

		if ( $with_id ) {
			parent::print_column_headers();
		} else {
			$column_headers = $this->_column_headers[0];

			if ( isset( $column_headers['actions'] ) ) {
				$column_headers['actions'] = esc_html__( 'Actions', 'wpforms' );

				$this->_column_headers[0] = $column_headers;
			}

			parent::print_column_headers( false );
		}
	}
}
