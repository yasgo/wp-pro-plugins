<?php

namespace WPForms\Pro\Forms\Fields\PageBreak;

use WPForms\Forms\Fields\Base\Frontend as FrontendBase;

/**
 * Frontend class for the Page Break field.
 *
 * @since 1.8.1
 */
class Frontend extends FrontendBase {

	/**
	 * Hooks.
	 *
	 * @since 1.8.1
	 */
	public function hooks() {

		if ( wpforms_get_render_engine() !== 'modern' ) {
			return;
		}

		add_filter( 'wpforms_frontend_strings', [ $this, 'frontend_strings' ], 10 );
	}

	/**
	 * Field display on the form front-end.
	 *
	 * @since 1.8.1
	 *
	 * @param array $field     Field data and settings.
	 * @param array $form_data Form data and settings.
	 */
	public function field_display_modern( $field, $form_data ) {

		// Top page breaks don't display.
		if ( ! empty( $field['position'] ) && $field['position'] === 'top' ) {
			return;
		}

		// Setup and sanitize the necessary data.

		/** This action is documented in pro/includes/fields/class-page-break.php. */
		$filtered_field = apply_filters( 'wpforms_pagedivider_field_display', $field, [], $form_data ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
		$field          = wpforms_list_intersect_key( (array) $filtered_field, $field );

		$next = ! empty( $field['next'] ) ? $field['next'] : '';
		$prev = ! empty( $field['prev'] ) ? $field['prev'] : '';

		if ( ! $this->has_page_button( 'prev', $prev ) && ! $this->has_page_button( 'next', $next ) ) {
			return;
		}

		printf(
			'<div class="wpforms-clear %s">',
			sanitize_html_class( $this->get_align_buttons_class() )
		);

		$this->display_page_button( 'prev', $prev, $form_data );
		$this->display_page_button( 'next', $next, $form_data );

		echo '</div>';
	}

	/**
	 * Get align buttons class.
	 *
	 * @since 1.8.6
	 *
	 * @return string
	 */
	private function get_align_buttons_class(): string {

		$frontend_obj = wpforms()->get( 'frontend' );
		$top          = ! empty( $frontend_obj->pages['top'] ) ? $frontend_obj->pages['top'] : [];

		return ! empty( $top['nav_align'] ) ? 'wpforms-pagebreak-' . $top['nav_align'] : 'wpforms-pagebreak-center';
	}

	/**
	 * Display page button.
	 *
	 * @since 1.8.1
	 *
	 * @param string $action    Action, Possible values: `prev` OR `next`.
	 * @param int    $caption   Button caption.
	 * @param array  $form_data Form data and settings.
	 */
	private function display_page_button( $action, $caption, $form_data ) {

		$frontend_obj = wpforms()->get( 'frontend' );
		$current      = $frontend_obj->pages['current'];

		if ( ! $this->has_page_button( $action, $caption ) ) {
			return;
		}

		printf(
			'<button class="wpforms-page-button wpforms-page-%1$s wpforms-disabled"
					data-action="%1$s" data-page="%2$d" data-formid="%3$d" aria-disabled="true" aria-describedby="wpforms-error-noscript">%4$s</button>',
			esc_attr( $action ),
			(int) $current,
			(int) $form_data['id'],
			esc_html( $caption )
		);

		if ( $action !== 'next' ) {
			return;
		}

		/** This action is documented in includes/class-frontend.php. */
		do_action( 'wpforms_display_submit_after', $form_data, 'next' ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Is a button visible for the page?
	 *
	 * @since 1.8.6
	 *
	 * @param string $action  Prev or next action.
	 * @param string $caption Button caption.
	 *
	 * @return bool
	 */
	private function has_page_button( string $action, string $caption ): bool {

		$frontend_obj = wpforms()->get( 'frontend' );
		$current      = $frontend_obj->pages['current'];
		$total        = $frontend_obj->pages['total'];

		if ( empty( $caption ) ) {
			return false;
		}

		if ( $action === 'prev' && $current <= 1 ) {
			return false;
		}

		return ! ( $action === 'next' && $current >= $total );
	}

	/**
	 * Open page indicator container.
	 *
	 * @since 1.8.1
	 *
	 * @param array $pagebreak Field data and settings.
	 */
	public function open_page_indicator_container( $pagebreak ) {

		$modern_attributes = '';

		if ( wpforms_get_render_engine() === 'modern' ) {
			$modern_attributes = sprintf(
				' role="progressbar" aria-valuenow="1" aria-valuemin="1" aria-valuemax="%d" tabindex="-1"',
				count( $pagebreak['pages'] )
			);
		}

		printf(
			'<div class="wpforms-page-indicator %1$s" data-indicator="%2$s" data-indicator-color="%3$s" data-scroll="%4$d"%5$s>',
			esc_attr( $pagebreak['indicator'] ),
			esc_attr( $pagebreak['indicator'] ),
			esc_attr( $pagebreak['color'] ),
			(int) $pagebreak['scroll'],
			$modern_attributes // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Modify javascript `wpforms_settings` properties on the front end.
	 *
	 * @since 1.8.1
	 *
	 * @param array $strings Array `wpforms_settings` properties.
	 *
	 * @return array
	 */
	public function frontend_strings( $strings ) {

		$strings['indicatorStepsPattern'] = sprintf( /* translators: %1$s - current step in multi-page form, %2$s - total number of pages. */
			esc_html__( 'Step %1$s of %2$s', 'wpforms' ),
			'{current}',
			'{total}'
		);

		return $strings;
	}
}
