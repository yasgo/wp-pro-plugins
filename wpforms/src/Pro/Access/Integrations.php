<?php

namespace WPForms\Pro\Access;

/**
 * Access/Capability integrations with third-party plugins.
 *
 * @since 1.5.8
 */
class Integrations {

	/**
	 * Init class.
	 *
	 * @since 1.5.8
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Capabilities integrations hooks.
	 *
	 * @since 1.5.8
	 */
	public function hooks() {

		// Members plugin.
		add_action( 'admin_enqueue_scripts', [ $this, 'members_enqueue_scripts' ] );
		add_action( 'members_register_cap_groups', [ $this, 'members_register_cap_group' ] );
		add_action( 'members_register_caps', [ $this, 'members_register_caps' ] );
		add_filter( 'members_get_capabilities', [ $this, 'members_get_capabilities' ] );

		// User Role Editor plugin.
		add_filter( 'ure_capabilities_groups_tree', [ $this, 'ure_capabilities_groups_tree' ] );
		add_filter( 'ure_custom_capability_groups', [ $this, 'ure_custom_capability_groups' ], 10, 2 );
		add_filter( 'ure_full_capabilites', [ $this, 'ure_full_capabilities' ], 10, 2 );
	}

	/**
	 * Get capabilities to remove from the plugins capabilities lists.
	 *
	 * @since 1.5.8
	 *
	 * @return array
	 */
	public function get_remove_caps() {

		$remove_caps   = [];
		$remove_groups = [ 'wpforms_forms', 'wpforms_logs', 'wpforms_announcements', 'wpforms-lite_announcements' ];

		foreach ( $remove_groups as $remove_group ) {
			\array_push( $remove_caps, 'edit_' . $remove_group, 'edit_others_' . $remove_group, 'publish_' . $remove_group, 'read_private_' . $remove_group );
		}

		return $remove_caps;
	}

	/**
	 * Enqueue scripts on Members plugin role editing screen.
	 *
	 * @since 1.5.8
	 */
	public function members_enqueue_scripts() {

		if ( ! function_exists( 'members_register_cap_group' ) ) {
			return;
		}

		$screen = get_current_screen();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $screen->id, $_GET['action'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $screen->id !== 'users_page_roles' && $_GET['action'] !== 'edit' ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-dashicons',
			WPFORMS_PLUGIN_URL . "assets/css/frontend/wpforms-dashicons{$min}.css"
		);
	}

	/**
	 * Register WPForms capabilities group in the Members plugin.
	 *
	 * @since 1.5.8
	 */
	public function members_register_cap_group() {

		members_register_cap_group(
			'wpforms',
			[
				'label' => esc_html__( 'WPForms', 'wpforms' ),
				'icon'  => 'dashicons-wpforms',
				'caps'  => [],
			]
		);

		array_map( 'members_unregister_cap_group', [ 'type-wpforms', 'type-wpforms_log', 'type-amn_wpforms', 'type-amn_wpforms-lite' ] );
	}

	/**
	 * Remove WPForms CPT capabilities from Members plugin "All" tab.
	 *
	 * @since 1.5.8
	 *
	 * @param array $list Current capabilities list.
	 *
	 * @return array
	 */
	public function members_get_capabilities( $list ) {

		$list = \array_diff( $list, $this->get_remove_caps() );

		return $list;
	}

	/**
	 * Register WPForms capabilities in the Members plugin.
	 *
	 * @since 1.5.8
	 */
	public function members_register_caps() {

		$caps = wpforms()->get( 'access' )->get_caps();

		foreach ( $caps as $cap => $label ) {
			members_register_cap(
				$cap,
				[
					'label' => $label,
					'group' => 'wpforms',
				]
			);
		}
	}

	/**
	 * Register WPForms capabilities group in the User Role Editor plugin.
	 *
	 * @since 1.5.8
	 *
	 * @param array $groups Current capability groups.
	 *
	 * @return array
	 */
	public function ure_capabilities_groups_tree( $groups = [] ) {

		$groups['wpforms_caps'] = [
			'caption' => esc_html__( 'WPForms', 'wpforms' ),
			'parent'  => 'custom',
			'level'   => 2,
		];

		$remove_groups = wpforms_list_only( $groups, [ 'wpforms', 'wpforms_log', 'amn_wpforms' ] );

		foreach ( $remove_groups as $key => $data ) {
			if ( isset( $groups[ $key ]['parent'] ) && $groups[ $key ]['parent'] === 'custom_post_types' ) {
				unset( $groups[ $key ] );
			}
		}

		return $groups;
	}

	/**
	 * Register WPForms capabilities in the User Role Editor plugin.
	 *
	 * @since 1.5.8
	 *
	 * @param array  $groups Current capability groups.
	 * @param string $cap_id Capability ID.
	 *
	 * @return array
	 */
	public function ure_custom_capability_groups( $groups = [], $cap_id = '' ) {

		// Get WPForms capabilities.
		$caps = array_keys( wpforms()->get( 'access' )->get_caps() );

		// If capability belongs to WPForms, register it to a group.
		if ( \in_array( $cap_id, $caps, true ) ) {
			$groups[] = 'wpforms_caps';
		}

		return $groups;
	}

	/**
	 * Remove WPForms CPT capabilities from User Role Editor plugin "All" tab.
	 *
	 * @since 1.5.8
	 *
	 * @param array $list Current capabilities list.
	 *
	 * @return array
	 */
	public function ure_full_capabilities( $list ) {

		$caps = wpforms()->get( 'access' )->get_caps();

		foreach ( $caps as $cap_id => $cap_name ) {
			$list[ $cap_id ] = [
				'inner'   => $cap_id,
				'human'   => $cap_name,
				'wp_core' => false,
			];
		}

		$list = \array_diff_key( $list, \array_flip( $this->get_remove_caps() ) );

		return $list;
	}
}
