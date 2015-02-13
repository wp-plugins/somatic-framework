<?php
// hook the somaRequest class which listens for soma_request query vars
// REPLACES THE OLD somaDownload CLASS
//
add_action( 'soma_request_legacy-download', 'soma_legacy_download' );
function soma_legacy_download($get) {

	// legacy call - the id's are stored in the download param of _GET
	if (isset($_GET['download'])) {
		$data = $_GET['download'];
	// new call, id's stored in data param and passed with action hook
	} else {
		$data = $get['data'];
	}


	// init group
	$group = array();


	// transfer requested image size
	if (isset( $_GET['getsize'])) {
		$size = $_GET['getsize'];
	} else {
		$size = "full";
	}

	// transfer eventual filename of zip file
	if (isset( $_GET['zipname'] ) ) {
		$group['zipname'] = sanitize_title($_GET['zipname']) . ' - ' . $_GET['getsize'];
	} else {
		$group['zipname'] = "Selected Items - " . $_GET['getsize'];
	}


	// build array of items to be processed for download

	//-- if we're viewing a single batch, then the download group should grab all the connected children of this batch
	if (isset( $_GET['batch'] ) ) {
		// init item array
		$items = array();
		$children = get_posts( array(
			'suppress_filters' => false,
			'post_type' => 'any',
			'post_status' => 'any',
			'connected_from' => intval($_GET['batch']),
			'numberposts' => -1	// needed to return all posts, not just the first 5
		) );
		// extract just the queried posts ID
		foreach ($children as $child) {
			$items[] = $child->ID;
		}
		// remove duplicates
		$items = array_unique($items);
		if (!$items || empty($items)) {
			wp_die('no items matched the download request!');
		}
	//-- package request
	} else if (isset( $_GET['package'] ) ) {
		// init item array
		$items = array();
		// retrieve stills with the requested package taxonomy term
		// $members = new WP_Query( array(
		// 	'post_type' => 'stills',
		// 	// 'post_status' => 'publish',
		// 	'taxonomy' => 'package',
		// 	'term' => $_GET['package'],
		// 	'posts_per_page' => -1
		// ) );
		$members = get_posts( array(
			'suppress_filters' => false,
			'post_type' => 'stills',
			// 'post_status' => 'publish',
			'taxonomy' => 'package',
			'term' => $_GET['package'],
			'numberposts' => -1
		) );
		// extract just the queried posts ID
		foreach ($members as $member) {
			$items[] = $member->ID;
		}
		if (isset( $_GET['include_releases'] ) ) {
		// retrieve assigned releases for each item
			foreach ($items as $item) {
				$releases = get_posts( array(
					'suppress_filters' => false,
					'post_type' => 'releases',
					'post_status' => 'publish',
					'connected_from' => $item,
					'posts_per_page' => -1
				) );
				foreach ($releases as $release) {
					// append connected release to the package list of items
					$items[] = $release->ID;
				}
			}
		}
		// remove duplicates
		$items = array_unique($items);
		if (!$items || empty($items)) {
			wp_die('no items matched the download request!');
		}
		// override the zipname with this package slug name
		$package = get_term_by('slug', $_GET['package'], 'package');
		$group['zipname'] = $package->slug . '-' . $size;

	//-- generic request, just grab serialized array of posts from the current query passed in _GET
	} else {
		$items = maybe_unserialize( $data );
		// convert to array if not yet
		if (!is_array($items)) {
			$items = array($items);
		}
	}

	// init group items
	$group['items'] = array();

	//
	foreach ($items as $item) {
		// populate the group with each item
		$group['items'][] = array(
			// retrieve path to original file
			'source' => get_attached_file($item),
			// name to be given to downloaded file
			'title' => basename(get_post_meta($item, '_wp_attached_file', true)),
			// mime-type
			'mime' => get_post_mime_type($item)
		);
	}

	// single file, just download it
	if (count($group['items']) == 1) {
		$item = $group['items'][0];
		$size = filesize($item['source']);
		header("Cache-Control: no-cache, must-revalidate"); 	// HTTP/1.1
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 		// Date in the past
		header("Content-Disposition: attachment; filename={$item['title']}");
		header("Content-type: {$item['mime']}");
		header("Content-Length: $size");
		readfile($item['source']);

	} else {
	// group of files, create a zip
		$zipfile = new zipfile();						// use zip_min.inc.php class
		$zipname = $group['zipname'].'.zip';

		foreach ( $group['items'] as $item) {
			$zipfile -> addFile(file_get_contents($item['source']), $item['title']);
		}
		// Force download the zip
		header("Cache-Control: no-cache, must-revalidate"); 	// HTTP/1.1
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 		// Date in the past
		header("Content-type: application/octet-stream");
		header("Content-Disposition: attachment; filename=$zipname");
		echo $zipfile -> file();

	}

	// save zip to server instead
	// $contents = $zipfile -> file();
	// file_put_contents("test.zip", $contents);

	exit;
}