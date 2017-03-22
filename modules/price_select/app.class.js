/*
Document   :  Price Select
Author     :  Muhammad Ali 
*/

// Initialization and events code for the app
aiowaffPriceSelect = (function($) {
	"use strict";

	// public
	var debug_level = 0;
	var loading = $('<div id="aiowaff-ajaxLoadingBox" class="aiowaff-panel-widget">loading</div>'); // append loading
	var loading_auto = $('<div id="aiowaff-ajaxLoadingBox-auto" class="aiowaff-panel-widget"><div>loading</div></div>');
	var page = '';
	var product_type = '';
	var priceFieldPrefix = '';
	var priceType = {
		'regular'	: 1,
		'sale'		: 1
	};
	var saveMetas = {
		'auto'		: 1,
		'selected'	: 1,
		'ancestry'	: 1,
		'current'	: 1
	};
	
	// init function, autoload
	(function init() {
		// load the triggers
		$(document).ready(function() {
			page = $('.inside #publish').length > 0 ? 'details' : 'list';
			//console.log( page );
			
			if ( page == 'details' ) {
				product_type = $('#woocommerce-product-data h3.hndle span #product-type').val();
			}
			//console.log( product_type ); 

			priceFieldPrefix = ( page == 'details' ? '' : '_aiowaff');
				
			woo_buttons_all();
			triggers();
		});
	})();
	
	function is_prod_amazon( post_id ) {
		var $hiddenWrapper = get_current_wrapper( post_id, 'hidden' );
		if ( $hiddenWrapper.length > 0 ) {
			var is = $hiddenWrapper.find('input.aiowaff-price-isprodamz').val();
			if ( String(is) == '1' ) return true;
		}
		return false;
	}
	
	function get_current_wrapper( post_id, type ) {
		var wrapper = '';
		switch (type) {
			case 'hidden':
				wrapper = '.aiowaffPriceSelectHidden';
				break;
				
			case 'wrapper':
				wrapper = '.aiowaffPriceSelectWrapper';
				break;
				
			case 'wrapper-btn':
				wrapper = '.aiowaff-priceselect-wrapper';
				break;
				
			case 'buttons':
				wrapper = '.aiowaffPriceSelectButtons';
				break;
		}
		var $wrapper = $(wrapper).filter(function(i) {
			return $(this).data('post_id') == post_id;
		});
		return $wrapper;
	}
	
	function choose_price_default( post_id ) {
		var $wrapper = get_current_wrapper( post_id, 'wrapper' ),
			$hiddenWrapper = get_current_wrapper( post_id, 'hidden' );
			
   		for (var pt in priceType) {
   			var $price = $wrapper.find('input.aiowaff-price-' + pt).prop('checked', false);
   			var _current = $hiddenWrapper.find('input.aiowaff-price-' + pt + '-current').val().trim(),
   				_selected = $hiddenWrapper.find('input.aiowaff-price-' + pt + '-selected').val().trim(),
   				_ancestry = $hiddenWrapper.find('input.aiowaff-price-' + pt + '-ancestry').val().trim();

   			if ( _current == 'selected' && _ancestry != '' && _selected != ''  ) {
   				_ancestry = _ancestry.split(',');
   				
   				if ( _ancestry.length > 0 ) {
   					var _ancestryCss = [];
   					for (var key in _ancestry) {
   						_ancestryCss.push( 'ul.aiowaffPriceSelect-Ancestry-' + _ancestry[key] );
   					}
   					_ancestryCss = _ancestryCss.join(' ');

   					var $_inputWrapp = $wrapper.find(_ancestryCss + ' ul.aiowaff-priceselect-price span');
   					if ( $_inputWrapp.length > 0 ) {
   						$_inputWrapp.find('input.aiowaff-price-' + pt).prop('checked', true);
   					}
   				}
   			}
   		}
	}
	
	function get_ancestry( $el ) {
		var $_parent = $el.parent('span'),
			_ancestry = $_parent.data('ancestry');
		return _ancestry;
	}
	
	function when_variations_loaded() {
        $('#woocommerce-product-data .woocommerce_variations .woocommerce_variation').each(function(i) {
            var that = $(this);
            var container = that.find('.woocommerce_variable_attributes .data .variable_pricing') || that.find('.woocommerce_variable_attributes .data_table .variable_pricing');
            var post_id = that.find('h3 .remove_variation').attr('rel');
 
            var $regular = container.find('input[name^="variable_regular_price"].wc_input_price'),
                $sale = container.find('input[name^="variable_sale_price"].wc_input_price');

            woo_buttons_add( post_id, $regular, $sale );
        });
	}
	
	function woo_buttons_all() {
		var post_id = 0;
		
   		if ( page == 'details' ) {
			
			if ( product_type == 'variable' ) {
			    $( '#woocommerce-product-data' ).on( 'woocommerce_variations_loaded', when_variations_loaded);
			} else { // simple product type
	
				var $regular = $('._regular_price_field #_regular_price'),
					$sale = $('._sale_price_field #_sale_price');
	
	   			post_id = $regular.parents('form').find('input#post_ID').val();

	   			woo_buttons_add( post_id, $regular, $sale );
	   		}
   		} else { // list page
   			
   			//aiowaff_price
   			$('td.aiowaff_product_info').each(function(i) {
   				var that = $(this),
   					parent = that.parent('tr'),
   					post_id = parent.prop('id').replace('post-', '');
   					
				var $regular = parent.find('._aiowaff_regular_price_field #_aiowaff_regular_price'),
					$sale = parent.find('._aiowaff_sale_price_field #_aiowaff_sale_price');
   					
   				woo_buttons_add( post_id, $regular, $sale );
   			});
   		}
		
		woo_buttons_triggers();  		
	}
	
	function woo_buttons_add( post_id, $regular, $sale ) {
   		// product is not amazon!
   		if ( !is_prod_amazon(post_id) ) {
   			return false;
   		}
   		
		var $hiddenWrapper = get_current_wrapper( post_id, 'hidden' ),
            $_clone = $hiddenWrapper.clone();
		$regular.after( $_clone );
		
		var btn_html = '\
		<div class="aiowaff-priceselect-wrapper" data-post_id="' + post_id + '" data-pricetype="{pricetype}">\
			<a href="#" class="aiowaff-price-btn-selected button button-primary button-large" data-btn="selected">select</a>\
			<a href="#" class="aiowaff-price-btn-auto button button-secondary button-large" data-btn="auto">auto</a>\
		</div>\
		';
		
		/*
		var hidden_html = '';
		hidden_html += '<div class="aiowaff-priceselect-hidden" data-post_id="' + post_id + '">';
		for (var pt in priceType) {
			for (var pt2 in saveMetas) {
				hidden_html += '<input type="hidden" class="aiowaff-price-' + pt + '-' + pt2 + '" name="aiowaff-price[' + post_id + '][' + pt + '][' + pt2 + ']" />';
			}
		}
		hidden_html += '</div>';
		$regular.after( hidden_html );
		*/

		$regular.after( btn_html.replace('{pricetype}', 'regular') );
		$sale.after( btn_html.replace('{pricetype}', 'sale') );

		btn_is_active( post_id, $hiddenWrapper );
	}
	
	function btn_is_active( post_id, hiddenWrapper, wrapperBtn) {
		var $hiddenWrapper = hiddenWrapper || get_current_wrapper( post_id, 'hidden' ),
			$wrapperBtn = wrapperBtn || get_current_wrapper( post_id, 'wrapper-btn' );
			
		for (var pt in priceType) {
			var $btnWrapper = $wrapperBtn.filter(function(i) {
				return $(this).data('pricetype') == pt;
			});
			var _current = $hiddenWrapper.find('input.aiowaff-price-' + pt + '-current').val().trim();
			$btnWrapper.find('[class^="aiowaff-price-btn-"]').removeClass('active');
			if ( _current != '' ) {
				$btnWrapper.find('.aiowaff-price-btn-' + _current).addClass('active');
			}
		}
	}
	
	function get_field_price_woo( post_id, pt ) {
		var $price_woo = '';
		if ( page == 'details' ) {
			
			if ( product_type == 'variable' ) {
			
				var $wrapperBtn = get_current_wrapper( post_id, 'wrapper-btn' );
				var $btnWrapper = $wrapperBtn.filter(function(i) {
					return $(this).data('pricetype') == pt;
				});
				$price_woo = $btnWrapper.parent().find('input[name^="variable' + priceFieldPrefix + '_' + pt + '_price"]');
			} else {
				
				$price_woo = $('.' + priceFieldPrefix + '_' + pt + '_price_field #' + priceFieldPrefix + '_' + pt + '_price');
			}
		} else { // list page

			var $buttonsWrapper = get_current_wrapper( post_id, 'buttons' );
			$price_woo = $buttonsWrapper.find('.' + priceFieldPrefix + '_' + pt + '_price_field #' + priceFieldPrefix + '_' + pt + '_price');
		}
		return $price_woo;
	}
	
	function woo_buttons_triggers() {
    	$(document.body).on('click', ".aiowaff-priceselect-wrapper > a", function(e){
    		e.preventDefault();
    		
    		var that = $(this),
    			btn = that.data('btn'),
    			$wrapperBtn = that.parents('.aiowaff-priceselect-wrapper'),
    			post_id = $wrapperBtn.data('post_id'),
    			btnPriceType = $wrapperBtn.data('pricetype');

			// choose price
    		if ( btn == 'selected' ) {
    			createLightbox( post_id );
    			choose_price_default( post_id );
    		}
    		// auto choose price
    		else {

				var $hiddenWrapper = get_current_wrapper( post_id, 'hidden' );
				if ( $hiddenWrapper.length > 0 ) {
		    		for (var pt in priceType) {
		    			if ( btnPriceType != pt ) continue;
	
		    			var $price = $hiddenWrapper.find('input.aiowaff-price-' + pt + '-auto');
		    			var $price_woo = get_field_price_woo( post_id, pt );
		
			    		if ( $price.length > 0 ) {
		    				var price = $price.val();
		    				//if ( price != '' ) {
	    						$hiddenWrapper.find('input.aiowaff-price-' + pt + '-current').val( 'auto' );
	    						$hiddenWrapper.find('input.aiowaff-price-' + pt + '-selected').val( '' );
	    						$hiddenWrapper.find('input.aiowaff-price-' + pt + '-ancestry').val( '' );
		    					
		    					if ( $price_woo.length > 0 ) {
		    						$price_woo.val( price );
		    					}
		    					
								btn_is_active( post_id, $hiddenWrapper, $wrapperBtn );
								
								if ( page == 'list' ) {
									save_prices( post_id, pt, 'auto' );
								}
		    				//}
		    			}
		    		}
	    		}
    		}
    	});
	}
	
    function triggers()
    {
    	// wp filter form
    	$('form#posts-filter').on('click', 'input#post-query-submit, input#search-submit', function(e) {
    		e.preventDefault();
    		
    		var $this = $(this), $form = $this.parents('form');
    		
    		$form.find('.aiowaffPriceSelectButtons').remove();
    		$form.find('input[name^="aiowaffPriceSelectInline"]').remove();
    		$form.submit();
    	});
    	
    	$('table.wp-list-table #the-list input#bulk_edit, input#doaction').click(function(){
    		var $this = $(this), $form = $this.parents('form');
    		$form.find('.aiowaffPriceSelectButtons').remove();
    		$form.find('input[name^="aiowaffPriceSelectInline"]').remove();
    	});
    	
    	// Cancel Prices button
    	$('.aiowaffPriceSelectWrapper .aiowaffPriceSelect-buttons').on('click', '> a.cancel', function(e) {
    		e.preventDefault();
    		$('#TB_closeWindowButton').trigger('click');
    	});
    	
    	// Save Prices button
    	$('.aiowaffPriceSelectWrapper .aiowaffPriceSelect-buttons').on('click', '> a.save', function(e) {
    		e.preventDefault();
    		
    		var that = $(this),
    			$wrapper = that.parents('.aiowaffPriceSelectWrapper'),
    			post_id = $wrapper.data('post_id');
    		//console.log( post_id );

			var $hiddenWrapper = get_current_wrapper( post_id, 'hidden' );
			if ( $hiddenWrapper.length > 0 ) {
				
				// validate prices (sale price must be lower than regular price)
				var _currentPrices = { 
					regular 	: $hiddenWrapper.find('input.aiowaff-price-' + pt + '-auto').val(),
					sale 		: $hiddenWrapper.find('input.aiowaff-price-' + pt + '-auto').val()
				};
				for (var pt in priceType) {
	    			var $price = $wrapper.find('input.aiowaff-price-' + pt + ':checked');
	    			if ( $price.length > 0 ) {
	    				var price = $price.parents('span').data('price');
	    				_currentPrices[pt] = price;
	    			}
				}
				if ( _currentPrices['sale'] > _currentPrices['regular'] ) {
					alert('Sale price must be lower than regular price!');
					return true;
				}

	    		for (var pt in priceType) {
	    			
	    			var $price = $wrapper.find('input.aiowaff-price-' + pt + ':checked');
	    			var $price_woo = get_field_price_woo( post_id, pt );
	
		    		if ( $price.length > 0 ) {
	    				var price = $price.parents('span').data('price');
	    				//if ( price != '' ) {
	    					$hiddenWrapper.find('input.aiowaff-price-' + pt + '-current').val( 'selected' );
	    					$hiddenWrapper.find('input.aiowaff-price-' + pt + '-selected').val( price );
	    					$hiddenWrapper.find('input.aiowaff-price-' + pt + '-ancestry').val( get_ancestry($price) );
	    					
	    					if ( $price_woo.length > 0 ) {
	    						$price_woo.val( price );
	    					}
	    					
	    					btn_is_active( post_id, $hiddenWrapper );
	    				//}
	    			}
	    		}
	    		
				if ( page == 'list' ) {
					save_prices( post_id, 'both', 'selected' );
				}
    		}
    		
    		if ( page == 'details' ) {
    			$('#TB_closeWindowButton').trigger('click');
    		}
    	});
    }
    
	function save_prices( post_id, whatType, operation ) {
		ajaxLoading( post_id, operation, 'show' );
			
		var data = {
			'action' 			: 'aiowaffPriceSelectSave',
			'post_id'			: post_id,
			'whatType'			: whatType,
			'operation'			: operation,
			'debug_level'		: debug_level
		};
			
		var $hiddenWrapper = get_current_wrapper( post_id, 'hidden' );
		$hiddenWrapper.find('input[name^="aiowaff-price"]').each(function(i) {
			var $this = $(this);
			data[ $this.prop('name') ] = $this.val();
		});
		//console.log( data ); return false;

		$.post(ajaxurl, data, function(response) {

			if( response.status == 'valid' ){
			}
				
			ajaxLoading( post_id, operation, 'close' );
				
			if ( operation == 'selected' ) {
				$('#TB_closeWindowButton').trigger('click');
			}

		}, 'json');
	}
    
	function createLightbox( id )
	{
		tb_show( 'Amazon Product Choose Price', '#TB_inline?inlineId=aiowaffPriceSelectInline-'+id );
		//tb_position();
		tb_resize();
	}
	
	function tb_resize()
	{
		function resize(){
			var tbWindow = $('#TB_window'),
				tb_width = tbWindow.width(),
				tb_height = tbWindow.height();
			
			$('#TB_ajaxContent').css({
				'width': (tb_width - 40) + "px",
				'height': (tb_height - 50) + "px"
			});
		}
		resize();
		
		$(window).on('resize', function(){
			resize();
		});
	}
	
	function ajaxLoading(post_id, operation, status)
    {
    	if ( page != 'list' ) return true;

   		var $wrapper = '';
		if ( operation == 'auto' ) {
    		$wrapper = get_current_wrapper( post_id, 'buttons' );
      	} else {
    		$wrapper = get_current_wrapper( post_id, 'wrapper' );
      	}

    	if( status == 'show' ){
        	$wrapper.append( loading_auto );
       	} else{
       		$wrapper.find('#aiowaff-ajaxLoadingBox-auto').remove();
       	}
    }
    
	// external usage
	return {
	}
})(jQuery);