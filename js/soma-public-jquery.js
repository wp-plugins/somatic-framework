jQuery(document).ready(function($) {
	// debug
	console.log("jquery version: "+$().jquery);	// jquery version
	console.log("jqueryUI version: "+$.ui.version);	// jquery version
	console.log(soma_vars);	// array of vars passed from admin.php

	if (soma_vars['debug_panel']) {
		// keycommands for displaying the debug panel
		$("body").keydown(function(event) {
			switch (true) {
				// semicolon key
				case ( event.keyCode == 186 ):
					wpDebugBar.actions.maximize();
					wpDebugBar.toggle.visibility();
				break;
				// apostrophe key
				case ( event.keyCode == 222 ):
					wpDebugBar.actions.restore();
					wpDebugBar.toggle.visibility();
				break;
			}		
		});		
	}

	// end
});