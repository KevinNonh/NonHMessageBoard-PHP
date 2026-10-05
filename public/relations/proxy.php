<?php
$url = $_GET['url'] ?? '';
if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    exit('Invalid URL');
}

// 白名单：只允许你的图床域名
$allowedHosts = ['image.chbnuohuang.com', 'board.1103.wang'];
$host = parse_url($url, PHP_URL_HOST);
if (!in_array($host, $allowedHosts, true)) {
    http_response_code(403);
    exit('Forbidden host');
}

// 只允许 https
$scheme = parse_url($url, PHP_URL_SCHEME);
if ($scheme !== 'https') {
    http_response_code(403);
    exit('Only https');
}

// 限制文件大小（5MB）
$ctx = stream_context_create(['http' => ['timeout' => 8]]);
$image = @file_get_contents($url, false, $ctx);
if ($image === false) {
    http_response_code(502);
    exit('Failed to fetch image');
}
if (strlen($image) > 5 * 1024 * 1024) {
    http_response_code(413);
    exit('Too large');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_buffer($finfo, $image);
finfo_close($finfo);
if (!preg_match('~^image/~', $mime)) {
    http_response_code(415);
    exit('Not an image');
}

header('Content-Type: ' . $mime);
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=3600');
echo $image;