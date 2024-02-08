/* global WPFormsBuilder, wpforms_builder, WPFormsUtils */

/**
 * Form Builder Field Layout module.
 *
 * @since 1.7.7
 */

'use strict';

var WPForms = window.WPForms || {};

WPForms.Admin = WPForms.Admin || {};
WPForms.Admin.Builder = WPForms.Admin.Builder || {};

WPForms.Admin.Builder.FieldLayout = WPForms.Admin.Builder.FieldLayout || ( function( document, window, $ ) {

	/**
	 * Elements holder.
	 *
	 * @since 1.7.7
	 *
	 * @type {object}
	 */
	let el = {};

	/**
	 * Public functions and properties.
	 *
	 * @since 1.7.7
	 *
	 * @type {object}
	 */
	const app = {

		/**
		 * Start the engine.
		 *
		 * @since 1.7.7
		 */
		init: function() {

			$( app.ready );
		},

		/**
		 * DOM is fully loaded.
		 *
		 * @since 1.7.7
		 */
		ready: function() {

			app.setup();
			app.initLabels();
			app.events();
		},

		/**
		 * Setup. Prepare some variables.
		 *
		 * @since 1.7.7
		 */
		setup: function() {

			// Cache DOM elements.
			el = {
				$builder:            $( '#wpforms-builder' ),
				$fieldOptions:       $( '#wpforms-field-options' ),
				$sortableFieldsWrap: $( '#wpforms-panel-fields .wpforms-field-wrap' ),
			};
		},

		/**
		 * Bind events.
		 *
		 * @since 1.7.7
		 */
		events: function() {

			el.$builder
				.on( 'click', '.wpforms-layout-column-placeholder', app.columnPlaceholderClick )
				.on( 'change', '.wpforms-field-option-row-preset input', app.presetChange )
				.on( 'mouseenter', '.wpforms-field-layout .wpforms-field', app.subfieldMouseEnter )
				.on( 'mouseleave', '.wpforms-field-layout .wpforms-field', app.subfieldMouseLeave )
				.on( 'wpformsFieldAddDragStart', app.fieldCantAddModal )
				.on( 'wpformsFieldAdd', app.fieldAdd )
				.on( 'wpformsBeforeFieldAddToDOM', app.beforeFieldAddToDOM )
				.on( 'wpformsBeforeFieldAddOnClick', app.beforeFieldAddOnClick )
				.on( 'wpformsBeforeFieldDelete', app.beforeFieldDelete )
				.on( 'wpformsBeforeFieldDeleteAlert', app.adjustDeleteFieldAlert )
				.on( 'wpformsFieldOptionTabToggle', app.fieldOptionsUpdate )
				.on( 'wpformsFieldMoveRejected', app.fieldMoveRejected )
				.on( 'wpformsBeforeFieldDuplicate', app.beforeFieldDuplicate )
				.on( 'wpformsFieldDuplicated', app.fieldDuplicated );
		},

		/**
		 * Column placeholder click event handler.
		 *
		 * @since 1.7.7
		 *
		 * @param {object} e Event object.
		 */
		columnPlaceholderClick: function( e ) {

			e.stopPropagation();

			const $placeholder = $( this ),
				$column = $placeholder.closest( '.wpforms-layout-column' ),
				isActive = $column.hasClass( 'wpforms-fields-sortable-default' ),
				$allColumns = el.$sortableFieldsWrap.find( '.wpforms-layout-column' );

			$allColumns.removeClass( 'wpforms-fields-sortable-default' );
			$column.toggleClass( 'wpforms-fields-sortable-default', ! isActive );

			WPFormsBuilder.fieldTabToggle( 'add-fields' );
		},

		/**
		 * Column placeholder click event handler.
		 *
		 * @since 1.7.7
		 *
		 * @param {object} e Event object.
		 */
		presetChange: function( e ) {

			const $input = $( this ),
				preset = $input.val(),
				$presetsRow = $input.closest( '.wpforms-field-option-row-preset' ),
				fieldId = $presetsRow.data( 'field-id' ),
				$fieldPreview = $( `#wpforms-field-${fieldId}` ),
				$fieldPreviewColumns = $fieldPreview.find( '.wpforms-field-layout-columns' );

			let $columnFields = [];

			// Detach and store in array all the fields from columns.
			$fieldPreviewColumns.find( '.wpforms-layout-column' ).each( function( columnIndex ) {
				$columnFields[ columnIndex ] = $( this ).find( '.wpforms-field' ).detach();
			} );

			let oldColumnsData = app.getFieldColumnsData( fieldId ),
				newColumnsData = preset.split( '-' )
					.map( function( width, i ) {

						return {
							'width_preset': width,
							'fields': oldColumnsData[ i ] ? oldColumnsData[ i ].fields : [],
						};
					} );

			// Generate new columns markup. Update field columns data.
			app.updateFieldColumnsData( fieldId, newColumnsData );
			$fieldPreviewColumns.html( app.generatePreviewColumns( newColumnsData ) );

			// Restore all the fields in new columns.
			$fieldPreviewColumns.find( '.wpforms-layout-column' ).each( function( columnIndex ) {

				const $column = $( this );

				$column.append( $columnFields[ columnIndex ] );
				WPForms.Admin.Builder.DragFields.initSortableContainer( $column );
			} );

			// Continue only if the new preset has fewer columns than the old one.
			if ( newColumnsData.length < oldColumnsData.length ) {

				// Combine all the remaining fields.
				let $allRemainingFields = $( [] );

				for ( let i = newColumnsData.length; i < oldColumnsData.length; i++ ) {
					$allRemainingFields = $allRemainingFields.add( $columnFields[ i ] );
				}

				// Add all the remaining fields to the base level after the layout field.
				$fieldPreview.after( $allRemainingFields );
			}

			// Update the order of the options of the fields inside the Layout field.
			app.reorderLayoutFieldsOptions( $fieldPreview );

			/**
			 * Event fired at the end of the change of layout preset.
			 *
			 * @since 1.7.8
			 *
			 * @param {object} data Layout field data object.
			 */
			el.$builder.trigger( 'wpformsLayoutAfterPresetChange', { fieldId: fieldId, preset: preset, newColumnsData: newColumnsData, oldColumnsData: oldColumnsData } );
		},

		/**
		 * Generate preview columns HTML.
		 *
		 * @since 1.7.7
		 *
		 * @param {Array} columnsData Columns data.
		 *
		 * @returns {string} Preview columns HTML.
		 */
		generatePreviewColumns: function( columnsData ) {

			if ( ! columnsData || ! columnsData.length ) {
				return '';
			}

			const placeholder = wp.template( 'wpforms-layout-field-column-plus-placeholder-template' )();
			const columnsHTML = columnsData.map( function( column ) {

				return `<div class="wpforms-layout-column wpforms-layout-column-${column['width_preset']}">
							${placeholder}
						</div>`;
			} );

			return columnsHTML.join( '' );
		},

		/**
		 * Get field columns data.
		 *
		 * @since 1.7.7
		 *
		 * @param {integer|number} fieldId Field Id.
		 *
		 * @returns {Array} Columns data.
		 */
		getFieldColumnsData: function( fieldId ) {

			let dataJson = $( `#wpforms-field-option-${fieldId}-columns-json` ).val(),
				data;

			try {
				data = JSON.parse( dataJson );
			} catch ( e ) {
				data = [];
			}

			return data;
		},

		/**
		 * Check if field with given ID is inside one of the columns in the layout with given ID.
		 *
		 * @since 1.7.8
		 *
		 * @param {int} layoutId The ID of the layout field.
		 * @param {int} fieldId  The ID of the field to look for.
		 *
		 * @returns {boolean} If the field exists in the layout.
		 */
		columnsHasFieldID: function( layoutId, fieldId ) {

			/*
			Get field columns data, and filter it to have only those columns, which in column.fields has field ID value.
			Return true if length of such reduced data is bigger than 0.
			 */
			return app.getFieldColumnsData( layoutId ).filter( function( column ) {
				return column.fields.includes( fieldId );
			} ).length > 0;
		},

		/**
		 * Update field columns data.
		 *
		 * @since 1.7.7
		 * @since 1.7.8 Added new triggers: `wpformsLayoutColumnsDataUpdated`, `wpformsLayoutAfterUpdateColumnsData`.
		 *
		 * @param {integer|number} fieldId Field Id.
		 * @param {Array}          data    Columns data.
		 */
		updateFieldColumnsData: function( fieldId, data ) {

			const $holder = $( `#wpforms-field-option-${fieldId}-columns-json` ),
				currentColumnsData = $holder.val(),
				newColumnsData = JSON.stringify( data );

			$holder.val( newColumnsData );

			if ( currentColumnsData !== newColumnsData ) {
				el.$builder.trigger( 'wpformsLayoutColumnsDataUpdated', { fieldId: fieldId, data: data } );
			}

			el.$builder.trigger( 'wpformsLayoutAfterUpdateColumnsData', { fieldId: fieldId, data: data } );
		},

		/**
		 * Event `wpformsBeforeFieldAddToDOM` handler.
		 *
		 * @since 1.7.7
		 *
		 * @param {Event}  e           Event.
		 * @param {object} options     Field add additional options.
		 * @param {jQuery} $newField   New field preview object.
		 * @param {jQuery} $newOptions New field options object.
		 * @param {jQuery} $sortable   Sortable container.
		 */
		beforeFieldAddToDOM: function( e, options, $newField, $newOptions, $sortable ) {

			if (
				! $sortable ||
				! $sortable.length ||
				! $sortable.hasClass( 'wpforms-layout-column' )
			) {
				return;
			}

			// This is needed to skip adding the field to the base level.
			// Corresponding check is in `admin-builder.js` before calling the `app.fieldAddToBaseLevel()`.
			e.skipAddFieldToBaseLevel = true;

			// Add the field to the column of the Layout field.
			app.fieldAddToColumn(
				$newField,
				$newOptions,
				options.position,
				$sortable
			);
		},

		/**
		 * Event `wpformsFieldAdd` handler.
		 *
		 * @since 1.7.7
		 *
		 * @param {Event}   e       Event.
		 * @param {integer} fieldId Field id.
		 * @param {string}  type    Field type.
		 */
		fieldAdd: function( e, fieldId, type ) {

			const $fieldOptions = $( `#wpforms-field-option-${fieldId}` ),
				$prevFieldOptions = $fieldOptions.prev(),
				prevFieldType = $prevFieldOptions.find( '.wpforms-field-option-hidden-type' ).val();

			// In the case of new field was placed right after the Layout field,
			// we must reorder the options of the fields in the columns of this layout.
			if ( prevFieldType === 'layout' ) {
				const prevFieldId = $prevFieldOptions.find( '.wpforms-field-option-hidden-id' ).val();

				app.reorderLayoutFieldsOptions(
					$( `#wpforms-field-${prevFieldId}` )
				);
			}

			if ( type !== 'layout' ) {
				return;
			}

			el.$builder.find( `#wpforms-field-${fieldId} .wpforms-layout-column` ).each( function() {
				WPForms.Admin.Builder.DragFields.initSortableContainer( $( this ) );
			} );
		},

		/**
		 * Event `wpformsBeforeFieldAddOnClick` handler.
		 *
		 * @since 1.7.7
		 *
		 * @param {Event}  e      Event.
		 * @param {string} type   Field type.
		 * @param {jQuery} $field Field button object
		 */
		beforeFieldAddOnClick: function( e, type, $field ) {

			app.fieldCantAddModal( e, type, {} );

			if ( e.isDefaultPrevented() ) {
				return;
			}

			if ( app.isFieldAllowedInColum( type ) ) {
				return;
			}

			const $defaultColumn = el.$sortableFieldsWrap.find( '.wpforms-fields-sortable-default' );

			if ( ! $defaultColumn.length ) {
				return;
			}

			e.preventDefault();
			app.fieldMoveRejected( e, $field, null );
		},

		/**
		 * Event `wpformsBeforeFieldDelete` handler.
		 *
		 * @since 1.7.7
		 *
		 * @param {Event}   e       Event.
		 * @param {integer} fieldId Field id.
		 * @param {string}  type    Field type.
		 */
		beforeFieldDelete: function( e, fieldId, type ) {

			if ( type !== 'layout' ) {
				app.removeFieldFromColumns( fieldId );

				// When user delete the field in the column, we need to remove
				// the class `wpforms-field-child-hovered` from the Layout field
				// in order to bring back the Duplicate and Delete icons.
				$( `#wpforms-field-${fieldId}` )
					.closest( '.wpforms-field-layout' )
					.removeClass( 'wpforms-field-child-hovered' );

				return;
			}

			const columnsData = app.getFieldColumnsData( fieldId );

			// Delete all the fields inside columns.
			columnsData.forEach( function( column ) {

				if ( ! Array.isArray( column.fields ) ) {
					return;
				}

				column.fields.forEach( function( fieldId ) {
					WPFormsBuilder.fieldDeleteById( fieldId );
				} );
			} );
		},

		/**
		 * Adjust warning text for delete field alert.
		 *
		 * @since 1.7.7
		 *
		 * @param {object} e         Triggered event.
		 * @param {object} fieldData Field data.
		 * @param {string} type      Field type.
		 */
		adjustDeleteFieldAlert: function( e, fieldData, type ) {

			if ( type !== 'layout' ) {
				return;
			}

			e.preventDefault();

			$.confirm( {
				title: false,
				content: wpforms_builder.layout.delete_confirm,
				icon: 'fa fa-exclamation-circle',
				type: 'orange',
				buttons: {
					confirm: {
						text: wpforms_builder.ok,
						btnClass: 'btn-confirm',
						keys: [ 'enter' ],
						action: function() {

							WPFormsBuilder.fieldDeleteById( fieldData.id );
						},
					},
					cancel: {
						text: wpforms_builder.cancel,
					},
				},
			} );
		},

		/**
		 * Update field options according to the position of the field.
		 * Event `wpformsFieldOptionTabToggle` handler.
		 *
		 * @since 1.7.7
		 *
		 * @param {Event}   e       Event.
		 * @param {integer} fieldId Field id.
		 */
		fieldOptionsUpdate: function( e, fieldId ) {

			app.fieldLegacyLayoutSelectorUpdate( fieldId );
			app.fieldSizeOptionUpdate( fieldId );
			app.fieldOptionGroupsToggle( fieldId );
		},

		/**
		 * Update legacy layout option.
		 *
		 * @since 1.7.7
		 *
		 * @param {integer} fieldId Field Id.
		 */
		fieldLegacyLayoutSelectorUpdate: function( fieldId ) {

			const $fieldLegacyLayoutSelector = $( `#wpforms-field-option-row-${fieldId}-css .layout-selector-display` );

			let	$fieldLegacyLayoutNotice = $fieldLegacyLayoutSelector.find( '.wpforms-alert-layout' );

			if ( $fieldLegacyLayoutNotice.length ) {
				return;
			}

			// Add "Layouts Have Moved!" notice.
			$fieldLegacyLayoutNotice = $( `
				<div class="wpforms-alert-warning wpforms-alert-layout wpforms-alert wpforms-alert-nomargin">
					<h4>${wpforms_builder.layout.legacy_layout_notice_title}</h4>
					<p>${wpforms_builder.layout.legacy_layout_notice_text}</p>
				</div>
			` );

			$fieldLegacyLayoutSelector.append( $fieldLegacyLayoutNotice );

			// Hide the legacy layout selector.
			$fieldLegacyLayoutSelector.find( '.heading, .layouts' ).addClass( 'wpforms-hidden-strict' );
		},

		/**
		 * Update Field Size option according to the position of the field.
		 *
		 * @since 1.7.7
		 *
		 * @param {integer} fieldId Field Id.
		 */
		fieldSizeOptionUpdate: function( fieldId ) {

			const $field = $( `#wpforms-field-${fieldId}` ),
				type = $field.data( 'field-type' ),
				isFieldInColumn = $field.closest( '.wpforms-field-layout' ).length > 0,
				$fieldSizeOptionRow = $( `#wpforms-field-option-row-${fieldId}-size` ),
				$fieldSizeOptionSelect = $fieldSizeOptionRow.find( 'select' );

			let	$fieldSizeOptionNotice = $fieldSizeOptionRow.find( '.wpforms-notice-field-size' );

			// Do not touch the Field Size option for certain fields.
			if ( [ 'textarea', 'richtext' ].indexOf( type ) > -1 ) {
				return;
			}

			// Add "Field size cannot be changed" notice.
			if ( ! $fieldSizeOptionNotice.length ) {
				$fieldSizeOptionNotice = $( `
					<label class="sub-label wpforms-notice-field-size" title="${wpforms_builder.layout.size_notice_tooltip}">
						${wpforms_builder.layout.size_notice_text}
					</label>
				` );
				$fieldSizeOptionRow.append( $fieldSizeOptionNotice );
			}

			// Toggle field size selector title attribute.
			if ( isFieldInColumn ) {
				$fieldSizeOptionSelect.attr( 'title', wpforms_builder.layout.size_notice_tooltip );
			} else {
				$fieldSizeOptionSelect.attr( 'title', '' );
			}

			// Toggle field size selector `disabled` attribute and notice visibility.
			$fieldSizeOptionSelect.toggleClass( 'wpforms-disabled', isFieldInColumn );
			$fieldSizeOptionNotice.toggleClass( 'wpforms-hidden', ! isFieldInColumn );
		},

		/**
		 * Toggle field option groups visibility.
		 *
		 * @since 1.7.7
		 *
		 * @param {integer} fieldId Field Id.
		 */
		fieldOptionGroupsToggle: function( fieldId ) {

			const $field = $( `#wpforms-field-${fieldId}` ),
				type = $field.data( 'field-type' );

			el.$fieldOptions.toggleClass( 'wpforms-hide-options-groups', type === 'layout' );
		},

		/**
		 * Receive field to column inside the Layout Field.
		 *
		 * @since 1.7.7
		 *
		 * @param {integer} fieldId   Field Id.
		 * @param {integer} position  Field position inside the column.
		 * @param {jQuery}  $sortable Sortable column container.
		 **/
		receiveFieldToColumn: function( fieldId, position, $sortable ) {

			// Remove the field from all the columns.
			app.removeFieldFromColumns( fieldId );

			// Do not need to continue if the field dropped to the main fields wrap.
			if ( ! $sortable || ! $sortable.hasClass( 'wpforms-layout-column' ) ) {
				return;
			}

			app.positionFieldInColumn( fieldId, position, $sortable );
			app.fieldOptionsUpdate( null, fieldId );

			/**
			 * Trigger on the end of the process of receiving field in layouts column.
			 *
			 * @since 1.7.8
			 *
			 * @param {object} data Field data object.
			 */
			el.$builder.trigger( 'wpformsLayoutAfterReceiveFieldToColumn', { fieldId: fieldId, position: position, column: $sortable } );
		},

		/**
		 * Remove field from all the columns in all Layout fields.
		 *
		 * @since 1.7.7
		 *
		 * @param {integer|number} fieldId Field Id.
		 **/
		removeFieldFromColumns: function( fieldId ) {

			fieldId = Number( fieldId );

			el.$builder.find( '.wpforms-field' ).each( function() {

				const $field = $( this );

				if ( $field.data( 'field-type' ) !== 'layout' ) {
					return;
				}

				const layoutFieldId = Number( $field.data( 'field-id' ) );

				let	columnsData = app.getFieldColumnsData( layoutFieldId );

				for ( let i = 0; i < columnsData.length; i++ ) {

					if ( ! Array.isArray( columnsData[ i ].fields ) ) {
						continue;
					}

					// Remove field from the column's list of fields.
					columnsData[ i ].fields = columnsData[ i ].fields.filter( function( id ) {
						return Number( id ) !== fieldId;
					} );
				}

				app.updateFieldColumnsData( layoutFieldId, columnsData );
			} );
		},

		/**
		 * Position field in the column inside the Layout Field.
		 *
		 * @since 1.7.7
		 *
		 * @param {integer|number} fieldId   Field Id.
		 * @param {integer|number} position  The new position of the field inside the column.
		 * @param {jQuery}         $sortable Sortable column container.
		 **/
		positionFieldInColumn: function( fieldId, position, $sortable ) {

			// Proceed only in the column.
			if ( ! $sortable || ! $sortable.hasClass( 'wpforms-layout-column' ) ) {
				return;
			}

			const $layoutField = $sortable.closest( '.wpforms-field-layout' ),
				layoutFieldId = $layoutField.data( 'field-id' ),
				columnIndex = $sortable.index();

			let	columnsData = app.getFieldColumnsData( layoutFieldId );

			// Skip if there is no data of the column.
			if ( ! columnsData || ! columnsData[ columnIndex ] ) {
				return;
			}

			let column = columnsData[ columnIndex ];

			column.fields = Array.isArray( column.fields ) ? column.fields : [];

			fieldId = Number( fieldId );

			// Remove field from the column.
			column.fields = column.fields.filter( function( id ) {
				return Number( id ) !== fieldId;
			} );

			// Add field to the new position in the column.
			column.fields.splice( position, 0, fieldId );

			columnsData[ columnIndex ] = column;

			app.updateFieldColumnsData( layoutFieldId, columnsData );
		},

		/**
		 * Duplicate Layout field.
		 *
		 * @since 1.7.7
		 *
		 * @param {integer} layoutFieldId Layout field Id.
		 **/
		duplicateLayoutField: function( layoutFieldId ) {

			const $field = $( `#wpforms-field-${layoutFieldId}` ),
				preset = $( `#wpforms-field-option-${layoutFieldId} .wpforms-field-option-row-preset input:checked` ).val();

			if ( $field.data( 'field-type' ) !== 'layout' ) {
				return;
			}

			const columnsData = app.getFieldColumnsData( layoutFieldId ),
				newLayoutFieldID = WPFormsBuilder.fieldDuplicateRoutine( layoutFieldId ),
				$newLayoutField = $( `#wpforms-field-${newLayoutFieldID}` ),
				$newLayoutFieldOptions = $( `#wpforms-field-option-${newLayoutFieldID}` ),
				$newLayoutFieldColumn = $newLayoutField.find( '.wpforms-layout-column' );

			let	newColumnsData = JSON.parse( JSON.stringify( columnsData ) );

			// Duplicate preset option value.
			$newLayoutFieldOptions.find( `#wpforms-field-option-${newLayoutFieldID}-preset-${preset}` ).prop( 'checked', true );

			// Delete the fields from the columns.
			$newLayoutField.find( '.wpforms-layout-column .wpforms-field' ).remove();

			// Reset "active column" state.
			$newLayoutField.find( '.wpforms-fields-sortable-default' ).removeClass( 'wpforms-fields-sortable-default' );

			columnsData.forEach( function( column, index ) {

				newColumnsData[ index ].fields = [];

				let $newColumn = $newLayoutFieldColumn.eq( index );

				column.fields.forEach( function( fieldId ) {

					const $field = $( `#wpforms-field-${fieldId}` );

					// Skip if there is no field OR duplicate field button.
					if ( ! $field.length || ! $field.find( '> .wpforms-field-duplicate' ).length ) {
						return;
					}

					const newFieldID = WPFormsBuilder.fieldDuplicateRoutine( fieldId ),
						$newField = $( `#wpforms-field-${newFieldID}` ).detach().removeClass( 'active' ),
						$newFieldOptions = $( `#wpforms-field-option-${newFieldID}` );

					$newColumn.append( $newField );
					$newFieldOptions.hide();
					newColumnsData[ index ].fields.push( newFieldID );
				} );
			} );

			app.updateFieldColumnsData( newLayoutFieldID, newColumnsData );
			app.reorderLayoutFieldsOptions( $newLayoutField );

			// Activate duplicate field to keep consistent behavior with other fields.
			$newLayoutField.trigger( 'click' );

			WPFormsUtils.triggerEvent( el.$builder, 'wpformsFieldDuplicated', [ layoutFieldId, $field, newLayoutFieldID, $newLayoutField ]  );
		},

		/**
		 * Subfield mouseenter event handler.
		 *
		 * @since 1.7.7
		 *
		 * @param {object} e Event object.
		 */
		subfieldMouseEnter: function( e ) {

			$( this ).closest( '.wpforms-field-layout' ).addClass( 'wpforms-field-child-hovered' );
		},

		/**
		 * Subfield mouseleave event handler.
		 *
		 * @since 1.7.7
		 *
		 * @param {object} e Event object.
		 */
		subfieldMouseLeave: function( e ) {

			$( this ).closest( '.wpforms-field-layout' ).removeClass( 'wpforms-field-child-hovered' );
		},

		/**
		 * Init labels in all the Layout fields.
		 *
		 * @since 1.7.7
		 */
		initLabels: function() {

			$( '.wpforms-field-option-layout .wpforms-field-option-row-label input' ).trigger( 'input' );
		},

		/**
		 * Add field to the column of the Layout field.
		 *
		 * @since 1.7.7
		 *
		 * @param {jQuery}                $newField   New field preview.
		 * @param {jQuery}                $newOptions New field options.
		 * @param {integer|number|string} position    New field position.
		 * @param {jQuery}                $column     Sortable column container.
		 */
		fieldAddToColumn: function( $newField, $newOptions, position,  $column ) {

			const $fields = $column.find( '.wpforms-field' );

			if ( position === 'bottom' ) {
				position = $fields.length;
			}

			const $fieldInPosition = $fields.eq( position ),
				fieldInPositionId = $fieldInPosition.data( 'field-id' );

			if ( $fieldInPosition.length ) {
				$fieldInPosition.before( $newField );
			} else {
				$column.append( $newField );
			}

			const $fieldOptionsInPosition = $( `#wpforms-field-option-${fieldInPositionId}` );

			if ( $fieldOptionsInPosition.length ) {
				$fieldOptionsInPosition.before( $newOptions );
			} else {
				el.$fieldOptions.append( $newOptions );
			}

			app.receiveFieldToColumn(
				$newField.data( 'field-id' ),
				position,
				$column
			);

			app.reorderLayoutFieldsOptions(
				$column.closest( '.wpforms-field-layout' )
			);
		},

		/**
		 * Display "This field is not allowed" alert modal.
		 * Event `wpformsFieldMoveRejected` handler.
		 *
		 * @since 1.7.7
		 *
		 * @param {Event}  e       Event.
		 * @param {jQuery} $field  Field object.
		 * @param {object} ui      Sortable ui object.
		 */
		fieldMoveRejected: function( e, $field, ui ) {

			const type = $field.data( 'field-type' );

			let name = type ? $( `#wpforms-add-fields-${type}` ).text() : $field.text();

			$.confirm( {
				title: wpforms_builder.heads_up,
				content: wpforms_builder.layout.not_allowed_alert_text.replace( /%s/g, `<strong>${name}</strong>` ),
				icon: 'fa fa-exclamation-circle',
				type: 'red',
				buttons: {
					confirm: {
						text: wpforms_builder.ok,
						btnClass: 'btn-confirm',
						keys: [ 'enter' ],
					},
				},
			} );
		},

		/**
		 * Display alert modal when trying to add the Layout field in certain cases.
		 * For example "The Layout field cannot be used when Conversational Forms is enabled.".
		 *
		 * Event `wpformsFieldAddDragStart` handler.
		 *
		 * @since 1.7.7
		 *
		 * @param {Event}  e    Event.
		 * @param {string} type SField type.
		 * @param {object} ui   Sortable ui object.
		 */
		fieldCantAddModal: function( e, type, ui ) {

			if ( type !== 'layout' ) {
				return;
			}

			let alertMessage = '';

			// Whether the Conversational Forms is enabled.
			if ( $( '#wpforms-panel-field-settings-conversational_forms_enable' ).is( ':checked' ) ) {
				alertMessage = wpforms_builder.layout.enabled_cf_alert_text;
			}

			if ( alertMessage === '' ) {
				return;
			}

			e.preventDefault();

			$.confirm( {
				title: wpforms_builder.heads_up,
				content: alertMessage,
				icon: 'fa fa-exclamation-circle',
				type: 'orange',
				buttons: {
					confirm: {
						text: wpforms_builder.ok,
						btnClass: 'btn-confirm',
						keys: [ 'enter' ],
					},
				},
			} );
		},

		/**
		 * Reorder fields options of the fields in columns.
		 * It is critical for proper functioning EP and PB fields.
		 *
		 * @since 1.7.7
		 *
		 * @param {jQuery} $layoutField Layout field object.
		 */
		reorderLayoutFieldsOptions: function( $layoutField ) {

			if ( ! $layoutField || ! $layoutField.length || $layoutField.data( 'field-type' ) !== 'layout' ) {
				return;
			}

			const layoutFieldId = $layoutField.data( 'field-id' ),
				columnsData = app.getFieldColumnsData( layoutFieldId );

			let $lastFieldOptions = $( `#wpforms-field-option-${layoutFieldId}` );

			columnsData.forEach( function( column, c ) {

				if ( ! Array.isArray( column.fields ) ) {
					return;
				}

				let fields = column.fields.slice();

				column.fields.forEach( function( fieldId, i ) {

					let $fieldOptions = $( `#wpforms-field-option-${fieldId}` );

					if ( ! $fieldOptions.length ) {

						// Remove not existing field.
						let fieldIndex = fields.indexOf( fieldId );

						if ( fieldIndex !== -1 ) {
							fields.splice( fieldIndex, 1 );
						}

						return;
					}

					$fieldOptions = $fieldOptions.detach();
					$lastFieldOptions.after( $fieldOptions );
					$lastFieldOptions = $fieldOptions;
				} );

				column.fields = fields;

				columnsData[ c ] = column;
			} );

			app.updateFieldColumnsData( layoutFieldId, columnsData );
		},

		/**
		 * Whether the field type is allowed to be in column.
		 *
		 * @since 1.7.7
		 *
		 * @param {string} fieldType Field type to check.
		 *
		 * @returns {boolean} True if allowed.
		 */
		isFieldAllowedInColum: function( fieldType ) {

			return wpforms_builder.layout.not_allowed_fields.indexOf( fieldType ) < 0;
		},

		/**
		 * Event `wpformsBeforeFieldDuplicate` handler.
		 *
		 * @since 1.7.7
		 *
		 * @param {Event}   e       Event.
		 * @param {integer} fieldId Field Id.
		 * @param {jQuery}  $field  Field object.
		 */
		beforeFieldDuplicate: function( e, fieldId, $field ) {

			if ( $field.data( 'field-type' ) !== 'layout' ) {
				return;
			}

			e.preventDefault();
			app.duplicateLayoutField( fieldId );
			WPFormsBuilder.increaseNextFieldIdAjaxRequest();
		},

		/**
		 * Event `wpformsFieldDuplicated` handler.
		 *
		 * @since 1.7.7
		 *
		 * @param {Event}   e          Event.
		 * @param {integer} fieldId    Field Id.
		 * @param {jQuery}  $field     Field object.
		 * @param {integer} newFieldId New field Id.
		 * @param {jQuery}  $newField  New field object.
		 */
		fieldDuplicated: function( e, fieldId, $field, newFieldId, $newField ) {

			// No need to run for the Layout fields.
			if ( $field.data( 'field-type' ) === 'layout' ) {
				return;
			}

			app.positionFieldInColumn(
				newFieldId,
				$newField.index() - 1,
				$newField.parent()
			);
		},
	};

	// Provide access to public functions/properties.
	return app;

}( document, window, jQuery ) );

// Initialize.
WPForms.Admin.Builder.FieldLayout.init();
