<?php

namespace WPForms\Pro\Access;

use WP_Post;

/**
 * Access/Capability management.
 *
 * @since 1.5.8
 */
class Capabilities {

	/**
	 * Init class.
	 *
	 * @since 1.5.8
	 */
	public function init() {

		if ( ! $this->init_allowed() ) {
			return;
		}

		$this->hooks();
	}

	/**
	 * Init conditions.
	 *
	 * @since 1.5.8
	 */
	public function init_allowed() {

		if ( \is_admin() && ! \wpforms_is_frontend_ajax() ) {
			return true;
		}

		if ( isset( $_GET['wpforms_form_preview'] ) && ! \is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}

		return false;
	}

	/**
	 * Capabilities hooks.
	 *
	 * @since 1.5.8
	 */
	public function hooks() {

		add_filter( 'map_meta_cap', [ $this, 'filter_map_meta_cap' ], 10, 4 );
		add_filter( 'wpforms_get_multiple_forms_args', [ $this, 'filter_wpforms_get_multiple_forms_args' ] );
		add_filter( 'wpforms_entry_fields_get_fields_where', [ $this, 'filter_get_fields_where' ], 10, 2 );
		add_filter( 'wpforms_entry_handler_get_entries_where', [ $this, 'filter_get_entries_where' ], 10, 2 );
	}

	/**
	 * Get a list of all capabilities.
	 *
	 * @since 1.5.8
	 *
	 * @return array
	 */
	public function get_caps() {

		$capabilities = [
			// Forms capabilities.
			'wpforms_create_forms'                => __( 'Create Forms', 'wpforms' ),
			'wpforms_view_own_forms'              => __( 'View Own Forms', 'wpforms' ),
			'wpforms_view_others_forms'           => __( 'View Others\' Forms', 'wpforms' ),
			'wpforms_edit_own_forms'              => __( 'Edit Own Forms', 'wpforms' ),
			'wpforms_edit_others_forms'           => __( 'Edit Others\' Forms', 'wpforms' ),
			'wpforms_delete_own_forms'            => __( 'Delete Own Forms', 'wpforms' ),
			'wpforms_delete_others_forms'         => __( 'Delete Others\' Forms', 'wpforms' ),
			// Entries capabilities.
			'wpforms_view_entries_own_forms'      => __( 'View Own Forms Entries', 'wpforms' ),
			'wpforms_view_entries_others_forms'   => __( 'View Others\' Forms Entries', 'wpforms' ),
			'wpforms_edit_entries_own_forms'      => __( 'Edit Own Forms Entries', 'wpforms' ),
			'wpforms_edit_entries_others_forms'   => __( 'Edit Others\' Forms Entries', 'wpforms' ),
			'wpforms_delete_entries_own_forms'    => __( 'Delete Own Forms Entries', 'wpforms' ),
			'wpforms_delete_entries_others_forms' => __( 'Delete Others\' Forms Entries', 'wpforms' ),
		];

		return \apply_filters( 'wpforms_access_capabilities_get_caps', $capabilities );
	}

	/**
	 * Get a list of meta capabilities.
	 *
	 * @since 1.5.8
	 *
	 * @param string $meta_cap Meta capability name to get.
	 *
	 * @return array
	 */
	public function get_meta_caps( $meta_cap = '' ) {

		$meta_caps = [
			// Form meta caps.
			'view_form_single'           => [
				'own'    => 'wpforms_view_own_forms',
				'others' => 'wpforms_view_others_forms',
			],
			'edit_form_single'           => [
				'own'    => 'wpforms_edit_own_forms',
				'others' => 'wpforms_edit_others_forms',
			],
			'delete_form_single'         => [
				'own'    => 'wpforms_delete_own_forms',
				'others' => 'wpforms_delete_others_forms',
			],
			// Form entries meta caps.
			'view_entries_form_single'   => [
				'own'    => 'wpforms_view_entries_own_forms',
				'others' => 'wpforms_view_entries_others_forms',
			],
			'edit_entries_form_single'   => [
				'own'    => 'wpforms_edit_entries_own_forms',
				'others' => 'wpforms_edit_entries_others_forms',
			],
			'delete_entries_form_single' => [
				'own'    => 'wpforms_delete_entries_own_forms',
				'others' => 'wpforms_delete_entries_others_forms',
			],
			// Form single entry meta caps. Use these only if Form ID is not available.
			'view_entry_single'          => [
				'own'    => 'wpforms_view_entries_own_forms',
				'others' => 'wpforms_view_entries_others_forms',
			],
			'edit_entry_single'          => [
				'own'    => 'wpforms_edit_entries_own_forms',
				'others' => 'wpforms_edit_entries_others_forms',
			],
			'delete_entry_single'        => [
				'own'    => 'wpforms_delete_entries_own_forms',
				'others' => 'wpforms_delete_entries_others_forms',
			],
		];

		$meta_caps = \apply_filters( 'wpforms_access_capabilities_get_meta_caps', $meta_caps );

		if ( 'all' === $meta_cap ) {
			return $meta_caps;
		}

		if ( \is_string( $meta_cap ) && \array_key_exists( $meta_cap, $meta_caps ) ) {
			return $meta_caps[ $meta_cap ];
		}

		return [];
	}

	/**
	 * Get a list of category caps.
	 *
	 * @since 1.5.8
	 *
	 * @param string $category Category name to get.
	 *
	 * @return array
	 */
	public function get_category_caps( $category = '' ) {

		$categories = [
			// Form categories.
			'view_forms'     => [
				'own'    => 'wpforms_view_own_forms',
				'others' => 'wpforms_view_others_forms',
			],
			'edit_forms'     => [
				'own'    => 'wpforms_edit_own_forms',
				'others' => 'wpforms_edit_others_forms',
			],
			'delete_forms'   => [
				'own'    => 'wpforms_delete_own_forms',
				'others' => 'wpforms_delete_others_forms',
			],
			// Entry categories.
			'view_entries'   => [
				'own'    => 'wpforms_view_entries_own_forms',
				'others' => 'wpforms_view_entries_others_forms',
			],
			'edit_entries'   => [
				'own'    => 'wpforms_edit_entries_own_forms',
				'others' => 'wpforms_edit_entries_others_forms',
			],
			'delete_entries' => [
				'own'    => 'wpforms_delete_entries_own_forms',
				'others' => 'wpforms_delete_entries_others_forms',
			],
		];

		$categories = \apply_filters( 'wpforms_access_capabilities_get_category_caps', $categories );

		if ( 'all' === $category ) {
			return $categories;
		}

		if ( \array_key_exists( $category, $categories ) ) {
			return $categories[ $category ];
		}

		return [];
	}

	/**
	 * Filter user's capabilities depending on a specific context and/or privilege.
	 *
	 * @since 1.5.8
	 *
	 * @param array  $caps    User's actual capabilities.
	 * @param string $cap     Capability name.
	 * @param int    $user_id The user ID.
	 * @param array  $args    Add the context to the cap. Typically the object ID.
	 *
	 * @return array
	 */
	public function filter_map_meta_cap( $caps, $cap, $user_id, $args ) {

		$meta_caps = $this->get_meta_caps( $cap );

		if ( empty( $meta_caps ) ) {
			return $caps;
		}

		$form_id = isset( $args[0] ) ? \absint( $args[0] ) : 0;
		$form_id = $this->map_meta_cap_id( $form_id, $cap );

		$form = wpforms()->get( 'form' )->get( $form_id, [ 'cap' => false ] );

		if ( ! $form ) {
			return $caps;
		}

		if ( ! is_a( $form, 'WP_Post' ) ) {
			return $caps;
		}

		if ( 'wpforms' !== $form->post_type ) {
			return $caps;
		}

		if ( (int) $user_id === (int) $form->post_author ) {
			$mapped_cap = ! empty( $meta_caps['own'] ) ? $meta_caps['own'] : '';
		} else {
			$mapped_cap = ! empty( $meta_caps['others'] ) ? $meta_caps['others'] : '';
		}

		// Return the capabilities required by the user.
		return empty( $mapped_cap ) ? $caps : [ $mapped_cap ];
	}

	/**
	 * Substitute an entry ID with its form ID to map meta capability correctly.
	 *
	 * @since 1.5.8
	 *
	 * @param int    $id  Potentially an entry id.
	 * @param string $cap Potentially a meta capability.
	 *
	 * @return int
	 */
	protected function map_meta_cap_id( $id, $cap ) {

		if ( ! in_array( $cap, [ 'view_entry_single', 'edit_entry_single', 'delete_entry_single' ], true ) ) {
			return $id;
		}

		$entry = wpforms()->get( 'entry' )->get( $id, [ 'cap' => false ] );

		return empty( $entry->form_id ) ? $id : $entry->form_id;
	}

	/**
	 * Filter wpforms()->get( 'form' )->get_multiple() arguments to fetch the same results
	 * as if a full list of forms was filtered using a meta capability.
	 * Save the resources by making the filtering upfront.
	 *
	 * @since 1.5.8
	 *
	 * @param array $args Array of wpforms()->get( 'form' )->get() arguments.
	 *
	 * @return array
	 */
	public function filter_wpforms_get_multiple_forms_args( $args ) {

		if ( ! isset( $args['cap'] ) ) {
			$args['cap'] = 'view_form_single';
		}

		if ( empty( $args['cap'] ) ) {
			return $args;
		}

		$meta_caps = $this->get_meta_caps( $args['cap'] );

		if ( empty( $meta_caps ) ) {
			return $this->current_user_can( $args['cap'] ) ? $args : $this->change_wp_query_args_by_user_can( $args, false, false );
		}

		$can_own    = ! empty( $meta_caps['own'] ) ? $this->current_user_can( $meta_caps['own'] ) : false;
		$can_others = ! empty( $meta_caps['others'] ) ? $this->current_user_can( $meta_caps['others'] ) : false;

		return $this->change_wp_query_args_by_user_can( $args, $can_own, $can_others );
	}

	/**
	 * Change WP_Query arguments to fetch the results
	 * based on the authorship capabilities the current user has.
	 *
	 * @since 1.5.8
	 *
	 * @param array $args       Array of WP_Query arguments.
	 * @param bool  $can_own    Can user interact with own posts.
	 * @param bool  $can_others Can user interact with others` posts.
	 *
	 * @return array
	 */
	protected function change_wp_query_args_by_user_can( $args, $can_own, $can_others ) {

		if ( ! \is_array( $args ) ) {
			$args = [];
		}

		$user_id = \get_current_user_id();

		if ( $can_others && ! $can_own ) {
			$args['author__not_in'] = $user_id;
		}

		if ( ! $can_others && $can_own ) {
			$args['author'] = $user_id;
		}

		// Make sure that WP_Query returns nothing if a user has none of the capabilities.
		if ( ! $can_others && ! $can_own ) {
			$args['post__in'] = [ 0 ];
		}

		return $args;
	}

	/**
	 * Filter wpforms()->get( 'entry_fields' )->get_fields()
	 * arguments to fetch the same results as if a full list of entries was filtered using a meta capability.
	 * Save the resources by making the filtering upfront.
	 *
	 * @since 1.6.0
	 *
	 * @param array $where Array of 'where' clauses.
	 * @param array $args  Array of wpforms()->get( 'entry_fields' )->get_fields() arguments.
	 *
	 * @return array
	 */
	public function filter_get_fields_where( $where, $args ) {

		return $this->get_allowed_form_ids_where( $where, $args );
	}

	/**
	 * Filter wpforms()->get( 'entry' )->get_entries()
	 * arguments to fetch the same results as if a full list of entries was filtered using a meta capability.
	 * Save the resources by making the filtering upfront.
	 *
	 * @since 1.6.0
	 *
	 * @param array $where Array of 'where' clauses.
	 * @param array $args  Array of wpforms()->get( 'entry' )->get_entries() arguments.
	 *
	 * @return array
	 */
	public function filter_get_entries_where( $where, $args ) {

		return $this->get_allowed_form_ids_where( $where, $args, wpforms()->get( 'entry' )->table_name );
	}

	/**
	 * Get a set of WHERE clauses for wpforms()->get( 'entry_fields' )->get_fields() or wpforms()->get( 'entry' )->get_entries()
	 * filtering a result by the allowed form ids.
	 *
	 * @since 1.6.0
	 *
	 * @param array  $where Array of 'where' clauses.
	 * @param array  $args  Array of wpforms()->get( 'entry_fields' )->get_fields() or wpforms()->get( 'entry' )->get_entries() arguments.
	 * @param string $table DB table to use in the "form_id IN" part of the query.
	 *
	 * @return array
	 */
	protected function get_allowed_form_ids_where( $where, $args, $table = '' ) {

		if ( ! isset( $args['cap'] ) ) {
			$args['cap'] = 'view_entries_form_single';
		}

		if ( empty( $args['cap'] ) ) {
			return $where;
		}

		$empty_where = [ 'return_empty' => '1=0' ];

		if ( ! empty( $args['form_id'] ) ) {
			return $this->current_user_can( $args['cap'], \absint( $args['form_id'] ) ) ? $where : $empty_where;
		}

		$meta_caps = $this->get_meta_caps( $args['cap'] );

		if ( empty( $meta_caps ) ) {
			return $this->current_user_can( $args['cap'] ) ? $where : $empty_where;
		}

		$allowed_forms = wpforms()->get( 'form' )->get(
			'',
			[
				'fields' => 'ids',
				'cap'    => $args['cap'],
			]
		);

		if ( empty( $allowed_forms ) ) {
			return $empty_where;
		}

		$allowed_forms = implode( ',', array_map( 'intval', $allowed_forms ) );

		$where['arg_form_id'] = $table ? "{$table}.form_id IN ( {$allowed_forms} )" : "form_id IN ( {$allowed_forms} )";

		return $where;
	}

	/**
	 * Check permissions for currently logged in user.
	 * Both short (e.g. 'view_own_forms') or long (e.g. 'wpforms_view_own_forms') capability name can be used.
	 * Only WPForms capabilities get processed.
	 *
	 * @since 1.5.8
	 *
	 * @param array|string $caps Capability name(s).
	 * @param int          $id   Optional. ID of the specific object to check against if capability is a "meta"
	 *                           cap. "Meta" capabilities, e.g. 'edit_post', 'edit_user', etc., are capabilities
	 *                           used by map_meta_cap() to map to other "primitive" capabilities, e.g.
	 *                           'edit_posts', edit_others_posts', etc. Accessed via func_get_args() and passed
	 *                           to WP_User::has_cap(), then map_meta_cap().
	 *
	 * @return bool
	 */
	public function current_user_can( $caps = [], $id = 0 ) {

		return (bool) $this->get_first_valid_cap( $caps, $id );
	}

	/**
	 * Get a first valid capability from an array of capabilities.
	 *
	 * @since 1.5.8
	 *
	 * @param array|string $caps Capability name(s).
	 * @param int          $id   Optional. ID of the specific object to check against if capability is a "meta" cap.
	 *                           "Meta" capabilities, e.g. 'edit_post', 'edit_user', etc., are capabilities used
	 *                           by map_meta_cap() to map to other "primitive" capabilities, e.g. 'edit_posts',
	 *                           edit_others_posts', etc. Accessed via func_get_args() and passed to WP_User::has_cap(),
	 *                           then map_meta_cap().
	 *
	 * @return string
	 */
	protected function get_first_valid_cap( $caps, $id = 0 ) {

		$manage_cap = wpforms_get_capability_manage_options();

		if ( current_user_can( $manage_cap ) ) {
			return $manage_cap;
		}

		if ( empty( $caps ) && ! current_user_can( $manage_cap ) ) {
			return '';
		}

		if ( $caps === 'any' ) {
			$caps = array_keys( $this->get_caps() );
		}

		foreach ( (array) $caps as $cap ) {
			$validated = $this->validate( $cap, $id );

			if ( $validated ) {
				return $validated;
			}
		}

		return '';
	}

	/**
	 * Validate a capability.
	 * Return a name of the first valid capability in case $cap is a meta capability.
	 *
	 * @since 1.5.8
	 *
	 * @param string $cap Capability name.
	 * @param int    $id  Optional. ID of the specific object to check against if capability is a "meta" cap.
	 *                    "Meta" capabilities, e.g. 'edit_post', 'edit_user', etc., are capabilities used
	 *                    by map_meta_cap() to map to other "primitive" capabilities, e.g. 'edit_posts',
	 *                    edit_others_posts', etc. Accessed via func_get_args() and passed to WP_User::has_cap(),
	 *                    then map_meta_cap().
	 *
	 * @return string
	 */
	protected function validate( $cap, $id = 0 ) {

		if ( ! empty( $id ) ) {
			$meta_caps = $this->get_meta_caps( $cap );
		}

		// Process meta capability.
		if ( ! empty( $meta_caps ) && \current_user_can( $cap, $id ) ) {
			$_cap = \map_meta_cap( $cap, \get_current_user_id(), $id );

			// Return real capability instead of meta.
			return ! empty( $_cap[0] ) ? $_cap[0] : '';
		}

		$category_caps = $this->get_category_caps( $cap );

		// Process capability category.
		if ( ! empty( $category_caps ) ) {
			return $this->get_first_valid_cap( $category_caps );
		}

		$cap = $this->maybe_prefix_cap( $cap );

		// Return empty string if $cap is not a WPForms capability.
		if ( ! array_key_exists( $cap, $this->get_caps() ) ) {
			return '';
		}

		// Process a primitive capability.
		if ( \current_user_can( $cap ) ) {
			return $cap;
		}

		return '';
	}

	/**
	 * Maybe prefix a WPForms capability.
	 *
	 * @since 1.5.8
	 *
	 * @param string $cap Capability name.
	 *
	 * @return string
	 */
	protected function maybe_prefix_cap( $cap ) {

		if ( strpos( $cap, 'wpforms_' ) !== 0 ) {
			$cap = 'wpforms_' . $cap;
		}

		return $cap;
	}

	/**
	 * Get a first valid capability from an array of capabilities.
	 *
	 * @since 1.5.8
	 *
	 * @param array|string $caps Capability name(s).
	 *
	 * @return string
	 */
	public function get_menu_cap( $caps ) {

		$valid = $this->get_first_valid_cap( $caps );

		return $valid ? $valid : wpforms_get_capability_manage_options();
	}

	/**
	 * Filter a given array of forms by given capability / capabilities
	 * of the current logged-in user.
	 *
	 * @since 1.7.5
	 *
	 * @param array|object|int[] $forms Array of forms / form ids / WP_Post to be filtered.
	 * @param array|string       $caps  WPForms capabilities name(s).
	 *
	 * @return array Filtered forms.
	 */
	public function filter_forms_by_current_user_capability( $forms, $caps ) {

		// In $forms argument, we can have single form as object or array.
		// Typecast (array) creates flat array from an object, so we cannot use it.
		if (
			is_a( $forms, WP_Post::class ) ||
			( is_array( $forms ) && array_key_exists( 'form_id', $forms ) ) ||
			is_scalar( $forms )
		) {
			$forms = [ $forms ];
		}

		$filtered_forms = [];

		foreach ( $forms as $form ) {
			$form_id = $this->get_form_id( $form );

			if ( $form_id && wpforms_current_user_can( $caps, $form_id ) ) {
				$filtered_forms[] = $form;
			}
		}

		return $filtered_forms;
	}

	/**
	 * Get form id.
	 *
	 * @since 1.7.5
	 *
	 * @param object|array|int $form Form.
	 *
	 * @return int|null
	 */
	private function get_form_id( $form ) {

		$form_id = null;

		if ( is_a( $form, WP_Post::class ) ) {
			$form_id = $form->ID;
		} elseif ( is_array( $form ) && array_key_exists( 'form_id', $form ) ) {
			$form_id = absint( $form['form_id'] );
		} elseif ( is_numeric( $form ) ) {
			$form_id = absint( $form );
		}

		return $form_id;
	}
}
