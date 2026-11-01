<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/content.php';
ini_set('display_errors', '0');

function respondWithCatalog(string $resource): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        header('Allow: GET');
        http_response_code(405);
        echo '{"error":"method_not_allowed"}';
        return;
    }
    try {
        $db = database();
        $data = match ($resource) {
            'categories' => publishedCategories($db),
            'projects' => publishedProjects($db),
            'contact' => contactSettings($db),
        };
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        // جزئیات اتصال و رمز هرگز در پاسخ یا گزارش عمومی درج نمی‌شوند.
        http_response_code(503);
        echo '{"error":"service_unavailable"}';
    }
}
