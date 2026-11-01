<?php
declare(strict_types=1);

// فقط فایل‌های عمومی موجود مخزن؛ مسیر خصوصی و فایل آپلود هرگز از این مسیر خوانده نمی‌شود.
function serveSiteAsset(string $path): void
{
    header('X-Content-Type-Options: nosniff');
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        http_response_code(405); header('Allow: GET, HEAD'); return;
    }
    if (!preg_match('~^(?:css/[A-Za-z0-9_-]+\.css|js/[A-Za-z0-9_-]+\.js|assets/images/[A-Za-z0-9_-]+\.(?:webp|png|jpe?g))$~D', $path)) {
        http_response_code(404); return;
    }
    $root = realpath(dirname(__DIR__, 2));
    $file = realpath($root . '/' . $path);
    if ($file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file) || !is_readable($file)) {
        http_response_code(404); return;
    }
    $type = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
        'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8',
        'webp' => 'image/webp', 'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg',
    };
    header('Content-Type: ' . $type);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: no-cache');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') readfile($file);
}
