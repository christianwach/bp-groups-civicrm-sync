<?php
/**
 * General Settings template.
 *
 * Handles markup for the General Settings meta box.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- <?php echo esc_html( $this->path_template . $this->path_metabox ); ?>metabox-settings-general.php -->
<table class="form-table">
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $this->form_parent_group_id ); ?>"><?php esc_html_e( 'Parent Group', 'bp-groups-civicrm-sync' ); ?></label></th>
		<td>
			<input type="checkbox" name="<?php echo esc_attr( $this->form_parent_group_id ); ?>" id="<?php echo esc_attr( $this->form_parent_group_id ); ?>" value="1"<?php checked( 1, $parent_group ); ?> />
			<p class="description">
				<?php

				echo sprintf(
					/* translators: 1: The opening anchor tag, 2: The closing anchor tag */
					esc_html__( 'Depending on your use case, select whether you want your CiviCRM Groups to be assigned to a "BuddyPress Groups" Parent Group in CiviCRM. If you do, then CiviCRM Groups will be nested under - and inherit permissions from - the "BuddyPress Groups" Parent Group. Please refer to %1$sthe documentation%2$s to decide if this is useful to you or not.', 'bp-groups-civicrm-sync' ),
					'<a href="https://docs.civicrm.org/user/en/latest/organising-your-data/groups-and-tags/">',
					'</a>'
				);

				?>
			</p>
			<p class="description">
				<?php

				echo sprintf(
					/* translators: 1: The opening strong tag, 2: The closing strong tag */
					esc_html__( '%1$sPlease Note:%2$s it is strongly recommended to decide how you want this set before you sync Groups. You can change this setting later, but it will require some heavy processing if you have a large number of Groups.', 'bp-groups-civicrm-sync' ),
					'<strong>',
					'</strong>'
				);

				?>
			</p>

		</td>
	</tr>
</table>
