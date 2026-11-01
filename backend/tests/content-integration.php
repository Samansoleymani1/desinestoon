<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/catalog.php';
require_once dirname(__DIR__) . '/app/media.php';

function ensure(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}
ensure(configuration()['DB_NAME'] === 'designestoon_test', 'فقط دیتابیس تست مجاز است.');
$db = database();
ensure((int)$db->query('SELECT COUNT(*) FROM projects')->fetchColumn() === 22, 'ابتدا دیتابیس تست با ۲۲ نمونه‌کار آماده شود.');
ensure((int)$db->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 7, 'تعداد دسته‌های اولیه نادرست است.');
ensure((int)$db->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0, 'مدیر واقعی نباید در دیتابیس تست باشد.');
ensure((int)$db->query('SELECT COUNT(*) FROM settings')->fetchColumn() === 0, 'تنظیمات تست باید خالی باشند.');
$initialProjects = $db->query('SELECT * FROM projects ORDER BY id')->fetchAll();
$initialCategories = $db->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$initialFiles = glob(uploadDirectory() . '/*') ?: [];
$password = bin2hex(random_bytes(20));
$query = $db->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
$query->execute(['contenttest', password_hash($password, PASSWORD_DEFAULT)]);
$db->exec('UPDATE admin_login_limits SET failures = 0, window_started = 0 WHERE id = 1');
$cookie = '';
$token = '';
$server = null;
$createdMedia = [];
$createdProjects = [];
$createdCategories = [];

function http(string $path, string $method = 'GET', array $fields = [], ?array $upload = null, bool $authenticated = true): array
{
    global $cookie;
    $headers = $authenticated ? 'Cookie: ' . $cookie . "\r\n" : '';
    $body = '';
    if ($method === 'POST') {
        if ($upload !== null) {
            $boundary = 'designestoon' . bin2hex(random_bytes(10));
            $headers .= 'Content-Type: multipart/form-data; boundary=' . $boundary . "\r\n";
            foreach ($fields as $name => $value) {
                $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
            }
            $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"image\"; filename=\"{$upload['name']}\"\r\nContent-Type: {$upload['mime']}\r\n\r\n{$upload['bytes']}\r\n--$boundary--\r\n";
        } else {
            $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $body = http_build_query($fields);
        }
    }
    $result = file_get_contents('http://127.0.0.1:18083' . $path, false, stream_context_create(['http' => [
        'method' => $method, 'header' => $headers, 'content' => $body,
        'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 20,
    ]]));
    foreach ($http_response_header as $header) {
        if ($authenticated && preg_match('/^Set-Cookie: (designestoon_admin=[^;]*)/i', $header, $match)) $cookie = $match[1];
    }
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $status);
    return ['status' => (int)$status[1], 'body' => $result, 'headers' => implode("\n", $http_response_header)];
}
function csrfFrom(array $response): string
{
    ensure((bool)preg_match('/name="csrf" value="([a-f0-9]{64})"/', $response['body'], $match), 'توکن موجود نیست.');
    return $match[1];
}
function uploadSample(string $type, int $width = 80, int $height = 60): array
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 230, 40, 120));
    ob_start();
    match ($type) {
        'jpeg' => imagejpeg($image), 'png' => imagepng($image), 'webp' => imagewebp($image),
    };
    $bytes = ob_get_clean();
    imagedestroy($image);
    return ['name' => 'test.' . ($type === 'jpeg' ? 'jpg' : $type), 'mime' => 'image/' . $type, 'bytes' => $bytes];
}
function fields(array $overrides = []): array
{
    global $token;
    return array_replace(['csrf' => $token, 'title' => 'نمونه‌کار تست', 'category_id' => '1', 'sort_order' => '5', 'is_published' => '1'], $overrides);
}
function newProjectId(): int
{
    global $db, $createdProjects, $createdMedia;
    $row = $db->query('SELECT id, media_id FROM projects ORDER BY id DESC LIMIT 1')->fetch();
    $createdProjects[] = (int)$row['id'];
    $createdMedia[] = (int)$row['media_id'];
    return (int)$row['id'];
}
try {
    $server = proc_open([PHP_BINARY, '-d', 'upload_max_filesize=10M', '-d', 'post_max_size=12M',
        '-d', 'memory_limit=256M', '-S', '127.0.0.1:18083', '-t', dirname(__DIR__) . '/public', dirname(__DIR__) . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/designestoon-content-test.log', 'a'],
         2 => ['file', sys_get_temp_dir() . '/designestoon-content-test.log', 'a']], $pipes);
    ensure(is_resource($server), 'سرور تست شروع نشد.');
    for ($i = 0; $i < 40; $i++) {
        $socket = @fsockopen('127.0.0.1', 18083);
        if ($socket) { fclose($socket); break; } usleep(100000);
    }
    $before = count(glob(uploadDirectory() . '/*') ?: []);
    ensure(http('/admin/project-form.php', 'POST', fields(), uploadSample('png'), false)['status'] === 303, 'نوشتن بدون ورود پذیرفته شد.');
    ensure(http('/admin/contact.php', 'POST', [], null, false)['status'] === 303, 'تماس بدون ورود محافظت نشده است.');
    $token = csrfFrom(http('/admin/login.php'));
    ensure(http('/admin/login.php', 'POST', ['csrf' => $token, 'username' => 'contenttest', 'password' => $password])['status'] === 303, 'ورود تست ناموفق بود.');
    $token = csrfFrom(http('/admin/'));
    foreach (['/admin/project-form.php', '/admin/projects.php', '/admin/category-form.php', '/admin/categories.php', '/admin/contact.php'] as $route) {
        ensure(http($route, 'POST', ['csrf' => 'invalid'])['status'] === 403, 'CSRF در مسیر نوشتن بررسی نشد.');
    }
    ensure(http('/admin/project-form.php', 'POST', array_diff_key(fields(), ['csrf' => true]), uploadSample('png'))['status'] === 403, 'آپلود بدون CSRF پذیرفته شد.');
    ensure(count(glob(uploadDirectory() . '/*') ?: []) === $before, 'فایل غیرمجاز ذخیره شد.');
    $defaultContact = json_decode(http('/api/contact.php', authenticated: false)['body'], true);
    ensure($defaultContact['contact_phone_primary'] === '09117901519', 'شماره پیش‌فرض نادرست است.');

    foreach (['jpeg', 'png', 'webp'] as $type) {
        $response = http('/admin/project-form.php', 'POST', fields(['title' => 'تست ' . $type]), uploadSample($type));
        ensure($response['status'] === 303, 'افزودن تصویر معتبر ناموفق بود: ' . $type);
        $id = newProjectId();
        $row = $db->query('SELECT m.* FROM media m JOIN projects p ON p.media_id = m.id WHERE p.id = ' . $id)->fetch();
        ensure((int)$row['width'] === 80 && (int)$row['height'] === 60, 'ابعاد ثبت‌شده نادرست است.');
        ensure($row['mime_type'] === 'image/webp', 'بهینه‌سازی WebP انجام نشد.');
        ensure(http($row['path'], authenticated: false)['status'] === 200, 'مسیر عمومی تصویر کار نمی‌کند.');
        ensure(http('/admin/image.php?id=' . $row['id'])['status'] === 200, 'پیش‌نمایش مدیر کار نمی‌کند.');
    }
    $id = $createdProjects[0];
    $oldMediaId = $createdMedia[0];
    $oldMedia = $db->query('SELECT * FROM media WHERE id = ' . $oldMediaId)->fetch();
    $oldFile = mediaFile($oldMedia);
    ensure(http('/admin/project-form.php?id=' . $id, 'POST', fields(['title' => '<script>تغییر عنوان</script>', 'category_id' => '3', 'sort_order' => '0']), uploadSample('png'))['status'] === 303, 'ویرایش کامل ناموفق بود.');
    $project = $db->query('SELECT * FROM projects WHERE id = ' . $id)->fetch();
    $createdMedia[] = (int)$project['media_id'];
    ensure((int)$project['category_id'] === 3 && (int)$project['sort_order'] === 0 && $project['title'] === '<script>تغییر عنوان</script>', 'ویرایش در دیتابیس ذخیره نشد.');
    ensure(is_file($oldFile), 'تصویر قبلی حذف شد.');
    $response = http('/admin/project-form.php?id=' . $id);
    ensure(str_contains($response['body'], '&lt;script&gt;') && !str_contains($response['body'], '<script>تغییر'), 'XSS در فرم وجود دارد.');
    $path = $db->query('SELECT path FROM media WHERE id = ' . (int)$project['media_id'])->fetchColumn();
    ensure(http($oldMedia['path'], authenticated: false)['status'] === 404, 'تصویر جایگزین‌شده بدون نمونه‌کار فعال عمومی ماند.');
    ensure(http('/admin/project-form.php?id=' . $id, 'POST', fields(['category_id' => '3', 'is_published' => '0']))['status'] === 303, 'لغو انتشار ناموفق بود.');
    ensure(http($path, authenticated: false)['status'] === 404, 'تصویر منتشرنشده عمومی شد.');
    $ids = array_column(json_decode(http('/api/projects.php', authenticated: false)['body'], true), 'id');
    ensure(!in_array($id, $ids, true), 'نمونه‌کار منتشرنشده در API است.');
    ensure(http('/admin/project-form.php?id=' . $id, 'POST', fields(['category_id' => '3']))['status'] === 303, 'انتشار مجدد ناموفق بود.');
    ensure(http('/admin/projects.php', 'POST', ['csrf' => $token, 'id' => $id, 'action' => 'archive'])['status'] === 422, 'بایگانی بدون تأیید انجام شد.');
    ensure(http('/admin/projects.php', 'POST', ['csrf' => $token, 'id' => $id, 'action' => 'archive', 'confirm' => '1'])['status'] === 303, 'بایگانی ناموفق بود.');
    ensure(http('/admin/project-form.php?id=' . $id, 'POST', fields())['status'] === 422, 'ویرایش نمونه‌کار بایگانی‌شده پذیرفته شد.');
    ensure(http('/admin/projects.php', 'POST', ['csrf' => $token, 'id' => $id, 'action' => 'restore', 'confirm' => '1'])['status'] === 303, 'بازیابی ناموفق بود.');
    $project = $db->query('SELECT * FROM projects WHERE id = ' . $id)->fetch();
    ensure($project['archived_at'] === null && (int)$project['is_published'] === 0, 'بازیابی ناخواسته منتشر شد.');

    // Seed، رکورد اولیه بایگانی‌شده را دوباره منتشر نمی‌کند.
    http('/admin/projects.php', 'POST', ['csrf' => $token, 'id' => 1, 'action' => 'archive', 'confirm' => '1']);
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/scripts/seed.php'), $output, $exit);
    ensure($exit === 0, 'Seed تکراری شکست خورد.');
    $original = $db->query('SELECT * FROM projects WHERE id = 1')->fetch();
    ensure($original['archived_at'] !== null && (int)$original['is_published'] === 0, 'Seed بایگانی را منتشر کرد.');

    $filesBeforeFailure = glob(uploadDirectory() . '/*') ?: [];
    $mediaCount = (int)$db->query('SELECT COUNT(*) FROM media')->fetchColumn();
    $badFiles = [
        ['name' => 'evil.jpg', 'mime' => 'image/jpeg', 'bytes' => '<?php echo "malware"; ?>'],
        ['name' => 'evil.php', 'mime' => 'image/png', 'bytes' => uploadSample('png')['bytes']],
        ['name' => 'broken.png', 'mime' => 'image/png', 'bytes' => "\x89PNG\r\n\x1a\ninvalid"],
        uploadSample('png', 9000, 1),
        ['name' => 'large.png', 'mime' => 'image/png', 'bytes' => uploadSample('png')['bytes'] . str_repeat('x', MAX_IMAGE_UPLOAD_BYTES + 1)],
    ];
    foreach ($badFiles as $file) ensure(http('/admin/project-form.php', 'POST', fields(), $file)['status'] === 422, 'فایل نامعتبر یا بزرگ پذیرفته شد.');
    ensure(http('/admin/project-form.php', 'POST', fields(['category_id' => '999999']), uploadSample('png'))['status'] === 422, 'دسته ناموجود پذیرفته شد.');
    ensure(http('/admin/project-form.php', 'POST', fields(['sort_order' => '-1']), uploadSample('png'))['status'] === 422, 'ترتیب منفی پذیرفته شد.');
    $db->exec("CREATE TRIGGER designestoon_fail_insert BEFORE INSERT ON projects FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test failure'");
    try {
        $failure = http('/admin/project-form.php', 'POST', fields(), uploadSample('png'));
        ensure($failure['status'] === 503 && !str_contains($failure['body'], 'test failure'), 'خطای داخلی افشا شد یا عملیات شکست نخورد.');
    } finally { $db->exec('DROP TRIGGER designestoon_fail_insert'); }

    ensure((glob(uploadDirectory() . '/*') ?: []) === $filesBeforeFailure, 'فایل ناقص پس از شکست باقی ماند.');
    ensure((int)$db->query('SELECT COUNT(*) FROM media')->fetchColumn() === $mediaCount, 'رکورد رسانه ناقص باقی ماند.');
    // payload اضافی در تصویر معتبر بازرمزگذاری و حذف می‌شود، نه اینکه خام ذخیره شود.
    $polyglot = uploadSample('png');
    $polyglot['bytes'] .= '<?php echo "hidden-payload"; ?>';
    ensure(http('/admin/project-form.php', 'POST', fields(), $polyglot)['status'] === 303, 'پاک‌سازی تصویر معتبر شکست خورد.');
    $polyglotId = newProjectId();
    $clean = $db->query('SELECT m.* FROM media m JOIN projects p ON p.media_id = m.id WHERE p.id = ' . $polyglotId)->fetch();
    ensure(!str_contains(file_get_contents(mediaFile($clean)), 'hidden-payload'), 'payload اضافی ذخیره شد.');

    ensure(http('/admin/category-form.php?id=7', 'POST', ['csrf' => $token, 'title' => 'ویرایش دسته اولیه', 'slug' => 'do-not-change', 'sort_order' => '88', 'is_published' => '1'])['status'] === 303, 'ویرایش دسته اولیه ناموفق بود.');
    ensure($db->query('SELECT slug FROM categories WHERE id = 7')->fetchColumn() === 'props', 'slug دسته اولیه تغییر کرد.');
    ensure(http('/admin/category-form.php', 'POST', ['csrf' => $token, 'title' => 'دسته تست', 'slug' => 'test-category', 'sort_order' => '0', 'is_published' => '1'])['status'] === 303, 'ساخت دسته ناموفق بود.');
    $categoryId = (int)$db->query("SELECT id FROM categories WHERE slug = 'test-category'")->fetchColumn();
    $createdCategories[] = $categoryId;
    ensure(http('/admin/category-form.php?id=' . $categoryId, 'POST', ['csrf' => $token, 'title' => 'دسته تغییرکرده', 'slug' => 'changed-slug', 'sort_order' => '9', 'is_published' => '0'])['status'] === 303, 'ویرایش دسته ناموفق بود.');
    $category = $db->query('SELECT * FROM categories WHERE id = ' . $categoryId)->fetch();
    ensure($category['slug'] === 'test-category' && (int)$category['sort_order'] === 9 && (int)$category['is_published'] === 0, 'شناسه یا وضعیت دسته نادرست است.');
    ensure(http('/admin/project-form.php?id=' . $id, 'POST', fields(['category_id' => (string)$categoryId]))['status'] === 303, 'تغییر دسته نمونه‌کار شکست خورد.');
    $ids = array_column(json_decode(http('/api/projects.php', authenticated: false)['body'], true), 'id');
    ensure(!in_array($id, $ids, true), 'دسته غیرفعال در API نشت کرد.');
    ensure(http('/admin/categories.php', 'POST', ['csrf' => $token, 'id' => $categoryId, 'confirm' => '1'])['status'] === 422, 'دسته دارای وابستگی حذف شد.');
    ensure(http('/admin/category-form.php', 'POST', ['csrf' => $token, 'title' => 'تکراری', 'slug' => 'birthday', 'sort_order' => '1'])['status'] === 409, 'slug تکراری پذیرفته شد.');
    ensure(http('/admin/category-form.php', 'POST', ['csrf' => $token, 'title' => 'خالی', 'slug' => 'empty-test', 'sort_order' => '1'])['status'] === 303, 'ساخت دسته خالی ناموفق بود.');
    $empty = (int)$db->query("SELECT id FROM categories WHERE slug = 'empty-test'")->fetchColumn();
    $createdCategories[] = $empty;
    ensure(http('/admin/categories.php', 'POST', ['csrf' => $token, 'id' => $empty, 'confirm' => '1'])['status'] === 303, 'حذف دسته بدون وابستگی ناموفق بود.');

    $contact = ['csrf' => $token, 'contact_phone_primary' => '+98 912 345 6789', 'contact_phone_secondary' => '', 'contact_instagram' => 'https://www.instagram.com/test_account/'];
    ensure(http('/admin/contact.php', 'POST', $contact)['status'] === 303, 'ذخیره تماس شکست خورد.');
    $saved = json_decode(http('/api/contact.php', authenticated: false)['body'], true);
    ensure($saved['contact_phone_primary'] === '09123456789', 'شماره ذخیره یا نرمال نشد.');
    ensure(str_contains(http('/admin/contact.php')['body'], '09123456789'), 'تماس پس از بارگذاری مجدد حفظ نشد.');
    $contact['contact_phone_primary'] = '<script>bad</script>';
    ensure(http('/admin/contact.php', 'POST', $contact)['status'] === 422, 'شماره نامعتبر پذیرفته شد.');
    ensure(contactSettings($db) === $saved, 'تماس معتبر در خطا تغییر کرد.');
    ensure(json_decode(http('/api/projects.php', authenticated: false)['body'], true) === publishedProjects($db), 'خروجی API پس از تغییرات نادرست است.');
    ensure(json_decode(http('/api/categories.php', authenticated: false)['body'], true) === publishedCategories($db), 'API دسته‌ها نادرست است.');
    foreach (['/api/contact.php', '/media.php'] as $route) ensure(http($route, 'POST', [], null, false)['status'] === 405, 'مسیر عمومی نوشتن را پذیرفت.');
    ensure(http('/media.php?file=../../app/bootstrap.php', authenticated: false)['status'] === 404, 'پیمایش مسیر پذیرفته شد.');
    session_name('designestoon_admin'); session_id(explode('=', $cookie, 2)[1]); session_start();
    $_SESSION['last_activity'] = time() - 1801; session_write_close();
    ensure(http('/admin/project-form.php', 'POST', fields(), uploadSample('png'))['status'] === 303, 'نشست منقضی نوشتن انجام داد.');
    echo "تست محتوایی: افزودن/ویرایش، آپلود JPEG/PNG/WebP، بایگانی/بازیابی، Seed، دسته‌ها، تماس، API و مجوزهای نوشتن موفق بود.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    // فقط رکوردها و فایل‌های ساخته‌شده این آزمون پاک می‌شوند؛ داده اولیه حفظ می‌شود.
    foreach ($createdProjects as $id) $db->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
    foreach ($createdMedia as $id) {
        $query = $db->prepare('SELECT * FROM media WHERE id = ?'); $query->execute([$id]);
        $row = $query->fetch();
        if ($row && ($file = mediaFile($row)) !== null) unlink($file);
        $db->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);
    }
    foreach ($createdCategories as $id) $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
    $query = $db->prepare('UPDATE projects SET title = ?, category_id = ?, media_id = ?, sort_order = ?, is_published = ?, archived_at = ?, updated_at = ? WHERE id = ?');
    foreach ($initialProjects as $row) $query->execute([$row['title'], $row['category_id'], $row['media_id'], $row['sort_order'], $row['is_published'], $row['archived_at'], $row['updated_at'], $row['id']]);
    $query = $db->prepare('UPDATE categories SET title = ?, sort_order = ?, is_published = ? WHERE id = ?');
    foreach ($initialCategories as $row) $query->execute([$row['title'], $row['sort_order'], $row['is_published'], $row['id']]);
    ensure($db->query('SELECT * FROM projects ORDER BY id')->fetchAll() === $initialProjects, 'رکوردهای اولیه تغییر ناخواسته کردند.');
    ensure($db->query('SELECT * FROM categories ORDER BY id')->fetchAll() === $initialCategories, 'دسته‌های اولیه تغییر ناخواسته کردند.');
    $db->exec('DELETE FROM settings');
    $db->exec('DELETE FROM admins');
    $db->exec('UPDATE admin_login_limits SET failures = 0, window_started = 0 WHERE id = 1');
    ensure((int)$db->query('SELECT COUNT(*) FROM projects')->fetchColumn() === 22 &&
        (int)$db->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 7, 'داده اولیه حفظ نشد.');
}
