<?php 
/*
 * Запрос списка рекламных меток и установка недостающих,
 * если это необходимо.
 */
require 'vendor/autoload.php';

use Anba\JustClick\JustClick;

$jc = new JustClick('test','vume','veluka');
//$jc->debug = true;
// Берётся список наименований партнёрок.
$name_list = $jc->getList( );
// Преобразование списка в нужный вид.
foreach ($name_list as $part_id => $part_name) {
	$part_list[$part_id]['name'] = $part_name;
}
//list( $options, $stats ) = $jc->getStats( 752 );
//foreach( $options as $part_id => $part_name ) {
//	if( isset( $part_list[$part_id] ) ) {
//		$part_list[$part_id]['name'] = $part_name;
//	} else {
//		$part_list[$part_id]['name'] = $part_name;
//	}
//}
/*
$myad_gr_name = 'analytics';
$myad_name = 'herokuapp';
$myad_url = 'http://vume.herokuapp.com/';
foreach( $part_list as $part_id => $part ) {
	//echo "Checking ads for id:$part_id name:{$part['name']}\r\n";
	$part_list[$part_id]['ads'] = $jc->getAds( $part_id );
	//print_r( $part_list[$part_id]['ads'] ); echo "\r\n";
	// Поиск рекламной кампании с указанным названием и запоминание её номера.
	$my_ad_gr_id = 0;
	foreach( $part_list[$part_id]['ads'] as $ad_group_id => $ad_group ) {
		if( $ad_group['name'] == $myad_gr_name ) {
			$my_ad_gr_id = $ad_group_id;
			break;
		}
	}
	// Проверка была ли найдена рекламная кампания с нужным названием.
	// И создание такой кампании, если она не была найдена.
	if( $my_ad_gr_id == 0 ) {
		//echo "Ad group name '$myad_gr_name' not found.\r\n";
		$part_list[$part_id]['ads'] = $jc->setAdsGroupEdit( $part_id, $myad_gr_name );
		//print_r( $part_list[$part_id]['ads'] ); echo "\r\n";
		foreach( $part_list[$part_id]['ads'] as $ad_group_id => $ad_group ) {
			if( $ad_group['name'] == $myad_gr_name ) {
				$my_ad_gr_id = $ad_group_id;
				break;
			}
		}
	}
	// Если номер нужной кампании найден, то ищется нужная метка.
	if( $my_ad_gr_id > 0 ) {
		//echo "Ad group name '$myad_gr_name' found with id:$my_ad_gr_id.\r\n";
		$my_ad_id = 0;
		// Ищется метка с указанным названием.
		foreach( $part_list[$part_id]['ads'][$my_ad_gr_id]['marks'] as $ad_id => $ad ) {
			if( $ad['name'] == $myad_name ) {
				$my_ad_id = $ad_id;
				break;
			}
		}
		// Если метка не найдена, то создаётся.
		if( $my_ad_id == 0 ) {
			//echo "Ad name '$myad_name' not found.\r\n";
			$part_list[$part_id]['ads'] = $jc->setAdsEdit( $part_id, $my_ad_gr_id, $myad_name, $myad_url );
			//print_r( $part_list[$part_id]['ads'] ); echo "\r\n";
			foreach( $part_list[$part_id]['ads'][$my_ad_gr_id]['marks'] as $ad_id => $ad ) {
				if( $ad['name'] == $myad_name ) {
					$my_ad_id = $ad_id;
					break;
				}
			}
		}
		// Если метка найдена, то берётся её URL.
		if( $my_ad_id > 0 ) {
			//echo "Ad name '$myad_name' found with id:$my_ad_id.\r\n";
			//echo "$myad_name ad:\r\n";
			//print_r( $part_list[$part_id]['ads'][$my_ad_gr_id]['marks'][$my_ad_id] );
			//echo "\r\n";
			$url = parse_url( $part_list[$part_id]['ads'][$my_ad_gr_id]['marks'][$my_ad_id]['url'] );
			$click_url = $url['scheme'].'://'.$url['host'].'/click/?ad='.$my_ad_id;
			//$click_url = http_build_url( $url, array( 'path' => '/click/', 'query' => 'ad='.$my_ad_id ) );
			echo $part_id.' = "'.$click_url.'"'."\r\n";
		}
	}
}
*/
//$r = $jc->setAdsGroupEdit( 10590, 'Название' );
//$r = $jc->setAdsEdit( 10590, 23144, 'Пр', 'http://ya.ru' );
$stats = $jc->getAds( 29614 );
print_r($stats);
$stats = $jc->setAdsGroupEdit( 29614, 'test4' );
print_r($stats);
//$doc = phpQuery::newDocument($part_list);
//print_r($doc['html']['head']['title']->text());
//$part_list = $jc->getList( );
//$part_list = $jc->refreshStats( );
//$part_list = $jc->refreshBills( );
// print_r( $part_list );
?>
