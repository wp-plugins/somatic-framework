<?php
/*
Plugin Name: Somatic Framework
Plugin URI: http://wordpress.org/extend/plugins/somatic-framework/
Description: Adds useful classes for getting the most out of Wordpress' advanced CMS features
Version: 1.1.1
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
		// mapping wp hooks to internal functions
		add_action( 'init', array(__CLASS__,'init') );
		add_action( 'admin_init', array(__CLASS__,'admin_init') );
		add_action( 'admin_menu', array(__CLASS__,'admin_menu') );
		add_action( 'admin_head', array(__CLASS__,'admin_head') );
		add_action( 'admin_footer', array(__CLASS__, 'admin_footer') );

		add_action( 'wp_head', array(__CLASS__,'wp_head') );

		add_filter( 'login_headerurl', array(__CLASS__,'change_wp_login_url') );
		add_filter( 'login_headertitle', array(__CLASS__,'change_wp_login_title') );

		add_filter( 'query_vars', array(__CLASS__,'query_vars' ) );
		add_action( 'parse_request', array(__CLASS__, 'parse_request' ) );

		add_action( 'wp_print_styles', array(__CLASS__, 'wp_print_styles') );
		add_action( 'wp_print_scripts', array(__CLASS__, 'wp_print_scripts') );
		add_action( 'admin_print_styles', array(__CLASS__, 'admin_print_styles') );
		add_action( 'admin_print_scripts', array(__CLASS__, 'admin_print_scripts') );

		add_action( 'wp_footer', array(__CLASS__, 'wp_footer') );
		remove_action( 'wp_head', 'wp_generator' );

		register_activation_hook( __FILE__, array(__CLASS__,'activate') );
		register_deactivation_hook( __FILE__, array(__CLASS__,'deactivate') );
		
		// replace builtin with google hosted
		// wp_deregister_script('jquery-ui-core');
		// wp_register_script('jquery-ui-core', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.13/jquery-ui.min.js', false, '1.8.13');

		// framework scripts and styles
		wp_register_script('soma-admin-jquery', SOMA_JS.'soma-admin-jquery.js', array('jquery', 'jquery-ui-core'), '1.0', true);
		wp_register_style( 'soma-admin', SOMA_CSS.'soma-admin-styles.css', array(), '1.0', 'all' );

		// jquery plugin lightbox functionality
		wp_register_style( 'colorbox-theme', SOMA_JS.'colorbox/colorbox.css', array(), '1.3.8', 'screen' );
		wp_register_script( 'colorbox', SOMA_JS.'colorbox/jquery.colorbox-min.js', array('jquery'), '1.3.8' );
		
		// jquery UI
		wp_register_style('jquery-ui-theme', SOMA_JS. '/ui/smoothness/jquery-ui-1.8.13.custom.css', false, '1.8.13');
		
		// jplayer register
		wp_register_script( 'jplayer', SOMA_JS.'jquery.jplayer.min.js', array('jquery'), '2.1', false);
		wp_register_script( 'jplayer-playlist', SOMA_JS.'jplayer.playlist.min.js', array('jquery'), '2.1', false);
		wp_register_script( 'jplayer-inspector', SOMA_JS.'jquery.jplayer.inspector.js', array('jquery'), '2.1', false);
		wp_register_style( 'jplayer-style', SOMA_CSS.'jplayer-blue-skin/jplayer.blue.monday.css', array(), '2.1', 'all' );
		// wp_register_style( 'jplayer-style', SOMA_CSS.'jplayer-blackyellow-skin/jplayer-black-and-yellow.css', array(), '', 'all' );
	}


	function wp_print_styles() {
		// wp_enqueue_style('colorbox-theme');
	}

	function wp_print_scripts() {
		// wp_enqueue_script('colorbox');
		
		// pass constants and vars to javascript to be available for jquery
		global $post;
		if (is_admin()) {
			$admin = 'true';
		} else {
			$admin = 'false';
		}
		$params = array(
			'SOMA_JS' => SOMA_JS,
			'SOMA_DIR' => SOMA_IMG,
			'SOMA_URL' => SOMA_URL,
			'SOMA_INC' => SOMA_INC,
			'pid' => $post->ID,
			'type' => $post->post_type,
			'getsize' => 'thumb',
			'is_admin' => $admin,
			'ajaxurl' => admin_url('admin-ajax.php'),	 						// need to define because ajaxurl isn't defined on front-end, only admin
		);
		wp_localize_script( 'jquery', 'soma_vars', $params); 	// will place in footer because of jquery registered in footer
	}

	function admin_print_styles() {
		wp_enqueue_style( 'soma-admin' );
		wp_enqueue_style( 'colorbox-theme' );
	}

	function admin_print_scripts() {
		wp_enqueue_script( 'soma-admin-jquery' );
		wp_enqueue_script( 'colorbox' );
	}

	function init() {
	}

	function admin_init() {
	}

	function admin_menu() {
	}

	function admin_head() {
	}

	function admin_footer() {
	}

	function wp_head() {
	}

	function wp_footer() {
	}

	function activate() {
		flush_rewrite_rules();
	}

	function deactivate() {
	}

	function change_wp_login_url() {
		echo bloginfo('url');
	}

	function change_wp_login_title() {
		echo get_option('blogname');
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
?>