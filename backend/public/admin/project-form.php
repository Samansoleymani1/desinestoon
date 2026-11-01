<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
require_once dirname(__DIR__, 2) . '/app/admin-layout.php';
$db = database(); $admin = requireAdmin($db); requireGet();
$project = ['title' => '', 'category_id' => '', 'sort_order' => '', 'is_published' => 0, 'image' => ''];
if (isset($_GET['id'])) {
    if (!is_string($_GET['id']) || !ctype_digit($_GET['id'])) { http_response_code(400); exit('شناسه معتبر نیست.'); }
    $q = $db->prepare('SELECT p.*, m.path AS image FROM projects p JOIN media m ON m.id = p.media_id WHERE p.id = ?');
    $q->execute([$_GET['id']]); $project = $q->fetch();
    if (!$project) { http_response_code(404); exit('نمونه‌کار پیدا نشد.'); }
}
$categories = $db->query('SELECT id, title FROM categories ORDER BY sort_order, id')->fetchAll();
pageStart(isset($_GET['id']) ? 'پیش‌نمایش فرم ویرایش' : 'پیش‌نمایش فرم افزودن', $admin);
phaseThreeNotice();
?>
<form><fieldset disabled><legend>اطلاعات نمونه‌کار — غیرفعال</legend>
<label for="title">عنوان</label><input id="title" value="<?= escape($project['title']) ?>">
<label for="category">دسته‌بندی</label><select id="category">
<?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= $project['category_id'] == $category['id'] ? 'selected' : '' ?>><?= escape($category['title']) ?></option><?php endforeach; ?>
</select><label for="order">ترتیب نمایش</label><input id="order" type="number" value="<?= escape($project['sort_order']) ?>">
<label><input type="checkbox" <?= $project['is_published'] ? 'checked' : '' ?>> منتشرشده</label>
<label for="image-path">مسیر تصویر فعلی</label><input id="image-path" dir="ltr" value="<?= escape($project['image']) ?>">
<label for="image">تصویر جدید — حداکثر ۱۰ مگابایت، فاز سوم</label><input id="image" type="file" accept="image/webp,image/jpeg,image/png">
<button disabled>ذخیره — در فاز سوم فعال می‌شود</button></fieldset></form>
<?php pageEnd(); ?>
