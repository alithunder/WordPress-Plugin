<?php
/**
 * Ali freamwork class
 * 
 * =======================
 *
 * @package		aiowaff
 * @author		Muhammad Ali
 * @version		1.0
 */
!defined('ABSPATH') and exit;

if (class_exists('aiowaff') != true) {

    class aiowaff {

        const VERSION = 1.0;
        // The time interval for the remote XML cache in the database (21600 seconds = 6 hours)
        const NOTIFIER_CACHE_INTERVAL = 21600;

        public $alias = 'aiowaff';
        public $details = array();
        public $localizationName = 'wooallinone';
        public $dev = '';
        public $debug = false;
        public $is_admin = false;

        /**
         * configuration storage
         *
         * @var array
         */
        public $cfg = array();

        /**
         * plugin modules storage
         *
         * @var array
         */
        public $modules = null;

        /**
         * errors storage
         *
         * @var object
         */
        private $errors = null;

        /**
         * DB class storage
         *
         * @var object
         */
        public $db = array();
        public $facebookInstance = null;
        public $fb_user_profile = null;
        public $fb_user_id = null;
        private $plugin_hash = null;
        private $v = null;
        // Products Providers Helpers!
        public $genericHelper = null;
        public $amzHelper = null;
        public $alibabaHelper = null;
        public $ebayHelper = null;
        public $jsFiles = array();
        public $wp_filesystem = null;
        private $opStatusMsg = array();
        public $charset = '';
        public $pluginDepedencies = null;
        public $pluginName = 'All WP Store';
        public $feedback_url = "feedback/index.php?app=%s&refferer_url=%s";
        public $app_settings = array(); // DEPRECATED; used only in method 'getShopConfig'
        public $amz_settings = array();
        public $u; // utils function object!
        public $timer; // timer object
        public $cur_provider = 'amazon';

        /**
         * The constructor
         */
        public function __construct($here = __FILE__) {
            $this->is_admin = is_admin() === true ? true : false;

            // get all amazon settings options
            //$this->amz_settings = $this->the_plugin->getAllSettings('array', 'amazon');
            $this->amz_settings = @unserialize(get_option($this->alias . '_amazon'));
            $this->build_amz_settings();

            //$current_url = $_SERVER['HTTP_REFERER'];
            $current_url = $this->get_current_page_url();
            $this->feedback_url = sprintf($this->feedback_url, $this->alias, rawurlencode($current_url));

            // load WP_Filesystem 
            include_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
            global $wp_filesystem;
            $this->wp_filesystem = $wp_filesystem;



            $this->plugin_hash = get_option('aiowaff_hash');

            // set the freamwork alias
            $this->buildConfigParams('default', array('alias' => $this->alias));

            // get the globals utils
            global $wpdb;

            // store database instance
            $this->db = $wpdb;

            // instance new WP_ERROR - http://codex.wordpress.org/Function_Reference/WP_Error
            $this->errors = new WP_Error();

            // charset
            if (isset($this->amz_settings['charset']) && !empty($this->amz_settings['charset']))
                $this->charset = $this->amz_settings['charset'];

            // plugin root paths
            $this->buildConfigParams('paths', array(
                // http://codex.wordpress.org/Function_Reference/plugin_dir_url
                'plugin_dir_url' => str_replace('aa-framework/', '', plugin_dir_url((__FILE__))),
                // http://codex.wordpress.org/Function_Reference/plugin_dir_path
                'plugin_dir_path' => str_replace('aa-framework/', '', plugin_dir_path((__FILE__)))
            ));

            // add plugin lib design paths and url
            $this->buildConfigParams('paths', array(
                'design_dir_url' => $this->cfg['paths']['plugin_dir_url'] . 'lib/design',
                'design_dir_path' => $this->cfg['paths']['plugin_dir_path'] . 'lib/design'
            ));

            // add plugin scripts paths and url
            $this->buildConfigParams('paths', array(
                'scripts_dir_url' => $this->cfg['paths']['plugin_dir_url'] . 'lib/scripts',
                'scripts_dir_path' => $this->cfg['paths']['plugin_dir_path'] . 'lib/scripts'
            ));

            // add plugin admin paths and url
            $this->buildConfigParams('paths', array(
                'freamwork_dir_url' => $this->cfg['paths']['plugin_dir_url'] . 'aa-framework/',
                'freamwork_dir_path' => $this->cfg['paths']['plugin_dir_path'] . 'aa-framework/'
            ));

            // add core-modules alias
            $this->buildConfigParams('core-modules', array(
                'amazon',
                'dashboard',
                'modules_manager',
                'setup_backup',
                'remote_support',
                'server_status',
                'insane_import',
                'support',
                'assets_download',
                'stats_prod',
                'price_select',
                //'amazon_debug',
                'woocustom',
                'cronjobs',
            ));

            // list of freamwork css files
            $this->buildConfigParams('freamwork-css-files', array(
                'core' => 'css/core.css',
                'panel' => 'css/panel.css',
                'form-structure' => 'css/form-structure.css',
                'form-elements' => 'css/form-elements.css',
                'form-message' => 'css/form-message.css',
                'button' => 'css/button.css',
                'table' => 'css/table.css',
                'tipsy' => 'css/tooltip.css',
                'admin' => 'css/admin-style.css'
            ));

            // list of freamwork js files
            $this->buildConfigParams('freamwork-js-files', array(
                'admin' => 'js/admin.js',
                'hashchange' => 'js/hashchange.js',
                'ajaxupload' => 'js/ajaxupload.js',
                'tipsy' => 'js/tooltip.js',
                'download_asset' => '../modules/assets_download/app.class.js'
            ));

            // utils functions
            require_once( $this->cfg['paths']['plugin_dir_path'] . 'aa-framework/utils/utils.php' );
            if (class_exists('aiowaff_Utils')) {
                // $this->u = new aiowaff_Utils( $this );
                $this->u = aiowaff_Utils::getInstance($this);
            }

            // timer functions
            require_once( $this->cfg['paths']['scripts_dir_path'] . '/runtime/runtime.php' );
            if (class_exists('aaRenderTime')) {
                //$this->timer = new aaRenderTime( $this );
                $this->timer = aaRenderTime::getInstance();
            }



            require_once( $this->cfg['paths']['plugin_dir_path'] . 'aa-framework/menu.php' );

            // Run the plugins section load method
            add_action('wp_ajax_aiowaffLoadSection', array(&$this, 'load_section'));

            // Plugin Depedencies Verification!
            if (get_option('aiowaff_depedencies_is_valid', false)) {
                require_once( $this->cfg['paths']['scripts_dir_path'] . '/plugin-depedencies/plugin_depedencies.php' );
                $this->pluginDepedencies = new aaTeamPluginDepedencies($this);

                // activation redirect to depedencies page
                if (get_option('aiowaff_depedencies_do_activation_redirect', false)) {
                    add_action('admin_init', array($this->pluginDepedencies, 'depedencies_plugin_redirect'));
                    return false;
                }

                // verify plugin library depedencies
                $depedenciesStatus = $this->pluginDepedencies->verifyDepedencies();
                if ($depedenciesStatus['status'] == 'valid') {
                    // go to plugin license code activation!
                    add_action('admin_init', array($this->pluginDepedencies, 'depedencies_plugin_redirect_valid'));
                } else {
                    // create depedencies page
                    add_action('init', array($this->pluginDepedencies, 'initDepedenciesPage'), 5);
                    return false;
                }
            }

            // Run the plugins initialization method
            add_action('init', array(&$this, 'initThePlugin'), 5);
            add_action('init', array($this, 'session_start'), 1);

            // Run the plugins section options save method
            add_action('wp_ajax_aiowaffSaveOptions', array(&$this, 'save_options'));

            // Run the plugins section options save method
            add_action('wp_ajax_aiowaffModuleChangeStatus', array(&$this, 'module_change_status'));

            // Run the plugins section options save method
            add_action('wp_ajax_aiowaffModuleChangeStatus_bulk_rows', array(&$this, 'module_bulk_change_status'));

            // Run the plugins section options save method
            add_action('wp_ajax_aiowaffInstallDefaultOptions', array(&$this, 'install_default_options'));

            // Amazon helper, import new product
            //add_action('wp_ajax_aiowaffPriceUpdate', array( &$this, 'productPriceUpdate_frm' ));

            add_action('wp_ajax_aiowaffUpload', array(&$this, 'upload_file'));
            add_action('wp_ajax_aiowaffDismissNotice', array(&$this, 'dismiss_notice'));

            if (is_admin()) {
                add_action('admin_head', array(&$this, 'createInstanceFreamwork'));
                $this->check_if_table_exists();
            }

            add_action('admin_init', array($this, 'plugin_redirect'));

            if ($this->debug == true) {
                add_action('wp_footer', array($this, 'print_plugin_usages'));
                add_action('admin_footer', array($this, 'print_plugin_usages'));
            }

            add_action('admin_init', array($this, 'product_assets_verify'));

            if (!is_admin()) {
                add_action('init', array($this, 'frontpage'));

                add_shortcode('amz_corss_sell', array($this, 'cross_sell_box'));
            }

            if (is_admin()) {
                add_action('admin_bar_menu', array($this, 'update_notifier_bar_menu'), 1000);
                add_action('admin_menu', array($this, 'update_plugin_notifier_menu'), 1000);
            }

            $this->check_amz_multiple_cart();

            require_once( $this->cfg['paths']['plugin_dir_path'] . 'aa-framework/ajax-list-table.php' );
            new aiowaffAjaxListTable($this);

            add_action('woocommerce_after_add_to_cart_button', array($this, 'woocommerce_external_add_to_cart'), 10);

            $config = $this->amz_settings;
            $p_type = ((isset($config['onsite_cart']) && $config['onsite_cart'] == "no") ? 'external' : 'simple');

            if ($p_type == 'simple')
                add_action('woocommerce_checkout_init', array($this, 'woocommerce_external_checkout'), 10);

            // GENERIC Helper
            if (1) {
                require_once( $this->cfg['paths']['plugin_dir_path'] . 'aa-framework/helpers/generic.helper.class.php' );

                if (class_exists('aiowaffGenericHelper')) {
                    // $this->genericHelper = new aiowaffGenericHelper( $this );
                    $this->genericHelper = aiowaffGenericHelper::getInstance($this);
                }
            }

            // AMAZON Helper
            if (isset($config['AccessKeyID']) && isset($config['SecretAccessKey']) && trim($config['AccessKeyID']) != "" && $config['SecretAccessKey'] != "") {
                require_once( $this->cfg['paths']['plugin_dir_path'] . 'aa-framework/helpers/amz.helper.class.php' );

                if (class_exists('aiowaffAmazonHelper')) {
                    // $this->amzHelper = new aiowaffAmazonHelper( $this );
                    $this->amzHelper = aiowaffAmazonHelper::getInstance($this);
                }
            }

            // ALIBABA Helper
            if (isset($config['alibaba_AppKey']) && isset($config['alibaba_TrackingID']) && trim($config['alibaba_AppKey']) != "" && $config['alibaba_TrackingID'] != "") {
                require_once( $this->cfg['paths']['plugin_dir_path'] . 'aa-framework/helpers/alibaba.helper.class.php' );

                if (class_exists('aiowaffAlibabaHelper')) {
                    // $this->alibabaHelper = new aiowaffAlibabaHelper( $this );
                    $this->alibabaHelper = aiowaffAlibabaHelper::getInstance($this);
                }
            }



            // EBAY Helper
            if (isset($config['ebay_DEVID']) && isset($config['ebay_AppID']) && isset($config['ebay_CertID']) && trim($config['ebay_DEVID']) != "" && $config['ebay_AppID'] != "" && trim($config['ebay_CertID']) != ""
            ) {
                require_once( $this->cfg['paths']['plugin_dir_path'] . 'aa-framework/helpers/ebay.helper.class.php' );

                if (class_exists('aiowaffEbayHelper')) {
                    // $this->ebayHelper = new aiowaffEbayHelper( $this );
                    $this->ebayHelper = aiowaffEbayHelper::getInstance($this);
                }
            }

            // ajax download lightbox
            add_action('wp_ajax_aiowaffDownoadAssetLightbox', array($this, 'download_asset_lightbox'));

            // admin ajax action
            require_once( $this->cfg['paths']['plugin_dir_path'] . 'aa-framework/utils/action_admin_ajax.php' );
            new aiowaff_ActionAdminAjax($this);

            // admin ajax action
            require_once( $this->cfg['paths']['plugin_dir_path'] . 'modules/cronjobs/cronjobs.core.php' );
            new aiowaffCronjobs($this);
            //aiowaffCronjobs::getInstance();

            $is_installed = get_option($this->alias . "_is_installed");
            if ($this->is_admin && $is_installed === false) {
                add_action('admin_print_styles', array($this, 'admin_load_styles'));
            }
        }

        public function session_start() {
            $session_id = isset($_COOKIE['PHPSESSID']) ? session_id($_COOKIE['PHPSESSID']) : ( isset($_REQUEST['PHPSESSID']) ? $_REQUEST['PHPSESSID'] : session_id() );
            if (!$session_id) {
                // session isn't started
                session_start();
            }
            //!isset($_SESSION['aateam_sess_dbg']) ? $_SESSION['aateam_sess_dbg'] = 0 : $_SESSION['aateam_sess_dbg']++;
            //var_dump('<pre>',$_SESSION['aateam_sess_dbg'],'</pre>');  			
        }

        public function session_close() {
            session_write_close(); // close the session
        }

        public function dismiss_notice() {
            update_option($this->alias . "_dismiss_notice", "true");
            header('Location: ' . sprintf(admin_url('admin.php?page=%s'), $this->alias));
            die;
        }

        /**
         * Operation Messages
         */
        public function opStatusMsgInit($pms = array()) {
            extract($pms);
            $this->opStatusMsg = array(
                'status' => isset($status) ? $status : 'invalid',
                'operation' => isset($operation) ? $operation : '',
                'operation_id' => isset($operation_id) ? (string) $operation_id : '',
                'msg_header' => isset($msg_header) ? $msg_header : '',
                'msg' => array(),
                'duration' => 0,
            );
            $this->opStatusMsgSetCache();
            return true;
        }

        public function opStatusMsgSet($pms = array()) {
            if (empty($pms))
                return false;
            foreach ($pms as $key => $val) {
                if ($key == 'msg') {
                    if (isset($pms['duration'])) {
                        $val .= ' - [ ' . (isset($pms['end']) ? 'total: ' : '') . $this->format_duration($pms['duration']) . ' ]';
                    }
                    $this->opStatusMsg["$key"][] = $val;
                } else {
                    $this->opStatusMsg["$key"] = $val;
                }
            }
            $this->opStatusMsgSetCache();
            return true;
        }

        public function opStatusMsgSetCache($from = 'file') {
            $this->session_close(); // close the session to allow asynchronous ajax calls

            if ($from == 'session') {
                $this->opStatusMsgSetSession();
            } else if ($from == 'cookie') {
                $this->opStatusMsgSetCookie();
            } else if ($from == 'file') {
                $this->opStatusMsgSetFile();
            }
        }

        private function opStatusMsgSetSession() {
            $this->session_start(); // start the session
            $_SESSION['aiowaff_opStatusMsg'] = serialize($this->opStatusMsg);
            $this->session_close(); // close the session
        }

        private function opStatusMsgSetCookie() {
            $cookie = $this->opStatusMsgGet();
            $cookie = $cookie['msg'];
            //$cookie = base64_encode($cookie);
            //$cookie = $this->encodeURIComponent( $cookie );

            $this->cookie_set(array(
                'name' => 'aiowaff_opStatusMsg',
                'value' => $cookie,
                // time() + 604800, // 1 hour = 3600 || 1 day = 86400 || 1 week = 604800 || '+30 days'
                'expire_sec' => strtotime(time() + 86400)
            ));
        }

        private function opStatusMsgSetFile() {
            $filename = $this->cfg['paths']['plugin_dir_path'] . 'cache/operation_status_msg.txt';

            $opStatusMsg = serialize($this->opStatusMsg);
            $this->u->writeCacheFile($filename, $opStatusMsg);
        }

        public function opStatusMsgGet($sep = '<br />', $from = 'code') {
            $opStatusMsg = $this->opStatusMsg;
            if ($from == 'session') {
                $opStatusMsg = unserialize($_SESSION['aiowaff_opStatusMsg']);
            } else if ($from == 'cookie') {
                $opStatusMsg = $_COOKIE['aiowaff_opStatusMsg'];
                return $opStatusMsg;
            } else if ($from == 'file') {
                $filename = $this->cfg['paths']['plugin_dir_path'] . 'cache/operation_status_msg.txt';

                if (!$this->u->verifyFileExists($filename)) {
                    $this->u->createFile($filename);
                }
                $opStatusMsg = $this->u->getCacheFile($filename);
                $opStatusMsg = unserialize($opStatusMsg);
            }

            $msg = (array) $opStatusMsg['msg'];
            $opStatusMsg['msg'] = implode($sep, $msg);
            if (isset($opStatusMsg['msg_header']) && !empty($opStatusMsg['msg_header'])) {
                $opStatusMsg['msg'] = $opStatusMsg['msg_header'] . $sep . $opStatusMsg['msg'];
            }
            return $opStatusMsg;
        }

        /**
         * Database tables
         */
        private function check_if_table_exists() {
            // assets asynchronous download
            $table_name_report_assets = $this->db->prefix . "amz_assets";
            if ($this->db->get_var("show tables like '$table_name_report_assets'") != $table_name_report_assets) {
                $sql = "CREATE TABLE " . $table_name_report_assets . " (
					`id` BIGINT(15) UNSIGNED NOT NULL AUTO_INCREMENT,
					`post_id` INT(11) NOT NULL,
					`asset` VARCHAR(225) NULL DEFAULT NULL,
					`thumb` VARCHAR(225) NULL DEFAULT NULL,
					`download_status` ENUM('new','success','inprogress','error') NULL DEFAULT 'new',
					`hash` VARCHAR(32) NULL DEFAULT NULL,
					`media_id` INT(11) NULL DEFAULT '0',
					`msg` TEXT NULL,
					`date_added` DATETIME NULL DEFAULT NULL,
					`date_download` DATETIME NULL DEFAULT NULL,
					PRIMARY KEY (`id`),
					INDEX `post_id` (`post_id`),
					INDEX `hash` (`hash`),
					INDEX `media_id` (`media_id`),
					INDEX `download_status` (`download_status`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
            }

            $table_name_report_products = $this->db->prefix . "amz_products";
            if ($this->db->get_var("show tables like '$table_name_report_products'") != $table_name_report_products) {
                $sql = "CREATE TABLE " . $table_name_report_products . " (
					`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					`post_id` INT(11) NOT NULL,
					`post_parent` INT(11) NULL DEFAULT '0',
					`type` ENUM('post','variation') NULL DEFAULT 'post',
					`title` TEXT NULL,
					`nb_assets` INT(4) NULL DEFAULT '0',
					`nb_assets_done` INT(4) NULL DEFAULT '0',
					`status` ENUM('new','success') NULL DEFAULT 'new',
					PRIMARY KEY (`post_id`, `id`),
					UNIQUE INDEX `post_id` (`post_id`),
					INDEX `post_parent` (`post_parent`),
					INDEX `type` (`type`),
					INDEX `nb_assets` (`nb_assets`),
					INDEX `nb_assets_done` (`nb_assets_done`),
					INDEX `id` (`id`),
					INDEX `status` (`status`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
            }

            // cross sell
            $table_name_cross_sell = $this->db->prefix . "amz_cross_sell";
            if ($this->db->get_var("show tables like '$table_name_cross_sell'") != $table_name_cross_sell) {
                $sql = "CREATE TABLE " . $table_name_cross_sell . " (
                    `ASIN` VARCHAR(10) NOT NULL,
                    `products` TEXT NULL,
                    `nr_products` INT(11) NULL DEFAULT NULL,
                    `add_date` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`ASIN`),
                    UNIQUE INDEX `ASIN` (`ASIN`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
            }

            // report logs
            $table_name_report = $this->db->prefix . "amz_report_log";
            if ($this->db->get_var("show tables like '$table_name_report'") != $table_name_report) {
                $sql = "CREATE TABLE " . $table_name_report . " (
                    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `log_id` VARCHAR(50) NULL DEFAULT NULL,
                    `log_action` VARCHAR(50) NULL DEFAULT NULL,
                    `desc` VARCHAR(255) NULL DEFAULT NULL,
                    `log_data_type` VARCHAR(50) NULL DEFAULT NULL,
                    `log_data` LONGTEXT NULL,
                    `source` TEXT NULL,
                    `date_add` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    INDEX `log_id` (`log_id`),
                    INDEX `log_action` (`log_action`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
            }
        }

        /**
         * Output the external product add to cart area.
         *
         * @access public
         * @subpackage	Product
         * @return void
         */
        public function woocommerce_external_add_to_cart() {
            echo '<script>jQuery(".single_add_to_cart_button").attr("target", "_blank");</script>';
        }

        public function check_amz_multiple_cart() {
            $amz_cross_sell = isset($_GET['amz_cross_sell']) ? $_GET['amz_cross_sell'] : false;
            if ($amz_cross_sell != false) {
                $asins = isset($_GET['asins']) ? $_GET['asins'] : '';

                if (trim($asins) != "") {
                    $asins = explode(',', $asins);
                    if (count($asins) > 0) {
                        $GLOBALS['aiowaff'] = $this;
                        // load the amazon webservices client class
                        require_once( $this->cfg['paths']['plugin_dir_path'] . '/lib/scripts/amazon/aaAmazonWS.class.php');

                        // create new amazon instance
                        $aaAmazonWS = new aaAmazonWS(
                                $this->amz_settings['AccessKeyID'], $this->amz_settings['SecretAccessKey'], $this->amz_settings['country'], $this->main_aff_id()
                        );
                        $aaAmazonWS->set_the_plugin($this);

                        $selectedItems = array();
                        foreach ($asins as $key => $value) {
                            $selectedItems[] = array(
                                'offerId' => $value,
                                'quantity' => 1
                            );
                        }

                        // debug only
                        //$aaAmazonWS->cartKill();

                        $cart = $aaAmazonWS->responseGroup('Cart')->cartThem($selectedItems);

                        $cart_items = isset($cart['CartItems']['CartItem']) ? $cart['CartItems']['CartItem'] : array();
                        if (count($cart_items) > 0) {
                            header('Location: ' . $cart['PurchaseURL'] . "&tag=" . $this->amz_settings['AffiliateId']);
                            exit();
                        }
                    }
                }
            }
        }

        public function frontpage() {
            global $product;

            if (isset($this->amz_settings['remove_gallery']) && $this->amz_settings['remove_gallery'] == 'no') {
                add_filter('the_content', array($this, 'remove_gallery'), 6);
            }

            // footer related!
            add_action('wp_footer', array(&$this, 'make_footer'), 1);

            // product price disclaimer for amazon & other extra details!
            add_action('wp_head', array($this, 'make_head'), 1);
            add_filter('woocommerce_get_price_html', array($this, 'amz_disclaimer_price_html'), 100, 2);
            add_filter('woocommerce_get_availability', array($this, 'amz_availability'), 100, 2);

            $p_type = ( isset($this->amz_settings['onsite_cart']) && 'no' == $this->amz_settings['onsite_cart'] ? 'external' : 'simple' );
            if ('external' == $p_type) {
                add_filter('woocommerce_product_single_add_to_cart_text', array($this, '_product_buy_text'));
            }

            if (!wp_script_is('aiowaff-frontend')) {
                wp_enqueue_script('aiowaff-frontend', $this->cfg['paths']['plugin_dir_url'] . '/lib/frontend/frontend.js', array('jquery'));
            }

            if (!wp_script_is('thickbox')) {
                wp_enqueue_script('thickbox', null, array('jquery'));
            }
            if (!wp_style_is('thickbox.css')) {
                wp_enqueue_style('thickbox.css', '/' . WPINC . '/js/thickbox/thickbox.css', null, '1.0');
            }

            // product buy url is the original amazon url!
            if ((!isset($this->amz_settings['product_buy_is_amazon_url']) || (isset($this->amz_settings['product_buy_is_amazon_url']) && $this->amz_settings['product_buy_is_amazon_url'] == 'yes')
                    ) && ( 'external' == $p_type )
            ) {
                add_action('aiowaff_footer', array($this, '_product_buy_url_make'), 30);
                add_action('woocommerce_after_shop_loop_item', array($this, '_product_buy_url_html'), 1);
                add_action('woocommerce_after_single_product', array($this, '_product_buy_url_html'), 1);
            }

            // product url redirect by prodid
            $redirect_prodid = (isset($_REQUEST['redirect_prodid']) && $_REQUEST['redirect_prodid']) != '' ? $_REQUEST['redirect_prodid'] : '';
            if (isset($redirect_prodid) && strlen($redirect_prodid) > 0) {
                $this->redirect_amazon($redirect_prodid);
            }

            $redirect_cart = (isset($_REQUEST['redirectCart']) && $_REQUEST['redirectCart']) != '' ? $_REQUEST['redirectCart'] : '';
            if (isset($redirect_cart) && $redirect_cart == 'true')
                $this->redirect_cart();
        }

        public function make_head() {
            $details = array('plugin_name' => 'aiowaff');

            ob_start();
            ?>
            <!-- start/ <?php echo $details['plugin_name']; ?> -->
            <style>
                .aiowaff-price-info {
                    font-size: 0.6em;
                    font-weight: normal;
                }

                .aiowaff-availability-icon {
                    background: transparent url("<?php bloginfo('siteurl'); ?>/wp-content/plugins/wooallinone/aa-framework/images/shipping.png") no-repeat top left;
                    padding-left: 30px;
                }

                .aiowaff-free-shipping {
                    color: #000;
                    font-size: 14px;
                }
                .aiowaff-free-shipping a.link {
                    text-decoration: none;
                }

                .aiowaff-coupon {
                }
                .aiowaff-coupon .aiowaff-coupon-title {
                    color: #d71321;
                    font-size: 18px;
                }
                .aiowaff-coupon .aiowaff-coupon-details {
                    color: #8c8c8c;
                    font-size: 14px;
                }
                .aiowaff-coupon .aiowaff-coupon-details a.link {
                    color: #db2a37;
                    text-decoration: none;
                }
                .aiowaff-coupon-container {
                    margin-top: 17px;
                }
                .aiowaff-coupon-container .aiowaff-coupon-clear {
                    clear: left;
                }
                .aiowaff-coupon-container .aiowaff-coupon-header {
                    float: left;
                    width: 100%;
                    color: #808080;
                    font-size: 12px;
                }
                #TB_ajaxContent .aiowaff-coupon-container .aiowaff-coupon-header p {
                    margin: 0px 0px 9px;
                    padding: 0;
                }
                .aiowaff-coupon-container .aiowaff-coupon-header > p {
                    float: left;
                }
                .aiowaff-coupon-container .aiowaff-coupon-header > a {
                    float: right;
                    color: #2b62a0;
                    font-weight: bold;
                }
                .aiowaff-coupon-container .aiowaff-coupon-summary {
                    background-color: #fff;
                    border: 1px solid #eaeaea;
                    border-radius: 4px;
                    padding: 6px 8px;
                    display: block;
                }
                .aiowaff-coupon-container .aiowaff-coupon-summary-inner {
                    display: block;
                    width: 100%;
                    /*-webkit-transform-style: preserve-3d;
                    -moz-transform-style: preserve-3d;
                    transform-style: preserve-3d;*/
                }
                .aiowaff-coupon-container .aiowaff-coupon-summary-inner-left {
                    display: inline-block;
                    width: 53px;
                    padding: 10px 5px;
                    color: #7d9f22;
                    line-height: 1.3em;
                    border: 2px dashed #699000;
                    border-radius: 10px;
                    /*box-shadow: 0 0 0 4px #f5f8ee, 2px 1px 6px 4px rgba(10, 10, 0, 0.5);*/
                    text-shadow: -1px -1px #c3d399;
                    text-align: center;
                }
                .aiowaff-coupon-container .aiowaff-coupon-summary-inner-right {
                    display: inline-block;
                    margin-left: 15px;
                    font-size: 12px;
                    color: #363636;
                    width: 80%;
                    /*position: relative;
                    top: 50%;
                    -webkit-transform: translateY(-50%);
                    -ms-transform: translateY(-50%);
                    transform: translateY(-50%);*/
                }
                #TB_ajaxContent .aiowaff-coupon-container .aiowaff-coupon-summary-inner-right p {
                    margin: 0px;
                    padding: 0px;
                }
                .aiowaff-coupon-container .aiowaff-coupon-desc {
                    font-size: 12px;
                    color: #808080;
                    margin-top: 24px;
                }
                .aiowaff-coupon-container .aiowaff-coupon-desc strong {
                    color: #444444;
                    margin-bottom: 12px;
                }
                .aiowaff-coupon-container .aiowaff-coupon-desc ol,
                .aiowaff-coupon-container .aiowaff-coupon-desc ul  {
                    font-size: 11px;
                    color: #5d5d5d;
                }
                .aiowaff-coupon-container .aiowaff-coupon-desc ul,
                .aiowaff-coupon-container .aiowaff-coupon-desc ol li,
                .aiowaff-coupon-container .aiowaff-coupon-desc ul li {
                    margin-left: 9px;
                }
            </style>
            <!-- end/ <?php echo $details['plugin_name']; ?> -->
            <?php
            $contents = ob_get_clean();
            echo $contents;
        }

        public function make_footer() {
            global $wp_query;

            $details = array('plugin_name' => 'aiowaff');

            // woocommerce-tabs amazon fix
            echo PHP_EOL . "<!-- start/ " . ($details['plugin_name']) . " woocommerce-tabs amazon fix -->" . PHP_EOL;
            echo '<script type="text/javascript">' . PHP_EOL;
            echo "jQuery('.woocommerce-tabs #tab-description .aplus p img[height=1]').css({ 'height': '1px' });" . PHP_EOL;
            echo '</script>' . PHP_EOL;
            echo "<!-- end/ " . ($details['plugin_name']) . " woocommerce-tabs amazon fix -->" . PHP_EOL . PHP_EOL;

            $current_amazon_aff = $this->_get_current_amazon_aff(); // ONLY FOR AMAZON
            $current_amazon_aff = json_encode($current_amazon_aff);
            $current_amazon_aff = htmlentities($current_amazon_aff);
            echo '<span id="aiowaff_current_aff" class="display: none;" data-current_aff="' . $current_amazon_aff . '" /></span>';

            if (!has_action('aiowaff_footer'))
                return true;

            $details = array('plugin_name' => 'aiowaff');

            $__wp_query = null;

            if (!$wp_query->is_main_query()) {
                $__wp_query = $wp_query;
                wp_reset_query();
            }

            echo PHP_EOL . "<!-- start/ " . ($details['plugin_name']) . " -->" . PHP_EOL;

            do_action('aiowaff_footer');

            echo "<!-- end/ " . ($details['plugin_name']) . " -->" . PHP_EOL . PHP_EOL;

            if (!empty($__wp_query)) {
                $GLOBALS['wp_query'] = $__wp_query;
                unset($__wp_query);
            }

            return true;
        }

        public function _product_buy_url_make() {
            ob_start();
            ?>
            <!-- start/ <?php echo $details['plugin_name']; ?> aiowaff product buy url -->
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    var $products = $('ul.products').find('.product_type_external'),
                            $prod_info = $('ul.products').find('.aiowaff-product-buy-url');

                    if ($products.length > 0 && $prod_info.length > 0) { // products list page
                        $products.each(function (i) {
                            var $this = $(this),
                                    product_id = $this.data('product_id');

                            var $current = $prod_info.filter(function (i) {
                                return $(this).data('product_id') == product_id;
                            });
                            if ($current.length > 0) {
                                $this.prop('href', $current.data('url'));
                            }
                        });
                    }

                    var $prod_container = $('div[id^="product-"]');
                    if ($prod_container.length > 0) { // product details page

                        var $this = $prod_container,
                                $prod_info = $this.next('.aiowaff-product-buy-url');

                        if ($prod_info.length > 0) {
                            var prod_id = $prod_info.data('product_id'),
                                    prod_url = $prod_info.data('url');

                            var is_link = $this.find('a.single_add_to_cart_button'),
                                    is_button = $this.find('button.single_add_to_cart_button');

                            if (is_link.length) {
                                is_link.prop('href', prod_url);
                            } else if (is_button.length) {

                                var btn_text = is_button.text(),
                                        new_a = '<a class="single_add_to_cart_button button alt" rel="nofollow" href="' + prod_url + '" target="_blank">' + btn_text + '</a>';
                                is_button.after(new_a);
                                is_button.remove();
                            }
                        }
                    }
                });
            </script>
            <!-- end/ <?php echo $details['plugin_name']; ?> waiowaff product buy url -->
            <?php
            $contents = ob_get_clean();
            echo $contents;
        }

        public function _product_buy_url_html() {
            global $product;
            if (isset($product->id)) {
                $product_id = $product->id;
                $product_buy_url = $this->_product_buy_url($product_id);

                if (!empty($product_buy_url)) {
                    echo '<span data-url="' . $product_buy_url . '" data-product_id="' . $product_id . '" class="aiowaff-product-buy-url" style="display: none;"></span>';
                }
            }
        }

        public function _product_buy_url($product_id, $redirect_asin = '') {
            if (empty($redirect_asin)) {
                // get product provider type
                $redirect_asin = $this->get_post_meta($product_id, '_amzASIN', true);
            }
            if (empty($redirect_asin))
                return '';

            $provider = $this->prodid_get_provider($this->prodid_get_provider_alias($redirect_asin));
            $asin = $this->prodid_get_asin($redirect_asin);

            $this->cur_provider = $provider;

            if (in_array($provider, array('amazon', 'alibaba', 'envato', 'ebay'))) {
                //$user_country = $this->get_country_perip_external();
            }

            $link = '';
            // PER PROVIDER
            if ('amazon' == $provider) {

                /* $get_user_location = wp_remote_get( 'http://api.hostip.info/country.php?ip=' . $_SERVER["REMOTE_ADDR"] );
                  if( isset($get_user_location->errors) ) {
                  $main_aff_site = $this->main_aff_site();
                  $user_country = $this->amzForUser( strtoupper(str_replace(".", '', $main_aff_site)) );
                  }else{
                  $user_country = $this->amzForUser($get_user_location['body']);
                  } */
                $user_country = $this->get_country_perip_external();

                $link = '//www.amazon' . ( $user_country['website'] ) . '/gp/product/' . ( $asin ) . '/?tag=' . ( $user_country['affID'] ) . '';
            } else if (in_array($provider, array('alibaba', 'envato', 'ebay'))) {

                $link = get_post_meta($product_id, '_aiowaff_product_url', true);
                //var_dump('<pre>', $product, $link, '</pre>'); die('debug...');

                if ('envato' == $provider) {
                    $affid = isset($this->amz_settings['envato_AffId']) ? $this->amz_settings['envato_AffId'] : '';
                    $link .= '?ref=' . $affid;
                } else if ('ebay' == $provider) {
                    $link_ = $this->get_ws_object('ebay')->get_product_link(array(
                        'prod_id' => $asin,
                        'prod_link' => $link,
                    ));
                    if (!empty($link_)) {
                        $link = $link_;
                    }
                }
            }

            return $link;
        }

        public function amz_disclaimer_price_html($price, $product) {
            $post_id = isset($product->id) ? $product->id : 0;
            if ($post_id <= 0)
                return $price;

            if (!is_product() || !$product->get_price() || !$this->verify_product_isamazon($post_id))
                return $price;

            // $price_update_date = get_post_meta($post_id, "_price_update_date", true);
            $price_update_date = get_post_meta($post_id, "_amzaff_sync_last_date", true);
            if (empty($price_update_date)) { // product not synced at least once yet! - bug solved 2015-11-03
                $price_update_date = strtotime($product->post->post_date);
            }
            if (!empty($price_update_date)) {
                //$price_update_date = date('F j, Y, g:i a', $price_update_date);
                $price_update_date = date_i18n(get_option('date_format') . ', ' . get_option('time_format'), $price_update_date);

                //$gmt_offset = get_option( 'gmt_offset' );
                //$price_update_date = gmdate( get_option( 'date_format' ) .', '. get_option( 'time_format' ), ($price_update_date + ($gmt_offset * 3600)) );
            }

            //<ins><span class="amount">£26.99</span></ins>
            $text = !empty($price_update_date) ? '&nbsp;<em class="aiowaff-price-info">' . sprintf(__('(as of %s)', $this->localizationName), $price_update_date) . '</em>' : '';
            $text .= $this->amz_product_free_shipping($post_id);

            $reg_price = get_post_meta(get_the_ID(), '_regular_price');
            $s_price = get_post_meta(get_the_ID(), '_price');
            if ($reg_price != $s_price) {
                return str_replace('</ins>', '</ins>' . $text, $price);
            } else {
                //return str_replace( '</span>', '</span>' . $text, $price );
                return $this->u->str_replace_last('</span>', '</span>' . $text, $price);
            }

            /*
              if ( substr_count($price, '</ins>') > 0 ) {
              $ret = str_replace( '</ins>', '</ins>' . $text, $price );
              } else {
              $ret = str_replace( '</span>', '</span>' . $text, $price );
              }
              return $ret;
             */
        }

        public function amz_availability($availability, $product) {
            //change text "In Stock' to 'available'
            //if ( $_product->is_in_stock() )
            //	$availability['availability'] = __('available', 'woocommerce');
            //change text "Out of Stock' to 'sold out'
            //if ( !$_product->is_in_stock() )
            //	$availability['availability'] = __('sold out', 'woocommerce');

            $post_id = isset($product->id) ? $product->id : 0;
            if ($post_id > 0) {
                $meta = get_post_meta($post_id, '_amzaff_availability', true);
                if (!empty($meta)) {
                    $availability['availability'] = /* '<img src="shipping.png" width="24" height="18" alt="Shipping availability" />' */'' . $meta;
                    $availability['class'] = 'aiowaff-availability-icon';
                }
            }
            return $availability;
        }

        public function amz_product_free_shipping($post_id) {
            $contents = '';
            $current_amazon_aff = array();

            $current_amazon_aff = $this->_get_current_amazon_aff(); // ONLY FOR AMAZON

            $_tag = '';
            $_affid = $current_amazon_aff['user_country']['key'];
            if (isset($this->amz_settings['AffiliateID']["$_affid"])) {
                $_tag = '&tag=' . $this->amz_settings['AffiliateID']["$_affid"];
            }

            // free shipping
            if (!isset($this->amz_settings['frontend_show_free_shipping']) || (isset($this->amz_settings['frontend_show_free_shipping']) && $this->amz_settings['frontend_show_free_shipping'] == 'yes')) {
                $meta = get_post_meta($post_id, '_amzaff_isSuperSaverShipping', true);
                if (!empty($meta)) {

                    $link = '//www.amazon' . $current_amazon_aff['user_country']['website'] . '/gp/help/customer/display.html/ref=mk_sss_dp_1?ie=UTF8&amp;pop-up=1&amp;nodeId=527692' . $_tag;

                    ob_start();
                    ?>
                    <span class="aiowaff-free-shipping">
                        &amp; <b><?php _e('FREE Shipping', $this->localizationName); ?></b>.
                        <a class="link" onclick="return aiowaff.popup(this.href, 'AmazonHelp', 'width=550,height=550,resizable=1,scrollbars=1,toolbar=0,status=0');" target="AmazonHelp" href="<?php echo $link; ?>"><?php _e('Details', $this->localizationName); ?></a>
                    </span>
                    <?php
                    $contents .= ob_get_clean();
                }
            }

            // coupon
            if (!isset($this->amz_settings['frontend_show_coupon_text']) || (isset($this->amz_settings['frontend_show_coupon_text']) && $this->amz_settings['frontend_show_coupon_text'] == 'yes')) {
                $meta_amzResp = get_post_meta($post_id, '_amzaff_amzRespPrice');
                if (!empty($meta_amzResp) && isset($meta_amzResp['Offers'], $meta_amzResp['Offers']['Offer'], $meta_amzResp['Offers']['Offer']['Promotions'], $meta_amzResp['Offers']['Offer']['Promotions']['Promotion']['Summary']) && !empty($meta_amzResp['Offers']['Offer']['Promotions']['Promotion']['Summary'])) {

                    $post = get_post($post_id);
                    $promotion = $meta_amzResp['Offers']['Offer']['Promotions']['Promotion']['Summary'];
                    $coupon = array(
                        'asin' => $this->get_post_meta($post_id, '_amzASIN', true),
                        'prod_title' => (string) $post->post_title,
                        'title' => isset($promotion['BenefitDescription']) ? $promotion['BenefitDescription'] : '',
                        'details' => sprintf(__('Your coupon will be applied at amazon checkout. %s', $this->localizationName), '<a name="' . __('COUPON DETAILS', $this->localizationName) . '" href="#TB_inline?width=500&height=700&inlineId=aiowaff-coupon-popup" class="thickbox link">' . __('Details', $this->localizationName) . '</a>'),
                        'popup_content' => isset($promotion['TermsAndConditions']) ? $promotion['TermsAndConditions'] : '',
                        'link' => '',
                        'link_more' => '',
                    );
                    if (isset($promotion['PromotionId'])) {
                        $coupon = array_merge($coupon, array(
                            'link' => 'http://www.amazon' . $current_amazon_aff['user_country']['website'] . '/gp/coupon/c/' . $promotion['PromotionId'] . '?ie=UTF8&email=&redirectASIN=' . $coupon['asin'] . $_tag,
                            'link_more' => 'http://www.amazon' . $current_amazon_aff['user_country']['website'] . '/gp/coupons/most-popular?ref=vp_c_' . $promotion['PromotionId'] . '_tcs' . $_tag,
                        ));
                    }

                    // php query class
                    require_once( $this->cfg['paths']['scripts_dir_path'] . '/php-query/phpQuery.php' );
                    if (trim($coupon['popup_content']) != "") {
                        if (!empty($this->the_plugin->charset))
                            $doc = phpQuery::newDocument($coupon['popup_content'], $this->the_plugin->charset);
                        else
                            $doc = phpQuery::newDocument($coupon['popup_content']);

                        $foundLinks = $doc->find("a");
                        if ((int) $foundLinks->size() > 0) {
                            foreach ($foundLinks as $foundLink) {
                                $foundLink = pq($foundLink);
                                $foundLink_href = trim($foundLink->attr('href'));
                                $foundLink_href .= $_tag;
                                $foundLink->attr('href', $foundLink_href);
                            }
                            $coupon['popup_content'] = $doc->html();
                        }
                    }

                    ob_start();
                    ?>
                    <div class="aiowaff-coupon">
                        <div class="aiowaff-coupon-title"><?php echo $coupon['title']; ?></div>
                        <div class="aiowaff-coupon-details"><?php echo $coupon['details']; ?></div>
                    </div>
                    <div id="aiowaff-coupon-popup" style="display: none;">
                        <div class="aiowaff-coupon-container">
                            <div class="aiowaff-coupon-header">
                                <p><?php _e('Coupons available for this offer', $this->localizationName); ?></p>
                                <a href="<?php echo $coupon['link_more']; ?>" target="_blank"><?php _e('View more coupons', $this->localizationName); ?></a>
                            </div>
                            <div class="aiowaff-coupon-clear"></div>
                            <div class="aiowaff-coupon-summary">
                                <div class="aiowaff-coupon-summary-inner">
                                    <div class="aiowaff-coupon-summary-inner-left">
                                        <a href="<?php echo $coupon['link']; ?>" target="_blank"><?php _e('Your coupon', $this->localizationName); ?></a>
                                    </div>
                                    <div class="aiowaff-coupon-summary-inner-right">
                                        <div><?php echo $coupon['prod_title']; ?></div>
                                        <div><?php echo $coupon['title']; ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="aiowaff-coupon-desc">
                    <?php echo $coupon['popup_content']; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                    $contents .= ob_get_clean();
                }
            }

            return $contents;
        }

        public function _get_current_amazon_aff() {
            $__ = $this->cur_provider;
            $this->cur_provider = 'amazon';

            $user_country = $this->get_country_perip_external();

            $ret = array(
                //'main_aff_site' 			=> $main_aff_site,
                'user_country' => $user_country,
            );

            $this->cur_provider = $__;

            return $ret;
        }

        public function _product_buy_text($text) {
            $gtext = isset($this->amz_settings['product_buy_text']) && !empty($this->amz_settings['product_buy_text']) ? $this->amz_settings['product_buy_text'] : '';
            if (empty($gtext))
                return $text;

            global $product;
            if (isset($product->id)) {
                $product_id = $product->id;

                // original text for non amazon/external products!
                if (!$this->verify_product_isamazon($product))
                    return $text;

                $_button_text = get_post_meta($product_id, '_button_text', true);
                if (!empty($_button_text)) {
                    return $_button_text;
                }
                return $gtext;
            }
            return $text;
        }

        public function get_amazon_country_site($country, $withPrefixPoint = false) {
            $provider = $this->cur_provider;

            if (isset($country) && !empty($country)) {

                if ('amazon' == $provider) {

                    $config = array('main_aff_id' => $country);

                    $ret = '';
                    if ($config['main_aff_id'] == 'com') {
                        $ret = '.com';
                    } elseif ($config['main_aff_id'] == 'ca') {
                        $ret = '.ca';
                    } elseif ($config['main_aff_id'] == 'cn') {
                        $ret = '.cn';
                    } elseif ($config['main_aff_id'] == 'de') {
                        $ret = '.de';
                    } elseif ($config['main_aff_id'] == 'in') {
                        $ret = '.in';
                    } elseif ($config['main_aff_id'] == 'it') {
                        $ret = '.it';
                    } elseif ($config['main_aff_id'] == 'es') {
                        $ret = '.es';
                    } elseif ($config['main_aff_id'] == 'fr') {
                        $ret = '.fr';
                    } elseif ($config['main_aff_id'] == 'uk') {
                        $ret = '.co.uk';
                    } elseif ($config['main_aff_id'] == 'jp') {
                        $ret = '.co.jp';
                    } elseif ($config['main_aff_id'] == 'mx') {
                        $ret = '.com.mx';
                    } elseif ($config['main_aff_id'] == 'br') {
                        $ret = '.com.br';
                    }

                    if (!empty($ret) && !$withPrefixPoint)
                        $ret = substr($ret, 1);
                    return $ret;
                }
                else if ('ebay' == $provider) {
                    return $country; // same as $config['main_aff_id']
                }
            }
            return '';
        }

        public function __amz_default_affid($config) {
            $provider = $this->cur_provider;
            $prefix = 'ebay' == $provider ? 'ebay_' : '';

            $config = (array) $config;

            // get all amazon settings options
            $main_aff_id = 'com';
            $country = 'com';
            if ('ebay' == $provider) {
                $main_aff_id = 'EBAY-US';
                $country = $main_aff_id;
            }

            // already have a Valid main affiliate id!
            if (isset($config[$prefix . 'main_aff_id'], $config[$prefix . 'AffiliateID'], $config[$prefix . 'AffiliateID'][$config[$prefix . 'main_aff_id']]) && !empty($config[$prefix . 'main_aff_id']) && !empty($config[$prefix . 'AffiliateID'][$config[$prefix . 'main_aff_id']])) {

                return $config;
            }

            // get key for first found not empty affiliate id! 
            if (isset($config[$prefix . 'AffiliateID']) && !empty($config[$prefix . 'AffiliateID']) && is_array($config[$prefix . 'AffiliateID'])) {
                foreach ($config[$prefix . 'AffiliateID'] as $key => $val) {
                    if (!empty($val)) {
                        $main_aff_id = $key;
                        $country = $this->get_amazon_country_site($main_aff_id);
                        break;
                    }
                }
            }

            $config[$prefix . 'main_aff_id'] = $main_aff_id;
            $config[$prefix . 'country'] = $country;

            return $config;
        }

        public function main_aff_id() {
            $provider = $this->cur_provider;
            $prefix = 'ebay' == $provider ? 'ebay_' : '';

            $config = $this->amz_settings;
            $config = $this->__amz_default_affid($config);
            $config = (array) $config;

            if (isset($config[$prefix . 'main_aff_id'], $config[$prefix . 'AffiliateID'], $config[$prefix . 'AffiliateID'][$config[$prefix . 'main_aff_id']]) && !empty($config[$prefix . 'main_aff_id']) && !empty($config[$prefix . 'AffiliateID'][$config[$prefix . 'main_aff_id']])) {

                return $config[$prefix . 'AffiliateID'][$config[$prefix . 'main_aff_id']];
            }
            return 'amazon' == $provider ? 'com' : 'EBAY-US';
        }

        public function main_aff_site() {
            $provider = $this->cur_provider;
            $prefix = 'ebay' == $provider ? 'ebay_' : '';

            $config = $this->amz_settings;
            $config = $this->__amz_default_affid($config);
            $config = (array) $config;

            if (isset($config[$prefix . 'main_aff_id'], $config[$prefix . 'AffiliateID'], $config[$prefix . 'AffiliateID'][$config[$prefix . 'main_aff_id']]) && !empty($config[$prefix . 'main_aff_id']) && !empty($config[$prefix . 'AffiliateID'][$config[$prefix . 'main_aff_id']])) {

                if ('amazon' == $provider) {

                    if ($config['main_aff_id'] == 'com') {
                        return '.com';
                    } elseif ($config['main_aff_id'] == 'ca') {
                        return '.ca';
                    } elseif ($config['main_aff_id'] == 'cn') {
                        return '.cn';
                    } elseif ($config['main_aff_id'] == 'de') {
                        return '.de';
                    } elseif ($config['main_aff_id'] == 'in') {
                        return '.in';
                    } elseif ($config['main_aff_id'] == 'it') {
                        return '.it';
                    } elseif ($config['main_aff_id'] == 'es') {
                        return '.es';
                    } elseif ($config['main_aff_id'] == 'fr') {
                        return '.fr';
                    } elseif ($config['main_aff_id'] == 'uk') {
                        return '.co.uk';
                    } elseif ($config['main_aff_id'] == 'jp') {
                        return '.co.jp';
                    } elseif ($config['main_aff_id'] == 'mx') {
                        return '.com.mx';
                    } elseif ($config['main_aff_id'] == 'br') {
                        return '.com.br';
                    } else {
                        return '.com';
                    }
                } else if ('ebay' == $provider) {
                    return $config[$prefix . 'main_aff_id'];
                }
            }

            if ('amazon' == $provider) {
                return '.com';
            } else if ('ebay' == $provider) {
                return 'EBAY-US';
            }
        }

        private function amzForUser($userCountry = 'US') {
            $provider = $this->cur_provider;
            $prefix = 'ebay' == $provider ? 'ebay_' : '';

            if (in_array($provider, array('alibaba', 'envato', 'ebay'))) {
                return array('key' => $userCountry);
            }

            $config = $this->amz_settings;
            $config = $this->__amz_default_affid($config);
            $config = (array) $config;

            $affIds = (array) isset($config[$prefix . 'AffiliateID']) ? $config[$prefix . 'AffiliateID'] : array();
            $main_aff_id = $this->main_aff_id();
            $main_aff_site = $this->main_aff_site();

            if ('amazon' == $provider) {

                if ($userCountry == 'US') {
                    return array(
                        'key' => 'com',
                        'website' => isset($affIds['com']) && (trim($affIds['com']) != "") ? '.com' : $main_aff_site,
                        'affID' => isset($affIds['com']) && (trim($affIds['com']) != "") ? $affIds['com'] : $main_aff_id
                    );
                } elseif ($userCountry == 'CA') {
                    return array(
                        'key' => 'ca',
                        'website' => isset($affIds['ca']) && (trim($affIds['ca']) != "") ? '.ca' : $main_aff_site,
                        'affID' => isset($affIds['ca']) && (trim($affIds['ca']) != "") ? $affIds['ca'] : $main_aff_id
                    );
                } elseif ($userCountry == 'FR') {
                    return array(
                        'key' => 'fr',
                        'website' => isset($affIds['fr']) && (trim($affIds['fr']) != "") ? '.fr' : $main_aff_site,
                        'affID' => isset($affIds['fr']) && (trim($affIds['fr']) != "") ? $affIds['fr'] : $main_aff_id
                    );
                } elseif ($userCountry == 'CN') {
                    return array(
                        'key' => 'cn',
                        'website' => isset($affIds['cn']) && (trim($affIds['cn']) != "") ? '.cn' : $main_aff_site,
                        'affID' => isset($affIds['cn']) && (trim($affIds['cn']) != "") ? $affIds['cn'] : $main_aff_id
                    );
                } elseif ($userCountry == 'DE') {
                    return array(
                        'key' => 'de',
                        'website' => isset($affIds['de']) && (trim($affIds['de']) != "") ? '.de' : $main_aff_site,
                        'affID' => isset($affIds['de']) && (trim($affIds['de']) != "") ? $affIds['de'] : $main_aff_id
                    );
                } elseif ($userCountry == 'IN') {
                    return array(
                        'key' => 'in',
                        'website' => isset($affIds['in']) && (trim($affIds['in']) != "") ? '.in' : $main_aff_site,
                        'affID' => isset($affIds['in']) && (trim($affIds['in']) != "") ? $affIds['in'] : $main_aff_id
                    );
                } elseif ($userCountry == 'IT') {
                    return array(
                        'key' => 'it',
                        'website' => isset($affIds['it']) && (trim($affIds['it']) != "") ? '.it' : $main_aff_site,
                        'affID' => isset($affIds['it']) && (trim($affIds['it']) != "") ? $affIds['it'] : $main_aff_id
                    );
                } elseif ($userCountry == 'JP') {
                    return array(
                        'key' => 'jp',
                        'website' => isset($affIds['jp']) && (trim($affIds['jp']) != "") ? '.co.jp' : $main_aff_site,
                        'affID' => isset($affIds['jp']) && (trim($affIds['jp']) != "") ? $affIds['jp'] : $main_aff_id
                    );
                } elseif ($userCountry == 'ES') {
                    return array(
                        'key' => 'es',
                        'website' => isset($affIds['es']) && (trim($affIds['es']) != "") ? '.es' : $main_aff_site,
                        'affID' => isset($affIds['es']) && (trim($affIds['es']) != "") ? $affIds['es'] : $main_aff_id
                    );
                } elseif ($userCountry == 'GB') {
                    return array(
                        'key' => 'uk',
                        'website' => isset($affIds['uk']) && (trim($affIds['uk']) != "") ? '.co.uk' : $main_aff_site,
                        'affID' => isset($affIds['uk']) && (trim($affIds['uk']) != "") ? $affIds['uk'] : $main_aff_id
                    );
                } elseif ($userCountry == 'MX') {
                    return array(
                        'key' => 'mx',
                        'website' => isset($affIds['mx']) && (trim($affIds['mx']) != "") ? '.com.mx' : $main_aff_site,
                        'affID' => isset($affIds['mx']) && (trim($affIds['mx']) != "") ? $affIds['mx'] : $main_aff_id
                    );
                } elseif ($userCountry == 'BR') {
                    return array(
                        'key' => 'br',
                        'website' => isset($affIds['br']) && (trim($affIds['br']) != "") ? '.com.br' : $main_aff_site,
                        'affID' => isset($affIds['br']) && (trim($affIds['br']) != "") ? $affIds['br'] : $main_aff_id
                    );
                } else {

                    $website = $config["main_aff_id"];
                    if ($config["main_aff_id"] == 'uk')
                        $website = 'co.uk';
                    if ($config["main_aff_id"] == 'jp')
                        $website = 'co.jp';
                    if ($config["main_aff_id"] == 'mx')
                        $website = 'com.mx';
                    if ($config["main_aff_id"] == 'br')
                        $website = 'com.br';

                    return array(
                        'key' => $config["main_aff_id"],
                        'website' => "." . $website,
                        'affID' => $main_aff_id
                    );
                }
            }
        }

        /**
         * Output the external product add to cart area.
         *
         * @access public
         * @subpackage	Product
         * @return void
         */
        public function woocommerce_external_checkout() {
            if (is_checkout() == true) {
                $this->redirect_cart();
            }
        }

        private function redirect_cart() {
            global $woocommerce;

            if (isset($woocommerce->cart->cart_contents_count) && (int) $woocommerce->cart->cart_contents_count > 0) {
                $amz_products = array();
                $original_product_count = $woocommerce->cart->cart_contents_count;
                foreach ($woocommerce->cart->cart_contents as $key => $value) {

                    $prod_id = isset($value['variation_id']) && (int) $value['variation_id'] > 0 ? $value['variation_id'] : $value['product_id'];
                    $amzASIN = $this->get_post_meta($prod_id, '_amzASIN', true);

                    // check if is a valid ASIN code 
                    if (isset($amzASIN) && strlen($amzASIN) == 10) {
                        $amz_products[] = array(
                            'asin' => $amzASIN,
                            'quantity' => $value['quantity'],
                            'key' => $key
                        );
                    }
                }

                // redirect back to checkout page
                if (count($amz_products) > 0) {

                    /* $get_user_location = wp_remote_get( 'http://api.hostip.info/country.php?ip=' . $_SERVER["REMOTE_ADDR"] ); 
                      if( isset($get_user_location->errors) ) {
                      $main_aff_site = $this->main_aff_site();
                      $user_country = $this->amzForUser( strtoupper(str_replace(".", '', $main_aff_site)) );
                      }else{
                      $user_country = $this->amzForUser($get_user_location['body']);
                      } */
                    $user_country = $this->get_country_perip_external();

                    $config = $this->amz_settings;

                    if (isset($config["redirect_checkout_msg"]) && trim($config["redirect_checkout_msg"]) != "") {
                        echo '<img src="' . ( $this->cfg['paths']['freamwork_dir_url'] . 'images/checkout_loading.gif' ) . '" style="margin: 10px auto;">';
                        echo "<h3>" . ( str_replace('{amazon_website}', 'www.amazon' . $user_country['website'], $config["redirect_checkout_msg"]) ) . "</h3>";
                    }

                    $checkout_type = isset($config['checkout_type']) && $config['checkout_type'] == '_blank' ? '_blank' : '_self';
                    ?>
                    <form target="<?php echo $checkout_type; ?>" id="amzRedirect" method="POST" action="//www.amazon<?php echo $user_country['website']; ?>/gp/aws/cart/add.html">
                        <input type="hidden" name="AssociateTag" value="<?php echo $user_country['affID']; ?>"/> 
                        <input type="hidden" name="SubscriptionId" value="<?php echo $config['AccessKeyID']; ?>"/> 
                    <?php
                    $cc = 1;
                    foreach ($amz_products as $key => $value) {
                        ?>		
                            <input type="hidden" name="ASIN.<?php echo $cc; ?>" value="<?php echo $value['asin']; ?>"/>
                            <input type="hidden" name="Quantity.<?php echo $cc; ?>" value="<?php echo $value['quantity']; ?>"/>
                        <?php
                        $cc++;
                    }

                    $redirect_in = isset($config['redirect_time']) && (int) $config['redirect_time'] > 0 ? ((int) $config['redirect_time'] * 1000) : 1;
                    ?>		 
                    </form>
                    <?php
                    ///* debug by uncomment
                    ?>
                    <script type="text/javascript">
                        setTimeout(function () {
                            document.getElementById("amzRedirect").submit();
                    <?php
                    if ((int) $woocommerce->cart->cart_contents_count > 0 && $checkout_type == '_blank') {
                        ?>
                                setTimeout(function () {
                                    window.location.reload(true);
                                }, 1);
                        <?php
                    }
                    ?>
                        }, <?php echo $redirect_in; ?>);
                    </script>
                    <?php
                    // remove amazon products from client cart
                    foreach ($amz_products as $key => $value) {

                        if (isset($value['asin']) && trim($value['asin']) != "") {
                            $post_id = $this->get_post_id_by_meta_key_and_value('_amzASIN', $value['asin']);

                            $redirect_to_amz = (int) get_post_meta($post_id, '_amzaff_redirect_to_amazon', true);
                            update_post_meta($post_id, '_amzaff_redirect_to_amazon', (int) ($redirect_to_amz + 1));

                            $redirect_to_amz2 = (int) get_post_meta($post_id, '_amzaff_redirect_to_amazon_prev', true);
                            update_post_meta($post_id, '_amzaff_redirect_to_amazon_prev', (int) ($redirect_to_amz2 + 1));

                            $woocommerce->cart->set_quantity($value['key'], 0);
                        }
                    }
                    //*/
                    exit();
                }
            }
        }

        private function redirect_amazon($redirect_asin = '') {
            //if ( empty($redirect_asin) ) {
            //	// get product provider type
            //	$redirect_asin = $this->get_post_meta($product_id, '_amzASIN', true);
            //}
            if (empty($redirect_asin))
                return '';

            $provider = $this->prodid_get_provider($this->prodid_get_provider_alias($redirect_asin));
            $asin = $this->prodid_get_asin($redirect_asin);

            $product = $this->get_product_by_wsid($redirect_asin);
            if (empty($product) || !isset($product['ID']))
                return '';

            $this->cur_provider = $provider;

            $config = $this->amz_settings; {
                //if( isset($redirect_asin) && trim($redirect_asin) != "" ){
                {
                    //$post_id = $this->get_post_id_by_meta_key_and_value('_amzASIN', $redirect_asin);
                    $post_id = $product['ID'];

                    $redirect_to_amz = (int) get_post_meta($post_id, '_amzaff_redirect_to_amazon', true);
                    update_post_meta($post_id, '_amzaff_redirect_to_amazon', (int) ($redirect_to_amz + 1));

                    $redirect_to_amz2 = (int) get_post_meta($post_id, '_amzaff_redirect_to_amazon_prev', true);
                    update_post_meta($post_id, '_amzaff_redirect_to_amazon_prev', (int) ($redirect_to_amz2 + 1));
                }
            }

            if (in_array($provider, array('amazon', 'alibaba', 'envato', 'ebay'))) {
                //$user_country = $this->get_country_perip_external();
            }

            // PER PROVIDER
            if ('amazon' == $provider) {

                /* $get_user_location = wp_remote_get( 'http://api.hostip.info/country.php?ip=' . $_SERVER["REMOTE_ADDR"] );
                  if( isset($get_user_location->errors) ) {
                  $main_aff_site = $this->main_aff_site();
                  $user_country = $this->amzForUser( strtoupper(str_replace(".", '', $main_aff_site)) );
                  }else{
                  $user_country = $this->amzForUser($get_user_location['body']);
                  } */
                $user_country = $this->get_country_perip_external();

                if (isset($config["90day_cookie"]) && $config["90day_cookie"] == 'yes') {
                    ?>
                    <form id="amzRedirect" method="GET" action="//www.amazon<?php echo $user_country['website']; ?>/gp/aws/cart/add.html">
                        <input type="hidden" name="AssociateTag" value="<?php echo $user_country['affID']; ?>"/> 
                        <input type="hidden" name="SubscriptionId" value="<?php echo $config['AccessKeyID']; ?>"/> 
                        <input type="hidden" name="ASIN.1" value="<?php echo $asin; ?>"/>
                        <input type="hidden" name="Quantity.1" value="1"/> 
                    </form> 
                    <?php
                    die('
						<script>
						setTimeout(function() {
						  	document.getElementById("amzRedirect").submit();
						}, 1);
						</script>
					');
                } else {
                    $link = 'http://www.amazon' . ( $user_country['website'] ) . '/gp/product/' . ( $asin ) . '/?tag=' . ( $user_country['affID'] ) . '';

                    die('<meta http-equiv="refresh" content="0; url=' . ( $link ) . '">');
                    /*
                      <!--form id="amzRedirect" method="GET" action="<?php echo $link;?>">
                      </form-->
                     */
                }
            } else if (in_array($provider, array('alibaba', 'envato', 'ebay'))) {

                $product_id = $product['ID'];

                // http://s.click.aliexpress.com/e/M3qYbAVb
                $link = get_post_meta($product_id, '_aiowaff_product_url', true);
                //var_dump('<pre>', $product, $link, '</pre>'); die('debug...');

                if ('envato' == $provider) {
                    $affid = isset($this->amz_settings['envato_AffId']) ? $this->amz_settings['envato_AffId'] : '';
                    $link .= '?ref=' . $affid;
                } else if ('ebay' == $provider) {
                    $link_ = $this->get_ws_object('ebay')->get_product_link(array(
                        'prod_id' => $asin,
                        'prod_link' => $link,
                    ));
                    if (!empty($link_)) {
                        $link = $link_;
                    }
                }

                if (empty($link)) {
                    die('no product url defined!');
                }

                die('<meta http-equiv="refresh" content="0; url=' . ( $link ) . '">');
            }
        }

        public function get_post_id_by_meta_key_and_value($key, $value) {
            global $wpdb;

            $_key = $key;
            if ($_key == '_amzASIN')
                $key = '_aiowaff_prodid';

            $meta = $wpdb->get_results($wpdb->prepare("SELECT * FROM `" . $wpdb->postmeta . "` WHERE meta_key=%s AND meta_value=%s", $key, $value));

            if (is_array($meta) && !empty($meta) && isset($meta[0])) {
                $meta = $meta[0];
            }
            if (is_object($meta)) {
                return $meta->post_id;
            } else {
                return false;
            }
        }

        /**
         * Some Plugin Status Info
         */
        public function plugin_redirect() {
            if (get_option('aiowaff_do_activation_redirect', false)) {

                $pullOutArray = @json_decode(file_get_contents($this->cfg['paths']['plugin_dir_path'] . 'modules/setup_backup/default-setup.json'), true);
                foreach ($pullOutArray as $key => $value) {

                    // prepare the data for DB update
                    //$saveIntoDb = $value != "true" ? serialize( $value ) : "true";
                    $saveIntoDb = !in_array($value, array('true', 'false')) && !is_bool($value) ? serialize($value) : $value;
                    // Use the function update_option() to update a named option/value pair to the options database table. The option_name value is escaped with $wpdb->escape before the INSERT statement.

                    if ('aiowaff_amazon' == $key) {
                        $saveIntoDb = $this->amazon_config_with_default($value);
                        $saveIntoDb = serialize($saveIntoDb);
                    }

                    update_option($key, $saveIntoDb);
                }

                /*
                  $cross_sell_table_name = $this->db->prefix . "amz_cross_sell";
                  if ($this->db->get_var("show tables like '$cross_sell_table_name'") != $cross_sell_table_name) {

                  $sql = "CREATE TABLE " . $cross_sell_table_name . " (
                  `ASIN` VARCHAR(10) NOT NULL,
                  `products` TEXT NULL,
                  `nr_products` INT(11) NULL DEFAULT NULL,
                  `add_date` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`ASIN`),
                  UNIQUE INDEX `ASIN` (`ASIN`)
                  ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

                  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

                  dbDelta($sql);
                  }
                 */

                delete_option('aiowaff_do_activation_redirect');
                wp_redirect(get_admin_url() . 'admin.php?page=aiowaff#!/amazon');
            }
        }

        public function amazon_config_with_default($default) {
            $dbs = @maybe_unserialize(get_option($this->alias . '_amazon'));

            // default mandatory keys & affiliate id
            if (isset($dbs['AccessKeyID']) && empty($dbs['AccessKeyID'])) {
                unset($dbs['AccessKeyID']);
            }
            if (isset($dbs['SecretAccessKey']) && empty($dbs['SecretAccessKey'])) {
                unset($dbs['SecretAccessKey']);
            }
            if (isset($dbs['AffiliateID'])) {
                if (empty($dbs['AffiliateID']) || !is_array($dbs['AffiliateID'])) {
                    unset($dbs['AffiliateID']);
                } else {
                    $found = false;
                    foreach ($dbs['AffiliateID'] as $key => $val) {
                        if (!empty($val)) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        unset($dbs['AffiliateID']);
                    }
                }
            }

            $new = array_replace_recursive($default, $dbs);
            //var_dump('<pre>', $new, '</pre>'); die('debug...'); 
            return $new;
        }

        public function activate() {
            add_option('aiowaff_do_activation_redirect', true);
            add_option('aiowaff_depedencies_is_valid', true);
            add_option('aiowaff_depedencies_do_activation_redirect', true);
        }

        public function get_plugin_status() {
            return $this->v->isReg(get_option('aiowaff_hash'));
        }

        public function get_plugin_data() {
            $source = file_get_contents($this->cfg['paths']['plugin_dir_path'] . "/plugin.php");
            $tokens = token_get_all($source);
            $data = array();
            if (trim($tokens[1][1]) != "") {
                $__ = explode("\n", $tokens[1][1]);
                foreach ($__ as $key => $value) {
                    $___ = explode(": ", $value);
                    if (count($___) == 2) {
                        $data[trim(strtolower(str_replace(" ", '_', $___[0])))] = trim($___[1]);
                    }
                }
            }

            $this->details = $data;
            return $data;
        }

        public function update_plugin_notifier_menu() {
            if (function_exists('simplexml_load_string')) { // Stop if simplexml_load_string funtion isn't available
                // Get the latest remote XML file on our server
                $xml = $this->get_latest_plugin_version(self::NOTIFIER_CACHE_INTERVAL);

                $plugin_data = get_plugin_data($this->cfg['paths']['plugin_dir_path'] . 'plugin.php'); // Read plugin current version from the main plugin file

                if (isset($plugin_data) && count($plugin_data) > 0) {
                    if ((string) $xml->latest > (string) $plugin_data['Version']) { // Compare current plugin version with the remote XML version
                        add_dashboard_page(
                                $plugin_data['Name'] . ' Plugin Updates', 'Amazon <span class="update-plugins count-1"><span class="update-count">New Updates</span></span>', 'administrator', $this->alias . '-plugin-update-notifier', array($this, 'update_notifier')
                        );
                    }
                }
            }
        }

        public function update_notifier() {
            $xml = $this->get_latest_plugin_version(self::NOTIFIER_CACHE_INTERVAL);
            $plugin_data = get_plugin_data($this->cfg['paths']['plugin_dir_path'] . 'plugin.php'); // Read plugin current version from the main plugin file
            ?>

            <style>
                .update-nag { display: none; }
                #instructions {max-width: 670px;}
                h3.title {margin: 30px 0 0 0; padding: 30px 0 0 0; border-top: 1px solid #ddd;}
            </style>


            <?php
        }

        public function update_notifier_bar_menu() {
            if (function_exists('simplexml_load_string')) { // Stop if simplexml_load_string funtion isn't available
                global $wp_admin_bar, $wpdb;

                // Don't display notification in admin bar if it's disabled or the current user isn't an administrator
                if (!is_super_admin() || !is_admin_bar_showing())
                    return;

                // Get the latest remote XML file on our server
                // The time interval for the remote XML cache in the database (21600 seconds = 6 hours)
                $xml = $this->get_latest_plugin_version(self::NOTIFIER_CACHE_INTERVAL);

                if (is_admin())
                    $plugin_data = get_plugin_data($this->cfg['paths']['plugin_dir_path'] . 'plugin.php'); // Read plugin current version from the main plugin file

                if (isset($plugin_data) && count($plugin_data) > 0) {

                    if ((string) $xml->latest > (string) $plugin_data['Version']) { // Compare current plugin version with the remote XML version
                        $wp_admin_bar->add_menu(
                                array(
                                    'id' => 'plugin_update_notifier',
                                    'title' => '<span>' . ( $plugin_data['Name'] ) . ' <span id="ab-updates">New Updates</span></span>',
                                    'href' => get_admin_url() . 'index.php?page=' . ( $this->alias ) . '-plugin-update-notifier'
                                )
                        );
                    }
                }
            }
        }

        public function get_latest_plugin_version($interval) {
            $base = array();
            $notifier_file_url = 'http://cc.Ali.com/apps-versions/index.php?app=' . $this->alias;
            $db_cache_field = $this->alias . '_notifier-cache';
            $db_cache_field_last_updated = $this->alias . '_notifier-cache-last-updated';
            $last = get_option($db_cache_field_last_updated);
            $now = time();

            // check the cache
            if (!$last || (( $now - $last ) > $interval)) {
                // cache doesn't exist, or is old, so refresh it
                if (function_exists('curl_init')) { // if cURL is available, use it...
                    $ch = curl_init($notifier_file_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    $cache = curl_exec($ch);
                    curl_close($ch);
                } else {
                    // ...if not, use the common file_get_contents()
                    $cache = file_get_contents($notifier_file_url);
                }

                if ($cache) {
                    // we got good results
                    update_option($db_cache_field, $cache);
                    update_option($db_cache_field_last_updated, time());
                }

                // read from the cache file
                $notifier_data = get_option($db_cache_field);
            } else {
                // cache file is fresh enough, so read from it
                $notifier_data = get_option($db_cache_field);
            }

            // Let's see if the $xml data was returned as we expected it to.
            // If it didn't, use the default 1.0 as the latest version so that we don't have problems when the remote server hosting the XML file is down
            if (strpos((string) $notifier_data, '<notifier>') === false) {
                $notifier_data = '<?xml version="1.0" encoding="UTF-8"?><notifier><latest>1.0</latest><changelog></changelog></notifier>';
            }

            // Load the remote XML data into a variable and return it
            $xml = simplexml_load_string($notifier_data);

            return $xml;
        }

        // add admin js init
        public function createInstanceFreamwork() {
            echo "<script type='text/javascript'>jQuery(document).ready(function ($) {
					/*var aiowaff = new aiowaff;
					aiowaff.init();*/
				});</script>";
        }

        /**
         * Create plugin init
         *
         *
         * @no-return
         */
        public function initThePlugin() {
            // If the user can manage options, let the fun begin!
            if (is_admin() && current_user_can('manage_options')) {
                if (is_admin() && (!isset($_REQUEST['page']) || strpos($_REQUEST['page'], 'codestyling') === false)) {
                    // Adds actions to hook in the required css and javascript
                    add_action("admin_print_styles", array(&$this, 'admin_load_styles'));
                    add_action("admin_print_scripts", array(&$this, 'admin_load_scripts'));
                }

                // create dashboard page
                add_action('admin_menu', array(&$this, 'createDashboardPage'));

                // get fatal errors
                // get fatal errors


                $section = isset($_REQUEST['section']) ? $_REQUEST['section'] : '';
                $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
                if ($page == $this->alias || strpos($page, $this->alias) == true && trim($section) != "") {
                    add_action('init', array(&$this, 'go_to_section'));
                }
            }

            // keep the plugin modules into storage
            if (!isset($_REQUEST['page']) || strpos($_REQUEST['page'], 'codestyling') === false) {
                $this->load_modules();
            }
        }

        public function go_to_section() {
            $section = isset($_REQUEST['section']) ? $_REQUEST['section'] : '';
            if (trim($section) != "") {
                header('Location: ' . sprintf(admin_url('admin.php?page=%s#!/%s'), $this->alias, $section));
                exit();
            }
        }

        private function update_products_type($what = 'all') {
            $config = $this->amz_settings;

            $products = array();

            $keyy = '_amzASIN';
            $_keyy = $keyy;
            if ($_keyy == '_amzASIN')
                $keyy = '_aiowaff_prodid';

            // update all products 
            if ($what == 'all') {
                $args = array(
                    'post_type' => 'product',
                    'fields' => 'ids',
                    'meta_key' => $keyy,
                    'posts_per_page' => '-1',
                    'meta_query' => array(
                        array(
                            'key' => $keyy,
                            'value' => array(''),
                            'compare' => 'NOT IN'
                        )
                    )
                );
                $query = new WP_Query($args);
                //var_dump('<pre>',$query,'</pre>'); die; 
                if (count($query->posts) > 0) {
                    foreach ($query->posts as $key => $value) {
                        $products[] = $value;
                    }
                }
            }
            // custom product
            else {
                $products[] = $what;
            }

            if (count($products) > 0) {
                $__p_type = ((isset($config['onsite_cart']) && $config['onsite_cart'] == "no") ? 'external' : 'simple');
                foreach ($products as $key => $value) {
                    $p_type = $__p_type;
                    if ($p_type == 'simple') {
                        $args = array(
                            'post_type' => 'product_variation',
                            'posts_per_page' => '5',
                            'post_parent' => $value
                        );

                        $query_variations = new WP_Query($args);

                        if ($query_variations->post_count > 0) {
                            $p_type = 'variable';
                        }
                    }

                    update_option('_transient_wc_product_type_' . $value, $p_type);
                    //update_option('_transient_woocommerce_product_type_' . $value, 'external'); // doesn't seem to be used in woocommerce new version! /note on: 2015-07-14
                    wp_set_object_terms($value, $p_type, 'product_type');
                }
            }
        }

        public function fixPlusParseStr($input = array(), $type = 'string') {
            if ($type == 'array') {
                if (count($input) > 0) {
                    $ret_arr = array();
                    foreach ($input as $key => $value) {
                        $ret_arr[$key] = str_replace("###", '+', $value);
                    }

                    return $ret_arr;
                }

                return $input;
            } else {
                return str_replace('+', '###', $input);
            }
        }

        // saving the options
        public function save_options() {
            // remove action from request
            unset($_REQUEST['action']);

            // unserialize the request options
            $serializedData = $this->fixPlusParseStr(urldecode($_REQUEST['options']));

            $savingOptionsArr = array();

            parse_str($serializedData, $savingOptionsArr);

            $savingOptionsArr = $this->fixPlusParseStr($savingOptionsArr, 'array');

            // create save_id and remote the box_id from array
            $save_id = $savingOptionsArr['box_id'];
            unset($savingOptionsArr['box_id']);

            // Verify that correct nonce was used with time limit.
            if (!wp_verify_nonce($savingOptionsArr['box_nonce'], $save_id . '-nonce'))
                die('Busted!');
            unset($savingOptionsArr['box_nonce']);

            // remove the white space before asin
            if ($save_id == 'aiowaff_amazon') {
                $_savingOptionsArr = $savingOptionsArr;
                $savingOptionsArr = array();
                foreach ($_savingOptionsArr as $key => $value) {
                    if (!is_array($value)) {
                        // Check for and remove mistake in string after copy/paste keys 
                        if ($key == 'AccessKeyID' || $key == 'SecretAccessKey') {
                            if (stristr($value, 'AWSAccessKeyId=') !== false)
                                $value = str_ireplace('AWSAccessKeyId=', '', $value);
                            if (stristr($value, 'AWSSecretKey=') !== false)
                                $value = str_ireplace('AWSSecretKey=', '', $value);
                        }
                        $savingOptionsArr[$key] = trim($value);
                    }else {
                        $savingOptionsArr[$key] = $value;
                    }
                }
            }

            /* if ( $save_id == 'aiowaff_report' ) {
              $__old_saving = get_option('aiowaff_report', array());
              $__old_saving = maybe_unserialize(maybe_unserialize($__old_saving));
              $__old_saving = (array) $__old_saving;

              $savingOptionsArr["report"] = $__old_saving["report"];
              } */

            // prepare the data for DB update
            $saveIntoDb = serialize($savingOptionsArr);

            // Use the function update_option() to update a named option/value pair to the options database table. The option_name value is escaped with $wpdb->escape before the INSERT statement.
            update_option($save_id, $saveIntoDb);

            //$this->amz_settings = $this->the_plugin->getAllSettings('array', 'amazon');
            $this->amz_settings = @unserialize(get_option($this->alias . '_amazon'));
            $this->build_amz_settings();

            // check for onsite cart option 
            if ($save_id == $this->alias . '_amazon') {
                $this->update_products_type('all');
            }

            die(json_encode(array(
                'status' => 'ok',
                'html' => 'Options updated successfully'
            )));
        }

        // saving the options
        public function install_default_options() {
            // remove action from request
            unset($_REQUEST['action']);

            // unserialize the request options
            $serializedData = urldecode($_REQUEST['options']);

            $savingOptionsArr = array();
            parse_str($serializedData, $savingOptionsArr);

            // fix for setup
            if ($savingOptionsArr['box_id'] == 'aiowaff_setup_box') {
                $serializedData = preg_replace('/box_id=aiowaff_setup_box&box_nonce=[\w]*&install_box=/', '', $serializedData);
                $savingOptionsArr['install_box'] = $serializedData;
                $savingOptionsArr['install_box'] = str_replace("\\'", "\\\\'", $savingOptionsArr['install_box']);
            }

            // create save_id and remote the box_id from array
            $save_id = $savingOptionsArr['box_id'];
            unset($savingOptionsArr['box_id']);

            // Verify that correct nonce was used with time limit.
            if (!wp_verify_nonce($savingOptionsArr['box_nonce'], $save_id . '-nonce'))
                die('Busted!');
            unset($savingOptionsArr['box_nonce']);

            // default sql - tables & tables data!
            require_once( $this->cfg['paths']['plugin_dir_path'] . 'modules/setup_backup/default-sql.php');
            if ($save_id != 'aiowaff_setup_box') {
                $savingOptionsArr['install_box'] = str_replace('\"', '"', $savingOptionsArr['install_box']);
            }

            // convert to array
            $savingOptionsArr['install_box'] = str_replace('#!#', '&', $savingOptionsArr['install_box']);
            $savingOptionsArr['install_box'] = str_replace("'", "\'", $savingOptionsArr['install_box']);
            $pullOutArray = json_decode($savingOptionsArr['install_box'], true);
            if (count($pullOutArray) == 0) {
                die(json_encode(array(
                    'status' => 'error',
                    'html' => "Invalid install default json string, can't parse it!"
                )));
            } else {

                foreach ($pullOutArray as $key => $value) {

                    // prepare the data for DB update
                    //$saveIntoDb = ( $value );
                    //if( $saveIntoDb === true ){
                    //	$saveIntoDb = 'true';
                    //} else if( $saveIntoDb === false ){
                    //	$saveIntoDb = 'false';
                    //}
                    // prepare the data for DB update
                    $saveIntoDb = $value != "true" ? serialize($value) : $value;

                    // Use the function update_option() to update a named option/value pair to the options database table. The option_name value is escaped with $wpdb->escape before the INSERT statement.
                    update_option($key, $saveIntoDb);
                }

                // update is_installed value to true 
                update_option($this->alias . "_is_installed", 'true');

                die(json_encode(array(
                    'status' => 'ok',
                    'html' => 'Install default successful'
                )));
            }
        }

        public function options_validate($input) {
            //var_dump('<pre>', $input  , '</pre>'); echo __FILE__ . ":" . __LINE__;die . PHP_EOL;
        }

        public function module_change_status($resp = 'ajax') {
            // remove action from request
            unset($_REQUEST['action']);

            // update into DB the new status
            $db_alias = $this->alias . '_module_' . $_REQUEST['module'];
            update_option($db_alias, $_REQUEST['the_status']);

            if (!isset($resp) || empty($resp) || $resp == 'ajax') {
                die(json_encode(array(
                    'status' => 'ok'
                )));
            }
        }

        public function module_bulk_change_status() {
            global $wpdb; // this is how you get access to the database

            $request = array(
                'id' => isset($_REQUEST['id']) && !empty($_REQUEST['id']) ? trim($_REQUEST['id']) : ''
            );

            if (trim($request['id']) != '') {
                $__rq2 = array();
                $__rq = explode(',', $request['id']);
                if (is_array($__rq) && count($__rq) > 0) {
                    foreach ($__rq as $k => $v) {
                        $__rq2[] = (string) $v;
                    }
                } else {
                    $__rq2[] = $__rq;
                }
                $request['id'] = implode(',', $__rq2);
            }

            if (is_array($__rq2) && count($__rq2) > 0) {
                foreach ($__rq2 as $kk => $vv) {
                    $_REQUEST['module'] = $vv;
                    $this->module_change_status('non-ajax');
                }

                die(json_encode(array(
                    'status' => 'valid',
                    'msg' => 'valid module change status Bulk'
                )));
            }

            die(json_encode(array(
                'status' => 'invalid',
                'msg' => 'invalid module change status Bulk'
            )));
        }

        // loading the requested section
        public function load_section() {
            $request = array(
                'section' => isset($_REQUEST['section']) ? strip_tags($_REQUEST['section']) : false
            );

            if (isset($request['section']) && $request['section'] == 'insane_mode') {
                die(json_encode(array(
                    'status' => 'redirect',
                    'url' => admin_url('admin.php?page=aiowaff_insane_import')
                )));
            }

            // get module if isset
            if (!in_array($request['section'], $this->cfg['activate_modules']))
                die(json_encode(array('status' => 'err', 'msg' => 'invalid section want to load!')));

            $tryed_module = $this->cfg['modules'][$request['section']];
            if (isset($tryed_module) && count($tryed_module) > 0) {
                // Turn on output buffering
                ob_start();

                $opt_file_path = $tryed_module['folder_path'] . 'options.php';
                if (is_file($opt_file_path)) {
                    require_once( $opt_file_path );
                }
                $options = ob_get_clean(); //copy current buffer contents into $message variable and delete current output buffer

                if (trim($options) != "") {
                    $options = json_decode($options, true);

                    // Derive the current path and load up aaInterfaceTemplates
                    $plugin_path = dirname(__FILE__) . '/';
                    if (class_exists('aaInterfaceTemplates') != true) {
                        require_once($plugin_path . 'settings-template.class.php');

                        // Initalize the your aaInterfaceTemplates
                        $aaInterfaceTemplates = new aaInterfaceTemplates($this->cfg);

                        // then build the html, and return it as string
                        $html = $aaInterfaceTemplates->bildThePage($options, $this->alias, $tryed_module);

                        // fix some URI
                        $html = str_replace('{plugin_folder_uri}', $tryed_module['folder_uri'], $html);

                        if (trim($html) != "") {
                            $headline = '';
                            if (isset($tryed_module[$request['section']]['in_dashboard']['icon'])) {
                                $headline .= '<img src="' . ($tryed_module['folder_uri'] . $tryed_module[$request['section']]['in_dashboard']['icon'] ) . '" class="aiowaff-headline-icon">';
                            }
                            $headline .= $tryed_module[$request['section']]['menu']['title'] . "<span class='aiowaff-section-info'>" . ( $tryed_module[$request['section']]['description'] ) . "</span>";

                            $has_help = isset($tryed_module[$request['section']]['help']) ? true : false;
                            if ($has_help === true) {

                                $help_type = isset($tryed_module[$request['section']]['help']['type']) && $tryed_module[$request['section']]['help']['type'] ? 'remote' : 'local';
                                if ($help_type == 'remote') {
                                    $headline .= '';
                                }
                            }

                            $headline .= '';

                            die(json_encode(array(
                                'status' => 'ok',
                                'headline' => $headline,
                                'html' => $html
                            )));
                        }

                        die(json_encode(array('status' => 'err', 'msg' => 'invalid html formatter!')));
                    }
                }
            }
        }

        public function fatal_errors() {
            // print errors
            if (is_wp_error($this->errors)) {
                $_errors = $this->errors->get_error_messages('fatal');

                if (count($_errors) > 0) {
                    foreach ($_errors as $key => $value) {
                        echo '<div class="error"> <p>' . ( $value ) . '</p> </div>';
                    }
                }
            }
        }

        /**
         * Builds the config parameters
         *
         * @param string $function
         * @param array	$params
         *
         * @return array
         */
        protected function buildConfigParams($type, array $params) {
            // check if array exist
            if (isset($this->cfg[$type])) {
                $params = array_merge($this->cfg[$type], $params);
            }

            // now merge the arrays
            $this->cfg = array_merge(
                    $this->cfg, array($type => array_merge($params))
            );
        }

        /*
         * admin_load_styles()
         *
         * Loads admin-facing CSS
         */

        public function admin_get_frm_style() {
            $css = array();

            if (isset($this->cfg['freamwork-css-files']) && is_array($this->cfg['freamwork-css-files']) && !empty($this->cfg['freamwork-css-files'])
            ) {

                foreach ($this->cfg['freamwork-css-files'] as $key => $value) {
                    if (is_file($this->cfg['paths']['freamwork_dir_path'] . $value)) {

                        $cssId = $this->alias . '-' . $key;
                        $css["$cssId"] = $this->cfg['paths']['freamwork_dir_path'] . $value;
                        // wp_enqueue_style( $this->alias . '-' . $key, $this->cfg['paths']['freamwork_dir_url'] . $value );
                    } else {
                        $this->errors->add('warning', __('Invalid CSS path to file: <strong>' . $this->cfg['paths']['freamwork_dir_path'] . $value . '</strong>. Call in:' . __FILE__ . ":" . __LINE__, $this->localizationName));
                    }
                }
            }
            return $css;
        }

        public function admin_load_styles() {
            global $wp_scripts;
            $protocol = is_ssl() ? 'https' : 'http';

            $javascript = $this->admin_get_scripts();

            // font awesome from CDN
            wp_enqueue_style($this->alias . '-font-awesome', $protocol . '://maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css');

            $style_url = $this->cfg['paths']['freamwork_dir_url'] . 'load-styles.php';
            if (is_file($this->cfg['paths']['freamwork_dir_path'] . 'load-styles.css')) {
                $style_url = str_replace(".php", '.css', $style_url);
            }
            wp_enqueue_style('aiowaff-aa-framework-styles', $style_url);

            if (in_array('jquery-ui-core', $javascript)) {
                $ui = $wp_scripts->query('jquery-ui-core');
                if ($ui) {
                    $uiBase = "http://code.jquery.com/ui/{$ui->ver}/themes/smoothness";
                    wp_register_style('jquery-ui-core', "$uiBase/jquery-ui.css", FALSE, $ui->ver);
                    wp_enqueue_style('jquery-ui-core');
                }
            }
            if (in_array('thickbox', $javascript))
                wp_enqueue_style('thickbox');
        }

        /*
         * admin_load_scripts()
         *
         * Loads admin-facing CSS
         */

        public function admin_get_scripts() {
            $javascript = array();

            $current_url = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : '';
            $current_url = explode("wp-admin/", $current_url);
            if (count($current_url) > 1) {
                $current_url = "/wp-admin/" . $current_url[1];
            } else {
                $current_url = "/wp-admin/" . $current_url[0];
            }

            if (isset($this->cfg['modules']) && is_array($this->cfg['modules']) && !empty($this->cfg['modules'])
            ) {
                foreach ($this->cfg['modules'] as $alias => $module) {

                    if (isset($module[$alias]["load_in"]['backend']) && is_array($module[$alias]["load_in"]['backend']) && count($module[$alias]["load_in"]['backend']) > 0) {
                        // search into module for current module base on request uri
                        foreach ($module[$alias]["load_in"]['backend'] as $page) {

                            $expPregQuote = ( is_array($page) ? false : true );
                            if (is_array($page))
                                $page = $page[0];

                            $delimiterFound = strpos($page, '#');
                            $page = substr($page, 0, ($delimiterFound !== false && $delimiterFound > 0 ? $delimiterFound : strlen($page)));
                            $urlfound = preg_match("%^/wp-admin/" . ($expPregQuote ? preg_quote($page) : $page) . "%", $current_url);

                            if (
                            // $current_url == '/wp-admin/' . $page
                                    ( ( $page == '@all' ) || ( $current_url == '/wp-admin/admin.php?page=aiowaff' ) || (!empty($page) && $urlfound > 0 ) ) && isset($module[$alias]['javascript'])) {

                                $javascript = array_merge($javascript, $module[$alias]['javascript']);
                            }
                        }
                    }
                }
            } // end if

            $this->jsFiles = $javascript;
            return $javascript;
        }

        public function admin_load_scripts() {
            // very defaults scripts (in wordpress defaults)
            wp_enqueue_script('jquery');

            $javascript = $this->admin_get_scripts();

            if (count($javascript) > 0) {
                $javascript = @array_unique($javascript);

                if (in_array('jquery-ui-core', $javascript))
                    wp_enqueue_script('jquery-ui-core');
                if (in_array('jquery-ui-widget', $javascript))
                    wp_enqueue_script('jquery-ui-widget');
                if (in_array('jquery-ui-mouse', $javascript))
                    wp_enqueue_script('jquery-ui-mouse');
                if (in_array('jquery-ui-accordion', $javascript))
                    wp_enqueue_script('jquery-ui-accordion');
                if (in_array('jquery-ui-autocomplete', $javascript))
                    wp_enqueue_script('jquery-ui-autocomplete');
                if (in_array('jquery-ui-slider', $javascript))
                    wp_enqueue_script('jquery-ui-slider');
                if (in_array('jquery-ui-tabs', $javascript))
                    wp_enqueue_script('jquery-ui-tabs');
                if (in_array('jquery-ui-sortable', $javascript))
                    wp_enqueue_script('jquery-ui-sortable');
                if (in_array('jquery-ui-draggable', $javascript))
                    wp_enqueue_script('jquery-ui-draggable');
                if (in_array('jquery-ui-droppable', $javascript))
                    wp_enqueue_script('jquery-ui-droppable');
                if (in_array('jquery-ui-datepicker', $javascript))
                    wp_enqueue_script('jquery-ui-datepicker');
                if (in_array('jquery-ui-resize', $javascript))
                    wp_enqueue_script('jquery-ui-resize');
                if (in_array('jquery-ui-dialog', $javascript))
                    wp_enqueue_script('jquery-ui-dialog');
                if (in_array('jquery-ui-button', $javascript))
                    wp_enqueue_script('jquery-ui-button');

                if (in_array('thickbox', $javascript))
                    wp_enqueue_script('thickbox');

                // date & time picker
                if (!wp_script_is('jquery-timepicker')) {
                    if (in_array('jquery-timepicker', $javascript))
                        wp_enqueue_script('jquery-timepicker', $this->cfg['paths']['freamwork_dir_url'] . 'js/jquery.timepicker.v1.1.1.min.js', array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker', 'jquery-ui-slider'));
                }
            }

            if (count($this->cfg['freamwork-js-files']) > 0) {
                foreach ($this->cfg['freamwork-js-files'] as $key => $value) {

                    if (is_file($this->cfg['paths']['freamwork_dir_path'] . $value)) {
                        if (in_array($key, $javascript))
                            wp_enqueue_script($this->alias . '-' . $key, $this->cfg['paths']['freamwork_dir_url'] . $value);
                    } else {
                        $this->errors->add('warning', __('Invalid JS path to file: <strong>' . $this->cfg['paths']['freamwork_dir_path'] . $value . '</strong> . Call in:' . __FILE__ . ":" . __LINE__, $this->localizationName));
                    }
                }
            }
        }

        /*
         * Builds out the options panel.
         *
         * If we were using the Settings API as it was likely intended we would use
         * do_settings_sections here. But as we don't want the settings wrapped in a table,
         * we'll call our own custom wplanner_fields. See options-interface.php
         * for specifics on how each individual field is generated.
         *
         * Nonces are provided using the settings_fields()
         *
         * @param array $params
         * @param array $options (fields)
         *
         */

        public function createDashboardPage() {
            add_menu_page(
                    __('All WP Store', $this->localizationName), __('All WP Store', $this->localizationName), 'manage_options', $this->alias, array(&$this, 'manage_options_template'), $this->cfg['paths']['plugin_dir_url'] . ''
            );

            add_submenu_page(
                    $this->alias, $this->alias . " " . __('Plugin configuration', $this->localizationName), __('Config', $this->localizationName), 'manage_options', $this->alias . "&section=amazon", array($this, 'manage_options_template')
            );

            //add_submenu_page(
            //$this->alias,
            //$this->alias . " " . __('Advanced Search', $this->localizationName),
            // __('Amazon Search', $this->localizationName),
            // 'manage_options',
            // $this->alias . "&section=advanced_search",
            // array( $this, 'manage_options_template')
            // );
            //add_submenu_page(
            //	$this->alias,
            //$this->alias . " " . __('Import Insane Mode', $this->localizationName),
            //  __('Insane Mode Import', $this->localizationName),
            //   'manage_options',
            //   $this->alias . "&section=insane_mode",
            //   array( $this, 'insane_import_redirect')
            // );
            //add_submenu_page(
            //$this->alias,
            //$this->alias . " " . __('CSV bulk products import', $this->localizationName),
            //__('CSV import', $this->localizationName),
            //'manage_options',
            //$this->alias . "&section=csv_products_import",
            // array( $this, 'manage_options_template')
            // );
        }

        public function manage_options_template() {
            // Derive the current path and load up aaInterfaceTemplates
            $plugin_path = dirname(__FILE__) . '/';
            if (class_exists('aaInterfaceTemplates') != true) {
                require_once($plugin_path . 'settings-template.class.php');

                // Initalize the your aaInterfaceTemplates
                $aaInterfaceTemplates = new aaInterfaceTemplates($this->cfg);

                // try to init the interface
                $aaInterfaceTemplates->printBaseInterface();
            }
        }

        public function insane_import_redirect() {
            echo __FILE__ . ":" . __LINE__;
            die . PHP_EOL;
        }

        /**
         * Getter function, plugin config
         *
         * @return array
         */
        public function getCfg() {
            return $this->cfg;
        }

        /**
         * Getter function, plugin all settings
         *
         * @params $returnType
         * @return array
         */
        public function getAllSettings($returnType = 'array', $only_box = '', $this_call = false) {
            if ($this_call == true) {
                //var_dump('<pre>',$returnType, $only_box,'</pre>');  
            }
            $allSettingsQuery = "SELECT * FROM " . $this->db->prefix . "options where 1=1 and option_name REGEXP '" . ( $this->alias) . "_([a-z])';";
            if (trim($only_box) != "") {
                $allSettingsQuery = "SELECT * FROM " . $this->db->prefix . "options where 1=1 and option_name = '" . ( $this->alias . '_' . $only_box) . "' LIMIT 1;";
            }
            $results = $this->db->get_results($allSettingsQuery, ARRAY_A);

            // prepare the return
            $return = array();
            if (count($results) > 0) {
                foreach ($results as $key => $value) {

                    if ($value['option_value'] == 'true') {
                        $return[$value['option_name']] = true;
                    } else {
                        //$return[$value['option_name']] = @unserialize(@unserialize($value['option_value']));
                        $return[$value['option_name']] = maybe_unserialize($value['option_value']);
                        $return[$value['option_name']] = maybe_unserialize($return[$value['option_name']]);
                    }
                }
            }

            if (trim($only_box) != "" && isset($return[$this->alias . '_' . $only_box])) {
                $return = $return[$this->alias . '_' . $only_box];
            }

            if ($returnType == 'serialize') {
                return serialize($return);
            } else if ($returnType == 'array') {
                return $return;
            } else if ($returnType == 'json') {
                return json_encode($return);
            }

            return false;
        }

        /**
         * Getter function, all products
         *
         * @params $returnType
         * @return array
         */
        public function getAllProductsMeta($returnType = 'array', $key = '', $withPrefix = true, $ws = 'all') {
            $_key = $key;
            if ($_key == '_amzASIN')
                $key = '_aiowaff_prodid';
            $q_ws = '';
            if ($ws != 'all') {
                $q_ws = " AND a.meta_value regexp '^$ws-' ";
            }

            // SELECT * FROM " . $this->db->prefix . "postmeta where 1=1 and meta_key='" . ( $key ) . "'
            $allSettingsQuery = "SELECT a.meta_value FROM " . $this->db->prefix . "postmeta AS a LEFT OUTER JOIN " . $this->db->prefix . "posts AS b ON a.post_id=b.ID WHERE 1=1 AND a.meta_key='" . ( $key ) . "' $q_ws AND !ISNULL(b.ID) AND b.post_type IN ('product', 'product_variation')";
            $results = $this->db->get_results($allSettingsQuery, ARRAY_A);

            //"SELECT * FROM wp_postmeta where 1=1 and meta_key='_amzASIN'";
            //$deleteAllAmzMeta = "DELETE FROM " . $this->db->prefix . "postmeta where 1=1 and meta_key='" . ( $key ) . "'";
            //$delAmzMetaNow = $this->db->query( 
            //					$this->db->prepare( $deleteAllAmzMeta )
            //				);
            //echo $delAmzMetaNow;
            // prepare the return
            $return = array();
            if (count($results) > 0) {
                foreach ($results as $key => $value) {
                    if (trim($value['meta_value']) != "") {
                        $return[] = $value['meta_value'];
                    }
                }
            }

            if (!$withPrefix && ($_key == '_amzASIN')) {
                $wslist = $this->get_ws_prefixes();
                foreach ($wslist as $wsprefix) {
                    foreach ($return as $k => $v) {
                        $return["$k"] = str_replace($wsprefix . '-', '', $return["$k"]);
                    }
                }
            }

            if ($returnType == 'serialize') {
                return serialize($return);
            } else if ($returnType == 'text') {
                return implode("\n", $return);
            } else if ($returnType == 'array') {
                return $return;
            } else if ($returnType == 'json') {
                return json_encode($return);
            }

            return false;
        }

        /*
         * GET modules lists
         */

        public function load_modules($pluginPage = '') {
            $folder_path = $this->cfg['paths']['plugin_dir_path'] . 'modules/';
            $cfgFileName = 'config.php';

            // static usage, modules menu order
            $menu_order = array();

            $modules_list = glob($folder_path . '*/' . $cfgFileName);

            $nb_modules = count($modules_list);
            if ($nb_modules > 0) {
                foreach ($modules_list as $key => $mod_path) {

                    $dashboard_isfound = preg_match("/modules\/dashboard\/config\.php$/", $mod_path);
                    $depedencies_isfound = preg_match("/modules\/depedencies\/config\.php$/", $mod_path);

                    if ($pluginPage == 'depedencies') {
                        if ($depedencies_isfound !== false && $depedencies_isfound > 0)
                            ;
                        else
                            continue 1;
                    } else {
                        if ($dashboard_isfound !== false && $dashboard_isfound > 0) {
                            unset($modules_list[$key]);
                            $modules_list[$nb_modules] = $mod_path;
                        }
                    }
                }
            }

            foreach ($modules_list as $module_config) {
                $module_folder = str_replace($cfgFileName, '', $module_config);

                // Turn on output buffering
                ob_start();

                if (is_file($module_config)) {
                    require_once( $module_config );
                }
                $settings = ob_get_clean(); //copy current buffer contents into $message variable and delete current output buffer

                if (trim($settings) != "") {
                    $settings = json_decode($settings, true);
                    $settings_keys = array_keys($settings);
                    $alias = (string) end($settings_keys);

                    // create the module folder URI
                    // fix for windows server
                    $module_folder = str_replace(DIRECTORY_SEPARATOR, '/', $module_folder);

                    $__tmpUrlSplit = explode("/", $module_folder);
                    $__tmpUrl = '';
                    $nrChunk = count($__tmpUrlSplit);
                    if ($nrChunk > 0) {
                        foreach ($__tmpUrlSplit as $key => $value) {
                            if ($key > ( $nrChunk - 4) && trim($value) != "") {
                                $__tmpUrl .= $value . "/";
                            }
                        }
                    }

                    // get the module status. Check if it's activate or not
                    $status = false;

                    // default activate all core modules
                    if ($pluginPage == 'depedencies') {
                        if ($alias != 'depedencies')
                            continue 1;
                        else
                            $status = true;
                    } else {
                        if ($alias == 'depedencies')
                            continue 1;

                        if (in_array($alias, $this->cfg['core-modules'])) {
                            $status = true;
                        } else {
                            // activate the modules from DB status
                            $db_alias = $this->alias . '_module_' . $alias;

                            if (get_option($db_alias) == 'true') {
                                $status = true;
                            }
                        }
                    }

                    // push to modules array
                    $this->cfg['modules'][$alias] = array_merge(array(
                        'folder_path' => $module_folder,
                        'folder_uri' => $this->cfg['paths']['plugin_dir_url'] . $__tmpUrl,
                        'db_alias' => $this->alias . '_' . $alias,
                        'alias' => $alias,
                        'status' => $status
                            ), $settings);

                    // add to menu order array
                    if (!isset($this->cfg['menu_order'][(int) $settings[$alias]['menu']['order']])) {
                        $this->cfg['menu_order'][(int) $settings[$alias]['menu']['order']] = $alias;
                    } else {
                        // add the menu to next free key
                        $this->cfg['menu_order'][] = $alias;
                    }

                    // add module to activate modules array
                    if ($status == true) {
                        $this->cfg['activate_modules'][$alias] = true;
                    }

                    // load the init of current loop module
                    $time_start = microtime(true);
                    $start_memory_usage = (memory_get_usage());

                    // in backend
                    if ($this->is_admin === true && isset($settings[$alias]["load_in"]['backend'])) {

                        $need_to_load = false;
                        if (is_array($settings[$alias]["load_in"]['backend']) && count($settings[$alias]["load_in"]['backend']) > 0) {

                            $current_url = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : '';
                            $current_url = explode("wp-admin/", $current_url);
                            if (count($current_url) > 1) {
                                $current_url = "/wp-admin/" . $current_url[1];
                            } else {
                                $current_url = "/wp-admin/" . $current_url[0];
                            }

                            foreach ($settings[$alias]["load_in"]['backend'] as $page) {

                                $expPregQuote = ( is_array($page) ? false : true );
                                if (is_array($page))
                                    $page = $page[0];

                                $delimiterFound = strpos($page, '#');
                                $page = substr($page, 0, ($delimiterFound !== false && $delimiterFound > 0 ? $delimiterFound : strlen($page)));
                                $urlfound = preg_match("%^/wp-admin/" . ($expPregQuote ? preg_quote($page) : $page) . "%", $current_url);

                                $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
                                $section = isset($_REQUEST['section']) ? $_REQUEST['section'] : '';
                                if (
                                // $current_url == '/wp-admin/' . $page ||
                                        ( ( $page == '@all' ) || ( $current_url == '/wp-admin/admin.php?page=aiowaff' ) || (!empty($page) && $urlfound > 0 ) ) || ( $action == 'aiowaffLoadSection' && $section == $alias ) || substr($action, 0, 3) == 'aiowaff'
                                ) {
                                    $need_to_load = true;
                                }
                            }
                        }

                        if ($need_to_load == false) {
                            continue;
                        }
                    }

                    if ($this->is_admin === false && isset($settings[$alias]["load_in"]['frontend'])) {

                        $need_to_load = false;
                        if ($settings[$alias]["load_in"]['frontend'] === true) {
                            $need_to_load = true;
                        }
                        if ($need_to_load == false) {
                            continue;
                        }
                    }

                    // load the init of current loop module
                    if ($status == true && isset($settings[$alias]['module_init'])) {
                        if (is_file($module_folder . $settings[$alias]['module_init'])) {
                            //if( is_admin() ) {
                            $current_module = array($alias => $this->cfg['modules'][$alias]);
                            $GLOBALS['aiowaff_current_module'] = $current_module;

                            require_once( $module_folder . $settings[$alias]['module_init'] );

                            $time_end = microtime(true);
                            $this->cfg['modules'][$alias]['loaded_in'] = $time_end - $time_start;

                            $this->cfg['modules'][$alias]['memory_usage'] = (memory_get_usage() ) - $start_memory_usage;
                            if ((float) $this->cfg['modules'][$alias]['memory_usage'] < 0) {
                                $this->cfg['modules'][$alias]['memory_usage'] = 0.0;
                            }
                            //}
                        }
                    }
                }
            }

            // order menu_order ascendent
            ksort($this->cfg['menu_order']);
        }

        public function print_plugin_usages() {
            $html = array();

            $html[] = '<style>
				.aiowaff-bench-log {
					border: 1px solid #ccc; 
					width: 450px; 
					position: absolute; 
					top: 92px; 
					right: 2%;
					background: #95a5a6;
					color: #fff;
					font-size: 12px;
					z-index: 99999;
					
				}
					.aiowaff-bench-log th {
						font-weight: bold;
						background: #34495e;
					}
					.aiowaff-bench-log th,
					.aiowaff-bench-log td {
						padding: 4px 12px;
					}
				.aiowaff-bench-title {
					position: absolute; 
					top: 55px; 
					right: 2%;
					width: 425px; 
					margin: 0px 0px 0px 0px;
					font-size: 20px;
					background: #ec5e00;
					color: #fff;
					display: block;
					padding: 7px 12px;
					line-height: 24px;
					z-index: 99999;
				}
			</style>';

            $html[] = '<h1 class="aiowaff-bench-title">aiowaff: Benchmark performance</h1>';
            $html[] = '<table class="aiowaff-bench-log">';
            $html[] = '<thead>';
            $html[] = '<tr>';
            $html[] = '<th>Module</th>';
            $html[] = '<th>Loading time</th>';
            $html[] = '<th>Memory usage</th>';
            $html[] = '</tr>';
            $html[] = '</thead>';


            $html[] = '<tbody>';

            $total_time = 0;
            $total_size = 0;
            foreach ($this->cfg['modules'] as $key => $module) {

                $html[] = '<tr>';
                $html[] = '<td>' . ( $key ) . '</td>';
                $html[] = '<td>' . ( number_format($module['loaded_in'], 4) ) . '(seconds)</td>';
                $html[] = '<td>' . ( $this->formatBytes($module['memory_usage']) ) . '</td>';
                $html[] = '</tr>';

                $total_time = $total_time + $module['loaded_in'];
                $total_size = $total_size + $module['memory_usage'];
            }

            $html[] = '<tr>';
            $html[] = '<td colspan="3">';
            $html[] = 'Total time: <strong>' . ( $total_time ) . '(seconds)</strong><br />';
            $html[] = 'Total Memory: <strong>' . ( $this->formatBytes($total_size) ) . '</strong><br />';
            $html[] = '</td>';
            $html[] = '</tr>';

            $html[] = '</tbody>';
            $html[] = '</table>';

            //echo '<script>jQuery("body").append(\'' . ( implode("\n", $html ) ) . '\')</script>';
            echo implode("\n", $html);
        }

        public function check_secure_connection() {

            $secure_connection = false;
            if (isset($_SERVER['HTTPS'])) {
                if ($_SERVER["HTTPS"] == "on") {
                    $secure_connection = true;
                }
            }
            return $secure_connection;
        }

        /*
          helper function, image_resize
          // use timthumb
         */

        public function image_resize($src = '', $w = 100, $h = 100, $zc = 2) {
            // in no image source send, return no image
            if (trim($src) == "") {
                $src = $this->cfg['paths']['freamwork_dir_url'] . '/images/no-product-img.jpg';
            }

            if (is_file($this->cfg['paths']['plugin_dir_path'] . 'timthumb.php')) {
                return $this->cfg['paths']['plugin_dir_url'] . 'timthumb.php?src=' . $src . '&w=' . $w . '&h=' . $h . '&zc=' . $zc;
            }
        }

        /*
          helper function, upload_file
         */

        public function upload_file() {
            $slider_options = '';
            // Acts as the name
            $clickedID = $_POST['clickedID'];
            // Upload
            if ($_POST['type'] == 'upload') {
                $override['action'] = 'wp_handle_upload';
                $override['test_form'] = false;
                $filename = $_FILES [$clickedID];

                $uploaded_file = wp_handle_upload($filename, $override);
                if (!empty($uploaded_file['error'])) {
                    echo json_encode(array("error" => "Upload Error: " . $uploaded_file['error']));
                } else {
                    echo json_encode(array(
                        "url" => $uploaded_file['url'],
                        "thumb" => ($this->image_resize($uploaded_file['url'], $_POST['thumb_w'], $_POST['thumb_h'], $_POST['thumb_zc']))
                            )
                    );
                } // Is the Response
            } else {
                echo json_encode(array("error" => "Invalid action send"));
            }

            die();
        }

        /**
         * Getter function, shop config
         *
         * @params $returnType
         * @return array
         */
        public function getShopConfig($section = '', $key = '', $returnAs = 'echo') {
            if (empty($this->app_settings)) {
                $this->app_settings = $this->getAllSettings();
            }

            if (isset($this->app_settings[$this->alias . "_" . $section])) {
                if (isset($this->app_settings[$this->alias . "_" . $section][$key])) {
                    if ($returnAs == 'echo')
                        echo $this->app_settings[$this->alias . "_" . $section][$key];

                    if ($returnAs == 'return')
                        return $this->app_settings[$this->alias . "_" . $section][$key];
                }
            }
        }

        public function download_image($file_url = '', $pid = 0, $action = 'insert', $product_title = '', $step = 0) {
            if (trim($file_url) != "") {

                if ($this->amz_settings["rename_image"] == 'product_title') {
                    $image_name = sanitize_file_name($product_title);
                    $image_name = preg_replace("/[^a-zA-Z0-9-]/", "", $image_name);
                    $image_name = substr($image_name, 0, 200);
                } else {
                    $image_name = uniqid();
                }

                // Find Upload dir path
                $uploads = wp_upload_dir();
                $uploads_path = $uploads['path'] . '';
                $uploads_url = $uploads['url'];

                $fileExt = explode(".", $file_url);
                $fileExt = end($fileExt);
                if (!empty($file_ext) && preg_match('/(jpg|jpe|jpeg|gif|png)/ui', $file_ext, $m)) {
                    $fileExt = substr($fileExt, 0, 3);
                } else {
                    $fileExt = 'jpg';
                }
                $filename = $image_name . "-" . ( $step ) . "." . $fileExt;

                // Save image in uploads folder
                $response = wp_remote_get($file_url);

                if (!is_wp_error($response)) {
                    $image = $response['body'];

                    $image_url = $uploads_url . '/' . $filename; // URL of the image on the disk
                    $image_path = $uploads_path . '/' . $filename; // Path of the image on the disk
                    $ii = 0;
                    while ($this->verifyFileExists($image_path)) {
                        $filename = $image_name . "-" . ( $step );
                        $filename .= '-' . $ii;
                        $filename .= "." . $fileExt;

                        $image_url = $uploads_url . '/' . $filename; // URL of the image on the disk
                        $image_path = $uploads_path . '/' . $filename; // Path of the image on the disk
                        $ii++;
                    }

                    // verify image hash
                    $hash = md5($image);
                    $hashFound = $this->verifyProdImageHash($hash);
                    if (!empty($hashFound) && isset($hashFound->media_id)) { // image hash not found!
                        $orig_attach_id = $hashFound->media_id;
                        // $attach_data = wp_get_attachment_metadata( $orig_attach_id );
                        // $image_path = $uploads_path . '/' . basename($attach_data['file']);
                        $image_path = $hashFound->image_path;

                        // Add image in the media library - Step 3
                        /* $wp_filetype = wp_check_filetype( basename( $image_path ), null );
                          $attachment = array(
                          'post_mime_type' => $wp_filetype['type'],
                          'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $image_path ) ),
                          'post_content'   => '',
                          'post_status'    => 'inherit'
                          );

                          // $attach_id = wp_insert_attachment( $attachment, $image_path, $pid  );
                          require_once( ABSPATH . 'wp-admin/includes/image.php' );
                          wp_update_attachment_metadata( $attach_id, $attach_data ); */

                        return array(
                            'attach_id' => $orig_attach_id, // $attach_id,
                            'image_path' => $image_path,
                            'hash' => $hash
                        );
                    }
                    //write image if the wp method fails
                    $has_wrote = $this->wp_filesystem->put_contents(
                            $uploads_path . '/' . $filename, $image, FS_CHMOD_FILE
                    );

                    if (!$has_wrote) {
                        file_put_contents($uploads_path . '/' . $filename, $image);
                    }

                    // Add image in the media library - Step 3
                    $wp_filetype = wp_check_filetype(basename($image_path), null);
                    $attachment = array(
                        // 'guid' 			=> $image_url,
                        'post_mime_type' => $wp_filetype['type'],
                        'post_title' => preg_replace('/\.[^.]+$/', '', basename($image_path)),
                        'post_content' => '',
                        'post_status' => 'inherit'
                    );

                    $attach_id = wp_insert_attachment($attachment, $image_path, $pid);
                    require_once( ABSPATH . 'wp-admin/includes/image.php' );
                    $attach_data = wp_generate_attachment_metadata($attach_id, $image_path);
                    wp_update_attachment_metadata($attach_id, $attach_data);

                    return array(
                        'attach_id' => $attach_id,
                        'image_path' => $image_path,
                        'hash' => $hash
                    );
                } else {
                    return array(
                        'status' => 'invalid',
                        'msg' => htmlspecialchars(implode(';', $response->get_error_messages()))
                    );
                }
            }
        }

        public function verifyProdImageHash($hash) {
            require( $this->cfg['paths']['plugin_dir_path'] . '/modules/assets_download/init.php' );
            $aiowaffAssetDownloadCron = new aiowaffAssetDownload();

            return $aiowaffAssetDownloadCron->verifyProdImageHash($hash);
        }

        /*
          public function productPriceUpdate_frm()
          {
          $asin = isset($_REQUEST['asin']) ? $_REQUEST['asin'] : '';
          if( strlen($asin) == 10 ){
          // get product id by ASIN
          $product = $this->db->get_row( "SELECT * from {$this->db->prefix}postmeta where 1=1 and meta_key='_amzASIN' and meta_value='$asin' ", ARRAY_A );

          $post_id = (int)$product['post_id'];
          if( $post_id > 0 ){

          $amzProduct = $this->amzHelper->getProductDataFromAmazon( 'return', array(
          'importProduct'     => 'no',
          ));

          // set the product price
          $this->amzHelper->productPriceUpdate( $amzProduct, $post_id, true );
          }
          }

          return 'invalid';
          }
         */

        public function addNewProduct($retProd = array(), $pms = array()) {
            $default_pms = array(
                'ws' => 'amazon',
                'operation_id' => '',
                'import_to_category' => 'amz',
                'import_images' => isset($this->amz_settings["number_of_images"]) && (int) $this->amz_settings["number_of_images"] > 0 ? (int) $this->amz_settings["number_of_images"] : 'all',
                'import_variations' => isset($this->amz_settings['product_variation']) ? $this->amz_settings['product_variation'] : 'yes_5',
                'spin_at_import' => isset($this->amz_settings['spin_at_import']) && ($this->amz_settings['spin_at_import'] == 'yes') ? true : false,
                'import_attributes' => isset($this->amz_settings['item_attribute']) && ($this->amz_settings['item_attribute'] == 'no') ? false : true,
            );
            $pms = array_merge($default_pms, $pms);

            $durationQueue = array(); // Duration Queue
            $this->timer_start(); // Start Timer
            // status messages
            $this->opStatusMsgInit(array(
                'operation_id' => $pms['operation_id'],
                'operation' => 'add_prod',
            ));

            if (count($retProd) == 0) {
                // status messages
                $this->opStatusMsgSet(array(
                    'msg' => sprintf('empty product array from %s!', $pms['ws']),
                    'duration' => $this->timer_end(), // End Timer
                ));
                return false;
            }

            $default_import = !isset($this->amz_settings["default_import"]) || ($this->amz_settings["default_import"] == 'publish') ? 'publish' : 'draft';
            $price_zero_import = isset($this->amz_settings["import_price_zero_products"]) && $this->amz_settings["import_price_zero_products"] == 'yes' ? true : false;

            // verify if : amazon zero price product!
            if (!$price_zero_import && $this->get_ws_object($pms['ws'])->productAmazonPriceIsZero($retProd)) {
                // status messages
                $this->opStatusMsgSet(array(
                    'msg' => 'price is zero, so it is skipped!',
                    'duration' => $this->timer_end(), // End Timer
                ));
                return false;
            }

            // short description
            if ($this->amz_settings['show_short_description'] == 'yes') {
                // first 3 paragraph
                $excerpt = @explode("\n", @strip_tags(implode("\n", $retProd['Feature'])));
                $excerpt = @implode("\n", @array_slice($excerpt, 0, 3));
            } else {
                $excerpt = '';
            }

            // full description
            //$desc = (count($retProd["images"]) > 0 ? "[gallery]" : "") . "\n" . $retProd['EditorialReviews'] . "\n" . (count($retProd['Feature']) > 0 &&  is_array($retProd['Feature']) == true ? implode("\n", $retProd['Feature']) : '') . "\n" . '[amz_corss_sell asin="' . ( $retProd['ASIN'] ) . '"]';
            $__desc = array();
            //$__desc[] = (count($retProd["images"]) > 0 ? "[gallery]" : "");
            $__desc[] = ($retProd['hasGallery'] == 'true' ? "[gallery]" : "");
            if ($pms['ws'] == 'amazon') {
                $__desc[] = !empty($retProd['EditorialReviews']) ? $retProd['EditorialReviews'] : '';
                $__desc[] = (count($retProd['Feature']) > 0 && is_array($retProd['Feature']) == true ? implode("\n", $retProd['Feature']) : '');
                $__desc[] = '[amz_corss_sell asin="' . ( $retProd['ASIN'] ) . '"]';
            } else {
                $__desc[] = isset($retProd['Description']) && !empty($retProd['Description']) ? $retProd['Description'] : '';
            }
            $desc = implode("\n", array_filter($__desc));

            $args = array(
                'post_title' => $retProd['Title'],
                'post_status' => $default_import,
                'post_content' => $desc,
                'post_excerpt' => $excerpt,
                'post_type' => 'product',
                'menu_order' => 0,
                'post_author' => 1, //get_current_user_id()
            );

            $existProduct = amzStore_bulk_wp_exist_post_by_args($args);
            $metaPrefix = 'amzStore_product_';

            // check if post exists, if exist return array
            if ($existProduct === false) {
                $lastId = wp_insert_post($args);

                $duration = $this->timer_end(); // End Timer
                // status messages
                $this->opStatusMsgSet(array(
                    'status' => 'valid',
                    'msg' => 'product inserted with ID: ' . $lastId,
                    'duration' => $duration,
                ));
            } else {
                $lastId = $existProduct['ID'];

                $duration = $this->timer_end(); // End Timer
                // status messages
                $this->opStatusMsgSet(array(
                    'status' => 'valid',
                    'msg' => 'product already exists with ID: ' . $lastId,
                    'duration' => $duration,
                ));
            }

            apply_filters('aiowaff_after_product_import', $lastId);

            $durationQueue[] = $this->timer_end(); // End Timer
            $this->timer_start(); // Start Timer
            // spin post/product content!
            if ($pms['spin_at_import']) {

                $this->timer_start(); // Start Timer

                $replacements_nb = 10;
                if (isset($this->amz_settings['spin_max_replacements']))
                    $replacements_nb = (int) $this->amz_settings['spin_max_replacements'];

                $this->spin_content(array(
                    'prodID' => $lastId,
                    'replacements' => $replacements_nb
                ));

                $duration = $this->timer_end(); // End Timer
                $this->timer_start(); // Start Timer
                // status messages
                $this->opStatusMsgSet(array(
                    'status' => 'valid',
                    'msg' => 'spin content done',
                    'duration' => $duration,
                ));

                // add last import report
                $this->add_last_imports('last_import_spin', array('duration' => $duration)); // End Timer & Add Report
            }

            // import images - just put images paths to assets table
            if (( $pms['import_images'] === 'all' ) || ( (int) $pms['import_images'] > 0 )) {

                // get product images
                $setImagesStatus = $this->get_ws_object($pms['ws'])->set_product_images($retProd, $lastId, 0, $pms['import_images']);

                $duration = $this->timer_end(); // End Timer
                $durationQueue[] = $duration; // End Timer
                $this->timer_start(); // Start Timer
                // status messages
                $this->opStatusMsgSet(array(
                    'status' => 'valid',
                    'msg' => $setImagesStatus['msg'],
                    'duration' => $duration,
                ));
            }

            $durationQueue[] = $this->timer_end(); // End Timer
            $this->timer_start(); // Start Timer
            // import to category
            if ($pms['import_to_category'] != 'amz') {

                $tocateg = $pms['import_to_category'];

                $final_categs = array();
                $final_categs[] = (int) $tocateg;

                $ancestors = get_ancestors((int) $tocateg, 'product_cat');

                if (count($ancestors) > 0 && is_array($ancestors) && $ancestors != '') {
                    $final_categs = array_merge($final_categs, $ancestors);
                }

                // set the post category
                wp_set_object_terms($lastId, $final_categs, 'product_cat', true);
            } else {
                $tocateg = $retProd['BrowseNodes'];

                // setup product categories
                $createdCats = $this->get_ws_object($pms['ws'])->set_product_categories($tocateg);

                // Assign the post on the categories created
                wp_set_post_terms($lastId, $createdCats, 'product_cat');
            }

            // product tags
            if (isset($retProd['Tags']) && !empty($retProd['Tags'])) {
                // setup product tags
                $createdTags = $this->get_ws_object($pms['ws'])->set_product_tags($retProd['Tags']);

                // Assign the post on the categories created
                if (!empty($createdTags)) {
                    wp_set_post_terms($lastId, $createdTags, 'product_tag');
                }
            }

            $duration = $this->timer_end(); // End Timer
            $durationQueue[] = $duration; // End Timer
            $this->timer_start(); // Start Timer
            // status messages
            $this->opStatusMsgSet(array(
                'status' => 'valid',
                'msg' => 'set product categories',
                'duration' => $duration,
            ));

            // import attributes
            if ($pms['import_attributes']) {
                if (count($retProd['ItemAttributes']) > 0) {
                    $this->timer_start(); // Start Timer
                }

                // add product attributes
                $this->get_ws_object($pms['ws'])->set_woocommerce_attributes($retProd['ItemAttributes'], $lastId);

                if (count($retProd['ItemAttributes']) > 0) {
                    $duration = $this->timer_end(); // End Timer
                    $this->timer_start(); // Start Timer
                    // status messages
                    $this->opStatusMsgSet(array(
                        'status' => 'valid',
                        'msg' => 'import attributes',
                        'duration' => $duration,
                    ));

                    // add last import report
                    $this->add_last_imports('last_import_attributes', array(
                        'duration' => $duration,
                    )); // End Timer & Add Report
                }
            }

            // than update the metapost
            $this->get_ws_object($pms['ws'])->set_product_meta_options($retProd, $lastId, false);

            $duration = $this->timer_end(); // End Timer
            $durationQueue[] = $duration; // End Timer
            $this->timer_start(); // Start Timer
            // status messages
            $this->opStatusMsgSet(array(
                'status' => 'valid',
                'msg' => 'set product metas',
                'duration' => $duration,
            ));

            // set the product price
            $this->get_ws_object($pms['ws'])->productPriceUpdate($retProd, $lastId, false);

            $duration = $this->timer_end(); // End Timer
            $durationQueue[] = $duration; // End Timer
            $this->timer_start(); // Start Timer
            // status messages
            $this->opStatusMsgSet(array(
                'status' => 'valid',
                'msg' => 'product price update',
                'duration' => $duration,
            ));

            // IMPORT PRODUCT VARIATIONS
            if ($pms['import_variations'] != 'no' && in_array($pms['ws'], array('amazon', 'ebay'))) {
                $this->timer_start(); // Start Timer
                // current message
                $current_msg = $this->opStatusMsg['msg'];

                $setVariationsStatus = $this->get_ws_object($pms['ws'])->set_woocommerce_variations($retProd, $lastId, $pms['import_variations']);

                // don't add all variation adding texts to the final message!
                $this->opStatusMsg['msg'] = $current_msg;

                $duration = $this->timer_end(); // End Timer
                $this->timer_start(); // Start Timer
                // status messages
                $this->opStatusMsgSet(array(
                    'status' => 'valid',
                    'msg' => $setVariationsStatus['msg'],
                    'duration' => $duration,
                ));

                // add last import report
                // ...done in amzHelper file
            }

            // Set the product type
            $this->update_products_type($lastId);

            $duration = $this->timer_end(); // End Timer
            $durationQueue[] = $duration; // End Timer
            $this->timer_start(); // Start Timer
            // status messages
            $this->opStatusMsgSet(array(
                'status' => 'valid',
                'msg' => 'update products type',
                'duration' => $duration,
            ));

            // ...any other executing code!

            $duration = $this->timer_end(); // End Timer
            $durationQueue[] = $duration; // End Timer
            $duration = round(array_sum($durationQueue), 4); // End Timer
            // status messages
            $this->opStatusMsgSet(array(
                'status' => 'valid',
                'msg' => 'product adding finished (duration is without time for spin, variations, attributes)',
                'duration' => $duration,
                'end' => true,
            ));

            // add last import report
            $this->add_last_imports('last_product', array(
                'duration' => $duration,
            )); // End Timer & Add Report

            return $lastId;
        }

        public function updateWooProduct($retProd = array(), $rules = array(), $lastId = 0) {
            $provider = 'ebay';

            if (count($retProd) == 0) {
                return false;
            }

            $args_update = array();
            $args_update['ID'] = $lastId;

            // title
            if ($rules['title'] == true) {
                $args_update['post_title'] = $retProd['Title'];
            }

            // short description
            /*
              if($rules['short_desc'] == true && $this->amz_settings['show_short_description'] == 'yes') {
              // first 3 paragraph
              $excerpt = @explode("\n", @strip_tags( implode("\n", $retProd['Feature']) ) );
              $excerpt = @implode("\n", @array_slice($excerpt, 0, 3));
              $args_update['post_excerpt'] = $excerpt;
              }
              //else {
              //    $excerpt = '';
              //}
             */

            // full description
            if ($rules['desc'] == true) {
                //$desc = ($retProd['hasGallery'] == 'true' ? "[gallery]" : "") . "\n" . $retProd['EditorialReviews'] . "\n" . (count($retProd['Feature']) > 0 &&  is_array($retProd['Feature']) == true ? implode("\n", $retProd['Feature']) : '') . "\n" . '[amz_corss_sell asin="' . ( $retProd['ASIN'] ) . '"]';
                $__desc = array();
                //$__desc[] = (count($retProd["images"]) > 0 ? "[gallery]" : "");
                $__desc[] = ($retProd['hasGallery'] == 'true' ? "[gallery]" : "");
                //if ( $provider == 'amazon' ) {
                //	$__desc[] = !empty($retProd['EditorialReviews']) ? $retProd['EditorialReviews'] : '';
                //	$__desc[] = (count($retProd['Feature']) > 0 &&  is_array($retProd['Feature']) == true ? implode("\n", $retProd['Feature']) : '');
                //	$__desc[] = '[amz_corss_sell asin="' . ( $retProd['ASIN'] ) . '"]';
                //} else {
                $__desc[] = isset($retProd['Description']) && !empty($retProd['Description']) ? $retProd['Description'] : '';
                //}
                $desc = implode("\n", array_filter($__desc));

                $args_update['post_content'] = $desc;
            }

            // update the post if needed
            if (count($args_update) > 1) { // because ID is allways the same!
                wp_update_post($args_update);
            }

            /*
              // than update the metapost
              if($rules['sku'] == true) {
              update_post_meta($lastId, '_sku', $retProd['SKU']);
              }
             */
            if ($rules['url'] == true) {
                update_post_meta($lastId, '_product_url', home_url(sprintf(
                                        '/?redirect_prodid=%s', $this->prodid_set($retProd['ASIN'], $provider, 'add')
                )));
            }

            /*
              // reviews
              $tab_data = array();
              $tab_data[] = array(
              'id' => 'amzAff-customer-review',
              'content' => '<iframe src="' . ( isset($retProd['CustomerReviewsURL']) ? $retProd['CustomerReviewsURL'] : '' ) . '" width="100%" height="450" frameborder="0"></iframe>'
              );
              if($rules['reviews'] == true) {
              if( isset($retProd['CustomerReviewsURL']) && @trim($retProd['CustomerReviewsURL']) != "" )
              update_post_meta($lastId, 'amzaff_woo_product_tabs', $tab_data);
              }
             */

            if ($rules['price'] == true) {
                // set the product price
                $this->get_ws_object($provider)->productPriceUpdate($retProd, $lastId, false);
            }
            /*
              if($rules['sales_rank'] == true) {
              update_post_meta($lastId, '_sales_rank', $retProd['SalesRank']);
              }
             */

            return $lastId;
        }

        public function getAmzSimilarityProducts($asin, $return_nr = 3, $force_update = false) {
            // add 1 fake return products, current product
            $return_nr = $return_nr + 1;

            $cache_valid_for = (60 * 60 * 24); // 24 hours in seconds
            // check for cache of this ASIN
            $cache_request = $this->db->get_row($this->db->prepare("SELECT * FROM " . ( $this->db->prefix ) . "amz_cross_sell WHERE ASIN = %s", $asin), ARRAY_A);

            // if cache found for this product
            if ($cache_request != "" && count($cache_request) > 0 && $force_update === false) {
                // if cache still valid, return from mysql cache
                if (isset($cache_request['add_date']) || (strtotime($cache_request['add_date']) > (time() + $cache_valid_for))) {

                    $ret = array();
                    // get products from DB cache amz_cross_sell table
                    $products = @unserialize($cache_request['products']);

                    return array_slice($products, 0, $return_nr);
                }
            }

            // load the amazon webservices client class
            require_once( $this->cfg['paths']['plugin_dir_path'] . '/lib/scripts/amazon/aaAmazonWS.class.php');

            // create new amazon instance
            $aaAmazonWS = new aaAmazonWS(
                    $this->amz_settings['AccessKeyID'], $this->amz_settings['SecretAccessKey'], $this->amz_settings['country'], $this->main_aff_id()
            );
            $aaAmazonWS->set_the_plugin($this);

            $retProd = array();
            $similarity = $aaAmazonWS->responseGroup('Medium,ItemAttributes,Offers')->optionalParameters(array(
                        'MerchantId' => 'Amazon',
                        'Condition' => 'New'
                    ))->similarityLookup($asin);

            $thisProd = $aaAmazonWS->responseGroup('Large,OfferFull,Offers')->optionalParameters(array(
                        'MerchantId' => 'Amazon',
                        'Condition' => 'New'
                    ))->lookup($asin);

            if ($thisProd['Items']['Request']["IsValid"] == "True" && isset($thisProd['Items']['Item']) && count($thisProd['Items']['Item']) > 0) {
                $thisProd = $thisProd['Items']['Item'];

                // product large image
                $retProd[$thisProd['ASIN']]['thumb'] = $thisProd['SmallImage']['URL'];

                $retProd[$thisProd['ASIN']]['ASIN'] = $thisProd['ASIN'];

                // product title
                $retProd[$thisProd['ASIN']]['Title'] = isset($thisProd['ItemAttributes']['Title']) ? $thisProd['ItemAttributes']['Title'] : '';

                // product Manufacturer
                $retProd[$thisProd['ASIN']]['Manufacturer'] = isset($thisProd['ItemAttributes']['Manufacturer']) ? $thisProd['ItemAttributes']['Manufacturer'] : '';

                $retProd[$thisProd['ASIN']]['price'] = isset($thisProd['OfferSummary']['LowestNewPrice']['FormattedPrice']) ? preg_replace("/[^0-9,.]/", "", $thisProd['OfferSummary']['LowestNewPrice']['FormattedPrice']) : '';
            }

            if ($similarity['Items']["Request"]["IsValid"] == "True" && isset($similarity['Items']['Item']) && count($similarity['Items']['Item']) > 1) {
                foreach ($similarity['Items']['Item'] as $key => $value) {
                    $thisProd = $value;

                    if (count($similarity['Items']['Item']) > 0 && count($value) > 0 && isset($thisProd['ASIN']) && strlen($thisProd['ASIN']) >= 10) {
                        // product large image
                        $retProd[$thisProd['ASIN']]['thumb'] = $thisProd['SmallImage']['URL'];

                        $retProd[$thisProd['ASIN']]['ASIN'] = $thisProd['ASIN'];

                        // product title
                        $retProd[$thisProd['ASIN']]['Title'] = isset($thisProd['ItemAttributes']['Title']) ? $thisProd['ItemAttributes']['Title'] : '';

                        // product Manufacturer
                        $retProd[$thisProd['ASIN']]['Manufacturer'] = isset($thisProd['ItemAttributes']['Manufacturer']) ? $thisProd['ItemAttributes']['Manufacturer'] : '';

                        $retProd[$thisProd['ASIN']]['price'] = isset($thisProd['OfferSummary']['LowestNewPrice']['FormattedPrice']) ? preg_replace("/[^0-9,.]/", "", $thisProd['OfferSummary']['LowestNewPrice']['FormattedPrice']) : '';

                        // remove if don't have valid price
                        if (!isset($retProd[$thisProd['ASIN']]['price']) || trim($retProd[$thisProd['ASIN']]['price']) == "") {
                            @unlink($retProd[$thisProd['ASIN']]);
                        }
                    }
                }
            }

            // if cache not found for this product
            if ($cache_request == "" && count($cache_request) == 0) {
                $this->db->insert(
                        $this->db->prefix . "amz_cross_sell", array(
                    'ASIN' => $asin,
                    'products' => serialize(array_slice($retProd, 0, $return_nr)),
                    'nr_products' => $return_nr
                        ), array(
                    '%s',
                    '%s',
                    '%d'
                        )
                );
            } else {
                $this->db->update(
                        $this->db->prefix . "amz_cross_sell", array(
                    'products' => serialize(array_slice($retProd, 0, $return_nr)),
                    'nr_products' => $return_nr
                        ), array('ASIN' => $asin), array(
                    '%s',
                    '%s',
                    '%d'
                        )
                );
            }

            return array_slice($retProd, 0, $return_nr);
        }

        public function remove_gallery($content) {
            return str_replace('[gallery]', '', $content);
        }

        public function cross_sell_box($atts) {
            extract(shortcode_atts(array(
                'asin' => ''
                            ), $atts));

            $cross_selling = (isset($this->amz_settings["cross_selling"]) && $this->amz_settings["cross_selling"] == 'yes' ? true : false);

            if ($cross_selling == false)
                return '';

            global $product;

            // get product related items from Amazon
            $products = $this->getAmzSimilarityProducts($asin);

            $backHtml = array();
            if (count($products) > 1) {

                $backHtml[] = "<link rel='stylesheet' id='amz-cross-sell' href='" . ( $this->cfg['paths']['design_dir_url'] ) . "/cross-sell.css' type='text/css' media='all' />";

                $backHtml[] = '<div class="cross-sell">';
                $backHtml[] = '<h2>' . ( __('Frequently Bought Together', $this->localizationName) ) . '</h2>';
                $backHtml[] = '<div style="margin-top: 0px;" class="separator"></div>';


                $backHtml[] = '<ul id="feq-products">';
                $cc = 0;
                $_total_price = 0;
                foreach ($products as $key => $value) {
                    $value['price'] = str_replace(",", ".", $value['price']);
                    $product_buy_url = $this->_product_buy_url('', $value['ASIN']);
                    $prod_link = home_url('/?redirectAmzASIN=' . $value['ASIN']);
                    $prod_link = $product_buy_url;
                    $backHtml[] = '<li>';
                    $backHtml[] = '<a target="_blank" rel="nofollow" href="' . ( $prod_link ) . '">';
                    $backHtml[] = '<img class="cross-sell-thumb" id="cross-sell-thumb-' . ( $value['ASIN'] ) . '" src="' . ( $value['thumb'] ) . '" alt="' . ( htmlentities(str_replace('"', "'", $value['Title'])) ) . '">';
                    $backHtml[] = '</a>';
                    if ($cc < (count($products) - 1)) {
                        $backHtml[] = '<div class="plus-sign">+</div>';
                    }

                    $backHtml[] = '</li>';

                    $cc++;

                    $_total_price = $_total_price + $value['price'];
                }

                $backHtml[] = '<li class="cross-sell-buy-btn">';
                $backHtml[] = '<span id="cross-sell-bpt">Price for all:</span>';
                $backHtml[] = '<span id="cross-sell-buying-price" class="price">' . ( woocommerce_price($_total_price) ) . '</span>';
                $backHtml[] = '<div style="clear:both"></div><a href="' . home_url() . '" id="cross-sell-add-to-cart"><img src="' . ( $this->cfg['paths']['freamwork_dir_url'] . 'images/btn_add-to-cart.png' ) . '"/></a>';
                $backHtml[] = '</li>';
                $backHtml[] = '</ul>';

                $backHtml[] = '<div class="cross-sell-buy-selectable">';
                $backHtml[] = '<ul class="cross-sell-items">';
                $cc = 0;
                foreach ($products as $key => $value) {

                    if ($cc == 0) {
                        $backHtml[] = '<li>';
                        $backHtml[] = '<input type="checkbox" checked="checked" value="' . ( $value['ASIN'] ) . '">';
                        $backHtml[] = '<div class="cross-sell-product-title"><strong>' . __('This item:', $this->localizationName) . ' </strong>' . $value['Title'] . '</div>';
                        $backHtml[] = '<div class="cross-sell-item-price">' . ( woocommerce_price($value['price']) ) . '</div>';
                        $backHtml[] = '</li>';
                    } else {
                        $product_buy_url = $this->_product_buy_url('', $value['ASIN']);
                        $prod_link = home_url('/?redirectAmzASIN=' . $value['ASIN']);
                        $prod_link = $product_buy_url;
                        $backHtml[] = '<li>';
                        $backHtml[] = '<input type="checkbox" checked="checked" value="' . ( $value['ASIN'] ) . '">';
                        $backHtml[] = '<div class="cross-sell-product-title">' . ( '<a target="_blank" rel="nofollow" href="' . ( $prod_link ) . '">' . $value['Title'] . '</a>' ) . '</div>';
                        $backHtml[] = '<div class="cross-sell-item-price">' . ( woocommerce_price($value['price']) ) . '</div>';
                        $backHtml[] = '</li>';
                    }

                    $cc++;
                }
                $backHtml[] = '</table>';

                $backHtml[] = '</div>';

                $backHtml[] = '</div>';

                $backHtml[] = '<div style="clear:both;"></div>';

                $backHtml[] = "<script type='text/javascript' src='" . ( $this->cfg['paths']['design_dir_url'] ) . "/cross-sell.js'></script>";
            }

            return isset($_total_price) && ($_total_price > 0 ) ? implode(PHP_EOL, $backHtml) : '';
        }

        /**
         * HTML escape given string
         *
         * @param string $text
         * @return string
         */
        public function escape($text) {
            $text = (string) $text;
            if ('' === $text)
                return '';

            $result = @htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
            if (empty($result)) {
                $result = @htmlspecialchars(utf8_encode($text), ENT_COMPAT, 'UTF-8');
            }

            return $result;
        }

        public function getBrowseNodes($nodeid = 0, $provider = 'amazon') {
            if (!is_numeric($nodeid)) {
                return array(
                    'status' => 'invalid',
                    'msg' => 'The $nodeid is not numeric: ' . $nodeid
                );
            }

            $prefix = 'ebay' == $provider ? '_' . $provider : '';
            $optname = $this->alias . $prefix . '_node_children_' . $nodeid;

            // try to get the option with this browsenode
            $nodes = get_option($optname, false);

            // unable to find the node into cache, get live data
            if (!isset($nodes) || $nodes == false || count($nodes) == 0) {
                $nodes = $this->get_ws_object($provider)->browseNodeLookup($nodeid);

                if ('amazon' == $provider) {

                    if (isset($nodes['BrowseNodes']) && count($nodes['BrowseNodes']) > 0) {
                        if (isset($nodes['BrowseNodes']['BrowseNode']['Children']['BrowseNode']) && count($nodes['BrowseNodes']['BrowseNode']['Children']['BrowseNode']) > 0) {

                            if (!isset($nodes['BrowseNodes']['BrowseNode']['Children']['BrowseNode'][1]['BrowseNodeId'])) {
                                $nodes['BrowseNodes']['BrowseNode']['Children']['BrowseNode'] = array(
                                    $nodes['BrowseNodes']['BrowseNode']['Children']['BrowseNode']
                                );
                            }

                            if (count($nodes['BrowseNodes']['BrowseNode']['Children']['BrowseNode']) > 0) {
                                $nodes = $nodes['BrowseNodes']['BrowseNode']['Children']['BrowseNode'];

                                // store the cache into DB
                                update_option($optname, $nodes);
                            }
                        }
                    }
                } else if ('ebay' == $provider) {

                    $childs = array();
                    $lev1 = isset($nodes['CategoryArray']) ? $nodes['CategoryArray'] : array();
                    if (!empty($lev1)) {

                        $lev2 = isset($lev1['Category']) ? $lev1['Category'] : array();
                        if (!empty($lev2)) {
                            $lev3 = isset($lev2['CategoryID']) ? array($lev2) : $lev2;

                            if (!empty($lev3)) {
                                foreach ($lev3 as $key => $val) {
                                    // don't count the current nodeid / category
                                    if ($nodeid == $val['CategoryID'])
                                        continue 1;

                                    $childs[] = $val;
                                }
                            } // end lev3
                        } // end lev2
                    } // end lev1

                    if (!empty($childs)) {
                        // store the cache into DB
                        $nodes = $childs;
                        update_option($optname, $nodes);
                    }
                }
            }


            return $nodes;
        }

        public function multi_implode($array, $glue) {
            $ret = '';

            foreach ($array as $item) {
                if (is_array($item)) {
                    $ret .= $this->multi_implode($item, $glue) . $glue;
                } else {
                    $ret .= $item . $glue;
                }
            }

            $ret = substr($ret, 0, 0 - strlen($glue));

            return $ret;
        }

        public function download_asset_lightbox($prod_id = 0, $from = 'default', $return = 'die') {
            $requestData = array(
                'prod_id' => isset($_REQUEST['prod_id']) ? $_REQUEST['prod_id'] : $prod_id,
                'from' => isset($_REQUEST['from']) ? $_REQUEST['from'] : $from,
            );
            extract($requestData);

            $assets = $this->get_ws_object('generic')->get_asset_by_postid('all', $prod_id, true);
            if (count($assets) <= 0) {
                if ($return == 'die') {
                    die(json_encode(array(
                        'status' => 'invalid',
                        'html' => __("this product has no assets to be dowloaded!", $this->localizationName)
                    )));
                } else {
                    return __("this product has no assets to be dowloaded!", $this->localizationName);
                }
            }

            $css = array();
            $css['container'] = ( $from == 'default' ? 'aiowaff-asset-download-lightbox-properties' : 'aiowaff-asset-download-IM' );

            $html = array();
            $html[] = '<div class="aiowaff-asset-download-lightbox ' . $css['container'] . '">';
            $html[] = '<div class="aiowaff-donwload-in-progress-box">';
            $html[] = '<h1>' . __('Images download in progress ... ', $this->localizationName) . '<a href="#" class="aiowaff-button red" id="aiowaff-close-btn">' . __('CLOSE', $this->localizationName) . '</a></h1>';
            $html[] = '<p class="aiowaff-message aiowaff-info aiowaff-donwload-notice">';
            $html[] = __('Please be patient while the images are downloaded. 
			This can take a while if your server is slow (inexpensive hosting) or if you have many images. 
			Do not navigate away from this page until this script is done. 
			You will be notified via this box when the regenerating is completed.', $this->localizationName);
            $html[] = '</p>';

            $html[] = '<div class="aiowaff-process-progress-bar">';
            $html[] = '<div class="aiowaff-process-progress-marker"><span>0%</span></div>';
            $html[] = '</div>';

            $html[] = '<div class="aiowaff-images-tail">';
            $html[] = '<ul>';

            if (count($assets) > 0) {
                foreach ($assets as $asset) {

                    $html[] = '<li data-id="' . ( $asset->id ) . '">';
                    $html[] = '<img src="' . ( $asset->thumb ) . '">';
                    $html[] = '</li>';
                }
            }

            $html[] = '</ul>';
            $html[] = '</div>';
            $html[] = '
			<script>
				jQuery(".aiowaff-images-tail ul").each(function(){
					
					var that = jQuery(this),
						lis = that.find("li"),
						size = lis.size();
					
					that.width( size *  86 );
				});
				jQuery(".aiowaff-images-tail ul").scrollLeft(0);
			</script>
			';

            $html[] = '<h2 class="aiowaff-process-headline">' . __('Debugging Information:', $this->localizationName) . '</h2>';
            $html[] = '<table class="aiowaff-table aiowaff-debug-info">';
            if ($from == 'default') {
                $html[] = '<tr>';
                $html[] = '<td width="150">' . __('Total Images:', $this->localizationName) . '</td>';
                $html[] = '<td>' . ( count($assets) ) . '</td>';
                $html[] = '</tr>';
                $html[] = '<tr>';
                $html[] = '<td>' . __('Images Downloaded:', $this->localizationName) . '</td>';
                $html[] = '<td class="aiowaff-value-downloaded">0</td>';
                $html[] = '</tr>';
                $html[] = '<tr>';
                $html[] = '<td>' . __('Downloaded Failures:', $this->localizationName) . '</td>';
                $html[] = '<td class="aiowaff-value-failures">0</td>';
                $html[] = '</tr>';
            } else {
                $html[] = '<tr>';
                $html[] = '<td>' . __('Total Images:', $this->localizationName) . '<span>' . ( count($assets) ) . '</span></td>';
                $html[] = '<td>' . __('Images Downloaded:', $this->localizationName) . '<span class="aiowaff-value-downloaded">0</span></td>';
                $html[] = '<td>' . __('Downloaded Failures:', $this->localizationName) . '<span class="aiowaff-value-failures">0</span></td>';
                $html[] = '</tr>';
            }
            $html[] = '</table>';

            $html[] = '<div class="aiowaff-downoad-log">';
            $html[] = '<ol>';
            //$html[] = 				'<li>"One-size-fits-most-Tube-DressCoverup-Field-Of-Flowers-White-0" (ID 214) failed to resize. The error message was: The originally uploaded image file cannot be found at <code>/home/aateam30/public_html/cc/wp-plugins/woo-Amazon-payments/wp-content/uploads/2014/03/One-size-fits-most-Tube-DressCoverup-Field-Of-Flowers-White-0.jpg</code></li>';
            $html[] = '</ol>';
            $html[] = '</div>';
            $html[] = '</div>';
            $html[] = '</div>';

            if ($return == 'die') {
                die(json_encode(array(
                    'status' => 'valid',
                    'html' => implode("\n", $html)
                )));
            }

            return implode("\n", $html);
        }

        /**
         * Delete product assets
         */
        public function product_assets_verify() {
            if (current_user_can('delete_posts'))
                add_action('delete_post', array($this, 'product_assets_delete'), 10);
        }

        public function product_assets_delete($prod_id) {
            // verify we are in woocommerce product
            if (function_exists('get_product')) {
                $product = get_product($prod_id);
                if (isset($product->id) && (int) $product->id > 0) {

                    require( $this->cfg['paths']['plugin_dir_path'] . '/modules/assets_download/init.php' );
                    $aiowaffAssetDownloadCron = new aiowaffAssetDownload();

                    return $aiowaffAssetDownloadCron->product_assets_delete($prod_id);
                }
            }
        }

        /**
         * Usefull
         */
        //format right (for db insertion) php range function!
        public function doRange($arr) {
            $newarr = array();
            if (is_array($arr) && count($arr) > 0) {
                foreach ($arr as $k => $v) {
                    $newarr[$v] = $v;
                }
            }
            return $newarr;
        }

        //verify if file exists!
        public function verifyFileExists($file, $type = 'file') {
            clearstatcache();
            if ($type == 'file') {
                if (!file_exists($file) || !is_file($file) || !is_readable($file)) {
                    return false;
                }
                return true;
            } else if ($type == 'folder') {
                if (!is_dir($file) || !is_readable($file)) {
                    return false;
                }
                return true;
            }
            // invalid type
            return 0;
        }

        // Return current Unix timestamp with microseconds
        // Simple function to replicate PHP 5 behaviour
        public function microtime_float() {
            list($usec, $sec) = explode(" ", microtime());
            return ((float) $usec + (float) $sec);
        }

        public function formatBytes($bytes, $precision = 2) {
            $units = array('B', 'KB', 'MB', 'GB', 'TB');

            $bytes = max($bytes, 0);
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);

            // Uncomment one of the following alternatives
            // $bytes /= pow(1024, $pow);
            $bytes /= (1 << (10 * $pow));

            return round($bytes, $precision) . ' ' . $units[$pow];
        }

        public function prepareForInList($v) {
            return "'" . $v . "'";
        }

        public function prepareForPairView($v, $k) {
            return sprintf("(%s, %s)", $k, $v);
        }

        public function db_custom_insert($table, $fields, $ignore = false, $wp_way = false) {
            if ($wp_way && !$ignore) {
                $this->db->insert(
                        $table, $fields['values'], $fields['format']
                );
            } else {

                $formatVals = implode(', ', array_map(array('aiowaff', 'prepareForInList'), $fields['format']));
                $theVals = array();
                foreach ($fields['values'] as $k => $v)
                    $theVals[] = $k;

                $q = "INSERT " . ($ignore ? "IGNORE" : "") . " INTO $table (" . implode(', ', $theVals) . ") VALUES (" . $formatVals . ");";
                foreach ($fields['values'] as $kk => $vv)
                    $fields['values']["$kk"] = esc_sql($vv);

                $q = vsprintf($q, $fields['values']);
                $r = $this->db->query($q);
            }
        }

        public function is_prod_valid($post_id) {
            if (empty($post_id))
                return false;

            $isProdAmz = $this->verify_product_isamazon($post_id);
            if (empty($isProdAmz))
                return false;
            return true;
        }

        public function verify_product_isamazon($prod_id) {
            // verify we are in woocommerce product
            if (is_object($prod_id)) {
                $product = $prod_id;
            } else if (function_exists('get_product')) {
                $product = get_product($prod_id);
            }

            if (1) {
                if (isset($product->id) && (int) $product->id > 0) {

                    $prod_id = (int) $product->id;

                    // verify is amazon product!
                    $asin = $this->get_post_meta($prod_id, '_amzASIN', true);

                    if ($asin !== false && strlen($asin) > 0) {
                        return true;
                    }
                }
            }
            return false;
        }

        public function verify_product_isvariation($prod_id) {
            // verify we are in woocommerce product
            if (function_exists('get_product')) {
                $product = new WC_Product_Variable($prod_id); // WC_Product

                if (isset($product->id) && (int) $product->id > 0) {
                    if ($product->has_child()) // is product variation parent!
                        return true;
                }
            }
            return false;
        }

        public function get_product_variations($prod_id) {
            // verify we are in woocommerce product
            if (function_exists('get_product')) {
                $product = new WC_Product_Variable($prod_id); // WC_Product

                if (isset($product->id) && (int) $product->id > 0) {
                    return $product->get_children();
                }
            }
            return array();
        }

        /**
         * spin post/product content
         */
        public function spin_content($req = array()) {

            $request = array(
                'prodID' => isset($req['prodID']) ? $req['prodID'] : 0,
                'replacements' => isset($req['replacements']) ? $req['replacements'] : 10
            );

            $ret = array(
                'status' => 'valid',
                'data' => array()
            );

            // spin content action
            require_once( $this->cfg['paths']["scripts_dir_path"] . '/php-query/phpQuery.php' );
            require_once( $this->cfg['paths']["scripts_dir_path"] . '/spin-content/spin.class.php' );

            if (1) {

                $lang = isset($this->amz_settings['main_aff_id']) ? $this->amz_settings['main_aff_id'] : 'en';
                $lang = strtolower($lang);

                $spinner = aiowaffSpinner::getInstance();
                $spinner->set_syn_language($lang);
                $spinner->set_replacements_number($request['replacements']);

                // first check if you have the original content saved into DB
                $post_content = get_post_meta($request['prodID'], 'aiowaff_old_content', true);

                // if not, retrive from DB
                if ($post_content == false) {
                    $live_post = get_post($request['prodID'], ARRAY_A);
                    $post_content = $live_post['post_content'];
                }

                $spinner->load_content($post_content);
                $spin_return = $spinner->spin_content();
                $reorder_content = $spinner->reorder_synonyms();
                $fresh_content = $spinner->get_fresh_content($reorder_content);

                update_post_meta($request['prodID'], 'aiowaff_spinned_content', $spin_return['spinned_content']);
                update_post_meta($request['prodID'], 'aiowaff_reorder_content', $reorder_content);
                update_post_meta($request['prodID'], 'aiowaff_old_content', $spin_return['old_content']);
                update_post_meta($request['prodID'], 'aiowaff_finded_replacements', $spin_return['finded_replacements']);

                // Update the post into the database
                wp_update_post(array(
                    'ID' => $request['prodID'],
                    'post_content' => $fresh_content
                ));

                $ret = array(
                    'status' => 'valid',
                    'data' => array(
                        'reorder_content' => $reorder_content
                    )
                );
            }
            return $ret;
        }

        /**
         * setup module messages
         */
        public function print_module_error($module = array(), $error_number, $title = "") {
            $html = array();
            if (count($module) == 0)
                return true;

            $html[] = '<div class="aiowaff-grid_4 aiowaff-error-using-module">';
            $html[] = '<div class="aiowaff-panel">';
            $html[] = '<div class="aiowaff-panel-header">';
            $html[] = '<span class="aiowaff-panel-title">';
            $html[] = __($title, $this->localizationName);
            $html[] = '</span>';
            $html[] = '</div>';
            $html[] = '<div class="aiowaff-panel-content">';

            $error_msg = isset($module[$module['alias']]['errors'][$error_number]) ? $module[$module['alias']]['errors'][$error_number] : '';

            $html[] = '<div class="aiowaff-error-details">' . ( $error_msg ) . '</div>';
            $html[] = '</div>';
            $html[] = '</div>';
            $html[] = '</div>';

            return implode("\n", $html);
        }

        public function convert_to_button($button_params = array()) {
            $button = array();
            $button[] = '<a';
            if (isset($button_params['url']))
                $button[] = ' href="' . ( $button_params['url'] ) . '"';

            if (isset($button_params['target']))
                $button[] = ' target="' . ( $button_params['target'] ) . '"';

            $button[] = ' class="aiowaff-button';

            if (isset($button_params['color']))
                $button[] = ' ' . ( $button_params['color'] ) . '';

            $button[] = '"';
            $button[] = '>';

            $button[] = $button_params['title'];

            $button[] = '</a>';

            return implode("", $button);
        }

        public function load_terms($taxonomy) {
            global $wpdb;

            $query = "SELECT DISTINCT t.name FROM {$wpdb->terms} AS t INNER JOIN {$wpdb->term_taxonomy} as tt ON tt.term_id = t.term_id WHERE 1=1 AND tt.taxonomy = '" . esc_sql($taxonomy) . "'";
            $result = $wpdb->get_results($query, OBJECT);
            return $result;
        }

        public function get_current_page_url() {
            $url = (!empty($_SERVER['HTTPS'])) ?
                    "https://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] :
                    "http://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']
            ;
            return $url;
        }

        public function get_country_perip_external($return_field = 'country') {
            //if ( isset($_COOKIE["aiowaff_country"]) && !empty($_COOKIE["aiowaff_country"]) ) {
            //	return unserialize($_COOKIE["aiowaff_country"]);
            //}

            $provider = $this->cur_provider;

            //unset($_SESSION["aiowaff_country"]); // for debugging...
            if (isset($_SESSION["aiowaff_country"], $_SESSION["aiowaff_country"]["$provider"]) && !empty($_SESSION["aiowaff_country"]["$provider"])) {
                if ($return_field == 'country') {
                    return unserialize($_SESSION["aiowaff_country"]["$provider"]);
                }
            }

            $ip = $this->get_client_ip();

            $config = $this->amz_settings;

            $paths = array(
                'api.hostip.info' => 'http://api.hostip.info/country.php?ip={ipaddress}',
                'www.geoplugin.net' => 'http://www.geoplugin.net/json.gp?ip={ipaddress}',
                'www.telize.com' => 'http://www.telize.com/geoip/{ipaddress}',
                'ipinfo.io' => 'http://ipinfo.io/{ipaddress}/geo',
            );

            $service_used = 'www.geoplugin.net';
            if (isset($config['services_used_forip']) && !empty($config['services_used_forip'])) {
                $service_used = $config['services_used_forip'];
            }

            $country = '';
            if ($service_used == 'local_csv') { // local csv file with ip lists
                // read csv hash (string with ip from list)
                $csv_hash = file_get_contents($this->cfg['paths']['plugin_dir_path'] . 'assets/GeoIPCountryWhois-hash.csv');
                $csv_hash = explode(',', $csv_hash);

                // read csv full (ip from, ip to, country)
                $csv_full = file_get_contents($this->cfg['paths']['plugin_dir_path'] . 'assets/GeoIPCountryWhois-full.csv');
                $csv_full = explode(PHP_EOL, $csv_full);

                //var_dump('<pre>',count($csv_hash), count($csv_full),'</pre>');
                //var_dump('<pre>',$csv_hash, $csv_full,'</pre>');

                $ip2number = $this->ip2number($ip);
                //var_dump('<pre>', $ip, $ip2number, '</pre>');

                $ipHashIndex = $this->binary_search($ip2number, $csv_hash, array($this, 'binary_search_cmp'));
                if ($ipHashIndex < 0) { // verify if is between (ip_from, ip_to) of csv row
                    $ipHashIndex = abs($ipHashIndex);
                    $ipFullRow = $csv_full["$ipHashIndex"];
                    $csv_row = explode(',', $ipFullRow);
                    if ($ip2number >= $csv_row[0] && $ip2number <= $csv_row[1]) {
                        $country = $csv_row[2];
                    }
                } else { // exact match in the list as ip_from of csv row
                    $ipFullRow = $csv_full["$ipHashIndex"];
                    $country = end(explode(',', $ipFullRow));
                }

                if (empty($country)) {
                    //$main_aff_site = $this->main_aff_site();
                    //$country = strtoupper(str_replace(".", '', $main_aff_site));
                    $country = 'NOT-FOUND';
                }
                $country = strtoupper($country);

                //var_dump('<pre>', $ipHashIndex, $ipFullRow, $country, '</pre>');
                //echo __FILE__ . ":" . __LINE__;die . PHP_EOL;
            } else { // external service
                $service_url = $paths["$service_used"];
                $service_url = str_replace('{ipaddress}', $ip, $service_url);

                $get_user_location = wp_remote_get($service_url);
                if (isset($get_user_location->errors)) {
                    //$main_aff_site = $this->main_aff_site();
                    //$country = strtoupper(str_replace(".", '', $main_aff_site));
                    $country = 'NOT-FOUND';
                } else {
                    $country = $get_user_location['body'];
                    switch ($service_used) {
                        case 'api.hostip.info':
                            break;

                        case 'www.geoplugin.net':
                            $country = json_decode($country);
                            $country = strtoupper($country->geoplugin_countryCode);
                            break;

                        case 'www.telize.com':
                            $country = json_decode($country);
                            $country = strtoupper($country->country_code);
                            break;

                        case 'ipinfo.io':
                            $country = json_decode($country);
                            $country = strtoupper($country->country);
                            break;

                        default:
                            break;
                    }
                }
            }

            if ($return_field == 'country') {
                $user_country = $this->amzForUser($country);
                //var_dump('<pre>',$user_country,'</pre>');
                //$this->cookie_set(array(
                //	'name'			=> 'aiowaff_country',
                //	'value'			=> serialize($user_country),
                //	'expire_sec'	=> strtotime( '+30 days' ) // time() + 604800, // 1 hour = 3600 || 1 day = 86400 || 1 week = 604800
                //));
                $_SESSION['aiowaff_country']["$provider"] = serialize($user_country);
                return $user_country;
            }
        }

        public function lang_init() {
            load_plugin_textdomain($this->alias, false, $this->cfg['paths']["plugin_dir_path"] . '/languages/');
        }

        public function delete_zeropriced_products_all($retType = 'die') {
            $key = '_amzASIN';
            $_key = $key;
            if ($_key == '_amzASIN')
                $key = '_aiowaff_prodid';

            //$prod_id = $_REQUEST['pid'];
            $ret = array();
            $args = array();
            $args['post_type'] = 'product';

            $args['meta_key'] = $key;
            $args['meta_value'] = '';
            $args['meta_compare'] = '!=';

            // show all posts
            //$args['fields'] = 'ids';
            $args['posts_per_page'] = '-1';

            $loop = new WP_Query($args);
            $cc = 0;
            $ret = array();
            while ($loop->have_posts()) : $loop->the_post();
                global $post;

                $post = (int) $post->ID;

                $sale_price = get_post_meta($post, '_sale_price', true);
                $regular_price = get_post_meta($post, '_regular_price', true);
                $price = get_post_meta($post, '_price', true);

                if ($regular_price == '' && $price == '') {
                    $cc++;
                    //if regular price is not set or it`s zero, put the post into trash 
                    wp_trash_post($post);
                }
            endwhile;

            $ret['status'] = 'valid';
            if ($cc == 0) {
                $ret['msg_html'] = 'No zero priced posts found.';
            } else {
                $ret['msg_html'] = $cc . ' posts moved to trash!';
            }

            if ($retType == 'die')
                die(json_encode($ret));
            else
                return $ret;
        }

        public function cookie_set($cookie_arr = array()) {
            extract($cookie_arr);
            if (!isset($path))
                $path = '/';
            if (!isset($domain))
                $domain = ($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false;
            $stat = setcookie($name, $value, $expire_sec, $path, $domain);
            return $stat;
        }

        public function cookie_del($cookie_arr = array()) {
            extract($cookie_arr);
            if (!isset($path))
                $path = '/';
            if (!isset($domain))
                $domain = ($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false;
            setcookie($name, null, strtotime('-1 day'), $path, $domain);
        }

        public function is_woocommerce_installed() {
            if (in_array('envato-wordpress-toolkit/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) || in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) || is_multisite()) {
                return true;
            } else {
                $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
                if (!empty($active_plugins) && is_array($active_plugins)) {
                    foreach ($active_plugins as $key => $val) {
                        if (($status = preg_match('/^woocommerce[^\/]*\/woocommerce\.php$/imu', $val)) !== false && $status > 0) {
                            return true;
                        }
                    }
                }
                return false;
            }
        }

        public function get_client_ip() {
            $ipaddress = '';

            if ($_SERVER['REMOTE_ADDR'])
                $ipaddress = $_SERVER['REMOTE_ADDR'];
            else if ($_SERVER['HTTP_CLIENT_IP'])
                $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
            else if ($_SERVER['HTTP_X_FORWARDED'])
                $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
            else if ($_SERVER['HTTP_FORWARDED_FOR'])
                $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
            else if ($_SERVER['HTTP_FORWARDED'])
                $ipaddress = $_SERVER['HTTP_FORWARDED'];
            else if ($_SERVER['HTTP_X_FORWARDED_FOR'])
                $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];

            return $ipaddress;
        }

        public function ip2number($ip) {
            $long = ip2long($ip);
            if ($long == -1 || $long === false) {
                return false;
            }
            return sprintf("%u", $long);
        }

        public function verify_module_status($module = '') {
            if (empty($module))
                return false;

            $mod_active = get_option('aiowaff_module_' . $module);
            if ($mod_active != 'true')
                return false; //module is inactive!
            return true;
        }

        public function last_update_date($format = false, $last_date = false, $year = false) {
            if ($last_date === '')
                return $last_date;
            if ($last_date === false)
                $last_date = time();
            if (!$format)
                return $last_date;

            $date_format = 'D j M / H.i';
            if ($year)
                $date_format = 'D j M Y / H.i';
            return date($date_format, $last_date); // Mon 2 Feb / 13.21
        }

        public function set_content_type($content_type) {
            return 'text/html';
        }

        public function __category_nice_name($categ_name) {
            $ret = $categ_name;

            $special = array('DVD' => 'DVD', 'MP3Downloads' => 'MP3 Downloads', 'PCHardware' => 'PC Hardware', 'VHS' => 'VHS');
            if (!in_array($categ_name, array_keys($special))) {
                $ret = preg_replace('/([A-Z])/', ' $1', $categ_name);
            } else {
                $ret = $special["$categ_name"];
            }
            return $ret;
        }

        // This function works exactly how encodeURIComponent is defined:
        // encodeURIComponent escapes all characters except the following: alphabetic, decimal digits, - _ . ! ~ * ' ( )
        public function encodeURIComponent($str) {
            $revert = array('%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')');
            return strtr(rawurlencode($str), $revert);
        }

        /**
         * Parameters: 
         *   $key - The key to be searched for.
         *   $list - The sorted array. 
         *   $compare_func - A user defined function for comparison. Same definition as the one in usort
         *   $low - First index of the array to be searched (local parameters).
         *   $high - Last index of the array to be searched (local parameters). 
         *
         * Return:
         *   index of the search key if found, otherwise return -(insert_index + 1). 
         *   insert_index is the index of greatest element that is smaller than $key or count($list) if $key
         *   is larger than all elements in the array.
         * 
         * License: Feel free to use the code if you need it.
         */
        public function binary_search($key, array $list, $compare_func) {
            $low = 0;
            $high = count($list) - 1;

            while ($low <= $high) {
                $mid = (int) (($high - $low) / 2) + $low; // could use php ceil function
                $cmp = call_user_func($compare_func, $list[$mid], $key);

                if ($cmp < 0) {
                    $low = $mid + 1;
                } else if ($cmp > 0) {
                    $high = $mid - 1;
                } else {
                    return $mid;
                }
            }
            return -($low - 1);
        }

        public function binary_search_cmp($a, $b) {
            return ($a < $b) ? -1 : (($a > $b) ? 1 : 0);
        }

        /**
         * Insane Mode - Last Imports Stats / Duration
         */
        public function get_last_imports($what = 'all') {
            $ret = array();
            $cfg = get_option('aiowaff_insane_last_reports', array());

            $def = array(
                // duration in miliseconds
                'request_amazon' => 1000, // request product from amazon
                'request_cache' => 10, // request product from cache
                'last_product' => 1500, // product without the bellow options
                //'last_import_images'                => 1200, // add images to assets table
                'last_import_images_download' => 2500, // download images
                'last_import_variations' => 1500, // import variations
                'last_import_spin' => 650, // spin post content
                'last_import_attributes' => 2300, // import attributes
            );
            foreach ($def as $key => $val) {
                $def["$key"] = array(
                    'items' => array(
                        array('duration' => $val),
                    ),
                );
            }

            foreach ($def as $key => $val) {
                // default
                if (!isset($cfg["$key"], $cfg["$key"]['items']) || !is_array($cfg["$key"]['items']) || empty($cfg["$key"]['items'])) {

                    $cfg["$key"] = $def["$key"];
                }
            }
            foreach ($cfg as $key => $val) {

                $media = array();
                foreach ($val['items'] as $key2 => $val2) {

                    $duration = $val2['duration'];
                    if (isset($val2['nb_items']) && (int) $val2['nb_items'] > 0) {
                        $nb_items = (int) $val2['nb_items'];
                        $media[] = round($duration / $nb_items, 4);
                    } else {
                        $media[] = round($duration, 4);
                    }
                }
                $media = !empty($media) ? round(array_sum($media) / count($media), 4) : 0;

                $cfg["$key"]["media"] = array('duration' => $media);
            }

            $ret = $cfg;
            //var_dump('<pre>', $ret, '</pre>'); die('debug...'); 
            return $ret;
        }

        public function add_last_imports($what = 'all', $new = array()) {
            if ($what === 'all' || empty($new))
                return false;

            $max_last_keep = in_array($what, array('last_import_images_download', 'last_import_variations')) ? 10 : 5;
            $ret = array();
            $cfg = get_option('aiowaff_insane_last_reports', array());

            if (!isset($cfg["$what"], $cfg["$what"]['items']) || !is_array($cfg["$what"]['items'])) {

                $cfg["$what"] = array(
                    'items' => array()
                );
            }

            if (count($cfg["$what"]['items']) >= $max_last_keep) {
                array_shift($cfg["$what"]['items']); // remove oldes maintained log regarding import
            }
            // add new latest log regarding import
            $cfg["$what"]['items'][] = $new;

            update_option('aiowaff_insane_last_reports', $cfg);
        }

        public function timer_start() {
            $this->timer->start();
        }

        public function timer_end($debug = false) {
            $this->timer->end($debug);
            $duration = $this->timer->getRenderTime(1, 0, false);
            return $duration;
        }

        public function format_duration($duration, $precision = 1) {
            $prec = $this->timer->getUnit($precision);
            $ret = $duration . ' ' . $prec;
            $ret = '<i>' . $ret . '</i>';
            return $ret;
        }

        public function save_amazon_request_time() {
            $time = microtime(true);
            update_option('aiowaff_last_amazon_request_time', $time);
            return true;
        }

        public function verify_amazon_request_rate($do_pause = true) {
            $ret = array('status' => 'valid'); // valid = no need for pause! 

            $rate = isset($this->amz_settings['amazon_requests_rate']) ? $this->amz_settings['amazon_requests_rate'] : 1;
            $rate = (int) $rate;
            $rate_milisec = $rate > 1 ? 1000 / $rate : 1000; // interval between requests in miliseconds
            $rate_milisec = floatval($rate_milisec);

            $current = microtime(true);
            $last = get_option('aiowaff_last_amazon_request_time', 0);
            $elapsed = round(($current - $last) * pow(10, 3), 0); // time elapsed from the last amazon requests
            // we may need to pause
            if ($elapsed < $rate_milisec) {
                if ($do_pause) {
                    $pause_microsec = ( $rate_milisec - $elapsed ) + 30; // here is in miliseconds - add 30 miliseconds to be sure
                    $pause_microsec = $pause_microsec * 1000; // pause in microseconds
                    usleep($pause_microsec);
                }
            }
            return $ret;
        }

        /**
         * cURL / Send http requests with curl
         */
        public static function curl($url, $input_params = array(), $output_params = array(), $debug = false) {
            $ret = array('status' => 'invalid', 'http_code' => 0, 'data' => '');

            // build curl options
            $ipms = array_replace_recursive(array(
                'userpwd' => false,
                'htaccess' => false,
                'post' => false,
                'postfields' => array(),
                'httpheader' => false,
                'verbose' => false,
                'ssl_verifypeer' => false,
                'ssl_verifyhost' => false,
                'httpauth' => false,
                'failonerror' => false,
                'returntransfer' => true,
                'binarytransfer' => false,
                'header' => false,
                'cainfo' => false,
                'useragent' => false,
                    ), $input_params);
            extract($ipms);

            $opms = array_replace_recursive(array(
                'resp_is_json' => false,
                'resp_add_http_code' => false,
                'parse_headers' => false,
                    ), $output_params);
            extract($opms);

            //var_dump('<pre>', $ipms, $opms, '</pre>'); die('debug...'); 
            // begin curl
            $url = trim($url);
            if (empty($url))
                return (object) $ret;

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);

            if (!empty($userpwd)) {
                curl_setopt($curl, CURLOPT_USERPWD, $userpwd);
            }
            if (!empty($htaccess)) {
                $url = preg_replace("/http(|s):\/\//i", "http://" . $htaccess . "@", $url);
            }
            if (!$post && !empty($postfields)) {
                $url = $url . "?" . http_build_query($postfields);
            }

            if ($post) {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $postfields);
            }

            if (!empty($httpheader)) {
                curl_setopt($curl, CURLOPT_HTTPHEADER, $httpheader);
            }

            curl_setopt($curl, CURLOPT_VERBOSE, $verbose);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $ssl_verifypeer);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $ssl_verifyhost);
            if ($httpauth !== false)
                curl_setopt($curl, CURLOPT_HTTPAUTH, $httpauth);
            curl_setopt($curl, CURLOPT_FAILONERROR, $failonerror);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, $returntransfer);
            curl_setopt($curl, CURLOPT_BINARYTRANSFER, $binarytransfer);
            curl_setopt($curl, CURLOPT_HEADER, $header);
            if ($cainfo !== false)
                curl_setopt($curl, CURLOPT_CAINFO, $cainfo);
            if ($useragent !== false)
                curl_setopt($curl, CURLOPT_USERAGENT, $useragent);
            if ($timeout !== false)
                curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);

            $data = curl_exec($curl);
            $http_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

            $ret = array_merge($ret, array('http_code' => $http_code));
            if ($debug) {
                $ret = array_merge($ret, array('debug_details' => curl_getinfo($curl)));
            }
            if ($data === false || curl_errno($curl)) { // error occurred
                $ret = array_merge($ret, array(
                    'data' => curl_errno($curl) . ' : ' . curl_error($curl)
                ));
            } else { // success
                if ($parse_headers) {
                    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
                    $headers = self::__parse_headers(substr($data, 0, $header_size)); // response begin with the headers
                    $data = substr($data, $header_size);
                    $ret = array_merge($ret, array('headers' => $headers));
                }

                // Add the status code to the json data, useful for error-checking
                if ($resp_add_http_code && $resp_is_json) {
                    $data = preg_replace('/^{/', '{"http_code":' . $http_code . ',', $data);
                }

                $ret = array_merge($ret, array(
                    'status' => 'valid',
                    'data' => $data
                ));
            }

            curl_close($curl);
            return $ret;
        }

        private static function __parse_headers($headers) {
            if (!is_array($headers)) {
                $headers = explode("\r\n", $headers);
            }
            $ret = array();
            foreach ($headers as $header) {
                $header = explode(":", $header, 2);
                if (count($header) == 2) {
                    $ret[$header[0]] = trim($header[1]);
                }
            }
            return $ret;
        }

        /**
         * 2015, October fixes including attributes after woocommerce version 2.4.0!
         */
        public function cleanValue($value) {
            // Format Camel Case
            //$value = trim( preg_replace('/([A-Z])/', ' $1', $value) );
            // Clean / from value
            $value = trim(preg_replace('/(\/)/', '-', $value));
            return $value;
        }

        public function cleanTaxonomyName($value, $withPrefix = true) {
            $ret = $value;

            // Sanitize taxonomy names. Slug format (no spaces, lowercase) - uses sanitize_title
            if ($withPrefix) {
                $ret = wc_attribute_taxonomy_name($value); // return 'pa_' . $value
            } else {
                $ret = woocommerce_sanitize_taxonomy_name($value); // return $value
            }
            $limit_max = $withPrefix ? 32 : 29; // 29 = 32 - strlen('pa_')
            // limit to 32 characters (database/ table wp_term_taxonomy/ field taxonomy/ is limited to varchar(32) )
            return substr($ret, 0, $limit_max);

            return $ret;
        }

        public function get_woocommerce_version() {
            $ver = '';
            $is_found = false;

            // try to find version
            if (!$is_found && defined('WC_VERSION')) {
                $ver = WC_VERSION;
                $is_found = true;
            }

            if (!$is_found) {
                global $woocommerce;
                if (is_object($woocommerce) && isset($woocommerce->version) && !empty($woocommerce->version)) {
                    $ver = $woocommerce->version;
                    $is_found = true;
                }
            }

            if (!$is_found) {
                // If get_plugins() isn't available, require it
                if (!function_exists('get_plugins')) {
                    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                }

                foreach (array('envato-wordpress-toolkit', 'woocommerce') as $folder) {
                    // Create the plugins folder and file variables
                    $plugin_folder = get_plugins('/' . $folder);
                    $plugin_file = 'woocommerce.php';

                    // If the plugin version number is set, return it 
                    if (isset($plugin_folder[$plugin_file]['Version']) && !empty($plugin_folder[$plugin_file]['Version'])) {

                        $ver = $plugin_folder[$plugin_file]['Version'];
                        $is_found = true;
                        break;
                    }
                }
            }
            return $ver;
        }

        public function force_woocommerce_product_version($ver_prod, $ver_min = '2.4.0', $ver_ret = false) {
            // min version compare
            $ret = $ver_prod;
            if (version_compare($ver_prod, $ver_min, "<")) {
                $ret = $ver_ret ? $ver_ret : $ver_min;
            }
            return $ret;
        }

        public function get_main_settings($provider = 'all') {
            $amz_settings = $this->amz_settings;
            $providers = array(
                'amazon' => array(
                    'title' => __('Amazon Settings', $this->localizationName),
                    'mandatory' => array('AccessKeyID', 'SecretAccessKey', 'country', 'main_aff_id'),
                    'keys' => array(
                        'AccessKeyID' => array(
                            'title' => __('Access Key ID', $this->localizationName),
                            'value' => '',
                        ),
                        'SecretAccessKey' => array(
                            'title' => __('Secret Access Key', $this->localizationName),
                            'value' => '',
                        ),
                        'country' => array(
                            'title' => __('Amazon location', $this->localizationName),
                            'value' => '',
                        ),
                        'main_aff_id' => array(
                            'title' => __('Main Affiliate ID', $this->localizationName),
                            'value' => '',
                        ),
                        'AffiliateID' => array(
                            'title' => __('Affiliate IDs', $this->localizationName),
                            'value' => '',
                        ),
                    ),
                ),
                'ebay' => array(
                    'title' => __('EBay Settings', $this->localizationName),
                    'mandatory' => array('ebay_DEVID', 'ebay_AppID', 'ebay_CertID', 'ebay_country', 'ebay_main_aff_id'),
                    'keys' => array(
                        'ebay_DEVID' => array(
                            'title' => __('DEVID', $this->localizationName),
                            'value' => '',
                        ),
                        'ebay_AppID' => array(
                            'title' => __('AppID', $this->localizationName),
                            'value' => '',
                        ),
                        'ebay_CertID' => array(
                            'title' => __('CertID', $this->localizationName),
                            'value' => '',
                        ),
                        'ebay_country' => array(
                            'title' => __('Ebay location', $this->localizationName),
                            'value' => '',
                        ),
                        'ebay_main_aff_id' => array(
                            'title' => __('Main Affiliate ID', $this->localizationName),
                            'value' => '',
                        ),
                        'ebay_AffiliateID' => array(
                            'title' => __('Affiliate campid IDs', $this->localizationName),
                            'value' => '',
                        ),
                    ),
                ),
                'alibaba' => array(
                    'title' => __('Alibaba Settings', $this->localizationName),
                    'mandatory' => array('alibaba_AppKey', 'alibaba_TrackingID', 'alibaba_DigitalSignature'),
                    'keys' => array(
                        'alibaba_AppKey' => array(
                            'title' => __('App Key', $this->localizationName),
                            'value' => '',
                        ),
                        'alibaba_TrackingID' => array(
                            'title' => __('Tracking ID', $this->localizationName),
                            'value' => '',
                        ),
                        'alibaba_DigitalSignature' => array(
                            'title' => __('Digital Signature', $this->localizationName),
                            'value' => '',
                        ),
                    ),
                ),
            );
            foreach ($providers as $pkey => $pval) {
                foreach ($pval['keys'] as $pkey2 => $pval2) {
                    if (isset($amz_settings["$pkey2"])) {
                        $pval2 = $amz_settings["$pkey2"];
                        $providers["$pkey"]['keys']["$pkey2"]['value'] = $pval2;

                        if (preg_match('/(country|main_aff_id)/iu', $pkey2)) {
                            $obj = is_object($this->get_ws_object($pkey)) ? $this->get_ws_object($pkey) : null;

                            if (!is_null($obj)) {
                                $providers["$pkey"]['keys']["$pkey2"]['value'] = $obj->get_country_name(
                                        $pval2, str_replace('ebay_', '', $pkey2)
                                );
                            }
                        }
                    }
                }
            }
            //var_dump('<pre>', $providers, '</pre>'); die('debug...');

            if ($provider != 'all') {
                return isset($providers["$provider"]) ? $providers["$provider"] : array();
            }
            return $providers;
        }

        public function verify_mandatory_settings($provider) {
            $ret = array(
                'status' => 'invalid',
                'fields' => array(),
                'fields_title' => array(),
            );

            $module_settings = $this->get_main_settings($provider);
            if (empty($module_settings))
                return array_merge($ret, array());

            $mandatory = isset($module_settings['mandatory']) ? $module_settings['mandatory'] : array();
            if (empty($mandatory))
                return array_merge($ret, array('status' => 'valid'));

            $module_mandatoryFields = array();
            $fields = array();
            foreach ($mandatory as $field) {

                if (isset($module_settings['keys']["$field"]['title'])) {
                    $fields["$field"] = $module_settings['keys']["$field"]['title'];
                }

                $module_mandatoryFields["$field"] = false;
                if (isset($module_settings['keys']["$field"]['value']) && !empty($module_settings['keys']["$field"]['value'])) {

                    $module_mandatoryFields["$field"] = true;
                }
            }

            $mandatoryValid = true;
            foreach ($module_mandatoryFields as $k => $v) {
                if (!$v) {
                    $mandatoryValid = false;
                    break;
                }
            }
            return array_merge($ret, array(
                'status' => $mandatoryValid ? 'valid' : 'invalid',
                'fields' => array_keys($fields),
                'fields_title' => array_values($fields),
            ));
        }

        public function build_amz_settings($new = array()) {
            if (!empty($new) && is_array($new)) {
                $this->amz_settings = array_replace_recursive($this->amz_settings, $new);
            }
            // plugin doesn't use the cart!			
            $this->amz_settings['onsite_cart'] = 'no';
            $this->amz_settings['90day_cookie'] = 'no';
            $this->amz_settings['cross_selling'] = 'no';

            return $this->amz_settings;
        }

        /**
         * Octomber 2015 - new plugin functions
         */
        public function get_ws_prefixes($ws = 'all') {
            $wslist = array(
                'amazon' => 'amz',
                'envato' => 'env',
                'alibaba' => 'ali',
                'ebay' => 'eby',
            );
            return $ws == 'all' ? $wslist : ( isset($wslist["$ws"]) ? $wslist["$ws"] : false );
        }

        public function get_ws_status($ws = 'all') {
            $wslist = array(
                'amazon' => true,
                'envato' => true,
                'alibaba' => true,
                'ebay' => true,
            );
            return $ws == 'all' ? $wslist : ( isset($wslist["$ws"]) ? $wslist["$ws"] : false );
        }

        public function get_post_meta($post_id, $key = '', $single = false, $withPrefix = true) {
            $_key = $key;
            if ($_key == '_amzASIN')
                $key = '_aiowaff_prodid';
            $ret = get_post_meta($post_id, $key, $single);
            if (!$withPrefix && ($_key == '_amzASIN')) {
                $wslist = $this->get_ws_prefixes();
                foreach ($wslist as $wsprefix) {
                    $ret = str_replace($wsprefix . '-', '', $ret);
                }
            }
            return $ret;
        }

        public function get_product_by_wsid($wsid) {
            global $wpdb;

            $query = "
SELECT a.ID, a.post_title FROM {$wpdb->posts} AS a LEFT JOIN {$wpdb->postmeta} AS b ON a.ID = b.post_id
 WHERE 1=1
 AND b.meta_key = '_aiowaff_prodid' AND b.meta_value = '" . esc_sql($wsid) . "'
 AND !ISNULL(b.meta_id)
;";
            $result = $wpdb->get_results($query, ARRAY_A);
            return (isset($result[0]) ? $result[0] : $result);
        }

        /**
         * Call Example
          $args = array(
          'post_title' 	=> $retProd['Title'],
          'post_status' 	=> $default_import,
          'post_content' 	=> $desc,
          'post_excerpt' 	=> $excerpt,
          'post_type' 	=> 'product',
          'menu_order' 	=> 0,
          'post_author' 	=> 1
          );
         */
        public function get_product_by_args($args) {
            global $wpdb;

            $args = array_merge(array(
                'post_title' => '',
                'post_status' => 'publish',
                'post_content' => '',
                'post_excerpt' => '',
                'post_type' => 'product',
                'menu_order' => 0,
                'post_author' => 1
                    ), $args);

            //$result = $wpdb->get_row("SELECT * FROM " . ( $wpdb->prefix ) . "posts WHERE 1=1 and post_status = '" . ( $args['post_status'] ) . "' and post_title = '" .  ( $args['post_title'] )  . "'", 'ARRAY_A');
            $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . ( $wpdb->prefix ) . "posts WHERE 1=1 and post_type IN ('product', 'product_variation') and post_status = '" . ( $args['post_status'] ) . "' and post_title = %s", $args['post_title']), 'ARRAY_A');
            if (count($result) > 0) {
                return $result;
            }
            return false;
        }

        // get webservice object
        public function get_ws_object($provider, $what = 'helper') {
            $arr = array(
                'generic' => array(
                    'helper' => $this->genericHelper,
                    'ws' => null,
                ),
                'amazon' => array(
                    'helper' => $this->amzHelper,
                    'ws' => is_object($this->amzHelper) ? $this->amzHelper->aaAmazonWS : null,
                ),
                'alibaba' => array(
                    'helper' => $this->alibabaHelper,
                    'ws' => is_object($this->alibabaHelper) ? $this->alibabaHelper->aaAlibabaWS : null,
                ),
                'envato' => array(
                    'helper' => $this->envatoHelper,
                    'ws' => is_object($this->envatoHelper) ? $this->envatoHelper->aaEnvatoWS : null,
                ),
                'ebay' => array(
                    'helper' => $this->ebayHelper,
                    'ws' => is_object($this->ebayHelper) ? $this->ebayHelper->aaEbayWS : null,
                ),
            );
            return $arr["$provider"]["$what"];
        }

        public function prodid_get_provider_alias($id) {
            $_id = explode('-', $id);
            return count($_id) > 1 ? $_id[0] : 'zzz';
        }

        public function prodid_get_asin($id) {
            $_id = explode('-', $id);
            return count($_id) > 1 ? $_id[1] : '9999999';
        }

        public function prodid_get_provider($alias) {
            $wslist = $this->get_ws_prefixes();
            foreach ($wslist as $key => $wsprefix) {
                if ($alias == $wsprefix) {
                    return $key;
                }
            }
            return '';
        }

        public function prodid_set($id, $provider, $what) {
            $ret = array();
            $alias = $this->get_ws_prefixes($provider);

            if (empty($id))
                return $id;
            $isa = is_array($id) ? true : false;

            if (!$isa) {
                $id = array($id);
            }
            foreach ($id as $key => $val) {
                if (empty($val)) {
                    $ret["$key"] = $val;
                    continue;
                }
                if ('add' == $what) {
                    $ret["$key"] = $val;
                    if (!preg_match('/^(' . $alias . ').*/imu', $val, $m)) {
                        $ret["$key"] = $alias . '-' . $val;
                    }
                } else if ('sub' == $what) {
                    $ret["$key"] = str_replace($alias . '-', '', $val);
                }
            }
            if (!$isa) {
                return $ret[0];
            }
            return $ret;
        }

        public function set_product_meta_asset($post_id, $metas = array()) {
            foreach ($metas as $key => $val) {
                update_post_meta($post_id, $key, $val);
            }
        }

        public function multi_implode_keyval($array, $glue) {
            $ret = '';

            foreach ($array as $key => $item) {
                if (is_array($item)) {
                    $ret .= $this->multi_implode($item, $glue) . $glue;
                } else {
                    $ret .= ($key . ': ' . $item) . $glue;
                }
            }

            $ret = substr($ret, 0, 0 - strlen($glue));

            return $ret;
        }

    }

}

if (!function_exists('array_replace_recursive')) {

    function array_replace_recursive($base, $replacements) {
        foreach (array_slice(func_get_args(), 1) as $replacements) {
            $bref_stack = array(&$base);
            $head_stack = array($replacements);

            do {
                end($bref_stack);

                $bref = &$bref_stack[key($bref_stack)];
                $head = array_pop($head_stack);

                unset($bref_stack[key($bref_stack)]);

                foreach (array_keys($head) as $key) {
                    if (isset($key, $bref, $bref[$key], $head[$key]) && is_array($bref[$key]) && is_array($head[$key])) {
                        $bref_stack[] = &$bref[$key];
                        $head_stack[] = $head[$key];
                    } else {
                        $bref[$key] = $head[$key];
                    }
                }
            } while (count($head_stack));
        }

        return $base;
    }

}