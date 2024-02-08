<?php

namespace WPForms\Pro\Admin\Entries\Table\DataObjects;

use WPForms\Admin\Base\Tables\DataObjects\ColumnBase;

/**
 * Column data object.
 *
 * @since 1.8.6
 */
class Column extends ColumnBase {

	/**
	 * Whether column presents one of the form fields.
	 *
	 * @since 1.8.6
	 *
	 * @var bool
	 */
	protected $is_form_field;

	/**
	 * List table column key slug.
	 *
	 * It is needed since a field column key has prefix `wpforms_field_`.
	 *
	 * @since 1.8.6
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Is column presenting entry meta.
	 *
	 * @since 1.8.6
	 *
	 * @return bool
	 */
	public function is_entry_meta(): bool {
		// phpcs:ignore WPForms.Formatting.EmptyLineBeforeReturn.RemoveEmptyLineBeforeReturnStatement
		return ! $this->is_form_field;
	}

	/**
	 * Is column presenting form field.
	 *
	 * @since 1.8.6
	 *
	 * @return bool
	 */
	public function is_form_field(): bool {
		// phpcs:ignore WPForms.Formatting.EmptyLineBeforeReturn.RemoveEmptyLineBeforeReturnStatement
		return $this->is_form_field;
	}

	/**
	 * Get list table column slug.
	 *
	 * @since 1.8.6
	 *
	 * @return string
	 */
	public function get_slug(): string {
		// phpcs:ignore WPForms.Formatting.EmptyLineBeforeReturn.RemoveEmptyLineBeforeReturnStatement
		return $this->slug;
	}
}
