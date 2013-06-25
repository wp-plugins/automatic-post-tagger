<?php
/*
Plugin Name: Automatic Post Tagger
Plugin URI: http://wordpress.org/plugins/automatic-post-tagger/
Description: This plugin automatically adds user-defined tags to posts.
Version: 1.5
Author: Devtard
Author URI: http://devtard.com
License: GPLv2 or later
*/

/*  Copyright 2012  Devtard  (email : devtard@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/



## Bug reports are appreciated. -- Devtard

#################################################################
#################### BASIC DECLARATIONS #########################
#################################################################

global $wpdb, $apt_table; //these variables HAVE TO be declared as a global in order to work in the activation/uninstall functions

$apt_settings = get_option('automatic_post_tagger');
$apt_table = $wpdb->prefix .'apt_tags'; //table for storing tags and related words

$apt_plugin_url = WP_PLUGIN_URL . "/" . basename(dirname(__FILE__)) . "/";
$apt_plugin_dir = WP_PLUGIN_DIR . "/" . basename(dirname(__FILE__)) . "/";
$apt_plugin_basename = plugin_basename(__FILE__); //automatic-post-tagger/automatic-post-tagger.php

$apt_new_backup_file_name_prefix = 'apt_backup';
$apt_new_backup_file_name_suffix = '.csv';

$apt_backup_dir_rel_path = $apt_plugin_dir .'backup/'; //relative path

$apt_new_backup_file_name = $apt_new_backup_file_name_prefix .'_'. time() . $apt_new_backup_file_name_suffix;
$apt_new_backup_file_rel_path = $apt_plugin_dir .'backup/'. $apt_new_backup_file_name; //relative path
$apt_new_backup_file_abs_path = $apt_plugin_url .'backup/'. $apt_new_backup_file_name; //absolute path

$apt_message_html_prefix_updated = '<div id="message" class="updated"><p>';
$apt_message_html_prefix_error = '<div id="message" class="error"><p>';
$apt_message_html_suffix = '</p></div>';

$apt_example_related_words = 'Example: &quot;cats'. $apt_settings['apt_string_separator'] .'kitty'. $apt_settings['apt_string_separator'] .'meo'. $apt_settings['apt_wildcard_character'] .'w&quot;. Related words are optional.';

//$wpdb->show_errors(); //for debugging

#################################################################
#################### get plugin version #########################

function apt_get_plugin_version(){ //return plugin version
	//this must not be removed or the function get_plugin_data won't work
	if(!function_exists('get_plugin_data')){
		require_once(ABSPATH .'wp-admin/includes/plugin.php');
	}

	$apt_plugin_data = get_plugin_data( __FILE__, FALSE, FALSE);
	$apt_plugin_version = $apt_plugin_data['Version'];
	return $apt_plugin_version;
}

#################################################################
####################### MYSQL MANAGEMENT ########################

#################################################################
#################### table creation function ####################

function apt_create_table(){ //this functions defines the plugin table structure - it is called when the plugin is being activated
	global $wpdb, 
	$apt_table;

	//this should prevent creating tables with different charset and collation
	if(!empty($wpdb->charset)){
		$apt_chararset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
	}
	if(!empty($wpdb->collate)){
		$apt_chararset_collate .= " COLLATE {$wpdb->collate}";
	}

	//primary key should be "tag" because when importing tags some may have the same id, so we need to compare the tag, not id - that is used only for deleting by checking checkboxes
	$apt_create_table_sql = 'CREATE TABLE IF NOT EXISTS '. $apt_table .'(
		id INT NOT NULL auto_increment,
		tag VARCHAR (255),
		related_words VARCHAR (255),
		UNIQUE KEY (tag),
		PRIMARY KEY  (id)
		) '. $apt_chararset_collate .';';

	$wpdb->query($apt_create_table_sql);
}
#################################################################
#################### table deletion function ####################

function apt_drop_table(){
	global $wpdb,
	$apt_table;

	$wpdb->query('DROP TABLE '. $apt_table); 
}
#################################################################
#################### activate function ##########################

function apt_create_options(){
	if(get_option('automatic_post_tagger') == FALSE){ //create the option only if it isn't defined yet
		$apt_default_settings = array(
			'apt_plugin_version' => apt_get_plugin_version(), //for future updates of the plugin
			'apt_admin_notice_install' => '1', //option for displaying installation notice
			'apt_admin_notice_update' => '0', //option for displaying update notice
			'apt_admin_notice_prompt' => '1', //option for displaying a notice asking the user to do stuff (plugin rating, sharing the plugin etc.)
			'apt_hidden_widgets' => '', //option for hidden widgets
			'apt_stats_current_tags' => '0',
			'apt_stats_install_date' => time(),
			'apt_title' => '1',
			'apt_content' => '1',
			'apt_excerpt' => '0',
			'apt_handling_current_tags' => '1',
			'apt_convert_diacritic' => '1',
			'apt_ignore_case' => '1',
			'apt_strip_tags' => '1',
			'apt_replace_whitespaces' => '1',
			'apt_replace_nonalphanumeric' => '0',
			'apt_ignore_wildcards' => '1',
			'apt_substring_analysis' => '0',
			'apt_substring_analysis_length' => '1000',
			'apt_substring_analysis_start' => '0',
			'apt_wildcards' => '1',
			'apt_wildcards_alphanumeric_only' => '0',
			'apt_word_separators' => '.,?!:;\'"`\|/()[]{}_+=-<>~@#$%^&*',
			'apt_tag_limit' => '20',
			'apt_tagging_hook_type' => '1',
			'apt_string_separator' => ',', //the comma will be a string separator for related words, DB options etc.
			'apt_wildcard_character' => '*',
			'apt_stored_backups' => '5',
			'apt_warning_messages' => '1',
			'apt_bulk_tagging_posts_per_cycle' => '15',
			'apt_bulk_tagging_queue' => '',
			'apt_bulk_tagging_statuses' => 'auto-draft,draft,inherit,trash'
		);

		//TODO v1.6	'apt_miscellaneous_add_most_frequent_tags_first', '1',
		//TODO v1.6	'apt_miscellaneous_minimum_keyword_occurrence', '1',


		add_option('automatic_post_tagger', $apt_default_settings, '', 'no'); //single option for saving default settings
	}//-if the option doesn't exist

}
#################################################################
#################### activate function ##########################

function apt_install_plugin(){ //runs only after MANUAL activation! -- also used for restoring settings
	apt_create_table(); //creating table for tags
	apt_create_options();
}
#################################################################
#################### update function ############################

function apt_update_plugin(){ //runs when all plugins are loaded (needs to be deleted after register_update_hook is available)
	//$apt_settings = get_option('automatic_post_tagger'); //TODO: v1.6

	if(current_user_can('manage_options')){
		$apt_current_version = apt_get_plugin_version();

		if((get_option('apt_plugin_version') != FALSE) AND (get_option('apt_plugin_version') <> $apt_current_version)){ //check if the saved version is not equal to the current version -- the FALSE check is there to determine if the option exists //TODO v1.6 change this condition to $apt_settings[] OR get_option

			#### now comes everything what must be changed in the new version
			//if the user has a very old version, we have to include all DB changes that are included in the following version checks - I am not really not sure if upgrading from the old versions are correctly supported, I don't really care -- reinstalling solves any problem anyway
			// we must not forget to include new changes to conditions for all previous versions

			//get_option is used here for currently not-existing options, this might be a problem (but it shouldn't) -- potential TODO
			//maybe I should add a acheck whether the value exists first

			if(get_option('apt_plugin_version') == '1.1' AND $apt_current_version == '1.2'){ //upgrade from 1.1 to 1.2 -- get_option must not be deleted
				apt_create_options();
			}
			if(get_option('apt_plugin_version') == '1.2' AND $apt_current_version == '1.3'){ //upgrade from 1.2 to 1.3 -- get_option must not be deleted
				apt_create_options();
			}
			if(get_option('apt_plugin_version') == '1.3' AND $apt_current_version == '1.4'){ //upgrade from 1.3 to 1.4 -- get_option must not be deleted
				apt_create_options();
			}

			##current version 1.5:
			if(get_option('apt_plugin_version') == '1.4' AND $apt_current_version == '1.5'){ //upgrade from 1.4 to 1.5 -- get_option must not be deleted

				//new stuff will be stored in one option as an array - we are adding old values
				$apt_settings_v15 = array(
					'apt_plugin_version' => apt_get_plugin_version(),
					'apt_admin_notice_install' => get_option('apt_admin_notice_install'),
					'apt_admin_notice_update' => get_option('apt_admin_notice_update'),
					'apt_admin_notice_prompt' => get_option('apt_admin_notice_donate'),
					'apt_hidden_widgets' => '', //resetting hidden widgets
					'apt_stats_current_tags' => get_option('apt_stats_current_tags'),
					'apt_stats_install_date' => get_option('apt_stats_install_date'),
					'apt_title' => get_option('apt_post_analysis_title'),
					'apt_content' => get_option('apt_post_analysis_content'),
					'apt_excerpt' => get_option('apt_post_analysis_excerpt'),
					'apt_handling_current_tags' => get_option('apt_handling_current_tags'),
					'apt_convert_diacritic' => get_option('apt_string_manipulation_convert_diacritic'),
					'apt_ignore_case' => get_option('apt_string_manipulation_lowercase'),
					'apt_strip_tags' => get_option('apt_string_manipulation_strip_tags'),
					'apt_replace_whitespaces' => get_option('apt_string_manipulation_replace_whitespaces'),
					'apt_replace_nonalphanumeric' => get_option('apt_string_manipulation_replace_nonalphanumeric'),
					'apt_ignore_wildcards' => get_option('apt_string_manipulation_ignore_asterisks'),
					'apt_substring_analysis' => get_option('apt_miscellaneous_substring_analysis'),
					'apt_substring_analysis_length' => get_option('apt_miscellaneous_substring_analysis_length'),
					'apt_substring_analysis_start' => get_option('apt_miscellaneous_substring_analysis_start'),
					'apt_wildcards' => get_option('apt_miscellaneous_wildcards'),
					'apt_wildcards_alphanumeric_only' => '0',
					'apt_word_separators' => get_option('apt_word_recognition_separators'),
					'apt_tag_limit' => get_option('apt_miscellaneous_tag_maximum'),
					'apt_tagging_hook_type' => '1',
					'apt_string_separator' => ';',
					'apt_wildcard_character' => '*',
					'apt_stored_backups' => '5',
					'apt_warning_messages' => '1',
					'apt_bulk_tagging_posts_per_cycle' => get_option('apt_bulk_tagging_posts_per_cycle'),
					'apt_bulk_tagging_queue' => get_option('apt_bulk_tagging_range'),
					'apt_bulk_tagging_statuses' => 'auto-draft;draft;inherit;trash' //adding new "inherit" status
				);

				add_option('automatic_post_tagger', $apt_settings_v15, '', 'no'); //single option for saving default settings

//die("db version: ". get_option('apt_plugin_version') ." current: ". $apt_current_version ." apt option: ". print_r($apt_settings_v15)); //for debugging

				//now delete the old options from version 1.4, we don't need them anymore
				delete_option('apt_plugin_version');
				delete_option('apt_admin_notice_install');
				delete_option('apt_admin_notice_update');
				delete_option('apt_admin_notice_donate');
				delete_option('apt_hidden_widgets');
				delete_option('apt_stats_current_tags');
				delete_option('apt_stats_assigned_tags');
				delete_option('apt_stats_install_date');
				delete_option('apt_post_analysis_title');
				delete_option('apt_post_analysis_content');
				delete_option('apt_post_analysis_excerpt');
				delete_option('apt_handling_current_tags');
				delete_option('apt_string_manipulation_convert_diacritic');
				delete_option('apt_string_manipulation_lowercase');
				delete_option('apt_string_manipulation_strip_tags');
				delete_option('apt_string_manipulation_replace_whitespaces');
				delete_option('apt_string_manipulation_replace_nonalphanumeric');
				delete_option('apt_string_manipulation_ignore_asterisks');
				delete_option('apt_word_recognition_separators');
				delete_option('apt_miscellaneous_tag_maximum');
				delete_option('apt_miscellaneous_substring_analysis');
				delete_option('apt_miscellaneous_substring_analysis_length');
				delete_option('apt_miscellaneous_substring_analysis_start');
				delete_option('apt_miscellaneous_wildcards');
				delete_option('apt_bulk_tagging_posts_per_cycle');
				delete_option('apt_bulk_tagging_range');
				delete_option('apt_bulk_tagging_statuses');

			}//-upgrade to 1.5


//			if($apt_settings['apt_plugin_version'] == '1.5' AND $apt_current_version == '1.6'){ //upgrade from 1.5 to 1.6 //TODO: v1.6
//			}//-upgrade to 1.6 //TODO: v1.6


			#### -/changes


			#### update version and show the update notice
			//retrieve all saved settings
			$apt_settings = get_option('automatic_post_tagger'); //TODO: v1.6 -- remove this and uncomment lines above with v1.6

			//modify settings
			$apt_settings['apt_admin_notice_update'] = 1; //we want to show the admin notice after upgrading
			$apt_settings['apt_plugin_version'] = $apt_current_version; //update plugin version in DB

			//update settings
			update_option('automatic_post_tagger', $apt_settings); 

		}//-if different versions
	}//if current user can
}
#################################################################
#################### uninstall function #########################

function apt_uninstall_plugin(){ //runs after uninstalling of the plugin -- also used for restoring settings
	apt_drop_table();
	delete_option('automatic_post_tagger');
}

#################################################################
########################## HOOKS ################################
#################################################################

if(is_admin()){ //these functions will be executed only if the admin panel is being displayed for performance reasons
	add_action('admin_menu', 'apt_menu_link');
	add_action('admin_notices', 'apt_plugin_admin_notices', 20); //check for admin notices

	//saving resources to avoid performance issues
	if($GLOBALS['pagenow'] == 'plugins.php'){ //check if the admin is on page plugins.php
		add_filter('plugin_action_links', 'apt_plugin_action_links', 12, 2);
		add_filter('plugin_row_meta', 'apt_plugin_meta_links', 12, 2);
	}

	if(in_array($GLOBALS['pagenow'], array('plugins.php', 'update-core.php', 'update.php'))){ //check if the admin is on pages update-core.php, plugins.php or update.php
		add_action('plugins_loaded', 'apt_update_plugin');
		register_activation_hook(__FILE__, 'apt_install_plugin');
		register_uninstall_hook(__FILE__, 'apt_uninstall_plugin');
	}

	if($GLOBALS['pagenow'] == 'options-general.php' AND $_GET['page'] == 'automatic-post-tagger'){ //check if the user is on page options-general.php?page=automatic-post-tagger
		add_action('admin_print_scripts', 'apt_insert_ajax_nonce_options_page'); //print required JS nonce
		add_action('admin_enqueue_scripts', 'apt_load_options_page_scripts'); //load js and css on the options page
	}

	if(in_array($GLOBALS['pagenow'], array('post.php', 'post-new.php'))){ //check if the admin is on pages post.php, post-new.php
		add_action('admin_print_scripts', 'apt_insert_ajax_nonce_meta_box'); //print required JS nonce
		add_action('admin_enqueue_scripts', 'apt_load_meta_box_scripts'); //load JS and css for the widget located on the editor page
		add_action('add_meta_boxes', 'apt_meta_box_add'); //add box to the post editor
	}

	//this must not be in the condition before or it will not work
	add_action('wp_ajax_apt_meta_box_create_new_tag', 'apt_meta_box_create_new_tag'); //callback for function saving the tag from meta_box
	add_action('wp_ajax_apt_toggle_widget', 'apt_toggle_widget'); //callbacks for function toggling visibility of widgets
}//-is_admin


//this code will be executed after every page reload!!
//TODO - find out whether it should be executed only in the backend or it has to be executed all the time because of scheduled posts

$apt_settings = get_option('automatic_post_tagger');

if($apt_settings['apt_tagging_hook_type'] == 1){
	add_action('publish_post','apt_single_post_tagging'); //executes the tagging script after publishing a post
}
else{ //trigger tagging when saving the post
	add_action('save_post','apt_single_post_tagging'); //executes the tagging script after saving a post
}


#################################################################
#################### action & meta links ########################

function apt_plugin_action_links($links, $file){
	global $apt_plugin_basename;

	if($file == $apt_plugin_basename){
 		$apt_settings_link = '<a href="'. admin_url('options-general.php?page=automatic-post-tagger') .'">' . __('Settings') . '</a>';
		$links = array_merge($links, array($apt_settings_link)); 
	}
 	return $links;
}

function apt_plugin_meta_links($links, $file){
	global $apt_plugin_basename;

	if($file == $apt_plugin_basename){
		$links[] = '<a href="http://wordpress.org/plugins/automatic-post-tagger/faq">FAQ</a>';
		$links[] = '<a href="http://wordpress.org/support/plugin/automatic-post-tagger">Support</a>';
		$links[] = '<a href="http://devtard.com/donate">Donate</a>';
	}
	return $links;
}
#################################################################
#################### menu link ##################################

function apt_menu_link(){
	$page = add_options_page('Automatic Post Tagger', 'Automatic Post Tagger', 'manage_options', 'automatic-post-tagger', 'apt_options_page');
}

#################################################################
######################## ADMIN NOTICES ##########################

function apt_plugin_admin_notices(){
	if(current_user_can('manage_options')){

		global $apt_message_html_prefix_updated,
		$apt_message_html_prefix_error,
		$apt_message_html_suffix;

		$apt_settings = get_option('automatic_post_tagger');

		###########################################################
		######################## GET actions ######################
		//must be before other checks
		//nonces are used for better security
		//isset checks must be there or the nonce check will cause the page to die

		if(isset($_GET['n']) AND $_GET['n'] == 1 AND check_admin_referer('apt_admin_notice_install_nonce')){
			$apt_settings['apt_admin_notice_install'] = 0; //hide activation notice
			update_option('automatic_post_tagger', $apt_settings); //save settings

			echo $apt_message_html_prefix_updated .'<b>Note:</b> Managing tags (creating, importing, editing, deleting) on this page doesn\'t affect tags that are already added to your posts.'. $apt_message_html_suffix; //display quick info for beginners
		}
//TODO v1.X: each version must have a unique notice
		if(isset($_GET['n']) AND $_GET['n'] == 2 AND check_admin_referer('apt_admin_notice_update_nonce')){
			$apt_settings['apt_admin_notice_update'] = 0; //hide update notice
			update_option('automatic_post_tagger', $apt_settings); //save settings

			echo $apt_message_html_prefix_updated .'<b>What\'s new in APT v1.5?</b>
			<br /><br />You can finally set your own string separator (using a comma is highly recommended), use wildcards for non-alphanumeric characters,
			create and import CSV files in a&nbsp;standardized format (remember to create one ASAP), store multiple backups at once and hide warning messages.
			The widget located next to the post editor is now able to display confirmation and error messages.

			<br /><br />A lot of code has been changed since the previous version. The plugin should be faster, more secure and stable.
			If something won\'t work, reinstall the plugin and post a new bug report on the <a href="http://wordpress.org/support/plugin/automatic-post-tagger">support forum</a>, please. -- <em>Devtard</em>'. $apt_message_html_suffix; //show new functions (should be same as the upgrade notice in readme.txt)
		}

		//prompt notice checking via GET
		if(isset($_GET['n']) AND $_GET['n'] == 3 AND check_admin_referer('apt_admin_notice_prompt_3_nonce')){
			$apt_settings['apt_admin_notice_prompt'] = 0; //hide prompt notice
			update_option('automatic_post_tagger', $apt_settings); //save settings

		}
		if(isset($_GET['n']) AND $_GET['n'] == 4 AND check_admin_referer('apt_admin_notice_prompt_4_nonce')){
			$apt_settings['apt_admin_notice_prompt'] = 0; //hide prompt notice and display another notice (below)
			update_option('automatic_post_tagger', $apt_settings); //save settings

			echo $apt_message_html_prefix_updated .'<b>Thank you.</b>'. $apt_message_html_suffix; //show "thank you" message
		}

		######################################################################################
		######################## admin notices not based on GET actions ######################
		if($apt_settings['apt_admin_notice_install'] == 1){ //show link to the setting page after installing
			echo $apt_message_html_prefix_updated .'<b>Automatic Post Tagger</b> has been installed. <a href="'. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&n=1'), 'apt_admin_notice_install_nonce') .'">Set up the plugin &raquo;</a>'. $apt_message_html_suffix;
		}
		if($apt_settings['apt_admin_notice_update'] == 1){ //show link to the setting page after updating
			echo $apt_message_html_prefix_updated .'<b>Automatic Post Tagger</b> has been updated to version <b>'. $apt_settings['apt_plugin_version'] .'</b>. <a href="'. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&n=2'), 'apt_admin_notice_update_nonce') .'">Find out what\'s new &raquo;</a>'. $apt_message_html_suffix;
		}

		//prompt notice
		if($apt_settings['apt_admin_notice_prompt'] == 1){ //determine whether the prompt notice was not dismissed yet
			if(((time() - $apt_settings['apt_stats_install_date']) >= 2629743) AND ($apt_settings['apt_admin_notice_update'] == 0) AND !isset($_GET['n'])){ //show prompt notice ONLY after a month (2629743 seconds), if the update notice isn't currently displayed and if any other admin notice isn't active

				//the style="float:right;" MUST NOT be deleted, since the message can be displayed anywhere where APT CSS styles aren't loaded!
				echo $apt_message_html_prefix_updated .'
					<b>Thanks for using <acronym title="Automatic Post Tagger">APT</acronym>!</b> You\'ve installed this plugin over a month ago. If you are satisfied with the results,
					could you please <a href="http://wordpress.org/support/view/plugin-reviews/automatic-post-tagger" target="_blank">rate the plugin</a> and share it with others?
					Positive feedback is a good motivation for further development. <em>-- Devtard</em>

					<span style="float:right;"><small>
					<a href="'. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&n=4'), 'apt_admin_notice_prompt_4_nonce') .'" title="Hide this notification"><b>OK, but I\'ve done that already!</b></a>
					| <a href="'. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&n=3'), 'apt_admin_notice_prompt_3_nonce') .'" title="Hide this notification">Don\'t bug me anymore!</a>
					</small></span>
				'. $apt_message_html_suffix;
			}//-if time + tag count check
		}//-if donations

	}//-if can manage options check
}

#################################################################
#################### JAVASCRIPT & CSS ###########################

//these functions call internal jQuery libraries, which are used instead of linking to googleapis.com in <=v1.4

function apt_load_meta_box_scripts(){ //load JS and CSS for the meta box for adding new tags
	global $apt_plugin_url;
	wp_enqueue_style('apt_style', $apt_plugin_url .'css/apt_style.css'); //load CSS
	wp_enqueue_script('apt_meta_box_js', $apt_plugin_url . 'js/apt_meta_box.js', array('jquery')); //load JS (adding new tags)
}

function apt_load_options_page_scripts(){ //load JS and CSS on the options page
	global $apt_plugin_url;
	wp_enqueue_style('apt_style', $apt_plugin_url .'css/apt_style.css'); //load CSS
	wp_enqueue_script('apt_options_page_js', $apt_plugin_url . 'js/apt_options_page.js', array('jquery')); //load JS (changing the background, toggling widgets)
}


//nonce generation for AJAX stuff - values defined here are retrieved in .js scripts

function apt_insert_ajax_nonce_meta_box(){ //load JS with nonce
	$apt_meta_box_nonce = wp_create_nonce('apt_meta_box_nonce');
?>
<!-- Automatic Post Tagger -->
<script type="text/javascript">
	var apt_meta_box_nonce = {
		security: '<?php echo $apt_meta_box_nonce; ?>'
	}
</script>
<!-- //-Automatic Post Tagger -->
<?php
}


function apt_insert_ajax_nonce_options_page(){ //load JS with nonce
	$apt_options_page_nonce = wp_create_nonce('apt_options_page_nonce');
?>
<!-- Automatic Post Tagger -->
<script type="text/javascript">
	var apt_options_page_nonce = {
		security: '<?php echo $apt_options_page_nonce; ?>'
	}
</script>
<!-- //-Automatic Post Tagger -->
<?php
}


#################################################################
######################## META BOX & WIDGETS #####################


function apt_meta_box_create_new_tag(){ //save tag sent via meta box
	check_ajax_referer('apt_meta_box_nonce', 'security');
	apt_create_new_tag($_POST['apt_box_tag_name'],$_POST['apt_box_tag_related_words']);
	die; //the AJAX script has to die or it will return exit(0)
}
 
function apt_toggle_widget(){ //update visibility of widgets via AJAX
	$apt_settings = get_option('automatic_post_tagger');
	check_ajax_referer('apt_options_page_nonce', 'security');

	$apt_hidden_widgets_count = substr_count($apt_settings['apt_hidden_widgets'], $apt_settings['apt_string_separator']) + 1; //variable prints number of hidden widgets; must be +1 higher than the number of separators!

	if($apt_settings['apt_hidden_widgets'] == ''){
		$apt_hidden_widgets_array = array();
	}
	else{
		$apt_hidden_widgets_array = explode($apt_settings['apt_string_separator'], $apt_settings['apt_hidden_widgets']);
	}


	if(in_array($_POST['apt_widget_id'], $apt_hidden_widgets_array)){ //is the widget ID in the array?
		unset($apt_hidden_widgets_array[array_search($_POST['apt_widget_id'], $apt_hidden_widgets_array)]);//the ID was found, remove it -- that array_serach thing is there to determine which array key is assigned to the value

		$apt_settings['apt_hidden_widgets'] = implode($apt_settings['apt_string_separator'], $apt_hidden_widgets_array);
		update_option('automatic_post_tagger', $apt_settings); //save settings
	}
	else{
 		array_push($apt_hidden_widgets_array, $_POST['apt_widget_id']); //add the ID to the end of the array

		$apt_settings['apt_hidden_widgets'] = implode($apt_settings['apt_string_separator'], $apt_hidden_widgets_array);
		update_option('automatic_post_tagger', $apt_settings); //save settings
	}
	die; //the AJAX script has to die or it will return exit(0)
}

## meta boxes
function apt_meta_box_add(){ //add meta box
	add_meta_box('apt_meta_box','Automatic Post Tagger','apt_meta_box_content','post','side');
}
function apt_meta_box_content(){ //meta box content
	global $apt_example_related_words;
	$apt_settings = get_option('automatic_post_tagger');
?>
	<p>
		Tag name: <span class="apt_help" title="Example: &quot;cat&quot;">i</span>
		<input onkeypress="return apt_enter_submit(event);" type="text" id="apt_box_tag_name" name="apt_box_tag_name" value="" maxlength="255" />
	</p>
	<p>
		Related words (separated by "<b><?php echo $apt_settings['apt_string_separator']; ?></b>"): <span class="apt_help" title="<?php echo $apt_example_related_words; ?>">i</span>
		<input onkeypress="return apt_enter_submit(event);" type="text" id="apt_box_tag_related_words" name="apt_box_tag_related_words" value="" maxlength="255" />
	</p>
	<p>
		<input class="button" type="button" id="apt_meta_box_create_new_tag_button" value=" Create new tag ">
	</p>

		<div id="apt_box_message"></div>

<?php
}

#################################################################
########################## TAGGING ALGORITHMS ###################
#################################################################

function apt_print_sql_where_without_specified_statuses(){ //this prints part of a SQL command that is used for terieving post IDs for bulk tagging - it returns IDs of posts without specified post statuses
	$apt_settings = get_option('automatic_post_tagger');

	$apt_table_select_posts_with_definded_statuses = ''; //this declaration is here to prevent throwing the notice "Undefined variable"

	//if no post statuses are set, don't add them to the SQL query
	if($apt_settings['apt_bulk_tagging_statuses'] != ''){
		$apt_post_statuses_array = explode($apt_settings['apt_string_separator'], $apt_settings['apt_bulk_tagging_statuses']); //retrieve saved post statuses divided by separators to an array
		$apt_post_statuses_sql = ''; //this declaration is here to prevent throwing the notice "Undefined variable"

		//adding all post statuses to a variable
		foreach($apt_post_statuses_array as $apt_post_status){
		    $apt_post_statuses_sql .= 'post_status != \''. $apt_post_status .'\' AND ';
		}

		//now we need to remove the last " AND " part from the end of the string
		$apt_post_statuses_sql = substr($apt_post_statuses_sql, 0, -5);

		//this is the final part that will be added to the SQL query
		$apt_table_select_posts_with_definded_statuses = "AND ($apt_post_statuses_sql)";
	}

	//get all IDs with set post statuses
	return 'WHERE post_type = \'post\' '. $apt_table_select_posts_with_definded_statuses;
}

function apt_bulk_tagging(){ //adds tags to multiple posts
	$apt_settings = get_option('automatic_post_tagger');

	$apt_ids_for_dosage_bulk_tagging_array = explode($apt_settings['apt_string_separator'], $apt_settings['apt_bulk_tagging_queue']); //make an array from the queue
	$apt_ids_for_dosage_bulk_tagging_array_sliced = array_slice($apt_ids_for_dosage_bulk_tagging_array, 0, $apt_settings['apt_bulk_tagging_posts_per_cycle']); //get first X elements from the array

	echo '<!-- Automatic Post Tagger --><ul class="apt_bulk_tagging_queue">';

	//run loop to process selected number of posts from the range
	foreach($apt_ids_for_dosage_bulk_tagging_array_sliced as $id){
		apt_single_post_tagging($id, 1); //send the current post ID + send '1' to let the script know that we do not want to check user-moron scenarios again
		unset($apt_ids_for_dosage_bulk_tagging_array[array_search($id, $apt_ids_for_dosage_bulk_tagging_array)]); //remove the id from the array
		echo '<li>Post with ID '. $id .' has been processed.</li>';
	}

	echo '</ul><!-- //-Automatic Post Tagger -->';

	//save remaining IDs to the option
	$apt_settings['apt_bulk_tagging_queue'] = implode($apt_settings['apt_string_separator'], $apt_ids_for_dosage_bulk_tagging_array);
	update_option('automatic_post_tagger', $apt_settings); //save settings


	//if there are not any ids in the option, redirect the user to a normal page
	if($apt_settings['apt_bulk_tagging_queue'] == ''){
		//other solutions do not work, explained below
		echo '<!-- Automatic Post Tagger (no post IDs in the queue) -->';
		echo '<p><small><b>Tagging in progress!</b> Click <a href="'. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=0'), 'apt_bulk_tagging_0_nonce') .'">here</a> if the browser won\'t redirect you in a few seconds.</small></p>'; //display an alternative link if methods below fail
		echo '<script>window.location.href=\''. str_replace('&amp;', '&', wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=0'), 'apt_bulk_tagging_0_nonce')) .'\'</script>'; //the str_replace function is here because the wp_nonce_url function provides &amp; instead of &, so I need to replace it or the web browser won't redirect anything
		echo '<noscript><meta http-equiv="refresh" content="0;url='. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=0'), 'apt_bulk_tagging_0_nonce') .'"></noscript>'; //if JS is disabled, use the meta tag
		echo '<!-- //-Automatic Post Tagger -->';
		exit;
	}
	else{//if there are still some ids in the option, redirect to the same page (and continue tagging)
		//other solutions do not work, explained below
		echo '<!-- Automatic Post Tagger (some post IDs in the queue) -->';
		echo '<p><small><b>Tagging in progress!</b> Click <a href="'. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=1'), 'apt_bulk_tagging_1_nonce') .'">here</a> if the browser won\'t redirect you in a few seconds.</small></p>'; //display an alternative link if methods below fail
		echo '<script>window.location.href=\''. str_replace('&amp;', '&', wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=1'), 'apt_bulk_tagging_1_nonce')) .'\'</script>'; //the str_replace function is here because the wp_nonce_url function provides &amp; instead of &, so I need to replace it or the web browser won't redirect anything
		echo '<noscript><meta http-equiv="refresh" content="0;url='. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=1'), 'apt_bulk_tagging_1_nonce') .'"></noscript>'; //if JS is disabled, use the meta tag
		echo '<!-- //-Automatic Post Tagger -->';
		exit;
	}
}

function apt_single_post_tagging($post_id, $apt_dont_check_moron_scenarios = 0){ //this function is for adding tags to only one post
	global $wpdb,
	$apt_table;

	$apt_settings = get_option('automatic_post_tagger');

	$apt_post_current_tags = wp_get_post_terms($post_id, 'post_tag', array("fields" => "names"));
	$apt_post_current_tag_count = count($apt_post_current_tags);

	#################################################################
	### stopping execution to prevent the script from doing unuseful job:

	//we do not have the ID of the post, stop!
	if ($post_id == false OR $post_id == null){
		return 1;
	}
	//the user does not want us to add tags if the post already has assigned some tags, stop!
	if(($apt_post_current_tag_count > 0) AND $apt_settings['apt_handling_current_tags'] == 3){
		return 2;
	}
	//number of current tags is the same or greater than the maximum so we can't append tags, stop! (replacement is ok, 3rd option won't be let here)
	if(($apt_post_current_tag_count >= $apt_settings['apt_tag_limit']) AND $apt_settings['apt_handling_current_tags'] == 1){
		return 3;
	}

	if($apt_dont_check_moron_scenarios == 0){ //if we got a second parameter != 0, don't check user-moron scenarios again - useful for bulk tagging
		### USER-MORON SCENARIOS
		//the user does not want to add any tags, stop!
		if($apt_settings['apt_tag_limit'] <= 0){
			return 4;
		}
		//there are not any tags to add (table is empty), stop!
		if($apt_settings['apt_stats_current_tags'] == 0){
			return 5;
		}
		//the user does not want us to search anything, stop!
		if($apt_settings['apt_title'] == 0 AND $apt_settings['apt_content'] == 0 AND $apt_settings['apt_excerpt'] == 0){
			return 6;
		}
		//the user does not want us to process 0 characters, stop!
		if($apt_settings['apt_substring_analysis'] == 1 AND $apt_settings['apt_substring_analysis_length'] == 0){
			return 7;
		}

/* //TODO v1.6
		//the user wants to search for tags with 0 or negative occurrences, stop!
		if ($apt_settings['apt_miscellaneous_minimum_keyword_occurrence'] <= 0){
			return 8;
		}
*/

	}//-moron checks


	#################################################################

	//if this isn't a revision - not sure if needed, but why not use it?
	if(!wp_is_post_revision($post_id)){

		$apt_word_separators_plus_space = ' '. $apt_settings['apt_word_separators']; //add also a space to the separators
		$apt_word_separators_array = str_split($apt_word_separators_plus_space);

		$apt_haystack_string = '';

		//we need to find out what should where should APT search
		if($apt_settings['apt_title'] == 1){ //include title
			$apt_post_title = $wpdb->get_var("SELECT post_title FROM $wpdb->posts WHERE ID = $post_id LIMIT 0, 1");
			$apt_haystack_string = $apt_haystack_string .' '. $apt_post_title;
		}
		if($apt_settings['apt_content'] == 1){ //include content
			$apt_post_content = $wpdb->get_var("SELECT post_content FROM $wpdb->posts WHERE ID = $post_id LIMIT 0, 1");
			$apt_haystack_string = $apt_haystack_string .' '. $apt_post_content;
		}
		if($apt_settings['apt_excerpt'] == 1){ //include excerpt
			$apt_post_excerpt = $wpdb->get_var("SELECT post_excerpt FROM $wpdb->posts WHERE ID = $post_id LIMIT 0, 1");
			$apt_haystack_string = $apt_haystack_string .' '. $apt_post_excerpt;
		}


		//preparing the string for searching
		if($apt_settings['apt_convert_diacritic'] == 1){
			setlocale(LC_ALL, 'en_GB'); //set locale
			$apt_haystack_string = iconv('UTF-8', 'ASCII//TRANSLIT', $apt_haystack_string); //replace diacritic character with ascii equivalents
		}
		if($apt_settings['apt_ignore_case'] == 1){
			$apt_haystack_string = strtolower($apt_haystack_string); //make it lowercase
		}
		if($apt_settings['apt_strip_tags'] == 1){
			$apt_haystack_string = wp_strip_all_tags($apt_haystack_string); //remove HTML, PHP and JS tags
		}
		if($apt_settings['apt_replace_nonalphanumeric'] == 1){
			$apt_haystack_string = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $apt_haystack_string); //replace all non-alphanumeric-characters with space
		}
		if($apt_settings['apt_replace_whitespaces'] == 1){
			$apt_haystack_string = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $apt_haystack_string); //replace whitespaces and newline characters with a space
		}

		if($apt_settings['apt_substring_analysis'] == 1){ //analyze only a part of the string
			$apt_haystack_string = substr($apt_haystack_string, $apt_settings['apt_substring_analysis_start'], $apt_settings['apt_substring_analysis_length']);
		}

		$apt_haystack_string = ' '. $apt_haystack_string .' '; //we need to add a space before and after the string: the engine is looking for ' string ' (with space at the beginning and the end, so it won't find e.g. ' ice ' in a word ' iceman ')
		$apt_tags_to_add_array = array(); //array of tags that will be added to a post

		$apt_select_tag_related_words_sql = "SELECT tag, related_words FROM $apt_table";
		$apt_select_tag_related_words_results = $wpdb->get_results($apt_select_tag_related_words_sql, ARRAY_N); //get tags and related words from the DB



		//determine if we should calculate the number of max. tags for a post - only when appending tags
		if($apt_settings['apt_handling_current_tags'] == 1){
			$apt_tags_to_add_max = $apt_settings['apt_tag_limit'] - $apt_post_current_tag_count;
		}
		else{
			$apt_tags_to_add_max = $apt_settings['apt_tag_limit'];
		}

//die(stripslashes($apt_haystack_string)); //for debugging

		## SEARCH FOR A SINGLE TAG AND ITS RELATED WORDS
		foreach($apt_select_tag_related_words_results as $apt_table_cell){ //loop handling every row in the table

			## CHECK FOR RELATED WORDS
			$apt_table_row_related_words_count = substr_count($apt_table_cell[1], $apt_settings['apt_string_separator']) + 1; //variable prints number of related words in the current row that is being "browsed" by the while; must be +1 higher than the number of separators!

			//resetting variables - this must be here or the plugin will add non-relevant tags 
			$apt_occurrences_tag = 0;
			$apt_occurrences_related_words = 0;

			if(!empty($apt_table_cell[1])){ //if there are not any related words, do not perform this action so the tag won't be added (adds tag always when no related words are assigned to it)

				$apt_table_cell_substrings = explode($apt_settings['apt_string_separator'], $apt_table_cell[1]); //create an array with related words divided by separators
				for($i=0; $i < $apt_table_row_related_words_count; $i++){ //loop handling substrings in the 'related_words' column - $i must be 0 because array always begin with 0!

					//preparing the substring needle for search --- note: removing tags here does not make any sense!
					$apt_substring_needle = $apt_table_cell_substrings[$i];
					if($apt_settings['apt_convert_diacritic'] == 1){
						setlocale(LC_ALL, 'en_GB'); //set locale
						$apt_substring_needle = iconv('UTF-8', 'ASCII//TRANSLIT', $apt_substring_needle); //replace diacritic character with ascii equivalents
					}
					if($apt_settings['apt_ignore_case'] == 1){
						$apt_substring_needle = strtolower($apt_substring_needle); //make it lowercase
					}
					if($apt_settings['apt_replace_nonalphanumeric'] == 1){
						if($apt_settings['apt_ignore_wildcards'] == 1){ //ignore wildcards so they will work
							$apt_substring_needle = preg_replace('/[^a-zA-Z0-9\s\\'. $apt_settings['apt_wildcard_character'] .']/', ' ', $apt_substring_needle); //replace all non-alphanumeric-characters with spaces
						}
						else{ //wildcards won't work
							$apt_substring_needle = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $apt_substring_needle); //replace all non-alphanumeric-characters with spaces
						}
					}

					## WORD SEPARATORS FOR SUBSTRINGS
					if(!empty($apt_settings['apt_word_separators'])){ //continue only if separators are set

						//wildcard search for related words
						if($apt_settings['apt_wildcards'] == 1){ //run if wildcards are allowed

							if($apt_settings['apt_wildcards_alphanumeric_only'] == 1){ //match only alphanumeric characters
								$apt_substring_needle_wildcards = str_replace($apt_settings['apt_wildcard_character'], '([a-zA-Z0-9]*)', $apt_substring_needle); //replace a wildcard with regexp
							}
							else{ //match any characters
								$apt_substring_needle_wildcards = str_replace($apt_settings['apt_wildcard_character'], '(.*)', $apt_substring_needle); //replace a wildcard with regexp
							}


							$apt_word_separators_separated = '';

							foreach($apt_word_separators_array as $apt_word_separator) {//add | (OR) between the letters, escaping those characters needing escaping
								$apt_word_separators_separated .= preg_quote($apt_word_separator, '/') .'|';
							}

							$apt_word_separators_separated = substr($apt_word_separators_separated, 0, -1); //remove last extra | character


//die($apt_word_separators_separated); //for debugging

							if(preg_match('/('. $apt_word_separators_separated .')'. $apt_substring_needle_wildcards .'('. $apt_word_separators_separated .')/', $apt_haystack_string)){ //strtolowered and asciied 'XsubstringX' has been found
//die("substring '". $apt_substring_needle_wildcards ."' found with separators '". $apt_word_separators_separated .'\''); //for debugging
								$apt_occurrences_related_words = 1; //set variable to 1
							}

						}
						else{ //if wildcards are not allowed, continue searching without using a regular expression
							if(strstr($apt_haystack_string, $apt_substring_needle)){ //strtolowered and asciied 'XsubstringX' has been found
								$apt_occurrences_related_words = 1; //set variable to 1
							}
						}//-else wildcard check

					}//-if separators are set
					## SPACE SEPARATORS FOR SUBSTRINGS
					else{ //if no separators are set, continue searching with spaces before and after every tag
						$apt_substring_needle_spaces = ' '. $apt_substring_needle .' '; //add separators - spaces

						//wildcard search for related words
						if($apt_settings['apt_wildcards'] == 1){ //run if wildcards are allowed
							$apt_substring_needle_wildcards = '/'. str_replace($apt_settings['apt_wildcard_character'], '([a-zA-Z0-9]*)', $apt_substring_needle_spaces) .'/';

							if(preg_match($apt_substring_needle_wildcards, $apt_haystack_string)){ //maybe I should add != FALSE or something similar to make it more clear, but it seems that this works, so I won't change it
								$apt_occurrences_related_words = 1; //set variable to 1
							}
						}
						else{ //if wildcards are not allowed, continue searching without using a regular expression
							if(strstr($apt_haystack_string, $apt_substring_needle_spaces)){ //strtolowered and asciied ' substring ' has been found
								$apt_occurrences_related_words = 1; //set variable to 1
							}
						}//-if wildcard check
					}//-else - no separators
				}//-for
			}//-if for related words check

//die("found: ".$apt_occurrences_related_words ."<br>text: ". stripslashes($apt_haystack_string) . "<br>needle: ". stripslashes($apt_substring_needle) .""); //for debugging

			## CHECK FOR TAGS
			if($apt_occurrences_related_words == 0){ //search for tags only when no substrings were found
//die("no substring was found, now we search for tags"); //for debugging
				//preparing the needle for search --- note: removing tags and whitespace characters here does not make any sense!
				$apt_tag_needle = $apt_table_cell[0];
				if($apt_settings['apt_convert_diacritic'] == 1){
					setlocale(LC_ALL, 'en_GB'); //set locale
					$apt_tag_needle = iconv('UTF-8', 'ASCII//TRANSLIT', $apt_tag_needle); //replace diacritic character with ascii equivalents
				}
				if($apt_settings['apt_ignore_case'] == 1){
					$apt_tag_needle = strtolower($apt_tag_needle); //make it lowercase
				}
				if($apt_settings['apt_replace_nonalphanumeric'] == 1){
					$apt_tag_needle = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $apt_tag_needle); //replace all non-alphanumeric-characters with space
				}

				## WORD SEPARATORS FOR TAGS
				if(!empty($apt_settings['apt_word_separators'])){ //continue only if separators are set
					$apt_word_separators_separated = '';

					foreach($apt_word_separators_array as $apt_word_separator) {//add | (OR) between the letters, escaping those characters needing escaping
						$apt_word_separators_separated .= preg_quote($apt_word_separator, '/') .'|';
					}

					$apt_word_separators_separated = substr($apt_word_separators_separated, 0, -1); //remove last extra | character


//die($apt_word_separators_separated); //for debugging

					if(preg_match('/('. $apt_word_separators_separated .')'. preg_quote($apt_tag_needle) .'('. $apt_word_separators_separated .')/', $apt_haystack_string)){ //strtolowered and asciied 'XtagX' has been found
//die("tag '". $apt_tag_needle ."' found with separators '". $apt_word_separators_separated .'\''); //for debugging
						$apt_occurrences_tag = 1; //set variable to 1
					}


				}//-if separators are set
				## SPACE SEPARATORS FOR TAGS
				else{ //if no separators are set, continue searching with spaces before and after every tag
					$apt_tag_needle_spaces = ' '. $apt_tag_needle .' ';

					//searching for tags (note for future me: we do not want to check for wildcards, they cannot be used in tags (don't implement it AGAIN, you moron)!
					if(strstr($apt_haystack_string, $apt_tag_needle_spaces)){ //strtolowered and asciied ' tag ' has been found
						$apt_occurrences_tag = 1; //set variable to 1
//die("tag found without separators"); //for debugging
					}
				}//-else - no separators
			}//-check for tags if no substrings were found


//die("tag: ". stripslashes($apt_table_cell[0]) ."<br>needle: ". stripslashes($apt_tag_needle)); //for debugging

			## ADDING TAGS TO ARRAY
			if($apt_occurrences_related_words == 1 OR $apt_occurrences_tag == 1){ //tag or one of related_words has been found, add tag to array!
//die("tag: ". stripslashes($apt_table_cell[0]) ."<br>rw found: ".$apt_occurrences_related_words ."<br> tag found: ".  $apt_occurrences_tag); //for debugging

				//we need to check if the tag isn't already in the array of the current tags (don't worry about the temporary array for adding tags, only unique values are pushed in)	
				if($apt_settings['apt_handling_current_tags'] == 2 OR $apt_post_current_tag_count == 0){ //if we need to replace tags, don't check for the current tags or they won't be added again after deleting the old ones --- $apt_post_current_tag_count == 0 will work also for the "do nothing" option
						array_push($apt_tags_to_add_array, $apt_table_cell[0]); //add tag to the array

//die("tag:". stripslashes($apt_table_cell[0]) ."<br>current tags: ". stripslashes(print_r($apt_tags_to_add_array, true))); //for debugging
				}
				else{//appending tags? check for current tags to avoid adding duplicate records to the array
					if(in_array($apt_table_cell[0], $apt_post_current_tags) == FALSE){
						array_push($apt_tags_to_add_array, $apt_table_cell[0]); //add tag to the array
					}
				}


			}//--if for pushing tag to array
//die("tag needle:". stripslashes($apt_tag_needle) ."<br>rw needle: ". stripslashes($apt_substring_needle) ."<br>rw found: ". $apt_occurrences_related_words."<br>tag found: " .$apt_occurrences_tag); //for debugging

			if(count($apt_tags_to_add_array) == $apt_tags_to_add_max){//check if the array is equal to the max. number of tags per one post, break the loop
				break; //stop the loop, the max. number of tags was hit
			}
		}//-foreach

//die("max: ".$apt_settings['apt_tag_limit'] ."<br>current tags: ". $apt_post_current_tag_count . "<br>max for this post: " .$apt_tags_to_add_max. "<br>current tags: ". stripslashes(print_r($apt_tags_to_add_array, true))); //for debugging


		$apt_number_of_found_tags = count($apt_tags_to_add_array);

		## ADDING TAGS TO THE POST
		//if the post has already tags, we should decide what to do with them
		if($apt_settings['apt_handling_current_tags'] == 1 OR $apt_settings['apt_handling_current_tags'] == 3){ //$apt_settings['apt_handling_current_tags'] == 3 -- means that if the post has no tags, we should add them - if it has some, it won't pass a condition above
			wp_set_post_terms($post_id, $apt_tags_to_add_array, 'post_tag', true); //append tags
		}
		if($apt_settings['apt_handling_current_tags'] == 2 AND $apt_number_of_found_tags > 0){ //if the plugin found some tags, replace the old ones,otherwise do not continue!
			wp_set_post_terms($post_id, $apt_tags_to_add_array, 'post_tag', false); //replace tags
		}

//die("current tags: ". stripslashes(print_r($apt_post_current_tags, true)) . "<br>array to add: ". stripslashes(print_r($apt_tags_to_add_array, true))); //for debugging

	}//- revision check
}//-end of tagging function

#################################################################
######################## CREATE TAG FUNCTION ####################

function apt_create_new_tag($apt_tag_name, $apt_tag_related_words){
	global $wpdb,
	$apt_table,
	$apt_message_html_prefix_updated,
	$apt_message_html_prefix_error,
	$apt_message_html_suffix;

	$apt_settings = get_option('automatic_post_tagger');

	if(empty($apt_tag_name)){ //checking if the value of the tag is empty
		echo $apt_message_html_prefix_error .'<b>Error:</b> You can\'t create a tag that doesn\'t have a name.'. $apt_message_html_suffix;
	}
	else{
		//removing slashes and and replacing whitespace characters from beginning and end
		$apt_created_tag_trimmed = trim(stripslashes($apt_tag_name));

		$apt_table_tag_existence_check_sql = $wpdb->prepare("SELECT COUNT(id) FROM $apt_table WHERE tag = %s LIMIT 0,1", $apt_tag_name); //TODO: bug - if I use weird stuff like word separators instead of a tag, i will get a message that the tag has been created (=wasn't wound in the database), even if it isn't true
		$apt_table_tag_existence_check_results = $wpdb->get_var($apt_table_tag_existence_check_sql);


//die("SELECT COUNT(id) FROM $apt_table WHERE tag ='". $apt_tag_name ."' LIMIT 0,1"); //for debugging


		if($apt_table_tag_existence_check_results == 1){ //checking if the tag exists

			echo $apt_message_html_prefix_error .'<b>Error:</b> Tag "<b>'. $apt_created_tag_trimmed .'</b>" couldn\'t be created, because it already exists!'. $apt_message_html_suffix;
		} 
		else{ //if the tag is not in DB, create one

			$apt_created_related_words_trimmed = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $apt_tag_related_words); //replacing multiple whitespace characters with a space (we could replace them completely, but that might annoy users)
			$apt_created_related_words_trimmed = preg_replace('{'. $apt_settings['apt_string_separator'] .'+}', $apt_settings['apt_string_separator'], $apt_created_related_words_trimmed); //replacing multiple separators with one
			$apt_created_related_words_trimmed = preg_replace('/[\\'. $apt_settings['apt_wildcard_character'] .']+/', $apt_settings['apt_wildcard_character'], $apt_created_related_words_trimmed); //replacing multiple wildcards with one
			$apt_created_related_words_trimmed = trim(trim(trim(stripslashes($apt_created_related_words_trimmed)), $apt_settings['apt_string_separator'])); //removing slashes, trimming separators and whitespace characters from the beginning and the end

			$wpdb->query($wpdb->prepare("INSERT IGNORE INTO $apt_table (tag, related_words) VALUES (%s, %s)", $apt_created_tag_trimmed, $apt_created_related_words_trimmed)); //add the tag to the database, ignore duplicities

			$apt_settings['apt_stats_current_tags'] = $wpdb->get_var("SELECT COUNT(id) FROM $apt_table"); //update stats - this must be a "live" select in the database instead of retrieving the value from a cached option
			update_option('automatic_post_tagger', $apt_settings); //save settings


			echo $apt_message_html_prefix_updated .'Tag "<b>'. stripslashes($apt_created_tag_trimmed) .'</b>" with '; //confirm message with a condition displaying related words if available
				if(empty($apt_created_related_words_trimmed)){
					echo 'no related words';
				}else{
					if(strstr($apt_created_related_words_trimmed, $apt_settings['apt_string_separator'])){ //print single or plural form
						echo 'related words "<b>'. stripslashes($apt_created_related_words_trimmed) .'</b>"';
					}
					else{
						echo 'the related word "<b>'. stripslashes($apt_created_related_words_trimmed) .'</b>"';
					}

				}
			echo ' has been created.'. $apt_message_html_suffix;

			if($apt_settings['apt_warning_messages'] == 1){ //display warnings if they are allowed
				//warning messages appearing when "unexpected" character are being saved
				if(preg_match('/[^a-zA-Z0-9\s]/', iconv('UTF-8', 'ASCII//TRANSLIT', $apt_created_tag_trimmed))){ //user-moron scenario
					echo $apt_message_html_prefix_updated .'<b>Warning:</b> The tag name "<b>'. stripslashes($apt_created_tag_trimmed) .'</b>" contains non-alphanumeric characters. <span class="apt_help" title="This is just a reminder that you might want to check your tags/related words for potential typos.">i</span>'. $apt_message_html_suffix; //warning message
				}
				if(preg_match('/[^a-zA-Z0-9\s\\'. $apt_settings['apt_string_separator'] .'\\'. $apt_settings['apt_wildcard_character'] .']/', iconv('UTF-8', 'ASCII//TRANSLIT', $apt_created_related_words_trimmed))){ //user-moron scenario
					echo $apt_message_html_prefix_updated .'<b>Warning:</b> Related words "<b>'. stripslashes($apt_created_related_words_trimmed) .'</b>" contain non-alphanumeric characters. <span class="apt_help" title="This is just a reminder that you might want to check your tags/related words for potential typos.">i</span>'. $apt_message_html_suffix; //warning message
				}
				if(strstr($apt_created_related_words_trimmed, ' '. $apt_settings['apt_string_separator']) OR strstr($apt_created_related_words_trimmed, $apt_settings['apt_string_separator'] .' ')){ //user-moron scenario
					echo $apt_message_html_prefix_updated .'<b>Warning:</b> Related words "<b>'. stripslashes($apt_created_related_words_trimmed) .'</b>" contain extra space near the separator "<b>'. $apt_settings['apt_string_separator'] .'</b>".'. $apt_message_html_suffix; //warning message
				}
				if(strstr($apt_created_related_words_trimmed, $apt_settings['apt_wildcard_character']) AND ($apt_settings['apt_wildcards'] == 0)){ //user-moron scenario
					echo $apt_message_html_prefix_updated .'<b>Warning:</b> Your related words contain a wildcard character, but using wildcards is currently disabled!'. $apt_message_html_suffix; //warning message
				}
			}//-if warnings allowed

		}//--else - existence in the db check
	}//--else - empty check
}

#################################################################
########################## OPTIONS PAGE #########################
#################################################################

function apt_options_page(){ //loads options page
	global $wpdb,
	$apt_table,
	$apt_backup_dir_rel_path,
	$apt_new_backup_file_name_prefix,
	$apt_new_backup_file_name_suffix,
	$apt_new_backup_file_rel_path,
	$apt_new_backup_file_abs_path,
	$apt_message_html_prefix_updated,
	$apt_message_html_prefix_error,
	$apt_message_html_suffix,
	$apt_example_related_words;

	$apt_settings = get_option('automatic_post_tagger');
	$apt_invalid_nonce_message = 'Sorry, your nonce did not verify, your request couldn\'t be executed. Please try again.';

	setlocale(LC_ALL, 'en_GB'); //set locale
?>

<div class="wrap">
<div id="icon-options-general" class="icon32"><br></div>
<h2>Automatic Post Tagger</h2>

<?php
#################################################################
######################## BULK TAGGING REDIRECTION ###############
if(isset($_GET['bt'])){
	$apt_settings = get_option('automatic_post_tagger');

	if($_GET['bt'] == 0 AND check_admin_referer('apt_bulk_tagging_0_nonce')){
		if($apt_settings['apt_bulk_tagging_queue'] == ''){
			echo $apt_message_html_prefix_updated .'Bulk tagging has been finished.'. $apt_message_html_suffix;
		}
		else{
			echo $apt_message_html_prefix_error .'<b>Error:</b> Post IDs are still in the queue - this shouldn\'t have happened. Please <a href="http://wordpress.org/support/plugin/automatic-post-tagger">contact the developer</a> if you encounter any problems.'. $apt_message_html_suffix;
		}
	}
	if($_GET['bt'] == 1 AND check_admin_referer('apt_bulk_tagging_1_nonce')){
			//if there are not any ids in the option, redirect the user to a normal page
			if($apt_settings['apt_bulk_tagging_queue'] == ''){
				echo $apt_message_html_prefix_error .'<b>Error:</b> The bulk tagging queue doesn\'t have any post IDs to process - this shouldn\'t have happened. Please <a href="http://wordpress.org/support/plugin/automatic-post-tagger">contact the developer</a> if you encounter any problems.'. $apt_message_html_suffix;
			}
			else{ //if there are some ids in the option, execute the function
				apt_bulk_tagging();
			}
	}
}//-isset $_GET['bt']

#################################################################
######################## SAVING OPTIONS #########################

if(isset($_POST['apt_save_settings_button'])){//saving all settings
	if(wp_verify_nonce($_POST['apt_save_settings_hash'],'apt_save_settings_nonce')){ //save only if the nonce was verified

		//settings saved to a single array which will be updated at the end of this condition
		$apt_settings['apt_title'] = (isset($_POST['apt_title'])) ? '1' : '0';
		$apt_settings['apt_content'] = (isset($_POST['apt_content'])) ? '1' : '0';
		$apt_settings['apt_excerpt'] = (isset($_POST['apt_excerpt'])) ? '1' : '0';
		$apt_settings['apt_handling_current_tags'] = $_POST['apt_handling_current_tags'];
		$apt_settings['apt_convert_diacritic'] = (isset($_POST['apt_convert_diacritic'])) ? '1' : '0';
		$apt_settings['apt_ignore_case'] = (isset($_POST['apt_ignore_case'])) ? '1' : '0';
		$apt_settings['apt_strip_tags'] = (isset($_POST['apt_strip_tags'])) ? '1' : '0';
		$apt_settings['apt_replace_whitespaces'] = (isset($_POST['apt_replace_whitespaces'])) ? '1' : '0';
		$apt_settings['apt_replace_nonalphanumeric'] = (isset($_POST['apt_replace_nonalphanumeric'])) ? '1' : '0';
		$apt_settings['apt_ignore_wildcards'] = (isset($_POST['apt_ignore_wildcards'])) ? '1' : '0';

//TODO v1.6	$apt_settings['apt_miscellaneous_add_most_frequent_tags_first'] = (isset($_POST['apt_miscellaneous_add_most_frequent_tags_first'])) ? '1' : '0';

		$apt_settings['apt_substring_analysis'] = (isset($_POST['apt_substring_analysis'])) ? '1' : '0';
		$apt_settings['apt_wildcards'] = (isset($_POST['apt_wildcards'])) ? '1' : '0';
		$apt_settings['apt_wildcards_alphanumeric_only'] = (isset($_POST['apt_wildcards_alphanumeric_only'])) ? '1' : '0';
		$apt_settings['apt_word_separators'] = stripslashes(html_entity_decode($_POST['apt_word_separators'], ENT_QUOTES));
		$apt_settings['apt_tagging_hook_type'] = $_POST['apt_tagging_hook_type'];
		$apt_settings['apt_warning_messages'] = (isset($_POST['apt_warning_messages'])) ? '1' : '0';


		//making sure that people won't save rubbish in the DB
		if(is_int((int)$_POST['apt_substring_analysis_length'])){ //value must be integer
			$apt_settings['apt_substring_analysis_length'] = $_POST['apt_substring_analysis_length'];
		}
		else{
			echo $apt_message_html_prefix_error .'<b>Error:</b> The option "apt_substring_analysis_length" couldn\'t be saved because the sent value wasn\'t integer.'. $apt_message_html_suffix; //user-moron scenario
		}
		if(is_int((int)$_POST['apt_substring_analysis_start'])){ //value must be integer
			$apt_settings['apt_substring_analysis_start'] = $_POST['apt_substring_analysis_start'];
		}
		else{
			echo $apt_message_html_prefix_error .'<b>Error:</b> The option "apt_substring_analysis_start" couldn\'t be saved because the sent value wasn\'t integer.'. $apt_message_html_suffix; //user-moron scenario
		}
		if(ctype_digit($_POST['apt_tag_limit'])){ //value must be natural
			$apt_settings['apt_tag_limit'] = $_POST['apt_tag_limit'];
		}
		else{
			echo $apt_message_html_prefix_error .'<b>Error:</b> The option "apt_tag_limit" couldn\'t be saved because the sent value wasn\'t natural.'. $apt_message_html_suffix; //user-moron scenario
		}
		if(ctype_digit($_POST['apt_stored_backups'])){ //value must be natural
			$apt_settings['apt_stored_backups'] = $_POST['apt_stored_backups'];
		}
		else{
			echo $apt_message_html_prefix_error .'<b>Error:</b> The option "apt_stored_backups" couldn\'t be saved because the sent value wasn\'t natural.'. $apt_message_html_suffix; //user-moron scenario
		}


		//the string separator must not be empty
		if(!empty($_POST['apt_string_separator'])){
			if($apt_settings['apt_warning_messages'] == 1){ //display warnings if they are allowed
				//the string separator is not a comma
				if($_POST['apt_string_separator'] != ','){ //don't display when non-comma character was submitted
					echo $apt_message_html_prefix_updated .'<b>Warning:</b> The option "apt_string_separator" has been set to "<b>'. $_POST['apt_string_separator'] .'</b>". Using a comma instead is recommended.'. $apt_message_html_suffix; //user-moron scenario
				}
				//the string separator should not contain more characters
				if(strlen($_POST['apt_string_separator']) > 1){
					echo $apt_message_html_prefix_updated .'<b>Warning:</b> The option "apt_string_separator" contains '. strlen($_POST['apt_string_separator']) .' characters.'. $apt_message_html_suffix; //user-moron scenario
				}
			}//-if warnings allowed

			//if the string separator has been changed, inform the user about changing the separator in all related words
			if($_POST['apt_string_separator'] != $apt_settings['apt_string_separator']){
				//replacing old separators in cells with related words with the new value
				$wpdb->query($wpdb->prepare("UPDATE $apt_table SET related_words = REPLACE(related_words, %s, %s)", $apt_settings['apt_string_separator'], $_POST['apt_string_separator']));

				//replacing old separators in post statuses
				$apt_settings['apt_bulk_tagging_statuses'] = str_replace($apt_settings['apt_string_separator'], $_POST['apt_string_separator'], $apt_settings['apt_bulk_tagging_statuses']); //searching for the current separator, replacing with newly submitted value;

				//replacing old separators in hidden widgets
				$apt_settings['apt_hidden_widgets'] = str_replace($apt_settings['apt_string_separator'], $_POST['apt_string_separator'], $apt_settings['apt_hidden_widgets']); //searching for the current separator, replacing with newly submitted value


				echo $apt_message_html_prefix_updated .'<b>Note:</b> All old string separators ("<b>'. $apt_settings['apt_string_separator'] .'</b>") have been changed to new values ("<b>'. $_POST['apt_string_separator'] .'</b>").'. $apt_message_html_suffix; //info message
			}

			$apt_settings['apt_string_separator'] = $_POST['apt_string_separator']; //this line MUST be under the check for current/old separator!!

		}//-if not empty
		else{
			echo $apt_message_html_prefix_error .'<b>Error:</b> The option "apt_string_separator" couldn\'t be saved because the sent value was empty.'. $apt_message_html_suffix; //user-moron scenario
		}


		//the wildcard must not be empty
		if(!empty($_POST['apt_wildcard_character'])){

			//the wildcard must not contain the string separator
			if(strstr($_POST['apt_wildcard_character'], $apt_settings['apt_string_separator'])){
				echo $apt_message_html_prefix_error .'<b>Error:</b> The option "apt_wildcard_character" couldn\'t be saved because the sent value is used as the string separator. Use something else, please.'. $apt_message_html_suffix; //user-moron scenario
			}
			else{ //the string doesn't contain the string separator

				if($apt_settings['apt_warning_messages'] == 1){ //display warnings if they are allowed
					//the wildcard is not an asterisk
					if($_POST['apt_wildcard_character'] != '*'){ //don't display when non-asterisk character was submitted
						echo $apt_message_html_prefix_updated .'<b>Warning:</b> The option "apt_wildcard_character" has been set to "<b>'. $_POST['apt_wildcard_character'] .'</b>". Using an asterisk instead is recommended.'. $apt_message_html_suffix; //user-moron scenario
					}
					//the wildcard should not contain more characters
					if(strlen($_POST['apt_wildcard_character']) > 1){
						echo $apt_message_html_prefix_updated .'<b>Warning:</b> The option "apt_wildcard_character" contains '. strlen($_POST['apt_wildcard_character']) .' characters.'. $apt_message_html_suffix; //user-moron scenario
					}
				}//-if warnings allowed

				//if the wildcard has been changed, inform the user about changing wildcards in all related words, if tags exist
				if($_POST['apt_wildcard_character'] != $apt_settings['apt_wildcard_character'] AND $apt_settings['apt_stats_current_tags'] > 0){

					//replacing old wildcards in cells with related words with the new value
					$wpdb->query($wpdb->prepare("UPDATE $apt_table SET related_words = REPLACE(related_words, %s, %s)", $apt_settings['apt_wildcard_character'], $_POST['apt_wildcard_character']));

					echo $apt_message_html_prefix_updated .'<b>Note:</b> All old wildcard characters used in related words ("<b>'. $apt_settings['apt_wildcard_character'] .'</b>") have been changed to new values ("<b>'. $_POST['apt_wildcard_character'] .'</b>").'. $apt_message_html_suffix; //info message
				}//wildcard has been changed

				$apt_settings['apt_wildcard_character'] = $_POST['apt_wildcard_character']; //this line MUST be under the check for current/old wildcard!!
			} //-else doesn't contain the string separator

		}//-if not empty
		else{
			echo $apt_message_html_prefix_error .'<b>Error:</b> The option "apt_wildcard_character" couldn\'t be saved because the sent value was empty.'. $apt_message_html_suffix; //user-moron scenario
		}


/* //TODO v1.6
		if(ctype_digit($_POST['apt_miscellaneous_minimum_keyword_occurrence'])){
			if($_POST['apt_miscellaneous_minimum_keyword_occurrence'] >= 1){
				$apt_settings['apt_miscellaneous_minimum_keyword_occurrence'] = $_POST['apt_miscellaneous_minimum_keyword_occurrence'];
			}
			else{
				echo $apt_message_html_prefix_error .'<b>Error:</b> The option "apt_miscellaneous_minimum_keyword_occurrence" must not be negative or zero.'. $apt_message_html_suffix; //user-moron scenario
			}

		}
		else{
			echo $apt_message_html_prefix_error .'<b>Error:</b> The option "apt_miscellaneous_minimum_keyword_occurrence" couldn\'t be saved because the sent value wasn\'t numeric.'. $apt_message_html_suffix; //user-moron scenario
		}
*/

		update_option('automatic_post_tagger', $apt_settings); //save settings


		//print message informing the user about better performance if they delete word separators
		if(isset($_POST['apt_replace_nonalphanumeric']) AND $apt_settings['apt_word_separators'] != ''){ //display this note only if there are not any separators
			echo $apt_message_html_prefix_updated .'<b>Note:</b> Replacing non-alphanumeric characters with spaces has been activated. Deleting all word separators is recommended for better performance.'. $apt_message_html_suffix; //user-moron scenario
		}
		//print message informing the user about non functioning wildcards
		if(isset($_POST['apt_replace_nonalphanumeric']) AND $apt_settings['apt_ignore_wildcards'] == 0){  //display this note only if wildcards are not being ignored
			echo $apt_message_html_prefix_updated .'<b>Note:</b> Non-alphanumeric characters (including wildcards) will be replaced with spaces. Wildcards won\'t work unless you allow the option "Don\'t replace wildcards".'. $apt_message_html_suffix; //user-moron scenario
		}

		echo $apt_message_html_prefix_updated .'Your settings have been saved.'. $apt_message_html_suffix; //confirm message
	}//-nonce check
	else{//the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

if(isset($_POST['apt_restore_default_settings_button'])){ //resetting settings
	if(wp_verify_nonce($_POST['apt_restore_default_settings_hash'],'apt_restore_default_settings_nonce')){ //save only if the nonce was verified
		apt_uninstall_plugin();
		apt_install_plugin();

		$apt_settings = get_option('automatic_post_tagger'); //we need to load newly generated settings again, the array saved in the global variable is old
		$apt_settings['apt_admin_notice_install'] = 0; //hide the activation notice after reinstalling
		update_option('automatic_post_tagger', $apt_settings); //save settings

		echo $apt_message_html_prefix_updated .'Default settings have been restored.'. $apt_message_html_suffix; //confirm message
	}//-nonce check
	else{//the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

#################################################################
#################### tag management #############################

if(isset($_POST['apt_create_new_tag_button'])){ //creating a new tag wuth relaterd words
	if(wp_verify_nonce($_POST['apt_create_new_tag_hash'],'apt_create_new_tag_nonce')){ //save only if the nonce was verified
		apt_create_new_tag($_POST['apt_create_tag_name'],$_POST['apt_create_tag_related_words']);
		$apt_settings = get_option('automatic_post_tagger'); //we need to refresh the options with stats when a new tag has been added
	}//-nonce check
	else{//the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

if(isset($_POST['apt_delete_all_tags_button'])){ //delete all records from $apt_table
	if(wp_verify_nonce($_POST['apt_delete_all_tags_hash'],'apt_delete_all_tags_nonce')){ //save only if the nonce was verified

		$wpdb->query('TRUNCATE TABLE '. $apt_table);
		$apt_settings['apt_stats_current_tags'] = 0; //reset stats
		update_option('automatic_post_tagger', $apt_settings); //save settings

		echo $apt_message_html_prefix_updated .'All tags have been deleted.'. $apt_message_html_suffix;
	}//-nonce check
	else{//the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

if(isset($_POST['apt_delete_chosen_tags_button'])){ //delete chosen records from $apt_table
	if(wp_verify_nonce($_POST['apt_delete_chosen_tags_hash'],'apt_delete_chosen_tags_nonce')){ //save only if the nonce was verified
		if(isset($_POST['apt_taglist_checkbox_'])){ //determine if any checkbox was checked
			foreach($_POST['apt_taglist_checkbox_'] as $id => $value){ //loop for handling checkboxes
				$wpdb->query($wpdb->prepare("DELETE FROM $apt_table WHERE id = %d", $id));
			}

			$apt_settings['apt_stats_current_tags'] = $wpdb->get_var("SELECT COUNT(id) FROM $apt_table"); //update stats - this should be a "live" select in the database instead of deducting from the cached value
			update_option('automatic_post_tagger', $apt_settings); //save settings

			echo $apt_message_html_prefix_updated .'All chosen tags have been deleted.'. $apt_message_html_suffix;
		}
		else{
			echo $apt_message_html_prefix_error .'<b>Error:</b> You must choose at least one tag in order to delete it.'. $apt_message_html_suffix;
		}
	}//-nonce check
	else{//the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

if(isset($_POST['apt_save_tags_button'])){ //saving changed tags
	if(wp_verify_nonce($_POST['apt_save_tags_hash'],'apt_save_tags_nonce')){ //save only if the nonce was verified

		foreach($_POST['apt_taglist_tag_'] as $id => $value){ //saving tag
			$apt_saved_tag = trim(stripslashes($_POST['apt_taglist_tag_'][$id])); //trimming slashes and whitespace characters

			if(empty($apt_saved_tag)){ //user-moron scenario - the sent walue WAS empty
				$apt_saved_tag_empty_error = 1;
			}
			else{ //save if not empty
				$wpdb->query($wpdb->prepare("UPDATE $apt_table SET tag = %s WHERE id = %d", $apt_saved_tag, $id));

				//generate warnings
				if($apt_settings['apt_warning_messages'] == 1){ //check if warnings should be displayed
					if(preg_match('/[^a-zA-Z0-9\s]/', iconv('UTF-8', 'ASCII//TRANSLIT', $apt_saved_tag))){ //user-moron scenario
						$apt_saved_tag_alphanumeric_warning = 1;
					}
				}//-if warnings allowed
			}//-else if not empty
		}//-foreach

		foreach($_POST['apt_taglist_related_words_'] as $id => $value){ //saving related words
			$apt_saved_related_words = $_POST['apt_taglist_related_words_'][$id]; //this must not be deleted or the variable in the query below will not submit empty values but nonsense instead!

			if(!empty($_POST['apt_taglist_related_words_'][$id])){ //the sent value was NOT empty
				$apt_saved_related_words = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', preg_replace('{'. $apt_settings['apt_string_separator'] .'+}', $apt_settings['apt_string_separator'], preg_replace('/[\\'. $apt_settings['apt_wildcard_character'] .']+/', $apt_settings['apt_wildcard_character'], trim(trim(trim(stripslashes($_POST['apt_taglist_related_words_'][$id])), $apt_settings['apt_string_separator']))) )); //trimming whitespace characters, wildcards and separators

				//generate warnings
				if($apt_settings['apt_warning_messages'] == 1){ //display warnings if they are allowed
					if(preg_match('/[^a-zA-Z0-9\s\\'. $apt_settings['apt_string_separator'] .'\\'. $apt_settings['apt_wildcard_character'] .']/', iconv('UTF-8', 'ASCII//TRANSLIT', $apt_saved_related_words))){ //user-moron scenario
						$apt_saved_related_words_alphanumeric_warning = 1;
					}
					if(strstr($apt_saved_related_words, ' '. $apt_settings['apt_string_separator']) OR strstr($apt_saved_related_words, $apt_settings['apt_string_separator']. ' ')){ //user-moron scenario
						$apt_saved_related_words_extra_spaces_warning = 1;
					}
					if(strstr($apt_saved_related_words, $apt_settings['apt_wildcard_character']) AND ($apt_settings['apt_wildcards'] == 0)){ //user-moron scenario
						$apt_saved_related_words_wildcard_warning = 1;
					}
				}//-if warnings allowed

			}//-if !empty check

			$wpdb->query($wpdb->prepare("UPDATE $apt_table SET related_words = %s WHERE id = %d", $apt_saved_related_words, $id));
		}//-foreach

		echo $apt_message_html_prefix_updated .'All tags have been saved.'. $apt_message_html_suffix;

		//warning messages appearing when "unexpected" character are being saved - user-moron scenarios
		if(isset($apt_saved_tag_empty_error) AND $apt_saved_tag_empty_error == 1){
			echo $apt_message_html_prefix_error .'<b>Error:</b> Some tag names were sent as empty strings, their previous values were restored.'. $apt_message_html_suffix; //warning message
		}

		if($apt_settings['apt_warning_messages'] == 1){ //display warnings if they are allowed
			if(isset($apt_saved_tag_alphanumeric_warning) AND $apt_saved_tag_alphanumeric_warning == 1){
				echo $apt_message_html_prefix_updated .'<b>Warning:</b> Some tag names contain non-alphanumeric characters. <span class="apt_help" title="This is just a reminder that you might want to check your tags/related words for potential typos.">i</span>'. $apt_message_html_suffix; //warning message
			}
			if(isset($apt_saved_related_words_alphanumeric_warning) AND $apt_saved_related_words_alphanumeric_warning == 1){
				echo $apt_message_html_prefix_updated .'<b>Warning:</b> Some related words contain non-alphanumeric characters. <span class="apt_help" title="This is just a reminder that you might want to check your tags/related words for potential typos.">i</span>'. $apt_message_html_suffix; //warning message
			}
			if(isset($apt_saved_related_words_extra_spaces_warning) AND $apt_saved_related_words_extra_spaces_warning == 1){
				echo $apt_message_html_prefix_updated .'<b>Warning:</b> Some related words contain extra spaces near separators.'. $apt_message_html_suffix; //warning message
			}
			if(isset($apt_saved_related_words_wildcard_warning) AND $apt_saved_related_words_wildcard_warning == 1){
				echo $apt_message_html_prefix_updated .'<b>Warning:</b> Your related words contain a wildcard character, but using wildcards is currently disabled!'. $apt_message_html_suffix; //warning message
			}
		}//-if warnings allowed

	}//-nonce check
	else{//the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

#################################################################
#################### import/export ##############################

if(isset($_POST['apt_import_existing_tags_button'])){ //import current tags
	if(wp_verify_nonce($_POST['apt_import_existing_tags_hash'],'apt_import_existing_tags_nonce')){ //save only if the nonce was verified

		$apt_table_select_current_tags_sql = 'SELECT name FROM '. $wpdb->terms .' NATURAL JOIN '. $wpdb->term_taxonomy .' WHERE taxonomy="post_tag"'; //select all existing tags
		$apt_table_select_current_tags_results = $wpdb->get_results($apt_table_select_current_tags_sql, ARRAY_N); //ARRAY_N - result will be output as a numerically indexed array of numerically indexed arrays. 
		$apt_currently_imported_tags = 0; //this will be used to determine how many tags were imported


		foreach($apt_table_select_current_tags_results as $apt_tag_name){ //run loop to process all rows
			$wpdb->query($wpdb->prepare("INSERT IGNORE INTO $apt_table(tag,related_words) VALUES(%s,'')", $apt_tag_name[0])); //we are not inserting any related words because there aren't any associated with them - we are importing already existing tags
			$apt_currently_imported_tags++;

			if($apt_settings['apt_warning_messages'] == 1){ //display warnings if they are allowed
				if(preg_match('/[^a-zA-Z0-9\s]/', iconv('UTF-8', 'ASCII//TRANSLIT', $apt_tag_name[0]))){ //user-moron scenario
					$apt_imported_current_tag_alphanumeric_warning = 1;
				}
			}//-if warnings allowed
		}//-while

		if($apt_currently_imported_tags != 0){ //we have imported something!
			$apt_settings['apt_stats_current_tags'] = $wpdb->get_var("SELECT COUNT(id) FROM $apt_table"); //update stats - this should be a "live" select in the database instead of counting up the value
			update_option('automatic_post_tagger', $apt_settings); //save settings

			echo $apt_message_html_prefix_updated .'All <b>'. $apt_currently_imported_tags .'</b> tags have been imported.'. $apt_message_html_suffix; //confirm message

			//user-moron warnings
			if($apt_settings['apt_warning_messages'] == 1){ //display warnings if they are allowed
				if(isset($apt_imported_current_tag_alphanumeric_warning) AND $apt_imported_current_tag_alphanumeric_warning == 1){
					echo $apt_message_html_prefix_updated .'<b>Warning:</b> Some tag names contain non-alphanumeric characters. <span class="apt_help" title="This is just a reminder that you might want to check your tags/related words for potential typos.">i</span>'. $apt_message_html_suffix; //warning message
				}
			}//-if warnings allowed
		}
		else{
			echo $apt_message_html_prefix_error .'<b>Error:</b> There aren\'t any tags in your database.'. $apt_message_html_suffix; //confirm message
		}

	}//-nonce check
	else{//the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

if(isset($_POST['apt_import_from_backup_button'])){ //import a backup file
	if(wp_verify_nonce($_POST['apt_import_from_backup_hash'],'apt_import_from_backup_nonce')){ //save only if the nonce was verified

		if(strstr($_FILES['apt_uploaded_file']['name'], $apt_new_backup_file_name_prefix)){ //checks if the name of uploaded file contains the prefix 'apt_backup'
			if(move_uploaded_file($_FILES['apt_uploaded_file']['tmp_name'], $apt_new_backup_file_rel_path)){ //file can be uploaded (moved to the plugin directory)


				$apt_backup_file_import_handle = fopen($apt_new_backup_file_rel_path, 'r');

				while(($apt_csv_row = fgetcsv($apt_backup_file_import_handle, 600, ',')) !== FALSE){ //lines can be long only 1000 characters (actual lines should be obviously shorter - tags and related words have limited length in the DB)
					if(!empty($apt_csv_row[0])){ //user-moron scenario check - don't save if the tag name is empty

						$wpdb->query($wpdb->prepare("INSERT IGNORE INTO $apt_table(tag,related_words) VALUES(%s,%s)", $apt_csv_row[0], $apt_csv_row[1])); //insert the tag in the DB

						if($apt_settings['apt_warning_messages'] == 1){ //check if warnings should be displayed
							//user-moron scenarios
							if(preg_match('/[^a-zA-Z0-9\s]/', iconv('UTF-8', 'ASCII//TRANSLIT', $apt_csv_row[0]))){ //display error if the tag has non-alphanumeric characters
								$apt_imported_tag_alphanumeric_warning = 1;
							}
							if(preg_match('/[^a-zA-Z0-9\s\\'. $apt_settings['apt_string_separator'] .'\\'. $apt_settings['apt_wildcard_character'] .']/', iconv('UTF-8', 'ASCII//TRANSLIT', $apt_csv_row[1]))){
								$apt_imported_related_words_alphanumeric_warning = 1;
							}
							if(strstr($apt_csv_row[1], ' '. $apt_settings['apt_string_separator']) OR strstr($apt_csv_row[1], $apt_settings['apt_string_separator'] .' ')){
								$apt_imported_related_words_extra_spaces_warning = 1;
							}
							if(strstr($apt_csv_row[1], $apt_settings['apt_wildcard_character']) AND ($apt_settings['apt_wildcards'] == 0)){
								$apt_imported_related_words_wildcard_warning = 1;
							}
						}//-if warnings allowed
					}//-if empty check
					else{
						$apt_imported_tag_empty_error = 1;
					}

				}//-while

				fclose($apt_backup_file_import_handle); //close the file
				unlink($apt_new_backup_file_rel_path); //remove the file from the directory


				$apt_settings['apt_stats_current_tags'] = $wpdb->get_var("SELECT COUNT(id) FROM $apt_table"); //update stats - this must be a "live" select in the database instead of retrieving the value from a cached option
				update_option('automatic_post_tagger', $apt_settings); //save settings

				echo $apt_message_html_prefix_updated .'All tags from your backup have been imported.'. $apt_message_html_suffix;

				//user-moron warnings/errors
				if(isset($apt_imported_tag_empty_error) AND $apt_imported_tag_empty_error == 1){
					echo $apt_message_html_prefix_error .'<b>Error:</b> Some tags weren\'t imported because their names were missing.'. $apt_message_html_suffix; //error message
				}

				if($apt_settings['apt_warning_messages'] == 1){ //display warnings if they are allowed
					if(isset($apt_imported_tag_alphanumeric_warning) AND $apt_imported_tag_alphanumeric_warning == 1){
						echo $apt_message_html_prefix_updated .'<b>Warning:</b> Some tag names contain non-alphanumeric characters. <span class="apt_help" title="This is just a reminder that you might want to check your tags/related words for potential typos.">i</span>'. $apt_message_html_suffix; //warning message
					}
					if(isset($apt_imported_related_words_wildcard_warning) AND $apt_imported_related_words_wildcard_warning == 1){
						echo $apt_message_html_prefix_updated .'<b>Warning:</b> Your related words contain a wildcard character, but using wildcards is currently disabled!'. $apt_message_html_suffix; //warning message
					}
					if(isset($apt_imported_related_words_alphanumeric_warning) AND $apt_imported_related_words_alphanumeric_warning == 1){
						echo $apt_message_html_prefix_updated .'<b>Warning:</b> Some related words contain non-alphanumeric characters. <span class="apt_help" title="This is just a reminder that you might want to check your tags/related words for potential typos.">i</span>'. $apt_message_html_suffix; //warning message
					}
					if(isset($apt_imported_related_words_extra_spaces_warning) AND $apt_imported_related_words_extra_spaces_warning == 1){
						echo $apt_message_html_prefix_updated .'<b>Warning:</b> Some related words contain extra spaces near related words.'. $apt_message_html_suffix; //warning message
					}
				}//-if warnings allowed
			}
			else{ //cannot upload file
				echo $apt_message_html_prefix_error .'<b>Error:</b> The file couldn\'t be uploaded.'. $apt_message_html_suffix; //error message
			}
		}
		else{ //the file name is invalid
			echo $apt_message_html_prefix_error .'<b>Error:</b> The prefix of the imported file name must be "<b>'. $apt_new_backup_file_name_prefix .'</b>".'. $apt_message_html_suffix; //error message
		}
	}//-nonce check
	else{//the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

if(isset($_POST['apt_export_tags_button'])){ //creating backup
	if(wp_verify_nonce($_POST['apt_export_tags_hash'],'apt_export_tags_nonce')){ //save only if the nonce was verified

		//export only when there are tags in the database
		if($apt_settings['apt_stats_current_tags'] != 0){

			//there is no need to trim tags and related words because function for saving/creating tags won't allow saving "messy" values
			$apt_select_table_sql = 'SELECT tag, related_words FROM '. $apt_table .' ORDER BY tag'; //sort tags alphabetically
			$apt_select_table_results = $wpdb->get_results($apt_select_table_sql, ARRAY_A);


			$apt_backup_file_fopen = fopen($apt_new_backup_file_rel_path, 'w');

			foreach($apt_select_table_results as $row){
					fputcsv($apt_backup_file_fopen, $row);
			}

			fclose($apt_backup_file_fopen);

			## DELETION of BACKUPS - if the number of generated backups is higher than a specified amount, delete the oldes one(s)
			chdir($apt_backup_dir_rel_path); //change directory to the backup directory
			$apt_existing_backup_files = glob($apt_new_backup_file_name_prefix .'*'. $apt_new_backup_file_name_suffix); //find files with a specified prefix and suffix

			if(count($apt_existing_backup_files) > $apt_settings['apt_stored_backups']){ //continue if there are more backups than the specified amiunt
				//sort the array of files drom the oldest one
				array_multisort(array_map('filemtime', $apt_existing_backup_files), SORT_NUMERIC, SORT_ASC, $apt_existing_backup_files);

				$apt_extra_old_files = count($apt_existing_backup_files) - $apt_settings['apt_stored_backups'];

				//this cycle will remove all extra old files
				for($i = 0; $apt_extra_old_files != 0; $i++){
					//delete the item which should be the oldest one
					unlink($apt_backup_dir_rel_path . $apt_existing_backup_files[$i]);

					//decrease the number of extra old files by 1
					$apt_extra_old_files--;
				}//-for
			}//-if more than X backups


			if(file_exists($apt_new_backup_file_rel_path)){
				echo $apt_message_html_prefix_updated .'Your <a href="'. $apt_new_backup_file_abs_path .'">backup</a> has been created.'. $apt_message_html_suffix;
			}
			else{
				echo $apt_message_html_prefix_error .'<b>Error:</b> Your backup couldn\'t be created because of insufficient permissions preventing the plugin from creating a file.'. $apt_message_html_suffix; //error message
			}
		}
		else{ //no tags in the database
			echo $apt_message_html_prefix_error .'<b>Error:</b> Your backup couldn\'t be created, because there aren\'t any tags in the database.'. $apt_message_html_suffix; //user-moron scenario
		}

	}//-nonce check
	else{//the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}
#################################################################
#################### bulk tagging ###############################

if(isset($_POST['apt_bulk_tagging_button'])){
	if(wp_verify_nonce($_POST['apt_bulk_tagging_hash'],'apt_bulk_tagging_nonce')){ //save only if the nonce was verified
		$apt_settings['apt_bulk_tagging_statuses'] = trim($_POST['apt_bulk_tagging_statuses'], $apt_settings['apt_string_separator']); //get rid of separators that are not between words
		update_option('automatic_post_tagger', $apt_settings); //save settings

		#################################################################
		### stopping execution to prevent the script from doing unuseful job:

		//I wanted to add there conditions for checking if an error occured to stop other conditions from executing but it is a bad idea
		//because then if a user makes multiple mistakes he won't be notified about them
	
		if(ctype_digit($_POST['apt_bulk_tagging_posts_per_cycle'])){ //value must be natural
			$apt_settings['apt_bulk_tagging_posts_per_cycle'] = $_POST['apt_bulk_tagging_posts_per_cycle'];
			update_option('automatic_post_tagger', $apt_settings); //save settings
		}
		else{
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<b>Error:</b> The option "apt_bulk_tagging_posts_per_cycle" couldn\'t be saved because the sent value wasn\'t natural.'. $apt_message_html_suffix; //user-moron scenario
		}
		if(!ctype_digit($_POST['apt_bulk_tagging_range_1']) OR !ctype_digit($_POST['apt_bulk_tagging_range_2'])){ //value must be natural
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<b>Error:</b> The option "apt_bulk_tagging_queue" couldn\'t be saved because the sent values weren\'t natural.'. $apt_message_html_suffix; //user-moron scenario
		}
		if($_POST['apt_bulk_tagging_range_1'] > $_POST['apt_bulk_tagging_range_2']){
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<b>Error:</b> The option "apt_bulk_tagging_range_1" can\'t be higher than "apt_bulk_tagging_range_2".'. $apt_message_html_suffix; //user-moron scenario
		}

		### USER-MORON SCENARIOS
		//there are not any tags to add (table is empty), stop!
		if($apt_settings['apt_stats_current_tags'] == 0){
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<b>Error:</b> There aren\'t any tags that can be added to posts.'. $apt_message_html_suffix;
		}
		//there are not any posts to tag, stop! (this doesn't have to be in the apt_single_post_tagging function)
		if($wpdb->get_var('SELECT COUNT(ID) FROM '. $wpdb->posts) == 0){
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<b>Error:</b> There aren\'t any posts that can be processed.'. $apt_message_html_suffix;
		}
		//the user does not want to add any tags, stop!
		if($apt_settings['apt_tag_limit'] <= 0){
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<b>Error:</b> The maximum number of tags can\'t be equal or lower than zero.'. $apt_message_html_suffix;
		}
		//the user does not want us to search anything, stop!
		if($apt_settings['apt_title'] == 0 AND $apt_settings['apt_content'] == 0 AND $apt_settings['apt_excerpt'] == 0){
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<b>Error:</b> The script isn\'t allowed to analyze any content.'. $apt_message_html_suffix;
		}
		//the user does not want us to process 0 characters, stop!
		if($apt_settings['apt_substring_analysis'] == 1 AND $apt_settings['apt_substring_analysis_length'] == 0){
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<b>Error:</b> The script isn\'t allowed to analyze any content.'. $apt_message_html_suffix;

		}
		#################################################################

		//we need to check if any errors occured - if the variable is not set, continue
		if(!isset($apt_bulk_tagging_error)){

			$apt_ids_for_bulk_tagging_array = array();
			$apt_print_ids_without_specified_statuses_sql = "SELECT ID FROM $wpdb->posts ". apt_print_sql_where_without_specified_statuses() ." ORDER BY ID ASC";
			$apt_print_ids_without_specified_statuses_results = $wpdb->get_results($apt_print_ids_without_specified_statuses_sql, ARRAY_A);

//print_r($apt_print_ids_without_specified_statuses_results); //for debugging

			foreach($apt_print_ids_without_specified_statuses_results as $row){ //for some reason if we don't use the variable we probably get an infinite loop resulting in a max_execution_time error

				//determine if the ID is within the range specified by the user, if yes, add it to the array
				if($row['ID'] >= $_POST['apt_bulk_tagging_range_1'] AND $row['ID'] <= $_POST['apt_bulk_tagging_range_2']){
					$apt_ids_for_bulk_tagging_array[] = $row['ID'];
				}
			}//-foreach

//die(print_r($apt_ids_for_bulk_tagging_array)); //for debugging

			//if no post IDs are added to the array, throw an exception and don't continue
			if(count($apt_ids_for_bulk_tagging_array) == 0){
				echo $apt_message_html_prefix_error .'<b>Error:</b> There isn\'t any post ID within the specified range.'. $apt_message_html_suffix;
			}
			else{//IDs are in the array, continue!
				$apt_settings['apt_bulk_tagging_queue'] = implode($apt_settings['apt_string_separator'], $apt_ids_for_bulk_tagging_array); //saving retrieved ids to the option
				update_option('automatic_post_tagger', $apt_settings); //save settings

				if($apt_settings['apt_bulk_tagging_queue'] != ''){ //if the option isn't empty, redirect the page to another page with a nonce

					//since the admin_head/admin_print_scripts hook doesn't work inside the options page function and we cannot use header() or wp_redirect() here
					//(because some webhosts will throw the "headers already sent" error), so we need to use a javascript redirect or a meta tag printed to a bad place
					//OR we could constantly check the database for a saved value and use admin_menu somewhere else (I am not sure if it is a good idea)

					echo '<!-- Automatic Post Tagger (no &bt in the URL, no tagging happened yet, some post IDs in the queue) -->';
					echo '<p><small><b>Tagging in progress!</b> Click <a href="'. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=1'), 'apt_bulk_tagging_1_nonce') .'">here</a> if the browser won\'t redirect you in a few seconds.</small></p>'; //display an alternative link if methods below fail
					echo '<script>window.location.href=\''. str_replace('&amp;', '&', wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=1'), 'apt_bulk_tagging_1_nonce')) .'\'</script>'; //the str_replace function is here because the wp_nonce_url function provides &amp; instead of &, so I need to replace it or the web browser won't redirect anything
					echo '<noscript><meta http-equiv="refresh" content="0;url='. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=1'), 'apt_bulk_tagging_1_nonce') .'"></noscript>'; //if use the meta tag to refresh the page
					echo '<!-- //-Automatic Post Tagger -->';
					//this doesn't work: because of the HAS error
					//wp_redirect(admin_url('options-general.php?page=automatic-post-tagger&bt=1'));
					//header('Location: '. admin_url('options-general.php?page=automatic-post-tagger&bt=1'));
					//exit;
				}
			}
		}//-if for no errors found


	}//-nonce check
	else{//the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

#################################################################
########################## USER INTERFACE #######################
#################################################################
?>

<div id="poststuff" class="metabox-holder has-right-sidebar">
	<div class="inner-sidebar">
		<div id="side-sortables" class="meta-box-sortabless" style="position:relative;">

			<!-- postbox -->
			<div class="postbox">
				<h3 class="hndle"><span>Useful links</span></h3>
				<div class="inside">
						<ul>
						<li><a href="http://wordpress.org/plugins/automatic-post-tagger/"><span class="apt_wp"></span>Plugin homepage</a></li>
						<li><a href="http://wordpress.org/plugins/automatic-post-tagger/faq"><span class="apt_wp"></span>Frequently asked questions</a> </li>
						<li><a href="http://wordpress.org/support/plugin/automatic-post-tagger" title="Bug reports and feature requests"><span class="apt_wp"></span>Support forum</a></li>
						</ul>

						<ul>
						<li><a href="http://devtard.com"><span class="apt_devtard"></span>Devtard's blog</a></li>
						<li><a href="http://twitter.com/devtard_com"><span class="apt_twitter"></span>Devtard's Twitter</a></li>

						</ul>
				</div>
			</div>
			<!-- //-postbox -->

			<!-- postbox -->
			<div class="postbox">
				<h3 class="hndle"><span>Show some love!</span></h3>
				<div class="inside">
					<p>If you find this plugin useful, please give it a good rating and share it with others.</p>
						<ul>
						<li><a href="http://wordpress.org/support/view/plugin-reviews/automatic-post-tagger"><span class="apt_rate"></span>Rate plugin at WordPress.org</a></li>
						<li><a href="http://twitter.com/home?status=Automatic Post Tagger - useful WordPress plugin that automatically adds user-defined tags to posts. http://wordpress.org/plugins/automatic-post-tagger/"><span class="apt_twitter"></span>Post a link to Twitter</a></li>
						</ul>
					<p>Thank you. <em>-- Devtard</em></p>
				</div>
			</div><!-- //-postbox -->
			
			<!-- postbox -->
			<div class="postbox">
				<h3 class="hndle"><span>Recent contributions <!--<span class="apt_float_right"><small><a href="http://wordpress.org/plugins/automatic-post-tagger/other_notes">Full list</a></small></span>--></span></h3>
				<div class="inside">
					<ul>
						<li>21/11/2012 <a href="http://about.me/mikeschinkel">about.me/mikeschinkel</a></li>
						<li>07/10/2012 <a href="http://askdanjohnson.com">askdanjohnson.com</a></li>
					</ul>

					<p>
						Do you want to help me to improve this plugin? <a href="http://devtard.com/how-to-contribute-to-automatic-post-tagger" target="_blank">Read this &raquo;</a>
					</p>

				</div>
			</div><!-- //-postbox -->
		</div><!-- //-side-sortables -->
	</div><!-- //-inner-sidebar -->


	<div class="has-sidebar sm-padded">
		<div id="post-body-content" class="has-sidebar-content">
			<div class="meta-box-sortabless">
			<!-- happy editing! -->

				<!-- postbox -->
				<form action="<?php echo admin_url('options-general.php?page=automatic-post-tagger'); ?>" method="post">
				<div class="postbox">
					<div onclick="apt_toggle_widget(4);" class="handlediv" title="Click to toggle"><br></div>
					<h3 class="hndle"><span>General settings</span></h3>
					<!-- the style="" parameter printed by PHP must not be removed or togglable widgets will stop working -->
					<div class="inside" id="apt_widget_id_[4]" <?php if(in_array(4, explode($apt_settings['apt_string_separator'], $apt_settings['apt_hidden_widgets']))){echo 'style="display: none;"';} else {echo 'style="display: block;"';} ?>>


						<table class="form-table">
							<tr valign="top">
								<th scope="row">
									Analyzed content <span class="apt_help" title="APT will look for tags and their related words in selected areas.">i</span>
								</th>
								<td>
									<input type="checkbox" name="apt_title" id="apt_title" <?php if($apt_settings['apt_title'] == 1) echo 'checked="checked"'; ?>> <label for="apt_title">Title</label><br />
									<input type="checkbox" name="apt_content" id="apt_content" <?php if($apt_settings['apt_content'] == 1) echo 'checked="checked"'; ?>> <label for="apt_content">Content</label><br />
									<input type="checkbox" name="apt_excerpt" id="apt_excerpt" <?php if($apt_settings['apt_excerpt'] == 1) echo 'checked="checked"'; ?>> <label for="apt_excerpt">Excerpt</label>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="apt_tag_limit">Max. tags per post</label> <span class="apt_help" title="APT won't assign more tags than the specified number.">i</span>
								</th>
								<td>
									 <input type="text" name="apt_tag_limit" id="apt_tag_limit" value="<?php echo $apt_settings['apt_tag_limit']; ?>" maxlength="10" size="3"><br />
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									Action triggering tagging <span class="apt_help" title="This option determines when the tagging script will be executed. Using the first option is recommended.">i</span>
								</th>
								<td>
									<input type="radio" name="apt_tagging_hook_type" id="apt_tagging_hook_type_1" value="1" <?php if($apt_settings['apt_tagging_hook_type'] == 1) echo 'checked="checked"'; ?>> <label for="apt_tagging_hook_type_1">Publishing/updating</label><br />
									<input type="radio" name="apt_tagging_hook_type" id="apt_tagging_hook_type_2" value="2" <?php if($apt_settings['apt_tagging_hook_type'] == 2) echo 'checked="checked"'; ?> onClick="return confirm('Are you sure? The tagging algorithm will be run after every manual AND automatic saving of a post!')"> <label for="apt_tagging_hook_type_2">Saving</label>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									Old tags handling <span class="apt_help" title="This option determines what will happen if a post already has tags.">i</span>
								</th>
								<td>
									<input type="radio" name="apt_handling_current_tags" id="apt_handling_current_tags_1" value="1" <?php if($apt_settings['apt_handling_current_tags'] == 1) echo 'checked="checked"'; ?>> <label for="apt_handling_current_tags_1">Append new tags to old tags</label><br />
									<input type="radio" name="apt_handling_current_tags" id="apt_handling_current_tags_2" value="2" <?php if($apt_settings['apt_handling_current_tags'] == 2) echo 'checked="checked"'; ?>> <label for="apt_handling_current_tags_2">Replace old tags with newly generated tags</label><br />
									<input type="radio" name="apt_handling_current_tags" id="apt_handling_current_tags_3" value="3" <?php if($apt_settings['apt_handling_current_tags'] == 3) echo 'checked="checked"'; ?>> <label for="apt_handling_current_tags_3">Do nothing</label>
								</td>
							</tr>

							<tr valign="top">
								<th scope="row">
									<label for="apt_word_separators">Word separators</label> <span class="apt_help" title="Each character in this field will be treated as a word separator. You don't have to include a space, it is already treated as a word separator by default.">i</span>
								</th>
								<td>
									<input type="text" name="apt_word_separators" id="apt_word_separators" value="<?php echo htmlentities($apt_settings['apt_word_separators'], ENT_QUOTES); ?>" maxlength="255" size="30"><br />
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									Content processing <span class="apt_help" title="Various operations which are executed when processing content.">i</span>
								</th>
								<td>
									<input type="checkbox" name="apt_convert_diacritic" id="apt_convert_diacritic" <?php if($apt_settings['apt_convert_diacritic'] == 1) echo 'checked="checked"'; ?>> <label for="apt_convert_diacritic">Convert Latin diacritic characters to their ASCII equivalents</label> <span class="apt_help" title="This option is required if your language isn't English or your posts contain non-ASCII characters.">i</span><br />
									<input type="checkbox" name="apt_wildcards" id="apt_wildcards" <?php if($apt_settings['apt_wildcards'] == 1) echo 'checked="checked"'; ?>> <label for="apt_wildcards">Use the wildcard character "<b><?php echo $apt_settings['apt_wildcard_character']; ?></b>" to substitute any characters in related words</label> <span class="apt_help" title="Example: pattern &quot;cat<?php echo $apt_settings['apt_wildcard_character']; ?>&quot; will match words &quot;cats&quot; and &quot;category&quot;, pattern &quot;c<?php echo $apt_settings['apt_wildcard_character']; ?>t&quot; will match &quot;cat&quot;, &quot;colt&quot; etc.">i</span><br />
									<span class="apt_margin_left_18"><input type="checkbox" name="apt_wildcards_alphanumeric_only" id="apt_wildcards_alphanumeric_only" <?php if($apt_settings['apt_wildcards_alphanumeric_only'] == 1) echo 'checked="checked"'; ?>> <label for="apt_wildcards_alphanumeric_only">Match alphanumeric characters only</label> <span class="apt_help" title="If enabled, the wildcard will substitute only alphanumeric characters (a-z, A-Z, 0-9).">i</span><br />
									<input type="checkbox" name="apt_substring_analysis" id="apt_substring_analysis" <?php if($apt_settings['apt_substring_analysis'] == 1) echo 'checked="checked"'; ?>> <label for="apt_substring_analysis">Analyze only</label> <input type="text" name="apt_substring_analysis_length" value="<?php echo $apt_settings['apt_substring_analysis_length']; ?>" maxlength="10" size="2"> characters starting at position <input type="text" name="apt_substring_analysis_start" value="<?php echo $apt_settings['apt_substring_analysis_start']; ?>" maxlength="5" size="3"> <span class="apt_help" title="This option is useful if you don't want to analyze all content. It behaves like the PHP function 'substr', you can also enter sub-zero values.">i</span><br />
									<input type="checkbox" name="apt_ignore_case" id="apt_ignore_case" <?php if($apt_settings['apt_ignore_case'] == 1) echo 'checked="checked"'; ?>> <label for="apt_ignore_case">Ignore case</label> <span class="apt_help" title="Ignore case of tags, related words and post content.">i</span><br />
									<input type="checkbox" name="apt_strip_tags" id="apt_strip_tags" <?php if($apt_settings['apt_strip_tags'] == 1) echo 'checked="checked"'; ?>> <label for="apt_strip_tags">Strip PHP/HTML tags from analyzed content</label> <span class="apt_help" title="Ignore PHP/HTML code.">i</span><br />
									<input type="checkbox" name="apt_replace_whitespaces" id="apt_replace_whitespaces" <?php if($apt_settings['apt_replace_whitespaces'] == 1) echo 'checked="checked"'; ?>> <label for="apt_replace_whitespaces">Replace (multiple) whitespace characters with spaces</label> <span class="apt_help" title="Spaces are treated as word separators.">i</span><br />
									<input type="checkbox" name="apt_replace_nonalphanumeric" id="apt_replace_nonalphanumeric" <?php if($apt_settings['apt_replace_nonalphanumeric'] == 1) echo 'checked="checked"'; ?>> <label for="apt_replace_nonalphanumeric">Replace non-alphanumeric characters with spaces</label> <span class="apt_help" title="If enabled, deleting user-defined word separators is recommended for better performance.">i</span><br />
									<span class="apt_margin_left_18"><input type="checkbox" name="apt_ignore_wildcards" id="apt_ignore_wildcards" <?php if($apt_settings['apt_ignore_wildcards'] == 1) echo 'checked="checked"'; ?>> <label for="apt_ignore_wildcards">Don't replace wildcard characters</label> <span class="apt_help" title="This option is required if you want to use wildcards.">i</span>
								</td>
							</tr>

							<tr valign="top">
								<th scope="row">
									<label for="apt_wildcard_character">Wildcard character</label> <span class="apt_help" title="Using an asterisk is recommended. If you change the value, all occurences of old wildcard characters in related words will be changed.">i</span>
								</th>
								<td>
									<input type="text" name="apt_wildcard_character" id="apt_wildcard_character" value="<?php echo $apt_settings['apt_wildcard_character']; ?>" maxlength="255" size="3">
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="apt_string_separator">String separator</label> <span class="apt_help" title="For separation of related words, ignored post statuses and DB options. Using a comma is recommended. If you change the value, all occurences of old string separators will be changed.">i</span>
								</th>
								<td>
									<input type="text" name="apt_string_separator" id="apt_string_separator" value="<?php echo $apt_settings['apt_string_separator']; ?>" maxlength="255" size="3">
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="apt_stored_backups">Max. stored backups</label> <span class="apt_help" title="The maximum number of generated backups stored in the plugin's directory. The extra oldest file will be always automatically deleted when creating a new backup.">i</span>
								</th>
								<td>
									<input type="text" name="apt_stored_backups" id="apt_stored_backups" value="<?php echo $apt_settings['apt_stored_backups']; ?>" maxlength="255" size="3">
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									Warning messages <span class="apt_help" title="Warnings can be hidden if you think that they are annoying.">i</span>
								</th>
								<td>
									<input type="checkbox" name="apt_warning_messages" id="apt_warning_messages" <?php if($apt_settings['apt_warning_messages'] == 1) echo 'checked="checked"'; ?>> <label for="apt_warning_messages">Display warning messages</label>
								</td>
							</tr>

									<!-- TODO v1.6 <label for="apt_miscellaneous_minimum_keyword_occurrence">Minimum keyword occurrence:</label> <input type="text" name="apt_miscellaneous_minimum_keyword_occurrence" id="apt_miscellaneous_minimum_keyword_occurrence" value="?php echo $apt_settings['apt_miscellaneous_minimum_keyword_occurrence']; ?>" maxlength="10" size="3"> <small><em>(keywords representing tags that occur less often won't be added as tags)</em></small><br /> -->
									<!-- TODO v1.6 <input type="checkbox" name="apt_miscellaneous_add_most_frequent_tags_first" id="apt_miscellaneous_add_most_frequent_tags_first" ?php if($apt_settings['apt_miscellaneous_add_most_frequent_tags_first'] == 1) echo 'checked="checked"'; ?>> <label for="apt_miscellaneous_add_most_frequent_tags_first">Add most frequent tags first <small><em>(useful for adding most relevant tags before the max. tag limit is hit)</em></small></label><br /> -->
		 
						</table>

						<p class="submit">
							<input class="button-primary" type="submit" name="apt_save_settings_button" value=" Save settings "> 
							<input class="button apt_red_background" type="submit" name="apt_restore_default_settings_button" onClick="return confirm('Do you really want to reset all settings to default values (including deleting all tags)?\nYou might want to create a backup first.')" value=" Restore default settings ">
						</p>
					</div>
				</div>
	
				<?php wp_nonce_field('apt_save_settings_nonce','apt_save_settings_hash'); ?>
				<?php wp_nonce_field('apt_restore_default_settings_nonce','apt_restore_default_settings_hash'); ?>
				</form>
				<!-- //-postbox -->
		
				<!-- postbox -->
				<form action="<?php echo admin_url('options-general.php?page=automatic-post-tagger'); ?>" method="post">
				<div class="postbox">
				<div onclick="apt_toggle_widget(5);" class="handlediv" title="Click to toggle"><br></div>
					<h3 class="hndle"><span>Create new tag</span></h3>
					<div class="inside" id="apt_widget_id_[5]" <?php if(in_array(5, explode($apt_settings['apt_string_separator'], $apt_settings['apt_hidden_widgets']))){echo 'style="display: none;"';} else {echo 'style="display: block;"';} ?>>

						<table class="apt_width_100_percent">
						<tr>
							<td class="apt_width_35_percent">Tag name <span class="apt_help" title="Example: &quot;cat&quot;">i</span></td>
							<td class="apt_width_65_percent">Related words (separated by <b><?php echo $apt_settings['apt_string_separator']; ?></b>") <span class="apt_help" title="<?php echo $apt_example_related_words; ?>">i</span></td></tr>
						<tr>
							<td><input class="apt_width_100_percent" type="text" name="apt_create_tag_name" maxlength="255"></td>
							<td><input class="apt_width_100_percent" type="text" name="apt_create_tag_related_words" maxlength="255"></td>
						</tr>
						</table>

						<p>
							<input class="button" type="submit" name="apt_create_new_tag_button" value=" Create new tag ">
							<span class="apt_float_right"><small><b>Tip:</b> You can also create tags directly from a widget located next to the post editor.</small></span>		
						</p>
					</div>
				</div>
				<?php wp_nonce_field('apt_create_new_tag_nonce','apt_create_new_tag_hash'); ?>
				</form>

				<!-- //-postbox -->


				<!-- postbox -->
				<form action="<?php echo admin_url('options-general.php?page=automatic-post-tagger'); ?>" enctype="multipart/form-data" method="post">
				<div class="postbox">
				<div onclick="apt_toggle_widget(6);" class="handlediv" title="Click to toggle"><br></div>
					<h3 class="hndle"><span>Import/Export tags</span></h3>
					<div class="inside" id="apt_widget_id_[6]" <?php if(in_array(6, explode($apt_settings['apt_string_separator'], $apt_settings['apt_hidden_widgets']))){echo 'style="display: none;"';} else {echo 'style="display: block;"';} ?>>

						<table class="apt_width_100_percent">
						<tr>
							<td class="apt_width_35_percent">Import already existing tags <span class="apt_help" title="This tool will import all already existing tags that are in your WordPress database.">i</span></td>
							<td class="apt_width_65_percent"><input class="button" type="submit" name="apt_import_existing_tags_button" value=" Import existing tags" onClick="return confirm('Do you really want to import all already existing tags?\nThis may take some time if your blog has lots of them.')"></td>
						</tr>
						<tr>
							<td>Import tags from a backup <span class="apt_help" title="This tool will import tags from a CSV file. Its name must begin with the prefix &quot;<?php echo $apt_new_backup_file_name_prefix; ?>&quot;.">i</span></td>
							<td><input type="file" size="1" name="apt_uploaded_file"> <input class="button" type="submit" name="apt_import_from_backup_button" value=" Import from backup "></td>
						</tr>
						<tr>
							<td>Export tags to a CSV backup <span class="apt_help" title="This tool will create a backup in the directory &quot;<?php echo $apt_backup_dir_rel_path; ?>&quot;.">i</span></td>
							<td><input class="button" type="submit" name="apt_export_tags_button" value=" Export tags "></td>
						</tr>
						</table>
					</div>
				</div>

				<?php wp_nonce_field('apt_import_existing_tags_nonce','apt_import_existing_tags_hash'); ?>
				<?php wp_nonce_field('apt_import_from_backup_nonce','apt_import_from_backup_hash'); ?>
				<?php wp_nonce_field('apt_export_tags_nonce','apt_export_tags_hash'); ?>
				</form>

				<!-- //-postbox -->

				<!-- postbox -->
				<form action="<?php echo admin_url('options-general.php?page=automatic-post-tagger'); ?>" method="post">
				<div class="postbox">
				<div onclick="apt_toggle_widget(7);" class="handlediv" title="Click to toggle"><br></div>
					<h3 class="hndle"><span>Manage tags <small>(<?php echo $apt_settings['apt_stats_current_tags']; ?>)</small></span></h3>
					<div class="inside" id="apt_widget_id_[7]" <?php if(in_array(7, explode($apt_settings['apt_string_separator'], $apt_settings['apt_hidden_widgets']))){echo 'style="display: none;"';} else {echo 'style="display: block;"';} ?>>

						<?php
						//for retrieving all tags and their count
						$apt_all_table_rows_sql = "SELECT * FROM $apt_table ORDER BY tag";
						$apt_all_table_rows_results = $wpdb->get_results($apt_all_table_rows_sql, ARRAY_A); //ARRAY_A - result will be output as an numerically indexed array of associative arrays, using column names as keys. 
						$apt_all_table_rows_count = count($apt_all_table_rows_results); //when we already did the query, why not just count live results instead of retrieving it from the option apt_stats_current_tags?

						if($apt_all_table_rows_count == 0){
							echo '<p>There aren\'t any tags.</p>';
						}
						else{
						?>

						<div class="apt_manage_tags_div">
							<table class="apt_width_100_percent">
								<tr><td class="apt_width_35_percent">Tag name</td><td style="width:63%;">Related words</td><td style="width:2%;"></td></tr>
						<?php
							foreach($apt_all_table_rows_results as $row){
							?>
								<tr>
								<td><input class="apt_width_100_percent" type="text" name="apt_taglist_tag_[<?php echo $row['id']; ?>]" id="apt_taglist_tag_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['tag']); ?>" maxlength="255"></td>
								<td><input class="apt_width_100_percent" type="text" name="apt_taglist_related_words_[<?php echo $row['id']; ?>]" id="apt_taglist_related_words_<?php echo $row['id']; ?>" value="<?php echo htmlspecialchars($row['related_words']); ?>" maxlength="255"></td>
								<td><input type="checkbox" name="apt_taglist_checkbox_[<?php echo $row['id']; ?>]" id="apt_taglist_checkbox_<?php echo $row['id']; ?>" onclick="apt_change_background(<?php echo $row['id']; ?>);"></td>
								</tr>
							<?php
							}//-foreach
						?>
							</table>
						</div>

						<p class="submit">
							<input class="button" type="submit" name="apt_save_tags_button" value=" Save changes ">

							<input class="button apt_red_background apt_float_right apt_button_margin_left" type="submit" name="apt_delete_chosen_tags_button" onClick="return confirm('Do you really want to delete chosed tags?')" value=" Delete chosen tags ">
							<input class="button apt_red_background apt_float_right apt_button_margin_left" type="submit" name="apt_delete_all_tags_button" onClick="return confirm('Do you really want to delete all tags?')" value=" Delete all tags ">
						</p>

						<?php
						}
						?>


					</div>
				</div>
				<?php wp_nonce_field('apt_save_tags_nonce','apt_save_tags_hash'); ?>
				<?php wp_nonce_field('apt_delete_chosen_tags_nonce','apt_delete_chosen_tags_hash'); ?>
				<?php wp_nonce_field('apt_delete_all_tags_nonce','apt_delete_all_tags_hash'); ?>
				</form>
				<!-- //-postbox -->

							<?php
							$apt_select_posts_id_min = $wpdb->get_var("SELECT MIN(ID) FROM $wpdb->posts ". apt_print_sql_where_without_specified_statuses());
							$apt_select_posts_id_max = $wpdb->get_var("SELECT MAX(ID) FROM $wpdb->posts ". apt_print_sql_where_without_specified_statuses());
							?>

				<!-- postbox -->
				<form action="<?php echo admin_url('options-general.php?page=automatic-post-tagger'); ?>" method="post">
				<div class="postbox">
					<div onclick="apt_toggle_widget(8);" class="handlediv" title="Click to toggle"><br></div>
					<h3 class="hndle"><span>Bulk tagging</span></h3>
					<div class="inside" id="apt_widget_id_[8]" <?php if(in_array(8, explode($apt_settings['apt_string_separator'], $apt_settings['apt_hidden_widgets']))){echo 'style="display: none;"';} else {echo 'style="display: block;"';} ?>>

							<table class="apt_width_100_percent">
								<tr>
									<td class="apt_width_35_percent"><label for="apt_bulk_tagging_posts_per_cycle">Number of posts tagged per cycle</label> <span class="apt_help" title="Low value helps avoid the &quot;max_execution_time&quot; error.">i</span></td>
									<td class="apt_width_65_percent"><input type="text" name="apt_bulk_tagging_posts_per_cycle" id="apt_bulk_tagging_posts_per_cycle" value="<?php echo $apt_settings['apt_bulk_tagging_posts_per_cycle']; ?>" maxlength="10" size="3"></td></tr>
								</tr>
								<tr>
									<td><label for="apt_bulk_tagging_statuses">Ignore posts with these statuses</label> <span class="apt_help" title="Posts with specified statuses won't be processed. Separate multiple values with &quot;<?php echo $apt_settings['apt_string_separator']; ?>&quot;. You can use these statuses: &quot;auto-draft&quot;, &quot;draft&quot;, &quot;future&quot;, &quot;inherit&quot;, &quot;pending&quot;, &quot;private&quot;, &quot;publish&quot;, &quot;trash&quot;.">i</span></td>
									<td><input type="text" name="apt_bulk_tagging_statuses" id="apt_bulk_tagging_statuses" value="<?php echo $apt_settings['apt_bulk_tagging_statuses']; ?>" maxlength="255" size="35"></td></tr>
								</tr>
								<tr>
									<td>Process only posts in this ID range <span class="apt_help" title="By default all posts will be processed. Default values are being calculated by using ignored statuses specified above.">i</span></td>
									<td><input type="text" name="apt_bulk_tagging_range_1" value="<?php echo $apt_select_posts_id_min; ?>" maxlength="255" size="3"> - <input type="text" name="apt_bulk_tagging_range_2" value="<?php echo $apt_select_posts_id_max; ?>" maxlength="255" size="3"></td></tr>
								</tr>
							</table>


							<p class="submit">
								<input class="button" type="submit" name="apt_bulk_tagging_button" onClick="return confirm('Do you really want to proceed?\nAny changes can\'t be reversed.')" value=" Assign tags "> 
							</p>
						</div>
					</div>

				<?php wp_nonce_field('apt_bulk_tagging_nonce','apt_bulk_tagging_hash'); ?>
				</form>
				<!-- //-postbox -->


			<!-- stop right here! -->
			</div>
		</div>
	</div>

</div>
</div>



<?php
//echo "memory usage: ". memory_get_usage($real_usage = true); //for debugging

} //-function options page
?>
