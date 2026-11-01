<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
// همان HTML سایت خوانده می‌شود؛ قالب دوم و کپی گالری وجود ندارد.
$html = file_get_contents(dirname(__DIR__, 2) . '/index.html');
if ($html === false) { http_response_code(503); exit('سایت موقتاً در دسترس نیست.'); }
$staticConfig = '<script src="./js/site-config.js" defer></script>';
if (substr_count($html, $staticConfig) !== 1) {
    http_response_code(503); exit('تنظیمات سایت موقتاً در دسترس نیست.');
}
echo str_replace($staticConfig, '<script src="./site-config.php" defer></script>', $html);
