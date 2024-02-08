<?php

namespace WPForms\Pro\Admin\Entries;

/**
 * Helpers for entries functionality.
 *
 * @since 1.6.9
 */
class Helpers {

	/**
	 * Get field selector's Advanced Options optgroup items.
	 *
	 * @since 1.6.9
	 *
	 * @return array
	 */
	public static function get_search_fields_advanced_options() {

		$advanced_options = [
			'entry_id'    => esc_html__( 'Entry ID', 'wpforms' ),
			'entry_notes' => esc_html__( 'Entry Notes', 'wpforms' ),
			'ip_address'  => esc_html__( 'IP Address', 'wpforms' ),
			'user_agent'  => esc_html__( 'User Agent', 'wpforms' ),
		];

		/**
		 * Allow developers to filter the Advanced Options optgroup items in the field selector of the search form.
		 *
		 * @since 1.6.9
		 *
		 * @param array $advanced_options {
		 *     Advanced Options optgroup value/label pairs.
		 *
		 *     @type string $entry_id    Option label 'Entry ID'.
		 *     @type string $entry_notes Option label 'Entry Notes'.
		 *     @type string $ip_addr     Option label 'IP Address'.
		 *     @type string $user_agent  Option label 'User Agent'.
		 * }
		 */
		return (array) apply_filters( 'wpforms_pro_admin_entries_get_search_fields_advanced_options', $advanced_options );
	}
}
