<?php
//** This code exists solely to declare the data for custom metaboxes with the somaticFramework! **//
//** it won't do you much good if you don't have the Somatic Framework installed **//


add_action( 'soma_metabox_data_init', 'mysite_metabox_data' );										// define metabox vars only in admin
add_action( 'add_meta_boxes_product', array('somaMetaboxes', 'add_boxes') );						// use our somatic metabox generator for another post type (maybe defined by another plugin)

// add_action('soma_field_type_case', 'media_field_type', 10, 4);
// add_filter('soma_field_fetch_meta', 'fetch_media_field', 10, 3);


//** you should only set these options when activating your plugin/theme **//

// soma_set_option('meta_serialize', 1));			// If you want to store all post_meta items as a serialized array in a single key, you need to set the option 'soma_meta_serialize' to 1 (true) (false (0) by default)
// soma_set_option('meta_prefix', '_myprefix'));	// specify your own post_meta prefix, otherwise default is "_soma"

//**  END options  **//


// outline and inject metaboxes and fields into the somaMetaboxes data container
function mysite_metabox_data($post) {
	if ( !class_exists("somaticFramework") ) return null; // only proceed if framework plugin is active

	// call the framework function for each individual metabox to be rendered
	soma_metabox_data( array(
		'types' => array('tracks'),											// REQUIRED! which post types this metabox is output for
		'id' => 'track-meta-box',											// HTML ID for this container element
		'title' => 'Track Info',											// text displayed in title of metabox
		'context' => 'normal',												// positioning: normal/side columns
		'priority' => 'high',												// positioning: vertical order - if all have same priority, metaboxes are rendered in order they appear in somaMetaboxes::$data array
		'restrict' => false,												// boolean for restricting display of this metabox for non-staff (a special somaticFramework permission class)
		'save' => true,														// boolean for displaying a "save changes" button at the end of this metabox (can have multiple on the page)
		'publish' => true,													// makes the save button always change post_status to publish (instead of keeping it on whatever it was, which means new items are saved as drafts)
		'fields' => array(													// array of individual fields within this metabox
			array(
				'name' => 'Featured Image',
				'id' => 'customid',
				'type' => 'upload-featured',
				'data' => 'featured',
				'width' => 320,
				'height' => 160,
				'allowed' => array('png'),
				'desc' => '',
			),
			array(
				'name' => 'Actor Name',										// text displayed alongside field input
				'id' => 'actors', 											// used when saving, should be the name of the post_meta (key) or taxonomy (exact slug) we're manipulating. NOTE: if using with upload-files, never put a hyphen in the ID string!
				'type' => 'select',											// field type (usually input: text, area, select, checkbox, radio), sometimes output (posts, other readonly data)
				'data' => 'taxonomy',										// what kind of data is being retrieved and saved for this post (meta [wp_postmeta table], core [wp_posts table], taxonomy, user, p2p, attachment, comment)
				'options' => soma_select_taxonomy_terms('actors'),			// array of options to populate html form input objects (in this case, generated automatically from available taxonomy terms)
				'default' => '',											// default value to show (in text fields or selectors)
				'multiple' => false,										// can multiple values be selected? or must the saved value be singular?
				'required' => true											// can this field be left empty or unselected? enables red styling to draw attention (validation functions to check completion don't exist yet)
			),
			array(
				'name' => 'Content',
				'id' => 'post_content',
				'type' => 'richtext',
				'data' => 'core',
			),
			array(
				'name' => 'Description',
				'id' => 'desc',
				'type' => 'text',
				'data' => 'meta',
				'desc' => 'Enter a short description for this item',
			),
			array(
				'name' => 'Title',
				'id' => 'post_title',
				'type' => 'text',
				'data' => 'core',											// note: this replaces the functionality of the core Title metabox, so you don't have to support it when you register custom post types
				'desc' => 'Enter a name for this item',
			),
			array(
				'id' => 'mybutton',
				'type' => 'button',
				'data' => 'link',											// note: unlike other types, this data arg does not fetch anything, but indicates what the button will do ("save" will output a form submit button)
				'options' => array('label' => 'Execute', 'url' => somaFunctions::build_request_link( 'execute-export', $post->ID ) ),
				'desc' => 'Push to execute something',
			),
			array(
				'name' => 'External Media URL',
				'id' => 'media',
				'type' => 'external_media',
				'data' => 'meta',
				'desc' => 'Enter a URL from YouTube, Vimeo, or SoundCloud',
				'required' => true
			),
			array(
				'name' => 'External Image URL',
				'id' => 'image',
				'type' => 'external_image',
				'data' => 'meta',
				'desc' => 'Enter the URL of the image',
			),
			array(
				'name' => 'Upload Stuff',
				'id' => 'customid',											// html tag ID should be unique for each field on the edit page
				'type' => 'upload-files',									// displays plUpload file uploader
				'data' => 'none',											// indicates there is no saved data to be retrieved when displaying this field
				'max' => 10,												// how many items should be allowed to be uploaded
				'width' => 900,												// if indicated, image will be resized to max of this integer, but not cropped
				'height' => 600,											// if indicated, image will be resized to max of this integer, but not cropped
				'allowed' => array('jpg','png'),							// array of permitted file extensions for this instance (defaults to jpg, jpeg, gif, png, mp3)
				'desc' => 'Photos must be 3:2 aspect ratio, in JPG or PNG format',
			),
			array(
				'name' => 'Attached Files',
				'id' => 'current-attachments',
				'type' => 'gallery',
				'data' => 'attachment',
				'desc' => 'These are your currently attachmented files',
			),
		)
	));

}



	// HOOKS FOR SOMATIC FRAMEWORK METABOX SYSTEM

	/**
	* fetch metadata in custom cases
	* hooks into soma_field_fetch_meta filter
	*
    * @access private
    * @param $meta retrieved post metadata
    * @param $post current post object
    * @param $field current metabox field from global $customMetaConfig
    * @return $meta OBJECT
	*/
	function fetch_media_field($meta, $post, $field) {
		// get attached media objects
		if ($field['data'] == 'media') {
			$meta = somaFunctions::fetch_attached_media($post->ID, $field['type']);
		}
		return $meta;
	}

	/**
	* custom metabox field with upload button and media player preview
	* hooks into soma_metabox_type_case action
	* must contain a conditional statement to only output the field when the type matches!
	* echos metabox field content
	*
    * @access private
    * @param $post current post object
    * @param $meta retrieved post metadata (in this case, the object attached to the post)
    * @param $field current metabox field from global $customMetaConfig
    * @param $complete boolean
	*/

	function media_field_type($post, $meta, $field, $complete) {
		if ($field['type'] == "audio" || $field['type'] == "video") {
			$label = $meta ? "Modify Media File" : "Upload Media File";
			echo "<a href=\"media-upload.php?post_id=$post->ID&amp;TB_iframe=1&amp;height=800&amp;width=640\" id=\"add_media\" class=\"thickbox clicker\" onclick=\"return false;\">$label</a>";
			echo $meta ? "" : "<p class=\"howto\">{$field['desc']}</p>";
			// don't attempt to show player if no media exist yet
			if (!$meta) return null;
			// cycle through array of attached media objects
			foreach ($meta as $media) {
				$url = wp_get_attachment_url( $media->ID);
				// output jplayer
				echo "<div id=\"jplayer-admin\" class=\"jp-jplayer\" type=\"{$field['type']}\" rel=\"$url\"></div>";
?>
<div class="jp-audio-container">
	<div class="jp-audio">
		<div class="jp-type-single">
			<div id="jp_interface_1" class="jp-interface">
				<ul class="jp-controls">
					<li><a href="#" class="jp-play" tabindex="1">play</a></li>
					<li><a href="#" class="jp-pause" tabindex="1">pause</a></li>
					<li><a href="#" class="jp-stop" tabindex="1">stop</a></li>
					<li><a href="#" class="jp-mute" tabindex="1">mute</a></li>
					<li><a href="#" class="jp-unmute" tabindex="1">unmute</a></li>
				</ul>
				<div class="jp-progress-container">
					<div class="jp-progress">
						<div class="jp-seek-bar">
							<div class="jp-play-bar"></div>
						</div>
					</div>
				</div>
				<div class="jp-volume-bar-container">
					<div class="jp-volume-bar">
						<div class="jp-volume-bar-value"></div>
					</div>
				</div>
				<!-- not supported in blackyellow skin -->
				<!-- <div class="jp-current-time"></div>  -->
				<!-- <div class="jp-duration"></div>  -->
			</div>
			<div id="jp_playlist_1" class="jp-playlist">
				<ul>
					<li><?php echo $media->post_title; ?></li>
				</ul>
			</div>
		</div>
	</div>
</div>
<?php
				echo $field['type'] ? "" : "<em>Must specify a format first!</em>";
				echo "<div id=\"jplayer_inspector\"></div>";
			}

		}
	}
?>