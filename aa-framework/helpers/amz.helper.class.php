<?php
/**
 *	Author: Ali
 *	Name: 	
 *	
**/
! defined( 'ABSPATH' ) and exit;

if(class_exists('aiowaffAmazonHelper') != true) {
	class aiowaffAmazonHelper extends aiowaff
	{
		private $the_plugin = null;
		public $aaAmazonWS = null;
		public $amz_settings = array();
		
		static protected $_instance;
        
        const MSG_SEP = '—'; // messages html bullet // '&#8212;'; // messages html separator
        
        private static $provider = 'amazon';
		
		
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
  
			// create a instance for amazon WS connections
			$this->setupAmazonWS();
			
			// ajax actions
			add_action('wp_ajax_aiowaffCheckAmzKeys', array( $this, 'check_amazon') );
			add_action('wp_ajax_aiowaffImportProduct', array( $this, 'getProductDataFromAmazon' ), 10, 2);
			
			add_action('wp_ajax_aiowaffStressTest', array( $this, 'stress_test' ));
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
					
                    $product = $this->aaAmazonWS->responseGroup('Large,ItemAttributes,Offers,Reviews')->optionalParameters(array('MerchantId' => 'All'))->lookup( $asin ); 

					if($product['Items']["Request"]["IsValid"] == "True"){

                        $thisProd = isset($product['Items']['Item']) ? $product['Items']['Item'] : array();
						if ( !empty($thisProd) ) {
							
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
		
		public function check_amazon()
		{
			$status = 'valid';
			$msg = '';
	        try {
	            // Do a test connection
	        	$tryRequest = $this->aaAmazonWS->category('DVD')->page(1)->responseGroup('Images')->search("Matrix");

	        } catch (Exception $e) {
	            // Check 
	            if (isset($e->faultcode)) {
	            	
					$msg = $e->faultcode . ": " . $e->faultstring; 
	                $status = 'invalid';
	            }
	        }
			
        	die(json_encode(array(
				'status' => $status,
				'msg' => $msg
			)));
		}
		
		private function convertMainAffIdInCountry( $main_add_id='' )
		{
			if( $main_add_id == 'com' ) return 'US';
			
			return strtoupper( $main_add_id );
		}
		
		public function getAmazonCategs()
		{
			$country = $this->convertMainAffIdInCountry( $this->amz_settings['main_aff_id'] );
			$csv = $categs = array();
		
			// try to read the plugin_root/assets/amazon/browsenodes.csv file
			$csv_file_content = file_get_contents( $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'assets/amazon/browsenodes.csv' );
			if( trim($csv_file_content) != "" ){
				$rows = explode("\r", $csv_file_content);
				if( count($rows) > 0 ){
					foreach ($rows as $key => $value) {
						$csv[] = explode(",", $value);
					}
				}
			}
			 
			// find current country in first row 
			$pos = 0;
			if( count($csv[0]) > 0 ){
				foreach ($csv[0] as $key => $value) {
					if( strtoupper($country) == strtoupper($value) ){
						$pos = $key;
					}
				}
			}
			
			if( $pos > 0 && count($csv) > 0 ){
				foreach ($csv as $key => $value) {
					// skip the header row	
					if( $key == 0 ) continue;
					
					if( isset($value[$pos]) && trim($value[$pos]) != "" ){
						$categs[$value[0]] = $value[$pos];
					}
				}
			}
			
			return $categs;  
		}

		public function getAmazonItemSearchParameters()
		{
			$country = $this->convertMainAffIdInCountry( $this->amz_settings['main_aff_id'] );
			$csv = $categs = array();
			
			
			// try to read the plugin_root/assets/amazon/searchindexParam-{country}.csv file
			// check if file exists
			if( !is_file( $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'assets/amazon/searchindexParam-' . ( $country ) . '.csv' ) ){
				die( 'Unable to load file: ' . $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'assets/amazon/searchindexParam-' . ( $country ) . '.csv' );
			}
			
        	//$csv_file_content = $this->the_plugin->wp_filesystem->get_contents( $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'assets/searchindexParam-' . ( $country ) . '.csv' );
        	$csv_file_content = file_get_contents( $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'assets/amazon/searchindexParam-' . ( $country ) . '.csv' );
			if( trim($csv_file_content) != "" ){
				$rows = explode("\r", $csv_file_content);
				 
				if( count($rows) > 0 ){
					foreach ($rows as $key => $value) {
						$csv[] = explode(",", trim($value));
					}
				}
			}
			
			if( count($csv) > 0 ){
				foreach ($csv as $key => $value) {
					$categs[$value[0]] = explode(":", trim($value[1]));
				}
			}
			
			return $categs;  
		}
		
		public function getAmazonSortValues()
		{
			$country = $this->convertMainAffIdInCountry( $this->amz_settings['main_aff_id'] );
			$csv = $categs = array();
			
			
			// try to read the plugin_root/assets/amazon/searchindexParam-{country}.csv file
			// check if file exists
			if( !is_file( $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'assets/amazon/sortvalues-' . ( $country ) . '.csv' ) ){
				die( 'Unable to load file: ' . $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'assets/amazon/sortvalues-' . ( $country ) . '.csv' );
			}
			
        	//$csv_file_content = $this->the_plugin->wp_filesystem->get_contents( $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'assets/amazon/sortvalues-' . ( $country ) . '.csv' );
        	$csv_file_content = file_get_contents( $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'assets/amazon/sortvalues-' . ( $country ) . '.csv' );
 
			if( trim($csv_file_content) != "" ){
				$rows = explode("\r", $csv_file_content);
				 
				if( count($rows) > 0 ){
					foreach ($rows as $key => $value) {
						$csv[] = explode(",", trim($value));
					}
				}
			}
			
			if( count($csv) > 0 ){
				foreach ($csv as $key => $value) {
					$categs[$value[0]] = explode(":", trim($value[1]));
				}
			}
			  
			return $categs;  
		}
		
		private function setupAmazonWS()
		{
			// load the amazon webservices client class
			require_once( $this->the_plugin->cfg['paths']['plugin_dir_path'] . '/lib/scripts/amazon/aaAmazonWS.class.php' );
			
			$this->the_plugin->cur_provider = self::$provider;
			
			// create new amazon instance
			$this->aaAmazonWS = new aaAmazonWS(
				$this->amz_settings['AccessKeyID'],
				$this->amz_settings['SecretAccessKey'],
				$this->amz_settings['country'],
				$this->the_plugin->main_aff_id()
			);
            $this->aaAmazonWS->set_the_plugin( $this->the_plugin );
		}
		
		public function browseNodeLookup( $nodeid )
		{
			$ret = $this->aaAmazonWS->responseGroup('BrowseNodeInfo')->browseNodeLookup( $nodeid );
            
            return $ret;
		}
		
		public function updateProductReviews( $post_id=0 )
		{
			
			// get product ASIN by post_id 
			$asin = $this->the_plugin->get_post_meta( $post_id, '_amzASIN', true, false );
			
			$product = $this->aaAmazonWS->responseGroup('Reviews')->optionalParameters(array('MerchantId' => 'All'))->lookup( $asin );
            
			if($product['Items']["Request"]["IsValid"] == "True"){
				$thisProd = isset($product['Items']['Item']) ? $product['Items']['Item'] : array();
				if (isset($product['Items']['Item']) && count($product['Items']['Item']) > 0){
					$reviewsURL = $thisProd['CustomerReviews']['IFrameURL'];
					if( trim($reviewsURL) != "" ){
						
						$tab_data = array();
						$tab_data[] = array(
							'id' => 'amzAff-customer-review',
							'content' => '<iframe src="' . ( $reviewsURL ) . '" width="100%" height="450" frameborder="0"></iframe>'
						); 
						
						update_post_meta( $post_id, 'amzaff_woo_product_tabs', $tab_data );
					}
				}
			}
			
			return $reviewsURL;  
		}
		
        /**
         * Get Product From Amazon
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
            $your_products = $this->the_plugin->getAllProductsMeta('array', '_amzASIN', false, 'all');
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
        			$aaAmazonWS = $this->aaAmazonWS;

        			// create request by ASIN
        			$product = $aaAmazonWS->responseGroup('Large,ItemAttributes,OfferFull,Variations,Reviews,PromotionSummary,SalesRank')->optionalParameters(array('MerchantId' => 'All'))->lookup($asin);
                  	 
                    $respStatus = $this->is_amazon_valid_response( $product );
                    if ( $respStatus['status'] != 'valid' ) { // error occured!
          			    
          			    $_msg[] = 'Invalid '.self::$provider.' response ( ' . $respStatus['code'] . ' - ' . $respStatus['msg'] . ' )';
                        
                        $ret = array_merge($ret, array('msg' => implode('<br />', $_msg)));
                        if ( $retType == 'return' ) { return $ret; }
                        else { die( json_encode( $ret ) ); }
                
                    } else { // success!
        
        				$thisProd = $product['Items']['Item'];
        				if ( 1 ) {
    
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
            $ret = array(
                'status'        => 'invalid',
                'msg'           => 'unknown message.',
                'html'          => 'unknown message.',
                'code'          => -1,
            );

            // parse amazon response
            if ( !isset($response['Items']['Request']['IsValid']) ) {

                $msg = 'invalid '.self::$provider.' response.';
                return array_merge($ret, array(
                    'msg'       => $msg,
                    'html'      => $msg,
                    'code'      => 1,
                ));
            }

            if ( $response['Items']['Request']['IsValid'] == 'False' ) {
        
                if ( isset($response['Items']['Request']['Errors']['Error']['Code']) ) {
                    $msg = self::$provider.' error id: <bold>' . ( $response['Items']['Request']['Errors']['Error']['Code'] ) . '</bold>: ' . ( $response['Items']['Request']['Errors']['Error']['Message'] );

                } else if ( is_array($response['Items']['Request']['Errors']['Error']) ) {
                    $_msg = array();
                    $_msg[] = self::$provider.' error id:';
                    foreach ($response['Items']['Request']['Errors']['Error'] as $err_key => $err_val) {
                        $_msg[] = '<bold>' . ( $err_val['Code'] ) . '</bold>: ' . ( $err_val['Message'] );
                    }
                    $msg = implode('<br />', $_msg);
                    
                } else {
                    $msg = 'unknown '.self::$provider.' error.';
                }
                return array_merge($ret, array(
                    'msg'       => $msg,
                    'html'      => $msg,
                    'code'      => 2, 
                ));
            }

            // No products found!
            //isset($response['Items']['Item']) && count($response['Items']['Item']) > 0
            if ( ( count($response['Items']) <= 0 )
                || !isset($response['Items']['Item'])
                || ( count($response['Items']['Item']) <= 0 ) ) {

                $amz_code = '';
                if ( isset($response['Items']['Request']['Errors']['Error']['Code']) ) {
                    $amz_code = $response['Items']['Request']['Errors']['Error']['Code'];

                    $msg = self::$provider.' error id: <bold>' . ( $response['Items']['Request']['Errors']['Error']['Code'] ) . '</bold>: ' . ( $response['Items']['Request']['Errors']['Error']['Message'] );
                    switch ($response['Items']['Request']['Errors']['Error']['Code']) {
                        case 'AWS.ECommerceService.NoExactMatches':
                            $msg = 'Sorry, your search did not return any results.';
                            break;

                        case 'AWS.InvalidParameterValue':
                            break;
                    }
                    
                } else if ( is_array($response['Items']['Request']['Errors']['Error']) ) {
                    $_msg = array();
                    $_msg[] = self::$provider.' error id:';
                    foreach ($response['Items']['Request']['Errors']['Error'] as $err_key => $err_val) {
                        $_msg[] = '<bold>' . ( $err_val['Code'] ) . '</bold>: ' . ( $err_val['Message'] );
                    }
                    $msg = implode('<br />', $_msg);
                    
                } else {
                    $msg = 'no products found.';
                }
                return array_merge($ret, array(
                    'msg'       => $msg,
                    'html'      => $msg,
                    'code'      => 3,
                    'amz_code'  => $amz_code,
                ));
            }

            // success   
            return array_merge($ret, array(
                'status'        => 'valid',
                'msg'           => 'valid message.',
                'html'          => 'valid message.',
                'code'          => 0,
            ));
        }

        // product data is valid
        public function is_valid_product_data( $product=array(), $from='' ) {
            if ( empty($product) || !is_array($product) ) return false;
            
            $rules = isset($product['ASIN']) && !empty($product['ASIN']);
            $rules = $rules && 1;
            return $rules ? true : false;
        }

        // build single product data based on amazon request array
        public function build_product_data( $item=array(), $old_item=array() ) {

            // summarize product details
            $retProd = array(
                'ASIN'                  => isset($item['ASIN']) ? $item['ASIN'] : '',
                'ParentASIN'            => isset($item['ParentASIN']) ? $item['ParentASIN'] : '',
                
                'ItemAttributes'        => isset($item['ItemAttributes']) ? $item['ItemAttributes'] : array(),
                'Title'                 => isset($item['ItemAttributes']['Title']) ? stripslashes($item['ItemAttributes']['Title']) : '',
                'SKU'                   => isset($item['ItemAttributes']['SKU']) ? $item['ItemAttributes']['SKU'] : '',
                'Feature'               => isset($item['ItemAttributes']['Feature']) ? $item['ItemAttributes']['Feature'] : '',
                'Brand'                 => isset($item['ItemAttributes']['Brand']) ? $item['ItemAttributes']['Brand'] : '',
                'Binding'               => isset($item['ItemAttributes']['Binding']) ? $item['ItemAttributes']['Binding'] : '',
                //'ListPrice'           => isset($item['ItemAttributes']['ListPrice']['FormattedPrice']) ? $item['ItemAttributes']['ListPrice']['FormattedPrice'] : '',
                
                'Variations'            => isset($item['Variations']) ? $item['Variations'] : array(),
                'VariationSummary'      => isset($item['VariationSummary']) ? $item['VariationSummary'] : array(),
                'BrowseNodes'           => isset($item['BrowseNodes']) ? $item['BrowseNodes'] : array(),
                'DetailPageURL'         => isset($item['DetailPageURL']) ? $item['DetailPageURL'] : '',
                'SalesRank'             => isset($item['SalesRank']) ? $item['SalesRank'] : 999999,

                'SmallImage'            => isset($item['SmallImage']['URL']) ? trim( $item['SmallImage']['URL'] ) : '',
                'LargeImage'            => isset($item['LargeImage']['URL']) ? trim( $item['LargeImage']['URL'] ) : '',

                'Offers'                => isset($item['Offers']) ? $item['Offers'] : '',
                'OfferSummary'          => isset($item['OfferSummary']) ? $item['OfferSummary'] : '',
                'EditorialReviews'      => isset($item['EditorialReviews']['EditorialReview']['Content'])
                    ? $item['EditorialReviews']['EditorialReview']['Content'] : '',
                    
				'hasGallery'			=> 'false',
            );
			
			// try to rebuid the description if is empty
			if( trim($retProd["EditorialReviews"]) == "" ){
				if( isset($item['EditorialReviews']['EditorialReview']) && count($item['EditorialReviews']['EditorialReview']) > 0 ){
					
					$new_description = array();
					foreach ($item['EditorialReviews']['EditorialReview'] as $desc) {
						if( isset($desc['Content']) && isset($desc['Source']) ){
							//$new_description[] = '<h3>' . ( $desc['Source'] ) . ':</h3>';
							$new_description[] = $desc['Content'] . '<br />';
						}
					}
				}
				
				if( isset($new_description) && count($new_description) > 0 ){
					$retProd["EditorialReviews"] = implode( "\n", $new_description );
				}
			}
			
            // CustomerReviews url
            if ( isset($item['CustomerReviews'], $item['CustomerReviews']['HasReviews'])
                && $item['CustomerReviews']['HasReviews'] ) {
                $retProd['CustomerReviewsURL'] = $item['CustomerReviews']['IFrameURL'];
            }

            // Images
            $retProd['images'] = $this->build_images_data( $item );
            if ( empty($retProd['images']['large']) ) {
                // no images found - if has variations, try to find first image from variations
                $retProd['images'] = $this->get_first_variation_image( $item );
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

			// has gallery: get gallery images
			if ( isset($item['ImageSets']) && count($item['ImageSets']) > 0 ) {
				foreach ( $item['ImageSets']["ImageSet"] as $key => $value ) {
					if ( isset($value['LargeImage']['URL']) ) {
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
            if ( isset($item['LargeImage']['URL']) ) {
               $retProd['large'][] = $item['LargeImage']['URL'];
            }
            if ( isset($item['SmallImage']['URL']) ) {
               $retProd['small'][] = $item['SmallImage']['URL'];
            }

            // get gallery images
            if (isset($item['ImageSets'], $item['ImageSets']['ImageSet']) && count($item['ImageSets']["ImageSet"]) > 0) {
                
                // hack if have only 1 item
                if( isset($item['ImageSets']['ImageSet']['SwatchImage']) ){
                    $_tmp = $item['ImageSets']["ImageSet"];
                    $item['ImageSets']["ImageSet"] = array();
                    $item['ImageSets']["ImageSet"][0] = $_tmp;  
                }

                $count = 0;
                foreach ($item['ImageSets']["ImageSet"] as $key => $value) {
                    
                    if( isset($value['LargeImage']['URL']) ){
                        $retProd['large'][] = $value['LargeImage']['URL'];
                    }
                    if( isset($value['SmallImage']['URL']) ){
                        $retProd['small'][] = $value['SmallImage']['URL'];
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
        public function get_first_variation_image( $retProd ) {

            $images = array( 'large' => array(), 'small' => array() );

            if ( isset($retProd['Variations'], $retProd['Variations']['TotalVariations'], $retProd['Variations']['Item']) ) {
                $total = (int)$retProd['Variations']['TotalVariations'];
                
                $variations = array();
                if ($total <= 1 || isset($retProd['Variations']['Item']['ASIN'])) { // --fix 2015.03.19
                    $variations[] = $retProd['Variations']['Item'];
                } else {
                    $variations = (array) $retProd['Variations']['Item'];
                }
 
                // Loop through the variation
                foreach ($variations as $variation_item) {
                    
                    $images = $this->build_images_data( $variation_item );
                    if ( !empty($images['large']) ) {
                        return $images;
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
	        
	        // Count the top browsenodes
	        $topBrowseNodeCounter = 0;
			
			if ( !isset($browseNodes['BrowseNode']) ) {
	        	// Delete the product_cat_children
	        	// This is to force the creation of a fresh product_cat_children
	        	//delete_option( 'product_cat_children' );
			
				return array();
			}

	        // Check if we have multiple top browseNode
	        if( is_array( $browseNodes['BrowseNode'] ) )
	        {
	        	// check if is has only one key
	        	if( isset($browseNodes["BrowseNode"]["BrowseNodeId"]) && trim($browseNodes["BrowseNode"]["BrowseNodeId"]) != "" ){
	        		$_browseNodes = $browseNodes["BrowseNode"];
	        		$browseNodes = array();
					$browseNodes['BrowseNode'][0] = $_browseNodes;
					unset($_browseNodes);
	        	}
    
	            foreach( $browseNodes['BrowseNode'] as $browseNode )
	            {
	                // Create a clone
	                $currentNode = $browseNode;
	
	                // Track the child layer
	                $childLayer = 0;
	
	                // Inifinite loop, since we don't know how many ancestral levels
	                while( true )
	                {
	                    $validCat = true;
	                    
	                    // Replace html entities
	                    $dmCatName = str_replace( '&', 'and', $currentNode['Name'] );
	                    $dmCatSlug = sanitize_title( str_replace( '&', 'and', $currentNode['Name'] ) );
						
						$dmCatSlug_id = '';
						if ( is_object($currentNode) && isset($currentNode->BrowseNodeId) )
	                    	$dmCatSlug_id = ($currentNode->BrowseNodeId);
						else if ( is_array($currentNode) && isset($currentNode['BrowseNodeId']) )
							$dmCatSlug_id = ($currentNode['BrowseNodeId']);

						// $dmCatSlug = ( !empty($dmCatSlug_id) ? $dmCatSlug_id . '-' . $dmCatSlug : $dmCatSlug );

	                    $dmTempCatSlug = sanitize_title( str_replace( '&', 'and', $currentNode['Name'] ) );
	                    
	                    if( $dmTempCatSlug == 'departments' ) $validCat = false;
	                    if( $dmTempCatSlug == 'featured-categories' ) $validCat = false;
	                   	if( $dmTempCatSlug == 'categories' ) $validCat = false;
						if( $dmTempCatSlug == 'products' ) $validCat = false;
	                    if( $dmTempCatSlug == 'all-products') $validCat = false;
	
	                    // Check if we will make the cat
	                    if( $validCat ) {
	                        $categories[0][] = array(
	                            'name' => $dmCatName,
	                            'slug' => $dmCatSlug
	                        );
	                    }
	
	                    // Check if the current node has a parent
	                    if( isset($currentNode['Ancestors']['BrowseNode']['Name']) )
	                    {
	                        // Set the next Ancestor as the current node
	                        $currentNode = $currentNode['Ancestors']['BrowseNode'];
	                        $childLayer++;
	                        continue;
	                    }
	                    else
	                    {
	                        // There's no more ancestors beyond this
	                        break;
	                    }
	                } // end infinite while
	                
	                // Increment the tracker
	                $topBrowseNodeCounter++;
	            } // end foreach
	        }
	        else
	        {
	            // Handle single branch browsenode
	            
	            // Create a clone
	            $currentNode = isset($browseNodes['BrowseNode']) ? $browseNodes['BrowseNode'] : array();
	            
	            // Inifinite loop, since we don't know how many ancestral levels
	            while (true) 
	            {
	                // Always true unless proven
	                $validCat = true;
	                
	                // Replace html entities
	                $dmCatName = str_replace( '&', 'and', $currentNode['Name'] );
	                $dmCatSlug = sanitize_title( str_replace( '&', 'and', $currentNode['Name'] ) );
					$dmCatSlug_id = $currentNode['BrowseNodeId'];
	                // $dmCatSlug = ( !empty($dmCatSlug_id) ? $dmCatSlug_id . '-' . $dmCatSlug : $dmCatSlug );  
	                
	                $dmTempCatSlug = sanitize_title( str_replace( '&', 'and', $currentNode['Name'] ) );
	                
					if( $dmTempCatSlug == 'departments' ) $validCat = false;
                    if( $dmTempCatSlug == 'featured-categories' ) $validCat = false;
                   	if( $dmTempCatSlug == 'categories' ) $validCat = false;
					if( $dmTempCatSlug == 'products' ) $validCat = false;
                    if( $dmTempCatSlug == 'all-products') $validCat = false;
	                
	                // Check if we will make the cat
	                if( $validCat ) {
	                    $categories[0][] = array(
	                        'name' => $dmCatName,
	                        'slug' => $dmCatSlug
	                    );
	                }
	
	                // Check if the current node has a parent
	                if (isset($currentNode['Ancestors']['BrowseNode']['Name'])) 
	                {
	                    // Set the next Ancestor as the current node
	                    $currentNode = $currentNode['Ancestors']['BrowseNode'];
	                    continue;
	                } 
	                else 
	                {
	                    // There's no more ancestors beyond this
	                    break;
	                }
	            } // end infinite while
	                
	        } // end if browsenode is an array
	        
	        // Tracker
	        $catCounter = 0;
	        
	        // Make the parent at the top
	        foreach( $categories as $category )
	        {
	            $categories[$catCounter] = array_reverse( $category );
	            $catCounter++;
	        }
	        
	        // Current top browsenode
	        $categoryCounter = 0;
	        
	        // Import only parent category from Amazon
			if( isset( $this->amz_settings["create_only_parent_category"] ) && $this->amz_settings["create_only_parent_category"] != '' && $this->amz_settings["create_only_parent_category"] == 'yes') {
				$categories = array( array( $categories[0][0] ) );
			}
			
			// Loop through each of the top browsenode
	        foreach( $categories as $category )
	        {
	            // The current node
	            $nodeCounter = 0;
	            // Loop through the array of the current browsenode
	            foreach( $category as $node )
	            {
	                // Check if we're at parent
	                if( $nodeCounter === 0 )
	                {                
	                    // Check if term exists
	                    $checkTerm = term_exists( str_replace( '&', 'and', $node['slug'] ), $wooTaxonomy );
	                    if( empty( $checkTerm ) )
	                    {
	                        // Create the new category
	                       $newCat = wp_insert_term( $node['name'], $wooTaxonomy, array( 'slug' => $node['slug'] ) );
	                       
	                       // Add the created category in the createdCategories
	                       // Only run when the $newCat is an error
	                       if( gettype($newCat) != 'object' ) {
	                       		$createdCategories[] = $newCat['term_id'];
	                       }       
	                    }
	                    else
	                    {
	                        // if term already exists add it on the createdCats
	                        $createdCategories[] = $checkTerm['term_id'];
	                    }
	                }
	                else
	                {  
	                    // The parent of the current node
	                    $parentNode = $categories[$categoryCounter][$nodeCounter - 1];
	                    // Get the term id of the parent
	                    $parent = term_exists( str_replace( '&', 'and', $parentNode['slug'] ), $wooTaxonomy );
	                    
	                    // Check if the category exists on the parent
	                    $checkTerm = term_exists( str_replace( '&', 'and', $node['slug'] ), $wooTaxonomy );
	                    
	                    if( empty( $checkTerm ) )
	                    {
	                        $newCat = wp_insert_term( $node['name'], $wooTaxonomy, array( 'slug' => $node['slug'], 'parent' => $parent['term_id'] ) );
	                        
	                        // Add the created category in the createdCategories
	                        $createdCategories[] = $newCat['term_id'];
	                    }
	                    else
	                    {
	                        $createdCategories[] = $checkTerm['term_id'];
	                    }
	                }
	                
	                $nodeCounter++;
	            } 
	    
	            $categoryCounter++;
	        } // End top browsenode foreach
	        
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
	 
	        // convert Amazon attributes into woocommerce attributes
	        $_product_attributes = array();
	        $position = 0;
			
			$allowedAttributes = 'all';

			//if ( isset($this->amz_settings['selected_attributes'])
			//	&& !empty($this->amz_settings['selected_attributes'])
			//	&& is_array($this->amz_settings['selected_attributes']) )
			//	$allowedAttributes = (array) $this->amz_settings['selected_attributes'];
				
	        foreach( $itemAttributes as $key => $value )
	        { 
	            if (!is_object($value)) 
	            {
	            	//if ( is_array($allowedAttributes) ) {
					//	if ( !in_array($key, $allowedAttributes) ) {
					//		continue 1;
					//	}
					//}
					
	                // Apparel size hack
	                if($key === 'ClothingSize') {
	                    $key = 'Size';
	                }
					// don't add list price,Feature,Title into attributes
					if( in_array($key, array('ListPrice', 'Feature', 'Title') ) ) continue;
	                
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
					
	                $this->add_attribute( $post_id, $key, $value );
	            }
	        }
	        
	        // update product attribute
			update_post_meta($post_id, '_product_attributes', $_product_attributes);
			
			$this->the_plugin->get_ws_object( 'generic' )->attrclean_clean_all( 'array' ); // delete duplicate attributes
			
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

	                if (is_string($attribute_value)) {
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
			$multiply_factor =  ($this->amz_settings["country"] == 'co.jp') ? 1 : 0.01;
  
			$price_setup = (isset($this->amz_settings["price_setup"]) && $this->amz_settings["price_setup"] == 'amazon_or_sellers' ? 'amazon_or_sellers' : 'only_amazon');
			//$offers_from = ( $price_setup == 'only_amazon' ? 'Amazon' : 'All' );
			
            $prodprice = array('regular_price' => '');
 
			// list price
			$offers = array(
				'ListPrice' => isset($thisProd['ItemAttributes']['ListPrice']['Amount']) ? ($thisProd['ItemAttributes']['ListPrice']['Amount'] * $multiply_factor ) : '',
				'LowestNewPrice' => isset($thisProd['Offers']['Offer']['OfferListing']['Price']['Amount']) ? ($thisProd['Offers']['Offer']['OfferListing']['Price']['Amount'] * $multiply_factor) : '',
				'Offers'	=> isset($thisProd['Offers']) ? $thisProd['Offers'] : array()
			);
  
			if( $price_setup == 'amazon_or_sellers' && isset($thisProd['OfferSummary']['LowestNewPrice']['Amount']) ) {
				$offers['LowestNewPrice'] = ($thisProd['OfferSummary']['LowestNewPrice']['Amount'] * $multiply_factor);
			}

			$prodprice['regular_price'] = $offers['ListPrice'];

			// if regular price is empty setup offer price as regular price
			if( 
				(!isset($offers['ListPrice']) || (float)$offers['ListPrice'] == 0.00)
				|| (isset($offers['ListPrice']) && $offers['LowestNewPrice'] > $offers['ListPrice'])
			) {
				$prodprice['regular_price'] = $offers['LowestNewPrice'];
			}

			// if still don't have any regular price, try to get from VariationSummary (ex: Apparel category)
			if( !isset($prodprice['regular_price']) || (float)$prodprice['regular_price'] == 0.00 ) {
				$prodprice['regular_price'] = isset($thisProd['VariationSummary']['LowestPrice']['Amount']) ? ( $thisProd['VariationSummary']['LowestPrice']['Amount'] * $multiply_factor ) : '';
			}
  
			if ( empty($prodprice['regular_price']) || (float)$prodprice['regular_price'] <= 0.00 ) return true;
			return false;
		}

		public function productPriceUpdate( $thisProd, $post_id='', $return=true )
		{
			$multiply_factor =  ($this->amz_settings["country"] == 'co.jp') ? 1 : 0.01;

            $price_setup = (isset($this->amz_settings["price_setup"]) && $this->amz_settings["price_setup"] == 'amazon_or_sellers' ? 'amazon_or_sellers' : 'only_amazon');
            //$offers_from = ( $price_setup == 'only_amazon' ? 'Amazon' : 'All' );
			
			// if any of regular | sale price set to auto => no product price syncronization!
			$priceStatus = $this->productPriceGetRegularSaleStatus( $post_id );
			if ( $priceStatus['regular'] == 'selected' || $priceStatus['sale'] == 'selected' ) {
				if( $return == true ) {
					die(json_encode(array(
						'status' => 'valid',
						'data'		=> array(
							'_sale_price' => woocommerce_price( get_post_meta($post_id, '_regular_price', true) ),
							'_regular_price' => woocommerce_price( get_post_meta($post_id, '_sale_price', true) ),
							'_price_update_date' => date('F j, Y, g:i a', get_post_meta($post_id, '_price_update_date', true))
						)
					)));
				}
				return true;
			} // end priceStatus
			
			// list price
			$offers = array(
				'ListPrice' 		=> isset($thisProd['ItemAttributes']['ListPrice']['Amount']) ? ($thisProd['ItemAttributes']['ListPrice']['Amount'] * $multiply_factor ) : '',
				'LowestNewPrice' 	=> isset($thisProd['Offers']['Offer']['OfferListing']['Price']['Amount']) ? ($thisProd['Offers']['Offer']['OfferListing']['Price']['Amount'] * $multiply_factor) : '',
				'LowestPrice' 		=> isset($thisProd['VariationSummary']['LowestSalePrice']['Amount']) ? ($thisProd['VariationSummary']['LowestSalePrice']['Amount'] * $multiply_factor) : '',
				'Offers'			=> isset($thisProd['Offers']) ? $thisProd['Offers'] : array()
			);
  
			if( $price_setup == 'amazon_or_sellers' && isset($thisProd['OfferSummary']['LowestNewPrice']['Amount']) ) {
				$offers['LowestNewPrice'] = ($thisProd['OfferSummary']['LowestNewPrice']['Amount'] * $multiply_factor);
			}

			// get current product meta, update the values of prices and update it back
			$product_meta = get_post_meta( $post_id, '_product_meta', true );

			$product_meta['product']['regular_price'] = $offers['ListPrice'];

			// if regular price is empty setup offer price as regular price or lowest new price greater then list price
			if( 
				(!isset($offers['ListPrice']) || (float)$offers['ListPrice'] == 0.00)
				|| (isset($offers['ListPrice']) && $offers['LowestNewPrice'] > $offers['ListPrice'])
			) {
				$product_meta['product']['regular_price'] = $offers['LowestNewPrice'];
			}

			// if still don't have any regular price, try to get from VariationSummary (ex: Apparel category)
			if( !isset($product_meta['product']['regular_price']) || (float)$product_meta['product']['regular_price'] == 0.00 ) {
				$product_meta['product']['regular_price'] = isset($thisProd['VariationSummary']['LowestPrice']['Amount']) ? ( $thisProd['VariationSummary']['LowestPrice']['Amount'] * $multiply_factor ) : '';
			}

			if( isset($offers['LowestNewPrice']) ) {
				$product_meta['product']['sales_price'] = $offers['LowestNewPrice']; 
				// if offer price is higher than regular price, delete the offer
				if( $offers['LowestNewPrice'] >= $product_meta['product']['regular_price'] ){
					unset($product_meta['product']['sales_price']);
				}
			}
			
			if( isset($offers['LowestPrice']) && empty($product_meta['product']['sales_price']) ) {
				$product_meta['product']['sales_price'] = $offers['LowestPrice']; 
				// if offer price is higher than regular price, delete the offer
				if( $offers['LowestPrice'] >= $product_meta['product']['regular_price'] ){
					unset($product_meta['product']['sales_price']);
				}
			}

			// set product price metas!
			if ( isset($product_meta['product']['sales_price']) && !empty($product_meta['product']['sales_price']) ) {
				update_post_meta($post_id, '_sale_price', $product_meta['product']['sales_price']);
				$this->productPriceSetRegularSaleMeta($post_id, 'sale', array(
					'auto' => number_format( (float)($product_meta['product']['sales_price']), 2, '.', '')
				));
			} else { // new sale price is 0
				update_post_meta($post_id, '_sale_price', '');
				$this->productPriceSetRegularSaleMeta($post_id, 'sale', array(
					'auto' => ''
				));
			}
			update_post_meta($post_id, '_price_update_date', time());
			update_post_meta($post_id, '_regular_price', $product_meta['product']['regular_price']);
			$this->productPriceSetRegularSaleMeta($post_id, 'regular', array(
				'auto' => number_format((float)($product_meta['product']['regular_price']), 2, '.', '')
			));
			update_post_meta($post_id, '_price', (isset($product_meta['product']['sales_price']) && trim($product_meta['product']['sales_price']) != "" ? $product_meta['product']['sales_price'] : $product_meta['product']['regular_price']));

			// set product price extra metas!
			$retExtra = $this->productPriceSetMeta( $thisProd, $post_id, 'return' );

			if( $return == true ) {
				die(json_encode(array(
					'status' => 'valid',
					'data'		=> array(
						'_sale_price' => isset($product_meta['product']['sales_price']) ? woocommerce_price($product_meta['product']['sales_price']) : '-',
						'_regular_price' => woocommerce_price($product_meta['product']['regular_price']),
						'_price_update_date' => date('F j, Y, g:i a', time())
					)
				)));
			}
		}

        public function get_productPrice( $thisProd )
        {
            $multiply_factor =  ($this->amz_settings["country"] == 'co.jp') ? 1 : 0.01;
            
            $price_setup = (isset($this->amz_settings["price_setup"]) && $this->amz_settings["price_setup"] == 'amazon_or_sellers' ? 'amazon_or_sellers' : 'only_amazon');
            //$offers_from = ( $price_setup == 'only_amazon' ? 'Amazon' : 'All' );
            
            $ret = array(
                'status'                => 'valid',
                '_price'                => '',
                '_sale_price'           => '',
                '_regular_price'        => '',
                '_price_update_date'    => '',
            );
 
            // list price
            $offers = array(
                'ListPrice'         => isset($thisProd['ItemAttributes']['ListPrice']['Amount']) ? ($thisProd['ItemAttributes']['ListPrice']['Amount'] * $multiply_factor ) : '',
                'LowestNewPrice'    => isset($thisProd['Offers']['Offer']['OfferListing']['Price']['Amount']) ? ($thisProd['Offers']['Offer']['OfferListing']['Price']['Amount'] * $multiply_factor) : '',
                'LowestPrice'       => isset($thisProd['VariationSummary']['LowestSalePrice']['Amount']) ? ($thisProd['VariationSummary']['LowestSalePrice']['Amount'] * $multiply_factor) : '',
                'Offers'            => isset($thisProd['Offers']) ? $thisProd['Offers'] : array()
            );
 
            if( $price_setup == 'amazon_or_sellers' && isset($thisProd['OfferSummary']['LowestNewPrice']['Amount']) ) {
                $offers['LowestNewPrice'] = ($thisProd['OfferSummary']['LowestNewPrice']['Amount'] * $multiply_factor);
            }

            // get current product meta, update the values of prices and update it back
            $product_meta = array('product' => array());

            $product_meta['product']['regular_price'] = $offers['ListPrice'];

            // if regular price is empty setup offer price as regular price or lowest new price greater then list price
            if( 
                (!isset($offers['ListPrice']) || (float)$offers['ListPrice'] == 0.00)
                || (isset($offers['ListPrice']) && $offers['LowestNewPrice'] > $offers['ListPrice'])
            ) {
                $product_meta['product']['regular_price'] = $offers['LowestNewPrice'];
            }

            // if still don't have any regular price, try to get from VariationSummary (ex: Apparel category)
            if( !isset($product_meta['product']['regular_price']) || (float)$product_meta['product']['regular_price'] == 0.00 ) {
                $product_meta['product']['regular_price'] = isset($thisProd['VariationSummary']['LowestPrice']['Amount']) ? ( $thisProd['VariationSummary']['LowestPrice']['Amount'] * $multiply_factor ) : '';
            }

            if( isset($offers['LowestNewPrice']) ) {
                $product_meta['product']['sales_price'] = $offers['LowestNewPrice']; 
                // if offer price is higher than regular price, delete the offer
                if( $offers['LowestNewPrice'] >= $product_meta['product']['regular_price'] ){
                    unset($product_meta['product']['sales_price']);
                }
            }
            
            if( isset($offers['LowestPrice']) && empty($product_meta['product']['sales_price']) ) {
                $product_meta['product']['sales_price'] = $offers['LowestPrice']; 
                // if offer price is higher than regular price, delete the offer
                if( $offers['LowestPrice'] >= $product_meta['product']['regular_price'] ){
                    unset($product_meta['product']['sales_price']);
                }
            }

            // set product price metas!
            if ( isset($product_meta['product']['sales_price']) && !empty($product_meta['product']['sales_price']) ) {
                $ret['_sale_price'] = $product_meta['product']['sales_price'];
            } else { // new sale price is 0
                $ret['_sale_price'] = '';
            }
            $ret['_price_update_date'] = time();
            $ret['_regular_price'] = $product_meta['product']['regular_price'];
            $ret['_price'] = (isset($product_meta['product']['sales_price']) && trim($product_meta['product']['sales_price']) != "" ? $product_meta['product']['sales_price'] : $product_meta['product']['regular_price']);
 
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
	            if (count($retProd['Variations']['VariationDimensions']['VariationDimension']) == 1) {
	                $VariationDimensions[$retProd['Variations']['VariationDimensions']['VariationDimension']] = array();
	            } else {
	                // Check if VariationDimension is given
	                if(count($retProd['Variations']['VariationDimensions']['VariationDimension']) > 0 ) {
	                    foreach ($retProd['Variations']['VariationDimensions']['VariationDimension'] as $dim) {
	                        $VariationDimensions[$dim] = array();
	                    }
	                }
	            }
                
                $ret['nb_found'] = $retProd['Variations']['TotalVariations'];
	            
	            // loop through the variations
	            //if (count($retProd['Variations']['Item']) == 1) {
	            if ($retProd['Variations']['TotalVariations'] == 1) { // --fix 2015.03.19

	                $variation_item = $retProd['Variations']['Item'];
	                $VariationDimensions = $this->variation_post( $variation_item, $post_id, $VariationDimensions );
	                //$var_mode = 'create';
                    $offset ++;
	            } else {
	            	
	                // if the variation still has items 
	                //$var_mode = 'variation';
					$cc = 0;
					
	                // Loop through the variation
	                for( $cc = 1; $cc <= $variationNumber; $cc++ )
	                {
	                    // Check if there are still variations
	                    if( $offset > ((int)$retProd['Variations']['TotalVariations'] - 1) ) {
	                        break;
	                    }
	                    //else if ( $offset == ((int)$retProd['Variations']['TotalVariations'] - 1) ) {
	                    //    //$var_mode = 'create';
	                    //}
	                    
	                    // Get the specifc variation 
	                    $variation_item = $retProd['Variations']['Item'][$offset];
  
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
	        $variation_post['post_title'] = isset($variation_item['ItemAttributes']['Title']) ? $variation_item['ItemAttributes']['Title'] : '';
			$variation_post['post_status'] = 'publish';
	        $variation_post['post_type'] = 'product_variation';
	        $variation_post['post_parent'] = $post_id;
	        unset( $variation_post['ID'] );
			
	        $variation_post_id = wp_insert_post( $variation_post );
	
			$images = array();
			$images['Title'] = isset($variation_item['ItemAttributes']['Title']) ? $variation_item['ItemAttributes']['Title'] : uniqid();
            $images['images'] = $this->build_images_data( $variation_item );
			
			$this->set_product_images( $images, $variation_post_id, $post_id, 1 );
	        
			// set the product price
			$this->productPriceUpdate( $variation_item, $variation_post_id, false );
			
			// than update the metapost
			$this->set_product_meta_options( $variation_item, $variation_post_id, true );
			 
	        // Compile all the possible variation dimensions         
	        if(is_array($variation_item['VariationAttributes']['VariationAttribute']) && isset($variation_item['VariationAttributes']['VariationAttribute'][0]['Name'])) {
	        	
	            foreach ($variation_item['VariationAttributes']['VariationAttribute'] as $va) {

					// Clean
					$va['Value'] = $this->the_plugin->cleanValue( $va['Value'] );

	                $this->add_attribute( $post_id, $va['Name'], $va['Value'] );

	                $curarr = $VariationDimensions[$va['Name']];
	                $curarr[$va['Value']] = $va['Value'];
					
	                $VariationDimensions[$va['Name']] = $curarr;
	        
	                $dimension_name = $this->the_plugin->cleanTaxonomyName(strtolower($va['Name']));
	                update_post_meta($variation_post_id, 'attribute_' . $dimension_name, sanitize_title($va['Value']));  
	            }
	        } else {
	            $dmName = $variation_item['VariationAttributes']['VariationAttribute']['Name'];
	            $dmValue = $variation_item['VariationAttributes']['VariationAttribute']['Value'];
	               
				// Clean
				$dmValue = $this->the_plugin->cleanValue( $dmValue );

	            $this->add_attribute( $post_id, $dmName, $dmValue );
	                
	            $curarr = $VariationDimensions[$dmName];
	            $curarr[$dmValue] = $dmValue;
	            $VariationDimensions[$dmName] = $curarr;
	        
	            $dimension_name = $this->the_plugin->cleanTaxonomyName(strtolower($dmName));
	            update_post_meta($variation_post_id, 'attribute_' . $dimension_name, sanitize_title($dmValue));
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
			if ( $is_variation == false ){
				$tab_data = array();
				$tab_data[] = array(
					'id' => 'amzAff-customer-review',
					'content' => '<iframe src="' . ( isset($retProd['CustomerReviewsURL']) ? urldecode($retProd['CustomerReviewsURL']) : '' ) . '" width="100%" height="450" frameborder="0"></iframe>'
				);	
			}

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
				if( isset($retProd['CustomerReviewsURL']) && @trim($retProd['CustomerReviewsURL']) != "" ) 
					update_post_meta( $post_id, 'amzaff_woo_product_tabs', $tab_data );
			}
			
			// 2015, october 28 - NEW METAS!
			update_post_meta($post_id, '_amzASIN', $this->the_plugin->prodid_set($retProd['ASIN'], self::$provider, 'sub'));
			update_post_meta($post_id, '_aiowaff_prodid', $this->the_plugin->prodid_set($retProd['ASIN'], self::$provider, 'add'));
			update_post_meta($post_id, '_aiowaff_prodtype', self::$provider);
			update_post_meta($post_id, '_product_url', home_url(sprintf(
				'/?redirectAmzASIN=%s&redirect_prodid=%s',
				$this->the_plugin->prodid_set($retProd['ASIN'], self::$provider, 'sub'),
				$this->the_plugin->prodid_set($retProd['ASIN'], self::$provider, 'add')
			)));
			if ( isset($retProd['DetailPageURL']) )
				update_post_meta($post_id, '_aiowaff_product_url', $retProd['DetailPageURL']);
		}

		public function attrclean_splitTitle($title) {
			$extra = array(
				'ASIN' => 'ASIN',
				'CEROAgeRating' => 'CERO Age Rating',
				'EAN' => 'EAN',
				'EANList' => 'EAN List',
				'EANListElement' => 'EAN List Element',
				'EISBN' => 'EISBN',
				'ESRBAgeRating' => 'ESRB Age Rating',
				'HMAC' => 'HMAC',
				'IFrameURL' => 'IFrame URL',
				'ISBN' => 'ISBN',
				'MPN' => 'MPN',
				'ParentASIN' => 'Parent ASIN',
				'PurchaseURL' => 'Purchase URL',
				'SKU' => 'SKU',
				'UPC' => 'UPC',
				'UPCList' => 'UPC List',
				'UPCListElement' => 'UPC List Element',
				'URL' => 'URL',
				'URLEncodedHMAC' => 'URL Encoded HMAC',
				'WEEETaxValue' => 'WEEE Tax Value'
			);
			
			if ( in_array($title, array_keys($extra)) ) {
				return $extra["$title"];
			}
			
			preg_match_all('/((?:^|[A-Z])[a-z]+)/', $title, $matches, PREG_PATTERN_ORDER);
			return implode(' ', $matches[1]);
		}


		/**
		 * Product Price - Update november 2014
		 */
		public function productPriceSetMeta( $thisProd, $post_id='', $return=true ) {
			$ret = array();
			$o = array(
				'ItemAttributes'		=> isset($thisProd['ItemAttributes']['ListPrice']) ? array('ListPrice' => $thisProd['ItemAttributes']['ListPrice']) : array(),
				'Offers'				=> isset($thisProd['Offers']) ? $thisProd['Offers'] : array(),
				'OfferSummary'			=> isset($thisProd['OfferSummary']) ? $thisProd['OfferSummary'] : array(),
				'VariationSummary'		=> isset($thisProd['VariationSummary']) ? $thisProd['VariationSummary'] : array(),
			);
			update_post_meta($post_id, '_amzaff_amzRespPrice', $o);
			
			// Offers/Offer/OfferListing/IsEligibleForSuperSaverShipping
			if ( isset($o['Offers']['Offer']['OfferListing']['IsEligibleForSuperSaverShipping']) ) {
				$ret['isSuperSaverShipping'] = $o['Offers']['Offer']['OfferListing']['IsEligibleForSuperSaverShipping'] === true ? 1 : 0;
				update_post_meta($post_id, '_amzaff_isSuperSaverShipping', $ret['isSuperSaverShipping']);
			}
			
			// Offers/Offer/OfferListing/Availability
			if ( isset($o['Offers']['Offer']['OfferListing']['Availability']) ) {
				$ret['availability'] = (string) $o['Offers']['Offer']['OfferListing']['Availability'];
				update_post_meta($post_id, '_amzaff_availability', $ret['availability']);
			}
			
			return $ret;
		}

		public function productPriceSetRegularSaleMeta( $post_id, $type, $newMetas=array() ) {
			$_amzaff_price = $newMetas;
			$_amzaff_price_db = get_post_meta( $post_id, '_amzaff_'.$type.'_price', true );
			if ( !empty($_amzaff_price_db) && is_array($_amzaff_price_db) ) {
				$_amzaff_price = array_merge($_amzaff_price_db, $_amzaff_price);
			}
			update_post_meta($post_id, '_amzaff_'.$type.'_price', $_amzaff_price);
		}

		public function productPriceGetRegularSaleStatus( $post_id, $type='both' ) {
			$ret = array('regular' => 'auto', 'sale' => 'auto');
			
			foreach (array('regular', 'sale') as $priceType) {
				$meta = (array) get_post_meta( $post_id, '_amzaff_'.$priceType.'_price', true );
				if ( !empty($meta) && isset($meta["current"]) && !empty($meta["current"]) ) {
					$ret["$priceType"] = $meta["current"];
				}
			}
			if ( $type != 'both' && in_array($type, array('regular', 'sale')) ) {
				return $ret["$type"];
			}
			return $ret;
		}
	
	
		/**
		 * Octomber 2015 - new plugin functions
		 */
		// key: country || main_aff_id
		public function get_countries( $key='country' ) {
			if ( 'country' == $key ) {
				return  array(
					'com' => 'Worldwide',
                    'de' => 'Germany',
                    'co.uk' => 'United Kingdom',
                    'ca' => 'Canada',
                    'fr' => 'France',
                    'co.jp' => 'Japan',
                    'in' => 'India',
                    'it' => 'Italy',
                    'cn' => 'China',
                    'es' => 'Spain',
                    'com.mx' => 'Mexico',
                    'com.br' => 'Brazil',
				);
			}
			else {
				return  array(
					'com' => 'United States',
					'uk' => 'United Kingdom',
					'de' => 'Deutschland',
					'fr' => 'France',
					'jp' => 'Japan',
					'ca' => 'Canada',
					'cn' => 'China',
					'in' => 'India',
					'it' => 'Italia',
					'es' => 'España',
					'mx' => 'Mexico',
					'br' => 'Brazil',
				);
			}
			return array();
		}
		
		// key: country || main_aff_id
		public function get_country_name( $country, $key='country' ) {
			$countries = $this->get_countries( $key );
			$country = isset($countries["$country"]) ? $countries["$country"] : '';
			return $country;
		}

		public function get_product_extra( $post_id ) {
			$extra = array_merge(array('_aiowaff_prodtype'), array());
			
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
 
			$this->aaAmazonWS
            	->category( ( $parameters['category'] == 'AllCategories' ? 'All' : $parameters['category'] ) )
                ->page( $page )
                ->responseGroup('Large,ItemAttributes,OfferFull,Offers,Variations,Reviews,PromotionSummary,SalesRank');
     
			// set the page
            $_optionalParameters['ItemPage'] = $page;
                    
            if( isset($_optionalParameters) && count($_optionalParameters) > 0 ){
				// add optional parameter to query
                $this->aaAmazonWS
                	->optionalParameters( $_optionalParameters );
			}
            //var_dump('<pre>',$this->aaAmazonWS,'</pre>');
                
            // add the search keywords
            $response = $this->aaAmazonWS
            	->search( isset($parameters['keyword']) ? $parameters['keyword'] : '' );
			//var_dump('<pre>',$response,'</pre>'); die;
    
            //$__asinsDebug = array();
            //foreach ( $response['Items']['Item'] as $item_key => $item_val ) {
            //    $__asinsDebug[] = $item_val['ASIN'];
            //}
            //var_dump('<pre>',$__asinsDebug,'</pre>');
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

			$this->aaAmazonWS
				->responseGroup('Large,ItemAttributes,OfferFull,Offers,Variations,Reviews,PromotionSummary,SalesRank')
				->optionalParameters(array('MerchantId' => 'All'));
			
			$response = $this->aaAmazonWS
				->lookup( implode(",", $asins) );
			//var_dump('<pre>',$response,'</pre>'); die;
                    
            //$__asinsDebug = array();
            //foreach ( $response['Items']['Item'] as $item_key => $item_val ) {
            //    $__asinsDebug[] = $item_val['ASIN'];
            //}
            //var_dump('<pre>',$__asinsDebug,'</pre>');
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

			{
				$rsp = $this->api_search_set_stats(array(
					'requestData'				=> $requestData,
					'response'					=> $response,
				));
				$requestData = $rsp['requestData'];
			}

            // verify array of Items or array of Item elements
            if ( isset($response['Items']['Item']['ASIN']) ) {
				$response['Items']['Item'] = array( $response['Items']['Item'] );
            }

			$_response = array();
			foreach ( $response['Items']['Item'] as $key => $value){
				$_response["$key"] = $value;
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
            if ( !isset($results['Items'], $results['Items']['TotalResults'], $results['Items']['NbPagesSelected'])
                || count($results) < 2 ) {
				$status = false;
			}
			$nbpages = (int) $results['Items']['NbPagesSelected'];
			
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
					'TotalResults'			=> $results['Items']['TotalResults'],
					'NbPagesSelected'		=> $results['Items']['NbPagesSelected'],
					'TotalPages'			=> $results['Items']['TotalPages'],
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
				if ( isset($response['Items']['TotalResults']) ) {

					$totalItems = isset($response['Items']['TotalResults']) ? $response['Items']['TotalResults'] : 0;
					$totalPages = $totalItems > 0 ? ceil( $totalItems / 10 ) : 0;
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
			
			$asins = $page_content['Items']['Item'];
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

				$dataToSave['Items']['TotalResults'] = $stats['TotalResults'];
            	$dataToSave['Items']['TotalPages'] = $stats['TotalPages'];
                $dataToSave['Items']['NbPagesSelected'] = $cachename->params['nbpages'];
			}

            if ( is_array($content) && !isset($content['__notused__']) ) {

				$rsp = $this->api_format_results(array(
					'requestData'			=> $requestData,
					'response'				=> $response,
				));

				$dataToSave["$page"] = array();

				// 1 item found only
				if ( $dataToSave['Items']['TotalResults'] == 1 && !isset($rsp['response'][0]) ) {
					$rsp['response'] = array($rsp['response']);
				}

				foreach ($rsp['response'] as $key => $value) {
					$product = $this->build_product_data( $value );
					if ( !empty($product['ASIN']) ) {
						$dataToSave["$page"]['Items']['Item']["$key"] = $product['ASIN'];
					}
				}
			}			

			return array(
				'dataToSave'		=> $dataToSave,
			);
		}
	
	}
}