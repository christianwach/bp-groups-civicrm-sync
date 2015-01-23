<!-- assets/templates/settings.php -->
<div id="icon-options-general" class="icon32"></div>

<div class="wrap">

	<h2 class="nav-tab-wrapper">
		<a href="<?php echo $urls['settings']; ?>" class="nav-tab nav-tab-active"><?php _e( 'Settings', 'bp-groups-civicrm-sync' ); ?></a>
		<a href="<?php echo $urls['utilities']; ?>" class="nav-tab"><?php _e( 'Utilities', 'bp-groups-civicrm-sync' ); ?></a>
	</h2>

	<?php

	// if we've got any messages, show them...
	if ( isset( $messages ) AND ! empty( $messages ) ) echo $messages;

	?>

	<form method="post" id="bp_groups_civicrm_sync_settings_form" action="<?php echo $this->admin_form_url_get(); ?>">

		<?php wp_nonce_field( 'bp_groups_civicrm_sync_settings_action', 'bp_groups_civicrm_sync_nonce' ); ?>

		<h3><?php _e( 'Parent Group', 'bp-groups-civicrm-sync' ); ?></h3>

		<p><?php _e( 'Depending on your use case, select whether you want your CiviCRM groups to be assigned to a "BuddyPress Groups" parent group in CiviCRM. If you do, then CiviCRM groups will be nested under - and inherit permissions from - the "BuddyPress Groups" parent group. Please refer to <a href="http://book.civicrm.org/user/current/organising-your-data/groups-and-tags/">the documentation</a> to decide if this is useful to you or not.', 'bp-groups-civicrm-sync' ); ?></p>

		<table class="form-table">

			<tr>
				<th scope="row"><label class="bp_groups_civicrm_sync_settings_label" for="bp_groups_civicrm_sync_settings_parent_group"><?php _e( 'Use Parent Group', 'bp-groups-civicrm-sync' ); ?></label></th>
				<td>
					<?php

					// checked by default
					$checked = ' checked="checked"';
					if ( isset( $parent_group ) AND $parent_group === 0 ) {
						$checked = '';
					}

					?><input type="checkbox" class="settings-checkbox" name="bp_groups_civicrm_sync_settings_parent_group" id="bp_groups_civicrm_sync_settings_parent_group" value="1"<?php echo $checked; ?> />
					<label class="bp_groups_civicrm_sync_settings_label" for="bp_groups_civicrm_sync_settings_parent_group"><?php _e( 'Assign CiviCRM groups to a "BuddyPress Groups" parent group.', 'bp-groups-civicrm-sync' ); ?></label>
				</td>
			</tr>

		</table>

		<p class="submit">
			<input class="button-primary" type="submit" id="bp_groups_civicrm_sync_settings_submit" name="bp_groups_civicrm_sync_settings_submit" value="<?php _e( 'Save Changes', 'bp-groups-civicrm-sync' ); ?>" />
		</p>

	</form>

</div><!-- /.wrap -->



