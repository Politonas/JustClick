<?php 
require_once 'JcStats.php';

$jc = new JcStats();
$jc->setDebug( true );
$part_list = $jc->getList( );
$part_list = $jc->refreshStats( );
$part_list = $jc->refreshBills( );
// print_r( $part_list );
?>
