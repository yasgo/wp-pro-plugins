<?php

namespace WPForms\Pro\Integrations\LiteConnect;

/**
 * Class AddRestoredFlagTask.
 *
 * @since 1.7.4
 */
class AddRestoredFlagTask {

	/**
	 * Task name.
	 *
	 * @since 1.7.4
	 *
	 * @var string
	 */
	const LITE_CONNECT_RESTORED_TASK = 'wpforms_lite_connect_add_restored_flag';

	/**
	 * ImportEntriesTask constructor.
	 *
	 * @since 1.7.4
	 */
	public function __construct() {

		$this->hooks();
	}

	/**
	 * Initialize the hooks.
	 *
	 * @since 1.7.4
	 */
	private function hooks() {

		// Process the tasks as needed.
		add_action( self::LITE_CONNECT_RESTORED_TASK, [ $this, 'process' ] );
	}

	/**
	 * Creates a task to add the restored flag to the Lite Connect API via Action Scheduler.
	 *
	 * @since 1.7.4
	 *
	 * @param bool $delay True if the task should run after 5 minutes.
	 */
	public function create( $delay = false ) {

		$run_at = time();

		if ( $delay ) {
			$run_at += 5 * MINUTE_IN_SECONDS;
		}

		// Creates the task to import entries.
		$action_id = wpforms()->get( 'tasks' )
				 ->create( self::LITE_CONNECT_RESTORED_TASK )
				 ->once( $run_at )
				 ->register();

		if ( $action_id === null ) {
			wpforms_log(
				'Lite Connect: error creating the AS task',
				[
					'task' => self::LITE_CONNECT_RESTORED_TASK,
				],
				[ 'type' => [ 'error' ] ]
			);
		}
	}

	/**
	 * Process the task to add the restored flag to the Lite Connect API via Action Scheduler.
	 *
	 * @since 1.7.4
	 */
	public function process() {

		// Grab current import status.
		$import = wpforms_setting( 'import', false, Integration::get_option_name() );

		if ( ! isset( $import['status'] ) || $import['status'] !== 'done' ) {
			return;
		}

		// Send the request to add the restored flag to the Lite Connect API.
		$flag = ( new Integration() )->add_restored_flag();

		// Recreate task if the import fail for any reasons.
		if ( $flag === false ) {
			// Create a new task.
			$this->create( true );
		}
	}
}
