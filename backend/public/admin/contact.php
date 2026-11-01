<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
require_once dirname(__DIR__, 2) . '/app/admin-layout.php';
$db = database(); $admin = requireAdmin($db); requireGet();
// مقادیر پیش‌فرض از سایت فعلی‌اند؛ در این فاز هیچ INSERT انجام نمی‌شود.
$values = ['contact_phone_primary' => '0911 790 1519', 'contact_phone_secondary' => '0936 620 8359', 'contact_instagram' => 'https://www.instagram.com/designestoon_amol/'];
$q = $db->prepare('SELECT setting_key, setting_value FROM settings WHERE setting_key IN (?, ?, ?)');
$q->execute(array_keys($values));
foreach ($q->fetchAll() as $row) $values[$row['setting_key']] = $row['setting_value'];
pageStart('اطلاعات تماس', $admin); phaseThreeNotice();
?>
<p>اگر تنظیمی در دیتابیس ثبت نشده باشد، اطلاعات فعلی سایت نمایش داده می‌شود؛ این صفحه هنوز سایت عمومی را تغییر نمی‌دهد.</p>
<form><fieldset disabled><legend>پیش‌نمایش اطلاعات تماس</legend>
<label for="phone1">شماره تماس اصلی و باکس رزرو</label><input id="phone1" dir="ltr" value="<?= escape($values['contact_phone_primary']) ?>">
<label for="phone2">شماره تماس دوم</label><input id="phone2" dir="ltr" value="<?= escape($values['contact_phone_secondary']) ?>">
<label for="instagram">نشانی اینستاگرام</label><input id="instagram" dir="ltr" value="<?= escape($values['contact_instagram']) ?>">
<button disabled>ذخیره اطلاعات تماس — فاز سوم</button></fieldset></form>
<?php pageEnd(); ?>
