<?php
/**
 * CiviCRM Meta Group Class.
 *
 * Handles functionality related to the CiviCRM Meta Group.
 *
 * @package BP_Groups_CiviCRM_Sync
 * @since 0.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * BP Groups CiviCRM Sync CiviCRM Meta Group Class.
 *
 * A class that encapsulates functionality related to the CiviCRM Meta Group.
 *
 * @since 0.4
 */
class BP_Groups_CiviCRM_Sync_CiviCRM_Group_Meta {

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
	 * BuddyPress utilities object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $bp The BuddyPress utilities object.
	 */
	public $bp;

	/**
	 * Admin utilities object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $admin The Admin utilities object.
	 */
	public $admin;

	/**
	 * Meta Group "Sync name" for Group "source" field.
	 *
	 * @since 0.4
	 * @access public
	 * @var string $sync_name The Meta Group "Sync name" for Group "source" field.
	 */
	public $sync_name = 'bp-groups-civicrm-sync';



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
		do_action( 'bpgcs/civicrm/group/meta/loaded' );

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
	 * Get source (our unique code) for our Meta Group.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class.
	 *
	 * @return string The unique Meta Group code.
	 */
	public function get_source() {
		return $this->sync_name;
	}



	/**
	 * Creates the CiviCRM Group which is the ultimate parent for all BuddyPress Groups.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class.
	 *
	 * @return int|bool The CiviCRM Group ID, or false on failure.
	 */
	public function group_create() {

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return false;
		}

		// Get the ID of the CiviCRM Meta Group.
		$source = $this->get_source();
		$meta_group_id = $this->civicrm->group_id_find( $source );

		// Return it if it already exists.
		if ( ! empty( $meta_group_id ) ) {
			return $meta_group_id;
		}

		// Define Group.
		$params = [
			'name' => __( 'BuddyPress Groups', 'bp-groups-civicrm-sync' ),
			'title' => __( 'BuddyPress Groups', 'bp-groups-civicrm-sync' ),
			'description' => __( 'Container for all BuddyPress Groups.', 'bp-groups-civicrm-sync' ),
			'is_active' => 1,
		];

		// Set inscrutable Group Type (Access Control).
		$params['group_type'] = [ '1' => 1 ];

		// Set "source" for the CiviCRM Group.
		$params['source'] = $source;

		// Create the CiviCRM Group.
		$group = $this->civicrm->group_create( $params );

		// Bail on failure.
		if ( $group === false ) {
			return false;
		}

		// --<
		return $group['id'];

	}



	/**
	 * Deletes the CiviCRM Group which is the ultimate parent for all BuddyPress Groups.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class.
	 *
	 * @return bool True if the CiviCRM Group is deleted, or false on failure.
	 */
	public function group_delete() {

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return false;
		}

		// Get the ID of the CiviCRM Meta Group.
		$source = $this->get_source();
		$meta_group_id = $this->civicrm->group_id_find( $source );

		// Return early if it does not exist.
		if ( empty( $meta_group_id ) ) {
			return true;
		}

		// --<
		return $this->civicrm->group_delete( $meta_group_id );

	}



	/**
	 * Assign all Synced CiviCRM Groups with no parent to our Meta Group.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class.
	 */
	public function groups_assign() {

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return;
		}

		// Get the ID of the CiviCRM Meta Group.
		$source = $this->get_source();
		$meta_group_id = $this->civicrm->group_id_find( $source );
		if ( $meta_group_id === false ) {
			return;
		}

		// Get all Groups.
		$groups = $this->civicrm->groups_get_all();
		if ( empty( $groups ) ) {
			return;
		}

		// Loop.
		foreach ( $groups as $group ) {

			// Sanity check.
			if ( empty( $group['id'] ) ) {
				continue;
			}

			// Exclude the container Group.
			if ( (int) $group['id'] === (int) $meta_group_id ) {
				continue;
			}

			// Skip if it's not a BuddyPress Sync Group.
			if ( ! $this->civicrm->has_bp_group( $group ) ) {
				continue;
			}

			// Skip if the Group has a parent.
			if ( ! empty( $group['parents'] ) ) {
				continue;
			}

			// Get the Group Type string.
			$group_type = $this->civicrm->group_type_get_by_source( $group['source'] );

			// Set BuddyPress Group to empty to trigger assignment to Meta Group.
			$bp_parent_id = 0;

			// Get the CiviCRM Group ID for the BuddyPress Parent Group.
			$civicrm_parent_id = $this->civicrm->group_nesting->parent_id_get( $bp_parent_id, $group_type );

			// Maybe create nesting.
			$this->civicrm->group_nesting->nesting_create( $group['id'], $civicrm_parent_id );

			// Preserve CiviCRM Group Type.
			$group['group_type'] = $this->civicrm->group_type_array_get_by_type( $group_type );

			// Update the Group.
			$success = $this->civicrm->group_update( $group );

		}

	}



	/**
	 * Remove all top-level Synced CiviCRM Groups from the Meta Group.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class.
	 */
	public function groups_remove() {

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return;
		}

		// Get the ID of the CiviCRM Meta Group.
		$source = $this->get_source();
		$meta_group_id = $this->civicrm->group_id_find( $source );
		if ( $meta_group_id === false ) {
			return;
		}

		// Get all Groups.
		$groups = $this->civicrm->groups_get_all();
		if ( empty( $groups ) ) {
			return;
		}

		// Loop.
		foreach ( $groups as $group ) {

			// Sanity check.
			if ( empty( $group['id'] ) ) {
				continue;
			}

			// Skip the container Group.
			if ( (int) $group['id'] === (int) $meta_group_id ) {
				continue;
			}

			// Skip if it's not a BuddyPress Sync Group.
			if ( ! $this->civicrm->has_bp_group( $group ) ) {
				continue;
			}

			// Skip when there is no parent.
			if ( empty( $group['parents'] ) ) {
				continue;
			}

			// Check if there are multiple parents.
			if ( strstr( $group['parents'], ',' ) ) {
				$group['parents'] = explode( ',', $group['parents'] );
				array_walk( $group['parents'], function( &$item ) {
					$item = (int) trim( $item );
				} );
			} else {
				$group['parents'] = [ (int) $group['parents'] ];
			}

			// Skip if the parent is not the container Group.
			if ( ! in_array( (int) $meta_group_id, $group['parents'] ) ) {
				continue;
			}

			// Delete Meta Group nesting.
			$this->civicrm->group_nesting->nesting_delete( $group['id'], $meta_group_id );

			// Get Group Type.
			$group_type = $this->civicrm->group_type_get_by_source( $group['source'] );

			// Preserve CiviCRM Group Type.
			$group['group_type'] = $this->civicrm->group_type_array_get_by_type( $group_type );

			// Remove the container Group ID from parents.
			$parents = array_diff( $group['parents'], [ (int) $meta_group_id ] );
			$group['parents'] = implode( ',', $parents );

			// Update the Group.
			$success = $this->civicrm->group_update( $group );

		}

	}



} // Class ends.
