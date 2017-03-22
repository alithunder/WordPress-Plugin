<?php
// soap
if (extension_loaded('soap')) {
?>
<div class="aiowaff-message aiowaff-success">
	SOAP extension installed on server
</div>
<?php
}else{
?>
<div class="aiowaff-message aiowaff-error">
	SOAP extension not installed on your server, please talk to your hosting company and they will install it for you.
</div>
<?php
}

// Woocommerce
if( class_exists( 'Woocommerce' ) ){
?>
<div class="aiowaff-message aiowaff-success">
	 WooCommerce plugin installed
</div>
<?php
}else{
?>
<div class="aiowaff-message aiowaff-error">
	WooCommerce plugin not installed, in order the product to work please install WooCommerce wordpress plugin.
</div>
<?php
}

// curl
if ( function_exists('curl_init') ) {
?>
<div class="aiowaff-message aiowaff-success">
	cURL extension installed on server
</div>
<?php
}else{
?>
<div class="aiowaff-message aiowaff-error">
	cURL extension not installed on your server, please talk to your hosting company and they will install it for you.
</div>
<?php
}
?>
<?php
