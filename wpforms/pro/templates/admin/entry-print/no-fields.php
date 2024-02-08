<?php
/**
 * No fields template for the Entry Print page.
 *
 * @var object $entry     Entry.
 * @var array  $form_data Form data and settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="print-item">
	<div class="print-item-value"><?php esc_html_e( 'This entry does not have any fields', 'wpforms' ); ?></div>
</div>
