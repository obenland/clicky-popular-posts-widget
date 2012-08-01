<?php
/** clicky-popular-posts-widget.php
 *
 * Plugin Name:		Clicky Popular Posts Widget
 * Plugin URI:		http://en.obenland.it/clicky-popular-posts-widget/#utm_source=wordpress&utm_medium=plugin&utm_campaign=clicky-popular-posts-widget
 * Description:		Display your top posts based on Clicky stats
 * Version:			1.1.2
 * Author:			Konstantin Obenland
 * Author URI:		http://en.obenland.it/#utm_source=wordpress&utm_medium=plugin&utm_campaign=clicky-popular-posts-widget
 * Text Domain: 	clicky-popular-posts-widget
 * Domain Path: 	/lang
 * License:			GPLv2
 */

 
if ( ! class_exists( 'Clicky_Api' ) ) {
	require_once( 'clicky-api.php' );
}


class Clicky_Popular_Posts_Widget extends WP_Widget {
	
	
	///////////////////////////////////////////////////////////////////////////
	// PROPERTIES, PRIVATE
	///////////////////////////////////////////////////////////////////////////
	
	/**
	 * The plugins' text domain
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 29.09.2011
	 * @access	private
	 *
	 * @var		string
	 */
	private $textdomain = 'clicky-popular-posts-widget';
	
	
	///////////////////////////////////////////////////////////////////////////
	// METHODS, PUBLIC
	///////////////////////////////////////////////////////////////////////////
	
	/**
	 * Constructor
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 29.09.2011
	 * @access	public
	 *
	 * @return	Clicky_Popular_Posts_Widget
	 */
	public function __construct() {
		
		load_plugin_textdomain( 'clicky-popular-posts-widget', false, 'clicky-popular-posts-widget/lang' );
		
		parent::__construct( 'clicky-popular-posts-widget', __( 'Clicky Popular Posts Widget', 'clicky-popular-posts-widget' ), array(
			'classname'		=>	'clicky-popular-posts-widget',
			'description'	=>	__( 'Display your top posts based on Clicky stats', 'clicky-popular-posts-widget' )
		) );
	}

	
	/**
	 * Displays the widget content
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 29.09.2011
	 * @access	public
	 *
	 * @param	array	$args
	 * @param	array	$instance
	 *
	 * @return	void
	 */
	public function widget( $args, $instance ) {
		
		if ( false === ( $output = get_transient( $this->id ) ) ) {
		
			$site_id	=	trim( $instance['site_id']  );
			$site_key	=	trim( $instance['site_key']  );
			if ( empty( $site_id ) OR  empty( $site_key ) ) {
				return;
			}
			
			ob_start();
			extract( $args );
			
			$clicky		=	new Clicky_Api( $site_id, $site_key );
			$clicky->flush_cache();
			$articles	=	array();
			$title		=	apply_filters( 'widget_title', $instance['title'] );
			
			$top_posts	=	$clicky->get( 'pages', array(
				'limit'		=>	$instance['number'] + 20,
				'date'		=>	$instance['date'],
				'output'	=>	'json'
			) );
			
			if ( is_wp_error( $top_posts ) ) {
				?><!-- <?php echo $top_posts->get_error_message(); ?> --><?php
				ob_end_flush();
				return;
			}
			
			foreach ( $top_posts[0]->dates[0]->items as $top_post ) {
				$post_id	=	url_to_postid( $top_post->url );
				if ( in_array( get_post_type( $post_id ), $instance['post_types'] ) ) {
					$articles[]	=	$post_id;
				}
			}
			$articles	=	array_unique( array_filter( $articles ) );
			
			if ( $articles ) {
				
				$articles	=	array_slice( $articles, 0, absint( $instance['number'] ) );
	
				echo $before_widget . $before_title . $title . $after_title . '<ul>';
				
				foreach ( $articles as $article_id ) { ?>
					<li>
						<a href="<?php echo get_permalink( $article_id ); ?>" title="<?php echo esc_attr( strip_tags( get_the_title( $article_id ) ) ); ?>">
							<?php echo get_the_title( $article_id ); ?>
						</a>
					</li>
				<?php
				}
				
				echo  '</ul>' . $after_widget;
			}
			
			set_transient( $this->id, ob_get_flush(), 60 * 60 * 24 );
		} else {
			echo $output;
		}
	}

	
	/**
	 * Updates the widget settings
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 29.09.2011
	 * @access	public
	 *
	 * @param	array	$new_instance
	 * @param	array	$old_instance
	 *
	 * @return	array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$new_instance = wp_parse_args( (array) $new_instance, array(
			'title'			=>	'',
			'site_id'		=>	'',
			'site_key'		=>	'',
			'number'		=>	5,
			'post_types'	=>	array( 'post' ),
			'date'			=>	'last-30-days'
		));

		$instance['title']		=	strip_tags( $new_instance['title'] );
		$instance['site_id']	=	trim( $new_instance['site_id'] );
		$instance['site_key']	=	trim( $new_instance['site_key'] );
		$instance['number']		=	absint( $new_instance['number'] );
		$instance['post_types']	=	array_intersect( get_post_types( array('public' => true) ), $new_instance['post_types'] );
		
		if ( array_key_exists( $new_instance['date'], $this->duration() ) )
			$instance['date']		=	$new_instance['date'];
		
		$clicky	=	new Clicky_Api( $instance['site_id'], $instance['site_key'] );
		$clicky->flush_cache();
		delete_transient( $this->id );

		return $instance;
	}

	
	/**
	 * Displays the widget's settings form
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 29.09.2011
	 * @access	public
	 *
	 * @param	array	$instance
	 *
	 * @return	void
	 */
	public function form( $instance ) {
		
		//Defaults
		$instance = wp_parse_args( (array) $instance, array(
			'title'			=>	'',
			'site_id'		=>	'',
			'site_key'		=>	'',
			'number'		=>	5,
			'post_types'	=>	array( 'post' ),
			'date'			=>	'last-30-days'
		));
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php esc_html_e( 'Title:', 'clicky-popular-posts-widget' ); ?>
				<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
			</label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'site_id' ); ?>"><?php esc_html_e( 'Site ID:', 'clicky-popular-posts-widget' ); ?>
				<input class="widefat" id="<?php echo $this->get_field_id( 'site_id' ); ?>" name="<?php echo $this->get_field_name( 'site_id' ); ?>" type="text" value="<?php echo esc_attr( $instance['site_id'] ); ?>" />
			</label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'site_key' ); ?>"><?php esc_html_e( 'Site Key:', 'clicky-popular-posts-widget' ); ?>
				<input class="widefat" id="<?php echo $this->get_field_id( 'site_key' ); ?>" name="<?php echo $this->get_field_name( 'site_key' ); ?>" type="text" value="<?php echo esc_attr( $instance['site_key'] ); ?>" />
			</label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of posts to show:', 'clicky-popular-posts-widget' ); ?>
				<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo absint( $instance['number'] ); ?>" size="2" />
			</label>
		</p>
		<p>
			<?php esc_html_e( 'Post Types:', 'clicky-popular-posts-widget' ); ?><br /><?php
			
			foreach ( get_post_types( array('public' => true), 'objects' ) as $post_type ): ?>
				<label for="<?php echo $this->get_field_id( 'post_types' ); ?>-<?php echo $post_type->name ?>">
					<input	id="<?php echo $this->get_field_id( 'post_types' ); ?>-<?php echo $post_type->name ?>"
							class="checkbox" type="checkbox"
							name="<?php echo $this->get_field_name( 'post_types' ); ?>[]"
							value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $instance['post_types'] ) ); ?> />
					&nbsp;<?php echo esc_html( $post_type->labels->name ); ?></label><br />
			<?php endforeach; ?>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'date' ); ?>"><?php esc_html_e( 'Date:', 'clicky-popular-posts-widget' ); ?>
				<select class="widefat" id="<?php echo $this->get_field_id( 'date' ); ?>" name="<?php echo $this->get_field_name( 'date' ); ?>">
					<?php foreach ( $this->duration() as $slug => $value ) {
						echo "<option value='{$slug}' " . selected( $instance['date'], $slug, false ) . ">{$value}</option>";
					} ?>
				</select>
			</label>
		</p>
	<?php
	}
	
	
	///////////////////////////////////////////////////////////////////////////
	// METHODS, PUBLIC
	///////////////////////////////////////////////////////////////////////////
	
	/**
	 * Returns the supported durations
	 *
	 * @author	Konstantin Obenland
	 * @since	1.1.1 - 17.04.2012
	 * @access	protected
	 *
	 * @return	array	The duration options
	 */
	protected function duration() {
		$duration = array(
			'last-7-days'	=>	__( 'Last seven days',	'clicky-popular-posts-widget' ),
			'last-14-days'	=>	__( 'Last two weeks',	'clicky-popular-posts-widget' ),
			'last-30-days'	=>	__( 'Last 30 days',		'clicky-popular-posts-widget' ),
			'last-60-days'	=>	__( 'Last 60 days',		'clicky-popular-posts-widget' ),
			'last-90-days'	=>	__( 'Last 90 days',		'clicky-popular-posts-widget' ),
			'last-180-days'	=>	__( 'Last 180 days',	'clicky-popular-posts-widget' ),
		);
		
		return apply_filters( 'clicky_popular_posts_widget_duration', $duration );
	}
} // End Class Clicky_Popular_Posts_Widget


/**
 * Hook it up
 *
 * @author	Konstantin Obenland
 * @since	1.0 - 29.09.2011
 */
function Clicky_Popular_Posts_Widget_Init() {
	register_widget( 'Clicky_Popular_Posts_Widget' );
}
add_action( 'widgets_init', 'Clicky_Popular_Posts_Widget_Init' );


/* End of file clicky-popular-posts-widget.php */
/* Location: ./wp-content/plugins/clicky-popular-posts-widget/clicky-popular-posts-widget.php */