# BP Groups CiviCRM Sync

**Contributors:** [needle](https://profiles.wordpress.org/needle/), [cuny-academic-commons](https://profiles.wordpress.org/cuny-academic-commons/)<br/>
**Donate link:** https://www.paypal.me/interactivist<br/>
**Tags:** civicrm, buddypress, user, groups, sync<br/>
**Requires at least:** 4.9<br/>
**Tested up to:** 5.9<br/>
**Stable tag:** 0.4<br/>
**License:** GPLv2 or later<br/>
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

**Please note:** this is the development repository for *BP Groups CiviCRM Sync*. The plugin is also available in [the WordPress Plugin Directory](http://wordpress.org/plugins/bp-groups-civicrm-sync/), which is the best place to get it from if you're not a developer.

The *BP Groups CiviCRM Sync* plugin provides two-way synchronisation between *CiviCRM* Groups and *BuddyPress* Groups. For each *BuddyPress* Group, the plugin will automatically create two *CiviCRM* Groups:

* A "Member Group" (of type "Mailing List") containing a Contact record for each corresponding *BuddyPress* Group Member. This Group is assigned the same name as the linked *BuddyPress* Group.
* An "ACL Group" (of type "Access Control") containing the Contact records of the Administrators of the corresponding *BuddyPress* Group. This gives *BuddyPress* Group Administrators the ability to view and edit their Group Members in *CiviCRM*.

When a new Member is added to (or joins) a *BuddyPress* Group, they are automatically added to the corresponding *CiviCRM* Group. Likewise, when a Contact is added to the  *CiviCRM* "Member Group", they will be added as a Member to the corresponding *BuddyPress* Group. If a Contact is added to the *CiviCRM* "ACL Group", they will be added to the *BuddyPress* Group as an Administrator.

#### Notes ####

*BP Groups CiviCRM Sync* requires a minimum of *WordPress 4.9*, *BuddyPress 1.8* and *CiviCRM 5.19*. Having the latest version of each plugin active is, of course, highly recommended.

This plugin works in a similar way to the [Drupal *Organic Groups CiviCRM* module](https://civicrm.org/blog/lobo/civicrm-and-og-organic-groups). Existing Groups in *CiviCRM* that were generated by the Drupal *Organic Groups CiviCRM* module can be migrated to become *BuddyPress*-compatible *CiviCRM* Groups.

#### Installation ####

There are two ways to install from GitHub:

###### ZIP Download ######

If you have downloaded *BP Groups CiviCRM Sync* as a ZIP file from the GitHub repository, do the following to install and activate the plugin:

1. Unzip the .zip file and, if needed, rename the enclosing folder so that the plugin's files are located directly inside `/wp-content/plugins/bp-groups-civicrm-sync`
2. Activate the plugin
3. Optionally, convert existing Groups
4. You are done!

###### git clone ######

If you have cloned the code from GitHub, it is assumed that you know what you're doing.
