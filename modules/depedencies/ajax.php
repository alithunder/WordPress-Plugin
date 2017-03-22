f<?php
/*
* Define class aiowaffDashboardAjax
* Make sure you skip down to the end of this file, as there are a few
* lines of code that are very important.
*/
!defined('ABSPATH') and exit;
if (class_exists('aiowaffDashboardAjax') != true) {
    class aiowaffDashboardAjax extends aiowaffDashboard
    {
    	public $the_plugin = null;
		private $module_folder = null;
		
		/*
        * Required __construct() function that initalizes the Ali Framework
        */
        public function __construct( $the_plugin=array() )
        {
        	$this->the_plugin = $the_plugin;
			$this->module_folder = $this->the_plugin->cfg['paths']['plugin_dir_url'] . 'modules/dashboard/';
			
			// ajax  helper
			add_action('wp_ajax_aiowaffDashboardRequest', array( &$this, 'ajax_request' ));
		}
		
		/*
		* ajax_request, method
		* --------------------
		*
		* this will create requests to 404 table
		*/
		

			die(json_encode($return));
		}

		private function getPublishProductsWidthStatus( $limit=0 )
		{
			$key = '_amzASIN';
			$_key = $key;
			if ( $_key == '_amzASIN' ) $key = '_aiowaff_prodid';

			$ret = array();
			
			$args = array();
			$ret['products'] = array();
			$ret['stats']['nb_products'] = 0;
			$ret['stats']['total_hits'] = 0;
			$ret['stats']['total_redirect_to_amazon'] = 0;
			$ret['stats']['total_addtocart'] = 0;
				
			$args['post_type'] = 'product';
	
			$args['meta_key'] = $key;
			$args['meta_value'] = '';
			$args['meta_compare'] = '!=';
	
			// show all posts
			$args['fields'] = 'ids';
			$args['posts_per_page'] = '-1';
			
			$loop = new WP_Query( $args );
			$cc = 0;
			 
			if( count($loop->posts) > 0 ){
				
				$stats_query = "SELECT post_id, meta_key, meta_value FROM " . (  $this->the_plugin->db->prefix ) . "postmeta WHERE 1=1 AND post_id IN (" . ( implode(",", $loop->posts) ) . ")";
				$stats_query .= " AND ( meta_key='_amzaff_redirect_to_amazon' ";
				$stats_query .= " OR meta_key='_amzaff_addtocart' ";
				$stats_query .= " OR meta_key='_amzaff_hits' )";  
				
				$stats_results = $this->the_plugin->db->get_results( $stats_query, ARRAY_A );
				
				$products_status = array();
				// reodering here
				if( count($stats_results) > 0 ){
					foreach ($stats_results as $row ) {
						$products_status[$row['post_id']][$row['meta_key']] = $row['meta_value'];
					}
				}
				
				foreach ($loop->posts as $post) {
					
					$redirect_to_amazon = ( isset($products_status[$post]['_amzaff_redirect_to_amazon']) ? (int) $products_status[$post]['_amzaff_redirect_to_amazon'] : 0 );
					$addtocart = ( isset($products_status[$post]['_amzaff_addtocart']) ? (int) $products_status[$post]['_amzaff_addtocart'] : 0 );
					$hits = ( isset($products_status[$post]['_amzaff_hits']) ? (int) $products_status[$post]['_amzaff_hits'] : 0 );
					$score = ($redirect_to_amazon * 3) + ($addtocart * 2) + ($hits * 1);
					
					$ret['products'][$post] = array(
						'id' => $post,
						'score' => $score,
						'redirect_to_amazon' => $redirect_to_amazon,
						'addtocart' => $addtocart,
						'hits' => $hits
					);
					
					$ret['stats']['nb_products'] = $ret['stats']['nb_products'] + 1;
					$ret['stats']['total_hits'] = $ret['stats']['total_hits'] + $hits;
					$ret['stats']['total_redirect_to_amazon'] = $ret['stats']['total_redirect_to_amazon'] + $redirect_to_amazon;
					$ret['stats']['total_addtocart'] = $ret['stats']['total_addtocart'] + $addtocart;
				} 
			}
			
			if( count($ret['products']) > 0 ){
				// reorder the products as a top
				$ret['products'] = $this->sort_hight_to_low( $ret['products'], 'score' );
				
				// limit the return, if request
				if( (int) $limit != 0 ){
					$ret['products'] = array_slice($ret['products'], 0, $limit);
				}
			}
			 
			return $ret;
		}
		
		function sort_hight_to_low( $a, $subkey ) 
		{
		    foreach($a as $k=>$v) {
		        $b[$k] = strtolower($v[$subkey]);
		    }
		    arsort($b);
		    foreach($b as $key=>$val) {
		        $c[$key] = $a[$key];
		    }
		    return $c;
		}
		
		
	
		/**
		 * $cache_lifetime in minutes
		 */
		private function getRemote( $the_url, $cache_lifetime=60 )
		{
			// try to get from cache
			$request_alias = 'aiowaff_' . md5($the_url);
			$from_cache = get_option( $request_alias );
			
			if( $from_cache != false ){
				if( time() < ( $from_cache['when'] + ($cache_lifetime * 60) )){
					return $from_cache['data'];
				}
			}
			$response = wp_remote_get( $the_url, array('user-agent' => "Mozilla/5.0 (Windows NT 6.2; WOW64; rv:24.0) Gecko/20100101 Firefox/24.0", 'timeout' => 10) ); 
			
			// If there's error
            if ( is_wp_error( $response ) ){
            	return array(
					'status' => 'invalid'
				);
            }
        	$body = wp_remote_retrieve_body( $response );
			
			$response_data = json_decode( $body, true );
			
			// overwrite the cache data 
			update_option( $request_alias, array(
				'when' => time(),
				'data' => $response_data
			) );
				
	        return $response_data;
		}
    }
}