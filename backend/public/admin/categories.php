<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
require_once dirname(__DIR__, 2) . '/app/admin-layout.php';
$db = database(); $admin = requireAdmin($db); requireGet();
$rows = $db->query('SELECT c.*, (SELECT COUNT(*) FROM projects p WHERE p.category_id = c.id) AS project_count FROM categories c ORDER BY c.sort_order, c.id')->fetchAll();
pageStart('دسته‌بندی‌ها', $admin);
?>
<div class="table-wrap"><table><caption>دسته‌بندی‌های موجود</caption><thead><tr><th>عنوان</th><th>شناسه متنی</th><th>نمونه‌کارها</th><th>ترتیب</th><th>وضعیت</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= escape($row['title']) ?></td><td><?= escape($row['slug']) ?></td><td><?= (int)$row['project_count'] ?></td><td><?= (int)$row['sort_order'] ?></td><td><?= $row['is_published'] ? 'منتشرشده' : 'منتشرنشده' ?></td></tr>
<?php endforeach; ?>
</tbody></table></div><button disabled>مدیریت دسته‌ها — فاز سوم</button>
<?php pageEnd(); ?>
