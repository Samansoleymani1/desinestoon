<?php
declare(strict_types=1);
function pageStart(string $title, ?array $admin = null): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . escape($title) . ' | مدیریت دیزاینستون</title><link rel="stylesheet" href="/admin/assets/admin.css"><script src="/admin/assets/admin.js" defer></script></head><body><header><strong>مدیریت دیزاینستون</strong>';
    if ($admin) {
        echo '<nav aria-label="منوی مدیریت"><a href="/admin/">داشبورد</a><a href="/admin/projects.php">نمونه‌کارها</a><a href="/admin/categories.php">دسته‌بندی‌ها</a><a href="/admin/contact.php">اطلاعات تماس</a></nav><div class="account">' . escape($admin['username']) . '<form action="/admin/logout.php" method="post"><input type="hidden" name="csrf" value="' . escape(csrfToken()) . '"><button>خروج امن</button></form></div>';
    }
    echo '</header><main><h1>' . escape($title) . '</h1>';
    if (isset($_SESSION['flash'])) {
        echo '<p class="success" role="status">' . escape($_SESSION['flash']) . '</p>';
        unset($_SESSION['flash']);
    }
}
function pageEnd(): void
{
    echo '</main><footer>مدیریت دیزاینستون — تغییرات در دیتابیس ذخیره می‌شوند؛ سایت استاتیک هنوز مستقل است.</footer></body></html>';
}
function formErrors(string $message): void
{
    if ($message !== '') echo '<p class="error" role="alert">' . escape($message) . '</p>';
}
function csrfField(): void
{
    echo '<input type="hidden" name="csrf" value="' . escape(csrfToken()) . '">';
}
function writeError(Throwable $error): string
{
    if ($error instanceof ContentValidation) {
        http_response_code(422);
        return $error->getMessage();
    }
    if ($error instanceof PDOException && $error->getCode() === '23000') {
        http_response_code(409);
        return 'اطلاعات با یک رکورد موجود تعارض دارد یا رکورد وابسته دارد. شناسه متنی و وابستگی‌ها را بررسی کنید.';
    }
    http_response_code(503);
    return 'ذخیره‌سازی انجام نشد. دسترسی سرویس و فضای ذخیره‌سازی را بررسی و دوباره تلاش کنید.';
}
