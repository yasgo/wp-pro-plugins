/* global wpCookies */

'use strict';

/**
 * Print Entry page.
 *
 * @since 1.8.1
 */
const WPFormsPrintEntryPage = window.WPFormsPrintEntryPage || ( function( document, window, $ ) {

	/**
	 * Public functions and properties.
	 *
	 * @since 1.8.1
	 *
	 * @type {object}
	 */
	const app = {

		/**
		 * DOM elements, selectors, and class names.
		 *
		 * @since 1.8.1
		 */
		vars: {},

		/**
		 * Cache object.
		 *
		 * @since 1.8.1
		 */
		cache: {

			/**
			 * Get the cache cookie.
			 *
			 * @since 1.8.1
			 *
			 * @returns {object} Entry Print page settings.
			 */
			getCookie: function() {

				return JSON.parse( wpCookies.get( 'wpforms_entry_print' ) ) || {};
			},

			/**
			 * Save an option to cookie.
			 *
			 * @since 1.8.1
			 *
			 * @param {string} mode Page mode.
			 * @param {boolean} isActive Is the option active or not?
			 */
			saveCookie: function( mode, isActive ) {

				const storage = app.cache.getCookie();

				storage[mode] = isActive;

				wpCookies.set( 'wpforms_entry_print', JSON.stringify( storage ) );
			},
		},

		/**
		 * Start the engine.
		 *
		 * @since 1.8.1
		 */
		init: function() {

			$( window ).on( 'load', function() {

				// in case of jQuery 3.+ we need to wait for an `ready` event first.
				if ( typeof $.ready.then === 'function' ) {
					$.ready.then( app.load );
				} else {
					app.load();
				}
			} );
		},

		/**
		 * Window load.
		 *
		 * @since 1.8.1
		 */
		load: function() {

			app.vars = {
				$body: $( 'body' ),
				$page: $( '.print-preview' ),
				$modeToggles: $( '.toggle-mode' ),
				$richTextFields: $( '.wpforms-entry-field-value-richtext' ),
				printButtonSelector: '.print',
				closeButtonSelector: '.close-window',
				settingsButtonSelector: '.button-settings',
				settingsMenuSelector: '.actions',
				toggleModeSelector: '.toggle-mode',
				toggleSelector: '.switch',
				activeClass: 'active',
			};

			app.vars.$modeToggles.each( app.presetSettingsValues );
			app.vars.$richTextFields.each( app.loadRichText );

			app.bindEvents();
		},

		/**
		 * Bind events.
		 *
		 * @since 1.8.1
		 */
		bindEvents: function() {

			$( document )
				.on( 'click', app.vars.printButtonSelector, app.print )
				.on( 'click', app.vars.closeButtonSelector, app.close )
				.on( 'click', app.vars.settingsButtonSelector, app.toggleSettings )
				.on( 'click', app.vars.toggleModeSelector, app.toggleMode );
		},

		/**
		 * Turn on/off mode in settings.
		 *
		 * @since 1.8.1
		 */
		toggleMode: function() {

			const $this = $( this );
			const mode = $this.data( 'mode' );
			const $switch = $this.find( app.vars.toggleSelector );
			const isActive = ! $switch.hasClass( app.vars.activeClass );

			$switch.toggleClass( app.vars.activeClass );

			if ( mode === 'compact' ) {
				app.disableMode( 'maintain-layout' );
			}

			if ( mode === 'maintain-layout' ) {
				app.disableMode( 'compact' );
			}

			app.vars.$page.toggleClass( 'wpforms-preview-mode-' + mode );

			app.cache.saveCookie( mode, isActive );
		},

		/**
		 * Turn off the mode.
		 *
		 * @since 1.8.1
		 *
		 * @param {string} mode Mode.
		 */
		disableMode: function( mode ) {

			$( app.vars.toggleModeSelector + '[data-mode="' + mode + '"]' ).find( app.vars.toggleSelector ).removeClass( app.vars.activeClass );

			app.vars.$page.removeClass( app.prepareModeClass( mode ) );

			app.cache.saveCookie( mode, false );
		},

		/**
		 * Preset setting values.
		 *
		 * @since 1.8.1
		 */
		presetSettingsValues: function() {

			const $this = $( this );
			const mode = $this.data( 'mode' );
			const $switch = $this.find( app.vars.toggleSelector );
			const storage = app.cache.getCookie();

			if ( Object.prototype.hasOwnProperty.call( storage, mode ) && storage[mode] ) {
				$switch.addClass( app.vars.activeClass );

				app.vars.$page.addClass( app.prepareModeClass( mode ) );
			}
		},

		/**
		 * Prepare mode class.
		 *
		 * @since 1.8.1
		 *
		 * @param {string} mode Mode.
		 *
		 * @returns {string} Mode class.
		 */
		prepareModeClass: function( mode ) {

			return 'wpforms-preview-mode-' + mode;
		},

		/**
		 * Open print modal.
		 *
		 * @since 1.8.1
		 *
		 * @param {Event} e Event.
		 */
		print: function( e ) {

			e.preventDefault();
			window.print();
		},

		/**
		 * Close the print preview window.
		 *
		 * @since 1.8.1
		 *
		 * @param {Event} e Event.
		 */
		close: function( e ) {

			e.preventDefault();
			window.close();
		},

		/**
		 * Load RichText fields.
		 *
		 * @since 1.8.1
		 */
		loadRichText: function() {

			const iframe = this;
			const $iframe = $( this );

			$iframe.on( 'load', function() {

				app.iframeStyles( iframe );
				app.updateRichTextIframeSize( iframe );
				app.modifyRichTextLinks( iframe );
			} );

			$iframe.attr( 'src', $iframe.data( 'src' ) );
		},

		/**
		 * Update RichText iframe size.
		 *
		 * @since 1.8.1
		 *
		 * @param {HTMLElement} iframe RichText iframe.
		 */
		updateRichTextIframeSize: function( iframe ) {

			if ( ! iframe || ! iframe.contentWindow ) {
				return;
			}

			const doc = iframe.contentWindow.document.documentElement || false;

			if ( ! doc ) {
				return;
			}

			const height = doc.querySelector( '.mce-content-body' ).scrollHeight;

			iframe.style.height = height + 'px';
		},

		/**
		 * Add `target` and `rel` attributes to all links inside iframe.
		 *
		 * @since 1.8.1
		 *
		 * @param {HTMLElement} iframe RichText iframe.
		 */
		modifyRichTextLinks: function( iframe ) {

			$( iframe ).contents().find( 'a' ).attr( {
				'target': '_blank',
				'rel': 'noopener',
			} );
		},

		/**
		 * Add styles to an iframe.
		 *
		 * @since 1.8.1
		 *
		 * @param {HTMLIFrameElement} iframe Iframe element.
		 */
		iframeStyles: function( iframe ) {

			const doc = iframe.contentWindow.document;
			const head = doc.querySelector( 'head' );
			const style = doc.createElement( 'style' );
			const fontFamily = app.vars.$body.css( 'font-family' );
			const fontSize = app.vars.$body.css( 'font-size' );
			const lineHeight = app.vars.$body.css( 'line-height' );

			style.setAttribute( 'type', 'text/css' );
			style.innerHTML = 'body.mce-content-body {' +
				'	margin: 0 !important;' +
				'	background-color: transparent !important;' +
				'	font-family: ' + fontFamily + ';' +
				'	font-size: ' + fontSize + ';' +
				'	line-height: ' + lineHeight + ';' +
				'}' +
				'*:first-child {' +
				'	margin-top: 0' +
				'}' +
				'*:last-child {' +
				'	margin-bottom: 0' +
				'}' +
				'ul, ol {' +
				'	padding-inline-start: 30px;' +
				'}' +
				'li {' +
				'	list-style-position: outside;' +
				'}' +
				'pre {' +
				'	white-space: pre !important;' +
				'	overflow-x: auto !important;' +
				'}' +
				'a,' +
				'img {' +
				'	display: inline-block;' +
				'}';

			head.appendChild( style );
		},

		/**
		 * Toggle settings menu.
		 *
		 * @since 1.8.1
		 *
		 * @param {Event} e Event.
		 */
		toggleSettings: function( e ) {

			e.preventDefault();
			$( this ).toggleClass( app.vars.activeClass );
			$( app.vars.settingsMenuSelector ).toggleClass( app.vars.activeClass );
		},
	};

	return app;

}( document, window, jQuery ) );

WPFormsPrintEntryPage.init();
