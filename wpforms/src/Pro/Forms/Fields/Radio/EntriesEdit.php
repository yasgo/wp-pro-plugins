<?php

namespace WPForms\Pro\Forms\Fields\Radio;

use WPForms\Pro\Forms\Fields\Base\EntriesEdit as EntriesEditBase;
use WPForms\Pro\Forms\Fields\Traits\ChoicesEntriesEdit as EntriesEditTrait;

/**
 * Editing Radio field entries.
 *
 * @since 1.6.5
 */
class EntriesEdit extends EntriesEditBase {

	use EntriesEditTrait;

	/**
	 * Constructor.
	 *
	 * @since 1.6.5
	 */
	public function __construct() {

		parent::__construct( 'radio' );
	}
}
