=== BP Groups CiviCRM Sync ===
Contributors: needle
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=PZSKM8T5ZP3SC
Tags: civicrm, buddypress, user, profile, xprofile, sync
Requires at least: 3.5
Tested up to: 3.6
Stable tag: 0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A port of the Drupal civicrm_og_sync module for WordPress that enables synchronisation between BuddyPress Groups and CiviCRM. Does not rely on any core CiviCRM files, since any required (or adapted) methods are included.



== Description ==

A port of the Drupal civicrm_og_sync module for WordPress that enables synchronisation between BuddyPress Groups and CiviCRM. Does not rely on any core CiviCRM files, since any required (or adapted) methods are included.

This plugin has been developed using *WordPress 3.6*, *BuddyPress 1.8* and *CiviCRM 4.3.5*. It requires the master branch of the [CiviCRM WordPress plugin](https://github.com/civicrm/civicrm-wordpress) plus the custom WordPress.php hook file from the [CiviCRM Hook Tester repo on GitHub](https://github.com/christianwach/civicrm-wp-hook-tester) so that it overrides the built-in *CiviCRM* file. It also uses (hopefully temporarily) a version of *BP Group Hierarchy 1.3.9* which can be [found on GitHub](https://github.com/christianwach/BP-Group-Hierarchy).



== Installation ==

1. Extract the plugin archive 
1. Upload plugin files to your `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress


== Changelog ==

= 0.1 =

Initial release
