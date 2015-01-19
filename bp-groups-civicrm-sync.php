<?php
/*
--------------------------------------------------------------------------------
Plugin Name: BP Groups CiviCRM Sync
Plugin URI: https://github.com/christianwach/bp-groups-civicrm-sync
Description: A port of the Drupal civicrm_og_sync module for WordPress that enables synchronisation between BuddyPress Groups and CiviCRM.
Author: Christian Wach
Version: 0.1
Author URI: http://haystack.co.uk
Text Domain: bp-groups-civicrm-sync
Domain Path: /languages
Depends: CiviCRM
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

// for debugging
define( 'BP_GROUPS_CIVICRM_SYNC_DEBUG', true );



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
			$bpcivisync_bp_check = '0';

			// get variables
			extract( $_POST );

			// did we ask to convert OG groups?
			if ( $bpcivisync_convert == '1' ) {

				// try and convert OG groups to BP groups
				$this->civi->convert_og_groups_to_bp_groups();
				return;

			}

			// did we ask to sync existing BP groups with Civi?
			if ( $bpcivisync_bp_check == '1' ) {

				// try and sync BP groups with Civi groups
				$this->sync_bp_and_civi();
				return;

			}

			// did we ask to check the sync of BP groups and Civi groups?
			if ( $bpcivisync_bp_check_sync == '1' ) {

				// check the sync between BP groups and Civi groups
				$this->check_sync_between_bp_and_civi();
				return;

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

		// init BP flags
		$checking_bp = false;

		// did we ask to check for group sync integrity?
		if ( isset( $_POST['bpcivisync_bp_check'] ) AND $_POST['bpcivisync_bp_check'] == '1' ) {

			// set flag
			$checking_bp = true;

		}

		// init OG flags
		$checking_og = false;
		$has_og_groups = false;

		// did we ask to check for OG groups?
		if ( isset( $_POST['bpcivisync_og_check'] ) AND $_POST['bpcivisync_og_check'] == '1' ) {

			// set flag
			$checking_og = true;

			// do we have any OG groups?
			$has_og_groups = $this->civi->has_og_groups();

		}

		// if we've updated...
		if ( isset( $_GET['updated'] ) ) {

			// are we checking OG?
			if ( $checking_og ) {

				// yes, did we get any?
				if ( $has_og_groups !== false ) {

					// show settings updated message
					echo '<div id="message" class="updated"><p>'.__( 'OG Groups found. You can now choose to migrate them to BP.', 'bp-groups-civicrm-sync' ).'</p></div>';

				} else {

					// show settings updated message
					echo '<div id="message" class="updated"><p>'.__( 'No OG Groups found.', 'bp-groups-civicrm-sync' ).'</p></div>';

				}

			} else {

				// are we checking BP?
				if ( $checking_bp ) {

				} else {

					// show settings updated message
					echo '<div id="message" class="updated"><p>'.__( 'Options saved.', 'bp-groups-civicrm-sync' ).'</p></div>';

				}

			}

		}

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

		// show BP to Civi
		$this->admin_form_bp_to_civi();

		// show BP and Civi Sync Check
		$this->admin_form_audit_bp_and_civi();

		// do we have any OG groups?
		if ( $checking_og AND $has_og_groups ) {

			// show OG to BP migration form
			$this->admin_form_og_to_bp();

		} else {

			// show heading
			echo '
			<hr>
			<h3>'.__( 'CiviCRM to BuddyPress Sync', 'bp-groups-civicrm-sync' ).'</h3>';

			// did we ask to check for OG groups?
			if ( $checking_og ) {

				// none were found
				echo '<p>'.__( 'No OG Groups found', 'bp-groups-civicrm-sync' ).'</p>
				';

			} else {

				echo '
				<table class="form-table">

					<tr valign="top">
						<th scope="row"><label for="bpcivisync_og_check">'.__( 'Check for OG groups', 'bp-groups-civicrm-sync' ).'</label></th>
						<td><input id="bpcivisync_og_check" name="bpcivisync_og_check" value="1" type="checkbox" /></td>
					</tr>

				</table>';

			}

		}

		// close div
		echo '

		</div>';

		// show submit button
		echo '

		<hr>
		<p class="submit">
			<input type="submit" name="bpcivisync_submit" value="'.__( 'Submit', 'bp-groups-civicrm-sync' ).'" class="button-primary" />
		</p>

		';

		// close form
		echo '

		</form>

		</div>
		'."\n\n\n\n";



	}



	/**
	 * Show our OG to BP admin option
	 *
	 * @return void
	 */
	public function admin_form_og_to_bp() {

		// show migration option
		echo '
		<hr>
		<h3>'.__( 'Convert OG groups in CiviCRM to BP groups', 'bp-groups-civicrm-sync' ).'</h3>

		<p>'.__( 'WARNING: this will probably only work when there are a small number of groups. If you have lots of groups, it would be worth writing some kind of chunked update routine. I will upgrade this plugin to do so at some point.', 'bp-groups-civicrm-sync' ).'</p>

		<table class="form-table">

			<tr valign="top">
				<th scope="row"><label for="bpcivisync_convert">'.__( 'Convert OG groups to BP groups', 'bp-groups-civicrm-sync' ).'</label></th>
				<td><input id="bpcivisync_convert" name="bpcivisync_convert" value="1" type="checkbox" /></td>
			</tr>

		</table>';

	}



	/**
	 * Show our BP to Civi admin option
	 *
	 * @return void
	 */
	public function admin_form_bp_to_civi() {

		// show heading
		echo '
		<hr>
		<h3>'.__( 'BuddyPress to CiviCRM Sync', 'bp-groups-civicrm-sync' ).'</h3>

		<p>'.__( 'WARNING: this will probably only work when there are a small number of groups. If you have lots of groups, it would be worth writing some kind of chunked update routine. I will upgrade this plugin to do so at some point.', 'bp-groups-civicrm-sync' ).'</p>

		<table class="form-table">

			<tr valign="top">
				<th scope="row"><label for="bpcivisync_bp_check">'.__( 'Sync BP Groups to CiviCRM', 'bp-groups-civicrm-sync' ).'</label></th>
				<td><input id="bpcivisync_bp_check" name="bpcivisync_bp_check" value="1" type="checkbox" /></td>
			</tr>

		</table>';

	}



	/**
	 * Show our BP and Civi Sync Checker
	 *
	 * @return void
	 */
	public function admin_form_audit_bp_and_civi() {

		// show heading
		echo '
		<hr>
		<h3>'.__( 'Check BuddyPress and CiviCRM Sync', 'bp-groups-civicrm-sync' ).'</h3>

		<p>'.__( 'Check this to find out if there are BuddyPress Groups with no CiviCRM Group and vice versa.', 'bp-groups-civicrm-sync' ).'</p>

		<table class="form-table">

			<tr valign="top">
				<th scope="row"><label for="bpcivisync_bp_check_sync">'.__( 'Check BP Groups and CiviCRM Groups', 'bp-groups-civicrm-sync' ).'</label></th>
				<td><input id="bpcivisync_bp_check_sync" name="bpcivisync_bp_check_sync" value="1" type="checkbox" /></td>
			</tr>

		</table>';

	}



	/**
	 * Sync BuddyPress groups to CiviCRM
	 *
	 * @return void
	 */
	public function sync_bp_and_civi() {

		// init or die
		if ( ! $this->civi->is_active() ) return;

		// get all BP groups (batching to come later)
		$groups = $this->bp->get_all_groups();

		/*
		print_r( array(
			'groups' => $groups,
		) ); die();
		*/

		// if we get some
		if ( count( $groups ) > 0 ) {

			// one at a time, then...
			foreach( $groups AS $group ) {

				// get the Civi group ID of this BuddyPress group
				$civi_group_id = $this->civi->find_group_id(
					$this->civi->get_group_sync_name( $group->id )
				);

				/*
				print_r( array(
					'group' => $group,
					//'_group' => $_group,
					'civi_group_id' => $civi_group_id,
				) ); die();
				*/

				// if we don't get an ID, create the group...
				if ( ! $civi_group_id ) {
					$this->bp->create_civi_group( $group->id, null, $group );
				} else {
					$this->bp->update_civi_group( $group->id, $group );
				}

				// sync members
				$this->sync_bp_and_civi_members( $group );

			}

		}

	}



	/**
	 * Sync BuddyPress group members to CiviCRM group membership
	 *
	 * @return void
	 */
	public function sync_bp_and_civi_members( $group ) {

		// params group members
		$params = array(
			'exclude_admins_mods' => 0,
			'per_page' => 100000,
			'group_id' => $group->id
		);

		// query group members
		if ( bp_group_has_members( $params ) ) {

			// one by one...
			while ( bp_group_members() ) {

				// set up member
				bp_group_the_member();

				// get user ID
				$user_id = bp_get_group_member_id();

				// update their membership
				$this->bp->civi_update_group_membership( $user_id, $group->id );

			}

		}

	}



	/**
	 * Check the Sync between BuddyPress groups and CiviCRM groups
	 *
	 * @return void
	 */
	public function check_sync_between_bp_and_civi() {

		// disable
		return;

		// init or die
		if ( ! $this->civi->is_active() ) return;

		// define get all groups params
		$params = array(
			'version' => 3,
			// define stupidly high limit, because API defaults to 25
			'options' => array(
				'limit' => '10000',
			),
		);

		// get all groups with no parent ID (get ALL for now)
		$all_groups = civicrm_api( 'group', 'get', $params );

		/*
		print_r( array(
			'method' => 'check_sync_between_bp_and_civi',
			'all_groups' => $all_groups,
		) ); die();
		*/

		// if we got some...
		if (

			$all_groups['is_error'] == 0 AND
			isset( $all_groups['values'] ) AND
			count( $all_groups['values'] ) > 0

		) {

			// loop through them
			foreach( $all_groups['values'] AS $civi_group ) {

				// is this group supposed to have a BP Group?
				$has_group = $this->civi->has_bp_group( $civi_group );

				// if so...
				if ( $has_group ) {

					// get the ID of the BP group it was supposed to sync with
					$group_id = $this->civi->get_bp_group_id_by_civi_group( $civi_group );

					// does this group exist?

				}

			}

		}

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



