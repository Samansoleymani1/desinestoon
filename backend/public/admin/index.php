<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
require_once dirname(__DIR__, 2) . '/app/admin-layout.php';
$db = database(); $admin = requireAdmin($db); requireGet();
$counts = $db->query('SELECT COUNT(*) AS total, COALESCE(SUM(is_published = 1 AND archived_at IS NULL), 0) AS published, COALESCE(SUM(archived_at IS NOT NULL), 0) AS archived FROM projects')->fetch();
$categories = $db->query('SELECT COUNT(*) FROM categories')->fetchColumn();
pageStart('داشبورد', $admin);
?>
<div class="stats">
<section><h2>نمونه‌کارها</h2><strong><?= (int)$counts['total'] ?></strong></section>
<section><h2>منتشرشده</h2><strong><?= (int)$counts['published'] ?></strong></section>
<section><h2>منتشرنشده</h2><strong><?= (int)$counts['total'] - (int)$counts['published'] - (int)$counts['archived'] ?></strong></section>
<section><h2>بایگانی‌شده</h2><strong><?= (int)$counts['archived'] ?></strong></section>
<section><h2>دسته‌بندی‌ها</h2><strong><?= (int)$categories ?></strong></section>
</div><p>تعداد منتشرشده، وضعیت خود نمونه‌کار را نشان می‌دهد؛ API عمومی تنها نمونه‌کارهای دسته‌های منتشرشده را نمایش می‌دهد.</p>
<p class="notice">سایت PHP اطلاعات این دیتابیس را نمایش می‌دهد؛ پس از تغییرات سایت را تازه کنید. نسخه GitHub Pages مستقل و استاتیک است.</p>
<a class="button" href="/admin/projects.php">مشاهده فهرست نمونه‌کارها</a>
<?php pageEnd(); ?>
