<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content.php';

function uploadDirectory(): string
{
    return dirname(__DIR__) . '/storage/uploads';
}
/** خروجی فقط فایل جدید است؛ هیچ فایل موجودی جایگزین یا حذف نمی‌شود. */
function receiveImage(?array $file): ?array
{
    if ($file === null || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) return null;
    if (!is_int($file['error'] ?? null) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new ContentValidation('دریافت تصویر ناموفق بود؛ حداکثر حجم مجاز ۱۰ مگابایت است.');
    }
    $temporary = $file['tmp_name'] ?? null;
    $name = $file['name'] ?? null;
    if (!is_string($temporary) || !is_string($name) || !is_uploaded_file($temporary)) {
        throw new ContentValidation('فایل بارگذاری‌شده معتبر نیست.');
    }
    $size = filesize($temporary);
    if ($size === false || $size < 1 || $size > MAX_IMAGE_UPLOAD_BYTES) {
        throw new ContentValidation('حجم تصویر باید بیشتر از صفر و حداکثر ۱۰ مگابایت باشد.');
    }
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
    $allowed = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']];
    if (!isset($allowed[$mime]) || !in_array($extension, $allowed[$mime], true)) {
        throw new ContentValidation('فقط فایل واقعی JPEG، PNG یا WebP با پسوند متناظر مجاز است.');
    }
    $dimensions = @getimagesize($temporary);
    if (!$dimensions || ($dimensions['mime'] ?? '') !== $mime ||
        $dimensions[0] < 1 || $dimensions[1] < 1 ||
        $dimensions[0] > 8192 || $dimensions[1] > 8192 || $dimensions[0] * $dimensions[1] > 16000000) {
        throw new ContentValidation('ابعاد تصویر معتبر نیست؛ حداکثر هر ضلع ۸۱۹۲ و مجموع ۱۶ میلیون پیکسل است.');
    }
    if (!function_exists('imagecreatefromstring')) throw new RuntimeException('GD required');
    $image = @imagecreatefromstring(file_get_contents($temporary));
    if (!$image) throw new ContentValidation('تصویر ناقص یا غیرقابل پردازش است.');
    $part = null;
    $final = null;
    $stream = null;
    $success = false;
    try {
        // بازرمزگذاری، نام اولیه و metadata و محتوای اضافی فایل را کنار می‌گذارد.
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $webp = function_exists('imagewebp');
        $outputExtension = $webp ? 'webp' : ($mime === 'image/jpeg' ? 'jpg' : 'png');
        $outputMime = $webp ? 'image/webp' : ($outputExtension === 'jpg' ? 'image/jpeg' : 'image/png');
        $directory = uploadDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Upload storage unavailable');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $outputExtension;
        $final = $directory . '/' . $filename;
        $part = $final . '.part';
        $stream = fopen($part, 'x+b');
        if (!$stream) throw new RuntimeException('Cannot create staged image');
        $encoded = $webp ? imagewebp($image, $stream, 85)
            : ($outputExtension === 'jpg' ? imagejpeg($image, $stream, 90) : imagepng($image, $stream, 6));
        fflush($stream);
        fclose($stream);
        $stream = null;
        clearstatcache(true, $part);
        $bytes = filesize($part);
        if (!$encoded || !$bytes || $bytes > MAX_IMAGE_UPLOAD_BYTES) {
            throw new ContentValidation('خروجی تصویر قابل ذخیره نیست یا بیش از ۱۰ مگابایت است.');
        }
        if (!rename($part, $final)) throw new RuntimeException('Cannot finalize image');
        $success = true;
        return ['path' => '/media.php?file=' . $filename, 'file' => $final,
            'mime_type' => $outputMime, 'width' => $dimensions[0], 'height' => $dimensions[1], 'byte_size' => $bytes];
    } finally {
        if (is_resource($stream)) fclose($stream);
        imagedestroy($image);
        if ($part !== null && is_file($part)) unlink($part);
        if (!$success && $final !== null && is_file($final)) unlink($final);
    }
}
function saveProject(PDO $db, array $input, ?array $file, ?int $id): int
{
    $title = formText($input, 'title');
    if ($title === '') throw new ContentValidation('عنوان نمونه‌کار را وارد کنید.');
    $category = formInteger($input, 'category_id', 1);
    $order = formInteger($input, 'sort_order');
    $published = formPublished($input);
    $image = receiveImage($file);
    if ($id === null && $image === null) throw new ContentValidation('برای نمونه‌کار جدید، تصویر انتخاب کنید.');
    try {
        $db->beginTransaction();
        $query = $db->prepare('SELECT id FROM categories WHERE id = ? FOR SHARE');
        $query->execute([$category]);
        if (!$query->fetch()) throw new ContentValidation('دسته‌بندی انتخاب‌شده وجود ندارد.');
        $mediaId = null;
        if ($id !== null) {
            $query = $db->prepare('SELECT media_id, archived_at FROM projects WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $existing = $query->fetch();
            if (!$existing) throw new ContentValidation('نمونه‌کار پیدا نشد.');
            if ($existing['archived_at'] !== null) throw new ContentValidation('ابتدا نمونه‌کار بایگانی‌شده را بازیابی کنید.');
            $mediaId = (int)$existing['media_id'];
        }
        if ($image !== null) {
            $query = $db->prepare('INSERT INTO media (path, mime_type, width, height, alt_text, byte_size) VALUES (?, ?, ?, ?, ?, ?)');
            $query->execute([$image['path'], $image['mime_type'], $image['width'], $image['height'], $title, $image['byte_size']]);
            $mediaId = (int)$db->lastInsertId();
        }
        if ($id === null) {
            $query = $db->prepare('INSERT INTO projects (title, category_id, media_id, sort_order, is_published) VALUES (?, ?, ?, ?, ?)');
            $query->execute([$title, $category, $mediaId, $order, $published]);
            $id = (int)$db->lastInsertId();
        } else {
            $query = $db->prepare('UPDATE projects SET title = ?, category_id = ?, media_id = ?, sort_order = ?, is_published = ? WHERE id = ?');
            $query->execute([$title, $category, $mediaId, $order, $published, $id]);
        }
        $db->commit();
        return $id;
    } catch (Throwable $error) {
        try {
            if ($db->inTransaction()) $db->rollBack();
        } finally {
            // حتی اگر اتصال هنگام rollback قطع شده باشد، فایل تازه پاک می‌شود.
            if ($image !== null && is_file($image['file'])) unlink($image['file']);
        }
        throw $error;
    }
}
function mediaFile(array $media): ?string
{
    if (preg_match('~^/media\.php\?file=([a-f0-9]{32}\.(?:webp|jpg|png))$~D', $media['path'], $match)) {
        $file = uploadDirectory() . '/' . $match[1];
    } elseif (preg_match('~^\./assets/images/[A-Za-z0-9_-]+\.(?:webp|png|jpe?g)$~D', $media['path'])) {
        $file = dirname(__DIR__, 2) . '/' . substr($media['path'], 2);
    } else {
        return null;
    }
    return is_file($file) ? $file : null;
}
function streamImage(array $media): void
{
    $file = mediaFile($media);
    if ($file === null || !in_array($media['mime_type'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
        http_response_code(404);
        return;
    }
    header('Content-Type: ' . $media['mime_type']);
    header('Content-Length: ' . filesize($file));
    header('Content-Disposition: inline');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Cache-Control: no-store');
    readfile($file);
}
