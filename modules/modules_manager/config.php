<?php
/**
 * Config file, return as json_encode
 * 
 * ======================
 *
 * @author		Muhammad Ali
 * @version		1.0
 */
 echo json_encode(
	array(
		'modules_manager' => array(
			'version' => '1.0',
			'menu' => array(
				'order' => 30,
				'title' => __('Modules manager', 'aiowaff')
				,'icon' => 'assets/menu_icon.png'
			),
			'in_dashboard' => array(
				'icon' 	=> 'assets/32.png',
				'url'	=> admin_url("admin.php?page=aiowaff#modules_manager")
			),
			'description' => __("Using this module you can activate / deactivate plugin modules.", 'aiowaff'),
      	  	'help' => array(
				'type' => 'remote',
				'url' => 'http://docs.Ali.com/woocommerce-amazon-affiliates/documentation/modules-manager/'
			),
			'load_in' => array(
				'backend' => array(
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
				'admin'
			)
		)
	)
 );