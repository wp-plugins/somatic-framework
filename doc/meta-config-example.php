<?php
//** This code exists solely to declare the data for custom metaboxes with the somaticFramework! **//
//** it won't do you much good if you don't have the Somatic Framework installed **//


add_action('admin_init', 'my_meta_box_data' );								// define metabox vars only in admin

// add_action('soma_field_type_case', 'media_field_type', 10, 4);
// add_filter('soma_field_fetch_meta', 'fetch_media_field', 10, 3);


//** you should only set these options when activating your plugin/theme **//

// update_option('soma_meta_serialize', false);			// If you want to store all post_meta items as a serialized array in a single key, you need to set the option 'soma_meta_serialize' to true (false by default)
// update_option('soma_meta_prefix', '_soma');			// this is the default prefix added to all post_meta keys. Change if desired.

//**  END options  **//



// outline and inject metaboxes and fields into the somaMetaboxes data container
function meta_box_data() {
	if ( !class_exists("somaticFramework") ) return null; // only proceed if framework plugin is active

	// call the framework function for each individual metabox to be rendered
	soma_metabox_data( array(
		'id' => 'track-meta-box',
		'title' => 'Track Info',											// text displayed in title of metabox
		'types' => array('tracks'),											// which post types this metabox is output for
		'context' => 'normal',												// positioning: normal/side columns
		'priority' => 'high',												// positioning: vertical order - if all have same priority, metaboxes are rendered in order they appear in somaMetaboxes::$data array
		'restrict' => false,												// boolean for restricting display of this metabox for non-staff (a special somaticFramework permission class)
		'save' => true,														// boolean for displaying a "save changes" button at the end of this metabox (can have multiple on the page)
		'fields' => array(													// array of individual fields within this metabox
			array(
				'name' => 'Actor Name',										// text displayed alongside field input
				'id' => 'actor_name', 										// used when saving, should be the name of the post_meta (key) or taxonomy (exact slug) we're manipulating
				'type' => 'text',											// field type (usually input: text, area, select, checkbox, radio), sometimes output (posts, other readonly data)
				'data' => 'meta',											// what kind of data is being retrieved and saved for this post (meta [wp_postmeta table], core [wp_posts table], taxonomy, user, p2p, attachment, comment)
				'options' => soma_select_taxonomy_terms('artists'),			// array of options to populate html form input objects (in this case, generated automatically from available taxonomy terms)
				'default' => '',											// default value to show (in text fields or selectors)
				'multiple' => false,										// can multiple values be selected? or must the saved value be singular?
				'required' => true											// can this field be left empty or unselected? enables red styling to draw attention (validation functions to check completion don't exist yet)
			),
		)
	));
	
	soma_metabox_data( array(
		'id' => 'resource-meta-box',
		'title' => 'Metadata',
		'types' => array('resource'),
		'context' => 'normal',
		'priority' => 'high',
		'restrict' => false,
		'save' => true,
		'fields' => array(
			array(
				'name' => 'Content Type',									// Label for this field
				'id' => 'content',											// id matching the taxonomy slug we're manipulating
				'type' => 'select',											// renders dropdown input selector
				'options' => soma_select_taxonomy_terms('content'),
				'data' => 'taxonomy',
				'multiple' => false,
			),
			array(
				'name' => 'Author(s)',
				'id' => 'author-list',
				'type' => 'p2p-list',
				'data' => 'p2p',
				'p2pname' => 'authors-works',
				'dir' => 'to',
			),
			array(
				'name' => 'Publication Info',
				'id' => 'publication',
				'type' => 'text',
				'data' => 'meta',
				'desc' => 'When was this published and by whom?',
			),
			array(
				'name' => 'SKU#',
				'id' => 'sku',
				'type' => 'numeric',
				'data' => 'meta',
				'desc' => 'Is this item sold in the Mises Store? Enter SKU for a link to be generated automatically',
			),
			array(
				'name' => 'ISBN',
				'id' => 'isbn',
				'type' => 'numeric',
				'data' => 'meta',
				'desc' => 'If this is a printed book, please include ISBN',
			),
			array(
				'name' => 'Subjects',
				'id' => 'subject',
				'type' => 'checkbox-multi',
				'options' => somaMetaboxes::select_taxonomy_terms('subject'),
				'data' => 'taxonomy',
				'multiple' => true,
			),
			array(
				'name' => 'Collection',
				'id' => 'collection',
				'type' => 'select',
				'options' => somaMetaboxes::select_taxonomy_terms('collection'),
				'data' => 'taxonomy',
				'multiple' => true,
			),
			array(
				'name' => 'Source',
				'id' => 'source',
				'type' => 'select',
				'options' => somaMetaboxes::select_taxonomy_terms('source'),
				'data' => 'taxonomy',
				'multiple' => false,
				'required' => true
			),
			array(
				'name' => 'Streaming Media URL',
				'id' => 'streaming-media',
				'type' => 'text',
				'data' => 'meta',
				'desc' => 'Enter link to YouTube, Vimeo, iTunes, etc.',
			),
			array(
				'name' => 'Description',
				'id' => 'post_content',
				'type' => 'richtext',
				'rows' => 30,
				'data' => 'core',
				'desc' => 'This is the content that will display on the resource page',
			),
			array(
				'name' => 'Excerpt',
				'id' => 'post_excerpt',
				'type' => 'textarea',
				'data' => 'core',
				'desc' => 'Excerpts are optional hand-crafted summaries that may be used when space is tight',
			),
			array(
				'name' => 'HTML Content',
				'id' => 'html',
				'type' => 'html',
				'rows' => 30,
				'data' => 'meta',
				'desc' => 'If this resource is available in HTML format, paste it here',
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
		// get attached media object for this post (only returns one)
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