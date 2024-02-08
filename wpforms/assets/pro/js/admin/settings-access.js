/* global wpforms_admin, wpforms_settings_access */
/**
 * WPForms Settings Access function.
 *
 * @since 1.5.9
 */

'use strict';

var WPFormsSettingsAccess = window.WPFormsSettingsAccess || ( function( document, window, $ ) {

	/**
	 * Public functions and properties.
	 *
	 * @since 1.5.9
	 *
	 * @type {object}
	 */
	var app = {

		/**
		 * Capability select elements value cache.
		 *
		 * @since 1.5.9
		 *
		 * @type {object}
		 */
		capsCache: {},

		/**
		 * List of "parent" capabilities for every tracked capability.
		 *
		 * @since 1.5.9
		 * @since 1.7.5 Add 'wpforms_create_forms' parent cap.
		 *
		 * @type {object}
		 */
		parentCaps: {

			// Forms.
			'wpforms_create_forms': [
				'wpforms_edit_own_forms',
				'wpforms_view_own_forms',
			],
			'wpforms_edit_own_forms': [
				'wpforms_view_own_forms',
			],
			'wpforms_edit_others_forms': [
				'wpforms_view_others_forms',
			],
			'wpforms_delete_own_forms': [
				'wpforms_view_own_forms',
			],
			'wpforms_delete_others_forms': [
				'wpforms_view_others_forms',
			],

			// Entries.
			'wpforms_view_entries_own_forms': [
				'wpforms_view_own_forms',
			],
			'wpforms_view_entries_others_forms': [
				'wpforms_view_others_forms',
			],
			'wpforms_edit_entries_own_forms': [
				'wpforms_view_own_forms',
				'wpforms_view_entries_own_forms',
			],
			'wpforms_edit_entries_others_forms': [
				'wpforms_view_others_forms',
				'wpforms_view_entries_others_forms',
			],
			'wpforms_delete_entries_own_forms': [
				'wpforms_view_own_forms',
				'wpforms_view_entries_own_forms',
			],
			'wpforms_delete_entries_others_forms': [
				'wpforms_view_others_forms',
				'wpforms_view_entries_others_forms',
			],
		},

		/**
		 * List of "child" capabilities for every tracked capability.
		 *
		 * @since 1.5.9
		 *
		 * @type {object}
		 */
		childCaps: {
			'wpforms_view_own_forms': [
				'wpforms_edit_own_forms',
				'wpforms_delete_own_forms',
				'wpforms_view_entries_own_forms',
				'wpforms_edit_entries_own_forms',
				'wpforms_delete_entries_own_forms',
			],
			'wpforms_view_others_forms': [
				'wpforms_edit_others_forms',
				'wpforms_delete_others_forms',
				'wpforms_view_entries_others_forms',
				'wpforms_edit_entries_others_forms',
				'wpforms_delete_entries_others_forms',
			],
			'wpforms_view_entries_own_forms': [
				'wpforms_edit_entries_own_forms',
				'wpforms_delete_entries_own_forms',
			],
			'wpforms_view_entries_others_forms': [
				'wpforms_edit_entries_others_forms',
				'wpforms_delete_entries_others_forms',
			],
		},

		/**
		 * Start the engine.
		 *
		 * @since 1.5.9
		 */
		init: function() {

			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 1.5.9
		 */
		ready: function() {

			app.updateAllCapsCache();
			app.events();
		},

		/**
		 * Register JS events.
		 *
		 * @since 1.5.9
		 */
		events: function() {

			$( '.wpforms-admin-settings-access select' ).on( 'change', app.selectChangeEvent );
		},

		/**
		 * Capability select element 'change' event callback.
		 *
		 * @since 1.5.9
		 */
		selectChangeEvent: function() {

			var $select = $( this ),
				cap,
				currentRoles,
				roleAdded,
				roleRemoved;

			if ( ! $select.length ) {
				return;
			}

			cap = $select.data( 'cap' );

			if ( ! cap ) {
				return;
			}

			// Get roles after new role was added/removed.
			currentRoles = $select.val();

			// Check if a role was added/removed.
			roleAdded = _.difference( currentRoles, app.getCapCache( cap ) ).toString();
			roleRemoved = _.difference( app.getCapCache( cap ), currentRoles ).toString();

			// Update cache with the current value.
			app.updateCapCache( $select );

			// The role was added.
			if ( roleAdded.length ) {
				app.processRoleAdded( cap, roleAdded );
			}

			// The role was removed.
			if ( roleRemoved.length ) {
				app.processRoleRemoved( cap, roleRemoved );
			}
		},

		/**
		 * Process the role added.
		 *
		 * @since 1.5.9
		 *
		 * @param {string} cap Capability to which the role was added.
		 * @param {string} role Role added.
		 */
		processRoleAdded: function( cap, role ) {

			var caps = app.getParentCapsMissing( cap, role );

			if ( ! caps.length ) {
				return;
			}

			app.displayModal( {
				cap: cap,
				caps: caps,
				role: role,
				template: wpforms_settings_access.l10n.grant_caps,
				confirmAction: function() {
					app.populateRoles( caps, role );
				},
				cancelAction: function() {
					app.removeRoles( [ cap ], role );
				},
			} );
		},

		/**
		 * Process the role removed.
		 *
		 * @since 1.5.9
		 *
		 * @param {string} cap Capability from which the role was removed.
		 * @param {string} role Role removed.
		 */
		processRoleRemoved: function( cap, role ) {

			var caps = app.getChildCapsPresent( cap, role );

			if ( ! caps.length ) {
				return;
			}

			app.displayModal( {
				cap: cap,
				caps: caps,
				role: role,
				template: wpforms_settings_access.l10n.remove_caps,
				confirmAction: function() {
					app.removeRoles( caps, role );
				},
				cancelAction: function() {
					app.populateRoles( [ cap ], role );
				},
			} );
		},

		/**
		 * Get a label for a capability.
		 *
		 * @since 1.5.9
		 *
		 * @param {string} cap Capability to get a label for.
		 *
		 * @returns {string} Label if found or an original capability if not.
		 */
		getCapLabel: function( cap ) {

			return wpforms_settings_access.labels.caps[ cap ] || cap;
		},

		/**
		 * Get labels for a capabilities list.
		 *
		 * @since 1.5.9
		 *
		 * @param {Array} caps Capabilities list.
		 *
		 * @returns {Array} Labels list.
		 */
		getCapLabels: function( caps ) {

			if ( typeof caps === 'undefined' || ! caps.length ) {
				return [];
			}

			return caps.map( app.getCapLabel );
		},

		/**
		 * Get a label for a role.
		 *
		 * @since 1.5.9
		 *
		 * @param {string} role Role to get a label for.
		 *
		 * @returns {string} Label if found or an original role if not.
		 */
		getRoleLabel: function( role ) {

			return wpforms_settings_access.labels.roles[ role ] || role;
		},

		/**
		 * Get a cached value for a capability select element.
		 *
		 * @since 1.5.9
		 *
		 * @param {string} cap Capability to get a cache for.
		 *
		 * @returns {Array} Cache value if found or an empty array if not.
		 */
		getCapCache: function( cap ) {

			return app.capsCache[ cap ] || [];
		},

		/**
		 * Update a cache for a capability select element.
		 *
		 * @since 1.5.9
		 *
		 * @param {jQuery} $el Capability select element to update a cache for.
		 * @param {Array} value Value to update a cache with.
		 */
		updateCapCache: function( $el, value ) {

			if ( ! $el.length ) {
				return;
			}

			var cap = $el.data( 'cap' );

			if ( ! cap ) {
				return;
			}

			value = value || $el.val();

			app.capsCache[ cap ] = value;
		},

		/**
		 * Update a cache for all capability select elements.
		 *
		 * @since 1.5.9
		 */
		updateAllCapsCache: function() {

			$( '.wpforms-admin-settings-access select' ).each( function() {
				app.updateCapCache( $( this ) );
			} );
		},

		/**
		 * Get "parent" caps missing a given role.
		 *
		 * @since 1.5.9
		 *
		 * @param {string} cap Capability to get "parent" caps for.
		 * @param {string} role Role to look for in capability select element value.
		 *
		 * @returns {Array} List of "parent" caps missing a given role.
		 */
		getParentCapsMissing: function( cap, role ) {

			var caps = app.parentCaps[ cap ];

			if ( ! caps ) {
				return [];
			}

			return caps.filter( function( _cap ) {
				var val = $( '#wpforms-setting-' + _cap ).val();
				return val ? val.indexOf( role ) === -1 : true;
			} );
		},

		/**
		 * Get "child" caps with a given role present.
		 *
		 * @since 1.5.9
		 *
		 * @param {string} cap Capability to get "child" caps for.
		 * @param {string} role Role to look for in capability select element value.
		 *
		 * @returns {Array} List of "child" caps with a given role present.
		 */
		getChildCapsPresent: function( cap, role ) {

			var caps = app.childCaps[ cap ];

			if ( ! caps ) {
				return [];
			}

			return caps.filter( function( _cap ) {
				var val = $( '#wpforms-setting-' + _cap ).val();
				return val ? val.indexOf( role ) !== -1 : false;
			} );
		},

		/**
		 * Display a modal to add/remove role from capability select element(s).
		 *
		 * @since 1.5.9
		 *
		 * @param {object} args Arguments for the modal.
		 */
		displayModal: function( args ) {

			var content = args.template
				.replace( '%1$s', '<b>' + app.getCapLabel( args.cap ) + '</b>' )
				.replace( /%2\$s/g, '<b>' + app.getCapLabels( args.caps ).join( ', ' ) + '</b>' )
				.replace( '%3$s', '<i>' + app.getRoleLabel( args.role ) + '</i>' );

			$.alert( {
				title  : wpforms_admin.heads_up,
				content: content,
				icon   : 'fa fa-exclamation-circle',
				type   : 'orange',
				boxWidth: '500px',
				buttons: {
					confirm: {
						text    : wpforms_admin.ok,
						btnClass: 'btn-confirm',
						keys    : [ 'enter' ],
						action  : args.confirmAction,
					},
					cancel : {
						text  : wpforms_admin.cancel,
						action: args.cancelAction,
					},
				},
			} );
		},

		/**
		 * Populate the role into the capability select element(s).
		 *
		 * @since 1.5.9
		 *
		 * @param {Array} caps Capabilities to populate the role into.
		 * @param {string} role Role to populate.
		 */
		populateRoles: function( caps, role ) {

			caps.map( function( cap ) {
				var $el = $( '#wpforms-setting-' + cap ),
					choicejs;
				if ( ! $el.length ) {
					return true;
				}
				choicejs = $el.data( 'choicesjs' );
				if ( ! choicejs ) {
					return true;
				}
				choicejs.setChoiceByValue( role );
				app.updateCapCache( $el );

				return cap;
			} );
		},

		/**
		 * Remove the role from the capability select element(s).
		 *
		 * @since 1.5.9
		 *
		 * @param {Array} caps Capabilities to remove the role from.
		 * @param {string} role Role to remove.
		 */
		removeRoles: function( caps, role ) {

			caps.map( function( cap ) {
				var $el = $( '#wpforms-setting-' + cap ),
					choicejs;
				if ( ! $el.length ) {
					return true;
				}
				choicejs = $el.data( 'choicesjs' );
				if ( ! choicejs ) {
					return true;
				}
				choicejs.removeActiveItemsByValue( role );
				app.updateCapCache( $el );
			} );
		},
	};

	// Provide access to public functions/properties.
	return app;

}( document, window, jQuery ) );

// Initialize.
WPFormsSettingsAccess.init();
