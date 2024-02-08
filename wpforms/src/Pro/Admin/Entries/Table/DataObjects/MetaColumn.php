<?php

namespace WPForms\Pro\Admin\Entries\Table\DataObjects;

use InvalidArgumentException;

/**
 * Entry meta column data object.
 *
 * @since 1.8.6
 */
class MetaColumn extends Column {

	/**
	 * Meta column constructor.
	 *
	 * @since 1.8.6
	 *
	 * @param int|string $id       Column ID.
	 * @param array      $settings Column settings.
	 *
	 * @throws InvalidArgumentException If ID is not a string or a negative integer.
	 */
	public function __construct( $id, array $settings ) {

		if (
			! is_string( $id )
			&& ! ( is_int( $id ) && $id < 0 )
		) {
			throw new InvalidArgumentException( 'MetaColumn ID must be a string or a negative integer.' );
		}

		parent::__construct( $id, $settings );

		$this->is_form_field = false;
		$this->slug          = $this->type;
	}
}
