<?php
/**
 * Payment details metabox template.
 *
 * @since 1.8.2
 *
 * @var object $payment              Payment object.
 * @var string $payment_status       Payment status.
 * @var string $payment_type         Payment type.
 * @var string $payment_gateway      Payment gateway.
 * @var string $payment_total        Payment total.
 * @var string $payment_subscription Payment subscription description.
 * @var string $payment_url          Payment single URL.
 * @var object $entry                Submitted entry values.
 * @var array  $form_data            Form data and settings.
 * @var bool   $show_button          Whether to show the button to go to payment view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div id="wpforms-entry-payment" class="postbox">
	<div class="postbox-header">
		<h2 class="hndle">
			<span><?php esc_html_e( 'Payment Details', 'wpforms' ); ?></span>
		</h2>
	</div>
	<div class="inside">
		<div class="wpforms-entry-payment-meta">
			<p class="wpforms-entry-payment-meta-status status-<?php echo sanitize_html_class( $payment->status ); ?>">
				<?php
				printf(
					wp_kses( /* translators: %s - payment status. */
						__( 'Status: <strong>%s</strong>', 'wpforms' ),
						[
							'strong' => [],
						]
					),
					esc_html( $payment_status )
				);
				?>
			</p>

			<?php if ( $payment->type === 'subscription' ) : ?>
				<p class="wpforms-entry-payment-meta-type status-<?php echo sanitize_html_class( $payment->subscription_status ); ?>">
					<?php
					printf(
						wp_kses( /* translators: %s - payment type. */
							__( 'Type: <strong>%s</strong>', 'wpforms' ),
							[
								'strong' => [],
							]
						),
						esc_html( $payment_type )
					);

					printf(
						' (%s)',
						esc_html( $payment_subscription )
					);
					?>
				</p>
			<?php endif; ?>

			<p class="wpforms-entry-payment-meta-total">
				<?php
				printf(
					wp_kses( /* translators: %s - payment total. */
						__( 'Total: <strong>%s</strong>', 'wpforms' ),
						[
							'strong' => [],
						]
					),
					esc_html( $payment_total )
				);
				?>
			</p>
			<p class="wpforms-entry-payment-meta-gateway">
				<?php
				printf(
					wp_kses( /* translators: %s - payment gateway. */
						__( 'Gateway: <strong>%s</strong>', 'wpforms' ),
						[
							'strong' => [],
						]
					),
					esc_html( $payment_gateway )
				);

				if ( $payment->mode === 'test' ) {
					printf(
						' (%s)',
						esc_html( _x( 'Test', 'Gateway mode', 'wpforms' ) )
					);
				}
				?>
			</p>
			<?php
			// TODO: Deprecate and replace on `wpforms_payment_single_advanced_details` in 1.8.3 core version.
			// phpcs:ignore WPForms.Comments.PHPDocHooks.RequiredHookDocumentation, WPForms.PHP.ValidateHooks.InvalidHookName
			do_action( 'wpforms_entry_payment_sidebar_actions', $entry, $form_data );
			?>
		</div>

		<?php if ( $show_button ) : ?>
			<div class="wpforms-entry-payment-publishing-actions">
				<a class="submit button" href="<?php echo esc_url( $payment_url ); ?>">
					<?php esc_html_e( 'View Payment', 'wpforms' ); ?>
				</a>
				<div class="clear"></div>
			</div>
		<?php endif; ?>
	</div>
</div>
