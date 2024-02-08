/* global flatpickr, wpforms_admin */

/**
 * Entries page.
 *
 * @since 1.6.3
 */

'use strict';

var WPFormsPagesEntries = window.WPFormsPagesEntries || ( function( document, window, $ ) {

	var app = {

		/**
		 * Start the engine.
		 *
		 * @since 1.6.3
		 */
		init: function() {

			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 1.6.3
		 */
		ready: function() {

			app.initFlatpickr();
			app.bindResetButtons();
			app.handleOnPrint();
		},

		/**
		 * Document ready.
		 *
		 * @since 1.6.3
		 */
		initFlatpickr: function() {

			var flatpickrLocale = {
					rangeSeparator: ' - ',
				},
				args = {
					altInput: true,
					altFormat: 'M j, Y',
					dateFormat: 'Y-m-d',
					mode: 'range',
					defaultDate: wpforms_admin.default_date,
				};

			if (
				flatpickr !== 'undefined' &&
				Object.prototype.hasOwnProperty.call( flatpickr, 'l10ns' ) &&
				Object.prototype.hasOwnProperty.call( flatpickr.l10ns, wpforms_admin.lang_code )
			) {
				flatpickrLocale = flatpickr.l10ns[ wpforms_admin.lang_code ];

				// Rewrite separator for all locales to make filtering work.
				flatpickrLocale.rangeSeparator = ' - ';
			}

			args.locale = flatpickrLocale;

			$( '.wpforms-filter-date-selector' ).flatpickr( args );
		},

		/**
		 * Reset input.
		 *
		 * @since 1.6.3
		 *
		 * @param {object} $input Input element.
		 */
		reset: function( $input ) {

			switch ( $input.prop( 'tagName' ).toLowerCase() ) {
				case 'input':
					$input.val( '' );
					break;
				case 'select':
					$input.val( $input.find( 'option' ).first().val() );
					break;
			}
		},

		/**
		 * Input is ignored for reset.
		 *
		 * @since 1.6.3
		 *
		 * @param {object} $input Input element.
		 *
		 * @returns {boolean} Is ignored.
		 */
		isIgnoredForReset: function( $input ) {

			return [ 'submit', 'hidden' ].indexOf( ( $input.attr( 'type' ) || '' ).toLowerCase() ) !== -1 &&
				! $input.hasClass( 'flatpickr-input' );
		},

		/**
		 * Bind reset buttons.
		 *
		 * @since 1.6.3
		 */
		bindResetButtons: function() {

			$( '#wpforms-reset-filter .reset' ).on( 'click', function() {

				var $form = $( this ).parents( 'form' );
				$form.find( $( this ).data( 'scope' ) ).find( 'input,select' ).each( function() {

					var $this = $( this );
					if ( app.isIgnoredForReset( $this ) ) {
						return;
					}
					app.reset( $this );
				} );

				// Submit the form
				$form.trigger( 'submit' );
			} );
		},

		/**
		 * Handle bulk print requests.
		 *
		 * @since 1.8.2
		 */
		handleOnPrint: function() {

			$( '#wpforms-entries-list' )
				.on( 'submit', 'form[target="_blank"]', function( e ) {

					e.preventDefault(); // don't submit multiple times.
					this.submit(); // use the native submit method of the form element.
					$( this ).removeAttr( 'target' ).trigger( 'reset' ); // blank the form fieldset.
				} )
				.on( 'change', '#bulk-action-selector-top', function() {

					const value = this.value; // what is the selected action.
					const $form = $( this ).closest( 'form' ); // look for the form element.

					if ( value === 'print' ) { // are we printing? then, let’s open the form in a new tab.
						$form.attr( 'target', '_blank' );

						return;
					}

					if ( $form.attr( 'target' ) ) {
						$form.removeAttr( 'target' ); // restore the original indication that where to display the response.
					}
				} );
		},
	};

	// Provide access to public functions/properties.
	return app;

}( document, window, jQuery ) );

// Initialize.
WPFormsPagesEntries.init();
