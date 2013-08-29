<?php
/*
 * event计数类
 */

/**
 * Description of Eventlog
 * event计数首先根据请求，记录到redis内，设定为string
 * key   ：  event:{类型:adsview|edm...}:{时间段，精确到小时}:{地区}:{eventid}:{view|click}
 * value :   view|click的次数，用incr做增长
 * ttl   :   6Hour
 * 每个小时执行定时脚本，统计上一小时的统计，并按月份存入 event{Ym}表内
 *
 * @author yuanhao.zuo
 */
class EventAction extends ApicommonAction{

	//存入redis的时间
	public $ttl = 21600;

	//all action
	protected $_allAction = array('view','click');

	//广告调用事件统计
	private $_adCallEventlog = 0;

	/**
	 * 广告计数
	 */
	public function q(){
		$type = $_GET['key'];
		$action = $_GET['action'];
		$id = $_GET['id'];

		//判断type是否合法
		if(!in_array($type, C('ALL_EVENT_TYPE'))){
			$this->_jsonpreturn('', 'error:wrong type', 0);
		}
		//判断action是否合法
		if(!in_array($action, $this->_allAction)){
			$this->_jsonpreturn('', 'error:wrong action', 0);
		}

		//判断id在event:ids:$type中是否存在
		$redis = D(GROUP_NAME.'.REvent');
		if($redis->isExistsIdInType($id,$type)){
			//注册统计用的key
			$tjKey = 'event:' . $type . ':' . strtotime(date('YmdH') . '0000');
			$tjVal = urlencode($this->_getRegion()) . ':' . $id . ':' . $action;
			$redis->regTongjiKey($tjKey, $tjVal);

			//拼接计数的redisKey
			$key = $tjKey . ':' . $tjVal;
			//增加记录
			$redis->doIncr($key,$this->ttl);

			//事件统计，根据userid
			if($_COOKIE['prouserid']){
				$this->_adCallEventlog = 1;
				$this->_doE( $id.'ad'.intval($_COOKIE['prouserid']), $action );
			}
		}

		if('click' == $action && '' != $_GET['url']){
			header('Location: '.rawurldecode($_GET['url']));
		}
		else {
			$this->_jsonpreturn('', 'success', 1);
		}
	}

	/**
	 * 事件计数
	 * http://event.51fanli.com/api/event/e/key/{eventid type element}/action/{view|click}/(retype/png)(url/url)
	 * 事件统计接口:
	 * key：事件id 事件类型 事件元素 ；事件类型默认为null 元素默认为0
	 * action：view或click
	 * retype：选填，返回类型：默认为json；png为1*1像素的png图片
	 * url：选填，需要跳转的url
	 */
	public function e(){
		$id = $_GET['key'];
		$action = $_GET['action'];
		$retype = isset($_GET['retype']) ? $_GET['retype'] : '';

		// 统计处理
		$this->_doE($id, $action);

		if('click' == $action && '' != $_GET['url']){
			header('Location: '.rawurldecode($_GET['url']));
		}
		else {
			//返回的格式
			switch ($retype){
				case 'png':
					header("Content-Type: image/png");
					//bin2hex(file_get_contents('http://static2.51fanli.net/common/images/loading/spacer.png'));
					echo pack('H*','89504e470d0a1a0a0000000d494844520000000100000001080300000028cb34bb00000006504c5445ffffff00000055c2d37e0000000174524e530040e6d8660000000a4944415408d76360000000020001e221bc330000000049454e44ae426082');
					break;
				default :
					$this->_jsonpreturn('', 'success', 1);
					break;
			}
		}

	}

	/**
	 * 统计处理
	 * key：事件id 事件类型 事件元素 ；事件类型默认为null 元素默认为0
	 * action：view或click
	 */
	private function _doE($id, $action){
		//判断action是否合法
		if(!in_array($action, $this->_allAction)){
			$this->_jsonpreturn('', 'error:wrong action', 0);
		}

		//获取统计数据
		$data = $this->_getEventlogParams($id);

		//统计
		if(false !== $data){
			$redis = D(GROUP_NAME . '.REvent');
			//注册统计用的key
			$tjKey = 'eventlog:' . strtotime(date('YmdH') . '0000');
			$tjVal = $data['eventid'] . ':' . $action;
			$redis->regTongjiKey($tjKey, $tjVal);

			//redisKey
			$key = $tjKey . ':' . $tjVal;
			//reidsValue
			$value = $data['type'].'-'.$data['element'].'-'.urlencode($data['region']);
			$redis->doEventlogIncr($key,$value);
		}
	}

	/**
	 * 获取事件统计参数
	 * @param string $id
	 * @param string $action view|click
	 * @return array 参数eventid,type,element,region,action
	 */
	private function _getEventlogParams($id){
		$data = array();
		$eventid = (int)$id;//事件id
		$type = '';//事件类型

		//获取type
		if(is_numeric($id)){
			$type = 'null';
		}else{
			$idLen = strlen($id);
			for($i = 0; $i<$idLen; $i++){
				if(!is_numeric($id[$i])){
					$type .= $id[$i];
				}
			}
		}

		//是否在统计列表内
		$redis = D(GROUP_NAME.'.REvent');
		if(!$redis->isInEventlogReordList($eventid.$type)){
			if($this->_adCallEventlog){
				// 注册统计列表
				$redis->regTongjiKey('eventlog:record:list', $eventid.$type);
			}else{
				return false;
			}
		}

		//获取element
		$element = 0;//事件元素
		if('null' != $type){
			$element = (int)substr($id,strlen($eventid)+strlen($type));
		}

		$data['eventid'] = $eventid;
		$data['type'] = $type;
		$data['element'] = $element;
		$data['region'] = $this->_getRegion();

		return $data;
	}


	//根据ip获取地区
	private function _getRegion(){
		$ip = get_client_ip();
		import("ORG.Net.IpLocation");
	    $qqwryFile = "qqwry.dat";

	    $iplocation = new IpLocation($qqwryFile);
	    $chkArress = $iplocation->getlocation($ip);
	    return iconv('gb2312', 'utf-8', $chkArress['country']);
	}

}

?>