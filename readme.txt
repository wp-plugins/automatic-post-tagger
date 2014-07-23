=== Automatic Post Tagger ===
Contributors: Devtard
Donate link: http://devtard.com/donate
Tags: automatic, autotagger, keyword, keywords, post, posts, regex, related, relevant, seo, tag, tags, tagger, tagging, wildcard
Requires at least: 3.0
Tested up to: 4.0
Stable tag: 1.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin automatically adds user-defined tags to posts.

== Description ==
APT uses a list of keywords created by the user to automatically tag posts when they're saved/published. Version 1.6 fully supports UTF-8 characters.

= Features =
* Automatically tags posts according to their title, content and excerpt
* Tags can be added when different user-defined keywords ("related words") are found
* Wildcard (regex) support for related words
* Bulk tagging tool for processing multiple posts at once
* Supports custom taxonomies & post types
* Import/Export tool for keywords
* And more (see screenshots)

== Installation ==
1. Install and activate the plugin.
2. Configure the plugin (Settings -> Automatic Post Tagger).
3. Create (import) keywords.
4. Publish/update a post (or use the bulk tagging tool to make the plugin process all posts). If your keywords or their related words are found, new tags will be added.

== Screenshots ==
1. Administration interface
2. Widget located next to the post editor

== Frequently Asked Questions ==
= The "max_input_vars" limit has been exceeded and I can't edit or delete keywords. =
You may encounter this problem if the plugin stores a lot keywords in the database and your PHP configuration prevents input fields from being submitted if there's too many of them. You can fix this by doing one of the following:

1. Change the "Keyword management mode" to "Single input field for all keywords". (You may also use the import/export tool to change the keywords, however you will have to reinstall the plugin every time you need to delete some keywords.)
2. If you can modify your PHP configuration, change the variable "max_input_vars" in your php.ini file to a higher value (1000 is usually the default value).

= I'm getting the "Maximum execution time of XY seconds exceeded" error when tagging posts. =
This might happen if your posts are long or you have a lot of keywords in the database. Here's what you can do:

1. Remove some of your word separators (or enable the option "Replace non-alphanumeric characters with spaces" to ignore them completely).
2. Enable the option "Analyze only XY characters starting at position XY".
3. Lower the number of posts tagged per cycle when using the bulk tagging tool.
4. If you can modify your PHP configuration, change the variable "max_execution_time" in your php.ini file to a higher value (30 is usually the default value).

= I want to add categories to posts instead of tags, what should I do to make it work? =
In the "Settings" widget change the value of the "Taxonomy assigned to posts" option to "category". New categories will be added only if you change keyword names to category IDs instead of their actual names. (When creating a new keyword representing the category "Uncategorized", you'll have to put its ID "1" into the field "Keyword name". If specified related words are found, this category will be added to a post.) Also make sure to uncheck "Keyword names" in the "Search for these items" section if you don't want APT to add categories if their IDs are found in posts. See [this page](http://devtard.com/?p=820) for more information.

= Where does the plugin store its settings? =
The settings and keywords + related words can be found in the following options (DB table wp_options): "automatic_post_tagger", "automatic_post_tagger_keywords" (both of them will be removed if you uninstall the plugin).

== Changelog ==
= 1.6 =
New features:

* UTF-8 support
* Custom taxonomy and post types
* Customizable wildcard regex pattern
* Multicharacter word separators; a space is not automatically treated as a separator anymore (HTML entities can be used to avoid problems if some word separators use characters identical to the delimiter)
* New keyword management mode
* Old tags can be removed from posts even if no new tags are found and added by the plugin
* Automatic backup of keywords when updating the plugin
* Users can choose not to search for keyword names in post content
* Automatic input adjustment can be turned off

Fixed:
 
* Multiple whitespace characters are being replaced with spaces also in the needles - not just in the haystack
* Multiple whitespace characters are being replaced with multiple spaces now (only one space was used before - it turns out that it causes problems when finding needles with wildcards)
* Function replacing non-alphanumeric characters replaces also whitespace characters now
* All relevant variables that are being printed now use the htmlspecialchars functions to make sure that submitted HTML code is displayed as plain text and doesn't mess up the displayed page
* Character limit removed from fgetcsv functions
* Keywords and related words are now being processed with the function htmlspecialchars before displaying in (confirmation) messages
* Duplicite keywords are being ignored when importing, stats fixed
* Wildcards match ANY characters in related words by default, not just alphanumeric characters
* Searching for related words (with appropriate word separators) when wildcard support is disabled
* Finding keywords/related words with special (regex) characters should work just fine; preg_match "Unknown modifier" warnings should no longear appear
* Undefined indexes

Added:

* New option automatic_post_tagger_keywords
* New CSS rule for warning and note messages
* Widget with links to my other plugins

Removed:

* Table "wp_apt_tags" and several suboptions
* Message encouraging users to rate the plugin (annoying and unnecessary)
* The "Contributors" widget
* Function apply_filters (no longer necessary)

Other changes:

* APT now uses the word "keyword" to distinguish the WP tags taxonomy from user-defined tags ("keywords") - many variables and names were changed
* Maxlength values increased from 255 to 5000
* The default number of stored backups is 10
* If multiple keywords with the same names are being saved, the one processed later will be removed
* The "Import keywords from the database" tool now imports any taxonomy that happens to be specified in settings
* If a keyword name is missing when saving all keywords, this particular keyword will be removed
* If the user wants to ignore non-alphanumeric characters, the script automatically ignores currently set word separators and only a space is regarded as one (this should make the searching process sligthly faster)
* The input "#apt_box_keyword_name" will remain active after creating a new keyword via the metabox (which allows faster adding of new keywords from the post editor)
* The APT meta box is displayed and the post can be tagged only if the current post type is allowed by the user
* Names of backup files now use unique IDs instead of timestamps
* If the backup folder doesn't exist or has insufficient permissions, the plugin attempts to fix that
* Exported keywords are now alphabetically sorted in the CSV file
* The update algorithm (backward compatibility among other things - updating from older versions to the newest one instead of the following one)
* User-defined $apt_post_types and $apt_post_statuses are now being prepared via WP API
* The bulk tagging tool now shows the total number of added tags and remaining post IDs in the queue
* New function for changing visibility of widgets
* Values of suboptions "apt_word_separators", "apt_bulk_tagging_statuses", "apt_hidden_widgets" were reset
* Suboptions "apt_bulk_tagging_statuses", "apt_hidden_widgets", "apt_bulk_tagging_queue", "apt_word_separators" changed from string type to array (no need to automatically change string separators in these options when the user changes it anymore)
* Update and installation admin notices are now being dismissed when the plugin verifies that the user actually visited the options page
* Special function for conditions that use the global $pagenow variable
* CSS, JS and the sprite image are now being called with the version parameter to prevent caching of old files
* Underscores in all file names were replaced with dashes (that includes also backup files)
* Minor appearance changes

== Upgrade Notice ==
= 1.6 =
* Multiple new features and bug fixes
