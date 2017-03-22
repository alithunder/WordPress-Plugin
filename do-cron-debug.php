<?php
if ( !defined('ABSPATH') ) {
    $absolute_path = __FILE__;
    $path_to_file = explode( 'wp-content', $absolute_path );
    $path_to_wp = $path_to_file[0];

    /** Set up WordPress environment */
    require_once( $path_to_wp.'/wp-load.php' );
    global $aiowaff;

    @ini_set('max_execution_time', 0);
    @set_time_limit(0); // infinte
    //aiowaff_SyncProducts_event();

    // SYNC...
    /*
    require_once( $path_to_wp.'/wp-content/plugins/wooallinone/modules/synchronization/init.php' );
    $sync = new wwcAmazonSyncronize($aiowaff);
    */

    /*
    $products = $sync->get_products();
    var_dump('<pre>', $products, '</pre>'); die('debug...');
    */

    /*
    $last_product = $sync->currentlist_last_product();
    var_dump('<pre>', $last_product, '</pre>'); die('debug...');
    */

    /*
    $cron_small_bulk = $sync->cron_small_bulk(array('recurrence' => 120));
    var_dump('<pre>', $cron_small_bulk, '</pre>'); die('debug...');
    */

    /*
    $cron_full_cycle = $sync->cron_full_cycle(array('recurrence' => 120));
    var_dump('<pre>', $cron_full_cycle, '</pre>'); die('debug...');
    */


    // CRONJOBS...
    /*
    require_once( $path_to_wp.'/wp-content/plugins/wooallinone/modules/cronjobs/init.php' );
    $cronjobs = new aiowaffCronjobs($aiowaff);
    */

    /*    
    var_dump('<pre>','first time','</pre>'); 
    $get_config = $cronjobs->get_config();
    foreach ($get_config as $cron_id => $cron) {
        //if ( !in_array($cron_id, array('unblock_crons')) ) continue 1;
        //if ( !in_array($cron_id, array('sync_products')) ) continue 1;
        if ( !in_array($cron_id, array('sync_products_cycle')) ) continue 1;
        //if ( !in_array($cron_id, array('assets_download')) ) continue 1;

        //$cronjobs->set_cron($cron_id, array('status' => 'new'));
        
        $cronjobs->run($cron_id);
        $status = $cronjobs->get_cron($cron_id);
        $status = $status['status'];
        var_dump('<pre>', $cron_id, $status, '</pre>');
    }

    var_dump('<pre>','second time','</pre>');  
    $get_config = $cronjobs->get_config();
    foreach ($get_config as $cron_id => $cron) {
        $status = $cronjobs->get_cron($cron_id);
        $status = $status['status'];
        var_dump('<pre>', $cron_id, $status, '</pre>');
    }

    echo __FILE__ . ":" . __LINE__;die . PHP_EOL;
    */


    // REPORT...
    /*
    require_once( $path_to_wp.'/wp-content/plugins/wooallinone/modules/report/init.php' );
    $report = new aiowaffReport($aiowaff);
    */
    
    /*
    $cronjob = $report->cronjob(array());
    var_dump('<pre>', $cronjob, '</pre>'); die('debug...');
    */

    
    // ASSETS DOWNLOAD...
    ///*
    require_once( $path_to_wp.'/wp-content/plugins/wooallinone/modules/assets_download/init.php' );
    $assets = new aiowaffAssetDownload();
    //*/
    
    ///*
    $cronjob = $assets->cronjob(array());
    var_dump('<pre>', $cronjob, '</pre>'); die('debug...');
    //*/
}