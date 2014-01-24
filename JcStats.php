<?php
//session_start();

if ( !class_exists( 'JustClick' ) ) require('justclick.class.php' );
require_once 'persistent.class.php';

class JcStats extends Persistent
{
	private $_debug = false;
	// Переменные public сохраняются Persistent, private не сохраняются.
	public $_jc;
	public $_classname;
	public $_part_list; // Каталог подключенных партнёрок JustClick и их параметров.
	
	public function __construct()
	{
		$this->_classname = 'direct';
		if( $this->_debug) echo 'construct JcStats: '.$this->_classname."\r\n";
		$this->Persistent( $this->_classname.'.persistent' ); // Установка имени фала для загрузки класса.
		$this->open(); // Загрузка состояния класса.
		if( !is_object( $this->_jc ) ) {
			$config = parse_ini_file( '.config.ini' );
			$this->setAuth( $config['username'], $config['password'] );
		}
		if( $this->_debug) print_r( $this->_classname );
		if( $this->_debug) print_r( $this->_jc );
		$this->_jc->debug = $this->_debug;
	}

	public function __destruct() {
		if( $this->_debug) echo 'destruct JcStats: '.$this->_classname."\r\n";
		$this->save(); // Сохранение состояния класса.
	}

	function setDebug( $debug )
	{
		$this->_debug = $debug;
		if( $this->_debug) print_r( $this->_classname );
		if( $this->_debug) print_r( $this->_jc );
		$this->_jc->debug = $this->_debug;
	}

	function setAuth( $username, $password )
	{
		$this->_jc = new JustClick( $this->_classname, $username, $password );
	}

	/**
	* Handler for client side form sumbit
	* @param Array $formPacket Collection of form items along with direct data
	* @return Array response packet
	*/
	function getList( )
	{
		$response = array();
		$response['success'] = false;

		if( empty( $this->_part_list ) ) {
			//if( $this->_debug) var_dump( $this );
			$this->_part_list = $this->_jc->getList();
			if( $this->_debug) var_dump( $this->_part_list );
		}

		// Обновление статистики.
		//exec('./justclick.sh');

		foreach( $this->_part_list as $part_id => $part_param ) {
			$result = array( 'id' => $part_id, 'name' => $part_param['name'] );
			// Проверка наличия загруженной статистики партнёрской программы.
			if( $this->_debug) print_r($part_param['stats']);
			if( empty( $part_param['stats'] ) ) {
			/*	$part_stats = $this->_jc->getStats( $part_id );
				$this->_part_list[$part_id]['stats'] = $part_stats;
				if( $this->_debug) print_r( $part_stats );
			*/
				$part_stats = array();
			} else {
				$part_stats = $part_param['stats'];
			}
			// Проверка наличия загруженной статистики партнёрской программы.
			if( $this->_debug) print_r($part_param['bills']);
			if( empty( $part_param['bills'] ) ) {
			/*	$bills = $this->_jc->getBills( $part_id );
				$part_bills = array( 'payed' => 0, 'payed_sum' => 0, 'payed_comm' => 0,
				                   'unpayed' => 0, 'unpayed_sum' => 0, 'unpayed_comm' => 0,
				                  'canceled' => 0, 'canceled_sum' => 0, 'canceled_comm' => 0
				);
				foreach( $bills as $bill_id => $bill ) {
					print_r( $bill );
					switch( $bill['status'] ) {
					case 'payed':
						$part_bills['payed'] += 1;
						$part_bills['payed_sum'] += $bill['summ'];
						$part_bills['payed_comm'] += $bill['comm'];
						break;
					case 'unpayed':
						$part_bills['unpayed'] += 1;
						$part_bills['unpayed_sum'] += $bill['summ'];
						$part_bills['unpayed_comm'] += $bill['comm'];
						break;
					case 'canceled':
						$part_bills['canceled'] += 1;
						$part_bills['canceled_sum'] += $bill['summ'];
						$part_bills['canceled_comm'] += $bill['comm'];
						break;
					default:
						echo "ERROR\r\n";
					}
				}
				$this->_part_list[$part_id]['bills'] = $part_bills;
				print_r( $part_bills );
			*/
				$part_bills = array();
			} else {
				$part_bills = $part_param['bills'];
				//print_r( $part_bills );
			}
			// Формирование результата в нужной для Ext.Direct форме.
			$result = array_merge( $result, $part_stats, $part_bills );
			$response['data'][] = $result;
		}
		$response['success'] = true;
		$response['total'] = count( $response['data'] );

		// Подготовка ответа.
		if( $response['success'] ) {
		} else {
			$success = false;
			$response['errors'] = array(
				'username'=>'already taken'
			);
		}
		if( $this->_debug) print_r( $response );
		return $response;
	}

	function refreshStats( ){
		foreach( $this->_part_list as $part_id => $part_param ) {
			$result = array( 'id' => $part_id, 'name' => $part_param['name'] );
			// Проверка наличия загруженной статистики партнёрской программы.
			if( empty( $part_param['stats'] ) ) {
				$part_stats = $this->_jc->getStats( $part_id );
				$this->_part_list[$part_id]['stats'] = $part_stats;
				if( $this->_debug)  print_r( $part_stats );
				if( $this->_debug)  print_r( $this->_part_list[$part_id]['stats'] );
			} else {
				$part_stats = $part_param['stats'];
			}
		}
	}

	function refreshBills( ){
		foreach( $this->_part_list as $part_id => $part_param ) {
			$result = array( 'id' => $part_id, 'name' => $part_param['name'] );
			// Проверка наличия загруженной статистики партнёрской программы.
			//if( empty( $part_param['bills'] ) ) {
			if( true ) {
				$bills = $this->_jc->getBills( $part_id );
				$part_bills = array( 'payed' => 0, 'payed_sum' => 0, 'payed_comm' => 0,
				                   'unpayed' => 0, 'unpayed_sum' => 0, 'unpayed_comm' => 0,
				                  'canceled' => 0, 'canceled_sum' => 0, 'canceled_comm' => 0
				);
				foreach( $bills as $bill_id => $bill ) {
					//print_r( $bill );
					switch( $bill['status'] ) {
					case 'payed':
						$part_bills['payed'] += 1;
						$part_bills['payed_sum'] += $bill['summ'];
						$part_bills['payed_comm'] += $bill['comm'];
						break;
					case 'unpayed':
						$part_bills['unpayed'] += 1;
						$part_bills['unpayed_sum'] += $bill['summ'];
						$part_bills['unpayed_comm'] += $bill['comm'];
						break;
					case 'canceled':
						$part_bills['canceled'] += 1;
						$part_bills['canceled_sum'] += $bill['summ'];
						$part_bills['canceled_comm'] += $bill['comm'];
						break;
					default:
						echo "ERROR\r\n";
					}
				}
				$this->_part_list[$part_id]['bills'] = $part_bills;
				//print_r( $part_bills );
			} else {
				$part_bills = $part_param['bills'];
			}
		}
	}

    /**
    * put your comment there...
    * This method configured with len=2, so 2 arguments will be sent
    * in the order according to the client side specified paramOrder
    * @param Number $userId
    * @param String $foo
    * @return Array response packet
    */
    function getStats( $partId ){
        $response = array(
            'success'=>true,
            'data'=>array(
                'foo'=>$foo,
                'name'=>'Aaron Conran',
                'username'=>'AaronConran',
                'company'=>'Sencha Inc.',
                'email'=>print_r($_SESSION,true), //'aaron@sencha.com',
		'started'=>false
             )
	);
        if( isset( $_SESSION['start'] ) and $_SESSION['start'] == true )
            $response['data']['started'] = true;
        return  $response;
        return array(
            'success'=>false,
            'errorMessage'=>"Consignment reference not found"
        );
    }

    function getPhoneInfo($userId) {
        return array(
            'success'=>true,
            'data'=>array(
                'cell'=>'443-555-1234',
                'office'=>'1-800-CALLEXT',
                'home'=>''
            )
        );
    }

    function getLocationInfo($userId) {
        return array(
            'success'=>true,
            'data'=>array(
                'street'=>'1234 Red Dog Rd.',
                'city'=>'Seminole',
                'state'=>'FL',
                'zip'=>33776
            )
        );
    }
}
