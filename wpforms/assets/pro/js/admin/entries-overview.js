/* global flatpickr, Chart, moment, ajaxurl, wpforms_admin_entries_overview */

/**
 * Script for manipulating DOM events in the "Entries Overview" page.
 * This script will be accessible in the "WPForms" → "Entries" page.
 *
 * @since 1.8.2
 */
'use strict';

let WPFormsEntriesOverview = window.WPFormsEntriesOverview || ( function( document, window, $, ajaxurl, l10n ) {

	/**
	 * Elements holder.
	 *
	 * @since 1.8.2
	 *
	 * @type {Object}
	 */
	const el = {};

	/**
	 * Runtime variables.
	 *
	 * @since 1.8.2
	 *
	 * @type {Object}
	 */
	const vars = {

		/**
		 * Chart.js instance.
		 *
		 * @since 1.8.2
		 */
		chart: null,

		/**
		 * Flatpickr instance.
		 *
		 * @since 1.8.2
		 */
		datepicker: null,

		/**
		 * The ISO 639-2 language code of the WordPress installation.
		 *
		 * @since 1.8.2
		 */
		locale: l10n.locale,

		/**
		 * Actual dataset that will appear on the chart.
		 *
		 * @since 1.8.2
		 */
		data: [],

		/**
		 * Active form id.
		 *
		 * @since 1.8.2
		 */
		activeFormId: l10n.settings.active_form_id,

		/**
		 * Chart type. Options are "Line" or "Bar".
		 * A line chart is a way of plotting data points on a line.
		 * A bar chart provides a way of showing data values represented as vertical bars.
		 *
		 * 1: Bar.
		 * 2: Line.
		 *
		 * @since 1.8.2
		 */
		type: l10n.settings.graph_style === 1 ? 'bar' : 'line',

		/**
		 * Chart color scheme. Options are "WPForms" or "WP".
		 *
		 * 1: WPForms.
		 * 2: WP.
		 *
		 * @since 1.8.2
		 */
		theme: l10n.settings.color_scheme || '1',

		/**
		 * Total number of entries.
		 *
		 * @since 1.8.2
		 */
		total: 0,

		/**
		 * Timespan (date range) delimiter. By default: ' - '.
		 *
		 * @since 1.8.2
		 */
		delimiter: l10n.delimiter,

		/**
		 * The Moment.js compatible format string to use for the tooltip.
		 *
		 * @since 1.8.5.4
		 */
		tooltipFormat: l10n.date_format,

		/**
		 * Table heading. Possible options are "All Forms" or particular form name.
		 *
		 * @since 1.8.2
		 */
		heading: '',

		/**
		 * Generic CSS class names for applying visual changes.
		 *
		 * @since 1.8.2
		 */
		classNames: {
			hide: 'wpforms-hide',
			selected: 'is-selected',
		},

		/**
		 * Start and end dates.
		 *
		 * @since 1.8.2
		 */
		timespan: '',

		/**
		 * Translated texts.
		 *
		 * @since 1.8.2
		 *
		 * @return {Object} Localized strings.
		 */
		get i18n() {

			return l10n.i18n;
		},

		/**
		 * In case the time span extends to other years, the xAxes date display format is updated to include the year identifier.
		 *
		 * @since 1.8.2
		 *
		 * @return {Object} Localized strings.
		 */
		get xAxesDisplayFormat() {

			if ( ! this.timespan.length ) {
				return 'MMM D';
			}

			const dates = this.timespan.split( this.delimiter );

			if ( ! Array.isArray( dates ) || dates.length !== 2 ) {
				return 'MMM D';
			}

			const startYear = moment( dates[0] ).format( 'YYYY' );
			const endYear   = moment( dates[1] ).format( 'YYYY' );

			return startYear === endYear ? 'MMM D' : 'MMM D YYYY';
		},

		/**
		 * Chart color options.
		 *
		 * 1: wpforms.
		 * 2: wp.
		 *
		 * @since 1.8.2
		 *
		 * @return {Object} Colors object specified for the graph.
		 */
		get colors() {

			const isLine = this.type === 'line';

			return {

				'1': { // WPForms (1) color scheme.
					hoverBorderColor: '#da691f',
					hoverBackgroundColor: '#da691f',
					borderColor: 'rgb(226, 119, 48)',
					pointBackgroundColor: 'rgba(255, 255, 255, 1)',
					backgroundColor: isLine ? 'rgba(255, 129, 0, 0.135)' : 'rgb(226, 119, 48)',
				},
				'2': { // WordPress (2) color scheme.
					hoverBorderColor: '#055f9a',
					hoverBackgroundColor: '#055f9a',
					borderColor: '#056aab',
					pointBackgroundColor: 'rgba(255, 255, 255, 1)',
					backgroundColor: isLine ? '#e6f0f7' : '#056aab',
				},
			};
		},

		/**
		 * Chart.js settings.
		 *
		 * @since 1.8.2
		 *
		 * @return {Object} Scriptable options as a function which is called for each data.
		 */
		get settings() { /* eslint max-lines-per-function: ["error", 100] */

			return {

				type: this.type,
				data: {
					labels: [],
					datasets: [
						{
							data: [],
							label: this.i18n?.label || '',
							borderWidth: 2,
							pointRadius: 4,
							pointBorderWidth: 1,
							maxBarThickness: 100,
							...this.colors[ this.theme ],
						},
					],
				},
				options: {
					clip: false,
					layout: {
						padding: {
							left: 15,
							right: 19,
							top: 21,
							bottom: 4,
						},
					},
					scales: {
						xAxes: [
							{
								type: 'time',
								offset: this.type === 'bar',
								time: {
									unit: 'day',
									tooltipFormat: this.tooltipFormat,
									displayFormats: {
										day: this.xAxesDisplayFormat,
									},
								},
								distribution: 'series',
								ticks: {
									beginAtZero: true,
									padding: 10,
									fontColor: '#787c82',
									fontSize: 13,
									minRotation: 25,
									maxRotation: 25,
									callback: function( value, index, values ) {

										// Distribute the ticks equally starting from a right side of xAxis.
										const gap = Math.floor( values.length / 7 );

										if ( gap < 1 ) {
											return value;
										}
										if ( ( values.length - index - 1 ) % gap === 0 ) {
											return value;
										}
									},
								},
							},
						],
						yAxes: [
							{
								ticks: {
									beginAtZero: true,
									maxTicksLimit: 6,
									padding: 20,
									fontColor: '#787c82',
									fontSize: 13,
									callback: function( value ) {

										// Make sure the tick value has no decimals.
										if ( Math.floor( value ) === value ) {
											return value;
										}
									},
								},
							},
						],
					},
					elements: {
						line: {
							tension: 0,
						},
					},
					animation: {
						duration: 0,
					},
					hover: {
						animationDuration: 0,
					},
					legend: {
						display: false,
					},
					tooltips: {
						displayColors: false,
					},
					responsiveAnimationDuration: 0,
					maintainAspectRatio: false,
				},
			};
		},
	};

	/**
	 * Public functions and properties.
	 *
	 * @since 1.8.2
	 */
	const app = {

		/**
		 * Start the engine.
		 *
		 * @since 1.8.2
		 */
		init: function() {

			$( app.ready );
		},

		/**
		 * Document ready.
		 *
		 * @since 1.8.2
		 */
		ready: function() {

			app.setup();
			app.bindEvents();
			app.initDatePicker();
			app.initChart();
		},

		/**
		 * Setup. Prepare some variables.
		 *
		 * @since 1.8.2
		 */
		setup: function() {

			// Cache DOM elements.
			el.$document   = $( document );
			el.$wrapper    = $( '.wpforms-entries-overview' );
			el.$heading    = $( '.wpforms-overview-top-bar-heading h2' );
			el.$spinner    = $( '.wpforms-overview-chart .spinner' );
			el.$canvas     = $( '#wpforms-entries-overview-canvas' );
			el.$filterBtn  = $( '#wpforms-datepicker-popover-button' );
			el.$datepicker = $( '#wpforms-entries-overview-datepicker' );
			el.$filterForm = $( '.wpforms-overview-top-bar-filter-form' );
			el.$notice     = $( '.wpforms-overview-chart-notice' );
			el.$total      = $( '.wpforms-overview-chart-total-items' );
			el.$nonce      = $( '.wpforms-entries-overview-table [name="nonce"]' );
		},

		/**
		 * Bind events.
		 *
		 * @since 1.8.2
		 */
		bindEvents: function() {

			el.$document
				.on( 'click', { selectors: [ '.wpforms-datepicker-popover', '.wpforms-dash-widget-settings-menu' ] }, app.handleOnClickOutside );
			el.$wrapper
				.on( 'submit', '.wpforms-overview-top-bar-filter-form', app.handleOnSubmitDatepicker )
				.on( 'click', '.wpforms-overview-top-bar-filter-form [type="reset"]', app.handleOnResetDatepicker )
				.on( 'change', '.wpforms-overview-top-bar-filter-form [type="radio"]', app.handleOnUpdateDatepicker )
				.on( 'click', '.wpforms-show-chart', app.handleOnShowChart )
				.on( 'click', '.wpforms-reset-chart', app.handleOnResetChart )
				.on( 'click', '.wpforms-dash-widget-settings-menu-save', app.handleOnSaveSettings )
				.on( 'click', '#wpforms-dash-widget-settings-button', { selector: '.wpforms-dash-widget-settings-menu', hide: '.wpforms-datepicker-popover' }, app.handleOnToggle )
				.on( 'click', '#wpforms-datepicker-popover-button', { selector: '.wpforms-datepicker-popover', hide: '.wpforms-dash-widget-settings-menu' }, app.handleOnToggle );
		},

		/**
		 * Create an instance of "flatpickr".
		 *
		 * @since 1.8.2
		 */
		initDatePicker: function() {

			if ( ! el.$datepicker.length ) {
				return;
			}

			vars.timespan   = el.$datepicker.val();
			vars.datepicker = flatpickr( el.$datepicker, {
				mode: 'range',
				inline: true,
				allowInput: false,
				enableTime: false,
				clickOpens: false,
				altInput: true,
				altFormat: 'M j, Y',
				dateFormat: 'Y-m-d',
				locale: {

					// Localized per-instance, if applicable.
					...flatpickr.l10ns[ vars.locale ] || {},
					rangeSeparator: vars.delimiter,
				},
				onChange: function( selectedDates, dateStr, instance ) {

					// Immediately after a user interacts with the datepicker, ensure that the "Custom" option is chosen.
					const $custom = el.$filterForm.find( 'input[value="custom"]' );

					$custom.prop( 'checked', true );
					app.selectDatepickerChoice( $custom.parent() );

					if ( dateStr ) {

						// Update filter button label when date range specified.
						el.$filterBtn.text( instance.altInput.value );
					}
				},
			} );

			// Determine if a custom date range was provided or selected.
			this.handleOnUpdateDatepicker( {}, el.$filterForm.find( 'input[value="custom"]' ).prop( 'checked' ) );
		},

		/**
		 * Callback which is called when the filter form gets submitted.
		 *
		 * @since 1.8.2
		 */
		handleOnSubmitDatepicker: function() {

			// Exclude radio inputs from the form submission.
			$( this ).find( 'input[type="radio"]' ).attr( 'name', '' );

			// Remove the popover from the view.
			// When the dropdown is closed, aria-expended="false".
			app.hideElm( el.$filterBtn.next() );
		},

		/**
		 * Callback which is called when the datepicker "Cancel" button clicked.
		 *
		 * @since 1.8.2
		 *
		 * @param {Object} event An event which takes place in the DOM.
		 */
		handleOnResetDatepicker: function( event ) {

			event.preventDefault();

			// To return the form to its original state, manually reset it.
			el.$filterForm.get( 0 ).reset();

			// Remove the popover from the view.
			// When the dropdown is closed, aria-expended="false".
			app.hideElm( el.$filterBtn.next() );

			app.handleOnUpdateDatepicker();
		},

		/**
		 * Callback which is called when the filter form elements change.
		 *
		 * @since 1.8.2
		 *
		 * @param {Object}  event         An event which takes place in the DOM.
		 * @param {boolean} isCustomDates Determine whether a custom date range is provided.
		 */
		handleOnUpdateDatepicker: function( event = {}, isCustomDates = false ) {

			const $selected = el.$filterForm.find( 'input:checked' );
			const $parent   = $selected.parent();
			const $target   = isCustomDates ? el.$datepicker : $selected;
			const dates     = $target.val().split( vars.delimiter );

			el.$filterBtn.text( isCustomDates ? $target.next().val() : $parent.text() );

			app.selectDatepickerChoice( $parent );

			if ( Array.isArray( dates ) && dates.length === 2 ) {

				// Sets the current selected date(s).
				vars.datepicker.setDate( dates );
				return;
			}

			vars.datepicker.clear(); // Reset the datepicker.
		},

		/**
		 * Create an instance of chart.
		 *
		 * @since 1.8.2
		 */
		initChart: function() {

			if ( ! el.$canvas.length ) {
				return;
			}

			const elm    = el.$canvas.get( 0 ).getContext( '2d' );
			vars.chart   = new Chart( elm, vars.settings );
			vars.heading = el.$heading.text();

			this.updateChartByFormId( '', this.updateChart, this.updateChartActiveForm );
		},

		/**
		 * Callback which is called when the "show-chart" button clicked.
		 *
		 * @since 1.8.2
		 *
		 * @param {Object} event An event which takes place in the DOM.
		 */
		handleOnShowChart: function( event ) {

			event.preventDefault();

			app.spinner();

			const $this = $( this );
			const form  = $this.data( 'form' );

			app.maybeCleanupChart( $this );

			$this.addClass( vars.classNames.hide );
			$this.prev().removeClass( vars.classNames.hide );
			$this.closest( 'tr' ).addClass( vars.classNames.selected );

			app.updateChartByFormId( form );
		},

		/**
		 * Callback which is called when the "reset-chart" button clicked.
		 *
		 * @since 1.8.2
		 *
		 * @param {Object} event An event which takes place in the DOM.
		 */
		handleOnResetChart: function( event ) {

			event.preventDefault();

			app.spinner();

			const $this = $( this );
			const $wrapper = $this.closest( '#wpforms-entries-list' );
			const $button  = $wrapper.find( `tr.${vars.classNames.selected} .wpforms-reset-chart` );

			// Determine whether the click is initiated from the "Graph" column.
			if ( $button.length ) {
				$button.addClass( vars.classNames.hide );
				$button.next().removeClass( vars.classNames.hide );
				$button.closest( 'tr' ).removeClass( vars.classNames.selected );
			}

			el.$heading.next().addClass( vars.classNames.hide ).end().text( vars.heading );

			$.post(
				ajaxurl,
				{
					_ajax_nonce: el.$nonce.val(), /* eslint-disable-line camelcase */
					action: 'wpforms_entries_overview_flush_chart_active_form_id',
				}
			).done( function() {
				app.updateChart();
			} );
		},

		/**
		 * Save the user's preferred graph style and color scheme.
		 *
		 * @since 1.8.2
		 *
		 * @param {Object} event An event which takes place in the DOM.
		 */
		handleOnSaveSettings: function( event ) {

			event.preventDefault();

			const $wrapper    = $( this ).closest( '.wpforms-dash-widget-settings-container' );
			const graphStyle  = $wrapper.find( 'input[name="wpforms-style"]:checked' ).val();
			const colorScheme = $wrapper.find( 'input[name="wpforms-color"]:checked' ).val();

			vars.type  = Number( graphStyle ) === 1 ? 'bar' : 'line';
			vars.theme = colorScheme;

			const options                   = Object.assign( {}, vars.settings );
			options.data.labels             = vars.chart.data.labels;
			options.data.datasets[ 0 ].data = vars.chart.data.datasets[ 0 ].data;

			vars.chart.destroy();

			const elm  = el.$canvas.get( 0 ).getContext( '2d' );
			vars.chart = new Chart( elm, options );

			$.post(
				ajaxurl,
				{
					graphStyle,
					colorScheme,
					_ajax_nonce: el.$nonce.val(), /* eslint-disable-line camelcase */
					action: 'wpforms_entries_overview_save_chart_preference_settings',
				}
			).done( function() {
				el.$wrapper.find( '.wpforms-dash-widget-settings-menu' ).hide();
			} );
		},

		/**
		 * Display or hide the matched elements.
		 *
		 * @since 1.8.2
		 *
		 * @param {Object} event An event which takes place in the DOM.
		 */
		handleOnToggle: function( event ) {

			event.preventDefault();

			event.stopPropagation();

			const { data: { selector, hide } } = event;

			// Toggle the visibility of the matched element.
			el.$wrapper.find( selector ).toggle( 0, function() {
				const $selector = $( selector );

				// When the dropdown is open, aria-expended="true".
				$selector.attr( 'aria-expanded', $selector.is( ':visible' ) );
			} );

			// In case the other popover is open, let’s hide it to avoid clutter.
			// When the dropdown is closed, aria-expended="false".
			app.hideElm( el.$wrapper.find( hide ) );
		},

		/**
		 * Hide the matched elements when clicked outside their container.
		 *
		 * @since 1.8.2
		 *
		 * @param {Object} event An event which takes place in the DOM.
		 */
		handleOnClickOutside: function( event ) {

			const { target, data: { selectors } } = event;

			$.each( selectors, function( index, selector ) {

				if ( ! $( target ).closest( `${selector}:visible` ).length ) {
					app.hideElm( el.$wrapper.find( selector ) );
				}
			} );
		},

		/**
		 * Either fills the container with placeholder data or determines
		 * whether actual data is available to process the chart dataset.
		 *
		 * @since 1.8.2
		 *
		 * @param {Object} data Chart dataset data.
		 *
		 * @return {Object} Labels and dataset data object.
		 */
		processDatasetData: function( data ) {

			const labels   = [];
			const datasets = [];

			if ( $.isPlainObject( data ) && Object.keys( data ).length > 0 ) {

				el.$notice.addClass( vars.classNames.hide );

				$.each( data || vars.data, function( index, item ) {

					const date = moment( item.day );

					labels.push( date );
					datasets.push( {
						t: date,
						y: item?.count || 0,
					} );
				} );

				return { labels, datasets };
			}

			el.$notice.removeClass( vars.classNames.hide );

			let date;
			const end  = moment().startOf( 'day' );
			const days = 30;
			const minY = 5;
			const maxY = 20;

			for ( let i = 1; i <= days; i++ ) {

				date = end.clone().subtract( i, 'days' );

				labels.push( date );
				datasets.push( {
					t: date,
					y: Math.floor( Math.random() * ( maxY - minY + 1 ) ) + minY, // NOSONAR not used in secure contexts.
				} );
			}

			return { labels, datasets };
		},

		/**
		 * Populate the chart with a fresh set of dataset data.
		 *
		 * @since 1.8.2
		 *
		 * @param {Array}         data  Chart dataset data.
		 * @param {string|number} total Total number of entries.
		 */
		updateChart: function( data, total ) {

			if ( ! vars.activeFormId ) {
				el.$total.text( total || vars.total );
			}

			const { labels, datasets } = app.processDatasetData( data || vars.data );

			vars.chart.data.labels = labels;
			vars.chart.data.datasets[ 0 ].data = datasets;
			vars.chart.update();

			el.$spinner.addClass( vars.classNames.hide );
		},

		/**
		 * Fetch and process the chart dataset data for a given form id.
		 *
		 * @since 1.8.2
		 *
		 * @param {string|number} formId    Given form id.
		 * @param {Function}      onSuccess Optional callback function that is executed if the request succeeds.
		 * @param {Function}      onDone    Optional deferred method to execute when the Ajax request terminates.
		 */
		updateChartByFormId: function( formId, onSuccess, onDone ) {

			$.post(
				ajaxurl,
				{
					form: formId,
					dates: vars.timespan,
					_ajax_nonce: el.$nonce.val(), /* eslint-disable-line camelcase */
					action: 'wpforms_entries_overview_refresh_chart_dataset_data',
				},
				function( { data: { data, name, total } } ) {

					// Cache dataset and overall number of entries for the chart stats.
					if ( ! formId && ! Object.keys( vars.data ).length ) {
						vars.data  = data;
						vars.total = total;
					}

					app.updateChart( data, total.toString() );

					if ( name ) {
						el.$heading.next().removeClass( vars.classNames.hide ).end().text( name );
					}

					if ( typeof onSuccess === 'function' ) {
						onSuccess();
					}
				}
			).done( onDone );
		},

		/**
		 * Update chart dataset with the data for the active form.
		 *
		 * @since 1.8.2
		 */
		updateChartActiveForm: function() {

			const { activeFormId: form } = vars;

			// If no form id is provided, leave the function early.
			if ( ! form ) {
				return;
			}

			app.spinner();
			vars.activeFormId = null; // Flush the active form id.

			const $showChart = $( `.wpforms-show-chart[data-form="${ form }"]` );

			if ( $showChart.length ) {
				$showChart.trigger( 'click' );
				return;
			}

			// Display on the chart the active form dataset.
			app.updateChartByFormId( form );
		},

		/**
		 * If another form is in preview, this method will ensure that the chart has been cleaned up properly.
		 *
		 * @since 1.8.2
		 *
		 * @param {Object} $this Reference to the DOM element.
		 */
		maybeCleanupChart: function( $this ) {

			const $selected = $this.closest( 'tbody' ).find( 'tr.is-selected' );

			if ( $selected.length <= 0 ) {
				return;
			}

			$selected.removeClass( vars.classNames.selected );
			$selected.find( '.wpforms-reset-chart' ).addClass( vars.classNames.hide );
			$selected.find( '.wpforms-show-chart' ).removeClass( vars.classNames.hide );
		},

		/**
		 * Pick an option (given) from the datepicker’s choices.
		 *
		 * @since 1.8.2
		 *
		 * @param {Object} $this Reference to the DOM element.
		 */
		selectDatepickerChoice: function( $this ) {

			el.$filterForm.find( 'label' ).removeClass( vars.classNames.selected );
			$this.addClass(  vars.classNames.selected  );
		},

		/**
		 * Signal to users that the processing of their request is underway and will soon complete.
		 *
		 * @since 1.8.2
		 */
		spinner: function() {

			el.$spinner.removeClass( vars.classNames.hide );
		},

		/**
		 * Hides the given DOM element.
		 *
		 * @since 1.8.2
		 *
		 * @param {Object} $elm Reference to the DOM element.
		 */
		hideElm: function( $elm ) {

			$elm.attr( 'aria-expanded', 'false' ).hide();
		},
	};

	// Provide access to public functions/properties.
	return app;

}( document, window, jQuery, ajaxurl, wpforms_admin_entries_overview ) );

// Initialize.
WPFormsEntriesOverview.init();
