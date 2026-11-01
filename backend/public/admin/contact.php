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
        saveContactSettings($db, $_POST);
        $_SESSION['flash'] = 'اطلاعات تماس با موفقیت ذخیره شد.';
        redirectAdmin('contact.php');
    } catch (Throwable $exception) { $error = writeError($exception); }
}
$values = contactSettings($db);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $key) if (is_string($_POST[$key] ?? null)) $values[$key] = $_POST[$key];
}
pageStart('اطلاعات تماس', $admin);
formErrors($error);
?>
<p>این تنظیمات در دیتابیس و API عمومی ذخیره می‌شوند؛ اتصال سایت اصلی در فاز چهارم انجام می‌شود.</p>
<form method="post"><fieldset><legend>راه‌های ارتباطی</legend><?php csrfField(); ?>
<label for="phone1">شماره تماس اصلی و باکس رزرو</label><input id="phone1" name="contact_phone_primary" dir="ltr" maxlength="40" required value="<?= escape($values['contact_phone_primary']) ?>">
<label for="phone2">شماره تماس دوم — اختیاری</label><input id="phone2" name="contact_phone_secondary" dir="ltr" maxlength="40" value="<?= escape($values['contact_phone_secondary']) ?>">
<label for="instagram">نشانی HTTPS اینستاگرام — اختیاری</label><input id="instagram" name="contact_instagram" dir="ltr" maxlength="255" value="<?= escape($values['contact_instagram']) ?>">
<button class="primary">ذخیره اطلاعات تماس</button></fieldset></form>
<?php pageEnd(); ?>
