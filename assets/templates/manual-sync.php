<?php
/**
 * Manual Sync page template.
 *
 * Contains markup for this plugin's Manual Sync page.
 *
 * @package BP_Groups_CiviCRM_Sync
 * @since 0.1
 */

?><!-- assets/templates/manual-sync.php -->
<div id="icon-options-general" class="icon32"></div>

<div class="wrap">

	<h2 class="nav-tab-wrapper">
		<a href="<?php echo $urls['settings']; ?>" class="nav-tab"><?php esc_html_e( 'Settings', 'bp-groups-civicrm-sync' ); ?></a>
		<a href="<?php echo $urls['manual_sync']; ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Manual Sync', 'bp-groups-civicrm-sync' ); ?></a>
	</h2>

	<?php if ( ! empty( $messages ) ) : ?>
		<?php echo $messages; ?>
	<?php endif; ?>

	<form method="post" id="bp_groups_civicrm_sync_manual_sync_form" action="<?php echo $this->admin_form_url_get(); ?>">

		<?php wp_nonce_field( 'bp_groups_civicrm_sync_manual_sync_action', 'bp_groups_civicrm_sync_nonce' ); ?>

		<h3><?php esc_html_e( 'BuddyPress to CiviCRM Sync', 'bp-groups-civicrm-sync' ); ?></h3>

		<p><?php esc_html_e( 'Synchronize BuddyPress Groups and their Memberships to their corresponding CiviCRM Member Groups and ACL Groups.', 'bp-groups-civicrm-sync' ); ?></p>

		<table class="form-table">

			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Sync BuddyPress Groups to CiviCRM', 'bp-groups-civicrm-sync' ); ?></th>
				<td><input type="submit" id="bp_groups_civicrm_sync_bp_check" name="bp_groups_civicrm_sync_bp_check" value="<?php if ( 'fgffgs' == get_option( '_bgcs_groups_page', 'fgffgs' ) ) { esc_attr_e( 'Sync Now', 'bp-groups-civicrm-sync' ); } else { esc_attr_e( 'Continue Sync', 'bp-groups-civicrm-sync' ); } ?>" class="button-primary" data-security="<?php echo esc_attr( wp_create_nonce( 'bp_groups_civicrm_sync_bp_nonce' ) ) ?>" /><?php if ( 'fgffgs' == get_option( '_bgcs_groups_page', 'fgffgs' ) ) {} else { ?> <input type="submit" id="bp_groups_civicrm_sync_bp_stop" name="bp_groups_civicrm_sync_bp_stop" value="<?php esc_attr_e( 'Stop Sync', 'bp-groups-civicrm-sync' ); ?>" class="button-secondary" /><?php } ?></td>
			</tr>

		</table>

		<div id="progress-bar"><div class="progress-label"></div></div>

		<hr>

		<?php if ( $has_og_groups ) : ?>

			<h3><?php esc_html_e( 'Convert Drupal OG Groups in CiviCRM to BuddyPress Groups', 'bp-groups-civicrm-sync' ); ?></h3>

			<p><?php esc_html_e( 'WARNING: this will probably only work when there are a small number of groups. If you have lots of groups, it would be worth writing some kind of chunked update routine. I will upgrade this plugin to do so at some point.', 'bp-groups-civicrm-sync' ); ?></p>

			<table class="form-table">

				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Convert OG Groups to BuddyPress Groups', 'bp-groups-civicrm-sync' ); ?></th>
					<td><input type="submit" id="bp_groups_civicrm_sync_convert" name="bp_groups_civicrm_sync_convert" value="<?php esc_attr_e( 'Convert Now', 'bp-groups-civicrm-sync' ); ?>" class="button-primary" /></td>
				</tr>

			</table>

		<?php endif; ?>

	</form>

</div><!-- /.wrap -->
