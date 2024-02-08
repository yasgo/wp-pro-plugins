<?php

namespace WPForms\Pro\Helpers;

/**
 * Upload files related helper methods.
 *
 * @since 1.7.0
 */
class Upload {

	/**
	 * Set correct file permissions in the file system.
	 *
	 * @since 1.7.0
	 *
	 * @param string $path File to set permissions for.
	 */
	public function set_file_fs_permissions( $path ) {

		$stat = stat( dirname( $path ) );

		@chmod( $path, $stat['mode'] & 0000666 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Process a file and do all the magic.
	 *
	 * @since 1.7.0
	 *
	 * @param array $file                Array of file data.
	 *
	 *      @type string $name     File name.
	 *      @type string $size     File size.
	 *      @type string $type     File type.
	 *      @type bool   $tmp_name Path to temporary directory.
	 *
	 * @param int   $field_id            Field ID.
	 * @param array $form_data           Form data and settings.
	 * @param bool  $is_media_integrated WordPress Media Library or WPForms directory.
	 *
	 * @return array  Array of file data.
	 */
	public function process_file( $file, $field_id, $form_data, $is_media_integrated ) {

		$file_name     = sanitize_file_name( $file['name'] );
		$file_ext      = pathinfo( $file_name, PATHINFO_EXTENSION );
		$file_base     = $this->get_file_basename( $file_name, $file_ext );
		$file_name_new = sprintf( '%s-%s.%s', $file_base, wp_hash( wp_rand() . microtime() . $form_data['id'] . $field_id ), strtolower( $file_ext ) );

		$file_details = [
			'file_name'     => $file_name,
			'file_name_new' => $file_name_new,
			'file_ext'      => $file_ext,
		];

		if ( $is_media_integrated ) {
			return $this->process_media_storage( $file_details, $file, $field_id, $form_data );
		}

		return $this->process_wpforms_storage( $file_details, $file, $form_data );
	}

	/**
	 * Process a file when WPForms storage is used.
	 *
	 * @since 1.7.0
	 *
	 * @param array $file_details Array of file detail data.
	 * @param array $file         File data.
	 * @param array $form_data    Form data and settings.
	 *
	 * @return array  Array of file data.
	 */
	private function process_wpforms_storage( $file_details, $file, $form_data ) {

		$form_id          = $form_data['id'];
		$upload_dir       = wpforms_upload_dir();
		$upload_path      = $upload_dir['path'];
		$form_directory   = $this->get_form_directory( $form_id, $form_data['created'] );
		$upload_path_form = $this->get_form_upload_path( $upload_path, $form_directory );
		$file_new         = trailingslashit( $upload_path_form ) . $file_details['file_name_new'];
		$file_url         = trailingslashit( $upload_dir['url'] ) . trailingslashit( $form_directory ) . $file_details['file_name_new'];

		wpforms_create_upload_dir_htaccess_file();
		wpforms_create_index_html_file( $upload_path );
		wpforms_create_index_html_file( $upload_path_form );

		$move_new_file = @rename( $file['tmp_name'], $file_new ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( $move_new_file === false ) {
			wpforms_log(
				'Upload Error, could not upload file',
				$file_url,
				[
					'type'    => [ 'entry', 'error' ],
					'form_id' => $form_id,
				]
			);

			return [];
		}

		$this->set_file_fs_permissions( $file_new );

		$file_details['attachment_id'] = '0';
		$file_details['upload_path']   = $upload_path_form;
		$file_details['file_url']      = $file_url;

		return $file_details;
	}

	/**
	 * Return the last sub folder where the file is stored.
	 *
	 * @since 1.7.6
	 *
	 * @param int    $form_id      Form ID.
	 * @param string $date_created Form entry creation date.
	 *
	 * @return string
	 */
	public function get_form_directory( $form_id, $date_created ) {

		return absint( $form_id ) . '-' . md5( $form_id . $date_created );
	}

	/**
	 * Process a file when WordPress Media Library is used.
	 *
	 * @since 1.7.0
	 *
	 * @param array $file_details Array of file detail data.
	 * @param array $file         File data.
	 * @param int   $field_id     Field ID.
	 * @param array $form_data    Form data and settings.
	 *
	 * @return array  Array of file data.
	 */
	private function process_media_storage( $file_details, $file, $field_id, $form_data ) {

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$file_args = [
			'error'    => '',
			'tmp_name' => $file['tmp_name'],
			'name'     => $file_details['file_name_new'],
			'type'     => $file['type'],
			'size'     => $file['size'],
		];

		$upload = wp_handle_sideload( $file_args, [ 'test_form' => false ] );

		if ( empty( $upload['file'] ) ) {
			return [];
		}

		$attachment_id = $this->insert_attachment( $file, $upload['file'], $form_data['fields'][ $field_id ] );

		if ( $attachment_id === 0 ) {
			return [];
		}

		$file_details['attachment_id'] = $attachment_id;
		$file_details['file_url']      = wp_get_attachment_url( $attachment_id );
		$file_details['file_name_new'] = wp_basename( $file_details['file_url'] );
		$file_details['upload_path']   = wp_normalize_path( trailingslashit( dirname( get_attached_file( $attachment_id ) ) ) );

		return $file_details;
	}

	/**
	 * Get form upload path.
	 *
	 * @since 1.7.0
	 *
	 * @param string $upload_path    Upload path.
	 * @param string $form_directory Form directory.
	 *
	 * @return string
	 */
	private function get_form_upload_path( $upload_path, $form_directory ) {

		$upload_path_form = wp_normalize_path( trailingslashit( $upload_path ) . $form_directory );

		if ( ! file_exists( $upload_path_form ) ) {
			wp_mkdir_p( $upload_path_form );
		}

		return $upload_path_form;
	}

	/**
	 * Helper to set data, insert an attachment, and update it.
	 *
	 * @since 1.7.0
	 *
	 * @param array  $file        File data.
	 * @param string $upload_file Data from the side loaded file.
	 * @param array  $field_data  Field data.
	 *
	 * @return int 0 if failed, else attachment ID.
	 */
	private function insert_attachment( $file, $upload_file, $field_data ) {

		$attachment_id = wp_insert_attachment(
			[
				'post_title'     => $this->get_wp_media_file_title( $file, $field_data ),
				'post_name'      => isset( $file['name'] ) ? $file['name'] : '',
				'post_content'   => $this->get_wp_media_file_desc( $file, $field_data ),
				'post_status'    => 'publish',
				'post_mime_type' => $file['type'],
			],
			$upload_file
		);

		if ( empty( $attachment_id ) || is_wp_error( $attachment_id ) ) {

			wpforms_log(
				"Upload Error, attachment wasn't created",
				$file['name'],
				[
					'type' => [ 'error' ],
				]
			);

			return 0;
		}

		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $upload_file )
		);

		return $attachment_id;
	}

	/**
	 * Get file basename.
	 *
	 * Ensure the file name length does not exceed 64 characters to prevent `make_clickable()`
	 * function from generating an incorrect URL.
	 *
	 * @since 1.7.0
	 *
	 * @param string $file_name File name.
	 * @param string $file_ext  File extension.
	 *
	 * @return string
	 */
	private function get_file_basename( $file_name, $file_ext ) {

		return mb_substr( wp_basename( $file_name, '.' . $file_ext ), 0, 64, 'UTF-8' );
	}

	/**
	 * Generate an attachment Title of a file uploaded to WordPress Media Library.
	 *
	 * @since 1.7.0
	 * @since 1.8.0 Added `wpforms_pro_helpers_upload_get_wp_media_file_title`
	 *                  and `wpforms_pro_helpers_upload_get_wp_media_file_title_{$field_type}` filters.
	 *
	 * @param array $file       File data.
	 * @param array $field_data Field data.
	 *
	 * @return string
	 */
	private function get_wp_media_file_title( $file, $field_data ) {

		$mime_type  = $file['type'];
		$field_type = $field_data['type'];
		$title      = sprintf(
			'%s: %s',
			isset( $field_data['label'] ) ? $field_data['label'] : '',
			sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) )
		);

		/**
		 * Allow filtering attachment Title of a file uploaded to WordPress Media Library.
		 *
		 * @since 1.8.0
		 *
		 * @param string $desc       Field label text.
		 * @param array  $file       File data.
		 * @param array  $field_data Field data.
		 */
		$title = apply_filters(
			'wpforms_pro_helpers_upload_get_wp_media_file_title',
			$title,
			$file,
			$field_data
		);

		/**
		 * Allow filtering attachment Title of a file uploaded to WordPress Media Library.
		 *
		 * @since 1.6.1
		 * @deprecated 1.8.0
		 *
		 * @param string $desc       Field label text.
		 * @param array  $file       File data.
		 * @param array  $field_data Field data.
		 */
		$title = apply_filters_deprecated(
			"wpforms_field_{$mime_type}_media_file_title",
			[ $title, $file, $field_data ],
			'1.8.0 of the WPForms plugin',
			'wpforms_pro_helpers_upload_get_wp_media_file_title'
		);

		/**
		 * Allow filtering attachment Title of a file uploaded to WordPress Media Library through specific field type.
		 *
		 * @since 1.6.1
		 *
		 * @param string $desc       Field label text.
		 * @param array  $file       File data.
		 * @param array  $field_data Field data.
		 */
		$title = apply_filters(
			"wpforms_pro_helpers_upload_get_wp_media_file_title_{$field_type}",
			$title,
			$file,
			$field_data
		);

		return wpforms_sanitize_text_deeply( $title );
	}

	/**
	 * Generate an attachment Description of a file uploaded to WordPress Media Library.
	 *
	 * @since 1.7.0
	 * @since 1.8.0 Added `wpforms_pro_helpers_upload_get_wp_media_file_desc`
	 *                  and `wpforms_pro_helpers_upload_get_wp_media_file_desc_{$field_type}` filters.
	 *
	 * @param array $file       File data.
	 * @param array $field_data Field data.
	 *
	 * @return string
	 */
	private function get_wp_media_file_desc( $file, $field_data ) {

		$mime_type  = $file['type'];
		$field_type = $field_data['type'];

		/**
		 * Allow filtering attachment Description of a file uploaded to WordPress Media Library.
		 *
		 * @since 1.8.0
		 *
		 * @param string $desc       Description text.
		 * @param array  $file       File data.
		 * @param array  $field_data Field data.
		 */
		$desc = apply_filters(
			'wpforms_pro_helpers_upload_get_wp_media_file_desc',
			isset( $field_data['description'] ) ? $field_data['description'] : '',
			$file,
			$field_data
		);

		/**
		 * Allow filtering attachment Description of a file uploaded to WordPress Media Library.
		 *
		 * @since 1.6.1
		 * @deprecated 1.8.0
		 *
		 * @param string $desc       Description text.
		 * @param array  $file       File data.
		 * @param array  $field_data Field data.
		 */
		$desc = apply_filters_deprecated(
			"wpforms_field_{$mime_type}_media_file_desc",
			[ $desc, $file, $field_data ],
			'1.8.0 of the WPForms plugin',
			'wpforms_pro_helpers_upload_get_wp_media_file_desc'
		);

		/**
		 * Allow filtering attachment Description of a file uploaded to WordPress Media Library through specific field type.
		 *
		 * @since 1.8.0
		 *
		 * @param string $desc       Description text.
		 * @param array  $file       File data.
		 * @param array  $field_data Field data.
		 */
		$desc = apply_filters(
			"wpforms_pro_helpers_upload_get_wp_media_file_desc_{$field_type}",
			$desc,
			$file,
			$field_data
		);

		return wp_kses_post_deep( $desc );
	}
}
