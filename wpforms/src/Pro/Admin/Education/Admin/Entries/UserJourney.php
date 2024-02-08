<?php

namespace WPForms\Pro\Admin\Education\Admin\Entries;

use \WPForms\Admin\Education\AddonsItemBase;

/**
 * Admin/Entries/UserJourney Education feature.
 *
 * @since 1.7.5
 */
class UserJourney extends AddonsItemBase {

	/**
	 * Addon slug.
	 *
	 * @since 1.7.5
	 */
	const SLUG = 'wpforms-user-journey';

	/**
	 * Addon slug without "wpforms-".
	 *
	 * @since 1.7.5
	 */
	const CLEAR_SLUG = 'user-journey';

	/**
	 * Indicate if current Education feature is allowed to load.
	 *
	 * @since 1.7.5
	 *
	 * @return bool
	 */
	public function allow_load() {

		return wpforms_is_admin_page( 'entries', 'details' );
	}

	/**
	 * Hooks.
	 *
	 * @since 1.7.5
	 */
	public function hooks() {

		add_action( 'wpforms_entry_details_content', [ $this, 'display' ], 20 );
	}

	/**
	 * Display User Journey preview metabox.
	 *
	 * @since 1.7.5
	 */
	public function display() {

		$dismissed = get_user_meta( get_current_user_id(), 'wpforms_dismissed', true );

		if ( ! empty( $dismissed[ 'edu-admin-' . static::CLEAR_SLUG . '-metabox' ] ) ) {
			return;
		}

		$addon = $this->addons->get_addon( static::SLUG );

		if (
			empty( $addon ) ||
			empty( $addon['status'] ) ||
			empty( $addon['action'] ) || (
				$addon['status'] === 'active' && $addon['action'] !== 'upgrade'
			)
		) {
			return;
		}

		$this->single_addon_template = 'education/admin/entries/' . static::CLEAR_SLUG;

		$this->display_single_addon( $addon );
	}
}
