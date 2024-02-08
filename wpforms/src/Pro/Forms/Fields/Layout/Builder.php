<?php

namespace WPForms\Pro\Forms\Fields\Layout;

use WP_Post;
use WPForms_Field_Layout;
use WPForms_Builder_Panel_Fields;

/**
 * Class Builder for Layout Field.
 *
 * @since 1.7.7
 */
class Builder {

	/**
	 * Instance of the WPForms_Field_Layout class.
	 *
	 * @since 1.7.7
	 *
	 * @var WPForms_Field_Layout
	 */
	private $field_obj;

	/**
	 * Class constructor.
	 *
	 * @since 1.7.7
	 *
	 * @param WPForms_Field_Layout $field_obj Instance of the WPForms_Field_Layout class.
	 */
	public function __construct( $field_obj ) {

		$this->field_obj = $field_obj;

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.7.7
	 */
	private function hooks() {

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueues' ] );
		add_filter( 'wpforms_save_form_args', [ $this, 'save_form_args' ], 20, 3 );
		add_filter( 'wpforms_builder_panel_fields_preview_fields', [ $this->field_obj, 'filter_base_fields' ] );
		add_filter( 'wpforms_builder_strings', [ $this, 'get_localized_strings' ], 10, 2 );
		add_filter( 'wpforms_field_new_class', [ $this, 'preview_field_new_class' ], 10, 2 );
		add_action( 'wpforms_builder_print_footer_scripts', [ $this, 'field_preview_column_plus_placeholder_template' ] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 1.7.7
	 */
	public function enqueues() {

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-builder-field-layout',
			WPFORMS_PLUGIN_URL . "assets/pro/js/admin/builder/layout{$min}.js",
			[ 'wpforms-builder' ],
			WPFORMS_VERSION,
			true
		);
	}

	/**
	 * Field options panel inside the builder.
	 *
	 * @since 1.7.7
	 *
	 * @param array $field Field settings.
	 */
	public function field_options( $field ) {

		$this->field_option_columns_json( $field );

		// Options open markup.
		$this->field_obj->field_option(
			'basic-options',
			$field,
			[
				'markup' => 'open',
			]
		);

		$this->field_obj->field_option(
			'label',
			$field,
			[
				'tooltip' => esc_html__( 'Enter text for the Layout field label. It will help identify your layout block inside the form builder, but will not be displayed in the form.', 'wpforms' ),
			]
		);

		$this->field_option_preset_selector( $field );

		// Options close markup.
		$this->field_obj->field_option(
			'basic-options',
			$field,
			[
				'markup' => 'close',
			]
		);
	}

	/**
	 * Columns JSON hidden field option.
	 *
	 * @since 1.7.7
	 *
	 * @param array $field Field settings.
	 */
	private function field_option_columns_json( $field ) {

		printf(
			'<input type="hidden" name="fields[%1$d][columns-json]" id="wpforms-field-option-%1$d-columns-json" value="%2$s">',
			(int) $field['id'],
			esc_attr( wp_json_encode( isset( $field['columns'] ) ? $field['columns'] : $this->field_obj->defaults['columns'] ) )
		);
	}

	/**
	 * Preset selector field option.
	 *
	 * @since 1.7.7
	 *
	 * @param array $field Field settings.
	 */
	private function field_option_preset_selector( $field ) {

		$presets = $this->field_obj->get_presets();

		$this->field_obj->field_element(
			'label',
			$field,
			[
				'slug'    => 'preset',
				'value'   => esc_html__( 'Select a Layout', 'wpforms' ),
				'tooltip' => esc_html__( 'Select a predefined layout preset', 'wpforms' ),
			]
		);

		$inputs = '';

		foreach ( $presets as $preset ) {

			$inputs .= sprintf(
				'<input type="radio" name="fields[%1$d][preset]" id="wpforms-field-option-%1$d-preset-%2$s" value="%2$s"%3$s><label for="wpforms-field-option-%1$d-preset-%2$s" class="preset-%2$s"></label>',
				(int) $field['id'],
				esc_attr( $preset ),
				$field['preset'] === $preset ? ' checked' : ''
			);
		}

		$this->field_obj->field_element(
			'row',
			$field,
			[
				'slug'    => 'preset',
				'content' => $inputs,
			]
		);
	}

	/**
	 * Display layout field preview inside the builder.
	 *
	 * @since 1.7.7
	 *
	 * @param array $field Field settings.
	 */
	public function field_preview( $field ) {

		// Label.
		$this->field_obj->field_preview_option( 'label', $field );

		// Notice.
		$this->field_preview_notice();

		// Columns.
		$columns      = isset( $field['columns'] ) && is_array( $field['columns'] ) ? $field['columns'] : $this->field_obj->defaults['columns'];
		$columns_html = '';

		foreach ( $columns as $column ) {

			$preset_class = ! empty( $column['width_preset'] ) ? ' wpforms-layout-column-' . (int) $column['width_preset'] : '';
			$style_width  = ! empty( $column['width_custom'] ) ? ' style="width: ' . (int) $column['width_custom'] . '%;"' : '';

			$columns_html .= sprintf(
				'<div class="wpforms-layout-column%1$s"%2$s>%3$s</div>',
				esc_attr( $preset_class ),
				$style_width, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$this->field_preview_column_content( $column )
			);
		}

		printf(
			'<div class="wpforms-field-layout-columns">%1$s</div>',
			$columns_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);

		// Description.
		$this->field_obj->field_preview_option( 'description', $field );
	}

	/**
	 * Display dismissible notice inside the Layout field preview.
	 *
	 * @since 1.7.7
	 */
	private function field_preview_notice() {

		$dismissed = get_user_meta( get_current_user_id(), 'wpforms_dismissed', true );

		if ( ! empty( $dismissed['edu-builder-layout-field-alert'] ) ) {
			return;
		}

		printf(
			'<div class="wpforms-alert wpforms-alert-info wpforms-alert-dismissible wpforms-dismiss-container wpforms-dismiss-out">
				<div class="wpforms-alert-message">
					<p>
						%1$s
						<a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>
					</p>
				</div>
				<div class="wpforms-alert-buttons">
					<button type="button" class="wpforms-dismiss-button" title="%4$s" data-section="builder-layout-field-alert"></button>
				</div>
			</div>',
			esc_html__( 'Drag and drop fields into the columns below, or click a column to make it active. You may then click on new fields to easily place them directly into the active column.', 'wpforms' ),
			esc_url(
				wpforms_utm_link(
					'https://wpforms.com/docs/how-to-use-the-layout-field-in-wpforms/',
					'Builder Notice',
					'Layout Field Documentation'
				)
			),
			esc_html__( 'Learn More', 'wpforms' ),
			esc_attr__( 'Dismiss this message.', 'wpforms' )
		);
	}

	/**
	 * Generate layout field preview column content.
	 *
	 * @since 1.7.7
	 *
	 * @param array $column Column data.
	 *
	 * @return string Column content HTML.
	 */
	public function field_preview_column_content( $column ) {

		$content = $this->get_field_preview_column_plus_placeholder_template();

		if ( empty( $column['fields'] ) || ! is_array( $column['fields'] ) ) {
			return $content;
		}

		$panel_fields_obj = WPForms_Builder_Panel_Fields::instance();

		ob_start();

		foreach ( $column['fields'] as $field_id ) {

			if ( empty( $panel_fields_obj->form_data['fields'][ $field_id ] ) ) {
				continue;
			}

			$panel_fields_obj->preview_single_field( $panel_fields_obj->form_data['fields'][ $field_id ], [] );
		}

		$content .= ob_get_clean();

		return $content;
	}

	/**
	 * Process layout fields data before saving the form.
	 *
	 * @since 1.7.7
	 *
	 * @param array $form Form array which is usable with `wp_update_post()`.
	 * @param array $data Data retrieved from $_POST and processed.
	 * @param array $args Empty by default, may contain custom data not intended to be saved, but used for processing.
	 *
	 * @return array
	 */
	public function save_form_args( $form, $data, $args ) {

		// Get a filtered form content.
		$form_data = json_decode( stripslashes( $form['post_content'] ), true );

		if ( empty( $form_data['fields'] ) || empty( $args['context'] ) || $args['context'] !== 'save_form' ) {
			return $form;
		}

		foreach ( (array) $form_data['fields'] as $id => $field ) {

			// Process only layout fields.
			if ( empty( $field['type'] ) || $field['type'] !== $this->field_obj->type ) {
				continue;
			}

			// Decode columns data from JSON.
			if ( isset( $field['columns-json'] ) ) {
				$field['columns'] = json_decode( $field['columns-json'], true );

				// Do not need to store JSON.
				unset( $field['columns-json'] );
			}

			// Set defaults to some field options.
			// For example, we don't have Label option in the Form Builder.
			$form_data['fields'][ $id ] = wp_parse_args( $field, $this->field_obj->defaults );
		}

		$form['post_content'] = wpforms_encode( $form_data );

		return $form;
	}

	/**
	 * Pass localized strings to builder.
	 *
	 * @since 1.7.7
	 *
	 * @param array   $strings All strings that will be passed to builder.
	 * @param WP_Post $form    Form object.
	 *
	 * @return array
	 */
	public function get_localized_strings( $strings, $form ) {

		$legacy_layout_notice_text = sprintf(
			wp_kses( /* translators: %1$s - WPForms.com URL to a related doc. */
				__( 'We’ve added a new field to help you build advanced form layouts more easily. Give the Layout Field a try! Layout CSS classes are still supported. <a href="%1$s" target="_blank" rel="noopener noreferrer">Learn More</a>', 'wpforms' ),
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'rel'    => [],
					],
				]
			),
			esc_url(
				wpforms_utm_link( 'https://wpforms.com/docs/how-to-use-the-layout-field-in-wpforms/', 'Field Options', 'How to Use the Layout Field Documentation' )
			)
		);

		$strings['layout'] = [
			'not_allowed_fields'         => $this->field_obj->get_not_allowed_fields(),
			/* translators: %s - field name. */
			'not_allowed_alert_text'     => esc_html__( 'The %s field can’t be placed inside a Layout field.', 'wpforms' ),
			'empty_label'                => esc_html__( 'Layout', 'wpforms' ),
			'got_it'                     => esc_html__( 'Got it!', 'wpforms' ),
			'size_notice_text'           => esc_html__( 'Field size cannot be changed when used in a layout.', 'wpforms' ),
			'size_notice_tooltip'        => esc_html__( 'When a field is placed inside a column, the field size always equals the column width.', 'wpforms' ),
			'dont_show_again'            => esc_html__( 'Don’t Show Again', 'wpforms' ),
			'legacy_layout_notice_title' => esc_html__( 'Layouts Have Moved!', 'wpforms' ),
			'legacy_layout_notice_text'  => $legacy_layout_notice_text,
			'enabled_cf_alert_text'      => esc_html__( 'The Layout field cannot be used when Conversational Forms is enabled.', 'wpforms' ),
			'delete_confirm'             => esc_html__( 'Are you sure you want to delete the Layout field? Deleting this field will also delete the fields inside it.', 'wpforms' ),
		];

		return $strings;
	}

	/**
	 * Get template for the column "plus" placeholder.
	 *
	 * @since 1.7.7
	 *
	 * @return string
	 */
	private function get_field_preview_column_plus_placeholder_template() {

		return sprintf(
			'<div class="wpforms-layout-column-placeholder" title="%s">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="normal-icon">
					<path d="M18.2 11.71a.62.62 0 0 0-.59-.58h-4.74V6.39a.62.62 0 0 0-.58-.58h-.58a.59.59 0 0 0-.58.58v4.74H6.39a.59.59 0 0 0-.58.58v.58c0 .34.24.58.58.58h4.74v4.74c0 .34.24.58.58.58h.58c.3 0 .58-.24.58-.58v-4.74h4.74c.3 0 .58-.24.58-.58v-.58ZM24 12a12 12 0 1 0-24 0 12 12 0 0 0 24 0Zm-1.55 0a10.44 10.44 0 1 1-20.9 0C1.55 6.29 6.19 1.55 12 1.55A10.5 10.5 0 0 1 22.45 12Z" class="wpforms-plus-path"/>
				</svg>
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="active-icon">
					<path d="M12 24a12 12 0 1 0 0-24 12 12 0 0 0 0 24ZM1.55 12C1.55 6.29 6.19 1.55 12 1.55A10.5 10.5 0 0 1 22.45 12a10.44 10.44 0 1 1-20.9 0ZM6 11.42a.56.56 0 0 0 0 .82l.34.34c.24.24.58.24.82 0l4.02-4.16v9.2c0 .33.24.57.58.57h.48c.3 0 .58-.24.58-.58v-9.2l3.97 4.17c.24.24.58.24.82 0l.34-.34a.56.56 0 0 0 0-.82L12.4 5.85a.56.56 0 0 0-.83 0L6 11.42Z" class="wpforms-plus-path"/>
				</svg>
			</div>',
			esc_attr__( 'Click to set this column as default. Click again to unset.', 'wpforms' )
		);
	}

	/**
	 * Output template for the column "plus" placeholder.
	 *
	 * @since 1.7.7
	 */
	public function field_preview_column_plus_placeholder_template() {

		?>
		<script type="text/html" id="tmpl-wpforms-layout-field-column-plus-placeholder-template">
			<?php echo $this->get_field_preview_column_plus_placeholder_template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</script>
		<?php
	}

	/**
	 * Get new field CSS class.
	 *
	 * @since 1.7.7
	 *
	 * @param string $class Preview new field CSS class.
	 * @param array  $field Field data.
	 *
	 * @return string
	 */
	public function preview_field_new_class( $class, $field ) {

		if ( empty( $field['type'] ) || $field['type'] !== $this->field_obj->type ) {
			return $class;
		}

		return trim( $class . ' label_hide' );
	}
}
