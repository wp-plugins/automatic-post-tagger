<?php
/*
Plugin Name: Automatic Post Tagger
Plugin URI: http://wordpress.org/plugins/automatic-post-tagger/
Description: This plugin uses keywords provided by the user to automatically add tags to posts according to their title, content and excerpt.
Version: 1.7
Author: Devtard
Author URI: http://devtard.com
License: GPLv2 or later

Copyright (C) 2012-2015 Devtard (gmail.com ID: devtard)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

defined('ABSPATH') or exit; //prevents direct access to the file

## =========================================================================
## ### BASIC DECLARATIONS
## =========================================================================

global $wpdb, $pagenow; //variables used in activation/uninstall functions HAVE TO be declared as global in order to work - see http://codex.wordpress.org/Function_Reference/register_activation_hook#A_Note_on_Variable_Scope; $pagenow is loaded because of the publish/save/insert post hooks

$apt_settings = get_option('automatic_post_tagger');

$apt_plugin_url = WP_PLUGIN_URL . "/" . basename(dirname(__FILE__)) . "/";
$apt_plugin_dir = WP_PLUGIN_DIR . "/" . basename(dirname(__FILE__)) . "/";
$apt_plugin_basename = plugin_basename(__FILE__); //automatic-post-tagger/automatic-post-tagger.php

$apt_new_backup_file_name_prefix = 'apt-backup';
$apt_new_backup_file_name_suffix = '.csv';
$apt_backup_dir_rel_path = $apt_plugin_dir .'backup/'; //relative path
$apt_backup_dir_abs_path = $apt_plugin_url .'backup/'; //absolute path

$apt_message_html_prefix_updated = '<div id="message" class="updated"><p>';
$apt_message_html_prefix_error = '<div id="message" class="error"><p>';
$apt_message_html_prefix_warning = '<div id="message" class="updated warning"><p>';
$apt_message_html_prefix_note = '<div id="message" class="updated note"><p>';
$apt_message_html_suffix = '</p></div>';
$apt_invalid_nonce_message = $apt_message_html_prefix_error .'<strong>Error:</strong> Sorry, your nonce did not verify, your request couldn\'t be executed. Please try again.'. $apt_message_html_suffix;
$apt_max_input_vars_value = @ini_get('max_input_vars');

//$wpdb->show_errors(); //for debugging - TODO: comment before releasing to public

## =========================================================================
## ### HOOKS
## =========================================================================

register_activation_hook(__FILE__, 'apt_install_plugin');
register_uninstall_hook(__FILE__, 'apt_uninstall_plugin');

function apt_admin_init_actions(){
	global $pagenow,
	$apt_plugin_basename;

	if($pagenow == 'plugins.php'){ //page plugins.php is being displayed
		add_filter('plugin_action_links_'. $apt_plugin_basename, 'apt_plugin_action_links', 10, 1);
		add_filter('plugin_row_meta', 'apt_plugin_meta_links', 10, 2);
	}
	if($pagenow == 'options-general.php' AND isset($_GET['page']) AND $_GET['page'] == 'automatic-post-tagger'){ //page options-general.php?page=automatic-post-tagger is being displayed
		add_action('admin_print_scripts', 'apt_insert_ajax_nonce_options_page');
		add_action('admin_enqueue_scripts', 'apt_load_options_page_scripts');
	}
	if(in_array($pagenow, array('post.php', 'post-new.php'))){ //page post.php or post-new.php is being displayed
		add_action('admin_print_scripts', 'apt_insert_ajax_nonce_meta_box');
		add_action('admin_enqueue_scripts', 'apt_load_meta_box_scripts');
		add_action('add_meta_boxes', 'apt_meta_box_add');
	}
}

if(is_admin()){ //only if the admin panel is being displayed
	add_action('admin_menu', 'apt_menu_link');
	add_action('admin_notices', 'apt_plugin_admin_notices', 20);
	add_action('admin_init', 'apt_admin_init_actions');
	add_action('wp_ajax_apt_meta_box_create_new_keyword', 'apt_meta_box_create_new_keyword');
	add_action('wp_ajax_apt_toggle_widget', 'apt_toggle_widget');

	if(isset($pagenow)){
		if(in_array($pagenow, array('plugins.php', 'update-core.php', 'update.php')) OR ($pagenow == 'options-general.php' AND isset($_GET['page']) AND $_GET['page'] == 'automatic-post-tagger')){ //the options page, or update-core.php or plugins.php or update.php are being displayed
			add_action('plugins_loaded', 'apt_update_plugin');
		}
	}
	else{ //BUG: the global variable $pagenow doesn't seem to work on Multisite when it's not in a function (apt_admin_init_actions) loaded via the admin_init hook (but when it is, then the update function isn't called via the plugins_loaded hook)
		add_action('plugins_loaded', 'apt_update_plugin'); //load the update function anyway
	}
} //-is_admin

## When the tagging function should be executed
if(@$apt_settings['apt_run_apt_publish_post'] == 1 AND isset($pagenow) AND in_array($pagenow, array('post.php', 'post-new.php')) AND @$apt_settings['apt_run_apt_save_post'] != 1){ //this hook IS fired when the post editor is displayed; the function is triggered only once (if tagging posts is allowed when posts are being saved)
	add_action('publish_post','apt_single_post_tagging'); //executes the tagging function when publishing posts
}
if(@$apt_settings['apt_run_apt_wp_insert_post'] == 1 AND isset($pagenow) AND !in_array($pagenow, array('post.php', 'post-new.php', 'edit.php'))){ //this hook IS NOT fired when the post editor is displayed (this would result in posts saved via the post editor always being processed by APT)
	add_action('wp_insert_post','apt_single_post_tagging'); //executes the tagging function when inserting posts
}
if(@$apt_settings['apt_run_apt_save_post'] == 1 AND isset($pagenow) AND in_array($pagenow, array('post.php', 'post-new.php')) AND ((isset($_GET['action']) AND $_GET['action'] != 'trash') OR !isset($_GET['action']))){ //this hook IS fired when the post editor is being displayed AND the post is not being trashed
	add_action('save_post','apt_single_post_tagging'); //executes the tagging function when saving posts
}

## ===================================
## ### GET PLUGIN VERSION
## ===================================

function apt_get_plugin_version(){ //return plugin version
	if(!function_exists('get_plugin_data')){
		require_once(ABSPATH .'wp-admin/includes/plugin.php');
	}

	$apt_plugin_data = get_plugin_data( __FILE__, false, false);
	$apt_plugin_version = $apt_plugin_data['Version'];
	return $apt_plugin_version;
}

## ===================================
## ### ACTIVATE FUNCTION
## ===================================

function apt_install_plugin(){ //runs only after MANUAL activation! (also used for restoring settings)
	if(get_option('automatic_post_tagger') == false){ //create the option only if it doesn't exist yet
		$apt_default_settings = array(
			'apt_plugin_version' => apt_get_plugin_version(),
			'apt_admin_notice_install' => '1',
			'apt_admin_notice_update' => '0',
			'apt_hidden_widgets' => array(),
			'apt_keywords_total' => '0',
			'apt_last_keyword_id' => '0',
			'apt_title' => '1',
			'apt_content' => '1',
			'apt_excerpt' => '0',
			'apt_search_for_keyword_names' => '1',
			'apt_search_for_related_words' => '1',
			'apt_tag_limit' => '20',
			'apt_run_apt_publish_post' => '1',
			'apt_run_apt_save_post' => '0',
			'apt_run_apt_wp_insert_post' => '1',
			'apt_old_tags_handling' => '1',
			'apt_old_tags_handling_2_remove_old_tags' => '0',
			'apt_word_separators' => array('.','&#44;',' ','?','!',':',';','\'','"','\\','|','/','(',')','[',']','{','}','_','+','=','-','<','>','~','@','#','$','%','^','&','*'),
			'apt_ignore_case' => '1',
			'apt_decode_html_entities_word_separators' => '1',
			'apt_decode_html_entities_analyzed_content' => '0',
			'apt_decode_html_entities_related_words' => '0',
			'apt_strip_tags' => '1',
			'apt_replace_whitespaces' => '1',
			'apt_replace_nonalphanumeric' => '0',
			'apt_dont_replace_wildcards' => '1',
			'apt_substring_analysis' => '0',
			'apt_substring_analysis_length' => '1000',
			'apt_substring_analysis_start' => '0',
			'apt_wildcards' => '1',
			'apt_post_types' => array('post'),
			'apt_post_statuses' => array('publish'),
			'apt_taxonomy_name' => 'post_tag',
			'apt_wildcard_character' => '*',
			'apt_string_separator' => ',',
			'apt_warning_messages' => '1',
			'apt_input_correction' => '1',
			'apt_create_backup_when_updating' => '1',
			'apt_stored_backups' => '10',
			'apt_wildcard_regex' => '(.*)',
			'apt_keyword_editor_mode' => '1',
			'apt_bulk_tagging_posts_per_cycle' => '15',
			'apt_bulk_tagging_delay' => '1',
			'apt_bulk_tagging_queue' => array()
		);

		add_option('automatic_post_tagger', $apt_default_settings, '', 'no'); //single option for storing default settings
	}

	if(get_option('automatic_post_tagger_keywords') == false){ //create the option only if it doesn't exist yet
		add_option('automatic_post_tagger_keywords', array(), '', 'no'); //single option for storing keywords
	}
}

## ===================================
## ### UPDATE FUNCTION
## ===================================

function apt_update_plugin(){ //update function - runs when all plugins are loaded
	global $wpdb, 
	$apt_message_html_prefix_error,
	$apt_message_html_suffix,
	$apt_backup_dir_rel_path;

	$apt_settings = get_option('automatic_post_tagger');

	if(current_user_can('manage_options')){
		$apt_current_version = apt_get_plugin_version();

//for debugging
//echo "apt_settings[apt_admin_notice_update]:". $apt_settings['apt_admin_notice_update'];
//echo " apt_settings['apt_plugin_version']:". $apt_settings['apt_plugin_version'];
//echo " apt_current_version:". $apt_current_version;

		#### now comes everything that must be changed in the new version
		//if the user uses a very old version, we have to include all DB changes that are included in the following version checks - in case of problems reinstalling fixes everything
		//we must not forget to include new changes in conditions for all previous versions
		//versions should be updated to the newest one, not to the following one

		//we need to check whether the DB option with the plugin version actually exists

		if(isset($apt_settings['apt_plugin_version'])){ //if the variable exists (since 1.5)
			if($apt_settings['apt_plugin_version'] != $apt_current_version){ //check whether the saved version isn't equal to the current version

				if(($apt_settings['apt_plugin_version'] == '1.5') OR ($apt_settings['apt_plugin_version'] == '1.5.1')){ //update from 1.5 to the newest version
					//copy old values to new suboptions
					$apt_settings['apt_dont_replace_wildcards'] = $apt_settings['apt_ignore_wildcards'];

					//remove suboptions
					unset($apt_settings['apt_admin_notice_prompt']);
					unset($apt_settings['apt_stats_install_date']);
					unset($apt_settings['apt_stats_current_tags']);
					unset($apt_settings['apt_convert_diacritic']);
					unset($apt_settings['apt_ignore_wildcards']);
					unset($apt_settings['apt_wildcards_alphanumeric_only']);

					//new suboptions
					$apt_settings['apt_last_keyword_id'] = '0';
					$apt_settings['apt_old_tags_handling_2_remove_old_tags'] = '0';
					$apt_settings['apt_post_types'] = array('post');
					$apt_settings['apt_taxonomy_name'] = 'post_tag';
					$apt_settings['apt_search_for_keyword_names'] = '1';
					$apt_settings['apt_search_for_related_words'] = '1';
					$apt_settings['apt_input_correction'] = '1';
					$apt_settings['apt_create_backup_when_updating'] = '1';
					$apt_settings['apt_wildcard_regex'] = '(.*)';
					$apt_settings['apt_keyword_editor_mode'] = '1';
					$apt_settings['apt_keywords_total'] = '0';
					$apt_settings['apt_bulk_tagging_delay'] = '1';
					$apt_settings['apt_decode_html_entities_word_separators'] = '1';
					$apt_settings['apt_decode_html_entities_analyzed_content'] = '0';
					$apt_settings['apt_decode_html_entities_related_words'] = '0';

					//reset values/change variables to arrays
					$apt_settings['apt_word_separators'] = array('.','&#44;',' ','?','!',':',';','\'','"','\\','|','/','(',')','[',']','{','}','_','+','=','-','<','>','~','@','#','$','%','^','&','*');
					$apt_settings['apt_post_statuses'] = array('publish');
					$apt_settings['apt_hidden_widgets'] = array();
					$apt_settings['apt_bulk_tagging_queue'] = array();

					//copy keywords from $apt_table to the new option "automatic_post_tagger_keywords"
					$apt_keywords_array_new = array();
					$apt_table = $wpdb->prefix .'apt_tags'; //table for storing keywords and related words
					$apt_select_keyword_related_words_sql = "SELECT tag, related_words FROM $apt_table";
					$apt_select_keyword_related_words_results = $wpdb->get_results($apt_select_keyword_related_words_sql, ARRAY_N); //get keywords and related words from the DB
					$apt_select_keyword_related_words_results_count = count($apt_select_keyword_related_words_results);
					$apt_keywords_moved_to_option_error = 0; //variable for checking whether an error occurred during copying keywords to their new option

					//move keywords to the new DB option and create a backup of keywords before deleting the old table
					if($apt_select_keyword_related_words_results_count > 0){ //export tags only if table isn't empty
						$apt_new_keyword_id = $apt_settings['apt_last_keyword_id']; //the id value MUST NOT be increased here - it is increased in the loop
						$apt_new_backup_file_name = apt_get_backup_file_name();
						$apt_new_backup_file_rel_path = $apt_backup_dir_rel_path . $apt_new_backup_file_name;
						$apt_backup_file_fopen = fopen($apt_new_backup_file_rel_path, 'w');

						foreach($apt_select_keyword_related_words_results as $apt_row){ //loop handling every row in the table
							$apt_new_keyword_id++; //the ID must be increased to avoid adding multiple keywords with the same ID
	 						array_push($apt_keywords_array_new, array($apt_new_keyword_id, $apt_row[0], $apt_row[1])); //add the id + keyword + related words to the array
							@fputcsv($apt_backup_file_fopen, $apt_row); //add each row from the table to the backup file; the @ character should suppress warnings if the fopen function returns false
						} //-foreach
						fclose($apt_backup_file_fopen);

						$apt_settings['apt_keywords_total'] = count($apt_keywords_array_new); //update keyword stats

						//single option for storing keywords - save all copied keywords to this option
						if(get_option('automatic_post_tagger_keywords') == false){ //create the option only if it doesn't exist yet
							add_option('automatic_post_tagger_keywords', $apt_keywords_array_new, '', 'no');
						}
						else{
							$apt_keywords_moved_to_option_error = 1;
							echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "automatic_post_tagger_keywords" already exists.'. $apt_message_html_suffix;
						} //-else option doesn't exist

						//check whether the number of copied keywords is equal to the number of table rows
						if($apt_settings['apt_keywords_total'] != $apt_select_keyword_related_words_results_count){
							$apt_keywords_moved_to_option_error = 1;
							echo $apt_message_html_prefix_error .'<strong>Error:</strong> The number of items copied to the option "automatic_post_tagger_keywords" is not the same as the number of keywords in the table "'. $apt_table .'".'. $apt_message_html_suffix;
						}
					} //-export tags only if table isn't empty
					else{ //table is empty
						if(get_option('automatic_post_tagger_keywords') == false){ //create the option only if it doesn't exist yet
							add_option('automatic_post_tagger_keywords', array(), '', 'no'); //save empty array
						}
					} //else table isn't empty

					if($apt_keywords_moved_to_option_error == 0){ //delete table if no errors occurred
						$wpdb->query('DROP TABLE '. $apt_table);
					} //-delete table
					else{ //some errors occurred
						echo $apt_message_html_prefix_error .'<strong>Error:</strong> The DB table "'. $apt_table .'" was not automatically deleted, because all its data wasn\'t successfully copied to the option "automatic_post_tagger_keywords".'. $apt_message_html_suffix;
					} //-else no errors occurred
				} //update from 1.5 and 1.5.1

				if($apt_settings['apt_plugin_version'] == '1.6'){ //update from 1.6 to the newest version
					//new suboptions
					//copying old values to new variables
					if($apt_settings['apt_tagging_hook_type'] == 1){ 
						$apt_settings['apt_run_apt_publish_post'] = '1';
						$apt_settings['apt_run_apt_save_post'] = '0';
					}
					else{ //trigger tagging when saving the post
						$apt_settings['apt_run_apt_save_post'] = '1';
						$apt_settings['apt_run_apt_publish_post'] = '0';
					}

					$apt_settings['apt_old_tags_handling'] = $apt_settings['apt_handling_current_tags'];
					$apt_settings['apt_old_tags_handling_2_remove_old_tags'] = $apt_settings['apt_handling_current_tags_2_remove_old_tags'];
					$apt_settings['apt_keyword_editor_mode'] = $apt_settings['apt_keyword_management_mode'];

					$apt_settings['apt_run_apt_wp_insert_post'] = '1';
					$apt_settings['apt_bulk_tagging_delay'] = '1';
					$apt_settings['apt_post_statuses'] = array('publish');
					$apt_settings['apt_decode_html_entities_word_separators'] = '1';
					$apt_settings['apt_decode_html_entities_analyzed_content'] = '0';
					$apt_settings['apt_decode_html_entities_related_words'] = '0';

					//remove suboptions
					unset($apt_settings['apt_tagging_hook_type']);
					unset($apt_settings['apt_bulk_tagging_statuses']);
					unset($apt_settings['apt_handling_current_tags']);
					unset($apt_settings['apt_handling_current_tags_2_remove_old_tags']);
					unset($apt_settings['apt_keyword_management_mode']);
				}


//TODO				if($apt_settings['apt_plugin_version'] == '1.7'){ //update from 1.7 to the newest version
//				}

				######################################################## 
				//update the plugin version and update notice

				//modify settings
				$apt_settings['apt_admin_notice_update'] = 1; //we want to show the admin notice after updating
				$apt_settings['apt_plugin_version'] = $apt_current_version; //update plugin version

				//update settings
				update_option('automatic_post_tagger', $apt_settings); 

				//create an automatic backup when updating
				if($apt_settings['apt_create_backup_when_updating'] == 1){
					apt_export_keywords(0);
				}
				######################################################## 
			} //-version equality check
		} //-if new suboption with the plugin version exists
		else{ //if it doesn't exist try to retrieve the variable from the OLD DB FORMAT - it's here because of backward compatibility
			if(get_option('apt_plugin_version')){
				if(get_option('apt_plugin_version') != $apt_current_version){ //check whether the saved version isn't equal to the current version
					if(get_option('apt_plugin_version') == '1.0' OR get_option('apt_plugin_version') == '1.1' OR  get_option('apt_plugin_version') == '1.2' OR get_option('apt_plugin_version') == '1.3'){ //update from 1.1, 1.2 or 1.3 to the newest version -- get_option must not be deleted
						apt_install_plugin();

						//delete old options
						delete_option('apt_plugin_version');
						delete_option('apt_admin_notice_install');
						delete_option('apt_admin_notice_update');
						delete_option('apt_admin_notice_donate');
						delete_option('apt_stats_current_tags');
						delete_option('apt_stats_assigned_tags');
						delete_option('apt_stats_install_date');
						delete_option('apt_post_analysis_title');
						delete_option('apt_post_analysis_content');
						delete_option('apt_post_analysis_excerpt');
						delete_option('apt_handling_current_tags');
						delete_option('apt_miscellaneous_tag_maximum');
						delete_option('apt_miscellaneous_tagging_occasion');
						delete_option('apt_miscellaneous_wildcards');
						delete_option('apt_word_recognition_separators');
						delete_option('apt_string_manipulation_convert_diacritic');
						delete_option('apt_miscellaneous_substring_analysis');
						delete_option('apt_miscellaneous_substring_analysis_length');
						delete_option('apt_miscellaneous_substring_analysis_start');
					}

					if(get_option('apt_plugin_version') == '1.4'){ //update from 1.4 to the newest version -- get_option must not be deleted

						//new stuff will be stored in one option as an array - we are adding old values
						$apt_new_settings = array(
							'apt_plugin_version' => apt_get_plugin_version(),
							'apt_admin_notice_install' => '1',
							'apt_admin_notice_update' => '0',
							'apt_hidden_widgets' => array(),
							'apt_keywords_total' => '0',
							'apt_last_keyword_id' => '0',
							'apt_title' => '1',
							'apt_content' => '1',
							'apt_excerpt' => '0',
							'apt_search_for_keyword_names' => '1',
							'apt_search_for_related_words' => '1',
							'apt_tag_limit' => '20',
							'apt_run_apt_publish_post' => '1',
							'apt_run_apt_save_post' => '0',
							'apt_run_apt_wp_insert_post' => '1',
							'apt_old_tags_handling' => '1',
							'apt_old_tags_handling_2_remove_old_tags' => '0',
							'apt_word_separators' => array('.','&#44;',' ','?','!',':',';','\'','"','\\','|','/','(',')','[',']','{','}','_','+','=','-','<','>','~','@','#','$','%','^','&','*'),
							'apt_ignore_case' => '1',
							'apt_decode_html_entities_word_separators' => '1',
							'apt_decode_html_entities_analyzed_content' => '0',
							'apt_decode_html_entities_related_words' => '0',
							'apt_strip_tags' => '1',
							'apt_replace_whitespaces' => '1',
							'apt_replace_nonalphanumeric' => '0',
							'apt_dont_replace_wildcards' => '1',
							'apt_substring_analysis' => '0',
							'apt_substring_analysis_length' => '1000',
							'apt_substring_analysis_start' => '0',
							'apt_wildcards' => '1',
							'apt_post_types' => array('post'),
							'apt_post_statuses' => array('publish'),
							'apt_taxonomy_name' => 'post_tag',
							'apt_wildcard_character' => '*',
							'apt_string_separator' => ',',
							'apt_warning_messages' => '1',
							'apt_input_correction' => '1',
							'apt_create_backup_when_updating' => '1',
							'apt_stored_backups' => '10',
							'apt_wildcard_regex' => '(.*)',
							'apt_keyword_editor_mode' => '1',
							'apt_bulk_tagging_posts_per_cycle' => '15',
							'apt_bulk_tagging_delay' => '1',
							'apt_bulk_tagging_queue' => array()
						);

						add_option('automatic_post_tagger', $apt_new_settings, '', 'no'); //single option for saving default settings

						//delete old options
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
					} //update from 1.4
				} //-version equality check
			} //-else new suboption with the plugin version exists
		} //-new DB version not detected
	} //if current user can
}

## ===================================
## ### UNINSTALL FUNCTION
## ===================================

function apt_uninstall_plugin(){ //runs after uninstalling of the plugin -- also used for restoring settings
	delete_option('automatic_post_tagger');
	delete_option('automatic_post_tagger_keywords');
}

## ===================================
## ### ACTION + META LINKS
## ===================================

function apt_plugin_action_links($apt_action_links){
   $apt_action_links[] = '<a href="'. admin_url('options-general.php?page=automatic-post-tagger') .'">'. __('Settings') .'</a>';
   return $apt_action_links;
}

function apt_plugin_meta_links($apt_meta_links, $apt_file){
	global $apt_plugin_basename;

	if($apt_file == $apt_plugin_basename){
		$apt_meta_links[] = '<a href="http://wordpress.org/support/plugin/automatic-post-tagger">Support forum</a>';
		$apt_meta_links[] = '<a href="http://wordpress.org/plugins/automatic-post-tagger/faq">FAQ</a>';
		$apt_meta_links[] = '<a href="http://devtard.com/donate">Donate</a>';
	}
	return $apt_meta_links;
}

## ===================================
## ### MENU LINK
## ===================================

function apt_menu_link(){
	add_options_page('Automatic Post Tagger', 'Automatic Post Tagger', 'manage_options', 'automatic-post-tagger', 'apt_options_page');
}

## ===================================
## ### ADMIN NOTICES
## ===================================

function apt_plugin_admin_notices(){
	if(current_user_can('manage_options')){
		global $pagenow,
		$apt_message_html_prefix_updated,
		$apt_message_html_prefix_warning,
		$apt_message_html_prefix_note,
		$apt_message_html_suffix;

		$apt_settings = get_option('automatic_post_tagger');

		if($pagenow == 'options-general.php' AND isset($_GET['page']) AND $_GET['page'] == 'automatic-post-tagger'){ //check whether the user is on page options-general.php?page=automatic-post-tagger
			## ===================================
			## ### ACTIONS BASED ON GET DATA
			## ===================================

			## the following must be executed before other conditions; isset checks are required
			if($apt_settings['apt_admin_notice_install'] == 1){ //install note will appear after clicking the link or visiting the options page
				$apt_settings['apt_admin_notice_install'] = 0; //hide activation notice
				update_option('automatic_post_tagger', $apt_settings); //save settings

				echo $apt_message_html_prefix_note .'<strong>Note:</strong> Now you need to create or import keywords which will be used by the plugin to automatically tag posts while they are being published, inserted or saved.
					<ul class="apt_custom_list">
						<li><em>Keyword names</em> represent tags that will be added to posts when they or their <em>Related words</em> are found.</li>
						<li><strong>By default only newly published/inserted posts are automatically tagged.</strong> If you want to see the plugin in action when writing new posts or editing drafts, enable the option <em>Run APT when posts are: Saved</em> and add the post status "draft" to <em>Allowed post statuses</em>.</li>
						<li>You can also use the <em>Bulk tagging tool</em> to process all of your already existing posts.</li>
					</ul>'. $apt_message_html_suffix; //display quick info for beginners
			}

			## TODO: each version must have a unique update notice
			if($apt_settings['apt_admin_notice_update'] == 1){ //update note will appear after clicking the link or visiting the options page
				$apt_settings['apt_admin_notice_update'] = 0; //hide update notice
				update_option('automatic_post_tagger', $apt_settings); //save settings

				echo $apt_message_html_prefix_note .'<strong>What\'s new in APT v1.7?</strong>
					<ul class="apt_custom_list">
						<li><strong>Full automation</strong>: APT can now process posts inserted to the database via WP API (this is usually done by autoblogging plugins - RSS importers/aggregators).</li>
						<li>HTML entities in analyzed content, word separators and related words can be converted to their applicable characters.</li>
						<li>New option <em>Allowed post statuses</em> replaces previously used <em>Ignored post statuses</em>. Specified post statuses are now always being taken into account, not just when using the Bulk tagging tool.</li>
						<li>Configurable time delay between cycles when using the Bulk tagging tool.</li>
						<li>Terms imported from taxonomies can be now saved as related words (with term IDs saved as their keyword names). This is useful when the plugin is used to add categories to posts.</li>
						<li>And more - see the <a href="https://wordpress.org/plugins/automatic-post-tagger/changelog/">Changelog</a>.</li>
					</ul>

					<br />If something doesn\'t work, please try to <abbr title="You can use the &quot;Restore default settings&quot; button below">reinstall the plugin</abbr> first. You are always welcome to post new bug reports or your suggestions on the <a href="http://wordpress.org/support/plugin/automatic-post-tagger">support forum</a>.'. $apt_message_html_suffix;

				echo $apt_message_html_prefix_warning .'<strong>Do you like APT and want more frequent updates?</strong> Motivate the developer to speed up the plugin\'s development by <a href="https://www.patreon.com/devtard">becoming his patron on Patreon</a>!'. $apt_message_html_suffix;
			} //-update notice(s)
		} //-options page check

		## ===================================
		## ### ACTIONS BASED ON DB DATA
		## ===================================
		if($apt_settings['apt_admin_notice_install'] == 1){ //show link to the setting page after installing
			echo $apt_message_html_prefix_note .'<strong>Automatic Post Tagger</strong> has been installed. <a href="'. admin_url('options-general.php?page=automatic-post-tagger') .'">Set up the plugin &raquo;</a>'. $apt_message_html_suffix;
		}
		if($apt_settings['apt_admin_notice_update'] == 1){ //show link to the setting page after updating
			echo $apt_message_html_prefix_note .'<strong>Automatic Post Tagger</strong> has been updated to version <strong>'. $apt_settings['apt_plugin_version'] .'</strong>. <a href="'. admin_url('options-general.php?page=automatic-post-tagger') .'">Find out what\'s new &raquo;</a>'. $apt_message_html_suffix;
		}
	} //-if can manage options check
}

## ===================================
## ### JAVASCRIPT & CSS
## ===================================

function apt_load_meta_box_scripts(){ //load JS and CSS on the edit.php page
	global $apt_plugin_url;
	wp_enqueue_style('apt_style', $apt_plugin_url .'css/apt-style.css', false, apt_get_plugin_version()); //load CSS
	wp_enqueue_script('apt_meta_box_js', $apt_plugin_url . 'js/apt-meta-box.js', array('jquery'), apt_get_plugin_version()); //load JS (adding new keywords)
}

function apt_load_options_page_scripts(){ //load JS and CSS on the options page
	global $apt_plugin_url;
	wp_enqueue_style('apt_style', $apt_plugin_url .'css/apt-style.css', false, apt_get_plugin_version()); //load CSS
	wp_enqueue_script('apt_options_page_js', $apt_plugin_url . 'js/apt-options-page.js', array('jquery'), apt_get_plugin_version()); //load JS (changing the background, toggling widgets)
}


function apt_insert_ajax_nonce_meta_box(){ //nonce generation for AJAX - meta box
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

function apt_insert_ajax_nonce_options_page(){ //nonce generation for AJAX - options page
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

## ===================================
## ### WIDGETS + META BOX
## ===================================

function apt_change_widget_visibility($apt_widget_id){ //return HTML code hiding widgets
	global $apt_settings;

	if(in_array($apt_widget_id, $apt_settings['apt_hidden_widgets'])){
		return 'style="display: none;"';
	}
	else{
		return 'style="display: block;"';
	}
}

function apt_toggle_widget(){ //update widget visibility via AJAX
	$apt_settings = get_option('automatic_post_tagger');
	check_ajax_referer('apt_options_page_nonce', 'security');

	if(in_array($_POST['apt_widget_id'], $apt_settings['apt_hidden_widgets'])){ //is the widget ID in the array?
		unset($apt_settings['apt_hidden_widgets'][array_search($_POST['apt_widget_id'], $apt_settings['apt_hidden_widgets'])]); //the ID was found, remove it (that array_serach thing is there to determine which array key is assigned to the value)
		update_option('automatic_post_tagger', $apt_settings); //save settings
	}
	else{
 		array_push($apt_settings['apt_hidden_widgets'], $_POST['apt_widget_id']); //add the ID at the end of the array
		update_option('automatic_post_tagger', $apt_settings); //save settings
	}
	die; //the AJAX script has to die, otherwise it will return exit(0)
}

function apt_meta_box_create_new_keyword(){ //save keyword sent via meta box
	check_ajax_referer('apt_meta_box_nonce', 'security');
	apt_create_new_keyword($_POST['apt_box_keyword_name'],$_POST['apt_box_keyword_related_words']);
	die; //the AJAX script has to die, otherwise it will return exit(0)
}

function apt_meta_box_add(){ //add meta box
	add_meta_box('apt_meta_box','Automatic Post Tagger','apt_meta_box_content','post','side');
}

function apt_meta_box_content(){ //meta box content
	$apt_settings = get_option('automatic_post_tagger');
?>
	<p>
		Keyword name: <span class="apt_help" title="Keyword names represent tags that will be added to posts when they or their Related words are found. Example: &quot;cat&quot;">i</span>
		<input onkeypress="return apt_enter_submit(event);" type="text" id="apt_box_keyword_name" name="apt_box_keyword_name" value="" maxlength="5000" />
	</p>
	<p>
		Related words (separated by "<strong><?php echo htmlspecialchars($apt_settings['apt_string_separator']); ?></strong>"): <span class="apt_help" title="<?php echo 'Related words are optional. Example: &quot;cats'. $apt_settings['apt_string_separator'] .'kitty'. $apt_settings['apt_string_separator'] .'meo'. $apt_settings['apt_wildcard_character'] .'w&quot;.'; ?>">i</span>
		<input onkeypress="return apt_enter_submit(event);" type="text" id="apt_box_keyword_related_words" name="apt_box_keyword_related_words" value="" maxlength="5000" />
	</p>
	<p>
		<input class="button" type="button" id="apt_meta_box_create_new_keyword_button" value=" Create new keyword ">
	</p>

	<div id="apt_box_message"></div>
<?php
}

## =========================================================================
## ### KEYWORD MANAGEMENT FUNCTIONS
## =========================================================================

function apt_create_new_keyword($apt_raw_keyword_name, $apt_raw_related_words){
	global $wpdb,
	$apt_message_html_prefix_updated,
	$apt_message_html_prefix_error,
	$apt_message_html_prefix_warning,
	$apt_message_html_suffix;

	$apt_settings = get_option('automatic_post_tagger');
	$apt_keywords_array_new = get_option('automatic_post_tagger_keywords');

	if(empty($apt_raw_keyword_name)){ //checking if the value of the keyword name is empty
		echo $apt_message_html_prefix_error .'<strong>Error:</strong> You can\'t create a keyword that doesn\'t have a name.'. $apt_message_html_suffix;
	}
	else{
		//removing slashes and replacement of whitespace characters from beginning and end

		//user input adjustment
		if($apt_settings['apt_input_correction'] == 1){
			$apt_new_keyword = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $apt_raw_keyword_name); //replacing multiple whitespace characters with a space  (if there were, say two spaces between words, this will convert them to one)
			$apt_new_keyword = trim(stripslashes($apt_new_keyword)); //trimming slashes and whitespace characters
		} //-user input adjustment
		else{
			$apt_new_keyword = stripslashes($apt_raw_keyword_name);
		} //-else user input adjustment

		$apt_to_be_created_keyword_already_exists = 0; //variable for determining whether the keyword already exists
		foreach($apt_keywords_array_new as $apt_key){ //process all elements of the array
			if(strtolower($apt_key[1]) == strtolower($apt_raw_keyword_name)){ //checking if the strtolowered keyword exists
				echo $apt_message_html_prefix_error .'<strong>Error:</strong> The keyword "<strong>'. htmlspecialchars($apt_new_keyword) .'</strong>" couldn\'t be created, because it already exists.'. $apt_message_html_suffix;
				$apt_to_be_created_keyword_already_exists = 1;
				break; //stop the loop
			}
		} //-foreach

		if($apt_to_be_created_keyword_already_exists == 0){ //if the keyword doesn't exist, create one
			//user input adjustment
			if($apt_settings['apt_input_correction'] == 1){
				$apt_new_related_words = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $apt_raw_related_words); //replacing multiple whitespace characters with a space  (if there were, say two spaces between words, this will convert them to one)
				$apt_new_related_words = preg_replace('/'. preg_quote($apt_settings['apt_string_separator'], '/') .'{2,}/', $apt_settings['apt_string_separator'], $apt_new_related_words); //replacing multiple separators with one
				$apt_new_related_words = preg_replace('/'. preg_quote($apt_settings['apt_wildcard_character'], '/') .'{2,}/', $apt_settings['apt_wildcard_character'], $apt_new_related_words); //replacing multiple wildcards with one
				$apt_new_related_words = trim(trim(stripslashes($apt_new_related_words)), $apt_settings['apt_string_separator']); //removing slashes, trimming separators and whitespace characters from the beginning and the end
			} //-user input adjustment
			else{
				$apt_new_related_words = stripslashes($apt_raw_related_words); //removing slashes
			} //-else user input adjustment

			$apt_new_keyword_id = $apt_settings['apt_last_keyword_id']+1;

 			array_push($apt_keywords_array_new, array($apt_new_keyword_id, $apt_new_keyword, $apt_new_related_words)); //add id + the keyword + related words at the end of the array
			update_option('automatic_post_tagger_keywords', $apt_keywords_array_new); //save keywords - this line must be before the count function in order to display correct stats

			$apt_settings['apt_last_keyword_id'] = $apt_new_keyword_id;
			$apt_settings['apt_keywords_total'] = count($apt_keywords_array_new); //update stats
			update_option('automatic_post_tagger', $apt_settings); //save settings


			echo $apt_message_html_prefix_updated .'The keyword "<strong>'. htmlspecialchars($apt_new_keyword) .'</strong>" with '; //confirmation message displaying related words if available
				if(empty($apt_new_related_words)){
					echo 'no related words';
				}else{
					if(strstr($apt_new_related_words, $apt_settings['apt_string_separator'])){ //print single or plural form
						echo 'related words "<strong>'. htmlspecialchars($apt_new_related_words) .'</strong>"';
					}
					else{
						echo 'the related word "<strong>'. htmlspecialchars($apt_new_related_words) .'</strong>"';
					}
				}
			echo ' has been created.'. $apt_message_html_suffix;


			if($apt_settings['apt_warning_messages'] == 1){ //display warnings
				if(strstr($apt_new_related_words, ' '. $apt_settings['apt_string_separator']) OR strstr($apt_new_related_words, $apt_settings['apt_string_separator'] .' ')){ //mistake scenario
					echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> Related words "<strong>'. htmlspecialchars($apt_new_related_words) .'</strong>" contain extra spaces near string separators.'. $apt_message_html_suffix;
				}
				if(strstr($apt_new_related_words, $apt_settings['apt_wildcard_character']) AND ($apt_settings['apt_wildcards'] == 0)){ //mistake scenario
					echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> Related words "<strong>'. htmlspecialchars($apt_new_related_words) .'</strong>" contain a wildcard character, but wildcard support is currently disabled!'. $apt_message_html_suffix;
				}
			} //-if warnings allowed

		} //-else - existence in the db check
	} //-else - empty check
}

function apt_sort_keywords($a, $b){ //case insensitive string comparison of sub-array elements - keywords
	return strnatcasecmp($a[1], $b[1]);
}

function apt_print_sql_where_without_specified_statuses(){ //this prints part of a SQL command that is used for retrieving post IDs for bulk tagging - it returns IDs of posts with specified post statuses
	global $wpdb;

	$apt_settings = get_option('automatic_post_tagger');

	//if no post statuses are set, don't add them to the SQL query
	if(empty($apt_settings['apt_post_statuses'])){
		return "WHERE 1=0 "; //disable any further changes, as there are no allowed post types.
	}
	else{
		$apt_post_statuses_escaped = ''; //this is here to prevent the notice "Undefined variable"

		//adding all post statuses to a variable
		foreach($apt_settings['apt_post_statuses'] as $apt_post_status){
			$apt_post_statuses_escaped .= $wpdb->prepare('%s', $apt_post_status) . ','; //add array values to a string and separate them by a comma
		}

		//now we need to remove the last "," part from the end of the string
		$apt_post_statuses_escaped_sql = substr($apt_post_statuses_escaped, 0, -1);
	}

	if(empty($apt_settings['apt_post_types'])){
		return "WHERE 1=0 "; //disable any further changes, as there are no allowed post types.
	}
	else{
		$apt_post_types_escaped = ''; //this is here to prevent the notice "Undefined variable"

		//adding all post types to a variable
		foreach($apt_settings['apt_post_types'] as $apt_post_type){
			$apt_post_types_escaped .= $wpdb->prepare('%s', $apt_post_type) . ','; //add array values to a string and separate them by a comma
		}

		//now we need to remove the last "," part from the end of the string
		$apt_post_types_escaped_sql = substr($apt_post_types_escaped, 0, -1);

		//get all IDs with set post statuses and types
	}

	return 'WHERE post_type IN ('. $apt_post_types_escaped_sql .') AND post_status IN ('. $apt_post_statuses_escaped_sql .')';
}

function apt_get_backup_file_name(){ //provide unique name of a new backup file
	global $apt_new_backup_file_name_prefix,
	$apt_new_backup_file_name_suffix,
	$apt_backup_dir_rel_path;

	//check whether the backup directory exists
	if(is_dir($apt_backup_dir_rel_path)){
		$apt_file_permissions = intval(substr(sprintf('%o', fileperms($apt_backup_dir_rel_path)), -4));
		if($apt_file_permissions != 755){ //check whether the directory permissions aren't 755
			@chmod($apt_backup_dir_rel_path, 0755); //change permissions
		} //permissions lower than X
	} //directory exists
	else{ //directory doesn't exist
		@mkdir($apt_backup_dir_rel_path, 0755); //create the directory
	}

	if(chdir($apt_backup_dir_rel_path)){ //continue only if the current directory can be changed to the backup directory
		$apt_existing_backup_files = glob($apt_new_backup_file_name_prefix .'*'. $apt_new_backup_file_name_suffix);
		$apt_existing_backup_files_count = count($apt_existing_backup_files);

		//check only if some backup files exist
		if($apt_existing_backup_files_count > 0){
			$apt_existing_file_ids = array();

			//extract the number from the file name and push it to an array
			foreach($apt_existing_backup_files as $apt_backup_file_name){
				if(preg_match('/\d+/', $apt_backup_file_name, $apt_current_file_id)){ //extract the numeric ID from a file if it exists
					array_push($apt_existing_file_ids, $apt_current_file_id[0]); //add the ID to the array
				}
			} //-foreach

			//assign the [0] index to the the highest ID
			array_multisort($apt_existing_file_ids, SORT_NUMERIC, SORT_DESC);

			//generate new id
			$apt_new_file_id = $apt_existing_file_ids[0]+1; 
		} //-some backup files currently exist
		else{ 
			$apt_new_file_id = 1; //the ID of the new backup file is 1
		}

		$apt_new_backup_file_name = $apt_new_backup_file_name_prefix .'-'. $apt_new_file_id . $apt_new_backup_file_name_suffix;

		return $apt_new_backup_file_name;
	} //-directory can be changed
	else{
		return false; //if the directory can't be changed, return false
	}
}

function apt_export_keywords($apt_print_messages = 1){
	global $apt_message_html_prefix_error,
	$apt_message_html_prefix_updated,
	$apt_message_html_suffix,
	$apt_backup_dir_rel_path,
	$apt_backup_dir_abs_path,
	$apt_new_backup_file_name_prefix,
	$apt_new_backup_file_name_suffix;

	$apt_settings = get_option('automatic_post_tagger');

	//continue only if the max number of backups is greater than zero
	if($apt_settings['apt_stored_backups'] != 0){
		//continue only when there are keywords in the database
		if($apt_settings['apt_keywords_total'] != 0){
			$apt_new_backup_file_name = apt_get_backup_file_name();

			if($apt_new_backup_file_name != false){ //continue only if the new name was successfully generated
				$apt_new_backup_file_rel_path = $apt_backup_dir_rel_path . $apt_new_backup_file_name;
				$apt_new_backup_file_abs_path = $apt_backup_dir_abs_path . $apt_new_backup_file_name;

				if(fopen($apt_new_backup_file_rel_path, 'w')){ //check whether the file can be created, otherwise do not continue
					if(get_option('automatic_post_tagger_keywords') != false){ //continue only if the option exists
						$apt_keywords_array = get_option('automatic_post_tagger_keywords');
						usort($apt_keywords_array, 'apt_sort_keywords'); //sort keywords by their name
						$apt_backup_file_fopen = fopen($apt_new_backup_file_rel_path, 'w');

						foreach($apt_keywords_array as $apt_row){
							unset($apt_row[0]); //remove the ID because we don't want to export it
							fputcsv($apt_backup_file_fopen, $apt_row);
						}
						fclose($apt_backup_file_fopen);

						## DELETION of BACKUPS - if the number of generated backups is higher than a specified amount, delete the old one(s)
						chdir($apt_backup_dir_rel_path); //change directory to the backup directory
						$apt_existing_backup_files = glob($apt_new_backup_file_name_prefix .'*'. $apt_new_backup_file_name_suffix); //find files with the specified prefix and suffix

						if(count($apt_existing_backup_files) > $apt_settings['apt_stored_backups']){ //continue if there are more backups than the specified number
							//sort the array of files drom the oldest one
							array_multisort(array_map('filemtime', $apt_existing_backup_files), SORT_NUMERIC, SORT_ASC, $apt_existing_backup_files);

							$apt_extra_old_files = count($apt_existing_backup_files) - $apt_settings['apt_stored_backups'];

							//this loop will remove all extra old files
							for($i = 0; $apt_extra_old_files != 0; $i++){
								//delete the item which should be the oldest one
								unlink($apt_backup_dir_rel_path . $apt_existing_backup_files[$i]);

								//decrease the number of extra old files by 1
								$apt_extra_old_files--;
							} //-for
						} //-if more than X backups

						if($apt_print_messages == 1){ //continue only if messages can be printed
							if(file_exists($apt_new_backup_file_rel_path)){ //check whether the created backup file actually exists
								echo $apt_message_html_prefix_updated .'Export complete. <a href="'. $apt_new_backup_file_abs_path .'">Download the CSV file &raquo;</a>'. $apt_message_html_suffix;
							}
							else{
								echo $apt_message_html_prefix_error .'<strong>Error:</strong> The CSV file that should have been created doesn\'t seem to exist.'. $apt_message_html_suffix;
							}
						} //-if messages can be printed
					} //-if option exists
					else{ //option doesn't exist
						if($apt_print_messages == 1){ //continue only if messages can be printed
							echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "automatic_post_tagger_keywords" doesn\'t exist.'. $apt_message_html_suffix;
						} //-if messages can be printed
					} //-else option exists

				} //-backup file can be created
				else{
					if($apt_print_messages == 1){ //continue only if messages can be printed
						echo $apt_message_html_prefix_error .'<strong>Error:</strong> The CSV file couldn\'t be created because of insufficient permissions (which prevent the plugin from creating the file).'. $apt_message_html_suffix;
					} //-if messages can be printed
				} //backup file can't be created
			} //-backup file name can be generated
			else{
				if($apt_print_messages == 1){ //continue only if messages can be printed
					echo $apt_message_html_prefix_error .'<strong>Error:</strong> The CSV file couldn\'t be created because of insufficient permissions (which prevent the plugin from generating a new name for the file).'. $apt_message_html_suffix;
				} //-if messages can be printed
			} //-backup file name can't be generated
		} //-some keywords exist
		else{ //no keywords exist
			if($apt_print_messages == 1){ //continue only if messages can be printed
				echo $apt_message_html_prefix_error .'<strong>Error:</strong> The CSV file couldn\'t be created, because there aren\'t any keywords in the database.'. $apt_message_html_suffix;
			} //-if messages can be printed
		} //-else keywords exixt
	} //if max number of backups is greater than zero
	else{ //plugin not allowed to export keywords
		if($apt_print_messages == 1){ //continue only if messages can be printed
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The plugin isn\'t allowed to export keywords.'. $apt_message_html_suffix;
		}
	} //-else max number of backups is greater than zero
}

function apt_export_keywords_to_textarea(){ //prints keywords in CSV format
	$apt_keywords_array = get_option('automatic_post_tagger_keywords');
	usort($apt_keywords_array, 'apt_sort_keywords'); //sort keywords by their name

    ob_start(); //turn on buffering
    $apt_csv_output = fopen('php://output', 'w');

	foreach($apt_keywords_array as $apt_row){
		unset($apt_row[0]); //remove the ID because we don't want to export it
		fputcsv($apt_csv_output, $apt_row);
	}

	fclose($apt_csv_output);
	return htmlspecialchars(ob_get_clean()); //return htmlspecialcharsed buffer contents
}

function apt_import_keywords_from_textarea($apt_imported_keywords){ //imports keywords from textarea
	global $apt_settings,
	$apt_message_html_prefix_updated,
	$apt_message_html_suffix,
	$apt_message_html_prefix_warning;

	$apt_imported_keywords_array = str_getcsv(stripslashes($apt_imported_keywords), "\n"); //remove backslashes and turn the string into an array; we are using str_getcsv instead of explode - it's better according to the manual
	$apt_keywords_array_new = array(); //all keywords will be saved into this variable
	$apt_new_keyword_id = $apt_settings['apt_last_keyword_id']; //the id value MUST NOT be increased here - it is increased in the loop

	foreach($apt_imported_keywords_array as $apt_csv_row){
		$apt_csv_row = str_getcsv($apt_csv_row);

		if(!empty($apt_csv_row[0])){ //mistake scenario check - don't save if the keyword name is empty

			$apt_to_be_saved_keyword_already_exists = 0; //variable for determining whether keyword exists
			foreach($apt_keywords_array_new as $apt_key){ //process all elements of the array
				if(strtolower($apt_key[1]) == strtolower($apt_csv_row[0])){ //checking if the strtolowered keyword already exists in the array
					$apt_to_be_saved_keyword_already_exists = 1;
					break; //stop the loop
				}
			} //-foreach

			if($apt_to_be_saved_keyword_already_exists == 0){ //add keyword only if it isn't already in the array
				$apt_new_keyword_id++; //increase the id value
				array_push($apt_keywords_array_new, array($apt_new_keyword_id, $apt_csv_row[0], $apt_csv_row[1]));

				if(!empty($apt_csv_row[1])){
					if($apt_settings['apt_warning_messages'] == 1){ //display warnings
						if(strstr($apt_csv_row[1], ' '. $apt_settings['apt_string_separator']) OR strstr($apt_csv_row[1], $apt_settings['apt_string_separator'] .' ')){
							$apt_imported_related_words_extra_spaces_warning = 1;
						}
						if(strstr($apt_csv_row[1], $apt_settings['apt_wildcard_character']) AND ($apt_settings['apt_wildcards'] == 0)){
							$apt_imported_related_words_wildcard_warning = 1;
						}
					} //-if warnings allowed
				} //-if related words not empty
			} //-if keyword isn't already in the array
		} //-if not empty
	} //-foreach

	update_option('automatic_post_tagger_keywords', $apt_keywords_array_new); //save keywords
	$apt_settings['apt_last_keyword_id'] = $apt_new_keyword_id; //save newest last id value
	$apt_settings['apt_keywords_total'] = count($apt_keywords_array_new); //save the number of current keywords
	update_option('automatic_post_tagger', $apt_settings); //save settings

	echo $apt_message_html_prefix_updated .'Keywords have been saved.'. $apt_message_html_suffix;

	if($apt_settings['apt_warning_messages'] == 1){ //display warnings
		if(isset($apt_imported_related_words_wildcard_warning) AND $apt_imported_related_words_wildcard_warning == 1){
			echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> Your related words contain the wildcard character, but wildcard support is currently disabled!'. $apt_message_html_suffix;
		}
		if(isset($apt_imported_related_words_extra_spaces_warning) AND $apt_imported_related_words_extra_spaces_warning == 1){
			echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> Some related words contain extra spaces near string separators.'. $apt_message_html_suffix;
		}
	} //-if warnings allowed
}

function apt_bulk_tagging(){ //adds tags to multiple posts
	global $apt_message_html_prefix_note,
	$apt_message_html_suffix;

	$apt_settings = get_option('automatic_post_tagger');
	$apt_ids_for_dosage_bulk_tagging_array_sliced = array_slice($apt_settings['apt_bulk_tagging_queue'], 0, $apt_settings['apt_bulk_tagging_posts_per_cycle']); //get first X elements from the array

	echo '<!-- Automatic Post Tagger -->';
	echo $apt_message_html_prefix_note .'<strong>Note:</strong> Bulk tagging is currently in progress. This may take some time.'. $apt_message_html_suffix;
	echo '<ul class="apt_custom_list">';

	//determine the number of already processed posts
	if(isset($_GET['pp'])){
		$apt_number_of_already_processed_posts = $_GET['pp'];
	}
	else{
		$apt_number_of_already_processed_posts = 0;
	}

	//determine the number of total tags added to posts
	if(isset($_GET['tt'])){
		$apt_number_of_added_tags_total = $_GET['tt'];
	}
	else{
		$apt_number_of_added_tags_total = 0;
	}

	//determine the number of affected posts
	if(isset($_GET['ap'])){
		$apt_number_of_affected_posts = $_GET['ap'];
	}
	else{
		$apt_number_of_affected_posts = 0;
	}

	//run loop to process selected number of posts from the range
	foreach($apt_ids_for_dosage_bulk_tagging_array_sliced as $apt_post_id){
		$apt_number_of_added_tags = apt_single_post_tagging($apt_post_id, 1, 1); //send the current post ID + send '1' to let the function know that we do not want to check mistake scenarios again + send 1 to return number of added tags
		$apt_number_of_added_tags_total += $apt_number_of_added_tags; //add up currently assigned tags to the variable
		$apt_number_of_already_processed_posts++; //increase the number of processed posts

		if($apt_number_of_added_tags != 0){
			$apt_number_of_affected_posts++;
		}

		unset($apt_settings['apt_bulk_tagging_queue'][array_search($apt_post_id, $apt_settings['apt_bulk_tagging_queue'])]); //remove the id from the array
		echo '<li><a href="'. admin_url('post.php?post='. $apt_post_id .'&action=edit') .'">Post ID '. $apt_post_id .'</a>: '. $apt_number_of_added_tags .' tags added</li>';
	}

	echo '</ul>';
	echo '<p><strong>Already processed posts:</strong> '. $apt_number_of_already_processed_posts .'<br />';
	echo '<strong>Tags added to posts:</strong> '. $apt_number_of_added_tags_total .'<br />';
	echo '<strong>Affected posts:</strong> '. $apt_number_of_affected_posts .'</p>';
	echo '<p><strong>Posts in the queue:</strong> '. count($apt_settings['apt_bulk_tagging_queue']) .'</p>';
	echo '<!-- //-Automatic Post Tagger -->';

	//save remaining IDs to the option
	update_option('automatic_post_tagger', $apt_settings); //save settings

	//if there are not any IDs in the queue, redirect the user to a normal page
	if(empty($apt_settings['apt_bulk_tagging_queue'])){
		echo '<!-- Automatic Post Tagger (no post IDs in the queue) -->';
		echo '<p><small>This page should be automatically refreshed in '. $apt_settings['apt_bulk_tagging_delay'] .' seconds. <a href="'. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=0&pp='. $apt_number_of_already_processed_posts .'&tt='. $apt_number_of_added_tags_total .'&ap='. $apt_number_of_affected_posts), 'apt_bulk_tagging_0_nonce') .'">Click here if that doesn\'t happen &raquo;</a></small></p>'; //display an alternative link if methods below fail
		echo '<script type="text/javascript">setTimeout(function(){window.location.replace("'. str_replace('&amp;', '&', wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=0&pp='. $apt_number_of_already_processed_posts .'&tt='. $apt_number_of_added_tags_total .'&ap='. $apt_number_of_affected_posts), 'apt_bulk_tagging_0_nonce')) .'")}, '. $apt_settings['apt_bulk_tagging_delay']*1000 .')</script>'; //the str_replace function is here because the wp_nonce_url function provides &amp; instead of &, so I need to replace it or the web browser won't redirect anything; the number of seconds has to be multiplied by 1000 here
		echo '<noscript><meta http-equiv="refresh" content="'. $apt_settings['apt_bulk_tagging_delay'] .';url='. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=0&pp='. $apt_number_of_already_processed_posts .'&tt='. $apt_number_of_added_tags_total .'&ap='. $apt_number_of_affected_posts), 'apt_bulk_tagging_0_nonce') .'"></noscript>'; //if JS is disabled, use the meta tag
		echo '<!-- //-Automatic Post Tagger -->';
		exit;
	}
	else{ //if there are still some IDs in the queue, redirect to the same page (and continue tagging)
		echo '<!-- Automatic Post Tagger (some post IDs in the queue) -->';
		echo '<p><small>This page should be automatically refreshed in '. $apt_settings['apt_bulk_tagging_delay'] .' seconds. <a href="'. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=1&pp='. $apt_number_of_already_processed_posts .'&tt='. $apt_number_of_added_tags_total .'&ap='. $apt_number_of_affected_posts), 'apt_bulk_tagging_1_nonce') .'">Click here if that doesn\'t happen &raquo;</a></small></p>'; //display an alternative link if methods below fail
		echo '<script type="text/javascript">setTimeout(function(){window.location.replace("'. str_replace('&amp;', '&', wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=1&pp='. $apt_number_of_already_processed_posts .'&tt='. $apt_number_of_added_tags_total .'&ap='. $apt_number_of_affected_posts), 'apt_bulk_tagging_1_nonce')) .'")}, '. $apt_settings['apt_bulk_tagging_delay']*1000 .')</script>'; //the str_replace function is here because the wp_nonce_url function provides &amp; instead of &, so I need to replace it or the web browser won't redirect anything; the number of seconds has to be multiplied by 1000 here
		echo '<noscript><meta http-equiv="refresh" content="'. $apt_settings['apt_bulk_tagging_delay'] .';url='. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=1&pp='. $apt_number_of_already_processed_posts .'&tt='. $apt_number_of_added_tags_total .'&ap='. $apt_number_of_affected_posts), 'apt_bulk_tagging_1_nonce') .'"></noscript>'; //if JS is disabled, use the meta tag
		echo '<!-- //-Automatic Post Tagger -->';
		exit;
	}
}

function apt_single_post_tagging($apt_post_id, $apt_dont_check_mistake_scenarios = 0, $apt_return_number_of_added_tags = 0){ //this function is for adding tags to only one post
	global $wpdb;

	$apt_settings = get_option('automatic_post_tagger');
	$apt_post_current_tags = wp_get_post_terms($apt_post_id, $apt_settings['apt_taxonomy_name'], array('fields' => 'names'));
	$apt_post_current_tag_count = count($apt_post_current_tags);

	#################################################################
	//stopping execution to prevent the function from doing unuseful job:

	//we do not have the ID of the post, stop!
	if ($apt_post_id == false OR $apt_post_id == null){
		return 1;
	}
	//the specified taxonomy doesn't exist, stop!
	if(!taxonomy_exists($apt_settings['apt_taxonomy_name'])){
		return 9;
	}
	//the current post type isn't allowed, stop!
	if(!in_array(get_post_type($apt_post_id), $apt_settings['apt_post_types'])){
		return 8;
	}
	//the current post status isn't allowed, stop!
	if(!in_array(get_post_status($apt_post_id), $apt_settings['apt_post_statuses'])){
		return 10;
	}
	//the user does not want us to add tags if the post already has some tags, stop!
	if(($apt_post_current_tag_count > 0) AND $apt_settings['apt_old_tags_handling'] == 3){
		return 2;
	}
	//number of current tags is the same or greater than the maximum so we can't append tags, stop! (replacement is ok, 3rd option won't be let here)
	if(($apt_post_current_tag_count >= $apt_settings['apt_tag_limit']) AND $apt_settings['apt_old_tags_handling'] == 1){
		return 3;
	}

	if($apt_dont_check_mistake_scenarios == 0){ //if we got a second parameter != 0, don't check mistake scenarios again - useful for bulk tagging
		### mistake SCENARIOS
		//the user does not want to add any tags, stop!
		if($apt_settings['apt_tag_limit'] <= 0){
			return 4;
		}
		//there are no keywords in the DB, stop!
		if($apt_settings['apt_keywords_total'] == 0){
			return 5;
		}
		//the user does not want us to search anything, stop!
		if(($apt_settings['apt_title'] == 0 AND $apt_settings['apt_content'] == 0 AND $apt_settings['apt_excerpt'] == 0) OR ($apt_settings['apt_search_for_keyword_names'] == 0 AND $apt_settings['apt_search_for_related_words'] == 0)){
			return 6;
		}
		//the user does want us to process 0 characters, stop!
		if($apt_settings['apt_substring_analysis'] == 1 AND $apt_settings['apt_substring_analysis_length'] == 0){
			return 7;
		}
	} //-mistake checks
	#################################################################

	$apt_haystack_string = '';

	//we need to find out what APT should analyze
	if($apt_settings['apt_title'] == 1){ //include title
		$apt_post_title = $wpdb->get_var("SELECT post_title FROM $wpdb->posts WHERE ID = $apt_post_id LIMIT 1");
		$apt_haystack_string = $apt_haystack_string .' '. $apt_post_title;
	}
	if($apt_settings['apt_content'] == 1){ //include content
		$apt_post_content = $wpdb->get_var("SELECT post_content FROM $wpdb->posts WHERE ID = $apt_post_id LIMIT 1");
		$apt_haystack_string = $apt_haystack_string .' '. $apt_post_content;
	}
	if($apt_settings['apt_excerpt'] == 1){ //include excerpt
		$apt_post_excerpt = $wpdb->get_var("SELECT post_excerpt FROM $wpdb->posts WHERE ID = $apt_post_id LIMIT 1");
		$apt_haystack_string = $apt_haystack_string .' '. $apt_post_excerpt;
	}
	if($apt_settings['apt_substring_analysis'] == 1){ //analyze only a part of the post
		$apt_haystack_string = substr($apt_haystack_string, $apt_settings['apt_substring_analysis_start'], $apt_settings['apt_substring_analysis_length']);
	}

	//preparing the string for searching
	if($apt_settings['apt_ignore_case'] == 1){
		$apt_haystack_string = strtolower($apt_haystack_string); //make it lowercase
	}
	if($apt_settings['apt_strip_tags'] == 1){
		$apt_haystack_string = wp_strip_all_tags($apt_haystack_string); //remove HTML, PHP and JS tags
	}
	if($apt_settings['apt_decode_html_entities_analyzed_content'] == 1){
		$apt_haystack_string = html_entity_decode($apt_haystack_string); //decode HTML entities
	}
	if($apt_settings['apt_replace_nonalphanumeric'] == 1){
		$apt_haystack_string = preg_replace('/[^a-zA-Z0-9]/', ' ', $apt_haystack_string); //replace all non-alphanumeric-characters with spaces
	}
	if($apt_settings['apt_replace_whitespaces'] == 1){
		$apt_haystack_string = preg_replace('/\s/', ' ', $apt_haystack_string); //replace whitespace characters with a space
	}

	$apt_haystack_string = ' '. $apt_haystack_string .' '; //we need to add a space before and after the string: the engine is looking for ' string ' (with space at the beginning and the end, so it won't find e.g. ' ice ' in a word ' iceman ')
	$apt_found_keywords_to_be_added_array = array(); //array of tags that will be added to a post


	//determine if we should calculate the number of max. tags for a post - only when appending tags
	if($apt_settings['apt_old_tags_handling'] == 1){
		$apt_tags_to_add_max = $apt_settings['apt_tag_limit'] - $apt_post_current_tag_count;
	}
	else{
		$apt_tags_to_add_max = $apt_settings['apt_tag_limit'];
	}

//die($apt_haystack_string); //for debugging
//die(var_dump($apt_settings['apt_word_separators'])); //for debugging

	if(!empty($apt_settings['apt_word_separators'])){ //continue only if separators are set
		$apt_word_separators_separated = '';

		//generate a string of WORD SEPARATORS separated by "|"
		foreach($apt_settings['apt_word_separators'] as $apt_word_separator){
			if($apt_settings['apt_decode_html_entities_word_separators'] == 1){ //html_entity_decode turns every HTML entity into applicable characters
				$apt_word_separators_separated .= preg_quote(html_entity_decode($apt_word_separator), '/') .'|'; //add "|" ("OR") between the letters, escaping those characters needing escaping
			}
			else{
				$apt_word_separators_separated .= preg_quote($apt_word_separator, '/') .'|'; //add "|" ("OR") between the letters, escaping those characters needing escaping
			}
		} //-foreach
		$apt_word_separators_separated = substr($apt_word_separators_separated, 0, -1); //remove the last extra "|" character
//die($apt_word_separators_separated); //for debugging
	} //-if separators set

	//this variable is below all the previous conditions to avoid loading keywords to memory when it's unnecessary
	$apt_keywords_array = get_option('automatic_post_tagger_keywords'); 

	## SEARCH FOR A SINGLE KEYWORD AND ITS RELATED WORDS
	foreach($apt_keywords_array as $apt_keyword_array_value){ //loop handling every keyword in the DB
		//resetting variables - this must not be omitted
		$apt_keyword_found = 0;
		$apt_related_words_found = 0;

		if($apt_settings['apt_search_for_related_words'] == 1){ //search for related words only
			## RELATED WORDS
			$apt_keyword_array_related_words_count = substr_count($apt_keyword_array_value[2], $apt_settings['apt_string_separator']) + 1; //this variable stores the number of related words in the current row that is being "browsed" by the while loop; must be +1 higher than the number of separators!

			if(!empty($apt_keyword_array_value[2])){ //if there are not any related words, do not continue

				$apt_keyword_array_value_substrings = explode($apt_settings['apt_string_separator'], $apt_keyword_array_value[2]); //create an array with related words divided by separators
				for($i=0; $i < $apt_keyword_array_related_words_count; $i++){ //loop handling substrings in the 'related_words' column - $i must be 0 because arrays always begin with 0!

					## preparing the substring needle for search (note: HTML tags in needles are not being stripped)
					$apt_substring_needle = $apt_keyword_array_value_substrings[$i];
					$apt_substring_wildcard = $apt_settings['apt_wildcard_character'];

					if($apt_settings['apt_decode_html_entities_related_words'] == 1){
						$apt_substring_needle = html_entity_decode($apt_substring_needle);
					}

					//lowercase strings
					if($apt_settings['apt_ignore_case'] == 1){
						$apt_substring_needle = strtolower($apt_substring_needle);
						$apt_substring_wildcard = strtolower($apt_settings['apt_wildcard_character']);
					}

					if($apt_settings['apt_replace_nonalphanumeric'] == 1){
						if($apt_settings['apt_dont_replace_wildcards'] == 1){ //don't replace wildcards
							$apt_substring_needle = preg_replace('/[^a-zA-Z0-9'. preg_quote($apt_substring_wildcard, '/') .']/', ' ', $apt_substring_needle);
						}
						else{ //wildcards won't work
							$apt_substring_needle = preg_replace('/[^a-zA-Z0-9]/', ' ', $apt_substring_needle); //replace all non-alphanumeric characters with spaces
						}
					}

					if($apt_settings['apt_replace_whitespaces'] == 1){
						$apt_substring_needle = preg_replace('/\s/', ' ', $apt_substring_needle); //replace whitespace characters with spaces
					}

					if($apt_settings['apt_wildcards'] == 1){
						$apt_wildcard_prepared = preg_quote($apt_substring_wildcard, '/'); //preg_quote the wildcard
						$apt_substring_needle = preg_quote($apt_substring_needle, '/'); //preg_quote regex characters
						$apt_substring_needle = str_replace($apt_wildcard_prepared, $apt_substring_wildcard, $apt_substring_needle); //replace the preg_quoted wildcard with original character (backslashes must be removed because the wildcard will be replaced with the regex pattern - without this it wouldn't work!)
						$apt_substring_needle_wildcards = str_replace($apt_substring_wildcard, $apt_settings['apt_wildcard_regex'], $apt_substring_needle); //replace a wildcard with user-defined regex
					}
					else{
						$apt_substring_needle = preg_quote($apt_substring_needle, '/'); //preg_quote regex characters
					}

					## SEPARATORS SET BY USER
					if(!empty($apt_settings['apt_word_separators']) AND $apt_settings['apt_replace_nonalphanumeric'] == 0){ //continue only if separators are set AND the use does NOT want to replace non-alphanumeric characters with spaces
						//wildcard search for related words
						if($apt_settings['apt_wildcards'] == 1){ //run if wildcards are allowed
							$apt_substring_needle_final = '/('. $apt_word_separators_separated .')'. $apt_substring_needle_wildcards .'('. $apt_word_separators_separated .')/';

							if(preg_match($apt_substring_needle_final, $apt_haystack_string)){ //'XsubstringX' found
								$apt_related_words_found = 1; //set variable to 1
//die("substring '". $apt_substring_needle_final ."' found with separators '". $apt_word_separators_separated .'\''); //for debugging
							}

						} //-wildcards allowed
						else{ //if wildcards are not allowed, continue searching without using a regular expression
							$apt_substring_needle_final = '/('. $apt_word_separators_separated .')'. $apt_substring_needle .'('. $apt_word_separators_separated .')/';

							if(preg_match($apt_substring_needle_final, $apt_haystack_string)){ //'XsubstringX' found
								$apt_related_words_found = 1; //set variable to 1
							}
						} //-else wildcard search

					} //-if separators are set OR non-alphanumeric searching is disabled
					## SPACE SEPARATORS
					else{ //if no separators are set OR the user does want to replace non-alphanumeric characters with spaces, continue searching (needles with spaces before and after every keyword)
						//wildcard search for related words
						if($apt_settings['apt_wildcards'] == 1){ //run if wildcards are allowed
							$apt_substring_needle_final = '/ '. $apt_substring_needle_wildcards .' /';

							if(preg_match($apt_substring_needle_final, $apt_haystack_string)){
								$apt_related_words_found = 1; //set variable to 1
							}
						} //-wildcards allowed
						else{ //if wildcards are not allowed, continue searching without using a regular expression
							$apt_substring_needle_final = ' '. $apt_substring_needle .' '; //add separators - spaces

							if(strstr($apt_haystack_string, $apt_substring_needle_final)){ //' substring ' found
								$apt_related_words_found = 1; //set variable to 1
							}
						} //-if wildcard check
					} //-else - no separators
				} //-for
			} //-if for related words check
		} //if the user wants to search for related words

//die("keyword found: ".$apt_related_words_found ."<br /><br />needle: ". $apt_substring_needle_final ."<br /><br />text:<br /><br />". $apt_haystack_string ); //for debugging

		if($apt_settings['apt_search_for_keyword_names'] == 1){ //search for keyword names only
			## KEYWORD NAMES
			if($apt_related_words_found == 0){ //search for keywords ONLY when NO related words were found
//die("no substring was found, now we search for keyword names"); //for debugging

				## preparing the needle for search (note: HTML tags in needles are not being stripped)
				$apt_keyword_needle = $apt_keyword_array_value[1];

				if($apt_settings['apt_ignore_case'] == 1){
					$apt_keyword_needle = strtolower($apt_keyword_needle); //make it lowercase
				}
				if($apt_settings['apt_replace_nonalphanumeric'] == 1){
					$apt_keyword_needle = preg_replace('/[^a-zA-Z0-9]/', ' ', $apt_keyword_needle); //replace all non-alphanumeric-characters with spaces
				}
				if($apt_settings['apt_replace_whitespaces'] == 1){
					$apt_keyword_needle = preg_replace('/\s/', ' ', $apt_keyword_needle); //replace whitespace characters with spaces
				}

				## SEPARATORS SET BY USER
				if(!empty($apt_settings['apt_word_separators']) AND $apt_settings['apt_replace_nonalphanumeric'] == 0){ //continue only if separators are set AND the use does NOT want to replace non-alphanumeric characters with spaces
					$apt_keyword_needle_final = '/('. $apt_word_separators_separated .')'. preg_quote($apt_keyword_needle, '/') .'('. $apt_word_separators_separated .')/';

					if(preg_match($apt_keyword_needle_final, $apt_haystack_string)){ //'XtagX' found
						$apt_keyword_found = 1; //set variable to 1
//die("keywords '". $apt_keyword_needle ."' found with separators '". print_r($apt_settings['apt_word_separators']) .'\'<br /><br />analyzed content: <br /><br />'. $apt_haystack_string); //for debugging
					}
				} //-if separators are set ANd non-alphanumeric searching disabled
				## SPACE SEPARATORS
				else{ //if no separators are set OR the use does want to replace non-alphanumeric characters with spaces, continue searching (needles with spaces before and after every keyword)
					$apt_keyword_needle_final = ' '. $apt_keyword_needle .' '; //add separators - spaces

					if(strstr($apt_haystack_string, $apt_keyword_needle_final)){ //' keyword ' found
						$apt_keyword_found = 1; //set variable to 1
					}
				} //-else - no separators
			} //-look for keywords if no related words were found
		} //if the user wants to search for keyword names


//die("keyword: ". $apt_keyword_array_value[1] ."<br />needle: ". $apt_keyword_needle); //for debugging

		## ADDING FOUND KEYWORDS TO AN ARRAY
		if($apt_related_words_found == 1 OR $apt_keyword_found == 1){ //keyword or one of related_words found, add the keyword to array!
//die("keyword: ". $apt_keyword_array_value[1] ."<br />rw found: ".$apt_related_words_found ."<br /> keyword found: ".  $apt_keyword_found); //for debugging

			//we need to check whether the keyword isn't already in the array of the current tags (don't worry about the temporary array for adding tags, only unique values are pushed in)	
			if($apt_settings['apt_old_tags_handling'] == 2 OR $apt_post_current_tag_count == 0){ //if we want to replace tags, we don't need to check whether the tag is already added to a post (it will be added again after deleting the old tags if it's found)
				array_push($apt_found_keywords_to_be_added_array, $apt_keyword_array_value[1]); //add keyword to the array

//die("keyword:". $apt_keyword_array_value[1] ."<br />current tags: ". print_r($apt_found_keywords_to_be_added_array, true)); //for debugging
			}
			else{ //if we are appending tags, avoid adding duplicate items to the array by checking whether they're already there
				if(in_array($apt_keyword_array_value[1], $apt_post_current_tags) == false){
					array_push($apt_found_keywords_to_be_added_array, $apt_keyword_array_value[1]); //add keyword to the arrayonly if it isn't already there
				}
			}
		} //-if keyword found

//die("keyword:". $apt_keyword_needle ."<br />rw needle: ". $apt_substring_needle ."<br />rw found: ". $apt_related_words_found."<br />kexword found: " .$apt_keyword_found); //for debugging

		if(count($apt_found_keywords_to_be_added_array) == $apt_tags_to_add_max){ //check whether the array is equal to the max. number of tags per one post, break the loop
			break; //stop the loop, the max. number of tags was reached
		}
	} //-foreach

//die("max: ".$apt_settings['apt_tag_limit'] ."<br />current tags: ". $apt_post_current_tag_count . "<br />max for this post: " .$apt_tags_to_add_max. "<br />current tags: ". print_r($apt_found_keywords_to_be_added_array, true)); //for debugging
//die("analyzed content:<br /><br />". $apt_haystack_string ."<br /><br />found tags:<br /><br />". print_r($apt_found_keywords_to_be_added_array)); //for debugging

	$apt_number_of_found_keywords = count($apt_found_keywords_to_be_added_array);

	## ADDING TAGS TO THE POST
	if($apt_settings['apt_old_tags_handling'] == 1 OR $apt_settings['apt_old_tags_handling'] == 3){ //if the post has no tags, we should add them - if it has some, it won't pass one of the first conditions in the function if $apt_settings['apt_old_tags_handling'] == 3
		wp_set_post_terms($apt_post_id, $apt_found_keywords_to_be_added_array, $apt_settings['apt_taxonomy_name'], true); //append tags
	}
	if($apt_settings['apt_old_tags_handling'] == 2){
		if($apt_number_of_found_keywords > 0){ //if the plugin found some tags (keywords), replace the old ones - otherwise do not continue!
			wp_set_post_terms($apt_post_id, $apt_found_keywords_to_be_added_array, $apt_settings['apt_taxonomy_name'], false); //replace tags
		}
		else{ //no new tags (keywords) were found
			if(($apt_settings['apt_old_tags_handling_2_remove_old_tags'] == 1) AND ($apt_post_current_tag_count > 0)){ //if no new tags were found and there are old tags, remove them all
				wp_delete_object_term_relationships($apt_post_id, $apt_settings['apt_taxonomy_name']); //remove all tags
			}
		} //else no keywords found
	} //if the user wants to replace old tags

//die("current tags: ". print_r($apt_post_current_tags, true) . "<br />array to add: ". print_r($apt_found_keywords_to_be_added_array, true) ."<br />delete old tags checkbox: ". $apt_settings['apt_old_tags_handling_2_remove_old_tags'] . "<br />current number of tags: ". $apt_post_current_tag_count); //for debugging

	//return number of added tags if needed
	if($apt_return_number_of_added_tags == 1){
		return $apt_number_of_found_keywords;
	} //-return number of added tags
} //-end of tagging function

## =========================================================================
## ### OPTIONS PAGE
## =========================================================================

function apt_options_page(){ //loads options page
	global $wpdb,
	$apt_backup_dir_rel_path,
	$apt_backup_dir_abs_path,
	$apt_new_backup_file_name_suffix,
	$apt_message_html_prefix_updated,
	$apt_message_html_prefix_error,
	$apt_message_html_prefix_warning,
	$apt_message_html_prefix_note,
	$apt_message_html_suffix,
	$apt_invalid_nonce_message,
	$apt_max_input_vars_value;

	$apt_settings = get_option('automatic_post_tagger');
	$apt_keywords_array = get_option('automatic_post_tagger_keywords');
?>

<div class="wrap">
<div id="icon-options-general" class="icon32"><br /></div>
<h2>Automatic Post Tagger</h2>

<?php
## ===================================
## ### MISCELLANEOUS
## ===================================

## bulk tagging redirection & messages
if(isset($_GET['bt'])){
	if($_GET['bt'] == 0 AND check_admin_referer('apt_bulk_tagging_0_nonce')){
		if(empty($apt_settings['apt_bulk_tagging_queue'])){
			echo $apt_message_html_prefix_updated .'Bulk tagging complete. APT has processed <strong>'. $_GET['pp'] .'</strong> posts and added <strong>'. $_GET['tt'] .'</strong> tags total to <strong>'. $_GET['ap'] .'</strong> posts.'. $apt_message_html_suffix;
		}
		else{
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The bulk tagging queue isn\'t empty. (Still unprocessed posts: '. count($apt_settings['apt_bulk_tagging_queue']) .')'. $apt_message_html_suffix;
		}
	}
	if($_GET['bt'] == 1 AND check_admin_referer('apt_bulk_tagging_1_nonce')){
		//if there are not any ids in the option, redirect the user to a normal page
		if(empty($apt_settings['apt_bulk_tagging_queue'])){
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The bulk tagging queue is empty.'. $apt_message_html_suffix;
		}
		else{ //if there are some ids in the option, execute the function
			apt_bulk_tagging();
		}
	}
} //-if isset $_GET['bt']

## display message when the "max_input_vars" limit is (about to be) reached
$apt_current_number_of_post_variables = count($_POST, COUNT_RECURSIVE);

if($apt_max_input_vars_value != false){ //make sure the value isn't false
	if($apt_current_number_of_post_variables > $apt_max_input_vars_value){
		echo $apt_message_html_prefix_error .'<strong>Error:</strong> The "max_input_vars" limit ('. $apt_max_input_vars_value .') has been exceeded (number of sent input variables: '. $apt_current_number_of_post_variables .'); some input fields have not been successfully submitted. If the plugin doesn\'t let you edit/delete keywords, change the option "Keyword management mode" to "Single input field for all keywords". See <a href="http://wordpress.org/plugins/automatic-post-tagger/faq">FAQ</a> for more information.'. $apt_message_html_suffix;
	}
	else{ //if the limit hasn't been exceeded yet
		## display warning if the "max_input_vars" limit is about to be reached
		if($apt_settings['apt_warning_messages'] == 1 AND $apt_current_number_of_post_variables != 0){
			$apt_max_input_vars_percentage = round(($apt_current_number_of_post_variables / $apt_max_input_vars_value) * 100);
			$apt_remaining_input_vars_percentage = 100 - $apt_max_input_vars_percentage;

			if($apt_remaining_input_vars_percentage < 5){ //if the number of free POST variables less than 5%
				echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> The "max_input_vars" limit ('. $apt_max_input_vars_value .') has been almost been reached (number of sent input variables: '. $apt_current_number_of_post_variables .'). When this happens, the plugin won\'t let you edit/delete keywords. You might want to change the option "Keyword management mode" to "Single input field for all keywords". See <a href="http://wordpress.org/plugins/automatic-post-tagger/faq">FAQ</a> for more information.'. $apt_message_html_suffix;
			}
		} //if warning messages allowed
	} //-else limit wasn't exceeded
}//if value is integer

## ===================================
## ### OPTIONS SAVING
## ===================================

if(isset($_POST['apt_save_settings_button'])){ //saving all settings
	if(wp_verify_nonce($_POST['apt_save_settings_hash'],'apt_save_settings_nonce')){ //save only if the nonce was verified

		//settings saved to a single array which will be updated at the end of this condition
		$apt_settings['apt_title'] = (isset($_POST['apt_title'])) ? '1' : '0';
		$apt_settings['apt_content'] = (isset($_POST['apt_content'])) ? '1' : '0';
		$apt_settings['apt_excerpt'] = (isset($_POST['apt_excerpt'])) ? '1' : '0';
		$apt_settings['apt_search_for_keyword_names'] = (isset($_POST['apt_search_for_keyword_names'])) ? '1' : '0';
		$apt_settings['apt_search_for_related_words'] = (isset($_POST['apt_search_for_related_words'])) ? '1' : '0';
		$apt_settings['apt_old_tags_handling'] = $_POST['apt_old_tags_handling'];
		$apt_settings['apt_old_tags_handling_2_remove_old_tags'] = (isset($_POST['apt_old_tags_handling_2_remove_old_tags'])) ? '1' : '0';
		$apt_settings['apt_run_apt_publish_post'] = (isset($_POST['apt_run_apt_publish_post'])) ? '1' : '0';
		$apt_settings['apt_run_apt_save_post'] = (isset($_POST['apt_run_apt_save_post'])) ? '1' : '0';
		$apt_settings['apt_run_apt_wp_insert_post'] = (isset($_POST['apt_run_apt_wp_insert_post'])) ? '1' : '0';
		$apt_settings['apt_ignore_case'] = (isset($_POST['apt_ignore_case'])) ? '1' : '0';
		$apt_settings['apt_decode_html_entities_word_separators'] = (isset($_POST['apt_decode_html_entities_word_separators'])) ? '1' : '0';
		$apt_settings['apt_decode_html_entities_analyzed_content'] = (isset($_POST['apt_decode_html_entities_analyzed_content'])) ? '1' : '0';
		$apt_settings['apt_decode_html_entities_related_words'] = (isset($_POST['apt_decode_html_entities_related_words'])) ? '1' : '0';
		$apt_settings['apt_strip_tags'] = (isset($_POST['apt_strip_tags'])) ? '1' : '0';
		$apt_settings['apt_replace_whitespaces'] = (isset($_POST['apt_replace_whitespaces'])) ? '1' : '0';
		$apt_settings['apt_replace_nonalphanumeric'] = (isset($_POST['apt_replace_nonalphanumeric'])) ? '1' : '0';
		$apt_settings['apt_dont_replace_wildcards'] = (isset($_POST['apt_dont_replace_wildcards'])) ? '1' : '0';
		$apt_settings['apt_substring_analysis'] = (isset($_POST['apt_substring_analysis'])) ? '1' : '0';
		$apt_settings['apt_wildcards'] = (isset($_POST['apt_wildcards'])) ? '1' : '0';
		$apt_settings['apt_input_correction'] = (isset($_POST['apt_input_correction'])) ? '1' : '0';
		$apt_settings['apt_create_backup_when_updating'] = (isset($_POST['apt_create_backup_when_updating'])) ? '1' : '0';
		$apt_settings['apt_warning_messages'] = (isset($_POST['apt_warning_messages'])) ? '1' : '0';
		$apt_settings['apt_keyword_editor_mode'] = $_POST['apt_keyword_editor_mode'];

		//these variables need to be stripslashed
		$apt_stripslashed_string_separator = stripslashes($_POST['apt_string_separator']);
		$apt_stripslashed_word_separators = stripslashes($_POST['apt_word_separators']);
		$apt_stripslashed_post_types = stripslashes($_POST['apt_post_types']);
		$apt_stripslashed_post_statuses = stripslashes($_POST['apt_post_statuses']);

		if(empty($apt_stripslashed_word_separators)){ //this prevents the explode function from saving an empty [0] item in the array if no word separators are set
			$apt_settings['apt_word_separators'] = array();
			echo $apt_message_html_prefix_note .'<strong>Note:</strong> No word separators were specified; a space will be used as a default word separator.'. $apt_message_html_suffix;
		}
		else{
			//user input adjustment
			if($apt_settings['apt_input_correction'] == 1){
				$apt_word_separators_trimmed = trim(trim($apt_stripslashed_word_separators, $apt_stripslashed_string_separator), $apt_settings['apt_string_separator']);
				$apt_word_separators_trimmed = preg_replace('/('. preg_quote($apt_stripslashed_string_separator, '/') .'){2,}/', $apt_stripslashed_string_separator, $apt_word_separators_trimmed); //replace multiple occurrences of the current string separator with one
				$apt_settings['apt_word_separators'] = explode($apt_settings['apt_string_separator'], $apt_word_separators_trimmed); //when exploding, we need to use the currently used ($apt_settings) apt_string_separator in word separators, otherwise the separators won't be exploded
			} //-user input adjustment
			else{
				$apt_settings['apt_word_separators'] = explode($apt_settings['apt_string_separator'], $apt_stripslashed_word_separators); //when exploding, we need to use the currently used ($apt_settings) apt_string_separator in word separators, otherwise the separators won't be exploded
			} //-else user input adjustments
		} //-else empty word separator

		if(empty($apt_stripslashed_post_types)){
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_post_types" couldn\'t be saved, because the sent value was empty.'. $apt_message_html_suffix;
		}
		else{
			//user input adjustment
			if($apt_settings['apt_input_correction'] == 1){
				$apt_post_types_trimmed = trim(trim($apt_stripslashed_post_types, $apt_stripslashed_string_separator));
				$apt_post_types_trimmed = preg_replace('/('. preg_quote($apt_stripslashed_string_separator, '/') .'){2,}/', $apt_stripslashed_string_separator, $apt_post_types_trimmed); //replace multiple occurrences of the current string separator with one
				$apt_settings['apt_post_types'] = explode($apt_settings['apt_string_separator'], $apt_post_types_trimmed);
			} //-user input adjustment
			else{
				$apt_settings['apt_post_types'] = explode($apt_settings['apt_string_separator'], $apt_stripslashed_post_types);
			} //-else user input adjustments
		} //-else empty post types

		if(empty($apt_stripslashed_post_statuses)){
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_post_statuses" couldn\'t be saved, because the sent value was empty.'. $apt_message_html_suffix;
		}
		else{
			//-user input adjustment
			if($apt_settings['apt_input_correction'] == 1){
				$apt_post_statuses_trimmed = trim(trim($apt_stripslashed_post_statuses, $apt_settings['apt_string_separator']));
				$apt_post_statuses_trimmed = preg_replace('/'. preg_quote($apt_settings['apt_string_separator'], '/') .'{2,}/', $apt_settings['apt_string_separator'], $apt_post_statuses_trimmed); //replacing multiple separators with one
				$apt_post_statuses_trimmed = preg_replace(array('/\s{2,}/', '/[\t\n]/'), '', $apt_post_statuses_trimmed); //removing whitespace characters
				$apt_settings['apt_post_statuses'] = explode($apt_settings['apt_string_separator'], $apt_post_statuses_trimmed);
			} //-user input adjustment
			else{
				$apt_settings['apt_post_statuses'] = explode($apt_settings['apt_string_separator'], $apt_stripslashed_post_statuses);
			} //-else user input adjustment
		} //-else empty post statuses

		if(empty($_POST['apt_taxonomy_name'])){
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_taxonomy_name" couldn\'t be saved, because the sent value was empty.'. $apt_message_html_suffix;
		}
		else{
			//user input adjustment
			if($apt_settings['apt_input_correction'] == 1){
				$apt_taxonomy_name_trimmed = trim($_POST['apt_taxonomy_name']);
				$apt_settings['apt_taxonomy_name'] = $apt_taxonomy_name_trimmed;
			} //-user input adjustment
			else{
				$apt_settings['apt_taxonomy_name'] = $_POST['apt_taxonomy_name'];
			} //-else user input adjustments
		} //-else empty taxonomy name

		if(empty($_POST['apt_wildcard_regex'])){
				echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_wildcard_regex" couldn\'t be saved, because the sent value was empty.'. $apt_message_html_suffix;
		}
		else{
			if(@preg_match($_POST['apt_wildcard_regex'], '') !== false){ //regex must be valid (@ suppresses PHP warnings)
				$apt_settings['apt_wildcard_regex'] = $_POST['apt_wildcard_regex'];
			}
			else{
				echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_wildcard_regex" couldn\'t be saved, because the sent regular expression was invalid.'. $apt_message_html_suffix;
			}
		} //else empty regex

		//making sure that people won't save rubbish in the DB
		if(is_numeric($_POST['apt_substring_analysis_length']) AND is_int((int)$_POST['apt_substring_analysis_length'])){ //value must be numeric and integer
			$apt_settings['apt_substring_analysis_length'] = (int)$_POST['apt_substring_analysis_length']; //we must not forget to save the CONVERTED variable
		}
		else{
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_substring_analysis_length" couldn\'t be saved, because the sent value wasn\'t integer.'. $apt_message_html_suffix;
		}

		if(is_numeric($_POST['apt_substring_analysis_start']) AND is_int((int)$_POST['apt_substring_analysis_start'])){ //value must be numeric and integer
			$apt_settings['apt_substring_analysis_start'] = (int)$_POST['apt_substring_analysis_start']; //we must not forget to save the CONVERTED variable
		}
		else{
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_substring_analysis_start" couldn\'t be saved, because the sent value wasn\'t integer.'. $apt_message_html_suffix;
		}

		if(ctype_digit($_POST['apt_tag_limit'])){ //value must be natural
			$apt_settings['apt_tag_limit'] = (int)$_POST['apt_tag_limit'];
		}
		else{
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_tag_limit" couldn\'t be saved, because the sent value wasn\'t natural.'. $apt_message_html_suffix;
		}

		if(ctype_digit($_POST['apt_stored_backups'])){ //value must be natural
			$apt_settings['apt_stored_backups'] = (int)$_POST['apt_stored_backups'];
		}
		else{
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_stored_backups" couldn\'t be saved, because the sent value wasn\'t natural.'. $apt_message_html_suffix;
		}

		//the string separator must not be empty
		if(!empty($apt_stripslashed_string_separator)){
			//the string separator must not contain the wildcard character
			if(strstr($apt_stripslashed_string_separator, $_POST['apt_wildcard_character'])){
				echo $apt_message_html_prefix_error .'<strong>Error:</strong> The new string separator couldn\'t be saved, because the sent value contained the wildcard character. Use something else, please.'. $apt_message_html_suffix;
			}
			else{ //the string doesn't contain the string separator
				if($apt_settings['apt_warning_messages'] == 1){ //display warnings
					//the string separator is not a comma
					if($apt_stripslashed_string_separator != ','){ //don't display when non-comma character was submitted
						echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> The string separator has been set to "<strong>'. htmlspecialchars($apt_stripslashed_string_separator) .'</strong>". Using a comma instead is recommended.'. $apt_message_html_suffix;

						if($apt_stripslashed_string_separator == ';'){ //don't display when a semicolon separator was submitted
							echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> You can\'t use HTML entities as word separators when a semicolon is used as a string separator.'. $apt_message_html_suffix;
						}
					}
				} //-if warnings allowed

				//if the string separator has been changed, inform the user about changing the separator in all related words (replacing separators elsewhere is not necessary if arrays are used)
				if($apt_stripslashed_string_separator != $apt_settings['apt_string_separator']){

					//replacing old separators in cells with related words with the new value
					$apt_keyword_separator_replacement_id = 0;
					//replace separators via a foreach
					foreach($apt_keywords_array as $apt_key){
						if (strstr($apt_key[2],$apt_settings['apt_string_separator'])){
							$apt_keywords_array[$apt_keyword_separator_replacement_id][2] = str_replace($apt_settings['apt_string_separator'], $apt_stripslashed_string_separator, $apt_key[2]);
						}
						$apt_keyword_separator_replacement_id++; //this incrementor must be placed AFTER the replacement function
					}
					update_option('automatic_post_tagger_keywords', $apt_keywords_array); //save keywords with new separators

					echo $apt_message_html_prefix_note .'<strong>Note:</strong> All old string separators ("<strong>'. htmlspecialchars($apt_settings['apt_string_separator']) .'</strong>") have been changed to new values ("<strong>'. htmlspecialchars($apt_stripslashed_string_separator) .'</strong>").'. $apt_message_html_suffix;

					if(in_array($apt_stripslashed_string_separator, $apt_settings['apt_word_separators'])){
							//if the new separator happens to be the same as one of the word separators, delete it and inform the user that they should add the separator as a HTML entity
							if($apt_settings['apt_input_correction'] == 1){
								$apt_word_separators_trimmed = implode($apt_stripslashed_string_separator, $apt_settings['apt_word_separators']); //we are trimming imploded word separators saved as an array several lines above (TODO: make this more effective?)
								$apt_word_separators_trimmed = trim(preg_replace('/('. preg_quote($apt_stripslashed_string_separator, '/') .'){2,}/', $apt_stripslashed_string_separator, $apt_word_separators_trimmed), $apt_stripslashed_string_separator); //replace multiple occurrences of the current string separator with one
								$apt_settings['apt_word_separators'] = explode($apt_settings['apt_string_separator'], $apt_word_separators_trimmed); //when exploding, we need to use the currently used ($apt_settings) apt_string_separator in word separators, otherwise the separators won't be exploded

								if($apt_settings['apt_warning_messages'] == 1){ //display warnings
									echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> The new string separator ("<strong>'. htmlspecialchars($apt_stripslashed_string_separator) .'</strong>") is already used as a word separator; the word separator "<strong>'. htmlspecialchars($apt_stripslashed_string_separator) .'</strong>" thus has been automatically removed. To prevent its automatic removal in the future, add this word separator again using its HTML entity.'. $apt_message_html_suffix;
								} //-if warnings allowed
							}
							else{
								if($apt_settings['apt_warning_messages'] == 1){ //display warnings
									echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> The new string separator ("<strong>'. htmlspecialchars($apt_stripslashed_string_separator) .'</strong>") is already used as a word separator. If you don\'t replace this word separator with its applicable HTML entity or remove redundant string separators, APT will treat the non-existent characters between these string separators as word separators, which might result in non-relevant tags being added to your posts.'. $apt_message_html_suffix;
								} //-if warnings allowed

							} //-input correction
					} //-if separator is the same as one of word separators
				} //-separator was changed

				$apt_settings['apt_string_separator'] = $apt_stripslashed_string_separator; //this line MUST be under the current/old separator check!
			} //-else doesn't contain the wildcard character
		} //-if not empty
		else{
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_string_separator" couldn\'t be saved, because the sent value was empty.'. $apt_message_html_suffix;
		}

		//the wildcard must not be empty
		if(!empty($_POST['apt_wildcard_character'])){
			//the wildcard must not contain the string separator
			if(strstr($_POST['apt_wildcard_character'], $apt_stripslashed_string_separator)){
				echo $apt_message_html_prefix_error .'<strong>Error:</strong> The new wildcard character couldn\'t be saved, because the sent value contained the string separator. Use something else, please.'. $apt_message_html_suffix;
			}
			else{ //the string doesn't contain the string separator

				if($apt_settings['apt_warning_messages'] == 1){ //display warnings
					//the wildcard is not an asterisk
					if($_POST['apt_wildcard_character'] != '*'){ //display when non-asterisk character was submitted
						echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> The option "apt_wildcard_character" has been set to "<strong>'. htmlspecialchars($_POST['apt_wildcard_character']) .'</strong>". Using an asterisk instead is recommended.'. $apt_message_html_suffix;
					}
				} //-if warnings allowed

				//if the wildcard has been changed, inform the user about changing wildcards in all related words, if keywords exist
				if($_POST['apt_wildcard_character'] != $apt_settings['apt_wildcard_character'] AND $apt_settings['apt_keywords_total'] > 0){
					//replacing old wildcards in cells with related words with the new value
					$apt_keyword_wildcard_replacement_id = 0;
					//replace wildcards via a foreach
					foreach($apt_keywords_array as $apt_key){
						if(strstr($apt_key[2],$apt_settings['apt_wildcard_character'])){
							$apt_keywords_array[$apt_keyword_wildcard_replacement_id][2] = str_replace($apt_settings['apt_wildcard_character'], $_POST['apt_wildcard_character'], $apt_key[2]);
						}
						$apt_keyword_wildcard_replacement_id++; //this incrementor must be placed AFTER the replacement function
					}

					update_option('automatic_post_tagger_keywords', $apt_keywords_array); //save keywords with new wildcards


					echo $apt_message_html_prefix_note .'<strong>Note:</strong> All old wildcard characters used in related words ("<strong>'. htmlspecialchars($apt_settings['apt_wildcard_character']) .'</strong>") have been changed to new values ("<strong>'. htmlspecialchars($_POST['apt_wildcard_character']) .'</strong>").'. $apt_message_html_suffix;
				} //wildcard has been changed

				$apt_settings['apt_wildcard_character'] = $_POST['apt_wildcard_character']; //this line MUST be placed after the current/old wildcard check
			} //-else doesn't contain the string separator

		} //-if not empty
		else{
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_wildcard_character" couldn\'t be saved, because the sent value was empty.'. $apt_message_html_suffix;
		}

		update_option('automatic_post_tagger', $apt_settings); //save settings

		## generate warnings
		//the $apt_settings variable is used here instead of $_POST; the POST data have been already saved there

		if($apt_settings['apt_warning_messages'] == 1){ //display warnings
			//warn the user if the string separator is repeated multiple times in the option apt_word_separators while input correction is disabled
			if($apt_settings['apt_input_correction'] == 0){
				if(preg_match('/('. preg_quote($apt_settings['apt_string_separator'], '/') .'){2,}/', $apt_stripslashed_word_separators)){
					echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> Your word separators contain multiple string separators in a row. If you don\'t remove them, APT will treat the non-existent characters between them as word separators, which might result in non-relevant tags being added to your posts.'. $apt_message_html_suffix;
				}
			} //-input correction disabled

			//warn the user if the specified post types doesn't exist
			foreach($apt_settings['apt_post_types'] as $apt_post_type){
				if(!post_type_exists($apt_post_type)){
					echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> The post type "<strong>'. htmlspecialchars($apt_post_type) .'</strong>" doesn\'t exist.'. $apt_message_html_suffix;
				}
			} //-foreach
			//warn the user if the specified post statuses doesn't exist
			foreach($apt_settings['apt_post_statuses'] as $apt_post_status){
				if(!in_array($apt_post_status, array('publish', 'future', 'draft', 'pending', 'private', 'trash', 'auto-draft', 'inherit'))){
					echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> The post status "<strong>'. htmlspecialchars($apt_post_status) .'</strong>" is not one of the default statuses used by WP.'. $apt_message_html_suffix; //we always display this warning, because the user should see it, even if they don't want warnings to be displayed
				}
			} //-foreach
			//warn the user if the taxonomy doesn't exist
			if(!taxonomy_exists($apt_settings['apt_taxonomy_name'])){
				echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> The taxonomy "<strong>'. htmlspecialchars($_POST['apt_taxonomy_name']) .'</strong>" doesn\'t exist.'. $apt_message_html_suffix;
			} //-if

			//warn users about the inability to add tags
			if(($apt_settings['apt_title'] == 0 AND $apt_settings['apt_content'] == 0 AND $apt_settings['apt_excerpt'] == 0) OR ($apt_settings['apt_substring_analysis'] == 1 AND $apt_settings['apt_substring_analysis_length'] == 0)){
				echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> The plugin isn\'t allowed to analyze any content.'. $apt_message_html_suffix;
			}
			if($apt_settings['apt_search_for_keyword_names'] == 0 AND $apt_settings['apt_search_for_related_words'] == 0){
				echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> The plugin isn\'t allowed to search for any keywords.'. $apt_message_html_suffix;
			}
			if($apt_settings['apt_tag_limit'] == 0){
				echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> The plugin isn\'t allowed add any tags.'. $apt_message_html_suffix;
			}
			if($apt_settings['apt_run_apt_publish_post'] == 0 AND $apt_settings['apt_run_apt_save_post'] == 0 AND $apt_settings['apt_run_apt_wp_insert_post'] == 0){
				echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> The plugin isn\'t allowed to automatically process posts.'. $apt_message_html_suffix;
			}

			//warn the user about ignored word separators
			if(isset($_POST['apt_replace_nonalphanumeric']) AND !empty($apt_settings['apt_word_separators'])){ //display this note only if word separators are set and the user wants to replace non-alphanumeric characters with spaces
				echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> Replacement of non-alphanumeric characters with spaces has been enabled. Currently set word separators will be ignored.'. $apt_message_html_suffix;
			}
			//warn the user about non-functioning wildcards
			if(isset($_POST['apt_replace_nonalphanumeric']) AND $apt_settings['apt_dont_replace_wildcards'] == 0){ //display this note only if wildcards are not being ignored
				echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> Replacement of non-alphanumeric characters (including wildcards) with spaces has been enabled. Wildcards won\'t work unless you allow the option "Don\'t replace wildcards".'. $apt_message_html_suffix;
			}
			//warn the user if whitespace characters won't be replaced 
			if(!isset($_POST['apt_replace_whitespaces'])){
				echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> Replacement of whitespace characters with spaces has been disabled. APT won\'t be able to find keywords separated by newlines and tabs.'. $apt_message_html_suffix;
			}
			//warn the user if user input adjustment is disabled
			if(!isset($_POST['apt_input_correction'])){
				echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> Automatic input correction has been disabled. <span class="apt_help" title="APT may not work as expected if your inputs contain invalid data: unnecessary spaces, multiple whitespace characters, wildcards, string separators etc.">i</span>'. $apt_message_html_suffix;
			}
			//warn the user if the number of backups is 0
			if($apt_settings['apt_stored_backups'] == 0){
				echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> The plugin isn\'t allowed to export keywords.'. $apt_message_html_suffix;

				if($apt_settings['apt_create_backup_when_updating'] == 1){
				echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> The plugin won\'t be allowed to create an automatic backup when updating.'. $apt_message_html_suffix;
				}
			}
		} //-if warnings allowed

		echo $apt_message_html_prefix_updated .'Settings have been saved.'. $apt_message_html_suffix;
	} //-nonce check
	else{ //the nonce is invalid
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

		echo $apt_message_html_prefix_updated .'Default settings have been restored.'. $apt_message_html_suffix;
	} //-nonce check
	else{ //the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

## ===================================
## ### KEYWORD MANAGEMENT
## ===================================

if(isset($_POST['apt_create_new_keyword_button'])){ //creating a new keyword with relaterd words
	if(wp_verify_nonce($_POST['apt_create_new_keyword_hash'],'apt_create_new_keyword_nonce')){ //save only if the nonce was verified
		apt_create_new_keyword($_POST['apt_create_keyword_name'], $_POST['apt_create_keyword_related_words']);
	} //-nonce check
	else{ //the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

if(isset($_POST['apt_delete_all_keywords_button'])){ //delete all keywords
	if(wp_verify_nonce($_POST['apt_delete_all_keywords_hash'],'apt_delete_all_keywords_nonce')){ //save only if the nonce was verified
		$apt_settings['apt_keywords_total'] = 0; //reset stats
		$apt_settings['apt_last_keyword_id'] = 0; //reset the last id
		update_option('automatic_post_tagger', $apt_settings); //save settings
		update_option('automatic_post_tagger_keywords', array()); //save empty array value

		echo $apt_message_html_prefix_updated .'All keywords have been deleted.'. $apt_message_html_suffix;
	} //-nonce check
	else{ //the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

if(isset($_POST['apt_delete_chosen_keywords_button'])){ //delete chosen keywords
	if(wp_verify_nonce($_POST['apt_delete_chosen_keywords_hash'],'apt_delete_chosen_keywords_nonce')){ //save only if the nonce was verified
		if(isset($_POST['apt_keywordlist_checkbox_'])){ //determine if any checkbox was checked
			$apt_keywords_array_new = $apt_keywords_array; //load current keywords 
			$apt_deleted_chosen_keywords = 0;

			foreach($_POST['apt_keywordlist_checkbox_'] as $apt_id => $apt_value){ //loop for handling checkboxes
				//find the keyword by its id and unset it
				foreach($apt_keywords_array_new as $apt_sub_key => $apt_sub_array){
					if($apt_sub_array[0] == $apt_id){
						unset($apt_keywords_array_new[$apt_sub_key]);
						$apt_deleted_chosen_keywords++;
					}
				} //-foreach
			} //-foreach checkbox ids

			update_option('automatic_post_tagger_keywords', $apt_keywords_array_new); //save keywords - this line must be placed before the count function in order to display correct stats

			$apt_settings['apt_keywords_total'] = count($apt_keywords_array_new);
			update_option('automatic_post_tagger', $apt_settings); //save settings

			echo $apt_message_html_prefix_updated .'<strong>'. $apt_deleted_chosen_keywords .'</strong> chosen keywords have been deleted.'. $apt_message_html_suffix;
		}
		else{
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> You must choose at least one keyword in order to delete it.'. $apt_message_html_suffix;
		}
	} //-nonce check
	else{ //the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

if(isset($_POST['apt_save_keywords_button'])){ //saving changed keywords
	if(wp_verify_nonce($_POST['apt_save_keywords_hash'],'apt_save_keywords_nonce')){ //save only if the nonce was verified
		if($apt_settings['apt_keyword_editor_mode'] == 1){ //if KEM =1
			$apt_keywords_array_new = array(); //all keywords will be saved into this variable

			foreach($_POST['apt_keywordlist_keyword_'] as $apt_id => $apt_value){ //saving related words ($apt_value is necessary here)
				if(!empty($_POST['apt_keywordlist_keyword_'][$apt_id])){ //continue only if the keyword name isn't empty
					//user input adjustment
					if($apt_settings['apt_input_correction'] == 1){
						$apt_saved_keyword = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $_POST['apt_keywordlist_keyword_'][$apt_id]); //replacing multiple whitespace characters with a space (if there were, say two spaces between words, this will convert them to one)
						$apt_saved_keyword = trim(stripslashes($apt_saved_keyword)); //trimming slashes and whitespace characters
					} //-user input adjustment
					else{
						$apt_saved_keyword = stripslashes($_POST['apt_keywordlist_keyword_'][$apt_id]); //stripping slashes
					} //-else user input adjustment

					$apt_to_be_saved_keyword_already_exists = 0; //variable for determining whether keyword exists
					foreach($apt_keywords_array_new as $apt_key){ //process all elements of the array
						if(strtolower($apt_key[1]) == strtolower($apt_saved_keyword)){ //checking if the strtolowered keyword already exists in the array
							$apt_to_be_saved_keyword_already_exists = 1;
							break; //stop the loop
						}
					} //-foreach

					if($apt_to_be_saved_keyword_already_exists == 0){ //add keyword only if it isn't already in the array
						$apt_saved_related_words = $_POST['apt_keywordlist_related_words_'][$apt_id];
						$apt_new_saved_related_words = ''; //this variable makes sure that related words are submitted as an empty string if they're not submitted

						if(!empty($apt_saved_related_words)){ //the sent value is NOT empty
							//user input adjustment
							if($apt_settings['apt_input_correction'] == 1){
								$apt_new_saved_related_words = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $apt_saved_related_words); //replacing multiple whitespace characters with a space (if there were, say two spaces between words, this will convert them to one)
								$apt_new_saved_related_words = preg_replace('/'. preg_quote($apt_settings['apt_string_separator'], '/') .'{2,}/', $apt_settings['apt_string_separator'], $apt_new_saved_related_words); //replacing multiple separators with one
								$apt_new_saved_related_words = preg_replace('/'. preg_quote($apt_settings['apt_wildcard_character'], '/') .'{2,}/', $apt_settings['apt_wildcard_character'], $apt_new_saved_related_words); //replacing multiple wildcards with one
								$apt_new_saved_related_words = trim(trim(stripslashes($apt_new_saved_related_words)), $apt_settings['apt_string_separator']); //removing slashes, trimming separators and whitespace characters from the beginning and the end
							} //-user input adjustment
							else{
								$apt_new_saved_related_words = stripslashes($apt_saved_related_words);
							} //-else user input adjustment

							//generate warnings
							if($apt_settings['apt_warning_messages'] == 1){ //display warnings
								if(strstr($apt_new_saved_related_words, ' '. $apt_settings['apt_string_separator']) OR strstr($apt_new_saved_related_words, $apt_settings['apt_string_separator']. ' ')){ //mistake scenario
									$apt_new_saved_related_words_extra_spaces_warning = 1;
								}
								if(strstr($apt_new_saved_related_words, $apt_settings['apt_wildcard_character']) AND ($apt_settings['apt_wildcards'] == 0)){ //mistake scenario
									$apt_new_saved_related_words_wildcard_warning = 1;
								}
							} //-if warnings allowed
						} //-if !empty check

						//if the keyword name is empty, the keyword will not be added to the array again with its previous value = it will be deleted (since 1.6)
			 			array_push($apt_keywords_array_new, array($apt_id, $apt_saved_keyword, $apt_new_saved_related_words)); //add the keyword + related words to the end of the array
					} //-if keyword isn't already in the array
				} //-else if keyword name not empty
				else{ //save if not empty
					$apt_saved_keyword_empty_error = 1;
				}
			} //-foreach


			update_option('automatic_post_tagger_keywords', $apt_keywords_array_new); //save keywords

			$apt_settings['apt_keywords_total'] = count($apt_keywords_array_new); //save the number of current keywords
			update_option('automatic_post_tagger', $apt_settings); //save settings

			echo $apt_message_html_prefix_updated .'Keywords have been saved.'. $apt_message_html_suffix;

			if(isset($apt_saved_keyword_empty_error) AND $apt_saved_keyword_empty_error == 1){
				echo $apt_message_html_prefix_note .'<strong>Note:</strong> Some keyword names were missing; these keywords were deleted.'. $apt_message_html_suffix;
			}

			if($apt_settings['apt_warning_messages'] == 1){ //display warnings
				//warning messages appearing when "unexpected" character are being saved - mistake scenarios
				if(isset($apt_new_saved_related_words_extra_spaces_warning) AND $apt_new_saved_related_words_extra_spaces_warning == 1){
					echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> Some related words contain extra spaces near string separators.'. $apt_message_html_suffix;
				}
				if(isset($apt_new_saved_related_words_wildcard_warning) AND $apt_new_saved_related_words_wildcard_warning == 1){
					echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> Your related words contain the wildcard character, but wildcard support is currently disabled!'. $apt_message_html_suffix;
				}
			} //-if warnings allowed
		} //-if KEM =1
		else{ //else KEM =1
			apt_import_keywords_from_textarea($_POST['apt_keywords_textarea']);
		} //-else KEM =1
	} //-nonce check
	else{ //the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

## ===================================
## ### IMPORT/EXPORT
## ===================================

if(isset($_POST['apt_import_from_database_button'])){ //import keywords from database
	if(wp_verify_nonce($_POST['apt_import_from_database_hash'],'apt_import_from_database_nonce')){ //save only if the nonce was verified

		$apt_keywords_array_new = $apt_keywords_array; //all keywords will be saved into this variable which also includes old keywords

			if($_POST['apt_import_from_database_column'] == 1){ //select only the term name if the user wants to import terms as keyword names, otherwise select also the ID
				$apt_retrieve_existing_taxonomy_sql = 'SELECT name FROM '. $wpdb->terms .' NATURAL JOIN '. $wpdb->term_taxonomy .' WHERE taxonomy="'. $apt_settings['apt_taxonomy_name'] .'"'; //select all existing tags
			}
			else{
				$apt_retrieve_existing_taxonomy_sql = 'SELECT term_id, name FROM '. $wpdb->terms .' NATURAL JOIN '. $wpdb->term_taxonomy .' WHERE taxonomy="'. $apt_settings['apt_taxonomy_name'] .'"'; //select all existing tags
			} //-else

		$apt_retrieve_existing_taxonomy_results = $wpdb->get_results($apt_retrieve_existing_taxonomy_sql, ARRAY_N); //ARRAY_N - result will be output as a numerically indexed array of numerically indexed arrays. 
		$apt_currently_imported_keywords = 0; //this will be used to determine how many keywrds were imported
		$apt_new_keyword_id = $apt_settings['apt_last_keyword_id']; //the id value MUST NOT be increased here - it is increased in the loop

		foreach($apt_retrieve_existing_taxonomy_results as $apt_taxonomy_array){ //run loop to process all rows
			$apt_to_be_created_keyword_already_exists = 0; //variable for determining whether the taxonomy item exists
			foreach($apt_keywords_array_new as $apt_key){ //process all elements of the array
				//duplicity check
				if($_POST['apt_import_from_database_column'] == 1){
					if(strtolower($apt_key[1]) == strtolower($apt_taxonomy_array[0])){ //checking whether the strtolowered term already exists in the DB
						$apt_to_be_created_keyword_already_exists = 1;
						break; //stop the loop
					}
				}
				else{
					if(strtolower($apt_key[1]) == $apt_taxonomy_array[0]){ //checking whether the term ID already exists in the DB
						$apt_to_be_created_keyword_already_exists = 1;
						break; //stop the loop
					}
				} //-else duplicity check
			} //-foreach

			//adding terms from taxonomy as keyword names
			if($_POST['apt_import_from_database_column'] == 1){
				if($apt_to_be_created_keyword_already_exists == 0){ //add the taxonomy item only if it doesn't exist yet
					$apt_new_keyword_id++; //increase the id value
					array_push($apt_keywords_array_new, array($apt_new_keyword_id, $apt_taxonomy_array[0], '')); //we are not inserting any related words because there aren't any associated with them - we are importing terms only
					$apt_currently_imported_keywords++;
				} //if-add keyword
			}
			else{ //adding terms from taxonomy as related words
				if($apt_to_be_created_keyword_already_exists == 0){ //add the taxonomy item only if it doesn't exist yet
					$apt_new_keyword_id++; //increase the id value
					array_push($apt_keywords_array_new, array($apt_new_keyword_id, $apt_taxonomy_array[0], $apt_taxonomy_array[1])); //we are importing terms as related words and their IDs as keyword names
					$apt_currently_imported_keywords++;
				} //if-add related words
			} //-else

		} //-foreach

		if($apt_currently_imported_keywords != 0){ //we have imported something!
			update_option('automatic_post_tagger_keywords', $apt_keywords_array_new); //save keywords - this line must be placed before the count function in order to display correct stats
			$apt_settings['apt_last_keyword_id'] = $apt_new_keyword_id; //save newest last id value
			$apt_settings['apt_keywords_total'] = count($apt_keywords_array_new); //update stats
			update_option('automatic_post_tagger', $apt_settings); //save settings

			echo $apt_message_html_prefix_updated .'Import complete. <strong>'. $apt_currently_imported_keywords .'</strong> keywords have been imported.'. $apt_message_html_suffix;
		}
		else{
			echo $apt_message_html_prefix_note .'<strong>Note:</strong> No new (unique) keywords were imported.'. $apt_message_html_suffix;
		}

	} //-nonce check
	else{ //the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

if(isset($_POST['apt_import_from_file_button'])){ //import a backup file
	if(wp_verify_nonce($_POST['apt_import_from_file_hash'],'apt_import_from_file_nonce')){ //save only if the nonce was verified

		if(strstr($_FILES['apt_uploaded_file']['name'], $apt_new_backup_file_name_suffix)){ //checks if the name of uploaded file contains the suffix ".csv"
			$apt_new_backup_file_rel_path = $apt_backup_dir_rel_path . apt_get_backup_file_name();

			if(move_uploaded_file($_FILES['apt_uploaded_file']['tmp_name'], $apt_new_backup_file_rel_path)){ //file can be uploaded (moved to the plugin directory)

				$apt_backup_file_import_handle = fopen($apt_new_backup_file_rel_path, 'r');
				$apt_keywords_array_new = $apt_keywords_array; //all keywords will be saved into this variable which also includes old keywords
				$apt_currently_imported_keywords = 0; //this will be used to determine how many keywords were imported
				$apt_new_keyword_id = $apt_settings['apt_last_keyword_id']; //the id value MUST NOT be increased here - it is increased in the loop

				while(($apt_csv_row = fgetcsv($apt_backup_file_import_handle, ',')) !== false){
					if(!empty($apt_csv_row[0])){ //mistake scenario check - don't save if the keyword name is empty

						$apt_to_be_created_keyword_already_exists = 0; //variable for determining whether keyword exists
						foreach($apt_keywords_array_new as $apt_key){ //process all elements of the array
							if(strtolower($apt_key[1]) == strtolower($apt_csv_row[0])){ //checking whether the strtolowered keyword already exists in the DB
								$apt_to_be_created_keyword_already_exists = 1;
								break; //stop the loop
							}
						} //-foreach

						if($apt_to_be_created_keyword_already_exists == 0){ //add keyword only if it doesn't exist yet
							$apt_new_keyword_id++; //increase the id value
							array_push($apt_keywords_array_new, array($apt_new_keyword_id, $apt_csv_row[0], $apt_csv_row[1]));
							$apt_currently_imported_keywords++;

							if(!empty($apt_csv_row[1])){
								if($apt_settings['apt_warning_messages'] == 1){ //display warnings
									if(strstr($apt_csv_row[1], ' '. $apt_settings['apt_string_separator']) OR strstr($apt_csv_row[1], $apt_settings['apt_string_separator'] .' ')){
										$apt_imported_related_words_extra_spaces_warning = 1;
									}
									if(strstr($apt_csv_row[1], $apt_settings['apt_wildcard_character']) AND ($apt_settings['apt_wildcards'] == 0)){
										$apt_imported_related_words_wildcard_warning = 1;
									}
								} //-if warnings allowed
							} //-if related words not empty
						} //-if keyword exists
					} //-if empty check
					else{
						$apt_imported_keyword_empty_error = 1;
					}

				} //-while

				fclose($apt_backup_file_import_handle); //close the file
				unlink($apt_new_backup_file_rel_path); //remove the file from the directory

				if($apt_currently_imported_keywords != 0){ //we have imported something!
					update_option('automatic_post_tagger_keywords', $apt_keywords_array_new); //save keywords - this line must be before the count function in order to display correct stats
					$apt_settings['apt_last_keyword_id'] = $apt_new_keyword_id; //save newest last id value
					$apt_settings['apt_keywords_total'] = count($apt_keywords_array_new); //update stats
					update_option('automatic_post_tagger', $apt_settings); //save settings

					echo $apt_message_html_prefix_updated .'Import complete. <strong>'. $apt_currently_imported_keywords .'</strong> keywords have been imported.'. $apt_message_html_suffix;

					//mistake warnings/errors
					if(isset($apt_imported_keyword_empty_error) AND $apt_imported_keyword_empty_error == 1){
						echo $apt_message_html_prefix_error .'<strong>Error:</strong> Some keywords weren\'t imported, because their names were missing.'. $apt_message_html_suffix;
					}

					if($apt_settings['apt_warning_messages'] == 1){ //display warnings
						if(isset($apt_imported_related_words_wildcard_warning) AND $apt_imported_related_words_wildcard_warning == 1){
							echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> Your related words contain the wildcard character, but wildcard support is currently disabled!'. $apt_message_html_suffix;
						}
						if(isset($apt_imported_related_words_extra_spaces_warning) AND $apt_imported_related_words_extra_spaces_warning == 1){
							echo $apt_message_html_prefix_warning .'<strong>Warning:</strong> Some related words contain extra spaces near string separators.'. $apt_message_html_suffix;
						}
					} //-if warnings allowed
				} //some keywords were imported
				else{ //no keywords were imported
					echo $apt_message_html_prefix_note .'<strong>Note:</strong> No new (unique) keywords were imported.'. $apt_message_html_suffix;
				} //-no keyword imported
			} //can upload file
			else{ //cannot upload file
				echo $apt_message_html_prefix_error .'<strong>Error:</strong> The file couldn\'t be uploaded.'. $apt_message_html_suffix;
			}
		}
		else{ //the file name is invalid
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The name of the imported file must contain the suffix "<strong>'. $apt_new_backup_file_name_suffix .'</strong>".'. $apt_message_html_suffix;
		}
	} //-nonce check
	else{ //the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

if(isset($_POST['apt_export_to_file_button'])){ //creating backup
	if(wp_verify_nonce($_POST['apt_export_to_file_hash'],'apt_export_to_file_nonce')){ //save only if the nonce was verified
		apt_export_keywords();
	} //-nonce check
	else{ //the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

## ===================================
## ### BULK TAGGING
## ===================================

if(isset($_POST['apt_bulk_tagging_button'])){
	if(wp_verify_nonce($_POST['apt_bulk_tagging_hash'],'apt_bulk_tagging_nonce')){ //save only if the nonce was verified
		### stopping execution to prevent the function from doing unuseful job:

		if(!ctype_digit($_POST['apt_bulk_tagging_range_1']) OR !ctype_digit($_POST['apt_bulk_tagging_range_2'])){ //value must be natural
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_bulk_tagging_queue" couldn\'t be saved, because the sent values weren\'t natural.'. $apt_message_html_suffix;
		}
		if($_POST['apt_bulk_tagging_range_1'] > $_POST['apt_bulk_tagging_range_2']){
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_bulk_tagging_range_1" can\'t be higher than "apt_bulk_tagging_range_2".'. $apt_message_html_suffix;
		}
		if(ctype_digit($_POST['apt_bulk_tagging_posts_per_cycle']) AND $_POST['apt_bulk_tagging_posts_per_cycle'] != 0){ //value must be natural and not zero
			$apt_settings['apt_bulk_tagging_posts_per_cycle'] = (int)$_POST['apt_bulk_tagging_posts_per_cycle'];
		}
		else{
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_bulk_tagging_posts_per_cycle" couldn\'t be saved, because the sent value wasn\'t natural or nonzero.'. $apt_message_html_suffix;
		}
		if(ctype_digit($_POST['apt_bulk_tagging_delay'])){ //value must be natural
			$apt_settings['apt_bulk_tagging_delay'] = (int)$_POST['apt_bulk_tagging_delay'];
		}
		else{
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The option "apt_bulk_tagging_delay" couldn\'t be saved, because the sent value wasn\'t natural.'. $apt_message_html_suffix;
		}

		update_option('automatic_post_tagger', $apt_settings); //save settings

		### mistake scenarios
		//there are not any keywords to add, stop!
		if($apt_settings['apt_keywords_total'] == 0){
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> There aren\'t any keywords that can be added to posts.'. $apt_message_html_suffix;
		}
		//there are not any posts to tag, stop! (this doesn't have to be in the apt_single_post_tagging function)
		if($wpdb->get_var('SELECT COUNT(ID) FROM '. $wpdb->posts) == 0){
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> There aren\'t any posts that can be processed.'. $apt_message_html_suffix;
		}
		//the user does not want to add any tags, stop!
		if($apt_settings['apt_tag_limit'] <= 0){
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The maximum number of tags can\'t be equal or lower than zero.'. $apt_message_html_suffix;
		}
		//the user does not want us to search anything, stop!
		if($apt_settings['apt_title'] == 0 AND $apt_settings['apt_content'] == 0 AND $apt_settings['apt_excerpt'] == 0){
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The plugin isn\'t allowed to analyze any content.'. $apt_message_html_suffix;
		}
		//the user does not want us to process 0 characters, stop!
		if($apt_settings['apt_substring_analysis'] == 1 AND $apt_settings['apt_substring_analysis_length'] == 0){
			$apt_bulk_tagging_error = 1;
			echo $apt_message_html_prefix_error .'<strong>Error:</strong> The plugin isn\'t allowed to analyze any content.'. $apt_message_html_suffix;
		}
		#################################################################

		//we need to check whether some errors occured - if the variable is not set, continue
		if(!isset($apt_bulk_tagging_error)){
			$apt_ids_for_bulk_tagging_array = array();
			$apt_print_ids_without_specified_statuses_sql = "SELECT ID FROM $wpdb->posts ". apt_print_sql_where_without_specified_statuses() ." ORDER BY ID ASC";
			$apt_print_ids_without_specified_statuses_results = $wpdb->get_results($apt_print_ids_without_specified_statuses_sql, ARRAY_A);

//print_r($apt_print_ids_without_specified_statuses_results); //for debugging

			foreach($apt_print_ids_without_specified_statuses_results as $apt_row){ //for some reason if we don't use the variable we probably get an infinite loop resulting in a max_execution_time error
				//determine if the ID is within the range specified by the user, if yes, add it to the array
				if($apt_row['ID'] >= $_POST['apt_bulk_tagging_range_1'] AND $apt_row['ID'] <= $_POST['apt_bulk_tagging_range_2']){
					$apt_ids_for_bulk_tagging_array[] = $apt_row['ID'];
				}
			} //-foreach

//die(print_r($apt_ids_for_bulk_tagging_array)); //for debugging

			//if no post IDs are added to the array, throw an exception and don't continue
			if(count($apt_ids_for_bulk_tagging_array) == 0){
				echo $apt_message_html_prefix_error .'<strong>Error:</strong> There isn\'t any post ID within the specified range.'. $apt_message_html_suffix;
			}
			else{ //IDs are in the array, continue!
				$apt_settings['apt_bulk_tagging_queue'] = $apt_ids_for_bulk_tagging_array; //saving retrieved ids to the option
				update_option('automatic_post_tagger', $apt_settings); //save settings

				if(!empty($apt_settings['apt_bulk_tagging_queue'])){ //if the option isn't empty, redirect the page to another page with a nonce
					//since the admin_head/admin_print_scripts hook doesn't work inside the options page function and we cannot use header() or wp_redirect() here
					//(because some webhosts will throw the "headers already sent" error), so we need to use a javascript redirect or a meta tag printed to a bad place
					//OR we could constantly check the database for a saved value and use admin_menu somewhere else (I am not sure if it is a good idea)

					echo $apt_message_html_prefix_note .'<strong>Note:</strong> Bulk tagging is currently in progress. This may take some time.'. $apt_message_html_suffix;
					echo '<!-- Automatic Post Tagger -->'; //no &bt in the URL, no tagging happened yet, some post IDs are in the queue
					echo '<p><strong>Posts in the queue:</strong> '. count($apt_ids_for_bulk_tagging_array) .'</p>'; //display number of posts in queue
					echo '<p><small>This page should be automatically refreshed in '. $apt_settings['apt_bulk_tagging_delay'] .' seconds. <a href="'. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=1'), 'apt_bulk_tagging_1_nonce') .'">Click here if that doesn\'t happen &raquo;</a></small></p>'; //display an alternative link if methods below fail
					echo '<script type="text/javascript">setTimeout(function(){window.location.replace("'. str_replace('&amp;', '&', wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=1'), 'apt_bulk_tagging_1_nonce')) .'")}, '. $apt_settings['apt_bulk_tagging_delay']*1000 .')</script>'; //the str_replace function is here because the wp_nonce_url function provides &amp; instead of &, so I need to replace it or the web browser won't redirect anything; the number of seconds has to be multiplied by 1000 here
					echo '<noscript><meta http-equiv="refresh" content="'. $apt_settings['apt_bulk_tagging_delay'] .';url='. wp_nonce_url(admin_url('options-general.php?page=automatic-post-tagger&bt=1'), 'apt_bulk_tagging_1_nonce') .'"></noscript>'; //if use the meta tag to refresh the page
					echo '<!-- //-Automatic Post Tagger -->';
					exit;
				}
			}
		} //-if for no errors found
	} //-nonce check
	else{ //the nonce is invalid
		die($apt_invalid_nonce_message);
	}
}

## =========================================================================
## ### USER INTERFACE
## =========================================================================
?>

<div id="poststuff" class="metabox-holder has-right-sidebar">
	<div class="inner-sidebar">
		<div id="side-sortables" class="meta-box-sortabless" style="position:relative;">

			<!-- postbox -->
			<div class="postbox">
				<h3 class="hndle"><span>Useful links</span></h3>
				<div class="inside">
						<ul>
							<li><a href="http://wordpress.org/plugins/automatic-post-tagger/"><span class="apt_icon apt_wp"></span>Plugin homepage</a></li>
							<li><a href="http://wordpress.org/support/plugin/automatic-post-tagger"><span class="apt_icon apt_wp"></span>Support forum</a></li>
							<li><a href="http://wordpress.org/plugins/automatic-post-tagger/faq"><span class="apt_icon apt_wp"></span>Frequently asked questions</a> </li>
						</ul>
				</div>
			</div>
			<!-- //-postbox -->

			<!-- postbox -->
			<div class="postbox">
				<h3 class="hndle"><span>Do you like the plugin?</span></h3>
				<div class="inside">
					<p>If you find this plugin useful, please rate it or consider donating to support its further development.</p>
						<ul>
							<li><a href="http://wordpress.org/support/view/plugin-reviews/automatic-post-tagger"><span class="apt_icon apt_rate"></span>Rate the plugin on WordPress.org</a></li>
							<li><a href="https://www.patreon.com/devtard"><span class="apt_icon apt_patreon"></span>Become a patron on Patreon</a></li>
						</ul>
					<p>Thanks!</p>
				</div>
			</div><!-- //-postbox -->

		</div><!-- //-side-sortables -->
	</div><!-- //-inner-sidebar -->

	<div class="has-sidebar sm-padded">
		<div id="post-body-content" class="has-sidebar-content">
			<div class="meta-box-sortabless">

				<!-- postbox -->
				<form action="<?php echo admin_url('options-general.php?page=automatic-post-tagger'); ?>" method="post">
				<div class="postbox">
					<div onclick="apt_toggle_widget(1);" class="handlediv" title="Click to toggle"><br /></div>
					<h3 class="hndle"><span>Settings</span></h3>

					<div class="inside" id="apt_widget_id_[1]" <?php echo apt_change_widget_visibility(1); ?>>
						<table class="form-table">
							<tr valign="top">
								<th scope="row">
									Run APT when posts are: <span class="apt_help" title="These options determine when the plugin should automatically process and tag posts.">i</span>
								</th>
								<td>
									<input type="checkbox" name="apt_run_apt_publish_post" id="apt_run_apt_publish_post" <?php if($apt_settings['apt_run_apt_publish_post'] == 1) echo 'checked="checked"'; ?>> <label for="apt_run_apt_publish_post">Published or updated</label><br />
									<input type="checkbox" name="apt_run_apt_wp_insert_post" id="apt_run_apt_wp_insert_post" <?php if($apt_settings['apt_run_apt_wp_insert_post'] == 1) echo 'checked="checked"'; ?>> <label for="apt_run_apt_wp_insert_post">Inserted</label> <span class="apt_help" title="If enabled, APT will process posts created by the function 'wp_insert_post' (other plugins usually use this function to add posts directly to the database).">i</span><br />
									<input type="checkbox" name="apt_run_apt_save_post" id="apt_run_apt_save_post" <?php if($apt_settings['apt_run_apt_save_post'] == 1) echo 'checked="checked"'; ?> onClick="if(document.getElementById('apt_run_apt_save_post').checked){return confirm('Are you sure? If enabled, the plugin will process posts automatically after every manual AND automatic post save!')}"> <label for="apt_run_apt_save_post">Saved</label> <span class="apt_help" title="If enabled, APT will process posts when they're saved (that includes automatic saves), published or updated.">i</span>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									Analyzed post fields: <span class="apt_help" title="APT will look for keywords and their related words in selected areas.">i</span>
								</th>
								<td>
									<input type="checkbox" name="apt_title" id="apt_title" <?php if($apt_settings['apt_title'] == 1) echo 'checked="checked"'; ?>> <label for="apt_title">Title</label><br />
									<input type="checkbox" name="apt_content" id="apt_content" <?php if($apt_settings['apt_content'] == 1) echo 'checked="checked"'; ?>> <label for="apt_content">Body content</label><br />
									<input type="checkbox" name="apt_excerpt" id="apt_excerpt" <?php if($apt_settings['apt_excerpt'] == 1) echo 'checked="checked"'; ?>> <label for="apt_excerpt">Excerpt</label>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									Search for these items: <span class="apt_help" title="This is useful if you don't want to add tags (or categories) if their names (or category IDs) are found in posts.">i</span>
								</th>
								<td>
									<input type="checkbox" name="apt_search_for_keyword_names" id="apt_search_for_keyword_names" <?php if($apt_settings['apt_search_for_keyword_names'] == 1) echo 'checked="checked"'; ?>> <label for="apt_search_for_keyword_names">Keyword name</label><br />
									<input type="checkbox" name="apt_search_for_related_words" id="apt_search_for_related_words" <?php if($apt_settings['apt_search_for_related_words'] == 1) echo 'checked="checked"'; ?>> <label for="apt_search_for_related_words">Related words</label>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									Old tags handling: <span class="apt_help" title="This option determines what happens if a post already has tags.">i</span>
								</th>
								<td>
									<input type="radio" name="apt_old_tags_handling" id="apt_old_tags_handling_1" value="1" <?php if($apt_settings['apt_old_tags_handling'] == 1) echo 'checked="checked"'; ?>> <label for="apt_old_tags_handling_1">Append new tags to old tags</label><br />
									<input type="radio" name="apt_old_tags_handling" id="apt_old_tags_handling_2" value="2" <?php if($apt_settings['apt_old_tags_handling'] == 2) echo 'checked="checked"'; ?>> <label for="apt_old_tags_handling_2">Replace old tags with newly generated tags</label><br />
									<span class="apt_sub_option"><input type="checkbox" name="apt_old_tags_handling_2_remove_old_tags" id="apt_old_tags_handling_2_remove_old_tags" <?php if($apt_settings['apt_old_tags_handling_2_remove_old_tags'] == 1) echo 'checked="checked"'; ?>> <label for="apt_old_tags_handling_2_remove_old_tags">Remove old tags if new ones aren't added</label> <span class="apt_help" title="Already assigned tags will be removed from posts even if the plugin doesn't add new ones (useful for removing old non-relevant tags).">i</span><br />
									<input type="radio" name="apt_old_tags_handling" id="apt_old_tags_handling_3" value="3" <?php if($apt_settings['apt_old_tags_handling'] == 3) echo 'checked="checked"'; ?>> <label for="apt_old_tags_handling_3">Do nothing</label> <span class="apt_help" title="The tagging function will skip posts which already have tags.">i</span>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="apt_tag_limit">Max # of tags per post:</label> <span class="apt_help" title="APT won't assign more tags than the specified number.">i</span>
								</th>
								<td>
									 <input type="text" name="apt_tag_limit" id="apt_tag_limit" value="<?php echo $apt_settings['apt_tag_limit']; ?>" maxlength="10" size="4"><br />
								</td>
							</tr>
						</table>

						<h3 class="title">Advanced settings</h3>
						<table class="form-table">
							<tr valign="top">
								<th scope="row">
									<label for="apt_word_separators">Word separators:</label> <span class="apt_help" title="Each string/character (separated by &quot;<?php echo htmlspecialchars($apt_settings['apt_string_separator']); ?>&quot;) will be treated as a word separator. If you want to use a character identical to the string separator, enter its HTML entity number. (Example: If the current string separator is a comma, use the following HTML entity as a word separator instead: &quot;&amp;#44;&quot;) If no separators are set, a space will be used as a default word separator.">i</span>
								</th>
								<td>
									<input type="text" name="apt_word_separators" id="apt_word_separators" value="<?php echo htmlspecialchars(implode($apt_settings['apt_string_separator'], $apt_settings['apt_word_separators'])); ?>" maxlength="5000" size="45"><br />
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									Content processing: <span class="apt_help" title="Various operations which are executed when analyzed content is being processed (mostly in the order that they are listed below).">i</span>
								</th>
								<td>
									<input type="checkbox" name="apt_wildcards" id="apt_wildcards" <?php if($apt_settings['apt_wildcards'] == 1) echo 'checked="checked"'; ?>> <label for="apt_wildcards">Wildcard support</label> <span class="apt_help" title="If enabled, you can use the wildcard character (&quot;<?php echo htmlspecialchars($apt_settings['apt_wildcard_character']); ?>&quot;) to match any string in related words. Example: the pattern &quot;cat<?php echo htmlspecialchars($apt_settings['apt_wildcard_character']); ?>&quot; will match words &quot;cat&quot;, &quot;cats&quot; and &quot;category&quot;, the pattern &quot;c<?php echo htmlspecialchars($apt_settings['apt_wildcard_character']); ?>t&quot; will match &quot;cat&quot;, &quot;cot&quot; etc. (but also &quot;ct&quot;)">i</span><br />
									<input type="checkbox" name="apt_substring_analysis" id="apt_substring_analysis" <?php if($apt_settings['apt_substring_analysis'] == 1) echo 'checked="checked"'; ?>> <label for="apt_substring_analysis">Analyze only</label> <input type="text" name="apt_substring_analysis_length" value="<?php echo $apt_settings['apt_substring_analysis_length']; ?>" maxlength="10" size="4"> characters starting at position <input type="text" name="apt_substring_analysis_start" value="<?php echo $apt_settings['apt_substring_analysis_start']; ?>" maxlength="10" size="4"> <span class="apt_help" title="This option is useful if you don't want to analyze all content. It behaves like the PHP function &quot;substr&quot;, you can also enter sub-zero values.">i</span><br />
									<input type="checkbox" name="apt_ignore_case" id="apt_ignore_case" <?php if($apt_settings['apt_ignore_case'] == 1) echo 'checked="checked"'; ?>> <label for="apt_ignore_case">Ignore case</label> <span class="apt_help" title="Ignore case of keywords, related words and post content. (Note: This option will convert all these strings to lowercase)">i</span><br />
									<input type="checkbox" name="apt_strip_tags" id="apt_strip_tags" <?php if($apt_settings['apt_strip_tags'] == 1) echo 'checked="checked"'; ?>> <label for="apt_strip_tags">Strip HTML, PHP, JS and CSS tags from analyzed content</label> <span class="apt_help" title="Ignore PHP/HTML/JavaScript/CSS code. (If enabled, only the word &quot;green&quot; will not be ignored in the following example: &lt;span title=&quot;red&quot;&gt;green&lt;/span&gt;)">i</span><br />
									<input type="checkbox" name="apt_decode_html_entities_word_separators" id="apt_decode_html_entities_word_separators" <?php if($apt_settings['apt_decode_html_entities_word_separators'] == 1) echo 'checked="checked"'; ?>> <label for="apt_decode_html_entities_word_separators">Decode HTML entities in word separators</label> <span class="apt_help" title="Convert HTML entities in word separators to their applicable characters.">i</span><br />
									<input type="checkbox" name="apt_decode_html_entities_analyzed_content" id="apt_decode_html_entities_analyzed_content" <?php if($apt_settings['apt_decode_html_entities_analyzed_content'] == 1) echo 'checked="checked"'; ?>> <label for="apt_decode_html_entities_analyzed_content">Decode HTML entities in analyzed content</label> <span class="apt_help" title="Convert HTML entities in analyzed content to their applicable characters.">i</span><br />
									<input type="checkbox" name="apt_decode_html_entities_related_words" id="apt_decode_html_entities_related_words" <?php if($apt_settings['apt_decode_html_entities_related_words'] == 1) echo 'checked="checked"'; ?>> <label for="apt_decode_html_entities_related_words">Decode HTML entities in related words</label> <span class="apt_help" title="Convert HTML entities in related words to their applicable characters.">i</span><br />
									<input type="checkbox" name="apt_replace_whitespaces" id="apt_replace_whitespaces" <?php if($apt_settings['apt_replace_whitespaces'] == 1) echo 'checked="checked"'; ?>> <label for="apt_replace_whitespaces">Replace whitespace characters with spaces</label> <span class="apt_help" title="If enabled, whitespace characters (spaces, tabs and newlines) will be replaced with spaces. This option will affect both the haystack (analyzed content) and the needle (keywords).">i</span><br />
									<input type="checkbox" name="apt_replace_nonalphanumeric" id="apt_replace_nonalphanumeric" <?php if($apt_settings['apt_replace_nonalphanumeric'] == 1) echo 'checked="checked"'; ?>> <label for="apt_replace_nonalphanumeric">Replace non-alphanumeric characters with spaces</label> <span class="apt_help" title="If enabled, currently set word separators will be ignored and only a space will be used as a default word separator. This option will affect both the haystack (analyzed content) and the needle (keywords).">i</span><br />
									<span class="apt_sub_option"><input type="checkbox" name="apt_dont_replace_wildcards" id="apt_dont_replace_wildcards" <?php if($apt_settings['apt_dont_replace_wildcards'] == 1) echo 'checked="checked"'; ?>> <label for="apt_dont_replace_wildcards">Don't replace wildcard characters</label> <span class="apt_help" title="This option is required if you want to use wildcards.">i</span>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="apt_post_types">Allowed post types:</label> <span class="apt_help" title="Only specified post types (separated by &quot;<?php echo htmlspecialchars($apt_settings['apt_string_separator']); ?>&quot;) will be processed. Example: &quot;post<?php echo htmlspecialchars($apt_settings['apt_string_separator']); ?>page&quot;.">i</span>
								</th>
								<td>
									<input type="text" name="apt_post_types" id="apt_post_types" value="<?php echo htmlspecialchars(implode($apt_settings['apt_string_separator'], $apt_settings['apt_post_types'])); ?>" maxlength="5000" size="15">
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="apt_post_statuses">Allowed post statuses:</label> <span class="apt_help" title="Only posts with these statuses (separated by &quot;<?php echo htmlspecialchars($apt_settings['apt_string_separator']); ?>&quot;) will be processed. You can use these statuses: &quot;auto-draft&quot;, &quot;draft&quot;, &quot;future&quot;, &quot;inherit&quot;, &quot;pending&quot;, &quot;private&quot;, &quot;publish&quot;, &quot;trash&quot;.">i</span></td>
								</th>
								<td><input type="text" name="apt_post_statuses" id="apt_post_statuses" value="<?php echo htmlspecialchars(implode($apt_settings['apt_string_separator'], $apt_settings['apt_post_statuses'])); ?>" maxlength="5000" size="15"></td></tr>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="apt_taxonomy_name">Affected taxonomy:</label> <span class="apt_help" title="This taxonomy will be used for adding terms (keywords) to posts. Example: &quot;post_tag&quot; or &quot;category&quot;. Using multiple taxonomies at once is not possible. (If you want to use APT to add categories to posts, see FAQ for more information.)">i</span>
								</th>
								<td>
									<input type="text" name="apt_taxonomy_name" id="apt_taxonomy_name" value="<?php echo htmlspecialchars($apt_settings['apt_taxonomy_name']); ?>" maxlength="5000" size="15">
								</td>
							</tr>
						</table>

						<h3 class="title">Miscellaneous</h3>
						<table class="form-table">
							<tr valign="top">
								<th scope="row">
									<label for="apt_wildcard_character">Wildcard character:</label> <span class="apt_help" title="Using an asterisk is recommended. If you change the value, all occurrences of old wildcard characters in related words will be changed.">i</span>
								</th>
								<td>
									<input type="text" name="apt_wildcard_character" id="apt_wildcard_character" value="<?php echo htmlspecialchars($apt_settings['apt_wildcard_character']); ?>" maxlength="5000" size="3">
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="apt_string_separator">String separator:</label> <span class="apt_help" title="For separation of word separators, post types & statuses, related words etc. Using a comma is recommended. If you change the value, all occurrences of old string separators will be changed.">i</span>
								</th>
								<td>
									<input type="text" name="apt_string_separator" id="apt_string_separator" value="<?php echo htmlspecialchars($apt_settings['apt_string_separator']); ?>" maxlength="5000" size="3">
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="apt_wildcard_regex">Wildcard pattern:</label> <span class="apt_help" title="This regular expression is used to match strings represented by wildcards. The regex pattern MUST be enclosed by ROUND brackets! Examples: &quot;(.*)&quot; matches any string; &quot;([a-zA-Z0-9]*)&quot; matches alphanumeric strings only.">i</span>
								</th>
								<td>
									<input type="text" name="apt_wildcard_regex" id="apt_wildcard_regex" value="<?php echo htmlspecialchars($apt_settings['apt_wildcard_regex']); ?>" maxlength="5000" size="15">
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									Warning messages: <span class="apt_help" title="Warnings can be hidden if you think that they are annoying.">i</span>
								</th>
								<td>
									<input type="checkbox" name="apt_warning_messages" id="apt_warning_messages" <?php if($apt_settings['apt_warning_messages'] == 1) echo 'checked="checked"'; ?> onClick="if(!document.getElementById('apt_warning_messages').checked){return confirm('Are you sure? If disabled, the plugin will NOT display various important messages!')}"> <label for="apt_warning_messages">Display warning messages</label>
								</td>
							</tr>

							<tr valign="top">
								<th scope="row">
									Input correction: <span class="apt_help" title="Automatically modify user inputs - remove unnecessary spaces, multiple whitespace characters, wildcards, string separators etc. Automatic input correction isn't enabled when keywords are being imported.">i</span>
								</th>
								<td>
									<input type="checkbox" name="apt_input_correction" id="apt_input_correction" <?php if($apt_settings['apt_input_correction'] == 1) echo 'checked="checked"'; ?>> <label for="apt_input_correction">Automatically correct user inputs</label>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									Automatic backups: <span class="apt_help" title="APT can automatically export your keywords and related words and save the file in the backup directory (<?php echo $apt_backup_dir_abs_path; ?>).">i</span>
								</th>
								<td>
									<input type="checkbox" name="apt_create_backup_when_updating" id="apt_create_backup_when_updating" <?php if($apt_settings['apt_create_backup_when_updating'] == 1) echo 'checked="checked"'; ?>> <label for="apt_create_backup_when_updating">Create a backup of keywords when updating the plugin</label>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="apt_stored_backups">Max # of stored backups:</label> <span class="apt_help" title="The maximum number of generated backups (CSV files) stored in the backup directory (<?php echo $apt_backup_dir_abs_path; ?>). The extra oldest file will be always automatically deleted when creating a new one.">i</span>
								</th>
								<td>
									<input type="text" name="apt_stored_backups" id="apt_stored_backups" value="<?php echo $apt_settings['apt_stored_backups']; ?>" maxlength="10" size="3">
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									Keyword editor mode: <span class="apt_help" title="This feature may be needed if the plugin stores a lot keywords in the database and your PHP configuration prevents input fields from being submitted if there's too many of them (current value of the &quot;max_input_vars&quot; variable: <?php echo $apt_max_input_vars_value; ?>). See FAQ for more information.">i</span>
								</th>
								<td>
									<input type="radio" name="apt_keyword_editor_mode" id="apt_keyword_editor_mode_1" value="1" <?php if($apt_settings['apt_keyword_editor_mode'] == 1) echo 'checked="checked"'; ?>> <label for="apt_keyword_editor_mode_1">Multiple input fields for every keyword</label> <span class="apt_help" title="If enabled, all keywords and their related words will be editable via their own input fields.">i</span><br />
									<input type="radio" name="apt_keyword_editor_mode" id="apt_keyword_editor_mode_2" value="2" <?php if($apt_settings['apt_keyword_editor_mode'] == 2) echo 'checked="checked"'; ?>> <label for="apt_keyword_editor_mode_2">Single input field for all keywords <span class="apt_help" title="If enabled, all keywords and their related words will be editable via a single textarea field (keywords have to be submitted in CSV format).">i</span></label>
								</td>
							</tr>
						</table>

						<p class="submit">
							<input class="button-primary" type="submit" name="apt_save_settings_button" value=" Save settings "> 
							<input class="button apt_right apt_red_background" type="submit" name="apt_restore_default_settings_button" onClick="return confirm('Do you really want to reset all settings to default values (including the deletion of all keywords)?\nYou might want to create a backup first.')" value=" Restore default settings ">
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
				<div onclick="apt_toggle_widget(2);" class="handlediv" title="Click to toggle"><br /></div>
					<h3 class="hndle"><span>Create new keyword</span></h3>
					<div class="inside" id="apt_widget_id_[2]" <?php echo apt_change_widget_visibility(2); ?>>

						<table class="apt_width_100_percent">
						<tr>
							<td class="apt_width_35_percent">Keyword name: <span class="apt_help" title="Keyword names represent tags that will be added to posts when they or their Related words are found. Example: &quot;cat&quot;">i</span></td>
							<td class="apt_width_65_percent">Related words (separated by "<strong><?php echo htmlspecialchars($apt_settings['apt_string_separator']); ?></strong>"): <span class="apt_help" title="<?php echo 'Related words are optional. Example: &quot;cats'. $apt_settings['apt_string_separator'] .'kitty'. $apt_settings['apt_string_separator'] .'meo'. $apt_settings['apt_wildcard_character'] .'w&quot;.'; ?>">i</span></td></tr>
						<tr>
							<td><input class="apt_width_100_percent" type="text" name="apt_create_keyword_name" maxlength="5000"></td>
							<td><input class="apt_width_100_percent" type="text" name="apt_create_keyword_related_words" maxlength="5000"></td>
						</tr>
						</table>

						<p>
							<input class="button" type="submit" name="apt_create_new_keyword_button" value=" Create new keyword ">
							<span class="apt_right"><small><strong>Hint:</strong> You can also create keywords directly from the APT widget displayed next to the post editor.</small></span>		
						</p>
					</div>
				</div>
				<?php wp_nonce_field('apt_create_new_keyword_nonce','apt_create_new_keyword_hash'); ?>
				</form>
				<!-- //-postbox -->

				<!-- postbox -->
				<form name="apt_import_form" action="<?php echo admin_url('options-general.php?page=automatic-post-tagger'); ?>" enctype="multipart/form-data" method="post">
				<div class="postbox">
				<div onclick="apt_toggle_widget(3);" class="handlediv" title="Click to toggle"><br /></div>
					<h3 class="hndle"><span>Import/Export keywords</span></h3>
					<div class="inside" id="apt_widget_id_[3]" <?php echo apt_change_widget_visibility(3); ?>>

						<table class="apt_width_100_percent">
						<tr>
							<td class="apt_width_35_percent">Import terms from the database: <span class="apt_help" title="This tool will import terms from the taxonomy &quot;<?php echo htmlspecialchars($apt_settings['apt_taxonomy_name']); ?>&quot;. If you import them as related words, their IDs will be saved as keyword names.">i</span></td>
							<td class="apt_width_65_percent">Import as
								
								<select name="apt_import_from_database_column">
									<option value="1" selected="selected">Keyword names</option>

									<option value="2">Related words</option>
								</select>

								<input class="button" type="submit" name="apt_import_from_database_button" value=" Import from DB " onClick="return confirm('Do you really want to import keywords from the taxonomy &quot;<?php echo htmlspecialchars($apt_settings['apt_taxonomy_name']); ?>&quot;?')">

							</td>
						</tr>
						<tr>
							<td>Import keywords from a CSV file: <span class="apt_help" title="This tool will imports keywords from a CSV file. The filename must contain the suffix &quot;<?php echo $apt_new_backup_file_name_suffix; ?>&quot;.">i</span></td>
							<td><input type="file" size="1" name="apt_uploaded_file"> <input class="button" type="submit" name="apt_import_from_file_button" value=" Import from file "></td>
						</tr>
						<tr>
							<td>Export keywords to a CSV file: <span class="apt_help" title="This tool will create a new CSV file in the backup directory (<?php echo $apt_backup_dir_abs_path; ?>).">i</span></td>
							<td><input class="button" type="submit" name="apt_export_to_file_button" value=" Export to file "></td>
						</tr>
						</table>
					</div>
				</div>

				<?php wp_nonce_field('apt_import_from_database_nonce','apt_import_from_database_hash'); ?>
				<?php wp_nonce_field('apt_import_from_file_nonce','apt_import_from_file_hash'); ?>
				<?php wp_nonce_field('apt_export_to_file_nonce','apt_export_to_file_hash'); ?>
				</form>
				<!-- //-postbox -->

				<?php
				 //this is necessary here, otherwise the keyword count won't be accurate
				$apt_settings = get_option('automatic_post_tagger');
				$apt_keywords_array = get_option('automatic_post_tagger_keywords');

				if($apt_settings['apt_keywords_total'] != 0){ //sort keywords only if there are some
					usort($apt_keywords_array, 'apt_sort_keywords'); //sort keywords by their name
				}
				?>

				<!-- postbox -->
				<form action="<?php echo admin_url('options-general.php?page=automatic-post-tagger'); ?>" method="post">
				<div class="postbox">
				<div onclick="apt_toggle_widget(4);" class="handlediv" title="Click to toggle"><br /></div>
					<h3 class="hndle"><span>Keyword editor <span class="apt_font_weight_normal"><small>(<?php echo $apt_settings['apt_keywords_total']; ?> keywords total)</span></small></span></h3>
					<div class="inside" id="apt_widget_id_[4]" <?php echo apt_change_widget_visibility(4); ?>>

						<?php
						if($apt_settings['apt_keyword_editor_mode'] == 1){
							if($apt_settings['apt_keywords_total'] != 0){
						?>
								<div class="apt_manage_keywords">
									<table class="apt_width_100_percent">
										<tr><td class="apt_width_35_percent">Keyword name</td><td style="width:63%;">Related words</td><td style="width:2%;"></td></tr>

									<?php
										foreach($apt_keywords_array as $apt_array_id => $apt_keyword_data){
									?>
										<tr>
										<td><input class="apt_width_100_percent" type="text" name="apt_keywordlist_keyword_[<?php echo $apt_keyword_data[0]; ?>]" id="apt_keywordlist_keyword_<?php echo $apt_keyword_data[0]; ?>" value="<?php echo htmlspecialchars($apt_keyword_data[1]); ?>" maxlength="5000"></td>
										<td><input class="apt_width_100_percent" type="text" name="apt_keywordlist_related_words_[<?php echo $apt_keyword_data[0]; ?>]" id="apt_keywordlist_related_words_<?php echo $apt_keyword_data[0]; ?>" value="<?php echo htmlspecialchars($apt_keyword_data[2]); ?>" maxlength="5000"></td>	
										<td><input type="checkbox" name="apt_keywordlist_checkbox_[<?php echo $apt_keyword_data[0]; ?>]" id="apt_keywordlist_checkbox_<?php echo $apt_keyword_data[0]; ?>" onclick="apt_change_background(<?php echo $apt_keyword_data[0]; ?>);"></td>
										</tr>
									<?php
										} //-for
									?>
									</table>
								</div>
							<?php
							} //-if there are keywords
							else{
								echo '<p>There aren\'t any keywords.</p>';
							} //-else there are keywords
						} //-if KEM =1
						else{ //KEM = 2
						?>
							<p>Keywords have to be submitted in CSV format. <span class="apt_help" title="Put each keyword with its related words on a new line. If you use spaces or commas in your keyword names and related words, you need to enclose these strings in quotes. Example: &quot;keyword name&quot;,&quot;related word,another related word&quot;">i</span></p>
							<textarea class="apt_manage_keywords_textarea" name="apt_keywords_textarea"><?php echo apt_export_keywords_to_textarea(); ?></textarea>
						<?php
						} //-else KEM = 1
						?>

						<?php if($apt_settings['apt_keywords_total'] != 0 OR $apt_settings['apt_keyword_editor_mode'] == 2){ ?>
							<?php if($apt_settings['apt_keyword_editor_mode'] == 1){ ?>
									<span class="apt_right"><small><strong>Hint:</strong> You can remove individual items by leaving the keyword names empty.</small></span>			
							<?php }else{ ?>
									<span class="apt_right"><small><strong>Hint:</strong> You can remove individual items by deleting their lines.</small></span>			
							<?php } ?>

							<p class="submit">
								<input class="button" type="submit" name="apt_save_keywords_button" value=" Save keywords ">

								<?php if($apt_settings['apt_keyword_editor_mode'] == 1){ ?>
									<input class="button apt_red_background apt_right apt_button_margin_left" type="submit" name="apt_delete_chosen_keywords_button" onClick="return confirm('Do you really want to delete chosen keywords?')" value=" Delete chosen keywords ">
								<?php } ?>

								<input class="button apt_red_background apt_right apt_button_margin_left" type="submit" name="apt_delete_all_keywords_button" onClick="return confirm('Do you really want to delete all keywords?')" value=" Delete all keywords ">
							</p>
						<?php } ?>
					</div>
				</div>
				<?php wp_nonce_field('apt_save_keywords_nonce','apt_save_keywords_hash'); ?>
				<?php wp_nonce_field('apt_delete_chosen_keywords_nonce','apt_delete_chosen_keywords_hash'); ?>
				<?php wp_nonce_field('apt_delete_all_keywords_nonce','apt_delete_all_keywords_hash'); ?>
				</form>
				<!-- //-postbox -->

							<?php
							$apt_select_posts_id_min = $wpdb->get_var("SELECT MIN(ID) FROM $wpdb->posts ". apt_print_sql_where_without_specified_statuses());
							$apt_select_posts_id_max = $wpdb->get_var("SELECT MAX(ID) FROM $wpdb->posts ". apt_print_sql_where_without_specified_statuses());
							?>

				<!-- postbox -->
				<form action="<?php echo admin_url('options-general.php?page=automatic-post-tagger'); ?>" method="post">
				<div class="postbox">
					<div onclick="apt_toggle_widget(5);" class="handlediv" title="Click to toggle"><br /></div>
					<h3 class="hndle"><span>Bulk tagging tool</span></h3>
					<div class="inside" id="apt_widget_id_[5]" <?php echo apt_change_widget_visibility(5); ?>>

						<table class="apt_width_100_percent">
							<tr>
								<td>Process only posts in this ID range: <span class="apt_help" title="By default all posts will be processed. Default values are being calculated by using set post types and statuses.">i</span></td>
								<td><input type="text" name="apt_bulk_tagging_range_1" value="<?php if($apt_select_posts_id_min != NULL){echo $apt_select_posts_id_min;}else{echo '0';}; ?>" maxlength="10" size="4"> - <input type="text" name="apt_bulk_tagging_range_2" value="<?php if($apt_select_posts_id_max != NULL){echo $apt_select_posts_id_max;}else{echo '0';}; ?>" maxlength="10" size="4"></td></tr>
							</tr>
							<tr>
								<td class="apt_width_35_percent"><label for="apt_bulk_tagging_posts_per_cycle">Number of posts tagged per cycle:</label> <span class="apt_help" title="How many posts should be processed every time a page is refreshed; low value helps avoid the &quot;max_execution_time&quot; error.">i</span></td>
								<td class="apt_width_65_percent"><input type="text" name="apt_bulk_tagging_posts_per_cycle" id="apt_bulk_tagging_posts_per_cycle" value="<?php echo $apt_settings['apt_bulk_tagging_posts_per_cycle']; ?>" maxlength="10" size="4"></td></tr>
							</tr>
							<tr>
								<td><label for="apt_bulk_tagging_delay">Time delay between cycles:</label> <span class="apt_help" title="Idle time between an automatic refresh of the page and processing of the next batch of posts.">i</span></td>
								<td><input type="text" name="apt_bulk_tagging_delay" value="<?php echo $apt_settings['apt_bulk_tagging_delay']; ?>" maxlength="10" size="4"> seconds</td></tr>
							</tr>
						</table>

						<p class="submit">
							<input class="button" type="submit" name="apt_bulk_tagging_button" onClick="return confirm('Do you really want to proceed?\nAny changes can\'t be reversed.')" value=" Process posts "> 
						</p>
					</div>
				</div>
				<?php wp_nonce_field('apt_bulk_tagging_nonce','apt_bulk_tagging_hash'); ?>
				</form>
				<!-- //-postbox -->

			</div>
		</div>
	</div>
</div>

<?php
} //- options page
?>
