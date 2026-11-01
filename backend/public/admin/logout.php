<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
$db = database();
requireAdmin($db);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); header('Allow: POST'); echo 'برای خروج از دکمه خروج امن استفاده کنید.'; exit;
}
if (!validCsrf()) { http_response_code(403); echo 'درخواست خروج معتبر نیست.'; exit; }
destroyAdminSession();
redirectAdmin('login.php?logged_out=1');
