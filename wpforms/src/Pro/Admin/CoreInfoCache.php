<?php

namespace WPForms\Pro\Admin;

use WPForms\Helpers\CacheBase;

/**
 * Class CoreInfoCache handles plugin information caching.
 *
 * @since 1.8.6
 */
class CoreInfoCache extends CacheBase {

	/**
	 * Determine if the class is allowed to load.
	 *
	 * @since 1.8.6
	 *
	 * @return bool
	 */
	protected function allow_load(): bool {
		// phpcs:ignore WPForms.Formatting.EmptyLineBeforeReturn.RemoveEmptyLineBeforeReturnStatement
		return is_admin() || wp_doing_cron() || wpforms_doing_wp_cli();
	}

	/**
	 * Provide settings.
	 *
	 * @since 1.8.6
	 *
	 * @return array Settings array.
	 */
	protected function setup(): array {
		// phpcs:ignore WPForms.Formatting.EmptyLineBeforeReturn.RemoveEmptyLineBeforeReturnStatement
		return [

			// Remote source URL.
			'remote_source' => 'https://wpforms.com/wp-content/core.json',

			// Addons cache file name.
			'cache_file'    => 'core.json',

			/**
			 * Time-to-live of the core information cache file in seconds.
			 *
			 * This applies to `uploads/wpforms/cache/core.json` file.
			 *
			 * @since 1.8.6
			 *
			 * @param integer $cache_ttl Cache time-to-live, in seconds.
			 *                           Default value: WEEK_IN_SECONDS.
			 */
			'cache_ttl'     => (int) apply_filters( 'wpforms_pro_admin_core_info_cache_ttl', WEEK_IN_SECONDS ),

			// Scheduled update action.
			'update_action' => 'wpforms_pro_core_info_cache_update',
		];
	}

	/**
	 * Prepare core info to store in a local cache..
	 *
	 * @since 1.8.6
	 *
	 * @param array $data Raw remote core data.
	 *
	 * @return array Prepared data for caching.
	 */
	protected function prepare_cache_data( $data ): array {

		if ( empty( $data[0] ) ) {
			return [];
		}

		$cache = $data[0];

		// The remote file doesn't contain these icons, but we need them.
		// The icons are used on the Dashboard > Updates page.
		$cache['icons'] = [
			'1x'      => 'https://plugins.svn.wordpress.org/wpforms-lite/assets/icon-128x128.png',
			'2x'      => 'https://plugins.svn.wordpress.org/wpforms-lite/assets/icon-256x256.png',
			'default' => 'https://plugins.svn.wordpress.org/wpforms-lite/assets/icon-256x256.png',
		];

		return $cache;
	}
}
