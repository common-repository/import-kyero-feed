=== Import Kyero Feed ===
Contributors: grimaceofdespair
Donate link: https://wordpressfoundation.org/donate/
Tags: importer, wordpress, kyero
Requires at least: 5.2
Tested up to: 6.1
Requires PHP: 5.6
Stable tag: 0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import Easy Real Estate properties and images from a kyero feed.

== Description ==

The Import Kyero Feed will import Properties from a Kyero feed into Easy Real Estate.

Images are downloaded and attached to the property.

Missing Property Features, Property Types and Property Locations are automatically created.

== Installation ==

The quickest method for installing the importer is:

1. Visit Plugins -> Add New in the WordPress dashboard
1. Search for import-kyero-feed
1. Click "Install Now"
1. Finally click "Activate Plugin & Run Importer"

If you would prefer to do things manually then follow these instructions:

1. Upload the `import-kyero-feed` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to the Tools -> Import screen, click on WordPress

== Changelog ==

= 0.1 =
* Initial release

== Frequently Asked Questions ==

= Help! I'm getting out of memory errors or a blank screen. =
If your feed is very large, the import script may run into your host's configured memory limit for PHP.

A message like "Fatal error: Allowed memory size of 8388608 bytes exhausted" indicates that the script can't successfully import your XML file under the current PHP memory limit. If you have access to the php.ini file, you can manually increase the limit; if you do not (your WordPress installation is hosted on a shared server, for instance), you might have to break your exported XML file into several smaller pieces and run the import script one at a time.

For those with shared hosting, the best alternative may be to consult hosting support to determine the safest approach for running the import. A host may be willing to temporarily lift the memory limit and/or run the process directly from their end.

-- [Support Article: Importing Content](https://wordpress.org/support/article/importing-content/#before-importing)

== Filters ==

The importer has a couple of filters to allow you to completely enable/block certain features:

* `import_allow_create_users`: return false if you only want to allow mapping to existing users
* `import_allow_fetch_attachments`: return false if you do not wish to allow importing and downloading of attachments
* `import_attachment_size_limit`: return an integer value for the maximum file size in bytes to save (default is 0, which is unlimited)

There are also a few actions available to hook into:

* `import_start`: occurs after the export file has been uploaded and author import settings have been chosen
* `import_end`: called after the last output from the importer
