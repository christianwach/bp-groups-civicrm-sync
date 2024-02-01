<?php
/**
 * WP-CLI integration for this plugin.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Bail if WP-CLI is not present.
if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Set up WP-CLI commands for this plugin.
 *
 * @since 0.5.0
 */
function bpgcs_cli_bootstrap() {

	// Include files.
	require_once __DIR__ . '/commands/command-base.php';
	require_once __DIR__ . '/commands/command-bpgcs.php';
	require_once __DIR__ . '/commands/command-job.php';

	// -----------------------------------------------------------------------------------
	// Add commands.
	// -----------------------------------------------------------------------------------

	// Add top-level command.
	WP_CLI::add_command( 'bpgcs', 'BP_Groups_CiviCRM_Sync_CLI_Command' );

	// Add Job command.
	WP_CLI::add_command( 'bpgcs job', 'BP_Groups_CiviCRM_Sync_CLI_Command_Job', [ 'before_invoke' => 'BP_Groups_CiviCRM_Sync_CLI_Command_Job::check_dependencies' ] );

}

// Set up commands.
WP_CLI::add_hook( 'before_wp_load', 'bpgcs_cli_bootstrap' );
