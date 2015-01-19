BuddyPress Groups CiviCRM Sync
==============================

The *BuddyPress Groups CiviCRM Sync* plugin provides synchronisation between *CiviCRM* and *BuddyPress* groups in a similar way to the Drupal *Organic Groups CiviCRM* module.

Existing groups in *CiviCRM* that were generated by the Drupal *Organic Groups CiviCRM* module can be migrated to become *BuddyPress*-compatible *CiviCRM* groups.

#### Notes ####

This plugin requires a minimum of *WordPress 3.9*, *BuddyPress 1.8* and *CiviCRM 4.6-alpha1*. Please refer to the installation page for how to use this plugin with versions of CiviCRM prior to 4.6-alpha1. If you have group hierarchies, then it also requires a minimum of *BP Group Hierarchy 1.3.9*.

For versions of *CiviCRM* prior to 4.6-alpha1, this plugin requires the corresponding branch of the [CiviCRM WordPress plugin](https://github.com/civicrm/civicrm-wordpress) plus the custom WordPress.php hook file from the [CiviCRM Hook Tester repo on GitHub](https://github.com/christianwach/civicrm-wp-hook-tester) so that it overrides the built-in *CiviCRM* file. Please refer to the each repo for further instructions.

#### Installation ####

There are two ways to install from GitHub:

###### ZIP Download ######

If you have downloaded *BuddyPress Groups CiviCRM Sync* as a ZIP file from the GitHub repository, do the following to install and activate the plugin and theme:

1. Unzip the .zip file and, if needed, rename the enclosing folder so that the plugin's files are located directly inside `/wp-content/plugins/bp-groups-civicrm-sync`
2. Activate the plugin
3. Optionally, convert existing groups
4. You are done!

###### git clone ######

If you have cloned the code from GitHub, it is assumed that you know what you're doing.
