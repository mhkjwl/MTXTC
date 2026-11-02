<?php
/**
 * @file dashboard.php
 * @description 后台仪表盘页面，展示系统概览统计（图片数、用户数、存储用量、接口状态）及图床接口启停管理
 * @author AI
 * @version 1.1.0-dev
 * @date 2026-08-04
 */
declare(strict_types=1);

ini_set("display_errors", 0);
require('../inc/lang.php');
require('../inc/common.php');
if(!isset($islogin) || $islogin!=1) exit('<script>parent.location.href="login.php";</script>');

// 统计API接口信息
$apiList = array(
    array('key' => '360',        'name' => '360图床',       'icon' => 'mdi-cloud-upload',        'enable' => 'api_360_enable'),
    array('key' => 'local',      'name' => '本地上传',       'icon' => 'mdi-monitor-screenshot',  'enable' => 'api_local_enable'),
    array('key' => 'chevereto',  'name' => 'PlcGO图床',      'icon' => 'mdi-cloud-outline',       'enable' => 'api_chevereto_enable'),
    array('key' => 'zhongzhuan', 'name' => '凌云图床',       'icon' => 'mdi-server',              'enable' => 'api_zhongzhuan_enable'),
    array('key' => 'yiyunt',     'name' => '怡云图床',       'icon' => 'mdi-weather-cloudy',      'enable' => 'api_yiyunt_enable'),
    array('key' => 'cfbed',      'name' => '自建图床',       'icon' => 'mdi-cloud-braces',         'enable' => 'api_cfbed_enable'),
    array('key' => 'scdn',       'name' => 'SCDN图床',       'icon' => 'mdi-flash-outline',       'enable' => 'api_scdn_enable'),
    array('key' => 'imgbb',      'name' => 'ImgBB图床',      'icon' => 'mdi-image-area',          'enable' => 'api_imgbb_enable'),
    array('key' => 'imgurla',    'name' => 'Imgur.LA图床',   'icon' => 'mdi-link-variant',        'enable' => 'api_imgurla_enable'),
    array('key' => 'helloimg',   'name' => 'Hello图床',       'icon' => 'mdi-hand-wave-outline',   'enable' => 'api_helloimg_enable'),
    array('key' => 'stardots',   'name' => 'StarDots图床',   'icon' => 'mdi-star-circle-outline', 'enable' => 'api_stardots_enable'),
    array('key' => 'remit',      'name' => 'Remit图床',      'icon' => 'mdi-image-outline',        'enable' => 'api_remit_enable'),
    array('key' => 'beeimg',     'name' => '蜜蜂图床',       'icon' => 'mdi-bee',                 'enable' => 'api_beeimg_enable'),
    array('key' => 'meituan',    'name' => '美团创作图床',   'icon' => 'mdi-food-outline',        'enable' => 'api_meituan_enable'),
    array('key' => 'alibaba',    'name' => '阿里巴巴图床',   'icon' => 'mdi-shopping-outline',     'enable' => 'api_alibaba_enable'),
    array('key' => 'suning',     'name' => '苏宁易购图床',   'icon' => 'mdi-store-outline',       'enable' => 'api_suning_enable'),
    array('key' => 'meipai',     'name' => '美拍网图床',     'icon' => 'mdi-camera-outline',      'enable' => 'api_meipai_enable'),
    array('key' => 'alipay',     'name' => '支付宝图床',     'icon' => 'mdi-credit-card-outline',  'enable' => 'api_alipay_enable'),
    array('key' => 'phototourl', 'name' => 'PHOTO图床',     'icon' => 'mdi-camera-outline',       'enable' => 'api_phototourl_enable'),
    array('key' => 'imgloc',     'name' => 'IMGLOC图床',    'icon' => 'mdi-image-outline',        'enable' => 'api_imgloc_enable'),
    array('key' => 'locimg',     'name' => 'LOC图床',       'icon' => 'mdi-map-marker-outline',   'enable' => 'api_locimg_enable'),
    array('key' => 'jisu',       'name' => '极速图床',      'icon' => 'mdi-speedometer',          'enable' => 'api_jisu_enable'),
    array('key' => 'yopngs',     'name' => '有图床',        'icon' => 'mdi-image-outline',        'enable' => 'api_yopngs_enable'),
    array('key' => 'feria',      'name' => '风筝图床',      'icon' => 'mdi-kite-outline',         'enable' => 'api_feria_enable'),
    array('key' => 'gurl',       'name' => 'Telegraph图床', 'icon' => 'mdi-telegraph',            'enable' => 'api_gurl_enable'),
    array('key' => 'ljpic',      'name' => '云间图床',      'icon' => 'mdi-weather-cloudy',       'enable' => 'api_ljpic_enable'),
    array('key' => 'nickyam',    'name' => 'Telegraph2图床','icon' => 'mdi-telegraph',            'enable' => 'api_nickyam_enable'),
    array('key' => 'dogimg',     'name' => '狗狗图床',      'icon' => 'mdi-dog',                  'enable' => 'api_dogimg_enable'),
    array('key' => 'matu',       'name' => '宝马图床',      'icon' => 'mdi-car-sports',           'enable' => 'api_matu_enable'),
    array('key' => 'pnglog',     'name' => '盘络图床',      'icon' => 'mdi-image-outline',        'enable' => 'api_pnglog_enable'),
    array('key' => 'lvse',       'name' => '绿色图床',      'icon' => 'mdi-leaf',                 'enable' => 'api_lvse_enable'),
    array('key' => 'fatcat',     'name' => '肥喵图床',      'icon' => 'mdi-cat',                  'enable' => 'api_fatcat_enable'),
    array('key' => '131img',     'name' => '131图床',       'icon' => 'mdi-image-multiple-outline','enable' => 'api_131img_enable'),
    array('key' => 'feimg',      'name' => 'FE图床',        'icon' => 'mdi-cloud-upload-outline', 'enable' => 'api_feimg_enable'),
    array('key' => 'yootn',      'name' => '友藤图床',      'icon' => 'mdi-vector-triangle',      'enable' => 'api_yootn_enable'),
    array('key' => 'czl',        'name' => 'CZL图床',       'icon' => 'mdi-image-outline',        'enable' => 'api_czl_enable'),
    array('key' => 'tutu',       'name' => 'TUTU图床',      'icon' => 'mdi-image-multiple-outline','enable' => 'api_tutu_enable'),
    array('key' => 'uuimg',      'name' => '悠悠图床',      'icon' => 'mdi-cloud-upload-outline', 'enable' => 'api_uuimg_enable'),
    array('key' => 'tuwu',       'name' => '图屋图床',      'icon' => 'mdi-home-outline',         'enable' => 'api_tuwu_enable'),
    array('key' => 'urusai',     'name' => 'UR图床',        'icon' => 'mdi-image-filter-drama',   'enable' => 'api_urusai_enable'),
    array('key' => 'imgcc',      'name' => '云图床',        'icon' => 'mdi-cloud-check-outline',  'enable' => 'api_imgcc_enable'),
    array('key' => 'imgdata',    'name' => 'ImgURL图床',    'icon' => 'mdi-database-outline',     'enable' => 'api_imgdata_enable'),
    array('key' => 'pngcdn',     'name' => '云朵图床',      'icon' => 'mdi-cloud-outline',        'enable' => 'api_pngcdn_enable'),
    array('key' => 'naixiai',    'name' => '奶昔图床',      'icon' => 'mdi-cup-outline',          'enable' => 'api_naixiai_enable'),
    array('key' => 'youzan',     'name' => '有赞图床',      'icon' => 'mdi-storefront-outline',   'enable' => 'api_youzan_enable'),
    array('key' => 'wentian',    'name' => 'WENTIAN图床',   'icon' => 'mdi-cloud-outline',        'enable' => 'api_wentian_enable'),
    // L1 修复：补齐 6 个新接入接口，原 $apiList 与 admin/api.php 不同步导致仪表盘统计遗漏
    array('key' => 'imgw',       'name' => '图网图床',       'icon' => 'mdi-cloud-upload-outline', 'enable' => 'api_imgw_enable'),
    array('key' => 'xwyue',      'name' => '星跃图床',       'icon' => 'mdi-cloud-upload',         'enable' => 'api_xwyue_enable'),
    array('key' => 'keye',       'name' => '珂艺云图床',     'icon' => 'mdi-cloud-sync',           'enable' => 'api_keye_enable'),
    array('key' => 'shaitu',     'name' => '晒图床',         'icon' => 'mdi-image-multiple',       'enable' => 'api_shaitu_enable'),
    array('key' => 'guaigua',    'name' => '乖乖图床',       'icon' => 'mdi-emoticon-happy',       'enable' => 'api_guaigua_enable'),
    array('key' => 'imgtolink',  'name' => 'LINK图床',       'icon' => 'mdi-link-variant',         'enable' => 'api_imgtolink_enable'),
);

$totalCount = count($apiList);
$enabledCount = 0;
$disabledCount = 0;

foreach ($apiList as &$api) {
    $isEnabled = empty($api['enable']) ? true : api_config_enabled($conf, $api['key']);
    $api['enabled'] = $isEnabled;
    if ($isEnabled) {
        $enabledCount++;
    } else {
        $disabledCount++;
    }
}
unset($api);

$defaultApi = isset($conf['api_default']) ? $conf['api_default'] : 'local';
$defaultName = '未知';
foreach ($apiList as $api) {
    if ($api['key'] === $defaultApi) {
        $defaultName = $api['name'];
        break;
    }
}

// 系统概览统计：图片数量、用户数量、启用用户、存储用量
$totalImages  = function_exists('pkg_safe_count') ? pkg_safe_count($DB, "SELECT COUNT(*) FROM eecms_images") : (int)$DB->count("SELECT COUNT(*) FROM eecms_images");
$totalUsers   = function_exists('pkg_safe_count') ? pkg_safe_count($DB, "SELECT COUNT(*) FROM eecms_users") : (int)$DB->count("SELECT COUNT(*) FROM eecms_users");
$activeUsers  = function_exists('pkg_safe_count') ? pkg_safe_count($DB, "SELECT COUNT(*) FROM eecms_users WHERE status=1") : (int)$DB->count("SELECT COUNT(*) FROM eecms_users WHERE status=1");
$totalSizeRaw = function_exists('pkg_safe_count') ? pkg_safe_count($DB, "SELECT COALESCE(SUM(size),0) FROM eecms_images") : (int)$DB->count("SELECT COALESCE(SUM(size),0) FROM eecms_images");

// 存储用量格式化（人类可读）
$_tb = (int)$totalSizeRaw;
if ($_tb >= 1073741824)      { $totalSize = number_format($_tb / 1073741824, 2) . ' GB'; }
elseif ($_tb >= 1048576)     { $totalSize = number_format($_tb / 1048576, 2) . ' MB'; }
elseif ($_tb >= 1024)        { $totalSize = number_format($_tb / 1024, 2) . ' KB'; }
else                         { $totalSize = $_tb . ' B'; }

// 分页由前端 JS 完成（renderApiTable），服务端无需预切片
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>后台首页 - <?php echo $lang->admin->title;?></title>
<link rel="preload" href="style/fonts/materialdesignicons-webfont.woff2?v=6.5.95" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="style/css/bootstrap.min.css">
<link rel="stylesheet" href="style/css/materialdesignicons.min.css">
<link rel="stylesheet" href="style/css/sweetalert2.min.css">
<link rel="stylesheet" href="style/css/admin.css">
<style>body{overflow:auto}</style>
</head>
<body>
<div class="admin-content">

  <div class="page-title">
    <i class="mdi mdi-view-dashboard-outline"></i> 仪表盘
  </div>

  <!-- 系统概览统计卡片 -->
  <div class="row g-2 mb-2">
    <div class="col-md-6 col-xl-3">
      <div class="stat-card stat-card-purple">
        <div class="stat-icon"><i class="mdi mdi-image-multiple-outline"></i></div>
        <div class="stat-value"><?php echo $totalImages; ?></div>
        <div class="stat-label">图片总数</div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="stat-card stat-card-orange">
        <div class="stat-icon"><i class="mdi mdi-account-group-outline"></i></div>
        <div class="stat-value"><?php echo $totalUsers; ?></div>
        <div class="stat-label">用户总数</div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="stat-card stat-card-green">
        <div class="stat-icon"><i class="mdi mdi-account-check-outline"></i></div>
        <div class="stat-value"><?php echo $activeUsers; ?></div>
        <div class="stat-label">启用用户</div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="stat-card stat-card-cyan">
        <div class="stat-icon"><i class="mdi mdi-database-outline"></i></div>
        <div class="stat-value" style="font-size:22px;"><?php echo $totalSize; ?></div>
        <div class="stat-label">存储用量</div>
      </div>
    </div>
  </div>

  <!-- 接口统计卡片 -->
  <div class="row g-2 mb-2">
    <div class="col-md-6 col-xl-3">
      <div class="stat-card stat-card-blue">
        <div class="stat-icon"><i class="mdi mdi-api"></i></div>
        <div class="stat-value"><?php echo $totalCount; ?></div>
        <div class="stat-label">接口总数</div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="stat-card stat-card-green">
        <div class="stat-icon"><i class="mdi mdi-check-circle-outline"></i></div>
        <div class="stat-value" id="statEnabled"><?php echo $enabledCount; ?></div>
        <div class="stat-label">已启用</div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="stat-card stat-card-red">
        <div class="stat-icon"><i class="mdi mdi-close-circle-outline"></i></div>
        <div class="stat-value" id="statDisabled"><?php echo $disabledCount; ?></div>
        <div class="stat-label">已关闭</div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="stat-card stat-card-cyan">
        <div class="stat-icon"><i class="mdi mdi-star-outline"></i></div>
        <div class="stat-value" style="font-size:18px;"><?php echo $defaultName; ?></div>
        <div class="stat-label">默认图床</div>
      </div>
    </div>
  </div>

  <!-- 接口详情 + 系统信息 -->
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="mdi mdi-view-dashboard-outline"></i> 图床接口状态</div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th style="width:50px;">#</th>
                  <th>接口名称</th>
                  <th style="width:100px;">状态</th>
                  <th style="width:80px;">默认</th>
                  <th style="width:100px;">操作</th>
                </tr>
              </thead>
              <tbody id="apiTableBody">
                <tr><td colspan="5" class="text-center text-muted py-4"><i class="mdi mdi-loading mdi-spin"></i> 加载中...</td></tr>
              </tbody>
            </table>
          </div>
          <div id="apiPagination" class="d-flex justify-content-center py-3"></div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="mdi mdi-information-outline"></i> 系统信息</div>
        </div>
        <div class="card-body">
          <div class="alert alert-info mb-3">
            <i class="mdi mdi-account-circle"></i> 管理员 <strong><?php echo isset($conf['admin_user']) ? htmlspecialchars($conf['admin_user'], ENT_QUOTES, 'UTF-8') : '';?></strong>，欢迎回来！
          </div>
          <table class="table table-bordered sys-info-table">
            <tbody>
              <tr><th>服务器软件</th><td><?php echo htmlspecialchars(isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '', ENT_QUOTES, 'UTF-8'); ?></td></tr>
              <tr><th>PHP 版本</th><td><?php echo phpversion(); ?></td></tr>
              <tr><th>POST 许可</th><td><?php echo ini_get('post_max_size'); ?></td></tr>
              <tr><th>文件上传许可</th><td><?php echo ini_get('upload_max_filesize'); ?></td></tr>
              <tr><th>最大运行时间</th><td><?php echo ini_get('max_execution_time'); ?>s</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<script src="style/js/jquery.min.js"></script>
<script src="style/js/sweetalert2.min.js"></script>
<script>
// H5 修复：接口列表由 JS 渲染，分页用 AJAX POST（不再 location.href 整页刷新 / URL 传参）
// 接口元数据由服务端一次性输出（仅含 key/name/icon/enable_key/enabled/is_default，无敏感信息）
var API_LIST = <?php echo json_encode(array_map(function($a) use ($defaultApi) {
    return [
        'key'        => $a['key'],
        'name'       => $a['name'],
        'icon'       => $a['icon'],
        'enable_key' => $a['enable'],
        'enabled'    => (bool)$a['enabled'],
        'is_default' => ($a['key'] === $defaultApi),
    ];
}, $apiList), JSON_UNESCAPED_UNICODE); ?>;
var CSRF_TOKEN = '<?php echo csrf_token();?>';
// M5 修复：CSRF Token 统一通过 Header 传递，不再放 POST Body
$.ajaxSetup({ beforeSend: function(xhr){ xhr.setRequestHeader('X-CSRF-Token', CSRF_TOKEN); } });
var PER_PAGE = 10;
var _curPage = 1;

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

function escHtml(s){
    if(s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function renderApiTable(page){
    _curPage = page;
    var total = API_LIST.length;
    var totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
    if(page > totalPages) page = totalPages;
    var start = (page - 1) * PER_PAGE;
    var slice = API_LIST.slice(start, start + PER_PAGE);
    var tbody = $('#apiTableBody');
    tbody.empty();
    if(slice.length === 0){
        tbody.html('<tr><td colspan="5" class="text-center text-muted py-4">暂无接口数据</td></tr>');
    } else {
        for(var i = 0; i < slice.length; i++){
            var a = slice[i];
            var statusBadge = a.enabled
                ? '<span class="badge-status badge-status-on"><i class="mdi mdi-check"></i> 启用</span>'
                : '<span class="badge-status badge-status-off"><i class="mdi mdi-close"></i> 关闭</span>';
            var defaultCell = a.is_default
                ? '<i class="mdi mdi-star" style="color:var(--admin-warning);font-size:18px;"></i>'
                : '<span style="color:var(--admin-text-faint);">-</span>';
            var actionBtn = a.enabled
                ? '<button type="button" class="btn btn-outline-warning btn-sm act-toggle" data-enable-key="'+escHtml(a.enable_key)+'" data-name="'+escHtml(a.name)+'" data-action="disable" title="禁用"><i class="mdi mdi-block-helper"></i></button>'
                : '<button type="button" class="btn btn-outline-success btn-sm act-toggle" data-enable-key="'+escHtml(a.enable_key)+'" data-name="'+escHtml(a.name)+'" data-action="enable" title="启用"><i class="mdi mdi-check"></i></button>';
            tbody.append('<tr id="api-row-'+escHtml(a.enable_key)+'">' +
                '<td>'+(start+i+1)+'</td>' +
                '<td><i class="mdi '+escHtml(a.icon)+' me-2" style="color:#94a3b8;"></i><strong>'+escHtml(a.name)+'</strong></td>' +
                '<td>'+statusBadge+'</td>' +
                '<td>'+defaultCell+'</td>' +
                '<td><div class="btn-group btn-group-sm">'+actionBtn+'</div></td>' +
                '</tr>');
        }
    }
    // 分页
    var box = $('#apiPagination');
    box.empty();
    if(totalPages <= 1) return;
    var ul = $('<ul class="pagination mb-0"></ul>');
    ul.append('<li class="page-item '+(page<=1?'disabled':'')+'"><a class="page-link" href="javascript:void(0)" data-page="'+(page-1)+'">上一页</a></li>');
    for(var p = 1; p <= totalPages; p++){
        ul.append('<li class="page-item '+(p===page?'active':'')+'"><a class="page-link" href="javascript:void(0)" data-page="'+p+'">'+p+'</a></li>');
    }
    ul.append('<li class="page-item '+(page>=totalPages?'disabled':'')+'"><a class="page-link" href="javascript:void(0)" data-page="'+(page+1)+'">下一页</a></li>');
    box.append($('<nav></nav>').append(ul));
}

// 事件委托：分页 + 接口启停（H2/H5 修复：避免内联 onclick 拼接 XSS）
$(function(){
    $('#apiPagination').on('click', '.page-link', function(){
        var p = parseInt($(this).data('page'), 10);
        if(!isNaN(p) && p >= 1) renderApiTable(p);
    });
    $('#apiTableBody').on('click', '.act-toggle', function(){
        var enableKey = $(this).data('enable-key');
        var name = $(this).data('name');
        var action = $(this).data('action');
        if(enableKey) toggleApi(enableKey, name, action);
    });
    renderApiTable(1);
});

function toggleApi(enableKey, name, action){
    var actText = action === 'enable' ? '启用' : '禁用';
    var iconType = action === 'enable' ? 'question' : 'warning';
    Swal.fire({
        title: '确认操作',
        text: '确定要' + actText + '「' + name + '」吗？',
        icon: iconType,
        showCancelButton: true,
        confirmButtonText: '确认' + actText,
        cancelButtonText: '取消',
        confirmButtonColor: action === 'enable' ? '#1a7f4b' : '#94620a',
        cancelButtonColor: '#6b7280',
        reverseButtons: true
    }).then(function(result){
        if(!result.isConfirmed) return;
        var fd = new FormData();
        fd.append('action', '1');
        fd.append(enableKey, action === 'enable' ? '1' : '0');
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            // H5 修复：必须读取响应内容判断成功/失败，原来无参数导致服务端失败仍显示成功
            success: function(res){
                if(res && res.code === 0){
                    toast('success', actText + '成功！', 1200);
                    // 局部更新该接口行状态与统计卡片（禁止整页刷新）
                    var row = document.getElementById('api-row-' + enableKey);
                    if(row){
                        var cells = row.querySelectorAll('td');
                        if(action === 'enable'){
                            cells[2].innerHTML = '<span class="badge-status badge-status-on"><i class="mdi mdi-check"></i> 启用</span>';
                            cells[4].innerHTML = '<div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-warning btn-sm act-toggle" data-enable-key="'+escHtml(enableKey)+'" data-name="'+escHtml(name)+'" data-action="disable" title="禁用"><i class="mdi mdi-block-helper"></i></button></div>';
                            var en = document.getElementById('statEnabled'); if(en){ en.textContent = parseInt(en.textContent,10)+1; }
                            var dis = document.getElementById('statDisabled'); if(dis){ dis.textContent = parseInt(dis.textContent,10)-1; }
                        } else {
                            cells[2].innerHTML = '<span class="badge-status badge-status-off"><i class="mdi mdi-close"></i> 关闭</span>';
                            cells[4].innerHTML = '<div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-success btn-sm act-toggle" data-enable-key="'+escHtml(enableKey)+'" data-name="'+escHtml(name)+'" data-action="enable" title="启用"><i class="mdi mdi-check"></i></button></div>';
                            var en = document.getElementById('statEnabled'); if(en){ en.textContent = parseInt(en.textContent,10)-1; }
                            var dis = document.getElementById('statDisabled'); if(dis){ dis.textContent = parseInt(dis.textContent,10)+1; }
                        }
                    }
                    // 同步更新内存中的 API_LIST 状态
                    for(var i = 0; i < API_LIST.length; i++){
                        if(API_LIST[i].enable_key === enableKey){
                            API_LIST[i].enabled = (action === 'enable');
                        }
                    }
                } else {
                    toast('error', (res && res.msg) ? res.msg : (actText + '失败！'), 3000);
                }
            },
            error: function(){
                toast('error', '网络错误，请重试！', 2000);
            }
        });
    });
}
</script>
</body>
</html>