/*
Document   :  Asset Download
Author     :  Muhammad Ali 
*/

// Initialization and events code for the app
aiowaffAssetDownload = (function ($) {
    "use strict";

    // public
    var debug_level = 0;
    var maincontainer = null;
    var loading = null;
    var download_buttons = null;

	// init function, autoload
	(function init() {
		// load the triggers
		$(document).ready(function(){
			maincontainer = $(".aiowaff-asset-download");
			loading = maincontainer.find("#aiowaff-main-loading");
			triggers();
		});
	})();
	
	function row_loading( row, status )
	{
		if( status == 'show' ){
			if( row.size() > 0 ){
				if( row.find('.aiowaff-row-loading-marker').size() == 0 ){
					var row_loading_box = $('<div class="aiowaff-row-loading-marker"><div class="aiowaff-row-loading"><div class="aiowaff-meter psp-animate" style="width:30%; margin: 10px 0px 0px 30%;"><span style="width:100%"></span></div></div></div>')
					row_loading_box.find('div.aiowaff-row-loading').css({
						'width': row.width(),
						'height': row.height()
					});

					row.find('td').eq(0).append(row_loading_box);
				}
				row.find('.aiowaff-row-loading-marker').fadeIn('fast');
			}
		}else{
			row.find('.aiowaff-row-loading-marker').fadeOut('fast');
		}
	}
	
	function download_asset( asset, step, step_size, callback ) 
	{
        if ( typeof asset == 'undefined' || asset.length <=0 ) {
            if( typeof callback == 'function' ){
                callback();
            }
            return false;
        }

		var marker = $(".aiowaff-process-progress-marker"),
			tail_list = asset.parent('ul'),
			asset_id = asset.data('id'),
			next_asset = asset.next('li'),
			start_time = new Date().getTime(),
			is_last_item = false, is_first_item = false;
		
		if( typeof step == 'undefined' ){
			step = 1;
			step_size = (100 / tail_list.find('li').size());
		}
		if ( step == 1 ) {
			is_first_item = true;			
		}

		// end of lists
		if( next_asset.size() == 0 ){
			is_last_item = true;
		}
		
		// make current asset li download in progress
		asset.addClass('inprogress');
		asset.append('<div class="aiowaff-process-progress">Load</div>');

		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		$.post(ajaxurl, {
			'action' 		: 'aiowaff_download_asset',
			'id'			: asset_id,
			'is_first_item'	: (is_first_item ? 'yes' : 'no'),
			'is_last_item'	: (is_last_item ? 'yes' : 'no'),
			'debug_level'	: debug_level
		}, function(response) {
		    
		    if ( 'invalid' == response.status ) {
                if( typeof callback == 'function' ){
                    callback();
                }
                return false;
		    }

			var end_time = new Date().getTime(),
				execution_time = (end_time - start_time) / 1000 + " seconds"; // seconds
			
			// add download log new row
			$(".aiowaff-downoad-log ol").append( "<li>" + ( response.msg.replace("{execution_time}", execution_time) ) + "</li>" );
			
			$(".aiowaff-downoad-log").animate({
				scrollTop: 99999
			}, 1);
			
			// remove asset from list
			asset.remove();
			
			// made the asset tail ul smaller
			tail_list.width( tail_list.width() - 86 );
			
			// update the progress bar 
			marker.width( (step_size * step) + "%" );
			marker.find('span').text( Math.ceil(step_size * step) + "%" );
			
			// increse the number of downloaded or failed 
			var downloaded = $(".aiowaff-value-downloaded").eq(0),
				downloaded_value = parseInt(downloaded.text());
			
			downloaded.text( ( downloaded_value + 1 ) );
  
			// is end of list, so stop execution
			if( is_last_item == true ){
				
				// remove the tail container
				tail_list.parent('div').remove();
				$(".aiowaff-asset-download-lightbox .aiowaff-downoad-log").css('height', "+=100px");
				
				// show close button
				$("a#aiowaff-close-btn").show();
  
				if( typeof callback == 'function' ){
					callback();
				}

				return false;
			}
			
			if ( !is_last_item ) {
				// increse the step
				step = step + 1;
			
				// continuing the tail
				download_asset( next_asset, step, step_size, callback );
			}

		}, 'json');
	}
	
	function download_asset_lightbox( prod_id, callback )
	{
		$.post(ajaxurl, {
			'action' 		: 'aiowaffDownoadAssetLightbox',
			'prod_id'		: prod_id,
			'debug_level'	: debug_level
		}, function(response) {
			if( response.status == 'valid' ){
				
				$(".aiowaff-asset-download").append( response.html );
				
				loading.hide();
  
				// start download each images
				download_asset( $(".aiowaff-asset-download").find('.aiowaff-images-tail').find('li').eq(0), undefined, 100, function(){
					if( typeof callback == 'function' ){
						callback();
					}
				});
			} else {
				
				loading.hide();
				alert( response.html );
				// $(".aiowaff-asset-download").append( response.html );
			}
		}, 'json');
	}
	
	function tail_download_all_products( download_btn )
	{
		loading.show();
		
		// remove the current lightbox 
		$(".aiowaff-asset-download-lightbox").remove();
		
		var prod_id = download_btn.data('prodid');
		
		download_asset_lightbox( prod_id, function(){
			
			$("tr[data-itemid='" + ( prod_id ) + "']").remove();
			download_buttons = $(".aiowaff-download-assets-btn");
			
			if( download_buttons.eq(0).size() > 0 ){
				tail_download_all_products( download_buttons.eq(0) );
			}
			else{
				window.location.reload();
			}
		});
	}
	
	function delete_assets_for_products( products )
	{
		loading.show();
		
		var prod_ids = [];
		products.each(function(){
			prod_ids.push( $(this).val() );
		});
		
		$.post(ajaxurl, {
			'action' 		: 'aiowaffDeleteAssetsProducts',
			'products'		: prod_ids,
			'debug_level'	: debug_level
		}, function(response) {
			if( response.status == 'valid' ){
				$.each( prod_ids, function( key, value ) {
					$("tr[data-itemid='" + ( value ) + "']").remove();
				});
				
				/*if( $(".aiowaff-table assets-download-list tbody tr").size() < 1 ){
					window.location.reload();
				} */
			}
			
			loading.hide();
		}, 'json');
	}
	
	function triggers()
	{
		maincontainer.on("click", 'a#aiowaff-close-btn', function(e){
			e.preventDefault();
			var that = $(this)
			
			$(".aiowaff-asset-download-lightbox").remove();
		});
			
		maincontainer.on("click", 'a.aiowaff-download-assets-btn', function(e){
			e.preventDefault();
			var that = $(this),
				prod_id = that.data('prodid');
  
			if( e.clicked != true ){
				loading.show();
				
				// console.log( that, prod_id );
				download_asset_lightbox( prod_id, function() {
                    $("tr[data-itemid='" + ( prod_id ) + "']").remove();
                    window.location.reload();
                } );
			}
			e.clicked = true; 
		});
		
		maincontainer.on("click", 'a.aiowaff-download-all-assets-btn', function(e){
			e.preventDefault();
			
			var that = $(this);
			download_buttons = $(".aiowaff-download-assets-btn");
			
			tail_download_all_products( download_buttons.eq(0) );
		});
		
		maincontainer.on("click", 'a.aiowaff-delete-all-assets-btn', function(e){
			e.preventDefault();
			
			var that = $(this),
				selected_products = maincontainer.find("input[name='delete_asset']:checked");
			
			if( selected_products.size() == 0 ){
				alert('Please select at least one product asset!');
				return false;
			}
			
			delete_assets_for_products( selected_products );
		});
		
		maincontainer.on("click", 'a.aiowaff-show-variations', function(e){
			e.preventDefault();
			
			var that = $(this);
			
			that.slideUp('fast');
			that.next('.aiowaff-variations-list').css({
				'height': '100%'
			});
		});
		
		/*
		maincontainer.on("click", 'a.aiowaff-button', function (e) {
			e.preventDefault();
			
			var $this = $(this), row = $this.parents('.aiowaff-table.assets-download-list').parents('tr').eq(0), itemid = row.data('itemid');

			row_loading(row, 'show');
			download_asset( itemid, row );
		});*/
	}

	// external usage
	return {
		"download_asset": download_asset
    }
})(jQuery);
