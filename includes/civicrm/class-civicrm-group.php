<?php
/**
 * CiviCRM Group Class.
 *
 * Handles functionality related to CiviCRM Groups.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Group Class.
 *
 * A class that encapsulates functionality related to CiviCRM Groups.
 *
 * @since 0.5.0
 */
class BP_Groups_CiviCRM_Sync_CiviCRM_Group {

	/**
	 * Plugin object.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync
	 */
	public $plugin;

	/**
	 * CiviCRM object.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_CiviCRM
	 */
	public $civicrm;

	/**
	 * Member Group identifier stored in Source.
	 *
	 * Since CiviCRM does not have Group Meta, we use the "Source" field.
	 * The actual data will be of the form: "BP Sync Group :2:" where 2
	 * is the BuddyPress Group ID.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var string
	 */
	public $source_member = 'BP Sync Group';

	/**
	 * ACL Group identifier stored in Source.
	 *
	 * Since CiviCRM does not have Group Meta, we use the "Source" field.
	 * The actual data will look like: "BP Sync Group ACL :2:" where 2 is
	 * the BuddyPress Group ID.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var string
	 */
	public $source_acl = 'BP Sync Group ACL';

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference to objects.
		$this->plugin  = $parent->plugin;
		$this->civicrm = $parent;

		// Boot when CiviCRM object is loaded.
		add_action( 'bpgcs/civicrm/loaded', [ $this, 'initialise' ] );

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
		do_action( 'bpgcs/civicrm/group/loaded' );

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.5.0
	 */
	public function register_hooks() {

	}

	/**
	 * Unregister hooks.
	 *
	 * @since 0.5.0
	 */
	public function unregister_hooks() {

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Creates the synced CiviCRM Groups for a given BuddyPress Group.
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 * @since 0.5.0 Moved here.
	 *
	 * @param object $bp_group The BuddyPress Group object.
	 * @return array|bool $group_ids Associative array of CiviCRM Group IDs, or false on failure.
	 */
	public function sync_groups_create( $bp_group ) {

		// Escalate permissions.
		$this->permissions_escalate();

		// First prepare the CiviCRM Member Group.
		$member_group_params = $this->member_group_prepare( $bp_group );

		// Create the CiviCRM Member Group.
		$member_group = $this->create( $member_group_params );
		if ( false === $member_group ) {
			$this->permissions_escalate_stop();
			return false;
		}

		// Next prepare the CiviCRM ACL Group.
		$acl_group_params = $this->acl_group_prepare( $bp_group );

		// Create the CiviCRM ACL Group.
		$acl_group = $this->create( $acl_group_params );

		// Bail on failure.
		if ( false === $acl_group ) {

			// Clean up by deleting the Member Group.
			$this->delete( $member_group['id'] );

			// Remove permissions.
			$this->permissions_escalate_stop();

			// --<
			return false;

		}

		// Populate return array.
		$group_ids = [
			'member_group_id' => (int) $member_group['id'],
			'acl_group_id'    => (int) $acl_group['id'],
		];

		// Create ACL for the CiviCRM Groups.
		$success = $this->civicrm->acl->update_for_groups( $acl_group, $member_group );
		if ( false === $success ) {
			$this->permissions_escalate_stop();
			return false;
		}

		// Maybe assing to Meta Group.
		$this->civicrm->group_nesting->nesting_update( $bp_group->id );

		// Add the creator to the Groups.
		$args = [
			'action'    => 'add',
			'group_id'  => $bp_group->id,
			'user_id'   => $bp_group->creator_id,
			'status'    => 'admin',
			'is_active' => 1,
		];

		// Update the corresponding CiviCRM Group memberships.
		$result = $this->civicrm->group_contact->memberships_sync( $args );

		// If there's a Contact ID we can use.
		if ( ! empty( $result['contact_id'] ) ) {

			// Update Member Group to preserve Group Creator.
			$member_group_params['id']         = $member_group['id'];
			$member_group_params['created_id'] = $result['contact_id'];

			// Update the CiviCRM Member Group.
			$member_group = $this->update( $member_group_params );

			// Update ACL Group to preserve Group Creator.
			$acl_group_params['id']         = $acl_group['id'];
			$acl_group_params['created_id'] = $result['contact_id'];

			// Update the CiviCRM ACL Group.
			$acl_group = $this->update( $acl_group_params );

		}

		// Remove permissions.
		$this->permissions_escalate_stop();

		// --<
		return $group_ids;

	}

	/**
	 * Updates the Synced CiviCRM Groups when a BuddyPress Group is updated.
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 *
	 * @param object $bp_group The BuddyPress Group object.
	 * @return bool|array $group_ids True when successfully updated or false on failure.
	 *                               A keyed array of Group IDs when Groups are created.
	 */
	public function sync_groups_update( $bp_group ) {

		// Escalate permissions.
		$this->permissions_escalate();

		// Get the Synced Group IDs from the BuddyPress Group meta.
		$sync_groups = $this->groups_for_bp_group_id_get( $bp_group->id );

		// If there are none, we need to create them instead.
		if ( empty( $sync_groups ) ) {
			$group_ids = $this->sync_groups_create( $bp_group );
			return $group_ids;
		}

		// First prepare the CiviCRM Member Group.
		$member_group_params = $this->member_group_prepare( $bp_group );

		// Add the Member Group ID to the params.
		if ( ! empty( $sync_groups ) ) {
			$member_group_params['id'] = $sync_groups['member_group_id'];
		}

		// Add the BuddyPress Group creator's Contact ID to the params.
		if ( ! empty( $bp_group->creator_id ) ) {
			$contact_id = $this->civicrm->contact->id_get_by_user_id( $bp_group->creator_id );
			if ( ! empty( $contact_id ) ) {
				$member_group_params['created_id'] = $contact_id;
			}
		}

		// Update the CiviCRM Member Group.
		$member_group = $this->update( $member_group_params );
		if ( false === $member_group ) {
			$this->permissions_escalate_stop();
			return false;
		}

		// Next prepare the CiviCRM ACL Group.
		$acl_group_params = $this->acl_group_prepare( $bp_group );

		// Add the ACL Group ID to the params.
		if ( ! empty( $sync_groups ) ) {
			$acl_group_params['id'] = $sync_groups['acl_group_id'];
		}

		// Add the BuddyPress Group creator's Contact ID to the params.
		if ( ! empty( $bp_group->creator_id ) ) {
			$contact_id = $this->civicrm->contact->id_get_by_user_id( $bp_group->creator_id );
			if ( ! empty( $contact_id ) ) {
				$acl_group_params['created_id'] = $contact_id;
			}
		}

		// Update the CiviCRM ACL Group.
		$acl_group = $this->update( $acl_group_params );
		if ( false === $acl_group ) {
			$this->permissions_escalate_stop();
			return false;
		}

		// Update ACL for the CiviCRM Groups.
		$success = $this->civicrm->acl->update_for_groups( $acl_group, $member_group );
		if ( false === $success ) {
			$this->permissions_escalate_stop();
			return false;
		}

		// Remove permissions.
		$this->permissions_escalate_stop();

		// --<
		return true;

	}

	/**
	 * Deletes the Synced CiviCRM Groups when a BuddyPress Group is deleted.
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 *
	 * @param object $bp_group The BuddyPress Group object.
	 */
	public function sync_groups_delete( $bp_group ) {

		// Init return.
		$success = false;

		// Escalate permissions.
		$this->permissions_escalate();

		// Get the Synced Group IDs from the BuddyPress Group meta.
		$sync_groups = $this->groups_for_bp_group_id_get( $bp_group->id );

		// Get the full Group data for deleting the ACL.
		$member_group = $this->get_by_id( $sync_groups['member_group_id'] );
		$acl_group    = $this->get_by_id( $sync_groups['acl_group_id'] );

		// Delete ACL for the CiviCRM Groups.
		$acl_deleted = $this->civicrm->acl->delete_for_groups( $acl_group, $member_group );

		// Delete the CiviCRM Member Group.
		$member_group_deleted = $this->delete( $sync_groups['member_group_id'] );

		// Delete the CiviCRM ACL Group.
		$acl_group_deleted = $this->delete( $sync_groups['acl_group_id'] );

		// Were all operations successful?
		if ( $acl_deleted && $member_group_deleted && $acl_group_deleted ) {
			$success = true;
		}

		// Remove permissions.
		$this->permissions_escalate_stop();

		// --<
		return $success;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Creates a CiviCRM Group.
	 *
	 * Unfortunately, CiviCRM insists on assigning the logged-in User as the Group
	 * creator. This means that we cannot assign the BuddyPress Group creator as
	 * the CiviCRM Group creator except by doing a follow-up API call.
	 *
	 * @see CRM_Contact_BAO_Group::create()
	 *
	 * @since 0.4
	 *
	 * @param array $group The array of CiviCRM API params.
	 * @return array|bool $group_data The array of Group data, or false otherwise.
	 */
	public function create( $group ) {

		// Init return.
		$group_data = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $group_data;
		}

		// Build params to create Group.
		$params = [
			'version' => 3,
			'debug'   => 1,
		] + $group;

		// Call the CiviCRM API.
		$result = civicrm_api( 'Group', 'create', $params );

		// Log and bail if there's an error.
		if ( ! empty( $result['is_error'] ) ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $group_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $group_data;
		}

		// The result set should contain only one item.
		$group_data = array_pop( $result['values'] );

		// --<
		return $group_data;

	}

	/**
	 * Updates a CiviCRM Group with a given set of data.
	 *
	 * This is an alias of `self::create()` except that we expect a
	 * Group ID to have been set in the Group data.
	 *
	 * @since 0.4
	 *
	 * @param array $group The CiviCRM Group data.
	 * @return array|bool The array of Group data from the CiviCRM API, or false on failure.
	 */
	public function update( $group ) {

		// Log and bail if there's no Group ID.
		if ( empty( $group['id'] ) ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => __( 'An ID must be present to update a Group.', 'bp-groups-civicrm-sync' ),
				'group'     => $group,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// Pass through.
		return $this->create( $group );

	}

	/**
	 * Deletes a CiviCRM Group.
	 *
	 * @since 0.4
	 *
	 * @param integer $group_id The numeric ID of the CiviCRM Group.
	 * @return bool $group_data The array Group data from the CiviCRM API, or false on failure.
	 */
	public function delete( $group_id ) {

		// Init as failure.
		$group_data = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $group_data;
		}

		// Log and bail if there's no Group ID.
		if ( empty( $group_id ) ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => __( 'An ID must be present to delete a Group.', 'bp-groups-civicrm-sync' ),
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// Build params to delete Group.
		$params = [
			'version' => 3,
			'id'      => $group_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'Group', 'delete', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $group_data;
		}

		// Success.
		$group_data = true;

		// --<
		return $group_data;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get all CiviCRM Group data.
	 *
	 * @since 0.3.7
	 * @since 0.4 Renamed.
	 *
	 * @return array $groups The array of CiviCRM Group data.
	 */
	public function groups_get_all() {

		// Init return.
		$groups = [];

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $group;
		}

		// Define params to get all Groups.
		$params = [
			'version' => 3,
			'options' => [
				'limit' => 0, // API defaults to 25.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Group', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $groups;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $groups;
		}

		// The result set is what we're after.
		$groups = $result['values'];

		// --<
		return $groups;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Find a CiviCRM Group ID by source and (optionally) by title.
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 *
	 * @param string $source The sync name for the CiviCRM Group.
	 * @param string $title The title of the CiviCRM Group to search for.
	 * @return int|bool $group_id The ID of the CiviCRM Group, or false on failure.
	 */
	public function id_find( $source, $title = '' ) {

		// Init return.
		$group_id = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $group_id;
		}

		// First find by "source".
		$group = $this->get_by_source( $source );

		// Return early if found.
		if ( false !== $group && is_array( $group ) ) {
			return (int) $group['id'];
		}

		// Find by title, if present.
		if ( ! empty( $title ) ) {

			// Next find by title.
			$group = $this->get_by_title( $title );

			// Return Group ID if found.
			if ( false !== $group && is_array( $group ) ) {
				return (int) $group['id'];
			}

		}

		// Fall back to false.
		return $group_id;

	}

	/**
	 * Gets a CiviCRM Group using a BuddyPress Group ID.
	 *
	 * @since 0.5.0
	 *
	 * @param integer $bp_group_id The numeric ID of the BuddyPress Group.
	 * @param string  $type The type of CiviCRM Group. Either "member" or "acl".
	 * @return array|bool $group The array of CiviCRM Group data, or false on failure.
	 */
	public function get_by_bp_id( $bp_group_id, $type = 'member' ) {

		// Get the "Source" for this Group Type.
		if ( 'member' === $type ) {
			$source = $this->member_group_get_sync_name( $bp_group_id );
		} else {
			$source = $this->acl_group_get_sync_name( $bp_group_id );
		}

		// Return Group by source.
		return $this->get_by_source( $source );

	}

	/**
	 * Gets a CiviCRM Group given a Group ID.
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 *
	 * @param int $group_id The numeric ID of the CiviCRM Group.
	 * @return array|bool $group The array of CiviCRM Group data, or false otherwise.
	 */
	public function get_by_id( $group_id ) {

		// Init return.
		$group = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $group;
		}

		// Define params to get the Group data.
		$params = [
			'version'  => 3,
			'group_id' => $group_id,
		];

		// Get Group.
		$result = civicrm_api( 'Group', 'get', $params );

		// Bail if we get any errors.
		if ( ! empty( $result['is_error'] ) ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $group;
		}

		// Bail if there are no values.
		if ( empty( $result['values'] ) ) {
			return $group;
		}

		// The result set should contain only one item.
		$group = array_shift( $result['values'] );

		// --<
		return $group;

	}

	/**
	 * Gets a CiviCRM Group given a "source".
	 *
	 * @since 0.4
	 *
	 * @param string $source The "sync name" for the CiviCRM Group.
	 * @return array|bool $group The array of CiviCRM Group data, or false otherwise.
	 */
	public function get_by_source( $source ) {

		// Init return.
		$group = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $group;
		}

		// Define params to get the Group data.
		$params = [
			'version' => 3,
			'source'  => $source,
		];

		// Get Group.
		$result = civicrm_api( 'Group', 'get', $params );

		// Bail if we get any errors.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $group;
		}

		// Bail if there are no values.
		if ( empty( $result['values'] ) ) {
			return $group;
		}

		// Bail if there are multiple values.
		if ( count( $result['values'] ) > 1 ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => __( 'There are multiple Groups with the same "sync name".', 'bp-groups-civicrm-sync' ),
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $group;
		}

		// The result set should contain only one item.
		$group = array_shift( $result['values'] );

		// --<
		return $group;

	}

	/**
	 * Gets a CiviCRM Group given a Group title.
	 *
	 * @since 0.4
	 *
	 * @param string $title The title of the CiviCRM Group.
	 * @return array|bool $group The array of CiviCRM Group data, or false otherwise.
	 */
	public function get_by_title( $title ) {

		// Init return.
		$group = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $group;
		}

		// Define params to get the Group data.
		$params = [
			'version' => 3,
			'title'   => $title,
		];

		// Get Group.
		$result = civicrm_api( 'Group', 'get', $params );

		// Bail if we get any errors.
		if ( ! empty( $result['is_error'] ) ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $group;
		}

		// Bail if there are no values.
		if ( empty( $result['values'] ) ) {
			return $group;
		}

		// Bail if there are multiple values.
		if ( count( $result['values'] ) > 1 ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => __( 'There are mulitple Groups with the same title.', 'bp-groups-civicrm-sync' ),
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return $group;
		}

		// The result set should contain only one item.
		$group = array_shift( $result['values'] );

		// --<
		return $group;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Checks if a CiviCRM Group has an associated BuddyPress Group.
	 *
	 * @since 0.1
	 * @since 0.5.0 Allow Group ID to be passed.
	 *
	 * @param array|int $group The array of CiviCRM Group data or the CiviCRM Group ID.
	 * @return boolean $has_group True if CiviCRM Group has a BuddyPress Group, false if not.
	 */
	public function has_bp_group( $group ) {

		// Get the Group if an integer is passed.
		if ( is_int( $group ) ) {
			$group = $this->get_by_id( $group );
		}

		// Bail if the "source" contains no info.
		if ( empty( $group['source'] ) ) {
			return false;
		}

		// If the Group "source" has no reference to BuddyPress, then it's not.
		if ( false === strstr( $group['source'], $this->source_member ) ) {
			return false;
		}

		// --<
		return true;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Checks if a CiviCRM Group is a BuddyPress Member Group.
	 *
	 * @since 0.1
	 * @since 0.5.0 Allow Group ID to be passed.
	 *
	 * @param array|int $group The array of CiviCRM Group data or the CiviCRM Group ID.
	 * @return boolean $is_member_group True if CiviCRM Group is a BuddyPress Member Group, false if not.
	 */
	public function is_member_group( $group ) {

		// Get the Group if an integer is passed.
		if ( is_int( $group ) ) {
			$group = $this->get_by_id( $group );
		}

		// Bail if Group has no reference to BuddyPress.
		if ( ! $this->has_bp_group( $group ) ) {
			return false;
		}

		// If the Group source has a reference to BuddyPress, then it is.
		if ( false !== strstr( $group['source'], $this->source_member . ' :' ) ) {
			return true;
		}

		// --<
		return false;

	}

	/**
	 * Construct params for a CiviCRM Member Group from a BuddyPress Group.
	 *
	 * @since 0.4
	 *
	 * @param object $bp_group The BuddyPress Group object.
	 * @return array $params The params for a CiviCRM Member Group.
	 */
	public function member_group_prepare( $bp_group ) {

		// Init params with BuddyPress data.
		$params = [
			'title'       => stripslashes( $bp_group->name ),
			'description' => stripslashes( $bp_group->description ),
			'is_active'   => 1,
		];

		// Get name for the CiviCRM Group.
		$params['source'] = $this->member_group_get_sync_name( $bp_group->id );

		// Define CiviCRM Group Type (Mailing List by default).
		$params['group_type'] = $this->type_array_get_by_type( 'member' );

		// --<
		return $params;

	}

	/**
	 * Construct "sync name" for CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param int $bp_group_id The BuddyPress Group ID.
	 * @return string $sync_name The "sync name" of the Member Group.
	 */
	public function member_group_get_sync_name( $bp_group_id ) {

		// Construct name, based on Organic Groups schema.
		$sync_name = $this->source_member . ' :' . $bp_group_id . ':';

		// --<
		return $sync_name;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Checks if a CiviCRM Group is a BuddyPress ACL Group.
	 *
	 * @since 0.1
	 * @since 0.5.0 Allow Group ID to be passed.
	 *
	 * @param array|int $group The array of CiviCRM Group data or the CiviCRM Group ID.
	 * @return boolean $is_acl_group True if CiviCRM Group is a BuddyPress ACL Group, false if not.
	 */
	public function is_acl_group( $group ) {

		// Get the Group if an integer is passed.
		if ( is_int( $group ) ) {
			$group = $this->get_by_id( $group );
		}

		// Bail if Group has no reference to BuddyPress.
		if ( ! $this->has_bp_group( $group ) ) {
			return false;
		}

		// If the Group source has a reference to BuddyPress ACL, then it is.
		if ( false !== strstr( $group['source'], $this->source_acl . ' :' ) ) {
			return true;
		}

		// --<
		return false;

	}

	/**
	 * Constructs the params for a CiviCRM ACL Group from a BuddyPress Group.
	 *
	 * @since 0.4
	 *
	 * @param object $bp_group The BuddyPress Group object.
	 * @return array $params The params for a CiviCRM ACL Group.
	 */
	public function acl_group_prepare( $bp_group ) {

		// Prepare title.
		$title = sprintf(
			/* translators: %s: The name of the BuddyPress Group */
			__( '%s: Administrator', 'bp-groups-civicrm-sync' ),
			stripslashes( $bp_group->name )
		);

		// Init params with BuddyPress data.
		$params = [
			'title'       => $title,
			'description' => stripslashes( $bp_group->description ),
			'is_active'   => 1,
		];

		// Get the "sync name" for the CiviCRM ACL Group.
		$params['source'] = $this->acl_group_get_sync_name( $bp_group->id );

		// Set inscrutable Group Type (Access Control).
		$params['group_type'] = $this->type_array_get_by_type( 'acl' );

		// --<
		return $params;

	}

	/**
	 * Construct name for CiviCRM ACL Group.
	 *
	 * @since 0.1
	 *
	 * @param int $bp_group_id The BuddyPress Group ID.
	 * @return string $sync_name The "sync name" of the ACL Group.
	 */
	public function acl_group_get_sync_name( $bp_group_id ) {

		// Construct name, based on Organic Groups schema.
		$sync_name = $this->source_acl . ' :' . $bp_group_id . ':';

		// --<
		return $sync_name;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets the BuddyPress Group ID for a given CiviCRM Group "source" string.
	 *
	 * @since 0.5.0
	 *
	 * @param string $source The "source" of the CiviCRM Group.
	 * @return int|bool $group_id The BuddyPress Group ID, or false on failure.
	 */
	public function id_get_by_source( $source ) {

		// Init return.
		$bp_group_id = false;

		// Bail if there's a problem.
		if ( empty( $source ) ) {
			return $bp_group_id;
		}

		// Bail if the Group "source" has no reference to BuddyPress.
		if ( false === strstr( $source, $this->source_member ) ) {
			return $bp_group_id;
		}

		// Get BuddyPress Group ID - source is of the form "BP Sync Group :BPID:".
		$tmp = explode( ':', $source );
		if ( empty( $tmp[1] ) ) {
			return $bp_group_id;
		}

		// Always cast as integer.
		$bp_group_id = (int) $tmp[1];

		// --<
		return $bp_group_id;

	}

	/**
	 * Gets the type of CiviCRM Group by "source" string.
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 *
	 * @param string $source The "source" of the CiviCRM Group.
	 * @return string|bool $group_type The type of CiviCRM Group - either 'member' or 'acl'. False if neither.
	 */
	public function type_get_by_source( $source ) {

		// Init return.
		$group_type = false;

		// Check for Member Group.
		if ( false !== strstr( $source, $this->source_member . ' :' ) ) {
			$group_type = 'member';
		}

		// Check for ACL Group.
		if ( false !== strstr( $source, $this->source_acl . ' :' ) ) {
			$group_type = 'acl';
		}

		// --<
		return $group_type;

	}

	/**
	 * Gets the API "type array" of a CiviCRM Group for a given type string.
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 *
	 * @param string $group_type The type of CiviCRM Group - either 'member' or 'acl'.
	 * @return array $type_data Associative array of CiviCRM Group Type data for the API.
	 */
	public function type_array_get_by_type( $group_type ) {

		// If 'member'.
		if ( 'member' === $group_type ) {

			/**
			 * Filter the CiviCRM Group Type.
			 *
			 * By default, this is set to "Mailing List".
			 *
			 * @since 0.1
			 *
			 * @param array The "Mailing List" CiviCRM Group Type array.
			 */
			$type_data = apply_filters( 'bp_groups_civicrm_sync_member_group_type', [ '2' => 2 ] );

		}

		// If 'acl'.
		if ( 'acl' === $group_type ) {

			/**
			 * Filter the CiviCRM Group Type.
			 *
			 * By default, this is set to "Access Control".
			 *
			 * @since 0.1
			 *
			 * @param array The "Access Control" CiviCRM Group Type array.
			 */
			$type_data = apply_filters( 'bp_groups_civicrm_sync_acl_group_type', [ '1' => 1 ] );

		}

		// --<
		return $type_data;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets the Synced CiviCRM Group IDs for a given BuddyPress Group ID.
	 *
	 * @since 0.4
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @return array|bool $sync_groups The array of Synced CiviCRM Group IDs, false otherwise.
	 */
	public function groups_for_bp_group_id_get( $group_id ) {

		// Check BuddyPress config.
		if ( ! $this->plugin->bp->is_configured() ) {
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
	 * @param int   $group_id The numeric ID of the BuddyPress Group.
	 * @param array $civicrm_groups The array of Synced CiviCRM Group IDs.
	 */
	public function groups_for_bp_group_id_set( $group_id, $civicrm_groups ) {

		// Check BuddyPress config.
		if ( ! $this->plugin->bp->is_configured() ) {
			return;
		}

		// Update our Group Meta with the IDs of the CiviCRM Groups.
		groups_update_groupmeta( $group_id, 'civicrm_groups', $civicrm_groups );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Filters the CiviCRM Permissions to escalate permissions.
	 *
	 * @since 0.4
	 */
	public function permissions_escalate() {
		add_action( 'civicrm_permission_check', [ $this, 'permissions_grant' ], 10, 2 );
	}

	/**
	 * Removes the CiviCRM Permissions filter to restore permissions.
	 *
	 * @since 0.4
	 */
	public function permissions_escalate_stop() {
		remove_action( 'civicrm_permission_check', [ $this, 'permissions_grant' ], 10 );
	}

	/**
	 * Grants permissions to create CiviCRM Groups.
	 *
	 * BuddyPress Members must be granted the permissions necessary to create the
	 * synced CiviCRM Groups when they create a BuddyPress Group.
	 *
	 * @since 0.4
	 *
	 * @param str  $permission The requested permission.
	 * @param bool $granted True if permission granted, false otherwise.
	 */
	public function permissions_grant( $permission, &$granted ) {

		// Build array of necessary permissions.
		$permissions = [
			'administer civicrm',
			'all civicrm permissions and acls',
			'edit groups',
		];

		// Allow the relevant ones.
		if ( in_array( strtolower( $permission ), $permissions ) ) {
			$granted = 1;
		}

	}

}
