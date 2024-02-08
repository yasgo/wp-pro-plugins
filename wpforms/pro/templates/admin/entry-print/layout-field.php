<?php
/**
 * Field Layout template for the Entry Print page.
 *
 * @var object $entry     Entry.
 * @var array  $form_data Form data and settings.
 * @var array  $field     Entry field data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="print-item field wpforms-field-layout">
	<?php
	foreach ( $field['columns'] as $column ) {
		$preset_width = ! empty( $column['width_preset'] ) ? (int) $column['width_preset'] : 50;

		if ( $preset_width === 33 ) {
			$preset_width = 33.33333;
		}

		if ( $preset_width === 67 ) {
			$preset_width = 66.66666;
		}

		$custom_width = ! empty( $column['width_custom'] ) ? (int) $column['width_custom'] : 50;
		$width        = min( $preset_width, $custom_width );

		?>
		<div class="print-item wpforms-field-layout-column" style="width: <?php echo (float) $width; ?>%">
			<?php
			foreach ( $column['fields'] as $child_field ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wpforms_render(
					'admin/entry-print/field',
					[
						'entry'     => $entry,
						'form_data' => $form_data,
						'field'     => $child_field,
					],
					true
				);
			}
			?>
		</div>
	<?php } ?>
</div>
