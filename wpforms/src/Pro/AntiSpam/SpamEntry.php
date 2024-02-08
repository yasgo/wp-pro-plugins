<?php

namespace WPForms\Pro\AntiSpam;

/**
 * Class SpamEntry.
 *
 * @since 1.8.3
 */
class SpamEntry {

	/**
	 * Entry status.
	 *
	 * @since 1.8.3
	 */
	const ENTRY_STATUS = 'spam';

	/**
	 * Initialize.
	 *
	 * @since 1.8.3
	 *
	 * @return void
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.8.3
	 *
	 * @return void
	 */
	public function hooks() {

		// Spam entry save.
		add_filter( 'wpforms_entry_save_args', [ $this, 'add_spam_status' ], 10, 2 );

		// Spam entries list.
		add_filter( 'wpforms_entries_table_counts', [ $this, 'entries_table_counts' ], 10, 2 );
		add_filter( 'wpforms_entries_table_views', [ $this, 'entries_table_views' ], 10, 3 );
		add_filter( 'wpforms_entries_single_details_actions_disable', [ $this, 'disallow_details_actions' ], 10, 2 );
		add_filter( 'wpforms_entry_table_actions', [ $this, 'filter_entry_actions' ], 10, 2 );

		// Spam entries table.
		add_filter( 'wpforms_entries_table_display_date_range_filter_disable', [ $this, 'disable_date_range_filter' ] );
		add_action( 'wpforms_entries_table_extra_tablenav', [ $this, 'add_remove_spam_entries_button' ] );
		add_filter( 'wpforms_entries_table_get_bulk_actions', [ $this, 'filter_bulk_actions' ] );
		add_filter( 'wpforms_entries_table_get_table_classes', [ $this, 'add_spam_entries_table_class' ] );
		add_filter( 'wpforms_entries_single_details_form_url', [ $this, 'filter_spam_form_url' ], 10, 3 );

		// Spam entry actions.
		add_action( 'admin_init', [ $this, 'entry_actions' ] );
		add_action( 'admin_notices', [ $this, 'entry_notices' ] );
		add_action( 'wpforms_process_entry_saved', [ $this, 'add_meta_data' ], 20, 4 );
		add_filter( 'wpforms_entries_table_process_actions_entries_list', [ $this, 'filter_process_actions_entries_list' ], 10, 2 );

		add_filter( 'wpforms_entry_email' , [ $this, 'disable_entry_email' ], 10, 4 );

		// Additional wrap classes.
		add_filter( 'wpforms_entries_list_list_all_wrap_classes', [ $this, 'add_wrap_classes' ] );
	}

	/**
	 * Add spam status to the entry save args.
	 *
	 * @since 1.8.3
	 *
	 * @param array $args      Entry save args.
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	public function add_spam_status( $args, $form_data ) {

		if ( $this->is_spam_form_data( $form_data ) ) {
			$args['status'] = self::ENTRY_STATUS;
		}

		return $args;
	}

	/**
	 * Add spam meta data to the entry.
	 *
	 * @since 1.8.3
	 *
	 * @param array $fields    Entry fields.
	 * @param array $entry     Entry data.
	 * @param array $form_data Form data.
	 * @param int   $entry_id  Entry ID.
	 */
	public function add_meta_data( $fields, $entry, $form_data, $entry_id ) {

		$spam_reason = ! empty( $form_data['spam_reason'] ) ? $form_data['spam_reason'] : null;

		if ( ! $spam_reason ) {
			return;
		}

		wpforms()->get( 'entry_meta' )->add(
			[
				'entry_id' => absint( $entry_id ),
				'form_id'  => absint( $form_data['id'] ),
				'type'     => 'spam',
				'data'     => sanitize_text_field( $spam_reason ),
			],
			'entry_meta'
		);

		wpforms()->get( 'entry_meta' )->add(
			[
				'entry_id' => absint( $entry_id ),
				'form_id'  => absint( $form_data['id'] ),
				'type'     => 'post_data_raw',
				'data'     => wp_json_encode( $form_data['post_data_raw'] ),
			],
			'entry_meta'
		);
	}

	/**
	 * Get entry spam reason.
	 *
	 * @since 1.8.3
	 *
	 * @param int $entry_id Entry ID.
	 *
	 * @return string
	 */
	private function get_spam_reason( $entry_id ) {

		$reason = wpforms()->get( 'entry_meta' )->get_meta(
			[
				'entry_id' => absint( $entry_id ),
				'type'     => 'spam',
				'number'   => 1,
			]
		);

		return ! empty( $reason[0] ) ? $reason[0]->data : '';
	}

	/**
	 * Get entry post data raw.
	 *
	 * @since 1.8.3
	 *
	 * @param int $entry_id Entry ID.
	 *
	 * @return array
	 */
	private function get_entry_post_data_raw( $entry_id ) {

		$post_data_raw = wpforms()->get( 'entry_meta' )->get_meta(
			[
				'entry_id' => absint( $entry_id ),
				'type'     => 'post_data_raw',
				'number'   => 1,
			]
		);

		return ! empty( $post_data_raw[0] ) ? json_decode( $post_data_raw[0]->data, true ) : [];
	}

	/**
	 * Disable entry email if the entry is marked as spam.
	 *
	 * @since 1.8.3
	 *
	 * @param bool  $enabled   Whether the email is enabled.
	 * @param array $fields    Entry fields.
	 * @param array $entry     Entry data.
	 * @param array $form_data Form data.
	 *
	 * @return bool
	 */
	public function disable_entry_email( $enabled, $fields, $entry, $form_data ) {

		if ( $this->is_spam_form_data( $form_data ) ) {
			return false;
		}

		return $enabled;
	}

	/**
	 * Send entry email if the entry is marked as spam.
	 *
	 * @since 1.8.3
	 *
	 * @param int   $entry_id  Entry ID.
	 * @param array $fields    Entry fields.
	 * @param array $form_data Form data.
	 */
	private function send_entry_email( $entry_id, $fields, $form_data ) {

		wpforms()->get( 'process' )->entry_email( $fields, [], $form_data, $entry_id, 'entry' );
	}

	/**
	 * Delete entry spam reason.
	 *
	 * @since 1.8.3
	 *
	 * @param int $entry_id Entry ID.
	 */
	private function delete_spam_reason( $entry_id ) {

		wpforms()->get( 'entry_meta' )->delete(
			[
				'entry_id' => absint( $entry_id ),
				'type'     => 'spam',
			]
		);
	}

	/**
	 * Add spam entries to the entries table counts.
	 *
	 * @since 1.8.3
	 *
	 * @param array $counts    Entries table counts.
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	public function entries_table_counts( $counts, $form_data ) {

		$counts['spam'] = $this->get_spam_entries_count( $form_data['id'] );

		return $counts;
	}

	/**
	 * Add spam entries to the entries table views.
	 *
	 * @since 1.8.3
	 *
	 * @param array $views     Entries table views.
	 * @param array $form_data Form data.
	 * @param array $counts    Entries table counts.
	 *
	 * @return array
	 */
	public function entries_table_views( $views, $form_data, $counts ) {

		$views['spam'] = sprintf(
			'<a href="%1$s" class="%2$s">%3$s <span class="count">(%4$d)</span></a>',
			esc_url( $this->get_spam_entries_list_url( $form_data['id'] ) ),
			$this->is_spam_list() ? 'current' : '',
			esc_html__( 'Spam', 'wpforms' ),
			absint( $counts['spam'] )
		);

		return $views;
	}

	/**
	 * Display spam entry notices.
	 *
	 * @since 1.8.3
	 */
	public function entry_notices() {

		if ( ! wpforms_is_admin_page( 'entries' ) ) {
			return;
		}

		$this->maybe_display_success_message();

		$this->maybe_display_error_message();
	}

	/**
	 * Maybe display a success message.
	 *
	 * @since 1.8.3
	 */
	private function maybe_display_success_message() {

		// Show a success message after marking entry as not spam.
		$message = ! empty( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $message === 'unspam' ) {
			wpforms()->get( 'notice' )->success( esc_html__( 'Entry successfully unmarked as spam.', 'wpforms' ) );
		}
	}

	/**
	 * Maybe display an error message.
	 *
	 * @since 1.8.3
	 */
	private function maybe_display_error_message() {

		if ( ! wpforms_is_admin_page( 'entries', 'details' ) ) {
			return;
		}

		$entry_id = $this->get_current_entry_id();

		if ( ! $entry_id ) {
			return;
		}

		$is_spam = $this->is_spam_entry( $entry_id );

		if ( ! $is_spam ) {
			return;
		}

		$spam_reason = $this->get_spam_reason( $entry_id );

		$mark_not_spam_url = wp_nonce_url(
			add_query_arg(
				[
					'action'   => 'mark_not_spam',
					'entry_id' => $entry_id,
				]
			),
			'edit-entry'
		);

		// Show error message if entry is spam.
		wpforms()->get( 'notice' )->error(
			sprintf(
				'%s %s',
				sprintf(
				/* translators: %s - antispam method. */
					esc_html__( 'This entry was marked as spam by %s.', 'wpforms' ),
					esc_html( $spam_reason )
				),
				sprintf( '<a href="%1$s">%2$s</a>', esc_url( $mark_not_spam_url ), esc_html__( 'Mark as Not Spam', 'wpforms' ) )
			),
			[
				'class' => 'wpforms-notice-spam',
			]
		);
	}

	/**
	 * Entry actions.
	 *
	 * @since 1.8.3
	 */
	public function entry_actions() {

		$nonce = ! empty( $_REQUEST['_wpnonce'] ) ? sanitize_key( $_REQUEST['_wpnonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'edit-entry' ) ) {
			return;
		}

		$action = ! empty( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( $action === 'mark_not_spam' ) {
			$this->action_mark_not_spam();
		}
	}

	/**
	 * Mark entry as not spam action.
	 *
	 * @since 1.8.3
	 */
	private function action_mark_not_spam() {

		$entry_id = $this->get_current_entry_id();

		if ( ! $this->is_spam_entry( $entry_id ) ) {
			return;
		}

		// Prepare entry data to use in post-processing.
		$entry = wpforms()->get( 'entry' )->get( $entry_id );

		if ( ! $entry ) {
			return;
		}

		// Mark entry as not spam.
		$this->set_as_not_spam( $entry );

		$this->process_complete( $entry );
	}

	/**
	 * Process entry as not spam.
	 *
	 * @since 1.8.3
	 *
	 * @param object $entry Entry data.
	 */
	private function process_complete( $entry ) {

		$entry_id = $entry->entry_id;
		$form_id  = $entry->form_id;

		$fields    = wpforms_decode( $entry->fields );
		$form_data = wpforms()->get( 'form' )->get(
			$form_id,
			[
				'content_only' => true,
			]
		);

		$form_data['post_data_raw'] = $this->get_entry_post_data_raw( $entry_id );

		wpforms()->get( 'process' )->process_complete( $form_id, $form_data, $fields, [], $entry_id );

		// Send email notification.
		$this->send_entry_email( $entry_id, $fields, $form_data );

		wp_safe_redirect( add_query_arg( 'message', 'unspam', wp_get_referer() ) );
		exit;
	}

	/**
	 * Filter entries list by spam status.
	 *
	 * @since 1.8.3
	 *
	 * @param array $entries_list Entries list.
	 * @param array $args         Query args.
	 *
	 * @return array
	 */
	public function filter_process_actions_entries_list( $entries_list, $args ) {

		$ids = $args['entry_id'];

		if ( empty( $ids ) ) {
			return $entries_list;
		}

		/**
		 * If we have just one entry ID in the list, it means that bulk action fired for spam entries.
		 * If it is, we need to change the status of the query to 'spam' to get the correct list.
		 */
		if ( $this->is_spam_entry( $ids[0] ) ) {
			$args['status'] = self::ENTRY_STATUS;
			$entries_list   = (array) wpforms()->get( 'entry' )->get_entries( $args );
		}

		return $entries_list;
	}

	/**
	 * Set entry as not spam.
	 *
	 * @since 1.8.3
	 *
	 * @param object $entry Entry data.
	 */
	private function set_as_not_spam( $entry ) {

		wpforms()->get( 'entry' )->update( $entry->entry_id, [ 'status' => '' ] );

		// Add record to entry meta.
		wpforms()->get( 'entry_meta' )->add(
			[
				'entry_id' => (int) $entry->entry_id,
				'form_id'  => (int) $entry->form_id,
				'user_id'  => get_current_user_id(),
				'type'     => 'log',
				'data'     => wpautop( sprintf( '<em>%s</em>', __( 'Marked as not spam.', 'wpforms' ) ) ),
			],
			'entry_meta'
		);

		$this->delete_spam_reason( $entry->entry_id );
	}

	/**
	 * Disallow details actions for spam entries.
	 *
	 * @since 1.8.3
	 *
	 * @param bool   $disable Disable actions.
	 * @param object $entry   Entry object.
	 *
	 * @return bool
	 */
	public function disallow_details_actions( $disable, $entry ) {

		return $this->is_spam_entry( $entry->entry_id );
	}

	/**
	 * Filter entry actions for spam entries.
	 *
	 * @since 1.8.3
	 *
	 * @param array  $actions Actions.
	 * @param object $entry   Entry object.
	 *
	 * @return array
	 */
	public function filter_entry_actions( $actions, $entry ) {

		if ( $this->is_spam_entry( $entry->entry_id ) ) {

			$args = [
				'action'   => 'mark_not_spam',
				'entry_id' => $entry->entry_id,
				'form_id'  => $entry->form_id,
			];

			$url = wp_nonce_url( $this->get_spam_entries_list_url( $entry->form_id, $args ), 'edit-entry' );

			$actions['edit'] = sprintf(
				'<a href="%s" title="%s" class="mark-not-spam">%s</a>',
				esc_url( $url ),
				esc_attr__( 'Mark as Not Spam', 'wpforms' ),
				esc_html__( 'Not Spam', 'wpforms' )
			);
		}

		return $actions;
	}

	/**
	 * Disallow entry date range filter for spam entries.
	 *
	 * @since 1.8.3
	 *
	 * @return bool
	 */
	public function disable_date_range_filter() {

		return $this->is_spam_list();
	}

	/**
	 * Add button to remove spam entries.
	 *
	 * @since 1.8.3
	 */
	public function add_remove_spam_entries_button() {

		$form_id = $this->get_current_form_id();

		if ( ! $form_id ) {
			return;
		}

		if ( ! $this->is_spam_list() ) {
			return;
		}

		if ( ! $this->get_spam_entries_count( $form_id ) ) {
			return;
		}

		submit_button( esc_html__( 'Empty Spam', 'wpforms' ), 'apply', 'empty_spam', false );
	}

	/**
	 * Filter bulk actions for spam entries.
	 *
	 * @since 1.8.3
	 *
	 * @param array $actions Bulk actions.
	 *
	 * @return array
	 */
	public function filter_bulk_actions( $actions ) {

		if ( $this->is_spam_list() ) {
			$allowed_actions = [
				'read',
				'unread',
				'null',
				'trash',
				'delete',
			];

			$actions = array_intersect_key( $actions, array_flip( $allowed_actions ) );
		}

		return $actions;
	}

	/**
	 * Add spam entries table class.
	 *
	 * @since 1.8.3
	 *
	 * @param array $classes Table classes.
	 *
	 * @return array
	 */
	public function add_spam_entries_table_class( $classes ) {

		if ( $this->is_spam_list() ) {
			$classes[] = 'wpforms-entries-table-spam';
		}

		return $classes;
	}

	/**
	 * Add spam entries wrap class.
	 *
	 * @since 1.8.3
	 *
	 * @param array $classes Wrap classes.
	 *
	 * @return array
	 */
	public function add_wrap_classes( $classes ) {

		if ( $this->is_spam_list() && ! $this->get_spam_entries_count( $this->get_current_form_id() ) ) {
			$classes[] = 'wpforms-entries-spam-empty';
		}

		return $classes;
	}

	/**
	 * Filter Back to All Entries link for spam entries.
	 *
	 * @since 1.8.3
	 *
	 * @param string $url      Form URL.
	 * @param int    $entry_id Entry ID.
	 * @param int    $form_id  Form ID.
	 *
	 * @return string
	 */
	public function filter_spam_form_url( $url, $entry_id, $form_id ) {

		if ( $this->is_spam_entry( $entry_id ) ) {
			return $this->get_spam_entries_list_url( $form_id );
		}

		return $url;
	}

	/**
	 * Check if entry is spam.
	 *
	 * @since 1.8.3
	 *
	 * @param int $entry_id Entry ID.
	 *
	 * @return bool
	 */
	private function is_spam_entry( $entry_id ) {

		$entry = wpforms()->get( 'entry' )->get( $entry_id );

		if ( ! $entry ) {
			return false;
		}

		return $entry->status === self::ENTRY_STATUS;
	}

	/**
	 * Check if form data marked as spam.
	 *
	 * @since 1.8.3
	 *
	 * @param array $form_data Form data.
	 *
	 * @return bool
	 */
	private function is_spam_form_data( $form_data ) {

		return ! empty( $form_data['spam_reason'] );
	}

	/**
	 * Check if the current page is a spam list.
	 *
	 * @since 1.8.3
	 *
	 * @return bool
	 */
	private function is_spam_list() {

		$status = ! empty( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return $status === self::ENTRY_STATUS;
	}

	/**
	 * Get spam entries count.
	 *
	 * @since 1.8.3
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return int
	 */
	private function get_spam_entries_count( $form_id ) {

		return wpforms()->get( 'entry' )->get_entries(
			[
				'form_id' => $form_id,
				'status'  => self::ENTRY_STATUS,
			],
			true
		);
	}

	/**
	 * Get spam entries list URL.
	 *
	 * @since 1.8.3
	 *
	 * @param int   $form_id Form ID.
	 * @param array $args    Additional arguments.
	 *
	 * @return string
	 */
	private function get_spam_entries_list_url( $form_id, $args = [] ) {

		$defaults = [
			'page'    => 'wpforms-entries',
			'view'    => 'list',
			'form_id' => $form_id,
			'status'  => self::ENTRY_STATUS,
		];

		$args = wp_parse_args( $args, $defaults );

		$base = remove_query_arg( [ 'type', 'status', 'paged' ] );

		return add_query_arg( $args, $base );
	}

	/**
	 * Get current entry ID.
	 *
	 * @since 1.8.3
	 *
	 * @return int
	 */
	private function get_current_entry_id() {

		return ! empty( $_REQUEST['entry_id'] ) ? absint( $_REQUEST['entry_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Get current form ID.
	 *
	 * @since 1.8.3
	 *
	 * @return int
	 */
	private function get_current_form_id() {

		return ! empty( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
