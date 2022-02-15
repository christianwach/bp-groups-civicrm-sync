/**
 * BP Groups CiviCRM Sync Manual Sync Javascript.
 *
 * Implements sync functionality on the plugin's Manual Sync page.
 *
 * @package BP_Groups_CiviCRM_Sync
 * @since 0.2.2
 */



/**
 * Create BP Groups CiviCRM Sync Manual Sync object.
 *
 * This works as a "namespace" of sorts, allowing us to hang properties, methods
 * and "sub-namespaces" from it.
 *
 * @since 0.2.2
 */
var BPGCS_Manual_Sync = BPGCS_Manual_Sync || {};



/**
 * Pass the jQuery shorcut in.
 *
 * @since 0.2.2
 *
 * @param {Object} $ The jQuery object.
 */
( function( $ ) {

	/**
	 * Create Settings Object.
	 *
	 * @since 0.2.2
	 */
	BPGCS_Manual_Sync.settings = new function() {

		// Prevent reference collisions.
		var me = this;

		/**
		 * Initialise Settings.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.2.2
		 */
		this.init = function() {

			// Init localisation.
			me.init_localisation();

			// Init settings.
			me.init_settings();

		};

		/**
		 * Do setup when jQuery reports that the DOM is ready.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.2.2
		 */
		this.dom_ready = function() {

		};

		// Init localisation array.
		me.localisation = [];

		/**
		 * Init localisation from settings object.
		 *
		 * @since 0.2.2
		 */
		this.init_localisation = function() {
			if ( 'undefined' !== typeof BPGCS_Settings ) {
				me.localisation = BPGCS_Settings.localisation;
			}
		};

		/**
		 * Getter for localisation.
		 *
		 * @since 0.2.2
		 *
		 * @param {String} The identifier for the desired localisation string.
		 * @return {String} The localised string.
		 */
		this.get_localisation = function( identifier ) {
			return me.localisation[identifier];
		};

		// Init settings array.
		me.settings = [];

		/**
		 * Init settings from settings object.
		 *
		 * @since 0.2.2
		 */
		this.init_settings = function() {
			if ( 'undefined' !== typeof BPGCS_Settings ) {
				me.settings = BPGCS_Settings.settings;
			}
		};

		/**
		 * Getter for retrieving a setting.
		 *
		 * @since 0.2.2
		 *
		 * @param {String} The identifier for the desired setting.
		 * @return The value of the setting.
		 */
		this.get_setting = function( identifier ) {
			return me.settings[identifier];
		};

	};

	/**
	 * Create Progress Bar Object.
	 *
	 * @since 0.2.2
	 */
	BPGCS_Manual_Sync.progress_bar = new function() {

		// Prevent reference collisions.
		var me = this;

		/**
		 * Initialise Progress Bar.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.2.2
		 */
		this.init = function() {

		};

		/**
		 * Do setup when jQuery reports that the DOM is ready.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.2.2
		 */
		this.dom_ready = function() {

			// Set up instance.
			me.setup();

			// Enable listeners.
			me.listeners();

		};

		/**
		 * Set up Progress Bar instance.
		 *
		 * @since 0.2.2
		 */
		this.setup = function() {

			// Assign properties.
			me.bar = $('#progress-bar');
			me.label = $('#progress-bar .progress-label');
			me.total = BPGCS_Manual_Sync.settings.get_setting( 'total_groups' );
			me.label_init = BPGCS_Manual_Sync.settings.get_localisation( 'total' );
			me.label_current = BPGCS_Manual_Sync.settings.get_localisation( 'current' );
			me.label_complete = BPGCS_Manual_Sync.settings.get_localisation( 'complete' );
			me.label_done = BPGCS_Manual_Sync.settings.get_localisation( 'done' );

		};

		/**
		 * Initialise listeners.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.2.2
		 */
		this.listeners = function() {

			// Declare vars.
			var button = $('#bp_groups_civicrm_sync_bp_check');

			/**
			 * Add a click event listener to start sync.
			 *
			 * @param {Object} event The event object.
			 */
			button.on( 'click', function( event ) {

				// Prevent form submission.
				if ( event.preventDefault ) {
					event.preventDefault();
				}

				// Initialise progress bar.
				me.bar.progressbar({
					value: false,
					max: me.total
				});

				// Show progress bar if not already shown.
				me.bar.show();

				// Initialise progress bar label.
				me.label.html( me.label_init.replace( '{{total}}', me.total ) );

				// Send.
				me.send();

			});

		};

		/**
		 * Send AJAX request.
		 *
		 * @since 0.2.2
		 *
		 * @param {Array} data The data received from the server.
		 */
		this.update = function( data ) {

			// Declare vars.
			var val;

			// Are we still in progress?
			if ( data.finished == 'false' ) {

				// Get current value of progress bar.
				val = me.bar.progressbar( 'value' ) || 0;

				// Are we still syncing group members?
				if ( data.members == 'done' ) {

					// Update progress bar label.
					me.label.html( me.label_complete.replace( '{{name}}', data.group_name ) );

					// Update progress bar.
					me.bar.progressbar( 'value', val + 1 );

				} else {

					// Update progress bar label.
					me.label.html( me.label_current.replace( '{{name}}', data.group_name ) );

					// Init progress bar if needed.
					if ( false === me.bar.progressbar( 'value' ) ) {
						me.bar.progressbar( 'value', 0 );
					}

				}

				// Trigger next batch.
				me.send();

			} else {

				// Update progress bar label.
				me.label.html( me.label_done );

				// Hide the progress bar.
				setTimeout(function () {
					me.bar.hide();
				}, 2000 );

			}

		};

		/**
		 * Send AJAX request.
		 *
		 * @since 0.2.2
		 */
		this.send = function() {

			// Use jQuery post.
			$.post(

				// URL to post to.
				BPGCS_Manual_Sync.settings.get_setting( 'ajax_url' ),

				// Token received by WordPress.
				{ action: 'sync_bp_and_civi' },

				// Callback.
				function( data, textStatus ) {

					// If success.
					if ( textStatus == 'success' ) {

						// Update progress bar.
						me.update( data );

					} else {

						// Show error.
						if ( console.log ) {
							console.log( textStatus );
						}

					}

				},

				// Expected format.
				'json'

			);

		};

	};

	// Init settings.
	BPGCS_Manual_Sync.settings.init();

	// Init Progress Bar.
	BPGCS_Manual_Sync.progress_bar.init();

} )( jQuery );



/**
 * Trigger dom_ready methods where necessary.
 *
 * @since 0.2.2
 */
jQuery(document).ready(function($) {

	// The DOM is loaded now.
	BPGCS_Manual_Sync.settings.dom_ready();
	BPGCS_Manual_Sync.progress_bar.dom_ready();

}); // End document.ready()

