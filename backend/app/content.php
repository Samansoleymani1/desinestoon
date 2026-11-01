<?php
declare(strict_types=1);

class ContentValidation extends RuntimeException {}

function formText(array $input, string $key, int $maximum = 255): string
{
    $value = $input[$key] ?? '';
    if (!is_string($value)) throw new ContentValidation('قالب اطلاعات فرم معتبر نیست.');
    $value = trim($value);
    $length = preg_match_all('/./us', $value);
    if ($length === false || $length > $maximum || str_contains($value, "\0")) {
        throw new ContentValidation('متن واردشده نامعتبر یا بیش از حد طولانی است.');
    }
    return $value;
}
function formInteger(array $input, string $key, int $minimum = 0): int
{
    $value = formText($input, $key, 10);
    if (!preg_match('/^[0-9]+$/D', $value) || (float)$value > 2147483647 || (int)$value < $minimum) {
        throw new ContentValidation('شناسه یا ترتیب نمایش معتبر نیست.');
    }
    return (int)$value;
}
function formPublished(array $input): int
{
    $value = formText($input, 'is_published', 1);
    if (!in_array($value, ['', '0', '1'], true)) throw new ContentValidation('وضعیت انتشار معتبر نیست.');
    return $value === '1' ? 1 : 0;
}
function contactSettings(PDO $db): array
{
    $values = [
        'contact_phone_primary' => '09117901519',
        'contact_phone_secondary' => '09366208359',
        'contact_instagram' => 'https://www.instagram.com/designestoon_amol/',
    ];
    $query = $db->prepare('SELECT setting_key, setting_value FROM settings WHERE setting_key IN (?, ?, ?)');
    $query->execute(array_keys($values));
    foreach ($query->fetchAll() as $row) $values[$row['setting_key']] = $row['setting_value'];
    return $values;
}
function normalizedPhone(string $phone): string
{
    $phone = strtr($phone, array_combine(
        preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY),
        str_split('01234567890123456789')
    ));
    $phone = str_replace([' ', '-', '(', ')'], '', $phone);
    if (str_starts_with($phone, '+98')) $phone = '0' . substr($phone, 3);
    if (!preg_match('/^0[1-9][0-9]{9}$/D', $phone)) throw new ContentValidation('شماره تلفن باید یک شماره معتبر ۱۱ رقمی ایران باشد.');
    return $phone;
}
function saveContactSettings(PDO $db, array $input): void
{
    $primary = normalizedPhone(formText($input, 'contact_phone_primary', 40));
    $secondary = formText($input, 'contact_phone_secondary', 40);
    $secondary = $secondary === '' ? '' : normalizedPhone($secondary);
    $instagram = formText($input, 'contact_instagram', 255);
    if ($instagram !== '' && !preg_match('~^https://(?:www\.)?instagram\.com/[A-Za-z0-9_.]+/?$~D', $instagram)) {
        throw new ContentValidation('نشانی اینستاگرام معتبر نیست؛ از آدرس HTTPS صفحه استفاده کنید.');
    }
    $values = ['contact_phone_primary' => $primary, 'contact_phone_secondary' => $secondary, 'contact_instagram' => $instagram];
    $db->beginTransaction();
    try {
        $query = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?');
        foreach ($values as $key => $value) $query->execute([$key, $value, $value]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}
function saveCategory(PDO $db, array $input, ?int $id): void
{
    $title = formText($input, 'title');
    if ($title === '') throw new ContentValidation('عنوان دسته‌بندی را وارد کنید.');
    $order = formInteger($input, 'sort_order');
    $published = formPublished($input);
    if ($id !== null) {
        // slug دسته موجود هرگز از فرم دریافت یا تغییر داده نمی‌شود.
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT id FROM categories WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            if (!$query->fetch()) throw new ContentValidation('دسته‌بندی پیدا نشد.');
            $query = $db->prepare('UPDATE categories SET title = ?, sort_order = ?, is_published = ? WHERE id = ?');
            $query->execute([$title, $order, $published, $id]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
    } else {
        $slug = formText($input, 'slug', 64);
        if ($slug === 'all' || !preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $slug)) {
            throw new ContentValidation('شناسه متنی باید با حرف لاتین شروع شود و فقط شامل حروف کوچک، عدد و خط تیره باشد؛ all رزرو شده است.');
        }
        $query = $db->prepare('INSERT INTO categories (slug, title, sort_order, is_published) VALUES (?, ?, ?, ?)');
        $query->execute([$slug, $title, $order, $published]);
    }
}
function deleteCategory(PDO $db, int $id, array $input): void
{
    if (formText($input, 'confirm', 1) !== '1') throw new ContentValidation('برای حذف دسته تأیید لازم است.');
    $db->beginTransaction();
    try {
        $query = $db->prepare('SELECT id FROM categories WHERE id = ? FOR UPDATE');
        $query->execute([$id]);
        if (!$query->fetch()) throw new ContentValidation('دسته‌بندی پیدا نشد.');
        $query = $db->prepare('SELECT COUNT(*) FROM projects WHERE category_id = ?');
        $query->execute([$id]);
        if ((int)$query->fetchColumn() !== 0) throw new ContentValidation('این دسته نمونه‌کار وابسته دارد و قابل حذف نیست؛ می‌توانید آن را غیرفعال کنید.');
        $query = $db->prepare('DELETE FROM categories WHERE id = ?');
        $query->execute([$id]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}
function archiveProject(PDO $db, int $id, bool $restore, array $input): void
{
    if (formText($input, 'confirm', 1) !== '1') throw new ContentValidation('تأیید عملیات بایگانی یا بازیابی لازم است.');
    // بازیابی عمداً منتشرنشده است تا انتشار دوباره تصمیم مستقل مدیر باشد.
    $query = $db->prepare($restore
        ? 'UPDATE projects SET archived_at = NULL, is_published = 0 WHERE id = ? AND archived_at IS NOT NULL'
        : 'UPDATE projects SET archived_at = CURRENT_TIMESTAMP, is_published = 0 WHERE id = ? AND archived_at IS NULL');
    $query->execute([$id]);
    if ($query->rowCount() !== 1) throw new ContentValidation('رکورد پیدا نشد یا وضعیت آن قبلاً تغییر کرده است.');
}
