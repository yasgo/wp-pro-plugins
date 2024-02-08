<?php

namespace WPForms\Pro\Admin\Entries\Ajax;

use WPForms\Pro\Admin\Entries\Table\Facades;

/**
 * Columns AJAX actions on Entries list page.
 *
 * @since 1.8.6
 */
class Columns {

	/**
	 * Determine if the class is allowed to load.
	 *
	 * @since 1.8.6
	 *
	 * @return bool
	 */
	private function allow_load(): bool {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		// Load only in the case of AJAX calls on Entries page.
		return wpforms_is_admin_ajax() && strpos( $action, 'wpforms_admin_entries_list_' ) === 0;
	}

	/**
	 * Initialize class.
	 *
	 * @since 1.8.6
	 */
	public function init() {

		if ( ! $this->allow_load() ) {
			return;
		}

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.8.6
	 */
	private function hooks() {

		add_action( 'wp_ajax_wpforms_admin_entries_list_save_columns_order', [ $this, 'save_order' ] );
	}

	/**
	 * Save columns' order.
	 *
	 * @since 1.8.6
	 */
	public function save_order() {

		$data = $this->get_prepared_data();

		// Prepare the new columns order.
		$columns = [];

		foreach ( $data['columns'] as $column ) {
			$column = str_replace( [ 'wpforms_field_', '-foot' ], '', $column );
			$column = $column === 'entry_id' ? Facades\Columns::COLUMN_ENTRY_ID : $column;
			$column = $column === 'notes_count' ? Facades\Columns::COLUMN_NOTES_COUNT : $column;

			// Do not store sticky columns.
			if ( in_array( $column, [ 'indicators', 'actions' ], true ) ) {
				continue;
			}

			$columns[] = $column;
		}

		// Save columns' order.
		$result = Facades\Columns::sanitize_and_save_columns( $data['form_id'], $columns );

		if ( $result === false || is_wp_error( $result ) ) {
			wp_send_json_error( __( 'Cannot save columns order.', 'wpforms' ) );
		}

		wp_send_json_success();
	}

	/**
	 * Get prepared data before perform ajax action.
	 *
	 * @since 1.8.6
	 *
	 * @return array
	 */
	private function get_prepared_data(): array {

		// Run a security check.
		if ( ! check_ajax_referer( 'wpforms-admin', 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Most likely, your session expired. Please reload the page.', 'wpforms' ) );
		}

		if ( empty( $_POST['form_id'] ) ) {
			wp_send_json_error( esc_html__( 'Form ID is missing.', 'wpforms' ) );
		}

		$form_id = absint( $_POST['form_id'] );

		// Check for permissions.
		if ( ! wpforms_current_user_can( 'view_entries_form_single', $form_id ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'wpforms' ) );
		}

		return [
			'form_id' => $form_id,
			'columns' => ! empty( $_POST['columns'] ) ? map_deep( (array) wp_unslash( $_POST['columns'] ), 'sanitize_key' ) : [],
		];
	}
}
