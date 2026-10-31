<?php
/**
 * @file api/img520.php
 * @description img520图床上传适配器（img520.com）
 * @author AI
 * @version 1.1.0-dev
 * @date 2026-08-04
 */
declare(strict_types=1);

if(!defined("UPLOAD_GATE")){header("HTTP/1.1 403 Forbidden");exit("Forbidden");}
require ('../inc/lang.php');
require ('../inc/common.php');
header("Content-type:application/json");

$file = $_FILES["file"]["name"] ?? '';
if (empty($file)) {
    echo json_encode(["code" => 201, "msg" => "未收到上传文件！"]); exit;
}

$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$fileType = $_FILES["file"]["type"] ?? '';
$allowedTypes = ["image/gif", "image/jpeg", "image/jpg", "image/pjpeg", "image/x-png", "image/png", "image/webp", "image/bmp"];

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(["code" => 201, "msg" => "只允许上传gif、jpeg、jpg、png、webp、bmp格式的图片文件！"]); exit;
}

if ($_FILES["file"]["size"] > 10 * 1024 * 1024) {
    echo json_encode(["code" => 201, "msg" => "文件大小超出限制！最大只能上传10MB的文件！"]); exit;
}

$uploadDir = 'upload';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

$tmpName = uniqid() . '.' . $extension;
move_uploaded_file($_FILES["file"]["tmp_name"], $uploadDir . "/" . $tmpName);
$filepath = realpath(dirname(__FILE__)) . "/" . $uploadDir . "/" . $tmpName;

// IMG520 API v1 上传接口（游客模式，无需 Token）
$endpoint = 'https://img520.com/api/v1/upload';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $endpoint,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['file' => new CURLFile($filepath, $fileType, random_name(12).'.'.$extension)],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => is_file(dirname(__DIR__)."/inc/cacert.pem"),
    CURLOPT_SSL_VERIFYHOST => is_file(dirname(__DIR__)."/inc/cacert.pem") ? 2 : 0,
    CURLOPT_CAINFO => dirname(__DIR__)."/inc/cacert.pem",
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
    ],
]);

$uploadimg = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
@unlink($filepath);

if ($uploadimg === false || $uploadimg === '') {
    echo json_encode(["code" => 201, "msg" => "连接img520.com失败：" . ($curlErr ?: '空响应') . " [HTTP " . $httpCode . "]"]); exit;
}

$imgData = json_decode($uploadimg, true);

if (!is_array($imgData)) {
    $snippet = mb_substr(trim(strip_tags($uploadimg)), 0, 300);
    echo json_encode([
        "code" => 201,
        "msg" => "img520.com接口返回了非JSON响应 [HTTP " . $httpCode . "]" . ($snippet ? "：" . $snippet : ""),
        "debug_response" => $snippet,
    ], JSON_UNESCAPED_UNICODE); exit;
}

$status = isset($imgData['status']) && $imgData['status'] === true;
$imgUrl = $imgData['data']['links']['url'] ?? '';

if ($status && $imgUrl !== '' && filter_var($imgUrl, FILTER_VALIDATE_URL)) {
    echo json_encode([
        "code" => 200,
        "msg" => "上传成功！",
        "path" => $imgUrl,
    ], JSON_UNESCAPED_UNICODE);
} elseif ($httpCode === 401) {
    echo json_encode(["code" => 201, "msg" => "img520.com 授权失败（401），管理员可能关闭了游客上传。"]);
} elseif ($httpCode === 403) {
    echo json_encode(["code" => 201, "msg" => "img520.com 管理员关闭了接口功能（403）。"]);
} elseif ($httpCode === 429) {
    echo json_encode(["code" => 201, "msg" => "img520.com 请求频率超限（429），请稍后再试。"]);
} elseif ($httpCode === 413) {
    echo json_encode(["code" => 201, "msg" => "文件大小超过img520.com限制（10MB）。"]);
} else {
    $errorMsg = $imgData['message'] ?? '未知错误';
    echo json_encode([
        "code" => 201,
        "msg" => "上传失败（HTTP " . $httpCode . "）：" . $errorMsg,
        "debug" => $uploadimg,
    ], JSON_UNESCAPED_UNICODE);
}

?>
