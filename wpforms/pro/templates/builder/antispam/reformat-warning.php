<?php
/**
 * Keyword Filter reformat alert.
 *
 * @since 1.7.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpforms-alert wpforms-alert-warning wpforms-alert-dismissible wpforms-alert-keyword-filter-reformat">
	<div class="wpforms-alert-message">
		<p><?php esc_html_e( 'It appears your keyword filter list is comma-separated. Would you like to reformat it?', 'wpforms' ); ?></p>
	</div>

	<div class="wpforms-alert-buttons">
		<button type="button" class="wpforms-btn wpforms-btn-sm wpforms-btn-light-grey wpforms-btn-keyword-filter-reformat">
			<?php esc_html_e( 'Yes, Reformat', 'wpforms' ); ?>
		</button>
	</div>
</div>
