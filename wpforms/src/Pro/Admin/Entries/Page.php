<?php

namespace WPForms\Pro\Admin\Entries;

use WPForms\Admin\Notice;
use WPForms\Pro\Admin\Entries\Table\DataObjects\Column;
use WPForms\Pro\Admin\Entries\Table\Facades\Columns;
use WPForms\Pro\Admin\DashboardWidget;
use WPForms\Pro\AntiSpam\SpamEntry;
use WPForms_Field_File_Upload;
use WPForms_Form_Handler;

/**
 * Display list of all entries for a single form.
 *
 * @since 1.8.6
 */
class Page {

	use FilterSearch;

	/**
	 * Entry trash status.
	 *
	 * @since 1.8.5
	 */
	const TRASH_ENTRY_STATUS = 'trash';

	/**
	 * Store admin alerts.
	 *
	 * @since 1.8.6
	 *
	 * @var array
	 */
	public $alerts = [];

	/**
	 * Abort. Bail on proceeding to process the page.
	 *
	 * @since 1.8.6
	 *
	 * @var bool
	 */
	public $abort = false;

	/**
	 * The human-readable error message.
	 *
	 * @since 1.8.6
	 *
	 * @var string
	 */
	private $abort_message;

	/**
	 * Form ID.
	 *
	 * @since 1.8.6
	 *
	 * @var int
	 */
	public $form_id;

	/**
	 * Form object.
	 *
	 * @since 1.8.6
	 *
	 * @var WPForms_Form_Handler
	 */
	public $form;

	/**
	 * Forms object.
	 *
	 * @since 1.8.6
	 *
	 * @var WPForms_Form_Handler[]
	 */
	public $forms;

	/**
	 * Entries object.
	 *
	 * @since 1.8.6
	 *
	 * @var ListTable
	 */
	public $entries;

	/**
	 * Primary class constructor.
	 *
	 * @since 1.8.6
	 */
	public function __construct() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.8.6
	 */
	private function hooks() {

		// Heartbeat doesn't pass $_GET parameters checked by $this->init() condition.
		add_filter( 'heartbeat_received', [ $this, 'heartbeat_new_entries_check' ], 10, 3 );

		// AJAX-callbacks.
		add_action( 'wp_ajax_wpforms_entry_list_process_delete_all', [ $this, 'process_delete' ] );
		add_action( 'wp_ajax_wpforms_entry_list_process_trash_all', [ $this, 'process_trash' ] );
	}

	/**
	 * Determine if the user is viewing the entries list page, if so, party on.
	 *
	 * @since 1.8.6
	 */
	public function init() { // phpcs:disable WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		// Only load if we are actually on the overview page.
		if ( ! wpforms_is_admin_page( 'entries', 'list' ) ) {
			return;
		}

		$form_id = $this->get_filtered_form_id();

		if ( empty( $form_id ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpforms-entries' ) );
			exit;
		}

		if ( ! wpforms_current_user_can( 'view_entries_form_single', $form_id ) ) {
			wp_die( esc_html__( 'You do not have permission to view this form\'s entries.', 'wpforms' ), 403 );
		}

		$form = wpforms()->get( 'form' )->get( $form_id );

		if ( empty( $form ) || $form->post_status === self::TRASH_ENTRY_STATUS ) {
			$this->abort_message = esc_html__( 'It looks like the form you are trying to access is no longer available.', 'wpforms' );
			$this->abort         = true;
		}

		$this->remove_get_parameters();
		set_screen_options();

		// Load the classes that build the entries' table.
		add_action( 'current_screen', [ $this, 'load_entries_list_table' ], 5 );

		// Processing and setup.
		add_action( 'wpforms_entries_init', [ $this, 'process_filter_dates' ], 7, 1 );
		add_action( 'wpforms_entries_init', [ $this, 'process_filter_search' ], 7, 1 );
		add_action( 'wpforms_entries_init', [ $this, 'process_read' ], 8, 1 );
		add_action( 'wpforms_entries_init', [ $this, 'setup' ], 10, 1 );
		add_action( 'wpforms_entries_init', [ $this, 'register_alerts' ], 20, 1 );

		/**
		 * Fires on init entries list page.
		 *
		 * @since 1.0.0
		 *
		 * @param string $context Current context.
		 */
		do_action( 'wpforms_entries_init', 'list' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		// Output.
		add_action( 'wpforms_admin_page', [ $this, 'list_all' ] );
		add_action( 'wpforms_admin_page', [ $this, 'display_abort_message' ] );
		add_action( 'wpforms_admin_page', [ $this, 'field_column_setting' ] );
		add_action( 'wpforms_entry_list_title', [ $this, 'list_form_actions' ], 10, 1 );

		// Enqueues.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueues' ] );
		add_filter( 'wpforms_admin_strings', [ $this, 'js_strings' ] );

		// Apply filter to search by date on Entries page.
		add_filter( 'wpforms_entry_handler_get_entries_args', [ $this, 'get_filtered_entry_table_args' ] );
	} // phpcs:enable WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

	/**
	 * Remove unnecessary $_GET parameters for shorter URL.
	 *
	 * @since 1.8.6
	 */
	private function remove_get_parameters() {

		$remove_args = [
			'_wp_http_referer',
			'action',
			'action2',
			'_wpnonce',
			'read',
			'unread',
			'unstarred',
			'starred',
			'deleted',
			'trashed',
			'restored',
		];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['paged'] ) && (int) $_GET['paged'] < 2 ) {
			$remove_args[] = 'paged';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( ! isset( $_GET['search']['term'] ) || wpforms_is_empty_string( $_GET['search']['term'] ) ) {
			$remove_args[] = 'search';
		}

		if ( empty( $this->get_filtered_dates() ) ) {
			$remove_args[] = 'date';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$_SERVER['REQUEST_URI'] = remove_query_arg( $remove_args, $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Load the PHP classes and initialize an entries table.
	 *
	 * @since 1.8.6
	 */
	public function load_entries_list_table() {

		// Create an `ListTable` instance and process bulk actions.
		$this->entries = new ListTable();

		$this->entries->process_bulk_actions();
	}

	/**
	 * Add per-page screen option to the Entries table.
	 *
	 * @since 1.8.6
	 * @deprecated 1.8.6
	 */
	public function screen_options() {

		_deprecated_function( __METHOD__, '1.8.6', 'WPForms\Pro\Admin\Entries\PageOptions::screen_options()' );

		$screen = get_current_screen();

		if ( $screen === null || $screen->id !== 'wpforms_page_wpforms-entries' ) {
			return;
		}

		/**
		 * Filter admin screen option arguments.
		 *
		 * @since 1.8.2
		 *
		 * @param array $args Option-dependent arguments.
		 */
		$args = (array) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'wpforms_entries_list_default_screen_option_args',
			[
				'label'   => esc_html__( 'Number of entries per page:', 'wpforms' ),
				'option'  => 'wpforms_entries_per_page',
				'default' => wpforms()->get( 'entry' )->get_count_per_page(),
			]
		);

		add_screen_option( 'per_page', $args );
	}

	/**
	 * Entries table per-page screen option value.
	 *
	 * @since 1.8.6
	 * @deprecated 1.8.6
	 *
	 * @param mixed  $status Status.
	 * @param string $option Options.
	 * @param mixed  $value  Value.
	 *
	 * @return mixed
	 */
	public function screen_options_set( $status, $option, $value ) {

		_deprecated_function( __METHOD__, '1.8.6', 'WPForms\Pro\Admin\Entries\PageOptions::screen_options_set()' );

		if ( $option === 'wpforms_entries_per_page' ) {
			return $value;
		}

		return $status;
	}

	/**
	 * Enqueue assets for the entries pages.
	 *
	 * @since 1.8.6
	 */
	public function enqueues() {

		// JavaScript.
		wp_enqueue_script(
			'wpforms-flatpickr',
			WPFORMS_PLUGIN_URL . 'assets/lib/flatpickr/flatpickr.min.js',
			[ 'jquery' ],
			'4.6.9'
		);

		// CSS.
		wp_enqueue_style(
			'wpforms-flatpickr',
			WPFORMS_PLUGIN_URL . 'assets/lib/flatpickr/flatpickr.min.css',
			[],
			'4.6.9'
		);

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-admin-list-table-ext',
			WPFORMS_PLUGIN_URL . "assets/css/admin-list-table-ext{$min}.css",
			[ 'wpforms-multiselect-checkboxes' ],
			WPFORMS_VERSION
		);

		wp_enqueue_script(
			'wpforms-admin-list-table-ext',
			WPFORMS_PLUGIN_URL . "assets/js/components/admin/list-table-ext{$min}.js",
			[ 'jquery', 'wpforms-multiselect-checkboxes' ],
			WPFORMS_VERSION,
			true
		);

		wp_enqueue_script(
			'wpforms-admin-entries',
			WPFORMS_PLUGIN_URL . "assets/pro/js/admin/entries{$min}.js",
			[ 'jquery', 'wpforms-flatpickr' ],
			WPFORMS_VERSION,
			true
		);

		/**
		 * Fires on enqueue entries list page assets.
		 *
		 * @since 1.0.0
		 *
		 * @param string $context Current context.
		 */
		do_action( 'wpforms_entries_enqueue', 'list' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Watch for and run complete form exports.
	 *
	 * @since 1.8.6
	 * @deprecated 1.5.5
	 */
	public function process_export() {

		$form_id = $this->get_filtered_form_id();

		// Check for run switch.
		if ( empty( $_GET['export'] ) || ! $form_id || $_GET['export'] !== 'all' || empty( $_GET['_wpnonce'] ) ) {
			return;
		}

		_deprecated_function( __METHOD__, '1.5.5 of the WPForms plugin', 'WPForms\Pro\Admin\Export\Export class' );

		// Security check.
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wpforms_entry_list_export' ) ) {
			return;
		}
		require_once WPFORMS_PLUGIN_DIR . 'pro/includes/admin/entries/class-entries-export.php';

		$export             = new \WPForms_Entries_Export(); // phpcs:ignore WPForms.PHP.BackSlash.RemoveBackslash
		$export->entry_type = 'all';
		$export->form_id    = $form_id;

		$export->export();
	}

	/**
	 * Watch for and run complete marking all entries as read.
	 *
	 * @since 1.8.6
	 */
	public function process_read() {

		$form_id = $this->get_filtered_form_id();

		// Check for run switch.
		if ( empty( $_GET['action'] ) || ! $form_id || $_GET['action'] !== 'markread' || empty( $_GET['_wpnonce'] ) ) {
			return;
		}

		// Security check.
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wpforms_entry_list_markread' ) ) {
			return;
		}

		wpforms()->get( 'entry' )->mark_all_read( $form_id );

		$this->alerts[] = [
			'type'    => 'success',
			'message' => esc_html__( 'All entries marked as read.', 'wpforms' ),
			'dismiss' => true,
		];
	}

	/**
	 * Watch for and update list column settings.
	 *
	 * @since 1.8.6
	 * @deprecated 1.8.6
	 */
	public function process_columns() {

		_deprecated_function( __METHOD__, '1.8.6 of the WPForms plugin', 'WPForms\Pro\Admin\Entries\Ajax\Columns class' );

		// Check for run switch and data.
		if ( empty( $_POST['action'] ) || empty( $_POST['form_id'] ) || $_POST['action'] !== 'list-columns' || empty( $_POST['_wpnonce'] ) ) {
			return;
		}

		// Security check.
		if ( ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'wpforms_entry_list_columns' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$fields  = isset( $_POST['fields'] ) ? explode( ',', wp_unslash( $_POST['fields'] ) ) : [];
		$post_id = absint( $_POST['form_id'] );

		// Remove KSES filters before updating meta for forms and their fields which contain HTML.
		// If we don't do this, forms for users who don't have 'unfiltered_html' capabilities can get corrupt due to conflicts with wp_kses().
		kses_remove_filters();

		// Update or delete.
		Columns::sanitize_and_save_columns( $post_id, $fields );

		// Re-initialize KSES filters for users who don't have 'unfiltered_html' capabilities.
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			kses_init_filters();
		}
	}

	/**
	 * Entry deletion and trigger if needed.
	 *
	 * @since 1.8.6
	 */
	public function process_delete() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// Security check.
		if ( ! check_ajax_referer( 'wpforms-admin', 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session expired. Please reload the builder.', 'wpforms' ) );
		}

		$form_id = $this->get_filtered_form_id();

		// Require data check.
		if ( ! $form_id ) {
			wp_send_json_error( esc_html__( 'Something went wrong while performing this action.', 'wpforms' ) );
		}

		// Permission check.
		if ( ! wpforms_current_user_can( 'delete_entries_form_single', $form_id ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'wpforms' ) );
		}

		// Get the screen first.
		$screen = isset( $_REQUEST['page'] ) ? sanitize_key( $_REQUEST['page'] ) : '';

		// The delete all functionality should only be available on the trash screen.
		// All other places the delete all button will be replaced by trash all button.
		if ( $screen !== self::TRASH_ENTRY_STATUS ) {
			wp_send_json_error( esc_html__( 'Something went wrong while performing this action.', 'wpforms' ) );
		}

		// Default args to find entries.
		$args = [
			'form_id' => $form_id,
			'select'  => 'entry_ids',
			'status'  => $screen,
			'number'  => '-1',
		];

		// Get entries.
		$entries = wpforms()->get( 'entry' )->get_entries( $args );

		if ( ! $entries ) {
			wp_send_json_error( esc_html__( 'Something went wrong while performing this action.', 'wpforms' ) );
		}

		$entry_ids = wp_list_pluck( $entries, 'entry_id' );
		$deleted   = 0;

		// Delete media if any.
		// It must be done before removing the entry itself.
		array_map( [ WPForms_Field_File_Upload::class, 'delete_uploaded_files_from_entry' ], $entry_ids );

		// Delete meta only if the related entry has been removed successfully.
		if ( wpforms()->get( 'entry' )->delete_where_in( 'entry_id', $entry_ids ) ) {
			wpforms()->get( 'entry_meta' )->delete_where_in( 'entry_id', $entry_ids );
			wpforms()->get( 'entry_fields' )->delete_where_in( 'entry_id', $entry_ids );

			$deleted = count( $entry_ids );
		}

		$redirect_url = ! empty( $_GET['url'] ) ? add_query_arg( 'deleted', $deleted, esc_url_raw( wp_unslash( $_GET['url'] ) ) ) : '';

		DashboardWidget::clear_widget_cache();

		wp_send_json_success( $redirect_url );
	}

	/**
	 * Entry trash and trigger if needed.
	 *
	 * @since 1.8.5
	 */
	public function process_trash() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// Security check.
		if ( ! check_ajax_referer( 'wpforms-admin', 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session expired. Please reload the builder.', 'wpforms' ) );
		}

		$form_id = $this->get_filtered_form_id();

		// Require data check.
		if ( ! $form_id ) {
			wp_send_json_error( esc_html__( 'Something went wrong while performing this action.', 'wpforms' ) );
		}

		// Permission check.
		if ( ! wpforms_current_user_can( 'delete_entries_form_single', $form_id ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'wpforms' ) );
		}

		if ( $this->is_list_filtered() ) {
			$this->process_filter_dates();
			$this->process_filter_search();
		}

		$entries_id = $this->process_trash_data( $form_id );

		if ( empty( $entries_id ) ) {
			wp_send_json_error( esc_html__( 'Something went wrong while performing this action.', 'wpforms' ) );
		}

		$trashed = $this->process_trash_with_ids( $entries_id, $form_id );

		$redirect_url = ! empty( $_GET['url'] ) ? add_query_arg( 'trashed', $trashed, esc_url_raw( wp_unslash( $_GET['url'] ) ) ) : '';

		DashboardWidget::clear_widget_cache();

		wp_send_json_success( $redirect_url );
	}

	/**
	 * Process entries for trashing.
	 *
	 * @since 1.8.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array $entries_id Entry IDs.
	 */
	private function process_trash_data( $form_id ) {

		// Check if entries filtered.
		// This also checks if there's entry ids provided. See: FilterSearch Trait.
		if ( ! empty( $this->filter['entry_id'] ) ) {
			return $this->filter['entry_id'];
		}

		// Get the screen first.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$screen = isset( $_REQUEST['page'] ) ? sanitize_key( $_REQUEST['page'] ) : '';

		// We can only trash all on all other screens expect trash screen.
		if ( $screen === self::TRASH_ENTRY_STATUS ) {
			return [];
		}

		// Default args to find entries.
		$args = [
			'form_id' => $form_id,
			'select'  => 'entry_ids',
			'number'  => '-1',
		];

		// Apply the date filter if set.
		if ( ! empty( $this->filter['date'] ) ) {
			$args['date'] = $this->filter['date'];
		}

		// Get entries.
		$all_entries  = wpforms()->get( 'entry' )->get_entries( $args );
		$spam_entries = wpforms()->get( 'entry' )->get_entries(
			[
				'form_id' => $form_id,
				'status'  => SpamEntry::ENTRY_STATUS,
				'select'  => 'entry_ids',
			]
		);

		return wp_list_pluck( array_merge( $all_entries, $spam_entries ), 'entry_id' );
	}

	/**
	 * Process trash when entry ids are given.
	 *
	 * @since 1.8.5
	 *
	 * @param array $entry_ids Entry IDs.
	 * @param int   $form_id   Form ID.
	 *
	 * @return int  $trashed Number of trashed entries.
	 */
	private function process_trash_with_ids( $entry_ids, $form_id ) {

		$trashed = 0;
		$user_id = get_current_user_id();

		foreach ( $entry_ids as $id ) {
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
				[ 'status' => self::TRASH_ENTRY_STATUS ],
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

		return $trashed;
	}

	/**
	 * Return validated information about search or FALSE.
	 *
	 * @since 1.8.6
	 *
	 * @return bool|array
	 */
	protected function get_filter_search_parts() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['search'] ) ) {
			return false;
		}

		$expected = [ 'field', 'comparison', 'term' ];

		foreach ( $expected as $field ) {
			if ( ! isset( $_GET['search'][ $field ] ) || $_GET['search'][ $field ] === '' ) {
				return false;
			}
		}

		return array_map( 'sanitize_text_field', wp_unslash( $_GET['search'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Return HTML with information about the search filter.
	 *
	 * @since 1.8.6
	 *
	 * @return string
	 */
	protected function get_filter_search_html() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity

		$form_id = $this->get_filtered_form_id();
		$data    = $this->get_filter_search_parts();

		if ( ! $data ) {
			return '';
		}

		$comparisons = [
			'contains'     => __( 'contains', 'wpforms' ),
			'contains_not' => __( 'does not contain', 'wpforms' ),
			'is'           => __( 'is', 'wpforms' ),
			'is_not'       => __( 'is not', 'wpforms' ),
		];
		$comparison  = isset( $comparisons[ $data['comparison'] ] ) ? $comparisons[ $data['comparison'] ] : $comparisons['contains'];
		$field       = isset( $data['field'] ) ? $data['field'] : '';
		$term        = isset( $data['term'] ) ? $data['term'] : '';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['search']['term'] ) && wpforms_is_empty_string( $term ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$term = htmlspecialchars( wp_unslash( $_GET['search']['term'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( is_numeric( $field ) && $form_id ) {
			$meta = wpforms()->get( 'form' )->get_field( $form_id, $field );

			if ( isset( $meta['label'] ) ) {
				$field = $meta['label'];
			}
		} else {
			$advanced_options = Helpers::get_search_fields_advanced_options();
			$field            = ! empty( $advanced_options[ $field ] ) ? $advanced_options[ $field ] : __( 'any form field', 'wpforms' );
		}

		return sprintf( /* translators: %1$s - field name, %2$s - operation, %3$s term. */
			__( 'where %1$s %2$s "%3$s"', 'wpforms' ),
			'<em>' . esc_html( $field ) . '</em>',
			esc_html( $comparison ),
			'<em>' . esc_html( $term ) . '</em>'
		);
	}

	/**
	 * Get filtered date range.
	 *
	 * @since 1.8.6
	 *
	 * @return array
	 */
	private function get_filtered_dates() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['date'] ) ) {
			return [];
		}

		$dates = (array) explode( ' - ', sanitize_text_field( wp_unslash( $_GET['date'] ) ) );

		return array_map( 'sanitize_text_field', $dates );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Return an array with information (HTML and id) for each filter for this current view.
	 *
	 * @since 1.8.6
	 *
	 * @return array
	 */
	public function get_filters_html() {

		$filters = [
			'.search-box' => $this->get_filter_search_html(),
		];

		$dates = $this->get_filtered_dates();

		if ( $dates ) {
			$dates = array_map(
				static function ( $date ) {

					return wpforms_date_format( $date );
				},
				$dates
			);

			$html = '';

			switch ( count( $dates ) ) {
				case 1:
					$html = sprintf( /* translators: %s: date. */
						esc_html__( 'on %s', 'wpforms' ),
						'<em>' . $dates[0] . '</em>'
					);
					break;

				case 2:
					$html = sprintf( /* translators: %1$s - date, %2$s - date. */
						esc_html__( 'between %1$s and %2$s', 'wpforms' ),
						'<em>' . $dates[0] . '</em>',
						'<em>' . $dates[1] . '</em>'
					);
					break;
			}

			$filters['.wpforms-filter-date'] = $html;
		}

		return array_filter( $filters );
	}

	/**
	 * Watch for filtering requests from a dates range selection.
	 *
	 * @since 1.8.6
	 */
	public function process_filter_dates() {

		$form_id = $this->get_filtered_form_id();

		if ( empty( $form_id ) ) {
			return;
		}

		$dates = $this->get_filtered_dates();

		if ( empty( $dates ) ) {
			return;
		}

		$this->filter = array_merge(
			$this->filter,
			[
				'date'        => count( $dates ) === 1 ? $dates[0] : $dates,
				'is_filtered' => true,
			]
		);
	}

	/**
	 * Setup entry list page data.
	 *
	 * This function does the error checking and variable setup.
	 *
	 * @since 1.8.6
	 */
	public function setup() {

		if ( wpforms_current_user_can( 'view_forms' ) ) {
			$forms = wpforms()->get( 'form' )->get(
				'',
				[
					'orderby' => 'ID',
					'order'   => 'ASC',
				]
			);

			// Fetch all forms.
			$this->forms = wpforms()->get( 'access' )->filter_forms_by_current_user_capability( $forms, 'view_entries_form_single' );
		}

		// Check that the user has created at least one form.
		if ( empty( $this->forms ) ) {

			$this->alerts[] = [
				'type'    => 'info',
				'message' =>
					sprintf(
						wp_kses(
							/* translators: %s - WPForms Builder page. */
							__( 'Whoops, you haven\'t created a form yet. Want to <a href="%s">give it a go</a>?', 'wpforms' ),
							[
								'a' => [
									'href' => [],
								],
							]
						),
						admin_url( 'admin.php?page=wpforms-builder' )
					),
				'abort'   => true,
			];

		} else {
			$form_id = $this->get_filtered_form_id();

			/**
			 * Filters a default form id on the Entries list page.
			 *
			 * @since 1.1.6
			 *
			 * @param int $form_id Default form id.
			 *
			 * @return int
			 */
			$this->form_id = $form_id ? $form_id : apply_filters( 'wpforms_entry_list_default_form_id', absint( $this->forms[0]->ID ) ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			$this->form    = wpforms()->get( 'form' )->get( $this->form_id, [ 'cap' => 'view_entries_form_single' ] );
		}
	}

	/**
	 * Whether the current list of entries is filtered somehow or not.
	 *
	 * @since 1.8.6
	 *
	 * @return bool
	 */
	protected function is_list_filtered() {

		$search_parts = $this->get_filter_search_parts();
		$dates        = $this->get_filtered_dates();
		$is_filtered  = ! empty( $dates ) || ( isset( $search_parts['term'] ) && ! wpforms_is_empty_string( $search_parts['term'] ) );

		/**
		 * Allows to mark the entries list as filtered or not, based on certain conditions.
		 *
		 * By default, the list is considered filtered if the date range is set or
		 * a non-empty search term is applied.
		 *
		 * @since 1.4.4
		 *
		 * @param bool $is_filtered Is the list filtered?
		 */
		return (bool) apply_filters( 'wpforms_entries_list_is_list_filtered', $is_filtered ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * List all entries in a specific form.
	 *
	 * @since 1.8.6
	 */
	public function list_all() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity

		if ( $this->abort ) {
			return;
		}

		$form_data = ! empty( $this->form->post_content ) ? wpforms_decode( $this->form->post_content ) : '';

		/**
		 * Filter the list all wrap classes.
		 *
		 * @since 1.8.3
		 *
		 * @param array $classes List all wrap classes.
		 */
		$classes = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'wpforms_entries_list_list_all_wrap_classes',
			[
				'wrap',
				'wpforms-admin-wrap',
			]
		);
		?>

		<div id="wpforms-entries-list" class="<?php echo wpforms_sanitize_classes( $classes, true ); ?>">

			<h1 class="page-title"><?php esc_html_e( 'Entries', 'wpforms' ); ?></h1>

			<?php
			$this->entries->form_id   = $this->form_id;
			$this->entries->form_data = $form_data;

			$this->entries->prepare_items();

			$last_entry = wpforms()->get( 'entry' )->get_last( $this->form_id );
			?>

			<?php $this->entries_disabled_notice(); ?>

			<div class="wpforms-admin-content">

			<?php

			// Show empty entries table after delete bulk action.
			$is_deleted = isset( $_REQUEST['deleted'] ) && $_REQUEST['deleted'] === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if (
				empty( $this->entries->items ) &&
				$this->entries->counts['spam'] === 0 &&
				$this->entries->counts['trash'] === 0 &&
				! $is_deleted &&
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				! isset( $_GET['search'] ) && ! isset( $_GET['date'] ) && ! isset( $_GET['type'] ) && ! isset( $_GET['status'] )
			) {

				// Output no entries screen.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wpforms_render(
					'admin/empty-states/no-entries',
					[
						'message' => ! empty( $this->entries->form_data['settings']['disable_entries'] )
							? __( 'Storing entry information has been disabled for this form.', 'wpforms' )
							: __( 'It looks like you don\'t have any form entries just yet - check back soon!', 'wpforms' ),
					],
					true
				);

			} else {
				/**
				 * Fire before entries table.
				 *
				 * @since 1.1.6
				 *
				 * @param array $form_data    Form content.
				 * @param Page  $entries_list WPForms\Pro\Admin\Entries\Page class instance.
				 */
				do_action( 'wpforms_entry_list_title', $form_data, $this ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

				// Are we on the "Spam" or "Trash" tab?
				// phpcs:disable WordPress.Security.NonceVerification.Recommended
				$has_status = isset( $_GET['status'] ) && in_array( $_GET['status'], [ SpamEntry::ENTRY_STATUS, self::TRASH_ENTRY_STATUS ], true );
				$status     = $has_status ? sanitize_key( $_GET['status'] ) : '';
				// phpcs:enable WordPress.Security.NonceVerification.Recommended
			?>

				<form id="wpforms-entries-table" method="GET"
					action="<?php echo esc_url( admin_url( 'admin.php?page=wpforms-entries' ) ); ?>"
					<?php echo ( ! $this->is_list_filtered() && isset( $last_entry->entry_id ) ) ? 'data-last-entry-id="' . absint( $last_entry->entry_id ) . '"' : ''; ?> <?php printf( 'data-filtered-count-trash="%1d"', absint( $this->entries->counts['total'] ) ); ?>>

					<?php if ( $this->get_filters_html() ) : ?>

						<div id="wpforms-reset-filter">
							<?php

							printf(
								wp_kses( /* translators: %s - number of entries found. */
									_n(
										'Found <strong>%s entry</strong>',
										'Found <strong>%s entries</strong>',
										absint( count( $this->entries->items ) ),
										'wpforms'
									),
									[
										'strong' => [],
									]
								),
								$has_status ? absint( $this->entries->counts[ $status ] ) : absint( $this->entries->counts['total'] )
							);
							?>

							<?php foreach ( $this->get_filters_html() as $id => $html ) : ?>
								<?php
								echo wp_kses(
									$html,
									[ 'em' => [] ]
								);
								?>
								<i class="reset fa fa-times-circle" data-scope="<?php echo esc_attr( $id ); ?>"></i>
							<?php endforeach; ?>
						</div>
					<?php endif ?>

					<input type="hidden" name="page" value="wpforms-entries" />
					<input type="hidden" name="view" value="list" />
					<input type="hidden" name="form_id" value="<?php echo absint( $this->form_id ); ?>" />

					<?php if ( $has_status ) : ?>
						<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
					<?php endif; ?>

					<?php $this->entries->views(); ?>

					<?php $this->entries->search_box( esc_html__( 'Search', 'wpforms' ), 'wpforms-entries' ); ?>

					<?php $this->entries->display(); ?>

				</form>

			<?php } ?>

			</div>

		</div>

		<?php
	}

	/**
	 * Display abort message using empty state page.
	 *
	 * @since 1.8.6
	 */
	public function display_abort_message() {

		if ( ! $this->abort ) {
			return;
		}

		?>
		<div id="wpforms-entries-list" class="wrap wpforms-admin-wrap">

			<h1 class="page-title">
				<?php esc_html_e( 'Entries', 'wpforms' ); ?>
			</h1>
			<div class="wpforms-admin-content">
				<?php
				// Output empty state screen.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wpforms_render(
					'admin/empty-states/no-entries',
					[
						'message' => $this->abort_message,
					],
					true
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Settings for field column personalization!
	 *
	 * @since 1.8.6
	 */
	public function field_column_setting() {

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_columns_multiselect();
	}

	/**
	 * Get columns multiselect menu.
	 *
	 * @since 1.8.6
	 *
	 * @return string HTML menu markup.
	 */
	private function get_columns_multiselect(): string {

		$field_columns = Columns::get_field_columns( $this->form_id );
		$meta_columns  = Columns::get_meta_columns();
		$selected_keys = Columns::get_selected_columns_keys();
		$selected_keys = empty( $selected_keys ) ? Columns::get_default_columns_keys() : $selected_keys;

		$html = '
			<div id="wpforms-list-table-ext-edit-columns-select-container" class="wpforms-hidden wpforms-list-table-ext-edit-columns-select-container wpforms-entries-list-page">
			<form method="post" action="' . esc_url( admin_url( 'admin.php?page=wpforms-entries&view=list&form_id=' . (int) $this->form_id ) ) . '">
				<input type="hidden" name="action" value="wpforms_admin_entries_list_save_columns_order"/>
				<input type="hidden" name="form_id" value="' . (int) $this->form_id . '"/>
				<select name="fields[]"
					id="wpforms-entries-table-edit-columns-select"
					class="wpforms-entries-table-edit-columns-select wpforms-list-table-ext-edit-columns-select"
					name="wpforms-entries-table-edit-columns-select"
					multiple="multiple">
					%s
					%s
				</select>
			</form>
			</div>
		';

		$field_options = $this->get_optgroup_html( $field_columns, $selected_keys, esc_html__( 'Form Fields', 'wpforms' ) );
		$meta_options  = $this->get_optgroup_html( $meta_columns, $selected_keys, esc_html__( 'Entry Meta', 'wpforms' ) );

		return sprintf( $html, $field_options, $meta_options );
	}

	/**
	 * Get columns multiselect options.
	 *
	 * @since 1.8.6
	 *
	 * @param Column[] $columns       List of columns.
	 * @param array    $selected_keys List of selected columns keys.
	 * @param string   $label         Label for the optgroup.
	 *
	 * @return string HTML options markup.
	 */
	private function get_optgroup_html( $columns, $selected_keys, $label ): string {

		if ( empty( $columns ) ) {
			return '';
		}

		$html = '<optgroup label="' . esc_html( $label ) . '">';

		foreach ( $columns as $column ) {
			$selected = in_array( $column->get_id(), $selected_keys, true ) ? 'selected' : '';
			$html    .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $column->get_id() ), $selected, esc_html( $column->get_label() ) );
		}

		$html .= '</optgroup>';

		return $html;
	}

	/**
	 * Entries list form actions.
	 *
	 * @since 1.8.6
	 *
	 * @param array $form_data Form data and settings.
	 */
	public function list_form_actions( $form_data ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity

		$base = add_query_arg(
			[
				'page'    => 'wpforms-entries',
				'view'    => 'list',
				'form_id' => absint( $this->form_id ),
			],
			admin_url( 'admin.php' )
		);

		// Edit Form URL.
		$edit_url = add_query_arg(
			[
				'page'    => 'wpforms-builder',
				'view'    => 'fields',
				'form_id' => absint( $this->form_id ),
			],
			admin_url( 'admin.php' )
		);

		// Payments URL.
		if ( wpforms()->get( 'payment' )->get_by( 'form_id', $this->form_id ) ) {
			$payments_url = add_query_arg(
				[
					'page'    => 'wpforms-payments',
					'form_id' => absint( $this->form_id ),
				],
				admin_url( 'admin.php' )
			);
		}

		// Preview Entry URL.
		$preview_url = esc_url( wpforms_get_form_preview_url( $this->form_id ) );

		// Export Entry URL.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$export_url = add_query_arg(
			[
				'page'   => 'wpforms-tools',
				'view'   => 'export',
				'form'   => absint( $this->form_id ),
				'search' => ! empty( $_GET['search'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_GET['search'] ) ) : [],
				'date'   => ! empty( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : [],
			],
			admin_url( 'admin.php' )
		);

		$read_url_args = [ 'action' => 'markread' ];

		if ( isset( $_GET['status'] ) && ! empty( $_GET['status'] ) ) {
			$read_url_args['status'] = sanitize_key( $_GET['status'] );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Mark Read URL.
		$read_url = wp_nonce_url(
			add_query_arg(
				$read_url_args,
				$base
			),
			'wpforms_entry_list_markread'
		);

		// Delete all entries.
		$delete_url = wp_nonce_url( $base, 'bulk-entries' );

		// Form title.
		$form_title = isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : '';

		if ( empty( $form_title ) ) {
			$form = wpforms()->get( 'form' )->get( $this->form_id );

			$form_title = ! empty( $form )
				? $form->post_title
				: sprintf( /* translators: %d - form ID. */
					esc_html__( 'Form (#%d)', 'wpforms' ),
					$this->form_id
				);
		}
		?>

		<div class="form-details">

			<span class="form-details-sub"><?php esc_html_e( 'Select Form', 'wpforms' ); ?></span>

			<h3 class="form-details-title">
				<?php
				echo esc_html( wp_strip_all_tags( $form_title ) );
				$this->form_selector_html();
				?>
			</h3>

			<div class="form-details-actions">

				<?php if ( $this->is_list_filtered() ) : ?>
					<a href="<?php echo esc_url( $base ); ?>" class="form-details-actions-entries">
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'All Entries', 'wpforms' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( isset( $payments_url ) ) : ?>
					<a href="<?php echo esc_url( $payments_url ); ?>" class="form-details-actions-view-payments">
						<span class="fa fa-credit-card"></span>
						<?php esc_html_e( 'View Payments', 'wpforms' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( wpforms_current_user_can( 'edit_form_single', $this->form_id ) ) : ?>
					<a href="<?php echo esc_url( $edit_url ); ?>" class="form-details-actions-edit">
						<span class="dashicons dashicons-edit"></span>
						<?php esc_html_e( 'Edit This Form', 'wpforms' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( wpforms_current_user_can( 'view_form_single', $this->form_id ) ) : ?>
					<a href="<?php echo esc_url( $preview_url ); ?>" class="form-details-actions-preview" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-visibility"></span>
						<?php esc_html_e( 'Preview Form', 'wpforms' ); ?>
					</a>
				<?php endif; ?>


				<a href="<?php echo esc_url( $export_url ); ?>" class="form-details-actions-export">
					<span class="dashicons dashicons-migrate"></span>
					<?php echo $this->is_list_filtered() ? esc_html__( 'Export Filtered', 'wpforms' ) : esc_html__( 'Export All', 'wpforms' ); ?>
				</a>

				<a href="<?php echo esc_url( $read_url ); ?>" class="form-details-actions-read">
					<span class="dashicons dashicons-marker"></span>
					<?php esc_html_e( 'Mark All Read', 'wpforms' ); ?>
				</a>

				<?php if ( wpforms_current_user_can( 'delete_entries_form_single', $this->form_id ) ) : ?>
					<?php $status = ! empty( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>

					<a href="<?php echo esc_url( $delete_url ); ?>" class="form-details-actions-removeall" data-page="<?php echo esc_attr( $status ); ?>">
						<span class="dashicons dashicons-trash"></span>
						<?php $status === self::TRASH_ENTRY_STATUS ? esc_html_e( 'Empty Trash', 'wpforms' ) : esc_html_e( 'Trash All', 'wpforms' ); ?>
					</a>
				<?php endif; ?>

			</div>

		</div>
		<?php
	}

	/**
	 * Display form selector HTML.
	 *
	 * @since 1.8.6
	 */
	protected function form_selector_html() {

		if ( ! wpforms_current_user_can( 'view_forms' ) ) {
			return;
		}

		if ( empty( $this->forms ) ) {
			return;
		}

		?>
		<div class="form-selector">
			<a href="#" title="<?php esc_attr_e( 'Open form selector', 'wpforms' ); ?>" class="toggle dashicons dashicons-arrow-down-alt2"></a>
			<div class="form-list">
				<ul>
					<?php
					foreach ( $this->forms as $key => $form ) {
						$form_url = add_query_arg(
							[
								'page'    => 'wpforms-entries',
								'view'    => 'list',
								'form_id' => absint( $form->ID ),
							],
							admin_url( 'admin.php' )
						);

						echo '<li><a href="' . esc_url( $form_url ) . '">' . esc_html( $form->post_title ) . '</a></li>';
					}
					?>
				</ul>
			</div>

		</div>
		<?php
	}

	/**
	 * Add notices and errors.
	 *
	 * @since 1.8.6
	 */
	public function register_alerts() {

		if ( empty( $this->alerts ) ) {
			return;
		}

		foreach ( $this->alerts as $alert ) {
			$type = ! empty( $alert['type'] ) ? $alert['type'] : 'info';

			Notice::add( $alert['message'], $type );

			if ( ! empty( $alert['abort'] ) ) {
				$this->abort = true;

				break;
			}
		}
	}

	/**
	 * Display admin notices and errors.
	 *
	 * @since 1.8.6
	 * @deprecated 1.6.7.1
	 *
	 * @param string $display Notice text.
	 * @param bool   $wrap    Whether wrap it or not.
	 */
	public function display_alerts( $display = '', $wrap = false ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity, Generic.Metrics.NestingLevel

		_deprecated_function( __METHOD__, '1.6.7.1 of the WPForms plugin' );

		if ( empty( $this->alerts ) ) {
			return;

		} else {

			if ( empty( $display ) ) {
				$display = [ 'error', 'info', 'warning', 'success' ];
			} else {
				$display = (array) $display;
			}

			foreach ( $this->alerts as $alert ) {

				$type = ! empty( $alert['type'] ) ? $alert['type'] : 'info';

				if ( in_array( $type, $display, true ) ) {
					$classes  = 'notice-' . $type;
					$classes .= ! empty( $alert['dismiss'] ) ? ' is-dismissible' : '';

					$output = sprintf(
						'<div class="notice %s"><p>%s</p></div>',
						wpforms_sanitize_classes( $classes ),
						wp_kses(
							$alert['message'],
							[
								'a' => [
									'href' => [],
								],
							]
						)
					);

					if ( $wrap ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo '<div class="wrap">' . $output . '</div>';
					} else {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $output;
					}
					if ( ! empty( $alert['abort'] ) ) {
						$this->abort = true;

						break;
					}
				}
			}
		}
	}

	/**
	 * Check for new entries using Heartbeat API.
	 *
	 * @since 1.8.6
	 *
	 * @param array  $response  The Heartbeat response.
	 * @param array  $data      The $_POST data sent.
	 * @param string $screen_id The screen id.
	 *
	 * @return array
	 */
	public function heartbeat_new_entries_check( $response, $data, $screen_id ) {

		if ( $screen_id !== 'wpforms_page_wpforms-entries' ) {
			return $response;
		}

		$entry_id = ! empty( $data['wpforms_new_entries_entry_id'] ) ? absint( $data['wpforms_new_entries_entry_id'] ) : 0;
		$form_id  = ! empty( $data['wpforms_new_entries_form_id'] ) ? absint( $data['wpforms_new_entries_form_id'] ) : 0;

		if ( empty( $form_id ) ) {
			return $response;
		}

		$entries_count = wpforms()->get( 'entry' )->get_next_count( $entry_id, $form_id, '' );

		if ( empty( $entries_count ) ) {
			return $response;
		}

		/* translators: %d - number of form entries. */
		$response['wpforms_new_entries_notification'] = esc_html( sprintf( _n( 'See %d new entry', 'See %d new entries', $entries_count, 'wpforms' ), $entries_count ) );

		return $response;
	}

	/**
	 * Localize needed strings.
	 *
	 * @since 1.8.6
	 *
	 * @param array $strings JS strings.
	 *
	 * @return mixed
	 */
	public function js_strings( $strings ) {

		$strings['lang_code']    = sanitize_key( wpforms_get_language_code() );
		$strings['default_date'] = [];
		$dates                   = $this->get_filtered_dates();

		if ( $dates ) {
			if ( count( $dates ) === 1 ) {
				$dates[1] = $dates[0];
			}
			$strings['default_date'] = [ sanitize_text_field( $dates[0] ), sanitize_text_field( $dates[1] ) ];
		}

		return $strings;
	}

	/**
	 * Display info notice for forms that have entries and disable_entries setting.
	 *
	 * The entries list isn't available when register_alerts runs.
	 *
	 * @since 1.8.6
	 *
	 * @return void
	 */
	protected function entries_disabled_notice() {

		if (
			empty( $this->entries->form_data['settings']['disable_entries'] )
			|| empty( $this->entries->items )
		) {
			return;
		}

		?>

		<div class="notice wpforms-notice notice-info" style="display: block;">
			<p>
				<?php esc_html_e( 'Storing entry information has been disabled for this form.', 'wpforms' ); ?>
			</p>
		</div>

		<?php
	}
}
