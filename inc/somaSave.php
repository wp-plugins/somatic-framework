<?php
class somaSave extends somaticFramework {

	function __construct() {
//		add_action('save_post', array(__CLASS__, 'validator') );
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
		if ($_GET['meta_missing'])
			echo '<div class="error"><p>Metadata fields are incomplete!</p></div>';
	}

	function completion_validator($pid, $post = null) {
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
	function save_asset($pid, $post = null) {

		// don't do on autosave or for new post creation or when trashing post
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft'  || $_GET['action'] == 'trash' ) return $pid;

		// don't do for core post types
		if ($post->post_type == 'post' || $post->post_type == 'page') return;

		global $wpdb;
		global $hook_suffix;

		$type = get_post_type($pid);
		$type_obj = get_post_type_object($type);
		$cap_type = $type_obj->cap->edit_posts;
		if ($hook_suffix != 'post.php' || $type == 'post' || $type == 'page' ) { 	// only execute these functions when saving from an individual post edit page for custom types. (allows quick edit on edit.php to work)
			return $pid;
		}
		// custom metabox data exists?
		if (empty(somaMetaboxes::$data) || !somaMetaboxes::$data) {
			wp_die('missing custom metabox data...', 'Save Error!', array('back_link' => true));
		}

		// verify nonce
		if (!wp_verify_nonce($_POST['soma_meta_box_nonce'], 'soma-save-asset')) {
			// wp_die('Invalid nonce!', 'Save Error!', array('back_link' => true));
		}

		// check permissions
		if (!current_user_can($cap_type, $pid)) {
			wp_die('You are not allowed to edit '.$type, 'Save Error!', array('back_link' => true));
		}

		// reset var for determining if fields are empty
		$missing = false;

		//** CYCLE THROUGH ALL METABOXES AND SAVE
		foreach (somaMetaboxes::$data as $meta_box) {
			// don't process metaboxes which are hidden for non-staff (otherwise saving will complain the item is incomplete... )
			if ($meta_box['restrict'] && !SOMA_STAFF) continue;
			// skip meta boxes whose post-type is not being used on this edit screen
			if (in_array($type, $meta_box['types'])) {

				// cycle through fields and save post_meta
				foreach ($meta_box['fields'] as $field) {

					// readonly fields - skip saving completely. also skip the post_content editor, as it saves itself...
					if ($field['type'] == 'readonly' || $field['type'] == 'posts' || $field['type'] == 'help' ) {
						continue;
					}
					
					// retrieve existing data per type
					if ($field['data'] == 'meta') {
						$old = somaFunctions::asset_meta('get', $pid, $field['id']);
					}
					// skip saving one-time fields that already have data
					if ($old && $field['once']) {
						continue;
					}
					if ($field['data'] == 'taxonomy') {
						$tax = wp_get_object_terms($pid, $field['id']);
						if (!is_wp_error($tax)) { // make sure the term request didn't fail
							$old = intval($tax[0]->term_id);	// when passing term_id into wp_set_object_terms, the value must be integer, not string, or else it will create a new term with the name of the string
						} else {
							$old = '';
						}
					}
					if ($field['data'] == 'core') {
						$old = $post->$field['id'];
					}


					if ($field['data'] == 'user') {
						$old = $post->post_author;
					}
					// retrieve and assemble $new data from current field states

					// default $new
					$new = $_POST[$field['id']];

					// $new transformations by type
					if ($field['data'] == 'taxonomy') {
						if ($field['id']=='new-'.$field['taxonomy'].'-term') {
							$new = $new; // leave as string
						} elseif (is_array($new)) {
							foreach ($new as $key => $var) {	// convert all array items to integer
								$new[$key] = intval($var);
							}
						} else {
							$new = intval($new);	// when passing term_id thru wp_set_object_terms, the value must be integer, not string, or else it will create a new term with the name of the string
							if ($new == 0) {
								$new = null;
							}
						}
					}
					// for date inputs
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
					// for datepicker jqueryUI inputs
					if ($field['type'] == 'datepicker') {
						if ($_POST[$field['id']] != '') {
							$new = date("Y-m-d", strtotime($_POST[$field['id']]));	 // force storing date as Y-m-d, not timestamps
						}
						else {
							$new = null;
						}
					}
					// for time inputs (dropdown select interface)
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
					// for timepicker jqueryUI inputs
					if ($field['type'] == 'timepicker') {
							if ($_POST[$field['id']] != '') {
								$new = date("H:i:s", strtotime($_POST[$field['id']]));	// force storing time directly as H:i:s, not timestamps!
							}
							else {
								$new = null;
							}
					}
					// for weight inputs
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
					
					// file uploads (creates attachments)
					if ($field['type'] == 'upload') {
						if (!empty($_FILES[$field['id']])) {
							
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
						}
						continue; // skip everything else, we're not comparing old/new uploads...
					}

					// hook additional field type conditionals to save custom metadata
					// must return $new unmodified if conditionals don't match!
					$new = apply_filters('soma_field_save_meta', $new, $field, $pid, $post);


					// var_dump($field['id']);
					// var_dump($new);

					//**** SAVING *****

					// if field is empty
					if ($new == '' || $new == null) {
						if ($field['data'] == 'taxonomy') {
							if ($field['id'] == 'new-'.$field['taxonomy'].'-term') {
								continue;
							}
							// all other taxonomy cases
							wp_set_object_terms($pid, $new, $field['id'], false);
						}
						if ($field['data'] == 'meta') {
							somaFunctions::asset_meta('delete', $pid, $field['id']);
						}

						// single checkboxes that are not checked are supposed to return null, and are entirely optional, so don't tag as missing
						// comments are also optional
						// only do this if meta field is required
						if ($field['type'] != 'checkbox-single' && $field['data'] != 'comment' && $field['required'] === true) {
							// set missing state at least this once for assigning incomplete metadata later
							$missing = true;
							// var_dump($field['id']); // use this to debug what's missing
						}
						continue;
					// field isn't blank and it's changed from old
					}
					if ($new && $new != $old) {
						if ($field['data'] == 'taxonomy') {
							if ($new == 'create') { // skip saving the select box which is indicating term creation
								continue;
							} elseif (is_array($new) && !$field['multiple']) {
								wp_die("not allowed to assign multiple terms!", 'Save Error!', array('back_link' => true));
							} elseif ($field['id'] == 'new-'.$field['taxonomy'].'-term') { 	// creating a new term from field instead of selector
								wp_set_object_terms($pid, $new, $field['taxonomy'], false);
							} else {
								wp_set_object_terms($pid, $new, $field['id'], false);		// multiple values accepted for non-hierarchical (tag-style) on stills and videos
							}
						}

						if ($field['data'] == 'meta') {
							somaFunctions::asset_meta('save', $pid, $field['id'], $new);
						}

						if ($field['data'] == 'user') {
							$wpdb->update( $wpdb->posts, array( $field['id'] => $new ), array( 'ID' => $pid ));
						}

						if ($field['data'] == 'core') {
							if ($field['type'] == "richtext" || $field['type'] == "textarea") {
								$new = stripslashes($new);									// because tinymce adds them.... even though we're using the_editor()...
							}
							$wpdb->update( $wpdb->posts, array( $field['id'] => $new ), array( 'ID' => $pid ));
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
						
						// hook additional field data cases to save custom metadata
						// must match $field['data'] to something before executing any saves, otherwise will always fire!
						do_action('soma_field_save_meta', $new, $field, $pid, $post);

					// new value is blank, changed from old value
					// selection set to "none", so get rid of meta and terms
					}
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
	}
	//** END SAVE METABOX DATA

}

$somaSave = new somaSave();
?>