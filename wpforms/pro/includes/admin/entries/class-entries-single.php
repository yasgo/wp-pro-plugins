<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPForms\Admin\Payments\Views\Overview\Helpers;
use WPForms\Db\Payments\ValueValidator;
use WPForms\Pro\Admin\Entries\Page;

/**
 * Display information about a single form entry.
 *
 * Previously list and single views were contained in a single class,
 * however were separated in v1.3.9.
 *
 * @since 1.3.9
 */
class WPForms_Entries_Single {

	/**
	 * Hold admin alerts.
	 *
	 * @since 1.1.6
	 *
	 * @var array
	 */
	public $alerts = [];

	/**
	 * Abort. Bail on proceeding to process the page.
	 *
	 * @since 1.1.6
	 *
	 * @var bool
	 */
	public $abort = false;

	/**
	 * The human readable error message.
	 *
	 * @since 1.6.5
	 *
	 * @var string
	 */
	private $abort_message;

	/**
	 * Form object.
	 *
	 * @since 1.1.6
	 *
	 * @var object
	 */
	public $form;

	/**
	 * Form data array.
	 *
	 * @since 1.8.3
	 *
	 * @var array
	 */
	public $form_data;

	/**
	 * Entry object.
	 *
	 * @since 1.1.6
	 *
	 * @var object
	 */
	public $entry;

	/**
	 * Entry settings.
	 *
	 * @since 1.8.3
	 *
	 * @var array
	 */
	public $entry_view_settings;

	/**
	 * Primary class constructor.
	 *
	 * @since 1.3.9
	 */
	public function __construct() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.8.3
	 */
	private function hooks() {

		// Maybe load entries page.
		add_action( 'admin_init', [ $this, 'init' ] );

		// Add hidden data to the entry.
		add_filter( 'wpforms_entry_single_data', [ $this, 'add_hidden_data' ], 1010, 3 );

	}

	/**
	 * Determine if the user is viewing the single entry page, if so, party on.
	 *
	 * @since 1.3.9
	 *
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function init() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		// Check if we are on the entry page.
		if ( ! wpforms_is_admin_page( 'entries', 'details' ) ) {
			return;
		}

		$entry_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		if ( empty( $entry_id ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpforms-entries' ) );
			exit;
		}

		if ( ! wpforms_current_user_can( 'view_entry_single', $entry_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view this entry.', 'wpforms' ), 403 );
		}

		// Initiate entry settings.
		$this->entry_view_settings = self::get_entry_view_settings();

		// Entry processing and setup.
		add_action( 'wpforms_entries_init', [ $this, 'process_star' ], 8, 1 );
		add_action( 'wpforms_entries_init', [ $this, 'process_unread' ], 8, 1 );
		add_action( 'wpforms_entries_init', [ $this, 'process_note_delete' ], 8, 1 );
		add_action( 'wpforms_entries_init', [ $this, 'process_note_add' ], 8, 1 );
		add_action( 'wpforms_entries_init', [ $this, 'process_notifications' ], 15, 1 );
		add_action( 'wpforms_entries_init', [ $this, 'setup' ], 10, 1 );
		add_action( 'wpforms_entries_init', [ $this, 'register_alerts' ], 20, 1 );

		// phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation, WPForms.PHP.ValidateHooks.InvalidHookName
		do_action( 'wpforms_entries_init', 'details' );

		// Output. Entry content and metaboxes.
		add_action( 'wpforms_admin_page', [ $this, 'details' ] );
		add_action( 'wpforms_entry_details_content', [ $this, 'details_fields' ], 10, 2 );
		add_action( 'wpforms_entry_details_content', [ $this, 'details_notes' ], 10, 2 );
		add_action( 'wpforms_entry_details_content', [ $this, 'details_log' ], 40, 2 );
		add_action( 'wpforms_entry_details_content', [ $this, 'details_debug' ], 50, 2 );
		add_action( 'wpforms_entry_details_sidebar', [ $this, 'details_meta' ], 10, 2 );
		add_action( 'wpforms_entry_details_sidebar', [ $this, 'details_payment' ], 15, 2 );
		add_action( 'wpforms_entry_details_sidebar', [ $this, 'details_actions' ], 20, 2 );
		add_action( 'wpforms_entry_details_sidebar', [ $this, 'details_related' ], 20, 2 );

		// Remove Screen Options tab from admin area header.
		add_filter( 'screen_options_show_screen', '__return_false' );

		// Enqueues.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueues' ] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 1.3.9
	 */
	public function enqueues() {

		wp_enqueue_media();

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-admin-view-entry',
			WPFORMS_PLUGIN_URL . "assets/pro/js/admin/view-entry{$min}.js",
			[ 'jquery' ],
			WPFORMS_VERSION,
			true
		);

		// Hook for addons.
		do_action( 'wpforms_entries_enqueue', 'details', $this );
	}

	/**
	 * Watch for and run single entry exports.
	 *
	 * @since 1.1.6
	 */
	public function process_export() {
		// Check for run switch.
		if ( empty( $_GET['export'] ) || ! is_numeric( $_GET['export'] ) ) {
			return;
		}

		_deprecated_function( __METHOD__, '1.5.5 of the WPForms plugin', 'WPForms\Pro\Admin\Export\Export class' );

		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wpforms_entry_details_export' ) ) {
			return;
		}
		require_once WPFORMS_PLUGIN_DIR . 'pro/includes/admin/entries/class-entries-export.php';
		$export = new WPForms_Entries_Export();
		$export->entry_type = absint( $_GET['export'] );
		$export->export();
	}

	/**
	 * Watch for and run starring/unstarring entry.
	 *
	 * @since 1.1.6
	 * @since 1.5.7 Added creation entry note for Entry Star action.
	 *
	 * @todo Convert to AJAX
	 */
	public function process_star() {

		// Security check.
		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wpforms_entry_details_star' ) ) {
			return;
		}

		$redirect_url = '';

		// Check for starring.
		if ( ! empty( $_GET['entry_id'] ) && ! empty( $_GET['action'] ) && 'star' === $_GET['action'] ) {

			wpforms()->get( 'entry' )->update(
				absint( $_GET['entry_id'] ),
				[
					'starred' => '1',
				]
			);

			if ( ! empty( $_GET['form'] ) ) {
				wpforms()->get( 'entry_meta' )->add(
					[
						'entry_id' => absint( $_GET['entry_id'] ),
						'form_id'  => absint( $_GET['form'] ),
						'user_id'  => get_current_user_id(),
						'type'     => 'log',
						'data'     => wpautop( sprintf( '<em>%s</em>', esc_html__( 'Entry starred.', 'wpforms' ) ) ),
					],
					'entry_meta'
				);

				$redirect_url = remove_query_arg( 'form' );
			}

			$this->alerts[] = [
				'type'    => 'success',
				'message' => esc_html__( 'This entry has been starred.', 'wpforms' ),
				'dismiss' => true,
			];
		}

		// Check for unstarring.
		if ( ! empty( $_GET['entry_id'] ) && ! empty( $_GET['action'] ) && 'unstar' === $_GET['action'] ) {

			wpforms()->get( 'entry' )->update(
				absint( $_GET['entry_id'] ),
				[
					'starred' => '0',
				]
			);

			if ( ! empty( $_GET['form'] ) ) {
				wpforms()->get( 'entry_meta' )->add(
					[
						'entry_id' => absint( $_GET['entry_id'] ),
						'form_id'  => absint( $_GET['form'] ),
						'user_id'  => get_current_user_id(),
						'type'     => 'log',
						'data'     => wpautop( sprintf( '<em>%s</em>', esc_html__( 'Entry unstarred.', 'wpforms' ) ) ),
					],
					'entry_meta'
				);

				$redirect_url = remove_query_arg( 'form' );
			}

			$this->alerts[] = [
				'type'    => 'success',
				'message' => esc_html__( 'This entry has been unstarred.', 'wpforms' ),
				'dismiss' => true,
			];
		}

		// Clean URL before the next page refresh - stop create a new note.
		if ( ! empty( $redirect_url ) ) {
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Watch for and run entry unread toggle.
	 *
	 * @todo Convert to AJAX.
	 *
	 * @since 1.1.6
	 */
	public function process_unread() {

		// Security check.
		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wpforms_entry_details_unread' ) ) {
			return;
		}

		// Check for run switch.
		if ( empty( $_GET['entry_id'] ) || empty( $_GET['action'] ) || 'unread' !== $_GET['action'] ) {
			return;
		}

		$entry_id = absint( $_GET['entry_id'] );

		// Capability check.
		if ( ! \wpforms_current_user_can( 'view_entry_single', $entry_id ) ) {
			return;
		}

		$is_success = wpforms()->get( 'entry' )->update(
			$entry_id,
			[
				'viewed' => '0',
			]
		);

		if ( ! $is_success ) {
			return;
		}

		if ( ! empty( $_GET['form'] ) ) {
			wpforms()->get( 'entry_meta' )->add(
				[
					'entry_id' => $entry_id,
					'form_id'  => absint( $_GET['form'] ),
					'user_id'  => get_current_user_id(),
					'type'     => 'log',
					'data'     => wpautop( sprintf( '<em>%s</em>', esc_html__( 'Entry unread.', 'wpforms' ) ) ),
				],
				'entry_meta'
			);
		}

		$this->alerts[] = [
			'type'    => 'success',
			'message' => esc_html__( 'This entry has been marked unread.', 'wpforms' ),
			'dismiss' => true,
		];
	}

	/**
	 * Watch for and run entry note deletion.
	 *
	 * @todo Convert to AJAX.
	 *
	 * @since 1.1.6
	 */
	public function process_note_delete() {

		// Security check.
		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wpforms_entry_details_deletenote' ) ) {
			return;
		}

		if ( empty( $_GET['note_id'] ) || empty( $_GET['entry_id'] ) ) {
			return;
		}

		if ( empty( $_GET['action'] ) || $_GET['action'] !== 'delete_note' ) {
			return;
		}

		$note_id    = absint( $_GET['note_id'] );
		$entry_id   = absint( $_GET['entry_id'] );
		$message    = esc_html__( 'Note deleted.', 'wpforms' );
		$entry_meta = wpforms()->get( 'entry_meta' );

		// Capability check.
		if ( ! wpforms_current_user_can( 'edit_entry_single', $entry_id ) ) {
			return;
		}

		// Get form id.
		$meta = $entry_meta->get_meta(
			[
				'id'   => $note_id,
				'type' => 'note',
			]
		);

		$form_id = null;

		if ( isset( $meta[0] ) ) {
			$form_id = $meta[0]->form_id;
		}

		if ( ! $form_id ) {
			return;
		}

		$is_deleted = $entry_meta->delete( $note_id );

		if ( ! $is_deleted ) {
			$this->alerts[] = [
				'type'    => 'error',
				'message' => esc_html__( 'Something went wrong while deleting a note. Please try again.', 'wpforms' ),
				'dismiss' => true,
			];

			return;
		}

		// Add log record.
		$entry_meta->add(
			[
				'entry_id' => $entry_id,
				'form_id'  => $form_id,
				'user_id'  => get_current_user_id(),
				'type'     => 'log',
				'data'     => wpautop( sprintf( '<em>%s</em>', $message ) ),
			],
			'entry_meta'
		);

		// Notify a user.
		$this->alerts[] = [
			'type'    => 'success',
			'message' => $message,
			'dismiss' => true,
		];
	}

	/**
	 * Watch for and run creating entry notes.
	 *
	 * @todo Convert to AJAX
	 *
	 * @since 1.1.6
	 */
	public function process_note_add() {

		// Check for post trigger and required vars.
		if ( empty( $_POST['wpforms_add_note'] ) || empty( $_POST['entry_id'] ) || empty( $_POST['form_id'] ) || empty( $_POST['entry_note'] ) ) {
			return;
		}

		// Security check.
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'wpforms_entry_details_addnote' ) ) {
			return;
		}

		$note = wp_kses_post( wp_unslash( $_POST['entry_note'] ) );

		// Bail if note has no content.
		if ( empty( $note ) ) {
			$this->alerts[] = [
				'type'    => 'error',
				'message' => esc_html__( 'Please provide a meaningful content for the note.', 'wpforms' ),
				'dismiss' => true,
			];

			return;
		}

		$entry_id   = absint( $_POST['entry_id'] );
		$form_id    = absint( $_POST['form_id'] );
		$message    = esc_html__( 'Note added.', 'wpforms' );
		$entry_meta = wpforms()->get( 'entry_meta' );

		// Add note.
		$entry_meta->add(
			[
				'entry_id' => $entry_id,
				'form_id'  => $form_id,
				'user_id'  => get_current_user_id(),
				'type'     => 'note',
				'data'     => wpautop( $note ),
			],
			'entry_meta'
		);

		// Add log record.
		$entry_meta->add(
			[
				'entry_id' => $entry_id,
				'form_id'  => $form_id,
				'user_id'  => get_current_user_id(),
				'type'     => 'log',
				'data'     => wpautop( sprintf( '<em>%s</em>', $message ) ),
			],
			'entry_meta'
		);

		// Notify a user.
		$this->alerts[] = [
			'type'    => 'success',
			'message' => $message,
			'dismiss' => true,
		];
	}

	/**
	 * Watch for and run single entry notifications.
	 *
	 * @since 1.1.6
	 */
	public function process_notifications() {

		// Check for run switch.
		if ( empty( $_GET['action'] ) || 'notifications' !== $_GET['action'] ) {
			return;
		}

		// Security check.
		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wpforms_entry_details_notifications' ) ) {
			return;
		}

		// Check for existing errors.
		if ( $this->abort || empty( $this->entry ) || empty( $this->form ) ) {
			return;
		}

		$fields    = wpforms_decode( $this->entry->fields );
		$form_data = wpforms_decode( $this->form->post_content );

		wpforms()->get( 'process' )->entry_email( $fields, [], $form_data, $this->entry->entry_id );

		$this->alerts[] = [
			'type'    => 'success',
			'message' => esc_html__( 'Notifications were resent!', 'wpforms' ),
			'dismiss' => true,
		];
	}

	/**
	 * Setup entry details data.
	 *
	 * This function does the error checking and variable setup.
	 *
	 * @since 1.1.6
	 */
	public function setup() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded

		// No entry ID was provided, abort.
		if ( empty( $_GET['entry_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->abort_message = esc_html__( 'It looks like the provided entry ID isn\'t valid.', 'wpforms' );
			$this->abort         = true;

			return;
		}

		$form_handler  = wpforms()->get( 'form' );
		$entry_handler = wpforms()->get( 'entry' );

		// Find the entry.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$entry = $entry_handler->get( absint( $_GET['entry_id'] ) );

		// If entry exists, find the form information.
		if ( ! empty( $entry ) ) {
			$form = $form_handler->get( $entry->form_id, [ 'cap' => 'view_entries_form_single' ] );
		}

		// No entry was found, no form was found, the form is in the Trash.
		if ( empty( $entry ) || empty( $form ) || $form->post_status === 'trash' ) {
			$this->abort_message = esc_html__( 'It looks like the entry you are trying to access is no longer available.', 'wpforms' );
			$this->abort         = true;

			return;
		}

		// Check if entry has trash status.
		if ( $entry->status === Page::TRASH_ENTRY_STATUS ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$this->abort_message = esc_html__( 'You can\'t view this entry because it\'s in the trash.', 'wpforms' );
			$this->abort         = true;

			return;
		}

		// Form details.
		$form_data      = wpforms_decode( $form->post_content );
		$form_id        = ! empty( $form_data['id'] ) ? $form_data['id'] : $entry->form_id;
		$form->form_url = add_query_arg(
			[
				'page'    => 'wpforms-entries',
				'view'    => 'list',
				'form_id' => absint( $form_id ),
			],
			admin_url( 'admin.php' )
		);

		// Define other entry details.
		$entry->entry_next       = $entry_handler->get_next( $entry->entry_id, $form_id, $entry->status );
		$entry->entry_prev       = $entry_handler->get_prev( $entry->entry_id, $form_id, $entry->status );
		$entry->entry_next_class = ! empty( $entry->entry_next ) ? '' : 'inactive';
		$entry->entry_prev_class = ! empty( $entry->entry_prev ) ? '' : 'inactive';
		$entry->entry_prev_count = $entry_handler->get_prev_count( $entry->entry_id, $form_id, $entry->status );

		// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$entry_count_args   = $entry->status === 'spam' ? [ 'form_id' => $form_id, 'status' => 'spam' ] : [ 'form_id' => $form_id ];
		$entry->entry_count = $entry_handler->get_entries( $entry_count_args, true );

		$base_url              = add_query_arg(
			[
				'page' => 'wpforms-entries',
				'view' => 'details',
			],
			admin_url( 'admin.php' )
		);
		$entry->entry_next_url = ! empty( $entry->entry_next ) ? add_query_arg( [ 'entry_id' => absint( $entry->entry_next->entry_id ) ], $base_url ) : '#';
		$entry->entry_prev_url = ! empty( $entry->entry_prev ) ? add_query_arg( [ 'entry_id' => absint( $entry->entry_prev->entry_id ) ], $base_url ) : '#';

		// Define entry meta.
		$entry_meta_handler = wpforms()->get( 'entry_meta' );

		$entry->entry_notes = $entry_meta_handler->get_meta(
			[
				'entry_id' => $entry->entry_id,
				'type'     => 'note',
			]
		);
		$entry->entry_logs  = $entry_meta_handler->get_meta(
			[
				'entry_id' => $entry->entry_id,
				'type'     => 'log',
			]
		);

		// Check for other entries by this user.
		if ( ! empty( $entry->user_id ) || ! empty( $entry->user_uuid ) ) {
			$args    = [
				'form_id'   => $form_id,
				'user_id'   => ! empty( $entry->user_id ) ? $entry->user_id : '',
				'user_uuid' => ! empty( $entry->user_uuid ) ? $entry->user_uuid : '',
			];
			$related = $entry_handler->get_entries( $args );

			foreach ( $related as $key => $r ) {
				if ( (int) $r->entry_id === (int) $entry->entry_id ) {
					unset( $related[ $key ] );
				}
			}

			$entry->entry_related = $related;
		}

		// Make public.
		$this->entry = $entry;

		$this->form  = $form;

		// Lastly, mark entry as read if needed.
		if ( $entry->viewed !== '1' && empty( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$is_success = $entry_handler->update(
				$entry->entry_id,
				[
					'viewed' => '1',
				]
			);
		}

		// Add log entry.
		if ( ! empty( $is_success ) ) {
			$entry_meta_handler->add(
				[
					'entry_id' => $entry->entry_id,
					'form_id'  => $form_id,
					'user_id'  => get_current_user_id(),
					'type'     => 'log',
					'data'     => wpautop( sprintf( '<em>%s</em>', esc_html__( 'Entry read.', 'wpforms' ) ) ),
				],
				'entry_meta'
			);

			// Update entry logs.
			$this->entry->viewed     = '1';
			$this->entry->entry_logs = $entry_meta_handler->get_meta(
				[
					'entry_id' => $entry->entry_id,
					'type'     => 'log',
				]
			);
		}

		/**
		 * Fires after the Entry Details page is initialized but not rendered yet.
		 *
		 * At this point the existing entry is found and loaded, the form data is available,
		 * additional entry details like notes, logs are available and the entry is
		 * marked as read, if needed.
		 *
		 * @since 1.1.6
		 *
		 * @param WPForms_Entries_Single $this Current instance of the WPForms_Entries_Single class.
		 */
		do_action( 'wpforms_entry_details_init', $this ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Entry Details page.
	 *
	 * @since 1.0.0
	 */
	public function details() {
		?>
		<div id="wpforms-entries-single" class="wrap wpforms-admin-wrap">

			<h1 class="page-title">

				<?php esc_html_e( 'View Entry', 'wpforms' ); ?>

				<?php
				if ( $this->abort ) {
					echo '</h1>'; // close heading.
					echo '</div>'; // close wrap.

					echo '<div class="wpforms-admin-content">';

						// Output no entries screen.
						echo wpforms_render( 'admin/empty-states/no-entry', [ 'message' => $this->abort_message ], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

					echo '</div>';

					return;
				}

				$entry     = $this->entry;
				$form_data = wpforms_decode( $this->form->post_content );

				/**
				 * Filters the form URL for the entry.
				 *
				 * @since 1.8.3
				 *
				 * @param string $form_url Form URL.
				 * @param int    $entry_id Entry ID.
				 * @param int    $form_id  Form ID.
				 */
				$form_url = apply_filters( 'wpforms_entries_single_details_form_url', $this->form->form_url, $entry->entry_id, $form_data['id'] );
				?>

				<a href="<?php echo esc_url( $form_url ); ?>" class="page-title-action wpforms-btn wpforms-btn-orange" data-action="back">
					<svg viewBox="0 0 16 14" class="page-title-action-icon">
						<path d="M16 6v2H4l4 4-1 2-7-7 7-7 1 2-4 4h12Z"/>
					</svg>
					<span class="page-title-action-text"><?php esc_html_e( 'Back to All Entries', 'wpforms' ); ?></span>
				</a>

				<div class="wpforms-admin-single-navigation">
					<div class="wpforms-admin-single-navigation-text">
						<?php
						printf(
							/* translators: %1$d - current number of entry, %2$d - total number of entries. */
							esc_html__( 'Entry %1$d of %2$d', 'wpforms' ),
							(int) $entry->entry_prev_count + 1,
							(int) $entry->entry_count
						);
						?>
					</div>
					<div class="wpforms-admin-single-navigation-buttons">
						<a
								href="<?php echo esc_url( $entry->entry_prev_url ); ?>"
								title="<?php esc_attr_e( 'Previous form entry', 'wpforms' ); ?>"
								id="wpforms-admin-single-navigation-prev-link"
								class="wpforms-btn-grey <?php echo sanitize_html_class( $entry->entry_prev_class ); ?>">
							<span class="dashicons dashicons-arrow-left-alt2"></span>
						</a>

						<span
								class="wpforms-admin-single-navigation-current"
								title="<?php esc_attr_e( 'Current form entry', 'wpforms' ); ?>">
								<?php echo (int) $entry->entry_prev_count + 1; ?>
						</span>

						<a
								href="<?php echo esc_url( $entry->entry_next_url ); ?>"
								title="<?php esc_attr_e( 'Next form entry', 'wpforms' ); ?>"
								id="wpforms-admin-single-navigation-next-link"
								class="wpforms-btn-grey <?php echo sanitize_html_class( $entry->entry_next_class ); ?>">
							<span class="dashicons dashicons-arrow-right-alt2"></span>
						</a>
					</div>

					<?php

						echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							'admin/entries/single-entry/settings',
							[ 'entry_view_settings' => $this->entry_view_settings ],
							true
						);
					?>
				</div>

			</h1>

			<div class="wpforms-admin-content">

				<div id="poststuff">

					<div id="post-body" class="metabox-holder columns-2">

						<!-- Left column -->
						<div id="post-body-content" style="position: relative;">
							<?php do_action( 'wpforms_entry_details_content', $entry, $form_data, $this ); ?>
						</div>

						<!-- Right column -->
						<div id="postbox-container-1" class="postbox-container">
							<?php do_action( 'wpforms_entry_details_sidebar', $entry, $form_data, $this ); ?>
						</div>

					</div>

				</div>

			</div>

		</div>
		<?php
	}

	/**
	 * Get entry display settings.
	 *
	 * @since 1.8.3
	 *
	 * @return array
	 */
	public static function get_entry_view_settings() {

		$defaults = [
			'fields'  => [
				'show_field_descriptions' => [
					'label' => esc_html__( 'Field Descriptions', 'wpforms' ),
					'value' => 0,
				],
				'show_empty_fields'       => [
					'label' => esc_html__( 'Empty Fields', 'wpforms' ),
					'value' => 1,
				],
				'show_unselected_choices' => [
					'label' => esc_html__( 'Unselected Choices', 'wpforms' ),
					'value' => 0,
				],
				'show_html_fields'        => [
					'label' => esc_html__( 'HTML/Content Fields', 'wpforms' ),
					'value' => 0,
				],
				'show_section_dividers'   => [
					'label' => esc_html__( 'Section Dividers', 'wpforms' ),
					'value' => 0,
				],
				'show_page_breaks'        => [
					'label' => esc_html__( 'Page Breaks', 'wpforms' ),
					'value' => 0,
				],
			],
			'display' => [
				'maintain_layouts' => [
					'label' => esc_html__( 'Maintain Layouts', 'wpforms' ),
					'value' => 0,
				],
				'compact_view'     => [
					'label' => esc_html__( 'Compact View', 'wpforms' ),
					'value' => 0,
				],
			],
		];

		return get_option( 'wpforms_entry_view_settings', $defaults );
	}



	/**
	 * Entry fields metabox.
	 *
	 * @since 1.1.5
	 *
	 * @param object $entry     Submitted entry values.
	 * @param array  $form_data Form data and settings.
	 */
	public function details_fields( $entry, $form_data ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		$form_title = isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : '';

		if ( empty( $form_title ) ) {
			$form = wpforms()->get( 'form' )->get( $entry->form_id );

			$form_title = ! empty( $form )
				? $form->post_title
				: sprintf( /* translators: %d - form ID. */
					esc_html__( 'Form (#%d)', 'wpforms' ),
					$entry->form_id
				);
		}

		?>
		<!-- Entry Fields metabox -->
		<div id="wpforms-entry-fields" class="postbox">

			<div class="postbox-header">
				<h2 class="hndle">
					<?php echo '1' === (string) $entry->starred ? '<span class="dashicons dashicons-star-filled"></span>' : ''; ?>
					<span><?php echo esc_html( $form_title ); ?></span>
				</h2>
			</div>

			<div class="inside">

				<?php

				$fields = apply_filters( 'wpforms_entry_single_data', wpforms_decode( $entry->fields ), $entry, $form_data );

				if ( empty( $fields ) ) {

					// Whoops, no fields! This shouldn't happen under normal use cases.
					echo '<p class="no-fields">' . esc_html__( 'This entry does not have any fields', 'wpforms' ) . '</p>';

				} else {

					add_filter( 'wp_kses_allowed_html', [ $this, 'modify_allowed_tags_entry_field_value' ], 10, 2 );

					// Content, Divider, HTML and layout fields must always be included because it's allowed to show and hide these fields.
					$forced_allowed_fields = [ 'content', 'divider', 'html', 'layout', 'pagebreak' ];

					$fields_layout = new WPForms_Field_Layout();
					$fields        = $this->add_formatted_data( $fields );
					$fields        = $fields_layout->filter_entries_print_preview_fields( $fields );
					$view          = $this->get_view_type();

					// Wrap the fields.
					echo '<div class="wpforms-entries-fields-wrapper' . esc_attr( $view ) . '">';

						// Display the fields and their values.
						foreach ( $fields as $field ) {

							if ( empty( $field['type'] ) ) {
								continue;
							}

							$field_type = $field['type'];

							if ( $field_type === 'pagebreak' && ! empty( $field['position'] ) && $field['position'] === 'bottom' ) {
								continue;
							}

							/** This filter is documented in /src/Pro/Admin/Entries/Edit.php */
							if ( ! (bool) apply_filters( "wpforms_pro_admin_entries_edit_is_field_displayable_{$field_type}", true, $field, $form_data ) && ! in_array( $field_type, $forced_allowed_fields, true ) ) { // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
								continue;
							}

							if ( $field_type === 'layout' ) {
								$this->print_layout_field( $field, $form_data );
							} else {

								$this->print_field( $field, $form_data );
							}
						}

					echo '</div>';
					remove_filter( 'wp_kses_allowed_html', [ $this, 'modify_allowed_tags_entry_field_value' ] );
				}
				?>

			</div>

		</div>
		<?php
	}

	/**
	 * Get view type for entries.
	 *
	 * @since 1.8.3
	 */
	public function get_view_type() {

		if ( $this->entry_view_settings['display']['compact_view']['value'] === 1 ) {
			return ' wpforms-entry-compact-layout';
		}

		if ( $this->entry_view_settings['display']['maintain_layouts']['value'] === 1 ) {
			return ' wpforms-entry-maintain-layout';
		}

		return '';

	}
	/**
	 * Prints fields for the entry.
	 *
	 * @since 1.8.3
	 *
	 * @param array $field     Field Data.
	 * @param array $form_data Form Data.
	 */
	public function print_field( $field, $form_data ) {

		// Get field default value.
		$field_value = isset( $field['value'] ) ? $field['value'] : '';
		$is_hidden   = $this->is_field_hidden( $field ); // If we should hide the field by default or not.

		// Set field value for HTML and Content fields.
		if ( in_array( $field['type'], [ 'html', 'content' ], true ) ) {

			$field_value = isset( $field['formatted_value'] ) ? $field['formatted_value'] : '';
		}

		/** This filter is documented in src/SmartTags/SmartTag/FieldHtmlId.php.*/
		$field_value = apply_filters( 'wpforms_html_field_value', wp_kses_post( $field_value ), $field, $form_data, 'entry-single' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		// Get field classes.
		$field_classes = $this->get_field_classes( $field, $field_value, $is_hidden );

		// Get field description.
		$field_description = isset( $form_data['fields'][ $field['id'] ]['description'] ) ? $form_data['fields'][ $field['id'] ]['description'] : '';

		echo '<div class="wpforms-entry-field-item ' . wpforms_sanitize_classes( $field_classes, true ) . '">';

			// Print the field label.
			$this->print_field_label( $field, $field_description );

			// Print the field value.
			$this->print_field_value( $field, $field_value );

			// Print the field meta data.
			$this->print_field_hidden_data( $field, $field_value );

		echo '</div>';
	}

	/**
	 * Check if field should be hidden by default or not.
	 *
	 * @since 1.8.3
	 *
	 * @param array $field Field data.
	 *
	 * @return boolean
	 */
	private function is_field_hidden( $field ) {

		return ( in_array( $field['type'], [ 'html', 'content' ], true ) && $this->entry_view_settings['fields']['show_html_fields']['value'] !== 1 ) ||
			( $field['type'] === 'divider' && $this->entry_view_settings['fields']['show_section_dividers']['value'] !== 1 ) ||
			( $field['type'] === 'pagebreak' && $this->entry_view_settings['fields']['show_page_breaks']['value'] !== 1 );
	}

	/**
	 * Print layout fields.
	 *
	 * @since 1.8.3
	 *
	 * @param array $field     Field data.
	 * @param array $form_data Form data.
	 */
	 private function print_layout_field( $field, $form_data ) {

		echo '<div class="wpforms-entry-field-layout">';
			foreach ( $field['columns'] as $column ) {

				$width = $this->get_layout_col_width( $column );

				echo '<div class="wpforms-entry-field-layout-inner wpforms-field-layout-column" style="width: ' . esc_attr( $width ) . '%">';

					foreach ( $column['fields'] as $child_field ) {
						$this->print_field( $child_field, $form_data );
					}

				echo '</div>';
			}
		echo '</div>';

	 }

	/**
	 * Get column width for the layout.
	 *
	 * @since 1.8.3
	 *
	 * @param array $column Column width data.
	 *
	 * @return int
	 */
	private function get_layout_col_width( $column ) {

		$preset_width = ! empty( $column['width_preset'] ) ? (int) $column['width_preset'] : 50;
		$custom_width = ! empty( $column['width_custom'] ) ? (int) $column['width_custom'] : 50;

		if ( $preset_width === 33 ) {
			$preset_width = 33.33333;
		} elseif ( $preset_width === 67 ) {
			$preset_width = 66.66666;
		}

		return min( $preset_width, $custom_width );
	}

	/**
	 * Prints field label.
	 *
	 * @since 1.8.3
	 *
	 * @param array  $field             Field Data.
	 * @param string $field_description Field Description.
	 */
	private function print_field_label( $field, $field_description = '' ) {

		$hide = $this->entry_view_settings['fields']['show_field_descriptions']['value'] === 1 ? '' : ' wpforms-hide';

		// Field name.
		echo '<p class="wpforms-entry-field-name">';
			/* translators: %d - field ID. */
			echo ! empty( $field['formatted_label'] )
				? esc_html( wp_strip_all_tags( $field['formatted_label'] ) )
				: sprintf( /* translators: %d - field ID. */
					esc_html__( 'Field ID #%d', 'wpforms' ),
					absint( $field['id'] )
				);
			echo ! empty( $field_description )
				? '<span class="wpforms-entry-field-description' . esc_attr( $hide ) . '">' . wp_kses_post( $field_description ) . '</span>'
				: '';
		echo '</p>';
	}

	/**
	 * Prints field meta.
	 *
	 * @since 1.8.3
	 *
	 * @param array $field Field Data.
	 */
	private function print_field_hidden_data( $field ) {

		$is_choices_field = $this->is_choice_field( $field['type'] );
		$hide_choices     = $this->entry_view_settings['fields']['show_unselected_choices']['value'] === 1 ? '' : ' wpforms-hide';

		if ( $is_choices_field ) {
			// Field choices.
			echo '<div class="wpforms-entry-field-value-is-choice' . esc_attr( $hide_choices ) . '">';
				echo wpforms_is_empty_string( $field['formatted_value'] )
					? esc_html__( 'Empty', 'wpforms' )
					: wpforms_esc_unselected_choices( $field['formatted_value'] );
			echo '</div>';
		}
	}

	/**
	 * Prints field value.
	 *
	 * @since 1.8.3
	 *
	 * @param array  $field       Field Data.
	 * @param string $field_value Field Value.
	 */
	private function print_field_value( $field, $field_value ) {

		if ( $this->is_structure_field( $field['type'] ) ) {
			return;
		}

		// Field value.
		// Formatted value for choices have unselected options so sort them out.
		$is_choices_field  = $this->is_choice_field( $field['type'] );
		$no_format         = $this->needs_unformatted_value( $field['type'] );
		$value             = $is_choices_field || $no_format ? $field_value : $field['formatted_value'];
		$hide_choice_value = $is_choices_field && $this->entry_view_settings['fields']['show_unselected_choices']['value'] === 1 ? ' wpforms-hide' : '';

		if ( $field['type'] === 'html' ) {
			$value = force_balance_tags( $value );
		}

		echo '<div class="wpforms-entry-field-value' . esc_attr( $hide_choice_value ) . '">';
			echo ! wpforms_is_empty_string( $value )
				? wp_kses_post( nl2br( make_clickable( $value ) ) )
				: esc_html__( 'Empty', 'wpforms' );
		echo '</div>';
	}

	/**
	 * Get field classes.
	 *
	 * @since 1.8.3
	 *
	 * @param array   $field       Field Data.
	 * @param string  $field_value Field Value.
	 * @param boolean $is_hidden   Hide or show the field.
	 *
	 * @return array
	 */
	private function get_field_classes( $field, $field_value, $is_hidden ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		if ( $this->is_structure_field( $field['type'] ) && $field['type'] !== 'layout' ) {
			$field_value = isset( $field['formatted_label'] ) ? $field['formatted_label'] : '';
		}

		$field_classes = [
			'field',
			'entry-field-item',
			"wpforms-field-{$field['type']}",
			"wpforms-field-entry-{$field['type']}",
		];

		if ( ! isset( $field_value ) || wpforms_is_empty_string( trim( $field_value ) ) ) {
			$field_classes[] = 'empty';
		}

		if ( ! $this->is_structure_field( $field['type'] ) ) {
			$field_classes[] = 'wpforms-field-entry-fields';
		}

		if ( $this->is_choice_field( $field['type'] ) ) {
			$field_classes[] = 'wpforms-field-entry-toggle';
		}

		if ( ( ! isset( $field_value ) || wpforms_is_empty_string( trim( $field_value ) ) ) && $this->entry_view_settings['fields']['show_empty_fields']['value'] !== 1 ) {
			$field_classes[] = 'wpforms-hide';
		}

		if ( $is_hidden ) {
			$field_classes[] = 'wpforms-hide';
		}

		return $field_classes;
	}

	/**
	 * Allow additional tags for the wp_kses_post function.
	 *
	 * @since 1.7.1
	 *
	 * @param array  $allowed_html List of allowed HTML.
	 * @param string $context      Context name.
	 *
	 * @return array
	 */
	public function modify_allowed_tags_entry_field_value( $allowed_html, $context ) {

		if ( $context !== 'post' ) {
			return $allowed_html;
		}

		$allowed_html['iframe'] = [
			'data-src' => [],
			'class'    => [],
		];

		return $allowed_html;
	}

	/**
	 * Entry notes metabox.
	 *
	 * @since 1.1.6
	 *
	 * @param object $entry     Submitted entry values.
	 * @param array  $form_data Form data and settings.
	 */
	public function details_notes( $entry, $form_data ) {

		$action_url = add_query_arg(
			[
				'page'     => 'wpforms-entries',
				'view'     => 'details',
				'entry_id' => absint( $entry->entry_id ),
			],
			admin_url( 'admin.php' )
		);
		$form_id    = ! empty( $form_data['id'] ) ? $form_data['id'] : $entry->form_id;
		?>
		<!-- Entry Notes metabox -->
		<div id="wpforms-entry-notes" class="postbox">

			<div class="postbox-header">
				<h2 class="hndle">
					<span><?php esc_html_e( 'Notes', 'wpforms' ); ?></span>
				</h2>
			</div>

			<div class="inside">

				<?php if ( wpforms_current_user_can( 'edit_entries_form_single', $form_id ) ) : ?>

					<div class="wpforms-entry-notes-new">

						<a href="#" class="button add"><?php esc_html_e( 'Add Note', 'wpforms' ); ?></a>

						<form action="<?php echo esc_url( $action_url ); ?>" method="post">
							<?php
							$args = [
								'media_buttons' => false,
								'editor_height' => 50,
								'teeny'         => true,
							];

							wp_editor( '', 'entry_note', $args );
							wp_nonce_field( 'wpforms_entry_details_addnote' );
							?>
							<input type="hidden" name="entry_id" value="<?php echo absint( $entry->entry_id ); ?>">
							<input type="hidden" name="form_id" value="<?php echo absint( $form_id ); ?>">
							<div class="btns">
								<input type="submit" name="wpforms_add_note" class="save button-primary alignright" value="<?php esc_attr_e( 'Add Note', 'wpforms' ); ?>">
								<a href="#" class="cancel button-secondary alignleft"><?php esc_html_e( 'Cancel', 'wpforms' ); ?></a>
							</div>
						</form>

					</div>
				<?php endif; ?>

				<?php
				if ( empty( $entry->entry_notes ) ) {
					echo '<p class="no-notes">' . esc_html__( 'No notes.', 'wpforms' ) . '</p>';
				} else {
					echo '<div class="wpforms-entry-notes-list">';
					$count = 1;
					foreach ( $entry->entry_notes as $note ) {
						$user        = get_userdata( $note->user_id );
						$user_name   = ! empty( $user->display_name ) ? $user->display_name : $user->user_login;
						$user_url    = add_query_arg(
							[
								'user_id' => absint( $user->ID ),
							],
							admin_url( 'user-edit.php' )
						);

						$date  = wpforms_datetime_format( $note->date, '', true );
						$class = 0 === $count % 2 ? 'even' : 'odd';

						if ( \wpforms_current_user_can( 'edit_entries_form_single', $form_data['id'] ) ) {

							$delete_url = wp_nonce_url(
								add_query_arg(
									[
										'page'     => 'wpforms-entries',
										'view'     => 'details',
										'entry_id' => absint( $entry->entry_id ),
										'note_id'  => absint( $note->id ),
										'action'   => 'delete_note',
									],
									admin_url( 'admin.php' )
								),
								'wpforms_entry_details_deletenote'
							);
						}
						?>
						<div class="wpforms-entry-notes-single <?php echo esc_attr( $class ); ?>">
							<div class="wpforms-entry-notes-byline">
								<?php
								printf(
									/* translators: %1$s - user name, %2$s - date. */
									esc_html__( 'Added by %1$s on %2$s', 'wpforms' ),
									'<a href="' . esc_url( $user_url ) . '" class="note-user">' . esc_html( $user_name ) . '</a>',
									esc_html( $date )
								);
								?>
								<?php if ( ! empty( $delete_url ) ) : ?>
									<span class="sep">|</span>
									<a href="<?php echo esc_url( $delete_url ); ?>" class="note-delete">
										<?php echo esc_html( _x( 'Delete', 'Entry: note', 'wpforms' ) ); ?>
									</a>
								<?php endif; ?>
							</div>
							<?php echo wp_kses_post( wp_unslash( $note->data ) ); ?>
						</div>
						<?php
						$count++;
					}
					echo '</div>';
				}
				?>

			</div>

		</div>
		<?php
	}

	/**
	 * Entry log metabox.
	 *
	 * @since 1.5.7
	 *
	 * @param object $entry     Submitted entry values.
	 * @param array  $form_data Form data and settings.
	 */
	public function details_log( $entry, $form_data ) {

		?>
		<!-- Entry Logs metabox -->
		<div id="wpforms-entry-logs" class="postbox">

			<div class="postbox-header">
				<h2 class="hndle">
					<span><?php esc_html_e( 'Log', 'wpforms' ); ?></span>
				</h2>
			</div>

			<div class="inside">

				<?php
				if ( empty( $entry->entry_logs ) ) {
					echo '<p class="no-logs">' . esc_html__( 'No logs.', 'wpforms' ) . '</p>';
				} else {
					echo '<div class="wpforms-entry-logs-list">';
					$count = 1;

					foreach ( $entry->entry_logs as $log ) {
						$user      = get_userdata( $log->user_id );
						$user_name = ! empty( $user->display_name ) ? $user->display_name : $user->user_login;
						$user_url  = add_query_arg(
							[
								'user_id' => absint( $user->ID ),
							],
							admin_url( 'user-edit.php' )
						);
						$date      = wpforms_datetime_format( $log->date, '', true );
						$class     = $count % 2 === 0 ? 'even' : 'odd';
						?>

						<div class="wpforms-entry-logs-single <?php echo esc_attr( $class ); ?>">
							<div class="wpforms-entry-logs-byline">
								<?php
								printf(
									/* translators: %1$s - user name, %2$s - date. */
									esc_html__( 'Added by %1$s on %2$s', 'wpforms' ),
									'<a href="' . esc_url( $user_url ) . '" class="log-user">' . esc_html( $user_name ) . '</a>',
									esc_html( $date )
								);
								?>
							</div>
							<?php echo wp_kses_post( wp_unslash( $log->data ) ); ?>
						</div>
						<?php
						$count++;
					}
					echo '</div>';
				}
				?>

			</div>

		</div>
		<?php
	}

	/**
	 * Entry debug metabox. Hidden by default obviously.
	 *
	 * @since 1.1.6
	 *
	 * @param object $entry     Submitted entry values.
	 * @param array  $form_data Form data and settings.
	 */
	public function details_debug( $entry, $form_data ) {

		if ( ! wpforms_debug() ) {
			return;
		}

		/** This filter is documented in /includes/functions.php */
		$allow_display = apply_filters( 'wpforms_debug_data_allow_display', true ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		if ( ! $allow_display ) {
			return;
		}

		?>
		<!-- Entry Debug metabox -->
		<div id="wpforms-entry-debug" class="postbox">

			<div class="postbox-header">
				<h2 class="hndle">
					<span><?php esc_html_e( 'Debug Information', 'wpforms' ); ?></span>
				</h2>
			</div>

			<div class="inside">

				<?php wpforms_debug_data( $entry ); ?>
				<?php wpforms_debug_data( $form_data ); ?>

			</div>

		</div>
		<?php
	}

	/**
	 * Entry Meta Details metabox.
	 *
	 * @since 1.1.5
	 *
	 * @param object $entry     Entry data.
	 * @param array  $form_data Form data.
	 */
	public function details_meta( $entry, $form_data ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$datetime = static function ( $date ) {
			return sprintf( /* translators: %1$s - formatted date, %2$s - formatted time. */
				__( '%1$s at %2$s', 'wpforms' ),
				wpforms_date_format( $date, 'M j, Y', true ),
				wpforms_time_format( $date, '', true )
			);
		};
		?>

		<!-- Entry Details metabox -->
		<div id="wpforms-entry-details" class="postbox">

			<div class="postbox-header">
				<h2 class="hndle">
					<span><?php esc_html_e( 'Entry Details', 'wpforms' ); ?></span>
				</h2>
			</div>

			<div class="inside">

				<div class="wpforms-entry-details-meta">

					<p class="wpforms-entry-id">
						<span class="dashicons dashicons-admin-network"></span>
						<?php
						printf(
							/* translators: %s - entry ID. */
							esc_html__( 'Entry ID: %s', 'wpforms' ),
							'<strong>' . absint( $entry->entry_id ) . '</strong>'
						);
						?>

					</p>

					<?php
					if ( ! empty( $entry->post_id ) && is_object( get_post( $entry->post_id ) ) ) :
						$entry_post_id  = absint( $entry->post_id );
						$entry_post_obj = get_post_type_object( get_post_type( $entry_post_id ) );

						if ( $entry_post_obj instanceof \WP_Post_Type ) {
							?>
							<p class="wpforms-entry-postid">
								<span class="dashicons dashicons-edit"></span>
								<?php
								printf( /* translators: %1$s - post type name, %2$s - post type ID. */
									esc_html__( '%1$s ID: %2$s', 'wpforms' ),
									esc_html( $entry_post_obj->labels->singular_name ),
									'<strong><a href="' . esc_url( get_edit_post_link( $entry_post_id ) ) . '" target="_blank">' . $entry_post_id . '</a></strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								);
								?>
							</p>
						<?php } ?>
					<?php endif; ?>

					<p class="wpforms-entry-date">
						<span class="dashicons dashicons-calendar"></span>
						<?php esc_html_e( 'Submitted:', 'wpforms' ); ?>
						<strong class="date-time">
							<?php echo esc_html( $datetime( $entry->date ) ); ?>
						</strong>
					</p>

					<?php if ( $entry->date_modified !== '0000-00-00 00:00:00' ) : ?>
						<p class="wpforms-entry-modified">
							<span class="dashicons dashicons-calendar-alt"></span>
							<?php esc_html_e( 'Modified:', 'wpforms' ); ?>
							<strong class="date-time">
								<?php echo esc_html( $datetime( $entry->date_modified ) ); ?>
							</strong>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $entry->user_id ) && 0 !== (int) $entry->user_id ) : ?>
						<p class="wpforms-entry-user">
							<span class="dashicons dashicons-admin-users"></span>
							<?php
							esc_html_e( 'User:', 'wpforms' );
							$user      = get_userdata( $entry->user_id );
							$user_name = esc_html( ! empty( $user->display_name ) ? $user->display_name : $user->user_login );
							$user_url  = add_query_arg(
								[
									'user_id' => absint( $user->ID ),
								],
								admin_url( 'user-edit.php' )
							);
							?>
							<strong><a href="<?php echo esc_url( $user_url ); ?>"><?php echo esc_html( $user_name ); ?></a></strong>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $entry->ip_address ) ) : ?>
						<p class="wpforms-entry-ip">
							<span class="dashicons dashicons-location"></span>
							<?php esc_html_e( 'User IP:', 'wpforms' ); ?>
							<strong><?php echo esc_html( $entry->ip_address ); ?></strong>
						</p>
					<?php endif; ?>

					<?php if ( apply_filters( 'wpforms_entry_details_sidebar_details_status', false, $entry, $form_data ) ) : ?>
						<p class="wpforms-entry-type">
							<span class="dashicons dashicons-category"></span>
							<?php esc_html_e( 'Type:', 'wpforms' ); ?>
							<strong><?php echo ! empty( $entry->status ) && $entry->type !== 'payment' ? esc_html( ucwords( sanitize_text_field( $entry->status ) ) ) : esc_html__( 'Completed', 'wpforms' ); ?></strong>
						</p>
					<?php endif; ?>

					<?php do_action( 'wpforms_entry_details_sidebar_details', $entry, $form_data ); ?>

				</div>

				<div id="major-publishing-actions">

					<?php
						do_action( 'wpforms_entry_details_sidebar_details_action',  $entry, $form_data );
					?>

					<?php
					$form_id = ! empty( $form_data['id'] ) ? $form_data['id'] : $entry->form_id;

					if ( wpforms_current_user_can( 'delete_entries_form_single', $form_id ) ) :
					?>
						<div id="delete-action">
							<?php

							$trash_link = wp_nonce_url(
								add_query_arg(
									[
										'view'     => 'list',
										'action'   => 'trash',
										'form_id'  => $form_id,
										'entry_id' => $entry->entry_id,
									]
								),
								'bulk-entries'
							);
							?>
							<a class="trash" href="<?php echo esc_url( $trash_link ); ?>">
								<?php esc_html_e( 'Trash Entry', 'wpforms' ); ?>
							</a>
						</div>
					<?php endif; ?>

					<div class="clear"></div>
				</div>

			</div>

		</div>
		<?php
	}

	/**
	 * Entry Payment Details metabox.
	 *
	 * @since 1.2.6
	 * @since 1.8.2 Use payment info from payment tables.
	 *
	 * @param object $entry     Submitted entry values.
	 * @param array  $form_data Form data and settings.
	 */
	public function details_payment( $entry, $form_data ) {

		if ( empty( $entry->type ) || $entry->type !== 'payment' ) {
			return;
		}

		$payment = wpforms()->get( 'payment' )->get_by( 'entry_id', $entry->entry_id );

		if ( ! $payment ) {
			return;
		}

		$allowed_types    = ValueValidator::get_allowed_types();
		$allowed_gateways = ValueValidator::get_allowed_gateways();
		$allowed_statuses = ValueValidator::get_allowed_statuses();
		$placeholder      = __( 'N/A', 'wpforms' );

		$payment_type         = isset( $payment->type, $allowed_types[ $payment->type ] ) ? $allowed_types[ $payment->type ] : $placeholder;
		$payment_gateway      = isset( $payment->gateway, $allowed_gateways[ $payment->gateway ] ) ? $allowed_gateways[ $payment->gateway ] : $placeholder;
		$payment_status       = isset( $payment->status, $allowed_statuses[ $payment->status ] ) ? $allowed_statuses[ $payment->status ] : $placeholder;
		$payment_total        = ! empty( $payment->total_amount ) ? wpforms_format_amount( wpforms_sanitize_amount( $payment->total_amount, $payment->currency ), true, $payment->currency ) : $placeholder;
		$payment_subscription = Helpers::get_subscription_description( $payment->id, $payment_total );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wpforms_render(
			'admin/entries/single-entry/payment-details',
			[
				'payment'              => $payment,
				'payment_status'       => $payment_status,
				'payment_type'         => $payment_type,
				'payment_gateway'      => $payment_gateway,
				'payment_total'        => $payment_total,
				'payment_subscription' => $payment_subscription,
				'payment_url'          => add_query_arg(
					[
						'page'       => 'wpforms-payments',
						'view'       => 'payment',
						'payment_id' => absint( $payment->id ),
					],
					admin_url( 'admin.php' )
				),
				'entry'                => $entry,
				'form_data'            => $form_data,
				'show_button'          => wpforms_current_user_can() && $payment->is_published,
			],
			true
		);
	}

	/**
	 * Entry Actions metabox.
	 *
	 * @since 1.1.5
	 *
	 * @param object $entry     Submitted entry values.
	 * @param array  $form_data Form data and settings.
	 */
	public function details_actions( $entry, $form_data ) {

		/**
		 * Filters whether to allow the entry details actions.
		 *
		 * @since 1.8.3
		 *
		 * @param bool   $disable_details_actions Whether to disable the entry details actions.
		 * @param object $entry                   Submitted entry values.
		 * @param array  $form_data               Form data and settings.
		 */
		if ( apply_filters( 'wpforms_entries_single_details_actions_disable', false, $entry, $form_data ) ) {
			return;
		}

		$entry->starred  = (string) $entry->starred;
		$entry->entry_id = (int) $entry->entry_id;
		$form_id         = ! empty( $form_data['id'] ) ? $form_data['id'] : $entry->form_id;

		$base = add_query_arg(
			[
				'page'     => 'wpforms-entries',
				'view'     => 'details',
				'entry_id' => $entry->entry_id,
			],
			admin_url( 'admin.php' )
		);

		// Print Entry URL.
		$print_url = add_query_arg(
			[
				'page'     => 'wpforms-entries',
				'view'     => 'print',
				'entry_id' => $entry->entry_id,
			],
			admin_url( 'admin.php' )
		);

		// Star Entry URL.
		$star_url  = wp_nonce_url(
			add_query_arg(
				[
					'action' => '1' === $entry->starred ? 'unstar' : 'star',
					'form'   => absint( $form_id ),
				],
				$base
			),
			'wpforms_entry_details_star'
		);
		$star_icon = '1' === $entry->starred ? 'dashicons-star-empty' : 'dashicons-star-filled';
		$star_text = '1' === $entry->starred ? esc_html__( 'Unstar', 'wpforms' ) : esc_html__( 'Star', 'wpforms' );

		// Unread URL.
		$unread_url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'unread',
					'form'   => (int) $form_id,
				],
				$base
			),
			'wpforms_entry_details_unread'
		);

		$action_links = [];

		$action_links['print']         = [
			'url'    => $print_url,
			'target' => 'blank',
			'icon'   => 'dashicons-media-text',
			'label'  => esc_html__( 'Print', 'wpforms' ),
		];
		$action_links['export']        = [
			'url'   => $this->get_export_url( (int) $form_id, $entry->entry_id, 'csv' ),
			'icon'  => 'dashicons-migrate',
			'label' => esc_html__( 'Export (CSV)', 'wpforms' ),
		];
		$action_links['export_xlsx']   = [
			'url'   => $this->get_export_url( (int) $form_id, $entry->entry_id, 'xlsx' ),
			'icon'  => 'dashicons-media-spreadsheet',
			'label' => esc_html__( 'Export (XLSX)', 'wpforms' ),
		];

		// If notifications are enabled, add the notifications action.
		if ( ! empty( $form_data['settings']['notification_enable'] ) ) {
			$action_links['notifications'] = $this->add_notifications_action( $base, $form_data );
		}

		if ( (string) $entry->viewed === '1' ) {
			$action_links['read'] = [
				'url'   => $unread_url,
				'icon'  => 'dashicons-hidden',
				'label' => esc_html__( 'Mark Unread', 'wpforms' ),
			];
		}
		$action_links['star'] = [
			'url'   => $star_url,
			'icon'  => $star_icon,
			'label' => $star_text,
		];

		$action_links = apply_filters( 'wpforms_entry_details_sidebar_actions_link', $action_links, $entry, $form_data );

		$delete_link = wp_nonce_url(
			add_query_arg(
				[
					'view'     => 'list',
					'action'   => 'delete',
					'form_id'  => $form_id,
					'entry_id' => $entry->entry_id,
				]
			),
			'bulk-entries'
		);

		if ( wpforms_current_user_can( 'delete_entries_form_single', $form_id ) ) {

			$action_links['delete'] = [
				'url'   => $delete_link,
				'icon'  => 'dashicons-trash',
				'label' => esc_html__( 'Delete Entry', 'wpforms' ),
			];
		}

		?>

		<!-- Entry Actions metabox -->
		<div id="wpforms-entry-actions" class="postbox">

			<div class="postbox-header">
				<h2 class="hndle">
					<span><?php esc_html_e( 'Actions', 'wpforms' ); ?></span>
				</h2>
			</div>

			<div class="inside">

				<div class="wpforms-entry-actions-meta">

					<?php
					foreach ( $action_links as $slug => $link ) {

						$link_is_disabled = isset( $link['disabled'] ) && ! empty( $link['disabled_by'] ) && is_array( $link['disabled_by'] ) && $link['disabled'];

						if ( $link_is_disabled ) {

							$title = sprintf( /* translators: %s - a list of addons that disable the link. */
								_n(
									'Unavailable because %s is enabled.',
									'Unavailable because %s are enabled.',
									count( $link['disabled_by'] ),
									'wpforms'
								),
								wpforms_conjunct( $link['disabled_by'], __( 'and', 'wpforms' ) )
							);

							printf(
								'<p class="wpforms-entry-%s" title="%s"><span class="dashicons %s"></span>%s</p>',
								esc_attr( $slug ),
								esc_attr( $title ),
								esc_attr( $link['icon'] ),
								esc_html( $link['label'] )
							);

						} else {

							printf(
								'<p class="wpforms-entry-%s"><a href="%s" %s><span class="dashicons %s"></span>%s</a></p>',
								esc_attr( $slug ),
								esc_url( $link['url'] ),
								! empty( $link['target'] ) ? 'target="_blank" rel="noopener noreferrer"' : '',
								esc_attr( $link['icon'] ),
								esc_html( $link['label'] )
							);

						}
					}

					do_action( 'wpforms_entry_details_sidebar_actions', $entry, $form_data );
					?>

				</div>

			</div>

		</div>
		<?php
	}

	/**
	 * Notifications action.
	 *
	 * @since 1.8.3
	 *
	 * @param string $base      The admin URL.
	 * @param array  $form_data Form data and settings.
	 *
	 * @return array The notifications action data.
	 */
	private function add_notifications_action( $base, $form_data ) {

		$notifications_url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'notifications',
				],
				$base
			),
			'wpforms_entry_details_notifications'
		);

		$action_data = [
			'url'   => $notifications_url,
			'icon'  => 'dashicons-email-alt',
			'label' => esc_html__( 'Resend Notifications', 'wpforms' ),
		];

		$notifications = $form_data['settings']['notifications'];

		// No notifications or payments? Bail.
		if ( empty( $notifications ) || empty( $form_data['payments'] ) ) {
			return $action_data;
		}

		// Loop over payment add-ons.
		foreach ( $form_data['payments'] as $slug => $settings ) {

			foreach ( $notifications as $notification ) {

				// Check "completed payments" setting.
				if ( empty( $notification[ $slug ] ) ) {
					continue;
				}

				// If completed payments are enabled, disable the action.
				$action_data['disabled']      = true;
				$action_data['disabled_by'][] = sprintf( /* translators: %s - payment add-on name. */
					esc_html__( 'the "%s completed payments" setting', 'wpforms' ),
					esc_html( ucfirst( $slug ) )
				);

				return $action_data;
			}
		}

		return $action_data;
	}

	/**
	 * Get Export URL.
	 *
	 * @since 1.6.5
	 *
	 * @param int    $form_id  Form ID.
	 * @param int    $entry_id Entry ID.
	 * @param string $type     Export type.
	 *
	 * @return string
	 */
	private function get_export_url( $form_id, $entry_id, $type ) {

		return wp_nonce_url(
			add_query_arg(
				[
					'page'     => 'wpforms-tools',
					'view'     => 'export',
					'action'   => 'wpforms_tools_single_entry_export_download',
					'form'     => $form_id,
					'entry_id' => $entry_id,
					'export_options' => [ $type ],
				],
				admin_url( 'admin.php' )
			),
			'wpforms-tools-single-entry-export-nonce',
			'nonce'
		);
	}

	/**
	 * Entry Related Entries metabox.
	 *
	 * @since 1.3.3
	 *
	 * @param object $entry     Submitted entry values.
	 * @param array  $form_data Form data and settings.
	 */
	public function details_related( $entry, $form_data ) {

		// Only display if we have related entries.
		if ( empty( $entry->entry_related ) ) {
			return;
		}
		?>

		<!-- Entry Actions metabox -->
		<div id="wpforms-entry-related" class="postbox">

			<div class="postbox-header">
				<h2 class="hndle">
					<span><?php esc_html_e( 'Related Entries', 'wpforms' ); ?></span>
				</h2>
			</div>

			<div class="inside">

				<p><?php esc_html_e( 'The user who created this entry also submitted the entries below.', 'wpforms' ); ?></p>

				<ul>
				<?php
				foreach ( $entry->entry_related as $related ) {
					$url = add_query_arg(
						[
							'page'     => 'wpforms-entries',
							'view'     => 'details',
							'entry_id' => absint( $related->entry_id ),
						],
						admin_url( 'admin.php' )
					);

					echo '<li>';
						echo '<a href="' . esc_url( $url ) . '">' . esc_html( wpforms_datetime_format( $related->date, '', true ) ) . '</a> ';
						echo $related->status === 'abandoned' ? esc_html__( '(Abandoned)', 'wpforms' ) : '';
					echo '</li>';
				}
				?>
				</ul>

			</div>

		</div>

		<?php
	}

	/**
	 * Add notices and errors.
	 *
	 * @since 1.6.7.1
	 */
	public function register_alerts() {

		if ( empty( $this->alerts ) ) {
			return;
		}

		foreach ( $this->alerts as $alert ) {
			$type = ! empty( $alert['type'] ) ? $alert['type'] : 'info';

			\WPForms\Admin\Notice::add( $alert['message'], $type );

			if ( ! empty( $alert['abort'] ) ) {
				$this->abort = true;

				break;
			}
		}
	}

	/**
	 * Display admin notices and errors.
	 *
	 * @since 1.1.6
	 * @deprecated 1.6.7.1
	 *
	 * @param mixed $display Type(s) of the notice.
	 * @param bool  $wrap    Whether to output the wrapper.
	 */
	public function display_alerts( $display = '', $wrap = false ) {

		_deprecated_function( __METHOD__, '1.6.7.1 of the WPForms plugin' );

		if ( empty( $this->alerts ) ) {
			return;
		}

		$display = empty( $display ) ?
			[ 'error', 'info', 'warning', 'success' ] :
			(array) $display;

		foreach ( $this->alerts as $alert ) {

			$type = ! empty( $alert['type'] ) ? $alert['type'] : 'info';

			if ( ! in_array( $type, $display, true ) ) {
				continue;
			}

			$classes = [ 'notice', 'notice-' . $type ];

			if ( ! empty( $alert['dismiss'] ) ) {
				$classes[] = 'is-dismissible';
			}

			$output = $wrap ?
				'<div class="wrap"><div class="%1$s"><p>%2$s</p></div></div>' :
				'<div class="%1$s"><p>%2$s</p></div>';

			printf(
				$output, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_attr( implode( ' ', $classes ) ),
				$alert['message'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}
	}

	/**
	 * Add formatted data to the field.
	 *
	 * @since 1.8.3
	 *
	 * @param array $fields Entry fields.
	 *
	 * @return array
	 */
	private function add_formatted_data( $fields ) {

		$this->form_data = wpforms_decode( $this->form->post_content );

		foreach ( $fields as $key => $field ) {
			if ( $field['type'] !== 'layout' ) {
				$field['formatted_value'] = $this->get_formatted_field_value( $field );
				$field['formatted_label'] = $this->get_formatted_field_label( $field );
				$fields[ $key ]           = $field;
			}
		}

		return $fields;
	}


	/**
	 * Get formatted field value.
	 *
	 * @since 1.8.3
	 *
	 * @param array $field Entry field.
	 *
	 * @return string
	 */
	private function get_formatted_field_value( $field ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$field_value = isset( $field['value'] ) ? wp_strip_all_tags( $field['value'] ) : '';

		if ( $field['type'] === 'html' ) {
			return isset( $field['code'] ) ? $field['code'] : '';
		}

		if ( $field['type'] === 'content' ) {
			return isset( $field['content'] ) ? $field['content'] : '';
		}

		if (
			! empty( $this->form_data['fields'][ $field['id'] ]['choices'] )
			&& $this->is_choice_field( $field['type'] )
		) {
			return $this->get_choices_field_value( $field, $field_value );
		}

		return $field_value;
	}

	/**
	 * Check if the field type is a choice field.
	 *
	 * @since 1.8.3
	 *
	 * @param string $type Field type.
	 *
	 * @return boolean
	 */
	private function is_choice_field( $type = '' ) {

		return in_array( $type, [ 'radio', 'checkbox', 'payment-checkbox', 'payment-multiple' ], true );
	}

	/**
	 * Check if the field type needs unfromatted value.
	 *
	 * @since 1.8.3
	 *
	 * @param string $type Field type.
	 *
	 * @return boolean
	 */
	private function needs_unformatted_value( $type = '' ) {

		return in_array( $type, [ 'richtext', 'file-upload', 'rating', 'signature', 'payment-coupon', 'number-slider' ], true );
	}

	/**
	 * Check if the field type is a divider field.
	 *
	 * @since 1.8.3
	 *
	 * @param string $type Field type.
	 *
	 * @return boolean
	 */
	private function is_structure_field( $type = '' ) {

		return in_array( $type, [ 'divider', 'pagebreak', 'layout' ], true );
	}

	/**
	 * Get formatted field label.
	 *
	 * @since 1.8.3
	 *
	 * @param array $field Entry field.
	 *
	 * @return string
	 */
	private function get_formatted_field_label( $field ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$field_label = isset( $field['name'] ) ? $field['name'] : '';

		if ( $field['type'] === 'divider' ) {
			return isset( $field['label'] ) && ! wpforms_is_empty_string( $field['label'] ) ? $field['label'] : esc_html__( 'Section Divider', 'wpforms' );
		}

		if ( $field['type'] === 'pagebreak' ) {
			return isset( $field['title'] ) && ! wpforms_is_empty_string( $field['title'] ) ? $field['title'] : esc_html__( 'Page Break', 'wpforms' );
		}

		if ( $field['type'] === 'content' ) {
			return esc_html__( 'Content Field', 'wpforms' );
		}

		return $field_label;
	}

	/**
	 * Get field value for checkbox and radio fields.
	 *
	 * @since 1.8.3
	 *
	 * @param array  $field       Entry field.
	 * @param string $field_value HTML markup for the field.
	 *
	 * @return string
	 */
	private function get_choices_field_value( $field, $field_value ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$choices_html    = '';
		$choices         = $this->form_data['fields'][ $field['id'] ]['choices'];
		$type            = in_array( $field['type'], [ 'radio', 'payment-multiple' ], true ) ? 'radio' : 'checkbox';
		$is_image_choice = ! empty( $this->form_data['fields'][ $field['id'] ]['choices_images'] );
		$template_name   = $is_image_choice ? 'image-choice' : 'choice';
		$is_dynamic      = ! empty( $field['dynamic'] );

		if ( $is_dynamic ) {
			$field_id   = $field['id'];
			$form_id    = $this->form_data['id'];
			$field_data = $this->form_data['fields'][ $field_id ];
			$choices    = wpforms_get_field_dynamic_choices( $field_data, $form_id, $this->form_data );
		}

		$layout        = isset( $this->form_data['fields'][ $field['id'] ]['input_columns'] ) && ! empty( $this->form_data['fields'][ $field['id'] ]['input_columns'] ) ? $this->form_data['fields'][ $field['id'] ]['input_columns'] : '1';
		$choices_html .= '<div class="wpforms-entry-choice-wrapper wpforms-entry-choice-column-' . $layout . '" >';

		foreach ( $choices as $key => $choice ) {
			$is_checked = $this->is_checked_choice( $field, $choice, $key, $is_dynamic );

			if ( ! $is_dynamic ) {
				$choice['label'] = $this->get_choice_label( $field, $choice, $key );
			}

			$choices_html .= wpforms_render(
				'admin/entries/single-entry/' . $template_name,
				[
					'choice_type' => $type,
					'is_checked'  => $is_checked,
					'choice'      => $choice,
				],
				true
			);
		}
		$choices_html .= '</div>';

		return $choices_html;
	}

	/**
	 * Get value for a choice item.
	 *
	 * @since 1.8.3
	 *
	 * @param array $field  Entry field.
	 * @param array $choice Choice settings.
	 * @param int   $key    Choice number.
	 *
	 * @return string
	 */
	private function get_choice_label( $field, $choice, $key ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$is_payment = strpos( $field['type'], 'payment-' ) === 0;

		if ( ! $is_payment ) {
			return ! isset( $choice['label'] ) || wpforms_is_empty_string( $choice['label'] )
				/* translators: %s - choice number. */
				? sprintf( esc_html__( 'Choice %s', 'wpforms' ), $key )
				: $choice['label'];
		}

		$label = $choice['label'] ?? '';
		/* translators: %s - item number. */
		$label = $label !== '' ? $label : sprintf( esc_html__( 'Item %s', 'wpforms' ), $key );

		if ( empty( $this->form_data['fields'][ $field['id'] ]['show_price_after_labels'] ) ) {
			return $label;
		}

		$value    = $choice['value'] ?? 0;
		$currency = $field['currency'] ?? '';
		$amount   = wpforms_format_amount( wpforms_sanitize_amount( $value, $currency ), true, $currency );

		return $amount ? $label . ' - ' . $amount : $label;
	}

	/**
	 * Is the choice item checked?
	 *
	 * @since 1.8.3
	 *
	 * @param array $field      Entry field.
	 * @param array $choice     Choice settings.
	 * @param int   $key        Choice number.
	 * @param bool  $is_dynamic Is dynamic field.
	 *
	 * @return bool
	 */
	private function is_checked_choice( $field, $choice, $key, $is_dynamic ) {

		$is_payment     = strpos( $field['type'], 'payment-' ) === 0;
		$separator      = $is_payment || $is_dynamic ? ',' : "\n";
		$active_choices = explode( $separator, $field['value_raw'] );

		if ( $is_dynamic ) {
			$active_choices = array_map( 'absint', $active_choices );

			return in_array( $choice['value'], $active_choices, true );
		}

		if ( $is_payment ) {
			$active_choices = array_map( 'absint', $active_choices );

			return in_array( $key, $active_choices, true );
		}

		$label = ! isset( $choice['label'] ) || wpforms_is_empty_string( $choice['label'] )
			/* translators: %s - choice number. */
			? sprintf( esc_html__( 'Choice %s', 'wpforms' ), $key )
			: sanitize_text_field( $choice['label'] );

		return in_array( $label, $active_choices, true );
	}

	/**
	 * Add HTML entries, dividers to entry.
	 *
	 * @since 1.8.3
	 *
	 * @param array  $fields    Form fields.
	 * @param object $entry     Entry fields.
	 * @param object $form_data Form data.
	 *
	 * @return array
	 */
	public function add_hidden_data( $fields, $entry, $form_data ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$settings = ! empty( $form_data['fields'] ) ? $form_data['fields'] : [];

		// Content, Divider, HTML and layout fields must always be included because it's allowed to show and hide these fields.
		$forced_allowed_fields = [ 'content', 'divider', 'html', 'layout', 'pagebreak' ];

		// First order settings field and remove fields that we dont need.
		foreach ( $settings as $key => $setting ) {

			if ( empty( $setting['type'] ) ) {
				unset( $settings[ $key ] );
				continue;
			}

			$field_type = $setting['type'];

			if ( in_array( $field_type, $forced_allowed_fields, true ) ) {
				continue;
			}

			// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
			/** This filter is documented in /src/Pro/Admin/Entries/Edit.php */
			if ( ! (bool) apply_filters( "wpforms_pro_admin_entries_edit_is_field_displayable_{$field_type}", true, $setting, $form_data ) ) {
				unset( $settings[ $key ] );
				continue;
			}
			// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

			if ( ! isset( $fields[ $key ] ) ) {
				unset( $settings[ $key ] );
				continue;
			}

			$settings[ $key ] = $fields[ $key ];
		}

		// Second, add fields that might have been removed on the form but are still tied to the entry.
		foreach ( $fields as $key => $field ) {

			if ( ! isset( $settings[ $key ] ) ) {
				$settings[ $key ] = $field;
			}
		}

		return $settings;
	}
}

new WPForms_Entries_Single();
