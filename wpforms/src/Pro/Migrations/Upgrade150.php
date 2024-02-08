<?php

namespace WPForms\Pro\Migrations;

use WPForms\Migrations\UpgradeBase;

/**
 * Class v1.5.0 upgrade for Pro.
 *
 * @since 1.7.5
 *
 * @noinspection PhpUnused
 */
class Upgrade150 extends UpgradeBase {

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

		$form_handler = wpforms()->get( 'form' );

		if ( ! $form_handler ) {
			return false;
		}

		$forms = $form_handler->get( '', [ 'fields' => 'ids' ] );

		if ( empty( $forms ) || ! is_array( $forms ) ) {
			return true;
		}

		foreach ( $forms as $form_id ) {
			delete_post_meta( $form_id, 'wpforms_entries_count' );
		}

		return true;
	}
}
