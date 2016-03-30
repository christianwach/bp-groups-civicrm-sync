/**
 * BP Groups CiviCRM Sync Utilities Javascript.
 *
 * Implements sync functionality on the plugin's Utilities admin page.
 *
 * @package WordPress
 * @subpackage BP_Groups_CiviCRM_Sync_Utilities
 */



/**
 * Create BP Groups CiviCRM Sync Utilities object.
 *
 * This works as a "namespace" of sorts, allowing us to hang properties, methods
 * and "sub-namespaces" from it.
 *
 * @since 0.2.2
 */
var BP_Groups_CiviCRM_Sync_Utilities = BP_Groups_CiviCRM_Sync_Utilities || {};



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
	BP_Groups_CiviCRM_Sync_Utilities.settings = new function() {

		// store object refs
		var me = this;

		/**
		 * Initialise Settings.
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

		};

	};

	/**
	 * Create Progress Bar Object.
	 *
	 * @since 0.2.2
	 */
	BP_Groups_CiviCRM_Sync_Utilities.progress_bar = new function() {

		// store object refs
		var me = this;

		/**
		 * Initialise CommentPress JSTOR.
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

			// enable listeners
			me.listeners();

		};

		/**
		 * Initialise listeners.
		 *
		 * This method should only be called once.
		 *
		 * @since 0.2.2
		 */
		this.listeners = function() {

		};

	};

	// init settings
	BP_Groups_CiviCRM_Sync_Utilities.settings.init();

	// init Progress Bar
	BP_Groups_CiviCRM_Sync_Utilities.progress_bar.init();

} )( jQuery );



/**
 * Trigger dom_ready methods where necessary.
 *
 * @since 0.2.2
 */
jQuery(document).ready(function($) {

	// The DOM is loaded now
	BP_Groups_CiviCRM_Sync_Utilities.settings.dom_ready();

	// The DOM is loaded now
	BP_Groups_CiviCRM_Sync_Utilities.progress_bar.dom_ready();

}); // end document.ready()

