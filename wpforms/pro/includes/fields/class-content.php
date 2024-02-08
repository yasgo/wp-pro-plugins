<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPForms_Field_Content.
 *
 * @since 1.7.8
 */
class WPForms_Field_Content extends WPForms_Field {

	use \WPForms\Admin\Builder\Traits\ContentInput;

	/**
	 * Class initialization method.
	 *
	 * @since 1.7.8
	 */
	public function init() {

		// Define field type information.
		$this->name     = esc_html__( 'Content', 'wpforms' );
		$this->keywords = esc_html__( 'image, text, table, list, heading, wysiwyg, visual', 'wpforms' );
		$this->type     = 'content';
		$this->icon     = 'fa-file-image-o';
		$this->order    = 181;
		$this->group    = 'fancy';

		$this->hooks();
	}

	/**
	 * Register WP hooks.
	 *
	 * @since 1.7.8
	 */
	private function hooks() {

		add_filter( 'wpforms_entries_table_fields_disallow', [ $this, 'hide_column_in_entries_table' ] );
		add_filter( 'wpforms_pro_admin_entries_print_preview_field_value', [ $this, 'print_preview_field_value' ], 10, 2 );
		add_filter( 'wpforms_pro_admin_entries_print_preview_field_value_use_nl2br', [ $this, 'print_preview_use_nl2br' ], 10, 2 );
		add_filter( "wpforms_pro_admin_entries_edit_is_field_displayable_{$this->type}", '__return_false' );
		add_action( 'wpforms_frontend_css', [ $this, 'frontend_css' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_css' ] );
	}

	/**
	 * Show field options in builder left panel.
	 *
	 * @since 1.7.8
	 *
	 * @param array $field Field data.
	 */
	public function field_options( $field ) {

		// Options open markup.
		$this->field_option( 'basic-options', $field, [ 'markup' => 'open' ] );

		$this->field_option_content( $field );

		// Set label to disabled.
		$args = [
			'type'  => 'hidden',
			'slug'  => 'label_disable',
			'value' => '1',
		];

		$this->field_element( 'text', $field, $args );

		// Options close markup.
		$this->field_option( 'basic-options', $field, [ 'markup' => 'close' ] );

		// Options open markup.
		$this->field_option( 'advanced-options', $field, [ 'markup' => 'open' ] );

		// Size.
		$this->field_option( 'size', $field );

		// Custom CSS classes.
		$this->field_option( 'css', $field );

		// Options close markup.
		$this->field_option( 'advanced-options', $field, [ 'markup' => 'close' ] );
	}

	/**
	 * Show field preview in the builder right panel.
	 *
	 * @since 1.7.8
	 *
	 * @param array $field Field data.
	 */
	public function field_preview( $field ) {

		$this->content_input_preview( $field );
	}

	/**
	 * Display field on the front end.
	 *
	 * @since 1.7.8
	 *
	 * @param array $field      Field data.
	 * @param array $field_atts Field attributes.
	 * @param array $form_data  Form data.
	 *
	 * @return void
	 */
	public function field_display( $field, $field_atts, $form_data ) {

		$this->content_input_display( $field );
	}

	/**
	 * Format field.
	 *
	 * Hides field on form submit preview.
	 *
	 * @since 1.7.8
	 *
	 * @param int   $field_id     Field ID.
	 * @param array $field_submit Submitted field value.
	 * @param array $form_data    Form data and settings.
	 */
	public function format( $field_id, $field_submit, $form_data ) {
	}

	/**
	 * Hide column from the entries list table.
	 *
	 * @since 1.7.8
	 *
	 * @param array $disallowed Table columns.
	 *
	 * @return array
	 */
	public function hide_column_in_entries_table( $disallowed ) {

		$disallowed[] = $this->type;

		return $disallowed;
	}

	/**
	 * Do caption shortcode for entry print preview and add clearing div.
	 *
	 * @since 1.7.9
	 *
	 * @param string $value Field value.
	 * @param array  $field Field data.
	 *
	 * @return string
	 */
	public function print_preview_field_value( $value, $field ) {

		if ( $field['type'] !== $this->type ) {
			return $value;
		}

		return wp_kses( sprintf( '%s<div class="wpforms-field-content-preview-end"></div>', $this->do_caption_shortcode( $value ) ), $this->get_allowed_html_tags() );
	}

	/**
	 * Do not use nl2br on content field's value.
	 *
	 * @since 1.7.9
	 *
	 * @param bool  $use   Boolean value flagging if field should use nl2br function.
	 * @param array $field Field data.
	 *
	 * @return bool
	 */
	public function print_preview_use_nl2br( $use, $field ) {

		return $field['type'] === $this->type ? false : $use;
	}

	/**
	 * Conditionally enqueue frontend field CSS.
	 *
	 * Hook it into action wpforms_frontend_css if the field should be displayed and styled in the front end.
	 *
	 * @since 1.7.8
	 *
	 * @param array $forms Forms on the current page.
	 */
	public function frontend_css( $forms ) {
		/*
		 * If it is NOT set to enqueue CSS globally
		 * and form does not have content field or for is not set to enqueue CSS
		 * then bail out.
		 */
		if (
			! wpforms()->get( 'frontend' )->assets_global()
			&& ( ! wpforms_has_field_type( $this->type, $forms, true ) || (int) wpforms_setting( 'disable-css', '1' ) !== 1 )
		) {
			return;
		}

		$this->enqueue_css();
	}

	/**
	 * Enqueue frontend field CSS.
	 *
	 * @since 1.7.8
	 */
	public function enqueue_css() {

		$min = wpforms_get_min_suffix();

		// Field styles based on the Form Styling setting.
		wp_enqueue_style(
			'wpforms-content-frontend',
			WPFORMS_PLUGIN_URL . "assets/pro/css/fields/content/frontend{$min}.css",
			[],
			WPFORMS_VERSION
		);
	}

	/**
	 * Whether current field can be populated dynamically.
	 *
	 * @since 1.7.8
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Current field specific data.
	 *
	 * @return false
	 */
	public function is_dynamic_population_allowed( $properties, $field ) {

		return false;
	}

	/**
	 * Whether current field can be populated dynamically.
	 *
	 * @since 1.7.8
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Current field specific data.
	 *
	 * @return false
	 */
	public function is_fallback_population_allowed( $properties, $field ) {

		return false;
	}

	/**
	 * Show field display on front-end.
	 *
	 * @since 1.7.8
	 *
	 * @param array $field Field data.
	 */
	private function content_input_display( $field ) {

		if ( ! isset( $field['content'] ) ) {
			return;
		}

		$content = wp_kses( $this->do_caption_shortcode( wpautop( $field['content'] ) ), $this->get_allowed_html_tags() );

		// Disallow links to be clickable if form is displayed in Gutenberg block in edit context.
		if ( isset( $_REQUEST['context'] ) && $_REQUEST['context'] === 'edit' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$content = str_replace( '<a ', '<a onclick="event.preventDefault()" ', $content );
		}

		// Define data.
		$primary            = $field['properties']['inputs']['primary'];
		$primary['class'][] = 'wpforms-field-row';

		printf(
			'<div %s>%s<div class="wpforms-field-content-display-frontend-clear"></div></div>',
			wpforms_html_attributes( $primary['id'], $primary['class'], $primary['data'], $primary['attr'] ),
			$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}
}

$content_field = new WPForms_Field_Content();
