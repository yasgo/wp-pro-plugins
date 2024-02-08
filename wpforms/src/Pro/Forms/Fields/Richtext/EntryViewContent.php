<?php

namespace WPForms\Pro\Forms\Fields\Richtext;

/**
 * Rich Text field iframe content (single entry page).
 *
 * @since 1.7.0
 */
class EntryViewContent {

	/**
	 * Rich Text field ID.
	 *
	 * @since 1.7.0
	 *
	 * @var int
	 */
	private $field_id;

	/**
	 * Entry ID.
	 *
	 * @since 1.7.0
	 *
	 * @var int
	 */
	private $entry_id;

	/**
	 * Entry field data.
	 *
	 * @since 1.7.0
	 *
	 * @var array
	 */
	private $entry_field;

	/**
	 * Indicate if the class is allowed to load.
	 *
	 * @since 1.7.0
	 *
	 * @return bool
	 */
	private function allow_load() {

		if ( ! wpforms_is_admin_page( 'entries', 'details' ) && ! wpforms_is_admin_page( 'entries', 'print' ) ) {
			return false;
		}

		$this->field_id = isset( $_GET['richtext_field_id'] ) ? absint( $_GET['richtext_field_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		if ( empty( $this->field_id ) ) {
			return false;
		}

		$this->entry_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		if ( empty( $this->entry_id ) ) {
			return false;
		}

		if ( ! wpforms_current_user_can( 'view_entry_single', $this->entry_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this entry.', 'wpforms' ), 403 );
		}

		return true;
	}

	/**
	 * Init.
	 *
	 * @since 1.7.0
	 */
	public function init() {

		// Only proceed if allowed.
		if ( ! $this->allow_load() ) {
			return;
		}

		// Find the entry.
		$entry = wpforms()->get( 'entry' )->get( $this->entry_id );

		// Find the form information.
		$form_data = wpforms()->get( 'form' )->get(
			$entry->form_id,
			[
				'cap'          => 'view_entries_form_single',
				'content_only' => true,
			]
		);

		$entry_fields      = apply_filters( 'wpforms_entry_single_data', wpforms_decode( $entry->fields ), $entry, $form_data );
		$this->entry_field = ! empty( $entry_fields[ $this->field_id ] ) ? $entry_fields[ $this->field_id ] : false;

		if ( empty( $this->entry_field['value'] ) ) {
			return;
		}

		// Finally, display content.
		$this->display_content();
	}

	/**
	 * Display Rich Text field content.
	 *
	 * @since 1.7.0
	 */
	private function display_content() {

		echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'fields/richtext-single-iframe',
			[
				'content' => $this->entry_field['value'],
			],
			true
		);

		exit;
	}
}
