<?php
global $aiowaff;
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
		'insane_import' => array(
			'version' => '1.0',
			'menu' => array(
				'order' => 4,
				'show_in_menu' => false,
				'title' => 'Products Importer Mode',
				'icon' => 'assets/16_icon.png'
			),
			'in_dashboard' => array(
				'icon' 	=> 'assets/32_icon.png',
				'url'	=> admin_url("admin.php?page=aiowaff#!/insane_import")
			),
			'help' => array(
				'type' => 'remote',
				'url' => 'http://docs.Ali.com/products/All WP Store/'
			),
			'description' => "With this module you can import hundreds of ASIN codes using different ways.",
			'module_init' => 'init.php',
			'load_in' => array(
				'backend' => array(
					'admin.php?page=aiowaff_assets_download',
					'admin.php?page=aiowaff_insane_import',
					'admin-ajax.php'
				),
				'frontend' => false
			),
			'javascript' => array(
				'admin',
				'download_asset',
				'hashchange',
				'tipsy'
			),
			'css' => array(
				'admin',
				'tipsy'
			),
            'errors' => array(
                1 => __('
                    You configured Products Importer Mode incorrectly. See 
                    ' . ( $aiowaff->convert_to_button ( array(
                        'color' => 'white_blue aiowaff-show-docs-shortcut',
                        'url' => 'javascript: void(0)',
                        'title' => 'here'
                    ) ) ) . ' for more details on fixing it. <br />
                    Setup the Amazon config mandatory settings ( Access Key ID, Secret Access Key, Main Affiliate ID ) 
                    ' . ( $aiowaff->convert_to_button ( array(
                        'color' => 'white_blue',
                        'url' => admin_url( 'admin.php?page=aiowaff#!/amazon' ),
                        'title' => 'here'
                    ) ) ) . '
                    ', $aiowaff->localizationName),
                2 => __('
                    You don\'t have WooCommerce installed/activated! Please activate it:
                    ' . ( $aiowaff->convert_to_button ( array(
                        'color' => 'white_blue',
                        'url' => admin_url('plugin-install.php?tab=search&s=woocommerce&plugin-search-input=Search+Plugins'),
                        'title' => 'NOW'
                    ) ) ) . '
                    ', $aiowaff->localizationName),
                3 => __('
                    You don\'t have neither the SOAP library or cURL library installed! Please activate it!
                    ', $aiowaff->localizationName),
                4 => __('
                    You don\'t have the cURL library installed! Please activate it!
                    ', $aiowaff->localizationName)
            )
		)
	)
);