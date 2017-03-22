<?php
/*
* Define class aiowaffInsaneImport
* Make sure you skip down to the end of this file, as there are a few
* lines of code that are very important.
*/
!defined('ABSPATH') and exit;

if (class_exists('aiowaffInsaneImport') != true) {
    class aiowaffInsaneImport
    {
        /*
        * Some required plugin information
        */
        const VERSION = '1.0';

        /*
        * Store some helpers config
        */
		public $the_plugin = null;
        
        // amazon
        //private $amzHelper = null;
        //private $aaAmazonWS = null;
		
		private $module_folder = '';
        private $module_folder_path = '';
		private $module = '';

		static protected $_instance;
		
		private $settings;

        private static $CACHE = array(
            'search_lifetime'       => 720, // cache lifetime in minutes /half day
            'search_folder'         => '',
            'prods_lifetime'        => 1440, // cache lifetime in minutes /one day
            'prods_folder'          => '',
        );
        private static $CACHE_ENABLED = array(
            'search'                => true,
            'prods'                 => true,
        );
		
        //const LOAD_MAX_LIMIT =  10; // number of ASINs per amazon requests!
		private static $LOAD_MAX_LIMIT = array(
            'amazon'                => 10,
            'alibaba'               => 1,
            'ebay'	            	=> 20,
        );
		
		private static $REQUESTS_DELAY = array( // delay in micro seconds based on provider and on number of requests made!
		 	// nbreq = number of consecutive requests made; delay = sleep in microseconds
            'amazon'                => array('nbreq' => 0, 'delay' => 0), // amazon delay is made with Amazon requests rate option!
            'alibaba'               => array('nbreq' => 100, 'delay' => 1000000),
            'ebay'	            	=> array('nbreq' => 100, 'delay' => 1000000),
		);
		private static $REQUESTS_NB = array(
			'amazon' 				=> array('current' => 0, 'total' => 0),
			'alibaba'				=> array('current' => 0, 'total' => 0),
			'ebay'					=> array('current' => 0, 'total' => 0),
		);

        const MSG_SEP = '—'; // messages html bullet // '&#8212;'; // messages html separator
        
        private static $optionalParameters = array( 
			'amazon'	=> array(
	            'BrowseNode'        	=> 'select',
	            'Brand'             	=> 'input',
	            'Condition'         	=> 'select',
	            'Manufacturer'      	=> 'input',
	            'MaximumPrice'      	=> 'input',
	            'MinimumPrice'      	=> 'input',
	            'MinPercentageOff'  	=> 'select',
	            'MerchantId'        	=> 'input',
	            'Sort'              	=> 'select',
			),
			'alibaba'	=> array(
				'commissionRateFrom'	=> 'input',
				'commissionRateTo'		=> 'input',
				'priceFrom'				=> 'input',
				'priceTo'				=> 'input',
				'promotionFrom'			=> 'input',
				'promotionTo'			=> 'input',
			),
			
			'ebay'		=> array(
				'BrowseNode'			=> 'select',
				'AuthorizedSellerOnly'	=> 'select',
				'BestOfferOnly'			=> 'select',
				'CharityOnly'			=> 'select',
				//http://developer.ebay.com/DevZone/finding/CallRef/Enums/conditionIdList.html
				'Condition'				=> 'select',
				//http://developer.ebay.com/DevZone/finding/CallRef/Enums/currencyIdList.html
				'Currency'				=> 'select',
				'ExpeditedShippingType'	=> 'select',
				'FeaturedOnly'			=> 'select',
				'FeedbackScoreMax'		=> 'input',
				'FeedbackScoreMin'		=> 'input',
				'FreeShippingOnly'		=> 'select',
				'GetItFastOnly'			=> 'select',
				'HideDuplicateItems'	=> 'select',
				'ListingType'			=> 'select',
				'LocalSearchOnly'		=> 'select',
				'MaxBids'				=> 'input',
				'MaxDistance'			=> 'input',
				'MaxPrice'				=> 'input',
				'MinBids'				=> 'input',
				'MinPrice'				=> 'input',
				'OutletSellerOnly'		=> 'select',
				'PaymentMethod'			=> 'select',
				'ReturnsAcceptedOnly'	=> 'select',
				'Seller'				=> 'input',
				'WorldOfGoodOnly'		=> 'select',
				'sortOrder' 			=> 'select',
			),
        );
		

        /*
        * Required __construct() function that initalizes the Ali Framework
        */
        public function __construct()
        {
        	global $aiowaff;

        	$this->the_plugin = $aiowaff;
			
			// amazon
            //$this->amzHelper = $this->the_plugin->amzHelper;
            //$this->aaAmazonWS = $this->the_plugin->amzHelper->aaAmazonWS;
            //$this->setupAmazonWS();
            
			$this->module_folder = $this->the_plugin->cfg['paths']['plugin_dir_url'] . 'modules/insane_import/';
            $this->module_folder_path = $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'modules/insane_import/';
			$this->module = $this->the_plugin->cfg['modules']['insane_import'];
			
			$this->settings = $this->the_plugin->getAllSettings('array', 'amazon');
            self::$CACHE = array_merge(self::$CACHE, array(
                'search_folder'         => $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'cache/search/',
                'prods_folder'          => $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'cache/products/',
            ));
  
			if (is_admin()) {
	            add_action('admin_menu', array( &$this, 'adminMenu' ));
			}
			
            // ajax requests
			add_action('wp_ajax_aiowaffIM_KeywordAutocomplete', array( &$this, 'ajax_autocomplete' ));
			add_action('wp_ajax_aiowaffIM_InsaneAjax', array( &$this, 'ajax_request' ), 10, 2);
            add_action('wp_ajax_aiowaffIM_LoadProdsGrabParseURL', array( &$this, 'loadprods_grab_parse_url' ));
            add_action('wp_ajax_aiowaffIM_LoadProdsByASIN', array( &$this, 'loadprods_queue_by_asin' ), 10, 2);
            add_action('wp_ajax_aiowaffIM_LoadProdsBySearch', array( &$this, 'loadprods_queue_by_search' ), 10, 2);
            add_action('wp_ajax_aiowaffIM_exportASIN', array( &$this, 'ajax_export_asin' ), 10, 1);
            add_action('wp_ajax_aiowaffIM_getCategoryParams', array( &$this, 'get_category_params_html' ), 10, 2);
            add_action('wp_ajax_aiowaffIM_getBrowseNodes', array( &$this, 'get_browse_nodes_html' ), 10, 2);
            add_action('wp_ajax_aiowaffIM_ImportProduct', array( $this, 'import_product' ), 10, 2);
			
			$this->settings['page_types'] = array(
				'Best Sellers',
				//'Deals',
				'Top Rated',
				'Most Wished For',
				'Movers & Shakers',
				'Hot New Releases',
				//'Best Sellers Cattegory',
				//'Gift Ideas',
				//'New Arrivals',
			);
        }

		/**
	    * Singleton pattern
	    *
	    * @return aiowaffInsaneImport Singleton instance
	    */
	    static public function getInstance()
	    {
	        if (!self::$_instance) {
	            self::$_instance = new self;
	        }

	        return self::$_instance;
	    }

		/**
	    * Hooks
	    */
	    static public function adminMenu()
	    {
	       self::getInstance()
	    		->_registerAdminPages();
	    }

	    /**
	    * Register plug-in module admin pages and menus
	    */
		protected function _registerAdminPages()
    	{ 
    		add_submenu_page(
    			$this->the_plugin->alias,
    			$this->the_plugin->alias . " " . __('Products Importer', $this->the_plugin->localizationName),
	            __('Products Importer', $this->the_plugin->localizationName),
	            'manage_options',
	            $this->the_plugin->alias . "_insane_import",
	            array($this, 'display_index_page')
	        );

			return $this;
		}

		public function display_index_page()
		{
			$this->printBaseInterface();
		}
		
		/*
		* printBaseInterface, method
		* --------------------------
		*
		* this will add the base DOM code for you options interface
		*/
		private function printBaseInterface()
		{
			global $wpdb;
			
			//ob_start();
?>
    		<link rel='stylesheet' href='<?php echo $this->module_folder;?>app.css' type='text/css' media='all' />
    		<link rel='stylesheet' href='<?php echo $this->module_folder;?>rangeslider/rangeslider.css' type='text/css' media='all' />
    		<div id="aiowaff-wrapper" class="fluid wrapper-aiowaff aiowaff-asin-grabber">

			<?php
			// show the top menu
			aiowaffAdminMenu::getInstance()->make_active('import|insane_import')->show_menu();
			?>

			<!-- Content -->
			<div id="aiowaff-content">
				
				<h1 class="aiowaff-section-headline">
					<?php 
					if( isset($this->module['insane_import']['in_dashboard']['icon']) ){
						echo '<img src="' . ( $this->module_folder . $this->module['insane_import']['in_dashboard']['icon'] ) . '" class="aiowaff-headline-icon">';
					}
					?>
					<?php echo $this->module['insane_import']['menu']['title'];?>
					<span class="aiowaff-section-info"><?php echo $this->module['insane_import']['description'];?></span>
					<?php
					$has_help = isset($this->module['insane_import']['help']) ? true : false;
					if( $has_help === true ){
						
						$help_type = isset($this->module['insane_import']['help']['type']) && $this->module['insane_import']['help']['type'] ? 'remote' : 'local';
						if( $help_type == 'remote' ){
							echo '';
						} 
					}
					echo '';
					?>
				</h1>
				
				<!-- Main loading box -->
				<div id="aiowaff-main-loading">
					<div id="aiowaff-loading-overlay"></div>
					<div id="aiowaff-loading-box">
						<div class="aiowaff-loading-text"><?php _e('Loading', $this->the_plugin->localizationName);?></div>
						<div class="aiowaff-meter aiowaff-animate" style="width:86%; margin: 34px 0px 0px 7%;"><span style="width:100%"></span></div>
					</div>
				</div>

				<!-- Container -->
				<div class="aiowaff-container clearfix" id="aiowaff-insane-import" style="position: relative;">
				    
					<!-- Main Content Wrapper -->
					<div id="aiowaff-content-wrap" class="clearfix" style="padding-top: 5px;">
                    <?php
                    // find if user makes the setup
                    $moduleValidateStat = $this->moduleValidation();
					if ( !$moduleValidateStat['status']
						//|| !is_object($this->the_plugin->amzHelper) || is_null($this->the_plugin->amzHelper)
					) {
                        echo $moduleValidateStat['html'];
                    }
                    else {
                    ?>
                    
                        <?php
                            // IMPORT PRODUCTS - PARAMETERS
                            $amz_settings = $this->settings;
                            $import_params = array(
                                'spin_at_import'            => false,
                                'import_attributes'         => false,
                                'import_type'               => 'default',
                                'number_of_images'          => 'all',
                                'number_of_variations'      => 'no',
                            );
                            
                            // download images
                            $import_type = 'default';
                            if ( isset($amz_settings['import_type']) && $amz_settings['import_type']=='asynchronous' ) {
                                $import_type = $amz_settings['import_type' ];
                            }
                            $import_params['import_type'] = $import_type;
                                
                            // number of images
                            $number_of_images = (
                                isset($amz_settings["number_of_images"]) && (int) $amz_settings["number_of_images"] > 0
                                ? (int) $amz_settings["number_of_images"] : 'all'
                            );
                            if ( $number_of_images > 100 ) $number_of_images = 'all';
                            $import_params['number_of_images'] = $number_of_images;
                            
                            // number of variations
                            $variationNumber = isset( $amz_settings['product_variation'] ) ? $amz_settings['product_variation'] : 'no';
                            // convert $variationNumber into number
                            if( $variationNumber == 'yes_all' ){
                                $variationNumber = 'all'; // 100 variation is enough
                            }
                            elseif( $variationNumber == 'no' ){
                                $variationNumber = 0;
                            }
                            else{
                                $variationNumber = explode(  "_", $variationNumber );
                                $variationNumber = (int) end( $variationNumber );
                                if ( $variationNumber > 100 ) $variationNumber = 'all';
                            }
                            $import_params['number_of_variations'] = $variationNumber;
                            
                            // spin at import
                            $spin_at_import = isset($amz_settings['spin_at_import']) && $amz_settings['spin_at_import'] == 'yes' ? true : false;
                            $import_params['spin_at_import'] = $spin_at_import;
                            
                            // import attributes
                            $import_attributes = isset($amz_settings['item_attribute']) && $amz_settings['item_attribute'] == 'no' ? false : true;
                            $import_params['import_attributes'] = $import_attributes;
    
                            //var_dump('<pre>', $import_params, '</pre>'); die('debug...'); 
                        ?>
    
                        <?php
                            // Lang Messages
                            $lang = array(
                                'loading'                   => __('Loading...', 'aiowaff'),
                                'closing'                   => __('Closing...', 'aiowaff'),
                                'load_op_search'            => __('load prods by search', 'aiowaff'),
                                'load_op_grab'              => __('load prods by grab', 'aiowaff'),
                                'load_op_bulk'              => __('load prods by bulk', 'aiowaff'),
                                'load_op_export'            => __('export asins', 'aiowaff'),
                                'load_op_import'            => __('import products', 'aiowaff'),
                                'search_pages_single'       => __(' First page', 'aiowaff'),
                                'search_pages_many'         => __(' First %s pages', 'aiowaff'),
                                'bulk_add_asin'             => self::MSG_SEP . __(' Please first add some ASINs!', 'aiowaff'),
                                'bulk_no_asin_found'        => self::MSG_SEP . __(' No ASINs found!', 'aiowaff'),
                                'bulk_asin_found'           => self::MSG_SEP . __(' %s ASINs found: ', 'aiowaff'),
                                'already_exists'            => self::MSG_SEP . __(' %s ASINs already parsed (loaded, invalid, imported): %s', 'aiowaff'),
                                'export_no_asin'            => self::MSG_SEP . __(' No ASINs found to export!', 'aiowaff'),
                                
                                'loadprods_inprogress'      => __('Loading Products in Queue In Progress...', 'aiowaff'),
                                'importprods_inprogress'    => __('Importing Products In Progress...', 'aiowaff'),
                                
                                'speed_value'               => __('%s PPM', 'aiowaff'), //products per minute
                                'speed_level1'              => __('SPEED is VERY SLOW.', 'aiowaff'),
                                'speed_level2'              => __('SPEED is SLOW.', 'aiowaff'),
                                'speed_level3'              => __('SPEED is OK.', 'aiowaff'),
                                'speed_level4'              => __('SPEED is FAST.', 'aiowaff'),
                                'speed_level5'              => __('SPEED is VERY FAST.', 'aiowaff'),
                                'speed_level6'              => __('SPEED is INSANE.', 'aiowaff'),
                                
                                'day'                       => __('day', 'aiowaff'),
                                'hour'                      => __('hour', 'aiowaff'),
                                'min'                       => __('minute', 'aiowaff'),
                                'sec'                       => __('second', 'aiowaff'),
                                
                                // import product screen
                                'btn_stop'                  => __('STOP', 'aiowaff'),
                                'btn_close'                 => __('CLOSE BOX', 'aiowaff'),

                                'import_empty'              => __('No products selected for import!', 'aiowaff'),
                                'process_status_stop'       => __('the process is stopped', 'aiowaff'),
                                'process_status_stop_'      => __('the process will stop after the current product', 'aiowaff'),
                                'process_status_run'        => __('the process is running', 'aiowaff'),
                                'process_status_finished'   => __('the process is finished', 'aiowaff'),
                                'parsed_prods'              => __('%s of %s products', 'aiowaff'),
                                'parsed_images'             => __('%s of %s images', 'aiowaff'),
                                'parsed_variations'         => __('%s of %s variations', 'aiowaff'),
                                
                                'current_product_title'     => __('current product', 'aiowaff'),
                                'next_product_title'        => __('next product', 'aiowaff'),
                                
								'check_all'					=> __('check all', 'aiowaff'),
								'uncheck_all'				=> __('uncheck all', 'aiowaff'),
								
								//'alibaba_nb_results'		=> __('%s items in %s pages', 'aiowaff'),
                            ); 
                        ?>
                        <!-- Lang Messages -->
                        <div id="aiowaff-lang-translation" style="display: none;"><?php echo htmlentities(json_encode( $lang )); ?></div>
                    
                        <?php
                            // Import Estimation Settings
                            $importSettings = $this->the_plugin->get_last_imports(); 
                        ?>
                        <!-- Import Estimation Settings -->
                        <div id="aiowaff-import-settings" style="display: none;"><?php echo htmlentities(json_encode( $importSettings )); ?></div>

                        <!-- Background Loading - OLD, not used -->
						<div class="aiowaff-insane-work-in-progress">
							<ul class="aiowaff-preloader"><li></li><li></li><li></li><li></li><li></li></ul>
							<span class="aiowaff-the-action"><?php _e('Execution action ...', $this->the_plugin->localizationName);?></span>
						</div>
						
						<!-- Import Product Screen -->
						<div id="aiowaff-import-screen" style="display: none;">

						<div class="aiowaff-iip-lightbox" id="aiowaff-iip-screen">
						    <div class="aiowaff-iip-in-progress-box">

						        <h1><?php _e('Import products in progress ...', $this->the_plugin->localizationName); ?></h1>
						        <p class="aiowaff-message aiowaff-info aiowaff-iip-notice">
						        <?php _e('Please be patient while the products are been imported. 
						        This can take a while if your server is slow (inexpensive hosting) or if you have many products. 
						        Do not navigate away from this page until this script is done. 
						        You will be notified via this box when the regenerating is completed.', $this->the_plugin->localizationName); ?>
						        </p>
						        <div class="aiowaff-iip-details">
						            <table>
						                <thead>
						                    <tr>
						                        <th><span><?php _e('Import Status', $this->the_plugin->localizationName); ?></span></th>
						                        <th><span><?php _e('Estimated Remained Time', $this->the_plugin->localizationName); ?></span></th>
						                        <th><span><?php _e('Speed', $this->the_plugin->localizationName); ?></span></th>
						                    </tr>
						                </thead>
						                <tbody>
						                    <tr>
						                        <td id="aiowaff-iip-estimate-status">
						                            <input type="button" value="<?php _e('STOP', $this->the_plugin->localizationName); ?>" class="aiowaff-button red" id="aiowaff-import-stop-button">
						                            <span><?php echo $lang['process_status_run']; ?></span>
						                        </td>
						                        <td id="aiowaff-iip-estimate-time"><span></span></td>
						                        <td id="aiowaff-iip-estimate-speed"><span>0 <?php _e('PPM', $this->the_plugin->localizationName); ?></span></td>
						                    </tr>
						                </tbody>
						            </table>
						        </div>
						        <div class="aiowaff-iip-process-progress-bar im-products">
						            <div class="aiowaff-iip-process-progress-marker"></div>
						            <div class="aiowaff-iip-process-progress-text">
						                <span><?php _e('Progress', $this->the_plugin->localizationName); ?>: <span>0%</span></span>
						                <span><?php _e('Parsed', $this->the_plugin->localizationName); ?>: <span></span></span>
						                <span><?php _e('Elapsed time', $this->the_plugin->localizationName); ?>: <span></span></span>
						            </div>
						        </div>
						      
						        <div class="aiowaff-iip-process-progress-bar im-images">
						            <div class="aiowaff-iip-process-progress-marker"></div>
						            <div class="aiowaff-iip-process-progress-text">
						                <span><?php _e('Progress', $this->the_plugin->localizationName); ?>: <span>0%</span></span>
						                <span><?php _e('Parsed', $this->the_plugin->localizationName); ?>: <span></span></span>
						            </div>
						        </div>
						      
						        <div class="aiowaff-iip-process-progress-bar im-variations">
						            <div class="aiowaff-iip-process-progress-marker"></div>
						            <div class="aiowaff-iip-process-progress-text">
						                <span><?php _e('Progress', $this->the_plugin->localizationName); ?>: <span>0%</span></span>
						                <span><?php _e('Parsed', $this->the_plugin->localizationName); ?>: <span></span></span>
						            </div>
						        </div>

						        <div class="aiowaff-iip-tail">
						            <ul class="WZC-keyword-attached aiowaff-insane-bigscroll">
						            </ul>
						        </div>
						        
						        <div class="aiowaff-iip-log">
						            
						        </div>

						    </div>
						</div>

						</div>

						<!-- Parents TABS -->
						<div class="aiowaff-insane-container aiowaff-big-buttons aiowaff-insane-tabs" id="aiowaff-wrap-loadproducts">
						    <div class="aiowaff-insane-buton-logs" data-logcontainer="aiowaff-logs-load-products"><?php _e('View Messages Log', $this->the_plugin->localizationName); ?></div>
							<div class="aiowaff-insane-panel-headline aiowaff-top-headline">

								<?php /*<a href="#aiowaff-content-amazon" data-provider="amazon" class="on"><?php _e('AMAZON', $this->the_plugin->localizationName);?></a>
                				<a href="#aiowaff-content-envato" data-provider="envato"><?php _e('ENVATO', $this->the_plugin->localizationName);?></a>
                				<a href="#aiowaff-content-ebay" data-provider="ebay"><?php _e('EBAY', $this->the_plugin->localizationName);?></a>
                				<a href="#aiowaff-content-alibaba" data-provider="alibaba"><?php _e('ALIBABA', $this->the_plugin->localizationName);?></a>*/ ?>

								<?php if ($this->provider_is_enabled('amazon')) { ?>
                				<a href="#aiowaff-content-amazon" data-provider="amazon" class="on"><img src="<?php echo $this->module_folder;?>assets/amz-logo.png" /></a>
                				<?php } ?>
                				
                				<?php if ($this->provider_is_enabled('ebay')) { ?>
                				<a href="#aiowaff-content-ebay" data-provider="ebay"><img src="<?php echo $this->module_folder;?>assets/ebay-logo.png" /></a>
                				<?php } ?>
                				<?php if ($this->provider_is_enabled('alibaba')) { ?>
                				<a href="#aiowaff-content-alibaba" data-provider="alibaba"><img src="<?php echo $this->module_folder;?>assets/ali-logo.png" /></a>
                				<?php } ?>
							</div>
							<div class="aiowaff-insane-tabs-content">
								<div class="aiowaff-content-scroll">
									
									<?php if ($this->provider_is_enabled('amazon')) { ?>
									<div id="aiowaff-content-amazon" class="aiowaff-insane-tab-content">

						<!-- Amazon Content Area -->
						<?php
						$provider_status = $this->providerSettingsValidation( 'amazon' );
						if ( 'invalid' == $provider_status['status'] ) {
							echo $provider_status['html'];
						} else {
						?>
						<div class="aiowaff-insane-container aiowaff-insane-tabs">
							<div class="aiowaff-insane-panel-headline aiowaff-operation-message">
								<span></span>
                				<a href="#aiowaff-content-search-amazon" class="on"><?php _e('SEARCH FOR PRODUCTS', $this->the_plugin->localizationName);?></a>
                				<a href="#aiowaff-content-grab-amazon"><?php _e('GRAB PRODUCTS', $this->the_plugin->localizationName);?></a>
                				<a href="#aiowaff-content-bulk-amazon"><?php _e('ALREADY HAVE A LIST?', $this->the_plugin->localizationName);?></a>
							</div>
							<div class="aiowaff-insane-tabs-content">
								<div class="aiowaff-content-scroll">
									
			            			<div id="aiowaff-content-search-amazon" class="aiowaff-insane-tab-content">
			            				<!-- Search buttons -->
			            				<div class="aiowaff-insane-tab-search-buttons-container">
			            					<form class="aiowaff-search-products">
				            					<ul class="aiowaff-insane-tab-search-buttons">
				            						<li>
			            								<span class="tooltip" title="Choose Keyword"><i class="fa fa-search"></i></span>
			            							 	<input type="text" id="aiowaff-search-keyword" name="aiowaff-search[keyword]" placeholder="<?php _e('Keyword', $this->the_plugin->localizationName);?>" class="autocomplete">
			            							 	<ul class="aiowaff-search-completion"></ul>
				            						</li>
				            						<li class="aiowaff-select-on-category">
				            							<span class="tooltip" title="Choose Category"><i class="fa fa-sitemap"></i></span>
                                                        <select class="aiowaff-search-category" name="aiowaff-search[category]">
                                                            <option value="" disabled="disabled"><?php _e('Category', $this->the_plugin->localizationName);?></option>
                                                            <option value="AllCategories" selected="selected" data-nodeid="all"><?php _e('All categories', $this->the_plugin->localizationName);?></option>
                                                            <?php echo $this->get_categories_html('amazon'); ?>
                                                        </select>
				            							<?php /*<input readonly type="text" class="aiowaff-select-category-placeholder" value="<?php _e('All categories', $this->the_plugin->localizationName);?>" id="aiowaff-search-search_on" name="aiowaff-search[search_on]" />
				            							<div class="aiowaff-category-selector">
				            								<label>
				            									<span><?php _e('Search on Category', $this->the_plugin->localizationName);?>:</span>
				            									<select id="aiowaff-search-category" name="aiowaff-search[category]">
						            								<option value="" disabled="disabled"><?php _e('Category', $this->the_plugin->localizationName);?></option>
                                                                    <option value="AllCategories" selected="selected"><?php _e('All categories', $this->the_plugin->localizationName);?></option>
																	<?php echo $this->get_categories_html(); ?>
																</select>
				            								</label>
				            								
                                                            <label>
                                                                <span><?php _e('Custom BrowseNode ID', $this->the_plugin->localizationName);?>:</span>
                                                                <input type="text" id="aiowaff-node" name="aiowaff-search[node]" />
                                                            </label>
				            							</div>*/ ?>
				            						</li>
                                                    <li>
                                                        <span class="tooltip" title="Choose number of pages to search for results from webservice"><i class="fa fa-briefcase"></i></span>
                                                        <select class="aiowaff-search-nbpages" name="aiowaff-search[nbpages]">
                                                            <option value="" disabled="disabled"><?php _e('Grab', $this->the_plugin->localizationName);?></option>
                                                        <?php
                                                            for ($i = 1; $i <= 5; ++$i) {
                                                                $text = $i == 1 ? $lang['search_pages_single'] : sprintf( $lang['search_pages_many'], $i );
                                                                $selected = $i == 1 ? 'selected="selected"' : '';
                                                                echo '<option value="'.$i.'" '.$selected.'>'.$text.'</option>';
                                                            }
                                                        ?>
                                                        </select>
                                                    </li>
				            						<li class="button-block">
				            							<input type="submit" value="<?php _e('Launch search', $this->the_plugin->localizationName);?>" class="aiowaff-button red" />
				            						</li>
				            					</ul>
			            					</form>
			            				</div>
			            			</div>
			            			
			            			<div id="aiowaff-content-grab-amazon" class="aiowaff-insane-tab-content">
			            				<!-- Grab from amazon -->
			            				<form class="aiowaff-grab-products">
			            					<label>
			            						<span><?php _e('Amazon URL', $this->the_plugin->localizationName);?>:</span>
												<input type="text" placeholder="<?php _e('Paste the Amazon page URL here', $this->the_plugin->localizationName);?>" name="aiowaff-grab[url]" value="">
												<span class="aiowaff-form-note"><?php _e('The Amazon Page from where you want to import the ASIN codes. E.g: http://www.amazon.com/gp/top-rated', $this->the_plugin->localizationName);?></span>
			            					</label>
			            					
			            					<label>
			            						<span><?php _e('Page type:', $this->the_plugin->localizationName);?></span>
												<select name="aiowaff-grab[page-type]">
												    <option value="best sellers"><?php _e('Best Sellers', $this->the_plugin->localizationName);?></option>
												    <option value="top rated"><?php _e('Top Rated', $this->the_plugin->localizationName);?></option>
												    <option value="most wished for"><?php _e('Most Wished For', $this->the_plugin->localizationName);?></option>
												    <option value="movers &amp; shakers"><?php _e('Movers &amp; Shakers', $this->the_plugin->localizationName);?></option>
												    <option value="hot new releases"><?php _e('Hot New Releases', $this->the_plugin->localizationName);?></option>
												</select>
			            					</label>
			            					
			            					<input type="button" value="<?php _e('GET ASIN codes', $this->the_plugin->localizationName);?>" class="aiowaff-button orange aiowaff-grabb-button">
			            				</form>
			            			</div>

			            			<div id="aiowaff-content-bulk-amazon" class="aiowaff-insane-tab-content">
			            			    <!-- ASINs Bulk Import -->
			            				<form class="aiowaff-import-products aiowaff-bulk-products">
			            					<h3><?php _e('ASIN codes', $this->the_plugin->localizationName);?>:</h3>
			            					<textarea class="aiowaff-content-bulk-asin"></textarea>
			            					<div class="aiowaff-delimiters">
												<span><?php _e('ASIN delimiter by', $this->the_plugin->localizationName);?>:</span>
												<input type="radio" val="newline" name="aiowaff-csv-delimiter" checked="" class="aiowaff-csv-radio" id="aiowaff-csv-radio-newline"><label for="aiowaff-csv-radio-newline"><?php _e('New line', $this->the_plugin->localizationName);?> <code>\n</code></label>
												<input type="radio" val="comma" name="aiowaff-csv-delimiter" id="aiowaff-csv-radio-comma"><label for="aiowaff-csv-radio-comma"><?php _e('Comma', $this->the_plugin->localizationName);?> <code>,</code></label>
												<input type="radio" val="tab" name="aiowaff-csv-delimiter" id="aiowaff-csv-radio-tab"><label for="aiowaff-csv-radio-tab"><?php _e('TAB', $this->the_plugin->localizationName);?> <code>TAB</code></label>
											</div>
											<div class="aiowaff-delimiters">
												<!--<span>Import to category:</span>
												<select id="aiowaff-to-category" name="aiowaff-to-category">
													<option value="-1">Use category from Amazon</option>
													<option class="level-0">Electronics</option>
													<option class="level-1""">Computers</option>
													<option class="level-2">Components</option>
												</select>-->
												<input class="aiowaff-addASINtoQueue" type="button" value="<?php _e('Add ASIN codes to Queue', $this->the_plugin->localizationName);?>" />
											</div>
			            				</form>	
			            			</div>
			            			
			            		</div>
		            		</div>
						</div>
						<?php } ?>
						<!-- end Amazon Content Area -->
						
									</div>
									<?php } ?>
									
									<?php if ($this->provider_is_enabled('envato')) { ?>
									<div id="aiowaff-content-envato" class="aiowaff-insane-tab-content">
										
						<!-- Envato Content Area -->
						<?php
						$provider_status = $this->providerSettingsValidation( 'envato' );
						if ( 'invalid' == $provider_status['status'] ) {
							echo $provider_status['html'];
						} else {
						?>
						<div class="aiowaff-insane-container aiowaff-insane-tabs">
							<div class="aiowaff-insane-panel-headline aiowaff-operation-message">
								<span></span>
                				<a href="#aiowaff-content-search-envato" class="on"><?php _e('SEARCH FOR PRODUCTS', $this->the_plugin->localizationName);?></a>
                				<a href="#aiowaff-content-grab-envato"><?php _e('POPULAR FILES', $this->the_plugin->localizationName);?></a>
                				<a href="#aiowaff-content-bulk-envato"><?php _e('ALREADY HAVE A LIST?', $this->the_plugin->localizationName);?></a>
							</div>
							<div class="aiowaff-insane-tabs-content">
								<div class="aiowaff-content-scroll">
									
			            			<div id="aiowaff-content-search-envato" class="aiowaff-insane-tab-content">
			            				<!-- Search buttons -->
			            				<div class="aiowaff-insane-tab-search-buttons-container">
			            					<form class="aiowaff-search-products">
				            					<ul class="aiowaff-insane-tab-search-buttons">
				            						<li>
			            								<span class="tooltip" title="The string to search for"><i class="fa fa-search"></i></span>
			            							 	<input type="text" id="aiowaff-search-keyword" name="aiowaff-search[keyword]" placeholder="<?php _e('Keyword', $this->the_plugin->localizationName);?>">
				            						</li>
				            						<li class="aiowaff-select-on-category">
				            							<span class="tooltip" title="The site to match."><i class="fa fa-sitemap"></i></span>
                                                        <?php echo $this->get_ws_object( 'envato' )->get_sites_selector( 'aiowaff-search[site]' ); ?>
				            						</li>
                                                    <li>
                                                        <span class="tooltip" title="Choose number of pages to search for results from webservice"><i class="fa fa-briefcase"></i></span>
                                                        <select class="aiowaff-search-nbpages" name="aiowaff-search[nbpages]">
                                                            <option value="" disabled="disabled"><?php _e('Grab', $this->the_plugin->localizationName);?></option>
                                                        <?php
                                                            for ($i = 1; $i <= 10; ++$i) {
                                                                $text = $i == 1 ? $lang['search_pages_single'] : sprintf( $lang['search_pages_many'], $i );
                                                                $selected = $i == 1 ? 'selected="selected"' : '';
                                                                echo '<option value="'.$i.'" '.$selected.'>'.$text.'</option>';
                                                            }
                                                        ?>
                                                        </select>
                                                        <span class="aiowaff-choose-between"></span>
                                                    </li>
				            						<li>
			            								<span class="tooltip" title="Page number (max. 60): <span style='color: red;'>if you choose this, the Grab number of pages option will not be used</span>"><i class="fa fa-briefcase"></i></span>
			            							 	<input type="text" name="aiowaff-search[page]" placeholder="<?php _e('Page nb', $this->the_plugin->localizationName);?>">
				            						</li>
				            						<?php
				            						$getEnvatoSearchParams = $this->get_category_params_html('return', array(
										                'what_params'           => 'all',
										                'category'              => '',
										                'nodeid'                => 0,
										                'provider'              => 'envato',
										            ));
													if ( 'valid' == $getEnvatoSearchParams['status'] ) {
														echo $getEnvatoSearchParams['html'];
													}
				            						?>
				            						<li class="button-block">
				            							<input type="submit" value="<?php _e('Launch search', $this->the_plugin->localizationName);?>" class="aiowaff-button red" />
				            						</li>
				            					</ul>
			            					</form>
			            				</div>

			            			</div>
			            			
			            			<div id="aiowaff-content-grab-envato" class="aiowaff-insane-tab-content">
			            				<!-- Popular Files -->
			            				<form class="aiowaff-grab-products">
			            					<label>
			            						<span><?php _e('Page type:', $this->the_plugin->localizationName);?></span>
												<?php echo $this->get_ws_object( 'envato' )->get_sites_selector( 'aiowaff-search[site]' ); ?>
			            					</label>
			            					
			            					<input type="hidden" name="aiowaff-search[search_type]" id="aiowaff-search-search_type" value="popular">
			            					<input type="button" value="<?php _e('Grabb Products', $this->the_plugin->localizationName);?>" class="aiowaff-button orange aiowaff-grabb-button">
			            				</form>
			            			</div>

			            			<div id="aiowaff-content-bulk-envato" class="aiowaff-insane-tab-content">
			            			    <!-- ASINs Bulk Import -->
			            				<form class="aiowaff-import-products aiowaff-bulk-products">
			            					<h3><?php _e('ASIN codes', $this->the_plugin->localizationName);?>:</h3>
			            					<textarea class="aiowaff-content-bulk-asin"></textarea>
			            					<div class="aiowaff-delimiters">
												<span><?php _e('ASIN delimiter by', $this->the_plugin->localizationName);?>:</span>
												<input type="radio" val="newline" name="aiowaff-csv-delimiter" checked="" class="aiowaff-csv-radio" id="aiowaff-csv-radio-newline"><label for="aiowaff-csv-radio-newline"><?php _e('New line', $this->the_plugin->localizationName);?> <code>\n</code></label>
												<input type="radio" val="comma" name="aiowaff-csv-delimiter" id="aiowaff-csv-radio-comma"><label for="aiowaff-csv-radio-comma"><?php _e('Comma', $this->the_plugin->localizationName);?> <code>,</code></label>
												<input type="radio" val="tab" name="aiowaff-csv-delimiter" id="aiowaff-csv-radio-tab"><label for="aiowaff-csv-radio-tab"><?php _e('TAB', $this->the_plugin->localizationName);?> <code>TAB</code></label>
											</div>
											<div class="aiowaff-delimiters">
												<input class="aiowaff-addASINtoQueue" type="button" value="<?php _e('Add ASIN codes to Queue', $this->the_plugin->localizationName);?>" />
											</div>
			            				
			            				</form>	+
			            			</div>
			            			
			            		</div>
		            		</div>
						</div>
						<?php } ?>
						<!-- end Envato Content Area -->

									</div>
									<?php } ?>

									<?php if ($this->provider_is_enabled('ebay')) { ?>
									<div id="aiowaff-content-ebay" class="aiowaff-insane-tab-content">

						<!-- Ebay Content Area -->
						<?php
						$provider_status = $this->providerSettingsValidation( 'ebay' );
						if ( 'invalid' == $provider_status['status'] ) {
							echo $provider_status['html'];
						} else {
						?>
						<div class="aiowaff-insane-container aiowaff-insane-tabs">
							<div class="aiowaff-insane-panel-headline aiowaff-operation-message">
								<span></span>
                				<a href="#aiowaff-content-search-ebay" class="on"><?php _e('SEARCH FOR PRODUCTS', $this->the_plugin->localizationName);?></a>
                				<a href="#aiowaff-content-bulk-ebay"><?php _e('ALREADY HAVE A LIST?', $this->the_plugin->localizationName);?></a>
							</div>
							<div class="aiowaff-insane-tabs-content">
								<div class="aiowaff-content-scroll">
									
			            			<div id="aiowaff-content-search-ebay" class="aiowaff-insane-tab-content">
			            				<!-- Search buttons -->
			            				<div class="aiowaff-insane-tab-search-buttons-container">
			            					<form class="aiowaff-search-products">
				            					<ul class="aiowaff-insane-tab-search-buttons">
				            						<li>
			            								<span class="tooltip" title="The string to search for"><i class="fa fa-search"></i></span>
			            							 	<input type="text" id="aiowaff-search-keyword" name="aiowaff-search[keyword]" placeholder="<?php _e('Keyword', $this->the_plugin->localizationName);?>">
				            						</li>
				            						<li class="aiowaff-select-on-category">
				            							<span class="tooltip" title="Choose Category"><i class="fa fa-sitemap"></i></span>
                                                        <select class="aiowaff-search-category" name="aiowaff-search[category]">
                                                            <option value="" disabled="disabled"><?php _e('Category', $this->the_plugin->localizationName);?></option>
                                                            <option value="AllCategories" selected="selected" data-nodeid="all"><?php _e('All categories', $this->the_plugin->localizationName);?></option>
                                                            <?php echo $this->get_categories_html('ebay'); ?>
                                                        </select>
				            						</li>
                                                    <li>
                                                        <span class="tooltip" title="Choose number of pages to search for results from webservice"><i class="fa fa-briefcase"></i></span>
                                                        <select class="aiowaff-search-nbpages" name="aiowaff-search[nbpages]">
                                                            <option value="" disabled="disabled"><?php _e('Grab', $this->the_plugin->localizationName);?></option>
                                                        <?php
                                                            for ($i = 1; $i <= 10; ++$i) {
                                                                $text = $i == 1 ? $lang['search_pages_single'] : sprintf( $lang['search_pages_many'], $i );
                                                                $selected = $i == 1 ? 'selected="selected"' : '';
                                                                echo '<option value="'.$i.'" '.$selected.'>'.$text.'</option>';
                                                            }
                                                        ?>
                                                        </select>
                                                        <span class="aiowaff-choose-between"></span>
                                                    </li>
				            						<li>
			            								<span class="tooltip" title="Page number (max. 100): <span style='color: red;'>if you choose this, the Grab number of pages option will not be used</span>"><i class="fa fa-briefcase"></i></span>
			            							 	<input type="text" name="aiowaff-search[page]" placeholder="<?php _e('Page nb', $this->the_plugin->localizationName);?>">
				            						</li>
				            						<?php
				            						$getEnvatoSearchParams = $this->get_category_params_html('return', array(
										                'what_params'           => 'all',
										                'category'              => '',
										                'nodeid'                => 0,
										                'provider'              => 'ebay',
										            ));
													if ( 'valid' == $getEnvatoSearchParams['status'] ) {
														echo $getEnvatoSearchParams['html'];
													}
				            						?>
				            						<li class="button-block">
				            							<input type="submit" value="<?php _e('Launch search', $this->the_plugin->localizationName);?>" class="aiowaff-button red" />
				            						</li>
				            					</ul>
			            					</form>
			            				</div>
			            			</div>
			            			
			            			<div id="aiowaff-content-bulk-ebay" class="aiowaff-insane-tab-content">
			            			    <!-- ASINs Bulk Import -->
			            				<form class="aiowaff-import-products aiowaff-bulk-products">
			            					<h3><?php _e('ASIN codes', $this->the_plugin->localizationName);?>:</h3>
			            					<textarea class="aiowaff-content-bulk-asin"></textarea>
			            					<div class="aiowaff-delimiters">
												<span><?php _e('ASIN delimiter by', $this->the_plugin->localizationName);?>:</span>
												<input type="radio" val="newline" name="aiowaff-csv-delimiter" checked="" class="aiowaff-csv-radio" id="aiowaff-csv-radio-newline"><label for="aiowaff-csv-radio-newline"><?php _e('New line', $this->the_plugin->localizationName);?> <code>\n</code></label>
												<input type="radio" val="comma" name="aiowaff-csv-delimiter" id="aiowaff-csv-radio-comma"><label for="aiowaff-csv-radio-comma"><?php _e('Comma', $this->the_plugin->localizationName);?> <code>,</code></label>
												<input type="radio" val="tab" name="aiowaff-csv-delimiter" id="aiowaff-csv-radio-tab"><label for="aiowaff-csv-radio-tab"><?php _e('TAB', $this->the_plugin->localizationName);?> <code>TAB</code></label>
											</div>
											<div class="aiowaff-delimiters">
												<input class="aiowaff-addASINtoQueue" type="button" value="<?php _e('Add ASIN codes to Queue', $this->the_plugin->localizationName);?>" />
											</div>
			            				</form>	
			            			</div>
			            			
			            		</div>
		            		</div>
						</div>
						<?php } ?>
						<!-- end Ebay Content Area -->
										
									</div>
									<?php } ?>

									<?php if ($this->provider_is_enabled('alibaba')) { ?>
									<div id="aiowaff-content-alibaba" class="aiowaff-insane-tab-content">

						<!-- Alibaba Content Area -->
						<?php
						$provider_status = $this->providerSettingsValidation( 'alibaba' );
						if ( 'invalid' == $provider_status['status'] ) {
							echo $provider_status['html'];
						} else {
						?>
						<div class="aiowaff-insane-container aiowaff-insane-tabs">
							<div class="aiowaff-insane-panel-headline aiowaff-operation-message">
                    			<span></span>
                				<a href="#aiowaff-content-search-alibaba" class="on"><?php _e('SEARCH FOR PRODUCTS', $this->the_plugin->localizationName);?></a>
							</div>
							<div class="aiowaff-insane-tabs-content">
								<div class="aiowaff-content-scroll">

			            			<div id="aiowaff-content-search-alibaba" class="aiowaff-insane-tab-content">
			            				<!-- Search buttons -->
			            				<div class="aiowaff-insane-tab-search-buttons-container">
			            					<form class="aiowaff-search-products">
				            					<ul class="aiowaff-insane-tab-search-buttons">
				            						<li>
			            								<span class="tooltip" title="Choose Keyword"><i class="fa fa-search"></i></span>
			            							 	<input type="text" id="aiowaff-search-keyword" name="aiowaff-search[keyword]" placeholder="<?php _e('Keyword', $this->the_plugin->localizationName);?>">
				            						</li>
				            						<li class="aiowaff-select-on-category">
				            							<span class="tooltip" title="Choose Category"><i class="fa fa-sitemap"></i></span>
                                                        <select class="aiowaff-search-category" name="aiowaff-search[category]">
                                                            <option value="" disabled="disabled"><?php _e('Category', $this->the_plugin->localizationName);?></option>
                                                            <?php echo $this->get_categories_html('alibaba'); ?>
                                                        </select>
				            						</li>
                                                    <li>
                                                        <span class="tooltip" title="Choose number of pages to search for results from webservice"><i class="fa fa-briefcase"></i></span>
                                                        <select class="aiowaff-search-nbpages" name="aiowaff-search[nbpages]">
                                                            <option value="" disabled="disabled"><?php _e('Grab', $this->the_plugin->localizationName);?></option>
                                                        <?php
                                                            for ($i = 1; $i <= 10; ++$i) {
                                                                $text = $i == 1 ? $lang['search_pages_single'] : sprintf( $lang['search_pages_many'], $i );
                                                                $selected = $i == 1 ? 'selected="selected"' : '';
                                                                echo '<option value="'.$i.'" '.$selected.'>'.$text.'</option>';
                                                            }
                                                        ?>
                                                        </select>
                                                        <span class="aiowaff-choose-between"></span>
                                                    </li>
				            						<li>
			            								<span class="tooltip" title="Page number: <span style='color: red;'>if you choose this, the Grab number of pages option will not be used</span>"><i class="fa fa-briefcase"></i></span>
			            							 	<input type="text" name="aiowaff-search[page]" placeholder="<?php _e('Page nb', $this->the_plugin->localizationName);?>">
				            						</li>
				            						<li class="button-block">
				            							<input type="submit" value="<?php _e('Launch search', $this->the_plugin->localizationName);?>" class="aiowaff-button red" />
				            						</li>
				            					</ul>
			            					</form>
			            				</div>
			            			</div>

			            		</div>
							</div>
						</div>
						<?php } ?>
						<!-- end Alibaba Content Area -->

									</div>
									<?php } ?>

			            			<!-- latest search operation status --> 
			            			<div id="aiowaff-loadprods-status"></div>

			            		</div>
		            		</div>
						</div><!-- end Parents TABS -->
						
                        <div class="aiowaff-insane-container aiowaff-insane-tabs aiowaff-insane-container-logs" id="aiowaff-logs-load-products">
                            <div class="aiowaff-insane-panel-headline">
                                <a href="#aiowaff-insane-loadstatus" class="on">
                                    <span><img src="<?php echo $this->module_folder;?>/assets/text_logs.png" alt="logs"></span>
                                    <?php _e('Load in Queue Log', $this->the_plugin->localizationName);?>
                                </a>
                            </div>
                            <div class="aiowaff-insane-tabs-content aiowaff-insane-status">
                                <div id="aiowaff-insane-loadstatus" class="aiowaff-insane-tab-content">
                                    <ul class="aiowaff-insane-logs">
                                        <?php /*<li class="aiowaff-log-notice">
                                            <i class="fa fa-info"></i>
                                            <span class="aiowaff-insane-logs-frame">Yesterday 10:24 PM</span>
                                            <p>You deleted the file intermediate-page_1000px_re…rid_02.jpg.</p>
                                        </li>
                                        <li class="aiowaff-log-error">
                                            <i class="fa fa-minus-circle"></i>
                                            <span class="aiowaff-insane-logs-frame">Yesterday 10:24 PM</span>
                                            <p>You deleted the file intermediate-page_1000px_re…rid_02.jpg.</p>
                                        </li>
                                        <li class="aiowaff-log-success">
                                            <i class="fa fa-check-circle"></i>
                                            <span class="aiowaff-insane-logs-frame">Yesterday 10:24 PM</span>
                                            <p>You deleted the file intermediate-page_1000px_re…rid_02.jpg.</p>
                                        </li>*/ ?>
                                    </ul>
                                </div>
                            </div>
                        </div>

						<div class="aiowaff-insane-container aiowaff-insane-tabs">
                    		<div class="aiowaff-insane-panel-headline aiowaff-check-all">
                    			<span>
                    				<input type="checkbox" value="added" name="check-all" id="squaredThree-all" checked>
                    				<label for="squaredThree-all">uncheck all</label>
                    			</span>
                    			<a href="#aiowaff-queued-products" class="on">
                    				<span><img src="<?php echo $this->module_folder;?>/assets/products_icon.png" alt="products"></span>
                    				<?php _e('queued products', $this->the_plugin->localizationName);?>
                    			</a>
                                <a href="#aiowaff-export-asins">
                                    <span><img src="<?php echo $this->module_folder;?>/assets/text_logs.png" alt="logs"></span>
                                    <?php _e('Export ASINs', $this->the_plugin->localizationName);?>
                                </a>
							</div>
							<div class="aiowaff-insane-tabs-content">
		            			<div id="aiowaff-queued-products" class="aiowaff-insane-tab-content">
                                    <div id="aiowaff-queued-message">
                                        <?php echo 'There are no products loaded and selected for import in the Queue. You should use one of the above methods first: Search for Products, Grab Products, Already have a list.'; ?>
                                    </div>
		            				<div class="WZC-products-scroll-cointainer">
                                        <ul class="WZC-keyword-attached aiowaff-insane-bigscroll">
		            					<?php
		            					/*$totals = 32; 
		            					for( $i = 0; $i < $totals; $i++ ){
		            					?>
										    <li>
										        <span class="aiowaff-checked-product squaredThree"><input type="checkbox" value="added" name="check" id="squaredThree-1" checked><label for="squaredThree-1"></label></span>
										        <a target="_blank" href="http://ecx.images-amazon.com/images/I/5141E97ulwL._SL75_.jpg" class="WZC-keyword-attached-image"><img src="http://ecx.images-amazon.com/images/I/5141E97ulwL._SL75_.jpg"></a>
										        <div class="WZC-keyword-attached-phrase"><span>galaxy note</span></div>
										        <div class="WZC-keyword-attached-title">Samsung Galaxy Note 4 SM-N910H Black Factory Unloc</div>
										        <div class="WZC-keyword-attached-brand">by: <span>Samsung</span></div>
										        <div class="WZC-keyword-attached-prices"><del>$1,029.99</del><span>$1,029.99</span></div>
										    </li>
									    <?php
										}*/
										?>
										</ul>
									</div>
		            			</div>
		            			<div id="aiowaff-queued-results-stats" class="aiowaff-insane-tab-product-search-results-stats">
									<label class="aiowaff-stats-block aiowaff-stats-found">
										<?php _e('Found', $this->the_plugin->localizationName);?>:
										<span><span>0</span> <?php _e('asins', $this->the_plugin->localizationName);?></span>
									</label>
									<label class="aiowaff-stats-block aiowaff-stats-loaded">
										<?php _e('Loaded and valid', $this->the_plugin->localizationName);?>:
										<span><span>0</span> <?php _e('products', $this->the_plugin->localizationName);?></span>
										<?php /*<p>(products are still being loaded in the background)</p>*/ ?>
									</label>
									<label class="aiowaff-stats-block aiowaff-stats-selected">
										<?php _e('Selected for Import', $this->the_plugin->localizationName);?>:
										<span><span>0</span> <?php _e('products', $this->the_plugin->localizationName);?></span>
									</label>
                                    <label class="aiowaff-stats-block aiowaff-stats-imported">
                                        <?php _e('Imported', $this->the_plugin->localizationName);?>:
                                        <span><span>0</span> <?php _e('products', $this->the_plugin->localizationName);?></span>
                                    </label>
                                    <label class="aiowaff-stats-block aiowaff-stats-import_errors">
                                        <?php _e('Errors on Import', $this->the_plugin->localizationName);?>:
                                        <span><span>0</span> <?php _e('products', $this->the_plugin->localizationName);?></span>
                                    </label>
									
									<a href="#" id="aiowaff-expand-all">
										<span><i class="fa fa-expand"></i> <?php _e('show products', $this->the_plugin->localizationName);?></span>
										<span style="display:none"><i class="fa fa-times"></i> <?php _e('collapse products list', $this->the_plugin->localizationName);?></span>
									</a>
								</div>
		            		
                                <div id="aiowaff-export-asins" class="aiowaff-insane-tab-content">
                                    <!-- ASINs Bulk export -->
                                    <form id="aiowaff-export-form" class="aiowaff-import-products">
                                        <div class="aiowaff-delimiters">
                                            <span><?php _e('ASIN delimiter by', $this->the_plugin->localizationName);?>:</span>
                                            <input type="radio" val="newline" name="aiowaff-export-delimiter" checked="" class="aiowaff-csv-radio" id="aiowaff-export-radio-newline"><label for="aiowaff-export-radio-newline"><?php _e('New line', $this->the_plugin->localizationName);?> <code>\n</code></label>
                                            <input type="radio" val="comma" name="aiowaff-export-delimiter" id="aiowaff-export-radio-comma"><label for="aiowaff-export-radio-comma"><?php _e('Comma', $this->the_plugin->localizationName);?> <code>,</code></label>
                                            <input type="radio" val="tab" name="aiowaff-export-delimiter" id="aiowaff-export-radio-tab"><label for="aiowaff-export-radio-tab"><?php _e('TAB', $this->the_plugin->localizationName);?> <code>TAB</code></label>
                                        </div>
                                        <div class="aiowaff-delimiters">
                                            <span>Export ASINs type:</span>
                                            <select id="aiowaff-export-asins-type" name="aiowaff-export-asins-type">
                                                <option value="1"><?php _e('All Loaded and valid', $this->the_plugin->localizationName); ?></option>
                                                <option value="2"><?php _e('All Selected for Import', $this->the_plugin->localizationName); ?></option>
                                                <option value="3"><?php _e('All Imported Successfully', $this->the_plugin->localizationName); ?></option>
                                                <option value="4"><?php _e('All Not Imported - Errors occured', $this->the_plugin->localizationName); ?></option>
                                                <option value="5"><?php _e('Remained Loaded in Queue', $this->the_plugin->localizationName); ?></option>
                                                <option value="6"><?php _e('Remained Selected in Queue', $this->the_plugin->localizationName); ?></option>
                                                <option value="7"><?php _e('All Found invalid', $this->the_plugin->localizationName); ?></option>
                                            </select>
                                            <input id="aiowaff-export-button" type="button" value="<?php _e('Export ASINs', $this->the_plugin->localizationName);?>" />
                                        </div>
                                    </form> 
                                </div>
                                
                            </div>
						</div>

						<div class="aiowaff-insane-container aiowaff-insane-tabs">
						    <div class="aiowaff-insane-buton-logs" data-logcontainer="aiowaff-logs-import-products"><?php _e('View Messages Log', $this->the_plugin->localizationName); ?></div>
                    		<div class="aiowaff-insane-panel-headline">
                    			<a href="#aiowaff-insane-import-parameters" class="on">
                    				<span><img src="<?php echo $this->module_folder;?>/assets/insane_icon.png" alt="insane settings"></span>
                    				<?php _e('Insane Mode Import Fine Tuning', $this->the_plugin->localizationName);?>
                    			</a>
							</div>
							<div class="aiowaff-insane-tabs-content">
								<div class="aiowaff-insane-import-parameters" id="aiowaff-insane-import-parameters">

									<ul>
                                        <li>
                                            <h4><?php _e('Image Import Type', $this->the_plugin->localizationName);?></h4>
                                            <span class="aiowaff-checked-product squaredThree">
                                                <input type="radio" value="default" name="import-parameters[import_type]" id="import-parameters-import_type-default" <?php echo $import_params['import_type'] == 'default' ? 'checked="checked"' : ''; ?>></span>
                                            <label for="import-parameters-import_type-default"><?php _e('Download images at import', $this->the_plugin->localizationName);?></label>
                                            <br />
                                            <span class="aiowaff-checked-product squaredThree">
                                                <input type="radio" value="asynchronous" name="import-parameters[import_type]" id="import-parameters-import_type-asynchronous" <?php echo $import_params['import_type'] == 'asynchronous' ? 'checked="checked"' : ''; ?>></span>
                                            <label for="import-parameters-import_type-asynchronous"><?php _e('Asynchronuous image download', $this->the_plugin->localizationName);?></label>
                                        </li>
										<li>
											<h4><?php _e('Number of Images', $this->the_plugin->localizationName);?></h4>
											<input type="range" min="1" max="100" step="1" value="<?php echo $import_params['number_of_images'] === 'all' ? 100 : $import_params['number_of_images']; ?>" name="import-parameters[nbimages]" id="import-parameters-nbimages">
											<output for="import-parameters-nbimages" id="import-parameters-nbimages-output"><?php echo $import_params['number_of_images']; ?></output>
										</li>
										<li>
											<h4><?php _e('Number of Variations', $this->the_plugin->localizationName);?></h4>
											<input type="range" min="0" max="100" step="1" value="<?php echo $import_params['number_of_variations'] === 'all' ? 100 : $import_params['number_of_variations']; ?>" name="import-parameters[nbvariations]" id="import-parameters-nbvariations">
											<output for="import-parameters-nbvariations" id="import-parameters-nbvariations-output"><?php echo $import_params['number_of_variations']; ?></output>
										</li>
                                        <li>
                                            <h4><?php _e('Others', $this->the_plugin->localizationName);?></h4>
                                            <span class="aiowaff-checked-product squaredThree">
                                                <input type="checkbox" value="added" name="import-parameters[spin]" id="import-parameters-spin" <?php echo $import_params['spin_at_import'] ? 'checked="checked"' : ''; ?>></span>
                                            <label for="import-parameters-spin"><?php _e('Spin on Import', $this->the_plugin->localizationName);?></label>
                                            <br />
                                            <span class="aiowaff-checked-product squaredThree">
                                                <input type="checkbox" value="added" name="import-parameters[attributes]" id="import-parameters-attributes" <?php echo $import_params['import_attributes'] ? 'checked="checked"' : ''; ?>></span>
                                            <label for="import-parameters-attributes"><?php _e('Import attributes', $this->the_plugin->localizationName);?></label>
                                        </li>
                                        <li>
                                            <h4><?php _e('Import in', $this->the_plugin->localizationName);?></h4>
                                            <?php echo $this->get_importin_category(); ?>
                                        </li>
                                        <li class="aiowaff-import-products-button-box">
                                        	<a href="#" id="aiowaff-import-products-button">
												<i class="fa fa-exclamation"></i>
												<?php _e('IMPORT PRODUCTS', $this->the_plugin->localizationName);?>
											</a>
                                        </li>
                                        <!--li>
                                            <h4><?php _e('Run', $this->the_plugin->localizationName);?></h4>
                                            <input type="button" value="<?php _e('IMPORT PRODUCTS', $this->the_plugin->localizationName);?>" id="aiowaff-import-products-button" class="aiowaff-button orange">
                                        </li-->
									</ul>
									
									
									
								    <div class="aiowaff-insane-import-estimate">
    		            				<div class="aiowaff-insane-import-ETA">
    		            					<p>
    		            						<?php _e('ESTIMATED TIME', $this->the_plugin->localizationName);?><br />
    		            						<span><?php //_e('5 MINUTES', $this->the_plugin->localizationName);?></span>
    		            					</p>		            				
    		            				</div>
    		            				<div class="aiowaff-insane-import-ETA-triangle"></div>	
    		            				<div id="aiowaff-speedometer">
    		            					<div class="speedometer-center">
    		            						<div class="speedometer-center-middle">
    		            							<canvas id="speedometer-markers" width="230" height="230"></canvas>
    		            							<div id="speedometer-needle">
    		            								<div class="speedometer-needle-center"></div>
    		            							</div>
    		            						</div>
    		            						<span class="speedometer-step"></span>
    			            					<span class="speedometer-step"></span>
    			            					<span class="speedometer-step"></span>
    			            					<span class="speedometer-step"></span>
    			            					<span class="speedometer-step"></span>
    		            					</div>
    		            					
    		            					<label id="aiowaff-speedometer-name"><i>5</i> <?php _e('Products per minute', $this->the_plugin->localizationName);?></label>
    		            				</div>
    		            				<?php
    		            				/*
                                        <input type="range" min="5" max="105" value="5" id="test-speedometer" step="10">
                                        */
                                        ?>
                                        <div class="aiowaff-insane-import-ETA-logo aiowaff-insane-logo-level1">
                                            <p><?php echo $lang['speed_level1']; ?></p>
                                        </div>
                                    </div>
		            			</div>
		            		</div>
						</div>
						
						<div class="aiowaff-insane-container aiowaff-insane-tabs aiowaff-insane-container-logs" id="aiowaff-logs-import-products">
                    		<div class="aiowaff-insane-panel-headline">
                    			<a href="#aiowaff-insane-importstatus" class="on">
                    				<span><img src="<?php echo $this->module_folder;?>/assets/text_logs.png" alt="logs"></span>
                    				<?php _e('Import Log', $this->the_plugin->localizationName);?>
                    			</a>
							</div>
							<div class="aiowaff-insane-tabs-content aiowaff-insane-status">
		            			<div id="aiowaff-insane-importstatus" class="aiowaff-insane-tab-content">
		            				<ul class="aiowaff-insane-logs">
		            					<?php /*<li class="aiowaff-log-notice">
		            						<i class="fa fa-info"></i>
		            						<span class="aiowaff-insane-logs-frame">Yesterday 10:24 PM</span>
		            						<p>You deleted the file intermediate-page_1000px_re…rid_02.jpg.</p>
		            					</li>
		            					<li class="aiowaff-log-error">
		            						<i class="fa fa-minus-circle"></i>
		            						<span class="aiowaff-insane-logs-frame">Yesterday 10:24 PM</span>
		            						<p>You deleted the file intermediate-page_1000px_re…rid_02.jpg.</p>
		            					</li>
		            					<li class="aiowaff-log-success">
		            						<i class="fa fa-check-circle"></i>
		            						<span class="aiowaff-insane-logs-frame">Yesterday 10:24 PM</span>
		            						<p>You deleted the file intermediate-page_1000px_re…rid_02.jpg.</p>
		            					</li>*/ ?>
		            				</ul>
		            			</div>
		            		</div>
						</div>

                    <?php
                    } // end moduleValidation
                    ?>
					</div><!-- end Main Content Wrapper -->
				</div>
			</div>
		</div>

        <script type="text/javascript" src="<?php echo $this->module_folder;?>rangeslider/rangeslider.min.js"></script>
		<script type="text/javascript" src="<?php echo $this->module_folder;?>app.class.js" ></script>

<?php 
		}

        public function moduleValidation() {
            $ret = array(
                'status'            => false,
                'html'              => ''
            );
            
            // AccessKeyID, SecretAccessKey, AffiliateId, main_aff_id
            
            // find if user makes the setup
            $module_settings = $this->the_plugin->getAllSettings('array', 'amazon');

			$module_mandatoryFields = array(
                'AccessKeyID'           => false,
                'SecretAccessKey'       => false,
                'main_aff_id'           => false
            );
            if ( isset($module_settings['AccessKeyID']) && !empty($module_settings['AccessKeyID']) ) {
                $module_mandatoryFields['AccessKeyID'] = true;
            }
            if ( isset($module_settings['SecretAccessKey']) && !empty($module_settings['SecretAccessKey']) ) {
                $module_mandatoryFields['SecretAccessKey'] = true;
            }
            if ( isset($module_settings['main_aff_id']) && !empty($module_settings['main_aff_id']) ) {
                $module_mandatoryFields['main_aff_id'] = true;
            }
            $mandatoryValid = true;
            foreach ($module_mandatoryFields as $k=>$v) {
                if ( !$v ) {
                    $mandatoryValid = false;
                    break;
                }
            }
            
            $module_name = 'Products Importer Mode';
			/*
            if ( !$mandatoryValid ) {
                $error_number = 1; // from config.php / errors key
                
                $ret['html'] = $this->the_plugin->print_module_error( $this->module, $error_number, 'Error: Unable to use '.$module_name.' module, yet!' );
                return $ret;
            }
			*/
            
            if( !$this->the_plugin->is_woocommerce_installed() ) {  
                $error_number = 2; // from config.php / errors key
                
                $ret['html'] = $this->the_plugin->print_module_error( $this->module, $error_number, 'Error: Unable to use '.$module_name.' module, yet!' );
                return $ret;
            }
            
            /*if( !extension_loaded('soap') ) {
                if( !(extension_loaded("curl") && function_exists('curl_init')) ) {
                    $error_number = 3; // from config.php / errors key
                
                    $ret['html'] = $this->the_plugin->print_module_error( $this->module, $error_number, 'Error: Unable to use '.$module_name.' module, yet!' );
                    return $ret;    
                }   
            }*/

            if( !(extension_loaded("curl") && function_exists('curl_init')) ) {  
                $error_number = 4; // from config.php / errors key
                
                $ret['html'] = $this->the_plugin->print_module_error( $this->module, $error_number, 'Error: Unable to use '.$module_name.' module, yet!' );
                return $ret;
            }
            
            $ret['status'] = true;
            return $ret;
        }		
        
		public function providerSettingsValidation( $provider ) {
			$ret = array('status' => 'invalid', 'html' => '');

			$provider_status = $this->the_plugin->verify_mandatory_settings( $provider );
			$ret = array_merge($ret, array(
				'status'			=> $provider_status['status'],
				'html'				=> 'valid' == $provider_status['status'] ? 'ok'
					: '<div class="aiowaff-provider-validation-message">Error: Unable to import products from this provider: Setup the provider config mandatory settings ( ' . implode(', ', $provider_status['fields_title']) . ' ).</div>',
			));
			return $ret;
		}

        
        /**
         * Ajax requests
         */
		public function ajax_autocomplete()
		{
			$ret = array();
			$keyword = isset($_REQUEST['keyword']) ? $_REQUEST['keyword'] : '';
			if( trim($keyword) == "" ){
				$ret['status'] = 'invalid';
			}
			else{
				$response = wp_remote_get( 'http://completion.amazon.com/search/complete?method=completion&q=' . ( $keyword ) . '&search-alias=aps&client=amzn-search-suggestions/--&mkt=1' );
				if( is_array($response) && $response['headers']['content-type'] == 'text/javascript;charset=UTF-8' ) {
					$body = $response['body'];
					
					$array = json_decode( $body, true );
					// if found any results
					if( isset($array[1]) && count($array[1]) > 0 ){
						$array[1] = array_filter( $array[1] );
						if( count($array[1]) > 0 ){
							$ret['status'] = 'valid';
							$ret['data'] = $array[1]; 
						}
					}  
				}
			}
			
			
			die( json_encode( $ret ) ); 
		}
		
		public function ajax_request( $retType='die', $pms=array() )
		{
            $requestData = array(
                'action'             => isset($_REQUEST['sub_action']) ? $_REQUEST['sub_action'] : '',
                'operation'          => isset($_REQUEST['operation']) ? $_REQUEST['operation'] : '',
                'operation_id'       => isset($_REQUEST['operation_id']) ? $_REQUEST['operation_id'] : '',
            );
            extract($requestData);
            
            $ret = array(
                'status'        => 'invalid',
                'msg'           => '',
            );
            
            if ($action == 'heartbeat' ) {
                
                $opStatusMsg = $this->the_plugin->opStatusMsgGet( '<br />', 'file' );
                
                $_opStatusMsg = array(
                    'operation'         => isset($opStatusMsg['operation']) ? $opStatusMsg['operation'] : '',
                    'operation_id'      => isset($opStatusMsg['operation_id']) ? $opStatusMsg['operation_id'] : '',
                    'msg'               => isset($opStatusMsg['msg']) ? $opStatusMsg['msg'] : '',
                );
  
                if ( $operation_id != $_opStatusMsg['operation_id'] ) {
                    $_opStatusMsg['msg'] = '';
                }

                $ret = array_merge($ret, array(
                    'status'    => 'valid',
                    'msg'       => $_opStatusMsg['msg'],
                ));
            }
            
            if ( $retType == 'return' ) { return $ret; }
            else { die( json_encode( $ret ) ); }
		}
    
    
        /**
         * Ajax - Load Products
         */
        // ajax/ grab asins from amazon page url
        public function loadprods_grab_parse_url() {
            //$durationQueue = array(); // Duration Queue
            $this->the_plugin->timer_start(); // Start Timer

            $base = array(
                'status'        => 'invalid',
                'msg'           => '',
                'asins'         => array(),
            );
            
            $asins = array();
            $params = array();
            parse_str( $_REQUEST['params'], $params );
            
            $remote_url = $params['aiowaff-grab']['url'];
            $page_type = $params['aiowaff-grab']['page-type'];
            $operation_id = isset($_REQUEST['operation_id']) ? $_REQUEST['operation_id'] : '';
            
            // status messages
            $this->the_plugin->opStatusMsgInit(array(
                'operation_id'  => $operation_id,
                'operation'     => 'load_by_grab',
                'msg_header'    => __('Founding products from remote amazon url...', 'aiowaff'),
            ));
            
            if ( trim($remote_url) == "" ) {
                // status messages
                $this->the_plugin->opStatusMsgSet(array(
                    'msg'       => self::MSG_SEP . __(' Please provide a valid Amazon Url.', $this->the_plugin->localizationName),
                    'duration'  => $this->the_plugin->timer_end(), // End Timer
                ));
            } else {
                require_once( $this->the_plugin->cfg['paths']['scripts_dir_path'] . '/php-query/phpQuery.php' );
 
                $input = wp_remote_get( 
                    $remote_url, 
                    array( 'timeout' => 30 ) 
                );
                
                $response = wp_remote_retrieve_body( $input );
                $doc = phpQuery::newDocument( $response );

                // Best Sellers page type
                if( $page_type == 'best sellers' ){
                    $container = $doc->find( '#zg_left_col1' );
                    
                    if (strpos($remote_url, 'ref=') !== false) {
                        $products = $container->find(".zg_itemImmersion .zg_itemWrapper .zg_image"); 
                    } else {
                        $products = $container->find(".zg_item .zg_image");
                    }
                    
                    if( (int)$products->size() > 0 ){
                        foreach ( $products as $product ) {
                            $product_url = trim(pq( $product )->find("a")->attr('href'));
                            if( $product_url != "" ){
                                $product_url = @urldecode( $product_url );
								$product_url = explode("/", $product_url );
                                $asins[] = end( $product_url );
                            }                   
                        } 
                    }
                }
                
                // Deals page type
                elseif( $page_type == 'deals' ){
                    $container = $doc->find( '#mainResults' );
                     
                    if ($container->find( ".prod" ) != "") {
                        $products = $container->find( ".prod" );
                    } else {
                            $products = $container->find( ".product" );
                        }

                    if( (int)$products->size() > 0 ){
                        foreach ( $products as $product ) {
                            $asin_item = pq( $product )->attr('name');     
                            $asins[] = $asin_item;                  
                        } 
                    }
                }

                // Top Rated, Most Wished For, Movers & Shakers, Hot New Releases, Best Sellers Cattegory, Gift Ideas page type
                elseif( $page_type == 'top rated' || 'most wished for' || 'movers & shakers' || 'hot new releases' || 'best sellers cattegory' || 'gift ideas' ){
                    $container = $doc->find( '#zg_left_col1' );
  
                    if (strpos($remote_url, 'ref=') !== false) {
                        $products = $container->find(".zg_itemImmersion .zg_itemWrapper .zg_image"); 
                    } else {
                        $products = $container->find(".zg_item .zg_image");
                    }
                    if( (int)$products->size() > 0 ){
                        foreach ( $products as $product ) {
                            $product_url = trim(pq( $product )->find("a")->attr('href'));
                            if( $product_url != "" ){
                                $product_url = @urldecode( $product_url );
                                $tmp = explode("/", $product_url );
                                $asins[] = end( $tmp );
                            }                   
                        } 
                    }
                }



                // New Arrivals page type
                if( $page_type == 'new arrivals' ){
                    $container = $doc->find( '#resultsCol' );
                    
                    $products = $container->find(".prod .image");
                    if( (int)$products->size() > 0 ){
                        foreach ( $products as $product ) {
                            $product_url = trim(pq( $product )->find("a")->attr('href'));
                            if( $product_url != "" ){
                                $product_url = @urldecode( $product_url );
                                $asins[] = end( explode("/", $product_url ) );
                            }                   
                        } 
                    }
                }
                
                // removes duplicate values
                $asins = array_unique($asins);

                if ( !empty($asins) ) {

                    $base = array_merge($base, array(
                        'status'    => 'valid',
                        'asins'     => $asins,
                    ));

                    // status messages
                    $this->the_plugin->opStatusMsgSet(array(
                        'status'    => 'valid',
                        'msg'       => self::MSG_SEP . sprintf( __(' The script was successfully. %s ASINs found: %s', $this->the_plugin->localizationName), count($base['asins']), implode(', ', $base['asins']) ),
                        'duration'  => $this->the_plugin->timer_end(), // End Timer
                    ));

                } else {
                    // status messages
                    $this->the_plugin->opStatusMsgSet(array(
                        'msg'       => self::MSG_SEP . __(' The script was unable to grab any ASIN codes. Please try again using another Page Type parameter.', $this->the_plugin->localizationName),
                        'duration'  => $this->the_plugin->timer_end(), // End Timer
                    ));
                }
            }

            $opStatusMsg = $this->the_plugin->opStatusMsgGet();
            $base['msg'] = $opStatusMsg['msg'];
            
            die( json_encode( $base ) );
        }

        // ajax/ load products in queue based on ASINs list
        public function loadprods_queue_by_asin( $retType='die', $pms=array() ) {
   
            $durationQueue = array(); // Duration Queue
            $this->the_plugin->timer_start(); // Start Timer
            
            //$amz_setup = $this->the_plugin->getAllSettings('array', 'amazon');
            $amz_setup = $this->settings;
            $do_parent_setting = !isset($amz_setup['variation_force_parent'])
                || ( isset($amz_setup['variation_force_parent']) && $amz_setup['variation_force_parent'] != 'no' )
                ? true : false;

            $requestData = array(
                'operation'             => isset($_REQUEST['operation']) ? $_REQUEST['operation'] : '',
                'asins'                 => isset($_REQUEST['asins']) ? (array) $_REQUEST['asins'] : array(),
                'asins_inqueue'         => isset($_REQUEST['asins_inqueue']) ? (array) $_REQUEST['asins_inqueue'] : array(),
                'page'                  => isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : 0,
                'operation_id'          => isset($_REQUEST['operation_id']) ? $_REQUEST['operation_id'] : '',
                'provider'              => isset($_REQUEST['provider']) ? $_REQUEST['provider'] : '',
            );
            foreach ($requestData as $rk => $rv) {
                if ( isset($pms["$rk"]) ) {
                    $new_val = $pms["$rk"];
                    $new_val = in_array($rk, array('asins', 'asins_inqueue')) ? (array) $new_val : $new_val;
                    $requestData["$rk"] = $new_val;
                }
            }
  
            $requestData['asins'] = array_unique( $requestData['asins'] );
            $requestData['asins_inqueue'] = array_unique( $requestData['asins_inqueue'] );
            extract($requestData);
			//$provider = $requestData['provider'];
            
            $prods = array();
            $ret = array(
                'status'        => 'invalid',
                'nb_amz_req'    => 0, // number of amazon requests
                'asins'         => array(
                    'found'             => array(), // found no matter if valid or not
                    'remained'          => $asins, // asins remained to be parsed in future requests 
                    'inqueue'           => array(), // already in queue
                    'loaded'            => array(), // valid & will be loaded in queue
                    'invalid'           => array(), // invalid & will NOT be loaded
                    'imported'          => array(), // already imported
                    'variations'        => array(), // variations: child -> parent

                    'from_cache'        => array(), // get from cache files
                    'from_amz'          => array(), // get straight from amazon request
                ),
                'msg'           => '',
                'duration'      => 0,
            );
            $ret['asins']['inqueue'] = $asins_inqueue;
            
            if ( $operation != 'search' ) {
                // status messages
                $this->the_plugin->opStatusMsgInit(array(
                    'operation_id'  => $requestData['operation_id'],
                    'operation'     => 'load_by_asin',
                    'msg_header'    => __('Loading products by ASIN...', 'aiowaff'),
                ));
            }
            
            if ( $operation != 'search' ) {
                // status messages
                $this->the_plugin->opStatusMsgSet(array(
                    'status'    => 'valid',
                    'msg'       => self::MSG_SEP . ' <u><strong>' . strtoupper($operation) . '</strong> operation.</u>',
                ));
            }
            // status messages
            $this->the_plugin->opStatusMsgSet(array(
                'status'    => 'valid',
                'msg'       => self::MSG_SEP . ' <strong>Page '.$page.'</strong>: try to retrieve Products Data.',
            ));

            if ( empty($asins) || !is_array($asins) ) {
                $tmp_msg = __('No ASINs provided!', $this->the_plugin->localizationName);

                $duration = $this->the_plugin->timer_end(); // End Timer
                // status messages
                $this->the_plugin->opStatusMsgSet(array(
                    'msg'       => $tmp_msg,
                    'duration'  => $duration,
                ));
                
                $ret['msg'] = $tmp_msg;
                $ret['duration'] = $duration;

                if ( $retType == 'return' ) { return $ret; }
                else { die( json_encode( $ret ) ); }
            }
            
            
            // already in queue
            $all_inqueue = $asins_inqueue;
            $here_inqueue = array_values( array_intersect($asins, $asins_inqueue) );
            $ret['asins']['inqueue'] = $here_inqueue;
            
            $asins = array_values( array_diff($asins, $asins_inqueue) );
            $ret['asins']['remained'] = $asins;
            

            // already imported
            $all_already_imported = $this->get_products_already_imported();
            $already_imported = array_values( array_intersect($asins, $all_already_imported) );
            $ret['asins']['imported'] = $already_imported;
            
            $asins = array_values( array_diff($asins, $all_already_imported) );
            $ret['asins']['remained'] = $asins;
   
            // from cache
            //foreach ($asins as $key => $asin) {
            $len = count($asins); $cc = 0;
            while ( $cc < $len ) {
                $key = $cc; $asin = $asins["$key"];

                $__cachePms = array(
                	'provider'			=> $provider,
                    'cache_type'        => 'prods',
                    'asin'              => $this->the_plugin->prodid_get_asin($asin),
                );
      
                $__cache = $this->getTheCache( $__cachePms );
                $__cachePage = ( $__cache !== false ? $__cache : array() );

                // cache is found!
                if ( self::$CACHE_ENABLED['prods'] && !empty($__cachePage)
                    && $this->get_ws_object( $provider )->is_valid_product_data($__cachePage) ) {
                    $product = $__cachePage;
                    $product_asin = $this->the_plugin->prodid_set($asin, $provider, 'add');
                    $parent_asin = $this->the_plugin->prodid_set($__cachePage['ParentASIN'], $provider, 'add');
  
                    // remove from the list for amazon request!
                    unset($asins["$key"]);
  
                    $ret['asins']['from_cache'][] = $product_asin;
                    
                    // product or parent already parsed
                    $already_parsed = array_merge_recursive($ret['asins'], array(
                        'all_inqueue'               => $all_inqueue,
                        'all_already_imported'      => $all_already_imported,
                    ));
 
                    $inqueue_product = $this->already_parsed_asin($already_parsed, $product_asin);
                    $inqueue_parent = $this->already_parsed_asin($already_parsed, $parent_asin);

                    // product is a variation child => try to find parent variation
                    if ( $do_parent_setting && !empty($parent_asin) && ( $product_asin != $parent_asin ) ) {

                        if ( !$inqueue_parent ) {
                            if ( !in_array($parent_asin, $asins) ) {
                                $asins[] = $parent_asin;
                                $len++;
                            }
                        }
                        else {
                            $ret['asins']['inqueue'][] = $parent_asin;
                        }
                        $ret['asins']['invalid'][] = $product_asin;
                        $ret['asins']['variations']["$product_asin"] = $parent_asin;
                    } else {
                            
                        if ( !$inqueue_product ) {
                            $ret['asins']['loaded'][] = $product_asin;
                            $prods["$product_asin"] = $product;
                        }
                        else {
                            $ret['asins']['inqueue'][] = $product_asin;
                            if ( ($key = array_search($product_asin, $asins)) !== false ) {
                                unset($asins["$key"]);
                                $asins = array_values($asins);
                            }
                        }
                    }
                }
                ++$cc;
            }
      
            $asins = array_values($asins);
            $ret['asins']['remained'] = $asins;
 
            // from amazon request!
            if ( !empty($asins) ) {
                $ret['asins']['remained'] = array_values( array_slice($asins, self::$LOAD_MAX_LIMIT["$provider"]) );
                $asins = array_values( array_slice($asins, 0, self::$LOAD_MAX_LIMIT["$provider"]) );
 
                $hasErr = (object) array('amazon' => false, 'amazon_loop' => false);
 
                try {
                    ++$ret['nb_amz_req'];
                    $hasErr->amazon = false;

					/*
                    $this->get_ws_object( $provider, 'ws' )
                    ->responseGroup('Large,ItemAttributes,OfferFull,Offers,Variations,Reviews,PromotionSummary,SalesRank')
                    ->optionalParameters(array('MerchantId' => 'All'));
                    $response = $this->get_ws_object( $provider, 'ws' )
                    ->lookup( implode(",", $asins) );
                    //var_dump('<pre>',$response,'</pre>'); die;
					*/
					$rsp = $this->get_ws_object( $provider )->api_search_byasin(array(
						'asins'					=> $this->the_plugin->prodid_set($asins, $provider, 'sub'),
					));
					$response = $rsp['response'];
 
					$this->inc_nbreq($provider, 'search_byasin'); // increase number of requests made!
                    
                    $respStatus = $this->get_ws_object( $provider )->is_amazon_valid_response( $response );
                    if ( $respStatus['status'] != 'valid' ) { // error occured!

                        $duration = $this->the_plugin->timer_end(); // End Timer
                        $durationQueue[] = $duration; // End Timer
                        $this->the_plugin->timer_start(); // Start Timer
                            
                        // status messages
                        $this->the_plugin->opStatusMsgSet(array(
                            'status'    => 'invalid',
                            'msg'       => 'Invalid ' . $provider . ' response ( ' . $respStatus['code'] . ' - ' . $respStatus['msg'] . ' )',
                            'duration'  => $duration,
                        ));
                        
                        $hasErr->amazon = true;
                        $hasErr->amazon_loop = true;
                    } else { // success!

                        $duration = $this->the_plugin->timer_end(); // End Timer
                        $durationQueue[] = $duration; // End Timer
                        $this->the_plugin->timer_start(); // Start Timer
                            
                        // status messages
                        $this->the_plugin->opStatusMsgSet(array(
                            'status'    => 'valid',
                            'msg'       => 'Valid ' . $provider . ' response',
                            'duration'  => $duration,
                        ));

                        // verify array of Items or array of Item elements
                        /*
                        if ( isset($response['Items']['Item']['ASIN']) ) {
                            $response['Items']['Item'] = array( $response['Items']['Item'] );
                        }
						*/
						$rsp = $this->get_ws_object( $provider )->api_format_results(array(
							'requestData'			=> $requestData,
							'response'				=> $response,
						));
						$requestData = $rsp['requestData'];
  
                        foreach ( $rsp['response'] as $key => $value){

                            $product = $this->build_product_data( $value, array(), $provider );
                            $product_asin = $this->the_plugin->prodid_set($product['ASIN'], $provider, 'add');
                            $parent_asin = $this->the_plugin->prodid_set($product['ParentASIN'], $provider, 'add');
  
							// join the first cache from search with the cache from details ( if provider needs it this way! )
							if ( isset($product['__isfrom']) && ('details' == $product['__isfrom']) ) {
	                            $__cachePms = array(
	                            	'provider'			=> $provider,
	                                'cache_type'        => 'prods',
	                                'asin'              => $this->the_plugin->prodid_get_asin($product_asin),
	                            );
	      
	                			$__cache = $this->getTheCache( $__cachePms );
	                			$__cachePage = ( $__cache !== false ? $__cache : array() );
 
	                			// cache is found!
	                			if ( self::$CACHE_ENABLED['prods'] && !empty($__cachePage)
	                    			&& $this->get_ws_object( $provider )->is_valid_product_data($__cachePage, 'search') ) {

                					//$product = array_replace_recursive($__cachePage, $product);
									$product = $this->build_product_data( $value, $__cachePage, $provider );
								}
							}
							
                            $ret['asins']['from_amz'][] = $product_asin;

                            // product or parent already parsed
                            $already_parsed = array_merge_recursive($ret['asins'], array(
                                'all_inqueue'               => $all_inqueue,
                                'all_already_imported'      => $all_already_imported,
                            ));
                            $inqueue_product = $this->already_parsed_asin($already_parsed, $product_asin);
                            $inqueue_parent = $this->already_parsed_asin($already_parsed, $parent_asin);

                            // product is a variation child => try to find parent variation
                            if ( $do_parent_setting && !empty($parent_asin) && ( $product_asin != $parent_asin ) ) {

                                if ( !$inqueue_parent ) {
                                    if ( !in_array($parent_asin, $ret['asins']['remained']) ) {
                                        $ret['asins']['remained'][] = $parent_asin;
                                    }
                                }
                                else {
                                    $ret['asins']['inqueue'][] = $parent_asin;
                                }
                                $ret['asins']['invalid'][] = $product_asin;
                                $ret['asins']['variations']["$product_asin"] = $parent_asin;
                            } else {
                                    
                                if ( !$inqueue_product ) {
                                    $ret['asins']['loaded'][] = $product_asin;
                                    $prods["$product_asin"] = $product;
                                }
                                else {
                                    $ret['asins']['inqueue'][] = $product_asin;
                                    if ( ($key = array_search($product_asin, $asins)) !== false ) {
                                        unset($asins["$key"]);
                                        $asins = array_values($asins);
                                    }
                                }
                            }
                            
                            // set cache
                            $__cachePms = array(
                            	'provider'			=> $provider,
                                'cache_type'        => 'prods',
                                'asin'              => $this->the_plugin->prodid_get_asin($product_asin),
                            );
                            $this->setTheCache( $__cachePms, $product );
                        }
                    }
                    // go to [success] label
                    //...

                } catch (Exception $e) {
                    // Check 
                    if (isset($e->faultcode)) { // error occured!

                        ob_start();
                        var_dump('<pre>', 'Invalid ' . $provider . ' response (exception)', $e,'</pre>');
                        
                        $duration = $this->the_plugin->timer_end(); // End Timer
                        $durationQueue[] = $duration; // End Timer
                        $this->the_plugin->timer_start(); // Start Timer
                            
                        // status messages
                        $this->the_plugin->opStatusMsgSet(array(
                            'status'    => 'invalid',
                            'msg'       => ob_get_clean(),
                            'duration'  => $duration,
                        ));
                        
                        $asins = array_values($asins);
                        $hasErr->amazon = true;
                        $hasErr->amazon_loop = true;
                    }
                }
            }

            if ( $operation != 'search' ) {
                // status messages
                $this->the_plugin->opStatusMsgSet(array(
                    'status'    => 'valid',
                    'msg'       => sprintf( 'Number of ' . $provider . ' Requests: %s', $ret['nb_amz_req'] ),
                ));
            }

            $invalid_prods = array_values( array_diff($asins, $ret['asins']['loaded']) );
            $ret['asins']['invalid'] = array_merge($ret['asins']['invalid'], $invalid_prods);
            
            $from_amz = array_values( array_diff($asins, $ret['asins']['from_cache']) );
            $ret['asins']['from_amz'] = array_merge($ret['asins']['from_amz'], $from_amz);
            
            // make unique
            foreach ($ret['asins'] as $atype => $avalue) {
                if ( !in_array($atype, array('variations')) ) {
                    $ret['asins']["$atype"] = array_unique( $avalue );
                }
            }

            // amazon request was made
            if ( isset($hasErr->amazon) ) {
                // error occured on amazon request
                if ( $hasErr->amazon ) {}
                // [success] label
                else {}
            }
            else {
            }

            $duration = $this->the_plugin->timer_end(); // End Timer
            $durationQueue[] = $duration; // End Timer
            $duration = round( array_sum($durationQueue), 4 ); // End Timer
            
            // status messages
            $this->the_plugin->opStatusMsgSet(array(
                'status'    => 'valid',
                'msg'       => $this->loadprods_set_msg( $ret ),
                'duration'  => $duration,
                'end'       => true,
            ));
            
            $opStatusMsg = $this->the_plugin->opStatusMsgGet();

            if ( empty($ret['asins']['invalid']) && empty($ret['asins']['imported']) && empty($ret['asins']['inqueue']) ) {
                $ret['status'] = 'valid';
            }
            
            // build html
            $ret['html'] = $this->loadprods_build_html( $prods, $provider );
            $ret['duration'] = $duration;
            
            $ret = array_merge($ret, array('msg' => $opStatusMsg['msg']));
            if ( $retType == 'return' ) { return $ret; }
            else { die( json_encode( $ret ) ); }
        }

        // ajax/ load products in queue based on Search
        public function loadprods_queue_by_search( $retType='die', $pms=array() ) {

            $durationQueue = array(); // Duration Queue
            $this->the_plugin->timer_start(); // Start Timer
            
            //params['aiowaff-search']: category, keyword, nbpages, node, search_on
            $requestData = array(
                //'use_categ_field'       => isset($_REQUEST['use_categ_field']) ? $_REQUEST['use_categ_field'] : 'category',
                'operation'             => isset($_REQUEST['operation']) ? $_REQUEST['operation'] : '',
                'asins_inqueue'         => isset($_REQUEST['asins_inqueue']) ? trim($_REQUEST['asins_inqueue']) : '',
                'params'                => isset($_REQUEST['params']) ? $_REQUEST['params'] : '',
                'page'                  => isset($_REQUEST['page']) ? (int) $_REQUEST['page'] : 0,
                'operation_id'          => isset($_REQUEST['operation_id']) ? $_REQUEST['operation_id'] : '',
                'provider'              => isset($_REQUEST['provider']) ? $_REQUEST['provider'] : '',
            );
            if ( !empty($requestData['asins_inqueue']) && substr_count($requestData['asins_inqueue'], ',') ) {
                $requestData['asins_inqueue'] = explode(',', $requestData['asins_inqueue']);
            } else {
                $requestData['asins_inqueue'] = array();
            }
            $requestData['asins_inqueue'] = array_unique($requestData['asins_inqueue']);
 
            $params = array();
            parse_str( ( $requestData['params'] ), $params);

            if( isset($params['aiowaff-search'])) {
                $requestData = array_merge($requestData, $params['aiowaff-search']);
            }
            foreach ($requestData as $rk => $rv) {
                if ( isset($pms["$rk"]) ) {
                    $new_val = $pms["$rk"];
                    $new_val = in_array($rk, array()) ? (array) $new_val : $new_val;
                    $requestData["$rk"] = $new_val;
                }
            }
            //foreach ($requestData as $key => $val) {
            //    if ( strpos($key, '-') !== false ) {
            //        $_key = str_replace('-', '_', $key); 
            //        $requestData["$_key"] = $val;
            //        unset($requestData["$key"]);
            //    }
            //}
            $provider = $requestData['provider'];
 
            if ( 'amazon' == $provider && (!isset($requestData['category']) || empty($requestData['category'])) ) {
                $requestData['category'] = 'AllCategories';
            }
            $max_nbpages = isset($requestData['category']) && ($requestData['category'] == 'AllCategories') ? 5 : 10;
            if ( !isset($requestData['nbpages']) || $requestData['nbpages'] < 1 || $requestData['nbpages'] > $max_nbpages ) {
                $requestData['nbpages'] = 1;
            }
			if ( !isset($requestData['page']) || empty($requestData['page']) ) {
				$requestData['page'] = 0;
			}
            
            // status messages
            $this->the_plugin->opStatusMsgInit(array(
                'operation_id'  => $requestData['operation_id'],
                'operation'     => 'load_by_search',
                'msg_header'    => __('Loading products by Searching...', 'aiowaff'),
            ));
            
            $parameters = array();
			if ( isset($requestData['keyword']) && !empty($requestData['keyword']) ) {
				$parameters['keyword'] = $requestData['keyword'];
			}
			if ( isset($requestData['category']) && !empty($requestData['category']) ) {
				$parameters['category'] = $requestData['category'];
			}
			if ( isset($requestData['site']) && !empty($requestData['site']) ) {
				$parameters['site'] = $requestData['site'];
			}
			if ( isset($requestData['term']) && !empty($requestData['term']) ) {
				$parameters['term'] = $requestData['term'];
			}
			if ( isset($requestData['search_type']) && !empty($requestData['search_type']) ) {
				$parameters['search_type'] = $requestData['search_type'];
			}
			if ( isset($requestData['nbpages']) && !empty($requestData['nbpages']) ) {
				$parameters['nbpages'] = (int) $requestData['nbpages'];
			}
            if ( isset($requestData['page']) && !empty($requestData['page']) ) {
                $parameters = array_merge($parameters, array(
                    'page'          => $requestData['page'],
                    'nbpages'		=> 1, // when you choose a specific page, number of pages is alwasy 1
                ));
            }

            // option parameters
            $_optionalParameters = array();
            $optionalParameters = array_keys( self::$optionalParameters["$provider"] );
            if( count($optionalParameters) > 0 ){
                foreach ($optionalParameters as $oparam){
                    if ( isset($requestData["$oparam"]) ) {
                        $_optionalParameters["$oparam"] = $requestData["$oparam"];
						$_optionalParameters["$oparam"] = trim( $_optionalParameters["$oparam"] );
                    }
                }
            }
            // if node is send, chain to request
            //if( isset($requestData['node']) && trim($requestData['node']) != "" ){
            //    $_optionalParameters['BrowseNode'] = $requestData['node'];
            //}
            if ( 'amazon' == $provider && !in_array('MerchantId', array_keys($_optionalParameters)) ) {
                $_optionalParameters['MerchantId'] = 'All';
            }
            // clear the empty array
            $_optionalParameters = array_filter($_optionalParameters);
            //var_dump('<pre>', $_optionalParameters, '</pre>'); die('debug...'); 

            // cache
            $__cacheSearchPms = array(
            	'provider'			=> $provider,
                'cache_type'        => 'search',
                'params1'           => $parameters,
                'params2'           => $_optionalParameters,
                'requestData'		=> $requestData,
            );
      
            $__cacheSearch = $this->getTheCache( $__cacheSearchPms );
            $__cacheSearchPage = ( $__cacheSearch !== false ? $__cacheSearch : array() );

            $searchResults = array();
            $ret = array(
                'status'        => 'invalid',
                'nb_amz_req'    => 0, // number of amazon requests
                'msg'           => '',
            );

            //$__searchPmsMsg = implode(', ', array_map(array($this->the_plugin, 'prepareForPairView'), $parameters, array_keys($parameters)));
            $__searchPmsMsg = http_build_query( $this->__search_nice_params( array_merge($parameters, $_optionalParameters), $provider ), '', ', ' );
            
            // status messages
            $this->the_plugin->opStatusMsgSet(array(
                'status'    => 'valid',
                'msg'       => self::MSG_SEP . ' <u><strong>Search Products</strong> operation: try to retrieve results.</u>',
            ));
            // status messages
            $this->the_plugin->opStatusMsgSet(array(
                'status'    => 'valid',
                'msg'       => 'Search Parameters: ' . $__searchPmsMsg,
            ));

            // cache is found!
            if ( self::$CACHE_ENABLED['search'] && !empty($__cacheSearchPage) ) {
                
                $__writeCache['dataToSave'] = $__cacheSearchPage;
                
                $duration = $this->the_plugin->timer_end(); // End Timer
                $durationQueue[] = $duration; // End Timer
                $this->the_plugin->timer_start(); // Start Timer
                
                // status messages
                $this->the_plugin->opStatusMsgSet(array(
                    'status'    => 'valid',
                    'msg'       => self::MSG_SEP . ' Search results returned from Cache',
                    'duration'  => $duration,
                ));

            }
			// cache NOT found!
			else {
 
                $duration = $this->the_plugin->timer_end(); // End Timer
                $durationQueue[] = $duration; // End Timer
                $this->the_plugin->timer_start(); // Start Timer

                // status messages
                $this->the_plugin->opStatusMsgSet(array(
                    'status'    => 'valid',
                    'msg'       => self::MSG_SEP . ' Search results - try to retrieve from ' . $provider,
                ));

                // already imported
                $all_already_imported = $this->get_products_already_imported();
            
                $hasErr = (object) array('cache' => false, 'amazon' => false, 'amazon_loop' => false, 'stop_loop' => false );
                $__writeCache = array('dataToSave' => array());

                $cc = 1; $max = 10;
                // Begin Loop
                do {
                	
                    $page = $cc;
                    if ( isset($requestData['page']) && !empty($requestData['page']) ) {
                        $page = $requestData['page'];
                    }

                    // status messages
                    $this->the_plugin->opStatusMsgSet(array(
                        'status'    => 'valid',
                        'msg'       => self::MSG_SEP . ' <strong>Page '.$page.'</strong>.',
                    ));
    
                    try {
                        ++$ret['nb_amz_req'];
                        $hasErr->amazon = false;

                        /*
						$this->get_ws_object( $provider, 'ws' )
                        ->category( ( $parameters['category'] == 'AllCategories' ? 'All' : $parameters['category'] ) )
                        ->page( $page )
                        ->responseGroup('Large,ItemAttributes,OfferFull,Offers,Variations,Reviews,PromotionSummary,SalesRank');
     
                        // set the page
                        $_optionalParameters['ItemPage'] = $page;
                    
                        if( count($_optionalParameters) > 0 ){
                            // add optional parameter to query
                            $this->get_ws_object( $provider, 'ws' )
                            ->optionalParameters( $_optionalParameters );
                        }
                        //var_dump('<pre>',$this->get_ws_object( $provider, 'ws' ),'</pre>');  
                
                        // add the search keywords
                        $response = $this->get_ws_object( $provider, 'ws' )
                        ->search( isset($parameters['keyword']) ? $parameters['keyword'] : '' );
                        //var_dump('<pre>',$response,'</pre>'); die;
    
                        //$__asinsDebug = array();
                        //foreach ( $response['Items']['Item'] as $item_key => $item_val ) {
                        //    $__asinsDebug[] = $item_val['ASIN'];
                        //}
                        //var_dump('<pre>',$__asinsDebug,'</pre>');
						*/
						$rsp = $this->get_ws_object( $provider )->api_search_bypages(array(
							'requestData'			=> $requestData,
							'parameters'			=> $parameters,
							'_optionalParameters'	=> $_optionalParameters,
							'page'					=> $page,
						));
						$response = $rsp['response'];
						
						$this->inc_nbreq($provider, 'search_bypages'); // increase number of requests made!
                        
                        $respStatus = $this->get_ws_object( $provider )->is_amazon_valid_response(
                        	$response,
                        	isset($requestData['search_type']) && !empty($requestData['search_type'])
								? $requestData['search_type'] : 'search'
						);
                        if ( $respStatus['status'] != 'valid' ) { // error occured!
    
                            $duration = $this->the_plugin->timer_end(); // End Timer
                            $durationQueue[] = $duration; // End Timer
                            $this->the_plugin->timer_start(); // Start Timer
                            
                            // status messages
                            $this->the_plugin->opStatusMsgSet(array(
                                'status'    => 'invalid',
                                'msg'       => 'Invalid ' . $provider . ' response ( ' . $respStatus['code'] . ' - ' . $respStatus['msg'] . ' )',
                                'duration'  => $duration,
                            ));
    
                            $hasErr->amazon = true;
                            $hasErr->amazon_loop = true;
                            if ( 3 == $respStatus['code'] || $page == 1
                            	|| ( isset($requestData['page']) && !empty($requestData['page']) ) ) { // no search results
                                $hasErr->stop_loop = true;
                            }
                        } else { // success!
    
                            $duration = $this->the_plugin->timer_end(); // End Timer
                            $durationQueue[] = $duration; // End Timer
                            $this->the_plugin->timer_start(); // Start Timer
                            
                            // status messages
                            $this->the_plugin->opStatusMsgSet(array(
                                'status'    => 'valid',
                                'msg'       => 'Valid ' . $provider . ' response',
                                'duration'  => $duration,
                            ));
    
							/*
                            if ( isset($response['Items']['TotalPages'])
                                && (int) $response['Items']['TotalPages'] < $requestData['nbpages'] ) {
                                $requestData['nbpages'] = (int) $response['Items']['TotalPages'];
                                // don't put this validated nbpages in $__cacheSearchPms, because the cache file could not be recognized then!
                            }
        
                            // verify array of Items or array of Item elements
                            if ( isset($response['Items']['Item']['ASIN']) ) {
                                $response['Items']['Item'] = array( $response['Items']['Item'] );
                            }
							*/
							$rsp = $this->get_ws_object( $provider )->api_format_results(array(
								'requestData'			=> $requestData,
								'response'				=> $response,
							));
							$requestData = $rsp['requestData'];
        
                            foreach ( $rsp['response'] as $key => $value){
        
                                $product = $this->build_product_data( $value, array(), $provider );
                                $product_asin = $product['ASIN'];
        
                                if ( !in_array(
                                	$this->the_plugin->prodid_set($product_asin, $provider, 'add'),
                                	$all_already_imported
								) ) {
    
                                    $__cachePms = array(
                                    	'provider'			=> $provider,
                                        'cache_type'        => 'prods',
                                        'asin'              => $product_asin,
                                    );
            
                                    $__cache = $this->getTheCache( $__cachePms );
                                    $__cachePage = ( $__cache !== false ? $__cache : array() );
            
                                    // cache is found!
                                    if ( self::$CACHE_ENABLED['prods'] && !empty($__cachePage)
                                        && $this->get_ws_object( $provider )->is_valid_product_data($__cachePage) ) ;
                                    else {
                                        $this->setTheCache( $__cachePms, $product );
                                    }
                                }
                            }
                        }
                        // go to [success] label
                        //...
    
                    } catch (Exception $e) {
                        // Check 
                        if (isset($e->faultcode)) { // error occured!
    
                            ob_start();
                            var_dump('<pre>', 'Invalid ' . $provider . ' response (exception)', $e,'</pre>');
                            
                            $duration = $this->the_plugin->timer_end(); // End Timer
                            $durationQueue[] = $duration; // End Timer
                            $this->the_plugin->timer_start(); // Start Timer
                            
                            // status messages
                            $this->the_plugin->opStatusMsgSet(array(
                                'status'    => 'invalid',
                                'msg'       => ob_get_clean(),
                                'duration'  => $duration,
                            ));
    
                            $hasErr->amazon = true;
                            $hasErr->amazon_loop = true;
                        }
                    }
    
                    ++$cc;
    
                    // [success] label
                    // here we build the results array using the setTheCache method!
                    $__cacheSearchPms = array_merge($__cacheSearchPms, array(
                        'page'              => $page,
                    ));
                    if ( !$hasErr->amazon ) {
                        $__writeCache = $this->setTheCache( $__cacheSearchPms, $response, $__writeCache['dataToSave'], false );
    
                        // we'll write the cache only if errors didn't occured on any page step                  
                        if ( !$hasErr->cache
                            && ( $__writeCache === false || !isset($__writeCache['dataToSave']) || empty($__writeCache['dataToSave']) )
                        ) {
                            $hasErr->cache = true;
                        }
                        
                        // status messages
                        $this->the_plugin->opStatusMsgSet(array(
                            'status'    => 'valid',
                            'msg'       => 'Page results retrieved successfully from ' . $provider,
                        ));
                    }
                
                } while ($cc <= $requestData['nbpages'] && $cc <= $max && !$hasErr->stop_loop );
                // End Loop
                
                // error occured during caching or on one amazon request => delete current wrote cache if found
                if ( $hasErr->cache || $hasErr->amazon_loop ) {
                    $this->deleteTheCache( $__cacheSearchPms );
                    $tmp_msg = self::MSG_SEP . ' Search results could not be wrote in cache file!';
                }
                // wrote cache
                else {
                    $this->setTheCache( $__cacheSearchPms, array('__notused__' => true), $__writeCache['dataToSave'], true );
                    $tmp_msg = self::MSG_SEP . ' Search results successfully wrote in cache file.';
                }
                
                $duration = $this->the_plugin->timer_end(); // End Timer
                $durationQueue[] = $duration; // End Timer
                $this->the_plugin->timer_start(); // Start Timer

                // status messages
                $this->the_plugin->opStatusMsgSet(array(
                    'status'    => 'valid',
                    'msg'       => $tmp_msg,
                    'duration'  => $duration,
                ));
            } // end cache NOT found!

            //var_dump('<pre>', $__writeCache['dataToSave'], '</pre>'); die('debug...'); 
            $results = $__writeCache['dataToSave'];
            
            // amazon should returned a valid reponse & at least one page
			$rsp = $this->get_ws_object( $provider )->api_search_validation(array(
				'results'				=> $results,
			));
			$nbpages = $rsp['nbpages'];
            //if ( !isset($results['Items'], $results['Items']['TotalResults'], $results['Items']['NbPagesSelected'])
            //    || count($results) < 2 ) {
			if ( !$rsp['status'] ) { 
                $duration = $this->the_plugin->timer_end(); // End Timer
                $durationQueue[] = $duration; // End Timer
                $duration = round( array_sum($durationQueue), 4 ); // End Timer
                
                // status messages
                $this->the_plugin->opStatusMsgSet(array(
                    'status'    => 'invalid',
                    'msg'       => 'Unsuccessfull operation!',
                    'duration'  => $duration,
                ));
                
                $opStatusMsg = $this->the_plugin->opStatusMsgGet();

                $ret = array_merge($ret, array('msg' => $opStatusMsg['msg']));
                if ( $retType == 'return' ) { return $ret; }
                else { die( json_encode( $ret ) ); }
            }
            //$nbpages = (int) $results['Items']['NbPagesSelected'];
            
            // search stats
			$rsp = $this->get_ws_object( $provider )->api_search_get_stats(array(
				'results'				=> $results,
			));
			$search_stats = $rsp['stats'];
            
            // status messages
            $opStatusMsg = array();
            $__opStatusMsg = $this->the_plugin->opStatusMsgGet();
            $opStatusMsg[] = $__opStatusMsg['msg'];
            $this->the_plugin->opStatusMsgInit(array(
                'operation_id'  => $requestData['operation_id'],
                'operation'     => 'load_by_search',
                'status'        => 'valid',
            ));
 
            // PARSE SEARCH RESULTS...
            $ret = array_merge($ret, array(
            	'status'			=> 'valid',
            	'asins'				=> array(),
            	'html'				=> '',
			));
            foreach ($results as $page => $page_content) {
                if ( !is_numeric($page) ) continue 1;
                //var_dump('<pre>',$page, $page_content,'</pre>');
                
                $duration = $this->the_plugin->timer_end(); // End Timer
                $durationQueue[] = $duration; // End Timer

                //$asins = $page_content['Items']['Item'];
				$rsp = $this->get_ws_object( $provider )->api_cache_get_page_asins(array(
					'page_content'				=> $page_content,
				));
				$asins = $this->the_plugin->prodid_set($rsp['asins'], $provider, 'add');
                $asins_inqueue = $this->build_asins_inqueue( (array) $requestData['asins_inqueue'], $ret['asins'] );
                $queueAsinsStats = $this->loadprods_queue_by_asin( 'return', array(
                    'operation'         => 'search',
                    'page'              => $page,
                    'asins'             => $asins,
                    'asins_inqueue'     => $asins_inqueue,
                    'provider'			=> $provider
                ));
                $queueAsinsStats['asins']['found'] = $asins;

                if ( isset($queueAsinsStats['duration']) ) {
                	$durationQueue[] = $queueAsinsStats['duration']; // End Timer
                	unset($queueAsinsStats['duration']);
				}
                
                $this->the_plugin->timer_start(); // Start Timer

                if ( isset($queueAsinsStats['msg']) ) {
                	unset($queueAsinsStats['msg']);
				}

                if ( isset($queueAsinsStats['html']) ) {
                	$ret['html'] .= $queueAsinsStats['html'];
                	unset($queueAsinsStats['html']);
				}
                
                if ( isset($queueAsinsStats['nb_amz_req']) ) {
                	$ret['nb_amz_req'] += $queueAsinsStats['nb_amz_req'];
                	unset($queueAsinsStats['nb_amz_req']);
				}

               	$ret['status'] = ( $ret['status'] == 'valid' ) && isset($queueAsinsStats['status'])
					&& ( $queueAsinsStats['status'] == 'valid' ) ? 'valid' : 'invalid';
                if ( $queueAsinsStats['status'] ) {
                	unset($queueAsinsStats['status']);
				}

                $ret = array_merge_recursive($ret, $queueAsinsStats); //array_replace_recursive
            }
    
            $duration = $this->the_plugin->timer_end(); // End Timer
            $durationQueue[] = $duration; // End Timer
            $duration = round( array_sum($durationQueue), 4 ); // End Timer

            // status messages
            if ( isset($search_stats['TotalResults'], $search_stats['TotalPages']) ) {
	            $search_stats_msg = sprintf( 
					__('%s items in %s pages', 'aiowaff'),
					$search_stats['TotalResults'],
					$search_stats['TotalPages']
				);
	            $this->the_plugin->opStatusMsgSet(array(
	                'status'    => 'valid',
	                'msg'       => $search_stats_msg,
	            ));
				$ret['search_stats'] = $search_stats_msg;
			}

            // status messages
            $this->the_plugin->opStatusMsgSet(array(
                'status'    => 'valid',
                'msg'       => sprintf( 'Number of ' . $provider . ' Requests: %s', $ret['nb_amz_req'] ),
                'duration'  => $duration,
                'end'       => true,
            ));
            
            $__opStatusMsg = $this->the_plugin->opStatusMsgGet();
            $opStatusMsg[] = $__opStatusMsg['msg'];
 
            $ret = array_merge($ret, array('msg' => implode('<br />', $opStatusMsg)));
            //var_dump('<pre>',$ret,'</pre>');
            if ( $retType == 'return' ) { return $ret; }
            else { die( json_encode( $ret ) ); }
        }

        // load products - set msg/message for ajax response
        private function loadprods_set_msg( $ret ) {
            
            $loaded = $ret['asins']['loaded'];
            $imported = $ret['asins']['imported'];
            $invalid = $ret['asins']['invalid'];
            $inqueue = $ret['asins']['inqueue'];
            $variations = $ret['asins']['variations'];
            
            //$amz_setup = $this->the_plugin->getAllSettings('array', 'amazon');
            $amz_setup = $this->settings;
            $do_parent_setting = !isset($amz_setup['variation_force_parent'])
                || ( isset($amz_setup['variation_force_parent']) && $amz_setup['variation_force_parent'] != 'no' )
                ? true : false;
            $show_variation = count($variations) > 0 ? true : false;

            $_invalid_childs = array();
            if ( $do_parent_setting && $show_variation ) {
                $invalid_real = array_diff( $invalid, array_keys($variations) );
                $invalid_childs = array_intersect( $invalid, array_keys($variations) );
                foreach ( $invalid_childs as $asin) {
                    $_invalid_childs["$asin"] = $variations["$asin"]; // child=parent
                }
                $__invalid_childs = !empty($_invalid_childs) ? http_build_query( $_invalid_childs, '', ', ' ) : '--';
            }

            // message
            $_msg = array();
            // Loaded
            if ( count($loaded) > 0 ) {
                $_msg[] = sprintf( __('%s ASINs loaded in queue: %s', $this->the_plugin->localizationName), count($loaded), implode(', ', $loaded) );
            }
            // Already Imported
            if ( count($imported) > 0 ) {
                $_msg[] = sprintf( __('%s ASINs already imported: %s', $this->the_plugin->localizationName), count($imported), implode(', ', $imported) );
            }
            // Already Parsed: loaded, invalid, already imported
            if ( count($inqueue) > 0 ) {
                $_msg[] = sprintf( __('%s ASINs already parsed (loaded, invalid, imported): %s', $this->the_plugin->localizationName), count($inqueue), implode(', ', $inqueue) );
            }
            // Invalid
            if ( count($invalid) > 0 ) {
                if ( $do_parent_setting && $show_variation ) {
                    if ( count($invalid_real) > 0 ) {
                        $_msg[] = sprintf( __('%s ASINs invalid: %s', $this->the_plugin->localizationName), count($invalid_real), implode(', ', $invalid_real) );
                    }
                }
                else {
                    $_msg[] = sprintf( __('%s ASINs invalid: %s', $this->the_plugin->localizationName), count($invalid), implode(', ', $invalid) );
                }
            }
            // Variations childs
            if ( $do_parent_setting && $show_variation ) {
                if ( count($_invalid_childs) > 0 ) {
                    $_msg[] = sprintf( __('%s ASINs variation childs (child=parent): %s', $this->the_plugin->localizationName), count($_invalid_childs), $__invalid_childs );
                }
            }

            return implode(' | ', $_msg);           
        }

        private function already_parsed_asins($parsed, $asins) {
            $ret = array('yes' => array(), 'no' => array());

            $tmp_yes = array();
            foreach (array('loaded', 'invalid', 'all_inqueue', 'all_already_imported') as $key) {
                $current = $parsed["$key"];
  
                // exists
                $tmp_yes = array_merge( $tmp_yes, array_values( array_intersect($asins, $current) ) );
            }
            $tmp_yes = array_unique($tmp_yes);
            $ret['yes'] = array_values( $tmp_yes );

            // do NOT exists
            $ret['no'] = array_values( array_diff($asins, $ret['yes']) );

            return (object) $ret;
        }

        private function already_parsed_asin($asins_parsed, $asin) {
            $stat = $this->already_parsed_asins($asins_parsed, array($asin));
            return in_array($asin, $stat->yes) ? true : false;
        }
        
        private function build_asins_inqueue($current=array(), $asins=array()) {
            $ret = (array) $current;
            if ( isset($asins['inqueue']) ) {
                $ret = array_merge($ret, $asins['inqueue']);
            }
            if ( isset($asins['loaded']) ) {
                $ret = array_merge($ret, $asins['loaded']);
            }
            if ( isset($asins['invalid']) ) {
                $ret = array_merge($ret, $asins['invalid']);
            }
            if ( isset($asins['imported']) ) {
                $ret = array_merge($ret, $asins['imported']);
            }
            $ret = array_unique($ret);
            return $ret;
        }

        private function __search_nice_params( $pms=array(), $provider='amazon' ) {
            $ret = array();
            foreach ($pms as $key => $value) {
            	if ( in_array($key, array('MerchantId')) && 'amazon' != $provider ) {
            		continue 1;
				}
                if ( $key == 'nbpages' ) $key = 'NbPages';
                $key = str_replace('_', ' ', $key);
                $key = ucwords($key);
                $ret["$key"] = $value;
            }
            return $ret;
        }


        /**
         * Import Product
         */
        public function import_product( $retType='die', $pms=array() ) {
            $requestData = array(
                'asin'                  => isset($_REQUEST['asin']) ? $_REQUEST['asin'] : '',
                'params'                => isset($_REQUEST['params']) ? $_REQUEST['params'] : '',
                'operation_id'          => isset($_REQUEST['operation_id']) ? $_REQUEST['operation_id'] : '', // operation id
                'provider'              => isset($_REQUEST['provider']) ? $_REQUEST['provider'] : '',
            );

            // params: import_type, nbimages, nbvariations, spin, attributes, to-category
            $params = array();
            parse_str( ( $requestData['params'] ), $params);
        
            if( !empty($params) ) {
                $requestData = array_merge($requestData, $params);
            }
            foreach ($requestData as $rk => $rv) {
                if ( 1 ) {
                    if ( isset($pms["$rk"]) ) {
                        $new_val = $pms["$rk"];
                        $new_val = in_array($rk, array()) ? (array) $new_val : $new_val;
                        $requestData["$rk"] = $new_val;
                    }
                }
            }
            foreach ($requestData as $key => $val) {
                if ( strpos($key, '-') !== false ) {
                    $_key = str_replace('-', '_', $key); 
                    $requestData["$_key"] = $val;
                    unset($requestData["$key"]);
                }
            }
            extract($requestData);

            $ret = array(
                'status'        => 'invalid',
                'msg'           => '',
            );
            
            // from cache
            $product_from_cache = array();
            $__cachePms = array(
            	'provider'			=> $provider,
                'cache_type'        => 'prods',
                'asin'              => $this->the_plugin->prodid_get_asin( $asin ),
            );
      
            $__cache = $this->getTheCache( $__cachePms );
            $__cachePage = ( $__cache !== false ? $__cache : array() );

            // cache is found!
            if ( self::$CACHE_ENABLED['prods'] && !empty($__cachePage)
                && $this->get_ws_object( $provider )->is_valid_product_data($__cachePage) ) {
                $product_from_cache = $__cachePage;
            }
            
            // try to insert in database
            $args_add = array(
                'asin'                  => $this->the_plugin->prodid_get_asin( $asin ),
                'from_cache'            => $product_from_cache,

                'from_module'           => 'insane',
                'import_type'           => $import_type,

                // bellow parameters are used in framework addNewProduct method
                'operation_id'          => $requestData['operation_id'],

                'import_to_category'    => $to_category,

                'import_images'         => (int) $nbimages > 0 ? (int) $nbimages : 'all',

                'import_variations'     => (string) $nbvariations === '0' ? 'no' : 'yes_' . $nbvariations,

                'spin_at_import'        => isset($requestData['spin']) ? true : false,

                'import_attributes'     => isset($requestData['attributes']) ? true : false,
            );
            $getProduct = $this->get_ws_object( $provider )->getProductDataFromAmazon( 'return', $args_add );
               
            $ret = array_merge($ret, $getProduct);
            $ret['import_settings'] = $this->the_plugin->get_last_imports();
            //var_dump('<pre>',$ret,'</pre>');
            
            if ( $retType == 'return' ) { return $ret; }
            else { die( json_encode( $ret ) ); }
        }


        /**
         * Load Products - HTML
         */
        // load products in queue - build html
        public function loadprods_build_html( $prods=array(), $provider='amazon' ) {
            //$amz_setup = $this->the_plugin->getAllSettings('array', 'amazon');
            $amz_setup = $this->settings;
            $do_parent_setting = !isset($amz_setup['variation_force_parent'])
                || ( isset($amz_setup['variation_force_parent']) && $amz_setup['variation_force_parent'] != 'no' )
                ? true : false;

            $html = array();
            foreach ($prods as $asin => $prod) {
                
                // number of variations
                $nb_variations = 0;
				if ( in_array($provider, array('amazon', 'ebay')) ) {
                	$nb_variations = isset($prod['Variations'], $prod['Variations']['TotalVariations'])
                    	? (int) $prod['Variations']['TotalVariations'] : 0;
				}
                    
                // number of images
                $nb_images = isset($prod['images'], $prod['images']['large'])
                    ? (int) count($prod['images']['large']) : 0;
                    
                $data_settings = array(
                    'nb_variations'             => $nb_variations,
                    'nb_images'                 => $nb_images,
                );
                $data_settings = htmlentities(json_encode( $data_settings ));
                
                // price
                $price = $this->get_ws_object( $provider )->get_productPrice($prod);
                //var_dump('<pre>', $price, '</pre>');
                $_regular = $price['_regular_price'];
                $_sale = $price['_sale_price'];
                $price_html = array(); //<del>$1,029.99</del><span>$1,029.99</span>
                if ( !empty($_regular) ) {
                    if ( !empty($_sale) ) {
                        $price_html[] = "<del>$$_regular</del>";
                        $price_html[] = "<span>$$_sale</span>";
                    } else {
                        $price_html[] = "<span>$$_regular</span>";
                    }
                } else if ( !empty($_sale) ) {
                    $price_html[] = "<span>$$_sale</span>";
                }
                $price_html = implode('', $price_html);

                $html[] = '<li class="selected" data-asin="'.$this->the_plugin->prodid_set($asin, $provider, 'add').'" data-settings="'.$data_settings.'">'
                    . ($nb_variations > 0 ? '<i class="fa fa-external-link" title="' . sprintf( __('%s variations', $this->the_plugin->localizationName), $nb_variations ) . '"></i>' : '')
                    . '<span class="aiowaff-provider-line aiowaff-provider-'.$provider.'">'.strtolower($provider).'</span><span class="aiowaff-checked-product squaredThree">
                       <input type="checkbox" value="added" name="check" id="squaredThree-'.$asin.'" checked><label for="squaredThree-'.$asin.'"></label>
                    </span>
                    <a target="_blank" href="'.$prod['DetailPageURL'].'" class="WZC-keyword-attached-image"><img src="'.$prod['SmallImage'].'"></a>
                    <div class="WZC-keyword-attached-phrase"><a target="_blank" href="'.$prod['DetailPageURL'].'" class="WZC-keyword-attached-url"><span>'.$this->the_plugin->prodid_set($asin, $provider, 'sub').'</span></a></div>
                    <div class="WZC-keyword-attached-title"><a target="_blank" href="'.$prod['DetailPageURL'].'" class="WZC-keyword-attached-url">'.$prod['Title'].'</a></div>
                    <div class="WZC-keyword-attached-brand">'.__('by:', $this->the_plugin->localizationName).' <span>'.$prod['Brand'].'</span></div>
                    <div class="WZC-keyword-attached-prices">'.$price_html.'</div>
                </li>';
            }
            return implode('', $html);
        }

        private function build_select( $param, $values, $default='', $extra=array() ) {
            $extra = array_replace_recursive(array(
                'prefix'        => 'aiowaff-search',
                'desc'          => array(),
                'nodeid'        => array(),
            ), $extra);
            extract($extra);

            $html = array();
            if (empty($values) || !is_array($values)) return '';
            foreach ($values as $k => $v) {
                
                $__selected = ($k == $default ? ' selected="selected"' : '');
                $__desc = (!empty($desc) && isset($desc["$k"]) ? ' data-desc="'.$desc["$k"].'"' : '');
                $__nodeid = (!empty($nodeid) && isset($nodeid["$k"]) ? ' data-nodeid="'.$nodeid["$k"].'"' : '');
                $html[] = '<option value="' . $k . '"' . $__selected . $__desc . $__nodeid . '>' . $v . '</option>';
            }
            return implode('', $html);
        }

        private function build_input_text( $param, $placeholder, $default='', $extra=array() ) {
            $extra = array_replace_recursive(array(
                'prefix'        => 'aiowaff-search',
                'desc'          => array(),
                'nodeid'        => array(),
            ), $extra);
            extract($extra);

            $name = $prefix.'['.$param.']';
            $id = "$prefix-$param";

            return '<input placeholder="' . $placeholder . '" name="' . $name . '" id="' . $id . '" type="text" value="' . (isset($default) && !empty($default) ? $default : '') . '"' . '>';
        }

        public function get_categories_html( $provider='amazon' ) {
            $categories = $this->get_categories('name', 'nice_name', $provider);
            $nodes = $this->get_categories('name', 'nodeid', $provider);
            return $this->build_select('category', $categories, '', array('nodeid' => $nodes));
        }
        
        public function build_searchform_element( $elm_type, $param, $value, $default, $extra=array() ) {
            $extra = array_replace_recursive(array(
                'global_desc'           => '',
                'desc'                  => array(),
            ), $extra);
            extract($extra);

            $css = array();
            $fa = 'fa-bars';
            if ( $param == 'Sort' ) {
                $fa = 'fa-sort';
            } else if ( $param == 'BrowseNode' ) {
                $fa = 'fa-sitemap';
                $css[] = 'aiowaff-param-node';
            }
            $css = !empty($css) ? ' ' .implode(' ', $css) : '';
            
            $html = array();
            $html[] = '<li class="aiowaff-param-optional'.$css.'">';
            $html[] =       '<span class="tooltip" title="'.$global_desc.'" data-title="'.$global_desc.'"><i class="fa '.$fa.'"></i></span>';
            $nice_name = $this->the_plugin->__category_nice_name( $param );
            if ( $elm_type == 'input' ) {
                $value = $nice_name;
                $html[] =   $this->build_input_text( $param, $value, $default, $extra );
            } else if ( $elm_type == 'select' ) {
            	$css = ' class=""';
            	if ( !empty($desc) && is_array($desc) ) {
            		$css = ' class="aiowaff-search-opt-desc"';
            	}
                $html[] =   '<select id="aiowaff-search-'.$param.'" name="aiowaff-search['.$param.']"'.$css.'>';
                $html[] =       '<option value="" disabled="disabled">'.$nice_name.'</option>';
                $html[] =   $this->build_select( $param, $value, $default, $extra );
                $html[] =   '</select>';
            }
            $html[] = '</li>';
            return implode('', $html);
        }
        
        public function get_category_params_html( $retType='die', $pms=array() ) {
            $ret = array(
                'status'        => 'invalid',
                'html'          => '',
            );
   
            $requestData = array(
                'what_params'           => isset($_REQUEST['what_params']) ? $_REQUEST['what_params'] : 'all',
                'category'              => isset($_REQUEST['category']) ? $_REQUEST['category'] : '',
                'nodeid'                => isset($_REQUEST['nodeid']) ? $_REQUEST['nodeid'] : '',
                'provider'              => isset($_REQUEST['provider']) ? $_REQUEST['provider'] : '',
            );
            foreach ($requestData as $rk => $rv) {
                if ( isset($pms["$rk"]) ) {
                    $new_val = $pms["$rk"];
                    $requestData["$rk"] = $new_val;
                }
            }
            extract($requestData);

            require('lists.inc.php');
            
            $optionalParameters = self::$optionalParameters["$provider"];
            if ( is_array($what_params) && !empty($what_params) ) {
                $optionalParameters = array_intersect_key($optionalParameters, array_flip($what_params));
            }

            // search parameters
            $ItemSearchParameters = array();
            if (!empty($optionalParameters) && !empty($nodeid)
				&& in_array($provider, array('amazon', 'alibaba'))) {
                $ItemSearchParameters = $this->get_ws_object( $provider )->getAmazonItemSearchParameters();
            }
    
            // sort parameters
            $ItemSortValues = array();
            if (!empty($optionalParameters)  && !empty($nodeid)
				&& in_array($provider, array('amazon', 'alibaba'))) {
                $ItemSortValues = $this->get_ws_object( $provider )->getAmazonSortValues();
            }
 
            $html = array();
            foreach ($optionalParameters as $oparam => $type) {
                
                if ( (!isset($ItemSearchParameters[$category]) || !in_array($oparam, $ItemSearchParameters[$category]))
                    && $oparam != 'Sort'
                    && in_array($provider, array('amazon', 'alibaba')) ) {
                    continue 1;
                }
                if ( $oparam == 'Sort' && (empty($category) || $category == 'AllCategories') ) {
                    continue 1;
                }
  	
                $desc           = array();
                $global_desc    = isset($aiowaff_search_params_desc["$provider"]["$oparam"])
                    ? $aiowaff_search_params_desc["$provider"]["$oparam"] : '';
                $value          = isset($aiowaff_search_params["$provider"]["$oparam"])
                    ? $aiowaff_search_params["$provider"]["$oparam"] : '';

                if ( $oparam == 'BrowseNode' ) {
                    
                    $value = $this->get_browse_nodes( $nodeid, $provider );

                }
				// amazon
				else if ( $oparam == 'Sort' ) {

					$value = (array) $value;
                    $curr_sort = array();
                    if ( isset($ItemSortValues[$category]) ) {
                        $curr_sort = $ItemSortValues[$category];
                    }
  
                    foreach ( $value as $skey => $stext ){
                        if ( empty($curr_sort) || !in_array( $skey, $curr_sort) ){
                            unset($value["$skey"]);
                        }
                        $desc["$skey"] = $aiowaff_search_params_sort["$provider"]["$skey"];
                    }
                }
				// ebay
				else if ( in_array($oparam, array('sortOrder', 'Condition', 'Currency', 'ListingType', 'PaymentMethod')) ) {
  
					$value = (array) $value;
                    foreach ( $value as $skey => $stext ){
                        $desc["$skey"] = isset($aiowaff_search_params_sel["$provider"]["$oparam"]["$skey"]) 
                        	? $aiowaff_search_params_sel["$provider"]["$oparam"]["$skey"] : $global_desc;
                    }
                }
                
                $extra = array(
                    'global_desc'       => $global_desc,
                    'desc'              => $desc,
                );

                if ( ($type == 'select' && !empty($value)) || ($type == 'input') ) {
                	$default = '';
                    $html[] = $this->build_searchform_element( $type, $oparam, $value, $default, $extra );
                }
            }
 
            $ret = array_merge($ret, array(
                'status'        => !empty($html) ? 'valid' : 'invalid',
                'html'          => implode('', $html),
            ));
            
            if ( $retType == 'return' ) { return $ret; }
            else { die( json_encode( $ret ) ); }
        }

        public function get_browse_nodes_html( $retType='die', $pms=array() ) {
            $requestData = array(
                'what_params'           => array('BrowseNode'),
                'category'              => isset($_REQUEST['category']) ? $_REQUEST['category'] : '',
                'nodeid'                => isset($_REQUEST['nodeid']) ? $_REQUEST['nodeid'] : '',
                'provider'              => isset($_REQUEST['provider']) ? $_REQUEST['provider'] : '',
            );
            foreach ($requestData as $rk => $rv) {
                if ( isset($pms["$rk"]) ) {
                    $new_val = $pms["$rk"];
                    $requestData["$rk"] = $new_val;
                }
            }
            extract($requestData);

            $ret = $this->get_category_params_html($retType, $requestData);

            if ( $retType == 'return' ) { return $ret; }
            else { die( json_encode( $ret ) ); }
        }


        /**
         * Export ASINs
         */
        public function ajax_export_asin() {
            $req = array(
                'asins'                 => isset($_REQUEST['asins']) ? (array) $_REQUEST['asins'] : array(),
                'export_asins_type'     => isset($_REQUEST['export_asins_type']) ? $_REQUEST['export_asins_type'] : '1',
                'delimiter'             => isset($_REQUEST['delimiter']) ? $_REQUEST['delimiter'] : 'newline',
                'do_export'             => isset($_REQUEST['do_export']) ? true : false,
            );
            $req = array_merge($req, array(
                'export_type'           => 'csv',
            ));
            extract($req);
            if ( $delimiter == 'newline' ) {
                $delimiter = "\n";
            } else if ( $delimiter == 'comma' ) {
                $delimiter = ",";
            } else if ( $delimiter == 'tab' ) {
                $delimiter = "\t";
            }
            $req["delimiter"] = $delimiter;
 
            $ret = array(
                'status'    => 'invalid',
                'msg'      => '',
            );
            
            if ( empty($export_asins_type) ) {
                $ret = array_merge($ret, array(
                    'msg'      => 'Please choose an export asins type!'
                ));
                die(json_encode( $ret ));
            }
            
            $file_rows = array_merge(array(0 => 'ASINs List'), $asins);
            if ( empty($file_rows) ) {
                $ret = array_merge($ret, array(
                    'msg'      => 'No ASINs found to export!'
                ));
                die(json_encode( $ret ));
            }
            
            if ( $do_export ) {
                $this->do_export( $file_rows, $req );
                die;
            }
            
            $ret = array_merge($ret, array(
                'status'        => 'valid',
                'msg'          => 'export was successfull.',
            ));
            die(json_encode( $ret ));
        }

        private function do_export( $result, $req ) {
            if (!$result) return false;
            
            extract($req);
            
            $filename = $this->__export_filename($req);
            switch ($export_type) {
                case 'csv' :
                    $file_ext = 'csv';
                    $content_type = 'text/csv';
                    break;
                    
                case 'sml':
                    $file_ext = 'xls';
                    $content_type = 'application/vnd.ms-excel';
                    //xls: application/vnd.ms-excel
                    //xlsx: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
                    
                    require_once( $this->the_plugin->cfg['paths']['scripts_dir_path'] . '/php-export-data/php-export-data.class.php' );
                    $exporter = null; 
                    if( class_exists('ExportDataExcel') ){
                        $exporter = new ExportDataExcel('string', 'test.xls');
                    }
                    break;
            }

            ob_end_clean();

            // export headers
            ///*
            header("Content-Description: File Transfer");           
            header("Content-Type: $content_type; charset=utf-8"); //application/force-download
            header("Content-Disposition: attachment; filename=$filename.$file_ext");
            // Disable caching
            header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
            header("Cache-Control: private", false);
            header("Pragma: no-cache"); // HTTP 1.0
            header("Expires: 0"); // Proxies
            //*/
            
            //echo "record1,record2,record3\n"; die;
 
            $isExport = false;
            if ( $export_type == 'csv'
                || ( $export_type == 'sml' && !is_null($exporter) ) ) {
                $isExport = true;
            }

            // begin export file
            if ( $isExport ) {
                $fp = fopen('php://output', 'w');
                $headrow = $result[0];
                $headrow = array($headrow);
                //$headrow = array_keys($headrow);
                $headrow = array_map(array($this, '__nice_title'), $headrow);
                unset($result[0]);
            }
  
            // export file content
            if ( $export_type == 'csv' ) {
                $this->__fputcsv_eol($fp, $headrow, ',', '"', $delimiter);
                foreach ($result as $data) {
                    $this->__fputcsv_eol($fp, array($data), ',', '"', $delimiter);
                }
                
            } else if ( $export_type == 'sml' && !is_null($exporter) ) {
                $exporter->initialize(); // starts streaming data to web browser
                
                // pass addRow() an array and it converts it to Excel XML format and sends 
                // it to the browser
                $exporter->addRow($headrow); 
                //$exporter->addRow(array("This", "is", "a", "test")); 
                //$exporter->addRow(array(1, 2, 3, "123-456-7890"));
                
                foreach ($result as $data) {
                    $exporter->addRow($data);
                }
                
                $exporter->finalize(); // writes the footer, flushes remaining data to browser.
                
                $content = $exporter->getString();
                fwrite($fp, $content);
            }
            
            // end export file
            if ( $isExport ) {
                fclose($fp);
            }

            $contLength = ob_get_length();
            //header( 'Content-Length: '.$contLength);

            die;
        }

        private function __export_filename( $req ) {
            extract($req);

            $f = array();
            $f[] = 'wooallinone_IM_export_asins';
            $f[] = time();
            
            return implode('__', $f);         
        }
        
        private function __nice_title($item) {
            $title = str_replace('_', ' ', $item);
            $title = ucwords($title);
            return $title;
        }
        
        private function __old_fputcsv_eol($handle, $array, $delimiter = ',', $enclosure = '"', $eol = "\n") {
            $return = fputcsv($handle, $array, $delimiter, $enclosure);
            if($return !== FALSE && "\n" != $eol && 0 === fseek($handle, -1, SEEK_CUR)) {
                fwrite($handle, $eol);
            }
            return $return;
        }
        
        private function __fputcsv_eol($fh, array $fields, $delimiter = ',', $enclosure = '"', $eol = "\n", $mysql_null = false) { 
            $delimiter_esc = preg_quote($delimiter, '/'); 
            $enclosure_esc = preg_quote($enclosure, '/'); 

            $output = array(); 
            foreach ($fields as $field) { 
                if ($field === null && $mysql_null) { 
                    $output[] = 'NULL'; 
                    continue; 
                } 

                $output[] = preg_match("/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field) ? ( 
                    $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure 
                ) : $field; 
            }

            fwrite($fh, join($delimiter, $output) . $eol); 
        }
        
        
        /**
         * Cache related
         */
        // build cache name
        private function buildCacheName($pms) {
            extract($pms);
            $arr = array();
            $ret = array();

            if ( $cache_type == 'search' ) {
                $ret['folder'] = self::$CACHE['search_folder'] . $provider.'/';
                $ret['cache_lifetime'] = self::$CACHE['search_lifetime'];
				
				$arr['provider'] = $provider;
				$arr = array_merge($arr, $params1);
                $arr = array_merge($arr, $params2);
                if ( isset($arr['ItemPage']) ) unset($arr['ItemPage']);

                $cachename = md5( json_encode( $arr ) );
                
            } else if ( $cache_type == 'prods' ) {

                $ret['folder'] = self::$CACHE['prods_folder'] . $provider.'/';
                $ret['cache_lifetime'] = self::$CACHE['prods_lifetime'];
                
				$arr['provider'] = $provider;
                $arr['asin'] = $asin;
                
                $cachename = strtolower($arr['asin']);
            }

            //$cachename = md5( json_encode( $arr ) );
            return (object) array_merge($ret, array(
            	'provider'			=> $provider,
                'cache_type'        => $cache_type,
                'filename'          => $cachename,
                'params'            => $arr
            ));
        }
        
        // get cache data
        private function getTheCache($pms) {
            extract($pms);
            $u = $this->the_plugin->u;

            $cachename = $this->buildCacheName($pms);
            $filename = $cachename->folder . ( $cachename->filename ) . '.json';

            // read from cache!
            if ( $u->needNewCache($filename, $cachename->cache_lifetime) !== true ) { // no need for new cache!
   
                $body = $u->getCacheFile($filename);
  
                if (is_null($body) || !$body || trim($body)=='') { // empty cache file
                } else {
                    $ret = $body;
                    //$ret = json_decode( $ret );
                    $ret = unserialize( $ret );
                    return $ret;
                }
            }
            return false;
        }
        
        // set cache data
        private function setTheCache($pms, $content, $old_content=array(), $do_write=true) {
            if ( empty($content) ) return false;
            extract($pms);
            $u = $this->the_plugin->u;

            $cachename = $this->buildCacheName($pms);
            $filename = $cachename->folder . ( $cachename->filename ) . '.json';

            $dataToSave = array();
            if ( $cache_type == 'prods' ) {
 
                if ( !empty($old_content) ) {
                    $dataToSave = $old_content;
                }
				$dataToSave = array_replace_recursive($dataToSave, $content);

            } else if ( $cache_type == 'search' ) {

				/*
                if ( !empty($old_content) ) {
                    $dataToSave = $old_content;
                } else {
                    $dataToSave['Items']['TotalResults'] = $content['Items']['TotalResults'];
                    $dataToSave['Items']['NbPagesSelected'] = $cachename->params['nbpages'];
                }

                if ( is_array($content) && !isset($content['__notused__']) ) {

                    $dataToSave["$page"] = array();
                    $response = $content;

                    // 1 item found only
                    if ( $dataToSave['Items']['TotalResults'] == 1 && !isset($response['Items']['Item'][0]) ) {
                        $response['Items']['Item'] = array($response['Items']['Item']);
                    }

                    foreach ($response['Items']['Item'] as $key => $value) {
                        $product = $this->build_product_data( $value, array(), $provider );
                        if ( !empty($product['ASIN']) ) {
                            $dataToSave["$page"]['Items']['Item']["$key"] = $product['ASIN'];
                        }
                    }

                    // 1 item found only
                    if ( $dataToSave['Items']['TotalResults'] == 1 && !isset($response['Items']['Item'][0]) ) {
                        $dataToSave["$page"]['Items']['Item'] = $dataToSave["$page"]['Items']['Item'][0];
                    }
                }
				*/
				$rsp = $this->get_ws_object( $provider )->api_cache_set_page_content(array(
					'requestData'			=> $requestData,
					'content'				=> $content,
					'old_content'			=> $old_content,
					'cachename'				=> $cachename,
					'page'					=> $page,
				));
				$dataToSave = $rsp['dataToSave'];
            }

            // return instead of write content to file
            if ( !$do_write ) {
                return array(
                    'dataToSave'        => $dataToSave,
                    'filename'          => $filename,
                );
            }

            $dataToSave = serialize( $dataToSave );
            //$dataToSave = json_encode( $dataToSave );
            return $u->writeCacheFile( $filename, $dataToSave ); // write new local cached file! - append new data
        } 

        // delete cache data
        private function deleteTheCache($pms) {
            $u = $this->the_plugin->u;
            
            $cachename = $this->buildCacheName($pms);
            $filename = $cachename->folder . ( $cachename->filename ) . '.json';
            return $u->deleteCache($filename);
        }

        // cache status (enabled | disabled)
        public function setCacheStatus($cache_type, $new_status='') {
            if ( !empty($new_status) && is_bool($new_status) ) {
                self::$CACHE_ENABLED["$cache_type"] = $new_status;
            }
            return self::$CACHE_ENABLED["$cache_type"];
        }

		public function getCacheSettings() {
			return array_merge(array(), self::$CACHE_ENABLED, self::$CACHE);
		}


        /**
         * Utils
         */
        // get categories; retkey = nodeid | name
        private function get_categories( $retkey='name', $retval='nice_name', $provider='amazon' ) {
            $ret = array();
            $categs = $this->get_ws_object( $provider )->getAmazonCategs();
            $categs = array_flip($categs);
            foreach ($categs as $key => $categ_name) {
                if ( $retval == 'nice_name' ) {
                    $__categ_name = $this->the_plugin->__category_nice_name($categ_name);
                } else if ( $retval == 'nodeid' ) {
                    $__categ_name = $key;
                }
                $__key = $retkey == 'name' ? $categ_name : $key; // key = nodeid
                $ret["$__key"] = $__categ_name;
            }
            return $ret;
        }
        
        private function get_importin_category() {
            $args = array(
                'orderby'   => 'menu_order',
                'order'     => 'ASC',
                'hide_empty' => 0,
                'post_per_page' => '-1'
            );
            $categories = get_terms('product_cat', $args);
              
            $args = array(
                'show_option_all'    => '',
                'show_option_none'   => 'Use category from Webservice',
                'orderby'            => 'ID', 
                'order'              => 'ASC',
                'show_count'         => 0,
                'hide_empty'         => 0, 
                'child_of'           => 0,
                'exclude'            => '',
                'echo'               => 0,
                'selected'           => 0,
                'hierarchical'       => 1, 
                'name'               => 'aiowaff-to-category',
                'id'                 => 'aiowaff-to-category',
                'class'              => 'postform',
                'depth'              => 0,
                'tab_index'          => 0,
                'taxonomy'           => 'product_cat',
                'hide_if_empty'      => false,
            );
            return wp_dropdown_categories( $args );
        }

        private function get_browse_nodes( $nodeid, $provider, $option_none=true ) {
            $ret = array();
            $first = false;
            $nodes = $this->the_plugin->getBrowseNodes( $nodeid, $provider );
			if ( empty($nodes) ) return $ret;
  
            foreach ($nodes as $key => $value){
            		
            	if ( 'amazon' == $provider ) {
            		$browse_node = isset($value['BrowseNodeId']) && trim($value['BrowseNodeId']) != ""
            			? $value['BrowseNodeId'] : array();
					$name = !empty($browse_node) ? $value['Name'] : '';
				}
				else if ( 'ebay' == $provider ) {
            		$browse_node = isset($value['CategoryID']) && trim($value['CategoryID']) != ""
            			? $value['CategoryID'] : array();
					$name = !empty($browse_node) ? $value['CategoryName'] : '';
				}
				
                if( !empty($browse_node) ) {
                    if ( !$first && $option_none ) {
                        $ret[''] = 'All Browse Nodes';
                        $first = true;
                    }
                    //$browse_node = $value['BrowseNodeId'];
                    //$name = $value['Name'];
                    $ret["$browse_node"] = $name;                    
                }
            }
            return $ret;
        }

        // get products already imported in database
        private function get_products_already_imported() {
            $your_products = (array) $this->the_plugin->getAllProductsMeta('array', '_amzASIN', true, 'all');
            if( empty($your_products) || !is_array($your_products) ){
                $your_products = array();
            }
            return $your_products;
        }

        // setup amazon object for making request
        private function setupAmazonWS() {
            $settings = $this->settings;

            // load the amazon webservices client class
            require_once( $this->the_plugin->cfg['paths']['plugin_dir_path'] . '/lib/scripts/amazon/aaAmazonWS.class.php' );
            
            // create new amazon instance
            $aaAmazonWS = new aaAmazonWS(
                $settings['AccessKeyID'],
                $settings['SecretAccessKey'],
                $settings['country'],
                $this->the_plugin->main_aff_id()
            );
            $aaAmazonWS->set_the_plugin( $this->the_plugin );
        }
        
        // build single product data based on amazon request array
        private function build_product_data( $item=array(), $old_item=array(), $provider='amazon' ) {
            return $this->get_ws_object( $provider )->build_product_data( $item, $old_item );
        }
		
		/**
		 * Octomber 2015 - new plugin functions
		 */
		public function get_ws_object( $provider, $what='helper' ) {
			return $this->the_plugin->get_ws_object( $provider, $what );
		}
    
		private function do_sleep( $provider ) {
			$rd = isset(self::$REQUESTS_DELAY["$provider"]) ? self::$REQUESTS_DELAY["$provider"] : array();
			if ( empty($rd) || !isset($rd['nbreq']) || !isset($rd['delay']) ) return;
			if ( empty($rd['nbreq']) || empty($rd['delay']) ) return;
			
			$nbreq = $rd['nbreq'];
			$delay = $rd['delay'];
			$current_nbreq = self::$REQUESTS_NB["$provider"]['current'];

			if ( $nbreq <= $current_nbreq ) {
				self::$REQUESTS_NB["$provider"]['current'] = 0;
				usleep( $delay );
			}
			return;
		}
		
		private function inc_nbreq( $provider, $from='' ) {
			if ( !in_array($from, array('search_byasin', 'search_bypages')) ) return;
			if ( !isset(self::$REQUESTS_NB["$provider"]) ) return;

			// increase number of requests made based on provider & from parameters
			$inc = 1;
			if ( 'search_byasin' == $from ) {
				if ( 'envato' == $provider ) {
					 $inc = 2; // 2 requests are made in provider aaEnvatoWS class file
				}
			}
			else if ( 'search_bypages' == $from ) ;
			
			self::$REQUESTS_NB["$provider"]['current'] += $inc;
			self::$REQUESTS_NB["$provider"]['total'] += $inc;
			
			// make delay if necessary
			$this->do_sleep( $provider );
		}
	
		private function provider_is_enabled( $provider ) {
			//$providers = $this->the_plugin->get_ws_prefixes();
			$providers_status = $this->the_plugin->get_ws_status();
			if ( isset($providers_status["$provider"]) && $providers_status["$provider"] ) return true;
			return false;
		}
	}
}

// Initialize the aiowaffInsaneImport class
$aiowaffInsaneImport = aiowaffInsaneImport::getInstance();