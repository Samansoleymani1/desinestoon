<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require_once dirname(__DIR__) . '/app/site-assets.php';
$path = $_GET['path'] ?? '';
if (!is_string($path)) { http_response_code(404); exit; }
serveSiteAsset($path);
