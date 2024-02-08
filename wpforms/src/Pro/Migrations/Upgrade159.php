<?php

namespace WPForms\Pro\Migrations;

use WPForms\Migrations\UpgradeBase;

/**
 * Class v1.5.9 upgrade for Pro.
 *
 * @since 1.7.5
 *
 * @noinspection PhpUnused
 */
class Upgrade159 extends UpgradeBase {

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

		// Re-check that all database tables exist for Lite users
		// who upgraded to Pro using the settings workflow for v1.5.9.
		$wpforms_pro = wpforms()->get( 'pro' );

		// Check and create custom DB tables.
		if ( ! $wpforms_pro->custom_tables_exist() ) {
			$wpforms_pro->create_custom_tables();
		}

		return true;
	}
}
