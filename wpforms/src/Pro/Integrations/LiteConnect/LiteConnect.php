<?php

namespace WPForms\Pro\Integrations\LiteConnect;

/**
 * Class LiteConnect for WPForms Pro.
 *
 * @since 1.7.4
 */
class LiteConnect extends \WPForms\Integrations\LiteConnect\LiteConnect {

	/**
	 * The Integration object.
	 *
	 * @since 1.7.4
	 *
	 * @var Integration
	 */
	private $integration;

	/**
	 * Import Entries Task object.
	 *
	 * @since 1.7.4
	 *
	 * @var ImportEntriesTask
	 */
	private $import_entries_task;

	/**
	 * Add Restored Flag Task object.
	 *
	 * @since 1.7.4
	 *
	 * @var AddRestoredFlagTask
	 */
	private $add_restored_flag_task;

	/**
	 * Admin object.
	 *
	 * @since 1.7.4
	 *
	 * @var Admin
	 */
	private $admin;

	/**
	 * Loads the integration.
	 *
	 * @since 1.7.4
	 */
	public function load() {

		parent::load();

		// Process import task.
		$this->import_entries_task = new ImportEntriesTask();

		// Process add restored flag task.
		$this->add_restored_flag_task = new AddRestoredFlagTask();

		// We always need to instance the Integration class as part of the load process for the Lite Connect integration.
		$this->integration = new Integration();

		// Load the Admin class.
		if ( is_admin() ) {
			$this->admin = new Admin();
		}
	}
}
