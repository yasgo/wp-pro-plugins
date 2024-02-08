<?php

namespace WPForms\Pro\Admin\Builder;

/**
 * Pro-related Form Builder stuff.
 *
 * @since 1.7.6
 */
class Builder {

	/**
	 * Primary class constructor.
	 *
	 * @since 1.7.6
	 */
	public function __construct() {

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.7.6
	 */
	private function hooks() {

		// Terminate initialization if not in builder.
		if ( ! wpforms_is_admin_page( 'builder' ) ) {
			return;
		}

		add_filter( 'wpforms_builder_strings', [ $this, 'form_builder_strings' ], 10, 2 );
		add_action( 'wpforms_builder_print_footer_scripts', [ $this, 'builder_templates' ] );
		add_action( 'wpforms_builder_enqueues', [ $this, 'builder_enqueues' ] );
	}

	/**
	 * Append additional strings for form builder.
	 *
	 * @since 1.7.6
	 *
	 * @param array  $strings List of strings.
	 * @param object $form    CPT of the form.
	 *
	 * @return array
	 */
	public function form_builder_strings( $strings, $form ) {

		$strings['notification_clone'] = esc_html__( ' - clone', 'wpforms' );

		$strings['notification_by_status_enable_alert'] = wp_kses( /* translators: %1$s - payment provider completed payments. Example: "PayPal Standard completed payments". */
			__( '<p>You have just enabled this notification for <strong>%1$s</strong>. Please note that this email notification will only send for <strong>%1$s</strong>.</p><p>If you\'d like to set up additional notifications for this form, please see our <a href="https://wpforms.com/docs/setup-form-notification-wpforms/" rel="nofollow noopener" target="_blank">tutorial</a>.</p>', 'wpforms' ),
			[
				'p'      => [],
				'strong' => [],
				'a'      => [
					'href'   => [],
					'rel'    => [],
					'target' => [],
				],
			]
		);

		$strings['notification_by_status_switch_alert'] = wp_kses( /* translators: %1$s - payment provider completed payments. Example: "PayPal Standard completed payments", %2$s - disabled Payment provider completed payments. */
			__( '<p>You have just <strong>disabled</strong> the notification for <strong>%2$s</strong> and <strong>enabled</strong> the notification for <strong>%1$s</strong>. Please note that this email notification will only send for <strong>%1$s</strong>.</p><p>If you\'d like to set up additional notifications for this form, please see our <a href="https://wpforms.com/docs/setup-form-notification-wpforms/" rel="nofollow noopener" target="_blank">tutorial</a>.</p>', 'wpforms' ),
			[
				'p'      => [],
				'strong' => [],
				'a'      => [
					'href'   => [],
					'rel'    => [],
					'target' => [],
				],
			]
		);

		return $strings;
	}

	/**
	 * Used to register the templates for setting blocks inside form builder.
	 *
	 * @since 1.7.6
	 */
	public function builder_templates() {

		$conditional_logic_tooltip = '<a href="' . esc_url( wpforms_utm_link( 'https://wpforms.com/docs/how-to-use-conditional-logic-with-wpforms/', 'Field Options', 'Conditional Logic Documentation' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'How to use Conditional Logic', 'wpforms' ) . '</a>';
		?>

		<!-- Confirmation block 'message' field template -->
		<script type="text/html" id="tmpl-wpforms-builder-confirmations-message-field">
			<div id="wpforms-panel-field-confirmations-message-{{ data.id }}-wrap" class="wpforms-panel-field wpforms-panel-field-tinymce" style="display: block;">
				<label for="wpforms-panel-field-confirmations-message-{{ data.id }}"><?php esc_html_e( 'Confirmation Message', 'wpforms' ); ?></label>
				<textarea id="wpforms-panel-field-confirmations-message-{{ data.id }}" name="settings[confirmations][{{ data.id }}][message]" rows="3" placeholder="" class="wpforms-panel-field-confirmations-message"></textarea>
				<a href="#" class="toggle-smart-tag-display toggle-unfoldable-cont" data-type="all" data-fields=""><i class="fa fa-tags"></i><span><?php esc_html_e( 'Show Smart Tags', 'wpforms' ); ?></span></a>
			</div>
		</script>

		<!-- Conditional logic toggle field template -->
		<script  type="text/html" id="tmpl-wpforms-builder-conditional-logic-toggle-field">
			<div id="wpforms-panel-field-settings-{{ data.type }}s-{{ data.id }}-conditional_logic-wrap" class="wpforms-panel-field wpforms-conditionals-enable-toggle wpforms-panel-field-checkbox">
				<span class="wpforms-toggle-control">
					<input type="checkbox" id="wpforms-panel-field-settings-{{ data.type }}s-{{ data.id }}-conditional_logic-checkbox" name="settings[{{ data.type }}s][{{ data.id }}][conditional_logic]" value="1"
						class="wpforms-panel-field-conditional_logic-checkbox"
						data-name="settings[{{ data.type }}s][{{ data.id }}]"
						data-actions="{{ data.actions }}"
						data-action-desc="{{ data.actionDesc }}">
					<label class="wpforms-toggle-control-icon" for="wpforms-panel-field-settings-{{ data.type }}s-{{ data.id }}-conditional_logic-checkbox"></label>
					<label for="wpforms-panel-field-settings-{{ data.type }}s-{{ data.id }}-conditional_logic-checkbox" class="wpforms-toggle-control-label">
						<?php esc_html_e( 'Enable Conditional Logic', 'wpforms' ); ?>
					</label><i class="fa fa-question-circle-o wpforms-help-tooltip tooltipstered" title="<?php echo esc_attr( $conditional_logic_tooltip ); ?>"></i>
				</span>
			</div>
		</script>

		<?php
	}

	/**
	 * Enqueue builder's assets.
	 *
	 * @since 1.7.6
	 *
	 * @param string $view Current view.
	 */
	public function builder_enqueues( $view ) {

		$min = wpforms_get_min_suffix();

		wp_enqueue_style(
			'wpforms-builder-pro',
			WPFORMS_PLUGIN_URL . "assets/pro/css/builder{$min}.css",
			[],
			WPFORMS_VERSION
		);
	}
}
