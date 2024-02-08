<?php

namespace WPForms\Pro\Integrations\PopupMaker;

use WPForms\Integrations\IntegrationInterface;

/**
 * Class PopupMaker.
 *
 * @since 1.7.9
 */
class PopupMaker implements IntegrationInterface {

	/**
	 * Field types that will be affected by this fix.
	 *
	 * @since 1.7.9
	 *
	 * @var array
	 */
	const FIELDS = [
		'date-time',
		'richtext',
	];

	/**
	 * Indicate if current integration is allowed to load.
	 *
	 * @since 1.7.9
	 *
	 * @return bool
	 */
	public function allow_load() {

		// Should return true when the plugin is active.
		return class_exists( 'Popup_Maker', true );
	}

	/**
	 * Load an integration.
	 *
	 * @since 1.7.9
	 */
	public function load() {

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.7.9
	 */
	private function hooks() {

		add_action( 'wpforms_frontend_css', [ $this, 'zindex_fix' ] );
	}

	/**
	 * Add extra CSS styles to address the overlapping issue.
	 * This fix ensures popovers are visible when triggered inside of a popup.
	 *
	 * @since 1.7.9
	 *
	 * @param array $forms Forms data. Result of getting multiple forms.
	 *
	 * @return void
	 */
	public function zindex_fix( $forms ) {

		// Bail early, in case the current form requires no fixing.
		if ( ! wpforms_has_field_type( self::FIELDS, $forms, true ) ) {
			return;
		}

		// The following z-index value is the same as what "Popup Maker" applies to its overlay container.
		// Note that `! important` is added to resolve specificity with other inline styles added directly to elements.
		?>
		<style>
			.pum-open-overlay .mce-floatpanel,
			.pum-open-overlay .ui-timepicker-wrapper,
			.pum-open-overlay .flatpickr-calendar.open {
				z-index: 1999999999 !important;
			}
		</style>
		<?php
	}

}
