<?php

namespace WPForms\Pro\AntiSpam;

/**
 * Keyword Filter class.
 *
 * @since 1.7.8
 */
class KeywordFilter {

	/**
	 * Save option name.
	 *
	 * @since 1.7.8
	 */
	const OPTION_NAME = 'wpforms_keyword_filter_keywords';

	/**
	 * List of fields to allowed to search for keywords.
	 *
	 * @since 1.7.8
	 */
	const ALLOWED_FIELDS = [
		'text',
		'textarea',
		'name',
		'email',
		'address',
		'url',
		'richtext',
	];

	/**
	 * Init class.
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

		add_filter( 'wpforms_process_after_filter', [ $this, 'process' ], 10, 3 );
		add_action( 'wp_ajax_wpforms_builder_save_keywords',  [ $this, 'ajax_save_keywords' ] );
		add_action( 'wp_ajax_wpforms_builder_load_keywords',  [ $this, 'ajax_load_keywords' ] );
		add_action( 'wpforms_builder_print_footer_scripts', [ $this, 'reformat_warning_template' ] );
		add_action( 'wpforms_admin_builder_anti_spam_panel_content', [ $this, 'get_settings' ], 20, 1 );
	}

	/**
	 * Add content to the "Spam Protection and Security" panel in the Form Builder.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form_data Form data and settings.
	 */
	public function get_settings( $form_data ) {

		ob_start();

		wpforms_panel_field(
			'toggle',
			'anti_spam',
			'enable',
			$form_data,
			__( 'Enable keyword filter', 'wpforms' ),
			[
				'parent'      => 'settings',
				'subsection'  => 'keyword_filter',
				'tooltip'     => __( 'Block entries that contain one or more keywords you define.', 'wpforms' ),
				'input_class' => 'wpforms-panel-field-toggle-next-field',
			]
		);
		?>
		<div class="wpforms-panel-field wpforms-panel-field-keyword-filter-body">
			<p>
				<?php
				printf( /* translators: %s - number of phrases. */
					esc_html__( 'Your keyword filter contains %s phrase(s).', 'wpforms' ),
					'<strong class="wpforms-panel-field-keyword-filter-keywords-count">' . absint( $this->count_keywords() ) . '</strong>'
				);
				?>
				<a href="#" class="wpforms-settings-keyword-filter-toggle-list" data-collapse="<?php esc_html_e( 'Collapse keyword list.', 'wpforms' ); ?>">
					<?php esc_html_e( 'Edit keyword list.', 'wpforms' ); ?>
				</a>
			</p>

			<div class="wpforms-panel-field-keyword-filter-keywords-container">
				<div class="wpforms-panel-field wpforms-panel-field-keyword-keywords">
					<label><?php esc_html_e( 'Keyword Filter List', 'wpforms' ); ?> <i class="fa fa-question-circle-o wpforms-help-tooltip" title="<?php esc_html_e( 'Keywords that will be blocked if they are found in a form entry.', 'wpforms' ); ?>"></i></label>
					<textarea></textarea>
				</div>
				<p class="note"><?php esc_html_e( 'Each word or phrase should be on its own line.', 'wpforms' ); ?></p>

				<div class="wpforms-panel-field-keyword-filter-actions wpforms-hidden">
					<button class="wpforms-btn wpforms-btn-sm wpforms-btn-blue wpforms-settings-keyword-filter-save-changes">
						<span class="wpforms-loading-spinner wpforms-loading-white wpforms-loading-inline wpforms-hidden"></span>
						<span class="wpforms-settings-keyword-filter-save-changes-text"><?php esc_html_e( 'Save Changes', 'wpforms' ); ?></span>
					</button>
					<button class="wpforms-btn wpforms-btn-sm wpforms-btn-light-grey-blue-borders wpforms-settings-keyword-filter-cancel">
						<?php esc_html_e( 'Cancel', 'wpforms' ); ?>
					</button>
				</div>
			</div>

			<?php
			wpforms_panel_field(
				'text',
				'anti_spam',
				'message',
				$form_data,
				__( 'Keyword Filter Message', 'wpforms' ),
				[
					'parent'     => 'settings',
					'subsection' => 'keyword_filter',
					'tooltip'    => __( 'Displayed if a visitor tries to submit an entry that contains a blocked keyword.', 'wpforms' ),
					'default'    => $this->get_default_error_message( $form_data ),
					'class'      => 'wpforms-panel-field-keyword-filter-message',
				]
			);
			?>
		</div>
		<?php

		wpforms_panel_fields_group( ob_get_clean() );
	}

	/**
	 * Prevent form submitting if filter fails.
	 *
	 * @since 1.7.8
	 *
	 * @param array $fields    Fields data.
	 * @param array $entry     Submitted form entry.
	 * @param array $form_data Form data and settings.
	 *
	 * @return array
	 */
	public function process( $fields, $entry, $form_data ) {

		if ( ! $this->is_enabled( $form_data ) ) {
			return $fields;
		}

		if ( $this->is_blocked_submission( $fields ) ) {
			$form_id = ! empty( $form_data['id'] ) ? $form_data['id'] : 0;

			wpforms()->get( 'process' )->errors[ $form_id ]['footer'] = $this->get_error_message( $form_data );
		}

		return $fields;
	}

	/**
	 * Get keywords count.
	 *
	 * @since 1.7.8
	 *
	 * @return int
	 */
	private function count_keywords() {

		return count( $this->get_keywords() );
	}

	/**
	 * Clean the text before searching for keywords.
	 *
	 * @since 1.7.8
	 *
	 * @param array $fields Entry fields.
	 *
	 * @return string
	 */
	protected function get_submitted_content( $fields ) {

		$filtered_fields = $this->get_filtered_fields();

		foreach ( $fields as $key => $field ) {

			// We need to process additional data from address field for filter.
			if ( $field['type'] === 'address' ) {
				$fields[ $key ] = $this->prepare_address_field( $field );
			}

			if ( ! in_array( $field['type'], $filtered_fields, true ) ) {
				unset( $fields[ $key ] );
			}
		}

		return implode( ' ', wp_list_pluck( $fields, 'value' ) );
	}

	/**
	 * Include all available address into the filter.
	 *
	 * @since 1.7.8
	 *
	 * @param array $field Field data.
	 *
	 * @return array
	 */
	private function prepare_address_field( $field ) {

		$subfields = [ 'address1', 'address2', 'city', 'state', 'postal', 'country' ];
		$value     = '';

		foreach ( $subfields as $subfield ) {
			if ( ! empty( $field[ $subfield ] ) ) {
				$value .= $field[ $subfield ] . ' ';
			}
		}

		$field['value'] = $value;

		return $field;
	}

	/**
	 * Get fields available to filter through.
	 *
	 * @since 1.7.8
	 *
	 * @return array
	 */
	private function get_filtered_fields() {

		/**
		 * Modify the list of filterable fields.
		 *
		 * @since 1.7.8
		 *
		 * @param array $filterable_fields Array of fields.
		 */
		return apply_filters(
			'wpforms_pro_anti_spam_keyword_filter_get_filtered_fields',
			self::ALLOWED_FIELDS
		);
	}

	/**
	 * Format blocked phrases before search.
	 *
	 * @since 1.7.8
	 *
	 * @return array
	 */
	protected function get_keywords() {

		$keywords = (array) json_decode( get_option( self::OPTION_NAME, '' ), true );

		/**
		 * Filter keywords list.
		 *
		 * @since 1.7.8
		 *
		 * @param string $keywords Keywords List.
		 */
		return (array) apply_filters( 'wpforms_pro_anti_spam_keyword_filter_get_keywords', $keywords );
	}

	/**
	 * Check if a blocked keyword is set in the submission.
	 *
	 * @since 1.7.8
	 *
	 * @param array $fields List of submitted fields.
	 *
	 * @return bool
	 */
	protected function is_blocked_submission( $fields ) {

		$text     = $this->get_submitted_content( $fields );
		$keywords = $this->get_keywords();

		if ( empty( $keywords ) ) {
			return false;
		}

		foreach ( $keywords as $keyword ) {
			if ( $this->match_keyword( $text, $keyword ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if text contains keywords.
	 *
	 * @since 1.7.8
	 *
	 * @param string $text    Text.
	 * @param string $keyword Keyword.
	 *
	 * @return bool
	 */
	private function match_keyword( $text, $keyword ) {

		$keyword = preg_quote( trim( $keyword ), '/' );

		/**
		 * Look for a matching keyword (case-insensitive), but not as part of another word.
		 *
		 * First group:
		 * `?<=` - Look-behind assertion;
		 * `^` - keyword is at starting position, i.e. a separate word / phrase, or
		 * `\W` - any non-word character (anything except a-zA-Z0-9 and underscore), or
		 *
		 * Second group:
		 * `?=` - Look-ahead assertion;
		 * `\W` - any non-word character, or
		 * `$` - end position, i.e. end of a separate word / phrase.
		 */
		$regex = '/(?<=^|\W)' . $keyword . '(?=\W|$)/i';

		if ( preg_match( $regex, $text ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Is Keywords filter enabled in settings?
	 *
	 * @since 1.7.8
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return bool
	 */
	protected function is_enabled( $form_data ) {

		return ! empty( $form_data['settings']['anti_spam']['keyword_filter']['enable'] );
	}

	/**
	 * Get error message.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return string
	 */
	protected function get_error_message( $form_data ) {

		return ! empty( $form_data['settings']['anti_spam']['keyword_filter']['message'] ) ?
			$form_data['settings']['anti_spam']['keyword_filter']['message'] :
			$this->get_default_error_message( $form_data );
	}

	/**
	 * Return default error message.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form_data Form Data.
	 *
	 * @return string
	 */
	private function get_default_error_message( $form_data ) {

		/**
		 * Modify default error message.
		 *
		 * @since 1.7.8
		 *
		 * @param string $message   Request arguments.
		 * @param array  $form_data Form Data.
		 *
		 * @return string
		 */
		return apply_filters( 'wpforms_pro_anti_spam_keyword_filter_get_default_error_message', esc_html__( 'Sorry, your message can\'t be submitted because it contains prohibited words.', 'wpforms' ), $form_data );
	}

	/**
	 * Load keywords on demand.
	 *
	 * @since 1.7.8
	 */
	public function ajax_load_keywords() {

		check_ajax_referer( 'wpforms-builder', 'nonce' );

		// Check for permissions.
		if ( ! wpforms_current_user_can( 'edit_forms' ) ) {
			wp_send_json_error();
		}

		wp_send_json_success( [ 'keywords' => $this->get_keywords() ] );
	}

	/**
	 * Save keywords list into the option table.
	 *
	 * @since 1.7.8
	 */
	public function ajax_save_keywords() {

		check_ajax_referer( 'wpforms-builder', 'nonce' );

		// Check for permissions.
		if ( ! wpforms_current_user_can( 'edit_forms' ) ) {
			wp_send_json_error();
		}

		// Check for required items.
		if ( ! isset( $_POST['keywords'] ) ) {
			wp_send_json_error();
		}

		$keywords = array_filter(
			array_map(
				'trim',
				array_map(
					'sanitize_text_field',
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					explode( PHP_EOL, wp_unslash( $_POST['keywords'] ) )
				)
			)
		);

		update_option( self::OPTION_NAME, wp_json_encode( array_values( $keywords ) ), 'no' );

		wp_send_json_success();
	}

	/**
	 * Keywords warning message block.
	 *
	 * @since 1.7.8
	 */
	public function reformat_warning_template() {
		?>
		<script type="text/html" id="tmpl-wpforms-settings-anti-spam-keyword-filter-reformat-warning-template">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo wpforms_render( 'builder/antispam/reformat-warning' );
			?>
		</script>
		<?php
	}
}
