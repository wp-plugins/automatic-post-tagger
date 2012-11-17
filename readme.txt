=== Automatic Post Tagger ===
Contributors: Devtard
Tags: add, auto, autoblog, automatic, autotag, autotagging, auto tag, autotagger, generate, keyword, keywords, post, posts, related, relevant, seo, suggest, tag, tagger, tagging, tags, word, words
Requires at least: 3.0
Tested up to: 3.4.2
Stable tag: trunk
License: GPLv2

This plugin automatically adds user-defined tags to posts.

== Description ==
With APT you won't have to manually add tags ever again. You just have to create a list of tags with related words for each of them and you are done. This plugin will add relevant tags automatically when a post is published or updated. It is perfect for autoblogs and lazy bloggers. :)

= Features = 
* Automatically adds tags to posts according to their title, content and excerpt
* Tags are added to a post also when different user-defined keywords are found (example: tag "cat" is added if you assign to it words "cats, kitty, meow" and they are found in a post by the plugin)
* Smart wildcard representation of any alphanumeric characters for related words (pattern "cat\*" will match "cats" and "category", pattern "c\*t" will match "cat" and "colt" etc.)
* Configurable maximum number of tags per post (APT won't add more tags than you want)
* Bulk tagging of already existing posts
* Workaround for Latin diacritic characters (non-Latin alphabets like Arabic or Chinese aren't supported)
* Supports importing already existing tags, creating and importing backups

== Installation ==
1. Upload the plugin to the '/wp-content/plugins/' directory.
2. Activate it through the 'Plugins' menu in WordPress.
3. Configure the plugin (Settings → Automatic Post Tagger).

== Screenshots ==
1. Administration interface
2. Widget located under the post editor

== Frequently Asked Questions ==
= Which plugin data is stored in the database? =
APT stores tags and related words in a table called "wp_apt_tags". Following options can be found in the table "wp_options".

* apt_plugin_version
* apt_stats_current_tags
* apt_stats_assigned_tags
* apt_stats_install_date
* apt_admin_notice_install
* apt_admin_notice_update
* apt_admin_notice_donate
* apt_hidden_widgets
* apt_post_analysis_title
* apt_post_analysis_content
* apt_post_analysis_excerpt
* apt_handling_current_tags
* apt_string_manipulation_convert_diacritic
* apt_string_manipulation_lowercase
* apt_string_manipulation_strip_tags
* apt_string_manipulation_replace_whitespaces
* apt_string_manipulation_replace_nonalphanumeric
* apt_string_manipulation_ignore_asterisks
* apt_word_recognition_separators
* apt_miscellaneous_tag_maximum
* apt_miscellaneous_minimum_keyword_occurrence
* apt_miscellaneous_add_most_frequent_tags_first
* apt_miscellaneous_substring_analysis
* apt_miscellaneous_substring_analysis_length
* apt_miscellaneous_substring_analysis_start
* apt_miscellaneous_wildcards
* apt_bulk_tagging_posts_per_cycle
* apt_bulk_tagging_range
* apt_bulk_tagging_statuses


= What happens after deleting the plugin? Will I have to remove its options etc. from my database? =
No. All plugin data will be automatically removed from your database after you delete the plugin via your administration interface.

= I get the "Maximum execution time of XY seconds exceeded" error when trying to assign tags to all posts. =
Delete all word separators and use the option "Replace non-alphanumeric characters with spaces" or try to assign less tags at once or let set the plugin to analyse less characters per post.

= I can't delete tags assigned by the plugin, it recreates them again! What should I do? =
If you are trying to delete tags from a published post you have to deactivate the plugin in order to delete tags.

= I got a warning message that saying that saved tag name/related words contain non-alphanumeric characters. What does that mean?  =
Your tag name or related words contain different characters than letters, numbers and asterisks. Your data were successfully saved into database but you may want to check the values again to make sure that you accidentally didn't make a typo. Non-alphanumeric characters in posts and your tags/related words are converted to spaces during searching for tags.

= Some tags can't be imported from my backup. Why? =
You are most likely trying to import records with duplicate tag names which are ignored by the plugin.

= ATP doesn't add tags even if they or their related words are in my post! =
This may happen if you put a PHP code in your post that doesn't have correct opening/closing tags (`<?php` and `?>`). You may want to check the option "Replace non-alphanumeric characters with spaces" if you are unable/unwilling to correct your code but you still want to analyze it. Also make sure that the option "Strip PHP/HTML tags from analysed content" is unchecked.

= APT doesn't add unusual tags to my posts, for example HTML tags like &lt;a&gt;. =
WordPress isn't able to do that, it just saves gibberish or an ampty string to the database.

= Which tag will be added if I want to add only one tag per post? =
The one that has the lowest ID (and was found in your post, of course).

= I have another problem that isn't described on this page and wasn't solved by reinstalling the plugin. =
Please post a new thread on the [support forum](http://wordpress.org/support/plugin/automatic-post-tagger).

== Contributions ==
Do you want to have your link displayed here? [Read this &raquo;](http://devtard.com/how-to-contribute-to-automatic-post-tagger)

= Recent donations =
* 07/10/2012: [askdanjohnson.com](http://askdanjohnson.com)

= Tag packs =

= Other =

== Changelog ==
= 1.4 =
* New feature: Customizable bulk tagging
* New feature: Users can hide widgets on the options page
* New feature: Forms use nonces for better security
* Changed: The widget form is now sending data when hitting enter.
* Changed: Explode() functions don't use the parameter 'limit' now
* Changed: Functions searching for strings with separators don't use 2 foreach functions now but a single (a bit faster) regular expression
* Changed: Minor design changes
* Added: Link to the developer's blog

= 1.3 =
* New feature: Content analysis of a substring
* Fixed: Bug causing not removing the option "apt_string_manipulation_lowercase" from the database
* Fixed: Bug responsible for not very accurate stats for assigned tags
* Changed: Upgrade function improved
* Removed (temporarily): Donation notice and Paypal links

= 1.2 =
* New feature: Custom word separators
* New feature: Option for converting diacritic characters to their ASCII equivalents
* New feature: Option for lowercasing strings
* New feature: Option for stripping PHP/HTML tags
* New feature: Option for replacing non-alphanumeric characters with spaces
* New feature: Option for ignoring asterisks when replacing non-alphanumeric characters with spaces
* New feature: Option for replacing whitespace characters with spaces
* Fixed: Bug causing adding duplicate tags to an array (resulting in less space for other tags if the tag limit is set too low)
* Fixed: Bug preventing the script from calculating the max. number of tags that can be added to a post in the case when we don't want to append tags 
* Fixed: Pressing enter when typing in the APT widget doesn't submit the form anymore
* Removed: Option "apt_miscellaneous_tagging_occasion" (tagging algorithm can't be run when saving a post anymore - only for debugging purposes)
* Removed: Facebook share link
* Changed: APT is searching for tags only when no substrings were found (more efficient)
* Changed: Variables in foreach loops are being unsetted
* Changed: Update messages now use htmlspecialchars() to display names of tags and related words

= 1.1 =
* New feature: Meta box located under the post editor allowing adding tags directly to the database.
* New feature: Background color of inputs changes when we check the checkbox
* Fixed: Update function can be triggered also on page update.php
* Fixed: Grammar errors
* Fixed: Link to the donor list
* Fixed: Non-alphanumeric characters in needles (searched phrases) are now replaced with spaces.
* Removed: Link to the developer's blog
* Changed: Donation notification will appear after a month
* Changed: Labels now have the "for" parameter
* Changed: Creating tags from the widget and the options page is done by using the same function

= 1.0 =
* Initial release

== Upgrade Notice ==
= 1.4 =
* New features: You can customize behaviour of the bulk tagging algorithm and toggle widgets.

= 1.3 =
* New feature: You can choose to analyse only a specific part of the content.

= 1.2 =
* New features: Customizable word separators and more control over the searching process.

= 1.1 =
* New feature: You can create tags directly from a widget under the post editor now.

= 1.0 =
* Initial release
