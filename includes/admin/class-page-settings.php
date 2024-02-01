<?php
/**
 * Settings Page class.
 *
 * Handles Settings Page functionality.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Settings Page class.
 *
 * A class that encapsulates Settings Page functionality.
 *
 * @since 0.5.0
 */
class BP_Groups_CiviCRM_Sync_Page_Settings extends BP_Groups_CiviCRM_Sync_Page_Settings_Base {

	/**
	 * Plugin object.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync
	 */
	public $plugin;

	/**
	 * Admin object.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_Admin
	 */
	public $admin;

	/**
	 * Form Parent Group input element ID.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var string
	 */
	public $form_parent_group_id = 'parent_id';

	/**
	 * Form interval ID.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var string
	 */
	public $form_interval_id = 'interval_id';

	/**
	 * Form sync direction ID.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var string
	 */
	public $form_direction_id = 'direction_id';

	/**
	 * Form batch ID.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var string
	 */
	public $form_batch_id = 'batch_id';

	/**
	 * Class constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param object $admin The admin object.
	 */
	public function __construct( $admin ) {

		// Store references to objects.
		$this->plugin = $admin->plugin;
		$this->admin  = $admin;

		// Set a unique prefix for all Pages.
		$this->hook_prefix_common = 'bpgcs_admin';

		// Set a unique prefix.
		$this->hook_prefix = 'bpgcs_settings';

		// Assign page slugs.
		$this->page_slug = 'bpgcs_settings';

		/*
		// Assign page layout.
		$this->page_layout = 'dashboard';
		*/

		// Assign path to plugin directory.
		$this->path_plugin = BP_GROUPS_CIVICRM_SYNC_PATH;

		// Assign form IDs.
		$this->form_parent_group_id = $this->hook_prefix . '_' . $this->form_parent_group_id;
		$this->form_interval_id     = $this->hook_prefix . '_' . $this->form_interval_id;
		$this->form_direction_id    = $this->hook_prefix . '_' . $this->form_direction_id;
		$this->form_batch_id        = $this->hook_prefix . '_' . $this->form_batch_id;

		// Bootstrap parent.
		parent::__construct();

	}

	/**
	 * Initialises this object.
	 *
	 * @since 0.5.0
	 */
	public function initialise() {

		// Assign translated strings.
		$this->plugin_name          = __( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' );
		$this->page_title           = __( 'Settings for BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' );
		$this->page_tab_label       = __( 'Settings', 'bp-groups-civicrm-sync' );
		$this->page_menu_label      = __( 'BP Groups Sync', 'bp-groups-civicrm-sync' );
		$this->page_help_label      = __( 'BP Groups CiviCRM Sync', 'bp-groups-civicrm-sync' );
		$this->metabox_submit_title = __( 'Settings', 'bp-groups-civicrm-sync' );

	}

	/**
	 * Adds styles.
	 *
	 * @since 0.5.0
	 */
	public function admin_styles() {

		// Enqueue our "Settings Page" stylesheet.
		wp_enqueue_style(
			$this->hook_prefix . '-css',
			plugins_url( 'assets/css/page-settings.css', BP_GROUPS_CIVICRM_SYNC_FILE ),
			false,
			BP_GROUPS_CIVICRM_SYNC_VERSION, // Version.
			'all' // Media.
		);

	}

	/**
	 * Adds scripts.
	 *
	 * @since 0.5.0
	 */
	public function admin_scripts() {

		// Enqueue our "Settings Page" script.
		wp_enqueue_script(
			$this->hook_prefix . '-js',
			plugins_url( 'assets/js/page-settings.js', BP_GROUPS_CIVICRM_SYNC_FILE ),
			[ 'jquery' ],
			BP_GROUPS_CIVICRM_SYNC_VERSION, // Version.
			true
		);

	}

	/**
	 * Registers meta boxes.
	 *
	 * @since 0.5.0
	 *
	 * @param string $screen_id The Settings Page Screen ID.
	 * @param array  $data The array of metabox data.
	 */
	public function meta_boxes_register( $screen_id, $data ) {

		// Bail if not the Screen ID we want.
		if ( $screen_id !== $this->page_context . $this->page_slug ) {
			return;
		}

		// Check User permissions.
		if ( ! $this->page_capability() ) {
			return;
		}

		// Define a handle for the following metabox.
		$handle = $this->hook_prefix . '_settings_general';

		// Add the metabox.
		add_meta_box(
			$handle,
			__( 'General Settings', 'bp-groups-civicrm-sync' ),
			[ $this, 'meta_box_general_render' ], // Callback.
			$screen_id, // Screen ID.
			'normal', // Column: options are 'normal' and 'side'.
			'core', // Vertical placement: options are 'core', 'high', 'low'.
			$data
		);

		/*
		// Make this metabox closed by default.
		add_filter( "postbox_classes_{$screen_id}_{$handle}", [ $this, 'meta_box_closed' ] );
		*/

		// Define a handle for the following metabox.
		$handle = $this->hook_prefix . '_settings_schedule';

		// Add the metabox.
		add_meta_box(
			$handle,
			__( 'Recurring Schedules', 'bp-groups-civicrm-sync' ),
			[ $this, 'meta_box_schedule_render' ], // Callback.
			$screen_id, // Screen ID.
			'normal', // Column: options are 'normal' and 'side'.
			'core', // Vertical placement: options are 'core', 'high', 'low'.
			$data
		);

		/*
		// Make this metabox closed by default.
		add_filter( "postbox_classes_{$screen_id}_{$handle}", [ $this, 'meta_box_closed' ] );
		*/

		/**
		 * Broadcast that the metaboxes have been added.
		 *
		 * @since 0.5.0
		 *
		 * @param string $screen_id The Screen indentifier.
		 * @param array $vars The array of metabox data.
		 */
		do_action( $this->hook_prefix . '_settings_page_meta_boxes_added', $screen_id, $data );

	}

	/**
	 * Renders "General Settings" meta box on Settings screen.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $unused Unused param.
	 * @param array $metabox Array containing id, title, callback, and args elements.
	 */
	public function meta_box_general_render( $unused, $metabox ) {

		// Get our settings.
		$parent_group = (int) $this->admin->setting_get( 'parent_group' );

		// Include template file.
		include $this->path_plugin . $this->path_template . $this->path_metabox . 'metabox-settings-general.php';

	}

	/**
	 * Renders "Recurring Schedules" meta box on Settings screen.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $unused Unused param.
	 * @param array $metabox Array containing id, title, callback, and args elements.
	 */
	public function meta_box_schedule_render( $unused, $metabox ) {

		// Get our settings.
		$interval    = $this->admin->setting_get( 'interval' );
		$direction   = $this->admin->setting_get( 'direction' );
		$batch_count = (int) $this->admin->setting_get( 'batch_count' );

		// First item.
		$first = [
			'off' => [
				'interval' => 0,
				'display'  => __( 'Off', 'bp-groups-civicrm-sync' ),
			],
		];

		// Build schedules.
		$schedules = $this->admin->schedule->intervals_get();
		$schedules = $first + $schedules;

		// Include template file.
		include $this->path_plugin . $this->path_template . $this->path_metabox . 'metabox-settings-schedule.php';

	}

	/**
	 * Performs save actions when the form has been submitted.
	 *
	 * @since 0.5.0
	 *
	 * @param string $submit_id The Settings Page form submit ID.
	 */
	public function form_save( $submit_id ) {

		// Check that we trust the source of the data.
		check_admin_referer( $this->form_nonce_action, $this->form_nonce_field );

		// Get existing interval.
		$existing_interval = $this->admin->setting_get( 'interval' );

		// Set new interval.
		$interval     = 'off';
		$interval_raw = filter_input( INPUT_POST, $this->form_interval_id );
		if ( ! empty( $interval_raw ) ) {
			$interval = sanitize_text_field( wp_unslash( $interval_raw ) );
		}
		$this->admin->setting_set( 'interval', esc_sql( $interval ) );

		// Set new sync direction.
		$direction     = 'civicrm';
		$direction_raw = filter_input( INPUT_POST, $this->form_direction_id );
		if ( ! empty( $direction_raw ) ) {
			$direction = sanitize_text_field( wp_unslash( $direction_raw ) );
		}
		$this->admin->setting_set( 'direction', esc_sql( $direction ) );

		// Set new batch count.
		$batch_count_raw = filter_input( INPUT_POST, $this->form_batch_id );
		$batch_count     = (int) sanitize_text_field( wp_unslash( $batch_count_raw ) );
		$this->admin->setting_set( 'batch_count', esc_sql( $batch_count ) );

		// Clear current scheduled event if the schedule is being deactivated.
		if ( 'off' !== $existing_interval && 'off' === $interval ) {
			$this->admin->schedule->unschedule();
		}

		/*
		 * Clear current scheduled event and add new scheduled event
		 * if the schedule is active and the interval has changed.
		 */
		if ( 'off' !== $interval && $interval !== $existing_interval ) {
			$this->admin->schedule->unschedule();
			$this->admin->schedule->schedule( $interval );
		}

		// Get existing option.
		$existing_parent_group = (int) $this->admin->setting_get( 'parent_group' );

		// Did we ask to enable Parent Group?
		$settings_parent_group = 0;
		$input_raw             = filter_input( INPUT_POST, $this->form_parent_group_id );
		if ( ! empty( $input_raw ) ) {
			$settings_parent_group = 1;
		}

		// Set option.
		$this->admin->setting_set( 'parent_group', $settings_parent_group );

		/**
		 * Fires when the settings are about to be updated.
		 *
		 * @since 0.4
		 */
		do_action_deprecated( 'bpgcs/admin/settings/update/before', [], '0.5.0', $this->hook_prefix . '/settings/form/save_before' );

		// Save settings.
		$this->admin->settings_save();

		/**
		 * Fires when the settings have been updated.
		 *
		 * @since 0.4
		 */
		do_action_deprecated( 'bpgcs/admin/settings/update/after', [], '0.5.0', $this->hook_prefix . '/settings/form/save_after' );

		// Is the Parent Group setting changing?
		if ( $existing_parent_group !== $settings_parent_group ) {

			// Are we switching from "No Parent Group"?
			if ( 0 === $existing_parent_group ) {

				// Create a Meta Group to hold all BuddyPress Groups.
				$this->plugin->civicrm->meta_group->create();

				// Assign all Synced CiviCRM Groups with no parent to the Meta Group.
				$this->plugin->civicrm->meta_group->groups_assign();

			} else {

				// Remove top-level Synced CiviCRM Groups from the Meta Group.
				$this->plugin->civicrm->meta_group->groups_remove();

				// Delete the Meta Group.
				$this->plugin->civicrm->meta_group->delete();

			}

		}

	}

}
