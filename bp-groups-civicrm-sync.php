<?php
/**
 * Plugin Name: BP Groups CiviCRM Sync
 * Plugin URI: https://github.com/christianwach/bp-groups-civicrm-sync
 * GitHub Plugin URI: https://github.com/christianwach/bp-groups-civicrm-sync
 * Description: Enables two-way synchronisation between BuddyPress Groups and CiviCRM Groups.
 * Author: Christian Wach
 * Version: 0.4.1a
 * Author URI: http://haystack.co.uk
 * Text Domain: bp-groups-civicrm-sync
 * Domain Path: /languages
 * Depends: CiviCRM
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



// Set our version here.
define( 'BP_GROUPS_CIVICRM_SYNC_VERSION', '0.4.1a' );

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
	 * CiviCRM object.
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 * @access public
	 * @var object $civi The CiviCRM object.
	 */
	public $civicrm;

	/**
	 * BuddyPress object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $bp The BuddyPress object.
	 */
	public $bp;

	/**
	 * Admin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $admin The Admin object.
	 */
	public $admin;



	/**
	 * Constructor.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// Init loading process.
		add_action( 'plugins_loaded', [ $this, 'initialise' ] );

	}



	// -------------------------------------------------------------------------



	/**
	 * Initialise this plugin.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// Bail if no CiviCRM init function.
		if ( ! $this->check_dependencies() ) {
			return;
		}

		// Use translation.
		$this->enable_translation();

		// Bootstrap this plugin.
		$this->include_files();
		$this->setup_objects();

		/**
		 * Broadcast that this plugin is loaded.
		 *
		 * Used internally by included classes in order to bootstrap.
		 *
		 * @since 0.4
		 */
		do_action( 'bpgcs/loaded' );

	}



	/**
	 * Check plugin dependencies.
	 *
	 * If any of these checks fail, this plugin will not initialise.
	 *
	 * @since 0.4
	 *
	 * @return bool True if dependencies are present, false otherwise.
	 */
	public function check_dependencies() {

		// Bail if no CiviCRM init function.
		if ( ! function_exists( 'civi_wp' ) ) {
			return false;
		}

		// Bail if CiviCRM is not fully installed.
		if ( ! defined( 'CIVICRM_INSTALLED' ) ) {
			return false;
		}
		if ( ! CIVICRM_INSTALLED ) {
			return false;
		}

		// Bail if BuddyPress is not installed.
		if ( ! function_exists( 'buddypress' ) ) {
			return false;
		}

		// --<
		return true;

	}



	/**
	 * Include files.
	 *
	 * @since 0.3.6
	 */
	public function include_files() {

		// Load our class files.
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/bpgcs-civicrm.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/bpgcs-buddypress.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/bpgcs-admin.php';

	}



	/**
	 * Set up this plugin's objects.
	 *
	 * @since 0.3.6
	 */
	public function setup_objects() {

		// Instantiate objects.
		$this->civicrm = new BP_Groups_CiviCRM_Sync_CiviCRM( $this );
		$this->bp = new BP_Groups_CiviCRM_Sync_BuddyPress( $this );
		$this->admin = new BP_Groups_CiviCRM_Sync_Admin( $this );

	}



	// -------------------------------------------------------------------------



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



	/**
	 * Test if this plugin is network activated.
	 *
	 * @since 0.4
	 *
	 * @return bool $is_network_active True if network activated, false otherwise.
	 */
	public function is_network_activated() {

		// Only need to test once.
		static $is_network_active;
		if ( isset( $is_network_active ) ) {
			return $is_network_active;
		}

		// If not multisite, set flag and bail.
		if ( ! is_multisite() ) {
			$is_network_active = false;
			return $is_network_active;
		}

		// Make sure plugin file is included when outside admin.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		// Get path from 'plugins' directory to this plugin.
		$this_plugin = plugin_basename( BP_GROUPS_CIVICRM_SYNC_FILE );

		// Test if network active.
		$is_network_active = is_plugin_active_for_network( $this_plugin );

		// --<
		return $is_network_active;

	}



	// -------------------------------------------------------------------------



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

/*
 * Uninstall uses the 'uninstall.php' method.
 * @see https://developer.wordpress.org/reference/functions/register_uninstall_hook/
 */



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
			$link = add_query_arg( [ 'page' => 'bp_groups_civicrm_sync_parent' ], admin_url( 'admin.php' ) );
		}

		// Add settings link.
		$links[] = '<a href="' . $link . '">' . esc_html__( 'Settings', 'bp-groups-civicrm-sync' ) . '</a>';

		// Add Paypal link.
		$paypal = 'https://www.paypal.me/interactivist';
		$links[] = '<a href="' . $paypal . '" target="_blank">' . __( 'Donate!', 'bp-groups-civicrm-sync' ) . '</a>';

	}

	// --<
	return $links;

}

// Add filters for the above.
add_filter( 'network_admin_plugin_action_links', 'bp_groups_civicrm_sync_plugin_action_links', 10, 2 );
add_filter( 'plugin_action_links', 'bp_groups_civicrm_sync_plugin_action_links', 10, 2 );
