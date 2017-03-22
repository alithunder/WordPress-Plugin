<?php
/**
 * Amazon Webservices Client Class
 * http://www.ebay.com
 * =========================
 *
 * @package			aaEbayWS
 * @author			 Ali
 */

if ( !class_exists('aaEbayWS') ) {
class aaEbayWS
{
	const RETURN_TYPE_ARRAY	= 1;
	const RETURN_TYPE_OBJECT = 2;
	
	private $protocol = 'XML';

	/**
	 * Baseconfigurationstorage
	 *
	 * @var array
	 */
	private $requestConfig = array();

	/**
	 * Responseconfigurationstorage
	 *
	 * @var array
	 */
	private $responseConfig = array(
		'returnType'			=> self::RETURN_TYPE_ARRAY,
		'optionalParameters'	=> array()
	);
	
	/**
	 * All possible locations
	 *
	 * @var array
	 */
	private $possibleLocations = array();
  
	/**
	 * The WSDL File
	 *
	 * @var string
	 */
	protected $endpoint = array(
		'finding'		=> 'http://svcs.ebay.com/services/search/FindingService/v1',
		'shopping'		=> 'http://open.api.ebay.com/shopping',
	);
	
    private $the_plugin = null;
    private $amz_settings = array();
	
	public $config = array(
		'product_desc_type'		=> 'text' // text | html
	);


	/**
	 * @param string $DEVID
	 * @param string $AppID
	 * @param string $CertID
	 */
	public function __construct($DEVID, $AppID, $CertID, $country='', $associateTag='')
	{
		if (!session_id()) {
		  @session_start();
		}

		// private setter helper
		//$this->setProtocol();

		if (empty($DEVID) || empty($AppID) || empty($CertID))
		{
			throw new Exception('No DEVID or AppID or CertID has been set');
		}
		
		// update posible locations
		$this->possibleLocations = array_keys( $this->get_locations() );

		$this->requestConfig['DEVID']	= trim($DEVID);
		$this->requestConfig['AppID']	= trim($AppID);
		$this->requestConfig['CertID']	= trim($CertID);
		
		$this->associateTag( $associateTag );
 
		$this->country( $country );
		//$this->responseConfig['country'] = !empty($country) ? $country : 'EBAY-US';
	}
	
    public function set_the_plugin( $the_plugin=array() ) {
        $this->the_plugin = $the_plugin;
   
        if ( !empty($this->the_plugin) && !empty($this->the_plugin->amz_settings) ) {
            $this->amz_settings = $this->the_plugin->amz_settings;
        } else {
            $this->amz_settings = @unserialize( get_option( $this->the_plugin->alias . '_amazon' ) );
        }

		// product description type		
		$product_desc_type = isset($this->amz_settings['ebay_product_desc_type']) ? $this->amz_settings['ebay_product_desc_type'] : '';
		$product_desc_type = !empty($product_desc_type) && in_array($product_desc_type, array('text', 'html')) ? $product_desc_type : '';
		if ( !empty($product_desc_type) ) {
			$this->config['product_desc_type'] = $product_desc_type;
		}
        
        // private setter helper
        //$this->setProtocol();
    }
	
	private function setProtocol()
	{ 
		$db_protocol_setting = isset($this->amz_settings['ebay_protocol']) ? $this->amz_settings['ebay_protocol'] : 'xml';
		
		$this->protocol = 'XML';
		//if ( extension_loaded('soap') && in_array($db_protocol_setting, array('soap', 'auto')) ) {
		//	$this->protocol = 'SOAP';
		//}

	}

	/**
	 * http://developer.ebay.com/DevZone/finding/Concepts/MakingACall.html#StandardURLParameters
	 * Builds the request parameters
	 *
	 * @param string $function
	 * @param array	$params
	 *
	 * @return array
	 */
	protected function buildRequestParams($function, array $params)
	{
		$appid = $this->requestConfig['AppID'];

		// findItemsAdvanced
		if ( 'findItemsAdvanced' == $function ) {

			$country = strtoupper($this->responseConfig['country']);
	
			return array_merge(
				array(
					'Request' => array_merge(
						array('OPERATION-NAME' => $function),
						array('SECURITY-APPNAME' => $appid),
						array('country' => $country),
						$params
						//,$this->responseConfig['optionalParameters']
					),	
					'headers' => array (
						'X-EBAY-SOA-OPERATION-NAME: ' . $function,
						'X-EBAY-SOA-SECURITY-APPNAME: ' . $appid,
						'X-EBAY-SOA-GLOBAL-ID: ' . $country,
						'X-EBAY-SOA-SERVICE-VERSION: 1.13.0',
						'X-EBAY-SOA-REQUEST-DATA-FORMAT: XML',
						'CONTENT-TYPE: text/xml;charset=utf-8'
					),
				)
			);
		
		} // end if findItemsAdvanced
		else if ( in_array($function, array('GetSingleItem', 'GetMultipleItems', 'GetCategoryInfo')) ) {
			
			$country = $this->get_location( strtoupper($this->responseConfig['country']), 'globalid', 'siteid' );
	
			return array_merge(
				array(
					'Request' => array_merge(
						array('OPERATION-NAME' => $function),
						array('SECURITY-APPNAME' => $appid),
						array('country' => $country),
						$params
						//,$this->responseConfig['optionalParameters']
					),	
					'headers' => array (
						'X-EBAY-API-CALL-NAME: ' . $function,
						'X-EBAY-API-APP-ID: ' . $appid,
						'X-EBAY-API-SITE-ID: ' . $country,
						'X-EBAY-API-VERSION: ' . '933', //949 | 933 | 647
						'X-EBAY-API-REQUEST-ENCODING: XML',
						//'X-EBAY-API-RESPONSE-ENCODING: XML',
						'CONTENT-TYPE: text/xml;charset=utf-8'
					),
				)
			);
		
		}
	}

	/**
	 * @param string $function Name of the function which should be called
	 * @param array $params Requestparameters 'ParameterName' => 'ParameterValue'
	 *
	 * @return array The response as an array with stdClass objects
	 */
	protected function performXmlRequest($function, $params)
	{
		// Create the XML request to be POSTed
		$xmlrequest = "";
		$xmlrequest	.=	"<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";

		// findItemsAdvanced
		if ( 'findItemsAdvanced' == $function ) {

			$api_url = $this->endpoint['finding'];
	
			$xmlrequest .=	"<{$function} xmlns=\"http://www.ebay.com/marketplace/search/v1/services\">\n";
			
			$op = $this->responseConfig['optionalParameters'];
	
			// keywords
			$keywords = '';
			if ( isset($params['Request']['keywords']) && !empty($params['Request']['keywords']) ) {
				$keywords = $params['Request']['keywords'];
			}
			if ( !empty($keywords) ) {
			$xmlrequest .=		"<keywords>";
			$xmlrequest .=			$keywords;
			$xmlrequest .=		"</keywords>\n";
			}
	
			// category
			$category = 0;
			if ( isset($params['Request']['category']) && !empty($params['Request']['category'])
				&& $params['Request']['category'] != 'all' ) {
				$category = $params['Request']['category'];
			}
			if ( isset($op['BrowseNode']) && !empty($op['BrowseNode']) ) {
				$category = $op['BrowseNode'];
			}
			if ( !empty($category) ) {
			$xmlrequest .=		"<categoryId>";
			$xmlrequest .=			$category;
			$xmlrequest .=		"</categoryId>\n";
			}
  
			// itemFilter
			$itemFilterDefault = array(
				array(
					'name' 			=> 'Seller',
	    			'value' 		=> 'eforcity',
	    			'paramName' 	=> '',
	    			'paramValue'	=> '',
				),
				array(
					'name' 			=> 'MaxPrice',
	    			'value' 		=> '75',
	    			'paramName' 	=> 'Currency',
	    			'paramValue'	=> 'USD',
				),
				array(
					'name' 			=> 'MinPrice',
	    			'value' 		=> '10',
	    			'paramName' 	=> 'Currency',
	    			'paramValue'	=> 'USD',
				),
				array(
					'name' 			=> 'FreeShippingOnly',
	    			'value' 		=> 'true',
	    			'paramName' 	=> '',
	    			'paramValue'	=> '',
				),
				array(
					'name' 			=> 'ListingType',
	    			'value' 		=> array('AuctionWithBIN','FixedPrice'),
	    			'paramName' 	=> '',
	    			'paramValue'	=> '',
				),
			);
			unset($itemFilterDefault[0], $itemFilterDefault[4]);
			$itemFilterDefault = array();	
 
			//$itemFilter = $itemFilterDefault;
			$itemFilter = array_diff_key($op, array('sortOrder' => 1, 'BrowseNode' => 1));
			
			$itemFilter = $this->__build_itemFilter( $itemFilter );
			if ( !empty($itemFilter) ) {
			$xmlrequest .=		$itemFilter."\n";
			}
	
			// sortOrder
			if ( isset($op['sortOrder']) && !empty($op['sortOrder']) ) {
			$xmlrequest .=		"<sortOrder>";
			$xmlrequest .=			$op['sortOrder'];
			$xmlrequest .=		"</sortOrder>\n";
			}
	
			// pagination
			$this->responseConfig['page'] = isset($this->responseConfig['page']) ? $this->responseConfig['page'] : 1;
			$this->responseConfig['perPage'] = isset($this->responseConfig['perPage']) ? $this->responseConfig['perPage'] : 20;
			$xmlrequest .=		"<paginationInput>\n";
			$xmlrequest .=			"<entriesPerPage>" . ( $this->responseConfig['perPage'] ) . "</entriesPerPage>\n";
			$xmlrequest .=			"<pageNumber>" . ( $this->responseConfig['page'] ) . "</pageNumber>\n";
			$xmlrequest .=		"</paginationInput>\n";
			
			// outputSelector
			$xmlrequest .=		"<outputSelector>PictureURLLarge</outputSelector>\n";
			$xmlrequest .=		"<outputSelector>PictureURLSuperSize</outputSelector>\n";
		
		} // end if findItemsAdvanced
		else if ( in_array($function, array('GetSingleItem', 'GetMultipleItems', 'GetCategoryInfo')) ) {
			
			$api_url = $this->endpoint['shopping'];	
			
			$xmlrequest .=	"<{$function} xmlns=\"urn:ebay:apis:eBLBaseComponents\">\n";
	
			if ( 'GetCategoryInfo' == $function ) {

				// itemid
				if ( isset($params['Request']['CategoryID']) && !empty($params['Request']['CategoryID']) ) {
					
				$CategoryID = $params['Request']['CategoryID'];
				$CategoryID = array($CategoryID);
	
				foreach ($CategoryID as $CategoryID_) {
				$xmlrequest .=		"<CategoryID>";
				$xmlrequest .=			$CategoryID_;
				$xmlrequest .=		"</CategoryID>\n";
				}
	
				}
		
				// IncludeSelector
				$IncludeSelector = 'ChildCategories';
				$xmlrequest .=		"<IncludeSelector>" . $IncludeSelector . "</IncludeSelector>\n";

			}
			else if ( in_array($function, array('GetSingleItem', 'GetMultipleItems')) ) {

				// itemid
				if ( isset($params['Request']['itemid']) && !empty($params['Request']['itemid']) ) {
					
				$itemid = $params['Request']['itemid'];
				$itemid = explode(',', $itemid);
				$itemid = array_map('trim', $itemid);
				$itemid = array_filter($itemid);
	
				foreach ($itemid as $itemid_) {
				$xmlrequest .=		"<ItemID>";
				$xmlrequest .=			$itemid_;
				$xmlrequest .=		"</ItemID>\n";
				}
	
				}
		
				// IncludeSelector
				$IncludeSelector = 'Details,Variations,ItemSpecifics,ShippingCosts'; //GetSingleItem
				if ( 'GetMultipleItems' == $function ) {
					$IncludeSelector = 'Details,Variations,ItemSpecifics';
				}
				if ( 'text' == $this->config['product_desc_type'] ) { // text description
					$IncludeSelector .= ',TextDescription';
				} else { // html description
					$IncludeSelector .= ',Description';
				}
				$xmlrequest .=		"<IncludeSelector>" . $IncludeSelector . "</IncludeSelector>\n";

			}

		}

		$xmlrequest .=		"</{$function}>";
		//var_dump('<pre>', $api_url, $xmlrequest, $params['headers'], '</pre>'); die('debug...'); 
		
		$response = $this->__make_request( $api_url, $xmlrequest, $params['headers'] );
		return $response;
	}
	
	// Generates an XML snippet from the array of item filters
	protected function __build_itemFilter($filterarray) {
		$xmlfilter = array();
		
		$_filterarray = array();
		foreach ($filterarray as $key => $val) {
			$__newEl = array(
				'name'		=> $key,
				'value'		=> $val
			);

			if ( in_array($key, array('MinPrice', 'MaxPrice')) ) {
				$depend = array();
				if ( isset($filterarray['Currency']) ) {
					$depend = array(
						'paramName'		=> 'Currency',
						'paramValue'	=> $filterarray['Currency']
					);
				}
				$__newEl = array_merge($__newEl, $depend);
			}
			$_filterarray[] = $__newEl;
		}
  
		// Iterate through each filter in the array
		foreach ($_filterarray as $itemfilter) {
	    	$xmlfilter[] = "<itemFilter>";
			
			// Iterate through each key in the filter
			foreach ($itemfilter as $key => $value) {
				$value = !is_array($value) ? array($value) : (array) $value;
				if (is_array($value)) {
					// If value is an array, iterate through each array value
					foreach ($value as $arrayval) {
						if ($arrayval != "") {
							$xmlfilter[] = "<$key>$arrayval</$key>";
						}
					}
				}
			}
			$xmlfilter[] = "</itemFilter>";
		}
		return implode("\n", $xmlfilter);
	}
	
	protected function __make_request( $url, $postfields, $headers ) {
		
		$how = 'curl';
		
		// curl GOOD
		if (1) {
            
            $input_params = array(
                'post'                          => true,
                'postfields'                    => $postfields,
                'httpheader'					=> $headers,
                'header'                        => true,
                'useragent'                     => 'Woo All In One Wordpress Plugin',
                'timeout'                       => 45,
            );
            $output_params = array(
                'parse_headers'                 => true,
                'resp_is_json'                  => false,
                'resp_add_http_code'            => true,
            );
            //$ret = file_get_contents( $amzLink );

            $ret = $this->the_plugin->curl( $url, $input_params, $output_params, true );
            $ret_  = $ret['status'];
            $ret = is_array($ret) && isset($ret['data']) ? $ret['data'] : '';
        }
		// curl initial code!
		else if ( '__curl' == $how ) {
			// open connection
			$curl = curl_init($this->endpoint['finding']);				// create a curl session
			curl_setopt($curl, CURLOPT_POST, true);							// POST request type
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);				// set headers using $headers array
			curl_setopt($curl, CURLOPT_POSTFIELDS, $postfields); 			// set the body of the POST
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);				// return values as a string, not to std out
	
			// execute request
			$data = curl_exec($curl);
			
			//return $this->returnData( simplexml_load_string($responsexml) );
	
			if ( $data === false || curl_errno($curl) ) { // error occurred
                $ret = array_merge($ret, array(
                    'status'    => 'invalid',
                    'data' 		=> curl_errno($curl) . ' : ' . curl_error($curl)
                ));
			} else { // success
                $ret = array_merge($ret, array(
                    'status'    => 'valid',
                    'data'      => $data
                ));
			}
			// close connection
			curl_close($curl);
		}
		// wp_remote_request
		else if ( 'wp_remote_request' == $how && function_exists('wp_remote_request') ) {
        	// wp_remote_request DOESN'T WORK!
            $ret = wp_remote_request( $url, array(
				'method' 	  => 'POST',
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'user-agent'  => 'Woo All In One Wordpress Plugin',
				//'blocking'    => true,
				'headers'     => $headers,
				//'cookies'     => array(),
				'body'        => array($postfields),
				//'compress'    => false,
				//'decompress'  => true,
				//'sslverify'   => true,
				//'stream'      => false,
				//'filename'    => null,
            ));
            $ret_ = 'valid';
            if ( is_wp_error($ret) ) {
                $ret_ = 'invalid';
                $ret = array('body' => $ret->get_error_message());
            }
            $ret = is_array($ret) && isset($ret['body']) ? $ret['body'] : '';
        }
		//var_dump('<pre>', $ret_, $ret, '</pre>'); die('debug...'); 
 
        if ($ret_ == 'invalid') {
            return json_decode(json_encode(array('status' => $ret_, 'data' => $ret)),1);
        }
		return json_decode(json_encode((array)simplexml_load_string($ret)),1);
	}

	/**
	 * @param string $function Name of the function which should be called
	 * @param array $params Requestparameters 'ParameterName' => 'ParameterValue'
	 *
	 * @return array The response as an array with stdClass objects
	 */
	protected function performXmlRequestItem__($function, $params)
	{
		$IncludeSelector = 'Details,Variations,ItemSpecifics,Description,ShippingCosts';

		// Create the XML request to be POSTed
		$link	= $this->endpoint['shopping'] . "?callname=" .
			( $params['Request']['OPERATION-NAME'] ) . 
		"&responseencoding=XML&appid=" .
			( $params['Request']['SECURITY-APPNAME'] ) . 
		"&siteid=" . 
			( $params['Request']['country'] ) .
		"&ItemID=" . 
			( $params['Request']['itemid'] ) 
		. "&IncludeSelector=" . 
			( $IncludeSelector ) 
		. "&version=949";
		//var_dump('<pre>', $link, '</pre>'); die('debug...'); 

		return $this->returnData( simplexml_load_string( file_get_contents($link) ) );
	}


	/**
	 * execute search
	 *
	 * @param string $pattern
	 *
	 * @return array|object return type depends on setting
	 *
	 * @see returnType()
	 */
	public function search($pattern='', $nodeId = null)
	{
		if (false === isset($this->requestConfig['category']) && true === empty($pattern))
		{
			throw new Exception('You need to specify keywords and/or a categoryId in the request');
		}

		$params = array();
		if (!empty($pattern)) {
			$params['keywords'] = urlencode ( utf8_encode($pattern) );
		}
		if (isset($this->requestConfig['category'])) {
			$params['category'] = $this->requestConfig['category'];
		}
		$params = $this->buildRequestParams('findItemsAdvanced', $params);
		
		return $this->returnData(
			$this->performXmlRequest("findItemsAdvanced", $params)
		);
	}

	/**
	* execute ItemLookup request
	*
	* @param string $itemid
	* @param string $method: GetSingleItem | GetMultipleItems
	*
	* @return array|object return type depends on setting
	*
	* @see returnType()
	*/
	public function lookup($itemid, $method='GetMultipleItems')
	{
		if ( !is_array($itemid)) {
			$itemid = explode(',', $itemid);
		}
		if ( is_array($itemid)) {
			$itemid = array_map('trim', $itemid);
			$itemid = array_filter($itemid);
			foreach ($itemid as $key => $val) {
				$itemid["$key"] = urlencode ( utf8_encode($val) );
			}
			$itemid = implode(',', $itemid);
		}

		$params = $this->buildRequestParams($method, array_merge(
			array(
				'itemid' 		=> $itemid,
			)
		));
		
		return $this->returnData(
			$this->performXmlRequest($method, $params)
		);
	}

	/**
	 * Implementation of BrowseNodeLookup
	 * This allows to fetch information about nodes (children anchestors, etc.)
	 *
	 * @param integer $nodeId
	 */
	public function browseNodeLookup($nodeId)
	{
		$this->validateNodeId($nodeId);

		$params = $this->buildRequestParams('GetCategoryInfo', array_merge(
			array(
				'CategoryID' 		=> $nodeId,
			)
		));

		return $this->returnData(
			$this->performXmlRequest('GetCategoryInfo', $params)
		);
	}
		

	/**
	 * Returns the response either as Array or Array/Object
	 *
	 * @param object $object
	 *
	 * @return mixed
	 */
	protected function returnData($responsexml)
	{
		switch ($this->responseConfig['returnType'])
		{
			case self::RETURN_TYPE_OBJECT:
				return $object;
			break;

			case self::RETURN_TYPE_ARRAY:
				return $this->objectToArray($responsexml);
			break;

			default:
				throw new InvalidArgumentException(sprintf(
					"Unknwon return type %s", $this->responseConfig['returnType']
				));
			break;
		}
	}

	/**
	 * Transforms the responseobject to an array
	 *
	 * @param object $object
	 *
	 * @return array An arrayrepresentation of the given object
	 */
	protected function objectToArray($object) {
		// smart php conversion object to array
		return json_decode(json_encode( (array) $object ), 1);
		
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
	 * set or get optional parameters
	 *
	 * if the argument params is null it will reutrn the current parameters,
	 * otherwise it will set the params and return itself.
	 *
	 * @param array $params the optional parameters
	 *
	 * @return array|aaEbayWS depends on params argument
	 */
	public function optionalParameters($params = null)
	{
		if (null === $params)
		{
			return $this->responseConfig['optionalParameters'];
		}

		if (false === is_array($params))
		{
			throw new InvalidArgumentException(sprintf(
				"%s is no valid parameter: Use an array with Key => Value Pairs", $params
			));
		}

		$this->responseConfig['optionalParameters'] = $params;

		return $this;
	}

	/**
	 * Setting/Getting the returntype
	 * It can be an object or an array
	 *
	 * @param integer $type Use the constants RETURN_TYPE_ARRAY or RETURN_TYPE_OBJECT
	 *
	 * @return integer|aaEbayWS depends on type argument
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
	 * @deprecated use returnType() instead
	 */
	public function setReturnType($type)
	{
		return $this->returnType($type);
	}

	/**
	 * Setter/Getter of the AssociateTag.
	 * This could be used for late bindings of this attribute
	 *
	 * @param string $associateTag
	 *
	 * @return string|aaAmazonWS depends on associateTag argument
	 */
	public function associateTag($associateTag = null)
	{
		if (null === $associateTag)
		{
			return $this->requestConfig['associateTag'];
		}

		$this->requestConfig['associateTag'] = $associateTag;

		return $this;
	}
	
	/**
	 * Basic validation of the nodeId
	 *
	 * @param integer $nodeId
	 *
	 * @return boolean
	 */
	final protected function validateNodeId($nodeId)
	{
		if (false === is_numeric($nodeId) || $nodeId < -1)
		//if (false === is_numeric($nodeId))
		{
			throw new InvalidArgumentException(sprintf('Node has to be a positive Integer.'));
		}

		return true;
	}

	/**
	 * Setting/Getting the amazon category
	 *
	 * @param string $category
	 *
	 * @return string|aaEbayWS depends on category argument
	 */
	public function category($category = null)
	{
		if (null === $category)
		{
			return isset($this->requestConfig['category']) ? $this->requestConfig['category'] : null;
		}
		
		//$this->validateNodeId($category);

		$this->requestConfig['category'] = $category;
		return $this;
	}

	/**
	* Set or get the country
	*
	* if the country argument is null it will return the current
	* country, otherwise it will set the country and return itself.
	*
	* @param string|null $country
	*
	* @return string|aaAmazonWS depends on country argument
	*/
	public function country($country = null)
	{
		if (null === $country)
		{
			return $this->responseConfig['country'];
		}

		if (false === in_array(strtoupper($country), $this->possibleLocations))
		{
			throw new InvalidArgumentException(sprintf(
			"Invalid Country-Code: %s! Possible Country-Codes: %s",
			$country,
			implode(', ', $this->possibleLocations)
			));
		}

		$this->responseConfig['country'] = strtolower($country);
		return $this;
	}

	/**
	 * Setting the resultpage to a specified value.
	 * Allows to browse resultsets which have more than one page.
	 *
	 * @param integer $page
	 *
	 * @return aaEbayWS
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

		//$this->responseConfig['optionalParameters'] = array_merge(
		//	$this->responseConfig['optionalParameters'],
		//	array("page" => $page)
		//);
		$this->responseConfig['page'] = $page;
		return $this;
	}
	
	/**
	 * Setting the resultpage to a specified value.
	 * Allows to browse resultsets which have more than one page.
	 *
	 * @param integer $page
	 *
	 * @return aaEbayWS
	 */
	public function perPage($page)
	{
		if (false === is_numeric($page) || $page <= 0)
		{
			throw new InvalidArgumentException(sprintf(
				'%s is an invalid perPage value. It has to be numeric and positive',
				$page
			));
		}

		//$this->responseConfig['optionalParameters'] = array_merge(
		//	$this->responseConfig['optionalParameters'],
		//	array("perPage" => $page)
		//);
		$this->responseConfig['perPage'] = $page;
		return $this;
	}
	
	public function getResponseStatus( $resp ) {
		$ret = array(
			'status' 	=> 'invalid',
			'code' 		=> -1,
			'msg' 		=> 'no status code received!'
		);
   
		if ( empty($resp) || !is_array($resp) ) {
			return $ret;
		}
		
		//Success, Warning
		$resp_ = array_change_key_case($resp, CASE_LOWER); //CASE_LOWER: implicit | CASE_UPPER
		if ( isset($resp_['ack']) && preg_match('/success|warning/iu', $resp_['ack']) ) {
			$ret = array_merge($ret, array(
				'status' 	=> 'valid',
				'code' 		=> 0,
				'msg' 		=> 'valid response!',
			));
			return $ret;
		}

		// finding api
		if ( isset($resp['errorMessage'], $resp['errorMessage']['error']) ) {
			$err = $resp['errorMessage']['error'];
			$ret = array_merge($ret, array(
				'code' 		=> isset($err['errorId']) ? $err['errorId'] : -1,
				'msg' 		=> isset($err['message']) ? $err['message'] : 'unknown status code!',
			));
			return $ret;
		}

		// shopping api
		if ( isset($resp['Errors']) ) {
			$err = $resp['Errors'];
			$ret = array_merge($ret, array(
				'code' 		=> isset($err['ErrorCode']) ? $err['ErrorCode'] : -1,
				'msg' 		=> isset($err['LongMessage']) ? $err['LongMessage'] : 'unknown status code!',
			));
			return $ret;
		}
			
		// unknown catched error!
		if ( 1 ) {
			$ret = array_merge($ret, array(
				'code' 		=> -1,
				'msg' 		=> 'unknown catched error!',
			));
			return $ret;
		}
		return $ret;
	}
	
	/**
	 * Utils
	 */
	/**
	 * pluralS
	 *
	 * @param int $intIn
	 *
	 * @return string
	 */
	public function pluralS($intIn) {
		// if $intIn > 1 return an 's', else return null string
		if ($intIn > 1) {
			return 's';
		} else {
			return '';
		}
	}
	
	/**
	 * pretty time from ebay time
	 *
	 * @param string $eBayTimeString
	 *
	 * @return string
	 */
	public function getPrettyTimeFromEbayTime($eBayTimeString){
		// Input is of form 'PT12M25S'
		$matchAry = array(); // initialize array which will be filled in preg_match
		$pattern = "#P([0-9]{0,3}D)?T([0-9]?[0-9]H)?([0-9]?[0-9]M)?([0-9]?[0-9]S)#msiU";
		preg_match($pattern, $eBayTimeString, $matchAry);

		$days  = (int) $matchAry[1];
		$hours = (int) $matchAry[2];
		$min   = (int) $matchAry[3];    // $matchAry[3] is of form 55M - cast to int
		$sec   = (int) $matchAry[4];

		$retnStr = '';
		if ($days)  { $retnStr .= "$days day"    . $this->pluralS($days);  }
		if ($hours) { $retnStr .= " $hours hour" . $this->pluralS($hours); }
		if ($min)   { $retnStr .= " $min minute" . $this->pluralS($min);   }
		if ($sec)   { $retnStr .= " $sec second" . $this->pluralS($sec);   }

		return $retnStr;
	}
	public function getPrettyTimeFromEbayTime__($eBayTimeString){
	
		$now = time();
		$future_date = strtotime($eBayTimeString);
		
		$secLefts = $future_date - $now;
		if($secLefts > 0) {
			$odjData = $this->secondsToTime($secLefts);
			return $odjData['d'] . " days, " . ( $odjData['h'] ) . " hours, " . ( $odjData['m'] ) . " minutes, " . ( $odjData['s'] ) . " seconds";
		}else{
			return 'End.';
		}
		
	} // function
	
	public function secondsToTime($inputSeconds) {

		$secondsInAMinute = 60;
		$secondsInAnHour  = 60 * $secondsInAMinute;
		$secondsInADay    = 24 * $secondsInAnHour;

		// extract days
		$days = floor($inputSeconds / $secondsInADay);

		// extract hours
		$hourSeconds = $inputSeconds % $secondsInADay;
		$hours = floor($hourSeconds / $secondsInAnHour);

		// extract minutes
		$minuteSeconds = $hourSeconds % $secondsInAnHour;
		$minutes = floor($minuteSeconds / $secondsInAMinute);

		// extract the remaining seconds
		$remainingSeconds = $minuteSeconds % $secondsInAMinute;
		$seconds = ceil($remainingSeconds);

		// return the final array
		$obj = array(
			'd' => (int) $days,
			'h' => (int) $hours,
			'm' => (int) $minutes,
			's' => (int) $seconds,
		);
		return $obj;
	}
		
	
	/**
	 * Global ID => Site ID, Site Name
	 */
	public function get_locations() {
		$ret = array(
			// global ID 			=> array(site ID, site name)
			'EBAY-AT' 				 => array('16', 'eBay Austria'),
			'EBAY-AU' 				 => array('15', 'eBay Australia'),
			'EBAY-CH' 				 => array('193', 'eBay Switzerland'),
			'EBAY-DE' 				 => array('77', 'eBay Germany'),
			'EBAY-ENCA' 			 => array('2', 'eBay Canada (English)'), //CA
			'EBAY-ES' 				 => array('186', 'eBay Spain'),
			'EBAY-FR' 				 => array('71', 'eBay France'),
			'EBAY-FRBE' 			 => array('23', 'eBay Belgium (French)'), //BEFR
			'EBAY-FRCA' 			 => array('210', 'eBay Canada (French)'), //CAFR
			'EBAY-GB' 				 => array('3', 'eBay UK'), //UK
			'EBAY-HK' 				 => array('201', 'eBay Hong Kong'),
			'EBAY-IE' 				 => array('205', 'eBay Ireland'),
			'EBAY-IN' 				 => array('203', 'eBay India'),
			'EBAY-IT' 				 => array('101', 'eBay Italy'),
			'EBAY-MOTOR' 			 => array('100', 'eBay Motors'),
			'EBAY-MY' 				 => array('207', 'eBay Malaysia'),
			'EBAY-NL' 				 => array('146', 'eBay Netherlands'),
			'EBAY-NLBE' 			 => array('123', 'eBay Belgium (Dutch)'), //BENL
			'EBAY-PH' 				 => array('211', 'eBay Philippines'),
			'EBAY-PL' 				 => array('212', 'eBay Poland'),
			'EBAY-SG' 				 => array('216', 'eBay Singapore'),
			'EBAY-US' 				 => array('0', 'eBay United States'),
			
			'EBAY-CN' 				 => array('223', 'eBay China'),
			'EBAY-RU' 				 => array('215', 'eBay Russia'),
			'EBAY-SE' 				 => array('218', 'eBay Sweden'),
			'EBAY-TW' 				 => array('196', 'eBay Taiwan'),
		);
		return $ret;
	}

	// filterby = globalid | siteid
	public function get_countries( $return_key='globalid' ) {
		$loc = $this->get_locations();
			
		$ret = array();
		foreach ( $loc as $key => $val) {
			$_key = $return_key == 'globalid' ? $key : $val[0];
			$ret["$_key"] = $val[1];
		}
		return $ret;
	}

	// filter_by: globalid | siteid ; return_fields = all | globalid | siteid | sitename
	public function get_location( $filter_value, $filter_by='globalid', $return_field='all' ) {
		$loc = $this->get_locations();
			
		if ( empty($filter_value) ) return false;
		if ( empty($return_field)
			|| !in_array($return_field, array('all', 'globalid', 'siteid', 'sitename')) ) {
		 	$return_field = 'all';
		 }

		// by global ID
		$row = array();
		if ( 'globalid' == $filter_by ) {
			$row = isset($loc["$filter_value"]) ? $loc["$filter_value"] : array();
			if ( !empty($row) ) {
				$row = array($filter_value, $row[0], $row[1]);
			}
		}
		// by site ID
		else {
			foreach ( $loc as $key => $val) {
				if ( $filter_value == $val[0] ) {
					$row = array($key, $filter_value, $row[1]);
					break;
				}
			}
		}
			
		if ( in_array($return_field, array('globalid', 'siteid', 'sitename')) ) {
			$index2field = array('globalid' => 0, 'siteid' => 1, 'sitename' => 2);
			$index = isset($index2field["$return_field"]) ? $index2field["$return_field"] : -1;

			if ( $index == -1 ) return false;
			return !empty($row) && isset($row["$index"]) ? $row["$index"] : false;
		}
		// all
		return !empty($row) ? $row : false;
	}
		
	public function get_vectorid( $globalid ) {
		if ( empty($globalid) ) return false;

		$list = array(
			'AT' => '229473',
			'AU' => '229515',
			'FRBE' => '229522',
			'ENCA' => '229529',
			'CH' => '229536',
			'DE' => '229487',
			'ES' => '229501',
			'FR' => '229480',
			'IE' => '229543',
			'IN' => '229550',
			'IT' => '229494',
			'NL' => '229557',
			'GB' => '229508',
			'US' => '229466',
		);
			
		$globalid = str_replace('EBAY-', '', $globalid);
		return isset($list["$globalid"]) ? $list["$globalid"] : false;
	}
		
	public function get_rotationid( $globalid ) {
		if ( empty($globalid) ) return false;

		$list = array(
			'AT' => '5221-53469-19255-0',
			'AU' => '705-53470-19255-0',
			'FRBE' => '1553-53471-19255-0',
			'ENCA' => '706-53473-19255-0',
			'CH' => '5222-53480-19255-0',
			'DE' => '707-53477-19255-0',
			'ES' => '1185-53479-19255-0',
			'FR' => '709-53476-19255-0',
			'IE' => '5282-53468-19255-0',
			'IN' => '4686-53472-19255-0',
			'IT' => '724-53478-19255-0',
			'NL' => '1346-53482-19255-0',
			'GB' => '710-53481-19255-0',
			'US' => '711-53200-19255-0'
		);
			
		$globalid = str_replace('EBAY-', '', $globalid);
		return isset($list["$globalid"]) ? $list["$globalid"] : false;
	}

	/**
	 * pms: array(
	 * 		prod_id		: (string) ebay product id
	 * 		prod_link	: (string) ebay product page url
	 * 		globalid	: (string) main affiliate id location key (ex: EBAY-US)
	 * 		affid		: (string) affiliate id value (ex: 01234)
	 * )
	 */
	public function get_product_link( $pms=array() ) {
		extract($pms);
		
		$mpre			= rawurlencode( $prod_link );
		$campid 		= $affid;
		$lgeo 			= 0;
		$ff3 			= 2; // it's an item link 
		$vectorid 		= $this->get_vectorid( $globalid );
		$rotationid 	= $this->get_rotationid( $globalid );
		$toolid			= 10044;

		//-------------------
		// ex : http://rover.ebay.com/rover/1/711-53200-19255-0/1?ff3=2&toolid=10044&campid=123&customid=&lgeo=1&vectorid=123&item=123

		//&vectorid=' . ( $vectorid ) . '
		//return 'http://rover.ebay.com/rover/1/' . ( $rotationid ) . '/1?ff3=' . ( $ff3) . '&toolid=' . ( $toolid) . '&campid=' . ( $campid) . '&customid=APIcallSKF&lgeo=' . ( $lgeo) . '&item=' . $prod_id;

		//-------------------
		// ex dec 2015: http://rover.ebay.com/rover/1/711-53200-19255-0/1?campid=1234567890&customid=&toolid=10001&mpre=http%3A%2F%2Fwww.ebay.co.uk%2Fitm%2FHEAVY-DUTY-WATER-RESISTANT-CAR-BOOT-LINER-LIP-PROTECTOR-DIRT-PET-DOG-COVER-MAT-%2F351040656363

		return 'http://rover.ebay.com/rover/1/' . ( $rotationid ) . '/1?ff3=' . ( $ff3) . '&toolid=' . ( $toolid) . '&campid=' . ( $campid) . '&customid=APIcallSKF&lgeo=' . ( $lgeo) . '&mpre=' . $mpre;
	}
}
} // end class exists!

// Initialize the aaEbayWS class
// $aaEbayWS = aaEbayWS::getInstance();