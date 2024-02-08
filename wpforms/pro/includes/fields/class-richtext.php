<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPForms\Pro\Helpers\Upload;

/**
 * Rich Text field.
 *
 * @since 1.7.0
 */
class WPForms_Field_Richtext extends WPForms_Field {

	/**
	 * Track if media is enabled for the field.
	 *
	 * @since 1.7.0
	 *
	 * @var array
	 */
	private $is_media_enabled = false;

	/**
	 * Track whether the user can upload or not.
	 *
	 * @since 1.7.0
	 *
	 * @var bool
	 */
	private $current_user_can_upload = false;

	/**
	 * Track whether the user capacity to upload files was added.
	 *
	 * @since 1.7.0
	 *
	 * @var bool
	 */
	private $cap_was_added = false;

	/**
	 * Upload files helper.
	 *
	 * @since 1.7.0
	 *
	 * @var Upload
	 */
	private $upload;

	/**
	 * List of allowed AJAX actions to overwrite.
	 *
	 * @since 1.7.0
	 *
	 * @var string[]
	 */
	private $allowed_ajax_actions = [
		'get-attachment',
		'query-attachments',
		'save-attachment',
		'upload-attachment',
		'send-attachment-to-editor',
	];

	/**
	 * Cleanup action name.
	 *
	 * @since 1.7.0
	 *
	 * @var string
	 */
	const MEDIA_CLEANUP_ACTION = 'wpforms_richtext_media_cleanup';

	/**
	 * Primary class constructor.
	 *
	 * @since 1.7.0
	 */
	public function init() {

		// Define field type information.
		$this->name     = esc_html__( 'Rich Text', 'wpforms' );
		$this->keywords = esc_html__( 'image, text, table, list, heading, wysiwyg, visual', 'wpforms' );
		$this->type     = 'richtext';
		$this->icon     = 'fa-pencil-square-o';
		$this->order    = 133;
		$this->group    = 'fancy';

		// Init upload files helper.
		$this->upload = new Upload();

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.7.0
	 */
	private function hooks() {

		// Define additional field properties.
		add_filter( 'wpforms_field_properties_richtext', [ $this, 'field_properties' ], 5, 3 );

		add_filter( 'wpforms_html_field_value', [ $this, 'allow_tags_for_richtext_entry_view' ], 7, 4 );

		add_action( 'wpforms_process_before', [ $this, 'process_submitted_images' ], 10, 2 );

		if ( $this->is_valid_request() ) {

			// Hook these in early because we modify the contents of the request on our pages.
			// We hook wp_ajax_nopriv, even though this is mostly for logged-out
			// users because we want to make sure a user without upload permissions can still
			// upload media to the form.
			add_action( 'wp_ajax_nopriv_query-attachments', [ $this, 'media_query_attachments' ], 5 );

			// Allow insert images for the unauthorized users.
			add_action( 'wp_ajax_nopriv_send-attachment-to-editor', 'wp_ajax_send_attachment_to_editor', 1 );

			add_filter( 'upload_mimes', [ $this, 'upload_mimes' ], 1001, 2 );

			add_filter( 'ajax_query_attachments_args', [ $this, 'restrict_attachments_by_mime_types' ] );

			// Don't allow a shortcode with caption.
			remove_action( 'image_send_to_editor', 'image_add_caption', 20 );

			// We hook in super early in the async-upload.php call so that we can override all the
			// auth and do upload related things without being logged in.
			add_filter( 'secure_auth_redirect', [ $this, 'override_auth_for_ajax_media_calls' ] );
		}

		add_action( self::MEDIA_CLEANUP_ACTION, [ $this, 'delete_attachment' ] );
		add_action( 'wpforms_frontend_css', [ $this, 'frontend_css' ] );
		add_action( 'wpforms_frontend_js', [ $this, 'frontend_js' ] );

		add_filter( 'wpforms_entry_table_column_value', [ $this, 'entry_table_value' ], 10, 4 );

		add_filter( 'wpforms_frontend_strings', [ $this, 'add_frontend_strings' ] );

		add_filter( "wpforms_pro_fields_entry_preview_get_field_value_{$this->type}_field_after", [ $this, 'entry_preview' ], 10, 3 );

		add_filter( 'quicktags_settings', [ $this, 'modify_quicktags' ], 10, 2 );

		add_action( 'pre_get_posts', [ $this, 'modify_attachment_query' ] );

		// This field requires fieldset+legend instead of the field label.
		add_filter( "wpforms_frontend_modern_is_field_requires_fieldset_{$this->type}", '__return_true', PHP_INT_MAX, 2 );

		add_filter( 'tiny_mce_before_init', [ $this, 'customize_tinymc' ] );
	}


	/**
	 * Customize TinyMCE editor.
	 *
	 * @since 1.8.5
	 *
	 * @see https://codex.wordpress.org/TinyMCE#Customize_TinyMCE_with_Filters
	 *
	 * @param array $in The TinyMCE settings array.
	 *
	 * @return array The modified TinyMCE settings array.
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	public function customize_tinymc( $in ) {

		// Append custom CSS file to comma seperated list of stylesheets.
		$current_content_css = ! empty( $in['content_css'] ) ? $in['content_css'] : '';
		$in['content_css']   = implode( ',', [ $current_content_css, esc_url( $this->get_editor_content_css_url() ) ] );

		return $in;
	}

	/**
	 * Field options panel inside the builder.
	 *
	 * @since 1.7.0
	 *
	 * @param array $field Field data and settings.
	 */
	public function field_options( $field ) {

		$this->field_option( 'basic-options', $field, [ 'markup' => 'open' ] );
		$this->field_option( 'label', $field );
		$this->field_option( 'description', $field );

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'media_enabled',
				'content' => $this->field_element(
					'toggle',
					$field,
					[
						'slug'    => 'media_enabled',
						'value'   => isset( $field['media_enabled'] ) ? '1' : '0',
						'desc'    => esc_html__( 'Allow Media Uploads', 'wpforms' ),
						'tooltip' => esc_html__( 'Check this option to allow uploading and embedding files.', 'wpforms' ),
					],
					false
				),
			]
		);

		$media_library = $this->field_element(
			'toggle',
			$field,
			[
				'slug'    => 'media_library',
				'value'   => isset( $field['media_library'] ) ? '1' : '0',
				'desc'    => esc_html__( 'Store files in WordPress Media Library', 'wpforms' ),
				'tooltip' => esc_html__( 'Check this option to store files in the WordPress Media Library.', 'wpforms' ),
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'media_controls',
				'class'   => ! isset( $field['media_enabled'] ) ? 'wpforms-hide' : '',
				'content' => $media_library,
			]
		);

		$this->field_option( 'required', $field );
		$this->field_option( 'basic-options', $field, [ 'markup' => 'close' ] );

		$this->field_option( 'advanced-options', $field, [ 'markup' => 'open' ] );

		$output_style = $this->field_element(
			'label',
			$field,
			[
				'slug'  => 'style',
				'value' => esc_html__( 'Field Style', 'wpforms' ),
			],
			false
		);

		$output_style .= $this->field_element(
			'select',
			$field,
			[
				'slug'    => 'style',
				'value'   => ! empty( $field['style'] ) ? esc_attr( $field['style'] ) : 'full',
				'options' => [
					'full'  => esc_html__( 'Full', 'wpforms' ),
					'basic' => esc_html__( 'Basic', 'wpforms' ),
				],
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'style',
				'content' => $output_style,
			]
		);

		$this->field_option( 'size', $field );
		$this->field_option( 'css', $field );
		$this->field_option( 'label_hide', $field );
		$this->field_option( 'advanced-options', $field, [ 'markup' => 'close' ] );
	}

	/**
	 * The field preview inside the builder.
	 *
	 * @since 1.7.0
	 *
	 * @param array $field Field data and settings.
	 */
	public function field_preview( $field ) {

		$this->field_preview_option( 'label', $field );

		$style         = ! empty( $field['style'] ) && $field['style'] === 'basic' ? 'wpforms-field-richtext-toolbar-basic' : '';
		$media_enabled = ! empty( $field['media_enabled'] ) ? 'wpforms-field-richtext-media-enabled' : '';
		?>

		<div class="wpforms-richtext-wrap tmce-active">
			<div class="wp-editor-tabs">
				<button type="button" class="wp-switch-editor switch-tmce"><?php esc_html_e( 'Visual', 'wpforms' ); ?></button>
				<button type="button" class="wp-switch-editor"><?php esc_html_e( 'Text', 'wpforms' ); ?></button>
			</div>
			<div class="wp-editor-container ">
				<div class="mce-container-body">
					<div class="mce-toolbar-grp <?php echo esc_attr( $style ); ?> <?php echo esc_attr( $media_enabled ); ?>"></div>
				</div>
				<textarea id="wpforms-richtext-<?php echo absint( $field['id'] ); ?>"></textarea>
				<div class="mce-statusbar">
					<i class="mce-ico mce-i-resize"></i>
				</div>
			</div>
		</div>

		<?php
		$this->field_preview_option( 'description', $field );
	}

	/**
	 * Define additional field properties.
	 *
	 * @since 1.7.0
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Field settings.
	 * @param array $form_data  Form data and settings.
	 *
	 * @return array
	 */
	public function field_properties( $properties, $field, $form_data ) {

		if ( ! empty( $field['media_enabled'] ) ) {
			$properties['container']['class'][] = 'wpforms-field-richtext-media-enabled';
		}

		if ( ! empty( $field['style'] ) && $field['style'] === 'basic' ) {
			$properties['container']['class'][] = 'wpforms-field-richtext-toolbar-basic';
		}

		$size                               = ! empty( $field['size'] ) ? $field['size'] : 'medium';
		$properties['container']['class'][] = 'wpforms-field-' . $size;

		return $properties;
	}

	/**
	 * The field display on the form front-end.
	 *
	 * @since 1.7.0
	 *
	 * @param array $field      Field data and settings.
	 * @param array $field_atts Field attributes.
	 * @param array $form_data  Form data and settings.
	 */
	public function field_display( $field, $field_atts, $form_data ) {

		$primary = $field['properties']['inputs']['primary'];
		$value   = '';

		if ( isset( $primary['attr']['value'] ) ) {
			$value = wpforms_esc_richtext_field( $primary['attr']['value'] );

			unset( $primary['attr']['value'] );
		}

		if ( isset( $field['size'] ) ) {
			$primary['data']['size'] = esc_attr( $field['size'] );
		}

		if ( isset( $field['required'] ) ) {
			$primary['data']['required'] = (bool) $field['required'];
			$primary['class'][]          = 'wpforms-field-required';
		}

		if ( isset( $field['media_enabled'] ) ) {
			$primary['data']['media_enabled'] = (bool) $field['media_enabled'];
		}

		$primary['class'][] = 'wpforms-richtext-field-editor';

		$this->display_editor( $primary, $field, $value );
	}

	/**
	 * Whether current field can be populated dynamically.
	 *
	 * @since 1.7.0
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Current field specific data.
	 *
	 * @return bool
	 */
	public function is_dynamic_population_allowed( $properties, $field ) {

		return false;
	}

	/**
	 * Determine whether the field is a richtext.
	 *
	 * @since 1.7.0
	 *
	 * @param array $field Field data.
	 *
	 * @return bool
	 */
	private function is_richtext_field( $field ) {

		return isset( $field['type'] ) && $field['type'] === $this->type;
	}

	/**
	 * Get the value based on field data and current properties.
	 *
	 * @since 1.7.0
	 *
	 * @param string $raw_value  Value from a GET param, always a string.
	 * @param string $input      Represent a subfield inside the field. May be empty.
	 * @param array  $properties Field properties.
	 * @param array  $field      Current field specific data.
	 *
	 * @return array
	 */
	protected function get_field_populated_single_property_value( $raw_value, $input, $properties, $field ) {

		if ( ! is_string( $raw_value ) ) {
			return $properties;
		}

		if ( ! empty( $input ) && isset( $properties['inputs'][ $input ] ) ) {
			$properties['inputs'][ $input ]['attr']['value'] = wpforms_esc_richtext_field( $raw_value );
		}

		return $properties;
	}

	/**
	 * Display the editor, including all before/after checks we need to do.
	 *
	 * @since 1.7.0
	 *
	 * @param array  $primary Field data.
	 * @param array  $field   Field data.
	 * @param string $value   Value to display.
	 */
	private function display_editor( $primary, $field, $value ) {

		$this->is_media_enabled = isset( $primary['data']['media_enabled'] ) ? $primary['data']['media_enabled'] : false;

		$this->current_user_can_upload = current_user_can( 'upload_files' );

		$this->before_editor();

		$this->add_rich_text_editor_field( $field, $primary, $value );

		$this->after_editor();
	}

	/**
	 * Enqueue frontend field CSS.
	 *
	 * @since 1.7.0
	 *
	 * @param array $forms Forms on the current page.
	 */
	public function frontend_css( $forms ) {

		if ( ! $this->is_enqueue_assets( $forms ) ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		// Styles for Add Media, Insert Link, and other modals.
		wp_enqueue_style(
			'wpforms-modal-views',
			WPFORMS_PLUGIN_URL . "assets/pro/css/fields/richtext/modal-views{$min}.css",
			[],
			WPFORMS_VERSION
		);

		// Make sure that the "editor.css" style is not dequeued by the Divi builder.
		if ( ! wp_style_is( 'editor-buttons' ) ) {
			wp_enqueue_style(
				'editor-buttons',
				includes_url( "css/editor{$min}.css" ),
				[ 'dashicons' ]
			);
		}

		// Make sure a copy of dashicons styles is loaded on the page globally when the admin bar
		// is displayed. Default dashicons library with the system handle `dashicons-css` will
		// be loaded in the markup of the Rich Text field and removed after form submission.
		if ( is_admin_bar_showing() ) {
			wp_enqueue_style(
				'wpforms-dashicons',
				includes_url( "css/dashicons{$min}.css" )
			);
		}

		$disable_css_setting = (int) wpforms_setting( 'disable-css', '1' );

		// Bail out if the Form Styling setting is set as none.
		if ( $disable_css_setting === 3 ) {
			return;
		}

		$css_file = $disable_css_setting === 2 ? 'base' : 'full';

		// Field styles based on the Form Styling setting.
		wp_enqueue_style(
			"wpforms-richtext-frontend-{$css_file}",
			WPFORMS_PLUGIN_URL . "assets/pro/css/fields/richtext/frontend-{$css_file}{$min}.css",
			[],
			WPFORMS_VERSION
		);
	}

	/**
	 * Enqueue builder field CSS.
	 *
	 * @since 1.7.0
	 * @deprecated 1.7.6
	 *
	 * @param string $view Current view.
	 */
	public function builder_css( $view ) {

		_deprecated_function( __METHOD__, '1.7.6 of the WPForms plugin' );
	}

	/**
	 * Enqueue frontend field js.
	 *
	 * @since 1.7.0
	 *
	 * @param array $forms Forms on the current page.
	 */
	public function frontend_js( $forms ) {

		if ( ! $this->is_enqueue_assets( $forms ) ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-richtext-field',
			WPFORMS_PLUGIN_URL . "assets/pro/js/fields/richtext{$min}.js",
			[ 'jquery' ],
			WPFORMS_VERSION,
			true
		);
	}

	/**
	 * Determine if assets need to be enqueued.
	 *
	 * @since 1.7.0
	 *
	 * @param array $forms Forms on the current page.
	 *
	 * @return bool
	 */
	private function is_enqueue_assets( $forms ) {

		return wpforms_has_field_type( 'richtext', $forms, true ) || wpforms()->get( 'frontend' )->assets_global();
	}

	/**
	 * Edit entry page requires this initialization.
	 *
	 * @since 1.7.0
	 */
	public function edit_entry_before_enqueues() {

		add_filter( 'media_view_settings', [ $this, 'edit_media_view_settings' ], 10, 2 );
		add_filter( 'media_view_strings', [ $this, 'edit_media_view_strings' ], 10, 2 );
		add_filter( 'upload_mimes', [ $this, 'upload_mimes' ], 1001, 2 );
	}

	/**
	 * Before we fire 'wp_editor', we want to manipulate some filters to allow it to function correctly.
	 *
	 * @since 1.7.0
	 */
	private function before_editor() {

		add_filter( 'user_can_richedit', '__return_true', 1001 );

		if ( ! $this->is_media_enabled ) {
			return;
		}

		add_filter( 'media_view_settings', [ $this, 'edit_media_view_settings' ], 10, 2 );
		add_filter( 'media_view_strings', [ $this, 'edit_media_view_strings' ], 10, 2 );
		add_filter( 'upload_mimes', [ $this, 'upload_mimes' ], 1001, 2 );

		if ( $this->current_user_can_upload || is_admin() ) {
			return;
		}

		$current_user = wp_get_current_user();

		if ( ! $current_user ) {
			return;
		}

		if ( ! $current_user->has_cap( 'upload_files' ) ) {

			$this->cap_was_added = true;

			$current_user->add_cap( 'upload_files' );
		}
	}

	/**
	 * Call the wp_editor() function with our field options.
	 *
	 * @since 1.7.0
	 *
	 * @param array  $field   Field data.
	 * @param array  $primary Field data.
	 * @param string $value   Text value of field.
	 */
	private function add_rich_text_editor_field( $field, $primary, $value ) {

		$mce_mode = ! empty( $field['style'] ) ? $field['style'] : 'full';
		$settings = [
			'media_buttons'    => $this->is_media_enabled,
			'drag_drop_upload' => $this->is_media_enabled,
			'textarea_name'    => "wpforms[fields][{$field['id']}]",
			'editor_height'    => $this->get_size_value_for_field( $primary['data']['size'] ),
			'editor_class'     => ! empty( $field['required'] ) ? 'wpforms-field-required' : '',
			'editor_css'       => '<style>.wpforms-field-richtext .insert-media.add_media { display: none !important; }</style>',
			'tinymce'          => [
				'plugins'                      => implode( ',', $this->get_tinymce_plugins( $field['id'], $primary ) ),
				'toolbar1'                     => implode( ',', $this->get_toolbar1( $field['id'], $primary, $mce_mode ) ),
				'toolbar2'                     => implode( ',', $this->get_toolbar2( $field['id'], $primary, $mce_mode ) ),
				'wpeditimage_disable_captions' => true,
			],
		];

		/**
		 * Allow filtering Rich Text field settings.
		 *
		 * @since 1.7.0
		 *
		 * @param array  $settings         {
		 *      Rich Text field settings.
		 *
		 *      @type bool   $wpautop          Flag to enable wpautop.
		 *      @type bool   $media_buttons    Flag to enable media button.
		 *      @type bool   $drag_drop_upload Flag to enable drag and drop upload.
		 *      @type string $textarea_name    Textarea name.
		 *      @type int    $textarea_rows    Textarea rows number.
		 *      @type string $editor_class     Field `css` class.
		 *      @type bool   $keep_styles      Flag to keep styles.
		 *      @type bool   $teeny            Flag to enable teeny.
		 *      @type bool   $quicktags        Flag to quick tags.
		 *      @type array  $tinymce          {
		 *          Tinymce settings.
		 *
		 *          @type string $plugins          Plugins list.
		 *          @type string $toolbar1         Toolbar1 list.
		 *          @type string $toolbar2         Toolbar2 list.
		 *          @type bool   $toolbar2         Flag to disable captions.
		 *      }
		 * }
		 * @param int    $field_id         Field ID.
		 * @param array  $primary          Field data.
		 */
		$settings = (array) apply_filters( 'wpforms_richtext_add_rich_text_editor_field_settings', $settings, $field['id'], $primary );

		wp_editor( $value, $primary['id'], $settings );
	}

	/**
	 * Similar to 'before_editor()', this is where we will unset certain filters to
	 * restore them to what they were before we fired 'wp_editor'.
	 *
	 * @since 1.7.0
	 */
	private function after_editor() {

		if ( ! $this->is_media_enabled ) {
			return;
		}

		remove_filter( 'media_view_settings', [ $this, 'edit_media_view_settings' ] );
		remove_filter( 'media_view_strings', [ $this, 'edit_media_view_strings' ] );
		remove_filter( 'upload_mimes', [ $this, 'upload_mimes' ], 1001 );

		if ( ! $this->current_user_can_upload && ! is_admin() ) {
			$current_user = wp_get_current_user();

			if ( ! $current_user ) {
				return;
			}

			if ( $this->cap_was_added ) {
				$current_user->remove_cap( 'upload_files' );
			}
		}

		remove_filter( 'user_can_richedit', '__return_true', 1001 );
	}

	/**
	 * Edit some media view settings.
	 *
	 * @since 1.7.0
	 *
	 * @param array   $settings List of media view settings.
	 * @param WP_Post $post     Post object.
	 *
	 * @return array Modified media view settings.
	 */
	public function edit_media_view_settings( $settings, $post ) {

		$settings['tabs']     = [];
		$settings['captions'] = false;
		$settings['months']   = [];

		return $settings;
	}

	/**
	 * Allow only images to be uploaded.
	 *
	 * @since 1.7.0
	 *
	 * @param array            $mimes Mime types.
	 * @param int|WP_User|null $user  User ID, User object or null if not provided (indicates current user).
	 *
	 * @return array
	 */
	public function upload_mimes( $mimes, $user ) {

		foreach ( $mimes as $key => $mime ) {
			// Skip non-image and potentially dangerous formats (SVG).
			if ( strpos( $mime, 'image' ) !== 0 || strpos( $mime, 'svg' ) ) {
				unset( $mimes[ $key ] );
			}
		}

		/**
		 * Allow changing valid mime types for upload.
		 *
		 * @since 1.7.0
		 *
		 * @param array            $mimes     Mime types.
		 * @param int|WP_User|null $user      User ID, User object or null if not provided (indicates current user).
		 * @param array            $form_data Form data and settings.
		 *
		 * @return array
		 */
		return (array) apply_filters( 'wpforms_richtext_upload_mimes', $mimes, $user, $this->form_data );
	}

	/**
	 * Edit some media view strings to reference a form instead of a page/post.
	 *
	 * @since 1.7.0
	 *
	 * @param array   $strings List of media view strings.
	 * @param WP_Post $post    Post object.
	 *
	 * @return array Modified media view strings.
	 */
	public function edit_media_view_strings( $strings, $post ) {

		$strings['insertIntoPost']     = esc_html__( 'Insert into form', 'wpforms' );
		$strings['uploadedToThisPost'] = esc_html__( 'Uploaded to this form', 'wpforms' );

		$strings_to_empty = [
			'addMedia',
			'createGalleryTitle',
			'createPlaylistTitle',
			'createVideoPlaylistTitle',
			'insertFromUrlTitle',
			'filterAttachments',
			'filterByDate',
			'filterByType',
			'playlistDragInfo',
			'searchLabel',
			'setFeaturedImage',
			'setFeaturedImageTitle',
			'videoPlaylistDragInfo',
		];

		foreach ( $strings_to_empty as $string ) {
			$strings[ $string ] = '';
		}

		/**
		 * Allow filtering Rich Text field media view strings.
		 *
		 * @since 1.7.0
		 *
		 * @param array   $strings Current strings.
		 * @param WP_Post $post    Post object.
		 */
		return (array) apply_filters( 'wpforms_richtext_edit_media_view_strings', $strings, $post );
	}

	/**
	 * Helper to get the list of TinyMCE plugins.
	 *
	 * @since 1.7.0
	 *
	 * @param int   $field_id Field ID.
	 * @param array $primary  Field data.
	 *
	 * @return array TinyMCE plugins.
	 */
	private function get_tinymce_plugins( $field_id, $primary ) {

		$plugins = [
			'charmap',
			'colorpicker',
			'hr',
			'link',
			'lists',
			'paste',
			'tabfocus',
			'textcolor',
			'wordpress',
			'wpemoji',
			'wptextpattern',
		];

		/**
		 * Allow filtering the list of TinyMCE plugins.
		 *
		 * @since 1.7.0
		 *
		 * @param array $plugins  TinyMCE plugins.
		 * @param int   $field_id Field ID.
		 * @param array $primary  Field data.
		 */
		return (array) apply_filters( 'wpforms_richtext_get_tinymce_plugins', $plugins, $field_id, $primary );
	}

	/**
	 * Helper to get the first-row list of TinyMCE buttons.
	 *
	 * @since 1.7.0
	 *
	 * @param int    $field_id Field ID.
	 * @param array  $primary  Field data.
	 * @param string $mode     MCE mode.
	 *
	 * @return array TinyMCE buttons.
	 */
	private function get_toolbar1( $field_id, $primary, $mode ) {

		if ( $mode === 'full' ) {
			$toolbar = [
				'formatselect',
				'bold',
				'italic',
				'bullist',
				'numlist',
				'blockquote',
				'alignleft',
				'aligncenter',
				'alignright',
				'link',
				'wp_add_media',
				'wp_more',
				'wp_adv',
			];
		} else {
			$toolbar = [
				'bold',
				'italic',
				'underline',
				'strikethrough',
				'bullist',
				'numlist',
				'blockquote',
				'alignleft',
				'aligncenter',
				'alignright',
				'undo',
				'redo',
				'link',
				'wp_add_media',
			];
		}

		if ( ! $this->is_media_enabled ) {
			$toolbar = array_diff( $toolbar, [ 'wp_add_media' ] );
		}

		/**
		 * Allow filtering the first-row list of TinyMCE buttons (Visual tab).
		 *
		 * @since 1.7.0
		 *
		 * @param array $toolbar  TinyMCE buttons.
		 * @param int   $field_id Field ID.
		 * @param array $primary  Field data.
		 */
		return (array) apply_filters( 'wpforms_richtext_get_toolbar1', $toolbar, $field_id, $primary );
	}

	/**
	 * Helper to get the second-row list of TinyMCE buttons.
	 *
	 * @since 1.7.0
	 *
	 * @param int    $field_id Field ID.
	 * @param array  $primary  Field data.
	 * @param string $mode     MCE mode.
	 *
	 * @return array TinyMCE buttons.
	 */
	private function get_toolbar2( $field_id, $primary, $mode ) {

		$toolbar = [];

		if ( $mode === 'full' ) {
			$toolbar = [
				'strikethrough',
				'hr',
				'forecolor',
				'pastetext',
				'removeformat',
				'charmap',
				'outdent',
				'indent',
				'undo',
				'redo',
				'wp_help',
			];
		}

		/**
		 * Allow filtering the second-row list of TinyMCE buttons (Visual tab).
		 *
		 * @since 1.7.0
		 *
		 * @param array $toolbar  TinyMCE buttons.
		 * @param int   $field_id Field ID.
		 * @param array $primary  Field data.
		 */
		return (array) apply_filters( 'wpforms_richtext_get_toolbar2', $toolbar, $field_id, $primary );
	}

	/**
	 * Helper to determine if the ID of a post is the magic wpforms post ID
	 * e.g. 'wpforms-{form_id}-field_{field_id}'.
	 *
	 * @since 1.7.0
	 *
	 * @param string $id Post ID to check.
	 *
	 * @return bool
	 */
	private function is_wpforms_post_id( $id ) {

		return strpos( $id, 'wpforms-' ) === 0;
	}

	/**
	 * Format and sanitize field.
	 *
	 * @since 1.7.0
	 *
	 * @param int   $field_id     Field ID.
	 * @param mixed $field_submit Field value that was submitted.
	 * @param array $form_data    Form data and settings.
	 */
	public function format( $field_id, $field_submit, $form_data ) {

		remove_filter( "wpforms_pro_fields_entry_preview_get_field_value_{$this->type}_field_after", 'nl2br', 100 );

		if ( is_array( $field_submit ) ) {
			$field_submit = implode( "\r\n", array_filter( $field_submit ) );
		}

		wpforms()->get( 'process' )->fields[ $field_id ] = [
			'name'  => ! empty( $form_data['fields'][ $field_id ]['label'] ) ? sanitize_text_field( $form_data['fields'][ $field_id ]['label'] ) : '',
			'value' => wpforms_sanitize_richtext_field( $field_submit ),
			'id'    => absint( $field_id ),
			'type'  => $this->type,
		];
	}

	/**
	 * Validate field on form submit.
	 *
	 * @since 1.7.0
	 *
	 * @param int   $field_id     Field ID.
	 * @param mixed $field_submit Field value that was submitted.
	 * @param array $form_data    Form data and settings.
	 */
	public function validate( $field_id, $field_submit, $form_data ) {

		if ( empty( $form_data['fields'][ $field_id ] ) ) {
			return;
		}

		$value = wpforms_esc_richtext_field( $field_submit );

		if ( ! empty( $form_data['fields'][ $field_id ]['required'] ) && empty( $value ) ) {
			wpforms()->get( 'process' )->errors[ $form_data['id'] ][ $field_id ] = wpforms_get_required_label();
		}
	}

	/**
	 * Process submitted images.
	 *
	 * @since 1.7.0
	 *
	 * @param array $entry     Submitted form data.
	 * @param array $form_data Form data and settings.
	 */
	public function process_submitted_images( $entry, $form_data ) {

		foreach ( $form_data['fields'] as $field ) {
			if ( $this->is_richtext_field( $field ) && ! empty( $field['media_enabled'] ) ) {
				$this->process_submitted_images_for_field( $entry['fields'][ $field['id'] ] );
			}
		}
	}

	/**
	 * Remove submitted images from scheduled media cleanup.
	 *
	 * @since 1.7.0
	 *
	 * @param string $field_value Field value.
	 */
	private function process_submitted_images_for_field( $field_value ) {

		preg_match_all( '/<img.*src=([\'\"])(.*)\1.*>/mU', $field_value, $matches );

		if ( empty( $matches[2] ) ) {
			return;
		}

		$upload_url     = wp_upload_dir()['url'];
		$wpf_upload_url = wpforms_upload_dir()['url'];

		foreach ( $matches[2] as $url ) {

			if ( strpos( $url, $upload_url ) === false && strpos( $url, $wpf_upload_url ) === false ) {
				continue;
			}

			// Remove image size from the image name.
			$file_name        = pathinfo( $url, PATHINFO_FILENAME );
			$file_name_origin = preg_replace( '/-\d+x\d+$/', '', $file_name );
			$url              = str_replace( $file_name, $file_name_origin, $url );
			$attachment_id    = attachment_url_to_postid( $url );

			if ( empty( $attachment_id ) ) {
				return;
			}

			wpforms()
				->get( 'tasks' )
				->create( self::MEDIA_CLEANUP_ACTION )
				->params( absint( $attachment_id ) )
				->cancel();
		}
	}

	/**
	 * Perform delete the attachment.
	 *
	 * @since 1.7.0
	 *
	 * @param int $meta_id ID for meta information for a task.
	 */
	public function delete_attachment( $meta_id ) {

		$task_meta = wpforms()->get( 'tasks_meta' );
		$meta      = $task_meta->get( (int) $meta_id );

		if ( empty( $meta ) || empty( $meta->data ) ) {
			return;
		}

		list( $attachment ) = $meta->data;

		if ( get_post_type( $attachment ) !== 'attachment' ) {
			return;
		}

		wp_delete_attachment( $attachment, true );
	}

	/**
	 * Helper to convert the size of the field, saved as a string, to a number for the number of rows.
	 *
	 * @since 1.7.0
	 *
	 * @param string $size Can be 'small', 'medium', or 'large'.
	 *
	 * @return int Size of value.
	 */
	private function get_size_value_for_field( $size = 'medium' ) {

		$value = 120;

		if ( $size === 'small' ) {
			$value = 70;
		}

		if ( $size === 'large' ) {
			$value = 220;
		}

		/**
		 * Allow filtering the Rich Text field size value.
		 *
		 * @since 1.7.0
		 *
		 * @param int    $value Size value.
		 * @param string $size  Size name.
		 */
		return (int) apply_filters( 'wpforms_richtext_get_size_value_for_field', $value, $size );
	}

	/**
	 * Filter the entry view to allow for HTML display, instead of stripping all tags.
	 *
	 * @since 1.7.0
	 *
	 * @param string $field_value Entry text.
	 * @param array  $field       Field data.
	 * @param array  $form_data   Form data and settings.
	 * @param string $context     Value display context.
	 *
	 * @return string Entry HTML.
	 */
	public function allow_tags_for_richtext_entry_view( $field_value, $field, $form_data, $context ) {

		if ( empty( $field['value'] ) || ! $this->is_richtext_field( $field ) ) {
			return $field_value;
		}

		if ( $context === 'entry-single' ) {
			return $this->get_entry_single_field_value_iframe( $field );
		}

		if ( $context === 'email-html' ) {
			return wpforms_esc_richtext_field( $field['value'] );
		}

		return wpforms_sanitize_richtext_field( $field['value'] );
	}

	/**
	 * Helper to easily check if a request is for the Rich Text field media, as we
	 * modify the post ID in the request for our actions.
	 *
	 * @since 1.7.0
	 *
	 * @return bool
	 */
	private function is_valid_request() {

		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! isset( $_POST['post_id'], $_POST['action'] ) ) {
			return false;
		}

		if ( ! in_array( sanitize_key( $_POST['action'] ), $this->allowed_ajax_actions, true ) ) {
			return false;
		}

		return $this->is_wpforms_post_id( sanitize_key( $_POST['post_id'] ) );
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * If a non-logged in user attempts to upload media through the media modal, they
	 * get redirected to the admin page and the upload fails. We check to make
	 * sure the request is for the Rich Text field, and if so, we bypass the redirect.
	 *
	 * @since 1.7.0
	 *
	 * @param bool $secure This is from us hooking into an early action, we don't really want to modify it.
	 *
	 * @return bool Location string if not modified, otherwise false.
	 */
	public function override_auth_for_ajax_media_calls( $secure ) {

		if ( ! $this->is_valid_async_upload_request() ) {
			return $secure;
		}

		add_filter( 'login_url', '__return_false', 1000 );

		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'media-form' ) ) {
			return false;
		}

		$attachment = $this->upload_attachment();

		if ( empty( $attachment['id'] ) ) {
			return false;
		}

		/**
		 * Allow filtering Rich Text field media cleanup window time.
		 *
		 * @since 1.7.0
		 *
		 * @param int $time Time.
		 */
		$time = (int) apply_filters( 'wpforms_richtext_override_auth_for_ajax_media_calls_time', time() + 1 * DAY_IN_SECONDS );

		wpforms()
			->get( 'tasks' )
			->create( self::MEDIA_CLEANUP_ACTION )
			->once( $time )
			->params(
				absint( $attachment['id'] )
			)
			->register();

		wp_send_json_success( $attachment );

		return true;
	}

	/**
	 * Do our own query attachment action.
	 *
	 * @since 1.7.0
	 */
	public function media_query_attachments() {

		wp_send_json_success( [] );
	}

	/**
	 * Do our own upload attachment action.
	 *
	 * @since 1.7.0
	 *
	 * @return array
	 */
	private function upload_attachment() {

		preg_match_all( '/\d+/', sanitize_key( $_POST['post_id'] ), $matches ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$form_id  = ! empty( $matches[0][0] ) ? absint( $matches[0][0] ) : 0;
		$field_id = isset( $matches[0][1] ) ? absint( $matches[0][1] ) : 0;

		if ( empty( $form_id ) || wpforms_is_empty_string( $field_id ) ) {
			wp_send_json_error();
		}

		$form = wpforms()->get( 'form' )
			->get(
				absint( $form_id ),
				[
					'cap' => false, // Allow get the form for non-logged users.
				]
			);

		if ( empty( $form ) ) {
			wp_send_json_error();
		}

		$form_data = wpforms_decode( $form->post_content );

		if ( ! $form_data || empty( $form_data['id'] ) || ! $this->is_media_enabled( $form_data, $field_id ) ) {
			wp_send_json_error();
		}

		$field = $this->get_field_settings( $form_data, $field_id );

		if ( ! $this->is_richtext_field( $field ) ) {
			wp_send_json_error();
		}

		$is_media_integrated  = $this->is_media_integrated( $form_data, $field_id );
		$form_data['created'] = $form->post_date;

		if ( ! $is_media_integrated ) {

			$this->form_id   = $form_id;
			$this->form_data = $form_data;

			add_filter( 'upload_dir', [ $this, 'modify_upload_directory' ], 1 );
			add_filter( 'update_attached_file', [ $this, 'modify_attached_file_path' ], 10, 2 );
		}

		$file = $this->upload->process_file(
			$_FILES['async-upload'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$field_id,
			$form_data,
			true
		);

		if ( empty( $file ) ) {
			wp_send_json_error();
		}

		$attachment = wp_prepare_attachment_for_js( $file['attachment_id'] );

		if ( ! $attachment || empty( $attachment['id'] ) || is_wp_error( $attachment ) ) {
			wp_send_json_error();
		}

		$attachment['title']    = pathinfo( $attachment['filename'], PATHINFO_FILENAME );
		$attachment['link']     = '';
		$attachment['editLink'] = '';

		$attachment['wpforms_richtext_media_integrated'] = $is_media_integrated;

		$this->generate_attachment_meta( $attachment['id'], $form_data, $field_id );

		return $attachment;
	}

	/**
	 * Replace the 'path' key to wp_uploads_dir $dir with the wpforms 'path'.
	 *
	 * @since 1.7.0
	 *
	 * @param array $dir Array of data to pass to wp_upload_dir().
	 *
	 * @return array WPForms upload root path (no trailing slash).
	 */
	public function modify_upload_directory( $dir ) {

		remove_filter( 'upload_dir', [ $this, 'modify_upload_directory' ], 1 );

		if ( ! is_array( $dir ) ) {
			$dir = [];
		}

		$wpforms_upload_dir = wpforms_upload_dir();
		$form_directory     = $this->upload->get_form_directory( $this->form_id, $this->form_data['created'] );
		$dir['path']        = wp_normalize_path( trailingslashit( $wpforms_upload_dir['path'] ) . $form_directory );
		$dir['url']         = trailingslashit( $wpforms_upload_dir['url'] ) . $form_directory;

		wpforms_create_upload_dir_htaccess_file();
		wpforms_create_index_html_file( $wpforms_upload_dir['path'] );
		wp_mkdir_p( $dir['path'] );
		wpforms_create_index_html_file( $dir['path'] );

		return $dir;
	}

	/**
	 * Correct an attachment file path.
	 *
	 * @since 1.7.0
	 *
	 * @param string $file_path     Path to the attached file to update.
	 * @param int    $attachment_id Attachment ID.
	 *
	 * @return string
	 */
	public function modify_attached_file_path( $file_path, $attachment_id ) {

		return substr( $file_path, strrpos( $file_path, 'wpforms/' ) );
	}

	/**
	 * Generate the attachment data + add a few meta values.
	 *
	 * @since 1.7.0
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $form_data     Form data and settings.
	 * @param int   $field_id      Field ID.
	 */
	private function generate_attachment_meta( $attachment_id, $form_data, $field_id ) {

		$meta_input = [
			'wpforms_richtext_attachment_uploaded_to_form_id'  => $form_data['id'],
			'wpforms_richtext_attachment_uploaded_to_field_id' => $field_id,
			'wpforms_richtext_attachment_uploaded_by_user_ip'  => wpforms_get_ip(),
		];

		if ( get_current_user_id() ) {
			$meta_input['wpforms_richtext_attachment_uploaded_by_user_id'] = get_current_user_id();
		}

		if ( ! $this->is_media_integrated( $form_data, $field_id ) ) {
			$meta_input['wpforms_richtext_attachment_temporary'] = true;
		}

		wp_update_post(
			[
				'ID'         => $attachment_id,
				'meta_input' => $meta_input,
			]
		);
	}

	/**
	 * Check if media is integrated. That is, uploading to WordPress Media Library instead of WPForms directory.
	 *
	 * @since 1.7.0
	 *
	 * @param array $form_data Form data and settings.
	 * @param int   $field_id  Field ID.
	 *
	 * @return bool
	 */
	private function is_media_integrated( $form_data, $field_id ) {

		if ( ! $this->is_media_enabled( $form_data, $field_id ) ) {
			return false;
		}

		$field = $this->get_field_settings( $form_data, $field_id );

		return ! empty( $field['media_library'] );
	}

	/**
	 * Check if media is enabled for a field.
	 *
	 * @since 1.7.0
	 *
	 * @param array $form_data Form data and settings.
	 * @param int   $field_id  Field ID.
	 *
	 * @return bool
	 */
	private function is_media_enabled( $form_data, $field_id ) {

		$field = $this->get_field_settings( $form_data, $field_id );

		return ! empty( $field['media_enabled'] );
	}

	/**
	 * Helper to verify 'fields' exists in the field data array and return it.
	 *
	 * @since 1.7.0
	 *
	 * @param array $field_data Array of field data.
	 * @param int   $field_id   Field ID.
	 *
	 * @return array  Empty array if $field_id isn't valid or 'fields' doesn't exist in $field_data,
	 *                otherwise, the array of 'fields' from $field_data for the $field_id key.
	 */
	private function get_field_settings( $field_data, $field_id ) {

		return ! empty( $field_data['fields'][ $field_id ] ) ? $field_data['fields'][ $field_id ] : [];
	}

	/**
	 * Check if a request is valid for the Rich Text async upload.
	 *
	 * @since 1.7.0
	 *
	 * @return bool Invalid state.
	 */
	private function is_valid_async_upload_request() {

		if ( ! $this->is_valid_request() ) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! isset( $_POST['action'], $_FILES['async-upload'] ) ) {
			return false;
		}

		return sanitize_key( $_POST['action'] ) === 'upload-attachment';
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Retrieve an iframe for displaying field value on the single entry page.
	 *
	 * @since 1.7.0
	 *
	 * @param array $field Field data.
	 *
	 * @return string Iframe HTML.
	 */
	public function get_entry_single_field_value_iframe( $field ) {

		return sprintf(
			'<iframe data-src="%s" class="wpforms-entry-field-value-richtext"></iframe>',
			add_query_arg(
				[
					'richtext_field_id' => absint( $field['id'] ),
				]
			)
		);
	}

	/**
	 * Display trimmed text on the entries overview page.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value       Value.
	 * @param object $entry       Current entry data.
	 * @param string $column_name Current column name.
	 * @param string $field_type  Field type.
	 *
	 * @return string
	 */
	public function entry_table_value( $value, $entry, $column_name, $field_type ) {

		if ( $field_type !== $this->type ) {
			return $value;
		}

		return sprintf( '<div data-field-type="%s">%s</div>', esc_attr( $this->type ), wp_trim_words( $value ) );
	}

	/**
	 * Pass additional settings and strings to JavaScript.
	 *
	 * @since 1.7.0
	 *
	 * @param array $strings Frontend strings.
	 *
	 * @return array Frontend strings.
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	public function add_frontend_strings( $strings ) {

		$version = 'ver=' . get_bloginfo( 'version' );
		$min     = wpforms_get_min_suffix();

		$strings['richtext_add_media_button']   = version_compare( get_bloginfo( 'version' ), '5.0', '<' );
		$strings['entry_preview_iframe_styles'] = [
			esc_url( includes_url( "js/tinymce/skins/lightgray/content.min.css?{$version}" ) ),
			esc_url( includes_url( "css/dashicons{$min}.css?{$version}" ) ),
			esc_url( includes_url( "js/tinymce/skins/wordpress/wp-content.css?{$version}" ) ),
			esc_url( $this->get_editor_content_css_url() ),
		];

		return $strings;
	}

	/**
	 * Wrap up the entry preview to iframe container.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value     Value.
	 * @param array  $field     Field data.
	 * @param array  $form_data Form data and settings.
	 *
	 * @return string
	 */
	public function entry_preview( $value, $field, $form_data ) {

		return sprintf( '<div class="wpforms-iframe">%s</div>', wpforms_esc_richtext_field( $value ) );
	}

	/**
	 * Modify Quicktags settings.
	 *
	 * @since 1.7.0
	 *
	 * @param array  $qt_init   Quicktags init settings.
	 * @param string $editor_id Editor ID.
	 *
	 * @return array
	 */
	public function modify_quicktags( $qt_init, $editor_id ) {

		// This callback is executed for all TinyMCE editors, on the Builder page as well.
		// First conditional check for verifying a prefix of editor ID is not enough
		// and `link` quick buttons are removed through the Builder page.
		// That's why we run the second conditional check.
		if (
			strpos( $editor_id, 'wpforms' ) !== 0 ||
			wpforms_is_admin_page( 'builder' )
		) {
			return $qt_init;
		}

		$buttons  = explode( ',', $qt_init['buttons'] );
		$link_key = array_search( 'link', $buttons, true );

		if ( $link_key === false ) {
			return $qt_init;
		}

		unset( $buttons[ $link_key ] );

		$qt_init['buttons'] = implode( ',', $buttons );

		return $qt_init;
	}

	/**
	 * Hide temporary attachments from WordPress Media Library.
	 *
	 * @since 1.7.0
	 *
	 * @param WP_Query $wp_query WP Query.
	 */
	public function modify_attachment_query( $wp_query ) {

		if ( empty( $wp_query->query_vars['post_type'] ) || $wp_query->query_vars['post_type'] !== 'attachment' ) {
			return;
		}

		$rich_text_meta = [
			'key'     => 'wpforms_richtext_attachment_temporary',
			'compare' => 'NOT EXISTS',
		];

		if ( empty( $wp_query->query_vars['meta_query'] ) ) {
			$meta_query = [
				$rich_text_meta,
			];
		} else {
			$meta_query = [
				'relation' => 'AND',
				$wp_query->query_vars['meta_query'],
				$rich_text_meta,
			];
		}

		$wp_query->set( 'meta_query', $meta_query );
	}

	/**
	 * Restrict attachments by mime types.
	 *
	 * @since 1.7.0
	 *
	 * @param array $args WP_Query arguments.
	 *
	 * @return array
	 */
	public function restrict_attachments_by_mime_types( $args ) {

		$args['post_mime_type'] = array_values( get_allowed_mime_types() );

		return $args;
	}

	/**
	 * Get editor content CSS URL.
	 *
	 * @since 1.8.5
	 *
	 * @return string
	 */
	private function get_editor_content_css_url(): string {

		$min = wpforms_get_min_suffix();

		return WPFORMS_PLUGIN_URL . "assets/pro/css/fields/richtext/editor-content{$min}.css";
	}
}

new WPForms_Field_RichText();
