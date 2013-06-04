jQuery(document).ready(function($) {

	// debug output
	if (typeof soma_vars != 'undefined' && soma_vars['debug'] == 'true') {
		if ($().jquery != 'undefined') console.log("jquery version: "+$().jquery);			// jquery version
		if ($.ui.version != 'undefined') console.log("jqueryUI version: "+$.ui.version);	// jqueryUI version
		console.log(soma_vars);																// array of vars passed from admin.php
	}


	// key commands for toggling the debug bar panels
	if (typeof soma_vars != 'undefined' && soma_vars['debug_panel'] === 'true') {
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

	if (typeof soma_vars != 'undefined' && soma_vars['colorbox'] === 'true') {
		// colorbox activation
		$("a[rel=colorbox]").colorbox();
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
	}
	// end
});