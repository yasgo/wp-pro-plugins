<?php

/**
 * Payment class.
 *
 * @since 1.0.0
 */
abstract class WPForms_Payment {

	/**
	 * Payment addon version.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Payment name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Payment name in slug format.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Load priority.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public $priority = 10;

	/**
	 * Payment icon.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $icon;

	/**
	 * Form data and settings.
	 *
	 * @since 1.1.0
	 *
	 * @var array
	 */
	public $form_data;

	/**
	 * Flag for modified strings.
	 *
	 * @since 1.7.5
	 *
	 * @var bool
	 */
	private static $i18n_modified = false;

	/**
	 * Flag for recommended payments.
	 *
	 * @since 1.7.7.2
	 *
	 * @var bool
	 */
	protected $recommended = false;

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$this->init();

		// Add to list of available payments.
		add_filter( 'wpforms_payments_available', [ $this, 'register_payment' ], $this->priority, 1 );

		// Fetch and store the current form data when in the builder.
		add_action( 'wpforms_builder_init', [ $this, 'builder_form_data' ] );

		// Output builder sidebar.
		add_action( 'wpforms_payments_panel_sidebar', [ $this, 'builder_sidebar' ], $this->priority );

		// Output builder content.
		add_action( 'wpforms_payments_panel_content', [ $this, 'builder_output' ], $this->priority );

		// Register builder HTML template(s).
		add_action( 'wpforms_builder_print_footer_scripts', [ $this, 'builder_templates' ] );

		// Enqueue assets and add localized strings.
		add_action( 'wpforms_builder_enqueues', [ $this, 'enqueues' ] );
		add_filter( 'wpforms_builder_strings', [ $this, 'add_localized_strings' ], 10, 2 );
	}

	/**
	 * All systems go. Used by subclasses.
	 *
	 * @since 1.0.0
	 */
	public function init() {
	}

	/**
	 * Add to list of registered payments.
	 *
	 * @since 1.0.0
	 *
	 * @param array $payments An array of all the active payment providers.
	 *
	 * @return array
	 */
	public function register_payment( $payments = [] ) {

		$payments[ $this->slug ] = $this->name;

		return $payments;
	}

	/**
	 * Enqueue builder's assets.
	 *
	 * @since 1.7.5
	 *
	 * @param string $view Current view.
	 */
	public function enqueues( $view ) {

		$min = wpforms_get_min_suffix();

		wp_enqueue_script(
			'wpforms-builder-payments',
			WPFORMS_PLUGIN_URL . "assets/pro/js/admin/builder/payments{$min}.js",
			[ 'wpforms-builder-conditionals' ],
			WPFORMS_VERSION,
			true
		);
	}

	/**
	 * Add localized strings.
	 *
	 * @since 1.7.5
	 *
	 * @param array  $strings List of builder strings.
	 * @param object $form    CPT of the form.
	 *
	 * @return array
	 */
	public function add_localized_strings( $strings, $form ) {

		if ( self::$i18n_modified ) {
			return $strings;
		}

		$disabled_message = sprintf(
			wp_kses( /* translators: %s - payment provider. */
				__( "<p>One of %s's recurring payment plans doesn't have conditional logic, which means that One-Time Payments will never work and were disabled.</p>", 'wpforms' ), // phpcs:ignore WordPress.WP.I18n.NoHtmlWrappedStrings
				[
					'p' => [],
				]
			),
			'{provider}'
		);

		$disabled_message .= sprintf(
			wp_kses( /* translators: %s - payment provider. */
				__( '<p>You should check your settings in <strong>Payments » %s</strong>.</p>', 'wpforms' ), // phpcs:ignore WordPress.WP.I18n.NoHtmlWrappedStrings
				[
					'p'      => [],
					'strong' => [],
				]
			),
			'{provider}'
		);

		self::$i18n_modified = true;

		return array_merge(
			$strings,
			[
				'payment_one_time_payments_disabled' => $disabled_message,
				'payment_plan_prompt'                => esc_html__( 'Enter a plan name', 'wpforms' ),
				'payment_plan_prompt_placeholder'    => esc_html__( 'Eg: Monthly Subscription', 'wpforms' ),
				'payment_plan_placeholder'           => esc_html__( 'Plan Name #{id}', 'wpforms' ),
				'payment_plan_confirm'               => esc_html__( 'Are you sure you want to delete this recurring plan?', 'wpforms' ),
				'payment_error_name'                 => esc_html__( 'You must provide a plan name.', 'wpforms' ),
			]
		);
	}

	/********************************************************
	 * Builder methods - these methods _build_ the Builder. *
	 ********************************************************/

	/**
	 * Fetch and store the current form data when in the builder.
	 *
	 * @since 1.1.0
	 */
	public function builder_form_data() {

		// Get current revision, if available.
		$revision = wpforms()->get( 'revisions' )->get_revision();

		// If we're viewing a valid revision, set the form data so the Form Builder shows correct state.
		if ( $revision && isset( $revision->post_content ) ) {
			$this->form_data = wpforms_decode( $revision->post_content );

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : false;

		$this->form_data = wpforms()->get( 'form' )->get(
			$form_id,
			[ 'content_only' => true ]
		);
	}

	/**
	 * Display content inside the panel sidebar area.
	 *
	 * @since 1.0.0
	 * @since 1.7.5.3 Added `is_payments_enabled` method to check if payments are enabled.
	 */
	public function builder_sidebar() {

		echo wpforms_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'builder/payment/sidebar',
			[
				'configured'  => $this->is_payments_enabled() ? 'configured' : '',
				'slug'        => $this->slug,
				'icon'        => $this->icon,
				'name'        => $this->name,
				'recommended' => $this->recommended,
			],
			true
		);
	}

	/**
	 * Wrap the builder content with the required markup.
	 *
	 * @since 1.0.0
	 */
	public function builder_output() {

		?>
		<div class="wpforms-panel-content-section wpforms-panel-content-section-<?php echo esc_attr( $this->slug ); ?>"
			id="<?php echo esc_attr( $this->slug ); ?>-provider" data-provider="<?php echo esc_attr( $this->slug ); ?>">

			<div class="wpforms-panel-content-section-title">

				<?php echo esc_html( $this->name ); ?>

			</div>

			<div class="wpforms-payment-settings wpforms-clear">

				<?php $this->builder_content(); ?>

			</div>

		</div>
		<?php
	}

	/**
	 * Display content inside the panel content area.
	 *
	 * @since 1.0.0
	 */
	public function builder_content() {

		$this->builder_content_one_time();
		$this->builder_content_recurring();
	}

	/**
	 * Builder content for one time payments.
	 *
	 * @since 1.7.5
	 */
	private function builder_content_one_time() {
		?>

		<div class="wpforms-panel-content-section-payment">
			<h2 class="wpforms-panel-content-section-payment-subtitle">
				<?php esc_html_e( 'One-Time Payments', 'wpforms' ); ?>
			</h2>
			<?php
			wpforms_panel_field(
				'toggle',
				$this->slug,
				'enable_one_time',
				$this->form_data,
				esc_html__( 'Enable one-time payments', 'wpforms' ),
				[
					'parent'  => 'payments',
					'default' => '0',
					'tooltip' => esc_html__( 'Allow your customers to one-time pay via the form.', 'wpforms' ),
					'class'   => 'wpforms-panel-content-section-payment-toggle wpforms-panel-content-section-payment-toggle-one-time',
				]
			);
			?>
			<div class="wpforms-panel-content-section-payment-one-time wpforms-panel-content-section-payment-toggled-body">
				<?php echo $this->get_builder_content_one_time_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Builder content for recurring payments.
	 *
	 * @since 1.7.5
	 */
	private function builder_content_recurring() {
		?>

		<div class="wpforms-panel-content-section-payment">
			<h2 class="wpforms-panel-content-section-payment-subtitle">
				<?php esc_html_e( 'Recurring Payments', 'wpforms' ); ?>
			</h2>
			<a href="#" class="wpforms-panel-content-section-payment-button wpforms-panel-content-section-payment-button-add-plan">
				<?php esc_html_e( 'Add New Plan', 'wpforms' ); ?>
			</a>
			<?php
			wpforms_panel_field(
				'toggle',
				$this->slug,
				'enable_recurring',
				$this->form_data,
				esc_html__( 'Enable recurring subscription payments', 'wpforms' ),
				[
					'parent'  => 'payments',
					'default' => '0',
					'tooltip' => esc_html__( 'Allow your customer to pay recurringly via the form.', 'wpforms' ),
					'class'   => 'wpforms-panel-content-section-payment-toggle wpforms-panel-content-section-payment-toggle-recurring',
				]
			);
			?>
			<div class="wpforms-panel-content-section-payment-recurring wpforms-panel-content-section-payment-toggled-body">
				<?php
				/**
				 * Before recurring payments content.
				 *
				 * @since 1.7.5
				 *
				 * @param string $slug Payment slug.
				 */
				do_action( 'wpforms_payment_builder_content_recurring_before', $this->slug );

				if ( empty( $this->form_data['payments'][ $this->slug ]['recurring'] ) ) {
					$this->form_data['payments'][ $this->slug ]['recurring'][] = [];
				}

				foreach ( $this->form_data['payments'][ $this->slug ]['recurring'] as $plan_id => $plan_settings ) {
					$this->builder_content_recurring_item( $plan_id );
				}

				/**
				 * After recurring payments content.
				 *
				 * @since 1.7.5
				 *
				 * @param string $slug Payment slug.
				 */
				do_action( 'wpforms_payment_builder_content_recurring_after', $this->slug );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Builder content for the recurring payment item.
	 *
	 * @since 1.7.5
	 *
	 * @param string $plan_id Plan ID.
	 */
	private function builder_content_recurring_item( $plan_id ) {

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wpforms_render(
			'builder/payment/recurring/item',
			[
				'plan_id' => $plan_id,
				'content' => $this->get_builder_content_recurring_payment_content( $plan_id ),
			],
			true
		);
	}

	/**
	 * Register recurring plan template.
	 *
	 * @since 1.7.5
	 */
	public function builder_templates() {

		// Check if Payment addon have multiple subscription plans support.
		if ( empty( $this->get_builder_content_recurring_payment_content( '{{ data.index }}' ) ) ) {
			return;
		}
		?>

		<script type="text/html" id="tmpl-wpforms-builder-payments-<?php echo esc_attr( $this->slug ); ?>-clone">
			<?php $this->builder_content_recurring_item( '{{ data.index }}' ); ?>
		</script>

		<?php
	}

	/**
	 * Get content inside the one time payment area.
	 *
	 * @since 1.7.5
	 *
	 * @return string
	 */
	protected function get_builder_content_one_time_content() {

		return '';
	}

	/**
	 * Get content inside the recurring payment area.
	 *
	 * @since 1.7.5
	 *
	 * @param string $plan_id Plan ID.
	 *
	 * @return string
	 */
	protected function get_builder_content_recurring_payment_content( $plan_id ) {

		return '';
	}

	/**
	 * Check if payments enabled.
	 *
	 * @since 1.7.5.3
	 *
	 * @return bool
	 */
	private function is_payments_enabled() {

		return ! empty( $this->form_data['payments'][ $this->slug ]['enable'] ) || ! empty( $this->form_data['payments'][ $this->slug ]['enable_one_time'] ) || ! empty( $this->form_data['payments'][ $this->slug ]['enable_recurring'] );
	}
}
