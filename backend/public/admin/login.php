<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
require_once dirname(__DIR__, 2) . '/app/admin-layout.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405); header('Allow: GET, POST'); exit;
}
$db = database();
if (currentAdmin($db)) redirectAdmin('');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf()) {
        http_response_code(403);
        $error = 'درخواست معتبر نیست. صفحه را تازه کنید و دوباره تلاش کنید.';
    } elseif (!is_string($_POST['username'] ?? null) || !is_string($_POST['password'] ?? null) ||
        strlen($_POST['username']) > 100 || strlen($_POST['password']) > 72) {
        http_response_code(400);
        $error = 'اطلاعات ورود معتبر نیست.';
    } else {
        $error = authenticateAdmin($db, trim($_POST['username']), $_POST['password']);
        if ($error === '') redirectAdmin('');
        http_response_code(str_starts_with($error, 'تعداد') ? 429 : 401);
    }
}
pageStart('ورود مدیر');
if ($error !== '') echo '<p class="error" role="alert">' . escape($error) . '</p>';
if (isset($_GET['logged_out'])) echo '<p class="success" role="status">با موفقیت از حساب خارج شدید.</p>';
?>
<section class="login"><p>ورود فقط برای مدیر سایت است. ثبت‌نام عمومی وجود ندارد.</p>
<form method="post" action="/admin/login.php">
<input type="hidden" name="csrf" value="<?= escape(csrfToken()) ?>">
<label for="username">نام کاربری</label><input id="username" name="username" autocomplete="username" maxlength="100" required>
<label for="password">رمز عبور</label><input id="password" type="password" name="password" autocomplete="current-password" required>
<button class="primary">ورود به پنل</button></form></section>
<?php pageEnd(); ?>
