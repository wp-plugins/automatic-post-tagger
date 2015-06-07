function apt_create_new_keyword(){
	var apt_box_keyword_name = jQuery('#apt_box_keyword_name').val();
	var apt_box_keyword_related_words = jQuery('#apt_box_keyword_related_words').val();
	 
	var data = {
		action: 'apt_meta_box_create_new_keyword',
		security: apt_meta_box_nonce.security,
		apt_box_keyword_name: apt_box_keyword_name,
		apt_box_keyword_related_words: apt_box_keyword_related_words,
		};
	jQuery.ajax ({
		type: 'POST',
		url: ajaxurl,
		data: data,
		success: function(response) {
			jQuery("#apt_box_message").fadeIn("fast");
			document.getElementById("apt_box_message").innerHTML=response; //print the returned message
			jQuery('#apt_box_keyword_name, #apt_box_keyword_related_words').val(''); //delete contents of the inputs
			jQuery("#apt_box_message").delay(5000).fadeOut("slow");
		}
	});

	jQuery('#apt_box_keyword_name').focus(); //move the cursor to the first input after submitting
}

//send the data when enter is pressed
function apt_enter_submit(e){
	if (e.which == 13){
		apt_create_new_keyword();

		var $targ = jQuery(e.target);

		if (!$targ.is("textarea") && !$targ.is(":button,:submit")) {
		var focusNext = false;
		jQuery(this).find(":input:visible:not([disabled],[readonly]), a").each(function(){
			if (this === e.target) {
			focusNext = true;
			}
			else if (focusNext){
			jQuery(this).focus();
			return false;
			}
		});

		return false;
		}
	}
}

jQuery(function(){
	//bind apt_create_new_keyword() and execute both functions that prevent submitting the form and the other one that adds new keywords to DB
	jQuery('#apt_meta_box_create_new_keyword_button').click(function(){
		apt_create_new_keyword();
	});
});
