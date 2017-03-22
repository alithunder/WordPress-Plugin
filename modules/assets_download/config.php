<?php
/**
 * Config file, return as json_encode
 * 
 * =======================
 *
 * @author		Muhammad Ali
 * @version		1.0
 */
echo json_encode(
	array(
		'assets_download' => array(
			'version' => '1.0',
			'menu' => array(
				'order' => 4,
				'show_in_menu' => false,
				'title' => 'Assets download',
				'icon' => 'assets/16_assets.png'
			),
			'in_dashboard' => array(
				'icon' 	=> 'assets/32_assetsdwl.png',
				'url'	=> admin_url("admin.php?page=aiowaff_assets_download")
			),
			'help' => array(
				'type' => 'remote',
				'url' => 'http://docs.Ali.com/products/All WP Store/'
			),
			'description' => "Download assets for the products - this applies to all products, specially for products with lots of variations",
			'module_init' => 'init.php',
			'load_in' => array(
				'backend' => array(
					'admin.php?page=aiowaff_assets_download',
					'admin.php?page=aiowaff_asin_grabber',
					'admin-ajax.php'
				),
				'frontend' => false
			),
			'javascript' => array(
				'admin',
				'hashchange',
				'tipsy'
			),
			'css' => array(
				'admin',
				'tipsy'
			)
		)
	)
);