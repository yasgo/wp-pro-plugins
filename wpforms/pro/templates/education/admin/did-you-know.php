<?php
/**
 * Admin/DidYouKnow Education template for Pro.
 *
 * @since 1.6.6
 *
 * @var integer $cols Table columns count.
 * @var string  $desc Message body.
 * @var string  $more Learn More button URL.
 * @var string  $page The current page.
 * @var integer $item DYK message number.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<tr class="wpforms-dyk wpforms-dismiss-container">
	<td colspan="<?php echo esc_attr( $cols ); ?>">
		<div class="wpforms-dyk-fbox wpforms-dismiss-out">
			<svg class='wpforms-dyk-bulb' viewBox='0 0 352 512'>
				<path d='M176 0C73 0-.1 83.5 0 176.2a175 175 0 0 0 43.6 115.6C69.2 321 93.8 368.7 96 384v75.2c0 3.1 1 6.2 2.7 8.8l24.6 36.9c3 4.4 8 7.1 13.3 7.1h78.8a16 16 0 0 0 13.3-7.1l24.6-36.9a16 16 0 0 0 2.6-8.8l.1-75.2c2.3-15.7 27-63.2 52.4-92.2A176 176 0 0 0 176 0zm48 454.3L206.7 480H145l-17-25.7V448h95.8v6.3zm0-38.3h-96v-32h96v32zm60.4-145.3a341 341 0 0 0-50.6 81.3H118.2a341 341 0 0 0-50.6-81.3A143.5 143.5 0 0 1 32.1 176C31.8 99 92.3 32 176 32a144.2 144.2 0 0 1 108.4 238.7zM176 64c-61.8 0-112 50.3-112 112a16 16 0 1 0 32 0 80 80 0 0 1 80-80 16 16 0 1 0 0-32z'/>
			</svg>
			<div class="wpforms-dyk-message"><strong><?php esc_html_e( 'Did You Know?', 'wpforms' ); ?></strong><br>
				<?php echo esc_html( $desc ); ?>
			</div>
			<div class="wpforms-dyk-buttons">
				<?php if ( ! empty( $more ) ) : ?>
					<a href="<?php echo esc_url( $more ); ?>" target="_blank" rel="noopener noreferrer" class="learn-more"><?php esc_html_e( 'Learn More', 'wpforms' ); ?></a>
				<?php endif; ?>
				<a href="https://wpforms.com/pricing/?utm_source=WordPress&amp;utm_medium=DYK%20<?php echo esc_attr( ucfirst( $page ) ); ?>&amp;utm_campaign=plugin&amp;utm_content=<?php echo (int) $item; ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
					<?php esc_html_e( 'Upgrade to Pro', 'wpforms' ); ?>
				</a>
				<button type="button" class="wpforms-dismiss-button" title="<?php esc_attr_e( 'Dismiss this message.', 'wpforms' ); ?>" data-section="admin-did-you-know-<?php echo esc_attr( $page ); ?>"></button>
			</div>
		</div>
	</td>
</tr>
