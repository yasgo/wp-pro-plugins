<?php

namespace WPForms\Pro\Admin;

/**
 * WPForms admin bar menu.
 *
 * @since 1.6.0
 */
class AdminBarMenu extends \WPForms\Admin\AdminBarMenu {

	/**
	 * Register hooks.
	 *
	 * @since 1.6.0
	 */
	public function hooks() {

		parent::hooks();

		add_filter( 'wpforms_admin_adminbarmenu_get_form_data', [ $this, 'add_entry_links_to_form_menu' ] );

		add_action( 'wpforms_admin_adminbarmenu_register_all_forms_menu_after', [ $this, 'entries_menu' ] );
	}

	/**
	 * Check if form contains a survey.
	 *
	 * @since 1.6.0
	 *
	 * @param int $form_id Form ID.
	 *
	 * @return bool
	 */
	public function has_survey( $form_id ) {

		if ( ! function_exists( 'wpforms_surveys_polls' ) ) {
			return false;
		}

		// Get our form data to check if surveys are enabled.
		$form      = wpforms()->get( 'form' )->get( $form_id );
		$form_data = wpforms_decode( $form->post_content );

		if ( ! empty( $form_data['settings']['survey_enable'] ) ) {
			return true;
		}

		if ( ! empty( $form_data['fields'] ) ) {
			foreach ( $form_data['fields'] as $field ) {
				if ( ! empty( $field['survey'] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Add entry and survey links to form data array for admin menu bar output.
	 * This gets called multiple times per page load, one for each form on the page.
	 *
	 * @since 1.6.5
	 *
	 * @param array $form_data Current form data.
	 *
	 * @return array Form data with added links.
	 */
	public function add_entry_links_to_form_menu( $form_data ) {

		$form_id = ! empty( $form_data['form_id'] ) ? absint( $form_data['form_id'] ) : 0;

		$form_data['entries_url'] = admin_url( 'admin.php?page=wpforms-entries&view=list&form_id=' . $form_id );

		if ( $this->has_survey( $form_id ) ) {
			$form_data['survey_url'] = admin_url( 'admin.php?page=wpforms-entries&view=survey&form_id=' . $form_id );
		}

		return $form_data;
	}

	/**
	 * Render View Entries admin menu bar sub-item.
	 *
	 * @since 1.6.0
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar WordPress Admin Bar object.
	 */
	public function entries_menu( \WP_Admin_Bar $wp_admin_bar ) {

		$wp_admin_bar->add_menu(
			[
				'parent' => 'wpforms-menu',
				'id'     => 'wpforms-entries',
				'title'  => esc_html__( 'Entries', 'wpforms' ),
				'href'   => admin_url( 'admin.php?page=wpforms-entries' ),
			]
		);
	}
}
