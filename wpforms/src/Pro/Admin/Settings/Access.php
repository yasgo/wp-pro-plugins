<?php

namespace WPForms\Pro\Admin\Settings;

use WPForms\Pro\Admin\DashboardWidget;

/**
 * Access management settings panel.
 *
 * @since 1.5.8
 */
class Access {

	/**
	 * View slug.
	 *
	 * @since 1.5.8
	 *
	 * @var string
	 */
	const SLUG = 'access';

	/**
	 * Init class.
	 *
	 * @since 1.5.8
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Access settings panel hooks.
	 *
	 * @since 1.5.8
	 */
	public function hooks() {

		add_filter( 'wpforms_settings_tabs', [ $this, 'add_tab' ] );
		add_filter( 'wpforms_settings_defaults', [ $this, 'add_section' ] );
		add_filter( 'wpforms_settings_exclude_view', [ $this, 'exclude_view' ] );
		add_filter( 'wpforms_settings_custom_process', [ $this, 'process_settings' ], 10, 2 );

		if ( wpforms_is_admin_page( 'settings', 'access' ) ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueues' ] );
		}
	}

	/**
	 * Load enqueues.
	 *
	 * @since 1.5.8.2
	 */
	public function enqueues() {

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-settings-access',
			WPFORMS_PLUGIN_URL . "assets/pro/js/admin/settings-access{$min}.js",
			[ 'jquery', 'jquery-confirm' ],
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			'wpforms-settings-access',
			'wpforms_settings_access',
			[
				'labels' => [
					'caps'  => wpforms()->get( 'access' )->get_caps(),
					'roles' => wp_list_pluck( get_editable_roles(), 'name' ),
				],
				'l10n'   => [
					/* translators: %1$s - capability being granted, %2$s - capability(s) required for a capability being granted, %3$s - role a capability is granted to. */
					'grant_caps'  => '<p>' . esc_html__( 'In order to give %1$s access, %2$s access is also required.', 'wpforms' ) . '</p><p>' . esc_html__( 'Would you like to also grant %2$s access to %3$s?', 'wpforms' ) . '</p>',
					/* translators: %1$s - capability being granted, %2$s - capability(s) required for a capability being granted, %3$s - role a capability is granted to. */
					'remove_caps' => '<p>' . esc_html__( 'In order to remove %1$s access, %2$s access is also required to be removed.', 'wpforms' ) . '</p><p>' . esc_html__( 'Would you like to also remove %2$s access from %3$s?', 'wpforms' ) . '</p>',
				],
			]
		);
	}

	/**
	 * Get forms caps settings labels.
	 *
	 * @since 1.8.4
	 *
	 * @return array
	 */
	protected function get_forms_caps_settings_labels() {

		return [
			'create_forms' => [
				'title' => esc_html__( 'Create Forms', 'wpforms' ),
				'caps'  => [
					'wpforms_create_forms' => [
						'title' => '',
						'desc'  => wp_kses(
							__( 'Can create new forms.', 'wpforms' ),
							[
								'strong' => [],
							]
						),
					],
				],
			],
			'view_forms'   => [
				'title' => esc_html__( 'View Forms', 'wpforms' ),
				'caps'  => [
					'wpforms_view_own_forms'    => [
						'desc' => wp_kses(
							__( 'Can view forms created by <strong>themselves</strong>.', 'wpforms' ),
							[
								'strong' => [],
							]
						),
					],
					'wpforms_view_others_forms' => [
						'desc' => wp_kses(
							__( 'Can view forms created by <strong>others</strong>.', 'wpforms' ),
							[
								'strong' => [],
							]
						),
					],
				],
			],
			'edit_forms'   => [
				'title' => esc_html__( 'Edit Forms', 'wpforms' ),
				'caps'  => [
					'wpforms_edit_own_forms'    => [
						'desc' => wp_kses(
							__( 'Can edit forms created by <strong>themselves</strong>.', 'wpforms' ),
							[
								'strong' => [],
							]
						),
					],
					'wpforms_edit_others_forms' => [
						'desc' => wp_kses(
							__( 'Can edit forms created by <strong>others</strong>.', 'wpforms' ),
							[
								'strong' => [],
							]
						),
					],
				],
			],
			'delete_forms' => [
				'title' => esc_html__( 'Delete Forms', 'wpforms' ),
				'caps'  => [
					'wpforms_delete_own_forms'    => [
						'desc' => wp_kses(
							__( 'Can delete forms created by <strong>themselves</strong>.', 'wpforms' ),
							[
								'strong' => [],
							]
						),
					],
					'wpforms_delete_others_forms' => [
						'desc' => wp_kses(
							__( 'Can delete forms created by <strong>others</strong>.', 'wpforms' ),
							[
								'strong' => [],
							]
						),
					],
				],
			],
		];
	}

	/**
	 * Get entries caps settings labels.
	 *
	 * @since 1.8.4
	 *
	 * @return array
	 */
	protected function get_entries_caps_settings_labels() {

		return [
			'view_entries'   => [
				'title' => esc_html__( 'View Entries', 'wpforms' ),
				'caps'  => [
					'wpforms_view_entries_own_forms'    => [
						'desc' => wp_kses(
							__( 'Can view entries of forms created by <strong>themselves</strong>.', 'wpforms' ),
							[
								'strong' => [],
							]
						),
					],
					'wpforms_view_entries_others_forms' => [
						'desc' => wp_kses(
							__( 'Can view entries of forms created by <strong>others</strong>.', 'wpforms' ),
							[
								'strong' => [],
							]
						),
					],
				],
			],
			'edit_entries'   => [
				'title' => esc_html__( 'Edit Entries', 'wpforms' ),
				'caps'  => [
					'wpforms_edit_entries_own_forms'    => [
						'desc' => wp_kses(
							__( 'Can edit entries of forms created by <strong>themselves</strong>.', 'wpforms' ),
							[
								'strong' => [],
							]
						),
					],
					'wpforms_edit_entries_others_forms' => [
						'desc' => wp_kses(
							__( 'Can edit entries of forms created by <strong>others</strong>.', 'wpforms' ),
							[
								'strong' => [],
							]
						),
					],
				],
			],
			'delete_entries' => [
				'title' => esc_html__( 'Delete Entries', 'wpforms' ),
				'caps'  => [
					'wpforms_delete_entries_own_forms'    => [
						'desc' => wp_kses(
							__( 'Can delete entries of forms created by <strong>themselves</strong>.', 'wpforms' ),
							[
								'strong' => [],
							]
						),
					],
					'wpforms_delete_entries_others_forms' => [
						'desc' => wp_kses(
							__( 'Can delete entries of forms created by <strong>others</strong>.', 'wpforms' ),
							[
								'strong' => [],
							]
						),
					],
				],
			],
		];
	}

	/**
	 * Add Access settings tab on the left of Misc tab.
	 *
	 * @since 1.5.8
	 *
	 * @param array $tabs Settings tabs.
	 *
	 * @return array
	 */
	public function add_tab( $tabs ) {

		$tab = [
			self::SLUG => [
				'name'   => \esc_html__( 'Access', 'wpforms' ),
				'form'   => true,
				'submit' => \esc_html__( 'Save Settings', 'wpforms' ),
			],
		];

		return \wpforms_list_insert_after( $tabs, 'geolocation', $tab );
	}

	/**
	 * Add Access settings section.
	 *
	 * @since 1.5.8
	 *
	 * @param array $settings Settings sections.
	 *
	 * @return array
	 */
	public function add_section( $settings ) {

		$settings[ self::SLUG ][ self::SLUG . '-heading' ] = [
			'id'       => self::SLUG . '-heading',
			'content'  => '<h4>' . esc_html__( 'Access', 'wpforms' ) . '</h4><p>' .
			sprintf(
				wp_kses( /* translators: %s - WPForms.com access control link. */
					__( 'By default, all permissions are provided only to administrator users. Please see our <a href="%s" target="_blank" rel="noopener noreferrer">Access Controls documentation</a> for full details.', 'wpforms' ),
					[
						'a' => [
							'href'   => [],
							'target' => [],
							'rel'    => [],
						],
					]
				),
				esc_url( wpforms_utm_link( 'https://wpforms.com/docs/how-to-set-up-access-controls-in-wpforms/', 'Settings - Access', 'Access Control Documentation' ) )
			)
			. '</p>',
			'type'     => 'content',
			'no_label' => true,
			'class'    => [ 'section-heading' ],
		];

		$roles      = get_editable_roles();
		$caps       = wpforms()->get( 'access' )->get_caps();
		$master_cap = wpforms_get_capability_manage_options();
		$options    = [];
		$role_caps  = [];

		// Get a list of assigned capabilities for every role.
		foreach ( $roles as $role => $details ) {
			if ( $role === $master_cap || ! empty( $details['capabilities'][ $master_cap ] ) ) {
				continue;
			}
			$options[ $role ]   = $details['name'];
			$role_caps[ $role ] = array_intersect_key( $caps, array_filter( $details['capabilities'] ) );
		}

		$forms_section   = $this->get_forms_section( $role_caps, $caps, $options );
		$entries_section = $this->get_entries_section( $role_caps, $caps, $options );

		$settings[ self::SLUG ] = array_merge( $settings[ self::SLUG ], $forms_section, $entries_section );

		return $settings;
	}

	/**
	 * Get Forms section settings.
	 *
	 * @since 1.8.4
	 *
	 * @param array $role_caps Set of roles with assigned capabilities.
	 * @param array $caps      Set of capabilities.
	 * @param array $options   Set of roles with names.
	 *
	 * @return array
	 */
	protected function get_forms_section( $role_caps, $caps, $options ) {

		$settings[ self::SLUG . '-forms-heading' ] = [
			'id'       => self::SLUG . '-forms-heading',
			'content'  => '<h4>' . esc_html__( 'Forms', 'wpforms' ) . '</h4><p>' . esc_html__( 'Select the user roles that are allowed to manage forms.', 'wpforms' ) . '</p>',
			'type'     => 'content',
			'no_label' => true,
			'class'    => [ 'section-heading' ],
		];

		$labels = $this->get_forms_caps_settings_labels();

		$forms_settings = $this->get_settings( $labels, $role_caps, $caps, $options );

		return array_merge( $settings, $forms_settings );
	}

	/**
	 * Get Entries section settings.
	 *
	 * @since 1.8.4
	 *
	 * @param array $role_caps Set of roles with assigned capabilities.
	 * @param array $caps      Set of capabilities.
	 * @param array $options   Set of roles with names.
	 *
	 * @return array
	 */
	protected function get_entries_section( $role_caps, $caps, $options ) {

		$settings[ self::SLUG . '-entries-heading' ] = [
			'id'       => self::SLUG . '-entries-heading',
			'content'  => '<h4>' . esc_html__( 'Entries', 'wpforms' ) . '</h4><p>' . esc_html__( 'Select the user roles that are allowed to manage entries.', 'wpforms' ) . '</p>',
			'type'     => 'content',
			'no_label' => true,
			'class'    => [ 'section-heading' ],
		];

		$labels = $this->get_entries_caps_settings_labels();

		$entries_settings = $this->get_settings( $labels, $role_caps, $caps, $options );

		return array_merge( $settings, $entries_settings );
	}

	/**
	 * Get settings for a section.
	 *
	 * @since 1.8.4
	 *
	 * @param array $labels    Set of labels for a section.
	 * @param array $role_caps Set of roles with assigned capabilities.
	 * @param array $caps      Set of capabilities.
	 * @param array $options   Set of roles with names.
	 *
	 * @return array
	 */
	protected function get_settings( $labels, $role_caps, $caps, $options ) {

		$settings = [];

		foreach ( $labels as $row_id => $row ) {

			$columns = [];

			foreach ( $row['caps'] as $cap_id => $cap ) {

				$selected = array_keys( wp_list_filter( $role_caps, [ $cap_id => $caps[ $cap_id ] ] ) );

				$columns[ $cap_id ] = [
					'id'        => $cap_id,
					'desc'      => $cap['desc'],
					'type'      => 'select',
					'choicesjs' => true,
					'multiple'  => true,
					'options'   => $options,
					'selected'  => $selected,
					'data'      => [ 'cap' => $cap_id ],
				];
			}

			$settings[ $row_id ] = [
				'id'      => $row_id,
				'name'    => esc_html( $row['title'] ),
				'type'    => 'columns',
				'columns' => $columns,
			];
		}

		return $settings;
	}

	/**
	 * Exclude Access settings from a saved settings list.
	 *
	 * @since 1.5.8
	 *
	 * @param array $exclude_views Views to exclude from saving.
	 *
	 * @return array
	 */
	public function exclude_view( $exclude_views ) {

		$exclude_views[] = self::SLUG;

		return $exclude_views;
	}

	/**
	 * Run own processing of a settings view.
	 *
	 * @since 1.5.8
	 *
	 * @param string $view Settings view slug.
	 * @param array  $rows Set of settings fields rows for Access view.
	 */
	public function process_settings( $view, $rows ) {

		if ( $view !== self::SLUG ) {
			return;
		}

		// Check nonce and other various security checks.
		if ( ! isset( $_POST['wpforms-settings-submit'] ) || empty( $_POST['nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpforms-settings-nonce' ) ) {
			return;
		}

		if ( ! wpforms_current_user_can() ) {
			return;
		}

		$columns = wp_filter_object_list( $rows, [ 'type' => 'columns' ], 'and', 'columns' );

		foreach ( $columns as $column ) {

			if ( empty( $column ) || ! is_array( $column ) ) {
				continue;
			}

			foreach ( $column as $cap_id => $cap ) {

				$value      = isset( $_POST[ $cap_id ] ) && is_array( $_POST[ $cap_id ] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ $cap_id ] ) ) : [];
				$value_prev = isset( $cap['selected'] ) ? $cap['selected'] : [];

				$add_cap_roles    = array_diff( $value, $value_prev );
				$remove_cap_roles = array_diff( $value_prev, $value );

				$this->save_caps( $cap_id, $add_cap_roles, $remove_cap_roles );
			}
		}
	}

	/**
	 * Add or remove a capability to a set of roles.
	 *
	 * @since 1.5.8
	 *
	 * @param string $cap_id           Capability name.
	 * @param array  $add_cap_roles    Set of roles to add the capability to.
	 * @param array  $remove_cap_roles Set of roles to remove the capability from.
	 */
	protected function save_caps( $cap_id, $add_cap_roles, $remove_cap_roles ) {

		if ( empty( $add_cap_roles ) && empty( $remove_cap_roles ) ) {
			return;
		}

		DashboardWidget::clear_widget_cache();

		$roles = \get_editable_roles();

		foreach ( $add_cap_roles as $role ) {
			if ( \array_key_exists( $role, $roles ) ) {
				\get_role( $role )->add_cap( $cap_id );
			}
		}

		foreach ( $remove_cap_roles as $role ) {
			if ( \array_key_exists( $role, $roles ) ) {
				\get_role( $role )->remove_cap( $cap_id );
			}
		}
	}
}
