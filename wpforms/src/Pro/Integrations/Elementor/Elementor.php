<?php

namespace WPForms\Pro\Integrations\Elementor;

use Elementor\Plugin as ElementorPlugin;

/**
 * Improve Elementor Compatibility.
 *
 * @since 1.7.0
 */
class Elementor extends \WPForms\Integrations\Elementor\Elementor {

	/**
	 * Load assets in the preview panel.
	 *
	 * @since 1.7.0
	 */
	public function preview_assets() {

		if ( ! ElementorPlugin::$instance->preview->is_preview_mode() ) {
			return;
		}

		parent::preview_assets();

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-pro-integrations',
			WPFORMS_PLUGIN_URL . "assets/pro/css/admin-integrations{$min}.css",
			[],
			WPFORMS_VERSION
		);
	}

	/**
	 * Load assets in the elementor document.
	 *
	 * @since 1.7.0
	 */
	public function editor_assets() {

		if ( empty( $_GET['action'] ) || $_GET['action'] !== 'elementor' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		parent::editor_assets();

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-pro-integrations',
			WPFORMS_PLUGIN_URL . "assets/pro/css/admin-integrations{$min}.css",
			[],
			WPFORMS_VERSION
		);
	}
}
