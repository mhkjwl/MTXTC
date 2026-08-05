<?php
declare(strict_types=1);
/**
 * 后台 - API 密钥管理
 *
 * @file        admin/apikeys.php
 * @description 展示全站所有用户的 API 密钥，支持按用户名/状态搜索
 *              操作：查看详情、重新生成（明文仅返回一次）、删除、启用/禁用
 *              安全：DB 只存 SHA-256 哈希，管理员无法查看历史明文（与 GitHub Token 一致）
 * @author      eecms
 * @version     1.1.0-dev
 * @date        2026-08-04
 * @see         docs/AI开发规范.md § 5.4（API 数据传输）、§ 6（敏感操作二次确认）
 */
ini_set("display_errors", 0);
require('../inc/lang.php');
require('../inc/common.php');
if(!isset($islogin) || $islogin!=1) exit('<script>parent.location.href="login.php";</script>');

// 统一 JSON 响应
function ak_json(int $code, string $msg, $data = null): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => $code, 'msg' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== AJAX 接口 ==========
if(isset($_POST['action'])) {
    $action = $_POST['action'];
    // CSRF 校验：仅写操作（list/detail/user_keys 为查询，不需 CSRF）
    $writeActions = ['regen', 'delete', 'toggle'];
    if(in_array($action, $writeActions, true) && !csrf_verify()) {
        ak_json(1, '安全校验失败，请刷新页面后重试！');
    }

    // ----- 密钥列表（支持 username/status 过滤） -----
    if($action === 'list') {
        $username = trim($_POST['username'] ?? '');
        $status   = $_POST['status'] ?? ''; // ''=全部, '1'=启用, '0'=禁用
        $filters = [];
        if($username !== '') $filters['username'] = $username;
        if($status !== '' && $status !== null) $filters['status'] = (int)$status;

        $rows = api_key_list_all($DB, $filters);
        $list = [];
        foreach($rows as $r) {
            $list[] = [
                'id'           => (int)$r['id'],
                'user_id'      => (int)$r['user_id'],
                'username'     => $r['username'] ? $r['username'] : '(已删除用户)',
                'name'         => $r['name'],
                'key_prefix'   => $r['key_prefix'],
                'status'       => (int)$r['status'],
                'last_used_at' => $r['last_used_at'],
                'created_at'   => $r['created_at'],
            ];
        }
        ak_json(0, 'ok', ['list' => $list, 'total' => count($list)]);
    }

    // ----- 查看详情（仅展示前缀/状态/时间，明文不可还原） -----
    if($action === 'detail') {
        $id = (int)($_POST['id'] ?? 0);
        if($id <= 0) ak_json(1, '参数错误');
        $row = api_key_get_by_id_admin($DB, $id);
        if(!$row) ak_json(1, '密钥不存在');
        ak_json(0, 'ok', [
            'id'           => (int)$row['id'],
            'user_id'      => (int)$row['user_id'],
            'username'     => $row['username'] ? $row['username'] : '(已删除用户)',
            'name'         => $row['name'],
            'key_prefix'   => $row['key_prefix'],
            'status'       => (int)$row['status'],
            'last_used_at' => $row['last_used_at'],
            'created_at'   => $row['created_at'],
        ]);
    }

    // ----- 重新生成（明文仅返回一次） -----
    if($action === 'regen') {
        $id = (int)($_POST['id'] ?? 0);
        if($id <= 0) ak_json(1, '参数错误');
        $existing = api_key_get_by_id_admin($DB, $id);
        if(!$existing) ak_json(1, '密钥不存在');
        $ret = api_key_regen_admin($DB, $id);
        if(empty($ret)) ak_json(1, '重新生成失败');
        log_admin_action($DB, 'regen', 'api_key', $id, ['name' => $existing['name'], 'user_id' => (int)$existing['user_id']]);
        // 明文仅返回一次，前端弹窗展示并提示立即保存
        ak_json(0, '密钥已重新生成，请立即复制保存（明文仅展示一次）', [
            'api_key'    => $ret['api_key'],
            'key_prefix' => $ret['key_prefix'],
        ]);
    }

    // ----- 删除 -----
    if($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if($id <= 0) ak_json(1, '参数错误');
        $existing = api_key_get_by_id_admin($DB, $id);
        if(!$existing) ak_json(1, '密钥不存在');
        $ok = api_key_delete_admin($DB, $id);
        if(!$ok) ak_json(1, '删除失败');
        log_admin_action($DB, 'delete', 'api_key', $id, ['name' => $existing['name'], 'user_id' => (int)$existing['user_id']]);
        ak_json(0, '已删除');
    }

    // ----- 启用/禁用 -----
    if($action === 'toggle') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['status'] ?? 0);
        if($id <= 0) ak_json(1, '参数错误');
        $existing = api_key_get_by_id_admin($DB, $id);
        if(!$existing) ak_json(1, '密钥不存在');
        $ok = api_key_set_status_admin($DB, $id, $status);
        if(!$ok) ak_json(1, '操作失败');
        log_admin_action($DB, 'toggle', 'api_key', $id, ['name' => $existing['name'], 'status' => $status]);
        ak_json(0, $status ? '已启用' : '已禁用');
    }

    // ----- 指定用户的密钥列表（用户管理详情 tab 用） -----
    if($action === 'user_keys') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if($userId <= 0) ak_json(1, '参数错误');
        $rows = api_key_list_by_user($DB, $userId);
        $list = [];
        foreach($rows as $r) {
            $list[] = [
                'id'           => (int)$r['id'],
                'name'         => $r['name'],
                'key_prefix'   => $r['key_prefix'],
                'status'       => (int)$r['status'],
                'last_used_at' => $r['last_used_at'],
                'created_at'   => $r['created_at'],
            ];
        }
        $cnt = api_key_count_by_user($DB, $userId);
        ak_json(0, 'ok', ['list' => $list, 'count' => $cnt]);
    }

    ak_json(1, '未知操作');
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API密钥管理 - <?php echo $lang->admin->title;?></title>
<link rel="preload" href="style/fonts/materialdesignicons-webfont.woff2?v=6.5.95" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="style/css/bootstrap.min.css">
<link rel="stylesheet" href="style/css/materialdesignicons.min.css">
<link rel="stylesheet" href="style/css/admin.css">
<link rel="stylesheet" href="style/css/sweetalert2.min.css">
<style>
  html,body{height:100%;margin:0}body{overflow:auto}.admin-content{padding:16px 20px!important}
  .ak-prefix{font-family:Consolas,Monaco,monospace;background:#f4f5f7;padding:2px 8px;border-radius:6px;color:#6d4aff;font-weight:600;font-size:12px}
  .ak-status-on{color:#10b981;font-weight:600}
  .ak-status-off{color:#ef4444;font-weight:600}
  .ak-plainkey{font-family:Consolas,Monaco,monospace;background:#1e1b2e;color:#a5f3fc;padding:14px;border-radius:8px;word-break:break-all;font-size:13px;line-height:1.6;border:1px solid #3b3553}
  .ak-mono{font-family:Consolas,Monaco,monospace;font-size:12px;color:#64748b}
</style>
</head>
<body>
<div class="admin-content">

  <div class="page-title">
    <i class="mdi mdi-key-variant"></i> API 密钥管理
  </div>

  <!-- 搜索栏 -->
  <div class="card mb-2">
    <div class="card-body py-2">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label small mb-1">用户名</label>
          <input type="text" class="form-control form-control-sm" id="fUsername" placeholder="按用户名搜索">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1">状态</label>
          <select class="form-select form-select-sm" id="fStatus">
            <option value="">全部</option>
            <option value="1">启用</option>
            <option value="0">禁用</option>
          </select>
        </div>
        <div class="col-md-5 text-end">
          <button type="button" class="btn btn-primary btn-sm" onclick="loadList()"><i class="mdi mdi-magnify"></i> 搜索</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetFilter()"><i class="mdi mdi-refresh"></i> 重置</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 密钥列表 -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="card-title"><i class="mdi mdi-key-chain"></i> 全站密钥列表</div>
      <span class="ak-mono">共 <b id="totalCount">0</b> 条</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th style="width:60px;">ID</th>
              <th>所属用户</th>
              <th>密钥名称</th>
              <th>密钥前缀</th>
              <th style="width:90px;">状态</th>
              <th style="width:150px;">创建时间</th>
              <th style="width:150px;">最后使用</th>
              <th style="width:160px;">操作</th>
            </tr>
          </thead>
          <tbody id="akTableBody">
            <tr><td colspan="8" class="text-center text-muted py-5"><i class="mdi mdi-loading mdi-spin"></i> 加载中...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- 安全提示 -->
  <div class="alert alert-info mt-2 mb-0 py-2 small">
    <i class="mdi mdi-shield-check-outline"></i>
    出于安全考虑，密钥明文仅在 <b>重新生成</b> 时返回一次（数据库只存 SHA-256 哈希，不可逆推）。
    如需查看历史明文，请联系用户在生成时自行保存。
  </div>

</div>

<!-- 重新生成结果 Modal（明文仅展示一次） -->
<div class="modal fade" id="plainModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title"><i class="mdi mdi-alert"></i> 新密钥已生成（仅展示一次）</h5>
      </div>
      <div class="modal-body">
        <p class="text-danger small mb-2"><i class="mdi mdi-alert-circle"></i> 请立即复制保存，关闭后将无法再次查看！</p>
        <div class="ak-plainkey" id="plainKeyBox"></div>
        <div class="mt-2 ak-mono">前缀：<span id="plainPrefix"></span></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary btn-sm" onclick="copyPlain()"><i class="mdi mdi-content-copy"></i> 复制密钥</button>
        <button type="button" class="btn btn-success btn-sm" onclick="closePlainModal()">我已保存</button>
      </div>
    </div>
  </div>
</div>

<input type="hidden" id="csrfToken" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');?>">

<script src="style/js/jquery.min.js"></script>
<script src="style/js/bootstrap.bundle.min.js"></script>
<script src="style/js/sweetalert2.min.js"></script>
<script>
// CSRF Token 统一注入请求头
$.ajaxSetup({
    beforeSend: function(xhr){ xhr.setRequestHeader('X-CSRF-Token', $('#csrfToken').val()); }
});

var currentPlainKey = '';

// 加载密钥列表
function loadList(){
    var u = $.trim($('#fUsername').val());
    var s = $('#fStatus').val();
    $('#akTableBody').html('<tr><td colspan="8" class="text-center text-muted py-5"><i class="mdi mdi-loading mdi-spin"></i> 加载中...</td></tr>');
    $.post('apikeys.php?action=list', {action:'list', username:u, status:s}, function(res){
        if(res.code !== 0){ Swal.fire('错误', res.msg, 'error'); return; }
        var list = res.data.list || [];
        $('#totalCount').text(list.length);
        if(list.length === 0){
            $('#akTableBody').html('<tr><td colspan="8" class="text-center text-muted py-5"><i class="mdi mdi-key-remove"></i> 暂无密钥</td></tr>');
            return;
        }
        var html = '';
        for(var i=0;i<list.length;i++){
            var r = list[i];
            var st = r.status === 1
                ? '<span class="ak-status-on"><i class="mdi mdi-check-circle"></i> 启用</span>'
                : '<span class="ak-status-off"><i class="mdi mdi-close-circle"></i> 禁用</span>';
            html += '<tr>'
                + '<td>'+r.id+'</td>'
                + '<td>'+escapeHtml(r.username)+'</td>'
                + '<td>'+escapeHtml(r.name)+'</td>'
                + '<td><span class="ak-prefix">'+escapeHtml(r.key_prefix)+'</span></td>'
                + '<td>'+st+'</td>'
                + '<td class="ak-mono">'+(r.created_at||'-')+'</td>'
                + '<td class="ak-mono">'+(r.last_used_at||'<span class="text-muted">未使用</span>')+'</td>'
                + '<td>'
                + '  <button class="btn btn-outline-warning btn-sm py-0 px-2" onclick="regen('+r.id+')"><i class="mdi mdi-refresh"></i> 重置</button>'
                + '  <button class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="toggle('+r.id+','+(r.status===1?0:1)+')">'+(r.status===1?'<i class="mdi mdi-pause"></i> 禁用':'<i class="mdi mdi-play"></i> 启用')+'</button>'
                + '  <button class="btn btn-outline-danger btn-sm py-0 px-2" onclick="del('+r.id+')"><i class="mdi mdi-delete"></i> 删除</button>'
                + '</td>'
                + '</tr>';
        }
        $('#akTableBody').html(html);
    }, 'json').fail(function(){ Swal.fire('错误','网络错误','error'); });
}

function resetFilter(){
    $('#fUsername').val(''); $('#fStatus').val(''); loadList();
}

// 重新生成（二次确认 → 全局通知 → 明文仅展示一次 → 局部刷新）
function regen(id){
    Swal.fire({
        title:'确认重新生成？',
        text:'旧密钥明文将立即失效，所有使用旧密钥的客户端需更新。新明文仅展示一次。',
        icon:'warning', showCancelButton:true,
        confirmButtonText:'确认重置', cancelButtonText:'取消', reverseButtons:true,
        confirmButtonColor:'#f59e0b'
    }).then(function(r){
        if(!r.isConfirmed) return;
        $.post('apikeys.php?action=regen', {action:'regen', id:id}, function(res){
            if(res.code !== 0){ Swal.fire('失败', res.msg, 'error'); return; }
            currentPlainKey = res.data.api_key;
            $('#plainKeyBox').text(currentPlainKey);
            $('#plainPrefix').text(res.data.key_prefix);
            // 全局通知（与删除操作一致）→ 通知结束后先刷新列表 → 再展示明文
            Swal.fire({title:'已重置', icon:'success', timer:1200, showConfirmButton:false})
                .then(function(){
                    loadList();
                    // 确保 SweetAlert2 遮罩已清除，再弹出 Bootstrap Modal
                    var swalContainer = document.querySelector('.swal2-container');
                    if(swalContainer){ swalContainer.remove(); }
                    var plainModalEl = document.getElementById('plainModal');
                    var plainModal = bootstrap.Modal.getInstance(plainModalEl) || new bootstrap.Modal(plainModalEl);
                    plainModal.show();
                });
        }, 'json').fail(function(){ Swal.fire('错误','网络错误','error'); });
    });
}

// 删除（二次确认）
function del(id){
    Swal.fire({
        title:'确认删除？',
        text:'删除后该密钥立即失效，且无法恢复。',
        icon:'warning', showCancelButton:true,
        confirmButtonText:'确认删除', cancelButtonText:'取消', reverseButtons:true,
        confirmButtonColor:'#ef4444'
    }).then(function(r){
        if(!r.isConfirmed) return;
        $.post('apikeys.php?action=delete', {action:'delete', id:id}, function(res){
            if(res.code !== 0){ Swal.fire('失败', res.msg, 'error'); return; }
            Swal.fire({title:'已删除', icon:'success', timer:1200, showConfirmButton:false});
            loadList();
        }, 'json').fail(function(){ Swal.fire('错误','网络错误','error'); });
    });
}

// 启用/禁用（二次确认）
function toggle(id, status){
    var act = status===1?'启用':'禁用';
    Swal.fire({
        title:'确认'+act+'？',
        icon:'warning', showCancelButton:true,
        confirmButtonText:'确认', cancelButtonText:'取消', reverseButtons:true
    }).then(function(r){
        if(!r.isConfirmed) return;
        $.post('apikeys.php?action=toggle', {action:'toggle', id:id, status:status}, function(res){
            if(res.code !== 0){ Swal.fire('失败', res.msg, 'error'); return; }
            Swal.fire({title:res.msg, icon:'success', timer:1200, showConfirmButton:false});
            loadList();
        }, 'json').fail(function(){ Swal.fire('错误','网络错误','error'); });
    });
}

// 复制明文
function copyPlain(){
    if(!currentPlainKey) return;
    var ta = document.createElement('textarea');
    ta.value = currentPlainKey;
    document.body.appendChild(ta); ta.select();
    try{ document.execCommand('copy'); Swal.fire({title:'已复制', icon:'success', timer:1000, showConfirmButton:false}); }
    catch(e){ Swal.fire('提示','请手动选择密钥复制','info'); }
    document.body.removeChild(ta);
}
function closePlainModal(){
    currentPlainKey = '';
    bootstrap.Modal.getInstance(document.getElementById('plainModal')).hide();
}

function escapeHtml(s){
    if(s===null||s===undefined) return '';
    return String(s).replace(/[&<>"']/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
}

$(function(){ loadList(); });
</script>
</body>
</html>
