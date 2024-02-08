<?php

namespace WPForms\Pro\SmartTags;

use WPForms\SmartTags\SmartTag\Generic;

/**
 * Class SmartTags.
 *
 * @since 1.6.7
 */
class SmartTags extends \WPForms\SmartTags\SmartTags {

	/**
	 * Get list of registered smart tags.
	 *
	 * @since 1.6.7
	 *
	 * @return array
	 */
	protected function smart_tags_list() {

		return (array) wpforms_array_insert(
			parent::smart_tags_list(),
			[
				'entry_id'          => esc_html__( 'Entry ID', 'wpforms' ),
				'entry_date'        => esc_html__( 'Entry Date', 'wpforms' ),
				'entry_details_url' => esc_html__( 'Entry Details URL', 'wpforms' ),
			],
			'form_name'
		);
	}

	/**
	 * Get smart tag class.
	 *
	 * @since 1.6.7
	 *
	 * @param string $smart_tag_name Smart tag name.
	 *
	 * @return string
	 */
	protected function get_smart_tag_class_name( $smart_tag_name ) {

		if ( ! $this->has_smart_tag( $smart_tag_name ) ) {
			return Generic::class;
		}

		$parent_class = parent::get_smart_tag_class_name( $smart_tag_name );

		if ( $parent_class !== Generic::class ) {
			return $parent_class;
		}

		$class_name      = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $smart_tag_name ) ) );
		$full_class_name = '\\WPForms\\Pro\\SmartTags\\SmartTag\\' . $class_name;

		if ( class_exists( $full_class_name ) ) {
			return $full_class_name;
		}

		/**
		 * Modify a smart tag class name that describes the smart tag logic for the PRO version.
		 *
		 * @since 1.6.7
		 *
		 * @param string $class_name     The value.
		 * @param string $smart_tag_name Smart tag name.
		 */
		$full_class_name = apply_filters( 'wpforms_pro_smarttags_get_smart_tag_class_name', '', $smart_tag_name );

		return class_exists( $full_class_name ) ? $full_class_name : Generic::class;
	}

	/**
	 * Retrieve the builder's special tags.
	 *
	 * @since 1.6.7
	 *
	 * @return array
	 */
	protected function get_replacement_builder_tags() {

		return array_merge(
			parent::get_replacement_builder_tags(),
			[
				'entry_date' => 'entry_date format="d/m/Y"',
			]
		);
	}
}
