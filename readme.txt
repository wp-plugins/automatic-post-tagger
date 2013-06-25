=== Automatic Post Tagger ===
Contributors: Devtard
Donate link: http://devtard.com/donate
Tags: add, auto, autoblog, automatic, autotag, autotagging, auto tag, autotagger, generate, keyword, keywords, post, posts, related, relevant, seo, suggest, tag, tagger, tagging, tags, word, words
Requires at least: 3.0
Tested up to: 3.6
Stable tag: trunk
License: GPLv2

This plugin automatically adds user-defined tags to posts.

== Description ==
With APT you won't have to manually add tags ever again. You just have to create a list of tags with related words for each of them and you are done. This plugin will **add relevant tags automatically** when a post is published or updated. It is perfect for autoblogs and lazy bloggers. :)

= Features = 
* Automatically adds tags to posts according to their title, content and excerpt
* Tags can be added when different user-defined keywords are found
* Smart wildcard representation of any alphanumeric characters
* Configurable maximum number of tags per post
* Bulk tagging of multiple posts
* Import/export tool
* Workaround for Latin diacritic characters (non-Latin alphabets, e.g. Arabic or Chinese aren't supported yet)

*Follow [@devtard_com](http://twitter.com/devtard_com) on Twitter or subscribe to my blog [devtard.com](http://devtard.com) to keep up to date on new releases and WordPress-related information.*

== Installation ==
1. Upload the plugin to the '/wp-content/plugins/' directory.
2. Activate it through the 'Plugins' menu in WordPress.
3. Configure the plugin (Settings -> Automatic Post Tagger).

== Screenshots ==
1. Administration interface
2. Widget located next to the post editor

== Frequently Asked Questions ==

= I have a problem that isn't described on this page and wasn't solved by reinstalling the plugin. =
Please post a new thread on the [support forum](http://wordpress.org/support/plugin/automatic-post-tagger).

= I get the "Maximum execution time of XY seconds exceeded" error when trying to assign tags to multiple posts. =
Lower the number of posts tagged per cycle.

= I can't delete tags assigned by the plugin, it recreates them again! What should I do? =
If you are trying to delete tags from a published post you have to deactivate the plugin in order to delete tags.

= Some tags can't be imported from my backup. Why? =
You are most likely trying to import records with duplicate tag names which are ignored by the plugin.

= ATP doesn't add tags even if they or their related words are in my post! =
This may happen if you put a PHP code in your post that doesn't have correct opening/closing tags (`<?php` and `?>`). You may want to check the option "Replace non-alphanumeric characters with spaces" if you are unable/unwilling to correct your code, but you still want to analyze it. Also make sure that the option "Strip PHP/HTML tags from analyzed content" is unchecked.

= APT doesn't add unusual tags to my posts, for example HTML tags like &lt;a&gt;. =
WordPress isn't able to do that, it just saves gibberish or an ampty string to the database.

= Which plugin data is stored in the database? =
APT stores tags and related words in a table called "wp_apt_tags". Plugin settings can be found in the option "automatic_post_tagger".
All plugin data will be automatically removed from your database after you delete the plugin via your administration interface.

== Changelog ==
= 1.5 =
* New feature: Custom string separator
* New feature: Import/export of "real" CSV files (the script no longer uses a custom file structure)
* New feature: Meta box is able to display confirmation and error messages
* New feature: Option for hiding warning messages
* New feature: Option for using wildcards for non-alphanumeric values
* New feature: Storing multiple backups at once and deleting older ones automatically
* Fixed: Bug causing jQuery issues on the page with the post editor
* Fixed: Bug causing not removing uploaded files from the plugin directory
* Fixed: Bug causing the inability to add tags with characters that need to be stripslashed before saving and htmlspecialcharsed when displaying
* Fixed: Inability to use a vertical bar in tags and related words
* Fixed: Not removing temporary CSV files after uploading
* Fixed: PHP notices triggered by undefined variables
* Fixed: Unnecessary loading of post title, content or excerpt when not needed
* Added: AJAX response dialogues in the meta box for adding tags
* Added: Clickable link for continuing the bulk tagging if the browser fails to redirect to another page
* Added: Condition for checking whether plugin settings already exist
* Added: Condition for checking whether there are tags that can be exported
* Added: Condition for checking whether the separator is included when saving appropriate options
* Added: Condition for checking whether we need to print a JS function
* Added: Donation links
* Added: Link to developer's Twitter account
* Added: New directory "backup" for backup files
* Added: New directory "css" for CSS files
* Added: New directory "js" for JS files
* Added: New function for creating options
* Added: New image "apt_sprite_icons.png" to the directory "images"
* Added: New option "automatic_post_tagger"
* Added: New "tooltip" bubbles replaced ubiquitous explanatory notes
* Added: Nonces for AJAX scripts
* Added: Nonces for links with GET parameters
* Added: Numeric values are being checked whether they are natural and integers
* Added: Prompt asking for "showing some love" (plugin rating, sharing on social networks etc.)
* Added: Replacing old wildcard characters and string separators when a new value is set
* Added: Storing plugin settings in one option with an array
* Added: UNIX timestamp in file names of CSV backups; users can now import any file with the prefix "apt_backup"
* Added: Usage of the internal WP jQuery library
* Added: Usage of the $wpdb class (including its prepare method for preventing SQL injection)
* Removed: All DB options from version 1.4
* Removed: All icons in the directory "images"
* Removed: Category prefixes in option names
* Removed: dbDelta function for creating the table for tags
* Removed: Deprecated PHP functions (mysql_query, mysql_fetch_array, mysql_num_rows)
* Removed: Iframe displaying latest contributors (I am too lazy to update it in real time and I also removed it for security reasons) - data is being hardcoded instead
* Removed: Link to jQuery library at googleapis.com
* Removed: Link to review the plugin as a new post
* Removed: Link to the contributions page in readme.txt (there are too few records which don't need a special page)
* Removed: Stats for overall assigned tags (I wasn't able to find a working solution for updating the number of added tags without using an extra option - using the main option for all settings didn't work while tagging multiple posts at once in a loop.)
* Removed: The ability to hide small widgets on the right side
* Removed: Unnecessary tag IDs in backup files
* Removed: Unnecessary variables storing $_POST values that were taking extra space
* Renamed: Directory "images" -> "img"
* Updated: Code structure (positions of several functions were rearranged)
* Updated: CSS classes for widgets and sidebar links with icons
* Updated: CSS enqueuing
* Updated: Meta box for adding tags
* Updated: Error handling (variables for HTML message tags)
* Updated: Function "apt_get_plugin_version" uses the function "get_plugin_data" now
* Updated: Functions preq_quote() use a new parameter '/'
* Updated: Function for displaying the admin prompt will be displayed only if no other notice is active
* Updated: Location of backup files (moved to the directory "backup")
* Updated: Option "apt_bulk_tagging_statuses" (new default post status "inherit")
* Updated: Option "apt_word_recognition_separators" (new default separators "\" and "|")

= 1.4 =
* New feature: Customizable bulk tagging
* New feature: Forms use nonces for better security
* New feature: Users can hide widgets on the options page
* Added: Link to the developer's blog
* Changed: The widget form is now sending data when hitting enter.
* Changed: Explode() functions don't use the parameter 'limit' now
* Changed: Functions searching for strings with separators don't use 2 foreach functions now but a single (a bit faster) regular expression
* Changed: Minor design changes
* Changed: Export button was moved to the widget "Import/Export tags"

= 1.3 =
* New feature: Content analysis of a substring
* Fixed: Bug causing not removing the option "apt_string_manipulation_lowercase" from the database
* Fixed: Bug responsible for not very accurate stats for assigned tags
* Changed: Upgrade function improved
* Removed (temporarily): Donation notice and Paypal links

= 1.2 =
* New feature: Custom word separators
* New feature: Option for converting diacritic characters to their ASCII equivalents
* New feature: Option for ignoring asterisks when replacing non-alphanumeric characters with spaces
* New feature: Option for lowercasing strings
* New feature: Option for replacing non-alphanumeric characters with spaces
* New feature: Option for replacing whitespace characters with spaces
* New feature: Option for stripping PHP/HTML tags
* Fixed: Bug causing adding duplicate tags to an array (resulting in less space for other tags if the tag limit is set too low)
* Fixed: Bug preventing the script from calculating the max. number of tags that can be added to a post in the case when we don't want to append tags 
* Fixed: Pressing enter when typing in the APT widget doesn't submit the form anymore
* Changed: APT is searching for tags only when no substrings were found (more efficient)
* Changed: Update messages now use htmlspecialchars() to display names of tags and related words
* Changed: Variables in foreach loops are being unsetted
* Removed: Facebook share link
* Removed: Option "apt_miscellaneous_tagging_occasion" (tagging algorithm can't be run when saving a post anymore - only for debugging purposes)

= 1.1 =
* New feature: Background color of inputs changes when we check the checkbox
* New feature: Meta box located next to the post editor allowing adding tags directly to the database.
* Fixed: Grammar errors
* Fixed: Link to the donor list
* Fixed: Non-alphanumeric characters in needles (searched phrases) are now replaced with spaces.
* Fixed: Update function can be triggered also on page update.php
* Changed: Creating tags from the widget and the options page is done by using the same function
* Changed: Donation notification will appear after a month
* Changed: Labels now have the "for" parameter
* Removed: Link to the developer's blog

= 1.0 =
* Initial release

== Upgrade Notice ==
= 1.5 =
* Multiple new features, improved speed, stability and security of the plugin.

= 1.4 =
* New features: You can customize behaviour of the bulk tagging algorithm and toggle widgets.

= 1.3 =
* New feature: You can choose to analyze only a specific part of the content.

= 1.2 =
* New features: Customizable word separators and more control over the searching process.

= 1.1 =
* New feature: You can create tags directly from a widget under the post editor now.

= 1.0 =
* Initial release
