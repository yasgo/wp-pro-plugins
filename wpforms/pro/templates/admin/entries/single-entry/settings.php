<?php
/**
 * Single entries setting block.
 *
 * @since {version}
 *
 * @var array $entry_view_settings Display settings for the single entry page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $entry_view_settings ) {
	return;
}

?>
<div class="wpforms-entries-settings-container">
	<button id="wpforms-entries-settings-button" class="wpforms-entries-settings-button button" type="button">
		<span class="dashicons dashicons-admin-generic"></span>
	</button>
	<div class="wpforms-entries-settings-menu">
		<div class="wpforms-entries-settings-menu-wrap wpforms-entries-settings-menu-items">

			<div class="wpforms-settings-title">
				<?php esc_html_e( 'Field Settings', 'wpforms' ); ?>
			</div>
			<?php
			foreach ( $entry_view_settings['fields'] as $slug => $settings ) {

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wpforms_panel_field_toggle_control(
					[],
					'wpforms-entry-setting-' . $slug,
					$slug,
					isset( $settings['label'] ) ? $settings['label'] : '',
					isset( $settings['value'] ) ? $settings['value'] : '',
					''
				);
			}
			?>
			<div class="wpforms-settings-title"><?php esc_html_e( 'Display Settings', 'wpforms' ); ?></div>
			<?php
			foreach ( $entry_view_settings['display'] as $slug => $settings ) {

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wpforms_panel_field_toggle_control(
					[],
					'wpforms-entry-setting-' . $slug,
					$slug,
					isset( $settings['label'] ) ? esc_html( $settings['label'] ) : '',
					isset( $settings['value'] ) ? esc_html( $settings['value'] ) : '',
					''
				);
			}
			?>

		</div>
	</div>
</div>
