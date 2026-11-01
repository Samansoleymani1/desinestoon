<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
require_once dirname(__DIR__, 2) . '/app/admin-layout.php';
$db = database(); $admin = requireAdmin($db); requireGet();
$counts = $db->query('SELECT COUNT(*) AS total, COALESCE(SUM(is_published = 1), 0) AS published FROM projects')->fetch();
$categories = $db->query('SELECT COUNT(*) FROM categories')->fetchColumn();
pageStart('داشبورد', $admin);
?>
<div class="stats">
<section><h2>نمونه‌کارها</h2><strong><?= (int)$counts['total'] ?></strong></section>
<section><h2>منتشرشده</h2><strong><?= (int)$counts['published'] ?></strong></section>
<section><h2>منتشرنشده</h2><strong><?= (int)$counts['total'] - (int)$counts['published'] ?></strong></section>
<section><h2>دسته‌بندی‌ها</h2><strong><?= (int)$categories ?></strong></section>
</div><p>تعداد منتشرشده، وضعیت خود نمونه‌کار را نشان می‌دهد؛ API عمومی تنها نمونه‌کارهای دسته‌های منتشرشده را نمایش می‌دهد.</p>
<p class="notice">سایت عمومی در این فاز همچنان از اطلاعات ثابت فعلی استفاده می‌کند.</p>
<a class="button" href="/admin/projects.php">مشاهده فهرست نمونه‌کارها</a>
<?php pageEnd(); ?>
