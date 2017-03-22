/*
Document   :  Modules Manager
Author     :  Muhammad Ali 
*/
// Initialization and events code for the app
aiowaffModulesManager = (function ($) {
	"use strict";
	
	// public
	var debug_level = 0;
	var maincontainer = null;
	var mainloading = null;
	var lightbox = null;

	// init function, autoload
	(function init() {
		// load the triggers
		$(document).ready(function(){
			maincontainer = $("#aiowaff-wrapper");
			mainloading = $("#aiowaff-main-loading");
			lightbox = $("#aiowaff-lightbox-overlay");

			triggers();
		});
	})();
	
	function activate_bulk_rows( status ) {
		var ids = [], __ck = $('.aiowaff-form .aiowaff-table input.aiowaff-item-checkbox:checked');
		__ck.each(function (k, v) {
			ids[k] = $(this).attr('name').replace('aiowaff-item-checkbox-', '');
		});
		ids = ids.join(',');
  
 		if (ids.length<=0) {
			alert('You didn\'t select any rows!');
			return false;
		}
  
		mainloading.fadeIn('fast');

		jQuery.post(ajaxurl, {
			'action' 		: 'aiowaffModuleChangeStatus_bulk_rows',
			'id'			: ids,
			'the_status'		: status == 'activate' ? 'true' : 'false',
			'debug_level'		: debug_level
		}, function(response) {
			if( response.status == 'valid' ){
				mainloading.fadeOut('fast');

				//refresh page!
				window.location.reload();
				return false;
			}
			mainloading.fadeOut('fast');
			alert('Problems occured while trying to activate the selected modules!');
		}, 'json');
	}
	
	function triggers()
	{
		maincontainer.on('click', 'input#aiowaff-item-check-all', function(){
			var that = $(this),
				checkboxes = $('.aiowaff-table input.aiowaff-item-checkbox');

			if( that.is(':checked') ){
				checkboxes.prop('checked', true);
			}
			else{
				checkboxes.prop('checked', false);
			}
		});

		maincontainer.on('click', '#aiowaff-activate-selected', function(e){
			e.preventDefault();
  
			if ( confirm('Are you sure you want to activate the selected modules?') ) {
				activate_bulk_rows( 'activate' );
			}
		});
		
		maincontainer.on('click', '#aiowaff-deactivate-selected', function(e){
			e.preventDefault();
  
			if ( confirm('Are you sure you want to deactivate the selected modules?') ) {
				activate_bulk_rows( 'deactivate' );
			}
		});
		
		//all checkboxes are checked by default!
		$('.aiowaff-form .aiowaff-table input.aiowaff-item-checkbox').attr('checked', 'checked');

		if ( $('.aiowaff-form .aiowaff-table input.aiowaff-item-checkbox:checked').length <= 0 ) {
			$('.aiowaff-form .aiowaff-table input#aiowaff-item-check-all').css('display', 'none');
			$('.aiowaff-form input#aiowaff-activate-selected').css('display', 'none');
			$('.aiowaff-form input#aiowaff-deactivate-selected').css('display', 'none');
			$('.aiowaff-list-table-left-col').css('display', 'none');
		}
		
	}

	// external usage
	return {
    	}
})(jQuery);
