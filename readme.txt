=== Automatic Post Tagger ===
Contributors: Devtard
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=T2QUJ4R6JHKNG
Tags: add, auto, autoblog, automatic, autotag, autotagger, generate, generated, keyword, keywords, post, posts, related, relevance, relevant, seo, synonym, synonyms, tag, tagger, tagging, tags, word, words
Requires at least: 3.0
Tested up to: 3.4.2
Stable tag: trunk
License: GPLv2

This plugin automatically adds user-specified tags to posts.

== Description ==
With APT you won't have to manually add tags ever again. You just have to create a list of tags with related words for each of them and you are done. This plugin will add relevant tags automatically when a post is published or saved.

= Features = 
* Automatically adds tags to posts according to their title, content and excerpt
* Tags are added to a post also when different user-specified keywords are found (example: tag "cat" is added if you assign to it words "cats, kitty, meow" and they are found in a post by the plugin)
* Smart wildcard representation of any alphanumeric characters for related words (pattern "cat\*" will match "cats" and "category", pattern "c\*t" will match "cat" and "colt" etc.)
* Adds tags to all posts with just one click (three ways of handling already assigned tags)
* Configurable maximum amount of tags per post (Automatic Post Tagger won't add more tags than you want)
* Supports importing already existing tags, creating and importing backups
* Workaround for Latin diacritic characters (non-Latin alphabets like Arabic or Chinese are not supported yet)

== Installation ==
1. Upload the plugin to the '/wp-content/plugins/' directory.
2. Activate it through the 'Plugins' menu in WordPress.
3. Configure the plugin (Settings → Automatic Post Tagger).

== Screenshots ==
1. Administration interface

== Frequently Asked Questions ==
= Which plugin data is stored in the database? =
Automatic Post Tagger stores tags and related words in a table called "wp_apt_tags". Following options can be found in the table "wp_options". Everything is deleted after uninstalling the plugin.

* apt_plugin_version
* apt_stats_current_tags
* apt_stats_assigned_tags
* apt_stats_install_date
* apt_admin_notice_install
* apt_admin_notice_update
* apt_admin_notice_donate
* apt_post_analysis_title
* apt_post_analysis_content
* apt_post_analysis_excerpt
* apt_handling_current_tags
* apt_miscellaneous_tag_maximum
* apt_miscellaneous_tagging_occasion
* apt_miscellaneous_wildcards

= What happens after deleting the plugin? Will I have to remove its options etc. from my database? =
No. All plugin data will be automatically removed from your database after you delete the plugin via your administration interface.

= How does searching for tags and related words work? =
Automatic Post Tagger does not work with strings obtained from the database (post title, content and excerpt) directly. After joining all needed strings together it flattens some UTF-8 characters to their basic ASCII counterparts. Then it lowercases the whole string, removes all HTML, PHP and JS tags and replaces multiple whitespace and non-alphanumeric characters with spaces. Diacritic characters in strings that are searched for are also converted to their ASCII equivalents. This workaround is not ideal, but it should work for everyone just fine. In the next version users may gain more control over these actions.

= I cannot delete tags assigned by the plugin, it recreates them again! What should I do? =
If you are trying to delete tags from a published post you have to deactivate the plugin in order to delete tags.

= I got a warning message that said that saved tag name/related words contain non-alphanumeric characters. What does that mean?  =
Your tag name or related words contain different characters than letters, numbers and asterisks. Your data were successfully saved into database but you may want to check the values again to make sure that you accidentally didn't make a typo.

= Some tags can't be imported from my backup. Why? =
You are most likely trying to import records with duplicate tag names which are ignored by the plugin.

= Something does not work. What should I do? =
Try reinstalling the plugin.

= I have another problem that is not described on this page. =
Post a new thread on the [support forum](http://wordpress.org/support/plugin/automatic-post-tagger "support forum").

== Other Notes ==
= TOP 5 contributors =
Nobody has donated yet. Be the first and have your link displayed here!

= Recent donations =
Nobody has donated yet. Be the first and have your link displayed here!

== Changelog ==

= 1.1 =
* Fixed: grammar errors

= 1.0 =
* Initial release

== Upgrade Notice ==

= 1.0 =
* Initial release
