<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
function verify(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$c = configuration();
verify($c['DB_NAME'] === 'designestoon_test', 'فقط دیتابیس تست مجاز است.');
$db = database();
$db->exec('DELETE FROM admins');
$db->exec('UPDATE admin_login_limits SET failures = 0, window_started = 0 WHERE id = 1');
$password = bin2hex(random_bytes(20));
function createAccount(string $password): int {
    $p = proc_open([PHP_BINARY, dirname(__DIR__) . '/scripts/create-admin.php', 'testadmin', '--password-stdin'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fwrite($pipes[0], $password . "\n"); fclose($pipes[0]);
    stream_get_contents($pipes[1]); fclose($pipes[1]);
    stream_get_contents($pipes[2]); fclose($pipes[2]);
    return proc_close($p);
}
function request(string $path, string &$cookie, string $method = 'GET', array $data = []): array {
    $headers = 'Cookie: ' . $cookie . "\r\n";
    if ($method === 'POST') $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $context = stream_context_create(['http' => ['method' => $method, 'header' => $headers,
        'content' => $method === 'POST' ? http_build_query($data) : '',
        'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]]);
    $body = file_get_contents('http://127.0.0.1:18082' . $path, false, $context);
    foreach ($http_response_header as $header) {
        if (preg_match('/^Set-Cookie: (designestoon_admin=[^;]*)/i', $header, $m)) $cookie = $m[1];
    }
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $status);
    return ['status' => (int)$status[1], 'body' => $body, 'headers' => implode("\n", $http_response_header)];
}
function token(array $response): string {
    verify((bool)preg_match('/name="csrf" value="([a-f0-9]{64})"/', $response['body'], $m), 'توکن CSRF موجود نیست.');
    return $m[1];
}
function login(string &$cookie, string $password): array {
    $r = request('/admin/login.php', $cookie);
    return request('/admin/login.php', $cookie, 'POST', ['csrf' => token($r), 'username' => 'testadmin', 'password' => $password]);
}
$server = null;
try {
    verify(createAccount('short') !== 0, 'رمز ضعیف پذیرفته شد.');
    verify(createAccount($password) === 0, 'ساخت مدیر شکست خورد.');
    verify(createAccount($password) !== 0, 'مدیر دوم ساخته شد.');
    $row = $db->query('SELECT * FROM admins')->fetch();
    verify(password_verify($password, $row['password_hash']) && $row['password_hash'] !== $password, 'هش نامعتبر است.');
    try {
        $q = $db->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
        $q->execute(['otheradmin', $row['password_hash']]);
        throw new RuntimeException('قید تک‌مدیر اجرا نشد.');
    } catch (PDOException $e) { verify($e->getCode() === '23000', 'خطای دیتابیس غیرمنتظره.'); }
    $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:18082', '-t', dirname(__DIR__) . '/public', dirname(__DIR__) . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/designestoon-auth-test.log', 'a'],
         2 => ['file', sys_get_temp_dir() . '/designestoon-auth-test.log', 'a']], $pipes);
    verify(is_resource($server), 'سرور شروع نشد.');
    for ($i = 0; $i < 40; $i++) {
        $s = @fsockopen('127.0.0.1', 18082);
        if ($s) { fclose($s); break; } usleep(100000);
    }
    $anonymous = '';
    foreach (['', 'projects.php', 'categories.php', 'category-form.php', 'project-form.php', 'image.php', 'contact.php', 'logout.php'] as $path) {
        $r = request('/admin/' . $path, $anonymous);
        verify($r['status'] === 303 && str_contains($r['headers'], 'Location: /admin/login.php'), 'صفحه ناشناس محافظت نشده است.');
    }
    verify(request('/admin/api/summary.php', $anonymous)['status'] === 401, 'API ناشناس محافظت نشده است.');
    verify(request('/api/projects.php', $anonymous)['status'] === 200, 'API عمومی خراب شده است.');
    $cookie = 'designestoon_admin=aaaaaaaaaaaaaaaaaaaaaaaaaa';
    $r = request('/admin/login.php', $cookie);
    verify($cookie !== 'designestoon_admin=aaaaaaaaaaaaaaaaaaaaaaaaaa', 'نشست جعلی پذیرفته شد.');
    verify(str_contains($r['headers'], 'HttpOnly') && str_contains($r['headers'], 'SameSite=Strict') && str_contains($r['headers'], 'path=/admin/'), 'کوکی امن تنظیم نشده است.');
    $preLogin = $cookie; $csrf = token($r);
    verify(request('/admin/login.php', $cookie, 'POST', ['username' => 'testadmin', 'password' => $password])['status'] === 403, 'ورود بدون CSRF پذیرفته شد.');
    verify(request('/admin/login.php', $cookie, 'POST', ['csrf' => $csrf, 'username' => "' OR 1=1 --", 'password' => $password])['status'] === 401, 'تزریق SQL در ورود پذیرفته شد.');
    verify(request('/admin/login.php', $cookie, 'POST', ['csrf' => $csrf, 'username' => 'testadmin', 'password' => $password])['status'] === 303, 'ورود معتبر شکست خورد.');
    verify($cookie !== $preLogin, 'شناسه نشست عوض نشد.');
    $r = request('/admin/', $cookie); $logoutToken = token($r);
    verify($r['status'] === 200 && str_contains($r['body'], 'با موفقیت'), 'داشبورد/پیام ورود موجود نیست.');
    verify($logoutToken !== $csrf, 'توکن پس از ورود تغییر نکرد.');
    verify(request('/admin/api/summary.php', $cookie)['status'] === 200, 'API مدیر مجاز نیست.');
    foreach (['projects.php', 'categories.php', 'project-form.php', 'project-form.php?id=1', 'contact.php'] as $path) {
        $r = request('/admin/' . $path, $cookie);
        verify($r['status'] === 200, 'صفحه مدیریت کار نمی‌کند.');
        if (str_contains($path, 'form') || $path === 'contact.php') verify(str_contains($r['body'], 'method="post"') && str_contains($r['body'], 'name="csrf"'), 'فرم نوشتن محافظت نشده است.');
    }
    verify(request('/admin/project-form.php?id=invalid', $cookie)['status'] === 400, 'شناسه نامعتبر پذیرفته شد.');
    verify(request('/admin/project-form.php?id=99999999', $cookie)['status'] === 404, 'رکورد ناموجود تشخیص داده نشد.');
    verify(request('/admin/projects.php', $cookie, 'POST', ['title' => 'تغییر ناخواسته'])['status'] === 403, 'نوشتن بدون CSRF پذیرفته شد.');
    $oldTitle = $db->query('SELECT title FROM projects WHERE id = 1')->fetchColumn();
    $q = $db->prepare('UPDATE projects SET title = ? WHERE id = 1');
    $q->execute(['<script>alert("x")</script>']);
    try {
        foreach (['projects.php', 'project-form.php?id=1'] as $path) {
            $r = request('/admin/' . $path, $cookie);
            verify(!str_contains($r['body'], '<script>') && str_contains($r['body'], '&lt;script&gt;'), 'XSS در عنوان.');
        }
    } finally { $q->execute([$oldTitle]); }
    verify(request('/admin/logout.php', $cookie)['status'] === 405, 'خروج با GET فعال است.');
    verify(request('/admin/logout.php', $cookie, 'POST', ['csrf' => 'wrong'])['status'] === 403, 'خروج بدون CSRF پذیرفته شد.');
    $replay = $cookie;
    verify(request('/admin/logout.php', $cookie, 'POST', ['csrf' => $logoutToken])['status'] === 303, 'خروج معتبر شکست خورد.');
    verify(request('/admin/api/summary.php', $replay)['status'] === 401, 'نشست پس از خروج قابل استفاده است.');
    for ($i = 0; $i < 5; $i++) { $fresh = ''; verify(login($fresh, 'incorrect-password')['status'] === 401, 'شمارش تلاش نادرست.'); }
    $fresh = ''; verify(login($fresh, $password)['status'] === 429, 'محدودیت ورود با کوکی تازه دور زده شد.');
    $db->exec('UPDATE admin_login_limits SET window_started = 1 WHERE id = 1');
    $cookie = ''; verify(login($cookie, $password)['status'] === 303, 'پایان بازه محدودیت کار نمی‌کند.');
    $db->exec('UPDATE admins SET is_active = 0');
    verify(request('/admin/api/summary.php', $cookie)['status'] === 401, 'نشست مدیر غیرفعال معتبر ماند.');
    $db->exec('UPDATE admins SET is_active = 1');
    $cookie = ''; verify(login($cookie, $password)['status'] === 303, 'ورود مجدد شکست خورد.');
    session_name('designestoon_admin'); session_id(explode('=', $cookie, 2)[1]); session_start();
    $_SESSION['last_activity'] = time() - 1801; session_write_close();
    verify(request('/admin/api/summary.php', $cookie)['status'] === 401, 'نشست منقضی معتبر ماند.');
    $cookie = ''; verify(login($cookie, $password)['status'] === 303, 'ورود برای تست انقضای مطلق شکست خورد.');
    session_id(explode('=', $cookie, 2)[1]); session_start();
    $_SESSION['authenticated_at'] = time() - 28801; session_write_close();
    verify(request('/admin/api/summary.php', $cookie)['status'] === 401, 'انقضای مطلق کار نمی‌کند.');
    echo "تست احراز هویت، تک‌مدیر، CSRF، محدودیت ورود، نشست، XSS و دسترسی‌ها موفق بود.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $db->exec('DELETE FROM admins');
    $db->exec('UPDATE admin_login_limits SET failures = 0, window_started = 0 WHERE id = 1');
}
