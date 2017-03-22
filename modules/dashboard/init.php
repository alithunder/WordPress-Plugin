<?php
/*
* Define class aiowaffDashboard
* Make sure you skip down to the end of this file, as there are a few
* lines of code that are very important.
*/
!defined('ABSPATH') and exit;
if (class_exists('aiowaffDashboard') != true) {
    class aiowaffDashboard
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
		
		public $ga = null;
		public $ga_params = array();
		
		public $boxes = array();

		static protected $_instance;

        /*
        * Required __construct() function that initalizes the Ali Framework
        */
        public function __construct()
        { 
        	global $aiowaff;
 
        	$this->the_plugin = $aiowaff;
			$this->module_folder = $this->the_plugin->cfg['paths']['plugin_dir_url'] . 'modules/dashboard/';
			
			if (is_admin()) {
	            add_action( "admin_enqueue_scripts", array( &$this, 'admin_print_styles') );
				add_action( "admin_print_scripts", array( &$this, 'admin_load_scripts') );
			}
			 
			// load the ajax helper
			require_once( $this->the_plugin->cfg['paths']['plugin_dir_path'] . 'modules/dashboard/ajax.php' );
			new aiowaffDashboardAjax( $this->the_plugin );
			
			// add the boxes
			$this->addBox( 'website_preview', '', $this->website_preview(), array(
				'size' => 'grid_1'
			) );
			
			$this->addBox( 'dashboard_links', '', $this->links(), array(
				'size' => 'grid_3'
			) );
			
			$providers = $this->the_plugin->get_ws_prefixes();
			$providers_status = $this->the_plugin->get_ws_status();
			foreach ($providers as $pkey => $palias) {
				$providers["$pkey"] = ucfirst($pkey);					
				if ( isset($providers_status["$pkey"]) && $providers_status["$pkey"] ) ;
				else unset( $providers["$pkey"] );					
			}
			$providers_ = array();
			foreach ($providers as $pkey => $ptitle) {
				$providers_[] = '<option value="'.$pkey.'">'.$ptitle.'</option>';
			}
			$products_performances_title = 'Provider 
				<select class="aiowaff-numer-items-in-top aiowaff-provider" style="width: 110px !important;">' . implode(PHP_EOL, $providers_) . '
				</select> Top 
				<select class="aiowaff-numer-items-in-top aiowaff-prods_per_page">
					<option value="10">10</option>
					<option value="20">20</option>
					<option value="30">30</option>
					<option value="50">50</option>
					<option value="100">100</option>
					<option value="0">Show All</option>
				</select>
				Products Performances';
			$this->addBox( 'products_performances', $products_performances_title, $this->products_performances(), array(
				'size' => 'grid_4'
			) );
			
		
			
        }

		/**
	    * Singleton pattern
	    *
	    * @return aiowaffDashboard Singleton instance
	    */
	    static public function getInstance()
	    {
	        if (!self::$_instance) {
	            self::$_instance = new self;
	        }

	        return self::$_instance;
	    }
	    
		public function admin_print_styles()
		{
			wp_register_style( 'aiowaff-DashboardBoxes', $this->module_folder . 'app.css', false, '1.0' );
        	wp_enqueue_style( 'aiowaff-DashboardBoxes' );
		}
		
		public function admin_load_scripts()
		{
			wp_enqueue_script( 'aiowaff-DashboardBoxes', $this->module_folder . 'app.class.js', array(), '1.0', true );
		}
		
		public function getBoxes()
		{
			$ret_boxes = array();
			if( count($this->boxes) > 0 ){
				foreach ($this->boxes as $key => $value) { 
					$ret_boxes[$key] = $value;
				}
			}
 
			return $ret_boxes;
		}
		
		private function formatAsFreamworkBox( $html_content='', $atts=array() )
		{
			return array(
				'size' 		=> isset($atts['size']) ? $atts['size'] : 'grid_4', // grid_1|grid_2|grid_3|grid_4
	            'header' 	=> isset($atts['header']) ? $atts['header'] : false, // true|false
	            'toggler' 	=> false, // true|false
	            'buttons' 	=> isset($atts['buttons']) ? $atts['buttons'] : false, // true|false
	            'style' 	=> isset($atts['style']) ? $atts['style'] : 'panel-widget', // panel|panel-widget
	            
	            // create the box elements array
	            'elements' => array(
	                array(
	                    'type' => 'html',
	                    'html' => $html_content
	                )
	            )
			);
		}
		
		private function addBox( $id='', $title='', $html='', $atts=array() )
		{ 
			// check if this box is not already in the list
			if( isset($id) && trim($id) != "" && !isset($this->boxes[$id]) ){
				
				$box = array();
				
				$box[] = '<div class="aiowaff-dashboard-status-box">';
				if( isset($title) && trim($title) != "" ){
					$box[] = 	'<h1>' . ( $title ) . '</h1>';
				}
				$box[] = 	$html;
				$box[] = '</div>';
				
				$this->boxes[$id] = $this->formatAsFreamworkBox( implode("\n", $box), $atts );
				
			}
		}
		
		public function formatRow( $content=array() )
		{
			$html = array();
			
			$html[] = '<div class="aiowaff-dashboard-status-box-row">';
			if( isset($content['title']) && trim($content['title']) != "" ){
				$html[] = 	'<h2>' . ( isset($content['title']) ? $content['title'] : 'Untitled' ) . '</h2>';
			}
			if( isset($content['ajax_content']) && $content['ajax_content'] == true ){
				$html[] = '<div class="aiowaff-dashboard-status-box-content is_ajax_content">';
				$html[] = 	'{' . ( isset($content['id']) ? $content['id'] : 'error_id_missing' ) . '}';
				$html[] = '</div>';
			}
			else{
				$html[] = '<div class="aiowaff-dashboard-status-box-content is_ajax_content">';
				$html[] = 	( isset($content['html']) && trim($content['html']) != "" ? $content['html'] : '!!! error_content_missing' );
				$html[] = '</div>';
			}
			$html[] = '</div>';
			
			return implode("\n", $html);
		}
		
		public function products_performances()
		{
			$html = array();
			
			$html[] = $this->formatRow( array( 
				'id' 			=> 'products_performances',
				'title' 		=> '',
				'html'			=> '',
				'ajax_content' 	=> true
			) );
			
			return implode("\n", $html);
		}

		public function support()
		{
			$html = array();
			$html[] = '<a href="http://support.Ali.com" target="_blank"><img src="' . ( $this->module_folder ) . 'assets/support_banner.jpg"></a>';
			
			return implode("\n", $html);
		}
		
	
		
		
		public function audience_overview()
		{
			$html = array();
			$html[] = '<div class="aiowaff-audience-graph" id="aiowaff-audience-visits-graph" data-fromdate="' . ( date('Y-m-d', strtotime("-1 week")) ) . '" data-todate="' . ( date('Y-m-d') ) . '"></div>';

			return  implode("\n", $html);
		}
		
		public function website_preview()
		{
			$html = array();
			$html[] = '<div class="aiowaff-website-preview">';
			$html[] = 	'<h4><b style="color:#a46497;">All WP Store</b>  is the most complete affiliates plugin on the Market! </h4>';
			$html[] = 	'<p>We’ve combined all major affiliation programs : Amazon, eBay, Aliexpress Affiliates into one major Affiliation Wordpress Plugin!
</p>';
		
			
			return  implode("\n", $html);
		}
		
		public function links()
		{
			$html = array();
			$html[] = '<ul class="aiowaff-summary-links">';
			
			/*ob_start();
			var_dump('<pre>',array_keys($this->the_plugin->cfg['modules']),'</pre>');
			$__x = ob_get_contents();
			ob_end_clean();
			$html[]  = '<li>' . $__x .'</li>';*/

			foreach ($this->the_plugin->cfg['modules'] as $key => $value) {
  
				if( !in_array( $key, array_keys($this->the_plugin->cfg['activate_modules'])) ) continue;
				//var_dump('<pre>',$value[$key],'</pre>');  
				$in_dashboard = isset($value[$key]['in_dashboard']) ? $value[$key]['in_dashboard'] : array();
  
				if( count($in_dashboard) > 0 ){
					
					$html[] = '
						<li>
							<a href="' . ( $in_dashboard['url'] ) . '">
								<img src="' . ( $value['folder_uri']  . $in_dashboard['icon'] ) . '">
								<span class="text">' . ( $value[$key]['menu']['title'] ) . '</span>
							</a>
						</li>';
				}
			}
			
			$html[] = '</ul>';
			
			return implode("\n", $html);
		}
    }
}

// Initialize the aiowaffDashboard class
//$aiowaffDashboard = aiowaffDashboard::getInstance( isset($module) ? $module : array() );
//$aiowaffDashboard = new aiowaffDashboard( isset($module) ? $module : array() );
// $aiowaff->cfg, ( isset($module) ? $module : array()) 
$aiowaffDashboard = aiowaffDashboard::getInstance();