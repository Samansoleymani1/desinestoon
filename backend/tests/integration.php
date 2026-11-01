<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/catalog.php';

function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function seed(): void {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/scripts/seed.php'), $exit);
    check($exit === 0, 'Seed failed');
}
// این تست داده‌ها را تغییر می‌دهد و فقط دیتابیس اختصاصی تست را می‌پذیرد.
$c = configuration();
check($c['DB_NAME'] === 'designestoon_test', 'Use only designestoon_test');
$db = database();
check((int)$db->query('SELECT COUNT(*) FROM projects')->fetchColumn() === 0, 'Test database must be empty');
$fixture = json_decode(file_get_contents(dirname(__DIR__) . '/database/initial-data.json'), true, 512, JSON_THROW_ON_ERROR);
seed();
check(publishedProjects($db) === $fixture['projects'], 'Project data/order mismatch');
check(publishedCategories($db) === $fixture['categories'], 'Category data/order mismatch');
seed();
check((int)$db->query('SELECT COUNT(*) FROM projects')->fetchColumn() === 22, 'Duplicate projects');
check((int)$db->query('SELECT COUNT(*) FROM media')->fetchColumn() === 22, 'Duplicate media');
$db->exec("UPDATE projects SET title = 'ویرایش مدیر', sort_order = 99, is_published = 0 WHERE id = 1");
$db->exec("UPDATE categories SET title = 'دسته ویرایش‌شده' WHERE id = 1");
$db->exec("UPDATE media SET width = 777 WHERE id = (SELECT media_id FROM projects WHERE id = 1)");
seed();
check($db->query('SELECT title FROM projects WHERE id = 1')->fetchColumn() === 'ویرایش مدیر', 'Seed overwrote project');
check((int)$db->query('SELECT sort_order FROM projects WHERE id = 1')->fetchColumn() === 99, 'Seed overwrote ordering');
check($db->query('SELECT title FROM categories WHERE id = 1')->fetchColumn() === 'دسته ویرایش‌شده', 'Seed overwrote category');
check((int)$db->query('SELECT width FROM media WHERE id = (SELECT media_id FROM projects WHERE id = 1)')->fetchColumn() === 777, 'Seed overwrote media');
check(count(publishedProjects($db)) === 21, 'Unpublished project leaked');
$db->exec('UPDATE categories SET is_published = 0 WHERE id = 2');
check(count(publishedCategories($db)) === 6, 'Hidden category leaked');
check(count(publishedProjects($db)) === 18, 'Projects in hidden category leaked');
try {
    $db->exec('DELETE FROM media WHERE id = (SELECT media_id FROM projects WHERE id = 1)');
    throw new RuntimeException('Foreign key not enforced');
} catch (PDOException $e) { check($e->getCode() === '23000', 'Unexpected DB error'); }

$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:18081', '-t', dirname(__DIR__) . '/public', dirname(__DIR__) . '/router.php'],
    [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/designestoon-test-server.log', 'a'],
     2 => ['file', sys_get_temp_dir() . '/designestoon-test-server.log', 'a']], $pipes);
check(is_resource($server), 'Server did not start');
try {
    for ($i = 0; $i < 40; $i++) {
        $socket = @fsockopen('127.0.0.1', 18081);
        if ($socket) { fclose($socket); break; }
        usleep(100000);
    }
    foreach (['projects', 'categories'] as $resource) {
        $body = file_get_contents("http://127.0.0.1:18081/api/$resource.php");
        check(str_contains($http_response_header[0], '200'), 'GET status');
        $expected = $resource === 'projects' ? publishedProjects($db) : publishedCategories($db);
        check(json_decode($body, true, 512, JSON_THROW_ON_ERROR) === $expected, 'HTTP data mismatch');
    }
    foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
        $ctx = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true]]);
        $body = file_get_contents('http://127.0.0.1:18081/api/projects.php', false, $ctx);
        check(str_contains($http_response_header[0], '405'), 'Write method accepted');
    }
    foreach (['/.env', '/database/schema.sql', '/scripts/seed.php', '/app/bootstrap.php'] as $private) {
        @file_get_contents('http://127.0.0.1:18081' . $private, false,
            stream_context_create(['http' => ['ignore_errors' => true]]));
        check(str_contains($http_response_header[0], '404'), 'Private file exposed');
    }
    // شبیه‌سازی قطعی اتصال بدون درج رمز در خروجی
    $db->exec('RENAME TABLE projects TO projects_offline');
    $body = file_get_contents('http://127.0.0.1:18081/api/projects.php', false,
        stream_context_create(['http' => ['ignore_errors' => true]]));
    check(str_contains($http_response_header[0], '503'), 'Failure status mismatch');
    check($body === '{"error":"service_unavailable"}', 'Internal details leaked');
    $db->exec('RENAME TABLE projects_offline TO projects');
    echo "آزمون دیتابیس، Seed تکرارپذیر، حفظ ویرایش، انتشار، روابط و HTTP موفق بود.\n";
} finally { proc_terminate($server); proc_close($server); }
