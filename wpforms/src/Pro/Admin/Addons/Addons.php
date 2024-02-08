<?php

namespace WPForms\Pro\Admin\Addons;

use WPForms\Helpers\Transient;

/**
 * Addons data handler for Pro.
 *
 * @since 1.6.6
 */
class Addons extends \WPForms\Admin\Addons\Addons {

	/**
	 * License data.
	 *
	 * @since 1.6.6
	 *
	 * @var array
	 */
	protected $license;

	/**
	 * Init.
	 *
	 * @since 1.6.6
	 */
	public function init() {

		if ( ! parent::allow_load() ) {
			return;
		}

		parent::init();

		// Load license data.
		$this->license['key']  = wpforms_get_license_key();
		$this->license['type'] = wpforms_get_license_type();
	}

	/**
	 * Return status of an addon.
	 *
	 * @since 1.6.6
	 *
	 * @param string $slug Addon slug.
	 *
	 * @return string One of the following: active | installed | missing.
	 */
	protected function get_status( $slug ) {

		$slug   = 'wpforms-' . str_replace( 'wpforms-', '', $slug );
		$plugin = sprintf( '%1$s/%1$s.php', sanitize_key( $slug ) );

		if ( is_plugin_active( $plugin ) ) {
			return 'active';
		}

		$plugins = get_plugins();

		if ( ! empty( $plugins[ $plugin ] ) ) {
			return 'installed';
		}

		return 'missing';
	}

	/**
	 * Prepare addon data.
	 *
	 * @since 1.6.6
	 *
	 * @param array $addon Addon data.
	 *
	 * @return array|bool
	 */
	protected function prepare_addon_data( $addon ) {

		$addon = parent::prepare_addon_data( $addon );

		$addon['status'] = $this->get_status( $addon['slug'] );

		if ( $addon['status'] === 'active' && $addon['plugin_allow'] ) {
			$addon['action'] = '';

			return $addon;
		}

		if ( $addon['status'] === 'installed' && $addon['plugin_allow'] ) {
			$addon['action'] = 'activate';
		} else {
			if ( ! $this->license['type'] ) {
				$addon['action'] = 'license';
			} elseif ( $addon['plugin_allow'] ) {
				$addon['action'] = 'install';
				$addon['url']    = $this->get_url( $addon['slug'] );
			} else {
				$addon['action'] = 'upgrade';
			}
		}

		return $addon;
	}

	/**
	 * Determine if user's license level has access.
	 *
	 * @since 1.6.6
	 *
	 * @param array $addon Addon data.
	 *
	 * @return bool
	 */
	protected function has_access( $addon ) {

		$license = in_array( $this->license['type'], [ 'agency', 'ultimate' ], true ) ? 'elite' : $this->license['type'];

		return ! empty( $addon['license'] ) && is_array( $addon['license'] ) && in_array( $license, $addon['license'], true );
	}

	/**
	 * Return download URL for an addon.
	 *
	 * @since 1.6.6
	 *
	 * @param string $slug Addon slug.
	 *
	 * @return string
	 */
	protected function get_url( $slug ) {

		$urls = $this->get_urls();

		return empty( $urls[ $slug ] ) ? '' : $urls[ $slug ];
	}

	/**
	 * Retrieve addon URLs from the stored transient or remote server.
	 *
	 * @since 1.6.6
	 *
	 * @param bool $force Whether to force the addons retrieval or re-use option cache.
	 *
	 * @return array
	 */
	protected function get_urls( $force = false ) {

		if ( empty( $this->license['key'] ) ) {
			return [];
		}

		if ( $force ) {
			return $this->get_remote_urls();
		}

		$urls = Transient::get( 'addons_urls' );

		// We store an empty array if the request isn't valid to prevent spam requests.
		if ( is_array( $urls ) ) {
			return $urls;
		}

		return $this->get_remote_urls();
	}

	/**
	 * Fetch addon URLs from the remote server.
	 *
	 * @since 1.6.6
	 *
	 * @return array List of addon URLs data.
	 */
	protected function get_remote_urls() {

		$addons = wpforms()->get( 'license' )->get_addons();

		// If there was an API error, set transient for only 10 minutes.
		if ( empty( $addons ) ) {
			Transient::set( 'addons_urls', [], 10 * MINUTE_IN_SECONDS );

			return [];
		}

		$urls = [];

		foreach ( (array) $addons as $addon ) {
			if ( ! empty( $addon->slug ) ) {
				$urls[ $addon->slug ] = ! empty( $addon->url ) ? $addon->url : '';
			}
		}

		// Otherwise, our request worked. Save the data and return it.
		Transient::set( 'addons_urls', $urls, DAY_IN_SECONDS );

		return $urls;
	}
}
