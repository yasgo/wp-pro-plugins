/* global wpforms_admin, wpforms_admin_edit_entry, wpf, wpforms, tinyMCE */
/**
 * WPForms Edit Entry function.
 *
 * @since 1.6.0
 */

'use strict';

var WPFormsEditEntry = window.WPFormsEditEntry || ( function( document, window, $ ) {

	/**
	 * Elements reference.
	 *
	 * @since 1.6.0
	 *
	 * @type {object}
	 */
	var el = {
		$editForm:     $( '#wpforms-edit-entry-form' ),
		$submitButton: $( '#wpforms-edit-entry-update' ),
	};

	/**
	 * Runtime vars.
	 *
	 * @since 1.6.0
	 *
	 * @type {object}
	 */
	var vars = {};

	/**
	 * Public functions and properties.
	 *
	 * @since 1.6.0
	 *
	 * @type {object}
	 */
	var app = {

		/**
		 * Start the engine.
		 *
		 * @since 1.6.0
		 */
		init: function() {

			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 1.6.0
		 */
		ready: function() {

			vars.nonce = el.$editForm.find( 'input[name="nonce"]' ).val();
			vars.entryId = el.$editForm.find( 'input[name="wpforms[entry_id]"]' ).val();

			app.initSavedFormData();
			app.events();

			wpf.initTooltips();
		},

		/**
		 * Register JS events.
		 *
		 * @since 1.6.0
		 */
		events: function() {

			// Submit the form.
			el.$submitButton.on( 'click', app.clickUpdateButton );

			// Submit result.
			el.$editForm
				.on( 'wpformsAjaxSubmitFailed', app.submitFailed )    // Submit Failed, display the errors.
				.on( 'wpformsAjaxSubmitSuccess', app.submitSuccess ); // Submit Success.

			// Prevent lost not saved changes.
			$( window ).on( 'beforeunload', app.beforeUnload );

			// Confirm file deletion.
			$( document ).on( 'click', '.wpforms-edit-entry-field-file-upload .delete', app.fileDelete );
		},

		/**
		 * Store form data for further comparison.
		 *
		 * @since 1.6.0
		 */
		initSavedFormData: function() {

			vars.savedFormData = el.$editForm.serialize();
		},

		/**
		 * Prevent lost not saved changes.
		 *
		 * @since 1.6.0
		 *
		 * @param {object} event Event object.
		 *
		 * @returns {string|void} Not empty string if needed to display standard alert.
		 */
		beforeUnload: function( event ) {

			if ( el.$editForm.serialize() === vars.savedFormData ) {
				return;
			}

			event.returnValue = 'Leave site?';

			return event.returnValue;
		},

		/**
		 * Click Update button event handler.
		 *
		 * @since 1.6.0
		 *
		 * @param {object} event Event object.
		 */
		clickUpdateButton: function( event ) {

			event.preventDefault();

			el.$submitButton.prop( 'disabled', true );

			app.preSubmitActions();

			// Hide all errors.
			app.hideErrors();

			wpforms.formSubmitAjax( el.$editForm );
		},

		/**
		 * Some fields requires special pre-submit actions.
		 *
		 * @since 1.6.0
		 */
		preSubmitActions: function() {

			var formID = $( '#wpforms-edit-entry-form' ).data( 'formid' );

			// Fix for Smart Phone fields.
			$( '.wpforms-smart-phone-field' ).trigger( 'input' );

			// Delete files from the list.
			$( '.wpforms-edit-entry-field-file-upload a.disabled' ).each( function() {

				$( this ).parent().remove();
			} );

			$( '.wpforms-field-file-upload' ).each( function() {

				var $this = $( this );

				if ( $this.is( ':empty' ) ) {
					$this.closest( '.wpforms-edit-entry-field-file-upload' ).addClass( 'empty' );
					$this.html( $( '<span>', {
						class: 'wpforms-entry-field-value',
						text: wpforms_admin_edit_entry.strings.entry_empty_file,
					} ) );
				}
			} );

			// Update Rich Text fields content.
			$( '.wpforms-field-richtext' ).each( function() {

				var fieldID = $( this ).data( 'field-id' ),
					editor = tinyMCE.get( 'wpforms-' + formID + '-field_' + fieldID );

				if ( editor ) {
					editor.save();
				}
			} );
		},

		/**
		 * Submit Failed, display the errors.
		 *
		 * @since 1.6.0
		 *
		 * @param {object} event    Event object.
		 * @param {object} response Response data.
		 */
		submitFailed: function( event, response ) {

			app.displayErrors( response );

			$.alert( {
				title: wpforms_admin.heads_up,
				content: response.data.errors.general,
				icon: 'fa fa-info-circle',
				type: 'orange',
				buttons: {
					confirm: {
						text: wpforms_admin_edit_entry.strings.continue_editing,
						btnClass: 'btn-confirm',
						keys: [ 'enter' ],
					},
					cancel: {
						text    : wpforms_admin_edit_entry.strings.view_entry,
						action  : function() {

							window.location.href = wpforms_admin_edit_entry.strings.view_entry_url;
						},
					},
				},
			} );
		},

		/**
		 * Submit Success.
		 *
		 * @since 1.6.0
		 *
		 * @param {object} event    Event object.
		 * @param {object} response Response data.
		 */
		submitSuccess: function( event, response ) {

			app.initSavedFormData();

			// Display alert only if some changes were made.
			if ( typeof response.data === 'undefined' ) {
				return;
			}

			// Update modified value.
			$( '#wpforms-entry-details .wpforms-entry-modified .date-time' ).text( response.data.modified );

			// Alert message.
			$.alert( {
				title: wpforms_admin_edit_entry.strings.success,
				content: wpforms_admin_edit_entry.strings.msg_saved,
				icon: 'fa fa-info-circle',
				type: 'green',
				buttons: {
					confirm: {
						text: wpforms_admin_edit_entry.strings.continue_editing,
						btnClass: 'btn-confirm',
						keys: [ 'enter' ],
					},
					cancel: {
						text    : wpforms_admin_edit_entry.strings.view_entry,
						action  : function() {

							window.location.href = wpforms_admin_edit_entry.strings.view_entry_url;
						},
					},
				},
			} );
		},

		/**
		 * Hide all errors.
		 *
		 * @since 1.6.0
		 */
		hideErrors: function() {

			el.$editForm.find( '.wpforms-field.wpforms-has-error' ).removeClass( 'wpforms-has-error' );
			el.$editForm.find( '.wpforms-error:not(label)' ).removeClass( 'wpforms-error' );
			el.$editForm.find( 'label.wpforms-error, em.wpforms-error' ).addClass( 'wpforms-hidden' );
		},

		/**
		 * Display validation errors arrived from the server.
		 *
		 * @since 1.6.0
		 *
		 * @param {object} response Response data.
		 */
		displayErrors: function( response ) {

			var errors = response.data && ( 'errors' in response.data ) ? response.data.errors : null;

			if ( wpf.empty( errors ) || wpf.empty( errors.field ) ) {
				return;
			}

			errors = errors.field;

			Object.keys( errors ).forEach( function( fieldID ) {

				// Display field error.
				app.displayFieldError( fieldID, errors[ fieldID ] );

				// Display complex field errors.
				app.displaySubfieldsErrors( fieldID, errors[ fieldID ] );
			} );
		},

		/**
		 * Display field validation error.
		 *
		 * @since 1.6.0
		 *
		 * @param {string} fieldID    Field ID.
		 * @param {string} fieldError Field error.
		 */
		displayFieldError: function( fieldID, fieldError ) {

			if (
				typeof fieldError !== 'string' ||
				( wpf.empty( fieldID ) && fieldID !== '0' ) ||
				wpf.empty( fieldError )
			) {
				return;
			}

			var formID = el.$editForm.data( 'formid' ),
				fieldInputID = 'wpforms-' + formID + '-field_' + fieldID,
				errorLabelID = fieldInputID + '-error',
				$fieldContainer = el.$editForm.find( '#' + fieldInputID + '-container' ),
				$errLabel = el.$editForm.find( '#' + errorLabelID );

			$fieldContainer.addClass( 'wpforms-has-error' );
			$( '#' + fieldInputID ).addClass( 'wpforms-error' );

			if ( $errLabel.length > 0 ) {
				$errLabel.html( fieldError ).removeClass( 'wpforms-hidden' );
				return;
			}

			$fieldContainer.append( '<label id="' + errorLabelID + '" class="wpforms-error">' + fieldError + '</label>' );
		},

		/**
		 * Display validation errors for subfields.
		 *
		 * @since 1.6.0
		 *
		 * @param {string} fieldID     Field ID.
		 * @param {object} fieldErrors Field errors.
		 */
		displaySubfieldsErrors: function( fieldID, fieldErrors ) {

			if ( typeof fieldErrors !== 'object' || wpf.empty( fieldErrors ) || wpf.empty( fieldID ) ) {
				return;
			}

			var formID = el.$editForm.data( 'formid' ),
				fieldInputID = 'wpforms-' + formID + '-field_' + fieldID,
				$fieldContainer = el.$editForm.find( '#' + fieldInputID + '-container' );

			Object.keys( fieldErrors ).forEach( function( key ) {

				var error = fieldErrors[ key ];

				if ( typeof error !== 'string' || error === '' ) {
					return;
				}

				var fieldInputName = 'wpforms[fields][' + fieldID + '][' + key + ']',
					errorLabelID = 'wpforms-' + formID + '-field_' + fieldID + '-' + key + '-error',
					$errLabel = el.$editForm.find( '#' + errorLabelID );

				if ( ! $fieldContainer.hasClass( 'wpforms-has-error' ) ) {
					$fieldContainer.addClass( 'wpforms-has-error' );
				}

				if ( $errLabel.length > 0 ) {
					$fieldContainer.find( '[name="' + fieldInputName + '"]' ).addClass( 'wpforms-error' );
					$errLabel.html( error ).removeClass( 'wpforms-hidden' );
					return;
				}

				var errorLabel = '<label id="' + errorLabelID + '" class="wpforms-error">' + error + '</label>';

				if ( $fieldContainer.hasClass( 'wpforms-field-likert_scale' ) ) {
					$fieldContainer.find( 'tr' ).eq( key.replace( /r/, '' ) ).after( errorLabel );
					return;
				}

				$fieldContainer.find( '[name="' + fieldInputName + '"]' ).addClass( 'wpforms-error' ).after( errorLabel );
			} );
		},

		/**
		 * Confirm file deletion.
		 *
		 * @since 1.6.6
		 *
		 * @param {object} event Event object.
		 */
		fileDelete : function( event ) {

			event.preventDefault();

			var $element = $( this ),
				$fileInput = $element.parent().find( 'a' ).first();

			// Trigger alert modal to confirm.
			$.confirm( {
				title: false,
				content:  wpforms_admin_edit_entry.strings.entry_delete_file.replace( '{file_name}', $fileInput.html() ),
				icon: 'fa fa-exclamation-circle',
				type: 'orange',
				buttons: {
					confirm: {
						text: wpforms_admin.ok,
						btnClass: 'btn-confirm',
						keys: [ 'enter' ],
						action: function() {

							$fileInput.html( $fileInput.text().strike() );
							$fileInput.addClass( 'disabled' );
							$element.parent().find( 'input[type="hidden"]' ).remove();
							$element.remove();
						},
					},
					cancel: {
						text: wpforms_admin.cancel,
						keys: [ 'esc' ],
					},
				},
			} );
		},
	};

	// Provide access to public functions/properties.
	return app;

}( document, window, jQuery ) );

// Initialize.
WPFormsEditEntry.init();
