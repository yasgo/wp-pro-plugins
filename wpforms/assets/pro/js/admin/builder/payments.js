/* global wpf, wpforms_builder */

'use strict';

var WPFormsBuilderPayments = window.WPFormsBuilderPayments || ( function( document, window, $ ) {

	/**
	 * Payments panel.
	 *
	 * @since 1.7.5
	 *
	 * @type {jQuery}
	 */
	var $paymentsPanel = $( '#wpforms-panel-payments' );

	/**
	 * Public functions and properties.
	 *
	 * @since 1.7.5
	 *
	 * @type {object}
	 */
	var app = {

		/**
		 * Init payment panel scripts.
		 *
		 * @since 1.7.5
		 */
		init: function() {

			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 1.7.5
		 */
		ready: function() {

			app.defaultStates();
			app.bindEvents();
		},

		/**
		 * Default states for the Payments panel.
		 *
		 * @since 1.7.5
		 */
		defaultStates: function() {

			$( '.wpforms-panel-content-section-payment-toggle input' ).each( app.toggleContent );
			$( '.wpforms-panel-content-section-payment-plan-name input' ).each( app.checkPlanName );
		},

		/**
		 * Bind events.
		 *
		 * @since 1.7.5
		 */
		bindEvents: function() {

			$paymentsPanel
				.on( 'click', '.wpforms-panel-content-section-payment-toggle input', app.toggleContent )
				.on( 'click', '.wpforms-panel-content-section-payment-plan-head-buttons-toggle', app.togglePlan )
				.on( 'click', '.wpforms-panel-content-section-payment-button-add-plan', app.addPlan )
				.on( 'input', '.wpforms-panel-content-section-payment-plan-name input', app.renamePlan )
				.on( 'focusout', '.wpforms-panel-content-section-payment-plan-name input', app.checkPlanName )
				.on( 'click', '.wpforms-panel-content-section-payment-plan-head-buttons-delete', app.deletePlan )
				.on( 'click', '.wpforms-panel-content-section-payment-toggle-recurring input', app.addEmptyPlan )
				.on( 'click', '.wpforms-panel-content-section-payment-toggle-one-time input', function() {

					app.noteOneTimePaymentsDisabled( $( this ) );
				} );

			$( document )
				.on( 'wpformsBeforeSave', app.showNoticesAfterFormSaved )
				.on( 'wpformsRemoveConditionalLogicRules', function( event, $el ) {

					app.disableOneTimePayments( $el );
				} );
		},

		/**
		 * Toggle payments content.
		 *
		 * @since 1.7.5
		 */
		toggleContent: function() {

			var $input = $( this ),
				$wrapper = $input.closest( '.wpforms-panel-content-section-payment' ),
				$body = $wrapper.find( '.wpforms-panel-content-section-payment-toggled-body' ),
				isChecked = $input.prop( 'checked' ) && ! $( '#wpforms-panel-field-settings-disable_entries' ).prop( 'checked' );

			$body.toggle( isChecked );
			$wrapper.toggleClass( 'wpforms-panel-content-section-payment-open', isChecked );
		},

		/**
		 * Add a new plan.
		 *
		 * @since 1.7.5
		 */
		addPlan: function() {

			if ( $( this ).hasClass( 'education-modal' ) ) {
				return;
			}

			const $wrapper = app.getProviderSection( $( this ) );

			$.confirm( {
				title: false,
				content: wpforms_builder.payment_plan_prompt +
					'<input autofocus="" type="text" id="wpforms-builder-payment-plan-name" placeholder="' + wpforms_builder.payment_plan_prompt_placeholder + '">' +
					'<p class="error">' + wpforms_builder.payment_error_name + '</p>',
				backgroundDismiss: false,
				closeIcon: false,
				icon: 'fa fa-info-circle',
				type: 'blue',
				buttons: {
					confirm: {
						text: wpforms_builder.ok,
						btnClass: 'btn-confirm',
						keys: [ 'enter' ],
						action: function() {

							var name  = this.$content.find( '#wpforms-builder-payment-plan-name' ).val().trim(),
								error = this.$content.find( '.error' );

							if ( ! name ) {
								error.show();

								return false;
							}

							app.createNewPlan( name, $wrapper );
						},
					},
					cancel: {
						text: wpforms_builder.cancel,
					},
				},
			} );
		},

		/**
		 * Create a new plan.
		 *
		 * @since 1.7.5
		 *
		 * @param {string} planName Plan name.
		 * @param {jQuery} $wrapper Payments provider settings container.
		 */
		createNewPlan: function( planName, $wrapper ) {

			var $recurringWrapper = $wrapper.find( '.wpforms-panel-content-section-payment-recurring' ),
				$lastPlanWrapper = $recurringWrapper.find( '.wpforms-panel-content-section-payment-plan' ).last(),
				index =  $lastPlanWrapper.length ? $lastPlanWrapper.data( 'plan-id' ) + 1 : 0,
				template = wp.template( 'wpforms-builder-payments-' + $wrapper.data( 'provider' ) + '-clone' ),
				data = {
					index: index,
				};

			if ( ! template ) {
				return;
			}

			// Needs to replace index manually because {{ data.index }} was sanitized in ID attribute.
			$recurringWrapper.append( template( data ).replaceAll( '-dataindex-', `-${index}-` ) );

			var $newPlan = $recurringWrapper.find( '.wpforms-panel-content-section-payment-plan' ).last(),
				$newPlanNameInput = $newPlan.find( '.wpforms-panel-content-section-payment-plan-name input' );

			$newPlanNameInput.val( planName ? planName : app.getDefaultPlanName( index + 1 ) );

			$newPlanNameInput.trigger( 'input' );

			$( document ).trigger( 'wpformsFieldUpdate', wpf.getFields() );

			$paymentsPanel.trigger( 'wpformsPaymentsPlanCreated', $newPlan, $wrapper.data( 'provider' ) );

			// Re-init tooltips
			wpf.initTooltips();
		},

		/**
		 * Add empty plan.
		 *
		 * @since 1.7.5.3
		 */
		addEmptyPlan: function() {

			var $wrapper = app.getProviderSection( $( this ) );

			if ( ! $( this ).prop( 'checked' ) || $wrapper.find( '.wpforms-panel-content-section-payment-plan' ).length ) {
				return;
			}

			app.createNewPlan( '', $wrapper );
		},

		/**
		 * Toggle a plan content.
		 *
		 * @since 1.7.5
		 */
		togglePlan: function() {

			var $plan = $( this ).closest( '.wpforms-panel-content-section-payment-plan' ),
				$body = $plan.find( '.wpforms-panel-content-section-payment-plan-body' ),
				$icon = $plan.find( '.wpforms-panel-content-section-payment-plan-head-buttons-toggle' );

			$icon.toggleClass( 'fa-chevron-circle-up fa-chevron-circle-down' );
			$body.toggle( $icon.hasClass( 'fa-chevron-circle-down' ) );
		},

		/**
		 * Rename a plan.
		 *
		 * @since 1.7.5
		 */
		renamePlan: function() {

			var $input = $( this ),
				$wrapper = app.getProviderSection( $input ),
				$plan = $input.closest( '.wpforms-panel-content-section-payment-plan' ),
				$planName = $plan.find( '.wpforms-panel-content-section-payment-plan-head-title' );

			if ( ! $input.val() ) {
				$planName.html( '' );

				return;
			}

			$planName.html( $input.val() );

			$paymentsPanel.trigger( 'wpformsPaymentsPlanRenamed', $input.val(), $plan, $wrapper.data( 'provider' ) );
		},

		/**
		 * Check a plan name on empty value.
		 *
		 * @since 1.7.5
		 */
		checkPlanName() {
			const $input = $( this ),
				$plan = $input.closest( '.wpforms-panel-content-section-payment-plan' ),
				$planName = $plan.find( '.wpforms-panel-content-section-payment-plan-head-title' );

			if ( $input.val() ) {
				$planName.html( $input.val() );

				return;
			}

			if ( ! $plan.length ) {
				$planName.html( '' );

				return;
			}

			const defaultValue = app.getDefaultPlanName( $plan.data( 'plan-id' ) + 1 );

			$planName.html( defaultValue );
			$input.val( defaultValue );
		},

		/**
		 * Retrieve a default plan name.
		 *
		 * @since 1.7.5
		 *
		 * @param {int} index Plan index.
		 *
		 * @returns {string} ex: Plan Name #3.
		 */
		getDefaultPlanName: function( index ) {

			return wpforms_builder.payment_plan_placeholder.replace( '{id}', index );
		},

		/**
		 * Delete a plan.
		 *
		 * @since 1.7.5
		 */
		deletePlan: function() {

			var $input = $( this ),
				$wrapper = app.getProviderSection( $input ),
				$plan = $input.closest( '.wpforms-panel-content-section-payment-plan' );

			$.alert( {
				title: wpforms_builder.heads_up,
				content: wpforms_builder.payment_plan_confirm,
				icon: 'fa fa-exclamation-circle',
				type: 'orange',
				buttons: {
					confirm: {
						text: wpforms_builder.ok,
						btnClass: 'btn-confirm',
						keys: [ 'enter' ],
						action: function() {

							$plan.remove();

							$paymentsPanel.trigger( 'wpformsPaymentsPlanDeleted', $plan, $wrapper.data( 'provider' ) );

							if ( ! $wrapper.find( '.wpforms-panel-content-section-payment-plan' ).length ) {
								$wrapper.find( '.wpforms-panel-content-section-payment-toggle-recurring input' ).trigger( 'click' );
							}
						},
					},
					cancel: {
						text: wpforms_builder.cancel,
					},
				},
			} );
		},

		/**
		 * Disable one-time payments.
		 *
		 * @since 1.7.5
		 *
		 * @param {jQuery} $el One Time input element.
		 */
		disableOneTimePayments: function( $el ) {

			if ( $el.prop( 'checked' ) ) {
				return;
			}

			app.noteOneTimePaymentsDisabled(
				app.getProviderSection( $el ).find( '.wpforms-panel-content-section-payment-toggle-one-time input' )
			);
		},

		/**
		 * Determine if one-time payments are allowed.
		 *
		 * @since 1.7.5
		 *
		 * @param {jQuery} $recurringBody Recurring section container.
		 *
		 * @returns {boolean} true if one-time payments are allowed.
		 */
		isAllowedOneTimePayments( $recurringBody ) {

			if ( ! $recurringBody.closest( '.wpforms-panel-content-section-payment' ).find( '.wpforms-panel-content-section-payment-toggle-recurring input' ).prop( 'checked' ) ) {
				return true;
			}

			var $plans = $recurringBody.find( '.wpforms-panel-content-section-payment-plan' ),
				$conditionalGroups = $recurringBody.find( '.wpforms-conditional-groups' );

			if ( ! $plans.length ) {
				return false;
			}

			if ( $plans.length !== $conditionalGroups.length ) {
				return false;
			}

			return app.isRecurringConditionalsValid( $plans );
		},

		/**
		 * Determine if recurring conditionals logic are valid.
		 *
		 * @since 1.7.5
		 *
		 * @param {jQuery} $plans Recurring plans container.
		 *
		 * @returns {boolean} true if conditionals logic are valid.
		 */
		isRecurringConditionalsValid: function( $plans ) {

			var hasInvalidConditional = false;

			$plans.find( '.wpforms-conditional-block' ).each( function() {

				var $this = $( this );

				if ( ! $this.find( '.wpforms-conditionals-enable-toggle input' ).prop( 'checked' ) ) {
					hasInvalidConditional = true;

					return false;
				}

				$this.find( '.wpforms-conditional-row' ).each( function() {

					var $row = $( this ),
						$valueInput = $row.find( '.wpforms-conditional-value' );

					if (
						! $row.find( '.wpforms-conditional-field' ).val() ||
						(
							! $valueInput.prop( 'disabled' ) &&
							! $valueInput.val()
						)
					) {
						hasInvalidConditional = true;

						return false;
					}
				} );
			} );

			return ! hasInvalidConditional;
		},

		/**
		 * Show popups after form was saved.
		 *
		 * Go through all available payment providers and check if its one-time payment has to be disabled.
		 *
		 * @since 1.7.5
		 */
		showNoticesAfterFormSaved: function() {

			var $sections = $paymentsPanel.find( '.wpforms-panel-content-section' );

			if ( ! $sections.length ) {
				return;
			}

			$sections.each( function() {

				app.noteOneTimePaymentsDisabled( $( this ).find( '.wpforms-panel-content-section-payment-toggle-one-time input' ) );
			} );
		},

		/**
		 * Note user about disabling one-time payments because one of the recurring plans hasn't conditional logic.
		 *
		 * @since 1.7.5
		 *
		 * @param {jQuery} $el One Time input element.
		 */
		noteOneTimePaymentsDisabled: function( $el ) {

			var $section = app.getProviderSection( $el ),
				$oneTimeBody = $section.find( '.wpforms-panel-content-section-payment-one-time' ),
				$recurringBody = $section.find( '.wpforms-panel-content-section-payment-recurring' );

			if ( ! $el.prop( 'checked' ) || app.isAllowedOneTimePayments( $recurringBody ) ) {
				return;
			}

			$oneTimeBody.hide();

			$el.prop( 'checked', false );

			app.showPopupDisabledOneTimePayments( $section.find( '.wpforms-panel-content-section-title' ).text().trim() );
		},

		/**
		 * Show popup about disabling one-time payments.
		 *
		 * @since 1.7.5
		 *
		 * @param {string} title Payment addon title.
		 */
		showPopupDisabledOneTimePayments: function( title ) {

			$.alert( {
				title: wpforms_builder.heads_up,
				content: wpforms_builder.payment_one_time_payments_disabled.replaceAll( '{provider}', title ),
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
		 * Get Provider Section.
		 *
		 * @since 1.7.5
		 *
		 * @param {jQuery} $input Input element.
		 *
		 * @returns {jQuery} Provider Section.
		 */
		getProviderSection: function( $input ) {

			return $input.closest( '.wpforms-panel-content-section' );
		},
	};

	// Provide access to public functions/properties.
	return app;

}( document, window, jQuery ) );

// Initialize.
WPFormsBuilderPayments.init();
