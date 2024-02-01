<?php
/**
 * CiviCRM Group Nesting Class.
 *
 * Handles functionality related to CiviCRM Group Nesting.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * BP Groups CiviCRM Sync CiviCRM Group Nesting Class.
 *
 * A class that encapsulates functionality related to CiviCRM Group Nesting.
 *
 * @since 0.4
 */
class BP_Groups_CiviCRM_Sync_CiviCRM_Group_Nesting {

	/**
	 * Plugin object.
	 *
	 * @since 0.4
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync
	 */
	public $plugin;

	/**
	 * CiviCRM object.
	 *
	 * @since 0.4
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_CiviCRM
	 */
	public $civicrm;

	/**
	 * Constructor.
	 *
	 * @since 0.4
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
		do_action( 'bpgcs/civicrm/group/nesting/loaded' );

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.4
	 */
	public function register_hooks() {

	}

	/**
	 * Unregister hooks.
	 *
	 * @since 0.4
	 */
	public function unregister_hooks() {

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets a CiviCRM Group Nesting.
	 *
	 * @since 0.4
	 *
	 * @param integer $group_id The numeric ID of the CiviCRM Group.
	 * @param integer $parent_id The numeric ID of the parent CiviCRM Group.
	 * @return array|bool $group_nesting_data The CiviCRM Group Nesting data, or false otherwise.
	 */
	public function nesting_get( $group_id, $parent_id ) {

		// Init return.
		$group_nesting_data = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $group_nesting_data;
		}

		// Define params to get existing Group Nesting.
		$params = [
			'version'         => 3,
			'parent_group_id' => $parent_id,
			'child_group_id'  => $group_id,
		];

		// Get existing Group Nesting.
		$result = civicrm_api( 'GroupNesting', 'get', $params );

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
			return $group_nesting_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $group_nesting_data;
		}

		// The result set should contain only one item.
		$group_nesting_data = array_pop( $result['values'] );

		// --<
		return $group_nesting_data;

	}

	/**
	 * Create a CiviCRM Group Nesting.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class.
	 *
	 * @param integer $group_id The numeric ID of the CiviCRM Group.
	 * @param integer $parent_id The numeric ID of the parent CiviCRM Group.
	 * @return array|bool $nesting The array of CiviCRM Group Nesting data, or false otherwise.
	 */
	public function nesting_create( $group_id, $parent_id ) {

		// Init return.
		$nesting = false;

		// Bail if no parent set.
		if ( empty( $parent_id ) || ! is_numeric( $parent_id ) ) {
			return $nesting;
		}

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $nesting;
		}

		// Define new Group Nesting.
		$params = [
			'version'         => 3,
			'child_group_id'  => $group_id,
			'parent_group_id' => $parent_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'GroupNesting', 'create', $params );

		// Log if there's an error.
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
			return $nesting;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $nesting;
		}

		// The result set should contain only one item.
		$nesting = array_pop( $result['values'] );

		// --<
		return $nesting;

	}

	/**
	 * Updates a CiviCRM Group's hierarchy when a BuddyPress Group's hierarchy is updated.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class and renamed.
	 *
	 * @param integer $bp_group_id The numeric ID of the BuddyPress Group.
	 * @param integer $bp_parent_id The numeric ID of the parent BuddyPress Group.
	 */
	public function nesting_update( $bp_group_id, $bp_parent_id = 0 ) {

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return false;
		}

		// Get the Synced Group IDs from the BuddyPress Group meta.
		$sync_groups = $this->civicrm->group->groups_for_bp_group_id_get( $bp_group_id );

		/*
		 * First handle the nesting of the CiviCRM Member Group.
		 * ---------------------------------------------------------------------
		 */

		// Get the CiviCRM Member Group ID of this BuddyPress Group.
		if ( ! empty( $sync_groups ) ) {
			$member_group_id = $sync_groups['member_group_id'];
		} else {
			$sync_name       = $this->civicrm->group->member_group_get_sync_name( $bp_group_id );
			$member_group_id = $this->civicrm->group->id_find( $sync_name );
		}

		// Bail if we don't get an ID for the Member Group.
		if ( empty( $member_group_id ) ) {
			return;
		}

		// Get the full Group data.
		$member_group = $this->civicrm->group->get_by_id( $member_group_id );

		// Delete all existing parents.
		if ( ! empty( $member_group['parents'] ) ) {
			$this->nesting_clear( $member_group_id, $member_group['parents'] );
		}

		// Get the ID of the CiviCRM Member Group that is synced to the BuddyPress Parent Group.
		$civicrm_parent_id = $this->parent_id_get( $bp_parent_id, 'member' );

		// Create new nesting.
		$this->nesting_create( $member_group_id, $civicrm_parent_id );

		/*
		 * Next handle the nesting of the CiviCRM ACL Group.
		 * ---------------------------------------------------------------------
		 */

		// Get the CiviCRM Member Group ID of this BuddyPress Group.
		if ( ! empty( $sync_groups ) ) {
			$acl_group_id = $sync_groups['acl_group_id'];
		} else {
			$sync_name    = $this->civicrm->group->acl_group_get_sync_name( $bp_group_id );
			$acl_group_id = $this->civicrm->group->id_find( $sync_name );
		}

		// Bail if we don't get an ACL Group ID.
		if ( empty( $acl_group_id ) ) {
			return;
		}

		// Get the full ACL Group data.
		$acl_group = $this->civicrm->group->get_by_id( $acl_group_id );

		// Delete all existing parents.
		if ( ! empty( $acl_group['parents'] ) ) {
			$this->nesting_clear( $acl_group_id, $acl_group['parents'] );
		}

		// Get the ID of the CiviCRM ACL Group that is synced to the BuddyPress Parent Group.
		$acl_parent_id = $this->parent_id_get( $bp_parent_id, 'acl' );

		// Create new nesting.
		$this->nesting_create( $acl_group_id, $acl_parent_id );

	}

	/**
	 * Clears the CiviCRM Group Nestings for a given Group ID.
	 *
	 * BuddyPress Groups can only have one Parent Group but CiviCRM Groups can
	 * have multiple Parent Groups. Deleting all parent CiviCRM Groups is a
	 * crude way to ensure that our Synced CiviCRM Groups have the same
	 * hierarchy as their BuddyPress counterparts.
	 *
	 * @since 0.4
	 *
	 * @param integer              $group_id The numeric ID of the CiviCRM Group.
	 * @param array|string|integer $parent_ids The parent CiviCRM Group IDs.
	 */
	public function nesting_clear( $group_id, $parent_ids ) {

		// Convert incoming parent IDs to an array.
		if ( is_array( $parent_ids ) ) {
			$parents = $parent_ids;
		} elseif ( is_string( $parent_ids ) && strstr( $parent_ids, ',' ) ) {
			$parents = explode( ',', $parent_ids );
		} elseif ( is_numeric( $parent_ids ) ) {
			$parents = [ $parent_ids ];
		}

		// Cast parent IDs as integers.
		array_walk(
			$parents,
			function( &$item ) {
				$item = (int) $item;
			}
		);

		// Delete them all.
		foreach ( $parents as $parent ) {
			$this->nesting_delete( $group_id, $parent );
		}

	}

	/**
	 * Deletes the CiviCRM Group Nesting for a given Group ID and Parent ID.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class.
	 *
	 * @param integer $group_id The numeric ID of the CiviCRM Group.
	 * @param integer $parent_id The numeric ID of the parent CiviCRM Group.
	 */
	public function nesting_delete( $group_id, $parent_id ) {

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return false;
		}

		// Get existing Group Nesting.
		$existing = $this->nesting_get( $group_id, $parent_id );
		if ( empty( $existing ) ) {
			return false;
		}

		// Construct params to delete Group Nesting.
		$params = [
			'version' => 3,
			'id'      => $existing['id'],
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'GroupNesting', 'delete', $params );

		// Log if there's an error.
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
		}

		// Success.
		return true;

	}

	/**
	 * For a given BuddyPress Parent Group ID, get the ID of the synced CiviCRM Group.
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 * @since 0.4 Moved to this class.
	 *
	 * @param integer $bp_parent_id The numeric ID of the parent BuddyPress Group.
	 * @param string  $group_type The CiviCRM Group Type - 'member' or 'acl'.
	 * @return int|bool $civicrm_parent_id The ID of the parent CiviCRM Group, false otherwise.
	 */
	public function parent_id_get( $bp_parent_id, $group_type ) {

		// Init return.
		$civicrm_parent_id = false;

		// If the BuddyPress parent ID is 0, we're removing the BuddyPress parent or none is set.
		if ( 0 === (int) $bp_parent_id ) {

			// Bail if we're not using a Meta Group.
			$parent_group = (int) $this->plugin->admin->setting_get( 'parent_group' );
			if ( 0 === $parent_group ) {
				return $civicrm_parent_id;
			}

			// Get the ID of the CiviCRM Meta Group.
			$sync_name = $this->civicrm->meta_group->get_source();

		} else {

			// What kind of Group is this?
			if ( 'member' === $group_type ) {
				$sync_name = $this->civicrm->group->member_group_get_sync_name( $bp_parent_id );
			} elseif ( 'acl' === $group_type ) {
				$sync_name = $this->civicrm->group->acl_group_get_sync_name( $bp_parent_id );
			} else {
				return $civicrm_parent_id;
			}

		}

		// Get the ID of the parent CiviCRM Group.
		$civicrm_parent_id = $this->civicrm->group->id_find( $sync_name );

		// --<
		return $civicrm_parent_id;

	}

}
