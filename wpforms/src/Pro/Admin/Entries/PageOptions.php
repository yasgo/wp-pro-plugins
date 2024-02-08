<?php

namespace WPForms\Pro\Admin\Entries;

/**
 * Class PageOptions.
 *
 * Handles saving Screen Options for the Entries page.
 *
 * @since 1.8.6
 */
class PageOptions {

	/**
	 * Primary class constructor.
	 *
	 * @since 1.8.6
	 */
	public function __construct() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.8.6
	 */
	private function hooks() {

		// Setup screen options - this needs to run early.
		add_action( 'load-wpforms_page_wpforms-entries', [ $this, 'screen_options' ] );
		add_filter( 'set-screen-option', [ $this, 'screen_options_set' ], 10, 3 );
		add_filter( 'set_screen_option_wpforms_entries_per_page', [ $this, 'screen_options_set' ], 10, 3 );
	}

	/**
	 * Add per-page screen option to the Entries table.
	 *
	 * @since 1.8.6
	 */
	public function screen_options() {

		$screen = get_current_screen();

		if ( $screen === null || $screen->id !== 'wpforms_page_wpforms-entries' ) {
			return;
		}

		/**
		 * Filter admin screen option arguments.
		 *
		 * @since 1.8.2
		 *
		 * @param array $args Option-dependent arguments.
		 */
		$args = (array) apply_filters( // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName
			'wpforms_entries_list_default_screen_option_args',
			[
				'label'   => esc_html__( 'Number of entries per page:', 'wpforms' ),
				'option'  => 'wpforms_entries_per_page',
				'default' => wpforms()->get( 'entry' )->get_count_per_page(),
			]
		);

		add_screen_option( 'per_page', $args );
	}

	/**
	 * Entries table per-page screen option value.
	 *
	 * @since 1.8.6
	 *
	 * @param mixed  $status Status.
	 * @param string $option Options.
	 * @param mixed  $value  Value.
	 *
	 * @return mixed
	 */
	public function screen_options_set( $status, $option, $value ) {

		if ( $option === 'wpforms_entries_per_page' ) {
			return $value;
		}

		return $status;
	}
}
