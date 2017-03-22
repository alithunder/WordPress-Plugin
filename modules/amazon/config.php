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
		'amazon' => array(
			'version' => '0.1',
			'menu' => array(
				'order' => 3,
				'title' => 'Config',
				'icon' => 'assets/16_amzconfig.png'
			),
			'in_dashboard' => array(
				'icon' 	=> 'assets/32_amazonconfig.png',
				'url'	=> admin_url("admin.php?page=aiowaff#!/amazon")
			),
			'description' => "Configuration - mandatory fields - Amazon Secret Key, Access Key ID and Affiliate ID.",
			'help' => array(
				'type' => 'remote',
				'url' => 'http://docs.Ali.com/products/All WP Store/'
			),
			'module_init' => 'init.php',
			'load_in' => array(
				'backend' => array(
					'admin-ajax.php'
				),
				'frontend' => true
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