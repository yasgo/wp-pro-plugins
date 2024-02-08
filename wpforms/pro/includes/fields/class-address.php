<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Address text field.
 *
 * @since 1.0.0
 */
class WPForms_Field_Address extends WPForms_Field {

	/**
	 * Address schemes: 'us' or 'international' by default.
	 *
	 * @since 1.2.7
	 *
	 * @var array
	 */
	public $schemes;

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Define field type information.
		$this->name  = esc_html__( 'Address', 'wpforms' );
		$this->type  = 'address';
		$this->icon  = 'fa-map-marker';
		$this->order = 70;
		$this->group = 'fancy';

		// Allow for additional or customizing address schemes.
		$default_schemes = [
			'us'            => [
				'label'          => esc_html__( 'US', 'wpforms' ),
				'address1_label' => esc_html__( 'Address Line 1', 'wpforms' ),
				'address2_label' => esc_html__( 'Address Line 2', 'wpforms' ),
				'city_label'     => esc_html__( 'City', 'wpforms' ),
				'postal_label'   => esc_html__( 'Zip Code', 'wpforms' ),
				'state_label'    => esc_html__( 'State', 'wpforms' ),
				'states'         => wpforms_us_states(),
			],
			'international' => [
				'label'          => esc_html__( 'International', 'wpforms' ),
				'address1_label' => esc_html__( 'Address Line 1', 'wpforms' ),
				'address2_label' => esc_html__( 'Address Line 2', 'wpforms' ),
				'city_label'     => esc_html__( 'City', 'wpforms' ),
				'postal_label'   => esc_html__( 'Postal Code', 'wpforms' ),
				'state_label'    => esc_html__( 'State / Province / Region', 'wpforms' ),
				'states'         => '',
				'country_label'  => esc_html__( 'Country', 'wpforms' ),
				'countries'      => wpforms_countries(),
			],
		];

		/**
		 * Allow modifying address schemes.
		 *
		 * @since 1.2.7
		 *
		 * @param array $schemes Address schemes.
		 */
		$this->schemes = apply_filters( 'wpforms_address_schemes', $default_schemes ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.8.1
	 */
	private function hooks() {

		// Define additional field properties.
		add_filter( 'wpforms_field_properties_address', [ $this, 'field_properties' ], 5, 3 );

		// Customize value format.
		add_filter( 'wpforms_html_field_value', [ $this, 'html_field_value' ], 10, 4 );

		// This field requires fieldset+legend instead of the field label.
		add_filter( "wpforms_frontend_modern_is_field_requires_fieldset_{$this->type}", '__return_true', PHP_INT_MAX, 2 );
	}

	/**
	 * Define additional field properties.
	 *
	 * @since 1.4.1
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Field data and settings.
	 * @param array $form_data  Form data and settings.
	 *
	 * @return array
	 */
	public function field_properties( $properties, $field, $form_data ) {

		// Determine scheme we should use moving forward.
		$scheme = 'us';

		if ( ! empty( $field['scheme'] ) ) {
			$scheme = esc_attr( $field['scheme'] );
		} elseif ( ! empty( $field['format'] ) ) {
			// <1.2.7 backwards compatibility.
			$scheme = esc_attr( $field['format'] );
		}

		// Expanded formats.
		// Remove primary for expanded formats.
		unset( $properties['inputs']['primary'] );

		$form_id   = absint( $form_data['id'] );
		$field_id  = absint( $field['id'] );
		$countries = $this->schemes[ $scheme ]['countries'] ?? [];

		asort( $countries );

		$states            = $this->schemes[ $scheme ]['states'] ?? '';
		$state_placeholder = ! empty( $field['state_placeholder'] ) ? $field['state_placeholder'] : '';

		// Set placeholder for state dropdown.
		if ( is_array( $states ) && ! $state_placeholder ) {
			$state_placeholder = $this->dropdown_empty_value( 'state' );
		}

		// Properties shared by both core schemes.
		$props      = [
			'inputs' => [
				'address1' => [
					'attr'     => [
						'name'        => "wpforms[fields][{$field_id}][address1]",
						'value'       => ! empty( $field['address1_default'] ) ? wpforms_process_smart_tags( $field['address1_default'], $form_data ) : '',
						'placeholder' => ! empty( $field['address1_placeholder'] ) ? $field['address1_placeholder'] : '',
					],
					'block'    => [],
					'class'    => [
						'wpforms-field-address-address1',
					],
					'data'     => [],
					'id'       => "wpforms-{$form_id}-field_{$field_id}",
					'required' => ! empty( $field['required'] ) ? 'required' : '',
					'sublabel' => [
						'hidden' => ! empty( $field['sublabel_hide'] ),
						'value'  => isset( $this->schemes[ $scheme ]['address1_label'] ) ? $this->schemes[ $scheme ]['address1_label'] : '',
					],
				],
				'address2' => [
					'attr'     => [
						'name'        => "wpforms[fields][{$field_id}][address2]",
						'value'       => ! empty( $field['address2_default'] ) ? wpforms_process_smart_tags( $field['address2_default'], $form_data ) : '',
						'placeholder' => ! empty( $field['address2_placeholder'] ) ? $field['address2_placeholder'] : '',
					],
					'block'    => [],
					'class'    => [
						'wpforms-field-address-address2',
					],
					'data'     => [],
					'hidden'   => ! empty( $field['address2_hide'] ),
					'id'       => "wpforms-{$form_id}-field_{$field_id}-address2",
					'required' => '',
					'sublabel' => [
						'hidden' => ! empty( $field['sublabel_hide'] ),
						'value'  => isset( $this->schemes[ $scheme ]['address2_label'] ) ? $this->schemes[ $scheme ]['address2_label'] : '',
					],
				],
				'city'     => [
					'attr'     => [
						'name'        => "wpforms[fields][{$field_id}][city]",
						'value'       => ! empty( $field['city_default'] ) ? wpforms_process_smart_tags( $field['city_default'], $form_data ) : '',
						'placeholder' => ! empty( $field['city_placeholder'] ) ? $field['city_placeholder'] : '',
					],
					'block'    => [
						'wpforms-field-row-block',
						'wpforms-one-half',
						'wpforms-first',
					],
					'class'    => [
						'wpforms-field-address-city',
					],
					'data'     => [],
					'id'       => "wpforms-{$form_id}-field_{$field_id}-city",
					'required' => ! empty( $field['required'] ) ? 'required' : '',
					'sublabel' => [
						'hidden' => ! empty( $field['sublabel_hide'] ),
						'value'  => isset( $this->schemes[ $scheme ]['city_label'] ) ? $this->schemes[ $scheme ]['city_label'] : '',
					],
				],
				'state'    => [
					'attr'     => [
						'name'        => "wpforms[fields][{$field_id}][state]",
						'value'       => ! empty( $field['state_default'] ) ? wpforms_process_smart_tags( $field['state_default'], $form_data ) : '',
						'placeholder' => $state_placeholder,
					],
					'block'    => [
						'wpforms-field-row-block',
						'wpforms-one-half',
					],
					'class'    => [
						'wpforms-field-address-state',
					],
					'data'     => [],
					'id'       => "wpforms-{$form_id}-field_{$field_id}-state",
					'options'  => $states,
					'required' => ! empty( $field['required'] ) ? 'required' : '',
					'sublabel' => [
						'hidden' => ! empty( $field['sublabel_hide'] ),
						'value'  => isset( $this->schemes[ $scheme ]['state_label'] ) ? $this->schemes[ $scheme ]['state_label'] : '',
					],
				],
				'postal'   => [
					'attr'     => [
						'name'        => "wpforms[fields][{$field_id}][postal]",
						'value'       => ! empty( $field['postal_default'] ) ? wpforms_process_smart_tags( $field['postal_default'], $form_data ) : '',
						'placeholder' => ! empty( $field['postal_placeholder'] ) ? $field['postal_placeholder'] : '',
					],
					'block'    => [
						'wpforms-field-row-block',
						'wpforms-one-half',
						'wpforms-first',
					],
					'class'    => [
						'wpforms-field-address-postal',
					],
					'data'     => [],
					'hidden'   => ! empty( $field['postal_hide'] ) || ! isset( $this->schemes[ $scheme ]['postal_label'] ) ? true : false,
					'id'       => "wpforms-{$form_id}-field_{$field_id}-postal",
					'required' => ! empty( $field['required'] ) ? 'required' : '',
					'sublabel' => [
						'hidden' => ! empty( $field['sublabel_hide'] ),
						'value'  => isset( $this->schemes[ $scheme ]['postal_label'] ) ? $this->schemes[ $scheme ]['postal_label'] : '',
					],
				],
				'country'  => [
					'attr'     => [
						'name'        => "wpforms[fields][{$field_id}][country]",
						'value'       => ! empty( $field['country_default'] ) ? wpforms_process_smart_tags( $field['country_default'], $form_data ) : '',
						'placeholder' => ! empty( $field['country_placeholder'] ) ? $field['country_placeholder'] : $this->dropdown_empty_value( 'country' ),
					],
					'block'    => [
						'wpforms-field-row-block',
						'wpforms-one-half',
					],
					'class'    => [
						'wpforms-field-address-country',
					],
					'data'     => [],
					'hidden'   => ! empty( $field['country_hide'] ) || ! isset( $this->schemes[ $scheme ]['countries'] ) ? true : false,
					'id'       => "wpforms-{$form_id}-field_{$field_id}-country",
					'options'  => $countries,
					'required' => ! empty( $field['required'] ) ? 'required' : '',
					'sublabel' => [
						'hidden' => ! empty( $field['sublabel_hide'] ),
						'value'  => isset( $this->schemes[ $scheme ]['country_label'] ) ? $this->schemes[ $scheme ]['country_label'] : '',
					],
				],
			],
		];
		$properties = array_merge_recursive( $properties, $props );

		// Input keys.
		$keys = [ 'address1', 'address2', 'city', 'state', 'postal', 'country' ];

		// Add input error class if needed.
		foreach ( $keys as $key ) {
			if ( ! empty( $properties['error']['value'][ $key ] ) ) {
				$properties['inputs'][ $key ]['class'][] = 'wpforms-error';
			}
		}

		// Add input required class if needed.
		foreach ( $keys as $key ) {
			if ( ! empty( $properties['inputs'][ $key ]['required'] ) ) {
				$properties['inputs'][ $key ]['class'][] = 'wpforms-field-required';
			}
		}

		// Add Postal code input mask for US address.
		if ( $scheme === 'us' ) {
			$properties['inputs']['postal']['class'][]                           = 'wpforms-masked-input';
			$properties['inputs']['postal']['data']['inputmask-mask']            = '(99999)|(99999-9999)';
			$properties['inputs']['postal']['data']['inputmask-keepstatic']      = 'true';
			$properties['inputs']['postal']['data']['rule-inputmask-incomplete'] = true;
		}

		return $properties;
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
		$this->field_option(
			'basic-options',
			$field,
			[
				'markup' => 'open',
			]
		);

		// Label.
		$this->field_option( 'label', $field );

		// Address Scheme - was "format" key prior to 1.2.7.
		$scheme = ! empty( $field['scheme'] ) ? esc_attr( $field['scheme'] ) : 'us';

		if ( empty( $scheme ) && ! empty( $field['format'] ) ) {
			$scheme = esc_attr( $field['format'] );
		}

		$tooltip = esc_html__( 'Select scheme format for the address field.', 'wpforms' );
		$options = [];

		foreach ( $this->schemes as $slug => $s ) {
			$options[ $slug ] = $s['label'];
		}

		$output = $this->field_element(
			'label',
			$field,
			[
				'slug'    => 'scheme',
				'value'   => esc_html__( 'Scheme', 'wpforms' ),
				'tooltip' => $tooltip,
			],
			false
		);

		$output .= $this->field_element(
			'select',
			$field,
			[
				'slug'    => 'scheme',
				'value'   => $scheme,
				'options' => $options,
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'scheme',
				'content' => $output,
			]
		);

		// Description.
		$this->field_option( 'description', $field );

		// Required toggle.
		$this->field_option( 'required', $field );

		// Options close markup.
		$this->field_option(
			'basic-options',
			$field,
			[
				'markup' => 'close',
			]
		);

		/*
		 * Advanced field options.
		 */

		// Options open markup.
		$this->field_option(
			'advanced-options',
			$field,
			[
				'markup' => 'open',
			]
		);

		// Size.
		$this->field_option( 'size', $field );

		// Address Line 1.
		$address1_placeholder = ! empty( $field['address1_placeholder'] ) ? esc_attr( $field['address1_placeholder'] ) : '';
		$address1_default     = ! empty( $field['address1_default'] ) ? esc_attr( $field['address1_default'] ) : '';

		printf(
			'<div class="wpforms-clear wpforms-field-option-row wpforms-field-option-row-address1"
				id="wpforms-field-option-row-%1$d-address1"
				data-subfield="address-1"
				data-field-id="%1$d">',
			absint( $field['id'] )
		);

			$this->field_element(
				'label',
				$field,
				[
					'slug'  => 'address1_placeholder',
					'value' => esc_html__( 'Address Line 1', 'wpforms' ),
				]
			);

			echo '<div class="wpforms-field-options-columns-2 wpforms-field-options-columns">';
				echo '<div class="placeholder wpforms-field-options-column">';
					printf( '<input type="text" class="placeholder" id="wpforms-field-option-%1$d-address1_placeholder" name="fields[%1$d][address1_placeholder]" value="%2$s">', absint( $field['id'] ), esc_attr( $address1_placeholder ) );
					printf( '<label for="wpforms-field-option-%d-address1_placeholder" class="sub-label">%s</label>', absint( $field['id'] ), esc_html__( 'Placeholder', 'wpforms' ) );
				echo '</div>';
				echo '<div class="default wpforms-field-options-column">';
					printf( '<input type="text" class="default" id="wpforms-field-option-%1$d-address1_default" name="fields[%1$d][address1_default]" value="%2$s">', absint( $field['id'] ), esc_attr( $address1_default ) );
					printf( '<label for="wpforms-field-option-%d-address1_default" class="sub-label">%s</label>', absint( $field['id'] ), esc_html__( 'Default Value', 'wpforms' ) );
				echo '</div>';
			echo '</div>';
		echo '</div>';

		// Address Line 2.
		$address2_placeholder = ! empty( $field['address2_placeholder'] ) ? esc_attr( $field['address2_placeholder'] ) : '';
		$address2_default     = ! empty( $field['address2_default'] ) ? esc_attr( $field['address2_default'] ) : '';
		$address2_hide        = ! empty( $field['address2_hide'] ) ? true : false;

		printf(
			'<div class="wpforms-clear wpforms-field-option-row wpforms-field-option-row-address2"
				id="wpforms-field-option-row-%1$d-address2"
				data-subfield="address-2"
				data-field-id="%1$d">',
			absint( $field['id'] )
		);

		echo '<div class="wpforms-field-header">';

			$this->field_element(
				'label',
				$field,
				[
					'slug'  => 'address2_placeholder',
					'value' => esc_html__( 'Address Line 2', 'wpforms' ),
				]
			);

			$this->field_element(
				'toggle',
				$field,
				[
					'slug'          => 'address2_hide',
					'value'         => $address2_hide,
					'desc'          => esc_html__( 'Hide', 'wpforms' ),
					'title'         => esc_html__( 'Turn On if you want to hide this sub field.', 'wpforms' ),
					'label-left'    => true,
					'control-class' => 'wpforms-field-option-in-label-right',
					'class'         => 'wpforms-subfield-hide',
				],
				true
			);

			echo '</div>';
			echo '<div class="wpforms-field-options-columns-2 wpforms-field-options-columns">';
				echo '<div class="placeholder wpforms-field-options-column">';
					printf( '<input type="text" class="placeholder" id="wpforms-field-option-%1$d-address2_placeholder" name="fields[%1$d][address2_placeholder]" value="%2$s">', absint( $field['id'] ), esc_attr( $address2_placeholder ) );
					printf( '<label for="wpforms-field-option-%d-address2_placeholder" class="sub-label">%s</label>', absint( $field['id'] ), esc_html__( 'Placeholder', 'wpforms' ) );
				echo '</div>';
				echo '<div class="default wpforms-field-options-column">';
					printf( '<input type="text" class="default" id="wpforms-field-option-%1$d-address2_default" name="fields[%1$d][address2_default]" value="%2$s">', absint( $field['id'] ), esc_attr( $address2_default ) );
					printf( '<label for="wpforms-field-option-%d-address2_default" class="sub-label">%s</label>', absint( $field['id'] ), esc_html__( 'Default Value', 'wpforms' ) );
				echo '</div>';
			echo '</div>';
		echo '</div>';

		// City.
		$city_placeholder = ! empty( $field['city_placeholder'] ) ? esc_attr( $field['city_placeholder'] ) : '';
		$city_default     = ! empty( $field['city_default'] ) ? esc_attr( $field['city_default'] ) : '';

		printf(
			'<div class="wpforms-clear wpforms-field-option-row wpforms-field-option-row-city"
				id="wpforms-field-option-row-%1$d-city"
				data-subfield="city"
				data-field-id="%1$d">',
			absint( $field['id'] )
		);

			$this->field_element(
				'label',
				$field,
				[
					'slug'  => 'city_placeholder',
					'value' => esc_html__( 'City', 'wpforms' ),
				]
			);

			echo '<div class="wpforms-field-options-columns-2 wpforms-field-options-columns">';
				echo '<div class="placeholder wpforms-field-options-column">';
					printf( '<input type="text" class="placeholder" id="wpforms-field-option-%1$d-city_placeholder" name="fields[%1$d][city_placeholder]" value="%2$s">', absint( $field['id'] ), esc_attr( $city_placeholder ) );
					printf( '<label for="wpforms-field-option-%d-city_placeholder" class="sub-label">%s</label>', absint( $field['id'] ), esc_html__( 'Placeholder', 'wpforms' ) );
				echo '</div>';
				echo '<div class="default wpforms-field-options-column">';
					printf( '<input type="text" class="default" id="wpforms-field-option-%1$d-city_default" name="fields[%1$d][city_default]" value="%2$s">', absint( $field['id'] ), esc_attr( $city_default ) );
					printf( '<label for="wpforms-field-option-%d-city_default" class="sub-label">%s</label>', absint( $field['id'] ), esc_html__( 'Default Value', 'wpforms' ) );
				echo '</div>';
			echo '</div>';
		echo '</div>';

		// State.
		$state_placeholder = ! empty( $field['state_placeholder'] ) ? $field['state_placeholder'] : '';

		printf(
			'<div class="wpforms-clear wpforms-field-option-row wpforms-field-option-row-state"
				id="wpforms-field-option-row-%1$d-state"
				data-subfield="state"
				data-field-id="%1$d">',
			absint( $field['id'] )
		);

			$this->field_element(
				'label',
				$field,
				[
					'slug'  => 'state_placeholder',
					'value' => esc_html__( 'State / Province / Region', 'wpforms' ),
				]
			);

			echo '<div class="wpforms-field-options-columns-2 wpforms-field-options-columns">';
				echo '<div class="placeholder wpforms-field-options-column">';
					printf( '<input type="text" class="placeholder" id="wpforms-field-option-%1$d-state_placeholder" name="fields[%1$d][state_placeholder]" value="%2$s">', absint( $field['id'] ), esc_attr( $state_placeholder ) );
					printf( '<label for="wpforms-field-option-%d-state_placeholder" class="sub-label">%s</label>', absint( $field['id'] ), esc_html__( 'Placeholder', 'wpforms' ) );
				echo '</div>';
				echo '<div class="default wpforms-field-options-column">';
					$this->subfield_default( $field, 'state', 'states' );
					printf( '<label for="wpforms-field-option-%d-state_default" class="sub-label">%s</label>', absint( $field['id'] ), esc_html__( 'Default Value', 'wpforms' ) );
				echo '</div>';
			echo '</div>';
		echo '</div>';

		// ZIP/Postal.
		$postal_placeholder = ! empty( $field['postal_placeholder'] ) ? esc_attr( $field['postal_placeholder'] ) : '';
		$postal_default     = ! empty( $field['postal_default'] ) ? esc_attr( $field['postal_default'] ) : '';
		$postal_hide        = ! empty( $field['postal_hide'] );
		$postal_visibility  = ! isset( $this->schemes[ $scheme ]['postal_label'] ) ? 'wpforms-hidden' : '';

		printf(
			'<div class="wpforms-clear wpforms-field-option-row wpforms-field-option-row-postal %1$s"
				id="wpforms-field-option-row-%2$d-postal"
				data-subfield="postal"
				data-field-id="%2$d">',
			sanitize_html_class( $postal_visibility ),
			absint( $field['id'] )
		);

		echo '<div class="wpforms-field-header">';

			$this->field_element(
				'label',
				$field,
				[
					'slug'  => 'postal_placeholder',
					'value' => esc_html__( 'ZIP / Postal', 'wpforms' ),
				]
			);

			$this->field_element(
				'toggle',
				$field,
				[
					'slug'          => 'postal_hide',
					'value'         => $postal_hide,
					'desc'          => esc_html__( 'Hide', 'wpforms' ),
					'title'         => esc_html__( 'Turn On if you want to hide this sub field.', 'wpforms' ),
					'label-left'    => true,
					'control-class' => 'wpforms-field-option-in-label-right',
					'class'         => 'wpforms-subfield-hide',
				],
				true
			);

			echo '</div>';
			echo '<div class="wpforms-field-options-columns-2 wpforms-field-options-columns">';
				echo '<div class="placeholder wpforms-field-options-column">';
					printf( '<input type="text" class="placeholder" id="wpforms-field-option-%1$d-postal_placeholder" name="fields[%1$d][postal_placeholder]" value="%2$s">', absint( $field['id'] ), esc_attr( $postal_placeholder ) );
					printf( '<label for="wpforms-field-option-%d-postal_placeholder" class="sub-label">%s</label>', absint( $field['id'] ), esc_html__( 'Placeholder', 'wpforms' ) );
				echo '</div>';
				echo '<div class="default wpforms-field-options-column">';
					printf( '<input type="text" class="default" id="wpforms-field-option-%1$d-postal_default" name="fields[%1$d][postal_default]" value="%2$s">', absint( $field['id'] ), esc_attr( $postal_default ) );
					printf( '<label for="wpforms-field-option-%d-postal_default" class="sub-label">%s</label>', absint( $field['id'] ), esc_html__( 'Default Value', 'wpforms' ) );
				echo '</div>';
			echo '</div>';
		echo '</div>';

		// Country.
		$country_placeholder = ! empty( $field['country_placeholder'] ) ? $field['country_placeholder'] : '';
		$country_hide        = ! empty( $field['country_hide'] );
		$country_visibility  = ! isset( $this->schemes[ $scheme ]['countries'] ) ? 'wpforms-hidden' : '';

		printf(
			'<div class="wpforms-clear wpforms-field-option-row wpforms-field-option-row-country %1$s"
				id="wpforms-field-option-row-%2$d-country"
				data-subfield="country"
				data-field-id="%2$d">',
			sanitize_html_class( $country_visibility ),
			absint( $field['id'] )
		);

			echo '<div class="wpforms-field-header">';

				$this->field_element(
					'label',
					$field,
					[
						'slug'  => 'country_placeholder',
						'value' => esc_html__( 'Country', 'wpforms' ),
					]
				);

				$this->field_element(
					'toggle',
					$field,
					[
						'slug'          => 'country_hide',
						'value'         => $country_hide,
						'desc'          => esc_html__( 'Hide', 'wpforms' ),
						'title'         => esc_html__( 'Turn On if you want to hide this sub field.', 'wpforms' ),
						'label-left'    => true,
						'control-class' => 'wpforms-field-option-in-label-right',
						'class'         => 'wpforms-subfield-hide',
					],
					true
				);

			echo '</div>';

			echo '<div class="wpforms-field-options-columns-2 wpforms-field-options-columns">';
				echo '<div class="placeholder wpforms-field-options-column">';
					printf( '<input type="text" class="placeholder" id="wpforms-field-option-%1$d-country_placeholder" name="fields[%1$d][country_placeholder]" value="%2$s">', absint( $field['id'] ), esc_attr( $country_placeholder ) );
					printf( '<label for="wpforms-field-option-%d-country_placeholder" class="sub-label">%s</label>', absint( $field['id'] ), esc_html__( 'Placeholder', 'wpforms' ) );
				echo '</div>';
				echo '<div class="default wpforms-field-options-column">';
					$this->subfield_default( $field, 'country', 'countries' );
					printf( '<label for="wpforms-field-option-%d-country_default" class="sub-label">%s</label>', absint( $field['id'] ), esc_html__( 'Default Value', 'wpforms' ) );
				echo '</div>';
			echo '</div>';
		echo '</div>';

		// Custom CSS classes.
		$this->field_option( 'css', $field );

		// Hide label.
		$this->field_option( 'label_hide', $field );

		// Hide sublabel.
		$this->field_option( 'sublabel_hide', $field );

		// Options close markup.
		$this->field_option(
			'advanced-options',
			$field,
			[
				'markup' => 'close',
			]
		);
	}

	/**
	 * Field preview inside the builder.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field Field data.
	 */
	public function field_preview( $field ) {

		// Define data.
		$address1_placeholder = ! empty( $field['address1_placeholder'] ) ? $field['address1_placeholder'] : '';
		$address1_default     = ! empty( $field['address1_default'] ) ? $field['address1_default'] : '';
		$address2_placeholder = ! empty( $field['address2_placeholder'] ) ? $field['address2_placeholder'] : '';
		$address2_default     = ! empty( $field['address2_default'] ) ? $field['address2_default'] : '';
		$address2_hide        = ! empty( $field['address2_hide'] ) ? 'wpforms-hide' : '';
		$city_placeholder     = ! empty( $field['city_placeholder'] ) ? $field['city_placeholder'] : '';
		$city_default         = ! empty( $field['city_default'] ) ? $field['city_default'] : '';
		$postal_placeholder   = ! empty( $field['postal_placeholder'] ) ? $field['postal_placeholder'] : '';
		$postal_default       = ! empty( $field['postal_default'] ) ? $field['postal_default'] : '';
		$postal_hide          = ! empty( $field['postal_hide'] ) ? 'wpforms-hide' : '';
		$country_hide         = ! empty( $field['country_hide'] ) ? 'wpforms-hide' : '';
		$format               = ! empty( $field['format'] ) ? $field['format'] : 'us';
		$scheme_selected      = ! empty( $field['scheme'] ) ? $field['scheme'] : $format;

		// Label.
		$this->field_preview_option( 'label', $field );

		// Field elements.
		foreach ( $this->schemes as $slug => $scheme ) {

			$address1_label = isset( $scheme['address1_label'] ) ? $scheme['address1_label'] : '';
			$address2_label = isset( $scheme['address2_label'] ) ? $scheme['address2_label'] : '';
			$city_label     = isset( $scheme['city_label'] ) ? $scheme['city_label'] : '';
			$state_label    = isset( $scheme['state_label'] ) ? $scheme['state_label'] : '';
			$postal_label   = isset( $scheme['postal_label'] ) ? $scheme['postal_label'] : '';
			$country_label  = isset( $scheme['country_label'] ) ? $scheme['country_label'] : '';

			$is_active_scheme  = $slug === $scheme_selected;
			$scheme_hide_class = ! $is_active_scheme ? 'wpforms-hide' : '';

			$state_placeholder   = ! empty( $field['state_placeholder'] ) ? $field['state_placeholder'] : '';
			$state_default       = $is_active_scheme && ! empty( $field['state_default'] ) ? $field['state_default'] : '';
			$country_placeholder = ! empty( $field['country_placeholder'] ) ? $field['country_placeholder'] : '';
			$country_default     = $is_active_scheme && ! empty( $field['country_default'] ) ? $field['country_default'] : '';

			// Wrapper.
			printf(
				'<div class="wpforms-address-scheme wpforms-address-scheme-%s %s">',
				wpforms_sanitize_classes( $slug ),
				wpforms_sanitize_classes( $scheme_hide_class )
			);

			// Row 1 - Address Line 1.
			printf(
				'<div class="wpforms-field-row wpforms-address-1">
					<input type="text" placeholder="%s" value="%s" readonly>
					<label class="wpforms-sub-label">%s</label>
				</div>',
				esc_attr( $address1_placeholder ),
				esc_attr( $address1_default ),
				esc_html( $address1_label )
			);

			// Row 2 - Address Line 2.
			printf(
				'<div class="wpforms-field-row wpforms-address-2 %s">
					<input type="text" placeholder="%s" value="%s" readonly>
					<label class="wpforms-sub-label">%s</label>
				</div>',
				wpforms_sanitize_classes( $address2_hide ),
				esc_attr( $address2_placeholder ),
				esc_attr( $address2_default ),
				esc_html( $address2_label )
			);

			// Row 3 - City & State.
			echo '<div class="wpforms-field-row">';

			// City.
			printf(
				'<div class="wpforms-city wpforms-one-half ">
					<input type="text" placeholder="%s" value="%s" readonly>
					<label class="wpforms-sub-label">%s</label>
				</div>',
				esc_attr( $city_placeholder ),
				esc_attr( $city_default ),
				esc_html( $city_label )
			);

			// State / Providence / Region.
			echo '<div class="wpforms-state wpforms-one-half last">';

				if ( isset( $scheme['states'] ) && empty( $scheme['states'] ) ) {

					// State text input.
					printf( '<input type="text" placeholder="%s" value="%s" readonly>', esc_attr( $state_placeholder ), esc_attr( $state_default ) );

				} elseif ( ! empty( $scheme['states'] ) && is_array( $scheme['states'] ) ) {

					$state_option = $this->dropdown_empty_value( $scheme['state_label'] );

					if ( ! empty( $state_placeholder ) ) {
						$state_option = $state_placeholder;
					}

					if ( $is_active_scheme && ! empty( $state_default ) ) {
						$state_option = $scheme['states'][ $state_default ];
					}

					// State select.
					printf( '<select readonly> <option class="placeholder" selected>%s</option> </select>', esc_html( $state_option ) );
				}

			printf( '<label class="wpforms-sub-label">%s</label>', esc_html( $state_label ) );
			echo '</div>';

			// End row 3 - City & State.
			echo '</div>';

			// Row 4 - Zip & Country.
			echo '<div class="wpforms-field-row">';

			// ZIP / Postal.
			printf(
				'<div class="wpforms-postal wpforms-one-half %s">
					<input type="text" placeholder="%s" value="%s" readonly>
					<label class="wpforms-sub-label">%s</label>
				</div>',
				wpforms_sanitize_classes( $postal_hide ),
				esc_attr( $postal_placeholder ),
				esc_attr( $postal_default ),
				esc_html( $postal_label )
			);

			// Country.
			printf( '<div class="wpforms-country wpforms-one-half last %s">', sanitize_html_class( $country_hide ) );

				if ( isset( $scheme['countries'] ) && empty( $scheme['countries'] ) ) {

					// Country text input.
					printf( '<input type="text" placeholder="%s" value="%s" readonly>', esc_attr( $country_placeholder ), esc_attr( $country_default ) );

				} elseif ( ! empty( $scheme['countries'] ) && is_array( $scheme['countries'] ) ) {

					$country_option = $this->dropdown_empty_value( $scheme['country_label'] );

					if ( ! empty( $country_placeholder ) ) {
						$country_option = $country_placeholder;
					}

					if ( $is_active_scheme && ! empty( $country_default ) ) {
						$country_option = $scheme['countries'][ $country_default ];
					}

					// Country select.
					printf( '<select readonly><option class="placeholder" selected>%s</option></select>', esc_html( $country_option ) );
				}

			printf( '<label class="wpforms-sub-label">%s</label>', esc_html( $country_label ) );
			echo '</div>';

			// End row 4 - Zip & Country.
			echo '</div>';

			// End wrapper.
			echo '</div>';
		}

		// Description.
		$this->field_preview_option( 'description', $field );
	}

	/**
	 * Field display on the form front-end.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field      Field data and settings.
	 * @param array $deprecated Deprecated field attributes. Use field properties instead.
	 * @param array $form_data  Form data and settings.
	 */
	public function field_display( $field, $deprecated, $form_data ) {

		// Define data.
		$format   = ! empty( $field['format'] ) ? esc_attr( $field['format'] ) : 'us';
		$scheme   = ! empty( $field['scheme'] ) ? esc_attr( $field['scheme'] ) : $format;
		$address1 = ! empty( $field['properties']['inputs']['address1'] ) ? $field['properties']['inputs']['address1'] : [];
		$address2 = ! empty( $field['properties']['inputs']['address2'] ) ? $field['properties']['inputs']['address2'] : [];
		$city     = ! empty( $field['properties']['inputs']['city'] ) ? $field['properties']['inputs']['city'] : [];
		$state    = ! empty( $field['properties']['inputs']['state'] ) ? $field['properties']['inputs']['state'] : [];
		$postal   = ! empty( $field['properties']['inputs']['postal'] ) ? $field['properties']['inputs']['postal'] : [];
		$country  = ! empty( $field['properties']['inputs']['country'] ) ? $field['properties']['inputs']['country'] : [];

		// Row wrapper.
		echo '<div class="wpforms-field-row wpforms-field-' . sanitize_html_class( $field['size'] ) . '">';

			// Address Line 1.
			echo '<div ' . wpforms_html_attributes( false, $address1['block'] ) . '>';
				$this->field_display_sublabel( 'address1', 'before', $field );
				printf(
					'<input type="text" %s %s>',
					wpforms_html_attributes( $address1['id'], $address1['class'], $address1['data'], $address1['attr'] ),
					! empty( $address1['required'] ) ? 'required' : ''
				);
				$this->field_display_sublabel( 'address1', 'after', $field );
				$this->field_display_error( 'address1', $field );
			echo '</div>';

		echo '</div>';

		if ( empty( $address2['hidden'] ) ) {

			// Row wrapper.
			echo '<div class="wpforms-field-row wpforms-field-' . sanitize_html_class( $field['size'] ) . '">';

				// Address Line 2.
				echo '<div ' . wpforms_html_attributes( false, $address2['block'] ) . '>';
					$this->field_display_sublabel( 'address2', 'before', $field );
					printf(
						'<input type="text" %s %s>',
						wpforms_html_attributes( $address2['id'], $address2['class'], $address2['data'], $address2['attr'] ),
						! empty( $address2['required'] ) ? 'required' : ''
					);
					$this->field_display_sublabel( 'address2', 'after', $field );
					$this->field_display_error( 'address2', $field );
				echo '</div>';

			echo '</div>';
		}

		// Row wrapper.
		echo '<div class="wpforms-field-row wpforms-field-' . sanitize_html_class( $field['size'] ) . '">';

			// City.
			echo '<div ' . wpforms_html_attributes( false, $city['block'] ) . '>';
				$this->field_display_sublabel( 'city', 'before', $field );
				printf(
					'<input type="text" %s %s>',
					wpforms_html_attributes( $city['id'], $city['class'], $city['data'], $city['attr'] ),
					! empty( $city['required'] ) ? 'required' : ''
				);
				$this->field_display_sublabel( 'city', 'after', $field );
				$this->field_display_error( 'city', $field );
			echo '</div>';

			// State.
			if ( isset( $this->schemes[ $scheme ]['states'] ) && isset( $state['options'] ) ) {

				echo '<div ' . wpforms_html_attributes( false, $state['block'] ) . '>';
					$this->field_display_sublabel( 'state', 'before', $field );
					if ( empty( $state['options'] ) ) {
						printf(
							'<input type="text" %s %s>',
							wpforms_html_attributes( $state['id'], $state['class'], $state['data'], $state['attr'] ),
							! empty( $state['required'] ) ? 'required' : ''
						);
					} else {
						printf(
							'<select %s %s>',
							wpforms_html_attributes( $state['id'], $state['class'], $state['data'], $state['attr'] ),
							! empty( $state['required'] ) ? 'required' : ''
						);
							if ( ! empty( $state['attr']['placeholder'] ) && empty( $state['attr']['value'] ) ) {
								printf( '<option class="placeholder" value="" selected disabled>%s</option>', esc_html( $state['attr']['placeholder'] ) );
							}
							foreach ( $state['options'] as $state_key => $state_label ) {
								printf(
									'<option value="%s" %s>%s</option>',
									esc_attr( $state_key ),
									selected( ! empty( $state['attr']['value'] ) && ( $state_key === $state['attr']['value'] || $state_label === $state['attr']['value'] ), true, false ),
									esc_html( $state_label )
								);
							}
						echo '</select>';
					}
					$this->field_display_sublabel( 'state', 'after', $field );
					$this->field_display_error( 'state', $field );
				echo '</div>';
			}

		echo '</div>';

		// Only render this row if we have at least one of the items.
		if ( empty( $country['hidden'] ) || empty( $postal['hidden'] ) ) {

			// Row wrapper.
			echo '<div class="wpforms-field-row wpforms-field-' . sanitize_html_class( $field['size'] ) . '">';

				// Postal.
				if ( empty( $postal['hidden'] ) ) {

					echo '<div ' . wpforms_html_attributes( false, $postal['block'] ) . '>';
						$this->field_display_sublabel( 'postal', 'before', $field );
						printf(
							'<input type="text" %s %s>',
							wpforms_html_attributes( $postal['id'], $postal['class'], $postal['data'], $postal['attr'] ),
							! empty( $postal['required'] ) ? 'required' : ''
						);
						$this->field_display_sublabel( 'postal', 'after', $field );
						$this->field_display_error( 'postal', $field );
					echo '</div>';
				}

				// Country.
				if ( isset( $country['options'] ) && empty( $country['hidden'] ) ) {

					echo '<div ' . wpforms_html_attributes( false, $country['block'] ) . '>';
						$this->field_display_sublabel( 'country', 'before', $field );
						if ( empty( $country['options'] ) ) {
							printf(
								'<input type="text" %s %s>',
								wpforms_html_attributes( $country['id'], $country['class'], $country['data'], $country['attr'] ),
								! empty( $country['required'] ) ? 'required' : ''
							);
						} else {
							printf( '<select %s %s>',
								wpforms_html_attributes( $country['id'], $country['class'], $country['data'], $country['attr'] ),
								! empty( $country['required'] ) ? 'required' : ''
							);
								if ( ! empty( $country['attr']['placeholder'] ) && empty( $country['attr']['value'] ) ) {
									printf( '<option class="placeholder" value="" selected disabled>%s</option>', esc_html( $country['attr']['placeholder'] ) );
								}
								foreach ( $country['options'] as $country_key => $country_label ) {
									printf(
										'<option value="%s" %s>%s</option>',
										esc_attr( $country_key ),
										selected( ! empty( $country['attr']['value'] ) && ( $country_key === $country['attr']['value'] || $country_label === $country['attr']['value'] ), true, false ),
										esc_html( $country_label )
									);
								}
							echo '</select>';
						}
						$this->field_display_sublabel( 'country', 'after', $field );
						$this->field_display_error( 'country', $field );
					echo '</div>';
				}

			echo '</div>';
		}
	}

	/**
	 * Validate field on form submit.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $field_id     Field ID.
	 * @param array $field_submit Submitted field values.
	 * @param array $form_data    Form data and settings.
	 */
	public function validate( $field_id, $field_submit, $form_data ) {

		$form_id  = $form_data['id'];
		$required = wpforms_get_required_label();
		$scheme   = ! empty( $form_data['fields'][ $field_id ]['scheme'] ) ? $form_data['fields'][ $field_id ]['scheme'] : $form_data['fields'][ $field_id ]['format'];

		// Extended required validation needed for the different address fields.
		if ( ! empty( $form_data['fields'][ $field_id ]['required'] ) ) {

			// Require Address Line 1.
			if ( isset( $field_submit['address1'] ) && wpforms_is_empty_string( $field_submit['address1'] ) ) {
				wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['address1'] = $required;
			}

			// Require City.
			if ( isset( $field_submit['city'] ) && wpforms_is_empty_string( $field_submit['city'] ) ) {
				wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['city'] = $required;
			}

			// Require ZIP/Postal.
			if ( isset( $this->schemes[ $scheme ]['postal_label'], $field_submit['postal'] ) && empty( $form_data['fields'][ $field_id ]['postal_hide'] ) && wpforms_is_empty_string( $field_submit['postal'] ) ) {
				wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['postal'] = $required;
			}

			// Required State.
			if ( isset( $this->schemes[ $scheme ]['states'], $field_submit['state'] ) && wpforms_is_empty_string( $field_submit['state'] ) ) {
				wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['state'] = $required;
			}

			// Required Country.
			if ( isset( $this->schemes[ $scheme ]['countries'], $field_submit['country'] ) && empty( $form_data['fields'][ $field_id ]['country_hide'] ) && wpforms_is_empty_string( $field_submit['country'] ) ) {
				wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['country'] = $required;
			}
		}
	}

	/**
	 * Format field.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $field_id     Field ID.
	 * @param array $field_submit Submitted field values.
	 * @param array $form_data    Form data and settings.
	 */
	public function format( $field_id, $field_submit, $form_data ) {

		$name     = isset( $form_data['fields'][ $field_id ]['label'] ) && ! wpforms_is_empty_string( $form_data['fields'][ $field_id ]['label'] ) ? $form_data['fields'][ $field_id ]['label'] : '';
		$address1 = isset( $field_submit['address1'] ) && ! wpforms_is_empty_string( $field_submit['address1'] ) ? $field_submit['address1'] : '';
		$address2 = isset( $field_submit['address2'] ) && ! wpforms_is_empty_string( $field_submit['address2'] ) ? $field_submit['address2'] : '';
		$city     = isset( $field_submit['city'] ) && ! wpforms_is_empty_string( $field_submit['city'] ) ? $field_submit['city'] : '';
		$state    = isset( $field_submit['state'] ) && ! wpforms_is_empty_string( $field_submit['state'] ) ? $field_submit['state'] : '';
		$postal   = isset( $field_submit['postal'] ) && ! wpforms_is_empty_string( $field_submit['postal'] ) ? $field_submit['postal'] : '';

		// If scheme type is 'us', define US as a country field value.
		if ( ! empty( $form_data['fields'][ $field_id ]['scheme'] ) && $form_data['fields'][ $field_id ]['scheme'] === 'us' ) {
			$country = 'US';
		} else {
			$country = isset( $field_submit['country'] ) && ! wpforms_is_empty_string( $field_submit['country'] ) ? $field_submit['country'] : '';
		}

		$value  = '';
		$value .= ! wpforms_is_empty_string( $address1 ) ? "$address1\n" : '';
		$value .= ! wpforms_is_empty_string( $address2 ) ? "$address2\n" : '';

		if ( ! wpforms_is_empty_string( $city ) && ! wpforms_is_empty_string( $state ) ) {
			$value .= "$city, $state\n";
		} elseif ( ! wpforms_is_empty_string( $state ) ) {
			$value .= "$state\n";
		} elseif ( ! wpforms_is_empty_string( $city ) ) {
			$value .= "$city\n";
		}
		$value .= ! wpforms_is_empty_string( $postal ) ? "$postal\n" : '';
		$value .= ! wpforms_is_empty_string( $country ) ? "$country\n" : '';
		$value  = wpforms_sanitize_textarea_field( $value );

		if ( wpforms_is_empty_string( $city ) && wpforms_is_empty_string( $address1 ) ) {
			$value = '';
		}

		wpforms()->get( 'process' )->fields[ $field_id ] = [
			'name'     => sanitize_text_field( $name ),
			'value'    => $value,
			'id'       => absint( $field_id ),
			'type'     => $this->type,
			'address1' => sanitize_text_field( $address1 ),
			'address2' => sanitize_text_field( $address2 ),
			'city'     => sanitize_text_field( $city ),
			'state'    => sanitize_text_field( $state ),
			'postal'   => sanitize_text_field( $postal ),
			'country'  => sanitize_text_field( $country ),
		];
	}

	/**
	 * Get field name for ajax error message.
	 *
	 * @since 1.6.3
	 *
	 * @param string $name  Field name for error triggered.
	 * @param array  $field Field settings.
	 * @param array  $props List of properties.
	 * @param string $error Error message.
	 *
	 * @return string
	 */
	public function ajax_error_field_name( $name, $field, $props, $error ) {

		if ( ! isset( $field['type'] ) || 'address' !== $field['type'] ) {
			return $name;
		}
		if ( ! isset( $field['scheme'] ) ) {
			return $name;
		}
		if ( 'us' === $field['scheme'] ) {
			$input = isset( $props['inputs']['postal'] ) ? $props['inputs']['postal'] : [];
		} else {
			$input = isset( $props['inputs']['country'] ) ? $props['inputs']['country'] : [];
		}

		return isset( $input['attr']['name'] ) ? $input['attr']['name'] : $name;
	}

	/**
	 * Customize format for HTML display.
	 *
	 * @since 1.7.6
	 *
	 * @param string $val       Field value.
	 * @param array  $field     Field data.
	 * @param array  $form_data Form data and settings.
	 * @param string $context   Value display context.
	 *
	 * @return string
	 */
	public function html_field_value( $val, $field, $form_data = [], $context = '' ) {

		if ( empty( $field['value'] ) || $field['type'] !== $this->type ) {
			return $val;
		}

		$scheme = isset( $form_data['fields'][ $field['id'] ]['scheme'] ) ? $form_data['fields'][ $field['id'] ]['scheme'] : 'us';

		// In the US it is common to use abbreviations for both the country and states, e.g. New York, NY.
		if ( $scheme === 'us' ) {
			return $val;
		}

		$allowed_contexts = [
			'entry-table',
			'entry-single',
			'entry-preview',
		];

		/**
		 * Allows filtering contexts in which the value should be transformed for display.
		 *
		 * Available contexts:
		 * - `entry-table`   - entries list table,
		 * - `entry-single`  - view entry, edit entry (non-editable field display), print preview,
		 * - `email-html`    - entry email notification,
		 * - `entry-preview` - entry preview on the frontend,
		 * - `smart-tag`     - smart tag in various places (Confirmations, Notifications, integrations etc).
		 *
		 * By default, `email-html` and `smart-tag` contexts are ignored. The data in these contexts
		 * can be used for automation and external data processing, so we keep the original format
		 * intact for backwards compatibility.
		 *
		 * @since 1.7.6
		 *
		 * @param array $allowed_contexts Contexts whitelist.
		 * @param array $field            Field data.
		 * @param array $form_data        Form data and settings.
		 */
		$allowed_contexts = (array) apply_filters( 'wpforms_field_address_html_field_value_allowed_contexts', $allowed_contexts, $field, $form_data );

		return in_array( $context, $allowed_contexts, true ) ?
			$this->transform_value_for_display( $scheme, $field, $val ) :
			$val;
	}

	/**
	 * Transform the value for display context.
	 *
	 * @since 1.7.6
	 *
	 * @param string $scheme The scheme used in the field.
	 * @param array  $field  Field data.
	 * @param string $value  Value to transform.
	 *
	 * @return string
	 */
	private function transform_value_for_display( $scheme, $field, $value ) {

		$transform = [
			'state'   => 'states',
			'country' => 'countries',
		];

		foreach ( $transform as $singular => $plural ) {

			$collection = isset( $this->schemes[ $scheme ][ $plural ] ) ? $this->schemes[ $scheme ][ $plural ] : '';

			// The 'countries' or 'states' is array and the value exists as array key.
			if ( is_array( $collection ) && array_key_exists( $field[ $singular ], $collection ) ) {
				$value = str_replace( $field[ $singular ], $collection[ $field[ $singular ] ], $value );
			}
		}

		return $value;
	}

	/**
	 * Output "Default" option fields for State/Country subfields.
	 *
	 * Default value should be set only for the scheme it belongs to.
	 *
	 * @since 1.8.0
	 *
	 * @param array  $field         Address field data.
	 * @param string $subfield_slug Subfield slug, either `state` or `country`.
	 * @param string $subfield_key  Subfield key in `$scheme` data, either `states` or `countries`.
	 */
	private function subfield_default( $field, $subfield_slug, $subfield_key ) {

		// Scheme or default value may not be set yet.
		$active_scheme = ! empty( $field['scheme'] ) ? $field['scheme'] : 'us';
		$default_value = ! empty( $field[ "{$subfield_slug}_default" ] ) ? $field[ "{$subfield_slug}_default" ] : '';

		foreach ( $this->schemes as $scheme_slug => $scheme_data ) {

			$subfield_label   = empty( $scheme_data[ $subfield_slug . '_label' ] ) ? ucfirst( $subfield_slug ) : $scheme_data[ $subfield_slug . '_label' ];
			$empty_value      = $this->dropdown_empty_value( $subfield_label );
			$is_active_scheme = $scheme_slug === $active_scheme;

			// If scheme contains an array of values, we display a select dropdown. Otherwise, text input.
			if ( ! empty( $scheme_data[ $subfield_key ] ) && is_array( $scheme_data[ $subfield_key ] ) ) {

				$options_escaped = sprintf( '<option value="">%s</option>', esc_html( $empty_value ) );

				foreach ( $scheme_data[ $subfield_key ] as $value => $label ) {
					$options_escaped .= sprintf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $value ),
						$is_active_scheme ? selected( $default_value, $value, false ) : '',
						esc_html( $label )
					);
				}

				if ( $is_active_scheme ) {
					printf(
						'<select class="default" id="wpforms-field-option-%1$d-%2$s_default" name="fields[%1$d][%2$s_default]" data-scheme="%3$s">%4$s</select>',
						absint( $field['id'] ),
						esc_attr( $subfield_slug ),
						esc_attr( $scheme_slug ),
						$options_escaped // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);

					continue;
				}

				printf(
					'<select class="default wpforms-hidden-strict" id="" name="" data-scheme="%s">%s</select>',
					esc_attr( $scheme_slug ),
					$options_escaped // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);

				continue;
			}

			if ( $is_active_scheme ) {
				printf(
					'<input type="text" class="default" id="wpforms-field-option-%1$d-%2$s_default" name="fields[%1$d][%2$s_default]" value="%3$s" data-scheme="%4$s">',
					absint( $field['id'] ),
					esc_attr( $subfield_slug ),
					esc_attr( $default_value ),
					esc_attr( $scheme_slug )
				);

				continue;
			}

			printf(
				'<input type="text" class="default wpforms-hidden-strict" id="" name="" value="" data-scheme="%s">',
				esc_attr( $scheme_slug )
			);
		}
	}

	/**
	 * Get select dropdown "placeholder" option which is displayed if nothing is selected.
	 *
	 * @since 1.8.0
	 *
	 * @param string $name Select field name, can be lowercase or uppercase.
	 *
	 * @return string
	 */
	private function dropdown_empty_value( $name ) {

		return sprintf( /* translators: %s - subfield name, e.g. state, country. */
			__( '--- Select %s ---', 'wpforms' ),
			$name
		);
	}
}

new WPForms_Field_Address();
