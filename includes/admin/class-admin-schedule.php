<?php
/**
 * Schedule class.
 *
 * Handles WordPress scheduling functionality.
 *
 * @package BP_Groups_CiviCRM_Sync
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Schedule class.
 *
 * Class for encapsulating WordPress scheduling functionality.
 *
 * This is a copy of the CiviCRM Admin Utilities class.
 *
 * @since 0.5.0
 */
class BP_Groups_CiviCRM_Sync_Schedule {

	/**
	 * Plugin object.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var object
	 */
	public $plugin;

	/**
	 * Admin object.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var BP_Groups_CiviCRM_Sync_Admin
	 */
	public $admin;

	/**
	 * Hook name.
	 *
	 * @since 0.5.0
	 * @access public
	 * @var string
	 */
	public $hook = 'bpgcs_refresh';

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param object $admin The admin object.
	 */
	public function __construct( $admin ) {

		// Store references to objects.
		$this->plugin = $admin->plugin;
		$this->admin  = $admin;

		// Add settings filters.
		add_filter( 'bp_groups_civicrm_sync_default_settings', [ $this, 'settings_default_add' ] );
		add_filter( 'bpgcs/admin/upgrade_settings', [ $this, 'settings_upgrade' ] );

		// Initialise when admin is loaded.
		add_action( 'bpgcs/admin/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Adds the default settings for schedule events.
	 *
	 * @since 0.5.0
	 *
	 * @param array $settings The default settings.
	 * @return array $settings The modified default settings.
	 */
	public function settings_default_add( $settings ) {

		// WordPress schedule.
		$settings['interval']    = 'off';
		$settings['direction']   = 'civicrm';
		$settings['batch_count'] = 25;

		// --<
		return $settings;

	}

	/**
	 * Upgrades the settings for schedule events.
	 *
	 * @since 0.5.0
	 *
	 * @param bool $save The default save flag.
	 * @return bool $save The modfied save flag.
	 */
	public function settings_upgrade( $save ) {

		// Add schedule settings from defaults.
		if ( ! $this->admin->setting_exists( 'interval' ) ) {
			$settings = $this->admin->settings_get_defaults();
			$this->admin->setting_set( 'interval', $settings['interval'] );
			$save = true;
		}
		if ( ! $this->admin->setting_exists( 'direction' ) ) {
			$settings = $this->admin->settings_get_defaults();
			$this->admin->setting_set( 'direction', $settings['direction'] );
			$save = true;
		}
		if ( ! $this->admin->setting_exists( 'batch_count' ) ) {
			$settings = $this->admin->settings_get_defaults();
			$this->admin->setting_set( 'batch_count', $settings['batch_count'] );
			$save = true;
		}

		// --<
		return $save;

	}

	/**
	 * Initialises this object.
	 *
	 * @since 0.5.0
	 */
	public function initialise() {

		// Add some custom schedules.
		// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_filter( 'cron_schedules', [ $this, 'intervals_add' ] );

		// Get our interval setting.
		$interval = $this->admin->setting_get( 'interval' );

		// Add scheduled event if set.
		if ( 'off' !== $interval ) {

			// Add scheduled event.
			$this->schedule( $interval );

			// Add schedule callback action.
			add_action( $this->hook, [ $this, 'schedule_callback' ] );

		}

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Sets up our scheduled event.
	 *
	 * @since 0.5.0
	 *
	 * @param string $interval One of the WordPress-defined intervals.
	 */
	public function schedule( $interval ) {

		// If not already present.
		if ( ! wp_next_scheduled( $this->hook ) && ! wp_installing() ) {

			// Add scheduled event.
			wp_schedule_event(
				time(), // Time when event fires.
				$interval, // Event interval.
				$this->hook // Hook to fire.
			);

		}

	}

	/**
	 * Clears our scheduled event.
	 *
	 * @since 0.5.0
	 */
	public function unschedule() {

		// Get next scheduled event.
		$timestamp = wp_next_scheduled( $this->hook );

		// Unschedule it if we get one.
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, $this->hook );
		}

		/*
		 * It's not obvious whether wp_unschedule_event() clears everything,
		 * so let's remove existing scheduled hook as well.
		 */
		wp_clear_scheduled_hook( $this->hook );

	}

	/**
	 * Called when a scheduled event is triggered.
	 *
	 * @since 0.5.0
	 */
	public function schedule_callback() {

		// Get the settings.
		$direction   = $this->admin->setting_get( 'direction' );
		$batch_count = (int) $this->admin->setting_get( 'batch_count' );

		switch ( $direction ) {

			case 'civicrm':
				$identifier = 'bpgcs_cron_civicrm_to_bp';
				if ( 0 === $batch_count ) {
					$this->plugin->civicrm->batch_sync_to_bp_all();
				} else {
					$this->plugin->civicrm->batch_sync_to_bp( $identifier );
				}
				break;

			case 'buddypress':
				$identifier = 'bpgcs_cron_bp_to_civicrm';
				if ( 0 === $batch_count ) {
					$this->plugin->bp->batch_sync_to_civicrm_all();
				} else {
					$this->plugin->bp->batch_sync_to_civicrm( $identifier );
				}
				break;

		}

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets the schedule intervals.
	 *
	 * @since 0.5.0
	 *
	 * @return array $intervals Array of schedule interval arrays, keyed by interval slug.
	 */
	public function intervals_get() {

		// Just a wrapper.
		return wp_get_schedules();

	}

	/**
	 * Adds some schedule intervals.
	 *
	 * @since 0.5.0
	 *
	 * @param array $intervals Existing array of schedule interval arrays, keyed by interval slug.
	 * @return array $intervals Modified array of schedule interval arrays.
	 */
	public function intervals_add( $intervals ) {

		// Add "Every second" for testing.
		if ( ! isset( $intervals['second'] ) ) {
			$intervals['10seconds'] = [
				'interval' => 1,
				'display'  => __( 'Every second', 'bp-groups-civicrm-sync' ),
			];
		}

		// Add "Every 5 minutes".
		if ( ! isset( $intervals['5minutes'] ) ) {
			$intervals['5minutes'] = [
				'interval' => 300,
				'display'  => __( 'Every 5 minutes', 'bp-groups-civicrm-sync' ),
			];
		}

		// Add "Every 10 minutes".
		if ( ! isset( $intervals['10minutes'] ) ) {
			$intervals['10minutes'] = [
				'interval' => 600,
				'display'  => __( 'Every 10 minutes', 'bp-groups-civicrm-sync' ),
			];
		}

		// Add "Every 20 minutes".
		if ( ! isset( $intervals['20minutes'] ) ) {
			$intervals['10minutes'] = [
				'interval' => 1200,
				'display'  => __( 'Every 20 minutes', 'bp-groups-civicrm-sync' ),
			];
		}

		// Add "Every half an hour".
		if ( ! isset( $intervals['halfhourly'] ) ) {
			$intervals['halfhourly'] = [
				'interval' => 1800,
				'display'  => __( 'Once Half-hourly', 'bp-groups-civicrm-sync' ),
			];
		}

		// --<
		return $intervals;

	}

}
