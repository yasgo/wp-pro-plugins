<?php

namespace WPForms\Pro\Integrations\Divi;

use WPForms_Field_Phone;
use WPForms_Field_File_Upload;

/**
 * Class Divi.
 *
 * @since 1.6.3
 */
class Divi extends \WPForms\Integrations\Divi\Divi {

	/**
	 * WPForms frontend styles special for Divi.
	 *
	 * @since 1.8.1
	 */
	protected function divi_frontend_styles() {

		parent::divi_frontend_styles();

		$min = wpforms_get_min_suffix();

		// Deregister style 'wpforms-dropzone' already registered for Gutenberg.
		wp_deregister_style( 'wpforms-dropzone' );

		wp_register_style(
			'wpforms-dropzone',
			WPFORMS_PLUGIN_URL . "assets/pro/css/integrations/divi/dropzone{$min}.css",
			[],
			WPForms_Field_File_Upload::DROPZONE_VERSION
		);

		wp_enqueue_style(
			'wpforms-smart-phone-field',
			WPFORMS_PLUGIN_URL . "assets/pro/css/integrations/divi/intl-tel-input{$min}.css",
			[],
			WPForms_Field_Phone::INTL_VERSION
		);

		wp_enqueue_style(
			'wpforms-richtext-field',
			WPFORMS_PLUGIN_URL . "assets/pro/css/integrations/divi/richtext{$min}.css",
			[],
			WPFORMS_VERSION
		);

		wp_enqueue_style(
			'wpforms-content-field',
			WPFORMS_PLUGIN_URL . "assets/pro/css/integrations/divi/content{$min}.css",
			[],
			WPFORMS_VERSION
		);

		$styles_name = $this->get_current_styles_name();

		if ( empty( $styles_name ) ) {
			return;
		}

		wp_enqueue_style(
			"wpforms-divi-pro-{$styles_name}",
			WPFORMS_PLUGIN_URL . "assets/pro/css/integrations/divi/wpforms-{$styles_name}{$min}.css",
			[],
			WPFORMS_VERSION
		);
	}

	/**
	 * Register frontend styles.
	 * Required for the plugin version of builder only.
	 *
	 * @since 1.6.3
	 */
	public function frontend_styles() {

		if ( ! $this->is_divi_plugin_loaded() ) {
			return;
		}

		parent::frontend_styles();

		$this->divi_frontend_styles();
	}

	/**
	 * Load styles.
	 *
	 * @since 1.7.0
	 */
	public function builder_styles() {

		parent::builder_styles();

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-pro-integrations',
			WPFORMS_PLUGIN_URL . "assets/pro/css/admin-integrations{$min}.css",
			[],
			WPFORMS_VERSION
		);

		$this->divi_frontend_styles();
	}
}
