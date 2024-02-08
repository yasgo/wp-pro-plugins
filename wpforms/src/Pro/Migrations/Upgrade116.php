<?php

namespace WPForms\Pro\Migrations;

use WPForms\Migrations\UpgradeBase;

/**
 * Class v1.1.6 upgrade for Pro.
 *
 * @since 1.7.5
 *
 * @noinspection PhpUnused
 */
class Upgrade116 extends UpgradeBase {

	/**
	 * Create entry_meta table.
	 *
	 * @since 1.7.5
	 *
	 * @return bool|null Upgrade result:
	 *                   true  - the upgrade completed successfully,
	 *                   false - in the case of failure,
	 *                   null  - upgrade started but not yet finished (background task).
	 */
	public function run() {

		$entry_meta_handler = wpforms()->get( 'entry_meta' );

		if ( ! $entry_meta_handler ) {
			return false;
		}

		if ( ! $entry_meta_handler->table_exists() ) {
			$entry_meta_handler->create_table();
		}

		return true;
	}
}
