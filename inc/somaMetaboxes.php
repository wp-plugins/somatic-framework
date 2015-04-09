<?php
class somaMetaboxes extends somaticFramework {

	function __construct() {
		add_action( 'init', array(__CLASS__,'init' ) );
		add_action( 'post_edit_form_tag' , array(__CLASS__,'post_edit_form_tag' ) );
		add_action( 'add_meta_boxes_post', array(__CLASS__, 'add_boxes'), 10, 1 );		// use our somatic metabox rendering for core post type
		add_action( 'add_meta_boxes_page', array(__CLASS__, 'add_boxes'), 10, 1 );		// use our somatic metabox rendering for core page type
		add_filter( 'redirect_post_location', array(__CLASS__, 'redirect_post_location' ), 20, 2 );
	}

	// needed to allow file upload inputs (input[type="file"]) within post.php
	function post_edit_form_tag( ) {
	    echo ' enctype="multipart/form-data"';
	}

	// modify redirect behavior after post saving
	function redirect_post_location($location, $pid) {
		if (soma_fetch_index($_POST, 'save_and_go_back')) {
			// wp_die(var_dump($_POST));
			$location = soma_fetch_index($_POST, 'referredby');
			$title = get_the_title($pid);
			$link = get_edit_post_link( $pid );
			somaFunctions::queue_notice( "updated", "Changes Saved to <a href='$link'>$title</a>" );
		}
		return $location;
	}

	static $data = array();				// container for other plugins and themes to store custom metabox and field data - SHOULD WE STORE THIS IN THE DB INSTEAD???

	function init() {

	}

	// called in the custom post type registration, responsible for rendering metaboxes in those types
	function add_boxes($post) {

		// treat core post types differently
		$type_obj = get_post_type_object($post->post_type);

		// identify this as a native CPT to differentiate between _builtin and CPT's defined by other plugins, and only in the case where no core WP box support is given
		if ($type_obj->somatic && $type_obj->blank_slate) { $warning = true; } else { $warning = false; }

		if (empty(self::$data) || !self::$data) {
			// THIS IS BAD - OUPTUTS BEFORE PAGE HEADERS
			if ($warning) add_action( 'admin_notices', call_user_func('soma_notices','update','No metaboxes have been defined! Make use of soma_metabox_data() [consult meta-config-example.php]'));
		}

		// trigger all calls to soma_metabox_data() which will each populate somaMetaboxes::$data[]
		do_action('soma_metabox_data_init', $post);

		// hook for insertion of stuff before any metaboxes are rendered
		do_action('soma_before_all_metaboxes', $post);

		$typehasabox = false;	// init marker

		// Generate meta box for each array
		foreach ( self::$data as $meta_box ) {
			$default_box_args = array(
				'types'				=> array(),				// REQUIRED! post types that will display this metabox. if empty, won't show
				'title' 			=> "Meta Box",   		// title shown at top of metabox
				'id' 				=> microtime(true),		// timestamp in microseconds, to ensure unique HTML ID for this container element
				'context' 			=> 'normal',			// positioning: normal/side columns
				'priority'			=> 'high',				// positioning: vertical order - if all have same priority, metaboxes are rendered in order they appear in somaMetaboxes::$data array
				'restrict' 			=> false,				// boolean for restricting display of this metabox for non-staff (a special somaticFramework permission class)
			);

			$meta_box = wp_parse_args( $meta_box, $default_box_args );

			if ( in_array($post->post_type, $meta_box['types'] ) ) {										// check if current metabox is indicated for the current post type
				if ( somaFunctions::fetch_index($meta_box, 'restrict') && SOMA_STAFF == false ) {					// don't show certain boxes for non-staff
					continue;
				} else {
					add_meta_box($meta_box['id'], $meta_box['title'], array(__CLASS__,'soma_metabox_generator'), $post->post_type, $meta_box['context'], $meta_box['priority'], array('box'=>$meta_box));
					$typehasabox = true;			// set marker
				}
			}
		}

		// if no metaboxes were added, then none were declared or matched the post type
		if (!$typehasabox && $warning) {
			// THIS IS BAD - OUPTUTS BEFORE PAGE HEADERS
			add_action( 'admin_notices', call_user_func('soma_notices','update','No metaboxes have been defined for this custom post type! [consult meta-config-example.php]'));
		}

		// hook for insertion before any box content
		do_action('soma_after_all_metaboxes', $post);
	}



/*
 * Metabox Field array:
 * ARGS:
	name 		- text shown in left column of field row
	id 			- (when data is taxonomy, this id must match the taxonomy slug!)
	type 		- (see below)
	data 		- meta, taxonomy, core, p2p, attachment, [save, save-back, link (only buttons)]
	options 	- array of name => value pairs
	once 		- only allows data to be set one time, then becomes readonly
	multiple 	- set to false to prevent more than one selection being saved
	required 	- set to true to trigger missing taxonomy assignment and error highlighting
	default 	- give a text string here to automatically show in input fields or have automatically selected
	desc 		- text string to show below field input
	reveal-control	- boolean assigned one selector (radio or dropdown) per page
	reveal-group 	- array of names corresponding with potential values of a reveal-control selector
 * TYPES:
	readonly
	gallery (of attachments, minus featured)
	p2p-objects
	p2p-list
	p2p-select
	p2p-multi
	text
	help
	numeric
	textarea
	richtext (using wp_editor())
	html
	upload-files (upload-images) [uses plupload]
	select
	radio
	radio-horizontal
	toggle
	checkbox
	select-multi (does that work??)
	date
	datepicker
	time
	timepicker
	weight
	media (uses old thickbox dialog media manager)
	external_media (video)
	external_image
	oembed (upcoming)
	button
 * DATA:
 	taxonomy
 	meta
 	core
	attachment
	featured
	none
*/

	// add_meta_box Callback function to display fields in meta box
	function soma_metabox_generator($post,$box, $add_meta_box = true) {
		// avoid undefined indexes and aborted metaboxes by setting defaults here (some are repeated from add boxes, in case this function is called directly)
			$default_box_args = array(
				'types'				=> array(),				// REQUIRED! post types that will display this metabox. if empty, won't show
				'title' 			=> "Meta Box",   		// title shown at top of metabox
				'id' 				=> microtime(true),		// timestamp in microseconds, to ensure unique HTML ID for this container element
				'save' 				=> false,				// boolean for displaying a "save changes" button at the end of this metabox (can have multiple on the page)
				'publish' 			=> false,				// makes the save button always change post_status to publish (instead of keeping it on whatever it was, which means new items are saved as drafts)
				'always-publish' 	=> false,				// deprecated
				'fields' 			=> array(),				// array of individual fields within this metabox
			);

		if ($add_meta_box) {
			// merge with incoming metabox args
			$meta_box = wp_parse_args( $box['args']['box'], $default_box_args );
		} else {
			$meta_box = wp_parse_args( $box, $default_box_args );
		}

		// hook for insertion before this box content
		do_action('before_'.$box['id'], $post, $box['id']);

		// Use nonce for verification
		echo '<input type="hidden" name="soma_meta_box_nonce" value="', wp_create_nonce("soma-save-asset"), '" />';

		// tag this form as containing somatic Framework fields, so our save routines can kick in
		echo '<input type="hidden" name="somatic" value="true" />';

		// build the form
		echo '<table class="form-table">';


		//** START FIELD LOOP
		//-------------------
		foreach ($meta_box['fields'] as $field) {
			$meta = null;																			// reset the value of $meta each loop, otherwise an empty iteration can pass on $meta to the next one

			// avoid undefined indexes by setting defaults here
			$default_field_args = array(
				'name' 					=> null,			// text displayed alongside field input
				'id' 					=> null,			// used when saving, should be the name of the post_meta (key) or taxonomy (exact slug) we're manipulating
				'type' 					=> null,			// field type (usually input: text, area, select, checkbox, radio), sometimes output (posts, other readonly data)
				'data' 					=> 'none',			// what kind of data is being retrieved and saved for this post (meta [wp_postmeta table], core [wp_posts table], taxonomy, p2p, attachment, comment). NONE indicates there is no saved data to be retrieved when displaying this field
				'options' 				=> array(),			// array of options to populate html form input objects (in this case, generated automatically from available taxonomy terms)
				'once' 					=> null,			// only gets written once, then turns read-only
				'multiple' 				=> false,			// can multiple values be selected? or must the saved value be singular?
				'required' 				=> false,			// can this field be left empty or unselected? enables red styling to draw attention (validation functions to check completion don't exist yet)
				'default' 				=> null,			// default value to show (in text fields or selectors)
				'desc' 					=> null, 			// description line shown below field contents
				'reveal-control' 		=> null,			// should be assigned only to a 'radio' or 'select' field type (and only one per page)
				'reveal-group' 			=> null,			// array of names corresponding with the possible values of the reveal-control selector. If the current value is in the array, that field is shown, otherwise hidden
				'allowed' 				=> array('jpg','jpeg','gif','png','mp3'),			// array of permitted file extensions for this instance
				'width'					=> null,			// used with upload-files field types. if indicated, image will be resized to max of this integer, but not cropped
				'height'				=> null,			// used with upload-files field types. if indicated, image will be resized to max of this integer, but not cropped
				'max'					=> null,			// how many items should be allowed to be uploaded
				'p2pname'				=> null,			// p2p connection ID
				'dir'					=> null,			// p2p direction
				'restrict' 				=> false,				// boolean for restricting display of this metabox for non-staff (a special somaticFramework permission class)
			);

			// merge with incoming field args
			$field = wp_parse_args( $field, $default_field_args );

			if ( $field['restrict'] && SOMA_STAFF == false ) {					// don't show certain fields for non-staff
				continue;
			}

			// preserve original field id, because we might mess with this later
			$field['id_orig'] = $field ['id'];

			// get current taxonomy data
			if ($field['data'] == 'taxonomy') {
				if (empty($field['options']) && $field['type'] != 'readonly') {
					continue;																		// if the options are empty and it's not readonly, then the taxonomy itself doesn't exist or it has no terms and rendering will fail, so skip this field
				}
				$terms = wp_get_object_terms($post->ID, $field['id']);								// retrieve terms
				if (is_wp_error($terms)) {
					wp_die($terms->get_error_message());
				}
				// if ($field['type'] == 'checkbox-multi' && is_null(soma_fetch_index($field, 'multiple'))) $field['multiple'] == true;		// default to multiple when using checkbox-multi, in case forgot to specify

				if ($field['type'] == 'readonly' && $field['multiple'] === false) {					// if readonly and singular, then $meta is just the name, no object
					$meta = $terms[0]->name;
				}
				if ($field['type'] == 'readonly' && $field['multiple']) {							// if readonly and multiple, then $meta is just a list, no objects
					$meta = somaFunctions::fetch_the_term_list( $post->ID, $field['id'],'',', ');	// returns linked html list of comma-separated terms
					if (!$meta) {
						continue;																	// if fetching terms comes up empty, then don't render this one
					}
				}
				if ($field['type'] != 'readonly') {													// not readonly but multiple values are accepted
					foreach ($terms as $term) {
						$meta[] = intval($term->term_id);
					}
				}
				if ($field['type'] == 'checkbox-single' || $field['type'] == 'toggle') {
					if (!empty($terms[0]->slug)) {													// check if theres a term in this taxonomy assigned at all, then set special $meta value to 1 (for the checked() function later)
						$meta = 1;
					}
				}
			}

			// get current post meta data
			if ($field['data'] == 'meta') {
				$meta = somaFunctions::asset_meta('get', $post->ID, $field['id']);
			}

			// toggle checkboxes either exist with value of 1 (or "on"), or they don't exist at all
			if (($field['type'] == 'checkbox-single' || $field['type'] == 'toggle') && $meta == 'on') {
				$meta = 1;
			}

			// if field is readonly and meta is empty, skip this line
			if ($field['data'] == 'meta' && $field['type'] == 'readonly' && !$meta) {
				// continue;
			}

			// get current post core database fields
			if ($field['data'] == 'core') {
				if ($field['id'] == 'post_author' && $field['type'] == 'readonly') {
					// show author linked name
					$meta = somaFunctions::fetch_post_author($post->ID, 'link');
				} else {
					$meta = $post->$field['id'];
				}
				// gotta make our own field ID that won't conflict with core ID's, which otherwise might get hijacked (confused for the actual core metabox data) during save
				$field['id'] = "core_".$field['id'];

			}

			// get all media attachments (minus featured image)
			if ($field['data'] == 'attachment') {
				$mime = soma_fetch_index($field, 'mime-type');						// filter specific mimetype if specified
				$meta = somaFunctions::fetch_attached_media($post->ID, $mime);
			}

			// don't show attachment gallery field at all if there aren't any yet
			if ($field['type'] == 'gallery' && $meta == null) {
				continue;
			}

			// get featured image
			if ($field['data'] == 'featured') {
				$meta = get_post_thumbnail_id( $post->ID );
				if (empty($meta)) $meta = null;
			}

			// get connected posts by types and direction
			if ($field['data'] == 'p2p') {
				switch (true) {
					case ($field['type'] == 'p2p-list') :																					// readonly output
						$meta = somaFunctions::fetch_connected_items( $post->ID, $field['p2pname'], $field['dir'], $output = 'html');		// list of links to related posts
						if (!$meta) continue;																								// if no connections exist, skip rendering this line
					break;
					case ($field['type'] == 'p2p-select') :																					// saveable
						$objs = somaFunctions::fetch_connected_items( $post->ID, $field['p2pname'], $field['dir'], $output = 'objects');
						if ($objs) {
							$obj = array_shift($objs);
							$meta = $obj->ID;																								// single post ID
						} else {
							$meta = false;																									// if no connections exist, keep false value for blank selection
						}
					break;
					case ($field['type'] == 'p2p-multi') :																					// saveable
						$objs = somaFunctions::fetch_connected_items( $post->ID, $field['p2pname'], $field['dir'], $output = 'objects');
						if ($objs) {
							foreach ($objs as $obj) {
								$meta[] = $obj->ID;																							// array of post ID's
							}
						} else {
							$meta = false;																									// if no connections exist, keep false value for blank selection
						}
					break;
					case ($field['type'] == 'p2p-objects') :																				// readonly output
						$meta = somaFunctions::fetch_connected_items( $post->ID, $field['p2pname'], $field['dir'], $output = 'objects');	// array of post objects
						if (!$meta) continue;																								// if no connections exist, skip rendering this line
					break;
				}
			}

			if ($field['type'] == 'link') {

			}

			// hook additional if statements to retrieve custom metadata
			// must return $meta unmodified if $field['data'] doesn't match!
			$meta = apply_filters('soma_field_fetch_meta', $meta, $post, $field);

			// don't render field at all if there's no metadata and field isn't required -- DO WE WANT THIS?
			if (!$meta && !$field['required']) {
				// continue;
			}

			// build a table row for each field ---------------------------------------------------------------------------------------------/


			// if $meta's not empty, then we're good - otherwise, mark as not complete to add the missing css code
			if (!$meta && $field['required']) {
				$complete = false;
			} else {
				$complete = true;
			}

			// define css class injection when missing $meta
			$missing = ' missing';

			// if there's a default option, or it's not required, or this is a UI element, don't add the "missing" class
			if ($field['default'] != '' || $field['required'] != true || $field['data'] == 'none') {
				$complete = true;
			}

			// for status taxonomies, indicate as "missing", even though $meta isn't empty, because a status of "incomplete" is the same as missing metadata
			if ($field['data'] == 'taxonomy') {
				$term = get_term($meta, $field['id']);
				if ( !is_wp_error( $term ) && !is_null( $term )) {
					if ( $term->slug == 'incomplete' || $term->slug == 'processing' ) {
						$complete = false;
					}
				}
			}

			// don't need to add emphasis class for readonly items that user can't input
			if ($field['type'] == 'readonly' || $field['type'] == 'posts' || $field['data'] == 'comment' || $field['type'] == 'help') {
				$complete = true;
			}

			// this metadata already exists and is not allowed to be changed once set, so change to readonly (or don't and just disable the select input)
			if ($meta && $field['once']) {
				$disable = true;
				$field['type'] = 'readonly';
				$field['desc'] = '';
			}

			// field row CSS class assignments
			$rowclass = "fieldrow";
			$group = soma_fetch_index($field, 'reveal-group');
			$reveal = false;				// reset each pass
			$initshow = true;				// reset each pass

			if (is_array($group)) {
				$reveal = true;
				$rowclass .= " reveal-row";
				$revealdata = " data-reveal-group='" . json_encode($group) . "'";
			}

			// build the field row
			echo '<tr id="', $field['id'] , '-row" class="', $rowclass, '"', $reveal ? $revealdata : null,' ', $initshow ? null : 'style="display:none;" ' ,'>';

			// include column for field name if included
			if ($field['name'] || $field['type'] == 'button' || $field['type'] == 'help') {
				echo '<td class="field-label"><label for="', $field['id'], '" class="', $complete ? null : $missing, '" >', $field['name'], '</label></td>';
				echo '<td class="field-data">';
			// no name given, so span both columns
			} else {
				echo '<td colspan="2">';
			}

			// keep it true to execute the code at the end that displays the "desc" row (allows us to bypass within these cases)
			$dodesc = true;

			// last chance to manipulate the data itself
			$meta = apply_filters( "soma_field_data_" . $field['id_orig'], $meta, $post->ID, $box['id'], $field['id'], $field['type'], $field['data'], $field['options'] );

			// build each field content by type
			switch ($field['type']) {
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'text':
				case 'oembed':
					echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['default'], '" class="meta-text', $complete ? null : $missing, '" />';
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'link':
					echo '<span class="sub-label">Title</span><input type="text" name="', $field['id'], '[]" id="', $field['id'], '" value="', $meta['title'], '" class="meta-link', $complete ? null : $missing, '" /><br />';
					echo '<span class="sub-label">URL</span><input type="text" name="', $field['id'], '[]" id="', $field['id'], '" value="', $meta['url'], '" class="meta-link', $complete ? null : $missing, '" />';
					if (!empty($meta['url']) && !empty($meta['title'])) {
						echo '<br /><span class="sub-label">Preview:</span><div class="meta-link"><a href="',$meta['url'],'" target="_blank">',$meta['title'],'</a></div>';
					}
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'help':
					echo '<p class="help">', $field['desc'], '</p>';
					$dodesc = false;
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'numeric':
					echo '<input type="text" alt="numeric" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['default'], '" class="meta-numeric', $complete ? null : $missing, '" />';
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'textarea':
					if (empty($field['rows'])) {
						$field['rows'] = 5;
					}
					echo '<textarea name="', $field['id'], '" id="', $field['id'], '" cols="60" rows="',$field['rows'],'" class="meta-textarea', $complete ? null : $missing, '" >', $meta ? $meta : $field['default'], '</textarea>';
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				// custom richtext/HTML editor  http://codex.wordpress.org/Function_Reference/wp_editor
				case 'richtext':
					$args = array();
					if (!empty($field['rows'])) {
						$args['textarea_rows'] = intval($field['rows']);				// override system defaults for visual editor rows
					}
					if (soma_fetch_index($field, 'hide_buttons')) {
						$args['media_buttons'] = false;									// hides the media upload buttons
					}
					$args['wpautop'] = false;
					$sanitizedID = somaFunctions::sanitize_wpeditor_id($field['id']);	// This is BIZARRE, but the ID that is passed to the wp_editor() function can only be comprised of lower-case letters. No underscores, no hyphens. Anything else will cause the WYSIWYG editor to malfunction. So we create a sanitized version to be given to wp_editor()
					wp_editor( $meta, $sanitizedID, $args );
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				// HTML only editor
				case 'html':
					$args = array();
					if (!empty($field['rows'])) {
						$args['textarea_rows'] = intval($field['rows']);				// override system defaults for visual editor rows
					}
					$args['wpautop'] = false;
					$args['tinymce'] = false;											// disable visual editor tab
					$args['media_buttons'] = false;										// hide media upload buttons
					$sanitizedID = somaFunctions::sanitize_wpeditor_id($field['id']);	// This is BIZARRE, but the ID that is passed to the wp_editor() function can only be comprised of lower-case letters. No underscores, no hyphens. Anything else will cause the WYSIWYG editor to malfunction. So we create a sanitized version to be given to wp_editor()
					wp_editor( $meta, $sanitizedID, $args );
				break;

				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'upload-files' :
				case 'upload-images' :
					$ft = false;
					if ($field['data'] == 'featured') $ft = true;
					if ( $ft && $meta ) {										// if this is a featured image uploader, but we already have one, show it (in this case, $meta simply contains the ID of the featured attachment)
						echo '<ul class="featured-image">';
						echo '<li class="meta-attachment-item">';
						echo '<a href="'.wp_get_attachment_url($meta).'" class="colorbox">'. wp_get_attachment_image($meta, 'medium', false, array('title'=>'Click to Zoom', 'class' => 'pic')) . '</a>';
						echo '<ul class="meta-attachment-actions">';
						echo '<li><a class="delete-attachment" href="#" rel="'.$meta.'" title="Delete this file" data-nonce="'.wp_create_nonce("soma-delete-attachment").'" data-featured="true">Remove Image</a><img src="'.admin_url('images/wpspin_light.gif').'" class="kill-animation" style="display:none;" alt="" /></li>';
						echo '</ul></li></ul>';
					}
					$uploader = new somaUploadField($field, $ft, $meta);
					$uploader->print_scripts();
					$uploader->html();
					$dodesc = false;
				break;

				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'gallery':
					// really need to incorporate dragdrop sorting here.... also ability to edit title/caption
					echo '<ul class="meta-attachment-gallery">';
					foreach ($meta as $att) :
						$i = 0;
						echo '<li class="meta-attachment-item">';
							$file = soma_fetch_file( $att->ID );
							switch ($att->post_mime_type) {
								case "application/pdf" :
									echo '<a class="filetype-icon" href="http://docs.google.com/viewer?url='.urlencode($file['url']).'&embedded=true" class="colorbox" rel="gallery-'.$field['id'].' iframe="true"><img src="'. $file['icon'] .'" title="Click to view PDF file with Google Docs" /></a>';
								break;
								case "application/msword" :
								case "application/vnd.ms-word" :
								case "application/vnd.openxmlformats-officedocument.wordprocessingml.document" :
									echo '<a class="filetype-icon" href="http://docs.google.com/viewer?url='.urlencode($file['url']).'&embedded=true" class="colorbox" rel="gallery-'.$field['id'].' iframe="true"><img src="'. $file['icon'] .'" title="Click to view Microsoft Word Doc with Google Docs" /></a>';
								break;
								case "application/msexcel" :
								case "application/vnd.ms-excel" :
								case "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" :
									echo '<a class="filetype-icon" href="http://docs.google.com/viewer?url='.urlencode($file['url']).'&embedded=true" class="colorbox" rel="gallery-'.$field['id'].' iframe="true"><img src="'. $file['icon'] .'" title="Click to view Microsoft Excel Spreadsheet with Google Docs" /></a>';
								break;
								case "application/mspowerpoint" :
								case "application/vnd.ms-powerpoint" :
								case "application/vnd.openxmlformats-officedocument.presentationml.presentation" :
									echo '<a class="filetype-icon" href="http://docs.google.com/viewer?url='.urlencode($file['url']).'&embedded=true" class="colorbox" rel="gallery-'.$field['id'].' iframe="true"><img src="'. $file['icon'] .'" title="Click to view PowerPoint Presentation with Google Docs" /></a>';
								break;
								case "application/zip" :
									echo '<a class="filetype-icon" href="'.$file['url'].'" target="blank"><img src="'. $file['icon'] .'" /></a><br>';
								break;
								case "image/jpeg" :
								case "image/jpg" :
								case "image/png" :
									echo "<div class='imageviewer'>";
									$img = soma_fetch_image($att->ID);
									echo '<a href="'.$file_url.'" class="colorbox" rel="gallery-'.$field['id'].'" title="'.$img['title'].'">';
									echo "<img src=\"{$img['thumbnail']['url']}\" title=\"{$img['title']}\" class=\"pic\" />";
									// echo wp_get_attachment_image($att->ID, 'thumbnail', false, array('title'=>'Click to Zoom', 'class' => 'pic'));
									echo '</a>';
									echo "</div>";
									$showmetafields = true;
								break;
								case 'audio/mpeg':
								case 'audio/wav':
								case 'audio/x-wave':
									echo "<div class='audioplayer'>";
									echo "<input type='text' value='{$att->post_title}' /><br />";
									echo do_shortcode('[audio src="'.$file['url'].'" id="'.$att->ID.'" ]');
									echo "</div>";
								break;
								case 'video/mp4':
									echo "<div class='videoplayer'>";
									echo "<input type='text' value='{$att->post_title}' /><br />";
									echo do_shortcode('[video src="'.$file['url'].'"]');
									echo "</div>";
								break;
								default :
									echo "<div class=\"filetype-icon\"><a class=\"download-attachment\" href=\"{$file['secure']}\" title=\"Download {$file['basename']}\"><img src=\"{$file['icon']}\" /></a></div><br>";
								break;
							}
						echo '<ul class="meta-attachment-meta">';
						echo "<li class='filename'>{$file['filename']}</li>";
						echo "<li class='uptime'>Uploaded ".human_time_diff($file['time'])." ago</li>";
						echo '</ul>';
						// collapsed meta fields
						if ($showmetafields == true) {
							echo '<ul class="meta-attachment-text">';
								echo "<input type='hidden' name='{$field['id']}[id][]' value='{$att->ID}' >";
								echo "<li class='att-text'><a href='#'>Title</a>";
								echo "<input type='text' name='{$field['id']}[title][]' id='attachmenttext[$i][title]' value='{$img['title']}' /></li>";
								echo "<li class='att-text'><a href='#'>Caption</a>";
								echo "<textarea rows='3' name='{$field['id']}[caption][]' id='attachmenttext[$i][caption]' >{$img['caption']}</textarea></li>";
								echo "<li class='att-text'><a href='#'>Description</a>";
								echo "<textarea rows='3' name='{$field['id']}[description][]' id='attachmenttext[$i][description]' >{$img['description']}</textarea></li>";
							echo "</ul>";
						}
						// action buttons
						echo '<ul class="meta-attachment-actions">';
							echo "<li><a class=\"download-attachment\" href=\"".$file['secure']."\" title=\"Download ".$file['basename']."\">Download File</a></li>";
							echo '<li><a class="delete-attachment" href="#" rel="'.$att->ID.'" title="Delete '.$file['basename'].'" data-nonce="'.wp_create_nonce("soma-delete-attachment").'">Delete File</a><img src="'.admin_url('images/wpspin_light.gif').'" class="kill-animation" style="display:none;" alt="" /></li>';
						echo '</ul>';
						echo '</li>';
						$i++;
					endforeach;
					echo '</ul>';
				break;

				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'select':
					$selectclass = "meta-select";
					if (!$complete) $selectclass .= $missing;
					if (soma_fetch_index($field, 'reveal-control')) {
						$selectclass .= " reveal-control";
					}
					echo '<select name="', $field['id'], '" id="', $field['id'], '"', $disable ? ' disabled="disabled"' : null, ' class="', $selectclass, '" data-taxonomy="', $field['id'],'" >';
					if (is_array($meta)) $meta = array_shift($meta);		// existing data might be an array, especially if taxonomy. But since this is a single-value selector, extract just the one value
					$opts = soma_fetch_index($field, 'options');
					if (!is_array($opts)) {
						echo "<em>to use a dropdown select box, you must supply an array of options (though that array can contain just one item)</em>";
						break;
					}

					// if meta is empty (never been set), and there's no default specified, display a 'none' option -- UNLESS this is post_parent, which could legitimately be set to 0
					if (empty($meta) && empty($field['default']) && $field['id'] != 'post_parent' && $field['data'] != 'core') {
						echo '<option value="" selected="selected">[Select One]</option>';
					}
					// list values and match
					foreach ($opts as $option) {
						// if meta matches, or if there's no meta, but there is a default string specified that matches
						if ($meta == $option['value'] || (empty($meta) && $field['default'] == $option['name'])) {
							$select = true;
						} else {
							$select = false;
						}
						echo '<option value="', $option['value'], '"' , selected($select), '>', $option['name'],'</option>';
					}
					echo '</select>';
					echo '<input type="text" name="', $field['id'], '-create" id="', $field['id'], '-create" value="" class="meta-select-input" style="display:none;" disabled="disabled" />';
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'radio':
				case 'radio-horizontal':
					$radioclass = "meta-radio";
					if (!$complete) $radioclass .= $missing;
					if ($field['type'] == 'radio-horizontal') $radioclass .= " radio-horizontal";
					if (soma_fetch_index($field, 'reveal-control')) $radioclass .= " reveal-control";

					echo "<ul class='$radioclass' >";
					if (is_array($meta)) $meta = array_shift($meta);		// existing data might be an array, especially if taxonomy. But since this is a single-value selector, extract just the one value
					$opts = soma_fetch_index($field, 'options');
					if (!is_array($opts)) {
						echo "<em>to use radio buttons, you must supply an array of options (two items minimum)</em>";
						break;
					}
					foreach ($opts as $option) {
						// if meta matches, or if the default string matches -- if meta is empty (never been set), and there's no default specified, no radio item will be checked
						if ($meta == $option['value'] || (empty($meta) && $field['default'] == $option['name'])) {
							$select = true;
						} else {
							$select = false;
						}
						echo '<li><label><input type="radio" name="', $field['id'], '" value="', $option['value'], '"' , checked($select), ' />', $select ? "<strong>" : null , $option['name'], $select ? "</strong>" : null ,'</label></li>';
					}
					echo '</ul>';
					break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'checkbox-single':		// legacy support
				case 'toggle':
					if ($field['data'] != 'meta') {
						echo "<em>please only use meta data for single checkbox fields. use checkbox fields for taxonomy data.</em>";
						break;
					}
					echo '<input type="checkbox" name="', $field['id'], '" id="', $field['id'], '"' , checked($meta, 1) , ' class="meta-toggle ', $complete ? null : $missing, '" />';
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'checkbox-multi':		// legacy support
				case 'checkbox':
					echo '<ul class="meta-checkbox-multi', $complete ? null : $missing, '" >';
					$opts = soma_fetch_index($field, 'options');
					if (!is_array($opts)) {
						echo "<em>to use checkboxes, you must supply an array of options (though that array can contain just one item)</em>";
						break;
					}
					foreach ($opts as $option) {
						$val = soma_fetch_index($option, 'value');
						if ( is_array($meta) && in_array($val, $meta) ) {
							echo '<li><label><input type="checkbox" value="', $option['value'],'" name="', $field['id'], '[]" id="check-', $option['value'],'" checked="checked" /><strong>',$option['name'],'</strong></label></li>';
						} else {
							echo '<li><label><input type="checkbox" value="', $option['value'],'" name="', $field['id'], '[]" id="check-', $option['value'], '" />',$option['name'],'</label></li>';
						}
					}
					echo '</ul>';
					break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'select-multi':
					echo '<select multiple="multiple" name="', $field['id'], '[]" id="', $field['id'], '" class="meta-select-multi', $complete ? null : $missing, '" >'; // adding brackets to end of select name pass to $_POST as array of selections
					// list values and match
					if (is_array($field['options'])) {		// must check if array exists or second test will throw error
						foreach ($field['options'] as $option) {
							if (!empty($meta) && soma_fetch_index($option['value'], $meta) ) {	// $meta isn't empty, and this option is within it
								echo '<option value="', $option['value'], '" selected="selected">', $option['name'],'</option>';
							} else {
								echo '<option value="', $option['value'], '">', $option['name'],'</option>';
							}
						}
					}
					echo '</select>';
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				// (dropdown select inputs)
				case 'date':

					// Day Selector
					if ($meta) { $day = date("j", strtotime($meta)); }
					else { $day= 0; }

					echo '<select name="'.$field['id'].'_day" id="'.$field['id'].'_day" class="', $complete ? null : $missing, '" >';
					echo '<option value="">Day</option>';
					for ( $currentday = 1; $currentday <= 31; $currentday += 1) {
					  echo '<option value="'.$currentday.'"';
					  if ($currentday == $day) { echo ' selected="selected" '; }
					  echo '>'.$currentday.'</option>';
					}
					echo "</select>";

					// Month Selector
					if ($meta) { $month = date('n', strtotime($meta)); }
					else { $month = 0; }

					$monthname = array(1=> "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");

					echo '<select name="'.$field['id'].'_month" id="'.$field['id'].'_month" class="', $complete ? null : $missing, '" >';
					echo '<option value="">Month</option>';
					for($currentmonth = 1; $currentmonth <= 12; $currentmonth++) {
						echo '<option value="';
						echo intval($currentmonth);
						echo '"';
						if ($currentmonth == $month) { echo ' selected="selected" '; }
						echo '>'.$monthname[$currentmonth].'</option>';
					}
					echo '</select>';

					// Year	Selector

					if ($meta) { $year = date('Y', strtotime($meta)); }
					else { $year=0; }
					echo '<select name="'.$field['id'].'_year" id="'.$field['id'].'_year" class="', $complete ? null : $missing, '" >';
					echo '<option value="">Year</option>';
					for ( $firstyear = "1970"; $firstyear <= date(Y)+10; $firstyear +=1 ) {
						echo "<option value=\"$firstyear\"";
						if ($firstyear == $year) { echo ' selected="selected" '; }
						echo ">$firstyear</option>";
					}
					echo "</select>";
				break;
				//  ---------------------------------------------------------------------------------------------------------- //
				// (jqueryUI inputs)
				case 'datepicker':
					if ($meta) {
						$human = date("F d, Y", strtotime($meta));	// human readable output
					} else {
						$human = "(none selected)"; 		// default value for altField
					}
					// hidden input that actually holds the data from the jqueryUI picker -- allows us to display a human-readable version in the mirror field
					echo '<input type="hidden" class="datepicker" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['default'], '" >';
					echo '<input type="text" class="datemirror', $complete ? null : $missing, '" readonly="readonly" value="', $human, '"/>';
					echo '<div class="datereset"></div>';
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				// (jqueryUI inputs)
				case 'timepicker':
					if ($meta) {
						$human = date("g:i A",strtotime($meta));
					} else {
						$human = "(none selected)"; // default value for altField
					}
					// hidden input that actually holds the data from the jqueryUI picker -- allows us to display a human-readable version in the mirror field
					echo '<input type="hidden" class="timepicker" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $human : $field['default'], '" >';
					echo '<input type="text" class="timemirror', $complete ? null : $missing, '" readonly="readonly" value="', $human, '"/>';
					echo '<div class="timereset"></div>';
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				// (dropdown select inputs)
				case 'time':
					// var_dump(date("h:i A",$meta));	// debugging time output
					// Hour Selector
					if ($meta) { $hour = intval(date("h", $meta)); }
					else { $hour = 0; }

					echo '<select name="'.$field['id'].'_hour" id="'.$field['id'].'_hour" class="', $complete ? null : $missing, '" >';
					echo '<option value="">Hour</option>';
					for ( $currenthour = 1; $currenthour <= 12; $currenthour += 1) {
						echo '<option value="'.$currenthour.'"';
						if ($currenthour === $hour) {
							echo ' selected="selected" ';
						}
						echo '>'.$currenthour.'</option>';
					}
					echo "</select>";

					echo " : ";

					// Minute Selector
					if ($meta) { $minute = intval(date("i", $meta)); }
					else { $minute = null; }

					echo '<select name="'.$field['id'].'_minute" id="'.$field['id'].'_minute" class="', $complete ? null : $missing, '" >';
					echo '<option value="">Mins</option>';
					for ( $currentminute = 0; $currentminute <= 59; $currentminute += 1) {
						echo '<option value="'.$currentminute.'"';
						if ($currentminute === $minute) {
							echo ' selected="selected" ';
						}
						echo '>'.sprintf("%02d", $currentminute).'</option>';
					}
					echo "</select>";

					// AM/PM Selector
					if ($meta) { $meridiem = date("A", $meta); }
					else { $meridiem = 0; }

					echo '<select name="'.$field['id'].'_meridiem" id="'.$field['id'].'_meridiem" class="', $complete ? null : $missing, '" >';

					echo '<option value="AM"';
					if ($meridiem == "AM") {
						echo ' selected="selected" ';
					}
					echo '>AM</option>';

					echo '<option value="PM"';
					if ($meridiem == "PM") {
						echo ' selected="selected" ';
					}
					echo '>PM</option>';

					echo "</select>";
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'weight':
					// Pounds Selector
					if ($meta) { $lbs = floor($meta / 16); }	// weight meta is stored in total ounces, so divide by 16 for pounds
					else { $lbs = 0; }

					echo '<select name="'.$field['id'].'_lbs" id="'.$field['id'].'_lbs" class="', $complete ? null : $missing, '" >';
					echo '<option value="">Pounds</option>';
					for ( $currentlbs = 1; $currentlbs <= 20; $currentlbs += 1) {
						echo '<option value="'.$currentlbs.'"';
						if ($currentlbs == $lbs) {
							echo ' selected="selected" ';
						}
						echo '>'.$currentlbs.'</option>';
					}
					echo "</select>";
					echo " lbs. ";

					// Ounce Selector
					if ($meta) { $oz = $meta % 16; }	// use modulus operator to get remainder of the 16oz division
					else { $oz = null; }

					echo '<select name="'.$field['id'].'_oz" id="'.$field['id'].'_oz" class="', $complete ? null : $missing, '" >';
					echo '<option value="">Ounces</option>';
					for ( $currentoz = 0; $currentoz <= 15; $currentoz += 1) {
						echo '<option value="'.$currentoz.'"';
						if ($currentoz === $oz) {
							echo ' selected="selected" ';
						}
						echo '>'.$currentoz.'</option>';
					}
					echo "</select>";
					echo " oz. ";

				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'readonly':
					if (is_array($meta)) {
						echo implode(", ", $meta);
					} else {
						// echo '<strong>', $meta ? $meta : $field['default'], '</strong>';
						echo $meta ? apply_filters('the_content', $meta) : $field['default'];
					}
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				// displaying related posts as mini thumbs
				case 'p2p-objects':
					if ($meta) {
						echo somaFunctions::mini_asset_output($meta); // THIS IS NOT GENERIC ENOUGH FOR THE SOMA FRAMEWORK..... NEED A NEW MINI-POST CONTENT
						echo '<div style="clear:left"></div><p class="howto">',$field['desc'],'</p>';
					} else { // if no relations
						echo '<em>(none)</em>';
					}
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				// displaying related posts as link list
				case 'p2p-list':
					if (!empty($meta)) {
						echo '<strong>', $meta ? $meta : $field['default'], '</strong>';
						echo $desc;
					} else { // if no relations
						echo '<em>(none)</em>';
					}
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				// select a single post relationship
				case 'p2p-select':
					echo '<select name="', $field['id'], '" id="', $field['id'], '"', $disable ? ' disabled="disabled"' : null, ' class="meta-select', $complete ? null : $missing, '" >';
					if (is_array($field['options'])) {		// must check if array exists or foreach will throw error
						// if meta is empty (never been set), and there's no default specified, display a 'none' option
						if (empty($meta) && empty($field['default'])) {
							echo '<option value="" selected="selected">[Select One]</option>';
						}
						// list values and match
						foreach ($field['options'] as $option) {
							// if meta matches, or if there's no meta, but there is a default string specified that matches
							if ($meta == $option['value'] || (!$meta && $field['default'] == $option['name'])) {
								$select = true;
							} else {
								$select = false;
							}
							echo '<option value="', $option['value'], '"', $select ? ' selected="selected"' : null, '>', $option['name'],'</option>';
						}
					} else {
						// if meta isn't array, default to single behaviour
						echo '<option value="', $option['value'], '"', $meta == $option['value'] ? ' selected="selected"' : '', '>', $option['name'],'</option>';
					}
					echo '</select>';
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				// selection from list of possible post relationships
				case 'p2p-multi':
					echo '<ul class="meta-checkbox-multi', $complete ? null : $missing, '" >';
					// echo '<span ', $complete ? null : $missing, '>';
					if (is_array($field['options'])) {		// must check if array exists or foreach will throw error
						foreach ($field['options'] as $option) {
							if (!empty($meta) && soma_fetch_index($option['value'] , $meta) ) {	// $meta isn't empty, and this option is within it
								echo '<li><label><input type="checkbox" value="', $option['value'],'" name="', $field['id'], '[]" id="check-', $option['value'],'" checked="checked" /><strong>',$option['name'],'</strong></label></li>';
							} else {
								echo '<li><label><input type="checkbox" value="', $option['value'],'" name="', $field['id'], '[]" id="check-', $option['value'], '" />',$option['name'],'</label></li>';
							}
						}
					} else {
						// if meta isn't array, default to checkbox-single behaviour
						echo '<input type="checkbox" name="', $field['id'], '" id="', $field['id'], '"', checked($meta, 1) , ' class="', $complete ? null : $missing, '" />';
					}
					echo '</ul>';
					break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				// external media (youtube, vimeo, soundcloud)
				case 'external_media':
					echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['default'], '" class="meta-text', $complete ? null : $missing, '" />';
					echo $field['desc'] ? "<div class=\"field-desc\">". $field['desc'] : null, '</div>';

					$existing = soma_asset_meta('get', $post->ID, $field['id']."_attached");			// did we already save an attachment for this field?
					if (empty($existing)) {
						$import = 'checked="checked"';														// we haven't, so check import by default
					}
					if (function_exists('get_post_thumbnail_id')) {
						$thumb = get_post_thumbnail_id( $post->ID );											// grab featured image
					}
					if ( empty($thumb) ) {																	// if featured hasn't been set, check featured by default
						$featured = 'checked="checked"';
					}
					// output
					echo '<div class="field-option"><input type="checkbox" name="import-ext-image" id="import-ext-image" class="meta-toggle" '.$import.'/><label for="import-ext-image">Import the external media thumbnail image as an attachment</label></div>';
					echo '<div class="field-option"><input type="checkbox" name="use-ext-feature" id="use-ext-feature" class="meta-toggle" '.$featured.'/><label for="use-ext-feature">Use the imported image as the Featured Image for this asset</label></div>';
					echo '<div class="field-option"><input type="checkbox" name="copy-ext-meta" id="copy-ext-meta" class="meta-toggle"/><label for="copy-ext-meta">Use the Title and Description from the external media source</label></div>';
					echo '</td></tr>';
					$dodesc = false;
					if ($meta) {
						$ext = soma_asset_meta('get', $post->ID, $field['id'].'_ext');	// grab external media API response we've already saved
						echo '<tr><td class="field-label">Site Link</td><td class="field-data"><a href="'.$ext["url"].'" target="_blank">'.ucwords($ext["site"]).'</a></td></tr>';
						echo '<tr><td class="field-label">ID</td><td class="field-data">'.$ext["id"].'</td></tr>';
						echo '<tr><td class="field-label">Source Title</td><td class="field-data">'.$ext["title"].'</td></tr>';
						echo '<tr><td class="field-label">Source Desc</td><td class="field-data">'.$ext["desc"].'</td></tr>';

						if ($ext['site'] == 'youtube' || $ext['site'] == 'vimeo') {
							echo '<tr><td class="field-label">Source Image</td><td class="field-data"><a class="trigger colorbox" href="'.$ext['iframe'].'" iframe="true" innerWidth="853px" innerHeight="480px"></a><img src="'.$ext['thumb'].'"></td></tr>';
						}
						if ($ext['site'] == 'soundcloud') {
							echo '<tr><td class="field-label">Source Image</td><td class="field-data" style="background-color:#000;"><a class="trigger colorbox" href="'.$ext['iframe'].'" iframe="true" width="85%" innerHeight="166px"><img src="'.$ext['thumb'].'" style="max-width:100%;"></a></td></tr>';
							// echo '<tr><td class="field-label">Source Embed</td><td class="field-data">'.$ext['embed'].'</td></tr>';
						}
						// echo "<li>{$ext['embed']}</li>";
					}
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				// external image (direct url)
				case 'external_image':
					echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['default'], '" class="meta-text', $complete ? null : $missing, '" />';
					echo $field['desc'] ? "<div class=\"field-desc\">". $field['desc'] : null, '</div>';

					if ( !has_post_thumbnail( $post->ID ) ) {
						$thumb = 'checked="checked"';	// select these by default if there isn't already a featured image
					} else {
						$img = soma_featured_image($post->ID, 'thumb');
					}
					echo '<div class="field-option"><input type="checkbox" name="import-ext-image" id="import-ext-image" class="meta-toggle" '.$thumb.'/><label for="import-ext-image">Import the external image as an attachment</label></div>';
					echo '<div class="field-option"><input type="checkbox" name="use-ext-feature" id="use-ext-feature" class="meta-toggle" '.$thumb.'/><label for="use-ext-feature">Use the imported image as the Featured Image for this asset</label></div>';
					echo '</td></tr>';
					$dodesc = false;
					if ($meta) {
						echo '<tr><td class="field-label">Source Link</td><td class="field-data"><a href="'.$meta.'" target="_blank">'.$meta.'</a></td></tr>';
						echo '<tr><td class="field-label">Source Image</td><td class="field-data"><a class="colorbox" href="'.$meta.'" iframe="true"></a><img src="'.$meta.'"></td></tr>';
						echo $img ? '<tr><td class="field-label">Imported Thumbnail</td><td class="field-data"><img src="'.$meta.'"></td></tr>': null;
					}
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'media':

				// must enqueue these core wp scripts for thickbox uploader to appear
				wp_enqueue_script('media-upload');
				wp_enqueue_script('thickbox');
				wp_enqueue_style('thickbox');
				// upload/modify media file button
				$label = $meta ? "Modify Media File" : "Upload Media File";
				echo "<a href=\"media-upload.php?post_id=$post->ID&amp;TB_iframe=1&amp;height=800&amp;width=640\" id=\"add_media\" class=\"thickbox clicker\" onclick=\"return false;\">$label</a>";

				// don't attempt to show player if no media exist yet
				if (!empty($meta)) {
					// cycle through array of attached media objects
					foreach ($meta as $media) {
						$url = wp_get_attachment_url( $media->ID);
						$mime = $media->post_mime_type;
						switch ($mime) {
							case 'audio/mpeg':
								$type = 'audio';
							break;
							case 'video/mp4':
								$type = 'video';
							break;
							default:
								$type = 'other';
							break;
						}
						// output html5 tags (handled by mediaelement.js - separate plugin)
						if ( $type == 'video') {
							echo '<tr><td></td><td class="field-data">';
							echo do_shortcode('[video src="'.$url.'"]');
							echo '</td></tr>';
						}
						if ( $type == 'audio') {
							echo '<tr><td></td><td class="field-data">';
							echo do_shortcode('[audio src="'.$url.'"]');
							echo '</td></tr>';
						}

						echo $field['type'] ? "" : "<em>Must specify a format first!</em>";		/// what is this for?
					}
				}
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'button':
					if ($field['data'] == ( 'save' || 'save-back' ) ) {
						if ($meta_box['always-publish'] || $meta_box['publish']) {
							echo '<input type="hidden" name="post_status" id="post_status" value="publish" />';
						}
						if ($field['data'] == 'save-back') {
							echo '<input type="hidden" name="save_and_go_back" value="1">';										// additional var to redirect back to referring page, detected by redirect_post_location() function
						}
						submit_button( $field['options']['label'], 'clicker', 'save', false, array( 'id' => $field["id"]));		// outputs generic submit button
					}
					if ($field['data'] == 'link') {
						echo "<a href='{$field['options']['url']}' class='clicker'>{$field['options']['label']}</a>";
					}
				break;
			}

			// hook to output case-specific metabox types not specified above
			do_action('soma_field_type_case', $post, $meta, $field, $complete);

			// output td for field description and close tags
			if ($dodesc) {
				echo $field['desc'] ? "<div class='field-desc'>{$field['desc']}</div>" : null;
				// echo $field['desc'] ? "</td></tr>\n<tr class=\"desc-row\">\n<td class=\"field-label\"></td>\n<td class=\"field-desc\">". $field['desc'] : null;
				echo '</td></tr>';
			}
		}

		//---------------------------------------------
		//** END FIELD LOOP

		echo '</table>';
		// hook for insertion after box content
		do_action('after_'.$box['id'], $post);

		// insert save changes button
		if ($meta_box['save']) {
			echo '<table class="form-table"><tr><td class="field-label"></td><td class="field-data">';

			if ($meta_box['always-publish'] || $meta_box['publish']) {
				echo '<input type="hidden" name="post_status" id="post_status" value="publish" />';
			}

			// outputs generic submit button
			submit_button( 'Save Changes', 'clicker', 'save', false, array( 'id' => $field["id"]));

			// // core save draft button
			// echo '<input type="submit" name="save" id="save-post" value="Save Draft" class="button button-highlighted" />';
			// echo '<img src="'.esc_url( admin_url( 'images/wpspin_light.gif' ) ).'" class="ajax-loading" id="ajax-loading" alt="" />';
			// // core publish button
			// echo '<input name="original_publish" type="hidden" id="original_publish" value="Publish" />';
			// echo '<input type="submit" name="publish" id="publish" class="button-primary" value="Publish" tabindex="5" accesskey="p"  />';
			echo "</td></tr></table>";
		}
	}

	///////////// FUNCTIONS FOR GENERATING METABOX OPTION ARRAYS FOR USE IN HTML INPUT OBJECTS //////////////////


	// outputs this: array(array('name'=>'1','value'=>'1'),array('name'=>'2','value'=>'2')) etc
	function number_selector_generator($max, $date = false, $zero = false) {
		$numbers = array();
		$zero ? $i = 0 : $i = 1;

		// add whole integers
		while ($i <= $max) {

			if ($date) {
				$name = $i.' years';
			} else {
				$name = $i;
			}
			$numbers[] = array('name'=>$name,'value'=>$i);
			$i++;
		}
		return $numbers;
	}

	// retrieve and output list of terms for a given taxonomy
	function select_taxonomy_terms($tax, $create = false) {
		if (taxonomy_exists($tax)) {
			$terms = get_terms($tax,'hide_empty=0');
			if (!empty($terms)) {
				if ($create) {
					$list[] = array('name' => '-- Create New --', 'value' => 'create');
				}
				foreach ($terms as $term) {
					$list[] = array('name' => $term->name, 'value' => intval($term->term_id));
				}
				return $list;
			}
			return $terms;	// taxonomy has no terms, return empty
		}
		$terms = array(); 	// taxonomy doesn't exist, return empty
		return $terms;
	}


	// retrieve list of users, output array for dropdown selector
	function select_users($roles) {
		$users = self::get_role_members($roles);
		if (empty($users)) {
			return array(array('name' => '[none available]', 'value' => '0'));	// no user role matches, output empty list
		}
		foreach ($users as $user) {
			$list[] = array('name' => $user->display_name, 'value' => intval($user->ID));
		}
		return $list;
	}

	// retrieves array of user objects for a given role name
	function get_role_members($roles = null) {
		if (!$roles) return false;
		// convert to array if single
		if (!is_array($roles)) {
			$roles = array($roles);
		}
		// init output array
		$members = array();
		global $wp_roles;
		foreach ($roles as $role) {
			if (!array_key_exists($role, $wp_roles->roles)) continue; 	// abort if given a non-existant role (otherwise the user_query will return everyone)
			$wp_user_search = new WP_User_Query( array( 'role' => $role) );
			// wp_die(var_dump($wp_user_search));
			$users = $wp_user_search->get_results();
			if (empty($users)) continue;
			foreach ($users as $user) {
				$members[] = $user;
			}
		}
		return $members;
	}

	// retrieve list of post types, output array for dropdown selector
	function select_types() {
		$types = get_post_types( array( 'exclude_from_search' => false, '_builtin' => false  ), 'objects' );
		foreach ($types as $type) {
			$list[] = array('name' => $type->label, 'value' => $type->query_var);
		}
		return $list;
	}

	// retrieve list of posts, can narrow by author
	function select_items( $type = null, $author_id = null ) {
		if (!$type) return null;
		$items = array();
		$args = array(
			'suppress_filters' => false,
			'post_type' => $type,
			'post_status' => 'any',
			'posts_per_page' => -1
		);
		if ($author_id != null) $args['post_author'] = $author_id;
		$items = get_posts( $args );
		if ( empty( $items ) ) {
			return array(array('name' => '[nothing to select]', 'value' => '0'));	// no user role matches, output empty list
		}
		foreach ($items as $item) {
			$list[] = array('name' => $item->post_title, 'value' => intval($item->ID));
		}
		return $list;
	}

	// builds a name/value array for inputs out of a simple array
	function select_generic($items) {
		$list = array();
		foreach ($items as $item) {
			$list[] = array('name' => $item, 'value' => sanitize_title($item));
		}
		return $list;
	}
}

// INIT
$somaMetaboxes = new somaMetaboxes();