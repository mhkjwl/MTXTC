<?php
/**
 * @file upimg.php
 * @description Favicon图标上传页面，支持上传.ico格式的浏览器标签小图标并覆盖根目录favicon.ico
 * @author AI
 * @version 1.1.0
 * @date 2026-08-04
 */
declare(strict_types=1);

ini_set("display_errors", 0);
require('../inc/lang.php');
require('../inc/common.php');
if(!isset($islogin) || $islogin!=1) exit('<script>parent.location.href="login.php";</script>');

$toast_icon = ''; $toast_title = '';
if(isset($_POST['submit1'])){
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if($isAjax){ header('Content-Type: application/json; charset=utf-8'); }

    if(!csrf_verify()){
        if($isAjax){ echo json_encode(['code'=>1, 'msg'=>'安全校验失败，请刷新页面后重试！']); exit; }
        $toast_icon = 'error'; $toast_title = '安全校验失败，请刷新页面后重试！';
    } else {
        $tmpName = $_FILES['favicon']['tmp_name'] ?? '';
        $fileName = $_FILES['favicon']['name'] ?? '';
        $fileSize = $_FILES['favicon']['size'] ?? 0;
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!is_uploaded_file($tmpName)) {
            $msg = '非法上传请求！';
        } elseif ($ext !== 'ico') {
            $msg = '仅允许上传 .ico 格式的图标文件！';
        } elseif ($fileSize > 102400) {
            $msg = '文件大小不能超过 100KB！';
        } elseif (move_uploaded_file($tmpName, "../favicon.ico")) {
            $msg = 'favicon图标上传成功！';
            if($isAjax){ echo json_encode(['code'=>0, 'msg'=>$msg]); exit; }
            $toast_icon = 'success'; $toast_title = $msg;
        } else {
            $msg = '上传失败！请检查根目录写入权限。';
        }
        // 失败分支统一处理（成功分支已 exit 或已置 success）
        if($toast_icon === ''){
            if($isAjax){ echo json_encode(['code'=>1, 'msg'=>$msg]); exit; }
            $toast_icon = 'error'; $toast_title = $msg;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LOGO修改 - <?php echo $lang->admin->title;?></title>
<link rel="preload" href="style/fonts/materialdesignicons-webfont.woff2?v=6.5.95" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="style/css/bootstrap.min.css">
<link rel="stylesheet" href="style/css/materialdesignicons.min.css">
<link rel="stylesheet" href="style/css/admin.css">
<style>html,body{height:100%;margin:0}body{overflow:auto}.admin-content{padding:16px 20px!important}</style>
<link rel="stylesheet" href="style/css/sweetalert2.min.css">
</head>
<body>
<div class="admin-content">

  <div class="page-title">
    <i class="mdi mdi-image-outline"></i> <?php echo $lang->admin->favicon;?>（浏览器小图标）
  </div>

  <div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="mdi mdi-image-outline"></i> 上传 Favicon 图标</div>
        </div>
        <div class="card-body">
          <div class="alert alert-info"><i class="mdi mdi-information-outline"></i> 仅支持上传 .ico 图标文件，大小不超过 100KB。请确保根目录可写入。</div>
          <form id="mainForm" method="post" enctype="multipart/form-data">
            <div class="mb-3">
              <label class="form-label">选择 ICO 文件</label>
              <input type="file" class="form-control" name="favicon" id="faviconFile" accept=".ico" style="max-width:400px;">
            </div>
            <div class="mb-4">
              <label class="form-label">当前图标</label>
              <div style="padding:12px;background:#f8fafc;border-radius:8px;display:inline-block;">
                <img id="faviconPreview" src="../favicon.ico?v=<?php echo time();?>" style="width:32px;height:32px;">
              </div>
            </div>
            <button type="button" class="btn btn-primary" onclick="doSave()"><i class="mdi mdi-upload"></i> 上传图标</button>
            <input type="hidden" name="submit1" value="1">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token();?>">
          </form>
        </div>
      </div>
    </div>
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

function doSave(){
    var file = document.getElementById('faviconFile');
    if(!file.value){
        toast('warning', '请先选择ICO文件！', 3000);
        return;
    }
    var ext = file.value.split('.').pop().toLowerCase();
    if(ext !== 'ico'){
        toast('error', '仅允许上传 .ico 格式的文件！', 3000);
        return;
    }
    Swal.fire({
        title: '确认上传',
        text: '确定要更新favicon图标吗？将会覆盖当前图标。',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '确认上传',
        cancelButtonText: '取消',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#94a3b8',
        reverseButtons: true
    }).then(function(result){
        if(!result.isConfirmed) return;
        var formData = new FormData(document.getElementById('mainForm'));
        fetch('upimg.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(response){ return response.json(); })
        .then(function(data){
            toast(data.code === 0 ? 'success' : 'error', data.msg, 3000);
            if(data.code === 0){
                // 无刷新更新预览图（新时间戳破缓存）
                var preview = document.getElementById('faviconPreview');
                if(preview){ preview.src = '../favicon.ico?v=' + new Date().getTime(); }
                // 清空文件选择，便于再次上传
                document.getElementById('faviconFile').value = '';
            }
        })
        .catch(function(){
            toast('error', '网络错误，请重试', 3000);
        });
    });
}
</script>
<?php if($toast_title): ?>
<script>
toast(<?php echo json_encode($toast_icon);?>, <?php echo json_encode($toast_title, JSON_UNESCAPED_UNICODE);?>, 3000);
</script>
<?php endif; ?>
</body>
</html>