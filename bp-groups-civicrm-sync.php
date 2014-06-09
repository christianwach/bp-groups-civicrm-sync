<?php
/*
--------------------------------------------------------------------------------
Plugin Name: BP Groups CiviCRM Sync
Description: A port of the Drupal civicrm_og_sync module for WordPress that enables synchronisation between BuddyPress Groups and CiviCRM. Does not rely on any core CiviCRM files, since any required (or adapted) methods are included.
Version: 0.1
Author: Christian Wach
Author URI: http://haystack.co.uk
Plugin URI: http://haystack.co.uk
--------------------------------------------------------------------------------
*/



// set our version here
define( 'BP_GROUPS_CIVICRM_SYNC_VERSION', '0.1' );

// store reference to this file
if ( !defined( 'BP_GROUPS_CIVICRM_SYNC_FILE' ) ) {
	define( 'BP_GROUPS_CIVICRM_SYNC_FILE', __FILE__ );
}

// store URL to this plugin's directory
if ( !defined( 'BP_GROUPS_CIVICRM_SYNC_URL' ) ) {
	define( 'BP_GROUPS_CIVICRM_SYNC_URL', plugin_dir_url( BP_GROUPS_CIVICRM_SYNC_FILE ) );
}
// store PATH to this plugin's directory
if ( !defined( 'BP_GROUPS_CIVICRM_SYNC_PATH' ) ) {
	define( 'BP_GROUPS_CIVICRM_SYNC_PATH', plugin_dir_path( BP_GROUPS_CIVICRM_SYNC_FILE ) );
}



/*
--------------------------------------------------------------------------------
BP_Groups_CiviCRM_Sync Class
--------------------------------------------------------------------------------
*/

class BP_Groups_CiviCRM_Sync {

	/** 
	 * Properties
	 */
	
	// CiviCRM utilities class
	public $civi;
	
	// BuddyPress utilities class
	public $bp;
	
	
	
	/** 
	 * Initialises this object
	 * 
	 * @return object
	 */
	function __construct() {
	
		// init loading process
		$this->initialise();
		
		// --<
		return $this;

	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * Do stuff on plugin init
	 * 
	 * @return void
	 */
	public function initialise() {
		
		// use translation files
		add_action( 'plugins_loaded', array( $this, 'enable_translation' ) );
		
		// load our cloned CiviCRM utility functions class
		require( BP_GROUPS_CIVICRM_SYNC_PATH . 'bp-groups-civicrm-sync-civi.php' );
		
		// initialise
		$this->civi = new BP_Groups_CiviCRM_Sync_CiviCRM;
	
		// load our BuddyPress utility functions class
		require( BP_GROUPS_CIVICRM_SYNC_PATH . 'bp-groups-civicrm-sync-bp.php' );
		
		// initialise
		$this->bp = new BP_Groups_CiviCRM_Sync_BuddyPress;
		
		// store references
		$this->civi->set_references( $this->bp );
		$this->bp->set_references( $this->civi );
	
		// is this the back end?
		if ( is_admin() ) {
		
			// multisite?
			if ( is_multisite() ) {
	
				// add menu to Network submenu
				add_action( 'network_admin_menu', array( $this, 'add_admin_menu' ), 30 );
			
			} else {
			
				// add menu to Network submenu
				add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 30 );
			
			}
			
		}
		
	}
	
	
		
	/**
	 * Do stuff on plugin activation
	 * 
	 * @return void
	 */
	public function activate() {
		
		// create a meta group to hold all BuddyPress groups
		$this->civi->create_meta_group();
		
	}
	
	
		
	/**
	 * Do stuff on plugin deactivation
	 * 
	 * @return void
	 */
	public function deactivate() {
		
	}
	
	
		
	//##########################################################################
	
	
	
	/** 
	 * Load translation files
	 * A good reference on how to implement translation in WordPress:
	 * http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
	 * 
	 * @return void
	 */
	public function enable_translation() {
		
		// not used, as there are no translations as yet
		load_plugin_textdomain(
		
			// unique name
			'bp-groups-civicrm-sync', 
			
			// deprecated argument
			false,
			
			// relative path to directory containing translation files
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'

		);
		
	}
	
	
	
	/** 
	 * Add an admin page for this plugin
	 * 
	 * @return void
	 */
	public function add_admin_menu() {
		
		// we must be network admin in multisite
		if ( is_multisite() AND !is_super_admin() ) { return false; }
		
		// check user permissions
		if ( !current_user_can('manage_options') ) { return false; }
		
		// try and update options
		$saved = $this->update_options();

		// multisite?
		if ( is_multisite() ) {
				
			// add the admin page to the Network Settings menu
			$page = add_submenu_page( 
		
				'settings.php', 
				__( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ), 
				__( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ), 
				'manage_options', 
				'bpcivisync_admin_page', 
				array( $this, 'admin_form' )
			
			);
		
		} else {
		
			// add the admin page to the Settings menu
			$page = add_options_page( 
		
				'settings.php', 
				__( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ), 
				__( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ), 
				'manage_options', 
				'bpcivisync_admin_page', 
				array( $this, 'admin_form' )
			
			);
		
		}
		
		// add styles only on our admin page, see:
		// http://codex.wordpress.org/Function_Reference/wp_enqueue_script#Load_scripts_only_on_plugin_pages
		//add_action( 'admin_print_styles-'.$page, array( $this, 'add_admin_styles' ) );
	
	}
	
	
	
	/**
	 * Enqueue any styles and scripts needed by our admin page
	 * 
	 * @return void
	 */
	public function add_admin_styles() {
		
		// add admin css
		wp_enqueue_style(
			
			'bpcivisync-admin-style', 
			BP_GROUPS_CIVICRM_SYNC_URL . 'assets/css/admin.css',
			null,
			BP_GROUPS_CIVICRM_SYNC_VERSION,
			'all' // media
			
		);
		
	}
	
	
	
	/**
	 * Update options supplied by our admin page
	 * 
	 * @return void
	 */
	public function update_options() {
		
	 	// was the form submitted?
		if( isset( $_POST['bpcivisync_submit'] ) ) {
			
			// check that we trust the source of the data
			check_admin_referer( 'bpcivisync_admin_action', 'bpcivisync_nonce' );
			
			// init vars
			$bpcivisync_convert = '0';
			
			// get variables
			extract( $_POST );
			
			// did we ask to convert OG groups?
			if ( $bpcivisync_convert == '1' ) {
			
				// try and convert OG groups to BP groups
				$this->civi->convert_og_groups_to_bp_groups();

			}
			
		}
		
	}
	
	
	
	/**
	 * Show our admin page
	 * 
	 * @return void
	 */
	public function admin_form() {
	
		// we must be network admin in multisite
		if ( is_multisite() AND !is_super_admin() ) {
			
			// disallow
			wp_die( __( 'You do not have permission to access this page.', 'bp-groups-civicrm-sync' ) );
			
		}
		
		// if we've updated...
		if ( isset( $_GET['updated'] ) ) {
		
			// show message
			echo '<div id="message" class="updated"><p>'.__( 'Options saved.', 'bp-groups-civicrm-sync' ).'</p></div>';
			
		}
		
		// do we have any OG groups?
		$og = $this->civi->has_og_groups();
		
		// sanitise admin page url
		$url = $_SERVER['REQUEST_URI'];
		$url_array = explode( '&', $url );
		if ( is_array( $url_array ) ) { $url = $url_array[0]; }
		
		// open admin page
		echo '
		
		<div class="wrap" id="bpcivisync_admin_wrapper">

		<div class="icon32" id="icon-options-general"><br/></div>

		<h2>'.__( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ).'</h2>

		<form method="post" action="'.htmlentities($url.'&updated=true').'">

		'.wp_nonce_field( 'bpcivisync_admin_action', 'bpcivisync_nonce', true, false ).'
		'.wp_referer_field( false ).'

		';
		
		// open div
		echo '<div id="bpcivisync_admin_options">
		
		';

		// do we have any OG groups?
		if ( $og ) {
		
			// show migration option
			echo '
			<h3>'.__( 'Convert OG groups in CiviCRM to BP groups', 'bp-groups-civicrm-sync' ).'</h3>
			
			<p>WARNING: this will probably only work when there are a small number of groups. If you have lots of groups, it would be worth writing some kind of chunked update routine. I will upgrade this plugin to do so at some point.</p>

			<table class="form-table">

				<tr valign="top">
					<th scope="row"><label for="bpcivisync_convert">'.__( 'Convert OG groups to BP groups', 'bp-groups-civicrm-sync' ).'</label></th>
					<td><input id="bpcivisync_convert" name="bpcivisync_convert" value="1" type="checkbox" /></td>
				</tr>

			</table>';
		
		} else {
		
			echo '<p>BP Groups CiviCRM Sync is up to date</p>';
		
		}
		
		// close div
		echo '
		
		</div>';
		
		// do we have any OG groups?
		if ( $og ) {
		
			// show submit button
			echo '
		
			<p class="submit">
				<input type="submit" name="bpcivisync_submit" value="'.__( 'Submit', 'bp-groups-civicrm-sync' ).'" class="button-primary" />
			</p>
		
			';
		
		}
		
		// close form
		echo '

		</form>

		</div>
		'."\n\n\n\n";



	}
	
	
	
} // class ends



// declare as global
global $bp_groups_civicrm_sync;

// init plugin
$bp_groups_civicrm_sync = new BP_Groups_CiviCRM_Sync;

// activation
register_activation_hook( __FILE__, array( $bp_groups_civicrm_sync, 'activate' ) );

// deactivation
register_deactivation_hook( __FILE__, array( $bp_groups_civicrm_sync, 'deactivate' ) );

// uninstall will use the 'uninstall.php' method when fully built
// see: http://codex.wordpress.org/Function_Reference/register_uninstall_hook



