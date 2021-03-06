<?php
/*
Plugin Name:  OPML Blogroll Plugin
Plugin URI:   https://github.com/tkrehbiel/opml-blogroll-tek/
Description:  WordPress Plugin to create a blogroll from a live OPML link.
Version:      20180122
Author:       Thomas Krehbiel
Author URI:   http://thomaskrehbiel.com/
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  wporg
Domain Path:  /languages
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Opml_Blogroll_Tek_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'opml_blogroll_tek_widget', // Base ID
			esc_html__( 'OPML Blogroll', 'text_domain' ), // Name
			array(
				// Use same class as Wordpress "Links" widget to match theme
				'classname' => "widget_links", 
				'description' => esc_html__( 'Create a Blogroll from a live OPML link', 'text_domain' ) 
				) // Args
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		echo PHP_EOL;
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
        }
        
        if ( empty($instance['source']) ) {
            echo '<p>';
            echo esc_html__( 'Please set source.' , 'text_domain' );
            echo '</p>';
        }
        else {
			render_opml( $instance['source'], $this->id );
        }
        
		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
        // Title of widget
        $title = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( 'New title', 'text_domain' );
        // OPML URL source for blogroll
		$source = ! empty( $instance['source'] ) ? $instance['source'] : esc_html__( '', 'text_domain' );
		// Using a simple checkbox to allow clearing the cache.
		// Checking the box doesn't set anything but it allows clicking the "Save" button.
		?>
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title:', 'text_domain' ); ?></label> 
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'source' ) ); ?>"><?php esc_attr_e( 'OPML URL:', 'text_domain' ); ?></label> 
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'source' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'source' ) ); ?>" type="text" value="<?php echo esc_attr( $source ); ?>">
		</p>
		<p>
		<input class="checkbox" type="checkbox" id="<?php echo $this->get_field_id( 'clearcache' ); ?>" name="<?php echo $this->get_field_name( 'clearcache' ); ?>" />
		<label for="<?php echo $this->get_field_id( 'clearcache' ); ?>"><?php esc_attr_e( 'Clear cache?', 'text_domain' ); ?></label>
		</p>
		<?php 
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['source'] = ( ! empty( $new_instance['source'] ) ) ? strip_tags( $new_instance['source'] ) : '';
		$instance['clearcache'] = 0;

		// Delete the transient cache every time settings are saved.
		// User might change the URL so always need to kill the cache.
		// Also a fast way to clear the cache if the OPML is updated.
		// Also clears any cache of RSS feeds so they are updated too.
		clear_cache($this->id);

		return $instance;
	}

}

function render_opml( $url, $id )
{
	//So I can quickly disable buggy/unfinished code on the live site:
	//echo 'Disabled'; return;

	$list = fetch_opml( $url, $id );
	if( $list === FALSE ) return;

	echo '<ul class="blogroll">'.PHP_EOL;
	foreach( $list as $outline )
	{
		echo '<li>';
		if( !empty( $outline['post_link']) && !empty( $outline['post_title'] ))
		{
			echo '<a class="opml_blogroll_post" href="'.esc_url( $outline['post_link'] ).'">';
			echo $outline['post_title'];
			echo '</a>';
			echo '<br/>';
		}
		echo '<a class="opml_blogroll_blog" href="'.esc_url( $outline['htmlUrl'] ).'">';
		echo $outline['title'];
		echo '</a>';
		if( !empty( $outline['handle'] ) )
		{
			echo '<br/>';
			echo '<a class="opml_blogroll_handle" href="'.esc_url( 'https://twitter.com/'.$outline['handle'] ).'">';
			echo '@'.$outline['handle'];
			echo '</a>';
		}
		if( !empty( $outline['post_link']) )
		{
			echo '<br/>';
			echo '<span class="opml_blogroll_date">';
			echo $outline['post_date'];
			echo '</span>';
		}
		echo '</li>'.PHP_EOL;
	}
	echo '</ul>'.PHP_EOL;
}

function fetch_opml( $url, $id )
{
	// Attempt to load from cache first
	$list = get_transient( "opml_blogroll_tek_list".$id );
	if( $list === FALSE )
	{
		echo '<p>(Fresh)</p>';

		$response = wp_remote_get( $url );
		if( is_wp_error( $response ) )
		{
			echo 'Cannot fetch OPML';
			return FALSE;
		}
		$body = wp_remote_retrieve_body( $response );
		if( empty($body) )
		{
			echo 'OPML body not found';
			return FALSE;
		}
		$xml = simplexml_load_string( $body );
		if( $xml === FALSE )
		{
			echo 'Cannot parse OPML XML';
			return FALSE;
		}

		$list = parse_opml( $xml->body->outline );

		usort( $list, 'compare_entries' );

		// Cache results so we don't have to get the OPML every time
		set_transient( "opml_blogroll_tek_list".$id, $list, HOUR_IN_SECONDS );
	}

	return $list;
}

// Callback to set fetch_feed() cache time
function fetch_feed_normal_cache( $seconds )
{
	return 3600*2; // 2 hours
}

// Callback to hopefully clear fetch_feed() caches
function fetch_feed_no_cache( $seconds )
{
	return 0;
}

// Clear the OPML cache and any RSS feed caches
function clear_cache( $id )
{
	$list = get_transient( "opml_blogroll_tek_list".$id );
	if( $list !== FALSE )
	{
		$olderrorlevel = error_reporting( E_ALL & ~E_WARNING );
		add_filter( 'wp_feed_cache_transient_lifetime', 'fetch_feed_no_cache' );
		include_once( ABSPATH . WPINC . '/feed.php' );
		foreach( $list as $outline )
		{
			$link = $outline['xmlUrl'];
			$rss = fetch_feed( $link );
		}	
		error_reporting( $olderrorlevel );
	}
	delete_transient( "opml_blogroll_tek_list".$id );
}

function parse_opml( SimpleXMLElement & $node )
{
	$list = array();
	foreach( $node->outline as $n )
	{
		if( !empty($n['htmlUrl']) && !empty($n['title']) )
		{
			// Have to cast as strings otherwise they are SimpleXMLElements,
			// which cannot be serialized and cached with set_transient().
			$entry = array( 
				'htmlUrl' => (string) $n['htmlUrl'],
				'xmlUrl' => (string) $n['xmlUrl']
			);

			// Test for title matching this pattern:
			// A Blog Title @handle
			// If so, split into title and Twitter handle.
			$title = (string) $n['title'];
			$entry['title'] = $title;
			$entry['handle'] = '';
			if( preg_match( "/^(.*) @(\w.*)$/", $title, $matches ) )
			{
				$entry['title'] = $matches[1];
				$entry['handle'] = $matches[2];
			}

			// If an RSS link is available, fetch it.
			// TODO: This is a huge hit to page load times.
			// Should re-work to load asynchronously.
			// Offhand don't know how to communicate
			// with WordPress caches through Javascript.
			if( !empty($n['xmlUrl']) )
			{
				fetch_opml_rss( $n['xmlUrl'], $entry );
			}

			$list[] = $entry;
		}
	}
	return $list;
}

function compare_entries( $a, $b )
{
	return -strcmp( $a['post_order'], $b['post_order'] );
}

function fetch_opml_rss( $link, & $entry )
{
	// Disable warnings because this Wordpress RSS feed reading thing
	// generates a ton of ugly warnings.
	$olderrorlevel = error_reporting( E_ALL & ~E_WARNING );
	// Set the cache timeout for RSS feeds - should be less than OPML fetch cache timeout
	add_filter( 'wp_feed_cache_transient_lifetime', 'fetch_feed_normal_cache' );
	include_once( ABSPATH . WPINC . '/feed.php' );
	// Uses WordPress's fetch_feed() function.
	// I'm led to believe that fetch_feed() does its own caching.
	$rss = fetch_feed( $link );
	if( !is_wp_error( $rss ) )
	{
		// We only want the most recent (first) item in the feed.
		$rss_count = $rss->get_item_quantity( 1 );
		if( $rss_count > 0 )
		{
			$rss_items = $rss->get_items( 0, 1 );
			// We only examine the most recent (presumably the first) item
			$item = $rss_items[0];
			$entry['post_title'] = $item->get_title();
			$entry['post_link'] = $item->get_permalink();
			$entry['post_date'] = $item->get_date( 'j F Y' );
			$entry['post_order'] = $item->get_date( 'YmdHis' );
		}
	}
	error_reporting( $olderrorlevel );
}

function parse_outline( SimpleXMLElement & $node )
{
	echo '<p>';
	render_outline( $node, $linklist );
	echo '</p>'.PHP_EOL;

	echo '<ul class="xoxo blogroll">'.PHP_EOL;
	foreach( $node->outline as $n )
	{
		echo '<li>';
		render_outline( $n, $linklist );
		echo '</li>'.PHP_EOL;
	}
	echo '</ul>'.PHP_EOL;
}

// Render a single outline node.
// Could be either a folder or a feed item,
// depending on the XML contents.
function render_outline( SimpleXMLElement & $outline )
{
	if( !empty($outline['htmlUrl']) 
		&& !empty($outline['title']) )
	{
		echo '<a href="'.$outline['htmlUrl'].'">';
		echo $outline['title'];
		echo '</a>';
	}
	else
	{
		echo $outline['title'];
	}
}

add_action('widgets_init', create_function('', 'return register_widget("Opml_Blogroll_Tek_Widget");'));
