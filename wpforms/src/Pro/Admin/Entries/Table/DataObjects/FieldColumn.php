<?php

namespace WPForms\Pro\Admin\Entries\Table\DataObjects;

use InvalidArgumentException;

/**
 * Field column data object.
 *
 * @since 1.8.6
 */
class FieldColumn extends Column {

	/**
	 * Field column constructor.
	 *
	 * @since 1.8.6
	 *
	 * @param int|string $id       Column ID.
	 * @param array      $settings Column settings.
	 *
	 * @throws InvalidArgumentException If ID is not a positive integer.
	 */
	public function __construct( $id, array $settings ) {

		if ( ! is_int( $id ) || $id < 0 ) {
			throw new InvalidArgumentException( 'FieldColumn ID must be a positive integer.' );
		}

		parent::__construct( $id, $settings );

		$this->is_form_field = true;
		$this->slug          = 'wpforms_field_' . $this->id;
		$this->label         = ! empty( $this->label ) ?
			wp_strip_all_tags( $this->label ) :
			sprintf( /* translators: %d - field ID. */
				esc_html__( 'Field #%d', 'wpforms' ),
				$this->id
			);
	}
}
