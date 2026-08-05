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

// ========== 接口配置（与前端 index.php 保持一致，用于显示可选图床接口）==========
$apiConfig = array(
    'cfbed'=>array('name'=>'自建图床', 'max_size'=>100),
    '360'=>array('name'=>'360图床', 'max_size'=>10),
    'local'=>array('name'=>'本地上传', 'max_size'=>10),
    'chevereto'=>array('name'=>'PlcGO图床', 'max_size'=>10),
    'zhongzhuan'=>array('name'=>'凌云图床', 'max_size'=>10),
    'phototourl'=>array('name'=>'PHOTO图床', 'max_size'=>10),
    'img520'=>array('name'=>'IMG520图床', 'max_size'=>10),
    'imgloc'=>array('name'=>'IMGLOC图床', 'max_size'=>10),
    'locimg'=>array('name'=>'LOC图床', 'max_size'=>10),
    'jisu'=>array('name'=>'极速图床', 'max_size'=>3),
    'yopngs'=>array('name'=>'有图床', 'max_size'=>10),
    'feria'=>array('name'=>'风筝图床', 'max_size'=>10),
    'gurl'=>array('name'=>'Telegraph图床', 'max_size'=>5),
    'ljpic'=>array('name'=>'云间图床', 'max_size'=>3),
    'nickyam'=>array('name'=>'Telegraph2图床', 'max_size'=>5),
    'dogimg'=>array('name'=>'狗狗图床', 'max_size'=>10),
    'matu'=>array('name'=>'宝马图床', 'max_size'=>10),
    'pnglog'=>array('name'=>'盘络图床', 'max_size'=>5),
    'lvse'=>array('name'=>'绿色图床', 'max_size'=>5),
    'fatcat'=>array('name'=>'肥喵图床', 'max_size'=>10),
    '131img'=>array('name'=>'131图床', 'max_size'=>20),
    'feimg'=>array('name'=>'FE图床', 'max_size'=>64),
    'yootn'=>array('name'=>'友藤图床', 'max_size'=>20),
    'czl'=>array('name'=>'CZL图床', 'max_size'=>0.5),
    'tutu'=>array('name'=>'TUTU图床', 'max_size'=>5),
    'uuimg'=>array('name'=>'悠悠图床', 'max_size'=>4997),
    'tuwu'=>array('name'=>'图屋图床', 'max_size'=>20),
    'urusai'=>array('name'=>'UR图床', 'max_size'=>64),
    'imgcc'=>array('name'=>'云图床', 'max_size'=>10),
    'imgdata'=>array('name'=>'ImgURL图床', 'max_size'=>10),
    'pngcdn'=>array('name'=>'云朵图床', 'max_size'=>10),
    'naixiai'=>array('name'=>'奶昔图床', 'max_size'=>10),
    'yiyunt'=>array('name'=>'怡云图床', 'max_size'=>10),
    'scdn'=>array('name'=>'SCDN图床', 'max_size'=>20),
    'imgbb'=>array('name'=>'ImgBB图床', 'max_size'=>32),
    'imgurla'=>array('name'=>'Imgur.LA图床', 'max_size'=>10),
    'helloimg'=>array('name'=>'Hello图床', 'max_size'=>10),
    'stardots'=>array('name'=>'StarDots图床', 'max_size'=>10),
    'remit'=>array('name'=>'Remit图床', 'max_size'=>10),
    'alibaba'=>array('name'=>'阿里巴巴图床', 'max_size'=>10),
    'beeimg'=>array('name'=>'蜜蜂图床', 'max_size'=>10),
    'meituan'=>array('name'=>'美团创作图床', 'max_size'=>10),
    'suning'=>array('name'=>'苏宁易购图床', 'max_size'=>10),
    'meipai'=>array('name'=>'美拍网图床', 'max_size'=>10),
    'alipay'=>array('name'=>'支付宝图床', 'max_size'=>10),
    'youzan'=>array('name'=>'有赞图床', 'max_size'=>10),
    'wentian'=>array('name'=>'WENTIAN', 'max_size'=>5),
);

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
    pkg_safe_query_prepared($DB, "DELETE FROM eecms_api_group_items WHERE group_id=?", 'i', [$groupId]);

    // 图床接口绑定
    $apis = isset($post['apis']) && is_array($post['apis']) ? $post['apis'] : array();
    // 仅允许存在于 $apiConfig 中的合法 key，防止越权写入任意标识
    $validKeys = array('cfbed','360','local','chevereto','zhongzhuan','phototourl','img520','imgloc','locimg','jisu','yopngs','feria','gurl','ljpic','nickyam','dogimg','matu','pnglog','lvse','fatcat','131img','feimg','yootn','czl','tutu','uuimg','tuwu','urusai','imgcc','imgdata','pngcdn','naixiai','yiyunt','scdn','imgbb','imgurla','helloimg','stardots','remit','alibaba','beeimg','meituan','suning','meipai','alipay','youzan','wentian');
    foreach($apis as $api) {
        if(!in_array($api, $validKeys, true)) continue;
        $DB->query_prepared("INSERT INTO eecms_api_group_items (group_id, api_type, is_s3, s3_id) VALUES (?, ?, 0, 0)", 'is', [$groupId, $api]);
    }

    // S3 存储绑定（s3_id 即为 S3 配置在 JSON 数组中的索引）
    $s3ids = isset($post['s3_ids']) && is_array($post['s3_ids']) ? $post['s3_ids'] : array();
    foreach($s3ids as $sid) {
        $sid = (int)$sid;
        if($sid < 0) continue;
        $apiType = 's3:'.$sid;
        $DB->query_prepared("INSERT INTO eecms_api_group_items (group_id, api_type, is_s3, s3_id) VALUES (?, ?, 1, ?)", 'isi', [$groupId, $apiType, $sid]);
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
            $enableKey = 'api_' . $item['api_type'] . '_enable';
            if(isset($conf[$enableKey]) && $conf[$enableKey] == '1') $enabled++;
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
            echo json_encode(array('code'=>1, 'msg'=>'创建失败：'.$DB->error()));
            exit;
        }
        // 若提交了接口绑定关系，一并保存
        if(isset($_POST['apis']) || isset($_POST['s3_ids'])) {
            saveGroupItems($DB, $newId, $_POST);
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
        if(isset($_POST['apis']) || isset($_POST['s3_ids'])) {
            saveGroupItems($DB, $id, $_POST);
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
        saveGroupItems($DB, $id, $_POST);
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
            $enableKey = 'api_'.$key.'_enable';
            $apiStatuses[] = array(
                'key'     => (string)$key,
                'name'    => $cfg['name'],
                'enabled' => (isset($conf[$enableKey]) && $conf[$enableKey] == '1'),
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
<style>
html,body{height:100%;margin:0}body{overflow:auto}.admin-content{padding:16px 20px!important}

/* 顶部工具条 */
.group-toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap}
.group-toolbar .group-stat{font-size:13px;color:var(--admin-text-muted)}
.group-toolbar .group-stat strong{color:var(--admin-primary);font-size:15px}

/* 分组卡片网格 */
.group-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px}
.group-card{background:var(--admin-card-bg);border:1px solid var(--admin-border);border-radius:var(--admin-radius);padding:16px;display:flex;flex-direction:column;box-shadow:var(--admin-shadow);transition:box-shadow .2s,transform .2s}
.group-card:hover{box-shadow:var(--admin-shadow-lg);transform:translateY(-2px)}
.group-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:8px}
.group-card-name{font-size:16px;font-weight:600;color:var(--admin-text);word-break:break-all;line-height:1.4}
.group-card-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;background:#eff6ff;color:#2563eb}
.group-card-desc{font-size:13px;color:var(--admin-text-muted);min-height:20px;flex:1;margin-bottom:10px;word-break:break-all;line-height:1.6}
.group-card-meta{font-size:12px;color:#94a3b8;margin-bottom:12px;display:flex;align-items:center;gap:4px}
.group-card-actions{display:flex;gap:6px;flex-wrap:wrap}

/* 接口绑定弹窗 */
.items-modal-wrap{max-height:62vh;overflow-y:auto;padding-right:6px;text-align:left}
.items-section{margin-bottom:20px}
.items-section:last-child{margin-bottom:0}
.items-section-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;border-bottom:1px solid var(--admin-border);padding-bottom:8px}
.items-section-title{font-weight:600;font-size:14px;color:var(--admin-text);display:flex;align-items:center;gap:6px}
.items-section-title i{color:var(--admin-primary);font-size:18px}
.items-toolbar{display:flex;gap:2px}
.items-toolbar .btn{padding:2px 8px;font-size:12px}
.items-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:8px}
.items-grid-s3{grid-template-columns:repeat(auto-fill,minmax(220px,1fr))}
.item-check{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--admin-border);border-radius:var(--admin-radius-sm);cursor:pointer;font-size:13px;transition:all .15s;background:#fff;user-select:none}
.item-check:hover{border-color:var(--admin-primary);background:#eff6ff}
.item-check input{margin:0;cursor:pointer;accent-color:var(--admin-primary);width:15px;height:15px;flex-shrink:0}
.item-check span{flex:1;word-break:break-all}
.item-check-disabled{opacity:.55;cursor:not-allowed;background:#f8fafc}
.item-check-disabled:hover{border-color:var(--admin-border);background:#f8fafc}
.item-check-disabled input{cursor:not-allowed}
.item-tag{font-size:11px;color:var(--admin-text-muted);background:#f1f5f9;padding:1px 6px;border-radius:4px;white-space:nowrap}
.items-stat{font-size:12px;color:var(--admin-text-muted);margin-top:10px;text-align:right}
.items-empty{text-align:center;padding:24px 12px;color:var(--admin-text-muted);font-size:13px;border:1px dashed var(--admin-border);border-radius:var(--admin-radius-sm)}
.items-empty i{font-size:28px;display:block;margin-bottom:8px;color:#cbd5e1}
</style>
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

<script defer src="style/js/jquery.min.js"></script>
<script defer src="style/js/bootstrap.min.js"></script>
<script defer src="style/js/sweetalert2.min.js"></script>
<script>
var CSRF_TOKEN = '<?php echo csrf_token();?>';
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
        data:{action:'list', csrf_token:CSRF_TOKEN},
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
    var data = {action: isEdit ? 'update' : 'create', csrf_token:CSRF_TOKEN, name:name, description:desc};
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
                data:{action:'get_items_with_status', group_id:id, csrf_token:CSRF_TOKEN},
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
                data:{action:'save_items', group_id:id, apis:apis, s3_ids:s3ids, csrf_token:CSRF_TOKEN},
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
            data:{action:'delete', id:id, csrf_token:CSRF_TOKEN},
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
