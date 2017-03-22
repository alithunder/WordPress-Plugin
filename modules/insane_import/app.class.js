/*
Document   :  Products Importer
Author     :  Muhammad Ali 
*/

// Initialization and events code for the app
aiowaffInsaneMode = (function($) {
	"use strict";

	// public
    var debug_level                     = 0,
        maincontainer                   = null,
        loading                         = null,
        background_loading_container    = null,
        containers                      = null,
        lang                            = null,
        default_import_settings         = null,
        box_queue_status_default        = 'close',
        providers                       = {
            'amazon'        : { 
                alias           : 'amz',
                loadprods       : ['search', 'grab', 'bulk']
            },
            'envato'        : {
                alias           : 'env',
                loadprods       : ['search', 'grab', 'bulk']
            },
            'ebay'          : {
                alias           : 'eby',
                loadprods       : ['search', 'bulk']
            },
            'alibaba'       : {
                alias           : 'ali',
                loadprods       : ['search']
            }
        };

        // per provider: load products containers 
        for (var pi in providers) {
            providers[pi].tab_init = false;
        }
                
	// init function, autoload
	(function init() {
		// load the triggers
		$(document).ready(function() {

			maincontainer = $("#aiowaff-insane-import");
			loading = maincontainer.find("#aiowaff-main-loading");
			background_loading_container = maincontainer.find(".aiowaff-insane-work-in-progress");
			containers = {
			    loadprods:      {
                    status       : maincontainer.find('#aiowaff-loadprods-status'),
                    mainwrap     : maincontainer.find('#aiowaff-wrap-loadproducts'),
                    //wrap         : maincontainer.find('#aiowaff-content-scroll-amazon'),
			        //search       : maincontainer.find('#aiowaff-content-search-amazon'),
			        //grab         : maincontainer.find('#aiowaff-content-grab-amazon'),
			        //bulk         : maincontainer.find('#aiowaff-content-bulk-amazon'),
			    },
			    loadstatus:     {
			        wrap         : maincontainer.find('#aiowaff-insane-loadstatus')
			    },
			    queueprods:     {
			        wrap         : maincontainer.find('#aiowaff-queued-products'),
			        results      : maincontainer.find('#aiowaff-queued-results-stats'),
			        export       : maincontainer.find('#aiowaff-export-asins'),
			        prods        : null,
			        check_all    : maincontainer.find('.aiowaff-check-all')
			    },
			    importprods:    {
			        wrap         : maincontainer.find('#aiowaff-insane-import-parameters'),
			        estimate     : null,
			        time         : null,
                    logo         : null,
                    screen_tmp   : maincontainer.find('#aiowaff-import-screen .aiowaff-iip-lightbox')
			    },
                importstatus:   {
                    wrap         : maincontainer.find('#aiowaff-insane-importstatus')
                },
			};
			containers.queueprods.prods      = containers.queueprods.wrap.find('.WZC-products-scroll-cointainer');
			containers.importprods.estimate  = containers.importprods.wrap.find('.aiowaff-insane-import-estimate');
			containers.importprods.time      = containers.importprods.estimate.find('.aiowaff-insane-import-ETA');
            containers.importprods.logo      = containers.importprods.estimate.find('.aiowaff-insane-import-ETA-logo');
 
            // per provider: load products containers 
            for (var pi in providers) {
                var pv = providers[pi],
                    wrap = null;
                
                containers.loadprods[pi] = {};

                for (var ppi in pv.loadprods) {
                    var ppv = pv.loadprods[ppi],
                        ppel = '#aiowaff-content-{el}-{provider}'.replace('{el}', ppv).replace('{provider}', pi);

                    containers.loadprods[pi][ppv] = maincontainer.find( ppel );
                    containers.loadprods[pi]['wrap'] = containers.loadprods[pi][ppv].parent();
                    wrap = containers.loadprods[pi]['wrap'];
                }
 
                containers.loadprods[pi]['bigwrap'] = wrap ? wrap.parents('.aiowaff-insane-container:first') : null;
 
                containers.loadprods[pi]['menu'] = containers.loadprods[pi]['bigwrap']
                    ? containers.loadprods[pi]['bigwrap'].find('.aiowaff-insane-panel-headline ') : null;
            }
  
			// language messages
            lang = maincontainer.find('#aiowaff-lang-translation').html();
            //lang = JSON.stringify(lang);
            lang = JSON && JSON.parse(lang) || $.parseJSON(lang);
            
            // import settings - default
            default_import_settings = maincontainer.find('#aiowaff-import-settings').html();
            //default_import_settings = JSON.stringify(default_import_settings);
            default_import_settings = JSON && JSON.parse(default_import_settings) || $.parseJSON(default_import_settings);

            //background_loading( "some msg", 'show' ); // ajax loading

			triggers();
		});
	})();
	
	
	// :: product ID based on provider
	var prodid = (function() {
	    
	    function set( list, provider, what ) {
            var alias   = providers[provider].alias,
                newarr  = [];
                
            if ( !list ) return list;
            var isa = $.isArray( list ) ? true : false;

            if ( !isa ) {
                list = [list];
            }
            if (1) {
                var regex = new RegExp('^' + alias, 'gi');
                for (var i in list) {
                    if (misc.hasOwnProperty(list, i)) {
                        switch (what) {
                            case 'add':
                                newarr[i] = list[i];
                                if ( ! list[i].match( regex ) ) {
                                    newarr[i] = alias+'-' + list[i];
                                }
                                break;
                            
                            case 'sub':
                                newarr[i] = list[i].replace( alias+'-', '' );
                                break;
                        }
                    }
                }
            }
            if ( !isa ) {
                return newarr[0];
            }
            return newarr;
	    };
	    
	    function get_provider_alias( id ) {
            var _id = id.split('-');
            return _id.length > 1 ? _id[0] : null;
	    };
	    
        function get_asin( id ) {
            var _id = id.split('-');
            return _id.length > 1 ? _id[1] : null;
        };
        
        function get_provider( alias ) {
            // per provider 
            for (var pi in providers) {
                var pv = providers[pi];
                if ( alias == pv.alias ) {
                    return pi;
                }
            }
            return null;
        };

	    return {
	        'set'                  : set,
            'get_provider_alias'   : get_provider_alias,
	        'get_provider'         : get_provider,
	        'get_asin'             : get_asin
	    };
	})();


    // :: SPEEDOMmETER interface
	function drawTextAlongArc(context, str, centerX, centerY, radius, angle) {
		var numbers = [5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 70, 85, 100];
        var len = numbers.length, s, cc = 5;
        context.save();
        
        context.translate(centerX, centerY);
        context.rotate(-1.49 * angle / 2);
        context.rotate(-1 * (angle / 10) / 2);
        
        for(var n = 0; n < len; n++) {
        	
        	context.rotate(angle / 9.15);
        	context.save();
        	context.translate(10, -1 * radius);
        	context.fillText( "|", 0, 21 );
        	context.fillText( numbers[n], 0, 42 );
        	context.restore();
        }
        context.restore();
    }
      
	function draw_tick_marks(options) {
		var canvas = document.getElementById('speedometer-markers'), 
	        context = canvas.getContext('2d'),
	        centerX = canvas.width / 2,
	        centerY = canvas.height / 2,
	        angle = Math.PI * 1.2,
	        radius = 125;
	      
    	context.font = '12pt Calibri';
    	context.textAlign = 'center';
    	context.fillStyle = 'rgb(189, 195, 199)';
    	context.strokeStyle = 'rgb(189, 195, 199)';
    	context.lineWidth = 1;
    	drawTextAlongArc(context, '', centerX, centerY, radius, angle);
	
	    // draw circle lines
	    context.arc(centerX, centerY, radius - 10, 0, 2 * Math.PI, false);
	    context.stroke();
	    
	    context.beginPath();
	    context.rect(47, canvas.height - 23, 140, 100);
	    context.fillStyle = 'white';
	    context.fill();
      	
      	context.beginPath();
	    context.rect(167, canvas.height - 26, 40, 40);
	    context.fillStyle = 'white';
	    context.fill();
	}
	
	function change_speedometer_value( new_value ) {
		var speedometer       = maincontainer.find("#aiowaff-speedometer"),
			needle 		      = speedometer.find("#speedometer-needle"),
			zero_pos 	      = -233,
			max_real_pos      = 51,
			max_insane_pos    = 71,
			one_grade         = 4.7,
			increase          = 0,
			new_pos           = zero_pos;

        new_value = new_value <= 5 ? 0 : parseInt( new_value - 5 );

        if ( new_value <= 45 ) {
            increase = new_value * one_grade;
        } else if ( new_value <= 65 ) {
            increase = 45 * one_grade + ( new_value - 45 ) * one_grade / 4;
        } else if ( new_value <= 95 ) {
            increase = 45 * one_grade + 20 * one_grade / 4 + ( new_value - 65 ) * one_grade / 3; // 20 = 65 - 45
        } else {
            increase = -zero_pos + max_insane_pos;
        }
        increase = parseInt( increase );
        
        new_pos = parseInt( zero_pos + increase );
        new_pos = new_pos > max_real_pos ? max_insane_pos : new_pos;
		
		// products per minute
		speedometer.find('#aiowaff-speedometer-name i').html( new_value );

		needle.css({
			'transform': 'rotate(' + new_pos + 'deg)'
		});
	}
	
	
	// :: QUEUE PRODUCTS interface
    var queueprod = (function() {
       
        function set_queue_width() {
            var w = ( containers.queueprods.prods.find("ul li").eq(0).outerWidth() + 10 ) // + 30
                * containers.queueprods.prods.find("ul li").size();
 
            containers.queueprods.wrap.data("list_width", w);
 
            // current box status
            var list = containers.queueprods.prods.find("ul"),
                status = containers.queueprods.wrap.hasClass('aiowaff-open') ? 'open' : 'close';
            if ( status == 'close' ) {
                list.width( containers.queueprods.wrap.data("list_width") );
            }
        };

        function create_products_scroll( status ){
            containers.queueprods.prods.width( $("div#aiowaff-content").width() - 70 );
            set_queue_width();
            view_products_list( status, true );
        };

        function view_products_list( status, is_init )
        {
            var list = containers.queueprods.prods.find("ul"),
                btn = maincontainer.find("#aiowaff-expand-all");
            var is_init = is_init || false;
            
            // toggle box status
            if ( status == 'toggle' ) {
                status = containers.queueprods.wrap.hasClass('aiowaff-open') ? 'close' : 'open';
            }
    
            if ( status == 'close' ) {
                //console.log( 'close' );
                list.width( containers.queueprods.wrap.data("list_width") );
                containers.queueprods.wrap.removeClass('aiowaff-open').addClass('aiowaff-close');
                
                // change button text
                btn.find("span").eq(1).hide();
                btn.find("span").eq(0).show();
    
            } else {
                //console.log( 'open' );
                if ( !is_init ) { // if default is open, we don't want to overwrite the ul list data saved width with the 100% value!
                    containers.queueprods.wrap.data("list_width", list.width());
                }
                list.width( "100%" );
                containers.queueprods.wrap.removeClass('aiowaff-close').addClass('aiowaff-open');
                
                // change button text
                btn.find("span").eq(0).hide();
                btn.find("span").eq(1).show();
            }
            return;
        };
        
        function check_all() {
            /*containers.queueprods.check_all.on('click', "input[type='checkbox'], label", function () {
                var that = $(this), elType = that.prop('tagName').toUpperCase();
                if ( elType == 'LABEL' ) {
                    that.parent().find('input[type="checkbox"]').trigger('click');
                    return false;
                }
            });*/
            var check_all   = containers.queueprods.check_all.find( 'input[type="checkbox"]' );
                
            // check all click
            containers.queueprods.check_all.on('click', 'input[type="checkbox"]', function (e) {
                var prods           = containers.queueprods.prods.find( 'ul li input[type="checkbox"]' );
                var that            = $(this),
                    status          = that.prop('checked'),
                    parent          = that.parent();
 
                status ? parent.find('label').text( lang.uncheck_all ) : parent.find('label').text( lang.check_all );

                prods.each(function (i) {
                   $(this).trigger('click'); 
                });
            });

            // product checkbox click
            containers.queueprods.prods.on('click', 'ul li input[type="checkbox"]', function (e) {
                var prods           = containers.queueprods.prods.find( 'ul li input[type="checkbox"]' ),
                    prods_checked   = containers.queueprods.prods.find( 'ul li input[type="checkbox"]:checked' );

                prods.length == prods_checked.length ? check_all.prop('checked', true) : check_all.prop('checked', false);
                var that            = check_all,
                    status          = that.prop('checked'),
                    parent          = that.parent();
 
                status ? parent.find('label').text( lang.uncheck_all ) : parent.find('label').text( lang.check_all );
            });
        }

        // external usage
        return {
            'set_queue_width'           : set_queue_width,
            'create_products_scroll'    : create_products_scroll,
            'view_products_list'        : view_products_list,
            'check_all'                 : check_all
        };
    })();

	
	// :: TRIGGERS
	function triggers()
	{
	    // tooltip
	    jQuery('span.tooltip, i, a').tipsy({live: true, gravity: 'w', html: true});

		// queue products create box
        queueprod.create_products_scroll( box_queue_status_default );
        queueprod.check_all();
		
		// queue show products button
		maincontainer.on('click', "#aiowaff-expand-all", function(e){
			e.preventDefault();
			
			var that     = $(this),
                parent   = that.parents().eq(2); 
 
			parent.find('.aiowaff-insane-panel-headline a[href="#aiowaff-queued-products"]').trigger('click');
			queueprod.view_products_list( 'toggle' );
		});

        // speedometer
        draw_tick_marks();

		// test speedometer
		//maincontainer.on('change', "#test-speedometer", function(){
		//	change_speedometer_value( $(this).val() );
		//});
		//change_speedometer_value( 105 );
		
		// TABS (can have subtabs)
		maincontainer.find(".aiowaff-insane-tabs").each(function(){
			var that = $(this),
				btns = that.find("> .aiowaff-insane-panel-headline a"),
				tabs = that.find("> .aiowaff-insane-tabs-content > .aiowaff-content-scroll > .aiowaff-insane-tab-content, > .aiowaff-insane-tabs-content > .aiowaff-insane-tab-content");
 
            that.find('> .aiowaff-insane-panel-headline a').removeClass('on').eq(0).addClass('on');
			that.on('click', '> .aiowaff-insane-panel-headline a', function(e){
				e.preventDefault();
				
				var btn = $(this),
				    provider = btn.data('provider') || false,
				    href = btn.attr("href"),
					rel = $( href );

                // tab init: first time when clicked!
                if ( provider && !providers[provider].tab_init ) {
                    if ( 'alibaba' == provider ) {
                        var search_wrap = containers.loadprods[provider].search;
                        loadprod.get_category_params(
                            search_wrap.find('select.aiowaff-search-category'),
                            { 'provider' : provider }
                        );
                    }
                    providers[provider].tab_init = true;
                }

				if( btn.hasClass('on') ) return;

				tabs.hide();
				rel.fadeIn( 200 );

				btns.parent("div").find("a.on").removeClass("on");
				btn.addClass("on");
			});
			
			//!! set default tab based on tab Index
			//$(".aiowaff-insane-panel-headline a").eq(2).click();
		});
		
        // show messages log button
        maincontainer.on('click', ".aiowaff-insane-buton-logs", function(e){
            e.preventDefault();
            
            var that    = $(this),
                log     = that.data('logcontainer');
            
            $('.aiowaff-insane-container-logs').filter(function(i){
                return $(this).prop('id') == log;
            }).toggle('aiowaff-logs-open');
        });
		
		// range sliders
        $('input[type="range"]').rangeslider({
            polyfill    : false,
            //onInit      : function() {
            //    if (this.value == this.max) {
            //    }
            //}
        });
        $(document).on('input', 'input[type="range"]', function(e) {
            var that = $(this),
                val = that.val(),
                max = that.prop('max'),
                id = that.prop('id'),
                $output = $('#' + id + '-output');

            if ( val == max ) {
                val = 'all';
            }
            $output.val( val );
            
            // estimate import duration & speed
            importprod.estimate({
                speed           : 47,
                time            : 90000 // 1000 * 60 * 1.5 => 1.5 min
            });
        });
	}


    // :: LOADING
    function row_loading( row, status, extra )
    {
        var extra = extra || {};
        var isextra = ( typeof extra != 'undefined' && misc.size(extra) == 1 ? true : false );
  
        if( status == 'show' ){
            if( row.size() > 0 ){
                if( row.find('.aiowaff-row-loading-marker').size() == 0 ){
                    //<div class="aiowaff-loading-text">Loading</div><div class="aiowaff-meter aiowaff-animate" style="width:30%; margin: 10px 0px 0px 30%;"><span style="width:100%"></span></div>
                    var html = '<div class="aiowaff-meter aiowaff-animate" style="width:30%; margin: 10px auto;"><span style="width:100%"></span></div><div class="aiowaff-loading-text">' + lang.loading + '</div>';
                    //if ( isextra ) {
                    //    html = html + extra.html;
                    //}
                    var row_loading_box = $('<div class="aiowaff-row-loading-marker"><div class="aiowaff-row-loading">' + html + '</div></div>');
                    row_loading_box.find('div.aiowaff-row-loading').css({
                        'width'     : parseInt( row.outerWidth() ),
                        'height'    : parseInt( row.outerHeight() + 40 ),
                        'top'       : '-40px'
                    });
                    row.prepend(row_loading_box);
                }
                if ( isextra && $.trim( extra.html ) != '' ) {
                    row.find('.aiowaff-row-loading-marker')
                    .find('div.aiowaff-row-loading')
                    .find('div.aiowaff-loading-text')
                    .html( extra.html );
                }
                row.find('.aiowaff-row-loading-marker').find('div.aiowaff-row-loading').css({
                    'width'     : parseInt( row.outerWidth() ),
                    'height'    : parseInt( row.outerHeight() + 40 ),
                    'top'       : '-40px'
                });
                //text loading!
                row.find('.aiowaff-row-loading-marker').find('div.aiowaff-loading-text').css({
                    'height'    : parseInt( row.outerHeight() - 10 )
                });
                row.find('.aiowaff-row-loading-marker').fadeIn('fast');
            }
        } else {
            row.find('.aiowaff-row-loading-marker').fadeOut('slow');
        }
    }
    
    function background_loading( msg, status )
    {
        if( status != 'show' ) {
            background_loading_container.hide();
        }
        
        background_loading_container.find('span').text( msg );
        background_loading_container.show();
        background_loading_container.animate({
            'height': '90px'
        }, 350 );
    }

    
    // :: MESSAGES
    function set_status_msg_generic( status, msg, op, from ) {
        var _op = '',
            wrap = { li: '', i: '', span: '' };

        switch (op) {
            case 'bulk':
                _op = lang.load_op_bulk;
                break;
                
            case 'grab':
                _op = lang.load_op_grab;
                break;
                
            case 'search':
                _op = lang.load_op_search;
                break;
                
            case 'export':
                _op = lang.load_op_export;
                break;
                
            case 'import':
                _op = lang.load_op_import;
                break;
        }
        switch (status) {
            case 'invalid':
                wrap.li = 'error';
                wrap.i = 'minus-circle';
                wrap.span = 'error';
                break;
                
            case 'valid':
                wrap.li = 'success';
                wrap.i = 'check-circle';
                wrap.span = 'success';
                break;
                
            case 'info':
                wrap.li = 'notice';
                wrap.i = 'info';
                wrap.span = 'info';
                break;
        }
        //<span class="aiowaff-insane-logs-frame">Yesterday 10:24 PM</span>
        var html = '\
            <li class="aiowaff-log-' + wrap.li + '">\
                <i class="fa fa-' + wrap.i + '"></i>\
                <span class="aiowaff-insane-logs-frame">' + misc.get_current_date() + '</span>\
                <span class="aiowaff-insane-logs-frame">' + _op + '</span>\
                <br />\
                <span class="aiowaff-insane-logs-msg"> ' + msg + '</span>\
            </li>',
            html_ = '\
            <span class="aiowaff-message aiowaff-' + wrap.span + '">\
                <span class="aiowaff-insane-logs-frame">' + misc.get_current_date() + '</span>\
                <span class="aiowaff-insane-logs-frame">' + _op + '</span>\
                <br />\
                <span class="aiowaff-insane-logs-msg"> ' + msg + '</span>\
            </span>';

        if ( from == 'loadprod' ) {
            containers.loadstatus.wrap.find('ul.aiowaff-insane-logs').prepend( html );
        } else {
            containers.importstatus.wrap.find('ul.aiowaff-insane-logs').prepend( html );
        }
    };
    

    // :: LOAD PRODUCTS in Queue
    var loadprod = (function() {
        
        var DEBUG                   = false,
            TEST                    = 0;
        var asins = {
            found               : [], // asins found, valid or not
            loaded              : [], // loaded in queue
            invalid             : [], // invalid - could not be loaded in queue
            already_imported    : [], // already_imported = already imported products ( NOT those which will be imported from selected queue )
            selected            : [], // selected for importing
            imported            : [], // imported from selected queue
            import_errors       : [] // not imported (have errors) from selected queue
        }, load_max_limit = 100;

        // Test!
        function __() { asins.found.push('asin1'); };
        
        // get public vars
        function get_vars() {
            return $.extend( {}, {
                asins       : asins
            } );
        };
        
        // init function, autoload
        (function init() {
            // load the triggers
            $(document).ready(function() {
    
                triggers();
                // per provider: triggers
                for (var pi in providers) {
                    triggers( pi );
                }
            });
        })();
        
        // Triggers
        function triggers( provider ) {
            var provider        = provider || 'all',
                box             = provider == 'all' ? null : containers.loadprods[provider].wrap,
                search_wrap     = $.inArray(provider, ['amazon', 'alibaba', 'envato', 'ebay']) == -1
                    ? null : containers.loadprods[provider].search;
 
            if ( 'all' == provider ) {

                keyword_autocomplete();
                //select_category();
                
                // selected products in queue
                containers.queueprods.prods.on('click', 'ul li input[type="checkbox"]', function(e) {
                    var that = $(this),
                        status = that.prop('checked'),
                        li = that.parents('li').eq(0),
                        asin = li.data('asin').toString(),
                        provider_alias = prodid.get_provider_alias( asin ),
                        provider = prodid.get_provider( provider_alias ),
                        operation = null;
    
                    if ( status ) {
                        operation = 'add';
                        li.addClass('selected');
                        
                        // add asin to selected asins list
                        set_results( [asin], 'selected', { 'provider' : provider } );
                    } else {
                        operation = 'remove';
                        li.removeClass('selected');
                        
                        // remove asin from selected asins list 
                        misc.arrayRemoveElement(asins.selected, asin);
                        set_results( null, 'selected', { 'provider' : provider } );
                    }
                    
                    // Import Products - update total products list
                    if ( $.inArray(importprod.v().process_status, ['start', 'run', 'stop', 'finished']) > -1 ) {
                        //importprod.stop_import();
                        importprod.calculate_products_data( 
                            'total',
                            importprod.get_prod_data( li, importprod.v().import_params ),
                            operation
                        );
                    }
                });
                
                // export asins
                containers.queueprods.export.on('click', 'form#aiowaff-export-form #aiowaff-export-button', function(e) {
                    e.preventDefault();
                    
                    var form = $(this).parents('form').eq(0);
                    export_asins( form );
                });
            }
            if ( 'amazon' == provider ) {
                
                // BULK LOAD ASINs
                box.on('click', 'form.aiowaff-bulk-products .aiowaff-addASINtoQueue', function(e) {
                    e.preventDefault();
    
                    bulk_add_asin_to_queue( $(this).parents('form').eq(0), { 'provider' : provider } );
                });
                
                // GRAB ASINs
                box.on('click', 'form.aiowaff-grab-products .aiowaff-grabb-button', function(e) {
                    e.preventDefault();
    
                    grab_parse_url( $(this).parents('form').eq(0), { 'provider' : provider } );
                });
                
                // SEARCH PRODS
                box.on('submit', 'form.aiowaff-search-products', function(e){
                //box.on('click', 'form.aiowaff-search-products .aiowaff-button', function(e) {
                    e.preventDefault();
    
                    search_prods( $(this), { 'provider' : provider } );
                });
                
                // search products - change category
                //var categ_wrap = search_wrap.find('.aiowaff-select-on-category');
    
                // change category
                search_wrap.on( 'change', 'select.aiowaff-search-category', function(){
                    var that = $(this);
        
                    search_select_pages( that.val(), { 'provider' : provider } );
                    get_category_params( that, { 'provider' : provider } );
                });
                
                // change browse nodes and retrieve childrens
                search_wrap.on( 'change', '.aiowaff-param-optional.aiowaff-param-node select', function(){
                    var that = $(this);
        
                    get_browse_nodes( that, false, { 'provider' : provider } );
                });
                
                // search products - sort parameter tooltip
                search_wrap.on('change', 'select[name="aiowaff-search[Sort]"]', function (e) {
                    var that    = $(this);
                    sort_tooltip( that );
                });
            }
            if ( 'alibaba' == provider ) {
                
                // SEARCH PRODS
                box.on('submit', 'form.aiowaff-search-products', function(e){
                //box.on('click', 'form.aiowaff-search-products .aiowaff-button', function(e) {
                    e.preventDefault();
    
                    search_prods( $(this), { 'provider' : provider } );
                });
                
                // search products - change category
                //var categ_wrap = search_wrap.find('.aiowaff-select-on-category');
    
                // change category
                search_wrap.on( 'change', 'select.aiowaff-search-category', function(){
                    var that = $(this);
        
                    //search_select_pages( that.val(), { 'provider' : provider } );
                    get_category_params( that, { 'provider' : provider } );
                });
            }
            if ( 'envato' == provider ) {
                
                // BULK LOAD ASINs
                box.on('click', 'form.aiowaff-bulk-products .aiowaff-addASINtoQueue', function(e) {
                    e.preventDefault();
    
                    bulk_add_asin_to_queue( $(this).parents('form').eq(0), { 'provider' : provider } );
                });
                
                // GRAB ASINs
                box.on('click', 'form.aiowaff-grab-products .aiowaff-grabb-button', function(e) {
                    e.preventDefault();
    
                    search_prods( $(this).parents('form').eq(0), { 'provider' : provider } );
                });
                
                // SEARCH PRODS
                box.on('submit', 'form.aiowaff-search-products', function(e){
                //box.on('click', 'form.aiowaff-search-products .aiowaff-button', function(e) {
                    e.preventDefault();
    
                    search_prods( $(this), { 'provider' : provider } );
                });
                
                box.on('click', ".aiowaff-envato-sites-selected a", function(e){
                    e.preventDefault();
                });
                
                box.on('click', ".aiowaff-envato-sites a", function(e){
                    e.preventDefault();
                    
                    var clone = $(this).clone();
                    maincontainer.find(".aiowaff-envato-sites-selected").html( clone );
                    maincontainer.find(".aiowaff-envato-sites-container input").val( clone.attr("href").replace("#", "" ) );
                });
                
                // fix overflow for ul sites!
                box.find('__.aiowaff-envato-sites-container').hover(function() {
                    box.find('.aiowaff-insane-tab-search-buttons').addClass('aiowaff-envato-sites-on');
                }, function() {
                    box.find('.aiowaff-insane-tab-search-buttons').removeClass('aiowaff-envato-sites-on');
                });
            }
            if ( 'ebay' == provider ) {
                
                // BULK LOAD ASINs
                box.on('click', 'form.aiowaff-bulk-products .aiowaff-addASINtoQueue', function(e) {
                    e.preventDefault();
    
                    bulk_add_asin_to_queue( $(this).parents('form').eq(0), { 'provider' : provider } );
                });
                
                // SEARCH PRODS
                box.on('submit', 'form.aiowaff-search-products', function(e){
                //box.on('click', 'form.aiowaff-search-products .aiowaff-button', function(e) {
                    e.preventDefault();
    
                    search_prods( $(this), { 'provider' : provider } );
                });
                
                // search products - change category
                //var categ_wrap = search_wrap.find('.aiowaff-select-on-category');
                
                // change category
                search_wrap.on( 'change', 'select.aiowaff-search-category', function(){
                    var that = $(this);
        
                    //search_select_pages( that.val(), { 'provider' : provider } );
                    //get_category_params( that, { 'provider' : provider } );
                    get_browse_nodes( that, false, { 'provider' : provider } );
                });
    
                // change browse nodes and retrieve childrens
                search_wrap.on( 'change', '.aiowaff-param-optional.aiowaff-param-node select', function(){
                    var that = $(this);
        
                    get_browse_nodes( that, false, { 'provider' : provider } );
                });
                
                // search products - sort parameter tooltip
                search_wrap.on('change', 'select.aiowaff-search-opt-desc', function (e) {
                    var that    = $(this);
                    sort_tooltip( that );
                });
            }
        };
        
        function sort_tooltip( that ) {
            var val     = that.val(),
                opt     = val!='' ? that.find("[value=" + ( val ) + "]") : null,
                desc    = opt !== null ? opt.data('desc') : '',
                ttip    = that.parent().find('span'),
                title   = ttip.data('title');

            if ( desc != '' ) {
                ttip.prop('title', title + '<br /><i><strong><u>' + val + '</u></strong>: ' + desc + '</i>');
            } else {
                ttip.prop('title', title);
            }
        };
        
        // BULK LOAD ASINs
        function bulk_add_asin_to_queue( form, pms ) {
            loading( 'show', lang.loading );
            
            var pms         = typeof pms == 'object' ? pms : {},
                provider    = misc.hasOwnProperty(pms, 'provider') ? pms.provider : 'amazon';

            var box         = containers.loadprods[provider].bulk,
                form        = typeof form != 'undefined' ? form : box.find('form.aiowaff-bulk-products'),
                asins_str   = $.trim( form.find('.aiowaff-content-bulk-asin').val() ),
                delimiter   = box.find("input[name=aiowaff-csv-delimiter]:checked").attr('id').split('radio-'),
                delimiter   = delimiter[1],
                _asins      = [];

            if ( delimiter == 'newline' ) {
                delimiter = "\n";
            } else if ( delimiter == 'comma' ) {
                delimiter = ",";
            } else if ( delimiter == 'tab' ) {
                delimiter = "\t";
            }
        
            if (asins_str == ""){
                set_status_msg( 'invalid', lang.bulk_add_asin, 'bulk' );
                loading( 'close', lang.bulk_add_asin );
                return false;
            }

            $.each( asins_str.split( delimiter ), function(key, val) {
                if ($.trim( val ) != "") {
                    _asins.push( $.trim( val ) );
                }
            });

            // success
            if (_asins.length > 0) {
                // update results
                set_results( _asins, 'found', { 'provider' : provider } );

                var msg = lang.bulk_asin_found.replace('%s', _asins.length) + _asins.join(', ');
                set_status_msg( 'valid', msg, 'bulk' );
                loading( 'show', msg );
                
                // load products in queue
                load_prods_by_asin( _asins, 'bulk', { 'provider' : provider } );
            }
            // error
            else {
                set_status_msg( 'invalid', lang.bulk_no_asin_found, 'bulk' );
                loading( 'show', lang.bulk_no_asin_found );
                return false;
            }
        };
        
        // GRAB ASINs
        function grab_parse_url( form, pms ) {
            var operation_id = new Date().getTime(); // in miliseconds
            loading( 'show', lang.loading );
            heartbeat.start( operation_id );
            
            var pms         = typeof pms == 'object' ? pms : {},
                provider    = misc.hasOwnProperty(pms, 'provider') ? pms.provider : 'amazon';

            var box         = containers.loadprods[provider].grab,
                form        = typeof form != 'undefined' ? form : box.find('form.aiowaff-grab-products');

            var data = {
                'action'        : 'aiowaffIM_LoadProdsGrabParseURL',
                'params'        : form.serialize(),
                'operation_id'  : operation_id,
                'debug_level'   : debug_level
            };

            $.post(ajaxurl, data, function(response) {
                set_status_msg( response.status, response.msg, 'grab' );
                loading( 'show', response.msg );

                // success
                if (response.status == 'valid') {
                    var _asins = response.asins;

                    // update results
                    set_results( _asins, 'found', { 'provider' : provider } );
                    
                    // load products in queue
                    load_prods_by_asin( _asins, 'grab', { 'provider' : provider } );
                    return true;
                }
                // error
                else {
                    heartbeat.stop();
                    loading( 'close' );
                    return false;
                }

            }, 'json');
        };
        
        // SEARCH PRODS
        function search_prods( form, pms ) {
            var operation_id = new Date().getTime(); // in miliseconds
            loading( 'show', lang.loading );
            heartbeat.start( operation_id );
            
            var pms         = typeof pms == 'object' ? pms : {},
                provider    = misc.hasOwnProperty(pms, 'provider') ? pms.provider : 'amazon';
 
            var box             = containers.loadprods[provider].search,
                form            = typeof form != 'undefined' ? form : box.find('form.aiowaff-search-products'),
                asins_inqueue   = get_asins_inqueue(),
                nodename        = null,
                nodeid          = null;
            var //search_wrap     = containers.loadprods[provider].search,
                category_dropdown = form.find('select.aiowaff-search-category'),
                category_id     = category_dropdown.length
                    ? category_dropdown.find('option:selected').data('nodeid') : 0,
                search_type_    = form.find('input[name="aiowaff-search[search_type]"]'),
                search_type     = typeof search_type_ !== 'undefined' && search_type_.length ? search_type_.val() : 0;
 
            var data            = [],
                form_params     = form.serializeArray();

            data.push(
                {name: 'debug_level',       value: debug_level},
                {name: 'action',            value: 'aiowaffIM_LoadProdsBySearch'},
                {name: 'operation',         value: 'search'},
                {name: 'operation_id',      value: operation_id},
                {name: 'asins_inqueue',     value: asins_inqueue},
                {name: 'provider',          value: provider}
            );
            // get last BrowseNode value
            if ( $.isArray(form_params) ) {
                for (var i = 0, len = form_params.length; i < len; i++) {
                    var obj = form_params[i];
                    if ( typeof(obj) != 'undefined' 
                        && misc.hasOwnProperty(obj, 'name') && misc.hasOwnProperty(obj, 'value') ) {

                        if ( obj.name.search(/BrowseNode/gi) > 0 ) {
                            if ( obj.value != '' ) {
                                nodename = obj.name;
                                nodeid   = obj.value;
                            }
                            form_params.splice(i, 1);
                            --i;
                        }
                    }
                }
                if ( nodeid ) {
                    form_params.push(
                        {name: nodename, value: nodeid}
                    );
                }
                if ( category_id ) {
                    form_params.push(
                        {name: 'aiowaff-search[category_id]', value: category_id}
                    );
                }
                if ( search_type ) {
                    form_params.push(
                        {name: 'aiowaff-search[search_type]', value: search_type}
                    );
                }
                form_params = $.param( form_params ); // turn the result into a query string
            }
            data.push(
                {name: 'params', value: form_params}
            );
            data = $.param( data ); // turn the result into a query string
            //console.log( data ); return false;

            /*
            var data            = {
                'debug_level'       : debug_level,
                'action'            : 'aiowaffIM_LoadProdsBySearch',
                'operation'         : 'search',
                'asins_inqueue'     : asins_inqueue,
                'params'            : form.serialize()
            };
            console.log( data ); return false;
            */

            $.post(ajaxurl, data, function(response) {
                if (1) {
                    set_status_msg( response.status, response.msg, 'search' );
                    //loading( 'show', response.msg );

                    if ( misc.hasOwnProperty(response, 'asins') ) {
                        // update results
                        set_results( response.asins.loaded, 'loaded', { 'provider' : provider, 'response' : response } );
                        set_results( response.asins.loaded, 'selected', { 'provider' : provider } );
                        set_results( response.asins.invalid, 'invalid', { 'provider' : provider } );
                        set_results( response.asins.already_imported, 'already_imported', { 'provider' : provider } );
                        set_results( response.asins.found, 'found', { 'provider' : provider } );
                        
                        // update queue prods width
                        queueprod.set_queue_width();
                        
                        //search stats
                        if ( misc.hasOwnProperty(response, 'search_stats') ) {
                            containers.loadprods[provider]['menu'].find('span').html( 
                                misc.numberWithCommas( response.search_stats, ' ' )
                            );
                        }
                        
                        if ( misc.hasOwnProperty(response.asins, 'remained') ) {
                            // load products in queue
                            load_prods_by_asin( response.asins.remained, 'search', { 'provider' : provider } );
                            return true;
                        }
                    }

                    heartbeat.stop();
                    loading( 'close' );
                }

            }, 'json');
        }
        
        // LOAD PRODS IN QUEUE & SET RESULTS
        function load_prods_by_asin( asin, op, pms ) {
            
            var operation_id = new Date().getTime(); // in miliseconds
            heartbeat.start( operation_id );
            
            var pms         = typeof pms == 'object' ? pms : {},
                provider    = misc.hasOwnProperty(pms, 'provider') ? pms.provider : 'amazon';
 
            asin = prodid.set( asin, provider, 'add' );
                
            var contor = 0, max = asin.length; // maximum one at a time //Math.ceil( asin.length / 10 ) * 2;
            max = max <= 1000 ? max : 1000;
            function do_load() {
                ///*
                var _r          = array_splice_verify_already_exists(asins, asin, 0, load_max_limit),
                    asin_step   = _r.slice,
                    exists      = _r.already_exists;
                asin = _r.array;
                //*/
                //var asin_step = asin.splice(0, load_max_limit); // debug!
                //console.log( 'header' ); console.dir( asin ); console.dir( asin_step );
                
                // asins already exists in: loaded or invalid or already imported
                if ( exists.length > 0 ) {
                    set_status_msg( 'invalid', lang.already_exists.replace('%s', exists.length).replace('%s', exists.join(', ')), op );
                }
    
                if ( asin_step.length <= 0 || contor >= max ) {
                    heartbeat.stop();
                    loading( 'close' );
                    return false;
                }
            
                var asins_inqueue   = get_asins_inqueue();
                var data = {
                    'debug_level'       : debug_level,
                    'action'            : 'aiowaffIM_LoadProdsByASIN',
                    'operation'         : op,
                    'operation_id'      : operation_id,
                    'asins'             : asin_step,
                    'page'              : contor + 1,
                    'asins_inqueue'     : asins_inqueue,
                    'provider'          : provider
                };
  
                $.post(ajaxurl, data, function(response) {
                    set_status_msg( response.status, response.msg, op );
                    loading( 'show', response.msg );

                    if ( misc.hasOwnProperty(response, 'asins') ) {
                        // update results
                        set_results( response.asins.loaded, 'loaded', { 'provider' : provider, 'response' : response } );
                        set_results( response.asins.loaded, 'selected', { 'provider' : provider, 'response' : response } );
                        set_results( response.asins.invalid, 'invalid', { 'provider' : provider } );
                        set_results( response.asins.already_imported, 'already_imported', { 'provider' : provider } );

                        response.asins.remained = prodid.set( response.asins.remained, provider, 'add' );
                        asin = misc.arrayUnique( asin.concat( response.asins.remained ) );
                        
                        // update queue prods width
                        queueprod.set_queue_width();
                    }

                    ++contor;
                    
                    // repeat till all products from the asins list are marked as loaded or invalid
                    do_load();

                }, 'json');
            };
            do_load(); // init cycle
        }

        function set_results( asin, type, pms ) {
            var pms         = typeof pms == 'object' ? pms : {},
                provider    = misc.hasOwnProperty(pms, 'provider') ? pms.provider : 'amazon',
                response    = misc.hasOwnProperty(pms, 'response') ? pms.response : null;

            var arrlist = ['found', 'loaded', 'invalid', 'selected', 'already_imported', 'imported', 'import_errors'];
            if ( $.inArray(type, arrlist) > -1 ) {
                if ( asin != null ) {
                    asin = prodid.set( asin, provider, 'add' );
                    asins[type] = misc.arrayUnique( asins[type].concat( asin ) );
                }
            }
            switch (type) {
                case 'found':
                case 'invalid':
                case 'already_imported':
                case 'imported':
                case 'import_errors':
                    break;

                case 'loaded':
                    var list = containers.queueprods.prods.find("ul");
                    //list.length && 
                    //console.log( list, response ); 
                    if ( typeof response != 'undefined' && response ) {
                        if ( misc.hasOwnProperty(response, 'html') ) {
                            list.prepend( response.html );
                        }
                    }
                    is_empty_queue();
                    break;
                    
                case 'selected':
                    // estimate import duration & speed
                    importprod.estimate({
                        speed           : 32,
                        time            : 3600000 // 1000 * 60 * 60 => 60 min => 1 hour
                    });
                    break;
            }
            if ( containers.queueprods.results.find('.aiowaff-stats-' + type + ' span span').length ) {
                containers.queueprods.results.find('.aiowaff-stats-' + type + ' span span').html( asins[type].length );
            }
            //console.log( type ), console.dir( asins[type] );
        };
        
        function export_asins( form ) {
            loading( 'show', lang.loading );

            var box = containers.queueprods.export,
                form = typeof form != 'undefined' ? form : box.find('form#aiowaff-export-form'),
                delimiter = box.find("input[name=aiowaff-export-delimiter]:checked").attr('id').split('radio-'),
                delimiter = delimiter[1],
                export_asins_type = box.find("#aiowaff-export-asins-type").val(),
                _asins = [];

            //if ( delimiter == 'newline' ) {
            //    delimiter = "\n";
            //} else if ( delimiter == 'comma' ) {
            //    delimiter = ",";
            //} else if ( delimiter == 'tab' ) {
            //    delimiter = "\t";
            //}
            
            switch (export_asins_type) {
                // loaded and valid
                case '1':
                    _asins = asins.loaded;
                    break;
                    
                // selected for import
                case '2':
                    _asins = asins.selected;
                    break;
                    
                // imported successfully
                case '3':
                    _asins = asins.imported;
                    break;
                    
                // not imported - with errors
                case '4':
                    _asins = asins.import_errors;
                    break;
                    
                // remained loaded in queue and remained selected in queue
                case '5':
                case '6':
                    var prods = containers.queueprods.prods.find( "ul li" + (export_asins_type == 6 ? '.selected' : '') );
                    $.each( prods, function(key, val) {
                        var that = $(this),
                            asin = that.data('asin').toString();
                        if ($.trim( asin ) != "") {
                            _asins.push( $.trim( asin ) );
                        }
                    });
                    break;
                    
                // found invalid
                case '7':
                    _asins = asins.invalid;
                    break;
            }
        
            if (_asins.length <= 0){
                set_status_msg( 'invalid', lang.export_no_asin, 'export' );
                loading( 'close', lang.export_no_asin );
                return false;
            }
 
            var data = {
                'debug_level'           : debug_level,
                'action'                : 'aiowaffIM_exportASIN',
                'asins'                 : _asins,
                'delimiter'             : delimiter,
                'export_asins_type'     : export_asins_type
            };
            data = $.extend({}, data);
  
            $.post(ajaxurl, data, function(response) {
                if (1) {
                    set_status_msg( response.status, response.msg, 'export' );
                    loading( 'show', response.msg );
                    
                    if( response.status == 'valid' ){
                    } else {
                        loading( 'close' );
                        return false;
                    }

                    // build download link
                    data = $.param( data );
                    var dwurl = ajaxurl + '?' + data + '&do_export=1';
                    //console.log( dwurl );
                    
                    loading( 'close' );
                    
                    // force download        
                    window.location = dwurl;
                    return true;
                }

            }, 'json');
        }

        // Loading
        function loading( status, msg, from ) {
            var msg         = msg || '',
                from        = from || '',
                container = containers.loadprods.mainwrap;

            //if (status == 'close') return false; //debug!
            row_loading( container, status, {html: msg} );
            
            if ( from == '' ) {
                importprod.loading( status, lang.loadprods_inprogress, 'external' );
            }
        };

        function set_status_msg( status, msg, op ) {
            set_status_msg_generic( status, msg, op, 'loadprod' );
        };

        // UTILS
        function array_splice_verify_already_exists(items, array, start, howmany) {
            var start = start || 0,
                howmany = howmany || 'all',
                r = [],
                already_exists = [],
                ret = {},
                cc = 0,
                len = array.length;
            while ( cc < len ) {
                // not found in unique: loaded or invalid or already imported in database
                if( $.inArray(array[start], items['loaded']) == -1
                    && $.inArray(array[start], items['invalid']) == -1
                    && $.inArray(array[start], items['already_imported']) == -1 ) {
                    r.push( array[start] );
                } else {
                    already_exists.push( array[start] );
                }
                array.splice(start, 1);
                if ( howmany !== 'all' && r.length >= howmany ) break;
                cc++;
            }
            var ret = {
                'array'             : array,
                'slice'             : r,
                'already_exists'    : already_exists
            };
            return ret;
        };
       
        function get_asins_inqueue() {
            var asins_inqueue = [];
            asins_inqueue = asins_inqueue.concat( asins.loaded );
            asins_inqueue = asins_inqueue.concat( asins.invalid );
            asins_inqueue = asins_inqueue.concat( asins.already_imported );
            asins_inqueue = misc.arrayUnique( asins_inqueue );
            return asins_inqueue;
        }

        function search_select_pages( category, pms ) {
            var pms         = typeof pms == 'object' ? pms : {},
                provider    = misc.hasOwnProperty(pms, 'provider') ? pms.provider : 'amazon';

            var category    = $.trim( category ),
                nb_pages    = 5, // grab first 5 pages (All categories)
                container   = containers.loadprods[provider].search,
                dropdown    = container.find('.aiowaff-search-nbpages'),
                current     = dropdown.val();
            
            // grab first 10 pages
            if ( category != '' && category != 'AllCategories' ) {
                nb_pages = 10;
            }
            current = current > nb_pages ? nb_pages : current; 

            var html    = [],
                first   = dropdown.find('option:first');
            html.push( '<option value="' + first.val() + '" disabled="disabled">' + first.text() + '</option>' );
            for (var i=1; i<=nb_pages; ++i) {
                var text = i == 1 ? lang.search_pages_single : lang.search_pages_many.replace('%s', i);
                html.push( '<option value="' + i + '">' + text + '</option>' );
            }
            dropdown.html( html.join('') );
            dropdown.val( current );
        };

        function select_category() {
            var search_on   = maincontainer.find("#aiowaff-search-search_on"),
                def_search  = search_on.val(),
                custom      = maincontainer.find("#aiowaff-node"),
                dropdown    = maincontainer.find("#aiowaff-search-category");
            search_on.data('use_categ_field', 'category');
            
            function set_search_on( val )
            {
                val = $.trim( val ); 
                if( val != "" ){
                    search_on.val( val );
                }else{
                    search_on.val( def_search );
                }
            }
            
            custom.on( 'keydown, keyup', function(){
                var that      = $(this),
                    val       = that.val(),
                    text      = dropdown.find('option:selected').text();
    
                set_search_on( val );
                search_select_pages( val );
                if ( $.trim( val ) != "" ) {
                    search_on.data('use_categ_field', 'node');
                } else {
                    search_on.data('use_categ_field', 'category');
                    search_on.val( text );
                }
            });
            dropdown.on( 'change', function(){
                var that      = $(this),
                    val       = that.val(),
                    text      = that.find('option:selected').text();
    
                set_search_on( text );
                
                search_select_pages( val );
                search_on.data('use_categ_field', 'category');
            });
        }
        
        function keyword_autocomplete() {
            var autocomplete = maincontainer.find(".aiowaff-search-completion");
            
            maincontainer.on('keyup', "input#aiowaff-search-keyword.autocomplete", function(){
                
                var that = $(this),
                    data = {
                        'action': 'aiowaffIM_KeywordAutocomplete',
                        'keyword': that.val()
                    };
                
                if( that.val() == "" ) return;
                    
                $.post(ajaxurl, data, function (response) {
                    if( response['status'] == 'valid' ){
                        autocomplete.html('');
                        $.each( response['data'], function(key, value){
                            autocomplete.append( "<li>" + ( value ) + "</li>" );
                        });
                        
                        autocomplete.css( 'display', 'block' );
                    }
                }, 'json');
            });
            
            autocomplete.on('click', 'li', function(){
                var that = $(this),
                    text = that.text();
                
                maincontainer.find("input#aiowaff-search-keyword.autocomplete").val( text );
                autocomplete.html("");
                autocomplete.hide(); 
            });
            
            $("body").on('click', maincontainer, function(){
                if( autocomplete.html() != "" ){
                    autocomplete.html("");
                    autocomplete.hide();
                }
            });
        }

        function get_category_params( category, pms ) {
            loading( 'show', lang.loading );
            
            var pms         = typeof pms == 'object' ? pms : {},
                provider    = misc.hasOwnProperty(pms, 'provider') ? pms.provider : 'amazon';
            
            var box             = containers.loadprods[provider].search,
                categ_wrap      = box.find('.aiowaff-select-on-category'),
                dropdown        = categ_wrap.find(".aiowaff-search-category"),
                category        = category || dropdown;

            var data = {
                'action'        : 'aiowaffIM_getCategoryParams',
                'category'      : category.find('option:selected').val(),
                'nodeid'        : category.find('option:selected').data('nodeid'),
                'provider'      : provider,
                'debug_level'   : debug_level
            };
    
            $.post(ajaxurl, data, function(response) {
                // success
                if (response.status == 'valid') {
                    // remove current parameters
                    box.find('li.aiowaff-param-optional').remove(); //detach()
                    
                    // add new parameters next to category wrapper
                    categ_wrap.after( response.html );
                    
                    (function(){
                        var search_wrap     = containers.loadprods[provider].search,
                            that            = search_wrap.find('select[name="aiowaff-search[Sort]"]');
                        sort_tooltip( that );
                    })();
                }
                loading( 'close' );
                return true;

            }, 'json');
        };

        function get_browse_nodes( that, category, pms ) {
            loading( 'show', lang.loading );
            
            var pms         = typeof pms == 'object' ? pms : {},
                provider    = misc.hasOwnProperty(pms, 'provider') ? pms.provider : 'amazon';
 
            var box             = containers.loadprods[provider].search,
                categ_wrap      = box.find('.aiowaff-select-on-category'),
                dropdown        = categ_wrap.find(".aiowaff-search-category"),
                category        = category || dropdown,
                parent_li       = that.parent(),
                ascensor_value  = that.hasClass('aiowaff-search-category')
                    ? that.find('option:selected').data('nodeid') : that.val(),
                len             = box.find('.aiowaff-param-optional.aiowaff-param-node').length; // prev element value
    
            // max deep
            if ( len >= 10 ){
                loading( 'close' );
                return false;
            }
  
            // remove all browse nodes after current one
            var next = null;
            while( (next = parent_li.next('.aiowaff-param-optional.aiowaff-param-node')).length > 0 ) {
                next.remove();
            }
  
            // store current childrens into array
            if( ascensor_value != "" ){
                var data = {
                    'action'        : 'aiowaffIM_getBrowseNodes',
                    'category'      : category.find('option:selected').val(),
                    'nodeid'        : ascensor_value,
                    'provider'      : provider,
                    'debug_level'   : debug_level
                };

                // make the import
                $.post(ajaxurl, data, function(response) {
                    if( response.status == 'valid' ){
                        parent_li.after( response.html );
                    }
                    loading( 'close' );
                }, 'json');
    
            }else{
                loading( 'close' );
            }
        }

        function is_empty_queue( show ) {
            var show        = show || true,
                msgbox      = containers.queueprods.wrap.find('#aiowaff-queued-message'),
                queue       = containers.queueprods.prods.find('ul li');

            var status = queue.length <= 0 ? true : false;
            show && ( status == true ) ? msgbox.show() : msgbox.hide();
        }

        // external usage
        return {
            // attributes
            'v'                     : get_vars,
            
            // methods
            '__'                    : __,
            'search_select_pages'   : search_select_pages,
            'loading'               : loading,
            'set_status_msg'        : set_status_msg,
            'set_results'           : set_results,
            'is_empty_queue'        : is_empty_queue,
            'get_category_params'   : get_category_params
        };
    })();


    // :: IMPORT PRODUCTS
    var importprod = (function() {
        
        var DEBUG                   = false,
            TEST                    = 0;
        var s                       = {},
            wrap                    = null,
            big_parent              = null,
            screen                  = null,
            process_status          = null, // values: start | run | stop | finished
            import_params           = {}, // the import parameters
            current_progress        = {}, // current step progress: progress & time elapsed
            current_estimate        = {}, // current step estimate: speed & time elapsed
            current_prods_data      = {}, // current step: number of: products, product images, product variations...
            total_prods_data        = {}, // total per import: number of: products, product images, product variations...
            elapsed_prods_data      = {}, // elapsed till current: number of: products, product images, product variations...
            current_prod            = {}, // current & next product to be imported
            current_asin            = '', // current product ASIN 
            logo_level              = 1,
            time_start              = null, // import process start time
            test_loop_current       = 0, // testing to simulate ajax requests
            test_loop_max           = 1000;

        // Test!
        function __() { console.log('__ method'); };
        
        // get public vars
        function get_vars() {
            return $.extend( {}, {
                s                   : s,
                process_status      : process_status,
                import_params       : import_params,
                current_prods_data  : current_prods_data,
                total_prods_data    : total_prods_data,
                elapsed_prods_data  : elapsed_prods_data,
                current_prod        : current_prod,
                current_asin        : current_asin,
                time_start          : time_start
            } );
        };
        
        // init function, autoload
        (function init() {
            // load the triggers
            $(document).ready(function() {
                s           = default_import_settings;
                wrap        = containers.importprods.wrap;
                big_parent  = wrap.parent().parent();
                triggers();
            });
        })();
        
        // init when starting new import
        function init_onstart() {
            time_start          = new Date().getTime(); // in miliseconds
            process_status      = 'start';            
            
            // get the import parameters
            get_parameters();
            
            // get the products data: number of: products, product images, product variations...
            get_products_data({
                'params' : import_params
            });
            // verify if there are products selected for import!
            if ( current_prods_data.nb_prods == 0 ) {
                process_status = 'finished';
                return false;
            }
            total_prods_data = current_prods_data;
            elapsed_prods_data = {
                'nb_prods'          : 0,
                'nb_variations'     : 0,
                'nb_images'         : 0
            };
            
            screen = loading( 'show', lang.loading );

            return true;
        }
        
        // Triggers
        function triggers() {
            // import products
            wrap.on('click', '#aiowaff-import-products-button', function(e) {
                e.preventDefault();
                
                import_products();
                return true;
            });
            
            // stop import
            $('body').on('click', '#aiowaff-import-stop-button', function(e) {
                e.preventDefault();

                var $this       = $(this),
                    is_close    = $this.data('is_close');

                if ( is_close ) {
                    screen.remove();
                    loadprod.loading( 'close', lang.importprods_inprogress, 'external' );
                    return false;
                }

                stop_import();
                return true;
            });
            
            // estimate when click on import parameters
            wrap.on('click', 'input[name^="import-parameters"]', function(e) {
                estimate( {
                    speed          : 105,
                    time           : 25200000 // 1000 * 60 * 60 * 7 => 60 min * 7 => 7 hours
                });
            });
            // default estimate
            estimate( {
                speed          : 105,
                time           : 50400000 // 1000 * 60 * 60 * 14 => 60 min * 14 => 14 hours
            });
        };
        
        // IMPORT PRODUCTS
        function import_products() {

            var operation_id = new Date().getTime(); // in miliseconds
            heartbeat.start( operation_id, 'import' );

            function do_import( is_init ) {
                //console.log( 'import: ', new Date().getTime() );

                var is_init = is_init || false;
                if ( is_init ) {
                    var init_status = init_onstart();
                    if ( !init_status ) {
                        heartbeat.stop();
                        alert( lang.import_empty );
                        return false;
                    }
                }
                
                // remove old imported product from queue
                if ( !$.isEmptyObject(current_prod) && current_prod.current.length ) {
                    current_prod.current.remove();
                }
                
                // verify if empty queue - no more products!
                loadprod.is_empty_queue();

                // current estimate
                estimate({
                    'box_screen'    : screen,
                    'show_screen'   : true,
                    'params'        : import_params
                });

                // current progress bars
                calculate_progress({
                    'box_screen'    : screen,
                    'show_screen'   : true,
                    'params'        : import_params
                });
     
                if ( TEST ) {
                    if ( test_loop_current >= test_loop_max ) {
                        process_status = 'stop';
                        show_process_status( screen, process_status );
                        heartbeat.stop();
                        alert( 'TEST Mode: loop max reached!' );
                        return false;
                    }
                    ++test_loop_current;
                }

                // import process is Stoped!
                if ( $.inArray(process_status, ['stop', 'finished']) > -1 ) {
                    screen.find('.aiowaff-iip-tail ul.WZC-keyword-attached').html('');
                    show_process_status( screen, process_status );
                    heartbeat.stop();
                    return false;
                }

                // current & next product data
                current_prod = get_current_next({
                    'box_screen'    : screen
                });

                // import process is Finished!
                if ( current_prod.current.length <=0 ) {
                    screen.find('.aiowaff-iip-tail ul.WZC-keyword-attached').html('');
                    process_status = 'finished';
                    show_process_status( screen, process_status );
                    heartbeat.stop();
                    return false;
                }

                // here - IMPORT CURRENT PRODUCT
                current_asin = current_prod.current.data('asin').toString();
                var provider_alias = prodid.get_provider_alias( current_asin ),
                    provider       = prodid.get_provider( provider_alias );
                process_status = 'run';
                var current_prod_data = get_prod_data( current_prod.current, {
                    'params' : import_params
                });

                // TESTING
                if ( TEST ) {
                    // add product ASIN to imported list!
                    loadprod.set_results( [current_asin], 'imported', { 'provider' : provider } );
                
                    // elapsed
                    calculate_products_data( 'elapsed', current_prod_data, 'add' );
                    //console.log( total_prods_data, elapsed_prods_data );
                
                    // sleep - in seconds
                    sleep( 2, function() {
                        do_import();
                    });
                }
                // REAL AJAX IMPORT
                else {
                    
                    var data        = [],
                        params      = import_params;
  
                    params = $.param( params ); // turn the result into a query string
                    data.push(
                        {name: 'debug_level',       value: debug_level},
                        {name: 'action',            value: 'aiowaffIM_ImportProduct'},
                        {name: 'asin',              value: current_asin},
                        {name: 'operation_id',      value: operation_id},
                        {name: 'params',            value: params},
                        {name: 'provider',          value: provider}
                    );
                    
                    data = $.param( data ); // turn the result into a query string
                    //console.log( data ); return false;
                    
                    // ajax request
                    $.post(ajaxurl, data, function(response) {
                        
                        set_status_msg( response.status, response.msg, 'import' );

                        // elapsed
                        calculate_products_data( 'elapsed', current_prod_data, 'add' );
                        //console.log( total_prods_data, elapsed_prods_data );

                        // reset import settings
                        if ( misc.hasOwnProperty(response, 'import_settings') ) {
                            s = response.import_settings;
                        }
                            
                        // success
                        if (response.status == 'valid') {
                            
                            // add product ASIN to imported list!
                            loadprod.set_results( [current_asin], 'imported', { 'provider' : provider } );
                            
                            // assets download lightbox
                            if ( misc.hasOwnProperty(response, 'show_download_lightbox')
                                && response.show_download_lightbox == true ) {
                                if ( 1 ) {

                                    //$("#aiowaff-wrapper").append( response.download_lightbox_html );
                                    big_parent.prepend( response.download_lightbox_html );
 
                                    aiowaffAssetDownload.download_asset( 
                                        $('.aiowaff-images-tail').find('li').eq(0), undefined, 100, function() {
                                            $(".aiowaff-asset-download-lightbox").remove();

                                            do_import();
                                        }
                                    );
                                    return true;

                                }
                            }
                        }
                        // error occured
                        else {
                            // add product ASIN to NOT Imported (with Errors) list!
                            loadprod.set_results( [current_asin], 'import_errors', { 'provider' : provider } );
                        }
                        
                        do_import();
                        return true;
                        
                    }, 'json');
            
                }
            }
            do_import( true ); // init cycle
        }
        
        function stop_import() {
            process_status = 'stop';
            show_process_status( screen, 'stop_' );
        }
        
        function get_current_next( pms ) {
            var pms          = typeof pms == 'object' ? pms : {},
                box_screen   = misc.hasOwnProperty(pms, 'box_screen') ? pms.box_screen : screen,
                show         = misc.hasOwnProperty(pms, 'show') ? pms.show : true;
            
            var _current     = containers.queueprods.prods.find('ul li.selected').eq(0),
                current      = _current.clone(),
                _next        = _current.nextAll('.selected').eq(0),
                next         = _next.clone();
 
            if ( show ) {

                // mark current imported product in queue 
                _current.addClass('imported')
                    .find('input').prop('disabled', true);

                // update import screen interface: current & next product
                var ul = box_screen.find('.aiowaff-iip-tail ul.WZC-keyword-attached');
                if ( current.length ) {
                    current.removeClass('selected').addClass('imported')
                        .find('.aiowaff-checked-product').before(
                            '<div class="WZC-product-current">' + lang.current_product_title + '</div>'
                        );
                    current.find('.aiowaff-checked-product input').prop('disabled', true);
                    ul.html( current );
                }
                if ( next.length ) {
                    next.removeClass('selected')
                        .find('.aiowaff-checked-product').before(
                            '<div class="WZC-product-current">' + lang.next_product_title + '</div>'
                        );
                    next.find('.aiowaff-checked-product input').prop('disabled', true);
                    ul.append( next );
                }
            }
            return {
                'current'   : _current,
                'next'      : _next
            };
        }
        
        // PROGRESS
        function calculate_progress( pms ) {
            var pms          = typeof pms == 'object' ? pms : {},
                box_screen   = misc.hasOwnProperty(pms, 'box_screen') ? pms.box_screen : screen,
                show_screen  = misc.hasOwnProperty(pms, 'show_screen') ? pms.show_screen : true,
                params       = {},
                progress     = (TEST == 2) && misc.hasOwnProperty(pms, 'progress') ? pms.progress : 0,
                time         = (TEST == 2) && misc.hasOwnProperty(pms, 'time') ? pms.time : 0,
                progress     = {
                    'nb_prods'          : { 'procent' : 0, 'total' : 0, 'elapsed' : 0 },
                    'nb_variations'     : { 'procent' : 0, 'total' : 0, 'elapsed' : 0 },
                    'nb_images'         : { 'procent' : 0, 'total' : 0, 'elapsed' : 0 }
                };
            var ret = {
                box_screen      : box_screen,
                params          : params,
                progress        : progress,
                time            : time
            };

            // get the import parameters
            params = misc.hasOwnProperty(pms, 'params') ? pms.params : get_parameters( pms );
            ret.params = params;
            //console.log( params );

            var time_end = new Date().getTime(),  // in miliseconds
                duration = time_end - time_start;
                
            for (var i in total_prods_data) {
                if ( misc.hasOwnProperty( total_prods_data, i ) ) {
                    progress[i]['total']    = total_prods_data[i];
                    progress[i]['elapsed']  = elapsed_prods_data[i];
                    progress[i]['procent']  = ( elapsed_prods_data[i] * 100 ) / total_prods_data[i];
                }
            }
            ret.progress = progress;
            ret.time     = duration;
            // show on importing screen
            if ( show_screen ) {
                show_progress( ret );
            }
            current_progress = ret;
            //console.log( current_progress ); 
            return ret;
        }

        function show_progress( pms ) {
            var pms         = typeof pms == 'object' ? pms : {},
                box_screen  = misc.hasOwnProperty(pms, 'box_screen') ? pms.box_screen : screen,
                params      = pms.params,
                progress    = pms.progress,
                time        = pms.time,
                time_txt    = get_time_converted( time );

            var im = {
                'prods'         : box_screen.find('.aiowaff-iip-process-progress-bar.im-products'),
                'images'        : box_screen.find('.aiowaff-iip-process-progress-bar.im-images'),
                'variations'    : box_screen.find('.aiowaff-iip-process-progress-bar.im-variations')
            };
 
            for (var i in im) {
                if ( misc.hasOwnProperty( im, i ) ) {
                    var __im = im[i];
                    
                    if ( __im.length ) {
                        var im_text     = __im.find('.aiowaff-iip-process-progress-text'),
                            im_marker   = __im.find('.aiowaff-iip-process-progress-marker');
 
                        if ( i == 'prods' ) {
                            im_text.find('> span').eq(2).find('span').html( time_txt );
                        }
                        im_text.find('> span').eq(1).find('span').html(
                            lang['parsed_'+i]
                                .replace('%s', progress['nb_'+i]['elapsed'])
                                .replace('%s', progress['nb_'+i]['total'])
                        );
                        im_text.find('> span').eq(0).find('span').html( Math.ceil(progress['nb_'+i]['procent']) + '%' );
                        
                        im_marker.width( progress['nb_'+i]['procent'] + '%' );
                    }
                }
            }
        }
        
        // ESTIMATE
        function estimate( pms ) {
            var pms          = typeof pms == 'object' ? pms : {},
                box          = misc.hasOwnProperty(pms, 'box') ? pms.box : wrap,
                show         = misc.hasOwnProperty(pms, 'show') ? pms.show : true,
                box_screen   = misc.hasOwnProperty(pms, 'box_screen') ? pms.box_screen : screen,
                show_screen  = misc.hasOwnProperty(pms, 'show_screen') ? pms.show_screen : false,
                params       = {},
                prods_data   = {},
                speed        = (TEST == 2) && misc.hasOwnProperty(pms, 'speed') ? pms.speed : 0,
                time         = (TEST == 2) && misc.hasOwnProperty(pms, 'time') ? pms.time : 0;
            var ret = {
                box             : box,
                box_screen      : box_screen,
                speed           : speed,
                time            : time
            };
            //console.log( s );
 
            // get the import parameters
            params = misc.hasOwnProperty(pms, 'params') ? pms.params : get_parameters( pms );
            //console.log( params );
            
            // current step: number of: products, product images, product variations...
            pms.params = params;
            prods_data = misc.hasOwnProperty(pms, 'prods_data') ? pms.prods_data : get_products_data( pms );
            //console.log( prods_data );
            
            // no products in queue or DEBUGing
            if ( prods_data.nb_prods == 0 || (TEST == 2) ) {
                // set speedometer
                if ( show ) {
                    show_estimate( ret );
                }
                // show on importing screen
                if ( show_screen ) {
                    show_estimate_screen( ret );
                }
                current_estimate = ret;
                return ret;
            }
            
            // ESTIMATE
            var _e = []; // estimate rules
            
            // products
            if ( misc.hasOwnProperty( s, 'last_product' ) ) {
                _e.push ( prods_data.nb_prods * s.last_product.media.duration );
            }

            // spin post content
            if ( misc.hasOwnProperty( s, 'last_import_spin' ) ) {
                var found = find_parameter( params, 'spin' );
                if ( found.status && found.value == 'added' ) {
                    _e.push ( prods_data.nb_prods * s.last_import_spin.media.duration );
                }
            }
            
            // import attributes
            if ( misc.hasOwnProperty( s, 'last_import_attributes' ) ) {
                var found = find_parameter( params, 'attributes' );
                if ( found.status && found.value == 'added' ) {
                    _e.push ( prods_data.nb_prods * s.last_import_attributes.media.duration );
                }
            }

            // variations
            if ( misc.hasOwnProperty( s, 'last_import_variations' ) && prods_data.nb_variations > 0 ) {
                _e.push ( prods_data.nb_variations * s.last_import_variations.media.duration );
            }
 
            // images download
            if ( misc.hasOwnProperty( s, 'last_import_images_download' ) && prods_data.nb_images > 0 ) {
                var found = find_parameter( params, 'import_type' );
                if ( found.status && found.value == 'default' ) {
                    _e.push ( prods_data.nb_images * s.last_import_images_download.media.duration );
                
                    // we import only 1 image per variation
                    _e.push ( prods_data.nb_variations * s.last_import_images_download.media.duration );
                }
            }

            // built estimate results
            ret.time = misc.arraySum( _e );
            ret.time += ( ret.time / 3 ); // add an extra safe estimation time: ajax requests etc!
            ret.speed = calculate_speed( ret.time, prods_data.nb_prods );
            //console.log( ret, prods_data, s ); 
            
            // set speedometer
            if ( show ) {
                show_estimate( ret );
            }
            // show on importing screen
            if ( show_screen ) {
                show_estimate_screen( ret );
            }
            current_estimate = ret;
            return ret;
        }
        
        // number of products per minute
        function calculate_speed( time, nb_prods ) {
            var ret     = 0,
                _time   = time;

            _time   = Math.ceil(_time / 1000); // in seconds
            ret     = parseInt( nb_prods * 60 / _time ); // products per minute ( 60 = 60 sec = 1 minute )
            ret     = ret <= 0 ? 1 : ret;
            return ret; 
        }
        
        // get import parameters
        function get_parameters( pms ) {
            var pms          = typeof pms == 'object' ? pms : {},
                box          = misc.hasOwnProperty(pms, 'box') ? pms.box : wrap,
                params       = misc.hasOwnProperty(pms, 'params') ? pms.params : [];
            
            // use cached params
            if ( $.isArray(params) && params.length > 0 ) {
                import_params = params;
                return params;
            }
 
            //import-parameters[import_type]: input, output
            box.find('input[name^="import-parameters"]').each(function (i) {
                var $this   = $(this),
                    type    = $this.prop('type'),
                    name    = $this.prop('name').replace('import-parameters[', '').replace(']', ''),
                    value   = $this.val(),
                    param   = {};

                var add = true;
                if ( type == 'radio' || type == 'checkbox' ) {
                    if ( !$this.prop('checked') ) add = false;
                } else if ( type == 'range' ) {
                    if ( value >= 100 ) value = 'all';
                }

                param = { 'name': name, 'value': value };
                if ( add ) {
                    params.push( param );
                }
            });

            // import in
            params.push( { 'name': 'to-category', 'value': box.find('select#aiowaff-to-category').val() } );

            //console.log( params );
            import_params = params;
            return params;
        }
        
        // current step: number of: products, product images, product variations...
        function get_products_data( pms ) {
            var pms          = typeof pms == 'object' ? pms : {},
                box          = misc.hasOwnProperty(pms, 'box') ? pms.box : wrap,
                params       = misc.hasOwnProperty(pms, 'params') ? pms.params : get_parameters( pms ),
                ret          = {
                    'nb_prods'          : 0,
                    'nb_variations'     : 0,
                    'nb_images'         : 0
                };

            // selected products in queue
            var found_images        = find_parameter( params, 'nbimages' ),
                found_variations    = find_parameter( params, 'nbvariations' );
            containers.queueprods.prods.find('ul li.selected').each(function(i) {
                var __ret = get_prod_data( $(this), {
                    'params'            : params,
                    'found_images'      : found_images,
                    'found_variations'  : found_variations
                });
                for (var i in ret) {
                    if ( misc.hasOwnProperty( ret, i ) ) {
                        ret[i] += __ret[i];
                    }
                }
            });
            current_prods_data = ret;
            return ret;
        }
        
        function get_prod_data( that, pms ) {
            var $this               = that,
                pms                 = typeof pms == 'object' ? pms : {},
                params              = misc.hasOwnProperty(pms, 'params') ? pms.params : get_parameters( pms ),
                found_images        = misc.hasOwnProperty(pms, 'found_images')
                    ? pms.found_images : find_parameter( pms.params, 'nbimages' ),
                found_variations    = misc.hasOwnProperty(pms, 'found_variations')
                    ? pms.found_variations : find_parameter( pms.params, 'nbvariations' ),
                asin                = $this.data('asin').toString(),
                s                   = $this.data('settings'),
                nb_images           = s.nb_images,
                nb_variations       = s.nb_variations,
                ret                 = {
                    'nb_prods'          : 0,
                    'nb_variations'     : 0,
                    'nb_images'         : 0
                };

            //console.log( asin, s );
            if ( found_variations.status && found_variations.value != 'all' ) {
                nb_variations = parseInt( nb_variations > found_variations.value ? found_variations.value : nb_variations );
            }
            if ( found_images.status && found_images.value != 'all' ) {
                nb_images = parseInt( nb_images > found_images.value ? found_images.value : nb_images );
            }
            nb_images += nb_variations;
            ret.nb_images       += parseInt( nb_images );
            ret.nb_variations   += parseInt( nb_variations );
            ++ret.nb_prods;
            return ret;
        }
        
        function calculate_products_data( what, current, operation ) {
            var ret = ( what == 'total' ? total_prods_data : elapsed_prods_data );
  
            for (var i in ret) {
                if ( misc.hasOwnProperty( ret, i ) ) {
                    if ( operation == 'add' ) {
                        ret[i] += current[i];
                    } else {
                        ret[i] -= current[i];
                    }
                }
            }
            
            // return
            what == 'total' ? total_prods_data = ret : elapsed_prods_data = ret;
            //console.log( total_prods_data, elapsed_prods_data );
            return ret;
        }

        function show_estimate( pms ) {
            var pms         = typeof pms == 'object' ? pms : {},
                speed       = pms.speed,
                time        = pms.time,
                level_prev  = logo_level,
                level       = build_logo_levels( speed ),
                cssClass    = 'aiowaff-insane-logo-level' + level,
                text        = lang['speed_level' + level],
                time_txt    = get_time_converted( time );
            logo_level = level;
            
            if ( speed > 0 ) {
                text += ' ' + lang.speed_value.replace('%s', '<strong>'+speed+'</strong>');
            }

            // speedometer index/hand
            change_speedometer_value( speed );
            
            // estimated time text
            time_txt = $.trim( time_txt );
            containers.importprods.time.find('span').html( time_txt );

            // speed logo & text
            containers.importprods.logo.html( '<p>'+text+'</p>' );
            containers.importprods.logo
                .removeClass('aiowaff-insane-logo-level' + level_prev)
                .addClass('aiowaff-insane-logo-level' + level);
        }
        
        function show_estimate_screen( pms ) {
            var pms         = typeof pms == 'object' ? pms : {},
                box_screen  = misc.hasOwnProperty(pms, 'box_screen') ? pms.box_screen : screen,
                speed       = pms.speed,
                time        = pms.time,
                speed_text  = '',
                time_txt    = get_time_converted( time );
            
            //if ( speed > 0 ) {
                speed_text = lang.speed_value.replace('%s', '<strong>'+speed+'</strong>');
                box_screen.find('#aiowaff-iip-estimate-speed').html( speed_text );
            //}

            // estimated time text
            time_txt = $.trim( time_txt );
            box_screen.find('#aiowaff-iip-estimate-time span').html( time_txt );

            if ( time_txt == '' ) {
                //box_screen.find('#aiowaff-iip-estimate-time span').html( '--' );
                show_process_status( box_screen, process_status );
            }
        }
        
        function show_process_status( box_screen, status ) {
            var status      = status || 'run',
                status_html = '';
            
            status_html = lang.process_status_run;
            if ( status == 'stop' ) {
                status_html = lang.process_status_stop;
            } else if ( status == 'stop_' ) {
                status_html = lang.process_status_stop_;
            } else if ( status == 'finished' ) {
                status_html = lang.process_status_finished;
            }

            box_screen.find('#aiowaff-iip-estimate-status span').html( status_html );
            if ( $.inArray(status, ['stop', 'finished', 'stop_']) > -1 ) {
                //box_screen.find('#aiowaff-iip-estimate-status input').addClass('gray').prop('disabled', true);
                box_screen.find('#aiowaff-iip-estimate-status input')
                    .data('is_close', true)
                    .addClass('green').val( lang.btn_close );
            }            
        }

        function build_logo_levels( speed ) {
            var level = 1;
            if ( speed <= 16 ) {
                level = 1;
            } else if ( speed > 16 && speed <= 29 ) {
                level = 2;
            } else if ( speed > 29 && speed <= 42 ) {
                level = 3;
            } else if ( speed > 42 && speed <= 70 ) {
                level = 4;
            } else if ( speed > 70 && speed <= 100 ) {
                level = 5;
            } else if ( speed > 100 ){
                level = 6;
            }
            return level;
        }
        
        // Loading
        function loading( status, msg, from ) {
            var msg         = msg || '',
                from        = from || '';

            if ( from == 'external' ) {
                //if (status == 'close') return false; //debug!
                row_loading( big_parent, status, {html: msg} );
            } else {
            
                var __screen = null;
                
                // close & remove old screen
                __screen = big_parent.find('#aiowaff-iip-screen');
                if ( __screen.length ) {
                    __screen.remove();
                    screen = null;
                }

                // Load import screen                    
                if ( status == 'show' ) {
                    __screen = containers.importprods.screen_tmp.clone();
                    __screen.prop('id', 'aiowaff-iip-screen');
                    
                    // import images
                    var found = find_parameter( import_params, 'import_type' );
                    if ( current_prods_data.nb_images <= 0 || ( found.status && found.value != 'default' ) ) {
                        __screen.find('.aiowaff-iip-process-progress-bar.im-images').remove();
                    }
                    // import variations
                    if ( current_prods_data.nb_variations <= 0 ) {
                        __screen.find('.aiowaff-iip-process-progress-bar.im-variations').remove();
                    }
                    
                    big_parent.prepend( __screen );
                    screen = big_parent.find('#aiowaff-iip-screen');
                    screen.css({
                        'width'     : parseInt( big_parent.outerWidth() ),
                        'height'    : parseInt( big_parent.outerHeight() + 42 ),
                        'top'       : '-42px'
                    });
                    //console.log( screen );
                }
            }
            
            if ( from == '' ) {
                loadprod.loading( status, lang.importprods_inprogress, 'external' );
            }
            return screen;
        };

        function set_status_msg( status, msg, op ) {
            set_status_msg_generic( status, msg, op, 'importprod' );
        };

        // UTILS
        // return an object with properties d, h, m and s: the number of days, hours, minutes, and seconds
        function convert_miliseconds2time( ms ) {
            var d, h, m, s;
            s = Math.floor(ms / 1000);
            m = Math.floor(s / 60);
            s = s % 60;
            h = Math.floor(m / 60);
            m = m % 60;
            d = Math.floor(h / 24);
            h = h % 24;
            return { d: d, h: h, m: m, s: s };
        };
        
        function get_time_converted( ms ) {
            var ret = [],
                time = convert_miliseconds2time( ms ),
                l = { d: lang.day, h: lang.hour, m: lang.min, s: lang.sec };
            
            // days
            for (var i in time) {
                if ( !misc.hasOwnProperty( time, i ) ) continue;
                if ( time[i] > 0 ) {
                    ret.push( time[i] + ' ' + ( time[i] > 1 ? l[i]+'s' : l[i] ) );
                }
            }
            return ret.join(' ');
        }

        function find_parameter( pms, key ) {
            var pms     = typeof pms == 'object' ? pms : {},
                ret     = { status: false, value: null };
            
            for (var i in pms) {
                var current = pms[i];
                if ( typeof current == 'object' && misc.hasOwnProperty( current, 'name' ) ) {
                    if ( current.name == key ) {
                        ret = {
                            status          : true,
                            value           : current.value
                        };
                        return ret;
                    }
                }
            }
            return ret;
        }
        
        function sleep( sec, callback ) {
            var sec         = sec || 1,
                milisec     = sec * 1000;

            if ( $.isFunction(callback) ) {
                var timer = setTimeout( function() {
                    callback();
                }, milisec );
            }
        }
        
        // external usage
        return {
            // attributes
            'v'                         : get_vars,
            
            // methods
            '__'                        : __,
            'loading'                   : loading,
            'set_status_msg'            : set_status_msg,
            'estimate'                  : estimate,
            'calculate_progress'        : calculate_progress,
            'calculate_products_data'   : calculate_products_data,
            'get_prod_data'             : get_prod_data,
            'stop_import'               : stop_import
        };
    })();

    
    // :: Heartbeat
    var heartbeat = (function() {

        var DISABLED                = false; // disable this module!
        var DEBUG                   = false,
            TEST                    = 0,
            interval                = 500, // interval in miliseconds
            timer                   = null, // timer
            for_module              = null, // for which module
            current_status          = null, // current process status
            operation_id            = null, // current operation id
            inner_method            = 'ajax'; // posible values: ajax | cookie

        // Test!
        function __() { console.log('__ method'); };
        
        // get public vars
        function get_vars() {
            return $.extend( {}, {
                current_status : current_status,
                operation_id   : operation_id
            } );
        };
        
        function start( id, mod ) {
            for_module      = mod || 'load';
            operation_id    = id;
            
            if ( DISABLED ) return false;

            var _status = current_status;
            if ( _status != 'run' ) { // not started yet!
                current_status = 'run';
                doit();
            }
        }
        
        function stop() {
            show_response( 'close', lang.closing );
            current_status = 'stop';
            clearTimeout( timer );
            timer = null;
        }
        
        function doit() {
            function _doit() {
                //console.log( 'heartbeat: ', new Date().getTime() );
 
                if ( current_status == 'stop' ) {
                    show_response( 'close', lang.closing );
                    return false;
                }
                current_status = 'run';

                if ( inner_method == 'ajax' ) {

                    var data        = [];
                    data.push(
                        {name: 'debug_level',       value: debug_level},
                        {name: 'action',            value: 'aiowaffIM_InsaneAjax'},
                        {name: 'sub_action',        value: 'heartbeat'},
                        {name: 'operation',         value: for_module},
                        {name: 'operation_id',      value: operation_id}
                    );
                        
                    data = $.param( data ); // turn the result into a query string
                    //console.log( data ); return false;
                        
                    // ajax request
                    $.post(ajaxurl, data, function(response) {
                            
                        // success
                        if (response.status == 'valid') {
                        }
                        // error occured
                        else {
                        }
                        
                        // safe verify!
                        if ( current_status == 'stop' ) {
                            show_response( 'close', lang.closing );
                            return false;
                        }
    
                        show_response( 'show', response.msg );
    
                        timer = setTimeout( function() {
                            _doit();
                        }, interval );
                
                        return true;
                            
                    }, 'json');
                    
                } else if ( inner_method == 'cookie' ) {

                    var resp_msg = cookies.read('aiowaff_opStatusMsg');
                    //console.log( resp_msg );
                    if ( $.trim(resp_msg) != '' ) {
                        resp_msg = decodeURIComponent( resp_msg );
                        resp_msg = JSON && JSON.parse(resp_msg) || $.parseJSON(resp_msg);
                    }

                    show_response( 'show', resp_msg );

                    timer = setTimeout( function() {
                        _doit();
                    }, interval );
                }
            };
            _doit();
        }
        
        function show_response( status, msg ) {
            var container   = null,
                loader      = null,
                loader_txt  = null;

            if ( for_module == 'load' ) {
                container   = containers.loadprods.mainwrap;
                loader      = container.find('.aiowaff-row-loading-marker');
                loader_txt  = loader.find('div.aiowaff-loading-text');
            
            } else if ( for_module == 'import' ) {
                container   = containers.importprods.wrap.parent().parent();
                loader      = container.find('#aiowaff-iip-screen');
                loader_txt  = loader.find('.aiowaff-iip-log');
            }
 
            if ( loader.length ) {
                if ( $.trim( msg ) != '' ) {

                    if ( for_module == 'load' ) {
                        loadprod.loading( status, msg, 'external' );

                    } else if ( for_module == 'import' ) {
                        if ( status == 'close' ) {
                            msg = '';
                        }
                        loader_txt.html( msg );
                    }
                    misc.scrollBottom( loader_txt );
                }
            }
        };
        
        // external usage
        return {
            // attributes
            'v'                     : get_vars,
            
            // methods
            '__'                    : __,
            'start'                 : start,
            'stop'                  : stop
        };
    })();
    
    
    // :: COOKIES
    var cookies = (function(){
        var cookies;
    
        function read(name){
            if(cookies){ return cookies[name]; }
    
            var c = document.cookie.split('; ');
            cookies = {};
    
            for(var i=c.length-1; i>=0; i--){
               var C = c[i].split('=');
               cookies[C[0]] = C[1];
            }
            return cookies[name];
        }
    
        // external usage
        return {
            // attributes
            'read'                     : read
        };
    })();
    
  
    // :: MISC
    var misc = {
    
        hasOwnProperty: function(obj, prop) {
            var proto = obj.__proto__ || obj.constructor.prototype;
            return (prop in obj) &&
            (!(prop in proto) || proto[prop] !== obj[prop]);
        },
    
        arrayHasOwnIndex: function(array, prop) {
            return array.hasOwnProperty(prop) && /^0$|^[1-9]\d*$/.test(prop) && prop <= 4294967294; // 2^32 - 2
        },
    
        arrayIntersect: function(a, b) {
            return $.grep(a, function(i) {
                return $.inArray(i, b) > -1;
            });
        },
        
        arrayUnique: function(array) {
            var a = array.concat();
            for(var i=0; i<a.length; ++i) {
                for(var j=i+1; j<a.length; ++j) {
                    if(a[i] === a[j])
                        a.splice(j--, 1);
                }
            }
            return a;
        },
       
        arrayGetElement: function(array, type) { // second parameter possible values: key | value
            for (var i in array) {
                if (misc.hasOwnProperty(array, i)) {
                    if ( type == 'key' ) return i;
                    return array[i];
                }
            }
        },
       
        arrayRemoveElement: function(array, value) {
            var idx = array.indexOf(value);
            if (idx != -1) array.splice(idx, 1);
            return array;
        },
        
        arraySum: function(array) {
            var total = 0;
            for (var i = 0, n = array.length; i < n; ++i) {
                total += array[i];
            }
            return total;
        },
        
        size: function(obj) {
            var size = 0;
            for (var key in obj) {
                if (misc.hasOwnProperty(obj, key)) size++;
            }
            return size;
        },
        
        get_current_date: function() {
            var UTCstring = (new Date()).toUTCString();
            return UTCstring;
        },
        
        scrollBottom: function( el ) {
            var height = el.scrollHeight || el.prop('scrollHeight');
            el.scrollTop( height );
        },
        
        numberWithCommas: function(x, sep) {
            var sep = sep || ',';
            return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, sep);
            //improvement that fix after '.' problem '123456789.01234'.replace(/\B(?=(?=\d*\.)(\d{3})+(?!\d))/g, '_')
        }
    };

	// external usage
	return {
		"background_loading": background_loading
	}
})(jQuery);

