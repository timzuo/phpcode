<?php
error_reporting(0);
$handle = @fopen("./index.php", "r");
if ($handle) {
	while (($buffer = fgets($handle, 4096)) !== false) {
		if (preg_match('/APP_PATH[\'\"]*\s*,\s*[\'\"]([^\'\"]*)/', $buffer, $match)) {
			define('APP_PATH', $match[1]);
			$config = include_once $match[1] . '/Conf/config.php';
		}
	}
	fclose($handle);
}
if ('51fanli' == getenv("HTTP_X_FL")) {
	$ip = $_SERVER['HTTP_CLIENT_IP'];
}
else {
	$ip = $_SERVER['REMOTE_ADDR'];
}
$userid = empty($_SESSION['userid']) ? 0 : $_SESSION['userid'];
$time = time();

header('HTTP/1.1 404 Not Found');
header('Status:404 Not Found');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>返利网-淘宝网,淘宝商城,当当网等知名400多家商城购物返现金</title>
<link href="http://static2.51fanli.net/common/css/module/error.css" rel="stylesheet" type="text/css" />
</head>

<body>
<div class="fault">
	<div class="top"></div>
    <div class="content">
    	<div class="logo"><img src="http://static2.51fanli.net/common/images/error/logo-1.png"/></div>
        <div class="right">
       	  <h3>咦...想看的页面不见了？</h3>
            <div class="text">别着急！小财神已经发现啦，正在通知返利网的技术人员呢！请耐心等待哦~</div>
            <div class="jump"><a href="javascript:history.back();">返回上一页&gt;&gt;</a><a href="http://weibo.com/51fanli" class="xinlang" target="_blank">去返利网微博看看&gt;&gt;</a><a href="http://www.51fanli.com/">返回首页&gt;&gt;</a></div>
        </div>
        <div class="clear"></div>
    </div>
    <div class="bottom"></div>
    <div class="clear"></div>
</div>
</body>
</html>
<?php
$mess = '404;' . $_SERVER['SERVER_NAME'] . ';user:' . $userid . ';ref:' . $_SERVER['HTTP_REFERER'] . ';ip:' . $ip;
$ch = curl_init();
$options = array(
	CURLOPT_URL => "http://fun.51fanli.com/actlog/s",
	CURLOPT_POST => true,
	CURLOPT_POSTFIELDS => http_build_query(array('l' => $mess, 't' => $time, 'sn' => md5($mess . $time . $config['LOGIN_SECRET_KEY']))),
	CURLOPT_RETURNTRANSFER => true,
	// BOTH CURLOPT_[CONNECT]TIMEOUT AND CURLOPT_[CONNECT]TIMEOUT_MS
	// CAN NOT LESS THAN ONE SECOND (ENV: PHP 5.3.2, LIBCURL 7.19.7)
	CURLOPT_CONNECTTIMEOUT => 1,
	CURLOPT_TIMEOUT => 1,
);
curl_setopt_array($ch, $options);
curl_exec($ch);
curl_close($ch);
?>