<?php
/**
 * Joplin Share PHP Service
 * 用纯字符串处理，不用任何 PHP 扩展
 */

declare(strict_types=1);

// 配置
define('JOPLIN_BASE_URL', 'https://home.flaresky.top:8443');

// 获取 share ID
$shareId = $_GET['share_id'] ?? '';

if (empty($shareId) || !preg_match('#^[a-zA-Z0-9_/-]+$#', $shareId)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Not Found</title></head><body>';
    echo '<h1>404 Not Found</h1><p>Missing or invalid share_id</p></body></html>';
    exit;
}

$targetUrl = JOPLIN_BASE_URL . '/shares/' . $shareId;

// 用 file_get_contents 获取页面
$context = stream_context_create([
    'http' => [
        'timeout' => 30,
        'follow_location' => true,
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
]);
$html = @file_get_contents($targetUrl, false, $context);
$httpCode = 200;
if (isset($http_response_header[0]) && preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $m)) {
    $httpCode = (int)$m[1];
}

if ($html === false || $httpCode !== 200) {
    http_response_code(502);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body>';
    echo '<h1>Error fetching resource</h1><p>HTTP Code: ' . $httpCode . '</p></body></html>';
    exit;
}

// 清理 HTML
$html = preg_replace('/<div[^>]*class="[^"]*notification[^"]*"[^>]*>.*?<\/div>/is', '', $html);
$html = preg_replace('/<p[^>]*>Joplin Last updated.*?<\/p>/is', '', $html);

// 提取标题
$title = 'Joplin 笔记';
if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
    $title = strip_tags($m[1]);
    $title = trim($title);
}
if (empty($title) || $title === 'Joplin 笔记') {
    if (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
        $title = strip_tags($m[1]);
    }
}

// 提取第一张图片作为 og:image
$ogImage = '';
if (preg_match('/<img[^>]+src="([^"]+)"[^>]*>/is', $html, $m)) {
    $imgSrc = $m[1];
    if (strpos($imgSrc, 'http') !== 0) {
        $imgSrc = rtrim(JOPLIN_BASE_URL, '/') . '/' . ltrim($imgSrc, '/');
    }
    $ogImage = str_replace('http://', 'https://', $imgSrc);
}

// 提取 main 容器内容
$content = '';
if (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $m)) {
    $content = $m[1];
} elseif (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $m)) {
    $content = $m[1];
} else {
    $content = '<p>无法解析内容</p>';
}

// 提取描述（第一段文字）
$ogDescription = 'Joplin 笔记分享';
if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $content, $m) && strlen(strip_tags($m[1])) > 20) {
    $ogDescription = mb_substr(strip_tags($m[1]), 0, 100, 'UTF-8');
}

// 清理内容
$content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
$content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);
$content = preg_replace('/<header[^>]*>.*?<\/header>/is', '', $content);
$content = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $content);
$content = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $content);
$content = preg_replace('/<div[^>]*class="[^"]*close-notification[^"]*"[^>]*>.*?<\/div>/is', '', $content);
$content = preg_replace('/<p[^>]*class="[^"]*last-updated[^"]*"[^>]*>.*?<\/p>/is', '', $content);
$content = preg_replace('/<div[^>]*class="[^"]*note-main[^"]*"[^>]*>/is', '', $content);
$content = preg_replace('/<div[^>]*id="joplin-container-pluginAssetsContainer"[^>]*>.*?<\/div>/is', '', $content);

// 输出页面
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <!-- Open Graph for WeChat sharing -->
    <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($ogDescription); ?>">
    <meta property="og:type" content="article">
    <?php if ($ogImage): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($ogDescription); ?>">
    <?php if ($ogImage): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/templates/wechat.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-light.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>hljs.highlightAll();</script>
</head>
<body>
    <div class="container">
        <div class="note-content">
            <?php echo $content; ?>
        </div>
    </div>
</body>
</html>
