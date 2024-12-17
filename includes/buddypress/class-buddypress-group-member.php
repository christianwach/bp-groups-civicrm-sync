<?php
/**
 * BuddyPress Group Members class.
 *
 * Handles BuddyPress Group Member-related functionality.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * BuddyPress Group Members class.
 *
 * A class that handles BuddyPress Group Members functionality.
 *
 * @since 0.5.0
 */
class BP_Groups_CiviCRM_Sync_BuddyPress_Group_Member {

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
	 * Store for bridging data between pre and post callbacks.
	 *
	 * @since 0.5.3
	 * @access private
	 * @var array
	 */
	public $bridging_array = [];

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
		do_action( 'bpgcs/buddypress/groups/loaded' );

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.5.0
	 */
	public function register_hooks() {

		// Register Group Membership hooks.
		$this->register_hooks_group_members();

	}

	/**
	 * Unregister hooks.
	 *
	 * @since 0.5.0
	 */
	public function unregister_hooks() {

		// Unregister Group Membership hooks.
		$this->unregister_hooks_group_members();

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Register hooks for BuddyPress Group Membership modifications.
	 *
	 * @since 0.4
	 * @since 0.5.0 Moved to this class.
	 */
	public function register_hooks_group_members() {

		// Before and after a Group Membership is saved.
		add_action( 'groups_member_before_save', [ $this, 'member_save_pre' ], 9 );
		add_action( 'groups_member_after_save', [ $this, 'member_save_post' ], 9 );

		// Before and after a Group Membership is removed.
		add_action( 'groups_member_before_remove', [ $this, 'member_remove_pre' ], 9 );
		add_action( 'groups_member_after_remove', [ $this, 'member_remove_post' ], 9 );

		// Before and after a Group Membership is deleted.
		add_action( 'bp_groups_member_before_delete', [ $this, 'member_delete_pre' ], 9, 2 );
		add_action( 'bp_groups_member_after_delete', [ $this, 'member_delete_post' ], 9, 2 );

	}

	/**
	 * Unregister hooks for BuddyPress Group Membership modifications.
	 *
	 * @since 0.4
	 * @since 0.5.0 Moved to this class.
	 */
	public function unregister_hooks_group_members() {

		// Remove callbacks.
		remove_action( 'groups_member_before_save', [ $this, 'member_save_pre' ], 9 );
		remove_action( 'groups_member_after_save', [ $this, 'member_save_post' ], 9 );
		remove_action( 'groups_member_before_remove', [ $this, 'member_remove_pre' ], 9 );
		remove_action( 'groups_member_after_remove', [ $this, 'member_remove_post' ], 9 );
		remove_action( 'bp_groups_member_before_delete', [ $this, 'member_delete_pre' ], 9 );
		remove_action( 'bp_groups_member_after_delete', [ $this, 'member_delete_post' ], 9 );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Called before a Group Membership is saved.
	 *
	 * @since 0.5.3
	 *
	 * @param BP_Groups_Member $member The BP Groups Member instance.
	 */
	public function member_save_pre( $member ) {

		// Sanity check.
		if ( ! ( $member instanceof BP_Groups_Member ) ) {
			return;
		}

		// Get the previous Group Membership.
		$previous_member = new BP_Groups_Member( $member->user_id, $member->group_id );

		// Add Group Membership object to the bridging array.
		$this->bridging_array['save'][ $member->group_id ][ $member->user_id ] = $previous_member;

	}

	/**
	 * Called after a Group Membership is saved.
	 *
	 * @since 0.5.3
	 *
	 * @param BP_Groups_Member $member The BP Groups Member instance.
	 */
	public function member_save_post( BP_Groups_Member $member ) {

		// Bail if we do not have an entry in the bridging array.
		if ( empty( $this->bridging_array['save'][ $member->group_id ][ $member->user_id ] ) ) {
			return;
		}

		// Okay, let's get it.
		$previous = $this->bridging_array['save'][ $member->group_id ][ $member->user_id ];
		unset( $this->bridging_array['save'][ $member->group_id ][ $member->user_id ] );

		// Is this User being banned?
		$is_active = 1;
		if ( 0 === (int) $previous->is_banned && 1 === (int) $member->is_banned ) {
			$is_active = 0;
		}

		// Our "status" is simply to distinguish between admins and others.
		$status = ( 1 === (int) $member->is_admin ) ? 'admin' : '';

		// If a Group Admin is being demoted, set special status.
		if ( '' === $status && 1 === $previous->is_admin ) {
			$status = 'ex-admin';
		}

		// Update Membership of CiviCRM Group.
		$args = [
			'action'    => 'add',
			'group_id'  => $member->group_id,
			'user_id'   => $member->user_id,
			'status'    => $status,
			'is_active' => $is_active,
		];

		// Update the corresponding CiviCRM Group memberships.
		$this->plugin->civicrm->group_contact->memberships_sync( $args );

	}

	/**
	 * Called before a Group Membership is removed.
	 *
	 * @since 0.5.3
	 *
	 * @param BP_Groups_Member $member The BP Groups Member instance.
	 */
	public function member_remove_pre( BP_Groups_Member $member ) {

		// Sanity check.
		if ( ! ( $member instanceof BP_Groups_Member ) ) {
			return;
		}

		// Get the previous Group Membership.
		$previous_member = new BP_Groups_Member( $member->user_id, $member->group_id );

		// Add Group Membership object to the bridging array.
		$this->bridging_array['remove'][ $member->group_id ][ $member->user_id ] = $previous_member;

	}

	/**
	 * Called after a Group Membership is removed.
	 *
	 * @since 0.5.3
	 *
	 * @param BP_Groups_Member $member The BP Groups Member instance.
	 */
	public function member_remove_post( BP_Groups_Member $member ) {

		// Bail if we do not have an entry in the bridging array.
		if ( empty( $this->bridging_array['remove'][ $member->group_id ][ $member->user_id ] ) ) {
			return;
		}

		// Okay, let's get it.
		$previous = $this->bridging_array['remove'][ $member->group_id ][ $member->user_id ];
		unset( $this->bridging_array['remove'][ $member->group_id ][ $member->user_id ] );

		// Our "status" is simply to distinguish between admins and others.
		$status = ( 1 === (int) $member->is_admin ) ? 'admin' : '';

		// Update Membership of CiviCRM Group.
		$args = [
			'action'    => 'delete',
			'group_id'  => $member->group_id,
			'user_id'   => $member->user_id,
			'status'    => $status,
			'is_active' => 0,
		];

		// Update the corresponding CiviCRM Group memberships.
		$this->plugin->civicrm->group_contact->memberships_sync( $args );

	}

	/**
	 * Called before a Group Membership is deleted.
	 *
	 * @since 0.5.3
	 *
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 */
	public function member_delete_pre( $user_id, $group_id ) {

		// Get the previous Group Membership.
		$previous_member = new BP_Groups_Member( $user_id, $group_id );

		// Add Group Membership object to the bridging array.
		$this->bridging_array['delete'][ $group_id ][ $user_id ] = $previous_member;

	}

	/**
	 * Called after a Group Membership is deleted.
	 *
	 * @since 0.5.3
	 *
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 */
	public function member_delete_post( $user_id, $group_id ) {

		// Bail if we do not have an entry in the bridging array.
		if ( empty( $this->bridging_array['delete'][ $group_id ][ $user_id ] ) ) {
			return;
		}

		// Okay, let's get it.
		$previous = $this->bridging_array['delete'][ $group_id ][ $user_id ];
		unset( $this->bridging_array['delete'][ $group_id ][ $user_id ] );

		// Our "status" is simply to distinguish between admins and others.
		$status = ( 1 === (int) $previous->is_admin ) ? 'admin' : '';

		// Update Membership of CiviCRM Group.
		$args = [
			'action'    => 'delete',
			'group_id'  => $group_id,
			'user_id'   => $user_id,
			'status'    => $status,
			'is_active' => 0,
		];

		// Update the corresponding CiviCRM Group memberships.
		$this->plugin->civicrm->group_contact->memberships_sync( $args );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Inform CiviCRM of Membership status change.
	 *
	 * @since 0.1
	 * @since 0.5.0 Moved to this class.
	 *
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @return array|bool $result The result of the update, or false on failure.
	 */
	public function civicrm_group_membership_update( $user_id, $group_id ) {

		// Bail if sync should not happen for this Group.
		if ( ! $this->bp->group->should_be_synced( $group_id ) ) {
			return false;
		}

		// Get current Member status.
		$status = false;
		if ( groups_is_user_admin( $user_id, $group_id ) ) {
			$status = 'admin';
		} elseif ( groups_is_user_mod( $user_id, $group_id ) ) {
			$status = 'mod';
		} elseif ( groups_is_user_member( $user_id, $group_id ) ) {
			$status = 'member';
		}

		// Is this Member active?
		$is_active = 1;
		if ( groups_is_user_banned( $user_id, $group_id ) ) {
			$is_active = 0;
		}

		// Update Membership of CiviCRM Groups.
		$args = [
			'action'    => 'add',
			'group_id'  => $group_id,
			'user_id'   => $user_id,
			'status'    => $status,
			'is_active' => $is_active,
		];

		// Update the corresponding CiviCRM Group memberships.
		$result = $this->plugin->civicrm->group_contact->memberships_sync( $args );

		// --<
		return $result;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get all Members of a BuddyPress Group.
	 *
	 * @since 0.2.2
	 * @since 0.5.0 Moved to this class.
	 *
	 * @param int $group_id The ID of the BuddyPress Group.
	 * @return array $members The Members of the Group.
	 */
	public function members_get_all( $group_id ) {

		// Init return as empty array.
		$members = [];

		// Check BuddyPress config.
		if ( ! $this->bp->is_configured() ) {
			return $members;
		}

		// Params to get all Group Members.
		$params = [
			'group_id'            => $group_id,
			'exclude_admins_mods' => false,
			'per_page'            => 0,
			'group_role'          => [ 'member', 'mod', 'admin', 'banned' ],
		];

		// Query Group Members.
		$members_query = groups_get_group_members( $params );

		// We want the "members" sub-array.
		if ( ! empty( $members_query['members'] ) ) {
			$members = $members_query['members'];
		}

		// --<
		return $members;

	}

	/**
	 * Create BuddyPress Group Members given an array of CiviCRM Contacts.
	 *
	 * @since 0.1
	 * @since 0.5.0 Moved to this class.
	 *
	 * @param int  $group_id The numeric ID of the BuddyPress Group.
	 * @param int  $contacts An array of CiviCRM Contact data.
	 * @param bool $is_admin Makes this Member a Group Admin.
	 */
	public function members_create( $group_id, $contacts, $is_admin = 0 ) {

		// Check BuddyPress config.
		if ( ! $this->bp->is_configured() ) {
			return;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->bp->group->should_be_synced( $group_id ) ) {
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
				$user = $this->bp->user->create( $contact );
			}

			// Sanity check.
			if ( $user ) {

				// Try and create Membership.
				if ( ! $this->create( $group_id, $user->ID, $is_admin ) ) {

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
	 * @since 0.5.0 Moved to this class.
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $contacts An array of CiviCRM Contact data.
	 */
	public function members_delete( $group_id, $contacts ) {

		// Check BuddyPress config.
		if ( ! $this->bp->is_configured() ) {
			return;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->bp->group->should_be_synced( $group_id ) ) {
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
				if ( ! $this->delete( $group_id, $user->ID ) ) {

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
	 * @since 0.5.0 Moved to this class.
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $contacts An array of CiviCRM Contact data.
	 */
	public function members_demote( $group_id, $contacts ) {

		// Check BuddyPress config.
		if ( ! $this->bp->is_configured() ) {
			return;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->bp->group->should_be_synced( $group_id ) ) {
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
				if ( ! $this->demote( $group_id, $user->ID ) ) {

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
	 * @since 0.5.0 Moved to this class.
	 *
	 * @param int    $group_id The numeric ID of the BuddyPress Group.
	 * @param int    $contacts An array of CiviCRM Contact data.
	 * @param string $status The status to which the Members will be promoted.
	 */
	public function members_promote( $group_id, $contacts, $status ) {

		// Check BuddyPress config.
		if ( ! $this->bp->is_configured() ) {
			return;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->bp->group->should_be_synced( $group_id ) ) {
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
				if ( ! $this->promote( $group_id, $user->ID, $status ) ) {

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

	// -----------------------------------------------------------------------------------

	/**
	 * Creates a BuddyPress Group Membership given a title and description.
	 *
	 * @since 0.1
	 * @since 0.5.0 Moved to this class.
	 *
	 * @param int  $group_id The numeric ID of the BuddyPress Group.
	 * @param int  $user_id The numeric ID of the WordPress User.
	 * @param bool $is_admin Makes this Member a Group Admin.
	 * @return bool $success True if successful, false if not.
	 */
	public function create( $group_id, $user_id, $is_admin = 0 ) {

		// Check BuddyPress config.
		if ( ! $this->bp->is_configured() ) {
			return false;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->bp->group->should_be_synced( $group_id ) ) {
			return false;
		}

		// Check existing Membership.
		$is_member = groups_is_user_member( $user_id, $group_id );

		// If User is already a Member.
		if ( $is_member ) {

			// If they are being promoted.
			if ( 1 === (int) $is_admin ) {

				// Promote them to Admin.
				$success = $this->promote( $group_id, $user_id, 'admin' );

			} else {

				// Demote them if needed.
				$success = $this->demote( $group_id, $user_id );

			}

			// Either way, skip creation.
			return $success;

		}

		// Unhook our action to prevent BuddyPress->CiviCRM sync.
		$this->unregister_hooks_group_members();

		// Use BuddyPress function.
		$success = groups_join_group( $group_id, $user_id );

		// Re-hook our action to enable BuddyPress->CiviCRM sync.
		$this->register_hooks_group_members();

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
	 * @since 0.5.0 Moved to this class.
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @return bool $success True if successful, false if not.
	 */
	public function delete( $group_id, $user_id ) {

		// Check BuddyPress config.
		if ( ! $this->bp->is_configured() ) {
			return false;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->bp->group->should_be_synced( $group_id ) ) {
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

		// Unhook our action to prevent BuddyPress->CiviCRM sync.
		$this->unregister_hooks_group_members();

		// Remove Member.
		$success = $member->remove();

		// Re-hook our action to enable BuddyPress->CiviCRM sync.
		$this->register_hooks_group_members();

		// --<
		return $success;

	}

	/**
	 * Demotes a BuddyPress Group Member given a WordPress User.
	 *
	 * @since 0.1
	 * @since 0.5.0 Moved to this class.
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @return bool $success True if successfully demoted, false if not.
	 */
	public function demote( $group_id, $user_id ) {

		// Check BuddyPress config.
		if ( ! $this->bp->is_configured() ) {
			return false;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->bp->group->should_be_synced( $group_id ) ) {
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

		// Unhook our action to prevent BuddyPress->CiviCRM sync.
		$this->unregister_hooks_group_members();

		// Demote them.
		$success = $member->demote();

		// Re-hook our action to enable BuddyPress->CiviCRM sync.
		$this->register_hooks_group_members();

		// --<
		return $success;

	}

	/**
	 * Promotes a BuddyPress Group Member given a WordPress User and a status.
	 *
	 * @since 0.1
	 * @since 0.5.0 Moved to this class.
	 *
	 * @param int    $group_id The numeric ID of the BuddyPress Group.
	 * @param int    $user_id The numeric ID of the WordPress User.
	 * @param string $status The status to which the Member will be promoted.
	 * @return bool $success True if successfully promoted, false if not.
	 */
	public function promote( $group_id, $user_id, $status ) {

		// Check BuddyPress config.
		if ( ! $this->bp->is_configured() ) {
			return false;
		}

		// Bail if sync should not happen for this Group.
		if ( ! $this->bp->group->should_be_synced( $group_id ) ) {
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

		// Unhook our action to prevent BuddyPress->CiviCRM sync.
		$this->unregister_hooks_group_members();

		// Promote them.
		$success = $member->promote( $status );

		// Re-hook our action to enable BuddyPress->CiviCRM sync.
		$this->register_hooks_group_members();

		// --<
		return $success;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets the User and Group IDs with a given limit and offset.
	 *
	 * @since 0.5.0
	 *
	 * @param integer $limit The numeric limit for the query.
	 * @param integer $offset The numeric offset for the query.
	 * @return array $group_user_ids The array of WordPress User IDs and BuddyPress Group IDs.
	 */
	public function group_users_get( $limit = 0, $offset = 0 ) {

		global $wpdb;

		// Get all BuddyPress Groups IDs.
		$group_ids = $this->bp->group->ids_get_all();
		if ( empty( $group_ids ) ) {
			return [];
		}

		$bp = buddypress();

		// Build WHERE clause.
		$where_clause = 'WHERE group_id IN (' . implode( ', ', $group_ids ) . ')';

		// If there is no limit, there's no need for an offset either.
		$user_group_table = $bp->groups->table_name_members;
		if ( 0 === $limit ) {

			// Perform the query.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$group_user_ids = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT user_id, group_id FROM $user_group_table $where_clause ORDER BY group_id",
				ARRAY_A
			);

		} else {

			// Perform the query.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$group_user_ids = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT user_id, group_id FROM $user_group_table $where_clause ORDER BY group_id LIMIT %d OFFSET %d",
					$limit,
					$offset
				),
				ARRAY_A
			);

		}

		// --<
		return $group_user_ids;

	}

	/**
	 * Gets the User IDs in a given BuddyPress Group.
	 *
	 * @since 0.5.0
	 *
	 * @param integer $group_id The numeric ID of the BuddyPress Group.
	 * @return array $user_ids The array of WordPress User IDs.
	 */
	public function group_user_ids_get( $group_id ) {

		$user_ids = [];

		// Get all Users in the BuddyPress Group.
		$group_users = $this->members_get_all( $group_id );
		if ( empty( $group_users ) ) {
			return $user_ids;
		}

		// Get the just the User IDs.
		$group_user_ids = wp_list_pluck( $group_users, 'ID' );

		// --<
		return $group_user_ids;

	}

	/**
	 * Gets the User IDs for a given set of Contact IDs.
	 *
	 * @since 0.5.0
	 *
	 * @param array $contact_ids The array of CiviCRM Contact IDs.
	 * @return array $data The array of User IDs keyed by Contact ID.
	 */
	public function group_user_ids_for_contact_ids_get( $contact_ids ) {

		$data = [];

		foreach ( $contact_ids as $contact_id ) {

			// Skip if there is no User ID.
			$user_id = $this->bp->user->id_get_by_contact_id( $contact_id );
			if ( empty( $user_id ) ) {
				$data[ $contact_id ] = 0;
				continue;
			}

			$data[ $contact_id ] = $user_id;

		}

		return $data;

	}

}
