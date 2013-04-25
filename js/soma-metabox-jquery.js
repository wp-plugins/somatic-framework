jQuery(document).ready(function($) {

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
				|| event.keyCode == 186		// colon (for time)
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
			altField: $(this).next('.datemirror'),	// display human-readable date in dummy field
			altFormat: "MM dd, yy"
		});
	});

	// jquery UI datepicker for metaboxes
	$( ".timepicker" ).each(function(){
			$(this).timepicker({
			// timeFormat: 'hh:mm:ss',	// matches php format H:i:s
			timeFormat: 'h:mm TT',	// matches php format H:i:s
			ampm: true,
			stepHour: 1,
			stepMinute: 5,
			hourMin: 1,
			hourMax: 23,
			hourGrid: 4,
			minuteGrid: 10,
			showOn: 'button',
			showSecond: false,
			buttonImage: soma_vars.SOMA_JS + '/ui/clock-icon.png',
			buttonImageOnly: true,
			buttonText: 'Click to Select'
			// altField: $(this).next('.timemirror'),	// display human-readable date in dummy field
			// altFormat: "h:mm TT"
		});
	});

	// mirror contents of hidden time input field to #humantime field - needed as the "altField" option doesn't seem to work with timepicker extension of datepicker...
	$( ".timepicker" ).change(function(){
		$(this).siblings('input').val($(this).val());
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

	// removes missing class when the user clicks on picker button
	$(".ui-timepicker-trigger").click(function(){
		if ($(this).siblings('input').hasClass("missing") ) {
			$(this).siblings('input').removeClass("missing");
		}
	});


	// clears value from UI datepicker (and hidden form input)
	$(".datereset").click(function(){
		$(this).siblings('.datepicker').datepicker( "setDate" , null );
		$(this).siblings('.datemirror').val('(none selected)');
	});

	// clears value from UI datepicker (and hidden form input)
	$(".timereset").click(function(){
		$(this).siblings('.timepicker').datepicker( "setDate" , null );
		$(this).siblings('.timemirror').val('(none selected)');
	});



	// clones additional inputs
	$(".addinput").click(function() {
		rowid = $(this).attr("rel");
		$("#"+rowid+"-row input:first").clone().insertAfter("#"+rowid+"-row input:last").show();
		return false;
	});

	// ajax attachment killer
	$(".att-text > a").on("click", function (e) {
		e.preventDefault();
		$(this).next('input').toggle();
		$(this).next('textarea').toggle();
	});

	// draggable metabox attachment items
	$(".meta-attachment-gallery").sortable({
    	opacity: 0.6,
    	distance: 15,
    	revert: true,
    	containment: "parent",
    	cursor: 'move',
	});

	// ajax attachment killer
	$("a.delete-attachment").on("click", function () {
		if (confirm('Are you sure you want to delete this attachment?')) {
			var container = $(this).parent().parent().parent();					// this is clunky...
			$(this).next('.kill-animation').show();								// anim gif
			if ($(this).attr("data-featured") == "true") var featured = true;	// is this for a featured image?
			opts = {
				url: ajaxurl,
				type: 'POST',
				async: true,
				cache: false,
				dataType: 'json',
				data:{
					action: 'delete_attachment',
					nonce: $(this).attr("data-nonce"),
					data: $(this).attr("rel")							// contains attachment ID integer
				},
				beforeSend: function(jqXHR, settings) {					// optionally process data before submitting the form via AJAX

				},
				success : function(response, status, xhr, form) {
					// console.log(response.msg);
					// remove the thumbnail here ??
					$(this).next('.kill-animation').hide();
					container.fadeOut( "fast", function() {
						// if this was a featurd image, show the uploader
						if (featured) {
							$(this).parents('ul:first').next('.plupload-container').fadeIn('fast');
							$(this).parents('ul:first').remove();		// kill this attachment thumb container
							return;
						}
						// if this is the last attachment, get rid of the whole row
						if (container.parent().children().length == 1) {
							if (container.parents('tr:first').next().hasClass('desc-row')) {
								container.parents('tr:first').next().remove();		// kill the following description row, if exists
							}
							container.parents('tr:first').remove();		// kill the attachments field row (including this thumb)
							return;
						}
						$(this).remove();								// kill this attachment thumb container
					});
					return;
				},
				error: function(xhr,textStatus,e) {
					$(this).next('.kill-animation').hide();
					console.log(xhr.msg);
				}
			};
			$.ajax(opts);												// send off ajax
		}
		return false;
	});

	// revealing the new term input field when "Create New" is selected
	$(".meta-select").change(function() {
		var foo = $(this).children('select option:selected').val();
		var tax = $(this).data('taxonomy');
		if (foo == 'create') {
			$("#"+tax+"-create").show().prop('disabled', false);
		} else {
			$("#"+tax+"-create").hide().prop('disabled', true);
		}
	});


	// based on initial value of the toggle controller, show/hide fieldrows
	var initstr = "";
	if ($(".reveal-control").length) {
		controller = $(".reveal-control");
		if (controller.hasClass('meta-select')) {
			initstr = controller.children('select option:selected').text();
		}
		if (controller.hasClass('meta-radio')) {
			initstr = controller.find('input:radio:checked').parent().text();
		}
		togglegroup(initstr);
	}

	// when the toggle controller is changed, fetch the selected value and pass to the toggler
	$(".reveal-control").change(function() {
		var which = "";
		if ($(this).hasClass('meta-select')) {
			which = $(this).children('select option:selected').text();
		}
		if ($(this).hasClass('meta-radio')) {
			which = $(this).find('input:radio:checked').parent().text();
		}
		togglegroup(which);
	});

	// take the current value and compare to the array of values for a toggleable field-row, toggles it
	function togglegroup(which) {
		$(".reveal-row").each(function() {
			group = $(this).data('reveal-group');
			if ($.inArray(which, group) == -1) {			// this fieldrow isn't in the group for this selector value
				$(this).hide();
			} else {
				$(this).show();
			}
		});
	}

	// automatically grow textareas as you type
	// $('textarea').autosize();

	// match trigger play icon block to the item it's overlaying
	// $(".trigger").each(function(){
	// 	$(this).width($(this).next().width());
	// 	$(this).height($(this).next().height());
	// });

	// end
});