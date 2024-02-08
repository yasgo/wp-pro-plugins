/* global Choices, wpf, wpforms_builder, wpforms_builder_anti_spam_filters */

'use strict';

var WPForms = window.WPForms || {};

WPForms.Admin = WPForms.Admin || {};
WPForms.Admin.Builder = WPForms.Admin.Builder || {};

WPForms.Admin.Builder.Filters = WPForms.Admin.Builder.Filters || ( function( document, window, $ ) {

	/**
	 * Elements holder.
	 *
	 * @since 1.7.8
	 *
	 * @type {object}
	 */
	let el = {};

	/**
	 * Runtime variables.
	 *
	 * @since 1.7.8
	 *
	 * @type {object}
	 */
	const vars = {

		/**
		 * Keyword list for keyword filter.
		 *
		 * @since 1.7.8
		 */
		keywordList: null,
	};

	/**
	 * Public functions and properties.
	 *
	 * @since 1.7.8
	 *
	 * @type {object}
	 */
	const app = {

		/**
		 * Init Filters section.
		 *
		 * @since 1.7.8
		 */
		init: function() {

			$( app.ready );
		},

		/**
		 * DOM is fully loaded.
		 *
		 * @since 1.7.8
		 */
		ready: function() {

			app.setup();
			app.events();
			app.initCountryList();
			app.loadStates();
		},

		/**
		 * Setup. Prepare some variables.
		 *
		 * @since 1.7.8
		 */
		setup: function() {

			// Cache DOM elements.
			el = {
				$builder: $( '#wpforms-builder' ),
				$panelToggle: $( '.wpforms-panel-field-toggle-next-field' ),
				$keywordsList: $( '.wpforms-panel-field-keyword-keywords textarea' ),
				$keywordsListContainer: $( '.wpforms-panel-field-keyword-filter-keywords-container' ),
				$keywordsListActions: $( '.wpforms-panel-field-keyword-filter-actions' ),
				$keywordsListSaveButton: $( '.wpforms-settings-keyword-filter-save-changes' ),
				$keywordsListCancelButton: $( '.wpforms-settings-keyword-filter-cancel' ),
				$keywordsListReformatButton: $( '.wpforms-btn-keyword-filter-reformat' ),
				$keywordsListToggle: $( '.wpforms-settings-keyword-filter-toggle-list' ),
				$keywordsListCount: $( '.wpforms-panel-field-keyword-filter-keywords-count' ),
				$countryCodes: $( '#wpforms-panel-field-anti_spam-country_filter-country_codes' ),
				$countryCodesHidden: $( '.wpforms-panel-field-country-filter-country-codes-json' ),
			};
		},

		/**
		 * Bind events.
		 *
		 * @since 1.7.8
		 */
		events: function() {

			// Anti-Spam settings panel related actions.
			el.$builder
				.on( 'change', '.wpforms-panel-field-toggle-next-field', app.togglePanel )
				.on( 'click', '.wpforms-settings-keyword-filter-toggle-list', app.loadKeywords )
				.on( 'click', '.wpforms-settings-keyword-filter-toggle-list', app.toggleKeywordsList )
				.on( 'click', '.wpforms-settings-keyword-filter-save-changes', app.saveKeywords )
				.on( 'click', '.wpforms-settings-keyword-filter-cancel', app.cancelSavingKeywordList )
				.on( 'click', '.wpforms-btn-keyword-filter-reformat', app.reformatKeywords )
				.on( 'change keyup paste cut', '.wpforms-panel-field-keyword-keywords textarea', app.updateKeywordsCount )
				.on( 'paste keyup', '.wpforms-panel-field-keyword-keywords textarea', app.showReformatWarning )
				.on( 'change', '#wpforms-panel-field-anti_spam-country_filter-country_codes',  app.changeCountryCodes )
				.on( 'wpformsSaved', app.saveKeywords );
		},

		/**
		 * Load default element states.
		 *
		 * @since 1.7.8
		 */
		loadStates: function() {

			el.$panelToggle.trigger( 'change' );
		},

		/**
		 * Init country list dropdown.
		 *
		 * @since 1.7.8
		 */
		initCountryList: function() {

			// Skip in certain cases.
			if ( typeof window.Choices !== 'function' ) {
				return;
			}

			// Return if already initialized.
			if ( typeof el.$countryCodes.data( 'choicesjs' ) !== 'undefined' ||
				el.$countryCodes.length === 0 ) {
				return;
			}

			el.$countryCodes.data( 'choicesjs', new Choices( el.$countryCodes[0], {
				shouldSort: false,
				removeItemButton: true,
				fuseOptions:{
					'threshold':  0.1,
					'distance': 1000,
				},
				callbackOnInit: function() {

					wpf.initMultipleSelectWithSearch( this );
				},
			} ) );

			// Update hidden input value.
			app.changeCountryCodes( null );

			// Update form state when hidden input is updated.
			// This will prevent a please-save-prompt to appear without doing any changes anywhere.
			if ( wpf.initialSave === true ) {
				wpf.savedState = wpf.getFormState( '#wpforms-builder-form' );
			}
		},

		/**
		 * Push country choices values to JSON.
		 *
		 * @since 1.7.8
		 */
		changeCountryCodes: function() {

			if ( el.$countryCodes.length <= 0 ) {
				return;
			}

			el.$countryCodesHidden.val( JSON.stringify( el.$countryCodes.val() ) );
		},

		/**
		 * Switch a filter panel.
		 *
		 * @since 1.7.8
		 */
		togglePanel: function() {

			const $this = $( this );

			$this.closest( '.wpforms-panel-field' ).next( '.wpforms-panel-field' ).toggle( $this.is( ':checked' ) );
		},

		/**
		 * Load keywords on demand.
		 *
		 * @since 1.7.8
		 *
		 * @param {Event} e Event.
		 */
		loadKeywords: function( e ) {

			e.preventDefault();

			// Load keywords only once.
			if ( el.$keywordsList.val().length !== 0 || vars.keywordList !== null ) {
				return;
			}

			$.post(
				wpforms_builder.ajax_url,
				{
					nonce : wpforms_builder.nonce,
					action : 'wpforms_builder_load_keywords',
				},
				function( res ) {

					if ( res.success ) {
						vars.keywordList = res.data.keywords.join( '\r\n' );

						el.$keywordsList.val( vars.keywordList );
						app.updateKeywordsCount();
					}
				}
			);
		},

		/**
		 * Toggle keywords list.
		 *
		 * @since 1.7.8
		 */
		toggleKeywordsList: function() {

			const currentText = el.$keywordsListToggle.text();
			const toggleText = el.$keywordsListToggle.data( 'collapse' );

			el.$keywordsListToggle.text( toggleText ).data( 'collapse', currentText );

			el.$keywordsListContainer.toggle();

			app.removeReformatWarning();
		},

		/**
		 * Save keywords list.
		 *
		 * @since 1.7.8
		 *
		 * @param {Event} e Event.
		 */
		saveKeywords: function( e ) {

			e.preventDefault();

			if ( el.$keywordsListSaveButton.attr( 'disabled' ) &&
				el.$keywordsList.val().length === 0 ||
				vars.keywordList === null ) {
				return;
			}

			const $buttonText = el.$keywordsListSaveButton.find( '.wpforms-settings-keyword-filter-save-changes-text' );
			const buttonText = $buttonText.text();
			const $spinner = el.$keywordsListSaveButton.find( '.wpforms-loading-spinner' );
			const keywords = app.getKeywords().join( '\r\n' );

			el.$keywordsListSaveButton.attr( 'disabled', 'disabled' ).css( 'width', el.$keywordsListSaveButton.outerWidth() );

			$.post( {
				url: wpforms_builder.ajax_url,
				data: {
					keywords: keywords,
					nonce: wpforms_builder.nonce,
					action: 'wpforms_builder_save_keywords',
				},
				beforeSend: function() {

					$spinner.removeClass( 'wpforms-hidden' );
					$buttonText.text( wpforms_builder.saving );
				},
				success: function( res ) {

					if ( res.success ) {
						vars.keywordList = keywords;

						el.$keywordsList.val( keywords );
					}
				},
				complete: function() {

					setTimeout( function() {

						// Save form data after keywords list is updated.
						if ( e.type !== 'wpformsSaved' ) {
							$( '#wpforms-save' ).trigger( 'click' );
						}

						$spinner.addClass( 'wpforms-hidden' );
						$buttonText.text( buttonText );
						el.$keywordsListSaveButton.removeAttr( 'disabled' ).removeAttr( 'style' );
						el.$keywordsListActions.addClass( 'wpforms-hidden' );
						app.removeReformatWarning();
					}, 1000 );
				},
			} );
		},

		/**
		 * Cansel keywords saving.
		 *
		 * @since 1.7.8
		 *
		 * @param {Event} e Event.
		 */
		cancelSavingKeywordList: function( e ) {

			e.preventDefault();

			el.$keywordsList.val( vars.keywordList );
			app.updateKeywordsCount();
			el.$keywordsListActions.addClass( 'wpforms-hidden' );
			app.toggleKeywordsList();
		},

		/**
		 * Update keywords count.
		 *
		 * @since 1.7.8
		 */
		updateKeywordsCount: function() {

			// Force execution on next tick to catch `cut` event reliably.
			setTimeout( function() {
				el.$keywordsListCount.text( '' ).text( app.getKeywords().length );
				el.$keywordsListActions.removeClass( 'wpforms-hidden' );
			} );
		},

		/**
		 * Get keywords.
		 *
		 * @since 1.7.8
		 *
		 * @returns {Array} Keywords list.
		 */
		getKeywords: function() {

			/**
			 * Split string by new lines.
			 */
			let keywords = el.$keywordsList.val().split( /\r\n|\r|\n/ );

			keywords = keywords.map( function( keyword ) {

				return keyword.trim();
			} );

			keywords = keywords.filter( function( keyword ) {

				return keyword.length > 0;
			} );

			return keywords;
		},

		/**
		 * Show reformat warning if text contains more commas or semicolons than new lines.
		 *
		 * @since 1.7.8
		 *
		 * @param {Event} e Event.
		 */
		showReformatWarning: function( e ) {

			const warningTmpl = wp.template( 'wpforms-settings-anti-spam-keyword-filter-reformat-warning-template' );
			const text = typeof e.originalEvent.clipboardData === 'undefined' ? $( this ).val() : e.originalEvent.clipboardData.getData( 'text' );
			const countComma = ( text.match( /,/g ) || [] ).length;
			const countSemicolon = ( text.match( /;/g ) || [] ).length;

			/**
			 * Split string by new lines.
			 */
			const countNewLines = text.split( /\r\n|\r|\n/ ).length;

			if ( countComma >= countNewLines || countSemicolon >= countNewLines ) {
				app.removeReformatWarning();
				el.$keywordsListActions.prepend( warningTmpl() );
			} else {
				app.removeReformatWarning();
			}
		},

		/**
		 * Remove reformat warning.
		 *
		 * @since 1.7.8
		 */
		removeReformatWarning: function() {

			$( '.wpforms-alert-keyword-filter-reformat' ).remove();
		},

		/**
		 * Update warning message in case of successful reformatting.
		 *
		 * @since 1.7.8
		 */
		successReformatWarning: function() {

			$( '.wpforms-alert-keyword-filter-reformat .wpforms-alert-message p' ).text( wpforms_builder_anti_spam_filters.successfullReformatWarning );
			$( '.wpforms-alert-keyword-filter-reformat .wpforms-alert-buttons' ).hide();
		},

		/**
		 * Reformat keywords.
		 *
		 * @since 1.7.8
		 */
		reformatKeywords: function() {

			app.successReformatWarning();
			vars.keywordList = el.$keywordsList.val();
			el.$keywordsList.val( function() {

				/**
				 * Split the string on each occurrence of a space or semicolon.
				 * The plus + character points to a single match of comma or semicolon: E.g 'one    two,,,, three' => ['one', 'two', 'three']
				 */
				const cleanText = this.value.split( /[,;]+/ ).map( function( e ) {

					return e.trim();
				} );

				return cleanText.join( '\n' );
			} );
			app.updateKeywordsCount();
		},
	};

	// Provide access to public functions/properties.
	return app;

}( document, window, jQuery ) );

// Initialize.
WPForms.Admin.Builder.Filters.init();
