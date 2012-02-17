<?php
class somaSorter extends somaticFramework {

	function __construct() {
		add_action( 'admin_menu' , array( __CLASS__, 'soma_sort_menus' ) );
		add_action( 'wp_ajax_custom_type_sort', array( __CLASS__, 'soma_save_custom_type_order' ) );
		add_filter( 'parse_query', array(__CLASS__,'filter_current_query' ));
	}
	
	//** modifies the QUERY before output ------------------------------------------------------//
	function filter_current_query($query) {
		
		// list sortable types by menu_order instead of date, so we can manually adjust order - note: this means the sorting by title in the edit listings won't do anything....
		if ( somaTypes::$type_data[$query->post_type]['sortable'] ) {
			if ( $query->is_post_type_archive && !$query->query_vars['suppress_filters']) {
				$query->set( 'orderby', 'menu_order' );
				$query->set( 'order', 'ASC' );
			}
		}
		return $query;
	}
	

	//
	function soma_sort_menus() {
		$types = get_post_types( array( '_builtin' => false  ), 'objects' );
		foreach ($types as $type) {
			// only add sort pages to hierarchical post types, which support menu-order
			if (somaTypes::$type_data[$type->rewrite['slug']]['sortable']) {
				$menupage = add_submenu_page('edit.php?post_type='.$type->name, 'Sort '.$type->labels->name, 'Sort Order', 'edit_posts', 'sort-'. $type->name, array(__CLASS__,'soma_sort_page'));
				add_action( 'admin_print_styles-'.$menupage, array( __CLASS__, 'soma_sorter_print_styles' ) );
				add_action( 'admin_print_scripts-'.$menupage, array( __CLASS__, 'soma_sorter_print_scripts' ) );				
			}
		}
	}

	// sort page contents where ajax list is displayed
	function soma_sort_page() {
		$type = $_GET['post_type'];
		$type_obj = get_post_type_object($type);
		$query = new WP_Query('post_type='.$type.'&posts_per_page=-1&orderby=menu_order&order=ASC&suppress_filters=true');
	?>
	<div class="wrap">
		<h2><?php echo $type_obj->labels->singular_name ?> List Order<img src="<?php bloginfo('url'); ?>/wp-admin/images/loading.gif" id="loading-animation" /></h2>
		<ul id="custom-type-list">
		<?php while ( $query->have_posts() ) : $query->the_post(); ?>
			<li id="<?php the_id(); ?>" class="custom-type-list-item"><?php the_post_thumbnail(array('50,50'));?><a href="<?php echo get_edit_post_link($post->ID); ?>"><?php the_title(); ?></a></li>
		<?php endwhile; ?>
	</div>
	<?php
	}

	// admin js
	function soma_sorter_print_scripts() {
		wp_enqueue_script('jquery-ui-sortable');
		wp_enqueue_script('soma-sorter-js', SOMA_JS . 'soma-sorter.js');
	}

	// admin css
	function soma_sorter_print_styles() {
		wp_enqueue_style('soma-sorter', SOMA_CSS . 'soma-sorter.css');
	}

	// ajax callback function
	function soma_save_custom_type_order() {
		
		global $wpdb;

		$order = explode(',', $_POST['order']);
		$counter = 0;

		foreach ($order as $post_id) {
			$foo = $wpdb->update($wpdb->posts, array( 'menu_order' => $counter ), array( 'ID' => $post_id) );
			$counter++;
		}
		echo json_encode($foo);
		exit;

	}

}


// INIT
$somaSorter = new somaSorter();