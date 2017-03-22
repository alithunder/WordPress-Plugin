<?php
/**
* Config file, return as json_encode
* 
* =======================
*
* @author		Muhammad Ali
* @version		1.0
*/
echo json_encode(array(
    'dashboard' => array(
        'version' => '1.0',
        'menu' => array(
            'order' => 1,
            'title' => 'Dashboard'
            ,'icon' => 'assets/16_dashboard.png'
        ),
        'description' => "Dashboard Area - Here you will find shortcuts to different areas of the plugin.",
        'help' => array(
			'type' => 'remote',
			'url' => 'http://docs.Ali.com/products/All WP Store/'
		),
        'module_init' => 'init.php',
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
				'admin',
				'tipsy'
			)
    )
));