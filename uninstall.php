<?php
//Don't uninstall unless you absolutely want to!
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	wp_die( 'WP_UNINSTALL_PLUGIN undefined.' );
}

// Delete option
delete_option( 'clicky-popular-posts-widget' );


/* Goodbye! Thank you for having me! */


/* End of file uninstall.php */
/* Location: ./wp-content/plugins/clicky-popular-posts-widget/uninstall.php */