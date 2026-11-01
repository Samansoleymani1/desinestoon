<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
require_once dirname(__DIR__, 2) . '/app/admin-layout.php';
require_once dirname(__DIR__, 2) . '/app/content.php';
$db = database();
$admin = requireAdmin($db);
allowFormMethods();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = formText($_POST, 'action', 10);
        if (!in_array($action, ['archive', 'restore'], true)) throw new ContentValidation('عملیات معتبر نیست.');
        archiveProject($db, formInteger($_POST, 'id', 1), $action === 'restore', $_POST);
        $_SESSION['flash'] = $action === 'restore' ? 'نمونه‌کار بازیابی شد؛ برای انتشار، فرم ویرایش را باز کنید.' : 'نمونه‌کار بدون حذف تصویر بایگانی شد.';
        redirectAdmin('projects.php');
    } catch (Throwable $exception) { $error = writeError($exception); }
}
$rows = $db->query('SELECT p.id, p.title, c.title AS category_title, p.sort_order, p.is_published, p.archived_at
    FROM projects p JOIN categories c ON c.id = p.category_id ORDER BY p.archived_at IS NOT NULL, p.sort_order, p.id')->fetchAll();
pageStart('نمونه‌کارها', $admin);
formErrors($error);
?>
<a class="button" href="/admin/project-form.php">افزودن نمونه‌کار</a>
<p>برای تغییر عنوان، تصویر، دسته، ترتیب یا انتشار از «ویرایش» استفاده کنید. بایگانی هیچ رکورد یا فایلی را حذف نمی‌کند.</p>
<div class="table-wrap"><table><caption>فهرست نمونه‌کارها و بایگانی</caption><thead><tr><th>شناسه</th><th>عنوان</th><th>دسته</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= (int)$row['id'] ?></td><td><?= escape($row['title']) ?></td><td><?= escape($row['category_title']) ?></td><td><?= (int)$row['sort_order'] ?></td>
<td><?= $row['archived_at'] !== null ? 'بایگانی‌شده' : ($row['is_published'] ? 'منتشرشده' : 'منتشرنشده') ?></td>
<td><a href="/admin/project-form.php?id=<?= (int)$row['id'] ?>">ویرایش</a>
<form method="post" class="compact-form"><?php csrfField(); ?>
<input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<input type="hidden" name="action" value="<?= $row['archived_at'] !== null ? 'restore' : 'archive' ?>">
<label><input type="checkbox" name="confirm" value="1" required> تأیید <?= $row['archived_at'] !== null ? 'بازیابی' : 'بایگانی' ?> «<?= escape($row['title']) ?>»</label>
<button><?= $row['archived_at'] !== null ? 'بازیابی به‌صورت منتشرنشده' : 'بایگانی' ?></button></form></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php pageEnd(); ?>
