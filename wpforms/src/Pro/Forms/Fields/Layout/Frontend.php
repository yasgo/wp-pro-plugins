<?php

namespace WPForms\Pro\Forms\Fields\Layout;

use WPForms\Frontend\Frontend as WPForms_Frontend;
use WPForms_Field_Layout;

/**
 * Class Frontend for Layout Field.
 *
 * @since 1.7.7
 */
class Frontend {

	/**
	 * Instance of the WPForms_Field_Layout class.
	 *
	 * @since 1.7.7
	 *
	 * @var WPForms_Field_Layout
	 */
	private $field_obj;

	/**
	 * Instance of the WPForms\Frontend\Frontend class.
	 *
	 * @since 1.7.7
	 *
	 * @var WPForms_Frontend
	 */
	private $frontend;

	/**
	 * Class constructor.
	 *
	 * @since 1.7.7
	 *
	 * @param WPForms_Field_Layout $field_obj Instance of the WPForms_Field_Layout class.
	 */
	public function __construct( $field_obj ) {

		$this->field_obj = $field_obj;
		$this->frontend  = wpforms()->get( 'frontend' );

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.7.7
	 */
	private function hooks() {

		add_action( 'wpforms_frontend_css', [ $this, 'enqueue_css' ] );
		add_filter( 'wpforms_frontend_fields_base_level', [ $this->field_obj, 'filter_base_fields' ] );
		add_filter( 'wpforms_process_after_filter', [ $this->field_obj, 'filter_fields_remove_layout' ], PHP_INT_MAX, 3 );
		add_filter( 'wpforms_pro_fields_entry_preview_print_entry_preview_exclude_field', [ $this, 'entry_preview_exclude_field' ], 10, 3 );
		add_filter( 'wpforms_frontend_output_form_is_empty', [ $this, 'filter_output_form_is_empty' ], 10, 2 );
	}

	/**
	 * Frontend CSS enqueues.
	 *
	 * @since 1.7.7
	 *
	 * @param array $forms Form data of forms on the current page.
	 */
	public function enqueue_css( $forms ) {

		if (
			! $this->frontend->assets_global() &&
			! wpforms_has_field_type( $this->field_obj->type, $forms, true )
		) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			WPForms_Field_Layout::STYLE_HANDLE,
			WPFORMS_PLUGIN_URL . "assets/pro/css/fields/layout{$min}.css",
			[],
			WPFORMS_VERSION
		);
	}

	/**
	 * Display field on the front-end.
	 *
	 * @since 1.7.7
	 *
	 * @param array $field     Field settings.
	 * @param array $form_data Form data and settings.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function field_display( $field, $form_data ) {

		$columns_html = '';
		$columns      = isset( $field['columns'] ) && is_array( $field['columns'] ) ? $field['columns'] : [];
		$preset_class = isset( $field['preset'] ) ? $field['preset'] : $this->field_obj->defaults['preset'];

		foreach ( $columns as $column ) {

			$column_preset_class = ! empty( $column['width_preset'] ) ? ' wpforms-layout-column-' . (int) $column['width_preset'] : '';
			$style_width         = ! empty( $column['width_custom'] ) ? ' style="width: ' . (int) $column['width_custom'] . '%;"' : '';

			$columns_html .= sprintf(
				'<div class="wpforms-layout-column%1$s"%2$s>%3$s</div>',
				esc_attr( $column_preset_class ),
				$style_width,
				$this->get_column_content( $column )
			);
		}

		printf(
			'<div class="wpforms-field-layout-columns wpforms-field-layout-preset-%1$s">%2$s</div>',
			esc_attr( $preset_class ),
			$columns_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Get column content HTML.
	 *
	 * @since 1.7.7
	 *
	 * @param array $column Column data.
	 *
	 * @return string
	 */
	public function get_column_content( $column ) {

		$form_data = $this->field_obj->form_data;

		// Bail if we don't have the column fields data for some reason.
		if ( empty( $form_data['fields'] ) || empty( $column['fields'] ) || ! is_array( $column['fields'] ) ) {
			return '';
		}

		ob_start();

		foreach ( $column['fields'] as $field_id ) {

			$field = isset( $form_data['fields'][ $field_id ] ) ? $form_data['fields'][ $field_id ] : false;

			if ( ! $field ) {
				continue;
			}

			$this->frontend->render_field( $form_data, $field );
		}

		return ob_get_clean();
	}

	/**
	 * Excluded from the Entry Preview display.
	 *
	 * @since 1.7.7
	 *
	 * @param bool  $exclude   Exclude the field.
	 * @param array $field     Field data.
	 * @param array $form_data Form data.
	 *
	 * @return bool
	 */
	public function entry_preview_exclude_field( $exclude, $field, $form_data ) {

		if ( $field['type'] === $this->field_obj->type ) {
			return true;
		}

		return $exclude;
	}

	/**
	 * Check if the form is empty before output to the frontend.
	 *
	 * @since 1.7.7
	 *
	 * @param bool  $form_is_empty True if the form is empty.
	 * @param array $form_data     Form data.
	 *
	 * @return bool
	 */
	public function filter_output_form_is_empty( $form_is_empty, $form_data ) {

		if ( $form_is_empty || ! isset( $form_data['fields'] ) || ! is_array( $form_data['fields'] ) ) {
			return $form_is_empty;
		}

		$layout_fields = wpforms_chain( wp_list_pluck( $form_data['fields'], 'type' ) )
			->array_filter(
				static function( $type ) {

					return $type === 'layout';
				}
			)
			->value();

		return count( $layout_fields ) === count( $form_data['fields'] );
	}
}
