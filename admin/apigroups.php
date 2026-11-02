<?php
/**
 * @file apigroups.php
 * @description 接口分组管理页面，支持创建/编辑/删除分组并绑定图床接口与S3存储，供套餐关联使用
 * @author AI
 * @version 1.2.0-dev
 * @date 2026-08-04
 */
declare(strict_types=1);

ini_set("display_errors", 0);
require('../inc/lang.php');
require('../inc/common.php');
if(!isset($islogin) || $islogin!=1) exit('<script>parent.location.href="login.php";</script>');

// ========== 接口配置（统一配置源，与前端 index.php / api/upload.php 共用 get_api_config()） ==========
$apiConfig = get_api_config();

// ========== S3 存储配置（仅取 enabled==1 的，用于在分组中可选）==========
$s3Configs = array();
if(isset($conf['s3_storage_configs']) && !empty($conf['s3_storage_configs'])) {
    $decoded = json_decode($conf['s3_storage_configs'], true);
    if(is_array($decoded)) {
        foreach($decoded as &$s3cfg) {
            if(isset($s3cfg['secret_key'])) {
                $s3cfg['secret_key'] = ct_decrypt($s3cfg['secret_key']);
            }
        }
        unset($s3cfg);
        foreach($decoded as $i => $s3) {
            if(isset($s3['enabled']) && $s3['enabled'] === '1') {
                $s3Configs[$i] = $s3;
            }
        }
    }
}

/**
 * 保存分组与接口的绑定关系（先清空旧绑定，再写入新绑定）
 * @param DB    $DB       数据库对象
 * @param int   $groupId  分组ID
 * @param array $post     $_POST 数据
 */
function saveGroupItems($DB, $groupId, $post) {
    $groupId = (int)$groupId;
    // H6 修复：$apiConfig 在全局作用域定义，函数内须 global 引入，否则 array_keys() 触发致命错误
    //         历史缺陷：原代码在此处未声明 global，函数会在 DELETE 之后崩溃，导致旧绑定被清空却无法写入新绑定
    global $apiConfig;
    $validKeys = is_array($apiConfig) ? array_keys($apiConfig) : array();

    // H6 修复：事务保护「先删后插」原子性，任一 INSERT 失败则回滚，避免数据丢失
    $txOk = @$DB->begin_transaction();
    try {
        $delOk = pkg_safe_query_prepared($DB, "DELETE FROM eecms_api_group_items WHERE group_id=?", 'i', [$groupId]);
        if($delOk === false) {
            if($txOk) @$DB->rollback();
            error_log('[apigroups] saveGroupItems DELETE failed: group=' . $groupId . ' err=' . $DB->error());
            return false;
        }

        // 图床接口绑定
        $apis = isset($post['apis']) && is_array($post['apis']) ? $post['apis'] : array();
        // H13 修复：从 $apiConfig 动态提取合法 key，避免硬编码白名单与配置不同步
        foreach($apis as $api) {
            if(!in_array($api, $validKeys, true)) continue;
            $insOk = $DB->query_prepared("INSERT INTO eecms_api_group_items (group_id, api_type, is_s3, s3_id) VALUES (?, ?, 0, 0)", 'is', [$groupId, $api]);
            if($insOk === false) {
                if($txOk) @$DB->rollback();
                error_log('[apigroups] saveGroupItems INSERT api failed: group=' . $groupId . ' api=' . $api . ' err=' . $DB->error());
                return false;
            }
        }

        // S3 存储绑定（s3_id 即为 S3 配置在 JSON 数组中的索引）
        $s3ids = isset($post['s3_ids']) && is_array($post['s3_ids']) ? $post['s3_ids'] : array();
        foreach($s3ids as $sid) {
            $sid = (int)$sid;
            if($sid < 0) continue;
            $apiType = 's3:'.$sid;
            $insOk = $DB->query_prepared("INSERT INTO eecms_api_group_items (group_id, api_type, is_s3, s3_id) VALUES (?, ?, 1, ?)", 'isi', [$groupId, $apiType, $sid]);
            if($insOk === false) {
                if($txOk) @$DB->rollback();
                error_log('[apigroups] saveGroupItems INSERT s3 failed: group=' . $groupId . ' s3_id=' . $sid . ' err=' . $DB->error());
                return false;
            }
        }

        if($txOk) @$DB->commit();
        return true;
    } catch(Throwable $e) {
        if($txOk) @$DB->rollback();
        error_log('[apigroups] saveGroupItems failed: group=' . $groupId . ' err=' . $e->getMessage());
        return false;
    }
}

/**
 * 统计分组中"当前有效（已启用）"与"全部绑定"的接口数量
 * - 图床接口：检查 eecms_config 中 api_<key>_enable 是否为 1
 * - S3 存储：检查 s3_storage_configs 中对应索引的 enabled 是否为 1
 * 当接口在「接口管理」中被关闭后，虽仍存在于 eecms_api_group_items，
 * 但不再计入有效数量，确保分组列表显示的数量与实际可用接口同步
 *
 * @param DB    $DB         数据库对象
 * @param int   $groupId    分组ID
 * @param array $conf       全局配置
 * @param array $s3Configs   仅含已启用 S3 配置的数组（键为索引）
 * @return array ['enabled'=>int, 'total'=>int]
 */
function count_group_items_by_status($DB, $groupId, $conf, $s3Configs) {
    $groupId = (int)$groupId;
    $items = pkg_safe_get_all_prepared($DB, "SELECT * FROM eecms_api_group_items WHERE group_id=?", 'i', [$groupId]);
    $enabled = 0;
    $total = count($items);
    foreach($items as $item) {
        if((int)$item['is_s3'] === 1) {
            $s3Id = (int)$item['s3_id'];
            // $s3Configs 已过滤为仅 enabled==1 的条目
            if(isset($s3Configs[$s3Id])) $enabled++;
        } else {
            if(api_config_enabled($conf, $item['api_type'])) $enabled++;
        }
    }
    return array('enabled' => $enabled, 'total' => $total);
}

// ========== AJAX 接口（统一返回 JSON）==========
if(isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if(!csrf_verify()) {
        echo json_encode(array('code'=>1, 'msg'=>'安全校验失败，请刷新页面后重试！'));
        exit;
    }
    $action = $_POST['action'];

    // ---- list：返回所有分组列表 ----
    if($action === 'list') {
        $listRows = pkg_safe_get_all($DB, "SELECT * FROM eecms_api_groups ORDER BY id DESC");
        $list = array();
        foreach($listRows as $row) {
            $cnt = count_group_items_by_status($DB, $row['id'], $conf, $s3Configs);
            $list[] = array(
                'id'          => (int)$row['id'],
                'name'        => $row['name'],
                'description' => $row['description'],
                'items_count' => $cnt['enabled'],
                'items_total' => $cnt['total'],
                'created_at'  => $row['created_at'],
            );
        }
        echo json_encode(array('code'=>0, 'data'=>$list));
        exit;
    }

    // ---- create：创建分组（name, description），可选同时保存接口绑定 ----
    if($action === 'create') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        if($name === '') {
            echo json_encode(array('code'=>1, 'msg'=>'分组名称不能为空'));
            exit;
        }
        if(function_exists('mb_strlen') && mb_strlen($name, 'UTF-8') > 64) {
            echo json_encode(array('code'=>1, 'msg'=>'分组名称不能超过 64 个字符'));
            exit;
        }
        $newId = $DB->insert_prepared("INSERT INTO eecms_api_groups (name, description) VALUES (?, ?)", 'ss', [$name, $description]);
        if(!$newId) {
            // M1 修复：不向前端暴露数据库错误细节，仅记录到服务端日志
            error_log('apigroups create failed: ' . $DB->error());
            echo json_encode(array('code'=>1, 'msg'=>'分组创建失败，请重试'));
            exit;
        }
        // 若提交了接口绑定关系，一并保存（binding_payload 用于标识"提交了空绑定"）
        if(isset($_POST['apis']) || isset($_POST['s3_ids']) || isset($_POST['binding_payload'])) {
            if(saveGroupItems($DB, $newId, $_POST) === false) {
                echo json_encode(array('code'=>1, 'msg'=>'接口绑定保存失败，请重试'));
                exit;
            }
        }
        $cnt = count_group_items_by_status($DB, $newId, $conf, $s3Configs);
        echo json_encode(array('code'=>0, 'msg'=>'分组创建成功', 'id'=>(int)$newId, 'items_count'=>$cnt['enabled'], 'items_total'=>$cnt['total']));
        exit;
    }

    // ---- update：编辑分组名称和描述，可选同时更新接口绑定 ----
    if($action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        if($id <= 0) {
            echo json_encode(array('code'=>1, 'msg'=>'参数错误'));
            exit;
        }
        if($name === '') {
            echo json_encode(array('code'=>1, 'msg'=>'分组名称不能为空'));
            exit;
        }
        if($DB->query_prepared("UPDATE eecms_api_groups SET name=?, description=? WHERE id=?", 'ssi', [$name, $description, $id]) === false) {
            echo json_encode(array('code'=>1, 'msg'=>'分组更新失败，请重试！')); exit;
        }
        if(isset($_POST['apis']) || isset($_POST['s3_ids']) || isset($_POST['binding_payload'])) {
            if(saveGroupItems($DB, $id, $_POST) === false) {
                echo json_encode(array('code'=>1, 'msg'=>'接口绑定保存失败，请重试'));
                exit;
            }
        }
        $cnt = count_group_items_by_status($DB, $id, $conf, $s3Configs);
        echo json_encode(array('code'=>0, 'msg'=>'分组更新成功', 'items_count'=>$cnt['enabled'], 'items_total'=>$cnt['total']));
        exit;
    }

    // ---- delete：删除分组（需检查是否有套餐正在绑定此分组）----
    if($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if($id <= 0) {
            echo json_encode(array('code'=>1, 'msg'=>'参数错误'));
            exit;
        }
        // 存在性校验：避免删除不存在的分组也返回成功
        $grpExists = pkg_safe_get_row_prepared($DB, "SELECT id FROM eecms_api_groups WHERE id=?", 'i', [$id]);
        if(!$grpExists) {
            echo json_encode(array('code'=>1, 'msg'=>'分组不存在或已被删除，请刷新后重试'));
            exit;
        }
        // 检查是否有启用的套餐正在使用此分组
        $pkgRow = pkg_safe_get_row_prepared($DB, "SELECT COUNT(*) AS c FROM eecms_packages WHERE group_id=? AND status=1", 'i', [$id]);
        $pkgCount = $pkgRow ? (int)$pkgRow['c'] : 0;
        if($pkgCount > 0) {
            echo json_encode(array('code'=>1, 'msg'=>'有 '.$pkgCount.' 个启用的套餐正在使用此分组，无法删除。请先修改相关套餐的分组绑定。'));
            exit;
        }
        pkg_safe_query_prepared($DB, "DELETE FROM eecms_api_group_items WHERE group_id=?", 'i', [$id]);
        pkg_safe_query_prepared($DB, "DELETE FROM eecms_api_groups WHERE id=?", 'i', [$id]);
        echo json_encode(array('code'=>0, 'msg'=>'分组已删除'));
        exit;
    }

    // ---- save_items：保存分组的接口绑定（group_id, apis[], s3_ids[]）----
    if($action === 'save_items') {
        $id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
        if($id <= 0) {
            echo json_encode(array('code'=>1, 'msg'=>'参数错误'));
            exit;
        }
        $exists = pkg_safe_get_row_prepared($DB, "SELECT id FROM eecms_api_groups WHERE id=?", 'i', [$id]);
        if(!$exists) {
            echo json_encode(array('code'=>1, 'msg'=>'分组不存在'));
            exit;
        }
        if(saveGroupItems($DB, $id, $_POST) === false) {
            echo json_encode(array('code'=>1, 'msg'=>'接口绑定保存失败，请重试'));
            exit;
        }
        $cnt = count_group_items_by_status($DB, $id, $conf, $s3Configs);
        echo json_encode(array('code'=>0, 'msg'=>'接口绑定已保存', 'items_count'=>$cnt['enabled'], 'items_total'=>$cnt['total']));
        exit;
    }

    // ---- get_items：返回指定分组的接口列表 ----
    if($action === 'get_items') {
        $id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
        if($id <= 0) {
            echo json_encode(array('code'=>1, 'msg'=>'参数错误'));
            exit;
        }
        $itemRows = pkg_safe_get_all_prepared($DB, "SELECT * FROM eecms_api_group_items WHERE group_id=?", 'i', [$id]);
        $apis = array();
        $s3ids = array();
        foreach($itemRows as $row) {
            if($row['is_s3']) {
                $s3ids[] = (int)$row['s3_id'];
            } else {
                $apis[] = $row['api_type'];
            }
        }
        echo json_encode(array('code'=>0, 'apis'=>$apis, 's3_ids'=>$s3ids));
        exit;
    }

    // ---- get_items_with_status：实时获取接口启用状态 + 分组绑定关系（单次请求）----
    if($action === 'get_items_with_status') {
        $id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

        // 图床接口实时状态
        $apiStatuses = array();
        foreach($apiConfig as $key => $cfg) {
            $apiStatuses[] = array(
                'key'     => (string)$key,
                'name'    => $cfg['name'],
                'enabled' => api_config_enabled($conf, (string)$key),
            );
        }

        // S3 存储列表（仅已启用的）
        $s3List = array();
        if(isset($conf['s3_storage_configs']) && !empty($conf['s3_storage_configs'])) {
            $decoded = json_decode($conf['s3_storage_configs'], true);
            if(is_array($decoded)) {
                foreach($decoded as $i => $s3) {
                    if(isset($s3['enabled']) && $s3['enabled'] === '1') {
                        $s3List[] = array(
                            'id'     => (int)$i,
                            'name'   => isset($s3['name']) ? $s3['name'] : ('S3-'.$i),
                            'bucket' => isset($s3['bucket']) ? $s3['bucket'] : '',
                        );
                    }
                }
            }
        }

        // 当前分组已绑定的接口
        $boundApis = array();
        $boundS3ids = array();
        if($id > 0) {
            $itemRows = pkg_safe_get_all_prepared($DB, "SELECT * FROM eecms_api_group_items WHERE group_id=?", 'i', [$id]);
            foreach($itemRows as $row) {
                if($row['is_s3']) {
                    $boundS3ids[] = (int)$row['s3_id'];
                } else {
                    $boundApis[] = $row['api_type'];
                }
            }
        }

        echo json_encode(array(
            'code'         => 0,
            'apis'         => $apiStatuses,
            's3_list'      => $s3List,
            'bound_apis'   => $boundApis,
            'bound_s3_ids' => $boundS3ids,
        ));
        exit;
    }

    echo json_encode(array('code'=>1, 'msg'=>'未知操作'));
    exit;
}

// 接口绑定弹窗 HTML 改为前端 JS 动态构建（buildItemsHtml），确保每次打开弹窗时
// 通过 AJAX 获取最新的接口启用状态，而非使用页面加载时的静态快照
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>接口分组管理 - <?php echo $lang->admin->title;?></title>
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
    <i class="mdi mdi-folder-multiple-outline"></i> 接口分组管理
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title" style="display:flex;justify-content:space-between;align-items:center;width:100%;">
        <span><i class="mdi mdi-format-list-group"></i> 接口分组列表</span>
        <button type="button" class="btn btn-primary btn-sm" onclick="openGroupModal(0)"><i class="mdi mdi-plus"></i> 新增分组</button>
      </div>
    </div>
    <div class="card-body" style="padding:20px;">
      <div class="group-toolbar">
        <div class="group-stat">共 <strong id="groupTotal">0</strong> 个分组 · 套餐通过分组决定用户可用的图床接口</div>
      </div>
      <div id="groupsContainer">
        <div class="empty-state">
          <i class="mdi mdi-loading mdi-spin"></i>
          <p>加载中...</p>
        </div>
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:16px;">
    <div class="card-header">
      <div class="card-title"><i class="mdi mdi-help-circle-outline"></i> 使用说明</div>
    </div>
    <div class="card-body">
      <div style="font-size:13px;color:var(--admin-text-muted);line-height:1.9;">
        <p style="margin-bottom:6px;"><i class="mdi mdi-chevron-right text-primary"></i> <strong>接口分组</strong>：将多个图床接口/S3 存储组合为一个分组，供套餐绑定使用。</p>
        <p style="margin-bottom:6px;"><i class="mdi mdi-chevron-right text-primary"></i> <strong>管理接口</strong>：勾选该分组包含的图床接口与 S3 存储，只有已启用的接口才可勾选。</p>
        <p style="margin-bottom:6px;"><i class="mdi mdi-chevron-right text-primary"></i> <strong>删除限制</strong>：若有启用的套餐正在使用该分组，需先修改套餐绑定后才能删除。</p>
        <p style="margin-bottom:0;"><i class="mdi mdi-chevron-right text-primary"></i> <strong>套餐关联</strong>：在「套餐管理」中将套餐的 group_id 指向此处创建的分组即可生效。</p>
      </div>
    </div>
  </div>

</div>

<!-- 新增/编辑分组 Modal（与其他页面保持一致的 Bootstrap Modal） -->
<div class="modal fade" id="groupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="groupModalTitle"><i class="mdi mdi-folder-plus-outline"></i> 新增分组</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
      </div>
      <div class="modal-body">
        <form id="groupForm">
          <input type="hidden" id="groupId">
          <div class="mb-3">
            <label class="form-label">分组名称</label>
            <input type="text" class="form-control" id="groupName" placeholder="如：免费用户可用接口" maxlength="64" required>
          </div>
          <div class="mb-0">
            <label class="form-label">分组描述（可选）</label>
            <textarea class="form-control" id="groupDesc" placeholder="描述该分组的用途，便于区分" maxlength="255" rows="3"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
        <button type="button" class="btn btn-primary" onclick="saveGroup()"><i class="mdi mdi-content-save"></i> 保存</button>
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
var groupsData = [];

// HTML 转义（用于安全渲染用户输入内容）
function escapeHtml(s){
    if(s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// 根据 AJAX 返回的实时接口状态数据，动态构建接口绑定弹窗 HTML
function buildItemsHtml(data){
    var apis = data.apis || [];
    var s3List = data.s3_list || [];
    var enabledCount = 0;
    apis.forEach(function(a){ if(a.enabled) enabledCount++; });

    var html = '<div class="items-modal-wrap">';
    // 图床接口区
    html += '<div class="items-section">';
    html += '<div class="items-section-head">';
    html += '<span class="items-section-title"><i class="mdi mdi-power-plug-outline"></i> 图床接口（共 '+apis.length+' 个，仅已启用的可选）</span>';
    html += '<span class="items-toolbar">';
    html += '<button type="button" class="btn btn-link btn-sm" onclick="itemsToggleAll(true)">全选已启用</button>';
    html += '<button type="button" class="btn btn-link btn-sm" onclick="itemsToggleAll(false)">取消全选</button>';
    html += '</span>';
    html += '</div>';
    html += '<div class="items-grid">';
    apis.forEach(function(a){
        html += '<label class="item-check'+(a.enabled ? '' : ' item-check-disabled')+'" title="'+escapeHtml(a.key)+'">';
        html += '<input type="checkbox" class="api-check" value="'+escapeHtml(a.key)+'"'+(a.enabled ? '' : ' disabled')+'>';
        html += '<span>'+escapeHtml(a.name)+'</span>';
        if(!a.enabled){
            html += '<small class="item-tag">未启用</small>';
        }
        html += '</label>';
    });
    html += '</div>';
    html += '<div class="items-stat">已启用 <strong>'+enabledCount+'</strong> / '+apis.length+' 个图床接口</div>';
    html += '</div>';
    // S3 存储区
    html += '<div class="items-section">';
    html += '<div class="items-section-head">';
    html += '<span class="items-section-title"><i class="mdi mdi-cloud-outline"></i> S3 存储配置</span>';
    html += '</div>';
    if(s3List.length === 0){
        html += '<div class="items-empty"><i class="mdi mdi-cloud-off-outline"></i> 暂无可用的 S3 存储配置（可在「S3 存储设置」中添加并启用）</div>';
    } else {
        html += '<div class="items-grid items-grid-s3">';
        s3List.forEach(function(s){
            html += '<label class="item-check">';
            html += '<input type="checkbox" class="s3-check" value="'+s.id+'">';
            html += '<span>'+escapeHtml(s.name)+'</span>';
            html += '<small class="item-tag">'+escapeHtml(s.bucket)+'</small>';
            html += '</label>';
        });
        html += '</div>';
    }
    html += '</div>';
    html += '</div>';
    return html;
}

// 轻提示（toast）
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

// 加载分组列表
function loadGroups(){
    $.ajax({
        url:'', type:'POST',
        data:{action:'list'},
        dataType:'json',
        success:function(resp){
            if(resp.code === 0){
                groupsData = resp.data || [];
                $('#groupTotal').text(groupsData.length);
                renderGroups(groupsData);
            } else {
                $('#groupsContainer').html('<div class="empty-state"><i class="mdi mdi-alert-circle-outline"></i><p>'+escapeHtml(resp.msg)+'</p></div>');
            }
        },
        error:function(){
            $('#groupsContainer').html('<div class="empty-state"><i class="mdi mdi-alert-circle-outline"></i><p>加载失败，请刷新页面重试</p></div>');
        }
    });
}

// 渲染分组卡片
function renderGroups(list){
    if(!list.length){
        $('#groupsContainer').html(
            '<div class="empty-state">'+
            '<i class="mdi mdi-folder-off-outline"></i>'+
            '<p>暂无接口分组</p>'+
            '<p style="font-size:13px;">点击右上角「新增分组」创建第一个接口分组</p>'+
            '</div>'
        );
        return;
    }
    var html = '<div class="group-grid">';
    list.forEach(function(g){
        var desc = g.description ? escapeHtml(g.description) : '<span class="text-muted">暂无描述</span>';
        html += '<div class="group-card">'+
            '<div class="group-card-head">'+
                '<div class="group-card-name">'+escapeHtml(g.name)+'</div>'+
                '<span class="group-card-badge"><i class="mdi mdi-power-plug"></i> '+(g.items_count||0)+' 个接口</span>'+
            '</div>'+
            '<div class="group-card-desc">'+desc+'</div>'+
            '<div class="group-card-meta"><i class="mdi mdi-clock-outline"></i> 创建于 '+escapeHtml(g.created_at)+'</div>'+
            '<div class="group-card-actions">'+
                '<button type="button" class="btn btn-primary btn-sm" onclick="openItemsModal('+g.id+')"><i class="mdi mdi-format-list-bulleted"></i> 管理接口</button>'+
                '<button type="button" class="btn btn-info btn-sm" onclick="openGroupModal('+g.id+')" title="编辑"><i class="mdi mdi-pencil"></i></button>'+
                '<button type="button" class="btn btn-danger btn-sm" onclick="deleteGroup('+g.id+')" title="删除"><i class="mdi mdi-delete"></i></button>'+
            '</div>'+
        '</div>';
    });
    html += '</div>';
    $('#groupsContainer').html(html);
}

// 根据ID查找分组
function findGroup(id){
    for(var i=0;i<groupsData.length;i++){
        if(groupsData[i].id == id) return groupsData[i];
    }
    return null;
}

// 新增 / 编辑分组（id=0 表示新增）
function openGroupModal(id){
    var g = id ? findGroup(id) : null;
    var isEdit = !!g;
    // 回填表单
    $('#groupId').val(isEdit ? id : '');
    $('#groupName').val(g ? g.name : '');
    $('#groupDesc').val(g ? g.description : '');
    // 标题与图标
    $('#groupModalTitle').html(isEdit
        ? '<i class="mdi mdi-folder-edit-outline"></i> 编辑分组'
        : '<i class="mdi mdi-folder-plus-outline"></i> 新增分组');
    groupModal.show();
    // 自动聚焦名称输入框
    setTimeout(function(){ $('#groupName').trigger('focus'); }, 200);
}

// 保存分组（新增/编辑）
function saveGroup(){
    var id = $('#groupId').val();
    var name = $('#groupName').val().trim();
    var desc = $('#groupDesc').val().trim();
    var isEdit = !!id;
    if(!name){
        toast('warning', '请输入分组名称');
        $('#groupName').trigger('focus');
        return;
    }
    var data = {action: isEdit ? 'update' : 'create', name:name, description:desc};
    if(isEdit) data.id = id;
    $.ajax({
        url:'', type:'POST', data:data, dataType:'json',
        success:function(resp){
            if(resp.code === 0){
                groupModal.hide();
                toast('success', resp.msg);
                loadGroups();
            } else {
                toast('error', resp.msg);
            }
        },
        error:function(){ toast('error', '网络错误，请重试'); }
    });
}

// 管理接口（弹窗：勾选图床接口 + S3 存储）
// 每次打开弹窗时通过 AJAX 实时获取接口启用状态，确保反映最新的接口管理变更
function openItemsModal(id){
    var g = findGroup(id);
    if(!g){ toast('error', '分组不存在'); return; }

    Swal.fire({
        title: '管理接口 - ' + g.name,
        html: '<div class="items-modal-wrap" style="text-align:center;padding:32px 12px;">'+
              '<i class="mdi mdi-loading mdi-spin" style="font-size:28px;color:#3b82f6;"></i>'+
              '<p style="margin-top:10px;color:#94a3b8;font-size:13px;">正在获取接口状态...</p></div>',
        width: '760px',
        showCancelButton:true,
        confirmButtonText:'保存绑定',
        cancelButtonText:'取消',
        confirmButtonColor:'#3b82f6',
        cancelButtonColor:'#94a3b8',
        reverseButtons:true,
        focusConfirm:false,
        didOpen:function(){
            // 单次 AJAX 请求同时获取：接口实时启用状态 + 当前分组的绑定关系
            $.ajax({
                url:'', type:'POST',
                data:{action:'get_items_with_status', group_id:id},
                dataType:'json',
                success:function(resp){
                    if(resp.code !== 0){
                        Swal.getHtmlContainer().innerHTML =
                            '<div style="text-align:center;padding:32px 12px;color:#ef4444;">'+
                            '<i class="mdi mdi-alert-circle-outline" style="font-size:28px;"></i>'+
                            '<p style="margin-top:8px;font-size:13px;">'+escapeHtml(resp.msg || '加载接口失败')+'</p></div>';
                        return;
                    }
                    // 用实时数据构建弹窗内容
                    var html = buildItemsHtml(resp);
                    Swal.getHtmlContainer().innerHTML = html;

                    // 回显当前分组已绑定的接口（仅勾选仍处于启用状态的）
                    var c = Swal.getHtmlContainer();
                    (resp.bound_apis || []).forEach(function(k){
                        var cb = $(c).find('input.api-check[value="'+k+'"]');
                        if(!cb.prop('disabled')) cb.prop('checked', true);
                    });
                    (resp.bound_s3_ids || []).forEach(function(s){
                        $(c).find('input.s3-check[value="'+s+'"]').prop('checked', true);
                    });
                },
                error:function(){
                    Swal.getHtmlContainer().innerHTML =
                        '<div style="text-align:center;padding:32px 12px;color:#ef4444;">'+
                        '<i class="mdi mdi-alert-circle-outline" style="font-size:28px;"></i>'+
                        '<p style="margin-top:8px;font-size:13px;">网络错误，请关闭后重试</p></div>';
                }
            });
        },
        preConfirm:function(){
            var c = Swal.getHtmlContainer();
            var apis = [];
            $(c).find('input.api-check:checked').each(function(){ apis.push($(this).val()); });
            var s3ids = [];
            $(c).find('input.s3-check:checked').each(function(){ s3ids.push($(this).val()); });
            return $.ajax({
                url:'', type:'POST',
                data:{action:'save_items', group_id:id, apis:apis, s3_ids:s3ids, binding_payload:1},
                dataType:'json'
            }).then(function(resp){
                if(resp.code !== 0){
                    Swal.showValidationMessage(resp.msg || '保存失败');
                    return false;
                }
                return resp;
            }).catch(function(){
                Swal.showValidationMessage('网络错误，请重试');
                return false;
            });
        }
    }).then(function(result){
        if(result.isConfirmed && result.value){
            toast('success', result.value.msg || '接口绑定已保存');
            loadGroups();
        }
    });
}

// 接口弹窗内：全选已启用 / 取消全选
// check=true  → 仅勾选未被 disabled 的图床接口（即"已启用"的）
// check=false → 取消所有勾选（图床接口 + S3 存储）
function itemsToggleAll(check){
    var c = Swal.getHtmlContainer();
    if(!c) return;
    if(check){
        // 全选已启用：仅勾选未禁用的 api-check
        $(c).find('input.api-check').each(function(){
            if(!$(this).prop('disabled')){
                this.checked = true;
            }
        });
    } else {
        // 取消全选：清除所有勾选
        $(c).find('input.api-check, input.s3-check').prop('checked', false);
    }
}

// 删除分组
function deleteGroup(id){
    var g = findGroup(id);
    var name = g ? g.name : '';
    Swal.fire({
        title:'确认删除',
        text:'确定要删除分组「'+name+'」吗？该分组下的接口绑定关系将一并清除，此操作不可恢复！',
        icon:'warning',
        showCancelButton:true,
        confirmButtonText:'确认删除',
        cancelButtonText:'取消',
        confirmButtonColor:'#ef4444',
        cancelButtonColor:'#94a3b8',
        reverseButtons:true
    }).then(function(result){
        if(!result.isConfirmed) return;
        $.ajax({
            url:'', type:'POST',
            data:{action:'delete', id:id},
            dataType:'json',
            success:function(resp){
                if(resp.code === 0){
                    toast('success', resp.msg);
                    loadGroups();
                } else {
                    toast('error', resp.msg, 4000);
                }
            },
            error:function(){ toast('error', '网络错误，请重试'); }
        });
    });
}

// 页面就绪后加载列表（defer 脚本在 DOMContentLoaded 前已执行，此时 $ 与 Swal 均可用）
var groupModal;
document.addEventListener('DOMContentLoaded', function(){
    groupModal = new bootstrap.Modal(document.getElementById('groupModal'));
    loadGroups();
});
</script>
</body>
</html>
