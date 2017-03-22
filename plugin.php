<?php
/*
Plugin Name:	All WP Store 
Plugin URI: 	
Description: 	We’ve combined all major affiliation programs : Amazon, eBay, Aliexpress Affiliates into one major Affiliation Wordpress Plugin! The All WP Store plugins allows you to mass import products from all affiliation programs in the same time! That way you can earn commissions from Amazon, eBay, Aliexpress Market simultaneously! 

Version: 		1.0
Author: 		Ali
Author URI:		
*/
! defined( 'ABSPATH' ) and exit;

// Derive the current path and load up aiowaff
$plugin_path = dirname(__FILE__) . '/';
if(class_exists('aiowaff') != true) {
    require_once($plugin_path . 'aa-framework/framework.class.php');

	// Initalize the your plugin
	$aiowaff = new aiowaff();

	// Add an activation hook
	register_activation_hook(__FILE__, array(&$aiowaff, 'activate'));
}

// load textdomain
add_action( 'plugins_loaded', 'wooallinone_load_textdomain' );

function wooallinone_load_textdomain() {  
	load_plugin_textdomain( 'wooallinone', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}