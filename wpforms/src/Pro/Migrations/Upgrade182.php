<?php

namespace WPForms\Pro\Migrations;

use WPForms\Migrations\UpgradeBase;
use WPForms\Pro\Tasks\Actions\MigrationPaymentEntriesTask;

/**
 * Class v1.8.2 upgrade for Pro.
 *
 * @since 1.8.2
 *
 * @noinspection PhpUnused
 */
class Upgrade182 extends UpgradeBase {

	/**
	 * Run upgrade.
	 *
	 * We run migration as Action Scheduler task.
	 * Class Tasks does not exist at this point, so here we can only check task completion status.
	 *
	 * @since 1.8.2
	 *
	 * @return bool|null Upgrade result:
	 *                   true  - the upgrade completed successfully,
	 *                   false - in the case of failure,
	 *                   null  - upgrade started but not yet finished (background task).
	 */
	public function run() {

		return $this->run_async( MigrationPaymentEntriesTask::class );
	}
}
