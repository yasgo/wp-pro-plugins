<?php

namespace WPForms\Pro\Admin\Builder\Notifications\Advanced;

use WPForms_Builder_Panel_Settings;

/**
 * Advanced Form Notifications.
 *
 * @since 1.7.7
 */
class Settings {

	/**
	 * Initialize class.
	 *
	 * @since 1.7.7
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.7.7
	 */
	private function hooks() {

		add_filter( 'wpforms_builder_strings', [ $this, 'javascript_strings' ], 10, 2 );
		add_action( 'wpforms_builder_enqueues', [ $this, 'builder_assets' ] );
		add_action( 'wpforms_form_settings_notifications_single_after', [ $this, 'content' ], 20, 2 );
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

		$strings['empty_label_alternative_text'] = __( 'Field #', 'wpforms' );

		return $strings;
	}

	/**
	 * Enqueue assets for the builder.
	 *
	 * @since 1.7.7
	 *
	 * @param string $view Current view.
	 */
	public function builder_assets( $view ) {

		$min = wpforms_get_min_suffix();

		// JavaScript.
		wp_enqueue_script(
			'wpforms-builder-notifications-advanced',
			WPFORMS_PLUGIN_URL . "assets/pro/js/admin/builder/notifications{$min}.js",
			[ 'jquery', 'conditionals', 'choicesjs' ],
			WPFORMS_VERSION,
			true
		);
	}

	/**
	 * Output Notification Advanced section.
	 *
	 * @since 1.7.7
	 *
	 * @param WPForms_Builder_Panel_Settings $settings Builder panel settings.
	 * @param int                            $id       Notification id.
	 *
	 * @return void
	 */
	public function content( $settings, $id ) {

		/**
		 * Filter the "Advanced" content.
		 *
		 * @since 1.7.7
		 *
		 * @param string                         $content  The content.
		 * @param WPForms_Builder_Panel_Settings $settings Builder panel settings.
		 * @param int                            $id       Notification id.
		 */
		$content = apply_filters( 'wpforms_pro_admin_builder_notifications_advanced_settings_content', '', $settings, $id );

		// Wrap advanced settings to the unfoldable group.
		wpforms_panel_fields_group(
			$content,
			[
				'borders'    => [ 'top' ],
				'class'      => 'wpforms-builder-notifications-advanced',
				'group'      => 'settings_notifications_advanced',
				'title'      => esc_html__( 'Advanced', 'wpforms' ),
				'unfoldable' => true,
			]
		);
	}

	/**
	 * Log Entry error.
	 *
	 * @since 1.7.7
	 *
	 * @param string $title    Title of the error.
	 * @param mixed  $data     Data to be logged.
	 * @param int    $form_id  Form ID.
	 * @param int    $entry_id Entry ID.
	 */
	public static function log_error( $title, $data, $form_id, $entry_id ) {

		wpforms_log(
			$title,
			$data,
			[
				'form_id' => $form_id,
				'parent'  => $entry_id,
				'type'    => [ 'entry', 'error' ],
			]
		);
	}

	/**
	 * Render a select field that is expected to be instantiated as ChoicesJS in the frontend.
	 *
	 * This function don't render `$options` as `<option>` but instead render the `$saved_value` as
	 * the `<option>` if its in `$options`.
	 *
	 * @since 1.7.8
	 *
	 * @param int    $notification_id Notification ID.
	 * @param string $field           Field name.
	 * @param array  $saved_value     Saved value of the field in the DB.
	 * @param array  $options         Options of the select field.
	 * @param string $label           Label of the Select field.
	 * @param array  $args            Other information.
	 *
	 * @return string
	 */
	public static function get_choicesjs_field( $notification_id, $field, $saved_value, $options, $label, $args ) {

		$field_identifier = sprintf(
			'wpforms-panel-field-notifications-%1$d-%2$s',
			$notification_id,
			$field
		);

		$output = sprintf(
			'<div id="%s-wrap" class="wpforms-panel-field">',
			$field_identifier
		);

		$tooltip = '';

		if ( ! empty( $args['tooltip'] ) ) {
			$tooltip = sprintf(
				'<i class="fa fa-question-circle-o wpforms-help-tooltip" title="%s"></i>',
				esc_attr( $args['tooltip'] )
			);
		}

		$output .= sprintf(
			'<label for="%1$s">%2$s%3$s</label>',
			$field_identifier,
			esc_html( $label ),
			$tooltip
		);

		$output .= sprintf(
			'<select id="%1$s" name="settings[notifications][%2$d][%3$s]" class="%3$s" multiple>',
			$field_identifier,
			$notification_id,
			$field
		);

		foreach ( $saved_value as $value ) {

			if ( ! isset( $options[ $value ] ) ) {
				continue;
			}

			$output .= sprintf(
				'<option value="%s" selected>%s</option>',
				esc_attr( $value ),
				esc_html( $options[ $value ] )
			);
		}

		$output .= '</select>';

		if ( ! empty( $args['after'] ) ) {
			$output .= $args['after'];
		}

		$output .= sprintf(
			'<input type="hidden" name="settings[notifications][%1$d][%2$s][hidden]" value="%3$s">',
			$notification_id,
			$field,
			esc_attr( wp_json_encode( $saved_value ) )
		);

		$output .= '</div>';

		return $output;
	}

	/**
	 * Return an array of field labels with its ID as the array key from
	 * form data.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form_data            Form data.
	 * @param array $field_ids_to_get     Field IDs to get.
	 * @param array $excluded_field_types Field types to exclude.
	 * @param array $included_field_types Field types to include.
	 *
	 * @return array
	 */
	public static function get_fields_from_form_data( $form_data, $field_ids_to_get, $excluded_field_types = [], $included_field_types = [] ) {

		if ( empty( $form_data['fields'] ) || empty( $field_ids_to_get ) ) {
			return [];
		}

		$fields_to_return = [];

		foreach ( $field_ids_to_get as $field_id ) {

			if (
				! isset( $form_data['fields'][ $field_id ] ) ||
				in_array( $form_data['fields'][ $field_id ]['type'], $excluded_field_types, true ) ||
				( ! empty( $included_field_types ) && ! in_array( $form_data['fields'][ $field_id ]['type'], $included_field_types, true ) )
			) {
				continue;
			}

			$fields_to_return[ $field_id ] = ! empty( $form_data['fields'][ $field_id ]['label'] )
				? esc_html( $form_data['fields'][ $field_id ]['label'] )
				: sprintf( /* translators: %d - field ID. */
					esc_html__( 'Field #%d', 'wpforms' ),
					absint( $field_id )
				);
		}

		return $fields_to_return;
	}

	/**
	 * Attach notifications data in form data.
	 *
	 * @since 1.7.8
	 *
	 * @param array $form              Form array which is usable with `wp_update_post()`.
	 * @param array $notification_data Array of notifications data to attach in form data.
	 *
	 * @return array
	 */
	public static function attach_notification_data_in_form_data( $form, $notification_data ) {

		if ( empty( $notification_data ) ) {
			return $form;
		}

		// Get a filtered form content.
		$form_data = json_decode( stripslashes( $form['post_content'] ), true );

		foreach ( $notification_data as $id => $notification ) {

			if ( empty( $form_data['settings']['notifications'][ $id ] ) ) {
				continue;
			}

			$form_data['settings']['notifications'][ $id ] = array_merge(
				$form_data['settings']['notifications'][ $id ],
				$notification
			);
		}

		// Save the modified version back to form.
		$form['post_content'] = wpforms_encode( $form_data );

		return $form;
	}

	/**
	 * Return the value from a field in array type.
	 *
	 * If the field is not present, then it will return an empty array.
	 *
	 * @since 1.7.8
	 *
	 * @param array  $form_data       Form data.
	 * @param int    $notification_id Notification ID.
	 * @param string $field           Field to fetch value.
	 *
	 * @return array
	 */
	public static function get_array_value_from_field( $form_data, $notification_id, $field ) {

		if ( empty( $form_data['settings']['notifications'][ $notification_id ][ $field ] ) ) {
			return [];
		}

		$value = $form_data['settings']['notifications'][ $notification_id ][ $field ];

		return is_array( $value ) ? $value : explode( ',', $value );
	}
}
