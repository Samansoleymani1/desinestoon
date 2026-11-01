<?php
declare(strict_types=1);

// متغیرهای واقعی فرایند بر فایل محلی اولویت دارند. فایل فقط key=value است.
function configuration(): array
{
    $local = [];
    $file = dirname(__DIR__) . '/.env';
    if (is_file($file)) {
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!preg_match('/^([A-Z_][A-Z0-9_]*)=(.*)$/', $line, $match)) {
                throw new RuntimeException('Invalid environment configuration');
            }
            $local[$match[1]] = $match[2];
        }
    }
    $config = [];
    foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $name) {
        $value = getenv($name);
        $config[$name] = $value === false ? ($local[$name] ?? '') : $value;
    }
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/D', $config['DB_HOST']) ||
        !preg_match('/^[a-zA-Z0-9_]+$/D', $config['DB_NAME']) ||
        !ctype_digit($config['DB_PORT']) || (int)$config['DB_PORT'] < 1 ||
        (int)$config['DB_PORT'] > 65535 || $config['DB_USER'] === '') {
        throw new RuntimeException('Missing or invalid database configuration');
    }
    return $config;
}

function database(): PDO
{
    $c = configuration();
    return new PDO(
        "mysql:host={$c['DB_HOST']};port={$c['DB_PORT']};dbname={$c['DB_NAME']};charset=utf8mb4",
        $c['DB_USER'], $c['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
}

// سقف معماری برای فاز آپلود؛ هنوز هیچ مسیر آپلودی وجود ندارد.
const MAX_IMAGE_UPLOAD_BYTES = 10 * 1024 * 1024;
