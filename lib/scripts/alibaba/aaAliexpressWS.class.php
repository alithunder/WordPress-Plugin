<?php
/**
 * Alibaba Webservice Client Class
 * =========================
 *
 * @package			aaAlibabaWS
 * @author			Ali
 */
 
// http://portals.aliexpress.com/help/help_center_API.html
if ( !class_exists('aaAlibabaWS') ) {
	class aaAlibabaWS {
		
        /*
         * Some required plugin information
         */
	    const VERSION = '1.0';
		
        /*
         * Store some helpers config
         */
		static protected $_instance;
		
		const RETURN_TYPE_ARRAY	= 1;
		const RETURN_TYPE_OBJECT = 2;
	
		private $protocol = 'JSON';
	
		/**
		 * Base configuration storage
		 *
		 * @var array
		 */
		private $requestConfig = array(
			'Webservice'			=> array(),
			'Mandatory'				=> array(),
			'optionalParameters'	=> array(),
		);
		
		/**
		 * Response configuration storage
		 *
		 * @var array
		 */
		private $responseConfig = array(
			'returnType'			=> self::RETURN_TYPE_ARRAY,
		);
		
		/**
		 * The Webservice Endpoint
		 *
		 * @var string
		 */
		protected $webserviceEndpoint = 'http://gw.api.alibaba.com/openapi/%%Protocol%%/1/portals.open/%%ApiMethod%%/%%AppKey%%?trackingId=%%TrackingID%%';
		
    	private $the_plugin = null;
    	private $amz_settings = array();


		/**
	     * Singleton pattern
	     *
	     * @return aaAlibabaWS Singleton instance
	     */
	    static public function getInstance()
	    {
	        if (!self::$_instance) {
	            self::$_instance = new self;
	        }
  
	        return self::$_instance;
	    }
	
		/**
		 * @param string $AppKey
		 * @param string $TrackingID
		 * @param string $DigitalSignature
		 */
		public function __construct($AppKey, $TrackingID, $DigitalSignature='' )
		{
			if (!session_id()) {
			  @session_start();
			}
	
			// private setter helper
			$this->setProtocol();
	
			if (empty($AppKey) || empty($TrackingID))
			{
				throw new Exception('No AppKey or TrackingID has been set');
			}
	
			$this->requestConfig['Webservice']['AppKey']				= $AppKey;
			$this->requestConfig['Webservice']['TrackingID']			= $TrackingID;
			$this->requestConfig['Webservice']['DigitalSignature']		= $DigitalSignature;
		}
		
	    public function set_the_plugin( $the_plugin=array() ) {
	        $this->the_plugin = $the_plugin;
	        
	        if ( !empty($this->the_plugin) && !empty($this->the_plugin->amz_settings) ) {
	            $this->amz_settings = $this->the_plugin->amz_settings;
	        } else {
	            $this->amz_settings = @unserialize( get_option( $this->the_plugin->alias . '_amazon' ) );
	        }
	        
	        // private setter helper
	        $this->setProtocol();
	    }

		private function setProtocol()
		{ 
			/*$db_protocol_setting = @unserialize( get_option("wwcAliAff_settings", true) );
			$db_protocol_setting = isset( $db_protocol_setting['protocol'] ) ? $db_protocol_setting['protocol'] : 'auto';
	
			if( $db_protocol_setting == 'auto' ){
				$this->protocol = 'JSON';
			}
			if( $db_protocol_setting == 'xml' ){
				$this->protocol = 'XML';
			}
			if( $db_protocol_setting == 'json' ){
				$this->protocol = 'JSON';
			}*/
			$this->protocol = 'param2';
			
			$this->requestConfig['Webservice']['Protocol'] = strtolower( $this->protocol );
		}
	
		/**
		 * Builds the request parameters
		 *
		 * @param string $function
		 * @param array	$params
		 *
		 * @return array
		 */
		protected function buildRequestParams($function, array $params)
		{
			$this->requestConfig['Webservice']['ApiMethod'] = $function;
			
			return array_merge(
				array(),
				$params,
				$this->requestConfig['optionalParameters']
			);
		}
		
		/**
		 * @param string $function Name of the function which should be called
		 * @param array $params Request parameters 'ParameterName' => 'ParameterValue'
		 *
		 * @return array The response as an array with stdClass objects
		 */
		protected function performJSONRequest($function, $params)
		{
            $url = $this->webserviceEndpoint;

            foreach ($this->requestConfig['Webservice'] as $key => $val) {
                $url = str_replace( "%%$key%%", $val, $url );
            }
 
            $_params = array();
            foreach ($params as $key => $val) {
                $_params[] = $key . '=' . rawurlencode($val);
            }
            if ( !empty($_params) ) {
                $url .= '&' . implode('&', $_params);
            }

            //var_dump('<pre>', $url, str_replace("&", "\n", $url), '</pre>');
            //echo __FILE__ . ":" . __LINE__;die . PHP_EOL;
            $ret = wp_remote_get( $url );
            //var_dump('<pre>', $url, $ret, '</pre>'); die('debug...'); 
            //echo __FILE__ . ":" . __LINE__;die . PHP_EOL;   
            if ( is_wp_error( $ret ) ) { // If there's error
                $err = htmlspecialchars( implode(';', $ret->get_error_messages()) );
                return array(
                    'error_code'            => -1,
                    'error_message'         => 'no response from alibaba Api!',
                    'exception'             => 'no response from alibaba Api!',
                );
            }
            $content = wp_remote_retrieve_body( $ret );
            
            return json_decode( $content, true );
		}

		protected function performTheRequest($function, $params)
		{
			return $this->returnData(
				$this->performJSONRequest($function, $params)
			);
		}
		
		public function getResponseStatus( $resp ) {
			$ret = array(
				'status' 	=> 'invalid',
				'code' 		=> -1,
				'msg' 		=> 'no status code received!'
			);
			
			$statuses = array(
				// for api.listPromotionProduct
				'20030000'		=> 'Required parameters',
				'20030010'		=> 'Keyword input parameter error',
				'20030020'		=> 'Category ID input parameter error or formatting errors',
				'20030030'		=> 'commission rate input parameter error or formatting errors',
				'20030040'		=> 'Unit input parameter error or formatting errors',
				'20030050'		=> '30 days promotion amount input parameter error or formatting errors',
				'20030060'		=> 'tracking ID input parameter error or limited length',
				'20030070'		=> 'unauthorized transfer request',
				'20020000'		=> 'System Error',
				'20010000'		=> 'call succeeds', // Success
				
				// for api.getPromotionProductDetail
				'20130000'		=> 'input parameter Product ID is error',
				'20130010'		=> 'Tracking ID is error or invalid',
				'20130020'		=> 'Unauthorized Request',
				'20120000'		=> 'System error',
				'20110000'		=> 'Success', // Success
			);

			if ( isset($resp['error_code']) ) {
				$ret = array_merge($ret, array(
					'code' 		=> $resp['error_code'],
					'msg' 		=> $resp['error_message'],
				));
				return $ret;
			}
			
			if ( isset($resp['errorCode']) ) {
				$ret = array_merge($ret, array(
					'status' 	=> in_array($resp['errorCode'], array('20010000', '20110000')) ? 'valid' : 'invalid',
					'code' 		=> $resp['errorCode'],
					'msg' 		=> isset($statuses["{$resp['errorCode']}"]) ? $statuses["{$resp['errorCode']}"] : 'unknown status code!',
				));
				return $ret;
			}
			return $ret;
		}
	
		/**
		 * Returns the response either as Array or Array/Object
		 *
		 * @param object $object
		 *
		 * @return mixed
		 */
		protected function returnData($object)
		{
			switch ($this->responseConfig['returnType'])
			{
				case self::RETURN_TYPE_OBJECT:
					return $object;
				break;
	
				case self::RETURN_TYPE_ARRAY:
					return $this->objectToArray($object);
				break;
	
				default:
					throw new InvalidArgumentException(sprintf(
						"Unknwon return type %s", $this->responseConfig['returnType']
					));
				break;
			}
		}
	
		/**
		 * Transforms the response object to an array
		 *
		 * @param object $object
		 *
		 * @return array An array representation of the given object
		 */
		protected function objectToArray($object)
		{
			$out = array();
			foreach ($object as $key => $value)
			{
				switch (true)
				{
					case is_object($value):
						$out[$key] = $this->objectToArray($value);
					break;
	
					case is_array($value):
						$out[$key] = $this->objectToArray($value);
					break;
	
					default:
						$out[$key] = $value;
					break;
				}
			}
	
			return $out;
		}
	
		/**
		 * Setting/Getting the category
		 *
		 * @param string $category
		 *
		 * @return string|aaAlibabaWS depends on category argument
		 */
		public function category($category = null)
		{
			if (null === $category)
			{
				return isset($this->requestConfig['Mandatory']['categoryId']) ? $this->requestConfig['Mandatory']['categoryId'] : null;
			}
	
			$this->requestConfig['Mandatory']['categoryId'] = $category;
	
			return $this;
		}
		
		/**
		 * Setting the result page to a specified value.
		 * Allows to browse result sets which have more than one page.
		 *
		 * @param integer $page
		 *
		 * @return aaAlibabaWS
		 */
		public function page($page)
		{
			if (false === is_numeric($page) || $page <= 0)
			{
				throw new InvalidArgumentException(sprintf(
					'%s is an invalid page value. It has to be numeric and positive',
					$page
				));
			}
	
			$this->requestConfig['Mandatory']['pageNo'] = $page;
	
			return $this;
		}
		
		/**
		 * Setting/Getting the optional parameters
		 *
		 * if the argument params is null it will reutrn the current parameters,
		 * otherwise it will set the params and return itself.
		 *
		 * @param array $params the optional parameters
		 *
		 * @return array|aaAlibabaWS depends on params argument
		 */
		public function optionalParameters($params = null)
		{
			if (null === $params)
			{
				return $this->requestConfig['optionalParameters'];
			}
	
			if (false === is_array($params))
			{
				throw new InvalidArgumentException(sprintf(
					"%s is no valid parameter: Use an array with Key => Value Pairs", $params
				));
			}
	
			$this->requestConfig['optionalParameters'] = $params;
	
			return $this;
		}
	
		/**
		 * Setting/Getting the return type
		 * It can be an object or an array
		 *
		 * @param integer $type Use the constants RETURN_TYPE_ARRAY or RETURN_TYPE_OBJECT
		 *
		 * @return integer|aaAlibabaWS depends on type argument
		 */
		public function returnType($type = null)
		{
			if (null === $type)
			{
				return $this->responseConfig['returnType'];
			}
	
			$this->responseConfig['returnType'] = $type;
	
			return $this;
		}
	
		/**
		 * Api Methods
		 */
		
		/**
		 * execute search
		 *
		 * @param string $pattern
		 *
		 * @return array|object return type depends on setting
		 *
		 * @see returnType()
		 */
		public function search($keywords, $nodeId = null)
		{
			if (false === isset($this->requestConfig['Mandatory']['categoryId']))
			{
				throw new Exception('No categoryId given: Please set it up before');
			}
			
			if (false === isset($this->requestConfig['Mandatory']['pageNo']))
			{
				throw new Exception('No pageNo given: Please set it up before');
			}
			
			$this->requestConfig['Mandatory']['keywords'] = $keywords;
	
			$params = $this->buildRequestParams('api.listPromotionProduct', array(
				'keywords' 		=> $this->requestConfig['Mandatory']['keywords'],
				'categoryId' 	=> $this->requestConfig['Mandatory']['categoryId'],
				'pageNo' 		=> $this->requestConfig['Mandatory']['pageNo'],
			));
  
			return $this->performTheRequest("api.listPromotionProduct", $params);
		}
		
		/**
		 * execute get_product_details
		 *
		 * @param string $pattern
		 *
		 * @return array|object return type depends on setting
		 *
		 * @see returnType()
		 */
		public function get_product_details($productId)
		{
			$this->requestConfig['Mandatory']['productId'] = $productId;

			$params = $this->buildRequestParams('api.getPromotionProductDetail', array(
				'productId' 	=> $this->requestConfig['Mandatory']['productId'],
			));
			
			return $this->performTheRequest("api.getPromotionProductDetail", $params);
		}
	}
} // end class exists!

// Initialize the aaAlibabaWS class
// $aaAlibabaWS = aaAlibabaWS::getInstance();