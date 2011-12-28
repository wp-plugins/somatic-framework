<?php
class somaMetaboxes extends somaticFramework {

	function __construct() {
		add_action( 'post_edit_form_tag' , array(__CLASS__,'post_edit_form_tag' ) );
		add_action( 'admin_print_styles-post.php', array(__CLASS__, 'jplayer_styles') );
		add_action( 'admin_print_scripts-post.php', array(__CLASS__, 'jplayer_scripts') );
	}

	// load jplayer on post.php for media objects (wish we could load these only if media metabox existed...)
	function jplayer_scripts() {
		wp_enqueue_script( 'jplayer' );
		wp_enqueue_script( 'jplayer-playlist' );
		// wp_enqueue_script( 'jplayer-inspector' );
	}
	function jplayer_styles() {
		wp_enqueue_style( 'jplayer-style' );
	}

	// needed to allow file upload inputs (input[type="file"]) within post.php
	function post_edit_form_tag( ) {
	    echo ' enctype="multipart/form-data"';
	}

	//
	// function load_tiny_mce() {
	// 	wp_tiny_mce( false ); // true gives you a stripped down version of the editor
	// }

	//*****  vars for passing data to somatic framework ********//
	static $data = array();				// container for other plugins and themes to store custom metabox and field data
	static $meta_serialize = true;		// whether we will store post_meta in serialized array (default), or as flat keys (wp default)
	static $meta_prefix = '_soma_';		// prefix to create name key name of serialized post_meta
	//*****

	// called in the custom post type registration, responsible for rendering metaboxes in those types
	function add_boxes($post) {

		if (empty(self::$data) || !self::$data) {
			wp_die('missing custom metabox data...', 'Load Error!', array('back_link' => true));
		}
		
		// hook for insertion before any box content
		do_action('soma_before_all_metaboxes', $post);

		// Generate meta box for each array
		foreach ( self::$data as $meta_box ) {
			if ( in_array($post->post_type, $meta_box['types'] ) ) {	// check if current post type is to use this metabox
				if ( !$meta_box['restrict'] || $meta_box['restrict'] && SOMA_STAFF ) {	// don't show certain boxes for non-staff
					add_meta_box($meta_box['id'], $meta_box['title'], array(__CLASS__,'soma_metabox_generator'), $post->post_type, $meta_box['context'], $meta_box['priority'], array('box'=>$meta_box));
				}
			}
		}
		
		// hook for insertion before any box content
		do_action('soma_after_all_metaboxes', $post);
	}


	// add_meta_box Callback function to display fields in meta box
	function soma_metabox_generator($post,$box) {
		$meta_box = $box['args']['box'];

		// hook for insertion before this box content
		do_action('before_'.$box['id'], $post, $box['id']);

		// Use nonce for verification
		echo '<input type="hidden" name="soma_meta_box_nonce" value="', wp_create_nonce("soma-save-asset"), '" />';
		echo '<table class="form-table">';

		foreach ($meta_box['fields'] as $field) {
			$meta = null;																// reset the value of $meta each loop, otherwise an empty iteration can pass on $meta to the next one
			// get current taxonomy data
			if ($field['data'] == 'taxonomy') {
				if (empty($field['options']) && $field['id'] != 'new-'.$field['taxonomy'].'-term' && $field['type'] != 'readonly') {		// the new-term field is going to retrieve empty, but we want to render it anyway. - ALSO, readonly taxonomies don't bother retrieving options
					continue;														// if the options are empty, then the taxonomy itself doesn't exist or it has no terms and rendering will fail, so skip this field
				}
				if ($field['id']=='new-'.$field['taxonomy'].'-term') {				// don't bother fetching existing terms, as we're going to create a new term with this field
					$meta = null;													// clear $meta so it doesn't fill the input text box with string "array" - don't care about displaying saved data in this one case
				} else {
					$terms = wp_get_object_terms($post->ID, $field['id']); 			// retrieve terms
					if ($field['type'] == 'readonly' && !$field['multiple']) {		// if readonly and singluar, then $meta is just the name, no object
						$meta = $terms[0]->name;
					}
					if ($field['type'] != 'readonly' && !$field['multiple']) { 		// not readonly but singular
						$meta = intval($terms[0]->term_id);							// grab only first array, as we accept singular storage only
					}
					if ($field['type'] == 'readonly' && $field['multiple']) {		// if readonly and multiple, then $meta is just a list, no objects
						$meta = somaFunctions::fetch_the_term_list( $post->ID, $field['id'],'',', ');// returns linked html list of comma-separated terms
						if (!$meta) {
							continue;												// if fetching terms comes up empty, then don't render this one
						}
					}
					if ($field['type'] != 'readonly' && $field['multiple']) {		// not readonly but multiple values are accepted
						foreach ($terms as $term) {
							$meta[] = intval($term->term_id);
						}
					}
				}
			}

			// get current post meta data
			if ($field['data'] == 'meta') {
				$meta = somaFunctions::asset_meta('get', $post->ID, $field['id']);
			}
			// if field is readonly and meta is empty, skip this line
			if ($field['data'] == 'meta' && $field['type'] == 'readonly' && !$meta) {
				// continue;
			}

			// get current post core database fields
			if ($field['data'] == 'core') {
				$meta = $post->$field['id'];
			}

			// get current author
			if ($field['data'] == 'user') {
				if ($field['type'] == 'readonly') {
					// retrieve name only
					$meta = somaFunctions::fetch_author($post->ID, null, true);
				} else {
					// retrive ID of author user
					$meta = $post->post_author;
				}
			}

			// get media attachment
			if ($field['data'] == 'media') {
				$meta = somaFunctions::fetch_attached_media($post->ID);
			}

			// get connected posts by types and direction
			if ($field['data'] == 'p2p') {
				if ($field['type'] == 'p2p-thumbs') {
					$meta = somaFunctions::fetch_connected_items( $post->ID, $field['post_type'], $field['dir'], $output = 'objects');
				}
				if ($field['type'] == 'p2p-list') {
					$meta = somaFunctions::fetch_connected_items( $post->ID, $field['post_type'], $field['dir'], $output = 'html');
				}
				// if no connections exist, skip rendering this line
				if (!$meta) {
					continue;
				}
			}
			// get all attachments as objects for this post
			if ($field['type'] == 'attachment') {
				$args = array(
				     'post_type' => 'attachment',
				     'numberposts' => -1,
				     'post_status' => null,
				     'post_parent' => $post->ID,
				     );
				$meta = get_posts($args);
				// don't render field at all if there's no attachments
				if (!$meta) {
					continue;
				}
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

			// if there's a default option, or it's not required, don't add the "missing" class
			if ($field['default'] != '' || $field['required'] != true) {
				$complete = true;
			}

			// for status taxonomies, indicate as "missing", even though $meta isn't empty, because a stauts of "incomplete" is the same as missing metadata
			if ($field['data'] == 'taxonomy') {
				$term = get_term($meta, $field['id']);
				if ($term->slug == 'incomplete' || $term->slug == 'processing') {
					$complete = false;
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

			// begin building table row
			echo '<tr id="'.$field['id'].'-row">';

			// include column for field name if included
			if ($field['name']) {
				echo '<td class="field-label"><label for="', $field['id'], '" class="', $complete ? null : $missing, '" >', $field['name'], '</label></td>';
				echo '<td class="field-data">';
			} else {
				// no name given, so span both columns
				echo '<td colspan="2">';
			}


			// build each field content by type
			switch ($field['type']) {
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'text':
					echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['default'], '" class="meta-text', $complete ? null : $missing, '" />';
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'help':
					echo '<p class="help">', $field['desc'], '</p>';
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'numeric':
					echo '<input type="text" alt="numeric" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['default'], '" class="meta-numeric', $complete ? null : $missing, '" />';
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'textarea':
					echo '<textarea name="', $field['id'], '" id="', $field['id'], '" cols="60" rows="5" class="meta-textarea', $complete ? null : $missing, '" >', $meta ? $meta : $field['default'], '</textarea>';
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'upload': ?>
						<input type="file" name="<?php echo $field['id']; ?>[]" id="" />
					</td></tr>
					<tr>
						<td></td>
						<td><a class="addinput" href="#" rel="<?php echo $field['id']; ?>">Add More Files</a>
					<?php
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
				break;

				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'attachment':
					echo '<ul class="meta-attachment-gallery">';
					foreach ($meta as $att) :
						echo '<li class="meta-attachment-item">';
							switch ($att->post_mime_type) {
								case "application/pdf" :
									echo '<a href="http://docs.google.com/viewer?url='.urlencode(wp_get_attachment_url($att->ID)).'&embedded=true" class="colorbox" iframe="true"><img src="'. SOMA_IMG . 'pdf-doc.png" title="Click to view PDF file with Google Docs" /></a>';
								break;
								case "application/msword" :
								case "application/vnd.ms-word" :
								case "application/vnd.openxmlformats-officedocument.wordprocessingml.document" :
									echo '<a href="http://docs.google.com/viewer?url='.urlencode(wp_get_attachment_url($att->ID)).'&embedded=true" class="colorbox" iframe="true"><img src="'. SOMA_IMG . 'word-doc.png" title="Click to view Microsoft Word Doc with Google Docs" /></a>';
								break;
								case "application/msexcel" :
								case "application/vnd.ms-excel" :
								case "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" :
									echo '<a href="http://docs.google.com/viewer?url='.urlencode(wp_get_attachment_url($att->ID)).'&embedded=true" class="colorbox" iframe="true"><img src="'. SOMA_IMG . 'excel-doc.png" title="Click to view Microsoft Excel Spreadsheet with Google Docs" /></a>';
								break;
								case "application/mspowerpoint" :
								case "application/vnd.ms-powerpoint" :
								case "application/vnd.openxmlformats-officedocument.presentationml.presentation" :
									echo '<a href="http://docs.google.com/viewer?url='.urlencode(wp_get_attachment_url($att->ID)).'&embedded=true" class="colorbox" iframe="true"><img src="'. SOMA_IMG . 'point-doc.png" title="Click to view PowerPoint Presentation with Google Docs" /></a>';
								break;
								case "application/zip" :
									echo '<a href="'.wp_get_attachment_url($att->ID).'" target="blank"><img src="'. SOMA_IMG . 'zip-doc.png" /></a>';
								break;
								case "image/jpeg" :
									echo '<a href="'.wp_get_attachment_url($att->ID).'" class="colorbox">'. wp_get_attachment_image($att->ID, 'thumbnail', false, array('title'=>'Click to Zoom'));
								break;
								case "image/jpg" :
									echo '<a href="'.wp_get_attachment_url($att->ID).'" class="colorbox">'. wp_get_attachment_image($att->ID, 'thumbnail', false, array('title'=>'Click to Zoom'));
								break;
							}
						echo '<br />';
						echo '<ul class="meta-attachment-actions">';
							$dl_url = get_option('siteurl') . "?download={$att->ID}&security=" . wp_create_nonce( "soma-download" );
							echo "<li><a href=\"$dl_url\" title=\"Download this file\">Download File</a></li>";
							echo '<li><a class="deletefile" href="#" rel="'.$att->ID.'" title="Delete this file">Delete File</a></li>';
						echo '</ul>';
						echo '</li>';
					endforeach;
					echo '</ul>';
				break;

				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'select':
					echo '<select name="', $field['id'], '" id="', $field['id'], '"', $disable ? ' disabled="disabled"' : null, ' class="meta-select', $complete ? null : $missing, '" >';
					if (is_array($field['options'])) {		// must check if array exists or foreach will throw error
						// if meta is empty (never been set), and there's no default specified, display a 'none' option
						if (!$meta && !$field['default']) {
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
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'radio':
					echo '<ul class="meta-radio', $complete ? null : $missing, '" >';
					if (is_array($field['options'])) {		// must check if array exists or foreach will throw error
						foreach ($field['options'] as $option) {
							// if meta matches, or if the default string matches -- if meta is empty (never been set), and there's no default specified, no radio item will be checked
							if ($meta == $option['value'] || $field['default'] == $option['name']) {
								$select = true;
							} else {
								$select = false;
							}
							echo '<li><label><input type="radio" name="', $field['id'], '" value="', $option['value'], '"', $select ? ' checked="checked"' : '', ' />', $option['name'],'</label></li>';
						}
					} else {
						// if meta isn't array, default to single behaviour
						echo '<li><label><input type="radio" name="', $field['id'], '" value="', $option['value'], '"', $meta == $option['value'] ? ' checked="checked"' : '', ' />', $option['name'],'</label></li>';
					}
					echo '</ul>';
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
					break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'checkbox-single':
					echo '<input type="checkbox" name="', $field['id'], '" id="', $field['id'], '"', $meta ? ' checked="checked"' : null, ' class="meta-checkbox-single', $complete ? null : $missing, '" />';
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'checkbox-multi':
					echo '<ul class="meta-checkbox-multi', $complete ? null : $missing, '" >';
					// echo '<span ', $complete ? null : $missing, '>';
					if (is_array($field['options'])) {		// must check if array exists or foreach will throw error
						foreach ($field['options'] as $option) {
							if (!empty($meta) && in_array($option['value'] , $meta) ) {	// $meta isn't empty, and this option is within it
								echo '<li><label><input type="checkbox" value="', $option['value'],'" name="', $field['id'], '[]" id="check-', $option['value'],'" checked="checked" /><strong>',$option['name'],'</strong></label></li>';
							} else {
								echo '<li><label><input type="checkbox" value="', $option['value'],'" name="', $field['id'], '[]" id="check-', $option['value'], '" />',$option['name'],'</label></li>';
							}
						}
					} else {
						// if meta isn't array, default to checkbox-single behaviour
						echo '<input type="checkbox" name="', $field['id'], '" id="', $field['id'], '"', $meta ? ' checked="checked"' : null, ' class="', $complete ? null : $missing, '" />';
					}
					echo '</ul>';
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
					break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'select-multi':
					echo '<select multiple="multiple" name="', $field['id'], '[]" id="', $field['id'], '" class="meta-select-multi', $complete ? null : $missing, '" >'; // adding brackets to end of select name pass to $_POST as array of selections
					// list values and match
					if (is_array($field['options'])) {		// must check if array exists or second test will throw error
						foreach ($field['options'] as $option) {
							if (!empty($meta) && in_array($option['value'] , $meta) ) {	// $meta isn't empty, and this option is within it
								echo '<option value="', $option['value'], '" selected="selected">', $option['name'],'</option>';
							} else {
								echo '<option value="', $option['value'], '">', $option['name'],'</option>';
							}
						}
					}
					echo '</select>';
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
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
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
				break;
				//  ---------------------------------------------------------------------------------------------------------- //
				// (jqueryUI inputs)
				case 'datepicker':
					if ($meta) {
						$human = date("l, F d, Y", strtotime($meta));	// human readable output
					} else {
						$human = "(none selected)"; 		// default value for altField
					}
					// hidden input that actually holds the data from the jqueryUI picker -- allows us to display a human-readable version in the mirror field
					echo '<input type="hidden" class="datepicker" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['default'], '" >';
					echo '<input type="text" class="datemirror', $complete ? null : $missing, '" readonly="readonly" value="', $human, '"/>';
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
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
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
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
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
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

					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				case 'readonly':
					if (is_array($meta)) {
						echo implode(", ", $meta);
					} else {
						// echo '<strong>', $meta ? $meta : $field['default'], '</strong>';
						echo $meta ? $meta : $field['default'];
					}
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				// displaying related posts as mini thumbs
				case 'p2p-thumbs':
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
					if ($meta) {
						echo '<strong>', $meta ? $meta : $field['default'], '</strong>';
						echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
					} else { // if no relations
						echo '<em>(none)</em>';
					}
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				// inject our own custom tinymce
				case 'richtext':
				// method #1
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] . "<br/>" : null;
					echo '<a role="button" id="post_content_wp_adv" href="javascript:;" class="mceButton mceButtonEnabled mce_wp_adv" onmousedown="return false;" onclick="return false;" title="Show/Hide Kitchen Sink (Alt+Shift+Z)" tabindex="-1"><span class="mceIcon mce_wp_adv"></span>Display</span></a> advanced formatting options</p>'; // cloned from the tinymce button, so the normal kitchen sink button doesn't work now
					the_editor($meta, $field['id'], '', false);

				// method #2
					// echo '<textarea name="', $field['id'], '" id="', $field['id'], '" class="theEditor">', $meta ? $meta : $field['default'], '</textarea>';
					// add_action( 'admin_print_footer_scripts', 'wp_tiny_mce', 25 );
				break;
				// ----------------------------------------------------------------------------------------------------------------------------- //
				// using the core editor ONLY for post_content
				case 'editor':
					echo "<div id=\"", user_can_richedit() ? "postdivrich" : "postdiv" , " class=\"postarea\">";
					the_editor($post->post_content);
					echo "</div>";
					echo $field['desc'] ? "</td></tr>\n<tr>\n<td></td>\n<td class=\"field-desc\">". $field['desc'] : null;
				break;

				case 'media':

					// add_action( 'admin_print_footer_scripts', create_function('', 'wp_enqueue_script( "jplayer" );') );
					// add_action( 'admin_print_scripts', create_function('', 'wp_enqueue_script( "jplayer-inspector" );') );
					// add_action( 'admin_print_styles', create_function('', 'wp_enqueue_style( "jplayer-style" );') );

				// media player output
				$label = $meta ? "Modify Media File" : "Upload Media File";
				echo "<a href=\"media-upload.php?post_id=$post->ID&amp;TB_iframe=1&amp;height=800&amp;width=640\" id=\"add_media\" class=\"thickbox clicker\" onclick=\"return false;\">$label</a>";
				echo $meta ? "" : "<p class=\"howto\">{$field['desc']}</p>";
				// don't attempt to show player if no media exist yet
				if ($meta) {
					$i = 1;
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
						}
						// output jplayer
						echo "<div id=\"jplayer-admin\" class=\"jp-jplayer $type\" rel=\"$url\"></div>";
						if ( $type == 'audio') :  ?>
						
							<script type="text/javascript">
								jQuery(document).ready(function($) {
									// jPlayer media player
									$("#jquery_jplayer_<?php echo $i; ?>").jPlayer({
										ready: function () {
											$(this).jPlayer("setMedia", {
												mp3: "<?php echo $url; ?>",
											});
										},
										swfPath: "<?php echo SOMA_JS; ?>",
										"backgroundColor":"#ff9",
										supplied: "mp3",
									});
								});
							</script>
							<div id="jquery_jplayer_1" class="jp-jplayer"></div>
							<div id="jp_container_1" class="jp-audio">
								<div class="jp-type-single">
									<div class="jp-gui jp-interface">
										<ul class="jp-controls">
											<li><a href="javascript:;" class="jp-play" tabindex="1">play</a></li>
											<li><a href="javascript:;" class="jp-pause" tabindex="1">pause</a></li>
											<li><a href="javascript:;" class="jp-stop" tabindex="1">stop</a></li>
											<li><a href="javascript:;" class="jp-mute" tabindex="1" title="mute">mute</a></li>
											<li><a href="javascript:;" class="jp-unmute" tabindex="1" title="unmute">unmute</a></li>
											<li><a href="javascript:;" class="jp-volume-max" tabindex="1" title="max volume">max volume</a></li>
										</ul>
										<div class="jp-progress">
											<div class="jp-seek-bar">
												<div class="jp-play-bar"></div>
											</div>
										</div>
										<div class="jp-volume-bar">
											<div class="jp-volume-bar-value"></div>
										</div>
										<div class="jp-time-holder">
											<div class="jp-current-time"></div>
											<div class="jp-duration"></div>
											<ul class="jp-toggles">
												<li><a href="javascript:;" class="jp-repeat" tabindex="1" title="repeat">repeat</a></li>
												<li><a href="javascript:;" class="jp-repeat-off" tabindex="1" title="repeat off">repeat off</a></li>
											</ul>
										</div>
									</div>
									<div class="jp-title">
										<ul>
											<li><?php echo $media->post_title ?></li>
										</ul>
									</div>
									<div class="jp-no-solution">
										<span>Update Required</span>
										To play the media you will need to either update your browser to a recent version or update your <a href="http://get.adobe.com/flashplayer/" target="_blank">Flash plugin</a>.
									</div>
								</div>
							</div>
						<?php
						endif;

						if ($type == 'video') : ?>
						
							<script type="text/javascript">
								jQuery(document).ready(function($) {
									// jPlayer media player
									$("#jquery_jplayer_<?php echo $i; ?>").jPlayer({
										ready: function () {
											$(this).jPlayer("setMedia", {
												m4v: "<?php echo $url; ?>",
											});
										},
										swfPath: "<?php echo SOMA_JS; ?>",
										"backgroundColor":"#ff9",
										supplied: "m4v",
									});
								});
							</script>
							<div id="jp_container_<?php echo $i; ?>" class="jp-video ">
								<div class="jp-type-single">
									<div id="jquery_jplayer_<?php echo $i; ?>" class="jp-jplayer" rel=""></div>
									<div class="jp-gui">
										<div class="jp-video-play">
											<a href="javascript:;" class="jp-video-play-icon" tabindex="1">play</a>
										</div>
										<div class="jp-interface">
											<div class="jp-progress">
												<div class="jp-seek-bar">
													<div class="jp-play-bar"></div>
												</div>
											</div>
											<div class="jp-current-time"></div>
											<div class="jp-duration"></div>
											<div class="jp-controls-holder">
												<ul class="jp-controls">
													<li><a href="javascript:;" class="jp-play" tabindex="1">play</a></li>
													<li><a href="javascript:;" class="jp-pause" tabindex="1">pause</a></li>
													<li><a href="javascript:;" class="jp-stop" tabindex="1">stop</a></li>
													<li><a href="javascript:;" class="jp-mute" tabindex="1" title="mute">mute</a></li>
													<li><a href="javascript:;" class="jp-unmute" tabindex="1" title="unmute">unmute</a></li>
													<li><a href="javascript:;" class="jp-volume-max" tabindex="1" title="max volume">max volume</a></li>
												</ul>
												<div class="jp-volume-bar">
													<div class="jp-volume-bar-value"></div>
												</div>
												<ul class="jp-toggles">
													<li><a href="javascript:;" class="jp-full-screen" tabindex="1" title="full screen">full screen</a></li>
													<li><a href="javascript:;" class="jp-restore-screen" tabindex="1" title="restore screen">restore screen</a></li>
													<li><a href="javascript:;" class="jp-repeat" tabindex="1" title="repeat">repeat</a></li>
													<li><a href="javascript:;" class="jp-repeat-off" tabindex="1" title="repeat off">repeat off</a></li>
												</ul>
											</div>
											<div class="jp-title">
												<ul>
													<li><?php echo $media->post_title ?></li>
												</ul>
											</div>
										</div>
									</div>
									<div class="jp-no-solution">
										<span>Update Required</span>
										To play the media you will need to either update your browser to a recent version or update your <a href="http://get.adobe.com/flashplayer/" target="_blank">Flash plugin</a>.
									</div>
								</div>
							</div>
						<?php
						endif;
						echo $field['type'] ? "" : "<em>Must specify a format first!</em>";
						echo "<div id=\"jplayer_inspector\"></div>";
						$i++;
					}
				}
				break;
			}

			// hook to output case-specific metabox types
			do_action('soma_field_type_case', $post, $meta, $field, $complete);

			echo '</td></tr>';
		}
		echo '</table>';
		// hook for insertion after box content
		do_action('after_'.$box['id'], $post);

		// insert save changes button
		if ($meta_box['save']) {
			// saving the post always attempts to publish!!
			echo '<table class="form-table"><tr><td class="field-label"></td><td class="field-data">';
			// echo '<input type="hidden" name="post_status" id="post_status" value="publish" />';

			// outputs generic submit button with $text, $class, $id
			submit_button( $msg, 'clicker save-changes');

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
					$list[] = array('name' => 'Create New', 'value' => 'create');
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
			$list[] = array('name' => $type->label, 'value' => $type->rewrite['slug']);
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