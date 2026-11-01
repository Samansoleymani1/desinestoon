<?php
declare(strict_types=1);
function pageStart(string $title, ?array $admin = null): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . escape($title) . ' | مدیریت دیزاینستون</title><link rel="stylesheet" href="/admin/assets/admin.css"></head><body><header><strong>مدیریت دیزاینستون</strong>';
    if ($admin) {
        echo '<nav aria-label="منوی مدیریت"><a href="/admin/">داشبورد</a><a href="/admin/projects.php">نمونه‌کارها</a><a href="/admin/categories.php">دسته‌بندی‌ها</a><a href="/admin/contact.php">اطلاعات تماس</a></nav><div class="account">' . escape($admin['username']) . '<form action="/admin/logout.php" method="post"><input type="hidden" name="csrf" value="' . escape(csrfToken()) . '"><button>خروج امن</button></form></div>';
    }
    echo '</header><main><h1>' . escape($title) . '</h1>';
    if (isset($_SESSION['flash'])) {
        echo '<p class="success" role="status">' . escape($_SESSION['flash']) . '</p>';
        unset($_SESSION['flash']);
    }
}
function pageEnd(): void { echo '</main><footer>پنل مدیریت — فاز دوم؛ ذخیره‌سازی محتوا و آپلود هنوز فعال نیست.</footer></body></html>'; }
function phaseThreeNotice(): void { echo '<p class="notice">این بخش فقط پیش‌نمایش رابط است. افزودن، ویرایش، ذخیره و آپلود در فاز سوم فعال می‌شوند.</p>'; }
