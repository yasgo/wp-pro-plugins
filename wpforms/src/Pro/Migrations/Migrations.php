<?php

namespace WPForms\Pro\Migrations;

use WPForms\Migrations\Base;
use WPForms\Migrations\Migrations as MigrationsLite;
use WPForms_Entry_Fields_Handler;
use WPForms_Entry_Handler;
use WPForms_Entry_Meta_Handler;

/**
 * Class Migrations handles Pro plugin upgrade routines.
 *
 * @since 1.7.5
 */
class Migrations extends Base {

	/**
	 * WP option name to store the migration version.
	 *
	 * @since 1.5.9
	 */
	const MIGRATED_OPTION_NAME = 'wpforms_versions';

	/**
	 * Name of the core plugin used in log messages.
	 *
	 * @since 1.7.5
	 */
	const PLUGIN_NAME = 'WPForms Pro';

	/**
	 * Upgrade classes.
	 *
	 * @since 1.7.5
	 */
	const UPGRADE_CLASSES = [
		'Upgrade116',
		'Upgrade133',
		'Upgrade143',
		'Upgrade150',
		'Upgrade159',
		'Upgrade165',
		'Upgrade168',
		'Upgrade173',
		'Upgrade175',
		'Upgrade176',
		'Upgrade182',
		'Upgrade183',
	];

	/**
	 * Custom table handler classes.
	 *
	 * @since 1.7.6
	 */
	const CUSTOM_TABLE_HANDLER_CLASSES = [
		WPForms_Entry_Handler::class,
		WPForms_Entry_Fields_Handler::class,
		WPForms_Entry_Meta_Handler::class,
	];

	/**
	 * Class init.
	 *
	 * @since 1.7.5
	 */
	public function init() {

		// Run Lite migrations first.
		( new MigrationsLite() )->init();

		$wpforms_pro = wpforms()->get( 'pro' );

		if ( ! $wpforms_pro ) {
			return;
		}

		$wpforms_pro->objects();

		// Run Pro migrations.
		parent::init();
	}
}
