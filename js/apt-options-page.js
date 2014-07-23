function apt_toggle_widget(num){
	var ele = document.getElementById("apt_widget_id_["+num+"]");

	var apt_widget_id = num;

	if(ele.style.display == "block"){
    	ele.style.display = "none"; //display the change immediately - we won't wait for the script, that would be too slow

		//save id to db
		var data = {
			action: 'apt_toggle_widget',
			security: apt_options_page_nonce.security,
			apt_widget_id: apt_widget_id,
			};
		jQuery.ajax ({
			type: 'POST',
			url: ajaxurl,
			data: data,
		});
  	}
	else {
		ele.style.display = "block"; //display the change immediately - we won't wait for the script, that would be too slow

		//delete id from db
		var data = {
			action: 'apt_toggle_widget',
			security: apt_options_page_nonce.security,
			apt_widget_id: apt_widget_id,
			};
		jQuery.ajax ({
			type: 'POST',
			url: ajaxurl,
			data: data,
		});
	}
}


//change backgrounds of input fields
function apt_change_background(num){
	if (document.getElementById("apt_keywordlist_checkbox_"+num).checked){
		document.getElementById("apt_keywordlist_keyword_"+num).style.backgroundColor='#FFD2D2';
		document.getElementById("apt_keywordlist_related_words_"+num).style.backgroundColor='#FFD2D2';
	}
	else{
		document.getElementById("apt_keywordlist_keyword_"+num).style.backgroundColor='';
		document.getElementById("apt_keywordlist_related_words_"+num).style.backgroundColor='';
	}
}
