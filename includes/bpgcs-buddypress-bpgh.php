<?php
/**
 * "BP Group Hierarchy" plugin compatibility Class.
 *
 * Handles compatibility with the "BP Group Hierarchy" plugin.
 *
 * @package BP_Groups_CiviCRM_Sync
 * @since 0.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * BP Groups CiviCRM Sync "BP Group Hierarchy" compatibility Class.
 *
 * A class that encapsulates compatibility with the "BP Group Hierarchy" plugin.
 *
 * @since 0.4
 */
class BP_Groups_CiviCRM_Sync_BuddyPress_BPGH {

	/**
	 * Plugin object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * BuddyPress object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $bp The BuddyPress object.
	 */
	public $bp;

	/**
	 * CiviCRM object.
	 *
	 * @since 0.4
	 * @access public
	 * @var object $civicrm The CiviCRM object.
	 */
	public $civicrm;

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

		// Bail if the BP Group Hierarchy plugin is not present.
		if ( ! defined( 'BP_GROUP_HIERARCHY_IS_INSTALLED' ) ) {
			return;
		}

		// Store reference to objects.
		$this->plugin  = $parent->plugin;
		$this->bp      = $parent;
		$this->civicrm = $parent->civicrm;
		$this->admin   = $parent->admin;

		// Boot when CiviCRM object is loaded.
		add_action( 'bpgcs/buddypress/loaded', [ $this, 'initialise' ] );

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
		do_action( 'bpgcs/buddypress/bpgh/loaded' );

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.4
	 */
	public function register_hooks() {

		// The following action allows us to know that the hierarchy has been altered.
		add_action( 'bp_group_hierarchy_before_save', [ $this, 'hierarchy_before_change' ] );
		add_action( 'bp_group_hierarchy_after_save', [ $this, 'hierarchy_after_change' ] );

		// Add settings callbacks.
		add_action( 'bpgcs/admin/settings/update/before', [ $this, 'settings_update' ] );
		add_action( 'bpgcs/admin/settings/update/after', [ $this, 'settings_updated' ] );
		add_action( 'bpgcs/submit/before', [ $this, 'settings_markup' ] );

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
	 * Registers when BuddyPress Group Hierarchy plugin is saving a Group.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class.
	 *
	 * @param object $group The BuddyPress Group object.
	 */
	public function hierarchy_before_change( $group ) {

		// Get parent ID.
		$parent_id = isset( $group->vars['parent_id'] ) ? (int) $group->vars['parent_id'] : 0;

		// Pass to CiviCRM object.
		$this->civicrm->group_nesting->nesting_update( $group->id, $parent_id );

	}

	/**
	 * Registers when BuddyPress Group Hierarchy plugin has saved a Group.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class.
	 *
	 * @param object $group The BuddyPress Group object.
	 */
	public function hierarchy_after_change( $group ) {
		// Nothing for now.
	}

	// -------------------------------------------------------------------------

	/**
	 * Create all CiviCRM Group Nestings.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class.
	 */
	public function hierarchy_build() {

		// Get tree.
		$tree = BP_Groups_Hierarchy::get_tree();
		if ( empty( $tree ) ) {
			return;
		}

		// Update the nesting for all Groups.
		foreach ( $tree as $group ) {
			$this->civicrm->group_nesting->nesting_update( $group->id, $group->parent_id );
		}

	}

	/**
	 * Delete all CiviCRM Group Nestings.
	 *
	 * @since 0.1
	 * @since 0.4 Moved to this class.
	 */
	public function hierarchy_collapse() {

		// Get tree.
		$tree = BP_Groups_Hierarchy::get_tree();
		if ( empty( $tree ) ) {
			return;
		}

		// Collapse nesting by assigning all to Meta Group.
		foreach ( $tree as $group ) {
			$this->civicrm->group_nesting->nesting_update( $group->id );
		}

	}

	// -------------------------------------------------------------------------

	/**
	 * Update the compatibility settings.
	 *
	 * @since 0.4
	 */
	public function settings_update() {

		// Get existing option.
		$this->existing_hierarchy = $this->admin->setting_get( 'nesting' );

		// Did we ask to enable hierarchy?
		$hierarchy = 0;
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['bp_groups_civicrm_sync_settings_hierarchy'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$hierarchy = (int) trim( wp_unslash( $_POST['bp_groups_civicrm_sync_settings_hierarchy'] ) );
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}

		// Sanitise and set option.
		$this->admin->setting_set( 'nesting', ( $hierarchy ? 1 : 0 ) );

	}

	/**
	 * Update the hierarchy when settings have changed.
	 *
	 * @since 0.4
	 */
	public function settings_updated() {

		// Get our setting.
		$hierarchy = $this->admin->setting_get( 'nesting' );

		// Bail if the hierarchy setting has not changed.
		if ( (int) $this->existing_hierarchy === (int) $hierarchy ) {
			return;
		}

		// Are we switching from "no hierarchy" to "use hierarchy"?
		if ( 0 === (int) $this->existing_hierarchy ) {

			// Build CiviCRM Group hierarchy.
			$this->civicrm->group_nesting->hierarchy_build();

		} else {

			// Collapse CiviCRM Group hierarchy.
			$this->civicrm->group_nesting->hierarchy_collapse();

		}

	}

	/**
	 * Renders our settings markup.
	 *
	 * @since 0.4
	 */
	public function settings_markup() {

		// Get our setting.
		$hierarchy = (int) $this->setting_get( 'nesting' );

		?>

		<hr>

		<h3><?php esc_html_e( 'BuddyPress Group Hierarchy', 'bp-groups-civicrm-sync' ); ?></h3>

		<p>
			<?php

			echo sprintf(
				/* translators: 1: The opening anchor tag, 2: The closing anchor tag. */
				esc_html__( 'Depending on your use case, select whether you want your CiviCRM Groups to be hierarchically organised in CiviCRM. If you do, then CiviCRM Groups will be nested under one another, mirroring the BuddyPress Group Hierarchy. Again, please refer to %1$sthe documentation%2$s to decide if this is useful to you or not.', 'bp-groups-civicrm-sync' ),
				'<a href="https://docs.civicrm.org/user/en/latest/organising-your-data/groups-and-tags/">',
				'</a>'
			);

			?>
		</p>

		<table class="form-table">

			<tr>
				<th scope="row"><label class="bp_groups_civicrm_sync_settings_label" for="bp_groups_civicrm_sync_settings_hierarchy"><?php esc_html_e( 'Use Hierarchy', 'bp-groups-civicrm-sync' ); ?></label></th>
				<td>
					<input type="checkbox" class="settings-checkbox" name="bp_groups_civicrm_sync_settings_hierarchy" id="bp_groups_civicrm_sync_settings_hierarchy" value="1"<?php checked( 0, $hierarchy ); ?> />
					<label class="bp_groups_civicrm_sync_settings_label" for="bp_groups_civicrm_sync_settings_hierarchy"><?php esc_html_e( 'Nest CiviCRM Groups hierarchically.', 'bp-groups-civicrm-sync' ); ?></label>
				</td>
			</tr>

		</table>

		<?php

	}

}
