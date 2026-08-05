<?php
/**
 * @file login.php
 * @description 管理员登录页面，处理登录认证、CSRF校验、防爆破锁定及登录会话管理
 * @author AI
 * @version 1.1.0-dev
 * @date 2026-08-04
 */
declare(strict_types=1);

ini_set("display_errors", 0);
require('../inc/lang.php');
require('../inc/common.php');

$toast_type = ''; $toast_msg = '';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

if(isset($_POST['user']) && isset($_POST['pass'])){
    // CSRF 校验：防止登录 CSRF 攻击
    if(!csrf_verify()) {
        $toast_type = 'error';
        $toast_msg = '安全校验失败，请刷新页面后重试！';
    }
    $user = trim((string)$_POST['user']);
    $pass = (string)$_POST['pass'];
    if($toast_type === '' && ($user === '' || $pass === '')) {
        $toast_type = 'warning';
        $toast_msg = '请填写完整的用户名和密码！';
    }
    // 防爆破：基于服务端文件的失败计数（按 IP），清 Cookie/换会话无法绕过
    $f2bKey = 'admin_login_' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
    $lockRemain = f2b_locked_seconds($f2bKey);
    if($toast_msg) {
        // 字段不完整，跳过登录逻辑
    } elseif($lockRemain > 0){
        $wait = ceil($lockRemain / 60);
        $toast_type = 'error';
        $toast_msg = "登录尝试过于频繁，请 {$wait} 分钟后再试！";
    } else {
        $user = daddslashes($_POST['user']);
        $pass = (string)$_POST['pass'];
        $stored = isset($conf['admin_pwd']) ? (string)$conf['admin_pwd'] : '';
        $isHashed = (strpos($stored, '$2y$') === 0 || strpos($stored, '$argon2') === 0);
        $passOk = $isHashed ? password_verify($pass, $stored) : hash_equals($stored, $pass);
        if($user === $conf['admin_user'] && $passOk) {
            f2b_reset($f2bKey);
            // 会话固定攻击防护：重新生成 session ID
            session_regenerate_id(true);
            // 如果密码是明文存储，登录成功后自动迁移为 bcrypt hash
            if(!$isHashed) {
                $newHash = password_hash($pass, PASSWORD_DEFAULT);
                $newHashEsc = daddslashes($newHash);
                $DB->query("update `eecms_config` set `main`='{$newHashEsc}' where `name`='admin_pwd'");
                $stored = $newHash;
            }
            $session = md5($conf['admin_user'].$stored.$password_hash);
            $token = authcode("{$user}\t{$session}", 'ENCODE', SYS_KEY);
            setcookie("admin_token", $token, time() + 604800, '/', '', $isHttps, true);
            @header('Location: index.php');
            exit;
        } else {
            f2b_hit($f2bKey, 5, 600, 600);
            $toast_type = 'error';
            $toast_msg = '用户名或密码不正确！';
        }
    }
}
elseif(isset($_GET['logout'])){
    setcookie("admin_token", "", time() - 604800, '/', '', $isHttps, true);
    @header('Location: login.php');
    exit;
}
elseif(isset($islogin) && $islogin == 1){
    @header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<title><?php echo $lang->admin->login;?> - <?php echo $lang->admin->title;?></title>
<link rel="shortcut icon" href="../favicon.ico">
<link rel="stylesheet" href="style/css/materialdesignicons.min.css">
<link rel="stylesheet" href="style/css/bootstrap.min.css">
<link rel="stylesheet" href="style/css/sweetalert2.min.css">
<link rel="stylesheet" href="style/css/admin.css">
<style>
:root {
  --login-text: #1e293b;
  --login-text-muted: #64748b;
}
/* 覆盖 admin.css 的 body 样式，登录页允许滚动 */
body { overflow: auto; background: #0f172a; }
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #312e81 100%);
  position: relative;
  overflow: hidden;
}
.login-page::before {
  content: '';
  position: absolute;
  width: 600px; height: 600px;
  background: radial-gradient(circle, rgba(59,130,246,.18) 0%, transparent 70%);
  top: -200px; right: -100px;
  border-radius: 50%;
}
.login-page::after {
  content: '';
  position: absolute;
  width: 450px; height: 450px;
  background: radial-gradient(circle, rgba(139,92,246,.14) 0%, transparent 70%);
  bottom: -120px; left: -80px;
  border-radius: 50%;
}
.login-card {
  position: relative; z-index: 1;
  width: 400px; max-width: 92%;
  background: rgba(255,255,255,.96);
  backdrop-filter: blur(10px);
  border-radius: 20px;
  padding: 40px 36px;
  box-shadow: 0 25px 60px rgba(0,0,0,.35);
}
.login-logo { text-align: center; margin-bottom: 24px; }
.login-logo img { height: 38px; }
.login-title {
  text-align: center; font-size: 20px; font-weight: 700;
  color: var(--login-text); margin-bottom: 6px;
}
.login-subtitle {
  text-align: center; font-size: 13px; color: var(--login-text-muted);
  margin-bottom: 28px;
}
.login-input-wrap {
  position: relative; margin-bottom: 18px;
}
.login-input-wrap .mdi {
  position: absolute; left: 14px; top: 50%;
  transform: translateY(-50%);
  font-size: 20px; color: #94a3b8;
  z-index: 4; pointer-events: none;
}
.login-input-wrap .form-control {
  padding-left: 44px; height: 48px; font-size: 14px;
  border: 1.5px solid #e2e8f0; border-radius: 10px;
  transition: all .25s;
}
.login-input-wrap .form-control:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59,130,246,.12);
}
.btn-login {
  width: 100%; height: 48px; font-size: 15px; font-weight: 600;
  border-radius: 10px;
  background: linear-gradient(135deg, #3b82f6, #6366f1);
  border: none; color: #fff;
  transition: all .25s;
  margin-top: 6px;
}
.btn-login:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(59,130,246,.45);
}
.login-footer {
  text-align: center; font-size: 12px; color: #94a3b8; margin-top: 22px;
}
</style>
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <a href="index.php"><img alt="logo" src="style/images/logo-sidebar.png"></a>
    </div>
    <div class="login-title">欢迎回来</div>
    <div class="login-subtitle">请使用您的管理员账号登录系统</div>

    <form method="post" id="loginForm">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token();?>">
      <div class="login-input-wrap">
        <i class="mdi mdi-account-outline"></i>
        <input type="text" class="form-control" id="user" name="user" placeholder="请输入用户名" required autofocus>
      </div>
      <div class="login-input-wrap">
        <i class="mdi mdi-lock-outline"></i>
        <input type="password" class="form-control" id="pass" name="pass" placeholder="请输入密码" required>
      </div>
      <button class="btn-login" type="submit">
        <i class="mdi mdi-login-variant me-1"></i> 登录系统
      </button>
    </form>
    <div class="login-footer"><?php echo $lang->admin->footer;?></div>
  </div>
</div>

<script src="style/js/sweetalert2.min.js"></script>
<script>
function toast(type, msg, timer){
    if(type === 'success'){
        Swal.fire({title:msg, icon:'success', timer:1200, showConfirmButton:false});
    } else if(type === 'error'){
        Swal.fire('错误', msg, 'error');
    } else if(type === 'warning'){
        Swal.fire('提示', msg, 'warning');
    } else {
        Swal.fire('提示', msg, 'info');
    }
}
document.getElementById('loginForm').addEventListener('submit', function(e) {
    var u = document.getElementById('user').value.trim();
    var p = document.getElementById('pass').value.trim();
    if (!u || !p) {
        e.preventDefault();
        toast('warning', '请填写完整的用户名和密码！', 3000);
    }
});
</script>
<?php if($toast_msg): ?>
<script>
toast(<?php echo json_encode($toast_type);?>, <?php echo json_encode($toast_msg, JSON_UNESCAPED_UNICODE);?>, 3000);
</script>
<?php endif; ?>
</body>
</html>