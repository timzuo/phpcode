<?php
/**
 * 统计模型
 *
 * @author yuanhao.zuo
 */
class EventModel extends AdvModel{

	/**
	 * 当统计表不存在，则创建
	 * @param string $table 统计表名event{Ym}
	 */
	private function _createLogTbl($table){
		if (false === $this->query('desc ' . $table)) {
			$sql = <<<EOF
CREATE TABLE `$table`(
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `eventid` int(11) unsigned NOT NULL COMMENT '所属id',
  `type` varchar(10) NOT NULL COMMENT '类型(adsview,edm...)',
  `date` int(11) unsigned NOT NULL COMMENT '时间',
  `region` varchar(20) NOT NULL COMMENT '地区',
  `view` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '浏览数',
  `click` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '点击数',
  PRIMARY KEY (`id`),
  KEY `idx_eventid` (`eventid`) USING HASH,
  KEY `idx_type` (`type`) USING HASH,
  KEY `idx_date` (`date`) USING BTREE,
  KEY `idx_region` (`region`) USING HASH
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
EOF;
			$rs = $this->query($sql);
			debuglog("##[rs:$rs]##月表不存在Sql：".$sql);
		}
		debuglog("##取得月表：".$table);
	}

	/**
	 * 插入log表
	 * @param array $data 需要插入的数据
	 * @param int $hour 数据时间
	 * @param string $table 表名
	 */
	public function insertLog($data,$hour,$table){
		$this->addConnect(C('EVENTLOG_MYSQL_DSN'), 1);
		$this->switchConnect(1);
		//检查event{Ym}表是否存在，无则创建
		$this->_createLogTbl($table);
		$sql = 'insert into '.$table.' (`eventid`,`type`, `date`, `region`, `view`, `click`) values ';

		//一次可insert的条数
		$insertRows = C('ONCE_INSERT_ROWS');

		$count = count($data);
		//需要insert的次数
		$loop = ceil($count / $insertRows);
		debuglog("开始插入eventlog数据库： table = $table");
		for($i = 0; $i < $loop; $i++){
			$sqlData = '';
			//起始位置
			$kStart = $i * $insertRows;

			//$data的序号
			$j = 0;
			foreach($data as $v){
				++$j;
				if($j < $kStart || $j >= $kStart + $insertRows){
					continue;
				}
				$sqlData .= '('.$v['eventid'].',"'.$v['type'].'",'.$hour.',"'.$v['region'].'",'.(int)$v['view'].','.(int)$v['click'].'),';
			}
			//echo $sql . substr($sqlData,0,-1);
			//拼接sql
			if( allowDebuglogByLevel(array(0,1,3)) ){
				$rs = $this->query($sql . substr($sqlData,0,-1) );
			}
			debuglog("##[rs:".json_encode($rs)."]##sql : " . $sql . substr($sqlData,0,-1)  );
		}

		debuglog("结束插入eventlog数据库： table = $table");
		$this->closeConnect(1);
	}

	/**
	 * 插入VERYIDE的分析表
	 * @param array $data 统计数据
	 */
	public function insertAnalytic($data){
		$this->addConnect(C('VERYIDE_MYSQL_DSN'), 2);
		$this->switchConnect(2);

		//日期
		$date = array('Y'=> date("Y",strtotime('-1 hours')) ,'M'=> date("Y-m",strtotime('-1 hours')) ,'D'=> date("Y-m-d",strtotime('-1 hours')) ,'H'=> date("H",strtotime('-1 hours')));

		debuglog("开始插入ad的analytic表");

		foreach($data as $k => $v){
			$appkey = $k;
			//查询语句
			$sql = array();
			$sql['COUNT'] = "select count(*) as 'tp_count' from ".C('VI_DBMODPRE')."analytic where appkey='".$appkey."' and category ='{CATE}' and `date`='{DATE}'";
			$sql['UPDATE'] = "update ".C('VI_DBMODPRE')."analytic set views=views+".$v['view'].", clicks=clicks+".$v['click']." where appkey='".$appkey."' and category ='{CATE}' and `date`='{DATE}'";
			$sql['INSERT'] = "insert into ".C('VI_DBMODPRE')."analytic(appkey,category,date,views,clicks) values('".$appkey."','{CATE}','{DATE}','".$v['view']."','".$v['click']."')";

			foreach( $date as $key => $val ){
				$count = str_replace(array('{CATE}','{DATE}'),array($key,$val),$sql['COUNT']);
				$update = str_replace(array('{CATE}','{DATE}'),array($key,$val),$sql['UPDATE']);
				$insert = str_replace(array('{CATE}','{DATE}'),array($key,$val),$sql['INSERT']);

				debuglog("##cntSql : " . $count);
				//查看记录数
				$count = $this->query($count);
				$tp_count = $count[0]['tp_count'];
				debuglog("##[rs:".json_encode($count)."]##rs : " . $tp_count);

				//没有记录
				if(0 == $tp_count){
					//创建数据
					if( allowDebuglogByLevel(array(0,1)) ){
						$this->query($insert);
					}
					debuglog("##insertSql: " . $insert);
				}else{
					//更新数据
					if( allowDebuglogByLevel(array(0,1)) ){
						$this->query($update);
					}
					debuglog("##updateSql: " . $update);
				}
			}
		}
		debuglog("结束插入ad的analytic表");
		$this->closeConnect(2);
	}

	/**
	 * 设置广告系统的ids
	 * @return array
	 */
	public function getAdsviewIds(){
		$this->addConnect(C('VERYIDE_MYSQL_DSN'), 2);
		$this->switchConnect(2);
		//获取广告位
		$adsareaIds = $this->query("SELECT id FROM ".C('VI_DBMODPRE')."adsarea where parent>0");
		debuglog("##获取广告位sql: "."SELECT id FROM ".C('VI_DBMODPRE')."adsarea where parent>0");
		$rsData = array();
		$timeLine = time()+3600;//可用广告的时间线
		debuglog("开始获取待统计的广告id");
		foreach($adsareaIds as $v){
			//获取广告位下可用的广告
			$adsviewIds = $this->query("SELECT id FROM ".C('VI_DBMODPRE')."adsview where `state`>0 and `state`<3 and name<>'' and ( type=".$v["id"]." or CONCAT(',',share) like '%,".$v["id"].",%' ) and start<=".$timeLine." and expire>".time() );
			debuglog("##adsareaSql: "."SELECT id FROM ".C('VI_DBMODPRE')."adsview where `state`>0 and `state`<3 and name<>'' and ( type=".$v["id"]." or CONCAT(',',share) like '%,".$v["id"].",%' ) and start<=".$timeLine." and expire>".time());
			foreach($adsviewIds as $val){
				$rsData[] = $val['id'];
			}
			debuglog("##广告位" . $v['id'] . "广告 : " . json_encode($adsviewIds));
		}
		debuglog("结束获取待统计的广告id");
		$this->closeConnect(2);
		return $rsData;
	}

	/**
	 * 获取待统计的事件列表
	 */
	public function getEventlogRecordList(){
		$this->addConnect(C('EVENTLOG_MYSQL_DSN'), 4);
		$this->switchConnect(4);
		$map['state'] = array('gt',0);

		$this->trueTableName = '51fanli_tb_event';
		$nextHour = strtotime(date('YmdH',  strtotime('+1 hours')).'0000');
		$map['starttime'] = array('elt', $nextHour);
		$map['endtime'] = array('gt',time());
		$rsData = $this->field('id,eventid,type')->where($map)->select();
		$this->closeConnect(4);
		return $rsData;
	}

	/**
	 * 当统计表不存在，则创建
	 * @param string $table 统计表名event{Ym}
	 */
	private function _createEventlogTbl($table){
		if (false === $this->query('desc ' . $table)) {
			$sql = <<<EOF
CREATE TABLE `$table`(
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `eventid` int(11) unsigned NOT NULL COMMENT '所属id',
  `type` varchar(10) NOT NULL COMMENT '类型(adsview,edm...)',
  `element` int(11) NOT NULL COMMENT '类型下的元素',
  `date` int(11) unsigned NOT NULL COMMENT '时间',
  `region` varchar(20) NOT NULL COMMENT '地区',
  `view` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '浏览数',
  `click` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '点击数',
  PRIMARY KEY (`id`),
  KEY `idx_eventid` (`eventid`) USING HASH,
  KEY `idx_type` (`type`) USING HASH,
  KEY `idx_element` (`element`) USING HASH,
  KEY `idx_date` (`date`) USING BTREE,
  KEY `idx_region` (`region`) USING HASH
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
EOF;
			$this->query($sql);
		}
	}

	/**
	 * 插入log表
	 * @param array $data 需要插入的数据
	 * @param int $hour 数据时间
	 * @param string $table 表名
	 */
	public function insertEventlog($data,$hour,$table){
		$table = 'tb_'.$table;//增加表前缀tb_
		$this->addConnect(C('EVENTLOG_MYSQL_DSN'), 1);
		$this->switchConnect(1);
		//检查event{Ym}表是否存在，无则创建
		$this->_createEventlogTbl($table);
		$sql = 'insert into '.$table.' (`eventid`,`type`, `element` , `date`, `region`, `view`, `click`) values ';

		//一次可insert的条数
		$insertRows = C('ONCE_INSERT_ROWS');

		$count = count($data);
		//需要insert的次数
		$loop = ceil($count / $insertRows);

		for($i = 0; $i < $loop; $i++){
			$sqlData = '';
			//起始位置
			$kStart = $i * $insertRows;

			//$data的序号
			$j = 0;
			foreach($data as $v){
				++$j;
				if($j < $kStart || $j >= $kStart + $insertRows){
					continue;
				}
				$sqlData .= '('.$v['eventid'].',"'.$v['type'].'","'.$v['element'].'",'.$hour.',"'.$v['region'].'",'.(int)$v['view'].','.(int)$v['click'].'),';
			}
			//echo $sql . substr($sqlData,0,-1);
			//拼接sql
			$this->query($sql . substr($sqlData,0,-1) );
		}
		$this->closeConnect(1);
	}

}

?>
