<?php

namespace WPForms\Pro\Frontend;

use WPForms\Frontend\Frontend as FrontendLite;

/**
 * Form front-end rendering for Pro.
 *
 * @since 1.8.1
 */
class Frontend extends FrontendLite {

	/**
	 * Load the CSS assets.
	 *
	 * @since 1.8.1
	 */
	public function assets_css() {

		parent::assets_css();

		// jQuery date/time library CSS.
		if (
			$this->assets_global() ||
			true === wpforms_has_field_type( 'date-time', $this->forms, true )
		) {
			wp_enqueue_style(
				'wpforms-jquery-timepicker',
				WPFORMS_PLUGIN_URL . 'assets/lib/jquery.timepicker/jquery.timepicker.min.css',
				[],
				'1.11.5'
			);
			wp_enqueue_style(
				'wpforms-flatpickr',
				WPFORMS_PLUGIN_URL . 'assets/lib/flatpickr/flatpickr.min.css',
				[],
				'4.6.9'
			);
		}

		$min         = wpforms_get_min_suffix();
		$disable_css = (int) wpforms_setting( 'disable-css', '1' );

		if ( $disable_css === 3 || $this->render_engine === 'classic' ) {
			return;
		}

		$style_name = $disable_css === 1 ? 'full' : 'base';

		wp_enqueue_style(
			"wpforms-pro-{$this->render_engine}-{$style_name}",
			WPFORMS_PLUGIN_URL . "assets/pro/css/frontend/{$this->render_engine}/wpforms-{$style_name}{$min}.css",
			[ "wpforms-{$this->render_engine}-{$style_name}" ],
			WPFORMS_VERSION
		);
	}

	/**
	 * Load the JS assets.
	 *
	 * @since 1.8.1
	 */
	public function assets_js() {

		parent::assets_js();

		if ( $this->amp_obj->is_amp() ) {
			return;
		}

		// Load jQuery date/time libraries.
		if (
			$this->assets_global() ||
			wpforms_has_field_type( 'date-time', $this->forms, true )
		) {
			wp_enqueue_script(
				'wpforms-flatpickr',
				WPFORMS_PLUGIN_URL . 'assets/lib/flatpickr/flatpickr.min.js',
				[ 'jquery' ],
				'4.6.9',
				true
			);
			wp_enqueue_script(
				'wpforms-jquery-timepicker',
				WPFORMS_PLUGIN_URL . 'assets/lib/jquery.timepicker/jquery.timepicker.min.js',
				[ 'jquery' ],
				'1.11.5',
				true
			);
		}
	}
}
