<?php
/**
 * Admin Class.
 *
 * Handles admin functionality.
 *
 * @package BP_Groups_CiviCRM_Sync
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

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
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * CiviCRM object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $civicrm The CiviCRM object.
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
	public $settings = [];

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference to parent.
		$this->plugin = $parent;

		// Add action for admin init.
		add_action( 'bpgcs/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Perform activation tasks.
	 *
	 * @since 0.1
	 */
	public function activate() {

		// Store version for later reference.
		$this->store_version();

		// Add default settings option only if it does not exist.
		if ( 'fgffgs' === get_option( 'bp_groups_civicrm_sync_settings', 'fgffgs' ) ) {
			add_option( 'bp_groups_civicrm_sync_settings', $this->settings_get_default() );
		}

	}

	/**
	 * Perform deactivation tasks.
	 *
	 * @since 0.1
	 */
	public function deactivate() {

		// We delete our options in uninstall.php.

	}

	/**
	 * Initialises this class.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// Store references.
		$this->bp = $this->plugin->bp;
		$this->civicrm = $this->plugin->civicrm;

		// Load plugin version.
		$this->plugin_version = get_option( 'bp_groups_civicrm_sync_version', false );

		// Upgrade version if needed.
		if ( BP_GROUPS_CIVICRM_SYNC_VERSION !== $this->plugin_version ) {
			$this->store_version();
		}

		// Load settings array.
		$this->settings = get_option( 'bp_groups_civicrm_sync_settings', $this->settings );

		// Is this the back end?
		if ( is_admin() ) {

			// Add AJAX handler.
			add_action( 'wp_ajax_sync_bp_and_civicrm', [ $this, 'bp_groups_sync_to_civicrm' ] );

			// Add menu to Network or Settings submenu.
			if ( $this->plugin->is_network_activated() ) {
				add_action( 'network_admin_menu', [ $this, 'admin_menu' ], 40 );
			} else {
				add_action( 'admin_menu', [ $this, 'admin_menu' ], 40 );
			}

		}

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 0.4
		 */
		do_action( 'bpgcs/admin/loaded' );

	}

	/**
	 * Store the plugin version.
	 *
	 * @since 0.1
	 */
	public function store_version() {

		// Store version.
		update_option( 'bp_groups_civicrm_sync_version', BP_GROUPS_CIVICRM_SYNC_VERSION );

	}

	// -------------------------------------------------------------------------

	/**
	 * Add this plugin's Settings Page to the WordPress admin menu.
	 *
	 * @since 0.1
	 */
	public function admin_menu() {

		// We must be network admin in multisite.
		if ( is_multisite() && ! is_super_admin() ) {
			return false;
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Check BuddyPress config.
		if ( ! $this->bp->is_configured() ) {
			return false;
		}

		// Multisite?
		if ( $this->plugin->is_network_activated() ) {

			// Add the admin page to the Network Settings menu.
			$this->parent_page = add_submenu_page(
				'settings.php',
				__( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ), // Page title.
				__( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ), // Menu title.
				'manage_options', // Required caps.
				'bp_groups_civicrm_sync_parent', // Slug name.
				[ $this, 'page_settings' ] // Callback.
			);

		} else {

			// Add the settings page to the CiviCRM menu.
			$this->parent_page = add_submenu_page(
				'CiviCRM', // Parent slug.
				__( 'BP Groups Sync', 'bp-groups-civicrm-sync' ), // Page title.
				__( 'BP Groups Sync', 'bp-groups-civicrm-sync' ), // Menu title.
				'manage_options', // Required caps.
				'bp_groups_civicrm_sync_parent', // Slug name.
				[ $this, 'page_settings' ] // Callback.
			);

		}

		// Register our form submit hander.
		add_action( 'load-' . $this->parent_page, [ $this, 'settings_update_router' ] );

		// Implement menu highlighting.
		add_action( 'admin_head-' . $this->parent_page, [ $this, 'admin_head' ], 50 );
		add_action( 'admin_head-' . $this->parent_page, [ $this, 'admin_menu_highlight' ], 50 );

		// Add settings page.
		$this->settings_page = add_submenu_page(
			'bp_groups_civicrm_sync_parent', // Parent slug.
			__( 'Settings: BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ), // Page title.
			__( 'Settings', 'bp-groups-civicrm-sync' ), // Menu title.
			'manage_options', // Required caps.
			'bp_groups_civicrm_sync_settings', // Slug name.
			[ $this, 'page_settings' ] // Callback.
		);

		// Register our form submit hander.
		add_action( 'load-' . $this->settings_page, [ $this, 'settings_update_router' ] );

		// Implement menu highlighting.
		add_action( 'admin_head-' . $this->settings_page, [ $this, 'admin_head' ], 50 );
		add_action( 'admin_head-' . $this->settings_page, [ $this, 'admin_menu_highlight' ], 50 );

		// Add Manual Sync page.
		$this->sync_page = add_submenu_page(
			'bp_groups_civicrm_sync_parent', // Parent slug.
			__( 'Manual Sync: BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ), // Page title.
			__( 'Manual Sync', 'bp-groups-civicrm-sync' ), // Menu title.
			'manage_options', // Required caps.
			'bp_groups_civicrm_sync_manual_sync', // Slug name.
			[ $this, 'page_manual_sync' ] // Callback.
		);

		// Register our form submit hander.
		add_action( 'load-' . $this->sync_page, [ $this, 'settings_update_router' ] );

		// Implement menu highlighting.
		add_action( 'admin_head-' . $this->sync_page, [ $this, 'admin_head' ], 50 );
		add_action( 'admin_head-' . $this->sync_page, [ $this, 'admin_menu_highlight' ], 50 );

		// Add styles and scripts only on our Manual Sync page.
		add_action( 'admin_print_styles-' . $this->sync_page, [ $this, 'page_manual_sync_styles' ] );
		add_action( 'admin_print_scripts-' . $this->sync_page, [ $this, 'page_manual_sync_scripts' ] );

	}

	/**
	 * Initialise plugin help.
	 *
	 * @since 0.1
	 */
	public function admin_head() {

		// There's a new screen object for help in 3.3.
		$screen = get_current_screen();

		// Use method in this class.
		$this->admin_help( $screen );

	}

	/**
	 * This tells WordPress to highlight the plugin's menu item, regardless of
	 * which actual admin screen we are on.
	 *
	 * @since 0.1
	 *
	 * @global string $plugin_page The slug of the current page.
	 * @global string $submenu_file The slug of the submenu file.
	 */
	public function admin_menu_highlight() {

		// We have to override these to highlight correctly.
		global $plugin_page, $submenu_file;

		// Define subpages.
		$subpages = [
			'bp_groups_civicrm_sync_settings',
			'bp_groups_civicrm_sync_manual_sync',
		];

		// This tweaks the Settings subnav menu to show only one menu item.
		if ( in_array( $plugin_page, $subpages ) ) {
			// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
			$plugin_page = 'bp_groups_civicrm_sync_parent';
			$submenu_file = 'bp_groups_civicrm_sync_parent';
			// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
		}

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

		// Init suffix.
		$page = '';

		// The page ID is different in multisite.
		if ( $this->plugin->is_network_activated() ) {
			$page = '-network';
		}

		// Init page IDs.
		$pages = [
			$this->parent_page . $page,
			$this->settings_page . $page,
			$this->sync_page . $page,
		];

		// Kick out if not our screen.
		if ( ! in_array( $screen->id, $pages ) ) {
			return $screen;
		}

		// Add a tab - we can add more later.
		$screen->add_help_tab( [
			'id' => 'bpgcs-help',
			'title' => __( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' ),
			'content' => $this->admin_help_text(),
		] );

		// --<
		return $screen;

	}

	/**
	 * Get help text.
	 *
	 * @since 0.1
	 *
	 * @return string $help The help text formatted as HTML.
	 */
	public function admin_help_text() {

		// Stub help text, to be developed further.
		$help = '<p>' . __( 'For further information about using BP Groups CiviCRM Sync, please refer to the readme.txt that comes with this plugin.', 'bp-groups-civicrm-sync' ) . '</p>';

		// --<
		return $help;

	}

	// -------------------------------------------------------------------------

	/**
	 * Show settings page.
	 *
	 * @since 0.1
	 */
	public function page_settings() {

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get admin page URLs.
		$urls = $this->page_get_urls();

		// Get our settings.
		$parent_group = (int) $this->setting_get( 'parent_group' );

		// Checked by default.
		$checked = ' checked="checked"';
		if ( isset( $parent_group ) && 0 === $parent_group ) {
			$checked = '';
		}

		// Include template file.
		include BP_GROUPS_CIVICRM_SYNC_PATH . 'assets/templates/settings.php';

	}

	/**
	 * Show Manual Sync page.
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 */
	public function page_manual_sync() {

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get admin page URLs.
		$urls = $this->page_get_urls();

		// Init messages.
		$messages = '';

		// Show "Organic Group to BuddyPress Sync" if we have Organic Groups.
		$has_og_groups = $this->civicrm->group_admin->has_og_groups();

		// Include template file.
		include BP_GROUPS_CIVICRM_SYNC_PATH . 'assets/templates/manual-sync.php';

	}

	/**
	 * Enqueues the styles needed by our Manual Sync page.
	 *
	 * @since 0.2.2
	 * @since 0.4 Renamed.
	 */
	public function page_manual_sync_styles() {

		// Enqueue CSS.
		wp_enqueue_style(
			'bgcs-manual-sync-style',
			BP_GROUPS_CIVICRM_SYNC_URL . 'assets/css/bgcs-admin-manual-sync.css',
			null, // Dependencies.
			BP_GROUPS_CIVICRM_SYNC_VERSION,
			'all' // Media.
		);

	}

	/**
	 * Enqueues the scripts needed by our Manual Sync page.
	 *
	 * @since 0.2.2
	 * @since 0.4 Renamed.
	 */
	public function page_manual_sync_scripts() {

		// Enqueue script.
		wp_enqueue_script(
			'bgcs-manual-sync-js',
			BP_GROUPS_CIVICRM_SYNC_URL . 'assets/js/bgcs-admin-manual-sync.js',
			[ 'jquery', 'jquery-ui-core', 'jquery-ui-progressbar' ],
			BP_GROUPS_CIVICRM_SYNC_VERSION,
			true
		);

		// Init localisation.
		$localisation = [
			'total' => __( '{{total}} groups to sync...', 'bp-groups-civicrm-sync' ),
			'current' => __( 'Processing group "{{name}}"', 'bp-groups-civicrm-sync' ),
			'complete' => __( 'Processing group "{{name}}" complete', 'bp-groups-civicrm-sync' ),
			'done' => __( 'All done!', 'bp-groups-civicrm-sync' ),
		];

		// Init settings.
		$settings = [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'total_groups' => groups_get_total_group_count(),
		];

		// Localisation array.
		$vars = [
			'localisation' => $localisation,
			'settings' => $settings,
		];

		// Localise the WordPress way.
		wp_localize_script(
			'bgcs-manual-sync-js',
			'BPGCS_Settings',
			$vars
		);

	}

	// -------------------------------------------------------------------------

	/**
	 * Get admin page URLs.
	 *
	 * @since 0.1
	 *
	 * @return array $admin_urls The array of admin page URLs.
	 */
	public function page_get_urls() {

		// Only calculate once.
		if ( isset( $this->urls ) ) {
			return $this->urls;
		}

		// Init return.
		$this->urls = [];

		// Multisite?
		if ( $this->plugin->is_network_activated() ) {

			// Get admin page URLs via our adapted method.
			$this->urls['settings'] = $this->network_menu_page_url( 'bp_groups_civicrm_sync_settings', false );
			$this->urls['manual_sync'] = $this->network_menu_page_url( 'bp_groups_civicrm_sync_manual_sync', false );

		} else {

			// Get admin page URLs.
			$this->urls['settings'] = menu_page_url( 'bp_groups_civicrm_sync_settings', false );
			$this->urls['manual_sync'] = menu_page_url( 'bp_groups_civicrm_sync_manual_sync', false );

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

		if ( isset( $_parent_pages[ $menu_slug ] ) ) {
			$parent_slug = $_parent_pages[ $menu_slug ];
			if ( $parent_slug && ! isset( $_parent_pages[ $parent_slug ] ) ) {
				$url = network_admin_url( add_query_arg( 'page', $menu_slug, $parent_slug ) );
			} else {
				$url = network_admin_url( 'admin.php?page=' . $menu_slug );
			}
		} else {
			$url = '';
		}

		$url = esc_url( $url );

		if ( $echo ) {
			echo $url;
		}

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

		// Sanitise admin page URL.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$target_url = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( ! empty( $target_url ) ) {
			$url_array = explode( '&', $target_url );
			if ( $url_array ) {
				$target_url = htmlentities( $url_array[0] . '&updated=true' );
			}
		}

		// --<
		return $target_url;

	}

	// -------------------------------------------------------------------------

	/**
	 * Get default plugin settings.
	 *
	 * @since 0.1
	 *
	 * @return array $settings The array of settings, keyed by setting name.
	 */
	public function settings_get_default() {

		// Init return.
		$settings = [];

		// Set default Parent Group setting.
		$settings['parent_group'] = 0;

		// Set default nesting setting for when BuddyPress Group Hierarchy is installed.
		$settings['nesting'] = 1;

		/**
		 * Allow settings to be filtered.
		 *
		 * @since 0.1
		 *
		 * @param array The existing settings array.
		 * @return array The modified settings array.
		 */
		return apply_filters( 'bp_groups_civicrm_sync_default_settings', $settings );

	}

	/**
	 * Route settings updates to relevant methods.
	 *
	 * @since 0.1
	 */
	public function settings_update_router() {

		// phpcs:disable WordPress.Security.NonceVerification.Missing

		// Was the settings form submitted?
		if ( isset( $_POST['bp_groups_civicrm_sync_settings_submit'] ) ) {
			$this->settings_update_options();
		}

		// Was the "Stop Sync" button pressed?
		if ( isset( $_POST['bp_groups_civicrm_sync_bp_stop'] ) ) {
			$this->settings_stop_sync();
		}

		// Were any sync operations requested?
		if (
			isset( $_POST['bp_groups_civicrm_sync_bp_check'] ) ||
			isset( $_POST['bp_groups_civicrm_sync_convert'] )
		) {
			$this->settings_update_sync();
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

	}

	/**
	 * Update options supplied by our admin page.
	 *
	 * @since 0.1
	 */
	public function settings_update_options() {

		// Check that we trust the source of the data.
		check_admin_referer( 'bp_groups_civicrm_sync_settings_action', 'bp_groups_civicrm_sync_nonce' );

		// Get existing option.
		$existing_parent_group = (int) $this->setting_get( 'parent_group' );

		// Did we ask to enable Parent Group?
		$settings_parent_group = 0;
		if ( isset( $_POST['bp_groups_civicrm_sync_settings_parent_group'] ) ) {
			$settings_parent_group = (int) $_POST['bp_groups_civicrm_sync_settings_parent_group'];
		}

		// Sanitise and set option.
		$this->setting_set( 'parent_group', ( $settings_parent_group ? 1 : 0 ) );

		/**
		 * Broadcast that we are about to update our settings.
		 *
		 * @since 0.4
		 */
		do_action( 'bpgcs/admin/settings/update/before' );

		// Save settings.
		$this->settings_save();

		/**
		 * Broadcast that we have updated our settings.
		 *
		 * @since 0.4
		 */
		do_action( 'bpgcs/admin/settings/update/after' );

		// Is the Parent Group setting changing?
		if ( $existing_parent_group !== $settings_parent_group ) {

			// Are we switching from "No Parent Group"?
			if ( 0 === $existing_parent_group ) {

				// Create a Meta Group to hold all BuddyPress Groups.
				$this->civicrm->meta_group->group_create();

				// Assign all Synced CiviCRM Groups with no parent to the Meta Group.
				$this->civicrm->meta_group->groups_assign();

			} else {

				// Remove top-level Synced CiviCRM Groups from the Meta Group.
				$this->civicrm->meta_group->groups_remove();

				// Delete the Meta Group.
				$this->civicrm->meta_group->group_delete();

			}

		}

		// Get admin URLs.
		$urls = $this->page_get_urls();

		// Redirect to settings page with message.
		wp_safe_redirect( add_query_arg( [ 'updated' => 'true' ], $urls['settings'] ) );
		exit;

	}

	/**
	 * Do sync procedure, depending on which one has been selected.
	 *
	 * @since 0.1
	 */
	public function settings_update_sync() {

		// Check that we trust the source of the data.
		check_admin_referer( 'bp_groups_civicrm_sync_manual_sync_action', 'bp_groups_civicrm_sync_nonce' );

		// Init vars.
		$bp_groups_civicrm_sync_convert = '';
		$bp_groups_civicrm_sync_bp_check = '';

		// Get variables.
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $_POST );

		// Did we ask to sync existing BuddyPress Groups with CiviCRM?
		if ( ! empty( $bp_groups_civicrm_sync_bp_check ) ) {
			$this->bp_groups_sync_to_civicrm();
		}

		// Did we ask to convert Organic Groups?
		if ( ! empty( $bp_groups_civicrm_sync_convert ) ) {
			$this->civicrm->group_admin->og_groups_to_bp_groups_convert();
		}

		// Get admin URLs.
		$urls = $this->page_get_urls();

		// Redirect to Manual Sync page with message.
		wp_safe_redirect( add_query_arg( [ 'updated' => 'true' ], $urls['manual_sync'] ) );
		exit;

	}

	/**
	 * Stop the sync procedure.
	 *
	 * @since 0.4.1
	 */
	public function settings_stop_sync() {

		// Check that we trust the source of the data.
		check_admin_referer( 'bp_groups_civicrm_sync_manual_sync_action', 'bp_groups_civicrm_sync_nonce' );

		// Delete the sync options.
		delete_option( '_bgcs_members_page' );
		delete_option( '_bgcs_groups_page' );

		// Get admin URLs.
		$urls = $this->page_get_urls();

		// Redirect to Manual Sync page with message.
		wp_safe_redirect( add_query_arg( [ 'updated' => 'true' ], $urls['manual_sync'] ) );
		exit;

	}

	/**
	 * Save the plugin's settings array.
	 *
	 * @since 0.1
	 *
	 * @return bool $result True if setting value has changed, false if not or if update failed.
	 */
	public function settings_save() {

		// Update WordPress option and return result.
		return update_option( 'bp_groups_civicrm_sync_settings', $this->settings );

	}

	/**
	 * Return a value for a specified setting.
	 *
	 * @since 0.1
	 *
	 * @param str $setting_name The name of the setting.
	 * @param mixed $default The default value of the setting.
	 * @return mixed $setting The value of the setting.
	 */
	public function setting_get( $setting_name, $default = false ) {

		// Get setting.
		return array_key_exists( $setting_name, $this->settings ) ? $this->settings[ $setting_name ] : $default;

	}

	/**
	 * Set a value for a specified setting.
	 *
	 * @since 0.1
	 *
	 * @param str $setting_name The name of the setting.
	 * @param mixed $value The value for the setting.
	 */
	public function setting_set( $setting_name, $value = '' ) {

		// Set setting.
		$this->settings[ $setting_name ] = $value;

	}

	// -------------------------------------------------------------------------

	/**
	 * Sync BuddyPress Groups to CiviCRM.
	 *
	 * This method steps through all Groups and all Group Members and syncs them
	 * to CiviCRM. It has been overhauled since 0.1 to sync in "chunks" instead
	 * of all at once. In the unlikely event that Javascript is disabled, there
	 * will be two buttons displayed on the admin page - one to continue the
	 * sync, one to stop the sync.
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 */
	public function bp_groups_sync_to_civicrm() {

		// Init AJAX return.
		$data = [
			'success' => 'false',
		];

		// Init CiviCRM or bail.
		if ( ! $this->civicrm->is_initialised() ) {
			if ( wp_doing_ajax() ) {
				wp_send_json( $data );
			} else {
				return;
			}
		}

		// If this is an AJAX request, check security.
		if ( wp_doing_ajax() ) {
			$result = check_ajax_referer( 'bp_groups_civicrm_sync_bp_nonce', false, false );
			if ( false === $result ) {
				wp_send_json( $data );
			}
		}

		// If the Groups paging value doesn't exist.
		if ( 'fgffgs' === get_option( '_bgcs_groups_page', 'fgffgs' ) ) {

			// Start at the beginning.
			$groups_page = 1;
			add_option( '_bgcs_groups_page', '1' );

		} else {

			// Use the existing value.
			$groups_page = intval( get_option( '_bgcs_groups_page', '1' ) );

		}

		$group_params = [
			'type' => 'alphabetical',
			'page' => $groups_page,
			'per_page' => 1,
			'populate_extras' => true,
			'show_hidden' => true,
		];

		// Query with our params.
		if ( bp_has_groups( $group_params ) ) {

			// Set finished flag.
			$data['finished'] = 'false';

			// Do the loop.
			while ( bp_groups() ) {

				// Set up Group.
				bp_the_group();

				// Get the Group object.
				global $groups_template;
				$group =& $groups_template->group;

				// Get Group ID.
				$group_id = bp_get_group_id();

				// Skip to next if sync should not happen for this Group.
				if ( ! $this->bp->group_should_be_synced( $group_id ) ) {
					continue;
				}

				// Get name of Group.
				$data['group_name'] = bp_get_group_name();

				// Get the ID of the CiviCRM Member Group for this BuddyPress Group.
				$member_group_id = $this->civicrm->group_id_find(
					$this->civicrm->member_group_get_sync_name( $group_id )
				);

				// If we don't get an ID, create the CiviCRM Group(s).
				if ( false === $member_group_id ) {
					$this->bp->civicrm_group_create( $group_id, null, $group );
				} else {
					$this->bp->civicrm_group_update( $group_id, $group );
				}

				// Get paging value, or start at the beginning if not present.
				$members_page = intval( get_option( '_bgcs_members_page', '1' ) );

				$member_params = [
					'exclude_admins_mods' => 0,
					'page' => $members_page,
					'per_page' => 20,
					'group_id' => $group_id,
				];

				// Query with our params.
				if ( bp_group_has_members( $member_params ) ) {

					// Set Members flag.
					$data['members'] = (string) $members_page;

					// Do the loop.
					while ( bp_group_members() ) {

						// Set up Member.
						bp_group_the_member();

						// Update their Membership.
						$this->bp->civicrm_group_membership_update( bp_get_group_member_id(), $group_id );

					}

					// Increment Members paging option.
					update_option( '_bgcs_members_page', (string) ( $members_page + 1 ) );

				} else {

					// Set Members flag.
					$data['members'] = 'done';

					// Delete the Members option to start from the beginning.
					delete_option( '_bgcs_members_page' );

					// Increment Groups paging option.
					update_option( '_bgcs_groups_page', (string) ( $groups_page + 1 ) );

				}

			} // End loop.

		} else {

			// Delete the Groups option to start from the beginning.
			delete_option( '_bgcs_groups_page' );

			// Set finished flag.
			$data['finished'] = 'true';

		}

		// Set success flag.
		$data['success'] = 'true';

		// Send data to browser if AJAX request.
		if ( wp_doing_ajax() ) {
			wp_send_json( $data );
		}

	}

}
