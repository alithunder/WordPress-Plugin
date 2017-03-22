jQuery(document).ready(function($) {

	var aiowaff_launch_search = function (data) {
		var searchAjaxLoader 	= jQuery("#aiowaff-ajax-loader"),
			searchBtn 			= jQuery("#aiowaff-search-link");
			
		searchBtn.hide();	
		searchAjaxLoader.show();
		
		var data = {
			action: 'amazon_request',
			search: jQuery('#aiowaff-search').val(),
			category: jQuery('#aiowaff-category').val(),
			page: ( parseInt(jQuery('#aiowaff-page').val(), 10) > 0 ? parseInt(jQuery('#aiowaff-page').val(), 10) : 1 )
		};
		
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(ajaxurl, data, function(response) {
			jQuery("#aiowaff-ajax-results").html(response);
			
			searchBtn.show();	
			searchAjaxLoader.hide();
		});
	};
	
	jQuery('body').on('change', '#aiowaff-page', function (e) {
		aiowaff_launch_search();
	});
	
	jQuery("#aiowaff-search-form").submit(function(e) {
		aiowaff_launch_search();
		return false;
	});
	
	jQuery('body').on('click', 'a.aiowaff-load-product', function (e) {
		e.preventDefault();
		
		var data = {
			'action': 'aiowaff_load_product',
			'ASIN':  jQuery(this).attr('rel')
		};

		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: data,
			success: function(response) {
				if(response.status == 'valid'){
					window.location = response.redirect_url;
					return true;
				}else{
					alert(response.msg);
					return false
				}
			}
		});
	});
});