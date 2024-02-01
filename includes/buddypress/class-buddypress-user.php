<?php
/**
 * BuddyPress Users class.
 *
 * Handles BuddyPress User-related functionality.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * BuddyPress Users class.
 *
 * A class that handles BuddyPress Users functionality.
 *
 * @since 0.5.0
 */
class BP_Groups_CiviCRM_Sync_BuddyPress_User {

	/**
	 * Plugin object.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync
	 */
	public $plugin;

	/**
	 * BuddyPress object.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_BuddyPress
	 */
	public $bp;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param object $parent The parent object.
	 */
	public function __construct( $parent ) {

		// Store reference to objects.
		$this->plugin = $parent->plugin;
		$this->bp     = $parent;

		// Boot when CiviCRM object is loaded.
		add_action( 'bpgcs/buddypress/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Initialises this class.
	 *
	 * @since 0.5.0
	 */
	public function initialise() {

		// Bootstrap this class.
		$this->register_hooks();

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 0.5.0
		 */
		do_action( 'bpgcs/buddypress/users/loaded' );

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.5.0
	 */
	public function register_hooks() {

	}

	/**
	 * Unregister hooks.
	 *
	 * @since 0.5.0
	 */
	public function unregister_hooks() {

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Creates a WordPress User given a CiviCRM Contact.
	 *
	 * If this is called because a Contact has been added with "New Contact" in
	 * CiviCRM and some Group has been chosen at the same time, then the call
	 * will come via CRM_Contact_BAO_Contact::create().
	 *
	 * Unfortunately, this method adds the Contact to the Group *before* the
	 * email has been stored so the CiviCRM Contact data does not contain it.
	 * WordPress will let a User be created but they will have no email address!
	 *
	 * In order to get around this, we try and recognise this state of affairs
	 * and listen for the addition of the email address to the Contact.
	 *
	 * @see CRM_Contact_BAO_Contact::create()
	 *
	 * @since 0.1
	 * @since 0.4 Renamed.
	 * @since 0.5.0 Moved and renamed.
	 *
	 * @param array $contact The data for the CiviCRM Contact.
	 * @return mixed $user WordPress User object or false on failure.
	 */
	public function create( $contact ) {

		// Create username from display name.
		$user_name = sanitize_title( sanitize_user( $contact['display_name'] ) );

		// Ensure username is unique.
		$user_name = $this->unique_username( $user_name, $contact );

		/**
		 * Let plugins override the new username.
		 *
		 * @since 0.4
		 *
		 * @param str $user_name The previously-generated WordPress username.
		 * @param array $contact The CiviCRM Contact data.
		 */
		$user_name = apply_filters( 'bpgcs/bp/new_username', $user_name, $contact );

		// Check if we have a User with that username.
		$user_id = username_exists( $user_name );

		// If not, check against email address.
		if ( ! $user_id && false === email_exists( $contact['email'] ) ) {

			// Generate a random password.
			$length                         = 12;
			$include_standard_special_chars = false;
			$random_password                = wp_generate_password( $length, $include_standard_special_chars );

			// Remove filters.
			$this->remove_filters();

			/**
			 * Allow other plugins to be aware of what we're doing.
			 *
			 * @since 0.1
			 *
			 * @param array $contact The CiviCRM Contact data.
			 */
			do_action( 'bp_groups_civicrm_sync_before_insert_user', $contact );

			$user_args = [
				'user_login' => $user_name,
				'user_pass'  => $random_password,
				'user_email' => $contact['email'],
				'first_name' => $contact['first_name'],
				'last_name'  => $contact['last_name'],
			];

			// Create the User.
			$user_id = wp_insert_user( $user_args );

			// Is the email address empty?
			if ( empty( $contact['email'] ) ) {

				// Store this Contact temporarily.
				$this->temp_contact = [
					'civi'    => $contact,
					'user_id' => $user_id,
				];

				// Add callback for the next "Email create" event.
				add_action( 'civicrm_post', [ $this, 'email_add' ], 10, 4 );

			} else {

				// Create a UFMatch record if the User was successfully created.
				if ( ! is_wp_error( $user_id ) && isset( $contact['contact_id'] ) ) {
					$this->plugin->civicrm->contact->ufmatch_create( $contact['contact_id'], $user_id, $contact['email'] );
				}

			}

			// Re-add filters.
			$this->add_filters();

			/**
			 * Allow other plugins to be aware of what we've done.
			 *
			 * @since 0.1
			 *
			 * @param array $contact The CiviCRM Contact data.
			 * @param int $user_id The numeric ID of the User.
			 */
			do_action( 'bp_groups_civicrm_sync_after_insert_user', $contact, $user_id );

		}

		// Return WordPress User if we get one.
		if ( ! empty( $user_id ) && is_numeric( $user_id ) ) {
			return get_user_by( 'id', $user_id );
		}

		// Return error.
		return false;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Called when a CiviCRM Contact's primary email address is updated.
	 *
	 * @since 0.3.1
	 * @since 0.5.0 Renamed.
	 *
	 * @param string  $op The type of database operation.
	 * @param string  $object_name The type of object.
	 * @param integer $object_id The ID of the object.
	 * @param object  $object_ref The object.
	 */
	public function email_add( $op, $object_name, $object_id, $object_ref ) {

		// Target our operation.
		if ( 'create' !== $op ) {
			return;
		}

		// Target our object type.
		if ( 'Email' !== $object_name ) {
			return;
		}

		// Remove callback even if subsequent checks fail.
		remove_action( 'civicrm_post', [ $this, 'email_add' ], 10, 4 );

		// Bail if we don't have a temp Contact.
		if ( ! isset( $this->temp_contact ) ) {
			return;
		}
		if ( ! is_array( $this->temp_contact ) ) {
			unset( $this->temp_contact );
			return;
		}

		// Bail if we have no Email or Contact ID.
		if ( ! isset( $object_ref->email ) || ! isset( $object_ref->contact_id ) ) {
			unset( $this->temp_contact );
			return;
		}

		// Bail if this is not the same Contact as above.
		if ( (int) $object_ref->contact_id !== (int) $this->temp_contact['civi']['contact_id'] ) {
			unset( $this->temp_contact );
			return;
		}

		// Get User ID.
		$user_id = $this->temp_contact['user_id'];

		// Create a UFMatch record.
		$this->plugin->civicrm->contact->ufmatch_create( $object_ref->contact_id, $user_id, $object_ref->email );

		// Remove filters.
		$this->remove_filters();

		/**
		 * Allow other plugins to be aware of what we're doing.
		 *
		 * @since 0.1
		 *
		 * @param array $temp_contact The temporary CiviCRM Contact data.
		 * @param object $object_ref The CiviCRM Contact data object.
		 * @param int $user_id The numeric ID of the User.
		 */
		do_action( 'bp_groups_civicrm_sync_before_update_user', $this->temp_contact, $object_ref, $user_id );

		// Build args array.
		$user_args = [
			'ID'         => $user_id,
			'user_email' => $object_ref->email,
		];

		// Update the WordPress User with this Email address.
		$user_id = wp_update_user( $user_args );

		// Re-add filters.
		$this->add_filters();

		/**
		 * Allow other plugins to be aware of what we've done.
		 *
		 * @since 0.1
		 *
		 * @param array $temp_contact The temporary CiviCRM Contact data.
		 * @param object $object_ref The CiviCRM Contact data object.
		 * @param int $user_id The numeric ID of the User.
		 */
		do_action( 'bp_groups_civicrm_sync_after_update_user', $this->temp_contact, $object_ref, $user_id );

		// Uset in case of repeats.
		unset( $this->temp_contact );

	}

	/**
	 * Generate a unique username for a WordPress User.
	 *
	 * @since 0.4
	 * @since 0.5.0 Moved to this class.
	 *
	 * @param str   $username The previously-generated WordPress username.
	 * @param array $contact The CiviCRM Contact data.
	 * @return str $new_username The modified WordPress username.
	 */
	public function unique_username( $username, $contact ) {

		// Bail if this is already unique.
		if ( ! username_exists( $username ) ) {
			return $username;
		}

		// Init flags.
		$count       = 1;
		$user_exists = 1;

		do {

			// Construct new username with numeric suffix.
			$new_username = sanitize_title( sanitize_user( $contact['display_name'] . ' ' . $count ) );

			// How did we do?
			$user_exists = username_exists( $new_username );

			// Try the next integer.
			$count++;

		} while ( $user_exists > 0 );

		// --<
		return $new_username;

	}

	/**
	 * Remove filters (that we know of) that will interfere with creating a WordPress User.
	 *
	 * @since 0.1
	 * @since 0.5.0 Moved to this class.
	 */
	private function remove_filters() {

		// Get CiviCRM instance.
		$civicrm = civi_wp();

		// Do we have the old-style plugin structure?
		if ( method_exists( $civicrm, 'update_user' ) ) {

			// Remove previous CiviCRM plugin filters.
			remove_action( 'user_register', [ $civicrm, 'update_user' ] );
			remove_action( 'profile_update', [ $civicrm, 'update_user' ] );

		} else {

			// Remove current CiviCRM plugin filters.
			remove_action( 'user_register', [ $civicrm->users, 'update_user' ] );
			remove_action( 'profile_update', [ $civicrm->users, 'update_user' ] );

		}

		// Remove CiviCRM WordPress Profile Sync filters.
		global $civicrm_wp_profile_sync;
		if ( is_object( $civicrm_wp_profile_sync ) ) {
			$civicrm_wp_profile_sync->hooks_wp_remove();
			$civicrm_wp_profile_sync->hooks_bp_remove();
		}

		/**
		 * Broadcast that we're removing User actions.
		 *
		 * @since 0.4
		 */
		do_action( 'bpgcs/remove_filters' );

	}

	/**
	 * Add filters (that we know of) after creating a WordPress User.
	 *
	 * @since 0.1
	 * @since 0.5.0 Moved to this class.
	 */
	private function add_filters() {

		// Get CiviCRM instance.
		$civicrm = civi_wp();

		// Do we have the old-style plugin structure?
		if ( method_exists( $civicrm, 'update_user' ) ) {

			// Re-add previous CiviCRM plugin filters.
			add_action( 'user_register', [ $civicrm, 'update_user' ] );
			add_action( 'profile_update', [ $civicrm, 'update_user' ] );

		} else {

			// Re-add current CiviCRM plugin filters.
			add_action( 'user_register', [ $civicrm->users, 'update_user' ] );
			add_action( 'profile_update', [ $civicrm->users, 'update_user' ] );

		}

		// Re-add CiviCRM WordPress Profile Sync filters.
		global $civicrm_wp_profile_sync;
		if ( is_object( $civicrm_wp_profile_sync ) ) {
			$civicrm_wp_profile_sync->hooks_wp_add();
			$civicrm_wp_profile_sync->hooks_bp_add();
		}

		/**
		 * Let other plugins know that we're adding User actions.
		 *
		 * @since 0.4
		 */
		do_action( 'bpgcs/add_filters' );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get a WordPress User ID for a given CiviCRM Contact ID.
	 *
	 * @since 0.5.0
	 *
	 * @param integer $contact_id The numeric CiviCRM Contact ID.
	 * @return integer|bool $user The WordPress User ID, or false on failure.
	 */
	public function id_get_by_contact_id( $contact_id ) {

		// Bail if no CiviCRM.
		if ( ! $this->plugin->civicrm->is_initialised() ) {
			return false;
		}

		// Search using CiviCRM's logic.
		$user_id = CRM_Core_BAO_UFMatch::getUFId( $contact_id );

		// Cast User ID as boolean if we didn't get one.
		if ( empty( $user_id ) ) {
			$user_id = false;
		}

		/**
		 * Filter the result of the WordPress User lookup.
		 *
		 * You can use this filter to create a WordPress User if none is found.
		 * Return the new WordPress User ID and the Group linkage will be made.
		 *
		 * @since 0.5.0
		 *
		 * @param integer|bool $user_id The numeric ID of the WordPress User.
		 * @param integer $contact_id The numeric ID of the CiviCRM Contact.
		 */
		$user_id = apply_filters( 'bpgcs/bp/user/id_get_by_contact_id', $user_id, $contact_id );

		// --<
		return $user_id;

	}

	/**
	 * Get a WordPress User object for a given CiviCRM Contact ID.
	 *
	 * @since 0.5.0
	 *
	 * @param integer $contact_id The numeric CiviCRM Contact ID.
	 * @return WP_User|bool $user The WordPress User object, or false on failure.
	 */
	public function user_get_by_contact_id( $contact_id ) {

		// Get WordPress User ID.
		$user_id = $this->id_get_by_contact_id( $contact_id );

		// Bail if we didn't get one.
		if ( empty( $user_id ) || false === $user_id ) {
			return false;
		}

		// Get User object.
		$user = new WP_User( $user_id );

		// Bail if we didn't get one.
		if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
			return false;
		}

		// --<
		return $user;

	}

}
