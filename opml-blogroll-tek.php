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
			render_opml( $instance['source'] );
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
		delete_transient( "opml_blogroll_tek_list" );

		return $instance;
	}

}

function render_opml( $url )
{
	//So I can quickly disable buggy/unfinished code on the live site:
	//echo 'Disabled'; return;

	$list = fetch_opml( $url );
	if( $list === FALSE ) return;

	echo '<ul class="blogroll">'.PHP_EOL;
	foreach( $list as $outline )
	{
		echo '<li>';
		echo '<a href="'.$outline['htmlUrl'].'">';
		echo $outline['title'];
		echo '</a>';
		echo '</li>'.PHP_EOL;
	}
	echo '</ul>'.PHP_EOL;
}

function fetch_opml( $url )
{
	// Attempt to load from cache first
	$list = get_transient( "opml_blogroll_tek_list" );
	if( $list === FALSE )
	{
		echo '<p>uncached</p>';

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

		// Cache results so we don't have to get the OPML every time
		set_transient( "opml_blogroll_tek_list", $list, WEEK_IN_SECONDS );
	}

	return $list;
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
			$list[] = array( 'htmlUrl' => (string) $n['htmlUrl'], 'title' => (string) $n['title'] );
		}
	}
	return $list;
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
