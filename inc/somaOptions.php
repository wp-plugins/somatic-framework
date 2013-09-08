<?php
/**
 * Used to set/modify various options
 *
 * @since 1.6
 */
class somaOptions extends somaticFramework  {

	function __construct() {
		add_action( 'init', array( __CLASS__, 'soma_global_options' ), 7 );     	  				// populate global variable with options to avoid additional DB queries - try to hook earlier than normal...
		add_action( 'init', array( __CLASS__, 'soma_go_endpoint') );								// creates new permalink endpoint of /go/[slug], used for redirects
		add_action( 'template_redirect', array( __CLASS__, 'soma_go_redirect' ) );					// logic to redirect
		add_filter( 'soma_go_redirect_codes', array( __CLASS__, 'soma_default_links' ), 1, 1 );		// default redirect links
		// add_action( 'init', array( __CLASS__, 'show_admin_bar' ), 9 );        					// gotta get in early to execute before _wp_admin_bar_init()
		add_action( 'wp', array( __CLASS__, 'soma_cron' ) );           								// init our cron
		add_action( 'soma_daily_event', array( __CLASS__, 'delete_autodrafts' ) );     				// fire this every day
		// add_action( 'personal_options', array(__CLASS__, 'hide_profile_options') );   			// hides some useless cruft, make profile simpler
		add_action( 'user_register', array( __CLASS__, 'full_display_name' ) );     				// automatically populate display name with fullname
		add_filter( 'user_contactmethods', array( __CLASS__, 'extend_user_contactmethod' ) );	   	// mods contact fields on user profile
		add_action( 'admin_menu', array( __CLASS__, 'add_pages' ) );         						// adds menu items to wp-admin
		add_action( 'admin_init', array( __CLASS__, 'register_soma_options' ) ); 				    // register settings to help with form saving and sanitizing
		add_action( 'admin_action_flush', array( __CLASS__, 'flush_rules' ) );   					// dynamically generated hook created by the ID on forms POSTed from admin.php
		add_action( 'admin_action_export', array( __CLASS__, 'export_settings' ) );   				// dynamically generated hook created by the ID on forms POSTed from admin.php
		add_action( 'admin_action_import', array( __CLASS__, 'import_settings' ) );   				// dynamically generated hook created by the ID on forms POSTed from admin.php
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'disable_dashboard_widgets' ), 100 );  // disable dashboard widgets
		add_action( 'admin_menu', array( __CLASS__, 'disable_admin_menus' ) );      // hide the admin sidebar menu items
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'disable_autosave' ) );    // optional disable autosave
		add_action( 'init', array( __CLASS__, 'disable_revisions' ), 50 );       // optional disable revisions
		add_filter( 'parse_query', array( __CLASS__, 'disable_paging' ) );        // optional disable auto-paging
		add_action( 'do_meta_boxes', array( __CLASS__, 'disable_metaboxes' ), 10, 3 );     // removes metaboxes from post editor
		add_filter( 'sanitize_option_somatic_framework_options', array( __CLASS__, 'sanitize_soma_options' ), 10, 2 );  // hooks into core update_option function to allow sanitizing before saving
		add_action( 'wp_before_admin_bar_render', array( __CLASS__, 'disable_admin_bar_links' ) ); // removes admin bar items
		add_action( 'get_header', array( __CLASS__, 'enable_threaded_comments' ) );     // enables threaded comments
		// add_filter( 'screen_options_show_screen', array( __CLASS__, 'disable_screen_options' ), 10, 2 );  // optional disable screen options tab NOT WORKING
		add_action( "user_register", array( __CLASS__, "user_admin_bar_false_by_default" ), 10, 1 ); // force option off when new user created
		add_filter( 'the_content', array( __CLASS__, 'always_colorbox'), 50);

		// add_action( 'show_user_profile', array(__CLASS__, 'show_extra_profile_fields') );  // unused
		// add_action( 'edit_user_profile', array(__CLASS__, 'show_extra_profile_fields') );  // unused
		// add_action( 'edit_user_profile_update', array(__CLASS__, 'save_extra_profile_fields') ); // unused
		// add_action( 'personal_options_update', array(__CLASS__, 'save_extra_profile_fields') ); // unused
		// add_action( 'admin_action_purge', array(__CLASS__,'purge_all_data' ) );     // dynamically generated hook created by the ID on forms POSTed from admin.php
		// add_action( 'profile_update', array(__CLASS__, 'full_display_name') );      // automatically populate display name with fullname ** NOTE DISABLED THIS BECAUSE OF INFINITE LOOP CONFLICT WITH ANY OTHER PLUGIN THAT MODIFIES USER DATA
		// add_action( 'admin_init', array( __CLASS__, 'disable_drag_metabox' ) );      // disable dragging of any metaboxes (including dashboard widgets) didn't really work?
	}

	//** sets somatic framework options defaults on Activation of plugin
	// if there are no theme options currently set, or the user has selected the checkbox to reset options to their defaults then the options are set/reset.
	function init_soma_options() {

		$defaults = array(
			"favicon" => "",            // full url path to a .png or .ico, usually set in a theme - framework will output <head> tags
			"login_logo" => "",            // full url path to a .png, usually set in a theme - framework will output inline CSS to display login logo, overriding default WP one
			"debug" => 1,             // debug mode output enabled (renders to debug bar if installed, ouput inline if not)
			"p2p" => 0,              // require posts 2 posts plugin by scribu
			"colorbox" => 0,            // enqueue Colorbox lightbox JS on front-end pages too (always active in admin)
			"meta_prefix" => "_soma",          // prefix added to post_meta keys
			"meta_serialize" => 0,           // whether to serialize somatic post_meta
			// 'bottom_admin_bar' => 0,          // pin the admin bar to the bottom of the window
			// "always_show_bar" => 0,           // show the top admin bar on the front-end always, even if not logged in, but still respect user preferences
			"kill_paging" => array(),          // array of post types slugs to filter wp_query to prevent automatic paging and always list all items
			"kill_autosave" => array(),          // array of post types slugs to disable autosave
			"kill_revisions" => array(),         // array of post types slugs to disable autosave
			"disable_menus" => array( 'links', 'tools' ),                             // hide admin sidebar menu items from everyone (but you could still go to the page directly)
			"disable_dashboard" => array( 'quick_press', 'recent_drafts', 'recent_comments', 'incoming_links', 'plugins', 'primary', 'secondary', 'thesis_news_widget' ),  // hide dashboard widgets from everyone
			"disable_metaboxes" => array( 'thesis_seo_meta', 'thesis_image_meta', 'thesis_multimedia_meta', 'thesis_javascript_meta', 'tb_page_options' ),         // hide metaboxes in post editor from everyone
			"disable_drag_metabox" => 0,         // prevent users from dragging/rearranging metaboxes (even dashboard widgets)
			"go_redirect" => 0,
			"fulldisplayname" => 1,
			// "disable_screen_options" => 0,         // hide the screen options tab
			"reset_default_options" => 0,         // will reset options to defaults next time plugin is activated
			"plugin_db_version" => SOMA_VERSION,
		);

		/* core WP metabox slugs for disabling:
	   * submitdiv – The “Publish” box that allows you to set several things.
	   * commentsdiv – Displays comments made on the post.
	   * trackbacksdiv – Displays an input box for sending trackbacks.
	   * commentstatusdiv – Allows you to enable/disable comments and pings for the post.
	   * revisionsdiv – Displays post revision links.
	   * authordiv – Displays a select box to choose a post author.
	   * postexcerpt – Creates a textarea for writing a custom excerpt.
	   * formatdiv – Allows the user to select a post format.
	   * pageparentdiv – The “Attributes” box for choosing a page parent and template.
	   * postimagediv – Displays the featured image box.
	   * slugdiv – Displays an additional post slug box.
	   * tagsdiv-post_tag – Displays the post tags meta box for selecting tags.
	   * categorydiv – Displays the categories meta box for selecting categories.
	   * tagsdiv-{$taxonomy} – Lets you choose terms for a non-hierarchical taxonomy (use the taxonomy name).
	   * {$taxonomy}div – Allows you to set terms for a hierarchical taxonomy (use the taxonomy name).
	*/

		$current = get_option( 'somatic_framework_options', null );        // fetch current options if they exist

		if ( ( is_null( $current ) ) || ( $current['reset_default_options'] == '1' ) ) {  // options don't exist yet OR user has requested reset
			update_option( 'somatic_framework_options', $defaults );        // write defaults
			$current = get_option( 'somatic_framework_options' );        // populate $current again
		}

		// convert old options
		$old_serialize = get_option( 'soma_meta_serialize', null );
		if ( !is_null( $old_serialize ) ) {
			$current['meta_serialize'] = $old_serialize;
			update_option( 'somatic_framework_options', $current );
			delete_option( 'soma_meta_serialize' );
		}
		// convert old options
		$old_prefix = get_option( 'soma_meta_prefix', null );
		if ( !is_null( $old_prefix ) ) {
			$current['meta_prefix'] = $old_prefix;
			update_option( 'somatic_framework_options', $current );
			delete_option( 'soma_meta_prefix' );
		}
		// convert old options
		$old_debug = get_option( 'soma_debug', null );
		if ( !is_null( $old_debug ) ) {
			$current['debug'] = $old_debug;
			update_option( 'somatic_framework_options', $current );
			delete_option( 'soma_debug' );
		}

		return $current;     // output settings array
	}

	// Delete options table entries ONLY when plugin deactivated AND deleted
	function delete_soma_options() {
		delete_option( 'somatic_framework_options' );
	}

	// init container for help text and options
	function register_soma_options() {
		// register_setting( 'soma_help', 'soma_help_text', array(__CLASS__, 'soma_help_validate' ) );
		register_setting( 'somatic_framework_plugin_options', 'somatic_framework_options', array( __CLASS__, 'soma_options_validate' ) );
	}

	// stash options in global var -- NOTE: since this is hooked to 'init', it won't be ready in time for usage by functions.php or other initial file loads - will have to just use get_option()
	function soma_global_options() {
		global $soma_options;
		$soma_options = get_option( 'somatic_framework_options', null );
		if ( is_null( $soma_options ) ) {
			$soma_options = self::init_soma_options();
		}
	}

	// schedule if event doesn't already exists
	function soma_cron() {
		if ( !wp_next_scheduled( 'soma_daily_event' ) ) {
			wp_schedule_event( time(), 'daily', 'soma_daily_event' );
		}
	}

	// kills auto draft posts (scheduled via cron daily)
	function delete_autodrafts() {
		$pargs = array(
			'post_type' => any,
			'post_status' => 'auto-draft',
			'numberposts' => -1,
		);
		$autodrafts = get_posts( $pargs );
		foreach ( $autodrafts as $draft ) {
			wp_delete_post( $draft->ID, true );
		}
		// sql query to delete revisions and their associated meta
		// DELETE a,b,c FROM wp_posts a WHERE a.post_type = 'revision' LEFT JOIN wp_term_relationships b ON (a.ID = b.object_id) LEFT JOIN wp_postmeta c ON (a.ID = c.post_id);

		// sql query to delete unused tags
		// SELECT * From wp_terms wt INNER JOIN wp_term_taxonomy wtt ON wt.term_id=wtt.term_id WHERE wtt.taxonomy='post_tag' AND wtt.count=0;

		// sql query to list unused post meta
		// SELECT * FROM wp_postmeta pm LEFT JOIN wp_posts wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL;
	}

	//** sets wordpress options
	function set_wp_options() {
		// update_option('blogname','Somatic Client Site');
		// update_option('blogdescription','Tagline Goes Here');
		// update_option('timezone_string','America/Denver');
		// update_option('thumbnail_size_h', 100);
		// update_option('thumbnail_size_w', 100);
		// update_option('thumbnail_crop', 1);
		// update_option('medium_size_w', 300);
		// update_option('medium_size_h', 300);
		// update_option('large_size_w', 800);
		// update_option('large_size_h', 800);
		// update_option('uploads_use_yearmonth_folders', 0);    // always hated this
		// update_option('blog_public', 1);        // let google in
		// update_option('comment_registration', 1);
		// update_option('default_role','author');
		// update_option('comment_moderation', 0);
		// update_option('default_ping_status','closed');
		// update_option('moderation_notify', 0);
		// update_option('comment_whitelist', 0);
		// update_option('thread_comments', 1);
		// update_option('require_name_email', 0);
		// update_option('comments_notify', 1);
		// update_option('enable_xmlrpc', 1);        // do we want to do this?
		// update_option('permalink_structure', '/%postname%/');   // default to pretty permalinks

		// $core_settings = array(
		//  'comments_notify' => 1,
		//  'enable_xmlrpc' => 1,
		//  'permalink_structure' => '/%postname%/'
		// );
		// foreach ( $core_settings as $k => $v ) {
		//  update_option( $k, $v );
		// }

		// delete dummy post, page and comment - DANGEROUS if this plugin is installed after someone is actually using those pages...
		// wp_delete_post(1, TRUE);
		// wp_delete_post(2, TRUE);
		// wp_delete_comment(1);
	}




	//** generate new roles and extend for custom post type capabilities ------------------------------------------------------//
	//  needs to be called elsewhere upon plugin activation
	function setup_capabilities() {

		// new manager role (expanding on core editor role)
		$editor = get_role( 'editor' );
		$editor_caps = $editor->capabilities;
		if ( $editor_caps == null ) $editor_caps = array(); // don't want to barf on array_merge later...
		$manager_caps = array(
			'edit_letters' => true,
			'edit_others_letters' => true,
			'edit_published_letters' => true,
			'publish_letters' => true,
			'delete_letters' => true,
			'delete_others_letters' => true,
			'delete_published_letters' => true,
			'edit_users' => true,
			'list_users' => true,
			'add_users' => true,
			'delete_users' => true,
			'create_users' => true,
			'remove_users' => true,
			'upload_files' => true,
			'read' => true,
		);
		$caps = array_merge( $editor_caps, $manager_caps );
		$manager = add_role( 'manager', 'Manager', $caps );
		if ( null == $manager ) {  // already exists, replace?? every time?
			remove_role( 'manager' );
			$manager = add_role( 'manager', 'Manager', $caps );
		}

		// get rid of unused core roles
		// remove_role('editor');
		// remove_role('subscriber');
		// remove_role('author');
		// remove_role('contributor');
	}

	// adds menu items to wp-admin
	function add_pages() {
		add_menu_page( 'Somatic Framework Options', 'Somatic', 'update_core', 'somatic-framework-options', null, SOMA_IMG.'soma-options-menu.png', '91' );
		add_submenu_page( 'somatic-framework-options', 'Framework Options', 'Options', 'update_core', 'somatic-framework-options', create_function( null, 'somaOptions::soma_options_page("options");' ) );
		add_submenu_page( 'somatic-framework-options', 'Declarations', 'Declarations', 'update_core', 'somatic-framework-declarations', create_function( null, 'somaOptions::soma_options_page("declarations");' ) );
		add_submenu_page( 'somatic-framework-options', 'Advanced', 'Advanced', 'update_core', 'somatic-framework-advanced', create_function( null, 'somaOptions::soma_options_page("advanced");' ) );
		// add_submenu_page('options-general.php', 'Help Text', 'Help Text', 'update_core', 'soma-help-editor', array(__CLASS__,'soma_help_page'), SOMA_IMG.'soma-downloads-menu.png');
	}

	// downloads a txt file with serialized array
	function export_settings() {
		check_admin_referer( 'soma-export', 'security' ); //wp
		header( "Cache-Control: public, must-revalidate" );
		header( "Pragma: hack" );
		header( "Content-Type: text/plain" );
		header( 'Content-Disposition: attachment; filename="somatic-framework-options-' . date( "Ymd" ) . '.dat"' );

		global $soma_options;

		echo serialize( $soma_options );
		exit();
	}

	// processes uploaded file and overwrites settings
	// NOTE - as this was called by "admin_action_***" hook, have to make sure conditional logic always results in a redirect, or else will just direct to an empty admin.php page!
	function import_settings() {
		if ( !empty( $_FILES ) && check_admin_referer( 'soma-import', 'security' ) ) {
			if ( $_FILES['file']['error'] > 0 ) {
				$result = add_query_arg( 'result', 'error-file', $_SERVER['HTTP_REFERER'] );
				wp_redirect( $result );
				exit;
			}
			elseif ( strpos( $_FILES['file']['name'], 'somatic-framework-options' ) === false ) {
				$result = add_query_arg( 'result', 'wrong-file', $_SERVER['HTTP_REFERER'] );
				wp_redirect( $result );
				exit;
			} else {
				$raw_options = file_get_contents( $_FILES['file']['tmp_name'] );
				$new_options = unserialize( $raw_options );

				if ( is_array( $new_options ) && version_compare( SOMA_VERSION, $new_options['plugin_db_version'], '<=' ) ) {
					$update = update_option( 'somatic_framework_options', $new_options );
					if ( $update ) {
						$result = add_query_arg( 'result', 'upload-success', $_SERVER['HTTP_REFERER'] );
						wp_redirect( $result );
					} else {
						$result = add_query_arg( 'result', 'upload-fail', $_SERVER['HTTP_REFERER'] );
						wp_redirect( $result );
					}
				} else {
					$result = add_query_arg( 'result', 'upload-fail', $_SERVER['HTTP_REFERER'] );
					wp_redirect( $result );
				}
				exit;
			}
		}
	}

	// NOTE - as this was called by "admin_action_***" hook, have to make sure conditional logic always results in a redirect, or else will just direct to an empty admin.php page!
	function flush_rules() {
		check_admin_referer( 'soma-flush', 'security' ); // die if invalid or missing nonce

		flush_rewrite_rules();

		$result = add_query_arg( 'result', 'flush-success', $_SERVER['HTTP_REFERER'] ); //
		wp_redirect( $result );  // send us back to plugin page
		exit();
	}


	// help page contents
	function soma_help_page() {
		if ( somaFunctions::fetch_index( $_REQUEST, 'settings-updated' ) ) {
			echo "<div id='notice_post' class='updated fade'><p>Text Saved...</p></div>";
		}  ?>
		<div class="wrap">
			<div id="icon-soma-editor" class="icon32"><br/></div>
			<h2>Help Text Editor</h2>

			<form action="options.php" method="post">
				<?php settings_fields( 'soma_help' ); // adds hidden form elements, nonce ?>

				<ul>
					<h3>Form Boxes</h3>
					<p><em>Enter the text to be shown at the top of each box. Leave empty if you don't want to display anything</em></p>
					<li><input type="submit" class="clicker" value="Save Text" /></li>
				<?php

		$types = get_post_types( array( '_builtin' => false, 'somatic' => true ), 'objects' );
		$helptext = get_option( 'soma_help_text' );
		foreach ( $types as $type ) {
			echo "<h4>$type->label</h4>";
			foreach ( somaMetaboxes::$data as $box ) {
				if ( in_array( $type->rewrite['slug'], $box['types'] ) ) {
					echo "<li><em>{$box['title']}</em></li>\n";
					echo "<li><textarea name=\"soma_help_text[{$box['id']}]\" id=\"soma_help_text[{$box['id']}]\" rows=\"4\">{$helptext[$box['id']]}</textarea></li>";
				}
			}
		}
?>
					<li><input type="submit" class="clicker" value="Save Text" /></li>
				</ul>
			</form>

		</div>
		<?php
	}

	function soma_help_validate( $input ) {
		// strip html
		array_walk( $input, create_function( '&$v,$k', '$v = wp_filter_nohtml_kses($v);' ) );
		return $input;
	}

	// options page contents
	function soma_options_page( $active_tab = null ) {

		if ( is_null( $active_tab ) ) $active_tab = 'options';
		if ( isset( $_GET[ 'tab' ] ) ) $active_tab = $_GET[ 'tab' ];

		// previous submit result messages
		if ( somaFunctions::fetch_index( $_GET, 'result' ) == 'purge-success' ) {
			echo "<div class='updated fade'><p>All data successfully purged!</p></div>";
		}
		if ( somaFunctions::fetch_index( $_GET, 'result' ) == 'export-success' ) {
			echo "<div class='updated fade'><p>All data successfully exported!</p></div>";
		}
		if ( somaFunctions::fetch_index( $_GET, 'result' ) == 'flush-success' ) {
			echo "<div class='updated fade'><p>Rewrite rules successfully flushed!</p></div>";
		}
		if ( somaFunctions::fetch_index( $_GET, 'result' ) == 'upload-success' ) {
			echo "<div class='updated fade'><p>Options successfully imported!</p></div>";
		}
		if ( somaFunctions::fetch_index( $_GET, 'result' ) == 'upload-fail' ) {
			echo "<div class='updated fade'><p>Something went wrong with the upload...</p></div>";
		}
		if ( somaFunctions::fetch_index( $_GET, 'result' ) == 'wrong-file' ) {
			echo "<div class='updated fade'><p>That is not a valid options file...</p></div>";
		}
		if ( somaFunctions::fetch_index( $_GET, 'result' ) == 'error-file' ) {
			echo "<div class='updated fade'><p>Something went wrong in the upload... try again.</p></div>";
		}

		// retrieve options
		global $soma_options;

		// soma_dump($soma_options);  // debug

		if ( $soma_options['reset_default_options'] ) {
			echo "<div class='updated fade'><p><strong>NOTICE:</strong> Settings are set be reset to defaults next time this plugin is activated!</p></div>";
		}

		echo "<style>#wpbody-content .icon32 { background: transparent url(\"".SOMA_IMG."soma-options.png\") no-repeat; !important }</style>";

		// retrieve custom post types
		$custom_types = get_post_types( array( "_builtin" => false, "somatic" => true ) );
		$custom_taxes = get_taxonomies( array( "_builtin" => false, "somatic" => true ) );

?>
		<div class="wrap somatic-options">
			<!-- page icon -->
			<div id="icon-options-general" class="icon32"></div>
			<!-- page title and tabbed navigation -->
			<h2 class="nav-tab-wrapper">
				<a href="?page=somatic-framework-options" class="nav-tab <?php echo $active_tab == 'options' ? 'nav-tab-active' : ''; ?>">Framework Options</a>
				<a href="?page=somatic-framework-declarations" class="nav-tab <?php echo $active_tab == 'declarations' ? 'nav-tab-active' : ''; ?>">Declarations</a>
				<a href="?page=somatic-framework-advanced" class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>">Advanced Functions</a>
			</h2>

			<?php if ( $active_tab == 'options' ) :              // first tab output ?>

			<!-- soma options -->
			<form action="options.php" method="post">
				<?php settings_fields( 'somatic_framework_plugin_options' ); // adds hidden form elements, nonce ?>
				<table class="form-table">

					<!-- Checkbox Buttons -->
					<tr valign="top">
						<th scope="row">
							General Options</th>
						<td>
							<label><input name="somatic_framework_options[debug]" type="checkbox" value="1" <?php if ( isset( $soma_options['debug'] ) ) { checked( '1', $soma_options['debug'] ); } ?> /> Debug Mode</label><br />
							<label><input name="somatic_framework_options[p2p]" type="checkbox" value="1" <?php if ( isset( $soma_options['p2p'] ) ) { checked( '1', $soma_options['p2p'] ); } ?> /> Require Posts 2 Posts Plugin <em>(often necessary when using custom post types)</em></label><br />
							<label><input name="somatic_framework_options[colorbox]" type="checkbox" value="1" <?php if ( isset( $soma_options['colorbox'] ) ) { checked( '1', $soma_options['colorbox'] ); } ?> /> Enable Colorbox JS lightbox for front-end content image links (not just admin)</label><br />
							<label><input name="somatic_framework_options[go_redirect]" type="checkbox" value="1" <?php if ( isset( $soma_options['go_redirect'] ) ) { checked( '1', $soma_options['go_redirect'] ); } ?> /> Enable custom redirects via go/[code]</label><br />
							<label><input name="somatic_framework_options[fulldisplayname]" type="checkbox" value="1" <?php if ( isset( $soma_options['fulldisplayname'] ) ) { checked( '1', $soma_options['fulldisplayname'] ); } ?> /> Force display name to be Firstname Lastname (wp default is the username)</label><br />
							<input type="submit" class="clicker" value="Save Changes" />
						</td>
					</tr>

					<!-- Textfield -->
					<tr valign="top">
						<th scope="row">
							Favicon
						</th>
						<td>
							<?php if ( !empty( $soma_options['favicon'] ) ) : ?><img src="<?php echo $soma_options['favicon']; ?>" style="vertical-align:middle;margin-right:8px;"><?php echo $soma_options['favicon']; else: echo "(not set)"; endif; ?><br />
							<input type="hidden" name="somatic_framework_options[favicon]" value="<?php echo $soma_options['favicon']; ?>">
						</td>
					</tr>
					<!-- Textfield -->
					<tr valign="top">
						<th scope="row">
							Login Logo
						</th>
						<td>
							<?php if ( !empty( $soma_options['login_logo'] ) ) : ?><?php echo $soma_options['login_logo']; else: echo "(not set)"; endif; ?><br /><img src="<?php echo $soma_options['login_logo']; ?>" style="display:block; border: 1px solid #eee;">
							<input type="hidden" name="somatic_framework_options[login_logo]" value="<?php echo $soma_options['login_logo']; ?>">
						</td>
					</tr>

					<!-- Checkbox Buttons -->
					<tr valign="top">
						<th scope="row">
							Disable Admin Menus
						</th>
						<td>
							<label><input name="somatic_framework_options[disable_menus][]" type="checkbox" value="posts" <?php if ( is_array( $soma_options['disable_menus'] ) ) { checked( '1', in_array( 'posts', $soma_options['disable_menus'] ) ); } ?> /> Posts</label><br />
							<label><input name="somatic_framework_options[disable_menus][]" type="checkbox" value="pages" <?php if ( is_array( $soma_options['disable_menus'] ) ) { checked( '1', in_array( 'pages', $soma_options['disable_menus'] ) ); } ?> /> Pages</label><br />
							<label><input name="somatic_framework_options[disable_menus][]" type="checkbox" value="links" <?php if ( is_array( $soma_options['disable_menus'] ) ) { checked( '1', in_array( 'links', $soma_options['disable_menus'] ) ); } ?> /> Links</label><br />
							<label><input name="somatic_framework_options[disable_menus][]" type="checkbox" value="comments" <?php if ( is_array( $soma_options['disable_menus'] ) ) { checked( '1', in_array( 'comments', $soma_options['disable_menus'] ) ); } ?> /> Comments</label><br />
							<label><input name="somatic_framework_options[disable_menus][]" type="checkbox" value="media" <?php if ( is_array( $soma_options['disable_menus'] ) ) { checked( '1', in_array( 'media', $soma_options['disable_menus'] ) ); } ?> /> Media</label><br />
							<label><input name="somatic_framework_options[disable_menus][]" type="checkbox" value="tools" <?php if ( is_array( $soma_options['disable_menus'] ) ) { checked( '1', in_array( 'tools', $soma_options['disable_menus'] ) ); } ?> /> Tools</label><br />
							<input type="submit" class="clicker" value="Save Changes" />
						</td>
					</tr>

					<!-- Checkbox Buttons -->
					<tr valign="top">
						<th scope="row">
							Disable Dashboard Widgets
						</th>
						<td>
							<label><input name="somatic_framework_options[disable_dashboard][]" type="checkbox" value="quick_press" <?php if ( is_array( $soma_options['disable_dashboard'] ) ) { checked( '1', in_array( 'quick_press', $soma_options['disable_dashboard'] ) ); } ?> /> Quick Press </label><br />
							<label><input name="somatic_framework_options[disable_dashboard][]" type="checkbox" value="recent_drafts" <?php if ( is_array( $soma_options['disable_dashboard'] ) ) { checked( '1', in_array( 'recent_drafts', $soma_options['disable_dashboard'] ) ); } ?> /> Recent Drafts </label><br />
							<label><input name="somatic_framework_options[disable_dashboard][]" type="checkbox" value="recent_comments" <?php if ( is_array( $soma_options['disable_dashboard'] ) ) { checked( '1', in_array( 'recent_comments', $soma_options['disable_dashboard'] ) ); } ?> /> Recent Comments </label><br />
							<label><input name="somatic_framework_options[disable_dashboard][]" type="checkbox" value="incoming_links" <?php if ( is_array( $soma_options['disable_dashboard'] ) ) { checked( '1', in_array( 'incoming_links', $soma_options['disable_dashboard'] ) ); } ?> /> Incoming Links </label><br />
							<input type="submit" class="clicker" value="Save Changes" />
						</td>
						<td>
							<label><input name="somatic_framework_options[disable_dashboard][]" type="checkbox" value="plugins" <?php if ( is_array( $soma_options['disable_dashboard'] ) ) { checked( '1', in_array( 'plugins', $soma_options['disable_dashboard'] ) ); } ?> /> Plugins </label><br />
							<label><input name="somatic_framework_options[disable_dashboard][]" type="checkbox" value="primary" <?php if ( is_array( $soma_options['disable_dashboard'] ) ) { checked( '1', in_array( 'primary', $soma_options['disable_dashboard'] ) ); } ?> /> Wordpress Blog </label><br />
							<label><input name="somatic_framework_options[disable_dashboard][]" type="checkbox" value="secondary" <?php if ( is_array( $soma_options['disable_dashboard'] ) ) { checked( '1', in_array( 'secondary', $soma_options['disable_dashboard'] ) ); } ?> /> Other Wordpress News </label><br />
							<label><input name="somatic_framework_options[disable_dashboard][]" type="checkbox" value="thesis_news_widget" <?php if ( is_array( $soma_options['disable_dashboard'] ) ) { checked( '1', in_array( 'thesis_news_widget', $soma_options['disable_dashboard'] ) ); } ?> /> Thesis News </label><br />
						</td>
					</tr>

					<!-- Checkbox Buttons -->
					<tr valign="top">
						<th scope="row">
							Disable Meta Boxes <br />(All Types)
						</th>
						<td>
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="submitdiv" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'submitdiv', $soma_options['disable_metaboxes'] ) ); } ?> /> Publish</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="postcustom" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'postcustom', $soma_options['disable_metaboxes'] ) ); } ?> /> Custom Fields</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="commentsdiv" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'commentsdiv', $soma_options['disable_metaboxes'] ) ); } ?> /> Discussion</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="trackbacksdiv" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'trackbacksdiv', $soma_options['disable_metaboxes'] ) ); } ?> /> Trackbacks</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="commentstatusdiv" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'commentstatusdiv', $soma_options['disable_metaboxes'] ) ); } ?> /> Comment Status</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="revisionsdiv" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'revisionsdiv', $soma_options['disable_metaboxes'] ) ); } ?> /> Revisions</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="authordiv" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'authordiv', $soma_options['disable_metaboxes'] ) ); } ?> /> Author</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="tb_page_options" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'tb_page_options', $soma_options['disable_metaboxes'] ) ); } ?> /> Jump Start Page Options</label><br />
							<input type="submit" class="clicker" value="Save Changes" />
						</td>
						<td>
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="postexcerpt" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'postexcerpt', $soma_options['disable_metaboxes'] ) ); } ?> /> Post Excerpt</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="formatdiv" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'formatdiv', $soma_options['disable_metaboxes'] ) ); } ?> /> Post Formats</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="pageparentdiv" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'pageparentdiv', $soma_options['disable_metaboxes'] ) ); } ?> /> Attributes</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="postimagediv" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'postimagediv', $soma_options['disable_metaboxes'] ) ); } ?> /> Featured Image</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="slugdiv" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'slugdiv', $soma_options['disable_metaboxes'] ) ); } ?> /> Slug</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="tagsdiv-post_tag" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'tagsdiv-post_tag', $soma_options['disable_metaboxes'] ) ); } ?> /> Post Tags</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="categorydiv" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'categorydiv', $soma_options['disable_metaboxes'] ) ); } ?> /> Post Categories</label><br />
						</td>
						<td>
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="thesis_seo_meta" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'thesis_seo_meta', $soma_options['disable_metaboxes'] ) ); } ?> /> Thesis SEO</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="thesis_image_meta" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'thesis_image_meta', $soma_options['disable_metaboxes'] ) ); } ?> /> Thesis Image</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="thesis_multimedia_meta" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'thesis_multimedia_meta', $soma_options['disable_metaboxes'] ) ); } ?> /> Thesis Multimedia</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="thesis_javascript_meta" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'thesis_javascript_meta', $soma_options['disable_metaboxes'] ) ); } ?> /> Thesis Javascript</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="thesis_title_tag" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'thesis_title_tag', $soma_options['disable_metaboxes'] ) ); } ?> /> Thesis 2.0 Custom Title</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="thesis_meta_description" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'thesis_meta_description', $soma_options['disable_metaboxes'] ) ); } ?> /> Thesis 2.0 Meta Description</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="thesis_meta_keywords" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'thesis_meta_keywords', $soma_options['disable_metaboxes'] ) ); } ?> /> Thesis 2.0 Meta Keywords</label><br />
						</td>
						<td>
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="thesis_meta_robots" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'thesis_meta_robots', $soma_options['disable_metaboxes'] ) ); } ?> /> Thesis 2.0 Meta Robots</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="thesis_canonical_link" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'thesis_canonical_link', $soma_options['disable_metaboxes'] ) ); } ?> /> Thesis 2.0 Canonical URL</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="thesis_html_body" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'thesis_html_body', $soma_options['disable_metaboxes'] ) ); } ?> /> Thesis 2.0 Body Class</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="thesis_post_content" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'thesis_post_content', $soma_options['disable_metaboxes'] ) ); } ?> /> Thesis 2.0 Readmore Text</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="thesis_post_image" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'thesis_post_image', $soma_options['disable_metaboxes'] ) ); } ?> /> Thesis 2.0 Post Image</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="thesis_post_thumbnail" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'thesis_post_thumbnail', $soma_options['disable_metaboxes'] ) ); } ?> /> Thesis 2.0 Post Thumbnail</label><br />
							<label><input name="somatic_framework_options[disable_metaboxes][]" type="checkbox" value="thesis_redirect" <?php if ( is_array( $soma_options['disable_metaboxes'] ) ) { checked( '1', in_array( 'thesis_redirect', $soma_options['disable_metaboxes'] ) ); } ?> /> Thesis 2.0 301 Redirect</label><br />
						</td>
					</tr>

					<!-- Checkbox Buttons -->
					<tr valign="top">
						<th scope="row">
							Disable Paging for Types
						</th>
						<td>
							<?php
		$types = get_post_types( array( 'show_ui' => true ), 'objects' );
		foreach ( $types as $type ) :?>
								<label><input name="somatic_framework_options[kill_paging][]" type="checkbox" value="<?php echo $type->name; ?>" <?php if ( is_array( $soma_options['kill_paging'] ) ) { checked( '1', in_array( $type->name, $soma_options['kill_paging'] ) ); } ?> /> <?php echo $type->label; ?></label><br />
							<?php endforeach; ?>
							<input type="submit" class="clicker" value="Save Changes" />
						</td>
					</tr>

					<!-- Checkbox Buttons -->
					<tr valign="top">
						<th scope="row">
							Disable Autosave for Types
						</th>
						<td>
							<?php
		$types = get_post_types( array( 'show_ui' => true ), 'objects' );
		foreach ( $types as $type ) :?>
								<label><input name="somatic_framework_options[kill_autosave][]" type="checkbox" value="<?php echo $type->name; ?>" <?php if ( is_array( $soma_options['kill_autosave'] ) ) { checked( '1', in_array( $type->name, $soma_options['kill_autosave'] ) ); } ?> /> <?php echo $type->label; ?></label><br />
							<?php endforeach; ?>
							<input type="submit" class="clicker" value="Save Changes" />
						</td>
					</tr>
					<!-- Checkbox Buttons -->
					<tr valign="top">
						<th scope="row">
							Disable Revisions for Types
						</th>
						<td>
							<?php
		$types = get_post_types( array( 'show_ui' => true ), 'objects' );
		foreach ( $types as $type ) : ?>
							<label><input name="somatic_framework_options[kill_revisions][]" type="checkbox" value="<?php echo $type->name; ?>" <?php if ( is_array( $soma_options['kill_revisions'] ) ) { checked( '1', in_array( $type->name, $soma_options['kill_revisions'] ) ); } ?> /> <?php echo $type->label; ?></label><br />
							<?php endforeach; ?>
							<input type="submit" class="clicker" value="Save Changes" />
						</td>
					</tr>


					<!-- Textbox Control -->
					<tr>
						<th scope="row">
							Post Meta
						</th>
						<td>
							<strong>CAUTION!</strong> <em>don't change these values after you've already saved a post with metadata - you won't lose anything (it will still exist in the database) but it won't be visible anymore...</em><br />
							<label>Post Meta Prefix <input type="text" size="7" name="somatic_framework_options[meta_prefix]" value="<?php echo $soma_options['meta_prefix']; ?>" /> <em>(just a few letters, can begin with but not end in underscore)</em></label><br />
							<label><input name="somatic_framework_options[meta_serialize]" type="checkbox" value="1" <?php if ( isset( $soma_options['meta_serialize'] ) ) { checked( '1', $soma_options['meta_serialize'] ); } ?> /> Serialize post-meta when saving?</label><br />
							<input type="submit" class="clicker" value="Save Changes" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							Reset Options
						</th>
						<td>
							<label><input name="somatic_framework_options[reset_default_options]" type="checkbox" value="1" <?php if ( isset( $soma_options['reset_default_options'] ) ) { checked( '1', $soma_options['reset_default_options'] ); } ?> /> Restore defaults upon plugin deactivation/reactivation</label><br />
							<em>Only check this if you want to reset plugin settings the NEXT TIME this plugin is activated</em><br />
							<input type="submit" class="clicker" value="Save Changes" />
						</td>
					</tr>
				</table>

				<!-- Settings to be retained but not shown -->
				<input type="hidden" name="somatic_framework_options[plugin_db_version]" value="<?php echo $soma_options['plugin_db_version']; ?>">

			</form>
			<br/>

			<?php endif; ?>

			<?php if ( $active_tab == 'declarations' ) :             // second tab output ?>

			<table class="form-table">
				<tr valign="top">
					<th scope="row">Custom Post Types</th>
					<td>
						<?php echo implode( ', ', $custom_types ); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Custom Taxonomies</th>
					<td>
						<?php echo implode( ', ', $custom_taxes ); ?>
					</td>
				</tr>

			</table>
			<br />

			<?php endif; ?>

			<?php if ( $active_tab == 'advanced' ) :            // third tab output ?>

			<!-- export .dat via admin_action_export hook -->
			<form action="<?php echo network_admin_url( 'admin.php' ); ?>" method="post">
				<ul>
					<h3>Export Somatic Framework Settings</h3>
					<li>This will download a text file to your computer containing the current settings</li>
					<input type="hidden" name="security" value="<?php echo wp_create_nonce( "soma-export" ); ?>" />
					<input type="hidden" name="action" value="export">
					<li>
						<input type="submit" value="Download Settings" class="clicker"/>
					</li>
				</ul>
			</form>
			<br />

			<!-- import .dat via admin_action_import hook -->
			<form action="<?php echo network_admin_url( 'admin.php' ); ?>" method="post" enctype="multipart/form-data">
				<ul>
					<h3>Import Somatic Framework Settings</h3>
					<li>Select a file to restore all the options</li>
					<input type="hidden" name="security" value="<?php echo wp_create_nonce( "soma-import" ); ?>" />
					<input type="hidden" name="action" value="import">
					<li>
						<input type="file" class="text_input" name="file" id="options-file" />
					</li>
					<li>
						<input type="submit" value="Upload Settings" class="clicker"/>
					</li>
				</ul>
			</form>
			<br/>

			<!-- flush via admin_action_flush hook -->
			<form action="<?php echo network_admin_url( 'admin.php' ); ?>" method="post">
				<ul>
					<h3>Flush Rewrite Rules</h3>
					<li>handy if you've changed your custom post type config sometime after having activated this framework</li>
					<input type="hidden" name="security" value="<?php echo wp_create_nonce( "soma-flush" ); ?>" />
					<input type="hidden" name="action" value="flush">
					<li>
						<input type="submit" value="Flush" class="clicker"/>
					</li>
				</ul>
			</form>

			<?php endif; ?>

		</div>
		<?php
	}

	// validate what has been posted from our settings page before sending along to update_option
	function soma_options_validate( $input ) {
		// ensure date in mysql format
		// $input['open_date'] = date("Y-m-d", strtotime($input['open_date']));
		// $input['close_date'] = date("Y-m-d", strtotime($input['close_date']));
		// strip html from textboxes
		// $input['textarea_one'] =  wp_filter_nohtml_kses($input['textarea_one']); // Sanitize textarea input (strip html tags, and escape characters)
		// $input['txt_one'] =  wp_filter_nohtml_kses($input['txt_one']); // Sanitize textbox input (strip html tags, and escape characters)
		// anything else?
		// should make sure those items that need to be arrays are arrays, or make them so, or make them null...
		return $input;
	}

	/**
	 * Sanitises various option values based on the nature of the option.
	 *
	 * @since 1.6
	 *
	 * @param string  $option The name of the option.
	 * @param string  $value  The unsanitised value.
	 * @return string Sanitized value.
	 */
	function sanitize_soma_options( $value, $option ) {
		// soma_dump($value);
		return $value;
	}

	// hides parts of the user profile panel, reduce complexity
	function hide_profile_options() {
?>
		<script type="text/javascript">
		jQuery(document).ready(function(){
			jQuery("#your-profile .form-table:first, #your-profile h3:first").remove();
			jQuery("#your-profile #description").parent().parent().remove();
			jQuery("h3:contains('About Yourself')").html('Password');
		});
		</script>
		<?php
	}

	// outputs extra usermeta fields to user profile edit page
	function show_extra_profile_fields( $user ) {
	}

	// save the custom fields we've created
	function save_extra_profile_fields( $user_id ) {
		if ( !current_user_can( 'edit_user', $user_id ) )
			return false;
	}

	// automatically populates the display name field with the First Last name (wp defaults to the username)
	function full_display_name( $user_id ) {
		global $soma_options;
		if ( somaFunctions::fetch_index( $soma_options, 'fulldisplayname' ) ) {
			$user = get_userdata( $user_id );
			$fullname = $_POST['first_name'] . " " . $_POST['last_name'];
			if ( $user->display_name != $fullname ) {
				wp_update_user( array( 'ID' => $user_id, 'display_name' => $fullname ) );
			}
		return null;
		}
	}

	// modifies the user profile page fields
	function extend_user_contactmethod( $contactmethods ) {
		global $soma_options;
		$prefix = $soma_options['meta_prefix'];
		$contactmethods[$prefix .'user_phone'] = 'Phone Number';
		$contactmethods[$prefix .'user_facebook'] = 'Facebook Profile URL';
		$contactmethods[$prefix .'user_twitter'] = 'Twitter Username';
		unset( $contactmethods['yim'] );
		unset( $contactmethods['aim'] );
		unset( $contactmethods['jabber'] );
		return $contactmethods;
	}

	//
	function disable_dashboard_widgets() {
		global $soma_options;
		if ( !is_array( $soma_options['disable_dashboard'] ) ) return false;     // abort to avoid PHP errors
		if ( in_array( "quick_press", $soma_options['disable_dashboard'] ) ) remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
		if ( in_array( "recent_drafts", $soma_options['disable_dashboard'] ) ) remove_meta_box( 'dashboard_recent_drafts', 'dashboard', 'side' );
		if ( in_array( "recent_comments", $soma_options['disable_dashboard'] ) ) remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
		if ( in_array( "incoming_links", $soma_options['disable_dashboard'] ) ) remove_meta_box( 'dashboard_incoming_links', 'dashboard', 'normal' );
		if ( in_array( "plugins", $soma_options['disable_dashboard'] ) ) remove_meta_box( 'dashboard_plugins', 'dashboard', 'normal' );
		if ( in_array( "primary", $soma_options['disable_dashboard'] ) ) remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
		if ( in_array( "secondary", $soma_options['disable_dashboard'] ) ) remove_meta_box( 'dashboard_secondary', 'dashboard', 'side' );
		if ( in_array( "thesis_news_widget", $soma_options['disable_dashboard'] ) ) remove_meta_box( 'thesis_news_widget', 'dashboard', 'normal' );
	}

	// removes unwanted metaboxes from post editor
	function disable_metaboxes( $type, $context, $post ) {
		global $soma_options;
		$boxes = $soma_options['disable_metaboxes'];
		if ( !is_array( $boxes ) ) return false;
		foreach ( $boxes as $box ) {
			foreach ( array( 'link', 'post', 'page' ) as $page ) {
				foreach ( array( 'normal', 'advanced', 'side' ) as $context ) {
					remove_meta_box( $box, $type, $context );
				}
			}
		}
	}

	//
	function disable_admin_menus() {
		global $soma_options;
		if ( !is_array( soma_fetch_index($soma_options,'disable_menus') ) ) return false;    // abort to avoid PHP errors
		if ( in_array( 'links', $soma_options['disable_menus'] ) )
			remove_menu_page( 'link-manager.php' );
		if ( in_array( 'comments', $soma_options['disable_menus'] ) )
			remove_menu_page( 'edit-comments.php' );
		if ( in_array( 'media', $soma_options['disable_menus'] ) )
			remove_menu_page( 'upload.php' );
		if ( in_array( 'tools', $soma_options['disable_menus'] ) )
			remove_menu_page( 'tools.php' );
		if ( in_array( 'posts', $soma_options['disable_menus'] ) )
			remove_menu_page( 'edit.php' );
		if ( in_array( 'pages', $soma_options['disable_menus'] ) )
			remove_menu_page( 'edit.php?post_type=page' );
	}

	//
	function disable_admin_bar_links() {
		global $wp_admin_bar, $soma_options;
		if ( !is_array( soma_fetch_index($soma_options,'disable_menus') ) ) return false;    // abort to avoid PHP errors
		if ( post_type_exists( 'feedback' ) )           // jetpack plugin contact forms they forgot to remove from the admin bar....
			$wp_admin_bar->remove_menu( 'new-feedback' );
		if ( in_array( 'links', $soma_options['disable_menus'] ) )
			$wp_admin_bar->remove_menu( 'new-link' );
		if ( in_array( 'comments', $soma_options['disable_menus'] ) )
			$wp_admin_bar->remove_menu( 'comments' );
		if ( in_array( 'media', $soma_options['disable_menus'] ) )
			$wp_admin_bar->remove_menu( 'new-media' );
		if ( in_array( 'posts', $soma_options['disable_menus'] ) )
			$wp_admin_bar->remove_menu( 'new-post' );
		if ( in_array( 'pages', $soma_options['disable_menus'] ) )
			$wp_admin_bar->remove_menu( 'new-page' );
	}

	//
	function disable_paging( $query ) {
		global $soma_options;

		// abort in admin
		if ( $query->is_admin ) return $query;

		// post type archives
		if ( $query->is_post_type_archive ) {
			$paging = soma_fetch_index( $soma_options, 'kill_paging' );
			if ( is_array( $paging ) && in_array( $query->query_vars['post_type'], $paging) ) {
				$query->set( 'nopaging', true );
				return $query;
			}
		}

		// taxonomy listings
		if ( $query->is_tax ) {
			if ( !is_null( $query->queried_object ) ) {
				$tax = get_taxonomy( $query->queried_object->taxonomy );
				foreach ( $tax->object_type as $cpt ) {
					if ( is_array( $soma_options[ 'kill_paging' ] ) && in_array( $cpt, $soma_options[ 'kill_paging' ] ) ) {
						$query->set( 'nopaging', true );
						return $query;
					}
				}
			}
		}

		// pass thru
		return $query;
	}

	// disable the screen tab without losing whatever had been set in it (like dashboard columns)
	function disable_screen_options( $display_boolean, $wp_screen_object ) {
		global $soma_options, $pagenow;
		$blacklist = array( 'post.php', 'post-new.php', 'index.php', 'edit.php' );      // only on certain screens
		if ( $soma_options['disable_screen_options'] && in_array( $GLOBALS['pagenow'], $blacklist ) ) {
			$wp_screen_object->render_screen_layout();
			$wp_screen_object->render_per_page_options();
			return false;
		} else {
			return true;
		}
	}

	//
	function disable_autosave() {
		global $soma_options;
		global $post;
		$killsave = somaFunctions::fetch_index( $soma_options, 'kill_autosave' );
		if ( is_array($killsave) && in_array( $post->post_type, $killsave ) ) {
			wp_dequeue_script( 'autosave' );
		}

	}

	// not sure if this works - need to test
	function disable_revisions() {
		global $soma_options;
		$types = somaFunctions::fetch_index( $soma_options, 'kill_revisions' );
		if ( is_array( $types ) ) {
			foreach ( $soma_options[ 'kill_revisions' ] as $type ) {
				remove_post_type_support( $type, 'revisions' );
			}
		}
	}

	// this is very deeeeep - need to finish and create UI for it above
	function disable_support() {
		global $soma_options;
		if ( post_type_supports( $post_type, $feature ) && $soma_options['kill_support'] ) {

		}
		/*
		'title'
		'editor'
		'author'
		'thumbnail'
		'excerpt'// (posts only) (toggle for pages?)
		'trackbacks'// (posts only)
		'page-attributes' // (template and menu order, pages only)
		'custom-fields'
		'comments'
		'revisions'
		'post-format' // i guess if we've declared it globally elsewhere, should be able to turn off per post-type - should check
		*/
	}

	// don't think this works, so disabled...
	function disable_drag_metabox() {
		global $soma_options;
		if ( somaFunctions::fetch_index( $soma_options, 'disable_drag_metabox' ) )
			wp_deregister_script( 'postbox' );
	}

	function enable_threaded_comments() {
		if ( !is_admin() ) {
			if ( is_singular() and comments_open() and ( get_option( 'thread_comments' ) == 1 ) )
				wp_enqueue_script( 'comment-reply' );
		}
	}

	function user_admin_bar_false_by_default( $user_id ) {
		update_user_meta( $user_id, 'show_admin_bar_front', 'false' );
	}

	function show_admin_bar() {
		// checking per user doesn't work because current user isn't known this early in init hook
		// get_currentuserinfo();
		// $useroption = get_user_meta($user_ID, 'show_admin_bar_front', true);
		// if ( empty( $useroption ) ) $useroption = false;
		global $soma_options;

		if ( !is_admin() && $soma_options[ 'always_show_bar' ] && is_user_logged_in() ) {
			add_filter( 'show_admin_bar', '__return_true' );
		} else {
			add_filter( 'show_admin_bar', '__return_false' );
		}
	}

	function soma_go_endpoint() {
		global $soma_options;
		if ( somaFunctions::fetch_index( $soma_options, 'go_redirect' ) ) {
			add_rewrite_endpoint( 'go', EP_ALL );
		}
	}

	function soma_go_redirect() {
		global $soma_options, $wp_query;
		$go = soma_fetch_index($wp_query->query_vars, 'go');					// possibly redirect an erroneous /go/ link, whether or not option is on...
		if (is_null($go)) return;												// go query var isn't set, so do nothing and wp continues on...
		if ($go == ""): wp_redirect( network_home_url(), 301 ); exit(); endif;			// go exists, but is empty, so just go back home
		if ( somaFunctions::fetch_index( $soma_options, 'go_redirect' ) ) {
			$codes = array();
			$codes = apply_filters('soma_go_redirect_codes', $codes);			// allow expansion - add another associative slug/url array key/value
			foreach ($codes as $slug => $url) {
				$slug = sanitize_title_with_dashes($slug);
				if ($go == $slug) {
					wp_redirect( esc_url_raw($url), 301 );
					exit();
				}
			}
			wp_redirect( network_home_url(), 301 );										// no matches, so just go back home
			exit;
		}
	}

	function soma_default_links($codes) {
		// init container
		$codes = array();
		$codes['wpengine'] = "http://www.shareasale.com/r.cfm?b=394686&u=402945&m=41388&urllink=";
		$codes['thesis'] = "http://www.shareasale.com/r.cfm?B=198392&U=402945&M=24570&urllink=";
		$codes['hover'] = "https://hover.com/MqSwvYkz";
		return $codes;
	}


	function always_colorbox($content) {
		global $soma_options;
		if ( somaFunctions::fetch_index( $soma_options, 'colorbox' ) ) {
			global $post;
			$pattern ="/<a(.*?)href=('|\")(.*?).(bmp|gif|jpeg|jpg|png)('|\")(.*?)>/i";
			$replacement = '<a$1href=$2$3.$4$5 rel="colorbox" $6>';
			$content = preg_replace($pattern, $replacement, $content);
		}
		return $content;
	}

	/////////////// END CLASS
}

$somaOptions = new somaOptions();
