<?php
/**
* Return as json_encode
* 
* ======================
*
* @author		Muhammad Ali
* @version		1.0
*/
global $aiowaff;
$aiowaffDashboard = aiowaffDashboard::getInstance();
echo json_encode(array(
    $tryed_module['db_alias'] =
        'html_validation' => ( $aiowaffDashboard->getBoxes() )
));