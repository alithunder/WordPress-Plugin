/*
Document   :  WooCustom
Author     :  Muhammad Ali 
*/

// Initialization and events code for the app
aiowaffWooCustom = (function($) {
    "use strict";
    
    var page = '';
    var product_type = '';
    
    // init function, autoload
    (function init() {
        // load the triggers
        $(document).ready(function() {
            
            page = $('.inside #publish').length > 0 ? 'details' : 'list';
            //console.log( page );

            if ( page == 'details' ) {
                product_type = $('#woocommerce-product-data h3.hndle span #product-type').val();
                //console.log( product_type ); 
            }
            
            woo_buttons_all();
        });
    })();
    
    function woo_buttons_all() {
        var post_id = 0;

        // Prevent inputs in meta box headings opening/closing contents
        (function() {
            var maincontainer = $("#woocommerce-product-data .wc-metaboxes-wrapper .woocommerce_variations .woocommerce_variation.wc-metabox");
    
            $( maincontainer.find(' > h3') ).unbind('click');
    
            jQuery( maincontainer ).on('click', ' > h3', function(event){
                    
                // If the user clicks on some form input inside the h3 the box should not be toggled
                if ( $(event.target).filter('input, option, label, select, a, span').length ) {
                    return;
                }
                    
               $( maincontainer ).toggleClass( 'closed' );
            });
        })();
      
        if ( product_type == 'variable' ) {
            
            var $asin = $('#woocommerce-product-data div.inside #general_product_data input#aiowaff_asin'),
                asin = $asin.val();
    
            post_id = $asin.parents('form').find('input#post_ID').val();
            
            woo_buttons_add( post_id, $asin );
            
            // add for product variations
            $("#woocommerce-product-data .wc-metaboxes-wrapper .woocommerce_variations .woocommerce_variation.wc-metabox").each(function(i) {
                var that = $(this);
                var container = that.find('.wc-metabox-content .sku').children().last();//that.find('h3 strong');
                var post_id = that.find('h3 .remove_variation').attr('rel');
             
                woo_buttons_add( post_id, null, { 'container' : container } );
            });
        } else { // simple product type
                
            var $asin = $('#woocommerce-product-data div.inside #general_product_data input#aiowaff_asin'),
                asin = $asin.val();
    
            post_id = $asin.parents('form').find('input#post_ID').val();
            
            woo_buttons_add( post_id, $asin );
        }
    }
    
    function woo_buttons_add( post_id, $asin, pms ) {
        var pms = ( typeof pms === 'object' && pms !== null ? pms : {} );
        var $prod_wrap = get_current_wrapper( post_id ), $prod_url = $prod_wrap.find('a'),
            asin = $prod_wrap.data('asin');
        var $container = ( misc.hasOwnProperty(pms, 'container') ? pms.container : null );
        
        // build asin element if not available yet!
        if ( $asin === null && $container ) {
            $asin = $container.after( '<div class="aiowaff_asin">Amazon ASIN: <span title="Amazon ASIN" style="color: green; font-weight: bold;">' + asin + '</span></div>' ).next('.aiowaff_asin');
        }
        
        if ( $asin ) {
            $asin.after( $prod_url );
        }
    }
    
    function get_current_wrapper( post_id ) {
        var wrapper = '';
        wrapper = '.aiowaffWoocustomFields';
        var $wrapper = $(wrapper).filter(function(i) {
            return $(this).data('post_id') == post_id;
        });
        return $wrapper;
    }
    
    var misc = (function(){
        
        function hasOwnProperty(obj, prop) {
            var proto = obj.__proto__ || obj.constructor.prototype;
            return (prop in obj) &&
            (!(prop in proto) || proto[prop] !== obj[prop]);
        }
        
        return {
            'hasOwnProperty' : hasOwnProperty
        }
    })();
                
    // external usage
    return {
    }
})(jQuery);