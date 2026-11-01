<?php
declare(strict_types=1);
header('Content-Type: text/javascript; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
// هیچ تنظیم اتصال، رمز یا مسیر فیزیکی سرور در این خروجی قرار نمی‌گیرد.
echo 'window.DesignestoonConfig = Object.freeze(' . json_encode([
    'mode' => 'api', 'apiBase' => './', 'siteBase' => './', 'timeoutMs' => 8000,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . ');';
