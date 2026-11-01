<?php
declare(strict_types=1);

function publishedCategories(PDO $db): array
{
    $rows = $db->query('SELECT id, slug, title, sort_order FROM categories WHERE is_published = 1 ORDER BY sort_order, id')->fetchAll();
    return array_map(static fn(array $r): array => [
        'id' => (int)$r['id'], 'slug' => $r['slug'], 'title' => $r['title'],
        'sort_order' => (int)$r['sort_order'],
    ], $rows);
}

function publishedProjects(PDO $db): array
{
    $rows = $db->query('SELECT p.id, p.title, c.slug AS category, m.path AS image, m.width, m.height
        FROM projects p JOIN categories c ON c.id = p.category_id JOIN media m ON m.id = p.media_id
        WHERE p.is_published = 1 AND c.is_published = 1 ORDER BY p.sort_order, p.id')->fetchAll();
    return array_map(static fn(array $r): array => [
        'id' => (int)$r['id'], 'title' => $r['title'], 'category' => $r['category'],
        'image' => $r['image'], 'width' => (int)$r['width'], 'height' => (int)$r['height'],
    ], $rows);
}
