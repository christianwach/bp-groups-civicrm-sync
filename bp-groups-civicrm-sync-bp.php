<?php
/*
--------------------------------------------------------------------------------
BP_Groups_CiviCRM_Sync_BuddyPress Class
--------------------------------------------------------------------------------
*/

class BP_Groups_CiviCRM_Sync_BuddyPress {

	/** 
	 * properties
	 */
	
	// CiviCRM utilities class
	public $civi;
	
	// flag for overriding sync process
	public $do_not_sync = false;
	
	
	
	/** 
	 * @description: initialises this object
	 * @return object
	 */
	function __construct() {
	
		// add actions for plugin init on BuddyPress init
		add_action( 'bp_setup_globals', array( $this, 'register_hooks' ), 11 );
		
		// --<
		return $this;

	}
	
	
	
	/**
	 * @description: set references to other objects
	 * @return nothing
	 */
	public function set_references( &$civi_object ) {
	
		// store
		$this->civi = $civi_object;
		
	}
	
	
		
	/**
	 * @description: register hooks on BuddyPress loaded
	 * @return nothing
	 */
	public function register_hooks() {
		
		// intercept BuddyPress group creation, late as we can
		add_action( 'groups_create_group', array( $this, 'create_civi_group' ), 100, 3 );
		
		// intercept group details update
		add_action( 'groups_details_updated', array( $this, 'update_civi_group_details' ), 100, 1 );
		
		// intercept BuddyPress group updates, late as we can
		add_action( 'groups_update_group', array( $this, 'update_civi_group' ), 100, 2 );
		
		// intercept prior to BuddyPress group deletion so we still have group data
		add_action( 'groups_before_delete_group', array( $this, 'delete_civi_group' ), 100, 1 );
		
		// group membership hooks: user joins or leaves group
		add_action( 'groups_join_group', array( $this, 'member_just_joined_group' ), 5, 2 );
		add_action( 'groups_leave_group', array( $this, 'civi_delete_group_membership' ), 5, 2 );
		
		// group membership hooks: modified group membership
		add_action( 'groups_promoted_member', array( $this, 'member_changed_status_group' ), 10, 2 );
		add_action( 'groups_demoted_member', array( $this, 'member_changed_status_group' ), 10, 2 );
		add_action( 'groups_unbanned_member', array( $this, 'member_changed_status_group' ), 10, 2 );
		add_action( 'groups_banned_member', array( $this, 'member_changed_status_group' ), 10, 2 );
		add_action( 'groups_removed_member', array( $this, 'member_changed_status_group' ), 10, 2 );
		add_action( 'groups_membership_accepted', array( $this, 'member_changed_status_group' ), 10, 2 );
		add_action( 'groups_accept_invite', array( $this, 'member_changed_status_group' ), 10, 2 );
		
		// test for presence BP Group Hierarchy plugin
		if ( defined( 'BP_GROUP_HIERARCHY_IS_INSTALLED' ) ) {
		
			// the following action allows us to know that the hierarchy has been altered
			add_action( 'bp_group_hierarchy_before_save', array( $this, 'hierarchy_before_change' ) );
			add_action( 'bp_group_hierarchy_after_save', array( $this, 'hierarchy_after_change' ) );
		
		}
		
	}
	
	
		
	//##########################################################################
	
	
	
	/**
	 * @description: test if BuddyPress plugin is active
	 * @return bool
	 */
	public function is_active() {
	
		// bail if no BuddyPress init function
		if ( ! function_exists( 'buddypress' ) ) return false;
		
		// try and init BuddyPress
		return buddypress();
		
	}
	
	
	
	/**
	 * @description: creates a CiviCRM Group when a BuddyPress group is created
	 * @param int $group_id the numeric ID of the BP group
	 * @param int $first_member WP user object
	 * @param int $group the BP group object
	 * @return nothing
	 */
	public function create_civi_group( $group_id, $first_member, $group ) {
	
		// pass to Civi to create groups
		$civi_groups = $this->civi->create_civi_group( $group_id, $first_member, $group );
		
		// did we get any?
		if ( $civi_groups !== false ) {
		
			// update our group meta with the ids of the CiviCRM groups
			groups_update_groupmeta( $group_id, 'civicrm_groups', $civi_groups );
		
		}
	
	}
	
	
	
	/**
	 * @description: updates a CiviCRM Group when a BuddyPress group is updated
	 * @param int $group_id the numeric ID of the BP group
	 * @param int $group the BP group object
	 * @return nothing
	 */
	public function update_civi_group_details( $group_id ) {
	
		// get the group object
		$group = groups_get_group( array( 'group_id' => $group_id ) );
		//print_r( $group ); die();
		
		// pass to Civi to update groups
		$civi_groups = $this->civi->update_civi_group( $group_id, $group );
		
	}
	
	
	
	/**
	 * @description: updates a CiviCRM Group when a BuddyPress group is updated
	 * @param int $group_id the numeric ID of the BP group
	 * @param int $group the BP group object
	 * @return nothing
	 */
	public function update_civi_group( $group_id, $group ) {
		
		/*
		print_r( array( 
			'group_id' => $group_id, 
			'group' => $group,
		) ); die();
		*/

		// pass to Civi to update groups
		$civi_groups = $this->civi->update_civi_group( $group_id, $group );
		
	}
	
	
	
	/**
	 * @description: deletes a CiviCRM Group when a BuddyPress group is deleted
	 * @param int $group_id the numeric ID of the BP group
	 * @return nothing
	 */
	public function delete_civi_group( $group_id ) {
	
		// pass to Civi to delete groups
		$civi_groups = $this->civi->delete_civi_group( $group_id );
		
		// we don't need to delete our meta, as BP will do so
		
	}
	
	
	
	/**
	 * @description: creates a BuddyPress Group given a title and description
	 * @param string $title the title of the BP group
	 * @param string $description the description of the BP group
	 * @return nothing
	 */
	public function create_group( $title, $description, $civi_creator_id = null ) {
	
		// the creator is the current user
		$user_id = bp_loggedin_user_id();
		
		/**
		 * Possible parameters (see function groups_create_group):
		 *	'group_id'
		 *	'creator_id'
		 *	'name'
		 *	'description'
		 *	'slug'
		 *	'status'
		 *	'enable_forum'
		 *	'date_created'
		 */
		$args = array(
			
			// group_id is not passed so that we create a group
			'creator_id' => $user_id,
			'name' => $title,
			'description' => $description,
			'slug' => groups_check_slug( sanitize_title( esc_attr( $title ) ) ),
			'status' => 'public',
			'enable_forum' => 0,
			'date_created' => current_time( 'mysql' ),

		);
		
		// let BuddyPress do the work
		$new_group_id = groups_create_group( $args );
		
		// add some meta
		groups_update_groupmeta( $new_group_id, 'total_member_count', 1 );
		groups_update_groupmeta( $new_group_id, 'last_activity', time() );
		groups_update_groupmeta( $new_group_id, 'invite_status', 'members' );
		
		// --<
		return $new_group_id;
		
	}
	
	
	
	/*
	 * @description: create BuddyPress Group Members given an array of Civi contacts
	 * @param int $group_id the numeric ID of the BP group
	 * @param int $civi_users an array of Civi contact data
	 * @param bool $is_admin makes this member a group admin
	 * @return bool $error or not...
	 */
	public function create_group_members( $group_id, $civi_users, $is_admin = 0 ) {
		
		// do we have any members?
		if ( ! isset( $civi_users ) OR count( $civi_users ) == 0 ) return;
		
		
		
		// add members of this group as admins
		foreach( $civi_users AS $civi_user ) {
	
			// get WP user
			$user = get_user_by( 'email', $civi_user['email'] );
			
			// sanity check
			if ( ! $user ) {
			
				// create a WP user
				$user = $this->_wordpress_create_user( $civi_user );
				
			}
			
			// sanity check
			if ( $user ) {
			
				// try and create membership
				if ( ! $this->create_group_member( $group_id, $user->ID, $is_admin ) ) {
				
					// allow something to be done about it
					do_action( 'bp_groups_civicrm_sync_member_create_failed', $group_id, $user->ID, $is_admin );
				
				}
			
			}
			
		}
		
	}
	
	
	
	/*
	 * @description: creates a BuddyPress Group Membership given a title and description
	 * @param int $group_id the numeric ID of the BP group
	 * @param int $user_id the numeric ID of the WP user
	 * @param bool $is_admin makes this member a group admin
	 * @return bool $success or not...
	 */
	public function create_group_member( $group_id, $user_id, $is_admin = 0 ) {
		
		// User is already a member, just return true
		if ( groups_is_user_member( $user_id, $group_id ) ) return true;
		
		
		
		// set up member
		$new_member = new BP_Groups_Member;
		$new_member->group_id = $group_id;
		$new_member->user_id = $user_id;
		$new_member->inviter_id = 0;
		$new_member->is_admin = $is_admin;
		$new_member->user_title = '';
		$new_member->date_modified = bp_core_current_time();
		$new_member->is_confirmed = 1;
		
		// save the membership
		if ( ! $new_member->save() ) return false;
		
		// --<
		return true;
		
	}
	
	
	
	/**
	 * @description: called when user joins group
	 * @param int $group_id the numeric ID of the BP group
	 * @param int $user_id the numeric ID of the WP user
	 * @return nothing
	 */
	public function member_just_joined_group( $group_id, $user_id ) {
	
		// call upgrade method
		$this->civi_update_group_membership( $user_id, $group_id );
		
	}	
	
	
	
	/*
	 * @description: called when user joins group. Variable order for ($user_id, $group_id) 
	 * is reversed for these hook other than 'groups_join_group', so call a separate function
	 * @param int $group_id the numeric ID of the BP group
	 * @param int $user_id the numeric ID of the WP user
	 * @return nothing
	 */
	public function member_changed_status_group( $user_id, $group_id ) {
	
		// call upgrade method
		$this->civi_update_group_membership( $user_id, $group_id );
		
	}
	
	
	
	/*
	 * @description: inform Civi of membership status change
	 * @param int $user_id the numeric ID of the WP user
	 * @param int $group_id the numeric ID of the BP group
	 * @return nothing
	 */
	public function civi_update_group_membership( $user_id, $group_id ) {
		
		// get current user status
		$status = $this->get_user_group_status( $user_id, $group_id );
		
		// make numeric for Civi
		$is_admin = $status == 'admin' ? 1 : 0;
		
		// assume active
		$is_active = 1;
		
		// is this user active?
		if ( groups_is_user_banned( $user_id, $group_id ) ) $is_active = 0;
		
		// update membership of CiviCRM groups
		$params = array(
			'bp_group_id' => $group_id,
			'uf_id' => $user_id,
			'is_active' => $is_active,
			'is_admin' => $is_admin,
		);
		
		// use clone of CRM_Bridge_OG_Drupal::og()
		$this->civi->sync_group_member( $params, 'add' );
		
		/*
		print_r( array( 
			'user_id' => $user_id, 
			'group_id' => $group_id,
			'status' => $status, 
			'is_admin' => $is_admin, 
			'is_active' => $is_active,
		) ); die();
		*/
		
	}
	
	
	
	/*
	 * @description: inform Civi of membership status change
	 * @param int $group_id the numeric ID of the BP group
	 * @param int $user_id the numeric ID of the WP user
	 * @return nothing
	 */
	public function civi_delete_group_membership( $group_id, $user_id ) {
		
		/*
		print_r( array( 
			'user_id' => $user_id, 
			'group_id' => $group_id,
		) ); die();
		*/
		
		// update membership of CiviCRM groups
		$params = array(
			'bp_group_id' => $group_id,
			'uf_id' => $user_id,
			'is_active' => 0,
			'is_admin' => 0,
		);
		
		// use clone of CRM_Bridge_OG_Drupal::og()
		$this->civi->sync_group_member( $params, 'delete' );
		
	}
	
	
	
	/**
	 * @description: registers when BuddyPress Group Hierarchy plugin is saving a group
	 * @return nothing
	 */
	public function hierarchy_before_change( $group ) {
		
		// init or die
		if ( ! $this->civi->is_active() ) return;
		
		// get parent ID
		$parent_id = isset( $group->vars['parent_id'] ) ? $group->vars['parent_id'] : 0;
		
		// pass to CiviCRM object
		$this->civi->update_civi_group_nesting( $group->id, $parent_id );
	
	}
	
	
	
	/**
	 * @description: registers when BuddyPress Group Hierarchy plugin has saved a group
	 * @return nothing
	 */
	public function hierarchy_after_change( $group ) {
	
		// nothing for now
		
	}
	
	
	
	/*
	 * @description: get BP group membership status for a user
	 * @param int $user_id the numeric ID of the WP user
	 * @param int $group_id the numeric ID of the BP group
	 * @return string $user_group_status
	 */
	public function get_user_group_status( $user_id, $group_id ) {
		
		// access BP
		global $bp;
		
		// init return
		$user_group_status = false;
		
		// the following functionality is modified code from the BP Groupblog plugin
		// Get the current user's group status. 
		// For efficiency, we try first to look at the current group object
		if ( isset( $bp->groups->current_group->id ) && $group_id == $bp->groups->current_group->id ) {
			
			// It's tricky to walk through the admin/mod lists over and over, so let's format
			if ( empty( $bp->groups->current_group->adminlist ) ) {
				$bp->groups->current_group->adminlist = array();
				if ( isset( $bp->groups->current_group->admins ) ) {
					foreach( (array)$bp->groups->current_group->admins as $admin ) {
						if ( isset( $admin->user_id ) ) {
							$bp->groups->current_group->adminlist[] = $admin->user_id;
						}
					}
				}
			}

			if ( empty( $bp->groups->current_group->modlist ) ) {
				$bp->groups->current_group->modlist = array();
				if ( isset( $bp->groups->current_group->mods ) ) {
					foreach( (array)$bp->groups->current_group->mods as $mod ) {
						if ( isset( $mod->user_id ) ) {
							$bp->groups->current_group->modlist[] = $mod->user_id;
						}
					}
				}
			}

			if ( in_array( $user_id, $bp->groups->current_group->adminlist ) ) {
				$user_group_status = 'admin';
			} elseif ( in_array( $user_id, $bp->groups->current_group->modlist ) ) {
				$user_group_status = 'mod';
			} else {
				// I'm assuming that if a user is passed to this function, they're a member
				// Doing an actual lookup is costly. Try to look for an efficient method
				$user_group_status = 'member';
			}
			
		}
		
		// fall back to BP functions if not set
		if ( $user_group_status === false ) {
		
			// use BP functions
			if ( groups_is_user_admin ( $user_id, $group_id ) ) {
				$user_group_status = 'admin';
			} else if ( groups_is_user_mod ( $user_id, $group_id ) ) {
				$user_group_status = 'mod';
			} else if ( groups_is_user_member ( $user_id, $group_id ) ) {
				$user_group_status = 'member';
			}
			
		}
		
		// are we promoting or demoting?
		if ( bp_action_variable( 1 ) AND bp_action_variable( 2 ) ) {
		
			// change user status based on promotion / demotion
			switch( bp_action_variable( 1 ) ) {
		
				case 'promote' :
					$user_group_status = bp_action_variable( 2 );
					break;
			
				case 'demote' :
				case 'ban' :
				case 'unban' :
					$user_group_status = 'member';
					break;
			
			}
		
		}
		
		// --<
		return $user_group_status;

	}
	
	
	
	/*
	 * @description: creates a WordPress User given a Civi contact
	 * @param array $civi_contact the data for the Civi contact
	 * @return mixed $user WP user object or false on failure
	 */
	public function _wordpress_create_user( $civi_contact ) {
	
		// create username from display name
		$user_name = sanitize_title( $civi_contact['display_name'] );
		
		// check if we have a user with that username
		$user_id = username_exists( $user_name );
		
		/*
		print_r( array(
			'in' => '_wordpress_create_user',
			'civi_contact' => $civi_contact,
			'user_name' => $user_name,
			'user_id' => $user_id,
		) ); die();
		*/
		
		// if not, check against email address
		if ( ! $user_id AND email_exists( $civi_contact['email'] ) == false ) {
			
			// generate a random password
			$random_password = wp_generate_password( 
			
				$length = 12, 
				$include_standard_special_chars = false 
				
			);
			
			// remove Civi plugin filters
			remove_action( 'user_register', array( civi_wp(), 'update_user' ) );
			remove_action( 'profile_update', array( civi_wp(), 'update_user' ) );
			
			// remove CiviCRM WordPress Profile Sync filters
			global $civicrm_wp_profile_sync;
			if ( is_object( $civicrm_wp_profile_sync ) ) {
				remove_action( 'user_register', array( $civicrm_wp_profile_sync, 'wordpress_contact_updated' ), 100 );
				remove_action( 'profile_update', array( $civicrm_wp_profile_sync, 'wordpress_contact_updated' ), 100 );
			}
		
			// create the user
			$user_id = wp_insert_user( array(
			
				'user_login' => $user_name, 
				'user_pass' => $random_password, 
				'user_email' => $civi_contact['email'],
				'first_name' => $civi_contact['first_name'],
				'last_name' => $civi_contact['last_name'],
				
			) );
			
		}
		
		// sanity check
		if ( is_numeric( $user_id ) AND $user_id ) {
		
			// return WP user
			return get_user_by( 'id', $user_id );
			
		}
	
		// return error
		return false;
		
	}
	
	
	
} // class ends





