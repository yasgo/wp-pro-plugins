<?php

namespace WPForms\Pro\Admin\Entries;

use WPForms\Pro\Forms\Fields\Base\EntriesEdit;

/**
 * Single entry edit function.
 *
 * @since 1.6.0
 */
class Edit {

	/**
	 * Abort. Bail on proceeding to process the page.
	 *
	 * @since 1.6.0
	 *
	 * @var bool
	 */
	public $abort = false;

	/**
	 * The human-readable error message.
	 *
	 * @since 1.7.3
	 *
	 * @var string
	 */
	private $abort_message;

	/**
	 * Form object.
	 *
	 * @since 1.6.0
	 *
	 * @var \WP_Post
	 */
	public $form;

	/**
	 * Form ID.
	 *
	 * @since 1.6.0
	 *
	 * @var integer
	 */
	public $form_id;

	/**
	 * Decoded Form Data.
	 *
	 * @since 1.6.0
	 *
	 * @var array
	 */
	public $form_data;

	/**
	 * Entry object.
	 *
	 * @since 1.6.0
	 *
	 * @var object
	 */
	public $entry;

	/**
	 * Entry ID.
	 *
	 * @since 1.6.0
	 *
	 * @var int
	 */
	public $entry_id;

	/**
	 * Decoded Entry Fields array.
	 *
	 * @since 1.6.0
	 *
	 * @var array
	 */
	public $entry_fields;

	/**
	 * Processing Fields array.
	 *
	 * @since 1.6.0
	 *
	 * @var array
	 */
	public $fields;

	/**
	 * Modified datetime holder.
	 *
	 * @since 1.6.0
	 *
	 * @var string
	 */
	public $date_modified;

	/**
	 * Processing errors.
	 *
	 * @since 1.6.0
	 *
	 * @var array
	 */
	public $errors;

	/**
	 * Determine if the user is viewing the entry edit page, if so, party on.
	 *
	 * @since 1.6.0
	 */
	public function init() {

		if ( $this->is_admin_entry_editing_ajax() ) {
			$entry_id = isset( $_POST['wpforms']['entry_id'] ) ? absint( wp_unslash( $_POST['wpforms']['entry_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		} else {
			$entry_id = isset( $_GET['entry_id'] ) ? absint( wp_unslash( $_GET['entry_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		}

		// Check permissions and other constraints.
		if ( ! is_admin() || ! wpforms_current_user_can( 'edit_entry_single', $entry_id ) ) {
			return;
		}

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.6.0
	 */
	private function hooks() {

		add_action( 'delete_attachment', [ $this, 'maybe_remove_attachment_data_from_entries' ] );

		if ( $this->is_admin_entry_editing_ajax() ) {

			remove_action( 'wp_ajax_wpforms_submit', [ wpforms()->get( 'process' ), 'ajax_submit' ] );
			// Submit action AJAX endpoint.
			add_action( 'wp_ajax_wpforms_submit', [ $this, 'ajax_submit' ] );

			return;
		}

		// Check view entry page.
		if ( wpforms_is_admin_page( 'entries', 'details' ) ) {

			add_action( 'wpforms_entry_details_sidebar_details_action', [ $this, 'display_edit_button' ], 10, 2 );
		}

		// Check edit entry page.
		if ( ! wpforms_is_admin_page( 'entries', 'edit' ) ) {
			return;
		}

		$entry_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		if ( empty( $entry_id ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpforms-entries' ) );
			exit;
		}

		// Entry processing and setup.
		add_action( 'wpforms_entries_init', [ $this, 'setup' ] );

		do_action( 'wpforms_entries_init', 'edit' );

		// Instance of `\WPForms_Entries_Single` class.
		$entries_single = new \WPForms_Entries_Single();

		// Display Empty State screen.
		add_action( 'wpforms_admin_page', [ $this, 'display_abort_message' ] );

		// Output. Entry edit page.
		add_action( 'wpforms_admin_page', [ $this, 'display_edit_page' ] );

		// Entry edit form.
		add_action( 'wpforms_pro_admin_entries_edit_content', [ $this, 'display_edit_form' ], 10, 2 );

		// Reuse Debug metabox from `\WPForms_Entries_Single` class.
		add_action( 'wpforms_pro_admin_entries_edit_content', [ $entries_single, 'details_debug' ], 50, 2 );

		// Update button.
		add_action( 'wpforms_entry_details_sidebar_details_action', [ $this, 'update_button' ], 10, 2 );

		// Reuse Details metabox from `\WPForms_Entries_Single` class.
		add_action( 'wpforms_pro_admin_entries_edit_sidebar', [ $entries_single, 'details_meta' ], 10, 2 );

		// Remove Screen Options tab from admin area header.
		add_filter( 'screen_options_show_screen', '__return_false' );

		// Enqueues.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueues' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'before_enqueue_media' ], 0 );

		// Hook for add-ons.
		do_action( 'wpforms_pro_admin_entries_edit_init', $this );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 1.6.0
	 */
	public function enqueues() {

		if ( ! empty( $this->abort ) ) {
			return;
		}

		$this->enqueue_styles();
		$this->enqueue_scripts();

		if ( empty( $this->form_data['fields'] ) || ! is_array( $this->form_data['fields'] ) ) {
			return;
		}

		// Get a list of unique field types used in a form.
		$field_types = array_filter( wp_list_pluck( $this->form_data['fields'], 'type' ) );

		foreach ( $field_types as $field_type ) {
			$obj = $this->get_entries_edit_field_object( $field_type );

			$obj->enqueues();
		}
	}

	/**
	 * Initialize Rich Text field related settings before calling wp_enqueue_media().
	 *
	 * @since 1.7.0
	 */
	public function before_enqueue_media() {

		( new \WPForms_Field_Richtext( false ) )->edit_entry_before_enqueues();
	}

	/**
	 * Enqueue styles.
	 *
	 * @since 1.6.0
	 */
	public function enqueue_styles() {

		wp_enqueue_media();

		$min = wpforms_get_min_suffix();

		// Frontend form base styles.
		wp_enqueue_style(
			'wpforms-base',
			WPFORMS_PLUGIN_URL . 'assets/css/frontend/classic/wpforms-base.css',
			[],
			WPFORMS_VERSION
		);

		// Entry Edit styles.
		wp_enqueue_style(
			'wpforms-entry-edit',
			WPFORMS_PLUGIN_URL . "assets/pro/css/entry-edit{$min}.css",
			[],
			WPFORMS_VERSION
		);
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since 1.6.0
	 */
	public function enqueue_scripts() {

		$min = wpforms_get_min_suffix();

		if ( wpforms_has_field_setting( 'input_mask', $this->form, true ) ) {
			// Load jQuery input mask library - https://github.com/RobinHerbots/jquery.inputmask.
			wp_enqueue_script(
				'wpforms-maskedinput',
				WPFORMS_PLUGIN_URL . 'assets/lib/jquery.inputmask.min.js',
				[ 'jquery' ],
				'5.0.7-beta.29',
				true
			);
		}

		// Load admin utils JS.
		wp_enqueue_script(
			'wpforms-admin-utils',
			WPFORMS_PLUGIN_URL . "assets/js/admin-utils{$min}.js",
			[ 'jquery' ],
			WPFORMS_VERSION,
			true
		);

		wp_enqueue_script(
			'wpforms-punycode',
			WPFORMS_PLUGIN_URL . 'assets/lib/punycode.min.js',
			[],
			'1.0.0',
			true
		);

		if ( wpforms_has_field_type( 'richtext', $this->form ) ) {
			wp_enqueue_script(
				'wpforms-richtext-field',
				WPFORMS_PLUGIN_URL . "assets/pro/js/fields/richtext{$min}.js",
				[ 'jquery' ],
				WPFORMS_VERSION,
				true
			);
		}

		wp_enqueue_script(
			'wpforms-generic-utils',
			WPFORMS_PLUGIN_URL . "assets/js/utils{$min}.js",
			[ 'jquery' ],
			WPFORMS_VERSION,
			true
		);

		// Load frontend base JS.
		wp_enqueue_script(
			'wpforms-frontend',
			WPFORMS_PLUGIN_URL . "assets/js/wpforms{$min}.js",
			[ 'jquery' ],
			WPFORMS_VERSION,
			true
		);

		// Load admin JS.
		wp_enqueue_script(
			'wpforms-admin-edit-entry',
			WPFORMS_PLUGIN_URL . "assets/pro/js/admin/edit-entry{$min}.js",
			[ 'jquery' ],
			WPFORMS_VERSION,
			true
		);

		// Localize frontend strings.
		wp_localize_script(
			'wpforms-frontend',
			'wpforms_settings',
			wpforms()->get( 'frontend' )->get_strings()
		);

		// Localize edit entry strings.
		wp_localize_script(
			'wpforms-admin-edit-entry',
			'wpforms_admin_edit_entry',
			$this->get_localized_data()
		);
	}

	/**
	 * Get localized data.
	 *
	 * @since 1.6.0
	 */
	private function get_localized_data() {

		$data['strings'] = [
			'update'            => esc_html__( 'Update', 'wpforms' ),
			'success'           => esc_html__( 'Success', 'wpforms' ),
			'continue_editing'  => esc_html__( 'Continue Editing', 'wpforms' ),
			'view_entry'        => esc_html__( 'View Entry', 'wpforms' ),
			'msg_saved'         => esc_html__( 'The entry was successfully saved.', 'wpforms' ),
			'entry_delete_file' => sprintf( /* translators: %s - file name. */
				esc_html__( 'Are you sure you want to permanently delete the file "%s"?', 'wpforms' ),
				'{file_name}'
			),
			'entry_empty_file'  => esc_html__( 'Empty', 'wpforms' ),
		];

		// View Entry URL.
		$data['strings']['view_entry_url'] = add_query_arg(
			[
				'page'     => 'wpforms-entries',
				'view'     => 'details',
				'entry_id' => $this->entry_id,
			],
			admin_url( 'admin.php' )
		);

		return $data;
	}

	/**
	 * Setup entry edit page data.
	 *
	 * This function does the error checking and variable setup.
	 *
	 * @since 1.6.0
	 */
	public function setup() {

		// Find the entry.
		// phpcs:ignore WordPress.Security.NonceVerification
		$entry = wpforms()->get( 'entry' )->get( (int) $_GET['entry_id'] );

		// If entry exists.
		if ( ! empty( $entry ) ) {
			// Find the form information.
			$form = wpforms()->get( 'form' )->get( $entry->form_id, [ 'cap' => 'edit_entries_form_single' ] );
		}

		// No entry was found, no form was found, the Form is in the Trash.
		if ( empty( $entry ) || empty( $form ) || $form->post_status === 'trash' ) {
			$this->abort_message = esc_html__( 'It looks like the entry you are trying to access is no longer available.', 'wpforms' );
			$this->abort         = true;

			return;
		}

		// Check if entry has trash status.
		if ( $entry->status === 'trash' ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$this->abort_message = esc_html__( 'You can\'t edit this entry because it\'s in the trash.', 'wpforms' );
			$this->abort         = true;

			return;
		}

		// No editable fields, redirect back.
		if ( ! wpforms()->get( 'entry' )->has_editable_fields( $entry ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$entry_list = add_query_arg(
				[
					'page'    => 'wpforms-entries',
					'view'    => 'list',
					'form_id' => $entry->form_id,
				],
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : $entry_list );
			exit;
		}

		// Form data.
		$form_data              = wpforms_decode( $form->post_content );
		$form->form_entries_url = add_query_arg(
			[
				'page'    => 'wpforms-entries',
				'view'    => 'list',
				'form_id' => absint( $form_data['id'] ),
			],
			admin_url( 'admin.php' )
		);

		// Make public.
		$this->entry        = $entry;
		$this->entry_id     = $entry->entry_id;
		$this->entry_fields = apply_filters( 'wpforms_entry_single_data', wpforms_decode( $entry->fields ), $entry, $form_data );
		$this->form         = $form;
		$this->form_data    = $form_data;
		$this->form_id      = $form->ID;

		// Lastly, mark entry as read if needed.
		if ( $entry->viewed !== '1' && empty( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$is_success = wpforms()->get( 'entry' )->update(
				$entry->entry_id,
				[
					'viewed' => '1',
				]
			);
		}

		if ( ! empty( $is_success ) ) {

			$this->add_entry_meta( esc_html__( 'Entry read.', 'wpforms' ) );

			$this->entry->viewed     = '1';
			$this->entry->entry_logs = wpforms()->get( 'entry_meta' )->get_meta(
				[
					'entry_id' => $entry->entry_id,
					'type'     => 'log',
				]
			);
		}

		do_action( 'wpforms_pro_admin_entries_edit_setup', $this );
	}

	/**
	 * Edit button in Entry Details metabox on the single entry view page.
	 *
	 * @since 1.6.0
	 *
	 * @param object $entry     Submitted entry values.
	 * @param array  $form_data Form data and settings.
	 */
	public function display_edit_button( $entry, $form_data ) {

		if ( ! isset( $form_data['id'] ) || ! isset( $entry->entry_id ) ) {
			return;
		}

		if ( ! wpforms_current_user_can( 'edit_entries_form_single', $form_data['id'] ) || ! wpforms()->get( 'entry' )->has_editable_fields( $entry ) ) {
			return;
		}

		// Edit Entry URL.
		$edit_url = add_query_arg(
			[
				'page'     => 'wpforms-entries',
				'view'     => 'edit',
				'entry_id' => $entry->entry_id,
			],
			admin_url( 'admin.php' )
		);

		printf(
			'<div id="publishing-action">
				<a href="%s" class="button button-primary button-large">%s</a>
			</div>',
			esc_url( $edit_url ),
			esc_html__( 'Edit', 'wpforms' )
		);
	}

	/**
	 * Entry Edit page.
	 *
	 * @since 1.6.0
	 */
	public function display_edit_page() {

		if ( $this->abort ) {
			return;
		}

		$entry          = $this->entry;
		$form_data      = $this->form_data;
		$form_id        = ! empty( $form_data['id'] ) ? (int) $form_data['id'] : 0;
		$view_entry_url = add_query_arg(
			[
				'page'     => 'wpforms-entries',
				'view'     => 'details',
				'entry_id' => $entry->entry_id,
			],
			admin_url( 'admin.php' )
		);

		$form_atts = [
			'id'    => 'wpforms-edit-entry-form',
			'class' => [ 'wpforms-form', 'wpforms-validate' ],
			'data'  => [
				'formid' => $form_id,
			],
			'atts'  => [
				'method'  => 'POST',
				'enctype' => 'multipart/form-data',
				'action'  => esc_url_raw( remove_query_arg( 'wpforms' ) ),
			],
		];
		?>

		<div id="wpforms-entries-single" class="wrap wpforms-admin-wrap wpforms-entries-single-edit">

			<h1 class="page-title">
				<?php esc_html_e( 'Edit Entry', 'wpforms' ); ?>
				<a href="<?php echo esc_url( $view_entry_url ); ?>" class="page-title-action wpforms-btn wpforms-btn-orange" data-action="back">
					<span class="page-title-action-text"><?php esc_html_e( 'Back to Entry', 'wpforms' ); ?></span>
				</a>
			</h1>

			<div class="wpforms-admin-content">

				<div id="poststuff">

					<div id="post-body" class="metabox-holder columns-2">

						<?php
						printf( '<div class="wpforms-container wpforms-edit-entry-container" id="wpforms-%d">', (int) $form_id );
						echo '<form ' . wpforms_html_attributes( $form_atts['id'], $form_atts['class'], $form_atts['data'], $form_atts['atts'] ) . '>';
						?>

							<!-- Left column -->
							<div id="post-body-content" style="position: relative;">
								<?php do_action( 'wpforms_pro_admin_entries_edit_content', $entry, $form_data, $this ); ?>
							</div>

							<!-- Right column -->
							<div id="postbox-container-1" class="postbox-container">
								<?php do_action( 'wpforms_pro_admin_entries_edit_sidebar', $entry, $form_data, $this ); ?>
							</div>

						</form>
						</div>

					</div>

				</div>

			</div>

		</div>
		<?php
	}

	/**
	 * Display abort message using empty state page.
	 *
	 * @since 1.7.3
	 */
	public function display_abort_message() {

		if ( ! $this->abort ) {
			return;
		}

		?>
		<div id="wpforms-entries-single" class="wrap wpforms-admin-wrap">

			<h1 class="page-title">
				<?php esc_html_e( 'Edit Entry', 'wpforms' ); ?>
			</h1>
			<div class="wpforms-admin-content">
				<?php
				// Output empty state screen.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wpforms_render(
					'admin/empty-states/no-entry',
					[
						'message' => $this->abort_message,
					],
					true
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Edit entry form metabox.
	 *
	 * @since 1.6.0
	 *
	 * @param object $entry     Submitted entry values.
	 * @param array  $form_data Form data and settings.
	 */
	public function display_edit_form( $entry, $form_data ) {

		$hide_empty = isset( $_COOKIE['wpforms_entry_hide_empty'] ) && 'true' === $_COOKIE['wpforms_entry_hide_empty'];
		?>
		<!-- Edit Entry Form metabox -->
		<div id="wpforms-entry-fields" class="postbox">

			<div class="postbox-header">
				<h2 class="hndle">
					<?php echo '1' === (string) $entry->starred ? '<span class="dashicons dashicons-star-filled"></span>' : ''; ?>
					<span><?php echo esc_html( $form_data['settings']['form_title'] ); ?></span>
					<a href="#" class="wpforms-empty-field-toggle">
						<?php echo $hide_empty ? esc_html__( 'Show Empty Fields', 'wpforms' ) : esc_html__( 'Hide Empty Fields', 'wpforms' ); ?>
					</a>
				</h2>
			</div>

			<div class="inside">

				<?php
				if ( empty( $this->entry_fields ) ) {

					// Whoops, no fields! This shouldn't happen under normal use cases.
					echo '<p class="no-fields">' . esc_html__( 'This entry does not have any fields', 'wpforms' ) . '</p>';

				} else {

					// Display the fields and their editable values.
					$this->display_edit_form_fields( $this->entry_fields, $form_data, $hide_empty );

				}
				?>

			</div>

		</div>
		<?php
	}

	/**
	 * Edit entry form fields.
	 *
	 * @since 1.6.0
	 *
	 * @param array $entry_fields Entry fields data.
	 * @param array $form_data    Form data and settings.
	 * @param bool  $hide_empty   Flag to hide empty fields.
	 */
	private function display_edit_form_fields( $entry_fields, $form_data, $hide_empty ) {

		$form_id = (int) $form_data['id'];

		echo '<input type="hidden" name="wpforms[id]" value="' . absint( $form_id ) . '">';
		echo '<input type="hidden" name="wpforms[entry_id]" value="' . esc_attr( $this->entry->entry_id ) . '">';
		echo '<input type="hidden" name="nonce" value="' . esc_attr( wp_create_nonce( 'wpforms-entry-edit-' . $form_id . '-' . $this->entry->entry_id ) ) . '">';

		if ( empty( $form_data['fields'] ) || ! is_array( $form_data['fields'] ) ) {
			echo '<div class="wpforms-edit-entry-field empty">';
			$this->display_edit_form_field_no_fields();
			echo '</div>';

			return;
		}

		foreach ( $form_data['fields'] as $field_id => $field ) {
			$this->display_edit_form_field( $field_id, $field, $entry_fields, $form_data, $hide_empty );
		}
	}

	/**
	 * Edit entry form field.
	 *
	 * @since 1.6.0
	 *
	 * @param int   $field_id     Field id.
	 * @param array $field        Field data.
	 * @param array $entry_fields Entry fields data.
	 * @param array $form_data    Form data and settings.
	 * @param bool  $hide_empty   Flag to hide empty fields.
	 */
	private function display_edit_form_field( $field_id, $field, $entry_fields, $form_data, $hide_empty ) {

		$field_type = ! empty( $field['type'] ) ? $field['type'] : '';

		// We can't display the field of unknown type or field, that is not displayable.
		if ( ! $this->is_field_displayable( $field_type, $field, $form_data ) ) {
			return;
		}

		$entry_field = ! empty( $entry_fields[ $field_id ] ) ? $entry_fields[ $field_id ] : $this->get_empty_entry_field_data( $field );

		$value = $entry_field['value'] ?? '';

		$field_value = ! wpforms_is_empty_string( $value ) ? $value : '';
		$field_value = apply_filters( 'wpforms_html_field_value', wp_strip_all_tags( $field_value ), $entry_field, $form_data, 'entry-single' );

		$field_class  = ! empty( $field['type'] ) ? sanitize_html_class( 'wpforms-edit-entry-field-' . $field['type'] ) : '';
		$field_class .= wpforms_is_empty_string( $field_value ) ? ' empty' : '';
		$field_class .= ! empty( $field['required'] ) ? ' wpforms-entry-field-required' : '';

		$field_style = $hide_empty && wpforms_is_empty_string( $value ) ? 'display:none;' : '';

		echo '<div class="wpforms-edit-entry-field ' . esc_attr( $field_class ) . '" style="' . esc_attr( $field_style ) . '">';

		// Field label.
		printf(
			'<p class="wpforms-entry-field-name">%s</p>',
			/* translators: %d - field ID. */
			! empty( $field['label'] ) ? esc_html( wp_strip_all_tags( $field['label'] ) ) : sprintf( esc_html__( 'Field ID #%d', 'wpforms' ), (int) $field_id )
		);

		/*
		 * Clean extra user-defined CSS classes to avoid styling issues.
		 * This makes Edit screen look similar to View screen in terms of visual structure:
		 * each field one by one on a single row, no columns, etc.
		 */
		$field['css'] = '';

		// Add properties to the field.
		$field['properties'] = wpforms()->get( 'frontend' )->get_field_properties( $field, $form_data );

		// Field output.
		if ( $this->is_field_entries_output_editable( $field, $entry_fields, $form_data ) ) {
			$this->display_edit_form_field_editable( $entry_field, $field, $form_data );
		} else {
			$this->display_edit_form_field_non_editable( $field_value );
		}

		echo '</div>';
	}

	/**
	 * Edit entry editable form field.
	 *
	 * @since 1.6.0
	 *
	 * @param array $entry_field Entry field data.
	 * @param array $field       Field data.
	 * @param array $form_data   Form data and settings.
	 */
	private function display_edit_form_field_editable( $entry_field, $field, $form_data ) {

		wpforms()->get( 'frontend' )->field_container_open( $field, $form_data );

		$field_object = $this->get_entries_edit_field_object( $field['type'] );

		$field_object->field_display( $entry_field, $field, $form_data );

		echo '</div>';
	}

	/**
	 * Edit entry non-editable form field.
	 *
	 * @since 1.6.0
	 *
	 * @param string $field_value Field value.
	 */
	private function display_edit_form_field_non_editable( $field_value ) {

		echo '<p class="wpforms-entry-field-value">';
		echo ! wpforms_is_empty_string( $field_value ) ?
			nl2br( make_clickable( $field_value ) ) : // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html__( 'Empty', 'wpforms' );
		echo '</p>';
	}

	/**
	 * Display a message about no fields in a form.
	 *
	 * @since 1.6.0.2
	 */
	private function display_edit_form_field_no_fields() {

		echo '<p class="wpforms-entry-field-value">';

		if ( \wpforms_current_user_can( 'edit_form_single', $this->form_data['id'] ) ) {
			$edit_url = add_query_arg(
				[
					'page'    => 'wpforms-builder',
					'view'    => 'fields',
					'form_id' => absint( $this->form_data['id'] ),
				],
				admin_url( 'admin.php' )
			);
			printf(
				wp_kses( /* translators: %s - form edit URL. */
					__( 'You don\'t have any fields in this form. <a href="%s">Add some!</a>', 'wpforms' ),
					[
						'a' => [
							'href' => [],
						],
					]
				),
				esc_url( $edit_url )
			);
		} else {
			esc_html_e( 'You don\'t have any fields in this form.', 'wpforms' );
		}

		echo '</p>';
	}

	/**
	 * Add Update Button to Entry Meta Details metabox actions.
	 *
	 * @since 1.6.0
	 *
	 * @param object $entry     Entry data.
	 * @param array  $form_data Form data.
	 */
	public function update_button( $entry, $form_data ) {

		printf(
			'<div id="publishing-action">
				<button class="button button-primary button-large wpforms-submit" id="wpforms-edit-entry-update">%s</button>
				<img src="%sassets/images/submit-spin.svg" class="wpforms-submit-spinner" style="display: none;">
			</div>',
			esc_html__( 'Update', 'wpforms' ),
			esc_url( WPFORMS_PLUGIN_URL )
		);
	}

	/**
	 * AJAX form submit.
	 *
	 * @since 1.6.0
	 */
	public function ajax_submit() {

		$this->form_id  = ! empty( $_POST['wpforms']['id'] ) ? (int) $_POST['wpforms']['id'] : 0;
		$this->entry_id = ! empty( $_POST['wpforms']['entry_id'] ) ? (int) $_POST['wpforms']['entry_id'] : 0;
		$this->errors   = [];

		if ( empty( $this->form_id ) ) {
			$this->errors['header'] = esc_html__( 'Invalid form.', 'wpforms' );
		}

		if ( empty( $this->entry_id ) ) {
			$this->errors['header'] = esc_html__( 'Invalid Entry.', 'wpforms' );
		}

		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'wpforms-entry-edit-' . $this->form_id . '-' . $this->entry_id ) ) {
			$this->errors['header'] = esc_html__( 'You do not have permission to perform this action.', 'wpforms' );
		}

		if ( ! empty( $this->errors['header'] ) ) {
			$this->process_errors();
		}

		// Hook for add-ons.
		do_action( 'wpforms_pro_admin_entries_edit_submit_before_processing', $this->form_id, $this->entry_id );

		// Process the data.
		$this->process( stripslashes_deep( wp_unslash( $_POST['wpforms'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Process the form entry updating.
	 *
	 * @since 1.6.0
	 *
	 * @param array $entry Form submission raw data ($_POST).
	 */
	private function process( $entry ) {

		// Setup variables.
		$this->fields = [];
		$this->entry  = wpforms()->get( 'entry' )->get( $this->entry_id );
		$form_id      = $this->form_id;
		$this->form   = wpforms()->get( 'form' )->get( $this->form_id, [ 'cap' => 'edit_entries_form_single' ] );

		// Validate form is real.
		if ( ! $this->form ) {
			$this->errors['header'] = esc_html__( 'Invalid form.', 'wpforms' );
			$this->process_errors();
			return;
		}

		// Validate entry is real.
		if ( ! $this->entry ) {
			$this->errors['header'] = esc_html__( 'Invalid entry.', 'wpforms' );
			$this->process_errors();
			return;
		}

		// Formatted form data for hooks.
		$this->form_data = apply_filters( 'wpforms_pro_admin_entries_edit_process_before_form_data', wpforms_decode( $this->form->post_content ), $entry );

		$this->form_data['created'] = $this->form->post_date;

		// Existing entry fields data.
		$this->entry_fields = apply_filters( 'wpforms_pro_admin_entries_edit_existing_entry_fields', wpforms_decode( $this->entry->fields ), $this->entry, $this->form_data );

		// Pre-process/validate hooks and filter.
		// Data is not validated or cleaned yet so use with caution.
		$entry = apply_filters( 'wpforms_pro_admin_entries_edit_process_before_filter', $entry, $this->form_data );

		do_action( 'wpforms_pro_admin_entries_edit_process_before', $entry, $this->form_data );
		do_action( "wpforms_pro_admin_entries_edit_process_before_{$this->form_id}", $entry, $this->form_data );

		// Validate fields.
		$this->process_fields( $entry, 'validate' );

		// Validation errors.
		if ( ! empty( wpforms()->get( 'process' )->errors[ $form_id ] ) ) {
			$this->errors = wpforms()->get( 'process' )->errors[ $form_id ];

			if ( empty( $this->errors['header'] ) ) {
				$this->errors['header'] = esc_html__( 'Entry has not been saved, please see the fields errors.', 'wpforms' );
			}
			$this->process_errors();
			exit;
		}

		// Format fields.
		$this->process_fields( $entry, 'format' );

		// This hook is for internal purposes and should not be leveraged.
		do_action( 'wpforms_pro_admin_entries_edit_process_format_after', $this->form_data );

		/**
		 * Entries edit process hooks/filter.
		 *
		 * @since 1.6.0
		 *
		 * @param array $fields    Entry fields data.
		 * @param array $entry     Entry data.
		 * @param array $form_data Form data and settings.
		 */
		$this->fields = apply_filters( 'wpforms_pro_admin_entries_edit_process_filter', wpforms()->get( 'process' )->fields, $entry, $this->form_data );

		do_action( 'wpforms_pro_admin_entries_edit_process', $this->fields, $entry, $this->form_data );
		do_action( "wpforms_pro_admin_entries_edit_process_{$form_id}", $this->fields, $entry, $this->form_data );

		$this->fields = apply_filters( 'wpforms_pro_admin_entries_edit_process_after_filter', $this->fields, $entry, $this->form_data );

		// Success - update data and send success.
		$this->process_update();
	}

	/**
	 * Process entry fields: validate or format.
	 *
	 * @since 1.6.0
	 *
	 * @param array  $entry  Submitted entry data.
	 * @param string $action Action to perform: `validate` or `format`.
	 */
	private function process_fields( $entry, $action = 'validate' ) {

		if ( empty( $this->form_data['fields'] ) ) {
			return;
		}

		$form_data = $this->form_data;

		$action = empty( $action ) ? 'validate' : $action;

		foreach ( (array) $form_data['fields']  as $field_properties ) {

			if (
				! $this->is_field_entries_editable( $field_properties['type'], $field_properties, $form_data ) ||
				! $this->is_field_entries_output_editable( $field_properties, $this->entry_fields, $form_data )
			) {
				continue;
			}

			$field_id     = isset( $field_properties['id'] ) ? $field_properties['id'] : '0';
			$field_type   = ! empty( $field_properties['type'] ) ? $field_properties['type'] : '';
			$field_submit = isset( $entry['fields'][ $field_id ] ) ? $entry['fields'][ $field_id ] : '';
			$field_data   = ! empty( $this->entry_fields[ $field_id ] ) ? $this->entry_fields[ $field_id ] : $this->get_empty_entry_field_data( $field_properties );

			if ( $action === 'validate' ) {

				// Some fields can be `required` but have an empty value because the field is hidden by CL on the frontend.
				// For cases like this we should allow empty value even for the `required` fields.
				if (
					! empty( $form_data['fields'][ $field_id ]['required'] ) &&
					(
						! isset( $field_data['value'] ) ||
						(string) $field_data['value'] === ''
					)
				) {
					unset( $form_data['fields'][ $field_id ]['required'] );
				}
			}

			if ( $action === 'validate' || $action === 'format' ) {
				$this->get_entries_edit_field_object( $field_type )->$action( $field_id, $field_submit, $field_data, $form_data );
			}
		}
	}

	/**
	 * Update entry data.
	 *
	 * @since 1.6.0
	 */
	private function process_update() {

		// Update entry fields.
		$updated_fields = $this->process_update_fields_data();

		// Silently return success if no changes performed.
		if ( empty( $updated_fields ) ) {
			wp_send_json_success();
		}

		// Update entry.
		$entry_data = [
			'fields'        => wp_json_encode( $this->get_updated_entry_fields( $updated_fields ) ),
			'date_modified' => $this->date_modified,
		];
		wpforms()->get( 'entry' )->update( $this->entry_id, $entry_data, '', 'edit_entry', [ 'cap' => 'edit_entry_single' ] );

		// Add record to entry meta.
		$this->add_entry_meta( esc_html__( 'Entry edited.', 'wpforms' ) );

		$removed_files = \WPForms_Field_File_Upload::delete_uploaded_files_from_entry( $this->entry_id, $updated_fields, $this->entry_fields );

		array_map( [ $this, 'add_removed_file_meta' ], $removed_files );

		$datetime_offset = get_option( 'gmt_offset' ) * 3600;
		$response        = [
			'modified' => wpforms_datetime_format( $this->date_modified, '', true ),
		];

		do_action( 'wpforms_pro_admin_entries_edit_submit_completed', $this->form_data, $response, $updated_fields, $this->entry );

		wp_send_json_success( $response );
	}

	/**
	 * Update entry fields data.
	 *
	 * @since 1.6.0
	 *
	 * @return array Updated fields data.
	 */
	private function process_update_fields_data() {

		$updated_fields = [];

		if ( ! is_array( $this->fields ) ) {
			return $updated_fields;
		}

		// Get saved fields data from DB.
		$entry_fields_obj = wpforms()->get( 'entry_fields' );
		$dbdata_result    = $entry_fields_obj->get_fields(
			[
				'entry_id' => $this->entry_id,
				'number'   => 1000,
			]
		);
		$dbdata_fields    = [];

		if ( ! empty( $dbdata_result ) ) {
			$dbdata_fields = array_combine( wp_list_pluck( $dbdata_result, 'field_id' ), $dbdata_result );
			$dbdata_fields = array_map( 'get_object_vars', $dbdata_fields );
		}

		$this->date_modified = current_time( 'Y-m-d H:i:s' );

		foreach ( $this->fields as $field ) {
			$save_field          = apply_filters( 'wpforms_entry_save_fields', $field, $this->form_data, $this->entry_id );
			$field_id            = $save_field['id'];
			$field_type          = empty( $save_field['type'] ) ? '' : $save_field['type'];
			$save_field['value'] = empty( $save_field['value'] ) && $save_field['value'] !== '0' ? '' : (string) $save_field['value'];
			$dbdata_value_exist  = isset( $dbdata_fields[ $field_id ]['value'] );

			// Process the field only if value was changed or not existed in DB at all. Also check if field is editable.
			if (
				( $dbdata_value_exist && (string) $dbdata_fields[ $field_id ]['value'] === $save_field['value'] ) ||
				! $this->is_field_entries_editable( $field_type, $this->form_data['fields'][ $field_id ], $this->form_data )
			) {
				continue;
			}

			// Add field data to DB if it doesn't exist and isn't empty.
			if ( ! $dbdata_value_exist && $save_field['value'] !== '' ) {
				$entry_fields_obj->add(
					[
						'entry_id' => $this->entry_id,
						'form_id'  => (int) $this->form_data['id'],
						'field_id' => (int) $field_id,
						'value'    => $save_field['value'],
						'date'     => $this->date_modified,
					]
				);
			}

			// Update field data in DB if it exists and isn't empty.
			if ( $dbdata_value_exist && $save_field['value'] !== '' ) {
				$entry_fields_obj->update(
					(int) $dbdata_fields[ $field_id ]['id'],
					[
						'value' => $save_field['value'],
						'date'  => $this->date_modified,
					],
					'id',
					'edit_entry'
				);
			}

			// Delete field data in DB if it exists and the value is empty.
			if ( $dbdata_value_exist && $save_field['value'] === '' ) {
				$entry_fields_obj->delete( (int) $dbdata_fields[ $field_id ]['id'] );
			}

			$updated_fields[ $field_id ] = $field;
		}

		return $updated_fields;
	}

	/**
	 * Process validation errors.
	 *
	 * @since 1.6.0
	 */
	private function process_errors() {

		$errors = $this->errors;

		if ( empty( $errors ) ) {
			wp_send_json_error();
		}

		$fields       = isset( $this->form_data['fields'] ) ? $this->form_data['fields'] : [];
		$field_errors = array_intersect_key( $errors, $fields );

		$response = [];

		$response['errors']['general'] = ! empty( $errors['header'] ) ? wpautop( esc_html( $errors['header'] ) ) : '';
		$response['errors']['field']   = ! empty( $field_errors ) ? $field_errors : [];

		$response = apply_filters( 'wpforms_pro_admin_entries_edit_submit_errors_response', $response, $this->form_data );

		do_action( 'wpforms_pro_admin_entries_edit_submit_completed', $this->form_data, $response, [], $this->entry );

		wp_send_json_error( $response );
	}

	/**
	 * Get updated entry fields data.
	 * Combine existing entry fields with updated fields keeping the original fields order.
	 *
	 * @since 1.6.0
	 *
	 * @param array $updated_fields Updated fields data.
	 *
	 * @return array Updated entry fields data.
	 */
	private function get_updated_entry_fields( $updated_fields ) {

		if ( empty( $updated_fields ) ) {
			return $this->entry_fields;
		}

		$result_fields = [];
		$form_fields   = ! empty( $this->form_data['fields'] ) ? $this->form_data['fields'] : [];

		foreach ( $form_fields as $field_id => $field ) {
			$entry_field = isset( $this->entry_fields[ $field_id ] ) ?
							$this->entry_fields[ $field_id ] :
							$this->get_empty_entry_field_data( $field );

			$result_fields[ $field_id ] = isset( $updated_fields[ $field_id ] ) ? $updated_fields[ $field_id ] : $entry_field;
		}
		return $result_fields;
	}

	/**
	 * Get empty entry field data.
	 *
	 * @since 1.6.0
	 *
	 * @param array $field_properties Field properties.
	 *
	 * @return array Empty entry field data.
	 */
	public function get_empty_entry_field_data( $field_properties ) {

		return [
			'name'      => ! empty( $field_properties['label'] ) ? $field_properties['label'] : '',
			'value'     => '',
			'value_raw' => '',
			'id'        => ! empty( $field_properties['id'] ) ? $field_properties['id'] : '',
			'type'      => ! empty( $field_properties['type'] ) ? $field_properties['type'] : '',
		];
	}

	/**
	 * Check if the field can be displayed.
	 *
	 * @since 1.6.0
	 * @since 1.7.1 Added $field and $form_data arguments.
	 *
	 * @param string $field_type Field type.
	 * @param array  $field      Field data.
	 * @param array  $form_data  Form data and settings.
	 *
	 * @return bool
	 */
	private function is_field_displayable( $field_type, $field, $form_data ) {

		if ( empty( $field_type ) ) {
			return false;
		}

		/**
		 * Determine if the field is displayable on the backend.
		 *
		 * @since 1.7.1
		 *
		 * @param bool  $is_displayable Is the field displayable?
		 * @param array $field          Field data.
		 * @param array $form_data      Form data and settings.
		 *
		 * @return bool
		 */
		$is_displayable = (bool) apply_filters( "wpforms_pro_admin_entries_edit_is_field_displayable_{$field_type}", true, $field, $form_data );

		if ( ! has_filter( 'wpforms_pro_admin_entries_edit_fields_dont_display' ) ) {
			return $is_displayable;
		}

		$dont_display = [ 'divider', 'entry-preview', 'html', 'pagebreak' ];

		return ! in_array(
			$field_type,
			(array) apply_filters_deprecated(
				'wpforms_pro_admin_entries_edit_fields_dont_display',
				[ $dont_display ],
				'1.7.1 of the WPForms plugin',
				"wpforms_pro_admin_entries_edit_is_field_displayable_{$field_type}"
			),
			true
		);
	}

	/**
	 * Check if the field entries are editable.
	 *
	 * @since 1.6.0
	 *
	 * @param string $type      Field type.
	 * @param array  $field     Field data.
	 * @param array  $form_data Form data.
	 *
	 * @return bool
	 */
	private function is_field_entries_editable( $type, $field, $form_data ) {

		$editable = in_array( $type,  wpforms()->get( 'entry' )->get_editable_field_types(), true );

		/**
		 * Allow change if the field is editable regarding to its type.
		 *
		 * @since 1.6.0
		 * @since 1.8.4 Added $field and $form_data arguments.
		 *
		 * @param bool   $editable  True if is editable.
		 * @param string $type      Field type.
		 * @param array  $field     Field data.
		 * @param array  $form_data Form data.
		 *
		 * @return bool
		 */
		return (bool) apply_filters( 'wpforms_pro_admin_entries_edit_field_editable', $editable, $type, $field, $form_data );
	}

	/**
	 * Check if the field entries are editable.
	 *
	 * @since 1.8.4
	 *
	 * @param array $field        Field data.
	 * @param array $entry_fields Entry fields data.
	 * @param array $form_data    Form data and settings.
	 *
	 * @return bool
	 */
	private function is_field_entries_output_editable( $field, $entry_fields, $form_data ) {

		/**
		 * Is the field editable?
		 *
		 * @since 1.5.9.5
		 *
		 * @param bool  $is_editable  Is the field editable?
		 * @param array $field        Field data.
		 * @param array $entry_fields Entry fields data.
		 * @param array $form_data    Form data and settings.
		 *
		 * @return bool ____
		 */
		return (bool) apply_filters(
			'wpforms_pro_admin_entries_edit_field_output_editable',
			$this->is_field_entries_editable( $field['type'], $field, $form_data ),
			$field,
			$entry_fields,
			$form_data
		);
	}

	/**
	 * Get entry editing field object.
	 *
	 * @since 1.6.0
	 *
	 * @param string $type Field type.
	 *
	 * @return \WPForms\Pro\Forms\Fields\Base\EntriesEdit
	 */
	private function get_entries_edit_field_object( $type ) {

		// Runtime objects holder.
		static $objects = [];

		// Getting the class name.
		$class_name = implode( '', array_map( 'ucfirst', explode( '-', $type ) ) );
		$class_name = '\WPForms\Pro\Forms\Fields\\' . $class_name . '\EntriesEdit';

		// Init object if needed.
		if ( empty( $objects[ $type ] ) ) {
			$objects[ $type ] = class_exists( $class_name ) ? new $class_name() : new EntriesEdit( $type );
		}

		return apply_filters( "wpforms_pro_admin_entries_edit_field_object_{$type}", $objects[ $type ] );
	}

	/**
	 * Helper function to determine if it is admin entry editing ajax request.
	 *
	 * @since 1.6.0
	 *
	 * @return boolean
	 */
	public function is_admin_entry_editing_ajax() {

		if ( ! wp_doing_ajax() ) {
			return false;
		}

		$ref = wp_get_raw_referer();

		if ( ! $ref ) {
			return false;
		}

		$query = wp_parse_url( $ref, PHP_URL_QUERY );
		wp_parse_str( $query, $query_vars );

		if (
			empty( $query_vars['page'] ) ||
			empty( $query_vars['view'] ) ||
			empty( $query_vars['entry_id'] )
		) {
			return false;
		}

		if (
			$query_vars['page'] !== 'wpforms-entries' ||
			$query_vars['view'] !== 'edit'
		) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if (
			empty( $_POST['wpforms']['entry_id'] ) ||
			empty( $_POST['action'] ) ||
			empty( $_POST['nonce'] )
		) {
			return false;
		}

		if ( $_POST['action'] !== 'wpforms_submit' ) {
			return false;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return true;
	}

	/**
	 * Add entry log record to the entry_meta table.
	 *
	 * @since 1.6.6
	 *
	 * @param string $message Message to log.
	 */
	private function add_entry_meta( $message ) {

		// Add record to entry meta.
		wpforms()->get( 'entry_meta' )->add(
			[
				'entry_id' => (int) $this->entry_id,
				'form_id'  => (int) $this->form_id,
				'user_id'  => get_current_user_id(),
				'type'     => 'log',
				'data'     => wpautop( sprintf( '<em>%s</em>', esc_html( $message ) ) ),
			],
			'entry_meta'
		);
	}

	/**
	 * Add removed file to entry meta.
	 *
	 * @since 1.6.6
	 *
	 * @param string $filename File name.
	 */
	private function add_removed_file_meta( $filename ) {

		/* translators: %s - name of the file that has been deleted. */
		$this->add_entry_meta( sprintf( esc_html__( 'The uploaded file "%s" has been deleted.', 'wpforms' ), esc_html( $filename ) ) );
	}

	/**
	 * Maybe remove attachment data from entry field.
	 *
	 * @since 1.6.6
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function maybe_remove_attachment_data_from_entries( $attachment_id ) {

		global $wpdb;

		$table_name = wpforms()->get( 'entry' )->table_name;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$entries = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `entry_id` FROM $table_name WHERE `fields` LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%"attachment_id":' . $wpdb->esc_like( $attachment_id ) . ',%'
			),
			ARRAY_N
		);

		if ( ! $entries ) {
			return;
		}

		foreach ( $entries as $entry_id ) {

			$this->date_modified = current_time( 'Y-m-d H:i:s' );

			$entry = wpforms()->get( 'entry' )->get( $entry_id );

			if ( empty( $entry ) ) {
				continue;
			}

			$entry_fields = wpforms_decode( $entry->fields );

			if ( empty( $entry_fields ) ) {
				continue;
			}

			$this->entry_id = $entry_id;
			$this->form_id  = $entry->form_id;

			$entry_fields = wpforms_chain( $entry_fields )
				->map(
					function ( $entry_field ) use ( $entry_id, $attachment_id ) {

						if ( $entry_field['type'] !== 'file-upload' ) {
							return $entry_field;
						}

						return $this->maybe_remove_attachment_data_from_entry_fields( $entry_field, (int) $entry_id, $attachment_id );
					}
				)
				->value();

			$entry_data = [
				'fields'        => wp_json_encode( $entry_fields ),
				'date_modified' => $this->date_modified,
			];

			wpforms()->get( 'entry' )->update(
				$entry_id,
				$entry_data,
				'',
				'edit_entry',
				[
					'cap' => 'edit_entry_single',
				]
			);
		}
	}

	/**
	 * Maybe remove attachment from entry fields.
	 *
	 * @since 1.6.6
	 *
	 * @param array $entry_field   Entry Field.
	 * @param int   $entry_id      Entry ID.
	 * @param int   $attachment_id Attachment ID.
	 *
	 * @return string|array
	 */
	private function maybe_remove_attachment_data_from_entry_fields( $entry_field, $entry_id, $attachment_id ) {

		if ( ! \WPForms_Field_File_Upload::is_modern_upload( $entry_field ) ) {
			if ( $this->maybe_remove_attachment_from_entry_field( $attachment_id, $entry_field, $entry_id, $entry_field['id'] ) ) {
				$entry_field['value'] = '';
			}

			return $entry_field;
		}

		if ( empty( $entry_field['value_raw'] ) ) {
			return $entry_field;
		}

		foreach ( $entry_field['value_raw'] as $raw_key => $field_value ) {

			if ( $this->maybe_remove_attachment_from_entry_field( $attachment_id, $field_value, $entry_id, $entry_field['id'] ) ) {
				unset( $entry_field['value_raw'][ $raw_key ] );
			}
		}

		$entry_field['value'] = implode( "\n", array_column( $entry_field['value_raw'], 'value' ) );

		return $entry_field;
	}

	/**
	 * Maybe remove attachment from entry field.
	 *
	 * @since 1.6.6
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $field_data    Field data.
	 * @param int   $entry_id      Entry ID.
	 * @param int   $field_id      Field ID.
	 *
	 * @return bool
	 */
	private function maybe_remove_attachment_from_entry_field( $attachment_id, $field_data, $entry_id, $field_id ) {

		if ( ! isset( $field_data['attachment_id'] ) || (int) $attachment_id !== $field_data['attachment_id'] ) {
			return false;
		}

		$this->add_removed_file_meta( $field_data['file_user_name'] );

		$entry_fields = wpforms()->get( 'entry_fields' )->get_fields(
			[
				'entry_id' => $entry_id,
				'field_id' => $field_id,
			]
		);

		if ( empty( $entry_fields ) ) {
			return false;
		}

		$dbdata_field_id = array_map(
			'get_object_vars',
			$entry_fields
		);

		if ( ! isset( $dbdata_field_id[0]['id'] ) ) {
			return false;
		}

		wpforms()->get( 'entry_fields' )->update(
			(int) $dbdata_field_id[0]['id'],
			[
				'value' => '',
				'date'  => $this->date_modified,
			],
			'id',
			'edit_entry'
		);

		return true;
	}

}
