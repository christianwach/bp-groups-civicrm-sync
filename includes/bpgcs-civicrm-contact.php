<?php
/**
 * CiviCRM Contact Class.
 *
 * Handles functionality related to CiviCRM Contacts.
 *
 * @package BP_Groups_CiviCRM_Sync
 * @since 0.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * BP Groups CiviCRM Sync CiviCRM Contact Class.
 *
 * A class that encapsulates functionality related to CiviCRM Contacts.
 *
 * @since 0.4
 */
class BP_Groups_CiviCRM_Sync_CiviCRM_Contact {

	/**
	 * Plugin object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * CiviCRM object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $civicrm The CiviCRM object.
	 */
	public $civicrm;

	/**
	 * BuddyPress object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $bp The BuddyPress object.
	 */
	public $bp;

	/**
	 * Admin object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $admin The Admin object.
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
		do_action( 'bpgcs/civicrm/contact/loaded' );

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
	 * Gets the data for multiple CiviCRM Contacts for an array of Contact IDs.
	 *
	 * @since 0.4
	 *
	 * @param array $contact_ids The array of CiviCRM Contact IDs.
	 * @return array $contacts The array of data for multiple CiviCRM Contacts.
	 */
	public function contacts_get_by_ids( $contact_ids ) {

		// Init return.
		$contacts = [];

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $contacts;
		}

		// Get the Contacts with the given IDs.
		$params = [
			'version' => 3,
			'id' => [
				'IN' => $contact_ids,
			],
			'options' => [
				'limit' => 0,
			],
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'Contact', 'get', $params );

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
			return $contacts;
		}

		// Bail if there are no values.
		if ( empty( $result['values'] ) ) {
			return $contacts;
		}

		// We want the array of values.
		$contacts = $result['values'];

		// --<
		return $contacts;

	}

	// -------------------------------------------------------------------------

	/**
	 * Gets the CiviCRM Contact ID for a given WordPress User ID.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class and renamed.
	 *
	 * @param int $user_id The numeric ID of the WordPress User.
	 * @return int|bool $contact_id The numeric ID of the CiviCRM Contact, false on failure.
	 */
	public function id_get_by_user_id( $user_id ) {

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return false;
		}

		// Make sure CiviCRM file is included.
		require_once 'CRM/Core/BAO/UFMatch.php';

		// Do initial search.
		$contact_id = CRM_Core_BAO_UFMatch::getContactId( $user_id );
		if ( ! empty( $contact_id ) && is_numeric( $contact_id ) ) {
			return $contact_id;
		}

		// Get the User object.
		$user = new WP_User( $user_id );
		if ( ! $user->exists() ) {
			return false;
		}

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
		$contact_id = CRM_Core_BAO_UFMatch::getContactId( $user->ID );

		// Log and bail on failure.
		if ( ! $contact_id ) {
			if ( BP_GROUPS_CIVICRM_SYNC_DEBUG ) {
				$error = sprintf(
					/* translators: %d: The numeric ID of the WordPress User */
					__( 'No CiviCRM Contact ID could be found for WordPress User ID %d', 'bp-groups-civicrm-sync' ),
					$user_id
				);
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( [
					'method' => __METHOD__,
					'error' => $error,
					'user' => $user,
					'backtrace' => $trace,
				], true ) );
			}
			return false;
		}

		// --<
		return $contact_id;

	}

	// -------------------------------------------------------------------------

	/**
	 * Create a link between a WordPress User and a CiviCRM Contact.
	 *
	 * This method optionally allows a Domain ID to be specified.
	 *
	 * @since 0.4
	 *
	 * @param integer $contact_id The numeric ID of the CiviCRM Contact.
	 * @param integer $user_id The numeric ID of the WordPress User.
	 * @param str     $username The WordPress username.
	 * @param integer $domain_id The CiviCRM Domain ID (defaults to current Domain ID).
	 * @return array|bool The UFMatch data on success, or false on failure.
	 */
	public function ufmatch_create( $contact_id, $user_id, $username, $domain_id = '' ) {

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return false;
		}

		// Sanity checks.
		if ( ! is_numeric( $contact_id ) || ! is_numeric( $user_id ) ) {
			return false;
		}

		// Construct params.
		$params = [
			'version' => 3,
			'uf_id' => $user_id,
			'uf_name' => $username,
			'contact_id' => $contact_id,
		];

		// Maybe add Domain ID.
		if ( ! empty( $domain_id ) ) {
			$params['domain_id'] = $domain_id;
		}

		// Create record via API.
		$result = civicrm_api( 'UFMatch', 'create', $params );

		// Log and bail on failure.
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

		// --<
		return $result;

	}

	// -------------------------------------------------------------------------

	/**
	 * Gets the CiviCRM Contact data for a given ID.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class and renamed.
	 *
	 * @param int $contact_id The numeric ID of the CiviCRM Contact.
	 * @return array|bool $contact The array CiviCRM Contact data, or false otherwise.
	 */
	public function get_by_id( $contact_id ) {

		// Init return.
		$contact = false;

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $contact;
		}

		// Get all Contact data.
		$params = [
			'version' => 3,
			'contact_id' => $contact_id,
		];

		// Use API.
		$result = civicrm_api( 'Contact', 'get', $params );

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
			return $contact;
		}

		// Bail if there are no values.
		if ( empty( $result['values'] ) ) {
			return $contact;
		}

		// Get Contact.
		$contact = array_shift( $result['values'] );

		// --<
		return $contact;

	}

	/**
	 * Updates a CiviCRM Contact for a given WordPress User.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class and renamed.
	 *
	 * @param object $user The WordPress User object.
	 */
	public function update_for_user( $user ) {

		// Try and initialise CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return false;
		}

		// Make sure CiviCRM file is included.
		require_once 'CRM/Core/BAO/UFMatch.php';

		// The synchronizeUFMatch method returns the Contact object.
		$contact = CRM_Core_BAO_UFMatch::synchronizeUFMatch(
			$user, // User object.
			$user->ID, // ID.
			$user->user_email, // Unique identifier.
			'WordPress', // CMS.
			null, // Unused.
			'Individual' // Contact type.
		);

	}

}
