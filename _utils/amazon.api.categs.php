<?php
if ( !defined('ABSPATH') ) {
	$absolute_path = __FILE__;
	$path_to_file = explode( 'wp-content', $absolute_path );
	$path_to_wp = $path_to_file[0];

	/** Set up WordPress environment */
	require_once( $path_to_wp.'/wp-load.php' );
} else {
	die('wrong path!');
}
  
$__allowedCountries = array('CA','CN','DE','ES','FR','IN','IT','JP','UK','US','BR','MX');

function __generateAssetsFiles( $write=true, $type='all', $country='all' ) {
	if ( $type == 'all' ) {
		__assets_search_sort( $write, 'sortvalues', $country );
		__assets_search_sort( $write, 'searchindexParam', $country );
		__assets_browsenodes( $write );
	} else if ( in_array($type, array('sortvalues', 'searchindexParam')) ) {
		__assets_search_sort( $write, $type, $country );
	} else if ( $type == 'browsenodes' ) {
		__assets_browsenodes( $write );
	}
}

function __assets_search_sort( $write=true, $type='all', $ccountry='all' ) {
	global $aiowaff, $__allowedCountries;

	require('amazon.api.categs.lib.php');
	if ( empty($assets) ) return false;
	
	$ret = array();
	
	if ( !empty($ccountry) && $ccountry!='all' ) {
		$assets = array("$ccountry" => $assets["$ccountry"]);
	}

	if ( empty($assets) ) return;
	foreach ($assets as $country => $categs) {
		
		if ( empty($categs) ) continue 1;
		foreach ($categs as $title => $params) {

			if ( $type == 'sortvalues' ) $params = $params[2];
			else if ( $type == 'searchindexParam' ) $params = $params[1];

			if ( empty($params) ) continue 1;
			$params = implode(':', explode(',', $params));
 
			$ret["$country"][] = implode(',', array($title, $params));
		}
	}

	ksort($ret);
	//var_dump('<pre>', $ret, '</pre>');
	
	if (!$write) return $ret;

	if ( !empty($ccountry) && $ccountry!='all' ) {
		$file_name = $aiowaff->cfg['paths']['plugin_dir_path'] . 'assets/amazon/' . $type . '-' . strtoupper($ccountry) . '.csv';
		__assets_writefile($file_name, $ret["$ccountry"]);
	} else {
		foreach ( $__allowedCountries as $country ) {
			$file_name = $aiowaff->cfg['paths']['plugin_dir_path'] . 'assets/amazon/' . $type . '-' . strtoupper($country) . '.csv';
			__assets_writefile($file_name, $ret["$country"]);
		}
	}
	return $ret;
}

function __assets_browsenodes( $write=true ) {
	global $aiowaff, $__allowedCountries;

	require('amazon.api.categs.lib.php');
	if ( empty($assets) ) return false;
	
	$ret = array();
	
	if ( empty($assets) ) return;
	foreach ($assets as $country => $categs) {
		
		if ( empty($categs) ) continue 1;
		foreach ($categs as $title => $params) {

			$params = $params[0];

			if ( empty($params) ) continue 1;
 
			$ret["$title"]["$country"] = $params;
		}
	}

	ksort($ret);
	//var_dump('<pre>', $ret, '</pre>');
  
	if ( empty($ret) ) return false;
	$retd = array();
	$retd[] = implode(',', array('', implode(',', $__allowedCountries)));
	foreach ($ret as $categ => $nodeid) {
		foreach ($__allowedCountries as $country) {
			if ( !isset($ret["$categ"]["$country"]) ) $ret["$categ"]["$country"] = '';
		}
		$tmp = array();
		foreach ($__allowedCountries as $country) {
			$tmp[] = $ret["$categ"]["$country"];
		}
		$retd[] = implode(',', array($categ, implode(',', $tmp)));
	}
	//var_dump('<pre>', $retd, '</pre>');
	
	if (!$write) return $retd;
	
	$file_name = $aiowaff->cfg['paths']['plugin_dir_path'] . 'assets/amazon/browsenodes.csv';
	__assets_writefile($file_name, $retd);
		
	return $retd;
}

function __assets_writefile($file_name, $content) {
	global $aiowaff;
	
	$content = implode("\r", $content);
	//var_dump('<pre>',$file_name, $content,'</pre>');  
	
	$has_wrote = $aiowaff->wp_filesystem->put_contents(
		$file_name, $content, FS_CHMOD_FILE
	);

	$has_wrote2 = false;
	if( !$has_wrote ){
		$has_wrote2 = file_put_contents( $file_name, $content );
	}
	
	$wrote_status = $has_wrote || $has_wrote2;
	
	echo '<div style="display: block;">' . $file_name .  '<span style="display: inline-block; margin-left: 20px; color: #' . ($wrote_status ? '00ff00' : 'ff0000') . '">' . ($wrote_status ? 'success' : 'error') . '</span></div>';
}

__generateAssetsFiles();
die('debug...');
