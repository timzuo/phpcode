<?php

// +----------------------------------------------------------------------
// | ThinkPHP
// +----------------------------------------------------------------------
// | Copyright (c) 2007 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// $Id$

function actlog($data, $url = "http://fun.51fanli.com/actlog/c") {
    $time = time();
	if (isset($_COOKIE['fl_mvp']) && !isset($data['rn'])) {
		list(,$reason,) = explode(':', $_COOKIE['fl_mvp'], 3);
		$data = $data + array('rn' => $reason);
	}
	if ($data['act'] == 'mtadmin' || $data['act'] == '11330440')
		return; //跳过监控账号日志
	if ($data['act'] == 'passporttest1' || $data['act'] == '12531898')
		return; //跳过监控账号日志
    $data = $data + array('t' => $time) + array('sn' => md5($data['cd'] . $data['act'] . $data['st'] . $time . C('LOGIN_SECRET_KEY')));
    $p = http_build_query($data);
    $ch = curl_init();
    $options = array(
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $p,
        CURLOPT_RETURNTRANSFER => true,
        // BOTH CURLOPT_[CONNECT]TIMEOUT AND CURLOPT_[CONNECT]TIMEOUT_MS
        // CAN NOT LESS THAN ONE SECOND (ENV: PHP 5.3.2, LIBCURL 7.19.7)
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => 1,
    );
    curl_setopt_array($ch, $options);
    $info = curl_exec($ch);
    log_debug($info, 'ACTLOG');
    curl_close($ch);
}

/**
 * 添加分享记录
 * @param string 分享类型，如sina、qq
 * @param string 当前栏目类型，如'团蟹'
 * @return true(sql返回id) or false
 */
function addShareLog($user_id, $share, $type = '') {
    $model = D('Sharelogs');
    $data = array();
    $data = array(
        'ip' => get_client_ip(),
        'referer' => $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : "http://" . $_SERVER['SERVER_NAME'] . $_SERVER["REQUEST_URI"],
        'share' => $share,
        'type' => convUTF82GBK($type),
        'user_id' => $user_id,
    );
    if (false === $model->create($data)) {
        return false;
    }
    if (false === $result = $model->add()) {
        return false;
    } else {
        return $result;
    }
}

/**
 * 通过循环遍历将对象转换为数组
 *
 * @param object $var
 * @return array
 */
function object_to_array($var) {
    if (is_object($var))
        $var = get_object_vars($var);

    if (is_array($var))
        $var = array_map('object_to_array', $var);

    return $var;
}

function getRandomUrl4ASP($f, $pre = '') {
    $flag = hash("crc32", $f) % 5;
    return 'http://i' . $flag . '.51fanli.net' . $pre . $f;
}

function getRandomUrl4PHP($f, $pre = '/fun/') {
    $flag = hash("crc32", $f) % 5;
    return 'http://l' . $flag . '.51fanli.net' . $pre . $f;
}

/**
 *
 * @param type $imagePath
 * @param type $type = 0 新生成的 getRandomUrl4PHP
 * @param type $type = 1 导入数据 getOrginalPic
 * @return type
 */
function getImageUrl($imagePath, $type = 0) {
    $path = pathinfo($imagePath);
    preg_match('%(.+)/(.+)%', $imagePath, $match);
    if ($type == 0) {
        $url = getRandomUrl4PHP($match[2], $match[1] . '/');
    } else {
        $url = getRandomUrl4ASP($match[2], $match[1] . '/');
    }
    return $url;
}

/**
 * 时间格式化，根据当前时间判断，返回类似“2分钟前”这样的时间
 * @param int 时间戳 $time
 * @return string 格式化时间
 */
function formatTime($time) {
    $rtime = date("m月d日 H:i", $time);
    $htime = date("H:i", $time);
    $time = time() - $time;
    if ($time < 60) {
        $str = '刚刚';
    } elseif ($time < 3600) {
        $min = floor($time / 60);
        $str = $min . '分钟前';
    } elseif ($time < 86400) {
        $h = floor($time / 3600);
        $str = $h . '小时前';
    } else {
        $str = $rtime;
    }
    return $str;
}

//对数据进行转码
function convUTF82GBK($str) {
    return IS_WIN ? iconv("UTF-8", "GBK", $str) : $str;
}

//对数据进行转码
function convGBK2UTF8($str) {
    return IS_WIN ? iconv("GBK", "UTF-8", $str) : $str;
}

//对数据进行转码
function reconvUTF82GBK($str) {
    return IS_WIN ? $str : iconv("UTF-8", "GBK", $str);
}

//对数据进行转码
function reconvGBK2UTF8($str) {
    return IS_WIN ? $str : iconv("GBK", "UTF-8", $str);
}

/**
 * 记录调试日志
 * @param type $info
 * @param type $prefix
 */
function log_debug($info, $prefix = '') {
    if (C('APP_DEBUG')) {
        $string = var_export($info, true);
        Log::write('DEBUG信息 ' . $prefix . '： ' . $string, Log::INFO);
    }
}

/**
 * 记录运行日志
 * @param type $info
 * @param type $prefix
 */
function log_info($info, $prefix = '') {
    $string = var_export($info, true);
    Log::write('日志信息 ' . $prefix . '： ' . $string, Log::INFO);
}

/**
 * 切割utf-8字符
 * 数字和英文字母算0.5个字符
 * utf-8汉字算1个字符
 * @param string $string
 * @param int $length
 * @param string $etc 可以设置为...
 * @return string
 */
function strcut($string, $length, $etc = '') {
    $result = '';
    $string = html_entity_decode(trim(strip_tags($string)), ENT_QUOTES, 'UTF-8');
    $strlen = strlen($string);

    for ($i = 0; (($i < $strlen) && ($length > 0)); $i++) {
        $number = strpos(str_pad(decbin(ord(substr($string, $i, 1))), 8, '0', STR_PAD_LEFT), '0');
        if ($number) {
            if ($length < 1.0) {
                break;
            }
            $result .= substr($string, $i, $number);
            $length -= 1.0;
            $i += $number - 1;
        } else {
            $result .= substr($string, $i, 1);
            $length -= 0.5;
        }
    }

    $result = htmlspecialchars($result, ENT_QUOTES, 'UTF-8');

    if ($i < $strlen) {
        $result .= $etc;
    }
    return $result;
}

function cutstr($string, $length = 100, $dot = '...') {
    $charset = 'utf-8';
    $string = preg_replace('/\s+/m', ' ', $string);
    if (strlen($string) <= $length) {
        return $string;
    }
    $string = str_replace(array('&amp;', '&quot;', '&lt;', '&gt;'), array('&', '"', '<', '>'), $string);
    $strcut = '';
    if (strtolower($charset) == 'utf-8') {
        $n = $tn = $noc = 0;
        while ($n < strlen($string)) {
            $t = ord($string[$n]);
            if ($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
                $tn = 1;
                $n++;
                $noc++;
            } elseif (194 <= $t && $t <= 223) {
                $tn = 2;
                $n += 2;
                $noc += 2;
            } elseif (224 <= $t && $t < 239) {
                $tn = 3;
                $n += 3;
                $noc += 2;
            } elseif (240 <= $t && $t <= 247) {
                $tn = 4;
                $n += 4;
                $noc += 2;
            } elseif (248 <= $t && $t <= 251) {
                $tn = 5;
                $n += 5;
                $noc += 2;
            } elseif ($t == 252 || $t == 253) {
                $tn = 6;
                $n += 6;
                $noc += 2;
            } else {
                $n++;
            }
            if ($noc >= $length) {
                break;
            }
        }
        if ($noc > $length) {
            $n -= $tn;
        }
        $strcut = substr($string, 0, $n);
    } else {
        for ($i = 0; $i < $length; $i++) {
            $strcut .= ord($string[$i]) > 127 ? $string[$i] . $string[++$i] : $string[$i];
        }
    }
    $strcut = str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $strcut);
    return $strcut . $dot;
}

/**
 *
 * 截取GBK中文字符
 */
function GBsubstr($string, $start, $length, $dot = '') {
    if (strlen($string) > $length) {
        $str = null;
        $len = $start + $length;
        for ($i = $start; $i < $len; $i++) {
            if (ord(substr($string, $i, 1)) > 0xa0) {
                $str.=substr($string, $i, 2);
                $i++;
            } else {
                $str.=substr($string, $i, 1);
            }
        }
        return $str . $dot;
    } else {
        return $string;
    }
}

function toDate($time = NULL, $format = 'Y-m-d H:i:s') {
    if (empty($time)) {
        $time = time();
    }
    $format = str_replace('#', ':', $format);
    return date($format, $time);
}

function get_client_ip() {
    if ('51fanli' == getenv("HTTP_X_FL")) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function IP($ip = '', $file = 'QQWry.dat') {
    $_ip = array();
    if (isset($_ip [$ip])) {
        return $_ip [$ip];
    } else {
        import("@.ORG.IpLocation");
        $iplocation = new IpLocation($file);
        $location = $iplocation->getlocation($ip);
        $_ip [$ip] = $location ['country'] . $location ['area'];
    }
    return $_ip [$ip];
}

/**
  +----------------------------------------------------------
 * 获取登录验证码 默认为4位数字
  +----------------------------------------------------------
 * @param string $fmode 文件名
  +----------------------------------------------------------
 * @return string
  +----------------------------------------------------------
 */
function build_verify($length = 4, $mode = 1) {
    return rand_string($length, $mode);
}

/**
  +----------------------------------------------------------
 * 产生随机字串，可用来自动生成密码
 * 默认长度6位 字母和数字混合 支持中文
  +----------------------------------------------------------
 * @param string $len 长度
 * @param string $type 字串类型
 * 0 字母 1 数字 其它 混合
 * @param string $addChars 额外字符
  +----------------------------------------------------------
 * @return string
  +----------------------------------------------------------
 */
function rand_string($len = 6, $type = '', $addChars = '') {
    $str = '';
    switch ($type) {
        case 0 :
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz' . $addChars;
            break;
        case 1 :
            $chars = str_repeat('0123456789', 3);
            break;
        case 2 :
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' . $addChars;
            break;
        case 3 :
            $chars = 'abcdefghijklmnopqrstuvwxyz' . $addChars;
            break;
        default :
            // 默认去掉了容易混淆的字符oOLl和数字01，要添加请使用addChars参数
            $chars = 'ABCDEFGHIJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789' . $addChars;
            break;
    }
    if ($len > 10) { //位数过长重复字符串一定次数
        $chars = $type == 1 ? str_repeat($chars, $len) : str_repeat($chars, 5);
    }
    if ($type != 4) {
        $chars = str_shuffle($chars);
        $str = substr($chars, 0, $len);
    } else {
        // 中文随机字
        for ($i = 0; $i < $len; $i++) {
            $str .= msubstr($chars, floor(mt_rand(0, mb_strlen($chars, 'utf-8') - 1)), 1);
        }
    }
    return $str;
}

function pwdHash($password, $type = 'md5') {
    return hash($type, $password);
}

/**
 * 一次给多个用户发送邮件,
 */
function multiSendMail($subject = '', $message = '', $from = '', $to = '') {
    $from = preg_replace('/\v*/', '', $from);
    if (empty($from) || empty($to)) {
        return false;
    }
    $to = explode(',', preg_replace('/\v*/', '', $to . ',' . C('SERVEEMAIL')));
    foreach ($to as $value) {
        if (!empty($value)) {
            sendMail($subject, $message, $from, $value);
        }
    }
}

// 参数设置 string $subject, string $message, 'name <name@server.suffix>' $from, 'name1 <name1@server1.suffix>,name2 <name2@server2.suffix>....' $to
function sendMail($subject = '', $message = '', $from = '', $to = '') {
    if (empty($subject) || empty($to)) {
        throw new Exception('参数错误');
    }
    $mail = array(
        'charset' => "UTF-8",
        'adminemail' => "",
        'sendmail_silent' => 1,
        'maildelimiter' => 1,
        'mailusername' => 1,
        'mailsubject' => $subject,
        'mailmessage' => $message,
        'from' => C('CHANNEL_MAIL_FROM'),
        'mailto' => $to,
        'server' => C('CHANNEL_MAIL_SERVER'),
        'port' => C('CHANNEL_MAIL_PORT'),
        'mailsend' => 2,
        'auth' => 1,
        'auth_username' => C('CHANNEL_MAIL_AUTH_USERNAME'),
        'auth_password' => C('CHANNEL_MAIL_AUTH_PASSWORD'),
    );

    if ($mail['sendmail_silent']) {
        error_reporting(0);
    }

    $email_from = $mail['from'];
    $email_to = $mail['mailto'];
    $charset = isset($mail['charset']) ? $mail['charset'] : 'UTF-8';
    $maildelimiter = $mail['maildelimiter'] == 1 ? "\r\n" : ($mail['maildelimiter'] == 2 ? "\r" : "\n");
    $mailusername = isset($mail['mailusername']) ? $mail['mailusername'] : 1;

    $email_subject = '=?' . $charset . '?B?' . base64_encode(str_replace("\r", '', str_replace("\n", '', $mail['mailsubject']))) . '?=';
    $email_message = chunk_split(base64_encode(str_replace("\r\n.", " \r\n..", str_replace("\n", "\r\n", str_replace("\r", "\n", str_replace("\r\n", "\n", str_replace("\n\r", "\r", $mail['mailmessage'])))))));

    $email_from = $email_from == '' ? '=?' . $charset . '?B?' . base64_encode('管理员') . "?= <" . $mail['adminemail'] . ">" : (preg_match('/^(.+?) \<(.+?)\>$/', $email_from, $from) ? '=?' . $charset . '?B?' . base64_encode($from[1]) . "?= <$from[2]>" : $email_from);

    foreach (explode(',', $email_to) as $touser) {
        $tousers[] = preg_match('/^(.+?) \<(.+?)\>$/', $touser, $to) ? ($mailusername ? '=?' . $charset . '?B?' . base64_encode($to[1]) . "?= <$to[2]>" : $to[2]) : $touser;
    }
    $email_to = implode(',', $tousers);

    $headers = "From: $email_from{$maildelimiter}X-Priority: 3{$maildelimiter}X-Mailer: Mailman{$maildelimiter}MIME-Version: 1.0{$maildelimiter}Content-type: text/html; charset=$charset{$maildelimiter}Content-Transfer-Encoding: base64{$maildelimiter}";

    $mail['port'] = $mail['port'] ? $mail['port'] : 25;

    if ($mail['mailsend'] == 1 && function_exists('mail')) {

        @mail($email_to, $email_subject, $email_message, $headers);
    } elseif ($mail['mailsend'] == 2) {

        if (!$fp = fsockopen($mail['server'], $mail['port'], $errno, $errstr, 30)) {
            throw new Exception('SMTP ' . "($mail[server]:$mail[port]) CONNECT - Unable to connect to the SMTP server");
        }
        stream_set_blocking($fp, true);

        $lastmessage = fgets($fp, 512);
        if (substr($lastmessage, 0, 3) != '220') {
            throw new Exception('SMTP ' . "$mail[server]:$mail[port] CONNECT - $lastmessage");
        }

        fputs($fp, ($mail['auth'] ? 'EHLO' : 'HELO') . " Mailman\r\n");
        $lastmessage = fgets($fp, 512);
        if (substr($lastmessage, 0, 3) != 220 && substr($lastmessage, 0, 3) != 250) {
            throw new Exception('SMTP ' . "($mail[server]:$mail[port]) HELO/EHLO - $lastmessage");
        }

        while (1) {
            if (substr($lastmessage, 3, 1) != '-' || empty($lastmessage)) {
                break;
            }
            $lastmessage = fgets($fp, 512);
        }

        if ($mail['auth']) {
            fputs($fp, "AUTH LOGIN\r\n");
            $lastmessage = fgets($fp, 512);
            if (substr($lastmessage, 0, 3) != 334) {
                throw new Exception('SMTP ' . "($mail[server]:$mail[port]) AUTH LOGIN - $lastmessage");
            }

            fputs($fp, base64_encode($mail['auth_username']) . "\r\n");
            $lastmessage = fgets($fp, 512);
            if (substr($lastmessage, 0, 3) != 334) {
                throw new Exception('SMTP ' . "($mail[server]:$mail[port]) USERNAME - $lastmessage");
            }

            fputs($fp, base64_encode($mail['auth_password']) . "\r\n");
            $lastmessage = fgets($fp, 512);
            if (substr($lastmessage, 0, 3) != 235) {
                throw new Exception('SMTP ' . "($mail[server]:$mail[port]) PASSWORD - $lastmessage");
            }

            $email_from = $mail['from'];
        }

        fputs($fp, "MAIL FROM: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $email_from) . ">\r\n");
        $lastmessage = fgets($fp, 512);
        if (substr($lastmessage, 0, 3) != 250) {
            fputs($fp, "MAIL FROM: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $email_from) . ">\r\n");
            $lastmessage = fgets($fp, 512);
            if (substr($lastmessage, 0, 3) != 250) {
                throw new Exception('SMTP ' . "($mail[server]:$mail[port]) MAIL FROM - $lastmessage");
            }
        }

        $email_tos = array();
        foreach (explode(',', $email_to) as $touser) {
            $touser = trim($touser);
            if ($touser) {
                fputs($fp, "RCPT TO: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $touser) . ">\r\n");
                $lastmessage = fgets($fp, 512);
                if (substr($lastmessage, 0, 3) != 250) {
                    fputs($fp, "RCPT TO: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $touser) . ">\r\n");
                    $lastmessage = fgets($fp, 512);
                    throw new Exception('SMTP ' . "($mail[server]:$mail[port]) RCPT TO - $lastmessage");
                }
            }
        }

        fputs($fp, "DATA\r\n");
        $lastmessage = fgets($fp, 512);
        if (substr($lastmessage, 0, 3) != 354) {
            throw new Exception('SMTP ' . "($mail[server]:$mail[port]) DATA - $lastmessage");
        }

        $headers .= 'Message-ID: <' . gmdate('YmdHs') . '.' . substr(md5($email_message . microtime()), 0, 6) . rand(100000, 999999) . '@' . $_SERVER['HTTP_HOST'] . ">{$maildelimiter}";

        fputs($fp, "Date: " . gmdate('r') . "\r\n");
        fputs($fp, "To: " . $email_to . "\r\n");
        fputs($fp, "Subject: " . $email_subject . "\r\n");
        fputs($fp, $headers . "\r\n");
        fputs($fp, "\r\n\r\n");
        fputs($fp, "$email_message\r\n.\r\n");
        $lastmessage = fgets($fp, 512);
        if (substr($lastmessage, 0, 3) != 250) {
            throw new Exception('SMTP ' . "($mail[server]:$mail[port]) END - $lastmessage");
        }

        fputs($fp, "QUIT\r\n");
    } elseif ($mail['mailsend'] == 3) {

        ini_set('SMTP', $mail['server']);
        ini_set('smtp_port', $mail['port']);
        ini_set('sendmail_from', $email_from);

        @mail($email_to, $email_subject, $email_message, $headers);
    }
}

/**
 * 用户名只能为英文字符、数字或汉字(3~25位)、下划线。
 * @param string $username
 * @return boolean
 */
function isLegalUsername($username) {
    return preg_match("/^[\x{4e00}-\x{9fa5}A-Za-z0-9_]+$/u", $username) &&
            mb_strlen($username, 'UTF-8') >= 3 &&
            mb_strlen($username, 'UTF-8') <= 25;
}

/**
 * 检查给定的密码是否合法
 *
 * 合法的密码:
 * 1、	密码长度在6-25位之间
 * 2、	密码必须至少包含数字和英文字母
 * 3、	密码不可为汉字，可为ASCII码任意字符
 * @param string $username
 * @return boolean
 */
function isLegalPassword($password) {
    //有中文或全角
    if (preg_match("/[\x80-\xff]./i", $password)) {
        return false;
    } else {
        $hasNum = ereg('[0-9]', $password);
        $hasLetter = ereg('[a-zA-Z]', $password);
        //包含数字、字符、长度6-25
        if ($hasNum && $hasLetter && mb_strlen($password, 'UTF-8') >= 6 && mb_strlen($password, 'UTF-8') <= 25) {
            return true;
        }
    }
    return false;
}

/**
 * 验证email：Email只可为6~50位的数字、字母、下划线和点组成，且首字符必须为字母或数字
 */
function chkemail($email) {
    return strlen($email) >= 6 && strlen($email) <= 50 && preg_match("/^[\w][\w-_]+(\.[\w-]+)*@[\w-]+(\.[\w-]+)+$", $email);
}

/**
 * 给密码进行分级
 * @param string $pw
 * @return boolean
 */
function passwdGrade($pw) {
    $grade = 0;
    if (strlen($pw) >= 6) {
        //全数字 || 全英文
        if (strlen(preg_replace("/^(.)\\1+$/", "", $pw)) == 0) {
            $grade = 1;
        } else if (preg_match("/^\d*$/", $pw) || preg_match("/^[a-z]*$/i", $pw)) {
            $grade = 1;
        } else if (preg_match("/^[a-z\d]*$/i", $pw)) {
            $grade = 2;
        } else {
            $grade = 3;
        }
    }
    return $grade;
}

//将数据压缩
function zipString($str) {
    return $str;
    //return gzdeflate($str, 9);
}

function matchip($value) {
    return preg_match("/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?).){4}$/", $value . ".");
}

/**
 * 对某个字段如果是null或者空的处理方式
 * @param type $str 判断的字符
 * @param type $rep 替换的默认值
 */
function checknull($str, $rep = '') {
    if ($str === null) {
        return $rep;
    } else {
        return $str;
    }
}

/**
 * 将论坛首页晒单列表按照vote排序
 * @param array $list
 * @return array
 */
function sortBbsShaidan($list) {
    $result = $vote = array();
    foreach ($list as $key => $val) {
        $val['P_Title_all'] = convGBK2UTF8($val['P_Title']);
        $val['P_Title'] = cutstr(convGBK2UTF8($val['P_Title']), 76, '');
        $val['UserName'] = cutstr(convGBK2UTF8($val['UserName']), 18, '');
        $val['UserName_all'] = convGBK2UTF8($val['UserName']);
        $val['B_Name'] = convGBK2UTF8($val['B_Name']);
        $val['tpic'] = getRandomUrl4ASP($val['tpic']);
        $result[$key] = $val;
        // 取得列的列表
        $vote[$key] = $val['vote'];
    }

    //按照有用数排序
    array_multisort($vote, SORT_DESC, $result);

    return $result;
}

/**
 * Escapes special characters in a string, replace function mysql_real_escape_string
 * @param mix $data
 * @return mix $data
 */
function escapeString($data) {
    if (is_string($data)) {
        $data = addslashes($data);
    } elseif (is_array($data)) {
        $data = array_map(__FUNCTION__, $data);
    }
    return $data;
}

/**
 * 商品跳转列表处理
 * @param int 商城id
 * @param string 商品地址
 * @return string 商品跳转地址
 */
function urlTracking($shopid, $url, $dn = 0) {
    //$code = '';
    if (empty($_REQUEST['trackingcode'])) {
        switch ((int) ($shopid)) {
            case 450://当当网
                $code = '0004';
                break;
            case 544://京东
                $code = '0008';
                break;
            case 574://新蛋
                $code = '0012';
                break;
            case 499://红孩子
                $code = '0016';
                break;
            default :
                $code = '';
                break;
        }
        if (!empty($code)) {
            $code = 'tc=' . $code . date('d');
        }
        return C('ITEM_URL') . $code . '&id=' . $shopid . '&go=' . urlencode($url) . ($dn ? '&dn=' . $dn : '');
    } else {
        $code = rawurldecode($_REQUEST['trackingcode']);
        $code = 'tc=' . $code;
        return C('ITEM_URL') . $code . '&id=' . $shopid . '&go=' . urlencode($url) . ($dn ? '&dn=' . $dn : '');
    }
}

/**
 * 统一处理淘宝返利url
 * @param string $url
 * @param string 跟单来源区分，默认a，a-z字母
 */
function trackUrl($url, $type = 'a') {
	if(isset($_REQUEST['app']) && $_REQUEST['app'] == "budou"){
		$pid = C('BUDOU_PID');
	}else{
		$pid = C('PID');
	}
    //判断链接有没有mm帐号信息，没有就自动加上p=mm_参数
    preg_match("/mm_\d+_\d+_\d+/i", $url, $match_url);
    //如果链接中的mm值和系统返利的值不一样，则替换
    if (!empty($match_url[0]) && $match_url[0] != $pid) {
        $url = preg_replace("/mm_\d+_\d+_\d+/i", $pid, $url);
    }
    //使用goshop链接跳转
    $goshop = C('GOSHOP') ? C('GOSHOP') : 'http://fun.51fanli.com/goshop/go?id=712&go=';
    //如果链接没有返利网跳转链接
    if (false === strpos($url, $goshop)) {
        //如果链接不是http://s.click.taobao.com开头
        if (false === strpos($url, 'http://s.click.taobao.com')) {
            $url = 'http://s.click.taobao.com/t_9?p=' . $pid . '&l=' . urlencode($url) . '&unid=$outcode$';
        }
        //增加uid和时间戳参数
        else {
            $url = str_replace('uiduid', '$outcode$', $url);
            preg_match('/(\$outcode\$|%24outcode%24)/i', $url, $match_uid);

            if (empty($match_uid[0])) {
                $url .= '&u=$outcode$';
            }
        }
        $url = $goshop . urlencode($url);
    } else {
        $url = str_replace('uiduid', '$outcode$', $url);
        preg_match('/(\$outcode\$|%24outcode%24)/i', $url, $match_uid);

        if (empty($match_uid[0])) {
            $url .= '&u=$outcode$';
        }
    }
    if(strpos($url, 'tc=')===false){//添加tc参数
        $url.='&tc='.$type;
    }
    return $url;
}

/**
 * 处理多次被encode的情况
 * @param string $str
 * @return string
 */
function trueUrldecode($str, $times = '') {
    if (empty($times)) {
        while ($str != urldecode($str)) {
            $str = urldecode($str);
        }
    } else {
        for ($i = 0; $i < $times; $i++) {
            $str = urldecode($str);
        }
    }

    return $str;
}

function pr($m) {
    echo '<pre>';
    if (is_scalar($m)) {
        var_dump($m);
    } else {
        print_r($m);
    }
    echo '</pre>';
    return true;
}

function getBrower() {
    $ua = $_SERVER['HTTP_USER_AGENT'];
    if (strpos($ua, "Maxthon") && strpos($ua, "MSIE")) {
        $visitor_browser = "Maxthon(Microsoft IE)";
    } elseif (strpos($ua, "Maxthon 2.0")) {
        $visitor_browser = "Maxthon 2.0";
    } elseif (strpos($ua, "Maxthon")) {
        $visitor_browser = "Maxthon";
    } elseif (strpos($ua, "MSIE 9.0")) {
        $visitor_browser = "MSIE 9.0";
    } elseif (strpos($ua, "MSIE 8.0")) {
        $visitor_browser = "MSIE 8.0";
    } elseif (strpos($ua, "MSIE 7.0")) {
        $visitor_browser = "MSIE 7.0";
    } elseif (strpos($ua, "MSIE 6.0")) {
        $visitor_browser = "MSIE 6.0";
    } elseif (strpos($ua, "MSIE 5.5")) {
        $visitor_browser = "MSIE 5.5";
    } elseif (strpos($ua, "MSIE 5.0")) {
        $visitor_browser = "MSIE 5.0";
    } elseif (strpos($ua, "MSIE 4.01")) {
        $visitor_browser = "MSIE 4.01";
    } elseif (strpos($ua, "MSIE")) {
        $visitor_browser = "MSIE 较高版本";
    } elseif (strpos($ua, "NetCaptor")) {
        $visitor_browser = "NetCaptor";
    } elseif (strpos($ua, "Netscape")) {
        $visitor_browser = "Netscape";
    } elseif (strpos($ua, "Chrome")) {
        $visitor_browser = "Chrome";
    } elseif (strpos($ua, "Lynx")) {
        $visitor_browser = "Lynx";
    } elseif (strpos($ua, "Opera")) {
        $visitor_browser = "Opera";
    } elseif (strpos($ua, "Konqueror")) {
        $visitor_browser = "Konqueror";
    } elseif (strpos($ua, "Mozilla/5.0")) {
        $visitor_browser = "Mozilla";
    } elseif (strpos($ua, "Firefox")) {
        $visitor_browser = "Firefox";
    } elseif (strpos($ua, "U")) {
        $visitor_browser = "Firefox";
    } else {
        $visitor_browser = "其它";
    }
    return $visitor_browser;
}

/**
 * 根据用户名生成两周免登录签名
 * @param type $sUserName
 * @return type
 */
function getLoginVerify($sUserName) {
    return substr(md5($sUserName . C('API_SECRET_KEY')), 8, 16);
}

/**
 * API统一签名函数
 *
 * @param array $params 不包括sn
 * @return string
 */
function api_signature($params, $secret) {
    ksort($params);
    $tmp = array();
    foreach ($params as $key => $val) {
        $tmp[] = $key . $val;
    }
    $tmp = implode('', $tmp);
    return md5($tmp . $secret);
}

/**
 * 组装并签名
 *
 * @param string $url API不包括?和任何参数
 * @param array $params 除固定参数外的所有参数
 * @param array $postParams post提交的参数加入签名，不加如url
 * @return string
 */
function pack_passport_api($url, $params, $postParams = array()) {
	// 1970-01-01 00:00:00 UTC 至今秒数
	// ASP请保持一致，将来会判断时间，超出+/-XXXX秒禁止访问
	$params['t'] = time();
	empty($params['ip']) && $params['ip'] = get_client_ip();
	$params['sn'] = api_signature(array_merge($params, $postParams), C('PASSPORT_SECRET_KEY'));
	return pack_url_params($url, $params);
}

function pack_budou_api($url, $params, $postParams = array()) {
	// 1970-01-01 00:00:00 UTC 至今秒数
	// ASP请保持一致，将来会判断时间，超出+/-XXXX秒禁止访问
	$params['t'] = time();
	empty($params['ip']) && $params['ip'] = get_client_ip();
	$params['sn'] = api_signature(array_merge($params, $postParams), C('BUDOU_SECRET_KEY'));
	return pack_url_params($url, $params);
}

/**
 * 组装url及参数
 *
 * @param string $url
 * @param array $params
 * @param boolean $encodeAmp 是否使用&amp;
 * @return string
 */
function pack_url_params($url, $params, $encodeAmp = false) {
	$glue = $encodeAmp ? '&amp;' : '&';

	if ($url != '' && strrpos($url, '?') === false) {
		$url .= '?';
	}
	elseif ($url != '' && substr($url, -1) != '?' && substr($url, -1) != '&' && substr($url, -4) != '&amp;') {
		$url .= $glue;
	}

	$tmp = array();
	foreach ($params as $key => $one) {
		$tmp[] = rawurlencode($key) . "=" . rawurlencode($one);
	}
	$tmp = implode($glue, $tmp);

	return $url . $tmp;
}

/**
 * Simpel HTTP
 *
 * @param string $url
 * @param array|string $plugins curl插件
 * @return string
 *
 * @example:
 * simple_http($url, array(
 *     'curl_post_data' => $post_data,
 *     'curl_relay_header',
 *     'curl_proxy_ip',
 *     'curl_proxy_cookie',
 * ));
 */
function simple_http($url, $plugins = array()) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	foreach ((array) $plugins as $k => $v) {
		if (is_callable($k)) {
			$k($ch, $v);
		}
		elseif(is_callable($v)) {
			$v($ch);
		}
	}
	$response = curl_exec($ch);
	curl_close($ch);
	return $response;
}

/**
 * simple_http插件：以POST方式提交数据
 *
 * @param curl & $ch
 * @param array & $data
 */
function curl_post_data(& $ch, & $data) {
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
}

/**
 * simple_http插件：中转头信息，传递服务器端headers
 *
 * @param curl & $ch
 */
function curl_relay_header(& $ch) {
	curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) {
		trim($header) && header($header, false); # false to disable replace
		return strlen($header);
	});
}

/**
 * simple_http插件：代理IP，传递客户端的IP到目标服务器
 *
 * @param curl & $ch
 */
function curl_proxy_ip(& $ch) {
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'X-Fl: 51fanli',
		'Client-Ip: ' . get_client_ip(),
	));
}

/**
 * simple_http插件：代理COOKIE，传递客户端的COOKIE到目标服务器
 *
 * @param curl & $ch
 */
function curl_proxy_cookie(& $ch) {
	$tmp = array();
	foreach ($_COOKIE as $k => $v) {
		$tmp[] = rawurlencode($k) . '=' . rawurlencode($v);
	}
	$tmp = implode('; ', $tmp);
	curl_setopt($ch, CURLOPT_COOKIE, $tmp);
}

/**
 * Close connection but continue execute script
 *
 * @param string $msg
 */
function connection_close($msg = '') {
	while (@ob_end_clean());
	ignore_user_abort(true);
	// disable mod_deflate
	function_exists('apache_setenv') && apache_setenv('no-gzip', '1');
	header('Connection: close');
	header('Content-Length: ' . strlen($msg));
	echo $msg;
	flush();
	// fastcgi compact
	function_exists('fastcgi_finish_request') && fastcgi_finish_request();
}

function json_encode_nounicode($mix) {
    return rawurldecode(json_encode(urlEncodeArr($mix)));
}

function urlEncodeArr(&$arr) {
    if (is_scalar($arr)) {
        return rawurlencode($arr);
    }
    $arrTmp = array();
    foreach ($arr as $k => $v) {
        $arrTmp[rawurlencode($k)] = urlEncodeArr($v);
    }
    return $arrTmp;
}

function xmlToArray($sXml, $bIsFile=false) {
    if($bIsFile){
        $oSXml = simplexml_load_file($sXml);
    }else{
        $oSXml=simplexml_load_string($sXml);
    }
    return json_decode(json_encode($oSXml) , true);
}

function Pinyin($_String, $_Code='UTF8'){ //GBK页面可改为gb2312，其他随意填写为UTF8
        $_DataKey = "a|ai|an|ang|ao|ba|bai|ban|bang|bao|bei|ben|beng|bi|bian|biao|bie|bin|bing|bo|bu|ca|cai|can|cang|cao|ce|ceng|cha".
                        "|chai|chan|chang|chao|che|chen|cheng|chi|chong|chou|chu|chuai|chuan|chuang|chui|chun|chuo|ci|cong|cou|cu|".
                        "cuan|cui|cun|cuo|da|dai|dan|dang|dao|de|deng|di|dian|diao|die|ding|diu|dong|dou|du|duan|dui|dun|duo|e|en|er".
                        "|fa|fan|fang|fei|fen|feng|fo|fou|fu|ga|gai|gan|gang|gao|ge|gei|gen|geng|gong|gou|gu|gua|guai|guan|guang|gui".
                        "|gun|guo|ha|hai|han|hang|hao|he|hei|hen|heng|hong|hou|hu|hua|huai|huan|huang|hui|hun|huo|ji|jia|jian|jiang".
                        "|jiao|jie|jin|jing|jiong|jiu|ju|juan|jue|jun|ka|kai|kan|kang|kao|ke|ken|keng|kong|kou|ku|kua|kuai|kuan|kuang".
                        "|kui|kun|kuo|la|lai|lan|lang|lao|le|lei|leng|li|lia|lian|liang|liao|lie|lin|ling|liu|long|lou|lu|lv|luan|lue".
                        "|lun|luo|ma|mai|man|mang|mao|me|mei|men|meng|mi|mian|miao|mie|min|ming|miu|mo|mou|mu|na|nai|nan|nang|nao|ne".
                        "|nei|nen|neng|ni|nian|niang|niao|nie|nin|ning|niu|nong|nu|nv|nuan|nue|nuo|o|ou|pa|pai|pan|pang|pao|pei|pen".
                        "|peng|pi|pian|piao|pie|pin|ping|po|pu|qi|qia|qian|qiang|qiao|qie|qin|qing|qiong|qiu|qu|quan|que|qun|ran|rang".
                        "|rao|re|ren|reng|ri|rong|rou|ru|ruan|rui|run|ruo|sa|sai|san|sang|sao|se|sen|seng|sha|shai|shan|shang|shao|".
                        "she|shen|sheng|shi|shou|shu|shua|shuai|shuan|shuang|shui|shun|shuo|si|song|sou|su|suan|sui|sun|suo|ta|tai|".
                        "tan|tang|tao|te|teng|ti|tian|tiao|tie|ting|tong|tou|tu|tuan|tui|tun|tuo|wa|wai|wan|wang|wei|wen|weng|wo|wu".
                        "|xi|xia|xian|xiang|xiao|xie|xin|xing|xiong|xiu|xu|xuan|xue|xun|ya|yan|yang|yao|ye|yi|yin|ying|yo|yong|you".
                        "|yu|yuan|yue|yun|za|zai|zan|zang|zao|ze|zei|zen|zeng|zha|zhai|zhan|zhang|zhao|zhe|zhen|zheng|zhi|zhong|".
                        "zhou|zhu|zhua|zhuai|zhuan|zhuang|zhui|zhun|zhuo|zi|zong|zou|zu|zuan|zui|zun|zuo";
        $_DataValue = "-20319|-20317|-20304|-20295|-20292|-20283|-20265|-20257|-20242|-20230|-20051|-20036|-20032|-20026|-20002|-19990".
                        "|-19986|-19982|-19976|-19805|-19784|-19775|-19774|-19763|-19756|-19751|-19746|-19741|-19739|-19728|-19725".
                        "|-19715|-19540|-19531|-19525|-19515|-19500|-19484|-19479|-19467|-19289|-19288|-19281|-19275|-19270|-19263".
                        "|-19261|-19249|-19243|-19242|-19238|-19235|-19227|-19224|-19218|-19212|-19038|-19023|-19018|-19006|-19003".
                        "|-18996|-18977|-18961|-18952|-18783|-18774|-18773|-18763|-18756|-18741|-18735|-18731|-18722|-18710|-18697".
                        "|-18696|-18526|-18518|-18501|-18490|-18478|-18463|-18448|-18447|-18446|-18239|-18237|-18231|-18220|-18211".
                        "|-18201|-18184|-18183|-18181|-18012|-17997|-17988|-17970|-17964|-17961|-17950|-17947|-17931|-17928|-17922".
                        "|-17759|-17752|-17733|-17730|-17721|-17703|-17701|-17697|-17692|-17683|-17676|-17496|-17487|-17482|-17468".
                        "|-17454|-17433|-17427|-17417|-17202|-17185|-16983|-16970|-16942|-16915|-16733|-16708|-16706|-16689|-16664".
                        "|-16657|-16647|-16474|-16470|-16465|-16459|-16452|-16448|-16433|-16429|-16427|-16423|-16419|-16412|-16407".
                        "|-16403|-16401|-16393|-16220|-16216|-16212|-16205|-16202|-16187|-16180|-16171|-16169|-16158|-16155|-15959".
                        "|-15958|-15944|-15933|-15920|-15915|-15903|-15889|-15878|-15707|-15701|-15681|-15667|-15661|-15659|-15652".
                        "|-15640|-15631|-15625|-15454|-15448|-15436|-15435|-15419|-15416|-15408|-15394|-15385|-15377|-15375|-15369".
                        "|-15363|-15362|-15183|-15180|-15165|-15158|-15153|-15150|-15149|-15144|-15143|-15141|-15140|-15139|-15128".
                        "|-15121|-15119|-15117|-15110|-15109|-14941|-14937|-14933|-14930|-14929|-14928|-14926|-14922|-14921|-14914".
                        "|-14908|-14902|-14894|-14889|-14882|-14873|-14871|-14857|-14678|-14674|-14670|-14668|-14663|-14654|-14645".
                        "|-14630|-14594|-14429|-14407|-14399|-14384|-14379|-14368|-14355|-14353|-14345|-14170|-14159|-14151|-14149".
                        "|-14145|-14140|-14137|-14135|-14125|-14123|-14122|-14112|-14109|-14099|-14097|-14094|-14092|-14090|-14087".
                        "|-14083|-13917|-13914|-13910|-13907|-13906|-13905|-13896|-13894|-13878|-13870|-13859|-13847|-13831|-13658".
                        "|-13611|-13601|-13406|-13404|-13400|-13398|-13395|-13391|-13387|-13383|-13367|-13359|-13356|-13343|-13340".
                        "|-13329|-13326|-13318|-13147|-13138|-13120|-13107|-13096|-13095|-13091|-13076|-13068|-13063|-13060|-12888".
                        "|-12875|-12871|-12860|-12858|-12852|-12849|-12838|-12831|-12829|-12812|-12802|-12607|-12597|-12594|-12585".
                        "|-12556|-12359|-12346|-12320|-12300|-12120|-12099|-12089|-12074|-12067|-12058|-12039|-11867|-11861|-11847".
                        "|-11831|-11798|-11781|-11604|-11589|-11536|-11358|-11340|-11339|-11324|-11303|-11097|-11077|-11067|-11055".
                        "|-11052|-11045|-11041|-11038|-11024|-11020|-11019|-11018|-11014|-10838|-10832|-10815|-10800|-10790|-10780".
                        "|-10764|-10587|-10544|-10533|-10519|-10331|-10329|-10328|-10322|-10315|-10309|-10307|-10296|-10281|-10274".
                        "|-10270|-10262|-10260|-10256|-10254";
        $_TDataKey   = explode('|', $_DataKey);
        $_TDataValue = explode('|', $_DataValue);
        $_Data = array_combine($_TDataKey, $_TDataValue);
        arsort($_Data);
        reset($_Data);
        if($_Code!= 'gb2312') $_String = _U2_Utf8_Gb($_String);
        $_Res = '';
        for($i=0; $i<strlen($_String); $i++) {
                $_P = ord(substr($_String, $i, 1));
                if($_P>160) {
                        $_Q = ord(substr($_String, ++$i, 1)); $_P = $_P*256 + $_Q - 65536;
                }
                $_Res .= _Pinyin($_P, $_Data);
        }
        return preg_replace("/[^a-z0-9]*/", '', $_Res);
}

function _Pinyin($_Num, $_Data){
        if($_Num>0 && $_Num<160 ){
                return chr($_Num);
        }elseif($_Num<-20319 || $_Num>-10247){
                return '';
        }else{
                foreach($_Data as $k=>$v){ if($v<=$_Num) break; }
                return $k;
        }
}
function _U2_Utf8_Gb($_C){
        $_String = '';
        if($_C < 0x80){
                $_String .= $_C;
        }elseif($_C < 0x800) {
                $_String .= chr(0xC0 | $_C>>6);
                $_String .= chr(0x80 | $_C & 0x3F);
        }elseif($_C < 0x10000){
                $_String .= chr(0xE0 | $_C>>12);
                $_String .= chr(0x80 | $_C>>6 & 0x3F);
                $_String .= chr(0x80 | $_C & 0x3F);
        }elseif($_C < 0x200000) {
                $_String .= chr(0xF0 | $_C>>18);
                $_String .= chr(0x80 | $_C>>12 & 0x3F);
                $_String .= chr(0x80 | $_C>>6 & 0x3F);
                $_String .= chr(0x80 | $_C & 0x3F);
        }
        return iconv('UTF-8', 'GB2312', $_String);
}

/**
 * 取B段IP或者C段IP
 *
 * @param
 */
function getIpByLevel($ip, $level='normal') {
	$level = strtolower($level);
	$ips = explode('.', $ip);
	switch ($level) {
		case 'b':
			$ip = $ips[0] . '.' . $ips[1] . '.*';
			break;
		case 'c':
			$ip = $ips[0] . '.' . $ips[1] . '.' . $ips[2] . '.*';
			break;
		default :
			break;
	}
	return $ip;
}

function debuglog($str){
    if( !C('DEBUGLOG') ) return;

    $rd = D('Redis');
    $rdKey = 'event:debug:actlist';
    if(is_array($str)){
        $str = json_encode($str);
    }
    $rd->rpush($rdKey,$str);
}

function allowDebuglogByLevel($levels){
    return in_array(C('DEBUGLOG'), $levels);
}