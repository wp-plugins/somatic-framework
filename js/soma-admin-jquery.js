jQuery(document).ready(function($) {
	// debug
	if (soma_vars['debug'] == 'true') {
		console.log("jquery version: "+$().jquery);	// jquery version
		console.log("jqueryUI version: "+$.ui.version);	// jquery version
		console.log(soma_vars);	// array of vars passed over by wp_localize_script()		
	}
	
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

	// colorbox activation through manual class assignment
	$(".colorbox").colorbox({
		// inline: function(){
		//     return $(this).attr('inline');
		// },
		iframe: function(){
		    return $(this).attr('iframe');
		},
		width: function(){
			if ($(this).attr('width') !== undefined) {
				return $(this).attr('width');
			}
		},
		height: function(){
			if ($(this).attr('height') !== undefined) {
				return $(this).attr('height');
			}
		},
		maxWidth: function(){
			if ($(this).attr('maxWidth') !== undefined) {
				return $(this).attr('maxWidth');
			}
		},
		maxHeight: function(){
			if ($(this).attr('maxHeight') !== undefined) {
				return $(this).attr('maxHeight');
			}
		},
		innerWidth: function(){
			if ($(this).attr('innerWidth') !== undefined) {
				return $(this).attr('innerWidth');
			}
		},
		innerHeight: function(){
			if ($(this).attr('innerHeight') !== undefined) {
				return $(this).attr('innerHeight');
			}
		},
		scalePhotos: true,
		scrolling: false,
		fastIframe: false
	});

	// automatically grow textareas as you type
	// $('textarea').autosize();

	// match trigger play icon block to the item it's overlaying
	// $(".trigger").each(function(){
	// 	$(this).width($(this).next().width());
	// 	$(this).height($(this).next().height());
	// });


	// key commands for toggling the debug bar panels
	if (soma_vars['debug_panel'] == 'true') {
		$(document).keydown(function(event) {
			switch (true) {
				// backslash (mini-panel)
				case ( event.keyCode == 220 && event.altKey == false):
					if ($("body").hasClass("debug-bar-partial") && $("body").hasClass("debug-bar-visible")) {
						wpDebugBar.toggle.visibility(false);
					} else {
						wpDebugBar.actions.restore();
						wpDebugBar.toggle.visibility(true);
					}

				break;
				// backslash + alt (full-panel)
				case ( event.keyCode == 220 && event.altKey == true ):
					if ($("body").hasClass("debug-bar-maximized") && $("body").hasClass("debug-bar-visible")) {
						wpDebugBar.toggle.visibility(false);
					} else {
						wpDebugBar.actions.maximize();
						wpDebugBar.toggle.visibility(true);
					}
				break;
			}
		});
	}
	// end
});