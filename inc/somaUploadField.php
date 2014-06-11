<?php

/**
 * Uses plUpload to accept files via drag and drop, shows a progress indicator for each one, and displays a row of thumbnails as a result
 * incoming files are resized (if width and height have been specified), and passed thru wp_handle_upload, which results in file/url/mimetype data, and the resulting file is placed in the uploads directory
 * these files are merely pending (not attachments yet), and can be removed from the pending list before saving the post, in which case the files are deleted from the uploads directory
 * hidden form inputs are created to store the file/url/mimetype of each pending image, and are destroyed if remove button is clicked
 * NOTE: if you leave the page before saving the post or deleting all pending images, the files will remain as orphans in the upload directory!
 * upon saving the post, the hidden inputs are parsed in save_asset() and inserted as attachments, with various sizes generated
 *
 * Inspired by code at http://www.krishnakantsharma.com/2012/01/image-uploads-on-wordpress-admin-screens-using-jquery-and-new-plupload/
 *
 * @since 1.7
 * @param $field - (array) metabox config data for a single field
 * @param $is_featured - (bool) is this an uploader for featured image selection? (optional)
 * @param $have_featured - (bool) does the post already have a featured image assigned? (optional)
 */

class somaUploadField extends somaticFramework {

	function __construct($field = null, $is_featured = false, $have_featured = false) {
		if (is_null($field)) return new WP_Error("missing","Must pass a field array...");

		$this->field = $field;
		$this->is_featured = $is_featured;

		// already have a featured image, hide the uploader till it's gone
		if ($is_featured && $have_featured) {
			$this->hidden = true;
		}


		// permitted file types by extension
		if (isset($this->field['allowed']) && is_array($this->field['allowed'])) {
			$this->allowed = implode(",", $field['allowed']);
		} else {
			$this->allowed = 'jpg,jpeg,gif,png,mp3';		// default
		}
		// max uploads total (per upload session, does not restrict number of attachments)
		if (isset($field['max'])) {
			$this->max = $field['max'];
		} else {
			$this->max = 20;							// default
		}
		if ($this->is_featured) {
			$this->max = 1;								// force only one file if featured
		}

	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'plupload-all' );			// wp core
		wp_enqueue_script( 'soma-plupload' );			// soma framework

		// config plupload vars
		$plupload_init = array(
			'runtimes' => 'html5,flash,silverlight',
			'browse_button' => $this->field['id'].'_plupload-browse-button',
			'container' => $this->field['id'].'_plupload-upload-ui',
			'drop_element' => $this->field['id'].'_drag-drop-area',
			'file_data_name' => $this->field['id'].'_async-upload',
			'filters' => array(array('title' => 'Allowed Files', 'extensions' => $this->allowed)),
			'max_file_count' => $this->max,
			'multiple_queues' => true,
			'unique_names' => true,
			'url' => admin_url('admin-ajax.php'),
			'flash_swf_url' => includes_url('js/plupload/plupload.flash.swf'),
			'silverlight_xap_url' => includes_url('js/plupload/plupload.silverlight.xap'),
			'urlstream_upload' => true,
			'multipart' => true,
			'multipart_params' => array(
				'_ajax_nonce' => wp_create_nonce($this->field['id'] . '_plupload'),		// security nonce
				'action' => 'plupload_action',											// the ajax action name
				'fieldID' => $this->field['id'],										// gets passed to ajax callback in $_POST
			),
			'filters' => array(
				'max_file_size' => wp_max_upload_size() . 'b',
    		),
		);
		if (isset($this->field['width']) && isset($this->field['height'])) {
			$plupload_init['resize'] = array(											// resizes incoming images to max height and width (no crop)
				'width' => $this->field['width'],
				'height' => $this->field['height'],
				'quality' => 90,
			);
		}
		wp_localize_script( 'soma-plupload', $this->field['id'].'_plupload_config', $plupload_init); 	// will place in footer because of soma-plupload registered in footer
	}

	/**
     * Enqueue scripts and styles
     *
     * @return void
     */
    public function print_scripts() {
    	return self::enqueue_scripts();
    }


    /**
     * Output field HTML
     *
     * @param string $html
     * @param mixed  $meta
     * @param array  $field
     *
     * @return string
     */
    public function html() {
		// for each uploaded file, hidden inputs will be created containing the values from wp_handle_upload, allowing deletion or attachment creation later
		// <input type="hidden" name="fieldID[0][file]" value="/home/www/uploads/myimage.jpg" class="storage-file" />
		// <input type="hidden" name="fieldID[0][url]" value="http://example.com/uploads/myimage.jpg" class="storage-url" />
		// <input type="hidden" name="fieldID[0][type]" value="image/jpeg" class="storage-type" />

		if ($this->is_featured) {
			$pendinglabel = "Pending ".$this->field['name'];
			$help = "Or drag and drop a file here";
		} else {
			$pendinglabel = "Pending Uploads <br/><span style='font-weight:normal;font-style:italic'>(save changes to commit)</span>";
 			$help = "Or drag and drop files here (". $this->max . " " . _n( 'item', 'items' , $this->max ) . " max)";
		}
		?>
		<div id="<?php echo $this->field['id']; ?>_plupload-upload-ui" data-fieldid="<?php echo $this->field['id']; ?>" class="plupload-container hide-if-no-js<?php echo $this->hidden ? " hidden" : null; ?>">
			<div id="<?php echo $this->field['id']; ?>_drag-drop-area" class="dropzone">
				<input id="<?php echo $this->field['id']; ?>_plupload-browse-button" type="button" value="Select Files to Upload" class="button" />
				<div class="dropzone-help"><?php echo $help; ?></div>
				<div class="filelist"><!-- uploading progress --></div>
			</div>
		</div>
		<?php echo $this->field['desc'] ? "<div class=\"field-desc\">". $this->field['desc'] : null, '</div></tr>';
		 ?>
		<tr id="<?php echo $this->field['id']; ?>_pending-thumbs" style="display:none;"><td class="field-label"><?php echo $pendinglabel; ?></td><td class="field-data">
			<div class="plupload-thumbs<? echo $this->is_featured ? " plupload-featured" : null; ?>" id="<?php echo $this->field['id']; ?>_plupload-thumbs"></div>
			<div class="clear"></div>
		</td></tr>
		<?php
	}


    /**
     * Upload
     * Ajax callback function
     *
     * @return error or (XML-)response
     */
	static function plupload_action() {
		header( 'Content-Type: text/html; charset=UTF-8' );
		if ( ! defined('DOING_AJAX' ) ) define( 'DOING_AJAX', true );
		// check ajax noonce
		$fieldID = $_POST["fieldID"];
		check_ajax_referer($fieldID . '_plupload');
		// handle file upload (requires file.php!)
		if ( ! function_exists( 'wp_handle_upload' ) ) require_once( ABSPATH . 'wp-admin/includes/file.php' );
		$upload = wp_handle_upload($_FILES[$fieldID . '_async-upload'], array('test_form' => false, 'action' => 'plupload_action'));

		// send the uploaded file url in response
		echo json_encode($upload);
		exit;
	}
}
// --> END class somaUploadField
add_action( 'wp_ajax_plupload_action', array('somaUploadField', 'plupload_action' ));	// plupload ajax callback (can't live inside the class for some reason...)