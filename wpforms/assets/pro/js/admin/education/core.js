/* global wpforms_education, WPFormsAdmin, wpforms_admin, wpforms_builder */
/**
 * WPForms Education core for Pro.
 *
 * @since 1.6.6
 */

'use strict';

var WPFormsEducation = window.WPFormsEducation || {};

WPFormsEducation.proCore = window.WPFormsEducation.proCore || ( function( document, window, $ ) {

	/**
	 * Public functions and properties.
	 *
	 * @since 1.6.6
	 *
	 * @type {object}
	 */
	var app = {

		/**
		 * Start the engine.
		 *
		 * @since 1.6.6
		 */
		init: function() {

			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 1.6.6
		 */
		ready: function() {

			app.events();
		},

		/**
		 * Register JS events.
		 *
		 * @since 1.6.6
		 */
		events: function() {

			app.openModalButtonClick();
			app.activateButtonClick();
		},

		/**
		 * Open education modal.
		 *
		 * @since 1.6.6
		 */
		openModalButtonClick: function() {

			$( document ).on(
				'click',
				'.education-modal',
				function( event ) {

					var $this = $( this );

					if ( ! $this.data( 'action' ) || [ 'activate', 'install' ].includes( $this.data( 'action' ) ) ) {
						return;
					}

					event.preventDefault();
					event.stopImmediatePropagation();

					switch ( $this.data( 'action' ) ) {
						case 'upgrade':
							app.upgradeModal(
								$this.data( 'name' ),
								$this.data( 'field-name' ),
								WPFormsEducation.core.getUTMContentValue( $this ),
								$this.data( 'license' ),
								$this.data( 'video' )
							);
							break;
						case 'license':
							app.licenseModal(
								$this.data( 'name' ),
								$this.data( 'field-name' ),
								WPFormsEducation.core.getUTMContentValue( $this )
							);
							break;
					}
				}
			);
		},

		/**
		 * Activate addon by clicking the toggle button.
		 * Used in the Geolocation education box on the single entry view page.
		 *
		 * @since 1.6.6
		 */
		activateButtonClick: function() {

			$( '.wpforms-education-toggle-plugin-btn' ).on( 'click', function( event ) {

				var $button = $( this );

				event.preventDefault();
				event.stopImmediatePropagation();

				if ( $button.hasClass( 'inactive' ) ) {
					return;
				}

				$button.addClass( 'inactive' );

				const $form = $button.closest( '.wpforms-addon-form, .wpforms-education-page' ),
					buttonText = $button.text(),
					plugin = $button.data( 'plugin' ),
					state = $button.data( 'action' ),
					pluginType = $button.data( 'type' );

				$button.html( WPFormsAdmin.settings.iconSpinner + buttonText );
				WPFormsAdmin.setAddonState(
					plugin,
					state,
					pluginType,
					function( res ) {

						if ( res.success ) {
							location.reload();
						} else {
							$form.append( '<div class="msg error" style="display: none;">' + wpforms_admin[ pluginType + '_error' ] + '</div>' );
							$form.find( '.msg' ).slideDown();
						}
						$button.text( buttonText );
						setTimeout( function() {

							$button.removeClass( 'inactive' );
							$form.find( '.msg' ).slideUp( '', function() {
								$( this ).remove();
							} );
						}, 5000 );
					},
					function( error ) {
						// eslint-disable-next-line no-console
						console.log( error.responseText );
					} );
			} );
		},

		/**
		 * Upgrade modal.
		 *
		 * @since 1.6.6
		 *
		 * @param {string} feature    Feature name.
		 * @param {string} fieldName  Field name.
		 * @param {string} utmContent UTM content.
		 * @param {string} type       License type.
		 * @param {string} video      Feature video URL.
		 */
		upgradeModal: function( feature, fieldName, utmContent, type, video ) {

			// Provide a default value.
			if ( typeof type === 'undefined' || type.length === 0 ) {
				type = 'pro';
			}

			// Make sure we received only supported type.
			if ( $.inArray( type, [ 'pro', 'elite' ] ) < 0 ) {
				return;
			}

			var modalTitle   = feature + ' ' + wpforms_education.upgrade[type].title,
				isVideoModal = ! _.isEmpty( video ),
				modalWidth   = WPFormsEducation.core.getUpgradeModalWidth( isVideoModal );

			if ( typeof fieldName !== 'undefined' && fieldName.length > 0 ) {
				modalTitle = fieldName + ' ' + wpforms_education.upgrade[type].title;
			}

			var modal = $.alert( {
				backgroundDismiss: true,
				title            : modalTitle,
				icon             : 'fa fa-lock',
				content          : wpforms_education.upgrade[type].message.replace( /%name%/g, feature ),
				boxWidth         : modalWidth,
				theme            : 'modern,wpforms-education',
				closeIcon        : true,
				onOpenBefore: function() {

					if ( isVideoModal ) {
						this.$el.addClass( 'upgrade-modal has-video' );
						this.$btnc.after( '<iframe src="' + video + '" class="pro-feature-video" frameborder="0" allowfullscreen="" width="475" height="267"></iframe>' );
					}

					this.$body.find( '.jconfirm-content' ).addClass( 'lite-upgrade' );
				},
				buttons     : {
					confirm: {
						text    : wpforms_education.upgrade[type].button,
						btnClass: 'btn-confirm',
						keys    : [ 'enter' ],
						action  : function() {

							window.open( WPFormsEducation.core.getUpgradeURL( utmContent, type ), '_blank' );
						},
					},
				},
			} );

			$( window ).on( 'resize', function() {

				modalWidth = WPFormsEducation.core.getUpgradeModalWidth( isVideoModal );

				if ( modal.isOpen() ) {
					modal.setBoxWidth( modalWidth );
				}
			} );
		},

		/**
		 * License modal.
		 *
		 * @since 1.6.6
		 *
		 * @param {string} feature    Feature name.
		 * @param {string} fieldName  Field name.
		 * @param {string} utmContent UTM content.
		 */
		licenseModal: function( feature, fieldName, utmContent ) {

			var name = fieldName || feature,
				content = wpforms_education.license.prompt,
				button = wpforms_education.license.button,
				isActivateModal = wpforms_education.license.is_empty && typeof WPFormsBuilder !== 'undefined';

			if ( isActivateModal ) {
				content = `
					<p>${wpforms_education.activate_license.prompt_part1}</p>
					<p>${wpforms_education.activate_license.prompt_part2}</p>
					<input type="password" id="wpforms-edu-modal-license-key" value="" placeholder="${wpforms_education.activate_license.placeholder}">
				`;
				button = wpforms_education.activate_license.button;
			}

			$.alert( {
				title  : wpforms_education.license.title,
				content: content.replace( /%name%/g, `<strong>${name}</strong>` ).replace( /~utm-content~/g, utmContent ),
				icon   : 'fa fa-exclamation-circle',
				type   : 'orange',
				buttons: {
					confirm: {
						text    : button,
						btnClass: 'btn-confirm',
						keys    : [ 'enter' ],
						action  : function() {

							if ( isActivateModal ) {

								this.$$confirm
									.prop( 'disabled', true )
									.html( WPFormsEducation.core.getSpinner() + wpforms_education.activating );

								app.activateLicense( this );

								return false;
							}

							window.open(
								wpforms_education.license.url.replace( /~utm-content~/g, utmContent ),
								'_blank'
							);
						},
					},
					cancel : {
						text: wpforms_education.cancel,
					},
				},
			} );
		},

		/**
		 * Activate license via AJAX.
		 *
		 * @since 1.7.6
		 *
		 * @param {object} previousModal Previous modal instance.
		 */
		activateLicense: function( previousModal ) {

			var key = $( '#wpforms-edu-modal-license-key' ).val();

			if ( key.length === 0 ) {
				previousModal.close();
				WPFormsEducation.core.errorModal( false, wpforms_education.activate_license.enter_key );

				return;
			}

			$.post(
				wpforms_education.ajax_url,
				{
					action: 'wpforms_verify_license',
					nonce : typeof wpforms_builder !== 'undefined' ? wpforms_builder.admin_nonce : wpforms_admin.nonce,
					license: key,
				},
				function( res ) {

					previousModal.close();

					if ( res.success ) {
						WPFormsEducation.core.saveModal(
							wpforms_education.activate_license.success_title,
							`
								<p>${wpforms_education.activate_license.success_part1}</p>
								<p>${wpforms_education.activate_license.success_part2}</p>
							`
						);

						return;
					}

					// In the case of error.
					WPFormsEducation.core.errorModal( false, res.data );
				}
			);
		},
	};

	// Provide access to public functions/properties.
	return app;

}( document, window, jQuery ) );

// Initialize.
WPFormsEducation.proCore.init();
