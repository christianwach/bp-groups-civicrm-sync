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
	 * @var object $parent_obj The plugin object.
	 */
	public $parent_obj;

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

		// Add actions for plugin init on CiviCRM init.
		add_action( 'civicrm_instance_loaded', [ $this, 'register_hooks' ] );

	}



	/**
	 * Set references to other objects.
	 *
	 * @since 0.1
	 *
	 * @param object $bp_object Reference to this plugin's BuddyPress object.
	 * @param object $admin_object Reference to this plugin's Admin object.
	 */
	public function set_references( &$bp_object, &$admin_object ) {

		// Store BuddyPress reference.
		$this->bp = $bp_object;

		// Store Admin reference.
		$this->admin = $admin_object;

	}



	/**
	 * Register hooks on plugin init.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// Allow plugins to register php and template directories.
		add_action( 'civicrm_config', [ $this, 'register_directories' ], 10, 1 );

		// Intercept CiviCRM Group create form.
		//add_action( 'civicrm_buildForm', [ $this, 'form_create_bp_group_options' ], 10, 2 );

		// Intercept CiviCRM Group create form submission.
		//add_action( 'civicrm_postProcess', [ $this, 'form_create_bp_group_process' ], 10, 2 );

		// Intercept CiviCRM Drupal Organic Groups edit form.
		add_action( 'civicrm_buildForm', [ $this, 'form_edit_og_options' ], 10, 2 );

		// Intercept CiviCRM Drupal Organic Groups edit form submission.
		add_action( 'civicrm_postProcess', [ $this, 'form_edit_og_process' ], 10, 2 );

		// Intercept CiviCRM's add Contacts to Group.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_added' ], 10, 4 );

		// Intercept CiviCRM's delete Contacts from Group.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_deleted' ], 10, 4 );

		// Intercept CiviCRM's rejoin Contacts to Group.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_rejoined' ], 10, 4 );

		/**
		 * Broadcast that this object is loaded.
		 *
		 * @since 0.1
		 */
		do_action( 'bp_groups_civicrm_sync_civi_loaded' );

	}



	/**
	 * Test if CiviCRM plugin is active.
	 *
	 * @since 0.1
	 *
	 * @return bool True if successfully initialised, false otherwise.
	 */
	public function is_active() {

		// Bail if no CiviCRM init function.
		if ( ! function_exists( 'civi_wp' ) ) {
			return false;
		}

		// Bail if CiviCRM is not fully installed.
		if ( ! defined( 'CIVICRM_INSTALLED' ) ) {
			return false;
		}
		if ( ! CIVICRM_INSTALLED ) {
			return false;
		}

		// Try and init CiviCRM.
		return civi_wp()->initialize();

	}



	/**
	 * Register directories that CiviCRM searches for php and template files.
	 *
	 * @since 0.1
	 *
	 * @param object $config The CiviCRM config object.
	 */
	public function register_directories( &$config ) {

		// Define our custom path.
		$custom_path = BP_GROUPS_CIVICRM_SYNC_PATH . 'assets/civicrm/custom_templates';

		// Kick out if no CiviCRM.
		if ( ! civi_wp()->initialize() ) {
			return;
		}

		// Get template instance.
		$template = CRM_Core_Smarty::singleton();

		// Add our custom template directory.
		$template->addTemplateDir( $custom_path );

		// Register template directories.
		$template_include_path = $custom_path . PATH_SEPARATOR . get_include_path();
		set_include_path( $template_include_path );

	}



	// -------------------------------------------------------------------------



	/**
	 * Creates the CiviCRM Group which is the ultimate parent for all BuddyPress Groups.
	 *
	 * @since 0.1
	 */
	public function meta_group_create() {

		// Bail if CiviCRM is not active.
		if ( ! $this->is_active() ) {
			return;
		}

		// Init transaction.
		$transaction = new CRM_Core_Transaction();

		// Get the Group ID of the CiviCRM Meta Group.
		$civi_meta_group_id = $this->find_group_id(
			$this->meta_group_get_source()
		);

		// Skip if it already exists.
		if ( is_numeric( $civi_meta_group_id ) && $civi_meta_group_id > 0 ) {

			// Skip.

		} else {

			// Define Group.
			$params = [
				'name' => __( 'BuddyPress Groups', 'bp-groups-civicrm-sync' ),
				'title' => __( 'BuddyPress Groups', 'bp-groups-civicrm-sync' ),
				'description' => __( 'Container for all BuddyPress Groups', 'bp-groups-civicrm-sync' ),
				'is_active' => 1,
			];

			// Set inscrutable Group Type (Access Control).
			$params['group_type'] = [ '1' => 1 ];

			// Get "source" for the CiviCRM Group.
			$params['source'] = $this->meta_group_get_source();

			// Use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup().
			$this->create_group( $params );

		}

		// Assign Groups with no parent to the Meta Group.
		//$this->meta_group_groups_assign();

		// Do the database transaction.
		$transaction->commit();

	}



	/**
	 * Deletes the CiviCRM Group which is the ultimate parent for all BuddyPress Groups.
	 *
	 * @since 0.1
	 */
	public function meta_group_delete() {

		// Init or die.
		if ( ! $this->is_active() ) {
			return;
		}

		// Get the Group ID of the CiviCRM Meta Group.
		$civi_meta_group_id = $this->find_group_id(
			$this->meta_group_get_source()
		);

		// If it exists.
		if ( is_numeric( $civi_meta_group_id ) && $civi_meta_group_id > 0 ) {

			// Init transaction.
			$transaction = new CRM_Core_Transaction();

			// Delete Group.
			CRM_Contact_BAO_Group::discard( $civi_meta_group_id );

			// Do the database transaction.
			$transaction->commit();

		}

	}



	/**
	 * Assign all CiviCRM Groups with no parent to our Meta Group.
	 *
	 * @since 0.1
	 */
	public function meta_group_groups_assign() {

		// Init or die.
		if ( ! $this->is_active() ) {
			return;
		}

		// Get the Group ID of the CiviCRM Meta Group.
		$civi_meta_group_id = $this->find_group_id(
			$this->meta_group_get_source()
		);

		// Bail on failure.
		if ( ! $civi_meta_group_id ) {
			return;
		}

		// Get all Groups.
		$all_groups = $this->get_all_civi_groups();

		// Bail if there are no results.
		if ( empty( $all_groups ) ) {
			return;
		}

		// Loop.
		foreach ( $all_groups as $group ) {

			// Skip if there is a parent.
			if ( empty( $group['parents'] ) ) {
				continue;
			}

			// Set BuddyPress Group to empty, which triggers assignment to Meta Group.
			$bp_parent_id = 0;

			// Exclude container Group.
			if ( isset( $group['id'] ) && $group['id'] == $civi_meta_group_id ) {
				continue;
			}

			// If "source" is not present, it's not an OG/BuddyPress Group.
			if ( ! isset( $group['source'] ) || is_null( $group['source'] ) ) {
				continue;
			}

			// Get Group Type.
			$group_type = $this->civi_group_get_code_by_source( $group['source'] );

			// Get CiviCRM Group ID for BuddyPress Parent Group.
			$civi_parent_id = $this->get_civi_parent_group_id( $bp_parent_id, $group_type );

			// Maybe create nesting.
			$this->group_nesting_create( $group['id'], $civi_parent_id, $group_type );

			// Retain CiviCRM Group Type.
			$group['group_type'] = $this->civi_group_get_type_by_code( $group_type );

			// Update the Group.
			$success = $this->update_group( $group );

		}

	}



	/**
	 * Remove all top-level CiviCRM Groups from the Meta Group.
	 *
	 * @since 0.1
	 */
	public function meta_group_groups_remove() {

		// Init or die.
		if ( ! $this->is_active() ) {
			return;
		}

		// Get the Group ID of the CiviCRM Meta Group.
		$civi_meta_group_id = $this->find_group_id(
			$this->meta_group_get_source()
		);

		// Bail on failure.
		if ( ! $civi_meta_group_id ) {
			return;
		}

		// Get all Groups.
		$all_groups = $this->get_all_civi_groups();

		// Bail if there are no results.
		if ( empty( $all_groups ) ) {
			return;
		}

		// Loop.
		foreach ( $all_groups as $group ) {

			// Skip when there is no parent.
			if ( empty( $group['parents'] ) ) {
				continue;
			}

			// If "source" is not present, it's not an OG/BuddyPress Group.
			if ( ! isset( $group['source'] ) || is_null( $group['source'] ) ) {
				continue;
			}

			// Skip if the parent is not the container Group.
			if ( $group['parents'] != $civi_meta_group_id ) {
				continue;
			}

			// Delete nesting.
			$this->group_nesting_delete( $group['id'], $group['parents'] );

			// Get Group Type.
			$group_type = $this->civi_group_get_code_by_source( $group['source'] );

			// Retain CiviCRM Group Type.
			$group['group_type'] = $this->civi_group_get_type_by_code( $group_type );

			// Clear parents.
			$group['parents'] = null;

			// Update the Group.
			$success = $this->update_group( $group );

		}

	}



	/**
	 * Get source (our unique code) for our Meta Group.
	 *
	 * @since 0.1
	 *
	 * @return string The unique Meta Group code.
	 */
	public function meta_group_get_source() {

		// Define code.
		return 'bp-groups-civicrm-sync';

	}



	// -------------------------------------------------------------------------



	/**
	 * Creates a CiviCRM Group when a BuddyPress Group is created.
	 *
	 * @since 0.1
	 *
	 * @param int $bp_group_id The numeric ID of the BuddyPress Group.
	 * @param object $bp_group The BuddyPress Group object.
	 * @return array|bool $return Associative array of CiviCRM Group IDs, or false on failure.
	 */
	public function create_civi_group( $bp_group_id, $bp_group ) {

		// Are we overriding this?
		if ( $this->do_not_sync ) {
			return false;
		}

		// Bail if no CiviCRM.
		if ( ! $this->is_active() ) {
			return false;
		}

		// Init return.
		$return = [];

		// Init transaction.
		$transaction = new CRM_Core_Transaction();

		// Define Group.
		$params = [
			'bp_group_id' => $bp_group_id,
			'name' => stripslashes( $bp_group->name ),
			'title' => stripslashes( $bp_group->name ),
			'description' => stripslashes( $bp_group->description ),
			'is_active' => 1,
		];

		// First create the CiviCRM Group.
		$group_params = $params;

		// Get name for the CiviCRM Group.
		$group_params['source'] = $this->member_group_get_sync_name( $bp_group_id );

		// Define CiviCRM Group Type (Mailing List by default).
		$group_params['group_type'] = $this->civi_group_get_type_by_code( 'member' );

		// Use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup().
		$success = $this->create_group( $group_params );

		// Store ID of created CiviCRM Group if successful.
		if ( $success ) {
			$return['member_group_id'] = $group_params['group_id'];
		} else {
			$return['member_group_id'] = 0;
		}

		// Next create the CiviCRM ACL Group.
		$acl_params = $params;

		// Set name and title of ACL Group.
		$acl_params['name'] .= ': Administrator';
		$acl_params['title'] = $acl_params['name'];

		// Set source name for ACL Group.
		$acl_params['source'] = $this->acl_group_get_sync_name( $bp_group_id );

		// Set inscrutable Group Type (Access Control).
		$acl_params['group_type'] = $this->civi_group_get_type_by_code( 'acl' );

		// Create the ACL Group too.
		$success = $this->create_group( $acl_params );

		// If successful.
		if ( $success ) {

			// Set some further params.
			$acl_params['acl_group_id'] = $acl_params['group_id'];
			$acl_params['civicrm_group_id'] = $group_params['group_id'];

			// Use cloned CiviCRM function.
			$this->update_civi_acl_tables( $acl_params, 'add' );

			// Store ID of created CiviCRM Group.
			$return['acl_group_id'] = $acl_params['group_id'];

		} else {
			$return['acl_group_id'] = 0;
		}

		// Create nesting with no parent.
		$this->group_nesting_update( $bp_group_id, 0 );

		// Do the database transaction.
		$transaction->commit();

		// Add the creator to the Groups.
		$params = [
			'bp_group_id' => $bp_group_id,
			'uf_id' => $bp_group->creator_id,
			'is_active' => 1,
			'is_admin' => 1,
		];

		// Use clone of CRM_Bridge_OG_Drupal::og().
		$this->group_contact_sync( $params, 'add' );

		// --<
		return $return;

	}



	/**
	 * Updates a CiviCRM Group when a BuddyPress Group is updated.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 * @param object $group The BuddyPress Group object.
	 */
	public function update_civi_group( $group_id, $group ) {

		// Are we overriding this?
		if ( $this->do_not_sync ) {
			return false;
		}

		// Init or die.
		if ( ! $this->is_active() ) {
			return false;
		}

		// Init return.
		$return = [];

		// Init transaction.
		$transaction = new CRM_Core_Transaction();

		// Define Group.
		$params = [
			'bp_group_id' => $group_id,
			'name' => stripslashes( $group->name ),
			'title' => stripslashes( $group->name ),
			'description' => stripslashes( $group->description ),
			'is_active' => 1,
		];

		// First update the CiviCRM Group.
		$group_params = $params;

		// Get name for the CiviCRM Group.
		$group_params['source'] = $this->member_group_get_sync_name( $group_id );

		// Define CiviCRM Group Type (Mailing List by default).
		$group_params['group_type'] = $this->civi_group_get_type_by_code( 'member' );

		// Use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup().
		$success = $this->update_group( $group_params );

		// Store ID of updated CiviCRM Group if successful.
		if ( $success ) {
			$return['member_group_id'] = $group_params['group_id'];
		} else {
			$return['member_group_id'] = 0;
		}

		// Next update the CiviCRM ACL Group.
		$acl_params = $params;

		// Set name and title of ACL Group.
		$acl_params['name'] .= ': Administrator';
		$acl_params['title'] = $acl_params['name'];

		// Set source name for ACL Group.
		$acl_params['source'] = $this->acl_group_get_sync_name( $group_id );

		// Set inscrutable Group Type (Access Control).
		$acl_params['group_type'] = $this->civi_group_get_type_by_code( 'acl' );

		// Update the ACL Group too.
		$success = $this->update_group( $acl_params );

		// If successful.
		if ( $success ) {

			// Set some further params.
			$acl_params['acl_group_id'] = $acl_params['group_id'];
			$acl_params['civicrm_group_id'] = $group_params['group_id'];

			// Use cloned CiviCRM function.
			$this->update_civi_acl_tables( $acl_params, 'update' );

			// Store ID of created CiviCRM Group.
			$return['acl_group_id'] = $acl_params['group_id'];

		} else {
			$return['acl_group_id'] = 0;
		}

		// Do the database transaction.
		$transaction->commit();

		// --<
		return $return;

	}



	/**
	 * Deletes a CiviCRM Group when a BuddyPress Group is deleted.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the BuddyPress Group.
	 */
	public function delete_civi_group( $group_id ) {

		// Are we overriding this?
		if ( $this->do_not_sync ) {
			return;
		}

		// Init or die.
		if ( ! $this->is_active() ) {
			return;
		}

		// Init transaction.
		$transaction = new CRM_Core_Transaction();

		// Get the Group object.
		$group = groups_get_group( [ 'group_id' => $group_id ] );

		// Define Group.
		$params = [
			'bp_group_id' => $group_id,
			'name' => $group->name,
			'title' => $group->name,
			'is_active' => 1,
		];

		// First delete the CiviCRM Group.
		$group_params = $params;

		// Get name for the CiviCRM Group.
		$group_params['source'] = $this->member_group_get_sync_name( $group_id );

		// Use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup().
		$this->delete_group( $group_params );

		// Next delete the CiviCRM ACL Group.
		$acl_params = $params;

		// Set name and title of ACL Group.
		$acl_params['name'] .= ': Administrator';
		$acl_params['title'] = $acl_params['name'];

		// Set source name for ACL Group.
		$acl_params['source'] = $this->acl_group_get_sync_name( $group_id );

		// Delete the ACL Group too.
		$this->delete_group( $acl_params );

		// Set some further params.
		$acl_params['acl_group_id'] = $acl_params['group_id'];
		$acl_params['civicrm_group_id'] = $group_params['group_id'];

		// Use cloned CiviCRM function.
		$this->update_civi_acl_tables( $acl_params, 'delete' );

		// Do the database transaction.
		$transaction->commit();

	}



	// -------------------------------------------------------------------------



	/**
	 * Create all CiviCRM Group Nestings.
	 *
	 * @since 0.1
	 */
	public function group_hierarchy_build() {

		// Init or die.
		if ( ! $this->is_active() ) {
			return;
		}

		// Get tree.
		$tree = BP_Groups_Hierarchy::get_tree();

		// Bail if we don't get one.
		if ( empty( $tree ) ) {
			return;
		}

		// Loop through tree.
		foreach ( $tree as $bp_group ) {

			// Update the nesting.
			$this->group_nesting_update( $bp_group->id, $bp_group->parent_id );

		}

	}



	/**
	 * Delete all CiviCRM Group Nestings.
	 *
	 * @since 0.1
	 */
	public function group_hierarchy_collapse() {

		// Init or die.
		if ( ! $this->is_active() ) {
			return;
		}

		// Get tree.
		$tree = BP_Groups_Hierarchy::get_tree();

		// Bail if we don't get one.
		if ( empty( $tree ) ) {
			return;
		}

		// Loop through tree.
		foreach ( $tree as $bp_group ) {

			// Collapse nesting by assigning all to Meta Group.
			$this->group_nesting_update( $bp_group->id, 0 );

		}

	}



	/**
	 * Create a CiviCRM Group Nesting.
	 *
	 * @since 0.1
	 *
	 * @param int $civi_group_id The numeric ID of the CiviCRM Group.
	 * @param int $civi_parent_id The numeric ID of the parent CiviCRM Group.
	 * @param string $source Group update type - 'member' or 'acl'.
	 */
	public function group_nesting_create( $civi_group_id, $civi_parent_id, $source ) {

		// Bail if no parent set.
		if ( empty( $civi_parent_id ) || ! is_numeric( $civi_parent_id ) ) {
			return;
		}

		// Define new Group Nesting.
		$params = [
			'version' => 3,
			'child_group_id' => $civi_group_id,
			'parent_group_id' => $civi_parent_id,
		];

		// Create CiviCRM Group Nesting under Meta Group.
		$result = civicrm_api( 'GroupNesting', 'create', $params );

		// Log if there's an error.
		if ( ! empty( $result['is_error'] ) && $result['is_error'] == 1 ) {

			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'params' => $params,
				'result' => $result,
				//'backtrace' => $trace,
			], true ) );

		}

	}



	/**
	 * Updates a CiviCRM Group's hierarchy when a BuddyPress Group's hierarchy is updated.
	 *
	 * @since 0.1
	 *
	 * @param int $bp_group_id The numeric ID of the BuddyPress Group.
	 * @param int $bp_parent_id The numeric ID of the parent BuddyPress Group.
	 */
	public function group_nesting_update( $bp_group_id, $bp_parent_id ) {

		// Init or die.
		if ( ! $this->is_active() ) {
			return;
		}

		// Init transaction.
		$transaction = new CRM_Core_Transaction();

		// Get Group source.
		$source = $this->member_group_get_sync_name( $bp_group_id );

		// Get the Group ID of the CiviCRM Member Group.
		$civi_group_id = $this->find_group_id( $source );

		// Bail if we don't get a Group ID.
		if ( ! $civi_group_id ) {

			// Construct message.
			$message = sprintf(
				/* translators: %s: The Group source */
				__( 'Could not find CiviCRM Group for Source %s', 'bp-groups-civicrm-sync' ),
				$source
			);

			// Log identifying data.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => $message,
				'bp_group_id' => $bp_group_id,
				'bp_parent_id' => $bp_parent_id,
				'source' => $source,
				'backtrace' => $trace,
			], true ) );

			return;

		}

		// Get the Group data.
		$civi_group = $this->get_civi_group_by_id( $civi_group_id );

		// If there's an existing parent.
		if ( isset( $civi_group['parents'] ) && ! empty( $civi_group['parents'] ) ) {

			// Delete existing.
			$this->group_nesting_delete( $civi_group_id, $civi_group['parents'] );

		}

		// Get CiviCRM Group ID for BuddyPress Group.
		$civi_parent_id = $this->get_civi_parent_group_id( $bp_parent_id, 'member' );

		// Create new nesting.
		$this->group_nesting_create( $civi_group_id, $civi_parent_id, 'member' );

		// Define Group.
		$group_params = [
			'bp_group_id' => $bp_group_id,
			'id' => $civi_group_id,
			'is_active' => 1,
		];

		// Get name for the CiviCRM Group.
		$group_params['source'] = $source;

		// Define CiviCRM Group Type (Mailing List by default).
		$group_params['group_type'] = $this->civi_group_get_type_by_code( 'member' );

		// Use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup().
		$success = $this->update_group( $group_params );

		// Get ACL source.
		$acl_source = $this->acl_group_get_sync_name( $bp_group_id );

		// Get the Group ID of the CiviCRM Member Group.
		$civi_acl_group_id = $this->find_group_id( $acl_source );

		// Bail if we don't get a Group ID.
		if ( ! $civi_acl_group_id ) {
			return;
		}

		// Get the ACL Group data.
		$civi_acl_group = $this->get_civi_group_by_id( $civi_acl_group_id );

		// Delete existing if there's an existing parent.
		if ( isset( $civi_acl_group['parents'] ) && ! empty( $civi_acl_group['parents'] ) ) {
			$this->group_nesting_delete( $civi_acl_group_id, $civi_acl_group['parents'] );
		}

		// Get CiviCRM Group ID for BuddyPress Group.
		$civi_acl_parent_id = $this->get_civi_parent_group_id( $bp_parent_id, 'acl' );

		// Create new nesting.
		$this->group_nesting_create( $civi_acl_group_id, $civi_acl_parent_id, 'acl' );

		// Define Group for update.
		$group_params = [
			'bp_group_id' => $bp_group_id,
			'id' => $civi_acl_group_id,
			'is_active' => 1,
		];

		// Get name for the CiviCRM ACL Group.
		$group_params['source'] = $acl_source;

		// Define CiviCRM Group Type (Access Control).
		$group_params['group_type'] = $this->civi_group_get_type_by_code( 'acl' );

		// Use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup().
		$success = $this->update_group( $group_params );

		// Do the database transaction.
		$transaction->commit();

		/*
		// Define Group.
		$params = [
			'version' => 3,
			'id' => $civi_group_id,
			'is_active' => 1,
		];

		// Use API to inspect Group.
		$group = civicrm_api( 'Group', 'get', $params );
		*/

		/*
		if( ! $success ) {
			bp_core_add_message( __( 'There was an error syncing; please try again.', 'bp-groups-civicrm-sync' ), 'error' );
		} else {
			bp_core_add_message( __( 'Group hierarchy settings synced successfully.', 'bp-groups-civicrm-sync' ) );
		}
		*/

	}



	/**
	 * Deletes all CiviCRM Group Nestings for a given Group ID.
	 *
	 * @since 0.1
	 *
	 * @param int $civi_group_id The numeric ID of the CiviCRM Group.
	 * @param int $civi_parent_id The numeric ID of the parent CiviCRM Group.
	 */
	public function group_nesting_delete( $civi_group_id, $civi_parent_id ) {

		// Define existing Group Nesting.
		$existing_params = [
			'version' => 3,
			'parent_group_id' => $civi_parent_id,
			'child_group_id' => $civi_group_id,
		];

		// Get existing Group Nesting.
		$existing_result = civicrm_api( 'GroupNesting', 'get', $existing_params );

		// Bail if there's an error.
		if ( ! empty( $existing_result['is_error'] ) && $existing_result['is_error'] == 1 ) {
			return;
		}

		// Bail if there are no results.
		if ( empty( $existing_result['values'] ) ) {
			return;
		}

		// Loop through them.
		foreach ( $existing_result['values'] as $existing_group_nesting ) {

			// Construct delete array.
			$delete_params = [
				'version' => 3,
				'id' => $existing_group_nesting['id'],
			];

			// Clear existing Group Nesting.
			$delete_result = civicrm_api( 'GroupNesting', 'delete', $delete_params );

		}

	}



	/**
	 * For a given BuddyPress Parent Group ID, get the ID of the synced CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param int $bp_parent_id The numeric ID of the parent BuddyPress Group.
	 * @param string $source Group update type - 'member' or 'acl'.
	 */
	public function get_civi_parent_group_id( $bp_parent_id, $source ) {

		// Init return.
		$civi_parent_id = false;

		// If the passed BuddyPress parent ID is 0, we're removing the BuddyPress parent or none is set.
		if ( $bp_parent_id == 0 ) {

			// Get our settings.
			$parent_group = absint( $this->admin->setting_get( 'parent_group' ) );

			// Bail if we're not using a Parent Group.
			if ( $parent_group == 0 ) {
				return false;
			}

			// Get the Group ID of the CiviCRM Meta Group.
			$civi_parent_id = $this->find_group_id(
				$this->meta_group_get_source()
			);

		} else {

			// What kind of Group is this?
			if ( $source == 'member' ) {
				$name = $this->member_group_get_sync_name( $bp_parent_id );
			} else {
				$name = $this->acl_group_get_sync_name( $bp_parent_id );
			}

			// Get the Group ID of the parent CiviCRM Group.
			$civi_parent_id = $this->find_group_id( $name );

		}

		// --<
		return $civi_parent_id;

	}



	// -------------------------------------------------------------------------



	/**
	 * Creates a CiviCRM Group.
	 *
	 * Unfortunately, CiviCRM insists on assigning the logged-in User as the Group creator
	 * This means that we cannot assign the BuddyPress Group creator as the CiviCRM Group creator
	 * except by doing some custom SQL.
	 *
	 * @see CRM_Contact_BAO_Group::create()
	 *
	 * @since 0.1
	 *
	 * @param array $params The array of CiviCRM API params.
	 * @param string $group_type The type of CiviCRM Group.
	 * @return bool $success True if Group created, false otherwise.
	 */
	public function create_group( &$params, $group_type = null ) {

		// Always use API 3.
		$params['version'] = 3;

		// If we have a Group Type passed here, use it.
		if ( ! is_null( $group_type ) ) {
			$params['group_type'] = $group_type;
		}

		// Use CiviCRM API to create the Group (will update if ID is set).
		$group = civicrm_api( 'Group', 'create', $params );

		// How did we do?
		if ( ! civicrm_error( $group ) ) {

			// Okay, add it to our params.
			$params['group_id'] = $group['id'];

			// Set flag.
			$success = true;

		} else {

			// Log identifying data.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => __( 'Could not create CiviCRM Group', 'bp-groups-civicrm-sync' ),
				'params' => $params,
				'group' => $group,
				'backtrace' => $trace,
			], true ) );

			// Set flag.
			$success = false;

		}

		// Because this is passed by reference, ditch the ID.
		unset( $params['id'] );

		// --<
		return $success;

	}



	/**
	 * Updates a CiviCRM Group or creates it if it doesn't exist.
	 *
	 * @since 0.1
	 *
	 * @param array $params The array of CiviCRM API params.
	 * @param string $group_type The type of CiviCRM Group.
	 */
	public function update_group( &$params, $group_type = null ) {

		// Always use API 3.
		$params['version'] = 3;

		// If ID not specified, get the CiviCRM Group ID from "source" value.
		if ( ! isset( $params['id'] ) || empty( $params['id'] ) ) {

			// Hand over to our clone of the CRM_Bridge_OG_Utils::groupID method.
			$params['id'] = $this->find_group_id(
				$params['source']
			);

		}

		// If we have a Group Type passed here, use it.
		if ( ! is_null( $group_type ) ) {
			$params['group_type'] = $group_type;
		}

		// Use CiviCRM API to create the Group (will update if ID is set).
		$group = civicrm_api( 'Group', 'create', $params );

		// How did we do?
		if ( ! civicrm_error( $group ) ) {

			// Okay, add it to our params.
			$params['group_id'] = $group['id'];

			// Set flag.
			$success = true;

		} else {

			// Log identifying data.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => __( 'Could not update CiviCRM Group', 'bp-groups-civicrm-sync' ),
				'params' => $params,
				'group' => $group,
				'backtrace' => $trace,
			], true ) );

			// Set flag.
			$success = false;

		}

		// Because this is passed by reference, ditch the ID.
		unset( $params['id'] );

		// --<
		return $success;

	}



	/**
	 * Deletes a CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param array $params The array of CiviCRM API params.
	 */
	public function delete_group( &$params ) {

		// Always use API 3.
		$params['version'] = 3;

		// If ID not specified, get the CiviCRM Group ID from "source" value.
		if ( ! isset( $params['id'] ) || empty( $params['id'] ) ) {

			// Hand over to our clone of the CRM_Bridge_OG_Utils::groupID method.
			$params['id'] = $this->find_group_id(
				$params['source']
			);

		}

		// Delete the Group only if we have a valid ID.
		if ( $params['id'] ) {

			// Delete Group.
			CRM_Contact_BAO_Group::discard( $params['id'] );

			// Assign Group ID.
			$params['group_id'] = $params['id'];

		}

		// Because this is passed by reference, ditch the ID.
		unset( $params['id'] );

	}



	// -------------------------------------------------------------------------



	/**
	 * Checks if a CiviCRM Contact is a member of a CiviCRM Group.
	 *
	 * @since 0.4
	 *
	 * @param integer $civi_group_id The numeric ID of the CiviCRM Group.
	 * @param integer $civi_contact_id The numeric ID of the CiviCRM Contact.
	 * @param string $status The status of the CiviCRM Group Contact.
	 * @return array|bool $group_contact The API data if the Contact is a member of the Group, false otherwise.
	 */
	public function group_contact_exists( $civi_group_id, $civi_contact_id, $status ) {

		// Init return.
		$group_contact = false;

		// Bail if CiviCRM is not active.
		if ( ! $this->is_active() ) {
			return $group_contact;
		}

		// Init API params.
		$params = [
			'version' => 3,
			'contact_id' => $civi_contact_id,
			'group_id' => $civi_group_id,
			'status' => $status,
		];

		// Call CiviCRM API.
		$result = civicrm_api( 'GroupContact', 'get', $params );

		// Return early if something went wrong.
		if ( ! empty( $result['error'] ) ) {

			// Write details to PHP log.
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

		// Return early if something went wrong.
		if ( empty( $result['values'] ) ) {
			return false;
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
	 *
	 * @param integer $civi_group_id The numeric ID of the CiviCRM Group.
	 * @param integer $civi_contact_id The numeric ID of a CiviCRM Contact.
	 * @return array|bool $group_contact The CiviCRM API data for the Contact, or false on failure.
	 */
	public function group_contact_create( $civi_group_id, $civi_contact_id ) {

		// Bail if CiviCRM is not active.
		if ( ! $this->is_active() ) {
			return false;
		}

		// Init API params.
		$params = [
			'version' => 3,
			'contact_id' => $civi_contact_id,
			'group_id' => $civi_group_id,
			'status' => 'Added',
		];

		// Call CiviCRM API.
		$group_contact = civicrm_api( 'GroupContact', 'create', $params );

		// Return early if something went wrong.
		if ( ! empty( $group_contact['error'] ) ) {

			// Write details to PHP log.
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'params' => $params,
					'result' => $group_contact,
					'backtrace' => $trace,
				], true ) );
			}

			return false;

		}

		// The API will not add a Group Contact if it already exists.
		return $group_contact;

	}



	/**
	 * Deletes a CiviCRM Contact from a CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param integer $civi_group_id The numeric ID of the CiviCRM Group.
	 * @param integer $civi_contact_id The numeric ID of a CiviCRM Contact.
	 * @return array|bool $group_contact The CiviCRM API data for the Group Contact, or false on failure.
	 */
	public function group_contact_delete( $civi_group_id, $civi_contact_id ) {

		// Bail if CiviCRM is not active.
		if ( ! $this->is_active() ) {
			return false;
		}

		// Init API params.
		$params = [
			'version' => 3,
			'contact_id' => $civi_contact_id,
			'group_id' => $civi_group_id,
			'status' => 'Removed',
		];

		// Call CiviCRM API.
		$group_contact = civicrm_api( 'GroupContact', 'create', $params );

		// Return early if something went wrong.
		if ( ! empty( $group_contact['error'] ) ) {

			// Write details to PHP log.
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'params' => $params,
					'result' => $group_contact,
					'backtrace' => $trace,
				], true ) );
			}

			return false;

		}

		// The API will not remove a Group Contact if it is already removed.
		return $group_contact;

	}



	/**
	 * Sync Group Member.
	 *
	 * @since 0.1
	 *
	 * @param array $params The array of CiviCRM API params.
	 * @param string $op The type of CiviCRM operation.
	 */
	public function group_contact_sync( &$params, $op ) {

		// Get the CiviCRM Contact ID.
		$civi_contact_id = $this->get_contact_id( $params['uf_id'] );

		// If we don't get one.
		if ( ! $civi_contact_id ) {

			// Write details to PHP log.
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {

				// Construct verbose error string.
				$error = sprintf(
					/* translators: %d: The numeric ID of the WordPress User */
					__( 'No CiviCRM Contact ID could be found for WordPress User ID %d', 'bp-groups-civicrm-sync' ),
					$user_id
				);

				// Log something.
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'error' => $error,
					'user' => print_r( new WP_User( $user_id ), true ),
					'backtrace' => $trace,
				], true ) );

			}

			// Bail.
			return;

		}

		// Get the CiviCRM Group ID of this BuddyPress Group.
		$civi_group_id = $this->find_group_id(
			$this->member_group_get_sync_name( $params['bp_group_id'] )
		);

		// Init CiviCRM Group params.
		$groupParams = [
			'version' => 3,
			'contact_id' => $civi_contact_id,
			'group_id' => $civi_group_id,
		];

		// Act based on operation type.
		if ( $op == 'add' ) {

			// Add the Contact to the Group.
			$groupParams['status'] = $params['is_active'] ? 'Added' : 'Pending';
			$group_contact = civicrm_api( 'GroupContact', 'create', $groupParams );

		} else {

			/*
			 * Removing a Contact from a Group has the following complication:
			 *
			 * When we try and "delete" a GroupContact, CiviCRM creates a record that
			 * the Contact was a Member of the Group but has been removed - even if
			 * they have *never been* a Member of the Group.
			 *
			 * Ideally the CiviCRM API should find out if the User was a Member before
			 * registering the "delete" event, but we have to do it ourselves.
			 */

			// "Remove" the Contact from the Group.
			$groupParams['status'] = 'Removed';

			// Does this Contact already have a "Removed" Group membership?
			$is_removed = $this->group_contact_exists( $civi_group_id, $civi_contact_id, 'Removed' );

			// Call the CiviCRM API.
			if ( $is_removed === false ) {
				$group_contact = civicrm_api( 'GroupContact', 'create', $groupParams );
			}

		}

		// Sanity check.
		if ( ! isset( $params['bp_status'] ) ) {
			$params['bp_status'] = '';
		}

		// Do we have an Admin User - or Admin being demoted?
		if (
			( ! empty( $params['is_admin'] ) && $params['is_admin'] == '1' )
			||
			$params['bp_status'] == 'ex-admin'
		) {

			// Get the Group ID of the ACL Group.
			$civi_acl_group_id = $this->find_group_id(
				$this->acl_group_get_sync_name( $params['bp_group_id'] )
			);

			// Define params.
			$groupParams = [
				'version' => 3,
				'contact_id' => $civi_contact_id,
				'group_id' => $civi_acl_group_id,
				'status' => $params['is_admin'] ? 'Added' : 'Removed',
			];

			// Set status based on operation type and BP status.
			if ( $op == 'add' && $params['bp_status'] != 'ex-admin' ) {
				$groupParams['status'] = $params['is_active'] ? 'Added' : 'Pending';
			} else {
				$groupParams['status'] = 'Removed';
			}

			// Call the CiviCRM API.
			$acl_group_contact = civicrm_api( 'GroupContact', 'create', $groupParams );

		}

	}



	/**
	 * Update a BuddyPress Group when a CiviCRM Contact is added to a Group.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $civi_group_id The ID of the CiviCRM Group.
	 * @param array $contact_ids Array of CiviCRM Contact IDs.
	 */
	public function group_contacts_added( $op, $object_name, $civi_group_id, $contact_ids ) {

		// Target our operation.
		if ( $op != 'create' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'GroupContact' ) {
			return;
		}

		// Get Group data.
		$civi_group = $this->get_civi_group_by_id( $civi_group_id );

		// Sanity check.
		if ( $civi_group === false ) {
			return;
		}

		// Get BuddyPress Group ID.
		$bp_group_id = $this->get_bp_group_id_by_civi_group( $civi_group );

		// Sanity check.
		if ( $bp_group_id === false ) {
			return;
		}

		// Loop through Contacts.
		if ( count( $contact_ids ) > 0 ) {
			foreach ( $contact_ids as $contact_id ) {

				// Get Contact data.
				$contact = $this->get_contact_by_contact_id( $contact_id );

				// Sanity check and add if okay.
				if ( $contact !== false ) {
					$contacts[] = $contact;
				}

			}
		}

		// Assume Member Group.
		$is_admin = 0;

		// Add as Admin if this is this an ACL Group.
		if ( $this->is_acl_group( $civi_group ) ) {
			$is_admin = 1;
		}

		// Add Contacts to BuddyPress Group.
		$this->bp->create_group_members( $bp_group_id, $contacts, $is_admin );

		// If it was an ACL Group they were added to, we also need to add them to
		// the Member Group - so, bail if this is a Member Group.
		if ( $is_admin == 0 ) {
			return;
		}

		// First, remove this action, otherwise we'll recurse.
		remove_action( 'civicrm_pre', [ $this, 'group_contacts_added' ], 10 );

		// Get the CiviCRM Group ID of the Member Group.
		$civi_group_id = $this->find_group_id(
			$this->member_group_get_sync_name( $bp_group_id )
		);

		// Sanity check.
		if ( $civi_group_id ) {

			// Add Contacts to Member Group.
			foreach ( $contacts as $contact ) {
				$this->group_contact_create( $civi_group_id, $contact['contact_id'] );
			}

			// Promote Members to Group Admins.
			$this->bp->promote_group_members( $bp_group_id, $contacts, 'admin' );

		}

		// Re-add this action.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_added' ], 10, 4 );

	}



	/**
	 * Update a BuddyPress Group when a CiviCRM Contact is deleted (or removed) from a Group.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $civi_group_id The ID of the CiviCRM Group.
	 * @param array $contact_ids Array of CiviCRM Contact IDs.
	 */
	public function group_contacts_deleted( $op, $object_name, $civi_group_id, $contact_ids ) {

		// Target our operation.
		if ( $op != 'delete' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'GroupContact' ) {
			return;
		}

		// Get Group data.
		$civi_group = $this->get_civi_group_by_id( $civi_group_id );

		// Sanity check.
		if ( $civi_group === false ) {
			return;
		}

		// Get BuddyPress Group ID.
		$bp_group_id = $this->get_bp_group_id_by_civi_group( $civi_group );

		// Sanity check.
		if ( $bp_group_id === false ) {
			return;
		}

		// Loop through Contacts.
		if ( count( $contact_ids ) > 0 ) {
			foreach ( $contact_ids as $contact_id ) {

				// Get Contact data.
				$contact = $this->get_contact_by_contact_id( $contact_id );

				// Sanity check and add if okay.
				if ( $contact !== false ) {
					$contacts[] = $contact;
				}

			}
		}

		// Is this an ACL Group?
		if ( $this->is_acl_group( $civi_group ) ) {

			// Demote to Member of BuddyPress Group.
			$this->bp->demote_group_members( $bp_group_id, $contacts );

			// Skip deletion.
			return;

		}

		// Delete from BuddyPress Group.
		$this->bp->delete_group_members( $bp_group_id, $contacts );

		// First, remove this action, otherwise we'll recurse.
		remove_action( 'civicrm_pre', [ $this, 'group_contacts_deleted' ], 10 );

		// Get the Group ID of the ACL Group.
		$civi_group_id = $this->find_group_id(
			$this->acl_group_get_sync_name( $bp_group_id )
		);

		// Sanity check.
		if ( $civi_group_id ) {

			// Remove Members from Group.
			foreach ( $contacts as $contact ) {
				$this->group_contact_delete( $civi_group_id, $contact['contact_id'] );
			}

		}

		// Re-add this action.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_deleted' ], 10, 4 );

	}



	/**
	 * Update a BuddyPress Group when a CiviCRM Contact is re-added to a Group.
	 *
	 * The issue here is that CiviCRM fires 'civicrm_pre' with $op = 'delete' regardless
	 * of whether the Contact is being removed or deleted. If a Contact is later re-added
	 * to the Group, then $op != 'create', so we need to intercept $op = 'edit'.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $civi_group_id The ID of the CiviCRM Group.
	 * @param array $contact_ids Array of CiviCRM Contact IDs.
	 */
	public function group_contacts_rejoined( $op, $object_name, $civi_group_id, $contact_ids ) {

		// Target our operation.
		if ( $op != 'edit' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'GroupContact' ) {
			return;
		}

		// First, remove this action, in case we recurse.
		remove_action( 'civicrm_pre', [ $this, 'group_contacts_rejoined' ], 10 );

		// Set op to 'create'.
		$op = 'create';

		// Use our Group Contact addition callback.
		$this->group_contacts_added( $op, $object_name, $civi_group_id, $contact_ids );

		// Re-add this action.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_rejoined' ], 10, 4 );

	}



	/**
	 * Get CiviCRM Contact ID by WordPress User ID.
	 *
	 * @since 0.1
	 *
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @return int $civi_contact_id The numeric ID of the CiviCRM Contact.
	 */
	public function get_contact_id( $user_id ) {

		// Init or die.
		if ( ! $this->is_active() ) {
			return;
		}

		// Make sure CiviCRM file is included.
		require_once 'CRM/Core/BAO/UFMatch.php';

		// Do initial search.
		$civi_contact_id = CRM_Core_BAO_UFMatch::getContactId( $user_id );
		if ( $civi_contact_id ) {
			return $civi_contact_id;
		}

		// Else synchronize contact for this User.
		$user = get_userdata( $user_id );
		if ( $user ) {

			// Sync this User.
			CRM_Core_BAO_UFMatch::synchronizeUFMatch(
				$user, // User object.
				$user->ID, // ID.
				$user->user_email, // Unique identifier.
				'WordPress', // CMS.
				null, // Status.
				'Individual', // Contact type.
				null // is_login.
			);

			// Get the CiviCRM Contact ID.
			$civi_contact_id = CRM_Core_BAO_UFMatch::getContactId( $user_id );
			if ( ! $civi_contact_id ) {

				// What to do?
				if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {

					// Construct verbose error string.
					$error = sprintf(
						/* translators: %d: The numeric ID of the WordPress User */
						__( 'No CiviCRM Contact ID could be found for WordPress User ID %d', 'bp-groups-civicrm-sync' ),
						$user_id
					);

					// Log something.
					$e = new \Exception();
					$trace = $e->getTraceAsString();
					error_log( print_r( [
						'method' => __METHOD__,
						'error' => $error,
						'user' => print_r( new WP_User( $user_id ), true ),
						'backtrace' => $trace,
					], true ) );

				}

				// Fallback.
				return false;

			}

		}

		// --<
		return $civi_contact_id;
	}



	// -------------------------------------------------------------------------



	/**
	 * Get CiviCRM Contact data by Contact ID.
	 *
	 * @since 0.1
	 *
	 * @param int $contact_id The numeric ID of the CiviCRM Contact.
	 * @return mixed $civi_contact The array of data for the CiviCRM Contact, or false if not found.
	 */
	public function get_contact_by_contact_id( $contact_id ) {

		// Get all Contact data.
		$params = [
			'version' => 3,
			'contact_id' => $contact_id,
		];

		// Use API.
		$result = civicrm_api( 'Contact', 'get', $params );

		// Bail if we get any errors.
		if ( ! empty( $result['is_error'] ) && $result['is_error'] == 1 ) {
			return false;
		}

		// Bail if there are no values.
		if ( empty( $result['values'] ) ) {
			return false;
		}

		// Get Contact.
		$contact = array_shift( $result['values'] );

		// --<
		return $contact;

	}



	// -------------------------------------------------------------------------



	/**
	 * Find a CiviCRM Group ID by source and (optionally) by title.
	 *
	 * @since 0.1
	 *
	 * @param string $source The sync name for the CiviCRM Group.
	 * @param string $title The title of the CiviCRM Group to search for.
	 * @return int|bool $group_id The ID of the CiviCRM Group.
	 */
	public function find_group_id( $source, $title = '' ) {

		// Construct query.
		$query = 'SELECT id FROM civicrm_group WHERE source = %1';

		// Define replacement.
		$params = [ 1 => [ $source, 'String' ] ];

		// Add title search, if present.
		if ( ! empty( $title ) ) {

			// Add to query.
			$query .= ' OR title = %2';

			// Add to replacements.
			$params[2] = [ $title, 'String' ];

		}

		// Let CiviCRM get the Group ID.
		$civi_group_id = CRM_Core_DAO::singleValueQuery( $query, $params );

		// Check for failure.
		if ( ! $civi_group_id ) {

			// Construct meaningful error.
			$error = sprintf(
				/* translators: %s: The Group source */
				__( 'No CiviCRM Group ID could be found for %s', 'bp-groups-civicrm-sync' ),
				$source
			);

			// Get BuddyPress Group ID - source is of the form "BP Sync Group :BPID:".
			$tmp = explode( ':', $source );
			$group_id = (int) $tmp[1];

			// Init message.
			$group_data = sprintf(
				/* translators: %s: The BuddyPress Group ID */
				__( 'No BuddyPress Group could be found for ID %d', 'bp-groups-civicrm-sync' ),
				$group_id
			);

			// Get the Group object if we get an ID.
			if ( $group_id ) {
				$group = groups_get_group( [
					'group_id' => $group_id,
					'populate_extras' => true,
				] );
				$group_data = print_r( $group, true );
			}

			// Log something.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'error' => $error,
				'group' => $group_data,
				'backtrace' => $trace,
			], true ) );

		}

		// --<
		return $civi_group_id;

	}



	/**
	 * Get all CiviCRM Group data.
	 *
	 * @since 0.3.7
	 *
	 * @return array $groups The array of CiviCRM Group data.
	 */
	public function get_all_civi_groups() {

		// Init return.
		$groups = [];

		// Define params to get all Groups.
		$params = [
			'version' => 3,
			'options' => [
				'limit' => '0', // API defaults to 25.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Group', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && $result['is_error'] == 1 ) {
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



	/**
	 * Get CiviCRM Group data by CiviCRM Group ID.
	 *
	 * @since 0.1
	 *
	 * @param int $civi_group_id The numeric ID of the CiviCRM Group.
	 * @return mixed $group The array of CiviCRM Group data, or false if none found.
	 */
	public function get_civi_group_by_id( $civi_group_id ) {

		// Define get "all with no parent" params.
		$params = [
			'version' => 3,
			'group_id' => $civi_group_id,
		];

		// Get Group.
		$result = civicrm_api( 'Group', 'get', $params );

		// Bail if we get any errors.
		if ( ! empty( $result['is_error'] ) && $result['is_error'] == 1 ) {
			return false;
		}

		// Bail if there are no values.
		if ( empty( $result['values'] ) ) {
			return false;
		}

		// Get Group data.
		$group = array_shift( $result['values'] );

		// --<
		return $group;

	}



	/**
	 * Get a BuddyPress Group ID by CiviCRM Group data.
	 *
	 * @since 0.1
	 *
	 * @param array $civi_group The array of CiviCRM Group data.
	 * @return mixed $bp_group_id The numeric ID of the BuddyPress Group, or false if none found.
	 */
	public function get_bp_group_id_by_civi_group( $civi_group ) {

		// Bail if Group has no reference to BuddyPress.
		if ( ! $this->has_bp_group( $civi_group ) ) {
			return false;
		}

		// Get BuddyPress Group ID - source is of the form "BP Sync Group :BPID:".
		$tmp = explode( ':', $civi_group['source'] );
		$bp_group_id = $tmp[1];

		// --<
		return $bp_group_id;

	}



	/**
	 * Get the type of CiviCRM Group by "source" string.
	 *
	 * @since 0.1
	 *
	 * @param string $source The source name of the CiviCRM Group.
	 * @return array $group_type The type of CiviCRM Group - either 'member' or 'acl'
	 */
	public function civi_group_get_code_by_source( $source ) {

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
	 * Get the type of CiviCRM Group by type string.
	 *
	 * @since 0.1
	 *
	 * @param string $group_type The type of CiviCRM Group - either 'member' or 'acl'.
	 * @return array $return Associative array of CiviCRM Group Types for the API.
	 */
	public function civi_group_get_type_by_code( $group_type ) {

		// If 'member'.
		if ( $group_type == 'member' ) {

			/**
			 * Define CiviCRM Group Type.
			 *
			 * By default, this is set to "Mailing List".
			 *
			 * @since 0.1
			 *
			 * @param array The existing CiviCRM Group Type array.
			 * @return array The modified CiviCRM Group Type array.
			 */
			$type_data = apply_filters( 'bp_groups_civicrm_sync_member_group_type', [ '2' => 2 ] );

		}

		// If 'acl'.
		if ( $group_type == 'acl' ) {

			/**
			 * Define CiviCRM Group Type.
			 *
			 * By default, this is set to "Access Control".
			 *
			 * @since 0.1
			 *
			 * @param array The existing CiviCRM Group Type array.
			 * @return array The modified CiviCRM Group Type array.
			 */
			$type_data = apply_filters( 'bp_groups_civicrm_sync_acl_group_type', [ '1' => 1 ] );

		}

		// --<
		return $type_data;

	}



	/**
	 * Check if a CiviCRM Group has an associated BuddyPress Group.
	 *
	 * @since 0.1
	 *
	 * @param array $civi_group The array of CiviCRM Group data.
	 * @return boolean $has_group True if CiviCRM Group has a BuddyPress Group, false if not.
	 */
	public function has_bp_group( $civi_group ) {

		// Bail if the source contains no info.
		if ( empty( $civi_group['source'] ) ) {
			return false;
		}

		// If the Group source has no reference to BuddyPress, then it's not.
		if ( strstr( $civi_group['source'], 'BP Sync Group' ) === false ) {
			return false;
		}

		// --<
		return true;

	}



	/**
	 * Construct name for CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param int $bp_group_id The BuddyPress Group ID.
	 * @return string
	 */
	public function member_group_get_sync_name( $bp_group_id ) {

		// Construct name, based on Organic Groups schema.
		return 'BP Sync Group :' . $bp_group_id . ':';

	}



	/**
	 * Check if a CiviCRM Group is a BuddyPress Member Group.
	 *
	 * @since 0.1
	 *
	 * @param array $civi_group The array of CiviCRM Group data.
	 * @return boolean $is_member_group True if CiviCRM Group is a BuddyPress Member Group, false if not.
	 */
	public function is_member_group( $civi_group ) {

		// Bail if Group has no reference to BuddyPress.
		if ( ! $this->has_bp_group( $civi_group ) ) {
			return false;
		}

		// If the Group source has a reference to BuddyPress, then it is.
		if ( strstr( $civi_group['source'], 'BP Sync Group :' ) !== false ) {
			return true;
		}

		// --<
		return false;

	}



	/**
	 * Construct name for CiviCRM ACL Group.
	 *
	 * @since 0.1
	 *
	 * @param int $bp_group_id The BuddyPress Group ID.
	 * @return string
	 */
	public function acl_group_get_sync_name( $bp_group_id ) {

		// Construct name, based on Organic Groups schema.
		return 'BP Sync Group ACL :' . $bp_group_id . ':';

	}



	/**
	 * Check if a CiviCRM Group is a BuddyPress ACL Group.
	 *
	 * @since 0.1
	 *
	 * @param array $civi_group The array of CiviCRM Group data.
	 * @return boolean $is_acl_group True if CiviCRM Group is a BuddyPress ACL Group, false if not.
	 */
	public function is_acl_group( $civi_group ) {

		// Bail if Group has no reference to BuddyPress.
		if ( ! $this->has_bp_group( $civi_group ) ) {
			return false;
		}

		// If the Group source has a reference to BuddyPress ACL, then it is.
		if ( strstr( $civi_group['source'], 'BP Sync Group ACL :' ) !== false ) {
			return true;
		}

		// --<
		return false;

	}



	// -------------------------------------------------------------------------



	/**
	 * Update ACL tables.
	 *
	 * @since 0.1
	 *
	 * @param array $aclParams The array of CiviCRM API params.
	 * @param string $op The CiviCRM database operation.
	 */
	public function update_civi_acl_tables( $aclParams, $op ) {

		// Drupal-esque operation.
		if ( $op == 'delete' ) {
			$this->update_civi_acl( $aclParams, $op );
			$this->update_civi_acl_entity_role( $aclParams, $op );
			$this->update_civi_acl_role( $aclParams, $op );
		} else {
			$this->update_civi_acl_role( $aclParams, $op );
			$this->update_civi_acl_entity_role( $aclParams, $op );
			$this->update_civi_acl( $aclParams, $op );
		}
	}



	/**
	 * Update ACL role.
	 *
	 * @since 0.1
	 *
	 * @param array $params The array of CiviCRM API params.
	 * @param string $op The CiviCRM database operation.
	 */
	public function update_civi_acl_role( &$params, $op ) {

		$optionGroupID = CRM_Core_DAO::getFieldValue(
			'CRM_Core_DAO_OptionGroup',
			'acl_role',
			'id',
			'name'
		);

		$dao = new CRM_Core_DAO_OptionValue();
		$dao->option_group_id = $optionGroupID;
		$dao->description = $params['source'];

		if ( $op == 'delete' ) {
			$dao->delete();
			return;
		}

		$dao->label = $params['title'];
		$dao->is_active = 1;

		$weightParams = [ 'option_group_id' => $optionGroupID ];
		$dao->weight = CRM_Utils_Weight::getDefaultWeight(
			'CRM_Core_DAO_OptionValue',
			$weightParams
		);

		$dao->value = CRM_Utils_Weight::getDefaultWeight(
			'CRM_Core_DAO_OptionValue',
			$weightParams,
			'value'
		);

		$query = '
			SELECT v.id
			FROM civicrm_option_value v
			WHERE v.option_group_id = %1
			AND v.description = %2
		';

		$queryParams = [
			1 => [ $optionGroupID, 'Integer' ],
			2 => [ $params['source'], 'String' ],
		];

		$dao->id = CRM_Core_DAO::singleValueQuery( $query, $queryParams );

		$dao->save();
		$params['acl_role_id'] = $dao->value;

	}



	/**
	 * Update ACL entity role.
	 *
	 * @since 0.1
	 *
	 * @param array $params The array of CiviCRM API params.
	 * @param string $op The CiviCRM database operation.
	 */
	public function update_civi_acl_entity_role( &$params, $op ) {

		$dao = new CRM_ACL_DAO_EntityRole();

		$dao->entity_table = 'civicrm_group';
		$dao->entity_id = $params['acl_group_id'];

		if ( $op == 'delete' ) {
			$dao->delete();
			return;
		}

		$dao->acl_role_id = $params['acl_role_id'];

		$dao->find( true );
		$dao->is_active = true;
		$dao->save();
		$params['acl_entity_role_id'] = $dao->id;

	}



	/**
	 * Update ACL.
	 *
	 * @since 0.1
	 *
	 * @param array $params The array of CiviCRM API params.
	 * @param string $op The CiviCRM database operation.
	 */
	public function update_civi_acl( &$params, $op ) {

		$dao = new CRM_ACL_DAO_ACL();

		$dao->object_table = 'civicrm_saved_search';
		$dao->object_id = $params['civicrm_group_id'];

		if ( $op == 'delete' ) {
			$dao->delete();
			return;
		}

		$dao->find( true );

		$dao->entity_table = 'civicrm_acl_role';
		$dao->entity_id    = $params['acl_role_id'];
		$dao->operation    = 'Edit';

		$dao->is_active = true;
		$dao->save();
		$params['acl_id'] = $dao->id;

	}



	// -------------------------------------------------------------------------



	/**
	 * Enable a BuddyPress Group to be created when creating a CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param string $formName The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_create_bp_group_options( $formName, &$form ) {

		// Is this the Group edit form?
		if ( $formName != 'CRM_Group_Form_Edit' ) {
			return;
		}

		// Get CiviCRM Group.
		$civi_group = $form->getVar( '_group' );

		// If we have a Group, bail.
		if ( ! empty( $civi_group ) ) {
			return;
		}

		// Okay, it's the new Group form.

		// Add the field element in the form.
		$form->add( 'checkbox', 'bpgroupscivicrmsynccreatefromnew', __( 'Create BuddyPress Group', 'bp-groups-civicrm-sync' ) );

		// Dynamically insert a template block in the page.
		CRM_Core_Region::instance( 'page-body' )->add( [
			'template' => 'bp-groups-civicrm-sync-new.tpl',
		] );

	}



	/**
	 * Maybe create a BuddyPress Group when creating a CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param string $formName The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_create_bp_group_process( $formName, &$form ) {

		// Kick out if not Group edit form.
		if ( ! ( $form instanceof CRM_Group_Form_Edit ) ) {
			return;
		}

		// Inspect submitted values.
		$values = $form->getVar( '_submitValues' );

		// Was our checkbox ticked?
		if ( ! isset( $values['bpgroupscivicrmsynccreatefromnew'] ) ) {
			return;
		}
		if ( $values['bpgroupscivicrmsynccreatefromnew'] != '1' ) {
			return;
		}

		// The Group hasn't been created yet.

		// Get CiviCRM Group.
		$civi_group = $form->getVar( '_group' );

		/*
		$e = new \Exception;
		$trace = $e->getTraceAsString();
		error_log( print_r( [
			'method' => __METHOD__,
			'values' => $values,
			'form' => $form,
			'civi_group' => $civi_group,
			//'backtrace' => $trace,
		], true ) );
		*/

		// Convert to BuddyPress Group.
		$this->civi_group_to_bp_group_convert( $civi_group );

	}



	/**
	 * Create a BuddyPress Group from a CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param object $civi_group The CiviCRM Group object.
	 */
	public function civi_group_to_bp_group_convert( $civi_group ) {

		// Set flag so that we don't act on the 'groups_create_group' action.
		$this->do_not_sync = true;

		/*
		$e = new \Exception;
		$trace = $e->getTraceAsString();
		error_log( print_r( [
			'method' => __METHOD__,
			'civi_group' => $civi_group,
			//'backtrace' => $trace,
		], true ) );
		*/

		// Remove hooks.
		remove_action( 'groups_create_group', [ $this->bp, 'create_civi_group' ], 100 );
		remove_action( 'groups_details_updated', [ $this->bp, 'update_civi_group_details' ], 100 );

		// Create the BuddyPress Group.
		$bp_group_id = $this->bp->create_group( $civi_group->title, $civi_group->description );

		// Re-add hooks.
		add_action( 'groups_create_group', [ $this->bp, 'create_civi_group' ], 100, 3 );
		add_action( 'groups_details_updated', [ $this->bp, 'update_civi_group_details' ], 100, 1 );

		// Get all Contacts in this Group.
		$params = [
			'version' => 3,
			'group' => $civi_group->id,
		];

		// Use API to get Members.
		$group_admins = civicrm_api( 'Contact', 'get', $params );

		// Do we have any Members?
		if ( isset( $group_admins['values'] ) && count( $group_admins['values'] ) > 0 ) {

			// Make Admins.
			$is_admin = 1;

			// Create Memberships from the CiviCRM Contacts.
			$this->bp->create_group_members( $bp_group_id, $group_admins['values'], $is_admin );

		}

		// Get source safely.
		$source = isset( $civi_group->source ) ? $civi_group->source : '';

		// Get the non-ACL CiviCRM Group ID.
		$civi_group_id = $this->find_group_id(
			str_replace( 'OG Sync Group ACL', 'OG Sync Group', $source )
		);

		// Get all Contacts in this Group.
		$params = [
			'version' => 3,
			'group' => $civi_group_id,
		];

		// Use API to get Members.
		$group_members = civicrm_api( 'Contact', 'get', $params );

		// Do we have any Members?
		if ( isset( $group_members['values'] ) && count( $group_members['values'] ) > 0 ) {

			// Make Members.
			$is_admin = 0;

			// Create Memberships from the CiviCRM Contacts.
			$this->bp->create_group_members( $bp_group_id, $group_members['values'], $is_admin );

		}

		// Update the "source" field for both CiviCRM Groups.

		// Define CiviCRM ACL Group.
		$acl_group_params = [
			'version' => 3,
			'id' => $civi_group->id,
		];

		// Get name for the CiviCRM Group.
		$acl_group_params['source'] = $this->acl_group_get_sync_name( $bp_group_id );

		// Use CiviCRM API to create the Group (will update if ID is set).
		$acl_group = civicrm_api( 'Group', 'create', $acl_group_params );

		// Error check.
		if ( $acl_group['is_error'] == '1' ) {

			// Debug.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'acl_group' => $acl_group,
				'backtrace' => $trace,
			], true ) );

			// Bail.
			return;

		}

		// Define CiviCRM Group.
		$member_group_params = [
			'version' => 3,
			'id' => $civi_group_id,
		];

		// Get name for the CiviCRM Group.
		$member_group_params['source'] = $this->member_group_get_sync_name( $bp_group_id );

		// Use CiviCRM API to create the Group (will update if ID is set).
		$member_group = civicrm_api( 'Group', 'create', $member_group_params );

		// Error check.
		if ( $member_group['is_error'] == '1' ) {

			// Debug.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'member_group' => $member_group,
				'backtrace' => $trace,
			], true ) );

			// Bail.
			return;

		}

		// If no parent.
		if ( isset( $civi_group['parents'] ) && $civi_group['parents'] == '' ) {

			// Assign both to meta group.
			$this->group_nesting_update( $bp_group_id, '0' );

		}

	}



	// -------------------------------------------------------------------------



	/**
	 * Enable a BuddyPress Group to be created from pre-existing Drupal Organic Groups in CiviCRM.
	 *
	 * @since 0.1
	 *
	 * @param string $formName The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_edit_og_options( $formName, &$form ) {

		// Is this the Group edit form?
		if ( $formName != 'CRM_Group_Form_Edit' ) {
			return;
		}

		// Get CiviCRM Group.
		$civi_group = $form->getVar( '_group' );

		// Get source safely.
		$source = isset( $civi_group->source ) ? $civi_group->source : '';

		/*
		 * In Drupal, Organic Groups are synced with 'OG Sync Group :GID:'.
		 * Related Organic Groups ACL Groups are synced with 'OG Sync Group ACL :GID:'.
		 */

		// Is this an Organic Groups Administrator Group?
		if ( strstr( $source, 'OG Sync Group ACL' ) === false ) {
			return;
		}

		// Add the field element in the form.
		$form->add( 'checkbox', 'bpgroupscivicrmsynccreatefromog', __( 'Create BuddyPress Group', 'bp-groups-civicrm-sync' ) );

		// Dynamically insert a template block in the page.
		CRM_Core_Region::instance( 'page-body' )->add( [
			'template' => 'bp-groups-civicrm-sync-og.tpl',
		] );

	}



	/**
	 * Create a BuddyPress Group based on pre-existing CiviCRM/Drupal/Organic Groups.
	 *
	 * @since 0.1
	 *
	 * @param string $formName The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_edit_og_process( $formName, &$form ) {

		// Kick out if not Group edit form.
		if ( ! ( $form instanceof CRM_Group_Form_Edit ) ) {
			return;
		}

		// Inspect submitted values.
		$values = $form->getVar( '_submitValues' );

		// Was our checkbox ticked?
		if ( ! isset( $values['bpgroupscivicrmsynccreatefromog'] ) ) {
			return;
		}
		if ( $values['bpgroupscivicrmsynccreatefromog'] != '1' ) {
			return;
		}

		// Get CiviCRM Group.
		$civi_group = $form->getVar( '_group' );

		// Convert to BuddyPress Group.
		$this->og_group_to_bp_group_convert( $civi_group );

	}



	/**
	 * Convert all legacy Organic Groups CiviCRM Groups to BuddyPress CiviCRM Groups.
	 *
	 * @since 0.1
	 */
	public function og_groups_to_bp_groups_convert() {

		// Init or die.
		if ( ! $this->is_active() ) {
			return;
		}

		// Get all Groups.
		$all_groups = $this->get_all_civi_groups();

		// Bail if there are no results.
		if ( empty( $all_groups ) ) {
			return;
		}

		// Loop through them.
		foreach ( $all_groups as $civi_group ) {

			// Send for processing.
			$this->og_group_to_bp_group_convert( (object) $civi_group );

		}

		// Assign to Meta Group.
		$this->meta_group_groups_assign();

	}



	/**
	 * Create a BuddyPress Group based on pre-existing CiviCRM/Drupal/Organic Groups.
	 *
	 * @since 0.1
	 *
	 * @param object $civi_group The CiviCRM Group object.
	 */
	public function og_group_to_bp_group_convert( $civi_group ) {

		// Get source.
		$source = $civi_group->source;

		/*
		 * In Drupal, Organic Groups are synced with 'OG Sync Group :GID:'.
		 * Related Organic Groups ACL Groups are synced with 'OG Sync Group ACL :GID:'.
		 */

		// Is this an Organic Groups Administrator Group?
		if ( strstr( $source, 'OG Sync Group ACL' ) === false ) {
			return;
		}

		// Set flag so that we don't act on the 'groups_create_group' action.
		$this->do_not_sync = true;

		// Remove hooks.
		remove_action( 'groups_create_group', [ $this->bp, 'create_civi_group' ], 100 );
		remove_action( 'groups_details_updated', [ $this->bp, 'update_civi_group_details' ], 100 );

		// Sanitise title by stripping suffix.
		$bp_group_title = array_shift( explode( ': Administrator', $civi_group->title ) );

		// Create the BuddyPress Group.
		$bp_group_id = $this->bp->create_group( $bp_group_title, $civi_group->description );

		// Re-add hooks.
		add_action( 'groups_create_group', [ $this->bp, 'create_civi_group' ], 100, 3 );
		add_action( 'groups_details_updated', [ $this->bp, 'update_civi_group_details' ], 100, 1 );

		// Get all Contacts in this Group.
		$params = [
			'version' => 3,
			'group' => $civi_group->id,
		];

		// Use API to get Members.
		$group_admins = civicrm_api( 'Contact', 'get', $params );

		// Do we have any Members?
		if ( isset( $group_admins['values'] ) && count( $group_admins['values'] ) > 0 ) {

			// Make Admins.
			$is_admin = 1;

			// Create Memberships from the CiviCRM Contacts.
			$this->bp->create_group_members( $bp_group_id, $group_admins['values'], $is_admin );

		}

		// Get the non-ACL CiviCRM Group ID.
		$civi_group_id = $this->find_group_id(
			str_replace( 'OG Sync Group ACL', 'OG Sync Group', $source )
		);

		// Get all Contacts in this Group.
		$params = [
			'version' => 3,
			'group' => $civi_group_id,
		];

		// Use API to get Members.
		$group_members = civicrm_api( 'Contact', 'get', $params );

		// Do we have any Members?
		if ( isset( $group_members['values'] ) && count( $group_members['values'] ) > 0 ) {

			// Make Members.
			$is_admin = 0;

			// Create Memberships from the CiviCRM Contacts.
			$this->bp->create_group_members( $bp_group_id, $group_members['values'], $is_admin );

		}

		// Update the "source" field for both CiviCRM Groups.

		// Define CiviCRM ACL Group.
		$acl_group_params = [
			'version' => 3,
			'id' => $civi_group->id,
		];

		// Get name for the CiviCRM Group.
		$acl_group_params['source'] = $this->acl_group_get_sync_name( $bp_group_id );

		// Use CiviCRM API to create the Group (will update if ID is set).
		$acl_group = civicrm_api( 'Group', 'create', $acl_group_params );

		// Error check.
		if ( $acl_group['is_error'] == '1' ) {

			// Debug.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'acl_group' => $acl_group,
				'backtrace' => $trace,
			], true ) );

			// Bail.
			return;

		}

		// Define CiviCRM Group.
		$member_group_params = [
			'version' => 3,
			'id' => $civi_group_id,
		];

		// Get name for the CiviCRM Group.
		$member_group_params['source'] = $this->member_group_get_sync_name( $bp_group_id );

		// Use CiviCRM API to create the Group (will update if ID is set).
		$member_group = civicrm_api( 'Group', 'create', $member_group_params );

		// Error check.
		if ( $member_group['is_error'] == '1' ) {

			// Debug.
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'member_group' => $member_group,
				'backtrace' => $trace,
			], true ) );

			// Bail.
			return;

		}

		// If no parent.
		if ( isset( $civi_group['parents'] ) && $civi_group['parents'] == '' ) {

			// Assign both to Meta Group.
			$this->group_nesting_update( $bp_group_id, '0' );

		}

	}



	/**
	 * Do we have any legacy Organic Groups CiviCRM Groups?
	 *
	 * @since 0.1
	 *
	 * @return bool True if legacy Organic Groups CiviCRM Groups are found, false if not.
	 */
	public function has_og_groups() {

		// Init or die.
		if ( ! $this->is_active() ) {
			return false;
		}

		// Get all Groups.
		$all_groups = $this->get_all_civi_groups();

		// Bail if there are no results.
		if ( empty( $all_groups ) ) {
			return false;
		}

		// Loop through them.
		foreach ( $all_groups as $civi_group ) {

			// If "source" is not present, it's not an Organic Groups Group.
			if ( empty( $civi_group['source'] ) ) {
				continue;
			}

			// Get source.
			$source = $civi_group['source'];

			/*
			 * In Drupal, Organic Groups are synced with 'OG Sync Group :GID:'.
			 * Related Organic Groups ACL Groups are synced with 'OG Sync Group ACL :GID:'.
			 */

			// Is this an Organic Groups Administrator Group?
			if ( strstr( $source, 'OG Sync Group ACL :' ) !== false ) {
				return true;
			}

			// Is this an Organic Groups Member Group?
			if ( strstr( $source, 'OG Sync Group :' ) !== false ) {
				return true;
			}

		}

		// --<
		return false;

	}



	// -------------------------------------------------------------------------



	/**
	 * Updates a CiviCRM Contact when a WordPress User is updated.
	 *
	 * @since 0.1
	 *
	 * @param object $user A WordPress User object.
	 */
	public function civi_contact_update( $user ) {

		// Make sure CiviCRM file is included.
		require_once 'CRM/Core/BAO/UFMatch.php';

		// synchronizeUFMatch returns the Contact object.
		$civi_contact = CRM_Core_BAO_UFMatch::synchronizeUFMatch(
			$user, // User object.
			$user->ID, // ID.
			$user->user_email, // Unique identifier.
			'WordPress', // CMS.
			null, // Unused.
			'Individual' // Contact type.
		);

	}



} // Class ends.
