<?php
/**
 *	Author: Ali
 *	Name: 	
 *	
**/
! defined( 'ABSPATH' ) and exit;

if(class_exists('aiowaffEbayHelper') != true) {
	class aiowaffEbayHelper extends aiowaff
	{
		private $the_plugin = null;
		public $aaEbayWS = null;
		public $amz_settings = array();
		
		static protected $_instance;
        
        const MSG_SEP = '—'; // messages html bullet // '&#8212;'; // messages html separator
        
        private static $provider = 'ebay';
		private static $variation_id = '%s/var-%s';
		
		
        /**
         * The constructor
         */
		public function __construct( $the_plugin=array() ) 
		{
			$this->the_plugin = $the_plugin; 
			
			// get all amazon settings options
            if ( !empty($this->the_plugin) && !empty($this->the_plugin->amz_settings) ) {
                $this->amz_settings = $this->the_plugin->amz_settings;
            } else {
                $this->amz_settings = @unserialize( get_option( $this->the_plugin->alias . '_amazon' ) );
            }
			$this->amz_settings['main_aff_id'] = 'com';
  
			// create a instance for amazon WS connections
			$this->setupWS();
			
			// ajax actions
			add_action('wp_ajax_aiowaffCheckKeysEbay', array( $this, 'check_keys') );
			add_action('wp_ajax_aiowaffImportProductEbay', array( $this, 'getProductDataFromAmazon' ), 10, 2);
			
			add_action('wp_ajax_aiowaffStressTestEbay', array( $this, 'stress_test' ));
			
			// TESTING
			if (0) { // search products by categoryId and/or keywords
				$aaEbayWS = $this->aaEbayWS;
				//$aaEbayWS->country('EBAY-US');
				$aaEbayWS->page(1)->perPage(10);
				$aaEbayWS->category(11450); // 11450 = Clothing, Shoes & Accessories; 11233 = Music
				$keywords = isset($_REQUEST['k']) ? $_REQUEST['k'] : '';
				//$keywords = '';
				$results = $aaEbayWS->search($keywords);
				var_dump('<pre>',$results ,'</pre>'); die;
			}
			if (0) { // lookup product id
				$aaEbayWS = $this->aaEbayWS;
				//$aaEbayWS->country('EBAY-US');
				//$results = $aaEbayWS->lookup('161853590094,381354919870,281794935811');
				$results = $aaEbayWS->lookup('351544063108');
				var_dump('<pre>',$results ,'</pre>'); die;
			}
			if (0) { // get category hierarchy	
				//63855 = "Clothing, Shoes & Accessories:Women's Clothing:Intimates & Sleep:Sleepwear & Robes" ; LeafCategory
				//11233 = root category
				$aaEbayWS = $this->aaEbayWS;
				//$aaEbayWS->country('EBAY-US');
				$results = $aaEbayWS->browseNodeLookup('63855');
				var_dump('<pre>',$results ,'</pre>'); die;
			}
		}
		
		/**
	    	* Singleton pattern
	    	*
	    	* @return pspGoogleAuthorship Singleton instance
	    	*/
		static public function getInstance( $the_plugin=array() )
		{
			if (!self::$_instance) {
				self::$_instance = new self( $the_plugin );
			}

			return self::$_instance;
		}
		
		public function stress_test()
		{
			$action = isset($_REQUEST['sub_action']) ? $_REQUEST['sub_action'] : '';
			$return = array();

			$start = microtime(true);

			//header('HTTP/1.1 500 Internal Server Error');
			//exit();
			
			if (!isset($_SESSION)) {
                session_start(); 
			}
			
			if( $action == 'import_images' ){
				
				if( isset($_SESSION["aiowaff_test_product"]) && count($_SESSION["aiowaff_test_product"]) > 0 ){
					$product = $_SESSION["aiowaff_test_product"];

					$this->set_product_images( $product, $product['local_id'], 0, 1 );
					$return = array( 
						'status' => 'valid',
						'log' => "Images added for product: " . $product['local_id'],
						'execution_time' => number_format( microtime(true) - $start, 2),
					);
				}
				
				else{
					$return = array( 
						'status' => 'invalid',
						'log' => 'Unable to create the woocommerce product!'
					);
				}
			}
			
			if( $action == 'insert_product' ){
				if( isset($_SESSION["aiowaff_test_product"]) && count($_SESSION["aiowaff_test_product"]) > 0 ){
					$product = $_SESSION["aiowaff_test_product"];
					
					$insert_id = $this->the_plugin->addNewProduct( $product, array(
						'ws'			=> self::$provider,
                        'import_images' => false,
                    ));
					if( (int) $insert_id > 0 ){
						
						$_SESSION["aiowaff_test_product"]['local_id'] = $insert_id;
						$return = array( 
							'status' => 'valid',
							'log' => "New product added: " . $insert_id,
							'execution_time' => number_format( microtime(true) - $start, 2),
						);
					}
				}
				
				else{
					$return = array( 
						'status' => 'invalid',
						'log' => 'Unable to create the woocommerce product!'
					);
				}
			}
			
			if( $action == 'get_product_data' ){
				
				$asin = isset($_REQUEST['ASIN']) ? $_REQUEST['ASIN'] : '';
				if( $asin != "" ){
					
                    //$product = $this->aaEbayWS->responseGroup('Large,ItemAttributes,Offers,Reviews')->optionalParameters(array('MerchantId' => 'All'))->lookup( $asin ); 
			        // Do a test connection
					$product = $this->aaEbayWS
						->get_item_details( $asin );
   
					//$respStatus = $this->aaEbayWS->getResponseStatus($product);
					$respStatus = $this->is_amazon_valid_response( $product );

                    if ( $respStatus['status'] != 'valid' ) { // error occured!

						$_msg[] = 'Invalid '.self::$provider.' response ( ' . $respStatus['code'] . ' - ' . $respStatus['msg'] . ' )';
                        
                        $ret = array_merge($ret, array('msg' => implode('<br />', $_msg)));
                        if ( $retType == 'return' ) { return $ret; }
                        else { die( json_encode( $ret ) ); }
                
                    } else { // success!
                        $thisProd = isset($product['body']) && !empty($product['body']) ? $product['body'] : array();
						if ( !empty($thisProd) ) {
							//$thisProd['__isfrom'] = 'details-only';
							
                            // build product data array
                            $retProd = array();
                            $retProd = $this->build_product_data( $thisProd );

							$return = array( 
								'status' => 'valid',
								'log' => $retProd,
								'execution_time' => number_format( microtime(true) - $start, 2),
							);
							
							// save the product into session, for feature using of it
							$_SESSION['aiowaff_test_product'] = $retProd;
						}

						else{
							$return = array(
								'status' => 'invalid',
								'msg'	=> 'Please provide a valid ASIN code!',
								'log'	=> $product
							);
						}
					}

				} else {
					$return = array(
						'status' => 'invalid',
						'msg'	=> 'Please provide a valid ASIN code!'
					);
				}
			}
			
			die( json_encode($return) );   
		}
		
		public function check_keys()
		{
			$return = array(
				'status' => 'invalid',
				'msg'	=> '',
				'log'	=> '',
			);

	        try {
	            // Do a test connection
	            // 99 = Everything  Else
	        	$tryRequest = $this->aaEbayWS->category(99)->page(1)->perPage(3)
	        		->search("music");
					
				$respStatus = $this->aaEbayWS->getResponseStatus($tryRequest);
				if ( $respStatus['status'] != 'valid' ) {
					$return = array_merge($return, array(
						'msg'		=> $respStatus['code'] . ' - ' . $respStatus['msg'],
						'log'		=> $tryRequest
					));
				} else {
					if ( isset($tryRequest['searchResult'], $tryRequest['searchResult']['item'])
						&& !empty($tryRequest['searchResult'])
						&& !empty($tryRequest['searchResult']['item']) ) {

						$return = array_merge($return, array(
							'status'	=> 'valid',
						));
					} else {
						$return = array_merge($return, array(
							'msg'	=> 'response has no result or searchResult/item tag!',
							'log'	=> $tryRequest
						));
					}
				}
	        } catch (Exception $e) {
	            // Check 
	            if (isset($e->faultcode)) {
					$return = array_merge($return, array(
						'msg'		=> $e->faultcode . ": " . $e->faultstring,
					));
	            }
	        }
			
        	die( json_encode($return) );
		}
		
		private function convertMainAffIdInCountry( $main_add_id='' )
		{
			if( $main_add_id == 'com' ) return 'US';
			
			return strtoupper( $main_add_id );
		}
		
		public function getAmazonCategs()
		{
			$categs = $this->the_plugin->getBrowseNodes('-1', self::$provider);
			if ( empty($categs) ) return array();
			
			$ret = array();
			foreach ($categs as $key => $val) {
				$id = $val['CategoryID'];
				$name = $val['CategoryName'];
				$ret["$name"] = $id;
			}
			return $ret;
		}

		public function getAmazonItemSearchParameters()
		{
			return array();
		}
		
		public function getAmazonSortValues()
		{
			return array();
		}
		
		private function setupWS()
		{
			// load the alibaba webservices client class
			require_once( $this->the_plugin->cfg['paths']['plugin_dir_path'] . '/lib/scripts/ebay/aaEbayWS.class.php' );

			$this->the_plugin->cur_provider = self::$provider;
			
			// create new alibaba instance
			$this->aaEbayWS = new aaEbayWS(
				$this->amz_settings['ebay_DEVID'],
				$this->amz_settings['ebay_AppID'],
				$this->amz_settings['ebay_CertID'],
				$this->amz_settings['ebay_country'],
				$this->the_plugin->main_aff_id()
			);

            $this->aaEbayWS->set_the_plugin( $this->the_plugin );
		}
		
		public function browseNodeLookup( $nodeid )
		{
			$ret = $this->aaEbayWS->browseNodeLookup( $nodeid );
            
            return $ret;
		}
		
		public function updateProductReviews( $post_id=0 )
		{
			return false;
		}
		
        /**
         * Get Product From WebService
         */
		public function getProductDataFromAmazon( $retType='die', $pms=array() ) {
			// require_once( $this->the_plugin->cfg['paths']["scripts_dir_path"] . '/shutdown-scheduler/shutdown-scheduler.php' );
			// $scheduler = new aateamShutdownScheduler();

            $this->the_plugin->timer_start(); // Start Timer

            $cross_selling = (isset($this->amz_settings["cross_selling"]) && $this->amz_settings["cross_selling"] == 'yes' ? true : false);

            $_msg = array();
			$ret = array(
                'status'                    => 'invalid',
                'msg'                       => '',
                'product_data'              => array(),
                'show_download_lightbox'    => false,
                'download_lightbox_html'    => '',
            );
            
            //$asin = isset($_REQUEST['asin']) ? htmlentities($_REQUEST['asin']) : '';
            //$category = isset($_REQUEST['category']) ? htmlentities($_REQUEST['category']) : 'All';
            
            // build method parameters
            $requestData = array(
            	'ws'					=> self::$provider,
                'asin'                  => isset($_REQUEST['asin']) ? htmlentities($_REQUEST['asin']) : '',
                'do_import_product'     => 'yes',
                'from_cache'            => array(),
                'debug_level'           => isset($_REQUEST['debug_level']) ? (int) $_REQUEST['debug_level'] : 0,

                'from_module'           => 'default',
                'import_type'           => isset($this->amz_settings['import_type'])
                    && $this->amz_settings['import_type'] == 'asynchronous' ? 'asynchronous' : 'default',

                // bellow parameters are used in framework addNewProduct method
                'operation_id'          => '',

                'import_to_category'    => isset($_REQUEST['to-category']) ? trim($_REQUEST['to-category']) : 0,

                'import_images'         => isset($this->amz_settings["number_of_images"])
                    && (int) $this->amz_settings["number_of_images"] > 0
                    ? (int) $this->amz_settings["number_of_images"] : 'all',

                'import_variations'     => isset($this->amz_settings['product_variation'])
                    ? $this->amz_settings['product_variation'] : 'yes_5',

                'spin_at_import'        => isset($this->amz_settings['spin_at_import'])
                    && ($this->amz_settings['spin_at_import'] == 'yes') ? true : false,
                    
                'import_attributes'     => isset($this->amz_settings['item_attribute'])
                    && ($this->amz_settings['item_attribute'] == 'no') ? false : true,
            );

            foreach ($requestData as $rk => $rv) {
                //empty($rv) || ( isset($pms["$rk"]) && !empty($pms["$rk"]) )
                if ( 1 ) {
                    if ( isset($pms["$rk"]) ) {
                        $new_val = $pms["$rk"];
                        $requestData["$rk"] = $new_val;
                    }
                }
            }
            $requestData['asin'] = trim( $requestData['asin'] );
            
            // Import To Category
            if ( empty($requestData['import_to_category']) || ( (int) $requestData['import_to_category'] <= 0 ) ) {
                $requestData['import_to_category'] = 'amz';
            }
 
            // NOT using category from amazon!
            if ( (int) $requestData['import_to_category'] > 0 ) {
                $__categ = get_term( $requestData['import_to_category'], 'product_cat' );
                if ( isset($__categ->term_id) && !empty($__categ->term_id) ) {
                    $requestData['import_to_category'] = $__categ->term_id;
                } else {
                    $requestData['import_to_category'] = 'amz';
                }
                //$requestData['import_to_category'] = $__categ->name ? $__categ->name : 'Untitled';

                //$__categ2 = get_term_by('name', $requestData['import_to_category'], 'product_cat');
                //$requestData['import_to_category'] = $__categ2->term_id;
            }

            extract($requestData);

            // provided ASIN in invalid
			if( empty($asin) ){
                $ret = array_merge($ret, array(
                    'msg'           => self::MSG_SEP . ' <u>Import Product ASIN</u> : is invalid (empty)!',
                ));
                if ( $retType == 'return' ) { return $ret; }
                else { die( json_encode( $ret ) ); }
			}
            
            // check if product already imported 
            $your_products = $this->the_plugin->getAllProductsMeta('array', '_amzASIN', true, 'all');
            if( isset($your_products) && count($your_products) > 0 ){
                if( in_array($asin, $your_products) ){
                    
                    $ret = array_merge($ret, array(
                        'msg'           => self::MSG_SEP . ' <u>Import Product ASIN</u> <strong>'.$asin.'</strong> : already imported!',
                    ));
                    if ( $retType == 'return' ) { return $ret; }
                    else { die( json_encode( $ret ) ); }
                }
            }

            $isValidProduct = false;
            $_msg[] = self::MSG_SEP . ' <u>Import Product ASIN</u> <strong>'.$asin.'</strong>';

            // from cache
            if ( isset($from_cache) && $this->is_valid_product_data($from_cache) ) {
                $retProd = $from_cache;
                $isValidProduct = true;
                
                $_msg[] = self::MSG_SEP . ' product data returned from Cache';

                if ( 1 ) {
                    $this->the_plugin->add_last_imports('request_cache', array(
                        'duration'      => $this->the_plugin->timer_end(),
                    )); // End Timer & Add Report
                }
            }
 
            // from amazon
            if ( !$isValidProduct ) {
                try {
    
        			// create new amazon instance
        			$aaEbayWS = $this->aaEbayWS;

			        // create request by ASIN
					$product = $this->aaEbayWS
						->lookup( $asin );
   
					$respStatus = $this->is_amazon_valid_response( $product );
                    if ( $respStatus['status'] != 'valid' ) { // error occured!
          			    
          			    $_msg[] = 'Invalid '.self::$provider.' response ( ' . $respStatus['code'] . ' - ' . $respStatus['msg'] . ' )';
                        
                        $ret = array_merge($ret, array('msg' => implode('<br />', $_msg)));
                        if ( $retType == 'return' ) { return $ret; }
                        else { die( json_encode( $ret ) ); }
                
                    } else { // success!
        
        				$thisProd = isset($product['Item']) && !empty($product['Item']) ? $product['Item'] : array();
        				if ( !empty($thisProd) ) {
        					//$thisProd['__isfrom'] = 'details-only';
    
                            // build product data array
                            $retProd = array(); 
                            $retProd = $this->build_product_data( $thisProd );
                            if ( $this->is_valid_product_data($retProd) ) {
                                $isValidProduct = true;
                                $_msg[] = 'Valid '.self::$provider.' response';
                            }
        
        					// DEBUG
        					if( $debug_level > 0 ) {
        					    ob_start();
        
        						if( $debug_level == 1) var_dump('<pre>', $retProd,'</pre>');
        						if( $debug_level == 2) var_dump('<pre>', $product ,'</pre>');
        
                                $ret = array_merge($ret, array('msg' => ob_get_clean()));
                                if ( $retType == 'return' ) { return $ret; }
                                else { die( json_encode( $ret ) ); }
        					}
        				}
        			}
    
                } catch (Exception $e) {
                    // Check 
                    if (isset($e->faultcode)) { // error occured!
    
                        ob_start();
                        var_dump('<pre>', 'Invalid '.self::$provider.' response (exception)', $e,'</pre>');
    
                        $_msg[] = ob_get_clean();
                        
                        $ret = array_merge($ret, array('msg' => implode('<br />', $_msg)));
                        if ( $retType == 'return' ) { return $ret; }
                        else { die( json_encode( $ret ) ); }
                    }
                } // end try
            } // end from amazon
            
            // If valid product data retrieved -> Try to Import Product in Database
            if ( $isValidProduct ) {

                if ( 1 ) {
                    $this->the_plugin->add_last_imports('request_amazon', array(
                        'duration'      => $this->the_plugin->timer_end(),
                    )); // End Timer & Add Report
                }

                // do not import product - just return the product data array
                if( !isset($do_import_product) || $do_import_product != 'yes' ){
                    $ret = array_merge($ret, array(
                        'status'        => 'valid',
                        'product_data'  => $retProd,
                        'msg'           => implode('<br />', $_msg))
                    );
                    if ( $retType == 'return' ) { return $ret; }
                    else { die( json_encode( $ret ) ); }
                }
        
                // add product in database
                $args_add = $requestData;
                $insert_id = $this->the_plugin->addNewProduct( $retProd, $args_add );
                $insert_id = (int) $insert_id;
                $opStatusMsg = $this->the_plugin->opStatusMsgGet();

                // Successfully adding product in database
                if ( $insert_id > 0 ) {

                    $_msg[] = self::MSG_SEP . ' Successfully Adding product in database (with ID: <strong>'.$insert_id.'</strong>).';
                    $ret['status'] = 'valid';
                    
                    if ( !empty($import_type) && $import_type=='default' ) {
                        $ret = array_merge($ret, array(
                            'show_download_lightbox'     => true,
                            'download_lightbox_html'     => $this->the_plugin->download_asset_lightbox( $insert_id, $from_module, 'html' ),
                       ));
                    }
                }
                // Error when trying to insert product in database
                else {
                    $_msg[] = self::MSG_SEP . ' Error Adding product in database.';
                }
                
                // detailed status from adding operation: successfull or with errors
                $_msg[] = $opStatusMsg['msg'];
                
                $ret = array_merge($ret, array('msg' => implode('<br />', $_msg)));
                if ( $retType == 'return' ) { return $ret; }
                else { die( json_encode( $ret ) ); }

            } else {

                $_msg[] = self::MSG_SEP . ' product data (from cache or '.self::$provider.') is not valid!';

                $ret = array_merge($ret, array('msg' => implode('<br />', $_msg)));
                if ( $retType == 'return' ) { return $ret; }
                else { die( json_encode( $ret ) ); }
            }

			// $scheduler->registerShutdownEvent(array($scheduler, 'getLastError'), true);
        }

        // verify if amazon response is valid!
        public function is_amazon_valid_response( $response, $operation='' ) {
			$respStatus = $this->aaEbayWS->getResponseStatus($response);
			if ( 'invalid' == $respStatus['status'] ) {
				if ( !isset($respStatus['html']) ) {
					$respStatus['html'] = $respStatus['msg'];
				}
				return $respStatus;
			}
			if ( 'search' == $operation ) {
				if ( isset($response['searchResult'], $response['searchResult']['item'])
					&& !empty($response['searchResult'])
					&& !empty($response['searchResult']['item']) ) ;
				else {
					$respStatus = array_merge($respStatus, array(
						'status'	=> 'invalid',
	                    'code'      => 3,
	                    'msg'       => 'api response has invalid result or (searchResult,item) pair!',
					));
				}
			}
			else {
				if ( isset($response['Item'])
					&& !empty($response['Item']) ) ;
				else {
					$respStatus = array_merge($respStatus, array(
						'status'	=> 'invalid',
	                    'code'      => -1,
	                    'msg'       => 'api response has invalid result!',
					));
				}
			}
			if ( !isset($respStatus['html']) ) {
				$respStatus['html'] = $respStatus['msg'];
			}
			return $respStatus;
        }

        // product data is valid
        public function is_valid_product_data( $product=array(), $from='details' ) {
            if ( empty($product) || !is_array($product) ) return false;
            
            $rules = isset($product['ASIN']) && !empty($product['ASIN']);
			$rules2 = false; //isset($product['ItemID']) && !empty($product['ItemID']); //itemId | ItemID
			
			$rules3 = isset($product['__isfrom'])
				&& ( (in_array($product['__isfrom'], array('details', 'details-only'))) || $from == $product['__isfrom'] );
			
            $rules = ($rules || $rules2) && $rules3;
            return $rules ? true : false;
        }

        // build single product data based on amazon request array
        public function build_product_data( $item=array(), $old_item=array() ) {

			// 3 = Apparel & Accessories
			$category = '';
			$category_id = '0';

			$retProd = array();
			if ( isset($item['__isfrom']) ) {
				$retProd['__isfrom'] = $item['__isfrom'];
			}
			$isc = isset($item['__isfrom']) ? $item['__isfrom'] : 'details-only';
			
            // summarize product details
            // from product details request
            if ( in_array($isc, array('details', 'details-only')) ) {
	            $retProd = array_merge($retProd, array(
	                'ASIN'                  => isset($item['ItemID']) ? $item['ItemID'] : '',
	                'ParentASIN'            => '',

	                //A SKU is not required to be unique. A seller can specify a particular SKU on one item or on multiple items. Different sellers can use the same SKUs.
	                //'SKU'                   => isset($item['SKU']) ? $item['SKU'] : '', // it's not sent in here!

	                'Brand'					=> '',
	                'BrowseNodes'           => array(),
	                'SmallImage'            => '',
	                'LargeImage'            => '',

	                'ItemAttributes'        => isset($item['ItemSpecifics'], $item['ItemSpecifics']['NameValueList']) ? $item['ItemSpecifics']['NameValueList'] : array(),
	                'Feature'               => '',
	                'EditorialReviews'		=> '',
	                'Description'			=> isset($item['Description']) ? $item['Description'] : '',
					'Summary'				=> '',
					'Title'					=> isset($item['Title']) ? $item['Title'] : '',
					
					'Variations'            => isset($item['Variations']) ? $item['Variations'] : array(),
					
					'hasGallery'			=> 'false',
	            ));
				//$retProd['Feature'] = array($retProd['Summary']);
				
				if ( !empty($retProd['Variations']) ) {
					$variations = isset($retProd['Variations']['Variation']) ? (array) $retProd['Variations']['Variation'] : array();
					$retProd['Variations']['TotalVariations'] = 1;
	            	if (isset($variations['VariationSpecifics']) || isset($variations['StartPrice'])
						|| isset($variations['SKU'])) { // --fix 2015.03.19
						//$retProd['Variations']['TotalVariations'] = 1;
					} else {
						$retProd['Variations']['TotalVariations'] = (int) count($retProd['Variations']['Variation']);
					}
				}

				if ( isset($item['PrimaryCategoryName']) && !empty($item['PrimaryCategoryName']) ) {
					$classification = explode(':', $item['PrimaryCategoryName']);
					$classification = array_map('trim', $classification);
					$classification = array_filter($classification);
					$retProd['BrowseNodes'] = $classification;
				}
				
				$retProd['DetailPageURL'] = isset($item['ViewItemURLForNaturalSearch']) ? $item['ViewItemURLForNaturalSearch'] : '';

				// other fields
				$metas = array('BestOfferEnabled', 'EndTime', 'StartTime', 'ViewItemURLForNaturalSearch', 'ListingType', 'Location', 'PaymentMethods', 'PrimaryCategoryID', 'PrimaryCategoryName', 'Quantity', 'Seller', 'BidCount', 'ConvertedCurrentPrice', 'CurrentPrice', 'ListingStatus', 'QuantitySold', 'ShipToLocations', 'Site', 'TimeLeft', 'HitCount', 'PrimaryCategoryIDPath', 'Storefront', 'Country', 'ReturnPolicy', 'AutoPay', 'IntegratedMerchantCreditCardEnabled', 'HandlingTime', 'ConditionID', 'ConditionDisplayName', 'QuantityAvailableHint', 'QuantityThreshold', 'ExcludeShipToLocation', 'GlobalShipping', 'ConditionDescription', 'QuantitySoldByPickupInStore', 'NewBestOffer');
				foreach ($metas as $key) {
					if ( isset($item["$key"]) && !empty($item["$key"]) ) {
						$retProd['__extra']["$key"] = isset($item["$key"]) ? $item["$key"] : '';
					}
				}
            }
			// from search pages request
			else {
	            $retProd = array_merge($retProd, array(
	                'ASIN'                  => isset($item['itemId']) ? $item['itemId'] : '',
	                'ParentASIN'            => '',

					//A SKU is not required to be unique. A seller can specify a particular SKU on one item or on multiple items. Different sellers can use the same SKUs.
	                'SKU'                   => isset($item['SKU']) ? $item['SKU'] : '',

	                'SmallImage'            => '', //isset($item['galleryURL']) ? trim( $item['galleryURL'] ) : '',
	                'LargeImage'            => '', //isset($item['pictureURLSuperSize']) ? trim( $item['pictureURLSuperSize'] ) : isset($item['pictureURLLarge']) ? trim( $item['pictureURLLarge'] ) : '',
					
					//'Tags'					=> '',
					'Title'					=> isset($item['title']) ? $item['title'] : '',
	            ));
				
				$retProd['DetailPageURL'] = isset($item['viewItemURL']) ? $item['viewItemURL'] : '';
				
				// other fields
				$metas = array('globalId', 'primaryCategory', 'viewItemURL', 'paymentMethod', 'autoPay', 'location', 'country', 'shippingInfo', 'sellingStatus', 'listingInfo', 'returnsAccepted', 'isMultiVariationListing', 'topRatedListing');
				foreach ($metas as $key) {
					if ( isset($item["$key"]) && !empty($item["$key"]) ) {
						$retProd['__extra']["$key"] = isset($item["$key"]) ? $item["$key"] : '';
					}
				}
			}

            // Images
            $retProd['images'] = $this->build_images_data( $item );
            if ( empty($retProd['images']['large']) ) {
                // no images found - if has variations, try to find first image from variations
                $retProd['images'] = $this->get_first_variation_image(
                	isset($item['Variations'], $item['Variations']['Pictures']) ? $item['Variations']['Pictures'] : array()
                );
            }
			if ( empty($retProd['SmallImage']) ) {
                if ( isset($retProd['images']['small']) && !empty($retProd['images']['small']) ) {
                    $retProd['SmallImage'] = $retProd['images']['small'][0];
                }
            }
            if ( empty($retProd['LargeImage']) ) {
                if ( isset($retProd['images']['large']) && !empty($retProd['images']['large']) ) {
                    $retProd['LargeImage'] = $retProd['images']['large'][0];
                }
            }
			if ( empty($retProd['SmallImage']) ) {
				$retProd['SmallImage'] = $retProd['LargeImage'];
			}
  
			// new/current content will overwrite old content
			if ( !empty($old_item) && is_array($old_item) ) {

				if ( in_array($isc, array('details', 'details-only')) ) {
					if ( isset($old_item['SmallImage']) && !empty($old_item['SmallImage']) ) {
						unset( $retProd['SmallImage'] ); // search sends better images
					}
					if ( isset($old_item['LargeImage']) && !empty($old_item['LargeImage']) ) {
						unset( $retProd['LargeImage'] ); // search sends better images
					}
					if ( isset($old_item['images']['large']) && !empty($old_item['images']['large']) ) {
						$old_item['images']['large'] = array_merge(
							$old_item['images']['large'],
							$retProd['images']['large']
						);
						$old_item['images']['small'] = array_merge(
							$old_item['images']['small'],
							$retProd['images']['small']
						);
                		$old_item['images']['large'] = @array_unique($old_item['images']['large']);
                		$old_item['images']['small'] = @array_unique($old_item['images']['small']);
						unset( $retProd['images'] );
					}
					
					if ( !empty($old_item['DetailPageURL']) && empty($retProd['DetailPageURL']) ) {
						unset( $retProd['DetailPageURL'] );	
					}
				}
 
				$retProd = array_replace_recursive($old_item, $retProd);
			}

			// has gallery: get gallery images
			if ( isset($item['PictureURL']) ) {
				if ( !is_array($item['PictureURL']) ) {
					$item['PictureURL'] = array($item['PictureURL']);
				}
				foreach ( $item['PictureURL'] as $key => $value ) {
					$value_ = trim($value);
					if ( !empty($value_) ) {
						$retProd['hasGallery'] = 'true';
						break;
					}
				}
			}
            return $retProd;
        }

        public function build_images_data( $item=array(), $nb_images='all' ) {
            $retProd = array( 'large' => array(), 'small' => array() );
  
            // product large image
            if ( isset($item['pictureURLSuperSize']) ) {
               $retProd['large'][] = $item['pictureURLSuperSize'];
            } else if ( isset($item['pictureURLLarge']) ) {
            	$retProd['large'][] = $item['pictureURLLarge'];
			}
			if ( isset($retProd['large'][0]) ) {
	            if ( isset($item['galleryURL']) ) {
	               $retProd['small'][] = $item['galleryURL'];
	            } else {
	            	$retProd['small'][] = $retProd['large'][0];
	            }
            }

            // get gallery images
            if (isset($item['PictureURL'])) {

				if ( !is_array($item['PictureURL']) ) {
					$item['PictureURL'] = array($item['PictureURL']);
				}

                $count = 0;
                foreach ($item['PictureURL'] as $key => $value) {
                    
					$value_ = trim($value);
					if ( !empty($value_) ) {
						$retProd['large'][] = $value_;
						$retProd['small'][] = $value_;
					}
                    $count++;
                }
                $retProd['large'] = @array_unique($retProd['large']);
                $retProd['small'] = @array_unique($retProd['small']);
            }
 
            // remove empty array elements!
			$retProd['large'] = @array_filter($retProd['large']);
            $retProd['small'] = @array_filter($retProd['small']);
            
            return $retProd;
        }
		
        // if product is variation parent, get first variation child image as product image
        public function get_first_variation_image( $retProd, $filter=array() ) {
            $images = array( 'large' => array(), 'small' => array() );

            if ( isset($retProd['VariationSpecificPictureSet']) ) {

				$name = $retProd['VariationSpecificName'];
                $variations = $retProd['VariationSpecificPictureSet'];
				
				if ( !isset($variations[0]) ) {
					$variations = array($variations);
				}

                // Loop through the variation
                $pics = array();
                foreach ($variations as $variation_item) {
                	
					$name_item = $variation_item['VariationSpecificValue'];

					if ( empty($filter) ) {
						// get first pictures set found!
						$pics = $variation_item;
	                    $images = $this->build_images_data( $pics );
	                    if ( !empty($images['large']) ) {
	                        return $images;
	                    }
					}
					else {
						if ( isset($filter["$name"]) && !empty($filter["$name"]) ) {
							if (in_array($name_item, $filter["$name"])) {
								$pics = $variation_item;
								$images = $this->build_images_data( $pics );
								break;
							}
						}
					}

                } // end foreach
            }
            return $images;
        }

		/**
	     * Create the tags for the product
	     * @param array $Tags
	     */
	    public function set_product_tags( $Tags='' )
	    {
			return array();
	    }

		/**
	     * Create the categories for the product & the attributes
	     * @param array $browseNodes
	     */
	    public function set_product_categories( $browseNodes=array() )
	    {
	        // The woocommerce product taxonomy
	        $wooTaxonomy = "product_cat";
	        
	        // Categories for the product
	        $createdCategories = array();
	        
	        // Category container
	        $categories = array();

	        // browseNode
	        if( is_array( $browseNodes ) && !empty($browseNodes) ) {
	        	
	            // Create a clone
	            $currentNode = $browseNodes;

	            // Always true unless proven
	            $validCat = true;
				
				foreach ($currentNode as $key => $value) {
		            // Replace html entities
		            $dmCatName = str_replace( array('&amp;', '&'), 'and', $value );
		            $dmCatSlug = sanitize_title( $dmCatName );
		            
		            // Check if we will make the cat
		            if( $validCat ) {
		                $categories[] = array(
		                    'name' => $dmCatName,
		                    'slug' => $dmCatSlug
		                );
		            }
				}
			}
			
			if ( 1 ) {
		        // Import only parent category from Amazon
				if( isset( $this->amz_settings["create_only_parent_category"] ) && $this->amz_settings["create_only_parent_category"] != '' && $this->amz_settings["create_only_parent_category"] == 'yes') {
					$categories = array( $categories[0] );
				}

	            // The current node
	            $nodeCounter = 0;
	            // Loop through the array of the current browsenode
	            foreach( $categories as $node )
	            {
	                // Check if we're NOT at parent
	                if( $nodeCounter > 0 )
	                {
	                    // The parent of the current node
	                    $parentNode = $categories[$nodeCounter - 1];
	                    // Get the term id of the parent
	                    $parent = term_exists( str_replace( '&', 'and', $parentNode['slug'] ), $wooTaxonomy );
					}

	                if( 1 )
	                {
	                    // Check if term exists
	                    $checkTerm = term_exists( str_replace( array('&amp;', '&'), 'and', $node['slug'] ), $wooTaxonomy );
	                    if( empty( $checkTerm ) )
	                    {
							if( $nodeCounter > 0 )
			                {
	                        	// Create the new category
								$newCat = wp_insert_term( $node['name'], $wooTaxonomy, array( 'slug' => $node['slug'], 'parent' => $parent['term_id'] ) );
							}
							else {
	                        	// Create the new category
								$newCat = wp_insert_term( $node['name'], $wooTaxonomy, array( 'slug' => $node['slug'] ) );								
							}
	                       
							// Add the created category in the createdCategories
							// Only run when the $newCat is an error
							if( gettype($newCat) != 'object' ) {
								$createdCategories[] = $newCat['term_id'];
							}
	                    }
	                    else
	                    {
	                        // if term already exists add it on the createdCategories
	                        $createdCategories[] = $checkTerm['term_id'];
	                    }
	                }

					$nodeCounter++;
	            }
			}
			
	        // Delete the product_cat_children
	        // This is to force the creation of a fresh product_cat_children
	        delete_option( 'product_cat_children' );
	        
	        $returnCat = array_unique($createdCategories);
	     
	        // return an array of term id where the post will be assigned to
	        return $returnCat;
	    }

		public function set_woocommerce_attributes( $itemAttributes=array(), $post_id ) 
		{
	        global $wpdb;
	        global $woocommerce;
	 
	        // convert Alibaba attributes into woocommerce attributes
	        $_product_attributes = array();
	        $position = 0;
			
			$allowedAttributes = 'all';

			//if ( isset($this->amz_settings['selected_attributes'])
			//	&& !empty($this->amz_settings['selected_attributes'])
			//	&& is_array($this->amz_settings['selected_attributes']) )
			//	$allowedAttributes = (array) $this->amz_settings['selected_attributes'];

			$_attr = array(); 
	        foreach( $itemAttributes as $_key => $_value )
	        {
	            if (is_array($_value)) 
	            {
	            	$key = strtolower($_value['Name']);
					$value = $_value['Value'];
					
					if ( empty($value) ) continue 1;
					
					if ( !is_array($value) ) {
						$value = array( $value );
					}
					foreach ($value as $_key2 => $_value2)
					{
						$value2 = $_value2;
						if ( isset($_attr["$key"]) )
						{
							if ( !in_array($value2, $_attr["$key"]) ) {
								$_attr["$key"][] = $value2;
							}
						} else {
							$_attr["$key"][0] = $value2;
						}
					}
				}
			}
			foreach( $_attr as $_key => $_value ) {
				if ( count($_value) > 1 ) ;
				else {
					$_attr["$_key"] = $_value[0];
				}
			}
 
	        foreach( $_attr as $key => $value )
	        {
	            if (1) 
	            {
	            	//if ( is_array($allowedAttributes) ) {
					//	if ( !in_array($key, $allowedAttributes) ) {
					//		continue 1;
					//	}
					//}
					
					// don't add these into attributes
					//if( in_array($key, array('ListPrice', 'Feature', 'Title') ) ) continue;
					
					$value_orig = $value;
	                
	                // change dimension name as woocommerce attribute name
	                $attribute_name = $this->the_plugin->cleanTaxonomyName(strtolower($key));
 
					// convert value into imploded array
					if( is_array($value) ) {
						$value = $this->the_plugin->multi_implode( $value, ', ' ); 
					}
					
					// Clean
					$value = $this->the_plugin->cleanValue( $value );
					 
					// if is empty attribute don't import
					if( trim($value) == "" ) continue;
					
	                $_product_attributes[$attribute_name] = array(
	                    'name' => $attribute_name,
	                    'value' => $value,
	                    'position' => $position++,
	                    'is_visible' => 1,
	                    'is_variation' => 0,
	                    'is_taxonomy' => 1
	                );
  
	                $this->add_attribute( $post_id, $key, $value_orig );
	            }
	        }
	        
	        // update product attribute
	        update_post_meta($post_id, '_product_attributes', $_product_attributes);
			
			//$this->the_plugin->get_ws_object( 'generic' )->attrclean_clean_all( 'array' ); // delete duplicate attributes
			
	        // refresh attribute cache
	        $dmtransient_name = 'wc_attribute_taxonomies';
	        $dmattribute_taxonomies = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "woocommerce_attribute_taxonomies");
	        set_transient($dmtransient_name, $dmattribute_taxonomies);
	    }
	
	    // add woocommrce attribute values
	    public function add_attribute($post_id, $key, $value) 
	    { 
	        global $wpdb;
	        global $woocommerce;
			 
	        // get attribute name, label
	        if ( isset($this->amz_settings['attr_title_normalize']) && $this->amz_settings['attr_title_normalize'] == 'yes' )
	        	$attribute_label = $this->attrclean_splitTitle( $key );
			else
				$attribute_label = $key;   
	        $attribute_name = $this->the_plugin->cleanTaxonomyName($key, false);

	        // set attribute type
	        $attribute_type = 'select';
	        
	        // check for duplicates
	        $attribute_taxonomies = $wpdb->get_var("SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = '".esc_sql($attribute_name)."'");
	        
	        if ($attribute_taxonomies) {
	            // update existing attribute
	            $wpdb->update(
                    $wpdb->prefix . 'woocommerce_attribute_taxonomies', array(
		                'attribute_label' => $attribute_label,
		                'attribute_name' => $attribute_name,
		                'attribute_type' => $attribute_type,
		                'attribute_orderby' => 'name'
                    ), array('attribute_name' => $attribute_name)
	            );
	        } else {
	            // add new attribute
	            $wpdb->insert(
	                $wpdb->prefix . 'woocommerce_attribute_taxonomies', array(
	                	'attribute_label' => $attribute_label,
	                	'attribute_name' => $attribute_name,
	                	'attribute_type' => $attribute_type,
	                	'attribute_orderby' => 'name'
	                )
	            );
	        }

	        // avoid object to be inserted in terms
	        if (is_object($value))
	            return;
	
	        // add attribute values if not exist
	        $taxonomy = $this->the_plugin->cleanTaxonomyName($attribute_name);
			
	        if( is_array( $value ) )
	        {
	            $values = $value;
	        }
	        else
	        {
	            $values = array($value);
	        }
  
	        // check taxonomy
	        if( !taxonomy_exists( $taxonomy ) ) 
	        {
	            // add attribute value
	            foreach ($values as $attribute_value) {
	            	$attribute_value = (string) $attribute_value;
	                if(is_string($attribute_value)) {
	                    // add term
	                    //$name = stripslashes($attribute_value);
	                    $name = $this->the_plugin->cleanValue( $attribute_value ); // 2015, october 28 - attributes bug update!
	                    $slug = sanitize_title($name);
						
	                    if( !term_exists($name) ) {
	                        if( trim($slug) != '' && trim($name) != '' ) {
	                        	$this->the_plugin->db_custom_insert(
	                        		$wpdb->terms,
	                        		array(
	                        			'values' => array(
		                                	'name' => $name,
		                                	'slug' => $slug
										),
										'format' => array(
											'%s', '%s'
										)
	                        		),
	                        		true
	                        	);
	                            /*$wpdb->insert(
                                    $wpdb->terms, array(
		                                'name' => $name,
		                                'slug' => $slug
                                    )
	                            );*/
	
	                            // add term taxonomy
	                            $term_id = $wpdb->insert_id;
	                        	$this->the_plugin->db_custom_insert(
	                        		$wpdb->term_taxonomy,
	                        		array(
	                        			'values' => array(
		                                	'term_id' => $term_id,
		                                	'taxonomy' => $taxonomy
										),
										'format' => array(
											'%d', '%s'
										)
	                        		),
	                        		true
	                        	);
	                            /*$wpdb->insert(
                                    $wpdb->term_taxonomy, array(
		                                'term_id' => $term_id,
		                                'taxonomy' => $taxonomy
                                    )
	                            );*/
								$term_taxonomy_id = $wpdb->insert_id;
								$__dbg = compact('taxonomy', 'attribute_value', 'term_id', 'term_taxonomy_id');
								//var_dump('<pre>1: ',$__dbg,'</pre>');
	                        }
	                    } else {
	                        // add term taxonomy
	                        $term_id = $wpdb->get_var("SELECT term_id FROM {$wpdb->terms} WHERE name = '".esc_sql($name)."'");
	                        $this->the_plugin->db_custom_insert(
	                        	$wpdb->term_taxonomy,
	                        	array(
	                        		'values' => array(
		                           		'term_id' => $term_id,
		                           		'taxonomy' => $taxonomy
									),
									'format' => array(
										'%d', '%s'
									)
	                        	),
	                        	true
	                        );
	                        /*$wpdb->insert(
                           		$wpdb->term_taxonomy, array(
		                            'term_id' => $term_id,
		                            'taxonomy' => $taxonomy
                                )
	                        );*/
							$term_taxonomy_id = $wpdb->insert_id;
							$__dbg = compact('taxonomy', 'attribute_value', 'term_id', 'term_taxonomy_id');
							//var_dump('<pre>1c: ',$__dbg,'</pre>');
	                    }
	                }
	            }
	        }
	        else 
	        {
	            // get already existing attribute values
	            $attribute_values = array();
	            /*$terms = get_terms($taxonomy, array('hide_empty' => true));
				if( !is_wp_error( $terms ) ) {
	            	foreach ($terms as $term) {
	                	$attribute_values[] = $term->name;
	            	}
				} else {
					$error_string = $terms->get_error_message();
					var_dump('<pre>',$error_string,'</pre>');  
				}*/
				$terms = $this->the_plugin->load_terms($taxonomy);
	            foreach ($terms as $term) {
	               	$attribute_values[] = $term->name;
	            }
	            
	            // Check if $attribute_value is not empty
	            if( !empty( $attribute_values ) )
	            {
	                foreach( $values as $attribute_value ) 
	                {
	                	$attribute_value = (string) $attribute_value;
						$attribute_value = $this->the_plugin->cleanValue( $attribute_value ); // 2015, october 28 - attributes bug update!
	                    if( !in_array( $attribute_value, $attribute_values ) ) 
	                    {
	                        // add new attribute value
	                        $__term_and_tax = wp_insert_term($attribute_value, $taxonomy);
							$__dbg = compact('taxonomy', 'attribute_value', '__term_and_tax');
							//var_dump('<pre>1b: ',$__dbg,'</pre>');
	                    }
	                }
	            }
	        }
	
	        // Add terms
	        if( is_array( $value ) )
	        {
	            foreach( $value as $dm_v )
	            {
	            	$dm_v = (string) $dm_v;
	                if( !is_array($dm_v) && is_string($dm_v)) {
	                	$dm_v = $this->the_plugin->cleanValue( $dm_v ); // 2015, october 28 - attributes bug update!
	                    $__term_and_tax = wp_insert_term( $dm_v, $taxonomy );
						$__dbg = compact('taxonomy', 'dm_v', '__term_and_tax');
						//var_dump('<pre>2: ',$__dbg,'</pre>');
	                }
	            }
	        }
	        else
	        {
	        	$value = (string) $value;
	            if( !is_array($value) && is_string($value) ) {
	            	$value = $this->the_plugin->cleanValue( $value ); // 2015, october 28 - attributes bug update!
	                $__term_and_tax = wp_insert_term( $value, $taxonomy );
					$__dbg = compact('taxonomy', 'value', '__term_and_tax');
					//var_dump('<pre>2b: ',$__dbg,'</pre>');
	            }
	        }
			
	        // link to woocommerce attribute values
	        if( !empty( $values ) )
	        {
	            foreach( $values as $term )
	            {
	            	
	                if( !is_array($term) && !is_object( $term ) )
	                { 
	                    $term = sanitize_title($term);
	                    
	                    $term_taxonomy_id = $wpdb->get_var( "SELECT tt.term_taxonomy_id FROM {$wpdb->terms} AS t INNER JOIN {$wpdb->term_taxonomy} as tt ON tt.term_id = t.term_id WHERE t.slug = '".esc_sql($term)."' AND tt.taxonomy = '".esc_sql($taxonomy)."'" );
  
	                    if( $term_taxonomy_id ) 
	                    {
	                        $checkSql = "SELECT * FROM {$wpdb->term_relationships} WHERE object_id = {$post_id} AND term_taxonomy_id = {$term_taxonomy_id}";
	                        if( !$wpdb->get_var($checkSql) ) {
	                            $wpdb->insert(
	                                    $wpdb->term_relationships, array(
			                                'object_id' => $post_id,
			                                'term_taxonomy_id' => $term_taxonomy_id
	                                    )
	                            );
	                        }
	                    }
	                }
	            }
	        }
	    }

		/**
		 * Product Price - from Amazon
		 */
		public function productAmazonPriceIsZero( $thisProd ) {
        	$product_meta = array('product' => array('regular_price' => ''));

			if ( isset($thisProd['__extra'], $thisProd['__extra']['ConvertedCurrentPrice']) ) {
				$product_meta['product']['regular_price'] = $thisProd['__extra']['ConvertedCurrentPrice'];
			} else if ( isset($thisProd['__extra'], $thisProd['__extra']['CurrentPrice']) ) {
				$product_meta['product']['regular_price'] = $thisProd['__extra']['CurrentPrice'];
			}
			if ( isset($thisProd['is_variation'], $thisProd['__extra']['StartPrice'])
				&& $thisProd['is_variation'] ) {
				$product_meta['product']['regular_price'] = $thisProd['__extra']['StartPrice'];
			}
			$product_meta['product']['price'] = $product_meta['product']['regular_price'];
			
			$prodprice = $product_meta['product'];

			if ( empty($prodprice['regular_price']) || (int)$prodprice['regular_price'] <= 0 ) return true;
			return false;
		}

		public function productPriceUpdate( $thisProd, $post_id='', $return=true )
		{
			// get current product meta, update the values of prices and update it back
			$product_meta = get_post_meta( $post_id, '_product_meta', true );

			if ( isset($thisProd['__extra'], $thisProd['__extra']['ConvertedCurrentPrice']) ) {
				$product_meta['product']['regular_price'] = $thisProd['__extra']['ConvertedCurrentPrice'];
			} else if ( isset($thisProd['__extra'], $thisProd['__extra']['CurrentPrice']) ) {
				$product_meta['product']['regular_price'] = $thisProd['__extra']['CurrentPrice'];
			}
			if ( isset($thisProd['is_variation'], $thisProd['__extra']['StartPrice'])
				&& $thisProd['is_variation'] ) {
				$product_meta['product']['regular_price'] = $thisProd['__extra']['StartPrice'];
			}
			$product_meta['product']['price'] = $product_meta['product']['regular_price'];

			// set product price metas!
			if ( isset($product_meta['product']['sale_price']) && !empty($product_meta['product']['sale_price']) ) {
				update_post_meta($post_id, '_sale_price', $product_meta['product']['sale_price']);
			} else { // new sale price is 0
				update_post_meta($post_id, '_sale_price', '');
			}
			update_post_meta($post_id, '_price_update_date', time());
			update_post_meta($post_id, '_regular_price', $product_meta['product']['regular_price']);
			update_post_meta($post_id, '_price', $product_meta['product']['price']);

			// set product price extra metas!
			$retExtra = $this->productPriceSetMeta( $thisProd, $post_id, 'return' );

			if( $return == true ) {
				die(json_encode(array(
					'status' => 'valid',
					'data'		=> array(
						'_sale_price' => isset($product_meta['product']['sale_price']) ? woocommerce_price($product_meta['product']['sale_price']) : '-',
						'_regular_price' => woocommerce_price($product_meta['product']['regular_price']),
						'_price_update_date' => date('F j, Y, g:i a', time())
					)
				)));
			}
		}

        public function get_productPrice( $thisProd )
        {
            $ret = array(
                'status'                => 'valid',
                '_price'                => '',
                '_sale_price'           => '',
                '_regular_price'        => '',
                '_price_update_date'    => '',
            );

        	$product_meta = array('product' => array('regular_price' => ''));

			if ( isset($thisProd['__extra'], $thisProd['__extra']['ConvertedCurrentPrice']) ) {
				$product_meta['product']['regular_price'] = $thisProd['__extra']['ConvertedCurrentPrice'];
			} else if ( isset($thisProd['__extra'], $thisProd['__extra']['CurrentPrice']) ) {
				$product_meta['product']['regular_price'] = $thisProd['__extra']['CurrentPrice'];
			}
			if ( isset($thisProd['is_variation'], $thisProd['__extra']['StartPrice'])
				&& $thisProd['is_variation'] ) {
				$product_meta['product']['regular_price'] = $thisProd['__extra']['StartPrice'];
			}
			$product_meta['product']['price'] = $product_meta['product']['regular_price'];

            // set product price metas!
            if ( isset($product_meta['product']['sale_price']) && !empty($product_meta['product']['sale_price']) ) {
                $ret['_sale_price'] = $product_meta['product']['sale_price'];
            } else { // new sale price is 0
                $ret['_sale_price'] = '';
            }
            $ret['_price_update_date'] = time();
            $ret['_regular_price'] = $product_meta['product']['regular_price'];
			$ret['_price'] = $product_meta['product']['price'];
 
            return $ret;
        }
	
        /**
         * Product Variations
         */
		public function set_woocommerce_variations( $retProd, $post_id, $variationNumber ) 
		{
	        global $woocommerce;
			
            $ret = array(
                'status'        => 'valid',
                'msg'           => '',
                'nb_found'      => 0,
                'nb_parsed'     => 0,
            );

			//$var_mode = '';
			$VariationDimensions = array();
			 
			// convert $variationNumber into number
			if( $variationNumber == 'yes_all' ){
				$variationNumber = 500; // 500 variations per product is enough
			}
			elseif( $variationNumber == 'no' ){
				$variationNumber = 0;
			}
            else{
                $variationNumber = explode(  "_", $variationNumber );
                $variationNumber = end( $variationNumber );
            }
            $variationNumber = (int) $variationNumber;
   
            $status = 'valid';
            if ( empty($variationNumber)
                || !isset($retProd['Variations']['TotalVariations']) || $retProd['Variations']['TotalVariations'] <= 0 ) {

                $status = 'invalid';
                return array_merge($ret, array(
                    'status'    => $status,
                    'msg'       => sprintf( $status . ': no variations found (number of variations setting: %s).', $variationNumber ),
                ));
            }

            $offset = 0; 
	        if ( $status == 'valid' ) { // status is valid

                $this->the_plugin->timer_start(); // Start Timer

	            // its not a simple product, it is a variable product
	            wp_set_post_terms($post_id, 'variable', 'product_type', false);
   
	            // initialize the variation dimensions array
				$varspecs = isset($retProd['Variations']['VariationSpecificsSet'],
					$retProd['Variations']['VariationSpecificsSet']['NameValueList'])
					? $retProd['Variations']['VariationSpecificsSet']['NameValueList'] : array();
				if ( !isset($varspecs[0]) ) { // array with 1 element
					$varspecs = array( $varspecs );
				}
				foreach ($varspecs as $kk => $vv) {
					$dim = $vv['Name'];
					$VariationDimensions["$dim"] = array();
				}


                $ret['nb_found'] = $retProd['Variations']['TotalVariations'];
				
	            // loop through the variations
	            $variations = isset($retProd['Variations']['Variation']) ? (array) $retProd['Variations']['Variation'] : array();
	            if (isset($variations['VariationSpecifics']) || isset($variations['StartPrice'])
					|| isset($variations['SKU'])) { // --fix 2015.03.19

	            	$variations = array( $variations );
	            }
				if (1) {

	                // if the variation still has items 
					$cc = 0;

	                // Loop through the variation
	                for( $cc = 1; $cc <= $variationNumber; $cc++ )
	                {
	                    // Check if there are still variations
	                    if( $offset > ((int)$retProd['Variations']['TotalVariations'] - 1) ) {
	                        break;
	                    }

	                    // Get the specifc variation 
	                    $variation_item = $variations[$offset];
						
						$variation_id = sprintf(
							self::$variation_id,
							$retProd['ASIN'],
							(isset($variation_item['SKU']) ? $variation_item['SKU'] : $offset)
						);
						$variation_item = array_merge($variation_item, array(
							'is_variation'	=> true,
							'offset'		=> $offset,
							'ASIN'			=> $variation_id,
							'Title'			=> $retProd['Title'] . ' - ' . $variation_id,
							'pictures'		=> isset($retProd['Variations'], $retProd['Variations']['Pictures'])
								? $retProd['Variations']['Pictures'] : array(),
							'__extra'		=> array(),
						));
						
						// other fields
						$metas = array('StartPrice', 'Quantity', 'SellingStatus');
						foreach ($metas as $key) {
							if ( isset($variation_item["$key"]) && !empty($variation_item["$key"]) ) {
								$variation_item['__extra']["$key"] = isset($variation_item["$key"]) ? $variation_item["$key"] : '';
							}
						}

	                    // Create the variation post
	                    $VariationDimensions = $this->variation_post( $variation_item, $post_id, $VariationDimensions );

	                    // Increase the offset
	                    $offset++;
	                }
	            }

	            $tempProdAttr = get_post_meta( $post_id, '_product_attributes', true );

	            foreach( $VariationDimensions as $name => $values )
	            {
	                if($name != '') {
	                    $dimension_name = $this->the_plugin->cleanTaxonomyName(strtolower($name));

	                	// convert value into imploded array
						if( is_array($values) ) {
							$values = $this->the_plugin->multi_implode( $values, ', ' ); 
						}

						// Clean
						$values = $this->the_plugin->cleanValue( $values );

	                    $tempProdAttr[$dimension_name] = array(
	                        'name' => $dimension_name,
	                        'value' => '', //$values, // 2015, october 28 - attributes bug update!
	                        'position' => 0,
	                        'is_visible' => 1,
	                        'is_variation' => 1,
	                        'is_taxonomy' => 1,
	                    );
						
	                    //$this->add_attribute( $post_id, $name, $values );
	                }
	            }

	            //update_post_meta($post_id, '_product_attributes', serialize($tempProdAttr));
	            // 2015-08-26 fix/ remove double serialize
	            
	            update_post_meta($post_id, '_product_attributes', $tempProdAttr);
                
                if ( $offset > 0 ) {
                    $this->the_plugin->add_last_imports('last_import_variations', array(
                        'duration'      => $this->the_plugin->timer_end(),
                        'nb_items'      => $offset,
                    )); // End Timer & Add Report
                }
	        } // end status is valid

            // status
            $ret['nb_parsed'] = $offset;

            $status = array();
            $status[] = $variationNumber > 0;
            $status[] = empty($ret['nb_found']) || empty($ret['nb_parsed']);
            $status = $status[0] && $status[1] ? 'invalid' : 'valid';

            return array_merge($ret, array(
                'status'    => $status,
                'msg'       => sprintf( $status . ': %s product variations added from %s variations found (number of variations setting: %s).', $ret['nb_parsed'], $ret['nb_found'], $variationNumber ),
            ));
	    }
		
		public function variation_post( $variation_item, $post_id, $VariationDimensions ) 
		{
	        global $woocommerce, $wpdb;
            
			$variation_post = get_post( $post_id, ARRAY_A );
	        $variation_post['post_title'] = isset($variation_item['Title']) ? $variation_item['Title'] : '';
			$variation_post['post_status'] = 'publish';
	        $variation_post['post_type'] = 'product_variation';
	        $variation_post['post_parent'] = $post_id;
	        unset( $variation_post['ID'] );
			
	        $variation_post_id = wp_insert_post( $variation_post );
	
			// IMAGES
			$images = array();
			$images['Title'] = isset($variation_item['Title']) ? $variation_item['Title'] : uniqid();
			
			// Compile all the possible variation dimensions
			$newarr = array();
			if ( isset($variation_item['VariationSpecifics']) ) {
				if ( !isset($variation_item['VariationSpecifics'][0]) ) {
					$variation_item['VariationSpecifics'] = array( $variation_item['VariationSpecifics'] );
				}
				foreach ($variation_item['VariationSpecifics'] as $k => $v) {

					if ( isset($v['NameValueList']) ) {
						if ( !isset($v['NameValueList'][0]) ) {
							$v['NameValueList'] = array( $v['NameValueList'] );
						}
						foreach ($v['NameValueList'] as $kk => $vv) {

							$name_ = $vv['Name'];
							if ( !isset($newarr["$name_"]) ) $newarr["$name_"] = array();
							
							if ( !is_array($vv['Value']) ) $vv['Value'] = array( $vv['Value'] );
							foreach ($vv['Value'] as $kkk => $vvv) {

								if ( !in_array($vvv, $newarr["$name_"]) ) {
									$newarr["$name_"][] = $vvv;
								}								
							}
						}
					}
				}
			}
			
            $images['images'] = $this->get_first_variation_image(
            	$variation_item['pictures'],
            	$newarr
            );

			$this->set_product_images( $images, $variation_post_id, $post_id, 1 );
			// end IMAGES
	        
			// set the product price
			$this->productPriceUpdate( $variation_item, $variation_post_id, false );
			
			// than update the metapost
			$this->set_product_meta_options( $variation_item, $variation_post_id, true );
			
			// Compile all the possible variation dimensions
			foreach ($newarr as $attr_name => $attr_vals) {
				foreach ($attr_vals as $attr_val) {
					// Clean
					$attr_val_ = $this->the_plugin->cleanValue( $attr_val );

	                $this->add_attribute( $post_id, $attr_name, $attr_val_ );

					if ( !isset($VariationDimensions["$attr_name"]) ) {
						$VariationDimensions["$attr_name"] = array();
					}
					if ( !in_array($attr_val_, $VariationDimensions["$attr_name"]) ) {
						$VariationDimensions["$attr_name"][] = $attr_val_;
					}
	        
	                $dimension_name = $this->the_plugin->cleanTaxonomyName(strtolower($attr_name));
	                update_post_meta($variation_post_id, 'attribute_' . $dimension_name, sanitize_title($attr_val_)); 
				}
			}
			 
	        // refresh attribute cache
	        $dmtransient_name = 'wc_attribute_taxonomies';
	        $dmattribute_taxonomies = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "woocommerce_attribute_taxonomies");
	        set_transient($dmtransient_name, $dmattribute_taxonomies);
            
            // status messages
            $this->the_plugin->opStatusMsgSet(array(
                'msg'       => 'variation inserted with ID: ' . $variation_post_id,
            ));
	
	        return $VariationDimensions;
	    }
		
        /**
         * Product Images
         */
		public function set_product_images( $retProd, $post_id, $parent_id=0, $number_of_images='all' )
		{
		    $ret = array(
                'status'        => 'valid',
                'msg'           => '',
                'nb_found'      => 0,
                'nb_parsed'     => 0,
            );

            $retProd["images"]['large'] = @array_unique($retProd["images"]['large']);
            $retProd["images"]['large'] = @array_filter($retProd["images"]['large']); // remove empty array elements!
            
            $status = 'valid';
            if ( empty($retProd["images"]['large']) ) {
                $status = 'invalid';
                return array_merge($ret, array(
                    'status'    => $status,
                    'msg'       => sprintf( $status . ': no images found (number of images setting: %s).', $number_of_images ),
                ));
            }
            $ret['nb_found'] = count($retProd["images"]['large']);
            
            if( (int) $number_of_images > 0 ){
                $retProd['images']['large'] = array_slice($retProd['images']['large'], 0, (int) $number_of_images);
            }

			$productImages = array();
			
			// try to download the images
			if ( $status == 'valid' ) {
			    //if ( 1 ) {
                //    $this->the_plugin->timer_start(); // Start Timer
                //}

				$step = 0;
				
				// product variation - ONLY 1 IMAGE PER VARIATION
				if ( $parent_id > 0 ) {
					$retProd["images"]['large'] = array_slice($retProd["images"]['large'], 0, 1);
				}
				
				// insert the product into db if is not duplicate
				$amz_prod_status = $this->the_plugin->db_custom_insert(
	               	$this->the_plugin->db->prefix . 'amz_products',
	               	array(
	               		'values' => array(
							'post_id' => $post_id, 
							'post_parent' => $parent_id,
							'title' => isset($retProd["Title"]) ? $retProd["Title"] : 'untitled',
							'type' => (int) $parent_id > 0 ? 'variation' : 'post',
							'nb_assets' => count($retProd["images"]['large'])
						),
						'format' => array(
							'%d',
							'%d',
							'%s',
							'%s',
							'%d' 
						)
	                ),
	                true
	            );
				/*$amz_prod_status = $this->the_plugin->db->insert( 
					$this->the_plugin->db->prefix . 'amz_products', 
					array( 
						'post_id' => $post_id, 
						'post_parent' => $parent_id,
						'title' => isset($retProd["Title"]) ? $retProd["Title"] : 'untitled',
						'type' => (int) $parent_id > 0 ? 'variation' : 'post',
						'nb_assets' => count($retProd["images"]['large'])
					), 
					array( 
						'%d',
						'%d',
						'%s',
						'%s',
						'%d' 
					) 
				);*/
			
				foreach ($retProd["images"]['large'] as $key => $value){
					
					$this->the_plugin->db_custom_insert(
						$this->the_plugin->db->prefix . 'amz_assets',
						array(
							'values' => array(
								'post_id' => $post_id,
								'asset' => $value,
								'thumb' => $retProd["images"]['small'][$key],
								'date_added' => date( "Y-m-d H:i:s" )
							), 
							'format' => array( 
								'%d',
								'%s',
								'%s',
								'%s'
							)
						),
						true
					);
					/*$this->the_plugin->db->insert( 
						$this->the_plugin->db->prefix . 'amz_assets', 
						array(
							'post_id' => $post_id,
							'asset' => $value,
							'thumb' => $retProd["images"]['small'][$key],
							'date_added' => date( "Y-m-d H:i:s" )
						), 
						array( 
							'%d',
							'%s',
							'%s',
							'%s'
						) 
					);*/
					
					//$ret = $this->the_plugin->download_image($value, $post_id, 'insert', $retProd['Title'], $step);
					//if(count($ret) > 0){
					//	$productImages[] = $ret;
					//}
					$step++;
				}
                
                // execute only for product, not for a variation child
                //if ( $parent_id <= 0 && count($retProd["images"]['large']) > 0 ) {
                //    $this->the_plugin->add_last_imports('last_import_images', array(
                //        'duration'      => $this->the_plugin->timer_end(),
                //        'nb_items'      => isset($retProd["images"]['large']) ? (int) count($retProd["images"]['large']) : 0,
                //    )); // End Timer & Add Report
                //}
			}

            // status
            $ret['nb_parsed'] = $step;

            $status = array();
            $status[] = ( (string) $number_of_images === 'all' ) || ( (int) $number_of_images > 0 );
            $status[] = empty($ret['nb_found']) || empty($ret['nb_parsed']);
            $status = $status[0] && $status[1] ? 'invalid' : 'valid';

            return array_merge($ret, array(
                'status'    => $status,
                'msg'       => sprintf( $status . ': %s product assets prepared in database from %s images found (number of images setting: %s).', $ret['nb_parsed'], $ret['nb_found'], $number_of_images ),
            ));

			// add gallery to product
			//$productImages = array(); // remade in assets module!
			//if(count($productImages) > 0){
			//	$the_ids = array();
			//	foreach ($productImages as $key => $value){
			//		$the_ids[] = $value['attach_id'];
			//	}
				
			//	// Add the media gallery image as a featured image for this post
			//	update_post_meta($post_id, "_thumbnail_id", $productImages[0]['attach_id']);
			//	update_post_meta($post_id, "_product_image_gallery", implode(',', $the_ids));
			//}
		}
		
        /**
         * Product Metas
         */
		public function set_product_meta_options( $retProd, $post_id, $is_variation=true )
		{
			//if ( $is_variation == false ){
			//	$tab_data = array();
			//	$tab_data[] = array(
			//		'id' => 'amzAff-customer-review',
			//		'content' => '<iframe src="' . ( isset($retProd['CustomerReviewsURL']) ? urldecode($retProd['CustomerReviewsURL']) : '' ) . '" width="100%" height="450" frameborder="0"></iframe>'
			//	);	
			//}

			// update the metapost
			if ( isset($retProd['SKU']) ) update_post_meta($post_id, '_sku', $retProd['SKU']);
			//update_post_meta($post_id, '_amzASIN', $retProd['ASIN']);
			update_post_meta($post_id, '_visibility', 'visible');
			update_post_meta($post_id, '_downloadable', 'no');
			update_post_meta($post_id, '_virtual', 'no');
			update_post_meta($post_id, '_stock_status', 'instock');
			update_post_meta($post_id, '_backorders', 'no');
			update_post_meta($post_id, '_manage_stock', 'no');
			//update_post_meta($post_id, '_product_url', home_url('/?redirectAmzASIN=' . $retProd['ASIN'] ));
			if ( isset($retProd['SalesRank']) ) update_post_meta($post_id, '_sales_rank', $retProd['SalesRank']);
			
			if ( $is_variation == false ){
				update_post_meta($post_id, '_product_version', $this->the_plugin->get_woocommerce_version()); // 2015, october 28 - attributes bug repaired!

				update_option('_transient_wc_product_type_' . $post_id, 'external');
				wp_set_object_terms( $post_id, 'external', 'product_type' );
				//if( isset($retProd['CustomerReviewsURL']) && @trim($retProd['CustomerReviewsURL']) != "" ) 
				//	update_post_meta( $post_id, 'amzaff_woo_product_tabs', $tab_data );
			}
			
			// 2015, october 28 - NEW METAS!
			update_post_meta($post_id, '_ebay_asin', $this->the_plugin->prodid_set($retProd['ASIN'], self::$provider, 'sub'));
			update_post_meta($post_id, '_aiowaff_prodid', $this->the_plugin->prodid_set($retProd['ASIN'], self::$provider, 'add'));
			update_post_meta($post_id, '_aiowaff_prodtype', self::$provider);
			update_post_meta($post_id, '_product_url', home_url(sprintf(
				'/?redirect_prodid=%s',
				$this->the_plugin->prodid_set($retProd['ASIN'], self::$provider, 'add')
			)));
			if ( isset($retProd['DetailPageURL']) )
				update_post_meta($post_id, '_aiowaff_product_url', $retProd['DetailPageURL']);
			
			if ( isset($retProd['__extra']) && !empty($retProd['__extra']) ) {
				update_post_meta($post_id, '_aiowaff_extra', $retProd['__extra']);
			}


			update_post_meta($post_id, '_wwcEbyAff_prodid', $this->the_plugin->prodid_set($retProd['ASIN'], self::$provider, 'add'));
			update_post_meta($post_id, '_wwcEbyAff_prodtype', self::$provider);
			//update_post_meta($post_id, '_product_url', home_url(sprintf(
			//	'/?redirect_prodid=%s',
			//	$this->the_plugin->prodid_set($retProd['ASIN'], self::$provider, 'add')
			//)));
			if ( isset($retProd['DetailPageURL']) )
				update_post_meta($post_id, '_wwcEbyAff_product_url', $retProd['DetailPageURL']);
			
			if ( isset($retProd['__extra']) && !empty($retProd['__extra']) ) {
				update_post_meta($post_id, '_wwcEbyAff_extra', $retProd['__extra']);
			}
		}

		public function attrclean_splitTitle($title) {
			return $title;
		}
		

		/**
		 * Product Price - Update november 2014
		 */
		public function productPriceSetMeta( $thisProd, $post_id='', $return=true ) {
			return array();
		}

		public function productPriceSetRegularSaleMeta( $post_id, $type, $newMetas=array() ) {
			return array();
		}

		public function productPriceGetRegularSaleStatus( $post_id, $type='both' ) {
			return array();
		}
	

		/**
		 * Octomber 2015 - new plugin functions
		 */
		/**
		 * pms: array(
		 * 		prod_id		: (string) ebay product id
		 * 		prod_link	: (string) ebay product page url
		 * )
		 */
		public function get_product_link( $pms=array() ) {
			extract($pms);

			$__ = $this->the_plugin->cur_provider;
			$this->the_plugin->cur_provider = 'ebay';

			$ret = $this->aaEbayWS->get_product_link( array(
				'prod_id'			=> $prod_id,
				'prod_link'			=> $prod_link,
				'globalid'			=> $this->the_plugin->main_aff_site(), //(ex: EBAY-US)
				'affid'				=> $this->the_plugin->main_aff_id(), //(ex: 01234)
			));

			$this->the_plugin->cur_provider = $__;

			return $ret;
		}

		public function get_countries() {
			return $this->aaEbayWS->get_countries();
		}
		
		// key: country || main_aff_id
		// same results not matter the key value; made like this for compatibility with amazon helper!
		public function get_country_name( $country, $key='country' ) {
			$country = $this->aaEbayWS->get_location( strtoupper($country), 'globalid', 'sitename' );
			return $country;
		}

		public function get_product_extra( $post_id ) {
			$extra = array_merge(array('_aiowaff_prodtype'), array(
				'_sales_rank', '_aiowaff_extra',
			));
			
			$ret = array();
			foreach ($extra as $meta) {
				$ret["$meta"] = get_post_meta($post_id, $meta, true);
			}
			return $ret;
		}


		/**
		 * search products by pages
		 * input(pms): array(
		 * 		requestData			: array,
		 * 		parameters			: array,
		 * 		_optionalParameters	: array,
		 * 		page				: int
		 * )
		 * return: array(
		 * 		response			: array
		 * )
		 */
		public function api_search_bypages( $pms=array() ) {
			extract($pms);
 
	      	$this->aaEbayWS
	      		->category( $requestData['category_id'] )
				->perPage(20)
	      		->page( $page );

			if( isset($_optionalParameters) && count($_optionalParameters) > 0 ){
				//if ( isset($_optionalParameters['BrowseNode']) ) {
				//	unset( $_optionalParameters['BrowseNode'] );
				//}
				foreach ($_optionalParameters as $key => $val) {
					if ( in_array($val, array('true', 'false')) ) {
						$_optionalParameters["$key"] = (bool) $val;
					}
				}
				$this->aaEbayWS
					->optionalParameters( $_optionalParameters );
			}
			//var_dump('<pre>',$this->aaEbayWS,'</pre>');
			
	      	$response = $this->aaEbayWS
	      		->search( isset($parameters['keyword']) ? $parameters['keyword'] : '' );
			//var_dump('<pre>',$response,'</pre>'); die;
			
            return array(
            	'response' 		=> $response,
			);
		}

		/**
		 * search products by asins list
		 * input(pms): array(
		 * 		asins				: array,
		 * )
		 * return: array(
		 * 		response			: array
		 * )
		 */
		public function api_search_byasin( $pms=array() ) {
			extract($pms);

			$response = $this->aaEbayWS
				->lookup( implode(",", $asins) );
			//var_dump('<pre>',$response,'</pre>'); die;
                    
            return array(
            	'response' 		=> $response,
			);
		}

		/**
		 * format api response results
		 * input(pms): array(
		 * 		requestData			: array,
		 * 		response			: array,
		 * )
		 * return: array(
		 * 		requestData			: array,
		 * 		response			: array,
		 * )
		 */
		public function api_format_results( $pms=array() ) {
			extract($pms);
			
			$operation = '';
			if ( isset($response['searchResult'], $response['searchResult']['item'])
				&& !empty($response['searchResult'])
				&& !empty($response['searchResult']['item']) ) {

				$operation = 'search';
			}
   
			if ( 'search' == $operation ) {
				$rsp = $this->api_search_set_stats(array(
					'requestData'				=> $requestData,
					'response'					=> $response,
				));
				$requestData = $rsp['requestData'];
 
				$_response = array();
				foreach ( $response['searchResult']['item'] as $key => $value){
					$value['__isfrom'] = 'search';
					$_response["$key"] = $value;
				}
			}
			else {
	            // verify array of Items or array of Item elements
	            if ( isset($response['Item']['ItemID']) ) {
					$response['Item'] = array( $response['Item'] );
	            }
	
				$_response = array();
				foreach ( $response['Item'] as $key => $value){
					//unset($value['Description'], $value['ShipToLocations'], $value['ExcludeShipToLocation']);
					$_response["$key"] = $value;
					$_response["$key"]['__isfrom'] = 'details';
					
					//if ( isset($value['ItemID']) && '281829771857' == $value['ItemID'] ) {
					//	var_dump('<pre>',$value,'</pre>'); 
					//}
				}
			}
			//var_dump('<pre>', $_response, '</pre>'); die('debug...');

			return array(
				'requestData'	=> $requestData,
				'response'		=> $_response,
			);
		}
		
		/**
		 * search results validation
		 * input(pms): array(
		 * 		results				: array,
		 * )
		 * return: array(
		 * 		status				: boolean,
		 * 		nbpages				: int,
		 * )
		 */
		public function api_search_validation( $pms=array() ) {
			extract($pms);
  
			$status = true;
			$nbpages = 0;
            if ( !isset($results['result'], $results['result']['TotalResults'], $results['result']['NbPagesSelected'])
                || count($results) < 2 ) {
				$status = false;
			} else {
				$nbpages = (int) $results['result']['NbPagesSelected'];
			}
			
			return array(
				'status'		=> $status,
				'nbpages'		=> $nbpages,
			);
		}
		
		/**
		 * search products by pages: get search stats!
		 * input(pms): array(
		 * 		results				: array,
		 * )
		 * return: array(
		 * 		stats				: array,
		 * )
		 */
		public function api_search_get_stats( $pms=array() ) {
			extract($pms);

			return array(
				'stats'	=> array(
					'TotalResults'			=> $results['result']['TotalResults'],
					'NbPagesSelected'		=> $results['result']['NbPagesSelected'],
					'TotalPages'			=> $results['result']['TotalPages'],
				)
			);
		}
		
		/**
		 * search products by pages: set search stats!
		 * input(pms): array(
		 * 		requestData			: array, 
		 * 		response			: array,
		 * )
		 * return: array(
		 * 		requestData			: array, 
		 * 		stats				: array,
		 * )
		 */
		public function api_search_set_stats( $pms=array() ) {
			extract($pms);

			{
				$totalItems = 0; $totalPages = 0;
				if ( isset($response['paginationOutput'])
					&& !empty($response['paginationOutput']) ) {
						
					$pg = $response['paginationOutput'];

					$nbitems_per_page = isset($pg['entriesPerPage']) && !empty($pg['entriesPerPage'])
						? (int) $pg['entriesPerPage'] : 20;
					
					$totalPages = isset($pg['totalPages']) ? $pg['totalPages'] : 0;
					$totalItems = isset($pg['totalEntries']) ? $pg['totalEntries'] : 0;

					if ( !empty($totalItems) && empty($totalPages) ) {
						$totalPages = $totalItems > 0 ? ceil( $totalItems / $nbitems_per_page ) : 0;
					}
					if ( !empty($totalPages) && empty($totalItems) ) {
						$totalItems = (int) ($totalPages * $nbitems_per_page);
					}
				}

				if ( isset($totalPages, $requestData['nbpages'])
					&& $totalPages > 0
	            	&& (int) $totalPages < $requestData['nbpages'] ) {
   
	                $requestData['nbpages'] = (int) $totalPages;
	                // don't put this validated nbpages in $__cacheSearchPms, because the cache file could not be recognized then!
				}
			}
			
			return array(
				'requestData'	=> $requestData,
				'stats'			=> array(
					'TotalResults'			=> $totalItems,
					'TotalPages'			=> $totalPages,
				)
			);
		}
		
		/**
		 * search products by pages: get page asins list from cache file! 
		 * input(pms): array(
		 * 		page_content		: array,
		 * )
		 * return: array(
		 * 		asins				: int,
		 * )
		 */
		public function api_cache_get_page_asins( $pms=array() ) {
			extract($pms);
			
			$asins = $page_content['result']['items'];
			return array(
				'asins'		=> $asins,
			);
		}
		
		/**
		 * search products by pages: set page content as list of asins! 
		 * input(pms): array(
		 * 		requestData			: array, 
		 * 		content				: array,
		 * 		old_content			: array,
		 * 		cachename			: object,
		 * 		page				: int,
		 * )
		 * return: array(
		 * 		dataToSave			: array,
		 * )
		 */
		public function api_cache_set_page_content( $pms=array() ) {
			extract($pms);

			$response = $content;
				
			$dataToSave = array();
			if ( !empty($old_content) ) {
				$dataToSave = $old_content;
			} else {

				$rsp = $this->api_search_set_stats(array(
					'requestData'				=> $requestData,
					'response'					=> $response,
				));
				$stats = $rsp['stats'];

				$dataToSave['result']['TotalResults'] = $stats['TotalResults'];
            	$dataToSave['result']['TotalPages'] = $stats['TotalPages'];
                $dataToSave['result']['NbPagesSelected'] = $cachename->params['nbpages'];
			}

            if ( is_array($content) && !isset($content['__notused__']) ) {

				$rsp = $this->api_format_results(array(
					'requestData'			=> $requestData,
					'response'				=> $response,
				));

				$dataToSave["$page"] = array();
				
				// 1 item found only
				if ( $dataToSave['result']['TotalResults'] == 1 && !isset($rsp['response'][0]) ) {
					$rsp['response'] = array($rsp['response']);
				}

				foreach ($rsp['response'] as $key => $value) {
					$product = $this->build_product_data( $value );
					if ( !empty($product['ASIN']) ) {
						$dataToSave["$page"]['result']['items']["$key"] = $product['ASIN'];
					}
				}
			}			

			return array(
				'dataToSave'		=> $dataToSave,
			);
		}
	
	}
}