<?php

namespace WPForms\Pro\Admin\Entries;

/**
 * Class Handler.
 *
 * @since 1.8.3
 */
class Handler {

	/**
	 * Init.
	 *
	 * @since 1.8.3
	 */
	public function init() {

		// Check if the current page is the entries page.
		if ( ! wpforms_is_admin_page( 'entries' ) ) {
			return;
		}

		$this->maybe_redirect();
	}

	/**
	 * Maybe redirect to the entries list page.
	 *
	 * @since 1.8.3
	 */
	public function maybe_redirect() {

		// Redirect to the entries page if the current page is not valid.
		if ( ! $this->is_valid_entries_page() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpforms-entries' ) );
			exit;
		}
	}

	/**
	 * Check if the current entries page is valid.
	 *
	 * @since 1.8.3
	 *
	 * @return bool
	 */
	protected function is_valid_entries_page() { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh, Generic.Metrics.CyclomaticComplexity.MaxExceeded

		$url_args = $this->get_entries_page_args();

		// Check if the page is the entries list page.
		if ( empty( $url_args ) || empty( $url_args['view'] ) ) {
			return true;
		}

		$available_views = [
			'list',
			'edit',
			'print',
			'details',
			'survey',
		];

		return in_array( sanitize_key( $url_args['view'] ), $available_views, true );
	}

	/**
	 * Get entries page arguments.
	 *
	 * @since 1.8.3
	 *
	 * @return array
	 */
	protected function get_entries_page_args() {

		$current_url = wpforms_current_url();
		$url_query   = wp_parse_url( $current_url, PHP_URL_QUERY );
		$url_args    = wp_parse_args( $url_query );

		// Remove the page argument. Empty page argument means that the current page is the entries list page.
		unset( $url_args['page'] );

		return $url_args;
	}
}
