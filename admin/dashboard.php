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
    array('key' => 'img520',     'name' => 'IMG520图床',    'icon' => 'mdi-image-multiple-outline','enable' => 'api_img520_enable'),
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
);

$totalCount = count($apiList);
$enabledCount = 0;
$disabledCount = 0;

foreach ($apiList as &$api) {
    $isEnabled = empty($api['enable']) ? true : (isset($conf[$api['enable']]) && $conf[$api['enable']] == '1');
    $api['enabled'] = $isEnabled;
    if ($isEnabled) {
        $enabledCount++;
    } else {
        $disabledCount++;
    }
}
unset($api);

$defaultApi = isset($conf['api_default']) ? $conf['api_default'] : '360';
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

// 分页
$perPage = 10;
$totalPages = ceil($totalCount / $perPage);
$page = isset($_GET['page']) ? max(1, min($totalPages, (int)$_GET['page'])) : 1;
$start = ($page - 1) * $perPage;
$pageList = array_slice($apiList, $start, $perPage);
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
<link rel="stylesheet" href="style/css/admin.css">
<style>html,body{height:100%;margin:0}body{overflow:auto}.admin-content{padding:16px 20px!important}</style>
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
        <div class="stat-value"><?php echo $enabledCount; ?></div>
        <div class="stat-label">已启用</div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="stat-card stat-card-red">
        <div class="stat-icon"><i class="mdi mdi-close-circle-outline"></i></div>
        <div class="stat-value"><?php echo $disabledCount; ?></div>
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
              <tbody>
                <?php $i = $start + 1; foreach ($pageList as $api): ?>
                <tr>
                  <td><?php echo $i++; ?></td>
                  <td>
                    <i class="mdi <?php echo $api['icon']; ?> me-2" style="color:#94a3b8;"></i>
                    <strong><?php echo $api['name']; ?></strong>
                  </td>
                  <td>
                    <?php if ($api['enabled']): ?>
                    <span class="badge-status badge-status-on"><i class="mdi mdi-check"></i> 启用</span>
                    <?php else: ?>
                    <span class="badge-status badge-status-off"><i class="mdi mdi-close"></i> 关闭</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($api['key'] === $defaultApi): ?>
                    <i class="mdi mdi-star" style="color:#f59e0b;font-size:20px;"></i>
                    <?php else: ?>
                    <span style="color:#94a3b8;">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                    <?php if ($api['enabled']): ?>
                      <button type="button" class="btn btn-warning btn-sm" onclick="toggleApi('<?php echo $api['enable']; ?>','<?php echo addslashes($api['name']); ?>','disable');return false;" title="禁用"><i class="mdi mdi-block-helper"></i></button>
                    <?php else: ?>
                      <button type="button" class="btn btn-success btn-sm" onclick="toggleApi('<?php echo $api['enable']; ?>','<?php echo addslashes($api['name']); ?>','enable');return false;" title="启用"><i class="mdi mdi-check"></i></button>
                    <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($totalPages > 1): ?>
          <div class="d-flex justify-content-center py-3">
            <nav>
              <ul class="pagination mb-0">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" style="cursor:pointer;" onclick="location.href='?page=<?php echo $page - 1; ?>';return false;" href="javascript:void(0)">上一页</a></li>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>"><a class="page-link" style="cursor:pointer;" onclick="location.href='?page=<?php echo $p; ?>';return false;" href="javascript:void(0)"><?php echo $p; ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>"><a class="page-link" style="cursor:pointer;" onclick="location.href='?page=<?php echo $page + 1; ?>';return false;" href="javascript:void(0)">下一页</a></li>
              </ul>
            </nav>
          </div>
          <?php endif; ?>
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

<script defer src="style/js/jquery.min.js"></script>
<script defer src="style/js/sweetalert2.min.js"></script>
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
        confirmButtonColor: action === 'enable' ? '#10b981' : '#f59e0b',
        cancelButtonColor: '#94a3b8',
        reverseButtons: true
    }).then(function(result){
        if(result.isConfirmed){
            var fd = new FormData();
            fd.append('action', '1');
            fd.append(enableKey, action === 'enable' ? '1' : '0');
            fd.append('csrf_token', '<?php echo csrf_token();?>');
            $.ajax({
                url: 'api.php',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                success: function(){
                    location.reload();
                },
                error: function(){
                    toast('error', '操作失败，请重试！', 2000);
                }
            });
        }
    });
}
</script>
</body>
</html>