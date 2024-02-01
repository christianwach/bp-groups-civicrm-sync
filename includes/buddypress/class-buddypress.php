<?php
/**
 * BuddyPress Loader.
 *
 * Handles BuddyPress-related functionality.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * BP Groups CiviCRM Sync BuddyPress Class.
 *
 * A class that encapsulates BuddyPress-related functionality.
 *
 * @since 0.1
 */
class BP_Groups_CiviCRM_Sync_BuddyPress {

	/**
	 * Plugin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync
	 */
	public $plugin;

	/**
	 * Users utility object.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_BuddyPress_User
	 */
	public $user;

	/**
	 * Groups utility object.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_BuddyPress_Group
	 */
	public $group;

	/**
	 * Group Members utility object.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_BuddyPress_Group_Member
	 */
	public $group_member;

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

		// Register hooks on BuddyPress init.
		add_action( 'bp_setup_globals', [ $this, 'register_hooks' ], 11 );

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 0.4
		 */
		do_action( 'bpgcs/buddypress/loaded' );

	}

	/**
	 * Include files.
	 *
	 * @since 0.4
	 */
	public function include_files() {

		// Include class files.
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/buddypress/class-buddypress-user.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/buddypress/class-buddypress-group.php';
		require BP_GROUPS_CIVICRM_SYNC_PATH . 'includes/buddypress/class-buddypress-group-member.php';

	}

	/**
	 * Set up this plugin's objects.
	 *
	 * @since 0.4
	 */
	public function setup_objects() {

		// Initialise objects.
		$this->user         = new BP_Groups_CiviCRM_Sync_BuddyPress_User( $this );
		$this->group        = new BP_Groups_CiviCRM_Sync_BuddyPress_Group( $this );
		$this->group_member = new BP_Groups_CiviCRM_Sync_BuddyPress_Group_Member( $this );

	}

	/**
	 * Register hooks on BuddyPress loaded.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

	}

	/**
	 * Checks if BuddyPress plugin is properly configured.
	 *
	 * @since 0.4
	 *
	 * @return bool True if properly configured, false otherwise.
	 */
	public function is_configured() {

		static $bp_initialised;
		if ( isset( $bp_initialised ) ) {
			return $bp_initialised;
		}

		// Assume not configured.
		$bp_initialised = false;

		// Is the Groups component active?
		if ( bp_is_active( 'groups' ) ) {
			$bp_initialised = true;
		}

		// --<
		return $bp_initialised;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Syncs all BuddyPress Groups to their corresponding CiviCRM Groups.
	 *
	 * @since 0.5.0
	 */
	public function batch_sync_to_civicrm_all() {

		// Feedback at the beginning of the process.
		$this->plugin->log_message( '' );
		$this->plugin->log_message( '--------------------------------------------------------------------------------' );
		$this->plugin->log_message( __( 'Syncing all BuddyPress Groups to their corresponding CiviCRM Groups...', 'bp-groups-civicrm-sync' ) );
		$this->plugin->log_message( '--------------------------------------------------------------------------------' );

		// Get all the Group Users in the Synced Groups.
		$group_users = $this->group_member->group_users_get();
		$this->sync_to_civicrm_populate( $group_users );

		// Get all the Group Contacts in the Synced Groups.
		$group_contacts = $this->plugin->civicrm->group_contact->contacts_get();
		$this->sync_to_civicrm_remove( $group_contacts );

	}

	/**
	 * Batch sync BuddyPress Groups to CiviCRM Groups.
	 *
	 * @since 0.5.0
	 *
	 * @param string $identifier The batch identifier.
	 */
	public function batch_sync_to_civicrm( $identifier ) {

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
				$this->batch_sync_remove( $batch );
				break;
			case 2:
				$this->batch_sync_done( $batch );
				break;
		}

	}

	/**
	 * Batch sync BuddyPress Group Users to CiviCRM Group Contacts.
	 *
	 * @since 0.5.0
	 *
	 * @param BP_Groups_CiviCRM_Sync_Admin_Batch $batch The batch object.
	 */
	public function batch_sync_populate( $batch ) {

		$limit  = $batch->stepper->step_count_get();
		$offset = $batch->stepper->initialise();

		// Get the batch of Group Users for this step.
		$groups_batch = $this->group_member->group_users_get( $limit, $offset );
		$this->sync_to_civicrm_populate( $groups_batch );

		// Get the next batch of Group Users.
		$batch->stepper->next();
		$offset       = $batch->stepper->initialise();
		$groups_batch = $this->group_member->group_users_get( $limit, $offset );

		// Move batching onwards.
		if ( empty( $groups_batch ) ) {
			$batch->next();
		}

	}

	/**
	 * Batch delete CiviCRM Group Contacts where the User no longer exists in the BuddyPress Group.
	 *
	 * @since 0.5.0
	 *
	 * @param BP_Groups_CiviCRM_Sync_Admin_Batch $batch The batch object.
	 */
	public function batch_sync_remove( $batch ) {

		$limit  = $batch->stepper->step_count_get();
		$offset = $batch->stepper->initialise();

		// Get the batch of Group Contacts for this step.
		$civicrm_batch = $this->plugin->civicrm->group_contact->contacts_get( $limit, $offset );
		$this->sync_to_civicrm_remove( $civicrm_batch );

		// Get the next batch of Group Contacts.
		$batch->stepper->next();
		$offset        = $batch->stepper->initialise();
		$civicrm_batch = $this->plugin->civicrm->group_contact->contacts_get( $limit, $offset );

		// Move batching onwards.
		if ( empty( $civicrm_batch ) ) {
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
	 * Syncs BuddyPress Group Users to to their corresponding CiviCRM Groups.
	 *
	 * @since 0.5.0
	 *
	 * @param array $group_users The array of BuddyPress Group Users.
	 */
	public function sync_to_civicrm_populate( $group_users ) {

		// Skip when empty.
		if ( empty( $group_users ) ) {
			return;
		}

		// Ensure each Group User has a CiviCRM Group Contact.
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

			// Update the CiviCRM Group Contact entries.
			$result = $this->group_member->civicrm_group_membership_update( $user_id, $group_id );

			if ( false === $result ) {
				$this->plugin->log_message(
					/* translators: 1: The ID of the Contact, 2: The ID of the User. */
					sprintf( __( 'Failed to sync User (Contact ID: %1$d) (User ID: %2$d)', 'bp-groups-civicrm-sync' ), $contact_id, $user_id )
				);
				continue;
			}

			if ( ! empty( $result['member_group_contact'] ) ) {
				if ( ! empty( $result['member_group_contact']['added'] ) ) {
					$this->plugin->log_message(
						/* translators: 1: The ID of the Contact, 2: The ID of the User. */
						sprintf( __( 'Added User to Member Group (Contact ID: %1$d) (User ID: %2$d)', 'bp-groups-civicrm-sync' ), $contact_id, $user_id )
					);
				}
			}

			if ( ! empty( $result['acl_group_contact'] ) ) {
				if ( ! empty( $result['acl_group_contact']['added'] ) ) {
					$this->plugin->log_message(
						/* translators: 1: The ID of the Contact, 2: The ID of the User. */
						sprintf( __( 'Added User to ACL Group (Contact ID: %1$d) (User ID: %2$d)', 'bp-groups-civicrm-sync' ), $contact_id, $user_id )
					);
				}
			}

		}

	}

	/**
	 * Removes Group Contacts where the User no longer exists in the BuddyPress Group.
	 *
	 * @since 0.5.0
	 *
	 * @param array $group_contacts The array of CiviCRM Group Contacts.
	 */
	public function sync_to_civicrm_remove( $group_contacts ) {

		// Skip when empty.
		if ( empty( $group_contacts ) ) {
			return;
		}

		// Delete each Group Contact where the User no longer exists in the BuddyPress Group.
		foreach ( $group_contacts as $group_contact ) {

			// Skip if the Group User exists.
			$user_id  = (int) $group_contact['uf_match.uf_id'];
			$group_id = $this->plugin->civicrm->group->id_get_by_source( $group_contact['group.source'] );
			if ( false === $group_id || groups_is_user_member( $user_id, $group_id ) ) {
				continue;
			}

			// Delete the Group Contact.
			$contact_id = (int) $group_contact['contact_id'];
			$success    = $this->plugin->civicrm->group_contact->delete( (int) $group_contact['group_id'], $contact_id );
			if ( false !== $success ) {
				$this->plugin->log_message(
					/* translators: 1: The ID of the Contact, 2: The ID of the User. */
					sprintf( __( 'Removed Contact (Contact ID: %1$d) (User ID: %2$d)', 'bp-groups-civicrm-sync' ), $contact_id, $user_id )
				);
			} else {
				$this->plugin->log_message(
					/* translators: 1: The ID of the Contact, 2: The ID of the User. */
					sprintf( __( 'Failed to remove Contact (Contact ID: %1$d) (User ID: %2$d)', 'bp-groups-civicrm-sync' ), $contact_id, $user_id )
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
			$member_group = $this->plugin->civicrm->group->get_by_bp_id( $group_id, 'member' );
			if ( ! empty( $member_group ) ) {

				// Update the Member Group.
				$bp_group = groups_get_group( [ 'group_id' => $group_id ] );
				if ( ! empty( $bp_group->id ) ) {
					$this->group->group_updated( $group_id, $bp_group );
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
			$acl_group = $this->plugin->civicrm->group->get_by_bp_id( $group_id, 'acl' );
			if ( ! empty( $acl_group ) ) {

				// Update the ACL Group if the Member Group hasn't already done it first.
				if ( empty( $bp_group ) ) {
					$bp_group = groups_get_group( [ 'group_id' => $group_id ] );
					if ( ! empty( $bp_group->id ) ) {
						$this->group->group_updated( $group_id, $bp_group );
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

		// Create the CiviCRM Groups.
		$civicrm_group_ids = $this->group->group_created( $group_id, null, $bp_group );
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
			$contact_id = $this->plugin->civicrm->contact->id_get_by_user_id( $user_id );
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
