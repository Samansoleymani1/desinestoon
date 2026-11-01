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
        deleteCategory($db, formInteger($_POST, 'id', 1), $_POST);
        $_SESSION['flash'] = 'دسته‌بندی بدون وابستگی حذف شد.';
        redirectAdmin('categories.php');
    } catch (Throwable $exception) { $error = writeError($exception); }
}
$rows = $db->query('SELECT c.*, (SELECT COUNT(*) FROM projects p WHERE p.category_id = c.id) AS project_count
    FROM categories c ORDER BY c.sort_order, c.id')->fetchAll();
pageStart('دسته‌بندی‌ها', $admin);
formErrors($error);
?>
<a class="button" href="/admin/category-form.php">افزودن دسته‌بندی</a>
<div class="table-wrap"><table><caption>دسته‌بندی‌های موجود</caption><thead><tr><th>عنوان</th><th>شناسه متنی</th><th>نمونه‌کارها</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= escape($row['title']) ?></td><td><?= escape($row['slug']) ?></td><td><?= (int)$row['project_count'] ?></td><td><?= (int)$row['sort_order'] ?></td><td><?= $row['is_published'] ? 'فعال' : 'غیرفعال' ?></td>
<td><a href="/admin/category-form.php?id=<?= (int)$row['id'] ?>">ویرایش</a>
<?php if ((int)$row['project_count'] === 0): ?>
<form method="post" class="compact-form"><?php csrfField(); ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<label><input type="checkbox" name="confirm" value="1" required> حذف «<?= escape($row['title']) ?>» را تأیید می‌کنم</label><button>حذف دسته بدون وابستگی</button></form>
<?php else: ?><p>دارای نمونه‌کار؛ حذف مجاز نیست.</p><?php endif; ?>
</td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php pageEnd(); ?>
