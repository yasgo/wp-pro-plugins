<?php
/**
 * Legend template represents above the fold components for the content of its parent form.
 *
 * @since 1.8.2
 *
 * @var bool   $has_header Whether this is the first iteration?
 * @var object $entry      Entry.
 * @var array  $form_data  Form data and settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_title = isset( $form_data['settings']['form_title'] ) ? ucfirst( $form_data['settings']['form_title'] ) : '';

$field_options   = [
	'description'        => esc_html__( 'Field Description', 'wpforms' ),
	'empty'              => esc_html__( 'Empty Fields', 'wpforms' ),
	'unselected-choices' => esc_html__( 'Unselected Choices', 'wpforms' ),
	'html'               => esc_html__( 'HTML/Content fields', 'wpforms' ),
	'divider'            => esc_html__( 'Section Dividers', 'wpforms' ),
	'pagebreak'          => esc_html__( 'Page Breaks', 'wpforms' ),
];
$display_options = [
	'maintain-layout' => esc_html__( 'Maintain Layout', 'wpforms' ),
	'compact'         => esc_html__( 'Compact View', 'wpforms' ),
];

if ( ! empty( $entry->entry_notes ) ) {
	$display_options['note'] = esc_html__( 'Notes', 'wpforms' );
}

/**
 * Allow modifying options for the Field Settings section on the Entry Print page.
 *
 * @since 1.8.1
 *
 * @param array  $field_options List of print page options for the Field Section.
 * @param object $entry         Entry.
 * @param array  $form_data     Form data and settings.
 */
$field_options = (array) apply_filters( 'wpforms_pro_admin_entries_print_preview_field_options', $field_options, $entry, $form_data );

/**
 * Allow modifying options for the Display Settings section on the Entry Print page.
 *
 * @since 1.8.1
 *
 * @param array  $display_options List of print page options for the Display Section.
 * @param object $entry           Entry.
 * @param array  $form_data       Form data and settings.
 */
$display_options = (array) apply_filters( 'wpforms_pro_admin_entries_print_preview_display_options', $display_options, $entry, $form_data );

?>

<div class="wpforms-preview print-preview">
	<?php
	/**
	 * Fires on entry print page before a header section.
	 *
	 * @since 1.5.1
	 *
	 * @param object $entry     Entry.
	 * @param array  $form_data Form data and settings.
	 */
	do_action( 'wpforms_pro_admin_entries_printpreview_print_html_header_before', $entry, $form_data );
	?>
	<div class="page-title">
		<h1>
			<?php
			// Only the first entry should display the form title.
			if ( $has_header ) {
				// i.e. → "User Registration Form - ".
				printf( '%s - ', esc_html( $form_title ) );
			}
			?>
			<span>
				<?php
				printf( /* translators: %d - entry ID. */
					esc_html__( 'Entry #%d', 'wpforms' ),
					absint( $entry->entry_id )
				);
				?>
			</span>
		</h1>

		<?php
		// Only the first entry should display the form settings options.
		if ( $has_header ) :
		?>
			<div class="buttons no-print">
				<a href="#" class="button button-print print"
				   title="<?php esc_attr_e( 'Print', 'wpforms' ); ?>"><?php esc_html_e( 'Print', 'wpforms' ); ?></a>
				<div class="settings">
					<a href="#" class="button button-settings" title="<?php esc_attr_e( 'Cog', 'wpforms' ); ?>">
						<i class="fa fa-cog" aria-hidden="true"></i>
					</a>
					<div class="actions">
						<div class="title"><?php esc_html_e( 'Field Settings', 'wpforms' ); ?></div>
						<?php
						foreach ( $field_options as $slug => $label ) {
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo wpforms_render(
								'admin/entry-print/toggle-option',
								[
									'slug'  => $slug,
									'label' => $label,
								],
								true
							);
						}
						?>
						<div class="title"><?php esc_html_e( 'Display Settings', 'wpforms' ); ?></div>
						<?php
						foreach ( $display_options as $slug => $label ) {
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo wpforms_render(
								'admin/entry-print/toggle-option',
								[
									'slug'  => $slug,
									'label' => $label,
								],
								true
							);
						}
						?>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
	/**
	 * Fires on entry print page after a header section.
	 *
	 * @since 1.5.4.2
	 *
	 * @param object $entry     Entry.
	 * @param array  $form_data Form data and settings.
	 */
	do_action( 'wpforms_pro_admin_entries_printpreview_print_html_header_after', $entry, $form_data );
	?>
	<div class="print-body">
