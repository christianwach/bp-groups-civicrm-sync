<?php
/**
 * Command class.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Bail if WP-CLI is not present.
if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Manage BP Groups CiviCRM Sync through the command-line.
 *
 * ## EXAMPLES
 *
 *     $ wp bpgcs job sync-to-bp
 *     Success: Job complete.
 *
 *     $ wp bpgcs job sync-to-civicrm
 *     Success: Job complete.
 *
 * @since 0.5.0
 *
 * @package BP_Groups_CiviCRM_Sync
 */
class BP_Groups_CiviCRM_Sync_CLI_Command extends BP_Groups_CiviCRM_Sync_CLI_Command_Base {

	/**
	 * Adds our description and sub-commands.
	 *
	 * @since 0.5.0
	 *
	 * @param object $command The command.
	 * @return array $info The array of information about the command.
	 */
	private function command_to_array( $command ) {

		$info = [
			'name'        => $command->get_name(),
			'description' => $command->get_shortdesc(),
			'longdesc'    => $command->get_longdesc(),
		];

		foreach ( $command->get_subcommands() as $subcommand ) {
			$info['subcommands'][] = $this->command_to_array( $subcommand );
		}

		if ( empty( $info['subcommands'] ) ) {
			$info['synopsis'] = (string) $command->get_synopsis();
		}

		return $info;

	}

}
