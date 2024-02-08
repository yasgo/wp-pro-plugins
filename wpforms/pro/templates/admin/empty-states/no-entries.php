<?php
/**
 * No entries HTML template.
 *
 * @since 1.6.2.3
 *
 * @var string $message An abort message to display.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wpforms-admin-empty-state-container wpforms-admin-no-entries">

	<h2 class="waving-hand-emoji"><?php esc_html_e( 'Hi there!', 'wpforms' ); ?></h2>

	<p><?php echo esc_html( $message ); ?></p>

	<img src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/empty-states/no-entries.svg' ); ?>" alt="">

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpforms-entries' ) ); ?>" class="wpforms-btn wpforms-btn-lg wpforms-btn-orange">
		<?php esc_html_e( 'Back to All Entries', 'wpforms' ); ?>
	</a>

</div>
