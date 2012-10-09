<?php
/*
Plugin Name: Automatic Post Tagger
Plugin URI: http://wordpress.org/extend/plugins/automatic-post-tagger
Description: This plugin automatically adds user-defined tags to posts.
Version: 1.3
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
		//$links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=T2QUJ4R6JHKNG">Donate</a>';
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
			echo '<div id="message" class="updated"><p><b>Note:</b> Managing tags (creating, importing, editing, deleting) on this page doesn\'t affect tags that are already added to your posts.</p></div>'; //display quick info for beginners
		}
		if(isset($_GET['n']) AND $_GET['n'] == 2){
			update_option('apt_admin_notice_update', 0); //hide update notice
			echo '<div id="message" class="updated"><p><b>New feature:</b> You can choose to analyse only a specific part of the content (Miscellaneous).</p></div>'; //show new functions
			echo '<div id="message" class="updated"><p>Please <a href="http://wordpress.org/extend/plugins/automatic-post-tagger">rate this plugin</a>. If you give it <b>5 stars</b> the developer will be motivated to work faster on implementing new features!</p></div>'; //gimme some stars!
		}
		if(isset($_GET['n']) AND $_GET['n'] == 3){
			update_option('apt_admin_notice_donate', 0); //hide donation notice
		}
		if(isset($_GET['n']) AND $_GET['n'] == 4){
			update_option('apt_admin_notice_donate', 0); //hide donation notice and display another notice (below)
			echo '<div id="message" class="updated"><p><b>Thank you for donating.</b> If you filled in the URL of your website, it should appear on the list of recent contributors ASAP.</p></div>'; //show "thank you" message
		}



		if(get_option('apt_admin_notice_install') == 1){ //show link to the setting page after installing
			echo '<div id="message" class="updated"><p><b>Automatic Post Tagger</b> has been installed. <a href="'. admin_url('options-general.php?page=automatic-post-tagger&n=1') .'">Set up the plugin &raquo;</a></p></div>';
		}
		if(get_option('apt_admin_notice_update') == 1){ //show link to the setting page after updating
			echo '<div id="message" class="updated"><p><b>Automatic Post Tagger</b> has been updated to version <b>'. get_option('apt_plugin_version') .'</b>. <a href="'. admin_url('options-general.php?page=automatic-post-tagger&n=2') .'">Find out what\'s new &raquo;</a></p></div>';
		}
/*
		if(get_option('apt_admin_notice_donate') == 1){ //determine if the donation notice was not dismissed
			if(((time() - get_option('apt_stats_install_date')) >= 2629743) AND (get_option('apt_stats_assigned_tags') >= 50)){ //show donation notice after a month (2629743 seconds) and if the plugin added more than 50 tags
//TODO: there should be a check for time so it won't print "over a month" after a year!

				echo '<div id="message" class="updated"><p>
					<b>Thanks for using <acronym title="Automatic Post Tagger">APT</acronym>!</b> You installed this plugin over a month ago. Since that time it has assigned <b>'. get_option('apt_stats_assigned_tags') .' tags</b> to your posts.
					If you are satisfied with the results, isn\'t it worth at least a few dollars? Donations motivate the developer to continue working on this plugin. <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=T2QUJ4R6JHKNG" title="Donate with Paypal"><b>Sure, no problem!</b></a>

					<span style="float:right">
					<a href="'. admin_url('options-general.php?page=automatic-post-tagger&n=3') .'" title="Hide this notification"><small>No thanks, don\'t bug me anymore!</small></a> |
					<a href="'. admin_url('options-general.php?page=automatic-post-tagger&n=4') .'" title="Hide this notification"><small>OK, but I donated already!</small></a>
					</span>
				</p></div>';
			}
		}//-if donations
*/
	}//-if admin check
}
#################################################################
######################## CREATE TAG FUNCTION ####################
function apt_create_a_new_tag($apt_tag_name,$apt_tag_related_words){
	global $wpdb, $apt_table;
	$apt_table_tag_existence_check = mysql_query("SELECT id FROM $apt_table WHERE tag = '". $apt_tag_name ."' LIMIT 0,1");

	if(empty($apt_tag_name)){ //checking if the value of the tag isn't empty
		echo '<div id="message" class="error"><p><b>Error:</b> You can\'t create a tag that does not have a name.</p></div>';
	}
		else{
			if(mysql_num_rows($apt_table_tag_existence_check)){ //checking if the tag exists
				echo '<div id="message" class="error"><p><b>Error:</b> Tag <b>"'. htmlspecialchars($apt_tag_name) .'"</b> already exists!</p></div>';
			} 
			else{ //if the tag is not in DB, create one

				$apt_created_tag_trimmed = trim($apt_tag_name); //replacing ONLY whitespace characters from beginning and end (we could remove multiple characters like ';', but they are not used here to separate anything, so we let the user to do what he/she wants)
				$apt_created_related_words_trimmed = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $apt_tag_related_words); //replacing multiple whitespace characters with a space (we could replace them completely, but that might annoy users)
				$apt_created_related_words_trimmed = preg_replace('{;+}', ';', $apt_created_related_words_trimmed); //replacing multiple semicolons with one
				$apt_created_related_words_trimmed = preg_replace('/[\*]+/', '*', $apt_created_related_words_trimmed); //replacing multiple asterisks with one
				$apt_created_related_words_trimmed = trim(trim(trim($apt_created_related_words_trimmed), ';')); //trimming semicolons and whitespace characters from the beginning and the end

				mysql_query("INSERT IGNORE INTO $apt_table (tag, related_words) VALUES ('". $apt_created_tag_trimmed ."', '". $apt_created_related_words_trimmed ."')");
				update_option('apt_stats_current_tags', mysql_num_rows(mysql_query("SELECT id FROM $apt_table"))); //update stats


				echo '<div id="message" class="updated"><p>Tag <b>"'. htmlspecialchars($apt_created_tag_trimmed) .'"</b> with '; //confirm message with a condition displaying related words if available
					if(empty($apt_created_related_words_trimmed)){
						echo 'no related words';
					}else{
						if(strstr($apt_created_related_words_trimmed, ';')){ //print single or plural form
							echo 'related words <b>"'. htmlspecialchars($apt_created_related_words_trimmed) .'"</b>';
						}
						else{
							echo 'related word <b>"'. htmlspecialchars($apt_created_related_words_trimmed) .'"</b>';
						}

					}
				echo ' has been created.</p></div>';

				//warning messages appearing when "unexpected" character are being saved
				if(preg_match("/[^a-zA-Z0-9\s]/", iconv('UTF-8', 'ASCII//TRANSLIT', $apt_created_tag_trimmed))){ //user-moron scenario
					echo '<div id="message" class="error"><p><b>Warning:</b> Tag name <b>"'. htmlspecialchars($apt_created_tag_trimmed) .'"</b> contains non-alphanumeric characters.</p></div>'; //warning message
				}
				if(preg_match("/[^a-zA-Z0-9\s\;\*]/", iconv('UTF-8', 'ASCII//TRANSLIT', $apt_created_related_words_trimmed))){ //user-moron scenario
					echo '<div id="message" class="error"><p><b>Warning:</b> Related words "'. htmlspecialchars($apt_created_related_words_trimmed) .'" contain non-alphanumeric characters.</p></div>'; //warning message
				}
				if(strstr($apt_created_related_words_trimmed, ' ;') OR strstr($apt_created_related_words_trimmed, '; ')){ //user-moron scenario
					echo '<div id="message" class="error"><p><b>Warning:</b> Related words "'. htmlspecialchars($apt_created_related_words_trimmed) .'" contain extra space near the semicolon.</p></div>'; //warning message
				}
				if(strstr($apt_created_related_words_trimmed, '*') AND (get_option('apt_miscellaneous_wildcards') == 0)){ //user-moron scenario
					echo '<div id="message" class="error"><p><b>Warning:</b> Your related words contain an asterisk, but using wildcards is currently disabled!</p></div>'; //warning message
				}


			}//--else
		}//--else
}

#################################################################
######################## META BOX ###############################

function apt_custom_box_add(){ //add custom box
	add_meta_box('apt_section_id','Automatic Post Tagger','apt_custom_box_content','post','side');
}
function apt_custom_box_content(){ //custom box content
?>
	<p>Tag name: <input onkeypress="return apt_enter_submit(event);" style="min-width:50px;width:100%;" type="text" id="apt_box_tag_name" name="apt_box_tag_name" value="" maxlength="255" /><br />
	Related words (separated by semicolons): <input onkeypress="return apt_enter_submit(event);" style="min-width:50px;width:100%;" type="text" id="apt_box_tag_related_words" name="apt_box_tag_related_words" value="" maxlength="255" /></p>

	<p>
		<input class="button-highlighted" type="button" id="apt_create_a_new_tag_ajax_button" value=" Create a new tag ">
		<span id="apt_box_message" style="color:green;"></span>
	</p>
<?php
}




function apt_custom_box_save_tag(){ //save tag sent via custom box
	apt_create_a_new_tag($_POST['apt_box_tag_name'],$_POST['apt_box_tag_related_words']);
}

#################### javascripts ####################
function apt_custom_box_ajax() { //javascript calling function above
?>
<script type='text/javascript' src='http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js'></script> 
<script type="text/javascript">
jQuery(document).ready(function($) {
	$('#apt_create_a_new_tag_ajax_button').click(function () {


	var apt_box_tag_name = $('#apt_box_tag_name').val();
	var apt_box_tag_related_words = $('#apt_box_tag_related_words').val();

	var data = {
		action: 'apt_custom_box_save_tag',
		apt_box_tag_name: apt_box_tag_name,
		apt_box_tag_related_words: apt_box_tag_related_words,
		};

	$.ajax ({
		type: 'POST',
		url: ajaxurl,
		data: data,
		success: function() {
				jQuery('#apt_box_tag_name, #apt_box_tag_related_words').val('');

				jQuery("#apt_box_message").fadeIn("fast");
				document.getElementById("apt_box_message").innerHTML="OK";
				jQuery("#apt_box_message").delay(1000).fadeOut("slow");
			}
		});
	});
});
function apt_enter_submit(e) {
    if (e.which == 13) {
        var $targ = $(e.target);

        if (!$targ.is("textarea") && !$targ.is(":button,:submit")) {
            var focusNext = false;
            $(this).find(":input:visible:not([disabled],[readonly]), a").each(function(){
                if (this === e.target) {
                    focusNext = true;
                }
                else if (focusNext){
                    $(this).focus();
                    return false;
                }
            });

            return false;
        }
    }
}
</script>
<?php
}

function apt_settings_page_javascript() { //javascript calling function above
?>
<script type="text/javascript">
function apt_change_background(num){
	if (document.getElementById("apt_taglist_checkbox_"+num).checked){
		document.getElementById("apt_taglist_tag_"+num).style.backgroundColor='#FFD2D2';
		document.getElementById("apt_taglist_related_words_"+num).style.backgroundColor='#FFD2D2';
	}
	else{
		document.getElementById("apt_taglist_tag_"+num).style.backgroundColor='';
		document.getElementById("apt_taglist_related_words_"+num).style.backgroundColor='';
	}
}
</script>
<?php
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

	add_option('apt_string_manipulation_convert_diacritic', '1', '', 'no');
	add_option('apt_string_manipulation_lowercase', '1', '', 'no');
	add_option('apt_string_manipulation_strip_tags', '1', '', 'no');
	add_option('apt_string_manipulation_replace_whitespaces', '1', '', 'no');
	add_option('apt_string_manipulation_replace_nonalphanumeric', '0', '', 'no');
	add_option('apt_string_manipulation_ignore_asterisks', '1', '', 'no');

	add_option('apt_word_recognition_separators', '.,?!:;\'"`/()[]{}_+=-<>~@#$%^&*', '', 'no');

	add_option('apt_miscellaneous_tag_maximum', '20', '', 'no');
	add_option('apt_miscellaneous_substring_analysis', '0', '', 'no');
	add_option('apt_miscellaneous_substring_analysis_length', '1000', '', 'no');
	add_option('apt_miscellaneous_substring_analysis_start', '0', '', 'no');
	add_option('apt_miscellaneous_wildcards', '0', '', 'no');
}
#################### update function ############################
function apt_update_plugin(){ //runs when all plugins are loaded (needs to be deleted after register_update_hook is available)
	if(current_user_can('manage_options')){
		if(get_option('apt_plugin_version') <> apt_get_plugin_version()){ //check if the saved version is not equal to the current version

			$apt_current_version = apt_get_plugin_version();

			#### now comes everything what must be changed in the new version
			if(get_option('apt_plugin_version') == '1.1' AND $apt_current_version == '1.2'){ //upgrade from 1.1
				delete_option('apt_miscellaneous_tagging_occasion');

				add_option('apt_string_manipulation_convert_diacritic', '1', '', 'no');
				add_option('apt_string_manipulation_lowercase', '1', '', 'no');
				add_option('apt_string_manipulation_strip_tags', '1', '', 'no');
				add_option('apt_string_manipulation_replace_whitespaces', '1', '', 'no');
				add_option('apt_string_manipulation_replace_nonalphanumeric', '0', '', 'no');
				add_option('apt_string_manipulation_ignore_asterisks', '1', '', 'no');

				add_option('apt_word_recognition_separators', '.,?!:;\'"`/()[]{}_+=-<>~@#$%^&*', '', 'no');

				add_option('apt_miscellaneous_substring_analysis', '0', '', 'no');
				add_option('apt_miscellaneous_substring_analysis_length', '1000', '', 'no');
				add_option('apt_miscellaneous_substring_analysis_start', '0', '', 'no');
			}
			if(get_option('apt_plugin_version') == '1.2' AND $apt_current_version == '1.3'){ //upgrade from 1.1
				add_option('apt_miscellaneous_substring_analysis', '0', '', 'no');
				add_option('apt_miscellaneous_substring_analysis_length', '1000', '', 'no');
				add_option('apt_miscellaneous_substring_analysis_start', '0', '', 'no');
			}


			## we must not forget to include new changes to conditions for all previous versions

			#### -/changes

			update_option('apt_admin_notice_update', 1); //we want to show the admin notice after upgrading, right?
			update_option('apt_plugin_version', $apt_current_version); //update plugin version in DB
		}//-if different versions
	}//if current user can
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
}

################################################################
########################## TAGGING ENGINE #######################
#################################################################
function apt_tagging_algorithm($post_id){ //this function is for adding tags to only one post - mass adding should be handled by using a loop
	global $wpdb, $apt_table, $apt_wp_posts;

	$apt_post_current_tags = wp_get_post_terms($post_id, 'post_tag', array("fields" => "names"));
	$apt_post_current_tag_count = count($apt_post_current_tags);
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
		//the user does not want us to process 0 characters, stop!
		if(get_option('apt_miscellaneous_substring_analysis') == 1 AND get_option('apt_miscellaneous_substring_analysis_length') == 0){
			return 7;
		}


//	}//-moron check



	#################################################################

	//if this isn't a revision - not sure if needed, but why not use it, huh?
	if(!wp_is_post_revision($post_id)){
		$apt_post_title = $wpdb->get_var("SELECT post_title FROM $apt_wp_posts WHERE ID = $post_id LIMIT 0, 1");
		$apt_post_content = $wpdb->get_var("SELECT post_content FROM $apt_wp_posts WHERE ID = $post_id LIMIT 0, 1");
		$apt_post_excerpt = $wpdb->get_var("SELECT post_excerpt FROM $apt_wp_posts WHERE ID = $post_id LIMIT 0, 1");

		$apt_word_separators = get_option('apt_word_recognition_separators');
		$apt_word_separators_plus_space = ' '. $apt_word_separators; //add also a space to the separators
		$apt_word_separators_array = str_split($apt_word_separators_plus_space);

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
		if(get_option('apt_string_manipulation_convert_diacritic') == 1){
			setlocale(LC_ALL, 'en_GB'); //set locale
			$apt_post_analysis_haystack_string = iconv('UTF-8', 'ASCII//TRANSLIT', $apt_post_analysis_haystack_string); //replace diacritic character with ascii equivalents
		}
		if(get_option('apt_string_manipulation_lowercase') == 1){
			$apt_post_analysis_haystack_string = strtolower($apt_post_analysis_haystack_string); //make it lowercase
		}
		if(get_option('apt_string_manipulation_strip_tags') == 1){
			$apt_post_analysis_haystack_string = wp_strip_all_tags($apt_post_analysis_haystack_string); //remove HTML, PHP and JS tags
		}
		if(get_option('apt_string_manipulation_replace_nonalphanumeric') == 1){
			$apt_post_analysis_haystack_string = preg_replace("/[^a-zA-Z0-9\s]/", ' ', $apt_post_analysis_haystack_string); //replace all non-alphanumeric-characters with space
		}
		if(get_option('apt_string_manipulation_replace_whitespaces') == 1){
			$apt_post_analysis_haystack_string = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $apt_post_analysis_haystack_string); //replace whitespaces and newline characters with a space
		}

		if(get_option('apt_miscellaneous_substring_analysis') == 1){ //analyse onlya part of the string
			$apt_post_analysis_haystack_string = substr($apt_post_analysis_haystack_string, get_option('apt_miscellaneous_substring_analysis_start'), get_option('apt_miscellaneous_substring_analysis_length'));
		}

		$apt_post_analysis_haystack_string = ' '. $apt_post_analysis_haystack_string .' '; //we need to add a space before and after the string: the engine is looking for ' string ' (with space at the beginning and the end, so it won't find e.g. ' ice ' in a word ' iceman ')

		$apt_tags_to_add_array = array(); //array of tags that will be added to a post
		$apt_table_rows_tag_related_words = mysql_query("SELECT tag,related_words FROM $apt_table");
		$apt_table_related_words = mysql_query("SELECT related_words FROM $apt_table");

		//determine if we should calculate the number of max. tags for a post - only when appending tags
		if(get_option('apt_handling_current_tags') == 1){
			$apt_tags_to_add_max = $apt_tag_maximum - $apt_post_current_tag_count;
		}
		else{
			$apt_tags_to_add_max = $apt_tag_maximum;
		}

//die(htmlspecialchars($apt_post_analysis_haystack_string)); //for debugging

		## SEARCH FOR A SINGLE TAG AND ITS RELATED WORDS
		while($apt_table_cell = mysql_fetch_array($apt_table_rows_tag_related_words, MYSQL_NUM)){ //loop handling every row in the table

			## CHECK FOR RELATED WORDS
			$apt_table_row_related_words_count = substr_count($apt_table_cell[1], ';') + 1; //variable prints number of related words in the current row that is being "browsed" by the while; must be +1 higher than the number of semicolons!

			//resetting variables - this must be here or the plugin will add non-relevant tags 
			$apt_table_tag_found = 0;
			$apt_table_related_word_found = 0;

			if(!empty($apt_table_cell[1])){ //if there are not any related words, do not perform this action so the tag won't be added (adds tag always when no related words are assigned to it)

				$apt_table_cell_substrings = explode(';', $apt_table_cell[1], $apt_table_row_related_words_count);
				for($i=0; $i < $apt_table_row_related_words_count; $i++){ //loop handling substrings in the 'related_words' column - $i must be 0 because array always begin with 0!

					//preparing the substring needle for search --- note: removing tags here does not make any sense!
					$apt_substring_needle = $apt_table_cell_substrings[$i];
					if(get_option('apt_string_manipulation_convert_diacritic') == 1){
						setlocale(LC_ALL, 'en_GB'); //set locale
						$apt_substring_needle = iconv('UTF-8', 'ASCII//TRANSLIT', $apt_substring_needle); //replace diacritic character with ascii equivalents
					}
					if(get_option('apt_string_manipulation_lowercase') == 1){
						$apt_substring_needle = strtolower($apt_substring_needle); //make it lowercase
					}
					if(get_option('apt_string_manipulation_replace_nonalphanumeric') == 1){
						if(get_option('apt_string_manipulation_ignore_asterisks') == 1){ //ignore asterisks so wildcards will work
							$apt_substring_needle = preg_replace("/[^a-zA-Z0-9\s\*]/", ' ', $apt_substring_needle); //replace all non-alphanumeric-characters with space
						}
						else{ //wildcards won't work
							$apt_substring_needle = preg_replace("/[^a-zA-Z0-9\s]/", ' ', $apt_substring_needle); //replace all non-alphanumeric-characters with space
						}
					}

					## WORD SEPARATORS FOR SUBSTRINGS
					if(!empty($apt_word_separators)){ //continue only if separators are set
						foreach($apt_word_separators_array as $separator){
							foreach($apt_word_separators_array as $separator_end){

								$apt_substring_needle_separated = $separator . $apt_substring_needle . $separator_end; //add each separator to the string

								//wildcard search for related words
								if(get_option('apt_miscellaneous_wildcards') == 1){ //run if wildcards are allowed
									$apt_substring_needle_wildcards = '/'. str_replace('*', '([a-zA-Z0-9]*)', $apt_substring_needle) .'/';
									if(preg_match($apt_substring_needle_wildcards, $apt_post_analysis_haystack_string)){
										$apt_table_related_word_found = 1; //set variable to 1
										break 2; //stop the loops if the tag was found, no need to continue
									}
								}
								else{ //if wildcards are not allowed, continue searching without using a regular expression
									if(strstr($apt_post_analysis_haystack_string, $apt_substring_needle)){ //strtolowered and asciied 'XsubstringX' has been found
										$apt_table_related_word_found = 1; //set variable to 1
										break 2; //stop the loops if the tag was found, no need to continue
									}
								}//-else wildcard check

							}//-foreach for the second deparator - end
						}//-foreach for the first deparator - end
					}//-if separators are set
					## SPACE SEPARATORS FOR SUBSTRINGS
					else{ //if no separators are set, continue searching with spaces before and after every tag
						$apt_substring_needle_spaces = ' '. $apt_substring_needle .' '; //add separators - spaces

						//wildcard search for related words
						if(get_option('apt_miscellaneous_wildcards') == 1){ //run if wildcards are allowed
							$apt_substring_needle_wildcards = '/'. str_replace('*', '([a-zA-Z0-9]*)', $apt_substring_needle_spaces) .'/';

							if(preg_match($apt_substring_needle_wildcards, $apt_post_analysis_haystack_string)){
								$apt_table_related_word_found = 1; //set variable to 1
							}
						}
						else{ //if wildcards are not allowed, continue searching without using a regular expression
							if(strstr($apt_post_analysis_haystack_string, $apt_substring_needle_spaces)){ //strtolowered and asciied ' substring ' has been found
								$apt_table_related_word_found = 1; //set variable to 1
							}
						}//-if wildcard check
					}//-else - no separators
				}//-for
			}//-if for related words check

//die("found: ".$apt_table_related_word_found ."<br>text: ". htmlspecialchars($apt_post_analysis_haystack_string) . "<br>needle: ". htmlspecialchars($apt_substring_needle) .""); //for debugging


			## CHECK FOR TAGS
			if($apt_table_related_word_found == 0){ //search for tags only when no substrings were found
//die("no substring was found, now we search for tags"); //for debugging
				//preparing the needle for search --- note: removing tags and whitespace characters here does not make any sense!
				$apt_tag_needle = $apt_table_cell[0];
				if(get_option('apt_string_manipulation_convert_diacritic') == 1){
					setlocale(LC_ALL, 'en_GB'); //set locale
					$apt_tag_needle = iconv('UTF-8', 'ASCII//TRANSLIT', $apt_tag_needle); //replace diacritic character with ascii equivalents
				}
				if(get_option('apt_string_manipulation_lowercase') == 1){
					$apt_tag_needle = strtolower($apt_tag_needle); //make it lowercase
				}
				if(get_option('apt_string_manipulation_replace_nonalphanumeric') == 1){
					$apt_tag_needle = preg_replace("/[^a-zA-Z0-9\s]/", ' ', $apt_tag_needle); //replace all non-alphanumeric-characters with space //TODO: this should be removed whrn word separators are available
				}

				## WORD SEPARATORS FOR TAGS
				if(!empty($apt_word_separators)){ //continue only if separators are set
					foreach($apt_word_separators_array as $separator){
						foreach($apt_word_separators_array as $separator_end){

							$apt_tag_needle_separated = $separator . $apt_tag_needle . $separator_end; //add each separator to the string

							//searching for tags (note for future me: we do not want to check for wildcards, they cannot be used in tags (don't implement it AGAIN, you moron)!
							if(strstr($apt_post_analysis_haystack_string, $apt_tag_needle_separated)){ //strtolowered and asciied 'XtagX' has been found
//die("tag '". $apt_tag_needle ."' found with separators '". $separator ."' and '". $separator_end ."'"); //for debugging
								$apt_table_tag_found = 1; //set variable to 1
								break 2; //stop the loops if the tag was found, no need to continue
							}
						}//-foreach for the second deparator - end
					}//-foreach for the first deparator - end
				}//-if separators are set
				## SPACE SEPARATORS FOR TAGS
				else{ //if no separators are set, continue searching with spaces before and after every tag
					$apt_tag_needle_spaces = ' '. $apt_tag_needle .' ';

					//searching for tags (note for future me: we do not want to check for wildcards, they cannot be used in tags (don't implement it AGAIN, you moron)!
					if(strstr($apt_post_analysis_haystack_string, $apt_tag_needle_spaces)){ //strtolowered and asciied ' tag ' has been found
						$apt_table_tag_found = 1; //set variable to 1
//die("tag found without separators"); //for debugging
					}
				}//-else - no separators
			}//-check for tags if no substrings were found


//die("tag: ". htmlspecialchars($apt_table_cell[0]) ."<br>needle: ". htmlspecialchars($apt_tag_needle)); //for debugging

			## ADDING TAGS TO ARRAY
			if($apt_table_related_word_found == 1 OR $apt_table_tag_found == 1){ //tag or one of related_words has been found, add tag to array!
//die("tag: ". htmlspecialchars($apt_table_cell[0]) ."<br>rw found: ".$apt_table_related_word_found ."<br> tag found: ".  $apt_table_tag_found); //for debugging

				//we need to check if the tag isn't already in the array of the current tags (don't worry about the temporary array for adding tags, only unique values are pushed in)	
				if(get_option('apt_handling_current_tags') == 2 OR $apt_post_current_tag_count == 0){ //if we need to replace tags, don't check for the current tags or they won't be added again after deleting the old ones --- $apt_post_current_tag_count == 0 will work also for the "do nothing" option
						array_push($apt_tags_to_add_array, $apt_table_cell[0]); //add tag to the array

//die("tag:". htmlspecialchars($apt_table_cell[0]) ."<br>current tags: ". htmlspecialchars(print_r($apt_tags_to_add_array, true))); //for debugging
				}
				else{//appending tags? check for current tags to avoid adding duplicate records to the array
					if(in_array($apt_table_cell[0], $apt_post_current_tags) == FALSE){
						array_push($apt_tags_to_add_array, $apt_table_cell[0]); //add tag to the array
					}
				}


			}//--if for pushing tag to array
//die("tag needle:". htmlspecialchars($apt_tag_needle) ."<br>rw needle: ". htmlspecialchars($apt_substring_needle) ."<br>rw found: ". $apt_table_related_word_found."<br>tag found: " .$apt_table_tag_found); //for debugging


			if(count($apt_tags_to_add_array) == $apt_tags_to_add_max){//check if the array is equal to the max. number of tags per one post, break the loop
				break; //stop the loop, the max. number of tags was hit
			}
		}//-while

//die("max: ".$apt_tag_maximum ."<br>current tags: ". $apt_post_current_tag_count . "<br>max for this post: " .$apt_tags_to_add_max. "<br>current tags: ". htmlspecialchars(print_r($apt_tags_to_add_array, true))); //for debugging

		## ADDING TAGS TO THE POST
		//if the post has already tags, we should decide what to do with them
		if(get_option('apt_handling_current_tags') == 1 OR get_option('apt_handling_current_tags') == 3){
			wp_set_post_terms($post_id, $apt_tags_to_add_array, 'post_tag', true); //append tags
			update_option('apt_stats_assigned_tags', get_option('apt_stats_assigned_tags') + count($apt_tags_to_add_array)); //update stats
		}
		if(get_option('apt_handling_current_tags') == 2 AND count($apt_tags_to_add_array) != 0){ //if the plugin generated some tags, replace the old ones,otherwise do not continue!
			wp_set_post_terms($post_id, $apt_tags_to_add_array, 'post_tag', false); //replace tags
			update_option('apt_stats_assigned_tags', get_option('apt_stats_assigned_tags') + count($apt_tags_to_add_array)); //update stats
		}

//die("current tags: ". htmlspecialchars(print_r($apt_post_current_tags, true)) . "<br>array to add: ". htmlspecialchars(print_r($apt_tags_to_add_array, true))); //for debugging

	}//- revision check
}//-end of tagging function

#################################################################
########################## HOOKS ################################
#################################################################

if(is_admin()){ //these functions will be executed only if the admin panel is being displayed for performance reasons
	add_action('admin_menu', 'apt_menu_link');
	add_action('admin_notices', 'apt_plugin_admin_notices', 20); //check for admin notices

	//for performance issues
	if($GLOBALS['pagenow'] == 'plugins.php'){ //check if the admin is on page plugins.php
		add_filter('plugin_action_links', 'apt_plugin_action_links', 12, 2);
		add_filter('plugin_row_meta', 'apt_plugin_meta_links', 12, 2);
	}
	if(in_array($GLOBALS['pagenow'], array('plugins.php', 'update-core.php', 'update.php'))){ //check if the admin is on pages update-core.php, plugins.php or update.php
		add_action('plugins_loaded', 'apt_update_plugin');
		register_activation_hook(__FILE__, 'apt_install_plugin');
		register_uninstall_hook(__FILE__, 'apt_uninstall_plugin');
	}

	if($GLOBALS['pagenow'] == 'options-general.php'){ //check if the admin is on page options-general.php
		add_action('admin_print_scripts', 'apt_settings_page_javascript'); //script for changing backgrounds of inputs
	}
	if(in_array($GLOBALS['pagenow'], array('post.php', 'post-new.php'))){ //check if the admin is on pages post.php, post-new.php
		add_action('admin_print_scripts', 'apt_custom_box_ajax'); //AJAX for saving a new tag
		add_action('add_meta_boxes', 'apt_custom_box_add'); //add box to the post editor
	}
	add_action('wp_ajax_apt_custom_box_save_tag', 'apt_custom_box_save_tag'); //callback for function saving the tag from meta_box - this must not be in the condition before or it will not work

}//-is_admin


add_action('publish_post','apt_tagging_algorithm'); //executes after every page reload!!
//add_action('save_post','apt_tagging_algorithm'); //for debugging

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

	update_option('apt_string_manipulation_convert_diacritic', (isset($_POST['apt_string_manipulation_convert_diacritic'])) ? '1' : '0');
	update_option('apt_string_manipulation_lowercase', (isset($_POST['apt_string_manipulation_lowercase'])) ? '1' : '0');
	update_option('apt_string_manipulation_strip_tags', (isset($_POST['apt_string_manipulation_strip_tags'])) ? '1' : '0');
	update_option('apt_string_manipulation_replace_whitespaces', (isset($_POST['apt_string_manipulation_replace_whitespaces'])) ? '1' : '0');
	update_option('apt_string_manipulation_replace_nonalphanumeric', (isset($_POST['apt_string_manipulation_replace_nonalphanumeric'])) ? '1' : '0');
	update_option('apt_string_manipulation_ignore_asterisks', (isset($_POST['apt_string_manipulation_ignore_asterisks'])) ? '1' : '0');

	update_option('apt_word_recognition_separators', stripslashes(html_entity_decode($_POST['apt_word_recognition_separators'], ENT_QUOTES)));

	update_option('apt_miscellaneous_substring_analysis', (isset($_POST['apt_miscellaneous_substring_analysis'])) ? '1' : '0');
	update_option('apt_miscellaneous_wildcards', (isset($_POST['apt_miscellaneous_wildcards'])) ? '1' : '0');

	//making sure that people won't save rubbish in the DB
	if(is_numeric($_POST['apt_miscellaneous_substring_analysis_length'])){
		update_option('apt_miscellaneous_substring_analysis_length', $_POST['apt_miscellaneous_substring_analysis_length']);
	}
	else{
		echo '<div id="message" class="error"><p><b>Error:</b> The option "apt_miscellaneous_substring_analysis_length" couldn\'t be saved because the sent value wasn\'t numeric.</p></div>'; //user-moron scenario
	}
	if(is_numeric($_POST['apt_miscellaneous_substring_analysis_start'])){
		update_option('apt_miscellaneous_substring_analysis_start', $_POST['apt_miscellaneous_substring_analysis_start']);
	}
	else{
		echo '<div id="message" class="error"><p><b>Error:</b> The option "apt_miscellaneous_substring_analysis_start" couldn\'t be saved because the sent value wasn\'t numeric.</p></div>'; //user-moron scenario
	}
	if(is_numeric($_POST['apt_miscellaneous_tag_maximum'])){
		update_option('apt_miscellaneous_tag_maximum', $_POST['apt_miscellaneous_tag_maximum']);
	}
	else{
		echo '<div id="message" class="error"><p><b>Error:</b> The option "apt_miscellaneous_tag_maximum" couldn\'t be saved because the sent value wasn\'t numeric.</p></div>'; //user-moron scenario
	}


	//print message informing the user about better performance if they delete separators
	if(isset($_POST['apt_string_manipulation_replace_nonalphanumeric']) AND get_option('apt_word_recognition_separators') != ''){ //display this note only if there are not any separators
		echo '<div id="message" class="updated"><p><b>Note:</b> Replacing non-alphanumeric characters with spaces has been activated. <b>Deleting all user-defined word separators</b> is recommended for better performance.</p></div>'; //user-moron scenario
	}
	//print message informing the user about non functioning wildcards
	if(isset($_POST['apt_string_manipulation_replace_nonalphanumeric']) AND get_option('apt_string_manipulation_ignore_asterisks') == 0){  //display this note only if asterisk are not being ignored
		echo '<div id="message" class="updated"><p><b>Note:</b> Non-alphanumeric characters (including asterisks) will be replaced with spaces. <b>Wildcards won\'t work</b> unless you allow the option "Don\'t replace asterisks".</p></div>'; //user-moron scenario
	}


	echo '<div id="message" class="updated"><p>Your settings have been saved.</p></div>'; //confirm message
}

if(isset($_POST['apt_restore_default_settings_button'])){ //resetting settings
	apt_uninstall_plugin();
	apt_install_plugin();
	echo '<div id="message" class="updated"><p>Default settings have been restored.</p></div>'; //confirm message
}


#################### tag management ##############################
if(isset($_POST['apt_create_a_new_tag_button'])){ //creating a new tag wuth relaterd words
	apt_create_a_new_tag($_POST['apt_create_tag_name'],$_POST['apt_create_tag_related_words']);
}


if(isset($_POST['apt_delete_all_tags_button'])){ //delete all records from $apt_table
	mysql_query('TRUNCATE TABLE '. $apt_table);
	update_option('apt_stats_current_tags', '0'); //reset stats

	echo '<div id="message" class="updated"><p>All tags have been deleted.</p></div>';
}

if(isset($_POST['apt_delete_chosen_tags_button'])){ //delete chosen records from $apt_table
	if(isset($_POST['apt_taglist_checkbox_'])){ //determine if any checkbox was checked
		foreach($_POST['apt_taglist_checkbox_'] as $id => $value){ //loop for handling checkboxes
			mysql_query("DELETE FROM $apt_table WHERE id=$id");
		}
		update_option('apt_stats_current_tags', mysql_num_rows(mysql_query("SELECT id FROM $apt_table"))); //update stats

		echo '<div id="message" class="updated"><p>All chosen tags have been deleted.</p></div>';
	}
	else{
		echo '<div id="message" class="error"><p><b>Error:</b> You must choose at least one tag in order to delete it.</p></div>';
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

	echo '<div id="message" class="updated"><p>All tags have been saved.</p></div>';

	//warning messages appearing when "unexpected" character are being saved - user-moron scenarios
	if($apt_saved_tag_empty_error == 1){
		echo '<div id="message" class="error"><p><b>Error:</b> Some tag names were saved as empty strings, their previous values were restored.</p></div>'; //warning message
	}
	if($apt_saved_tag_aplhanumeric_warning == 1){
		echo '<div id="message" class="error"><p><b>Warning:</b> Some tag names contain non-alphanumeric characters.</p></div>'; //warning message
	}
	if($apt_saved_related_words_aplhanumeric_warning == 1){
		echo '<div id="message" class="error"><p><b>Warning:</b> Some related words contain non-alphanumeric characters.</p></div>'; //warning message
	}
	if($apt_saved_related_words_extra_spaces_warning == 1){
		echo '<div id="message" class="error"><p><b>Warning:</b> Some related words contain extra spaces near semicolons.</p></div>'; //warning message
	}
	if($apt_saved_related_words_asterisk_warning == 1){
		echo '<div id="message" class="error"><p><b>Warning:</b> Your related words contain an asterisk, but using wildcards is currently disabled!</p></div>'; //warning message
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
		echo '<div id="message" class="updated"><p>All <b>'. $apt_current_tags .'</b> tags have been imported.</p></div>'; //confirm message

		if($apt_imported_current_tag_aplhanumeric_warning == 1){
			echo '<div id="message" class="error"><p><b>Warning:</b> Some tag names contain non-alphanumeric characters.</p></div>'; //warning message
		}
	}
	else{
		echo '<div id="message" class="error"><p><b>Error:</b> There aren\'t any tags in your database.</p></div>'; //confirm message
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
			echo '<div id="message" class="updated"><p>All tags from your backup have been imported.</p></div>';

			if($apt_imported_tag_aplhanumeric_warning == 1){
				echo '<div id="message" class="error"><p><b>Warning:</b> Some tag names contain non-alphanumeric characters.</p></div>'; //warning message
			}
			if($apt_imported_related_words_asterisk_warning == 1){
				echo '<div id="message" class="error"><p><b>Warning:</b> Your related words contain an asterisk, but using wildcards is currently disabled!</p></div>'; //warning message
			}
			if($apt_imported_related_words_aplhanumeric_warning == 1){
				echo '<div id="message" class="error"><p><b>Warning:</b> Some related words contain non-alphanumeric characters.</p></div>'; //warning message
			}
			if($apt_imported_related_words_extra_spaces_warning == 1){
				echo '<div id="message" class="error"><p><b>Warning:</b> Some related words contain extra spaces near semicolons.</p></div>'; //warning message
			}
			if($apt_imported_tag_empty_error == 1){
				echo '<div id="message" class="error"><p><b>Error:</b> Some tags weren\'t imported because their names were missing.</p></div>'; //warning message
			}
		}
		else{ //cannot upload file
			echo '<div id="message" class="error"><p><b>Error:</b> The file could not be uploaded.</p></div>'; //error message
		}
	}
	else{ //the file name is invalid
		echo '<div id="message" class="error"><p><b>Error:</b> The name of the imported file must be "'. $apt_backup_file_name .'".</p></div>'; //error message
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
		echo '<div id="message" class="updated"><p>Your <a href="'. $apt_backup_file_export_url .'">backup</a> has been created.</p></div>';
	}
	else{
		echo '<div id="message" class="error"><p><b>Error:</b> Your backup could not be created. Change the permissions of the directory <code>'. dirname(__FILE__) .'</code> to 777 first.</p></div>'; //error message
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
		echo '<div id="message" class="error"><p><b>Error:</b> There aren\'t any tags that can be added to posts.</p></div>';
	}
	if(mysql_num_rows($apt_table_select_posts) == 0){
		$apt_assign_tags_to_all_posts_error = 1;
		echo '<div id="message" class="error"><p><b>Error:</b> There aren\'t any posts that can be processed.</p></div>';
	}
	if($apt_tag_maximum == 0){
		$apt_assign_tags_to_all_posts_error = 1;
		echo '<div id="message" class="error"><p><b>Error:</b> The maximum number of tags per post is set to <b>zero</b>. No tags can be added!</p></div>';
	}
	if(get_option('apt_post_analysis_title') == 0 AND get_option('apt_post_analysis_content') == 0 AND get_option('apt_post_analysis_excerpt') == 0){
		$apt_assign_tags_to_all_posts_error = 1;
		echo '<div id="message" class="error"><p><b>Error:</b> The script isn\'t allowed to analyze any content.</p></div>';
	}
	#################################################################

	if($apt_assign_tags_to_all_posts_error != 1){//run only if no error occured
		while($apt_post_id = mysql_fetch_array($apt_table_select_posts, MYSQL_NUM)){ //run loop to process all posts that are not auto-draft and in trash
			apt_tagging_algorithm($apt_post_id[0], 'nocheck'); //send the current post ID and '1' to let the script know that we do not want to check user-moron scenarios again
		}//-while

		echo '<div id="message" class="updated"><p>Automatic Post Tagger has processed '. $apt_table_wp_post_count .' posts.</p></div>';
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
					</ul>
				</div>
			</div>
			<!-- //-postbox -->

			<!-- postbox -->
			<div class="postbox">
				<h3>Show some love!</h3>
				<div class="inside">
					<p>If you find this plugin useful, please give it a good rating and share it with others.</p>
<!--
					<p>If you find this plugin useful, please consider donating. Every donation, no matter how small, is appreciated. Your support helps cover the <acronym title="webhosting fees etc.">costs</acronym> associated with development of this <em>free</em> software.</p>

					<ul>
					<li><a class="apt_sidebar_link apt_donate" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=T2QUJ4R6JHKNG">Donate with PayPal</a></li>
					</ul>

					<p>If you can't donate, it's OK - there are other ways to make the developer happy.</p>
-->
					<ul>
					<li><a class="apt_sidebar_link apt_rate" href="http://wordpress.org/extend/plugins/automatic-post-tagger">Rate plugin at WordPress.org</a></li>
					<li><a class="apt_sidebar_link apt_twitter" href="http://twitter.com/home?status=Automatic Post Tagger - useful WordPress plugin that automatically adds user-defined tags to posts. http://wordpress.org/extend/plugins/automatic-post-tagger">Post a link to Twitter</a></li>
					<li><a class="apt_sidebar_link apt_wp_new_post" href="<?php echo admin_url('post-new.php'); ?>">Review this plugin on your blog</a></li>
					</ul>

					<p>Thank you.</p>

				</div>
			</div><!-- //-postbox -->
			
			<!-- postbox -->
			<div class="postbox">
				<h3>Recent contributions <span style="float:right;"><small><a href="http://wordpress.org/extend/plugins/automatic-post-tagger/other_notes">Full list</a></small></span></h3>
				<div class="inside">
					<p><iframe border="0" allowtransparency="yes" style="width:100%; height:35px;" src="http://devtard.com/projects/automatic-post-tagger/contributors.php" frameborder="0" scrolling="no">List of recent contributors</iframe></p>
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
							<input type="checkbox" name="apt_post_analysis_title" id="apt_post_analysis_title" <?php if(get_option('apt_post_analysis_title') == 1) echo 'checked="checked"'; ?>> <label for="apt_post_analysis_title">Title</label><br />
							<input type="checkbox" name="apt_post_analysis_content" id="apt_post_analysis_content" <?php if(get_option('apt_post_analysis_content') == 1) echo 'checked="checked"'; ?>> <label for="apt_post_analysis_content">Content</label><br />
							<input type="checkbox" name="apt_post_analysis_excerpt" id="apt_post_analysis_excerpt" <?php if(get_option('apt_post_analysis_excerpt') == 1) echo 'checked="checked"'; ?>> <label for="apt_post_analysis_excerpt">Excerpt</label>
						</p>	
						<p>
							<b>Handling current tags</b><br />
							<small>What should the plugin do if posts already have tags?</small><br />
							<input type="radio" name="apt_handling_current_tags" id="apt_handling_current_tags_1" value="1" <?php if(get_option('apt_handling_current_tags') == 1) echo 'checked="checked"'; ?>> <label for="apt_handling_current_tags_1">Append new tags to old tags</label><br />
							<input type="radio" name="apt_handling_current_tags" id="apt_handling_current_tags_2" value="2" <?php if(get_option('apt_handling_current_tags') == 2) echo 'checked="checked"'; ?>> <label for="apt_handling_current_tags_2">Replace old tags with newly generated tags</label><br />
							<input type="radio" name="apt_handling_current_tags" id="apt_handling_current_tags_3" value="3" <?php if(get_option('apt_handling_current_tags') == 3) echo 'checked="checked"'; ?>> <label for="apt_handling_current_tags_3">Do nothing</label>
						</p>
						<p>
							<b>String manipulation</b><br />
							<small>How should the searching algorithm behave?</small><br />
							<input type="checkbox" name="apt_string_manipulation_convert_diacritic" id="apt_string_manipulation_convert_diacritic" <?php if(get_option('apt_string_manipulation_convert_diacritic') == 1) echo 'checked="checked"'; ?>> <label for="apt_string_manipulation_convert_diacritic">Convert Latin diacritic characters to their ASCII equivalents (required if your language isn't English)</label><br />
							<input type="checkbox" name="apt_string_manipulation_lowercase" id="apt_string_manipulation_lowercase" <?php if(get_option('apt_string_manipulation_lowercase') == 1) echo 'checked="checked"'; ?>> <label for="apt_string_manipulation_lowercase">Lowercase strings to ignore the case sensitivity</label><br />
							<input type="checkbox" name="apt_string_manipulation_strip_tags" id="apt_string_manipulation_strip_tags" <?php if(get_option('apt_string_manipulation_strip_tags') == 1) echo 'checked="checked"'; ?>> <label for="apt_string_manipulation_strip_tags">Strip PHP/HTML tags from analysed content</label><br />
							<input type="checkbox" name="apt_string_manipulation_replace_whitespaces" id="apt_string_manipulation_replace_whitespaces" <?php if(get_option('apt_string_manipulation_replace_whitespaces') == 1) echo 'checked="checked"'; ?>> <label for="apt_string_manipulation_replace_whitespaces">Replace (multiple) whitespace characters with spaces (and treat them as separators)</label><br />
							<input type="checkbox" name="apt_string_manipulation_replace_nonalphanumeric" id="apt_string_manipulation_replace_nonalphanumeric" <?php if(get_option('apt_string_manipulation_replace_nonalphanumeric') == 1) echo 'checked="checked"'; ?>> <label for="apt_string_manipulation_replace_nonalphanumeric">Replace non-alphanumeric characters with spaces (and treat them as separators)</label><br />
							<span style="margin-left: 18px;"><small>(If enabled, deleting user-defined word separators is recommended for better performance.)</small></span><br />
							<span style="margin-left: 18px;"><input type="checkbox" name="apt_string_manipulation_ignore_asterisks" id="apt_string_manipulation_ignore_asterisks" <?php if(get_option('apt_string_manipulation_ignore_asterisks') == 1) echo 'checked="checked"'; ?>> <label for="apt_string_manipulation_ignore_asterisks">Don't replace asterisks</label>
						</p>
						<p>
							<b>Word recognition</b><br />
							<small>How should APT recognize words?</small><br />
							<label for="apt_word_recognition_separators">Word separators:</label> <input type="text" name="apt_word_recognition_separators" id="apt_word_recognition_separators" value="<?php echo htmlentities(get_option('apt_word_recognition_separators'), ENT_QUOTES); ?>" maxlength="255" size="25"> <small>(spaces are already treated as separators by default)</small><br />
						</p>
						<p>
							<b>Miscellaneous</b><br />
							<label for="apt_miscellaneous_tag_maximum">Maximum number of tags per post:</label> <input type="text" name="apt_miscellaneous_tag_maximum" id="apt_miscellaneous_tag_maximum" value="<?php echo get_option('apt_miscellaneous_tag_maximum'); ?>" maxlength="10" size="3"><br />
							<input type="checkbox" name="apt_miscellaneous_substring_analysis" id="apt_miscellaneous_substring_analysis" <?php if(get_option('apt_miscellaneous_substring_analysis') == 1) echo 'checked="checked"'; ?>> <label for="apt_miscellaneous_substring_analysis">Analyze only</label> <input type="text" name="apt_miscellaneous_substring_analysis_length" value="<?php echo get_option('apt_miscellaneous_substring_analysis_length'); ?>" maxlength="10" size="2"> characters starting at position <input type="text" name="apt_miscellaneous_substring_analysis_start" value="<?php echo get_option('apt_miscellaneous_substring_analysis_start'); ?>" maxlength="5" size="3"> <small>(<a href="http://www.php.net/manual/en/function.substr.php" title="Manual entry for function substr">more information</a>)</small><br />
							<input type="checkbox" name="apt_miscellaneous_wildcards" id="apt_miscellaneous_wildcards" <?php if(get_option('apt_miscellaneous_wildcards') == 1) echo 'checked="checked"'; ?>> <label for="apt_miscellaneous_wildcards">Use the wildcard (*) to substistute any aplhanumeric characters in related words</label><br />
							<span style="margin-left: 18px;"><small>(Example: pattern "cat*" will match words "cats" and "category", pattern "c*t" will match "cat" and "colt".)</small></span>
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

						<table style="width:100%;">
						<tr>
							<td style="width:30%;">Tag name <small>(example: <i>cat</i>)</small>:</td>
							<td style="width:68%;">Related words, separated by a semicolon <small>(example: <i>cats;kitty;meo*w</i>) (optional)</small>:</td></tr>
						<tr>
							<td><input style="width:100%;" type="text" name="apt_create_tag_name" maxlength="255"></td>
							<td><input style="width:100%;" type="text" name="apt_create_tag_related_words" maxlength="255"></td>
						</tr>
						</table></p>


						<p>
							<input class="button-highlighted" type="submit" name="apt_create_a_new_tag_button" value=" Create a new tag ">
							<span style="float:right;"><b>Tip:</b> You can also create tags directly from a widget located under the post editor.</span>		
					</div>
				</div>
				</form>

				<!-- //-postbox -->

				<!-- postbox -->
				<form action="<?php echo admin_url('options-general.php?page=automatic-post-tagger'); ?>" enctype="multipart/form-data" method="post">
				<div class="postbox">
					<h3>Import tags</h3>
					<div class="inside">

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

							<div style="max-height:400px;overflow:auto;"><table style="width:100%;">
							<tr><td style="width:30%;">Tag name</td><td style="width:68%;">Related words</td><td style="width:2%;"></td></tr>

						<?php
							while($row = mysql_fetch_array($apt_table_rows_all)){
							?>
								<tr>
								<td><input style="width:100%;" type="text" name="apt_taglist_tag_[<?php echo $row['id']; ?>]" id="apt_taglist_tag_<?php echo $row['id']; ?>" value="<?php echo $row['tag']; ?>" maxlength="255"></td>
								<td><input style="width:100%;" type="text" name="apt_taglist_related_words_[<?php echo $row['id']; ?>]" id="apt_taglist_related_words_<?php echo $row['id']; ?>" value="<?php echo $row['related_words']; ?>" maxlength="255"></td>
								<td><input style="width:10px;" type="checkbox" name="apt_taglist_checkbox_[<?php echo $row['id']; ?>]" id="apt_taglist_checkbox_<?php echo $row['id']; ?>" onclick="apt_change_background(<?php echo $row['id']; ?>);"></td>
								</tr>
							<?php
							}
						?>
							</table></div>

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
						<p>This tool adds tags to all posts which post status isn't "trash", "draft" or "auto-draft". It follows rules defined above.
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
