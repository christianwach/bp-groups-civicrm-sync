<?php
/**
 * BP Groups CiviCRM Sync
 *
 * Plugin Name:       BP Groups CiviCRM Sync
 * Description:       Enables two-way synchronisation between BuddyPress Groups and CiviCRM Groups.
 * Version:           0.5.3a
 * Plugin URI:        https://github.com/christianwach/bp-groups-civicrm-sync
 * GitHub Plugin URI: https://github.com/christianwach/bp-groups-civicrm-sync
 * Author:            Christian Wach
 * Author URI:        https://haystack.co.uk
 * Requires at least: 4.9
 * Requires PHP:      7.4
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain:       bp-groups-civicrm-sync
 * Domain Path:       /languages
 *
 * @package BP_Groups_CiviCRM_Sync
 * @link    https://github.com/christianwach/bp-groups-civicrm-sync
 * @license GPL v2 or later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Set our version here.
define( 'BP_GROUPS_CIVICRM_SYNC_VERSION', '0.5.3a' );

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
 * Plugin class.
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
	 * @var BP_Groups_CiviCRM_Sync_CiviCRM
	 */
	public $civicrm;

	/**
	 * BuddyPress object.
	 *
	 * @since 0.1
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_BuddyPress
	 */
	public $bp;

	/**
	 * Admin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_Admin
	 */
	public $admin;

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// Always include WP-CLI command.
		require_once BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/wp-cli/wp-cli-bpgcs.php';

		// Init loading process.
		add_action( 'plugins_loaded', [ $this, 'initialise' ] );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Initialise this plugin.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// Only do this once.
		static $done;
		if ( isset( $done ) && true === $done ) {
			return;
		}

		// Bail if no CiviCRM init function.
		if ( ! $this->check_dependencies() ) {
			return;
		}

		// Use translation.
		$this->enable_translation();

		// Bootstrap this plugin.
		$this->include_files();
		$this->setup_objects();
		$this->register_hooks();

		/**
		 * Broadcast that this plugin is loaded.
		 *
		 * Used internally by included classes in order to bootstrap.
		 *
		 * @since 0.4
		 */
		do_action( 'bpgcs/loaded' );

		// We're done.
		$done = true;

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
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/civicrm/class-civicrm.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/buddypress/class-buddypress.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/admin/class-admin.php';

	}

	/**
	 * Set up this plugin's objects.
	 *
	 * @since 0.3.6
	 */
	public function setup_objects() {

		// Instantiate objects.
		$this->civicrm = new BP_Groups_CiviCRM_Sync_CiviCRM( $this );
		$this->bp      = new BP_Groups_CiviCRM_Sync_BuddyPress( $this );
		$this->admin   = new BP_Groups_CiviCRM_Sync_Admin( $this );

	}

	/**
	 * Registers hooks.
	 *
	 * @since 0.5.2
	 */
	public function register_hooks() {

		// Add links to settings page.
		add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );

	}

	// -----------------------------------------------------------------------------------

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

	// -----------------------------------------------------------------------------------

	/**
	 * Load translation files.
	 *
	 * A good reference on how to implement translation in WordPress:
	 *
	 * @see http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
	 *
	 * @since 0.1
	 */
	public function enable_translation() {

		// Load translations if there are any.
		// phpcs:ignore WordPress.WP.DeprecatedParameters.Load_plugin_textdomainParam2Found
		load_plugin_textdomain(
			'bp-groups-civicrm-sync', // Unique name.
			false, // Deprecated argument.
			dirname( plugin_basename( __FILE__ ) ) . '/languages/' // Relative path to translation files.
		);

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Write to the error log.
	 *
	 * @since 0.4.4
	 *
	 * @param array $data The data to write to the log file.
	 */
	public function log_error( $data = [] ) {

		// Skip if not debugging.
		if ( BP_GROUPS_CIVICRM_SYNC_DEBUG === false ) {
			return;
		}

		// Skip if empty.
		if ( empty( $data ) ) {
			return;
		}

		// Format data.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$error = print_r( $data, true );

		// Write to log file.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $error );

	}

	/**
	 * Write a message to the log file.
	 *
	 * @since 0.4.4
	 *
	 * @param string $message The message to write to the log file.
	 */
	public function log_message( $message = '' ) {

		// Skip if not debugging.
		if ( BP_GROUPS_CIVICRM_SYNC_DEBUG === false ) {
			return;
		}

		// Write to log file.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $message );

	}

	/**
	 * Adds utility links to settings page.
	 *
	 * @since 0.1
	 * @since 0.5.2 Moved into plugin class.
	 *
	 * @param array  $links The existing links array.
	 * @param string $file The name of the plugin file.
	 * @return array $links The modified links array.
	 */
	public function action_links( $links, $file ) {

		// Bail if not this plugin.
		if ( plugin_basename( dirname( __FILE__ ) . '/bp-groups-civicrm-sync.php' ) !== $file ) {
			return $links;
		}

		// Add links only when CiviCRM is fully installed.
		if ( ! defined( 'CIVICRM_INSTALLED' ) || ! CIVICRM_INSTALLED ) {
			return $links;
		}

		// Bail if CiviCRM plugin is not present.
		if ( ! function_exists( 'civi_wp' ) ) {
			return $links;
		}

		// Bail if BuddyPress plugin is not present.
		if ( ! function_exists( 'buddypress' ) ) {
			return $links;
		}

		// Add settings link if not network activated and not viewing network admin.
		$link    = add_query_arg( [ 'page' => 'bpgcs_settings' ], admin_url( 'admin.php' ) );
		$links[] = '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Settings', 'bp-groups-civicrm-sync' ) . '</a>';

		// Always add Paypal link.
		$paypal  = 'https://www.paypal.me/interactivist';
		$links[] = '<a href="' . esc_url( $paypal ) . '" target="_blank">' . __( 'Donate!', 'bp-groups-civicrm-sync' ) . '</a>';

		// --<
		return $links;

	}

}

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

/*
 * Uninstall uses the 'uninstall.php' method.
 * @see https://developer.wordpress.org/reference/functions/register_uninstall_hook/
 */
