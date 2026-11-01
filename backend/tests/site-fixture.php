<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/media.php';
require_once dirname(__DIR__) . '/app/catalog.php';
if (configuration()['DB_NAME'] !== 'designestoon_test') { fwrite(STDERR, "فقط دیتابیس تست مجاز است.\n"); exit(1); }
$db = database();
$file = dirname(__DIR__) . '/storage/site-test-state.json';
$action = $argv[1] ?? '';
if ($action === 'prepare') {
    $fixture = json_decode(file_get_contents(dirname(__DIR__) . '/database/initial-data.json'), true, 512, JSON_THROW_ON_ERROR);
    if (is_file($file) || publishedProjects($db) !== $fixture['projects'] || publishedCategories($db) !== $fixture['categories'] ||
        (int)$db->query('SELECT COUNT(*) FROM projects')->fetchColumn() !== 22 ||
        (int)$db->query('SELECT COUNT(*) FROM categories')->fetchColumn() !== 7 ||
        (int)$db->query('SELECT COUNT(*) FROM media')->fetchColumn() !== 22 ||
        (int)$db->query('SELECT COUNT(*) FROM admins')->fetchColumn() !== 0 ||
        (int)$db->query('SELECT COUNT(*) FROM settings')->fetchColumn() !== 0) {
        fwrite(STDERR, "دیتابیس تست باید تازه و Seedشده و بدون مدیر و تنظیم تماس باشد؛ snapshot قبلی نباید وجود داشته باشد.\n"); exit(1);
    }
    $state = ['projects' => $db->query('SELECT * FROM projects')->fetchAll(),
        'categories' => $db->query('SELECT * FROM categories')->fetchAll()];
    if (file_put_contents($file, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === false) exit(1);
    echo "آماده‌سازی تست مرورگر موفق بود.\n";
} elseif ($action === 'cleanup') {
    if (!is_file($file)) exit(1);
    $state = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $newMedia = $db->query('SELECT * FROM media WHERE id > 22')->fetchAll();
    $db->exec('DELETE FROM projects WHERE id > 22');
    $query = $db->prepare('UPDATE projects SET title=?, category_id=?, media_id=?, sort_order=?, is_published=?, archived_at=?, updated_at=? WHERE id=?');
    foreach ($state['projects'] as $row) $query->execute([$row['title'], $row['category_id'], $row['media_id'], $row['sort_order'], $row['is_published'], $row['archived_at'], $row['updated_at'], $row['id']]);
    $db->exec('DELETE FROM categories WHERE id > 7');
    $query = $db->prepare('UPDATE categories SET title=?, sort_order=?, is_published=? WHERE id=?');
    foreach ($state['categories'] as $row) $query->execute([$row['title'], $row['sort_order'], $row['is_published'], $row['id']]);
    $db->exec('DELETE FROM media WHERE id > 22');
    foreach ($newMedia as $row) {
        if (str_starts_with($row['path'], '/media.php?file=') && ($path = mediaFile($row)) !== null) unlink($path);
    }
    $db->exec('DELETE FROM admins');
    $db->exec('DELETE FROM settings');
    $db->exec('UPDATE admin_login_limits SET failures=0, window_started=0 WHERE id=1');
    unlink($file);
    echo "داده‌ها و فایل‌های تست مرورگر پاک و داده اولیه بازیابی شد.\n";
} else {
    fwrite(STDERR, "فرمان prepare یا cleanup لازم است.\n"); exit(1);
}
