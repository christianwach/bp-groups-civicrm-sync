<?php
/**
 * Uninstaller.
 *
 * Handles uninstall functionality.
 *
 * @package BP_Groups_CiviCRM_Sync
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



// Kick out if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}



// Delete options.
delete_option( 'bp_groups_civicrm_sync_settings' );
delete_option( 'bp_groups_civicrm_sync_version' );
