/* global Choices, wpf, wpforms_builder */

'use strict';

var WPForms = window.WPForms || {};

WPForms.Admin = WPForms.Admin || {};
WPForms.Admin.Builder = WPForms.Admin.Builder || {};

WPForms.Admin.Builder.Notifications = WPForms.Admin.Builder.Notifications || ( function( document, window, $ ) {

	/**
	 * Elements holder.
	 *
	 * @since 1.7.7
	 *
	 * @type {object}
	 */
	let el = {};

	/**
	 * ChoicesJS config.
	 *
	 * @since 1.7.7
	 *
	 * @type {object}
	 */
	const choicesJSConfig = {
		removeItemButton: true,
		shouldSort: false,
	};

	/**
	 * ChoicesJS config for File Upload attachment field.
	 *
	 * @since 1.7.8
	 *
	 * @type {object}
	 */
	let choicesJSConfigFileUpload;

	/**
	 * Advanced Notification sections.
	 *
	 * @since 1.7.8
	 *
	 * @type {Array}
	 */
	const advancedNotificationSections = [
		{ // File Upload Attachments.
			toggle: {
				className: '.notifications_enable_file_upload_attachment_toggle',
				actionElements: [ 'file_upload_attachment_fields' ],
			},
			choicesJS: {
				fieldName: 'file_upload_attachment_fields',
				choices: 'fileUpload',
			},
		},
		{ // Entry CSV Attachment.
			toggle: {
				className: '.notifications_enable_entry_csv_attachment_toggle',
				actionElements: [
					'entry_csv_attachment_entry_information',
					'entry_csv_attachment_file_name',
				],
			},
			choicesJS: {
				fieldName: 'entry_csv_attachment_entry_information',
				choices: 'entryInformation',
			},
		},
	];

	/**
	 * Public functions and properties.
	 *
	 * @since 1.7.7
	 *
	 * @type {object}
	 */
	const app = {

		/**
		 * Init Advanced Notifications section.
		 *
		 * @since 1.7.7
		 */
		init: function() {

			choicesJSConfigFileUpload = Object.assign( {}, choicesJSConfig );
			choicesJSConfigFileUpload.noChoicesText = wpforms_builder.notifications_file_upload.no_choices_text;

			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 1.7.7
		 */
		ready: function() {

			if ( typeof window.Choices !== 'function' ) {
				return;
			}

			app.setup();
			app.bindEvents();
			app.maybeSaveFormState();
		},

		/**
		 * Setup. Prepare some variables.
		 *
		 * @since 1.7.7
		 */
		setup: function() {

			// Cache DOM elements.
			el = {
				$builder: $( '#wpforms-builder' ),
			};
		},

		/**
		 * Initialized the Advanced section fields.
		 *
		 * @since 1.7.8
		 *
		 * @param {object}  $block             jQuery element of the block.
		 * @param {boolean} isDynamicallyAdded Whether this block is dynamically added or not.
		 * @param {string}  originalId         Original notification block id if notification is cloned.
		 */
		initBlock: function( $block, isDynamicallyAdded = false, originalId = '' ) {

			const blockID = $block.data( 'blockId' );

			if ( $block.data( 'blockType' ) !== 'notification' || ! blockID ) {
				return;
			}

			advancedNotificationSections.forEach( function( section ) {

				app.initToggle(
					$block,
					blockID,
					section.toggle.className,
					section.toggle.actionElements
				);

				const $choicesJSField = $block.find( `.${section.choicesJS.fieldName}` ).first();

				if ( $choicesJSField.length <= 0 ) {
					return;
				}

				let choices;
				let choicesConfig = choicesJSConfig;

				if ( section.choicesJS.choices === 'fileUpload' ) {
					choices = app.choicesJSHelperMethods.fileUploadFields.getAllChoices();

					if ( choices[0].choices.length === 0 ) {
						choicesConfig = choicesJSConfigFileUpload;
					}
				} else {
					choices = app.choicesJSHelperMethods.entryInformation.getAllChoices();
				}

				// Handle initial ChoicesJS field.
				if ( isDynamicallyAdded ) {

					/*
					 We should clear existing choices for added notification block
					 But not on cloned blocks.
					 */
					const shouldClearExistingChoices = ! originalId;

					app.initDynamicallyAddedChoicesJS(
						$block,
						section.choicesJS.fieldName,
						shouldClearExistingChoices
					);

					app.setDynamicallyAddedEntryInformationFileNameFieldValue( $block, originalId );
				}

				app.initChoicesJS( $choicesJSField, choices, choicesConfig );

				if ( section.choicesJS.choices === 'fileUpload' ) {

					// Initially compute the File Upload Attachment Size.
					app.computeForFileUploadAttachmentSize( $choicesJSField );

					$choicesJSField.on( 'change', app.fileUploadFieldChange );
				}
			} );
		},

		/**
		 * Initialize toggle.
		 *
		 * @since 1.7.8
		 *
		 * @param {object} $block         jQuery element of the block.
		 * @param {number} blockID        The block ID.
		 * @param {string} toggleElClass  Class name of the toggle element.
		 * @param {Array}  actionElements Elements that are controlled by the toggle.
		 *
		 * @returns {boolean} Whether or not the toggle has been initialized.
		 */
		initToggle: function( $block, blockID, toggleElClass, actionElements ) {

			const $toggleEl = $block.find( toggleElClass ).first();

			if ( $toggleEl.length <= 0 ) {
				return false;
			}

			return app.setupToggleConditional( $toggleEl, blockID, actionElements );
		},

		/**
		 * Setup the conditional for the toggle and its action elements.
		 *
		 * @since 1.7.8
		 *
		 * @param {object} $toggleEl      jQuery element of the toggle.
		 * @param {number} blockID        The block ID.
		 * @param {Array}  actionElements Elements that are controlled by the toggle.
		 *
		 * @returns {boolean} Returns `false` if there are no action elements found. Otherwise, returns `true`.
		 */
		setupToggleConditional: function( $toggleEl, blockID, actionElements ) {

			const actionElementIds = actionElements.map( function( element ) {
				return `#wpforms-panel-field-notifications-${blockID}-${element}-wrap`;
			} );

			if ( actionElementIds.length <= 0 ) {
				return false;
			}

			const actionElement = actionElementIds.join( ',' );

			$toggleEl.conditions( [
				{
					conditions: {
						element:   $toggleEl,
						type:      'checked',
						operator:  'is',
						condition: '1',
					},
					actions: {
						if: {
							element: actionElement,
							action: 'show',
						},
						else: {
							element: actionElement,
							action:  'hide',
						},
					},
					effect: 'appear',
				},
			] );

			return true;
		},

		/**
		 * Initialize ChoicesJS in a given select field.
		 *
		 * @since 1.7.7
		 * @since 1.7.8 Added `choices` and `choicesConfig` parameters.
		 *
		 * @param {object} $selectEl     jQuery element of the select field.
		 * @param {Array}  choices       Array containing the choices for the ChoicesJS instance.
		 * @param {object} choicesConfig ChoicesJS config.
		 *
		 * @returns {false|Choices} ChoicesJS instance.
		 */
		initChoicesJS: function( $selectEl, choices, choicesConfig ) {

			const fieldName = $selectEl.attr( 'name' );

			if ( ! fieldName ) {
				return false;
			}

			// Original val.
			const originalVal = $selectEl.val(),
				choiceJSInstance = $selectEl.data( 'choicesjs' );

			if ( typeof choiceJSInstance !== 'undefined' ) {
				choiceJSInstance.destroy();
			}

			const choicesJS = new Choices( $selectEl[0], choicesConfig );

			// Init and cache the ChoicesJS init.
			$selectEl.data( 'choicesjs', choicesJS );

			$selectEl
				.on( 'change', app.changeChoicesJS );

			app.choicesJSHelperMethods.populateInstance( choicesJS, choices, originalVal );

			return choicesJS;
		},

		/**
		 * ChoicesJS field change event handler.
		 *
		 * @since 1.7.8
		 */
		changeChoicesJS: function() {

			const $this = $( this );
			const currentVal = $this.data( 'choicesjs' ).getValue();
			let fieldName = $this.attr( 'name' );

			if ( ! fieldName || ! currentVal ) {
				return;
			}

			// Find the closest hidden field designated to this field.
			const hiddenFieldName = fieldName + '[hidden]',
				hiddenField = $this.closest( '.wpforms-panel-field' ).find( `input[name="${hiddenFieldName}"]` );

			if ( hiddenField.length <= 0 ) {
				return;
			}

			const newVal = [];

			for ( let i = 0; i < currentVal.length; i++ ) {
				newVal.push( currentVal[ i ].value );
			}

			hiddenField.val( JSON.stringify( newVal ) );
		},

		/**
		 * Change event handler for "File Upload Fields" select/ChoicesJS field.
		 *
		 * @since 1.7.8
		 */
		fileUploadFieldChange: function() {

			app.computeForFileUploadAttachmentSize( $( this ) );
		},

		/**
		 * Compute and display the file upload size in MB.
		 *
		 * @since 1.7.8
		 *
		 * @param {object} $choicesJSField jQuery element of the ChoicesJS field.
		 */
		computeForFileUploadAttachmentSize: function( $choicesJSField ) {

			const currentVal = $choicesJSField.data( 'choicesjs' ).getValue();
			const $fileSizeDom = $choicesJSField.parents( '.wpforms-panel-field' ).find( '.notifications-file-upload-attachment-size' );

			if ( $fileSizeDom.length <= 0 ) {
				return;
			}

			if ( currentVal.length <= 0 ) {
				$fileSizeDom.text( 0 );
				return;
			}

			const defaultMaxSize = Number( wpforms_builder.notifications_file_upload.wp_max_upload_size );
			let fileUploadSize = 0;

			for ( let counter = 0; counter < currentVal.length; counter++ ) {
				const field = wpf.getField( currentVal[ counter ].value );

				if ( field.type !== 'file-upload' ) {
					continue;
				}

				const maxSize = app.utils.convertToNumber( field.max_size, defaultMaxSize );
				const maxFileNumber = app.utils.convertToNumber( field.max_file_number, 1 );

				fileUploadSize += maxSize * maxFileNumber;
			}

			$fileSizeDom.text( +wpf.numberFormat( fileUploadSize, 2, '.', ',' ).replace( ',', '' ) );
		},

		/**
		 * Initialized ChoicesJS in Entry Information field in the newly added notifications block.
		 *
		 * @since 1.7.8
		 *
		 * @param {object}  $block                     jQuery element of the newly added notifications block.
		 * @param {string}  fieldName                  Field name.
		 * @param {boolean} shouldClearExistingChoices Whether to clear existing selected choices or not.
		 */
		initDynamicallyAddedChoicesJS: function( $block, fieldName, shouldClearExistingChoices ) {

			const blockID = $block.data( 'blockId' );

			// Find the ChoicesJS Field wrapper.
			const $divWrapper = $block.find( `#wpforms-panel-field-notifications-${blockID}-${fieldName}-wrap` );

			if ( $divWrapper.length <= 0 ) {
				return;
			}

			// Find the ChoicesJS Wrapper inside the newly added notification.
			const $choicesWrapper = $divWrapper.find( '.choices' );

			if ( $choicesWrapper.length <= 0 ) {
				return;
			}

			const $choicesJSField = $choicesWrapper.find( `.${fieldName}` ).first();

			if ( $choicesJSField.length <= 0 ) {
				return;
			}

			// Remove the ChoicesJS artifact.
			$choicesJSField
				.removeClass( 'choices__input' )
				.removeAttr( 'hidden' )
				.removeAttr( 'data-choice' )
				.removeData( 'choice' )
				.prependTo( $divWrapper.first() );

			$divWrapper.find( 'label:first' ).prependTo( $divWrapper.first() );

			// Delete the ChoicesJS wrapper DOM.
			$choicesWrapper.first().remove();

			// Make sure nothing is pre-selected for.
			if ( shouldClearExistingChoices ) {
				$choicesJSField.val( [] );
			}
		},

		/**
		 * Bind events.
		 *
		 * @since 1.7.7
		 */
		bindEvents: function() {

			el.$builder
				.on( 'wpformsSettingsBlockAdded', app.notificationBlockAdded )
				.on( 'wpformsSettingsBlockCloned', app.notificationsBlockCloned )
				.on( 'wpformsPanelSwitch', app.panelSwitch )
				.on( 'wpformsPanelSectionSwitch', app.panelSectionSwitch );
		},

		/**
		 * Resetting fields when we add a new webhook.
		 *
		 * @since 1.7.8
		 *
		 * @param {object} event  Event object.
		 * @param {object} $block New Webhook block.
		 */
		notificationBlockAdded: function( event, $block ) {

			app.initBlock( $block, true );
		},

		/**
		 * Insert the default value in File Name field for dynamically added
		 * notification block.
		 *
		 * @since 1.7.7
		 * @since 1.7.8 Replaced the second parameter from preselected file name to `originalId`.
		 *
		 * @param {object} $block     jQuery element of the newly added notifications block.
		 * @param {string} originalId Original notification block id if notification is cloned.
		 */
		setDynamicallyAddedEntryInformationFileNameFieldValue: function( $block, originalId ) {

			const $entryInformationFileNameField = $block.find( '.entry_csv_attachment_file_name' );

			if ( $entryInformationFileNameField.length <= 0 ) {
				return;
			}

			const preSelectedFileName = originalId ? $( `#wpforms-panel-field-notifications-${originalId}-entry_csv_attachment_file_name` ).val() : '';

			$entryInformationFileNameField.val( preSelectedFileName.length === 0 ? wpforms_builder.entry_information.default_file_name : preSelectedFileName );
		},

		/**
		 * Triggered when a notification block was cloned.
		 *
		 * @since 1.7.7
		 *
		 * @param {Event}  ev         Event object.
		 * @param {object} $clone     jQuery element cloned.
		 * @param {string} originalId Original notification ID.
		 */
		notificationsBlockCloned: function( ev, $clone, originalId ) {

			app.initBlock( $clone, true, originalId );
		},

		/**
		 * Event handler for panel switch.
		 *
		 * @since 1.7.8
		 *
		 * @param {object} event Event object.
		 * @param {string} panel Active panel.
		 */
		panelSwitch: function( event, panel ) {

			if ( panel !== 'settings' ) {
				return;
			}

			// Find the active section.
			const activeSection = $( '#wpforms-panel-settings .wpforms-panel-sidebar' ).find( '.wpforms-panel-sidebar-section.active' ).data( 'section' );

			if ( activeSection !== 'notifications' ) {
				return;
			}

			app.loopAllNotificationsBlock( function( $block, blockID ) {
				app.initBlock( $block );
			} );
		},

		/**
		 * Loop through all notifications block and invoke the passed callback function.
		 *
		 * @since 1.7.8
		 *
		 * @param {Function} callback Callback function.
		 */
		loopAllNotificationsBlock: function( callback ) {

			$( '.wpforms-notification.wpforms-builder-settings-block' ).each( function( index, block ) {

				const $block = $( block );
				const blockID = $block.data( 'blockId' );

				if ( $block.data( 'blockType' ) !== 'notification' || ! blockID ) {
					return;
				}

				callback( $block, blockID );
			} );

			// Re-save the form state since DOM has been changed.
			app.maybeSaveFormState();
		},

		/**
		 * Event handler for panel section switch.
		 *
		 * @since 1.7.8
		 *
		 * @param {object} event   Event object.
		 * @param {string} section Active section.
		 */
		panelSectionSwitch: function( event, section ) {

			if ( section !== 'notifications' ) {
				return;
			}

			app.loopAllNotificationsBlock( function( $block, blockID ) {
				app.initBlock( $block );
			} );
		},

		/**
		 * Save the form state if it was changed during the initialization process.
		 *
		 * @since 1.7.7
		 */
		maybeSaveFormState: function() {

			const currentState = wpf.getFormState( '#wpforms-builder-form' );

			// If some elements were changed (e.g. ChoiceJS instance was pre-populated),
			// then the whole form state was changed as well.
			// That's why we need to re-save it.
			if ( wpf.savedState !== currentState ) {
				wpf.savedState = currentState;
			}
		},

		/**
		 * Helper methods for ChoicesJS.
		 *
		 * @since 1.7.7
		 */
		choicesJSHelperMethods: {

			/**
			 * Choices for "File Upload Fields" field.
			 *
			 * @since 1.7.8
			 */
			fileUploadFields: {

				/**
				 * Get all choices.
				 *
				 * @since 1.7.8
				 *
				 * @returns {Array} Array of all the choices.
				 */
				getAllChoices: function() {

					return [
						{
							label: 'hidden',
							choices: app.choicesJSHelperMethods.getFormFields( [ 'file-upload' ] ),
						},
					];
				},
			},

			/**
			 * Choices for "Entry Information" field.
			 *
			 * @since 1.7.8
			 */
			entryInformation: {

				/**
				 * Get all choices.
				 *
				 * @since 1.7.8
				 *
				 * @returns {Array} Array of all the choices.
				 */
				getAllChoices: function() {

					return [
						{
							label: 'hidden',
							choices: [
								{
									value: 'all_fields',
									label: wpforms_builder.entry_information.localized.all_fields,
								},
							],
						},
						{
							label: wpforms_builder.fields_available,
							choices: app.choicesJSHelperMethods.getFormFields( false, wpforms_builder.entry_information.excluded_field_types ),
						},
						{
							label: wpforms_builder.other,
							choices: app.choicesJSHelperMethods.entryInformation.getOtherChoices(),
						},
					];
				},

				/**
				 * Get "Other" options.
				 *
				 * @since 1.7.8
				 *
				 * @returns {*[]} Returns an array of objects containing the label and value (field key).
				 */
				getOtherChoices: function() {

					let otherFields = [];

					// Get "Others".
					for ( const smartTagKey in wpforms_builder.smart_tags ) {

						if ( wpforms_builder.entry_information.excluded_tags.includes( smartTagKey ) ) {
							continue;
						}

						// Replace the value if necessary.
						let value = Object.hasOwn( wpforms_builder.entry_information.replacement_tags, smartTagKey ) ? wpforms_builder.entry_information.replacement_tags[ smartTagKey ] : smartTagKey;

						otherFields.push( {
							label: wpf.encodeHTMLEntities( wpforms_builder.smart_tags[ smartTagKey ] ),
							value: wpf.sanitizeHTML( value ),
						} );
					}

					return otherFields;
				},
			},

			/**
			 * Get current form fields.
			 *
			 * @since 1.7.8
			 *
			 * @param {Array|false} allowed Field types to return. Pass `false` to return all fields.
			 * @param {Array}       exclude Field types to exclude.
			 *
			 * @returns {Array} Array containing fields in `object` with `label` and `value` properties.
			 */
			getFormFields: function( allowed, exclude = [] ) {

				let availableFields = [];

				const fields = wpf.getFields( allowed, true );

				for ( const fieldKey of wpf.orders.fields ) {

					const field = fields[ fieldKey ];

					if ( ! field || field.label === undefined || app.choicesJSHelperMethods.isFieldExcluded( field, exclude ) ) {
						continue;
					}

					availableFields.push( {
						label: wpf.encodeHTMLEntities( app.choicesJSHelperMethods.getFieldLabel( field ) ),
						value: wpf.sanitizeHTML( field.id.toString() ),
					} );
				}

				return availableFields;
			},

			/**
			 * Check whether the given `field` is excluded based on the passed `exclude`.
			 *
			 * @since 1.7.8
			 *
			 * @param {object} field   Field to check.
			 * @param {Array}  exclude Array of fields that should be excluded.
			 *
			 * @returns {boolean} Whether the given `field` is excluded.
			 */
			isFieldExcluded: function( field, exclude ) {

				return Array.isArray( exclude ) && exclude.length > 0 && exclude.includes( field.type );
			},

			/**
			 * Returns the label of a field.
			 *
			 * If the field doesn't have a label, it will use `wpforms_builder.empty_label_alternative_text`
			 * instead.
			 *
			 * @since 1.7.8
			 *
			 * @param {object} field Field object.
			 *
			 * @returns {string} Label of the field.
			 */
			getFieldLabel: function( field ) {

				return field.label.length === 0 ? `${wpforms_builder.empty_label_alternative_text}${field.id}` : field.label;
			},

			/**
			 * Populate ChoiceJS instance with values with optional pre-selected ones.
			 *
			 * @since 1.7.7
			 *
			 * @param {Choices} choicesJS   ChoicesJS instance.
			 * @param {Array}   choices     Array containing the choices for the ChoicesJS instance.
			 * @param {Array}   preSelected Array of pre-selected choices.
			 */
			populateInstance: function( choicesJS, choices, preSelected = [] ) {

				if ( ! choicesJS ) {
					return;
				}

				choicesJS.clearStore();

				choicesJS.setChoices( choices );

				if ( ! Array.isArray( preSelected ) ) {
					return;
				}

				preSelected.forEach( function( item ) {
					choicesJS.setChoiceByValue( item );
				} );
			},
		},

		/**
		 * Other utils.
		 *
		 * @since 1.7.8
		 */
		utils: {

			/**
			 * Converts a given value to a number.
			 *
			 * If the converted value is less than or equal to 0, return the default value instead.
			 *
			 * @since 1.7.8
			 *
			 * @param {any}    value        Value to be converted.
			 * @param {number} defaultValue Default value.
			 *
			 * @returns {number} The value converted to a number or the default value.
			 */
			convertToNumber: function( value, defaultValue ) {

				const convertValue = Number( value );

				return ( convertValue <= 0 ) ? defaultValue : convertValue;
			},
		},
	};

	// Provide access to public functions/properties.
	return app;

}( document, window, jQuery ) );

// Initialize.
WPForms.Admin.Builder.Notifications.init();
