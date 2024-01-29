<?php
/**
 * Settings page template.
 *
 * Contains markup for this plugin's Settings page.
 *
 * @package BP_Groups_CiviCRM_Sync
 * @since 0.1
 */

?>
<!-- assets/templates/settings.php -->
<div id="icon-options-general" class="icon32"></div>

<div class="wrap">

	<h2 class="nav-tab-wrapper">
		<?php /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
		<a href="<?php echo $urls['settings']; ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Settings', 'bp-groups-civicrm-sync' ); ?></a>
		<?php /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
		<a href="<?php echo $urls['manual_sync']; ?>" class="nav-tab"><?php esc_html_e( 'Manual Sync', 'bp-groups-civicrm-sync' ); ?></a>
	</h2>

	<form method="post" id="bp_groups_civicrm_sync_settings_form" action="<?php echo $this->admin_form_url_get(); ?>">

		<?php wp_nonce_field( 'bp_groups_civicrm_sync_settings_action', 'bp_groups_civicrm_sync_nonce' ); ?>

		<p>
			<?php

			echo sprintf(
				/* translators: 1: The opening strong tag, 2: The closing strong tag */
				esc_html__( '%1$sPlease Note:%2$s it is strongly recommended to choose the following settings before you sync Groups. You can change these settings later, but it will require some heavy processing if you have a large number of Groups.', 'bp-groups-civicrm-sync' ),
				'<strong>',
				'</strong>'
			);

			?>
		</p>

		<hr>

		<h3><?php esc_html_e( 'Parent Group', 'bp-groups-civicrm-sync' ); ?></h3>

		<p>
			<?php

			echo sprintf(
				/* translators: 1: The opening anchor tag, 2: The closing anchor tag */
				esc_html__( 'Depending on your use case, select whether you want your CiviCRM Groups to be assigned to a "BuddyPress Groups" Parent Group in CiviCRM. If you do, then CiviCRM Groups will be nested under - and inherit permissions from - the "BuddyPress Groups" Parent Group. Please refer to %1$sthe documentation%2$s to decide if this is useful to you or not.', 'bp-groups-civicrm-sync' ),
				'<a href="https://docs.civicrm.org/user/en/latest/organising-your-data/groups-and-tags/">',
				'</a>'
			);

			?>
		</p>

		<table class="form-table">

			<tr>
				<th scope="row"><label class="bp_groups_civicrm_sync_settings_label" for="bp_groups_civicrm_sync_settings_parent_group"><?php esc_html_e( 'Use Parent Group', 'bp-groups-civicrm-sync' ); ?></label></th>
				<td>
					<input type="checkbox" class="settings-checkbox" name="bp_groups_civicrm_sync_settings_parent_group" id="bp_groups_civicrm_sync_settings_parent_group" value="1"<?php checked( 1, $parent_group ); ?> />
					<label class="bp_groups_civicrm_sync_settings_label" for="bp_groups_civicrm_sync_settings_parent_group"><?php esc_html_e( 'Assign CiviCRM Groups to a "BuddyPress Groups" Parent Group.', 'bp-groups-civicrm-sync' ); ?></label>
				</td>
			</tr>

		</table>

		<?php

		/**
		 * Allow extra content to be added.
		 *
		 * @since 0.4
		 */
		do_action( 'bpgcs/submit/before' );

		?>

		<hr>

		<p class="submit">
			<input class="button-primary" type="submit" id="bp_groups_civicrm_sync_settings_submit" name="bp_groups_civicrm_sync_settings_submit" value="<?php esc_attr_e( 'Save Changes', 'bp-groups-civicrm-sync' ); ?>" />
		</p>

	</form>

</div><!-- /.wrap -->
