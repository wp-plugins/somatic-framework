jQuery(document).ready(function($) {
	var typeList = $('#type-sort-list');

	$(".loading-animation").hide();

	typeList.sortable({
		update: function(event, ui) {
			$('.loading-animation').show(); // Show the animate loading gif while waiting

			opts = {
				url: ajaxurl, // ajaxurl is defined by WordPress and points to /wp-admin/admin-ajax.php
				type: 'POST',
				async: true,
				cache: false,
				dataType: 'json',
				data:{
					action: 'custom_type_sort', // Tell WordPress how to handle this ajax request
					order: typeList.sortable('toArray').toString() // Passes ID's of list items in	1,3,2 format
				},
				beforeSend: function(jqXHR, settings) {				// optionally process data before submitting the form via AJAX
					waiting_for_server('start');	// wait animation
				},
				success : function(response, status, xhr, form) {
					console.log(response);
					waiting_for_server('end');	// wait animation
					return;
				},
				error: function(xhr,textStatus,e) {
					console.log(xhr);
					alert('there was an error saving the changes!');
					$("#loading").hide();
				}
			};
			$.ajax(opts);
		}
	});

	// controls animation and changes while waiting for server response
	function waiting_for_server(which) {
		if (which == 'start') {
			$(".loading-animation").show();									// show animated loading while waiting for response from server
		} else {
			$(".loading-animation").hide();									// hide loading anim
		}
	}
});