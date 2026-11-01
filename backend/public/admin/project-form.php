<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
require_once dirname(__DIR__, 2) . '/app/admin-layout.php';
require_once dirname(__DIR__, 2) . '/app/media.php';
$db = database();
$admin = requireAdmin($db);
allowFormMethods();
$id = null;
$project = ['title' => '', 'category_id' => '', 'sort_order' => 0, 'is_published' => 0, 'media_id' => null, 'archived_at' => null];
if (isset($_GET['id'])) {
    try { $id = formInteger($_GET, 'id', 1); }
    catch (ContentValidation $error) { http_response_code(400); exit('شناسه معتبر نیست.'); }
    $query = $db->prepare('SELECT * FROM projects WHERE id = ?');
    $query->execute([$id]);
    $project = $query->fetch();
    if (!$project) { http_response_code(404); exit('نمونه‌کار پیدا نشد.'); }
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $savedId = saveProject($db, $_POST, $_FILES['image'] ?? null, $id);
        $_SESSION['flash'] = $id === null ? 'نمونه‌کار جدید با موفقیت اضافه شد.' : 'تغییرات نمونه‌کار ذخیره شد.';
        redirectAdmin('project-form.php?id=' . $savedId);
    } catch (Throwable $exception) { $error = writeError($exception); }
}
$values = $project;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['title', 'category_id', 'sort_order'] as $key) {
        if (is_string($_POST[$key] ?? null)) $values[$key] = $_POST[$key];
    }
    $values['is_published'] = ($_POST['is_published'] ?? '') === '1';
}
$categories = $db->query('SELECT id, title, is_published FROM categories ORDER BY sort_order, id')->fetchAll();
pageStart($id === null ? 'افزودن نمونه‌کار' : 'ویرایش نمونه‌کار', $admin);
formErrors($error);
if ($project['archived_at'] !== null) echo '<p class="notice">این نمونه‌کار بایگانی شده است. ابتدا از فهرست آن را بازیابی کنید.</p>';
?>
<form method="post" enctype="multipart/form-data">
<?php csrfField(); ?>
<fieldset <?= $project['archived_at'] !== null ? 'disabled' : '' ?>><legend>اطلاعات نمونه‌کار</legend>
<label for="title">عنوان</label><input id="title" name="title" maxlength="255" required value="<?= escape($values['title']) ?>">
<label for="category">دسته‌بندی</label><select id="category" name="category_id" required>
<option value="">انتخاب دسته‌بندی</option>
<?php foreach ($categories as $category): ?>
<option value="<?= (int)$category['id'] ?>" <?= $values['category_id'] == $category['id'] ? 'selected' : '' ?>><?= escape($category['title']) ?><?= $category['is_published'] ? '' : ' — غیرفعال' ?></option>
<?php endforeach; ?>
</select>
<label for="order">ترتیب نمایش؛ عدد کوچک‌تر زودتر نمایش داده می‌شود</label><input id="order" name="sort_order" type="number" min="0" max="2147483647" required value="<?= escape($values['sort_order']) ?>">
<label><input name="is_published" value="1" type="checkbox" <?= $values['is_published'] ? 'checked' : '' ?>> منتشرشده</label>
<p>نمونه‌کار در دسته غیرفعال، در API عمومی نمایش داده نمی‌شود.</p>
<?php if ($project['media_id'] !== null): ?>
<p>تصویر فعلی</p><img class="image-preview" src="/admin/image.php?id=<?= (int)$project['media_id'] ?>" alt="تصویر فعلی نمونه‌کار">
<?php endif; ?>
<label for="image"><?= $id === null ? 'تصویر' : 'تعویض تصویر؛ خالی بگذارید تا تصویر فعلی حفظ شود' ?></label>
<input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" <?= $id === null ? 'required' : '' ?>>
<p>حداکثر ۱۰ مگابایت؛ JPEG، PNG یا WebP. تصویر قبلی پس از تعویض نگهداری می‌شود. پس از خطای فرم، فایل را دوباره انتخاب کنید.</p>
<p id="image-message" role="status"></p><img id="image-preview" class="image-preview" alt="پیش‌نمایش تصویر انتخاب‌شده" hidden>
<button class="primary">ذخیره نمونه‌کار</button></fieldset></form>
<a href="/admin/projects.php">بازگشت به فهرست</a>
<?php pageEnd(); ?>
