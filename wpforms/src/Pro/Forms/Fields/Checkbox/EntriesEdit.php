<?php

namespace WPForms\Pro\Forms\Fields\Checkbox;

use WPForms\Pro\Forms\Fields\Base\EntriesEdit as EntriesEditBase;
use WPForms\Pro\Forms\Fields\Traits\ChoicesEntriesEdit as EntriesEditTrait;

/**
 * Editing Checkbox field entries.
 *
 * @since 1.6.0
 */
class EntriesEdit extends EntriesEditBase {

	use EntriesEditTrait;

	/**
	 * Constructor.
	 *
	 * @since 1.6.0
	 */
	public function __construct() {

		parent::__construct( 'checkbox' );
	}
}
