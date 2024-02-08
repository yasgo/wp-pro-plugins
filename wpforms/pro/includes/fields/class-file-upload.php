<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPForms\Pro\Forms\Fields\FileUpload\Chunk;
use WPForms\Pro\Helpers\Upload;
use WPForms\Pro\Robots;

/**
 * File upload field.
 *
 * @since 1.0.0
 */
class WPForms_Field_File_Upload extends WPForms_Field {

	/**
	 * Dropzone plugin version.
	 *
	 * @since 1.5.6
	 *
	 * @var string
	 */
	const DROPZONE_VERSION = '5.9.3';

	/**
	 * Classic (old) style of file uploader field.
	 *
	 * @since 1.5.6
	 *
	 * @var string
	 */
	const STYLE_CLASSIC = 'classic';

	/**
	 * Modern style of file uploader field.
	 *
	 * @since 1.5.6
	 *
	 * @var string
	 */
	const STYLE_MODERN = 'modern';

	/**
	 * Maximum file number.
	 *
	 * @since 1.8.0
	 *
	 * @var int
	 */
	const MAX_FILE_NUM = 100;

	/**
	 * Replaceable (either in PHP or JS) template for a maximum file number.
	 *
	 * @since 1.5.8
	 *
	 * @var string
	 */
	const TEMPLATE_MAXFILENUM = '{maxFileNumber}';

	/**
	 * Handle name for wp_register_styles handle.
	 *
	 * @since 1.7.7
	 *
	 * @var string
	 */
	const HANDLE = 'wpforms-dropzone';

	/**
	 * File extensions that are now allowed.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $denylist = [ 'ade', 'adp', 'app', 'asp', 'bas', 'bat', 'cer', 'cgi', 'chm', 'cmd', 'com', 'cpl', 'crt', 'csh', 'csr', 'dll', 'drv', 'exe', 'fxp', 'flv', 'hlp', 'hta', 'htaccess', 'htm', 'html', 'htpasswd', 'inf', 'ins', 'isp', 'jar', 'js', 'jse', 'jsp', 'ksh', 'lnk', 'mdb', 'mde', 'mdt', 'mdw', 'msc', 'msi', 'msp', 'mst', 'ops', 'pcd', 'php', 'pif', 'pl', 'prg', 'ps1', 'ps2', 'py', 'rb', 'reg', 'scr', 'sct', 'sh', 'shb', 'shs', 'sys', 'swf', 'tmp', 'torrent', 'url', 'vb', 'vbe', 'vbs', 'vbscript', 'wsc', 'wsf', 'wsf', 'wsh', 'dfxp', 'onetmp' ];

	/**
	 * Upload files helper.
	 *
	 * @since 1.7.0
	 *
	 * @var Upload
	 */
	private $upload;

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Define field type information.
		$this->name  = esc_html__( 'File Upload', 'wpforms' );
		$this->type  = 'file-upload';
		$this->icon  = 'fa-upload';
		$this->order = 100;
		$this->group = 'fancy';

		// Init our upload helper & add the actions.
		$this->upload = new Upload();

		// Form frontend javascript.
		add_action( 'wpforms_frontend_js', [ $this, 'frontend_js' ] );

		// Form frontend CSS.
		add_action( 'wpforms_frontend_css', [ $this, 'frontend_css' ] );

		// Field styles for Gutenberg.
		add_action( 'enqueue_block_editor_assets', [ $this, 'gutenberg_enqueues' ] );

		// Field styles for Gutenberg. Register after wpforms-pro-integrations.
		add_action( 'init', [ $this, 'register_gutenberg_styles' ], 20 );

		// Set editor style handle for block type editor.
		add_filter( 'register_block_type_args', [ $this, 'register_block_type_args' ], 10, 2 );

		// Define additional field properties.
		add_filter( 'wpforms_field_properties_file-upload', [ $this, 'field_properties' ], 5, 3 );

		// Customize value format.
		add_filter( 'wpforms_html_field_value', [ $this, 'html_field_value' ], 10, 4 );

		// Add builder strings.
		add_filter( 'wpforms_builder_strings', [ $this, 'add_builder_strings' ], 10, 2 );

		// Upload file ajax route.
		add_action( 'wp_ajax_wpforms_file_upload_speed_test', 'wp_send_json_success' );
		add_action( 'wp_ajax_nopriv_wpforms_file_upload_speed_test', 'wp_send_json_success' );

		// TODO: perhaps remove, chunks uploading replaces this.
		add_action( 'wp_ajax_wpforms_upload_file', [ $this, 'ajax_modern_upload' ] );
		add_action( 'wp_ajax_nopriv_wpforms_upload_file', [ $this, 'ajax_modern_upload' ] );

		// Ajax handlers for newest uploads (With chunks and parallel support).
		add_action( 'wp_ajax_wpforms_upload_chunk_init', [ $this, 'ajax_chunk_upload_init' ] );
		add_action( 'wp_ajax_nopriv_wpforms_upload_chunk_init', [ $this, 'ajax_chunk_upload_init' ] );

		add_action( 'wp_ajax_wpforms_upload_chunk', [ $this, 'ajax_chunk_upload' ] );
		add_action( 'wp_ajax_nopriv_wpforms_upload_chunk', [ $this, 'ajax_chunk_upload' ] );

		add_action( 'wp_ajax_wpforms_file_chunks_uploaded', [ $this, 'ajax_chunk_upload_finalize' ] );
		add_action( 'wp_ajax_nopriv_wpforms_file_chunks_uploaded', [ $this, 'ajax_chunk_upload_finalize' ] );

		// Remove file ajax route.
		add_action( 'wp_ajax_wpforms_remove_file', [ $this, 'ajax_modern_remove' ] );
		add_action( 'wp_ajax_nopriv_wpforms_remove_file', [ $this, 'ajax_modern_remove' ] );

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! empty( $_POST['slow'] ) && $_POST['slow'] === 'true' && ! empty( $this->ajax_validate_form_field_modern() ) ) {
			add_action( 'wpforms_file_upload_chunk_parallel', '__return_false' );
			add_action( 'wpforms_file_upload_chunk_size', [ $this, 'get_slow_connection_chunk_size' ] );
		}

		add_filter( 'wpforms_pro_admin_entries_edit_field_output_editable', [ $this, 'is_editable' ], 10, 4 );

		add_filter( 'wpforms_process_after_filter', [ $this, 'upload_complete' ], 10, 3 );

		add_filter( 'wpforms_pro_fields_entry_preview_is_field_support_preview_file-upload_field', '__return_false' );
	}

	/**
	 * Enqueue frontend field js.
	 *
	 * @since 1.5.6
	 *
	 * @param array $forms Forms on the current page.
	 */
	public function frontend_js( $forms ) {

		$is_file_modern_style = false;

		foreach ( $forms as $form ) {
			if ( $this->is_field_style( $form, self::STYLE_MODERN ) ) {
				$is_file_modern_style = true;

				break;
			}
		}

		if (
			$is_file_modern_style ||
			wpforms()->get( 'frontend' )->assets_global()
		) {

			$min = wpforms_get_min_suffix();

			wp_enqueue_script(
				self::HANDLE,
				WPFORMS_PLUGIN_URL . 'assets/pro/lib/dropzone.min.js',
				[ 'jquery' ],
				self::DROPZONE_VERSION,
				true
			);

			wp_enqueue_script(
				'wpforms-file-upload',
				WPFORMS_PLUGIN_URL . "assets/pro/js/wpforms-file-upload.es5{$min}.js",
				[ 'wpforms', 'wp-util', self::HANDLE ],
				WPFORMS_VERSION,
				true
			);

			wp_localize_script(
				self::HANDLE,
				'wpforms_file_upload',
				[
					'url'             => admin_url( 'admin-ajax.php' ),
					'errors'          => [
						'default_error'     => esc_html__( 'Something went wrong, please try again.', 'wpforms' ),
						'file_not_uploaded' => esc_html__( 'This file was not uploaded.', 'wpforms' ),
						'file_limit'        => wpforms_setting(
							'validation-maxfilenumber',
							sprintf( /* translators: %s - max number of files allowed. */
								esc_html__( 'File uploads exceed the maximum number allowed (%s).', 'wpforms' ),
								'{fileLimit}'
							)
						),
						'file_extension'    => wpforms_setting( 'validation-fileextension', esc_html__( 'File type is not allowed.', 'wpforms' ) ),
						'file_size'         => wpforms_setting( 'validation-filesize', esc_html__( 'File exceeds the max size allowed.', 'wpforms' ) ),
						'post_max_size'     => sprintf( /* translators: %s - max allowed file size by a server. */
							esc_html__( 'File exceeds the upload limit allowed (%s).', 'wpforms' ),
							wpforms_max_upload()
						),
					],
					'loading_message' => esc_html__( 'File upload is in progress. Please submit the form once uploading is completed.', 'wpforms' ),
				]
			);
		}
	}

	/**
	 * Enqueue frontend field CSS.
	 *
	 * @since 1.5.6
	 *
	 * @param array $forms Forms on the current page.
	 */
	public function frontend_css( $forms ) {

		$is_file_modern_style = false;

		foreach ( $forms as $form ) {
			if ( $this->is_field_style( $form, self::STYLE_MODERN ) ) {
				$is_file_modern_style = true;

				break;
			}
		}

		if (
			$is_file_modern_style ||
			wpforms()->get( 'frontend' )->assets_global()
		) {

			$min = wpforms_get_min_suffix();

			wp_enqueue_style(
				self::HANDLE,
				WPFORMS_PLUGIN_URL . "assets/pro/css/dropzone{$min}.css",
				[],
				self::DROPZONE_VERSION
			);
		}
	}

	/**
	 * Whether provided form has a file field with a specified style.
	 *
	 * @since 1.5.6
	 *
	 * @param array  $form  Form data.
	 * @param string $style Desired field style.
	 *
	 * @return bool
	 */
	protected function is_field_style( $form, $style ) {

		$is_field_style = false;

		if ( empty( $form['fields'] ) ) {
			return $is_field_style;
		}

		foreach ( (array) $form['fields'] as $field ) {

			if (
				! empty( $field['type'] ) &&
				$field['type'] === $this->type &&
				! empty( $field['style'] ) &&
				$field['style'] === sanitize_key( $style )
			) {
				$is_field_style = true;

				break;
			}
		}

		return $is_field_style;
	}

	/**
	 * Load enqueues for the Gutenberg editor.
	 *
	 * @since 1.5.6
	 */
	public function gutenberg_enqueues() {

		if ( version_compare( get_bloginfo( 'version' ), '5.5', '>=' ) ) {
			return;
		}

		wp_enqueue_style( self::HANDLE );
	}

	/**
	 * Register Gutenberg block styles.
	 *
	 * @since 1.7.4.2
	 */
	public function register_gutenberg_styles() {

		$min  = wpforms_get_min_suffix();
		$deps = is_admin() ? [ 'wpforms-pro-integrations' ] : [];

		wp_register_style(
			self::HANDLE,
			WPFORMS_PLUGIN_URL . "assets/pro/css/dropzone{$min}.css",
			$deps,
			self::DROPZONE_VERSION
		);
	}

	/**
	 * Set editor style handle for block type editor.
	 *
	 * @since 1.7.4.2
	 *
	 * @param array  $args       Array of arguments for registering a block type.
	 * @param string $block_type Block type name including namespace.
	 */
	public function register_block_type_args( $args, $block_type ) {

		if ( $block_type !== 'wpforms/form-selector' ) {
			return $args;
		}

		// The Full Site Editor (FSE) uses an iframe with the site editor.
		// It inserts into the iframe only those scripts defined during the block registration.
		// Here we set the 'editor_style' field of the 'wpforms/form-selector' block to the current handle.
		// All other styles required for 'wpforms/form-selector' block will be loaded as dependencies.
		// So, our styles will be loaded in the following order:
		// wpforms-integrations
		// wpforms-gutenberg-form-selector
		// wpforms-pro-integrations
		// wpforms-dropzone.
		$args['editor_style'] = self::HANDLE;

		return $args;
	}

	/**
	 * Define additional field properties.
	 *
	 * @since 1.3.7
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Field data and settings.
	 * @param array $form_data  Form data and settings.
	 *
	 * @return array
	 */
	public function field_properties( $properties, $field, $form_data ) {

		$this->form_data  = (array) $form_data;
		$this->form_id    = absint( $this->form_data['id'] );
		$this->field_id   = absint( $field['id'] );
		$this->field_data = $this->form_data['fields'][ $this->field_id ];

		// Input Primary: adjust name.
		$properties['inputs']['primary']['attr']['name'] = "wpforms_{$this->form_id}_{$this->field_id}";

		// Input Primary: filter files in classic uploader style in files selection window.
		if ( empty( $this->field_data['style'] ) || self::STYLE_CLASSIC === $this->field_data['style'] ) {
			$properties['inputs']['primary']['attr']['accept'] = rtrim( '.' . implode( ',.', $this->get_extensions() ), ',.' );
		}

		// Input Primary: allowed file extensions.
		$properties['inputs']['primary']['data']['rule-extension'] = implode( ',', $this->get_extensions() );

		// Input Primary: max file size.
		$properties['inputs']['primary']['data']['rule-maxsize'] = $this->max_file_size();

		return $properties;
	}

	/**
	 * Whether current field can be populated dynamically.
	 *
	 * @since 1.5.0
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Current field specific data.
	 *
	 * @return bool
	 */
	public function is_dynamic_population_allowed( $properties, $field ) {

		// We need to disable an ability to steal files from user computer.
		return false;
	}

	/**
	 * Whether current field can be populated dynamically.
	 *
	 * @since 1.5.0
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Current field specific data.
	 *
	 * @return bool
	 */
	public function is_fallback_population_allowed( $properties, $field ) {

		// We need to disable an ability to steal files from user computer.
		return false;
	}

	/**
	 * Customize format for HTML display.
	 *
	 * Additionally, truncates the list of files on the entry table view.
	 *
	 * @since 1.7.6
	 *
	 * @param string $val       Field value.
	 * @param array  $field     Field settings.
	 * @param array  $form_data Form data and settings.
	 * @param string $context   Value display context.
	 *
	 * @return string
	 */
	public function html_field_value( $val, $field, $form_data = [], $context = '' ) {

		if ( empty( $field['value'] ) || $field['type'] !== $this->type ) {
			return $val;
		}

		// Process modern uploader.
		if ( ! empty( $field['value_raw'] ) ) {
			$values = $context === 'entry-table' ? array_slice( $field['value_raw'], 0, 3, true ) : $field['value_raw'];
			$html   = wpforms_chain( $values )
				->map(
					function ( $file ) use ( $context ) {

						if ( empty( $file['value'] ) || empty( $file['file_original'] ) ) {
							return '';
						}

						return $this->get_file_link_html( $file, $context ) . '<br>';
					}
				)
				->array_filter()
				->implode()
				->value();

			if ( count( $values ) < count( $field['value_raw'] ) ) {
				$html .= '&hellip;';
			}

			return $html;
		}

		return $this->get_file_link_html( $field, $context );
	}

	/**
	 * Customize format for HTML email notifications.
	 *
	 * @since 1.1.3
	 * @since 1.5.6 Added different link generation for classic and modern uploader.
	 * @deprecated 1.7.6
	 *
	 * @param string $val       Field value.
	 * @param array  $field     Field settings.
	 * @param array  $form_data Form data and settings.
	 * @param string $context   Value display context.
	 *
	 * @return string
	 */
	public function html_email_value( $val, $field, $form_data = [], $context = '' ) {

		_deprecated_function( __METHOD__, '1.7.6 of the WPForms plugin', __CLASS__ . '::html_field_value()' );

		return $this->html_field_value( $val, $field, $form_data, $context );
	}

	/**
	 * Get file link HTML.
	 *
	 * @since 1.6.6
	 *
	 * @param array  $file    File data.
	 * @param string $context Value display context.
	 *
	 * @return string
	 */
	private function get_file_link_html( $file, $context ) {

		$html  = in_array( $context, [ 'email-html', 'entry-single' ], true ) ? $this->file_icon_html( $file ) : '';
		$html .= sprintf(
			'<a href="%s" rel="noopener noreferrer" target="_blank" style="%s">%s</a>',
			esc_url( $file['value'] ),
			$context === 'email-html' ? 'padding-left:10px;' : '',
			esc_html( $file['file_original'] )
		);

		return $html;
	}

	/**
	 * File Upload field specific strings.
	 *
	 * @since 1.5.8
	 *
	 * @return array Field specific strings.
	 */
	public function get_strings() {

		return [
			'preview_title_single' => esc_html__( 'Click or drag a file to this area to upload.', 'wpforms' ),
			'preview_title_plural' => esc_html__( 'Click or drag files to this area to upload.', 'wpforms' ),
			'preview_hint'         => sprintf( /* translators: % - max number of files as a template string (not a number), replaced by a number later. */
				esc_html__( 'You can upload up to %s files.', 'wpforms' ),
				self::TEMPLATE_MAXFILENUM
			),
		];
	}

	/**
	 * Add Builder strings that are passed to JS.
	 *
	 * @since 1.5.8
	 *
	 * @param array $strings Form Builder strings.
	 * @param array $form    Form Data.
	 *
	 * @return array Form Builder strings.
	 */
	public function add_builder_strings( $strings, $form ) {

		$strings['file_upload'] = $this->get_strings();

		return $strings;
	}

	/**
	 * Field options panel inside the builder.
	 *
	 * @since 1.0.0
	 * @since 1.5.6 Added modern style uploader options.
	 *
	 * @param array $field Field data and settings.
	 */
	public function field_options( $field ) {

		$style = ! empty( $field['style'] ) ? $field['style'] : self::STYLE_MODERN;

		/*
		 * Basic field options.
		 */

		// Options open markup.
		$this->field_option( 'basic-options', $field, [ 'markup' => 'open' ] );

		// Label.
		$this->field_option( 'label', $field );

		// Description.
		$this->field_option( 'description', $field );

		// Allowed extensions.
		$lbl = $this->field_element(
			'label',
			$field,
			[
				'slug'          => 'extensions',
				'value'         => esc_html__( 'Allowed File Extensions', 'wpforms' ),
				'tooltip'       => esc_html__( 'Enter the extensions you would like to allow, comma separated.', 'wpforms' ),
				'after_tooltip' => sprintf(
					'<a href="%1$s" class="after-label-description" target="_blank" rel="noopener noreferrer">%2$s</a>',
					esc_url( wpforms_utm_link( 'https://wpforms.com/docs/a-complete-guide-to-the-file-upload-field/#file-types', 'Field Options', 'File Upload Extensions Documentation' ) ),
					esc_html__( 'See More Details', 'wpforms' )
				),
			],
			false
		);
		$fld = $this->field_element(
			'text',
			$field,
			[
				'slug'  => 'extensions',
				'value' => ! empty( $field['extensions'] ) ? $field['extensions'] : '',
			],
			false
		);
		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'extensions',
				'content' => $lbl . $fld,
			]
		);

		// Max file size.
		$lbl = $this->field_element(
			'label',
			$field,
			[
				'slug'    => 'max_size',
				'value'   => esc_html__( 'Max File Size', 'wpforms' ),
				/* translators: %s - max upload size. */
				'tooltip' => sprintf( esc_html__( 'Enter the max size of each file, in megabytes, to allow. If left blank, the value defaults to the maximum size the server allows which is %s.', 'wpforms' ), wpforms_max_upload() ),
			],
			false
		);
		$fld = $this->field_element(
			'text',
			$field,
			[
				'slug'  => 'max_size',
				'type'  => 'number',
				'attrs' => [
					'min'     => 1,
					'max'     => 512,
					'step'    => 1,
					'pattern' => '[0-9]',
				],
				'value' => ! empty( $field['max_size'] ) ? abs( $field['max_size'] ) : '',
			],
			false
		);
		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'max_size',
				'content' => $lbl . $fld,
			]
		);

		// Max file number.
		$lbl = $this->field_element(
			'label',
			$field,
			[
				'slug'    => 'max_file_number',
				'value'   => esc_html__( 'Max File Uploads', 'wpforms' ),
				'tooltip' => esc_html__( 'Enter the max number of files to allow. If left blank, the value defaults to 1.', 'wpforms' ),
			],
			false
		);

		$fld = $this->field_element(
			'text',
			$field,
			[
				'slug'  => 'max_file_number',
				'type'  => 'number',
				'attrs' => [
					'min'     => 1,
					'max'     => self::MAX_FILE_NUM,
					'step'    => 1,
					'pattern' => '[0-9]',
				],
				'value' => $this->get_max_file_number( $field ),
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'max_file_number',
				'content' => $lbl . $fld,
				'class'   => $style === self::STYLE_CLASSIC ? 'wpforms-hidden' : '',
			]
		);

		// Required toggle.
		$this->field_option( 'required', $field );

		// Options close markup.
		$this->field_option( 'basic-options', $field, [ 'markup' => 'close' ] );

		/*
		 * Advanced field options.
		 */

		// Options open markup.
		$this->field_option( 'advanced-options', $field, [ 'markup' => 'open' ] );

		// Style.
		$lbl = $this->field_element(
			'label',
			$field,
			[
				'slug'    => 'style',
				'value'   => esc_html__( 'Style', 'wpforms' ),
				'tooltip' => esc_html__( 'Modern Style supports multiple file uploads, displays a drag-and-drop upload box, and uses AJAX. Classic Style supports single file upload and displays a traditional upload button.', 'wpforms' ),
			],
			false
		);

		$fld = $this->field_element(
			'select',
			$field,
			[
				'slug'    => 'style',
				'value'   => $style,
				'options' => [
					self::STYLE_MODERN  => esc_html__( 'Modern', 'wpforms' ),
					self::STYLE_CLASSIC => esc_html__( 'Classic', 'wpforms' ),
				],
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'style',
				'content' => $lbl . $fld,
			]
		);

		// Media Library toggle.
		$fld = $this->field_element(
			'toggle',
			$field,
			[
				'slug'    => 'media_library',
				'value'   => ! empty( $field['media_library'] ) ? 1 : '',
				'desc'    => esc_html__( 'Store file in WordPress Media Library', 'wpforms' ),
				'tooltip' => esc_html__( 'Check this option to store the final uploaded file in the WordPress Media Library', 'wpforms' ),
			],
			false
		);

		$this->field_element(
			'row',
			$field,
			[
				'slug'    => 'media_library',
				'content' => $fld,
			]
		);

		// Custom CSS classes.
		$this->field_option( 'css', $field );

		// Hide Label.
		$this->field_option( 'label_hide', $field );

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
	 * Field preview panel inside the builder.
	 *
	 * @since 1.0.0
	 * @since 1.5.6 Added modern style uploader logic.
	 *
	 * @param array $field Field data.
	 */
	public function field_preview( $field ) {

		// Label.
		$this->field_preview_option( 'label', $field );

		$modern_classes  = [ 'wpforms-file-upload-builder-modern' ];
		$classic_classes = [ 'wpforms-file-upload-builder-classic' ];

		if ( empty( $field['style'] ) || $field['style'] !== self::STYLE_CLASSIC ) {
			$classic_classes[] = 'wpforms-hide';
		} else {
			$modern_classes[] = 'wpforms-hide';
		}

		$strings         = $this->get_strings();
		$max_file_number = $this->get_max_file_number( $field );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wpforms_render(
			'fields/file-upload-backend',
			[
				'max_file_number' => $max_file_number,
				'preview_hint'    => str_replace( self::TEMPLATE_MAXFILENUM, $max_file_number, $strings['preview_hint'] ),
				'modern_classes'  => implode( ' ', $modern_classes ),
				'classic_classes' => implode( ' ', $classic_classes ),
			],
			true
		);

		// Description.
		$this->field_preview_option( 'description', $field );
	}

	/**
	 * Only a non-empty field is editable.
	 *
	 * @since 1.6.8.1
	 *
	 * @param bool  $is_editable  Default value.
	 * @param array $field        Field data.
	 * @param array $entry_fields Entry fields data.
	 * @param array $form_data    Form data and settings.
	 *
	 * @return bool
	 */
	public function is_editable( $is_editable, $field, $entry_fields, $form_data ) {

		if ( $field['type'] !== $this->type ) {
			return $is_editable;
		}

		return ! empty( $entry_fields[ $field['id'] ]['value'] );
	}

	/**
	 * Field display on the form front-end.
	 *
	 * @since 1.0.0
	 * @since 1.5.6 Added modern style uploader logic.
	 *
	 * @param array $field      Field data and settings.
	 * @param array $deprecated Deprecated field attributes. Use field properties.
	 * @param array $form_data  Form data and settings.
	 */
	public function field_display( $field, $deprecated, $form_data ) {

		// Define data.
		$primary = $field['properties']['inputs']['primary'];

		// Modern style.
		if ( self::is_modern_upload( $field ) ) {

			$strings         = $this->get_strings();
			$max_file_number = $this->get_max_file_number( $field );
			$input_name      = $this->get_input_name();
			$files           = $this->sanitize_modern_files_input();
			$value           = ! empty( $files ) ? wp_json_encode( $files ) : '';
			$count           = count( $files );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo wpforms_render(
				'fields/file-upload-frontend',
				[
					'field_id'        => $field['id'],
					'form_id'         => $form_data['id'],
					'value'           => $value,
					'input_name'      => $input_name,
					'required'        => $primary['required'],
					'extensions'      => $primary['data']['rule-extension'],
					'max_size'        => abs( $primary['data']['rule-maxsize'] ),
					'chunk_size'      => $this->get_chunk_size(),
					'max_file_number' => $max_file_number,
					'preview_hint'    => str_replace( self::TEMPLATE_MAXFILENUM, $max_file_number, $strings['preview_hint'] ),
					'post_max_size'   => wp_max_upload_size(),
					'is_full'         => ! empty( $value ) && $count >= $max_file_number,
				],
				true
			);

			return;
		}

		// Classic style.
		printf(
			'<input type="file" %s %s>',
			wpforms_html_attributes( $primary['id'], $primary['class'], $primary['data'], $primary['attr'] ),
			! empty( $primary['required'] ) ? 'required' : ''
		);
	}

	/**
	 * Input name.
	 *
	 * The input name is name in which the data is expected to be sent in from the client.
	 *
	 * @since 1.6.2
	 *
	 * @return string
	 */
	public function get_input_name() {

		return sprintf( 'wpforms_%d_%d', $this->form_id, $this->field_id );
	}

	/**
	 * Maximum size for a chunk in file uploads.
	 *
	 * @since 1.6.2
	 *
	 * @return int
	 */
	public function get_chunk_size() {

		return min(
			apply_filters( 'wpforms_file_upload_chunk_size', 2 * 1024 * 1024 ),
			wp_max_upload_size(),
			$this->max_file_size()
		);
	}

	/**
	 * Maximum chunk for slow connections.
	 *
	 * @since 1.6.2
	 *
	 * @return int Chunk size expected for slow connections.
	 */
	public function get_slow_connection_chunk_size() {

		return min(
			512 * 1024,
			wp_max_upload_size(),
			$this->max_file_size()
		);
	}

	/**
	 * Validate field for various errors on form submit.
	 *
	 * @since 1.0.0
	 * @since 1.5.6 Added modern style uploader logic.
	 *
	 * @param int   $field_id     Field ID.
	 * @param array $field_submit Submitted field value.
	 * @param array $form_data    Form data and settings.
	 */
	public function validate( $field_id, $field_submit, $form_data ) {

		$this->form_data  = (array) $form_data;
		$this->form_id    = absint( $this->form_data['id'] );
		$this->field_id   = absint( $field_id );
		$this->field_data = $this->form_data['fields'][ $this->field_id ];
		$input_name       = $this->get_input_name();
		$style            = ! empty( $this->field_data['style'] ) ? $this->field_data['style'] : self::STYLE_CLASSIC;

		// Add modern validate.
		if ( $style === self::STYLE_CLASSIC ) {
			$this->validate_classic( $input_name );
		} else {
			$this->validate_modern( $input_name );
		}
	}

	/**
	 * Validate classic file uploader field data.
	 *
	 * @since 1.5.6
	 * @since 1.7.2 The `$input_name` argument was deprecated.
	 *
	 * @param string $deprecated_input_name Input name inside the form on front-end.
	 */
	protected function validate_classic( $deprecated_input_name ) {

		if ( ! isset( get_defined_vars()['deprecated_input_name'] ) ) {
			_deprecated_argument( __METHOD__, '1.7.2 of the WPForms plugin', 'The `$input_name` argument was deprecated.' );
		}

		$input_name = $this->get_input_name();

		if ( empty( $_FILES[ $input_name ] ) ) {
			return;
		}

		/*
		 * If nothing is uploaded and it is not required, don't process.
		 */
		if ( $_FILES[ $input_name ]['error'] === 4 && ! $this->is_required() ) {
			return;
		}

		/*
		 * Basic file upload validation.
		 */
		$validated_basic = $this->validate_basic( (int) $_FILES[ $input_name ]['error'] );
		if ( ! empty( $validated_basic ) ) {
			wpforms()->get( 'process' )->errors[ $this->form_id ][ $this->field_id ] = $validated_basic;

			return;
		}

		/*
		 * Validate if file is required and provided.
		 */
		if (
			( empty( $_FILES[ $input_name ]['tmp_name'] ) || 4 === $_FILES[ $input_name ]['error'] ) &&
			$this->is_required()
		) {
			wpforms()->get( 'process' )->errors[ $this->form_id ][ $this->field_id ] = wpforms_get_required_label();

			return;
		}

		/*
		 * Validate file size.
		 */
		$file_size      = ! empty( $_FILES[ $input_name ]['size'] ) ? (int) $_FILES[ $input_name ]['size'] : 0;
		$validated_size = $this->validate_size( [ $file_size ] );

		if ( ! empty( $validated_size ) ) {
			wpforms()->get( 'process' )->errors[ $this->form_id ][ $this->field_id ] = $validated_size;

			return;
		}

		/*
		 * Validate file extension.
		 */
		$ext = strtolower( pathinfo( $_FILES[ $input_name ]['name'], PATHINFO_EXTENSION ) );

		$validated_ext = $this->validate_extension( $ext );

		if ( ! empty( $validated_ext ) ) {
			wpforms()->get( 'process' )->errors[ $this->form_id ][ $this->field_id ] = $validated_ext;

			return;
		}

		/*
		 * Validate file against what WordPress is set to allow.
		 * At the end of the day, if you try to upload a file that WordPress
		 * doesn't allow, we won't allow it either. Users can use a plugin to
		 * filter the allowed mime types in WordPress if this is an issue.
		 */
		$validated_filetype = $this->validate_wp_filetype_and_ext( $_FILES[ $input_name ]['tmp_name'], sanitize_file_name( wp_unslash( $_FILES[ $input_name ]['name'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( ! empty( $validated_filetype ) ) {
			wpforms()->get( 'process' )->errors[ $this->form_id ][ $this->field_id ] = $validated_filetype;

			return;
		}
	}

	/**
	 * Validate modern file uploader field data.
	 *
	 * @since 1.5.6
	 * @since 1.7.2 The `$input_name` argument was deprecated.
	 *
	 * @param string $deprecated_input_name Input name inside the form on front-end.
	 */
	protected function validate_modern( $deprecated_input_name ) {

		if ( ! isset( get_defined_vars()['deprecated_input_name'] ) ) {
			_deprecated_argument( __METHOD__, '1.7.2 of the WPForms plugin', 'The `$input_name` argument was deprecated.' );
		}

		$value = $this->sanitize_modern_files_input();

		if ( empty( $value ) && $this->is_required() ) {
			wpforms()->get( 'process' )->errors[ $this->form_id ][ $this->field_id ] = wpforms_get_required_label();

			return;
		}

		if ( ! empty( $value ) ) {
			$this->validate_modern_files( $value );
		}
	}

	/**
	 * Sanitize modern files input.
	 *
	 * @since 1.7.2
	 *
	 * @return array
	 */
	private function sanitize_modern_files_input() {

		$input_name = $this->get_input_name();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$json_value = isset( $_POST[ $input_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $input_name ] ) ) : '';
		$files      = json_decode( $json_value, true );

		if ( empty( $files ) || ! is_array( $files ) ) {
			return [];
		}

		return array_filter( array_map( [ $this, 'sanitize_modern_file' ], $files ) );
	}

	/**
	 * Sanitize modern file.
	 *
	 * @since 1.7.2
	 *
	 * @param array $file File information.
	 *
	 * @return array
	 */
	private function sanitize_modern_file( $file ) {

		if ( empty( $file['file'] ) || empty( $file['name'] ) ) {
			return [];
		}

		$sanitized_file = [];
		$rules          = [
			'name'           => 'sanitize_file_name',
			'file'           => 'sanitize_file_name',
			'url'            => 'esc_url_raw',
			'size'           => 'absint',
			'type'           => 'sanitize_text_field',
			'file_user_name' => 'sanitize_text_field',
		];

		foreach ( $rules as $rule => $callback ) {
			$file_attribute          = isset( $file[ $rule ] ) ? $file[ $rule ] : '';
			$sanitized_file[ $rule ] = $callback( $file_attribute );
		}

		return $sanitized_file;
	}

	/**
	 * Validate files for a modern file upload field.
	 *
	 * @since 1.7.1
	 *
	 * @param array $files List of uploaded files.
	 */
	private function validate_modern_files( $files ) {

		if ( ! $this->has_missing_tmp_file( $files ) ) {
			wpforms()->get( 'process' )->errors[ $this->form_id ][ $this->field_id ] = $this->validate_basic( 7 );

			return;
		}

		$max_file_number = $this->get_max_file_number( $this->field_data );

		if ( count( $files ) > $max_file_number ) {
			wpforms()->get( 'process' )->errors[ $this->form_id ][ $this->field_id ] = str_replace(
				'{fileLimit}',
				$max_file_number,
				wpforms_setting(
					'validation-maxfilenumber',
					sprintf( /* translators: %s - max number of files allowed. */
						esc_html__( 'File uploads exceed the maximum number allowed (%s).', 'wpforms' ),
						'{fileLimit}'
					)
				)
			);

			return;
		}

		foreach ( $files as $file ) {
			$path      = trailingslashit( $this->get_tmp_dir() ) . $file['file'];
			$file_size = filesize( $path );
			$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
			$errors    = wpforms_chain( [] )
				->array_merge( (array) $this->validate_size( [ $file_size ] ) )
				->array_merge( (array) $this->validate_extension( $extension ) )
				->array_merge( (array) $this->validate_wp_filetype_and_ext( $path, $file['name'] ) )
				->array_filter()
				->value();

			if ( ! empty( $errors ) ) {
				wpforms()->get( 'process' )->errors[ $this->form_id ][ $this->field_id ] = implode( ' ', $errors );

				return;
			}
		}
	}

	/**
	 * Check if file(s) exists in temp directory.
	 *
	 * @since 1.6.5
	 *
	 * @param array $files List of files.
	 *
	 * @return bool
	 */
	private function has_missing_tmp_file( $files ) {

		foreach ( $files as $file ) {
			if ( empty( $file['file'] ) || ! is_file( trailingslashit( $this->get_tmp_dir() ) . $file['file'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Getting max file number.
	 *
	 * @since 1.8.0
	 *
	 * @param array $field Field data.
	 *
	 * @return int
	 */
	private function get_max_file_number( $field ) {

		if ( empty( $field['max_file_number'] ) ) {
			return 1;
		}

		$max_file_number = absint( $field['max_file_number'] );

		if ( $max_file_number < 1 ) {
			return 1;
		}

		if ( $max_file_number > self::MAX_FILE_NUM ) {
			return self::MAX_FILE_NUM;
		}

		return $max_file_number;
	}

	/**
	 * Format and sanitize field.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $field_id     Field ID.
	 * @param array $field_submit Submitted field value.
	 * @param array $form_data    Form data and settings.
	 */
	public function format( $field_id, $field_submit, $form_data ) {

		$field_id    = absint( $field_id );
		$field_label = ! empty( $form_data['fields'][ $field_id ]['label'] ) ? sanitize_text_field( $form_data['fields'][ $field_id ]['label'] ) : '';
		$style       = ! empty( $form_data['fields'][ $field_id ]['style'] ) && $form_data['fields'][ $field_id ]['style'] === self::STYLE_MODERN
			? self::STYLE_MODERN
			: self::STYLE_CLASSIC;

		if ( $style === self::STYLE_CLASSIC ) {
			wpforms()->get( 'process' )->fields[ $field_id ] = [
				'name'          => $field_label,
				'value'         => '',
				'file'          => '',
				'file_original' => '',
				'ext'           => '',
				'id'            => $field_id,
				'type'          => $this->type,
			];

			return;
		}

		wpforms()->get( 'process' )->fields[ $field_id ] = [
			'name'      => $field_label,
			'value'     => '',
			'value_raw' => '',
			'id'        => $field_id,
			'type'      => $this->type,
			'style'     => self::STYLE_MODERN,
		];
	}

	/**
	 * Complete the upload process for all upload fields.
	 *
	 * @since 1.7.1
	 *
	 * @param array $fields    Fields data.
	 * @param array $entry     Submitted form entry.
	 * @param array $form_data Form data and settings.
	 *
	 * @return array
	 */
	public function upload_complete( $fields, $entry, $form_data ) {

		if ( ! empty( wpforms()->get( 'process' )->errors[ $form_data['id'] ] ) ) {
			return $fields;
		}

		$this->form_data = $form_data;

		foreach ( $fields as $field_id => $field ) {
			if ( empty( $field['type'] ) || $field['type'] !== $this->type ) {
				continue;
			}

			$this->form_id    = absint( $form_data['id'] );
			$this->field_id   = $field_id;
			$this->field_data = ! empty( $this->form_data['fields'][ $field_id ] )
				? $this->form_data['fields'][ $field_id ]
				: [];
			$is_visible       = ! isset( wpforms()->get( 'process' )->fields[ $field_id ]['visible'] ) || ! empty( wpforms()->get( 'process' )->fields[ $field_id ]['visible'] );

			$fields[ $field_id ]['visible'] = $is_visible;

			if ( ! $is_visible ) {
				continue;
			}

			$fields[ $field_id ] = self::is_modern_upload( $field )
				? $this->complete_upload_modern( $field )
				: $this->complete_upload_classic( $field );
		}

		return $fields;
	}

	/**
	 * Complete upload process for the classic upload field.
	 *
	 * @since 1.7.1
	 *
	 * @param array $processed_field Processed field data.
	 *
	 * @return array
	 */
	private function complete_upload_classic( $processed_field ) {

		$input_name = $this->get_input_name();
		$file       = ! empty( $_FILES[ $input_name ] ) ? $_FILES[ $input_name ] : false; // phpcs:ignore

		// If there was no file uploaded stop here before we continue with the upload process.
		if ( ! $file || $file['error'] !== 0 ) {
			return $processed_field;
		}

		$processed_file = $this->upload->process_file(
			$file,
			$this->field_id,
			$this->form_data,
			$this->is_media_integrated()
		);

		return array_merge(
			$processed_field,
			[
				'value'          => esc_url_raw( $processed_file['file_url'] ),
				'file'           => $processed_file['file_name_new'],
				'file_original'  => $processed_file['file_name'],
				'file_user_name' => sanitize_text_field( $file['name'] ),
				'ext'            => $processed_file['file_ext'],
				'attachment_id'  => absint( $processed_file['attachment_id'] ),
			]
		);
	}

	/**
	 * Complete upload process for the modern upload field.
	 *
	 * @since 1.7.1
	 *
	 * @param array $processed_field Processed field data.
	 *
	 * @return array
	 */
	private function complete_upload_modern( $processed_field ) {

		$files = $this->sanitize_modern_files_input();

		if ( empty( $files ) ) {
			return $processed_field;
		}

		wpforms_create_upload_dir_htaccess_file();

		$upload_dir = wpforms_upload_dir();

		if ( empty( $upload_dir['error'] ) ) {
			wpforms_create_index_html_file( $upload_dir['path'] );
		}

		$data = [];

		foreach ( $files as $file ) {
			$data[] = $this->process_file( $file );
		}

		$data                         = array_filter( $data );
		$processed_field['value_raw'] = $data;
		$processed_field['value']     = wpforms_chain( $data )
			->map(
				static function ( $file ) {

					return $file['value'];
				}
			)
			->implode( "\n" )
			->value();

		return $processed_field;
	}

	/**
	 * Generate a ready for DB data for each file.
	 *
	 * @since 1.5.6
	 *
	 * @param array $file File to generate data for.
	 *
	 * @return array
	 */
	protected function generate_file_data( $file ) {

		return [
			'name'           => sanitize_text_field( $file['file_name'] ),
			'value'          => esc_url_raw( $file['file_url'] ),
			'file'           => $file['file_name_new'],
			'file_original'  => $file['file_name'],
			'file_user_name' => sanitize_text_field( $file['file_user_name'] ),
			'ext'            => wpforms_chain( $file['file'] )->explode( '.' )->pop()->value(),
			'attachment_id'  => isset( $file['attachment_id'] ) ? absint( $file['attachment_id'] ) : 0,
			'id'             => $this->field_id,
			'type'           => $file['type'],
		];
	}

	/**
	 * Format, sanitize, and upload files for fields that have conditional logic rules applied.
	 *
	 * @since      1.3.8
	 * @deprecated 1.7.1.2
	 *
	 * @param array $form_data Form data and settings.
	 */
	public function format_conditional( $form_data ) {

		_deprecated_function( __METHOD__, '1.7.1.2 of the WPForms plugin' );

		// If the form contains no fields with conditional logic no need to
		// continue processing.
		if ( empty( $form_data['conditional_fields'] ) ) {
			return;
		}

		// Loop through each field that has conditional logic rules.
		foreach ( $form_data['conditional_fields'] as $key => $field_id ) {

			// Check if the field exists.
			if ( empty( wpforms()->get( 'process' )->fields[ $field_id ] ) ) {
				continue;
			}

			// Check if the 'type' exists.
			if ( empty( wpforms()->get( 'process' )->fields[ $field_id ]['type'] ) ) {
				continue;
			}

			// We are only concerned with file upload fields.
			if ( wpforms()->get( 'process' )->fields[ $field_id ]['type'] !== $this->type ) {
				continue;
			}

			// If the upload field was no visible at submit then ignore it.
			if ( empty( wpforms()->get( 'process' )->fields[ $field_id ]['visible'] ) ) {
				continue;
			}

			// If there are errors pertaining to this form, its not going to
			// process, so bail and avoid file upload.
			if ( ! empty( wpforms()->get( 'process' )->errors[ $form_data['id'] ] ) ) {
				continue;
			}

			/*
			 * We made it this far, so we can assume we have a file upload field
			 * which was visible during submit, inside a form which does not
			 * contain any errors, so at last we can proceed with uploading the
			 * file.
			 */

			// Unset this field from conditional fields so the format method will proceed.
			unset( $form_data['conditional_fields'][ $key ] );

			// Upload the file and celebrate.
			$this->format( $field_id, [], $form_data );
		}
	}

	/**
	 * Determine the max allowed file size in bytes as per field options.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of bytes allowed.
	 */
	public function max_file_size() {

		if ( ! empty( $this->field_data['max_size'] ) ) {

			// Strip any suffix provided (eg M, MB etc), which leaves us with the raw MB value.
			$max_size = preg_replace( '/[^0-9.]/', '', $this->field_data['max_size'] );

			return wpforms_size_to_bytes( $max_size . 'M' );
		}

		return wpforms_max_upload( true );
	}

	/**
	 * Clean up the tmp folder - remove all old files every day (filterable interval).
	 *
	 * @since 1.5.6
	 */
	protected function clean_tmp_files() {

		$files = glob( trailingslashit( $this->get_tmp_dir() ) . '*' );

		if ( ! is_array( $files ) || empty( $files ) ) {
			return;
		}

		$lifespan = (int) apply_filters( 'wpforms_field_' . $this->type . '_clean_tmp_files_lifespan', DAY_IN_SECONDS );

		foreach ( $files as $file ) {
			if ( $file === 'index.html' || ! is_file( $file ) ) {
				continue;
			}

			// In some cases filemtime() can return false, in that case - pretend this is a new file and do nothing.
			$modified = (int) filemtime( $file );

			if ( empty( $modified ) ) {
				$modified = time();
			}

			if ( ( time() - $modified ) >= $lifespan ) {
				@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/**
	 * Remove the file from the temporary directory.
	 *
	 * @since 1.5.6
	 */
	public function ajax_modern_remove() {

		$default_error = esc_html__( 'Something went wrong while removing the file.', 'wpforms' );

		$validated_form_field = $this->ajax_validate_form_field_modern();
		if ( empty( $validated_form_field ) ) {
			wp_send_json_error( $default_error, 400 );
		}

		if ( empty( $_POST['file'] ) ) {
			wp_send_json_error( $default_error, 403 );
		}

		$file     = sanitize_file_name( wp_unslash( $_POST['file'] ) );
		$tmp_path = wp_normalize_path( $this->get_tmp_dir() . '/' . $file );

		// Requested file does not exist, which is good.
		if ( ! is_file( $tmp_path ) ) {
			wp_send_json_success( $file );
		}

		if ( @unlink( $tmp_path ) ) {
			wp_send_json_success( $file );
		}

		wp_send_json_error( $default_error, 400 );
	}

	/**
	 * Upload the file, used during AJAX requests.
	 *
	 * @deprecated 1.6.2
	 *
	 * @since 1.5.6
	 */
	public function ajax_modern_upload() {

		$default_error = esc_html__( 'Something went wrong, please try again.', 'wpforms' );

		$validated_form_field = $this->ajax_validate_form_field_modern();
		if ( empty( $validated_form_field ) ) {
			wp_send_json_error( $default_error, 403 );
		}

		// Make sure we have required values from $_FILES.
		if ( empty( $_FILES['file']['name'] ) ) {
			wp_send_json_error( $default_error, 403 );
		}
		if ( empty( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( $default_error, 403 );
		}

		$error          = empty( $_FILES['file']['error'] ) ? 0 : (int) $_FILES['file']['error'];
		$name           = sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) );
		$file_user_name = sanitize_text_field( wp_unslash( $_FILES['file']['name'] ) );
		$path           = $_FILES['file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$extension      = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$errors         = wpforms_chain( [] )
			->array_merge( (array) $this->validate_basic( $error ) )
			->array_merge( (array) $this->validate_size() )
			->array_merge( (array) $this->validate_extension( $extension ) )
			->array_merge( (array) $this->validate_wp_filetype_and_ext( $path, $name ) )
			->array_filter()
			->value();

		if ( count( $errors ) ) {
			wp_send_json_error( implode( ',', $errors ), 400 );
		}

		$tmp_dir  = $this->get_tmp_dir();
		$tmp_name = $this->get_tmp_file_name( $extension );
		$tmp_path = wp_normalize_path( $tmp_dir . '/' . $tmp_name );
		$tmp      = $this->move_file( $path, $tmp_path );

		if ( ! $tmp ) {
			wp_send_json_error( $default_error, 400 );
		}

		$this->clean_tmp_files();

		wp_send_json_success(
			[
				'name'           => $name,
				'file'           => pathinfo( $tmp, PATHINFO_FILENAME ) . '.' . pathinfo( $tmp, PATHINFO_EXTENSION ),
				'file_user_name' => $file_user_name,
			]
		);
	}

	/**
	 * Initializes the chunk upload process.
	 *
	 * No data is being send by the client, they expecting an authorization from this method
	 * before sending any chunk.
	 *
	 * The server may return different configs to the uploader client (smaller chunks, disable
	 * parallel uploads etc).
	 *
	 * This method would validate the file extension, maximum size and other things.
	 *
	 * @since 1.6.2
	 */
	public function ajax_chunk_upload_init() {

		$default_error = esc_html__( 'Something went wrong, please try again.', 'wpforms' );

		$validated_form_field = $this->ajax_validate_form_field_modern();
		if ( empty( $validated_form_field ) ) {
			wp_send_json_error( $default_error );
		}

		$handler = Chunk::from_current_request( $this );
		if ( ! $handler || ! $handler->create_metadata() ) {
			wp_send_json_error( $default_error, 403 );
		}

		$error     = 0;
		$name      = sanitize_file_name( wp_unslash( $handler->get_file_name() ) );
		$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$errors    = wpforms_chain( [] )
			->array_merge( (array) $this->validate_basic( $error ) )
			->array_merge( (array) $this->validate_size( [ $handler->get_file_size() ] ) )
			->array_merge( (array) $this->validate_extension( $extension ) )
			->array_filter()
			->value();

		if ( count( $errors ) > 0 ) {
			wp_send_json_error( implode( ',', $errors ) );
		}

		wp_send_json(
			[
				'success' => true,
				'data'    => [
					'dzchunksize'          => $handler->get_chunk_size(),
					'parallelChunkUploads' => apply_filters( 'wpforms_file_upload_chunk_parallel', true ),
				],
			]
		);
	}

	/**
	 * Upload the files using chunks.
	 *
	 * @since 1.6.2
	 */
	public function ajax_chunk_upload() {

		$default_error = esc_html__( 'Something went wrong, please try again.', 'wpforms' );

		$validated_form_field = $this->ajax_validate_form_field_modern();
		if ( empty( $validated_form_field ) ) {
			wp_send_json_error( $default_error );
		}

		$handler = Chunk::from_current_request( $this );

		if ( ! $handler || ! $handler->load_metadata() ) {
			wp_send_json_error( $default_error, 403 );
		}

		if ( ! $handler->write() ) {
			wp_send_json_error( $default_error, 403 );
		}

		wp_send_json( [ 'success' => true ] );
	}

	/**
	 * Ajax handler for finalizing a chunked upload.
	 *
	 * @since 1.6.2
	 */
	public function ajax_chunk_upload_finalize() {

		$default_error = esc_html__( 'Something went wrong, please try again.', 'wpforms' );
		$handler       = Chunk::from_current_request( $this );

		if ( ! $handler || ! $handler->load_metadata() ) {
			wp_send_json_error( $default_error, 403 );
		}

		$file_name      = $handler->get_file_name();
		$file_user_name = $handler->get_file_user_name();
		$extension      = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		$tmp_dir        = $this->get_tmp_dir();
		$tmp_name       = $this->get_tmp_file_name( $extension );
		$tmp_path       = wp_normalize_path( $tmp_dir . '/' . $tmp_name );
		$file_new       = pathinfo( $tmp_path, PATHINFO_FILENAME ) . '.' . pathinfo( $tmp_path, PATHINFO_EXTENSION );

		if ( ! $handler->finalize( $tmp_path ) ) {
			wp_send_json_error( $default_error, 403 );
		}

		$is_valid_type = $this->validate_wp_filetype_and_ext( $tmp_path, $file_name );

		if ( $is_valid_type !== false ) {
			wp_send_json_error( $is_valid_type, 403 );
		}

		$this->clean_tmp_files();

		wp_send_json_success(
			[
				'name'           => $file_name,
				'file'           => $file_new,
				'url'            => $this->get_tmp_url() . '/' . $file_new,
				'size'           => filesize( $tmp_path ),
				'type'           => wp_check_filetype( $tmp_path )['type'],
				'file_user_name' => $file_user_name,
			]
		);
	}

	/**
	 * Validate form ID, field ID and field style for existence and that they are actually valid.
	 *
	 * @since 1.5.6
	 *
	 * @return array Empty array on any kind of failure.
	 */
	protected function ajax_validate_form_field_modern() {

		if (
			empty( $_POST['form_id'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Missing
			empty( $_POST['field_id'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		) {
			return [];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form_data = wpforms()->get( 'form' )->get( (int) $_POST['form_id'], [ 'content_only' => true ] );

		if ( empty( $form_data ) || ! is_array( $form_data ) ) {
			return [];
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$field_id = (int) $_POST['field_id'];

		if (
			! isset( $form_data['fields'][ $field_id ]['style'] ) ||
			$form_data['fields'][ $field_id ]['style'] !== self::STYLE_MODERN
		) {
			return [];
		}

		// Make data available everywhere in the class, so we don't need to pass it manually.
		$this->form_data  = $form_data;
		$this->form_id    = $this->form_data['id'];
		$this->field_id   = $field_id;
		$this->field_data = $this->form_data['fields'][ $this->field_id ];

		return [
			'form_data' => $form_data,
			'field_id'  => $field_id,
		];
	}

	/**
	 * Basic file upload validation.
	 *
	 * @since 1.5.6
	 *
	 * @param int $error Error ID provided by PHP.
	 *
	 * @return false|string False if no errors found, error text otherwise.
	 */
	protected function validate_basic( $error ) {

		if ( $error === 0 || $error === 4 ) {
			return false;
		}

		$errors = [
			false,
			esc_html__( 'The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'wpforms' ),
			esc_html__( 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.', 'wpforms' ),
			esc_html__( 'The uploaded file was only partially uploaded.', 'wpforms' ),
			esc_html__( 'No file was uploaded.', 'wpforms' ),
			'',
			esc_html__( 'Missing a temporary folder.', 'wpforms' ),
			esc_html__( 'Failed to write file to disk.', 'wpforms' ),
			esc_html__( 'File upload stopped by extension.', 'wpforms' ),
		];

		if ( array_key_exists( $error, $errors ) ) {
			return sprintf( /* translators: %s - error text. */
				esc_html__( 'File upload error. %s', 'wpforms' ),
				$errors[ $error ]
			);
		}

		return false;
	}

	/**
	 * Generate both the file info and the file data to send to the database.
	 *
	 * @since 1.7.0
	 *
	 * @param array $file File to generate data from.
	 *
	 * @return array File data.
	 */
	public function process_file( $file ) {

		$file['tmp_name'] = trailingslashit( $this->get_tmp_dir() ) . $file['file'];
		$file['type']     = 'application/octet-stream';

		if ( is_file( $file['tmp_name'] ) ) {
			$filetype     = wp_check_filetype( $file['tmp_name'] );
			$file['type'] = $filetype['type'];
			$file['size'] = filesize( $file['tmp_name'] );
		}

		$uploaded_file = $this->upload->process_file(
			$file,
			$this->field_id,
			$this->form_data,
			$this->is_media_integrated()
		);

		if ( empty( $uploaded_file ) ) {
			return [];
		}

		$uploaded_file['file']           = $file['file'];
		$uploaded_file['file_user_name'] = $file['file_user_name'];
		$uploaded_file['type']           = $file['type'];

		return $this->generate_file_data( $uploaded_file );
	}

	/**
	 * Validate file size.
	 *
	 * @since 1.5.6
	 *
	 * @param array $sizes Array with all file sizes in bytes.
	 *
	 * @return false|string False if no errors found, error text otherwise.
	 */
	protected function validate_size( $sizes = null ) {

		if (
			$sizes === null &&
			! empty( $_FILES )
		) {
			$sizes = [];

			foreach ( $_FILES as $file ) {
				$sizes[] = $file['size'];
			}
		}

		if ( ! is_array( $sizes ) ) {
			return false;
		}

		$max_size = min( wp_max_upload_size(), $this->max_file_size() );

		foreach ( $sizes as $size ) {
			if ( $size > $max_size ) {
				return sprintf( /* translators: $s - allowed file size in MB. */
					esc_html__( 'File exceeds max size allowed (%s).', 'wpforms' ),
					size_format( $max_size )
				);
			}
		}

		return false;
	}

	/**
	 * Validate extension against denylist and admin-provided list.
	 * There are certain extensions we do not allow under any circumstances,
	 * with no exceptions, for security purposes.
	 *
	 * @since 1.5.6
	 *
	 * @param string $ext Extension.
	 *
	 * @return false|string False if no errors found, error text otherwise.
	 */
	protected function validate_extension( $ext ) {

		// Make sure file has an extension first.
		if ( empty( $ext ) ) {
			return esc_html__( 'File must have an extension.', 'wpforms' );
		}

		// Validate extension against all allowed values.
		if ( ! in_array( $ext, $this->get_extensions(), true ) ) {
			return esc_html__( 'File type is not allowed.', 'wpforms' );
		}

		return false;
	}

	/**
	 * Validate file against what WordPress is set to allow.
	 * At the end of the day, if you try to upload a file that WordPress
	 * doesn't allow, we won't allow it either. Users can use a plugin to
	 * filter the allowed mime types in WordPress if this is an issue.
	 *
	 * @since 1.5.6
	 *
	 * @param string $path Path to a newly uploaded file.
	 * @param string $name Name of a newly uploaded file.
	 *
	 * @return false|string False if no errors found, error text otherwise.
	 */
	protected function validate_wp_filetype_and_ext( $path, $name ) {

		$wp_filetype = wp_check_filetype_and_ext( $path, $name );

		$ext             = empty( $wp_filetype['ext'] ) ? '' : $wp_filetype['ext'];
		$type            = empty( $wp_filetype['type'] ) ? '' : $wp_filetype['type'];
		$proper_filename = empty( $wp_filetype['proper_filename'] ) ? '' : $wp_filetype['proper_filename'];

		if ( $proper_filename || ! $ext || ! $type ) {
			return esc_html__( 'File type is not allowed.', 'wpforms' );
		}

		return false;
	}

	/**
	 * Get form-specific uploads directory path and URL.
	 *
	 * @since 1.5.6
	 *
	 * @return array
	 */
	protected function get_form_files_dir() {

		$upload_dir = wpforms_upload_dir();
		$folder     = absint( $this->form_data['id'] ) . '-' . wp_hash( $this->form_data['created'] . $this->form_data['id'] );

		return [
			'path' => trailingslashit( $upload_dir['path'] ) . $folder,
			'url'  => trailingslashit( $upload_dir['url'] ) . $folder,
		];
	}

	/**
	 * Get tmp dir for files.
	 *
	 * @since 1.5.6
	 *
	 * @return string
	 */
	public function get_tmp_dir() {

		$upload_dir = wpforms_upload_dir();
		$tmp_root   = $upload_dir['path'] . '/tmp';

		if ( ! file_exists( $tmp_root ) || ! wp_is_writable( $tmp_root ) ) {
			wp_mkdir_p( $tmp_root );
		}

		// Check if the index.html exists in the directory, if not - create it.
		wpforms_create_index_html_file( $tmp_root );

		return $tmp_root;
	}

	/**
	 * Get tmp url for files.
	 *
	 * @since 1.7.1
	 *
	 * @return string
	 */
	private function get_tmp_url() {

		$upload_dir = wpforms_upload_dir();

		return $upload_dir['url'] . '/tmp';
	}

	/**
	 * Create both the directory and index.html file in it if any of them doesn't exist.
	 *
	 * @since 1.5.6
	 *
	 * @param string $path Path to the directory.
	 *
	 * @return string Path to the newly created directory.
	 */
	protected function create_dir( $path ) {

		if ( ! file_exists( $path ) ) {
			wp_mkdir_p( $path );
		}

		// Check if the index.html exists in the path, if not - create it.
		wpforms_create_index_html_file( $path );

		return $path;
	}

	/**
	 * Get tmp file name.
	 *
	 * @since 1.5.6
	 *
	 * @param string $extension File extension.
	 *
	 * @return string
	 */
	protected function get_tmp_file_name( $extension ) {

		return wp_hash( wp_rand() . microtime() . $this->form_id . $this->field_id ) . '.' . $extension;
	}

	/**
	 * Move file to a permanent location.
	 *
	 * @since 1.5.6
	 *
	 * @param string $path_from From.
	 * @param string $path_to   To.
	 *
	 * @return false|string False on error.
	 */
	protected function move_file( $path_from, $path_to ) {

		$this->create_dir( dirname( $path_to ) );

		if ( false === move_uploaded_file( $path_from, $path_to ) ) {
			wpforms_log(
				'Upload Error, could not upload file',
				$path_from,
				[
					'type' => [ 'entry', 'error' ],
				]
			);

			return false;
		}

		$this->upload->set_file_fs_permissions( $path_to );

		return $path_to;
	}

	/**
	 * Get all allowed extensions.
	 * Check against user-entered extensions.
	 *
	 * @since 1.5.6
	 *
	 * @return array
	 */
	protected function get_extensions() {

		// Allowed file extensions by default.
		$default_extensions = $this->get_default_extensions();

		// Allowed file extensions.
		$extensions = ! empty( $this->field_data['extensions'] ) ? explode( ',', $this->field_data['extensions'] ) : $default_extensions;

		return wpforms_chain( $extensions )
			->map(
				static function ( $ext ) {

					return strtolower( preg_replace( '/[^A-Za-z0-9_-]/', '', $ext ) );
				}
			)
			->array_filter()
			->array_intersect( $default_extensions )
			->value();
	}

	/**
	 * Get default extensions supported by WordPress
	 * without those that we manually denylist.
	 *
	 * @since 1.5.6
	 *
	 * @return array
	 */
	protected function get_default_extensions() {

		return wpforms_chain( get_allowed_mime_types() )
			->array_keys()
			->implode( '|' )
			->explode( '|' )
			->array_diff( $this->denylist )
			->value();
	}

	/**
	 * Whether field is required or not.
	 *
	 * @uses $this->field_data
	 *
	 * @since 1.5.6
	 *
	 * @return bool
	 */
	protected function is_required() {

		return ! empty( $this->field_data['required'] );
	}

	/**
	 * Whether field is integrated with WordPress Media Library.
	 *
	 * @uses $this->field_data
	 *
	 * @since 1.5.6
	 *
	 * @return bool
	 */
	protected function is_media_integrated() {

		return ! empty( $this->field_data['media_library'] ) && '1' === $this->field_data['media_library'];
	}

	/**
	 * Disallow WPForms upload directory indexing in robots.txt.
	 *
	 * @since 1.6.1
	 * @deprecated 1.7.0
	 *
	 * @param string $output Robots.txt output.
	 *
	 * @return string
	 */
	public function disallow_upload_dir_indexing( $output ) {

		_deprecated_function( __METHOD__, '1.7.0 of the WPForms plugin' );

		return ( new Robots() )->disallow_upload_dir_indexing( $output );
	}

	/**
	 * Get file icon html.
	 *
	 * @since 1.6.6
	 *
	 * @param array $file_data File data.
	 *
	 * @return string
	 */
	public function file_icon_html( $file_data ) {

		$src       = esc_url( $file_data['value'] );
		$ext_types = wp_get_ext_types();

		if ( ! in_array( $file_data['ext'], $ext_types['image'], true ) ) {

			$src = wp_mime_type_icon( wp_ext2type( $file_data['ext'] ) );
		} elseif ( $file_data['attachment_id'] ) {

			$image = wp_get_attachment_image_src( $file_data['attachment_id'], [ 16, 16 ], true );
			$src   = $image ? $image[0] : $src;
		}

		return sprintf( '<span class="file-icon"><img width="16" height="16" src="%s" alt="" /></span>', esc_url( $src ) );
	}

	/**
	 * Get Form files path.
	 *
	 * @since 1.6.6
	 *
	 * @param string $form_id Form ID.
	 *
	 * @return string
	 */
	private static function get_form_files_path( $form_id ) {

		$form_data  = wpforms()->get( 'form' )->get( $form_id );
		$upload_dir = wpforms_upload_dir();

		return trailingslashit( $upload_dir['path'] ) . ( new Upload() )->get_form_directory( $form_data->ID, $form_data->post_date );
	}

	/**
	 * Fallback method to get Form files path for already existing uploads with incorrectly generated hashes (files uploaded before version 1.7.6 ).
	 *
	 * @since 1.7.6
	 *
	 * @param string $form_id Form ID.
	 *
	 * @return string
	 */
	private static function get_form_files_path_backward_fallback( $form_id ) {

		$form_data  = wpforms()->get( 'form' )->get( $form_id );
		$upload_dir = wpforms_upload_dir();

		return trailingslashit( $upload_dir['path'] ) . absint( $form_data->ID ) . '-' . md5( $form_data->post_date . $form_data->ID );
	}

	/**
	 * Maybe delete uploaded files from entry.
	 *
	 * @since 1.6.6
	 *
	 * @param string $entry_id       Entry ID.
	 * @param array  $delete_fields  Fields to delete.
	 * @param array  $exclude_fields Exclude fields.
	 *
	 * @return array Removed files names.
	 */
	public static function delete_uploaded_files_from_entry( $entry_id, $delete_fields = [], $exclude_fields = [] ) {

		$removed_files = [];
		$entry         = wpforms()->get( 'entry' )->get( $entry_id );

		if ( empty( $entry ) ) {
			return $removed_files;
		}

		$files_path = self::get_form_files_path( $entry->form_id );

		if ( ! is_dir( $files_path ) ) {
			$files_path = self::get_form_files_path_backward_fallback( $entry->form_id );
		}

		$fields_to_delete = $delete_fields ? $delete_fields : (array) wpforms_decode( $entry->fields );

		foreach ( $fields_to_delete as $field ) {


			if ( ! isset( $field['type'] ) || $field['type'] !== 'file-upload' || ( $exclude_fields && ! isset( $exclude_fields[ $field['id'] ] ) ) ) {
				continue;
			}

			$removed_files = self::delete_uploaded_file_from_entry( $removed_files, $field, $exclude_fields, $files_path, $entry );
		}

		return $removed_files;
	}

	/**
	 * Maybe delete uploaded file from entry.
	 *
	 * @since 1.6.6
	 * @since 1.8.5 Added $entry argument.
	 *
	 * @param array  $removed_files  Removed files array.
	 * @param array  $field          Field to delete.
	 * @param array  $exclude_fields Exclude fields.
	 * @param string $files_path     Form files path.
	 * @param object $entry          Entry.
	 *
	 * @return array
	 */
	private static function delete_uploaded_file_from_entry( $removed_files, $field, $exclude_fields, $files_path, $entry ) {

		if ( ! self::is_modern_upload( $field ) ) {

			$removed_files[] = self::delete_uploaded_file( $files_path, $field, $entry );

			return $removed_files;
		}
		$values = $field['value_raw'];

		if ( $exclude_fields ) {
			$values = ! empty( $field['value_raw'] ) ? array_diff_key( $exclude_fields[ $field['id'] ]['value_raw'], $field['value_raw'] ) : $exclude_fields[ $field['id'] ]['value_raw'];
		}

		if ( empty( $values ) ) {
			return $removed_files;
		}

		foreach ( $values as $value_raw ) {
			$removed_files[] = self::delete_uploaded_file( $files_path, $value_raw, $entry );
		}

		return $removed_files;
	}

	/**
	 * Delete uploaded file.
	 *
	 * @since 1.6.6
	 * @since 1.8.5 Add $entry argument and delete files from Media Library for spam entries.
	 *
	 * @param string $files_path Path to files.
	 * @param array  $file_data  File data.
	 * @param object $entry      Entry.
	 *
	 * @return string
	 */
	private static function delete_uploaded_file( $files_path, $file_data, $entry ) {

		if ( empty( $file_data['file'] ) ) {
			return '';
		}

		// We delete attachments from Media Library only for spam entries.
		if ( $entry->status === 'spam' && ! empty( $file_data['attachment_id'] ) ) {
			wp_delete_attachment( $file_data['attachment_id'], true );

			return $file_data['file_user_name'];
		}

		$file = trailingslashit( $files_path ) . $file_data['file'];

		if ( ! is_file( $file ) ) {
			return '';
		}

		unlink( $file );

		return $file_data['file_user_name'];
	}

	/**
	 * Check if modern upload was used.
	 *
	 * @param array $field_data Field data.
	 *
	 * @since 1.6.6
	 *
	 * @return bool
	 */
	public static function is_modern_upload( $field_data ) {

		return isset( $field_data['style'] ) && $field_data['style'] === self::STYLE_MODERN;
	}

	/**
	 * Returns an array containing the file paths of the files uploading in a file upload entry.
	 *
	 * @since 1.7.8
	 *
	 * @param string $form_id     Form ID.
	 * @param array  $entry_field Entry field data.
	 *
	 * @return array The file path of the uploaded file. Returns an empty string if the file path isn't fetched.
	 */
	public static function get_entry_field_file_paths( $form_id, $entry_field ) {

		$form_file_path = self::get_form_files_path( $form_id );
		$files          = [];

		if ( self::is_modern_upload( $entry_field ) ) {

			foreach ( $entry_field['value_raw'] as $value ) {
				$file_path = self::get_file_path( $value['attachment_id'], $value['file'], $form_file_path );

				if ( empty( $file_path ) ) {
					continue;
				}

				$files[] = $file_path;
			}
		} else {
			$files[] = self::get_file_path( $entry_field['attachment_id'], $entry_field['file'], $form_file_path );
		}

		return $files;
	}

	/**
	 * Returns the file path of a given attachment ID or file name.
	 *
	 * @since 1.7.8
	 *
	 * @param int    $attachment_id  Attachment ID.
	 * @param string $file_name      File name.
	 * @param string $file_base_path The base path of uploaded files.
	 *
	 * @return string
	 */
	private static function get_file_path( $attachment_id, $file_name, $file_base_path ) {

		$file_path = empty( $attachment_id ) ? trailingslashit( $file_base_path ) . $file_name : get_attached_file( $attachment_id );

		return ( empty( $file_path ) || ! is_file( $file_path ) ) ? '' : $file_path;
	}
}

new WPForms_Field_File_Upload();
