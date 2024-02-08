<?php

namespace WPForms\Pro\Tasks\Actions;

use WPForms\Tasks\Task;
use WPForms\Tasks\Tasks;

/**
 * The payment entries migration task.
 *
 * @since 1.8.2
 */
class MigrationPaymentEntriesTask extends Task {

	/**
	 * Action name for this task.
	 *
	 * @since 1.8.2
	 */
	const ACTION = 'wpforms_process_migration_payment_entries';

	/**
	 * Status option name.
	 *
	 * @since 1.8.2
	 */
	const STATUS = 'wpforms_process_migration_payment_entries_status';

	/**
	 * Start status.
	 *
	 * @since 1.8.2
	 */
	const START = 'start';

	/**
	 * In progress status.
	 *
	 * @since 1.8.2
	 */
	const IN_PROGRESS = 'in progress';

	/**
	 * Completed status.
	 *
	 * @since 1.8.2
	 */
	const COMPLETED = 'completed';

	/**
	 * Chunk size to use.
	 * Specifies how many entries are processed in one DB request.
	 *
	 * @since 1.8.2
	 */
	const CHUNK_SIZE = 1000;

	/**
	 * Chunk size of the migration task.
	 * Specifies how many entry ids to load at once for further conversion.
	 *
	 * @since 1.8.2
	 */
	const TASK_CHUNK_SIZE = self::CHUNK_SIZE * 10;

	/**
	 * Maximum size of data that can be transferred in one db request.
	 * We consider that 4194304 (4M) is an appropriate value for most of cases.
	 *
	 * @since 1.8.2
	 */
	const MAX_ALLOWED_PACKET = 4194304;

	/**
	 * Temporary table name.
	 *
	 * @since 1.8.2
	 *
	 * @var string
	 */
	private $temp_table_name;

	/**
	 * Class constructor.
	 *
	 * @since 1.8.2
	 */
	public function __construct() {

		parent::__construct( self::ACTION );
	}

	/**
	 * Initialize the task.
	 *
	 * @since 1.8.2
	 */
	public function init() {

		global $wpdb;

		// Get a task status.
		$status = get_option( self::STATUS );

		// This task is run in \WPForms\Pro\Migrations\Upgrade182::run(),
		// and started in \WPForms\Migrations\UpgradeBase::run_async().
		// Bail out if a task is not started or completed.
		if ( ! $status || $status === self::COMPLETED ) {
			return;
		}

		$this->temp_table_name = "{$wpdb->prefix}wpforms_temp_payment_entries";

		if ( ! $this->is_allowed() ) {
			return;
		}

		// Register hooks.
		$this->hooks();

		// Add new only if none exists.
		if ( wpforms()->get( 'tasks' )->is_scheduled( self::ACTION ) !== false ) {
			return;
		}

		if ( $status === self::START ) {
			// Mark that a task is in progress.
			update_option( self::STATUS, self::IN_PROGRESS );

			// Init migration.
			$this->init_migration();
		}
	}

	/**
	 * Determine whether a task is allowed (can be initialized).
	 *
	 * @since 1.8.2
	 *
	 * @return bool
	 */
	private function is_allowed() {

		return wpforms()->get( 'tasks' )
			&& wpforms()->get( 'entry' )
			&& wpforms()->get( 'payment' )
			&& wpforms()->get( 'payment_meta' );
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.8.2
	 */
	private function hooks() {

		// Register the migrate action.
		add_action( self::ACTION, [ $this, 'migrate' ] );

		// Register after process queue action.
		add_action( 'action_scheduler_after_process_queue', [ $this, 'after_process_queue' ] );
	}

	/**
	 * Init migration.
	 *
	 * @since 1.8.2
	 */
	private function init_migration() {

		// Get all payment entries.
		$count = $this->get_unprocessed_payment_entry_ids();

		if ( ! $count ) {
			$this->drop_temp_table();

			return;
		}

		$index = 0;

		while ( $index < $count ) {
			// We do not use Task class here as we do not need meta. So, we reduce number of DB requests.
			as_enqueue_async_action(
				self::ACTION,
				[ $index ],
				Tasks::GROUP
			);

			$index += self::TASK_CHUNK_SIZE;
		}

		$this->maybe_raise_mysql_max_allowed_packet();
	}

	/**
	 * Get payment entry ids.
	 * Store them in a temporary table.
	 *
	 * @since 1.8.2
	 *
	 * @return int
	 */
	private function get_unprocessed_payment_entry_ids() {

		global $wpdb;

		$this->create_temp_table();

		$entry_handler   = wpforms()->get( 'entry' );
		$payment_handler = wpforms()->get( 'payment' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"INSERT INTO $this->temp_table_name (entry_id)
				SELECT entry_id
				FROM $entry_handler->table_name
				WHERE type = 'payment' AND entry_id NOT IN ( SELECT entry_id FROM $payment_handler->table_name )"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $wpdb->rows_affected;
	}

	/**
	 * Create a temporary table.
	 *
	 * @since 1.8.2
	 */
	private function create_temp_table() {

		$this->drop_temp_table();

		$query = "CREATE TABLE $this->temp_table_name (
			id bigint(20) AUTO_INCREMENT,
			entry_id bigint(20) NOT NULL,
			PRIMARY KEY  (id)
		)";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $query );
	}

	/**
	 * Drop a temporary table.
	 *
	 * @since 1.8.2
	 */
	private function drop_temp_table() {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS $this->temp_table_name" );
	}

	/**
	 * Migrate entries.
	 *
	 * @since 1.8.2
	 *
	 * @param int $action_index Action index.
	 */
	public function migrate( $action_index ) {

		global $wpdb;

		// Using OFFSET makes a way longer request, as MySQL has to access all rows before OFFSET.
		// We follow very fast way with indexed column (id > $action_index).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$entry_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT entry_id FROM $this->temp_table_name WHERE id > %d LIMIT %d",
				$action_index,
				self::TASK_CHUNK_SIZE
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$i               = 0;
		$entry_ids_count = count( $entry_ids );

		// This cycle is twice less memory consuming than array_chunk( $entry_ids ).
		while ( $i < $entry_ids_count ) {
			$entry_ids_chunk = array_slice( $entry_ids, $i, self::CHUNK_SIZE );

			$this->migrate_payment_data( $entry_ids_chunk );

			$i += self::CHUNK_SIZE;
		}
	}

	/**
	 * Migrate payment data to the new table.
	 *
	 * @since 1.8.2
	 *
	 * @param array $entry_ids List of entry ids.
	 */
	private function migrate_payment_data( $entry_ids ) {

		global $wpdb;

		$payments = [];
		$metadata = [];

		foreach ( $this->get_entries_by_ids( $entry_ids ) as $entry ) {

			if ( empty( $entry->meta ) ) {
				continue;
			}

			$payment_data   = $this->transform_payment_data( $entry );
			$payment_status = strtolower( $entry->status );

			if ( in_array( $payment_status, [ 'active', 'completed' ], true ) ) {
				$payment_status = 'processed';
			}

			// Prepare a payment record for inserting into the table.
			$payments[] = $wpdb->prepare(
				'( %d, %s, %f, %f, %f, %s, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d )',
				$entry->form_id,
				$payment_status,
				$payment_data['payment_total'],
				0, // There were no discounts for migrated payments.
				$payment_data['payment_total'],
				strtoupper( substr( $payment_data['payment_currency'], 0, 3 ) ),
				$entry->entry_id,
				$payment_data['payment_gateway'],
				$payment_data['payment_type'],
				$payment_data['payment_mode'],
				substr( $payment_data['payment_transaction'], 0, 40 ),
				substr( $payment_data['payment_customer'], 0, 40 ),
				substr( $payment_data['payment_subscription'], 0, 40 ),
				$payment_data['payment_subscription'] ? 'not-synced' : '',
				'', // Placeholder for title.
				$entry->date,
				$entry->date_modified,
				1 // All migrated payments are published.
			);

			// Collect payments meta for using in separate DB queries.
			$metadata[] = [
				'subscription_period' => $payment_data['payment_period'],
				'payment_note'        => $payment_data['payment_note'],
				'payment_recipient'   => $payment_data['payment_recipient'],
				'receipt_number'      => $payment_data['receipt_number'],
				'user_id'             => $entry->user_id,
				'user_agent'          => $entry->user_agent,
				'user_uuid'           => $entry->user_uuid,
				'ip_address'          => $entry->ip_address,
				'is_migrated'         => 1, // It might be useful for determining whether a payment has been migrated.
			];
		}

		if ( empty( $payments ) ) {
			return;
		}

		// Chaining syntax are used here since an order of methods call is important.
		$this->insert_payments( $payments )->insert_metadata( $metadata );
	}

	/**
	 * Retrieve list of entries by their IDs.
	 *
	 * @since 1.8.2
	 *
	 * @param array $ids Entries IDs.
	 *
	 * @return array
	 */
	private function get_entries_by_ids( $ids ) {

		global $wpdb;

		$entry_handler  = wpforms()->get( 'entry' );
		$entry_ids_list = implode( ',', $ids );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"SELECT entry_id, form_id, user_id, status, meta, date, date_modified, ip_address, user_agent, user_uuid
			FROM $entry_handler->table_name
			WHERE entry_id IN ( $entry_ids_list )"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching

		return is_array( $wpdb->last_result ) ? $wpdb->last_result : [];
	}

	/**
	 * Transform legacy payment meta.
	 *
	 * @since 1.8.2
	 *
	 * @param object $entry Single entry.
	 *
	 * @return array
	 */
	private function transform_payment_data( $entry ) {

		static $defaults;

		if ( ! isset( $defaults ) ) {
			$defaults = [
				'payment_total'        => 0,
				'payment_currency'     => '',
				'payment_gateway'      => '',
				'payment_type'         => '',
				'payment_mode'         => '',
				'payment_transaction'  => '',
				'payment_customer'     => '',
				'payment_subscription' => '',
				'payment_period'       => '',
				'payment_note'         => '',
				'payment_recipient'    => '',
				'receipt_number'       => '',
			];
		}

		$payment_data = json_decode( $entry->meta, true );

		if ( ! is_array( $payment_data ) ) {
			return $defaults;
		}

		$payment_data = wp_parse_args( $payment_data, $defaults );

		// Prepare a payment gateway.
		// In the past, it was stored as a payment type.
		$payment_data['payment_gateway'] = $payment_data['payment_type'];

		// Prepare a payment type.
		$payment_data['payment_type'] = ! empty( $payment_data['payment_subscription'] ) ? 'subscription' : 'one-time';

		// Prepare a payment mode.
		// Convert the legacy `production` to `live` mode.
		$payment_data['payment_mode'] = in_array( $payment_data['payment_mode'], [ 'production', 'live' ], true ) ? 'live' : 'test';

		return $payment_data;
	}

	/**
	 * Insert records into the main payments table.
	 *
	 * @since 1.8.2
	 *
	 * @param array $payments Payments are ready for using in DB query.
	 *
	 * @return MigrationPaymentEntriesTask
	 */
	private function insert_payments( $payments ) {

		global $wpdb;

		$payment_handler = wpforms()->get( 'payment' );
		$values          = implode( ', ', $payments );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"INSERT INTO $payment_handler->table_name
			( form_id, status, subtotal_amount, discount_amount, total_amount, currency, entry_id, gateway, type, mode, transaction_id, customer_id, subscription_id, subscription_status, title, date_created_gmt, date_updated_gmt, is_published )
			VALUES $values"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this;
	}

	/**
	 * Insert records into the payments meta table.
	 *
	 * @since 1.8.2
	 *
	 * @param array $metadata List of payments metadata.
	 */
	private function insert_metadata( $metadata ) {

		global $wpdb;

		if ( empty( $wpdb->insert_id ) ) {
			return;
		}

		// This is the first payment ID inserted in the last DB query.
		$payment_id = $wpdb->insert_id;
		$values     = [];

		foreach ( $metadata as $payment_meta ) {
			foreach ( $payment_meta as $meta_key => $meta_value ) {

				if ( wpforms_is_empty_string( $meta_value ) ) {
					continue;
				}

				$values[] = $wpdb->prepare(
					'( %d, %s, %s )',
					$payment_id,
					$meta_key,
					$meta_value
				);
			}

			// Increment payment ID.
			$payment_id++;
		}

		if ( empty( $values ) ) {
			return;
		}

		$values               = implode( ', ', $values );
		$payment_meta_handler = wpforms()->get( 'payment_meta' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"INSERT INTO $payment_meta_handler->table_name
			( payment_id, meta_key, meta_value )
			VALUES $values"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Raise the maximum size of one packet if it is possible.
	 *
	 * @since 1.8.2
	 */
	private function maybe_raise_mysql_max_allowed_packet() {

		global $wpdb;

		// The length of the query is defined by MAX_ALLOWED_PACKET variable.
		// We try to raise MAX_ALLOWED_PACKET variable to more appropriate value specified in self::MAX_ALLOWED_PACKET.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$max_allowed_packet     = (int) $wpdb->get_var( "SHOW VARIABLES LIKE 'MAX_ALLOWED_PACKET'", 1 );
		$new_max_allowed_packet = self::MAX_ALLOWED_PACKET;

		// Bail out if the current MAX_ALLOWED_PACKET variable is good enough.
		if ( $new_max_allowed_packet <= $max_allowed_packet ) {
			return;
		}

		$is_suppressed = $wpdb->suppress_errors;

		$wpdb->suppress_errors();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "SET GLOBAL MAX_ALLOWED_PACKET = $new_max_allowed_packet" );
		$wpdb->suppress_errors( $is_suppressed );
	}

	/**
	 * After process queue action.
	 *
	 * @since 1.8.2
	 */
	public function after_process_queue() {

		if ( wpforms()->get( 'tasks' )->is_scheduled( self::ACTION ) ) {
			return;
		}

		$this->drop_temp_table();

		// Mark that a task is completed.
		update_option( self::STATUS, self::COMPLETED );
	}
}
