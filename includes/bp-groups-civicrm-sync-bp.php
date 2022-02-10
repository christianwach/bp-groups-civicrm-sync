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
	 * Admin utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $admin The Admin utilities object.
	 */
	public $admin;

	/**
	 * Flag for overriding sync process.
	 *
	 * @since 0.1
	 * @access public
	 * @var bool $do_not_sync Flag for overriding sync process.
	 */
	public $do_not_sync = false;



	/**
	 * Initialise this object.
	 *
	 * @since 0.1
	 *
	 * @param object $parent_obj The parent object.
	 */
	public function __construct( $parent_obj ) {

		// Store reference to parent.
		$this->parent_obj = $parent_obj;

		// Add actions for plugin init on BuddyPress init.
		add_action( 'bp_setup_globals', [ $this, 'register_hooks' ], 11 );

	}



	/**
	 * Set references to other objects.
	 *
	 * @since 0.1
	 *
	 * @param object $civi_object Reference to this plugin's CiviCRM object.
	 * @param object $admin_object Reference to this plugin's Admin object.
	 */
	public function set_references( &$civi_object, &$admin_object ) {

		// Store.
		$this->civi = $civi_object;

		// Store Admin reference.
		$this->admin = $admin_object;

	}



	/**
	 * Register hooks on BuddyPress loaded.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// Intercept BuddyPress Group creation, late as we can.
		add_action( 'groups_create_group', [ $this, 'create_civi_group' ], 100, 3 );

		// Intercept Group details update.
		add_action( 'groups_details_updated', [ $this, 'update_civi_group_details' ], 100, 1 );

		// Intercept BuddyPress Group updates, late as we can.
		add_action( 'groups_update_group', [ $this, 'update_civi_group' ], 100, 2 );

		// Intercept prior to BuddyPress Group deletion so we still have Group data.
		add_action( 'groups_before_delete_group', [ $this, 'delete_civi_group' ], 100, 1 );

		// Group Membership hooks: User joins or leaves Group.
		add_action( 'groups_join_group', [ $this, 'member_just_joined_group' ], 5, 2 );
		add_action( 'groups_leave_group', [ $this, 'civi_delete_group_membership' ], 5, 2 );

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

		// Catch Groups admin page load.
		add_action( 'bp_groups_admin_load', [ $this, 'groups_admin_load' ], 10, 1 );

		// Test for presence BP Group Hierarchy plugin.
		if ( defined( 'BP_GROUP_HIERARCHY_IS_INSTALLED' ) ) {

			// The following action allows us to know that the hierarchy has been altered.
			add_action( 'bp_group_hierarchy_before_save', [ $this, 'hierarchy_before_change' ] );
			add_action( 'bp_group_hierarchy_after_save', [ $this, 'hierarchy_after_change' ] );

		}

		/**
		 * Broadcast that this object is loaded.
		 *
		 * @since 0.1
		 */
		do_action( 'bp_groups_civicrm_sync_bp_loaded' );

	}



	// -------------------------------------------------------------------------



	/**
	 * Test if BuddyPress plugin is active.
	 *
	 * @since 0.1
	 *
	 * @return bool|object False if BuddyPress could not be found, BuddyPress reference if successful.
	 */
	public function is_active() {

		// Bail if no BuddyPress init function.
		if ( ! function_exists( 'buddypress' ) ) {
			return false;
		}

		// Try and init BuddyPress.
		return buddypress();

	}



	/**
	 * Creates a CiviCRM Group when a BuddyPress Group is created.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param object $first_member WordPress User object.
	 * @param object $group The BuddyPress Group object.
	 */
	public function create_civi_group( $group_id, $first_member, $group ) {

		// Bail if sync should not happen.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Create the corresponding CiviCRM Groups.
		$civi_groups = $this->civi->create_civi_group( $group_id, $group );

		// Bail if something went wrong.
		if ( $civi_groups === false ) {
			return;
		}

		// Update our Group Meta with the IDs of the CiviCRM Groups.
		groups_update_groupmeta( $group_id, 'civicrm_groups', $civi_groups );

	}



	/**
	 * Updates a CiviCRM Group when a BuddyPress Group is updated.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 */
	public function update_civi_group_details( $group_id ) {

		// Bail if sync should not happen.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Get the Group object.
		$group = groups_get_group( [ 'group_id' => $group_id ] );

		// Pass to CiviCRM to update Groups.
		$civi_groups = $this->civi->update_civi_group( $group_id, $group );

	}



	/**
	 * Updates a CiviCRM Group when a BuddyPress Group is updated.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $group The BuddyPress Group object.
	 */
	public function update_civi_group( $group_id, $group ) {

		// Bail if sync should not happen.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Pass to CiviCRM to update Groups.
		$civi_groups = $this->civi->update_civi_group( $group_id, $group );

	}



	/**
	 * Deletes a CiviCRM Group when a BuddyPress Group is deleted.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 */
	public function delete_civi_group( $group_id ) {

		// Bail if sync should not happen.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Pass to CiviCRM to delete Groups.
		$civi_groups = $this->civi->delete_civi_group( $group_id );

		// We don't need to delete our meta, as BuddyPress will do so.

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
	public function groups_admin_load( $doaction ) {

		// Only add hooks if saving data.
		if ( $doaction && $doaction == 'save' ) {

			// Group Membership hooks: Group Membership status is being modified.
			add_action( 'groups_promote_member', [ $this, 'member_changing_status' ], 10, 3 );
			add_action( 'groups_demote_member', [ $this, 'member_changing_status' ], 10, 2 );
			add_action( 'groups_unban_member', [ $this, 'member_changing_status' ], 10, 2 );
			add_action( 'groups_ban_member', [ $this, 'member_changing_status' ], 10, 2 );

			// User is being removed from Group.
			add_action( 'groups_remove_member', [ $this, 'civi_delete_group_membership' ], 10, 2 );

		}

	}



	/**
	 * Get all BuddyPress Groups.
	 *
	 * @since 0.1
	 *
	 * @return array $groups Array of BuddyPress Group objects.
	 */
	public function get_all_groups() {

		// Init return as empty array.
		$groups = [];

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
	 * @return int $new_group_id The numeric ID of the new BuddyPress Group.
	 */
	public function create_group( $title, $description, $creator_id = null ) {

		// Set creator to current WordPress User if no CiviCRM Contact passed.
		if ( empty( $creator_id ) ) {
			$creator_id = bp_loggedin_user_id();
		}

		// Get current time.
		$time = current_time( 'mysql' );

		/*
		 * Possible parameters (see function groups_create_group):
		 *	'group_id'
		 *	'creator_id'
		 *	'name'
		 *	'description'
		 *	'slug'
		 *	'status'
		 *	'enable_forum'
		 *	'date_created'
		 */
		$args = [
			// Group_id is not passed so that we create a group.
			'creator_id' => $creator_id,
			'name' => $title,
			'description' => $description,
			'slug' => groups_check_slug( sanitize_title( esc_attr( $title ) ) ),
			'status' => 'public',
			'enable_forum' => 0,
			'date_created' => $time,
		];

		// Let BuddyPress do the work.
		$new_group_id = groups_create_group( $args );

		// Add some meta.
		groups_update_groupmeta( $new_group_id, 'total_member_count', 1 );
		groups_update_groupmeta( $new_group_id, 'last_activity', $time );
		groups_update_groupmeta( $new_group_id, 'invite_status', 'members' );

		/**
		 * Broadcast that a BuddyPress Group has been created.
		 *
		 * @since 0.1
		 *
		 * @param int $new_group_id The numeric ID of the new Group.
		 */
		do_action( 'bp_groups_civicrm_sync_group_created', $new_group_id );

		// --<
		return $new_group_id;

	}



	/**
	 * Get all Members of a BuddyPress Group.
	 *
	 * @since 0.2.2
	 *
	 * @param int $group_id The ID of the BuddyPress Group.
	 * @return array $members The Members of the Group.
	 */
	public function get_all_group_members( $group_id ) {

		// Init return as empty array.
		$members = [];

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
	public function create_group_members( $group_id, $contacts, $is_admin = 0 ) {

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
				$user = $this->wordpress_create_user( $contact );
			}

			// Sanity check.
			if ( $user ) {

				// Try and create Membership.
				if ( ! $this->create_group_member( $group_id, $user->ID, $is_admin ) ) {

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
	 * Creates a BuddyPress Group Membership given a title and description.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @param bool $is_admin Makes this Member a Group Admin.
	 * @return bool $success True if successful, false if not.
	 */
	public function create_group_member( $group_id, $user_id, $is_admin = 0 ) {

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
				$this->promote_group_member( $group_id, $user_id, 'admin' );

			} else {

				// Demote them if needed.
				$this->demote_group_member( $group_id, $user_id );

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
	 * Delete BuddyPress Group Members given an array of CiviCRM Contacts.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $contacts An array of CiviCRM Contact data.
	 */
	public function delete_group_members( $group_id, $contacts ) {

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
				if ( ! $this->delete_group_member( $group_id, $user->ID ) ) {

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
	public function delete_group_member( $group_id, $user_id ) {

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
	 * Demote BuddyPress Group Members given an array of CiviCRM Contacts.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $contacts An array of CiviCRM Contact data.
	 */
	public function demote_group_members( $group_id, $contacts ) {

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
				if ( ! $this->demote_group_member( $group_id, $user->ID ) ) {

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
	 * Demote a BuddyPress Group Member given a WordPress User.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @return bool $success True if successful, false if not.
	 */
	public function demote_group_member( $group_id, $user_id ) {

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
	 * Promote BuddyPress Group Members given an array of CiviCRM Contacts.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $contacts An array of CiviCRM Contact data.
	 * @param string $status The status to which the Members will be promoted.
	 */
	public function promote_group_members( $group_id, $contacts, $status ) {

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
				if ( ! $this->promote_group_member( $group_id, $user->ID, $status ) ) {

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
	public function promote_group_member( $group_id, $user_id, $status ) {

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
		$this->civi_update_group_membership( $user_id, $group_id );

	}



	/**
	 * Called when User's Group status is about to change.
	 *
	 * Parameter order ($group_id, $user_id) is the opposite of the "past tense"
	 * hooks. Compare, for example, 'groups_promoted_member'.
	 *
	 * @see $this->groups_admin_load()
	 *
	 * @since 0.2.2
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @param string $status New status being changed to.
	 */
	public function member_changing_status( $group_id, $user_id, $status = '' ) {

		// If we have no value for the status param, check if User is Group Admin.
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

		// Make status numeric for CiviCRM.
		$is_admin = $status == 'admin' ? 1 : 0;

		// Is this User being banned?
		$is_active = 1;
		if ( 'groups_ban_member' == current_action() ) {
			$is_active = 0;
		}

		// Update Membership of CiviCRM Group.
		$params = [
			'bp_group_id' => $group_id,
			'uf_id' => $user_id,
			'is_active' => $is_active,
			'is_admin' => $is_admin,
			'bp_status' => $status,
		];

		// First, remove the CiviCRM actions, otherwise we may recurse.
		remove_action( 'civicrm_pre', [ $this->civi, 'group_contacts_added' ], 10 );
		remove_action( 'civicrm_pre', [ $this->civi, 'group_contacts_rejoined' ], 10 );
		remove_action( 'civicrm_pre', [ $this->civi, 'group_contacts_deleted' ], 10 );

		// Use clone of CRM_Bridge_OG_Drupal::og().
		$this->civi->group_contact_sync( $params, 'add' );

		// Re-add the CiviCRM actions.
		add_action( 'civicrm_pre', [ $this->civi, 'group_contacts_added' ], 10, 4 );
		add_action( 'civicrm_pre', [ $this->civi, 'group_contacts_rejoined' ], 10, 4 );
		add_action( 'civicrm_pre', [ $this->civi, 'group_contacts_deleted' ], 10, 4 );

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

		// Set a flag for used in "past tense" callback.
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
		$this->civi_update_group_membership( $user_id, $group_id );

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
		$this->civi_delete_group_membership( $group_id, $user_id );

	}



	/**
	 * Inform CiviCRM of Membership status change.
	 *
	 * @since 0.1
	 *
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 */
	public function civi_update_group_membership( $user_id, $group_id ) {

		// Bail if sync should not happen for this Group.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Get current User status.
		$status = $this->get_user_group_status( $user_id, $group_id );

		// Check previous status.
		if ( isset( $this->old_status ) && $this->old_status === 'ex-admin' ) {
			$status = $this->old_status;
			unset( $this->old_status );
		}

		// Make numeric for CiviCRM.
		$is_admin = $status == 'admin' ? 1 : 0;

		// Assume active.
		$is_active = 1;

		// Is this User active?
		if ( groups_is_user_banned( $user_id, $group_id ) ) {
			$is_active = 0;
		}

		// Update Membership of CiviCRM Group.
		$params = [
			'bp_group_id' => $group_id,
			'uf_id' => $user_id,
			'is_active' => $is_active,
			'is_admin' => $is_admin,
			'bp_status' => $status,
		];

		// First, remove the CiviCRM actions, otherwise we may recurse.
		remove_action( 'civicrm_pre', [ $this->civi, 'group_contacts_added' ], 10 );
		remove_action( 'civicrm_pre', [ $this->civi, 'group_contacts_rejoined' ], 10 );
		remove_action( 'civicrm_pre', [ $this->civi, 'group_contacts_deleted' ], 10 );

		// Use clone of CRM_Bridge_OG_Drupal::og().
		$this->civi->group_contact_sync( $params, 'add' );

		// Re-add the CiviCRM actions.
		add_action( 'civicrm_pre', [ $this->civi, 'group_contacts_added' ], 10, 4 );
		add_action( 'civicrm_pre', [ $this->civi, 'group_contacts_rejoined' ], 10, 4 );
		add_action( 'civicrm_pre', [ $this->civi, 'group_contacts_deleted' ], 10, 4 );

	}



	/**
	 * Inform CiviCRM of Membership status change.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $user_id The numeric ID of the WordPress User.
	 */
	public function civi_delete_group_membership( $group_id, $user_id ) {

		// Bail if sync should not happen for this Group.
		if ( ! $this->group_should_be_synced( $group_id ) ) {
			return;
		}

		// Get current User status.
		$status = $this->get_user_group_status( $user_id, $group_id );

		// Make numeric for CiviCRM.
		$is_admin = $status == 'admin' ? 1 : 0;

		// Update Membership of CiviCRM Groups.
		$params = [
			'bp_group_id' => $group_id,
			'uf_id' => $user_id,
			'is_active' => 0,
			'is_admin' => $is_admin,
			'bp_status' => $status,
		];

		// First, remove the CiviCRM action, otherwise we'll recurse.
		remove_action( 'civicrm_pre', [ $this->civi, 'group_contacts_deleted' ], 10 );

		// Use clone of CRM_Bridge_OG_Drupal::og().
		$this->civi->group_contact_sync( $params, 'delete' );

		// Re-add the CiviCRM action.
		add_action( 'civicrm_pre', [ $this->civi, 'group_contacts_deleted' ], 10, 4 );

	}



	/**
	 * Registers when BuddyPress Group Hierarchy plugin is saving a Group.
	 *
	 * @since 0.1
	 *
	 * @param object $group The BuddyPress Group object.
	 */
	public function hierarchy_before_change( $group ) {

		// Init or die.
		if ( ! $this->civi->is_active() ) {
			return;
		}

		// Get parent ID.
		$parent_id = isset( $group->vars['parent_id'] ) ? $group->vars['parent_id'] : 0;

		// Pass to CiviCRM object.
		$this->civi->group_nesting_update( $group->id, $parent_id );

	}



	/**
	 * Registers when BuddyPress Group Hierarchy plugin has saved a Group.
	 *
	 * @since 0.1
	 *
	 * @param object $group The BuddyPress Group object.
	 */
	public function hierarchy_after_change( $group ) {

		// Nothing for now.

	}



	/**
	 * Get BuddyPress Group Membership status for a User.
	 *
	 * @since 0.1
	 *
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @return string $user_group_status The Membership status for a User.
	 */
	public function get_user_group_status( $user_id, $group_id ) {

		// Access BuddyPress.
		global $bp;

		// Init return.
		$user_group_status = false;

		// The following functionality is modified code from the BuddyPress Groupblog plugin.
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

			// Use BuddyPress functions.
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
	 * Creates a WordPress User given a CiviCRM Contact.
	 *
	 * If this is called because a Contact has been added with "New Contact" in
	 * CiviCRM and some Group has been chosen at the same time, then the call
	 * will come via CRM_Contact_BAO_Contact::create(). Unfortunately, this
	 * method adds the Contact to the Group _before_ the email has been stored
	 * so the $civi_contact data does not contain it. WordPress will still let
	 * a User be created but they will have no email address!
	 *
	 * In order to get around this, one of two things needs to happen: either
	 * 1. the Contact needs to be added to the Group _after_ the email has been
	 * stored (this requires raising a ticket on Jira and waiting to see what
	 * happens) or 2. we try and recognise this state of affairs and listen for
	 * the addition of the email address to the Contact.
	 *
	 * @since 0.1
	 *
	 * @param array $civi_contact The data for the CiviCRM Contact.
	 * @return mixed $user WordPress User object or false on failure.
	 */
	public function wordpress_create_user( $civi_contact ) {

		// Create username from display name.
		$user_name = sanitize_title( sanitize_user( $civi_contact['display_name'] ) );

		// Check if we have a User with that username.
		$user_id = username_exists( $user_name );

		// If not, check against email address.
		if ( ! $user_id && email_exists( $civi_contact['email'] ) == false ) {

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
			 * @param array $civi_contact The CiviCRM Contact data.
			 */
			do_action( 'bp_groups_civicrm_sync_before_insert_user', $civi_contact );

			// Create the User.
			$user_id = wp_insert_user( [
				'user_login' => $user_name,
				'user_pass' => $random_password,
				'user_email' => $civi_contact['email'],
				'first_name' => $civi_contact['first_name'],
				'last_name' => $civi_contact['last_name'],
			] );

			// Is the email address empty?
			if ( empty( $civi_contact['email'] ) ) {

				// Store this Contact temporarily.
				$this->temp_contact = [
					'civi' => $civi_contact,
					'user_id' => $user_id,
				];

				// Add callback for the next "Email create" event.
				add_action( 'civicrm_post', [ $this, 'civi_email_updated' ], 10, 4 );

			}

			// Re-add filters.
			$this->add_filters();

			/**
			 * Allow other plugins to be aware of what we've done.
			 *
			 * @since 0.1
			 *
			 * @param array $civi_contact The CiviCRM Contact data.
			 * @param int $user_id The numeric ID of the User.
			 */
			do_action( 'bp_groups_civicrm_sync_after_insert_user', $civi_contact, $user_id );

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
	 * @param string $objectName The type of object.
	 * @param integer $objectId The ID of the object.
	 * @param object $objectRef The object.
	 */
	public function civi_email_updated( $op, $objectName, $objectId, $objectRef ) {

		// Target our operation.
		if ( $op != 'create' ) {
			return;
		}

		// Target our object type.
		if ( $objectName != 'Email' ) {
			return;
		}

		// Remove callback even if subsequent checks fail.
		remove_action( 'civicrm_post', [ $this, 'civi_email_updated' ], 10, 4 );

		// Bail if we don't have a temp Contact.
		if ( ! isset( $this->temp_contact ) ) {
			return;
		}
		if ( ! is_array( $this->temp_contact ) ) {
			unset( $this->temp_contact );
			return;
		}

		// Bail if we have no email or Contact ID.
		if ( ! isset( $objectRef->email ) || ! isset( $objectRef->contact_id ) ) {
			unset( $this->temp_contact );
			return;
		}

		// Bail if this is not the same Contact as above.
		if ( $objectRef->contact_id != $this->temp_contact['civi']['contact_id'] ) {
			unset( $this->temp_contact );
			return;
		}

		// Get User ID.
		$user_id = $this->temp_contact['user_id'];

		// Make sure there's an entry in the ufMatch table.
		$transaction = new CRM_Core_Transaction();

		// Create the UF Match record.
		$ufmatch             = new CRM_Core_DAO_UFMatch();
		$ufmatch->domain_id  = CRM_Core_Config::domainID();
		$ufmatch->uf_id      = $user_id;
		$ufmatch->contact_id = $objectRef->contact_id;
		$ufmatch->uf_name    = $objectRef->email;

		if ( ! $ufmatch->find( true ) ) {
			$ufmatch->save();
			$ufmatch->free();
			$transaction->commit();
		}

		// Remove filters.
		$this->remove_filters();

		/**
		 * Allow other plugins to be aware of what we're doing.
		 *
		 * @since 0.1
		 *
		 * @param array $temp_contact The temporary CiviCRM Contact data.
		 * @param object $objectRef The CiviCRM Contact data object.
		 * @param int $user_id The numeric ID of the User.
		 */
		do_action( 'bp_groups_civicrm_sync_before_update_user', $this->temp_contact, $objectRef, $user_id );

		// Update the WordPress User with this email address.
		$user_id = wp_update_user( [
			'ID' => $user_id,
			'user_email' => $objectRef->email,
		] );

		// Re-add filters.
		$this->add_filters();

		/**
		 * Allow other plugins to be aware of what we've done.
		 *
		 * @since 0.1
		 *
		 * @param array $temp_contact The temporary CiviCRM Contact data.
		 * @param object $objectRef The CiviCRM Contact data object.
		 * @param int $user_id The numeric ID of the User.
		 */
		do_action( 'bp_groups_civicrm_sync_after_update_user', $this->temp_contact, $objectRef, $user_id );

	}



	/**
	 * Remove filters (that we know of) that will interfere with creating a WordPress User.
	 *
	 * @since 0.1
	 */
	private function remove_filters() {

		// Get CiviCRM instance.
		$civi = civi_wp();

		// Do we have the old-style plugin structure?
		if ( method_exists( $civi, 'update_user' ) ) {

			// Remove previous CiviCRM plugin filters.
			remove_action( 'user_register', [ civi_wp(), 'update_user' ] );
			remove_action( 'profile_update', [ civi_wp(), 'update_user' ] );

		} else {

			// Remove current CiviCRM plugin filters.
			remove_action( 'user_register', [ civi_wp()->users, 'update_user' ] );
			remove_action( 'profile_update', [ civi_wp()->users, 'update_user' ] );

		}

		// Remove CiviCRM WordPress Profile Sync filters.
		global $civicrm_wp_profile_sync;
		if ( is_object( $civicrm_wp_profile_sync ) ) {
			remove_action( 'user_register', [ $civicrm_wp_profile_sync, 'wordpress_contact_updated' ], 100 );
			remove_action( 'profile_update', [ $civicrm_wp_profile_sync, 'wordpress_contact_updated' ], 100 );
		}

	}



	/**
	 * Add filters (that we know of) after creating a WordPress User.
	 *
	 * @since 0.1
	 */
	private function add_filters() {

		// Get CiviCRM instance.
		$civi = civi_wp();

		// Do we have the old-style plugin structure?
		if ( method_exists( $civi, 'update_user' ) ) {

			// Re-add previous CiviCRM plugin filters.
			add_action( 'user_register', [ civi_wp(), 'update_user' ] );
			add_action( 'profile_update', [ civi_wp(), 'update_user' ] );

		} else {

			// Re-add current CiviCRM plugin filters.
			add_action( 'user_register', [ civi_wp()->users, 'update_user' ] );
			add_action( 'profile_update', [ civi_wp()->users, 'update_user' ] );

		}

		// Re-add CiviCRM WordPress Profile Sync filters.
		global $civicrm_wp_profile_sync;
		if ( is_object( $civicrm_wp_profile_sync ) ) {
			add_action( 'user_register', [ $civicrm_wp_profile_sync, 'wordpress_contact_updated' ], 100, 1 );
			add_action( 'profile_update', [ $civicrm_wp_profile_sync, 'wordpress_contact_updated' ], 100, 1 );
		}

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



} // Class ends.
