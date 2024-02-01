<?php
/**
 * Job command class.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Bail if WP-CLI is not present.
if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Run BP Groups CiviCRM Sync cron jobs.
 *
 * ## EXAMPLES
 *
 *     $ wp bpgcs job sync_to_bp
 *     Success: Job complete.
 *
 *     $ wp bpgcs job sync_to_civicrm
 *     Success: Job complete.
 *
 * @since 0.5.0
 *
 * @package BP_Groups_CiviCRM_Sync
 */
class BP_Groups_CiviCRM_Sync_CLI_Command_Job extends BP_Groups_CiviCRM_Sync_CLI_Command {

	/**
	 * Correspondences pseudo-cache.
	 *
	 * @since 0.5.0
	 * @var array
	 */
	private $correspondences = [
		'bp_groups'     => [],
		'member_groups' => [],
		'acf_groups'    => [],
		'users'         => [],
	];

	/**
	 * Sync CiviCRM Group Contacts to WordPress Groups.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp bpgcs job sync_to_bp
	 *     Success: Job complete.
	 *
	 * @alias sync-to-bp
	 *
	 * @since 0.5.0
	 *
	 * @param array $args The WP-CLI positional arguments.
	 * @param array $assoc_args The WP-CLI associative arguments.
	 */
	public function sync_to_bp( $args, $assoc_args ) {

		// Bootstrap CiviCRM.
		$this->bootstrap_civicrm();

		$plugin = bp_groups_civicrm_sync();

		// Get all CiviCRM Group Contacts in synced Groups.
		$civicrm_contacts = $plugin->civicrm->group_contact->contacts_get();

		// Ensure each Group Contact exists in the BuddyPress Group.
		if ( ! empty( $civicrm_contacts ) ) {
			foreach ( $civicrm_contacts as $group_contact ) {

				// Get User and Group IDs.
				$user_id     = (int) $group_contact['uf_match.uf_id'];
				$bp_group_id = $plugin->civicrm->group->id_get_by_source( $group_contact['group.source'] );

				// Set admin flag by type of Group.
				$is_admin = 0;
				$type     = $plugin->civicrm->group->type_get_by_source( $group_contact['group.source'] );
				if ( 'acl' === $type ) {
					$is_admin = 1;
				}

				// Update the BuddyPress Group membership.
				$success = 'null';
				if ( groups_is_user_member( $user_id, $bp_group_id ) ) {
					if ( 1 === $is_admin && ! groups_is_user_admin( $user_id, $bp_group_id ) ) {
						$success = $plugin->bp->group_member->promote( $bp_group_id, $user_id, 'admin' );
					}
				} else {
					$success = $plugin->bp->group_member->create( $bp_group_id, $user_id, $is_admin );
				}

				// Feedback.
				$contact_id = (int) $group_contact['contact_id'];
				if ( false === $success ) {
					WP_CLI::log( sprintf( WP_CLI::colorize( '%rFailed to sync User%n %y(Contact ID: %d) (User ID: %d)%n' ), $contact_id, $user_id ) );
				}
				if ( true === $success ) {
					WP_CLI::log( sprintf( WP_CLI::colorize( '%gAdded User to BuddyPress Group%n %y(ID: %d) (Contact ID: %d) (User ID: %d)%n' ), $bp_group_id, $contact_id, $user_id ) );
				}

			}
		}

		// Get all the Group Users in the Synced Groups.
		$group_users = $plugin->bp->group_member->group_users_get();

		// Delete each Group User where the Contact no longer exists in the CiviCRM Member Group.
		if ( ! empty( $group_users ) ) {
			foreach ( $group_users as $group_user ) {

				// Get the CiviCRM Group ID for this BuddyPress Group ID.
				$bp_group_id = (int) $group_user['group_id'];

				// Get the CiviCRM Groups for this BuddyPress Group ID.
				$civicrm_groups = $this->cache_check_for_civicrm_groups_sync_to_bp( $bp_group_id );
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
				$exists = $plugin->civicrm->group_contact->get( $civicrm_groups['member_group_id'], $contact_id );
				if ( ( $exists instanceof CRM_Core_Exception ) || ! empty( $exists ) ) {
					continue;
				}

				// Finally delete the BuddyPress Group membership.
				$success = $plugin->bp->group_member->delete( $bp_group_id, $user_id );
				if ( ! empty( $success ) ) {
					WP_CLI::log( sprintf( WP_CLI::colorize( '%gRemoved User%n %y(User ID: %d) (Contact ID: %d)%n' ), $user_id, $contact_id ) );
				} else {
					WP_CLI::log( sprintf( WP_CLI::colorize( '%rFailed to remove User%n %y(User ID: %d) (Contact ID: %d)%n' ), $user_id, $contact_id ) );
				}

			}
		}

		WP_CLI::log( '' );
		WP_CLI::success( 'Job complete.' );

	}

	/**
	 * Sync WordPress BuddyPress Group Users to CiviCRM Groups.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp bpgcs job sync_to_civicrm
	 *     Success: Executed 'sync_to_civicrm' job.
	 *
	 * @alias sync-to-civicrm
	 *
	 * @since 0.5.0
	 *
	 * @param array $args The WP-CLI positional arguments.
	 * @param array $assoc_args The WP-CLI associative arguments.
	 */
	public function sync_to_civicrm( $args, $assoc_args ) {

		// Bootstrap CiviCRM.
		$this->bootstrap_civicrm();

		$plugin = bp_groups_civicrm_sync();

		// Get all the Group Users in the Synced Groups.
		$group_users = $plugin->bp->group_member->group_users_get();
		if ( ! empty( $group_users ) ) {

			foreach ( $group_users as $group_user ) {

				// Get the CiviCRM Group IDs for this BuddyPress Group ID.
				$bp_group_id = (int) $group_user['group_id'];

				// Check pseudo-cache.
				$bp_group_data = $this->cache_check_for_bp_group( $bp_group_id );
				if ( 'db' === $bp_group_data['source'] ) {
					WP_CLI::log( '' );
					WP_CLI::log( sprintf( WP_CLI::colorize( '%gSyncing Users from BuddyPress Group%n %Y%s%n %y(ID: %d)%n' ), $bp_group_data['title'], (int) $bp_group_id ) );
				}

				// Get the CiviCRM Groups for this BuddyPress Group ID.
				$civicrm_groups = $this->cache_check_for_civicrm_groups_sync_to_civicrm( $bp_group_id );
				if ( empty( $civicrm_groups['member_group_id'] ) && empty( $civicrm_groups['acf_group_id'] ) ) {
					continue;
				}

				// Get the CiviCRM Contact ID for this User ID.
				$user_id    = (int) $group_user['user_id'];
				$contact_id = $this->cache_check_for_contact( $user_id );
				if ( empty( $contact_id ) || false === $contact_id ) {
					WP_CLI::log( sprintf( WP_CLI::colorize( '%bNo Contact ID found for User%n %y(ID: %d)%n' ), $user_id ) );
					continue;
				}

				// Update the CiviCRM Group Contact entries.
				$result = $plugin->bp->group_member->civicrm_group_membership_update( $user_id, $bp_group_id );

				if ( false === $result ) {
					WP_CLI::log( sprintf( WP_CLI::colorize( '%rFailed to sync User%n %y(Contact ID: %d) (User ID: %d)%n' ), $contact_id, $user_id ) );
					continue;
				}

				if ( ! empty( $result['member_group_contact'] ) ) {
					if ( ! empty( $result['member_group_contact']['added'] ) ) {
						WP_CLI::log( sprintf( WP_CLI::colorize( '%gSynced User to Member Group%n %y(Contact ID: %d) (User ID: %d)%n' ), $contact_id, $user_id ) );
					}
				}

				if ( ! empty( $result['acl_group_contact'] ) ) {
					if ( ! empty( $result['acl_group_contact']['added'] ) ) {
						WP_CLI::log( sprintf( WP_CLI::colorize( '%gSynced User to ACL Group%n %y(Contact ID: %d) (User ID: %d)%n' ), $contact_id, $user_id ) );
					}
				}

			}

		}

		// Get all the Group Contacts in the Synced Groups.
		$group_contacts = $plugin->civicrm->group_contact->contacts_get();

		// Delete each Group Contact where the User no longer exists in the BuddyPress Group.
		if ( ! empty( $group_contacts ) && is_array( $group_contacts ) ) {
			$bp_group_ids = [];
			foreach ( $group_contacts as $group_contact ) {

				$user_id     = (int) $group_contact['uf_match.uf_id'];
				$bp_group_id = $plugin->civicrm->group->id_get_by_source( $group_contact['group.source'] );

				// Show feedback each time Group changes.
				if ( ! in_array( $bp_group_id, $bp_group_ids ) ) {

					// Try the pseudo-cache for the BuddyPress Group.
					$bp_group_data = $this->cache_check_for_bp_group( $bp_group_id );

					WP_CLI::log( '' );
					WP_CLI::log( sprintf( WP_CLI::colorize( '%gDeleting Contacts not in BuddyPress Group%n %Y%s%n %y(ID: %d)%n' ), $bp_group_data['title'], $bp_group_id ) );

					$bp_group_ids[] = $bp_group_id;

				}

				// Skip if the Group User exists.
				if ( false === $bp_group_id || groups_is_user_member( $user_id, $bp_group_id ) ) {
					continue;
				}

				// Delete the Group Contact.
				$contact_id = (int) $group_contact['contact_id'];
				$success    = $plugin->civicrm->group_contact->delete( (int) $group_contact['group_id'], $contact_id );
				if ( false !== $success ) {
					WP_CLI::log( sprintf( WP_CLI::colorize( '%gRemoved Contact%n %y(Contact ID: %d) (User ID: %d)%n' ), $contact_id, $user_id, $bp_group_id ) );
				} else {
					WP_CLI::log( sprintf( WP_CLI::colorize( '%rFailed to remove Contact%n %y(Contact ID: %d) (User ID: %d)%n' ), $contact_id, $user_id ) );
				}

			}
		}

		WP_CLI::log( '' );
		WP_CLI::success( 'Job complete.' );

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

		$plugin = bp_groups_civicrm_sync();

		// Query the pseudo-cache for the Contact ID.
		if ( isset( $this->correspondences['users'][ $user_id ] ) ) {
			$contact_id = $this->correspondences['users'][ $user_id ];
		} else {

			// Check the database.
			$contact_id = $plugin->civicrm->contact->id_get_by_user_id( $user_id );
			if ( ! empty( $contact_id ) ) {
				$this->correspondences['users'][ $user_id ] = (int) $contact_id;
			} else {
				$this->correspondences['users'][ $user_id ] = false;
			}

		}

		// --<
		return $contact_id;

	}

	/**
	 * Checks the pseudo-cache for the BuddyPress Group.
	 *
	 * Adds the BuddyPress Group title to the pseudo-cache if not present.
	 *
	 * The return array contains the name of the BuddyPress Group and whether
	 * or not the name has been retrieved from the pseudo-cache or the database.
	 *
	 * @since 0.5.0
	 *
	 * @param integer $group_id The ID of the BuddyPress Group.
	 * @return array $data The array of data for the BuddyPress Group.
	 */
	private function cache_check_for_bp_group( $group_id ) {

		// Init return.
		$data = [];

		// Query the pseudo-cache for the BuddyPress Group.
		if ( ! empty( $this->correspondences['bp_groups'][ $group_id ] ) ) {
			$title  = $this->correspondences['bp_groups'][ $group_id ];
			$source = 'cache';
		} else {

			// Check the database.
			$bp_group = groups_get_group( [ 'group_id' => $group_id ] );
			if ( ! empty( $bp_group->id ) ) {
				$title  = stripslashes( $bp_group->name );
				$source = 'db';
			} else {
				$title  = '';
				$source = false;
			}

			// Populate pseudo-cache.
			$this->correspondences['bp_groups'][ $group_id ] = $title;

		}

		// Populate return.
		$data['title']  = $title;
		$data['source'] = $source;

		// --<
		return $data;

	}

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
	private function cache_check_for_civicrm_groups_sync_to_bp( $group_id ) {

		$plugin = bp_groups_civicrm_sync();

		// Query the pseudo-cache for the Member Group.
		if ( isset( $this->correspondences['member_groups'][ $group_id ] ) ) {
			$member_group_id = $this->correspondences['member_groups'][ $group_id ];
		} else {

			// Check the database.
			$member_group = $plugin->civicrm->group->get_by_bp_id( $group_id, 'member' );
			if ( ! empty( $member_group ) ) {

				// Update the Member Group.
				$bp_group = groups_get_group( [ 'group_id' => $group_id ] );
				if ( ! empty( $bp_group->id ) ) {
					$plugin->bp->group->group_updated( $group_id, $bp_group );
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
			$acl_group = $plugin->civicrm->group->get_by_bp_id( $group_id, 'acl' );
			if ( ! empty( $acl_group ) ) {

				// Update the ACL Group if the Member Group hasn't already done it first.
				if ( empty( $bp_group ) ) {
					$bp_group = groups_get_group( [ 'group_id' => $group_id ] );
					if ( ! empty( $bp_group->id ) ) {
						$plugin->bp->group->group_updated( $group_id, $bp_group );
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
		$civicrm_group_ids = $plugin->bp->group->group_updated( $group_id, $bp_group );
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
	 * Checks the pseudo-cache for the CiviCRM Groups.
	 *
	 * Adds the CiviCRM Group IDs to the pseudo-cache if not present and writes
	 * feedback to STDOUT.
	 *
	 * Updates both CiviCRM Member Group and CiviCRM ACL Group if they exist.
	 * Creates both CiviCRM Groups if they don't.
	 *
	 * @since 0.5.0
	 *
	 * @param integer $group_id The ID of the BuddyPress Group.
	 * @return array $civicrm_groups The keyed array of CiviCRM Group IDs.
	 */
	private function cache_check_for_civicrm_groups_sync_to_civicrm( $group_id ) {

		$plugin = bp_groups_civicrm_sync();

		// Query the pseudo-cache for the Member Group.
		if ( isset( $this->correspondences['member_groups'][ $group_id ] ) ) {
			$member_group_id = $this->correspondences['member_groups'][ $group_id ];
		} else {

			// Check the database.
			$member_group = $plugin->civicrm->group->get_by_bp_id( $group_id, 'member' );
			if ( ! empty( $member_group ) ) {

				// Update the Member Group.
				$bp_group = groups_get_group( [ 'group_id' => $group_id ] );
				if ( ! empty( $bp_group->id ) ) {
					$plugin->bp->group->group_updated( $group_id, $bp_group );
				}

				// Update the pseudo-cache and feedback.
				$member_group_id                                     = (int) $member_group['id'];
				$this->correspondences['member_groups'][ $group_id ] = $member_group_id;
				WP_CLI::log( sprintf( WP_CLI::colorize( '%gSyncing with CiviCRM Member Group%n %Y%s%n %y(ID: %d)%n' ), $member_group['title'], $member_group_id ) );

			}

		}

		// Query the pseudo-cache for the ACL Group.
		if ( isset( $this->correspondences['acl_groups'][ $group_id ] ) ) {
			$acl_group_id = $this->correspondences['acl_groups'][ $group_id ];
		} else {

			// Check the database.
			$acl_group = $plugin->civicrm->group->get_by_bp_id( $group_id, 'acl' );
			if ( ! empty( $acl_group ) ) {

				// Update the ACL Group if the Member Group hasn't already done it first.
				if ( empty( $bp_group ) ) {
					$bp_group = groups_get_group( [ 'group_id' => $group_id ] );
					if ( ! empty( $bp_group->id ) ) {
						$plugin->bp->group->group_updated( $group_id, $bp_group );
					}
				}

				// Update the pseudo-cache and feedback.
				$acl_group_id                                     = (int) $acl_group['id'];
				$this->correspondences['acl_groups'][ $group_id ] = $acl_group_id;
				WP_CLI::log( sprintf( WP_CLI::colorize( '%gSyncing with CiviCRM ACL Group%n %Y%s%n %y(ID: %d)%n' ), $acl_group['title'], $acl_group_id ) );

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
		$civicrm_group_ids = $plugin->bp->group->group_created( $group_id, null, $bp_group );
		if ( false === $civicrm_group_ids ) {
			return $civicrm_groups;
		}

		// Feedback.
		$member_group = $plugin->civicrm->group->get_by_bp_id( $group_id, 'member' );
		if ( ! empty( $member_group ) ) {
			WP_CLI::log( sprintf( WP_CLI::colorize( '%gSyncing with CiviCRM Member Group%n %Y%s%n %y(ID: %d)%n' ), $member_group['title'], $civicrm_group_ids['member_group_id'] ) );
		}
		$acl_group = $plugin->civicrm->group->get_by_bp_id( $group_id, 'acl' );
		if ( ! empty( $acl_group ) ) {
			WP_CLI::log( sprintf( WP_CLI::colorize( '%gSyncing with CiviCRM ACL Group%n %Y%s%n %y(ID: %d)%n' ), $acl_group['title'], $civicrm_group_ids['acl_group_id'] ) );
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

}
