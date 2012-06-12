<?php
/*
Plugin Name: Demo: Custom Posts
Plugin URI: http://tobiaslabs.com/
Description: Add a custom post and modify the permalink structure for it.
Version: 0.1
Author: Tobias Labs
Author URI: http://tobiaslabs.com/
*/

$tlwp_custom_post = new tlwp_custom_post;

class tlwp_custom_post
{
	// these are set and explained in __construct()
	var $options = array();
	
	function __construct()
	{
		// this is the name of the post type in the database (e.g., WordPress
		// uses "post", "attachment", "revision", etc.)
		$this->options['database_post_type'] = 'tl_demo_post';
		
		// this is the permalink schema of the URL, e.g. http://site.com/2012/03/14/my-post
		$this->options['permalink_structure'] = '/myreallysweetpost/%year%/%monthnum%/%day%/%'.$this->options['database_post_type'].'%';
		
		// on activation and deactivation we need to flush the rewrite rules
		// to make the new custom permalink rule take place
		register_activation_hook( __FILE__, array( $this, 'activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );
		
		// the basic setup is here
		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'post_type_link', array( $this, 'post_permalink' ), 10, 3 );
	}
	
	function activation()
	{
		// see the function for details
		$this->post_rewrite( true );
	}
	
	function deactivation()
	{
		// this is done on deactivation to make sure links to the custom
		// permalink schema are not kept in the redirect rules
		flush_rewrite_rules();
	}
	
	function init()
	{
		// you can write the correct names into the labels array, or just
		// rename this variable to your own
		$name = "Demo Post";
	
		// registration of the custom post, for full details look at:
		// http://codex.wordpress.org/Function_Reference/register_post_type
		register_post_type(
			$this->options['database_post_type'],
			array(
				'labels' => array(
					'name' => _x( $name.'s', 'post type general name' ),
					'singular_name' => _x( $name, 'post type singular name' ),
					'add_new' => _x('Add New', $this->options['database_post_type'] ),
					'add_new_item' => __( 'Add New '.$name ),
					'edit_item' => __( 'Edit '.$name ),
					'new_item' => __( 'New '.$name ),
					'all_items' => __( 'All '.$name.'s' ),
					'view_item' => __( 'View '.$name ),
					'search_items' => __( 'Search '.$name.'s' ),
					'not_found' =>  __( 'No '.strtolower($name).'s found' ),
					'not_found_in_trash' => __( 'No '.strtolower($name).'s found in Trash' ), 
					'parent_item_colon' => '',
					'menu_name' => $name
				),
				'public' => true, // if this is true, the post will be publically viewable
				'publicly_queryable' => true,
				'show_ui' => true,
				'show_in_menu' => true,
				'capability_type' => 'post',
				'hierarchical' => false,
				'menu_position' => 5, // this is optional, removing it puts the item at the bottom of the menu
				
				// the menu icon must be 16x16, and for the hover shade change, you'll need two versions
				'menu_icon' => plugins_url( 'icon-shaded-16x16.png', __FILE__ ),
				
				// delete the ones you do not need
				'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions', 'page-attributes', 'post-formats' ),
				'map_meta_cap' => true,
				'query_var' => false,
				'rewrite' => false
			)
		);
		// finally, to get the custom URL schema, we need to rewrite the rules
		$this->post_rewrite();
	}
	
	function post_permalink( $permalink, $post_id, $leavename )
	{
		$post = get_post( $post_id );
		
		// these are the fields that you can use in your permalink schema
		$rewritecode = array(
			'%year%',
			'%monthnum%',
			'%day%',
			'%hour%',
			'%minute%',
			'%second%',
			$leavename ? '' : '%postname%',
			'%post_id%',
			'%category%',
			'%author%',
			$leavename ? '' : '%pagename%',
		);
		
		// for active posts of our cutom type, we need to rewrite the rules
		if ( ( $post->post_type = $this->options['database_post_type'] ) && ( '' != $permalink ) && ( !in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ) ) ) ) {
			
			$unixtime = strtotime( $post->post_date );

			$category = '';
			if ( strpos( $permalink, '%category%' ) !== false )
			{
				$categories = get_the_category( $post->ID );
				if ( $categories )
				{
					usort( $categories, '_usort_terms_by_ID' ); // order by term ID
					$category = $categories[0]->slug;
					if ( $parent = $categories[0]->parent )
						$category = get_category_parents( $parent, false, '/', true ) . $category;
				}
				// show default category in permalinks, without having to assign it explicitly
				if ( empty( $category ) )
				{
					$default_category = get_category( get_option( 'default_category' ) );
					$category = is_wp_error( $default_category ) ? '' : $default_category->slug;
				}
			}

			$author = '';
			if ( strpos( $permalink, '%author%' ) !== false )
			{
				$authordata = get_userdata( $post->post_author );
				$author = $authordata->user_nicename;
			}

			$date = explode( " ", date( 'Y m d H i s', $unixtime ) );
			$rewritereplace = array(
				$date[0],
				$date[1],
				$date[2],
				$date[3],
				$date[4],
				$date[5],
				$post->post_name,
				$post->ID,
				$category,
				$author,
				$post->post_name,
			);
			$permalink = str_replace( $rewritecode, $rewritereplace, $permalink );
		}
		return $permalink;
	}
	
	function post_rewrite( $flush = false )
	{
		global $wp_rewrite;
		$wp_rewrite->add_rewrite_tag(
			"%".$this->options['database_post_type']."%",
			'([^/]+)',
			"post_type=".$this->options['database_post_type']."&name="
		);
		$wp_rewrite->add_permastruct( $this->options['database_post_type'], $this->options['permalink_structure'], false );
		if ( $flush )
		{
			flush_rewrite_rules();
		}
	}
}