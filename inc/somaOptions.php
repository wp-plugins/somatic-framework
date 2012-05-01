<?php

class somaOptions extends somaticFramework  {

	function __construct() {
		add_action( 'wp', array(__CLASS__, 'soma_cron') );											// init our cron
		add_action( 'soma_daily_event', array(__CLASS__, 'delete_autodrafts') );					// fire this every day
		add_action( 'personal_options', array(__CLASS__, 'hide_profile_options') );					// hides some useless cruft, make profile simpler
		// add_action( 'show_user_profile', array(__CLASS__, 'show_extra_profile_fields') );		// unused
		// add_action( 'edit_user_profile', array(__CLASS__, 'show_extra_profile_fields') );		// unused
		// add_action( 'edit_user_profile_update', array(__CLASS__, 'save_extra_profile_fields') );	// unused
		// add_action( 'personal_options_update', array(__CLASS__, 'save_extra_profile_fields') );	// unused
		add_action( 'profile_update', array(__CLASS__, 'full_display_name') );						// automatically populate display name with fullname
		add_action( 'user_register', array(__CLASS__, 'full_display_name') );						// automatically populate display name with fullname
		add_filter( 'user_contactmethods', array(__CLASS__, 'extend_user_contactmethod'),10,1);		// mods contact fields on user profile
		add_action( 'admin_menu', array(__CLASS__, 'add_pages' ) );									// adds menu items to wp-admin
		add_action( 'admin_init', array(__CLASS__, 'soma_options_init' ) );							// register settings
		add_action( 'admin_action_flush', array(__CLASS__,'flush_rules' ) );						// dynamically generated hook created by the ID on forms POSTed from admin.php
		// add_action( 'admin_action_purge', array(__CLASS__,'purge_all_data' ) );					// dynamically generated hook created by the ID on forms POSTed from admin.php
		// add_action( 'admin_action_export', array(__CLASS__,'export_csv' ) );						// dynamically generated hook created by the ID on forms POSTed from admin.php
		add_action( 'wp_dashboard_setup', array(__CLASS__, 'disable_dashboard_widgets'), 100);
		add_action( 'admin_menu', array(__CLASS__, 'disable_admin_menus' ) );						// hide the admin sidebar menu items
		add_action( 'wp_print_scripts', array(__CLASS__, 'disable_autosave' ) );					// optional disable autosave
		add_action( 'init', array(__CLASS__, 'disable_revisions') );								// optional disable revisions
	}

	// init container for help text and options
	function soma_options_init() {
		// register_setting( 'soma_help', 'soma_help_text', array(__CLASS__, 'soma_help_validate' ) );
		register_setting( 'somatic_framework_plugin_options', 'somatic_framework_options', array(__CLASS__, 'soma_options_validate' ) );
	}

	// schedule if event doesn't already exists
	function soma_cron() {
		if ( !wp_next_scheduled( 'soma_daily_event' ) ) {
			wp_schedule_event(time(), 'daily', 'soma_daily_event');
		}
	}

	// kills auto draft posts (scheduled via cron daily)
	function delete_autodrafts() {
		$pargs = array(
			'post_type' => any,
			'post_status' => 'auto-draft',
			'numberposts' => -1,
		);
		$autodrafts = get_posts($pargs);
		foreach ($autodrafts as $draft) {
			wp_delete_post($draft->ID, true);
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
		// update_option('uploads_use_yearmonth_folders', 0);				// always hated this
		// update_option('blog_public', 1);								// let google in
		// update_option('comment_registration', 1);
		// update_option('default_role','author');
		// update_option('comment_moderation', 0);
		// update_option('default_ping_status','closed');
		// update_option('moderation_notify', 0);
		// update_option('comment_whitelist', 0);
		// update_option('thread_comments', 1);
		// update_option('require_name_email', 0);
		// update_option('comments_notify', 1);
		// update_option('enable_xmlrpc', 1);								// do we want to do this?
		// update_option('permalink_structure', '/%postname%/');			// default to pretty permalinks

		// delete dummy post, page and comment
		// wp_delete_post(1, TRUE);
		// wp_delete_post(2, TRUE);
		// wp_delete_comment(1);
	}


	//** sets somatic framework options defaults
	// if there are no theme options currently set, or the user has selected the checkbox to reset options to their defaults then the options are set/reset.
	function set_soma_options() {

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
			"kill_autosave" => array(),
			"metaboxes" => array('thesis_seo_meta', 'thesis_image_meta','thesis_multimedia_meta', 'thesis_javascript_meta'),		// hide these in post editor
			"reset_default_options" => 0,			// will reset options to defaults next time plugin is activated
			"plugin_db_version" => self::get_plugin_version()
		);

		$current = get_option('somatic_framework_options', null);								// fetch current options if they exist

	    if ( ( is_null( $current ) ) || ( $current['reset_default_options'] == '1' ) ) {		// options don't exist yet OR user has requested reset
			update_option('somatic_framework_options', $defaults);								// write defaults
		}
		
		// convert old options
		$old_serialize = get_option( 'soma_meta_serialize', null );
		if ( !is_null( $old_serialize ) ) {
			update_option('somatic_framework_options', array('meta_serialize', $old_serialize));
			delete_option( 'soma_meta_serialize' );
		}
		// convert old options
		$old_prefix = get_option( 'soma_meta_prefix', null );
		if ( !is_null( $old_prefix ) ) {
			update_option('somatic_framework_options', array('meta_prefix', $old_prefix));
			delete_option( 'soma_meta_prefix' );
		}
		// convert old options
		$old_debug = get_option( 'soma_debug', null );
		if ( !is_null( $old_debug ) ) {
			update_option('somatic_framework_options', array('debug', $old_debug));
			delete_option( 'soma_debug' );
		}
	}

	// Delete options table entries ONLY when plugin deactivated AND deleted
	function delete_soma_options() {
		delete_option('somatic_framework_options');
	}

	//** generate new roles and extend for custom post type capabilities ------------------------------------------------------//
	//  needs to be called elsewhere upon plugin activation
	function setup_capabilities() {

		// new manager role (expanding on core editor role)
		$editor = get_role('editor');
		$editor_caps = $editor->capabilities;
		if ($editor_caps == null) $editor_caps = array();	// don't want to barf on array_merge later...
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
		$caps = array_merge($editor_caps, $manager_caps);
		$manager = add_role('manager', 'Manager', $caps);
		if (null == $manager) {		// already exists, replace?? every time?
			remove_role('manager');
			$manager = add_role('manager', 'Manager', $caps);
		}

		// get rid of unused core roles
		// remove_role('editor');
		// remove_role('subscriber');
		// remove_role('author');
		// remove_role('contributor');
	}

	// adds menu items to wp-admin
	function add_pages() {
		if ( SOMA_STAFF ) {
			// add_submenu_page('options-general.php', 'Help Text', 'Help Text', 'update_core', 'soma-help-editor', array(__CLASS__,'soma_help_page'), SOMA_IMG.'soma-downloads-menu.png');
			add_submenu_page('tools.php', 'Somatic Framework', 'Somatic Framework', 'update_core', 'soma-options-page', array(__CLASS__,'soma_options_page'), SOMA_IMG.'soma-downloads-menu.png');
		}
	}

	// help page contents
	function soma_help_page() {
		if (somaFunctions::fetch_index($_REQUEST, 'settings-updated') ) {
			echo "<div id='notice_post' class='updated fade'><p>Text Saved...</p></div>";
		}
		?>
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

				$types = get_post_types( array( '_builtin' => false  ), 'objects' );
				$helptext = get_option('soma_help_text');
				foreach ($types as $type) {
					echo "<h4>$type->label</h4>";
					foreach (somaMetaboxes::$data as $box) {
						if (in_array($type->rewrite['slug'], $box['types'])) {
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

	function soma_help_validate($input) {
		// strip html
		array_walk($input, create_function('&$v,$k', '$v = wp_filter_nohtml_kses($v);'));
		return $input;
	}

	// options page contents
	function soma_options_page() {
		// previous submit result messages
		if (somaFunctions::fetch_index($_GET, 'result') == 'purge-success') {
			echo "<div class='updated fade'><p>All data successfully purged!</p></div>";
		}
		if (somaFunctions::fetch_index($_GET, 'result') == 'export-success') {
			echo "<div class='updated fade'><p>All data successfully exported!</p></div>";
		}
		if (somaFunctions::fetch_index($_GET, 'result') == 'flush-success') {
			echo "<div class='updated fade'><p>Rewrite rules successfully flushed!</p></div>";
		}

		// retrieve options
		$options = get_option('somatic_framework_options');
		soma_dump($options);


		// retrieve custom post types
		$custom_types = get_post_types(array("_builtin" => false));
		$custom_taxes = get_taxonomies(array("_builtin" => false));

		?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"><br/></div>
			<h2>Somatic Framework Options</h2>

			<?php if (SOMA_STAFF) : ?>

			<table class="form-table">
				<tr valign="top">
					<th scope="row">Declared Custom Post Types</th>
					<td>
						<?php echo implode(', ', $custom_types); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Declared Custom Taxonomies</th>
					<td>
						<?php echo implode(', ', $custom_taxes); ?>
					</td>
				</tr>

			</table>
			<br />
			<!-- soma options -->
			<form action="options.php" method="post">
				<?php settings_fields( 'somatic_framework_plugin_options' ); // adds hidden form elements, nonce ?>
				
				<input type="submit" class="clicker" value="Save Changes" />
				<br />
				<table class="form-table">

					<!-- Checkbox Buttons -->
					<tr valign="top">
						<th scope="row">General Options</th>
						<td>
							<label><input name="somatic_framework_options[debug]" type="checkbox" value="1" <?php if (isset($options['debug'])) { checked('1', $options['debug']); } ?> /> Debug Mode</label><br />
							<label><input name="somatic_framework_options[p2p]" type="checkbox" value="1" <?php if (isset($options['p2p'])) { checked('1', $options['p2p']); } ?> /> Require Posts 2 Posts Plugin <em>(often necessary when using custom post types)</em></label><br />
						</td>
					</tr>
					<!-- Checkbox Buttons -->
					<tr valign="top">
						<th scope="row">Disable Admin Menus</th>
						<td>
							<label><input name="somatic_framework_options[hide_links_menu]" type="checkbox" value="1" <?php if (isset($options['hide_links_menu'])) { checked('1', $options['hide_links_menu']); } ?> /> Links</label><br />
							<label><input name="somatic_framework_options[hide_comments_menu]" type="checkbox" value="1" <?php if (isset($options['hide_comments_menu'])) { checked('1', $options['hide_comments_menu']); } ?> /> Comments</label><br />
							<label><input name="somatic_framework_options[hide_media_menu]" type="checkbox" value="1" <?php if (isset($options['hide_media_menu'])) { checked('1', $options['hide_media_menu']); } ?> /> Media</label><br />
							<label><input name="somatic_framework_options[hide_tools_menu]" type="checkbox" value="1" <?php if (isset($options['hide_tools_menu'])) { checked('1', $options['hide_tools_menu']); } ?> /> Tools</label><br />
						</td>
					</tr>

					<!-- Checkbox Buttons -->
					<tr valign="top">
						<th scope="row">Disable Dashboard Widgets</th>
						<td>
							<label><input name="somatic_framework_options[dashboard_quick_press]" type="checkbox" value="1" <?php if (isset($options['dashboard_quick_press'])) { checked('1', $options['dashboard_quick_press']); } ?> /> Quick Press </label><br />
							<label><input name="somatic_framework_options[dashboard_recent_drafts]" type="checkbox" value="1" <?php if (isset($options['dashboard_recent_drafts'])) { checked('1', $options['dashboard_recent_drafts']); } ?> /> Recent Drafts </label><br />
							<label><input name="somatic_framework_options[dashboard_recent_comments]" type="checkbox" value="1" <?php if (isset($options['dashboard_recent_comments'])) { checked('1', $options['dashboard_recent_comments']); } ?> /> Recent Comments </label><br />
							<label><input name="somatic_framework_options[dashboard_incoming_links]" type="checkbox" value="1" <?php if (isset($options['dashboard_incoming_links'])) { checked('1', $options['dashboard_incoming_links']); } ?> /> Incoming Links </label><br />
							<label><input name="somatic_framework_options[dashboard_plugins]" type="checkbox" value="1" <?php if (isset($options['dashboard_plugins'])) { checked('1', $options['dashboard_plugins']); } ?> /> Plugins </label><br />
							<label><input name="somatic_framework_options[dashboard_primary]" type="checkbox" value="1" <?php if (isset($options['dashboard_primary'])) { checked('1', $options['dashboard_primary']); } ?> /> Wordpress Blog </label><br />
							<label><input name="somatic_framework_options[dashboard_secondary]" type="checkbox" value="1" <?php if (isset($options['dashboard_secondary'])) { checked('1', $options['dashboard_secondary']); } ?> /> Other Wordpress News </label><br />
							<label><input name="somatic_framework_options[thesis_news_widget]" type="checkbox" value="1" <?php if (isset($options['thesis_news_widget'])) { checked('1', $options['thesis_news_widget']); } ?> /> Thesis News </label><br />
						</td>
					</tr>

					<!-- Checkbox Buttons -->
					<tr valign="top">
						<th scope="row">Disable Meta Boxes</th>
						<td>
							<label><input name="somatic_framework_options[metaboxes][]" type="checkbox" value="thesis_seo_meta" <?php if (is_array($options['metaboxes'])) { checked('1', in_array('thesis_seo_meta', $options['metaboxes'])); } ?> /> Thesis SEO</label><br />
							<label><input name="somatic_framework_options[metaboxes][]" type="checkbox" value="thesis_image_meta" <?php if (is_array($options['metaboxes'])) { checked('1', in_array('thesis_image_meta', $options['metaboxes'])); } ?> /> Thesis Image</label><br />
							<label><input name="somatic_framework_options[metaboxes][]" type="checkbox" value="thesis_multimedia_meta" <?php if (is_array($options['metaboxes'])) { checked('1', in_array('thesis_multimedia_meta', $options['metaboxes'])); } ?> /> Thesis Multimedia</label><br />
							<label><input name="somatic_framework_options[metaboxes][]" type="checkbox" value="thesis_javascript_meta" <?php if (is_array($options['metaboxes'])) { checked('1', in_array('thesis_javascript_meta', $options['metaboxes'])); } ?> /> Thesis Javascript</label><br />
						</td>
					</tr>
					
					<!-- Checkbox Buttons -->
					<tr valign="top">
						<th scope="row">Disable Autosave for Types</th>
						<td>
							<?php
							$types = get_post_types(array('show_ui' => true), 'objects');
							foreach ($types as $type) {
?>							<label><input name="somatic_framework_options[kill_autosave][]" type="checkbox" value="<?php echo $type->name; ?>" <?php if (is_array($options['kill_autosave'])) { checked('1', in_array($type->name, $options['kill_autosave'])); } ?> /> <?php echo $type->label; ?></label><br />								
							<?php }
							
							?>
						</td>
					</tr>
					<!-- Checkbox Buttons -->
					<tr valign="top">
						<th scope="row">Disable Revisions for Types</th>
						<td>
							<?php
							$types = get_post_types(array('show_ui' => true), 'objects');
							foreach ($types as $type) {
?>							<label><input name="somatic_framework_options[kill_revisions][]" type="checkbox" value="<?php echo $type->name; ?>" <?php if (is_array($options['kill_revisions'])) { checked('1', in_array($type->name, $options['kill_revisions'])); } ?> /> <?php echo $type->label; ?></label><br />								
							<?php }

							?>
						</td>
					</tr>


					<!-- Textbox Control -->
					<tr>
						<th scope="row">Post Meta</th>
						<td>
							<strong>CAUTION!</strong> <em>don't change these values after you've already saved a post with metadata - you won't lose anything (it will still exist in the database) but it won't be visible anymore...</em><br />
							<label>Post Meta Prefix <input type="text" size="7" name="somatic_framework_options[meta_prefix]" value="<?php echo $options['meta_prefix']; ?>" /> <em>(just a few letters, can begin with but not end in underscore)</em></label><br />
							<label><input name="somatic_framework_options[meta_serialize]" type="checkbox" value="1" <?php if (isset($options['meta_serialize'])) { checked('1', $options['meta_serialize']); } ?> /> Serialize post-meta when saving?</label><br />
						</td>
					</tr>

					<tr>
						<th scope="row">Reset Options</th>
						<td>
							<label><input name="somatic_framework_options[reset_default_options]" type="checkbox" value="1" <?php if (isset($options['reset_default_options'])) { checked('1', $options['reset_default_options']); } ?> /> Restore defaults upon plugin deactivation/reactivation</label><br />
							<em>Only check this if you want to reset plugin settings the NEXT TIME this plugin is activated</em>
						</td>
					</tr>
				</table>
				<br />
				<input type="submit" class="clicker" value="Save Changes" />
			</form>
			<br/>

			<!-- flush via admin_action_flush hook -->
			<form action="<?php echo admin_url( 'admin.php' ); ?>" method="post">
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
			<br/>

			<!-- export csv via admin_action_export hook -->
			<form action="<?php echo admin_url( 'admin.php' ); ?>" method="post">
				<ul>
					<h3>Export All Application Data</h3>
					<li>This will download a CSV file to your computer with an entry line for each application</li>
					<input type="hidden" name="security" value="<?php echo wp_create_nonce( "soma-export" ); ?>" />
					<input type="hidden" name="items" value="24">
					<input type="hidden" name="action" value="export">
					<li>
						<input type="submit" value="Download CSV" class="clicker"/>
					</li>

				</ul>
			</form>
			<br/>
			<!-- purge all via admin_action_purge hook -->
			<form action="<?php echo admin_url( 'admin.php' ); ?>" method="post" id="purge">
				<ul>
					<h3>Reset All Application Data</h3>
					<li>
						Clicking this button will purge the entire system of all Applications, Letters, and uploaded Documents. Please be sure you've already exported the application data above before proceeding...
					</li>
					<li>
						<em>Currently in the system:</em>
					</li>
					<?php
					$apps = get_posts(array('post_status'=>'any','post_type' => 'applications'));
					$app_count = count($apps);
					$letters = get_posts(array('post_status'=>'any','post_type' => 'letters'));
					$lett_count = count($letters);
					$docs = get_posts(array('post_status'=>'any','post_type' => 'attachment'));
					$doc_count = count($docs);
					 ?>
					<li>
						<?php echo $app_count; ?> Applications
					</li>
					<li>
						<?php echo $lett_count; ?> Letters
					</li>
					<li>
						<?php echo $doc_count; ?> Documents
					</li>

					<input type="hidden" name="security" value="<?php echo wp_create_nonce( "soma-reset" ); ?>" />
					<input type="hidden" name="action" value="purge">
					<li>
						<input type="submit" value="PURGE DATA" class="clicker"/>
					</li>
				</ul>
			</form>

			<?php endif; ?>
		</div>
		<?php
	}

	function soma_options_validate($input) {
		// ensure date in mysql format
		// $input['open_date'] = date("Y-m-d", strtotime($input['open_date']));
		// $input['close_date'] = date("Y-m-d", strtotime($input['close_date']));
		// strip html from textboxes
		// $input['textarea_one'] =  wp_filter_nohtml_kses($input['textarea_one']); // Sanitize textarea input (strip html tags, and escape characters)
		// $input['txt_one'] =  wp_filter_nohtml_kses($input['txt_one']); // Sanitize textbox input (strip html tags, and escape characters)
		// anything else?
		return $input;
	}

	//
	function flush_rules() {
		check_admin_referer( 'soma-flush', 'security' ); // die if invalid or missing nonce

		flush_rewrite_rules();

		$result = add_query_arg('result','flush-success', $_SERVER['HTTP_REFERER']);	//
		wp_redirect( $result );		// send us back to plugin page
		exit();
	}

	// kills everything!!
	function purge_all_data() {
		check_admin_referer( 'soma-reset', 'security' ); // die if invalid or missing nonce
		// do purging...

		$assets = get_posts( array(
			'suppress_filters' => true,
			'post_type' => array('applications','letters','attachment'),
			'post_status' => 'any',
			'numberposts' => -1
		) );
		foreach ($assets as $asset) {
			wp_delete_post($asset->ID, true);
		}

		$result = add_query_arg('result','purge-success', $_SERVER['HTTP_REFERER']);	//
		wp_redirect( $result );		// send us back to plugin page
		exit();
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
		$user = get_userdata($user_id);
		$fullname = $_POST['first_name'] . " " . $_POST['last_name'];
		if ($user->display_name != $fullname) {
			wp_update_user( array('ID' => $user_id, 'display_name' => $fullname) );
		}
		return null;
	}

	// modifies the user profile page fields
	function extend_user_contactmethod( $contactmethods ) {
		$opt = get_option('somatic_framework_options');
		$prefix = $opt['meta_prefix'];
		$contactmethods[$prefix .'user_phone'] = 'Phone Number';
		$contactmethods[$prefix .'user_facebook'] = 'Facebook Profile URL';
		unset($contactmethods['yim']);
		unset($contactmethods['aim']);
		unset($contactmethods['jabber']);
		return $contactmethods;
	}

	//
	function disable_dashboard_widgets() {
		$opt = get_option('somatic_framework_options');
		if ($opt['dashboard_quick_press']) remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
		if ($opt['dashboard_recent_drafts']) remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
		if ($opt['dashboard_recent_comments']) remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
		if ($opt['dashboard_incoming_links']) remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');
		if ($opt['dashboard_plugins']) remove_meta_box('dashboard_plugins', 'dashboard', 'normal');
		if ($opt['dashboard_primary']) remove_meta_box('dashboard_primary', 'dashboard', 'side');
		if ($opt['dashboard_secondary']) remove_meta_box('dashboard_secondary', 'dashboard', 'side');
		if ($opt['thesis_news_widget']) remove_meta_box('thesis_news_widget', 'dashboard', 'normal');
	}

	//
	function disable_admin_menus() {
		$opt = get_option('somatic_framework_options');
		if ($opt['hide_links_menu'])
			remove_menu_page('link-manager.php');
		if ($opt['hide_comments_menu'])
			remove_menu_page('edit-comments.php');
		if ($opt['hide_media_menu'])
			remove_menu_page('upload.php');
	}
	
	//
	function disable_autosave() {
		$opt = get_option('somatic_framework_options');
		global $post;
		if ( is_array( $opt[ 'kill_autosave' ] ) && in_array( $post->post_type, $opt[ 'kill_autosave' ] ) ) {
			wp_dequeue_script('autosave');
			// soma_dump($post->post_type,"no autosave for you");
		}

	}

	// not sure if this works - need to test
	function disable_revisions() {
		$opt = get_option('somatic_framework_options');
		if ( is_array( $opt[ 'kill_revisions' ] ) ) {
			foreach ($opt[ 'kill_revisions' ] as $type) {
				remove_post_type_support( $type, 'revisions' );
			}
		}
	}
	
	// this is very deeeeep - need to finish and create UI for it above
	function disable_support() {
		if ( post_type_supports($post_type, $feature) && $opt['kill_support'] ) {
			
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

}

$somaOptions = new somaOptions();