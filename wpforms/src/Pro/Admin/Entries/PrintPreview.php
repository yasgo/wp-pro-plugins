<?php

namespace WPForms\Pro\Admin\Entries;

/**
 * Print view for single form entries.
 *
 * @since 1.5.1
 */
class PrintPreview {

	/**
	 * Entry object.
	 *
	 * @since 1.5.1
	 *
	 * @var object
	 */
	public $entry;

	/**
	 * Form data.
	 *
	 * @since 1.5.1
	 *
	 * @var array
	 */
	public $form_data;

	/**
	 * The array of bulk entries.
	 *
	 * @since 1.8.2
	 *
	 * @var array
	 */
	private $entries;

	/**
	 * Constructor.
	 *
	 * @since 1.5.1
	 */
	public function __construct() {

		if ( ! $this->is_print_page() ) {
			return;
		}

		if ( ! $this->is_valid_request() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpforms-entries' ) );
			exit;
		}

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.5.1
	 */
	public function hooks() {

		/**
		 * Allow adding entry properties on the print page.
		 *
		 * @since 1.8.1
		 *
		 * @param object $entry     Entry object.
		 * @param array  $form_data Form data and settings.
		 */
		do_action( 'wpforms_pro_admin_entries_print_preview_entry', $this->entry, $this->form_data );

		add_action( 'admin_init', [ $this, 'print_html' ], 1 );
		add_filter( 'wpforms_entry_single_data', [ $this, 'add_hidden_data' ], 1010, 3 );
	}

	/**
	 * Check if current page request meets requirements for the Entry Print page.
	 *
	 * @since 1.5.1
	 *
	 * @return bool
	 */
	public function is_print_page() {

		// Only proceed for the form builder.
		return wpforms_is_admin_page( 'entries', 'print' );
	}

	/**
	 * Is the request valid?
	 *
	 * @since 1.7.1
	 *
	 * @return bool
	 */
	private function is_valid_request() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// Check that entry ID was passed.
		if ( empty( $_GET['entry_id'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$entry_ids = array_map( 'absint', explode( ',', wp_unslash( $_GET['entry_id'] ) ) );

		foreach ( $entry_ids as $entry_id ) {
			$_entry = wpforms()->get( 'entry' )->get( $entry_id );

			// Filter entries to ensure the current user can access their details.
			if ( ! is_object( $_entry ) ) {
				continue;
			}

			$_entry->entry_notes = wpforms()->get( 'entry_meta' )->get_meta(
				[
					'type'     => 'note',
					'entry_id' => $entry_id,
				]
			);
			$this->entries[]     = $_entry; // Assign data extracted from each entry.
		}

		// Bail early, in case we have no entry to print.
		if ( empty( $this->entries ) ) {
			return false;
		}

		// Continue store a first entry for backward compatibility.
		$this->entry = $this->entries[0];

		// Fetch the current form data with "content_only" flag.
		// Note that form-id and data will be the same across all entries.
		$this->form_data = wpforms()->get( 'form' )->get(
			$this->entry->form_id,
			[
				'content_only' => true,
			]
		);

		// Bail early, in case form-data is not valid or can not be processed.
		if ( empty( $this->form_data ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Output HTML markup for the print preview page.
	 *
	 * @since 1.5.1
	 * @since 1.8.1 Rewrite to templates.
	 */
	public function print_html() {

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wpforms_render(
			'admin/entry-print/head',
			[
				'entry'     => $this->entry,
				'form_data' => $this->form_data,
			],
			true
		);

		$last_entry_index = count( $this->entries ) - 1;

		// Loop through all entries.
		foreach ( $this->entries as $entry_index => $entry ) {
			$this->entry        = $entry;
			$is_first_iteration = $entry_index === 0; // Is this the first iteration?
			$is_last_iteration  = $entry_index === $last_entry_index; // Is this the last iteration?

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo wpforms_render(
				'admin/entry-print/legend-start',
				[
					'has_header' => $is_first_iteration,
					'entry'      => $this->entry,
					'form_data'  => $this->form_data,
				],
				true
			);

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo wpforms_render(
				'admin/entry-print/fields',
				[
					'entry'     => $this->entry,
					'form_data' => $this->form_data,
					'fields'    => $this->get_fields(),
				],
				true
			);

			// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
			/**
			 * Fires on entry print page before after all fields.
			 *
			 * @param object $entry     Entry.
			 * @param array  $form_data Form data and settings.
			 *
			 * @since 1.5.4.2
			 */
			do_action( 'wpforms_pro_admin_entries_printpreview_print_html_fields_after', $this->entry, $this->form_data );
			// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo wpforms_render(
				'admin/entry-print/notes',
				[
					'entry'     => $this->entry,
					'form_data' => $this->form_data,
				],
				true
			);

			// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
			/**
			 * Fires on entry print page before after notes.
			 *
			 * @param object $entry     Entry.
			 * @param array  $form_data Form data and settings.
			 *
			 * @since 1.5.4.2
			 */
			do_action( 'wpforms_pro_admin_entries_printpreview_print_html_notes_after', $this->entry, $this->form_data );
			// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo wpforms_render(
				'admin/entry-print/legend-end',
				[
					'has_page_break' => ! $is_last_iteration,
					'entry'          => $this->entry,
					'form_data'      => $this->form_data,
				],
				true
			);
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wpforms_render(
			'admin/entry-print/footer',
			[
				'entry'     => $this->entry,
				'form_data' => $this->form_data,
			],
			true
		);
		exit;
	}

	/**
	 * Get list of fields for the print page.
	 *
	 * @since 1.8.1
	 *
	 * @return array
	 */
	private function get_fields() {

		// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
		/**
		 * Modify entry fields data.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $fields    Entry fields.
		 * @param object $entry     Entry data.
		 * @param array  $form_data Form data and settings.
		 */
		$fields = (array) apply_filters( 'wpforms_entry_single_data', wpforms_decode( $this->entry->fields ), $this->entry, $this->form_data );
		// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

		foreach ( $fields as $key => $field ) {

			$is_field_allowed = $this->is_field_allowed( $field );

			if ( ! $is_field_allowed ) {
				unset( $fields[ $key ] );
				continue;
			}

			if ( ! isset( $field['id'], $field['type'] ) ) {
				unset( $fields[ $key ] );
				continue;
			}

			if ( $field['type'] !== 'layout' ) {
				$fields[ $key ] = $this->add_formatted_data( $field );

				continue;
			}

			if ( empty( $field['columns'] ) ) {
				unset( $fields[ $key ] );
			}
		}

		/**
		 * Modify entry fields data for the print page.
		 *
		 * @since 1.8.1.2
		 *
		 * @param array $fields Entry fields.
		 */
		return apply_filters( 'wpforms_pro_admin_entries_print_preview_fields', $fields );
	}

	/**
	 * Add formatted data to the field.
	 *
	 * @since 1.8.1
	 *
	 * @param array $field Entry field.
	 *
	 * @return array
	 */
	private function add_formatted_data( $field ) {

		$field['formatted_value'] = $this->get_formatted_field_value( $field );
		$field['formatted_label'] = $this->get_formatted_field_label( $field );

		return $field;
	}

	/**
	 * Get formatted field value.
	 *
	 * @since 1.8.1
	 *
	 * @param array $field Entry field.
	 *
	 * @return string
	 */
	private function get_formatted_field_value( $field ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
		/** This filter is documented in src/SmartTags/SmartTag/FieldHtmlId.php.*/
		$field_value = isset( $field['value'] ) ? apply_filters( 'wpforms_html_field_value', wp_strip_all_tags( $field['value'] ), $field, $this->form_data, 'entry-single' ) : '';
		// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

		if ( $field['type'] === 'html' ) {
			$field_value = isset( $field['code'] ) ? $field['code'] : '';
		}

		if ( $field['type'] === 'content' ) {
			$field_value = isset( $field['content'] ) ? $field['content'] : '';
		}

		if (
			! empty( $this->form_data['fields'][ $field['id'] ]['choices'] )
			&& in_array( $field['type'], [ 'radio', 'checkbox', 'payment-checkbox', 'payment-multiple' ], true )
		) {
			$field_value = $this->get_choices_field_value( $field, $field_value );
		}

		/**
		 * Filter print preview value.
		 *
		 * @since 1.7.9
		 *
		 * @param string $field_value Field value.
		 * @param array  $field       Field data.
		 */
		$field_value = make_clickable( apply_filters( 'wpforms_pro_admin_entries_print_preview_field_value', $field_value, $field ) );

		/**
		 * Decide if field value should use nl2br.
		 *
		 * @since 1.7.9
		 *
		 * @param bool  $use   Boolean value flagging if field should use nl2br function.
		 * @param array $field Field data.
		 */
		return apply_filters( 'wpforms_pro_admin_entries_print_preview_field_value_use_nl2br', true, $field ) ? nl2br( $field_value ) : $field_value;
	}

	/**
	 * Get formatted field value.
	 *
	 * @since 1.8.1
	 *
	 * @param array $field Entry field.
	 *
	 * @return string
	 */
	private function get_formatted_field_label( $field ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$field_label = isset( $field['name'] ) ? $field['name'] : '';

		if ( $field['type'] === 'divider' ) {
			$field_label = isset( $field['label'] ) && ! wpforms_is_empty_string( $field['label'] ) ? $field['label'] : esc_html__( 'Section Divider', 'wpforms' );
		}

		if ( $field['type'] === 'pagebreak' ) {
			$field_label = isset( $field['title'] ) && ! wpforms_is_empty_string( $field['title'] ) ? $field['title'] : esc_html__( 'Page Break', 'wpforms' );
		}

		if ( $field['type'] === 'content' ) {
			$field_label = esc_html__( 'Content Field', 'wpforms' );
		}

		return $field_label;
	}

	/**
	 * Get field value for checkbox and radio fields.
	 *
	 * @since 1.8.1
	 *
	 * @param array  $field       Entry field.
	 * @param string $field_value HTML markup for the field.
	 *
	 * @return string
	 */
	private function get_choices_field_value( $field, $field_value ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$choices_html    = '';
		$choices         = $this->form_data['fields'][ $field['id'] ]['choices'];
		$type            = in_array( $field['type'], [ 'radio', 'payment-multiple' ], true ) ? 'radio' : 'checkbox';
		$is_image_choice = ! empty( $this->form_data['fields'][ $field['id'] ]['choices_images'] );
		$template_name   = $is_image_choice ? 'image-choice' : 'choice';
		$is_dynamic      = ! empty( $field['dynamic'] );

		if ( $is_dynamic ) {
			$field_id   = $field['id'];
			$form_id    = $this->form_data['id'];
			$field_data = $this->form_data['fields'][ $field_id ];
			$choices    = wpforms_get_field_dynamic_choices( $field_data, $form_id, $this->form_data );
		}

		foreach ( $choices as $key => $choice ) {
			$is_checked = $this->is_checked_choice( $field, $choice, $key, $is_dynamic );

			if ( ! $is_dynamic ) {
				$choice['label'] = $this->get_choice_label( $field, $choice, $key );
			}

			$choices_html .= wpforms_render(
				'admin/entry-print/' . $template_name,
				[
					'entry'       => $this->entry,
					'form_data'   => $this->form_data,
					'field'       => $field,
					'choice_type' => $type,
					'is_checked'  => $is_checked,
					'choice'      => $choice,
				],
				true
			);
		}

		return sprintf(
			'<div class="field-value-default-mode">%1$s</div><div class="field-value-choices-mode">%2$s</div>',
			wpforms_is_empty_string( $field_value ) ? esc_html__( 'Empty', 'wpforms' ) : $field_value,
			wpforms_esc_unselected_choices( $choices_html )
		);
	}

	/**
	 * Get value for a choice item.
	 *
	 * @since 1.8.1.2
	 *
	 * @param array $field  Entry field.
	 * @param array $choice Choice settings.
	 * @param int   $key    Choice number.
	 *
	 * @return string
	 */
	private function get_choice_label( $field, $choice, $key ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$is_payment = strpos( $field['type'], 'payment-' ) === 0;

		if ( ! $is_payment ) {
			return ! isset( $choice['label'] ) || wpforms_is_empty_string( $choice['label'] )
				/* translators: %s - choice number. */
				? sprintf( esc_html__( 'Choice %s', 'wpforms' ), $key )
				: $choice['label'];
		}

		$label = $choice['label'] ?? '';
		/* translators: %s - item number. */
		$label = $label !== '' ? $label : sprintf( esc_html__( 'Item %s', 'wpforms' ), $key );

		if ( empty( $this->form_data['fields'][ $field['id'] ]['show_price_after_labels'] ) ) {
			return $label;
		}

		$value    = $choice['value'] ?? 0;
		$currency = $field['currency'] ?? '';
		$amount   = wpforms_format_amount( wpforms_sanitize_amount( $value, $currency ), true, $currency );

		return $amount ? $label . ' - ' . $amount : $label;
	}

	/**
	 * Is the choice item checked?
	 *
	 * @since 1.8.1.2
	 *
	 * @param array $field      Entry field.
	 * @param array $choice     Choice settings.
	 * @param int   $key        Choice number.
	 * @param bool  $is_dynamic Is dynamic field.
	 *
	 * @return bool
	 */
	private function is_checked_choice( $field, $choice, $key, $is_dynamic ) {

		$is_payment     = strpos( $field['type'], 'payment-' ) === 0;
		$separator      = $is_payment || $is_dynamic ? ',' : "\n";
		$active_choices = explode( $separator, $field['value_raw'] );

		if ( $is_dynamic ) {
			$active_choices = array_map( 'absint', $active_choices );

			return in_array( $choice['value'], $active_choices, true );
		}

		if ( $is_payment ) {
			$active_choices = array_map( 'absint', $active_choices );

			return in_array( $key, $active_choices, true );
		}

		$label = ! isset( $choice['label'] ) || wpforms_is_empty_string( $choice['label'] )
			/* translators: %s - choice number. */
			? sprintf( esc_html__( 'Choice %s', 'wpforms' ), $key )
			: sanitize_text_field( $choice['label'] );

		return in_array( $label, $active_choices, true );
	}

	/**
	 * Add HTML entries, dividers to entry.
	 *
	 * @since 1.6.7
	 *
	 * @param array  $fields    Form fields.
	 * @param object $entry     Entry fields.
	 * @param object $form_data Form data.
	 *
	 * @return array
	 */
	public function add_hidden_data( $fields, $entry, $form_data ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$settings = ! empty( $form_data['fields'] ) ? $form_data['fields'] : [];

		// Content, Divider, HTML and layout fields must always be included because it's allowed to show and hide these fields.
		$forced_allowed_fields = [ 'content', 'divider', 'html', 'layout', 'pagebreak' ];

		// First order settings field and remove fields that we dont need.
		foreach ( $settings as $key => $setting ) {

			if ( empty( $setting['type'] ) ) {
				unset( $settings[ $key ] );
				continue;
			}

			$field_type = $setting['type'];

			if ( in_array( $field_type, $forced_allowed_fields, true ) ) {
				continue;
			}

			// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
			/** This filter is documented in /src/Pro/Admin/Entries/Edit.php */
			if ( ! (bool) apply_filters( "wpforms_pro_admin_entries_edit_is_field_displayable_{$field_type}", true, $setting, $form_data ) ) {
				unset( $settings[ $key ] );
				continue;
			}
			// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

			if ( ! isset( $fields[ $key ] ) ) {
				unset( $settings[ $key ] );
				continue;
			}

			$settings[ $key ] = $fields[ $key ];
		}

		// Second, add fields that might have been removed on the form but are still tied to the entry.
		foreach ( $fields as $key => $field ) {

			if ( ! isset( $settings[ $key ] ) ) {
				$settings[ $key ] = $field;
			}
		}

		return $settings;
	}

	/**
	 * Check if field is allowed to be displayed.
	 *
	 * @since 1.8.1.2
	 * @since 1.8.6 Internal Information and Entry Preview fields are not allowed.
	 *
	 * @param array $field Field data.
	 *
	 * @return bool
	 */
	public function is_field_allowed( $field ) {

		if ( empty( $field['type'] ) ) {
			return false;
		}

		// These fields should never be displayed on the Print page.
		$ignore = [
			'internal-information',
			'entry-preview',
		];

		if ( in_array( $field['type'], $ignore, true ) ) {
			return false;
		}

		$is_dynamic = ! empty( $field['dynamic'] );

		// If field is not dynamic, it is allowed.
		if ( ! $is_dynamic ) {
			return true;
		}

		$form_data       = $this->form_data;
		$fields          = $form_data['fields'];
		$field_id        = $field['id'];
		$field_data      = $fields[ $field_id ];
		$dynamic_choices = wpforms_get_field_dynamic_choices( $field_data, $form_data['id'], $form_data );

		// If field is dynamic and has choices, it is allowed.
		return ! empty( $dynamic_choices );
	}
}
