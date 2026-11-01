<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
require_once dirname(__DIR__, 2) . '/app/admin-layout.php';
require_once dirname(__DIR__, 2) . '/app/content.php';
$db = database();
$admin = requireAdmin($db);
allowFormMethods();
$id = null;
$category = ['title' => '', 'slug' => '', 'sort_order' => 0, 'is_published' => 1];
if (isset($_GET['id'])) {
    try { $id = formInteger($_GET, 'id', 1); }
    catch (ContentValidation $exception) { http_response_code(400); exit('شناسه معتبر نیست.'); }
    $query = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $query->execute([$id]);
    $category = $query->fetch();
    if (!$category) { http_response_code(404); exit('دسته‌بندی پیدا نشد.'); }
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        saveCategory($db, $_POST, $id);
        $_SESSION['flash'] = 'دسته‌بندی با موفقیت ذخیره شد.';
        redirectAdmin('categories.php');
    } catch (Throwable $exception) { $error = writeError($exception); }
}
$values = $category;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['title', 'sort_order', 'slug'] as $key) {
        if (is_string($_POST[$key] ?? null) && ($key !== 'slug' || $id === null)) $values[$key] = $_POST[$key];
    }
    $values['is_published'] = ($_POST['is_published'] ?? '') === '1';
}
pageStart($id === null ? 'افزودن دسته‌بندی' : 'ویرایش دسته‌بندی', $admin);
formErrors($error);
?>
<form method="post"><fieldset><legend>اطلاعات دسته‌بندی</legend><?php csrfField(); ?>
<label for="title">عنوان</label><input id="title" name="title" maxlength="255" required value="<?= escape($values['title']) ?>">
<label for="slug">شناسه متنی ثابت</label><input id="slug" name="slug" dir="ltr" maxlength="64" <?= $id !== null ? 'readonly' : 'required pattern="[a-z][a-z0-9-]{0,63}"' ?> value="<?= escape($values['slug']) ?>">
<p>شناسه متنی پس از ایجاد تغییر نمی‌کند تا سازگاری فیلترها حفظ شود.</p>
<label for="order">ترتیب نمایش</label><input id="order" name="sort_order" type="number" min="0" max="2147483647" required value="<?= escape($values['sort_order']) ?>">
<label><input type="checkbox" name="is_published" value="1" <?= $values['is_published'] ? 'checked' : '' ?>> فعال و منتشرشده</label>
<button class="primary">ذخیره دسته‌بندی</button></fieldset></form>
<?php pageEnd(); ?>
