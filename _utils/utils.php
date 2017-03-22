<?php die(); ?>

// get amazon browsenode categories per countries
http://docs.aws.amazon.com/AWSECommerceService/latest/DG/BrowseNodeIDs.html

(function (window, document, $) {
 var ww = $('.informaltable table'), tmp = [], resp = [];
  
  // get first csv row
  ww.find('thead tr th').each(function (i) {
    var $this = $(this);
    var title = $this.text();
    
    title = $.trim( title );
    tmp.push( title );
  });
  resp.push( tmp.join(',') );
  tmp = [];
  
  // get csv content
  ww.find('tbody tr').each(function (i) {
    var $this = $(this);
    
    tmp = [];
    $this.find('td').each(function(ii) {
      var $this2 = $(this);
      var val = $this2.text();
      val = $.trim( val );
      
      tmp.push( val );
    });
    resp.push( tmp.join(',') );

  });

  // generate csv file
  for (var i in resp) {
    var v = resp[i];
    
    console.log( v );
  }
 
})(window, document, jQuery, undefined);