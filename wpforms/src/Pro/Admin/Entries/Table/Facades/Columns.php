<?php

namespace WPForms\Pro\Admin\Entries\Table\Facades;

use WP_Error; // phpcs:ignore WPForms.PHP.UseStatement.UnusedUseStatement
use WPForms\Admin\Base\Tables\Facades\ColumnsBase;
use WPForms\Pro\Admin\Entries\Table\DataObjects\FieldColumn;
use WPForms\Pro\Admin\Entries\Table\DataObjects\MetaColumn;
use WPForms\Pro\Admin\Entries\Table\DataObjects\Column; // phpcs:ignore WPForms.PHP.UseStatement.UnusedUseStatement
use WPForms\Pro\Admin\Entries\ListTable;
/**
 * Column facade class.
 *
 * Hides the complexity of columns' collection behind a simple interface.
 *
 * @since 1.8.6
 */
class Columns extends ColumnsBase {

	/**
	 * The ID of the table column called "Entry ID".
	 *
	 * @since 1.8.6
	 *
	 * @var int
	 */
	const COLUMN_ENTRY_ID = -1;

	/**
	 * The ID of the table column called "Entry Notes".
	 *
	 * @since 1.8.6
	 *
	 * @var int
	 */
	const COLUMN_NOTES_COUNT = -2;

	/**
	 * Get columns.
	 *
	 * Returns all possible columns for Entries table. It returns both entry meta and form fields columns.
	 *
	 * @since 1.8.6
	 *
	 * @param int|string $form_id Form ID.
	 *
	 * @return Column[] Array of columns as objects.
	 */
	protected static function get_all( $form_id = 0 ): array {

		$form_id = $form_id ? absint( $form_id ) : self::get_current_form_id();

		static $form_columns = [];

		if ( ! isset( $form_columns[ $form_id ] ) ) {
			$form_columns[ $form_id ] = self::get_meta_columns() + self::get_field_columns( $form_id );
		}

		return $form_columns[ $form_id ];
	}

	/**
	 * Get field columns.
	 *
	 * Returns array of columns for the form fields. Array is indexed by field ID (positive int).
	 *
	 * @since 1.8.6
	 *
	 * @param int|string $form_id Form ID.
	 *
	 * @return FieldColumn[] Array of columns as objects.
	 */
	public static function get_field_columns( $form_id = 0 ): array {

		$fields  = self::get_form_fields( $form_id );
		$columns = [];

		// Default forbidden fields types list.
		$default_forbidden_fields = [
			'divider',
			'layout',
			'pagebreak',
			'internal-information',
			'html',
			'content',
			'entry-preview',
			'captcha',
		];

		/**
		 * Filter forbidden field types.
		 *
		 * In addition to this list, the following fields are always forbidden:
		 * divider, layout, pagebreak, internal-information, html, content, entry-preview, captcha,
		 *
		 * @since 1.8.6
		 *
		 * @param array      $forbidden_fields Forbidden field types.
		 * @param int|string $form_id          Form ID.
		 */
		$filtered_forbidden_fields = (array) apply_filters(
			'wpforms_pro_admin_entries_table_facades_columns_get_field_columns_forbidden_fields',
			[],
			$form_id
		);

		// Defaults is the strictly forbidden field types.
		$forbidden_fields = array_merge( $filtered_forbidden_fields, $default_forbidden_fields );

		foreach ( $fields as $id => $field ) {
			if ( ! isset( $field['type'] ) || in_array( $field['type'], $forbidden_fields, true ) ) {
				continue;
			}

			$columns[ $id ] = new FieldColumn( $id, $field );
		}

		return $columns;
	}

	/**
	 * Get entry meta-columns.
	 *
	 * Returns array of columns for the entry meta. Array is indexed by two different types of indexes:
	 * - negative int - for entry_id column and entry_notes column.
	 * - string - for all other columns.
	 *
	 * @since 1.8.6
	 *
	 * @return MetaColumn[] Array of columns as objects.
	 */
	public static function get_meta_columns(): array {

		$columns_data = [
			self::COLUMN_ENTRY_ID    => [
				'label' => esc_html__( 'Entry ID', 'wpforms' ),
				'type'  => 'entry_id',
			],
			self::COLUMN_NOTES_COUNT => [
				'label' => esc_html__( 'Entry Notes', 'wpforms' ),
				'type'  => 'notes_count',
			],
			'date'                   => [
				'label' => esc_html__( 'Date', 'wpforms' ),
			],
			'type'                   => [
				'label' => esc_html__( 'Entry Type', 'wpforms' ),
			],
			'user_ip'                => [
				'label' => esc_html__( 'User IP', 'wpforms' ),
			],
			'user_agent'             => [
				'label' => esc_html__( 'User Agent', 'wpforms' ),
			],
			'user_uuid'              => [
				'label' => esc_html__( 'Unique User ID', 'wpforms' ),
			],
		];

		$form_fields = self::get_form_fields( self::get_current_form_id() );

		if ( wpforms_has_payment( 'form', $form_fields ) ) {
			$columns_data['payment'] = [
				'label' => esc_html__( 'Payment', 'wpforms' ),
			];
		}

		/**
		 * Filter entry meta columns.
		 *
		 * @since 1.8.6
		 *
		 * @param array $columns_data Array of columns data.
		 */
		$columns_data = apply_filters( 'wpforms_pro_admin_entries_table_facades_columns_get_meta_columns_columns_data', $columns_data );

		$columns_data = array_map(
			static function ( $column ) {
				$column['type']      = ! empty( $column['type'] ) ? (string) $column['type'] : '';
				$column['draggable'] = (bool) ( $column['draggable'] ?? true );

				return $column;
			},
			$columns_data
		);

		$columns = [];

		foreach ( $columns_data as $id => $settings ) {
			$columns[ $id ] = new MetaColumn( $id, $settings );
		}

		return $columns;
	}

	/**
	 * Get columns' keys for the columns which user selected to be displayed.
	 *
	 * It returns an array of keys in the order they should be displayed,
	 * e.g., [ -1, 'entry_id', 'notes_count', 'date', 2, 1 ].
	 * The returned array contains draggable and non-draggable columns.
	 *
	 * @since 1.8.6
	 *
	 * @return array
	 */
	public static function get_selected_columns_keys(): array {

		$form_id = self::get_current_form_id();

		if ( ! $form_id ) {
			return [];
		}

		$form_obj = wpforms()->get( 'form' );

		if ( ! $form_obj ) {
			return [];
		}

		$form = $form_obj->get( $form_id, [ 'content_only' => true ] );

		return empty( $form['meta']['entry_columns'] ) ? [] : $form['meta']['entry_columns'];
	}

	/**
	 * Get columns' keys for the columns which user selected to be displayed.
	 *
	 * @since 1.8.6
	 *
	 * @return array
	 */
	public static function get_default_columns_keys(): array {

		$all_columns   = self::get_all();
		$form_data     = self::get_form_data();
		$has_payment   = wpforms_has_payment( 'form', $form_data );
		$field_columns = $has_payment ? 2 : 3;

		if ( empty( $form_data['fields'] ) ) {
			$default_columns = [];
		} else {
			$default_columns = array_filter(
				$all_columns,
				static function ( $column ) {

					return $column->is_form_field();
				}
			);
		}

		$default_columns = array_slice( $default_columns, 0, $field_columns, true );

		if ( $has_payment ) {
			$default_columns['payment'] = $all_columns['payment'];
		}

		/**
		 * Filters whether to show the status column in the entry table.
		 * This filter is often used to trigger by add-ons to show the status column for forms.
		 *
		 * @since 1.6.0
		 *
		 * @param bool  $show_status Whether to show the status column. Default false.
		 * @param array $form_data   Form data.
		 *
		 * @return bool
		 */
		if ( apply_filters( 'wpforms_entries_table_column_status', false, $form_data ) ) { // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			$default_columns['type'] = $all_columns['type'];
		}

		$default_columns['date'] = $all_columns['date'];

		return array_keys( $default_columns );
	}

	/**
	 * Get draggable columns ordered keys.
	 *
	 * The Expected result is array of keys in the order they should be displayed,
	 * e.g., [ -1, 'entry_id', 'notes_count', 'date', 2, 1 ].
	 * It will return custom order if user has already saved it, otherwise it will return default order.
	 *
	 * @since 1.8.6
	 *
	 * @return array
	 */
	public static function get_draggable_ordered_keys(): array {

		// First, let's check if user has already saved custom order.
		$custom_order = self::get_selected_columns_keys();
		$all_columns  = self::get_all();

		if ( $custom_order ) {
			// If a user has saved custom order, let's filter out columns which are not draggable.
			return array_filter(
				$custom_order,
				static function ( $id ) use ( $all_columns ) {

					return isset( $all_columns[ $id ] ) && $all_columns[ $id ]->is_draggable();
				}
			);
		}

		return self::get_default_columns_keys();
	}

	/**
	 * Save columns' keys array into form's entry_columns meta.
	 *
	 * If columns' keys' array is empty, it will delete entry_columns meta.
	 *
	 * @since 1.8.6
	 *
	 * @param int   $form_id      Form ID.
	 * @param array $columns_keys Array of columns keys in desired display order.
	 *
	 * @return false|int|WP_Error
	 */
	public static function sanitize_and_save_columns( int $form_id, array $columns_keys ) {

		$columns_keys = array_map( [ __CLASS__, 'sanitize_column_key' ], $columns_keys );
		$columns_keys = array_filter( $columns_keys, [ __CLASS__, 'validate_column_key' ] );

		$form_obj = wpforms()->get( 'form' );

		if ( ! $form_obj ) {
			return false;
		}

		// Remove KSES filters before updating meta for forms and their fields which contain HTML.
		// If we don't do this, forms for users who don't have 'unfiltered_html' capabilities can get corrupt due to conflicts with wp_kses().
		kses_remove_filters();

		$result = $columns_keys
			? $form_obj->update_meta( $form_id, 'entry_columns', $columns_keys, [ 'cap' => 'view_entries_form_single' ] )
			: $form_obj->delete_meta( $form_id, 'entry_columns', [ 'cap' => 'view_entries_form_single' ] );

		// Re-initialize KSES filters for users who don't have 'unfiltered_html' capabilities.
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			kses_init_filters();
		}

		return $result;
	}

	/**
	 * Sanitize column key.
	 *
	 * @since 1.8.6
	 *
	 * @param string|int $key Column key.
	 *
	 * @return int|string
	 */
	public static function sanitize_column_key( $key ) {

		return is_numeric( $key ) ? (int) $key : sanitize_key( $key );
	}

	/**
	 * Get form data for given form ID.
	 *
	 * If form ID is not provided, it will try to get it from $_REQUEST['form_id'].
	 *
	 * @since 1.8.6
	 *
	 * @param int|string $form_id Form ID.
	 *
	 * @return array
	 */
	private static function get_form_data( $form_id = 0 ): array {

		$form_id = $form_id ? absint( $form_id ) : self::get_current_form_id();

		if ( ! $form_id ) {
			return [];
		}

		$form_obj = wpforms()->get( 'form' );

		if ( ! $form_obj ) {
			return [];
		}

		$form = $form_obj->get( $form_id, [ 'content_only' => true ] );

		if ( ! $form ) {
			return [];
		}

		return $form;
	}

	/**
	 * Get form fields for given form ID.
	 *
	 * If form ID is not provided, it will try to get it from $_REQUEST['form_id'].
	 *
	 * @since 1.8.6
	 *
	 * @param int|string $form_id Form ID.
	 *
	 * @return array
	 */
	private static function get_form_fields( $form_id = 0 ): array {

		$form = self::get_form_data( $form_id );

		return empty( $form['fields'] ) ? [] : $form['fields'];
	}

	/**
	 * Get current form ID.
	 *
	 * Helper to obtain form ID from $_REQUEST['form_id'].
	 *
	 * @since 1.8.6
	 *
	 * @return int
	 */
	private static function get_current_form_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ! empty( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : 0;
	}

	/**
	 * Get columns' data ready to use in the list table object.
	 *
	 * @since 1.8.6
	 *
	 * @param ListTable|mixed $list_table List table object.
	 *
	 * @return array
	 */
	public static function get_list_table_columns( $list_table ): array {

		if ( ! $list_table instanceof ListTable ) {
			return [];
		}

		$columns = [
			'cb'         => '<input type="checkbox" />',
			'indicators' => '',
		];

		$order       = self::get_draggable_ordered_keys();
		$all_columns = self::get_all();

		foreach ( $order as $column_id ) {
			$columns[ $all_columns[ $column_id ]->get_slug() ] = $all_columns[ $column_id ]->get_label();
		}

		if ( ! $list_table->is_trash_list() || wpforms_current_user_can( 'delete_entries_form_single', $list_table->form_id ) ) {
			$columns['actions'] = esc_html__( 'Actions', 'wpforms' );
		}

		/**
		 * Filters of all the Entries list table columns.
		 *
		 * @since 1.0.0
		 *
		 * @param array $columns   The Entries list table columns.
		 * @param array $form_data Form data.
		 *
		 * @return bool
		 */
		return apply_filters( 'wpforms_entries_table_columns', $columns, self::get_form_data() ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}
}
