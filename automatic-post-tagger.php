<?php
/*
Plugin Name: Automatic Post Tagger
Plugin URI: http://wordpress.org/extend/plugins/automatic-post-tagger
Description: This plugin automatically adds user-specified tags to posts.
Version: 1.0
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

## Dragons ahead! Read the code at your own risk. Don't complain if you'll get dizzy. I warned you! ##

#################################################################
#################### BASIC DECLARATIONS #########################
#################################################################
global $wpdb, $apt_table, $apt_plugin_basename;

$apt_table = $wpdb->prefix .'apt_tags';
$apt_wp_posts = $wpdb->prefix .'posts';
$apt_wp_terms = $wpdb->prefix .'terms';
$apt_wp_term_taxonomy = $wpdb->prefix .'term_taxonomy';

$apt_backup_file_name = 'apt_backup.csv';

$apt_backup_file_export_dir = WP_PLUGIN_DIR . "/" . basename(dirname(__FILE__)) . "/" . $apt_backup_file_name;
$apt_backup_file_export_url = WP_PLUGIN_URL . "/" . basename(dirname(__FILE__)) . "/" . $apt_backup_file_name;
$apt_plugin_url = WP_PLUGIN_URL . "/" . basename(dirname(__FILE__)) . "/";
$apt_plugin_basename = plugin_basename(__FILE__); //automatic-post-tagger/automatic-post-tagger.php


#################################################################
########################### FUNCTIONS ###########################
#################################################################


#################### get plugin version #########################
function apt_get_plugin_version(){ //return plugin version
	if(!function_exists('get_plugins')){
		require_once(ABSPATH . 'wp-admin/includes/plugin.php');
	}

	$apt_plugin_folder = get_plugins('/' . plugin_basename(dirname(__FILE__)));
	$apt_plugin_file = basename((__FILE__)); //automatic-post-tagger.php
	return $apt_plugin_folder[$apt_plugin_file]['Version'];
}
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
		$links[] = '<a href="http://wordpress.org/extend/plugins/automatic-post-tagger/faq">FAQ</a>';
		$links[] = '<a href="http://wordpress.org/support/plugin/automatic-post-tagger">Support</a>';
		$links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=T2QUJ4R6JHKNG">Donate</a>';
	}
	return $links;
}
#################### menu link ##################################
function apt_menu_link(){
	$page = add_options_page('Automatic Post Tagger', 'Automatic Post Tagger', 'manage_options', 'automatic-post-tagger', 'apt_options_page');
}
#################################################################
######################## ADMIN NOTICES ##########################

#################### admin notices notice ##########################
function apt_plugin_admin_notices(){
	if(current_user_can('manage_options')){

		######################## GET notifications ###################### //must be before other checks
		if(isset($_GET['n']) AND $_GET['n'] == 1){
			update_option('apt_admin_notice_install', 0); //hide activation notice
			echo '<div class="updated"><p><b>Note:</b> Managing tags (creating, importing, editing, deleting) on this page doesn\'t affect tags that are already added to your posts.</p></div>'; //display quick info for beginners
		}
		if(isset($_GET['n']) AND $_GET['n'] == 2){
			update_option('apt_admin_notice_update', 0); //hide update notice
			echo '<div class="updated"><p><b>New features in version '. get_option('apt_plugin_version') .':</b> Lorem ipsum for v1.1</p></div>'; //show new functions
		}
		if(isset($_GET['n']) AND $_GET['n'] == 3){
			update_option('apt_admin_notice_donate', 0); //hide donation notice
		}
		if(isset($_GET['n']) AND $_GET['n'] == 4){
			update_option('apt_admin_notice_donate', 0); //hide donation notice and display another notice (below)
			echo '<div class="updated"><p><b>Thank you for donating.</b> If you filled in the URL of your website, it should appear on the list of recent contributors in the next 48 hours.</p></div>'; //show "thank you" message
		}



		if(get_option('apt_admin_notice_install') == 1){ //show link to the setting page after installing
			echo '<div id="message" class="updated"><p><b>Automatic Post Tagger</b> has been installed. <a href="'. admin_url('options-general.php?page=automatic-post-tagger&n=1') .'">Set up the plugin &raquo;</a></p></div>';
		}
		if(get_option('apt_admin_notice_update') == 1){ //show link to the setting page after updating
			echo '<div id="message" class="updated"><p><b>Automatic Post Tagger</b> has been updated. <a href="'. admin_url('options-general.php?page=automatic-post-tagger&n=2') .'">Find out what\'s new &raquo;</a></p></div>';
		}

		if(get_option('apt_admin_notice_donate') == 1){ //determine if the donation notice was not dismissed
			if(((time() - get_option('apt_stats_install_date')) >= 604800) AND (get_option('apt_stats_assigned_tags') >= 10)){ //show donation notice after a week (604800 seconds) and if the plugin added more than 10 tags
				echo '<div id="message" class="updated"><p>
					<b>Thanks for using APT!</b> You installed this plugin over a week ago. Since that time it has assigned <b>'. get_option('apt_stats_assigned_tags') .' tags</b> to your posts.
					If you are satisfied with the results, isn\'t it worth at least a few dollars? Donations motivate the developer to continue working on this plugin. <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=T2QUJ4R6JHKNG" title="Donate with Paypal"><b>Sure, no problem!</b></a>

					<span style="float:right">
					<a href="'. admin_url('options-general.php?page=automatic-post-tagger&n=3') .'" title="Hide this notification"><small>No thanks, don\'t bug me anymore!</small></a> |
					<a href="'. admin_url('options-general.php?page=automatic-post-tagger&n=4') .'" title="Hide this notification"><small>OK, but I donated already!</small></a>
					</span>
				</p></div>';
			}
		}//-if donations
	}//-if admin check
}
#################################################################
####################### MYSQL MANAGEMENT ########################

#################### table creation function ####################
function apt_create_table(){ //this functions defines the plugin table structure - it is called when the plugin is activated
	global $wpdb, $apt_table;

	//this should prevent creating tables with different charset and collation
	if(!empty($wpdb->charset)){
		$apt_chararset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
	}
	if(!empty($wpdb->collate)){
	        $apt_chararset_collate .= " COLLATE {$wpdb->collate}";
	}

	//primary key should be tag because when importing tags some may have the same id, so we need to compare the tag, not id - that is used only for deleting by checking checkboxes
	$sql = 'CREATE TABLE '. $apt_table .'(
		id INT NOT NULL auto_increment,
		tag VARCHAR (255),
		related_words VARCHAR (255),
		UNIQUE KEY (tag),
		PRIMARY KEY  (id)
		) '. $apt_chararset_collate .';';


	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}
#################### table deletion function ####################
function apt_drop_table(){
	global $wpdb, $apt_table;
	mysql_query("DROP TABLE $apt_table"); 
}
#################### activate function ##########################
function apt_install_plugin(){ //runs only after MANUAL activation!
//also used for restoring settings

	apt_create_table(); //creating table for tags

	add_option('apt_plugin_version', apt_get_plugin_version(), '', 'no'); //for future updates of the plugin
	add_option('apt_admin_notice_install', '1', '', 'no'); //option for displaying installation notice
	add_option('apt_admin_notice_update', '0', '', 'no'); //option for displaying update notice
	add_option('apt_admin_notice_donate', '1', '', 'no'); //option for displaying donation notice

	add_option('apt_stats_current_tags', '0', '', 'no');
	add_option('apt_stats_assigned_tags', '0', '', 'no');
	add_option('apt_stats_install_date', time(), '', 'no');

	add_option('apt_post_analysis_title', '1', '', 'no');
	add_option('apt_post_analysis_content', '1', '', 'no');
	add_option('apt_post_analysis_excerpt', '0', '', 'no');
	add_option('apt_handling_current_tags', '1', '', 'no');
	add_option('apt_miscellaneous_tag_maximum', '20', '', 'no');
	add_option('apt_miscellaneous_tagging_occasion', '1', '', 'no');
	add_option('apt_miscellaneous_wildcards', '0', '', 'no');
}
#################### update function ############################
function apt_update_plugin(){ //runs when all plugins are loaded (needs to be deleted after register_update_hook is available)
	if(current_user_can('manage_options')){
		if(apt_get_plugin_version() != get_option('apt_plugin_version')){

			#### now comes everything what must be changed in the new version
			if(get_option('apt_plugin_version') == '1.0'){ //upgrade to v1.1 from 1.0:
				//changes
			}

			#### -/changes

			update_option('apt_admin_notice_update', 1); //we want to show the admin notice after upgrading, right?
			update_option('apt_plugin_version', apt_get_plugin_version(), '', 'no'); //update plugin version in DB
		}
	}
}
#################### uninstall function #########################
function apt_uninstall_plugin(){ //runs after uninstalling of the plugin
//also used for restoring settings

	apt_drop_table();

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
}

#################################################################
########################## TAGGING ENGINE #######################
#################################################################
function apt_tagging_algorithm($post_id){ //this function is for adding tags to only one post - mass adding should be handled by using a loop
	global $wpdb, $apt_table, $apt_wp_posts;
	$apt_post_current_tag_count = count(wp_get_post_terms($post_id, 'post_tag', array("fields" => "names")));
	$apt_tag_maximum = get_option('apt_miscellaneous_tag_maximum');

	#################################################################
	### stopping execution to prevent the script from doing unuseful job:

	//we do not have the ID of the post, stop!
	if ($post_id == false OR $post_id == null){
		return 1;
	}
	//the user does not want us to add tags if the post already have them, stop!
	if(($apt_post_current_tag_count > 0) AND get_option('apt_handling_current_tags') == 3){
		return 2;
	}
	//number of current tags is the same or greater than the maximum so we can't append tags, stop! (replacement is ok, 3rd option won't be let here)
	if(($apt_post_current_tag_count >= $apt_tag_maximum) AND get_option('apt_handling_current_tags') == 1){
		return 3;
	}

//TODO:	if($apt_moron_check == 'check'){ //if we got a second parameter, don't check user-moron scenarios again
//I need to find out how to pass the second argument to stop checking this when I run this function multiple times

		### USER-MORON SCENARIOS
		//the user does not want to add any tags, stop!
		if($apt_tag_maximum == 0){
			return 4;
		}
		//there are not any tags to add (table is empty), stop!
		if (mysql_num_rows(mysql_query('SELECT id FROM '. $apt_table)) == 0){
			return 5;
		}
		//the user does not want us to search anything, stop!
		if(get_option('apt_post_analysis_title') == 0 AND get_option('apt_post_analysis_content') == 0 AND get_option('apt_post_analysis_excerpt') == 0){
			return 6;
		}

//	}//-moron check



	#################################################################

	//if this isn't a revision - not sure if needed, but why not use it, huh?
	if(!wp_is_post_revision($post_id)){
		$apt_post_title = $wpdb->get_var("SELECT post_title FROM $apt_wp_posts WHERE ID = $post_id LIMIT 0, 1");
		$apt_post_content = $wpdb->get_var("SELECT post_content FROM $apt_wp_posts WHERE ID = $post_id LIMIT 0, 1");
		$apt_post_excerpt = $wpdb->get_var("SELECT post_excerpt FROM $apt_wp_posts WHERE ID = $post_id LIMIT 0, 1");
		$apt_post_analysis_haystack_string = '';

		//we need to find out what should be searching for
		if(get_option('apt_post_analysis_title') == 1){ //include title
			$apt_post_analysis_haystack_string = $apt_post_analysis_haystack_string .' '. $apt_post_title;
		}
		if(get_option('apt_post_analysis_content') == 1){ //include content
			$apt_post_analysis_haystack_string = $apt_post_analysis_haystack_string .' '. $apt_post_content;
		}
		if(get_option('apt_post_analysis_excerpt') == 1){ //include excerpt
			$apt_post_analysis_haystack_string = $apt_post_analysis_haystack_string .' '. $apt_post_excerpt;
		}


		//preparing the string for searching
		setlocale(LC_ALL, 'en_GB'); //set locale
		$apt_post_analysis_haystack_string = iconv('UTF-8', 'ASCII//TRANSLIT', $apt_post_analysis_haystack_string); //replace diacritic character with ascii equivalents
		$apt_post_analysis_haystack_string = strtolower($apt_post_analysis_haystack_string); //make it lowercase
		$apt_post_analysis_haystack_string = wp_strip_all_tags($apt_post_analysis_haystack_string); //remove HTML, PHP and JS tags
		$apt_post_analysis_haystack_string = preg_replace("/[^a-zA-Z0-9\s]/", ' ', $apt_post_analysis_haystack_string); //replace all non-alphanumeric-characters with space
		$apt_post_analysis_haystack_string = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $apt_post_analysis_haystack_string); //replace whitespaces and newline characters with a space
		$apt_post_analysis_haystack_string = ' '. $apt_post_analysis_haystack_string .' '; //we need to add a space before and after the string: the engine is looking for ' string ' (with space at the beginning and the end, so it won't find e.g. ' ice ' in a word ' iceman ')


		$apt_tags_to_add_array = array(); //array of tags that will be added to a post
		$apt_table_rows_tag_related_words = mysql_query("SELECT tag,related_words FROM $apt_table");
		$apt_table_related_words = mysql_query("SELECT related_words FROM $apt_table");

		if(get_option('apt_handling_current_tags') == 1){
			$apt_tags_to_add_max = $apt_tag_maximum - $apt_post_current_tag_count;
		}
		if(get_option('apt_handling_current_tags') == 2 OR 3){
			$apt_tags_to_add_max = $apt_tag_maximum;
		}



		while($apt_table_cell = mysql_fetch_array($apt_table_rows_tag_related_words, MYSQL_NUM)){ //loop handling every row in the table
			$apt_table_row_related_words_count = substr_count($apt_table_cell[1], ';') + 1; //variable prints number of related words in the current row that is being "browsed" by the while; must be +1 higher than the number of semicolons!

			//resetting variables - this must be here or the plugin will add non-relevant tags 
			$apt_table_tag_found = 0;
			$apt_table_related_word_found = 0;

			if(!empty($apt_table_cell[1])){ //if there are not any related words, do not perform this action so the tag won't be added (adds tag always when no related words are assigned to it)
				for($i=0; $i < $apt_table_row_related_words_count; $i++){ //loop handling substrings in the 'related_words' column - $i must be 0 because array always begin with 0!
					$apt_table_cell_substrings = explode(';', $apt_table_cell[1], $apt_table_row_related_words_count);

					//trimming the substring - it no multiple whitespace characters etc. are removed
					$apt_substring_needle = ' '. strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $apt_table_cell_substrings[$i])) .' ';
					$apt_substring_needle_wildcards = '/'. str_replace('*', '([a-zA-Z0-9]*)', $apt_substring_needle) .'/';


					//wildcard search for related words
					if(get_option('apt_miscellaneous_wildcards') == 1){ //run if wildcards are allowed
						if(preg_match($apt_substring_needle_wildcards, $apt_post_analysis_haystack_string)){
							$apt_table_related_word_found = 1; //set variable to 1
						}
					}
					else{ //if wildcards are not allowed, continue searching without using a regular expression
						if(strstr($apt_post_analysis_haystack_string, $apt_substring_needle)){ //strtolowered and asciied ' substring ' has been found
							$apt_table_related_word_found = 1; //set variable to 1
						}
					}//-if wildcard check
				}//-for
			}//-if for related words check


			//trimming the tag - it no multiple whitespace characters etc. are removed
			$apt_tag_needle = ' '. strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $apt_table_cell[0])) .' ';

			//searching for tags (note for future me-dumbass: we do not want to check for wildcards, they cannot be used in tags, moron!
			if($apt_table_related_word_found == 0){ //do not continue searching if the related word has been found
				if(strstr($apt_post_analysis_haystack_string, $apt_tag_needle)){ //strtolowered and asciied ' tag ' has been found
					$apt_table_tag_found = 1; //set variable to 1
				}
			}//-if related word not found

			//adding tags to the array
			if($apt_table_related_word_found == 1 OR $apt_table_tag_found == 1){ //tag or one of related_words has been found, add tag to array!
					array_push($apt_tags_to_add_array, $apt_table_cell[0]); //add tag to the array
					update_option('apt_stats_assigned_tags', get_option('apt_stats_assigned_tags') + 1); //add 1 for every tag added to a post
			}//--if for pushing tag to array


			if(count($apt_tags_to_add_array) == $apt_tags_to_add_max){//check if the array is equal to the max. number of tags per one post, break the loop
				break; //stop the loop, the max. number of tags was hit
			}
		}//-while


		//if the post has already tags, we should decide what to do with them
		if(get_option('apt_handling_current_tags') == 1 OR 3){
			wp_set_post_terms($post_id, $apt_tags_to_add_array, 'post_tag', true); //append tags

		}
		if(get_option('apt_handling_current_tags') == 2 AND (count($apt_tags_to_add_array) != 0)){ //if the plugin generated some tags, replace the old ones,otherwise do not continue!
			wp_set_post_terms($post_id, $apt_tags_to_add_array, 'post_tag', false); //replace tags

			/*
			//alternative way of deleting current tags
			$apt_delete_tags_from_post = mysql_query("SELECT * FROM ". $wpdb->prefix ."term_relationships tr JOIN ". $wpdb->prefix ."term_taxonomy tt ON tr.term_taxonomy_id=tt.term_taxonomy_id WHERE tr.object_id='$post_id' AND tt.taxonomy='post_tag'");
			while ($arr = mysql_fetch_array($apt_delete_tags_from_post)){
				mysql_query("DELETE FROM ".$wpdb->prefix."term_relationships WHERE term_taxonomy_id='". $arr['term_taxonomy_id'] ."'");
			}
			wp_set_post_terms($post_id, $apt_tags_to_add_array, 'post_tag', true); //append tags
			*/
		}

	}//- revision check
}//-end of tagging function


#################################################################
########################## HOOKS ################################
#################################################################

if(is_admin()){ //these functions will be executed only if the admin panel is being displayed for performance reasons
	add_action('admin_menu', 'apt_menu_link');

	if($GLOBALS['pagenow'] == 'plugins.php'){ //check if the admin is on page plugins.php
		add_filter('plugin_action_links', 'apt_plugin_action_links', 12, 2);
		add_filter('plugin_row_meta', 'apt_plugin_meta_links', 12, 2);
	}

	if(in_array($GLOBALS['pagenow'], array('plugins.php', 'update-core.php'))){ //check if the admin is on pages update-core.php, plugins.php
		add_action('plugins_loaded', 'apt_update_plugin'); 

		register_activation_hook(__FILE__, 'apt_install_plugin');
		register_uninstall_hook(__FILE__, 'apt_uninstall_plugin');
	}
	add_action('admin_notices', 'apt_plugin_admin_notices', 20); //check for admin notices
}

if(get_option('apt_miscellaneous_tagging_occasion') == 1){ //trigger tagging when publishing the post
	add_action('publish_post','apt_tagging_algorithm', 25); //lower priority (default 10), accepted args = 1
}
if(get_option('apt_miscellaneous_tagging_occasion') == 2){ //trigger tagging when saving the post
	add_action('save_post','apt_tagging_algorithm', 25);//lower priority (default 10), accepted args = 1
}

#################################################################
########################## OPTIONS PAGE #########################
#################################################################

function apt_options_page(){ //loads options page
######################## DECLARATIONS ###########################
	global $wpdb, $apt_table, $apt_wp_posts, $apt_wp_terms, $apt_wp_term_taxonomy, $apt_admin_notices_current, $apt_plugin_url, $apt_backup_file_name, $apt_backup_file_export_dir, $apt_backup_file_export_url;
	setlocale(LC_ALL, 'en_GB'); //set locale
	wp_enqueue_style('apt-style', plugin_dir_url( __FILE__ ) . 'style.css'); //load .css style
?>

<div class="wrap">
<h2>Automatic Post Tagger</h2>

<?php
######################## SAVING OPTIONS #########################
if(isset($_POST['apt_save_settings_button'])){ //saving all form data
	update_option('apt_post_analysis_title', (isset($_POST['apt_post_analysis_title'])) ? '1' : '0');
	update_option('apt_post_analysis_content', (isset($_POST['apt_post_analysis_content'])) ? '1' : '0');
	update_option('apt_post_analysis_excerpt', (isset($_POST['apt_post_analysis_excerpt'])) ? '1' : '0');
	update_option('apt_handling_current_tags', $_POST['apt_handling_current_tags']);
	update_option('apt_miscellaneous_wildcards', (isset($_POST['apt_miscellaneous_wildcards'])) ? '1' : '0');
	update_option('apt_miscellaneous_tagging_occasion', $_POST['apt_miscellaneous_tagging_occasion']);

	//making sure that people won't save rubbish in the DB
	if(is_numeric($_POST['apt_miscellaneous_tag_maximum'])){
		update_option('apt_miscellaneous_tag_maximum', $_POST['apt_miscellaneous_tag_maximum']);
	}
	else{
		echo '<div class="error"><p><b>Error:</b> The option "apt_miscellaneous_tag_maximum" couldn\'t be saved because the sent value wasn\'t numeric.</p></div>'; //user-moron scenario
	}

	echo '<div class="updated"><p>Your settings have been saved.</p></div>'; //confirm message
}

if(isset($_POST['apt_restore_default_settings_button'])){ //resetting settings
	apt_uninstall_plugin();
	apt_install_plugin();
	echo '<div class="updated"><p>Default settings have been restored.</p></div>'; //confirm message
}


#################### tag management ##############################
if(isset($_POST['apt_create_a_new_tag_button'])){ //creating a new tag wuth relaterd words

$lol = mysql_query("SELECT id FROM $apt_table WHERE tag = '". $_POST['apt_create_tag'] ."' LIMIT 0,1");

if(empty($_POST['apt_create_tag'])){ //checking if the value of the tag isn't empty
	echo '<div class="error"><p><b>Error:</b> You can\'t create a tag that does not have a name.</p></div>';
}
	else{
		if(mysql_num_rows($lol)){ //checking if the tag exists
			echo '<div class="error"><p><b>Error:</b> Tag <b>"'. $_POST['apt_create_tag'] .'"</b> already exists!</p></div>';
		} 
		else{ //if the tag is not in DB, create one

			$apt_created_tag_trimmed = trim($_POST['apt_create_tag']); //replacing ONLY whitespace characters from beginning and end (we could remove multiple characters like ';', but they are not used here to separate anything, so we let the user to do what he/she wants)
			$apt_created_related_words_trimmed = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $_POST['apt_create_related_words']); //replacing multiple whitespace characters with a space (we could replace them completely, but that might annoy users)
			$apt_created_related_words_trimmed = preg_replace('{;+}', ';', $apt_created_related_words_trimmed); //replacing multiple semicolons with one
			$apt_created_related_words_trimmed = preg_replace('/[\*]+/', '*', $apt_created_related_words_trimmed); //replacing multiple asterisks with one
			$apt_created_related_words_trimmed = trim(trim(trim($apt_created_related_words_trimmed), ';')); //trimming semicolons and whitespace characters from the beginning and the end

			mysql_query("INSERT IGNORE INTO $apt_table (tag, related_words) VALUES ('". $apt_created_tag_trimmed ."', '". $apt_created_related_words_trimmed ."')");
			update_option('apt_stats_current_tags', mysql_num_rows(mysql_query("SELECT id FROM $apt_table"))); //update stats


			echo '<div class="updated"><p>Tag <b>"'. $apt_created_tag_trimmed .'"</b> with '; //confirm message with a condition displaying related words if available
				if(empty($apt_created_related_words_trimmed)){
					echo 'no related words';
				}else{
					if(strstr($apt_created_related_words_trimmed, ';')){ //print single or plural form
						echo 'related words <b>"'. $apt_created_related_words_trimmed .'"</b>';
					}
					else{
						echo 'related word <b>"'. $apt_created_related_words_trimmed .'"</b>';
					}

				}
			echo ' has been created.</p></div>';

			//warning messages appearing when "unexpected" character are being saved
			if(preg_match("/[^a-zA-Z0-9\s]/", iconv('UTF-8', 'ASCII//TRANSLIT', $apt_created_tag_trimmed))){ //user-moron scenario
				echo '<div class="error"><p><b>Warning:</b> Tag name <b>"'. $apt_created_tag_trimmed .'"</b> contains non-alphanumeric characters.</p></div>'; //warning message
			}
			if(preg_match("/[^a-zA-Z0-9\s\;\*]/", iconv('UTF-8', 'ASCII//TRANSLIT', $apt_created_related_words_trimmed))){ //user-moron scenario
				echo '<div class="error"><p><b>Warning:</b> Related words "'. $apt_created_related_words_trimmed .'" contain non-alphanumeric characters.</p></div>'; //warning message
			}
			if(strstr($apt_created_related_words_trimmed, ' ;') OR strstr($apt_created_related_words_trimmed, '; ')){ //user-moron scenario
				echo '<div class="error"><p><b>Warning:</b> Related words "'. $apt_created_related_words_trimmed .'" contain extra space near the semicolon.</p></div>'; //warning message
			}
			if(strstr($apt_created_related_words_trimmed, '*') AND (get_option('apt_miscellaneous_wildcards') == 0)){ //user-moron scenario
				echo '<div class="error"><p><b>Warning:</b> Your related words contain an asterisk, but using wildcards is currently disabled!</p></div>'; //warning message
			}


		}//--else
	}//--else
}//--if


if(isset($_POST['apt_delete_all_tags_button'])){ //delete all records from $apt_table
	mysql_query('TRUNCATE TABLE '. $apt_table);
	update_option('apt_stats_current_tags', '0'); //reset stats

	echo '<div class="updated"><p>All tags have been deleted.</p></div>';
}

if(isset($_POST['apt_delete_chosen_tags_button'])){ //delete chosen records from $apt_table
	if(array_key_exists('apt_taglist_checkbox_', $_POST)){ //determine if any checkbox was checked
		foreach($_POST['apt_taglist_checkbox_'] as $id => $value){ //loop for handling checkboxes
			mysql_query("DELETE FROM $apt_table WHERE id=$id");
		}
		update_option('apt_stats_current_tags', mysql_num_rows(mysql_query("SELECT id FROM $apt_table"))); //update stats

		echo '<div class="updated"><p>All chosen tags have been deleted.</p></div>';
	}
	else{
		echo '<div class="error"><p><b>Error:</b> You must choose at least one tag in order to delete it.</p></div>';
	}
}

if(isset($_POST['apt_save_tags_button'])){ //saving changed tags

	foreach($_POST['apt_taglist_tag_'] as $id => $value){ //saving tag
		$apt_saved_tag = trim($_POST['apt_taglist_tag_'][$id]);

		if(empty($apt_saved_tag)){ //user-moron scenario
			$apt_saved_tag_empty_error = 1;
			$apt_saved_tag = $wpdb->get_var('SELECT tag FROM '. $apt_table .' WHERE id='. $id); //tag was saved as empty string, restoring previous value
		}
		else{ //save if not empty
			if(preg_match("/[^a-zA-Z0-9\s]/", iconv('UTF-8', 'ASCII//TRANSLIT', $apt_saved_tag))){ //user-moron scenario
				$apt_saved_tag_aplhanumeric_warning = 1;
			}

			mysql_query("UPDATE $apt_table SET tag='". $apt_saved_tag ."' WHERE id='". $id ."'");
		}
	}

	foreach($_POST['apt_taglist_related_words_'] as $id => $value){ //saving related words
		$apt_saved_related_words = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', preg_replace('{;+}', ';', preg_replace('/[\*]+/', '*', trim(trim(trim($_POST['apt_taglist_related_words_'][$id]), ';')))));
		mysql_query("UPDATE $apt_table SET related_words='". $apt_saved_related_words ."' WHERE id='". $id ."'"); //handling multiple and whitespace characters is the same as in the case of creating a new tag (few rows above)

		if(!empty($apt_saved_related_words)){
			if(preg_match("/[^a-zA-Z0-9\s\;\*]/", iconv('UTF-8', 'ASCII//TRANSLIT', $apt_saved_related_words))){ //user-moron scenario
				$apt_saved_related_words_aplhanumeric_warning = 1;
			}
			if(strstr($apt_saved_related_words, ' ;') OR strstr($apt_saved_related_words, '; ')){ //user-moron scenario
				$apt_saved_related_words_extra_spaces_warning = 1;
			}
			if(strstr($apt_saved_related_words, '*') AND (get_option('apt_miscellaneous_wildcards') == 0)){ //user-moron scenario
				$apt_saved_related_words_asterisk_warning = 1;
			}
		}
	}

	echo '<div class="updated"><p>All tags have been saved.</p></div>';

	//warning messages appearing when "unexpected" character are being saved - user-moron scenarios
	if($apt_saved_tag_empty_error == 1){
		echo '<div class="error"><p><b>Error:</b> Some tag names were saved as empty strings, their previous values were restored.</p></div>'; //warning message
	}
	if($apt_saved_tag_aplhanumeric_warning == 1){
		echo '<div class="error"><p><b>Warning:</b> Some tag names contain non-alphanumeric characters.</p></div>'; //warning message
	}
	if($apt_saved_related_words_aplhanumeric_warning == 1){
		echo '<div class="error"><p><b>Warning:</b> Some related words contain non-alphanumeric characters.</p></div>'; //warning message
	}
	if($apt_saved_related_words_extra_spaces_warning == 1){
		echo '<div class="error"><p><b>Warning:</b> Some related words contain extra spaces near semicolons.</p></div>'; //warning message
	}
	if($apt_saved_related_words_asterisk_warning == 1){
		echo '<div class="error"><p><b>Warning:</b> Your related words contain an asterisk, but using wildcards is currently disabled!</p></div>'; //warning message
	}
}

#################### import/export ##############################
if(isset($_POST['apt_import_existing_tags_button'])){ //import current tags
//there is no need to trim tags, they should be trimmed already
	$apt_current_tags = 0;

	$apt_table_select_current_tags = mysql_query('SELECT name FROM '. $apt_wp_terms .' NATURAL JOIN '. $apt_wp_term_taxonomy .' WHERE taxonomy="post_tag"');
	while($apt_tag_id = mysql_fetch_array($apt_table_select_current_tags, MYSQL_NUM)){ //run loop to process all tags
       		mysql_query("INSERT IGNORE INTO $apt_table(tag,related_words) VALUES('". $apt_tag_id[0] ."','')");
		$apt_current_tags++;

		if(preg_match("/[^a-zA-Z0-9\s]/", iconv('UTF-8', 'ASCII//TRANSLIT', $apt_tag_id[0]))){ //user-moron scenario
			$apt_imported_current_tag_aplhanumeric_warning = 1;
		}
	}

	if($apt_current_tags != 0){
		update_option('apt_stats_current_tags', mysql_num_rows(mysql_query("SELECT id FROM $apt_table"))); //update stats
		echo '<div class="updated"><p>All <b>'. $apt_current_tags .'</b> tags have been imported.</p></div>'; //confirm message

		if($apt_imported_current_tag_aplhanumeric_warning == 1){
			echo '<div class="error"><p><b>Warning:</b> Some tag names contain non-alphanumeric characters.</p></div>'; //warning message
		}
	}
	else{
		echo '<div class="error"><p><b>Error:</b> There aren\'t any tags in your database.</p></div>'; //confirm message
	}

}

if(isset($_POST['apt_import_from_a_backup_button'])){ //import a backup file

	if($_FILES['apt_uploaded_file']['name'] == $apt_backup_file_name){ //checks if the name of uploaded file is valid

		if(move_uploaded_file($_FILES['apt_uploaded_file']['tmp_name'], $apt_backup_file_export_dir)){ //file can be imported
			$apt_backup_file_import_handle = fopen($apt_backup_file_export_dir, 'r');
			while(($apt_csv_row = fgetcsv($apt_backup_file_import_handle, 550, '|')) !== FALSE){ //lines can be long only 550 characters!

				if(!empty($apt_csv_row[1])){ //user-moron scenario check - don't  save if the tag name is empty
					mysql_query("INSERT IGNORE INTO $apt_table(tag,related_words) VALUES('". $apt_csv_row[1] ."','". $apt_csv_row[2] ."')");

					//user-moron scenarios
					if(preg_match("/[^a-zA-Z0-9\s]/", iconv('UTF-8', 'ASCII//TRANSLIT', $apt_csv_row[1]))){ //display error if the tag has non-alphanumeric characters
						$apt_imported_tag_aplhanumeric_warning = 1;
					}
					if(preg_match("/[^a-zA-Z0-9\s\;\*]/", iconv('UTF-8', 'ASCII//TRANSLIT', $apt_csv_row[2]))){
						$apt_imported_related_words_aplhanumeric_warning = 1;
					}
					if(strstr($apt_csv_row[2], ' ;') OR strstr($apt_csv_row[2], '; ')){
						$apt_imported_related_words_extra_spaces_warning = 1;
					}
					if(strstr($apt_csv_row[2], '*') AND (get_option('apt_miscellaneous_wildcards') == 0)){
						$apt_imported_related_words_asterisk_warning = 1;
					}
				}
				else{
					$apt_imported_tag_empty_error = 1;
				}

			}
			fclose($apt_backup_file_import_handle);

			update_option('apt_stats_current_tags', mysql_num_rows(mysql_query("SELECT id FROM $apt_table"))); //update stats
			echo '<div class="updated"><p>All tags from your backup have been imported.</p></div>';

			if($apt_imported_tag_aplhanumeric_warning == 1){
				echo '<div class="error"><p><b>Warning:</b> Some tag names contain non-alphanumeric characters.</p></div>'; //warning message
			}
			if($apt_imported_related_words_asterisk_warning == 1){
				echo '<div class="error"><p><b>Warning:</b> Your related words contain an asterisk, but using wildcards is currently disabled!</p></div>'; //warning message
			}
			if($apt_imported_related_words_aplhanumeric_warning == 1){
				echo '<div class="error"><p><b>Warning:</b> Some related words contain non-alphanumeric characters.</p></div>'; //warning message
			}
			if($apt_imported_related_words_extra_spaces_warning == 1){
				echo '<div class="error"><p><b>Warning:</b> Some related words contain extra spaces near semicolons.</p></div>'; //warning message
			}
			if($apt_imported_tag_empty_error == 1){
				echo '<div class="error"><p><b>Error:</b> Some tags weren\'t imported because their names were missing.</p></div>'; //warning message
			}
		}
		else{ //cannot upload file
			echo '<div class="error"><p><b>Error:</b> The file could not be uploaded.</p></div>'; //error message
		}
	}
	else{ //the file name is invalid
		echo '<div class="error"><p><b>Error:</b> The name of the imported file must be "'. $apt_backup_file_name .'".</p></div>'; //error message
	}
}
if(isset($_POST['apt_create_a_backup_button'])){ //creating backup
//there is no need to trim tags and related words because function for saving/creating tags won't allow saving "messy" values
	$apt_backup_query = mysql_query("SELECT id,tag,related_words FROM $apt_table");

	while($apt_backup_file_export_row = mysql_fetch_array($apt_backup_query)){
		$apt_backup_file_export_write = $apt_backup_file_export_write . $apt_backup_file_export_row['id'] .'|'. $apt_backup_file_export_row['tag'] .'|'. $apt_backup_file_export_row['related_words'] ."\n"; //the quotes must be here instead apostrophes, or the new line will not be created; $apt_backup_file_export_write = $apt_backup_file_export_write . has to be there repeated or only one line is exported
	}

	@$apt_backup_file_export = fopen($apt_backup_file_export_dir, 'w');
	@fwrite($apt_backup_file_export, $apt_backup_file_export_write);
	@fclose($apt_backup_file_export);

	if(file_exists($apt_backup_file_export_dir)){
		echo '<div class="updated"><p>Your <a href="'. $apt_backup_file_export_url .'">backup</a> has been created.</p></div>';
	}
	else{
		echo '<div class="error"><p><b>Error:</b> Your backup could not be created. Change the permissions of the directory <code>'. dirname(__FILE__) .'</code> to 777 first.</p></div>'; //error message
	}

}

#################### assigning tags with one click ##############
if(isset($_POST['apt_assign_tags_to_all_posts_button'])){
	$apt_table_select_posts = mysql_query("SELECT ID FROM $apt_wp_posts WHERE post_type = 'post' AND (post_status != 'trash' AND post_status != 'draft' AND post_status != 'auto-draft')");
	$apt_table_wp_post_count = mysql_num_rows($apt_table_select_posts);
	$apt_tag_maximum = get_option('apt_miscellaneous_tag_maximum');

	#################################################################
	### stopping execution to prevent the script from doing unuseful job:

	$apt_assign_tags_to_all_posts_error = 0;

	### USER-MORON SCENARIOS
	if (mysql_num_rows(mysql_query('SELECT id FROM '. $apt_table)) == 0){
		$apt_assign_tags_to_all_posts_error = 1;
		echo '<div class="error"><p><b>Error:</b> There aren\'t any tags that can be added to posts.</p></div>';
	}
	if(mysql_num_rows($apt_table_select_posts) == 0){
		$apt_assign_tags_to_all_posts_error = 1;
		echo '<div class="error"><p><b>Error:</b> There aren\'t any posts that can be processed.</p></div>';
	}
	if($apt_tag_maximum == 0){
		$apt_assign_tags_to_all_posts_error = 1;
		echo '<div class="error"><p><b>Error:</b> The maximum number of tags per post is set to <b>zero</b>. No tags can\'t be added!</p></div>';
	}
	if(get_option('apt_post_analysis_title') == 0 AND get_option('apt_post_analysis_content') == 0 AND get_option('apt_post_analysis_excerpt') == 0){
		$apt_assign_tags_to_all_posts_error = 1;
		echo '<div class="error"><p><b>Error:</b> The script isn\'t allowed to analyze any content.</p></div>';
	}
	#################################################################

	if($apt_assign_tags_to_all_posts_error != 1){//run only if no error occured
		while($apt_post_id = mysql_fetch_array($apt_table_select_posts, MYSQL_NUM)){ //run loop to process all posts that are not auto-draft and in trash
			apt_tagging_algorithm($apt_post_id[0], 'nocheck'); //send the current post ID and '1' to let the script know that we do not want to check user-moron scenarios again
		}//-while

		echo '<div class="updated"><p>Automatic Post Tagger has processed '. $apt_table_wp_post_count .' posts.</p></div>';
	}
}
#################################################################
#################################################################
########################## USER INTERFACE #######################
#################################################################
#################################################################
?>

<div id="poststuff" class="metabox-holder has-right-sidebar">
	<div class="inner-sidebar">
		<div id="side-sortables" class="meta-box-sortabless" style="position:relative;">

			<!-- postbox -->
			<div class="postbox">
				<h3>Useful links</h3>
				<div class="inside">
					<ul>
					<li><a class="apt_sidebar_link apt_wp" href="http://wordpress.org/extend/plugins/automatic-post-tagger/">Plugin homepage</a></li>
					<li><a class="apt_sidebar_link apt_wp" href="http://wordpress.org/extend/plugins/automatic-post-tagger/faq">Frequently asked questions</a> </li>
					<li><a class="apt_sidebar_link apt_wp" href="http://wordpress.org/support/plugin/automatic-post-tagger" title="Bug reports and feature requests">Support forum</a></li>
					<li><a class="apt_sidebar_link apt_db" href="http://devtard.com">Developer's blog</a></li>
					</ul>
				</div>
			</div>
			<!-- //-postbox -->

			<!-- postbox -->
			<div class="postbox">
				<h3>Show some love!</h3>
				<div class="inside">
					<p>If you find this plugin useful, please consider donating. Every donation, no matter how small, is appreciated. Your support helps cover the costs associated with development of this free software.</p>

					<ul>
					<li><a class="apt_sidebar_link apt_donate" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=T2QUJ4R6JHKNG">Donate with PayPal</a></li>
					</ul>

					<p>If you can't donate, it's OK - there are other ways to make the developer happy.</p>

					<ul>
					<li><a class="apt_sidebar_link apt_rate" href="http://wordpress.org/extend/plugins/automatic-post-tagger">Rate plugin at WordPress.org</a></li>
					<li><a class="apt_sidebar_link apt_wp_new_post" href="<?php echo admin_url('post-new.php'); ?>">Review this plugin on your blog</a></li>
					<li><a class="apt_sidebar_link apt_twitter" href="http://twitter.com/home?status=Automatic Post Tagger - useful WordPress plugin that automatically adds user-specified tags to posts and pages. http://wordpress.org/extend/plugins/automatic-post-tagger">Post a link to Twitter</a></li>
					<li><a class="apt_sidebar_link apt_facebook" href="http://www.facebook.com/sharer.php?u=http://wordpress.org/extend/plugins/automatic-post-tagger&amp;t=Automatic Post Tagger%20-%20useful%20WordPress%20plugin%20that%20automatically%20adds%20user-specified%20tags%20to%20posts%20and%20pages%20.">Post a link to Facebook</a></li>

					</ul>

					<p>Thank you very much for your support.</p>

				</div>
			</div><!-- //-postbox -->
			
			<!-- postbox -->
			<div class="postbox">
				<h3>Recent contributors<span style="float:right;"><small><a href="http://wordpress.org/extend/plugins/automatic-post-tagger/other_notes/#TOP-5-contributors">Full list</a></small></span></h3>
				<div class="inside">
					<p><iframe border="0" allowtransparency="yes" style="width:100%; height:135px;" src="http://devtard.com/projects/automatic-post-tagger/contributors.php" frameborder="0" scrolling="no">List of recent contributors</iframe></p>
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
					<h3>General settings</h3>
					<div class="inside">
						<p>
							<b>Post analysis</b><br />
							<small>Where should Automatic Post Tagger look for tags and their related words?</small><br />
							<label><input type="checkbox" name="apt_post_analysis_title" <?php if(get_option('apt_post_analysis_title') == 1) echo 'checked="checked"'; ?>> Title</label><br />
							<label><input type="checkbox" name="apt_post_analysis_content" <?php if(get_option('apt_post_analysis_content') == 1) echo 'checked="checked"'; ?>> Content</label><br />
							<label><input type="checkbox" name="apt_post_analysis_excerpt" <?php if(get_option('apt_post_analysis_excerpt') == 1) echo 'checked="checked"'; ?>> Excerpt</label>
						</p>	
						<p>
							<b>Handling current tags</b><br />
							<small>What should the plugin do if posts already have tags?</small><br />
							<label><input type="radio" name="apt_handling_current_tags" value="1" <?php if(get_option('apt_handling_current_tags') == 1) echo 'checked="checked"'; ?>> Append new tags to old tags</label><br />
							<label><input type="radio" name="apt_handling_current_tags" value="2" <?php if(get_option('apt_handling_current_tags') == 2) echo 'checked="checked"'; ?>> Replace old tags with newly generated tags</label><br />
							<label><input type="radio" name="apt_handling_current_tags" value="3" <?php if(get_option('apt_handling_current_tags') == 3) echo 'checked="checked"'; ?>> Do nothing</label>
						</p>
						<p>
							<b>Miscellaneous</b><br />
							<label>Maximum number of tags per one post: <input type="text" name="apt_miscellaneous_tag_maximum" value="<?php echo get_option('apt_miscellaneous_tag_maximum'); ?>" maxlength="10" size="3"></label><br />
							<label>Run tagging algorithm after a post is 
								<select size="1" name="apt_miscellaneous_tagging_occasion">
									<option value="1" <?php if(get_option('apt_miscellaneous_tagging_occasion') == 1){ echo ' selected="selected"'; } ?>>published/updated</option>
									<option value="2" <?php if(get_option('apt_miscellaneous_tagging_occasion') == 2){ echo ' selected="selected"'; } ?> onClick="alert('Warning: The tagging algorithm will run after every manual and automatic saving of a post!')">saved</option>
								</select>.<br />
							<label><input type="checkbox" name="apt_miscellaneous_wildcards" <?php if(get_option('apt_miscellaneous_wildcards') == 1) echo 'checked="checked"'; ?>> Use wildcard (*) to substistute any aplhanumeric characters in related words<br />
							<small>(Example: pattern "cat*" will match words "cats" and "category", pattern "c*t" will match "cat" and "colt".)</small></label>
						</p>
						
						<p style="margin-top:20px;">
						<input class="button-primary" type="submit" name="apt_save_settings_button" value=" Save settings "> 
						<input class="button apt_warning" type="submit" name="apt_restore_default_settings_button" onClick="return confirm('Do you really want to reset all settings to default values (including deleting all tags)?')" value=" Restore default settings ">
						</p>
					</div>
				</div>
				</form>
				<!-- //-postbox -->
		
				<!-- postbox -->
				<form action="<?php echo admin_url('options-general.php?page=automatic-post-tagger'); ?>" method="post">
				<div class="postbox">
					<h3>Create a new tag</h3>
					<div class="inside">

						<p><table style="width:100%;">
						<tr>
							<td style="width:30%;">Tag name <small>(example: <i>cat</i>)</small>:</td>
							<td style="width:68%;">Related words, separated by a semicolon <small>(example: <i>cats;kitty;meo*w</i>) (optional)</small>:</td></tr>
						<tr>
							<td><input style="width:100%;" type="text" name="apt_create_tag" maxlength="255"></td>
							<td><input style="width:100%;" type="text" name="apt_create_related_words" maxlength="255"></td>
						</tr>
						</table></p>


						<p>
							<input class="button-highlighted" type="submit" name="apt_create_a_new_tag_button" value=" Create a new tag ">
						</p>
					</div>
				</div>
				</form>

				<!-- //-postbox -->

				<!-- postbox -->
				<form action="<?php echo admin_url('options-general.php?page=automatic-post-tagger'); ?>" enctype="multipart/form-data" method="post">
				<div class="postbox">
					<h3>Import tags</h3>
					<div class="inside">

						<p>
						<table border="0" width="100%">
						<tr>
							<td>Import all tags that are already in your database:</td>
							<td><input class="button" type="submit" name="apt_import_existing_tags_button" value=" Import existing tags " onClick="return confirm('Do you really want to import all already existing tags? This may take some time if your blog has lots of tags.')">
						</td></tr>
						<tr>
							<td>Import tags from a created backup:</td>
							<td><input type="file" size="1" name="apt_uploaded_file">
							<input class="button" type="submit" name="apt_import_from_a_backup_button" value=" Import from a backup ">
						</td></tr>
						</table>
						</p>
					</div>
				</div>
				</form>

				<!-- //-postbox -->

				<!-- postbox -->
				<form action="<?php echo admin_url('options-general.php?page=automatic-post-tagger'); ?>" method="post">
				<div class="postbox">
					<h3>Manage tags <small>(<?php echo get_option('apt_stats_current_tags'); ?>)</small></h3>
					<div class="inside">


						<?php
						$apt_table_rows_all = mysql_query("SELECT * FROM $apt_table ORDER BY tag");
						if(mysql_num_rows($apt_table_rows_all) == 0){
							echo '<p>There aren\'t any tags.</p>';
						}
						else{
						?>

							<p><div style="max-height:400px;overflow:auto;"><table style="width:100%;">
							<tr><td style="width:30%;">Tag name</td><td style="width:68%;">Related words</td><td style="width:2%;"></td></tr>

						<?php
							while($row = mysql_fetch_array($apt_table_rows_all)){
							?>
								<tr>
								<td><input style="width:100%;" type="text" name="apt_taglist_tag_[<?php echo $row['id']; ?>]" value="<?php echo $row['tag']; ?>" maxlength="255"></td>
								<td><input style="width:100%;" type="text" name="apt_taglist_related_words_[<?php echo $row['id']; ?>]" value="<?php echo $row['related_words']; ?>" maxlength="255"></td>
								<td><input style="width:10px;" type="checkbox" name="apt_taglist_checkbox_[<?php echo $row['id']; ?>]"></td>
								</tr>
							<?php
							}
						?>
							</table></div></p>

						<p style="margin-top:20px;">
						<input class="button-highlighted" type="submit" name="apt_save_tags_button" value=" Save changes ">
						<input class="button" type="submit" name="apt_create_a_backup_button" value=" Create a backup ">


						<input class="button apt_warning" style="float:right;" type="submit" name="apt_delete_chosen_tags_button" onClick="return confirm('Do you really want to delete chosed tags?')" value=" Delete chosen tags ">
						<input class="button apt_warning" style="float:right;" type="submit" name="apt_delete_all_tags_button" onClick="return confirm('Do you really want to delete all tags?')" value=" Delete all tags ">

						</p>

						<?php
						}
						?>


					</div>
				</div>
				</form>
				<!-- //-postbox -->

				<!-- postbox -->
				<form action="<?php echo admin_url('options-general.php?page=automatic-post-tagger'); ?>" method="post">
				<div class="postbox">
					<h3>Assign tags to all posts</h3>
					<div class="inside">
						<p>This tool adds tags to all posts which post status isn't "trash", "draft" or "auto-draft". It follows rules specified above.
						<br />Make sure that you understand how it will behave before you hit the button, any changes can't be reversed.</p>

						<p style="margin-top:20px;">
						<input class="button-highlighted" type="submit" name="apt_assign_tags_to_all_posts_button" onClick="return confirm('Do you really want to assign tags to all posts? This may take some time if your blog has lots of posts.')" value=" Assign tags "> 
						</p>
					</div>
				</div>
				</form>
				<!-- //-postbox -->


			<!-- stop right here! -->
			</div>
		</div>
	</div>

</div>
</div>



<?php
} //-function options page
?>
