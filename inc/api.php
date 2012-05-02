<?php

/**
 * Register a new custom post type with minimal user input
 *
 * Must be called with "init" hook
 * Sets up a new custom post type via register_post_type, generates labels from provided strings, merges arguments with wp defaults, adds css to display custom icons, customizes wp messages and customizes the columns in edit.php lists
 *
 * Takes the following parameters, as an associative array:
 *
 * - 'slug' (string): A unique identifier for this custom post type (required).
 * - 'single' (string): Singular label name for this custom post type (required).
 * - 'plural' (string): Plural label name for this custom post type (optional).
 * - 'args' (array( key => value )): arguments to override default custom post type registration (optional).
 * - 'columns' (array( id => Title )): define the column listings in edit.php (optional)
 * - 'help' (string): html text to display in the help menu when this custom post type is active (optional).
 * - 'sortable' (bool): list items by menu_order instead of date, so we can manually adjust order - also displays a Sort page for this CPT
 * - 'create_nav_item' (bool): automatically generate a custom nav menu item linked the archive page for this CPT
 * - 'icons' (string): full http:// URL to directory (including trailing slash) where icons for this custom post type are located (optional).
 *
 *		place 4 PNG files for each custom post type you wish to show a custom icon in that directory, following the naming scheme:
 *			[slug]-menu-icon.png  (16x16px)
 *			[slug]-list-icon.png  (32x32px)
 *			[slug]-add-icon.png  (32x32px)
 *			[slug]-edit-icon.png  (32x32px)
 *		where "slug" is the exact string you supplied for the slug argument earlier, e.g. product-menu-icon.png
 *
 * @since 1.1
 * @param array $args
 * @return bool|object False on failure, post type object on success.
 */
function soma_init_type( $args ) {
	if ( !did_action('init') ) {
		return new WP_Error("missing","Connection types should not be registered before the 'init' hook.");
	}
	if ( !is_array( $args ) || empty( $args ) ) {
		return new WP_Error("missing","Must pass custom type parameters as array");
	}

	if ( empty( $args['slug'] ) ) {
		somaFunctions::soma_notices( __FUNCTION__ , "Must provide a slug (as a text string)!");			// does this work for admin notices?? have we tested??
		return null;
		// return new WP_Error("missing","Must provide a slug (as a text string)!");
	}

	if ( !isset( $args['single'] ) || !is_string( $args['single'] ) ) {
		return new WP_Error("missing","Must provide a singular name (as a text string)!");
	}

	return somaTypes::init_type( $args );
}


/**
 * Register a new custom taxonomy with minimal user input
 *
 * Must be called with "init" hook
 * Sets up a new taxonomy via register_taxonomy, generates labels from provided strings, merges arguments with wp defaults, generates terms if provided, and hides taxonomy metabox if desired
 *
 * Takes the following parameters, as an associative array:
 *
 * - 'slug' - string A unique identifier for this custom post type (required).
 * - 'single' - string: Singular label name for this custom post type (required).
 * - 'plural' - string: Plural label name for this custom post type (optional).
 * - 'types' - array( slug, slug ): list of post type slugs which this metabox should appear for (required).
 * - 'metabox' - bool: display the default taxonomy metabox in the post editor (defaults true) - NOTE the wp arg "show_ui" also determines this, but it toggles both the post editor metabox and the admin menu item for adding/editing/deleting terms.
 * 				This allows us to preserve that menu, but hide the automatically generated metabox on the side so we can select terms in our custom metaboxes instead
 * - 'terms' - array( term, term ): list of terms to pre-populate for this taxonomy (optional).
 * - 'terms' - array( term => array( slug => 'apple', description => "A yummy fruit" ) ): alternate method to specify slug and description when generating terms for this taxonomy (optional).
 * - 'args' - array( key => value ): arguments to override default custom post type registration (optional).
 *
 * @since 1.1
 * @param array $args
 * @return bool|object False on failure, taxonomy object on success.
 */
function soma_init_taxonomy( $args ) {
	if ( !did_action('init') )
		return new WP_Error("broken","Taxonomies should not be registered before the 'init' hook.");

	if ( empty( $args ) )
		return new WP_Error("missing","Must pass custom taxonomy parameters as array");

	if ( empty( $args['slug'] ) )
		return new WP_Error("missing","Must provide a slug (as a text string)!");

	if ( !isset( $args['single'] ) || !is_string( $args['single'] ) )
		return new WP_Error("missing","Must provide a singular name (as a text string)!");

	return somaTypes::init_taxonomy( $args );
}


/**
 * Configure custom metabox display
 *
 * Must be called with "admin_init" hook
 * Passes configuration data to the somatic Framwork which reads the arrays to build custom metaboxes and their form field contents
 *
 * Takes the following parameters, as an associative array:
 *
 * - 'id' - string A unique identifier for this metabox, used to create ID for div element (required).
 * - 'title' - string: Text to display in metabox top border (required).
 * - 'types' - array( key, key ): list of post type slugs which this metabox should appear for (required).
 * - 'context' - string: ('normal', 'advanced', 'side') - which column to display metabox (optional)
 * - 'priority' - string: ('high', 'core', 'default', 'low') - vertical postitioning. if all have same priority, metaboxes are rendered in order they are created (optional)
 * - 'restrict' - bool: restrict display of this metabox to STAFF only, as defined by somaFunctions::check_staff() (optional).
 * - 'save' - bool: display a "save changes" button at the end of this metabox (optional).
 * - 'fields' - array( array( key => value ) ): associative array of individual fields within this metabox, each item following this format:
 *		array (
 *			'id' => 'colors', 											// used when saving, should be the name of the post_meta or taxonomy we're manipulating - passed as form field ID when submitting
 *			'name' => 'Select a Color',									// text displayed alongside field input
 *			'type' => 'select',											// field type (usually input: text, area, select, checkbox, radio), sometimes output (related posts, attachment, other readonly data)
 *			'data' => 'taxonomy',										// what kind of data is being retrieved and saved for this post (meta [wp_postmeta table], core [wp_posts table], taxonomy, user, p2p, attachment, comment)
 *			'options' => soma_select_taxonomy_terms('colors'),			// array of options to populate input objects (in this case, generated elsewhere by function)
 *			'options' => array(											// example of manual array creation for use by html input select form - name is displayed, value is passed together with field ID of form
 *							array('name' => 'Springtime Green', 'value' => 'green'),
 *							array('name' => 'Autumn Red', 'value' => 'red')
 *						),
 *			'default' => 'green',										// default value shown by form input
 *			'multiple' => false,										// can multiple options be selected? or must saved value be singular?
 *			'required' => true											// can this field be left empty or unselected? other validation functions are needed to make use of this value (not included yet)
 *		)
 *
 *
 * @since 1.1
 * @param array $args
 * @return bool|WP_Error on failure, True on success.
 */
function soma_metabox_data( $args ) {
	if ( !did_action('admin_init') )
		return new WP_Error("broken","Metabox data should not be registered before the 'admin_init' hook.");

	if ( empty( $args ) || !is_array( $args) )
		return new WP_Error("missing","Must pass custom metabox data as array");

	somaMetaboxes::$data[] = $args;
	return true;
}


///////////// FUNCTIONS FOR GENERATING METABOX OPTION ARRAYS FOR USE IN HTML INPUT OBJECTS //////////////////
// useful when creating arrays to pass to soma_metabox_data //


// outputs this: array(array('name'=>'1','value'=>'1'),array('name'=>'2','value'=>'2')) etc
function soma_select_number_generator($max, $date = false, $zero = false) {
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
function soma_select_taxonomy_terms($tax, $create = false) {
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
function soma_select_users($roles) {
	$users = soma_get_role_members($roles);
	if (empty($users)) {
		return array(array('name' => '[none available]', 'value' => '0'));	// no user role matches, output empty list
	}
	foreach ($users as $user) {
		$list[] = array('name' => $user->display_name, 'value' => intval($user->ID));
	}
	return $list;
}

// retrieves array of user objects for a given role name
function soma_get_role_members($roles = null) {
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

// retrieve list of custom post types, output array for dropdown selector
function soma_select_types() {
	$types = get_post_types( array( 'exclude_from_search' => false, '_builtin' => false  ), 'objects' );
	foreach ($types as $type) {
		$list[] = array('name' => $type->label, 'value' => $type->query_var);
	}
	return $list;
}

// retrieve list of posts, can narrow by author
function soma_select_items( $type = null, $author_id = null ) {
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
function soma_select_generic($items) {
	$list = array();
	foreach ($items as $item) {
		$list[] = array('name' => $item, 'value' => sanitize_title($item));
	}
	return $list;
}


/**
 * Manipulate the post_meta of a given post ID
 *
 * Multi-tool for getting, creating, changing, or deleting the post_meta
 *
 * built to abstract the core functions 'get_post_meta' and 'update_post_meta' and add new functionality (such as handling serialization of the post_meta).
 * http://codex.wordpress.org/Function_Reference/get_post_meta
 * http://codex.wordpress.org/Function_Reference/update_post_meta
 *
 *
 * @since 1.3
 * @param $action (string) ["save", "get", "delete"]: what to do (required).
 * @param $pid (integer/string): the post ID to be manipulated (required).
 * @param $key (string): name of the post_meta key to work on (required).
 * @param $value (string/array): what to save to this post_meta key (optional, unless saving).
 * @param $serialize (boolean): override the global option for serializing the data (optional)
 * @param $use_prefix (boolean): append the global post_meta key prefix to the beginning of $key before saving (optional, defaults true)
 * @return bool True on success, False on failure (when action = 'save')
 * @return value of the post_meta key requested (when action = 'get')
 */

function soma_asset_meta( $action = null, $pid = null, $key = null, $value = null, $serialize = null, $use_prefix = true ) {

	if ( !$pid || !$action ) {
		return new WP_Error('missing', "Must pass ID and action...");
	}

	return somaFunctions::asset_meta( $action, $pid, $key, $value, $serialize, $use_prefix );
}

/**
 * Retrieves featured image of a post and returns array of intermediate sizes, paths, urls
 * or if missing, returns image data for a "missing" placeholder
 *
 * @since 1.3
 * @param $pid - (string/integer) post ID to get the featured image of (required)
 * @param $size - (string) [icon,thumb,medium,large,full] (optional)
 * @return array - tons of data
 * @return string - just the url of the specified $size
 */

function soma_featured_image( $pid = null, $size = null ) {
	if (!$pid) {
		return new WP_Error('missing', "Must pass a post ID argument!");
	}
	return somaFunctions::fetch_featured_image( $pid, $size );
}


/**
 * Parses a given URL from Youtube or Vimeo, detects the site, extracts the video ID and returns all the metadata returned by the public APIs
 *
 * @since 1.3.3
 * @param $url - (string) valid fully-formed URL to an asset on Youtube or Vimeo (required)
 * @param $width - (string) modifies generated embed/iframe code (optional)
 * @param $height - (string) modifies generated embed/iframe code (optional)
 * @return array:
 *	array['url']		rebuild external url
 *	array['id']			raw media ID
 *	array['site']		site name
 *	array['thumb']		url of default thumbnail
 *	array['title']		title
 *	array['desc']		description
 *	array['duration']	in seconds
 *	array['mobile']		mobile-friendly display url
 *	array['embed']		full code for embedding (should be html5 friendly)
 *	array['iframe']		url to pass to iframe renderers (like colorbox)
 *	array['api']		we include everything we got from the site API, just in case (each site formats differently)
 */

function soma_external_media( $url = null, $width = null, $height = null ) {
	if (!$url) {
		return new WP_Error('missing', "Must give us a URL!");
	}
	return somaFunctions::fetch_external_media( $url, $width, $height );
}

/**
 * Retrieves the term of a taxonomy that is meant to have only one value set at a time. ex: a dropdown selector in a metabox lets you choose only one term. This grabs that term.
 * Useful for taxonomies that function like a "status"
 *
 * @since 1.3.1
 * @param $pid - (string/integer) post ID (required)
 * @param $tax - (string) taxonomy slug to get the set term of (required)
 * @return string - the term's pretty name
 */

function soma_singular_term( $pid = null, $tax = null ) {
	if (!$pid || !$tax) {
		return new WP_Error('missing', "must pass a post ID and a taxonomy!");
	}
	return somaFunctions::fetch_the_singular_term( $pid, $tax );
}

/**
 * Outputs contents of variables for debugging purposes
 * Integrated with Debug Bar plugin (http://wordpress.org/extend/plugins/debug-bar/)
 * Depends on Kint class and the 'soma_debug' option being true
 *
 * @since 1.5
 * @param $data - variable to be rendered by Kint
 * @param $inline - force debug output to be rendered inline on the page, even if Debug Panel is installed and active (will still show there, too)
 * @return
 */

function soma_dump( $data, $inline = null ) {
	$opt = get_option('somatic_framework_options');
	if ( !$opt['debug'] ) return null;									// abort if debug option is off
	if ( !class_exists('Kint') ) return null;							// abort if Kint doesn't exist
	if ( !Kint::enabled() ) return null;								// abort if we don't have Kint to make output pretty
	if ( $inline ) set_transient( 'debug_inline', true );				// make a note to force inline output

	// $args = func_get_args();
	return call_user_func_array( array( 'Kint', 'dump' ), array($data) );		// pass along args to the Kint Class
}


/**
 * Used by modified Kint class to avoid output buffer problems
 * And to store the buffer in a global variable to be fetched by the debug display functions
 * inspired by Kint Debugger https://wordpress.org/extend/plugins/kint-debugger/
 *
 * @since 1.5
 * @param $buffer - incoming output buffer
 * @return $buffer - outgoing output buffer
 */

function soma_dump_globals( $buffer ) {
	global $soma_dump;
	$soma_dump[] = $buffer;
	// if we're forcing output to be inline, then just spit it out
	if ( get_transient( 'debug_inline') ) {
		delete_transient( 'debug_inline');
		return $buffer;
	}
	// we have Debug Bar, so don't output on the page
	if ( class_exists( 'Debug_Bar' ) ) return;

	// no Debug Bar, so output directly inline
	return $buffer;
}

/*
$defaults = array(
	"debug" => 0,							// debug mode
	"p2p" => 1,								// require posts 2 posts
	"meta_prefix" => "_soma",				// prefix added to post_meta keys
	"meta_serialize" => 0,					// whether to serialize somatic post_meta
	"dashboard_quick_press" => 1,			// hiding dashboards widgets?
	"dashboard_recent_drafts" => 1,
	"dashboard_recent_comments" => 1,
	"dashboard_incoming_links" => 1,
	"dashboard_plugins" => 1,
	"dashboard_primary" => 1,
	"dashboard_secondary" => 1,
	"thesis_news_widget" => 1,
	"hide_links_menu" => 1,
	"hide_comments_menu" => 0,
	"hide_media_menu" => 0,
	"hide_tools_menu" => 1,
	'bottom_admin_bar' => 0,
	"kill_autosave" => array(),				// array of post types slugs to disable autosave
	"favicon" => null,						// full url path to a .png or .ico
	"metaboxes" => array('thesis_seo_meta', 'thesis_image_meta','thesis_multimedia_meta', 'thesis_javascript_meta'),		// hide these in post editor
*/
function soma_set_option( $which = null, $value = null ) {
	$opt = get_option('somatic_framework_options', null);
	if (is_null($opt)) return new WP_Error('missing', "Can't find somatic options to save into...");
	if (!is_null($which) && !is_null($value)) {
		$opt[$which] = $value;
		$update = update_option('somatic_framework_options', $opt);
		return $update;
	} else {
		return new WP_Error('missing', "Must pass an option name and a value");
	}
}

// incomplete effort to consolidate notice reporting and have it output in the right place (rather than before the page headers)
function soma_notices($type, $msg) {
	switch ($type) {
		case 'update':
			$output = "<div id=\"message\" class=\"updated\" style=\"font-weight: bold\"><p>$msg</p></div>";
		break;
		case 'error':
			$output = "<div id=\"message\" class=\"error\" style=\"font-weight: bold\"><p>$msg</p></div>";
		break;
		case 'success':
			$output = "<div id=\"message\" class=\"updated success\" style=\"font-weight: bold\"><p>$msg</p></div>";
		break;
		default:
			$output = "<div id=\"message\" class=\"updated\" style=\"font-weight: bold\"><p>$msg</p></div>";
		break;
	}
	echo $output;
}