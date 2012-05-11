<?php
class somaSorter extends somaticFramework {

	function __construct() {
		add_action( 'admin_menu' , array( __CLASS__, 'soma_sort_menus' ) );
		add_action( 'wp_ajax_custom_type_sort', array( __CLASS__, 'soma_save_custom_type_order' ) );
		add_filter( 'parse_query', array(__CLASS__,'filter_current_query' ));
	}

	//** modifies the QUERY before output ------------------------------------------------------//
	// list sortable post types by menu_order instead of date, so we can manually adjust order - note: this means the sorting by title in the edit listings won't do anything....
	function filter_current_query($query) {
		$obj = $query->get_queried_object();

		// if this is a custom post type
		if ( $obj->sortable ) {
			$query->set( 'orderby', 'menu_order' );
			$query->set( 'order', 'ASC' );
			return $query;
		}
		// if this is a taxonomy or term, extract the post types (if *any* of the associated post types are set to be sortable, they'll all display in order...)
		if ($obj->taxonomy) {
			$tax = get_taxonomy($obj->taxonomy);
			foreach ($tax->object_type as $cpt) {
				$cptobj = get_post_type_object($cpt);
				if ($cptobj->sortable) {
					$query->set( 'orderby', 'menu_order' );
					$query->set( 'order', 'ASC' );
					return $query;
				}
			}
		}

		// nothing matched, pass along unfiltered
		return $query;
	}


	// generates sort order submenu page
	function soma_sort_menus() {
		$types = get_post_types( array( '_builtin' => false  ), 'objects' );
		foreach ($types as $type) {
			// only add sort pages to hierarchical post types, which support menu-order
			if ( $type->sortable ) {
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
?>
	<div class="wrap">
		<h2><?php echo $type_obj->labels->name ?> List Order</h2>
<?php
		// default query args
		$query_args = array(
			'post_type' => $type,
			'posts_per_page' => '-1',
			'orderby' => 'menu_order',
			'order' => 'ASC',
			'suppress_filters' => true,
		);
		// if we have grouping
		if (!is_null($type_obj->sort_group_type) && !is_null($type_obj->sort_group_slug)) {

			// container for all the custom type items
			echo '<ul id="type-sort-list">';

			// tax group
			if ($type_obj->sort_group_type == 'taxonomy') {
				$terms = get_terms($type_obj->sort_group_slug);
				foreach ($terms as $term) {
					$query_args['tax_query'] = array(		// add taxonomy term to query args
						array(
							'taxonomy' => $type_obj->sort_group_slug,
							'field' => 'slug',
							'terms' => $term->slug
						)
					);
					$query = new WP_Query($query_args); ?>
					<h3><?php echo $term->name; ?></h3>
					<?php while ( $query->have_posts() ) : $query->the_post(); ?>
						<li id="<?php the_id(); ?>" class="type-sort-list-item">
							<a class="title" href="<?php echo get_edit_post_link($post->ID); ?>"><?php the_title(); ?></a>
							<?php the_post_thumbnail(array('50,50'));?>
							<img src="<?php bloginfo('url'); ?>/wp-admin/images/loading.gif" class="loading-animation" style="display:none;"/>
							<div class="clearfix"></div>
						</li>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
					<?php
				}
			}
			echo '</ul>';
		// no grouping, just list them all
		} else {
			$query = new WP_Query($query_args); ?>
			<ul id="type-sort-list">
			<?php while ( $query->have_posts() ) : $query->the_post(); ?>
				<li id="<?php the_id(); ?>" class="type-sort-list-item">
					<a class="title" href="<?php echo get_edit_post_link($post->ID); ?>"><?php the_title(); ?></a>
					<?php the_post_thumbnail(array('50,50'));?>
					<img src="<?php bloginfo('url'); ?>/wp-admin/images/loading.gif" class="loading-animation" style="display:none;"/>
					<div class="clearfix"></div>
				</li>
			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>
			</ul>
			<?php
		}
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