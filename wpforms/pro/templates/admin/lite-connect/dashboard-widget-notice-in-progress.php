<?php
/**
 * Admin/LiteConnect Dashboard Widget Entries Restore in progress notice template for Pro.
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

		<img src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/lite-connect/wait.svg' ); ?>" alt="">

		<h4><?php esc_html_e( 'Entry Restore in Progress', 'wpforms' ); ?></h4>

		<p>
			<?php esc_html_e( 'Your entries are currently being imported. This should only take a few minutes. An admin notice will be displayed when the process is complete.', 'wpforms' ); ?>
		</p>

	</div>
</div>
