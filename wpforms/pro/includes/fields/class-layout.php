<?php
/**
 * Suppress inspection on private properties `frontend_obj` and `builder_obj`.
 * They are used via getter `get_object()`.
 *
 * @noinspection PhpPropertyOnlyWrittenInspection
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPForms\Pro\Forms\Fields\Layout\Builder;
use WPForms\Pro\Forms\Fields\Layout\Frontend;

/**
 * Layout field.
 *
 * @since 1.7.7
 */
class WPForms_Field_Layout extends WPForms_Field {

	/**
	 * Handle name for `wp_register_styles`.
	 *
	 * @since 1.7.7
	 *
	 * @var string
	 */
	const STYLE_HANDLE = 'wpforms-layout';

	/**
	 * Instance of the Builder class for Layout Field.
	 *
	 * @since 1.7.7
	 *
	 * @var Builder
	 */
	private $builder_obj;

	/**
	 * Layout presets.
	 *
	 * @since 1.7.7
	 *
	 * @var array
	 */
	const PRESETS = [
		'50-50',
		'67-33',
		'33-67',
		'33-33-33',
		'50-25-25',
		'25-25-50',
		'25-50-25',
		'25-25-25-25',
	];

	/**
	 * Field types that not allowed to drag into the column.
	 *
	 * @since 1.7.7
	 *
	 * @var array
	 */
	const NOT_ALLOWED_FIELDS = [
		'layout',
		'pagebreak',
		'entry-preview',
	];

	/**
	 * Primary class constructor.
	 *
	 * @since 1.7.7
	 */
	public function init() {

		// Define field type information.
		$this->name     = esc_html__( 'Layout', 'wpforms' );
		$this->keywords = esc_html__( 'column, row', 'wpforms' );
		$this->type     = 'layout';
		$this->icon     = 'fa-columns';
		$this->order    = 150;
		$this->group    = 'fancy';

		// Default settings.
		$this->defaults = [
			'label'       => $this->name,
			'name'        => $this->name,
			'description' => '',
			'label_hide'  => '1',
			'size'        => 'large',
			'preset'      => '50-50',
			'columns'     => [
				0 => [
					'width_custom' => '',
					'width_preset' => '50',
					'fields'       => [],
				],
				1 => [
					'width_custom' => '',
					'width_preset' => '50',
					'fields'       => [],
				],
			],
		];

		$this->init_objects();
		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.7.7
	 */
	private function hooks() {

		add_filter( 'wpforms_field_new_default', [ $this, 'field_new_default' ] );
		add_filter( 'wpforms_entry_single_data', [ $this, 'filter_fields_remove_layout' ], 1000, 3 );
		add_filter( "wpforms_pro_admin_entries_edit_is_field_displayable_{$this->type}", '__return_false' );
		add_filter( 'wpforms_pro_admin_entries_print_preview_fields', [ $this, 'filter_entries_print_preview_fields' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'gutenberg_enqueues' ] );
		add_filter( 'register_block_type_args', [ $this, 'register_block_type_args' ], 20, 2 );
		add_filter( 'wpforms_conversational_form_detected', [ $this, 'cf_frontend_hooks' ], 10, 2 );
	}

	/**
	 * Initialize sub-objects.
	 *
	 * @since 1.7.7
	 */
	private function init_objects() {

		$is_ajax = wp_doing_ajax();

		if ( $is_ajax || wpforms_is_admin_page( 'builder' ) ) {
			$this->builder_obj = $this->get_object( 'Builder' );
		}

		if ( $is_ajax || ! is_admin() ) {
			$this->frontend_obj = $this->get_object( 'Frontend' );
		}
	}

	/**
	 * Define new field defaults.
	 *
	 * @since 1.7.7
	 *
	 * @param array $field Field settings.
	 *
	 * @return array Field settings.
	 */
	public function field_new_default( $field ) {

		if ( $field['type'] !== $this->type ) {
			return $field;
		}

		return wp_parse_args( $field, $this->defaults );
	}

	/**
	 * Get filtered presets.
	 *
	 * @since 1.7.7
	 *
	 * @return array Presets array.
	 */
	public function get_presets() {

		/**
		 * Filters the layout field presets list.
		 *
		 * @since 1.7.7
		 *
		 * @param array $presets An array of the layout field presets.
		 */
		return (array) apply_filters( 'wpforms_field_layout_get_presets', self::PRESETS );
	}

	/**
	 * Get filtered not allowed fields list.
	 *
	 * @since 1.7.7
	 *
	 * @return array Not allowed fields list.
	 */
	public function get_not_allowed_fields() {

		/**
		 * Filters the list of the fields that not allowed to be placed inside the column.
		 *
		 * @since 1.7.7
		 *
		 * @param array $not_allowed_fields An array of the not allowed fields types.
		 */
		return (array) apply_filters( 'wpforms_field_layout_get_not_allowed_fields', self::NOT_ALLOWED_FIELDS );
	}

	/**
	 * Field options panel inside the builder.
	 *
	 * @since 1.7.7
	 *
	 * @param array $field Field settings.
	 */
	public function field_options( $field ) {

		$this->get_object( 'Builder' )->field_options( $field );
	}

	/**
	 * Field preview inside the builder.
	 *
	 * @since 1.7.7
	 *
	 * @param array $field Field settings.
	 */
	public function field_preview( $field ) {

		$this->get_object( 'Builder' )->field_preview( $field );
	}

	/**
	 * Field display on the form front-end.
	 *
	 * @since 1.7.7
	 *
	 * @param array $field      Field settings.
	 * @param array $deprecated Deprecated.
	 * @param array $form_data  Form data and settings.
	 */
	public function field_display( $field, $deprecated, $form_data ) {

		$this->get_object( 'Frontend' )->field_display( $field, $form_data );
	}

	/**
	 * Filter base level fields.
	 *
	 * @since 1.7.7
	 *
	 * @param array $fields Form fields.
	 *
	 * @return array Form fields without the fields in the columns.
	 */
	public function filter_base_fields( $fields ) {

		$fields_in_columns = [];

		foreach ( $fields as $field ) {

			if ( empty( $field['type'] ) || $field['type'] !== $this->type || empty( $field['columns'] ) ) {
				continue;
			}

			foreach ( $field['columns'] as $column ) {

				if ( empty( $column['fields'] ) || ! is_array( $column['fields'] ) ) {
					continue;
				}

				array_push( $fields_in_columns, ...$column['fields'] );
			}
		}

		foreach ( $fields_in_columns as $field_id ) {
			unset( $fields[ $field_id ] );
		}

		return $fields;
	}

	/**
	 * Filter fields data. Remove the Layout fields from the fields list.
	 *
	 * @since 1.7.7
	 *
	 * @param array $fields    Fields data.
	 * @param array $entry     Entry data.
	 * @param array $form_data Form data.
	 *
	 * @return array Fields data without the layout fields.
	 */
	public function filter_fields_remove_layout( $fields, $entry, $form_data ) {

		if ( empty( $fields ) ) {
			return $fields;
		}

		foreach ( $fields as $id => $field ) {

			if ( empty( $field['type'] ) || $field['type'] !== $this->type ) {
				continue;
			}

			unset( $fields[ $id ] );
		}

		return $fields;
	}

	/**
	 * Filter fields data. Add the fields from the columns to the fields list.
	 *
	 * @since 1.8.1.2
	 *
	 * @param array $fields Fields data.
	 *
	 * @return array
	 */
	public function filter_entries_print_preview_fields( $fields ) { // phpcs:ignore Generic.Metrics.NestingLevel.MaxExceeded

		foreach ( $fields as $key => $field ) {
			if ( $field['type'] !== $this->type ) {
				continue;
			}

			foreach ( $field['columns'] as $column_index => $column ) {
				foreach ( $column['fields'] as $layout_field_index => $layout_field_id ) {
					if ( empty( $fields[ $layout_field_id ] ) ) {
						unset( $fields[ $key ]['columns'][ $column_index ]['fields'][ $layout_field_index ] );
						continue;
					}

					$fields[ $key ]['columns'][ $column_index ]['fields'][ $layout_field_index ] = $fields[ $layout_field_id ];

					unset( $fields[ $layout_field_id ] );
				}
			}
		}

		return $fields;
	}

	/**
	 * Hooks that must be registered only on the Conversational Forms Frontend page.
	 *
	 * @since 1.7.7
	 *
	 * @param array   $form_data Form data.
	 * @param integer $form_id   Form Id.
	 */
	public function cf_frontend_hooks( $form_data, $form_id ) { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		// This filter is needed to remove the Layout fields from the CF frontend.
		add_filter( 'wpforms_frontend_form_data', [ $this, 'filter_fields_remove_layout_cf' ] );
	}

	/**
	 * Filter fields data. Remove the Layout fields from the fields list in CF.
	 *
	 * @since 1.7.7
	 *
	 * @param array $form_data Form data.
	 *
	 * @return array Fields data without the layout fields.
	 */
	public function filter_fields_remove_layout_cf( $form_data ) {

		$form_data['fields'] = $this->filter_fields_remove_layout( $form_data['fields'], [], $form_data );

		return $form_data;
	}

	/**
	 * Load enqueues for the Gutenberg editor in WP version < 5.5.
	 *
	 * @since 1.7.7
	 */
	public function gutenberg_enqueues() {

		if ( version_compare( get_bloginfo( 'version' ), '5.5', '>=' ) ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			self::STYLE_HANDLE,
			WPFORMS_PLUGIN_URL . "assets/pro/css/fields/layout{$min}.css",
			[],
			WPFORMS_VERSION
		);
	}

	/**
	 * Set editor style handle for block type editor.
	 *
	 * @since 1.7.7
	 *
	 * @param array  $args       Array of arguments for registering a block type.
	 * @param string $block_type Block type name including namespace.
	 */
	public function register_block_type_args( $args, $block_type ) {

		if ( $block_type !== 'wpforms/form-selector' || ! is_admin() ) {
			return $args;
		}

		$min = wpforms_get_min_suffix();

		// CSS.
		wp_register_style(
			self::STYLE_HANDLE,
			WPFORMS_PLUGIN_URL . "assets/pro/css/fields/layout{$min}.css",
			[ $args['editor_style'] ],
			WPFORMS_VERSION
		);

		$args['editor_style'] = self::STYLE_HANDLE;

		return $args;
	}

	/**
	 * Get object.
	 *
	 * @since 1.7.7
	 *
	 * @param string $class Class name, `Builder` or `Frontend`.
	 *
	 * @return object
	 */
	private function get_object( $class ) {

		$property   = strtolower( $class ) . '_obj';
		$fqdn_class = 'WPForms\Pro\Forms\Fields\Layout\\' . $class;

		if ( ! is_a( $this->$property, $fqdn_class ) ) {
			$this->$property = new $fqdn_class( $this );
		}

		return $this->$property;
	}
}

new WPForms_Field_Layout();
