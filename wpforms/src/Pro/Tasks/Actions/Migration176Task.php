<?php

namespace WPForms\Pro\Tasks\Actions;

use WPForms\Tasks\Meta;
use WPForms\Tasks\Task;
use WPForms\Tasks\Tasks;
use WPForms_Entry_Fields_Handler;

/**
 * Class Migration176Task.
 *
 * @since 1.7.6
 */
class Migration176Task extends Task {

	/**
	 * Action name for this task.
	 *
	 * @since 1.7.6
	 */
	const ACTION = 'wpforms_process_migration_176';

	/**
	 * Status option name.
	 *
	 * @since 1.7.6
	 */
	const STATUS = 'wpforms_process_migration_176_status';

	/**
	 * Start status.
	 *
	 * @since 1.7.6
	 */
	const START = 'start';

	/**
	 * In progress status.
	 *
	 * @since 1.7.6
	 */
	const IN_PROGRESS = 'in progress';

	/**
	 * Completed status.
	 *
	 * @since 1.7.6
	 */
	const COMPLETED = 'completed';

	/**
	 * Chunk size to use.
	 * Specifies how many entries to load for scanning in one db request.
	 * Affects memory usage.
	 *
	 * @since 1.7.6
	 */
	const CHUNK_SIZE = 50;

	/**
	 * Entry fields handler.
	 *
	 * @since 1.7.6
	 *
	 * @var WPForms_Entry_Fields_Handler
	 */
	private $entry_fields_handler;

	/**
	 * Class constructor.
	 *
	 * @since 1.7.6
	 */
	public function __construct() {

		parent::__construct( self::ACTION );
	}

	/**
	 * Initialize the task with all the proper checks.
	 *
	 * @since 1.7.6
	 */
	public function init() {

		$this->entry_fields_handler = wpforms()->get( 'entry_fields' );

		if ( ! $this->entry_fields_handler ) {
			return;
		}

		// Bail out if migration is not started or completed.
		$status = get_option( self::STATUS );

		if ( ! $status || $status === self::COMPLETED ) {
			return;
		}

		// Mark that migration is in progress.
		update_option( self::STATUS, self::IN_PROGRESS );

		$this->hooks();

		$tasks = wpforms()->get( 'tasks' );

		// Add new if none exists.
		if ( $tasks->is_scheduled( self::ACTION ) !== false ) {
			return;
		}

		// Init migration.
		$this->init_migration( $tasks );
	}

	/**
	 * Add hooks.
	 *
	 * @since 1.7.6
	 */
	private function hooks() {

		// Register the migrate action.
		add_action( self::ACTION, [ $this, 'migrate' ] );

		// Register after process queue action.
		add_action( 'action_scheduler_after_process_queue', [ $this, 'after_process_queue' ] );
	}

	/**
	 * Migrate.
	 *
	 * @since 1.7.6
	 *
	 * @param int $meta_id Action meta id.
	 */
	public function migrate( $meta_id ) {

		$params = ( new Meta() )->get( $meta_id );

		if ( ! $params ) {
			return;
		}

		list( $duplicated_fields ) = $params->data;

		foreach ( $duplicated_fields as $row ) {
			if ( ! isset( $row['form_id'], $row['entry_id'], $row['field_id'] ) ) {
				continue;
			}

			$this->drop_duplicated_fields( $row['form_id'], $row['entry_id'], $row['field_id'] );
		}
	}

	/**
	 * Get the field row ID.
	 *
	 * @since 1.7.6
	 *
	 * @param int $form_id  Form ID.
	 * @param int $entry_id Entry ID.
	 * @param int $field_id Field ID.
	 *
	 * @return int
	 */
	private function get_row_id( $form_id, $entry_id, $field_id ) {

		$fields = $this->entry_fields_handler->get_fields(
			[
				'number'   => 1,
				'form_id'  => $form_id,
				'entry_id' => $entry_id,
				'field_id' => $field_id,
				'order'    => 'DESC',
			]
		);

		return ! empty( $fields[0] ) ? (int) $fields[0]->id : 0;
	}

	/**
	 * Drop duplicated fields.
	 *
	 * @since 1.7.6
	 *
	 * @param int $form_id  Form ID.
	 * @param int $entry_id Entry ID.
	 * @param int $field_id Field ID.
	 */
	private function drop_duplicated_fields( $form_id, $entry_id, $field_id ) {

		$row_id = $this->get_row_id( $form_id, $entry_id, $field_id );

		if ( ! $row_id ) {
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . esc_sql( $this->entry_fields_handler->table_name ) .
				' WHERE form_id = %d
				AND entry_id = %d
				AND field_id = %d
				AND id < %d',
				$form_id,
				$entry_id,
				$field_id,
				$row_id
			)
		);
	}

	/**
	 * After process queue action.
	 * Set status as completed.
	 *
	 * @since 1.7.6
	 */
	public function after_process_queue() {

		if ( as_has_scheduled_action( self::ACTION ) ) {
			return;
		}

		// Mark that migration is finished.
		update_option( self::STATUS, self::COMPLETED );
	}

	/**
	 * Init migration.
	 *
	 * @since 1.7.6
	 *
	 * @param Tasks $tasks Tasks class instance.
	 */
	private function init_migration( $tasks ) {

		// This part of the migration shouldn't take more than 1 second even on big sites.
		$duplicated_fields = $this->get_duplicated_entry_fields();

		if ( ! $duplicated_fields ) {
			// Mark that migration is completed.
			update_option( self::STATUS, self::COMPLETED );

			return;
		}

		/**
		 * This part of the migration can take a while.
		 * Saving hundreds of entries with a potentially very high number of entry fields could be time and memory consuming.
		 * That is why we perform save via Action Scheduler.
		 */
		$entry_id_chunks = array_chunk( $duplicated_fields, self::CHUNK_SIZE, true );

		foreach ( $entry_id_chunks as $entry_id_chunk ) {
			$tasks->create( self::ACTION )->async()->params( $entry_id_chunk )->register();
		}
	}

	/**
	 * Get duplicated fields list.
	 *
	 * @since 1.7.6
	 *
	 * @return int[]
	 */
	private function get_duplicated_entry_fields() {

		global $wpdb;

		$entry_fields = wpforms()->get( 'entry_fields' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$duplicated_fields = $wpdb->get_results(
			'SELECT form_id, field_id, entry_id, COUNT(*) as count
			FROM ' . esc_sql( $entry_fields->table_name ) . '
			GROUP BY form_id, entry_id, field_id
			HAVING count > 1',
			ARRAY_A
		);

		if ( ! $duplicated_fields || ! is_array( $duplicated_fields ) ) {
			return [];
		}

		return $duplicated_fields;
	}
}
