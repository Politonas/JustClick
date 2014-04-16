<?php 
require 'vendor/autoload.php';

use Anba\JustClick\JustClick;

$jc = new JustClick('test','vume','veluka');
//$cr = $jc->loginJc('http://vume.justclick.ru/shops/sales/');
//var_dump($cr);
//echo $cr->text();
$cr = $jc->getList();
var_dump($cr);
$cr = $jc->getBrief();
var_dump($cr);
?>
