<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

// این فایل فقط برای ورودی‌های مدیریت بارگذاری می‌شود؛ API عمومی مستقل می‌ماند.
ini_set('display_errors', '0');
set_exception_handler(static function (Throwable $e): void {
    http_response_code(503);
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo '{"error":"service_unavailable","message":"سرویس مدیریت موقتاً در دسترس نیست."}';
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>خطا</title><p>سرویس مدیریت موقتاً در دسترس نیست. دوباره تلاش کنید.</p></html>';
    }
});
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self' blob:; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
if (!$https && !$local) throw new RuntimeException('HTTPS required');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_name('designestoon_admin');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/admin/', 'secure' => $https,
    'httponly' => true, 'samesite' => 'Strict',
]);
if (!session_start()) throw new RuntimeException('Session unavailable');

function escape(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrfToken(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function validCsrf(): bool
{
    return is_string($_POST['csrf'] ?? null) && isset($_SESSION['csrf']) &&
        hash_equals($_SESSION['csrf'], $_POST['csrf']);
}
function redirectAdmin(string $path): never
{
    header('Location: /admin/' . $path, true, 303);
    exit;
}
function destroyAdminSession(): void
{
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600, 'path' => $params['path'],
        'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_destroy();
}
function currentAdmin(PDO $db): ?array
{
    if (!isset($_SESSION['admin_id'])) return null;
    $now = time();
    if ($now - ($_SESSION['last_activity'] ?? 0) > 1800 ||
        $now - ($_SESSION['authenticated_at'] ?? 0) > 28800) {
        $_SESSION = [];
        session_regenerate_id(true);
        return null;
    }
    $q = $db->prepare('SELECT id, username FROM admins WHERE id = ? AND is_active = 1');
    $q->execute([$_SESSION['admin_id']]);
    $admin = $q->fetch();
    if (!$admin) {
        $_SESSION = [];
        session_regenerate_id(true);
        return null;
    }
    $_SESSION['last_activity'] = $now;
    return $admin;
}
function requireAdmin(PDO $db, bool $api = false): array
{
    $admin = currentAdmin($db);
    if ($admin) return $admin;
    if ($api) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo '{"error":"unauthorized","message":"ابتدا وارد حساب مدیریت شوید."}';
        exit;
    }
    redirectAdmin('login.php');
}
function authenticateAdmin(PDO $db, string $username, string $password): string
{
    // قفل ردیف، محدودیت را میان درخواست‌های هم‌زمان و نشست‌های متفاوت اتمیک می‌کند.
    $db->beginTransaction();
    try {
        $row = $db->query('SELECT failures, window_started FROM admin_login_limits WHERE id = 1 FOR UPDATE')->fetch();
        if (!$row) throw new RuntimeException('Security migration required');
        $now = time();
        $failures = $now - (int)$row['window_started'] >= 900 ? 0 : (int)$row['failures'];
        $started = $failures === 0 ? $now : (int)$row['window_started'];
        if ($failures >= 5) {
            $db->commit();
            return 'تعداد تلاش‌ها بیش از حد مجاز است. پس از پایان بازه ۱۵ دقیقه‌ای دوباره تلاش کنید.';
        }
        $q = $db->prepare('SELECT id, password_hash, is_active FROM admins WHERE username = ?');
        $q->execute([$username]);
        $admin = $q->fetch();
        // هش ثابت فقط برای یکسان‌سازی هزینه کاربر ناموجود است؛ رمز پیش‌فرض نیست.
        $dummy = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        $valid = password_verify($password, $admin['password_hash'] ?? $dummy);
        $valid = $valid && $admin && (int)$admin['is_active'] === 1;
        $update = $db->prepare('UPDATE admin_login_limits SET failures = ?, window_started = ? WHERE id = 1');
        $update->execute([$valid ? 0 : $failures + 1, $started]);
        $db->commit();
        if (!$valid) return 'نام کاربری یا رمز عبور نادرست است.';
        session_regenerate_id(true);
        $_SESSION = ['admin_id' => (int)$admin['id'], 'authenticated_at' => $now,
            'last_activity' => $now, 'csrf' => bin2hex(random_bytes(32)),
            'flash' => 'با موفقیت وارد پنل مدیریت شدید.'];
        return '';
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
function requireGet(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        echo 'این روش درخواست مجاز نیست.';
        exit;
    }
}

// همه فرم‌های نوشتن از همان نشست و CSRF فاز دوم استفاده می‌کنند.
function requireWriteRequest(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('این عملیات فقط با ارسال فرم انجام می‌شود.');
    }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 12 * 1024 * 1024) {
        http_response_code(413);
        exit('حجم درخواست بیش از حد مجاز است. تصویر باید حداکثر ۱۰ مگابایت باشد.');
    }
    if (!validCsrf()) {
        http_response_code(403);
        exit('درخواست معتبر نیست. صفحه را تازه کنید و دوباره تلاش کنید.');
    }
}
function allowFormMethods(): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
        http_response_code(405);
        header('Allow: GET, POST');
        exit('این روش درخواست مجاز نیست.');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') requireWriteRequest();
}
