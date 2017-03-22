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
		'price_select' => array(
			'version' => '1.0',
			'menu' => array(
				'order' => 4,
				'show_in_menu' => false,
				'title' => 'Price select',
				'icon' => 'assets/16_assets.png'
			),
			/*'in_dashboard' => array(
				'icon' 	=> 'assets/32_assets.png',
				'url'	=> admin_url("admin.php?page=aiowaff_price_select")
			),*/
			'help' => array(
				'type' => 'remote',
				'url' => 'http://docs.Ali.com/woocommerce-amazon-affiliates/documentation/price_select/'
			),
			'description' => "With this module you can manually select amazon products price.",
			'module_init' => 'init.php',
			'load_in' => array(
				'backend' => array(
					'post.php',
					'post-new.php',
					'admin-ajax.php',
					array('edit\.php\?(.*)post_type\=product')
				),
				'frontend' => true
			),
			'javascript' => array(
				'admin',
				'hashchange',
				'tipsy',
				'thickbox'
			),
			'css' => array(
				'admin',
				'tipsy'
			)
		)
	)
);