=== BP Groups CiviCRM Sync ===
Contributors: needle, cuny-academic-commons
Donate link: https://www.paypal.me/interactivist
Tags: civicrm, buddypress, user, groups, sync
Requires at least: 4.9
Tested up to: 5.5
Stable tag: 0.3.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

BP Groups CiviCRM Sync enables two-way synchronisation between BuddyPress groups and CiviCRM groups.



== Description ==

This plugin provides two-way synchronisation between *CiviCRM Groups* and *BuddyPress Groups* in a similar way to the Drupal *Organic Groups CiviCRM* module. For each *BuddyPress* group, the plugin will automatically create two *CiviCRM* groups:

* A "normal" (mailing list) group containing a contact record for each corresponding *BuddyPress* group member. This group is assigned the same name as the linked *BuddyPress* group.
* An "ACL" group containing the contact record of the administrators of the corresponding *BuddyPress* group. This gives *BuddyPress* group admins the ability to view and edit members of their group in *CiviCRM*.

When a new user is added to (or joins) a *BuddyPress* group, they are automatically added to the corresponding *CiviCRM* group. Likewise, when a contact is added to the "normal" *CiviCRM* group, they will be added as a member to the corresponding *BuddyPress* group. If a contact is added to the *CiviCRM* "ACL" group, they will be added to the *BuddyPress* group as an administrator.

### Requirements

This plugin requires a minimum of *WordPress 4.9*, *BuddyPress 1.8* and *CiviCRM 4.7*. Having the latest version of each plugin active is, of course, highly recommended.

### Plugin Development

This plugin is in active development. For feature requests and bug reports (or if you're a plugin author and want to contribute) please visit the plugin's [GitHub repository](https://github.com/christianwach/bp-groups-civicrm-sync).



== Installation ==

1. Extract the plugin archive
1. Upload plugin files to your `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. In Multisite, it is recommended that you network-activate the plugin



== Changelog ==

= 0.3.7 =

* Adds filter to exclude BuddyPress groups from sync
* Fixes ACL group membership sync

= 0.3.6 =

* Improves plugin loading procedure
* Better code documentation

= 0.3.5 =

* Fixes sync procedure hanging when errors are encountered

= 0.3.4 =

* Drops support for CiviCRM 4.5
* Fixes membership of ACL Group for BuddyPress group admins

= 0.3.3 =

* Fixes CiviCRM "Mailing List" group type on BuddyPress group creation

= 0.3.2 =

* Make usernames URL-friendly

= 0.3.1 =

* Fixes empty WordPress user emails when Contacts are added to groups via "New Individual" form
* Updates hook references for CiviCRM 4.7.x instances

= 0.3 =

* AJAX-driven BuddyPress to CiviCRM sync
* Fixed sync recursion errors
* Fixed sync when using Groups admin page

= 0.2.1 =

Set "Use Parent Group" to off by default

= 0.2 =

First public release

= 0.1 =

Initial release
