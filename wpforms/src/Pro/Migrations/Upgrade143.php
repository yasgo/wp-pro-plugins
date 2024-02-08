<?php

namespace WPForms\Pro\Migrations;

use WPForms\Admin\Notice;
use WPForms\Migrations\UpgradeBase;

/**
 * Class v1.4.3 upgrade for Pro.
 *
 * @since 1.7.5
 *
 * @noinspection PhpUnused
 */
class Upgrade143 extends UpgradeBase {

	/**
	 * Incomplete status.
	 *
	 * @since 1.7.5
	 */
	const INCOMPLETE = 'incomplete';

	/**
	 * Completed status.
	 *
	 * @since 1.7.5
	 */
	const COMPLETED = 'completed';

	/**
	 * Fields update options name.
	 *
	 * @since 1.7.5
	 */
	const FIELDS_UPDATE_OPTION = 'wpforms_fields_update';

	/**
	 * Primary class constructor.
	 *
	 * @since 1.7.5
	 *
	 * @param Migrations $migrations Instance of Migrations class.
	 */
	public function __construct( $migrations ) {

		parent::__construct( $migrations );

		$this->hooks();
	}

	/**
	 * Run upgrade.
	 *
	 * @since 1.7.5
	 *
	 * @return bool|null Upgrade result:
	 *                   true  - the upgrade completed successfully,
	 *                   false - in the case of failure,
	 *                   null  - upgrade started but not yet finished (background task).
	 */
	public function run() {

		$upgrade_v143 = get_option( self::FIELDS_UPDATE_OPTION );

		if ( $upgrade_v143 === self::COMPLETED ) {
			delete_option( self::FIELDS_UPDATE_OPTION );

			return true;
		}

		$this->hooks();

		if ( $upgrade_v143 !== false ) {
			return null;
		}

		$entry_handler        = wpforms()->get( 'entry' );
		$entry_fields_handler = wpforms()->get( 'entry_fields' );

		if ( ! $entry_handler || ! $entry_fields_handler ) {
			return false;
		}

		// Check the total number of entries currently stored.
		$entry_total = $entry_handler->get_entries( [], true );

		// If the site has at least one entry, indicate to a user
		// that we need to run the database upgrade routine.
		if ( ! empty( $entry_total ) ) {
			update_option( self::FIELDS_UPDATE_OPTION, true );
		}

		return null;
	}

	/**
	 * General hooks.
	 *
	 * @since 1.7.5
	 */
	private function hooks() {

		add_action( 'wp_ajax_wpforms_upgrade_143', [ $this, 'v143_upgrade_ajax' ] );
		add_action( 'admin_notices', [ $this, 'admin_notice' ] );
		add_action( 'wpforms_tools_display_tab_upgrade', [ $this, 'upgrade_tab' ] );
	}

	/**
	 * AJAX upgrade routine that upgrades existing entries field to the new
	 * entry fields database.
	 *
	 * @since 1.4.3
	 * @since 1.5.9 Moved from WPForms_Upgrades.
	 * @since 1.7.5 Moved from WPForms\Pro\Migrations
	 */
	public function v143_upgrade_ajax() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.NestingLevel.MaxExceeded

		// Run a security check.
		check_ajax_referer( 'wpforms-admin', 'nonce' );

		// Check for permissions.
		if ( ! wpforms_current_user_can() ) {
			wp_send_json_error();
		}

		global $wpdb;

		// Table names.
		$fields_table  = $wpdb->prefix . 'wpforms_entry_fields';
		$entries_table = $wpdb->prefix . 'wpforms_entries';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Check if this is the initial total check.
		if ( ! empty( $_POST['init'] ) ) {

			$upgraded = count( $wpdb->get_results( "SELECT DISTINCT entry_id FROM {$fields_table}" ) );

			// If we have fields that have already been upgraded, then we know
			// this is resuming a previous attempt.
			if ( ! empty( $upgraded ) ) {

				// Determine the last entry that was added in the upgrade routine.
				$last_entry_id = $wpdb->get_var( "SELECT MAX(entry_id) FROM {$fields_table}" );

				// Delete fields with this entry.
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$fields_table} WHERE `entry_id` = %d;",
						absint( $last_entry_id )
					)
				);
			}

			wp_send_json_success(
				[
					'total'    => wpforms()->get( 'entry' )->get_entries( [], true ),
					'upgraded' => $upgraded,
				]
			);
		}

		if ( empty( $_POST['upgraded'] ) ) {

			// If upgraded entries is 0 we know this is the beginning of the
			// upgrade routine, so update the option to indicate that the
			// upgrade has started but not completed. This way if it doesn't
			// finish, we can resume and complete.
			update_option( self::FIELDS_UPDATE_OPTION, self::INCOMPLETE );

			// Fetch the first 10 entries.
			$entries = wpforms()->get( 'entry' )->get_entries(
				[
					'number' => 10,
					'order'  => 'ASC',
				]
			);

		} else {

			// Determine the last entry that was added in the upgrade routine.
			$last_entry_id = $wpdb->get_var( "SELECT MAX(entry_id) FROM {$fields_table}" );

			$entries = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$entries_table} WHERE entry_id > %d ORDER BY entry_id ASC LIMIT 10;",
					absint( $last_entry_id )
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Loop through the entries and add each field value to the new entry
		// fields database table.
		if ( ! empty( $entries ) ) {
			foreach ( $entries as $entry ) {

				$fields = wpforms_decode( $entry->fields );

				if ( ! empty( $fields ) ) {
					foreach ( $fields as $field ) {
						if ( isset( $field['id'] ) && isset( $field['value'] ) && $field['value'] !== '' ) {
							wpforms()->get( 'entry_fields' )->add(
								[
									'entry_id' => absint( $entry->entry_id ),
									'form_id'  => absint( $entry->form_id ),
									'field_id' => absint( $field['id'] ),
									'value'    => $field['value'],
									'date'     => $entry->date,
								]
							);
						}
					}
				}
			}
		}

		// If there are less than 10 entries, this batch completed the
		// upgrade routine. Update the option accordingly.
		if ( count( $entries ) < 10 ) {
			update_option( self::FIELDS_UPDATE_OPTION, self::COMPLETED );
		}

		wp_send_json_success(
			[
				'count' => count( $entries ),
			]
		);
	}

	/**
	 * Alert the user if there are upgrades that need to be performed.
	 *
	 * @since 1.4.3
	 * @since 1.7.5 Moved from WPForms\Pro\Migrations
	 */
	public function admin_notice() {

		// Only show upgrade notice to site administrators.
		if ( ! is_super_admin() ) {
			return;
		}

		// Don't show upgrade notices on the upgrades screen.
		if ( ! empty( $_GET['page'] ) && $_GET['page'] === 'wpforms-tools' && ! empty( $_GET['view'] ) && $_GET['view'] === 'upgrade' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// v1.4.3 fields database upgrade notice.
		$upgrade_v143 = get_option( self::FIELDS_UPDATE_OPTION, false );

		if ( $upgrade_v143 ) {
			if ( $upgrade_v143 === self::INCOMPLETE ) {
				/* translators: %s - resume page URL. */
				$msg = __( 'WPForms database upgrade is incomplete, click <a href="%s">here</a> to resume.', 'wpforms' );
			} else {
				/* translators: %s - entries upgrade page URL. */
				$msg = __( 'WPForms needs to upgrade the database, click <a href="%s">here</a> to start the upgrade.', 'wpforms' );
			}

			Notice::info(
				sprintf(
					wp_kses(
						$msg,
						[
							'a' => [
								'href' => [],
							],
						]
					),
					esc_url( admin_url( 'admin.php?page=wpforms-tools&view=upgrade' ) )
				)
			);
		}
	}

	/**
	 * Generate the upgrade tab inside the Tools page if needed.
	 *
	 * @since 1.4.3
	 * @since 1.7.5 Moved from WPForms\Pro\Migrations
	 */
	public function upgrade_tab() {

		// v1.4.3 fields database upgrade.
		$upgrade_v143 = get_option( self::FIELDS_UPDATE_OPTION, false );

		if ( $upgrade_v143 ) {

			$msg   = esc_html__( 'WPForms needs to upgrade the database, click the button below to begin.', 'wpforms' );
			$label = esc_html__( 'Run Upgrade', 'wpforms' );

			if ( $upgrade_v143 === self::INCOMPLETE ) {
				$msg   = esc_html__( 'WPForms database upgrade is incomplete, click the button below to resume.', 'wpforms' );
				$label = esc_html__( 'Resume Upgrade', 'wpforms' );
			}

			echo '<div class="wpforms-setting-row tools upgrade" id="wpforms-upgrade-143">';

			echo '<h3>' . esc_html__( 'Upgrade', 'wpforms' ) . '</h3>';
			echo '<p>' . esc_html( $msg ) . '</p>';
			echo '<p>' . esc_html__( 'Please do not leave this page or close the browser while the upgrade is in progress.', 'wpforms' ) . '</p>';
			echo '<button class="wpforms-btn wpforms-btn-md wpforms-btn-orange" id="wpforms-tools-upgrade-fields">' . esc_html( $label ) . '</button>';

			echo '<div class="status" style="display:none;">';
			echo '<div class="progress-bar"><div class="bar"></div></div>';
			echo '<p class="msg"><span class="percent">0%</span> - ';
			printf(
			/* translators: %1$s - total number of entries upgraded, %2$s - total number of entries on site. */
				esc_html__( 'Updated %1$s of %2$s entries.', 'wpforms' ),
				'<span class="current">0</span>',
				'<span class="total">0</span>'
			);
			echo '</p>';
			echo '</div>';

			echo '</div>';

			return;
		}

		echo '<p>' . esc_html__( 'No updates are currently needed.', 'wpforms' ) . '</p>';
	}
}
