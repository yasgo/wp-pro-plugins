<?php

namespace WPForms\Pro\Integrations\LiteConnect;

use WPForms\Tasks\Meta;
use WPForms\Pro\Admin\DashboardWidget;

/**
 * Class ImportEntriesTask.
 *
 * @since 1.7.4
 */
class ImportEntriesTask {

	/**
	 * Task name.
	 *
	 * @since 1.7.4
	 *
	 * @var string
	 */
	const LITE_CONNECT_IMPORT_TASK = 'wpforms_lite_connect_import_entries';

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
		add_action( self::LITE_CONNECT_IMPORT_TASK, [ $this, 'process' ] );
	}

	/**
	 * Creates a task to import entries from the Lite Connect API via Action Scheduler.
	 *
	 * @since 1.7.4
	 *
	 * @param string $last_import_id The ID of the last imported entry.
	 */
	public function create( $last_import_id = null ) {

		$tasks = wpforms()->get( 'tasks' );

		// Creates the task to import entries.
		$action_id = $tasks->create( self::LITE_CONNECT_IMPORT_TASK )
			->params( $last_import_id )
			->once( time() + 15 )
			->register();

		if ( $action_id === null ) {
			wpforms_log(
				'Lite Connect: error creating the AS task',
				[
					'task' => self::LITE_CONNECT_IMPORT_TASK,
				],
				[ 'type' => [ 'error' ] ]
			);
		} else {
			// Updates the import status value to 'scheduled'.
			$this->set_as_scheduled();
		}
	}

	/**
	 * Process the task to import entries from the Lite Connect API via Action Scheduler.
	 *
	 * @since 1.7.4
	 *
	 * @param int $meta_id The meta ID.
	 */
	public function process( $meta_id ) {

		// Load task data.
		$params = ( new Meta() )->get( (int) $meta_id );

		list( $last_import_id ) = $params->data;

		// Grab current import status.
		$import = wpforms_setting( 'import', false, Integration::get_option_name() );

		if ( ! isset( $import['status'] ) || $import['status'] !== 'scheduled' ) {
			return;
		}

		$integration = new Integration();

		// Update status to 'running' and import the entries from Lite Connect API.
		$import = $integration->retrieve_and_decrypt( $last_import_id );

		// Clear Dashboard Widget And Entries Default screen cache.
		DashboardWidget::clear_widget_cache();

		// Recreate task if the import fail for any reasons.
		if ( $import === false ) {
			// Remove all entries that were imported previously.
			$integration->reset_import();

			// If the number of tries to import has reached the limit don't try again.
			if ( $integration->has_reached_fail_limit() ) {
				wpforms_log(
					'Lite Connect: the number of import fails has reached the limit',
					[
						'fails_limit' => Integration::FAILS_LIMIT,
					],
					[ 'type' => [ 'error' ] ]
				);

				return;
			}

			// Create a new task.
			$this->create();
		}
	}

	/**
	 * Update the import flag status to 'scheduled'.
	 *
	 * @since 1.7.4
	 */
	private function set_as_scheduled() {

		$settings = get_option( Integration::get_option_name() );

		$settings['import']['status'] = 'scheduled';

		update_option( Integration::get_option_name(), $settings );
	}
}
