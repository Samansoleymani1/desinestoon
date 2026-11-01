<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';
require_once dirname(__DIR__, 2) . '/app/media.php';
$db = database(); requireAdmin($db); requireGet();
try { $id = formInteger($_GET, 'id', 1); }
catch (ContentValidation $error) { http_response_code(400); exit('شناسه تصویر معتبر نیست.'); }
$query = $db->prepare('SELECT * FROM media WHERE id = ?');
$query->execute([$id]);
$media = $query->fetch();
if (!$media) { http_response_code(404); exit; }
streamImage($media);
