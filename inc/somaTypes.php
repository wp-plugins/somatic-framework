<?php

class somaTypes extends somaticFramework {

	function __construct() {
		add_action( 'init', array( __CLASS__, 'soma_init' ) );
		add_action( 'admin_head', array( __CLASS__, 'custom_type_icons' ) );
		add_action( 'admin_head', array( __CLASS__, 'column_styles' ) );
		add_filter( 'nav_menu_css_class', array( __CLASS__, 'fix_nav_classes' ), 10, 3 );
		add_filter( 'post_updated_messages', array( __CLASS__, 'custom_type_messages' ) );
		add_action( 'contextual_help', array( __CLASS__, 'custom_type_help_text' ), 10, 3 );
		add_action( 'right_now_content_table_end' , array( __CLASS__, 'custom_types_rightnow' ) );
		add_action( 'admin_head-nav-menus.php', array( __CLASS__, 'filters_for_cpt_archives' ) );    // hacks the output of CPT nav menu items displayed in Appearance -> Menus
		add_filter( 'hidden_meta_boxes', array( __CLASS__, 'show_cpt_menus' ), 10, 2 );       // hacks the display of CPT in Appearance -> Menus (screen options hack)
		add_filter( 'parse_query', array( __CLASS__, 'filter_current_query' ) );         // modifies ordering when querying CPTs
		add_filter( 'posts_orderby', array( __CLASS__, 'posts_orderby' ) );          // modifies ordering when querying CPTs
		add_filter( 'posts_join', array( __CLASS__, 'posts_join' ) );            // modifies ordering when querying CPTs
		add_action( 'add_meta_boxes', array( __CLASS__, 'remove_publish_box' ) );         // hides the core Publish metabox if CPT argument is true
	}

	//** CUSTOM POST TYPES -----------------------------------------------------------------------------------------------------//

	// global storage container for custom post type data (used in functions outside init_type)
	public static $type_data = array();

	// allows other plugins to hook init when registering custom post types (even though they might load before this plugin)
	public function soma_init() {
		do_action('soma_init');
	}

	// assembles and generates a custom post type  --  http://codex.wordpress.org/Function_Reference/register_post_type
	public function init_type( $data ) {

		// push to internal variables for convenience
		$slug = $data['slug'];
		$single = $data['single'];
		if ( somaFunctions::is_blank( $data['plural'] ) ) {
			$plural = $data['single'] . "s";
		} else {
			$plural = $data['plural'];
		}

		// generate labels
		$labels = array(
			'name' => _x( $plural, 'post type general name' ),
			'singular_name' => _x( $single, 'post type singular name' ),
			'add_new' => _x( 'Add New', $single ),
			'add_new_item' => __( 'Create A New '.$single ),
			'edit_item' => __( 'Edit '. $single ),
			'edit' => _x( 'Edit', $single ),
			'new_item' => __( 'New '.$single ),
			'view_item' => __( 'View '.$single ),
			'search_items' => __( 'Search '.$plural ),
			'not_found' =>  __( 'No '.$plural.' found' ),
			'not_found_in_trash' => __( 'No '.$plural.' found in Trash' ),
			'view' =>  __( 'View '.$single )
		);


		// generate args
		$default_args = array(
			'public' 				=> true,            //wp
			'publicly_queryable' 	=> true,			//wp
			'show_ui' 				=> true,			//wp
			'has_archive' 			=> $slug,			//wp
			'hierarchical' 			=> false,			//wp - allows parent to be specified
			'query_var' 			=> true,			//wp
			'show_in_nav_menus' 	=> true,			//wp (default: value of 'public' arg)
			'supports' 				=> array( 'title', 'editor', 'thumbnail' ),		//wp -- pass false to disable all core metaboxes, including title and editor
			'rewrite' 				=> array( 'slug' => $slug, 'with_front' => false ), //wp
			'register_meta_box_cb' 	=> array( 'somaMetaBoxes', 'add_boxes' ), //wp
			'labels' 				=> $labels,			//wp
			'menu_icon' 			=> null,  			//wp - defaults to posts icon

			// non-core args (but get stored in the post type object)
			'somatic' 				=> true,			//soma - flag that this CPT was created with this framework (helps when dealing with mixed sources of CPTs)
			'sort_by' 				=> 'post_date',		//soma - how to filter the query when displaying this type - post_date, post_title, post_author, meta_value, menu_order - which also causes the admin sorting menu to appear
			'sort_order' 			=> 'DESC',			//soma - which direction to sort (wp default is DESC, but when manually choosing sort order, usually want to set this to ASC)
			'sort_menu' 			=> true,			//soma - (sort_by: manual) whether to show a sorting admin submenu for this type
			'sort_group_type' 		=> null,			//soma - (sort_by: manual) what kind of object to use for determining grouping: taxonomy, author, other? p2p would be nice here...
			'sort_group_slug' 		=> null,			//soma - (sort_by: manual) string: slug of the taxonomy/author/object to group list items by
			'create_nav_item' 		=> true,			//soma - automatically generate a nav menu item for this type - NOTE: will re-create it if you manually delete the nav item!
			'hide_publish' 			=> false,			//soma - hide the Publish core metabox (make sure you include save buttons on in your metabox field config!)
			'blank_slate' 			=> false,			//soma - disable all the core wp metaboxes. better make sure to declare some custom ones, or the edit page will be empty...
			'icon_set'				=> 'document',		//soma - loads set of icons for menu, page headers
		);

		// merge with incoming register cpt args
		$args = wp_parse_args( $data['args'], $default_args );


		// menu icon from built-in set or custom path
		if ( !somaFunctions::fetch_index($data, 'custom_icon_path') ) {
			$args['menu_icon'] = SOMA_IMG . "cpt-icons/" . $args['icon_set'] . '-menu-icon.png';
		} else {
			$args['menu_icon'] = $data['custom_icon_path'] . $slug . '-menu-icon.png';
			unset($args['icon_set']);
		}

		// special case for when we don't want ANY core wp metaboxes
		if ( somaFunctions::fetch_index( $data['args'], 'blank_slate' ) ) {
			$args['supports'] = false;
		}

		// in order to take advantage of hierarchical post types, need to be able to select parent, thus we need the attributes metabox...
		// ACTUALLY, don't always want to do this, as we could be using a custom field and "simple page ordering" instead of the attributes metabox...
		// if ( somaFunctions::fetch_index( $data['args'], 'hierarchical' ) == true ) {
		// 	$args['supports'][] = 'page-attributes';
		// }

		// create the post-type
		$result = register_post_type( $slug, $args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// create a nav menu item for this type
		if ( $args['create_nav_item'] ) {
			if ( is_admin() ) {         // cut down on number of times we check, but it does mean won't create if we don't visit admin at least once
				$existargs = array(
					'name' => $slug,
					'post_type' => 'nav_menu_item',
					'showposts' => 1,
					'post_status' => 'publish'
				);
				$exists = get_posts( $existargs );    // check if we've already made one...
				if ( empty( $exists ) ) {
					$archive_url = get_post_type_archive_link( $slug );
					$menu_item_data = array(
						'menu-item-object' => 'custom',
						'menu-item-type' => 'custom',
						'menu-item-title' => $labels['name'],
						'menu-item-url' => $archive_url,
						'menu-item-status' => 'publish',
					);
					$menus = wp_get_nav_menus();
					if ( !empty( $menus ) ) {
						$menu_id = $menus[0]->term_id;
						wp_update_nav_menu_item( $menu_id, 0, $menu_item_data );
						flush_rewrite_rules();
					}
				}
			}
		}

		// store cpt data for later
		self::$type_data[$slug] = $data;

		// custom list columns
		add_filter( "manage_edit-".$slug."_columns", array( __CLASS__, "custom_list_columns" ), 10 );
		add_action( "manage_".$slug."_posts_custom_column", array( __CLASS__, 'custom_column_data' ), 10, 2 );
		return $result;
	}


	//** EDIT.PHP LIST COLUMNS -----------------------------------------------------------------------------------------------------//


	// custom column items and order
	// columns retrieved from $type_data container
	function custom_list_columns( $columns ) {
		global $post_type;
		if ( isset( self::$type_data[$post_type]['columns'] ) ) {       				// custom columns defined? if there are any, we're going to replace WP default ones completely...
			$first = array( "cb" => "<input type=\"checkbox\" />" );     				// we always want to show the checkbox in the first column for bulk actions
			$columns = array_merge( $first, self::$type_data[$post_type]['columns'] ); 	// combine checkbox with the user-defined columns
		}
		return $columns;
	}

	// custom column item content generation
	function custom_column_data( $column, $pid ) {
		global $post, $post_type;
		// output each column's content
		switch ( $column ) {
		case "thumb":
			$img = somaFunctions::fetch_featured_image( $pid );
			$edit = get_edit_post_link( $pid );
			$view = get_permalink( $pid );
			$output = "<a href=\"$edit\"><img src=\"{$img['thumbnail']['url']}\" width=\"".SOMA_THUMB_WIDTH."\" height=\"".SOMA_THUMB_WIDTH."\"/></a>";
			echo $output;
			break;
			// EXAMPLE of listing taxonomy terms
			// case "artists":
			//  echo somaFunctions::fetch_the_term_list( $post->ID, 'artists','',', ');
			// break;

			/* core columns
			cb
				Checkbox for bulk actions.
			title
				Post title.
				Includes "edit", "quick edit", "trash" and "view" links. If $mode (set from $_REQUEST['mode']) is 'excerpt', a post excerpt is included between the title and links.
			author
				Post author.
			categories
				Categories the post belongs to.
			tags
				Tags for the post.
			comments
				Number of pending comments.
			date
				The date and publish status of the post.
			*/
		}
		do_action( 'soma_column_data', $column, $pid );
	}

	function column_styles() {
		$width = get_option( 'thumbnail_size_w' );
		if (intval($width) > 200) $width = '200';
		echo "\n<style>.wp-list-table .column-thumb { width:".$width."px; }</style>";
	}

	//** CUSTOM TAXONOMY AND TERMS -----------------------------------------------------------------------------------------------------------//

	public function init_taxonomy( $data ) {
		$slug = $data['slug'];
		$single = $data['single'];
		if ( somaFunctions::is_blank( $data['plural'] ) ) {
			$plural = $data['single'] . "s";
		} else {
			$plural = $data['plural'];
		}

		$metabox = somaFunctions::fetch_index( $data, 'metabox' );
		if (is_null($metabox)) $metabox = true;   						// default to showing metabox (must pass false exactly)
		$types = somaFunctions::fetch_index( $data, 'types' );
		if (is_null($types)) new WP_Error( 'missing', 'can\'t declare taxonomy without post types...' );

		$default_args = array(
			'hierarchical' => true,   // category vs. tag style
			'query_var' => true,
			'public' => true,
			'show_ui' => true,
			'show_in_nav_menus' => true,
			'show_admin_column' => true,   // new in 3.5, sets up taxonomy column - but will only show up if no custom columns have been defined for the post type!
			'rewrite' => array(
				'slug' => $slug,
				'with_front' => false,
				'hierarchical' => true
			),
			'labels' => array(
				'name' => $plural,
				'singular_name' => $single,
				'add_new_item' => 'Add New '.$single,
				'search_items' => 'Search '.$plural,
				'popular_items' => 'Popular '.$plural,
				'all_items' => 'All '.$plural,
				'edit_item' => 'Edit '.$plural,
				'new_item_name' => 'New '.$single.' Name',
				'choose_from_most_used' => 'Choose from most used '.strtolower( $plural ),
				'add_or_remove_items' => 'Add or remove '.strtolower( $plural ),
				'separate_items_with_commas' => 'Separate '.strtolower( $plural ).' with commas'
			),
			'somatic' => true,			//soma - flag that this taxonomy was created with this framework
		);

		// option to obscure the taxonomy slug in the permalink structure by changing it's slug to be the same as the custom post type it's registered for. NOTE: this only works if the taxonomy has only been assigned to ONE post type
		// resulting permalink looks like  domain.com/customposttype/taxonomyterm
		// ex: domain.com/cars/red  instead of domain.com/cars/colors/red
		// http://wordpress.stackexchange.com/questions/5308/custom-post-types-taxonomies-and-permalinks
		// not working yet...
		// if (count($types) == 1) {
		//  $default_args['rewrite'] => array( 'slug' => $types[0] );
		// }

		$args = wp_parse_args( $data['args'], $default_args );
		// do it
		register_taxonomy( $slug, $types, $args );


		// pre-populate included terms - as this public function could be called from external plugins or themes, have to check for both cases and only run once...
		// really wish we could do this only on register_activation_hook, but can't combine that hook with init hook that this function uses...
		// maybe in the future could inject register_activation_hook within this function...

		if ( !empty( $data['terms'] ) ) {
			global $pagenow;
			// execute only upon plugin activation -- NOTE this will return true anytime we're activating ANY plugin - still better than executing every page load....
			if ( is_admin() && soma_fetch_index($_GET,'action') == "activate" && $pagenow == "plugins.php" ) {
				self::term_generator( $slug, $data['terms'] ); // populate terms
			}

			// execute only upon theme activation -- NOTE this will return true anytime we're activating ANY theme - still better than executing every page load....
			if ( is_admin() && 'themes.php' == $pagenow && isset( $_GET['activated'] ) ) {
				self::term_generator( $slug, $data['terms'] ); // populate terms
			}
		}

		// hide metabox on post editor?
		if ( $metabox == false ) {
			// build metabox ID name
			if ( $args['hierarchical'] ) {
				$box = $slug . "div";
			} else {
				$box = "tagsdiv-" . $slug;
			}
			// remove for each post type
			foreach ( $types as $type ) {
				add_action( 'add_meta_boxes', create_function( '', "
					remove_meta_box( \"$box\", \"$type\", \"side\" );
				" ) );
			}
		}
	}

	// makes terms, either from flat array(term, term, term) - or array(term => array( slug => foo))
	function term_generator( $taxonomy, $terms ) {
		foreach ( $terms as $term => $args ) {
			if ( !is_array( $args ) ) {  // no args array was given, so $args holds the term and $term appears as an array index number
				$term = $args;
				$args = array( 'slug'=>sanitize_title( $term ) );
			}
			if ( !term_exists( $args['slug'], $taxonomy ) ) {
				wp_insert_term( $term, $taxonomy, $args );
			}
		}
	}


	//** MISC CUSTOMIZATIONS ------------------------------------------------------------------------------------------------------------------------------------//

	// inject inline CSS to display custom admin menu icons for custom post types
	// images should be named "slug-add-icon.png" and be 32x32px and placed in directory defined as "icons" in init_type()
	function custom_type_icons() {
		global $pagenow, $post_type;
		if (!$post_type) $post_type = somaFunctions::fetch_index($_GET, 'post_type');

		// abort if custom post type hasn't been defined for whatever type we're viewing
		if ( !somaFunctions::fetch_index(self::$type_data, $post_type ) ) return null;

		// built-in icon sets or custom path
		if ( !somaFunctions::fetch_index(self::$type_data[$post_type], 'custom_icon_path' ) ) {
			$obj = get_post_type_object($post_type);
			$url = SOMA_IMG . "cpt-icons/" . $obj->icon_set;
		} else {
			$url = self::$type_data[$post_type]['custom_icon_path'] . $post_type;
		}

		$sortpage = "sort-" . $post_type;

		switch (true) {
			case ( $pagenow == 'post-new.php' ) :
				echo '<style>#wpbody-content .icon32 { background: transparent url("'. $url .'-add-icon.png") no-repeat; !important }</style>';
			break;
			case ( $pagenow == 'post.php' || somaFunctions::fetch_index($_GET, 'page' ) == $sortpage ) :
				echo '<style>#wpbody-content .icon32 { background: transparent url("'. $url .'-edit-icon.png") no-repeat; !important }</style>';
			break;
			case ( $pagenow == 'edit.php' ) :
				echo '<style>#wpbody-content .icon32 { background: transparent url("'. $url .'-list-icon.png") no-repeat; !important }</style>';
			break;
		}
	}

	// adds 'current' highlighting classes to nav menu items (otherwise missing for custom post )
	// props to Kevin Langley http://profiles.wordpress.org/users/kevinlangleyjr
	function fix_nav_classes( $classes, $item, $args ) {
		global $wp_query;
		$post_type = $wp_query->query_vars[ 'post_type' ];
		$posts_page = get_option( 'page_for_posts', true );
		if ( $item->object_id == $posts_page && ( $post_type != 'post' && $post_type != '' ) ) {
			$remove_array = array( 'current_page_parent', 'current_page_item', 'current-menu-item' );
			foreach ( $remove_array as $remove ) {
				$class_index = array_search( $remove, $classes );
				if ( $class_index ) {
					unset( $classes[ $class_index ] );
				}
			}
		}
		if ( $post_type != '' ) {
			$post_type_url = get_post_type_archive_link( $post_type );
			$check = strpos( $post_type_url, $item->url );
			if ( $check !== false && $check == 0 && $item->url != trailingslashit( network_site_url() ) ) {
				$classes[] = 'current_page_parent';
				$classes[] = 'current_page_item';
				$classes[] = 'current-menu-item';
			}
		}
		return $classes;
	}


	// add custom messages to post_updated_messages
	function custom_type_messages( $messages ) {
		global $post, $post_ID;
		foreach ( self::$type_data as $type => $vars ) {
			$single = $vars['single'];
			$messages[$type] = array(
				0 => '', // Unused
				1 => sprintf( __( $single.' updated. <a href="%s">View '.$single.'</a>' ), esc_url( get_permalink( $post_ID ) ) ),
				2 => __( 'Custom field updated.' ),
				3 => __( 'Custom field deleted.' ),
				4 => __( $single.' updated.' ),
				/* translators: %s: date and time of the revision */
				5 => isset( $_GET['revision'] ) ? sprintf( __( $single.' restored to revision from %s' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				6 => sprintf( __( $single.' published. <a href="%s">View '.$single.'</a>' ), esc_url( get_permalink( $post_ID ) ) ),
				7 => __( $single.' saved.' ),
				8 => sprintf( __( $single.' submitted. <a target="_blank" href="%s">Preview '.$single.'</a>' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
				9 => sprintf( __( $single.' scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview '.$single.'</a>' ),
					// translators: Publish box date format, see http://php.net/date
					date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_ID ) ) ),
				10 => sprintf( __( $single.' draft updated. <a target="_blank" href="%s">Preview '.$single.'</a>' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
			);
		}
		return $messages;
	}


	// display contextual help for custom post types
	function custom_type_help_text( $contextual_help, $screen_id, $screen ) {
		// $contextual_help .= var_dump( $screen ); // use this to help determine $screen->id
		$type = somaFunctions::fetch_index( self::$type_data, $screen->post_type );
		if ( $type != false ) {           // see if a custom post type has been defined for the current screen display
			if (  somaFunctions::fetch_index( $type, 'help' ) ) {
				$contextual_help = self::$type_data[ $screen->post_type ][ 'help' ];
			}
		}
		return $contextual_help;
	}



	/**
	 * Kills post types!
	 *
	 * Usage for a custom post type named 'movies':
	 * disable_post_type( 'movies' );
	 *
	 * Usage for the built in 'post' post type:
	 * disable_post_type( 'post', 'edit.php' );
	 */
	public function disable_post_type( $post_type, $slug = '' ) {
		global $wp_post_types;
		global $remove_slug;
		if ( isset( $wp_post_types[ $post_type ] ) ) {
			unset( $wp_post_types[ $post_type ] );
			$remove_slug = ( !$slug ) ? 'edit.php?post_type=' . $post_type : $slug;
			add_action( 'admin_menu', 'remove_page' );
			function remove_page() {
				global $remove_slug;
				remove_menu_page( $remove_slug );
			}
		}
	}


	// Output stats for custom types and taxonomies in the Right Now dashboard panel
	function custom_types_rightnow() {
		$args = array(
			'public' => true ,
			'somatic' => true,			// only return custom types and taxonomies declared by somatic framework
			'_builtin' => false,
		);
		$output = 'object';
		$operator = 'and';
		$post_types = get_post_types( $args , $output , $operator );
		foreach ( $post_types as $post_type ) {
			$num_posts = wp_count_posts( $post_type->name );
			$num = number_format_i18n( $num_posts->publish );
			$text = _n( $post_type->labels->singular_name, $post_type->labels->name , intval( $num_posts->publish ) );
			if ( current_user_can( 'edit_posts' ) ) {
				$num = "<a href='edit.php?post_type=$post_type->name'>$num</a>";
				$text = "<a href='edit.php?post_type=$post_type->name'>$text</a>";
			}
			echo '<tr><td class="first b b-' . $post_type->name . '">' . $num . '</td>';
			echo '<td class="t ' . $post_type->name . '">' . $text . '</td></tr>';
		}
		$taxonomies = get_taxonomies( $args , $output , $operator );
		foreach ( $taxonomies as $taxonomy ) {
			$num_terms  = wp_count_terms( $taxonomy->name );
			$num = number_format_i18n( $num_terms );
			$text = _n( $taxonomy->labels->singular_name, $taxonomy->labels->name , intval( $num_terms ) );
			if ( current_user_can( 'manage_categories' ) ) {
				$num = "<a href='edit-tags.php?taxonomy=$taxonomy->name'>$num</a>";
				$text = "<a href='edit-tags.php?taxonomy=$taxonomy->name'>$text</a>";
			}
			echo '<tr><td class="first b b-' . $taxonomy->name . '">' . $num . '</td>';
			echo '<td class="t ' . $taxonomy->name . '">' . $text . '</td></tr>';
		}
	}

	// generate filter hooks for adding cpt archive checkboxes
	// props to Kevin Langley https://github.com/klangley/cpt-archive-to-nav
	function filters_for_cpt_archives() {
		$post_type_args = array(
			'show_in_nav_menus' => true
		);

		$post_types = get_post_types( $post_type_args, 'object' );

		foreach ( $post_types as $post_type ) {
			if ( $post_type->has_archive ) {
				add_filter( 'nav_menu_items_' . $post_type->name, array( __CLASS__, 'add_cpt_archive_checkbox' ), null, 3 );
			}
		}
	}

	// inject a new checkbox item in Apppearance -> Menus -> (your cpt) visible in the "view all" tab --- so you can manually add a custom nav menu item for your CPT archive pages
	// props to Kevin Langley https://github.com/klangley/cpt-archive-to-nav
	function add_cpt_archive_checkbox( $posts, $args, $post_type ) {
		global $_nav_menu_placeholder, $wp_rewrite;
		$_nav_menu_placeholder = ( 0 > $_nav_menu_placeholder ) ? intval( $_nav_menu_placeholder ) - 1 : -1;

		array_unshift( $posts, (object) array(
				'ID' => 0,
				'object_id' => $_nav_menu_placeholder,
				'post_content' => '',
				'post_excerpt' => '',
				'post_title' => $post_type['args']->labels->name,
				'post_name' => $post_type['args']->name,
				'post_type' => 'nav_menu_item',
				'type' => 'custom',
				'url' => get_post_type_archive_link( $args['post_type'] )
			) );

		return $posts;
	}

	// shows the metabox listing the custom post type or taxonomy in Appearance->Menus
	function show_cpt_menus( $boxes, $screen ) {
		$args = array( '_builtin' => false, 'show_in_nav_menus'=> true );
		$types = array_keys( get_post_types( $args ) );
		$taxes = array_keys( get_taxonomies( $args ) );
		$list = array_merge( $types, $taxes );
		foreach ( $list as $item ) {
			$killkey = array_search( "add-".$item, $boxes );
			if ( $killkey ) {
				unset( $boxes[$killkey] );
			}
		}
		return $boxes;
	}

	// kill the core Publish metabox
	function remove_publish_box() {
		foreach ( self::$type_data as $type => $vars ) {
			if ( $vars['args']['hide_publish'] ) {
				remove_meta_box( 'submitdiv', $type, 'side' );
			}
		}
	}


	//** modifies the QUERY before output ------------------------------------------------------//
	function filter_current_query( $query ) {
		// abort if suppressing or if ordering has been manually chosen by GET
		if ( somaFunctions::fetch_index( $query->query_vars, 'suppress_filters' ) || somaFunctions::fetch_index( $_GET, 'orderby' ) ) return $query;


		// nothing matched, pass along unfiltered
		return $query;
	}

	// modifies SQL query orderby values BEFORE parsing
	// http://codex.wordpress.org/Custom_Queries
	function posts_orderby( $orderby ) {
		$obj = get_queried_object();
		// got nothing, abort
		if ( is_null( $obj ) ) return $orderby;
		// are we querying a custom post type that has sorting arguments?
		if ( !$obj->_builtin && isset( $obj->sort_by ) && !$obj->taxonomy ) {
			$orderby = $obj->sort_by . " " . $obj->sort_order;     // reconstruct the ORDERBY sql string
		}
		// soma_dump($orderby);
		return $orderby;
	}

	// joins additional tables to SQL query BEFORE parsing
	// http://codex.wordpress.org/Custom_Queries
	function posts_join( $join ) {
		$obj = get_queried_object();
		// got nothing, abort
		if ( is_null( $obj ) ) return $join;
		// are we querying a custom post type that has sorting arguments set?
		if ( !$obj->_builtin && $obj->sort_by == "meta_value" && isset( $obj->sort_key ) ) {
			global $wpdb, $soma_options;
			$key = $soma_options['meta_prefix'] . "_" . $obj->sort_key;  // assemble post_meta key
			$join .= "LEFT JOIN $wpdb->postmeta ON ({$wpdb->posts}.ID = $wpdb->postmeta.post_id AND $wpdb->postmeta.meta_key = '$key')";  // join the post_meta table so we can sort by the meta_value
		}
		return $join;
	}

}
// INIT
$somaTypes = new somaTypes();
?>
