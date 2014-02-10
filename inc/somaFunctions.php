<?php

class somaFunctions extends somaticFramework {

	function __construct() {
		add_action( 'init', array(__CLASS__,'init' ));
		add_action( 'admin_init', array(__CLASS__,'check_plugin_dependency' ));
		add_filter( 'query_vars', array(__CLASS__,'custom_query_vars' ));
		add_filter( 'parse_query', array(__CLASS__,'filter_current_query' ));			// empty at the moment
		add_filter( 'pre_get_posts', array(__CLASS__,'pre_get_posts'));
		add_action( 'before_delete_post', array(__CLASS__, 'delete_attachments_when_parents_die' ));		// have to do this "before", as wp_delete_post detaches all attachments before the 'delete_post' action, which left us unable to retrieve connected attachments...
		add_filter( 'gettext',  array(__CLASS__, 'modify_core_language'  ), 20, 3);
		add_filter( 'ngettext',  array(__CLASS__, 'modify_core_language'  ), 20, 3);
		// add_filter( 'login_redirect', array(__CLASS__, 'dashboard_redirect' ));
		add_filter( 'add_menu_classes', array(__CLASS__, 'show_pending_number'));
		// add_filter( 'wp_die_handler', array(__CLASS__, 'soma_wp_die_handler'),10,3);
		add_filter( 'editable_roles', array(__CLASS__, 'editable_roles'));
		add_filter( 'map_meta_cap', array(__CLASS__, 'admin_map_meta_cap'), 10, 4);
		remove_filter('check_comment_flood', 'check_comment_flood_db');					// deal with "posting too quickly" problem....
		add_filter( 'edit_posts_per_page', array(__CLASS__, 'edit_list_length'));
		add_action( 'wp_ajax_unlink_file', array(__CLASS__, 'ajax_unlink_file'));
		add_action( 'wp_ajax_delete_attachment', array(__CLASS__, 'ajax_delete_attachment'));
		add_action( 'admin_notices', array(__CLASS__,'soma_admin_notices'), 100);
	}

	function init() {
		// localization init needed?? no translations yet
		// load_plugin_textdomain( 'stock-media-asset-manager', SOMA_DIR . '/lang', basename( dirname( __FILE__ ) ) . '/lang' );
		// self::session_manager(); /// WARNING this occasionally causes PHP errors: "Cannot send session cache limiter - headers already sent" -- do we need this?
		self::wp_version_check();
		self::check_staff();
	}

	// displays notices using the core wp messge box.
	function soma_admin_notices() {
		// if (soma_fetch_index($_GET['soma-notify']) == true) {
		// if (get_query_var('soma_notify') == true) {
		$notices = get_transient( 'soma_notices' );						// check if any waiting
			if ($notices === false) return;
			foreach ($notices as $notice) {
				if (empty($notice['msg'])) continue;
				if (empty($notice['type'])) $notice['type'] == 'updated';
				echo "<div class='{$notice['type']}'>";
				echo "<p>{$notice['msg']}</p>";
				echo "</div>";
			}
		// }
		delete_transient('soma_notices');
	}

	// adds an admin notice message to the queue. can use 'updated', or 'error'
	function queue_notice($type = 'updated', $msg) {
		$notices = get_transient( 'soma_notices' );							// grab any existing notices
		if ($notices === false || !is_array($notices)) $notices = array();	// or init
		$notices[] = array('type' => $type, 'msg' => $msg);					// add to the queue
		set_transient( 'soma_notices', $notices );							// store it
	}

	// adds an admin notice as well as reloads the page to display the notice. good for returning the result of a custom manipulation function without leaving the page
	function completion_notice($type, $msg) {
		somaFunctions::queue_notice($type, $msg);
		// $result = add_query_arg( 'soma_notify', 'true', $_SERVER['HTTP_REFERER'] );		// used to be needed to trigger, but now we just check for the transient itself
		// wp_redirect( $result );
  		wp_redirect( $_SERVER['HTTP_REFERER'] );
  		exit;	// this halts everything, so this better be your last function call
	}

	// set if user has staff privileges (default to editors)
	function check_staff() {
		$privs = 'edit_others_posts';
		$privs = apply_filters('soma_staff_privs', $privs);
		define( 'SOMA_STAFF', current_user_can( $privs ));
	}

	// changes how many items are shown per page in /wp-admin/edit.php
	function edit_list_length() {
		return 40;
	}

	// checks if something is truly empty (not set) or null, and not simply set to a valid but negative value, like false or - 0 (0 as an integer) - 0.0 (0 as a float) - "0" (0 as a string)
	// NOTE: THIS DOESN'T AVOID THE PHP NOTICE ERROR IF SOMETHING DOESN'T EXIST (not set)
	function is_blank( $value ) {
		return empty( $value ) && !is_numeric( $value ) && $value !== false;
	}

	// checks if array is associative or not
	function array_is_associative($arr) {
		if (!is_array($arr)) return false;
		return array_keys($arr) !== range(0, count($arr) - 1);
	}

	// returns URL to facebook image from facebook ID stored in post_meta
	function fetch_facebook_pic($pid, $size = "square") {
		// sizes: square, small, normal, large

		$fid = somaFunctions::asset_meta('get', $pid, 'facebook_id');
		if (!$fid) return SOMA_IMG . '/generic-female-thumb.png';

		// $userurl = "http://graph.facebook.com/$fid";
		// $user = json_decode(file_get_contents($userurl));
		// $feedurl = "http://graph.facebook.com/$fid/feed";
		// $feed = json_decode(file_get_contents($feedurl));
		return "https://graph.facebook.com/$fid/picture?type=$size";
	}

	// since our postmeta is serialized, to query posts by postmeta requires retrieving a set of posts and then checking each entry against the desired postmeta for matches
	function fetch_posts($args, $meta_key = null, $meta_value = null) {
		if (!is_array($args)) return null;
		$args['numberposts'] = -1;	// always retrieve all, not just first 5
		$items = get_posts($args);
		$matches = array();
		foreach ($items as $item) {
			$val = somaFunctions::asset_meta('get', $item->ID, $meta_key);
			if ($val == $meta_value) {
				$matches[] = $item;
			}
		}
		return $matches;
	}

	// retrieves array of user objects for a given role name
	function get_role_members_OLD($rolename) {
		$blogusers = get_users_of_blog();
		$members = array();
		foreach ($blogusers as $user) {
			$userdata = get_userdata($user->ID);
			// check role name
			if ($userdata->wp_capabilities[$rolename] == '1') {
				// add to new role array
				$members[] = $userdata;
			}
		}
		return $members;
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

	// retrieves name of role assigned to pass user id or current user if not provided
	function get_user_role( $uid = null ) {
		if (!$uid) {
			$user = wp_get_current_user();
		} else {
			$user = new WP_User( $uid );
		}
		if ( !empty( $user->roles ) && is_array( $user->roles ) ) {
			foreach ( $user->roles as $role )
				return $role;
		}
	}

	// comparison function for usort - allows sorting of arrays of objects by object properties
	function usort_displaynames($a, $b) {
		if ($a->display_name == $b->display_name)
			return 0;
		else
			return ($a->display_name < $b->display_name ? -1 : 1);
	}

	// comparison function for usort - allows sorting of arrays of objects by object properties
	function usort_ids($a, $b) {
		if ($a->ID == $b->ID)
			return 0;
		else
			return ($a->ID < $b->ID ? -1 : 1);
	}

	// checks to see if $_GET or $_POST values are set, avoids Undefined index error
	function fetch_index($array, $index) {
		if (!is_array($array)) return null;
		return isset($array[$index]) ? $array[$index] : null;
	}

	//
	function fetch_author($author_id = null, $output = "display_name") {
		if ($author_id != null) {
			$user = get_userdata($author_id);
			if ($user == null) {
				return new WP_Error('missing','can\'t find the user...');
			}
		} else {
			return new WP_Error('missing','must provide author id...');
		}
		// $code = get_the_author_meta( somaMetaboxes::$meta_prefix .'user_code', $post->post_author );
		// $code = get_the_author_meta( 'user_exclusive', $post->post_author );

		if ($output == "link") {
			if (is_admin()) {
				$url = admin_url() . "edit.php?author=" . $user->ID;
				$url .=  $type ? "&post_type=".$type : "";
			} else {
				$url = home_url() . "?author=" . $user->ID;
				$url .=  $type ? "&post_type=" . $type : "";
			}
			return "<a href=\"" . $url . "\">". $user->display_name . "</a>";
		}
		return $user->$output;
	}

	//
	function fetch_post_author($post_id = null, $output = "display_name" ) {
		if (!$post_id) {
			global $post;
			if ($post == null) {
				return new WP_Error('missing','can\'t find the post...');
			}
		} else {
			$post = get_post($post_id);
		}
		$type = $post->post_type;
		$user = get_userdata($post->post_author);

		// $code = get_the_author_meta( somaMetaboxes::$meta_prefix .'user_code', $post->post_author );
		// $code = get_the_author_meta( 'user_exclusive', $post->post_author );

		if ($output == "link") {
			if (is_admin()) {
				$url = admin_url() . "edit.php?author=" . $user->ID;
				$url .=  $type ? "&post_type=".$type : "";
			} else {
				$url = home_url() . "?author=" . $user->ID;
				$url .=  $type ? "&post_type=" . $type : "";
			}
			return "<a href=\"" . $url . "\">". $user->display_name . "</a>";
		}
		return $user->$output;
	}

	//
	function fetch_current_user($output = "display_name") {
		global $current_user;
		if ($current_user == null) {
			return new WP_Error('missing','can\'t find the user...');
		}

		if ($output == "link") {
			if (is_admin()) {
				$url = admin_url() . "edit.php?author=" . $current_user->ID;
				$url .=  $type ? "&post_type=".$type : "";
			} else {
				$url = home_url() . "?author=" . $current_user->ID;
				$url .=  $type ? "&post_type=" . $type : "";
			}
			return "<a href=\"" . $url . "\">". $current_user->display_name . "</a>";
		}
		return $current_user->$output;
	}


	//** clone of core get_the_term_list() - modifies the URL output if inside admin to stay in admin and to link to the edit post listing screen, also - can output comma separated string instead of links
	function fetch_the_term_list( $id = null, $taxonomy, $before = '', $sep = ', ', $after = '', $output = 'html' ) {
		if (is_null($id)) return false;
		$terms = wp_get_object_terms( $id, $taxonomy );
		$type = get_post_type($id);
		if ( is_wp_error( $terms ) )
			return $terms->get_error_message();
		if ( empty( $terms ) )
			return false;
		// create array with links
		if ($output == "html") {
			foreach ( $terms as $term ) {
				// within admin, link to the admin edit post list screen, but filtering for this taxonomy term
				if (is_admin()) {
					$link = admin_url() . "edit.php?" . $taxonomy . "=" . $term->slug . "&post_type=" . $type;
				} else {
					$link = get_term_link( $term, $taxonomy );
				}
				if ( is_wp_error( $link ) )
					return $link->get_error_message();
				$term_links[] = '<a href="' . $link . '" rel="tag">' . $term->name . '</a>';
			}
		// create plain string of comma-separated names
		}
		if ($output == "text") {
			foreach ($terms as $term) {
				$term_links[] = $term->name;
			}
		}

		// outputs a vertical stack table for items in the edit.php listing
		if ($output == "table") {
			if (!is_admin()) return false;

			$table = "<table>";
			$i = 1;
			$max = count($terms);
			foreach ($terms as $term) {
				// avoid border on last entry
				if ($i == $max) {
					$table .= "<tr><td class=\"last\">";
				} else {
					$table .= "<tr><td>";
				}
				$link = admin_url() . "edit.php?" . $taxonomy . "=" . $term->slug . "&post_type=" . $type;
				$table .= "<a href=\"".$link."\" rel=\"tag\">$term->name</a></td></tr>";
				$i++;
			}
			$table .= "</table>";
			return $table;
		}

		return $before . join( $sep, $term_links ) . $after;
	}

	// retrieves taxonomy terms that are used in a singular way (only one possible state, ie ON or OFF)
	function fetch_the_singular_term( $post, $taxonomy, $label = "slug" ) {
		if (is_wp_error($post)) return $post;
		if (empty($post)) return new WP_Error('missing', "must pass a post argument!");
		if (is_object($post)) {
			$pid = $post->ID;
		} else {
			$pid = intval($post);
		}

		$term = wp_get_object_terms( $pid, $taxonomy );
		if (is_wp_error($term)) return null;
		if ($label == 'slug') {
			return $term[0]->slug;
		}
		if ($label == 'name') {
			return $term[0]->name;
		}
		if ($label == 'id' || $label == 'term_id') {
			return $term[0]->term_id;
		}
		return null;
	}


	//** for retrieving p2p connected posts
	// can output count, array of post objects, list of html links, list of text, a table of links, or select dropdown input values
	// note - modifies the URL output if inside wp-admin to stay in admin and to link to the edit post listing screen rather than the post permalink
	// $pid = can be single integer or array of post ID's
	// $p2pname = the name of the connection you made with "p2p_register_connection_type"
	// $dir = connection direction (to, from, any)
	// $output = how to format the return data

	function fetch_connected_items( $pid = 0, $p2pname, $dir = 'any', $output = 'html', $sep = ", " ) {
		if (!function_exists('p2p_register_connection_type')) return 'No P2P plugin!';		// make sure plugin is enabled

		// https://github.com/scribu/wp-posts-to-posts/wiki/Query-vars

		$args = array( 'connected_type' => $p2pname );
		switch( $dir ) {
			case "to" :
				$args['connected_to'] = $pid;
			break;
			case "from" :
				$args['connected_from'] = $pid;
			break;
			default :
				$args['connected_items'] = $pid;	// this passes "any" for direction
			break;
		}

		if ($meta_key && $meta_value) {
			$args['connected_meta'] = array(
				$meta_key => $meta_value
			);
		}

		$query = new WP_Query( $args );
		$items = array();
		$items = $query->posts;

		if ( is_wp_error( $items ) )
			return $items->get_error_message();;

		// output how many connected items
		if ($output == "count") {
			if ( empty( $items ) ) return 0;
			return count($items);
		}


		// simply output the array of matching post objects
		if ($output == "objects") {
			if ( empty( $items ) ) return false;
			return $items;
		}

		// create array with links
		if ($output == "html") {
			if ( empty( $items ) ) return false;
			foreach ( $items as $item ) {
				// within admin, link to the admin edit post list screen, but filtering for this taxonomy term
				if (is_admin()) {
					$link = get_edit_post_link($item->ID);
				} else {
					$link = get_permalink($item->ID);
				}
				if ( is_wp_error( $link ) )
					return $link->get_error_message();;
				$item_list[] = '<a href="' . $link . '">' . $item->post_title . '</a>';
			}
			return $before . join( $sep, $item_list ) . $after;

			// return p2p_list_posts( p2p_type( $p2pname )->get_connected( $post->ID ) );  // a helpful function from scribu to do the same thing ;-)
		}

		// create plain string of comma-separated names
		if ($output == "text") {
			if ( empty( $items ) ) return false;
			foreach ($items as $item) {
				$item_list[] = $item->post_title;
			}
			return $before . join( $sep, $item_list ) . $after;
		}

		// outputs a vertical stack table for items in the edit.php listing
		if ($output == "table") {
			if (!is_admin()) return false;
			if ( empty( $items ) ) return false;
			// generate table html
			$table = "<table>";
			$i = 1;
			$max = count($items);
			foreach ($items as $item) {
				// avoid border on last entry
				if ($i == $max) {
					$table .= "<tr><td class=\"last\">";
				} else {
					$table .= "<tr><td>";
				}
				$table .= "<a href=\"".get_edit_post_link($item->ID)."\">$item->post_title</a></td></tr>";
				$i++;
			}
			$table .= "</table>";
			return $table;
		}

		// outputs an array for populating select dropdown inputs
		if ($output == "select") {
			if ( empty( $items ) ) {
				return array(array('name' => '[nothing to select]', 'value' => '0'));	// no user role matches, output empty list
			}
			foreach ($items as $item) {
				$list[] = array('name' => $item->post_title, 'value' => intval($item->ID));
			}
			return $list;
		}
	}


	//** generates mini box html output of asset ------------------------------------------------------//
	function mini_asset_output($items) {
		if (!is_array($items)) {
			$items = array($items);
		}
		$html = "<div class=\"mini-asset-list\">\n";
		$html .= "<ul class=\"slider\">\n";

		foreach ($items as $item) {

			$view = get_permalink($item->ID);
			$edit = get_edit_post_link($item->ID);
			$title = get_the_title($item->ID);
			$services = somaFunctions::fetch_the_term_list($item->ID, 'service','','<br/>','','html');
			$status = somaFunctions::fetch_the_singular_term($item->ID, 'app_status');
			$type = $item->post_type;
			$img = somaFunctions::fetch_featured_image($item->ID);


			$html .= "<li class=\"item $status\">\n";

			$html .= "\t<ul>\n";

			// in admin, click image to edit the item, in front-end, click image to go to single asset view
			// if (is_admin()) {
			// 	if ( $review ) {
			// 		// add lightbox link to image
			// 		$html .= "\t<li class=\"post-thumb\"><a class=\"lightbox\" rel=\"gallery\" href=\"{$img['large']['url']}\"><img src=\"{$img['thumbnail']['url']}\" /></a></li>\n";
			// 	} else {
			// 		// add edit link to image
			// 		$html .= "\t<li class=\"post-thumb\"><a href=\"". get_edit_post_link($item->ID) ."\"><img src=\"{$img['thumbnail']['url']}\" /></a></li>\n";
			// 	}
			// } else {
			// 	// add view link to image
			// 	$html .= "<li class=\"post-thumb\"><a href=\"". get_permalink($item->ID) . "\"><img src=\"{$img['thumbnail']['url']}\" /></a></li>\n";
			// }


			$html .= "\t\t<li class=\"asset-thumb\">\n";

			$html .= "\t\t\t<a href=\"$edit\"><div class=\"fb-thumb\"><img src=\"{$img['thumbnail']['url']}\"/></a></div>\n";
			// $html .= "\t\t\t<a href=\"$edit\"><img src=\"{$img['thumbnail']['url']}\"/></a>\n";
			$html .= "\t\t</li>\n";

			$html .= "\t\t<li class=\"title\"><strong><a href=\"$edit\">". get_the_title($item->ID). "</a></strong></li>\n";

			// $html .= "\t\t<li class=\"services\">". $services . "</li>\n";

			// $html .= "\t\t<li class=\"creator\">". somaFunctions::fetch_post_author( $item->ID) ."</li>\n";


			if ($type == "classes") {
				$conn = get_posts( array(
					'post_type' => array('clients'),
					'suppress_filters' => false,
					'numberposts' => -1,
					'connected' => $item->ID,
				) );
				$max = somaFunctions::asset_meta('get', $item->ID, 'class_max');
				if (!$max) $max = "?";
				$count = count( $conn);
				$html .= "\t\t<li class=\"count\">" .$count . " Enrolled / $max Max</li>\n";
				$html .= "\t\t<li class=\"type\">$type</li>\n";		// minibox label
			} else {
				$html .= "\t\t<li class=\"type\">$status</li>\n";	// minibox label
			}

			$html .= "\t</ul>\n";
			$html .= "</li>\n";
		}
		$html .= "</ul>\n";
		$html .= "</div>\n";
		return $html;
	}

	// need this?
	function extract_numbers($string) {
		preg_match_all('/([\d]+)/', $string, $match);
		return $match[0];
	}

	// automatically sends user to dashboard after login, instead of their profile page
	function dashboard_redirect($url) {
		global $user;
		// if ($user->roles[0] == 'foo')
		// 	return 'wp-admin/admin.php?page=foo-user-page';
		return 'wp-admin/index.php';
	}

	/**
	 * Performs a query for results within a set of multiple terms (matching any one of, not all)
	 *
	 * @param string $type (required) Post Type slug
	 * @param string $tax (required) Taxonomy slug
	 * @param array strings $terms (required) Term slugs to match within
	 * @return array of objects
	 */
	function multi_term_query($type = null, $tax = null, $terms = null) {
		global $wpdb;

		if (!$type || !$tax || !is_array($terms)) {
			wp_die('missing an argument...');
		}

		$querystr = "SELECT *
			FROM $wpdb->posts
			LEFT JOIN $wpdb->term_relationships ON($wpdb->posts.ID = $wpdb->term_relationships.object_id)
			LEFT JOIN $wpdb->term_taxonomy ON($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id)
			LEFT JOIN $wpdb->terms ON($wpdb->term_taxonomy.term_id = $wpdb->terms.term_id)
			WHERE $wpdb->posts.post_type = '$type'
			AND $wpdb->posts.post_status = 'publish'
			AND $wpdb->term_taxonomy.taxonomy = '$tax'
			AND (";
		$i = 1;
		foreach ($terms as $term) {
			$querystr .= "$wpdb->terms.slug = '$term'";
			if ($i < count($terms)) {
					$querystr .= " OR ";
			}
			$i++;
		}

		$querystr .= ")\nORDER BY $wpdb->posts.post_date ASC";
		// echo $querystr;
		$posts = $wpdb->get_results($querystr, OBJECT);
		return $posts;
	}


	// add_action('post_submitbox_misc_actions','my_post_submitbox_misc_actions');
	function my_post_submitbox_misc_actions() {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$("#misc-publishing-actions select#post_status option[value='pending']").remove();
		});
		</script>
		<?php
	}

	// changes words throughout wordpress
	function modify_core_language( $translated ) {
	     // $translated = str_replace( 'Publish', 'Ingest', $translated );
	     $translated = str_replace( 'Dashboard', 'Overview', $translated );
	     $translated = str_replace( 'featured', 'assigned', $translated );
	     $translated = str_replace( 'Start typing the title of a post', 'Start typing the title of an asset', $translated );	// p2p metabox
	     // $translated = str_replace( 'Post', 'Article', $translated );
	     return $translated;
	}

	// deletes all attachments (including the actual files on server) when a post is deleted -- custom post type args or this post's post_meta must be set for this to happen, so its not universal
	function delete_attachments_when_parents_die($post_id) {
		$metakill = somaFunctions::asset_meta('get', $post_id, 'delete_attachments_upon_deletion');
		$ptype = get_post_type($post_id);
		$ptobj = get_post_type_object($ptype);
		$typekill = $ptobj->delete_attachments_upon_deletion;
		if ( $metakill || $typekill ) {
			global $wpdb;
			$attids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = %s", $post_id, 'attachment')); // properly prepared safe query
			foreach ( $attids as $attid ) {
				wp_delete_attachment($attid);
			}
		}
	}

	//** allows use of custom field data in the query vars URL string. without this, would need to call the query_posts() function and pass meta_key/meta_value...
	function custom_query_vars($qvars) {
	    $qvars[] = "meta_key";
	    $qvars[] = "meta_value";
	    $qvars[] = "meta_compare";
	    return $qvars;
	}

	//** modifies the QUERY before output ------------------------------------------------------//
	function filter_current_query($query) {
		return $query;
	}

	// this is much like filter_current_query
	function pre_get_posts($query) {
		// wp_die(var_dump($query));
		return $query;
	}

	/**
     * Merge new query parameters with existing parameters.
     *
     * This preserves existing request parameters, for compatibility
     * with plugins, etc.
     *
     * New parameters overwrite existing parameters with same name.
     *
     * @access private
     * @param array $new_params Parameters for a new query via
     *        {@link query_posts()} or a new {@link WP_Query}.
     * @global $wp_query is used to get the current request parameters.
     *
     * @return array The merged parameters array, ready for passing to
     *               {@link WP_Query::query_posts()} or
     *               {@link WP_Query::get_posts()}.
     */
    public function preserve_query_params( $new_params = array() ) {
        global $wp_query;
        return( array_merge( $wp_query->query, $new_params ) );
    }

	//** retrieves featured image of a post, or an attachment itself and returns array of intermediate sizes, paths, urls ----------------------------------------------------------------------------------//
	public function fetch_featured_image($post = null, $specific = null) {
		if (is_wp_error($post)) return $post;
		if (empty($post)) return new WP_Error('missing', "must pass a post argument!");
		if (is_object($post)) {
			$pid = $post->ID;
		} else {
			$pid = intval($post);
		}

		$img = array();	// container

		// fetch id of featured image attachment
		if (has_post_thumbnail($pid)) {
			$att_id = get_post_thumbnail_id($pid);
		}

		// post is already attachment - just pass ID
		if (get_post_type($pid) == 'attachment') {
			$att_id = $pid;
		}

		// some attachment successfully found
		if ($att_id) {
			$att_meta = wp_get_attachment_metadata($att_id); 		// get metadata of attachment
			if ( !empty( $att_meta['subdir'] ) ) {					// the original SMAM system generates this custom attachment meta when it created upload directories based on type/author. If it exists, use it
				$subdir = $att_meta['subdir'] . '/';
			}
			$dirname = dirname( $att_meta['file'] );				// when "Organize my uploads into month- and year-based folders" is turned on, the date subfolder is stored in file path
			if ( $dirname == "." ) {	// no subdirectory path is needed
				$subdir = "/";
			} else {
				$subdir = '/'. dirname($att_meta['file']) . '/';
			}
			// build the paths from the base media upload dir and the subdir
			$media_path = WP_MEDIA_DIR . $subdir;
			$media_url = WP_MEDIA_URL . $subdir;

			// NEW ARRAY
			$img['id'] = $att_id;

			// 'sizes' key will only exist if the uploaded image was equal or larger than the site option for thumbnail size
			if ( !is_null( somaFunctions::fetch_index( $att_meta, 'sizes' ) ) && !empty( $att_meta[ 'sizes' ] ) ) {
				// loop through all available image sizes, including any custom ones via add_image_size()
				foreach ($att_meta['sizes'] as $size => $data) {
					$img[$size]['file'] 	= 	$data['file'];
					$img[$size]['height'] 	= 	$data['height'];
					$img[$size]['width'] 	= 	$data['width'];
					$img[$size]['url'] 		= 	$media_url . $img[$size]['file'];
					$img[$size]['path'] 	= 	$media_path . $img[$size]['file'];
				}
				$img['full']['file'] 	= 	basename($att_meta['file']);	// $att_meta['file'] contains the entire server path, so extract just the name
				$img['full']['url'] 	= 	$media_url . $img['full']['file'];
				$img['full']['path'] 	= 	$media_path . $img['full']['file'];
				$img['full']['height'] 	= 	$att_meta['height'];
				$img['full']['width'] 	= 	$att_meta['width'];
				$img['loc']['path'] 	= 	$media_path;
				$img['loc']['url']		= 	$media_url;

			// populate thumb data with the direct file info, as the uploaded image was smaller or equal to the site option for thumbnail size. Yes, the thumb and the full img info are the same in this case...
			} else {
				$img['thumbnail']['file'] 	= 	basename($att_meta['file']);
				$img['thumbnail']['height'] = 	$att_meta['height'];
				$img['thumbnail']['width'] 	= 	$att_meta['width'];
				$img['thumbnail']['url'] 	= 	WP_MEDIA_URL . '/'. $att_meta['file']; 	// don't include subdir when building paths, as we're taking path directly from 'file', which already includes it...
				$img['thumbnail']['path'] 	= 	WP_MEDIA_DIR . '/'. $att_meta['file'];
				$img['full']['file'] 	= 	basename($att_meta['file']);
				$img['full']['url'] 	= 	WP_MEDIA_URL . '/'. $att_meta['file'];
				$img['full']['path'] 	= 	WP_MEDIA_DIR . '/'. $att_meta['file'];
				$img['full']['height'] 	= 	$att_meta['height'];
				$img['full']['width'] 	= 	$att_meta['width'];
				$img['loc']['path'] 	= 	WP_MEDIA_DIR;
				$img['loc']['url']		= 	WP_MEDIA_URL;

				//** FUTURE NOTE: might be good when there isn't a medium or large version of the image to default to some kind of "missing" image that actually says "image was too small"
			}

			$img['mime']			= 	get_post_mime_type($att_id);											// mime-type of file
			$img['date']			= 	get_the_date('M j, Y',$att_id) ." - ". get_the_time('h:iA',$att_id); 	// date attachment was created
			$img['orientation']		= 	somaFunctions::fetch_index($att_meta, 'orientation');					// custom attribute, generated with ACME system
			$img['original']		= 	somaFunctions::fetch_index($att_meta, 'original');						// custom attribute, generated with ACME system

			// should we pass along? too confusing... use the typical meta fields below instead
			// $img['image_meta'] = $att_meta['image_meta'];

			// need to fetch the post from the table to get all the extra fields
			$att_post = get_post($att_id);
			$img['title'] = $att_post->post_title;
			$img['description'] = $att_post->post_content;
			$img['caption'] = $att_post->post_excerpt;
			$img['alt'] = get_post_meta($att_id, '_wp_attachment_image_alt', true);

		} else {
		// nothing found, return generic placeholder image
			$img['id'] = false;
			$sizes = get_intermediate_image_sizes();
			foreach ($sizes as $size) {
				$img[$size]['url'] = SOMA_IMG . 'placeholder-image.png';
			}
			$img['thumbnail']['width'] = SOMA_THUMB_WIDTH;
			$img['thumbnail']['height'] = SOMA_THUMB_HEIGHT;
			$img['full']['name'] = 'MISSING IMAGE';
			$img['full']['url'] = SOMA_IMG . 'placeholder-image.png';
			$img['file']['path'] = SOMA_DIR . 'images/placeholder-image.png';
			$img['file']['file'] = 'placeholder-image.png';
		}

		// return just the requested URL
		if (!is_null($specific)) {
			if ($specific == 'filename') return $img['full']['file'];
			if (isset($att_meta['sizes']) && is_array($att_meta['sizes'])) {
				if (array_key_exists($specific, $img)) {
					return $img[$specific]['url'];			// this allows for custom image sizes to be passed via $specific
				} else {
					return $img['full']['url'];			// couldn't find the size requested, so just give original file
				}
			} else {
				return null;															// no sizes have been generated for this attachment
			}
		} else {
			return $img;	// return array of variants
		}

	}

	// handles saving and retrieving post_meta via serialized arrays
	public function asset_meta( $action = null, $post = null, $key = null, $value = null, $serialize = null, $use_prefix = true ) {
		if (is_wp_error($post)) return $post;
		if ( empty($post) || empty($action) ) return new WP_Error('missing', "Must pass ID and action...");
		if (is_object($post)) {
			$pid = $post->ID;
		} else {
			$pid = intval($post);
		}


		global $soma_options;										// fetch options
		if ( $serialize === null ) {
			$serialize = somaFunctions::fetch_index($soma_options, 'meta_serialize');			// use default var if not passed in params
			if ($serialize == 1) {									// explicit true if evaluates as true
				$serialize = true;
			} else {
				$serialize = false;									// default false
			}
		}
		$prefix = $soma_options['meta_prefix'];
		// if we're supposed to use a prefix and we have one...
		if ( $use_prefix && !empty($prefix) ) {
			if ( $serialize ) {
				$meta_key =  $prefix . "_asset_meta";
			} else {
				$meta_key = $prefix . "_" . $key;
			}
		} else {
			// just use the given key, no prefix
			$meta_key = $key;
		}



		switch ( $action ) {
			case ('save') :
				if (!$key) return new WP_Error('missing', "Must specify a field to save to...");
				if (!$value) return new WP_Error('missing', "Missing a value for $key...");

				$post_meta = get_post_meta( $pid, $meta_key, true);			// retrieve meta
				if (empty($value)) {
					if ( $serialize ) {
						unset($post_meta[$key]);							// remove key if value is empty (otherwise key will be saved with blank value)
					} else {
						return delete_post_meta( $pid, $meta_key ); 		// trash meta because value is empty
					}
				} else {													// set new value
					if ( $serialize ) {
						$post_meta[$key] = $value;
					} else {
						$post_meta = $value;
					}
				}
				return update_post_meta( $pid, $meta_key, $post_meta );		// rewrite the array to post_meta
			break;

			case ('get') :
				$post_meta = get_post_meta( $pid, $meta_key, true);			// retrieve meta
				if ( $serialize ) {
					if (!$key) return $post_meta;							// return whole meta array if no field specified
					return $post_meta[$key];								// return field
				} else {
					return $post_meta;
				}
			break;

			case ('delete') :
				// if (!$key) return delete_post_meta( $pid, $meta_key ); 	// note: this deletes the whole meta_key, all fields lost
				if (!$key) return new WP_Error('missing', "Missing a key...");
				$post_meta = get_post_meta( $pid, $meta_key, true);					// retrieve meta
				if ( !empty( $post_meta ) ) {										// if there was data to be deleted, proceed
					if ( $serialize ) {
						unset($post_meta[$key]); 									// trash meta
						return update_post_meta( $pid, $meta_key, $post_meta ); 	// rewrite the array to post_meta
					} else {
						return delete_post_meta( $pid, $meta_key ); 				// trash meta
					}
				} else {												// nothing to delete, so don't bother (this happens when post gets saved with fields still blank, and the save_post functions attempt to clear the metadata)
					return false;
				}
			break;

			default :
				return new WP_Error('action', "First parameter must be 'save', 'get', or 'delete'...");
			break;
		}
	}


	// returns all attachments (except featured image)
	public function fetch_attached_media($post = null, $mime = null, $include_featured = false) {
		if (is_wp_error($post)) return $post;
		if (empty($post)) return new WP_Error('missing', "must pass a post argument!");
		if (is_object($post)) {
			$pid = $post->ID;
		} else {
			$pid = intval($post);
		}

		$args = array(
			'post_parent' => $pid,
			'post_type' => 'attachment',
			'numberposts' => -1,
			'post_status' => 'any',
			'orderby' => 'menu_order',
			'order' => 'ASC',
			'exclude' => get_post_thumbnail_id($pid),
		);
		if ($include_featured == true) {
			unset($args['exclude']);
		}
		if (!empty($mime)) {
			// only return requested media type (audio/mpeg, video/mp4, image/jpeg, application/pdf, application/zip)
			$args['post_mime_type'] = $mime;
		}
		// fetch children (results in array of objects, even if only one exists)
		$kids = get_posts($args);
		// check if empty
		$media = ( !empty($kids) ) ? $kids : null;
		return $media;
	}

	function session_manager() {
		if (!session_id()) {
			session_start();
		}
		$_SESSION['media'] = WP_MEDIA_DIR;
		$_SESSION['images'] = SOMA_IMG;
		global $current_user;
		$_SESSION['user'] = $current_user->data ? $current_user->roles[0] : 'guest';
	}



	//** checks for activation of plugins that somaFramework is dependent on ------------------------------------------------------//
	function check_plugin_dependency() {
		// require scribu's p2p plugin
		global $soma_options;

		if ( somaFunctions::fetch_index($soma_options, 'p2p') && !function_exists('p2p_register_connection_type') ) {
			add_action( 'admin_notices', create_function('', "
				echo '<div id=\"message\" class=\"error\" style=\"font-weight: bold\"><p>PLUGIN REQUIRED: \"Posts 2 Posts\" - please <a href=\"http://scribu.net/wordpress/posts-to-posts\" target=\"_blank\">download</a> and/or activate!</p></div>';
			"));
		}

		// // require companion SOMA theme
		// $themes = get_themes();
		// if (!isset($themes['Stock Media Asset Manager'])) { // theme is not installed!
		// 	add_action('admin_notices', 'theme_install_error_msg' );
		// 	function theme_install_error_msg() {
		// 		echo '<div id="message" class="error" style="font-weight: bold">';
		// 		echo '<p>THEME MISSING: "Stock Media Asset Manager" - please <a href="' . network_admin_url() .'/themes.php">install!</a></p>';
		// 		echo '</div>';
		// 	}
		// }
		// if (get_current_theme() != 'Stock Media Asset Manager') { // theme is not activated!
		// 	add_action('admin_notices', 'theme_active_error_msg' );
		// 	function theme_active_error_msg() {
		// 		echo '<div id="message" class="updated" style="font-weight: bold">';
		// 		echo '<p>THEME NOT ACTIVE: "Stock Media Asset Manager" - please <a href="' . network_admin_url() .'/themes.php">activate!</a></p>';
		// 		echo '</div>';
		// 	}
		// }
	}

	//** generates full current url string with current query vars ------------------------------------------------------//
	function get_current_url() {
		$urlis = 'http://' . $_SERVER['HTTP_HOST'] . htmlentities($_SERVER['PHP_SELF']) . $_SERVER['REQUEST_URI'];
		return $urlis;
	}

	// displays warning error msg if wp version too old ------------------------------------------------------//
	function wp_version_check() {
		global $wp_version; #wp
		$new_admin_version = '3.3';
		$updateURL = "";
		if (version_compare($wp_version, $new_admin_version, '<')) {
			add_action( 'admin_notices', create_function('', "
				echo '<div id=\"message\" class=\"error\" style=\"font-weight: bold\"><p>WORDPRESS 3.3 MINIMUM REQUIRED - please update WP or de-activate this plugin!</p></div>';
			"));
		}
	}

	/**
	 * Displays (and returns) total number of posts within specified categories
	 *
	 * @param string $catslugs Comma separated category slugs
	 * @param bool $display (optional) Echo the result?
	 * @return int Post count
	 */
	function in_category_count($catslugs = '', $display = true) {
		global $wpdb;

		$post_count = 0;
		$slug_where = '';
		$catslugs_arr = split(',', $catslugs);

	 	foreach ($catslugs_arr as $catslugkey => $catslug) {
			if ( $catslugkey > 0 ) {
				$slug_where .= ', ';
			 }

	 		$slug_where .= "'" . trim($catslug) . "'";
		}

		$slug_where = "cat_terms.slug IN (" . $slug_where . ")";

		$sql =	"SELECT	COUNT( DISTINCT cat_posts.ID ) AS post_count " .
				"FROM 	" . $wpdb->term_taxonomy . " AS cat_term_taxonomy INNER JOIN " . $wpdb->terms . " AS cat_terms ON " .
							"cat_term_taxonomy.term_id = cat_terms.term_id " .
						"INNER JOIN " . $wpdb->term_relationships . " AS cat_term_relationships ON " .
							"cat_term_taxonomy.term_taxonomy_id = cat_term_relationships.term_taxonomy_id " .
						"INNER JOIN " . $wpdb->posts . " AS cat_posts ON " .
							"cat_term_relationships.object_id = cat_posts.ID " .
				"WHERE 	cat_posts.post_status = 'publish' AND " .
						"cat_posts.post_type = 'post' AND " .
						"cat_term_taxonomy.taxonomy = 'category' AND " .
						$slug_where;

		$post_count = $wpdb->get_var($sql);

		if ( $display ) {
			echo $post_count;
		}

		return $post_count;
	}

	// are we using this??
	function get_posts_related_by_taxonomySQL($post_id, $taxonomy, $args=array()) {
		global $wpdb;
		$sql = <<<SQL
			SELECT
			related.object_id
			FROM
			{$wpdb->term_relationships} post
			INNER JOIN {$wpdb->term_taxonomy} link ON post.term_taxonomy_id = link.term_taxonomy_id
			INNER JOIN {$wpdb->term_relationships} related ON post.term_taxonomy_id = related.term_taxonomy_id
			WHERE 1=1
			AND link.taxonomy=$taxonomy
			AND post.object_id=$post_id
			AND post.object_id<>related.object_id
			AND post.post_type==related.post_type
SQL;
	$post_ids = $wpdb->get_col($wpdb->prepare($sql));
	$post = get_post($post_id);
	$args = wp_parse_args($args,array(
		'post_type' => $post->post_type,
		'post__in' => $post_ids,
	));
	var_dump($args);
	return new WP_Query($args);
	}

	function get_posts_related_by_taxonomy($post_id,$taxonomy,$args=array()) {
		$query = new WP_Query();
		$terms = wp_get_object_terms($post_id,$taxonomy);
		if (count($terms)) {
			// Assumes only one term for per post in this taxonomy
			$post_ids = get_objects_in_term($terms[0]->term_id,$taxonomy);
			$post = get_post($post_id);
			$args = wp_parse_args($args,array(
				'post_type' => $post->post_type, // The assumes the post types match
				'post__in' => $post_ids,
				'post__not_in' => $post->ID,
				'taxonomy' => $taxonomy,
				'term' => $terms[0]->slug,
				));
			$query = new WP_Query($args);
		}
		return $query;
	}

	// checks if date string is actually formatted as desired
	function check_datetime_format($date = null, $format = "Y-m-d H:i:s") {
		if ($date == null) return false;

		$date = trim($date);
		$time = strtotime($date);

		$is_valid = date($format, $time) == $date;

		return $is_valid;
	}

	function fetch_start_timestamp($pid) {
		$date = somaFunctions::asset_meta('get', $pid, 'start_date');
		$time = somaFunctions::asset_meta('get', $pid, 'start_time');
		$start = strtotime($date . " " . $time);
		return $start;
	}

	function fetch_end_timestamp($pid) {
		$date = somaFunctions::asset_meta('get', $pid, 'end_date');
		$time = somaFunctions::asset_meta('get', $pid, 'end_time');
		$end = strtotime($date . " " . $time);
		return $end;
	}

	// shows an indicator in the admin side menu for pending items
	function show_pending_number( $menu ) {
		// currently this function returns count for site-wide posts, not by author, so disable for non-staff
		if (!SOMA_STAFF) return $menu;
		//
		$status = "pending";
		$types = get_post_types( array( '_builtin' => false  ), 'names' );
		foreach ($types as $type) {
			$num_posts = wp_count_posts( $type, 'readable' );
			$pending_count = 0;
			if ( !empty($num_posts->$status) )
				$pending_count = $num_posts->$status;

			// build string to match in $menu array
			$menu_str = 'edit.php?post_type=' . $type;

			// loop through $menu items, find match, add indicator
			foreach( $menu as $menu_key => $menu_data ) {
				if( $menu_str != $menu_data[2] )
					continue;
				$menu[$menu_key][0] .= " <span class='update-plugins count-$pending_count'><span class='plugin-count'>" . number_format_i18n($pending_count) . '</span></span>';
			}
		}
		return $menu;
	}

	/**
	* Kill WordPress execution and display HTML message with error message.
	*
	* @since 3.0.0
	* @access private
	*
	* @param string $message Error message.
	* @param string $title Error title.
	* @param string|array $args Optional arguements to control behaviour.
	* inspired by JPB_User_Caps
	*/
	function soma_wp_die_handler( $message, $title = '', $args = array() ) {
		var_dump(func_get_args());
		$defaults = array( 'response' => 500 );
		$r = wp_parse_args($args, $defaults);

		$have_gettext = function_exists('__');

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $message ) ) {
			if ( empty( $title ) ) {
				$error_data = $message->get_error_data();
				if ( is_array( $error_data ) && isset( $error_data['title'] ) )
					$title = $error_data['title'];
			}
			$errors = $message->get_error_messages();
			switch ( count( $errors ) ) :
			case 0 :
				$message = '';
				break;
			case 1 :
				$message = "<p>{$errors[0]}</p>";
				break;
			default :
				$message = "<ul>\n\t\t<li>" . join( "</li>\n\t\t<li>", $errors ) . "</li>\n\t</ul>";
				break;
			endswitch;
		} elseif ( is_string( $message ) ) {
			$message = "<p>$message</p>";
		}

		if ( isset( $r['back_link'] ) && $r['back_link'] ) {
			$back_text = $have_gettext? __('&laquo; Back') : '&laquo; Back';
			$message .= "\n<p><a href='javascript:history.back()'>$back_text</p>";
		}

		if ( defined( 'WP_SITEURL' ) && '' != WP_SITEURL )
			$admin_dir = WP_SITEURL . '/wp-admin/';
		elseif ( function_exists( 'get_bloginfo' ) && '' != get_bloginfo( 'wpurl' ) )
			$admin_dir = get_bloginfo( 'wpurl' ) . '/wp-admin/';
		elseif ( strpos( $_SERVER['PHP_SELF'], 'wp-admin' ) !== false )
			$admin_dir = '';
		else
			$admin_dir = 'wp-admin/';

		if ( !function_exists( 'did_action' ) || !did_action( 'admin_head' ) ) :
		if ( !headers_sent() ) {
			status_header( $r['response'] );
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
		}

		if ( empty($title) )
			$title = $have_gettext ? __('WordPress &rsaquo; Error') : 'WordPress &rsaquo; Error';

		$text_direction = 'ltr';
		if ( isset($r['text_direction']) && 'rtl' == $r['text_direction'] )
			$text_direction = 'rtl';
		elseif ( function_exists( 'is_rtl' ) && is_rtl() )
			$text_direction = 'rtl';
	?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<!-- Ticket #11289, IE bug fix: always pad the error page with enough characters such that it is greater than 512 bytes, even after gzip compression abcdefghijklmnopqrstuvwxyz1234567890aabbccddeeffgghhiijjkkllmmnnooppqqrrssttuuvvwwxxyyzz11223344556677889900abacbcbdcdcededfefegfgfhghgihihjijikjkjlklkmlmlnmnmononpopoqpqprqrqsrsrtstsubcbcdcdedefefgfabcadefbghicjkldmnoepqrfstugvwxhyz1i234j567k890laabmbccnddeoeffpgghqhiirjjksklltmmnunoovppqwqrrxsstytuuzvvw0wxx1yyz2z113223434455666777889890091abc2def3ghi4jkl5mno6pqr7stu8vwx9yz11aab2bcc3dd4ee5ff6gg7hh8ii9j0jk1kl2lmm3nnoo4p5pq6qrr7ss8tt9uuvv0wwx1x2yyzz13aba4cbcb5dcdc6dedfef8egf9gfh0ghg1ihi2hji3jik4jkj5lkl6kml7mln8mnm9ono -->
	<html xmlns="http://www.w3.org/1999/xhtml" <?php if ( function_exists( 'language_attributes' ) ) language_attributes(); ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title><?php echo $title ?></title>
		<link rel="stylesheet" href="<?php echo $admin_dir; ?>css/install.css" type="text/css" />
	<?php
	if ( 'rtl' == $text_direction ) : ?>
		<link rel="stylesheet" href="<?php echo $admin_dir; ?>css/install-rtl.css" type="text/css" />
	<?php endif; ?>
	</head>
	<body id="error-page">
	<?php endif; ?>
		<?php echo $message; ?>
	</body>
	</html>
	<?php
		die();
	}

	//** end soma_wp_die_handler

	// Remove 'Administrator' from the list of roles if the current user is not an admin
	function editable_roles( $roles ){
		if( isset( $roles['administrator'] ) && !current_user_can('administrator') ){
			unset( $roles['administrator']);
		}
		return $roles;
	}

	// If someone is trying to edit or delete an admin and that user isn't an admin, don't allow it -- IS THIS STILL NEEDED?
	function admin_map_meta_cap( $caps, $cap, $user_id, $args ){

		switch( $cap ){
			case 'edit_user':
			case 'remove_user':
			case 'promote_user':
				if( isset($args[0]) && $args[0] == $user_id )
					break;
				elseif( !isset($args[0]) )
					$caps[]  = 'do_not_allow';
				$other    = new WP_User( absint($args[0]) );
				if( $other->has_cap( 'administrator' ) ){
					if(!current_user_can('administrator')){
						$caps[] = 'do_not_allow';
					}
				}
			break;
			case 'delete_user':
			case 'delete_users':
				if( !isset($args[0]) )
					break;
				$other    = new WP_User( absint($args[0]) );
				if( $other->has_cap( 'administrator' ) ){
					if(!current_user_can('administrator')){
						$caps[] = 'do_not_allow';
					}
				}
			break;
			default:
			break;
		}
		return $caps;
	}

	// AJAX delete images on the fly.
	// can't use nonce checking because the elements calling this function are dynamically created in javascript
	function ajax_unlink_file() {
		$file = somaFunctions::fetch_index($_POST, 'data');		// retrieve file from ajax post
		if ($file && file_exists($file)) {						// make sure it's there
			$delete = unlink($file);							// kill it
			if ($delete) {
				$response = array( 'success' => true, 'msg' => 'Successfully deleted the uploaded file!' );
			} else {
				$response = array( 'error' => true, 'msg' => 'Could not delete the uploaded file...' );
			}
		} else {
			$response = array( 'error' => true, 'msg' => 'Could not find the file to delete...' );
		}
		echo json_encode( $response );
		exit;
	}

	// AJAX delete attachments
	function ajax_delete_attachment() {
		$nonce = check_ajax_referer( 'soma-delete-attachment', 'nonce', false );
		if (!$nonce) {
			$response = array( 'error' => true, 'msg' => 'nonce verification failed...' );
			echo json_encode( $response );
			exit;
		}
		$att_id = somaFunctions::fetch_index($_POST, 'data');
		if ($att_id) {
			$result = wp_delete_attachment($att_id, true);
			$response = array( 'success' => true, 'msg' => $result );
		} else {
			$response = array( 'error' => true, 'msg' => 'Could not find the attachment...' );
		}
		echo json_encode( $response );
		exit;
	}



	// grabs metadata from public vimeo API, returns array
	function fetch_vimeo_meta( $link = null ) {
		if ( !$link ) return new WP_Error('missing', "Must specify a video ID");
		// use the oembed API to parse the raw URL for us
		$oembed = json_decode(file_get_contents("http://vimeo.com/api/oembed.json?url=".urlencode($link)), true);
		$meta = array_shift(unserialize(file_get_contents("http://vimeo.com/api/v2/video/{$oembed['video_id']}.php")));
		$meta = array_merge($oembed, $meta);
		return $meta;
	}

	// grabs metadata from public youtube API, returns array
	function fetch_youtube_meta( $url = null ) {
		if ( !$url ) return new WP_Error('missing', "Must specify a video ID");
		// extract ID
		if (preg_match('%(?:youtube\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match)) {
		    $id = $match[1];
		}
		$meta = json_decode(file_get_contents("http://gdata.youtube.com/feeds/api/videos/$id?v=2&alt=json"), true);     // json_decode true returns data as array instead of object
		$meta = $meta['entry'];     // isolate the single entry we requested
		return $meta;
	}

	// grab metadata from external site URL
	// only supporting 3 media sites right now: youtube, vimeo, soundcloud
	function fetch_external_media($url = null, $width = null, $height = null) {
		if ( !$url ) return new WP_Error('missing', "Must specify a valid URL from a supported site");

		// in case user didn't include http
		if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
			$url = "http://" . $url;
	    }

		// identify which site
		switch (true) {
			case ( strpos( $url, 'youtube.com' ) > 0 || strpos ($url, 'youtu.be' ) > 0 ) :
				$site = 'youtube';
			break;
			case ( strpos( $url, 'vimeo.com' ) > 0 ) :
				$site = 'vimeo';
			break;
			case ( strpos( $url, 'soundcloud.com' ) > 0 ) :
				$site = 'soundcloud';
			break;
			default:
				return new WP_Error('missing', "Problem with the URL. Can't figure out which site it's from (or it's not supported). Check to make sure you entered it correctly...");
			break;
		}

		$media = array();	// init container
		$width = $width ? $width : "853";			// iframe default
		$height = $height ? $height : "480";		// iframe default

		// extract ID, fetch meta, build output
		switch ($site) {
			case "youtube";
				if (preg_match('%(?:youtube\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match)) {
				    $id = $match[1];
					$meta = json_decode(file_get_contents("http://gdata.youtube.com/feeds/api/videos/$id?v=2&alt=json"), true);				// json_decode true returns data as array instead of object
					$meta = $meta['entry'];																									// isolate the single entry we requested
					$media['url']		= "http://www.youtube.com/watch?v=$id";																	// rebuild external url
					$media['id']		= $id;
					$media['site']		= $site;
					$media['thumb']		= $meta['media$group']['media$thumbnail'][1]['url'];												// grabs url of medium 360x480 image
					$media['title']		= stripslashes($meta['media$group']['media$title']['$t']);
					$media['desc']		= stripslashes($meta['media$group']['media$description']['$t']);
					$media['duration']	= $meta['media$group']['media$content'][0]['duration'];
					$media['mobile']	= "youtube:$id";																					// launches youtube app, i think...
					$media['embed'] 	= "<iframe width=\"$width\" height=\"$height\" src=\"http://www.youtube.com/embed/$id?rel=0&hd=1\" frameborder=\"0\" allowfullscreen></iframe>";
					$media['iframe']	= "http://www.youtube.com/embed/$id?rel=0&hd=1&autoplay=1";											// url to pass to iframe renderers
					$media['api']		= $meta;																							// we include everything we got from the site API, just in case (each site formats differently)
				} else {
					return new WP_Error('missing', "Can't extract ID from youtube URL...");
				}
			break;
			case "vimeo";
				if ($oembed = json_decode(file_get_contents("http://vimeo.com/api/oembed.json?url=".urlencode($url)), true)) {				// get oembed metadata
					$id = $oembed['video_id'];																								// extract id from api response
					$meta = array_shift(unserialize(file_get_contents("http://vimeo.com/api/v2/video/$id.php")));							// get more metadata (note: we're making two distinct calls to API for vimeo)
					$meta = array_merge($oembed, $meta);																					// combine the two (including some overlapping keys)
					$media['url']		= "http://vimeo.com/$id";																			// rebuild external url
					$media['id']		= $id;																								// store raw media ID
					$media['site']		= $site;																							//
					$media['thumb']		= $meta['thumbnail_url'];																			// grabs url of default image 640x360
					$media['title']		= stripslashes($meta['title']);																					//
					$media['desc']		= stripslashes($meta['description']);																				//
					$media['duration']	= $meta['duration'];																				// in seconds
					$media['mobile']	= $meta['mobile_url'];																				// mobile-friendly display url
					$media['embed']		= $meta['html'];																					// auto embed code (should be HTML5/ipad compatible)
					$media['iframe']	= "http://player.vimeo.com/video/$id?title=0&amp;byline=0&amp;portrait=0&amp;&amp;frameborder=0&amp;autoplay=1";			// url to pass to iframe renderers (like colorbox)
					$media['api']		= $meta;																							// we include everything we got from the site API, just in case (each site formats differently)
				} else {
					return new WP_Error('missing', "Can't extract ID from vimeo URL...");
				}
			break;
			case "soundcloud";
				$clientid = "006b3d9b3bbd5bd6cc7d27ab05d9a11b";		// my soundcloud api id
				if ($meta = json_decode(file_get_contents("http://api.soundcloud.com/resolve?client_id=".$clientid."&format=json&url=".urlencode($url)), true)) {
				//	$oembed = json_decode(file_get_contents("http://soundcloud.com/oembed?format=json&show_comments=false&auto_play=true&url=".urlencode($url)), true);
					$media['id']		= $meta['id'];
					$media['url']		= $meta['permalink_url'];
					$media['site']		= $site;
					if (empty($meta['artwork_url'])) {
						$media['thumb']	= $meta['waveform_url'];
					} else {
						$media['thumb']	= $meta['artwork_url'];
					}
					$media['title']		= $meta['title'];
					$media['desc']		= $meta['description'];
					$media['duration']	= $meta['duration'];
					$media['mobile']	= $meta['stream_url']."?client_id=006b3d9b3bbd5bd6cc7d27ab05d9a11b";
					$media['direct']	= $meta['stream_url']."?client_id=006b3d9b3bbd5bd6cc7d27ab05d9a11b";
					$media['iframe']	= "http://w.soundcloud.com/player/?url=".$meta['uri']."?auto_play=true&show_artwork=false&show_comments=false&color=000000&show_playcount=false&sharing=false&show_user=false&liking=false";				// http://developers.soundcloud.com/docs/oembed   --  https://github.com/soundcloud/Widget-JS-API/wiki/widget-options
					$media['embed']		= '<iframe width="100%" height="166" scrolling="no" frameborder="no" src="'.$media['iframe'].'"></iframe>';
					$media['format']	= $meta['original_format'];
					$media['api']		= $meta;													// mp3 in this case
				} else {
					return new WP_Error('missing', "Can't extract ID from soundcloud URL...");
				}
			break;
		}

		if (empty($media)) return new WP_Error('missing', "Somehow we failed...");
		// success
		return $media;
	}


	// retrieves ID of attachment from given URL
	// source: Rarst  http://wordpress.stackexchange.com/questions/6645/turn-a-url-into-an-attachment-post-id
	function get_attachment_id_from_url( $url ) {

		$dir = wp_upload_dir();
		$dir = trailingslashit($dir['baseurl']);

		if( false === strpos( $url, $dir ) )
			return new WP_Error('missing', "Something wrong with this url...");

		$file = basename($url);

		$query = array(
			'post_type' => 'attachment',
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'value' => $file,
					'compare' => 'LIKE',
					)
			)
		);

		$query['meta_query'][0]['key'] = '_wp_attached_file';
		$ids = get_posts( $query );

		foreach( $ids as $id )
			if ( $url == array_shift( wp_get_attachment_image_src($id, 'full') ) )
				return $id;

		$query['meta_query'][0]['key'] = '_wp_attachment_metadata';
		$ids = get_posts( $query );

		foreach( $ids as $id ) {

			$meta = wp_get_attachment_metadata($id);

			foreach( $meta['sizes'] as $size => $values )
			if( $values['file'] == $file && $url == array_shift( wp_get_attachment_image_src($id, $size) ) ) {

				return $id;
			}
		}

		return new WP_Error('missing', "Somehow we failed...");
	}

	/**
	 * Download an image from the specified URL and attach it to a post.
	 * Modified version of core function media_sideload_image() in /wp-admin/includes/media.php  (which returns an html img tag instead of attachment ID)
	 * Additional functionality: ability override actual filename, set as post thumbnail, and to pass $post_data to override values in wp_insert_attachment (original only allowed $desc)
	 *
	 * @since 1.4
	 *
	 * @param string $url (required) The URL of the image to download
	 * @param int $post_id (required) The post ID the media is to be associated with
	 * @param bool $thumb (optional) Whether to make this attachment the Featured Image for the post
	 * @param string $filename (optional) Replacement filename for the URL filename (do not include extension)
	 * @param array $post_data (optional) Array of key => values for wp_posts table (ex: 'post_title' => 'foobar', 'post_status' => 'draft')
	 * @return int|object The ID of the attachment or a WP_Error on failure
	 */
	function attach_external_image( $url = null, $post_id = null, $thumb = null, $filename = null, $post_data = array() ) {
		if ( !$url || !$post_id ) return new WP_Error('missing', "Need a valid URL and post ID...");
		if ( !self::array_is_associative( $post_data ) ) return new WP_Error('missing', "Must pass post data as associative array...");

		// Download file to temp location, returns full server path to temp file, ex; /home/somatics/public_html/mysite/wp-content/26192277_640.tmp MUST BE FOLLOWED WITH AN UNLINK AT SOME POINT
		$tmp = download_url( $url );

		// If error storing temporarily, unlink
		if ( is_wp_error( $tmp ) ) {
			@unlink($file_array['tmp_name']);	// clean up
			$file_array['tmp_name'] = '';
			return $tmp; // output wp_error
		}

		preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);	// fix file filename for query strings
		$url_filename = basename($matches[0]);													// extract filename from url for title
		$url_type = wp_check_filetype($url_filename);											// determine file type (ext and mime/type)

		// override filename if given, reconstruct server path
		if ( !empty( $filename ) ) {
			$filename = sanitize_file_name($filename);
			$tmppath = pathinfo( $tmp );														// extract path parts
			$new = $tmppath['dirname'] . "/". $filename . "." . $tmppath['extension'];			// build new path
			rename($tmp, $new);																	// renames temp file on server
			$tmp = $new;																		// push new filename (in path) to be used in file array later
		}

		// assemble file data (should be built like $_FILES since wp_handle_sideload() will be using)
		$file_array['tmp_name'] = $tmp;															// full server path to temp file

		if ( !empty( $filename ) ) {
			$file_array['name'] = $filename . "." . $url_type['ext'];							// user given filename for title, add original URL extension
		} else {
			$file_array['name'] = $url_filename;												// just use original URL filename
		}

		// set additional wp_posts columns
		if ( empty( $post_data['post_title'] ) ) {
			$post_data['post_title'] = basename($url_filename, "." . $url_type['ext']);			// just use the original filename (no extension)
		}

		// make sure gets tied to parent
		if ( empty( $post_data['post_parent'] ) ) {
			$post_data['post_parent'] = $post_id;
		}

		// required libraries for media_handle_sideload
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		// do the validation and storage stuff
		$att_id = media_handle_sideload( $file_array, $post_id, null, $post_data );				// $post_data can override the items saved to wp_posts table, like post_mime_type, guid, post_parent, post_title, post_content, post_status

		// If error storing permanently, unlink
		if ( is_wp_error($att_id) ) {
			@unlink($file_array['tmp_name']);	// clean up
			return $att_id; // output wp_error
		}

		// set as post thumbnail if desired
		if ($thumb) {
			set_post_thumbnail($post_id, $att_id);
		}

		return $att_id;
	}

	/**
	* Gets the excerpt of a specific post ID or object - if one doesn't exist, it will create one dynamically
	* @since 1.7.3
	*
	* @param - $post - object/int/string - the ID or object of the post to get the excerpt of
	* @param - $length - int - the length of the excerpt in words
	* @param - $tags - string - the allowed HTML tags. These will not be stripped out
	* @param - $extra - string - text to append to the end of the excerpt
	*/
	function fetch_excerpt($post, $length = 30, $tags = '<a><em><strong>', $extra = '…') {

		if (is_wp_error($post)) return $post;
		if (empty($post)) return new WP_Error('missing', "must pass a post argument!");
		if (!is_object($post)) {
			$post = get_post(intval($post));
		}
		if (!is_object($post)) {
			return new WP_Error('error', "Can't retrieve the requested post...");
		}

		if (has_excerpt($post->ID)) {
			$the_excerpt = $post->post_excerpt;
			return apply_filters('the_content', $the_excerpt);
		} else {
			$the_excerpt = $post->post_content;
		}

		$the_excerpt = strip_shortcodes(strip_tags($the_excerpt), $tags);
		$the_excerpt = preg_split('/\b/', $the_excerpt, $length * 2+1);
		$excerpt_waste = array_pop($the_excerpt);
		$the_excerpt = implode($the_excerpt);
		$the_excerpt .= $extra;

		return apply_filters('the_content', $the_excerpt);
	}

	/**
	* generates HTML link code in conjunction with the soma_go_redirect option
	* won't work unless you've already hooked the soma_go_redirect_codes filter and added a slug/url pair
	* @since 1.7.6
	*
	* @param - $slug - string - properly formatted slug for the url: /go/[slug]
	* @param - $text - string - text to be wrapped inside the <a> tags
	* @return - string - HTML link code
	*/
	function make_go_link($slug = null, $text = null) {
		if (empty($slug) || empty($text)) return new WP_Error('missing', "must pass a slug and text argument!");
		$slug = sanitize_title_with_dashes($slug);
		$text = sanitize_title($text);
		$codes = apply_filters('soma_go_redirect_codes', $codes);
		if (array_key_exists($slug, $codes)) {
			return "<a href='{$codes[$slug]}' rel='nofollow'>$text</a>";
		} else {
			return new WP_Error('error', "that slug hasn't been added yet, so I can't give you a URL ...");
		}

	}

	// Returns array of details about a file (only works on attachments)
	// -------------------------------------------------------------
	function fetch_file($pid = null) {
		if ( empty( $pid ) ) return false;
		$type = get_post_type( intval( $pid ) );
		if ( $type != "attachment" ) return false;

		$file_path = get_attached_file( $pid );
		$file = pathinfo( $file_path );  											// returns array of dirname, basename, extension, filename
		$file['mime'] = get_post_mime_type( $pid );									// returns mime type
		$file['url'] = wp_get_attachment_url( $pid );								// returns full URL for downloading
		$file['secure'] = self::build_request_link('legacy-download', $pid);		// returns secure download URL
		if ( is_file( SOMA_DIR . "images/file-icons/" . $file['extension'] . ".png" ) ) {
			$file['icon'] = SOMA_IMG . 'file-icons/'. $file['extension'].'.png';
		} else {
			$file['icon'] = SOMA_IMG . 'file-icons/bin.png';
		}
		$file['time'] = get_post_time("U", true, $pid);
		return $file;
	}

	// Return all sub pages of a given post
	// -------------------------------------------------------------
	function fetch_sub_pages($pid = null) {
		if (empty($pid)) return null;
		$post_type = get_post_type($pid);
		$args = array(
			'post_type' => $post_type,
			'child_of' => $pid,
			'parent' => $pid,
			'sort_order' => 'ASC',
			'sort_column' => 'menu_order',
			'numberposts' => -1
		);
		return get_pages($args);
	}

	// Return all root (top-level) posts of any given post type
	// -------------------------------------------------------------
	function fetch_root_pages($post_type = 'page') {
		$args = array(
			'post_type' => $post_type,
			'parent' => 0,
			'order' => 'ASC',
			'orderby' => 'menu_order',
			'numberposts' => -1
		);
		return get_pages($args);
	}

	/**
	* Helps in use of dash and underscore in field ID's of richtext field types (dealing with wp_editor() finicky rules)
	* @since 1.8.1
	*
	* @param - $id - string - user defined ID for this field, anything that's not a lowercase letter or a dash or underscore will get trashed!!
	* @return - $sanID - string - safe ID with obfuscations
	*/

	function sanitize_wpeditor_id($id) {
		$sanID = preg_replace('/_+/', 'uuuuu', $id);			// to preserve some backwards compatibility, we'll make special replacements for dashes and underscores, so they'll get preserved
		$sanID = preg_replace('/-+/', 'ddddd', $sanID);
		$sanID = preg_replace('/[^a-z]+/', '', $sanID);			// final pass to eliminate all non-alpha characters
		return $sanID;
	}


	function unsanitize_wpeditor_id($id) {
		$unsanID = preg_replace('/uuuuu+/', '_', $id);			// to preserve some backwards compatibility, we'll make special replacements for dashes and underscores, so they'll get preserved
		$unsanID = preg_replace('/ddddd+/', '-', $unsanID);
		return $unsanID;
	}


	/**
	* Constructs URL string to be used to trigger the soma_request_ action hook of the somaRequest Class
	* typically used to pass an array of data to a custom function
	*
	* @since 1.8.5
	*
	* @param - $action - (string) identifier used to assemble the action hook
	* @return - $data - (string/array) - stuff to be sent to the hooked function
	*/

	function build_request_link($action = null, $data = null) {
		if (empty($action) || empty($data)) return new WP_Error('error', 'action or data missing for the request!');
		$safedata = http_build_query(array('data' => $data));			// prepare data contents (even associative or indexed arrays) for inclusion in the URL
		$link = get_option('siteurl') . "?soma_request=$action&$safedata&security=" . wp_create_nonce( "soma-request" );
		return $link;
	}

}
// --> END class somaFunctions
// INIT
$somaFunctions = new somaFunctions();
?>