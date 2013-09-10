<?php
//** force download of CSV file - adapted from baruch system, STILL INCOMPLETE!!

class somaExport extends somaticFramework {
	
	function __construct($id) {

		check_admin_referer( 'soma-export', 'security' ); // will die if invalid or missing nonce

		// init group
		$group = array();

		// build array of items to be processed for download

		if (isset( $_GET['year'] ) ) {
			// init item array
			$items = array();
			$members = get_posts( array(
				'suppress_filters' => false,
				'post_type' => array('applications'),
				'post_status' => 'any',
				'numberposts' => -1
			) );
			// extract just the queried posts ID
			foreach ($members as $member) {
				$items[] = $member->ID;
			}
			// remove duplicates
			$items = array_unique($items);
			if (!$items) {
				wp_die('no items matched the export request!');
			}
			$filename = "somatic-wp-".$_GET['year'] . ".csv";

		//-- generic request, just grab posts from the current query passed in the session var
		} else {
			if ( isset( $_GET['items'] ) ) {
				$items = maybe_unserialize($_GET['items']);
				// convert to array if not yet
				if (!is_array($items)) {
					$items = array($items);
				}
				$filename = "somatic-wp-export.csv";
			} else {
				wp_die('no items to export!');
			}
		}

		// init data container
		$data = array();

		// insert first header line for the CSV table
		$data[] = array(
			'ID',
			'FIRST NAME',
			'LAST NAME',
			'EMAIL',
			);


		foreach ($items as $item) {
			$post = get_post( $item );
			if ($post->post_type != "applications") continue;
			$date = date( 'm/d/Y', $post->post_date );
			// retrieve assigned releases for each item
			$release_list = array();
			$releases = get_posts( array(
				'suppress_filters' => false,
				'post_type' => 'releases',
				'post_status' => 'publish',
				'connected_from' => $item,
				'posts_per_page' => -1
			) );
			foreach ($releases as $release) {
				$release_list[] = $release->post_title;
			}
			$release_files = implode(", ", $release_list);
			// append line to the data array
			$data[] = array(
				$post->ID,
				soma_asset_meta('get', $post->ID, 'first_name'),
				soma_asset_meta('get', $post->ID, 'last_name'),
				soma_asset_meta('get', $post->ID, 'email'),
			 );
		}

		// generate file, write data to it, close it
		$filepath = SOMA_DIR . '/temp/'. $filename;
		$csvfile = fopen($filepath, 'w');
		foreach ($data as $line) {
		    fputcsv($csvfile, $line);
		}
		fclose($csvfile);

		// force download of file
		$size = filesize($filepath);
		header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
		header("Content-Disposition: attachment; filename={$filename}");
		header("Content-type: text/csv");
		header("Content-Length: $size");
		readfile($filepath);

		// delete temp file after downloading
		unlink($filepath);


		// save zip to server instead
		// $contents = $zipfile -> file();
		// file_put_contents("test.zip", $contents);
	}
}