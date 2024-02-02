<?php
/**
 * BuddyPress Groups class.
 *
 * Handles BuddyPress Group-related functionality.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * BuddyPress Groups class.
 *
 * A class that handles BuddyPress Groups functionality.
 *
 * @since 0.5.0
 */
class BP_Groups_CiviCRM_Sync_BuddyPress_Group {

	/**
	 * Plugin object.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync
	 */
	public $plugin;

	/**
	 * BuddyPress object.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_BuddyPress
	 */
	public $bp;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference to objects.
		$this->plugin = $parent->plugin;
		$this->bp     = $parent;

		// Boot when CiviCRM object is loaded.
		add_action( 'bpgcs/buddypress/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Initialises this class.
	 *
	 * @since 0.5.0
	 */
	public function initialise() {

		// Bootstrap this class.
		$this->register_hooks();

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 0.5.0
		 */
		do_action( 'bpgcs/buddypress/group/loaded' );

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.5.0
	 */
	public function register_hooks() {

		// Register BuddyPress Group hooks.
		$this->register_hooks_groups();

		// Add a link to the CiviCRM Group.
		add_action( 'bp_groups_admin_comment_row_actions', [ $this, 'links_to_civicrm_add' ], 10, 3 );

		// Maybe add Menu Items to CiviCRM Admin Utilities menu.
		add_action( 'bp_groups_admin_meta_boxes', [ $this, 'menu_item_hook' ], 10, 2 );

	}

	/**
	 * Unregister hooks.
	 *
	 * @since 0.5.0
	 */
	public function unregister_hooks() {

		// Unregister BuddyPress Group hooks.
		$this->unregister_hooks_groups();

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Registers BuddyPress Group hooks.
	 *
	 * @since 0.4
	 * @since 0.5.0 Moved to this class.
	 */
	public function register_hooks_groups() {

		// Intercept BuddyPress Group modifications, reasonably late.
		add_action( 'groups_create_group', [ $this, 'group_created' ], 100, 3 );
		add_action( 'groups_details_updated', [ $this, 'group_details_updated' ], 100, 1 );
		add_action( 'groups_update_group', [ $this, 'group_updated' ], 100, 2 );
		add_action( 'groups_before_delete_group', [ $this, 'group_deleted_pre' ], 100, 1 );

	}

	/**
	 * Unregisters BuddyPress Group hooks.
	 *
	 * @since 0.4
	 * @since 0.5.0 Moved to this class.
	 */
	public function unregister_hooks_groups() {

		// Intercept BuddyPress Group modifications, reasonably late.
		remove_action( 'groups_create_group', [ $this, 'group_created' ], 100 );
		remove_action( 'groups_details_updated', [ $this, 'group_details_updated' ], 100 );
		remove_action( 'groups_update_group', [ $this, 'group_updated' ], 100 );
		remove_action( 'groups_before_delete_group', [ $this, 'group_deleted_pre' ], 100 );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Fires when a BuddyPress Group has been created.
	 *
	 * Creates the corresponding CiviCRM Groups.
	 *
	 * @since 0.1
	 * @since 0.5.0 Moved here and renamed.
	 *
	 * @param int    $group_id The numeric ID of the BuddyPress Group.
	 * @param object $first_member WordPress User object.
	 * @param object $group The BuddyPress Group object.
	 * @return array $civicrm_groups The array of data for the new CiviCRM Groups.
	 */
	public function group_created( $group_id, $first_member, $group ) {

		// Bail if sync should not happen.
		if ( ! $this->should_be_synced( $group_id ) ) {
			return false;
		}

		// Create the corresponding CiviCRM Groups.
		$civicrm_groups = $this->plugin->civicrm->group->sync_groups_create( $group );
		if ( false === $civicrm_groups ) {
			return false;
		}

		// Save the IDs of the CiviCRM Groups.
		$this->plugin->civicrm->group->groups_for_bp_group_id_set( $group_id, $civicrm_groups );

		// --<
		return $civicrm_groups;

	}

	/**
	 * Fires when the details of a BuddyPress Group have been updated.
	 *
	 * Updates the corresponding CiviCRM Groups.
	 *
	 * @since 0.1
	 * @since 0.5.0 Moved here and renamed.
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @return bool|array $success True when successfully updated or false on failure.
	 *                             A keyed array of Group IDs when Groups are created.
	 */
	public function group_details_updated( $group_id ) {

		// Bail if sync should not happen.
		if ( ! $this->should_be_synced( $group_id ) ) {
			return;
		}

		// Get the Group object.
		$group = groups_get_group( [ 'group_id' => $group_id ] );

		// Pass to CiviCRM to update Groups.
		$success = $this->plugin->civicrm->group->sync_groups_update( $group );

		// Return early unless Groups have been created.
		if ( is_bool( $success ) ) {
			return $success;
		}

		/*
		 * The following code should not run in normal operation, however it is
		 * included as a failsafe for smaller groups. It might time out if there
		 * are a large number of BuddyPress Group Users, so it's limited to 50
		 * for now and writes to the error log as a notification.
		 */

		// Save the IDs of the CiviCRM Groups.
		$this->plugin->civicrm->group->groups_for_bp_group_id_set( $group_id, $success );

		// Now sync all Group Users.
		$group_user_ids = $this->plugin->bp->group_member->group_user_ids_get( $group_id );
		if ( count( $group_user_ids ) < 51 ) {
			foreach ( $group_user_ids as $user_id ) {
				$this->bp->group_member->civicrm_group_membership_update( $user_id, $group_id );
			}
		} else {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => __( 'Please run Manual Sync to sync Group Members to CiviCRM', 'bp-groups-civicrm-sync' ),
				'group'     => $group,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
		}

		// --<
		return $success;

	}

	/**
	 * Fires when a BuddyPress Group has been updated.
	 *
	 * Updates the corresponding CiviCRM Groups.
	 *
	 * @since 0.1
	 * @since 0.5.0 Moved here and renamed.
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $group The BuddyPress Group object.
	 * @return bool|array $success True when successfully updated or false on failure.
	 *                             A keyed array of Group IDs when Groups are created.
	 */
	public function group_updated( $group_id, $group ) {

		// Bail if sync should not happen.
		if ( ! $this->should_be_synced( $group_id ) ) {
			return;
		}

		// Pass to CiviCRM to update Groups.
		$success = $this->plugin->civicrm->group->sync_groups_update( $group );

		// Return early unless Groups have been created.
		if ( is_bool( $success ) ) {
			return $success;
		}

		/*
		 * The following code should not run in normal operation, however it is
		 * included as a failsafe for smaller groups. It might time out if there
		 * are a large number of BuddyPress Group Users, so it's limited to 50
		 * for now and writes to the error log as a notification.
		 */

		// Save the IDs of the CiviCRM Groups.
		$this->plugin->civicrm->group->groups_for_bp_group_id_set( $group_id, $success );

		// Now sync all Group Users.
		$group_user_ids = $this->plugin->bp->group_member->group_user_ids_get( $group_id );
		if ( count( $group_user_ids ) < 51 ) {
			foreach ( $group_user_ids as $user_id ) {
				$this->bp->group_member->civicrm_group_membership_update( $user_id, $group_id );
			}
		} else {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => __( 'Please run Manual Sync to sync Group Members to CiviCRM', 'bp-groups-civicrm-sync' ),
				'group'     => $group,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
		}

		// --<
		return $success;

	}

	/**
	 * Fires when a BuddyPress Group is about to be deleted.
	 *
	 * Deletes the corresponding CiviCRM Groups.
	 * We don't need to delete our meta because BuddyPress will do that.
	 *
	 * @since 0.1
	 * @since 0.5.0 Moved here and renamed.
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 */
	public function group_deleted_pre( $group_id ) {

		// Bail if sync should not happen.
		if ( ! $this->should_be_synced( $group_id ) ) {
			return;
		}

		// Get the Group object.
		$group = groups_get_group( [ 'group_id' => $group_id ] );

		// Pass to CiviCRM to delete Groups.
		$success = $this->plugin->civicrm->group->sync_groups_delete( $group );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Creates a BuddyPress Group with a given title and description.
	 *
	 * @since 0.1
	 * @since 0.5.0 Moved here and renamed.
	 *
	 * @param string $title The title of the BuddyPress Group.
	 * @param string $description The description of the BuddyPress Group.
	 * @param int    $creator_id The numeric ID of the WordPress User.
	 * @return int|bool $group_id The ID of the new BuddyPress Group, false otherwise.
	 */
	public function create( $title, $description, $creator_id = null ) {

		// Check BuddyPress config.
		if ( ! $this->bp->is_configured() ) {
			return false;
		}

		// Set creator to current WordPress User if no CiviCRM Contact passed.
		if ( empty( $creator_id ) ) {
			$creator_id = bp_loggedin_user_id();
		}

		// Get current time.
		$time = current_time( 'mysql' );

		/*
		 * Possible parameters:
		 *
		 *	'group_id'
		 *	'creator_id'
		 *	'name'
		 *	'description'
		 *	'slug'
		 *	'status'
		 *	'enable_forum'
		 *	'date_created'
		 *
		 * @see groups_create_group()
		 */
		$args = [
			// Group ID is not passed so that we create a Group.
			'creator_id'   => $creator_id,
			'name'         => $title,
			'description'  => $description,
			'slug'         => groups_check_slug( sanitize_title( esc_attr( $title ) ) ),
			'status'       => 'public',
			'enable_forum' => 0,
			'date_created' => $time,
		];

		// Let BuddyPress do the work.
		$group_id = groups_create_group( $args );

		// Add some meta.
		groups_update_groupmeta( $group_id, 'total_member_count', 1 );
		groups_update_groupmeta( $group_id, 'last_activity', $time );
		groups_update_groupmeta( $group_id, 'invite_status', 'members' );

		/**
		 * Broadcast that a BuddyPress Group has been created.
		 *
		 * @since 0.1
		 *
		 * @param int $group_id The numeric ID of the new Group.
		 */
		do_action( 'bp_groups_civicrm_sync_group_created', $group_id );

		// --<
		return $group_id;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Checks if a Group should by synced.
	 *
	 * @since 0.3.6
	 * @since 0.5.0 Moved here.
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @return bool $should_be_synced Whether or not the Group should be synced.
	 */
	public function should_be_synced( $group_id ) {

		// Never sync if BuddyPress isn't configured.
		if ( ! $this->bp->is_configured() ) {
			return false;
		}

		// Assume User should be synced.
		$should_be_synced = true;

		/**
		 * Let other plugins override whether a Group should be synced.
		 *
		 * @since 0.3.6
		 * @deprecated 0.5.0 Use the {@see 'bpgcs/buddypress/group/should_be_synced'} filter instead.
		 *
		 * @param bool $should_be_synced True if the Group should be synced, false otherwise.
		 * @param int $group_id The numeric ID of the BuddyPress Group.
		 */
		$should_be_synced = apply_filters_deprecated(
			'bp_groups_civicrm_sync_should_be_synced',
			[ $should_be_synced, $group_id ],
			'0.5.0',
			'bpgcs/buddypress/group/should_be_synced'
		);

		/**
		 * Filters whether or not a Group should be synced.
		 *
		 * @since 0.5.0
		 *
		 * @param bool $should_be_synced True if the Group should be synced, false otherwise.
		 * @param int $group_id The numeric ID of the BuddyPress Group.
		 */
		return apply_filters( 'bpgcs/buddypress/group/should_be_synced', $should_be_synced, $group_id );

	}

	/**
	 * Gets a BuddyPress Group ID for a given CiviCRM Group.
	 *
	 * @since 0.1
	 * @since 0.5.0 Moved here and renamed.
	 * @since 0.5.0 Allow CiviCRM Group ID to be passed.
	 *
	 * @param array|int $group The array of CiviCRM Group data, or a CiviCRM Group ID.
	 * @return integer|bool $bp_group_id The ID of the BuddyPress Group, or false if none found.
	 */
	public function id_get_by_civicrm_group( $group ) {

		// Get the Group if an integer is passed.
		if ( is_int( $group ) ) {
			$group = $this->plugin->civicrm->group->get_by_id( $group );
		}

		// Get the ID from Group "source".
		$bp_group_id = $this->plugin->civicrm->group->id_get_by_source( $group['source'] );

		// --<
		return $bp_group_id;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get all BuddyPress Groups.
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 * @since 0.5.0 Moved here.
	 *
	 * @return array $groups Array of BuddyPress Group objects.
	 */
	public function groups_get_all() {

		// Init return as empty array.
		$groups = [];

		// Check BuddyPress config.
		if ( ! $this->bp->is_configured() ) {
			return $groups;
		}

		// Init with unlikely per_page value so we get all.
		$params = [
			'type'            => 'alphabetical',
			'per_page'        => 100000,
			'populate_extras' => true,
			'show_hidden'     => true,
		];

		// Query with our params.
		$has_groups = bp_has_groups( $params );

		// Access template.
		global $groups_template;

		// If we we get any, return them.
		if ( $has_groups ) {
			return $groups_template->groups;
		}

		// Fallback.
		return $groups;

	}

	/**
	 * Queries for all BuddyPress Group IDs.
	 *
	 * @since 0.5.0
	 *
	 * @return array $group_ids The array of BuddyPress Group IDs.
	 */
	public function ids_query_all() {

		// Init return as empty array.
		$group_ids = [];

		// Check BuddyPress config.
		if ( ! $this->bp->is_configured() ) {
			return $groups;
		}

		// Build params.
		$params = [
			'type'            => 'alphabetical',
			'per_page'        => 0,
			'populate_extras' => false,
			'show_hidden'     => true,
			'fields'          => 'ids',
		];

		// Query with our params.
		$group_ids = groups_get_groups( $params );

		// --<
		return $group_ids;

	}

	/**
	 * Gets all BuddyPress Group IDs.
	 *
	 * @since 0.5.0
	 *
	 * @return array $group_ids The array of BuddyPress Group IDs.
	 */
	public function ids_get_all() {

		$group_ids = [];

		// Query the Group ID data.
		$result = $this->ids_query_all();

		// Use "groups" sub-array.
		if ( ! empty( $result['groups'] ) ) {
			$group_ids = $result['groups'];
		}

		// --<
		return $group_ids;

	}

	/**
	 * Counts all BuddyPress Groups.
	 *
	 * @since 0.5.0
	 *
	 * @return int $groups_total The total number of BuddyPress Groups.
	 */
	public function total_get() {

		$groups_total = 0;

		// Query the Group ID data.
		$result = $this->ids_query_all();

		// Use "groups" sub-array.
		if ( ! empty( $result['total'] ) ) {
			$groups_total = (int) $result['total'];
		}

		// --<
		return $groups_total;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Adds links to the synced CiviCRM Groups.
	 *
	 * @since 0.4.4
	 * @since 0.5.0 Moved here.
	 *
	 * @param array $actions Array of actions to be displayed for the column content.
	 * @param array $item The current group item in the loop.
	 * @return array $actions Modified array of actions to be displayed for the column content.
	 */
	public function links_to_civicrm_add( $actions, $item ) {

		// CiviCRM Member Group.
		$sync_name       = $this->plugin->civicrm->group->member_group_get_sync_name( $item['id'] );
		$member_group_id = $this->plugin->civicrm->group->id_find( $sync_name );
		if ( ! empty( $member_group_id ) ) {
			$member_url      = $this->plugin->civicrm->link_admin_get( 'civicrm/group/edit', 'reset=1&action=update&id=' . $member_group_id );
			$actions['civicrm_member'] = sprintf( '<a href="%s">%s</a>', esc_url( $member_url ), __( 'CiviCRM', 'bp-groups-civicrm-sync' ) );
		}

		// CiviCRM Admin Group.
		$sync_name    = $this->plugin->civicrm->group->acl_group_get_sync_name( $item['id'] );
		$acl_group_id = $this->plugin->civicrm->group->id_find( $sync_name );
		if ( ! empty( $acl_group_id ) ) {
			$acl_url      = $this->plugin->civicrm->link_admin_get( 'civicrm/group/edit', 'reset=1&action=update&id=' . $acl_group_id );
			$actions['civicrm_acl'] = sprintf( '<a href="%s">%s</a>', esc_url( $acl_url ), __( 'ACL', 'bp-groups-civicrm-sync' ) );
		}

		// --<
		return $actions;

	}

	/**
	 * Registers a hook to add a Menu Items to the CiviCRM Admin Utilities menu.
	 *
	 * @since 0.5.0
	 */
	public function menu_item_hook() {

		// Bail if User doesn't have BuddyPress capability.
		if ( ! bp_current_user_can( 'bp_moderate' ) ) {
			return;
		}

		// Bail if Contact doesn't have CiviCRM capability.
		if ( ! $this->plugin->civicrm->check_permission( 'edit groups' ) ) {
			return;
		}

		// Add Menu Items to CiviCRM Admin Utilities menu.
		add_action( 'civicrm_admin_utilities_menu_top', [ $this, 'menu_item_add_to_cau' ], 10, 2 );

	}

	/**
	 * Adds Menu Items to the CiviCRM Admin Utilities menu.
	 *
	 * @since 0.5.0
	 *
	 * @param str   $id The menu parent ID.
	 * @param array $components The active CiviCRM Conponents.
	 */
	public function menu_item_add_to_cau( $id, $components ) {

		// Access WordPress admin bar.
		global $wp_admin_bar;

		// Bail if not the screen we want.
		$screen = get_current_screen();
		if ( 'toplevel_page_bp-groups' !== $screen->id ) {
			return;
		}

		// Bail if there's no Group ID.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$bp_group_id = isset( $_GET['gid'] ) ? (int) sanitize_text_field( wp_unslash( $_GET['gid'] ) ) : 0;
		if ( empty( $bp_group_id ) ) {
			return;
		}

		// Bail if User doesn't have BuddyPress capability.
		if ( ! bp_current_user_can( 'bp_moderate' ) ) {
			return;
		}

		// Bail if Contact doesn't have CiviCRM capability.
		if ( ! $this->plugin->civicrm->check_permission( 'edit groups' ) ) {
			return;
		}

		// Get CiviCRM Member Group links.
		$member_edit_url     = '';
		$member_contacts_url = '';
		$member_group        = $this->plugin->civicrm->group->get_by_bp_id( $bp_group_id, 'member' );

		if ( ! empty( $member_group ) ) {

			// Add item to menu.
			$wp_admin_bar->add_node(
				[
					'id'     => 'bpgcs-0',
					'parent' => $id,
					'title'  => __( 'Member Group Settings', 'bp-groups-civicrm-sync' ),
					'href'   => $this->plugin->civicrm->link_admin_get( 'civicrm/group/edit', 'reset=1&action=update&id=' . $member_group['id'] ),
					'meta'   => [
						'class' => 'bpgcs-menu-item',
					],
				]
			);

			// Add item to menu.
			$wp_admin_bar->add_node(
				[
					'id'     => 'bpgcs-1',
					'parent' => $id,
					'title'  => __( 'Member Group Contacts', 'bp-groups-civicrm-sync' ),
					'href'   => $this->plugin->civicrm->link_admin_get( 'civicrm/group/search', 'force=1&context=smog&gid=' . $member_group['id'] ),
					'meta'   => [
						'class' => 'bpgcs-menu-item',
					],
				]
			);

		}

		// Get CiviCRM ACL Group links.
		$acl_edit_url     = '';
		$acl_contacts_url = '';
		$acl_group        = $this->plugin->civicrm->group->get_by_bp_id( $bp_group_id, 'acl' );

		if ( ! empty( $acl_group ) ) {

			// Add item to menu.
			$wp_admin_bar->add_node(
				[
					'id'     => 'bpgcs-2',
					'parent' => $id,
					'title'  => __( 'ACL Group Settings', 'bp-groups-civicrm-sync' ),
					'href'   => $this->plugin->civicrm->link_admin_get( 'civicrm/group/edit', 'reset=1&action=update&id=' . $acl_group['id'] ),
					'meta'   => [
						'class' => 'bpgcs-menu-item',
					],
				]
			);

			// Add item to menu.
			$wp_admin_bar->add_node(
				[
					'id'     => 'bpgcs-3',
					'parent' => $id,
					'title'  => __( 'ACL Group Contacts', 'bp-groups-civicrm-sync' ),
					'href'   => $this->plugin->civicrm->link_admin_get( 'civicrm/group/search', 'force=1&context=smog&gid=' . $acl_group['id'] ),
					'meta'   => [
						'class' => 'bpgcs-menu-item',
					],
				]
			);

		}

	}

}
