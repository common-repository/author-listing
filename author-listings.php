<?php
/*
Plugin Name: Author Listings
Plugin URI: http://wordpress.org/extend/plugins/author-listing/
Description: Provides template tags to which list the authors which have recently been active (or not active).
Author: Simon Wheatley
Version: 1.11b
Author URI: http://simonwheatley.co.uk/wordpress/
*/

/*  Copyright 2008 Simon Wheatley

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
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

require_once ( dirname (__FILE__) . '/plugin.php' );
require_once ( dirname (__FILE__) . '/author.php' );

/**
 * A Class to list all active (or inactive) authors in the WordPress installation.
 *
 * Extends John Godley's WordPress Plugin Class, which adds all sorts of functionality
 * like templating which can be overriden by the theme, etc.
 * 
 * The following functions hook up public methods from this class are effectively template 
 * tags, see their own documentation for further information:
 * * list_inactive_authors()
 * * list_active_authors()
 *
 * @package default
 * @author Simon Wheatley
 **/
class AuthorListing extends AuthorListing_Plugin
{
	/**
	 * The number of days over which we are considering the activity
	 * to have taken place.
	 *
	 * @var integer
	 **/
	public $cut_off_days;
	
	/**
	 * Determines whether password protected posts are considered or ignored. Defaults 
	 * to false.
	 *  
	 * You can change this flag like so:
	 * $AuthorListing->include_protected_posts = true;
	 * (After this point all protected posts will be considered when looking for 
	 * recent posts).
	 *
	 * @var bool
	 **/
	public $include_protected_posts;
	

	/**
	 * Constructor for this class. 
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	function AuthorListing() 
	{
		$this->register_plugin ('author-listings', __FILE__);
		$this->cut_off_days = 30;
		$this->include_protected_posts = false;
		
//		$this->latest_category_post_ids( array( 10, 3, 11 ) );
//		$this->get_category_posts();
	}
	
	/**
	 * A template method to print the (in)active authors. Default HTML can be overriden
	 * by adding a new template file into view/author-listings/active-authors-list.php
	 * and/or view/author-listings/inactive-authors-list.php in the root of the 
	 * theme directory.
	 *
	 * @param int $active_authors optional Defaults to 1 to show active authors. Will be cast to boolean. Determines if we show active or inactive authors.
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function list_authors( $active_authors, $include_cats = array() )
	{
		$include_cats = $this->ensure_array_of_integers( $include_cats );
		
		$template_vars = array();
		if ( $active_authors ) {
			$template_vars['authors'] = $this->get_active_authors( $include_cats );
		} else {
			$template_vars['authors'] = $this->get_inactive_authors();
		}
		
		// Print the HTML
		if ( $active_authors ) {
			$this->render( 'active-authors-list', $template_vars );
		} else {
			$this->render( 'inactive-authors-list', $template_vars );
		}
	}
	
	/**
	 * A template method.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function list_active_category_posts( $exclude_cats )
	{
		$exclude_cats = $this->ensure_array_of_integers( $exclude_cats );

		$template_vars = array();
		$template_vars['posts'] = $this->get_category_posts( $exclude_cats );
		
		// Print the HTML
		$this->render( 'active-category-posts', $template_vars );
	}

	/**
	 * A protected method to return the UNIX timestamp at which our activity period starts
	 *
	 * @return int UNIX timestamp
	 * @author Simon Wheatley
	 **/
	protected function cut_off_time()
	{
		return time() - ( 60 * 60 * 24 * $this->cut_off_days );
	}
	

	/**
	 * A method to return the list of post IDs for the latest post in each category
	 *
	 * @param array $excluded_cats optional An array of category IDs to exclude from the selection.
	 * @return array WordPress Post IDs for the latest post in each category.
	 * @author Simon Wheatley
	 **/
	protected function get_latest_category_post_ids( $excluded_cats = array() )
	{
		global $wpdb;
		// Deep breath... this is going to take some time
		$unprepared_sql  = ' SELECT ';
		$unprepared_sql .= ' post.ID AS post_ID ';
		$unprepared_sql .= ' FROM ';
		$unprepared_sql .= '  ( '; // Subquery to retrieve the date of the latest post from each category
		$unprepared_sql .= '   SELECT MAX( post.post_date_gmt ) AS max_date ';
		$unprepared_sql .= "   FROM $wpdb->posts AS post ";
		$unprepared_sql .= "   INNER JOIN $wpdb->term_relationships AS term_relationships ON term_relationships.object_id = post.ID ";
		$unprepared_sql .= "   INNER JOIN $wpdb->term_taxonomy AS taxonomy ON term_relationships.term_taxonomy_id = taxonomy.term_taxonomy_id ";
		$unprepared_sql .= '    AND post.post_status = "publish" '; // Published posts only
		$unprepared_sql .= '    AND post.post_type = "post" ';
		$unprepared_sql .= '    AND taxonomy.taxonomy = "category" ';
		$unprepared_sql .= '   GROUP BY term_relationships.term_taxonomy_id ';
		$unprepared_sql .= ' ) AS cat_latest_date, ';
		$unprepared_sql .= " $wpdb->term_taxonomy AS taxonomy, ";
		$unprepared_sql .= " $wpdb->terms AS term, ";
		$unprepared_sql .= " $wpdb->posts AS post, ";
		$unprepared_sql .= " $wpdb->term_relationships AS term_relationships ";
		$unprepared_sql .= ' WHERE cat_latest_date.max_date = post.post_date_gmt ';
		// Post which are younger than our cutoff
		$unprepared_sql .= '  AND post.post_date_gmt >= FROM_UNIXTIME( %1$d ) ';

		// Don't select posts which are in an excluded category
		if ( ! empty( $excluded_cats ) ) {
			// We've checked the user input of excluded_cats are all integers earlier in list_*
			$excluded_cats_list = join( ',', $excluded_cats );
			$sql_exclude_cats = $wpdb->prepare( " AND term.term_id NOT IN ( $excluded_cats_list ) " );
			$unprepared_sql .= $sql_exclude_cats;
		}

		// ...resume normal SQL construction...
		$unprepared_sql .= '  AND term.term_id = taxonomy.term_id ';
		$unprepared_sql .= '  AND term_relationships.term_taxonomy_id = taxonomy.term_taxonomy_id ';
		$unprepared_sql .= '  AND term_relationships.object_id = post.ID ';
		// Published posts only, belt and braces as we check this in the subquery;
		$unprepared_sql .= '  AND post.post_status = "publish" ';
		// Posts only, another belt and braces as (again) we check this in the subquery;
		$unprepared_sql .= '  AND post.post_type = "post" ';
		$unprepared_sql .= '  AND taxonomy.taxonomy = "category" ';
		// It strikes me that post_password might be NULL or the empty string, best check both
		if ( ! $this->include_protected_posts ) $unprepared_sql .= "AND ( post.post_password IS NULL OR post.post_password = '' ) ";
		$unprepared_sql .= ' GROUP BY post.ID ';
		$unprepared_sql .= ' ORDER BY post.post_date_gmt ASC ';
		// Posts which are not in an excluded category (cont.)
		$sql = $wpdb->prepare( $unprepared_sql, $this->cut_off_time(), $sql_exclude_cats );

		$latest_category_post_ids = $wpdb->get_col( $sql );
		return $latest_category_post_ids;
	}

	/**
	 * A method to return the list of author IDs who have been active in the last 30 days.
	 *
	 * @return array WordPress User IDs for the authors who have been (in)active in the last 30 days.
	 * @author Simon Wheatley
	 **/
	protected function active_author_ids( $cats = array() )
	{
		global $wpdb;
		// SWTODO This SELECT DISTINCT query could need optimising (maybe check there's an index on post_author)
		// N.B. The greater than *or equal* prevents people falling in the infinitesimally
		// small crack where they posted their article *exactly* thirty years ago to the day. :)
		// N.B. This does NOT cope with posts being marked "private", which is different to password protecting posts
		$unprepared_sql  = " SELECT DISTINCT post.post_author ";
		$unprepared_sql .= " FROM $wpdb->posts AS post ";
		// From particular category
		$unprepared_sql .= "   INNER JOIN $wpdb->term_relationships AS term_relationships ON term_relationships.object_id = post.ID ";
		$unprepared_sql .= "   INNER JOIN $wpdb->term_taxonomy AS taxonomy ON taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id ";
		$unprepared_sql .= " WHERE post.post_date_gmt >= FROM_UNIXTIME( %d ) ";

		// From particular category
		if ( ! empty( $cats ) ) {
			// We've checked the user input of cats are all integers earlier in list_*
			$cats_list = join( ',', $cats );
			$sql_cats = $wpdb->prepare( ' AND taxonomy.term_id IN ( ' . $cats_list . ' ) ' );
			$unprepared_sql .= $sql_cats;
			$unprepared_sql .= " AND taxonomy.taxonomy = 'category' ";
		}

		$unprepared_sql .= "  AND post.post_status = 'publish' ";
		$unprepared_sql .= "  AND post.post_type = 'post' ";
		// It strikes me that post_password might be NULL or the empty string, best check both
		if ( ! $this->include_protected_posts ) {
			$unprepared_sql .= "AND ( post.post_password IS NULL OR post.post_password = '' ) ";
		}
		$unprepared_sql .= " ORDER BY post.post_date_gmt ASC ";
		$sql = $wpdb->prepare( $unprepared_sql, $this->cut_off_time() );

		$active_author_ids = $wpdb->get_col( $sql );
		return $active_author_ids;
	}

	/**
	 * A method to return the list of author IDs who have NOT been active in the last 30 days.
	 *
	 * @param boolean $active_authors optional Determines whether active or inactive author IDs are returned.
	 * @return array WordPress User IDs for the authors who have been (in)active in the last 30 days.
	 * @author Simon Wheatley
	 **/
	protected function inactive_author_ids()
	{
		global $wpdb;
		// SWTODO: This SELECT DISTINCT query may need optimising (maybe check there's an index on post_author)
		// N.B. This does NOT cope with posts being marked "private", which is different to password protecting posts
		$unprepared_sql  = "SELECT DISTINCT my_posts.post_author ";
		$unprepared_sql .= "FROM ( SELECT * FROM $wpdb->posts ORDER BY post_date_gmt DESC ) AS my_posts ";
		$unprepared_sql .= "WHERE my_posts.post_date_gmt < FROM_UNIXTIME( %d ) ";
		$unprepared_sql .= "AND my_posts.post_status = 'publish' ";
		$unprepared_sql .= "AND my_posts.post_type = 'post' ";

		// Now weed out those who HAVE posted in the period of activity.
		// active_author_ids shouldn't need checking as it's be retrieved as a col from the DB, not a user input
		$active_author_ids_list = join( ',', (array) $this->active_author_ids() );
		if ( ! empty( $active_author_ids_list ) ) {
			$unprepared_sql .= "AND my_posts.post_author NOT IN ( $active_author_ids_list ) ";
		}

		// It strikes me that post_password might be NULL or the empty string, best check both
		if ( ! $this->include_protected_posts ) $unprepared_sql .= "AND ( my_posts.post_password IS NULL OR my_posts.post_password = '' ) ";
		$unprepared_sql .= "ORDER BY my_posts.post_date_gmt DESC ";
		$sql = $wpdb->prepare( $unprepared_sql, $this->cut_off_time() );

		$inactive_author_ids = $wpdb->get_col( $sql );
		return $inactive_author_ids;
	}
	
	/**
	 * Takes a list of post IDs and returns them in reverse chronological order
	 *
	 * @param array $excluded_cats optional An array of category IDs to exclude from the selection.
	 * @return array WordPress Post IDs for the latest post in each category.
	 * @author Simon Wheatley
	 **/
	protected function order_post_ids_by_date( $post_ids = array() )
	{
		if ( empty( $post_ids ) ) return array();
		
		global $wpdb;
		$unprepared_sql  = " SELECT DISTINCT posts.ID ";
		$unprepared_sql .= " FROM $wpdb->posts AS posts ";

		// Awkward input sanitisation, best make sure all array values are integers
		// post_ids shouldn't need checking as it's be retrieved as a col from the DB, not a user input
		$post_ids_list = join( ',', $post_ids );
		// Normally we'd "prepare" these values in, but this would, unhelpfully, quote them as a string
		$unprepared_sql .= " WHERE posts.ID IN ( $post_ids_list ) ";

		$unprepared_sql .= "  AND posts.post_status = 'publish' ";
		$unprepared_sql .= "  AND posts.post_type = 'post' ";
		$unprepared_sql .= " ORDER BY posts.post_date_gmt DESC ";

		$post_ids_list = join( ',', $post_ids );
		$sql = $wpdb->prepare( $unprepared_sql, $post_ids_list );

		$ordered_post_ids = $wpdb->get_col( $sql );
		return $ordered_post_ids;
	}
	
	/**
	 * Takes an array and ensures that every element is cast to an integer
	 *
	 * @param array 
	 * @return array of integers
	 * @author Simon Wheatley
	 **/
	protected function ensure_array_of_integers( $my_array )
	{
		if ( empty( $my_array ) ) return array();
		
		$new_array = array();
		foreach ( $my_array AS $key => $value ) {
			$new_array[ $key ] = (int) $value;
		}

		return $new_array;
	}

	/**
	 * A getter which returns an array of active authors as WP_User objects
	 *
	 * @return array An array of WP_User objects.
	 * @author Simon Wheatley
	 **/
	protected function get_active_authors( $include_cats )
	{
		$author_ids = $this->active_author_ids( $include_cats );
		$active_authors = array();
		foreach ( $author_ids AS $author_id ) {
			$author = new AL_Author( $author_id );
			$author->include_protected_posts = $this->include_protected_posts;
			$active_authors[] = $author;
		}
		// All ready.
		return $active_authors;
	}

	/**
	 * A getter which returns an array of active authors as WP_User objects
	 *
	 * @return array An array of WP_User objects.
	 * @author Simon Wheatley
	 **/
	protected function get_inactive_authors()
	{
		$author_ids = $this->inactive_author_ids( false );
		$inactive_authors = array();
		foreach ( $author_ids AS $author_id ) {
			$author = new AL_Author( $author_id );
			$author->include_protected_posts = $this->include_protected_posts;
			$inactive_authors[] = $author;
		}
		// All ready.
		return $inactive_authors;
	}

	/**
	 * A getter
	 *
	 * @return array An array of WP_Post objects.
	 * @author Simon Wheatley
	 **/
	protected function get_category_posts( $exclude_cats )
	{
		// Get all relevant post IDs for category posts
		$category_post_ids = $this->get_latest_category_post_ids( $exclude_cats );

		// Get the active authors in the excluded categories
		$active_author_ids = $this->active_author_ids( $exclude_cats );
		
		$author_post_ids = array();

		foreach ( $active_author_ids AS $author_id ) {
			$author = new AL_Author( $author_id );
			array_push( $author_post_ids, $author->latest_post_id( $exclude_cats ) );
		}

		$post_ids = $this->order_post_ids_by_date( array_merge( $author_post_ids, $category_post_ids ) );

		$posts = array();
		foreach( $post_ids AS $post_id ) {
			$post = get_post( $post_id );
			$post->al_category_entry = ( in_array( $post_id, $category_post_ids ) ) ? true : false;
			$post->al_author_entry = ( in_array( $post_id, $author_post_ids ) && ! $post->al_category_entry ) ? true : false;
			$posts[] = $post;
		}

		return $posts;
	}
	
}

/**
 * Instantiate the plugin
 *
 * @global
 **/

$AuthorListing = new AuthorListing();

/**
 * A template tag function which wraps the list_authors method from the 
 * AuthorListing class for convenience.
 *
 * @param string $args optional A string of URL GET alike variables which are parsed into params for the method call
 * @return void Prints some HTML
 * @author Simon Wheatley
 **/
function list_active_authors( $args = null )
{
	global $AuthorListing;

	// Traditional WP argument munging.
	$defaults = array(
		'days' => 30,
		'include_protected_posts' => false,
		'include_cats' => false
	);
	$r = wp_parse_args( $args, $defaults );
	
	// Sort out include_protected_posts arg
	if ( $r['include_protected_posts'] == 'yes' ) {
		$r['include_protected_posts'] = true;
	}
	if ( $r['include_protected_posts'] == 'no' ) {
		$r['include_protected_posts'] = false;
	}
	// Now cast to a boolean to be sure
	$r['include_protected_posts'] = (bool) $r['include_protected_posts'];
	
	// Set the activity period
	$AuthorListing->cut_off_days = $r['days'];
	
	// Set the protected posts
	$AuthorListing->include_protected_posts = $r['include_protected_posts'];	
	
	// Sort out excluded categories
	$include_cats = array();
	if ( $r['include_cats'] ) $include_cats = explode( ',', $r['include_cats'] );

	// Call the method
	$active = true;
	$AuthorListing->list_authors( $active, $include_cats );
}

/**
 * A template tag function which wraps the list_authors method from the 
 * AuthorListing class for convenience.
 *
 * @param string $args optional A string of URL GET alike variables which are parsed into params for the method call
 * @return void Prints some HTML
 * @author Simon Wheatley
 **/
function list_inactive_authors( $args = null )
{
	global $AuthorListing;

	// Traditional WP argument munging.
	$defaults = array(
		'days' => 30,
		'include_protected_posts' => false
	);
	$r = wp_parse_args( $args, $defaults );
	
	// Sort out include_protected_posts arg
	if ( $r['include_protected_posts'] == 'yes' ) {
		$r['include_protected_posts'] = true;
	}
	if ( $r['include_protected_posts'] == 'no' ) {
		$r['include_protected_posts'] = false;
	}
	// Now cast to a boolean to be sure
	$r['include_protected_posts'] = (bool) $r['include_protected_posts'];
		
	// Set the activity period
	$AuthorListing->cut_off_days = $r['days'];
	
	// Set the protected posts
	$AuthorListing->include_protected_posts = $r['include_protected_posts'];	
	
	// Call the method
	$active = false;
	$AuthorListing->list_authors( $active );
}

/**
 * A template tag function
 *
 * @param string $args optional A string of URL GET alike variables which are parsed into params for the method call
 * @return void Prints some HTML
 * @author Simon Wheatley
 **/
function list_active_categories( $args = null )
{
	global $AuthorListing;

	// Traditional WP argument munging.
	$defaults = array(
		'days' => 30,
		'include_protected_posts' => false,
		'exclude_cats' => false
	);
	$r = wp_parse_args( $args, $defaults );
	
	// Sort out include_protected_posts arg
	if ( $r['include_protected_posts'] == 'yes' ) {
		$r['include_protected_posts'] = true;
	}
	if ( $r['include_protected_posts'] == 'no' ) {
		$r['include_protected_posts'] = false;
	}
	// Now cast to a boolean to be sure
	$r['include_protected_posts'] = (bool) $r['include_protected_posts'];
		
	// Set the activity period
	$AuthorListing->cut_off_days = $r['days'];
	
	// Set the protected posts
	$AuthorListing->include_protected_posts = $r['include_protected_posts'];	
	
	// Sort out excluded categories
	$exclude_cats = array();
	if ( $r['exclude_cats'] ) $exclude_cats = explode( ',', $r['exclude_cats'] );
	
	// Call the method
	$AuthorListing->list_active_category_posts( $exclude_cats );
}


/**
 * A template tag function
 *
 * @return void Returns true if the current post is the latest post in a category, ONLY usable within the scope of this plugin
 * @author Simon Wheatley
 **/
function al_is_category_post()
{
	global $post;
	return (bool) @ $post->al_category_entry;	
}


/**
 * A template tag function
 *
 * @return void Returns true if the current post is a stand alone author post, ONLY usable within the scope of this plugin
 * @author Simon Wheatley
 **/
function al_is_author_post()
{
	global $post;
	return (bool) @ $post->al_author_entry;
}



?>