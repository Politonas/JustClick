<?php 
/**
* vk.wallpost main class code
*
* @package vk.wallpost
* @author Ayrat Belyaev <xbreaker@gmail.com>
* @copyright (c) 2011 xbreaker
* @license http://creativecommons.org/licenses/by-sa/3.0/legalcode
*/
namespace Anba\JustClick;

use Goutte\Client;
use Guzzle\Http\Client as GuzzleClient;
use Symfony\Component\DomCrawler\Crawler;
use Guzzle\Plugin\Cookie\CookiePlugin;
use Guzzle\Plugin\Cookie\CookieJar\FileCookieJar;

/**
 * Class for getting data from JustClick server.
 *
 * @package    JustClick
 * @author        Anton Bakulev <bakulev@mipt.ru>
 * @copyright  2013-2014 Anton Bakulev <bakulev@mipt.ru>
 * @license    https://raw.githubusercontent.com/bakulev/JustClick/master/LICENSE
 * @link       https://github.com/bakulev/JustClick
 */
class JustClick
{
    private $classname;
    public $username;  
    public $password;
    private $client;

    private $justclick_schema = 'http';
    private $justclick_fqdn = 'justclick.ru';

    public $_ip_h; // Хеш IP адреса для логина.
    public $user;
    public $wall;
    private $_referrer; // Store referrer for requests.
    private $_curl; // Handle for Curl lib.
    private $_dbc_client;
    private $_dbc_username;
    private $_dbc_password;
    private $_cookies   = "default.cookies.curl.txt";
    private $_headers   = 0; //1- помогает при дебаге
    //private $_userAgent = "Mozilla/5.0 (Windows; U; Windows NT 6.1; ru; rv:1.9.2.13) Gecko/20101203 Firefox/3.6.13";
    private $_userAgent = 'Mozilla/5.0 (Linux; U; Android 4.0.4; ru-ru; CyanogenMod-9) AppleWebKit/534.30 Mobile Safari/534.30';
    private $_proxyAddr = 'url:port'; //менять не в этом файла, а при инициализации объекта
    private $_proxyAuth = 'login:password';
    private $_proxyType = 'https'; //socks4|socks5
    public $useProxy    = false;  
    private $_debug = false;
    private $_count = 0;
    
    /**
     * @param string classname
     * @param string username
     * @param string password
     */
    public function __construct( $classname, $username = '', $password = '' )
    {
        $this->classname = $classname;
        if( !empty($username) ) $this->username = $username;
        if( !empty($password) ) $this->password = $password;
        if( empty($this->username) or empty($this->password) ) {
            if( $this->_debug) echo 'No username or password'."\r\n";
            $this->RaiseExeption("No login or pass");
        } else {
            if( $this->_debug) echo 'Saved login and pass '.$this->username.' '.$this->password."\r\n";
        }
        $this->client = new Client();
		// Установка перманентных cookie в файл.
		$cookie_file_name = '/tmp/justclick_cookie.jar';
		$cookiePlugin = new CookiePlugin(new FileCookieJar($cookie_file_name));
		$this->client->getClient()->getEventDispatcher()->addSubscriber($cookiePlugin);
		//echo "Cookies:";var_dump($this->client->getCookieJar()->all());
    }

    public function __destruct() {
        //if( $this->_debug) echo 'destruct vk: '.$this->classname."\r\n";
        //$this->save();
    }

    /** 
    *  Геттеры и сеттеры.
    */
    function __get($var)
    {
        switch ($var) {
        case "id": 
        case "username": 
        case "password": 
        case "ip_h": 
        case "curl":
        case "dbc_client":
        case "dbc_username":
        case "dbc_password":
        case "cookies":
        case "userAgent":
        case "headers":
        case "debug":
            $var = "_" . $var;
            return $this->$var; 
            break;
        default: 
            $this->RaiseExeption("Unknow field '$var'");
            break;
        }
    }
  
    function __set($var, $val)
    {
        switch ($var) {
        case "id": 
        case "username": 
        case "password": 
        case "ip_h": 
        case "curl":
        case "dbc_client":
        case "dbc_username":
        case "dbc_password":
        case "cookies":
        case "userAgent":
        case "headers":
        case "debug":
            $var = "_" . $var;
            return $this->$var = $val; 
            break;
        default: 
            $this->RaiseExeption("Unknow field '$var'");
            break;
        }
    }

    /**
    *   Выбрасывает исключение с указанным тектом
    */
    public static function RaiseExeption($txt,$level=E_USER_NOTICE)
    {
        $trace = debug_backtrace();
        trigger_error(
                    $txt.
                    ' in ' . $trace[0]['file'] .
                    ' on line ' . $trace[0]['line'],
                    E_USER_NOTICE);
    }

    /**
     * Производится авторизация на сайте JustClick.
     * 
     * @param string doneurl
     */
      public function loginJc( $doneurl )
    {
        // Подготовка данных для отправки.
        $get_data = array(
        );
        $post_data = array(
                'doneurl' => $doneurl, //'http://'.$this->username.'.justclick.ru/assistant/howto/',
               'errorurl' => $this->justclick_schema.'://'.$this->username.'.'.$this->justclick_fqdn.'/access/logon/error/?doneurl='.$this->justclick_schema.'%3A%2F%2F'.$this->username.'.'.$this->justclick_fqdn.'%2Fassistant%2Fstats%2Fid%2F10590%2F',
               'password' => $this->password,
              'user_name' => $this->username,
                   'save' => 1
        );
        try {
            // Отправляется запрос на форму авторизации.
            $crawler = $this->client->request('POST', ($this->justclick_schema).'://'.$this->username.'.'.($this->justclick_fqdn).'/access/logon', $post_data);
        } catch (Guzzle\Http\Exception\CurlException $e) {
           echo 'Error #'.$e->getErrorNo().': '.$e->getError()."\r\n";
        }
		echo "Cookies:";var_dump($this->client->getCookieJar()->all());
        // Получение ответа.
        return $crawler;
    }

    public function getBrief( $result_body = false )
    {
		$request_url = $this->justclick_schema.'://'.$this->username.'.'.($this->justclick_fqdn).'/advertise/assistant/brief/';
		// Подготовка данных для отправки.
		$get_data = array(
		);
		$post_data = array(
		);
        try {
            // Отправляется запрос на форму авторизации.
            $crawler = $this->client->request('GET', $request_url);
        } catch (Guzzle\Http\Exception\CurlException $e) {
           echo 'Error #'.$e->getErrorNo().': '.$e->getError()."\r\n";;
        }

		if ($crawler->filter('html:contains("Авторизация в системе")')->count() > 0) {
				$crawler = $this->loginJc($request_url);
		}

		if ($this->_debug) echo $crawler->text();
        if ($crawler->filter('html:contains("Сводка")')->count() > 0) { 
            $brief = array();
			// Запрос дополнительных параметров.
			// Определение количества страниц.
            $pages = $crawler->filter('span.totalpages')->text();
			if ($this->_debug) echo 'Pages: '.$pages."\r\n";
            for( $page = 1; $page <= $pages; $page++ ) {
				if ($this->_debug) echo 'Page '.$page."\r\n";
				$items = $this->getBriefAjax( $page );
				foreach ($items as $item_id => $item) {
                	$brief[$item_id] = $item;
				}
            }
            //if( $this->_debug) echo "Count: ".count($brief)."\r\n";
        } else {
            // Ошибка.
			throw new Exception('Html don\'t contains "Сводка" words.');
        }
        return $brief;
    }

	// cash_total // Выплатят 'Всего заработано Вами'
	// cash_payed // Выплачено 'Вам уже выплачено'
	// cash_debt // Долг 'Вам должны выплатить'
	// subsribers // Подписчики 'Всего от Вас подписчиков'
	// partners // Партнеры 'Всего под Вами партнёров'
	// sold_direct // 'Всего прямых продаж'
	// sold_partn // 'Всего продаж партнёрами'
	// clicks // Переходы 'Всего кликов'
	// payed // Оплачено заказов.
	// payed_sum // Сумма оплаченных заказов.
	// payed_comm // Комиссия с оплаченных заказов.
	// unpayed // Неоплаченных заказов.
	// unpayed_sum // Сумма неоплаченных заказов.
	// unpayed_comm // Комиссия с неоплаченных заказов.
	// canceled // Отменённых заказов.
	// canceled_sum // Сумма отменённых заказов.
	// canceled_comm // Комиссия с отменённых заказов.
    public function getBriefAjax( $page = 1, $result_body = false )
    {
		$request_url = $this->justclick_schema.'://'.$this->username.'.'.($this->justclick_fqdn).'/advertise/assistant/briefajax/';
		// Подготовка данных для отправки.
		$get_data = array(
			'p' => $page,
			'selector' => '',
			'format' => 'view',
			'_' => time().'000'
		);
        $get_query = http_build_query($get_data);
        if (!empty( $get_query )) $request_url .= '?'.$get_query;
		$post_data = array(
		);
        try {
            // Отправляется запрос на форму авторизации.
            $crawler = $this->client->request('GET', $request_url, $post_data);
        } catch (Guzzle\Http\Exception\CurlException $e) {
        	echo 'Error #'.$e->getErrorNo().': '.$e->getError()."\r\n";;
        }

		if ($crawler->filter('html:contains("Авторизация в системе")')->count() > 0) {
				$crawler = $this->loginJc($request_url);
		}

		if ($this->_debug) echo $crawler->text();

        if ($crawler->filter('html:contains("Название партнерки")')->count() > 0) { 
			$items = array();
			// Поиск списка партнёров из выпадающего меню.
			//$options = $crawler->filter('body > div.result > table.standart-view > tr:not(.main-tr):gt(0)');
			$options = $crawler->filterXPath('.//body/div/table/tr[position()>2]');
			$values = $options->each(function (Crawler $node, $i) {
				$name = $node->filterXPath('td[position()=1]/a');
				if(1 === preg_match('/\/advertise\/assistant\/tools\/id\/([0-9]+)\//', $name->attr('href'), $matches)) {
					// Можно и через substr находить номер.
            		//$item_id = trim( substr($tr['td:eq(0) > a']->attr('href'),19), '/' );
					$item_id = $matches[1];
				} else {
					throw new Exception('Can\'t find partner number in '.$href);
				}
				$item = array();
				// Номер партнёрки для контроля. Вообще он в индексе записи содержится.
				$item['id'] = $item_id;
				// Наименование партнёрки.
				$item['name'] = $name->text();
				// Выплачено 'Вам уже выплачено':
				$item['cash_payed'] = strtr(trim($node->filterXPath('td[position()=2]')->html()), array(' '=>''));
				// Выплатят 'Всего заработано Вами':
				$item['cash_total'] = strtr(trim($node->filterXPath('td[position()=3]')->html()), array(' '=>'')); 
				// Долг 'Вам должны выплатить':
				$item['cash_debt'] = strtr(trim($node->filterXPath('td[position()=4]')->html()), array(' '=>''));
				// Переходы 'Всего кликов':
				$item['clicks'] = $node->filterXPath('td[position()=5]')->html();
				// Подписчики 'Всего от Вас подписчиков':
				$item['subsribers'] = $node->filterXPath('td[position()=6]')->html();
				// Партнеры 'Всего под Вами партнёров':
				$item['partners'] = $node->filterXPath('td[position()=7]')->html();
				// 'Всего прямых продаж':
				// $item['sold_direct'] = $row_value;
				// 'Всего продаж партнёрами':
				// $item['sold_partn'] = $row_value;
				//print_r( pq($tr)->html() ); die;
				//echo "{$item['name']} ${item['id']}\r\n";// $payed $pay $debt $clicks $subscribers $partners\r\n";
				$items[$item_id] = $item;
				return array($item_id => $item);;//$node->html();
			});
			foreach($values as $item) {
				//var_dump($item);
				$items[key($item)] = current($item);
			}
			//print_r($items);
		} else {
			throw new Exception('Html don\'t contains "Название партнерки" words.');
		}
        return $items;
    }

 	public function getList(  )
    {
		$request_url = $this->justclick_schema.'://'.$this->username.'.'.($this->justclick_fqdn).'/advertise/assistant/brief/';
		// Подготовка данных для отправки.
		$get_data = array(
		);
		$post_data = array(
		);
        try {
            // Отправляется запрос на форму авторизации.
            $crawler = $this->client->request('GET', $request_url);
        } catch (Guzzle\Http\Exception\CurlException $e) {
           echo 'Error #'.$e->getErrorNo().': '.$e->getError()."\r\n";;
        }

		if ($crawler->filter('html:contains("Авторизация в системе")')->count() > 0) {
				$crawler = $this->loginJc($request_url);
		}

		if ($this->_debug) echo $crawler->text();
        if ($crawler->filter('html:contains("Сводка")')->count() > 0) { 
            $brief = array();
			// Поиск списка партнёров из выпадающего меню.
			$options = $crawler->filter('select.select')->filter('option');
			// Запрос списка партнёрских программ.
			$keys = $options->each(function (Crawler $node, $i) {
				if ($i > 0) return $node->attr('value');
			});
			$values = $options->each(function (Crawler $node, $i) {
				$brief[$node->attr('value')] = $node->text();
				if ($i > 0) return $node->text();
			});
			// Присвоение каждому идентификатору названия партнёрской программы.
			$brief = array_combine($keys, $values);
			unset($brief['']); // Убрать лишний первый элемент массива.
        } else {
            // Ошибка.
			throw new Exception('Html don\'t contains "Сводка" words.');
        }
        return $brief;
    }

    public function getGoods( $part_id )
    {
		$request_url = $this->justclick_schema.'://'.$this->username.'.'.($this->justclick_fqdn).'/advertise/assistant/stats/id/'.$part_id.'/';
        $get_data = array(
        );
        $get_query = http_build_query($get_data);
        if (!empty( $get_query )) $request_ur += '?'.$get_query;
        $post_data = array(
        );
        $post_query = http_build_query( $post_data );
        $result_body = $this->execCurl( $request_url, '', $post_data );
        //echo $result_body;
        $document = phpQuery::newDocument( $result_body );
        $head = $document->find('body > table > tr > td > div.main > div.wrap > div.article > h1')->text();
        //echo $head;
        if( $head === 'Авторизация в системе' ) {
            $result_body = $this->loginJc( $request_url );
            $document = phpQuery::newDocument( $result_body );
        $head = $document->find('body > table > tr > td > div.main > div.wrap > div.article > h1')->text();
        //echo $head;
        }

        $option = $document->find('body > table > tr > td > div.main > div.wrap > table > tr > td > div.article > div > form#formFind > table > tr > td:eq(5) > select > option');
        $good = array();
        foreach ($option as $el) {
            $good_id = pq($el)->attr('value');
            if( !empty($good_id) ) $good[$good_id] = pq($el)->text();
        }
        print_r($good);
        //$r = pq($form)->html();
        return $good;
    }

    public function getStats( $part_id, $result_body = false )
    {
        $brief = false; // Результат запроса. Список партнёрок.
        // Если результат запроса с предидущего шага неизвестен, то запросить его.
        if( ! $result_body ) {
            $request_url = 'http://'.$this->username.'.justclick.ru/assistant/stats/id/'.$part_id.'/';
            //if( $this->_debug) echo $request_url;
            $get_data = array(
            );
            $get_query = http_build_query($get_data);
            if (!empty( $get_query )) $request_url .= '?'.$get_query;
            $post_data = array(
            );
            $post_query = http_build_query( $post_data );
            //echo "Request URL: ".$request_url."\r\n";
            $result_body = $this->execCurl( $request_url, '', $post_data );
            //if( $this->_debug) echo $result_body;
        }
        $doc = phpQuery::newDocument($result_body);
        $title = $doc['html']['head']['title']->text();
        //print_r($title);
        if ( strstr($title, 'Авторизация в системе') // Если не авторизованы.
           && $this->_count++ < 2 // Даётся две попытки авторизации.
        ) {
            //if( $this->_debug) echo "true\r\n";
            $result_body = $this->loginJc( $request_url );
            $brief = $this->getStats( $part_id, $result_body );
        } elseif( strstr($title, 'Общая статистика') ) { 
            $this->_count = 0; 
            $brief = array();
            // Запись списка прартнёрок.
            $list = $doc['html']['body']['select.select'];
            foreach( $list['option'] as $option ) {
                $option = pq($option);
                $item_id = $option->val();
                $item_name = $option->text();
                //if( $this->_debug) echo "$item_id $item_name\r\n";
                $brief[$item_id] = $item_name;
            }
            //if( $this->_debug) print_r($list);
            // Запрос параметров статистики.
            $stat = $this->getStatsAjax( $part_id );
            //if( $this->_debug) print_r($brief);
        } else {
            // Ошибка.
            $brief = false;
        }
        return array($brief, $stat);
    }
    public function getStatsAjax( $part_id, $page = 1 )
    {
        $items = false; // Результат запроса. Список партнёрок.

        $request_url = 'http://vume.justclick.ru/assistant/statsajax/';
        //if( $this->_debug ) echo $request_url;
        $get_data = array(
                  'p' => $page,
           'selector' => '{"id":"'.$part_id.'"}',
             'format' => 'view',
                  '_' => time().'000'
        );
        $get_query = http_build_query($get_data);
        //echo "Get: ".$get_query."\r\n";
        if (!empty( $get_query )) $request_url .= '?'.$get_query;
        //echo $request_url."\r\n";
        $post_data = array(
        );
        $post_query = http_build_query( $post_data );
        //echo $request_url."\r\n";
        $result_body = $this->execCurl( $request_url, '', $post_data );
        //if( $this->_debug) echo $result_body;

        $document = phpQuery::newDocument( $result_body );
        $form = $document->find('table.standart-view');
        //if( $this->_debug) echo $form->html();
        $result = array();
        foreach ($form['tr:gt(0)'] as $el) {
            $el = pq($el);
            $row_title = $el['td:eq(0)']->text();
            $row_value = $el['td:eq(1)']->text();
            //echo "Row: $row_title : $row_value \r\n";
            //echo '"'.pq($el)->html().'"'."\r\n";
            switch( $row_title ) {
            case 'Всего заработано Вами':
                $result['cash_total'] = strtr( rtrim( $row_value, '=Р'), array( ' '=>'', ','=>'.' ) );
                break;
            case 'Вам уже выплачено':
                $result['cash_payed'] =  strtr( rtrim( $row_value, '=Р'), array( ' '=>'', ','=>'.' ) );
                break;
            case 'Вам должны выплатить':
                $result['cash_debt'] =  strtr( rtrim( $row_value, '=Р'), array( ' '=>'', ','=>'.' ) );
                break;
            case 'Подписчиков':
//Выписано счетов
//Оплачено счетов
//Заработано комиссионных
//Выписано счетов посетителями, пришедшими от Ваших партнёров
//Оплачено счетов клиентами, пришедшими от Ваших партнёров
//Заработано комиссионных 2-го уровня (от дохода партнёров)
                $result['subsribers'] = $row_value;
                break;
            case 'Зарегистрировано под Вас партнёров в этот период':
                $result['partners'] = $row_value;
                break;
            case 'Прямых продаж':
                $result['sold_direct'] = $row_value;
                break;
            case 'Продаж партнёрами':
                $result['sold_partn'] = $row_value;
                break;
            case 'Переходов по Вашей ссылке':
                $result['clicks'] = $row_value;
                break;
            }
        }
        //if( $this->_debug) print_r($result);
        return $result;
    }

      public function getBills( $part_id )
    {
        $request_url = 'http://'.$this->username.'.justclick.ru/assistant/billsajax/';
        $get_data = array(
               'p' => 1,  // Номер страницы для отображения.
             'per' => 50, // Количество записей на странице.
        'selector' => '{"id":"'.$part_id.'"}', // Фильтр выборки.
          'format' => 'view'
        //       '_' => 1382338514077
        );
        $get_query = http_build_query($get_data);
        if (!empty( $get_query )) $request_url .= '?'.$get_query;
        $post_data = array(
        );
        $post_query = http_build_query( $post_data );
        //echo "Request URL: ".$request_url."\r\n";
        $result_body = $this->execCurl( $request_url, '', $post_data );
        //if( $this->_debug) echo $result_body;

        $document = phpQuery::newDocument( $result_body );
        $form = $document->find('div.result > table > tr');
        $r = pq($form)->html();
        if( $this->_debug) echo $r;
        $result = array();
        foreach ($form as $el) {
            $row_bill_id = pq($el)->find('td:eq(0)')->text();
            $row_email = pq($el)->find('td:eq(1)')->text();
            $row_phone = pq($el)->find('td:eq(2)')->text();
            $row_name = pq($el)->find('td:eq(3)')->text();
            $row_product = pq($el)->find('td:eq(4)')->text();
            $row_date = pq($el)->find('td:eq(5)')->text();
            $row_summ = trim( pq($el)->find('td:eq(6)')->text() );
            $row_comm = trim( pq($el)->find('td:eq(7)')->text() );
            $row_status = trim( pq($el)->find('td:eq(8)')->text() );

            $row = array();
            $row['bill_id'] = $row_bill_id;
            $row['email'] = $row_email;
            $row['phone'] = $row_phone;
            $row['product'] = $row_product;
            $row['date'] = $row_date;
            $row['summ'] = strtr( rtrim( $row_summ, 'руб.'), array( ' '=>'', ','=>'.' ) );
            $row['comm'] = strtr( rtrim( $row_comm, 'руб.'), array( ' '=>'', ','=>'.' ) );
            if( $row_status == 'Отмена' ) $row['status'] = 'canceled';
            elseif( $row_status == 'Не оплачен' ) $row['status'] = 'unpayed';
            else $row['status'] = 'payed';
            
            if( !empty( $row_status ) ) $result[ $row['bill_id'] ] = $row;
        }
        //print_r($result);
        return $result;
    }

	public function getAds( $part_id )
    {
		$request_url = $this->justclick_schema.'://'.$this->username.'.'.($this->justclick_fqdn).'/advertise/publicity/ads/id/'.$part_id.'/';
		// Подготовка данных для отправки.
		$get_data = array(
		);
		$post_data = array(
		);
        try {
            // Отправляется запрос на форму авторизации.
            $crawler = $this->client->request('GET', $request_url);
        } catch (Guzzle\Http\Exception\CurlException $e) {
			echo 'Error #'.$e->getErrorNo().': '.$e->getError()."\r\n";;
        }

		if ($crawler->filter('html:contains("Авторизация в системе")')->count() > 0) {
				$crawler = $this->loginJc($request_url);
		}

		if ($this->_debug) echo $crawler->text();
        if ($crawler->filter('html:contains("Рекламные кампании")')->count() > 0) { 
            $brief = array();
			// Запрос дополнительных параметров.
			$items = $this->getAdsAjax( $part_id );
			foreach ($items as $item_id => $item) {
				$brief[$item_id] = $item;
			}
        } else {
            // Ошибка.
			throw new Exception('Html don\'t contains "Рекламные кампании" words.');
        }
        return $brief;
    }
    public function getAdsAjax( $part_id, $page = 1 )
    {
		$request_url = $this->justclick_schema.'://'.$this->username.'.'.($this->justclick_fqdn).'/advertise/publicity/adsajax/';
		// Подготовка данных для отправки.
		$get_data = array(
			'p' => $page,
			'selector' => '{"id":"'.$part_id.'"}',
			'format' => 'view',
			'_' => time().'000'
		);
        $get_query = http_build_query($get_data);
        if (!empty( $get_query )) $request_url .= '?'.$get_query;
		$post_data = array(
		);
        try {
            // Отправляется запрос на форму авторизации.
            $crawler = $this->client->request('GET', $request_url, $post_data);
        } catch (Guzzle\Http\Exception\CurlException $e) {
        	echo 'Error #'.$e->getErrorNo().': '.$e->getError()."\r\n";;
        }

		if ($crawler->filter('html:contains("Авторизация в системе")')->count() > 0) {
				$crawler = $this->loginJc($request_url);
		}

		if ($this->_debug) echo $crawler->text();

        if ($crawler->filter('html:contains("Добавить метку")')->count() > 0) { 
			$items = array();
			// Поиск списка партнёров из выпадающего меню.
			$options = $crawler->filterXPath('.//body/div/table[@class="standart-view acordeon"]/tr[@class="acordeon-tit"]');
			//var_dump($options->html());
			$values = $options->each(function (Crawler $node, $i) {
				//echo $node->html()."\r\n\r\n";
				$row = $node->filterXPath('td');
				$ad_name = trim($row->text());
				$ad_id = $row->filterXPath('div[@class="toolbar"]/a[@class="delete-items-group"]')->attr('rel');
				$item = array();
				// Номер партнёрки для контроля. Вообще он в индексе записи содержится.
				$item['id'] = $ad_id;
				// Наименование партнёрки.
				$item['name'] = $ad_name;
				// Добавление к списку.
				$items[$ad_id] = $item;
				return array($ad_id => $item);;//$node->html();
			});
			foreach($values as $item) {
				//var_dump($item);
				$items[key($item)] = current($item);
			}
			//print_r($items);
		} else {
			throw new Exception('Html don\'t contains "Добавить метку" words.');
		}
        return $items;
    }

    public function getAdsLabs( $part_id, $group_id, $page = 1 )
    {
        $items = false; // Результат запроса. Список партнёрок.

        $request_url = 'http://vume.justclick.ru/publicity/adslabs/';
        //if( $this->_debug ) echo $request_url;
        $get_data = array(
           'group_id' => $group_id,
           'selector' => '{"id":"'.$part_id.'"}',
                  '_' => time().'000'
        );
        $get_query = http_build_query($get_data);
        //echo "Get: ".$get_query."\r\n";
        if (!empty( $get_query )) $request_url .= '?'.$get_query;
        //echo $request_url."\r\n";
        $post_data = array(
        );
        $post_query = http_build_query( $post_data );
        //echo $request_url."\r\n";
        $result_body = $this->execCurl( $request_url, '', $post_data );
        //if( $this->_debug) echo $result_body;

        $document = phpQuery::newDocument( $result_body );
        $form = $document->find('div.result-partners > table.standart-view');
        //if( $this->_debug) echo $form->html();
        $result = array();
        foreach ($form['tr'] as $el) {
            $el = pq($el);
            if( !empty($mark_name) and !empty($mark_id) ) {
                //if( $this->_debug) echo $el->html();
                $mark_url = $el['td > div.subcontent > div > a']->attr('href');
                //if( $this->_debug) echo "mark_url = $mark_url\r\n";
                $result[$mark_id]['name'] = $mark_name;
                $result[$mark_id]['url'] = $mark_url;
                unset($mark_name);
                unset($mark_id);
            }
            $mark_name = $el['td.td-left']->text();
            //if( $this->_debug) echo "mark_name = $mark_name\r\n";
            $mark_id = $el['td.td-right > div.toolbar > a.delete-item']->attr('rel');
            //if( $this->_debug) echo "mark_id = $mark_id\r\n";
        }
        //if( $this->_debug) print_r($result);
        return $result;
    }

	/*
	 * Добавление новой рекламной кампании.
	 */
	public function setAdsGroupEdit( $part_id, $group_title )
    {
		$request_url = $this->justclick_schema.'://'.$this->username.'.'.($this->justclick_fqdn).'/advertise/publicity/adsgroupedit/id/'.$part_id.'/';
		// Подготовка данных для отправки.
		$get_data = array(
		);
        $get_query = http_build_query($get_data);
        if (!empty( $get_query )) $request_url .= '?'.$get_query;
		$post_data = array(
			'group_title' => $group_title,
			'save' => 'Сохранить'
		);
        try {
            // Отправляется запрос на форму авторизации.
            $crawler = $this->client->request('POST', $request_url, $post_data);
        } catch (Guzzle\Http\Exception\CurlException $e) {
        	echo 'Error #'.$e->getErrorNo().': '.$e->getError()."\r\n";;
        }

		if ($crawler->filter('html:contains("Авторизация в системе")')->count() > 0) {
				$crawler = $this->loginJc($request_url);
		}

		if ($this->_debug) echo $crawler->text();

        if ($crawler->filter('html:contains("Рекламные кампании")')->count() > 0) { 
            $brief = array();
			// Запрос дополнительных параметров.
			$items = $this->getAdsAjax( $part_id );
			foreach ($items as $item_id => $item) {
				$brief[$item_id] = $item;
			}
		} else {
			throw new Exception('Html don\'t contains "Рекламные кампании" words.');
		}
        return $brief;
    }
      public function setAdsEdit( $part_id, $group_id, $ad_title, $ad_url, $result_body = false )
    {
        $brief = false; // Результат запроса. Список партнёрок.
        // Если результат запроса с предидущего шага неизвестен, то запросить его.
        if( ! $result_body ) {
            $request_url = 'http://'.$this->username.'.justclick.ru/publicity/adsedit/gid/'.$group_id.'/id/'.$part_id.'/';
            //if( $this->_debug) echo $request_url;
            $get_data = array(
            );
            $get_query = http_build_query($get_data);
            if (!empty( $get_query )) $request_url .= '?'.$get_query;
            $post_data = array(
                      'ad_id' => '',
                'ad_group_id' => $group_id,
                   'ad_title' => $ad_title,
                     'ad_url' => $ad_url,
                    'ad_text' => '',
             'ad_external_id' => '',
                       'save' => 'Сохранить'
            );
            $post_query = http_build_query( $post_data );
            //echo "Request URL: ".$request_url."\r\n";
            $result_body = $this->execCurl( $request_url, '', $post_data );
            //if( $this->_debug) echo $result_body;
        }
        $doc = phpQuery::newDocument($result_body);
        $title = $doc['html']['head']['title']->text();
        //if( $this->_debug)print_r($title);
        if ( strstr($title, 'Авторизация в системе') // Если не авторизованы.
           && $this->_count++ < 2 // Даётся две попытки авторизации.
        ) {
            //if( $this->_debug) echo "true\r\n";
            $result_body = $this->loginJc( $request_url );
            $brief = $this->setAdsEdit( $part_id, $group_id, $ad_title, $ad_url, $result_body );
        } elseif( strstr($title, 'Рекламные кампании') ) { 
            $this->_count = 0; 
            // Запрос параметров статистики.
            $brief = $this->getAdsAjax( $part_id );
        } else {
            // Ошибка.
            $brief = false;
        }
        return $brief;
    }
    /** 
    *   Выполнение запроса (вынесено в отдельный метод, для более удобного дебага)
    */
    private function execCurl( $request_url, $referer_url = '', $post_params = '', $request_headers = array(), $is_follow = true, $cookie = '' ) 
    {
        if( !isset($this->_curl) or 'resource'!=gettype($this->_curl) ) {
            // Инициализация CURL
            $this->_curl = curl_init(); 
            //if( $this->_debug) echo "CURL initialize.\r\n";
            // Установка флага отладки.
            //curl_setopt($this->_curl, CURLOPT_VERBOSE, true);
            // Настройка прокси.
            if($this->useProxy)  
            {
              curl_setopt($this->_curl, CURLOPT_PROXY, $this->proxyAddr);
              curl_setopt($this->_curl, CURLOPT_PROXYUSERPWD, $this->proxyAuth);
              if( $this->proxyType == 'socks4' )
                curl_setopt($this->_curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
              if( $this->proxyType == 'socks5' )
                curl_setopt($this->_curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            }  
            curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, 1); 
            @curl_setopt($this->_curl, CURLOPT_FOLLOWLOCATION, 1); 
            curl_setopt($this->_curl, CURLOPT_USERAGENT, $this->userAgent);
            if($this->headers)
              curl_setopt($this->_curl, CURLOPT_HEADER, 1);  
            if($this->_cookies) 
            {
              curl_setopt($this->_curl, CURLOPT_COOKIEJAR,  $this->_cookies);
              curl_setopt($this->_curl, CURLOPT_COOKIEFILE, $this->_cookies); 
            }
        }
        //if( $this->_debug) var_dump($this->_curl);

        $url = parse_url( $request_url );
        $headers = array_merge( array( 
                     'Host'=>$url['host'],
                   'Accept'=>'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
          'Accept-Language'=>'ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4',
        'X-Requested-With' => 'XMLHttpRequest'
               ), $request_headers
        );
        $http_headers = array();
        foreach( $headers as $var => $val ) $http_headers[] = $var.': '.$val;  
        curl_setopt($this->_curl, CURLOPT_URL, $request_url); 
        curl_setopt($this->_curl, CURLOPT_HTTPHEADER, $http_headers); 
        if( !empty($referrer_url) ) curl_setopt($this->_curl, CURLOPT_REFERER, $referrer_url);
        if( !empty($cookie) )curl_setopt( $this->_curl, CURLOPT_COOKIE, $cookie );
        curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($this->_curl, CURLOPT_MAXREDIRS, 3); 
        @curl_setopt($this->_curl, CURLOPT_FOLLOWLOCATION, $is_follow); 
        curl_setopt($this->_curl, CURLOPT_COOKIEJAR, $this->_cookies); 
        curl_setopt($this->_curl, CURLOPT_USERAGENT, $this->userAgent);
        if( !empty($post_params) ) {
            curl_setopt($this->_curl, CURLOPT_POST, 1);  
            curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $post_params);
        }
        curl_setopt($this->_curl, CURLOPT_ENCODING , 'gzip,delate');
        curl_setopt($this->_curl, CURLOPT_HEADER, $this->headers); 

        $result = curl_exec($this->_curl);
        //$result = $this->curl_exec_follow($this->_curl);
        $eo = curl_errno($this->_curl);
        $err = curl_error($this->_curl);
        if($eo > 0) {
              $this->RaiseExeption("CURL error in '$func:$eo' $err");
        }    
        //curl_close($this->_curl);  
        return $result;
    }
  
    // Установка логина и пароля для доступа к капче.
    public function setCaptchaAccess( $username, $password ) {
        $this->_dbc_username = $username;
        $this->_dbc_password = $password;
    }
    // Решение капчи.
    public function getCaptcha( $captcha_url ) {
        echo "Get captcha {$captcha_url}.\r\n";
        $request_url = 'https://m.vk.com'.$captcha_url;
        echo "URL: {$request_url}.\r\n";
        $r = $this->execCurl( $request_url ); 
        if( !isset($this->_dbc_client) ) {
            if( isset($this->_dbc_username) and isset($this->_dbc_password) ) {
                // Put your DBC username & password here.
                // Use DeathByCaptcha_HttpClient() class if you want to use HTTP API.
                $this->_dbc_client = new DeathByCaptcha_SocketClient($this->_dbc_username, $this->_dbc_password);
                $this->_dbc_client->is_verbose = true;
                if( $this->_debug) echo "Your balance is {$this->_dbc_client->balance} US cents\n";
                if( $this->_debug) echo "type: ".gettype($this->_dbc_client);
            } else {
                echo "DeathByCaptcha username or password not set.\r\n";
                die;
            }
        }

        // Put your CAPTCHA image file name, file resource, or vector of bytes,
        // and optional solving timeout (in seconds) here; you'll get CAPTCHA
        // details array on success.
        if ($captcha = $this->_dbc_client->upload('base64:'.base64_encode($r))) {
            echo "CAPTCHA {$captcha['captcha']} uploaded\n";

            sleep(DeathByCaptcha_Client::DEFAULT_TIMEOUT);

            // Poll for CAPTCHA text:
            if ($text = $this->_dbc_client->get_text($captcha['captcha'])) {
                echo "CAPTCHA {$captcha['captcha']} solved: {$text}\n";

                // Report an incorrectly solved CAPTCHA.
                // Make sure the CAPTCHA was in fact incorrectly solved!
                //$client->report($captcha['captcha']);
                return $text;
            }
        }
        echo "Can't solve captcha.\r\n";
        die;
    }
    /** 
    *   Посылаем данные для входа в систему 
    */
    private function auth($start_page = 'http://m.vk.com/') 
    {
        if ($this->username=="") 
        {
          $this->RaiseExeption("Empty login"); 
          return false;
        }
        $login = urlencode($this->username);
        $pass = urlencode($this->password);

        //$start_cookie = 'remixlang=0;remixmdevice=1366/768/1/!!!!!!!'; 
        //$result_body = $this->execCurl( $start_page, '', '', array(), true, $start_cookie ); 
        $result = $this->execCurl( $start_page ); 
        // Результат: [446,false,3,"Вход | ВКонтакте","код html addcslashes","extend(cur,{module:'login'});",false,"_touch"]
        $result_arr = str_getcsv( $result );
        $result_body = stripcslashes( $result_arr[4] );
        preg_match_all('/<form method=\"post\" action=\"(.*?)\">/', $result_body, $pmath1);
        if( !empty($pmath1[1][0]) ) {
            $login_url = $pmath1[1][0];
            $post_params = 'email='.$login.'&pass='.$pass;
            $result_body = $this->execCurl( $login_url, '', $post_params, array('Origin'=>'http://m.vk.com') ); 
echo "RESUTL $result_body END"; die;
        }

        // Проверка результата входа в аккаунт.
        // В случае успешного входа внизу страницы должна присутствовать опция выхода.
        preg_match_all('/login.vk.com\/\?act=logout_mobile(.*?)">/', $result_body, $pmath1);
        if( !empty($pmath1[1][0]) ) $this->user['logout_hash'] = 'https:/login.vk.com/?act=logout_mobile'.$pmath1[1][0];
        preg_match_all('/\/id([0-9]+?)\?act=edit_status\&from=menu/', $result_body, $pmath1);
        if( !empty($pmath1[1][0]) ) $this->user['id'] = $pmath1[1][0];

        if( isset($this->user['logout_hash']) and isset($this->user['id']) ) {
            echo "UserID:".$this->user['id']." Logout:".$this->user['logout_hash']."\r\n";
            return true;
        } else {
            return false;
        }
    }
  
  /** 
  *   Добавляет в альбом фотографию. 
  *   Возвращает массив:
  *                     'user_id'  => пользователь который загрузил фото
  *                     'photo_id' => порядковый номер фото в системе
  *                     'mixed_id' => уникальный photo_id (состоит из user_id + photo_id), который далее 
  *                                   можно передать в makePost и таким образом опубликовать ее на стенке и она появится 
  *                                   у нас в альбоме "Фотографии со стены"  
  */
  private function uploadPhoto($imgURL, $linkTo) 
  {
    $u = urlencode($imgURL);
    $i = urlencode($linkTo);
    $c = $this->getCurl();
    $q = 'act=a_photo&url='.$u.'&image='.$i.'&extra=0&index=1';
    curl_setopt($c, CURLOPT_POST, 1);  
    curl_setopt($c, CURLOPT_REFERER, 'http://vk.com/share.php');
    curl_setopt($c, CURLOPT_POSTFIELDS, $q);
    curl_setopt($c, CURLOPT_URL, 'http://vk.com/share.php');   
    $r = $this->execCurl($c, 'uploadPhoto');
    if(preg_match('/photo_id/i', $r, $o))  
    {
      preg_match_all('/{"user_id":(\d+),"photo_id":(\d+)}/i', $r, $out);
      $f = array(
                 'user_id'  => $out[1][0],
                 'photo_id' => $out[2][0],
                 'mixed_id' => $out[1][0].'_'.$out[2][0]);
      return $f;
    }
    else 
    {
      return false;
    }
  }

    // Запрос хеша для отправки личных сообщений пользователю.
    public function queryVk( $request_url, $get_data, $post_data, $ajax = true  ) 
    {
        $debug_name = 'vk:queryVk';

        // Без этого параметра возвращается полная страница по коду 3, а с ним по коду 0.
        if( $ajax ) $post_data = array_merge( array( '_ajax' => 1 ), $post_data );

        $success = false; // Успешно ли был выполнен запрос.
        $count_login_try = 1; // Количество попыток логина.
        $action = 'try';
        do {
            // Подготовка URL к запросу.
            $url = parse_url( $request_url ); // Разбор URL для редиректа.
            if( !isset($url['scheme']) ) $url['scheme'] = 'https'; // Если протокол не указан, то указываем.
            if( !isset($url['host']) ) $url['host'] = 'm.vk.com'; // Если хост не указан, то указываем.
            // Дополнительный разбор URL, т.к. в процессе редиректов могут интересные данные проскакивать.
            if( isset( $url['query'] ) ) {
                parse_str( $url['query'], $query_get_data );
                echo $debug_name; print_r( $query_get_data );
                if( isset($query_get_data['ip_h']) ) $this->_ip_h = $query_get_data['ip_h'];
                $get_data = array_merge( $query_get_data, $get_data );
            }
            $url['query'] = http_build_query( $get_data );
            $request_url = self::http_build_url( $request_url, $url ); // Сборка обратно URL для запроса.
            echo $debug_name." {$action}: URL: {$request_url}.\r\n";
            echo $debug_name." {$action}: post: ".http_build_query( $post_data ).".\r\n";
            $r = $this->execCurl( $request_url, '', http_build_query( $post_data ) ); 
            // Разбор результатов.
            $result_arr = json_decode($r);
            //print_r($result_arr);
            $result_code = $result_arr[2];
            switch( $result_code ) {
            case '0': // Успешный запрос.
                //print_r($result_arr[3][0]);
                $result_body = $result_arr[3][0];
                $success = true;
                $action = 'finish';
                break;
            case '1': // Редирект запроса на другой URL. Требуется вход в систему или повторный запрос результата.
                $request_url = $result_arr[3]; // Содержится URL для редиректа.
                $action = 'redirect';
                break;
            case '2':
                echo $debug_name." Captcha requested\r\n";
                if( isset( $captcha_url ) ) echo "CAPTCHA FAILED.\r\n";
                //print_r( $result_arr );
                $captcha_url = $result_arr[4];
                $captcha_key = $this->getCaptcha( $captcha_url );
                list( $var1, $var2 ) = explode( '?', $captcha_url, 2 );
                parse_str( $var2, $output ); 
                $captcha_sid = $output['sid'];
                $post_data = array_merge( array( 'captcha_sid' => $captcha_sid, 'captcha_key' => $captcha_key ), $post_data ); // Распознанная капча.
                $action = 'try';
                break;
            case '3': // Отображение целой страницы. Форма логина и результат запроса после логина.
                // Тело ответа.
                $result_body = $result_arr[4];
                // Переключение по заголовку страницы.
                switch( $result_arr[3] ) {
                case 'Вход | ВКонтакте':
                //case 'Мобильная версия ВКонтакте':
                    if( $count_login_try-- <= 0 ) { echo "Incorrect login or password\r\n"; die; }
                    $post_data = array( 'email' => $this->username, 'pass' => $this->password );
                    $document = phpQuery::newDocument( $result_body );
                    $form = $document->find('div.mcont > div.pcont > div.form_item > form');
                    //echo $document->find('div.mcont > div.pcont > div.form_item > form')->html();
                    $request_url = pq($form)->attr('action');
                    $captcha_url = pq($form)->find('img#captcha')->attr('src');
                    $captcha_sid = pq($form)->find('input[name=captcha_sid]')->attr('value');
                    if( !empty( $captcha_sid ) ) {
                        echo "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA\r\n";
                        echo "action $ captcha_sid $captcha_url $captcha_sid\r\n";
                        $captcha_key = $this->getCaptcha( $captcha_url );
                        list( $var1, $var2 ) = explode( '?', $captcha_url, 2 );
                        parse_str( $var2, $output ); 
                        $captcha_sid = $output['sid'];
                        $post_data = array_merge( array( 'captcha_sid' => $captcha_sid, 'captcha_key' => $captcha_key ), $post_data ); // Распознанная капча.
                    }
                    $action = 'try';
                    break;
                case 'Диалоги':
                case 'Стена':
                    //print_r($result_arr[4]);
                    $success = true;
                    $action = 'finish';
                    break;
                case 'Ошибка':
                    $action = 'finish';
                    break;
                default: // Для клуба тут название клуба 'Электрик на дом.Электромонтажные работы в Москве.'
                    echo "UNKNOWN page header {$result_arr[3]}\r\n";
                    $action = 'finish';
                    //die;
                }
                //print_r($result_body);
                // Некоторые страницы доступны и без входа вконтакт. Надо всё равно войти.
                $document = phpQuery::newDocument( $result_body );
                //echo $document->find('div.mhead')->html();
                $hentry = $document->find('div.mhead > a.hb_wrap');
                foreach ($hentry as $el) {
                    //echo '"'.pq($el)->text().'"';
                    if( strstr( pq($el)->find('div.hb_btn')->text(), 'войти' ) ) {
                        $request_url = pq($el)->attr('href');
                        rtrim('/',$request_url);
                        echo $debug_name.' Request URL: '.$request_url."\r\n";
                        $action = 'try';
                    };
                }
                break;
            default:
                echo "UNKNOWN result code {$result_code}\r\n";
                print_r($r);
                die;
            }
        } while ($action != 'finish' );
        return array( $success, $result_body );
    }
  
    // Запрос хеша для отправки личных сообщений пользователю.
    public function getMail( $profile_id ) 
    {
        $debug_name = 'vk:getMail';
        echo $debug_name." Getting mail info for {$profile_id}.\r\n";

        list( $success, $result_body ) = $this->queryVk( $profile_id, array(), array(), false );

        $profile = array(); // Результат работы.
        $dialog = array( );
        if( $success ) {
            // Разбор текста стены.
            $document = phpQuery::newDocument($result_body);
            // Разбор тела документа с помощью phpQuery;
            $service_msg = $document->find('div.mcont > div.pcont > div.service_msg')->text();
            if( !empty( $service_msg ) ) {
                echo $debug_name." $service_msg";
                die;
            } else {
                if( $mail_url = $document->find('div.mcont > div.pcont > div.create_post > form#write_form')->attr('action') ) {
                    $url = parse_url( $mail_url );
                    parse_str( $url['query'], $query );
                    if( isset( $query['hash'] ) and isset( $query['to'] ) ) {
                        $profile['mail'][$this->ip_h] = $query['hash'];
                        $profile['id'] = $query['to'];
                    } else {
                        echo $debug_name." ERROR in parsing\r\n";
                        die;
                    }
                }
                //print_r( $document->find('div.mcont > div.pcont > div.messages')->html() );
                var_dump( count($hentry = $document->find('div.mcont > div.pcont > div.messages > div.msg_item') ));
                if( count( $hentry = $document->find('div.mcont > div.pcont > div.messages > div.msg_item') )<1 )
                    $hentry = $document->find('div.msg_item');
                foreach ($hentry as $el) {
                    //print_r( pq($el)->html() ); die;
                    $msg_url = parse_url( pq($el)->find('div.mi_cont > div.mi_head > a.mi_date')->attr('href') );
                    parse_str( $msg_url['query'], $output );
                    $msg_id = $output['id'];
                    $author = pq($el)->find('div.mi_cont > div.mi_head > a.mi_author')->attr('href');
                    $text = pq($el)->find('div.mi_cont > div.mi_body > div.mi_text')->text();
                    $dialog[$msg_id] = array( 'author' => $author, 'text' => $text );
                }
                $profile['dialog']=$dialog;
                if( count($profile['dialog']) < 3 ) print_r($result_body);
            }
        }
        return $profile;
    }

    // Запрос данных о профиле пользователя.
    public function getProfile( $profile_id ) 
    {
        $debug_name = 'vk:getProfile';
        echo $debug_name." Getting profile of {$profile_id}.\r\n";

        list( $success, $result_body ) = $this->queryVk( $profile_id, array(), array(), false );

        //print_r($result_body);
/*
<div id="mcont" class="mcont">
<div class="pcont fit_box">
<div class="owner_panel profile_panel">

<div class="op_fcont">
<h2 class="op_header">Антон Караев</h2>
<div class="pp_last_activity">заходил сегодня в 1:14</div>
<div class="pp_status">Sunlounger &ndash; Hierbas Ibicencas</div>
<div class="pp_info">22 года, Казань</div>
</div>

<div class="op_block">
<table class="row_table">
<td class="row_table_column" width="50%">
<a class="button wide_button" href="/write222528645">Личное сообщение</a>
</td>
<td class="row_table_last_column" width="50%">
<a class="button wide_button" href="/friends?act=accept&id=222528645&from=profile&hash=c7b1477caf37e2f3a3">Добавить в друзья</a>
</td>
</table>
</div>
</div>

<div class="op_block"><a class="button wide_button" href="/write89816218">Личное сообщение</a></div>
<div class="op_block">
<div class="pp_state">Ирина у Вас в друзьях</div>

*/
        // Разбор текста стены.
        $profile = array();
        // Разбор тела документа с помощью phpQuery;
        $document = phpQuery::newDocument($result_body);
        $service_msg = $document->find('div.mcont > div.pcont > div.service_msg')->text();
        if( !empty( $service_msg ) ) {
            echo $debug_name." service_msg $service_msg";
        } else {
            $profile['name'] = $document->find('div.mcont > div.pcont > div.owner_panel > div.op_fcont > h2.op_header')->text();
            $profile['last_activity'] = $document->find('div.mcont > div.pcont > div.owner_panel > div.op_fcont > div.pp_last_activity')->text();
            $hentry = $document->find('div.mcont > div.pcont > div.owner_panel > div.op_block');
            print_r( $document->find('div.mcont > div.pcont > div.owner_panel > div.op_block')->text() );
            foreach ($hentry as $el) {
                $pp_state = pq($el)->find('div.pp_state')->text();
                //echo $debug_name." pp_state $pp_state\r\n";
                if( strpos( $pp_state, 'у Вас в друзьях' ) ) {
                    $profile['fiend'] = true;
                }
                $button = pq($el)->find('a.button');
                foreach( $button as $bt ) {
                    $text = pq($bt)->text();
                    //echo $debug_name." Button Text: $text\r\n";
                    if( $text === 'Личное сообщение' ) {
                        $profile['write'] = pq($bt)->attr('href');
                    }
                    if( $text === 'Добавить в друзья' ) {
                        $profile['add'][$this->ip_h] = pq($bt)->attr('href');
                    }
                }
            }
        }

        return $profile;
    }

    // Запрос хеша для отправки личных сообщений пользователю.
    public function getMailOld( $profile_id ) 
    {
        echo "vk: Getting mail info for {$profile_id}.\r\n";
        $get_data = array();
        $request_url = 'https://m.vk.com'.$profile_id.'?write';
        // Если запрашивать _ajax=1 то будет только стена пользователя.
        $post_params = '';//_ajax=1'; // Без этого параметра возвращается полная страница по коду 3, а с ним по коду 0.
        $long_body = true;
        $action = 'try';
        do {
            echo "{$action}: URL: {$request_url}.\r\n";
            $r = $this->execCurl( $request_url, '', $post_params ); 
            // Разбор результатов.
            $result_arr = json_decode($r);
            //print_r($result_arr);
            $result_code = $result_arr[2];
            switch( $result_code ) {
            case '0': // Успешный запрос.
                //print_r($result_arr[3][0]);
                $result_body = $result_arr[3][0];
                $long_body = false;
                $action = 'finish';
                break;
            case '1': // Редирект запроса на другой URL. Требуется вход в систему или повторный запрос результата.
                $request_url = $result_arr[3]; // Содержится URL для редиректа.
                $url = parse_url( $request_url ); // Разбор URL для редиректа.
                if( !isset($url['scheme']) ) $url['scheme'] = 'https'; // Если протокол не указан, то указываем.
                if( !isset($url['host']) ) $url['host'] = 'm.vk.com'; // Если хост не указан, то указываем.
                $request_url = http_build_url( $request_url, $url ); // Сборка обратно URL для запроса.
                parse_str( $url['query'], $query );
                print_r( $url );
                print_r( $query );
                if( $query['ip_h'] ) $this->_ip_h = $query['ip_h'];
                $action = 'redirect';
                break;
            case '3': // Отображение целой страницы. Форма логина и результат запроса после логина.
                // Переключение по заголовку страницы.
                switch( $result_arr[3] ) {
                case 'Вход | ВКонтакте':
                    preg_match_all('/<form method=\"post\" action=\"(.*?)\">/', $result_arr[4], $pmath1);
                    $request_url = $pmath1[1][0];
                    parse_str($request_url, $output); print_r($output);
                    $post_params = 'email='.urlencode($this->username).'&pass='.urlencode($this->password);
                    $action = 'try';
                    break;
                case 'Диалоги':
                    //print_r($result_arr[4]);
                    $result_body = $result_arr[4];
                    $action = 'finish';


                    break;
                default: // Для клуба тут название клуба 'Электрик на дом.Электромонтажные работы в Москве.'
                    echo "UNKNOWN page header {$result_arr[3]}\r\n";
                    $result_body = $result_arr[4];
                    $document = phpQuery::newDocument($result_body);
                    echo $document->find('div.mhead > a')->attr('href')."\r\n";
                    $action = 'finish';
                }
                break;
            default:
                echo "UNKNOWN result code {$result_code}\r\n";
                print_r($r);
                die;
            }
        } while ($action != 'finish' );

        //print_r($result_arr);

        // Разбор текста стены.
        $profile = array(); // Результат работы.
        $dialog = array( );
        $document = phpQuery::newDocument($result_body);
        if( $long_body ) {
            // Разбор тела документа с помощью phpQuery;
            $service_msg = $document->find('div.mcont > div.pcont > div.service_msg')->text();
            if( !empty( $service_msg ) ) {
                echo "$service_msg";
                die;
            } else {
                $document = phpQuery::newDocument($result_body);
                $mail_url = $document->find('div.mcont > div.pcont > div.create_post > form#write_form')->attr('action');
                $url = parse_url( $mail_url );
                parse_str( $url['query'], $query );
                if( isset( $query['hash'] ) and isset( $query['to'] ) ) {
                    $profile['mail'][$this->ip_h] = $query['hash'];
                    $profile['id'] = $query['to'];
                } else {
                    echo "ERROR in parsing\r\n";
                    die;
                }
            }

            // Разбор тела документа с помощью phpQuery;
            $hentry = $document->find('div.mcont > div.pcont > div.messages > div.msg_item');
            //print_r( $document->find('div.mcont > div.pcont > div.messages')->html() );
        } else {
            $hentry = $document->find('div.msg_item');
        }
        foreach ($hentry as $el) {
            //print_r( pq($el)->html() ); die;
            $msg_url = parse_url( pq($el)->find('div.mi_cont > div.mi_head > a.mi_date')->attr('href') );
            parse_str( $msg_url['query'], $output );
            $msg_id = $output['id'];
            $author = pq($el)->find('div.mi_cont > div.mi_head > a.mi_author')->attr('href');
            $text = pq($el)->find('div.mi_cont > div.mi_body > div.mi_text')->text();
            $dialog[$msg_id] = array( 'author' => $author, 'text' => $text );
        }
        $profile['dialog']=$dialog;

        return $profile;
    }

    // Отправка сообщения пользователю.
    public function postMail ( $profile_id, $message, $hash, $attach = array() ) 
    {
        echo "Post message to {$profile_id} whith hash {$hash}.\r\n";
        $get_data = array(
                    'act' => 'send',
                     'to' => $profile_id,
                   'from' => 'dialog',
                   'hash' => $hash,
        );
        $get_query = http_build_query($get_data);
        $request_url = 'https://m.vk.com/mail?'.$get_query;
        $post_data = array(
                  '_ajax' => 1,
                'message' => $message
        );
        $post_data = array_merge( $post_data, $attach );
        $post_query = http_build_query( $post_data );
        $action = 'try';
        do {
            echo "{$action}: URL: {$request_url} POST:{$post_query}.\r\n";
            $r = $this->execCurl( $request_url, 'https://m.vk.com/mail?act=show&peer='.$profile_id, $post_query ); 
            // Разбор результатов.
            $result_arr = json_decode($r);
            //print_r($result_arr);
            $result_code = $result_arr[2];
            switch( $result_code ) {
            case '0': // Успешный запрос.
                if ( $request_url === 'https://m.vk.com/write843880?write' ) echo "AAAAAAAAAAAAAAAA\r\n";
                //print_r($result_arr[3][0]);
                $result_body = $result_arr[3][0];
                $action = 'finish';
                break;
            case '1': // Редирект запроса на другой URL. Требуется вход в систему или повторный запрос результата.
                $request_url = $result_arr[3]; // Содержится URL для редиректа.
                $url = parse_url( $request_url ); // Разбор URL для редиректа.
                if( !isset($url['scheme']) ) $request_url = 'https://m.vk.com'.$request_url;
                $action = 'redirect';
                break;
            case '2':
                if( isset( $captcha_url ) ) echo "CAPTCHA FAILED.\r\n";
                print_r($result_arr);
                $captcha_url = $result_arr[4];
                $captcha_key = $this->getCaptcha( $captcha_url );
                list( $var1, $var2 ) = explode( '?', $captcha_url, 2 );
                parse_str( $var2, $output ); 
                $captcha_sid = $output['sid'];
                $post_data = array_merge( array( 'captcha_sid' => $captcha_sid, 'captcha_key' => $captcha_key ), $post_data ); // Распознанная капча.
                $post_query = http_build_query( $post_data );
                $action = 'try';
                break;
            case '3': // Отображение целой страницы. Форма логина и результат запроса после логина.
                // Переключение по заголовку страницы.
                switch( $result_arr[3] ) {
                case 'Вход | ВКонтакте':
                    preg_match_all('/<form method=\"post\" action=\"(.*?)\">/', $result_arr[4], $pmath1);
                    $request_url = $pmath1[1][0];
                    $post_query = 'email='.urlencode($this->username).'&pass='.urlencode($this->password);
                    $action = 'try';
                    break;
                case 'Диалоги':
                    //print_r($result_arr[4]);
                    $result_body = $result_arr[4];
                    /*if ( $request_url === 'https://m.vk.com/write843880?write' ) {
                        $request_url = 'https://m.vk.com/mail?'.$get_query;
                        $action = 'try';
                    } else {*/
                        $action = 'finish';
                    //}
                    break;
                case 'Ошибка':
                    $result_body = $result_arr[4];
                    $action = 'finish';
                    break;
                case 'Подтверждение':
                    print_r( $result_arr );
                    die;
                default:
                    echo "UNKNOWN page header {$result_arr[3]}\r\n";
                    print_r( $result_arr );
                    die;
                }
                break;
            default:
                echo "UNKNOWN result code {$result_code}\r\n";
                print_r($r);
                die;
            }
        } while ($action != 'finish' );

        //print_r($result_arr);

        // Разбор текста стены.
        $dialog = array( );
        // Разбор тела документа с помощью phpQuery;
        $document = phpQuery::newDocument($result_body);
        //$hentry = $document->find('div.mcont > div.pcont > div.messages > div.msg_item');
        $hentry = $document->find('div.msg_item'); // Если _ajax=1, то сразу msg_item показывается.
        //print_r( $document->find('div.mcont > div.pcont > div.messages')->html() );
        $self = true;
        foreach ($hentry as $el) {
            //print_r( pq($el)->html() ); die;
            $msg_url = parse_url( pq($el)->find('div.mi_cont > div.mi_head > a.mi_date')->attr('href') );
            parse_str( $msg_url['query'], $output );
            $msg_id = $output['id'];
            $author = pq($el)->find('div.mi_cont > div.mi_head > a.mi_author')->attr('href');
            $text = pq($el)->find('div.mi_cont > div.mi_body > div.mi_text')->text();
            $dialog[$msg_id] = array( 'author' => $author, 'text' => $text );
            if( $self ) { $dialog[$msg_id]['self'] = true; $self = false; } // Для простоты пометка последнего собощения собственным.
        }
        $profile['dialog']=$dialog;

        echo "ALL DONE\r\n";
        return $profile;
    }

    // Запрос данных о профиле пользователя.
    public function getProfileOld( $profile_id ) 
    {
        echo "Getting profile of {$profile_id}.\r\n";
        $get_data = array();
        $request_url = 'https://m.vk.com'.$profile_id;
        // Если запрашивать _ajax=1 то будет только стена пользователя.
        $post_params = '';//_ajax=1'; // Без этого параметра возвращается полная страница по коду 3, а с ним по коду 0.
        $action = 'try';
        do {
            echo "{$action}: URL: {$request_url}.\r\n";
            $r = $this->execCurl( $request_url, '', $post_params ); 
            // Разбор результатов.
            $result_arr = json_decode($r);
            //print_r($result_arr);
            $result_code = $result_arr[2];
            switch( $result_code ) {
            case '0': // Успешный запрос.
                //print_r($result_arr[3][0]);
                $result_body = $result_arr[3][0];
                $action = 'finish';
                break;
            case '1': // Редирект запроса на другой URL. Требуется вход в систему или повторный запрос результата.
                $request_url = $result_arr[3]; // Содержится URL для редиректа.
                $url = parse_url( $request_url ); // Разбор URL для редиректа.
                if( !isset($url['scheme']) ) $url['scheme'] = 'https'; // Если протокол не указан, то указываем.
                if( !isset($url['host']) ) $url['host'] = 'm.vk.com'; // Если хост не указан, то указываем.
                $request_url = http_build_url( $request_url, $url ); // Сборка обратно URL для запроса.
                parse_str( $url['query'], $query );
                print_r( $url );
                print_r( $query );
                if( $query['ip_h'] ) $this->_ip_h = $query['ip_h'];
                $action = 'redirect';
                break;
            case '3': // Отображение целой страницы. Форма логина и результат запроса после логина.
                // Переключение по заголовку страницы.
                switch( $result_arr[3] ) {
                case 'Вход | ВКонтакте':
                    preg_match_all('/<form method=\"post\" action=\"(.*?)\">/', $result_arr[4], $pmath1);
                    $request_url = $pmath1[1][0];
                    parse_str($request_url, $output); print_r($output);
                    $post_params = 'email='.urlencode($this->username).'&pass='.urlencode($this->password);
                    $action = 'try';
                    break;
                case 'Стена':
                    //print_r($result_arr[4]);
                    $result_body = $result_arr[4];
                    $action = 'finish';
                    break;
                default: // Для клуба тут название клуба 'Электрик на дом.Электромонтажные работы в Москве.'
                    echo "UNKNOWN page header {$result_arr[3]}\r\n";
                    $result_body = $result_arr[4];
                    $document = phpQuery::newDocument($result_body);
                    echo $document->find('div.mhead > a')->attr('href')."\r\n";
                    $action = 'finish';
                }
                break;
            default:
                echo "UNKNOWN result code {$result_code}\r\n";
                print_r($r);
                die;
            }
        } while ($action != 'finish' );

        //print_r($result_body);
/*
<div id="mcont" class="mcont">
<div class="pcont fit_box">
<div class="owner_panel profile_panel">

<div class="op_fcont">
<h2 class="op_header">Антон Караев</h2>
<div class="pp_last_activity">заходил сегодня в 1:14</div>
<div class="pp_status">Sunlounger &ndash; Hierbas Ibicencas</div>
<div class="pp_info">22 года, Казань</div>
</div>

<div class="op_block">
<table class="row_table">
<td class="row_table_column" width="50%">
<a class="button wide_button" href="/write222528645">Личное сообщение</a>
</td>
<td class="row_table_last_column" width="50%">
<a class="button wide_button" href="/friends?act=accept&id=222528645&from=profile&hash=c7b1477caf37e2f3a3">Добавить в друзья</a>
</td>
</table>
</div>
</div>

<div class="op_block"><a class="button wide_button" href="/write89816218">Личное сообщение</a></div>
<div class="op_block">
<div class="pp_state">Ирина у Вас в друзьях</div>

*/
        // Разбор текста стены.
        $profile = array();
        // Разбор тела документа с помощью phpQuery;
        $document = phpQuery::newDocument($result_body);
        $service_msg = $document->find('div.mcont > div.pcont > div.service_msg')->text();
        if( !empty( $service_msg ) ) {
            echo "$service_msg";
        } else {
            $profile['name'] = $document->find('div.mcont > div.pcont > div.owner_panel > div.op_fcont > h2.op_header')->text();
            $profile['last_activity'] = $document->find('div.mcont > div.pcont > div.owner_panel > div.op_fcont > div.pp_last_activity')->text();
            $hentry = $document->find('div.mcont > div.pcont > div.owner_panel > div.op_block');
            print_r( $document->find('div.mcont > div.pcont > div.owner_panel > div.op_block')->text() );
            foreach ($hentry as $el) {
                $pp_state = pq($el)->find('div.pp_state')->text();
                echo "pp_state $pp_state\r\n";
                if( strpos( $pp_state, 'у Вас в друзьях' ) ) {
                    $profile['fiend'] = true;
                }
                $button = pq($el)->find('a.button');
                foreach( $button as $bt ) {
                    $text = pq($bt)->text();
                    echo "Button Text: $text\r\n";
                    if( $text === 'Личное сообщение' ) {
                        $profile['write'] = pq($bt)->attr('href');
                    }
                    if( $text === 'Добавить в друзья' ) {
                        $profile['add'][$this->ip_h] = pq($bt)->attr('href');
                    }
                }
            }
        }

        return $profile;
    }
    public function http_build_url( $request_url, $url ) {
        $result_url = $url['scheme'].'://'.$url['host'].$url['path'];
        if( isset( $url['query'] ) ) $result_url .= '?'.$url['query'];
        return $result_url;
    }
    // Возможные значения all, online, suggestions
    public function getMembers( $group_id ) 
    {
        echo "Getting {$group_id} members.\r\n";
        $get_data = array();
        $get_query = http_build_query($get_data);
        $request_url = 'https://m.vk.com'.$group_id.'?act=members';
        $post_params = '_ajax=1'; // Без этого параметра возвращается полная страница по коду 3, а с ним по коду 0.
        $action = 'try';
        do {
            echo "{$action}: URL: {$request_url}.\r\n";
            $r = $this->execCurl( $request_url, '', $post_params ); 
            // Разбор результатов.
            $result_arr = json_decode($r);
            //print_r($result_arr);
            $result_code = $result_arr[2];
            switch( $result_code ) {
            case '0': // Успешный запрос.
                //print_r($result_arr[3][0]);
                $result_body = $result_arr[3][0];
                $action = 'finish';
                break;
            case '1': // Редирект запроса на другой URL. Требуется вход в систему или повторный запрос результата.
                $request_url = $result_arr[3]; // Содержится URL для редиректа.
                $url = parse_url( $request_url ); // Разбор URL для редиректа.
                if( !isset($url['scheme']) ) $url['scheme'] = 'https'; // Если протокол не указан, то указываем.
                if( !isset($url['host']) ) $url['host'] = 'm.vk.com'; // Если хост не указан, то указываем.
                echo "request_url $request_url\r\n";
                $request_url = self::http_build_url( $request_url, $url ); // Сборка обратно URL для запроса.
                echo "request_url $request_url\r\n";
                print_r( $url );
                if( isset( $url['query'] ) ) {
                    parse_str( $url['query'], $query );
                    print_r( $query );
                    if( $query['ip_h'] ) $this->_ip_h = $query['ip_h'];
                }
                $action = 'redirect';
                break;
            case '3': // Отображение целой страницы. Форма логина и результат запроса после логина.
                // Переключение по заголовку страницы.
                switch( $result_arr[3] ) {
                case 'Вход | ВКонтакте':
                    preg_match_all('/<form method=\"post\" action=\"(.*?)\">/', $result_arr[4], $pmath1);
                    $request_url = $pmath1[1][0];
                    $post_params = 'email='.urlencode($this->username).'&pass='.urlencode($this->password);
                    $action = 'try';
                    break;
                case 'Стена':
                    //print_r($result_arr[4]);
                    $result_body = $result_arr[4];
                    $action = 'finish';
                    break;
                default: // Для клуба тут название клуба 'Электрик на дом.Электромонтажные работы в Москве.'
                    echo "UNKNOWN page header {$result_arr[3]}\r\n";
                    $result_body = $result_arr[4];
                    $document = phpQuery::newDocument($result_body);
                    echo $document->find('div.mhead > a')->attr('href')."\r\n";
                    $action = 'finish';
                }
                break;
            default:
                echo "UNKNOWN result code {$result_code}\r\n";
                print_r($r);
                die;
            }
        } while ($action != 'finish' );

        print_r($result_body);
/*<a href="/id80455298" class="inline_item">
<div class="ii_body">
<img src="https://pp.vk.me/c5146/u80455298/e_5fb842d8.jpg" class="ii_img" /><span class="ii_owner">Александр Ивонин</span>
</div>
</a>
*/
        // Разбор списка.
        $profiles = array();
        // Разбор тела документа с помощью phpQuery;
        $hentry = $document->find('a.inline_item');
        foreach ($hentry as $el) {
            $friend_id = pq($el)->attr('href');
            $profiles[$friend_id]['name'] = pq($el)->find('div.ii_body > span.class')->text();
            $profiles[$friend_id]['photo'] = pq($el)->find('div.ii_body > img.ii_img')->attr('src');
        }

        return $profiles;
    }
    // Возможные значения all, online, suggestions
    public function getFriends( $section = '' ) 
    {
        echo "Getting {$section} Friends.\r\n";
        $get_data = array();
        if( !empty( $section ) ) $get_data['section'] = $section;
        $get_query = http_build_query($get_data);
        $request_url = 'https://m.vk.com/friends?'.$get_query;
        $post_params = '';//_ajax=1'; // Без этого параметра возвращается полная страница по коду 3, а с ним по коду 0.
        $count_login_try = 0;
        $action = 'try';
        do {
            echo "{$action}: URL: {$request_url}.\r\n";
            $r = $this->execCurl( $request_url, '', $post_params ); 
            // Разбор результатов.
            $result_arr = json_decode($r);
            //print_r($result_arr);
            $result_code = $result_arr[2];
            switch( $result_code ) {
            case '0': // Успешный запрос.
                //print_r($result_arr[3][0]);
                $result_body = $result_arr[3][0];
                $action = 'finish';
                break;
            case '1': // Редирект запроса на другой URL. Требуется вход в систему или повторный запрос результата.
                $request_url = $result_arr[3]; // Содержится URL для редиректа.
                $url = parse_url( $request_url ); // Разбор URL для редиректа.
                if( !isset($url['scheme']) ) $url['scheme'] = 'https'; // Если протокол не указан, то указываем.
                if( !isset($url['host']) ) $url['host'] = 'm.vk.com'; // Если хост не указан, то указываем.
                echo "request_url $request_url\r\n";
                $request_url = self::http_build_url( $request_url, $url ); // Сборка обратно URL для запроса.
                echo "request_url $request_url\r\n";
                print_r( $url );
                if( isset( $url['query'] ) ) {
                    parse_str( $url['query'], $query );
                    print_r( $query );
                    if( $query['ip_h'] ) $this->_ip_h = $query['ip_h'];
                }
                $action = 'redirect';
                break;
            case '3': // Отображение целой страницы. Форма логина и результат запроса после логина.
                // Переключение по заголовку страницы.
                switch( $result_arr[3] ) {
                case 'Вход | ВКонтакте':
                    if( $count_login_try++ > 1 ) { echo "Incorrect login or password\r\n"; die; }
                    $post_data = array( 'email' => $this->username, 'pass' => $this->password );
                    $result_body = $result_arr[4];
                    $document = phpQuery::newDocument($result_body);
                    $form = $document->find('div.mcont > div.pcont > div.form_item > form');
                    //echo $document->find('div.mcont > div.pcont > div.form_item > form')->html();
                    $request_url = pq($form)->attr('action');
                    $captcha_url = pq($form)->find('img#captcha')->attr('src');
                    $captcha_sid = pq($form)->find('input[name=captcha_sid]')->attr('value');
                    if( !empty( $captcha_sid ) ) {
                        echo "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA\r\n";
                        echo "action $ captcha_sid $captcha_url $captcha_sid\r\n";
                        $captcha_key = $this->getCaptcha( $captcha_url );
                        list( $var1, $var2 ) = explode( '?', $captcha_url, 2 );
                        parse_str( $var2, $output ); 
                        $captcha_sid = $output['sid'];
                        $post_data = array_merge( array( 'captcha_sid' => $captcha_sid, 'captcha_key' => $captcha_key ), $post_data ); // Распознанная капча.
                    }
                    $post_query = http_build_query( $post_data );
print_r($request_url)."\r\n";
                    $post_params = $post_query;
print_r($post_params)."\r\n";
                    $action = 'try';
                    break;
                case 'Стена':
                    //print_r($result_arr[4]);
                    $result_body = $result_arr[4];
                    $action = 'finish';
                    break;
                default: // Для клуба тут название клуба 'Электрик на дом.Электромонтажные работы в Москве.'
                    echo "UNKNOWN page header {$result_arr[3]}\r\n";
                    $result_body = $result_arr[4];
                    $document = phpQuery::newDocument($result_body);
                    echo $document->find('div.mhead > a')->attr('href')."\r\n";
                    $action = 'finish';
                }
                break;
            default:
                echo "UNKNOWN result code {$result_code}\r\n";
                print_r($r);
                die;
            }
        } while ($action != 'finish' );

        //print_r($result_body);
        // Разбор текста стены.
        $me = array();
        // Разбор тела документа с помощью phpQuery;
        $document = phpQuery::newDocument($result_body);
        $me['id'] = $document->find('div.ip_user_link')->find('div.op_fcont')->find('a.op_owner')->attr('href');
        $me['name'] = $document->find('div.ip_user_link')->find('div.op_fcont')->find('a.op_owner')->attr('data-name');

        $hentry = $document->find('div.simple_fit_item');
        foreach ($hentry as $el) {
            $friend_id = pq($el)->find('a.si_owner')->attr('href');
            $me['friends'][$friend_id]['name'] = pq($el)->find('div.si_body > a.si_owner')->text();
            $me['friends'][$friend_id]['write'] = pq($el)->find('div.si_links > a')->attr('href');
            $me['friends'][$friend_id]['photo'] = pq($el)->find('img.si_img')->attr('src');
        }

        return $me;
    }
// Поиск людей не только в именах
// http://m.vk.com/search?c%5Bsection%5D=people&c%5Bsort%5D=1&c%5Bq%5D=%D0%98%D0%BD%D1%84%D0%BE%D0%B1%D0%B8%D0%B7%D0%BD%D0%B5%D1%81&c%5Bcountry%5D=0&c%5Bname%5D=0
    public function getSearch($section, $query, $offset = 0, $sort = 0) 
    {
        echo "Search $section for $query starting from $offset.\r\n";
        $data = array( 'c' => array( 
                             'section' => $section,
                                'sort' => $sort,
                                   'q' => $query,
                             'country' => 0
                              ),
                  'offset' => $offset
        );
        $search_query = http_build_query($data);
        $request_url = 'https://m.vk.com/search?'.$search_query;
        $post_params = '_ajax=1'; // Без этого параметра возвращается полная страница по коду 3, а с ним по коду 0.
        $action = 'try';
        do {
            echo "{$action}: URL: {$request_url}.\r\n";
            $r = $this->execCurl( $request_url, '', $post_params ); 
            // Разбор результатов.
            $result_arr = json_decode($r);
            //print_r($result_arr);
            $result_code = $result_arr[2];
            switch( $result_code ) {
            case '0': // Успешный запрос.
                //print_r($result_arr[3][0]);
                $result_body = $result_arr[3][0];
                $action = 'finish';
                break;
            case '1': // Редирект запроса на другой URL. Требуется вход в систему или повторный запрос результата.
                $request_url = $result_arr[3]; // Содержится URL для редиректа.
                $url = parse_url( $request_url ); // Разбор URL для редиректа.
                if( !isset($url['scheme']) ) $request_url = 'https://m.vk.com'.$request_url;
                $action = 'redirect';
                break;
            case '3': // Отображение целой страницы. Форма логина и результат запроса после логина.
                // Переключение по заголовку страницы.
                switch( $result_arr[3] ) {
                case 'Вход | ВКонтакте':
                    preg_match_all('/<form method=\"post\" action=\"(.*?)\">/', $result_arr[4], $pmath1);
                    $request_url = $pmath1[1][0];
                    $post_params = 'email='.urlencode($this->username).'&pass='.urlencode($this->password);
                    $action = 'try';
                    break;
                case 'Поиск':
                    $result_body = $result_arr[4];
                    $action = 'finish';
                    break;
                default:
                    echo "UNKNOWN page header {$result_arr[3]}\r\n";
                    die;
                }
                break;
            default:
                echo "UNKNOWN result code {$result_code}\r\n";
                print_r($r);
                die;
            }
        } while ($action != 'finish' );
        // Если произошла ошибка, то ответ:
        //[446,false,1,"https:\/\/login.vk.com\/?_origin=http:\/\/m.vk.com&role=pda&ip_h=9d24b03ff30f112204&_phash=447526beec7249afcfe9f8db2ac619d1&to=c2VhcmNoP2MlNUJzZWN0aW9uJTVEPWNvbW11bml0aWVzJmMlNUJzb3J0JTVEPTEmYyU1QnElNUQ9JUQxJTgwJUQwJUIwJUQwJUIxJUQwJUJFJUQxJTgyJUQwJUIwJmMlNUJjb3VudHJ5JTVEPTAmb2Zmc2V0PTkw",false]
        //[446,false,3,"Вход | ВКонтакте","код html addcslashes","extend(cur,{module:'login'});",false,"_touch"]
        //$this->auth('https://login.vk.com/?_origin=http://m.vk.com&role=pda&ip_h=9d24b03ff30f112204&_phash=447526beec7249afcfe9f8db2ac619d1&to=c2VhcmNoP2MlNUJzZWN0aW9uJTVEPWNvbW11bml0aWVzJmMlNUJzb3J0JTVEPTEmYyU1QnElNUQ9JUQxJTgwJUQwJUIwJUQwJUIxJUQwJUJFJUQxJTgyJUQwJUIwJmMlNUJjb3VudHJ5JTVEPTAmb2Zmc2V0PTkw');
        echo "ALL DONE\r\n";
/*        <a href="/promokasting" class="simple_fit_item search_item">
<img src="https://pp.vk.me/c406530/g37592325/e_62db9889.jpg" class="si_img" />
<div class="si_body">
<span class="si_owner">Работа в Москве</span>
<div class="si_slabel">Группа</div>
<div class="si_slabel">15<span class="num_delim"> </span>788 участников</div>
</div>
</a><div class="show_more_wrap"><a class="show_more" href="/search?c%5Bsection%5D=communities&c%5Bsort%5D=0&c%5Bq%5D=%D1%80%D0%B0%D0%B1%D0%BE%D1%82%D0%B0&c%5Bcountry%5D=0&offset=120">Показать еще результаты</a></div>
*/

        $search = array();
        // Разбор тела документа с помощью phpQuery;
        $document = phpQuery::newDocument($result_body);
        $hentry = $document->find('a.search_item');
        //print_r(pq('a.search_item')->attr('href')); 
        foreach ($hentry as $el) {
            $pq = pq($el);
            $search['results'][$pq->attr('href')] = array( 
                  'name' => $pq->find('div.si_body')->find('span.si_owner')->text(),
                  'type' => $pq->find('div.si_body')->find('div.si_slabel:first')->text(),
                  'num' => $pq->find('div.si_body')->find('div.si_slabel:last')->text()
            );
        }
        parse_str(  $document->find('a.show_more')->attr('href'), $output );
        if( count( $output ) > 0 ) $search['next_offset'] = $output['offset'];
/*
        preg_match_all('/href=\"(.*?)\"/', $result_body, $preg_match);
        //sort($preg_match[1]);
    print_r($preg_match);
        preg_match_all('/action=\"(.*?)\"/', $result_body, $preg_match);
    print_r($preg_match);
        //wall?act=post&new_comment=-55217589_2&hash=
        parse_str($action, $output);
        $wall['hash']=$output['hash'];
*/        
        //print_r($search);
        return $search;
    }

    public function getClub($club_id) 
    {
        echo "Getting Club {$club_id}.\r\n";
        $request_url = 'https://m.vk.com'.$club_id;
        $post_params = '';//_ajax=1'; // Без этого параметра возвращается полная страница по коду 3, а с ним по коду 0.
        $action = 'try';
        do {
            echo "{$action}: URL: {$request_url}.\r\n";
            $r = $this->execCurl( $request_url, '', $post_params ); 
            // Разбор результатов.
            $result_arr = json_decode($r);
            //print_r($result_arr);
            $result_code = $result_arr[2];
            switch( $result_code ) {
            case '0': // Успешный запрос.
                //print_r($result_arr[3][0]);
                $result_body = $result_arr[3][0];
                $action = 'finish';
                break;
            case '1': // Редирект запроса на другой URL. Требуется вход в систему или повторный запрос результата.
                $request_url = $result_arr[3]; // Содержится URL для редиректа.
                $url = parse_url( $request_url ); // Разбор URL для редиректа.
                if( !isset($url['scheme']) ) $request_url = 'https://m.vk.com'.$request_url;
                $action = 'redirect';
                break;
            case '3': // Отображение целой страницы. Форма логина и результат запроса после логина.
                // Переключение по заголовку страницы.
                switch( $result_arr[3] ) {
                case 'Вход | ВКонтакте':
                    preg_match_all('/<form method=\"post\" action=\"(.*?)\">/', $result_arr[4], $pmath1);
                    $request_url = $pmath1[1][0];
                    $post_params = 'email='.urlencode($this->username).'&pass='.urlencode($this->password);
                    $action = 'try';
                    break;
                case 'Стена':
                    //print_r($result_arr[4]);
                    $result_body = $result_arr[4];
                    $action = 'finish';
                    break;
                default: // Для клуба тут название клуба 'Электрик на дом.Электромонтажные работы в Москве.'
                    echo "UNKNOWN page header {$result_arr[3]}\r\n";
                    $result_body = $result_arr[4];
                    $action = 'finish';
                }
                break;
            default:
                echo "UNKNOWN result code {$result_code}\r\n";
                print_r($r);
                die;
            }
        } while ($action != 'finish' );

        // Разбор текста стены.
/*<div class="create_post create_post_extra">
<form action="/wall-33177271?act=post&from=profile&hash=beb39efe3b557e1a00" method="post">
<div class="iwrap"><textarea name="message" class="textfield" rows="3" placeholder="Написать сообщение.."></textarea></div>
<div class="ibwrap">
<div class="cp_attached_wrap" id="attached_wrap"></div>
<div class="cp_buttons_block">
<input class="button" type="submit" value="Отправить" /><span class="cp_icon_btn cp_attach_btn" id="attach_photo_btn">
<input type="image" class="i_icon" src="/images/blank.gif" width="26" height="26" name="add_attach" />
</span>
</div>
</div>
</form>
</div>*/
        //print_r($result_body);
        $club = array();
        // Разбор тела документа с помощью phpQuery;
        $document = phpQuery::newDocument($result_body);
        $club['enter'] = $document->find('div.op_block')->find('a')->attr('href');
        $club['post_href'] = $document->find('div.create_post')->find('form')->attr('action');
        $hentry = $document->find('div.wall_posts')->find('div.post_item');
        
        foreach ($hentry as $el) {
            $post_id = pq($el)->find('a.anchor')->attr('name');
            $pq = pq($el)->find('a.pi_link');
            $post = array();
            foreach( $pq as $aa ) {
                $href = pq($aa)->attr('href');
                if( strpos( $href, 'like') !== false ) $post['like'] = pq($aa)->attr('href');
                if( strpos( $href, 'wall') !== false ) $post['wall'] = pq($aa)->attr('href');
            }
            $club['posts'][$post_id] = $post;
        }
        //parse_str(  $document->find('a.show_more')->attr('href'), $output );
        //if( count( $output ) > 0 ) $search['next_offset'] = $output['offset'];

        //print_r($club);
        return $club;
    }

    public function getWall($wall_id) 
    {
        echo "Getting Wall {$wall_id}.\r\n";
        $request_url = 'http://m.vk.com/wall'.$wall_id;
        $post_params = '_ajax=1'; // Без этого параметра возвращается полная страница по коду 3, а с ним по коду 0.
        $action = 'try';
        do {
            echo "{$action}: URL: {$request_url}.\r\n";
            $r = $this->execCurl( $request_url, '', $post_params ); 
            // Разбор результатов.
            $result_arr = json_decode($r);
            //print_r($result_arr);
            $result_code = $result_arr[2];
            switch( $result_code ) {
            case '0': // Успешный запрос.
                //print_r($result_arr[3][0]);
                $result_body = $result_arr[3][0];
                $action = 'finish';
                break;
            case '1': // Редирект запроса на другой URL. Требуется вход в систему или повторный запрос результата.
                $request_url = $result_arr[3]; // Содержится URL для редиректа.
                $url = parse_url( $request_url ); // Разбор URL для редиректа.
                if( !isset($url['scheme']) ) $request_url = 'https://m.vk.com'.$request_url;
                $action = 'redirect';
                break;
            case '3': // Отображение целой страницы. Форма логина и результат запроса после логина.
                // Переключение по заголовку страницы.
                switch( $result_arr[3] ) {
                case 'Вход | ВКонтакте':
                    preg_match_all('/<form method=\"post\" action=\"(.*?)\">/', $result_arr[4], $pmath1);
                    $request_url = $pmath1[1][0];
                    $post_params = 'email='.urlencode($this->username).'&pass='.urlencode($this->password);
                    $action = 'try';
                    break;
                case 'Стена':
                    //print_r($result_arr[4]);
                    $result_body = $result_arr[4];
                    $action = 'finish';
                    break;
                default:
                    echo "UNKNOWN page header {$result_arr[3]}\r\n";
                    die;
                }
                break;
            default:
                echo "UNKNOWN result code {$result_code}\r\n";
                print_r($r);
                die;
            }
        } while ($action != 'finish' );

        print_r($r);
        // Разбор текста стены.
        preg_match_all('/href=\\\"(.*?)\\\"/', $r, $preg_match);
        sort($preg_match[1]);
    //print_r($preg_match);
        // Поиск кода для поста на стену.
        preg_match_all('/action=\"(.*?)\"/', $result_body, $preg_match);
        //print_r($preg_match);
        $wall = array();
        //wall?act=post&new_comment=-55217589_2&hash=
        parse_str($preg_match[1][0], $output); // Разбор параметра hash из action.
        //print_r($output);
        $wall['hash']=$output['hash'];

        echo "ALL DONE\r\n";
        return $wall;
    }
    private function getWallOld($wall_id) 
    {
        echo "Getting Wall information.\r\n";
        $q = '_ref=wall'.$wall_id.
             '';
        echo $q."\r\n";
        $mobile = true;
        if( $mobile ) {
            $request_url = 'http://m.vk.com/wall'.$wall_id.
                           '';
            echo $request_url."\r\n";
            $headers = array(
                 'Origin' => 'http://m.vk.com',
                 'X-Requested-With' => 'XMLHttpRequest'
            );
            $r = $this->execCurl( $request_url, 'http://m.vk.com/wall'.$wall_id, $q, $headers ); 
        } else {
            $q = '_ref=wall'.$wall_id.
                 '';
            echo $q."\r\n";
            $request_url = 'http://vk.com/wall'.$wall_id.
                           '';
            echo $request_url."\r\n";
            $headers = array(
             //    'Origin' => 'http://m.vk.com',
                 'X-Requested-With' => 'XMLHttpRequest'
            );
            $r = $this->execCurl( $request_url, 'http://vk.com/wall'.$wall_id, '', $headers ); 
        }
    //print_r($r);
        
        preg_match_all('/action=\\\"(.*?)\\\"/', $r, $preg_match);
        $wall = array();
        $action = strtr($preg_match[1][0],array('\\'=>''));
        //wall?act=post&new_comment=-55217589_2&hash=
        parse_str($action, $output);
        $wall['hash']=$output['hash'];
    print_r($output);
        
        return $wall;
    }

    public function deletePost( $post_delete_url ) 
    {
        echo "Delete post {$post_delete_url}.\r\n";
        $request_url = 'https://m.vk.com'.$post_delete_url;
        // Без этого параметра возвращается полная страница по коду 3, а с ним по коду 0.
        $post_data = array( '_ajax' => '1' ); // Для сохранения оригинальной последовательности добавлено последним.
        $post_query = http_build_query( $post_data );
        $action = 'try';
        do {
            echo "{$action}: URL: {$request_url}.\r\n";
            $r = $this->execCurl( $request_url, '', $post_query ); 
            // Разбор результатов.
            $result_arr = json_decode($r);
            //print_r($result_arr);
            $result_code = $result_arr[2];
            switch( $result_code ) {
            case '0': // Успешный запрос.
                //print_r($result_arr[3][0]);
                $result_body = $result_arr[3][0];
                $action = 'finish';
                break;
            case '1': // Редирект запроса на другой URL. Требуется вход в систему или повторный запрос результата.
                $request_url = $result_arr[3]; // Содержится URL для редиректа.
                $url = parse_url( $request_url ); // Разбор URL для редиректа.
                if( !isset($url['scheme']) ) $request_url = 'https://m.vk.com'.$request_url;
                $action = 'redirect';
                break;
            case '2':
                if( isset( $captcha_url ) ) echo "CAPTCHA FAILED.\r\n";
                print_r( $result_arr );
                $captcha_url = $result_arr[4];
                $captcha_key = $this->getCaptcha( $captcha_url );
                list( $var1, $var2 ) = explode( '?', $captcha_url, 2 );
                parse_str( $var2, $output ); 
                $captcha_sid = $output['sid'];
                $post_data = array_merge( array( 'captcha_sid' => $captcha_sid, 'captcha_key' => $captcha_key ), $post_data ); // Распознанная капча.
                $post_query = http_build_query( $post_data );
                $action = 'try';
                break;
            case '3': // Отображение целой страницы. Форма логина и результат запроса после логина.
                // Переключение по заголовку страницы.
                switch( $result_arr[3] ) {
                case 'Вход | ВКонтакте':
                    preg_match_all('/<form method=\"post\" action=\"(.*?)\">/', $result_arr[4], $pmath1);
                    $request_url = $pmath1[1][0];
                    $post_query = 'email='.urlencode($this->username).'&pass='.urlencode($this->password);
                    $action = 'try';
                    break;
                case 'Стена':
                    //print_r($result_arr[4]);
                    $result_body = $result_arr[4];
                    $action = 'finish';
                    break;
                case 'Ошибка':
                    $result_body = $result_arr[4];
                    $action = 'finish';
                    break;
                default:
                    //print_r($result_arr);
                    echo "UNKNOWN page header {$result_arr[3]}\r\n";
                    $document = phpQuery::newDocument($result_arr[4]);
                    echo $document->find('div.mhead > a')->attr('href')."\r\n";
//<div id="mhead" class="mhead"><div class="hb_wrap mhb_user"><div class="mhu_iwrap"><img src="/images/deactivated_c.gif" class="mhu_img" /></div></div>
//<a href="/login?act=blocked_logout&hash=a8d16bddc70aef1ad3" class="hb_wrap mhb_notify mh_nobl" accesskey="#">
                    die;
                }
                break;
            default:
                echo "UNKNOWN result code {$result_code}\r\n";
                print_r($r);
                die;
            }
        } while ($action != 'finish' );

        // Разбор текста стены.
        //print_r($result_body);
        $wall = array( );
        // Разбор тела документа с помощью phpQuery;
        $document = phpQuery::newDocument($result_body);
        //$hentry = $document->find('div.pi_cont:first >find('div.pi_body')->find('div.pi_actions_wrap')->find('ul.pi_actions')->find('li.pia_item_wrap');

        echo "ALL DONE\r\n";
        return $wall;
    }
    public function postWall($wall_id, $message, $hash, $attach = array() ) 
    {
        echo "Post message to Wall {$wall_id} whith hash {$hash}.\r\n";
        $get_data = array(
                    'act' => 'post',
                   'from' => 'profile',
                   'hash' => $hash,
        );
        $get_query = http_build_query($get_data);
        $request_url = 'https://m.vk.com/wall'.$wall_id.'?'.$get_query;
        $post_data = array(
                  '_ref=' => $wall_id,
               'message=' => urlencode($message)
        );
        $post_data = array_merge( $post_data, $attach );
        // Без этого параметра возвращается полная страница по коду 3, а с ним по коду 0.
        $post_data = array_merge( $post_data, array( '_ajax' => '1' ) ); // Для сохранения оригинальной последовательности добавлено последним.
        $post_query = http_build_query( $post_data );
        $action = 'try';
        do {
            echo "{$action}: URL: {$request_url}.\r\n";
            $r = $this->execCurl( $request_url, '', $post_query ); 
            // Разбор результатов.
            $result_arr = json_decode($r);
            //print_r($result_arr);
            $result_code = $result_arr[2];
            switch( $result_code ) {
            case '0': // Успешный запрос.
                //print_r($result_arr[3][0]);
                $result_body = $result_arr[3][0];
                $action = 'finish';
                break;
            case '1': // Редирект запроса на другой URL. Требуется вход в систему или повторный запрос результата.
                $request_url = $result_arr[3]; // Содержится URL для редиректа.
                $url = parse_url( $request_url ); // Разбор URL для редиректа.
                if( !isset($url['scheme']) ) $request_url = 'https://m.vk.com'.$request_url;
                $action = 'redirect';
                break;
            case '2':
                if( isset( $captcha_url ) ) echo "CAPTCHA FAILED.\r\n";
                print_r($result_arr);
                $captcha_url = $result_arr[4];
                $captcha_key = $this->getCaptcha( $captcha_url );
                list( $var1, $var2 ) = explode( '?', $captcha_url, 2 );
                parse_str( $var2, $output ); 
                $captcha_sid = $output['sid'];
/*
[446,[0,0,0,0,0,0,0],2,"\/wall-57005460?act=post&from=profile&hash=6fd880b59583af78e4","\/captcha.php?sid=753221467199&s=1",{"captcha_sid":"753221467199","_ref=":"-57005460","message=":"%D0%91%D0%BE%D0%BB%D1%8C%D1%88%D0%B5+%D0%92%D1%8B+%D0%BD%D0%B5+%D0%BD%D0%B0%D0%B9%D0%B4%D1%91%D1%82%D0%B5+%D0%BD%D0%B0%D1%88%D0%B8+%D1%82%D1%80%D0%B5%D0%BD%D0%B8%D0%BD%D0%B3%D0%B8%21","attach1":"-57536765_44870079","attach1_type":"page","attach2":"221574853_308977575","attach2_type":"photo","_ajax":"1"}]
[446,[0,0,0,0,0,0,0],2,"\/wall-7325453?act=post&from=profile&hash=5900695e287a73bb4a","\/captcha.php?sid=919147576345&s=1",{"captcha_sid":"919147576345","_ref=":"-7325453","message=":"%D0%91%D0%BE%D0%BB%D1%8C%D1%88%D0%B5+%D0%92%D1%8B+%D0%BD%D0%B5+%D0%BD%D0%B0%D0%B9%D0%B4%D1%91%D1%82%D0%B5+%D0%BD%D0%B0%D1%88%D0%B8+%D1%82%D1%80%D0%B5%D0%BD%D0%B8%D0%BD%D0%B3%D0%B8%21","attach1":"-57536765_44870079","attach1_type":"page","attach2":"221574853_308977575","attach2_type":"photo","_ajax":"1"}]
*/
                $post_data = array_merge( array( 'captcha_sid' => $captcha_sid, 'captcha_key' => $captcha_key ), $post_data ); // Распознанная капча.
                $post_query = http_build_query( $post_data );
                $action = 'try';
                break;
            case '3': // Отображение целой страницы. Форма логина и результат запроса после логина.
                // Переключение по заголовку страницы.
                switch( $result_arr[3] ) {
                case 'Вход | ВКонтакте':
                    preg_match_all('/<form method=\"post\" action=\"(.*?)\">/', $result_arr[4], $pmath1);
                    $request_url = $pmath1[1][0];
                    $post_query = 'email='.urlencode($this->username).'&pass='.urlencode($this->password);
                    $action = 'try';
                    break;
                case 'Стена':
                    //print_r($result_arr[4]);
                    $result_body = $result_arr[4];
                    $action = 'finish';
                    break;
                case 'Ошибка':
                    $result_body = $result_arr[4];
                    $action = 'finish';
                    break;
                default:
                    echo "UNKNOWN page header {$result_arr[3]}\r\n";
                    die;
                }
                break;
            default:
                echo "UNKNOWN result code {$result_code}\r\n";
                print_r($r);
                die;
            }
        } while ($action != 'finish' );

        // Разбор текста стены.
        //print_r($result_body);
        $wall = array( );
        // Разбор тела документа с помощью phpQuery;
        $document = phpQuery::newDocument($result_body);
        //$hentry = $document->find('div.pi_cont:first >find('div.pi_body')->find('div.pi_actions_wrap')->find('ul.pi_actions')->find('li.pia_item_wrap');
        $count = 0;
        do {
            $hentry = $document->find('div.post_item:eq('.$count.') > div.pi_cont > div.pi_body > div.pi_actions_wrap > ul.pi_actions > li.pia_item_wrap');
            foreach ($hentry as $el) {
                $item = pq($el)->find('a.pia_item');
                switch( $item->text() ) {
                case 'Редактировать':
                    $wall['edit'] = $item->attr('href');
                    break;
                case 'Поделиться':
                    $wall['publish'] = $item->attr('href');
                    break;
                case 'Удалить':
                    $wall['delete'] = $item->attr('href');
                    break;
                default:
                    echo "UNKNOWN text after post: $text.\r\n";
                }
            }
            $count++;
        } while ( !isset( $wall['delete'] ) );

        echo "ALL DONE\r\n";
        return $wall;
    }

    public function postComment($wall_id, $message, $hash, $attach = array() ) 
    {
        echo "Post message to Wall {$wall_id} whith hash {$hash}.\r\n";
        $get_data = array(
                    'act' => 'post',
            'new_comment' => $wall_id,
                   'hash' => $hash,
        );
        $get_query = http_build_query($get_data);
        $request_url = 'https://m.vk.com/wall?'.$get_query;
        $post_data = array(
                  '_ref=' => $wall_id,
               'message=' => urlencode($message),
              'reply_to=' => ''
        );
        $post_data = array_merge( $post_data, $attach );
        // Без этого параметра возвращается полная страница по коду 3, а с ним по коду 0.
        $post_data = array_merge( $post_data, array( '_ajax' => '1' ) ); // Для сохранения оригинальной последовательности добавлено последним.
        $post_query = http_build_query( $post_data );
        $action = 'try';
        do {
            echo "{$action}: URL: {$request_url}.\r\n";
            $r = $this->execCurl( $request_url, '', $post_query ); 
            // Разбор результатов.
            $result_arr = json_decode($r);
            //print_r($result_arr);
            $result_code = $result_arr[2];
            switch( $result_code ) {
            case '0': // Успешный запрос.
                //print_r($result_arr[3][0]);
                $result_body = $result_arr[3][0];
                $action = 'finish';
                break;
            case '1': // Редирект запроса на другой URL. Требуется вход в систему или повторный запрос результата.
                $request_url = $result_arr[3]; // Содержится URL для редиректа.
                $url = parse_url( $request_url ); // Разбор URL для редиректа.
                if( !isset($url['scheme']) ) $request_url = 'https://m.vk.com'.$request_url;
                $action = 'redirect';
                break;
            case '3': // Отображение целой страницы. Форма логина и результат запроса после логина.
                // Переключение по заголовку страницы.
                switch( $result_arr[3] ) {
                case 'Вход | ВКонтакте':
                    preg_match_all('/<form method=\"post\" action=\"(.*?)\">/', $result_arr[4], $pmath1);
                    $request_url = $pmath1[1][0];
                    $post_query = 'email='.urlencode($this->username).'&pass='.urlencode($this->password);
                    $action = 'try';
                    break;
                case 'Стена':
                    //print_r($result_arr[4]);
                    $result_body = $result_arr[4];
                    $action = 'finish';
                    break;
                case 'Ошибка':
                    $result_body = $result_arr[4];
                    $action = 'finish';
                    break;
                default:
                    echo "UNKNOWN page header {$result_arr[3]}\r\n";
                    die;
                }
                break;
            default:
                echo "UNKNOWN result code {$result_code}\r\n";
                print_r($r);
                die;
            }
        } while ($action != 'finish' );

        // Разбор текста стены.
        print_r($result_body);

        echo "ALL DONE\r\n";
        return $wall;
    }
    public function postWallOld($wall_id, $message, $hash) 
    {
        if( !isset($this->wall[$wall_id]['hash'] ) ) $this->wall[$wall_id] = $this->getWall($wall_id);
        print_r($this->wall);

        if( !empty($this->wall[$wall_id]['hash'] ) ) {
            $wall = $this->wall[$wall_id];
            $mobile = true;
            if( $mobile ) {
                $q = '_ref='.$wall_id.
                     '&message='.urlencode($message).
                     '&reply_to='.
                    '&attach1=-49204402_44357413'.
                    '&attach1_type=page'.
                    '&attach2=-55217589_305318264'.
                    '&attach2_type=photo'.
                     '';
                echo $q."\r\n";
                $headers = array(
                     'Origin' => 'http://m.vk.com',
                     'X-Requested-With' => 'XMLHttpRequest'
                );
                $request = 'http://m.vk.com/wall?'.
                           'act=post'.
                           '&new_comment='.$wall_id.
                           '&hash='.$wall['hash'];
                $r = $this->execCurl( $request, 'http://m.vk.com/'.$wall_id, $q, $headers ); 
                // [446,[0,0,0,0,1,45,0,0],1,"\/wall-55217589_2#reply21",false] //Успех
                // [446,[0,0,0,0,1,45,0,0],1,"\/wall-55217589_2?post_add&m=34#post_add",false] //Пустое сообщение было отослано.
            } else {
                $q = 'act=post'.
                    '%al=1'.
                    '&attach1=-49204402_44357413'.
                    '&attach1_type=page'.
                    '&attach2=-55217589_305318264'.
                    '&attach2_type=photo'.
                    '&from_group='.
                    '&hash=ca9d6f1fd23e19ad69'.
                    '&last=21'.
                    '&message='.urlencode($message).
                    '&reply_to:-55217589_2'.
                    '&reply_to_msg='.
                    '&reply_to_user=0'.
                    '&type=full'.
                     '';
                echo $q."\r\n";
                $request_url = 'http://vk.com/al_wall.php';
                echo $request_url."\r\n";
                $headers = array(
                     'Origin' => 'http://vk.com',
                     'X-Requested-With' => 'XMLHttpRequest'
                );
                $r = $this->execCurl( $request_url, 'http://vk.com/'.$wall_id, '', $headers ); 
            }
            print_r($r);
            
        }
        return $r;
    }
  /** 
  *   Пишем на стену  
  *   Параметры:
  *             $hash - значение параметра post_hash с исходной страницы
  *             $url - публикуемая ссылка
  *             $message - сообщение, выводимое на стенке
  *             $title - название ссылки, выводимое в всплывающей подсказке
  *             $descr - описание ссылки, выводимое в всплывающей подсказке
  *             $photo - значение уникального photo_id, которое получается с помощью функции photo
  *             $type - тип сообщения, share - ссылка, photo - фото, если пустое значение, то простое сообщение
  */      
  private function makePostMobile($hash, $url, $message, $title, $descr, $photo, $type="share") 
  {
    $u = urlencode($url);
    $m = urlencode($message);
    $t = urlencode($title);
    $d = urlencode($descr);
    $q = '_ref=wall-55217589_2'.
        '&message='.urlencode('проверка поста. test message').
        '&reply_to=';
    echo $q."\r\n";
    $request_url = 'http://m.vk.com/wall'.
                  '?act=post'.
                  '&new_comment=-55217589_2'.
                  '&hash='.$this->_hash; 
    echo $request_url."\r\n";
    $headers = array(
         'Origin: http://m.vk.com',
         'X-Requested-With: XMLHttpRequest'
    );
    $c=$this->getCurl();
    $r = $this->execCurl($c, $request_url, 'http://m.vk.com/wall-55217589_2', $q, true, 'makePost', $headers ); 
//print_r($r);
    
    return $r;
  }
  /** 
  *   Параметры:
  *             $hash - значение параметра post_hash с исходной страницы
  *             $url - публикуемая ссылка
  *             $message - сообщение, выводимое на стенке
  *             $title - название ссылки, выводимое в всплывающей подсказке
  *             $descr - описание ссылки, выводимое в всплывающей подсказке
  *             $photo - значение уникального photo_id, которое получается с помощью функции photo
  *             $type - тип сообщения, share - ссылка, photo - фото, если пустое значение, то простое сообщение
  */      
  private function makePostMob($hash, $url, $message, $title, $descr, $photo, $type="share") 
  {
    $u = urlencode($url);
    $m = urlencode($message);
    $t = urlencode($title);
    $d = urlencode($descr);
    if( $type == 'share') 
    {
      $q = 'act=post&al=1&hash='.$hash.'&message='.$m.'&note_title=&official=&status_export=&to_id='.
      $this->wallId.'&type=all&attach1_type=share&url='.$u.'&title='.$t.'&description='.$d.
      '&extra=0&extra_data=&type=own&facebook_export=&friends_only=&signed=';
      if($photo)
        $q .= '&attach1='.$photo;
    } 
    elseif( $type == 'photo') 
    {
      $q = 'act=post&al=1&hash='.$hash.'&message='.$m.'&note_title=&official=&status_export=&to_id='.
      $this->wallId.'&type=all&attach1_type=photo&attach1='.$photo;
    } 
    elseif( $type == '') 
    {
      $q = 'act=post&al=1&hash='.$hash.'&message='.$m.'&note_title=&official=&status_export=&'.
      "to_id=".$this->wallId.'&type=all';
    }  
    $c = $this->getCurl();
    curl_setopt($c, CURLOPT_HTTPHEADER, array('X-Requested-With: XMLHttpRequest')); 
    curl_setopt($c, CURLOPT_POST, 1);  
    curl_setopt($c, CURLOPT_REFERER, $this->wallURL);
    curl_setopt($c, CURLOPT_POSTFIELDS, $q);
    curl_setopt($c, CURLOPT_TIMEOUT, 15); 
    curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($c, CURLOPT_URL, 'http://vk.com/al_wall.php');   
    $r = $this->execCurl($c, 'makePost');
    
    return $r;
  }
  
  /** 
  *   Пишем на стену  (логинится, находит хеш и постит на стену)
  */
  public function postMessage($url='', $message='Test', $title='', $descr='', $type='share') 
  {
    $h=$this->auth();
    if (!$h) 
      return false;
    $r = $this->makePost($h['post_hash'], $url, $message, $title, $descr, false, $type);
    $c = preg_match_all('/reply_link_wrap/smi',$r,$f);
    if( $c == 0 ) 
    {
      return false;
    } 
    else 
    {
      return true;
    }
  }

  /** 
  *   Грузим картинку на стену
  */
  public function postPicture($imgUrl, $linkTo='', $message) 
  {
    $h=$this->getHash();
    if (!$h) 
      return false;
    $img=$this->uploadPhoto($imgUrl, $linkTo);
    if (!$img) 
    {
      $this->RaiseExeption("Picture is not uploaded");
      return false;
    }
    if (!$this->wallId)
      $this->wallId = $h['user_id'];
    $r = $this->makePost($h['post_hash'], $linkTo, $message, "", "", $img["mixed_id"], "photo");
    $c = preg_match_all('/reply_link_wrap/smi',$r,$f);
    if( $c == 0 ) 
    {
      return false;
    } 
    else 
    { 
      return true;
    }
  }
    
}
?>
