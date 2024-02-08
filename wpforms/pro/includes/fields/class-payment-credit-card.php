<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Credit card field (legacy).
 *
 * @since 1.0.0
 */
class WPForms_Field_CreditCard extends WPForms_Field {

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Define field type information.
		$this->name  = esc_html__( 'Credit Card', 'wpforms' );
		$this->type  = 'credit-card';
		$this->icon  = 'fa-credit-card';
		$this->order = 90;
		$this->group = 'payment';

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.8.1
	 */
	private function hooks() {

		// Define additional field properties.
		add_filter( 'wpforms_field_properties_credit-card', [ $this, 'field_properties' ], 5, 3 );

		// Set field to required by default.
		add_filter( 'wpforms_field_new_required', [ $this, 'default_required' ], 10, 2 );

		// Hide field if supporting payment gateway is not activated.
		add_action( 'wpforms_builder_print_footer_scripts', [ $this, 'builder_footer_scripts' ] );

		// Load required scripts.
		add_action( 'wpforms_frontend_js', [ $this, 'load_js' ], 10 );

		// This field requires fieldset+legend instead of the field label.
		add_filter( "wpforms_frontend_modern_is_field_requires_fieldset_{$this->type}", '__return_true', PHP_INT_MAX, 2 );
	}

	/**
	 * Load required scripts.
	 *
	 * @since 1.7.5.3
	 *
	 * @param array $forms Forms on the current page.
	 */
	public function load_js( $forms ) {

		if (
			wpforms_has_field_type( 'credit-card', $forms, true ) ||
			wpforms()->get( 'frontend' )->assets_global()
		) {
			wp_enqueue_script(
				'wpforms-payment',
				WPFORMS_PLUGIN_URL . 'assets/pro/lib/jquery.payment.min.js',
				[ 'jquery' ],
				WPFORMS_VERSION,
				true
			);
		}
	}

	/**
	 * Define additional field properties.
	 *
	 * @since 1.3.8
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Field settings.
	 * @param array $form_data  Form data and settings.
	 *
	 * @return array
	 */
	public function field_properties( $properties, $field, $form_data ) {

		// Remove primary for expanded formats since we have first, middle, last.
		unset( $properties['inputs']['primary'] );

		$form_id  = absint( $form_data['id'] );
		$field_id = absint( $field['id'] );
		$position = wpforms_get_render_engine() === 'classic' ? 'before' : 'after';

		$props      = [
			'inputs' => [
				'number' => [
					'attr'     => [
						'name'         => '',
						'value'        => '',
						'placeholder'  => ! empty( $field['cardnumber_placeholder'] ) ? $field['cardnumber_placeholder'] : '',
						'autocomplete' => 'off',
					],
					'block'    => [
						'wpforms-field-credit-card-number',
					],
					'class'    => [
						'wpforms-field-credit-card-cardnumber',
					],
					'data'     => [
						'rule-creditcard' => 'yes',
					],
					'id'       => "wpforms-{$form_id}-field_{$field_id}",
					'required' => ! empty( $field['required'] ) ? 'required' : '',
					'sublabel' => [
						'hidden'   => ! empty( $field['sublabel_hide'] ),
						'value'    => esc_html__( 'Card Number', 'wpforms' ),
						'position' => $position,
					],
				],
				'cvc'    => [
					'attr'     => [
						'name'         => '',
						'value'        => '',
						'placeholder'  => ! empty( $field['cardcvc_placeholder'] ) ? $field['cardcvc_placeholder'] : '',
						'maxlength'    => '4',
						'autocomplete' => 'off',
					],
					'block'    => [
						'wpforms-field-credit-card-code',
					],
					'class'    => [
						'wpforms-field-credit-card-cardcvc',
					],
					'data'     => [],
					'id'       => "wpforms-{$form_id}-field_{$field_id}-cardcvc",
					'required' => ! empty( $field['required'] ) ? 'required' : '',
					'sublabel' => [
						'hidden'   => ! empty( $field['sublabel_hide'] ),
						'value'    => esc_html__( 'Security Code', 'wpforms' ),
						'position' => $position,
					],
				],
				'name'   => [
					'attr'     => [
						'name'        => '',
						'value'       => '',
						'placeholder' => ! empty( $field['cardname_placeholder'] ) ? $field['cardname_placeholder'] : '',
					],
					'block'    => [
						'wpforms-field-credit-card-name',
					],
					'class'    => [
						'wpforms-field-credit-card-cardname',
					],
					'data'     => [],
					'id'       => "wpforms-{$form_id}-field_{$field_id}-cardname",
					'required' => ! empty( $field['required'] ) ? 'required' : '',
					'sublabel' => [
						'hidden'   => ! empty( $field['sublabel_hide'] ),
						'value'    => esc_html__( 'Name on Card', 'wpforms' ),
						'position' => $position,
					],
				],
				'month'  => [
					'attr'     => [],
					'class'    => [
						'wpforms-field-credit-card-cardmonth',
					],
					'data'     => [],
					'id'       => "wpforms-{$form_id}-field_{$field_id}-cardmonth",
					'required' => ! empty( $field['required'] ) ? 'required' : '',
					'sublabel' => [
						'hidden'   => ! empty( $field['sublabel_hide'] ),
						'value'    => esc_html__( 'Expiration', 'wpforms' ),
						'position' => $position,
					],
				],
				'year'   => [
					'attr'     => [],
					'class'    => [
						'wpforms-field-credit-card-cardyear',
					],
					'data'     => [],
					'id'       => "wpforms-{$form_id}-field_{$field_id}-cardyear",
					'required' => ! empty( $field['required'] ) ? 'required' : '',
				],
			],
		];
		$properties = array_merge_recursive( $properties, $props );

		// If this field is required we need to make some adjustments.
		if ( ! empty( $field['required'] ) ) {

			// Add required class if needed (for multi-page validation).
			$properties['inputs']['number']['class'][] = 'wpforms-field-required';
			$properties['inputs']['cvc']['class'][]    = 'wpforms-field-required';
			$properties['inputs']['name']['class'][]   = 'wpforms-field-required';
			$properties['inputs']['month']['class'][]  = 'wpforms-field-required';
			$properties['inputs']['year']['class'][]   = 'wpforms-field-required';

			// Below we add our input special classes if certain fields are
			// required. jQuery Validation library will not correctly validate
			// fields that do not have a name attribute, so we use the
			// `wpforms-input-temp-name` class to let jQuery know we should add
			// a temporary name attribute before validation is initialized, then
			// remove it before the form submits.
			$properties['inputs']['number']['class'][] = 'wpforms-input-temp-name';
			$properties['inputs']['cvc']['class'][]    = 'wpforms-input-temp-name';
			$properties['inputs']['name']['class'][]   = 'wpforms-input-temp-name';
			$properties['inputs']['month']['class'][]  = 'wpforms-input-temp-name';
			$properties['inputs']['year']['class'][]   = 'wpforms-input-temp-name';
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
	 * Default to required.
	 *
	 * @since 1.0.9
	 *
	 * @param bool  $required Required status, true is required.
	 * @param array $field    Field settings.
	 *
	 * @return bool
	 */
	public function default_required( $required, $field ) {

		if ( 'credit-card' === $field['type'] ) {
			return true;
		}

		return $required;
	}

	/**
	 * If a supporting payment gateway is not active, don't allow users to add
	 * the field inside the form builder.
	 *
	 * @since 1.4.6
	 */
	public function builder_footer_scripts() {

		if ( apply_filters( 'wpforms_field_credit_card_enable', false ) ) {
			return;
		}
		?>
		<script type="text/javascript">
			jQuery(function($){
				$( '#wpforms-add-fields-credit-card' ).remove();
			});
		</script>
		<?php
	}

	/**
	 * Field options panel inside the builder.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field Field settings.
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

		// Card Number.
		$cardnumber_placeholder = ! empty( $field['cardnumber_placeholder'] ) ? esc_attr( $field['cardnumber_placeholder'] ) : '';

		printf(
			'<div class="wpforms-clear wpforms-field-option-row wpforms-field-option-row-cardnumber" id="wpforms-field-option-row-%1$d-cardnumber" data-subfield="cardnumber" data-field-id="%1$d">',
			absint( $field['id'] )
		);
		$this->field_element(
			'label',
			$field,
			[
				'slug'  => 'cardnumber_placeholder',
				'value' => esc_html__( 'Card Number Placeholder Text', 'wpforms' ),
			]
		);

		echo '<div class="placeholder">';
		printf(
			'<input type="text" class="placeholder-update" id="wpforms-field-option-%1$d-cardnumber_placeholder" name="fields[%1$d][cardnumber_placeholder]" value="%2$s" data-field-id="%1$d" data-subfield="credit-card-cardnumber">',
			absint( $field['id'] ),
			esc_attr( $cardnumber_placeholder )
		);
		echo '</div>';
		echo '</div>';

		// CVC/Security Code.
		$cardcvc_placeholder = ! empty( $field['cardcvc_placeholder'] ) ? $field['cardcvc_placeholder'] : '';

		printf(
			'<div class="wpforms-clear wpforms-field-option-row wpforms-field-option-row-cvc" id="wpforms-field-option-row-%1$d-cvc" data-subfield="cvc" data-field-id="%1$d">',
			absint( $field['id'] )
		);
		$this->field_element(
			'label',
			$field,
			[
				'slug'  => 'cardcvc_placeholder',
				'value' => esc_html__( 'Security Code Placeholder Text', 'wpforms' ),
			]
		);

		echo '<div class="placeholder">';
		printf(
			'<input type="text" class="placeholder-update" id="wpforms-field-option-%1$d-cardcvc_placeholder" name="fields[%1$d][cardcvc_placeholder]" value="%2$s" data-field-id="%1$d" data-subfield="credit-card-cardcvc">',
			absint( $field['id'] ),
			esc_attr( $cardcvc_placeholder )
		);
		echo '</div>';
		echo '</div>';

		// Card Name.
		$cardname_placeholder = ! empty( $field['cardname_placeholder'] ) ? $field['cardname_placeholder'] : '';

		printf(
			'<div class="wpforms-clear wpforms-field-option-row wpforms-field-option-row-cardname" id="wpforms-field-option-row-%1$d-cardname" data-subfield="cardname" data-field-id="%1$d">',
			absint( $field['id'] )
		);
		$this->field_element(
			'label',
			$field,
			[
				'slug'  => 'cardname_placeholder',
				'value' => esc_html__( 'Name on Card Placeholder Text', 'wpforms' ),
			]
		);

		echo '<div class="placeholder">';
		printf(
			'<input type="text" class="placeholder-update" id="wpforms-field-option-%1$d-cardname_placeholder" name="fields[%1$d][cardname_placeholder]" value="%2$s" data-field-id="%1$d" data-subfield="credit-card-cardname">',
			absint( $field['id'] ),
			esc_attr( $cardname_placeholder )
		);
		echo '</div>';
		echo '</div>';

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
	 * @param array $field Field settings.
	 */
	public function field_preview( $field ) {

		// Define data.
		$number_placeholder = ! empty( $field['cardnumber_placeholder'] ) ? esc_attr( $field['cardnumber_placeholder'] ) : '';
		$cvc_placeholder    = ! empty( $field['cardcvc_placeholder'] ) ? esc_attr( $field['cardcvc_placeholder'] ) : '';
		$name_placeholder   = ! empty( $field['cardname_placeholder'] ) ? esc_attr( $field['cardname_placeholder'] ) : '';

		// Label.
		$this->field_preview_option( 'label', $field );
		?>

		<div class="format-selected format-selected-full">

			<div class="wpforms-field-row">
				<div class="wpforms-credit-card-cardnumber">
					<label class="wpforms-sub-label"><?php esc_html_e( 'Card Number', 'wpforms' ); ?></label>
					<input type="text" placeholder="<?php echo esc_attr( $number_placeholder ); ?>" readonly>
				</div>

				<div class="wpforms-credit-card-cardcvc">
					<label class="wpforms-sub-label"><?php esc_html_e( 'Security Code', 'wpforms' ); ?></label>
					<input type="text" placeholder="<?php echo esc_attr( $cvc_placeholder ); ?>" readonly>
				</div>
			</div>

			<div class="wpforms-field-row">
				<div class="wpforms-credit-card-cardname">
					<label class="wpforms-sub-label"><?php esc_html_e( 'Name on Card', 'wpforms' ); ?></label>
					<input type="text" placeholder="<?php echo esc_attr( $name_placeholder ); ?>" readonly>
				</div>

				<div class="wpforms-credit-card-expiration">
					<label class="wpforms-sub-label"><?php esc_html_e( 'Expiration', 'wpforms' ); ?></label>
					<div class="wpforms-credit-card-cardmonth">
						<select readonly>
							<option>MM</option>
						</select>
					</div>
					<span>/</span>
					<div class="wpforms-credit-card-cardyear">
						<select readonly>
							<option>YY</option>
						</select>
					</div>
				</div>
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
		$number = ! empty( $field['properties']['inputs']['number'] ) ? $field['properties']['inputs']['number'] : [];
		$cvc    = ! empty( $field['properties']['inputs']['cvc'] ) ? $field['properties']['inputs']['cvc'] : [];
		$name   = ! empty( $field['properties']['inputs']['name'] ) ? $field['properties']['inputs']['name'] : [];
		$month  = ! empty( $field['properties']['inputs']['month'] ) ? $field['properties']['inputs']['month'] : [];
		$year   = ! empty( $field['properties']['inputs']['year'] ) ? $field['properties']['inputs']['year'] : [];

		// Display warning for non SSL pages.
		if ( ! is_ssl() ) {
			echo '<div class="wpforms-cc-warning wpforms-error-alert">';
			esc_html_e( 'This page is insecure. Credit Card field should be used for testing purposes only.', 'wpforms' );
			echo '</div>';
		}

		// Row wrapper.
		echo '<div class="wpforms-field-row wpforms-field-' . sanitize_html_class( $field['size'] ) . '">';

		// Card number.
		echo '<div ' . wpforms_html_attributes( false, $number['block'] ) . '>';
		$this->field_display_sublabel( 'number', 'before', $field );
		printf(
			'<input type="text" %s %s>',
			wpforms_html_attributes( $number['id'], $number['class'], $number['data'], $number['attr'] ),
			! empty( $number['required'] ) ? 'required' : ''
		);
		$this->field_display_sublabel( 'number', 'after', $field );
		$this->field_display_error( 'number', $field );
		echo '</div>';

		// CVC.
		echo '<div ' . wpforms_html_attributes( false, $cvc['block'] ) . '>';
		$this->field_display_sublabel( 'cvc', 'before', $field );
		printf(
			'<input type="text" %s %s>',
			wpforms_html_attributes( $cvc['id'], $cvc['class'], $cvc['data'], $cvc['attr'] ),
			! empty( $cvc['required'] ) ? 'required' : ''
		);
		$this->field_display_sublabel( 'cvc', 'after', $field );
		$this->field_display_error( 'cvc', $field );
		echo '</div>';

		echo '</div>';

		// Row wrapper.
		echo '<div class="wpforms-field-row wpforms-field-' . sanitize_html_class( $field['size'] ) . '">';

		// Name.
		echo '<div ' . wpforms_html_attributes( false, $name['block'] ) . '>';
		$this->field_display_sublabel( 'name', 'before', $field );
		printf(
			'<input type="text" %s %s>',
			wpforms_html_attributes( $name['id'], $name['class'], $name['data'], $name['attr'] ),
			! empty( $name['required'] ) ? 'required' : ''
		);
		$this->field_display_sublabel( 'name', 'after', $field );
		$this->field_display_error( 'name', $field );
		echo '</div>';

		// Expiration.
		echo '<div class="wpforms-field-credit-card-expiration">';

		// Month.
		$this->field_display_sublabel( 'month', 'before', $field );
		printf(
			'<select %s %s>',
			wpforms_html_attributes( $month['id'], $month['class'], $month['data'], $month['attr'] ),
			! empty( $month['required'] ) ? 'required' : ''
		);
		echo '<option class="placeholder" selected disabled>MM</option>';
		foreach ( range( 1, 12 ) as $number ) {
			printf( '<option value="%1$d">%1$d</option>', absint( $number ) );
		}
		echo '</select>';
		$this->field_display_sublabel( 'month', 'after', $field );
		$this->field_display_error( 'month', $field );

		// Sep.
		echo '<span>/</span>';

		// Year.
		$this->field_display_sublabel( 'year', 'before', $field );
		printf(
			'<select %s %s>',
			wpforms_html_attributes( $year['id'], $year['class'], $year['data'], $year['attr'] ),
			! empty( $year['required'] ) ? 'required' : ''
		);
		echo '<option class="placeholder" selected disabled>YY</option>';
		$start_year = gmdate( 'y' );
		$end_year   = $start_year + 11;

		for ( $i = $start_year; $i < $end_year; $i++ ) {
			printf( '<option value="%1$d">%1$d</option>', absint( $i ) );
		}
		echo '</select>';
		$this->field_display_sublabel( 'year', 'after', $field );
		$this->field_display_error( 'year', $field );

		echo '</div>';

		echo '</div>';
	}

	/**
	 * Currently validation happens on the front end. We do not do
	 * generic server-side validation because we do not allow the card
	 * details to POST to the server.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $field_id     Field ID.
	 * @param array $field_submit Submitted field value.
	 * @param array $form_data    Form data and settings.
	 */
	public function validate( $field_id, $field_submit, $form_data ) {
	}

	/**
	 * Format field.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $field_id     Field ID.
	 * @param array $field_submit Submitted field value.
	 * @param array $form_data    Form data and settings.
	 */
	public function format( $field_id, $field_submit, $form_data ) {

		// Define data.
		$name = ! empty( $form_data['fields'][ $field_id ]['label'] ) ? $form_data['fields'][ $field_id ]['label'] : '';

		// Set final field details.
		wpforms()->get( 'process' )->fields[ $field_id ] = [
			'name'  => sanitize_text_field( $name ),
			'value' => '',
			'id'    => absint( $field_id ),
			'type'  => sanitize_key( $this->type ),
		];
	}
}
new WPForms_Field_CreditCard();
