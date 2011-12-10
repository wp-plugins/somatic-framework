<?php

class somaTypes extends somaticFramework {

	function __construct() {
		add_action( 'admin_head', array(__CLASS__, 'custom_type_icons' ) );
		add_filter( 'wp_nav_menu_items', array(__CLASS__,'custom_type_nav'), 10, 2 );
		add_filter( 'post_updated_messages', array(__CLASS__,'custom_type_messages') );
	}

	//** CUSTOM POST TYPES -----------------------------------------------------------------------------------------------------//

	// global storage container for custom post type data (used in functions outside init_type)
	static $type_data = array();


	// assembles and generates a custom post type
	public function init_type($data) {

		// push to internal variables for convenience
		$slug = $data['slug'];
		$single = $data['single'];
		$plural = $data['plural'];

		// generate labels
		$labels = array(
			'name' => _x($plural, 'post type general name'),
			'singular_name' => _x($single, 'post type singular name'),
			'add_new' => _x('Add New', $single),
			'add_new_item' => __('Create A New '.$single),
			'edit_item' => __('Edit '. $single),
			'edit' => _x('Edit', $single),
			'new_item' => __('New '.$single),
			'view_item' => __('View '.$single),
			'search_items' => __('Search '.$plural),
			'not_found' =>  __('No '.$plural.' found'),
			'not_found_in_trash' => __('No '.$plural.' found in Trash'),
			'view' =>  __('View '.$single)
		);
		// generate args
		$default_args = array(
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true,
			'has_archive' => $slug,
			'hierarchical' => true,
			'query_var' => true,
			'show_in_nav_menus' => true,
			'supports' => array( 'editor', 'title', 'thumbnail' ),
			'rewrite' => array( 'slug' => $slug, 'with_front' => false ),
			'register_meta_box_cb' => array('somaMetaBoxes','add_boxes'),
			'labels' => $labels
		);

		// merge with incoming args
		$args = wp_parse_args($data['args'], $default_args);

		// create the post-type
		register_post_type($slug, $args);

		// store cpt data for later
		self::$type_data[$slug] = $data;

		// store custom messages too
		self::$type_data[$slug]['messages'] = array(
			0 => '', // Unused
			1 => sprintf( __($single.' updated. <a href="%s">View '.$single.'</a>'), esc_url( get_permalink($post_ID) ) ),
			2 => __('Custom field updated.'),
			3 => __('Custom field deleted.'),
			4 => __($single.' updated.'),
			/* translators: %s: date and time of the revision */
			5 => isset($_GET['revision']) ? sprintf( __($single.' restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf( __($single.' published. <a href="%s">View '.$single.'</a>'), esc_url( get_permalink($post_ID) ) ),
			7 => __($single.' saved.'),
			8 => sprintf( __($single.' submitted. <a target="_blank" href="%s">Preview '.$single.'</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
			9 => sprintf( __($single.' scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview '.$single.'</a>'),
			// translators: Publish box date format, see http://php.net/date
			date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
			10 => sprintf( __($single.' draft updated. <a target="_blank" href="%s">Preview '.$single.'</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
		);

		// custom list columns
		add_filter("manage_edit-".$slug."_columns", array(__CLASS__,"custom_edit_columns"));
		add_action("manage_".$slug."_posts_custom_column", array(__CLASS__,'custom_column_data'));
	}


	//** EDIT.PHP LIST COLUMNS -----------------------------------------------------------------------------------------------------//


	// custom column items and order
	// columns retrieved from $type_data container
	function custom_edit_columns($columns) {
		global $post_type;
		$columns = self::$type_data[$post_type]['columns'];
		return $columns;
	}

	// custom column item content generation
	function custom_column_data($column) {
		global $post, $post_type;
		// don't bother for core types
		if( $post_type != 'posts' || $post_type != 'pages') {
			// output each column's content
			switch ($column) {
				case "thumb":
					$img = somaFunctions::fetch_featured_image($post->ID);
					$edit = get_edit_post_link($post->ID);
					$view = get_permalink($post->ID);
					$output = "<a href=\"$edit\"><img src=\"{$img['thumb']['url']}\" /></a>";
					echo $output;
				break;
				case "artists":
					echo somaFunctions::fetch_the_term_list( $post->ID, 'artists','',', ');
				break;
				case "albums":
					echo somaFunctions::fetch_the_term_list( $post->ID, 'albums','',', ');
				break;
				case "genres":
					echo somaFunctions::fetch_the_term_list( $post->ID, 'genres','',', ');
				break;

			}
		}
	}

	//** CUSTOM TAXONOMY AND TERMS -----------------------------------------------------------------------------------------------------------//

	public function init_taxonomy($data) {
		$slug = $data['slug'];
		$single = $data['single'];
		$plural = $data['plural'];
		$metabox = isset($data['metabox']) ? $data['metabox'] : true;
		$types = isset($data['types']) ? $data['types'] : new WP_Error('missing','can\'t declare taxonomy without post types...');

		$default_args = array(
			'hierarchical' => true,			// category vs. tag style
			'query_var' => true,
			'public' => true,
			'show_ui' => true,
			'show_in_nav_menus' => true,
			'labels' => array(
				'name' => $plural,
				'singular_name' => $single,
				'add_new_item' => 'Add New '.$single,
				'search_items' => 'Search '.$plural,
				'popular_items' => 'Popular '.$plural,
				'all_items' => 'All '.$plural,
				'edit_item' => 'Edit '.$plural,
				'new_item_name' => 'New '.$single.' Name',
				'choose_from_most_used' => 'Choose from most used '.strtolower($plural),
				'add_or_remove_items' => 'Add or remove '.strtolower($plural),
				'separate_items_with_commas' => 'Separate '.strtolower($plural).' with commas'
			)
		);

		$args = wp_parse_args($data['args'], $default_args);
		// do it
		register_taxonomy($slug, $types, $args);

		// then populate with terms - execute only upon theme activation -- WILL THIS WORK??????
		// $pagenow = $_SERVER['SCRIPT_NAME'];
		// if ( is_admin() && isset($_GET['activated'] ) && $pagenow == "/wp-admin/themes.php" ) {
		if (isset($data['terms']) && !empty($data['terms'])) {
			self::term_generator($slug, $data['terms']);
		}
		// }

		// hide metabox on post editor?
		if ($metabox == false) {
			// build metabox ID name
			if ( $args['hierarchical'] ) {
				$box = $slug . "div";
			} else {
				$box = "tagsdiv-" . $slug;
			}
			// remove for each post type
			foreach ( $types as $type ) {
				add_action( 'do_meta_boxes', create_function('', "
					remove_meta_box( $box, $type, 'side' );
				"));
			}
		}
	}

	// makes terms, either from flat array(term, term, term) - or array(term => array( slug => foo))
	function term_generator($taxonomy, $terms) {
		foreach ($terms as $term => $args) {
			if (!is_array($args)) {		// no args array was given, so $args holds the term and $term appears as an array index number
				$term = $args;
				$args = array('slug'=>sanitize_title($term));
			}
			if (!term_exists( $args['slug'], $taxonomy )) {
				wp_insert_term( $term, $taxonomy, $args );
			}
		}
	}


	//** MISC CUSTOMIZATIONS ------------------------------------------------------------------------------------------------------------------------------------//

	// custom admin menu icons for post types
	// icon URLs retrieved from $type_data container
	function custom_type_icons() {
		global $pagenow, $post_type;
		if ( $pagenow == 'post-new.php' && ($post_type != 'post' || $post_type != 'page')) {
			echo '<style>#wpbody-content .icon32 { background: transparent url("'.self::$type_data[$post_type]['icons']['add_icon'].'") no-repeat; !important }</style>';
		}
		if ( $pagenow == 'post.php' && ($post_type != 'post' || $post_type != 'page')) {
			echo '<style>#wpbody-content .icon32 { background: transparent url("'.self::$type_data[$post_type]['icons']['edit_icon'].'") no-repeat; !important }</style>';
		}
		if ( $pagenow == 'edit.php' && ($post_type != 'post' || $post_type != 'page')) {
			echo '<style>#wpbody-content .icon32 { background: transparent url("'.self::$type_data[$post_type]['icons']['list_icon'].'") no-repeat; !important }</style>';
		}
	}

	// automatically adds custom post types to the primary navbar and adds highlighting for current page
	function custom_type_nav($menuItems, $args) {
		// var_dump($menuItems);
		global $wp_query;
		$types = get_post_types( array( '_builtin' => false  ), 'objects' );
		if('primary' == $args->theme_location) {
			foreach ($types as $type) {
				if ( $wp_query->query_vars['post_type'] == $type->rewrite['slug'] ) {
					$class = 'class="current_page_item"';
				} else {
					$class = '';
				}
				// build nav item
				$navItem .= '<li ' . $class . '>' .
					$args->before .
					'<a href="' . home_url( '/'. $type->rewrite['slug'] ) . '" title="'.$type->labels->name.'">' .
					$args->link_before .
					$type->labels->name .
					$args->link_after .
					'</a>' .
					$args->after .
					'</li>';
			}
			$menuItems = $menuItems . $navItem;
		}
		return $menuItems;
	}


	// add custom messages to post_updated_messages
	// messages retrieved from $type_data container
	function custom_type_messages( $messages ) {
		foreach (self::$type_data as $type => $vars) {
			$messages[$type] = $vars["messages"];
		}
		return $messages;
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
	public function disable_post_type( $post_type, $slug = '' ){
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
}

?>