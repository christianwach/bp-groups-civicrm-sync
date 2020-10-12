<?php /*
--------------------------------------------------------------------------------
Plugin Name: BP Groups CiviCRM Sync
Plugin URI: https://github.com/christianwach/bp-groups-civicrm-sync
Description: A port of the Drupal civicrm_og_sync module for WordPress that enables two-way synchronisation between BuddyPress groups and CiviCRM groups.
Author: Christian Wach
Version: 0.3.7
Author URI: http://haystack.co.uk
Text Domain: bp-groups-civicrm-sync
Domain Path: /languages
Depends: CiviCRM
--------------------------------------------------------------------------------
*/



// Set our version here.
define( 'BP_GROUPS_CIVICRM_SYNC_VERSION', '0.3.7' );

// Store reference to this file.
if ( ! defined( 'BP_GROUPS_CIVICRM_SYNC_FILE' ) ) {
	define( 'BP_GROUPS_CIVICRM_SYNC_FILE', __FILE__ );
}

// Store URL to this plugin's directory.
if ( ! defined( 'BP_GROUPS_CIVICRM_SYNC_URL' ) ) {
	define( 'BP_GROUPS_CIVICRM_SYNC_URL', plugin_dir_url( BP_GROUPS_CIVICRM_SYNC_FILE ) );
}

// Store PATH to this plugin's directory.
if ( ! defined( 'BP_GROUPS_CIVICRM_SYNC_PATH' ) ) {
	define( 'BP_GROUPS_CIVICRM_SYNC_PATH', plugin_dir_path( BP_GROUPS_CIVICRM_SYNC_FILE ) );
}

// For debugging.
if ( ! defined( 'BP_GROUPS_CIVICRM_SYNC_DEBUG' ) ) {
	define( 'BP_GROUPS_CIVICRM_SYNC_DEBUG', false );
}



/**
 * BP Groups CiviCRM Sync Class.
 *
 * A class that encapsulates plugin functionality.
 *
 * @since 0.1
 */
class BP_Groups_CiviCRM_Sync {

	/**
	 * CiviCRM utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $civi The CiviCRM utilities object.
	 */
	public $civi;

	/**
	 * BuddyPress utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $bp The BuddyPress utilities object.
	 */
	public $bp;

	/**
	 * Admin utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $admin The Admin utilities object.
	 */
	public $admin;



	/**
	 * Initialises this object.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// Init loading process
		$this->initialise();

	}



	//##########################################################################



	/**
	 * Do stuff on plugin init.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// Use translation files.
		add_action( 'plugins_loaded', [ $this, 'enable_translation' ] );

		// Include files.
		$this->include_files();

		// Set up objects.
		$this->setup_objects();

		// Set up references.
		$this->setup_references();

	}



	/**
	 * Do stuff on plugin activation.
	 *
	 * @since 0.1
	 */
	public function activate() {

		// Setup plugin admin.
		$this->admin->activate();

	}



	/**
	 * Do stuff on plugin deactivation.
	 *
	 * @since 0.1
	 */
	public function deactivate() {

		// Tear down plugin admin.
		$this->admin->deactivate();

	}



	//##########################################################################



	/**
	 * Load translation files.
	 *
	 * A good reference on how to implement translation in WordPress:
	 * http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
	 *
	 * @since 0.1
	 */
	public function enable_translation() {

		// Load translations if there are any.
		load_plugin_textdomain(
			'bp-groups-civicrm-sync', // Unique name.
			false, // Deprecated argument.
			dirname( plugin_basename( __FILE__ ) ) . '/languages/' // Relative path to translation files.
		);

	}



	/**
	 * Include files.
	 *
	 * @since 0.3.6
	 */
	public function include_files() {

		// Load our CiviCRM utility methods class.
		require( BP_GROUPS_CIVICRM_SYNC_PATH . 'bp-groups-civicrm-sync-civi.php' );

		// Load our BuddyPress utility methods class.
		require( BP_GROUPS_CIVICRM_SYNC_PATH . 'bp-groups-civicrm-sync-bp.php' );

		// Load our Admin utility class.
		require( BP_GROUPS_CIVICRM_SYNC_PATH . 'bp-groups-civicrm-sync-admin.php' );

	}



	/**
	 * Set up this plugin's objects.
	 *
	 * @since 0.3.6
	 */
	public function setup_objects() {

		// Instantiate CiviCRM object.
		$this->civi = new BP_Groups_CiviCRM_Sync_CiviCRM( $this );

		// Instantiate BuddyPress object.
		$this->bp = new BP_Groups_CiviCRM_Sync_BuddyPress( $this );

		// Instantiate Admin object.
		$this->admin = new BP_Groups_CiviCRM_Sync_Admin( $this );

	}



	/**
	 * Set up references to other objects.
	 *
	 * @since 0.3.6
	 */
	public function setup_references() {

		// Store references.
		$this->civi->set_references( $this->bp, $this->admin );
		$this->bp->set_references( $this->civi, $this->admin );
		$this->admin->set_references( $this->bp, $this->civi );

	}



} // Class ends.



/**
 * Utility for retrieving a reference to this plugin.
 *
 * @since 0.3.6
 *
 * @return object $bp_groups_civicrm_sync The plugin reference.
 */
function bp_groups_civicrm_sync() {

	// Hold the plugin instance in a global variable.
	global $bp_groups_civicrm_sync;

	// Instantiate plugin if not yet instantiated.
	if ( ! isset( $bp_groups_civicrm_sync ) ) {
		$bp_groups_civicrm_sync = new BP_Groups_CiviCRM_Sync();
	}

	// --<
	return $bp_groups_civicrm_sync;

}

// Init plugin.
bp_groups_civicrm_sync();

// Activation.
register_activation_hook( __FILE__, [ bp_groups_civicrm_sync(), 'activate' ] );

// Deactivation.
register_deactivation_hook( __FILE__, [ bp_groups_civicrm_sync(), 'deactivate' ] );

// Uninstall will use the 'uninstall.php' method when fully built.
// See: http://codex.wordpress.org/Function_Reference/register_uninstall_hook



/**
 * Utility to add link to settings page.
 *
 * @since 0.1
 *
 * @param array $links The existing links array.
 * @param str $file The name of the plugin file.
 * @return array $links The modified links array.
 */
function bp_groups_civicrm_sync_plugin_action_links( $links, $file ) {

	// Add settings link.
	if ( $file == plugin_basename( dirname( __FILE__ ) . '/bp-groups-civicrm-sync.php' ) ) {

		// Is this Network Admin?
		if ( is_network_admin() ) {
			$link = add_query_arg( [ 'page' => 'bp_groups_civicrm_sync_parent' ], network_admin_url( 'settings.php' ) );
		} else {
			$link = add_query_arg( [ 'page' => 'bp_groups_civicrm_sync_parent' ], admin_url( 'options-general.php' ) );
		}

		// Add settings link.
		$links[] = '<a href="' . $link . '">' . esc_html__( 'Settings', 'bp-groups-civicrm-sync' ) . '</a>';

		// Add Paypal link.
		$paypal = 'https://www.paypal.me/interactivist';
		$links[] = '<a href="' . $paypal . '" target="_blank">' . __( 'Donate!', 'civicrm-admin-utilities' ) . '</a>';

	}

	// --<
	return $links;

}

// Add filters for the above.
add_filter( 'network_admin_plugin_action_links', 'bp_groups_civicrm_sync_plugin_action_links', 10, 2 );
add_filter( 'plugin_action_links', 'bp_groups_civicrm_sync_plugin_action_links', 10, 2 );



