<?php
/**
 * Notes template for the Entry Print page.
 *
 * @var object $entry     Entry.
 * @var array  $form_data Form data and settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $entry->entry_notes ) ) {
	return;
}
?>

<div class="print-item wpforms-field-notes">
	<div class="print-item-title"><?php esc_html_e( 'Notes', 'wpforms' ); ?></div>
	<div class="print-item-value">
		<?php
		foreach ( $entry->entry_notes as $note ) {
			$user      = get_userdata( $note->user_id );
			$user_name = ! empty( $user->display_name ) ? $user->display_name : $user->user_login;
			$date      = wpforms_datetime_format( $note->date, '', true );
			?>
			<div class="note-item">
				<div><?php echo wp_kses_post( $note->data ); ?></div>
				<div class="print-item-description">
					<?php
					printf( /* translators: %1$s - user name, %2$s - date. */
						esc_html__( 'Added by %1$s on %2$s', 'wpforms' ),
						esc_html( $user_name ),
						esc_html( $date )
					);
					?>
				</div>
			</div>
		<?php } ?>
	</div>
</div>
