<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap.php';

function hiddenPassword(string $prompt): string
{
    if (PHP_OS_FAMILY === 'Windows') {
        throw new RuntimeException('از اسکریپت create-admin.ps1 استفاده کنید.');
    }
    if (!stream_isatty(STDIN)) throw new RuntimeException('برای ورودی امن از --password-stdin استفاده کنید.');
    $state = shell_exec('stty -g');
    if (!$state) throw new RuntimeException('پنهان‌سازی ورودی در این ترمینال ممکن نیست.');
    fwrite(STDERR, $prompt);
    exec('stty -echo', $output, $status);
    if ($status !== 0) throw new RuntimeException('پنهان‌سازی ورودی ممکن نیست.');
    try { $line = fgets(STDIN); }
    finally { exec('stty ' . escapeshellarg(trim($state))); fwrite(STDERR, "\n"); }
    if ($line === false) throw new RuntimeException('ورودی دریافت نشد.');
    return rtrim($line, "\r\n");
}
try {
    $username = $argv[1] ?? '';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/D', $username)) {
        throw new RuntimeException('نام کاربری باید ۳ تا ۱۰۰ نویسه لاتین، عدد، نقطه، خط تیره یا زیرخط باشد.');
    }
    if (($argv[2] ?? '') === '--password-stdin') {
        if (stream_isatty(STDIN)) throw new RuntimeException('این گزینه فقط برای ورودی لوله‌ای امن است.');
        $password = rtrim(stream_get_contents(STDIN), "\r\n");
    } else {
        if (count($argv) !== 2) throw new RuntimeException('رمز را در آرگومان دستور قرار ندهید.');
        $password = hiddenPassword('رمز عبور: ');
        if (!hash_equals($password, hiddenPassword('تکرار رمز عبور: '))) throw new RuntimeException('تکرار رمز یکسان نیست.');
    }
    if (preg_match_all('/./us', $password) < 12 || strlen($password) > 72 || strpbrk($password, "\r\n") !== false) {
        throw new RuntimeException('رمز باید حداقل ۱۲ نویسه و حداکثر ۷۲ بایت و بدون خط جدید باشد.');
    }
    $db = database();
    $insert = $db->prepare('INSERT INTO admins (username, password_hash) SELECT ?, ? WHERE NOT EXISTS (SELECT 1 FROM admins)');
    $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    unset($password);
    if ($insert->rowCount() !== 1) throw new RuntimeException('حساب مدیر از قبل وجود دارد؛ هیچ تغییری انجام نشد.');
    echo "حساب مدیر با موفقیت ساخته شد.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "ساخت حساب ناموفق بود؛ اتصال، مجوز یا ساختار دیتابیس را بررسی کنید.\n"); exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, $e instanceof RuntimeException ? $e->getMessage() . "\n" : "ساخت حساب ناموفق بود.\n"); exit(1);
}
