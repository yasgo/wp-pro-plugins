<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Date / Time field.
 *
 * @since 1.0.0
 */
class WPForms_Field_Date_Time extends WPForms_Field {

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Define field type information.
		$this->name  = esc_html__( 'Date / Time', 'wpforms' );
		$this->type  = 'date-time';
		$this->icon  = 'fa-calendar-o';
		$this->order = 80;
		$this->group = 'fancy';

		$this->defaults = [
			'date_placeholder'            => '',
			'date_format'                 => 'm/d/Y',
			'date_type'                   => 'datepicker',
			'time_placeholder'            => '',
			'time_format'                 => 'g:i A',
			'time_interval'               => '30',
			'date_limit_days_sun'         => '0',
			'date_limit_days_mon'         => '1',
			'date_limit_days_tue'         => '1',
			'date_limit_days_wed'         => '1',
			'date_limit_days_thu'         => '1',
			'date_limit_days_fri'         => '1',
			'date_limit_days_sat'         => '0',
			'time_limit_hours_start_hour' => '09',
			'time_limit_hours_start_min'  => '00',
			'time_limit_hours_start_ampm' => 'am',
			'time_limit_hours_end_hour'   => '06',
			'time_limit_hours_end_min'    => '00',
			'time_limit_hours_end_ampm'   => 'pm',
		];

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.8.1
	 */
	private function hooks() {

		// Set custom option wrapper classes.
		add_filter( 'wpforms_builder_field_option_class', [ $this, 'field_option_class' ], 10, 2 );

		// Define additional field properties.
		add_filter( "wpforms_field_properties_{$this->type}", [ $this, 'field_properties' ], 5, 3 );
	}

	/**
	 * Define additional field properties.
	 *
	 * @since 1.5.0
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Field settings.
	 * @param array $form_data  Form data and settings.
	 *
	 * @return array
	 */
	public function field_properties( $properties, $field, $form_data ) {

		$limits_available = (bool) apply_filters( 'wpforms_datetime_limits_available', true );

		// Remove primary input.
		unset( $properties['inputs']['primary'] );

		// Define data.
		$form_id        = absint( $form_data['id'] );
		$field_id       = absint( $field['id'] );
		$field_format   = ! empty( $field['format'] ) ? $field['format'] : 'date-time';
		$field_required = ! empty( $field['required'] ) ? 'required' : '';
		$field_size_cls = 'wpforms-field-' . ( ! empty( $field['size'] ) ? $field['size'] : 'medium' );

		$date_format      = ! empty( $field['date_format'] ) ? $field['date_format'] : $this->defaults['date_format'];
		$date_placeholder = ! empty( $field['date_placeholder'] ) ? $field['date_placeholder'] : $this->defaults['date_placeholder'];
		$date_type        = ! empty( $field['date_type'] ) ? $field['date_type'] : $this->defaults['date_type'];

		$time_placeholder = ! empty( $field['time_placeholder'] ) ? $field['time_placeholder'] : $this->defaults['time_placeholder'];
		$time_format      = ! empty( $field['time_format'] ) ? $field['time_format'] : $this->defaults['time_format'];
		$time_interval    = ! empty( $field['time_interval'] ) ? $field['time_interval'] : $this->defaults['time_interval'];

		// Backwards compatibility with old datepicker format.
		if ( $date_format === 'mm/dd/yyyy' ) {
			$date_format = 'm/d/Y';
		} elseif ( $date_format === 'dd/mm/yyyy' ) {
			$date_format = 'd/m/Y';
		} elseif ( $date_format === 'mmmm d, yyyy' ) {
			$date_format = 'F j, Y';
		}

		$default_date = [
			'container' => [
				'attr'  => [],
				'class' => [
					'wpforms-field-row-block',
					"wpforms-date-type-{$date_type}",
				],
				'data'  => [],
				'id'    => '',
			],
			'attr'      => [
				'name'        => "wpforms[fields][{$field_id}][date]",
				'value'       => '',
				'placeholder' => $date_placeholder,
			],
			'sublabel'  => [
				'hidden' => ! empty( $field['sublabel_hide'] ),
				'value'  => esc_html__( 'Date', 'wpforms' ),
			],
			'class'     => [
				'wpforms-field-date-time-date',
				'wpforms-datepicker',
				! empty( $field_required ) ? 'wpforms-field-required' : '',
				! empty( wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['date'] ) ? 'wpforms-error' : '',
			],
			'data'      => [
				'date-format' => $date_format,
			],
			'id'        => "wpforms-{$form_id}-field_{$field_id}",
			'required'  => $field_required,
		];

		// Limit Days.
		if ( $limits_available && ! empty( $field['date_limit_days'] ) && $date_type === 'datepicker' ) {
			$days       = [ 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' ];
			$limit_days = [];

			foreach ( $days as $day ) {
				if ( ! empty( $field[ 'date_limit_days_' . $day ] ) ) {
					$limit_days[] = $day;
				}
			}
			$default_date['data']['limit-days'] = implode( ',', $limit_days );
		}
		if ( $limits_available && $date_type === 'datepicker' ) {
			$default_date['data']['disable-past-dates'] = ! empty( $field['date_disable_past_dates'] ) ? '1' : '0';
		}

		$default_time = [
			'container' => [
				'attr'  => [],
				'class' => [
					'wpforms-field-row-block',
				],
				'data'  => [],
				'id'    => '',
			],
			'attr'      => [
				'name'        => "wpforms[fields][{$field_id}][time]",
				'value'       => '',
				'placeholder' => $time_placeholder,
			],
			'sublabel'  => [
				'hidden' => ! empty( $field['sublabel_hide'] ),
				'value'  => esc_html__( 'Time', 'wpforms' ),
			],
			'class'     => [
				'wpforms-field-date-time-time',
				'wpforms-timepicker',
				! empty( $field_required ) ? 'wpforms-field-required' : '',
				! empty( wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['time'] ) ? 'wpforms-error' : '',
			],
			'data'      => [
				'time-format' => $time_format,
				'step'        => $time_interval,
			],
			'id'        => "wpforms-{$form_id}-field_{$field_id}-time",
			'required'  => $field_required,
		];

		// Determine time format validation rule only for default (embedded) time formats.
		if ( in_array( $time_format, [ 'H:i', 'H:i A' ], true ) ) {
			$default_time['data']['rule-time24h'] = 'true';
		} elseif ( $time_format === 'g:i A' ) {
			$default_time['data']['rule-time12h'] = 'true';
		}

		if ( ! empty( $field['time_limit_hours'] ) && $limits_available ) {
			$default_time['data']['min-time']  = ! empty( $field['time_limit_hours_start_hour'] ) ? $field['time_limit_hours_start_hour'] : $this->defaults['time_limit_hours_start_hour'];
			$default_time['data']['min-time'] .= ':';
			$default_time['data']['min-time'] .= ! empty( $field['time_limit_hours_start_min'] ) ? $field['time_limit_hours_start_min'] : $this->defaults['time_limit_hours_start_min'];

			$default_time['data']['max-time']  = ! empty( $field['time_limit_hours_end_hour'] ) ? $field['time_limit_hours_end_hour'] : $this->defaults['time_limit_hours_end_hour'];
			$default_time['data']['max-time'] .= ':';
			$default_time['data']['max-time'] .= ! empty( $field['time_limit_hours_end_min'] ) ? $field['time_limit_hours_end_min'] : $this->defaults['time_limit_hours_end_min'];

			// If the format contains `g` or `h`, then this is 12 hours format.
			if ( preg_match( '/[gh]/', $time_format ) ) {
				$default_time['data']['min-time'] .= ! empty( $field['time_limit_hours_start_ampm'] ) ? $field['time_limit_hours_start_ampm'] : $this->defaults['time_limit_hours_start_ampm'];
				$default_time['data']['max-time'] .= ! empty( $field['time_limit_hours_end_ampm'] ) ? $field['time_limit_hours_end_ampm'] : $this->defaults['time_limit_hours_end_ampm'];
			}

			// Limit Hours validation should apply only for default (embedded) time formats.
			if ( in_array( $time_format, [ 'g:i A', 'H:i' ], true ) ) {
				$default_time['data']['rule-time-limit'] = 'true';
			}
		}

		switch ( $field_format ) {
			case 'date-time':
				$properties['input_container'] = [
					'id'    => '',
					'class' => [
						'wpforms-field-row',
						$field_size_cls,
					],
					'data'  => [],
					'attr'  => [],
				];

				$properties['inputs']['date'] = $default_date;
				$properties['inputs']['time'] = $default_time;
				break;

			case 'date':
				$properties['inputs']['date']            = $default_date;
				$properties['inputs']['date']['class'][] = $field_size_cls;
				break;

			case 'time':
				$properties['inputs']['time']            = $default_time;
				$properties['inputs']['time']['class'][] = $field_size_cls;
				break;
		}

		if ( $field['date_type'] === 'dropdown' ) {
			$properties['inputs']['date']['dropdown_wrap'] = [
				'attr'  => [],
				'class' => [
					'wpforms-field-date-dropdown-wrap',
					$field_size_cls,
				],
				'data'  => [],
				'id'    => '',
			];
		}

		return $properties;
	}

	/**
	 * @inheritdoc
	 */
	protected function get_field_populated_single_property_value( $raw_value, $input, $properties, $field ) {

		$properties   = parent::get_field_populated_single_property_value( $raw_value, $input, $properties, $field );
		$date_type    = ! empty( $field['date_type'] ) ? $field['date_type'] : 'datepicker';
		$field_format = ! empty( $field['format'] ) ? $field['format'] : 'date-time';

		// Ordinary date/time fields, without dropdown, were already processed by this time.
		if (
			'time' === $field_format ||
			'dropdown' !== $date_type
		) {
			return $properties;
		}

		$subinput = explode( '_', $input );

		// Only date subfield supports this extra logic.
		if (
			empty( $subinput ) ||
			'date' !== $subinput[0] ||
			empty( $subinput[1] )
		) {
			return $properties;
		}

		$properties['inputs']['date']['default'][ sanitize_key( $subinput[1] ) ] = (int) $raw_value;

		return $properties;
	}

	/**
	 * Field options panel inside the builder.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field Field data and settings.
	 */
	public function field_options( $field ) {

		/*
		 * Basic field options
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

		// Format option.
		$format        = ! empty( $field['format'] ) ? esc_attr( $field['format'] ) : 'date-time';
		$format_label  = $this->field_element(
			'label',
			$field,
			[
				'slug'    => 'format',
				'value'   => esc_html__( 'Format', 'wpforms' ),
				'tooltip' => esc_html__( 'Select format for the date field.', 'wpforms' ),
			],
			false
		);
		$format_select = $this->field_element(
			'select',
			$field,
			[
				'slug'    => 'format',
				'value'   => $format,
				'options' => [
					'date-time' => esc_html__( 'Date and Time', 'wpforms' ),
					'date'      => esc_html__( 'Date', 'wpforms' ),
					'time'      => esc_html__( 'Time', 'wpforms' ),
				],
			],
			false
		);
		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'format',
				'content' => $format_label . $format_select,
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
		 * Advanced field options
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

		// Custom options.
		echo '<div class="format-selected-' . $format . ' format-selected">';

			// Date.
			$date_placeholder = ! empty( $field['date_placeholder'] ) ? $field['date_placeholder'] : '';
			$date_format      = ! empty( $field['date_format'] ) ? esc_attr( $field['date_format'] ) : 'm/d/Y';
			$date_type        = ! empty( $field['date_type'] ) ? esc_attr( $field['date_type'] ) : 'datepicker';
			// Backwards compatibility with old datepicker format.
			if ( 'mm/dd/yyyy' === $date_format ) {
				$date_format = 'm/d/Y';
			} elseif ( 'dd/mm/yyyy' === $date_format ) {
				$date_format = 'd/m/Y';
			} elseif ( 'mmmm d, yyyy' === $date_format ) {
				$date_format = 'F j, Y';
			}

			$date_formats = wpforms_date_formats();

			printf(
				'<div class="wpforms-clear wpforms-field-option-row wpforms-field-option-row-date no-gap" id="wpforms-field-option-row-%d-date" data-subfield="date" data-field-id="%d">',
				esc_attr( $field['id'] ),
				esc_attr( $field['id'] )
			);
			$this->field_element(
				'label',
				$field,
				[
					'slug'    => 'date_placeholder',
					'value'   => esc_html__( 'Date', 'wpforms' ),
					'tooltip' => esc_html__( 'Advanced date options.', 'wpforms' ),
				]
			);

			echo '<div class="wpforms-field-options-columns-2 wpforms-field-options-columns">';
				echo '<div class="type wpforms-field-options-column">';
					printf(
						'<select id="wpforms-field-option-%d-date_type" name="fields[%d][date_type]">',
						esc_attr( $field['id'] ),
						esc_attr( $field['id'] )
					);
						printf(
							'<option value="datepicker" %s>%s</option>',
							selected( $date_type, 'datepicker', false ),
							esc_html__( 'Date Picker', 'wpforms' )
						);
						printf(
							'<option value="dropdown" %s>%s</option>',
							selected( $date_type, 'dropdown', false ),
							esc_html__( 'Date Dropdown', 'wpforms' )
						);
					echo '</select>';
					printf(
						'<label for="wpforms-field-option-%d-date_type" class="sub-label">%s</label>',
						esc_attr( $field['id'] ),
						esc_html__( 'Type', 'wpforms' )
					);
				echo '</div>';
				echo '<div class="placeholder wpforms-field-options-column">';
					printf(
						'<input type="text" class="placeholder" id="wpforms-field-option-%d-date_placeholder" name="fields[%d][date_placeholder]" value="%s">',
						esc_attr( $field['id'] ),
						esc_attr( $field['id'] ),
						esc_attr( $date_placeholder )
					);
					printf(
						'<label for="wpforms-field-option-%d-date_placeholder" class="sub-label">%s</label>',
						esc_attr( $field['id'] ),
						esc_html__( 'Placeholder', 'wpforms' )
					);
				echo '</div>';
			echo '</div>';
			echo '<div class="wpforms-field-options-columns-2 wpforms-field-options-columns">';
				echo '<div class="format wpforms-field-options-column">';
					printf(
						'<select id="wpforms-field-option-%d-date_format" name="fields[%d][date_format]">',
						esc_attr( $field['id'] ),
						esc_attr( $field['id'] )
					);
					foreach ( $date_formats as $key => $value ) {
						if ( in_array( $key, [ 'm/d/Y', 'd/m/Y' ], true ) ) {
							printf(
								'<option value="%s" %s>%s (%s)</option>',
								esc_attr( $key ),
								selected( $date_format, $key, false ),
								esc_html( date( $value ) ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
								esc_html( $key )
							);
						} else {
							printf(
								'<option value="%s" class="datepicker-only" %s>%s</option>',
								esc_attr( $key ),
								selected( $date_format, $key, false ),
								esc_html( date( $value ) ) // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
							);
						}
					}
					echo '</select>';
					printf(
						'<label for="wpforms-field-option-%d-date_format" class="sub-label">%s</label>',
						esc_attr( $field['id'] ),
						esc_html__( 'Format', 'wpforms' )
					);
				echo '</div>';
			echo '</div>';

			// Limit Days options.
			$this->field_options_limit_days( $field );

		echo '</div>';

		// Time.
		$time_placeholder = ! empty( $field['time_placeholder'] ) ? $field['time_placeholder'] : '';
		$time_format      = ! empty( $field['time_format'] ) ? esc_attr( $field['time_format'] ) : 'g:i A';
		$time_formats     = wpforms_time_formats();

		$time_interval    = ! empty( $field['time_interval'] ) ? esc_attr( $field['time_interval'] ) : '30';
		$time_intervals   = apply_filters(
			'wpforms_datetime_time_intervals',
			[
				'15' => esc_html__( '15 minutes', 'wpforms' ),
				'30' => esc_html__( '30 minutes', 'wpforms' ),
				'60' => esc_html__( '1 hour', 'wpforms' ),
			]
		);
		printf(
			'<div class="wpforms-clear wpforms-field-option-row wpforms-field-option-row-time no-gap" id="wpforms-field-option-row-%d-time" data-subfield="time" data-field-id="%d">',
			esc_attr( $field['id'] ),
			esc_attr( $field['id'] )
		);
			$this->field_element(
				'label',
				$field,
				[
					'slug'    => 'time_placeholder',
					'value'   => esc_html__( 'Time', 'wpforms' ),
					'tooltip' => esc_html__( 'Advanced time options.', 'wpforms' ),
				]
			);

			echo '<div class="wpforms-field-options-columns-2 wpforms-field-options-columns">';
				echo '<div class="interval wpforms-field-options-column">';
					printf(
						'<select id="wpforms-field-option-%d-time_interval" name="fields[%d][time_interval]">',
						esc_attr( $field['id'] ),
						esc_attr( $field['id'] )
					);
						foreach ( $time_intervals as $key => $value ) {
							printf(
								'<option value="%s" %s>%s</option>',
								esc_attr( $key ),
								selected( $time_interval, $key, false ),
								$value // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							);
						}
					echo '</select>';
					printf(
						'<label for="wpforms-field-option-%d-time_interval" class="sub-label">%s</label>',
						esc_attr( $field['id'] ),
						esc_html__( 'Interval', 'wpforms' )
					);
				echo '</div>';
				echo '<div class="placeholder wpforms-field-options-column">';
					printf(
						'<input type="text"" class="placeholder" id="wpforms-field-option-%d-time_placeholder" name="fields[%d][time_placeholder]" value="%s">',
						esc_attr( $field['id'] ),
						esc_attr( $field['id'] ),
						esc_attr( $time_placeholder )
					);
					printf(
						'<label for="wpforms-field-option-%d-time_placeholder" class="sub-label">%s</label>',
						esc_attr( $field['id'] ),
						esc_html__( 'Placeholder', 'wpforms' )
					);
				echo '</div>';
			echo '</div>';
			echo '<div class="wpforms-field-options-columns-2 wpforms-field-options-columns">';
				echo '<div class="format wpforms-field-options-column">';
						printf(
							'<select id="wpforms-field-option-%d-time_format" name="fields[%d][time_format]">',
							esc_attr( $field['id'] ),
							esc_attr( $field['id'] )
						);
							foreach ( $time_formats as $key => $value ) {
								printf(
									'<option value="%s" %s>%s</option>',
									esc_attr( $key ),
									selected( $time_format, $key, false ),
									esc_html( $value )
								);
							}
						echo '</select>';
					printf(
						'<label for="wpforms-field-option-%d-time_format" class="sub-label">%s</label>',
						esc_attr( $field['id'] ),
						esc_html__( 'Format', 'wpforms' )
					);
				echo '</div>';
			echo '</div>';

			// Limit Hours options.
			$this->field_options_limit_hours( $field );

		echo '</div>';

		echo '</div>';

		// Custom CSS classes.
		$this->field_option( 'css', $field );

		// Hide label.
		$this->field_option( 'label_hide', $field );

		// Hide sublabels.
		$sublabel_class = isset( $field['format'] ) && $field['format'] !== 'date-time' ? 'wpforms-hidden' : '';

		$this->field_option( 'sublabel_hide', $field, [ 'class' => $sublabel_class ] );

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
	 * Display limit days options.
	 *
	 * @since 1.6.3
	 *
	 * @param array $field Field setting.
	 */
	private function field_options_limit_days( $field ) {

		echo '<div class="wpforms-clear"></div>';

		$output = $this->field_element(
			'toggle',
			$field,
			[
				'slug'    => 'date_limit_days',
				'value'   => ! empty( $field['date_limit_days'] ) ? '1' : '0',
				'desc'    => esc_html__( 'Limit Days', 'wpforms' ),
				'tooltip' => esc_html__( 'Check this option to adjust which days of the week can be selected.', 'wpforms' ),
				'class'   => 'wpforms-panel-field-toggle',
			],
			false
		);
		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'date_limit_days',
				'content' => $output,
				'class'   => 'wpforms-clear',
			],
			true
		);

		$week_days = [
			'sun' => esc_html__( 'Sun', 'wpforms' ),
			'mon' => esc_html__( 'Mon', 'wpforms' ),
			'tue' => esc_html__( 'Tue', 'wpforms' ),
			'wed' => esc_html__( 'Wed', 'wpforms' ),
			'thu' => esc_html__( 'Thu', 'wpforms' ),
			'fri' => esc_html__( 'Fri', 'wpforms' ),
			'sat' => esc_html__( 'Sat', 'wpforms' ),
		];

		// Rearrange days array according to the Start of Week setting.
		$start_of_week = get_option( 'start_of_week' );
		$start_of_week = ! empty( $start_of_week ) ? (int) $start_of_week : 0;

		if ( $start_of_week > 0 ) {
			$days_after = $week_days;
			$days_begin = array_splice( $days_after, 0, $start_of_week );
			$days       = array_merge( $days_after, $days_begin );
		} else {
			$days = $week_days;
		}

		// Limit Days body.
		$output = '';
		foreach ( $days as $day => $day_translation ) {

			$day_slug = 'date_limit_days_' . $day;

			// Set defaults.
			if ( ! isset( $field['date_format'] ) ) {
				$field[ $day_slug ] = $this->defaults[ $day_slug ];
			}

			$output .= '<label class="sub-label">';
			$output .= $this->field_element(
				'checkbox',
				$field,
				[
					'slug'   => $day_slug,
					'value'  => ! empty( $field[ $day_slug ] ) ? '1' : '0',
					'nodesc' => '1',
					'class'  => 'wpforms-field-options-column',
				],
				false
			);
			$output .= '<br>' . $day_translation . '</label>';
		}

		printf(
			'<div
				class="wpforms-field-option-row wpforms-field-option-row-date_limit_days_options wpforms-panel-field-toggle-body wpforms-field-options-columns wpforms-field-options-columns-7 checkboxes-row"
				id="wpforms-field-option-row-%1$d-date_limit_days_options"
				data-toggle="%2$s"
				data-toggle-value="1"
				data-field-id="%1$d">%3$s</div>',
			esc_attr( $field['id'] ),
			esc_attr( 'fields[' . (int) $field['id'] . '][date_limit_days]' ),
			$output // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);

		// Disable Past Dates.
		$output = $this->field_element(
			'toggle',
			$field,
			[
				'slug'    => 'date_disable_past_dates',
				'value'   => ! empty( $field['date_disable_past_dates'] ) ? '1' : '0',
				'desc'    => esc_html__( 'Disable Past Dates', 'wpforms' ),
				'tooltip' => esc_html__( 'Check this option to prevent any previous date from being selected.', 'wpforms' ),
			],
			false
		);
		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'date_disable_past_dates',
				'content' => $output,
			],
			true
		);
	}

	/**
	 * Display limit hours options.
	 *
	 * @since 1.6.3
	 *
	 * @param array $field Field setting.
	 */
	private function field_options_limit_hours( $field ) {

		echo '<div class="wpforms-clear"></div>';

		$output = $this->field_element(
			'toggle',
			$field,
			[
				'slug'    => 'time_limit_hours',
				'value'   => ! empty( $field['time_limit_hours'] ) ? '1' : '0',
				'desc'    => esc_html__( 'Limit Hours', 'wpforms' ),
				'tooltip' => esc_html__( 'Check this option to adjust the range of times that can be selected.', 'wpforms' ),
				'class'   => 'wpforms-panel-field-toggle',
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'time_limit_hours',
				'content' => $output,
			],
			true
		);

		// Determine time format type.
		// If the format contains `g` or `h`, then this is 12 hours format, otherwise 24 hours.
		$time_format = empty( $field['time_format'] ) || ( ! empty( $field['time_format'] ) && preg_match( '/[gh]/', $field['time_format'] ) ) ? 12 : 24;

		// Limit Hours body.
		$output = '';

		foreach ( [ 'start', 'end' ] as $option ) {

			$output .= '<div class="wpforms-field-options-columns wpforms-field-options-columns-4">'; // Open columns container.

			$slug    = 'time_limit_hours_' . $option . '_hour';
			$output .= $this->field_element(
				'select',
				$field,
				[
					'slug'    => $slug,
					'value'   => ! empty( $field[ $slug ] ) ? $field[ $slug ] : $this->defaults[ $slug ],
					'options' => $time_format === 12 ? $this->get_selector_numeric_options( 1, $time_format, 1 ) : $this->get_selector_numeric_options( 0, $time_format - 1, 1 ),
					'class'   => 'wpforms-field-options-column',
				],
				false
			);

			$slug    = 'time_limit_hours_' . $option . '_min';
			$output .= $this->field_element(
				'select',
				$field,
				[
					'slug'    => $slug,
					'value'   => ! empty( $field[ $slug ] ) ? $field[ $slug ] : $this->defaults[ $slug ],
					'options' => $this->get_selector_numeric_options( 0, 59, 5 ),
					'class'   => 'wpforms-field-options-column',
				],
				false
			);

			$slug    = 'time_limit_hours_' . $option . '_ampm';
			$output .= $this->field_element(
				'select',
				$field,
				[
					'slug'    => $slug,
					'value'   => ! empty( $field[ $slug ] ) ? $field[ $slug ] : $this->defaults[ $slug ],
					'options' => [
						'am' => 'AM',
						'pm' => 'PM',
					],
					'class'   => [
						'wpforms-field-options-column',
						$time_format === 24 ? 'wpforms-hidden-strict' : '',
					],
				],
				false
			);

			$slug    = 'time_limit_hours_' . $option . '_hour';
			$output .= $this->field_element(
				'label',
				$field,
				[
					'slug'  => $slug,
					'value' => ( $option === 'start' ) ? esc_html__( 'Start Time', 'wpforms' ) : esc_html__( 'End Time', 'wpforms' ),
					'class' => [
						'sub-label',
						'wpforms-field-options-column',
					],
				],
				false
			);

			$output .= sprintf(
				'<div class="%s wpforms-field-options-column"></div>',
				$time_format === 12 ? 'wpforms-hidden-strict' : ''
			);

			$output .= '</div>'; // Close columns container.
		}

		printf(
			'<div
				class="wpforms-field-option-row wpforms-field-option-row-%1$s %2$s"
				id="wpforms-field-option-row-%3$d-%1$s"
				data-toggle="%4$s"
				data-toggle-value="1"
				data-field-id="%3$d">%5$s</div>',
			'time_limit_hours_options',
			'wpforms-panel-field-toggle-body',
			esc_attr( $field['id'] ),
			esc_attr( 'fields[' . (int) $field['id'] . '][time_limit_hours]' ),
			$output // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Generate array of numeric options for date/time selectors.
	 *
	 * @since 1.6.3
	 *
	 * @param integer $min  Minimum value.
	 * @param integer $max  Maximum value.
	 * @param integer $step Step.
	 *
	 * @return array
	 */
	private function get_selector_numeric_options( $min, $max, $step = 1 ) {

		$range   = range( (int) $min, (int) $max, (int) $step );
		$options = [];

		foreach ( $range as $i ) {
			$value             = str_pad( $i, 2, '0', STR_PAD_LEFT );
			$options[ $value ] = $value;
		}

		return $options;
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

		if ( 'date-time' === $field['type'] ) {

			$date_type = ! empty( $field['date_type'] ) ? sanitize_html_class( $field['date_type'] ) : 'datepicker';
			$class     = "wpforms-date-type-$date_type";
		}

		return $class;
	}

	/**
	 * Field preview inside the builder.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field Field data and settings.
	 */
	public function field_preview( $field ) {

		$date_placeholder = ! empty( $field['date_placeholder'] ) ? $field['date_placeholder'] : '';
		$time_placeholder = ! empty( $field['time_placeholder'] ) ? $field['time_placeholder'] : '';
		$format           = ! empty( $field['format'] ) ? $field['format'] : 'date-time';
		$date_type        = ! empty( $field['date_type'] ) ? $field['date_type'] : 'datepicker';
		$date_format      = ! empty( $field['date_format'] ) ? $field['date_format'] : 'm/d/Y';

		if ( 'mm/dd/yyyy' === $date_format || 'm/d/Y' === $date_format ) {
			$date_first_select  = 'MM';
			$date_second_select = 'DD';
		} else {
			$date_first_select  = 'DD';
			$date_second_select = 'MM';
		}

		// Label.
		$this->field_preview_option( 'label', $field );

		printf(
			'<div class="%s format-selected">',
			sanitize_html_class( 'format-selected-' . $format )
		);

			// Date.
			printf(
				'<div class="wpforms-date %s">',
				sanitize_html_class( 'wpforms-date-type-' . $date_type )
			);
				echo '<div class="wpforms-date-datepicker">';
					printf( '<input type="text" placeholder="%s" class="primary-input" readonly>', esc_attr( $date_placeholder ) );
					printf( '<label class="wpforms-sub-label">%s</label>', esc_html__( 'Date', 'wpforms' ) );
				echo '</div>';
				echo '<div class="wpforms-date-dropdown">';
					printf( '<select readonly class="first"><option>%s</option></select>', esc_html( $date_first_select ) );
					printf( '<select readonly class="second"><option>%s</option></select>', esc_html( $date_second_select ) );
					echo '<select readonly><option>YYYY</option></select>';
					printf( '<label class="wpforms-sub-label">%s</label>', esc_html__( 'Date', 'wpforms' ) );
				echo '</div>';
			echo '</div>';

			// Time.
			echo '<div class="wpforms-time">';
				printf( '<input type="text" placeholder="%s" class="primary-input" readonly>', esc_attr( $time_placeholder ) );
				printf( '<label class="wpforms-sub-label">%s</label>', esc_html__( 'Time', 'wpforms' ) );
			echo '</div>';
		echo '</div>';

		// Description.
		$this->field_preview_option( 'description', $field );
	}

	/**
	 * Field display on the form front-end.
	 *
	 * @since 1.0.0
	 * @since 1.5.0 Converted to a new format, where all the data are taken not from $deprecated, but field properties.
	 *
	 * @param array $field      Field data and settings.
	 * @param array $deprecated Deprecated array of field attributes.
	 * @param array $form_data  Form data and settings.
	 */
	public function field_display( $field, $deprecated, $form_data ) {

		$form_id    = $form_data['id'];
		$properties = $field['properties'];
		$container  = isset( $properties['input_container'] ) ? $properties['input_container'] : [];
		$date_prop  = isset( $field['properties']['inputs']['date'] ) ? $field['properties']['inputs']['date'] : [];
		$time_prop  = isset( $field['properties']['inputs']['time'] ) ? $field['properties']['inputs']['time'] : [];

		$date_prop['data']                = isset( $date_prop['data'] ) ? $date_prop['data'] : [];
		$date_prop['data']['date-format'] = isset( $date_prop['data']['date-format'] ) ? $date_prop['data']['date-format'] : $this->defaults['date_format'];
		$date_prop['data']['date-format'] = apply_filters( 'wpforms_datetime_date_format', $date_prop['data']['date-format'], $form_data, $field );
		$date_prop['data']['input']       = 'true';

		$time_prop['data']                = isset( $time_prop['data'] ) ? $time_prop['data'] : [];
		$time_prop['data']['step']        = isset( $time_prop['data']['step'] ) ? $time_prop['data']['step'] : $this->defaults['time_interval'];
		$time_prop['data']['step']        = apply_filters( 'wpforms_datetime_time_interval', $time_prop['data']['step'], $form_data, $field );
		$time_prop['data']['time-format'] = isset( $time_prop['data']['time-format'] ) ? $time_prop['data']['time-format'] : $this->defaults['time_format'];
		$time_prop['data']['time-format'] = apply_filters( 'wpforms_datetime_time_format', $time_prop['data']['time-format'], $form_data, $field );

		$field_required = ! empty( $field['required'] ) ? ' required' : '';
		$field_format   = ! empty( $field['format'] ) ? $field['format'] : 'date-time';

		$date_format = ! empty( $field['date_format'] ) ? $field['date_format'] : 'm/d/Y';
		$date_type   = ! empty( $field['date_type'] ) ? esc_attr( $field['date_type'] ) : 'datepicker';

		switch ( $field_format ) {
			case 'date-time':
				printf(
					'<div %s>',
					wpforms_html_attributes( $container['id'], $container['class'], $container['data'], $container['attr'] )
				);

				printf(
					'<div %s>',
					wpforms_html_attributes( $date_prop['container']['id'], $date_prop['container']['class'], $date_prop['container']['data'], $date_prop['container']['attr'] )
				);

				$this->field_display_sublabel( 'date', 'before', $field );

				if ( $date_type === 'dropdown' ) {

					$this->field_display_date_dropdowns( $date_format, $field, $field_required, $form_id );

				} else {

					printf(
						'<div class="wpforms-datepicker-wrap"><input type="text" %s %s><a title="%s" data-clear class="wpforms-datepicker-clear" style="display:%s;"></a></div>',
						wpforms_html_attributes( $date_prop['id'], $date_prop['class'], $date_prop['data'], $date_prop['attr'] ),
						esc_attr( $date_prop['required'] ),
						esc_attr__( 'Clear Date', 'wpforms' ),
						empty( $date_prop['attr']['value'] ) ? 'none' : 'block'
					);
				}

				$this->field_display_error( 'date', $field );
				$this->field_display_sublabel( 'date', 'after', $field );

				echo '</div>';

				printf(
					'<div %s>',
					wpforms_html_attributes( $time_prop['container']['id'], $time_prop['container']['class'], $time_prop['container']['data'], $time_prop['container']['attr'] )
				);

				$this->field_display_sublabel( 'time', 'before', $field );

				printf(
					'<input type="text" %s %s>',
					wpforms_html_attributes( $time_prop['id'], $time_prop['class'], $time_prop['data'], $time_prop['attr'] ),
					! empty( $time_prop['required'] ) ? 'required' : ''
				);

				$this->field_display_error( 'time', $field );
				$this->field_display_sublabel( 'time', 'after', $field );

				echo '</div>';

				echo '</div>';
				break;

			case 'date':
				if ( $date_type === 'dropdown' ) {

					$this->field_display_date_dropdowns( $date_format, $field, $field_required, $form_id );

				} else {

					printf(
						'<div class="wpforms-datepicker-wrap"><input type="text" %s %s><a title="%s" data-clear class="wpforms-datepicker-clear" style="display:%s;"></a></div>',
						wpforms_html_attributes( $date_prop['id'], $date_prop['class'], $date_prop['data'], $date_prop['attr'] ),
						esc_attr( $date_prop['required'] ),
						esc_attr__( 'Clear Date', 'wpforms' ),
						empty( $date_prop['attr']['value'] ) ? 'none' : 'block'
					);
				}
				break;

			case 'time':
			default:
				printf(
					'<input type="text" %s %s>',
					wpforms_html_attributes( $time_prop['id'], $time_prop['class'], $time_prop['data'], $time_prop['attr'] ),
					! empty( $time_prop['required'] ) ? 'required' : ''
				);
				$this->field_display_error( 'time', $field );
				break;
		}
	}

	/**
	 * Display the date field using dropdowns.
	 *
	 * @since 1.3.0
	 *
	 * @param string $format         Field format.
	 * @param array  $field          Field data and settings.
	 * @param string $field_required Is this field required or not, has a HTML attribute or empty.
	 * @param int    $form_id        Form ID.
	 */
	public function field_display_date_dropdowns( $format, $field, $field_required, $form_id ) {

		$format = ! empty( $format ) ? esc_attr( $format ) : 'm/d/Y';

		// Backwards compatibility with old datepicker format.
		if ( $format === 'mm/dd/yyyy' ) {
			$format = 'm/d/Y';
		} elseif ( $format === 'dd/mm/yyyy' ) {
			$format = 'd/m/Y';
		} elseif ( $format === 'mmmm d, yyyy' ) {
			$format = 'F j, Y';
		}

		// phpcs:disable WPForms.Comments.ParamTagHooks.InvalidAlign

		/**
		 * Filter DateTime field Date dropdowns ranges data.
		 *
		 * @since 1.4.4
		 *
		 * @param array $ranges {
		 *      Date dropdowns ranges data.
		 *
		 *      @type array  $months       Months.
		 *      @type array  $days         Days.
		 *      @type array  $years        Years.
		 *      @type string $months_label Months label.
		 *      @type string $days_label   Days label.
		 *      @type string $years_label  Years label.
		 * }
		 * @param integer $form_id Form ID.
		 * @param array   $field   Field data.
		 */
		$ranges = apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'wpforms_datetime_date_dropdowns',
			[
				'months'       => range( 1, 12 ),
				'days'         => range( 1, 31 ),
				'years'        => range( date( 'Y' ) + 1, 1920 ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				'months_label' => esc_html__( 'MM', 'wpforms' ),
				'days_label'   => esc_html__( 'DD', 'wpforms' ),
				'years_label'  => esc_html__( 'YYYY', 'wpforms' ),
			],
			$form_id,
			$field
		);
		// phpcs:enable WPForms.Comments.ParamTagHooks.InvalidAlign

		$properties = $field['properties'];
		$wrap       = isset( $properties['inputs']['date']['dropdown_wrap'] ) ? $properties['inputs']['date']['dropdown_wrap'] : [];

		printf(
			'<div %s>',
			wpforms_html_attributes( $wrap['id'], $wrap['class'], $wrap['data'], $wrap['attr'] )
		);

		if ( $format === 'm/d/Y' ) {
			$this->field_display_date_dropdown_element( 'month', $ranges['months_label'], $ranges['months'], $field, $field_required, $form_id );
			$this->field_display_date_dropdown_element( 'day', $ranges['days_label'], $ranges['days'], $field, $field_required, $form_id );
		} else {
			$this->field_display_date_dropdown_element( 'day', $ranges['days_label'], $ranges['days'], $field, $field_required, $form_id );
			$this->field_display_date_dropdown_element( 'month', $ranges['months_label'], $ranges['months'], $field, $field_required, $form_id );
		}

		$this->field_display_date_dropdown_element( 'year', $ranges['years_label'], $ranges['years'], $field, $field_required, $form_id );

		echo '</div>';
	}

	/**
	 * Display the Date Dropdown element.
	 *
	 * @since 1.8.1
	 *
	 * @param string $element        Date element: `day`, `month` or `year`.
	 * @param string $label          Field label.
	 * @param array  $numbers        Numbers range.
	 * @param array  $field          Field data and settings.
	 * @param string $field_required Is this field required or not, has HTML attribute or empty.
	 * @param int    $form_id        Form ID.
	 */
	private function field_display_date_dropdown_element( $element, $label, $numbers, $field, $field_required, $form_id ) {

		$defaults   = ! empty( $field['properties']['inputs']['date']['default'] ) && is_array( $field['properties']['inputs']['date']['default'] ) ? $field['properties']['inputs']['date']['default'] : [];
		$short      = $element[0];
		$current    = ! empty( $defaults[ $short ] ) ? (int) $defaults[ $short ] : 0;
		$properties = isset( $field['properties']['inputs']['date'][ $short ] ) ? $field['properties']['inputs']['date'][ $short ] : [];

		$atts = $this->get_date_dropdown_element_atts( $element, $form_id, $properties, $field_required, $field );

		$this->frontend_obj->display_date_dropdown_element( $label, $short, $numbers, $current, $atts, $field_required, $field );
	}

	/**
	 * Get the Date Dropdown element attributes.
	 *
	 * @since 1.8.1
	 *
	 * @param string $element        Date element: `day`, `month` or `year`.
	 * @param int    $form_id        Form ID.
	 * @param array  $properties     Field element properties.
	 * @param string $field_required Is this field required or not, has a HTML attribute or empty.
	 * @param array  $field          Field data and settings.
	 *
	 * @return array
	 */
	private function get_date_dropdown_element_atts( $element, $form_id, $properties, $field_required, $field ) {

		$atts = [];

		$atts['id'] = "wpforms-{$form_id}-field_{$field['id']}-{$element}";

		$atts['class']   = isset( $properties['class'] ) ? $properties['class'] : [];
		$atts['class'][] = 'wpforms-field-date-time-date-' . $element;
		$atts['class'][] = ! empty( $field_required ) ? 'wpforms-field-required' : '';
		$atts['class'][] = ! empty( wpforms()->get( 'process' )->errors[ $form_id ][ $field['id'] ]['date'] ) ? 'wpforms-error' : '';

		$atts['data'] = isset( $properties['data'] ) ? $properties['data'] : [];
		$atts['attr'] = isset( $properties['attr'] ) ? $properties['attr'] : [];

		return $atts;
	}

	/**
	 * Validate field on form submit.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $field_id     Field ID.
	 * @param array $field_submit Submitted field value.
	 * @param array $form_data    Form data and settings.
	 */
	public function validate( $field_id, $field_submit, $form_data ) {

		$this->validate_time_limit( $field_id, $field_submit, $form_data );

		if ( empty( $form_data['fields'][ $field_id ]['required'] ) ) {
			return;
		}

		// Extended validation needed for the different address fields.
		$form_id  = $form_data['id'];
		$format   = $form_data['fields'][ $field_id ]['format'];
		$required = wpforms_get_required_label();

		$is_date_format = $format === 'date' || $format === 'date-time';
		$is_time_format = $format === 'time' || $format === 'date-time';

		if (
			! empty( $form_data['fields'][ $field_id ]['date_type'] ) &&
			$form_data['fields'][ $field_id ]['date_type'] === 'dropdown'
		) {
			if (
				$is_date_format &&
				( empty( $field_submit['date']['m'] ) || empty( $field_submit['date']['d'] ) || empty( $field_submit['date']['y'] ) )
			) {
				wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['date'] = $required;
			}
		} else {
			if (
				$is_date_format &&
				empty( $field_submit['date'] )
			) {
				wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['date'] = $required;
			}
		}

		if (
			$is_time_format &&
			empty( $field_submit['time'] )
		) {
			wpforms()->get( 'process' )->errors[ $form_id ][ $field_id ]['time'] = $required;
		}
	}

	/**
	 * Validate time limit (Limit Hours).
	 *
	 * @since 1.7.1
	 *
	 * @param int   $field_id     Field ID.
	 * @param array $field_submit Submitted field value.
	 * @param array $form_data    Form data and settings.
	 */
	private function validate_time_limit( $field_id, $field_submit, $form_data ) { // phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh

		if ( empty( $form_data['fields'][ $field_id ] ) ) {
			return;
		}

		$field = $form_data['fields'][ $field_id ];

		if ( empty( $field['time_limit_hours'] ) || empty( $field_submit['time'] ) ) {
			return;
		}

		// Limit Hours validation should apply only for default (embedded) time formats.
		if (
			empty( $field['time_format'] ) ||
			! in_array( $field['time_format'], [ 'g:i A', 'H:i' ], true )
		) {
			return;
		}

		$min_time = $field['time_limit_hours_start_hour'] . ':' . $field['time_limit_hours_start_min'];
		$max_time = $field['time_limit_hours_end_hour'] . ':' . $field['time_limit_hours_end_min'];

		if ( $field['time_format'] === 'g:i A' ) {
			if ( $field['time_limit_hours_start_hour'] === '00' ) {
				$min_time = '12:' . $field['time_limit_hours_start_min'];
			}

			if ( $field['time_limit_hours_end_hour'] === '00' ) {
				$max_time = '12:' . $field['time_limit_hours_end_min'];
			}

			$min_time .= ' ' . strtoupper( $field['time_limit_hours_start_ampm'] );
			$max_time .= ' ' . strtoupper( $field['time_limit_hours_end_ampm'] );
		}

		$min_timestamp    = strtotime( $min_time );
		$max_timestamp    = strtotime( $max_time );
		$submit_timestamp = strtotime( $field_submit['time'] );

		if ( $max_timestamp > $min_timestamp ) {
			$is_valid = ( $submit_timestamp >= $min_timestamp ) && ( $submit_timestamp <= $max_timestamp );
		} else {
			$is_valid = ( ( $submit_timestamp >= $min_timestamp ) && ( $submit_timestamp >= $max_timestamp ) ) ||
						( ( $submit_timestamp <= $min_timestamp ) && ( $submit_timestamp <= $max_timestamp ) );
		}

		if ( ! $is_valid ) {

			$error = wpforms_setting( 'validation-time-limit', esc_html__( 'Please enter time between {minTime} and {maxTime}.', 'wpforms' ) );
			$error = str_replace( [ '{minTime}', '{maxTime}' ], [ $min_time, $max_time ], $error );

			wpforms()->get( 'process' )->errors[ $form_data['id'] ][ $field_id ]['time'] = $error;
		}
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

		$name        = ! empty( $form_data['fields'][ $field_id ]['label'] ) ? $form_data['fields'][ $field_id ]['label'] : '';
		$format      = $form_data['fields'][ $field_id ]['format'];
		$date_format = $form_data['fields'][ $field_id ]['date_format'];
		$time_format = $form_data['fields'][ $field_id ]['time_format'];
		$value       = '';
		$date        = '';
		$time        = '';
		$unix        = '';

		if ( ! empty( $field_submit['date'] ) ) {
			if ( is_array( $field_submit['date'] ) ) {

				if (
					! empty( $field_submit['date']['m'] ) &&
					! empty( $field_submit['date']['d'] ) &&
					! empty( $field_submit['date']['y'] )
				) {
					if (
						'dd/mm/yyyy' === $date_format ||
						'd/m/Y' === $date_format
					) {
						$date = $field_submit['date']['d'] . '/' . $field_submit['date']['m'] . '/' . $field_submit['date']['y'];
					} else {
						$date = $field_submit['date']['m'] . '/' . $field_submit['date']['d'] . '/' . $field_submit['date']['y'];
					}
				} else {
					// So we are missing some of the values.
					// We can't process date further, as we won't be able to retrieve its unix time.
					wpforms()->get( 'process' )->fields[ $field_id ] = [
						'name'  => sanitize_text_field( $name ),
						'value' => sanitize_text_field( $value ),
						'id'    => absint( $field_id ),
						'type'  => $this->type,
						'date'  => '',
						'time'  => '',
						'unix'  => false,
					];

					return;
				}
			} else {
				$date = $field_submit['date'];
			}
		}

		if ( ! empty( $field_submit['time'] ) ) {
			$time = $field_submit['time'];
		}

		if ( 'date-time' === $format && ! empty( $field_submit ) ) {
			$value = trim( "$date $time" );
		} elseif ( 'date' === $format ) {
			$value = $date;
		} elseif ( 'time' === $format ) {
			$value = $time;
		}

		// Always store the raw time in 12H format.
		if ( ( 'H:i A' === $time_format || 'H:i' === $time_format ) && ! empty( $time ) ) {
			$time = date( 'g:i A', strtotime( $time ) );
		}

		// Always store the date in m/d/Y format so it is strtotime() compatible.
		if (
			( 'dd/mm/yyyy' === $date_format || 'd/m/Y' === $date_format ) &&
			! empty( $date )
		) {
			list( $d, $m, $y ) = explode( '/', $date );

			$date = "$m/$d/$y";
		}

		// Calculate unix time if we have a date.
		if ( ! empty( $date ) ) {
			$unix = strtotime( trim( "$date $time" ) );
		}

		wpforms()->get( 'process' )->fields[ $field_id ] = [
			'name'  => sanitize_text_field( $name ),
			'value' => sanitize_text_field( $value ),
			'id'    => absint( $field_id ),
			'type'  => $this->type,
			'date'  => sanitize_text_field( $date ),
			'time'  => sanitize_text_field( $time ),
			'unix'  => $unix,
		];
	}
}

new WPForms_Field_Date_Time();
