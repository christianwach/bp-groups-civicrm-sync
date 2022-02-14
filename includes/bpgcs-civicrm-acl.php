<?php
/**
 * CiviCRM ACL Class.
 *
 * Handles functionality related to CiviCRM ACLs.
 *
 * @package BP_Groups_CiviCRM_Sync
 * @since 0.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * BP Groups CiviCRM Sync CiviCRM ACL Class.
 *
 * A class that encapsulates functionality related to CiviCRM ACLs.
 *
 * @since 0.4
 */
class BP_Groups_CiviCRM_Sync_CiviCRM_ACL {

	/**
	 * Plugin object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * CiviCRM utilities object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $civicrm The CiviCRM utilities object.
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
		$this->plugin = $parent->plugin;
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
		do_action( 'bpgcs/civicrm/acl/loaded' );

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



	// -------------------------------------------------------------------------



	/**
	 * Creates the ACL for an ACL Group's permissions over a Member Group.
	 *
	 * Not used at present because the `update_for_groups()` method does repair
	 * for all Synced Groups as well as creating the necessary ACL items.
	 *
	 * @see self::update_for_groups()
	 *
	 * @since 0.4
	 *
	 * @param array $acl_group The array of CiviCRM ACL Group data.
	 * @param array $member_group The array of CiviCRM Member Group data.
	 * @return bool True when all operations are successful, false otherwise.
	 */
	public function create_for_groups( $acl_group, $member_group ) {

		// Define params to create an "ACL Role".
		$params = [
			'description' => $acl_group['source'],
			'label' => $acl_group['title'],
			'is_active' => 1,
		];

		// Create the "ACL Role".
		$acl_role = $this->acl_role_create( $params );
		if ( empty( $acl_role ) ) {
			return false;
		}

		// Define params to create an "ACL Entity Role".
		$params = [
			'entity_id' => $acl_group['id'],
			'acl_role_id' => $acl_role['value'],
			'is_active' => 1,
		];

		// Create the "ACL Entity Role".
		$acl_entity_role = $this->acl_entity_role_create( $params );
		if ( empty( $acl_entity_role ) ) {
			return false;
		}

		// Construct "name" for ACL.
		$name = sprintf(
			/* translators: %s: The name of the Member Group as shown in the description of the ACL */
			__( 'Edit Contacts in Group: %s', 'bp-groups-civicrm-sync' ),
			$member_group['title']
		);

		// Define params to create an "ACL".
		$params = [
			'object_id' => $member_group['id'],
			'entity_id' => $acl_role['value'],
			'operation' => 'Edit',
			'name' => $name,
			'is_active' => 1,
		];

		// Create the "ACL".
		$acl = $this->acl_create( $params );
		if ( empty( $acl ) ) {
			return false;
		}

		// Success.
		return true;

	}



	/**
	 * Updates the ACL for an ACL Group's permissions over a Member Group.
	 *
	 * This method now contains repair code to make sure that the ACLs for the
	 * Synced CiviCRM ACL Group are correct every time a BuddyPress Group is
	 * updated.
	 *
	 * The repair method does mean that each CiviCRM ACL Group can have one and
	 * only one ACL applied to it. This is probably fine but might need another
	 * look in future versions.
	 *
	 * @since 0.4
	 *
	 * @param array $acl_group The array of CiviCRM ACL Group data.
	 * @param array $member_group The array of CiviCRM Member Group data.
	 * @return bool True when all operations are successful, false otherwise.
	 */
	public function update_for_groups( $acl_group, $member_group ) {

		// First get the existing "ACL Role".
		$existing_acl_role = $this->acl_role_get( $acl_group['source'] );

		// Create one if none found.
		if ( empty( $existing_acl_role ) ) {

			// Define params to create an "ACL Role".
			$params = [
				'description' => $acl_group['source'],
				'label' => $acl_group['title'],
				'is_active' => 1,
			];

			// Create the "ACL Role".
			$acl_role = $this->acl_role_create( $params );

		} else {

			// Only the ACL Role "Label" needs to change.
			$params = [
				'id' => $existing_acl_role['id'],
				'label' => $acl_group['title'],
			];

			// Update the "ACL Role".
			$acl_role = $this->acl_role_update( $params );

			// Add the existing ACL Role value if missing.
			if ( empty( $acl_role['value'] ) ) {
				$acl_role['value'] = $existing_acl_role['value'];
			}

		}

		// Bail if no "ACL Role".
		if ( empty( $acl_role ) ) {
			return false;
		}

		/*
		 * It seems that previous versions of this plugin created corrupted
		 * "ACL Entity Roles" where the "ACL Role" is not set. Additionally,
		 * some Groups are missing their "ACL Entity Role" while others have
		 * multiple entries.
		 *
		 * Let's try and repair the situation by deleting all the existing
		 * "ACL Entity Roles" for the ACL Group and rebuilding with properly
		 * configured ones.
		 */

		// Get all existing "ACL Entity Roles".
		$existing_acl_entity_roles = $this->acl_entity_roles_get_for_group( $acl_group['id'] );

		// Create one if none found.
		if ( empty( $existing_acl_entity_roles ) ) {

			// Define params to create an "ACL Entity Role".
			$params = [
				'entity_id' => $acl_group['id'],
				'acl_role_id' => $acl_role['value'],
				'is_active' => 1,
			];

			// Create the "ACL Entity Role".
			$acl_entity_role = $this->acl_entity_role_create( $params );
			if ( empty( $acl_entity_role ) ) {
				return false;
			}

		} else {

			// Delete the ones that do not have the correct "ACL Role" value.
			$remaining = [];
			foreach( $existing_acl_entity_roles as $item ) {
				if ( (int) $item['acl_role_id'] !== (int) $acl_role['value'] ) {
					$this->acl_entity_role_delete( $item['id'] );
					continue;
				}
				$remaining[] = $item;
			}

			// Create one if there are none remaining.
			if ( empty( $remaining ) ) {

				// Define params to create an "ACL Entity Role".
				$params = [
					'entity_id' => $acl_group['id'],
					'acl_role_id' => $acl_role['value'],
					'is_active' => 1,
				];

				// Create the "ACL Entity Role".
				$acl_entity_role = $this->acl_entity_role_create( $params );
				if ( empty( $acl_entity_role ) ) {
					return false;
				}

			}

		}

		/*
		 * I can't see anything about the existing "ACL Entity Role" that needs
		 * to be altered. I've left the code to update it in here just in case.
		 */

		/*
		// Next get the existing "ACL Entity Role".
		$existing_acl_entity_role = $this->acl_entity_role_get( $acl_role['value'], $acl_group['id'] );
		if ( empty( $existing_acl_entity_role ) ) {
			return false;
		}

		// Define params to update an "ACL Entity Role".
		$params = [
			'id' => $existing_acl_entity_role['id'],
			'entity_id' => $acl_group['id'],
			'acl_role_id' => $acl_role['id'],
		];

		// Update the "ACL Entity Role".
		$acl_entity_role = $this->acl_entity_role_update( $params );
		if ( empty( $acl_entity_role ) ) {
			return false;
		}
		*/

		// Next get the existing "ACL".
		$existing_acl = $this->acl_get( $acl_role['value'], $member_group['id'] );

		// Construct "name" for ACL.
		$name = sprintf(
			/* translators: %s: The name of the Member Group as shown in the description of the ACL */
			__( 'Edit Contacts in Group: %s', 'bp-groups-civicrm-sync' ),
			$member_group['title']
		);

		// Create one if none found.
		if ( empty( $existing_acl ) ) {

			// Define params to create an "ACL".
			$params = [
				'object_id' => $member_group['id'],
				'entity_id' => $acl_role['value'],
				'operation' => 'Edit',
				'name' => $name,
				'is_active' => 1,
			];

			// Create the "ACL".
			$acl = $this->acl_create( $params );

		} else {

			// Only the ACL "Name" needs to change.
			$params = [
				'id' => $existing_acl['id'],
				'name' => $name,
			];

			// Update the "ACL".
			$acl = $this->acl_update( $params );

		}

		// Bail if something went wrong.
		if ( empty( $acl ) ) {
			return false;
		}

		// Success.
		return true;

	}



	/**
	 * Deletes the ACL for an ACL Group's permissions over a Member Group.
	 *
	 * @since 0.4
	 *
	 * @param array $acl_group The array of CiviCRM ACL Group data.
	 * @param array $member_group The array of CiviCRM Member Group data.
	 */
	public function delete_for_groups( $acl_group, $member_group ) {

		// Init return.
		$success = false;

		// First get the existing "ACL Role".
		$acl_role = $this->acl_role_get( $acl_group['source'] );

		// Next delete the existing "ACL".
		$acl = $this->acl_get( $acl_role['value'], $member_group['id'] );
		if ( ! empty( $acl ) ) {
			$acl_deleted = $this->acl_delete( $acl['id'] );
		}

		// Next delete the existing "ACL Entity Role".
		$acl_entity_role = $this->acl_entity_role_get( $acl_role['value'], $acl_group['id'] );
		if ( ! empty( $acl_entity_role ) ) {
			$acl_entity_role_deleted = $this->acl_entity_role_delete( $acl_entity_role['id'] );
		}

		// Lastly delete the existing "ACL Role".
		if ( ! empty( $acl_role ) ) {
			$acl_role_deleted = $this->acl_role_delete( $acl_role['id'] );
		}

		// Were all operations successful?
		if ( $acl_role_deleted && $acl_entity_role_deleted && $acl_deleted ) {
			$success = true;
		}

		// --<
		return $success;

	}



	// -------------------------------------------------------------------------



	/**
	 * Gets an "ACL Role" for a given "sync name".
	 *
	 * @since 0.4
	 *
	 * @param string $source The "sync name" of the CiviCRM Member Role data.
	 * @return array|bool $acl_role_data The array of ACL Role data from the CiviCRM API, or false on failure.
	 */
	public function acl_role_get( $source ) {

		// Init return.
		$acl_role_data = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $acl_role_data;
		}

		// Get the "ACL Roles" Option Group.
		$option_group = $this->option_group_get( 'acl_role' );
		if ( empty( $option_group ) ) {
			return $acl_role_data;
		}

		// Build params to get ACL Role.
		$params = [
			'version' => 3,
			'option_group_id' => $option_group['id'],
			'description' => $source,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'OptionValue', 'get', $params );

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
			return $acl_role_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $acl_role_data;
		}

		// The result set should contain only one item.
		$acl_role_data = array_pop( $result['values'] );

		// --<
		return $acl_role_data;

	}



	/**
	 * Creates an "ACL Role".
	 *
	 * What this actually does is it creates an Option Value in the "ACL Role"
	 * Option Group.
	 *
	 * @since 0.4
	 *
	 * @param array $acl_role The array of CiviCRM ACL Role data.
	 * @return array|bool $acl_role_data The array of ACL Role data from the CiviCRM API, or false on failure.
	 */
	public function acl_role_create( $acl_role ) {

		// Init return.
		$acl_role_data = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $acl_role_data;
		}

		// Get the "ACL Roles" Option Group.
		$option_group = $this->option_group_get( 'acl_role' );
		if ( empty( $option_group ) ) {
			return $acl_role_data;
		}

		// Build params to create ACL Role.
		$params = [
			'version' => 3,
			'option_group_id' => $option_group['id'],
		] + $acl_role;

		// Call the CiviCRM API.
		$result = civicrm_api( 'OptionValue', 'create', $params );

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
			return $acl_role_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $acl_role_data;
		}

		// The result set should contain only one item.
		$acl_role_data = array_pop( $result['values'] );

		// --<
		return $acl_role_data;

	}



	/**
	 * Updates a CiviCRM ACL Role with a given set of data.
	 *
	 * This is an alias of `self::acl_role_create()` except that we expect an
	 * Option Value ID to have been set in the ACL Role data.
	 *
	 * @since 0.4
	 *
	 * @param array $acl_role The CiviCRM ACL Role data.
	 * @return array|bool The array of ACL Role data from the CiviCRM API, or false on failure.
	 */
	public function acl_role_update( $acl_role ) {

		// Log and bail if there's no Option Value ID.
		if ( empty( $acl_role['id'] ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'message' => __( 'An ID must be present to update an ACL Role.', 'bp-groups-civicrm-sync' ),
					'acl_role' => $acl_role,
					'backtrace' => $trace,
				], true ) );
			}
			return false;
		}

		// Pass through.
		return $this->acl_role_create( $acl_role );

	}



	/**
	 * Deletes a CiviCRM "ACL Role" for a given ID.
	 *
	 * @since 0.4
	 *
	 * @param integer $acl_role_id The numeric ID of the CiviCRM ACL Role.
	 * @return bool $success True if the operation was successful, false on failure.
	 */
	public function acl_role_delete( $acl_role_id ) {

		// Init as failure.
		$success = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $success;
		}

		// Log and bail if there's no ACL Role ID.
		if ( empty( $acl_role_id ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'message' => __( 'An ID must be present to delete an ACL Role.', 'bp-groups-civicrm-sync' ),
					'backtrace' => $trace,
				], true ) );
			}
			return false;
		}

		// Build params to delete ACL Role.
		$params = [
			'version' => 3,
			'id' => $acl_role_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'OptionValue', 'delete', $params );

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
			return $success;
		}

		// Success.
		$success = true;

		// --<
		return $success;

	}



	// -------------------------------------------------------------------------



	/**
	 * Gets an "ACL Entity Role" for a given ACL Role and Synced ACL Group.
	 *
	 * Note that this works in API4 but fails in API3:
	 *
	 * @see https://lab.civicrm.org/dev/core/-/issues/2615
	 *
	 * Since we want to support CiviCRM versions prior to 5.39, we need to use
	 * the previous "direct" method.
	 *
	 * @since 0.4
	 *
	 * @param integer $acl_role_id The numeric "value" of the ACL Role.
	 * @param integer $acl_group_id The numeric ID of the Synced ACL Group.
	 * @return array|bool $acl_entity_role_data The array of ACL Entity Role data, or false on failure.
	 */
	public function acl_entity_role_get( $acl_role_id, $acl_group_id ) {

		// Init return.
		$acl_entity_role_data = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $acl_entity_role_data;
		}

		/*
		 * If the API4 Entity is available, use it.
		 *
		 * @see https://github.com/civicrm/civicrm-core/blob/master/Civi/Api4/ACLEntityRole.php#L17
		 */
		$version = CRM_Utils_System::version();
		if ( version_compare( $version, '5.39', '>=' ) ) {

			// Build params to get ACL Entity Role.
			$params = [
				'limit' => 0,
				'checkPermissions' => false,
			];
			$params['where'] = [
				[ 'acl_role_id', '=', (int) $acl_role_id ],
				[ 'entity_table', '=', 'civicrm_group' ],
				[ 'entity_id', '=', (int) $acl_group_id ],
			];

			// Call CiviCRM API4.
			$result = civicrm_api4( 'ACLEntityRole', 'get', $params );

			// Bail if there are no results.
			if ( empty( $result->count() ) ) {
				return $acl_entity_role_data;
			}

			// The result set should contain only one item.
			$acl_entity_role_data = $result->first();

		} else {

			// New instance of Entity Role class.
			$dao = new CRM_ACL_DAO_EntityRole();

			// Find by using properties.
			$dao->acl_role_id = (int) $acl_role_id;
			$dao->entity_table = 'civicrm_group';
			$dao->entity_id = (int) $acl_group_id;
			$dao->find( true );

			// Bail if there are no results.
			if ( empty( $dao->id ) ) {
				return $acl_entity_role_data;
			}

			// Build array of results.
			$acl_entity_role_data = [
				'id' => $dao->id,
				'acl_role_id' => $dao->acl_role_id,
				'entity_table' => 'civicrm_group',
				'entity_id' => $dao->entity_id,
			];

		}

		// --<
		return $acl_entity_role_data;

	}



	/**
	 * Gets all "ACL Entity Roles" for a given Synced ACL Group.
	 *
	 * @since 0.4
	 *
	 * @param integer $acl_group_id The numeric ID of the Synced ACL Group.
	 * @return array|bool $acl_entity_role_data The array of ACL Entity Role data, or false on failure.
	 */
	public function acl_entity_roles_get_for_group( $acl_group_id ) {

		// Init return.
		$acl_entity_role_data = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $acl_entity_role_data;
		}

		/*
		 * If the API4 Entity is available, use it.
		 *
		 * @see https://github.com/civicrm/civicrm-core/blob/master/Civi/Api4/ACLEntityRole.php#L17
		 */
		$version = CRM_Utils_System::version();
		//if ( version_compare( $version, '5.39', '>=' ) ) {
		if ( 1 === 2 ) {

			// Build params to get ACL Entity Role.
			$params = [
				'limit' => 0,
				'checkPermissions' => false,
			];
			$params['where'] = [
				[ 'entity_table', '=', 'civicrm_group' ],
				[ 'entity_id', '=', (int) $acl_group_id ],
			];

			// Call CiviCRM API4.
			$result = civicrm_api4( 'ACLEntityRole', 'get', $params );

			// Bail if there are no results.
			if ( empty( $result->count() ) ) {
				return $acl_entity_role_data;
			}

			// Add the results to the return array.
			$acl_entity_role_data = [];
			foreach ( $result as $item ) {
				$acl_entity_role_data[] = $item;
			}

		} else {

			// New instance of Entity Role class.
			$dao = new CRM_ACL_DAO_EntityRole();

			// Find by using properties.
			$dao->entity_table = 'civicrm_group';
			$dao->entity_id = (int) $acl_group_id;

			// Add the results to the return array.
			if ( $dao->find() ) {
				$acl_entity_role_data = [];
				while ( $dao->fetch() ) {
					$acl_entity_role_data[] = [
						'id' => $dao->id,
						'acl_role_id' => $dao->acl_role_id,
						'entity_table' => 'civicrm_group',
						'entity_id' => $dao->entity_id,
					];
				}
			}

		}

		// --<
		return $acl_entity_role_data;

	}



	/**
	 * Creates an "ACL Entity Role".
	 *
	 * Note that this works in API4 but fails in API3:
	 *
	 * @see https://lab.civicrm.org/dev/core/-/issues/2615
	 *
	 * Since we want to support CiviCRM versions prior to 5.39, we need to use
	 * the previous "direct" method.
	 *
	 * @since 0.4
	 *
	 * @param array $acl_entity_role The array of CiviCRM ACL Entity Role data.
	 * @return array|bool The array of ACL Entity Role data from the CiviCRM API, or false on failure.
	 */
	public function acl_entity_role_create( $acl_entity_role ) {

		// Init return.
		$acl_entity_role_data = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $acl_entity_role_data;
		}

		/*
		 * If the API4 Entity is available, use it.
		 *
		 * @see https://github.com/civicrm/civicrm-core/blob/master/Civi/Api4/ACLEntityRole.php#L17
		 */
		$version = CRM_Utils_System::version();
		if ( version_compare( $version, '5.39', '>=' ) ) {

			// Build params to create ACL Entity Role.
			$params = [
				'checkPermissions' => false,
			];
			$params['values'] = [
				'entity_table' => 'civicrm_group',
			] + $acl_entity_role;

			// Call CiviCRM API4.
			$result = civicrm_api4( 'ACLEntityRole', 'create', $params );

			// Bail if there are no results.
			if ( empty( $result->count() ) ) {
				return $acl_entity_role_data;
			}

			// The result set should contain only one item.
			$acl_entity_role_data = $result->first();

		} else {

			// Init transaction.
			$transaction = new CRM_Core_Transaction();

			// New instance of Entity Role class.
			$dao = new CRM_ACL_DAO_EntityRole();

			// Add properties.
			$dao->entity_table = 'civicrm_group';
			$dao->entity_id = $acl_entity_role['entity_id'];
			$dao->acl_role_id = $acl_entity_role['acl_role_id'];
			$dao->is_active = true;

			// Trigger an update if the ID is known.
			if ( ! empty( $acl_entity_role['id'] ) ) {
				$dao->id = (int) $acl_entity_role['id'];
			}

			$dao->save();

			// Do the database transaction.
			$transaction->commit();

			// Build array of results.
			$acl_entity_role_data = [
				'id' => $dao->id,
				'acl_role_id' => $acl_entity_role['acl_role_id'],
				'entity_table' => 'civicrm_group',
				'entity_id' => $acl_entity_role['entity_id'],
				'is_active' => 1,
			];

		}

		// --<
		return $acl_entity_role_data;

	}



	/**
	 * Updates a CiviCRM "ACL Entity Role" with a given set of data.
	 *
	 * This is an alias of `self::acl_entity_role_create()` except that we expect
	 * an Option Value ID to have been set in the ACL Entity Role data.
	 *
	 * @since 0.4
	 *
	 * @param array $acl_entity_role The array of CiviCRM ACL Entity Role data.
	 * @return array|bool The array of ACL Entity Role data from the CiviCRM API, or false on failure.
	 */
	public function acl_entity_role_update( $acl_entity_role ) {

		// Log and bail if there's no Option Value ID.
		if ( empty( $acl_entity_role['id'] ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'message' => __( 'An ID must be present to update an ACL Entity Role.', 'bp-groups-civicrm-sync' ),
					'acl_entity_role' => $acl_entity_role,
					'backtrace' => $trace,
				], true ) );
			}
			return false;
		}

		// Pass through.
		return $this->acl_entity_role_create( $acl_entity_role );

	}



	/**
	 * Deletes a CiviCRM "ACL Entity Role" for a given ID.
	 *
	 * @since 0.4
	 *
	 * @param integer $acl_entity_role_id The numeric ID of the CiviCRM ACL Entity Role.
	 * @return bool $success True if the operation was successful, false on failure.
	 */
	public function acl_entity_role_delete( $acl_entity_role_id ) {

		// Init as failure.
		$success = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $success;
		}

		// Log and bail if there's no ACL Entity Role ID.
		if ( empty( $acl_entity_role_id ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'message' => __( 'An ID must be present to delete an ACL Entity Role.', 'bp-groups-civicrm-sync' ),
					'backtrace' => $trace,
				], true ) );
			}
			return false;
		}

		// Build params to delete ACL Entity Role.
		$params = [
			'version' => 3,
			'id' => $acl_entity_role_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'AclRole', 'delete', $params );

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
			return $success;
		}

		// Success.
		$success = true;

		// --<
		return $success;

	}



	// -------------------------------------------------------------------------



	/**
	 * Gets an "ACL" for a given set of data.
	 *
	 * @since 0.4
	 *
	 * @param integer $acl_role_id The numeric "value" of the ACL Role.
	 * @param integer $member_group_id The numeric ID of the Synced Member Group.
	 * @return array|bool $acl_data The array of ACL data from the CiviCRM API, or false on failure.
	 */
	public function acl_get( $acl_role_id, $member_group_id ) {

		// Init return.
		$acl_data = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $acl_data;
		}

		// Build params to get ACL.
		$params = [
			'version' => 3,
			'object_table' => 'civicrm_saved_search',
			'object_id' => $member_group_id,
			'entity_table' => 'civicrm_acl_role',
			'entity_id' => $acl_role_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'Acl', 'get', $params );

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
			return $acl_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $acl_data;
		}

		// The result set should contain only one item.
		$acl_data = array_pop( $result['values'] );

		// --<
		return $acl_data;

	}



	/**
	 * Creates an "ACL".
	 *
	 * @since 0.4
	 *
	 * @param array $acl The array of CiviCRM ACL data.
	 * @return array|bool $acl_data The array of ACL data from the CiviCRM API, or false on failure.
	 */
	public function acl_create( $acl ) {

		// Init return.
		$acl_data = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $acl_data;
		}

		// Build params to create ACL.
		$params = [
			'version' => 3,
			'object_table' => 'civicrm_saved_search',
			'entity_table' => 'civicrm_acl_role',
		] + $acl;

		// Call the CiviCRM API.
		$result = civicrm_api( 'Acl', 'create', $params );

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
			return $acl;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $acl_data;
		}

		// The result set should contain only one item.
		$acl_data = array_pop( $result['values'] );

		// --<
		return $acl_data;

	}



	/**
	 * Updates a CiviCRM ACL with a given set of data.
	 *
	 * This is an alias of `self::acl_create()` except that we expect an ID to
	 * have been set in the ACL data.
	 *
	 * @since 0.4
	 *
	 * @param array $acl The array of CiviCRM ACL data.
	 * @return array|bool The array of ACL data from the CiviCRM API, or false on failure.
	 */
	public function acl_update( $acl ) {

		// Log and bail if there's no Option Value ID.
		if ( empty( $acl['id'] ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'message' => __( 'An ID must be present to update an ACL.', 'bp-groups-civicrm-sync' ),
					'acl' => $acl,
					'backtrace' => $trace,
				], true ) );
			}
			return false;
		}

		// Pass through.
		return $this->acl_create( $acl );

	}



	/**
	 * Deletes a CiviCRM "ACL" for a given ID.
	 *
	 * @since 0.4
	 *
	 * @param integer $acl_id The numeric ID of the CiviCRM ACL Role.
	 * @return bool $success True if the operation was successful, false on failure.
	 */
	public function acl_delete( $acl_id ) {

		// Init as failure.
		$success = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $success;
		}

		// Log and bail if there's no ACL ID.
		if ( empty( $acl_id ) ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'message' => __( 'An ID must be present to delete an ACL.', 'bp-groups-civicrm-sync' ),
					'backtrace' => $trace,
				], true ) );
			}
			return false;
		}

		// Build params to delete ACL.
		$params = [
			'version' => 3,
			'id' => $acl_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'Acl', 'delete', $params );

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
			return $success;
		}

		// Success.
		$success = true;

		// --<
		return $success;

	}



	// -------------------------------------------------------------------------



	/**
	 * Gets a CiviCRM Option Group by name.
	 *
	 * @since 0.4
	 *
	 * @param string $name The name of the Option Group.
	 * @return array $option_group The array of Option Group data.
	 */
	public function option_group_get( $name ) {

		// Only do this once per named Option Group.
		static $pseudocache;
		if ( isset( $pseudocache[ $name ] ) ) {
			return $pseudocache[ $name ];
		}

		// Init return.
		$options = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $options;
		}

		// Define query params.
		$params = [
			'version' => 3,
			'name' => $name,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'OptionGroup', 'get', $params );

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
			return $options;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $options;
		}

		// The result set should contain only one item.
		$options = array_pop( $result['values'] );

		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[ $name ] ) ) {
			$pseudocache[ $name ] = $options;
		}

		// --<
		return $options;

	}



} // Class ends.
