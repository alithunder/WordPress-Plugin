/*
    Document   :  aiowaff
    Created on :  2014
    Author     :  Muhammad Ali 
*/

// Initialization and events code for the app
aiowaff = (function ($) {
    "use strict";

    var option = {
        'prefix': "aiowaff"
    };
    
    var t = null,
        ajaxBox = null,
        section = 'dashboard',
        subsection  = '',
        in_loading_section = null,
        topMenu = null;

    function init() 
    {
        $(document).ready(function(){
            
            t = $("div.wrapper-aiowaff");
            ajaxBox = t.find('#aiowaff-ajax-response');
            topMenu = t.find('#aiowaff-topMenu');
            
            if (t.size() > 0 ) {
                fixLayoutHeight();
            }
            
            // plugin depedencies if default!
            if ( $("li#aiowaff-nav-depedencies").length > 0 ) {
                section = 'depedencies';
            }
            
            triggers();
            
            $('div#aiowaff-header #aiowaff-header-bottom #aiowaff-topMenu > ul > li').hover(function(){
                $(this).addClass('active');
            }, function(){
                $(this).removeClass('active');
            });
        });
    }
    
    function ajaxLoading(status) 
    {
        var loading = $('<div id="aiowaff-ajaxLoadingBox" class="aiowaff-panel-widget">loading</div>'); // append loading
        ajaxBox.html(loading);
    }
    
    function makeRequest() 
    {
        // fix for duble loading of js function
        if( in_loading_section == section ){
            return false;
        }
        in_loading_section = section;
        
        // do not exect the request if we are not into our ajax request pages
        if( ajaxBox.size() == 0 ) return false;

        ajaxLoading();
        var data = {
            'action': 'aiowaffLoadSection',
            'section': section
        }; 
        
        jQuery.post(ajaxurl, data, function (response) {
            
            if( response.status == 'redirect' ){
                window.location = response.url;
                return;
            }
            
            if (response.status == 'ok') {
                $("h1.aiowaff-section-headline").html(response.headline);
                ajaxBox.html(response.html);
                
                makeTabs();
                
                if( typeof aiowaffDashboard != "undefined" ){
                    aiowaffDashboard.init();
                }
                
                // find new open
                var new_open = topMenu.find('li#aiowaff-sub-nav-' + section);
                var in_submenu = new_open.parent('.aiowaff-sub-menu');
                
                // close current open menu
                var current_open = topMenu.find(">li.active");
                if( current_open != in_submenu.parent('li') ){
                    current_open.find(".aiowaff-sub-menu").slideUp(250);
                    current_open.removeClass("active");
                }
                
                // open current menu
                in_submenu.find('.active').removeClass('active');
                //new_open.addClass('active');
                
                // check if is into a submenu
                if( in_submenu.size() > 0 ){
                    if( !in_submenu.parent('li').hasClass('active') ){
                        in_submenu.slideDown(100);
                    }
                    //.in_submenu.parent('li').addClass('active');
                }
                
                if( section == 'dashboard' ){
                    topMenu.find(".aiowaff-sub-menu").slideUp(250);
                    topMenu.find('.active').removeClass('active');
                    
                    //topMenu.find('li#aiowaff-nav-' + section).addClass('active');
                }
                
                multiselect_left2right();
            }
        },
        'json');
    }
    
    function installDefaultOptions($btn) {
        var theForm = $btn.parents('form').eq(0),
            value = $btn.val(),
            statusBoxHtml = theForm.find('div.aiowaff-message'); // replace the save button value with loading message
        $btn.val('installing default settings ...').removeClass('blue').addClass('gray');
        if (theForm.length > 0) { // serialiaze the form and send to saving data
            var data = {
                'action': 'aiowaffInstallDefaultOptions',
                'options': theForm.serialize()
            }; // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
            jQuery.post(ajaxurl, data, function (response) {
                if (response.status == 'ok') {
                    statusBoxHtml.addClass('aiowaff-success').html(response.html).fadeIn().delay(3000).fadeOut();
                    setTimeout(function () {
                        window.location.reload()
                    },
                    2000);
                } else {
                    statusBoxHtml.addClass('aiowaff-error').html(response.html).fadeIn().delay(13000).fadeOut();
                } // replace the save button value with default message
                $btn.val(value).removeClass('gray').addClass('blue');
            },
            'json');
        }
    }
    
    function saveOptions ($btn, callback) 
    {
        var theForm = $btn.parents('form').eq(0),
            value = $btn.val(),
            statusBoxHtml = theForm.find('div#aiowaff-status-box'); // replace the save button value with loading message
        $btn.val('saving setings ...').removeClass('green').addClass('gray');
        
        multiselect_left2right(true);

        if (theForm.length > 0) { // serialiaze the form and send to saving data
            var data = {
                'action': 'aiowaffSaveOptions',
                'options': theForm.serialize()
            }; // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
            jQuery.post(ajaxurl, data, function (response) {
                if (response.status == 'ok') {
                    statusBoxHtml.addClass('aiowaff-success').html(response.html).fadeIn().delay(3000).fadeOut();
                    if (section == 'synchronization') {
                        updateCron();
                    }
                    
                } // replace the save button value with default message
                $btn.val(value).removeClass('gray').addClass('green');
                
                if( typeof callback == 'function' ){
                    callback.call();
                }
            },
            'json');
        }
    }
    
    function moduleChangeStatus($btn) 
    {
        var value = $btn.text(),
            the_status = $btn.hasClass('activate') ? 'true' : 'false';
        // replace the save button value with loading message
        $btn.text('saving setings ...');
        var data = {
            'action': 'aiowaffModuleChangeStatus',
            'module': $btn.attr('rel'),
            'the_status': the_status
        };
        // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        jQuery.post(ajaxurl, data, function (response) {
            if (response.status == 'ok') {
                window.location.reload();
            }
        },
        'json');
    }
    
    function updateCron() 
    {
        var data = {
            'action': 'aiowaffSyncUpdate'
        }; // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        jQuery.post(ajaxurl, data, function (response) {},
        'json');
    }
    
    function fixLayoutHeight() 
    {
        var win = $(window),
            aiowaffWrapper = $("#aiowaff-wrapper"),
            minusHeight = 40,
            winHeight = win.height(); // show the freamwork wrapper and fix the height
        aiowaffWrapper.css('min-height', parseInt(winHeight - minusHeight)).show();
        $("div#aiowaff-ajax-response").css('min-height', parseInt(winHeight - minusHeight - 240)).show();
    }
    
    function activatePlugin( $that ) 
    {
        var requestData = {
            'ipc': $('#productKey').val(),
            'email': $('#yourEmail').val()
        };
        if (requestData.ipc == "") {
            alert('Please type your Item Purchase Code!');
            return false;
        }
        $that.replaceWith('Validating your IPC <em>( ' + (requestData.ipc) + ' )</em>  and activating  Please be patient! (this action can take about <strong>10 seconds</strong>)');
        var data = {
            'action': 'aiowaffTryActivate',
            'ipc': requestData.ipc,
            'email': requestData.email
        }; // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        jQuery.post(ajaxurl, data, function (response) {
            if (response.status == 'OK') {
                window.location.reload();
            } else {
                alert(response.msg);
                return false;
            }
        },
        'json');
    }
    
    function ajax_list()
    {
        var make_request = function( action, params, callback ){
            var loading = $("#aiowaff-main-loading");
            loading.show();
 
            // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
            jQuery.post(ajaxurl, {
                'action'        : 'aiowaffAjaxList',
                'ajax_id'       : $(".aiowaff-table-ajax-list").find('.aiowaff-ajax-list-table-id').val(),
                'sub_action'    : action,
                'params'        : params
            }, function(response) {
   
                if( response.status == 'valid' )
                {
                    $("#aiowaff-table-ajax-response").html( response.html );

                    loading.fadeOut('fast');
                }
            }, 'json');
        }

        $(".aiowaff-table-ajax-list").on('change', 'select[name=aiowaff-post-per-page]', function(e){
            e.preventDefault();

            make_request( 'post_per_page', {
                'post_per_page' : $(this).val()
            } );
        })

        .on('change', 'select[name=aiowaff-filter-post_type]', function(e){
            e.preventDefault();

            make_request( 'post_type', {
                'post_type' : $(this).val()
            } );
        })
        
        .on('change', 'select[name=aiowaff-filter-post_parent]', function(e){
            e.preventDefault();

            make_request( 'post_parent', {
                'post_parent' : $(this).val()
            } );
        })

        .on('click', 'a.aiowaff-jump-page', function(e){
            e.preventDefault();

            make_request( 'paged', {
                'paged' : $(this).attr('href').replace('#paged=', '')
            } );
        })

        .on('click', '.aiowaff-post_status-list a', function(e){
            e.preventDefault();

            make_request( 'post_status', {
                'post_status' : $(this).attr('href').replace('#post_status=', '')
            } );
        });
    }
    
    function amzCheckAWS()
    {
        $('body').on('click', '.aiowaffCheckAmzKeys', function (e) {
            e.preventDefault();
            $('#AccessKeyID').val( $.trim( $('#AccessKeyID').val() ) );
            $('.aiowaff-aff-ids input').each(function(){
                $(this).val( $.trim( $(this).val() ) );
            });

            var that = $(this),
                old_value = that.val(),
                submit_btn = that.parents('form').eq(0).find('input[type=submit]');
            
            that.removeClass('blue').addClass('gray');
            that.val('Checking your keys ...'); 
            
            saveOptions(submit_btn, function(){
                
                jQuery.post(ajaxurl, {
                    'action' : 'aiowaffCheckAmzKeys'
                }, function(response) {
                        if( response.status == 'valid' ){
                            alert('WooCommerce Amazon Affiliates was able to connect to Amazon with the specified AWS Key Pair and Associate ID');
                        }
                        else{
                            var msg = 'WooCommerce Amazon Affiliates was not able to connect to Amazon with the specified AWS Key Pair and Associate ID. Please triple-check your AWS Keys and Associate ID.';
                            
                            msg += "\n" + response.msg;
                            alert( msg );
                            
                        }
                        that.val( old_value ).removeClass('gray').addClass('blue');
                }, 'json');
            });
        });
    }
    
    function removeHelp()
    {
        $("#aiowaff-help-container").remove();    
    }
    
    function showHelp( that )
    {
        removeHelp();

        var help_type = that.data('helptype');
        var operation = that.data('operation');
        var html = $('<div class="aiowaff-panel-widget" id="aiowaff-help-container" />');
        
        var btn_close_text = ( operation == 'help' ? 'Close HELP' : 'Close Feedback' );
        html.append("<a href='#close' class='aiowaff-button red' id='aiowaff-close-help'>" + btn_close_text + "</a>")
        if( help_type == 'remote' ){
            var url = that.data('url');
            var content_wrapper = $("#aiowaff-content");
            
            html.append( '<iframe src="' + ( url ) + '" style="width:100%; height: 100%;border: 1px solid #d7d7d7;" frameborder="0" id="aiowaff-iframe-docs"></iframe>' )
            
            content_wrapper.append(html);
            
            // feedback iframe related!
            //var $iframe = $('#aiowaff-iframe-docs'),
        }
    }
    
    function hashChange()
    {
        if ( location.href.indexOf("aiowaff#") != -1 ) {
            // Alerts every time the hash changes!
            if(location.hash != "") {
                section = location.hash.replace("#", '');
                
                var __tmp = section.indexOf('#');
                if ( __tmp == -1 ) subsection = '';
                else { // found subsection block!
                        subsection = section.substr( __tmp+1 );
                        section = section.slice( 0, __tmp );
                    }
                } 
     
                if ( subsection != '' )
                makeRequest([
                    function (s) { scrollToElement( s ) },
                    '#'+subsection
                ]);
            else 
                makeRequest();
            return false;
        }
        if ( location.href.indexOf("=aiowaff") != -1 ) {
            makeRequest();
            return false;
        }
    }
    
    function multiselect_left2right( autselect ) {
        var $allListBtn = $('.multisel_l2r_btn');
        var autselect = autselect || false;
 
        if ( $allListBtn.length > 0 ) {
            $allListBtn.each(function(i, el) {
 
                var $this = $(el), $multisel_available = $this.prevAll('.aiowaff-multiselect-available').find('select.multisel_l2r_available'), $multisel_selected = $this.prevAll('.aiowaff-multiselect-selected').find('select.multisel_l2r_selected');
 
                if ( autselect ) {
                    $multisel_selected.find('option').each(function() {
                        $(this).prop('selected', true);
                    });
                    $multisel_available.find('option').each(function() {
                        $(this).prop('selected', false);
                    });
                } else {

                $this.on('click', '.moveright', function(e) {
                    e.preventDefault();
                    $multisel_available.find('option:selected').appendTo($multisel_selected);
                });
                $this.on('click', '.moverightall', function(e) {
                    e.preventDefault();
                    $multisel_available.find('option').appendTo($multisel_selected);
                });
                $this.on('click', '.moveleft', function(e) {
                    e.preventDefault();
                    $multisel_selected.find('option:selected').appendTo($multisel_available);
                });
                $this.on('click', '.moveleftall', function(e) {
                    e.preventDefault();
                    $multisel_selected.find('option').appendTo($multisel_available);
                });
                
                }
            });
        }
    }
    
    function makeTabs()
    {
        // tabs
        $('ul.tabsHeader').each(function() {
            var child_tab = '', child_tab_s = '';

            // For each set of tabs, we want to keep track of
            // which tab is active and it's associated content
            var $active, $content, $links = $(this).find('a');
            var $content_sub;

            // If the location.hash matches one of the links, use that as the active tab.
            // If no match is found, use the first link as the initial active tab.
            var __tabsWrapper = $(this), __currentTab = $(this).find('li.tabsCurrent').attr('title');
            $active = $( $links.filter('[title="'+__currentTab+'"]')[0] || $links[0] );
            //$active.addClass('active');

            // subtabs per tab!
            var __child_tab = makeTabs_subtabs( $active );
            child_tab = __child_tab.child_tab;
            if ( child_tab != '' ) child_tab_s = '.'+child_tab;
 
            $content = $( '.'+($active.attr('title')) );
            if ( child_tab != '' ) {
                $content_sub = $( '.'+($active.attr('title')) + child_tab_s );
            }

            // Hide the remaining content
            $links.not($active).each(function () {
                $( '.'+($(this).attr('title')) ).hide();
            });
            if ( child_tab != '' )
                $( '.'+($active.attr('title')) ).not( 'ul.subtabsHeader,'+child_tab_s ).hide();

            // Bind the click event handler
            $(this).on('click', 'a', function(e){
                // Make the old tab inactive.
                $active.removeClass('active');
                
                // subtabs per tab!
                var __child_tab = makeTabs_subtabs( $active );
                child_tab = __child_tab.child_tab;
                if ( child_tab != '' ) child_tab_s = '.'+child_tab;

                $content.hide();
                if ( child_tab != '' ) $content_sub.hide();

                // Update the variables with the new link and content
                __currentTab = $(this).attr('title');
                __tabsWrapper.find('li.tabsCurrent').attr('title', __currentTab);
                $active = $(this);
                
                // subtabs per tab!
                var __child_tab = makeTabs_subtabs( $active );
                child_tab = __child_tab.child_tab;
                if ( child_tab != '' ) child_tab_s = '.'+child_tab;

                $content = $( '.'+($(this).attr('title')) );
                if ( child_tab != '' )
                    $content_sub = $( '.'+($(this).attr('title')) + child_tab_s );

                // Make the tab active.
                //$active.addClass('active');
                if ( child_tab != '' ) $content_sub.show();
                else $content.show();

                // Prevent the anchor's default click action
                e.preventDefault();
            });
        });
        
        // subtabs
        $('ul.subtabsHeader').each(function() {
            var parent_tab = $(this).data('parent'), parent_tab_s = '.'+parent_tab;

            // For each set of tabs, we want to keep track of
            // which tab is active and it's associated content
            var $active_sub, $content_sub, $links_sub = $(this).find('a');
 
            // If the location.hash matches one of the links, use that as the active tab.
            // If no match is found, use the first link as the initial active tab.
            var __tabsWrapper = $(this), __currentTab = $(this).find('li.tabsCurrent').attr('title');
            $active_sub = $( $links_sub.filter('[title="'+__currentTab+'"]')[0] || $links_sub[0] );
            //$active_sub.addClass('active');
            $content_sub = $(parent_tab_s + '.'+($active_sub.attr('title')));
            
            // Bind the click event handler
            $(this).on('click', 'a', function(e){
                // Make the old tab inactive.
                $active_sub.removeClass('active');
                $content_sub.hide();

                // Update the variables with the new link and content
                __currentTab = $(this).attr('title');
                __tabsWrapper.find('li.tabsCurrent').attr('title', __currentTab);
                $active_sub = $(this);
                $content_sub = $( parent_tab_s + '.'+($(this).attr('title')) );

                // Make the tab active.
                //$active_sub.addClass('active');
                $content_sub.show();

                // Prevent the anchor's default click action
                e.preventDefault();
            });
        });
    }
    
    function makeTabs_subtabs( active_tab ) {
 
        var ret = { 'child_tab': "" };

        var $subtabsWrapper = $('ul.subtabsHeader').filter(function(i) {
            return ( $(this).data('parent') == active_tab.attr('title') );
        });

            $('ul.subtabsHeader').hide();
        if ( $subtabsWrapper.length > 0 ) {

            $subtabsWrapper.show();

            // For each set of tabs, we want to keep track of
            // which tab is active and it's associated content
            var $active, $links = $subtabsWrapper.find('a');

            // If the location.hash matches one of the links, use that as the active tab.
            // If no match is found, use the first link as the initial active tab.
            var __tabsWrapper = $subtabsWrapper, __currentTab = $subtabsWrapper.find('li.tabsCurrent').attr('title');
            $active = $( $links.filter('[title="'+__currentTab+'"]')[0] || $links[0] );
            //$active.addClass('active');

            ret.child_tab = $active.attr('title');
        }
        return ret;
    }
    
    function triggers() 
    {
        amzCheckAWS();
        amzCheckAWSAlibaba();
        amzCheckAWSEbay();
        
        $(window).resize(function () {
            fixLayoutHeight();
        });
         
        $('body').on('click', '.aiowaff_activate_product', function (e) {
            e.preventDefault();
            activatePlugin($(this));
        });
        $('body').on('click', '.aiowaff-saveOptions', function (e) {
            e.preventDefault();
            saveOptions($(this));
        });
        $('body').on('click', '.aiowaff-installDefaultOptions', function (e) {
            e.preventDefault();
            installDefaultOptions($(this));
        });
        
        $('body').on('click', '#' + option.prefix + "-module-manager a", function (e) {
            e.preventDefault();
            moduleChangeStatus($(this));
        }); // Bind the event.
        
        $('body').on('click', 'input#psp-item-check-all', function(){
            var that = $(this),
                checkboxes = $('#psp-list-table-posts input.psp-item-checkbox');

            if( that.is(':checked') ){
                checkboxes.prop('checked', true);
            }
            else{
                checkboxes.prop('checked', false);
            }
        });

        // Bind the hashchange event.
        /*$(window).on('hashchange', function(){
            hashChange();
        });
        hashChange();*/
        $(window).hashchange(function () { // Alerts every time the hash changes!
            if (location.hash != "") {
                section = location.hash.replace("#!/", '');
                if( t.size() > 0 ) {
                    makeRequest();
                }
            }else{
                if( t.size() > 0 && location.search == "?page=aiowaff" ){
                    makeRequest();
                }
            }
        }) // Trigger the event (useful on page load).
        $(window).hashchange();
        
        ajax_list();
        
        $("body").on('click', "a.aiowaff-show-feedback", function(e){
            e.preventDefault();
            
            showHelp( $(this) );
        });
        
        $("body").on('click', "a.aiowaff-show-docs-shortcut", function(e){
            e.preventDefault();
            
            $("a.aiowaff-show-docs").click();
        });
        
        $("body").on('click', "a.aiowaff-show-docs", function(e){
            e.preventDefault();
            
            showHelp( $(this) );
        });
        
         $("body").on('click', "a#aiowaff-close-help", function(e){
            e.preventDefault();
            
            removeHelp();
        });
        
        multiselect_left2right();
    }
    
    function amzCheckAWSAlibaba()
    {
        $('body').on('click', '.aiowaffCheckKeysAlibaba', function (e) {
            e.preventDefault();

            var that = $(this),
                old_value = that.val(),
                submit_btn = that.parents('form').eq(0).find('input[type=submit]');
            
            that.removeClass('blue').addClass('gray');
            that.val('Checking your keys ...'); 
            
            saveOptions(submit_btn, function(){
                
                jQuery.post(ajaxurl, {
                    'action' : 'aiowaffCheckKeysAlibaba'
                }, function(response) {
                        if( response.status == 'valid' ){
                            alert('WooCommerce Alibaba Affiliates was able to connect to Alibaba with the specified App Key and Tracking ID pair.');
                        }
                        else{
                            var msg = 'WooCommerce Alibaba Affiliates was NOT able to connect to Alibaba with the specified App Key and Tracking ID pairl.' + "\n" + 'Please triple-check your App Key and Tracking ID.';
                            
                            msg += "\n" + 'Error code: ' + response.msg;
                            alert( msg );
                            
                        }
                        that.val( old_value ).removeClass('gray').addClass('blue');
                }, 'json');
            });
        });
    }
    
    function amzCheckAWSEbay()
    {
        $('body').on('click', '.aiowaffCheckKeysEbay', function (e) {
            e.preventDefault();

            var that = $(this),
                old_value = that.val(),
                submit_btn = that.parents('form').eq(0).find('input[type=submit]');
            
            that.removeClass('blue').addClass('gray');
            that.val('Checking your keys ...'); 
            
            saveOptions(submit_btn, function(){
                
                jQuery.post(ajaxurl, {
                    'action' : 'aiowaffCheckKeysEbay'
                }, function(response) {
                        if( response.status == 'valid' ){
                            alert('WooCommerce Ebay Affiliates was able to connect to Ebay with the specified Keys pair.');
                        }
                        else{
                            var msg = 'WooCommerce Ebay Affiliates was NOT able to connect to Ebay with the specified Keys pair.' + "\n" + 'Please triple-check your Keys.';
                            
                            msg += "\n" + 'Error code: ' + response.msg;
                            alert( msg );
                            
                        }
                        that.val( old_value ).removeClass('gray').addClass('blue');
                }, 'json');
            });
        });
    }
    
    init();
    
    return {
        'init'              : init,
        'makeTabs'          : makeTabs,
        'saveOptions'       : saveOptions
    }
})(jQuery);