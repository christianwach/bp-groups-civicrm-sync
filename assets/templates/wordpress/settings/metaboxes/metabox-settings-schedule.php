<?php
/**
 * Scheduled Events template.
 *
 * Handles markup for the Scheduled Events meta box.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- <?php echo $this->path_template . $this->path_metabox; ?>metabox-settings-schedule.php -->
<table class="form-table">
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $this->form_interval_id ); ?>"><?php esc_html_e( 'Schedule Interval', 'bp-groups-civicrm-sync' ); ?></label></th>
		<td>
			<select class="settings-select" name="<?php echo esc_attr( $this->form_interval_id ); ?>" id="<?php echo esc_attr( $this->form_interval_id ); ?>">
				<?php foreach ( $schedules as $key => $value ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $interval, $key ); ?>><?php echo esc_html( $value['display'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Choose how often to synchronize your Synced Groups.', 'bp-groups-civicrm-sync' ); ?></p>
		</td>
	</tr>
</table>

<table class="form-table sync-details">
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $this->form_direction_id ); ?>"><?php esc_html_e( 'Sync direction', 'bp-groups-civicrm-sync' ); ?></label></th>
		<td>
			<select class="settings-select" name="<?php echo esc_attr( $this->form_direction_id ); ?>" id="<?php echo esc_attr( $this->form_direction_id ); ?>">
				<option value="civicrm" <?php selected( $direction, 'civicrm' ); ?>><?php esc_html_e( 'CiviCRM Groups &rarr; BuddyPress Groups', 'bp-groups-civicrm-sync' ); ?></option>
				<option value="buddypress" <?php selected( $direction, 'buddypress' ); ?>><?php esc_html_e( 'BuddyPress Groups &rarr; CiviCRM Groups', 'bp-groups-civicrm-sync' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Choose whether your CiviCRM Groups or BuddyPress Groups are the "source of truth".', 'bp-groups-civicrm-sync' ); ?></p>
		</td>
	</tr>

	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $this->form_batch_id ); ?>"><?php esc_html_e( 'Batch count', 'bp-groups-civicrm-sync' ); ?></label></th>
		<td>
			<input type="number" name="<?php echo esc_attr( $this->form_batch_id ); ?>" id="<?php echo esc_attr( $this->form_batch_id ); ?>" value="<?php echo esc_attr( $batch_count ); ?>" />
			<p class="description"><?php esc_html_e( 'Set the number of items to process each time the schedule runs. Setting "0" will process all Groups in one go. Be aware that this could exceed your PHP timeout limit if you have lots of Groups and Contacts. It would be better to use one of the supplied WP-CLI commands in this situation.', 'bp-groups-civicrm-sync' ); ?></p>
		</td>
	</tr>
</table>
