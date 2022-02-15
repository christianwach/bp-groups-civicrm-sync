<?php
/**
 * CiviCRM Group Contact Class.
 *
 * Handles functionality related to the CiviCRM Group Contacts.
 *
 * @package BP_Groups_CiviCRM_Sync
 * @since 0.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * BP Groups CiviCRM Sync CiviCRM Group Contact Class.
 *
 * A class that encapsulates functionality related to the CiviCRM Group Contacts.
 *
 * @since 0.4
 */
class BP_Groups_CiviCRM_Sync_CiviCRM_Group_Contact {

	/**
	 * Plugin object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * CiviCRM object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $civicrm The CiviCRM object.
	 */
	public $civicrm;

	/**
	 * BuddyPress object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $bp The BuddyPress object.
	 */
	public $bp;

	/**
	 * Admin object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $admin The Admin object.
	 */
	public $admin;



	/**
	 * Constructor.
	 *
	 * @since 0.4
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference to objects.
		$this->plugin = $parent->plugin;
		$this->civicrm = $parent;
		$this->bp = $parent->bp;
		$this->admin = $parent->admin;

		// Boot when CiviCRM object is loaded.
		add_action( 'bpgcs/civicrm/loaded', [ $this, 'initialise' ] );

	}



	/**
	 * Initialises this class.
	 *
	 * @since 0.4
	 */
	public function initialise() {

		// Bootstrap this class.
		$this->register_hooks();

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 0.4
		 */
		do_action( 'bpgcs/civicrm/group/contact/loaded' );

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.4
	 */
	public function register_hooks() {

		// Intercept CiviCRM's add Contacts to Group.
		add_action( 'civicrm_pre', [ $this, 'memberships_added' ], 10, 4 );

		// Intercept CiviCRM's delete Contacts from Group.
		add_action( 'civicrm_pre', [ $this, 'memberships_deleted' ], 10, 4 );

		// Intercept CiviCRM's rejoin Contacts to Group.
		add_action( 'civicrm_pre', [ $this, 'memberships_rejoined' ], 10, 4 );

	}



	/**
	 * Unregister hooks.
	 *
	 * @since 0.4
	 */
	public function unregister_hooks() {

		// Remove all callbacks.
		remove_action( 'civicrm_pre', [ $this, 'memberships_added' ], 10 );
		remove_action( 'civicrm_pre', [ $this, 'memberships_deleted' ], 10 );
		remove_action( 'civicrm_pre', [ $this, 'memberships_rejoined' ], 10 );

	}



	// -------------------------------------------------------------------------



	/**
	 * Update the CiviCRM Group memberships for a BuddyPress Group Member.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class and renamed.
	 * @since 0.4 Returns keyed array of results.
	 *
	 * @param array $args The array of arguments.
	 * @return array|bool $result The array of result data, or false on failure.
	 */
	public function memberships_sync( $args ) {

		// Init return.
		$result = [];

		// Sanity check BuddyPress membership status.
		if ( empty( $args['status'] ) ) {
			$args['status'] = '';
		}

		// Get the CiviCRM Contact ID.
		$contact_id = $this->civicrm->contact->id_get_by_user_id( $args['user_id'] );
		if ( empty( $contact_id ) ) {
			return false;
		}

		// Add Contact ID to return array.
		$result['contact_id'] = $contact_id;

		// Get the Synced Group IDs from the BuddyPress Group meta.
		$sync_groups = $this->bp->civicrm_groups_get( $args['group_id'] );

		/*
		 * First handle membership of the CiviCRM Member Group.
		 *
		 * This is the simpler of the two memberships to assign: the BuddyPress
		 * Member is either added to the Group or removed from the Group in
		 * accordance with the sync "action".
		 */

		// Get the CiviCRM Member Group ID of this BuddyPress Group.
		if ( ! empty( $sync_groups ) ) {
			$member_group_id = $sync_groups['member_group_id'];
		} else {
			$sync_name = $this->civicrm->member_group_get_sync_name( $args['group_id'] );
			$member_group_id = $this->civicrm->group_id_find( $sync_name );
		}

		// Params to update the Group Contact for the CiviCRM Member Group.
		$member_group_params = [
			'contact_id' => $contact_id,
			'group_id' => $member_group_id,
		];

		// Remove callbacks to prevent recursion.
		$this->unregister_hooks();

		// Act based on operation type.
		if ( $args['action'] == 'add' ) {

			// Add the Contact to the Group.
			$member_group_params['status'] = $args['is_active'] ? 'Added' : 'Pending';
			$member_group_contact = $this->membership_create( $member_group_params );

		} else {

			// Remove this Group membership.
			$member_group_contact = $this->membership_delete( $member_group_params );

		}

		// Add Member Group Contact to return array.
		$result['member_group_contact'] = $member_group_contact;

		// Restore callbacks.
		$this->register_hooks();

		// Skip ACL Group unless we have a Group Admin or a Group Admin is being demoted.
		if ( ! in_array( $args['status'], [ 'admin', 'ex-admin' ] ) ) {
			return $result;
		}

		/*
		 * Next handle membership of the CiviCRM ACL Group.
		 *
		 * The BuddyPress Member is added to the ACL Group when they have 'admin'
		 * status in the BuddyPress Group or removed from the ACL Group if their
		 * BuddyPress Group membership has been deleted or they are demoted from
		 * 'admin' status.
		 */

		// Get the ID of the CiviCRM ACL Group.
		if ( ! empty( $sync_groups ) ) {
			$acl_group_id = $sync_groups['acl_group_id'];
		} else {
			$sync_name = $this->civicrm->acl_group_get_sync_name( $args['group_id'] );
			$acl_group_id = $this->civicrm->group_id_find( $sync_name );
		}

		// Define params.
		$acl_group_params = [
			'contact_id' => $contact_id,
			'group_id' => $acl_group_id,
		];

		// Remove callbacks to prevent recursion.
		$this->unregister_hooks();

		// Act based on action and BuddyPress Group status.
		if ( $args['action'] === 'add' && $args['status'] !== 'ex-admin' ) {

			// Add to ACL Group.
			$acl_group_params['status'] = $args['is_active'] ? 'Added' : 'Pending';
			$acl_group_contact = $this->membership_create( $acl_group_params );

		} else {

			// Remove this Group membership.
			$acl_group_contact = $this->membership_delete( $acl_group_params );

		}

		// Add ACL Group Contact to return array.
		$result['acl_group_contact'] = $acl_group_contact;

		// Restore callbacks.
		$this->register_hooks();

		// --<
		return $result;

	}



	// -------------------------------------------------------------------------



	/**
	 * Update a BuddyPress Group when a CiviCRM Contact is added to a Group.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class and renamed.
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $group_id The ID of the CiviCRM Group.
	 * @param array $contact_ids The array of CiviCRM Contact IDs.
	 */
	public function memberships_added( $op, $object_name, $group_id, $contact_ids ) {

		// Target our operation.
		if ( $op !== 'create' ) {
			return;
		}

		// Target our object type.
		if ( $object_name !== 'GroupContact' ) {
			return;
		}

		// Bail if there are no Contact IDs.
		if ( empty( $contact_ids ) ) {
			return;
		}

		// Get CiviCRM Group data.
		$group = $this->civicrm->group_get_by_id( $group_id );
		if ( $group === false ) {
			return;
		}

		// Get BuddyPress Group ID.
		$bp_group_id = $this->bp->group_id_get_by_civicrm_group( $group );
		if ( $bp_group_id === false ) {
			return;
		}

		// Get full data for the Contacts.
		$contacts = $this->civicrm->contact->contacts_get_by_ids( $contact_ids );

		// Assume Member Group.
		$is_admin = 0;

		// Add as BuddyPress Group Admin if this is this an ACL Group.
		if ( $this->civicrm->is_acl_group( $group ) ) {
			$is_admin = 1;
		}

		// Add Contacts to BuddyPress Group.
		$this->bp->group_members_create( $bp_group_id, $contacts, $is_admin );

		/*
		 * If it was a CiviCRM ACL Group they were added to, we also need to add
		 * them to the CiviCRM Member Group.
		 */

		// Bail if this is a CiviCRM Member Group.
		if ( $is_admin == 0 ) {
			return;
		}

		// Get the Synced Group IDs from the BuddyPress Group meta.
		$sync_groups = $this->bp->civicrm_groups_get( $bp_group_id );

		// Get the CiviCRM Member Group ID for this BuddyPress Group.
		if ( ! empty( $sync_groups ) ) {
			$member_group_id = $sync_groups['member_group_id'];
		} else {
			$sync_name = $this->civicrm->member_group_get_sync_name( $bp_group_id );
			$member_group_id = $this->civicrm->group_id_find( $sync_name );
		}

		// Sanity check.
		if ( empty( $member_group_id ) ) {
			return;
		}

		// Remove callbacks to prevent recursion.
		$this->unregister_hooks();

		// Add Contacts to CiviCRM Member Group.
		foreach ( $contacts as $contact ) {
			$group_contact = [
				'group_id' => $member_group_id,
				'contact_id' => $contact['contact_id'],
				'status' => 'Added',
			];
			$this->membership_create( $group_contact );
		}

		// Restore callbacks.
		$this->register_hooks();

		// Promote Members to Group Admins.
		$this->bp->group_members_promote( $bp_group_id, $contacts, 'admin' );

	}



	/**
	 * Update a BuddyPress Group when a CiviCRM Contact is deleted (or removed) from a Group.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class and renamed.
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $group_id The ID of the CiviCRM Group.
	 * @param array $contact_ids Array of CiviCRM Contact IDs.
	 */
	public function memberships_deleted( $op, $object_name, $group_id, $contact_ids ) {

		// Target our operation.
		if ( $op !== 'delete' ) {
			return;
		}

		// Target our object type.
		if ( $object_name !== 'GroupContact' ) {
			return;
		}

		// Bail if there are no Contact IDs.
		if ( empty( $contact_ids ) ) {
			return;
		}

		// Get CiviCRM Group data.
		$group = $this->civicrm->group_get_by_id( $group_id );
		if ( $group === false ) {
			return;
		}

		// Get BuddyPress Group ID.
		$bp_group_id = $this->bp->group_id_get_by_civicrm_group( $group );
		if ( $bp_group_id === false ) {
			return;
		}

		// Get full data for the Contacts.
		$contacts = $this->civicrm->contact->contacts_get_by_ids( $contact_ids );

		// Demote and return early if this a CiviCRM ACL Group.
		if ( $this->civicrm->is_acl_group( $group ) ) {
			$this->bp->group_members_demote( $bp_group_id, $contacts );
			return;
		}

		// Delete from BuddyPress Group.
		$this->bp->group_members_delete( $bp_group_id, $contacts );

		// Get the Synced Group IDs from the BuddyPress Group meta.
		$sync_groups = $this->bp->civicrm_groups_get( $bp_group_id );

		// Get the CiviCRM Member Group ID for this BuddyPress Group.
		if ( ! empty( $sync_groups ) ) {
			$acl_group_id = $sync_groups['acl_group_id'];
		} else {
			$sync_name = $this->civicrm->acl_group_get_sync_name( $bp_group_id );
			$acl_group_id = $this->civicrm->group_id_find( $sync_name );
		}

		// Sanity check.
		if ( empty( $acl_group_id ) ) {
			return;
		}

		// Remove callbacks to prevent recursion.
		$this->unregister_hooks();

		// Remove Members from CiviCRM ACL Group.
		foreach ( $contacts as $contact ) {
			$group_params = [
				'group_id' => $acl_group_id,
				'contact_id' => $contact['contact_id'],
			];
			$group_contact = $this->membership_delete( $group_params );
		}

		// Restore callbacks.
		$this->register_hooks();

	}



	/**
	 * Update a BuddyPress Group when a CiviCRM Contact is re-added to a Group.
	 *
	 * The issue here is that CiviCRM fires 'civicrm_pre' with $op = 'delete'
	 * regardless of whether the Contact is being removed or deleted. If a
	 * Contact is later re-added to the Group, then $op != 'create', so we need
	 * to intercept $op = 'edit'.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class and renamed.
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $group_id The ID of the CiviCRM Group.
	 * @param array $contact_ids Array of CiviCRM Contact IDs.
	 */
	public function memberships_rejoined( $op, $object_name, $group_id, $contact_ids ) {

		// Target our operation.
		if ( $op !== 'edit' ) {
			return;
		}

		// Target our object type.
		if ( $object_name !== 'GroupContact' ) {
			return;
		}

		// Bail if there are no Contact IDs.
		if ( empty( $contact_ids ) ) {
			return;
		}

		// Set op to 'create'.
		$op = 'create';

		// Use our Group Contact addition callback.
		$this->memberships_added( $op, $object_name, $group_id, $contact_ids );

	}



	// -------------------------------------------------------------------------



	/**
	 * Checks if a Contact is a member of a CiviCRM Group.
	 *
	 * There seem to be issues with APIv3 here because "Removed" and "Pending"
	 * memberships do not appear in any result set. Also the "Status" of the
	 * membership is only returned when querying by Group ID alone.
	 *
	 * We therefore have to query the API for *all three statuses* to be certain
	 * of the Group membership for a Contact.
	 *
	 * @since 0.4
	 *
	 * @param integer $group_id The numeric ID of the CiviCRM Group.
	 * @param integer $contact_id The numeric ID of the CiviCRM Contact.
	 * @return array|bool $group_contact The Group Contact data, false otherwise.
	 */
	public function membership_exists( $group_id, $contact_id ) {

		// Init return.
		$group_contact = false;

		// Define statuses.
		$statuses = [ 'Added', 'Pending', 'Removed' ];

		// We have to query the API for all three statuses.
		foreach ( $statuses as $status ) {

			// Get the existing Group Contact data.
			$existing = $this->membership_get_by_status( $group_id, $contact_id, $status );

			// Skip the rest if we find it.
			if ( ! empty( $existing ) ) {
				$group_contact = $existing;
				$group_contact['status'] = $status;
				break;
			}

		}

		// --<
		return $group_contact;

	}



	/**
	 * Checks if a Contact is a member of a CiviCRM Group with a given status.
	 *
	 * @since 0.4
	 *
	 * @param integer $group_id The numeric ID of the CiviCRM Group.
	 * @param integer $contact_id The numeric ID of the CiviCRM Contact.
	 * @param string $status The status of the CiviCRM Group Contact.
	 * @return array|bool $group_contact The Group Contact data, false otherwise.
	 */
	public function membership_get_by_status( $group_id, $contact_id, $status ) {

		// Init return.
		$group_contact = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $group_contact;
		}

		// Init API params.
		$params = [
			'version' => 3,
			'contact_id' => $contact_id,
			'group_id' => $group_id,
			'status' => $status,
		];

		// Call CiviCRM API.
		$result = civicrm_api( 'GroupContact', 'get', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['error'] ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'params' => $params,
					'result' => $result,
					'backtrace' => $trace,
				], true ) );
			}
			return $group_contact;
		}

		// Return false if none found.
		if ( empty( $result['values'] ) ) {
			return $group_contact;
		}

		// There should only be one entry.
		$group_contact = array_pop( $result['values'] );

		// --<
		return $group_contact;

	}



	/**
	 * Adds a CiviCRM Contact to a CiviCRM Group.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class and renamed.
	 *
	 * @param array $group_contact The array of CiviCRM Group Contact data.
	 * @return array|bool $result The array of CiviCRM API data, or false on failure.
	 */
	public function membership_create( $group_contact ) {

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return false;
		}

		// Build params to create Group Contact.
		$params = [
			'version' => 3,
		] + $group_contact;

		// Call CiviCRM API.
		$result = civicrm_api( 'GroupContact', 'create', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['error'] ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'params' => $params,
					'result' => $result,
					'backtrace' => $trace,
				], true ) );
			}
			return false;
		}

		// The API will not add a Group Contact if it already exists.
		return $result;

	}



	/**
	 * Updates a CiviCRM Contact's membership of a CiviCRM Group.
	 *
	 * This is an alias of `self::membership_create()`
	 *
	 * @since 0.4
	 *
	 * @param array $group_contact The array of CiviCRM Group Contact data.
	 * @return array|bool $result The array of CiviCRM API data, or false on failure.
	 */
	public function membership_update( $group_contact ) {
		return $this->membership_create( $group_contact );
	}



	/**
	 * Removes a CiviCRM Contact from a CiviCRM Group.
	 *
	 * This doesn't actually delete the Group Contact - it sets the status to
	 * "Removed" to retain a record of past memberships.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class and renamed.
	 *
	 * @param array $group_contact The array of CiviCRM Group Contact data.
	 * @return array|bool $result The array of CiviCRM API data, true if no membership exists, or false on failure.
	 */
	public function membership_delete( $group_contact ) {

		// Skip if there is no existing Membership at all.
		$existing = $this->membership_exists( $group_contact['group_id'], $group_contact['contact_id'] );
		if ( $existing === false ) {
			return true;
		}

		// Skip if there is already a "Removed" Membership.
		if ( $existing['status'] === 'Removed' ) {
			return $existing;
		}

		// Build params to "remove" a Group Contact.
		$params = [
			'status' => 'Removed',
		] + $group_contact;

		// We're only updating the status of the Group Contact.
		return $this->membership_create( $params );

	}



} // Class ends.
