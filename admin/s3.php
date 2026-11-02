<?php
/**
 * @file s3.php
 * @description S3兼容存储配置页面，管理S3存储的新增/编辑/删除，支持Endpoint、Bucket、密钥加密存储及自定义域名
 * @author AI
 * @version 1.2.0-dev
 * @date 2026-08-04
 */
declare(strict_types=1);

ini_set("display_errors", 0);
require('../inc/lang.php');
require('../inc/common.php');
if(!isset($islogin) || $islogin!=1) exit('<script>parent.location.href="login.php";</script>');

// 从数据库加载 S3 存储配置（解密 secret_key）
$s3Configs = array();
if(isset($conf['s3_storage_configs']) && !empty($conf['s3_storage_configs'])) {
    $decoded = json_decode($conf['s3_storage_configs'], true);
    if(is_array($decoded)) {
        // 解密每条配置的 secret_key
        foreach($decoded as &$cfg) {
            if(isset($cfg['secret_key'])) {
                $cfg['secret_key'] = ct_decrypt($cfg['secret_key']);
            }
        }
        unset($cfg);
        $s3Configs = $decoded;
    }
}

// 处理 list / get / 新增 / 编辑 / 删除操作（统一 AJAX JSON 响应，禁止整页刷新）
if(isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if(!csrf_verify()) {
        echo json_encode(['code' => 1, 'msg' => '安全校验失败，请刷新页面后重试！']);
        exit;
    }

    $act = $_POST['action'];

    // ---- list：返回所有 S3 配置（列表展示用，不含敏感字段）----
    if($act === 'list') {
        $out = array();
        foreach($s3Configs as $i => $cfg) {
            // 跳过软删除项（H3 修复：删除时保留索引避免 group_items.s3_id 绑定错位）
            if(isset($cfg['deleted']) && $cfg['deleted'] === '1') continue;
            $out[] = array(
                'id'       => (int)$i,
                'name'     => isset($cfg['name']) ? $cfg['name'] : '',
                'bucket'   => isset($cfg['bucket']) ? $cfg['bucket'] : '',
                'endpoint' => isset($cfg['endpoint']) ? $cfg['endpoint'] : '',
                'enabled'  => isset($cfg['enabled']) ? $cfg['enabled'] : '0',
                'max_size' => isset($cfg['max_size']) ? $cfg['max_size'] : '10',
            );
        }
        echo json_encode(['code' => 0, 'data' => $out]);
        exit;
    }

    // ---- get：返回单条配置（编辑回填用，secret_key 以占位符返回）----
    if($act === 'get' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        if(!isset($s3Configs[$id]) || (isset($s3Configs[$id]['deleted']) && $s3Configs[$id]['deleted'] === '1')) {
            echo json_encode(['code' => 1, 'msg' => '配置不存在！']);
            exit;
        }
        $cfg = $s3Configs[$id];
        // 与前端回填占位符保持一致（16 个 •），保存时据此保留原值
        $cfg['secret_key'] = '••••••••••••••••';
        // M17 修复：access_key 同样掩码（仅显示后4位），与 secret_key 处理一致
        $ak = isset($cfg['access_key']) ? $cfg['access_key'] : '';
        $cfg['access_key'] = strlen($ak) > 4 ? str_repeat('•', strlen($ak) - 4) . substr($ak, -4) : '••••';
        $cfg['id'] = (int)$id;
        echo json_encode(['code' => 0, 'data' => $cfg]);
        exit;
    }

    // ---- delete：软删除指定配置（保留数组索引，避免 eecms_api_group_items.s3_id 绑定错位）----
    if($act === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        if(!isset($s3Configs[$id]) || (isset($s3Configs[$id]['deleted']) && $s3Configs[$id]['deleted'] === '1')) {
            echo json_encode(['code' => 1, 'msg' => '配置不存在！']);
            exit;
        }
        // 软删除：标记 deleted=1 + 强制禁用，保留索引位以保持其他配置的 s3_id 稳定
        $s3Configs[$id]['deleted'] = '1';
        $s3Configs[$id]['enabled'] = '0';
        // H2 修复：存库前必须重新加密所有 secret_key，否则明文落库
        $s3ConfigsToSave = array_map(function($cfg) {
            if(isset($cfg['secret_key'])) {
                $cfg['secret_key'] = ct_encrypt($cfg['secret_key']);
            }
            return $cfg;
        }, array_values($s3Configs));
        $json = json_encode($s3ConfigsToSave, JSON_UNESCAPED_UNICODE);
        $ok = $DB->query_prepared("INSERT INTO eecms_config SET `name`=?, `main`=? ON DUPLICATE KEY UPDATE `main`=?", 'sss', ['s3_storage_configs', $json, $json]);
        if($ok === false) {
            echo json_encode(['code' => 1, 'msg' => '配置删除失败，请重试！']);
            exit;
        }
        echo json_encode(['code' => 0, 'msg' => 'S3 存储配置已删除！']);
        exit;
    }

    // ---- save：新增或编辑配置 ----
    if($act === 'save') {
        $editId = isset($_POST['edit_id']) ? $_POST['edit_id'] : '';
        $entry = array(
            'name'       => isset($_POST['s3_name']) ? trim($_POST['s3_name']) : '',
            'endpoint'   => isset($_POST['s3_endpoint']) ? trim($_POST['s3_endpoint']) : '',
            'access_key' => isset($_POST['s3_access_key']) ? trim($_POST['s3_access_key']) : '',
            'secret_key' => isset($_POST['s3_secret_key']) ? trim($_POST['s3_secret_key']) : '',
            'bucket'     => isset($_POST['s3_bucket']) ? trim($_POST['s3_bucket']) : '',
            'region'     => isset($_POST['s3_region']) ? trim($_POST['s3_region']) : '',
            'path_style' => isset($_POST['s3_path_style']) ? '1' : '0',
            'verify_ssl' => isset($_POST['s3_verify_ssl']) ? '1' : '0',
            'path_prefix'=> isset($_POST['s3_path_prefix']) ? trim($_POST['s3_path_prefix']) : '',
            'domain'     => isset($_POST['s3_domain']) ? trim($_POST['s3_domain']) : '',
            'max_size'   => isset($_POST['s3_max_size']) ? trim($_POST['s3_max_size']) : '10',
            'enabled'    => isset($_POST['s3_enabled']) ? '1' : '0',
        );

        $errors = array();
        if(empty($entry['name'])) $errors[] = '显示名称不能为空';
        if(empty($entry['endpoint'])) $errors[] = 'Endpoint 不能为空';
        if(empty($entry['access_key'])) $errors[] = 'Access Key 不能为空';
        if(empty($entry['secret_key'])) $errors[] = 'Secret Key 不能为空';
        if(empty($entry['bucket'])) $errors[] = 'Bucket 不能为空';
        if(empty($entry['region'])) $errors[] = 'Region 不能为空';

        if(!empty($errors)) {
            echo json_encode(['code' => 1, 'msg' => implode('；', $errors)]);
            exit;
        }

        // 编辑时 secret_key 为占位符（或旧版 __KEEP_EXISTING__ 标志）则保留原值
        if(in_array($entry['secret_key'], ['••••••••••••••••', '__KEEP_EXISTING__'], true) && $editId !== '' && isset($s3Configs[(int)$editId])) {
            $entry['secret_key'] = $s3Configs[(int)$editId]['secret_key'];
        }
        // M17 修复：编辑时 access_key 为掩码（含 •）则保留原值
        if($editId !== '' && isset($s3Configs[(int)$editId]) && strpos($entry['access_key'], '•') !== false) {
            $entry['access_key'] = $s3Configs[(int)$editId]['access_key'];
        }

        if($editId !== '' && isset($s3Configs[(int)$editId]) && !(isset($s3Configs[(int)$editId]['deleted']) && $s3Configs[(int)$editId]['deleted'] === '1')) {
            $s3Configs[(int)$editId] = $entry;
        } else {
            // 新增配置：硬约束要求"所有新增床接口/S3存储必须默认禁用"，服务端强制（忽略前端传入）
            $entry['enabled'] = '0';
            $s3Configs[] = $entry;
        }

        // 加密 secret_key 后再存储到数据库
        $s3ConfigsToSave = array_map(function($cfg) {
            $cfg['secret_key'] = ct_encrypt($cfg['secret_key']);
            return $cfg;
        }, array_values($s3Configs));

        $json = json_encode($s3ConfigsToSave, JSON_UNESCAPED_UNICODE);
        $ok = $DB->query_prepared("INSERT INTO eecms_config SET `name`=?, `main`=? ON DUPLICATE KEY UPDATE `main`=?", 'sss', ['s3_storage_configs', $json, $json]);

        if($ok === false) {
            echo json_encode(['code' => 1, 'msg' => '配置保存失败，请重试！']);
            exit;
        }
        echo json_encode(['code' => 0, 'msg' => 'S3 存储配置已保存！']);
        exit;
    }

    echo json_encode(['code' => 1, 'msg' => '未知操作！']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>S3 存储设置 - <?php echo $lang->admin->title;?></title>
<link rel="preload" href="style/fonts/materialdesignicons-webfont.woff2?v=6.5.95" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="style/css/bootstrap.min.css">
<link rel="stylesheet" href="style/css/materialdesignicons.min.css">
<link rel="stylesheet" href="style/css/admin.css">
<style>body{overflow:auto}</style>
<link rel="stylesheet" href="style/css/sweetalert2.min.css">
</head>
<body>
<div class="admin-content">

  <div class="page-title">
    <i class="mdi mdi-cloud-outline"></i> S3 存储设置
  </div>

  <div class="row">
    <div class="col-lg-12">

      <!-- ========== 列表页（始终展示，新增/编辑通过弹窗）========== -->
      <div class="card">
        <div class="card-header">
          <div class="card-title" style="display:flex;justify-content:space-between;align-items:center;width:100%;">
            <span><i class="mdi mdi-aws"></i> S3 存储配置列表</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="openS3Modal()"><i class="mdi mdi-plus"></i> 新增 S3 配置</button>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th style="width:50px;">#</th>
                  <th>名称</th>
                  <th>Bucket</th>
                  <th>Endpoint</th>
                  <th style="width:100px;">状态</th>
                  <th style="width:120px;">操作</th>
                </tr>
              </thead>
              <tbody id="s3TableBody">
                <tr><td colspan="6" class="text-center text-muted py-4"><i class="mdi mdi-loading mdi-spin"></i> 加载中...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- 常见 S3 兼容服务参考 -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="mdi mdi-information-outline"></i> 常见 S3 兼容服务参考</div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered small mb-0 s3-ref-table">
              <thead>
                <tr><th>服务商</th><th>Endpoint 示例</th><th>Region</th></tr>
              </thead>
              <tbody>
                <tr><td>AWS S3</td><td><code>https://s3.amazonaws.com</code></td><td>us-east-1</td></tr>
                <tr><td>Cloudflare R2</td><td><code>https://&lt;account_id&gt;.r2.cloudflarestorage.com</code></td><td>auto</td></tr>
                <tr><td>阿里云 OSS</td><td><code>https://oss-cn-hangzhou.aliyuncs.com</code></td><td>oss-cn-hangzhou</td></tr>
                <tr><td>腾讯云 COS</td><td><code>https://cos.ap-guangzhou.myqcloud.com</code></td><td>ap-guangzhou</td></tr>
                <tr><td>七牛云 Kodo</td><td><code>https://s3.cn-north-1.qiniucs.com</code></td><td>cn-north-1</td></tr>
                <tr><td>华为云 OBS</td><td><code>https://obs.cn-north-4.myhuaweicloud.com</code></td><td>cn-north-4</td></tr>
                <tr><td>Backblaze B2</td><td><code>https://s3.us-west-004.backblazeb2.com</code></td><td>us-west-004</td></tr>
                <tr><td>MinIO（自建）</td><td><code>https://minio.example.com</code></td><td>us-east-1</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ========== 新增/编辑 S3 配置弹窗 ========== -->
<div class="modal fade" id="s3EditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="s3ModalTitle"><i class="mdi mdi-plus-circle"></i> 新增 S3 配置</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
      </div>
      <div class="modal-body">
        <div id="s3EditAlert" class="alert alert-info" style="display:none;"><i class="mdi mdi-information-outline"></i> <span id="s3EditText"></span></div>
        <form id="s3Form" method="post">
          <div class="mb-3">
            <label class="form-label"><span class="text-danger">*</span> 显示名称</label>
            <input type="text" class="form-control" name="s3_name" id="f_name" placeholder="例如：阿里云 OSS、腾讯云 COS、Cloudflare R2" required style="max-width:500px;">
            <small class="text-muted">用于后台识别，不影响上传</small>
          </div>

          <div class="mb-3">
            <label class="form-label"><span class="text-danger">*</span> Endpoint</label>
            <input type="text" class="form-control" name="s3_endpoint" id="f_endpoint" placeholder="例如：https://s3.amazonaws.com 或 https://oss-cn-hangzhou.aliyuncs.com" required style="max-width:600px;">
            <small class="text-muted">S3 兼容服务的接入点地址</small>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label"><span class="text-danger">*</span> Access Key</label>
              <input type="text" class="form-control" name="s3_access_key" id="f_access_key" placeholder="Access Key" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label"><span class="text-danger">*</span> Secret Key</label>
              <input type="password" class="form-control" name="s3_secret_key" id="f_secret_key" placeholder="Secret Key" required>
              <small class="text-muted" id="f_secret_hint" style="display:none;">密码字段，留空则保持原值不变</small>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label"><span class="text-danger">*</span> Bucket</label>
              <input type="text" class="form-control" name="s3_bucket" id="f_bucket" placeholder="Bucket 名称" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label"><span class="text-danger">*</span> Region</label>
              <input type="text" class="form-control" name="s3_region" id="f_region" placeholder="例如：us-east-1、oss-cn-hangzhou" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">自定义域名（可选）</label>
            <input type="text" class="form-control" name="s3_domain" id="f_domain" placeholder="例如：https://cdn.example.com（留空则使用默认 S3 域名）" style="max-width:600px;">
            <small class="text-muted">用于替换默认的 S3 访问域名，实现 CDN 加速或自定义域名访问</small>
          </div>

          <div class="mb-3">
            <label class="form-label">存储路径前缀（可选）</label>
            <input type="text" class="form-control" name="s3_path_prefix" id="f_path_prefix" placeholder="例如：images（留空则上传到 Bucket 根目录）" style="max-width:600px;">
            <small class="text-muted">上传文件的存储目录前缀，文件直接存入该目录，不会再按日期创建子目录</small>
          </div>

          <div class="mb-3">
            <label class="form-label">单文件大小限制 (MB)</label>
            <input type="number" step="0.1" min="0.1" class="form-control" name="s3_max_size" id="f_max_size" placeholder="10" style="max-width:200px;">
          </div>

          <div class="mb-3">
            <label class="form-label">存储选项</label>
            <div class="d-flex flex-wrap gap-4">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="s3_path_style" id="f_path_style" value="1">
                <label class="form-check-label" for="f_path_style">Path-Style</label>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="s3_verify_ssl" id="f_verify_ssl" value="1" checked>
                <label class="form-check-label" for="f_verify_ssl">校验 SSL</label>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="s3_enabled" id="f_enabled" value="1">
                <label class="form-check-label" for="f_enabled">启用</label>
              </div>
            </div>
            <small class="text-muted d-block mt-2">Path-Style：开启后使用 <code>{endpoint}/{bucket}/{key}</code>。SSL 校验：自建 S3 自签名证书可关闭。启用：开启后该存储可被前端上传接口调度。</small>
          </div>

          <input type="hidden" name="action" value="save">
          <input type="hidden" name="edit_id" id="f_edit_id" value="">
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> 取消</button>
        <button type="button" class="btn btn-primary" onclick="doSave()"><i class="mdi mdi-content-save"></i> <span id="s3SaveBtnText">新增配置</span></button>
      </div>
    </div>
  </div>
</div>

<script src="style/js/jquery.min.js"></script>
<script src="style/js/bootstrap.min.js"></script>
<script src="style/js/sweetalert2.min.js"></script>
<script>
var CSRF_TOKEN = '<?php echo csrf_token();?>';
// M5 修复：CSRF Token 统一通过 Header 传递，不再放 POST Body
$.ajaxSetup({ beforeSend: function(xhr){ xhr.setRequestHeader('X-CSRF-Token', CSRF_TOKEN); } });
var s3Modal;

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

// HTML 转义
function escHtml(s){
    if(s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// 加载 S3 配置列表（AJAX 局部刷新，禁止整页刷新）
function loadS3Configs(){
    $.ajax({
        url:'', type:'POST',
        data:{action:'list'},
        dataType:'json',
        success:function(resp){
            var tbody = $('#s3TableBody');
            if(resp.code !== 0){
                tbody.html('<tr><td colspan="6" class="text-center text-muted py-5"><i class="mdi mdi-alert-circle-outline" style="font-size:32px;display:block;margin-bottom:8px;"></i>'+escHtml(resp.msg || '加载失败')+'</td></tr>');
                return;
            }
            var list = resp.data || [];
            if(!list.length){
                tbody.html('<tr><td colspan="6" class="text-center text-muted py-5"><i class="mdi mdi-cloud-off-outline" style="font-size:42px;display:block;margin-bottom:8px;"></i>暂无 S3 存储配置<br><span style="font-size:13px;">点击右上角「新增 S3 配置」添加你的第一个 S3 存储</span></td></tr>');
                return;
            }
            var html = '';
            list.forEach(function(cfg, idx){
                var statusBadge = cfg.enabled == '1'
                    ? '<span class="badge-status badge-status-on"><i class="mdi mdi-check"></i> 启用</span>'
                    : '<span class="badge-status badge-status-off"><i class="mdi mdi-close"></i> 关闭</span>';
                var name = escHtml(cfg.name);
                html += '<tr id="s3-row-'+cfg.id+'">' +
                    '<td>'+(idx+1)+'</td>' +
                    '<td><strong>'+name+'</strong></td>' +
                    '<td>'+escHtml(cfg.bucket)+'</td>' +
                    '<td><code style="font-size:12px;">'+escHtml(cfg.endpoint)+'</code></td>' +
                    '<td>'+statusBadge+'</td>' +
                    '<td><div class="btn-group btn-group-sm">' +
                        '<button type="button" class="btn btn-info btn-sm act-edit" data-id="'+cfg.id+'" title="编辑"><i class="mdi mdi-pencil"></i></button>' +
                        '<button type="button" class="btn btn-danger btn-sm act-delete" data-id="'+cfg.id+'" data-name="'+name+'" title="删除"><i class="mdi mdi-delete"></i></button>' +
                    '</div></td>' +
                '</tr>';
            });
            tbody.html(html);
        },
        error:function(){
            $('#s3TableBody').html('<tr><td colspan="6" class="text-center text-muted py-5"><i class="mdi mdi-alert-circle-outline" style="font-size:32px;display:block;margin-bottom:8px;"></i>网络错误，请刷新页面重试</td></tr>');
        }
    });
}

// 打开弹窗：无 id 为新增，有 id 为编辑（AJAX 拉取数据回填）
function openS3Modal(id){
    var form = document.getElementById('s3Form');
    form.reset();
    $('#f_edit_id').val('');
    $('#f_secret_key').val('');
    $('#f_path_style').prop('checked', false);
    $('#f_verify_ssl').prop('checked', true);
    $('#f_enabled').prop('checked', false);
    $('#f_secret_hint').hide();
    $('#s3EditAlert').hide();

    if(typeof id !== 'undefined' && id !== null && id !== ''){
        // 编辑模式：拉取单条配置回填
        $('#s3ModalTitle').html('<i class="mdi mdi-pencil"></i> 编辑 S3 配置');
        $('#s3SaveBtnText').text('保存修改');
        $.ajax({
            url:'', type:'POST',
            data:{action:'get', id:id},
            dataType:'json',
            success:function(resp){
                if(resp.code !== 0){ toast('error', resp.msg || '配置不存在'); return; }
                var d = resp.data;
                $('#f_edit_id').val(d.id);
                $('#f_name').val(d.name || '');
                $('#f_endpoint').val(d.endpoint || '');
                $('#f_access_key').val(d.access_key || '');
                $('#f_secret_key').val('••••••••••••••••');
                $('#f_bucket').val(d.bucket || '');
                $('#f_region').val(d.region || '');
                $('#f_domain').val(d.domain || '');
                $('#f_path_prefix').val(d.path_prefix || '');
                $('#f_max_size').val(d.max_size || '10');
                $('#f_path_style').prop('checked', d.path_style === '1');
                $('#f_verify_ssl').prop('checked', d.verify_ssl === '1');
                $('#f_enabled').prop('checked', d.enabled === '1');
                $('#f_secret_hint').show();
                $('#s3EditText').html('正在编辑：<strong>'+escHtml(d.name)+'</strong> —— 密码留空则保持原值不变。');
                $('#s3EditAlert').show();
            },
            error:function(){ toast('error', '加载配置失败，请重试'); }
        });
    } else {
        // 新增模式
        $('#s3ModalTitle').html('<i class="mdi mdi-plus-circle"></i> 新增 S3 配置');
        $('#s3SaveBtnText').text('新增配置');
    }
    s3Modal.show();
}

// 保存（新增/编辑）：AJAX 提交，成功后关闭弹窗 + 刷新列表（禁止整页跳转）
function doSave(){
    var form = document.getElementById('s3Form');
    var name = form.s3_name.value.trim();
    var endpoint = form.s3_endpoint.value.trim();
    var accessKey = form.s3_access_key.value.trim();
    var secretKey = form.s3_secret_key.value.trim();
    var bucket = form.s3_bucket.value.trim();
    var region = form.s3_region.value.trim();

    if(!name){ toast('warning', '请输入显示名称', 2000); return; }
    if(!endpoint){ toast('warning', '请输入 Endpoint', 2000); return; }
    if(!accessKey){ toast('warning', '请输入 Access Key', 2000); return; }
    if(!secretKey){ toast('warning', '请输入 Secret Key', 2000); return; }
    if(!bucket){ toast('warning', '请输入 Bucket 名称', 2000); return; }
    if(!region){ toast('warning', '请输入 Region', 2000); return; }

    Swal.fire({
        title: '确认保存',
        text: '确定要保存当前 S3 存储配置吗？',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '确认保存',
        cancelButtonText: '取消',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#94a3b8',
        reverseButtons: true
    }).then(function(result){
        if(!result.isConfirmed) return;
        var formData = new FormData(form);
        $.ajax({
            url:'', type:'POST', data:formData, processData:false, contentType:false, dataType:'json',
            success:function(resp){
                if(resp.code === 0){
                    s3Modal.hide();
                    toast('success', resp.msg || '保存成功！', 1200);
                    loadS3Configs();
                } else {
                    toast('error', resp.msg || '保存失败', 3000);
                }
            },
            error:function(){ toast('error', '保存失败，请重试！', 3000); }
        });
    });
}

// 删除：AJAX 提交，成功后刷新列表
function deleteConfig(id, name){
    Swal.fire({
        title: '确认删除',
        text: '确定要删除 S3 存储配置「' + name + '」吗？此操作不可恢复！',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '确认删除',
        cancelButtonText: '取消',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        reverseButtons: true
    }).then(function(result){
        if(!result.isConfirmed) return;
        var formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        $.ajax({
            url:'', type:'POST', data:formData, processData:false, contentType:false, dataType:'json',
            success:function(resp){
                if(resp.code === 0){
                    toast('success', resp.msg || '删除成功！', 1200);
                    loadS3Configs();
                } else {
                    toast('error', resp.msg || '删除失败！', 3000);
                }
            },
            error:function(){ toast('error', '删除失败，请重试！', 3000); }
        });
    });
}

// 页面就绪：初始化弹窗实例 + 加载列表 + 事件委托（避免内联 onclick 拼接导致 XSS，H2 修复）
document.addEventListener('DOMContentLoaded', function(){
    var modalEl = document.getElementById('s3EditModal');
    s3Modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    // 事件委托
    $('#s3TableBody').on('click', '.act-edit', function(){
        var id = $(this).data('id');
        if(typeof id !== 'undefined') openS3Modal(id);
    });
    $('#s3TableBody').on('click', '.act-delete', function(){
        var id = $(this).data('id');
        var name = $(this).data('name') || '';
        if(typeof id !== 'undefined') deleteConfig(id, name);
    });
    loadS3Configs();
});
</script>
</body>
</html>
