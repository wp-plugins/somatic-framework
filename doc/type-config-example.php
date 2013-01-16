<?php
//** This code exists solely to declare the data for custom metaboxes with the somaticFramework! **//
//** it won't do you much good if you don't have the Somatic Framework installed **//


add_action( 'init', 'mysite_type_data' );
add_action( 'soma_column_data', 'custom_column_data', 10, 2);

function mysite_type_data() {
	// only proceed if framework plugin is active
	if ( !class_exists("somaticFramework") ) return null;

	//
	soma_init_type( array(
		"slug" => "resource",													// primary identifier
		"single" => "Resource",													// used to build labels
		"plural" => "Resources",												// used to build labels
		"args" => array(														// optional argument overrides http://codex.wordpress.org/Function_Reference/register_post_type
				'menu_position' => 5,											// where the admin menu item where sit
				'supports' => array( 'title', 'thumbnail', 'post-formats'),		// which features and core metaboxes to show -- Don't forget to use add_theme_support( 'post-thumbnails' ) when required!
				// non-wp args below
				'sort_by' => 'meta_value',										// how to filter the query when displaying this type - post_date (published), title (post_title), author, parent, modified, rand (random), meta_value, menu_order - which also causes the admin sorting menu to appear
				'sort_key' => 'custommetakey',									// if "meta_value" is indicated for sort_by, must supply a post meta key (which will automatically have the site meta prefix added)
				'sort_order' => 'ASC',											// sorting order (typically need to set this manually to ASC when using menu_order)
				'hide_publish' => true,											// hides the Publish core metabox (make sure you include save buttons in your metabox field config)
				"sort_group_type" => 'taxonomy',								// optional: group listings on the Sort Order subpage by an object type
				"sort_group_slug" => 'format',									// required if sort_group_type is set - which object to group by (in this case, a specific taxonomy)
				"create_nav_item"=> true,										// automatically generate a nav menu item for this type in Appearance->Menus which you can rearrange - NOTE: will re-create the nav item if you manually delete it!
				"blank_slate" => true,											// disables all default metaboxes, so you'll have to declare custom ones. leave out the "supports" arg with this one, and use "hide_publish" if you want the edit page to be totally blank
			),
		"icons" => "http://mysite.com/mytheme/img/",							// set url path to where your custom icons are located
		"columns" => array(														// custom columns for edit.php listing
				"thumb" => "Cover",
				"title" => "Title",
				"authors" => "Authors",
				"content" => "Content Type",
				"format" => "Formats",
				"source" => "Source"

				// DEFAULT WP COLUMN KEYS
				// 'cb' – Post checkbox.
				// 'date' – Date when post was last modified.
				// 'title' – Post title and common post actions including Edit, Quick Edit, Trash, and View.
				// 'categories' – Post categories.
				// 'tags' – Post tags.
				// 'comments' – Number of post comments.
				// 'author' – Post author.
			)
		)
	);



	// enables an existing (or built-in) taxonomy for our custom post type
	register_taxonomy_for_object_type('category', 'resource');


	//

	soma_init_taxonomy( array(
			"slug" => "format",												// primary identifier
			"single" => "Format",											// used to build labels
			"plural" => "Formats",											// used to build labels
			"types" => array( 'resource' ),									// assign taxonomy to which post types
			"args" => array(),												// optional argument overrides
			'terms' => array(												// list of terms to be automatically spawned
				'PDF' => array( 'slug'=> 'pdf'),							// Name => args (slug, description, parent)
				'ePUB' => array( 'slug'=> 'epub'),
				'Book' => array( 'slug'=> 'book'),
				'AudioBook' => array( 'slug'=> 'audiobook'),
				'Mises Store' => array( 'slug'=> 'store'),
				'MP3 Audio' => array( 'slug'=> 'mp3'),
				'MP4 Video' => array( 'slug'=> 'mp4'),
				'Windows Media' => array( 'slug'=> 'wmv'),
				'HTML' => array( 'slug'=> 'html'),
				'Streaming Media' => array( 'slug'=> 'streaming'),
			)
		)
	);

	soma_init_taxonomy( array(
			"slug" => "content",
			"single" => "Content Type",
			"plural" => "Content Types",
			"types" => array( 'resource' ),
			"args" => array( 'hierarchical' => true ),
			"metabox" => false,											// hides the side metabox that WP automatically shows in the post editor (useful when you want to handle this taxonomy in a custom metabox)
			'terms' => array(
				'Book',
				'Journal',
				'Article',
				'Audio',
				'Video',
			)
		)
	);


	// establish relationships
	if ( function_exists( 'p2p_register_connection_type' ) ) {
		p2p_register_connection_type( array(
			'id' => 'authors_to_works',
			'from' => 'author',
			'to' => array( 'books','articles'),
			'sortable' => 'any',
			'admin_column' => 'any',
			'title' => array( 'to' => 'Authored By', 'from' => 'Works by this Author' )
		) );
	}
}


function custom_column_data($column, $pid) {
	// output each column's content
	switch ($column) {
		// p2p connected column output
		case "authors":
			echo somaFunctions::fetch_connected_items($pid, 'authors-works');
		break;
		// taxonomy column output
		case "format":
			echo somaFunctions::fetch_the_term_list( $pid, 'format','',', ');
		break;
		// post_meta column output
		case "notes":
			echo somaFunctions::asset_meta('get', $pid, 'notes');
		break;
		// custom column output
		case "email":
			$email = somaFunctions::asset_meta('get', $pid, 'email');
			echo "<a href=\"mailto:$email\">$email</a>";
		break;
	}
}