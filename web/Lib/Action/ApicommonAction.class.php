<?php

/**
 * 所有Api文件的入口类
 *
 * @author czeng
 */
class ApicommonAction extends Action {

	protected $log = array();

	/**
	 * 对所有请求进行sql注入过滤，如果出现请求非法，就抛出异常，未注册的GPC参数则删除
	 */
	protected function _initialize() {
		$this->log['starttime'] = microtime(true);
		//是数字类
		$numTypeArr = array(
			"id",
			"prouserid",
		);
		//是字符串类
		$strTypeArr = array(
			"key",
			"action",
			"retype",
			"url",
			);
		//其它无关参数
		$otherTypeArr = array(

			);
		$allTypeArr = array_merge((array) $otherTypeArr, (array) $strTypeArr, (array) $numTypeArr);
		//处理字符串型的POST数组，非法抛出异常
		foreach ($_POST as $k => $v) {
			if (in_array($k, $numTypeArr) && !is_numeric($v) && '' != $v) {
				$this->_doReqError(10002);
			}
		}
		//处理字符串型的GET数组，非法抛出异常
		foreach ($_GET as $k => $v) {
			if (!in_array($k, $allTypeArr)) {
				unset($_GET[$k]);
			}
			else {
				if (in_array($k, $numTypeArr) && !is_numeric($v) && '' != $v) {
					$this->_doReqError(10002);
				}
				if (in_array($k, $strTypeArr)) {
					$_GET[$k] = addslashes(trim($_GET[$k]));
				}
			}
		}
		//COOKIE is same to GET
		//处理字符串型的COOKIE数组，非法抛出异常
		foreach ($_COOKIE as $k => $v) {
			if (!in_array($k, $allTypeArr)) {
				unset($_COOKIE[$k]);
			}
			else {
				if (in_array($k, $numTypeArr) && !is_numeric($v) && '' != $v) {
					$_COOKIE[$k] = floatval($_COOKIE[$k]);
				}
				if (in_array($k, $strTypeArr)) {
					$_COOKIE[$k] = addslashes($_COOKIE[$k]);
				}
			}
		}
		//重新生成$_REQUEST
		$_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
	}

	/**
	 * jsonp格式返回数据，登录时跨域post提交用
	 * @param array $data 需返回的数据数组
	 * @param string $info 需返回的信息
	 * @param int $status 需返回的状态
	 */
	protected function _jsonpreturn($data='', $info='', $status='') {
		header("Content-Type:text/html; charset=utf-8");
		echo htmlentities($_REQUEST['jsoncallback']) . '(' . json_encode(array('info' => $info, 'data' => $data, 'status' => $status)) . ')';
		exit;
	}

}
?>
