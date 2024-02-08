<?php

namespace WPForms\Pro\Forms\Fields\Select;

use WPForms\Pro\Forms\Fields\Base\EntriesEdit as EntriesEditBase;
use WPForms\Pro\Forms\Fields\Traits\ChoicesEntriesEdit as EntriesEditTrait;

/**
 * Editing Select field entries.
 *
 * @since 1.6.1
 */
class EntriesEdit extends EntriesEditBase {

	use EntriesEditTrait;

	/**
	 * Constructor.
	 *
	 * @since 1.6.1
	 */
	public function __construct() {

		parent::__construct( 'select' );
	}
}
