<?php
/*
--------------------------------------------------------------------------------
BP_Groups_CiviCRM_Sync_CiviCRM Class
--------------------------------------------------------------------------------
*/

class BP_Groups_CiviCRM_Sync_CiviCRM {

	/** 
	 * properties
	 */
	
	// BuddyPress utilities class
	public $bp;
	
	// flag for overriding sync process
	public $do_not_sync = false;
	
	
	
	/** 
	 * @description: initialises this object
	 * @return object
	 */
	function __construct() {
	
		// add actions for plugin init on CiviCRM init
		add_action( 'civicrm_instance_loaded', array( $this, 'register_hooks' ) );
				
		// --<
		return $this;

	}
	
	
	
	/**
	 * @description: set references to other objects
	 * @return nothing
	 */
	public function set_references( &$bp_object ) {
	
		// store BuddyPress reference
		$this->bp = $bp_object;
		
	}
	
	
		
	/**
	 * @description: register hooks on plugin init
	 * @return nothing
	 */
	public function register_hooks() {
		
		// allow plugins to register php and template directories
		add_action( 'civicrm_config', array( $this, 'register_directories' ), 10, 1 );
		
		// intercept CiviCRM group edit form
		add_action( 'civicrm_buildForm', array( $this, 'civi_group_form_options' ), 10, 2 );
		
		// intercept CiviCRM group edit form submission
		add_action( 'civicrm_postProcess', array( $this, 'civi_group_form_process' ), 10, 2 );
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: creates the CiviCRM Group which is the ultimate parent for all BuddyPress groups
	 * @return nothing
	 */
	public function create_meta_group() {
	
		// init or die
		if ( ! $this->is_active() ) return;
		
		// init transaction
		$transaction = new CRM_Core_Transaction();
		
		
		
		// don't die
		$abort = false;
	
		// get the group ID of the Civi meta group
		$civi_meta_group_id = $this->get_group_id(
		
			$this->get_meta_group_source(),
			null, 
			$abort
			
		);
		
		// skip if it already exists
		if ( is_numeric( $civi_meta_group_id ) AND $civi_meta_group_id > 0 ) {
		
			// skip
			
		} else {
		
			// define group
			$params = array(
				'name' => __( 'BuddyPress Groups', 'bp-groups-civicrm-sync' ),
				'title' => __( 'BuddyPress Groups', 'bp-groups-civicrm-sync' ),
				'description' => __( 'Container for all BuddyPress Groups', 'bp-groups-civicrm-sync' ),
				'is_active' => 1,
			);
		
			// set inscrutable group type
			$params['group_type'] = array( '1' );
		
			// get "source" for the Civi group
			$params['source'] = $this->get_meta_group_source();
		
			// use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup()
			$this->create_group( $params );
		
		}
		
		
		
		// assign groups with no parent to the meta group
		//$this->assign_groups_to_meta_group();
		
		
		
		// do the database transaction
	    $transaction->commit();
	    
	}
	
	
	
	/**
	 * @description: assign all CiviCRM Groups with no parent to our meta group
	 * @return nothing
	 */
	public function assign_groups_to_meta_group() {
	
		// init or die
		if ( ! $this->is_active() ) return;
		
		// don't die
		$abort = false;
	
		// get the group ID of the Civi meta group
		$civi_meta_group_id = $this->get_group_id(
		
			$this->get_meta_group_source(),
			null, 
			$abort
			
		);
		
		// define get "all with no parent" params
		$params = array(
			'version' => 3,
			// define stupidly high limit, because API defaults to 25
			'options' => array( 
				'limit' => '10000',
			),
		);
		
		// get all groups with no parent ID (get ALL for now)
		$all_groups = civicrm_api( 'group', 'get', $params );
		
		/*
		print_r( array(
			'all' => $all_groups 
		) ); die();
		*/
		
		// if we got some...
		if ( 
		
			$all_groups['is_error'] == 0 AND 
			isset( $all_groups['values'] ) AND 
			count( $all_groups['values'] ) > 0 
			
		) {
			
			// loop
			foreach( $all_groups['values'] AS $group ) {
				
				// when there is no parent...
				if ( 
					isset( $group['parents'] ) 
					AND 
					(
						is_null( $group['parents'] )
						OR
						$group['parents'] == ''
					)
				) {
				
					// init transaction
					//$transaction = new CRM_Core_Transaction();
		
					// set BP group to empty, which triggers assignment to meta group
					$bp_parent_id = 0;
					
					// check for member group
					if ( strstr( $group['source'], 'BP Sync Group :' ) !== false ) {
						$this->create_civi_group_nesting( $group['id'], $bp_parent_id, $source = 'member' );
					}
			
					// check for ACL group
					if ( strstr( $group['source'], 'BP Sync Group ACL :' ) !== false ) {
						$this->create_civi_group_nesting( $group['id'], $bp_parent_id, $source = 'acl' );
					}
				
					// do the database transaction
					//$transaction->commit();
		
				}
			
			}
			
		} else {
		
			// debug
			print_r( array(
				'method' => 'assign_groups_to_meta_group',
				'all_groups' => $all_groups,
			) ); die();
		
		}
		
	}
	
	
	
	/**
	 * @description: get source (our unique code) for our meta group
	 * @return string
	 */
	public function get_meta_group_source() {
	
		// define code
		return 'bp-groups-civicrm-sync';
		
	}
	
	
		
	/**
	 * @description: construct name for CiviCRM group
	 * @param int $bp_group_id the BuddyPress group ID
	 * @return string
	 */
	public function get_group_sync_name( $bp_group_id ) {
	
		// construct name, based on OG schema
		return 'BP Sync Group :'.$bp_group_id.':';
		
	}
	
	
	
	/**
	 * @description: construct name for CiviCRM ACL group
	 * @param int $bp_group_id the BuddyPress group ID
	 * @return string
	 */
	public function get_acl_group_sync_name( $bp_group_id ) {
	
		// construct name, based on OG schema
		return 'BP Sync Group ACL :'.$bp_group_id.':';
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: test if CiviCRM plugin is active
	 * @return bool
	 */
	public function is_active() {
	
		// bail if no CiviCRM init function
		if ( ! function_exists( 'civi_wp' ) ) return false;
		
		// try and init CiviCRM
		return civi_wp()->initialize();
		
	}
	
	
	
	/**
	 * @description: register directories that CiviCRM searches for php and template files
	 * @param object $config the CiviCRM config object
	 * @return nothing
	 */
	public function register_directories( &$config ) {
		
		/*
		print_r( array(
			'config' => $config
		) ); die();
		*/
		
		// define our custom path
		$custom_path = BP_GROUPS_CIVICRM_SYNC_PATH . 'civicrm_custom_templates';
		
		// kick out if no CiviCRM
		if ( ! civi_wp()->initialize() ) return;
		
		
		
		// get template instance
		$template = CRM_Core_Smarty::singleton();
		
		// add our custom template directory
		$template->addTemplateDir( $custom_path );
		
		// register template directories
		$template_include_path = $custom_path . PATH_SEPARATOR . get_include_path();
		set_include_path( $template_include_path );
		
	}
		
		
		
	/**
	 * @description: enable a BP group to be created from pre-existing Civi/Drupal/OG groups
	 * @param string $formName the CiviCRM form name
	 * @param object $form the CiviCRM form object
	 * @return nothing
	 */
	public function civi_group_form_options( $formName, &$form ) {
		
		// is this the group edit form?
		if ( $formName != 'CRM_Group_Form_Edit' ) return;
		
		
		
		// get Civi group
		$civi_group = $form->getVar( '_group' );
	
		/*
		print_r( array(
			'formName' => $formName,
			'civi_group' => $civi_group,
			//'form' => $form,
		) ); die();
		*/

		// get source
		$source = $civi_group->source;
	
		// in Drupal, OG groups are synced with 'OG Sync Group :GID:'
		// related OG ACL groups are synced with 'OG Sync Group ACL :GID:'
	
		// is this an OG administrator group?
		if ( strstr( $source, 'OG Sync Group ACL' ) === false ) return;
		
		
		
		/*
		print_r( array(
			'formName' => $formName,
			'source' => $source,
			'form' => $form,
		) ); die();
		*/

		// Add the field element in the form
		$form->add( 'checkbox', 'bpgroupscivicrmsynccreate', __( 'Create BuddyPress Group', 'bp-groups-civicrm-sync' ) );

		// dynamically insert a template block in the page
		CRM_Core_Region::instance('page-body')->add( array(
			'template' => 'bp-groups-civicrm-sync.tpl'
		));

	}
	
	
	
	/**
	 * @description: create a BP group based on pre-existing Civi/Drupal/OG group
	 * @param string $formName the CiviCRM form name
	 * @param object $form the CiviCRM form object
	 * @return nothing
	 */
	public function civi_group_form_process( $formName, &$form ) {
		
		/*
		print_r( array(
			'formName' => $formName,
			'form' => $form,
		) ); die();
		*/
		
		// kick out if not group edit form
		if ( ! is_a( $form, 'CRM_Group_Form_Edit' ) ) return;
		
		
		
		// inspect submitted values
		$values = $form->getVar( '_submitValues' );
		
		// was our checkbox ticked?
		if ( !isset( $values['bpgroupscivicrmsynccreate'] ) ) return;
		if ( $values['bpgroupscivicrmsynccreate'] != '1' ) return;
		
		
		
		// get Civi group
		$civi_group = $form->getVar( '_group' );
		
		// convert to BP group
		$this->convert_og_group_to_bp_group( $civi_group );
		
	}
	
	
	
	/**
	 * @description: do we have any legacy OG CiviCRM Groups?
	 * @return bool
	 */
	public function has_og_groups() {
	
		// init or die
		if ( ! $this->is_active() ) return;
		
		// define get all groups params
		$params = array(
			'version' => 3,
		);
		
		// get all groups with no parent ID (get ALL for now)
		$all_groups = civicrm_api( 'group', 'get', $params );
		
		// if we got some...
		if (
		
			$all_groups['is_error'] == 0 AND 
			isset( $all_groups['values'] ) AND 
			count( $all_groups['values'] ) > 0 
			
		) {
			
			// loop through them
			foreach( $all_groups['values'] AS $civi_group ) {
				
				// get source
				$source = $civi_group['source'];
		
				// in Drupal, OG groups are synced with 'OG Sync Group :GID:'
				// related OG ACL groups are synced with 'OG Sync Group ACL :GID:'
		
				// is this an OG administrator group?
				if ( strstr( $source, 'OG Sync Group ACL :' ) !== false ) return true;
		
				// is this an OG member group?
				if ( strstr( $source, 'OG Sync Group :' ) !== false ) return true;
		
			}
			
		}
		
		// --<
		return false;
		
	}
	
	
	
	/**
	 * @description: convert all legacy OG CiviCRM Groups to BP CiviCRM Groups
	 * @return nothing
	 */
	public function convert_og_groups_to_bp_groups() {
	
		// init or die
		if ( ! $this->is_active() ) return;
		
		// define get all groups params
		$params = array(
		
			// v3, of course
			'version' => 3,
			
			// define stupidly high limit, because API defaults to 25
			'options' => array( 
				'limit' => '10000',
			),
			
		);
		
		// get all groups with no parent ID (get ALL for now)
		$all_groups = civicrm_api( 'group', 'get', $params );
		
		/*
		print_r( array(
			'method' => 'convert_og_groups_to_bp_groups',
			'all_groups' => $all_groups,
		) ); die();
		*/
		
		// if we got some...
		if (
		
			$all_groups['is_error'] == 0 AND 
			isset( $all_groups['values'] ) AND 
			count( $all_groups['values'] ) > 0 
			
		) {
			
			// loop through them
			foreach( $all_groups['values'] AS $civi_group ) {
				
				// send for processing
				$this->convert_og_group_to_bp_group( (object)$civi_group );
			
			}
			
		}
		
		// assign to meta group
		$this->assign_groups_to_meta_group();
		
	}
	
	
	
	/**
	 * @description: create a BP group based on pre-existing Civi/Drupal/OG group
	 * @param string $formName the CiviCRM form name
	 * @param object $form the CiviCRM form object
	 */
	public function convert_og_group_to_bp_group( $civi_group ) {
		
		// get source
		$source = $civi_group->source;
		
		// in Drupal, OG groups are synced with 'OG Sync Group :GID:'
		// related OG ACL groups are synced with 'OG Sync Group ACL :GID:'
		
		// is this an OG administrator group?
		if ( strstr( $source, 'OG Sync Group ACL' ) === false ) return;
		
		/*
		print_r( array(
			'method' => 'convert_og_group_to_bp_group',
			'civi_group' => $civi_group,
		) ); //die();
		*/
		
		// set flag so that we don't act on the 'groups_create_group' action
		$this->do_not_sync = true;
		
		// remove hooks
		remove_action( 'groups_create_group', array( $this->bp, 'create_civi_group' ), 100 );
		remove_action( 'groups_details_updated', array( $this->bp, 'update_civi_group_details' ), 100 );
		
		// sanitise title by stripping suffix
		$bp_group_title = array_shift( explode( ': Administrator', $civi_group->title ) );
		
		// create the BuddyPress group
		$bp_group_id = $this->bp->create_group( $bp_group_title, $civi_group->description );
		
		// re-add hooks
		add_action( 'groups_create_group', array( $this->bp, 'create_civi_group' ), 100, 3 );
		add_action( 'groups_details_updated', array( $this->bp, 'update_civi_group_details' ), 100, 1 );
		
		
		
		// get all contacts in this group
		$params = array(
			'version' => 3,
			'group' => $civi_group->id,
		);
		
		// use API to get members
		$group_admins = civicrm_api( 'contact', 'get', $params );
		
		// do we have any members?
		if ( isset( $group_admins['values'] ) AND count( $group_admins['values'] ) > 0 ) {
			
			// make admins
			$is_admin = 1;
			
			// create memberships from the Civi contacts
			$this->bp->create_group_members( $bp_group_id, $group_admins['values'], $is_admin );
			
		}
		
		
		
		// get the non-ACL Civi group ID
		$civi_group_id = $this->get_group_id(
		
			str_replace( 'OG Sync Group ACL', 'OG Sync Group', $source )
			
		);
		
		// get all contacts in this group
		$params = array(
			'version' => 3,
			'group' => $civi_group_id,
		);
		
		// use API to get members
		$group_members = civicrm_api( 'contact', 'get', $params );
		
		/*
		print_r( array(
			'formName' => $formName,
			'group_members' => $group_members,
		) ); die();
		*/
		
		// do we have any members?
		if ( isset( $group_members['values'] ) AND count( $group_members['values'] ) > 0 ) {
			
			// make members
			$is_admin = 0;
			
			// create memberships from the Civi contacts
			$this->bp->create_group_members( $bp_group_id, $group_members['values'], $is_admin );
			
		}
		
		
		
		// update the "source" field for both CiviCRM groups
		
		// define Civi ACL group
		$acl_group_params = array(
			'version' => 3,
			'id' => $civi_group->id,
		);
		
		// get name for the Civi group
		$acl_group_params['source'] = $this->get_acl_group_sync_name( $bp_group_id );
		
		// use Civi API to create the group (will update if ID is set)
		$acl_group = civicrm_api( 'group', 'create', $acl_group_params );
		
		// error check
		if ( $acl_group['is_error'] == '1' ) {
		
			// debug
			print_r( array(
				'method' => 'convert_og_group_to_bp_group',
				'acl_group' => $acl_group,
			) ); die();
		
		}
		

		
		// define Civi group
		$member_group_params = array(
			'version' => 3,
			'id' => $civi_group_id,
		);
		
		// get name for the Civi group
		$member_group_params['source'] = $this->get_group_sync_name( $bp_group_id );
		
		// use Civi API to create the group (will update if ID is set)
		$member_group = civicrm_api( 'group', 'create', $member_group_params );
		
		// error check
		if ( $member_group['is_error'] == '1' ) {
		
			// debug
			print_r( array(
				'method' => 'convert_og_group_to_bp_group',
				'member_group' => $member_group,
			) ); die();
		
		}
		
		
		
		// if no parent
		if ( isset( $civi_group['parents'] ) AND $civi_group['parents'] == '' ) {
		
			// assign both to meta group
			$this->update_civi_group_nesting( $bp_group_id, '0' );
			
		}
		
	}
	
	
	
	/**
	 * @description: creates a CiviCRM Group when a BuddyPress group is created
	 * @param int $bp_group_id the numeric ID of the BP group
	 * @param int $first_member WP user object
	 * @param object $bp_group the BP group object
	 * @return array $return associative array of CiviCRM group IDs (member_group_id, acl_group_id)
	 */
	public function create_civi_group( $bp_group_id, $first_member, $bp_group ) {
	
		// are we overriding this?
		if ( $this->do_not_sync ) return false;
	
		// init or die
		if ( ! $this->is_active() ) return false;
		
		// init return
		$return = array();
	
		
		
		// init transaction
		$transaction = new CRM_Core_Transaction();
		
		// define group
		$params = array(
			'bp_group_id' => $bp_group_id,
			'name' => $bp_group->name,
			'title' => $bp_group->name,
			'description' => $bp_group->description,
			'is_active' => 1,
		);
		
		
		
		// first create the CiviCRM group
		$group_params = $params;
		
		// get name for the Civi group
		$group_params['source'] = $this->get_group_sync_name( $bp_group_id );
		
		// define Civi group type
		$group_params['group_type'] = array( '2' => 1 );
		
		// use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup()
		$this->create_group( $group_params );
		
		// store ID of created Civi group
		$return['member_group_id'] = $group_params['group_id'];
		
		
		
		// next create the CiviCRM ACL group
		$acl_params = $params;
		
		// set name and title of ACL group
		$acl_params['name'] = $acl_params['title'] = $acl_params['name'].': Administrator';
		
		// set source name for ACL group
		$acl_params['source'] = $this->get_acl_group_sync_name( $bp_group_id );
		
		// set inscrutable group type
		$acl_params['group_type'] = array( '1' );
		
		// create the ACL group too
		$this->create_group( $acl_params );
		
		// set some further params
		$acl_params['acl_group_id'] = $acl_params['group_id'];
		$acl_params['civicrm_group_id'] = $group_params['group_id'];
		
		// use cloned CiviCRM function
		$this->updateCiviACLTables( $acl_params, 'add' );
		
		// store ID of created Civi group
		$return['acl_group_id'] = $acl_params['group_id'];
		
		
		
		// create nesting with no parent
		$this->update_civi_group_nesting( $bp_group_id, 0 );
		


		// do the database transaction
	    $transaction->commit();
	    
	    
		// add the creator to the groups
		$params = array(
			'bp_group_id' => $bp_group_id,
			'uf_id' => $bp_group->creator_id,
			'is_active' => 1,
			'is_admin' => 1,
		);
		
		// use clone of CRM_Bridge_OG_Drupal::og()
		$this->sync_group_member( $params, 'add' );
		
		
		
		// --<
		return $return;
		
	}
	
	
	
	/**
	 * @description: updates a CiviCRM Group when a BuddyPress group is updated
	 * @param int $group_id the numeric ID of the BP group
	 * @param int $group the BP group object
	 * @return nothing
	 */
	public function update_civi_group( $group_id, $group ) {
	
		// are we overriding this?
		if ( $this->do_not_sync ) return false;
	
		// init or die
		if ( ! $this->is_active() ) return false;
		
		// init return
		$return = array();
	
		
		
		// init transaction
		$transaction = new CRM_Core_Transaction();
		
		// define group
		$params = array(
			'bp_group_id' => $group_id,
			'name' => $group->name,
			'title' => $group->name,
			'description' => $group->description,
			'is_active' => 1,
		);
		
		
		
		// first update the CiviCRM group
		$group_params = $params;
		
		// get name for the Civi group
		$group_params['source'] = $this->get_group_sync_name( $group_id );
		
		// define Civi group type
		$group_params['group_type'] = array( '2' => 1 );
		
		// use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup()
		$this->update_group( $group_params );
		
		// store ID of created Civi group
		$return['member_group_id'] = $group_params['group_id'];
		
		
		
		// next update the CiviCRM ACL group
		$acl_params = $params;
		
		// set name and title of ACL group
		$acl_params['name'] = $acl_params['title'] = $acl_params['name'].': Administrator';
		
		// set source name for ACL group
		$acl_params['source'] = $this->get_acl_group_sync_name( $group_id );
		
		// set inscrutable group type
		$acl_params['group_type'] = array( '1' );
		
		// update the ACL group too
		$this->update_group( $acl_params );
		
		// set some further params
		$acl_params['acl_group_id'] = $acl_params['group_id'];
		$acl_params['civicrm_group_id'] = $group_params['group_id'];
		
		// use cloned CiviCRM function
		$this->updateCiviACLTables( $acl_params, 'update' );
		
		// store ID of created Civi group
		$return['acl_group_id'] = $acl_params['group_id'];
		
				
		
		// do the database transaction
	    $transaction->commit();
	    
	    
	    
		/*
		print_r( array(
			'group_params' => $group_params,
			'acl_params' => $acl_params,
			'return' => $return,
		) ); die();
		*/
		
		// --<
		return $return;
		
	}
	
	
	
	/**
	 * @description: deletes a CiviCRM Group when a BuddyPress group is deleted
	 * @param int $group_id the numeric ID of the BP group
	 * @return nothing
	 */
	public function delete_civi_group( $group_id ) {
	
		// are we overriding this?
		if ( $this->do_not_sync ) return;
	
		// init or die
		if ( ! $this->is_active() ) return;
		
		
		
		// init transaction
		$transaction = new CRM_Core_Transaction();
		
		// get the group object
		$group = groups_get_group( array( 'group_id' => $group_id ) );
		
		// define group
		$params = array(
			'bp_group_id' => $group_id,
			'name' => $group->name,
			'title' => $group->name,
			'is_active' => 1,
		);
		


		// first delete the CiviCRM group
		$group_params = $params;
		
		// get name for the Civi group
		$group_params['source'] = $this->get_group_sync_name( $group_id );
		
		// use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup()
		$this->delete_group( $group_params );
		
		
		
		// next delete the CiviCRM ACL group
		$acl_params = $params;
		
		// set name and title of ACL group
		$acl_params['name'] = $acl_params['title'] = $acl_params['name'].': Administrator';
		
		// set source name for ACL group
		$acl_params['source'] = $this->get_acl_group_sync_name( $group_id );
		
		// delete the ACL group too
		$this->delete_group( $acl_params );
		
		// set some further params
		$acl_params['acl_group_id'] = $acl_params['group_id'];
		$acl_params['civicrm_group_id'] = $group_params['group_id'];
		
		// use cloned CiviCRM function
		$this->updateCiviACLTables( $acl_params, 'delete' );
		
		
		
		// do the database transaction
	    $transaction->commit();
	    
	}
	
	
	
	/**
	 * @description: create a CiviCRM Group Nesting
	 * @param int $civi_group_id the numeric ID of the Civi group
	 * @param int $bp_parent_id the numeric ID of the parent BP group
	 * @param string $source group update type - ('member' or 'acl')
	 * @return nothing
	 */
	public function create_civi_group_nesting( $civi_group_id, $bp_parent_id, $source ) {
		
		// if the passed BP parent ID is 0, we're removing the BP parent or none is set...
		if ( $bp_parent_id == 0 ) {
		
			// get meta group or die
			$abort = true;
	
			// get the group ID of the Civi meta group
			$civi_parent_id = $this->get_group_id(
			
				$this->get_meta_group_source(),
				null, 
				$abort
				
			);
			
		} else {
		
			// get parent or die
			$abort = true;
			
			// what kind of group is this?
			if ( $source == 'member' ) {
				$name = $this->get_group_sync_name( $bp_parent_id );
			} else {
				$name = $this->get_acl_group_sync_name( $bp_parent_id );
			}

			// get the group ID of the parent Civi group
			$civi_parent_id = $this->get_group_id(
				
				$name,
				null, 
				$abort
				
			);
		
		}
		
		// init transaction
		//$transaction = new CRM_Core_Transaction();
		
		// define new group nesting
		$create_params = array(
			'version' => 3,
			'child_group_id' => $civi_group_id,
			'parent_group_id' => $civi_parent_id,
		);
		
		// create Civi group nesting under meta group
		$create_result = civicrm_api( 'group_nesting', 'create', $create_params );
		
		/*
		// debug
		print_r( array(
			'method' => 'create_civi_group_nesting',
			'create_params' => $create_params,
			'create_result' => $create_result,
		) ); //die();
		*/
		
		// error check
		if ( $create_result['is_error'] == '1' ) {
		
			// debug
			print_r( array(
				'method' => 'create_civi_group_nesting',
				'create_params' => $create_params,
				'create_result' => $create_result,
			) ); die();
		
		}
		
		// do the database transaction
	    //$transaction->commit();
	    
	}
	
	
	
	/**
	 * @description: updates a CiviCRM Group's hierarchy when a BuddyPress group's hierarchy is updated
	 * @param int $group_id the numeric ID of the BP group
	 * @param int $parent_id the numeric ID of the parent BP group
	 * @return nothing
	 */
	public function update_civi_group_nesting( $bp_group_id, $bp_parent_id ) {
	
		// init or die
		if ( ! $this->is_active() ) return;
		
		// init transaction
		$transaction = new CRM_Core_Transaction();
		
		
		
		// get group source
		$source = $this->get_group_sync_name( $bp_group_id );
		
		// don't die
		$abort = false;
	
		// get the group ID of the Civi member group
		$civi_group_id = $this->get_group_id(
			
			$source,
			null, 
			$abort
			
		);
		
		// delete existing
		$this->delete_civi_group_nesting( $civi_group_id );
		
		// create new nesting
		$this->create_civi_group_nesting( $civi_group_id, $bp_parent_id, 'member' );
		
		
		
		// define group
		$group_params = array(
			'bp_group_id' => $bp_group_id,
			'id' => $civi_group_id,
			'is_active' => 1,
		);
		
		// get name for the Civi group
		$group_params['source'] = $source;
		
		// use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup()
		$this->update_group( $group_params );
		
		
		
		// get ACL source
		$acl_source = $this->get_acl_group_sync_name( $bp_group_id );
		
		// don't die
		$abort = false;
	
		// get the group ID of the Civi member group
		$civi_acl_group_id = $this->get_group_id(
			
			$acl_source,
			null, 
			$abort
			
		);
		
		// delete existing
		$this->delete_civi_group_nesting( $civi_acl_group_id );
		
		// create new nesting
		$this->create_civi_group_nesting( $civi_acl_group_id, $bp_parent_id, 'acl' );
		
		// define group for update
		$group_params = array(
			'bp_group_id' => $bp_group_id,
			'id' => $civi_acl_group_id,
			'is_active' => 1,
		);
		
		// get name for the Civi ACL group
		$group_params['source'] = $acl_source;
		
		// use our adapted version of CRM_Bridge_OG_Drupal::updateCiviGroup()
		$this->update_group( $group_params );
		


		// do the database transaction
	    $transaction->commit();
	    
	    
	    
		// define group
		$params = array(
			'version' => 3,
			'id' => $civi_group_id,
			'is_active' => 1,
		);
	
		// use API to inspect group
		$group = civicrm_api( 'group', 'get', $params );
		
		/*
		print_r( array(
			'existing_params' => $existing_params,
			'existing_result' => $existing_result,
			'delete_params' => $delete_params,
			'delete_result' => $delete_result,
			'create_params' => $create_params,
			'create_result' => $create_result,
			'new_params' => $new_params,
			'new_result' => $new_result,
			'group' => $group,
		) ); die();
		*/
	
		/*
		if( !$success ) {
			bp_core_add_message( __( 'There was an error syncing; please try again.', 'bp-groups-civicrm-sync' ), 'error' );
		} else {
			bp_core_add_message( __( 'Group hierarchy settings synced successfully.', 'bp-groups-civicrm-sync' ) );
		}
		*/
	
	}
	
	
	
	/**
	 * @description: deletes all CiviCRM Group Nestings for a given Group ID
	 * @return nothing
	 */
	public function delete_civi_group_nesting( $civi_group_id ) {
		
		// define existing group nesting
		$existing_params = array(
			'version' => 3,
			'child_group_id' => $civi_group_id,
		);
		
		// get existing group nesting
		$existing_result = civicrm_api( 'group_nesting', 'get', $existing_params );
		
		/*
		print_r( array(
			'civi_group_id' => $civi_group_id,
			'civi_parent_id' => $civi_parent_id,
			'existing_params' => $existing_params,
			'existing_result' => $existing_result,
		) ); die();
		*/
	
		// did we get any?
		if ( isset( $existing_result['values'] ) AND count( $existing_result['values'] ) > 0 ) {
			
			// loop through them
			foreach( $existing_result['values'] AS $existing_group_nesting ) {
			
				// construct delete array
				$delete_params = array(
					'version' => 3,
					'id' => $existing_group_nesting['id'],
				);

				// clear existing group nesting
				$delete_result = civicrm_api( 'group_nesting', 'delete', $delete_params );
			
			}
		
		}
		
	}
	
	
	
	/**
	 * @description: creates a CiviCRM Group
	 * @return nothing
	 */
	public function create_group( &$params, $group_type = null ) {
		
		// do not die on failure
		$abort = false;
		
		// always use API 3
		$params['version'] = 3;
		
		// if we have a group type passed here, use it
		if ( $group_type ) $params['group_type'] = $group_type;
		
		// use Civi API to create the group (will update if ID is set)
		$group = civicrm_api( 'group', 'create', $params );
		
		// how did we do?
		if ( ! civicrm_error( $group ) ) {
			
			// okay, add it to our params
			$params['group_id'] = $group['id'];
			
		}
		
		// because this is passed by reference, ditch the ID
		unset( $params['id'] );
		
	}
	
	
	
	/**
	 * @description: updates a CiviCRM Group or creates it if it doesn't exist
	 * @return nothing
	 */
	public function update_group( &$params, $group_type = null ) {
		
		// do not die on failure
		$abort = false;
		
		// always use API 3
		$params['version'] = 3;
		
		// get the Civi group ID
		// hand over to our clone of the CRM_Bridge_OG_Utils::groupID method
		$params['id'] = $this->get_group_id(
		
			$params['source'], 
			null, 
			$abort
			
		);
		
		// if we have a group type passed here, use it
		if ( $group_type ) $params['group_type'] = $group_type;
		
		// use Civi API to create the group (will update if ID is set)
		$group = civicrm_api( 'group', 'create', $params );
		
		// how did we do?
		if ( ! civicrm_error( $group ) ) {
			
			// okay, add it to our params
			$params['group_id'] = $group['id'];
			
		}
		
		// because this is passed by reference, ditch the ID
		unset( $params['id'] );
		
	}
	
	
	
	/**
	 * @description: deletes a CiviCRM Group
	 * @return nothing
	 */
	public function delete_group( &$params ) {
		
		// do not die on failure
		$abort = false;
		
		// always use API 3
		$params['version'] = 3;
		
		// get the Civi group ID
		// hand over to our clone of the CRM_Bridge_OG_Utils::groupID method
		$params['id'] = $this->get_group_id(
		
			$params['source'], 
			null, 
			$abort
			
		);
		
		// delete the group only if we have a valid id
		if ( $params['id'] ) {
		
			// delete group
			CRM_Contact_BAO_Group::discard( $params['id'] );
			
			// assign group ID
	        $params['group_id'] = $params['id'];
	        
		}
		
		// because this is passed by reference, ditch the ID
		unset( $params['id'] );
		
	}
	
	
	
	/**
	 * @description: sync group member
	 * @return int $group_id
	 */
	function sync_group_member( &$params, $op ) {
		
		// get the Civi contact ID
		$civi_contact_id = $this->get_contact_id( $params['uf_id'] );
		if ( !$civi_contact_id ) {
			CRM_Core_Error::fatal();
		}
		
		// die if not found
		$abort = true;
		
		// no title please
		$title = null;
		
		// get the Civi group ID of this BuddyPress group
		$civi_group_id = $this->get_group_id(
			
			$this->get_group_sync_name( $params['bp_group_id'] ),
			$title, 
			$abort
			
		);
		
		// init Civi group params
		$groupParams = array(
			'contact_id' => $civi_contact_id,
			'group_id' => $civi_group_id,
			'version' => 3,
		);
		
		// do the operation on the group
		if ($op == 'add') {
			$groupParams['status'] = $params['is_active'] ? 'Added' : 'Pending';
			$group_contact = civicrm_api( 'GroupContact', 'Create', $groupParams );
		} else {
			$groupParams['status'] = 'Removed';
			$group_contact = civicrm_api( 'GroupContact', 'Delete', $groupParams );
		}
		
		// do we have an admin user?
		if ( $params['is_admin'] !== NULL ) {
		
			// get the group ID of the acl group
			$civi_group_id = $this->get_group_id(
				
				$this->get_acl_group_sync_name( $params['bp_group_id'] ),
				$title, 
				$abort
				
			);
			
			// define params
			$groupParams = array(
				'contact_id' => $civi_contact_id,
				'group_id' => $civi_group_id,
				'status' => $params['is_admin'] ? 'Added' : 'Removed',
				'version' => 3,
			);
			
			// either add or remove, depending on role
			if ($params['is_admin']) {
				$acl_group_contact = civicrm_api( 'GroupContact', 'Create', $groupParams );
			} else {
				$acl_group_contact = civicrm_api( 'GroupContact', 'Delete', $groupParams );
			}
			
		}
		
		/*
		print_r( array(
			'group_contact' => $group_contact,
			'acl_group_contact' => $acl_group_contact,
		) ); die();
		*/
		
	}
	
	
	
	/**
	 * @description: get CiviCRM contact ID by WordPress user ID
	 * @return int $group_id
	 */
	public function get_contact_id( $user_id ) {
		
		// init or die
		if ( ! $this->is_active() ) return;
		
		// make sure Civi file is included
		require_once 'CRM/Core/BAO/UFMatch.php';
			
		// do initial search
		$civi_contact_id = CRM_Core_BAO_UFMatch::getContactId( $user_id );
		if ( $civi_contact_id ) {
			return $civi_contact_id;
		}
		
		// else synchronize contact for this user
		$user = get_userdata( $user_id );
		if ( $user ) {
			
			// sync this user
			CRM_Core_BAO_UFMatch::synchronizeUFMatch(
				$user, // user object
				$user->ID, // ID
				$user->user_mail, // unique identifier
				'WordPress', // CMS
				null, // status
				'Individual', // contact type
				null // is_login
			);
			
			// get the Civi contact ID
			$civi_contact_id = CRM_Core_BAO_UFMatch::getContactId( $user_id );
			if ( !$civi_contact_id ) {
				CRM_Core_Error::fatal();
			}
		
		}
		
		// --<
		return $civi_contact_id;
	}
	
	
	
	/**
	 * @description: get CiviCRM group ID
	 * @return int $group_id
	 */
	public function get_group_id( $source, $title = null, $abort = false ) {
		
		// construct query
		$query = "SELECT id FROM civicrm_group WHERE source = %1";
		
		// define replacement
		$params = array( 1 => array( $source, 'String' ) );
		
		// add title search, if present
		if ( $title ) {
			
			// add to query
			$query .= " OR title = %2";
			
			// add to replacements
			$params[2] = array( $title, 'String' );
			
		}
		
		// let Civi get the group ID
		$civi_group_id = CRM_Core_DAO::singleValueQuery( $query, $params );
		
		// check for failure and our flag
		if ( $abort AND !$civi_group_id ) {
		
			// okay, die!!!
			CRM_Core_Error::fatal();
			
		}
		
		// --<
		return $civi_group_id;
		
	}
	
	
	
	/**
	 * @description: update ACL tables
	 * @return nothing
	 */
	public function updateCiviACLTables( $aclParams, $op ) {
		
		// Drupal-esque operation
		if ( $op == 'delete' ) {
			$this->updateCiviACL( $aclParams, $op );
			$this->updateCiviACLEntityRole( $aclParams, $op );
			$this->updateCiviACLRole( $aclParams, $op );
		} else {
			$this->updateCiviACLRole( $aclParams, $op );
			$this->updateCiviACLEntityRole( $aclParams, $op );
			$this->updateCiviACL( $aclParams, $op );
		}
	}
	
	
	
	/**
	 * @description: update ACL role
	 * @return nothing
	 */
	public function updateCiviACLRole( &$params, $op ) {

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

		$weightParams = array('option_group_id' => $optionGroupID);
		$dao->weight = CRM_Utils_Weight::getDefaultWeight( 
			'CRM_Core_DAO_OptionValue',
			$weightParams
		);

		$dao->value = CRM_Utils_Weight::getDefaultWeight( 
			'CRM_Core_DAO_OptionValue',
			$weightParams,
			'value'
		);

		$query = "
			SELECT v.id
			FROM civicrm_option_value v
			WHERE v.option_group_id = %1
			AND v.description     = %2
		";

		$queryParams = array(
			1 => array($optionGroupID, 'Integer'),
			2 => array($params['source'], 'String'),
		);

		$dao->id = CRM_Core_DAO::singleValueQuery( $query, $queryParams );

		$dao->save();
		$params['acl_role_id'] = $dao->value;

	}
	
	
	
	/**
	 * @description: update ACL entity role
	 * @return nothing
	 */
	public function updateCiviACLEntityRole( &$params, $op ) {
	
		$dao = new CRM_ACL_DAO_EntityRole();

		$dao->entity_table = 'civicrm_group';
		$dao->entity_id = $params['acl_group_id'];
		
		if ( $op == 'delete' ) {
			$dao->delete();
			return;
		}

		$dao->acl_role_id = $params['acl_role_id'];

		$dao->find(TRUE);
		$dao->is_active = TRUE;
		$dao->save();
		$params['acl_entity_role_id'] = $dao->id;
		
	}
	
	
	
	/**
	 * @description: update ACL
	 * @return nothing
	 */
	public function updateCiviACL( &$params, $op ) {
		
		$dao = new CRM_ACL_DAO_ACL();

		$dao->object_table = 'civicrm_saved_search';
		$dao->object_id = $params['civicrm_group_id'];

		if ( $op == 'delete' ) {
			$dao->delete();
			return;
		}

		$dao->find(TRUE);

		$dao->entity_table = 'civicrm_acl_role';
		$dao->entity_id    = $params['acl_role_id'];
		$dao->operation    = 'Edit';

		$dao->is_active = TRUE;
		$dao->save();
		$params['acl_id'] = $dao->id;
		
	}
	
	
	
	//##########################################################################
	
	
	
	/**
	 * @description: updates a CiviCRM Contact when a WordPress user is updated
	 * @param int $user a WP user object
	 * @return nothing
	 */
	function _civi_contact_update( $user ) {
		
		// make sure Civi file is included
		require_once 'CRM/Core/BAO/UFMatch.php';
			
		// synchronizeUFMatch returns the contact object
		$civi_contact = CRM_Core_BAO_UFMatch::synchronizeUFMatch(
		
			$user, // user object
			$user->ID, // ID
			$user->user_mail, // unique identifier
			'WordPress', // CMS
			null, // unused
			'Individual' // contact type
			
		);
		
	}
	
	
	
} // class ends





