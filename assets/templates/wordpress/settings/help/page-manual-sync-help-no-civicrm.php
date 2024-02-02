<?php
/**
 * Manual Sync Help template.
 *
 * Handles markup for Manual Sync Help when there are no synced CiviCRM Groups.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- <?php echo esc_html( $this->path_template . $this->path_help ); ?>page-manual-sync-help-no-civicrm.php -->
<p><?php esc_html_e( 'Use "Sync Now" to create CiviCRM Member Groups and ACL Groups for your BuddyPress Groups.', 'bp-groups-civicrm-sync' ); ?></p>
