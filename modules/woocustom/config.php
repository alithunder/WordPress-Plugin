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
		'woocustom' => array(
			'version' => '0.1',
			'menu' => array(
			    'show_in_menu' => false,
				'order' => 3,
				'title' => 'Woocustom Functionality',
				'icon' => 'assets/16.png'
			),
			'description' => "Using this module you can make custom jobs to backend & frontend - related to woocommerce.",
			'module_init' => 'init.php',
			'load_in' => array(
				'backend' => array('@all'),
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