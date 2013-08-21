BuddyPress Groups CiviCRM Sync
==============================

The *BuddyPress Groups CiviCRM Sync* plugin provides synchronisation between *CiviCRM* and *BuddyPress* groups in a similar way to the Drupal *Organic Groups CiviCRM* module.

Existing groups in *CiviCRM* that were generated by the Drupal *Organic Groups CiviCRM* module can be migrated to become *BuddyPress*-compatible *CiviCRM* groups.

#### Notes ####

This plugin has been developed using *WordPress 3.6*, *BuddyPress 1.8* and *CiviCRM 4.3.5*. It requires the master branch of the [CiviCRM WordPress plugin](https://github.com/civicrm/civicrm-wordpress) plus the custom WordPress.php hook file from the [CiviCRM Hook Tester repo on GitHub](https://github.com/christianwach/civicrm-wp-hook-tester) so that it overrides the built-in *CiviCRM* file. It also uses (hopefully temporarily) a version of *BP Group Hierarchy 1.3.9* which can be [found on GitHub](https://github.com/christianwach/BP-Group-Hierarchy).

**Bear in mind** this plugin is at a very early stage of development, so all the most strenuous disclaimers apply. If you would like to be involved, please leave a message on the issue queue.

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