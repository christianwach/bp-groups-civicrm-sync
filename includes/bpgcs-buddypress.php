<?php
/**
 * BuddyPress Class.
 *
 * Handles BuddyPress-related functionality.
 *
 * @package BP_Groups_CiviCRM_Sync
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * BP Groups CiviCRM Sync BuddyPress Class.
 *
 * A class that encapsulates BuddyPress functionality.
 *
 * @since 0.1
 */
class BP_Groups_CiviCRM_Sync_BuddyPress {

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
	 * @since 0.4 Renamed.
	 * @access public
	 * @var object $civicrm The CiviCRM object.
	 */
	public $civicrm;

	/**
	 * Admin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $admin The Admin object.
	 */
	public $admin;

	/**
	 * "BP Group Hierarchy" plugin compatibility.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $bpgh The "BP Group Hierarchy" plugin compatibility object.
	 */
	public $bpgh;



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

		// Boot when plugin is loaded.
		add_action( 'bpgcs/loaded', [ $this, 'initialise' ] );

	}



	/**
	 * Initialises this class.
	 *
	 * @since 0.4
	 */
	public function initialise() {

		// Store references.
		$this->civicrm = $this->plugin->civicrm;
		$this->admin = $this->plugin->admin;

		// Bootstrap this class.
		$this->include_files();
		$this->setup_objects();

		// Register hooks on BuddyPress init.
		add_action( 'bp_setup_globals', [ $this, 'register_hooks' ], 11 );

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 0.4
		 */
		do_action( 'bpgcs/buddypress/loaded' );

	}



	/**
	 * Include files.
	 *
	 * @since 0.4
	 */
	public function include_files() {

		// Include class files.
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/bpgcs-buddypress-bpgh.php';

	}



	/**
	 * Set up this plugin's objects.
	 *
	 * @since 0.4
	 */
	public function setup_objects() {

		// Initialise objects.
		$this->bpgh = new BP_Groups_CiviCRM_Sync_BuddyPress_BPGH( $this );

	}



	/**
	 * Register hooks on BuddyPress loaded.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// Intercept BuddyPress Group modifications.
		$this->register_hooks_groups();

		// Group Membership hooks: User joins or leaves Group.
		$this->register_hooks_group_members();

		// Catch Groups admin page load.
		add_action( 'bp_groups_admin_load', [ $this, 'register_hooks_bp_groups_admin' ], 10, 1 );

		/**
		 * Broadcast that our hooks have been registered.
		 *
		 * @since 0.1
		 */
		do_action( 'bp_groups_civicrm_sync_bp_loaded' );

	}



	/**
	 * Register hooks for BuddyPress Group modifications.
	 *
	 * @since 0.4
	 */
	public function register_hooks_groups() {

		// Intercept BuddyPress Group modifications, reasonably late.
		add_action( 'groups_create_group', [ $this, 'civicrm_group_create' ], 100, 3 );
		add_action( 'groups_details_updated', [ $this, 'civicrm_group_update_details' ], 100, 1 );
		add_action( 'groups_update_group', [ $this, 'civicrm_group_update' ], 100, 2 );
		add_action( 'groups_before_delete_group', [ $this, 'civicrm_group_delete' ], 100, 1 );

	}



	/**
	 * Unregister hooks for BuddyPress Group modifications.
	 *
	 * @since 0.4
	 */
	public function unregister_hooks_groups() {

		// Intercept BuddyPress Group modifications, reasonably late.
		remove_action( 'groups_create_group', [ $this, 'civicrm_group_create' ], 100 );
		remove_action( 'groups_details_updated', [ $this, 'civicrm_group_update_details' ], 100 );
		remove_action( 'groups_update_group', [ $this, 'civicrm_group_update' ], 100 );
		remove_action( 'groups_before_delete_group', [ $this, 'civicrm_group_delete' ], 100 );

	}



	/**
	 * Add hooks when the Groups admin page is loaded.
	 *
	 * None of the "past tense" actions fire on the Groups admin page, so we
	 * have to hook into the "present tense" actions and figure out what's going
	 * on at that point.
	 *
	 * @since 0.2.2
	 *
	 * @param string $doaction Current $_GET action being performed in admin screen.
	 */
	public function register_hooks_bp_groups_admin( $doaction ) {

		// Only add hooks if saving data.
		if ( $doaction && $doaction == 'save' ) {

			// Group Membership hooks: Group Membership status is being modified.
			add_action( 'groups_promote_member', [ $this, 'member_changing_status' ], 10, 3 );
			add_action( 'groups_demote_member', [ $this, 'member_changing_status' ], 10, 2 );
			add_action( 'groups_unban_member', [ $this, 'member_changing_status' ], 10, 2 );
			add_action( 'groups_ban_member', [ $this, 'member_changing_status' ], 10, 2 );

			// User is being removed from Group.
			add_action( 'groups_remove_member', [ $this, 'civicrm_group_membership_delete' ], 10, 2 );

		}

	}



	/**
	 * Register hooks for BuddyPress Group Membership modifications.
	 *
	 * @since 0.4
	 */
	public function register_hooks_group_members() {

		// Group Membership hooks: User joins or leaves Group.
		add_action( 'groups_join_group', [ $this, 'member_just_joined_group' ], 5, 2 );
		add_action( 'groups_leave_group', [ $this, 'civicrm_group_membership_delete' ], 5, 2 );

		// Group Membership hooks: removed Group Membership.
		add_action( 'groups_removed_member', [ $this, 'member_removed_from_group' ], 10, 2 );

		// Group Membership hooks: Group Membership status is being reduced.
		add_action( 'groups_demote_member', [ $this, 'member_reduce_status' ], 10, 2 );
		add_action( 'groups_ban_member', [ $this, 'member_reduce_status' ], 10, 2 );

		// Group Membership hooks: modified Group Membership.
		add_action( 'groups_promoted_member', [ $this, 'member_changed_status' ], 10, 2 );
		add_action( 'groups_demoted_member', [ $this, 'member_changed_status' ], 10, 2 );
		add_action( 'groups_unbanned_member', [ $this, 'member_changed_status' ], 10, 2 );
		add_action( 'groups_banned_member', [ $this, 'member_changed_status' ], 10, 2 );
		add_action( 'groups_membership_accepted', [ $this, 'member_changed_status' ], 10, 2 );
		add_action( 'groups_accept_invite', [ $this, 'member_changed_status' ], 10, 2 );

	}



	/**
	 * Unregister hooks for BuddyPress Group Membership modifications.
	 *
	 * @since 0.4
	 */
	public function unregister_hooks_group_members() {

		// Remove callbacks.
		remove_action( 'groups_join_group', [ $this, 'member_just_joined_group' ], 5 );
		remove_action( 'groups_leave_group', [ $this, 'civicrm_group_membership_delete' ], 5 );
		remove_action( 'groups_removed_member', [ $this, 'member_removed_from_group' ], 10 );
		remove_action( 'groups_demote_member', [ $this, 'member_reduce_status' ], 10 );
		remove_action( 'groups_ban_member', [ $this, 'member_reduce_status' ], 10 );
		remove_action( 'groups_promoted_member', [ $this, 'member_changed_status' ], 10 );
		remove_action( 'groups_demoted_member', [ $this, 'member_changed_status' ], 10 );
		remove_action( 'groups_unbanned_member', [ $this, 'member_changed_status' ], 10 );
		remove_action( 'groups_banned_member', [ $this, 'member_changed_status' ], 10 );
		remove_action( 'groups_membership_accepted', [ $this, 'member_changed_status' ], 10 );
		remove_action( 'groups_accept_invite', [ $this, 'member_changed_status' ], 10 );

	}



	/**
	 * Checks if BuddyPress plugin is properly configured.
	 *
	 * @since 0.4
	 *
	 * @return bool True if properly configured, false otherwise.
	 */
	public function is_configured() {

		static $bp_initialised;
		if ( isset( $bp_initialised ) ) {
			return $bp_initialised;
		}

		// Assume not configured.
		$bp_initialised = false;

		// Is the Groups component active?
		if ( bp_is_active( 'groups' ) ) {
			$bp_initialised = true;
		}

		// --<
		return $bp_initialised;

	}



	// -------------------------------------------------------------------------



	/**
	 * Creates a CiviCRM Group when a BuddyPress Group is created.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param object $first_member WordPress User object.
	 * @param object $group The BuddyPress Group object.
	 */
	public function civicrm_group_create( $group_id, $first_member, $group ) {

		// Bail if sync should not happen.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Create the corresponding CiviCRM Groups.
		$civicrm_groups = $this->civicrm->sync_groups_create( $group );
		if ( $civicrm_groups === false ) {
			return;
		}

		// Save the IDs of the CiviCRM Groups.
		$this->civicrm_groups_set( $group_id, $civicrm_groups );

	}



	/**
	 * Updates a CiviCRM Group when a BuddyPress Group is updated.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 */
	public function civicrm_group_update_details( $group_id ) {

		// Bail if sync should not happen.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Get the Group object.
		$group = groups_get_group( [ 'group_id' => $group_id ] );

		// Pass to CiviCRM to update Groups.
		$success = $this->civicrm->sync_groups_update( $group );

	}



	/**
	 * Updates a CiviCRM Group when a BuddyPress Group is updated.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $group The BuddyPress Group object.
	 */
	public function civicrm_group_update( $group_id, $group ) {

		// Bail if sync should not happen.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Pass to CiviCRM to update Groups.
		$success = $this->civicrm->sync_groups_update( $group );

	}



	/**
	 * Deletes a CiviCRM Group when a BuddyPress Group is deleted.
	 *
	 * We don't need to delete our meta, as BuddyPress will do so.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 */
	public function civicrm_group_delete( $group_id ) {

		// Bail if sync should not happen.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Get the Group object.
		$group = groups_get_group( [ 'group_id' => $group_id ] );

		// Pass to CiviCRM to delete Groups.
		$success = $this->civicrm->sync_groups_delete( $group );

	}



	// -------------------------------------------------------------------------



	/**
	 * Gets the Synced CiviCRM Group IDs for a given BuddyPress Group ID.
	 *
	 * @since 0.4
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @return array|bool $sync_groups The array of Synced CiviCRM Group IDs, false otherwise.
	 */
	public function civicrm_groups_get( $group_id ) {

		// Check BuddyPress config.
		if ( ! $this->is_configured() ) {
			return false;
		}

		// Get the Synced Group IDs from the BuddyPress Group meta.
		$sync_groups = groups_get_groupmeta( $group_id, 'civicrm_groups' );
		if ( empty( $sync_groups ) ) {
			return false;
		}

		// --<
		return $sync_groups;

	}



	/**
	 * Stores the Synced CiviCRM Groups for a BuddyPress Group.
	 *
	 * @since 0.4
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param array $civicrm_groups The array of Synced CiviCRM Group IDs.
	 */
	public function civicrm_groups_set( $group_id, $civicrm_groups ) {

		// Check BuddyPress config.
		if ( ! $this->is_configured() ) {
			return;
		}

		// Update our Group Meta with the IDs of the CiviCRM Groups.
		groups_update_groupmeta( $group_id, 'civicrm_groups', $civicrm_groups );

	}



	// -------------------------------------------------------------------------



	/**
	 * Get all BuddyPress Groups.
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 *
	 * @return array $groups Array of BuddyPress Group objects.
	 */
	public function groups_get_all() {

		// Init return as empty array.
		$groups = [];

		// Check BuddyPress config.
		if ( ! $this->is_configured() ) {
			return $groups;
		}

		// Init with unlikely per_page value so we get all.
		$params = [
			'type' => 'alphabetical',
			'per_page' => 100000,
			'populate_extras' => true,
			'show_hidden' => true,
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
	 * Creates a BuddyPress Group given a title and description.
	 *
	 * @since 0.1
	 *
	 * @param string $title The title of the BuddyPress Group.
	 * @param string $description The description of the BuddyPress Group.
	 * @param int $creator_id The numeric ID of the WordPress User.
	 * @return int|bool $group_id The ID of the new BuddyPress Group, false otherwise.
	 */
	public function group_create( $title, $description, $creator_id = null ) {

		// Check BuddyPress config.
		if ( ! $this->is_configured() ) {
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
			'creator_id' => $creator_id,
			'name' => $title,
			'description' => $description,
			'slug' => groups_check_slug( sanitize_title( esc_attr( $title ) ) ),
			'status' => 'public',
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



	// -------------------------------------------------------------------------



	/**
	 * Get all Members of a BuddyPress Group.
	 *
	 * @since 0.2.2
	 *
	 * @param int $group_id The ID of the BuddyPress Group.
	 * @return array $members The Members of the Group.
	 */
	public function group_members_get_all( $group_id ) {

		// Init return as empty array.
		$members = [];

		// Check BuddyPress config.
		if ( ! $this->is_configured() ) {
			return $members;
		}

		// Params to get all Group Members.
		$params = [
			'exclude_admins_mods' => 0,
			'per_page' => 1000000,
			'group_id' => $group_id,
		];

		// Query Group Members.
		$has_members = bp_group_has_members( $params );

		// Access template.
		global $members_template;

		// If we we get any, return them.
		if ( $has_members ) {
			return $members_template->members;
		}

		// --<
		return $members;

	}



	/**
	 * Create BuddyPress Group Members given an array of CiviCRM Contacts.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $contacts An array of CiviCRM Contact data.
	 * @param bool $is_admin Makes this Member a Group Admin.
	 */
	public function group_members_create( $group_id, $contacts, $is_admin = 0 ) {

		// Check BuddyPress config.
		if ( ! $this->is_configured() ) {
			return;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Bail if we have no Contacts.
		if ( empty( $contacts ) ) {
			return;
		}

		// Add Members of this Group as Admins.
		foreach ( $contacts as $contact ) {

			// Get WordPress User.
			$user = get_user_by( 'email', $contact['email'] );

			// Maybe create a WordPress User.
			if ( ! $user ) {
				$user = $this->wp_create_user( $contact );
			}

			// Sanity check.
			if ( $user ) {

				// Try and create Membership.
				if ( ! $this->group_member_create( $group_id, $user->ID, $is_admin ) ) {

					/**
					 * Allow something to be done on failure.
					 *
					 * @since 0.1
					 *
					 * @param int $group_id The numeric ID of the Group.
					 * @param int $user->ID The numeric ID of the User.
					 * @param bool $is_admin True if the Member is a Group Admin.
					 */
					do_action( 'bp_groups_civicrm_sync_member_create_failed', $group_id, $user->ID, $is_admin );

				} else {

					/**
					 * Allow something to be done on success.
					 *
					 * @since 0.1
					 *
					 * @param int $group_id The numeric ID of the Group.
					 * @param int $user->ID The numeric ID of the User.
					 * @param bool $is_admin True if the Member is a Group Admin.
					 */
					do_action( 'bp_groups_civicrm_sync_member_created', $group_id, $user->ID, $is_admin );

				}

			}

		}

	}



	/**
	 * Delete BuddyPress Group Members given an array of CiviCRM Contacts.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $contacts An array of CiviCRM Contact data.
	 */
	public function group_members_delete( $group_id, $contacts ) {

		// Check BuddyPress config.
		if ( ! $this->is_configured() ) {
			return;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// If we have no Members.
		if ( empty( $contacts ) ) {
			return;
		}

		// One by one.
		foreach ( $contacts as $contact ) {

			// Get WordPress User.
			$user = get_user_by( 'email', $contact['email'] );

			// Sanity check.
			if ( $user ) {

				// Try and delete Membership.
				if ( ! $this->group_member_delete( $group_id, $user->ID ) ) {

					/**
					 * Allow something to be done on failure.
					 *
					 * @since 0.1
					 *
					 * @param int $group_id The numeric ID of the Group.
					 * @param int $user->ID The numeric ID of the User.
					 */
					do_action( 'bp_groups_civicrm_sync_member_delete_failed', $group_id, $user->ID );

				} else {

					/**
					 * Allow something to be done on success.
					 *
					 * @since 0.1
					 *
					 * @param int $group_id The numeric ID of the Group.
					 * @param int $user->ID The numeric ID of the User.
					 */
					do_action( 'bp_groups_civicrm_sync_member_deleted', $group_id, $user->ID );

				}

			}

		}

	}



	/**
	 * Demote BuddyPress Group Members given an array of CiviCRM Contacts.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $contacts An array of CiviCRM Contact data.
	 */
	public function group_members_demote( $group_id, $contacts ) {

		// Check BuddyPress config.
		if ( ! $this->is_configured() ) {
			return;
		}


		// Bail if sync should not happen for this Group.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Do we have any Members?
		if ( empty( $contacts ) ) {
			return;
		}

		// One by one.
		foreach ( $contacts as $contact ) {

			// Get WordPress User.
			$user = get_user_by( 'email', $contact['email'] );

			// Sanity check.
			if ( $user ) {

				// Try and demote Member.
				if ( ! $this->group_member_demote( $group_id, $user->ID ) ) {

					/**
					 * Allow something to be done on failure.
					 *
					 * @since 0.1
					 *
					 * @param int $group_id The numeric ID of the Group.
					 * @param int $user->ID The numeric ID of the User.
					 */
					do_action( 'bp_groups_civicrm_sync_member_demote_failed', $group_id, $user->ID );

				} else {

					/**
					 * Allow something to be done on success.
					 *
					 * @since 0.1
					 *
					 * @param int $group_id The numeric ID of the Group.
					 * @param int $user->ID The numeric ID of the User.
					 */
					do_action( 'bp_groups_civicrm_sync_member_demoted', $group_id, $user->ID );

				}

			}

		}

	}



	/**
	 * Promote BuddyPress Group Members given an array of CiviCRM Contacts.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $contacts An array of CiviCRM Contact data.
	 * @param string $status The status to which the Members will be promoted.
	 */
	public function group_members_promote( $group_id, $contacts, $status ) {

		// Check BuddyPress config.
		if ( ! $this->is_configured() ) {
			return;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Bail if we have no Contacts.
		if ( empty( $contacts ) ) {
			return;
		}

		// One by one.
		foreach ( $contacts as $contact ) {

			// Get WordPress User.
			$user = get_user_by( 'email', $contact['email'] );

			// Sanity check.
			if ( $user ) {

				// Try and promote Member.
				if ( ! $this->group_member_promote( $group_id, $user->ID, $status ) ) {

					/**
					 * Allow something to be done on failure.
					 *
					 * @since 0.1
					 *
					 * @param int $group_id The numeric ID of the Group.
					 * @param int $user->ID The numeric ID of the User.
					 */
					do_action( 'bp_groups_civicrm_sync_member_promote_failed', $group_id, $user->ID );

				} else {

					/**
					 * Allow something to be done on success.
					 *
					 * @since 0.1
					 *
					 * @param int $group_id The numeric ID of the Group.
					 * @param int $user->ID The numeric ID of the User.
					 */
					do_action( 'bp_groups_civicrm_sync_member_promoted', $group_id, $user->ID );

				}

			}

		}

	}



	// -------------------------------------------------------------------------



	/**
	 * Creates a BuddyPress Group Membership given a title and description.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @param bool $is_admin Makes this Member a Group Admin.
	 * @return bool $success True if successful, false if not.
	 */
	public function group_member_create( $group_id, $user_id, $is_admin = 0 ) {

		// Check BuddyPress config.
		if ( ! $this->is_configured() ) {
			return false;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return false;
		}

		// Check existing Membership.
		$is_member = groups_is_user_member( $user_id, $group_id );

		// If User is already a Member.
		if ( $is_member ) {

			// If they are being promoted.
			if ( $is_admin == 1 ) {

				// Promote them to Admin.
				$this->group_member_promote( $group_id, $user_id, 'admin' );

			} else {

				// Demote them if needed.
				$this->group_member_demote( $group_id, $user_id );

			}

			// Either way, skip creation.
			return true;

		}

		// Unhook our action to prevent BuddyPress->CiviCRM sync.
		remove_action( 'groups_join_group', [ $this, 'member_just_joined_group' ], 5 );

		// Use BuddyPress function.
		$success = groups_join_group( $group_id, $user_id );

		// Re-hook our action to enable BuddyPress->CiviCRM sync.
		add_action( 'groups_join_group', [ $this, 'member_just_joined_group' ], 5, 2 );

		/*
		// Set up Member.
		$new_member = new BP_Groups_Member;
		$new_member->group_id = $group_id;
		$new_member->user_id = $user_id;
		$new_member->inviter_id = 0;
		$new_member->is_admin = $is_admin;
		$new_member->user_title = '';
		$new_member->date_modified = bp_core_current_time();
		$new_member->is_confirmed = 1;

		// Save the Membership.
		if ( ! $new_member->save() ) {
			return false;
		}
		*/

		// --<
		return $success;

	}



	/**
	 * Delete a BuddyPress Group Membership given a WordPress User.
	 *
	 * We cannot use 'groups_remove_member()' because the logged in User may not
	 * pass the 'bp_is_item_admin()' check in that function.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @return bool $success True if successful, false if not.
	 */
	public function group_member_delete( $group_id, $user_id ) {

		// Check BuddyPress config.
		if ( ! $this->is_configured() ) {
			return false;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return false;
		}

		// Bail if User is not a Member.
		if ( ! groups_is_user_member( $user_id, $group_id ) ) {
			return false;
		}

		// Set up object.
		$member = new BP_Groups_Member( $user_id, $group_id );

		/**
		 * Trigger BuddyPress action.
		 *
		 * We hook in to 'groups_removed_member' so we can trigger this action
		 * without recursion.
		 *
		 * @since 0.1
		 *
		 * @param int $group_id The numeric ID of the Group.
		 * @param int $user_id The numeric ID of the User.
		 */
		do_action( 'groups_remove_member', $group_id, $user_id );

		// Remove Member.
		$success = $member->remove();

		// --<
		return $success;

	}



	/**
	 * Demote a BuddyPress Group Member given a WordPress User.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @return bool $success True if successful, false if not.
	 */
	public function group_member_demote( $group_id, $user_id ) {

		// Check BuddyPress config.
		if ( ! $this->is_configured() ) {
			return false;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return false;
		}

		// Bail if User is not a Member.
		if ( ! groups_is_user_member( $user_id, $group_id ) ) {
			return false;
		}

		// Set up object.
		$member = new BP_Groups_Member( $user_id, $group_id );

		/**
		 * Trigger BuddyPress action.
		 *
		 * We hook in to 'groups_demoted_member' so we can trigger this action
		 * without recursion.
		 *
		 * @since 0.1
		 *
		 * @param int $group_id The numeric ID of the Group.
		 * @param int $user_id The numeric ID of the User.
		 */
		do_action( 'groups_demote_member', $group_id, $user_id );

		// Demote them.
		$success = $member->demote();

		// --<
		return $success;

	}



	/**
	 * Promote a BuddyPress Group Member given a WordPress User and a status.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @param string $status The status to which the Member will be promoted.
	 * @return bool $success True if successful, false if not.
	 */
	public function group_member_promote( $group_id, $user_id, $status ) {

		// Check BuddyPress config.
		if ( ! $this->is_configured() ) {
			return false;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return false;
		}

		// Bail if User is not a Member.
		if ( ! groups_is_user_member( $user_id, $group_id ) ) {
			return false;
		}

		// Set up object.
		$member = new BP_Groups_Member( $user_id, $group_id );

		/**
		 * Trigger BuddyPress action.
		 *
		 * We hook in to 'groups_promoted_member' so we can trigger this action
		 * without recursion.
		 *
		 * @since 0.1
		 *
		 * @param int $group_id The numeric ID of the Group.
		 * @param int $user_id The numeric ID of the User.
		 * @param string $status The status to which the Member will be promoted.
		 */
		do_action( 'groups_promote_member', $group_id, $user_id, $status );

		// Promote them.
		$success = $member->promote( $status );

		// --<
		return $success;

	}



	// -------------------------------------------------------------------------



	/**
	 * Called when User joins Group.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $user_id The numeric ID of the WordPress User.
	 */
	public function member_just_joined_group( $group_id, $user_id ) {

		// Call update method.
		$this->civicrm_group_membership_update( $user_id, $group_id );

	}



	/**
	 * Called when User's Group status is about to change.
	 *
	 * Parameter order ($group_id, $user_id) is the opposite of the "past tense"
	 * hooks. Compare, for example, 'groups_promoted_member'.
	 *
	 * @see $this->register_hooks_bp_groups_admin()
	 *
	 * @since 0.2.2
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @param string $status New status being changed to.
	 */
	public function member_changing_status( $group_id, $user_id, $status = '' ) {

		// If we have no status, check if User is Group Admin.
		if ( empty( $status ) ) {
			$status = groups_is_user_admin( $user_id, $group_id ) ? 'admin' : '';
		}

		/*
		 * Group Admins cannot be banned.
		 * @see BP_Groups_Member::ban()
		 */
		if ( $status == 'admin' && 'groups_ban_member' == current_action() ) {
			return;
		}

		// If a Group Admin is being demoted, set special status.
		if ( $status == 'admin' && 'groups_demote_member' == current_action() ) {
			$status = 'ex-admin';
		}

		// Is this User being banned?
		$is_active = 1;
		if ( 'groups_ban_member' == current_action() ) {
			$is_active = 0;
		}

		// Update Membership of CiviCRM Group.
		$args = [
			'action' => 'add',
			'group_id' => $group_id,
			'user_id' => $user_id,
			'status' => $status,
			'is_active' => $is_active,
		];

		// Update the corresponding CiviCRM Group memberships.
		$this->civicrm->group_contact->memberships_sync( $args );

	}



	/**
	 * Called when User's Group status is being reduced.
	 *
	 * @since 0.4
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $user_id The numeric ID of the WordPress User.
	 */
	public function member_reduce_status( $group_id, $user_id ) {

		// Bail if not on front-end.
		if ( is_admin() ) {
			return;
		}

		// Check if User is Group Admin.
		$status = groups_is_user_admin( $user_id, $group_id ) ? 'admin' : '';

		/*
		 * Group Admins cannot be banned.
		 * @see BP_Groups_Member::ban()
		 */
		if ( $status == 'admin' && 'groups_ban_member' == current_action() ) {
			return;
		}

		// If a Group Admin is being demoted, set special status.
		if ( $status == 'admin' && 'groups_demote_member' == current_action() ) {
			$status = 'ex-admin';
		}

		// Set a flag for use in "past tense" callback.
		$this->old_status = $status;

	}



	/**
	 * Called when User's Group status has changed.
	 *
	 * Parameter order ($user_id, $group_id) is reversed for these "past tense"
	 * hooks. Compare, for example, 'groups_join_group', 'groups_promote_member'
	 * and other "present tense" hooks.
	 *
	 * @since 0.1
	 *
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 */
	public function member_changed_status( $user_id, $group_id ) {

		// Call update method.
		$this->civicrm_group_membership_update( $user_id, $group_id );

	}



	/**
	 * Called when a User has been removed from a Group.
	 *
	 * Parameter order ($user_id, $group_id) is reversed for this "past tense"
	 * hook. Compare, for example, 'groups_join_group'.
	 *
	 * @since 0.2.2
	 *
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 */
	public function member_removed_from_group( $user_id, $group_id ) {

		// Call delete method.
		$this->civicrm_group_membership_delete( $group_id, $user_id );

	}



	// -------------------------------------------------------------------------



	/**
	 * Inform CiviCRM of Membership status change.
	 *
	 * @since 0.1
	 *
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 */
	public function civicrm_group_membership_update( $user_id, $group_id ) {

		// Bail if sync should not happen for this Group.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Get current Member status.
		$status = $this->group_status_get_for_user( $user_id, $group_id );

		// Check previous Member status.
		if ( isset( $this->old_status ) && $this->old_status === 'ex-admin' ) {
			$status = $this->old_status;
			unset( $this->old_status );
		}

		// Is this Member active?
		$is_active = 1;
		if ( groups_is_user_banned( $user_id, $group_id ) ) {
			$is_active = 0;
		}

		// Update Membership of CiviCRM Groups.
		$args = [
			'action' => 'add',
			'group_id' => $group_id,
			'user_id' => $user_id,
			'status' => $status,
			'is_active' => $is_active,
		];

		// Update the corresponding CiviCRM Group memberships.
		$this->civicrm->group_contact->memberships_sync( $args );

	}



	/**
	 * Inform CiviCRM of Membership status change.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $user_id The numeric ID of the WordPress User.
	 */
	public function civicrm_group_membership_delete( $group_id, $user_id ) {

		// Bail if sync should not happen for this Group.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Get current User status.
		$status = $this->group_status_get_for_user( $user_id, $group_id );

		// Remove Membership of CiviCRM Groups.
		$args = [
			'action' => 'delete',
			'group_id' => $group_id,
			'user_id' => $user_id,
			'status' => $status,
			'is_active' => 0,
		];

		// Remove the corresponding CiviCRM Group memberships.
		$this->civicrm->group_contact->memberships_sync( $args );

	}



	// -------------------------------------------------------------------------
	// Moved.
	// -------------------------------------------------------------------------



	/**
	 * Get BuddyPress Group Membership status for a User.
	 *
	 * The following is modified code from the BuddyPress Groupblog plugin.
	 *
	 * @since 0.1
	 *
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @return string $user_group_status The Membership status for a User.
	 */
	public function group_status_get_for_user( $user_id, $group_id ) {

		// Check BuddyPress config.
		if ( ! $this->is_configured() ) {
			return false;
		}

		// Access BuddyPress.
		$bp = buddypress();

		// Init return.
		$user_group_status = false;

		// Get the current User's Group status.
		// For efficiency, we try first to look at the current Group object.
		if ( isset( $bp->groups->current_group->id ) && $group_id == $bp->groups->current_group->id ) {

			// It's tricky to walk through the Admin/Mod lists over and over, so let's format.
			if ( empty( $bp->groups->current_group->adminlist ) ) {
				$bp->groups->current_group->adminlist = [];
				if ( isset( $bp->groups->current_group->admins ) ) {
					foreach ( (array) $bp->groups->current_group->admins as $admin ) {
						if ( isset( $admin->user_id ) ) {
							$bp->groups->current_group->adminlist[] = $admin->user_id;
						}
					}
				}
			}

			if ( empty( $bp->groups->current_group->modlist ) ) {
				$bp->groups->current_group->modlist = [];
				if ( isset( $bp->groups->current_group->mods ) ) {
					foreach ( (array) $bp->groups->current_group->mods as $mod ) {
						if ( isset( $mod->user_id ) ) {
							$bp->groups->current_group->modlist[] = $mod->user_id;
						}
					}
				}
			}

			if ( in_array( $user_id, $bp->groups->current_group->adminlist ) ) {
				$user_group_status = 'admin';
			} elseif ( in_array( $user_id, $bp->groups->current_group->modlist ) ) {
				$user_group_status = 'mod';
			} else {
				// I'm assuming that if a User is passed to this function, they're a Member.
				// Doing an actual lookup is costly. Try to look for an efficient method.
				$user_group_status = 'member';
			}

		}

		// Fall back to BuddyPress functions if not set.
		if ( $user_group_status === false ) {
			if ( groups_is_user_admin( $user_id, $group_id ) ) {
				$user_group_status = 'admin';
			} elseif ( groups_is_user_mod( $user_id, $group_id ) ) {
				$user_group_status = 'mod';
			} elseif ( groups_is_user_member( $user_id, $group_id ) ) {
				$user_group_status = 'member';
			}
		}

		// Are we promoting or demoting?
		if ( bp_action_variable( 1 ) && bp_action_variable( 2 ) ) {

			// Change User status based on promotion / demotion.
			switch ( bp_action_variable( 1 ) ) {

				case 'promote':
					$user_group_status = bp_action_variable( 2 );
					break;

				case 'demote':
				case 'ban':
				case 'unban':
					$user_group_status = 'member';
					break;

			}

		}

		// --<
		return $user_group_status;

	}



	/**
	 * Check if a Group should by synced.
	 *
	 * @since 0.3.6
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @return bool $should_be_synced Whether or not the Group should be synced.
	 */
	public function group_should_be_synced( $group_id ) {

		// Never sync if BuddyPress isn't configured.
		if ( ! $this->is_configured() ) {
			return false;
		}

		// Assume User should be synced.
		$should_be_synced = true;

		/**
		 * Let other plugins override whether a Group should be synced.
		 *
		 * @since 0.3.6
		 *
		 * @param bool $should_be_synced True if the Group should be synced, false otherwise.
		 * @param int $group_id The numeric ID of the BuddyPress Group.
		 * @return bool $should_be_synced The modified value of the sync flag.
		 */
		return apply_filters( 'bp_groups_civicrm_sync_group_should_be_synced', $should_be_synced, $group_id );

	}



	/**
	 * Get a BuddyPress Group ID by CiviCRM Group data.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class and renamed.
	 *
	 * @param array $group The array of CiviCRM Group data.
	 * @return integer|bool $bp_group_id The numeric ID of the BuddyPress Group, or false if none found.
	 */
	public function group_id_get_by_civicrm_group( $group ) {

		// Bail if Group has no reference to BuddyPress.
		if ( ! $this->civicrm->has_bp_group( $group ) ) {
			return false;
		}

		// Get BuddyPress Group ID - source is of the form "BP Sync Group :BPID:".
		$tmp = explode( ':', $group['source'] );
		$bp_group_id = (int) $tmp[1];

		// --<
		return $bp_group_id;

	}



	// -------------------------------------------------------------------------
	// To move.
	// -------------------------------------------------------------------------



	/**
	 * Creates a WordPress User given a CiviCRM Contact.
	 *
	 * If this is called because a Contact has been added with "New Contact" in
	 * CiviCRM and some Group has been chosen at the same time, then the call
	 * will come via CRM_Contact_BAO_Contact::create().
	 *
	 * Unfortunately, this method adds the Contact to the Group *before* the
	 * email has been stored so the CiviCRM Contact data does not contain it.
	 * WordPress will let a User be created but they will have no email address!
	 *
	 * In order to get around this, we try and recognise this state of affairs
	 * and listen for the addition of the email address to the Contact.
	 *
	 * @see CRM_Contact_BAO_Contact::create()
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 *
	 * @param array $contact The data for the CiviCRM Contact.
	 * @return mixed $user WordPress User object or false on failure.
	 */
	public function wp_create_user( $contact ) {

		// Create username from display name.
		$user_name = sanitize_title( sanitize_user( $contact['display_name'] ) );

		// Ensure username is unique.
		$user_name = $this->unique_username( $user_name, $contact );

		/**
		 * Let plugins override the new username.
		 *
		 * @since 0.4
		 *
		 * @param str $user_name The previously-generated WordPress username.
		 * @param array $contact The CiviCRM Contact data.
		 */
		$user_name = apply_filters( 'bpgcs/bp/new_username', $user_name, $contact );

		// Check if we have a User with that username.
		$user_id = username_exists( $user_name );

		// If not, check against email address.
		if ( ! $user_id && email_exists( $contact['email'] ) == false ) {

			// Generate a random password.
			$length = 12;
			$include_standard_special_chars = false;
			$random_password = wp_generate_password( $length, $include_standard_special_chars );

			// Remove filters.
			$this->remove_filters();

			/**
			 * Allow other plugins to be aware of what we're doing.
			 *
			 * @since 0.1
			 *
			 * @param array $contact The CiviCRM Contact data.
			 */
			do_action( 'bp_groups_civicrm_sync_before_insert_user', $contact );

			// Create the User.
			$user_id = wp_insert_user( [
				'user_login' => $user_name,
				'user_pass' => $random_password,
				'user_email' => $contact['email'],
				'first_name' => $contact['first_name'],
				'last_name' => $contact['last_name'],
			] );

			// Is the email address empty?
			if ( empty( $contact['email'] ) ) {

				// Store this Contact temporarily.
				$this->temp_contact = [
					'civi' => $contact,
					'user_id' => $user_id,
				];

				// Add callback for the next "Email create" event.
				add_action( 'civicrm_post', [ $this, 'civicrm_email_updated' ], 10, 4 );

			} else {

				// Create a UFMatch record if the User was successfully created.
				if ( ! is_wp_error( $user_id ) && isset( $contact['contact_id'] ) ) {
					$this->civicrm->contact->ufmatch_create( $contact['contact_id'], $user_id, $contact['email'] );
				}

			}

			// Re-add filters.
			$this->add_filters();

			/**
			 * Allow other plugins to be aware of what we've done.
			 *
			 * @since 0.1
			 *
			 * @param array $contact The CiviCRM Contact data.
			 * @param int $user_id The numeric ID of the User.
			 */
			do_action( 'bp_groups_civicrm_sync_after_insert_user', $contact, $user_id );

		}

		// Return WordPress User if we get one.
		if ( ! empty( $user_id ) && is_numeric( $user_id ) ) {
			return get_user_by( 'id', $user_id );
		}

		// Return error.
		return false;

	}



	/**
	 * Called when a CiviCRM Contact's primary email address is updated.
	 *
	 * @since 0.3.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $object_id The ID of the object.
	 * @param object $object_ref The object.
	 */
	public function civicrm_email_updated( $op, $object_name, $object_id, $object_ref ) {

		// Target our operation.
		if ( $op != 'create' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'Email' ) {
			return;
		}

		// Remove callback even if subsequent checks fail.
		remove_action( 'civicrm_post', [ $this, 'civicrm_email_updated' ], 10, 4 );

		// Bail if we don't have a temp Contact.
		if ( ! isset( $this->temp_contact ) ) {
			return;
		}
		if ( ! is_array( $this->temp_contact ) ) {
			unset( $this->temp_contact );
			return;
		}

		// Bail if we have no email or Contact ID.
		if ( ! isset( $object_ref->email ) || ! isset( $object_ref->contact_id ) ) {
			unset( $this->temp_contact );
			return;
		}

		// Bail if this is not the same Contact as above.
		if ( $object_ref->contact_id != $this->temp_contact['civi']['contact_id'] ) {
			unset( $this->temp_contact );
			return;
		}

		// Get User ID.
		$user_id = $this->temp_contact['user_id'];

		// Create a UFMatch record.
		$this->civicrm->contact->ufmatch_create( $object_ref->contact_id, $user_id, $object_ref->email );

		// Remove filters.
		$this->remove_filters();

		/**
		 * Allow other plugins to be aware of what we're doing.
		 *
		 * @since 0.1
		 *
		 * @param array $temp_contact The temporary CiviCRM Contact data.
		 * @param object $object_ref The CiviCRM Contact data object.
		 * @param int $user_id The numeric ID of the User.
		 */
		do_action( 'bp_groups_civicrm_sync_before_update_user', $this->temp_contact, $object_ref, $user_id );

		// Update the WordPress User with this email address.
		$user_id = wp_update_user( [
			'ID' => $user_id,
			'user_email' => $object_ref->email,
		] );

		// Re-add filters.
		$this->add_filters();

		/**
		 * Allow other plugins to be aware of what we've done.
		 *
		 * @since 0.1
		 *
		 * @param array $temp_contact The temporary CiviCRM Contact data.
		 * @param object $object_ref The CiviCRM Contact data object.
		 * @param int $user_id The numeric ID of the User.
		 */
		do_action( 'bp_groups_civicrm_sync_after_update_user', $this->temp_contact, $object_ref, $user_id );

		// Uset in case of repeats.
		unset( $this->temp_contact );

	}



	/**
	 * Generate a unique username for a WordPress User.
	 *
	 * @since 0.4
	 *
	 * @param str $username The previously-generated WordPress username.
	 * @param array $contact The CiviCRM Contact data.
	 * @return str $new_username The modified WordPress username.
	 */
	public function unique_username( $username, $contact ) {

		// Bail if this is already unique.
		if ( ! username_exists( $username ) ) {
			return $username;
		}

		// Init flags.
		$count = 1;
		$user_exists = 1;

		do {

			// Construct new username with numeric suffix.
			$new_username = sanitize_title( sanitize_user( $contact['display_name'] . ' ' . $count ) );

			// How did we do?
			$user_exists = username_exists( $new_username );

			// Try the next integer.
			$count++;

		} while ( $user_exists > 0 );

		// --<
		return $new_username;

	}



	/**
	 * Remove filters (that we know of) that will interfere with creating a WordPress User.
	 *
	 * @since 0.1
	 */
	private function remove_filters() {

		// Get CiviCRM instance.
		$civicrm = civi_wp();

		// Do we have the old-style plugin structure?
		if ( method_exists( $civicrm, 'update_user' ) ) {

			// Remove previous CiviCRM plugin filters.
			remove_action( 'user_register', [ $civicrm, 'update_user' ] );
			remove_action( 'profile_update', [ $civicrm, 'update_user' ] );

		} else {

			// Remove current CiviCRM plugin filters.
			remove_action( 'user_register', [ $civicrm->users, 'update_user' ] );
			remove_action( 'profile_update', [ $civicrm->users, 'update_user' ] );

		}

		// Remove CiviCRM WordPress Profile Sync filters.
		global $civicrm_wp_profile_sync;
		if ( is_object( $civicrm_wp_profile_sync ) ) {
			$civicrm_wp_profile_sync->hooks_wp_remove();
			$civicrm_wp_profile_sync->hooks_bp_remove();
		}

		/**
		 * Broadcast that we're removing User actions.
		 *
		 * @since 0.4
		 */
		do_action( 'bpgcs/remove_filters' );

	}



	/**
	 * Add filters (that we know of) after creating a WordPress User.
	 *
	 * @since 0.1
	 */
	private function add_filters() {

		// Get CiviCRM instance.
		$civicrm = civi_wp();

		// Do we have the old-style plugin structure?
		if ( method_exists( $civicrm, 'update_user' ) ) {

			// Re-add previous CiviCRM plugin filters.
			add_action( 'user_register', [ $civicrm, 'update_user' ] );
			add_action( 'profile_update', [ $civicrm, 'update_user' ] );

		} else {

			// Re-add current CiviCRM plugin filters.
			add_action( 'user_register', [ $civicrm->users, 'update_user' ] );
			add_action( 'profile_update', [ $civicrm->users, 'update_user' ] );

		}

		// Re-add CiviCRM WordPress Profile Sync filters.
		global $civicrm_wp_profile_sync;
		if ( is_object( $civicrm_wp_profile_sync ) ) {
			$civicrm_wp_profile_sync->hooks_wp_add();
			$civicrm_wp_profile_sync->hooks_bp_add();
		}

		/**
		 * Let other plugins know that we're adding User actions.
		 *
		 * @since 0.4
		 */
		do_action( 'bpgcs/add_filters' );

	}



} // Class ends.
