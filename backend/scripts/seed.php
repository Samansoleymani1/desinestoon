<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap.php';

try {
    $data = json_decode(file_get_contents(dirname(__DIR__) . '/database/initial-data.json'), true, 512, JSON_THROW_ON_ERROR);
    $db = database();
    $db->beginTransaction();
    // درج فقط در صورت نبود شناسه؛ هیچ UPDATE یا REPLACE انجام نمی‌شود.
    $category = $db->prepare('INSERT INTO categories (id, slug, title, sort_order)
        SELECT ?, ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM categories WHERE id = ?)');
    foreach ($data['categories'] as $c) {
        $category->execute([$c['id'], $c['slug'], $c['title'], $c['sort_order'], $c['id']]);
    }
    $exists = $db->prepare('SELECT id FROM projects WHERE id = ?');
    $media = $db->prepare('INSERT INTO media (path, mime_type, width, height, alt_text)
        SELECT ?, ?, ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM media WHERE path = ?)');
    $mediaId = $db->prepare('SELECT id FROM media WHERE path = ?');
    $project = $db->prepare('INSERT INTO projects (id, title, category_id, media_id, sort_order) VALUES (?, ?, ?, ?, ?)');
    $categoryIds = array_column($data['categories'], 'id', 'slug');
    foreach ($data['projects'] as $i => $p) {
        $exists->execute([$p['id']]);
        if ($exists->fetchColumn() !== false) continue;
        $media->execute([$p['image'], 'image/webp', $p['width'], $p['height'], $p['title'], $p['image']]);
        $mediaId->execute([$p['image']]);
        $project->execute([$p['id'], $p['title'], $categoryIds[$p['category']], $mediaId->fetchColumn(), $i + 1]);
    }
    $db->commit();
    echo "ورود اطلاعات اولیه کامل شد؛ رکوردهای موجود تغییر نکردند.\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, "ورود اطلاعات ناموفق بود؛ اتصال، ساختار و تعارض شناسه‌ها را بررسی کنید.\n");
    exit(1);
}
