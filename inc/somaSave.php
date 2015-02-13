<?php
class somaSave extends somaticFramework {

	function __construct() {
		add_action('admin_notices', array(__CLASS__,'save_notices'));
		add_action('save_post', array(__CLASS__, 'save_asset'), 10, 2);
		// add_action('save_post', array(__CLASS__, 'completion_validator'), 70, 2); // must fire after metadata and doc completion is determined
		// add_action('pending_to_publish', 'example'); // could be useful to only execute certain things once it's been approved? like stuff that should only happen after editors have finalized?
	}

	// displays error messages
	function save_notices() {
		global $post;
		if (isset( $_GET['validation'] ) ) {
			switch ( $_GET['validation'] ) {
				case "fail":
					echo "<div class=\"error\"><p><strong>This item is incomplete! Can't submit until all metadata is completed. </strong></p><p><em>If you are unable to complete the fields now, please save your changes for editing later.</em></p></div>";
					echo "<style type=\"text/css\">input#save-post { background: lightGreen; }</style>"; // highlights the "save" button
				break;
				case "success":
					echo "<div class=\"updated success\"><p><strong>Success! This item has been published and will be displayed publicly.</strong></p></div>";
				break;
				case "submitted":
					echo "<div class=\"updated success\"><p><strong>Success! This item has been submitted for review. An Editor will process it shortly.</strong></p></div>";
				break;
			}
		}
		if (isset($_GET['meta_missing']))
			echo '<div class="error"><p>Metadata fields are incomplete!</p></div>';
	}

	function completion_validator($pid, $post = null) {
		if ( somaFunctions::fetch_index($_POST, 'action') == "inline-save" ) return; //  NOTE: REMOVE THIS ONE IF GOING TO DISPLAY CUSTOM COLUMN BOXES IN QUICK EDIT
		// don't do on autosave or for new post creation or when trashing post
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft'  || $_GET['action'] == 'trash' ) return $pid;
		// abort if core post types
		if ($post->post_type == 'post' || $post->post_type == 'page') return;
		// abort if currently processing a newly spawned asset from a batch acceptance
		if ($post->post_title == "temp" && $post->post_status == 'draft') return;

		// init completion markers
		$meta_missing = false;

		// metadata fields complete?
		if ( has_term( 'incomplete', 'metadata', $pid ) ) {
			add_filter('redirect_post_location', create_function('$location','return add_query_arg("meta_missing", true, $location);'));
			$meta_missing = true;
		}

		// attempting to submit for review - check for completion and warn
		if ( isset($_POST['publish']) && $_POST['post_status'] == 'pending' ) {
			//  don't allow pending while any of these are incomplete
			if ( $meta_missing || $other_missing) {
				global $wpdb;
				$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $pid ) );
				// filter the query URL to change the published message
				add_filter('redirect_post_location', create_function('$location','return add_query_arg("message", "4", $location);'));
				// filter the query URL to display failure notice
				add_filter('redirect_post_location', create_function('$location','return add_query_arg("validation", "fail", $location);'));
			} else {
				// filter the query URL to display success notice
				add_filter('redirect_post_location', create_function('$location','return add_query_arg("validation", "submitted", $location);'));
			}
		}

		// attempting to publish - check for completion and intervene if necessary
		if ( (isset($_POST['publish']) || isset($_POST['save']) ) && $_POST['post_status'] == 'publish' ) { 	// $_POST['publish'] or $_POST['save'] shows they pushed the big blue button (even if it says "submit for review"), so also check post_status
			//  don't allow publishing while any of these are incomplete
			if ( $meta_missing || $other_missing ) {
				global $wpdb;
				$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $pid ) );
				// filter the query URL to change the published message
				add_filter('redirect_post_location', create_function('$location','return add_query_arg("message", "4", $location);'));
				// filter the query URL to display failure notice
				add_filter('redirect_post_location', create_function('$location','return add_query_arg("validation", "fail", $location);'));
			} else {
				if ( isset($_POST['publish']) && $_POST['post_status'] == 'publish' ) {
					// filter the query URL to display success notice (only if we're publishing, not just updating a published item)
					add_filter('redirect_post_location', create_function('$location','return add_query_arg("validation", "success", $location);'));
				}
			}
		}
	}

	/**
	 * Fixes the odd indexing of multiple file uploads from the format:
	 *
	 * $_FILES['field']['key']['index']
	 *
	 * To the more standard and appropriate:
	 *
	 * $_FILES['field']['index']['key']
	 *
	 * @param array $files
	 * @author Corey Ballou
	 * @link http://www.jqueryin.com
	 *
	 * returns nothing, transforms the incoming array in situ
	 */
	function fix_file_array(&$files) {
		$names = array(
			'name' => 1,
			'type' => 1,
			'tmp_name' => 1,
			'error' => 1,
			'size' => 1
		);

		foreach ($files as $key => $part) {
			// only deal with valid keys and multiple files
			$key = (string) $key;
			if (isset($names[$key]) && is_array($part)) {
				foreach ($part as $position => $value) {
					$files[$position][$key] = $value;
				}
				// remove old key reference
				unset($files[$key]);
			}
		}
	}


	//** Collect and save data from meta box fields -----------------------------------------------------------------------------------------------------------------------------------------------//
	// NOTE: debugging any values within this function must be done with wp_die() not var_dump() otherwise won't show
	function save_asset($pid, $post) {

		global $wpdb, $hook_suffix;

		// don't do on autosave or for new post creation or when trashing post or when using quick edit
		if ( somaFunctions::fetch_index($_POST, 'action') == "inline-save" ) return;	//  NOTE: REMOVE THIS ONE IF GOING TO DISPLAY CUSTOM COLUMN BOXES IN QUICK EDIT

		// Autosave, do nothing
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		// AJAX? Not used here
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
		// Check user permissions
		if ( ! current_user_can( 'edit_post', $pid ) ) return;

		// Return if it's a post revision
		if ( false !== wp_is_post_revision( $pid ) ) return;

		if ( $post->post_status == 'auto-draft' ) return;

		if ( somaFunctions::fetch_index($_GET,'action') == 'trash' ) return;

		// don't fire unless this form has our signature on it (and thus contains our custom fields to save)
		if ( is_null(somaFunctions::fetch_index($_POST, 'somatic'))) return;

		// trigger all calls to soma_metabox_data() which will each populate somaMetaboxes::$data[] -- IMPORTANT without this there is no metabox data to work with!
		do_action('soma_metabox_data_init', $post);

		// only run our custom save routines if custom metabox data has been defined at least once
		if (empty(somaMetaboxes::$data)) wp_die("Can't save this without custom metabox data...", "Save Error!", array('back_link' => true));

		// let's go!

		$type = get_post_type($pid);
		$type_obj = get_post_type_object($type);
		$cap_type = $type_obj->cap->edit_posts;
		// if ($hook_suffix != 'post.php' || $type == 'post' || $type == 'page' ) { 	// only execute these functions when saving from an individual post edit page for custom types. (allows quick edit on edit.php to work)
		// 	return $pid;
		// }

		// verify nonce
		if (!wp_verify_nonce($_POST['soma_meta_box_nonce'], 'soma-save-asset')) {
			wp_die('Invalid nonce!', 'Save Error!', array('back_link' => true));
		}

		// check permissions
		if (!current_user_can($cap_type, $pid)) {
			wp_die('You are not allowed to edit '.$type, 'Save Error!', array('back_link' => true));		/// DISABLED - this was breaking woocommerce paypal digital goods checkout... maybe move this where it only executes when post type matches
		}

		// reset holding var for determining if fields are empty
		$missing = false;

		//
		//** ---------------------- CYCLE THRU CONFIGURED METABOXES -------------------- **//
		//
		foreach (somaMetaboxes::$data as $meta_box) {
			// don't process metaboxes which are hidden for non-staff (otherwise saving will complain the item is incomplete... )
			if (soma_fetch_index($meta_box, 'restrict') && !SOMA_STAFF) continue;
			// only fire for meta boxes whose post-type is set to the current post object
			if (in_array($type, $meta_box['types'])) {

				// cycle through fields and save post_meta
				foreach ($meta_box['fields'] as $field) {

					// readonly fields - skip saving completely. also skip the post_content editor, as it saves itself...
					if ($field['type'] == 'readonly' || $field['type'] == 'posts' || $field['type'] == 'help' ) continue;

					// single toggle checkboxes are a unique case in that when they are unchecked, they dont' show up in $_POST at all, but this is the only way to communicate that they have been unchecked!
					if (!isset($_POST[$field['id']]) && ($field['type'] == 'checkbox-single' || $field['type'] == 'toggle')) {
						if ($field['data'] == 'meta') {
							somaFunctions::asset_meta('delete', $pid, $field['id']);
							continue;
						}
						if ($field['data'] == 'taxonomy') {
							wp_set_object_terms($pid, null, $field['id'], false);
							continue;
						}
					}

					//
					//** ---------------------- RETRIEVE EXISTING DATA -------------------- **//
					//


					if ($field['data'] == 'meta') {
						$old = somaFunctions::asset_meta('get', $pid, $field['id']);
					}

					//
					if ($field['data'] == 'taxonomy') {
						$taxes = wp_get_object_terms($pid, $field['id']);
						$old = array();										// remains empty if can't fetch anything
						if (!is_wp_error($tax)) {							// make sure the term request didn't fail
							foreach ($taxes as $tax) {
								$old[] = intval($tax->term_id);			// when passing term_id into wp_set_object_terms, the value must be integer, not string, or else it will create a new term with the name of the string
							}
						}
					}
					//
					if ($field['data'] == 'core') {
						$old = $post->$field['id'];
					}

					if ($field['data'] == 'attachment') {
						$old = array();						// just need a placeholder
					}

					//
					if ($field['data'] == 'p2p') {
						$p2pargs = array(
							'direction' => 'any',
							'fields' => 'all',
						);
						// only get connections for this post (otherwise we'd grab all connections within this p2p connection type!)
						if ($field['dir'] == 'to') {
							$p2pargs['from'] = $pid;
						}
						if ($field['dir'] == 'from') {
							$p2pargs['to'] = $pid;
						}
						// retrieve connections
						$conn = p2p_get_connections($field['p2pname'], $p2pargs);

						// for p2p single relationships
						if ($field['type'] == 'p2p-select') {
							switch (true) {
								case (empty($conn)) :
									$old = false;					// if no connections exist, keep false value
								break;
								case ($field['dir'] == 'to') :
									$old = $conn[0]->p2p_to;		// single connected post id
								break;
								case ($field['dir'] == 'from') :
									$old = $conn[0]->p2p_from;		// single connected post id
								break;
							}
						}
						// for p2p multiple relationships
						if ($field['type'] == 'p2p-multi') {
							switch (true) {
								case (empty($conn)) :
									$old = array();					// if no connections exist, make empty array
								break;
								case ($field['dir'] == 'to') :
									foreach ($conn as $con) {
										$old[] = $con->p2p_to;		// array of connected posts
									}
								break;
								case ($field['dir'] == 'from') :
									foreach ($conn as $con) {
										$old[] = $con->p2p_from;	// array of connected posts
									}
								break;
							}
						}
					}

					// skip saving one-time fields that already have data
					if ($old && soma_fetch_index($field,'once')) continue;


					//
					//** -------- RETRIEVE AND ASSEMBLE $NEW DATA FROM CURRENT FIELD STATES -------- **//
					//


					// add prefix to match ID's in $_POST
					if ( $field['data'] == 'core' ) {
						$field['id'] = "core_" . $field['id'];
					}

					// match obfuscated ID's to accomodate wp_editor() finicky ID rules
					if ($field['type'] == 'richtext' || $field['type'] == 'html') {
						$field['id'] = somaFunctions::sanitize_wpeditor_id($field['id']);
					}

					// default source of $new data
					$new = $_POST[$field['id']];


					// taxonomy data has to be sanitized first  --------------------------------------------------------------//
					if ($field['data'] == 'taxonomy') {
						// $_POST gave us 'create'
						if ($new == 'create') {							// the taxonomy selector has been set to "Create New"
							$new = $_POST[$field['id']."-create"];		// so grab the text from the accompanying input field, which will passed a string, thus creating a new term
							$old = "";									// make old an empty string instead of empty array, so that later conditionals will work

						// $_POST gave us array
						} elseif (is_array($new)) {
							foreach ($new as $key => $var) {			// convert all array items to integer
								$new[$key] = intval($var);
							}

						// $_POST gave us a single value, still need to transform into array
						} else {
							if (empty($new)) {
								$new = array();
							} else {
								$new = array(intval($new));				// when passing term_id thru wp_set_object_terms, the value must be integer, not string, or else it will create a new term with the name of the string
							}

						}
					}

					// for link inputs  --------------------------------------------------------------//
					if ($field['type'] == 'link') {
						$title = $_POST[$field['id']][0];
						$url = $_POST[$field['id']][1];
						$new = array();
						$new['title'] = $title;
						// if (filter_var($url, FILTER_VALIDATE_URL)) {
							$new['url'] = $url;
						// } else {
						// 	$new['url'] = '( invalid URL given - make sure to include http:// )';
						// }
					}

					// for date inputs  --------------------------------------------------------------//
					if ($field['type'] == 'date') {

						// assemble date field data
						$day = $_POST[$field['id'].'_day'];
						$month = $_POST[$field['id'].'_month'];
						$year = $_POST[$field['id'].'_year'];
						if ($day != null && $month != null && $year != null) {
							$date = $year.'-'.$month.'-'.$day;
							$new = date("Y-m-d", strtotime($date)); // force storing date as Y-m-d, not timestamps
						} else {
							$new = null;
						}
					}
					// for datepicker jqueryUI inputs  --------------------------------------------------------------//
					if ($field['type'] == 'datepicker') {
						if ($_POST[$field['id']] != '') {
							$new = date("Y-m-d", strtotime($_POST[$field['id']]));	 // force storing date as Y-m-d, not timestamps
						}
						else {
							$new = null;
						}
					}
					// for time inputs (dropdown select interface)  --------------------------------------------------------------//
					if ($field['type'] == 'time') {
						// assemble date field data
						$hour = $_POST[$field['id'].'_hour'];
						$minute = $_POST[$field['id'].'_minute'];
						$meridiem = $_POST[$field['id'].'_meridiem'];
						if ($hour != '' && $minute != '') {
							$time = $hour . ":" . $minute . " " . $meridiem;  // assemble human readable timestring
							$new = date("H:i:s", strtotime($time));		// storing time directly as H:i:s, not timestamps!
						} else {
							$new = null;
						}
					}
					// for timepicker jqueryUI inputs  --------------------------------------------------------------//
					if ($field['type'] == 'timepicker') {
							if ($_POST[$field['id']] != '') {
								$new = date("H:i:s", strtotime($_POST[$field['id']]));	// force storing time directly as H:i:s, not timestamps!
							}
							else {
								$new = null;
							}
					}
					// for weight inputs  --------------------------------------------------------------//
					if ($field['type'] == 'weight') {

						$lbs = intval($_POST[$field['id'].'_lbs']);
						$oz = intval($_POST[$field['id'].'_oz']);
						$total = ($lbs * 16) + $oz;
						if ($total > 0) {
							$new = $total;
						} else {
							$new = false;
						}
					}

					// transforming the html checkbox value of "on" into boolean for DB clarity adn easier testing later...
					if (($field['type'] == 'checkbox-single' || $field['type'] == 'toggle') && $_POST[$field['id']] == "on") {
						$new = 1;
					}

					// for external media URL fields  --------------------------------------------------------------//
					if ($field['type'] == 'external_media') {
						$ext_media = null;																			// init container

						// field is newly changed
						if (!empty($new) && $new != $old) {
							$ext_media = somaFunctions::fetch_external_media($new);									// parse url and ping APIs
							if (is_wp_error($ext_media)) {															// did the url process okay?
								$new = null;																		// didn't work, nuke the field data
								// wp_die($ext_media->get_error_message());											// should probably display an error notification rather than dying....
								continue;																			// skip??
							}
							$new = $ext_media['url'];															// replace user-submitted with "cleaned" url
							somaFunctions::asset_meta('save', $pid, $field['id']."_ext", $ext_media);			// save another key with all the metadata associated with this external media url (so we don't have to call external API's everytime)
						}

						// field is already populated, but/and user has chosen to copy the external title/desc into the asset, replacing the user fields NOTE: for this to work, this field should appear after the title and desc fields!
						if (!empty($new) && $_POST['copy-ext-meta']) {
							if (empty($ext_media)) $ext_media = somaFunctions::fetch_external_media($new);			// grab if we haven't already (since $new hasn't changed)
							if (is_wp_error($ext_media)) {
								$new = null;																		// didn't work, nuke the field data
								// wp_die($ext_media->get_error_message());											// find a better way to show what happened...
								continue;																			// skip??
							}
							somaFunctions::asset_meta('save', $pid, 'desc', $ext_media['desc']);					// overwrite the desc
							$wpdb->update( $wpdb->posts, array( 'post_title' => $ext_media['title'] ), array( 'ID' => $pid ));	// overwrite the post title
						}

						// field is already populated, but/and user has chosen to import external source thumbnail into library and attach to post and possibly set as featured image
						if (!empty($new) && $_POST['import-ext-image'] || $_POST['use-ext-feature']) {
							if (empty($ext_media)) $ext_media = somaFunctions::fetch_external_media($new);			// grab if we haven't already (since $new hasn't changed)
							if (is_wp_error($ext_media)) {
								$new = null;																		// didn't work, nuke the field data
								// wp_die($ext_media->get_error_message());											// should probably display an error notification rather than dying....
								continue;																			// skip??
							}


							$existing = somaFunctions::asset_meta('get', $pid, $field['id']."_attached");			// did we already save an attachment for this field?
							if ( !empty( $existing ) ) {
								wp_delete_attachment( $existing, true );											// kill it so we don't spawn more
							}

							// upload the image from url into library, and use as featured image if checked
							$result = somaFunctions::attach_external_image($ext_media['thumb'], $pid, $_POST['use-ext-feature'], null, array('post_title' => '[ext] '.$ext_media['title'])); // title the attachment [frame] title (of external video)
							if (is_wp_error($result)) {
								$new = null;																		// didn't work, nuke the field data
								// wp_die($ext_media->get_error_message());											// should probably display an error notification rather than dying....
								continue;																			// skip??
							}

							// save new attachment ID to indicate that we have already imported an attachment for this field, so we can overwrite it later (if user checks import-ext-image again) instead of spawning more attachments
							somaFunctions::asset_meta('save', $pid, $field['id']."_attached", $result);
						}
						// field has been cleared, so get rid of any external metadata that may have been stored
						if (empty($new) && $new != $old) {
							somaFunctions::asset_meta('delete', $pid, $field['id']."_ext");
						}
					}

					// for external media URL fields  --------------------------------------------------------------//
					if ($field['type'] == 'external_image') {
						if (!empty($new)) {																				// field newly or already populated

							// import external source thumbnail into library and attach to post
							if ($_POST['import-ext-image'] || $_POST['use-ext-feature']) {

								// did we already save an attachment for this field?
								$existing = somaFunctions::asset_meta('get', $pid, $field['id']."_attached");
								if ( !empty( $existing ) ) {
									wp_delete_attachment( $existing, true );											// kill it so we don't spawn more
								}

								// upload the image from url into library, and use as featured image if checked
								$result = somaFunctions::attach_external_image($new, $pid, $_POST['use-ext-feature'] ); // should give this a post_title? but based on what?
								if (is_wp_error($result)) {
									$new = null;																		// didn't work, nuke the field data
									// wp_die($ext_media->get_error_message());											// should probably display an error notification rather than dying....
									continue;																			// skip??
								}

								// save new attachment ID to indicate that we have already imported an attachment for this field, so we can overwrite it later (if user checks import-ext-image again) instead of spawning more attachments
								somaFunctions::asset_meta('save', $pid, $field['id']."_attached", $result);
							}
						}
					}

					// OLD STYLE basic html file selector input uploader. replaced with plupload system below
					// file uploads (creates attachments)  --------------------------------------------------------------//
					if ($field['type'] == 'upload-OLD') {
						if (!soma_fetch_index($_FILES, $field['id'])) continue;			// skip if no file has been posted for uploading

						self::fix_file_array($_FILES[$field['id']]); 							// reformats array to better process each item

						foreach ($_FILES[$field['id']] as $position => $fileitem) {
							if ($fileitem['error'] == UPLOAD_ERR_NO_FILE) continue;				// don't bother handling uploads from blank inputs
							$file = wp_handle_upload($fileitem, array('test_form' => false));
							if (isset($file['error'])) {
								wp_die(var_dump($fileitem, $file['error']));					// barf when there's a problem
							}
							$filename = $file['file'];											// local path

							if (!empty($filename)) {
								$wp_filetype = wp_check_filetype(basename($filename), null);	// get from file extension
								$attachment = array(
									'post_mime_type' => $wp_filetype['type'],
									'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
									'post_status' => 'inherit'
								);
								$attach_id = wp_insert_attachment($attachment, $filename, $pid);
								// you must first include the image.php file
								// for the function wp_generate_attachment_metadata() to work
								require_once(ABSPATH . 'wp-admin/includes/image.php');
								$attach_data = wp_generate_attachment_metadata($attach_id, $filename);
								wp_update_attachment_metadata($attach_id, $attach_data);
							}
						}
						continue; // skip everything else, we're not comparing old/new uploads...
					}

					// image uploads (creates attachments and can set as featured image)  --------------------------------------------------------------//
					// uses hidden inputs for data retrieval
					// keys passed for each image instance: file, url, mime/type
					if ($field['type'] == 'upload-files' || $field['type'] == 'upload-images') {
						if (!soma_fetch_index($_POST, $field['id'])) continue;			// skip if no file has been posted for uploading
						foreach ($_POST[$field['id']] as $incoming) {
							if (file_exists($incoming['file'])) {
								$attachment = array(
									'post_mime_type' => $incoming['type'],
									'post_title' => preg_replace('/\.[^.]+$/', '', basename($incoming['file'])),
									'post_status' => 'inherit'
								);
								$attach_id = wp_insert_attachment($attachment, $incoming['file'], $pid);
								// you must first include the image.php file
								// for the function wp_generate_attachment_metadata() to work
								require_once(ABSPATH . 'wp-admin/includes/image.php');
								$attach_data = wp_generate_attachment_metadata($attach_id, $incoming['file']);
								wp_update_attachment_metadata($attach_id, $attach_data);

								// set as featured image
								if ($field['data'] == 'featured') set_post_thumbnail($pid, $attach_id);
							}
						}
						continue; // skip everything else, we're not comparing old/new uploads...
					}

					// hook additional field type conditionals to save custom metadata
					// must return $new unmodified if conditionals don't match!
					$new = apply_filters('soma_field_new_save_data', $new, $field, $pid, $post);


					//
					//** ----------------------- COMMIT CHANGES TO DB ------------------------- **//
					//


					// ---- restore obfuscated ID's to match original data source ID's before saving ---- //
					if ($field['type'] == 'richtext' || $field['type'] == 'html') {
						$field['id'] = somaFunctions::unsanitize_wpeditor_id($field['id']);
					}

					// ---- strip core prefix from ID
					if ($field['data'] == 'core') {
						$field['id'] = substr($field['id'], strlen("core_"));
					}

					// ---- if field is empty, nuke saved values ---- //
					if ($new == '' || $new == null || (is_array($new) && empty($new))) {

						if ($field['data'] == 'taxonomy') {
							wp_set_object_terms($pid, $new, $field['id'], false);
						}

						if ($field['data'] == 'meta') {
							somaFunctions::asset_meta('delete', $pid, $field['id']);
						}

						// only do this if meta field is required
						// comments are optional, so don't tag as missing
						if (soma_fetch_index($field, 'required') && $field['data'] != 'comment') {
							// set missing state at least this once for assigning incomplete metadata later
							$missing = true;
						}


						if ( $field['data'] == 'core' ) {

							// unhook this function so it doesn't loop infinitely
							remove_action('save_post', array(__CLASS__, 'save_asset'), 10, 2);

							// update the post, which calls save_post again
							$update_post = array();
							$update_post['ID'] = $pid;
							if ( $field['id'] == 'post_title' ) {
								$new = "Untitled";
								$update_post['post_name'] = wp_unique_post_slug( 'untitled', $pid, $post->post_status, $post->post_type, $post->post_parent );
							}
							$update_post[$field['id']] = $new;
							wp_update_post( $update_post );

							// re-hook this function
							add_action('save_post', array(__CLASS__, 'save_asset'), 10, 2);
						}

						// move on - no additional save routines needed
						continue;
					}



					// ---- field isn't blank and it's changed from old ---- //

					// array data

					if (is_array($new) && is_array($old)) {
						$diffo = array_diff($old, $new);
						$diffn = array_diff($new, $old);
						// wp_die(var_dump($new, $old, $diffn, $diffo));	// debug
						if (!empty($diffo) || !empty($diffn)) {

							//
							if ($field['data'] == 'taxonomy') {
								if ((count($new) > 1) && $field['multiple'] === false) {
									wp_die("not allowed to assign multiple terms for {$field['name']}!", 'Save Error!', array('back_link' => true));
								} else {
									wp_set_object_terms($pid, $new, $field['id'], false);			// multiple values accepted for non-hierarchical (tag-style)
								}
							}

							//
							if ($field['data'] == 'meta') {
								if ((count($new) > 1) && $field['multiple'] === false) {
									wp_die("not allowed to assign multiple terms for {$field['name']}!", 'Save Error!', array('back_link' => true));
								} else {
									somaFunctions::asset_meta('save', $pid, $field['id'], $new);
								}
							}

							//
							if ($field['data'] == 'p2p' && $field['type'] == 'p2p-multi') {
								// remove all connections first?
								foreach ($conn as $con) {
									p2p_delete_connection($con->p2p_id);
								}
								// set all new connections?
								foreach ($new as $newid) {
									p2p_type( $field['p2pname'] )->connect( $pid, $newid, array(
										'date' => current_time('mysql')
									) );
								}
							}

							// updating attachments own meta
							if ($field['data'] == 'attachment') {
								$atts = array();
								$count = count($new["id"]);	// how many attachments are we processing?
								$c = 0;
								while ( $c < $count) {
									$entry = array();
									foreach ($new as $key => $list) {
										$entry[$key] = $list[$c];
									}
									$atts[] = $entry;
									$c++;
								}

								$i = 1;
								foreach ($atts as $att) {
									if (wp_is_post_revision( $pid )) continue;
									// unhook this function so it doesn't loop infinitely
									remove_action('save_post', array(__CLASS__, 'save_asset'), 10, 2);
									$slug = sanitize_title( $att['title'] );
									$attpost = array(
											'ID' => $att['id'],
											'post_title' => $att['title'],
											'post_content' => $att['description'],
											'post_excerpt' => $att['caption'],
											'menu_order' => $i,						// gallery order determined by order they appear in the $_POST data
											'post_name' => $slug,
										);
									$push = wp_update_post( $attpost );
									// re-hook this function
									add_action('save_post', array(__CLASS__, 'save_asset'), 10, 2);
									if ($push === 0) wp_die('wpdb update had a problem with the attachments...');

									$i++;
								}
							}

						}

					// non-array data

					} elseif (!empty($new) && $new != $old) {

						if ( $field['data'] == 'taxonomy' ) {
							wp_set_object_terms($pid, $new, $field['id'], false);
						}

						if ( $field['data'] == 'meta' ) {
							somaFunctions::asset_meta('save', $pid, $field['id'], $new);
						}

						if ( $field['data'] == 'core' ) {

							// unhook this function so it doesn't loop infinitely
							remove_action('save_post', array(__CLASS__, 'save_asset'), 10, 2);

							// update the post, which calls save_post again
							$update_post = array();
							$update_post['ID'] = $pid;
							$update_post[$field['id']] = $new;
							// special case for making slugs
							if ( $field['id'] == 'post_title' ) {
								$slug = sanitize_title( $new );
								$slug = wp_unique_post_slug( $slug, $pid, $post->post_status, $post->post_type, $post->post_parent );
								$update_post['post_name'] = $slug;
							}
							wp_update_post( $update_post );

							// re-hook this function
							add_action('save_post', array(__CLASS__, 'save_asset'), 10, 2);
						}

						if ($field['data'] == 'p2p' && $field['type'] == 'p2p-select') {
							// remove all connections first, as we're only keeping one
							p2p_delete_connection($conn[0]->p2p_id);
							p2p_type( $field['p2pname'] )->connect( $pid, $new, array(
								'date' => current_time('mysql')
							) );
						}

						if ($field['data'] == 'comment') {
							global $current_user;
							$data = array(
								'comment_post_ID' => $pid,
								'comment_author' => $current_user->data->display_name,
								'comment_author_email' => $current_user->data->user_email,
								'comment_content' => $new,
								'comment_agent' => $_SERVER['HTTP_USER_AGENT'],
								'comment_date' => date('Y-m-d H:i:s'),
								'comment_date_gmt' => date('Y-m-d H:i:s'),
								'comment_approved' => 1,
							);
							$comment_id = wp_new_comment($data);
						}

					}



					// hook additional field data cases to save custom metadata
					// must match $field['data'] to something before executing any saves, otherwise will always fire!
					do_action('soma_field_save_meta', $new, $field, $pid, $post);


					// useful for debugging what's being saved
					$trace[$field['id']] = $new;
				}
			}
		}
		// set completion taxonomy term
		$missing ? wp_set_object_terms($pid, 'incomplete', 'metadata', false) : wp_set_object_terms($pid, 'complete', 'metadata', false);
		// useful for debugging what's being saved
		$trace['missing'] = $missing;
		// wp_die(var_dump($trace));

		// hook additional save actions
		do_action('soma_save_asset', $pid, $post);
	}
	//** END SAVE METABOX DATA

}

$somaSave = new somaSave();
?>