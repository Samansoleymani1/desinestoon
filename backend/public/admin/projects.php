<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
require_once dirname(__DIR__, 2) . '/app/admin-layout.php';
$db = database(); $admin = requireAdmin($db); requireGet();
$rows = $db->query('SELECT p.id, p.title, c.title AS category_title, p.sort_order, p.is_published FROM projects p JOIN categories c ON c.id = p.category_id ORDER BY p.sort_order, p.id')->fetchAll();
pageStart('نمونه‌کارها', $admin);
?>
<a class="button" href="/admin/project-form.php">پیش‌نمایش فرم افزودن</a>
<div class="table-wrap"><table><caption>فهرست نمونه‌کارهای موجود</caption><thead><tr><th>شناسه</th><th>عنوان</th><th>دسته</th><th>ترتیب</th><th>وضعیت</th><th>فرم</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= (int)$row['id'] ?></td><td><?= escape($row['title']) ?></td><td><?= escape($row['category_title']) ?></td><td><?= (int)$row['sort_order'] ?></td><td><?= $row['is_published'] ? 'منتشرشده' : 'منتشرنشده' ?></td><td><a href="/admin/project-form.php?id=<?= (int)$row['id'] ?>">پیش‌نمایش ویرایش</a></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php phaseThreeNotice(); pageEnd(); ?>
