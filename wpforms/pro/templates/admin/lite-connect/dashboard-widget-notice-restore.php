<?php
/**
 * Admin/LiteConnect Dashboard Widget Restore Entries notice template for Pro.
 *
 * @since 1.7.4
 *
 * @var string $entries_since_info Entries information string.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div id="wpforms-dash-widget-lite-connect" class="wpforms-dash-widget-chart-overlay">
	<div class="wpforms-dash-widget-modal">

		<h4><?php esc_html_e( 'Restore Your Form Entries', 'wpforms' ); ?></h4>

		<p>
			<?php echo $entries_since_info; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php esc_html_e( 'Restore them now and get instant access to reports.', 'wpforms' ); ?>
		</p>

		<a href="<?php echo esc_url( add_query_arg( [ 'wpforms_lite_connect_action' => 'import' ] ) ); ?>" class="wpforms-btn">
			<?php esc_html_e( 'Restore Entries Now', 'wpforms' ); ?>
		</a>

	</div>
</div>
