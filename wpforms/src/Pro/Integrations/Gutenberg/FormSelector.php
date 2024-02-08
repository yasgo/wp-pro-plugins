<?php

namespace WPForms\Pro\Integrations\Gutenberg;

// phpcs:ignore WPForms.PHP.UseStatement.UnusedUseStatement
use \WPForms\Integrations\Gutenberg\FormSelector as FormSelectorLite;

/**
 * Form Selector Gutenberg block with live preview.
 *
 * @since 1.7.0
 */
class FormSelector extends FormSelectorLite {

	/**
	 * Register WPForms Gutenberg block styles.
	 *
	 * @since 1.7.4.2
	 */
	protected function register_styles() {

		parent::register_styles();

		if ( ! is_admin() ) {
			return;
		}

		$min                 = wpforms_get_min_suffix();
		$disable_css_setting = (int) wpforms_setting( 'disable-css', '1' );
		$deps                = [ 'wpforms-integrations' ];

		if ( $disable_css_setting !== 3 && $this->render_engine !== 'classic' ) {
			$css_file = $disable_css_setting === 2 ? 'base' : 'full';
			$deps     = [ 'wpforms-pro-gutenberg-form-selector' ];

			wp_register_style(
				'wpforms-pro-gutenberg-form-selector',
				WPFORMS_PLUGIN_URL . "assets/pro/css/frontend/{$this->render_engine}/wpforms-{$css_file}{$min}.css",
				[ 'wpforms-gutenberg-form-selector' ],
				WPFORMS_VERSION
			);
		}

		wp_register_style(
			'wpforms-pro-integrations',
			WPFORMS_PLUGIN_URL . "assets/pro/css/admin-integrations{$min}.css",
			$deps,
			WPFORMS_VERSION
		);
	}

	/**
	 * Load WPForms Gutenberg block scripts.
	 *
	 * @since 1.7.0
	 */
	public function enqueue_block_editor_assets() {

		parent::enqueue_block_editor_assets();

		wp_enqueue_style( 'wpforms-pro-integrations' );
	}
}
