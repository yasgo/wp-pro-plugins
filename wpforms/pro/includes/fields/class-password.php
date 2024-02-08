<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Password field.
 *
 * @since 1.0.0
 */
class WPForms_Field_Password extends WPForms_Field {

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Define field type information.
		$this->name     = esc_html__( 'Password', 'wpforms' );
		$this->keywords = esc_html__( 'user', 'wpforms' );
		$this->type     = 'password';
		$this->icon     = 'fa-lock';
		$this->order    = 130;
		$this->group    = 'fancy';

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.8.1
	 */
	private function hooks() {

		// Define additional field properties.
		add_filter( 'wpforms_field_properties_password', [ $this, 'field_properties' ], 5, 3 );

		// Set confirmation status to option wrapper class.
		add_filter( 'wpforms_builder_field_option_class', [ $this, 'field_option_class' ], 10, 2 );

		// Form frontend CSS enqueues.
		add_action( 'wpforms_frontend_css', [ $this, 'enqueue_frontend_css' ] );

		// Form frontend JS enqueues.
		add_action( 'wpforms_frontend_js', [ $this, 'enqueue_frontend_js' ] );

		// Add frontend strings.
		add_filter( 'wpforms_frontend_strings', [ $this, 'add_frontend_strings' ] );

		add_action( 'wpforms_pro_fields_entry_preview_get_field_value_password_field', [ $this, 'modify_entry_preview_value' ], 10, 3 );

		// This field requires fieldset+legend instead of the field label.
		add_filter( "wpforms_frontend_modern_is_field_requires_fieldset_{$this->type}", [ $this, 'is_field_requires_fieldset' ], PHP_INT_MAX, 2 );
	}

	/**
	 * Define additional field properties.
	 *
	 * @since 1.3.7
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Field settings.
	 * @param array $form_data  Form data and settings.
	 *
	 * @return array
	 */
	public function field_properties( $properties, $field, $form_data ) {

		// Prevent "spell-jacking" of passwords.
		$properties['inputs']['primary']['attr']['spellcheck'] = 'false';

		if ( ! empty( $field['password-strength'] ) ) {
			$properties['inputs']['primary']['data']['rule-password-strength']  = true;
			$properties['inputs']['primary']['data']['password-strength-level'] = $field['password-strength-level'];
		}

		if ( empty( $field['confirmation'] ) ) {
			return $properties;
		}

		$form_id  = absint( $form_data['id'] );
		$field_id = absint( $field['id'] );

		// Password confirmation setting enabled.
		$props      = [
			'inputs' => [
				'primary'   => [
					'block'    => [
						'wpforms-field-row-block',
						'wpforms-one-half',
						'wpforms-first',
					],
					'class'    => [
						'wpforms-field-password-primary',
					],
					'sublabel' => [
						'hidden' => ! empty( $field['sublabel_hide'] ),
						'value'  => esc_html__( 'Password', 'wpforms' ),
					],
				],
				'secondary' => [
					'attr'     => [
						'name'        => "wpforms[fields][{$field_id}][secondary]",
						'value'       => '',
						'placeholder' => ! empty( $field['confirmation_placeholder'] ) ? $field['confirmation_placeholder'] : '',
						'spellcheck'  => 'false',
					],
					'block'    => [
						'wpforms-field-row-block',
						'wpforms-one-half',
					],
					'class'    => [
						'wpforms-field-password-secondary',
					],
					'data'     => [
						'rule-confirm' => '#' . $properties['inputs']['primary']['id'],
					],
					'id'       => "wpforms-{$form_id}-field_{$field_id}-secondary",
					'required' => ! empty( $field['required'] ) ? 'required' : '',
					'sublabel' => [
						'hidden' => ! empty( $field['sublabel_hide'] ),
						'value'  => esc_html__( 'Confirm Password', 'wpforms' ),
					],
					'value'    => '',
				],
			],
		];
		$properties = array_merge_recursive( $properties, $props );

		// Input Primary: adjust name.
		$properties['inputs']['primary']['attr']['name'] = "wpforms[fields][{$field_id}][primary]";

		// Input Primary: remove size and error classes.
		$properties['inputs']['primary']['class'] = array_diff(
			$properties['inputs']['primary']['class'],
			[
				'wpforms-field-' . sanitize_html_class( $field['size'] ),
				'wpforms-error',
			]
		);

		// Input Primary: add error class if needed.
		if ( ! empty( $properties['error']['value']['primary'] ) ) {
			$properties['inputs']['primary']['class'][] = 'wpforms-error';
		}

		// Input Secondary: add error class if needed.
		if ( ! empty( $properties['error']['value']['secondary'] ) ) {
			$properties['inputs']['secondary']['class'][] = 'wpforms-error';
		}

		// Input Secondary: add required class if needed.
		if ( ! empty( $field['required'] ) ) {
			$properties['inputs']['secondary']['class'][] = 'wpforms-field-required';
		}

		return $properties;
	}

	/**
	 * @inheritdoc
	 */
	public function is_dynamic_population_allowed( $properties, $field ) {

		return false;
	}

	/**
	 * @inheritdoc
	 */
	public function is_fallback_population_allowed( $properties, $field ) {

		return false;
	}

	/**
	 * Add class to field options wrapper to indicate if field confirmation is enabled.
	 *
	 * @since 1.3.0
	 *
	 * @param string $class
	 * @param array  $field
	 *
	 * @return string
	 */
	public function field_option_class( $class, $field ) {

		if ( 'password' === $field['type'] ) {
			if ( isset( $field['confirmation'] ) ) {
				$class = 'wpforms-confirm-enabled';
			} else {
				$class = 'wpforms-confirm-disabled';
			}
		}

		return $class;
	}

	/**
	 * Field options panel inside the builder.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field
	 */
	public function field_options( $field ) {
		/*
		 * Basic field options.
		 */

		// Options open markup.
		$args = [
			'markup' => 'open',
		];

		$this->field_option( 'basic-options', $field, $args );

		// Label.
		$this->field_option( 'label', $field );

		// Description.
		$this->field_option( 'description', $field );

		// Required toggle.
		$this->field_option( 'required', $field );

		// Confirmation toggle.
		$fld  = $this->field_element(
			'toggle',
			$field,
			[
				'slug'    => 'confirmation',
				'value'   => isset( $field['confirmation'] ) ? '1' : '0',
				'desc'    => esc_html__( 'Enable Password Confirmation', 'wpforms' ),
				'tooltip' => esc_html__( 'Check this option to ask users to provide their password twice.', 'wpforms' ),
			],
			false
		);
		$args = [
			'slug'    => 'confirmation',
			'content' => $fld,
		];

		$this->field_element( 'row', $field, $args );

		// Password strength.
		$meter = $this->field_element(
			'toggle',
			$field,
			[
				'slug'    => 'password-strength',
				'value'   => isset( $field['password-strength'] ) ? '1' : '0',
				'desc'    => esc_html__( 'Enable Password Strength', 'wpforms' ),
				'tooltip' => esc_html__( 'Check this option to set minimum password strength.', 'wpforms' ),
			],
			false
		);
		$args  = [
			'slug'    => 'password-strength',
			'content' => $meter,
		];

		$this->field_element( 'row', $field, $args );

		$strength_label = $this->field_element(
			'label',
			$field,
			[
				'value'   => esc_html__( 'Minimum Strength', 'wpforms' ),
				'tooltip' => esc_html__( 'Select minimum password strength level.', 'wpforms' ),
			],
			false
		);

		$strength = $this->field_element(
			'select',
			$field,
			[
				'slug'    => 'password-strength-level',
				'options' => [
					'2' => esc_html__( 'Weak', 'wpforms' ),
					'3' => esc_html__( 'Medium', 'wpforms' ),
					'4' => esc_html__( 'Strong', 'wpforms' ),
				],
				'value'   => isset( $field['password-strength-level'] ) ? $field['password-strength-level'] : '3',

			],
			false
		);
		$args = [
			'slug'    => 'password-strength-level',
			'class'   => ! isset( $field['password-strength'] ) ? 'wpforms-hidden' : '',
			'content' => $strength_label . $strength,
		];

		$this->field_element( 'row', $field, $args );

		// Options close markup.
		$args = [
			'markup' => 'close',
		];

		$this->field_option( 'basic-options', $field, $args );

		/*
		 * Advanced field options.
		 */

		// Options open markup.
		$args = [
			'markup' => 'open',
		];

		$this->field_option( 'advanced-options', $field, $args );

		// Size.
		$this->field_option( 'size', $field );

		// Placeholder.
		$this->field_option( 'placeholder', $field );

		// Confirmation Placeholder.
		$lbl  = $this->field_element(
			'label',
			$field,
			[
				'slug'    => 'confirmation_placeholder',
				'value'   => esc_html__( 'Confirmation Placeholder Text', 'wpforms' ),
				'tooltip' => esc_html__( 'Enter text for the confirmation field placeholder.', 'wpforms' ),
			],
			false
		);
		$fld  = $this->field_element(
			'text',
			$field,
			[
				'slug'  => 'confirmation_placeholder',
				'value' => ! empty( $field['confirmation_placeholder'] ) ? esc_attr( $field['confirmation_placeholder'] ) : '',
			],
			false
		);
		$args = [
			'slug'    => 'confirmation_placeholder',
			'content' => $lbl . $fld,
		];

		$this->field_element( 'row', $field, $args );

		// Default value.
		$this->field_option( 'default_value', $field );

		// Custom CSS classes.
		$this->field_option( 'css', $field );

		// Hide Label.
		$this->field_option( 'label_hide', $field );

		// Hide sublabels.
		$this->field_option( 'sublabel_hide', $field );

		// Options close markup.
		$args = [
			'markup' => 'close',
		];

		$this->field_option( 'advanced-options', $field, $args );
	}

	/**
	 * Field preview inside the builder.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field Current field specific data.
	 */
	public function field_preview( $field ) {

		$placeholder         = ! empty( $field['placeholder'] ) ? $field['placeholder'] : '';
		$confirm_placeholder = ! empty( $field['confirmation_placeholder'] ) ? $field['confirmation_placeholder'] : '';
		$default_value       = ! empty( $field['default_value'] ) ? $field['default_value'] : '';
		$confirm             = ! empty( $field['confirmation'] ) ? 'enabled' : 'disabled';

		// Label.
		$this->field_preview_option( 'label', $field );
		?>

		<div class="wpforms-confirm wpforms-confirm-<?php echo esc_attr( $confirm ); ?>">

			<div class="wpforms-confirm-primary">
				<input type="password" placeholder="<?php echo esc_attr( $placeholder ); ?>" value="<?php echo esc_attr( $default_value ); ?>" class="primary-input" readonly>
				<label class="wpforms-sub-label"><?php esc_html_e( 'Password', 'wpforms' ); ?></label>
			</div>

			<div class="wpforms-confirm-confirmation">
				<input type="password" placeholder="<?php echo esc_attr( $confirm_placeholder ); ?>" class="secondary-input" readonly>
				<label class="wpforms-sub-label"><?php esc_html_e( 'Confirm Password', 'wpforms' ); ?></label>
			</div>

		</div>

		<?php
		// Description.
		$this->field_preview_option( 'description', $field );
	}

	/**
	 * Field display on the form front-end.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field      Field data and settings.
	 * @param array $deprecated Deprecated field attributes. Use field properties.
	 * @param array $form_data  Form data and settings.
	 */
	public function field_display( $field, $deprecated, $form_data ) {

		// Define data.
		$confirmation = ! empty( $field['confirmation'] );
		$primary      = $field['properties']['inputs']['primary'];
		$secondary    = ! empty( $field['properties']['inputs']['secondary'] ) ? $field['properties']['inputs']['secondary'] : [];

		// Standard password field.
		if ( ! $confirmation ) {

			// Primary field.
			printf(
				'<input type="password" %s %s>',
				wpforms_html_attributes( $primary['id'], $primary['class'], $primary['data'], $primary['attr'] ),
				$primary['required']
			);

		} else { // Confirmation password field configuration.

			// Row wrapper.
			echo '<div class="wpforms-field-row wpforms-field-' . sanitize_html_class( $field['size'] ) . '">';

				// Primary field.
				echo '<div ' . wpforms_html_attributes( false, $primary['block'] ) . '>';
					$this->field_display_sublabel( 'primary', 'before', $field );
					printf(
						'<input type="password" %s %s>',
						wpforms_html_attributes( $primary['id'], $primary['class'], $primary['data'], $primary['attr'] ),
						$primary['required']
					);
					$this->field_display_sublabel( 'primary', 'after', $field );
					$this->field_display_error( 'primary', $field );
				echo '</div>';

				// Secondary field.
				echo '<div ' . wpforms_html_attributes( false, $secondary['block'] ) . '>';
					$this->field_display_sublabel( 'secondary', 'before', $field );
					printf(
						'<input type="password" %s %s>',
						wpforms_html_attributes( $secondary['id'], $secondary['class'], $secondary['data'], $secondary['attr'] ),
						$secondary['required']
					);
					$this->field_display_sublabel( 'secondary', 'after', $field );
					$this->field_display_error( 'secondary', $field );
				echo '</div>';

			echo '</div>';

		} // End if().
	}

	/**
	 * Validate field on form submit.
	 *
	 * @since 1.3.0
	 *
	 * @param int          $field_id     Field ID.
	 * @param array|string $field_submit Submitted field value.
	 * @param array        $form_data    Form data and settings.
	 */
	public function validate( $field_id, $field_submit, $form_data ) {

		$form_id  = $form_data['id'];
		$fields   = $form_data['fields'];
		$required = wpforms_get_required_label();

		// Standard configuration, confirmation disabled.
		if ( empty( $fields[ $field_id ]['confirmation'] ) ) {

			// Required check.
			if ( ! empty( $fields[ $field_id ]['required'] ) && wpforms_is_empty_string( $field_submit ) ) {
				wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ] = $required;
			}
		} else {

			if ( ! empty( $fields[ $field_id ]['required'] ) && isset( $field_submit['primary'] ) && wpforms_is_empty_string( $field_submit['primary'] ) ) {
				wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['primary'] = $required;
			}

			// Required check, secondary confirmation field.
			if ( ! empty( $fields[ $field_id ]['required'] ) && isset( $field_submit['secondary'] ) && wpforms_is_empty_string( $field_submit['secondary'] ) ) {
				wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['secondary'] = $required;
			}

			// Fields need to match.
			if ( $field_submit['primary'] !== $field_submit['secondary'] ) {
				wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['secondary'] = esc_html__( 'Field values do not match.', 'wpforms' );
			}
		}

		if (
			! empty( $fields[ $field_id ]['password-strength'] ) &&
			! empty( $fields[ $field_id ]['password-strength-level'] ) &&
			PHP_VERSION_ID >= 70200 &&
			! $this->is_empty_not_required_field( $field_id, $field_submit, $fields ) // Don't check the password strength for empty fields which is set as not required.
		) {

			require_once WPFORMS_PLUGIN_DIR . 'pro/libs/bjeavons/zxcvbn-php/autoload.php';

			$password_value = empty( $fields[ $field_id ]['confirmation'] ) ? $field_submit : $field_submit['primary'];
			$strength       = ( new \ZxcvbnPhp\Zxcvbn() )->passwordStrength( $password_value );

			if ( isset( $strength['score'] ) && $strength['score'] < (int) $fields[ $field_id ]['password-strength-level'] ) {
				wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ] = $this->strength_error_message();
			}
		}
	}

	/**
	 * Format and sanitize field.
	 *
	 * @since 1.3.0
	 *
	 * @param int          $field_id     Field ID.
	 * @param array|string $field_submit Submitted field value.
	 * @param array        $form_data    Form data and settings.
	 */
	public function format( $field_id, $field_submit, $form_data ) {

		// Define data.
		if ( is_array( $field_submit ) ) {
			$value = isset( $field_submit['primary'] ) && ! wpforms_is_empty_string( $field_submit['primary'] ) ? $field_submit['primary'] : '';
		} else {
			$value = ! wpforms_is_empty_string( $field_submit ) ? $field_submit : '';
		}

		$name = ! wpforms_is_empty_string( $form_data['fields'][ $field_id ] ['label'] ) ? $form_data['fields'][ $field_id ]['label'] : '';

		// Set final field details.
		wpforms()->get( 'process' )->fields[ $field_id ] = [
			'name'      => sanitize_text_field( $name ),
			'value'     => sanitize_text_field( $value ),
			'value_raw' => $value, // This is necessary for the login form to work correctly, it will be deleted before saving the entry.
			'id'        => absint( $field_id ),
			'type'      => $this->type,
		];
	}

	/**
	 * Form frontend CSS enqueues.
	 *
	 * @since 1.6.7
	 *
	 * @param array $forms Form data of forms on the current page.
	 */
	public function enqueue_frontend_css( $forms ) {

		if ( ! $this->strength_enabled( $forms ) && ! wpforms()->get( 'frontend' )->assets_global() ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-password-field',
			WPFORMS_PLUGIN_URL . "assets/pro/css/fields/password{$min}.css",
			[],
			WPFORMS_VERSION
		);
	}

	/**
	 * Form frontend JS enqueues.
	 *
	 * @since 1.6.7
	 *
	 * @param array $forms Form data of forms on the current page.
	 */
	public function enqueue_frontend_js( $forms ) {

		if ( ! $this->strength_enabled( $forms ) && ! wpforms()->get( 'frontend' )->assets_global() ) {
			return;
		}

		$min = \wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-password-field',
			WPFORMS_PLUGIN_URL . "assets/pro/js/fields/password{$min}.js",
			[ 'jquery', 'password-strength-meter' ],
			WPFORMS_VERSION,
			true
		);

	}

	/**
	 * Check if password strength enabled.
	 *
	 * @since 1.6.7
	 *
	 * @param array $forms Form data of forms on the current page.
	 *
	 * @return bool
	 */
	private function strength_enabled( $forms ) {

		foreach ( $forms as $form_data ) {
			if ( empty( $form_data['fields'] ) ) {
				continue;
			}

			foreach ( $form_data['fields'] as $field ) {
				if ( $field['type'] === 'password' && isset( $field['password-strength'] ) ) {
					return (bool) $field['password-strength'];
				}
			}
		}

		return false;
	}

	/**
	 * Add password strength validation error to frontend strings.
	 *
	 * @since 1.6.7
	 *
	 * @param array $strings Frontend strings.
	 *
	 * @return array Frontend strings.
	 */
	public function add_frontend_strings( $strings ) {

		$strings['val_password_strength'] = $this->strength_error_message();

		return $strings;
	}

	/**
	 * Get strength error message.
	 *
	 * @since 1.6.7
	 *
	 * @return string
	 */
	private function strength_error_message() {

		return wpforms_setting( 'validation-passwordstrength', esc_html__( 'A stronger password is required. Consider using upper and lower case letters, numbers, and symbols.', 'wpforms' ) );
	}

	/**
	 * Modify value for the entry preview field.
	 *
	 * @since 1.6.9
	 *
	 * @param string $value     Value.
	 * @param array  $field     Field data.
	 * @param array  $form_data Form data.
	 *
	 * @return string
	 */
	public function modify_entry_preview_value( $value, $field, $form_data ) {

		return str_repeat( '*', strlen( $value ) );
	}

	/**
	 * Checks if password field has been submitted empty and set as not required at the same time.
	 *
	 * @since 1.7.4
	 *
	 * @param int   $field_id     Field ID.
	 * @param array $field_submit Submitted field value.
	 * @param array $fields       Fields settings.
	 *
	 * @return bool
	 */
	private function is_empty_not_required_field( $field_id, $field_submit, $fields ) {

		return (
				// If submitted value is empty or is an array of empty values (that happens when password confirmation is enabled).
				empty( $field_submit ) || empty( implode( '', array_values( (array) $field_submit ) ) )
			)
			&& empty( $fields[ $field_id ]['required'] ); // If field is not set as required.
	}

	/**
	 * Determine if the field requires fieldset instead of the regular field label.
	 *
	 * @since 1.8.1
	 *
	 * @param bool  $requires_fieldset True if requires fieldset.
	 * @param array $field             Field data.
	 *
	 * @return bool
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function is_field_requires_fieldset( $requires_fieldset, $field ) {

		return ! empty( $field['confirmation'] );
	}
}

new WPForms_Field_Password();
