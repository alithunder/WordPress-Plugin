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
		'cronjobs' => array(
			'version' => '0.1',
			'menu' => array(
			    'show_in_menu' => false,
				'order' => 3,
				'title' => 'Cronjobs',
				'icon' => 'assets/16.png'
			),
            'in_dashboard' => array(
                'icon'  => 'assets/32.png',
                'url'   => admin_url("admin.php?page=aiowaff#!/cronjobs")
            ),
            'help' => array(
                'type' => 'remote',
                'url' => 'http://docs.Ali.com/products/All WP Store/'
            ),
			'description' => "Using this module you can view this plugin associated cronjobs.",
			'module_init' => 'cronjobs.core.php',
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