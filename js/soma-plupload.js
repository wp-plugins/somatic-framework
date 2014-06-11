jQuery.fn.exists = function() {
    return jQuery(this).length > 0;
};

// separate debug function fallback to alert in IE
function trace(s) {
	if (soma_vars['debug'] == 'false') return;		// abort if not in debug mode
	try { console.log(s); } catch (e) { /* nothing... alert(s) */ }
}

// php mirror functions
function basename(path) {
	return path.replace(/\\/g,'/').replace( /.*\//, '' );
}

function dirname(path) {
	return path.replace(/\\/g,'/').replace(/\/[^\/]*$/, '');
}

var pendingcount = 0;

jQuery(document).ready(function($) {

	if ($(".plupload-container").exists()) {
		var pconfig = false;

		$(".plupload-container").each(function() {
			var $this = $(this);
			var fieldID = $this.data('fieldid');									// identify instance
			var thisconfig = window[fieldID+"_plupload_config"];					// dynamically link to variable passed by wp_localize_script
			pconfig = JSON.parse(JSON.stringify(thisconfig));						// retrieve passed params

			// init uploader
			var uploader = new plupload.Uploader(pconfig);
			uploader.bind('Init', function(up) {
			});
			uploader.init();

            // file(s) added in the plupload queue
			uploader.bind('FilesAdded', function(up, files) {
				trace( "Proposed total: " + (files.length + pendingcount) );
				// check if the amount of new files added plus any already pending will exceed the max
				if ( ( files.length + pendingcount ) > uploader.settings.max_file_count ) {
					alert('Select no more than '+uploader.settings.max_file_count+' files per upload!');
					up.splice();													// clears upload queue
					return false;													// die
				}

				trace('Going to add ' + files.length + ' files...');
				// spawn progress for each file
				$.each(files, function(i, file) {
						$this.find('.filelist').append(
						'<div class="file" id="' + file.id + '"><strong>' +
						file.name + '</strong> (<span>' + plupload.formatSize(0) + '</span>/' + plupload.formatSize(file.size) + ') ' +
						'<div class="fileprogress"></div></div>');
				});
				up.refresh();
				up.start();
			});

			uploader.bind('QueueChanged', function(up) {
				trace('Queued files: ' + uploader.files.length);
			});


			// update progress
			uploader.bind('UploadProgress', function(up, file) {

				$('#' + file.id + " .fileprogress").width(file.percent + "%");
				$('#' + file.id + " span").html(plupload.formatSize(parseInt(file.size * file.percent / 100), 10));
			});

			// set index for hidden input names. Gets incremented with each upload, and does not reset until page reload
			// so that even when images are removed, new uploads don't create conflicting indexes
			// does mean that the resulting POST could send array with missing keys, use array_values() to rebuild array keys
			var index = 0;
			// a file was uploaded
			uploader.bind('FileUploaded', function(up, file, result) {
				var data = $.parseJSON(result.response);							// wp_handle_upload returns url, file, type
				if ( data == '0' ) {
					trace('there was a problem with the ajax callback...');
					return;															// abort if callback fails
				}
				if ( data === null || 'error' in data ) {
					trace('there was a problem with wp_handle_upload...');
					return;															// abort if wp_handle_upload returned error
				}
				$('#' + file.id).fadeOut();

				// grab the input element from the settings (stored as actual DOM element now, not just ID string)
				// retrieve the form element that this input is embedded in
				var thisform = uploader.settings.browse_button[0].form;

				// create new hidden inputs and append to this form, to store wp_handle_upload data
				$('<input>').attr({
					'type': 'hidden',
					'name': fieldID+'['+index+'][file]',
					'value': data.file,
					'class': 'storage-file'
					}).appendTo(thisform);
				$('<input>').attr({
					'type': 'hidden',
					'name': fieldID+'['+index+'][url]',
					'value': data.url,
					'class': 'storage-url'
					}).appendTo(thisform);
				$('<input>').attr({
					'type': 'hidden',
					'name': fieldID+'['+index+'][type]',
					'value': data.type,
					'class': 'storage-type'
					}).appendTo(thisform);


				// show thumbs
				plu_add_thumbs(fieldID, index, uploader.settings.max_file_count);
				$("#" + fieldID+ '_pending-thumbs').show();							// reveal the hidden pending row
				index++;															// increment for subsequent uploads
			});

			uploader.bind('FileUploaded', function(up, file, response) {
				trace('A File Uploaded!');
				pendingcount++;
				trace ('Pending Uploads: ' + pendingcount);
			});

			uploader.bind('UploadComplete', function(up, files) {
				trace('Completed files: ' + uploader.files.length);
				up.splice();														// reset the queue
				if ( pendingcount >= uploader.settings.max_file_count ) {
					trace('Maxed!');
					$("#" + fieldID + '-row' ).fadeOut('fast');		// hide the uploader UI to prevent further uploads
				}
			});

			uploader.bind('Error', function(up, args) {
				trace(args.code + ': ' + args.message);
			});

        });
    }


});

function plu_add_thumbs(fieldID, index, max) {
	var $ = jQuery;
	var uploadContainer = $("#" + fieldID + "_plupload-upload-ui");					// div container for relevant plupload instance
	var thumbsContainer = $("#" + fieldID + "_plupload-thumbs");					// div container for generated thumbs
	var url = $('input[name="'+ fieldID +'['+ index +'][url]"]').val();
	var type = $('input[name="'+ fieldID +'['+ index +'][type]"]').val();
	// extract file extension from url
	var filename = basename(url);
	var extension = filename.split('.').pop();
	switch (type) {
		case "application/pdf" :
		case "audio/mpeg" :
		case "audio/wav" :
		case "audio/x-aiff" :
		case "video/mp4" :
		case "application/zip" :
		case "application/msword":
		case "application/vnd.ms-word" :
		case "application/msexcel" :
		case "application/vnd.ms-excel" :
		case "application/mspowerpoint" :
		case "application/vnd.ms-powerpoint" :
		case "application/octet-stream" :
			var newthumb = $('<div class="thumb file" id="' + fieldID + '_thumb' + index + '"><div class="icon" style="background-image: url(\''+soma_vars["SOMA_IMG"]+'file-icons/'+extension+'.png\');"></div>'+filename+'<br/><div class="pendinginfo"><a class="kill" data-field="' + fieldID +'" data-index="' + index + '" href="#">Remove File</a><img src="'+soma_vars['loading-spin']+'" class="kill-animation" style="display:none;" alt="" /></div><div class="clear"></div></div>');
		break;
		case "image/jpeg" :
		case "image/png" :
			var newthumb = $('<div class="thumb image" id="' + fieldID + '_thumb' + index + '"><a href="'+url+'" class="colorbox" rel="pending-gallery" title="'+basename(url)+'"><img src="' + url + '" alt="" /></a><div class="thumbinfo"><a class="kill" data-field="' + fieldID +'" data-index="' + index + '" href="#">Remove File</a><img src="'+soma_vars['loading-spin']+'" class="kill-animation" style="display:none;" alt="" /></div><div class="clear"></div></div>');
		break;
		default:
			var newthumb = $('<div class="thumb file" id="' + fieldID + '_thumb' + index + '"><div class="icon" style="background-image: url(\''+soma_vars["SOMA_IMG"]+'file-icons/bin.png\');"></div>'+filename+'<br/><em>Can\'t tell what kind of file this is!</em><div class="pendinginfo"><a class="kill" data-field="' + fieldID +'" data-index="' + index + '" href="#">Remove File</a><img src="'+soma_vars['loading-spin']+'" class="kill-animation" style="display:none;" alt="" /></div><div class="clear"></div></div>');
	}

	thumbsContainer.append(newthumb);

	// init colorbox on new dom elements
	$('.thumb > .colorbox').colorbox({
		scalePhotos: true,
		scrolling: false
	});

	newthumb.find('a.kill').on("click", function(event) {							// has to be here because doesn't exist in DOM until now
		var fieldID = $(this).attr("data-field");
		var index = $(this).attr("data-index");
		var file = $('input[name="'+ fieldID +'['+ index +'][file]"]').val();				// retrieve from hidden input storage

		// ajax callback to execute php unlink on the file
		$(this).next('.kill-animation').show();										// show a loading icon while waiting...
		opts = {
			url: ajaxurl,
			type: 'POST',
			async: true,
			cache: false,
			dataType: 'json',
			data:{
				action: 'unlink_file',
				data: file
			},
			beforeSend: function(jqXHR, settings) {									// optionally process data before submitting the form via AJAX
			},
			success : function(response, status, xhr, form) {
				trace(response.msg);
				$(this).next('.kill-animation').hide();
				$('input[name="'+ fieldID +'['+ index +'][file]"]').remove();		// remove hidden storage
				$('input[name="'+ fieldID +'['+ index +'][url]"]').remove();		// remove hidden storage
				$('input[name="'+ fieldID +'['+ index +'][type]"]').remove();		// remove hidden storage
				$('#' + fieldID + '_thumb' + index).fadeOut( "fast", function() {
					$(this).remove();												// fade and remove the thumbnail element
					pendingcount--;														// decrement global count
					trace ('Removed a file, pending Uploads: ' + pendingcount);
				});
				if ( $('#' + fieldID + '_plupload-thumbs').children().length == 1 ) {		// have we removed the last thumb?
					$('#' + fieldID + '_pending-thumbs').hide();								// hide the pending row
				}
				if ( $('#' + fieldID + '_plupload-thumbs').children().length <= max) {
					$("#" + fieldID + '-row' ).fadeIn('fast');							// bring back the uploader interface
				}

				return;
			},
			error: function(xhr,textStatus,e) {
				trace(xhr.msg);
				alert(xhr.msg);
				$(this).next('.kill-animation').hide();
			}
		};
		$.ajax(opts);		// send off ajax

		return false;
	});

	// SORTABLE REENABLE LATER
	// if (images.length > 1) {
	//	thumbsContainer.sortable({
	// 		update: function(event, ui) {
	//			var kimages = [];
	//			thumbsContainer.find("img").each(function() {
	// 				kimages[kimages.length] = $(this).attr("src");
	// 				$("#" + fieldID).val(kimages.join());
	// 				plu_show_thumbs(fieldID);
	// 			});
	// 		}
	// 	});
	// 	thumbsContainer.disableSelection();
	// }
}