<?php

namespace WPForms\Pro\Forms\Fields\FileUpload;

/**
 * Editing field entries.
 *
 * @since 1.6.6
 */
class EntriesEdit extends \WPForms\Pro\Forms\Fields\Base\EntriesEdit {

	/**
	 * Constructor.
	 *
	 * @since 1.6.6
	 */
	public function __construct() {

		parent::__construct( 'file-upload' );
	}

	/**
	 * Enqueues for the Edit Entry page.
	 *
	 * @since 1.6.6
	 */
	public function enqueues() {

		wp_enqueue_style(
			'tooltipster',
			WPFORMS_PLUGIN_URL . 'assets/lib/jquery.tooltipster/jquery.tooltipster.min.css',
			null,
			'4.2.6'
		);

		wp_enqueue_script(
			'tooltipster',
			WPFORMS_PLUGIN_URL . 'assets/lib/jquery.tooltipster/jquery.tooltipster.min.js',
			[ 'jquery' ],
			'4.2.6',
			true
		);
	}

	/**
	 * Display the field on the Edit Entry page.
	 *
	 * @since 1.6.6
	 *
	 * @param array $entry_field Entry field data.
	 * @param array $field       Field data and settings.
	 * @param array $form_data   Form data and settings.
	 */
	public function field_display( $entry_field, $field, $form_data ) {

		$html = '';

		$is_media_file = isset( $field['media_library'] );

		if ( \WPForms_Field_File_Upload::is_modern_upload( $entry_field ) ) {

			foreach ( $entry_field['value_raw'] as $key => $field_data ) {

				$html .= $this->get_file_item_html( $field_data, $is_media_file, $key );
			}
		} else {

			$html .= $this->get_file_item_html( $entry_field, $is_media_file );
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get HTML for the file item.
	 *
	 * @since 1.6.6
	 *
	 * @param array $field_data    Field data.
	 * @param bool  $is_media_file Is WP media.
	 * @param int   $key           Key for multiple items.
	 *
	 * @return string
	 */
	private function get_file_item_html( $field_data, $is_media_file, $key = 0 ) {

		$html = '<div class="file-entry">';

		$html .= $this->field_object->file_icon_html( $field_data );

		$html .= sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $field_data['value'] ),
			esc_html( $field_data['file_user_name'] )
		);

		$html .= sprintf(
			'<input type="hidden" name="wpforms[fields][%d][]" value="%s"/>',
			esc_attr( $field_data['id'] ),
			esc_attr( $key )
		);

		if ( $is_media_file ) {

			$title = sprintf(
				wp_kses( /* translators: %s - link to the Media Library. */
					__( 'Please use the default <a href="%s">WordPress Media</a> interface to remove this file.', 'wpforms' ),
					[
						'a' => [
							'href' => [],
						],
					]
				),
				esc_url( admin_url( 'upload.php' ) )
			);

			$html .= sprintf( '<i class="fa fa-question-circle wpforms-help-tooltip" title="%s"></i>', esc_html( $title ) );
		} else {
			$html .= $this->remove_button_html();
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Get remove button html.
	 *
	 * @since 1.6.6
	 *
	 * @return string
	 */
	private function remove_button_html() {

		return '<a class="delete button-link-delete" href=""><span class="dashicons dashicons-trash wpforms-trash-icon"></span></a>';
	}

	/**
	 * Format and sanitize a field while processing entry editing.
	 *
	 * @since 1.6.6
	 *
	 * @param int   $field_id     Field ID.
	 * @param mixed $field_submit Field value that was submitted.
	 * @param mixed $field_data   Existing field data.
	 * @param array $form_data    Form data and settings.
	 */
	public function format( $field_id, $field_submit, $field_data, $form_data ) {

		if ( ! \WPForms_Field_File_Upload::is_modern_upload( $field_data ) ) {

			if ( ! is_array( $field_submit ) ) {
				$field_data['value']         = '';
				$field_data['file_original'] = '';
				$field_data['ext']           = '';
			}

			wpforms()->get( 'process' )->fields[ $field_id ] = $field_data;

			return;
		}

		if ( ! isset( $field_data['value_raw'] ) || ! is_array( $field_submit ) ) {
			$field_data['value_raw'] = '';
			$field_data['value']     = '';

			wpforms()->get( 'process' )->fields[ $field_id ] = $field_data;

			return;
		}

		$field_data['value_raw'] = array_intersect_key( $field_data['value_raw'], array_combine( $field_submit, $field_submit ) );

		$field_data['value'] = implode( "\n", array_column( $field_data['value_raw'], 'value' ) );

		wpforms()->get( 'process' )->fields[ $field_id ] = $field_data;
	}

	/**
	 * Skip validation.
	 *
	 * @since 1.6.6
	 *
	 * @param int   $field_id     Field ID.
	 * @param mixed $field_submit Field value that was submitted.
	 * @param mixed $field_data   Existing field data.
	 * @param array $form_data    Form data and settings.
	 */
	public function validate( $field_id, $field_submit, $field_data, $form_data ) { }

}
