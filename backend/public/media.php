<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require_once dirname(__DIR__) . '/app/media.php';
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
$file = $_GET['file'] ?? '';
if (!is_string($file) || !preg_match('/^[a-f0-9]{32}\.(webp|jpg|png)$/D', $file)) { http_response_code(404); exit; }
try {
    $db = database();
    $query = $db->prepare('SELECT m.* FROM media m WHERE m.path = ? AND EXISTS
        (SELECT 1 FROM projects p JOIN categories c ON c.id = p.category_id
         WHERE p.media_id = m.id AND p.is_published = 1 AND p.archived_at IS NULL AND c.is_published = 1)');
    $query->execute(['/media.php?file=' . $file]);
    $media = $query->fetch();
    if (!$media) { http_response_code(404); exit; }
    streamImage($media);
} catch (Throwable $error) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"error":"service_unavailable"}';
}
