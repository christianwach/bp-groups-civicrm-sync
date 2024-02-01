<?php
/**
 * "Manual Sync" template.
 *
 * Handles markup for a "Manual Sync" meta box.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- <?php echo $this->path_template . $this->path_metabox; ?>metabox-manual-sync.php -->
<div class="bpgcs_wrapper">

	<p><?php echo esc_html( $description ); ?></p>

	<p>
		<?php submit_button( $stop_value, 'secondary' . $stop_visibility, $stop_id, false ); ?>
		<?php submit_button( $submit_value, 'primary', $submit_id, false, $submit_attributes ); ?>
	</p>

	<div class="progress-bar progress-bar-hidden">
		<div id="progress-bar-<?php echo esc_attr( $submit_id ); ?>"><div class="progress-label"></div></div>
	</div>

</div>
