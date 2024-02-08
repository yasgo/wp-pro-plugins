<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Entry preview field.
 *
 * @since 1.6.9
 */
class WPForms_Entry_Preview extends WPForms_Field {

	/**
	 * Init.
	 *
	 * @since 1.6.9
	 */
	public function init() {

		// Define field type information.
		$this->name     = esc_html__( 'Entry Preview', 'wpforms' );
		$this->keywords = esc_html__( 'confirm', 'wpforms' );
		$this->type     = 'entry-preview';
		$this->icon     = 'fa-file-text-o';
		$this->order    = 190;
		$this->group    = 'fancy';

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.6.9
	 */
	private function hooks() {

		add_filter( 'wpforms_builder_strings', [ $this, 'add_builder_strings' ], 10, 2 );
		add_action( 'wpforms_frontend_css', [ $this, 'enqueue_styles' ] );
		add_action( 'wpforms_frontend_js', [ $this, 'enqueue_scripts' ] );
		add_action( 'wpforms_frontend_confirmation', [ $this, 'enqueue_styles' ] );
		add_action( 'wpforms_frontend_confirmation', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_wpforms_get_entry_preview', [ $this, 'ajax_get_entry_preview' ] );
		add_action( 'wp_ajax_nopriv_wpforms_get_entry_preview', [ $this, 'ajax_get_entry_preview' ] );
		add_action( 'wpforms_form_settings_confirmations_single_after', [ $this, 'add_confirmation_fields' ], 10, 2 );
		add_action( 'wpforms_frontend_confirmation_message_after', [ $this, 'entry_preview_confirmation' ], 10, 4 );
		add_filter( 'wpforms_frontend_form_data', [ $this, 'ignore_fields' ] );
		add_filter( "wpforms_pro_admin_entries_edit_is_field_displayable_{$this->type}", '__return_false' );
	}

	/**
	 * Enqueue styles.
	 *
	 * @since 1.6.9
	 *
	 * @param array $forms Forms on the page.
	 */
	public function enqueue_styles( $forms ) {

		if ( (int) wpforms_setting( 'disable-css', '1' ) === 3 ) {
			return;
		}

		$forms = ! empty( $forms ) && is_array( $forms ) ? $forms : [];

		if ( ! $this->is_page_has_entry_preview( $forms ) ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-entry-preview',
			WPFORMS_PLUGIN_URL . "assets/pro/css/fields/entry-preview{$min}.css",
			[],
			WPFORMS_VERSION
		);

	}

	/**
	 * Enqueue scripts.
	 *
	 * @since 1.7.0
	 *
	 * @param array $forms Forms on the page.
	 */
	public function enqueue_scripts( $forms ) {

		$forms = ! empty( $forms ) && is_array( $forms ) ? $forms : [];

		if ( ! $this->is_page_has_entry_preview( $forms ) ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-entry-preview',
			WPFORMS_PLUGIN_URL . "assets/pro/js/fields/entry-preview{$min}.js",
			[ 'jquery' ],
			WPFORMS_VERSION,
			true
		);
	}

	/**
	 * The current page has entry preview confirmation or field.
	 *
	 * @since 1.6.9
	 *
	 * @param array $forms Forms on the page.
	 *
	 * @return bool
	 */
	private function is_page_has_entry_preview( $forms ) {

		if ( ! empty( wpforms()->get( 'process' )->form_data ) && $this->is_form_has_entry_preview_confirmation( wpforms()->get( 'process' )->form_data ) ) {
			return true;
		}

		foreach ( $forms as $form_data ) {
			if (
				$this->is_form_has_entry_preview_confirmation( $form_data )
				|| $this->is_form_has_entry_preview_field( $form_data )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The form has an entry preview confirmation.
	 *
	 * @since 1.6.9
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	private function is_form_has_entry_preview_confirmation( $form_data ) {

		if ( empty( $form_data['settings']['confirmations'] ) ) {
			return false;
		}

		foreach ( $form_data['settings']['confirmations'] as $confirmation ) {
			if ( ! empty( $confirmation['message_entry_preview'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The form has an entry preview field.
	 *
	 * @since 1.6.9
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	private function is_form_has_entry_preview_field( $form_data ) {

		if ( empty( $form_data['fields'] ) ) {
			return false;
		}

		foreach ( $form_data['fields'] as $field ) {
			if ( ! empty( $field['type'] ) && $field['type'] === $this->type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Ajax callback for getting entry preview.
	 *
	 * @since 1.6.9
	 */
	public function ajax_get_entry_preview() {

		$form_id = isset( $_POST['wpforms']['id'] ) ? absint( $_POST['wpforms']['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $form_id ) ) {
			wp_send_json_error();
		}

		if ( ! wpforms()->get( 'form' ) ) {
			wp_send_json_error();
		}

		$form_data = wpforms()->get( 'form' )->get( $form_id, [ 'content_only' => true ] );

		if ( ! $form_data ) {
			wp_send_json_error();
		}

		$submitted_fields         = stripslashes_deep( $_POST['wpforms'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$form_data['created']     = ! empty( $form_data['created'] ) ? $form_data['created'] : time();
		$current_entry_preview_id = ! empty( $_POST['current_entry_preview_id'] ) ? absint( $_POST['current_entry_preview_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$fields                   = $this->get_entry_preview_fields( $form_data, $submitted_fields, $current_entry_preview_id );

		if ( empty( $fields ) ) {
			wp_send_json_success();
		}

		$type = ! empty( $form_data['fields'][ $current_entry_preview_id ]['style'] ) ? $form_data['fields'][ $current_entry_preview_id ]['style'] : 'basic';

		ob_start();
		$this->print_entry_preview( $type, $fields, $form_data );
		wp_send_json_success( ob_get_clean() );
	}

	/**
	 * Get ID of the start position for search.
	 *
	 * @since 1.6.9
	 *
	 * @param array $form_data              Form data and settings.
	 * @param int   $end_with_page_break_id Last page break field ID.
	 *
	 * @return int
	 */
	private function get_start_page_break_id( $form_data, $end_with_page_break_id ) {

		$is_current_range   = false;
		$is_next_page_break = false;
		$first_field        = reset( $form_data['fields'] );
		$first_field_id     = absint( $first_field['id'] );

		/**
		 * Force showing all fields from the beginning of the form instead of
		 * the fields between current and previous Entry Preview fields.
		 *
		 * @since 1.8.1
		 *
		 * @param bool  $force_all_fields       Whether to force all fields instead of a range between current and previous Entry Preview fields.
		 * @param array $form_data              Form data and settings.
		 * @param int   $end_with_page_break_id Last Page Break field ID.
		 */
		if ( apply_filters( 'wpforms_entry_preview_get_start_page_break_id_force_first', false, $form_data, $end_with_page_break_id ) ) {
			return $first_field_id;
		}

		foreach ( array_reverse( (array) $form_data['fields'] ) as $field_properties ) {
			$field_id   = absint( $field_properties['id'] );
			$field_type = $field_properties['type'];

			if ( $end_with_page_break_id === $field_id ) {
				$is_current_range = true;

				continue;
			}

			if ( $is_current_range && $field_type === $this->type ) {
				$is_next_page_break = true;

				continue;
			}

			if ( $is_current_range && $is_next_page_break && $field_type === 'pagebreak' ) {
				return $field_id;
			}
		}

		return $first_field_id;
	}

	/**
	 * Get ID of the end position for search.
	 *
	 * @since 1.6.9
	 *
	 * @param array $form_data                Form data and settings.
	 * @param int   $current_entry_preview_id Current entry preview ID.
	 *
	 * @return int
	 */
	private function get_end_page_break_id( $form_data, $current_entry_preview_id ) {

		$is_current_page = false;

		foreach ( array_reverse( (array) $form_data['fields'] ) as $field_properties ) {
			$field_id = absint( $field_properties['id'] );

			if ( $current_entry_preview_id === $field_id ) {
				$is_current_page = true;

				continue;
			}

			if ( $is_current_page && $field_properties['type'] === 'pagebreak' ) {
				return $field_id;
			}
		}

		return 0;
	}

	/**
	 * Get fields that related to the current entry preview.
	 *
	 * @since 1.6.9
	 *
	 * @param array $form_data                Form data and settings.
	 * @param array $submitted_fields         Submitted fields.
	 * @param int   $current_entry_preview_id Current entry preview ID.
	 *
	 * @return array
	 */
	private function get_entry_preview_fields( $form_data, $submitted_fields, $current_entry_preview_id ) {

		$end_with_page_break_id             = $this->get_end_page_break_id( $form_data, $current_entry_preview_id );
		$start_with_page_break_id           = $this->get_start_page_break_id( $form_data, $end_with_page_break_id );
		$is_current_range                   = false;
		$entry_preview_fields               = [];
		wpforms()->get( 'process' )->fields = [];

		foreach ( (array) $form_data['fields'] as $field_properties ) {

			$field_id    = absint( $field_properties['id'] );
			$field_type  = $field_properties['type'];
			$field_value = isset( $submitted_fields['fields'][ $field_id ] ) ? $submitted_fields['fields'][ $field_id ] : '';

			// We should process all submitted fields for correct Conditional Logic work.
			$this->process_field( $field_value, $field_properties, $form_data );

			if ( $field_id === $end_with_page_break_id ) {
				$is_current_range = false;
			}

			if ( $is_current_range && ! empty( wpforms()->get( 'process' )->fields[ $field_id ] ) ) {
				$entry_preview_fields[ $field_id ] = wpforms()->get( 'process' )->fields[ $field_id ];
			}

			if ( $field_type === 'pagebreak' && $field_id === $start_with_page_break_id ) {
				$is_current_range = true;
			}
		}

		/** This filter is documented in wpforms/includes/class-process.php */
		return apply_filters( 'wpforms_process_filter', $entry_preview_fields, $submitted_fields, $form_data ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Process field for entry preview.
	 *
	 * @since 1.6.9
	 *
	 * @param string $field_value      Submitted field value.
	 * @param array  $field_properties Field properties.
	 * @param array  $form_data        Form data and settings.
	 */
	private function process_field( $field_value, $field_properties, $form_data ) {

		$field_id   = absint( $field_properties['id'] );
		$field_type = $field_properties['type'];

		if ( $this->is_field_support_preview( $field_value, $field_properties, $form_data ) ) {
			/**
			 * Apply things for format and sanitize, see WPForms_Field::format().
			 *
			 * @param int    $field       Field ID.
			 * @param string $field_value Submitted field value.
			 * @param array  $form_data   Form data and settings.
			 */
			do_action( "wpforms_process_format_{$field_type}", $field_id, $field_value, $form_data );

			return;
		}

		wpforms()->get( 'process' )->fields[ $field_id ] = [
			'name'  => ! empty( $form_data['fields'][ $field_id ]['label'] ) ? sanitize_text_field( $form_data['fields'][ $field_id ]['label'] ) : '',
			'value' => '',
			'id'    => $field_id,
			'type'  => $field_type,
		];
	}

	/**
	 * Remove invisible fields from the Entry Preview.
	 *
	 * @since 1.6.9
	 *
	 * @param array $entry_preview_fields List of entry preview fields.
	 * @param array $form_data            Form data and settings.
	 *
	 * @return array
	 */
	private function filter_conditional_logic( $entry_preview_fields, $form_data ) {

		foreach ( $entry_preview_fields as $field_id => $field ) {
			if (
				! empty( $form_data['fields'][ $field_id ]['conditionals'] )
				&& ! wpforms_conditional_logic_fields()->field_is_visible( $form_data, $field_id )
			) {
				unset( $entry_preview_fields[ $field_id ] );
			}
		}

		return $entry_preview_fields;
	}

	/**
	 * Show entry preview on the confirmation.
	 *
	 * @since 1.6.9
	 *
	 * @param array $confirmation Current confirmation data.
	 * @param array $form_data    Form data and settings.
	 * @param array $fields       Sanitized field data.
	 * @param int   $entry_id     Entry id.
	 */
	public function entry_preview_confirmation( $confirmation, $form_data, $fields, $entry_id ) {

		if ( empty( $confirmation['message_entry_preview'] ) ) {
			return;
		}

		$type = ! empty( $confirmation['message_entry_preview_style'] ) ? $confirmation['message_entry_preview_style'] : 'basic';

		$this->print_entry_preview( $type, $fields, $form_data );
	}

	/**
	 * Print entry preview.
	 *
	 * @since 1.6.9
	 *
	 * @param string $type      Entry preview type.
	 * @param array  $fields    Entry preview fields.
	 * @param array  $form_data Form data and settings.
	 */
	private function print_entry_preview( $type, $fields, $form_data ) {

		$fields         = $this->filter_conditional_logic( $fields, $form_data );
		$ignored_fields = self::get_ignored_fields();
		$fields_html    = '';

		foreach ( $fields as $field ) {
			if ( in_array( $field['type'], $ignored_fields, true ) ) {
				continue;
			}

			$value = $this->get_field_value( $field, $form_data );

			if ( wpforms_is_empty_string( $value ) ) {
				continue;
			}

			/**
			 * Hide the field.
			 *
			 * @since 1.7.0
			 *
			 * @param bool  $hide      Hide the field.
			 * @param array $field     Field data.
			 * @param array $form_data Form data.
			 *
			 * @return bool
			 */
			if ( (bool) apply_filters( 'wpforms_pro_fields_entry_preview_print_entry_preview_exclude_field', false, $field, $form_data ) ) {
				continue;
			}

			$fields_html .= sprintf(
				'<div class="wpforms-entry-preview-label">%s</div>
				<div class="wpforms-entry-preview-value">%s</div>',
				esc_html( $this->get_field_label( $field, $form_data ) ),
				wp_kses_post( $value )
			);
		}

		if ( empty( $fields_html ) ) {
			return;
		}

		printf(
			'<div class="wpforms-entry-preview wpforms-entry-preview-%s">%s</div>',
			esc_attr( $type ),
			wp_kses_post( $fields_html )
		);
	}

	/**
	 * Get list of ignored fields for the entry preview field.
	 *
	 * @since 1.6.9
	 *
	 * @return array
	 */
	private static function get_ignored_fields() {

		// TODO: 'captcha' should be dropped from the list when the WPForms Captcha 1.3.2/1.4 is released.
		$ignored_fields = [ 'hidden', 'captcha' ];

		/**
		 * List of ignored fields for the entry preview field.
		 *
		 * @since 1.6.9
		 *
		 * @param array $fields List of ignored fields.
		 *
		 * @return array
		 */
		return (array) apply_filters( 'wpforms_pro_fields_entry_preview_get_ignored_fields', $ignored_fields );
	}

	/**
	 * Get field label.
	 *
	 * @since 1.6.9
	 *
	 * @param array $field     Field data.
	 * @param array $form_data Form data and settings.
	 *
	 * @return string
	 */
	private function get_field_label( $field, $form_data ) {

		$label = ! empty( $field['name'] )
			? wp_strip_all_tags( $field['name'] )
			: sprintf( /* translators: %d - field ID. */
				esc_html__( 'Field ID #%d', 'wpforms' ),
				absint( $field['id'] )
			);

		/**
		 * Modify the field label inside the entry preview field.
		 *
		 * @since 1.6.9
		 *
		 * @param string $label     Label.
		 * @param array  $field     Field data.
		 * @param array  $form_data Form data.
		 *
		 * @return string
		 */
		return (string) apply_filters( 'wpforms_pro_fields_entry_preview_get_field_label', $label, $field, $form_data );
	}

	/**
	 * Get field value.
	 *
	 * @since 1.6.9
	 *
	 * @param array $field     Field data.
	 * @param array $form_data Form data and settings.
	 *
	 * @return string
	 */
	private function get_field_value( $field, $form_data ) {

		$value = isset( $field['value'] ) ? $field['value'] : '';
		$type  = $field['type'];

		/** This filter is documented in src/SmartTags/SmartTag/FieldHtmlId.php. */
		$value = (string) apply_filters( 'wpforms_html_field_value', wp_strip_all_tags( $value ), $field, $form_data, 'entry-preview' );

		/**
		 * The field value inside for exact field type the entry preview field.
		 *
		 * @since 1.6.9
		 *
		 * @param string $value     Value.
		 * @param array  $field     Field data.
		 * @param array  $form_data Form data.
		 *
		 * @return string
		 */
		$value = (string) apply_filters( "wpforms_pro_fields_entry_preview_get_field_value_{$type}_field", $value, $field, $form_data );

		/**
		 * The field value inside the entry preview field.
		 *
		 * @since 1.6.9
		 *
		 * @param string $value     Value.
		 * @param array  $field     Field data.
		 * @param array  $form_data Form data.
		 *
		 * @return string
		 */
		$value = (string) apply_filters( 'wpforms_pro_fields_entry_preview_get_field_value', $value, $field, $form_data );

		if ( ! $this->is_field_support_preview( $value, $field, $form_data ) ) {
			/**
			 * Show fields that do not have available preview.
			 *
			 * @since 1.7.0
			 *
			 * @param bool  $show      Show the field.
			 * @param array $field     Field data.
			 * @param array $form_data Form data.
			 *
			 * @return bool
			 */
			$show = (bool) apply_filters( 'wpforms_pro_fields_entry_preview_get_field_value_show_preview_not_available', true, $field, $form_data );

			return $show ? sprintf( '<em>%s</em>', esc_html__( 'Preview not available', 'wpforms' ) ) : '';
		}

		if ( wpforms_is_empty_string( $value ) ) {
			/**
			 * Show fields with the empty value.
			 *
			 * @since 1.7.0
			 *
			 * @param bool  $show      Show the field.
			 * @param array $field     Field data.
			 * @param array $form_data Form data.
			 *
			 * @return bool
			 */
			$show = (bool) apply_filters( 'wpforms_pro_fields_entry_preview_get_field_value_show_empty', true, $field, $form_data );

			return $show ? sprintf( '<em>%s</em>', esc_html__( 'Empty', 'wpforms' ) ) : '';
		}

		/**
		 * The field value inside the entry preview for exact field type after all checks.
		 *
		 * @since 1.7.0
		 *
		 * @param string $value     Value.
		 * @param array  $field     Field data.
		 * @param array  $form_data Form data.
		 *
		 * @return string
		 */
		return (string) apply_filters( "wpforms_pro_fields_entry_preview_get_field_value_{$type}_field_after", $value, $field, $form_data );
	}

	/**
	 * Determine whether the field is available to show inside the entry preview field.
	 *
	 * @since 1.6.9
	 *
	 * @param string $value     Value.
	 * @param array  $field     Processed field data.
	 * @param array  $form_data Form data.
	 *
	 * @return bool
	 */
	private function is_field_support_preview( $value, $field, $form_data ) {

		$field_type   = $field['type'];
		$is_supported = true;

		// Compatibility with Authorize.Net and Stripe addons.
		if ( wpforms_is_empty_string( $value ) && in_array( $field_type, [ 'stripe-credit-card', 'authorize_net' ], true ) ) {
			return false;
		}

		/**
		 * The field availability inside the entry preview field.
		 *
		 * @since 1.6.9
		 *
		 * @param bool   $is_supported The field availability.
		 * @param string $value        Value.
		 * @param array  $field        Field data.
		 * @param array  $form_data    Form data.
		 *
		 * @return bool
		 */
		$is_supported = (bool) apply_filters( "wpforms_pro_fields_entry_preview_is_field_support_preview_{$field_type}_field", $is_supported, $value, $field, $form_data );

		/**
		 * Fields availability inside the entry preview field.
		 * Actually, it can control availabilities for all field types.
		 *
		 * @since 1.6.9
		 *
		 * @param bool   $is_supported Fields availability.
		 * @param string $value        Value.
		 * @param array  $field        Field data.
		 * @param array  $form_data    Form data.
		 *
		 * @return bool
		 */
		return (bool) apply_filters( 'wpforms_pro_fields_entry_preview_is_field_support_preview', $is_supported, $value, $field, $form_data );
	}

	/**
	 * Field options panel inside the builder.
	 *
	 * @since 1.6.9
	 *
	 * @param array $field Field data.
	 */
	public function field_options( $field ) {

		$this->field_option( 'basic-options', $field, [ 'markup' => 'open' ] );

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'description',
				'content' => sprintf(
					'<p class="note">%s</p>',
					esc_html__( 'Entry Preview must be displayed on its own page, without other fields. HTML fields are allowed.', 'wpforms' )
				),
			]
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'preview-notice-enable',
				'content' => $this->field_element(
					'toggle',
					$field,
					[
						'slug'    => 'preview-notice-enable',
						// When we add the field to a form it enabled by default.
						'value'   => ! empty( $field['preview-notice-enable'] ) || wp_doing_ajax(),
						'desc'    => esc_html__( 'Display Preview Notice', 'wpforms' ),
						'tooltip' => esc_html__( 'Check this option to show a message above the entry preview.', 'wpforms' ),
					],
					false
				),
			]
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'preview-notice',
				'content' =>
					$this->field_element(
						'label',
						$field,
						[
							'slug'    => 'preview-notice',
							'value'   => esc_html__( 'Preview Notice', 'wpforms' ),
							'tooltip' => esc_html__( 'Fill in the message to show above the entry preview.', 'wpforms' ),
						],
						false
					) .
					$this->field_element(
						'textarea',
						$field,
						[
							'slug'  => 'preview-notice',
							'value' => isset( $field['preview-notice'] ) ?
								$field['preview-notice'] :
								self::get_default_notice(),
						],
						false
					),
			]
		);

		$this->field_option( 'basic-options', $field, [ 'markup' => 'close' ] );

		$this->field_option( 'advanced-options', $field, [ 'markup' => 'open' ] );

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'style',
				'content' =>
					$this->field_element(
						'label',
						$field,
						[
							'slug'    => 'style',
							'value'   => esc_html__( 'Style', 'wpforms' ),
							'tooltip' => esc_html__( 'Choose the entry preview display style.', 'wpforms' ),
						],
						false
					) .
					$this->field_element(
						'select',
						$field,
						[
							'slug'    => 'style',
							'value'   => ! empty( $field['style'] ) ? $field['style'] : 'basic',
							'options' => self::get_styles(),
						],
						false
					),
			]
		);

		$this->field_option( 'css', $field );

		$this->field_option( 'advanced-options', $field, [ 'markup' => 'close' ] );
	}

	/**
	 * Get default notice.
	 *
	 * @since 1.6.9
	 *
	 * @return string
	 */
	private static function get_default_notice() {

		return sprintf(
			"<strong>%s</strong>\n%s",
			esc_html__( 'This is a preview of your submission. It has not been submitted yet!', 'wpforms' ),
			esc_html__( 'Please take a moment to verify your information. You can also go back to make changes.', 'wpforms' )
		);
	}

	/**
	 * Get list of available styles.
	 *
	 * @since 1.6.9
	 *
	 * @return array
	 */
	private static function get_styles() {

		return [
			'basic'         => esc_html__( 'Basic', 'wpforms' ),
			'compact'       => esc_html__( 'Compact', 'wpforms' ),
			'table'         => esc_html__( 'Table', 'wpforms' ),
			'table_compact' => esc_html__( 'Table, Compact', 'wpforms' ),
		];
	}

	/**
	 * Whether the current field can be populated dynamically.
	 *
	 * @since 1.6.9
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Current field specific data.
	 *
	 * @return bool
	 */
	public function is_dynamic_population_allowed( $properties, $field ) {

		return false;
	}

	/**
	 * Whether the current field can be populated using a fallback.
	 *
	 * @since 1.6.9
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Current field specific data.
	 *
	 * @return bool
	 */
	public function is_fallback_population_allowed( $properties, $field ) {

		return false;
	}

	/**
	 * Format field.
	 *
	 * @since 1.6.9
	 *
	 * @param int    $field_id     Field ID.
	 * @param string $field_submit Submitted field value.
	 * @param array  $form_data    Form data and settings.
	 */
	public function format( $field_id, $field_submit, $form_data ) {
	}

	/**
	 * Create the field preview.
	 *
	 * @since 1.6.9
	 *
	 * @param array $field Field data and settings.
	 */
	public function field_preview( $field ) {

		printf(
			'<label class="label-title">
			<span class="text">%s</span></label>',
			esc_html__( 'Entry Preview', 'wpforms' )
		);

		$is_new_field = wp_doing_ajax();
		$notice       = ! empty( $field['preview-notice-enable'] ) && isset( $field['preview-notice'] ) && ! wpforms_is_empty_string( $field['preview-notice'] )
			? force_balance_tags( $field['preview-notice'] ) : '';
		$notice       = $is_new_field || wpforms_is_empty_string( $notice ) ? self::get_default_notice() : $notice;
		$is_disabled  = $is_new_field || ! empty( $field['preview-notice-enable'] );

		printf(
			'<div class="wpforms-entry-preview-notice nl2br"%2$s>%1$s</div>',
			wp_kses_post( nl2br( $notice ) ),
			! $is_disabled ? ' style="display: none"' : ''
		);

		printf(
			'<div class="wpforms-alert wpforms-alert-info"%2$s>
				<p>%1$s</p>
			</div>',
			esc_html__( 'Entry preview will be displayed here and will contain all fields found on the previous page.', 'wpforms' ),
			$is_disabled ? ' style="display: none"' : ''
		);
	}

	/**
	 * Display the field input elements on the frontend.
	 *
	 * @since 1.6.9
	 *
	 * @param array $field      Field data and settings.
	 * @param array $field_atts Field attributes.
	 * @param array $form_data  Form data and settings.
	 */
	public function field_display( $field, $field_atts, $form_data ) {

		echo '<div class="wpforms-entry-preview-updating-message">' . esc_html__( 'Updating preview…', 'wpforms' ) . '</div>';

		if ( ! empty( $field['preview-notice-enable'] ) ) {
			$notice = ! empty( $field['preview-notice'] ) ? $field['preview-notice'] : self::get_default_notice();

			printf(
				'<div class="wpforms-entry-preview-notice" style="display: none;">%1$s</div>',
				wp_kses_post( nl2br( $notice ) )
			);
		}

		echo '<div class="wpforms-entry-preview-wrapper" style="display: none;"></div>';
	}

	/**
	 * Add a custom JS i18n strings for the builder.
	 *
	 * @since 1.6.9
	 *
	 * @param array $strings List of strings.
	 * @param array $form    Current form.
	 *
	 * @return array
	 */
	public function add_builder_strings( $strings, $form ) {

		$strings['entry_preview_require_page_break']      = esc_html__( 'Page breaks are required for entry previews to work. If you\'d like to remove page breaks, you\'ll have to first remove the entry preview field.', 'wpforms' );
		$strings['entry_preview_default_notice']          = self::get_default_notice();
		$strings['entry_preview_require_previous_button'] = esc_html__( 'You can\'t hide the previous button because it is required for the entry preview field on this page.', 'wpforms' );

		return $strings;
	}

	/**
	 * Add fields to the confirmation settings.
	 *
	 * @since 1.6.9
	 *
	 * @param WPForms_Builder_Panel_Settings $settings Settings.
	 * @param int                            $field_id Field ID.
	 */
	public function add_confirmation_fields( $settings, $field_id ) {

		wpforms_panel_field(
			'toggle',
			'confirmations',
			'message_entry_preview',
			$settings->form_data,
			esc_html__( 'Show entry preview after confirmation message', 'wpforms' ),
			[
				'input_id'    => 'wpforms-panel-field-confirmations-message_entry_preview-' . $field_id,
				'input_class' => 'wpforms-panel-field-confirmations-message_entry_preview',
				'parent'      => 'settings',
				'subsection'  => $field_id,
			]
		);

		wpforms_panel_field(
			'select',
			'confirmations',
			'message_entry_preview_style',
			$settings->form_data,
			esc_html__( 'Preview Style', 'wpforms' ),
			[
				'input_id'    => 'wpforms-panel-field-confirmations-message_entry_preview_style-' . $field_id,
				'input_class' => 'wpforms-panel-field-confirmations-message_entry_preview_style',
				'parent'      => 'settings',
				'subsection'  => $field_id,
				'default'     => 'basic',
				'options'     => self::get_styles(),
			]
		);
	}

	/**
	 * Ignore entry preview fields for some forms.
	 *
	 * @since 1.6.9
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return array
	 */
	public function ignore_fields( $form_data ) {

		if ( ! $this->is_fields_ignored( $form_data ) ) {
			return $form_data;
		}

		if ( empty( $form_data['fields'] ) ) {
			return $form_data;
		}

		foreach ( $form_data['fields'] as $key => $field ) {
			if ( $field['type'] === $this->type ) {
				unset( $form_data['fields'][ $key ] );
			}
		}

		return $form_data;
	}

	/**
	 * Allow ignoring entry preview fields for some forms.
	 *
	 * @since 1.6.9
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	private function is_fields_ignored( $form_data ) {

		$is_ignore = false;

		/**
		 * Allow ignoring entry preview fields for some forms.
		 *
		 * @since 1.6.9
		 *
		 * @param bool  $is_ignore Ignore the entry preview fields.
		 * @param array $form_data Form data and settings.
		 *
		 * @return bool
		 */
		return (bool) apply_filters( 'wpforms_pro_fields_entry_preview_is_fields_ignored', $is_ignore, $form_data );
	}
}

new WPForms_Entry_Preview();
