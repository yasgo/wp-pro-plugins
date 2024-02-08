<?php

namespace WPForms\Pro\Admin\Builder\Notifications\Advanced;

use WPForms\Pro\Helpers\CSV;
use WPForms\Pro\Tasks\Actions\EntryEmailCSVCleanupTask;
use WPForms_Builder_Panel_Settings;
use WPForms_WP_Emails;
use Exception;

/**
 * Class EntryCsvAttachment.
 *
 * Contains functionality for Entry CSV Attachment in email notifications.
 *
 * @since 1.7.7
 */
class EntryCsvAttachment {

	/**
	 * Default Entry CSV Attachment file name.
	 *
	 * @since 1.7.7
	 *
	 * @var string
	 */
	const DEFAULT_FILE_NAME = 'entry-details';

	/**
	 * Number of attempts when creating a new folder for Entry CSV Attachment.
	 *
	 * @since 1.7.7
	 *
	 * @var int
	 */
	const CREATE_FOLDER_MAX_ATTEMPT = 5;

	/**
	 * Length of the random name of the folder for Entry CSV Attachment.
	 *
	 * @since 1.7.7
	 *
	 * @var int
	 */
	const RANDOM_FOLDER_NAME_LENGTH = 5;

	/**
	 * Default Entry CSV Attachment folder name.
	 *
	 * @since 1.7.7
	 *
	 * @var string
	 */
	const FOLDER_NAME = 'entry-attachment-csv';

	/**
	 * The max character length of a file name.
	 *
	 * @since 1.7.7
	 *
	 * @var int
	 */
	const FILE_NAME_MAX_LENGTH = 200;

	/**
	 * CSV helper class instance.
	 *
	 * @since 1.7.7
	 *
	 * @var CSV
	 */
	private $csv;

	/**
	 * Initialize class.
	 *
	 * @since 1.7.7
	 */
	public function init() {

		$this->csv = new CSV();

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.7.7
	 */
	private function hooks() {

		add_filter( 'wpforms_builder_strings', [ $this, 'javascript_strings' ], 10, 2 );
		add_filter( 'wpforms_pro_admin_builder_notifications_advanced_settings_content', [ $this, 'settings' ], 10, 3 );
		add_filter( 'wpforms_save_form_args', [ $this, 'format_data_on_save' ], 10, 3 );
		add_filter( 'wpforms_emails_send_email_data', [ $this, 'attach_entry_csv_in_email' ], 10, 2 );
		add_filter( 'wpforms_tasks_get_tasks', [ $this, 'add_tasks' ] );
	}

	/**
	 * Add localized strings.
	 *
	 * @since 1.7.7
	 *
	 * @param array  $strings Form builder JS strings.
	 * @param object $form    Current form.
	 *
	 * @return array
	 */
	public function javascript_strings( $strings, $form ) {

		$strings['entry_information'] = [
			'default_file_name'    => self::DEFAULT_FILE_NAME,
			'excluded_tags'        => array_keys( $this->get_entry_information_excluded_tags() ),
			'localized'            => $this->get_all_fields_string(),
			'replacement_tags'     => $this->get_entry_information_replacement_tag(),
			'excluded_field_types' => $this->get_entry_information_excluded_field_types(),
		];

		return $strings;
	}

	/**
	 * Get the tags excluded in Entry Information select field.
	 *
	 * @since 1.7.7
	 *
	 * @return array
	 */
	private function get_entry_information_excluded_tags() {

		return [
			'date format="m/d/Y"' => '',
			'query_var key=""'    => '',
			'user_meta key=""'    => '',
		];
	}

	/**
	 * Get key and label for "All fields" string.
	 *
	 * @since 1.7.7
	 *
	 * @return array
	 */
	private function get_all_fields_string() {

		return [
			'all_fields' => esc_html__( 'All Fields', 'wpforms' ),
		];
	}

	/**
	 * Returns an array of tags to be replaced on Entry Information.
	 *
	 * @since 1.7.7
	 *
	 * @return array
	 */
	private function get_entry_information_replacement_tag() {

		return [
			'entry_date format="d/m/Y"' => 'entry_date',
		];
	}

	/**
	 * Returns an array containing field types that are excluded in Entry Information field.
	 *
	 * @since 1.7.7
	 *
	 * @return array
	 */
	private function get_entry_information_excluded_field_types() {

		return [
			'captcha',
			'divider',
			'entry-preview',
			'html',
			'content',
			'internal-information',
			'layout',
			'pagebreak',
		];
	}

	/**
	 * Entry CSV Attachment settings.
	 *
	 * @since 1.7.7
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
			'entry_csv_attachment_enable',
			$settings->form_data,
			esc_html__( 'Enable Entry CSV Attachment', 'wpforms' ),
			[
				'input_class' => 'notifications_enable_entry_csv_attachment_toggle',
				'parent'      => 'settings',
				'subsection'  => $id,
			],
			false
		);

		$content .= $this->entry_information_panel_field( $settings->form_data, $id );

		$content .= wpforms_panel_field(
			'text',
			'notifications',
			'entry_csv_attachment_file_name',
			$settings->form_data,
			esc_html__( 'File Name', 'wpforms' ),
			[
				'class'       => 'entry_csv_attachment_file_name_wrap',
				'default'     => self::DEFAULT_FILE_NAME,
				'input_class' => 'entry_csv_attachment_file_name',
				'parent'      => 'settings',
				'subsection'  => $id,
			],
			false
		);

		return $content;
	}

	/**
	 * Get Entry Information select output build on saved values.
	 *
	 * @since 1.7.7
	 *
	 * @param array $form_data       Form data.
	 * @param int   $notification_id Notification ID.
	 *
	 * @return string
	 */
	private function entry_information_panel_field( $form_data, $notification_id ) {

		$field  = 'entry_csv_attachment_entry_information';
		$values = Settings::get_array_value_from_field( $form_data, $notification_id, $field );

		// We use "+" instead of `array_merge()` preserve numeric keys.
		$options = $this->get_all_fields_string() +
			Settings::get_fields_from_form_data( $form_data, $values, $this->get_entry_information_excluded_field_types() ) +
			$this->get_entry_information_other_tags();

		return Settings::get_choicesjs_field(
			$notification_id,
			$field,
			$values,
			$options,
			__( 'Entry Information', 'wpforms' ),
			[
				'tooltip' => __( 'At least one item must be selected for inclusion in the CSV file.', 'wpforms' ),
			]
		);
	}

	/**
	 * Get the tags under "Other" group in Entry Information select field.
	 *
	 * @since 1.7.7
	 *
	 * @return array
	 */
	private function get_entry_information_other_tags() {

		$included_tags = array_diff_key(
			wpforms()->get( 'smart_tags' )->builder(),
			$this->get_entry_information_excluded_tags()
		);

		foreach ( $this->get_entry_information_replacement_tag() as $tag => $replace_tag ) {

			if ( ! isset( $included_tags[ $tag ] ) ) {
				continue;
			}

			$included_tags[ $replace_tag ] = $included_tags[ $tag ];

			unset( $included_tags[ $tag ] );
		}

		return $included_tags;
	}

	/**
	 * Format the Entry CSV Attachment > Entry Information data.
	 *
	 * @since 1.7.7
	 *
	 * @param array $form Form array which is usable with `wp_update_post()`.
	 * @param array $data Data retrieved from $_POST and processed.
	 * @param array $args Empty by default, may have custom data not intended to be saved, but used for processing.
	 *
	 * @return array
	 */
	public function format_data_on_save( $form, $data, $args ) {

		return Settings::attach_notification_data_in_form_data( $form, $this->get_entry_information( $data ) );
	}

	/**
	 * Returns an array of `entry_csv_attachment_entry_information` from $_POST.
	 *
	 * The array keys are the notification ID of the Entry CSV Attachment -> Entry Information.
	 *
	 * @since 1.7.7
	 *
	 * @param array $post_data Data retrieved from $_POST and processed.
	 *
	 * @return array
	 */
	private function get_entry_information( $post_data ) {

		if ( empty( $post_data['settings']['notifications'] ) ) {
			return [];
		}

		$new_entry_csv_attachments = [];

		foreach ( $post_data['settings']['notifications'] as $id => $notification ) {

			// Save a notification ID.
			$new_entry_csv_attachments[ $id ] = [];

			// Sanitize a File Name value.
			$new_entry_csv_attachments[ $id ]['entry_csv_attachment_file_name'] = substr( $this->get_file_name( $notification ), 0, self::FILE_NAME_MAX_LENGTH );

			if ( ! isset( $notification['entry_csv_attachment_entry_information']['hidden'] ) ) {
				continue;
			}

			// Sanitize a Entry Information items.
			$entry_information = [];
			$selected_fields   = json_decode( wp_unslash( $notification['entry_csv_attachment_entry_information']['hidden'] ) );

			if ( is_array( $selected_fields ) ) {
				$entry_information = array_map( [ $this, 'convert_numeric_to_int' ], $selected_fields );
			}

			$new_entry_csv_attachments[ $id ]['entry_csv_attachment_entry_information'] = $entry_information;
		}

		return $new_entry_csv_attachments;
	}

	/**
	 * Convert numeric value to int.
	 *
	 * @since 1.7.7
	 *
	 * @param mixed $val Value to convert.
	 *
	 * @return mixed
	 */
	private function convert_numeric_to_int( $val ) {

		if ( is_numeric( $val ) ) {
			return absint( $val );
		}

		return $val;
	}

	/**
	 * Add the Entry CSV Attachment in the email.
	 *
	 * @since 1.7.7
	 *
	 * @param array             $email     Email data to be used when sending email.
	 * @param WPForms_WP_Emails $email_obj WPForms_WP_Emails object in context.
	 *
	 * @return array
	 */
	public function attach_entry_csv_in_email( $email, $email_obj ) {

		if ( ! isset( $email_obj->form_data, $email_obj->notification_id, $email_obj->fields ) ) {
			return $email;
		}

		$form_data       = $email_obj->form_data;
		$notification_id = $email_obj->notification_id;
		$entry_fields    = $email_obj->fields;

		if (
			empty( $entry_fields ) ||
			empty( $form_data['settings']['notifications'][ $notification_id ]['entry_csv_attachment_enable'] )
		) {
			return $email;
		}

		$notification = $form_data['settings']['notifications'][ $notification_id ];

		if ( empty( $notification['entry_csv_attachment_entry_information'] ) ) {
			Settings::log_error(
				'Entry CSV Attachment',
				[
					'error'        => 'At least one item must be selected in the Entry Information dropdown.',
					'notification' => $notification,
				],
				$form_data['id'],
				$email_obj->entry_id
			);

			return $email;
		}

		$notification['id'] = $notification_id;

		try {
			$csv_attachment = $this->generate_csv(
				$notification,
				$form_data,
				$entry_fields,
				$email_obj->entry_id
			);
		} catch ( Exception $e ) {

			Settings::log_error(
				'Entry CSV Attachment',
				[
					'error'        => $e->getMessage(),
					'notification' => $notification,
				],
				$form_data['id'],
				$email_obj->entry_id
			);

			return $email;
		}

		if ( ! $csv_attachment ) {
			return $email;
		}

		$email['attachments'] = array_merge( (array) $email['attachments'], [ $csv_attachment ] );

		/**
		 * Fires after the CSV attachment was attached to the email.
		 *
		 * @since 1.7.7
		 *
		 * @param array $email Email data used on the email sent.
		 */
		do_action( 'wpforms_attach_entry_csv_in_email_complete', $email ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return $email;
	}

	/**
	 * Generate the Entry CSV Attachment.
	 *
	 * @since 1.7.7
	 *
	 * @param array $notification Notification data.
	 * @param array $form_data    Form data.
	 * @param array $entry_fields Entry data.
	 * @param int   $entry_id     Entry ID.
	 *
	 * @throws Exception When unable to create the CSV file.
	 *
	 * @return string Returns full path to the generated CSV.
	 */
	private function generate_csv( $notification, $form_data, $entry_fields, $entry_id ) {

		$content = $this->get_csv_header_body_content(
			$notification['entry_csv_attachment_entry_information'],
			$form_data,
			$entry_fields,
			$entry_id
		);

		/**
		 * Give devs the ability to modify the content for the Entry CSV Attachment.
		 *
		 * @since 1.8.4
		 *
		 * @param array $content  Content.
		 * @param int   $entry_id Entry ID.
		 */
		$content = apply_filters( 'wpforms_pro_admin_builder_notifications_advanced_entry_csv_attachment_content', $content, $entry_id );

		$csv_content = [ $content['header'], $content['body'] ];
		$file_name   = $this->get_file_name( $notification );

		/**
		 * Give devs an ability to modify the file name for the Entry CSV Attachment.
		 *
		 * @since 1.7.7
		 *
		 * @param string $file_name    File name.
		 * @param array  $notification Notification data.
		 * @param array  $form_data    Form data.
		 * @param array  $entry_fields Entry data.
		 * @param int    $entry_id     Entry ID.
		 */
		$filename = apply_filters( 'wpforms_entry_csv_attachment_filename', $file_name, $notification, $form_data, $entry_fields, $entry_id ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		return $this->create_csv( $csv_content, $filename );
	}

	/**
	 * Returns an array containing the values to be inserted in CSV.
	 *
	 * @since 1.7.7
	 *
	 * @param array $attachment_fields Array containing the field ID or smart tag to be added in CSV.
	 * @param array $form_data         Form data.
	 * @param array $entry_fields      Entry data.
	 * @param int   $entry_id          Entry ID.
	 *
	 * @return array
	 */
	private function get_csv_header_body_content(
		$attachment_fields,
		$form_data,
		$entry_fields,
		$entry_id
	) {

		/**
		 * Filter whether to include hidden entry field.
		 *
		 * @since 1.7.7
		 *
		 * @param bool $include_hidden Whether to include hidden entry field. Default `false`.
		 */
		$include_hidden = apply_filters( 'wpforms_entry_csv_attachment_include_hidden', false ); // phpcs:ignore WPForms.PHP.ValidateHooks.InvalidHookName

		$output = [
			'header' => [],
			'body'   => [],
		];

		$filtered = $this->handle_all_fields( $attachment_fields, $form_data['fields'] );

		// Allowed Smart Tags.
		$allowed_smart_tags = $this->get_entry_information_other_tags();

		// Loop through each of the fields to include in CSV.
		foreach ( $filtered as $field ) {

			if ( ! empty( $entry_fields[ $field ] ) ) {
				$entry_field = $entry_fields[ $field ];

				if ( ! $include_hidden && $this->is_field_hidden( $entry_field ) ) {
					continue;
				}

				$output['header'][] = $this->csv->escape_value( $entry_field['name'] );
				$output['body'][]   = $this->csv->escape_value( $entry_field['value'] );

				continue;
			}

			if ( empty( $allowed_smart_tags[ $field ] ) ) {
				continue;
			}

			$output['header'][] = $this->csv->escape_value( $allowed_smart_tags[ $field ] );
			$output['body'][]   = $this->csv->escape_value(
				wpforms_process_smart_tags( '{' . $field . '}', $form_data, $entry_fields, $entry_id )
			);
		}

		return $output;
	}

	/**
	 * This function handle the 'All Fields' on Entry Information.
	 *
	 * If there is NO 'All Fields', this function returns the passed `$attachment_fields`.
	 * Otherwise, it will return an array that was processed by the following steps:
	 * 1. Remove all other Field IDs in `$attachment_fields`.
	 * 2. It will get all the Field IDs in the form and add them after 'all_fields'.
	 *
	 * @since 1.7.7
	 *
	 * @param array $attachment_fields An array containing the possible data to be included in CSV file.
	 * @param array $form_data_fields  Array containing the Form Fields.
	 *
	 * @return array
	 */
	private function handle_all_fields( $attachment_fields, $form_data_fields ) {

		if ( ! in_array( 'all_fields', $attachment_fields, true ) ) {
			return $attachment_fields;
		}

		// Remove all other Field IDs.
		$filtered = array_filter( $attachment_fields, [ $this, 'not_is_numeric' ] );

		if ( empty( $form_data_fields ) ) {
			return $filtered;
		}

		// Add all the Form Field IDs after the 'all_fields'.
		return wpforms_array_insert(
			$filtered,
			array_keys( $form_data_fields ),
			array_search( 'all_fields', $filtered, true )
		);
	}

	/**
	 * Check whether a given value is not numeric.
	 *
	 * @since 1.7.7
	 *
	 * @param mixed $value Value to check.
	 *
	 * @return bool
	 */
	private function not_is_numeric( $value ) {

		return ! is_numeric( $value );
	}

	/**
	 * Returns whether the entry field is hidden.
	 *
	 * @since 1.7.7
	 *
	 * @param array $field Array containing information about an entry field.
	 *
	 * @return bool
	 */
	private function is_field_hidden( $field ) {

		return isset( $field['visible'] ) && $field['visible'] === false;
	}

	/**
	 * Get the file name for the Entry CSV Attachment.
	 *
	 * @since 1.7.7
	 *
	 * @param array $notification Notification data.
	 *
	 * @return string
	 */
	private function get_file_name( $notification ) {

		if ( empty( $notification['entry_csv_attachment_file_name'] ) ) {
			return self::DEFAULT_FILE_NAME;
		}

		$file_name = sanitize_file_name( trim( $notification['entry_csv_attachment_file_name'] ) );

		if ( empty( $file_name ) ) {
			return self::DEFAULT_FILE_NAME;
		}

		$path_info = pathinfo( $file_name );

		if ( empty( $path_info['filename'] ) ) {
			return self::DEFAULT_FILE_NAME;
		}

		return $path_info['filename'];
	}

	/**
	 * Create Entry CSV Attachment.
	 *
	 * @since 1.7.7
	 *
	 * @param array  $csv_content Array containing the content.
	 * @param string $file_name   File name of the CSV to be created.
	 *
	 * @throws Exception When unable to create the CSV file.
	 *
	 * @return string Full path of the created CSV file.
	 */
	private function create_csv( $csv_content, $file_name ) {

		$csv_file = wp_normalize_path( $this->get_csv_dir_path() . '/' . "{$file_name}.csv" );

		// Open a stream.
		$fp = fopen( $csv_file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		if ( ! $fp ) {
			throw new Exception(
				sprintf(
					'Unable to create the CSV file: %s',
					str_replace( wpforms_upload_dir()['path'], '', $csv_file )
				)
			);
		}

		foreach ( $csv_content as $csv_fields ) {
			fputcsv( $fp, $csv_fields );
		}

		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		return $csv_file;
	}

	/**
	 * Create a new folder where the Entry CSV Attachment file will be generated.
	 *
	 * @since 1.7.7
	 *
	 * @throws Exception When unable to create a new folder for the Entry CSV Attachment.
	 *
	 * @return string Full path to the created directory.
	 */
	private function get_csv_dir_path() {

		$upload_dir = wpforms_upload_dir();

		if (
			! empty( $upload_dir['error'] )
		) {
			throw new Exception( 'WPForms uploads folder does not exists.' );
		}

		$parent_folder = trailingslashit( $upload_dir['path'] ) . self::FOLDER_NAME;
		$dir_path      = '';

		wpforms_create_index_html_file( $parent_folder );

		for ( $attempt_count = 0; $attempt_count < self::CREATE_FOLDER_MAX_ATTEMPT; $attempt_count++ ) {

			$random_folder_name = sanitize_file_name( wp_generate_password( self::RANDOM_FOLDER_NAME_LENGTH, false ) );
			$dir_path           = $parent_folder . '/' . $random_folder_name;

			if ( file_exists( $dir_path ) ) {
				continue;
			}

			if ( wp_mkdir_p( $dir_path ) ) {
				break;
			}
		}

		if ( ! file_exists( $dir_path ) ) {
			throw new Exception( 'Unable to create a folder for a CSV file.' );
		}

		return $dir_path;
	}

	/**
	 * Add Entry CSV Attachment-related tasks.
	 *
	 * @since 1.7.7
	 *
	 * @param array $tasks List of task classes.
	 *
	 * @return array
	 */
	public function add_tasks( $tasks ) {

		$tasks[] = EntryEmailCSVCleanupTask::class;

		return $tasks;
	}
}
