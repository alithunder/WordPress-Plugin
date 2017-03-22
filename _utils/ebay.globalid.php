<?php die; ?>

http://developer.ebay.com/devzone/finding/CallRef/Enums/GlobalIdList.html
SEE Also: http://developer.ebay.com/DevZone/shopping/docs/CallRef/types/SiteCodeType.html because they differ for some values!
(function (window, document, $, undefined) {
  var ww = $('#bottomhalf #doc table.tableEnum');
  var tmp = [],
      categs = [];
  
  // get csv content
  ww.find('tbody tr').each(function (i) {
    var $this = $(this);
    var categ = {
      'globalid'    : '',
      'sitename'    : '',
      'siteid'      : '',
    },
    categ2index = ['globalid', '', '', 'sitename', 'siteid'];
    
    if ( i == 0 ) return true;
    
    $this.find('td').each(function(ii) {
      var $this2 = $(this);
      if (ii > 4 || $.inArray(ii, [1,2]) > -1) return true;
      var val = columnsParse( ii, $this2 );
      val = $.trim( val );
      
      categ[ categ2index[ii] ] = val;
    });
    
    categs.push( categ );
  });
  //console.log( categs  );
 
  // get category parameters
  function columnsParse( index, column ) {
    
    // globalid
    if ( $.inArray(index, [0, 3, 4]) > -1 ) {
      return $.trim( column.text() );
    }
  }; // end function columnsParse
  
  function generateCSV( what ) {
    var what = what || 'globalid';
    var LN = '\n', TAB = '\t',
        _2TAB = TAB+TAB, _3TAB = TAB+TAB+TAB, _4TAB = TAB+TAB+TAB+TAB;
    LN = '';
    
    // generate php array
    if ( 'globalid' == what ) {
      for (var i in categs) {
        var v = categs[i];
        if ( v.globalid == '' || v.siteid == '' ) continue;

        console.log( _4TAB + "'" + v.globalid + "' " + _4TAB + " => array('" + v.siteid + "', '" + v.sitename + "')," );
        //console.log( v );
      }
    }
  }
  //generateCSV( 'globalid' );
  console.log('UNCOMMENT THE LINES WITH generateCSV function call!');
 
})(window, document, jQuery);