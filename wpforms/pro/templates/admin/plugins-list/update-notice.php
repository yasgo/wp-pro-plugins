<?php
/**
 * Admin > Plugins list for Pro.
 * Template of the update plugin notice.
 *
 * @since 1.8.6
 *
 * @var string $plugin_slug   Plugin slug.
 * @var string $plugin_path   Plugin file path.
 * @var int    $columns_count Columns count.
 * @var string $update_notice Notice text markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kses_args = [
	'a'    => [
		'href'       => [],
		'target'     => [],
		'aria-label' => [],
		'rel'        => [],
	],
	'br'   => [],
	'span' => [
		'class' => [],
	],
]

?>
<tr class="plugin-update-tr active"
	id="<?php echo esc_attr( $plugin_slug ); ?>-update"
	data-slug="<?php echo esc_attr( $plugin_slug ); ?>"
	data-plugin="<?php echo esc_attr( $plugin_path ); ?>"
>
	<td colspan="<?php echo esc_attr( $columns_count ); ?>" class="plugin-update">
		<div class="update-message notice inline notice-warning notice-alt">
			<p>
				<?php echo wp_kses( $update_notice, $kses_args ); ?>
			</p>
		</div>
	</td>
</tr>
