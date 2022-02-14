<?php
/**
 * CiviCRM Class.
 *
 * Handles CiviCRM-related functionality.
 *
 * @package BP_Groups_CiviCRM_Sync
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * BP Groups CiviCRM Sync CiviCRM Class.
 *
 * A class that encapsulates CiviCRM functionality.
 *
 * Code snippet preserved from previous docblock:
 *
 * $groupOptions = CRM_Core_BAO_OptionValue::getOptionValuesAssocArrayFromName('group_type');
 * $groupTypes = CRM_Core_OptionGroup::values('group_type', TRUE);
 *
 * @since 0.1
 */
class BP_Groups_CiviCRM_Sync_CiviCRM {

	/**
	 * Plugin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * BuddyPress utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $bp The BuddyPress utilities object.
	 */
	public $bp;

	/**
	 * Admin utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $admin The Admin utilities object.
	 */
	public $admin;

	/**
	 * CiviCRM ACL utilities object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $acl The CiviCRM ACL utilities object.
	 */
	public $acl;

	/**
	 * CiviCRM Meta Group object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $meta_group The CiviCRM Meta Group object.
	 */
	public $meta_group;

	/**
	 * CiviCRM Group Contact object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $group_contact The CiviCRM Group Contact object.
	 */
	public $group_contact;

	/**
	 * CiviCRM Group Nesting object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $group_nesting The CiviCRM Group Nesting object.
	 */
	public $group_nesting;

	/**
	 * CiviCRM Group Admin object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $group_admin The CiviCRM Group Admin object.
	 */
	public $group_admin;



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
		$this->bp = $this->plugin->bp;
		$this->admin = $this->plugin->admin;

		// Bootstrap this class.
		$this->include_files();
		$this->setup_objects();
		$this->register_hooks();

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 0.4
		 */
		do_action( 'bpgcs/civicrm/loaded' );

	}



	/**
	 * Include files.
	 *
	 * @since 0.4
	 */
	public function include_files() {

		// Include class files.
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/bpgcs-civicrm-group-meta.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/bpgcs-civicrm-group-contact.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/bpgcs-civicrm-group-nesting.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/bpgcs-civicrm-group-admin.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/bpgcs-civicrm-acl.php';

	}



	/**
	 * Set up this plugin's objects.
	 *
	 * @since 0.4
	 */
	public function setup_objects() {

		// Initialise objects.
		$this->meta_group = new BP_Groups_CiviCRM_Sync_CiviCRM_Group_Meta( $this );
		$this->group_contact = new BP_Groups_CiviCRM_Sync_CiviCRM_Group_Contact( $this );
		$this->group_nesting = new BP_Groups_CiviCRM_Sync_CiviCRM_Group_Nesting( $this );
		$this->group_admin = new BP_Groups_CiviCRM_Sync_CiviCRM_Group_Admin( $this );
		$this->acl = new BP_Groups_CiviCRM_Sync_CiviCRM_ACL( $this );

	}



	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		/**
		 * Broadcast that our hooks have been registered.
		 *
		 * @since 0.1
		 */
		do_action( 'bp_groups_civicrm_sync_civi_loaded' );

	}



	/**
	 * Checks if CiviCRM plugin is initialised.
	 *
	 * @since 0.1
	 *
	 * @return bool True if successfully initialised, false otherwise.
	 */
	public function is_initialised() {

		// Try and init CiviCRM.
		return civi_wp()->initialize();

	}



	// -------------------------------------------------------------------------



	/**
	 * Creates the Synced CiviCRM Groups when a BuddyPress Group is created.
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 *
	 * @param object $bp_group The BuddyPress Group object.
	 * @return array|bool $group_ids Associative array of CiviCRM Group IDs, or false on failure.
	 */
	public function sync_groups_create( $bp_group ) {

		// First prepare the CiviCRM Member Group.
		$member_group_params = $this->member_group_prepare( $bp_group );

		// Create the CiviCRM Member Group.
		$member_group = $this->group_create( $member_group_params );
		if ( $member_group === false ) {
			return false;
		}

		// TODO: Update Member Group to preserve Group Creator.

		// Next prepare the CiviCRM ACL Group.
		$acl_group_params = $this->acl_group_prepare( $bp_group );

		// Create the CiviCRM ACL Group.
		$acl_group = $this->group_create( $acl_group_params );

		// Bail on failure.
		if ( $acl_group === false ) {

			// Clean up by deleting the Member Group.
			$this->group_delete( $member_group['id'] );

			// --<
			return false;

		}

		// TODO: Update ACL Group to preserve Group Creator.

		// Populate return array.
		$group_ids = [
			'member_group_id' => $member_group['id'],
			'acl_group_id' => $acl_group['id'],
		];

		// Create ACL for the CiviCRM Groups.
		$success = $this->acl->update_for_groups( $acl_group, $member_group );
		if ( $success === false ) {
			return false;
		}

		// Maybe assing to Meta Group.
		$this->group_nesting->nesting_update( $bp_group->id );

		// Add the creator to the Groups.
		$args = [
			'action' => 'add',
			'group_id' => $bp_group->id,
			'user_id' => $bp_group->creator_id,
			'status' => 'admin',
			'is_active' => 1,
		];

		// Update the corresponding CiviCRM Group memberships.
		$this->group_contact->memberships_sync( $args );

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
	 */
	public function sync_groups_update( $bp_group ) {

		// Get the Synced Group IDs from the BuddyPress Group meta.
		$sync_groups = groups_get_groupmeta( $bp_group->id, 'civicrm_groups' );

		// First prepare the CiviCRM Member Group.
		$member_group_params = $this->member_group_prepare( $bp_group );

		// Add the Member Group ID to the params.
		if ( ! empty( $sync_groups ) ) {
			$member_group_params['id'] = $sync_groups['member_group_id'];
		}

		// Update the CiviCRM Member Group.
		$member_group = $this->group_update( $member_group_params );
		if ( $member_group === false ) {
			return false;
		}

		// Next prepare the CiviCRM ACL Group.
		$acl_group_params = $this->acl_group_prepare( $bp_group );

		// Add the ACL Group ID to the params.
		if ( ! empty( $sync_groups ) ) {
			$acl_group_params['id'] = $sync_groups['acl_group_id'];
		}

		// Update the CiviCRM ACL Group.
		$acl_group = $this->group_update( $acl_group_params );
		if ( $acl_group === false ) {
			return false;
		}

		// Update ACL for the CiviCRM Groups.
		$success = $this->acl->update_for_groups( $acl_group, $member_group );
		if ( $success === false ) {
			return false;
		}

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

		// Get the Synced Group IDs from the BuddyPress Group meta.
		$sync_groups = groups_get_groupmeta( $bp_group->id, 'civicrm_groups' );

		// Get the full Group data for deleting the ACL.
		$member_group = $this->group_get_by_id( $sync_groups['member_group_id'] );
		$acl_group = $this->group_get_by_id( $sync_groups['acl_group_id'] );

		// Delete ACL for the CiviCRM Groups.
		$acl_deleted = $this->acl->delete_for_groups( $acl_group, $member_group );

		// Delete the CiviCRM Member Group.
		$member_group_deleted = $this->group_delete( $sync_groups['member_group_id'] );

		// Delete the CiviCRM ACL Group.
		$acl_group_deleted = $this->group_delete( $sync_groups['acl_group_id'] );

		// Were all operations successful?
		if ( $acl_deleted && $member_group_deleted && $acl_group_deleted ) {
			$success = true;
		}

		// --<
		return $success;

	}



	// -------------------------------------------------------------------------



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
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'params' => $params,
					'result' => $result,
					'backtrace' => $trace,
				], true ) );
			}
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



	// -------------------------------------------------------------------------



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
	public function group_create( $group ) {

		// Init return.
		$group_data = false;

		// Try and initialise CiviCRM.
		if ( ! $this->is_initialised() ) {
			return $group_data;
		}

		// Build params to create Group.
		$params = [
			'version' => 3,
			'debug' => 1,
		] + $group;

		// Call the CiviCRM API.
		$result = civicrm_api( 'Group', 'create', $params );

		// Log and bail if there's an error.
		if ( ! empty( $result['is_error'] ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'params' => $params,
					'result' => $result,
					'backtrace' => $trace,
				], true ) );
			}
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
	 * This is an alias of `self::group_create()` except that we expect a
	 * Group ID to have been set in the Group data.
	 *
	 * @since 0.4
	 *
	 * @param array $group The CiviCRM Group data.
	 * @return array|bool The array of Group data from the CiviCRM API, or false on failure.
	 */
	public function group_update( $group ) {

		// Log and bail if there's no Group ID.
		if ( empty( $group['id'] ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'message' => __( 'An ID must be present to update a Group.', 'bp-groups-civicrm-sync' ),
					'group' => $group,
					'backtrace' => $trace,
				], true ) );
			}
			return false;
		}

		// Pass through.
		return $this->group_create( $group );

	}



	/**
	 * Deletes a CiviCRM Group.
	 *
	 * @since 0.4
	 *
	 * @param integer $group_id The numeric ID of the CiviCRM Group.
	 * @return bool $group_data The array Group data from the CiviCRM API, or false on failure.
	 */
	public function group_delete( $group_id ) {

		// Init as failure.
		$group_data = false;

		// Try and initialise CiviCRM.
		if ( ! $this->is_initialised() ) {
			return $group_data;
		}

		// Log and bail if there's no Group ID.
		if ( empty( $group_id ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'message' => __( 'An ID must be present to delete a Group.', 'bp-groups-civicrm-sync' ),
					'backtrace' => $trace,
				], true ) );
			}
			return false;
		}

		// Build params to delete Group.
		$params = [
			'version' => 3,
			'id' => $group_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'Group', 'delete', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'params' => $params,
					'result' => $result,
					'backtrace' => $trace,
				], true ) );
			}
			return $group_data;
		}

		// Success.
		$group_data = true;

		// --<
		return $group_data;

	}



	// -------------------------------------------------------------------------



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
	public function group_id_find( $source, $title = '' ) {

		// Init return.
		$group_id = false;

		// Try and initialise CiviCRM.
		if ( ! $this->is_initialised() ) {
			return $group_id;
		}

		// First find by "source".
		$group = $this->group_get_by_source( $source );

		// Return early if found.
		if ( $group !== false && is_array( $group ) ) {
			return (int) $group['id'];
		}

		// Find by title, if present.
		if ( ! empty( $title ) ) {

			// Next find by title.
			$group = $this->group_get_by_title( $title );

			// Return Group ID if found.
			if ( $group !== false && is_array( $group ) ) {
				return (int) $group['id'];
			}

		}

		// Fall back to false.
		return $group_id;

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
	public function group_get_by_id( $group_id ) {

		// Init return.
		$group = false;

		// Try and initialise CiviCRM.
		if ( ! $this->is_initialised() ) {
			return $group;
		}

		// Define params to get the Group data.
		$params = [
			'version' => 3,
			'group_id' => $group_id,
		];

		// Get Group.
		$result = civicrm_api( 'Group', 'get', $params );

		// Bail if we get any errors.
		if ( ! empty( $result['is_error'] ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'params' => $params,
					'result' => $result,
					'backtrace' => $trace,
				], true ) );
			}
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
	public function group_get_by_source( $source ) {

		// Init return.
		$group = false;

		// Try and initialise CiviCRM.
		if ( ! $this->is_initialised() ) {
			return $group;
		}

		// Define params to get the Group data.
		$params = [
			'version' => 3,
			'source' => $source,
		];

		// Get Group.
		$result = civicrm_api( 'Group', 'get', $params );

		// Bail if we get any errors.
		if ( ! empty( $result['is_error'] ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'params' => $params,
					'result' => $result,
					'backtrace' => $trace,
				], true ) );
			}
			return $group;
		}

		// Bail if there are no values.
		if ( empty( $result['values'] ) ) {
			return $group;
		}

		// Bail if there are multiple values.
		if ( count( $result['values'] ) > 1 ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'message' => __( 'There are multiple groups with the same "sync name".', 'bp-groups-civicrm-sync' ),
					'params' => $params,
					'result' => $result,
					'backtrace' => $trace,
				], true ) );
			}
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
	public function group_get_by_title( $title ) {

		// Init return.
		$group = false;

		// Try and initialise CiviCRM.
		if ( ! $this->is_initialised() ) {
			return $group;
		}

		// Define params to get the Group data.
		$params = [
			'version' => 3,
			'title' => $title,
		];

		// Get Group.
		$result = civicrm_api( 'Group', 'get', $params );

		// Bail if we get any errors.
		if ( ! empty( $result['is_error'] ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'params' => $params,
					'result' => $result,
					'backtrace' => $trace,
				], true ) );
			}
			return $group;
		}

		// Bail if there are no values.
		if ( empty( $result['values'] ) ) {
			return $group;
		}

		// Bail if there are multiple values.
		if ( count( $result['values'] ) > 1 ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'message' => __( 'There are mulitple groups with the same title.', 'bp-groups-civicrm-sync' ),
					'params' => $params,
					'result' => $result,
					'backtrace' => $trace,
				], true ) );
			}
			return $group;
		}

		// The result set should contain only one item.
		$group = array_shift( $result['values'] );

		// --<
		return $group;

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
	public function group_type_get_by_source( $source ) {

		// Init return.
		$group_type = false;

		// Check for Member Group.
		if ( strstr( $source, 'BP Sync Group :' ) !== false ) {
			$group_type = 'member';
		}

		// Check for ACL Group.
		if ( strstr( $source, 'BP Sync Group ACL :' ) !== false ) {
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
	public function group_type_array_get_by_type( $group_type ) {

		// If 'member'.
		if ( $group_type == 'member' ) {

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
		if ( $group_type == 'acl' ) {

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



	/**
	 * Checks if a CiviCRM Group has an associated BuddyPress Group.
	 *
	 * @since 0.1
	 *
	 * @param array $group The array of CiviCRM Group data.
	 * @return boolean $has_group True if CiviCRM Group has a BuddyPress Group, false if not.
	 */
	public function has_bp_group( $group ) {

		// Bail if the "source" contains no info.
		if ( empty( $group['source'] ) ) {
			return false;
		}

		// If the Group "source" has no reference to BuddyPress, then it's not.
		if ( strstr( $group['source'], 'BP Sync Group' ) === false ) {
			return false;
		}

		// --<
		return true;

	}



	// -------------------------------------------------------------------------



	/**
	 * Check if a CiviCRM Group is a BuddyPress Member Group.
	 *
	 * @since 0.1
	 *
	 * @param array $group The array of CiviCRM Group data.
	 * @return boolean $is_member_group True if CiviCRM Group is a BuddyPress Member Group, false if not.
	 */
	public function is_member_group( $group ) {

		// Bail if Group has no reference to BuddyPress.
		if ( ! $this->has_bp_group( $group ) ) {
			return false;
		}

		// If the Group source has a reference to BuddyPress, then it is.
		if ( strstr( $group['source'], 'BP Sync Group :' ) !== false ) {
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
			'title' => stripslashes( $bp_group->name ),
			'description' => stripslashes( $bp_group->description ),
			'is_active' => 1,
		];

		// Get name for the CiviCRM Group.
		$params['source'] = $this->member_group_get_sync_name( $bp_group->id );

		// Define CiviCRM Group Type (Mailing List by default).
		$params['group_type'] = $this->group_type_array_get_by_type( 'member' );

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
		$sync_name = 'BP Sync Group :' . $bp_group_id . ':';

		// --<
		return $sync_name;

	}



	// -------------------------------------------------------------------------



	/**
	 * Check if a CiviCRM Group is a BuddyPress ACL Group.
	 *
	 * @since 0.1
	 *
	 * @param array $group The array of CiviCRM Group data.
	 * @return boolean $is_acl_group True if CiviCRM Group is a BuddyPress ACL Group, false if not.
	 */
	public function is_acl_group( $group ) {

		// Bail if Group has no reference to BuddyPress.
		if ( ! $this->has_bp_group( $group ) ) {
			return false;
		}

		// If the Group source has a reference to BuddyPress ACL, then it is.
		if ( strstr( $group['source'], 'BP Sync Group ACL :' ) !== false ) {
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
			'title' => $title,
			'description' => stripslashes( $bp_group->description ),
			'is_active' => 1,
		];

		// Get the "sync name" for the CiviCRM ACL Group.
		$params['source'] = $this->acl_group_get_sync_name( $bp_group->id );

		// Set inscrutable Group Type (Access Control).
		$params['group_type'] = $this->group_type_array_get_by_type( 'acl' );

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
		$sync_name = 'BP Sync Group ACL :' . $bp_group_id . ':';

		// --<
		return $sync_name;

	}



	// -------------------------------------------------------------------------



	/**
	 * Updates a CiviCRM Contact when a WordPress User is updated.
	 *
	 * @since 0.1
	 *
	 * @param object $user The WordPress User object.
	 */
	public function civicrm_contact_update( $user ) {

		// Try and initialise CiviCRM.
		if ( ! $this->is_initialised() ) {
			return false;
		}

		// Make sure CiviCRM file is included.
		require_once 'CRM/Core/BAO/UFMatch.php';

		// The synchronizeUFMatch method returns the Contact object.
		$contact = CRM_Core_BAO_UFMatch::synchronizeUFMatch(
			$user, // User object.
			$user->ID, // ID.
			$user->user_email, // Unique identifier.
			'WordPress', // CMS.
			null, // Unused.
			'Individual' // Contact type.
		);

	}



	/**
	 * Create a link between a WordPress User and a CiviCRM Contact.
	 *
	 * This method optionally allows a Domain ID to be specified.
	 *
	 * @since 0.4
	 *
	 * @param integer $contact_id The numeric ID of the CiviCRM Contact.
	 * @param integer $user_id The numeric ID of the WordPress User.
	 * @param str $username The WordPress username.
	 * @param integer $domain_id The CiviCRM Domain ID (defaults to current Domain ID).
	 * @return array|bool The UFMatch data on success, or false on failure.
	 */
	public function ufmatch_create( $contact_id, $user_id, $username, $domain_id = '' ) {

		// Try and initialise CiviCRM.
		if ( ! $this->is_initialised() ) {
			return false;
		}

		// Sanity checks.
		if ( ! is_numeric( $contact_id ) || ! is_numeric( $user_id ) ) {
			return false;
		}

		// Construct params.
		$params = [
			'version' => 3,
			'uf_id' => $user_id,
			'uf_name' => $username,
			'contact_id' => $contact_id,
		];

		// Maybe add Domain ID.
		if ( ! empty( $domain_id ) ) {
			$params['domain_id'] = $domain_id;
		}

		// Create record via API.
		$result = civicrm_api( 'UFMatch', 'create', $params );

		// Log and bail on failure.
		if ( ! empty( $result['is_error'] ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new Exception();
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

		// --<
		return $result;

	}



} // Class ends.