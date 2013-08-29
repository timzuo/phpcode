<?php

/**
 * Description of CronAction
 * 定时脚本类
 *
 * @author Alfred
 */
class CronAction extends CommonAction {

	//存入的表event{Ym}
	protected $_eventTable = '';
	//记录的时间戳
	protected $_eventHour = '';

	private $_banner = array();

	public function __construct() {
		parent::__construct();

		ignore_user_abort(true);
		set_time_limit(0);

		$slowJobs = array_map('strtolower', array(
			'recordTbl',
			'recordEventlog',
				));
		
		/*
		if (in_array(strtolower(ACTION_NAME), $slowJobs)
				&& !(isset($_REQUEST['action']) && $_REQUEST['action'] == 'force')
				&& !D('Redis')->aheadUpdateLock("fun:cron:" . ACTION_NAME, 0, C('MIN_CACHE_TIME'))) {
			header('Content-Type: text/plain; charset=utf-8');
			echo "在" . C('MIN_CACHE_TIME') . "秒内只允许执行一遍，如需强制刷新请传递参数?action=force";
			exit;
		}
		*/

		if(isset($_GET['debuglog'])){
			C('DEBUGLOG',$_GET['debuglog']);
		}
		echo 'debuglog = '.C('DEBUGLOG');
	}

	protected function _newkey() {
		return ':newkey-' . uniqid();
	}

	////////////////////////////////////////////////////广告统计start/////////////////////////////////////////////////
	/**
	 * 根据类型设置需要统计的对象的id集合
	 */
	public function setTypeIds() {
		//判断密钥
		if ($_GET['secret_key'] != C('SECRET_KEY')) {
			throw_exception('error:wrong secret_key');
		}

		$model = D('Api.Event');
		$redis = D('Api.REvent');

		//获取广告系统的ids
		$adsviewIds = $model->getAdsviewIds();
		if( allowDebuglogByLevel(array(0,1)) ){
			$redis->setIdsByType($adsviewIds, 'adsview');
		}
	}

	/**
	 * 记录至数据库，用户定时程序调用
	 */
	public function recordTbl() {
		//公共请求参数进行判断 START
		$checkFields = 'secret_key';
		$this->_doReq($checkFields);

		//判断密钥
		if ($_GET['secret_key'] != C('SECRET_KEY')) {
			throw_exception('error:wrong secret_key');
		}

		$this->_eventTable = 'event' . date('Ym', strtotime('-1 hours'));

		debuglog("start#eventTable = ".$this->_eventTable);

		//判断是否已做过记录（防止重复统计）
		if( allowDebuglogByLevel(array(0,1)) ){
			$this->_checkIsRecord();
		}

		//获取数据
		$data = $this->_getData();

		//插入数据
		$this->_insertData($data);

		$this->setTypeIds();

		//删除过期的注册统计的key
		if( allowDebuglogByLevel(array(0,1)) ){
			D('Api.REvent')->delExpiredRegTongjiKey('event:adsview:');
		}
		debuglog("删除过期5小时的redis里的统计缓存");

		debuglog("end!");
	}

	/**
	 * 判断是否可以做记录（防止重复统计）
	 */
	private function _checkIsRecord() {
		$redis = D('Api.REvent');
		if ($redis->isRecorded()) {
			debuglog("end! - alreay done");
			throw_exception('error:already done');
		}
	}

	/**
	 * 获取数据
	 * key : event:{类型:adsview|edm...}:{时间段，精确到小时}:{地区}:{eventid}:{view|click}
	 */
	private function _getData() {
		//统计上一小时的数据时间
		$hour = strtotime(date('YmdH', strtotime('-1 hours')) . '0000');
		//$hour = strtotime(date('YmdH').'0000');
		debuglog("获取上一小时数据： hour = " . date('YmdH', strtotime('-1 hours')) . '0000 ; 时间戳$hour = '.$hour );
		$this->_eventHour = $hour;
		//注册的统计用key集合
		$redis = D('Api.REvent');
		return $redis->getEventAdsviewData($hour);
	}

	/**
	 * 插入数据
	 */
	private function _insertData($data) {
		//记录数据至日志表
		$this->_insertData2Log($data);
		//记录数据至分析表
		$this->_insertData2Analytic($data);
	}

	/**
	 * 插入数据至日志表
	 * @param array $data 数据
	 */
	private function _insertData2Log($data) {
		$model = D('Api.Event');
		//插入log表
		if( allowDebuglogByLevel(array(3)) ) $this->_eventTable = 'debuglog_' . $this->_eventTable;
		$model->insertLog($data, $this->_eventHour, $this->_eventTable);
		echo 'insertlog success';
	}

	/**
	 * 插入数据至分析表
	 * @param array $data 数据
	 */
	private function _insertData2Analytic($data) {
		$rsData = array();
		//计数累加
		foreach ($data as $v) {
			$appkey = $v['type'] . '-' . $v['eventid'];
			$rsData[$appkey] = array(
				'view' => $rsData[$appkey]['view'] + $v['view'],
				'click' => $rsData[$appkey]['click'] + $v['click'],
			);
		}

		$model = D('Api.Event');
		$model->insertAnalytic($rsData);
		echo 'insertAnalytic success';
	}

	////////////////////////////////////////////////////广告统计end/////////////////////////////////////////////////

	
	////////////////////////////////////////////////////事件统计start/////////////////////////////////////////////////
	/**
	 * 事件统计
	 * http://fun.51fanli.com/cron/recordeventlog/secret_key/{secret_eky}
	 */
	public function recordEventlog() {
		//判断密钥
		$this->_checkSecretKey();

		//判断是否需要统计
		$this->_checkEventlogIsRecorded();

		//获取数据
		$data = $this->_getEventlogData();

		//插入eventlog{Ym}日志表
		$this->_insertData2Eventlog($data);

		//设置下一小时需要统计的数据
		$this->setEventlogRecordList();

		//删除过期的redis里的统计缓存
		$this->_delExpiredEventLogCache();
	}

	/**
	 * 判断密钥
	 */
	private function _checkSecretKey() {
		if ($_GET['secret_key'] != C('SECRET_KEY')) {
			throw_exception('error:wrong secret_key');
		}
	}

	/**
	 * 判断是否需要统计
	 */
	private function _checkEventlogIsRecorded() {
		$redis = D('Api.REvent');
		if ($redis->eventlogIsRecorded()) {
			throw_exception('error:already done');
		}
	}

	/**
	 * 获取事件统计的数据
	 */
	private function _getEventlogData() {
		//统计上一小时的数据时间
		$hour = strtotime(date('YmdH', strtotime('-1 hours')) . '0000');
		$this->_eventHour = $hour;
		$redis = D('Api.REvent');
		$data = $redis->getEventlogData($hour);
		return $data;
	}

	/**
	 * 插入时间统计数据至日志表
	 */
	private function _insertData2Eventlog($data) {
		$model = D('Api.Event');
		//插入log表
		$model->insertEventlog($data, $this->_eventHour, 'eventlog' . date('Ym', strtotime('-1 hours')));
		echo 'insertlog success';
	}

	/**
	 * 设置下一小时需要统计的数据
	 */
	public function setEventlogRecordList() {
		//判断密钥
		$this->_checkSecretKey();

		//获取待统计的事件
		$model = D('Api.Event');
		$data = $model->getEventlogRecordList();

		$recordList = array();
		foreach ($data as $v) {
			$eventTypes = explode(',', $v['type']);
			foreach ($eventTypes as $et) {
				$recordList[] = $v['eventid'] . $et;
			}
		}

		//加入统计列表
		$redis = D('Api.REvent');
		$redis->setEventlogRecordList($recordList);
	}

	/**
	 * 删除过期5小时的redis里的统计缓存
	 */
	private function _delExpiredEventlogCache() {
		$redis = D('Api.REvent');
		$redis->delExpiredEventlogCache();
	}

	function curl_short_timeout(& $ch) {
		curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 1);
		curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, 0);
		// BOTH CURLOPT_[CONNECT]TIMEOUT AND CURLOPT_[CONNECT]TIMEOUT_MS
		// CAN NOT LESS THAN ONE SECOND (ENV: PHP 5.3.2, LIBCURL 7.19.7)
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 1);
	}

	////////////////////////////////////////////////////事件统计end/////////////////////////////////////////////////
}
