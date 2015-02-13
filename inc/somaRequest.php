<?php
/**
 * Handler for custom action links. Allows triggering of functions by URL, much like wp_ajax_
 * Allows links to execute actions without form submission, thus allowing them to live within forms (like post editor) without triggering the form
 * Useful especially for triggering downloads, as the hooked function can use custom headers to push the output to a file, without leaving the current page
 *
 * @since 1.8.5
 * @param $action - (string) identifier used to assemble the action hook
 * @param $_GET - (array) key/value pairs passed as URL parameters [NOTE: all values are visible in the string, so do not send passwords or sensitive data]
 */

class somaRequest extends somaticFramework {

	function __construct($action) {

		// old nonce, backward compatible for pre-1.8.5 calls to somaDownload class
		if ( isset( $_GET['download'] ) ) {
			if ( !isset( $_GET['security'] ) || !wp_verify_nonce( $_GET['security'], 'soma-download' ) ) wp_die( "Sorry, security token missing or invalid. Also - you should use the new somaRequest class for downloads!", "Security Error", array('back_link' => true));
		} else {
			// new universal request nonce
			if ( !isset( $_GET['security'] ) || !wp_verify_nonce( $_GET['security'], 'soma-request' ) ) wp_die( "Sorry, security token missing or invalid.", "Security Error", array('back_link' => true)); 				// will die if invalid or missing nonce
		}

		// make sure request has a point
		if ( empty($action) ) wp_die( "Sorry, no action was passed with the request.", "Action Failure", array( 'back_link' => true ) );

		// only work for logged in users or allow if request indicates public
		if (  is_user_logged_in() || soma_fetch_index( $_GET ,'public' ) == true ) {
			// trigger action hook, pass along the params
			do_action( 'soma_request_' . $action, $_GET );
			// die gracefully in case action hook fails to go anywhere
			wp_die( "Sorry, but this link goes nowhere. Contact the developer.", "Link Error", array( 'back_link' => true ) );
			// header('Location: ' . $_SERVER['HTTP_REFERER']);	// just goes back quietly
		} else {
			 wp_die( "Sorry, you must be logged in to access this content.", "Security Error", array( 'back_link' => true ) );
		}
	}
}