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
		'content_spinner' => array(
			'version' => '1.0',
			'menu' => array(
				'order' => 4,
				'show_in_menu' => false,
				'title' => 'Content Spinner',
				'icon' => 'assets/16_spinner.png'
			),
			'in_dashboard' => array(
				'icon' 	=> 'assets/32_spinner.png',
				'url'	=> admin_url("admin.php?page=aiowaff_content_spinner")
			),
			'help' => array(
				'type' => 'remote',
				'url' => 'http://docs.Ali.com/products/All WP Store/'
			),
			'description' => "Excellent On Page Optimization for Affiliate Products",
			'module_init' => 'init.php',
			'load_in' => array(
				'backend' => array(
					'admin.php?page=aiowaff_content_spinner',
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