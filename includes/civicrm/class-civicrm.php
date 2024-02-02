<?php
/**
 * CiviCRM Loader.
 *
 * Handles CiviCRM-related functionality.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Class.
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
	 * @var BP_Groups_CiviCRM_Sync
	 */
	public $plugin;

	/**
	 * CiviCRM ACL object.
	 *
	 * @since 0.4
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_CiviCRM_ACL
	 */
	public $acl;

	/**
	 * CiviCRM Contact object.
	 *
	 * @since 0.4
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_CiviCRM_Contact
	 */
	public $contact;

	/**
	 * CiviCRM Group utility object.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_CiviCRM_Group
	 */
	public $group;

	/**
	 * CiviCRM Meta Group object.
	 *
	 * @since 0.4
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_CiviCRM_Group_Meta
	 */
	public $meta_group;

	/**
	 * CiviCRM Group Contact object.
	 *
	 * @since 0.4
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_CiviCRM_Group_Contact
	 */
	public $group_contact;

	/**
	 * CiviCRM Group Nesting object.
	 *
	 * @since 0.4
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_CiviCRM_Group_Nesting
	 */
	public $group_nesting;

	/**
	 * CiviCRM Group Admin object.
	 *
	 * Placeholder.
	 *
	 * @since 0.4
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_CiviCRM_Group_Admin
	 */
	public $group_admin;

	/**
	 * Correspondences pseudo-cache.
	 *
	 * @since 0.5.0
	 * @var array
	 */
	private $correspondences = [
		'member_groups' => [],
		'acf_groups'    => [],
		'users'         => [],
	];

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
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/civicrm/class-civicrm-contact.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/civicrm/class-civicrm-group.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/civicrm/class-civicrm-group-meta.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/civicrm/class-civicrm-group-contact.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/civicrm/class-civicrm-group-nesting.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/civicrm/class-civicrm-acl.php';

	}

	/**
	 * Set up this plugin's objects.
	 *
	 * @since 0.4
	 */
	public function setup_objects() {

		// Initialise objects.
		$this->contact       = new BP_Groups_CiviCRM_Sync_CiviCRM_Contact( $this );
		$this->group         = new BP_Groups_CiviCRM_Sync_CiviCRM_Group( $this );
		$this->meta_group    = new BP_Groups_CiviCRM_Sync_CiviCRM_Group_Meta( $this );
		$this->group_contact = new BP_Groups_CiviCRM_Sync_CiviCRM_Group_Contact( $this );
		$this->group_nesting = new BP_Groups_CiviCRM_Sync_CiviCRM_Group_Nesting( $this );
		$this->acl           = new BP_Groups_CiviCRM_Sync_CiviCRM_ACL( $this );

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

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

	// -----------------------------------------------------------------------------------

	/**
	 * Gets a CiviCRM admin link.
	 *
	 * @since 0.4.4
	 *
	 * @param string $path The CiviCRM path.
	 * @param string $params The CiviCRM parameters.
	 * @return string $link The URL of the CiviCRM page.
	 */
	public function link_admin_get( $path = '', $params = null ) {

		// Init link.
		$link = '';

		// Init CiviCRM or bail.
		if ( ! $this->is_initialised() ) {
			return $link;
		}

		// Use CiviCRM to construct link.
		$link = CRM_Utils_System::url(
			$path, // Path to the resource.
			$params, // Params to pass to resource.
			true, // Force an absolute link.
			null, // Fragment (#anchor) to append.
			true, // Encode special HTML characters.
			false, // CMS front end.
			true // CMS back end.
		);

		// --<
		return $link;

	}

	/**
	 * Check a CiviCRM permission.
	 *
	 * @since 0.5.0
	 *
	 * @param str $permission The permission string.
	 * @return bool $permitted True if allowed, false otherwise.
	 */
	public function check_permission( $permission ) {

		// Always deny if CiviCRM is not active.
		if ( ! $this->is_initialised() ) {
			return false;
		}

		// Deny by default.
		$permitted = false;

		// Check CiviCRM permissions.
		if ( CRM_Core_Permission::check( $permission ) ) {
			$permitted = true;
		}

		/**
		 * Return permission but allow overrides.
		 *
		 * @since 0.5.0
		 *
		 * @param bool $permitted True if allowed, false otherwise.
		 * @param str $permission The CiviCRM permission string.
		 * @return bool $permitted True if allowed, false otherwise.
		 */
		return apply_filters( 'bpgcs/civicrm/permitted', $permitted, $permission );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Syncs all CiviCRM Groups to their corresponding BuddyPress Groups.
	 *
	 * @since 0.5.0
	 */
	public function batch_sync_to_bp_all() {

		// Feedback at the beginning of the process.
		$this->plugin->log_message( '' );
		$this->plugin->log_message( '--------------------------------------------------------------------------------' );
		$this->plugin->log_message( __( 'Syncing all CiviCRM Groups to their corresponding BuddyPress Groups...', 'bp-groups-civicrm-sync' ) );
		$this->plugin->log_message( '--------------------------------------------------------------------------------' );

		// Ensure each Group Contact exists in the BuddyPress Group.
		$civicrm_contacts = $this->group_contact->contacts_get();
		if ( ( $group_contacts instanceof CRM_Core_Exception ) ) {
			$group_contacts = [];
		}
		$this->sync_to_bp_populate( $civicrm_contacts );

		// Get all the Group Users in the Synced Groups.
		$group_users = $this->plugin->bp->group_member->group_users_get();
		$this->sync_to_bp_remove( $group_users );

	}

	/**
	 * Batch sync CiviCRM Groups to BuddyPress Groups.
	 *
	 * @since 0.5.0
	 *
	 * @param string $identifier The batch identifier.
	 */
	public function batch_sync_to_bp( $identifier ) {

		// Get the current Batch.
		$batch        = new BP_Groups_CiviCRM_Sync_Admin_Batch( $identifier );
		$batch_number = $batch->initialise();

		// Set batch count for schedules.
		if ( false !== strpos( $identifier, 'bpgcs_cron' ) ) {
			$batch_count = (int) $this->plugin->admin->setting_get( 'batch_count' );
			$batch->stepper->step_count_set( $batch_count );
		}

		// Call the Batches in order.
		switch ( $batch_number ) {
			case 0:
				$this->batch_sync_populate( $batch );
				break;
			case 1:
				// $this->batch_sync_done( $batch );
				$this->batch_sync_remove( $batch );
				break;
			case 2:
				$this->batch_sync_done( $batch );
				break;
		}

	}

	/**
	 * Batch sync CiviCRM Group Contacts to BuddyPress Group Users.
	 *
	 * @since 0.5.0
	 *
	 * @param BP_Groups_CiviCRM_Sync_Admin_Batch $batch The batch object.
	 */
	public function batch_sync_populate( $batch ) {

		$limit  = $batch->stepper->step_count_get();
		$offset = $batch->stepper->initialise();

		// Get the batch of Group Contacts for this step.
		$civicrm_batch = $this->group_contact->contacts_get( $limit, $offset );
		if ( ( $civicrm_batch instanceof CRM_Core_Exception ) ) {
			$civicrm_batch = [];
		}
		$this->sync_to_bp_populate( $civicrm_batch );

		// Get the next batch of Group Contacts.
		$batch->stepper->next();
		$offset        = $batch->stepper->initialise();
		$civicrm_batch = $this->group_contact->contacts_get( $limit, $offset );
		if ( ( $civicrm_batch instanceof CRM_Core_Exception ) ) {
			$civicrm_batch = [];
		}

		// Move batching onwards.
		if ( empty( $civicrm_batch ) ) {
			$batch->next();
		}

	}

	/**
	 * Batch delete BuddyPress Group Users where the Contact no longer exists in the CiviCRM Group.
	 *
	 * @since 0.5.0
	 *
	 * @param BP_Groups_CiviCRM_Sync_Admin_Batch $batch The batch object.
	 */
	public function batch_sync_remove( $batch ) {

		$limit  = $batch->stepper->step_count_get();
		$offset = $batch->stepper->initialise();

		// Get the batch of Group Users for this step.
		$groups_batch = $this->plugin->bp->group_member->group_users_get( $limit, $offset );
		$this->sync_to_bp_remove( $groups_batch );

		// Get the next batch of Group Users.
		$batch->stepper->next();
		$offset       = $batch->stepper->initialise();
		$groups_batch = $this->plugin->bp->group_member->group_users_get( $limit, $offset );

		// Move batching onwards.
		if ( empty( $groups_batch ) ) {
			$batch->next();
		}

	}

	/**
	 * Batch done.
	 *
	 * @since 0.5.0
	 *
	 * @param BP_Groups_CiviCRM_Sync_Admin_Batch $batch The batch object.
	 */
	public function batch_sync_done( $batch ) {

		// We're finished.
		$batch->delete();
		unset( $batch );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Syncs Group Contacts to to their corresponding BuddyPress Groups.
	 *
	 * @since 0.5.0
	 *
	 * @param array $civicrm_contacts The array of CiviCRM Group Contacts.
	 */
	public function sync_to_bp_populate( $civicrm_contacts ) {

		// Skip when empty.
		if ( empty( $civicrm_contacts ) ) {
			return;
		}

		// Ensure each Group Contact exists in the BuddyPress Group.
		foreach ( $civicrm_contacts as $group_contact ) {

			// Get User and Group IDs.
			$user_id  = (int) $group_contact['uf_match.uf_id'];
			$group_id = $this->group->id_get_by_source( $group_contact['group.source'] );

			// Set admin flag by type of Group.
			$is_admin = 0;
			$type     = $this->group->type_get_by_source( $group_contact['group.source'] );
			if ( 'acl' === $type ) {
				$is_admin = 1;
			}

			// Update the BuddyPress Group membership.
			$success = 'null';
			if ( groups_is_user_member( $user_id, $group_id ) ) {
				if ( 1 === $is_admin && ! groups_is_user_admin( $user_id, $group_id ) ) {
					$success = $this->plugin->bp->group_member->promote( $group_id, $user_id, 'admin' );
				}
			} else {
				$success = $this->plugin->bp->group_member->create( $group_id, $user_id, $is_admin );
			}

			// Feedback.
			$contact_id = (int) $group_contact['contact_id'];
			if ( false === $success ) {
				$this->plugin->log_message(
					sprintf(
						/* translators: 1: The ID of the BuddyPress Group, 2: The ID of the Contact, 3: The ID of the User. */
						__( 'Failed to sync User to BuddyPress Group (ID: %1$d) (Contact ID: %2$d) (User ID: %3$d)', 'bp-groups-civicrm-sync' ),
						$group_id,
						$contact_id,
						$user_id
					)
				);
			}
			if ( true === $success ) {
				$this->plugin->log_message(
					sprintf(
						/* translators: 1: The ID of the BuddyPress Group, 2: The ID of the Contact, 3: The ID of the User. */
						__( 'Added User to BuddyPress Group (ID: %1$d) (Contact ID: %2$d) (User ID: %3$d)', 'bp-groups-civicrm-sync' ),
						$group_id,
						$contact_id,
						$user_id
					)
				);
			}

		}

	}

	/**
	 * Removes Group Users where the Contact no longer exists in the CiviCRM Member Group.
	 *
	 * @since 0.5.0
	 *
	 * @param array $group_users The array of BuddyPress Group Users.
	 */
	public function sync_to_bp_remove( $group_users ) {

		// Skip when empty.
		if ( empty( $group_users ) ) {
			return;
		}

		// Delete each Group User where the Contact no longer exists in the CiviCRM Member Group.
		foreach ( $group_users as $group_user ) {

			// Get the CiviCRM Group ID for this BuddyPress Group ID.
			$group_id = (int) $group_user['group_id'];

			// Get the CiviCRM Groups for this BuddyPress Group ID.
			$civicrm_groups = $this->cache_check_for_civicrm_groups( $group_id );
			if ( empty( $civicrm_groups['member_group_id'] ) && empty( $civicrm_groups['acf_group_id'] ) ) {
				continue;
			}

			// Get the CiviCRM Contact ID for this User ID.
			$user_id    = (int) $group_user['user_id'];
			$contact_id = $this->cache_check_for_contact( $user_id );
			if ( empty( $contact_id ) || false === $contact_id ) {
				continue;
			}

			// Get the existing GroupContact entry in the Member Group.
			$exists = $this->group_contact->get( $civicrm_groups['member_group_id'], $contact_id );
			if ( ( $exists instanceof CRM_Core_Exception ) || ! empty( $exists ) ) {
				continue;
			}

			// Finally delete the BuddyPress Group membership.
			$success = $this->plugin->bp->group_member->delete( $group_id, $user_id );
			if ( ! empty( $success ) ) {
				$this->plugin->log_message(
					/* translators: 1: The ID of the Contact, 2: The ID of the User. */
					sprintf( __( 'Removed User (Contact ID: %1$d) (User ID: %2$d)', 'bp-groups-civicrm-sync' ), $contact_id, $user_id )
				);
			} else {
				$this->plugin->log_message(
					/* translators: 1: The ID of the Contact, 2: The ID of the User. */
					sprintf( __( 'Failed to remove User (Contact ID: %1$d) (User ID: %2$d)', 'bp-groups-civicrm-sync' ), $contact_id, $user_id )
				);
			}

		}

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Checks the pseudo-cache for the CiviCRM Groups.
	 *
	 * Adds the CiviCRM Group IDs to the pseudo-cache if not present.
	 * Updates both CiviCRM Member Group and CiviCRM ACL Group if they exist.
	 * Creates both CiviCRM Groups if they don't.
	 *
	 * @since 0.5.0
	 *
	 * @param integer $group_id The ID of the BuddyPress Group.
	 * @return array $civicrm_groups The keyed array of CiviCRM Group IDs.
	 */
	private function cache_check_for_civicrm_groups( $group_id ) {

		// Query the pseudo-cache for the Member Group.
		if ( isset( $this->correspondences['member_groups'][ $group_id ] ) ) {
			$member_group_id = $this->correspondences['member_groups'][ $group_id ];
		} else {

			// Check the database.
			$member_group = $this->group->get_by_bp_id( $group_id, 'member' );
			if ( ! empty( $member_group ) ) {

				// Update the Member Group.
				$bp_group = groups_get_group( [ 'group_id' => $group_id ] );
				if ( ! empty( $bp_group->id ) ) {
					$this->plugin->bp->group->group_updated( $group_id, $bp_group );
				}

				// Update the pseudo-cache.
				$member_group_id                                     = (int) $member_group['id'];
				$this->correspondences['member_groups'][ $group_id ] = $member_group_id;

			}

		}

		// Query the pseudo-cache for the ACL Group.
		if ( isset( $this->correspondences['acl_groups'][ $group_id ] ) ) {
			$acl_group_id = $this->correspondences['acl_groups'][ $group_id ];
		} else {

			// Check the database.
			$acl_group = $this->group->get_by_bp_id( $group_id, 'acl' );
			if ( ! empty( $acl_group ) ) {

				// Update the ACL Group if the Member Group hasn't already done it first.
				if ( empty( $bp_group ) ) {
					$bp_group = groups_get_group( [ 'group_id' => $group_id ] );
					if ( ! empty( $bp_group->id ) ) {
						$this->plugin->bp->group->group_updated( $group_id, $bp_group );
					}
				}

				// Update the pseudo-cache.
				$acl_group_id                                     = (int) $acl_group['id'];
				$this->correspondences['acl_groups'][ $group_id ] = $acl_group_id;

			}

		}

		// Return early if we have both Group IDs.
		if ( ! empty( $member_group_id ) && ! empty( $acl_group_id ) ) {
			$civicrm_groups = [
				'member_group_id' => $member_group_id,
				'acl_group_id'    => $acl_group_id,
			];
			return $civicrm_groups;
		}

		// Init return.
		$civicrm_groups = [
			'member_group_id' => false,
			'acl_group_id'    => false,
		];

		// Prime pseudo-cache.
		$this->correspondences['member_groups'][ $group_id ] = false;
		$this->correspondences['acl_groups'][ $group_id ]    = false;

		// Grab the full BuddyPress Group.
		$bp_group = groups_get_group( [ 'group_id' => $group_id ] );
		if ( empty( $bp_group->id ) ) {
			return $civicrm_groups;
		}

		// Create the CiviCRM Groups and sync Members.
		$civicrm_group_ids = $this->plugin->bp->group->group_updated( $group_id, $bp_group );
		if ( false === $civicrm_group_ids ) {
			return $civicrm_groups;
		}

		// Populate pseudo-cache.
		$this->correspondences['member_groups'][ $group_id ] = $civicrm_group_ids['member_group_id'];
		$this->correspondences['acl_groups'][ $group_id ]    = $civicrm_group_ids['acl_group_id'];

		// Populate return.
		$civicrm_groups = [
			'member_group_id' => $civicrm_group_ids['member_group_id'],
			'acl_group_id'    => $civicrm_group_ids['acl_group_id'],
		];

		// --<
		return $civicrm_groups;

	}

	/**
	 * Checks the pseudo-cache for the CiviCRM Contact.
	 *
	 * Adds the CiviCRM Contact ID to the pseudo-cache if not present.
	 *
	 * @since 0.5.0
	 *
	 * @param integer $user_id The ID of the WordPress User.
	 * @return integer $contact_id The ID of the CiviCRM Contact, or false on failure.
	 */
	private function cache_check_for_contact( $user_id ) {

		// Init return.
		$contact_id = false;

		// Query the pseudo-cache for the Contact ID.
		if ( isset( $this->correspondences['users'][ $user_id ] ) ) {
			$contact_id = $this->correspondences['users'][ $user_id ];
		} else {

			// Check the database.
			$contact_id = $this->contact->id_get_by_user_id( $user_id );
			if ( ! empty( $contact_id ) ) {
				$this->correspondences['users'][ $user_id ] = (int) $contact_id;
			} else {
				$this->correspondences['users'][ $user_id ] = false;
			}

		}

		// --<
		return $contact_id;

	}

}
