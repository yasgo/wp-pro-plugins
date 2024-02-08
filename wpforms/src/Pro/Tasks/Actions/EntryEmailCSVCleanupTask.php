<?php

namespace WPForms\Pro\Tasks\Actions;

use WPForms\Pro\Admin\Builder\Notifications\Advanced\EntryCsvAttachment;
use WPForms\Tasks\Meta;
use WPForms\Tasks\Task;

/**
 * Class EntryEmailCSVCleanupTask.
 *
 * @since 1.7.7
 */
class EntryEmailCSVCleanupTask extends Task {

	/**
	 * Chunk size to use when processing CSV files to delete.
	 *
	 * @since 1.7.7
	 *
	 * @var int
	 */
	const CHUNK_SIZE = 50;

	/**
	 * Scan action name for this task.
	 *
	 * @since 1.7.7
	 *
	 * @var string
	 */
	const SCAN_ACTION = 'wpforms_process_entry_emails_csv_scan';

	/**
	 * Cleanup action name for this task.
	 *
	 * @since 1.7.7
	 *
	 * @var string
	 */
	const CLEANUP_ACTION = 'wpforms_process_entry_emails_csv_cleanup';

	/**
	 * Cleanup status option name.
	 *
	 * @since 1.7.7
	 *
	 * @var string
	 */
	const CLEANUP_STATUS = 'wpforms_process_entry_emails_csv_cleanup_status';

	/**
	 * Cleanup status "In Progress".
	 *
	 * @since 1.7.7
	 *
	 * @var string
	 */
	const CLEANUP_STATUS_IN_PROGRESS = 'in progress';

	/**
	 * Cleanup status "Completed".
	 *
	 * @since 1.7.7
	 *
	 * @var string
	 */
	const CLEANUP_STATUS_COMPLETED = 'completed';

	/**
	 * CSV File TTL value.
	 *
	 * @since 1.7.7
	 *
	 * @var int
	 */
	const ENTRY_CSV_DEFAULT_TTL = DAY_IN_SECONDS;

	/**
	 * Class constructor.
	 *
	 * @since 1.7.7
	 */
	public function __construct() {

		parent::__construct( self::SCAN_ACTION );

		$this->init();
	}

	/**
	 * Initialize.
	 *
	 * @since 1.7.7
	 */
	private function init() {

		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 1.7.7
	 */
	private function hooks() {

		add_action( self::SCAN_ACTION, [ $this, 'scan' ] );
		add_action( self::CLEANUP_ACTION, [ $this, 'cleanup' ] );
		add_action( 'action_scheduler_after_process_queue', [ $this, 'after_process_queue' ] );
		add_action( 'wpforms_attach_entry_csv_in_email_complete', [ $this, 'create_cleanup_task' ] );
	}

	/**
	 * Scan the Entry Attachment CSV parent folder for CSV files.
	 *
	 * @since 1.7.7
	 */
	public function scan() {

		$tasks = wpforms()->get( 'tasks' );

		if ( ! $tasks ) {
			return;
		}

		// Bail out if cleanup is already in progress.
		if ( self::CLEANUP_STATUS_IN_PROGRESS === (string) get_option( self::CLEANUP_STATUS ) ) {
			return;
		}

		$upload_dir = wpforms_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			// Log here.
			return;
		}

		// First we want to scan the directory.
		$directory = $upload_dir['path'] . '/' . EntryCsvAttachment::FOLDER_NAME;

		$files = glob( "$directory/*/*.csv" );

		if ( empty( $files ) ) {
			return;
		}

		// Mark the cleanup is in progress.
		update_option( self::CLEANUP_STATUS, self::CLEANUP_STATUS_IN_PROGRESS );

		/**
		 * We chunk the cleanup tasks to account for potential memory/resource issue.
		 */
		$file_chunks = array_chunk( $files, self::CHUNK_SIZE, true );
		$count       = count( $file_chunks );

		foreach ( $file_chunks as $index => $file_chunk ) {
			$tasks->create( self::CLEANUP_ACTION )->async()->params( $file_chunk, $index, $count )->register();
		}

		$this->log( 'Cleanup task(s) created.' );
	}

	/**
	 * Cleanup action.
	 *
	 * Delete CSV files that expired there TTL.
	 *
	 * @since 1.7.7
	 *
	 * @param int $meta_id Action meta id.
	 */
	public function cleanup( $meta_id ) {

		$params = ( new Meta() )->get( $meta_id );

		if ( ! $params ) {
			return;
		}

		list( $file_chunk, $index, $count ) = $params->data;

		if ( empty( $file_chunk ) ) {
			return;
		}

		/**
		 * Give developers an ability to modify CSV TTL.
		 *
		 * @since 1.7.7
		 *
		 * @param int $ttl TTL of CSV files.
		 */
		$ttl = (int) apply_filters( 'wpforms_pro_tasks_actions_entry_email_csv_cleanup_task_ttl', self::ENTRY_CSV_DEFAULT_TTL );

		$now = time();

		foreach ( $file_chunk as $file ) {
			$this->attempt_to_delete_csv( $file, $now, $ttl );
		}

		$this->log(
			sprintf(
				'Cleanup action %1$d/%2$d is done.',
				$index + 1,
				$count
			)
		);
	}

	/**
	 * Attempt to delete a CSV file and it's directory.
	 *
	 * @since 1.7.7
	 *
	 * @param string $file     File path to delete.
	 * @param int    $time_now Current Unix timestamp.
	 * @param int    $ttl      TTL of the file.
	 */
	private function attempt_to_delete_csv( $file, $time_now, $ttl ) {

		clearstatcache( true, $file );

		if ( ! is_file( $file ) ) {
			return;
		}

		$path_info = pathinfo( $file );

		if (
			strtolower( $path_info['extension'] ) !== 'csv' ||
			( $time_now - filemtime( $file ) ) < $ttl
		) {
			return;
		}

		// We want to hide the first part of the path in the logs for security purposes.
		$wpforms_upload_dir = wpforms_upload_dir();
		$remove_path_string = wp_normalize_path( WP_CONTENT_DIR );

		if ( empty( $wpforms_upload_dir['error'] ) ) {
			$remove_path_string = $wpforms_upload_dir['path'];
		}

		// Delete the file.
		if ( ! unlink( $file ) ) {
			$this->log(
				sprintf(
					'Cleanup action unable to delete the file: %s.',
					str_replace( $remove_path_string, '', $file )
				)
			);

			return;
		}

		if ( ! rmdir( $path_info['dirname'] ) ) {
			$this->log(
				sprintf(
					'Cleanup action unable to delete the directory: %s.',
					str_replace( $remove_path_string, '', $path_info['dirname'] )
				)
			);
		}
	}

	/**
	 * After process queue action.
	 *
	 * @since 1.7.7
	 */
	public function after_process_queue() {

		if ( wpforms()->get( 'tasks' )->is_scheduled( self::CLEANUP_ACTION ) ) {
			return;
		}

		// Mark that cleanup is finished.
		if ( (string) get_option( self::CLEANUP_STATUS ) === self::CLEANUP_STATUS_IN_PROGRESS ) {
			update_option( self::CLEANUP_STATUS, self::CLEANUP_STATUS_COMPLETED );
			$this->log( 'Cleanup task(s) completed.' );
		}
	}

	/**
	 * Log message to WPForms logger and standard debug.log file.
	 *
	 * @since 1.7.7
	 *
	 * @param string $message The error message that should be logged.
	 */
	private function log( $message ) {

		if ( defined( 'WPFORMS_DEBUG' ) && WPFORMS_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Entry CSV Attachment: %s', $message ) );
			wpforms_log( 'Entry CSV Attachment', $message, [ 'type' => 'log' ] );
		}
	}

	/**
	 * Create cleanup task.
	 *
	 * @since 1.7.7
	 *
	 * @param array $email_data Email data used on the email sent.
	 */
	public function create_cleanup_task( $email_data ) {

		$tasks = wpforms()->get( 'tasks' );

		if ( $tasks->is_scheduled( self::SCAN_ACTION ) ) {
			return;
		}

		/**
		 * Filters the Entry CSV Attachment cleanup interval.
		 *
		 * @since 1.7.7
		 *
		 * @param int $interval Interval in seconds.
		 */
		$interval = (int) apply_filters( 'wpforms_pro_tasks_actions_entry_email_csv_cleanup_task_interval', DAY_IN_SECONDS );

		$tasks->create( self::SCAN_ACTION )
			->recurring( time() + 60, $interval )
			->params()
			->register();
	}
}
