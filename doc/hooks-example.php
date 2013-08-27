<?php
//** This code exists solely to declare the data for custom metaboxes with the somaticFramework! **//
//** it won't do you much good if you don't have the Somatic Framework installed **//



// this hooks into the somatic framework save routine, altering the $new data value for this special case
// IMPORTANT: you must return $new **unchanged** if your conditionals don't match. Otherwise you will override the saved data for *every* other case!!

add_filter( 'soma_field_new_save_data', 'default_enrol_dates', 10, 4 );
function default_enrol_dates($new, $field, $pid, $post) {
	if ($field['id'] == "enrol_start_date") {			// only fire for this specific field
		if ( empty( $_POST["enrol_start_date"] ) ) {	// if no date has been set
			$new = $_POST["start_date"];				// copy the value from the course start date
		}
	}
	if ($field['id'] == "enrol_end_date") {
		if ( empty( $_POST["enrol_end_date"] ) ) {
			$new = $_POST["end_date"];
		}
	}
	return $new;
}