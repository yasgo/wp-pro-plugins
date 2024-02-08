<?php

namespace WPForms\Pro\Integrations\WPorg;

use \WPForms\Integrations\WPorg\Translations as DefaultTranslations;

/**
 * Load translations from WordPress.org for the Lite version.
 *
 * @since 1.5.6
 */
class Translations extends DefaultTranslations {

	/**
	 * Load an integration.
	 *
	 * @since 1.5.6
	 * @since 1.6.9 Added custom event for the translations update.
	 */
	public function load() {

		// Load lite translations with other plugins translations check.
		add_filter( 'http_request_args', [ $this, 'request_lite_translations' ], 10, 2 );

		// Download translations when plugin upgrade from Lite to Pro.
		add_action( 'wpforms_install', [ $this, 'download_translations' ] );

		parent::load();
	}

	/**
	 * Add WPForms Lite translation files to the update checklist of installed plugins, to check for new translations.
	 *
	 * @since 1.5.6
	 *
	 * @param array  $args HTTP Request arguments to modify.
	 * @param string $url  The HTTP request URI that is executed.
	 *
	 * @return array The modified Request arguments to use in the update request.
	 */
	public function request_lite_translations( $args, $url ) {

		// Only do something on upgrade requests.
		if ( strpos( $url, 'api.wordpress.org/plugins/update-check' ) === false ) {
			return $args;
		}

		/*
		 * Checking this by name because the installed path is not guaranteed.
		 * The capitalized json data defines the array keys, therefore we need to check and define these as such.
		 */
		$plugins      = json_decode( $args['body']['plugins'], true );
		$wpforms_data = [];

		foreach ( $plugins['plugins'] as $data ) {
			if ( ! isset( $data['Name'] ) ) {
				continue;
			}

			// If WPForms Lite is already in the list, don't add it again.
			if ( $data['Name'] === 'WPForms Lite' ) {
				return $args;
			}

			// Check real data for WPForms plugin.
			if ( $data['Name'] === 'WPForms' ) {
				$wpforms_data = $data;
			}
		}

		if ( empty( $wpforms_data ) ) {
			return $args;
		}

		/*
		 * Add an entry to the list that matches the WordPress.org slug for WPForms Lite.
		 *
		 * This entry is based on the currently present data from this plugin, to make sure the version and textdomain
		 * settings are as expected. Take care of the capitalized array key as before.
		 */
		$plugins['plugins']['wpforms-lite/wpforms.php'] = $wpforms_data;

		// Override the name of the plugin.
		$plugins['plugins']['wpforms-lite/wpforms.php']['Name'] = 'WPForms Lite';

		// Override the version of the plugin to prevent increasing the update count.
		$plugins['plugins']['wpforms-lite/wpforms.php']['Version'] = '9999.0';

		// Overwrite the plugins argument in the body to be sent in the upgrade request.
		$args['body']['plugins'] = wp_json_encode( $plugins );

		return $args;
	}
}
