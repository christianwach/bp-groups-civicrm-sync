<?php
/**
 * Manual Sync Help template.
 *
 * Handles markup for Manual Sync Help.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- <?php echo esc_html( $this->path_template . $this->path_help ); ?>page-manual-sync-help.php -->
<p><?php esc_html_e( 'Choose your sync direction depending on whether your CiviCRM Groups or your BuddyPress Groups are the "source of truth".', 'bp-groups-civicrm-sync' ); ?></p>

<p><?php esc_html_e( 'The procedure in both directions is as follows:', 'bp-groups-civicrm-sync' ); ?></p>

<ol>
	<li><?php esc_html_e( 'Group members in the source Group will be added to the target Group if they are missing.', 'bp-groups-civicrm-sync' ); ?></li>
	<li><?php esc_html_e( 'Group members in the target Group will be deleted if they are no longer members of the source Group.', 'bp-groups-civicrm-sync' ); ?></li>
</ol>
