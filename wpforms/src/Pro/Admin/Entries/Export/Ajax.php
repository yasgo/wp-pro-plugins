<?php

namespace WPForms\Pro\Admin\Entries\Export;

use Exception;
use Generator;
use WPForms\Db\Payments\ValueValidator;
use WPForms\Pro\Helpers\CSV;
use WPForms\Helpers\Transient;
use WPForms\Pro\Admin\Entries;
use WPForms\Pro\Admin\Entries\Export\Traits\Export as ExportTrait;

/**
 * Ajax endpoints and data processing.
 *
 * @since 1.5.5
 */
class Ajax {

	use Entries\FilterSearch;
	use ExportTrait;

	/**
	 * Instance of Export Class.
	 *
	 * @since 1.5.5
	 *
	 * @var Export
	 */
	protected $export;

	/**
	 * CSV helper class instance.
	 *
	 * @since 1.7.7
	 *
	 * @var CSV
	 */
	private $csv;

	/**
	 * Request data.
	 *
	 * @since 1.5.5
	 *
	 * @var array
	 */
	public $request_data;

	/**
	 * Values array.
	 *
	 * @since 1.8.6
	 *
	 * @var array
	 */
	private $values = [];

	/**
	 * Constructor.
	 *
	 * @since 1.5.5
	 *
	 * @param Export $export Instance of Export.
	 */
	public function __construct( $export ) {

		$this->export = $export;
		$this->csv    = new CSV();

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.5.5
	 */
	public function hooks() {

		add_action( 'wp_ajax_wpforms_tools_entries_export_form_data', [ $this, 'ajax_form_data' ] );
		add_action( 'wp_ajax_wpforms_tools_entries_export_step', [ $this, 'ajax_export_step' ] );
	}

	/**
	 * Ajax endpoint. Send form fields.
	 *
	 * @since 1.5.5
	 *
	 * @throws Exception Try-catch.
	 */
	public function ajax_form_data() {

		try {

			// Run a security check.
			if ( ! check_ajax_referer( 'wpforms-tools-entries-export-nonce', 'nonce', false ) ) {
				throw new Exception( $this->export->errors['security'] );
			}

			if ( empty( $this->export->data['form_data'] ) ) {
				throw new Exception( $this->export->errors['form_data'] );
			}

			$fields         = empty( $this->export->data['form_data']['fields'] ) ? [] : (array) $this->export->data['form_data']['fields'];
			$payment_fields = empty( $this->export->data['form_data']['payment_fields'] ) ? [] : (array) $this->export->data['form_data']['payment_fields'];

			$dynamic_choices_count = $this->get_dynamic_choices_count( $fields );

			wp_send_json_success(
				[
					'fields'                 => $this->get_prepared_fields( $fields ),
					'payment_fields'         => $this->get_prepared_fields( $payment_fields ),
					'statuses'               => $this->get_available_form_entry_statuses( $this->export->data['form_data']['id'] ),
					'dynamic_columns'        => $dynamic_choices_count > 1,
					'dynamic_columns_notice' => $this->get_dynamic_columns_notice( $dynamic_choices_count ),
				]
			);

		} catch ( Exception $e ) {

			$error = $this->export->errors['common'] . '<br>' . $e->getMessage();

			if ( wpforms_debug() ) {
				$error .= '<br><b>WPFORMS DEBUG</b>: ' . $e->__toString();
			}

			wp_send_json_error( [ 'error' => $error ] );
		}
	}

	/**
	 * Ajax endpoint. Entries export processing.
	 *
	 * @since 1.5.5
	 *
	 * @throws Exception Try-catch.
	 */
	public function ajax_export_step() {// phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		try {

			// Init arguments.
			$this->export->init_args( 'POST' );
			$args = $this->export->data['post_args'];

			// Security checks.
			if (
				empty( $args['nonce'] ) ||
				empty( $args['action'] ) ||
				! check_ajax_referer( 'wpforms-tools-entries-export-nonce', 'nonce', false ) ||
				! wpforms_current_user_can( 'view_entries' )
			) {
				throw new Exception( $this->export->errors['security'] );
			}

			// Check for form_id at the first step.
			if ( empty( $args['form_id'] ) && empty( $args['request_id'] ) ) {
				throw new Exception( $this->export->errors['unknown_form_id'] );
			}

			// Unlimited execution time.
			wpforms_set_time_limit();

			// Apply search by fields and advanced options.
			$this->process_filter_search();

			$this->request_data = $this->get_request_data( $args );

			if ( empty( $this->request_data ) ) {
				throw new Exception( $this->export->errors['unknown_request'] );
			}

			if ( $this->request_data['type'] === 'xlsx' ) {
				// Write to the .xlsx file.
				$this->export->file->write_xlsx( $this->request_data );
			} else {
				// Writing to the .csv file.
				$this->export->file->write_csv( $this->request_data );
			}

			// Prepare response.
			$response = $this->get_response_data();

			// Store request data.
			Transient::set( 'wpforms-tools-entries-export-request-' . $this->request_data['request_id'], $this->request_data, $this->export->configuration['request_data_ttl'] );

			wp_send_json_success( $response );

		} catch ( Exception $e ) {

			$error = $this->export->errors['common'] . '<br>' . $e->getMessage();

			if ( wpforms_debug() ) {
				$error .= '<br><b>WPFORMS DEBUG</b>: ' . $e->__toString();
			}
			wp_send_json_error( [ 'error' => $error ] );

		}
	}

	/**
	 * Get request data at first step.
	 *
	 * @since 1.5.5
	 *
	 * @param array $args Arguments array.
	 *
	 * @return array Request data.
	 */
	public function get_request_data( $args ) {

		// Prepare arguments.
		$db_args = [
			'number'      => 0,
			'offset'      => 0,
			'form_id'     => $args['form_id'],
			'entry_id'    => $args['entry_id'],
			'is_filtered' => ! empty( $args['entry_id'] ),
			'date'        => $args['dates'],
			'select'      => 'entry_ids',
			'status'      => $args['status'],
		];

		if ( $args['search']['term'] !== '' ) {
			$db_args['value']         = $args['search']['term'];
			$db_args['value_compare'] = $args['search']['comparison'];
			$db_args['field_id']      = $args['search']['field'];
		}

		// Count total entries.
		$count = wpforms()->get( 'entry' )->get_entries( $db_args, true );

		// Retrieve form data.
		$form_data = wpforms()->get( 'form' )->get(
			$args['form_id'],
			[
				'content_only' => true,
			]
		);

		// Prepare get entries args for further steps.
		unset( $db_args['select'] );

		$db_args['number'] = $this->export->configuration['entries_per_step'];

		$form_data['fields'] = empty( $form_data['fields'] ) ? [] : (array) $form_data['fields'];

		$fields = $this->exclude_fields( $form_data['fields'], $this->export->configuration['disallowed_fields'] );

		$fields_indexes = wp_list_pluck( $fields, 'id' );

		// Sort selected fields by order in form.
		// This is needed to correctly display the columns in the exported file after separating fields by type.
		foreach ( $fields_indexes as $index => $field_id ) {
			if ( ! in_array( (int) $field_id, $args['fields'], true ) ) {
				unset( $fields_indexes[ $index ] );
			}
		}

		$export_options = ! empty( $args['export_options'] ) ? $args['export_options'] : [];

		// Prepare `request data` for saving.
		$request_data = [
			'request_id'      => md5( wp_json_encode( $db_args ) . microtime() ),
			'form_data'       => $form_data,
			'db_args'         => $db_args,
			'fields'          => empty( $args['entry_id'] ) ? $fields_indexes : wp_list_pluck( $fields, 'id' ),
			'additional_info' => empty( $args['entry_id'] ) ? $args['additional_info'] : array_keys( $this->export->additional_info_fields ),
			'count'           => $count,
			'total_steps'     => (int) ceil( $count / $this->export->configuration['entries_per_step'] ),
			'type'            => in_array( 'xlsx', $export_options, true ) ? 'xlsx' : 'csv',
			'dynamic_columns' => in_array( 'dynamic_columns', $export_options, true ),
		];

		/**
		 * Filter $request_data during ajax request.
		 *
		 * @since 1.8.2
		 *
		 * @param array $request_data Request data array.
		 */
		$request_data                = apply_filters( 'wpforms_pro_admin_entries_export_ajax_request_data', $request_data );
		$request_data['columns_row'] = $this->get_csv_cols( $request_data );

		return $request_data;
	}

	/**
	 * Get response data.
	 *
	 * @since 1.5.5
	 *
	 * @return array Export data.
	 */
	protected function get_response_data() {

		return [
			'request_id' => $this->request_data['request_id'],
			'count'      => $this->request_data['count'],
		];
	}

	/**
	 * Get CSV columns row.
	 *
	 * @since 1.5.5
	 *
	 * @param array $request_data Request data array.
	 *
	 * @return array CSV columns (first row).
	 */
	public function get_csv_cols( $request_data ) {

		$columns_row = [];

		if ( ! empty( $request_data['form_data']['fields'] ) ) {
			$fields = array_map(
				static function ( $field ) {
					$field['label'] = ! empty( $field['label'] ) ?
						trim( wp_strip_all_tags( $field['label'] ) ) :
						sprintf( /* translators: %d - field ID. */
							esc_html__( 'Field #%d', 'wpforms' ),
							(int) $field['id']
						);

					return $field;
				},
				$request_data['form_data']['fields']
			);

			$columns_labels = wp_list_pluck( $fields, 'label', 'id' );

			foreach ( $request_data['fields'] as $field_id ) {
				if ( ! isset( $columns_labels[ $field_id ] ) ) {
					continue;
				}

				$field = $request_data['form_data']['fields'][ $field_id ];

				// Check if field is multiple input.
				// It can be: name, address, select, checkbox, payment-checkbox, file-upload, likert_scale.
				if ( $this->is_multiple_input( $field ) ) {

					$enabled_dynamic_columns = $request_data['dynamic_columns'];

					$columns = $this->get_multiple_choices_columns( $field, $request_data['form_data'], $enabled_dynamic_columns );

					// No need to add dynamic columns if they are disabled.
					// Return regular column instead.
					if ( ! $enabled_dynamic_columns && $this->is_dynamic_choices( $field ) ) {
						$columns_row[ $field_id ] = $columns_labels[ $field_id ];

						continue;
					}

					// Add dynamic columns for each multiple field value.
					foreach ( $columns as $key => $column ) {
						$is_modified = $column['modified'] ?? false;

						$columns_row[ "multiple_field_{$field_id}_{$key}" ] = sprintf(
							'%s: %s%s',
							$columns_labels[ $field_id ],
							trim( $column['label'] ),
							$is_modified ? __( ' (modified)', 'wpforms' ) : ''
						);
					}
				} else {
					$columns_row[ $field_id ] = $columns_labels[ $field_id ];
				}
			}
		} else {
			$fields = [];
		}
		if ( ! empty( $request_data['additional_info'] ) ) {
			foreach ( $request_data['additional_info'] as $field_id ) {
				if ( $field_id === 'del_fields' ) {
					$columns_row += $this->get_deleted_fields_columns( $fields, $request_data );
				} else {
					$columns_row[ $field_id ] = $this->export->additional_info_fields[ $field_id ];
				}
			}
		}

		$columns_row = apply_filters_deprecated(
			'wpforms_export_get_csv_cols',
			[ $columns_row, ! empty( $request_data['db_args']['entry_id'] ) ? (int) $request_data['db_args']['entry_id'] : 'all' ],
			'1.5.5 of the WPForms plugin',
			'wpforms_pro_admin_entries_export_ajax_get_csv_cols'
		);

		return apply_filters( 'wpforms_pro_admin_entries_export_ajax_get_csv_cols', $columns_row, $request_data );
	}

	/**
	 * Get single entry data.
	 *
	 * @since 1.6.5
	 *
	 * @param array $entries Entries.
	 *
	 * @return Generator
	 */
	public function get_entry_data( $entries ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.NestingLevel.MaxExceeded

		$no_fields  = empty( $this->request_data['form_data']['fields'] );
		$del_fields = in_array( 'del_fields', $this->request_data['additional_info'], true );

		// Prepare entries data.
		foreach ( $entries as $entry ) {

			$fields = $this->get_entry_fields_data( $entry );
			$row    = [];

			foreach ( $this->request_data['columns_row'] as $col_id => $col_label ) {

				if ( is_numeric( $col_id ) ) {
					$row[ $col_id ] = isset( $fields[ $col_id ]['value'] ) ? $fields[ $col_id ]['value'] : '';
				} elseif ( strpos( $col_id, 'del_field_' ) !== false ) {
					$f_id           = str_replace( 'del_field_', '', $col_id );
					$row[ $col_id ] = isset( $fields[ $f_id ]['value'] ) ? $fields[ $f_id ]['value'] : '';
				} elseif ( strpos( $col_id, 'multiple_field_' ) !== false ) {
					$row_value = $this->get_multiple_row_value( $fields, $col_id );

					if ( $this->is_skip_value( $row_value, $fields, $col_id ) ) {
						continue;
					}

					$row[ $col_id ] = $row_value;
				} else {
					$row[ $col_id ] = $this->get_additional_info_value( $col_id, $entry, $this->request_data['form_data'] );
				}

				$row[ $col_id ] = $this->csv->escape_value( $row[ $col_id ] );
			}

			if ( $no_fields && ! $del_fields ) {
				continue;
			}

			$export_data = apply_filters_deprecated(
				'wpforms_export_get_data',
				[ [ $entry->entry_id => $row ], ! empty( $this->request_data['db_args']['entry_id'] ) ? (int) $this->request_data['db_args']['entry_id'] : 'all' ],
				'1.5.5 of the WPForms plugin',
				'wpforms_pro_admin_entries_export_get_entry_data'
			);

			$export_data = apply_filters_deprecated(
				'wpforms_pro_admin_entries_export_ajax_get_data',
				[ $export_data, $this->request_data ],
				'1.6.5 of the WPForms plugin',
				'wpforms_pro_admin_entries_export_get_entry_data'
			);

			/**
			 * Filters the export data.
			 *
			 * @since 1.6.5
			 * @since 1.8.4 Added the `$entry` parameter.
			 *
			 * @param array  $export_data  An array of information to be exported from the entry.
			 * @param array  $request_data An array of information requested from the entry.
			 * @param object $entry        The entry object.
			 */
			yield apply_filters( 'wpforms_pro_admin_entries_export_ajax_get_entry_data', $export_data[ $entry->entry_id ], $this->request_data, $entry );
		}
	}

	/**
	 * Get multiple row value.
	 *
	 * @since 1.8.5
	 *
	 * @param array  $fields Field data.
	 * @param string $col_id Column id.
	 *
	 * @return string
	 */
	public function get_multiple_row_value( $fields, $col_id ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh,Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$row_value = '';

		// Get multiple field id. Contains field id and value id.
		// See get_csv_cols method.
		// $col_id: 'multiple_field_' . $field_id . '_' . $key.
		$id = str_replace( 'multiple_field_', '', $col_id );

		// Get field id and value id.
		// $id: $field_id . '_' . $key.
		$multiple_key = explode( '_', $id );

		// The First element is field id.
		$multiple_field_id = $multiple_key[0];

		// Second element is value id.
		$multiple_value_id = (int) end( $multiple_key );

		$field = isset( $fields[ $multiple_field_id ] ) ? $fields[ $multiple_field_id ] : null;

		if ( ! $field ) {
			return $row_value;
		}

		$type = $field['type'];

		// Convert value to array.
		$values = $this->get_field_values( $field );

		// Get field choices.
		$choices = $this->get_multiple_choices_columns(
			$this->request_data['form_data']['fields'][ $multiple_field_id ],
			$this->request_data['form_data'],
			$this->request_data['dynamic_columns']
		);

		/**
		 * If field has only one choice, set label to 'Checked'.
		 *
		 * See field_properties method.
		 * includes/fields/class-checkbox.php
		 * src/Forms/Fields/PaymentCheckbox/Field.php
		 */
		if ( count( $choices ) === 1 ) {
			$choices = array_map(
				static function ( $choice ) {

					$choice['label'] = __( 'Checked', 'wpforms' );

					return $choice;
				},
				$choices
			);
		}

		// Make sure that values array has the same length as choices array.
		$values = array_pad( $values, count( $choices ), '' );

		// Add each value to the separate column in the row.
		foreach ( $values as $index => $value ) {
			// No needed comparison indexes for File Upload, Name and Address fields.
			if ( in_array( $type, [ 'file-upload', 'name', 'address' ], true ) ) {
				// If value index not equal to value id, skip it.
				if ( $multiple_value_id !== $index ) {
					continue;
				}

				$row_value = $value;

				continue;
			}

			$labels = array_column( $choices, 'label' );

			// Try to find value index in choices array.
			$value_index = array_search( $value, array_map( 'trim', $labels ), true );

			// For Likert Scale field search value index by key.
			if ( $type === 'likert_scale' ) {
				$value_index = array_search( $index, array_column( $choices, 'label' ), true );

				// Try to find modified value index.
				if ( $value_index === false ) {
					$index .= __( ' (modified)', 'wpforms' );

					$value_index = array_search( $index, array_column( $choices, 'label' ), true );
				}
			}

			// If value not found in choices array, skip it.
			if ( $value_index === false ) {
				continue;
			}

			// If value index not equal to value id, skip it.
			if ( $multiple_value_id !== $value_index ) {
				continue;
			}

			// For Likert Scale field we can set value without choices array.
			if ( $field['type'] === 'likert_scale' ) {
				$row_value = $value;

				continue;
			}

			// Set value.
			if ( isset( $choices[ $value_index ] ) ) {
				$row_value = $choices[ $value_index ]['label'];

				if ( $field['type'] === 'payment-checkbox' ) {
					$is_modified = $choices[ $value_index ]['modified'] ?? false;

					// Modify choices already formatted.
					$row_value = $is_modified
						? $choices[ $value_index ]['value']
						: sprintf(
							'%1$s - %2$s',
							$choices[ $value_index ]['label'],
							wpforms_format_amount( $choices[ $value_index ]['value'], true, $field['currency'] )
						);
				}
			}
		}

		return (string) $row_value;
	}

	/**
	 * Get entry field values.
	 *
	 * @since 1.8.5
	 *
	 * @param array $field Field data.
	 *
	 * @return array
	 */
	private function get_field_values( $field ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		// Get field value.
		$value = $field['value'] ?? '';

		// For Payment Checkbox field.
		if ( isset( $field['value_choice'] ) ) {
			$value = $field['value_choice'];

			$values = $this->prepare_values( $value );

			$value_raw = explode( ',', $field['value_raw'] );

			foreach ( $values as $index => $value ) {
				if ( ! $value ) {
					$values[ $index ] = sprintf(
						/* translators: %s - choice number. */
						esc_html__( 'Choice %s', 'wpforms' ),
						$value_raw[ $index ]
					);
				}
			}

			return $values;
		}

		// Convert value to array.
		$values = $this->prepare_values( $value );

		$type = $field['type'];

		// Prepare values for Name field, depends on format.
		if ( $type === 'name' ) {
			return $this->request_data['form_data']['fields'][ $field['id'] ]['format'] === 'first-last' ?
				[ $field['first'], $field['last'] ] :
				[ $field['first'], $field['middle'] ?? '', $field['last'] ];
		}

		// Prepare values for Address field.
		if ( $type === 'address' ) {
			$address_values = [
				$field['address1'],
				$field['address2'],
				$field['city'],
				$field['state'],
				$field['postal'],
				$field['country'],
			];

			// If address field is empty, return empty array.
			// By default empty address field storing Country value in database.
			if ( empty( $field['value'] ) ) {
				$address_values = array_pad( [], 6, '' );
			}

			return $address_values;
		}

		// Prepare values for Likert Scale field.
		if ( $type === 'likert_scale' ) {
			return $this->get_likert_scale_field_value( $values );
		}

		return $values;
	}

	/**
	 * Get Likert Scale field value.
	 *
	 * @since 1.8.5
	 *
	 * @param array $values Field values.
	 *
	 * @return array
	 */
	private function get_likert_scale_field_value( $values ) {

		// If single-row rating scale is selected.
		if ( count( $values ) === 1 ) {
			$values = $values[0];
			$values = explode( ',', $values );
			$values = array_map( 'trim', $values );

			// We need return array with keys as values.
			return array_combine( $values, $values );
		}

		// Get only odd values for rows.
		$rows = array_filter(
			$values,
			static function ( $key ) {

				return $key % 2 !== 0;
			},
			ARRAY_FILTER_USE_KEY
		);

		// Explode row values by comma.
		$rows = array_map(
			static function ( $key ) {

				return array_map( 'trim', explode( ',', $key ) );
			},
			$rows
		);

		// Get only even values for columns.
		$columns = array_filter(
			$values,
			static function ( $key ) {

				return $key % 2 === 0;
			},
			ARRAY_FILTER_USE_KEY
		);

		// Remove colon from values.
		$columns = array_map(
			static function ( $value ) {

				return str_replace( ':', '', $value );
			},
			$columns
		);

		$field_value = [];

		// Prepare an array with columns as keys and rows as values.
		foreach ( $rows as $index => $row ) {
			foreach ( $row as $row_label ) {
				$field_value[ $row_label ][] = $columns[ $index - 1 ];
			}
		}

		// Convert values to string.
		return array_map(
			static function ( $value ) {

				return implode( ', ', $value );
			},
			$field_value
		);
	}

	/**
	 * Get value of additional information column.
	 *
	 * @since 1.5.5
	 * @since 1.5.9 Added $form_data parameter and Payment Status data processing.
	 *
	 * @param string $col_id    Column id.
	 * @param object $entry     Entry object.
	 * @param array  $form_data Form data.
	 *
	 * @return string
	 */
	public function get_additional_info_value( $col_id, $entry, $form_data = [] ) {

		$entry = (array) $entry;

		switch ( $col_id ) {
			case 'date':
				$format = sprintf(
					'%s %s',
					get_option( 'date_format' ),
					get_option( 'time_format' )
				);

				$val = wpforms_datetime_format( $entry['date'], $format, true );
				break;

			case 'notes':
				$val = $this->get_additional_info_notes_value( $entry );
				break;

			case 'status':
				$val = $this->get_additional_info_status_value( $entry );
				break;

			case 'geodata':
				$val = $this->get_additional_info_geodata_value( $entry );
				break;

			case 'pstatus':
				$val = wpforms_has_payment( 'form', $form_data ) ? $this->get_additional_info_pstatus_value( $entry ) : '';
				break;

			case 'pginfo':
				$val = wpforms_has_payment( 'form', $form_data ) ? $this->get_additional_info_pginfo_value( $entry ) : '';
				break;

			case 'viewed':
			case 'starred':
				$val = $entry[ $col_id ] ? esc_html__( 'Yes', 'wpforms' ) : esc_html__( 'No', 'wpforms' );
				break;

			default:
				$val = $entry[ $col_id ];
		}

		/**
		 * Modify value of additional information column.
		 *
		 * @since 1.5.5
		 *
		 * @param string $val    The value.
		 * @param string $col_id Column id.
		 * @param object $entry  Entry object.
		 */
		return apply_filters( 'wpforms_pro_admin_entries_export_ajax_get_additional_info_value', $val, $col_id, $entry );
	}

	/**
	 * Get value of additional information notes.
	 *
	 * @since 1.5.5
	 *
	 * @param array $entry Entry data.
	 *
	 * @return string
	 */
	public function get_additional_info_notes_value( $entry ) {

		$entry_meta_obj = wpforms()->get( 'entry_meta' );
		$entry_notes    = $entry_meta_obj ?
			$entry_meta_obj->get_meta(
				[
					'entry_id' => $entry['entry_id'],
					'type'     => 'note',
				]
			) :
			null;

		$val = '';

		if ( empty( $entry_notes ) ) {
			return $val;
		}

		return array_reduce(
			$entry_notes,
			function ( $carry, $item ) {

				$item = (array) $item;

				$author       = get_userdata( $item['user_id'] );
				$author_name  = ! empty( $author->first_name ) ? $author->first_name : $author->user_login;
				$author_name .= ! empty( $author->last_name ) ? ' ' . $author->last_name : '';

				$carry .= wpforms_datetime_format( $item['date'], '', true ) . ', ';
				$carry .= $author_name . ': ';
				$carry .= wp_strip_all_tags( $item['data'] ) . "\n";

				return $carry;
			},
			$val
		);
	}

	/**
	 * Get value of entry status (Additional Information).
	 *
	 * @since 1.7.3
	 *
	 * @param array $entry Entry data.
	 *
	 * @return string
	 */
	public function get_additional_info_status_value( $entry ) {

		return in_array( $entry['status'], [ 'partial', 'abandoned' ], true ) ? ucwords( sanitize_text_field( $entry['status'] ) ) : esc_html__( 'Completed', 'wpforms' );
	}

	/**
	 * Get value of additional information geodata.
	 *
	 * @since 1.5.5
	 *
	 * @param array $entry Entry data.
	 *
	 * @return string
	 */
	public function get_additional_info_geodata_value( $entry ) {

		$entry_meta_obj = wpforms()->get( 'entry_meta' );
		$location       = $entry_meta_obj ?
			$entry_meta_obj->get_meta(
				[
					'entry_id' => $entry['entry_id'],
					'type'     => 'location',
					'number'   => 1,
				]
			) :
			null;

		$val = '';

		if ( empty( $location[0]->data ) ) {
			return $val;
		}

		$location = json_decode( $location[0]->data, true );
		$loc_ary  = [];

		$map_query_args = [];

		$loc = '';

		if ( ! empty( $location['city'] ) ) {
			$map_query_args['q'] = $location['city'];
			$loc                 = $location['city'];
		}

		if ( ! empty( $location['region'] ) ) {
			if ( ! isset( $map_query_args['q'] ) ) {
				$map_query_args['q'] = '';
			}
			$map_query_args['q'] .= empty( $map_query_args['q'] ) ? $location['region'] : ',' . $location['region'];
			$loc                 .= empty( $loc ) ? $location['region'] : ', ' . $location['region'];
		}

		if ( ! empty( $location['latitude'] ) && ! empty( $location['longitude'] ) ) {
			if ( ! isset( $map_query_args['ll'] ) ) {
				$map_query_args['ll'] = '';
			}
			$map_query_args['ll'] .= $location['latitude'] . ',' . $location['longitude'];
			$loc_ary['latlong']    = [
				'label' => esc_html__( 'Lat/Long', 'wpforms' ),
				'val'   => $location['latitude'] . ', ' . $location['longitude'],
			];
		}

		if ( ! empty( $map_query_args ) ) {
			$map_query_args['z']      = apply_filters( 'wpforms_geolocation_map_zoom', '6' );
			$map_query_args['output'] = 'embed';

			$map = add_query_arg( $map_query_args, 'https://maps.google.com/maps' );

			$loc_ary['map'] = [
				'label' => esc_html__( 'Map', 'wpforms' ),
				'val'   => $map,
			];
		}

		if ( ! empty( $loc ) ) {
			$loc_ary['loc'] = [
				'label' => esc_html__( 'Location', 'wpforms' ),
				'val'   => $loc,
			];
		}

		if ( ! empty( $location['postal'] ) ) {
			$loc_ary['zip'] = [];

			if ( ! empty( $location['country'] ) && $location['country'] === 'US' ) {
				$loc_ary['zip']['label'] = esc_html__( 'Zipcode', 'wpforms' );
			} else {
				$loc_ary['zip']['label'] = esc_html__( 'Postal', 'wpforms' );
			}
			$loc_ary['zip']['val'] = $location['postal'];
		}

		if ( ! empty( $location['country'] ) ) {
			$loc_ary['country'] = [
				'label' => esc_html__( 'Country', 'wpforms' ),
				'val'   => $location['country'],
			];
		}

		return array_reduce(
			$loc_ary,
			static function ( $carry, $item ) {

				$item   = (array) $item;
				$carry .= $item['label'] . ': ' . $item['val'] . "\n";

				return $carry;
			},
			$val
		);
	}

	/**
	 * Get the value of additional payment status information.
	 *
	 * @since 1.5.9
	 *
	 * @param array $entry Entry array.
	 *
	 * @return string
	 */
	public function get_additional_info_pstatus_value( $entry ) {

		if ( $entry['type'] !== 'payment' ) {
			return '';
		}

		// Maybe get payment status from payments table.
		$payment = wpforms()->get( 'payment' )->get_by( 'entry_id', $entry['entry_id'] );

		if ( ! isset( $payment->status ) ) {
			return esc_html__( 'N/A', 'wpforms' );
		}

		return ucwords( sanitize_text_field( $payment->status ) );
	}

	/**
	 * Get value of additional payment information.
	 *
	 * @since 1.5.5
	 *
	 * @param array $entry Entry array.
	 *
	 * @return string
	 */
	public function get_additional_info_pginfo_value( $entry ) {

		// Maybe get payment status from payments table.
		$payment_table_data = wpforms()->get( 'payment' )->get_by( 'entry_id', $entry['entry_id'] );

		if ( empty( $payment_table_data ) ) {
			return '';
		}

		return $this->get_additional_info_from_payment_table( $payment_table_data );
	}

	/**
	 * Get deleted fields columns.
	 *
	 * @since 1.5.5
	 *
	 * @param array $existing_fields Existing fields array.
	 * @param array $request_data    Request data array.
	 *
	 * @return array Deleted fields columns
	 */
	public function get_deleted_fields_columns( $existing_fields, $request_data ) {

		global $wpdb;

		$table_name = wpforms()->get( 'entry_fields' )->table_name;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare(
			"SELECT DISTINCT field_id FROM $table_name WHERE `form_id` = %d AND `field_id` NOT IN ( " .
			implode( ',', wp_list_pluck( $existing_fields, 'id' ) ) . ' )',
			(int) $request_data['db_args']['form_id']
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		$deleted_fields_columns = [];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$db_result = $wpdb->get_col( $sql );

		foreach ( $db_result as $id ) {
			/* translators: %d - deleted field ID. */
			$deleted_fields_columns[ 'del_field_' . $id ] = sprintf( esc_html__( 'Deleted field #%d', 'wpforms' ), (int) $id );
		}

		return $deleted_fields_columns;
	}

	/**
	 * Get entry fields data.
	 *
	 * @since 1.5.5
	 *
	 * @param object $entry Entry data.
	 *
	 * @return array Fields data by ID.
	 */
	public function get_entry_fields_data( $entry ) {

		$fields_by_id = [];

		if ( empty( $entry->fields ) ) {
			return $fields_by_id;
		}

		$fields = json_decode( $entry->fields, true );

		if ( empty( $fields ) ) {
			return $fields_by_id;
		}

		foreach ( $fields as $field ) {

			if ( ! isset( $field['id'] ) ) {
				continue;
			}

			$fields_by_id[ $field['id'] ] = $field;
		}

		return $fields_by_id;
	}

	/**
	 * Get date format.
	 *
	 * @since 1.5.5
	 * @deprecated 1.8.5
	 */
	public function date_format() {

		_deprecated_function( __METHOD__, '1.8.5 of the WPForms plugin' );

		$this->export->data['date_format'] = empty( $this->export->data['date_format'] ) ? sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) ) : $this->export->data['date_format'];

		return $this->export->data['date_format'];
	}

	/**
	 * Get GMT offset in seconds.
	 *
	 * @since 1.5.5
	 * @deprecated 1.8.5
	 */
	public function gmt_offset_sec() {

		_deprecated_function( __METHOD__, '1.8.5 of the WPForms plugin' );

		$this->export->data['gmt_offset_sec'] = empty( $this->export->data['gmt_offset_sec'] ) ? get_option( 'gmt_offset' ) * 3600 : $this->export->data['gmt_offset_sec'];

		return $this->export->data['gmt_offset_sec'];
	}

	/**
	 * Get additional gateway info from payment table.
	 *
	 * @since 1.8.2
	 *
	 * @param array $payment_table_data Payment table data.
	 *
	 * @return string
	 */
	private function get_additional_info_from_payment_table( $payment_table_data ) {

		$value         = '';
		$ptinfo_labels = [
			'total_amount'        => esc_html__( 'Total', 'wpforms' ),
			'currency'            => esc_html__( 'Currency', 'wpforms' ),
			'gateway'             => esc_html__( 'Gateway', 'wpforms' ),
			'type'                => esc_html__( 'Type', 'wpforms' ),
			'mode'                => esc_html__( 'Mode', 'wpforms' ),
			'transaction_id'      => esc_html__( 'Transaction', 'wpforms' ),
			'customer_id'         => esc_html__( 'Customer', 'wpforms' ),
			'subscription_id'     => esc_html__( 'Subscription', 'wpforms' ),
			'subscription_status' => esc_html__( 'Subscription Status', 'wpforms' ),
		];

		array_walk(
			$payment_table_data,
			static function( $item, $key ) use ( $ptinfo_labels, &$value ) {
				if ( ! isset( $ptinfo_labels[ $key ] ) || wpforms_is_empty_string( $item ) ) {
					return;
				}

				if ( $key === 'total_amount' ) {
					$item = wpforms_format_amount( $item );
				}

				if ( $key === 'gateway' ) {

					$item = ValueValidator::get_allowed_gateways()[ $item ];
				}

				if ( $key === 'type' ) {

					$item = ValueValidator::get_allowed_types()[ $item ];
				}

				if ( $key === 'subscription_status' ) {

					$item = ucwords( str_replace( '-', ' ', $item ) );
				}

				$value .= $ptinfo_labels[ $key ] . ': ';
				$value .= $item . "\n";
			}
		);

		$meta_labels = [
			'payment_note'        => esc_html__( 'Payment Note', 'wpforms' ),
			'subscription_period' => esc_html__( 'Subscription Period', 'wpforms' ),
		];

		// Get meta data for payment.
		$meta = wpforms()->get( 'payment_meta' )->get_all( $payment_table_data->id );

		if ( empty( $meta ) ) {
			return $value;
		}

		array_walk(
			$meta,
			static function( $item, $key ) use ( $meta_labels, &$value ) {
				if ( ! isset( $meta_labels[ $key ], $item->value ) || wpforms_is_empty_string( $item->value ) ) {
					return;
				}

				$value .= $meta_labels[ $key ] . ': ';
				$value .= $item->value . "\n";
			}
		);

		return $value;
	}

	/**
	 * Get prepared fields.
	 *
	 * @since 1.8.5
	 *
	 * @param array $fields Fields.
	 *
	 * @return array
	 */
	private function get_prepared_fields( $fields ) {

		$fields = array_map(
			static function ( $field ) {
				$field['label'] = ! empty( $field['label'] ) ?
					trim( wp_strip_all_tags( $field['label'] ) ) :
					sprintf( /* translators: %d - field ID. */
						esc_html__( 'Field #%d', 'wpforms' ),
						(int) $field['id']
					);

				return $field;
			},
			$fields
		);

		// Reset array keys to save order of fields in JS.
		return array_values( $fields );
	}

	/**
	 * Exclude fields from a fields array.
	 *
	 * @since 1.8.5
	 *
	 * @param array $fields         Fields array.
	 * @param array $exclude_fields Fields to exclude.
	 *
	 * @return array
	 */
	private function exclude_fields( $fields, $exclude_fields ) {

		return array_filter(
			array_values( $fields ),
			static function ( $field ) use ( $exclude_fields ) {

				return isset( $field['type'] ) && ! in_array( $field['type'], $exclude_fields, true );
			}
		);
	}

	/**
	 * Get multiple field choices columns.
	 *
	 * @since 1.8.5
	 *
	 * @param array $field              Field data.
	 * @param array $form_data          Form data.
	 * @param bool  $is_dynamic_columns Is dynamic choices.
	 *
	 * @return array
	 */
	private function get_multiple_choices_columns( $field, $form_data, $is_dynamic_columns = false ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$type = $field['type'];

		if ( in_array( $type, [ 'select', 'checkbox', 'payment-checkbox' ], true ) ) {
			if ( $this->is_dynamic_choices( $field ) && $is_dynamic_columns ) {
				return wpforms_get_field_dynamic_choices( $field, $form_data['id'], $form_data );
			}

			return $this->get_choices( $form_data['id'], $field );
		}

		if ( $type === 'file-upload' ) {
			$max_file_number = [
				$this->get_max_files( $form_data['id'], $field['id'] ),
				$field['max_file_number'],
			];

			$count = max( $max_file_number );

			// Return array with exactly the same number of elements as max_file_number.
			$columns = array_fill( 0, $count, [] );

			return array_map(
				static function ( $column, $index ) use ( $field ) {

					$modified = $field['max_file_number'] < $index + 1 ? __( ' (modified)', 'wpforms' ) : '';

					$column['label'] = sprintf( '%s #%d%s', __( 'File', 'wpforms' ), $index + 1, $modified );

					return $column;
				},
				$columns,
				array_keys( $columns )
			);
		}

		if ( $type === 'likert_scale' ) {
			return $this->get_likert_scale_columns( $field, $form_data );
		}

		if ( $type === 'name' ) {
			return $field['format'] === 'first-last' ?
			    [
					[ 'label' => __( 'First', 'wpforms' ) ],
					[ 'label' => __( 'Last', 'wpforms' ) ],
				] :
				[
					[ 'label' => __( 'First', 'wpforms' ) ],
					[ 'label' => __( 'Middle', 'wpforms' ) ],
					[ 'label' => __( 'Last', 'wpforms' ) ],
				];
		}

		if ( $type === 'address' ) {
			return [
				[ 'label' => __( 'Address Line 1', 'wpforms' ) ],
				[ 'label' => __( 'Address Line 2', 'wpforms' ) ],
				[ 'label' => __( 'City', 'wpforms' ) ],
				[ 'label' => __( 'State', 'wpforms' ) ],
				[ 'label' => __( 'Zip/Postal Code', 'wpforms' ) ],
				[ 'label' => __( 'Country', 'wpforms' ) ],
			];
		}

		return [];
	}

	/**
	 * Get Likert Scale field columns.
	 *
	 * @since 1.8.5
	 *
	 * @param array $field     Field data.
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	private function get_likert_scale_columns( $field, $form_data ) {

		if ( isset( $this->values[ $field['id'] ] ) ) {
			return $this->values[ $field['id'] ];
		}

		// Get all values from database.
		$values = $this->get_entry_fields_values( $form_data['id'], $field['id'] );
		$keys   = [];

		foreach ( $values as $value_item ) {
			$value = json_decode( $value_item['value'], true );

			$value = $this->prepare_values( $value['value'] ?? '' );

			$value = $this->get_likert_scale_field_value( $value );

			$value_keys = array_keys( $value );

			foreach ( $value_keys as $value_key ) {
				$keys[] = $value_key;
			}
		}

		$keys = array_unique( $keys );

		// Get modified columns.
		$modified_columns = array_diff( $keys, $field['columns'] );

		// Add (modified) to column label.
		$modified_columns = array_map(
			static function ( $column ) {

				return $column . __( ' (modified)', 'wpforms' );
			},
			$modified_columns
		);

		// Add modified columns to columns array.
		$field['columns'] = array_merge( $field['columns'], $modified_columns );

		$columns = array_map(
			static function ( $column ) {

				return [ 'label' => $column ];
			},
			$field['columns']
		);

		$columns = array_values( $columns );

		$this->values[ $field['id'] ] = $columns;

		return $columns;
	}

	/**
	 * Get field choices.
	 *
	 * @since 1.8.5
	 *
	 * @param int   $form_id Form ID.
	 * @param array $field   Field data.
	 *
	 * @return array Choices.
	 */
	private function get_choices( $form_id, $field ) {

		$field_choices = $field['choices'];
		$field_id      = $field['id'];

		foreach ( $field_choices as $key => $choice ) {
			// Check if choice label not empty.
			if ( ! empty( $choice['label'] ) ) {
				continue;
			}

			// If choice has no label, set default label.
			$choice['label'] = sprintf( /* translators: %d - choice ID. */
				esc_html__( 'Choice %d', 'wpforms' ),
				$key
			);

			$field_choices[ $key ] = $choice;
		}

		$labels = array_column( $field_choices, 'label' );

		$choices = $this->get_all_existing_choices( $form_id, $field_id, $field['type'] );

		foreach ( $choices as $choice ) {
			$label = $choice['label'] ?? $choice;
			// Check if choice already exists.
			if ( in_array( $label, $labels, true ) ) {
				continue;
			}

			// Add modified choice to choices array.
			$field_choices[] = [
				'label'    => $label,
				'value'    => $choice['value'] ?? '',
				'modified' => true,
			];
		}

		return array_values( $field_choices );
	}

	/**
	 * Get all existing choices from database.
	 *
	 * @since 1.8.5
	 *
	 * @param int    $form_id  Form ID.
	 * @param int    $field_id Field ID.
	 * @param string $type     Field type.
	 *
	 * @return array Choices.
	 */
	private function get_all_existing_choices( int $form_id, int $field_id, string $type ): array {

		if ( isset( $this->values[ $field_id ] ) ) {
			return $this->values[ $field_id ];
		}

		$entry_fields_values = $this->get_entry_fields_values( $form_id, $field_id );

		if ( $type === 'payment-checkbox' ) {
			return $this->get_all_payment_choices( $entry_fields_values, $field_id );
		}

		$choices = [];

		foreach ( $entry_fields_values as $row_value ) {
			$values = $this->prepare_values( $row_value['value'] ?? '' );
			$values = array_map(
				static function ( $value ) {

					return rtrim( $value, ';' );
				},
				$values
			);
			$values = array_filter( $values );

			foreach ( $values as $value ) {
				$choices[] = $value;
			}
		}

		$choices = array_unique( $choices );

		$this->values[ $field_id ] = $choices;

		return $choices;
	}

	/**
	 * Get all payment choices.
	 * Retrieve correct choices labels from Entry data.
	 *
	 * @since 1.8.6
	 *
	 * @param array $entry_fields_values Entry fields values.
	 * @param int   $field_id            Field ID.
	 *
	 * @return array Payment choices.
	 */
	private function get_all_payment_choices( array $entry_fields_values, int $field_id ): array {

		$choices = [];

		foreach ( $entry_fields_values as $row_value ) {
			$entry_id = $row_value['entry_id'];

			// Get entry for current Payment Checkbox field value.
			$entry = wpforms()->get( 'entry' )->get( $entry_id );

			// Get field values for current entry.
			$entry_fields_data = $this->get_entry_fields_data( $entry );

			// Filter entry fields data by current field ID.
			$entry_fields_data = array_filter(
				$entry_fields_data,
				static function ( $field ) use ( $field_id ) {

					return (int) $field['id'] === $field_id;
				}
			);

			// Reset array keys.
			$entry_fields_data = array_values( $entry_fields_data );

			// Entry fields data contains only one element in this case.
			$field_values = $this->get_field_values( $entry_fields_data[0] );

			// Get field choices.
			$values = $entry_fields_data[0]['value'] ?? '';

			// Prepare values.
			$values = $this->prepare_values( $values );

			// Combine field values and choices.
			// Where field values are keys and choices are values.
			$field_values = array_combine( $field_values, $values );

			// Add field values to choices array.
			foreach ( $field_values as $field_key => $field_value ) {
				$choices[] = [
					'label' => $field_key,
					'value' => $field_value,
				];
			}
		}

		$choices = array_unique( $choices, SORT_REGULAR );

		$this->values[ $field_id ] = $choices;

		// Return unique choices.
		return $choices;
	}

	/**
	 * Get max files number.
	 *
	 * @since 1.8.5
	 *
	 * @param int $form_id  Form ID.
	 * @param int $field_id Field ID.
	 *
	 * @return int
	 */
	private function get_max_files( $form_id, $field_id ) {

		if ( isset( $this->values[ $field_id ] ) ) {
			return $this->values[ $field_id ];
		}

		$entry_fields_values = $this->get_entry_fields_values( $form_id, $field_id );

		$counts = [];

		foreach ( $entry_fields_values as $row_value ) {
			$values = explode( "\n", $row_value['value'] );

			$counts[] = count( array_filter( $values ) );
		}

		if ( empty( $counts ) ) {
			return 0;
		}

		$max_files = max( $counts );

		$this->values[ $field_id ] = $max_files;

		return $max_files;
	}

	/**
	 * Get entry fields values.
	 *
	 * @since 1.8.5
	 *
	 * @param int $form_id  Form ID.
	 * @param int $field_id Field ID.
	 *
	 * @return array
	 */
	private function get_entry_fields_values( $form_id, $field_id ) {

		global $wpdb;

		$table_name = wpforms()->get( 'entry_fields' )->table_name;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare(
			"SELECT DISTINCT `value`, `entry_id` FROM $table_name WHERE `form_id` = %d AND `field_id` = %d",
			$form_id,
			$field_id
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Prepare values for export.
	 *
	 * @since 1.8.6
	 *
	 * @param string $value Value.
	 *
	 * @return array Values.
	 */
	private function prepare_values( string $value ): array {

		$values = explode( "\n", $value );

		return array_map(
			static function ( $single_value ) {

				return wpforms_decode_string( trim( $single_value ) );
			},
			$values
		);
	}
}
