<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/admin.php';
$db = database(); requireAdmin($db, true);
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); header('Allow: GET'); echo '{"error":"method_not_allowed"}'; exit;
}
$rows = $db->query('SELECT COUNT(*) AS total, COALESCE(SUM(is_published = 1 AND archived_at IS NULL), 0) AS published FROM projects')->fetch();
echo json_encode(['projects' => (int)$rows['total'], 'published' => (int)$rows['published'],
    'categories' => (int)$db->query('SELECT COUNT(*) FROM categories')->fetchColumn()], JSON_THROW_ON_ERROR);
