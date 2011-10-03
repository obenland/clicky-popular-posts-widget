<?php
/** clicky-api.php
 *
 * WordPress wrapper class for the Clicky API
 *
 * @author	Konstantin Obenland
 *
 * @version	1.0
 * @link	http://getclicky.com/help/api	Clicky API Documentation
 * @license	GPL2
 */
class Clicky_Api {
	
	
	///////////////////////////////////////////////////////////////////////////
	// PROPERTIES, PROTECTED
	///////////////////////////////////////////////////////////////////////////
	
	/**
	 * The plugin's textdomain
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 28.09.2011
	 * @access	protected
	 *
	 * @var		string
	 */
	protected $textdomain	=	'clicky-api';
	
	
	///////////////////////////////////////////////////////////////////////////
	// PROPERTIES, PRIVATE
	///////////////////////////////////////////////////////////////////////////
	
	/**
	 * Site ID
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 28.09.2011
	 * @access	private
	 *
	 * @var		string
	 */
	private $site_id;
	
	
	/**
	 * Key for the specific website
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 28.09.2011
	 * @access	private
	 *
	 * @var		string
	 */
	private $site_key;
	
	
	/**
	 * The api target URL for all blog requests
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 28.09.2011
	 * @access	private
	 *
	 * @var		string
	 */
	private $url	=	'http://api.getclicky.com/api/stats/4/';
	
	
	/**
	 * The cached data
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 28.09.2011
	 * @access	private
	 *
	 * @var		array
	 */
	private $cache;

	
	///////////////////////////////////////////////////////////////////////////
	// METHODS, PUBLIC
	///////////////////////////////////////////////////////////////////////////
	
	/**
	 * Constructor
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 28.09.2011
	 * @access	public
	 *
	 * @param	string	$site_id	Site ID
	 * @param	string	$site_key	Site Key
	 *
	 * @return	Clicky_Api
	 */
	public function __construct( $site_id, $site_key ) {
		
		$this->site_id	=	$site_id;
		$this->site_key	=	$site_key;
		
		$this->cache	=	get_option( $this->textdomain );
	}
	
	/**
	 * Fires the request and returns the result
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 28.09.2011
	 * @access	public
	 *
	 * @param	string	$type
	 * @param	array	$args	optional
	 *
	 * @return	array
	 */
	public function get( $type, $args = array() ) {
	
		if ( false === ($response = $this->cache_get( $type )) ) {

			$response	=	wp_remote_get( $this->build_url($type, $args) );

			if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
				
				if ( isset($args['output']) ) {
					switch ( $args['output'] ) {
						case 'json':
							$response	=	json_decode( wp_remote_retrieve_body($response) );
							break;
						case 'php':
							$response	=	maybe_unserialize( wp_remote_retrieve_body($response) );
							break;
						case 'xml':
							$response	=	apply_filters( 'clicky_api_xml_response_handler', wp_remote_retrieve_body($response) );
							break;
						case 'csv':
							$response	=	apply_filters( 'clicky_api_csv_response_handler', wp_remote_retrieve_body($response) );
							break;
						default:
							return new WP_Error(
								'clicky-api-output-error',
								__( 'Can\'t handle output type.', $this->textdomain ),
								$response
							);
					}
				} else {
					$response	=	apply_filters( 'clicky_api_xml_response_handler', wp_remote_retrieve_body($response) );
				}
				
				$this->cache_add( $type, $response );
				
			} else if ( ! is_wp_error($response) ) {
			
				$response	=	new WP_Error(
					'clicky-api-request_failed',
					 wp_remote_retrieve_header( $response, 'X-Application-Error-Message' ),
					$response
				);
			}
		}
		return $response;
	}
	
	
	/**
	 * Deletes all cached data for the applicaton
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 28.09.2011
	 * @access	public
	 *
	 * @return	boolean
	 */
	public function flush_cache() {
		unset( $this->cache[$this->site_id] );
		return update_option( $this->textdomain, $this->cache );
	}
	
	
	///////////////////////////////////////////////////////////////////////////
	// METHODS, PRIVATE
	///////////////////////////////////////////////////////////////////////////
	
	/**
	 * Sets cache data
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 28.09.2011
	 * @access	private
	 *
	 * @param	string	$type
	 * @param	array	$data
	 *
	 * @return	bool
	 */
	private function cache_add( $type, $data ) {
		$this->cache[$this->site_id][$type]	=	$data;
		return update_option( $this->textdomain, $this->cache );
	}
	
	
	/**
	 * Returns cache data
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 28.09.2011
	 * @access	private
	 *
	 * @param	string	$type	optional
	 *
	 * @return	array|boolean
	 */
	private function cache_get( $type = '' ) {
	
		if ( $type ) {
			if ( isset($this->cache[$this->site_id][$type] ) ) {
				return $this->cache[$this->site_id][$type];
			}
		} elseif ( isset($this->cache[$this->site_id]) ) {
			return $this->cache[$this->site_id];
		}
		
		return false;
	}
	
	
	/**
	 * Generates the request url based on the query args
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 28.09.2011
	 * @access	private
	 *
	 * @param	string	$type
	 * @param	array	$args	optional
	 *
	 * @return	string
	 */
	private function build_url( $type, $args = array() ) {
		
		$url			=	trailingslashit($this->url);
		$args['type']	=	$type;
		
		if ( is_ssl() ) {
			$url		=	str_replace( 'http://', "https://", $url );
		}
		
		return add_query_arg(
			wp_parse_args( $args, array(
				'site_id'	=>	$this->site_id,
				'sitekey'	=>	$this->site_key,
			)
		), $url );
	}
} // End of class Clicky_Api


/* End of file clicky-api.php */
/* Location: ./wp-content/mu-plugins/clicky-api.php */