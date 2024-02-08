<?php
/**
 * Page break placeholder element and closing div tags for the print entry.
 *
 * @since 1.8.2
 *
 * @var bool $has_page_break Whether to insert page breaks before the current element.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
	</div> <!-- Close the .print-body element. -->
</div> <!-- Close the .print-preview element. -->

<?php

// force a page break when printing in between entries, so they don’t bleed together.
if ( $has_page_break ) {
	echo '<div class="page-break"></div>';
}
