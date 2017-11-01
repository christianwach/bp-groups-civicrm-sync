<?php

/**
 * BP Groups CiviCRM Sync Admin Class.
 *
 * A class that encapsulates admin functionality.
 *
 * @since 0.1
 */
class BP_Groups_CiviCRM_Sync_Admin {

	/**
	 * Plugin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $parent_obj The plugin object.
	 */
	public $parent_obj;

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
	 * Settings page reference.
	 *
	 * @since 0.1
	 * @access public
	 * @var str $settings The Settings page reference.
	 */
	public $settings_page;

	/**
	 * Manual Sync page reference.
	 *
	 * @since 0.1
	 * @access public
	 * @var str $sync_page The Manual Sync page reference.
	 */
	public $sync_page;

	/**
	 * Plugin version.
	 *
	 * @since 0.1
	 * @access public
	 * @var str $plugin_version The Plugin version.
	 */
	public $plugin_version;

	/**
	 * Settings array.
	 *
	 * @since 0.1
	 * @access public
	 * @var array $settings The Settings array.
	 */
	public $settings = array();



	/**
	 * Initialise this object.
	 *
	 * @since 0.1
	 *
	 * @param object $parent_obj The parent object.
	 */
	function __construct( $parent_obj ) {

		// store reference to parent
		$this->parent_obj = $parent_obj;

		// add action for admin init
		add_action( 'plugins_loaded', array( $this, 'initialise' ) );

	}



	/**
	 * Set references to other objects.
	 *
	 * @since 0.1
	 *
	 * @param object $bp_object Reference to this plugin's BP object.
	 * @param object $civi_object Reference to this plugin's CiviCRM object.
	 */
	public function set_references( &$bp_object, &$civi_object ) {

		// store BuddyPress reference
		$this->bp = $bp_object;

		// store
		$this->civi = $civi_object;

	}



	/**
	 * Perform activation tasks.
	 *
	 * @since 0.1
	 */
	public function activate() {

		// store version for later reference
		$this->store_version();

		// add settings option only if it does not exist
		if ( 'fgffgs' == get_option( 'bp_groups_civicrm_sync_settings', 'fgffgs' ) ) {

			// store default settings
			add_option( 'bp_groups_civicrm_sync_settings', $this->settings_get_default() );

		}

	}



	/**
	 * Perform deactivation tasks.
	 *
	 * @since 0.1
	 */
	public function deactivate() {

		// we delete our options in uninstall.php

	}



	/**
	 * Initialise when all plugins are loaded.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// load plugin version
		$this->plugin_version = get_option( 'bp_groups_civicrm_sync_version', false );

		// upgrade version if needed
		if ( $this->plugin_version != BP_GROUPS_CIVICRM_SYNC_VERSION ) $this->store_version();

		// load settings array
		$this->settings = get_option( 'bp_groups_civicrm_sync_settings', $this->settings );

		// is this the back end?
		if ( is_admin() ) {

			// add AJAX handler
			add_action( 'wp_ajax_sync_bp_and_civi', array( $this, 'sync_bp_and_civi' ) );

			// multisite?
			if ( is_multisite() ) {

				// add menu to Network submenu
				add_action( 'network_admin_menu', array( $this, 'admin_menu' ), 30 );

			} else {

				// add menu to Network submenu
				add_action( 'admin_menu', array( $this, 'admin_menu' ), 30 );

			}

		}

	}



	/**
	 * Store the plugin version.
	 *
	 * @since 0.1
	 */
	public function store_version() {

		// store version
		update_option( 'bp_groups_civicrm_sync_version', BP_GROUPS_CIVICRM_SYNC_VERSION );

	}



	//##########################################################################



	/**
	 * Add this plugin's Settings Page to the WordPress admin menu.
	 *
	 * @since 0.1
	 */
	public function admin_menu() {

		// we must be network admin in multisite
		if ( is_multisite() AND ! is_super_admin() ) { return false; }

		// check user permissions
		if ( ! current_user_can('manage_options') ) { return false; }

		// multisite?
		if ( is_multisite() ) {

			// add the admin page to the Network Settings menu
			$this->parent_page = add_submenu_page(
				'settings.php',
				__( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ),
				__( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ),
				'manage_options',
				'bp_groups_civicrm_sync_parent',
				array( $this, 'page_settings' )
			);

		} else {

			// add the admin page to the Settings menu
			$this->parent_page = add_options_page(
				__( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ),
				__( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ),
				'manage_options',
				'bp_groups_civicrm_sync_parent',
				array( $this, 'page_settings' )
			);

		}

		// add styles and scripts only on our Settings admin page
		// @see wp-admin/admin-header.php
		//add_action( 'admin_print_styles-' . $this->parent_page, array( $this, 'admin_settings_styles' ) );
		//add_action( 'admin_print_scripts-' . $this->parent_page, array( $this, 'admin_settings_scripts' ) );
		add_action( 'admin_head-' . $this->parent_page, array( $this, 'admin_head' ), 50 );
		add_action( 'admin_head-' . $this->parent_page, array( $this, 'admin_menu_highlight' ), 50 );

		// add settings page
		$this->settings_page = add_submenu_page(
			'bp_groups_civicrm_sync_parent', // parent slug
			__( 'BP Groups CiviCRM Sync: Settings', 'bp-groups-civicrm-sync' ), // page title
			__( 'Settings', 'bp-groups-civicrm-sync' ), // menu title
			'manage_options', // required caps
			'bp_groups_civicrm_sync_settings', // slug name
			array( $this, 'page_settings' ) // callback
		);

		// add styles and scripts only on our Settings admin page
		// @see wp-admin/admin-header.php
		//add_action( 'admin_print_styles-' . $this->settings_page, array( $this, 'admin_settings_styles' ) );
		//add_action( 'admin_print_scripts-' . $this->settings_page, array( $this, 'admin_settings_scripts' ) );
		add_action( 'admin_head-' . $this->settings_page, array( $this, 'admin_head' ), 50 );
		add_action( 'admin_head-' . $this->settings_page, array( $this, 'admin_menu_highlight' ), 50 );

		// add utilities page
		$this->sync_page = add_submenu_page(
			'bp_groups_civicrm_sync_parent', // parent slug
			__( 'BP Groups CiviCRM Sync: Sync', 'bp-groups-civicrm-sync' ), // page title
			__( 'Utilities', 'bp-groups-civicrm-sync' ), // menu title
			'manage_options', // required caps
			'bp_groups_civicrm_sync_utilities', // slug name
			array( $this, 'page_utilities' ) // callback
		);

		// add styles and scripts only on our Utilities admin page
		// @see wp-admin/admin-header.php
		add_action( 'admin_print_styles-' . $this->sync_page, array( $this, 'admin_utilities_styles' ) );
		add_action( 'admin_print_scripts-' . $this->sync_page, array( $this, 'admin_utilities_scripts' ) );
		add_action( 'admin_head-' . $this->sync_page, array( $this, 'admin_head' ), 50 );
		add_action( 'admin_head-' . $this->sync_page, array( $this, 'admin_menu_highlight' ), 50 );

		// try and update options
		$this->settings_update_router();

	}



	/**
	 * This tells WP to highlight the plugin's menu item, regardless of which
	 * actual admin screen we are on.
	 *
	 * @since 0.1
	 *
	 * @global string $plugin_page The slug of the current page.
	 * @global string $submenu_file The slug of the submenu file.
	 */
	public function admin_menu_highlight() {

		global $plugin_page, $submenu_file;

		// define subpages
		$subpages = array(
		 	'bp_groups_civicrm_sync_settings',
		 	'bp_groups_civicrm_sync_utilities',
		 );

		// This tweaks the Settings subnav menu to show only one menu item
		if ( in_array( $plugin_page, $subpages ) ) {
			$plugin_page = 'bp_groups_civicrm_sync_parent';
			$submenu_file = 'bp_groups_civicrm_sync_parent';
		}

	}



	/**
	 * Initialise plugin help.
	 *
	 * @since 0.1
	 */
	public function admin_head() {

		// there's a new screen object for help in 3.3
		$screen = get_current_screen();

		// use method in this class
		$this->admin_help( $screen );

	}



	/**
	 * Enqueue any styles needed by our Utilities admin page.
	 *
	 * @since 0.2.2
	 */
	public function admin_utilities_styles() {

		// enqueue css
		wp_enqueue_style(
			'bgcs-utilities-style',
			BP_GROUPS_CIVICRM_SYNC_URL . 'assets/css/bgcs-admin-utilities.css',
			null,
			BP_GROUPS_CIVICRM_SYNC_VERSION,
			'all' // media
		);

	}



	/**
	 * Enqueue any scripts needed by our Utilities admin page.
	 *
	 * @since 0.2.2
	 */
	public function admin_utilities_scripts() {

		// enqueue javascript
		wp_enqueue_script(
			'bgcs-utilities-js',
			BP_GROUPS_CIVICRM_SYNC_URL . 'assets/js/bgcs-admin-utilities.js',
			array( 'jquery', 'jquery-ui-core', 'jquery-ui-progressbar' ),
			BP_GROUPS_CIVICRM_SYNC_VERSION
		);

		// init localisation
		$localisation = array(
			'total' => __( '{{total}} groups to sync...', 'bp-groups-civicrm-sync' ),
			'current' => __( 'Processing group "{{name}}"', 'bp-groups-civicrm-sync' ),
			'complete' => __( 'Processing group "{{name}}" complete', 'bp-groups-civicrm-sync' ),
			'done' => __( 'All done!', 'bp-groups-civicrm-sync' ),
		);

		// init settings
		$settings = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'total_groups' => groups_get_total_group_count(),
		);

		// localisation array
		$vars = array(
			'localisation' => $localisation,
			'settings' => $settings,
		);

		// localise the WordPress way
		wp_localize_script(
			'bgcs-utilities-js',
			'BP_Groups_CiviCRM_Sync_Utils',
			$vars
		);

	}



	/**
	 * Adds help copy to admin page.
	 *
	 * @since 0.1
	 *
	 * @param object $screen The existing WordPress screen object.
	 * @return object $screen The amended WordPress screen object.
	 */
	public function admin_help( $screen ) {

		// init suffix
		$page = '';

		// the page ID is different in multisite
		if ( is_multisite() ) {
			$page = '-network';
		}

		// init page IDs
		$pages = array(
			$this->parent_page . $page,
			$this->settings_page . $page,
			$this->sync_page . $page,
		);

		// kick out if not our screen
		if ( ! in_array( $screen->id, $pages ) ) { return $screen; }

		// add a tab - we can add more later
		$screen->add_help_tab( array(
			'id'      => 'bp_groups_civicrm_sync',
			'title'   => __( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ),
			'content' => $this->admin_help_text(),
		));

		// --<
		return $screen;

	}



	/**
	 * Get help text.
	 *
	 * @since 0.1
	 *
	 * @return string $help Help formatted as HTML.
	 */
	public function admin_help_text() {

		// stub help text, to be developed further
		$help = '<p>' . __( 'For further information about using BP Groups CiviCRM Sync, please refer to the readme.txt that comes with this plugin.', 'bp-groups-civicrm-sync' ) . '</p>';

		// --<
		return $help;

	}



	/**
	 * Show settings page.
	 *
	 * @since 0.1
	 */
	public function page_settings() {

		// check user permissions
		if ( current_user_can( 'manage_options' ) ) {

			// get admin page URLs
			$urls = $this->page_get_urls();

			// get our settings
			$parent_group = absint( $this->setting_get( 'parent_group' ) );

			// checked by default
			$checked = ' checked="checked"';
			if ( isset( $parent_group ) AND $parent_group === 0 ) {
				$checked = '';
			}

			// assume no BP Group Hierarchy plugin
			$bp_group_hierarchy = false;

			// test for presence BP Group Hierarchy plugin
			if ( defined( 'BP_GROUP_HIERARCHY_IS_INSTALLED' ) ) {

				// set flag
				$bp_group_hierarchy = true;

				// get our settings
				$hierarchy = absint( $this->setting_get( 'nesting' ) );

				// checked by default
				$hierarchy_checked = ' checked="checked"';
				if ( isset( $hierarchy ) AND $hierarchy === 0 ) {
					$hierarchy_checked = '';
				}

			}

			// include template file
			include( BP_GROUPS_CIVICRM_SYNC_PATH . 'assets/templates/settings.php' );

		}

	}



	/**
	 * Show utilities page.
	 *
	 * @since 0.1
	 */
	public function page_utilities() {

		// check user permissions
		if ( current_user_can( 'manage_options' ) ) {

			// get admin page URLs
			$urls = $this->page_get_urls();

			// init messages
			$messages = '';

			// init BP flags
			$checking_bp = false;

			// did we ask to check for group sync integrity?
			if ( isset( $_POST['bp_groups_civicrm_sync_bp_check'] ) AND ! empty( $_POST['bp_groups_civicrm_sync_bp_check'] ) ) {

				// set flag
				$checking_bp = true;

			}

			// init OG flags
			$checking_og = false;
			$has_og_groups = false;

			// did we ask to check for OG groups?
			if ( isset( $_POST['bp_groups_civicrm_sync_og_check'] ) AND ! empty( $_POST['bp_groups_civicrm_sync_og_check'] ) ) {

				// set flag
				$checking_og = true;

				// do we have any OG groups?
				$has_og_groups = $this->civi->has_og_groups();

			}

			// if we've updated
			if ( isset( $_GET['updated'] ) ) {

				// are we checking OG?
				if ( $checking_og ) {

					// yes, did we get any?
					if ( $has_og_groups !== false ) {

						// show settings updated message
						$messages .= '<div id="message" class="updated"><p>' . __( 'OG Groups found. You can now choose to migrate them to BP.', 'bp-groups-civicrm-sync' ) . '</p></div>';

					} else {

						// show settings updated message
						$messages .= '<div id="message" class="updated"><p>' . __( 'No OG Groups found.', 'bp-groups-civicrm-sync' ) . '</p></div>';

					}

				} else {

					// are we checking BP?
					if ( $checking_bp ) {

					} else {

						// show settings updated message
						//$messages .= '<div id="message" class="updated"><p>' . __( 'Options saved.', 'bp-groups-civicrm-sync' ) . '</p></div>';

					}

				}

			}

			// OG to BP flags
			$og_to_bp_do_sync = false;

			// do we have any OG groups?
			if ( $checking_og AND $has_og_groups ) {

				// show OG to BP
				$og_to_bp_do_sync = true;

			}

			// include template file
			include( BP_GROUPS_CIVICRM_SYNC_PATH . 'assets/templates/utilities.php' );

		}

	}



	//##########################################################################



	/**
	 * Get admin page URLs.
	 *
	 * @since 0.1
	 *
	 * @return array $admin_urls The array of admin page URLs.
	 */
	public function page_get_urls() {

		// only calculate once
		if ( isset( $this->urls ) ) { return $this->urls; }

		// init return
		$this->urls = array();

		// multisite?
		if ( is_multisite() ) {

			// get admin page URLs via our adapted method
			$this->urls['settings'] = $this->network_menu_page_url( 'bp_groups_civicrm_sync_settings', false );
			$this->urls['utilities'] = $this->network_menu_page_url( 'bp_groups_civicrm_sync_utilities', false );

		} else {

			// get admin page URLs
			$this->urls['settings'] = menu_page_url( 'bp_groups_civicrm_sync_settings', false );
			$this->urls['utilities'] = menu_page_url( 'bp_groups_civicrm_sync_utilities', false );

		}

		// --<
		return $this->urls;

	}



	/**
	 * Get the url to access a particular menu page based on the slug it was registered with.
	 * If the slug hasn't been registered properly no url will be returned.
	 *
	 * @since 0.1
	 *
	 * @param string $menu_slug The slug name to refer to this menu by - should be unique for this menu.
	 * @param bool $echo Whether or not to echo the url - default is true.
	 * @return string $url The URL.
	 */
	public function network_menu_page_url( $menu_slug, $echo = true ) {
		global $_parent_pages;

		if ( isset( $_parent_pages[$menu_slug] ) ) {
			$parent_slug = $_parent_pages[$menu_slug];
			if ( $parent_slug && ! isset( $_parent_pages[$parent_slug] ) ) {
				$url = network_admin_url( add_query_arg( 'page', $menu_slug, $parent_slug ) );
			} else {
				$url = network_admin_url( 'admin.php?page=' . $menu_slug );
			}
		} else {
			$url = '';
		}

		$url = esc_url( $url );

		if ( $echo ) echo $url;

		// --<
		return $url;

	}



	/**
	 * Get the URL for the form action.
	 *
	 * @since 0.1
	 *
	 * @return string $target_url The URL for the admin form action.
	 */
	public function admin_form_url_get() {

		// sanitise admin page url
		$target_url = $_SERVER['REQUEST_URI'];
		$url_array = explode( '&', $target_url );
		if ( $url_array ) { $target_url = htmlentities( $url_array[0] . '&updated=true' ); }

		// --<
		return $target_url;

	}



	//##########################################################################



	/**
	 * Get default plugin settings.
	 *
	 * @since 0.1
	 *
	 * @return array $settings The array of settings, keyed by setting name.
	 */
	public function settings_get_default() {

		// init return
		$settings = array();

		// set default parent group setting
		$settings['parent_group'] = 0;

		// set default nesting setting for when BP Group Hierarchy is installed
		$settings['nesting'] = 1;

		// allow filtering
		return apply_filters( 'bp_groups_civicrm_sync_default_settings', $settings );

	}



	/**
	 * Route settings updates to relevant methods.
	 *
	 * @since 0.1
	 */
	public function settings_update_router() {

	 	// was the settings form submitted?
		if( isset( $_POST['bp_groups_civicrm_sync_settings_submit'] ) ) {
			$this->settings_update_options();
		}

	 	// was the "Stop Sync" button pressed?
		if( isset( $_POST['bp_groups_civicrm_sync_bp_stop'] ) ) {
			delete_option( '_bgcs_members_page' );
			delete_option( '_bgcs_groups_page' );
			return;
		}

		// were any sync operations requested?
		if(
			isset( $_POST[ 'bp_groups_civicrm_sync_bp_check' ] ) OR
			isset( $_POST[ 'bp_groups_civicrm_sync_bp_check_sync' ] ) OR
			isset( $_POST[ 'bp_groups_civicrm_sync_convert' ] )
		) {
			$this->settings_update_sync();
		}

	}



	/**
	 * Update options supplied by our admin page.
	 *
	 * @since 0.1
	 */
	public function settings_update_options() {

		// check that we trust the source of the data
		check_admin_referer( 'bp_groups_civicrm_sync_settings_action', 'bp_groups_civicrm_sync_nonce' );

		// get existing option
		$existing_parent_group = $this->setting_get( 'parent_group' );

		// default to empty value
		$settings_parent_group = 0;

		// did we ask to enable parent group?
		if ( isset( $_POST['bp_groups_civicrm_sync_settings_parent_group'] ) ) {

			// yes, set flag
			$settings_parent_group = absint( $_POST['bp_groups_civicrm_sync_settings_parent_group'] );

		}

		// sanitise and set option
		$this->setting_set( 'parent_group', ( $settings_parent_group ? 1 : 0 ) );

		// test for presence BP Group Hierarchy plugin
		if ( defined( 'BP_GROUP_HIERARCHY_IS_INSTALLED' ) ) {

			// get existing option
			$existing_hierarchy = $this->setting_get( 'nesting' );

			// default to empty value
			$settings_hierarchy = 0;

			// did we ask to enable parent group?
			if ( isset( $_POST['bp_groups_civicrm_sync_settings_hierarchy'] ) ) {

				// yes, set flag
				$settings_hierarchy = absint( $_POST['bp_groups_civicrm_sync_settings_hierarchy'] );

			}

			// sanitise and set option
			$this->setting_set( 'nesting', ( $settings_hierarchy ? 1 : 0 ) );

		}

		// save settings
		$this->settings_save();

		// test for presence BP Group Hierarchy plugin
		if ( defined( 'BP_GROUP_HIERARCHY_IS_INSTALLED' ) ) {

			// is the hierarchy setting changing?
			if ( $existing_hierarchy != $settings_hierarchy ) {

				// are we switching from "no hierarchy" to "use hierarchy"?
				if ( $existing_hierarchy == 0 ) {

					// build CiviCRM group hierarchy
					$this->civi->group_hierarchy_build();

				} else {

					// collapse CiviCRM group hierarchy
					$this->civi->group_hierarchy_collapse();

				}

			}

		}

		// is the parent group setting changing?
		if ( $existing_parent_group != $settings_parent_group ) {

			// are we switching from "no parent group"?
			if ( $existing_parent_group == 0 ) {

				// create a meta group to hold all BuddyPress groups
				$this->civi->meta_group_create();

				// assign BP Sync Groups with no parent to the meta group
				$this->civi->meta_group_groups_assign();

			} else {

				// remove top-level BP Sync Groups from the "BuddyPress Groups" container group
				$this->civi->meta_group_groups_remove();

				// delete "BuddyPress Groups" meta group
				$this->civi->meta_group_delete();

			}

		}

		// get admin URLs
		$urls = $this->page_get_urls();

		// redirect to settings page with message
		wp_redirect( $urls['settings'] . '&updated=true' );
		die();

	}



	/**
	 * Do sync procedure, depending on which one has been selected.
	 *
	 * @since 0.1
	 *
	 * @return boolean True if sync performed, false otherwise.
	 */
	public function settings_update_sync() {

		// check that we trust the source of the data
		check_admin_referer( 'bp_groups_civicrm_sync_utilities_action', 'bp_groups_civicrm_sync_nonce' );

		// init vars
		$bp_groups_civicrm_sync_convert = '';
		$bp_groups_civicrm_sync_bp_check = '';
		$bp_groups_civicrm_sync_bp_check_sync = '';

		// get variables
		extract( $_POST );

		// did we ask to sync existing BP groups with CiviCRM?
		if ( ! empty( $bp_groups_civicrm_sync_bp_check ) ) {

			// try and sync BP groups with CiviCRM groups
			$this->sync_bp_and_civi();

		}

		// did we ask to check the sync of BP groups and CiviCRM groups?
		if ( ! empty( $bp_groups_civicrm_sync_bp_check_sync ) ) {

			// check the sync between BP groups and CiviCRM groups
			$this->check_sync_between_bp_and_civi();

		}

		// did we ask to convert OG groups?
		if ( ! empty( $bp_groups_civicrm_sync_convert ) ) {

			// try and convert OG groups to BP groups
			$this->civi->og_groups_to_bp_groups_convert();

		}

		// get admin URLs
		$urls = $this->page_get_urls();

		// redirect to utilities page with message
		wp_redirect( $urls['utilities'] . '&updated=true' );
		die();

	}



	/**
	 * Save the plugin's settings array.
	 *
	 * @since 0.1
	 *
	 * @return bool $result True if setting value has changed, false if not or if update failed.
	 */
	public function settings_save() {

		// update WordPress option and return result
		return update_option( 'bp_groups_civicrm_sync_settings', $this->settings );

	}



	/**
	 * Return a value for a specified setting.
	 *
	 * @since 0.1
	 *
	 * @return mixed $setting The value of the setting.
	 */
	public function setting_get( $setting_name = '', $default = false ) {

		// sanity check
		if ( $setting_name == '' ) {
			wp_die( __( 'You must supply a setting to setting_get()', 'bp-groups-civicrm-sync' ) );
		}

		// get setting
		return ( array_key_exists( $setting_name, $this->settings ) ) ? $this->settings[$setting_name] : $default;

	}



	/**
	 * Set a value for a specified setting.
	 *
	 * @since 0.1
	 */
	public function setting_set( $setting_name = '', $value = '' ) {

		// sanity check
		if ( $setting_name == '' ) {
			wp_die( __( 'You must supply a setting to setting_set()', 'bp-groups-civicrm-sync' ) );
		}

		// set setting
		$this->settings[$setting_name] = $value;

	}



	//##########################################################################



	/**
	 * Sync BuddyPress groups to CiviCRM.
	 *
	 * This method steps through all groups and all group members and syncs them
	 * to CiviCRM. It has been overhauled since 0.1 to sync in "chunks" instead
	 * of all at once. In the unlikely event that Javascript is disabled, there
	 * will be two buttons displayed on the admin page - one to continue the
	 * sync, one to stop the sync.
	 *
	 * @since 0.1
	 */
	public function sync_bp_and_civi() {

		// init or die
		if ( ! $this->civi->is_active() ) return;

		// init AJAX return
		$data = array();

		// if the groups paging value doesn't exist
		if ( 'fgffgs' == get_option( '_bgcs_groups_page', 'fgffgs' ) ) {

			// start at the beginning
			$groups_page = 1;
			add_option( '_bgcs_groups_page', '1' );

		} else {

			// use the existing value
			$groups_page = intval( get_option( '_bgcs_groups_page', '1' ) );

		}

		$group_params = array(
			'type' => 'alphabetical',
			'page' => $groups_page,
			'per_page' => 1,
			'populate_extras' => true,
			'show_hidden' => true,
		);

		// query with our params
		if ( bp_has_groups( $group_params ) ) {

			// set finished flag
			$data['finished'] = 'false';

			// do the loop
			while ( bp_groups() ) {

				// set up group
				bp_the_group();

				// get the group object
				global $groups_template;
				$group =& $groups_template->group;

				// get group ID
				$group_id = bp_get_group_id();

				// get name of group
				$data['group_name'] = bp_get_group_name();

				// get the CiviCRM group ID of this BuddyPress group
				$civi_group_id = $this->civi->find_group_id(
					$this->civi->member_group_get_sync_name( $group_id )
				);

				// if we don't get an ID, create the group
				if ( ! $civi_group_id ) {
					$this->bp->create_civi_group( $group_id, null, $group );
				} else {
					$this->bp->update_civi_group( $group_id, $group );
				}

				// get paging value, or start at the beginning if not present
				$members_page = intval( get_option( '_bgcs_members_page', '1' ) );

				$member_params = array(
					'exclude_admins_mods' => 0,
					'page' => $members_page,
					'per_page' => 10,
					'group_id' => $group_id,
				);

				// query with our params
				if ( bp_group_has_members( $member_params ) ) {

					// set members flag
					$data['members'] = (string) $members_page;

					// do the loop
					while ( bp_group_members() ) {

						// set up member
						bp_group_the_member();

						// update their membership
						$this->bp->civi_update_group_membership( bp_get_group_member_id(), $group_id );

					} // end while

					// increment members paging option
					update_option( '_bgcs_members_page', (string) ( $members_page + 1 ) );

				} else {

					// set members flag
					$data['members'] = 'done';

					// delete the members option to start from the beginning
					delete_option( '_bgcs_members_page' );

					// increment groups paging option
					update_option( '_bgcs_groups_page', (string) ( $groups_page + 1 ) );

				}

			} // end while

		} else {

			// delete the groups option to start from the beginning
			delete_option( '_bgcs_groups_page' );

			// set finished flag
			$data['finished'] = 'true';

		}

		// is this an AJAX request?
		if ( defined( 'DOING_AJAX' ) AND DOING_AJAX ) {

			// set reasonable headers
			header('Content-type: text/plain');
			header("Cache-Control: no-cache");
			header("Expires: -1");

			// echo
			echo json_encode( $data );

			// die
			exit();

		}

	}



	/**
	 * Check the Sync between BuddyPress groups and CiviCRM groups.
	 *
	 * @since 0.1
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

		// if we got some
		if (
			$all_groups['is_error'] == 0 AND
			isset( $all_groups['values'] ) AND
			count( $all_groups['values'] ) > 0
		) {

			// loop through them
			foreach( $all_groups['values'] AS $civi_group ) {

				// is this group supposed to have a BP Group?
				$has_group = $this->civi->has_bp_group( $civi_group );

				// if it does
				if ( $has_group ) {

					// get the ID of the BP group it was supposed to sync with
					$group_id = $this->civi->get_bp_group_id_by_civi_group( $civi_group );

					// does this group exist?

				}

			}

		}

	}



} // class ends
