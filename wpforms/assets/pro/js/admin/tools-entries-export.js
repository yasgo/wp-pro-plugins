/* global wpforms_tools_entries_export, ajaxurl, Choices */
/**
 * WPForms Entries Export function.
 *
 * @since 1.5.5
 */

const WPFormsEntriesExport = window.WPFormsEntriesExport || ( function( document, window, $ ) {
	/**
	 * Elements.
	 *
	 * @since 1.5.5
	 *
	 * @type {Object}
	 */
	const el = {
		$form 			         : $( '#wpforms-tools-entries-export' ),
		$selectForm              : $( '#wpforms-tools-entries-export-selectform' ),
		$selectFormSpinner       : $( '#wpforms-tools-entries-export-selectform-spinner' ),
		$selectFormMsg           : $( '#wpforms-tools-entries-export-selectform-msg' ),
		$expOptions              : $( '#wpforms-tools-entries-export-options' ),
		$fieldsCheckboxes        : $( '#wpforms-tools-entries-export-options-fields-checkboxes' ),
		$paymentFieldsSection    : $( '#wpforms-tools-entries-export-options-payment-fields' ),
		$paymentFieldsCheckboxes : $( '#wpforms-tools-entries-export-options-payment-fields-checkboxes' ),
		$dateSection             : $( '#wpforms-tools-entries-export-options-date' ),
		$dateFlatpickr           : $( '#wpforms-tools-entries-export-options-date-flatpickr' ),
		$searchSection           : $( '#wpforms-tools-entries-export-options-search' ),
		$searchField             : $( '#wpforms-tools-entries-export-options-search-field' ),
		$submitButton            : $( '#wpforms-tools-entries-export-submit' ),
		$cancelButton            : $( '#wpforms-tools-entries-export-cancel' ),
		$processMsg              : $( '#wpforms-tools-entries-export-process-msg' ),
		$optionFields            : $( '#wpforms-tools-entries-export-options-type-info' ),
		$selectStatuses          : $( '#wpforms-tools-entries-export-select-statuses' ),
		$optionStatuses          : $( '#wpforms-tools-entries-export-options-status' ),
		$clearDateButton         : $( '.wpforms-clear-datetime-field' ),
	};

	/**
	 * Shorthand to translated strings.
	 *
	 * @since 1.5.5
	 *
	 * @type {Object}
	 */
	const i18n = wpforms_tools_entries_export.i18n;

	/**
	 * Runtime variables.
	 *
	 * @since 1.5.5
	 *
	 * @type {Object}
	 */
	const vars = {};

	/**
	 * Public functions and properties.
	 *
	 * @since 1.5.5
	 *
	 * @type {Object}
	 */
	const app = {

		/**
		 * Forms data cached.
		 *
		 * @since 1.5.5
		 *
		 * @type {Object}
		 */
		formsCache: {},

		/**
		 * Start the engine.
		 *
		 * @since 1.5.5
		 */
		init() {
			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 1.5.5
		 */
		ready() {
			vars.processing = false;

			app.initChoices();
			app.initDateRange();
			app.initFormContainer();
			app.initSubmit();
			app.events();
		},

		/**
		 * Register JS events.
		 *
		 * @since 1.5.5
		 */
		events() {
			// Selecting form.
			el.$selectForm[ 0 ].addEventListener( 'choice', function( event ) {
				app.selectFormEvent( event );
			} );

			// Toggle all checkboxes on or off.
			$( document ).on( 'change', '#wpforms-tools-entries-export-options .wpforms-toggle-all', function() {
				const $this = $( this ),
					$toggle = $this.find( 'input' ),
					$options = $this.siblings().find( 'input' );

				$options.prop( 'checked', $toggle.prop( 'checked' ) );
			} );

			// Update toggle all state when changing individual checkbox.
			$( document ).on( 'change', '#wpforms-tools-entries-export-options-fields-checkboxes label, #wpforms-tools-entries-export-options-payment-fields-checkboxes label, #wpforms-tools-entries-export-options-additional-info label', function() {
				const $this = $( this );

				if ( $this.hasClass( 'wpforms-toggle-all' ) ) {
					return;
				}

				const $options = $this.parent().find( 'label' ).not( '.wpforms-toggle-all' ).find( 'input' );
				const $checked = $options.filter( ':checked' );
				const $toggle = $this.siblings( '.wpforms-toggle-all' ).find( 'input' );

				$toggle.prop( 'checked', $checked.length === $options.length );
			} );

			// Display file download error.
			$( document ).on( 'csv_file_error', function( e, msg ) {
				app.displaySubmitMessage( msg, 'error' );
			} );

			// Display dynamic columns notice.
			$( document ).on( 'change', '#wpforms-tools-entries-export-options-type-info input', function() {
				app.switchDynamicColumnsNotice( $( this ) );
			} );

			// Clear date field.
			$( document ).on( 'click', '.wpforms-clear-datetime-field', function( e ) {
				e.preventDefault();
				el.$dateFlatpickr.flatpickr().clear();
				$( this ).addClass( 'wpforms-hidden' );

				// Reinitialization date range input to correct work after clear.
				app.initDateRange();
			} );
		},

		/**
		 * Select form event.
		 *
		 * @since 1.8.5
		 *
		 * @param {Object} event Event.
		 */
		selectFormEvent( event ) {
			if ( event.detail.choice.placeholder ) {
				el.$expOptions.addClass( 'hidden' );
				return;
			}
			if ( vars.formID === event.detail.choice.value ) {
				return;
			}

			vars.formID = event.detail.choice.value;

			app.resetChoices();
			el.$optionStatuses.removeClass( 'wpforms-hidden' );

			if ( 'undefined' === typeof app.formsCache[ vars.formID ] ) {
				app.retrieveFormAndRenderFields();
			} else {
				if ( app.formsCache[ vars.formID ].statuses.length > 1 ) {
					app.setChoices( app.formsCache[ vars.formID ].statuses );
				} else {
					el.$optionStatuses.addClass( 'wpforms-hidden' );
				}

				// Render cached form fields checkboxes.
				app.renderFields( app.formsCache[ vars.formID ].fields );

				// Render cached payment fields checkboxes.
				app.renderFields( app.formsCache[ vars.formID ].paymentFields, true );

				app.handleSearchFields( app.formsCache[ vars.formID ].fields, app.formsCache[ vars.formID ].paymentFields );

				app.optionsFields( app.formsCache[ vars.formID ].dynamicColumns );
				app.addDynamicColumnsNotice( app.formsCache[ vars.formID ].dynamicColumnsNotice );
			}
		},

		/**
		 * Switch dynamic columns notice.
		 *
		 * @since 1.8.5
		 *
		 * @param {Object} input Input.
		 */
		switchDynamicColumnsNotice( input ) {
			const inputValue = input.val();

			if ( inputValue === 'dynamic_columns' ) {
				const $notice = input.parent().find( '.wpforms-tools-entries-export-notice-warning' );
				$notice.toggleClass( 'wpforms-hide' );
			}
		},

		/**
		 * Retrieve the form fields and render fields checkboxes.
		 *
		 * @since 1.5.5
		 */
		retrieveFormAndRenderFields() {
			vars.ajaxData = {
				action: 'wpforms_tools_entries_export_form_data',
				nonce:  wpforms_tools_entries_export.nonce,
				form:   vars.formID,
			};
			el.$selectFormSpinner.removeClass( 'hidden' );
			app.displayFormsMessage( '' );
			$.get( ajaxurl, vars.ajaxData )
				.done( function( res ) {
					if ( res.success ) {
						// Render form fields checkboxes.
						app.renderFields( res.data.fields );

						// Render payment fields checkboxes.
						app.renderFields( res.data.payment_fields, true );

						app.optionsFields( res.data.dynamic_columns );

						if ( res.data.dynamic_columns ) {
							app.addDynamicColumnsNotice( res.data.dynamic_columns_notice );
						}

						app.handleSearchFields( res.data.fields, res.data.payment_fields );

						app.formsCache[ vars.formID ] = {
							fields: res.data.fields,
							paymentFields: res.data.payment_fields,
							dynamicColumns: res.data.dynamic_columns,
							dynamicColumnsNotice: res.data.dynamic_columns_notice,
							statuses: res.data.statuses,
						};
						el.$expOptions.removeClass( 'hidden' );

						if ( res.data.statuses.length > 1 ) {
							app.setChoices( res.data.statuses );
						} else {
							el.$optionStatuses.addClass( 'wpforms-hidden' );
						}
					} else {
						app.displayFormsMessage( res.data.error );
						el.$expOptions.addClass( 'hidden' );
					}
				} )
				.fail( function( jqXHR, textStatus, errorThrown ) {
					app.displayFormsMessage( i18n.error_prefix + '<br>' + errorThrown );
					el.$expOptions.addClass( 'hidden' );
				} )
				.always( function() {
					el.$selectFormSpinner.addClass( 'hidden' );
				} );
		},

		/**
		 * Export step ajax request.
		 *
		 * @since 1.5.5
		 *
		 * @param {string} requestId Request Identifier.
		 */
		exportAjaxStep( requestId ) {
			if ( ! vars.processing ) {
				return;
			}

			const ajaxData = app.getAjaxPostData( requestId );
			$.post( ajaxurl, ajaxData )
				.done( function( res ) {
					let msg = '';
					clearTimeout( vars.timerId );
					if ( ! res.success ) {
						app.displaySubmitMessage( res.data.error, 'error' );
						return;
					}
					if ( res.data.count === 0 ) {
						app.displaySubmitMessage( i18n.prc_2_no_entries );
						return;
					}
					msg = i18n.prc_3_done;
					msg += '<br>' + i18n.prc_3_download + ', <a href="#" class="wpforms-download-link">' + i18n.prc_3_click_here + '</a>.';
					app.displaySubmitMessage( msg, 'info' );
					app.triggerDownload( res.data.request_id );
				} )
				.fail( function( jqXHR, textStatus, errorThrown ) {
					clearTimeout( vars.timerId );
					app.displaySubmitMessage( i18n.error_prefix + '<br>' + errorThrown, 'error' );
				} )
				.always( function() {
					app.displaySubmitSpinner( false );
				} );
		},

		/**
		 * Get export step ajax POST data.
		 *
		 * @since 1.5.5
		 *
		 * @param {string} requestId Request Identifier.
		 *
		 * @return {Object} Ajax POST data.
		 */
		getAjaxPostData( requestId ) {
			let ajaxData;

			if ( requestId === 'first-step' ) {
				const statuses = [];
				ajaxData = el.$form.serializeArray().reduce( function( obj, item ) {
					if ( item.name === 'statuses' ) {
						statuses.push( item.value );
					} else {
						obj[ item.name ] = item.value;
					}
					return obj;
				}, {} );
				if ( el.$fieldsCheckboxes.find( 'input' ).length < 1 ) {
					ajaxData.date = '';
					ajaxData[ 'search[term]' ] = '';
				}

				ajaxData.statuses = statuses;
			} else {
				ajaxData = {
					action:     'wpforms_tools_entries_export_step',
					nonce:      wpforms_tools_entries_export.nonce,
					// eslint-disable-next-line camelcase
					request_id: requestId,
				};
			}

			return ajaxData;
		},

		/**
		 * Submit button click.
		 *
		 * @since 1.5.5
		 */
		initSubmit() {
			el.$submitButton.on( 'click', function( e ) {
				e.preventDefault();

				const $t = $( this );

				if ( $t.hasClass( 'wpforms-btn-spinner-on' ) ) {
					return;
				}

				el.$submitButton.blur();
				app.displaySubmitSpinner( true );
				app.displaySubmitMessage( '' );

				vars.timerId = setTimeout(
					function() {
						app.displaySubmitMessage( i18n.prc_1_filtering + '<br>' + i18n.prc_1_please_wait, 'info' );
					},
					3000
				);

				app.exportAjaxStep( 'first-step' );
			} );

			el.$cancelButton.on( 'click', function( e ) {
				e.preventDefault();
				el.$cancelButton.blur();
				app.displaySubmitMessage( '' );
				app.displaySubmitSpinner( false );
			} );
		},

		/**
		 * Init Form container.
		 *
		 * @since 1.5.5
		 */
		initFormContainer() {
			if ( wpforms_tools_entries_export.form_id > 0 ) {
				el.$expOptions.removeClass( 'hidden' );

				if ( el.$fieldsCheckboxes.find( 'input' ).length < 1 ) {
					el.$dateSection.addClass( 'hidden' );
					el.$searchSection.addClass( 'hidden' );
				}
			}
		},

		/**
		 * Init Choices.
		 *
		 * @since 1.8.5
		 */
		initChoices() {
			vars.Choices = new Choices( el.$selectStatuses[ 0 ], {
				removeItemButton: true,
				itemSelectText: '',
			} );
		},

		/**
		 * Reset Choices.
		 *
		 * @since 1.8.5
		 */
		resetChoices() {
			vars.Choices.clearInput();
			vars.Choices.clearStore();
			vars.Choices.setChoices( [], 'value', 'label', true );
		},

		/**
		 * Set Choices.
		 * Exclude choice with value 'spam'.
		 *
		 * @since 1.8.5
		 *
		 * @param {Array} choices Choices.
		 */
		setChoices( choices ) {
			// Try to get 'spam' choice.
			const spamChoice = choices.filter( function( choice ) {
				return choice.value === 'spam';
			} )[ 0 ];

			// Set 'spam' choice.
			if ( spamChoice ) {
				vars.Choices.setChoices( [ spamChoice ], 'value', 'label', true );
			}

			// Exclude choice with value 'spam'.
			choices = choices.filter( function( choice ) {
				return choice.value !== 'spam';
			} );

			// Select all choices.
			vars.Choices.setValue( choices, 'value', 'label', true );
		},

		/**
		 * Init Flatpickr at Date Range field.
		 *
		 * @since 1.5.5
		 */
		initDateRange() {
			const langCode = wpforms_tools_entries_export.lang_code;
			const flatpickr = window.flatpickr;
			let flatpickrLocale = {
				rangeSeparator: ' - ',
			};

			if (
				flatpickr !== 'undefined' &&
				flatpickr.hasOwnProperty( 'l10ns' ) &&
				flatpickr.l10ns.hasOwnProperty( langCode )
			) {
				flatpickrLocale = flatpickr.l10ns[ langCode ];
				flatpickrLocale.rangeSeparator = ' - ';
			}

			el.$dateFlatpickr.flatpickr( {
				altInput: true,
				altFormat: 'M j, Y',
				dateFormat: 'Y-m-d',
				locale: flatpickrLocale,
				mode: 'range',
				defaultDate: wpforms_tools_entries_export.dates,
				onChange( selectedDates ) {
					el.$clearDateButton.toggleClass( 'wpforms-hidden', selectedDates.length !== 2 );
				},
			} );
		},

		/**
		 * Render fields checkboxes.
		 *
		 * @since 1.5.5
		 *
		 * @param {Object}  fields        Form fields data.
		 * @param {boolean} paymentFields Payment fields flag.
		 */
		renderFields( fields, paymentFields = false ) {
			if ( typeof fields !== 'object' ) {
				return;
			}

			const html = {
				checkboxes: '',
				options: '',
			};
			const fieldsKeys = Object.keys( fields );

			el.$paymentFieldsSection.show();

			if ( fieldsKeys.length === 0 ) {
				html.checkboxes = '<span>' + i18n.error_form_empty + '</span>';
			} else {
				html.checkboxes += '<label class="wpforms-toggle-all"><input type="checkbox" checked> ' + i18n.label_select_all + '</label>';

				fieldsKeys.forEach( function( index ) {
					let ch = '<label><input type="checkbox" name="fields[{index}]" value="{id}" checked> {label}</label>';
					const id = parseInt( fields[ index ].id, 10 );
					ch = ch.replace( '{index}', parseInt( index, 10 ) + '-' + id );
					ch = ch.replace( '{id}', id );
					ch = ch.replace( '{label}', fields[ index ].label );
					html.checkboxes += ch;

					let op = '<option value="{id}">{label}</option>';
					op = op.replace( '{id}', id );
					op = op.replace( '{label}', fields[ index ].label );
					html.options += op;
				} );
				el.$dateSection.removeClass( 'hidden' );
				el.$searchSection.removeClass( 'hidden' );
			}

			if ( paymentFields ) {
				el.$paymentFieldsCheckboxes.html( html.checkboxes );
			} else {
				el.$fieldsCheckboxes.html( html.checkboxes );
			}

			// Hide payment fields section if there are no payment fields.
			if ( paymentFields && fieldsKeys.length === 0 ) {
				el.$paymentFieldsSection.hide();
			}

			el.$searchField.find( 'optgroup:first-child option:not(:first-child)' ).remove();
			el.$searchField.find( 'optgroup:first-child' ).append( html.options );
		},

		/**
		 * Hide date and search sections if there are no fields.
		 *
		 * @since 1.8.5.2
		 *
		 * @param {Object} formFields    Form fields.
		 * @param {Object} paymentFields Payment fields.
		 */
		handleSearchFields( formFields, paymentFields ) {
			const formFieldsCount = Object.keys( formFields ).length;
			const paymentFieldsCount = Object.keys( paymentFields ).length;

			if ( formFieldsCount === 0 && paymentFieldsCount === 0 ) {
				el.$dateSection.addClass( 'hidden' );
				el.$searchSection.addClass( 'hidden' );
			}
		},

		/**
		 * Show/hide additional options.
		 *
		 * @since 1.8.5
		 *
		 * @param {boolean} isDynamicColumns Is dynamic columns enabled.
		 */
		optionsFields( isDynamicColumns ) {
			app.switchDynamicColumns( isDynamicColumns );
			if ( isDynamicColumns ) {
				// Reset the dynamic columns option after form change.
				el.$optionFields.find( 'input[value=dynamic_columns]' ).prop( 'checked', false );
			}
		},

		/**
		 * Show/hide dynamic columns option.
		 *
		 * @since 1.8.5
		 *
		 * @param {boolean} isDynamicColumns Is dynamic columns enabled.
		 */
		switchDynamicColumns( isDynamicColumns ) {
			const labelElement = el.$optionFields.find( 'input[value=dynamic_columns]' ).parent();
			labelElement.toggle( isDynamicColumns );
		},

		/**
		 * Add notice about dynamic columns.
		 *
		 * @since 1.8.5
		 *
		 * @param {string} notice Notice.
		 */
		addDynamicColumnsNotice( notice ) {
			el.$optionFields.find( '.wpforms-tools-entries-export-notice-warning' ).remove();
			const labelElement = el.$optionFields.find( 'input[value=dynamic_columns]' ).parent();
			labelElement.append( '<div class="wpforms-tools-entries-export-notice-warning wpforms-hide">' + notice + '</div>' );
		},

		/**
		 * Show/hide submit button spinner.
		 *
		 * @since 1.5.5
		 *
		 * @param {boolean} show Show or hide the submit button spinner.
		 */
		displaySubmitSpinner( show ) {
			if ( show ) {
				el.$submitButton.addClass( 'wpforms-btn-spinner-on' );
				el.$cancelButton.removeClass( 'hidden' );
				vars.processing = true;
			} else {
				el.$submitButton.removeClass( 'wpforms-btn-spinner-on' );
				el.$cancelButton.addClass( 'hidden' );
				vars.processing = false;
			}
		},

		/**
		 * Display error message under form selector.
		 *
		 * @since 1.5.5
		 *
		 * @param {string} message Message.
		 */
		displayFormsMessage( message ) {
			el.$selectFormMsg.html( '<p>' + message + '</p>' );

			if ( message.length > 0 ) {
				el.$selectFormMsg.removeClass( 'wpforms-hidden' );
			} else {
				el.$selectFormMsg.addClass( 'wpforms-hidden' );
			}
		},

		/**
		 * Display message under submit button.
		 *
		 * @since 1.5.5
		 *
		 * @param {string} message Message.
		 * @param {string} type    Use 'error' for errors messages.
		 */
		displaySubmitMessage( message, type = '' ) {
			if ( type && type === 'error' ) {
				el.$processMsg.addClass( 'wpforms-error' );
			} else {
				el.$processMsg.removeClass( 'wpforms-error' );
			}

			el.$processMsg.html( '<p>' + message + '</p>' );

			if ( message.length > 0 ) {
				el.$processMsg.removeClass( 'wpforms-hidden' );
			} else {
				el.$processMsg.addClass( 'wpforms-hidden' );
			}
		},

		/**
		 * Initiating file downloading.
		 *
		 * @since 1.5.5
		 *
		 * @param {string} requestId Request ID.
		 */
		triggerDownload( requestId ) {
			let url = wpforms_tools_entries_export.export_page;

			url += '&action=wpforms_tools_entries_export_download';
			url += '&nonce=' + wpforms_tools_entries_export.nonce;
			url += '&request_id=' + requestId;

			el.$expOptions.find( 'iframe' ).remove();
			el.$expOptions.append( '<iframe src="' + url + '"></iframe>' );
			el.$processMsg.find( '.wpforms-download-link' ).attr( 'href', url );
		},

	};

	// Provide access to public functions/properties.
	return app;
}( document, window, jQuery ) );

// Initialize.
WPFormsEntriesExport.init();
