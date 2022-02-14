<?php
/**
 * CiviCRM Group Admin Class.
 *
 * Handles functionality related to CiviCRM Group Admin.
 *
 * @package BP_Groups_CiviCRM_Sync
 * @since 0.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * BP Groups CiviCRM Sync CiviCRM Group Admin Class.
 *
 * A class that encapsulates functionality related to CiviCRM Group Admin.
 *
 * @since 0.4
 */
class BP_Groups_CiviCRM_Sync_CiviCRM_Group_Admin {

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
		do_action( 'bpgcs/civicrm/group/nesting/loaded' );

	}



	/**
	 * Register hooks.
	 *
	 * @since 0.4
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
	 * Register directories that CiviCRM searches for php and template files.
	 *
	 * @since 0.1
	 *
	 * @param object $config The CiviCRM config object.
	 */
	public function register_directories( &$config ) {

		// Define our custom path.
		$custom_path = BP_GROUPS_CIVICRM_SYNC_PATH . 'assets/civicrm/custom_templates';

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
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
	 * Enables a BuddyPress Group to be created when creating a CiviCRM Group.
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
		$group = $form->getVar( '_group' );

		// If we have a Group, bail.
		if ( ! empty( $group ) ) {
			return;
		}

		// Okay, it's the new Group form.

		// Add text.
		$form->assign( 'bpgcs_title', __( 'BuddyPress Group Sync', 'bp-groups-civicrm-sync' ) );
		$form->assign( 'bpgcs_description', sprintf(
			/* translators: 1: The opening strong tag, 2: The closing strong tag */
			__( '%1$sNOTE:%2$s If you are going to create a BuddyPress Group, you only need to fill out the "Group Title" field (and optionally the "Group Description" field). The Group Type will be automatically set to "Access Control" and (if a container group has been specified) the Parent Group will be automatically assigned to the container group.', 'bp-groups-civicrm-sync' ),
			'<strong>',
			'</strong>'
		) );
		$form->assign( 'bpgcs_label', __( 'Create a BuddyPress Group', 'bp-groups-civicrm-sync' ) );

		// Add the field element in the form.
		$form->add( 'checkbox', 'bpgcs_create_from_new', __( 'Create BuddyPress Group', 'bp-groups-civicrm-sync' ) );

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
		if ( ! isset( $values['bpgcs_create_from_new'] ) ) {
			return;
		}
		if ( $values['bpgcs_create_from_new'] != '1' ) {
			return;
		}

		// The Group hasn't been created yet, but the data is there.

		// Get CiviCRM Group.
		$group = new stdClass();
		$group->title = $values['title'];
		$group->description = $values['description'];

		// Convert to BuddyPress Group.
		$this->civicrm_group_to_bp_group_convert( $group );

	}



	/**
	 * Create a BuddyPress Group from a CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param object $group The CiviCRM Group object.
	 */
	public function civicrm_group_to_bp_group_convert( $group ) {

		// Remove hooks to prevent recursion.
		$this->bp->unregister_hooks_groups();

		// Create the BuddyPress Group.
		$bp_group_id = $this->bp->group_create( $group->title, $group->description );

		// Re-add hooks.
		$this->bp->register_hooks_groups();

		// Get all Contacts in this Group.
		$params = [
			'version' => 3,
			'group' => $group->id,
		];

		// Use API to get Members.
		$group_admins = civicrm_api( 'Contact', 'get', $params );

		// Do we have any Members?
		if ( isset( $group_admins['values'] ) && count( $group_admins['values'] ) > 0 ) {

			// Make Admins.
			$is_admin = 1;

			// Create Memberships from the CiviCRM Contacts.
			$this->bp->group_members_create( $bp_group_id, $group_admins['values'], $is_admin );

		}

		// Get source safely.
		$source = isset( $group->source ) ? $group->source : '';

		// Get the non-ACL CiviCRM Group ID.
		$civicrm_group_id = $this->civicrm->group_id_find(
			str_replace( 'OG Sync Group ACL', 'OG Sync Group', $source )
		);

		// Get all Contacts in this Group.
		$params = [
			'version' => 3,
			'group' => $civicrm_group_id,
		];

		// Use API to get Members.
		$group_members = civicrm_api( 'Contact', 'get', $params );

		// Do we have any Members?
		if ( isset( $group_members['values'] ) && count( $group_members['values'] ) > 0 ) {

			// Make Members.
			$is_admin = 0;

			// Create Memberships from the CiviCRM Contacts.
			$this->bp->group_members_create( $bp_group_id, $group_members['values'], $is_admin );

		}

		// Update the "source" field for both CiviCRM Groups.

		// Define CiviCRM ACL Group.
		$acl_group_params = [
			'version' => 3,
			'id' => $civicrm_group->id,
		];

		// Get name for the CiviCRM Group.
		$acl_group_params['source'] = $this->civicrm->acl_group_get_sync_name( $bp_group_id );

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
			'id' => $civicrm_group_id,
		];

		// Get name for the CiviCRM Group.
		$member_group_params['source'] = $this->civicrm->member_group_get_sync_name( $bp_group_id );

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

		// If no parent, maybe assign both CiviCRM Groups to the Meta Group.
		if ( empty( $civicrm_group['parents'] ) ) {
			$this->civicrm->group_nesting->nesting_update( $bp_group_id );
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
		$group = $form->getVar( '_group' );

		// Get source safely.
		$source = isset( $group->source ) ? $group->source : '';

		/*
		 * In Drupal, Organic Groups are synced with 'OG Sync Group :GID:'.
		 * Related Organic Groups ACL Groups are synced with 'OG Sync Group ACL :GID:'.
		 */

		// Is this an Organic Groups Administrator Group?
		if ( strstr( $source, 'OG Sync Group ACL' ) === false ) {
			return;
		}

		// Add text.
		$form->assign( 'bpgcs_title', __( 'BuddyPress Group Sync', 'bp-groups-civicrm-sync' ) );
		$form->assign( 'bpgcs_description', sprintf(
			/* translators: 1: The opening strong tag, 2: The closing strong tag */
			__( '%1$sWARNING:%2$s You may wish to make sure your CiviCRM Contacts exist as WordPress Users before creating this group. CiviCRM Contacts that do not have a corresponding WordPress User will have one created for them. You will need to review roles for the new WordPress Users when this process is complete.', 'bp-groups-civicrm-sync' ),
			'<strong>',
			'</strong>'
		) );
		$form->assign( 'bpgcs_label', __( 'Convert to BuddyPress Group', 'bp-groups-civicrm-sync' ) );

		// Add the field element in the form.
		$form->add( 'checkbox', 'bpgcs_create_from_og', __( 'Create BuddyPress Group', 'bp-groups-civicrm-sync' ) );

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
		if ( ! isset( $values['bpgcs_create_from_og'] ) ) {
			return;
		}
		if ( $values['bpgcs_create_from_og'] != '1' ) {
			return;
		}

		// Get CiviCRM Group.
		$group = $form->getVar( '_group' );

		// Convert to BuddyPress Group.
		$this->og_group_to_bp_group_convert( $group );

	}



	/**
	 * Convert all legacy Organic Groups CiviCRM Groups to BuddyPress CiviCRM Groups.
	 *
	 * @since 0.1
	 */
	public function og_groups_to_bp_groups_convert() {

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return;
		}

		// Get all Groups.
		$groups = $this->civicrm->groups_get_all();
		if ( empty( $groups ) ) {
			return;
		}

		// Loop through them.
		foreach ( $groups as $group ) {

			// Send for processing.
			$this->og_group_to_bp_group_convert( (object) $group );

		}

		// Assign to Meta Group.
		$this->civicrm->meta_group->groups_assign();

	}



	/**
	 * Create a BuddyPress Group based on pre-existing CiviCRM/Drupal/Organic Groups.
	 *
	 * @since 0.1
	 *
	 * @param object $group The CiviCRM Group object.
	 */
	public function og_group_to_bp_group_convert( $group ) {

		// Get source.
		$source = $group->source;

		/*
		 * In Drupal, Organic Groups are synced with 'OG Sync Group :GID:'.
		 * Related Organic Groups ACL Groups are synced with 'OG Sync Group ACL :GID:'.
		 */

		// Is this an Organic Groups Administrator Group?
		if ( strstr( $source, 'OG Sync Group ACL' ) === false ) {
			return;
		}

		// Sanitise title by stripping suffix.
		$bp_group_title = array_shift( explode( ': Administrator', $group->title ) );

		// Remove hooks to prevent recursion.
		$this->bp->unregister_hooks_groups();

		// Create the BuddyPress Group.
		$bp_group_id = $this->bp->group_create( $bp_group_title, $group->description );

		// Re-add hooks.
		$this->bp->register_hooks_groups();

		// Get all Contacts in this Group.
		$params = [
			'version' => 3,
			'group' => $group->id,
		];

		// Use API to get Members.
		$group_admins = civicrm_api( 'Contact', 'get', $params );

		// Do we have any Members?
		if ( isset( $group_admins['values'] ) && count( $group_admins['values'] ) > 0 ) {

			// Make Admins.
			$is_admin = 1;

			// Create Memberships from the CiviCRM Contacts.
			$this->bp->group_members_create( $bp_group_id, $group_admins['values'], $is_admin );

		}

		// Get the non-ACL CiviCRM Group ID.
		$civicrm_group_id = $this->civicrm->group_id_find(
			str_replace( 'OG Sync Group ACL', 'OG Sync Group', $source )
		);

		// Get all Contacts in this Group.
		$params = [
			'version' => 3,
			'group' => $civicrm_group_id,
		];

		// Use API to get Members.
		$group_members = civicrm_api( 'Contact', 'get', $params );

		// Do we have any Members?
		if ( isset( $group_members['values'] ) && count( $group_members['values'] ) > 0 ) {

			// Make Members.
			$is_admin = 0;

			// Create Memberships from the CiviCRM Contacts.
			$this->bp->group_members_create( $bp_group_id, $group_members['values'], $is_admin );

		}

		// Update the "source" field for both CiviCRM Groups.

		// Define CiviCRM ACL Group.
		$acl_group_params = [
			'version' => 3,
			'id' => $civicrm_group->id,
		];

		// Get name for the CiviCRM Group.
		$acl_group_params['source'] = $this->civicrm->acl_group_get_sync_name( $bp_group_id );

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
			'id' => $civicrm_group_id,
		];

		// Get name for the CiviCRM Group.
		$member_group_params['source'] = $this->civicrm->member_group_get_sync_name( $bp_group_id );

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

		// If no parent, maybe assign both CiviCRM Groups to the Meta Group.
		if ( empty( $civicrm_group['parents'] ) ) {
			$this->civicrm->group_nesting->nesting_update( $bp_group_id );
		}

	}



	/**
	 * Checks if a CiviCRM Group has an associated Organic Group.
	 *
	 * @since 0.4
	 *
	 * @param array $group The array of CiviCRM Group data.
	 * @return boolean $has_group True if CiviCRM Group has an Organic Group, false if not.
	 */
	public function has_og_group( $group ) {

		// Bail if the "source" contains no info.
		if ( empty( $group['source'] ) ) {
			return false;
		}

		// If the Group "source" has no reference to OG, then it's not.
		if ( strstr( $group['source'], 'OG Sync Group' ) === false ) {
			return false;
		}

		// --<
		return true;

	}



	/**
	 * Do we have any legacy Organic Groups CiviCRM Groups?
	 *
	 * @since 0.1
	 *
	 * @return bool True if legacy Organic Groups CiviCRM Groups are found, false if not.
	 */
	public function has_og_groups() {

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return false;
		}

		// Define params.
		$params = [
			'version' => 3,
			'source' => [
				'LIKE' => '%OG Sync Group%',
			],
		];

		// Call the CiviCRM API.
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
			return false;
		}

		// Bail if there are no values.
		if ( empty( $result['values'] ) ) {
			return false;
		}

		// --<
		return true;

	}



} // Class ends.
