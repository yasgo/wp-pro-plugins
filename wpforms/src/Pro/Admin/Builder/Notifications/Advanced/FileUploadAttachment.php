<?php

namespace WPForms\Pro\Admin\Builder\Notifications\Advanced;

use WPForms_Builder_Panel_Settings;
use WPForms_Field_File_Upload;
use WPForms_WP_Emails;

/**
 * Class FileUpload.
 *
 * Contains functionality for File upload attachment in email notifications.
 *
 * @since 1.7.8
 */
class FileUploadAttachment {

	/**
	 * Initialize class.
	 *
	 * @since 1.7.8
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.7.8
	 */
	private function hooks() {

		add_filter( 'wpforms_builder_strings', [ $this, 'javascript_strings' ], 10, 2 );
		add_filter( 'wpforms_pro_admin_builder_notifications_advanced_settings_content', [ $this, 'settings' ], 10, 3 );
		add_filter( 'wpforms_save_form_args', [ $this, 'format_data_on_save' ], 10, 3 );
		add_filter( 'wpforms_emails_send_email_data', [ $this, 'attach_file_uploads_in_email' ], 20, 2 );
	}

	/**
	 * Add localized strings.
	 *
	 * @since 1.7.8
	 *
	 * @param array  $strings Form builder JS strings.
	 * @param object $form    Current form.
	 *
	 * @return array
	 */
	public function javascript_strings( $strings, $form ) {

		$strings['notifications_file_upload'] = [
			'wp_max_upload_size' => wp_max_upload_size() / MB_IN_BYTES,
			'no_choices_text'    => esc_html__( 'You do not have any file upload fields', 'wpforms' ),
		];

		return $strings;
	}

	/**
	 * File upload settings.
	 *
	 * @since 1.7.8
	 *
	 * @param string                         $content  Notification > Advanced content.
	 * @param WPForms_Builder_Panel_Settings $settings Builder panel settings.
	 * @param int                            $id       Notification id.
	 *
	 * @return string
	 */
	public function settings( $content, $settings, $id ) {

		$content .= wpforms_panel_field(
			'toggle',
			'notifications',
			'file_upload_attachment_enable',
			$settings->form_data,
			esc_html__( 'Enable File Upload Attachments', 'wpforms' ),
			[
				'input_class' => 'notifications_enable_file_upload_attachment_toggle',
				'parent'      => 'settings',
				'subsection'  => $id,
			],
			false
		);

		$content .= $this->file_upload_attachment_fields( $settings->form_data, $id );

		return $content;
	}

	/**
	 * Get "File Upload Fields" select output build on saved values.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form_data       Form data.
	 * @param int   $notification_id Notification ID.
	 *
	 * @return string
	 */
	private function file_upload_attachment_fields( $form_data, $notification_id ) {

		$field   = 'file_upload_attachment_fields';
		$values  = Settings::get_array_value_from_field( $form_data, $notification_id, $field );
		$options = Settings::get_fields_from_form_data( $form_data, $values, [], [ 'file-upload' ] );

		$note = sprintf(
			wp_kses( /* translators: %s - link to the WPForms.com doc article. */
				__( '<strong>Heads up!</strong> Some email providers have limits on attachment file size. If your visitors upload large files, your notifications may not be delivered. <a href="%s" target="_blank" rel="noopener noreferrer">Learn More</a>', 'wpforms' ),
				[
					'a'      => [
						'href'   => [],
						'rel'    => [],
						'target' => [],
					],
					'strong' => [],
				]
			),
			esc_url(
				wpforms_utm_link(
					'https://wpforms.com/docs/attaching-files-to-form-notification-emails/',
					'Builder Notifications',
					'Form Attachments Documentation'
				)
			)
		);

		$file_size_note = wp_kses(
			__( 'You allow attaching up to <strong><span class="notifications-file-upload-attachment-size">0</span> MB</strong>', 'wpforms' ),
			[
				'strong' => [],
				'span'   => [
					'class' => [],
				],
			]
		);

		return Settings::get_choicesjs_field(
			$notification_id,
			$field,
			$values,
			$options,
			__( 'File Upload Fields', 'wpforms' ),
			[
				'after'   => "<p class='note'>{$note}</p><p class='note'>{$file_size_note}</p>",
				'tooltip' => __( 'Select the file upload field(s) containing the files you’d like to receive as attachments.', 'wpforms' ),
			]
		);
	}

	/**
	 * Format the File Upload Attachment data.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form Form array which is usable with `wp_update_post()`.
	 * @param array $data Form custom data.
	 * @param array $args Empty by default, may have custom data not intended to be saved, but used for processing.
	 *
	 * @return array
	 */
	public function format_data_on_save( $form, $data, $args ) {

		return Settings::attach_notification_data_in_form_data( $form, $this->get_file_upload_attachment_fields_from_post( $data ) );
	}

	/**
	 * Returns an array of `file_upload_attachment_fields` from $_POST.
	 *
	 * The array keys are the notification ID.
	 *
	 * @since 1.7.8
	 *
	 * @param array $post_data Data retrieved from $_POST and processed.
	 *
	 * @return array
	 */
	private function get_file_upload_attachment_fields_from_post( $post_data ) {

		if ( empty( $post_data['settings']['notifications'] ) ) {
			return [];
		}

		$file_upload_attachment_fields = [];

		foreach ( $post_data['settings']['notifications'] as $id => $notification ) {

			// Save a notification ID.
			$file_upload_attachment_fields[ $id ] = [];

			if ( ! isset( $notification['file_upload_attachment_fields']['hidden'] ) ) {
				continue;
			}

			// Sanitize Entry Information items.
			$file_upload_attachment_fields[ $id ]['file_upload_attachment_fields'] = array_map(
				'absint',
				(array) json_decode( wp_unslash( $notification['file_upload_attachment_fields']['hidden'] ) )
			);
		}

		return $file_upload_attachment_fields;
	}

	/**
	 * Attached the File Uploads in the email.
	 *
	 * @since 1.7.8
	 *
	 * @param array             $email     Email data to be used when sending email.
	 * @param WPForms_WP_Emails $email_obj WPForms_WP_Emails object in context.
	 *
	 * @return array
	 */
	public function attach_file_uploads_in_email( $email, $email_obj ) {

		if (
			! isset( $email_obj->form_data, $email_obj->notification_id, $email_obj->fields ) ||
			empty( $email_obj->fields ) ||
			empty( $email_obj->form_data['settings']['notifications'][ $email_obj->notification_id ]['file_upload_attachment_enable'] ) ||
			empty( $email_obj->form_data['settings']['notifications'][ $email_obj->notification_id ]['file_upload_attachment_fields'] )
		) {
			return $email;
		}

		$attachments = $this->get_file_upload_attachments(
			$email_obj->form_data['id'],
			$email_obj->entry_id,
			$email_obj->fields,
			$email_obj->form_data['settings']['notifications'][ $email_obj->notification_id ]['file_upload_attachment_fields']
		);

		if ( ! empty( $attachments ) ) {
			$email['attachments'] = array_merge( (array) $email['attachments'], $attachments );
		}

		return $email;
	}

	/**
	 * Return an array containing the file paths to be attached in the notification email.
	 *
	 * @since 1.7.8
	 *
	 * @param string $form_id                      Form ID.
	 * @param int    $entry_id                     Entry ID.
	 * @param array  $entry_fields                 Entry fields.
	 * @param array  $file_upload_fields_to_attach Array of File Upload field IDs to attach in the email.
	 *
	 * @return array
	 */
	private function get_file_upload_attachments( $form_id, $entry_id, $entry_fields, $file_upload_fields_to_attach ) {

		$wpforms_upload_dir = wpforms_upload_dir();

		if ( ! empty( $wpforms_upload_dir['error'] ) ) {
			Settings::log_error(
				'File Upload Attachments: Unable to attach file uploads.',
				[
					'error' => $wpforms_upload_dir['error'],
				],
				$form_id,
				$entry_id
			);

			return [];
		}

		$attachments = [];

		foreach ( $file_upload_fields_to_attach as $file_upload_field_id ) {

			if (
				! isset( $entry_fields[ $file_upload_field_id ] ) ||
				$entry_fields[ $file_upload_field_id ]['type'] !== 'file-upload' ||
				empty( $entry_fields[ $file_upload_field_id ]['value'] )
			) {
				continue;
			}

			$file_paths = WPForms_Field_File_Upload::get_entry_field_file_paths( $form_id, $entry_fields[ $file_upload_field_id ] );

			if ( empty( $file_paths ) ) {
				continue;
			}

			foreach ( $file_paths as $file_path ) {
				$attachments[] = $file_path;
			}
		}

		return $attachments;
	}
}
