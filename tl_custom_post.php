<?php
/*
Plugin Name: Demo: Custom Posts
Plugin URI: http://tobiaslabs.com/
Description: Add a custom post and modify the permalink structure for it.
Version: 0.1
Author: Tobias Labs
Author URI: http://tobiaslabs.com/
*/
$TL_Custom_Post = new TL_Custom_Post;
class TL_Custom_Post
{
	var $options = array(
		'database_post_type' => 'tl_demo_post',
		'permalink_structure' => '/myreallysweetpost/%year%/%monthnum%/%day%/%tl_demo_post%'
	);
	function __construct() {
	//die(var_dump(__FILE__));
		add_action( 'init', array( $this, 'Init' ) );
		add_filter( 'post_type_link', array( $this, 'PostPermalink' ), 10, 3 );
		register_activation_hook( __FILE__, array( $this, 'Activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'Deactivation' ) );
	}
	function Activation() {
		$this->PostRewrite( true );
	}
	function Deactivation() {
		flush_rewrite_rules();
	}
	function PostPermalink( $permalink, $post_id, $leavename ) {

		$post = get_post( $post_id );

		$rewritecode = array(
			'%year%',
			'%monthnum%',
			'%day%',
			'%hour%',
			'%minute%',
			'%second%',
			$leavename? '' : '%postname%',
			'%post_id%',
			'%category%',
			'%author%',
			$leavename? '' : '%pagename%',
		);

		if ( $post->post_type = $this->options['database_post_type'] && '' != $permalink && !in_array($post->post_status, array('draft', 'pending', 'auto-draft')) ) {
			$unixtime = strtotime($post->post_date);

			$category = '';
			if ( strpos($permalink, '%category%') !== false ) {
				$cats = get_the_category($post->ID);
				if ( $cats ) {
					usort($cats, '_usort_terms_by_ID'); // order by ID
					$category = $cats[0]->slug;
					if ( $parent = $cats[0]->parent )
						$category = get_category_parents($parent, false, '/', true) . $category;
				}
				// show default category in permalinks, without
				// having to assign it explicitly
				if ( empty($category) ) {
					$default_category = get_category( get_option( 'default_category' ) );
					$category = is_wp_error( $default_category ) ? '' : $default_category->slug;
				}
			}

			$author = '';
			if ( strpos($permalink, '%author%') !== false ) {
				$authordata = get_userdata($post->post_author);
				$author = $authordata->user_nicename;
			}

			$date = explode(" ",date('Y m d H i s', $unixtime));
			$rewritereplace =
			array(
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
			$permalink = str_replace($rewritecode, $rewritereplace, $permalink);
		}
		return $permalink;
	}
	function Init() {
		register_post_type( $this->options['database_post_type'], array(
			'labels' => array( 'name' => _x('Demo Posts', 'post type general name')	),
			'public' => true,
			'capability_type' => 'post',
			'map_meta_cap' => true,
			'hierarchical' => false,
			'menu_position' => 5,
			'menu_icon' => plugins_url( 'tl_custom_post/icon-16x16.png' ),
			'supports' => array('title','thumbnail','editor','comments','custom-fields','revisions'),
			'publicly_queryable' => true,
			'query_var' => false,
			'rewrite' => false
		) );
		$this->PostRewrite();
	}
	function PostRewrite( $flush = false ) {
		global $wp_rewrite;
		$wp_rewrite->add_rewrite_tag(
			"%".$this->options['database_post_type']."%",
			'([^/]+)',
			"post_type=".$this->options['database_post_type']."&name="
		);
		$wp_rewrite->add_permastruct( $this->options['database_post_type'], $this->options['permalink_structure'], false );
		if ( $flush )
			flush_rewrite_rules();
	}
}