/* global tinymce, tinyMCE, tinyMCEPreInit, wpforms_settings, wpforms */

/**
 * Rich Text field.
 *
 * @since 1.7.0
 */

'use strict';

var WPFormsRichTextField = window.WPFormsRichTextField || ( function( document, window, $ ) {

	/**
	 * Private functions and properties.
	 *
	 * @since 1.7.0
	 *
	 * @type {object}
	 */
	var vars = {
		mediaPostIdUpdateEvent: false,
	};

	/**
	 * Public functions and properties.
	 *
	 * @since 1.7.0
	 *
	 * @type {object}
	 */
	var app = {

		/**
		 * Start the engine.
		 *
		 * @since 1.7.0
		 */
		init: function() {

			$( document ).on( 'wpformsReady', app.customizeRichTextField );
		},

		/**
		 * Customize Rich Text field.
		 *
		 * @since 1.7.0
		 */
		customizeRichTextField: function() {

			var $document = $( document );

			$document.on( 'tinymce-editor-setup', function( event, editor ) {

				// Add media button.
				app.addMediaButton( editor );

				// Validate hidden editor textarea on keyup.
				editor.on( 'keyup', function() {

					app.validateRichTextField( editor );
				} );

				editor.on( 'focus', function( e ) {

					$( e.target.editorContainer ).closest( '.wp-editor-wrap' ).addClass( 'wpforms-focused' );
				} );

				editor.on( 'blur', function( e ) {

					$( e.target.editorContainer ).closest( '.wp-editor-wrap' ).removeClass( 'wpforms-focused' );
				} );
			} );

			// Validate on mutation (insert image or any other changes).
			$document.on( 'wpformsRichTextContentChange', function( event, mutation, editor ) {

				app.validateRichTextField( editor );
				app.enableAddMediaButtons( mutation );
			} );

			// Init each field.
			$document.on( 'tinymce-editor-init', function( event, editor ) {

				const docStyle = editor.getDoc().body.style;
				const $body = $( 'body' );

				// Inherit body text font family.
				docStyle.fontFamily = $body.css( 'font-family' );
				docStyle.background = 'transparent';

				app.initEditorModernMarkupMode( editor );
				app.mediaPostIdUpdate();
				app.observeEditorChanges( editor );
				app.cleanImages( editor );

				$document.trigger( 'wpformsRichTextEditorInit', [ editor ] );
			} );

			// Re-initialize tinyMCE in Elementor's popups.
			window.addEventListener( 'elementor/popup/show', function( event ) {

				app.reInitRichTextFields( event.detail.instance.$element );
			} );

			// Set `required` property for each of the hidden editor textarea.
			$( 'textarea.wp-editor-area' ).each( function() {

				var $this = $( this );

				if ( $this.hasClass( 'wpforms-field-required' ) ) {
					$this.prop( 'required', true );
				}
			} );

			// Closing media modal on click.
			$document.on( 'click', '.media-modal-close, .media-modal-backdrop', app.enableAddMediaButtons );

			// Closing media modal via ESC key.
			if ( typeof wp !== 'undefined' && typeof wp.media === 'function' ) {
				wp.media.view.Modal.prototype.on( 'escape', function() {
					app.enableAddMediaButtons( 'escapeEvent' );
				} );
			}

			$document.on( 'click', '.switch-html', function() {

				const $wrap = $( this ).closest( '.wp-editor-wrap' );

				setTimeout( function() {
					$wrap.find( '.wp-editor-area' ).trigger( 'focus' );
					$wrap.addClass( 'wpforms-focused' );
				}, 0 );
			} );

			$document.on( 'click', '.switch-tmce', function( e ) {

				const $wrap = $( this ).closest( '.wp-editor-wrap' ),
					textareaId = $wrap.find( '.wp-editor-area' ).attr( 'id' );

				const editor = tinyMCE.get( textareaId );

				if ( editor ) {
					editor.focus();
				}
			} );

			$document.on( 'focus', '.wp-editor-area', function() {

				$( this ).closest( '.wp-editor-wrap' ).addClass( 'wpforms-focused' );
			} );

			$document.on( 'blur', '.wp-editor-area', function( e ) {

				$( this ).closest( '.wp-editor-wrap' ).removeClass( 'wpforms-focused' );
			} );
		},

		/**
		 * Replace special characters in image attributes.
		 *
		 * @since 1.8.3
		 * @param {object} editor TinyMCE editor instance.
		 */
		cleanImages: function( editor ) {

			// Get TinyMCE content in raw format.
			const content = editor.getContent( { format: 'raw' } );

			// Create a temporary element to manipulate the content.
			const imageDiv = document.createElement( 'div' );

			// Set the content to the temporary element.
			imageDiv.innerHTML = content;

			// Find all the images in the content.
			const images = imageDiv.querySelectorAll( 'img' );

			// Loop through all the images.
			for ( let i = 0; i < images.length; i++ ) {

				// Replace wrong quote characters.
				images[ i ].outerHTML = images[ i ].outerHTML.replace( /"”|”"|"″|″"/g, '"' );
			}

			// Send clean image back to TinyMCE.
			editor.setContent( imageDiv.innerHTML );
		},


		/**
		 * Add media button for WordPress 4.9.
		 *
		 * @since 1.7.0
		 *
		 * @param {object} editor TinyMCE editor instance.
		 */
		addMediaButton: function( editor ) {

			if ( wpforms_settings.richtext_add_media_button ) {
				editor.addButton( 'wp_add_media', {
					tooltip: 'Add Media',
					icon: 'dashicon dashicons-admin-media',
					cmd: 'WP_Medialib',
				} );
			}
		},

		/**
		 * Enable Add Media buttons.
		 *
		 * @since 1.7.0
		 *
		 * @param {object|string} mutation Mutation observer's record or event object.
		 */
		enableAddMediaButtons: function( mutation ) {

			var isEscapeEvent = mutation === 'escapeEvent';

			if ( ! isEscapeEvent && ! app.isCloseEvent( mutation ) && ! app.isMutationImage( mutation ) ) {
				return;
			}

			$( '.mce-btn-group button i.dashicons-admin-media' ).closest( '.mce-btn' ).removeClass( 'mce-btn-disabled' );
		},

		/**
		 * Is it the close media library event?
		 *
		 * @since 1.7.0
		 *
		 * @param {object} mutation Mutation observer's record or event object.
		 *
		 * @returns {boolean} True if is the close event.
		 */
		isCloseEvent: function( mutation ) {

			return typeof mutation.target !== 'undefined' && (
				mutation.target.classList.contains( 'media-modal-icon' ) ||
				mutation.target.classList.contains( 'media-modal-backdrop' )
			);
		},

		/**
		 * Is it not mutation event?
		 *
		 * @since 1.7.0
		 *
		 * @param {object} mutation Mutation observer's record or event object.
		 *
		 * @returns {boolean} True if isn't mutation event.
		 */
		isMutationImage: function( mutation ) {

			if ( typeof mutation.addedNodes === 'undefined' || typeof mutation.addedNodes[0] === 'undefined' ) {
				return false;
			}

			var isMutationImage = false;

			mutation.addedNodes.forEach( function( node ) {

				if ( node.tagName === 'IMG' ) {
					isMutationImage = true;

					return false;
				}

				if ( node.tagName === 'A' && node.querySelector( 'img' ) ) {
					isMutationImage = true;

					return false;
				}
			} );

			return isMutationImage;
		},

		/**
		 * Disable Add Media buttons.
		 *
		 * @since 1.7.0
		 */
		disableAddMediaButtons: function() {

			$( '.mce-btn-group button i.dashicons-admin-media' ).closest( '.mce-btn' ).addClass( 'mce-btn-disabled' );
		},

		/**
		 * Update Fake Post ID according to the Field ID.
		 *
		 * @since 1.7.0
		 */
		mediaPostIdUpdate: function() {

			if ( vars.mediaPostIdUpdateEvent ) {
				return;
			}

			$( '.wpforms-field-richtext-media-enabled .mce-toolbar .mce-btn' ).on( 'click', function( e ) {

				var $this = $( e.target );

				if ( ! $this.hasClass( 'dashicons-admin-media' ) && $this.find( '.dashicons-admin-media' ).length === 0 ) {
					return;
				}

				var formId = $this.closest( 'form' ).data( 'formid' ),
					fieldId = $this.closest( '.wpforms-field-richtext' ).data( 'field-id' );

				// Replace the digital parts with the current form and field IDs.
				wp.media.model.settings.post.id = 'wpforms-' + formId + '-field_' + fieldId;

				app.disableAddMediaButtons();
			} );

			vars.mediaPostIdUpdateEvent = true;
		},

		/**
		 * Observe changes inside editor's iframe.
		 *
		 * @since 1.7.0
		 *
		 * @param {object} editor TinyMCE editor instance.
		 */
		observeEditorChanges: function( editor ) {

			// Observe changes inside editor's iframe.
			var observer = new MutationObserver( function( mutationsList, observer ) {

				for ( var key in mutationsList ) {

					if ( mutationsList[ key ].type === 'childList' ) {
						$( document ).trigger( 'wpformsRichTextContentChange', [ mutationsList[ key ], editor ] );
					}
				}
			} );

			observer.observe(
				editor.iframeElement.contentWindow.document.body,
				{
					childList: true,
					subtree: true,
					attributes: true,
				}
			);
		},

		/**
		 * Validate Rich Text field.
		 *
		 * @since 1.7.0
		 *
		 * @param {object} editor TinyMCE editor instance.
		 */
		validateRichTextField: function( editor ) {

			if ( ! editor || ! $( editor.iframeElement ).closest( 'form' ).data( 'validator' ) ) {
				return;
			}

			var $textarea = $( '#' + editor.id );

			// We should save and validate if only the editor's content has the real changes.
			if ( editor.getContent() === $textarea.val() ) {
				return;
			}

			editor.save();

			$textarea.valid();
		},

		/**
		 * Re-initialize tinyMCEs in given form (container).
		 *
		 * @since 1.7.0
		 *
		 * @param {jQuery} $form Form container.
		 */
		reInitRichTextFields: function( $form ) {

			$form.find( '.wp-editor-area' ).each( function() {

				var id = $( this ).attr( 'id' );

				if ( tinymce.get( id ) ) {

					// Remove existing editor.
					tinyMCE.execCommand( 'mceRemoveEditor', false, id );
				}

				window.quicktags( tinyMCEPreInit.qtInit[ id ] );
				$( '#' + id ).css( 'visibility', 'initial' );

				tinymce.init( tinyMCEPreInit.mceInit[ id ] );
			} );
		},

		/**
		 * Initialize tinyMCE in Modern Markup mode.
		 *
		 * @since 1.8.1
		 *
		 * @param {object} editor TinyMCE editor instance.
		 */
		initEditorModernMarkupMode: function( editor ) {

			if ( ! wpforms.isModernMarkupEnabled() || window.WPFormsEditEntry || ! window.WPForms.FrontendModern ) {
				return;
			}

			const docStyle    = editor.getDoc().body.style;
			const $el         = $( editor.getElement() );
			const $field      = $el.closest( '.wpforms-field' );
			const $form       = $el.closest( '.wpforms-form' );
			const cssVars     = window.WPForms.FrontendModern.getCssVars( $form );
			const inputHeight = cssVars['field-size-input-height'] ? cssVars['field-size-input-height'].replace( 'px', '' ) : 43;
			const sizeK      = {
				'small' : 1.80,
				'medium': 2.79,
				'large' : 5.12,
			};

			let fieldSize = 'medium';
			fieldSize = $field.hasClass( 'wpforms-field-small' ) ? 'small' : fieldSize;
			fieldSize = $field.hasClass( 'wpforms-field-large' ) ? 'large' : fieldSize;

			const width  = editor.getWin().clientWidth;
			const height = inputHeight * sizeK[ fieldSize ];
			editor.theme.resizeTo( width, height );

			docStyle.color    = cssVars['field-text-color'];
			docStyle.fontSize = cssVars['field-size-font-size'];
		},
	};

	// Provide access to public functions/properties.
	return app;

}( document, window, jQuery ) );

WPFormsRichTextField.init();
