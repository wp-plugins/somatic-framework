<?php
/*
Plugin Name: Somatic Framework
Plugin URI: http://wordpress.org/extend/plugins/somatic-framework/
Description: Adds useful classes for getting the most out of Wordpress' advanced CMS features
Version: 1.6.3
Author: Israel Curtis
Author URI: mailto:israel@somaticstudios.com
*/

/*
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


// don't load us directly!
if (!function_exists('is_admin')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

//** DECLARE CONSTANTS

// the server path to the plugin's directory
define( 'SOMA_DIR', WP_PLUGIN_DIR . '/somatic-framework/' );
// the URL path to the plugin's directory
define( 'SOMA_URL', WP_PLUGIN_URL . '/somatic-framework/' );
// the URL path to the plugin's image directory
define( 'SOMA_IMG', SOMA_URL . 'images/' );
// the server path to the plugin's includes
define( 'SOMA_INC', SOMA_DIR . 'inc/' );
// the server path to the plugin's dev includes
define( 'SOMA_DEV', SOMA_DIR . 'dev/' );
// the server path to the plugin's temporary storage
define( 'SOMA_TEMP', SOMA_DIR . 'temp/' );
// the URL path to the plugin's javascript
define( 'SOMA_JS', SOMA_URL . 'js/' );
// the URL path to the plugin's javascript
define( 'SOMA_CSS', SOMA_URL . 'css/' );

$wp_upload_dir = wp_upload_dir();
// the server path to the media uploads directory
define( 'WP_MEDIA_DIR', $wp_upload_dir['basedir'] );
// the URL path to the media uploads directory
define( 'WP_MEDIA_URL', $wp_upload_dir['baseurl'] );


if (!class_exists("somaticFramework")) :

class somaticFramework {

	function __construct() {
		//
		register_activation_hook( __FILE__, array(__CLASS__,'activate') );
		register_deactivation_hook( __FILE__, array(__CLASS__,'deactivate') );
		register_uninstall_hook( __FILE__, array(__CLASS__,'uninstall') );

		// mapping wp hooks to internal functions
		add_action( 'init', array(__CLASS__,'init') );
		add_action( 'admin_init', array(__CLASS__,'admin_init') );
		add_action( 'admin_menu', array(__CLASS__,'admin_menu') );
		add_action( 'admin_head', array(__CLASS__,'admin_head') );
		add_action( 'admin_footer', array(__CLASS__, 'admin_footer') );
		add_filter( 'admin_footer_text', array(__CLASS__,'admin_footer_text') );
		add_filter( 'plugin_action_links', array(__CLASS__, 'soma_plugin_action_links'), 10, 2 );

		add_action( 'wp_head', array(__CLASS__,'wp_head') );
		add_action( 'wp_footer', array(__CLASS__, 'wp_footer') );
		remove_action( 'wp_head', 'wp_generator' );				// hide wp version html output?

		add_action( 'wp_print_scripts', array(__CLASS__, 'wp_print_scripts') );		// only keeping this one around for localize_script() - prints on front and back end...
		add_action( 'wp_enqueue_scripts', array(__CLASS__, 'wp_enqueue_scripts') );
		add_action( 'admin_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts') );

		add_filter( 'login_headerurl', array(__CLASS__,'login_headerurl') );
		add_filter( 'login_headertitle', array(__CLASS__,'login_headertitle') );
		add_action( 'login_enqueue_scripts', array(__CLASS__,'login_enqueue_scripts'));
		add_action( 'login_footer', array(__CLASS__,'change_login_footer'));

		add_filter( 'query_vars', array(__CLASS__,'query_vars' ) );
		add_action( 'parse_request', array(__CLASS__, 'parse_request' ) );

		// framework scripts and styles
		wp_register_script( 'soma-admin-jquery', SOMA_JS.'soma-admin-jquery.js', array('jquery', 'jquery-ui-core'), '1.6', true);
		wp_register_style( 'soma-admin', SOMA_CSS.'soma-admin-styles.css', array(), '1.6', 'all' );

		wp_register_script( 'soma-public-jquery', SOMA_JS.'soma-public-jquery.js', array('jquery', 'jquery-ui-core'), '1.6', true);
		wp_register_style( 'soma-public', SOMA_CSS.'soma-public-styles.css', array(), '1.6', 'all' );
		wp_register_style( 'soma-login', SOMA_CSS.'soma-login-styles.css', array(), '1.6', 'all' );


		// jquery plugin lightbox functionality
		wp_register_style( 'colorbox-theme', SOMA_JS.'colorbox/colorbox.css', array(), '1.3.19', 'screen' );
		wp_register_script( 'colorbox', SOMA_JS.'colorbox/jquery.colorbox-min.js', array('jquery'), '1.3.19' );

		// jquery UI
		wp_register_style( 'jquery-ui-theme', SOMA_JS. 'ui/smoothness/jquery-ui-1.8.17.custom.css', false, '1.8.17');
		wp_register_script( 'jquery-ui-datepicker', SOMA_JS.'ui/jquery.ui.datepicker.min.js', array('jquery', 'jquery-ui-core'), '1.8.17', true);

		// autosize textareas  (not working quite right yet...)
		// wp_register_script( 'autosize', SOMA_JS.'jquery.autosize-min.js', array('jquery'), '1.6' );

		// going to need to register mediaelement.js instead of depending on external plugin....
	}


	// hooking front-end for scripts AND styles! -- https://wpdevel.wordpress.com/2011/12/12/use-wp_enqueue_scripts-not-wp_print_styles-to-enqueue-scripts-and-styles-for-the-frontend/
	function wp_enqueue_scripts() {

		if (!is_admin()) {
			wp_enqueue_script( 'soma-public-jquery' );
			wp_enqueue_style( 'soma-public' );
		}
		// wp_enqueue_script('colorbox');
	}

	// pass constants and vars to javascript to be available for jquery
	function wp_print_scripts() {
		global $soma_options;
		global $post;
		if ($post == null) {
			$pid = null;
			$type = null;
		} else {
			$pid = $post->ID;
			$type = $post->post_type;
		}
		if (is_admin()) {
			$admin = 'true';
		} else {
			$admin = 'false';
		}
		if ($soma_options['debug']) {
			$debug = 'true';
		} else {
			$debug = 'false';
		}
		if (class_exists('Debug_Bar') && $debug == 'true') {
			$debug_panel = 'true';
		} else {
			$debug_panel = 'false';
		}
		$params = array(
			'SOMA_JS' => SOMA_JS,
			'SOMA_DIR' => SOMA_IMG,
			'SOMA_URL' => SOMA_URL,
			'SOMA_INC' => SOMA_INC,
			'pid' => $pid,
			'type' => $type,
			'getsize' => 'thumb',
			'is_admin' => $admin,
			'debug' => $debug,
			'debug_panel' => $debug_panel,
			'ajaxurl' => admin_url('admin-ajax.php'),	 						// need to define because ajaxurl isn't defined on front-end, only admin
		);
		wp_localize_script( 'jquery', 'soma_vars', $params); 	// will place in footer because of jquery registered in footer
	}

	// hooking admin for scripts and styles!
	function admin_enqueue_scripts() {
		wp_enqueue_script( 'soma-admin-jquery' );
		wp_enqueue_script( 'colorbox' );
		// wp_enqueue_script( 'autosize' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style( 'soma-admin' );
		wp_enqueue_style( 'colorbox-theme' );
		wp_enqueue_style( 'jquery-ui-theme' );
	}

	// hooking login for scripts and styles!
	function login_enqueue_scripts() {
		wp_enqueue_style('soma-login');		// for some reason this is being output in the footer, instead of <head>...
		global $soma_options;
		if (!empty($soma_options['favicon'])) {
			echo "<link rel=\"shortcut icon\" href=\"{$soma_options['favicon']}\">";
		}
	}

	function init() {
	}

	function admin_init() {
		self::requires_wordpress_version();
	}

	function admin_menu() {
	}

	// back-end <head>
	function admin_head() {
		global $soma_options;
		if (!empty($soma_options['favicon'])) {
			echo "<link rel=\"shortcut icon\" href=\"{$soma_options['favicon']}\">";
		}
		if ($soma_options['bottom_admin_bar']) :
			?><style type="text/css" media="screen">
			* html body{margin-top:0 !important;}
			body.admin-bar{margin-top:-28px;padding-bottom:28px;}
			body.wp-admin #footer{padding-bottom:28px;}
			#wpadminbar{top:auto !important;bottom:0;}
			#wpadminbar .quicklinks .ab-sub-wrapper{bottom:28px;}
			#wpadminbar .quicklinks .ab-sub-wrapper ul .ab-sub-wrapper{bottom:-7px;}
			</style><?php
		endif;
	}

	function admin_footer() {
	}

	// front-end <head>
	function wp_head() {
		global $soma_options;
		if (!empty($soma_options['favicon'])) {
			echo "<link rel=\"shortcut icon\" href=\"{$soma_options['favicon']}\">";
		}
		if ($soma_options['bottom_admin_bar']) :
			?><style type="text/css" media="screen">
			* html body{margin-top:0 !important;}
			body.admin-bar{margin-top:-28px;padding-bottom:28px;}
			body.wp-admin #footer{padding-bottom:28px;}
			#wpadminbar{top:auto !important;bottom:0;}
			#wpadminbar .quicklinks .ab-sub-wrapper{bottom:28px;}
			#wpadminbar .quicklinks .ab-sub-wrapper ul .ab-sub-wrapper{bottom:-7px;}
			</style><?php
		endif;

		// inject html5shim if IE
		global $is_IE;
		if ($is_IE) echo '<!--[if lt IE 9]><script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script><![endif]-->';
	}

	function wp_footer() {

	}

	// custom admin footer credit
	function admin_footer_text() {
		echo 'Somatic Framework '. self::get_plugin_version() .'<br />';
		// echo 'Built by <a href="http://www.somaticstudios.com">Somatic Studios</a><br />';
		echo get_num_queries() . " queries. " . timer_stop(0,3) . " seconds.";
	}

	function activate() {
		// somaOptions::set_wp_options();			// dangerous - only use on a fresh wp install!!
		somaOptions::init_soma_options();
		// somaOptions::setup_capabilities();		// creates/modifies user roles
		flush_rewrite_rules();
	}

	function deactivate() {

	}

	function uninstall() {
		if ( __FILE__ != WP_UNINSTALL_PLUGIN ) return;		// important: check if the file is the one that was registered with the uninstall hook (function)
		somaOptions::delete_soma_options();
		// get rid of any framework-generated pages?
		// get rid of custom user roles?
	}

	function login_headerurl() {
		echo bloginfo('url');
	}

	function login_headertitle() {
		echo get_option('blogname');
	}

	function change_login_footer() {
		echo "<div id=\"soma-login-footer\">Somatic Framework</div>\n";
	}

	// adds custom vars to query
	function query_vars($qvars) {
		$qvars[] = "download";
		return $qvars;
	}

	function parse_request($wp) {
		// listen for download query, init class, pass ID's
		if (array_key_exists('download', $wp->query_vars )) {
			new somaDownload($wp->query_vars['download']);
		}
	}

	function get_plugin_version() {
		if ( ! function_exists( 'get_plugins' ) )
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$plugin_folder = get_plugins( '/' . plugin_basename( dirname( __FILE__ ) ) );
		$plugin_file = basename( ( __FILE__ ) );
		return $plugin_folder[$plugin_file]['Version'];
	}

	function requires_wordpress_version() {
		global $wp_version;
		$plugin = plugin_basename( __FILE__ );
		$plugin_data = get_plugin_data( __FILE__, false );

		if ( version_compare($wp_version, "3.3", "<" ) ) {
			if( is_plugin_active($plugin) ) {
				deactivate_plugins( $plugin );
				wp_die( "'".$plugin_data['Name']."' requires WordPress 3.3 or higher, and has been deactivated! Please upgrade WordPress and try again.<br /><br />Back to <a href='".admin_url()."'>WordPress admin</a>." );
			}
		}
	}

	// Display a Settings link on the main Plugins page, under our plugin
	function soma_plugin_action_links( $links, $file ) {
		if ( $file == plugin_basename( __FILE__ ) ) {
			$soma_links = '<a href="'.get_admin_url().'admin.php?page=somatic-framework-options">'.__('Settings').'</a>';
			// make the 'Settings' link appear first
			array_unshift( $links, $soma_links );
		}
		return $links;
	}



}
// end somaticFramework class /////////////////////////////////////

endif;

// initiate the primary class ------------------------------------------------------//
if (class_exists("somaticFramework") && !$somaticFramework) {
	$somaticFramework = new somaticFramework();
}

// load all classes in /inc
foreach( glob( SOMA_INC ."*.php" ) as $filename) {
    require_once $filename;
}

// load classes for debug output
$soma_options = get_option('somatic_framework_options', null);
if (!is_null($soma_options) && $soma_options['debug']) {

	// php -> console hacks
	// require_once SOMA_DEV . 'ChromePhp.php';
	// require_once SOMA_DEV . 'FirePHPCore/fb.php

	// include kint class
	require SOMA_DEV . 'kint/Kint.class.php';

	// hook our own output panel in the Debug Bar plugin
	add_filter('debug_bar_panels', 'debug_bar_somatic_panel');
	function debug_bar_somatic_panel( $panels ) {
		// include our debug panel extensions
		require_once SOMA_DEV . 'debug-bar-panel.php';
		// build new panel array so we can re-order panels
		$newpanels = array();
		// PHP errors come first
		if ( $panels[0]->_title == "Notices / Warnings") { $newpanels[] = array_shift($panels); }
		// insert our somatic debug panel next
		$newpanels[] = new somaDebugBarPanel();
		// then the rest as they were
		foreach ($panels as $panel) { $newpanels[] = $panel; }
		// send back to debug bar for output
		return $newpanels;
	}
}