<?php

namespace WPForms\Pro\Admin\Builder;

/**
 * AntiSpam class.
 *
 * @since 1.7.8
 */
class AntiSpam extends \WPForms\Admin\Builder\AntiSpam {

	/**
	 * Register hooks.
	 *
	 * @since 1.7.8
	 */
	protected function hooks() {

		parent::hooks();
		add_action( 'wpforms_builder_enqueues', [ $this, 'builder_assets' ] );
	}

	/**
	 * Enqueue assets for the builder.
	 *
	 * @since 1.7.8
	 *
	 * @param string $view Current view.
	 */
	public function builder_assets( $view ) {

		$min = wpforms_get_min_suffix();

		// JavaScript.
		wp_enqueue_script(
			'wpforms-builder-anti-spam-filters',
			WPFORMS_PLUGIN_URL . "assets/pro/js/admin/builder/filters{$min}.js",
			[ 'jquery', 'choicesjs' ],
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			'wpforms-builder-anti-spam-filters',
			'wpforms_builder_anti_spam_filters',
			[
				'successfullReformatWarning' => __( 'Your keyword filter list has been reformatted. Please save these changes.', 'wpforms' ),
			]
		);
	}
}
