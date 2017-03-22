<?php
/*
* Define class aiowaffServerStatus
* Make sure you skip down to the end of this file, as there are a few
* lines of code that are very important.
*/
!defined('ABSPATH') and exit;

if (class_exists('aiowaffServerStatus') != true) {
    class aiowaffServerStatus
    {
        /*
        * Some required plugin information
        */
        const VERSION = '1.0';

        /*
        * Store some helpers config
        */
		public $the_plugin = null;

		private $module_folder = '';
		private $module = '';

		static protected $_instance;

        /*
        * Required __construct() function that initalizes the Ali Framework
        */
        public function __construct()
        {
        	global $aiowaff;

        	$this->the_plugin = $aiowaff;
			$this->module_folder = $this->the_plugin->cfg['paths']['plugin_dir_url'] . 'modules/server_status/';
			$this->module = $this->the_plugin->cfg['modules']['server_status'];

			if (is_admin()) {
	            add_action('admin_menu', array( &$this, 'adminMenu' ));
			}

			// load the ajax helper
			require_once( $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'modules/server_status/ajax.php' );
			new aiowaffServerStatusAjax( $this->the_plugin );
        }

		/**
	    * Singleton pattern
	    *
	    * @return aiowaffServerStatus Singleton instance
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
    			$this->the_plugin->alias . " " . __('Check System status', $this->the_plugin->localizationName),
	            __('System Status', $this->the_plugin->localizationName),
	            'manage_options',
	            $this->the_plugin->alias . "_server_status",
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
			
			$amz_settings = @unserialize( get_option( 'aiowaff_amazon' ) );
			$plugin_data = get_plugin_data( $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'plugin.php' );  
?>
		<link rel='stylesheet' href='<?php echo $this->module_folder;?>app.css' type='text/css' media='all' />
		<script type="text/javascript" src="<?php echo $this->module_folder;?>app.class.js" ></script>
		<div id="aiowaff-wrapper" class="fluid wrapper-aiowaff">
			
			<?php
			// show the top menu
			aiowaffAdminMenu::getInstance()->make_active('info|server_status')->show_menu();
			?>
			
			<!-- Main loading box -->
			<div id="aiowaff-main-loading">
				<div id="aiowaff-loading-overlay"></div>
				<div id="aiowaff-loading-box">
					<div class="aiowaff-loading-text"><?php _e('Loading', $this->the_plugin->localizationName);?></div>
					<div class="aiowaff-meter aiowaff-animate" style="width:86%; margin: 34px 0px 0px 7%;"><span style="width:100%"></span></div>
				</div>
			</div>

			<!-- Content -->
			<div id="aiowaff-content">
				
				<h1 class="aiowaff-section-headline">
					<?php 
					if( isset($this->module['server_status']['in_dashboard']['icon']) ){
						echo '<img src="' . ( $this->module_folder . $this->module['server_status']['in_dashboard']['icon'] ) . '" class="aiowaff-headline-icon">';
					}
					?>
					<?php echo $this->module['server_status']['menu']['title'];?>
					<span class="aiowaff-section-info"><?php echo $this->module['server_status']['description'];?></span>
					<?php
					$has_help = isset($this->module['server_status']['help']) ? true : false;
					if( $has_help === true ){
						
						$help_type = isset($this->module['server_status']['help']['type']) && $this->module['server_status']['help']['type'] ? 'remote' : 'local';
						if( $help_type == 'remote' ){
							echo '';
						} 
					}
					echo '';
					?>
				</h1>
				
				<!-- Container -->
				<div class="aiowaff-container clearfix">

					<!-- Main Content Wrapper -->
					<div id="aiowaff-content-wrap" class="clearfix" style="padding-top: 5px;">

						<!-- Content Area -->
						<div id="aiowaff-content-area">
							<div class="aiowaff-grid_4">
	                        	<div class="aiowaff-panel">
									<div class="aiowaff-panel-content">
										<table class="aiowaff-table" cellspacing="0">
											
<?php
$providers = $this->the_plugin->get_main_settings('all');
//var_dump('<pre>', $providers, '</pre>'); die('debug...');
?>

<?php
$html = array();
foreach ($providers as $pkey => $pval) {
	$html[] = 	'<thead>
					<tr>
						<th colspan="2">' . $pval['title'] . '</th>
					</tr>
				</thead>';
	$html[] = 	'<tbody>';

	foreach ($pval['keys'] as $pkey2 => $pval2) {
		$html[] = 		'<tr>';
		$html[] =			'<td width="190">' . $pval2['title'] . ':</td>';
		$html[] =			'<td>';
		
		if ( is_array($pval2['value']) ) {
			foreach ($pval2['value'] as $key => $value) {
				if ( trim($value) != "" ) {
					$html[] = "<strong>" . $key . ":</strong> " . $value . "<br />";
				}
			}
		}
		else {
			$html[] = $pval2['value'];
		}

		$html[] = 			'</td>';
		$html[]	=		'</tr>';
	}

	$html[] = 	'</tbody>';
}
?>

<?php echo implode(PHP_EOL, $html); ?> 
<?php /*
											<thead>
												<tr>
													<th colspan="2"><?php _e( 'Amazon Settings', $this->the_plugin->localizationName ); ?></th>
												</tr>
											</thead>
											
											<tbody>
												<tr>
									                <td width="190"><?php _e( 'Access Key ID',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php echo $amz_settings['AccessKeyID'];?></td>
									            </tr>
									            <tr>
									                <td width="190"><?php _e( 'Secret Access Key',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php echo $amz_settings['SecretAccessKey'];?></td>
									            </tr>
									            <tr>
									                <td width="190"><?php _e( 'Affiliate IDs',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php
									                	if ( isset($amz_settings['AffiliateID']) ) { foreach ($amz_settings['AffiliateID'] as $key => $value) {
									                		if( trim($value) != "" ){
									                			echo "<strong>" . $key . ":</strong> " . $value . "<br />";
									                		}
														} }
									                ?></td>
									            </tr>
									        </tbody>
*/ ?>
											
											<thead>
												<tr>
													<th colspan="2"><?php _e( 'aiowaff import settings', $this->the_plugin->localizationName ); ?></th>
												</tr>
											</thead>
											
											<tbody>
												<?php /*<tr>
									                <td width="190"><?php _e( 'Request Type',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php echo $amz_settings['protocol'];?></td>
									            </tr>
									            <tr>
									                <td width="190"><?php _e( 'Amazon API location',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php echo $amz_settings['country'];?></td>
									            </tr>
									            <tr>
									                <td width="190"><?php _e( 'On-site Cart',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php echo $amz_settings['onsite_cart'];?></td>
									            </tr>*/ ?>
									            <tr>
									                <td width="190"><?php _e( 'Download Item Attribute',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php echo $amz_settings['item_attribute'];?></td>
									            </tr>
									            <tr>
									                <td width="190"><?php _e( 'Variation',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php echo $amz_settings['product_variation'];?></td>
									            </tr>
									            <tr>
									                <td width="190"><?php _e( 'Number of images',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php echo $amz_settings['number_of_images'];?></td>
									            </tr>
									            <?php /*<tr>
									                <td width="190"><?php _e( 'Cross-selling',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php echo $amz_settings['cross_selling'];?></td>
									            </tr>*/ ?>
									        </tbody> 
											
											<?php /*<thead>
												<tr>
													<th colspan="2"><?php _e( 'Syncronize Capabilities Testing:', $this->the_plugin->localizationName ); ?></th>
												</tr>
											</thead>*/ ?>
<?php /*
											<tbody>
									            <tr>
									            	<td style="vertical-align: middle;">Import test:</td>
									                <td>
														<div class="aiowaff-import-products-test">
															<div class="aiowaff-test-timeline">
																<div class="aiowaff-one_step" id="stepid-step1">
																	<div class="aiowaff-step-status aiowaff-loading-inprogress"></div>
																	<span class="aiowaff-step-name">Step 1</span>
																</div>
																<div class="aiowaff-one_step" id="stepid-step2">
																	<div class="aiowaff-step-status"></div>
																	<span class="aiowaff-step-name">Step 2</span>
																</div>
																<div class="aiowaff-one_step" id="stepid-step3">
																	<div class="aiowaff-step-status"></div>
																	<span class="aiowaff-step-name">Step 3</span>
																</div>
																<div style="clear:both;"></div>
															</div>
															<table class="aiowaff-table aiowaff-logs" cellspacing="0">
																<tr id="logbox-step1">
																	<td width="50">Step 1:</td>
																	<td>
																		<div class="aiowaff-log-title">
																			Get product from Amazon.<?php echo $amz_settings['country'];?>
																			<a href="#" class="aiowaff-button gray">View details +</a>
																		</div>
																		
																		<textarea class="aiowaff-log-details"></textarea>
																	</td>
																</tr>
																<tr id="logbox-step2">
																	<td width="50">Step 2:</td>
																	<td>
																		<div class="aiowaff-log-title">
																			Import the product into woocomerce
																			<a href="#" class="aiowaff-button gray">View details +</a>
																		</div>
																		
																		<textarea class="aiowaff-log-details"></textarea>
																	</td>
																</tr>
																<tr id="logbox-step3">
																	<td width="50">Step 3:</td>
																	<td>
																		<div class="aiowaff-log-title">
																			Download images (<?php echo $amz_settings['number_of_images'];?>) for products
																			<a href="#" class="aiowaff-button gray">View details +</a>
																		</div>
																		
																		<textarea class="aiowaff-log-details"></textarea>
																	</td>
																</tr>
															</table>
															<div class="aiowaff-begin-test-container">
																<a href="#begin-test" class="aiowaff-button blue aiowaffStressTest">Begin the test</a>
																
																<input id="aiowaff-test-ASIN" value="B0074FGNJ6" type="text" />
																<label>Test with ASIN code</label>
															</div>
														</div>
													</td>
									            </tr>
											</tbody>
*/ ?>
											<thead>
												<tr>
													<th colspan="2"><?php _e( 'Environment', $this->the_plugin->localizationName ); ?></th>
												</tr>
											</thead>
									
											<tbody>
												<tr>
									                <td width="190"><?php _e( 'Home URL',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php echo home_url(); ?></td>
									            </tr>
									            <tr>
									                <td><?php _e( 'aiowaff Version',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php echo $plugin_data['Version'];?></td>
									            </tr>
									            <tr>
									                <td><?php _e( 'WP Version',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php if ( is_multisite() ) echo 'WPMU'; else echo 'WP'; ?> <?php bloginfo('version'); ?></td>
									            </tr>
									            <tr>
									                <td><?php _e( 'Web Server Info',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php echo esc_html( $_SERVER['SERVER_SOFTWARE'] );  ?></td>
									            </tr>
									            <tr>
									                <td><?php _e( 'PHP Version',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php if ( function_exists( 'phpversion' ) ) echo esc_html( phpversion() ); ?></td>
									            </tr>
									            <tr>
                                                    <td><?php _e( 'MySQL Version',$this->the_plugin->localizationName ); ?>:</td>
                                                    <td><?php if ( function_exists( 'mysql_get_server_info' ) ) echo esc_html( (is_resource($wpdb->dbh)) ? mysql_get_server_info( $wpdb->dbh ) : $wpdb->db_version() ); ?></td>
                                                </tr>
									            <tr>
									                <td><?php _e( 'WP Memory Limit',$this->the_plugin->localizationName ); ?>:</td>
									                <td><div class="aiowaff-loading-ajax-details" data-action="check_memory_limit"></div></td>
									            </tr>
									            <tr>
									                <td><?php _e( 'WP Debug Mode',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php if ( defined('WP_DEBUG') && WP_DEBUG ) echo __( 'Yes', $this->the_plugin->localizationName ); else echo __( 'No', $this->the_plugin->localizationName ); ?></td>
									            </tr>
									            <tr>
									                <td><?php _e( 'WP Max Upload Size',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php echo size_format( wp_max_upload_size() ); ?></td>
									            </tr>
									            <tr>
									                <td><?php _e('PHP Post Max Size',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php if ( function_exists( 'ini_get' ) ) echo size_format( woocommerce_let_to_num( ini_get('post_max_size') ) ); ?></td>
									            </tr>
									            <tr>
									                <td><?php _e('PHP Time Limit',$this->the_plugin->localizationName ); ?>:</td>
									                <td><?php if ( function_exists( 'ini_get' ) ) echo ini_get('max_execution_time'); ?></td>
									            </tr>
									            <tr>
									                <td><?php _e('WP Remote GET',$this->the_plugin->localizationName ); ?>:</td>
									                <td><div class="aiowaff-loading-ajax-details" data-action="remote_get"></div></td>
									            </tr>
									            <tr>
									                <td><?php _e('SOAP Client',$this->the_plugin->localizationName ); ?>:</td>
									                <td><div class="aiowaff-loading-ajax-details" data-action="check_soap"></div></td>
									            </tr>
									            <tr>
									                <td><?php _e('SimpleXML library',$this->the_plugin->localizationName ); ?>:</td>
									                <td><div class="aiowaff-loading-ajax-details" data-action="check_simplexml"></div></td>
									            </tr>
											</tbody>
									
											<thead>
												<tr>
													<th colspan="2"><?php _e( 'Plugins', $this->the_plugin->localizationName ); ?></th>
												</tr>
											</thead>
									
											<tbody>
									         	<tr>
									         		<td><?php _e( 'Installed Plugins',$this->the_plugin->localizationName ); ?>:</td>
									         		<td><div class="aiowaff-loading-ajax-details" data-action="active_plugins"></div></td>
									         	</tr>
											</tbody>
									
											<thead>
												<tr>
													<th colspan="2"><?php _e( 'Settings', $this->the_plugin->localizationName ); ?></th>
												</tr>
											</thead>
									
											<tbody>
									
									            <tr>
									                <td><?php _e( 'Force SSL',$this->the_plugin->localizationName ); ?>:</td>
													<td><?php echo get_option( 'woocommerce_force_ssl_checkout' ) === 'yes' ? __( 'Yes', $this->the_plugin->localizationName ) : __( 'No', $this->the_plugin->localizationName ); ?></td>
									            </tr>
											</tbody>
											
											<thead>
												<tr>
													<th colspan="2"><?php _e( 'Woocommerce Dependencies - Needed for the cart option to work properly', $this->the_plugin->localizationName ); ?></th>
												</tr>
											</thead>
									
											<tbody>
												<?php
													$check_pages = array(
														_x( 'Cart Page', 'Page setting', 'woocommerce' ) => array(
																'option' => 'woocommerce_cart_page_id',
																'shortcode' => '[' . apply_filters( 'woocommerce_cart_shortcode_tag', 'woocommerce_cart' ) . ']'
															),
														_x( 'Checkout Page', 'Page setting', 'woocommerce' ) => array(
																'option' => 'woocommerce_checkout_page_id',
																'shortcode' => '[' . apply_filters( 'woocommerce_checkout_shortcode_tag', 'woocommerce_checkout' ) . ']'
															),
													);
										
													$alt = 1;
										
													foreach ( $check_pages as $page_name => $values ) {
										
														if ( $alt == 1 ) echo '<tr>'; else echo '<tr>';
										
														echo '<td>' . esc_html( $page_name ) . ':</td><td>';
										
														$error = false;
										
														$page_id = get_option( $values['option'] );
										
														// Page ID check
														if ( ! $page_id ) {
															echo '<div class="aiowaff-message aiowaff-error">' . __( 'Page not set', 'woocommerce' ) . '</div>';
															$error = true;
														} else {
										
															// Shortcode check
															if ( $values['shortcode'] ) {
																$page = get_post( $page_id );
										
																if ( empty( $page ) ) {
										
																	echo '<div class="aiowaff-message aiowaff-error">' . sprintf( __( 'Page does not exist', 'woocommerce' ) ) . '</div>';
																	$error = true;
										
																} else if ( ! strstr( $page->post_content, $values['shortcode'] ) ) {
										
																	echo '<div class="aiowaff-message aiowaff-error">' . sprintf( __( 'Page does not contain the shortcode: %s', 'woocommerce' ), $values['shortcode'] ) . '</div>';
																	$error = true;
										
																}
															}
										
														}
										
														if ( ! $error ) echo '<div class="aiowaff-message aiowaff-success">#' . absint( $page_id ) . ' - ' . str_replace( home_url(), '', get_permalink( $page_id ) ) . '</div>';
										
														echo '</td></tr>';
										
														$alt = $alt * -1;
													}
												?>
											</tbody>											
											
											<!--tfoot>
												<tr>
													<th colspan="2">
														<a href="#" class="aiowaff-button blue aiowaff-export-logs">Export status log as file</a>
													</th>
												</tr>
											</tfoot-->
										</table>
				            		</div>
								</div>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</div>
			</div>
		</div>

<?php
		}

		/*
		* ajax_request, method
		* --------------------
		*
		* this will create requesto to 404 table
		*/
		public function ajax_request()
		{
			global $wpdb;
			$request = array(
				'id' 			=> isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0
			);
			
			$asin = $this->the_plugin->get_post_meta($request['id'], '_amzASIN', true);
			
			$sync = new wwcAmazonSyncronize( $this->the_plugin );
			$sync->updateTheProduct( $asin );
		}
    }
}

// Initialize the aiowaffServerStatus class
$aiowaffServerStatus = aiowaffServerStatus::getInstance();
