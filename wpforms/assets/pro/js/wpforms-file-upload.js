/* global WPFormsUtils */
'use strict';

( function( $ ) {

	/**
	 * All connections are slow by default.
	 *
	 * @since 1.6.2
	 *
	 * @type {boolean|null}
	 */
	var isSlow = null;

	/**
	 * Previously submitted data.
	 *
	 * @since 1.7.1
	 *
	 * @type {Array}
	 */
	var submittedValues = [];

	/**
	 * Default settings for our speed test.
	 *
	 * @since 1.6.2
	 *
	 * @type {{maxTime: number, payloadSize: number}}
	 */
	var speedTestSettings = {
		maxTime: 3000, // Max time (ms) it should take to be considered a 'fast connection'.
		payloadSize: 100 * 1024, // Payload size.
	};

	/**
	 * Create a random payload for the speed test.
	 *
	 * @since 1.6.2
	 *
	 * @returns {string} Random payload.
	 */
	function getPayload() {

		var data = '';

		for ( var i = 0; i < speedTestSettings.payloadSize; ++i ) {
			data += String.fromCharCode( Math.round( Math.random() * 36 + 64 ) );
		}

		return data;
	}

	/**
	 * Run speed tests and flag the clients as slow or not. If a connection
	 * is slow it would let the backend know and the backend most likely
	 * would disable parallel uploads and would set smaller chunk sizes.
	 *
	 * @since 1.6.2
	 *
	 * @param {Function} next Function to call when the speed detection is done.
	 */
	function speedTest( next ) {

		if ( null !== isSlow ) {
			setTimeout( next );
			return;
		}

		var data  = getPayload();
		var start = new Date;

		wp.ajax.post( {
			action: 'wpforms_file_upload_speed_test',
			data: data,
		} ).then( function() {

			var delta = new Date - start;

			isSlow = delta >= speedTestSettings.maxTime;

			next();
		} ).fail( function() {

			isSlow = true;

			next();
		} );
	}

	/**
	 * Toggle loading message above submit button.
	 *
	 * @since 1.5.6
	 *
	 * @param {object} $form jQuery form element.
	 *
	 * @returns {Function} event handler function.
	 */
	function toggleLoadingMessage( $form ) {

		return function() {

			if ( $form.find( '.wpforms-uploading-in-progress-alert' ).length ) {
				return;
			}

			$form.find( '.wpforms-submit-container' )
				.before(
					`<div class="wpforms-error-alert wpforms-uploading-in-progress-alert">
						${window.wpforms_file_upload.loading_message}
					</div>`
				);
		};
	}

	/**
	 * Is a field loading?
	 *
	 * @since 1.7.6
	 *
	 * @param {object} dz Dropzone object.
	 *
	 * @returns {boolean} true if the field is loading.
	 */
	function uploadInProgress( dz ) {

		return dz.loading > 0 || dz.getFilesWithStatus( 'error' ).length > 0;
	}

	/**
	 * Is at least one field loading?
	 *
	 * @since 1.7.6
	 *
	 * @returns {boolean} true if at least one field is loading.
	 */
	function anyUploadsInProgress() {

		var anyUploadsInProgress = false;

		window.wpforms.dropzones.some( function( dz ) {

			if ( uploadInProgress( dz ) ) {
				anyUploadsInProgress = true;

				return true;
			}
		} );

		return anyUploadsInProgress;
	}

	/**
	 * Disable submit button and add overlay.
	 *
	 * @param {object} $form jQuery form element.
	 */
	function disableSubmitButton( $form ) {

		// Find the primary submit button and the "Next" button for multi-page forms.
		let $btn = $form.find( '.wpforms-submit' );
		const $btnNext = $form.find( '.wpforms-page-next:visible' );
		const handler = toggleLoadingMessage( $form ); // Get the handler function for loading message toggle.

		// For multi-pages layout, use the "Next" button instead of the primary submit button.
		if ( $form.find( '.wpforms-page-indicator' ).length !== 0 && $btnNext.length !== 0 ) {
			$btn = $btnNext;
		}

		// Disable the submit button.
		$btn.prop( 'disabled', true );
		WPFormsUtils.triggerEvent( $form, 'wpformsFormSubmitButtonDisable', [ $form, $btn ] );

		// If the overlay is not already added and the button is of type "submit", add an overlay.
		if ( ! $form.find( '.wpforms-submit-overlay' ).length && $btn.attr( 'type' ) === 'submit' ) {

			// Add a container for the overlay and append the overlay element to it.
			$btn.parent().addClass( 'wpforms-submit-overlay-container' );
			$btn.parent().append( '<div class="wpforms-submit-overlay"></div>' );

			// Set the overlay dimensions to match the submit button's size.
			$form.find( '.wpforms-submit-overlay' ).css( {
				width: `${$btn.outerWidth()}px`,
				height: `${$btn.parent().outerHeight()}px`,
			} );

			// Attach the click event to the overlay so that it triggers the handler function.
			$form.find( '.wpforms-submit-overlay' ).on( 'click', handler );
		}
	}

	/**
	 * Disable submit button when we are sending files to the server.
	 *
	 * @since 1.5.6
	 *
	 * @param {object} dz Dropzone object.
	 */
	function toggleSubmit( dz ) { // eslint-disable-line complexity

		var $form = jQuery( dz.element ).closest( 'form' ),
			$btn = $form.find( '.wpforms-submit' ),
			$btnNext = $form.find( '.wpforms-page-next:visible' ),
			handler = toggleLoadingMessage( $form ),
			disabled = uploadInProgress( dz );

		// For multi-pages layout.
		if ( $form.find( '.wpforms-page-indicator' ).length !== 0 && $btnNext.length !== 0 ) {
			$btn = $btnNext;
		}

		const isButtonDisabled = Boolean( $btn.prop( 'disabled' ) ) || $btn.hasClass( 'wpforms-disabled' );

		if ( disabled === isButtonDisabled ) {
			return;
		}

		if ( disabled ) {
			disableSubmitButton( $form );
			return;
		}

		if ( anyUploadsInProgress() ) {
			return;
		}

		$btn.prop( 'disabled', false );
		WPFormsUtils.triggerEvent( $form, 'wpformsFormSubmitButtonRestore', [ $form, $btn ] );
		$form.find( '.wpforms-submit-overlay' ).off( 'click', handler );
		$form.find( '.wpforms-submit-overlay' ).remove();
		$btn.parent().removeClass( 'wpforms-submit-overlay-container' );
		if ( $form.find( '.wpforms-uploading-in-progress-alert' ).length ) {
			$form.find( '.wpforms-uploading-in-progress-alert' ).remove();
		}
	}

	/**
	 * Try to parse JSON or return false.
	 *
	 * @since 1.5.6
	 *
	 * @param {string} str JSON string candidate.
	 *
	 * @returns {*} Parse object or false.
	 */
	function parseJSON( str ) {
		try {
			return JSON.parse( str );
		} catch ( e ) {
			return false;
		}
	}

	/**
	 * Leave only objects with length.
	 *
	 * @since 1.5.6
	 *
	 * @param {object} el Any array.
	 *
	 * @returns {bool} Has length more than 0 or no.
	 */
	function onlyWithLength( el ) {
		return el.length > 0;
	}

	/**
	 * Leave only positive elements.
	 *
	 * @since 1.5.6
	 *
	 * @param {*} el Any element.
	 *
	 * @returns {*} Filter only positive.
	 */
	function onlyPositive( el ) {
		return el;
	}

	/**
	 * Get xhr.
	 *
	 * @since 1.5.6
	 *
	 * @param {object} el Object with xhr property.
	 *
	 * @returns {*} Get XHR.
	 */
	function getXHR( el ) {
		return el.chunkResponse || el.xhr;
	}

	/**
	 * Get response text.
	 *
	 * @since 1.5.6
	 *
	 * @param {object} el Xhr object.
	 *
	 * @returns {object} Response text.
	 */
	function getResponseText( el ) {
		return typeof el === 'string' ? el : el.responseText;
	}

	/**
	 * Get data.
	 *
	 * @since 1.5.6
	 *
	 * @param {object} el Object with data property.
	 *
	 * @returns {object} Data.
	 */
	function getData( el ) {
		return el.data;
	}

	/**
	 * Get value from files.
	 *
	 * @since 1.5.6
	 *
	 * @param {object} files Dropzone files.
	 *
	 * @returns {object} Prepared value.
	 */
	function getValue( files ) {
		return files
			.map( getXHR )
			.filter( onlyPositive )
			.map( getResponseText )
			.filter( onlyWithLength )
			.map( parseJSON )
			.filter( onlyPositive )
			.map( getData );
	}

	/**
	 * Sending event higher order function.
	 *
	 * @since 1.5.6
	 * @since 1.5.6.1 Added special processing of a file that is larger than server's post_max_size.
	 *
	 * @param {object} dz Dropzone object.
	 * @param {object} data Adding data to request.
	 *
	 * @returns {Function} Handler function.
	 */
	function sending( dz, data ) {

		return function( file, xhr, formData ) {

			/*
			 * We should not allow sending a file, that exceeds server post_max_size.
			 * With this "hack" we redefine the default send functionality
			 * to prevent only this object from sending a request at all.
			 * The file that generated that error should be marked as rejected,
			 * so Dropzone will silently ignore it.
			 *
			 * If Chunks are enabled the file size will never exceed (by a PHP constraint) the
			 * postMaxSize. This block shouldn't be removed nonetheless until the "modern" upload is completely
			 * deprecated and removed.
			 */
			if ( file.size > this.dataTransfer.postMaxSize ) {
				xhr.send = function() {};

				file.accepted = false;
				file.processing = false;
				file.status = 'rejected';
				file.previewElement.classList.add( 'dz-error' );
				file.previewElement.classList.add( 'dz-complete' );

				return;
			}

			Object.keys( data ).forEach( function( key ) {
				formData.append( key, data[key] );
			} );
		};
	}

	/**
	 * Convert files to input value.
	 *
	 * @since 1.5.6
	 * @since 1.7.1 Added the dz argument.
	 *
	 * @param {object} files Files list.
	 * @param {object} dz Dropzone object.
	 *
	 * @returns {string} Converted value.
	 */
	function convertFilesToValue( files, dz ) {

		if ( ! submittedValues[ dz.dataTransfer.formId ] || ! submittedValues[ dz.dataTransfer.formId ][ dz.dataTransfer.fieldId ] ) {
			return files.length ? JSON.stringify( files ) : '';
		}

		files.push.apply( files, submittedValues[ dz.dataTransfer.formId ][ dz.dataTransfer.fieldId ] );

		return JSON.stringify( files );
	}

	/**
	 * Get input element.
	 *
	 * @since 1.7.1
	 *
	 * @param {object} dz Dropzone object.
	 *
	 * @returns {jQuery} Hidden input element.
	 */
	function getInput( dz ) {

		return jQuery( dz.element ).parents( '.wpforms-field-file-upload' ).find( 'input[name=' + dz.dataTransfer.name + ']' );
	}

	/**
	 * Update value in input.
	 *
	 * @since 1.5.6
	 *
	 * @param {object} dz Dropzone object.
	 */
	function updateInputValue( dz ) {

		var $input = getInput( dz );

		$input.val( convertFilesToValue( getValue( dz.files ), dz ) ).trigger( 'input' );

		if ( typeof jQuery.fn.valid !== 'undefined' ) {
			$input.valid();
		}
	}

	/**
	 * Complete event higher order function.
	 *
	 * @deprecated 1.6.2
	 *
	 * @since 1.5.6
	 *
	 * @param {object} dz Dropzone object.
	 *
	 * @returns {Function} Handler function.
	 */
	function complete( dz ) {

		return function() {
			dz.loading = dz.loading || 0;
			dz.loading--;
			dz.loading = Math.max( dz.loading - 1, 0 );
			toggleSubmit( dz );
			updateInputValue( dz );
		};
	}

	/**
	 * Add an error message to the current file.
	 *
	 * @since 1.6.2
	 *
	 * @param {object} file         File object.
	 * @param {string} errorMessage Error message
	 */
	function addErrorMessage( file, errorMessage ) {

		if ( file.isErrorNotUploadedDisplayed ) {
			return;
		}

		var span = document.createElement( 'span' );
		span.innerText = errorMessage.toString();
		span.setAttribute( 'data-dz-errormessage', '' );

		file.previewElement.querySelector( '.dz-error-message' ).appendChild( span );
	}

	/**
	 * Confirm the upload to the server.
	 *
	 * The confirmation is needed in order to let PHP know
	 * that all the chunks have been uploaded.
	 *
	 * @since 1.6.2
	 *
	 * @param {object} dz Dropzone object.
	 *
	 * @returns {Function} Handler function.
	 */
	function confirmChunksFinishUpload( dz ) {

		return function confirm( file ) {

			if ( ! file.retries ) {
				file.retries = 0;
			}

			if ( 'error' === file.status ) {
				return;
			}

			/**
			 * Retry finalize function.
			 *
			 * @since 1.6.2
			 */
			function retry() {
				file.retries++;

				if ( file.retries === 3 ) {
					addErrorMessage( file, window.wpforms_file_upload.errors.file_not_uploaded );
					return;
				}

				setTimeout( function() {
					confirm( file );
				}, 5000 * file.retries );
			}

			/**
			 * Fail handler for ajax request.
			 *
			 * @since 1.6.2
			 *
			 * @param {object} response Response from the server
			 */
			function fail( response ) {

				var hasSpecificError =	response.responseJSON &&
										response.responseJSON.success === false &&
										response.responseJSON.data;

				if ( hasSpecificError ) {
					addErrorMessage( file, response.responseJSON.data );
				} else {
					retry();
				}
			}

			/**
			 * Handler for ajax request.
			 *
			 * @since 1.6.2
			 *
			 * @param {object} response Response from the server
			 */
			function complete( response ) {

				file.chunkResponse = JSON.stringify( { data: response } );
				dz.loading = dz.loading || 0;
				dz.loading--;
				dz.loading = Math.max( dz.loading, 0 );

				toggleSubmit( dz );
				updateInputValue( dz );
			}

			wp.ajax.post( jQuery.extend(
				{
					action: 'wpforms_file_chunks_uploaded',
					form_id: dz.dataTransfer.formId,
					field_id: dz.dataTransfer.fieldId,
					name: file.name,
				},
				dz.options.params.call( dz, null, null, {file: file, index: 0} )
			) ).then( complete ).fail( fail );

			// Move to upload the next file, if any.
			dz.processQueue();
		};
	}

	/**
	 * Toggle showing empty message.
	 *
	 * @since 1.5.6
	 *
	 * @param {object} dz Dropzone object.
	 */
	function toggleMessage( dz ) {

		setTimeout( function() {
			var validFiles = dz.files.filter( function( file ) {
				return file.accepted;
			} );

			if ( validFiles.length >= dz.options.maxFiles ) {
				dz.element.querySelector( '.dz-message' ).classList.add( 'hide' );
			} else {
				dz.element.querySelector( '.dz-message' ).classList.remove( 'hide' );
			}
		}, 0 );
	}

	/**
	 * Toggle error message if total size more than limit.
	 * Runs for each file.
	 *
	 * @since 1.5.6
	 *
	 * @param {object} file Current file.
	 * @param {object} dz   Dropzone object.
	 */
	function validatePostMaxSizeError( file, dz ) {

		setTimeout( function() {
			if ( file.size >= dz.dataTransfer.postMaxSize ) {
				var errorMessage = window.wpforms_file_upload.errors.post_max_size;
				if ( ! file.isErrorNotUploadedDisplayed ) {
					file.isErrorNotUploadedDisplayed = true;
					errorMessage = window.wpforms_file_upload.errors.file_not_uploaded + ' ' + errorMessage;
					addErrorMessage( file, errorMessage );
				}
			}
		}, 1 );
	}

	/**
	 * Start File Upload.
	 *
	 * This would do the initial request to start a file upload. No chunk
	 * is uploaded at this stage, instead all the information related to the
	 * file are send to the server waiting for an authorization.
	 *
	 * If the server authorizes the client would start uploading the chunks.
	 *
	 * @since 1.6.2
	 *
	 * @param {object} dz   Dropzone object.
	 * @param {object} file Current file.
	 */
	function initFileUpload( dz, file ) {

		wp.ajax.post( jQuery.extend(
			{
				action : 'wpforms_upload_chunk_init',
				form_id: dz.dataTransfer.formId,
				field_id: dz.dataTransfer.fieldId,
				name: file.name,
				slow: isSlow,
			},
			dz.options.params.call( dz, null, null, {file: file, index: 0} )
		) ).then( function( response ) {

			// File upload has been authorized.

			for ( var key in response ) {
				dz.options[ key ] = response[ key ];
			}

			if ( response.dzchunksize ) {
				dz.options.chunkSize = parseInt( response.dzchunksize, 10 );
				file.upload.totalChunkCount = Math.ceil( file.size / dz.options.chunkSize );
			}

			dz.processQueue();
		} ).fail( function( response ) {

			file.status = 'error';

			if ( ! file.xhr ) {
				const field = dz.element.closest( '.wpforms-field' );
				const hiddenInput = field.querySelector( '.dropzone-input' );
				const errorMessage = window.wpforms_file_upload.errors.file_not_uploaded + ' ' + window.wpforms_file_upload.errors.default_error;

				file.previewElement.classList.add( 'dz-processing', 'dz-error', 'dz-complete' );
				hiddenInput.classList.add( 'wpforms-error' );
				field.classList.add( 'wpforms-has-error' );
				addErrorMessage( file, errorMessage );
			}

			dz.processQueue();
		} );
	}

	/**
	 * Validate the file when it was added in the dropzone.
	 *
	 * @since 1.5.6
	 *
	 * @param {object} dz Dropzone object.
	 *
	 * @returns {Function} Handler function.
	 */
	function addedFile( dz ) {

		return function( file ) {

			if ( file.size >= dz.dataTransfer.postMaxSize ) {
				validatePostMaxSizeError( file, dz );
			} else {
				speedTest( function() {
					initFileUpload( dz, file );
				} );
			}

			dz.loading = dz.loading || 0;
			dz.loading++;
			toggleSubmit( dz );

			toggleMessage( dz );
		};
	}

	/**
	 * Send an AJAX request to remove file from the server.
	 *
	 * @since 1.5.6
	 *
	 * @param {string} file File name.
	 * @param {object} dz Dropzone object.
	 */
	function removeFromServer( file, dz ) {

		wp.ajax.post( {
			action: 'wpforms_remove_file',
			file: file,
			form_id: dz.dataTransfer.formId,
			field_id: dz.dataTransfer.fieldId,
		} );
	}

	/**
	 * Init the file removal on server when user removed it on front-end.
	 *
	 * @since 1.5.6
	 *
	 * @param {object} dz Dropzone object.
	 *
	 * @returns {Function} Handler function.
	 */
	function removedFile( dz ) {

		return function( file ) {
			toggleMessage( dz );

			var json = file.chunkResponse || ( file.xhr || {} ).responseText;

			if ( json ) {
				var object = parseJSON( json );

				if ( object && object.data && object.data.file ) {
					removeFromServer( object.data.file, dz );
				}
			}

			// Remove submitted value.
			if ( Object.prototype.hasOwnProperty.call( file, 'isDefault' ) && file.isDefault ) {
				submittedValues[ dz.dataTransfer.formId ][ dz.dataTransfer.fieldId ].splice( file.index, 1 );
				dz.options.maxFiles++;
				removeFromServer( file.file, dz );
			}

			updateInputValue( dz );

			dz.loading = dz.loading || 0;
			dz.loading--;
			dz.loading = Math.max( dz.loading, 0 );

			toggleSubmit( dz );

			const numErrors = dz.element.querySelectorAll( '.dz-preview.dz-error' ).length;

			if ( numErrors === 0 ) {
				dz.element.classList.remove( 'wpforms-error' );
				dz.element.closest( '.wpforms-field' ).classList.remove( 'wpforms-has-error' );
			}
		};
	}

	/**
	 * Process any error that was fired per each file.
	 * There might be several errors per file, in that case - display "not uploaded" text only once.
	 *
	 * @since 1.5.6.1
	 *
	 * @param {object} dz Dropzone object.
	 *
	 * @returns {Function} Handler function.
	 */
	function error( dz ) {

		return function( file, errorMessage ) {

			if ( file.isErrorNotUploadedDisplayed ) {
				return;
			}

			if ( typeof errorMessage === 'object' ) {
				errorMessage = Object.prototype.hasOwnProperty.call( errorMessage, 'data' ) && typeof errorMessage.data === 'string' ? errorMessage.data : '';
			}

			errorMessage = errorMessage !== '0' ? errorMessage : '';

			file.isErrorNotUploadedDisplayed = true;
			file.previewElement.querySelectorAll( '[data-dz-errormessage]' )[0].textContent = window.wpforms_file_upload.errors.file_not_uploaded + ' ' + errorMessage;
			dz.element.classList.add( 'wpforms-error' );
			dz.element.closest( '.wpforms-field' ).classList.add( 'wpforms-has-error' );
		};
	}

	/**
	 * Preset previously submitted files to the dropzone.
	 *
	 * @since 1.7.1
	 *
	 * @param {object} dz Dropzone object.
	 */
	function presetSubmittedData( dz ) {

		var files = parseJSON( getInput( dz ).val() );

		if ( ! files || ! files.length ) {
			return;
		}

		submittedValues[dz.dataTransfer.formId] = [];

		// We do deep cloning an object to be sure that data is passed without links.
		submittedValues[dz.dataTransfer.formId][dz.dataTransfer.fieldId] = JSON.parse( JSON.stringify( files ) );

		files.forEach( function( file, index ) {

			file.isDefault = true;
			file.index = index;

			if ( file.type.match( /image.*/ ) ) {
				dz.displayExistingFile( file, file.url );

				return;
			}

			dz.emit( 'addedfile', file );
			dz.emit( 'complete', file );
		} );

		dz.options.maxFiles = dz.options.maxFiles - files.length;
	}

	/**
	 * Dropzone.js init for each field.
	 *
	 * @since 1.5.6
	 *
	 * @param {object} $el WPForms uploader DOM element.
	 *
	 * @returns {object} Dropzone object.
	 */
	function dropZoneInit( $el ) {

		if ( $el.dropzone ) {
			return $el.dropzone;
		}

		var formId = parseInt( $el.dataset.formId, 10 );
		var fieldId = parseInt( $el.dataset.fieldId, 10 ) || 0;
		var maxFiles = parseInt( $el.dataset.maxFileNumber, 10 );

		var acceptedFiles = $el.dataset.extensions.split( ',' ).map( function( el ) {
			return '.' + el;
		} ).join( ',' );

		// Configure and modify Dropzone library.
		var dz = new window.Dropzone( $el, {
			url: window.wpforms_file_upload.url,
			addRemoveLinks: true,
			chunking: true,
			forceChunking: true,
			retryChunks: true,
			chunkSize: parseInt( $el.dataset.fileChunkSize, 10 ),
			paramName: $el.dataset.inputName,
			parallelChunkUploads: !! ( $el.dataset.parallelUploads || '' ).match( /^true$/i ),
			parallelUploads: parseInt( $el.dataset.maxParallelUploads, 10 ),
			autoProcessQueue: false,
			maxFilesize: ( parseInt( $el.dataset.maxSize, 10 ) / ( 1024 * 1024 ) ).toFixed( 2 ),
			maxFiles: maxFiles,
			acceptedFiles: acceptedFiles,
			dictMaxFilesExceeded: window.wpforms_file_upload.errors.file_limit.replace( '{fileLimit}', maxFiles ),
			dictInvalidFileType: window.wpforms_file_upload.errors.file_extension,
			dictFileTooBig: window.wpforms_file_upload.errors.file_size,
		} );

		// Custom variables.
		dz.dataTransfer = {
			postMaxSize: $el.dataset.maxSize,
			name: $el.dataset.inputName,
			formId: formId,
			fieldId: fieldId,
		};

		presetSubmittedData( dz );

		// Process events.
		dz.on( 'sending', sending( dz, {
			action: 'wpforms_upload_chunk',
			form_id: formId,
			field_id: fieldId,
		} ) );
		dz.on( 'addedfile', addedFile( dz ) );
		dz.on( 'removedfile', removedFile( dz ) );
		dz.on( 'complete', confirmChunksFinishUpload( dz ) );
		dz.on( 'error', error( dz ) );

		return dz;
	}

	/**
	 * Hidden Dropzone input focus event handler.
	 *
	 * @since 1.8.1
	 */
	function dropzoneInputFocus() {

		$( this ).prev( '.wpforms-uploader' ).addClass( 'wpforms-focus' );
	}

	/**
	 * Hidden Dropzone input blur event handler.
	 *
	 * @since 1.8.1
	 */
	function dropzoneInputBlur() {

		$( this ).prev( '.wpforms-uploader' ).removeClass( 'wpforms-focus' );
	}

	/**
	 * Hidden Dropzone input blur event handler.
	 *
	 * @since 1.8.1
	 *
	 * @param {object} e Event object.
	 */
	function dropzoneInputKeypress( e ) {

		e.preventDefault();

		if ( e.keyCode !== 13 ) {
			return;
		}

		$( this ).prev( '.wpforms-uploader' ).trigger( 'click' );
	}

	/**
	 * Hidden Dropzone input blur event handler.
	 *
	 * @since 1.8.1
	 */
	function dropzoneClick() {

		$( this ).next( '.dropzone-input' ).trigger( 'focus' );
	}

	/**
	 * Classic File upload success callback to determine if all files are uploaded.
	 *
	 * @since 1.8.3
	 *
	 * @param {Event} e Event.
	 * @param {jQuery} $form Form.
	 */
	function combinedUploadsSizeOk( e, $form ) {

		if ( anyUploadsInProgress() ) {
			disableSubmitButton( $form );
		}
	}

	/**
	 * Events.
	 *
	 * @since 1.8.1
	 */
	function events() {

		$( '.dropzone-input' )
			.on( 'focus', dropzoneInputFocus )
			.on( 'blur', dropzoneInputBlur )
			.on( 'keypress', dropzoneInputKeypress );

		$( '.wpforms-uploader' )
			.on( 'click', dropzoneClick );

		$( 'form.wpforms-form' )
			.on( 'wpformsCombinedUploadsSizeOk', combinedUploadsSizeOk );
	}

	/**
	 * DOMContentLoaded handler.
	 *
	 * @since 1.5.6
	 */
	function ready() {

		window.wpforms = window.wpforms || {};
		window.wpforms.dropzones = [].slice.call( document.querySelectorAll( '.wpforms-uploader' ) ).map( dropZoneInit );

		events();
	}

	/**
	 * Modern File Upload engine.
	 *
	 * @since 1.6.0
	 */
	var wpformsModernFileUpload = {

		/**
		 * Start the initialization.
		 *
		 * @since 1.6.0
		 */
		init: function() {

			if ( document.readyState === 'loading' ) {
				document.addEventListener( 'DOMContentLoaded', ready );
			} else {
				ready();
			}
		},
	};

	// Call init and save in global variable.
	wpformsModernFileUpload.init();
	window.wpformsModernFileUpload = wpformsModernFileUpload;

}( jQuery ) );
