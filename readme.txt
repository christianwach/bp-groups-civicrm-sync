=== BP Groups CiviCRM Sync ===
Contributors: needle, cuny-academic-commons
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=PZSKM8T5ZP3SC
Tags: civicrm, buddypress, user, groups, sync
Requires at least: 3.9
Tested up to: 4.1
Stable tag: 0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A port of the Drupal civicrm_og_sync module for WordPress that enables synchronisation between BuddyPress Groups and CiviCRM.



== Description ==

A port of the Drupal civicrm_og_sync module for WordPress that enables synchronisation between BuddyPress Groups and CiviCRM. It does not rely on any core CiviCRM files, since any required (or adapted) methods are included.

### Requirements

This plugin requires a minimum of *WordPress 3.9*, *BuddyPress 1.8* and *CiviCRM 4.6-alpha1*. Please refer to the installation page for how to use this plugin with versions of CiviCRM prior to 4.6-alpha1.

### Plugin Development

This plugin is in active development. For feature requests and bug reports (or if you're a plugin author and want to contribute) please visit the plugin's [GitHub repository](https://github.com/christianwach/bp-groups-civicrm-sync).



== Installation ==

1. Extract the plugin archive
1. Upload plugin files to your `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

For versions of *CiviCRM* prior to 4.6-alpha1, this plugin requires the corresponding branch of the [CiviCRM WordPress plugin](https://github.com/civicrm/civicrm-wordpress) plus the custom WordPress.php hook file from the [CiviCRM Hook Tester repo on GitHub](https://github.com/christianwach/civicrm-wp-hook-tester) so that it overrides the built-in *CiviCRM* file. Please refer to the each repo for further instructions.



== Changelog ==

= 0.1 =

Initial release
