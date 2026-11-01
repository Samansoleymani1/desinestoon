<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/media.php';
try {
    $db = database();
    $known = array_flip($db->query('SELECT path FROM media')->fetchAll(PDO::FETCH_COLUMN));
    $count = 0;
    foreach (glob(uploadDirectory() . '/*') ?: [] as $file) {
        $name = basename($file);
        if (!preg_match('/^[a-f0-9]{32}\.(?:webp|jpg|png)(?:\.part)?$/D', $name)) continue;
        if (!isset($known['/media.php?file=' . $name])) {
            echo 'فایل نیازمند بررسی: ' . $name . "\n";
            $count++;
        }
    }
    echo "تعداد فایل‌های نیازمند بررسی: $count — هیچ فایلی حذف نشد.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "بررسی فایل‌ها ناموفق بود؛ هیچ فایلی تغییر نکرد.\n");
    exit(1);
}
