<?php /*
================================================================================
BP Groups CiviCRM Sync Uninstaller
================================================================================
AUTHOR: Christian Wach <needle@haystack.co.uk>
--------------------------------------------------------------------------------
NOTES
=====


--------------------------------------------------------------------------------
*/



// Kick out if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}



// Delete options.
delete_option( 'bp_groups_civicrm_sync_settings' );
delete_option( 'bp_groups_civicrm_sync_version' );


