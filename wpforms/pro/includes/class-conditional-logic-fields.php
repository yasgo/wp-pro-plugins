<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conditional logic for fields.
 *
 * Contains functionality for using conditional logic with front-end field
 * visibility.
 *
 * This was contained in an addon until version 1.3.8 when it was rolled into
 * core.
 *
 * @since 1.3.8
 */
class WPForms_Conditional_Logic_Fields {

	/**
	 * One is the loneliest number that you'll ever do.
	 *
	 * @since 1.3.8
	 * @var WPForms_Conditional_Logic_Fields
	 */
	private static $instance;

	/**
	 * Boolean that contains if conditional logic is in use on a page.
	 *
	 * @since 1.3.8
	 * @var bool
	 */
	public $conditional_logic = false;

	/**
	 * Main Instance.
	 *
	 * @since 1.3.8
	 * @return WPForms_Conditional_Logic_Fields
	 */
	public static function instance() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WPForms_Conditional_Logic_Fields ) ) {
			self::$instance = new WPForms_Conditional_Logic_Fields;
			add_action( 'wpforms_loaded', [ self::$instance, 'init' ], 10 );
		}

		return self::$instance;
	}

	/**
	 * Initialize.
	 *
	 * @since 1.3.8
	 */
	public function init() {

		// Form builder.
		add_action( 'wpforms_field_options_after_advanced-options', [ $this, 'builder_field_conditionals' ], 10, 2 );
		// Site frontend.
		add_action( 'wpforms_frontend_js', [ $this, 'frontend_assets' ] );
		add_filter( 'wpforms_field_atts', [ $this, 'frontend_field_attributes' ], 10, 3 );
		add_action( 'wpforms_wp_footer_end', [ $this, 'frontend_conditional_rules' ] );
		// Processing.
		add_filter( 'wpforms_process_before_form_data',             [ $this, 'process_before_form_data' ], 10, 2 );
		add_filter( 'wpforms_process_initial_errors',               [ $this, 'process_initial_errors' ], 10, 2 );
		add_action( 'wpforms_process_format_after',                 [ $this, 'process_field_visibility' ],  5, 1 );
		add_filter( 'wpforms_entry_email_process',                  [ $this, 'process_notification_conditionals' ], 10, 4 );
		add_filter( 'wpforms_entry_confirmation_process',           [ $this, 'process_confirmation_conditionals' ], 10, 4 );
	}

	/****************************************************************
	 * Form builder methods, related to form builder functionality. *
	 * - builder_field_conditionals.                                *
	 ****************************************************************/

	/**
	 * Display conditional logic settings for fields inside the form builder.
	 *
	 * @since 1.3.8
	 *
	 * @param array          $field    Field data.
	 * @param \WPForms_Field $instance Field object instance.
	 */
	public function builder_field_conditionals( $field, $instance ) {

		// Certain fields don't support conditional logic.
		if ( in_array( $field['type'], [ 'entry-preview', 'hidden', 'pagebreak' ], true ) ) {
			return;
		}
		?>

		<div class="wpforms-conditional-fields wpforms-field-option-group wpforms-field-option-group-conditionals wpforms-hide"
			id="wpforms-field-option-conditionals-<?php echo (int) $field['id']; ?>">

			<a href="#" class="wpforms-field-option-group-toggle">
				<?php esc_html_e( 'Smart Logic', 'wpforms' ); ?>
			</a>

			<div class="wpforms-field-option-group-inner">
				<?php
				wpforms_conditional_logic()->builder_block(
					[
						'form'     => $instance->form_id,
						'field'    => $field,
						'instance' => $instance,
					]
				);
				?>
			</div>

		</div>
		<?php
	}

	/******************************************************************
	 * Frontend methods, related to form displaying on site frontend. *
	 * - frontend_assets                                              *
	 * - frontend_field_attributes                                    *
	 * - frontend_conditional_rules                                   *
	 ******************************************************************/

	/**
	 * Enqueue assets for the frontend.
	 *
	 * @since 1.3.8
	 */
	public function frontend_assets() {

		if ( ! $this->conditional_logic && ! wpforms()->get( 'frontend' )->assets_global() ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-builder-conditionals',
			WPFORMS_PLUGIN_URL . "assets/pro/js/wpforms-conditional-logic-fields{$min}.js",
			[ 'jquery', 'wpforms' ],
			WPFORMS_VERSION,
			true
		);
	}

	/**
	 * Filter front-end field attributes.
	 *
	 * If a field has conditional logic or is a conditional logic trigger, apply
	 * the necessary classes for proper detection.
	 *
	 * For backwards compatibility purposes, we are filtering the attributes
	 * instead of the actual properties.
	 *
	 * @since 1.3.8
	 *
	 * @param array $attributes Field attributes.
	 * @param array $field      Field data and settings.
	 * @param array $form_data  Form data and settings.
	 *
	 * @return array
	 */
	public function frontend_field_attributes( $attributes, $field, $form_data ) {

		// Skip conditional logic attributes on the entry edit admin page.
		if ( wpforms_is_admin_page( 'entries', 'edit' ) ) {
			return $attributes;
		}

		// Check to see if the field displays conditionally.
		$conditional = $this->field_is_conditional( $field );

		if ( $conditional ) {

			// Add the classes to indicate this is a conditional field.
			$attributes['field_class'][] = 'wpforms-conditional-field';
			$attributes['field_class'][] = 'wpforms-conditional-' . sanitize_html_class( $field['conditional_type'] );

			// If initial state is hidden, add inline style to prevent flash of
			// not styled content while waiting for CSS to load.
			if ( 'show' === $field['conditional_type'] ) {
				$attributes['field_style'] = 'display:none;';
			}
		}

		// Check to see if the field is a trigger for a conditional rule.
		$trigger = $this->field_is_trigger( $field, $form_data );

		if ( $trigger ) {
			// Add the class to indicate this is a conditional trigger.
			$attributes['field_class'][] = 'wpforms-conditional-trigger';
		}

		return $attributes;
	}

	/**
	 * Include conditional logic rules for form(s) if available as JSON in site
	 * footer.
	 *
	 * @since 1.3.8
	 *
	 * @param array $forms List of forms.
	 */
	public function frontend_conditional_rules( $forms ) {

		$conditionals = $this->generate_rules( $forms );

		if ( ! empty( $conditionals ) ) {
			echo "<script type='text/javascript'>\n";
			echo "/* <![CDATA[ */\n";
			echo 'var wpforms_conditional_logic = ' . wp_json_encode( $conditionals ) . "\n";
			echo "/* ]]> */\n";
			echo "</script>\n";
		}
	}

	/*****************************************
	 * Conditional logic processing methods. *
	 * - process_before_form_data            *
	 * - process_initial_errors              *
	 * - process_field_visibility            *
	 * - process_notification_conditionals   *
	 * - process_confirmation_conditionals   *
	 *****************************************/

	/**
	 * Check for fields that contains active conditional logic rules.
	 *
	 * This runs at the very beginning of form processing. We add all the IDs to
	 * all fields with active conditional logic rules to the $form_data, for
	 * quick and easy reference later on during process, since $form_data is
	 * used and passed throughout the processing work flow.
	 *
	 * @since 1.3.8
	 *
	 * @param array $form_data Form data and settings.
	 * @param array $entry     Submitted entry values.
	 *
	 * @return array
	 */
	public function process_before_form_data( $form_data, $entry ) {

		$form_data['conditional_fields'] = [];

		foreach ( $form_data['fields'] as $id => $field ) {
			if ( $this->field_is_conditional( $field ) && ! in_array( $field['type'], [ 'html', 'divider', 'content' ], true ) ) {
				$form_data['conditional_fields'][] = $id;
			}
		}

		return $form_data;
	}

	/**
	 * Remove any validation errors on fields that have active conditional logic
	 * rules running.
	 *
	 * This method returns all form errors not related to a fields with
	 * conditional logic.
	 *
	 * @since 1.3.8
	 *
	 * @param array $errors    List of errors.
	 * @param array $form_data Form data and settings.
	 *
	 * @return array
	 */
	public function process_initial_errors( $errors, $form_data ) {

		if ( empty( $form_data['conditional_fields'] ) || empty( $errors[ $form_data['id'] ] ) ) {
			return $errors;
		}

		foreach ( $errors[ $form_data['id'] ] as $field_id => $error ) {
			if ( in_array( $field_id, $form_data['conditional_fields'], true ) ) {
				unset( $errors[ $form_data['id'] ][ $field_id ] );
			}
		}

		return $errors;
	}

	/**
	 * Determine a field's visibility when a form is submitted.
	 *
	 * This method runs immediately after the fields are sanitized and formatted.
	 * We reference the fields that are known to have conditional logic rules
	 * and then calculate each field's visibility at submit. If the
	 * field is hidden at submit, remove any errors related to it since they are
	 * not relevant and then remove all values.
	 *
	 * @since 1.3.8
	 *
	 * @param array $form_data Form data and settings.
	 */
	public function process_field_visibility( $form_data ) {

		// If the form contains no fields with conditional logic no need to
		// continue processing.
		if ( empty( $form_data['conditional_fields'] ) ) {
			return;
		}

		// Loop through each field that has conditional logic rules.
		foreach ( $form_data['conditional_fields'] as $field_id ) {

			$conditionals = $this->clear_empty_rules( $form_data['fields'][ $field_id ]['conditionals'] );

			// Determine the field visibility.
			$visible = wpforms_conditional_logic()->process( wpforms()->get( 'process' )->fields, $form_data, $conditionals );

			if ( 'hide' === $form_data['fields'][ $field_id ]['conditional_type'] ) {
				$visible = ! $visible;
			}

			// Field was not visible at submit.
			if ( ! $visible ) {

				// Remove any errors associated with the field.
				if ( ! empty( wpforms()->get( 'process' )->errors[ $form_data['id'] ][ $field_id ] ) ) {
					unset( wpforms()->get( 'process' )->errors[ $form_data['id'] ][ $field_id ] );
				}

				$allowed_keys = [ 'name', 'id', 'type' ];

				$fields = ! empty( wpforms()->get( 'process' )->fields[ $field_id ] ) ? wpforms()->get( 'process' )->fields[ $field_id ] : false;

				if ( is_array( $fields ) ) {
					// Remove any values.
					foreach ( $fields as $key => $value ) {
						if ( ! in_array( $key, $allowed_keys, true ) ) {
							wpforms()->get( 'process' )->fields[ $field_id ][ $key ] = '';
						}
					}
				}
			}

			// Save the visibility state so other addons can easily access it
			// during processing if needed.
			wpforms()->get( 'process' )->fields[ $field_id ]['visible'] = $visible;
		}
	}

	/**
	 * Process conditional logic for form entry notifications.
	 *
	 * This method will be moved to a different class in the future since it's
	 * not directly related to conditional logic fields.
	 *
	 * @since 1.1.0
	 *
	 * @param bool  $process   Whether to process the logic or not.
	 * @param array $fields    List of submitted fields.
	 * @param array $form_data Form data and settings.
	 * @param int   $id        Notification ID.
	 *
	 * @return bool
	 */
	public function process_notification_conditionals( $process, $fields, $form_data, $id ) {

		$settings = $form_data['settings'];

		// Confirm conditional logic is enabled.
		if (
			empty( $settings['notifications'][ $id ]['conditional_logic'] ) ||
			empty( $settings['notifications'][ $id ]['conditional_type'] ) ||
			empty( $settings['notifications'][ $id ]['conditionals'] )
		) {
			return $process;
		}

		$conditionals = $this->clear_empty_rules( $settings['notifications'][ $id ]['conditionals'] );
		if ( empty( $conditionals ) ) {
			return $process;
		}

		$type    = $settings['notifications'][ $id ]['conditional_type'];
		$process = wpforms_conditional_logic()->process( $fields, $form_data, $conditionals );

		if ( 'stop' === $type ) {
			$process = ! $process;
		}

		// If preventing the notification, log it.
		if ( ! $process ) {
			wpforms_log(
				esc_html__( 'An Entry Notification was not sent due to conditional logic.', 'wpforms' ),
				$settings['notifications'][ $id ],
				[
					'type'    => [ 'entry', 'conditional_logic' ],
					'parent'  => wpforms()->get( 'process' )->entry_id,
					'form_id' => $form_data['id'],
				]
			);
		}

		return $process;
	}

	/**
	 * Process conditional logic for form entry confirmations.
	 *
	 * This method will be moved to a different class in the future since it's
	 * not directly related to conditional logic fields.
	 *
	 * @since 1.4.8
	 *
	 * @param bool  $process   Whether to process the logic or not.
	 * @param array $fields    List of submitted fields.
	 * @param array $form_data Form data and settings.
	 * @param int   $id        Confirmation ID.
	 *
	 * @return bool
	 */
	public function process_confirmation_conditionals( $process, $fields, $form_data, $id ) {

		$settings = isset( $form_data['settings'] ) ? $form_data['settings'] : [];

		// Confirm conditional logic is enabled.
		if (
			empty( $settings['confirmations'][ $id ]['conditional_logic'] ) ||
			empty( $settings['confirmations'][ $id ]['conditional_type'] ) ||
			empty( $settings['confirmations'][ $id ]['conditionals'] )
		) {
			return $process;
		}

		$conditionals = $this->clear_empty_rules( $settings['confirmations'][ $id ]['conditionals'] );
		if ( empty( $conditionals ) ) {
			return $process;
		}

		$type    = $settings['confirmations'][ $id ]['conditional_type'];
		$process = wpforms_conditional_logic()->process( $fields, $form_data, $conditionals );

		if ( 'stop' === $type ) {
			$process = ! $process;
		}

		// If preventing the confirmation, log it.
		if ( ! $process ) {
			wpforms_log(
				esc_html__( 'Entry Confirmation stopped by conditional logic.', 'wpforms' ),
				$settings['confirmations'][ $id ],
				[
					'type'    => [ 'entry', 'conditional_logic' ],
					'parent'  => wpforms()->get( 'process' )->entry_id,
					'form_id' => $form_data['id'],
				]
			);
		}

		return $process;
	}

	/**************************
	 * Helper methods.        *
	 * - field_is_conditional *
	 * - field_is_trigger     *
     * - field_is_visible     *
	 * - generate_rules       *
     * - clear_empty_rules    *
	 **************************/

	/**
	 * Check if a field has conditional logic rules.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field Field data.
	 *
	 * @return bool
	 */
	public function field_is_conditional( $field ) {

		// First thing, check if conditional logic is enabled for the field.
		if (
			empty( $field['conditional_logic'] ) ||
			empty( $field['conditionals'] )
		) {
			return false;
		}

		// Now confirm we have at least one valid conditional rule configured.
		foreach ( $field['conditionals'] as $group_id => $group ) {

			foreach ( $group as $rule ) {

				if ( ! isset( $rule['field'] ) || '' === trim( $rule['field'] ) || empty( $rule['operator'] ) ) {
					continue;
				}

				if (
					( in_array( $rule['operator'], [ 'e', '!e' ], true ) ) ||
					( isset( $rule['value'] ) && '' !== trim( $rule['value'] ) )
				) {
					$this->conditional_logic = true;

					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if a field is a conditional logic rule trigger.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field     Field data and settings.
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	public function field_is_trigger( $field, $form_data ) {

		$field_id = $field['id'];

		// Below we loop through form fields and see if there is a conditional
		// logic rule that is connected to this field.
		foreach ( $form_data['fields'] as $field ) {

			// First thing, check if conditional logic is enabled for the field.
			if (
				empty( $field['conditional_logic'] ) ||
				empty( $field['conditionals'] )
			) {
				continue;
			}

			foreach ( $field['conditionals'] as $group ) {

				foreach ( $group as $rule ) {

					if ( ! isset( $rule['field'] ) || '' === trim( $rule['field'] ) || empty( $rule['operator'] ) ) {
						continue;
					}

					if (
						( in_array( $rule['operator'], [ 'e', '!e' ], true ) && (int) $rule['field'] === (int) $field_id ) ||
						( isset( $rule['value'] ) && trim( $rule['value'] ) !== '' && (int) $rule['field'] === (int) $field_id )
					) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check if the field is visible under conditions of submitted entry.
	 *
	 * @since 1.6.8.1
	 *
	 * @param array $form_data Form data and settings.
	 * @param int   $field_id  Field id.
	 *
	 * @return bool
	 */
	public function field_is_visible( $form_data, $field_id ) {

		if ( ! array_key_exists( $field_id, $form_data['fields'] ) ) {
			return false;
		}

		$conditionals = $this->clear_empty_rules( $form_data['fields'][ $field_id ]['conditionals'] );

		// Determine the field visibility.
		$visible = wpforms_conditional_logic()->process( wpforms()->get( 'process' )->fields, $form_data, $conditionals );

		if ( $form_data['fields'][ $field_id ]['conditional_type'] === 'hide' ) {
			$visible = ! $visible;
		}

		return $visible;
	}

	/**
	 * Generate formatted conditional logic rules for a form or forms.
	 *
	 * @since 1.0.0
	 *
	 * @param array $forms List of forms.
	 *
	 * @return array
	 */
	public function generate_rules( $forms ) {

		// If this boolean is not true we know there is no valid conditional
		// logic rule so we can avoid processing all the fields again.
		if ( ! $this->conditional_logic ) {
			return [];
		}

		$conditionals = [];

		// Detect if an array of forms is being passed, or the form data from a
		// single form.
		if ( ! empty( $forms['fields'] ) ) {
			$forms = [ $forms ];
		}

		// Let's loop through each form on the page.
		foreach ( $forms as $form ) {

			// If for some reason it's misconfigured and their are no fields
			// then don't proceed.
			if ( empty( $form['fields'] ) ) {
				continue;
			}

			$form_id = absint( $form['id'] );

			// Now we loop through each field inside the form.
			foreach ( $form['fields'] as $field ) {

				$field_id = absint( $field['id'] );

				// First thing, check if conditional logic is enabled for the field.
				if (
					empty( $field['conditional_logic'] ) ||
					empty( $field['conditionals'] ) ||
					'1' !== $field['conditional_logic']
				) {
					continue;
				}

				$field['conditionals'] = $this->clear_empty_rules( $field['conditionals'] );
				if ( empty( $field['conditionals'] ) ) {
					continue;
				}

				foreach ( $field['conditionals'] as $group_id => $group ) {

					foreach ( $group as $rule_id => $rule ) {

						if ( ! isset( $rule['field'] ) || '' === trim( $rule['field'] ) || empty( $rule['operator'] ) ) {
							continue;
						}

						if (
							( in_array( $rule['operator'], [ 'e', '!e' ], true ) ) ||
							( isset( $rule['value'] ) && '' !== trim( $rule['value'] ) )
						) {
							// Valid conditional!
							$rule_field = $rule['field'];
							$rule_value = isset( $rule['value'] ) ? $rule['value'] : '';

							// This special value processing is only required for
							// non-text based fields that are not using empty checks.
							if (
								( ! in_array( $rule['operator'], [ 'e', '!e' ], true ) ) &&
								in_array(
									$form['fields'][ $rule_field ]['type'],
									[
										'select',
										'checkbox',
										'radio',
										'payment-multiple',
										'payment-checkbox',
										'payment-select',
									],
									true
								)
							) {

								if ( in_array( $form['fields'][ $rule_field ]['type'], [ 'payment-multiple', 'payment-checkbox', 'payment-select' ], true ) ) {

									// Payment items values are different, they are the actual IDs.
									$val = $rule['value'];

								} else {

									// For rules referring to fields with choices
									// we need to replace the choice key with the choice value.
									if ( ! empty( $form['fields'][ $rule_field ]['choices'][ $rule_value ]['value'] ) ) {
										$val = esc_attr( $form['fields'][ $rule_field ]['choices'][ $rule_value ]['value'] );
									} elseif ( isset( $form['fields'][ $rule_field ]['choices'][ $rule_value ]['label'] ) && '' !== trim( $form['fields'][ $rule_field ]['choices'][ $rule_value ]['label'] ) ) {
										$val = esc_attr( $form['fields'][ $rule_field ]['choices'][ $rule_value ]['label'] );
									} else {
										/* translators: %d - choice number. */
										$val = sprintf( esc_html__( 'Choice %d', 'wpforms' ), (string) $rule_value );
									}
								}

								$field['conditionals'][ $group_id ][ $rule_id ]['value'] = $val;
							}

							// Include the target field type for reference in the JS.
							$field['conditionals'][ $group_id ][ $rule_id ]['type'] = $form['fields'][ $rule_field ]['type'];

							$conditionals[ $form_id ][ $field_id ]['logic']  = $field['conditionals'];
							$conditionals[ $form_id ][ $field_id ]['action'] = $field['conditional_type'];

						} // End if().
					} // End foreach().
				} // End foreach().
			} // End foreach().
		} // End foreach().

		return $conditionals;
	}

	/**
	 * Clear conditionals array, remove empty rules and groups.
	 *
	 * @since 1.5.8
	 *
	 * @param array $conditionals Conditional rules.
	 *
	 * @return array Cleared conditional rules.
	 */
	public function clear_empty_rules( $conditionals ) {

		if ( empty( $conditionals ) || ! is_array( $conditionals ) ) {
			return [];
		}

		foreach ( $conditionals as $group_id => $group ) {

			if ( empty( $group ) || ! is_array( $group ) ) {
				unset( $conditionals[ $group_id ] );
				continue;
			}

			foreach ( $group as $rule_id => $rule ) {
				// "field" is the only required key we need to have to be able to process the rule.
				// "field" not selected equal ''.
				// "field" may be '0' for first field in form.
				// "operator" is preselected so it's always there.
				// "value" may be empty.
				if ( ! isset( $rule['field'] ) || '' === $rule['field'] ) {
					unset( $conditionals[ $group_id ][ $rule_id ] );
				}
			}

			if ( empty( $conditionals[ $group_id ] ) ) {
				unset( $conditionals[ $group_id ] );
			}
		}

		return $conditionals;
	}
}

/**
 * The function which returns the one WPForms_Conditional_Logic_Fields instance.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * @since 1.3.8
 *
 * @return WPForms_Conditional_Logic_Fields
 */
function wpforms_conditional_logic_fields() {

	return WPForms_Conditional_Logic_Fields::instance();
}

wpforms_conditional_logic_fields();
