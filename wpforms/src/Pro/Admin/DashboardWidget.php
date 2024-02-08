<?php

namespace WPForms\Pro\Admin;

use DateInterval;
use DatePeriod;
use DateTime;
use Exception;
use WP_Post;
use WPForms\Admin\Dashboard\Widget;
use WPForms\Admin\Helpers\Datepicker;
use WPForms\Pro\Reports\EntriesCount;

/**
 * Dashboard Widget shows a chart and the form entries stats in WP Dashboard.
 *
 * @since 1.5.0
 */
class DashboardWidget extends Widget {

	/**
	 * Widget settings.
	 *
	 * @since 1.5.0
	 *
	 * @var array
	 */
	public $settings;

	/**
	 * Runtime values.
	 *
	 * @since 1.5.5
	 * @deprecated 1.8.6
	 *
	 * @var array
	 */
	public $runtime_data = [];

	/**
	 * Entries count.
	 *
	 * @since 1.7.6
	 *
	 * @var EntriesCount
	 */
	private $entries_count;

	/**
	 * Constructor.
	 *
	 * @since 1.5.0
	 */
	public function __construct() {

		$this->entries_count = new EntriesCount();
	}

	/**
	 * Init class.
	 *
	 * @since 1.5.5
	 * @since 1.8.3 Added cache clean hooks.
	 */
	public function init() { // phpcs:ignore WPForms.PHP.HooksMethod.InvalidPlaceForAddingHooks

		// phpcs:disable WPForms.PHP.ValidateHooks.InvalidHookName
		/** This filter is documented in the wpforms/src/Lite/Admin/DashboardWidget.php file. */
		if ( ! apply_filters( 'wpforms_admin_dashboardwidget', true ) ) {
			return;
		}
		// phpcs:enable WPForms.PHP.ValidateHooks.InvalidHookName

		// This widget should be displayed for certain high-level users only.
		if ( ! wpforms_current_user_can( 'view_entries' ) ) {
			return;
		}

		add_action( 'wpforms_create_form', [ static::class, 'clear_widget_cache' ] );
		add_action( 'wpforms_save_form', [ static::class, 'clear_widget_cache' ] );
		add_action( 'wpforms_delete_form', [ static::class, 'clear_widget_cache' ] );

		/**
		 * Clear cache after PRO plugin deactivation.
		 *
		 * If user wants to switch to Lite version it needs to deactivate PRO plugin first.
		 * After activation of Lite version, the cache will be cleared.
		 */
		add_action( 'deactivate_wpforms/wpforms.php', [ static::class, 'clear_widget_cache' ] );

		// Continue only if we are on the dashboard page.
		if ( ! $this->is_dashboard_page() && ! $this->is_dashboard_widget_ajax_request() ) {
			return;
		}

		$this->settings();
		$this->hooks();
	}

	/**
	 * Filterable widget settings.
	 *
	 * @since 1.5.0
	 */
	public function settings() {

		$widget_slug = static::SLUG;

		// phpcs:disable WPForms.Comments.PHPDocHooks.RequiredHookDocumentation, WPForms.PHP.ValidateHooks.InvalidHookName

		$this->settings = [

			// Number of forms to display in the forms list before "Show More" button appears.
			'forms_list_number_to_display'     => apply_filters( "wpforms_{$widget_slug}_forms_list_number_to_display", 5 ),

			// Allow results caching to reduce DB load.
			'allow_data_caching'               => apply_filters( "wpforms_{$widget_slug}_allow_data_caching", true ),

			// PHP DateTime supported string (http://php.net/manual/en/datetime.formats.php).
			'date_end_str'                     => apply_filters( "wpforms_{$widget_slug}_date_end_str", 'today' ),

			// Transient lifetime in seconds. Defaults to one hour in seconds.
			'transient_lifetime'               => apply_filters( "wpforms_{$widget_slug}_transient_lifetime", HOUR_IN_SECONDS ),

			// Determine if the days with no entries should appear on a chart. Once switched, the effect applies after cache expiration.
			'display_chart_empty_entries'      => apply_filters( "wpforms_{$widget_slug}_display_chart_empty_entries", true ),

			// Determine if the forms with no entries should appear in a forms list. Once switched, the effect applies after cache expiration.
			'display_forms_list_empty_entries' => apply_filters( "wpforms_{$widget_slug}_display_forms_list_empty_entries", true ),
		];

		// phpcs:enable WPForms.Comments.PHPDocHooks.RequiredHookDocumentation, WPForms.PHP.ValidateHooks.InvalidHookName
	}

	/**
	 * Widget hooks.
	 *
	 * @since 1.5.0
	 */
	public function hooks() {

		$widget_slug = static::SLUG;

		add_action( 'admin_enqueue_scripts', [ $this, 'widget_scripts' ] );

		if ( $widget_slug === 'dash_widget' ) {
			add_action( 'wp_dashboard_setup', [ $this, 'widget_register' ] );
		}

		add_action( "wp_ajax_wpforms_{$widget_slug}_get_chart_data", [ $this, 'get_chart_data_ajax' ] );
		add_action( "wp_ajax_wpforms_{$widget_slug}_get_forms_list", [ $this, 'get_forms_list_ajax' ] );
		add_action( "wp_ajax_wpforms_{$widget_slug}_save_widget_meta", [ $this, 'save_widget_meta_ajax' ] );
	}

	/**
	 * Load widget-specific scripts.
	 *
	 * @since 1.5.0
	 *
	 * @param string $hook_suffix The current admin page.
	 *
	 * @throws Exception Exception.
	 */
	public function widget_scripts( $hook_suffix ) {

		if ( ! in_array( $hook_suffix, [ 'index.php', 'wpforms_page_wpforms-entries' ], true ) ) {
			return;
		}

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-dashboard-widget',
			WPFORMS_PLUGIN_URL . "assets/css/dashboard-widget$min.css",
			[],
			WPFORMS_VERSION
		);

		wp_enqueue_script(
			'wpforms-chart',
			WPFORMS_PLUGIN_URL . 'assets/lib/chart.min.js',
			[ 'moment' ],
			'2.9.4',
			true
		);

		wp_enqueue_script(
			'wpforms-dashboard-widget',
			WPFORMS_PLUGIN_URL . "assets/pro/js/admin/dashboard-widget$min.js",
			[ 'jquery', 'wpforms-chart' ],
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			'wpforms-dashboard-widget',
			'wpforms_dashboard_widget',
			[
				'nonce'            => wp_create_nonce( 'wpforms_' . static::SLUG . '_nonce' ),
				'slug'             => static::SLUG,
				'date_format'      => sanitize_text_field( Datepicker::get_wp_date_format_for_momentjs() ),
				'empty_chart_html' => $this->get_empty_chart_html(),
				'chart_data'       => $this->get_entries_count_by(
					'date',
					$this->widget_meta( 'get', 'timespan' ),
					$this->widget_meta( 'get', 'active_form_id' )
				),
				'chart_type'       => (int) $this->widget_meta( 'get', 'graph_style' ) === 2 ? 'line' : 'bar',
				'color_scheme'     => (int) $this->widget_meta( 'get', 'color_scheme' ) === 2 ? 'wp' : 'wpforms',
				'show_more_html'   => esc_html__( 'Show More', 'wpforms' ) . '<span class="dashicons dashicons-arrow-down"></span>',
				'show_less_html'   => esc_html__( 'Show Less', 'wpforms' ) . '<span class="dashicons dashicons-arrow-up"></span>',
				'i18n'             => [
					'total_entries' => esc_html__( 'Total Entries', 'wpforms' ),
					'entries'       => esc_html__( 'Entries', 'wpforms' ),
					'form_entries'  => esc_html__( 'Form Entries', 'wpforms' ),
				],
			]
		);
	}

	/**
	 * Register the widget.
	 *
	 * @since 1.5.0
	 */
	public function widget_register() {

		global $wp_meta_boxes;

		$widget_key = 'wpforms_reports_widget_pro';

		wp_add_dashboard_widget(
			$widget_key,
			esc_html__( 'WPForms', 'wpforms' ),
			[ $this, 'widget_content' ]
		);

		// Attempt to place the widget at the top.
		$normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];
		$widget_instance  = [ $widget_key => $normal_dashboard[ $widget_key ] ];

		unset( $normal_dashboard[ $widget_key ] );

		$sorted_dashboard = array_merge( $widget_instance, $normal_dashboard );

		$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
	}

	/**
	 * Load widget content.
	 *
	 * @since 1.5.0
	 *
	 * @throws Exception Exception.
	 */
	public function widget_content() {

		$form_obj = wpforms()->get( 'form' );
		$forms    = $form_obj ? $form_obj->get( '', [ 'fields' => 'ids' ] ) : [];

		echo '<div class="wpforms-dash-widget wpforms-pro">';

		if ( empty( $forms ) ) {
			$this->widget_content_no_forms_html();
		} else {
			$this->widget_content_html();
		}

		$plugin           = $this->get_recommended_plugin();
		$hide_recommended = $this->widget_meta( 'get', 'hide_recommended_block' );

		if (
			! empty( $plugin ) &&
			! empty( $forms ) &&
			! $hide_recommended
		) {
			$this->recommended_plugin_block_html( $plugin );
		}

		echo '</div><!-- .wpforms-dash-widget -->';
	}

	/**
	 * Widget content HTML if a user has no forms.
	 *
	 * @since 1.5.0
	 */
	public function widget_content_no_forms_html() {

		$create_form_url = add_query_arg( 'page', 'wpforms-builder', admin_url( 'admin.php' ) );
		$learn_more_url  = 'https://wpforms.com/docs/creating-first-form/?utm_source=WordPress&utm_medium=link&utm_campaign=plugin&utm_content=dashboardwidget';

		?>
		<div class="wpforms-dash-widget-block wpforms-dash-widget-block-no-forms">
			<img class="wpforms-dash-widget-block-sullie-logo" src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/sullie.png' ); ?>" alt="<?php esc_attr_e( 'Sullie the WPForms mascot', 'wpforms' ); ?>">
			<h2><?php esc_html_e( 'Create Your First Form to Start Collecting Leads', 'wpforms' ); ?></h2>
			<p><?php esc_html_e( 'You can use WPForms to build contact forms, surveys, payment forms, and more with just a few clicks.', 'wpforms' ); ?></p>

			<?php if ( wpforms_current_user_can( 'create_forms' ) ) : ?>
				<a href="<?php echo esc_url( $create_form_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Create Your Form', 'wpforms' ); ?>
				</a>
			<?php endif; ?>

			<a href="<?php echo esc_url( $learn_more_url ); ?>" class="button" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Learn More', 'wpforms' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Widget content HTML.
	 *
	 * @since 1.5.0
	 *
	 * @throws Exception Exception.
	 */
	public function widget_content_html() {

		$widget_slug    = static::SLUG;
		$timespan       = $this->widget_meta( 'get', 'timespan' );
		$active_form_id = $this->widget_meta( 'get', 'active_form_id' );

		// phpcs:disable WPForms.Comments.PHPDocHooks.RequiredHookDocumentation, WPForms.PHP.ValidateHooks.InvalidHookName
		$title           = empty( $active_form_id ) ? apply_filters( "wpforms_{$widget_slug}_total_entries_title", esc_html__( 'Total Entries', 'wpforms' ) ) : get_the_title( $active_form_id );
		$timespan_at_top = (bool) apply_filters( "wpforms_{$widget_slug}_timespan_at_top", false );
		// phpcs:enable WPForms.Comments.PHPDocHooks.RequiredHookDocumentation, WPForms.PHP.ValidateHooks.InvalidHookName

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		/**
		 * Filters the content before the Dashboard Widget Chart block container (for Pro).
		 *
		 * @since 1.7.4
		 *
		 * @param string $chart_block_before Chart block before markup.
		 */
		echo apply_filters( 'wpforms_pro_admin_dashboard_widget_content_html_chart_block_before', '' );
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>

		<div class="wpforms-dash-widget-chart-block-container">

			<div class="wpforms-dash-widget-block">
				<?php if ( empty( $active_form_id ) ) : ?>
				<button type="button" id="wpforms-dash-widget-reset-chart" class="wpforms-dash-widget-reset-chart" title="<?php esc_html_e( 'Reset chart to display all forms', 'wpforms' ); ?>"
					style="display: none;" >
					<span class="dashicons dashicons-dismiss"></span>
				</button>
				<?php endif; ?>
				<h3 id="wpforms-dash-widget-chart-title">
					<?php echo esc_html( $title ); ?>
				</h3>
				<div class="wpforms-dash-widget-settings">
				<?php
				if ( $timespan_at_top ) {
					$this->timespan_select_html( $active_form_id );
					$this->widget_settings_html();
				}
				?>
				</div>
			</div>

			<div class="wpforms-dash-widget-block wpforms-dash-widget-chart-block">
				<canvas id="wpforms-dash-widget-chart" width="400" height="300"></canvas>
				<div class="wpforms-dash-widget-overlay"></div>
			</div>

		</div>

		<?php if ( ! $timespan_at_top ) : ?>
			<div class="wpforms-dash-widget-block wpforms-dash-widget-block-title">
				<h3>
					<span id="entry-count-text"><?php esc_html_e( 'Total Entries', 'wpforms' ); ?></span>:
					<span id="entry-count-value" data-total-count="<?php echo esc_attr( $this->get_total_entries() ); ?>"><?php echo esc_html( $this->get_total_entries() ); ?></span>
				</h3>
				<div class="wpforms-dash-widget-settings">
				<?php
				$this->timespan_select_html( $active_form_id );
				$this->widget_settings_html();
				?>
				</div>
			</div>
		<?php endif; ?>

		<div id="wpforms-dash-widget-forms-list-block" class="wpforms-dash-widget-block wpforms-dash-widget-forms-list-block">
			<?php $this->forms_list_block( $timespan ); ?>
		</div>
		<?php
	}

	/**
	 * Forms list block.
	 *
	 * @since 1.5.0
	 *
	 * @param int $days Timespan (in days) to fetch the data for.
	 *
	 * @throws Exception When date is failing.
	 */
	public function forms_list_block( $days ) {

		$forms = $this->get_entries_count_by( 'form', $days );

		if ( empty( $forms ) ) {
			$this->forms_list_block_empty_html();
		} else {
			$this->forms_list_block_html( $forms );
		}
	}

	/**
	 * Empty forms list block HTML.
	 *
	 * @since 1.5.0
	 */
	public function forms_list_block_empty_html() {

		?>
		<p class="wpforms-error wpforms-error-no-data-forms-list">
			<?php esc_html_e( 'No entries for selected period.', 'wpforms' ); ?>
		</p>
		<?php
	}

	/**
	 * Forms list block HTML.
	 *
	 * @since 1.5.0
	 *
	 * @param array $forms Forms to display in the list.
	 */
	public function forms_list_block_html( $forms ) {

		// Number of forms to display in the forms list before "Show More" button appears.
		$show_forms     = $this->settings['forms_list_number_to_display'];
		$active_form_id = $this->widget_meta( 'get', 'active_form_id' );
		$widget_slug    = static::SLUG;
		?>

		<table id="wpforms-dash-widget-forms-list-table" cellspacing="0">
			<?php
			echo wp_kses(
				apply_filters( "wpforms_{$widget_slug}_forms_list_columns", '', $forms ),
				[
					'tr' => [
						'class' => [],
					],
					'td' => [
						'class' => [],
					],
				]
			);

			foreach ( array_values( $forms ) as $key => $form ) :

				$is_active_form = $form['form_id'] === $active_form_id;

				if ( ! is_array( $form ) ) {
					continue;
				}
				if ( ! isset( $form['form_id'], $form['title'], $form['count'], $form['edit_url'] ) ) {
					continue;
				}

				$form_data = $form['form_id'] ? wpforms()->get( 'form' )->get( $form['form_id'], [ 'content_only' => true ] ) : [];

				$classes = [
					$key >= $show_forms && $show_forms > 0 ? 'wpforms-dash-widget-forms-list-hidden-el' : '',
					$is_active_form ? 'wpforms-dash-widget-form-active' : '',
				];
				?>

				<tr data-form-id="<?php echo absint( $form['form_id'] ); ?>"
					data-entry-count="<?php echo absint( $form['count'] ); ?>"
					class="<?php echo esc_attr( implode( ' ', array_unique( $classes ) ) ); ?>"
				>
					<td>
						<span class="wpforms-dash-widget-form-title">
							<?php
							echo wp_kses(
								/**
								 * Allow modifying a widget title.
								 *
								 * @since 1.5.5
								 *
								 * @param string $form_title Widget title.
								 * @param array  $form       Form data and settings.
								 *
								 * @return string
								 */
								apply_filters(
									"wpforms_{$widget_slug}_forms_list_form_title",
									$form['title'],
									$form
								),
								[
									'a' => [
										'href'  => [],
										'class' => [],
									],
								]
							);
							?>
						</span>
					</td>
					<?php
					echo wp_kses(
						/**
						 * Allow adding additional cells for a widget table.
						 *
						 * @since 1.5.5
						 *
						 * @param string $form_title Widget title.
						 * @param array  $form       Form data and settings.
						 *
						 * @return string
						 */
						apply_filters(
							"wpforms_{$widget_slug}_forms_list_additional_cells",
							'',
							$form
						),
						[
							'td' => [],
							'a'  => [
								'href' => [],
							],
						]
					);
					?>
					<td>
						<?php

						if ( $form['count'] === 0 && ! empty( $form_data['settings']['disable_entries'] ) ) {
							echo '&mdash;';
						} elseif ( wpforms_current_user_can( 'view_entries_form_single', $form['form_id'] ) ) {
							// Ensure the current user has enough permission to view entries of this form.
							printf(
								'<a href="%s" class="entry-list-link">%d</a>',
								esc_url( $form['edit_url'] ),
								absint( $form['count'] )
							);
						} else {
							echo absint( $form['count'] );
						}
						?>
					</td>
					<td class="graph">
						<?php if ( absint( $form['count'] ) > 0 ) : ?>
							<button type="button" class="wpforms-dash-widget-single-chart-btn chart dashicons dashicons-chart-bar" title="<?php esc_attr_e( 'Display only this form data in the graph', 'wpforms' ); ?>"></button>
							<?php
								if ( $is_active_form ) {
									?>
									<button type="button" id="wpforms-dash-widget-reset-chart" class="wpforms-dash-widget-reset-chart" title="<?php esc_html_e( 'Reset graph to display all forms', 'wpforms' ); ?>" >
										<span class="dashicons dashicons-dismiss"></span>
									</button>
									<?php
								}
							?>
							<?php
							echo wp_kses(
								/**
								 * Allow adding additional buttons for a widget table.
								 *
								 * @since 1.5.5
								 *
								 * @param string $form_title Widget title.
								 * @param array  $form       Form data and settings.
								 *
								 * @return string
								 */
								apply_filters(
									"wpforms_{$widget_slug}_forms_list_additional_buttons",
									'',
									$form
								),
								[
									'button' => [
										'type'  => [],
										'class' => [],
										'title' => [],
									],
									'span'   => [
										'class' => [],
									],
								]
							);
							?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<?php if ( $show_forms > 0 && count( $forms ) > $show_forms ) : ?>
			<button type="button" id="wpforms-dash-widget-forms-more" class="wpforms-dash-widget-forms-more" title="<?php esc_attr_e( 'Show all forms', 'wpforms' ); ?>">
				<?php esc_html_e( 'Show More', 'wpforms' ); ?> <span class="dashicons dashicons-arrow-down"></span>
			</button>
		<?php endif; ?>

		<?php
	}

	/**
	 * Recommended plugin block HTML.
	 *
	 * @since 1.5.0
	 * @since 1.7.3 Added plugin parameter.
	 *
	 * @param array $plugin Plugin data.
	 */
	public function recommended_plugin_block_html( $plugin = [] ) {

		if ( ! $plugin ) {
			return;
		}

		$install_url = wp_nonce_url(
			self_admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( $plugin['slug'] ) ),
			'install-plugin_' . $plugin['slug']
		);
		?>

		<div class="wpforms-dash-widget-recommended-plugin-block">
			<span class="wpforms-dash-widget-recommended-plugin">
				<span class="recommended"><?php esc_html_e( 'Recommended Plugin:', 'wpforms' ); ?></span>
				<strong><?php echo esc_html( $plugin['name'] ); ?></strong>
				<span class="sep">-</span>
				<span class="action-links">
					<?php if ( wpforms_can_install( 'plugin' ) ) { ?>
						<a href="<?php echo esc_url( $install_url ); ?>"><?php esc_html_e( 'Install', 'wpforms' ); ?></a>
						<span class="sep sep-vertical">&vert;</span>
					<?php } ?>
					<a href="<?php echo esc_url( $plugin['more'] ); ?>?utm_source=wpformsplugin&utm_medium=link&utm_campaign=wpformsdashboardwidget"><?php esc_html_e( 'Learn More', 'wpforms' ); ?></a>
				</span>
			</span>
			<button type="button" id="wpforms-dash-widget-dismiss-recommended-plugin-block" class="wpforms-dash-widget-dismiss-recommended-plugin-block" title="<?php esc_html_e( 'Dismiss recommended plugin', 'wpforms' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<?php
	}

	/**
	 * Get empty chart HTML.
	 *
	 * @since 1.5.0
	 */
	public function get_empty_chart_html() {

		ob_start();
		?>

		<div class="wpforms-error wpforms-error-no-data-chart">
			<div class="wpforms-dash-widget-modal">
				<h2><?php esc_html_e( 'No entries for selected period', 'wpforms' ); ?></h2>
				<p><?php esc_html_e( 'Please select a different period or check back later.', 'wpforms' ); ?></p>
			</div>
		</div>

		<?php

		return ob_get_clean();
	}

	/**
	 * Get timespan options for $element (in days).
	 *
	 * @since 1.5.0
	 * @deprecated 1.5.2
	 *
	 * @param string $element Possible value: 'chart' or 'forms_list'.
	 *
	 * @return array
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_timespan_options_for( $element ) {

		_deprecated_function( __METHOD__, '1.5.2 of the WPForms plugin', 'get_timespan_options()' );

		return $this->get_timespan_options();
	}

	/**
	 * Get default timespan option for $element.
	 *
	 * @since 1.5.0
	 * @deprecated 1.5.2
	 *
	 * @param string $element Possible value: 'chart' or 'forms_list'.
	 *
	 * @return int|null
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_timespan_default_for( $element ) {

		_deprecated_function( __METHOD__, '1.5.2 of the WPForms plugin', 'DashboardWidget::get_timespan_default()' );

		return $this->get_timespan_default();
	}

	/**
	 * Converts number of days to day start and day end values.
	 *
	 * @since 1.5.5
	 *
	 * @param integer $days Timespan days.
	 *
	 * @return array|false
	 */
	public function get_days_interval( $days = 0 ) {

		// PHP DateTime supported string (http://php.net/manual/en/datetime.formats.php).
		$date_end_str  = $this->settings['date_end_str'];
		$modify_offset = (float) get_option( 'gmt_offset' ) * 60 . ' minutes';

		try {
			$now               = new DateTime();
			$interval['start'] = new DateTime( $date_end_str );
			$interval['end']   = new DateTime( $date_end_str );

			$interval['end']
				->setTime( $now->format( 'H' ), $now->format( 'i' ), $now->format( 's' ) )
				->modify( $modify_offset )
				->setTime( 23, 59, 59 );
			$interval['start']
				->setTime( $now->format( 'H' ), $now->format( 'i' ), $now->format( 's' ) )
				->modify( $modify_offset )
				->modify( '-' . ( absint( $days ) - 1 ) . 'days' )
				->setTime( 0, 0 );
		} catch ( Exception $e ) {
			return false;
		}

		return $interval;
	}


	/**
	 * Get entries count grouped by $param.
	 * Main point of entry to fetch form entry count data from DB.
	 * Caches the result.
	 *
	 * @since 1.5.0
	 *
	 * @param string $param   Possible value: 'date' or 'form'.
	 * @param int    $days    Timespan (in days) to fetch the data for.
	 * @param int    $form_id Form ID to fetch the data for.
	 *
	 * @return array
	 * @throws Exception When dates management fails.
	 */
	public function get_entries_count_by( $param, $days = 0, $form_id = 0 ) {

		// Allow only 'date' and 'form' params.
		if ( ! in_array( $param, [ 'date', 'form' ], true ) ) {
			return [];
		}

		// Allow results caching to reduce DB load.
		$allow_caching = $this->settings['allow_data_caching'];

		if ( $allow_caching ) {
			$widget_slug    = static::SLUG;
			$transient_name = $this->get_entries_transient_name( $param, $days, $form_id );

			$cache = get_transient( $transient_name );

			// Filter the cache to clear or alter its data.
			// phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation, WPForms.PHP.ValidateHooks.InvalidHookName
			$cache = apply_filters( "wpforms_{$widget_slug}_cached_data", $cache, $param, $days, $form_id );

			if ( is_array( $cache ) && count( $cache ) > 0 ) {
				return $cache;
			}
		}

		$dates = $this->get_days_interval( $days );

		switch ( $param ) {
			case 'date':
				$result = $this->get_entries_count_by_date_sql( $form_id, $dates['start'], $dates['end'] );
				break;

			case 'form':
				$result = $this->get_entries_count_by_form_sql( $form_id, $dates['start'], $dates['end'] );
				break;

			default:
				$result = [];
		}

		if ( $allow_caching ) {
			// Transient lifetime in seconds. Defaults to the end of a current day.
			$transient_lifetime = $this->settings['transient_lifetime'];

			set_transient( $transient_name, $result, $transient_lifetime );
		}

		return $result;
	}

	/**
	 * Get total number of form entries.
	 *
	 * @since 1.7.4
	 *
	 * @return int
	 * @throws Exception When dates management fails.
	 */
	private function get_total_entries() {

		$total = 0;
		$forms = $this->get_entries_count_by( 'form' );

		if ( ! empty( $forms ) ) {
			foreach ( $forms as $form ) {
				$total += $form['count'];
			}
		}

		return (int) $total;
	}

	/**
	 * Get entries count grouped by date.
	 * In most cases it's better to use `get_entries_count_by( 'date' )` instead.
	 * Doesn't cache the result.
	 *
	 * @since 1.5.0
	 * @since 1.7.5 Filter the forms where entries are fetched by allowed
	 *                  access of the current user.
	 *
	 * @param int      $form_id    Form ID to fetch the data for.
	 * @param DateTime $date_start Start date for the search.
	 * @param DateTime $date_end   End date for the search.
	 *
	 * @return array
	 * @throws Exception When dates are failing.
	 */
	public function get_entries_count_by_date_sql( $form_id = 0, $date_start = null, $date_end = null ) {

		if ( ! empty( $form_id ) && ! wpforms_current_user_can( 'view_entries_form_single', $form_id ) ) {
			return [];
		}

		$results = $this->entries_count->get_by_date_sql( $form_id, $date_start, $date_end );

		if ( ! $this->settings['display_chart_empty_entries'] ) {
			return $results;
		}

		return $this->fill_chart_empty_entries( $results, $date_start, $date_end );
	}

	/**
	 * Get entries count grouped by form.
	 * In most cases it's better to use `get_entries_count_by( 'form' )` instead.
	 * Doesn't cache the result.
	 *
	 * @since 1.5.0
	 * @since 1.7.5 Filter the results by allowed access of the current user.
	 *
	 * @param int      $form_id    Form ID to fetch the data for.
	 * @param DateTime $date_start Start date for the search.
	 * @param DateTime $date_end   End date for the search.
	 *
	 * @return array
	 */
	public function get_entries_count_by_form_sql( $form_id = 0, $date_start = null, $date_end = null ) {

		if ( ! empty( $form_id ) && ! wpforms_current_user_can( 'view_entries_form_single', $form_id ) ) {
			return [];
		}

		$results = $this->entries_count->get_by_form_sql( $form_id, $date_start, $date_end );

		// Keep backward compatibility converting an array to object.
		foreach ( $results as $key => $form ) {
			$results[ $key ] = (object) $form;
		}

		// Determine if the forms with no entries should appear in a forms list. Once switched, the effect applies after cache expiration.
		if ( $this->settings['display_forms_list_empty_entries'] ) {
			$forms = $this->fill_forms_list_empty_entries_form_data( $results );
		} else {
			$forms = $this->fill_forms_list_form_data( $results );
		}

		$access_obj = wpforms()->get( 'access' );

		return (
		$access_obj ?
			$access_obj->filter_forms_by_current_user_capability( $forms, 'view_entries_form_single' ) :
			[]
		);
	}

	/**
	 * Fill DB results with empty entries where there's no data.
	 * Needed to correctly distribute labels and data on a chart.
	 *
	 * @since 1.5.0
	 *
	 * @param array    $results    DB results from `$wpdb->prepare()`.
	 * @param DateTime $date_start Start date for the search.
	 * @param DateTime $date_end   End date for the search.
	 *
	 * @return array
	 * @throws Exception DatePeriod may throw an exception.
	 */
	public function fill_chart_empty_entries( $results, $date_start, $date_end ) {

		if ( ! is_array( $results ) ) {
			return [];
		}

		$period = new DatePeriod(
			$date_start,
			new DateInterval( 'P1D' ),
			$date_end
		);

		foreach ( $period as $value ) {
			/**
			 * Period value.
			 *
			 * @var DateTime $value
			 */
			$date = $value->format( 'Y-m-d' );

			if ( ! array_key_exists( $date, $results ) ) {
				$results[ $date ] = [
					'day'   => $date,
					'count' => 0,
				];

				continue;
			}

			// Mold an object to array to stay uniform.
			$results[ $date ] = (array) $results[ $date ];
		}

		ksort( $results );

		return $results;
	}

	/**
	 * Fill a forms list with the data needed for a frontend display.
	 *
	 * @since 1.5.0
	 *
	 * @param array $results DB results from `$wpdb->prepare()`.
	 *
	 * @return array
	 */
	public function fill_forms_list_form_data( $results ) {

		if ( ! is_array( $results ) ) {
			return [];
		}

		$processed = [];

		foreach ( $results as $form_id => $result ) {

			$form_obj = wpforms()->get( 'form' );
			$form     = $form_obj ? $form_obj->get( $form_id ) : null;

			if ( empty( $form ) ) {
				continue;
			}

			$data = $this->get_formatted_forms_list_form_data( $form, $results );

			if ( $data ) {
				$processed[ $form->ID ] = $data;
			}
		}

		return $processed;
	}

	/**
	 * Fill a forms list with the data needed for a frontend display.
	 * Includes forms with zero entries.
	 *
	 * @since 1.5.0
	 *
	 * @param array $results DB results from `$wpdb->prepare()`.
	 *
	 * @return array
	 */
	public function fill_forms_list_empty_entries_form_data( $results ) {

		if ( ! is_array( $results ) ) {
			return [];
		}

		$form_obj = wpforms()->get( 'form' );
		$forms    = $form_obj ? $form_obj->get() : null;

		if ( empty( $forms ) ) {
			return [];
		}

		$processed = [];

		foreach ( $forms as $form ) {

			$data = $this->get_formatted_forms_list_form_data( $form, $results );

			if ( $data ) {
				$processed[ $form->ID ] = $data;
			}
		}

		return wp_list_sort( $processed, 'count', 'DESC' );
	}

	/**
	 * Get formatted form data for a forms list frontend display.
	 *
	 * @since 1.5.4
	 *
	 * @param WP_Post $form    Form object.
	 * @param array   $results DB results from `$wpdb->prepare()`.
	 *
	 * @return array
	 */
	public function get_formatted_forms_list_form_data( $form, $results ) {

		if ( ! ( $form instanceof WP_Post ) ) {
			return [];
		}

		$widget_slug = static::SLUG;

		$edit_url = add_query_arg(
			[
				'page'    => 'wpforms-entries',
				'view'    => 'list',
				'form_id' => absint( $form->ID ),
			],
			admin_url( 'admin.php' )
		);

		$form_data = [
			'form_id'  => $form->ID,
			'count'    => isset( $results[ $form->ID ]->count ) ? absint( $results[ $form->ID ]->count ) : 0,
			'title'    => $form->post_title,
			'edit_url' => $edit_url,
		];

		// phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation, WPForms.PHP.ValidateHooks.InvalidHookName
		return (array) apply_filters( "wpforms_{$widget_slug}_form_item_fields", $form_data, $form );
	}

	/**
	 * Get the data for a chart using AJAX.
	 *
	 * @since 1.5.0
	 *
	 * @throws Exception Exception.
	 */
	public function get_chart_data_ajax() {

		check_admin_referer( 'wpforms_' . static::SLUG . '_nonce' );

		$days    = ! empty( $_POST['days'] ) ? absint( $_POST['days'] ) : 0;
		$form_id = ! empty( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

		$data = $this->get_entries_count_by( 'date', $days, $form_id );

		wp_send_json( $data );
	}

	/**
	 * Get the data for a forms list using AJAX.
	 *
	 * @since 1.5.0
	 *
	 * @throws Exception Exception.
	 */
	public function get_forms_list_ajax() {

		check_admin_referer( 'wpforms_' . static::SLUG . '_nonce' );

		$days = ! empty( $_POST['days'] ) ? absint( $_POST['days'] ) : 0;

		ob_start();
		$this->forms_list_block( $days );
		wp_send_json( ob_get_clean() );
	}

	/**
	 * Clear dashboard widget cached data.
	 *
	 * @since 1.5.2
	 */
	public static function clear_widget_cache() {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
				'%wpforms_' . $wpdb->esc_like( static::SLUG ) . '_pro_entries_by_%'
			)
		);
	}

	/**
	 * Get transient name for entries count by $param.
	 *
	 * @since 1.8.6
	 *
	 * @param string $param   Possible value: 'date' or 'form'.
	 * @param int    $days    Timespan (in days) to fetch the data for.
	 * @param int    $form_id Form ID to fetch the data for.
	 *
	 * @return string
	 */
	private function get_entries_transient_name( $param, $days, $form_id ): string {

		$widget_slug = static::SLUG;
		$user_id     = get_current_user_id();

		return "wpforms_{$widget_slug}_pro_entries_by_{$param}_{$days}_user_{$user_id}_form_{$form_id}";
	}
}
