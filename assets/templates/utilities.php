<?php
/**
 * Utilities page template.
 *
 * Contains markup for this plugin's Utilities page.
 *
 * @package BP_Groups_CiviCRM_Sync
 * @since 0.1
 */

?><!-- assets/templates/utilities.php -->
<div id="icon-options-general" class="icon32"></div>

<div class="wrap">

	<h2 class="nav-tab-wrapper">
		<a href="<?php echo $urls['settings']; ?>" class="nav-tab"><?php esc_html_e( 'Settings', 'bp-groups-civicrm-sync' ); ?></a>
		<a href="<?php echo $urls['utilities']; ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Utilities', 'bp-groups-civicrm-sync' ); ?></a>
	</h2>

	<?php if ( ! empty( $messages ) ) : ?>
		<?php echo $messages; ?>
	<?php endif; ?>

	<form method="post" id="bp_groups_civicrm_sync_utilities_form" action="<?php echo $this->admin_form_url_get(); ?>">

		<?php wp_nonce_field( 'bp_groups_civicrm_sync_utilities_action', 'bp_groups_civicrm_sync_nonce' ); ?>

		<h3><?php esc_html_e( 'BuddyPress to CiviCRM Sync', 'bp-groups-civicrm-sync' ); ?></h3>

		<table class="form-table">

			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Sync BP Groups to CiviCRM', 'bp-groups-civicrm-sync' ); ?></th>
				<td><input type="submit" id="bp_groups_civicrm_sync_bp_check" name="bp_groups_civicrm_sync_bp_check" value="<?php if ( 'fgffgs' == get_option( '_bgcs_groups_page', 'fgffgs' ) ) { esc_attr_e( 'Sync Now', 'bp-groups-civicrm-sync' ); } else { esc_attr_e( 'Continue Sync', 'bp-groups-civicrm-sync' ); } ?>" class="button-primary" /><?php if ( 'fgffgs' == get_option( '_bgcs_groups_page', 'fgffgs' ) ) {} else { ?> <input type="submit" id="bp_groups_civicrm_sync_bp_stop" name="bp_groups_civicrm_sync_bp_stop" value="<?php esc_attr_e( 'Stop Sync', 'bp-groups-civicrm-sync' ); ?>" class="button-secondary" /><?php } ?></td>
			</tr>

		</table>

		<div id="progress-bar"><div class="progress-label"></div></div>

		<hr>

		<?php /*
		<h3><?php esc_html_e( 'Check BuddyPress and CiviCRM Sync', 'bp-groups-civicrm-sync' ); ?></h3>

		<p><?php esc_html_e( 'Check this to find out if there are BuddyPress Groups with no CiviCRM Group and vice versa.', 'bp-groups-civicrm-sync' ); ?></p>

		<table class="form-table">

			<tr valign="top">
				<th scope="row"><label for="bp_groups_civicrm_sync_bp_check_sync"><?php esc_html_e( 'Check BP Groups and CiviCRM Groups', 'bp-groups-civicrm-sync' ); ?></label></th>
				<td><input type="submit" id="bp_groups_civicrm_sync_bp_check_sync" name="bp_groups_civicrm_sync_bp_check_sync" value="<?php esc_attr_e( 'Check Now', 'bp-groups-civicrm-sync' ); ?>" class="button-primary" /></td>
			</tr>

		</table>

		<hr>
		*/ ?>

		<?php if ( $og_to_bp_do_sync ) : ?>

			<h3><?php esc_html_e( 'Convert OG groups in CiviCRM to BP groups', 'bp-groups-civicrm-sync' ); ?></h3>

			<p><?php esc_html_e( 'WARNING: this will probably only work when there are a small number of groups. If you have lots of groups, it would be worth writing some kind of chunked update routine. I will upgrade this plugin to do so at some point.', 'bp-groups-civicrm-sync' ); ?></p>

			<table class="form-table">

				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Convert OG groups to BP groups', 'bp-groups-civicrm-sync' ); ?></th>
					<td><input type="submit" id="bp_groups_civicrm_sync_convert" name="bp_groups_civicrm_sync_convert" value="<?php esc_attr_e( 'Convert Now', 'bp-groups-civicrm-sync' ); ?>" class="button-primary" /></td>
				</tr>

			</table>

		<?php else : ?>

			<h3><?php esc_html_e( 'CiviCRM to BuddyPress Sync', 'bp-groups-civicrm-sync' ); ?></h3>

			<?php if ( $checking_og ) : ?>

				<p><?php esc_html_e( 'No OG Groups found', 'bp-groups-civicrm-sync' ); ?></p>

			<?php else : ?>

				<table class="form-table">

					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Check for OG groups', 'bp-groups-civicrm-sync' ); ?></th>
						<td><input id="bp_groups_civicrm_sync_og_check" name="bp_groups_civicrm_sync_og_check" value="<?php esc_attr_e( 'Check Now', 'bp-groups-civicrm-sync' ); ?>" type="submit" class="button-primary" /></td>
					</tr>

				</table>

			<?php endif; ?>

		<?php endif; ?>

	</form>

</div><!-- /.wrap -->
