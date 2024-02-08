<?php

namespace WPForms\Pro\Admin\Entries\Overview;

use WPForms\Admin\Helpers\Datepicker;
use WP_Post;

/**
 * "Entries" overview page inside the admin, which lists all forms.
 * This page will be accessible via "WPForms" → "Entries".
 *
 * @since 1.8.2
 */
class Page {

	/**
	 * Array of start and end dates
	 * along with number of days in between.
	 *
	 * Responsible for generating "Last X Days".
	 *
	 * @since 1.8.2
	 *
	 * @var array
	 */
	private $timespan;

	/**
	 * Overview table instance.
	 *
	 * @since 1.8.2
	 *
	 * @var Table
	 */
	private $overview_table;

	/**
	 * Initialize.
	 *
	 * @since 1.8.2
	 */
	public function init() {

		// Bail early, if the class is not permitted to load.
		if ( ! $this->is_allowed() ) {
			return;
		}

		$this->hooks();
	}

	/**
	 * Determine if the class is allowed to load.
	 *
	 * @since 1.8.2
	 *
	 * @return bool
	 */
	private function is_allowed() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return wpforms_is_admin_page( 'entries' ) && empty( $_GET['form_id'] ) && empty( $_GET['entry_id'] );
	}

	/**
	 * Hooks.
	 *
	 * @since 1.8.2
	 */
	private function hooks() {

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'current_screen', [ $this, 'init_overview_table' ] );
		add_filter( 'wpforms_entries_list_default_screen_option_args', [ $this, 'screen_option' ] );
		add_action( 'wpforms_admin_page', [ $this, 'output' ] );
		add_action( 'wpforms_pro_admin_entries_overview_page_output_before', [ $this, 'output_top_bar' ] );
		add_action( 'wpforms_pro_admin_entries_overview_page_output_before', [ $this, 'output_chart' ] );
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @since 1.8.2
	 */
	public function enqueue_assets() {

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-flatpickr',
			WPFORMS_PLUGIN_URL . 'assets/lib/flatpickr/flatpickr.min.css',
			[],
			'4.6.9'
		);

		wp_enqueue_script(
			'wpforms-flatpickr',
			WPFORMS_PLUGIN_URL . 'assets/lib/flatpickr/flatpickr.min.js',
			[ 'jquery' ],
			'4.6.9',
			true
		);

		wp_enqueue_script(
			'wpforms-chart',
			WPFORMS_PLUGIN_URL . 'assets/lib/chart.min.js',
			[ 'moment' ],
			'2.9.4',
			true
		);

		wp_enqueue_script(
			'wpforms-admin-entries-overview',
			WPFORMS_PLUGIN_URL . "assets/pro/js/admin/entries-overview{$min}.js",
			[ 'jquery', 'wpforms-flatpickr', 'wpforms-chart' ],
			WPFORMS_VERSION,
			true
		);

		$admin_l10n = [
			'settings'    => $this->get_chart_settings(),
			'locale'      => sanitize_key( wpforms_get_language_code() ),
			'date_format' => sanitize_text_field( Datepicker::get_wp_date_format_for_momentjs() ),
			'delimiter'   => Datepicker::TIMESPAN_DELIMITER,
			'i18n'        => [
				'label' => esc_html__( 'Entries', 'wpforms' ),
			],
		];

		wp_localize_script(
			'wpforms-admin-entries-overview', // Script handle the data will be attached to.
			'wpforms_admin_entries_overview', // Name for the JavaScript object.
			$admin_l10n
		);
	}

	/**
	 * Base class for displaying a list of forms in an HTML table.
	 *
	 * @since 1.8.2
	 */
	public function init_overview_table() {

		$this->timespan       = Datepicker::process_timespan();
		$this->overview_table = new Table();

		// Timespans should be initialized early because the "screen options" needs to be filled in before the rendered page content.
		$this->overview_table->set_timespans( $this->timespan );
	}

	/**
	 * Update "per_page" label for the overview page.
	 *
	 * @since 1.8.2
	 *
	 * @param array $args Screen option arguments.
	 *
	 * @return array
	 */
	public function screen_option( $args ) {

		$args['label'] = esc_html__( 'Number of forms per page:', 'wpforms' );

		return $args;
	}

	/**
	 * Handles output of the overview page.
	 *
	 * @since 1.8.2
	 */
	public function output() {

		// In the event that the overview table has not yet been initialized, leave early.
		if ( ! $this->overview_table ) {
			return;
		}

		?>
		<div id="wpforms-entries-list" class="wrap wpforms-admin-wrap wpforms-entries-overview">

			<h1 class="page-title">
				<?php esc_html_e( 'Entries', 'wpforms' ); ?>
			</h1>

			<div class="wpforms-admin-content">

				<?php
				if ( ! $this->overview_table->has_items() ) {

					// Output no forms screen.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo wpforms_render( 'admin/empty-states/no-forms' );

				} else {

					/**
					 * Allow rendering of other elements before the forms table.
					 *
					 * @since 1.8.2
					 */
					do_action( 'wpforms_pro_admin_entries_overview_page_output_before' );

					$this->overview_table->prepare_items();
				?>
					<form class="wpforms-entries-overview-table" method="get" action="<?php echo esc_url( $this->get_current_screen_url() ); ?>">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpforms_entries_overview_nonce' ) ); ?>">
						<?php
							$this->overview_table->views();
							$this->overview_table->display();
						?>
					</form>
				<?php } ?>

			</div>
		</div>
		<?php
	}

	/**
	 * Handles output of the overview page top-bar.
	 *
	 * Includes:
	 * 1. Heading.
	 * 2. Datepicker filter.
	 * 3. Chart theme customization settings.
	 *
	 * @since 1.8.2
	 */
	public function output_top_bar() {

		list( $choices, $chosen_filter, $value ) = Datepicker::process_datepicker_choices( $this->timespan );

		?>
		<div class="wpforms-overview-top-bar">
			<div class="wpforms-overview-top-bar-heading">
				<h2><?php esc_html_e( 'All Forms', 'wpforms' ); ?></h2>
				<button type="button" class="wpforms-reset-chart dashicons dashicons-dismiss wpforms-hide" title="<?php esc_attr_e( 'Reset chart to display all forms', 'wpforms' ); ?>"></button>
			</div>

			<div class="wpforms-overview-top-bar-filters">
				<?php
				// Output "Datepicker" form template.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wpforms_render(
					'admin/components/datepicker',
					[
						'id'            => 'entries',
						'action'        => $this->get_current_screen_url(),
						'chosen_filter' => $chosen_filter,
						'choices'       => $choices,
						'value'         => $value,
					],
					true
				);
				?>

				<div class="wpforms-overview-chart-settings">
					<?php
					// Output "Settings" template.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo wpforms_render(
						'admin/dashboard/widget/settings',
						array_merge( $this->get_chart_settings(), [ 'enabled' => true ] ),
						true
					);
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handles output of the overview page chart (graph).
	 *
	 * @since 1.8.2
	 */
	public function output_chart() {

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wpforms_render(
			'admin/components/chart',
			[
				'id'          => 'entries',
				'total_items' => esc_html__( 'Total Entries', 'wpforms' ),
				'notice'      => [
					'heading'     => esc_html__( 'No entries for selected period', 'wpforms' ),
					'description' => esc_html__( 'Please select a different period or check back later.', 'wpforms' ),
				],
			],
			true
		);
	}

	/**
	 * Get the user’s preferences for displaying of the graph.
	 *
	 * @since 1.8.2
	 *
	 * @return array
	 */
	private function get_chart_settings() {

		$user_id        = get_current_user_id();
		$graph_style    = get_user_meta( $user_id, 'wpforms_dash_widget_graph_style', true );
		$color_scheme   = get_user_meta( $user_id, 'wpforms_dash_widget_color_scheme', true );
		$active_form_id = get_user_meta( $user_id, 'wpforms_dash_widget_active_form_id', true );
		$active_form    = empty( $active_form_id ) ? false : wpforms()->get( 'form' )->get( $active_form_id );

		return [
			'active_form_id' => $active_form instanceof WP_Post && $active_form->post_status === 'publish' ? $active_form->ID : '',
			'graph_style'    => $graph_style ? absint( $graph_style ) : 2, // Line.
			'color_scheme'   => $color_scheme ? absint( $color_scheme ) : 1, // WPForms.
		];
	}

	/**
	 * Get the current view page URL.
	 *
	 * @since 1.8.2
	 *
	 * @return string
	 */
	private function get_current_screen_url() {

		return add_query_arg(
			[
				'page' => 'wpforms-entries',
			],
			admin_url( 'admin.php' )
		);
	}
}
