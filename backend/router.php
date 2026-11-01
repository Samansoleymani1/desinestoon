<?php
declare(strict_types=1);
// فقط router سرور توسعه PHP؛ در Apache از rewrite عمومی استفاده می‌شود.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
foreach (explode('/', $path) as $segment) {
    if (str_starts_with($segment, '.')) { http_response_code(404); return; }
}
if (preg_match('~^/(css|js|assets)/~', $path)) {
    require_once __DIR__ . '/app/site-assets.php';
    serveSiteAsset(substr($path, 1));
    return;
}
if ($path === '/') {
    require __DIR__ . '/public/index.php';
    return;
}
// سرور داخلی برای مسیر ناموجود ممکن است به index.php برگردد؛ اینجا صریحاً 404 می‌دهیم.
$public = realpath(__DIR__ . '/public');
$candidate = realpath($public . $path);
if ($candidate !== false && str_starts_with($candidate, $public . DIRECTORY_SEPARATOR) &&
    (is_file($candidate) || (is_dir($candidate) && is_file($candidate . '/index.php')))) {
    return false;
}
http_response_code(404);
return;
