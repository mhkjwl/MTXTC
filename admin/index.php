<?php
/**
 * @file index.php
 * @description 后台主框架页面，提供侧边栏导航菜单与iframe标签页式内容管理，作为后台各功能页面的容器
 * @author AI
 * @version 1.1.0-dev
 * @date 2026-08-04
 */
declare(strict_types=1);

ini_set("display_errors", 0);
require('../inc/lang.php');
require('../inc/common.php');
if(!isset($islogin) || $islogin!=1) exit("<script>location.href='login.php';</script>");
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
<title>后台首页 - <?php echo $lang->admin->title;?></title>
<link rel="shortcut icon" type="image/x-icon" href="../favicon.ico">
<!-- 预加载大字体 -->
<link rel="preload" href="style/fonts/materialdesignicons-webfont.woff2?v=6.5.95" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="style/css/materialdesignicons.min.css">
<link rel="stylesheet" href="style/css/bootstrap.min.css">
<link rel="stylesheet" href="style/css/animate.min.css">
<link rel="stylesheet" href="style/css/admin.css?v=20260802">
</head>
<body>
<div class="admin-layout" id="adminLayout">

  <!-- 侧边栏遮罩 -->
  <div class="admin-sidebar-overlay" id="sidebarOverlay"></div>

  <!-- 左侧导航 -->
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-sidebar-brand">
      <a href="index.php">
        <img src="style/images/logo-sidebar.png" alt="后台管理" />
        <span class="brand-text">后台管理</span>
      </a>
    </div>
    <nav class="admin-sidebar-nav">
      <div class="nav-section">主菜单</div>
      <ul>
        <li class="nav-item active">
          <a class="ct-open-tab" href="dashboard.php" id="default-page">
            <i class="mdi mdi-view-dashboard-outline"></i><span>后台首页</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="ct-open-tab" href="setting.php">
            <i class="mdi mdi-cog-outline"></i><span>网站设置</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="ct-open-tab" href="user.php">
            <i class="mdi mdi-account-outline"></i><span>修改信息</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="ct-open-tab" href="upimg.php">
            <i class="mdi mdi-image-outline"></i><span>LOGO修改</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="ct-open-tab" href="api.php">
            <i class="mdi mdi-power-plug-outline"></i><span>API接口设置</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="ct-open-tab" href="s3.php">
            <i class="mdi mdi-cloud-outline"></i><span>S3存储设置</span>
          </a>
        </li>
      </ul>
      <div class="nav-section">用户与图片</div>
      <ul>
        <li class="nav-item">
          <a class="ct-open-tab" href="users.php">
            <i class="mdi mdi-account-group-outline"></i><span>用户管理</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="ct-open-tab" href="images.php">
            <i class="mdi mdi-image-multiple-outline"></i><span>图片管理</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="ct-open-tab" href="apikeys.php">
            <i class="mdi mdi-key-variant"></i><span>API密钥管理</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="ct-open-tab" href="regconfig.php">
            <i class="mdi mdi-account-plus-outline"></i><span>注册设置</span>
          </a>
        </li>
      </ul>
      <div class="nav-section">套餐与权限</div>
      <ul>
        <li class="nav-item">
          <a class="ct-open-tab" href="packages.php">
            <i class="mdi mdi-package-variant-closed"></i><span>套餐管理</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="ct-open-tab" href="apigroups.php">
            <i class="mdi mdi-group"></i><span>接口分组</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="ct-open-tab" href="redeem.php">
            <i class="mdi mdi-ticket-outline"></i><span>兑换码管理</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="ct-open-tab" href="access.php">
            <i class="mdi mdi-shield-lock-outline"></i><span>访问控制</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="ct-open-tab" href="changelog.php">
            <i class="mdi mdi-history"></i><span>更新日志</span>
          </a>
        </li>
      </ul>
    </nav>
    <div class="admin-sidebar-footer">
      <p class="copyright"><?php echo $lang->admin->footer;?></p>
    </div>
  </aside>

  <!-- 主内容区 -->
  <main class="admin-main">
    <!-- 头部 -->
    <header class="admin-header">
      <div class="admin-header-left">
        <button class="admin-header-toggler" id="sidebarToggler" title="切换侧边栏">
          <span></span>
        </button>
        <span style="font-size:14px;font-weight:500;color:#64748b;">EECMS</span>
      </div>
      <div class="admin-header-right">
        <a href="/" target="_blank" class="btn-icon" title="访问前台">
          <i class="mdi mdi-home-outline"></i>
        </a>
        <div class="dropdown">
          <a href="javascript:void(0)" data-bs-toggle="dropdown" class="admin-user-dropdown">
            <img class="avatar" src="style/images/avatar.jpg" alt="头像" />
            <span class="user-name"><?php echo htmlspecialchars($conf['admin_user'], ENT_QUOTES, 'UTF-8');?></span>
            <i class="mdi mdi-chevron-down" style="font-size:16px;color:#94a3b8;"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item ct-open-tab" data-url="user.php" href="javascript:void(0)"><i class="mdi mdi-account-outline"></i>修改信息</a></li>
            <li class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="javascript:void(0)" id="adminLogoutBtn"><i class="mdi mdi-logout-variant"></i>退出登录</a></li>
          </ul>
        </div>
      </div>
    </header>

    <!-- 页面内容：自定义标签页 + iframe -->
    <div class="admin-content admin-frame-content">
      <!-- 标签栏 -->
      <div class="ct-tab-bar">
        <div class="ct-tab-nav" id="ctTabNav">
          <ul class="ct-tab-list" id="ctTabList"></ul>
        </div>
        <div class="ct-tab-tools">
          <button type="button" class="ct-tab-tool-btn" id="ctRefreshBtn" title="刷新当前页">
            <i class="mdi mdi-refresh"></i>
          </button>
          <button type="button" class="ct-tab-tool-btn" id="ctCloseAllBtn" title="关闭其他标签页">
            <i class="mdi mdi-close-box-multiple-outline"></i>
          </button>
        </div>
      </div>
      <!-- iframe 内容区 -->
      <div class="ct-tab-content" id="ctContent"></div>
    </div>
  </main>

</div>

<!-- JS -->
<script src="style/js/jquery.min.js"></script>
<script src="style/js/popper.min.js"></script>
<script src="style/js/bootstrap.min.js"></script>
<script src="style/js/sweetalert2.min.js"></script>
<script src="style/js/perfect-scrollbar.min.js"></script>
<script src="style/js/jquery.cookie.min.js"></script>
<script src="style/js/index.min.js?v=20260802d"></script>
<script>
// 登出：POST + CSRF（禁止 GET 触发写操作，防止 CSRF 登出攻击）
// 注意：上方外部 JS 均带 defer，内联脚本执行时 jQuery 尚未加载，
// 故使用原生 JS（fetch + addEventListener），不依赖 $
// M18 修复：退出登录增加二次确认（硬约束要求危险操作必须二次确认）
document.getElementById('adminLogoutBtn').addEventListener('click', function(){
    if(typeof Swal === 'undefined'){
        // Swal 未加载时的降级：直接执行登出
        doLogout();
        return;
    }
    Swal.fire({
        title: '确认退出登录？',
        text: '退出后需要重新输入账号密码登录',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '确认退出',
        cancelButtonText: '取消',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        reverseButtons: true
    }).then(function(result){
        if(result.isConfirmed) doLogout();
    });
});
function doLogout(){
    var fd = new FormData();
    fd.append('action', 'logout');
    // M5 修复：CSRF Token 通过 Header 传递
    fetch('login.php', {method:'POST', body:fd, credentials:'same-origin', headers:{'X-CSRF-Token':'<?php echo csrf_token();?>'}})
      .then(function(r){ return r.json(); })
      .then(function(){ window.location.href = 'login.php'; })
      .catch(function(){ window.location.href = 'login.php'; });
}
</script>
</body>
</html>
