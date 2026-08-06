<?php
/**
 * @file changelog.php
 * @description 后台更新日志页面，解析 docs/版本日志.md 并以卡片时间线形式展示各版本变更记录
 * @author AI
 * @version 1.3.6-dev
 * @date 2026-08-05
 */
declare(strict_types=1);

ini_set("display_errors", 0);
require('../inc/lang.php');
require('../inc/common.php');
if(!isset($islogin) || $islogin!=1) exit('<script>parent.location.href="login.php";</script>');

/**
 * 解析版本日志 Markdown 文件为结构化数组
 *
 * @param string $filePath 版本日志文件路径
 * @return array<int,array{version:string,date:string,fields:array<string,string>,details:array<int,array{file:string,op:string,desc:string}>}>
 */
function parse_changelog(string $filePath): array
{
    if(!file_exists($filePath)){
        return [];
    }
    $content = file_get_contents($filePath);
    if($content === false || $content === ''){
        return [];
    }

    // 按 ## [x.y.z] - YYYY-MM-DD 切分条目
    $pattern = '/^##\s*\[([^\]]+)\]\s*-\s*(\d{4}-\d{2}-\d{2})/m';
    if(!preg_match_all($pattern, $content, $headers, PREG_OFFSET_CAPTURE)){
        return [];
    }

    $entries = [];
    $headerCount = count($headers[0]);
    for($i = 0; $i < $headerCount; $i++){
        $version = $headers[1][$i][0];
        $date    = $headers[2][$i][0];
        $start   = $headers[0][$i][1] + strlen($headers[0][$i][0]);
        $end     = ($i + 1 < $headerCount) ? $headers[0][$i + 1][1] : strlen($content);
        $block   = substr($content, $start, $end - $start);

        // 提取粗体字段
        $fields = [];
        $fieldNames = ['变更类型', '变更摘要', '影响范围', '操作方', '审批状态', '备份位置', '回退方案'];
        foreach($fieldNames as $name){
            if(preg_match('/-\s*\*\*' . preg_quote($name, '/') . '\*\*：(.+)/', $block, $m)){
                $fields[$name] = trim($m[1]);
            }else{
                $fields[$name] = '-';
            }
        }

        // 解析变更明细表格
        $details = [];
        if(preg_match('/###\s*变更明细(.+)$/s', $block, $tableBlock)){
            $tableText = $tableBlock[1];
            // 匹配表格行（跳过表头和分隔行）
            $lines = preg_split('/\n/', $tableText);
            foreach($lines as $line){
                $line = trim($line);
                if($line === '' || strpos($line, '|') !== 0){
                    continue;
                }
                // 跳过分隔行 |---|---|---|
                if(preg_match('/^\|[\s\-:|]+\|$/', $line)){
                    continue;
                }
                $cells = array_map('trim', explode('|', trim($line, '|')));
                if(count($cells) >= 3){
                    $details[] = [
                        'file' => $cells[0],
                        'op'   => $cells[1],
                        'desc' => $cells[2],
                    ];
                }
            }
        }

        $entries[] = [
            'version' => $version,
            'date'    => $date,
            'fields'  => $fields,
            'details' => $details,
        ];
    }

    // 按版本号倒序排列（最新在最上）
    usort($entries, function($a, $b){
        return version_compare($b['version'], $a['version']);
    });

    return $entries;
}

$changelogPath = dirname(__DIR__) . '/docs/版本日志.md';
$entries = parse_changelog($changelogPath);
$totalCount = count($entries);
$latestVersion = $totalCount > 0 ? $entries[0]['version'] : '-';
$latestDate = $totalCount > 0 ? $entries[0]['date'] : '-';

/**
 * 根据变更类型返回 Bootstrap 徽章 class
 *
 * @param string $type 变更类型
 * @return string 徽章 class
 */
function get_type_badge_class(string $type): string
{
    $typeLower = mb_strtolower($type, 'UTF-8');
    // L15 修复：中文用 mb_strpos 避免多字节字符匹配错误
    if(strpos($typeLower, 'bug') !== false || mb_strpos($type, '修复', 0, 'UTF-8') !== false){
        return 'bg-danger';
    }
    if(mb_strpos($type, '新增', 0, 'UTF-8') !== false){
        return 'bg-success';
    }
    if(mb_strpos($type, '优化', 0, 'UTF-8') !== false || mb_strpos($type, '改进', 0, 'UTF-8') !== false){
        return 'bg-primary';
    }
    return 'bg-secondary';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>更新日志 - <?php echo $lang->admin->title;?></title>
<link rel="preload" href="style/fonts/materialdesignicons-webfont.woff2?v=6.5.95" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="style/css/bootstrap.min.css">
<link rel="stylesheet" href="style/css/materialdesignicons.min.css">
<link rel="stylesheet" href="style/css/admin.css">
<style>
html,body{height:100%;margin:0}body{overflow:auto}.admin-content{padding:16px 20px!important}
.changelog-summary{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px}
.summary-card{flex:1;min-width:140px;padding:14px 18px;border-radius:var(--admin-radius);background:var(--admin-card-bg);box-shadow:var(--admin-shadow);border:1px solid var(--admin-border)}
.summary-card .label{font-size:0.8rem;color:var(--admin-text-muted);margin-bottom:4px}
.summary-card .value{font-size:1.3rem;font-weight:700;color:var(--admin-text)}
.version-card{margin-bottom:16px;border:1px solid var(--admin-border);border-radius:var(--admin-radius);overflow:hidden;background:var(--admin-card-bg);box-shadow:var(--admin-shadow)}
.version-card .card-header{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:14px 18px;background:rgba(59,130,246,0.04);border-bottom:1px solid var(--admin-border)}
.version-card .ver-num{font-size:1.25rem;font-weight:700;color:var(--admin-text);font-family:"JetBrains Mono",monospace}
.version-card .ver-date{font-size:0.86rem;color:var(--admin-text-muted)}
.version-card .ver-badge{font-size:0.78rem;font-weight:600;padding:4px 10px;border-radius:999px}
.version-card .card-body{padding:16px 18px}
.version-card .field-row{display:flex;gap:8px;margin-bottom:8px;font-size:0.9rem}
.version-card .field-label{color:var(--admin-text-muted);flex-shrink:0;min-width:80px}
.version-card .field-value{color:var(--admin-text);word-break:break-word}
.version-card .field-value code{background:rgba(59,130,246,0.08);padding:1px 6px;border-radius:4px;font-size:0.84rem;color:var(--admin-primary)}
.version-card .details-title{font-size:0.86rem;font-weight:600;color:var(--admin-text-muted);margin:14px 0 8px}
.version-card .rollback{margin-top:12px;padding:10px 14px;background:rgba(255,193,87,0.08);border:1px solid rgba(255,193,87,0.2);border-radius:var(--admin-radius-sm)}
.version-card .rollback summary{cursor:pointer;font-size:0.84rem;font-weight:600;color:#d9a441;outline:none;user-select:none}
.version-card .rollback summary i{margin-right:4px}
.version-card .rollback-body{margin-top:8px;font-size:0.84rem;color:var(--admin-text);line-height:1.6}
.empty-state{text-align:center;padding:80px 20px;color:var(--admin-text-muted)}
.empty-state i{font-size:64px;display:block;margin-bottom:16px;opacity:0.4}
.empty-state h4{color:var(--admin-text-muted);margin-bottom:8px}
</style>
</head>
<body>
<div class="admin-content">

  <div class="page-title">
    <i class="mdi mdi-history"></i> 更新日志
  </div>

<?php if($totalCount === 0): ?>
  <div class="empty-state">
    <i class="mdi mdi-file-document-outline"></i>
    <h4>暂无更新日志</h4>
    <p>版本日志文件不存在或尚未记录任何版本变更</p>
  </div>
<?php else: ?>
  <!-- 顶部统计 -->
  <div class="changelog-summary">
    <div class="summary-card">
      <div class="label">总版本数</div>
      <div class="value"><?php echo $totalCount; ?></div>
    </div>
    <div class="summary-card">
      <div class="label">最新版本</div>
      <div class="value"><?php echo htmlspecialchars($latestVersion, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="summary-card">
      <div class="label">最新更新</div>
      <div class="value"><?php echo htmlspecialchars($latestDate, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  </div>

  <!-- 版本卡片列表 -->
  <?php foreach($entries as $entry):
      $version = htmlspecialchars($entry['version'], ENT_QUOTES, 'UTF-8');
      $date    = htmlspecialchars($entry['date'], ENT_QUOTES, 'UTF-8');
      $fields  = $entry['fields'];
      $type    = $fields['变更类型'];
      $badgeClass = get_type_badge_class($type);
      $typeBadge = '<span class="ver-badge ' . $badgeClass . '">' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '</span>';
  ?>
  <div class="version-card">
    <div class="card-header">
      <span class="ver-num">v<?php echo $version; ?></span>
      <span class="ver-date"><i class="mdi mdi-calendar-outline"></i> <?php echo $date; ?></span>
      <?php echo $typeBadge; ?>
      <?php if($fields['审批状态'] !== '-'): ?>
        <span class="ver-badge bg-<?php echo $fields['审批状态'] === '已同意' ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($fields['审批状态'], ENT_QUOTES, 'UTF-8'); ?></span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <div class="field-row">
        <span class="field-label"><i class="mdi mdi-text"></i> 变更摘要</span>
        <span class="field-value"><?php echo htmlspecialchars($fields['变更摘要'], ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
      <div class="field-row">
        <span class="field-label"><i class="mdi mdi-folder-outline"></i> 影响范围</span>
        <span class="field-value"><code><?php echo htmlspecialchars($fields['影响范围'], ENT_QUOTES, 'UTF-8'); ?></code></span>
      </div>
      <div class="field-row">
        <span class="field-label"><i class="mdi mdi-account"></i> 操作方</span>
        <span class="field-value"><?php echo htmlspecialchars($fields['操作方'], ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
      <?php if($fields['备份位置'] !== '-'): ?>
      <div class="field-row">
        <span class="field-label"><i class="mdi mdi-folder-zip-outline"></i> 备份位置</span>
        <span class="field-value"><code><?php echo htmlspecialchars($fields['备份位置'], ENT_QUOTES, 'UTF-8'); ?></code></span>
      </div>
      <?php endif; ?>

      <?php if(!empty($entry['details'])): ?>
      <div class="details-title"><i class="mdi mdi-format-list-bulleted"></i> 变更明细</div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:30%">文件</th>
              <th style="width:15%">操作</th>
              <th>说明</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($entry['details'] as $detail): ?>
            <tr>
              <td><code><?php echo htmlspecialchars($detail['file'], ENT_QUOTES, 'UTF-8'); ?></code></td>
              <td><?php echo htmlspecialchars($detail['op'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($detail['desc'], ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <?php if($fields['回退方案'] !== '-'): ?>
      <details class="rollback">
        <summary><i class="mdi mdi-arrow-undo-circle-outline"></i> 回退方案</summary>
        <div class="rollback-body"><?php echo htmlspecialchars($fields['回退方案'], ENT_QUOTES, 'UTF-8'); ?></div>
      </details>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

</div>
</body>
</html>
