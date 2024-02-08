<?php

namespace WPForms\Pro\Admin\Entries\Export\Traits;

/**
 * Export trait.
 *
 * @since 1.8.5
 */
trait Export {

	/**
	 * Get dynamic columns notice.
	 *
	 * @since 1.8.5
	 *
	 * @param int $dynamic_choices_count Dynamic choices count.
	 *
	 * @return string
	 */
	private function get_dynamic_columns_notice( $dynamic_choices_count ) {

		return sprintf(
			/* translators: %d - dynamic columns count. */
			esc_html__( 'This form has %d dynamic columns. Exporting dynamic columns will increase the size of the exported table.', 'wpforms' ),
			$dynamic_choices_count
		);
	}

	/**
	 * Get dynamic choices count.
	 *
	 * @since 1.8.5
	 *
	 * @param array $fields Fields array.
	 *
	 * @return int
	 */
	private function get_dynamic_choices_count( $fields ) {

		$count = 0;

		if ( empty( $fields ) ) {
			return $count;
		}

		foreach ( $fields as $field ) {
			if ( $this->is_dynamic_choices( $field ) && $this->is_multiple_input( $field ) ) {
				$dynamic_choices = wpforms_get_field_dynamic_choices(
					$field,
					$this->export->data['form_data']['id'],
					$this->export->data['form_data']
				);

				$count += count( $dynamic_choices );
			}
		}

		return $count;
	}

	/**
	 * Check if field is multiple input.
	 *
	 * @since 1.8.5
	 *
	 * @param array $field Field data.
	 *
	 * @return bool
	 */
	private function is_multiple_input( $field ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		/**
		 * Filter to allow multiple input for specific fields.
		 *
		 * @since 1.8.5
		 *
		 * @param bool  $is_multiple_input Is multiple input.
		 * @param array $field             Field data.
		 */
		if ( ! apply_filters( 'wpforms_pro_admin_entries_export_allow_multiple_input_field', true, $field ) ) {
			return false;
		}

		$type = $field['type'];

		$available_types = [
			'name',
			'address',
			'select',
			'checkbox',
			'file-upload',
			'likert_scale',
			'payment-checkbox',
		];

		if ( ! in_array( $type, $available_types, true ) ) {
			return false;
		}

		// Check if select is multiple.
		if ( $type === 'select' && ! empty( $field['multiple'] ) ) {
			return true;
		}

		// Check if file upload is multiple.
		if ( $type === 'file-upload' && $field['max_file_number'] > 1 ) {
			return true;
		}

		// Check if name field is multi-input.
		if ( $type === 'name' && in_array( $field['format'], [ 'first-last', 'first-middle-last' ], true ) ) {
			return true;
		}

		// The rest of the fields are multiple choice by default.
		if ( in_array( $type, [ 'checkbox', 'payment-checkbox', 'likert_scale', 'address' ], true ) ) {
			return true;
		}

		// Return false for single select and file upload fields.
		return false;
	}

	/**
	 * Check if field has dynamic choices.
	 *
	 * @since 1.8.5
	 *
	 * @param array $field Field data.
	 *
	 * @return bool
	 */
	private function is_dynamic_choices( $field ) {

		return isset( $field['dynamic_choices'] ) && $field['dynamic_choices'];
	}

	/**
	 * Get available form entry statuses.
	 *
	 * @since 1.8.5
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return array
	 */
	private function get_available_form_entry_statuses( $form_id ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$statuses = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT `status` FROM {$wpdb->prefix}wpforms_entries WHERE `form_id` = %d",
				$form_id
			)
		);

		$statuses = array_map(
			static function ( $status ) {

				return [
					'value' => $status ? $status : 'published', // published entries have empty status.
					'label' => $status ? ucwords( sanitize_text_field( $status ) ) : __( 'Published', 'wpforms' ), // null should be 'Published' for UI.
				];
			},
			$statuses
		);

		return array_values( array_filter( $statuses ) );
	}

	/**
	 * Get field ID from multiple field ID.
	 *
	 * @since 1.8.6
	 *
	 * @param string $col_id Column ID.
	 *
	 * @return string
	 */
	private function get_multiple_field_id( string $col_id ): string {

		// Get multiple field id. Contains field id and value id.
		// See get_csv_cols method.
		// $col_id: 'multiple_field_' . $field_id . '_' . $key.
		$id = str_replace( 'multiple_field_', '', $col_id );

		// Get field id and value id.
		// $id: $field_id . '_' . $key.
		$multiple_key = explode( '_', $id );

		// The First element is field id.
		return $multiple_key[0] ?? '';
	}

	/**
	 * Check if value should be skipped.
	 *
	 * @since 1.8.6
	 *
	 * @param string $value  Field value.
	 * @param array  $fields Fields array.
	 * @param string $col_id Column ID.
	 *
	 * @return bool
	 */
	private function is_skip_value( string $value, array $fields, string $col_id ): bool {

		// No skip for AJAX requests.
		if ( wpforms_is_ajax() ) {
			return false;
		}

		$field = $fields[ $this->get_multiple_field_id( $col_id ) ] ?? [];

		if ( empty( $field ) ) {
			return false;
		}

		// Skip empty values only for available fields.
		$available_types = [
			'select',
			'checkbox',
			'payment-checkbox',
		];

		if ( ! in_array( $field['type'], $available_types, true ) ) {
			return false;
		}

		/**
		 * Filters whether to skip not selected choices for multiple fields.
		 *
		 * @since 1.8.6
		 *
		 * @param bool $skip_not_selected_choices Whether to skip not selected choices.
		 */
		$skip_not_selected_choices = apply_filters( 'wpforms_pro_admin_entries_export_skip_not_selected_choices', false ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return empty( $value ) && $skip_not_selected_choices;
	}
}
