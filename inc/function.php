<?php
/**
 * @file function.php
 * @description 核心函数库：加密密钥回退、curl SSL 证书校验等通用工具函数
 * @author AI
 * @version 1.1.0-dev
 * @date 2026-08-04
 */
declare(strict_types=1);

// 加密密钥：正常流程由 common.php 从数据库读取并定义，此处仅为安全回退（生成随机密钥而非硬编码值）
if(!defined('ENCRYPT_KEY')) {
    try { define('ENCRYPT_KEY', bin2hex(random_bytes(24))); }
    catch (Throwable $e) { define('ENCRYPT_KEY', md5(uniqid((string)mt_rand(), true) . microtime(true))); }
}

// 统一配置 curl 的 SSL 证书校验：使用项目内置 Mozilla CA 包（inc/cacert.pem）
// 防止出站请求被中间人攻击；CA 包缺失时回退不校验（避免硬故障）
function curl_setup_ssl($ch) {
	$ca = defined('SYSTEM_ROOT') ? SYSTEM_ROOT.'cacert.pem' : dirname(__FILE__).'/cacert.pem';
	if(is_file($ca)) {
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_CAINFO, $ca);
	} else {
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	}
}

function get_curl($url,$post=0,$referer=1,$cookie=0,$header=0,$ua=0,$nobaody=0){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setup_ssl($ch);
	$httpheader[] = "Accept:*/*";
	$httpheader[] = "Accept-Encoding:gzip,deflate,sdch";
	$httpheader[] = "Accept-Language:zh-CN,zh;q=0.8";
	$httpheader[] = "Connection:close";
	curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	if($post){
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	}
	if($header){
		curl_setopt($ch, CURLOPT_HEADER, TRUE);
	}
	if($cookie){
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
	}
	if($referer){
		if($referer==1){
			curl_setopt($ch, CURLOPT_REFERER, 'http://m.qzone.com/infocenter?g_f=');
		}else{
			curl_setopt($ch, CURLOPT_REFERER, $referer);
		}
	}
	if($ua){
		curl_setopt($ch, CURLOPT_USERAGENT,$ua);
	}else{
		curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
	}
	if($nobaody){
		curl_setopt($ch, CURLOPT_NOBODY,1);
	}
	curl_setopt($ch, CURLOPT_ENCODING, "gzip");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	$ret = curl_exec($ch);
	curl_close($ch);
	return $ret;
}
function real_ip(){
	global $conf;
	$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	// 默认只信任 REMOTE_ADDR，防止 XFF/Client-IP 伪造。
	// 仅当后台显式开启「信任反向代理」(trust_proxy=1) 时才采信代理头。
	$trustProxy = isset($conf['trust_proxy']) && $conf['trust_proxy'] == '1';
	if(!$trustProxy){
		return $ip;
	}
	if (isset($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
		$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
	} elseif (isset($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) {
		$ip = $_SERVER['HTTP_X_REAL_IP'];
	} elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
		$candidate = trim($parts[0]);
		if(filter_var($candidate, FILTER_VALIDATE_IP)){
			$ip = $candidate;
		}
	}
	return $ip;
}
function get_ip_city($ip)
{
    $url = 'https://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip=';
    @$city = get_curl($url . $ip);
    $city = json_decode($city, true);
    if (!is_array($city)) {
        return false;
    }
    if (isset($city['city']) && $city['city']) {
        $location = ($city['province'] ?? '').($city['city'] ?? '');
    } else {
        $location = $city['province'] ?? '';
    }
	if($location){
		return $location;
	}else{
		return false;
	}
}
// 注：原 send_mail() 为不可达死代码（引用不存在的 includes/smtp.class.php），已删除。
// 邮件发送统一使用 inc/smtp_mailer.php（user_auth.php 中的 send_verification_email）。
function daddslashes($string, $force = 1, $strip = FALSE) {
	// PHP 8.0 移除了 get_magic_quotes_gpc()，魔术引号在 PHP 5.4+ 已移除
	// 所以默认 $force=1，总是做转义处理
	if($force) {
		if(is_array($string)) {
			foreach($string as $key => $val) {
				$string[$key] = daddslashes($val, $force, $strip);
			}
		} else {
			$string = addslashes($strip ? stripslashes($string) : $string);
		}
	}
	return $string;
}

function getSubstr($str, $leftStr, $rightStr)
{
	$left = strpos($str, $leftStr);
	//echo '左边:'.$left;
	$right = strpos($str, $rightStr,$left);
	//echo '<br>右边:'.$right;
	if($left < 0 or $right < $left) return '';
	return substr($str, $left + strlen($leftStr), $right-$left-strlen($leftStr));
}

function strexists($string, $find) {
	return !(strpos($string, $find) === FALSE);
}
function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
	$ckey_length = 4;
	$key = md5($key ? $key : ENCRYPT_KEY);
	$keya = md5(substr($key, 0, 16));
	$keyb = md5(substr($key, 16, 16));
	$keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';
	$cryptkey = $keya.md5($keya.$keyc);
	$key_length = strlen($cryptkey);
	$string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
	$string_length = strlen($string);
	$result = '';
	$box = range(0, 255);
	$rndkey = array();
	for($i = 0; $i <= 255; $i++) {
		$rndkey[$i] = ord($cryptkey[$i % $key_length]);
	}
	for($j = $i = 0; $i < 256; $i++) {
		$j = ($j + $box[$i] + $rndkey[$i]) % 256;
		$tmp = $box[$i];
		$box[$i] = $box[$j];
		$box[$j] = $tmp;
	}
	for($a = $j = $i = 0; $i < $string_length; $i++) {
		$a = ($a + 1) % 256;
		$j = ($j + $box[$a]) % 256;
		$tmp = $box[$a];
		$box[$a] = $box[$j];
		$box[$j] = $tmp;
		$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
	}
	if($operation == 'DECODE') {
		// PHP8 兼容：解密失败时 $result 为乱码，substr 取值可能非数字，
		// 显式转 (int) 避免「string - int」TypeError；数据不合法时返回空串。
		$__ts = (int)substr($result, 0, 10);
		if(($__ts == 0 || $__ts - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
			return substr($result, 26);
		} else {
			return '';
		}
	} else {
		return $keyc.str_replace('=', '', base64_encode($result));
	}
}

function random($length, $numeric = 0) {
	$seed = base_convert(md5(microtime().$_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
	$seed = $numeric ? (str_replace('0', '', $seed).'012340567890') : ($seed.'zZ'.strtoupper($seed));
	$hash = '';
	$max = strlen($seed) - 1;
	for($i = 0; $i < $length; $i++) {
		$hash .= $seed[mt_rand(0, $max)];
	}
	return $hash;
}

/**
 * 生成随机文件名（小写字母+数字）
 * @param int $length 文件名长度（不含扩展名），默认12
 * @return string 随机文件名，如 a3b9xk2mnp7q
 */
function random_name($length = 12) {
	$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
	$str = '';
	$max = strlen($chars) - 1;
	for($i = 0; $i < $length; $i++) {
		$str .= $chars[mt_rand(0, $max)];
	}
	return $str;
}
function showmsg($content = '未知的异常',$type = 4,$back = false)
{
switch($type)
{
case 1:
	$panel="success";
break;
case 2:
	$panel="info";
break;
case 3:
	$panel="warning";
break;
case 4:
	$panel="danger";
break;
}

echo '<div class="panel panel-'.$panel.'">
      <div class="panel-heading">
        <h3 class="panel-title">提示信息</h3>
        </div>
        <div class="panel-body">';
echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

if ($back) {
	echo '<hr/><a href="'.htmlspecialchars($back, ENT_QUOTES, 'UTF-8').'"><< 返回上一页</a>';
}
else
    echo '<hr/><a href="javascript:history.back(-1)"><< 返回上一页</a>';

echo '</div>
    </div>';
}
function sysmsg($msg = '未知的异常',$die = true) {
    ?>  
    <!DOCTYPE html>
    <html xmlns="http://www.w3.org/1999/xhtml" lang="zh-CN">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>站点提示信息</title>
        <style type="text/css">
html{background:#eee}body{background:#fff;color:#333;font-family:"微软雅黑","Microsoft YaHei",sans-serif;margin:2em auto;padding:1em 2em;max-width:700px;-webkit-box-shadow:10px 10px 10px rgba(0,0,0,.13);box-shadow:10px 10px 10px rgba(0,0,0,.13);opacity:.8}h1{border-bottom:1px solid #dadada;clear:both;color:#666;font:24px "微软雅黑","Microsoft YaHei",,sans-serif;margin:30px 0 0 0;padding:0;padding-bottom:7px}#error-page{margin-top:50px}h3{text-align:center}#error-page p{font-size:9px;line-height:1.5;margin:25px 0 20px}#error-page code{font-family:Consolas,Monaco,monospace}ul li{margin-bottom:10px;font-size:9px}a{color:#21759B;text-decoration:none;margin-top:-10px}a:hover{color:#D54E21}.button{background:#f7f7f7;border:1px solid #ccc;color:#555;display:inline-block;text-decoration:none;font-size:9px;line-height:26px;height:28px;margin:0;padding:0 10px 1px;cursor:pointer;-webkit-border-radius:3px;-webkit-appearance:none;border-radius:3px;white-space:nowrap;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;-webkit-box-shadow:inset 0 1px 0 #fff,0 1px 0 rgba(0,0,0,.08);box-shadow:inset 0 1px 0 #fff,0 1px 0 rgba(0,0,0,.08);vertical-align:top}.button.button-large{height:29px;line-height:28px;padding:0 12px}.button:focus,.button:hover{background:#fafafa;border-color:#999;color:#222}.button:focus{-webkit-box-shadow:1px 1px 1px rgba(0,0,0,.2);box-shadow:1px 1px 1px rgba(0,0,0,.2)}.button:active{background:#eee;border-color:#999;color:#333;-webkit-box-shadow:inset 0 2px 5px -3px rgba(0,0,0,.5);box-shadow:inset 0 2px 5px -3px rgba(0,0,0,.5)}table{table-layout:auto;border:1px solid #333;empty-cells:show;border-collapse:collapse}th{padding:4px;border:1px solid #333;overflow:hidden;color:#333;background:#eee}td{padding:4px;border:1px solid #333;overflow:hidden;color:#333}
        </style>
    </head>
    <body id="error-page">
        <?php echo '<h3>站点提示信息</h3>';
        echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
    </body>
    </html>
    <?php
    if ($die == true) {
        exit;
    }
}
function checkIfActive($string) {
	$array=explode(',',$string);
	$php_self=substr($_SERVER['REQUEST_URI'],strrpos($_SERVER['REQUEST_URI'],'/')+1);
	$php_self=str_replace('.html','',$php_self);
	if (in_array($php_self,$array)){
		return 'current';
	}else
		return null;
}

// ========== CSRF 防护 ==========
// 获取（不存在则生成）当前会话的 CSRF 令牌
function csrf_token() {
	if(session_status() !== PHP_SESSION_ACTIVE) {
		@session_start();
	}
	if(empty($_SESSION['csrf_token'])) {
		try {
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		} catch (Throwable $e) {
			$_SESSION['csrf_token'] = md5(uniqid((string)mt_rand(), true));
		}
	}
	return $_SESSION['csrf_token'];
}

// 校验请求携带的 CSRF 令牌（POST 字段 csrf_token 或请求头 X-CSRF-Token）
function csrf_verify() {
	if(session_status() !== PHP_SESSION_ACTIVE) {
		@session_start();
	}
	$sent = '';
	if(isset($_POST['csrf_token'])) {
		$sent = (string)$_POST['csrf_token'];
	} elseif(isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
		$sent = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
	}
	$stored = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
	return $stored !== '' && $sent !== '' && hash_equals($stored, $sent);
}

// ========== 服务端失败计数（防爆破，文件存储，清 Cookie/换会话无法绕过）==========
function f2b_dir() {
	$dir = sys_get_temp_dir() . '/eecms_f2b';
	if(!is_dir($dir)) @mkdir($dir, 0700, true);
	return $dir;
}
function f2b_file($key) {
	return f2b_dir() . '/' . md5($key) . '.json';
}
function f2b_read($key) {
	$f = f2b_file($key);
	if(!is_file($f)) return ['count'=>0,'lock'=>0,'first'=>0];
	$d = json_decode((string)@file_get_contents($f), true);
	return is_array($d) ? array_merge(['count'=>0,'lock'=>0,'first'=>0], $d) : ['count'=>0,'lock'=>0,'first'=>0];
}
function f2b_write($key, $data) {
	@file_put_contents(f2b_file($key), json_encode($data), LOCK_EX);
}
/** 检查 key 是否处于锁定期，返回剩余秒数（0=未锁定） */
function f2b_locked_seconds($key) {
	$d = f2b_read($key);
	return $d['lock'] > time() ? ($d['lock'] - time()) : 0;
}
/**
 * 记录一次失败：$window 秒内累计 $max 次即锁定 $lockSeconds 秒
 * @return bool 本次是否触发锁定
 */
function f2b_hit($key, $max=5, $window=600, $lockSeconds=600) {
	$d = f2b_read($key);
	$now = time();
	if($d['first'] === 0 || ($now - $d['first']) > $window) {
		$d = ['count'=>0,'lock'=>0,'first'=>$now];
	}
	$d['count']++;
	$locked = false;
	if($d['count'] >= $max) {
		$d['lock'] = $now + $lockSeconds;
		$d['count'] = 0;
		$locked = true;
	}
	f2b_write($key, $d);
	return $locked;
}
/** 验证成功后清零计数 */
function f2b_reset($key) {
	@unlink(f2b_file($key));
}

/**
 * 删除本地存储的图片文件（仅匹配 api/upload/ 下的安全文件名，防路径穿越）
 * 用于删除图片记录时联动清理磁盘文件，避免孤儿文件堆积
 * @param string $url 图片完整 URL
 * @return bool 是否删除了文件
 */
function try_delete_local_image($url) {
	$path = (string)parse_url($url, PHP_URL_PATH);
	if($path === '' || strpos($path, '/api/upload/') === false) return false;
	$base = basename($path);
	// 文件名白名单：十六进制/随机字符 + 图片后缀（与 local.php 命名规则一致）
	if(!preg_match('/^[a-zA-Z0-9]+\.(gif|jpg|jpeg|png|webp|bmp)$/', $base)) return false;
	$fs = rtrim(ROOT, '/') . '/api/upload/' . $base;
	if(is_file($fs)) return @unlink($fs);
	return false;
}

// ========== 敏感数据加密/解密 ==========
// 使用 AES-256-CBC 加密，密钥从 ENCRYPT_KEY 派生
// 用于加密存储 SMTP 密码、S3 Secret Key 等敏感配置

/**
 * 加密敏感数据（enc2 格式：AES-256-CBC + HMAC-SHA256 完整性校验）
 * @param string $plaintext 明文
 * @return string 加密后的 base64 字符串（带 enc2: 前缀标识）
 */
function ct_encrypt($plaintext) {
	if($plaintext === '' || $plaintext === null) return '';
	// 已经加密的不重复加密
	if(strpos($plaintext, 'enc2:') === 0 || strpos($plaintext, 'enc:') === 0) return $plaintext;
	$key = hash('sha256', ENCRYPT_KEY, true);
	$macKey = hash('sha256', 'mac:' . ENCRYPT_KEY, true);
	$iv = openssl_random_pseudo_bytes(16);
	$encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
	if($encrypted === false) return $plaintext; // 加密失败时回退为明文
	$mac = hash_hmac('sha256', $iv . $encrypted, $macKey, true);
	return 'enc2:' . base64_encode($iv . $mac . $encrypted);
}

/**
 * 解密敏感数据（兼容 enc2 新格式与 enc 旧格式）
 * enc2 格式校验 HMAC，篡改会导致解密拒绝；enc 旧格式仅做解密（无完整性）
 * @param string $ciphertext 加密后的字符串
 * @return string 解密后的明文
 */
function ct_decrypt($ciphertext) {
	if($ciphertext === '' || $ciphertext === null) return '';
	$key = hash('sha256', ENCRYPT_KEY, true);

	// enc2 新格式：IV(16) + HMAC(32) + 密文
	if(strpos($ciphertext, 'enc2:') === 0) {
		$data = base64_decode(substr($ciphertext, 5), true);
		if($data === false || strlen($data) < 48) return $ciphertext;
		$macKey = hash('sha256', 'mac:' . ENCRYPT_KEY, true);
		$iv = substr($data, 0, 16);
		$mac = substr($data, 16, 32);
		$encrypted = substr($data, 48);
		$expected = hash_hmac('sha256', $iv . $encrypted, $macKey, true);
		if(!hash_equals($expected, $mac)) return $ciphertext; // 完整性校验失败，拒绝解密
		$decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
		if($decrypted === false) return $ciphertext;
		return $decrypted;
	}

	// enc 旧格式（向后兼容）：IV(16) + 密文
	if(strpos($ciphertext, 'enc:') !== 0) return $ciphertext; // 非加密数据，直接返回
	$data = base64_decode(substr($ciphertext, 4));
	if($data === false || strlen($data) < 16) return $ciphertext;
	$iv = substr($data, 0, 16);
	$encrypted = substr($data, 16);
	$decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
	if($decrypted === false) return $ciphertext; // 解密失败时返回原值
	return $decrypted;
}
?>