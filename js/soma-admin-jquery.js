jQuery(document).ready(function($) {
	// debug
	console.log("jquery version: "+$().jquery);	// jquery version
	console.log("jqueryUI version: "+$.ui.version);	// jquery version
	console.log(soma_vars);	// array of vars passed from admin.php
	
	// pull in GET Vars
	$._GET = [];
	var urlHalves = String(document.location).split('?');
	if(urlHalves[1]){
		var urlVars = urlHalves[1].split('&');
		for(var i=0; i<=(urlVars.length); i++){
			if(urlVars[i]){
				var urlVarPair = urlVars[i].split('=');
				$._GET[urlVarPair[0]] = urlVarPair[1];
			}
		}
	}
	
	// limit characters for numeric input
	$("input[alt=numeric]").keydown(function(event) {
		switch (true) {
			case( event.keyCode > 32 && event.keyCode < 47 ):
				// pageup, pagedown, home, end, arrows, insert, delete
			break;
			case( event.keyCode > 47 && event.keyCode < 58 ):
				// standard numbers
			break;
			case( event.keyCode > 95 && event.keyCode < 106 ):
				// keypad numbers
			break;
			case ( event.keyCode == 8		// backspace
				|| event.keyCode == 9		// tab
				|| event.keyCode == 17		// control
				|| event.keyCode == 109 	// dash
				|| event.keyCode == 110 	// period
				|| event.keyCode == 189 	// subtract
				|| event.keyCode == 190		// decimal
				):
			break;
			default:
				event.preventDefault();		// stop character entry
		}		
	});

	// jquery UI datepicker for metaboxes
	$( ".datepicker" ).each(function(){
		$(this).datepicker({
			dateFormat: 'yy-mm-dd',	// will store selected date in hidden input field in ISO8601 format - matches php format Y-m-d
			// gotoCurrent: true,
			showButtonPanel: true,
			showOn: 'button',
			buttonImage: soma_vars.SOMA_JS + 'ui/calendar-icon.png',
			buttonImageOnly: true,
			buttonText: 'Click to Select',
			altField: $(this).next('.datehuman'),	// display human-readable date in dummy field
			altFormat: "MM dd, yy"
		});
	});

	// removes missing class when the user clicks on input field
	$(":input").focus(function(){
		if ($(this).hasClass("missing") ) {
			$(this).removeClass("missing");
		}
		// for the p2p input box
		if ($(this).parents('div').hasClass("missing") ) {
			$(this).parents('div').removeClass("missing");
		}		
	});
	
	$(':checkbox, :radio').click(function () {
	    $(this).parents('ul').removeClass("missing");
	});
	
	// removes missing class when the user clicks on picker button
	$(".ui-datepicker-trigger").click(function(){
		if ($(this).siblings('input').hasClass("missing") ) {
			$(this).siblings('input').removeClass("missing");
		}
	});
	
	
	// clones additional inputs
	$(".addinput").click(function() {
		rowid = $(this).attr("rel");
		$("#"+rowid+"-row input:first").clone().insertAfter("#"+rowid+"-row input:last").show();
		return false;
	});
		
	// ajax file attachment killer
	$("a.deletefile").click(function () {
		if (confirm('Are you sure you want to delete this file?')) {
			var parent = jQuery(this).parent(),
				data = jQuery(this).attr("rel"),
				_wpnonce = $("input[name='_wpnonce']").val();

			$.post(
				ajaxurl,
				{ action: 'unlink_file', _wpnonce: _wpnonce, data: data },
				function(response){
					//$("#info").html(response).fadeOut(3000);
					// alert(data.post);
				},
				"json"
			);
			parent.fadeOut("slow");	
		}
		return false;
	});

	// colorbox activation through manual class assignment
	$("a.colorbox").colorbox({
		inline: function(){
		    return $(this).attr('inline');
		},
		href: function(){
		    return $(this).attr('href');
		},
		iframe: function(){
		    return $(this).attr('iframe');
		},
		width: function(){
			if ($(this).attr('iframe') == "true") {
				return "90%";
			}
		},
		height: function(){
			if ($(this).attr('iframe') == "true") {
				return "90%";
			}
		},
		maxWidth:"100%",
		maxHeight:"100%",
		scalePhotos: true,
		scrolling: false,
	});

	// end
});