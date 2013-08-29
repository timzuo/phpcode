<?php
/**
 * event计数模型
 *
 * @author yuanhao.zuo
 */
class REventModel extends RedisModel{

	/**
	 * 根据类型获取可用于统计的id数组
	 * @param string $type 类型
	 * @return array id数组
	 */
	public function isExistsIdInType($id,$type){
		return $this->redis->sIsMember('event:ids:'.$type,$id);
	}

	/**
	 * 根据类型设置可用于统计的id数组
	 * @param array $ids id数组
	 * @param string $type 类型
	 */
	public function setIdsByType($ids, $type){
		$cacheKey = 'event:ids:'.$type;
		$tmpCacheKey = $cacheKey .':weyu492hbv8';
		foreach($ids as $id){
			$this->redis->sAdd($tmpCacheKey,$id);
		}
		$this->redis->rename($tmpCacheKey,$cacheKey);
	}

	/**
	 * 统计加1
	 * @param string $key 键名
	 * @param int $ttl 过期时间
	 */
	public function doIncr($key,$ttl){
		if($this->redis->exists($key)){
			$this->redis->incr($key);
		}else{
			$this->redis->setex($key,$ttl,1);
		}
	}

	/**
	 * 判断是否已经记录统计
	 * @return boolean 已统计：true ; 未统计:false
	 */
	public function isRecorded(){
		$cacheKey = 'event:record:log';
		//本小时数据已统计
		if(date('YmdH') == $this->redis->get($cacheKey)){
			debuglog("已统计die：{$cacheKey} = " . date('YmdH'));
			return true;
		}
		//做记录
		debuglog("未统计set：{$cacheKey} = " . date('YmdH'));
		if( allowDebuglogByLevel(array(0,1)) ){
			$this->redis->set($cacheKey,date('YmdH'));
		}
		return false;

	}

	/**
	 * 判断是否已经记录统计
	 * @return boolean 已统计：true ; 未统计:false
	 */
	public function eventlogIsRecorded(){
		$cacheKey = 'eventlog:record:log';
		//本小时数据已统计
		if(date('YmdH') == $this->redis->get($cacheKey)){
			return true;
		}
		//做记录
		$this->redis->set($cacheKey,date('YmdH'));
		return false;
	}

	/**
	 * 获取指定事件的redis统计数据
	 * @param int $hour 时间戳
	 * @return array 统计数据
	 */
	public function getEventlogData($hour){
		$regTongjiKey = 'eventlog:'.$hour;
		$keys = $this->getTongjiKey($regTongjiKey);
		$data = array(); //记录统计数据
		foreach($keys as $v){
			$vArr = explode(':', $v);
			//获取数据
			$eventid = (int)$vArr[0];
			$action = $vArr[1];
			$eventlogArr = $this->redis->zRange($regTongjiKey.':'.$v,0,-1,true);
			foreach($eventlogArr as $key=>$val){
				$eventKey = md5($eventid.'-'.$key);
				$keyArr = explode('-',$key);
				$data[$eventKey]['eventid'] = $eventid;
				$data[$eventKey]['type'] = $keyArr[0];
				$data[$eventKey]['element'] = (int)$keyArr[1];
				$data[$eventKey]['region'] = urldecode($keyArr[2]);
				$data[$eventKey][$action] = $val;
			}
		}
		return $data;
	}

	/**
	 * 是否在需要统计的列表内
	 * @param string $eventKey 事件标识
	 * @return boolean 在列表中true;不在false
	 */
	public function isInEventlogReordList($eventKey){
		return $this->redis->sIsMember('eventlog:record:list',$eventKey);
	}

	/**
	 * 设置待统计的事件列表
	 * @param array $data 事件id+事件类型
	 */
	public function setEventlogRecordList($data){
		$cacheKey = 'eventlog:record:list';
		$tmpCacheKey = $cacheKey .':weyu492hbv8';
		foreach($data as $v){
			$this->redis->sAdd($tmpCacheKey,$v);
		}
		$this->redis->rename($tmpCacheKey,$cacheKey);
	}

	/**
	 * 用score值计数
	 * @param string $key eventlog:{$hour}:{$id}:{view|click}
	 * @param string $value {$type}-{$element}-{$region}
	 */
	public function doEventlogIncr($key,$value){
		$this->redis->zIncrBy($key,1,$value);
	}

	/**
	 * 删除过期5小时的redis里的统计缓存
	 */
	public function delExpiredEventlogCache() {
		$preRegTongjiKey = 'eventlog:';
		$hour = strtotime(date('YmdH', strtotime('-5 hours')) . '0000');
		$regTongjiKey = $preRegTongjiKey . $hour;
		$keys = $this->getTongjiKey($regTongjiKey);
		$delTongjiKey = array();
		foreach ($keys as $v) {
			$delTongjiKey[] = $regTongjiKey . ':' . $v;
		}
		$this->redis->del($delTongjiKey);
		$this->delExpiredRegTongjiKey($preRegTongjiKey, $hour);
	}

	/**
	 * 删除过期的注册统计key
	 * @param string $preKey
	 * @param int $hour
	 */
	public function delExpiredRegTongjiKey($preKey,$hour=0){
		if(empty ($hour)){
			$hour = strtotime(date('YmdH', strtotime('-5 hours')) . '0000');
		}
		$this->redis->del($preKey.$hour);
	}

	/**
	 * 注册统计用的key
	 * @param string $tjKey
	 * @param string $tjVal
	 * @return boolean
	 */
	public function regTongjiKey($key, $val) {
		$this->redis->sAdd($key, $val);
		return true;
	}

	/**
	 * 获取统计key
	 * @param string $key
	 * @return array
	 */
	public function getTongjiKey($key) {
		return $this->redis->sMembers($key);
	}

	/**
	 * 获取指定广告的redis统计数据
	 * @param int $hour 时间戳
	 * @return array 统计数据
	 */
	public function getEventAdsviewData($hour) {
		$regTongjiKey = 'event:adsview:' . $hour;
		$type = 'adsview';
		$keys = $this->getTongjiKey($regTongjiKey);
		debuglog("开始从redis获取数据");
		$data = array(); //记录统计数据
		foreach ($keys as $v) {
			$vArr = explode(':', $v);
			//获取数据
			$eventid = (int) $vArr[1];
			$region = urldecode($vArr[0]);
			$k = md5($eventid . '|' . $type . '|' . $region);
			$data[$k]['eventid'] = $eventid;
			$data[$k]['region'] = $region;
			$data[$k]['type'] = $type;
			$data[$k][$vArr[2]] = (int) $this->redis->get($regTongjiKey . ':' . $v);
			debuglog("##".$eventid . '|' . $type . '|' . $region . '; value = ' .json_encode($data[$k]));
		}
		debuglog("结束从redis获取数据");
		return $data;
	}
}

?>
