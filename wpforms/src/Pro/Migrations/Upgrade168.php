<?php

namespace WPForms\Pro\Migrations;

use WPForms\Migrations\UpgradeBase;

/**
 * Class v1.6.8 upgrade for Pro.
 *
 * @since 1.7.5
 *
 * @noinspection PhpUnused
 */
class Upgrade168 extends UpgradeBase {

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
	public function run() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		if (
			! (
				function_exists( 'wpforms_form_templates_pack_load' ) ||
				function_exists( 'wpforms_form_templates_pack' )
			)
		) {
			return true;
		}

		add_action(
			'admin_init',
			static function() {
				deactivate_plugins( 'wpforms-form-templates-pack/wpforms-form-templates-pack.php' );
			}
		);

		return true;
	}
}
