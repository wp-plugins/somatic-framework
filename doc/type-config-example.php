<?php
//** This code exists solely to declare the data for custom metaboxes with the somaticFramework! **//
//** it won't do you much good if you don't have the Somatic Framework installed **//


add_action( 'init', 'init' );
add_filter( 'parse_query', 'filter_custom_query' );
add_filter( 'p2p_connectable_args', 'order_authors_by_title', 10, 2 );
add_action( 'soma_column_data', 'custom_column_data', 10, 2);

function init() {
	// only proceed if framework plugin is active
	if ( !class_exists("somaticFramework") ) return null;


	//
	soma_init_type( array(
		"slug" => "resource",													// primary identifier
		"single" => "Resource",													// used to build labels
		"plural" => "Resources",												// used to build labels
		'args' => array(														// optional argument overrides http://codex.wordpress.org/Function_Reference/register_post_type
				'menu_position' => 5,											// where the admin menu item where sit
				'supports' => array( 'title', 'thumbnail'),						// which features and core metaboxes to show
				'public' => true,
			),
		'icons' => MOCMS_IMG,
		"columns" => array(														// custom columns for edit.php listing
				"cb" => "<input type=\"checkbox\" />",
				"thumb" => "Cover",
				"title" => "Title",
				"authors" => "Authors",
				"content" => "Content Type",
				"format" => "Formats",
				"source" => "Source"
			)
		)
	);

	//
	soma_init_type( array(
		"slug" => "author",
		"single" => "Author",
		"plural" => "Authors",
		'args' => array(
				'menu_position' => 6,
				'supports' => array( 'editor', 'title', 'thumbnail' ),
				'public' => true,
			),
		'icons' => MOCMS_IMG,
		"columns" => array(
				"cb" => "<input type=\"checkbox\" />",
				"thumb" => "Photo",
				"title" => "Name",
				"email" => "Email",
			)
		)
	);
	
	//
	soma_init_type( array(
		"slug" => "daily",
		"single" => "Daily Article",
		"plural" => "Daily Articles",
		'args' => array(
				'menu_position' => 7,
				'supports' => array( 'comments', 'title', 'thumbnail', 'excerpt', 'editor' ),
				'public' => true,
			),
		'icons' => MOCMS_IMG,
		"columns" => array(
				"cb" => "<input type=\"checkbox\" />",
				"thumb" => "Thumb",
				"title" => "Article Title",
				"resourceauthor" => "Author",
				"author" => "Editor",
				"date" => "Date"
			)
		)
	);


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
	
	soma_init_taxonomy( array(
			"slug" => "subject",
			"single" => "Subject",
			"plural" => "Subjects",
			"types" => array( 'resource', 'daily' ),
			"args" => array( 'hierarchical' => true ),
			"metabox" => false,
			'terms' => array(
				'Socialism',
				'Capitalism',
				'Taxation',
				'Core',
				'Biography',
				'Government',
				'Monetary Policy'
			)
		)
	);
	
	soma_init_taxonomy( array(
			"slug" => "keyword",
			"single" => "Keyword",
			"plural" => "Keywords",
			"types" => array( 'resource', 'daily' ),
			"args" => array( 'hierarchical' => false ),
		)
	);
	
	
	soma_init_taxonomy( array(
			"slug" => "collection",
			"single" => "Collection",
			"plural" => "Collections",
			"types" => array( 'resource' ),
			"args" => array( 'hierarchical' => true ),
			"metabox" => false,
			'terms' => array(
				'Mises University',
				'Austrian Scholars Conference',
				'Mises Circle Seminars',
				'Lecture Series',
				'Beginner\'s Guide',
				'Mises Academy'
			)
		)
	);
	
	
	soma_init_taxonomy( array(
			"slug" => "source",
			"single" => "Source",
			"plural" => "Sources",
			"types" => array( 'resource' ),
			"args" => array( 'hierarchical' => true ),
			"metabox" => false,
			'terms' => array(
				'Quarterly Journal of Austrian Economics',
				'The Freeman',
				'Libertarian Papers',
				'Journal of Libertarian Studies',
				'Austrian Economics Newsletter',
				'American Affairs',
			)
		)
	);

	// establish relationships
	
	if ( !function_exists( 'p2p_register_connection_type' ) )
		return;

	p2p_register_connection_type( array(
		'id' => 'authors-works',
		'from' => 'author',
		'to' => array( 'resource','daily'),
		'sortable' => 'any',
		'title' => array( 'to' => 'Authored By', 'from' => 'Works by this Author' )
	) );

	p2p_register_connection_type( array(
		'id' => 'daily-resources',
		'from' => 'daily',
		'to' => array('resource'),
		'title' => array( 'to' => 'Used in Daily Articles', 'from' => 'Resource References' ),
		'can_create_post' => false
	) );

	
}

function custom_column_data($column, $post) {
	// output each column's content
	switch ($column) {
		case "authors":
			echo somaFunctions::fetch_connected_items($post->ID, 'authors-works');
		break;
		case "format":
			echo somaFunctions::fetch_the_term_list( $post->ID, 'format','',', ');
		break;
		case "source":
			echo somaFunctions::fetch_the_term_list( $post->ID, 'source','',', ');
		break;
		case "content":
			echo somaFunctions::fetch_the_term_list( $post->ID, 'content','',', ');
		break;
		case "notes":
			echo somaFunctions::asset_meta('get', $post->ID, 'notes');
		break;
		case "institution":
			echo somaFunctions::asset_meta('get', $post->ID, 'institution');
		break;
		case "email":
			$email = somaFunctions::asset_meta('get', $post->ID, 'email');
			echo "<a href=\"mailto:$email\">$email</a>";
		break;
	}
}


// modifies the QUERY before output
function filter_custom_query($query) {
	// sort our custom types and taxonomies by menu_order instead of date, so we can manually adjust their display order with somaSorter
	if ( $query->query['post_type'] == 'tracks'
		|| isset( $query->query['genres'] )
		|| isset( $query->query['album'] )
		|| isset( $query->query['formats'] )
		|| isset( $query->query['artists'] )
		&& !$query->query_vars['suppress_filters']) {
		$query->set( 'orderby', 'menu_order' );
		$query->set( 'order', 'ASC' );
	}
	return $query;
}

// modify p2p lists of related authors to sort alphabetically rather than default chronological
function order_authors_by_title( $args, $ctype ) {
	if ( 'authors_to_dailys' == $ctype->id ) {
		$args['orderby'] = 'title';
		$args['order'] = 'asc';
	}
	return $args;
}