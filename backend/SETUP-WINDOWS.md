# راهنمای یکپارچه اجرای سایت و پنل در ویندوز

این راهنما برای همین شاخه `feature/admin-backend` است. بدون هاست و دامنه می‌توانید پنل را باز کنید، تصویر اضافه کنید و با تازه‌کردن سایت، همان تصویر و باکس تماس را ببینید. تمام قسمت‌ها روی یک سرویس محلی PHP اجرا می‌شوند. هیچ رمز یا فایل `.env` نباید در Git قرار بگیرد.

## ۱. نصب ابزارها

پیشنهاد ساده، Laragon با PHP 8.2 یا بالاتر و MySQL 8.4 است؛ حداقل دیتابیس پشتیبانی‌شده MySQL 8.0.16 است. در صورت استفاده از winget:

```powershell
winget install --id Git.Git --exact
winget install --id LeNgocKhoa.Laragon --exact
```

یا نصب‌کننده را از وب‌سایت رسمی Laragon دریافت کنید. نسخه‌های همراه هر نصب‌کننده متفاوت‌اند؛ با فرمان‌های زیر نسخه واقعی را بررسی کنید. اگر MySQL یا PHP مناسب همراه نصب نبود، همان نسخه را از بخش مدیریت ابزارهای Laragon اضافه کنید. هدف تست‌شده این پروژه MySQL است؛ MariaDB همراه XAMPP در این محیط تست نشده است.

دستورهای این راهنما PowerShell هستند. ترمینال Laragon را باز و داخل آن PowerShell اجرا کنید تا PHP و MySQL در PATH باشند. سرویس MySQL را از Laragon اجرا کنید. مخزن را بیرون مسیر عمومی Apache، مثلاً در `C:\projects\desinestoon` نگهداری کنید:

```powershell
git clone --branch feature/admin-backend https://github.com/Samansoleymani1/desinestoon.git C:\projects\desinestoon
cd C:\projects\desinestoon
php --version
mysql --version
php -m | Select-String 'PDO|pdo_mysql|fileinfo|gd'
php -r "print_r(gd_info());"
```

اگر مخزن را دارید، دوباره clone نکنید؛ تغییرات محلی را حفظ و شاخه را بررسی کنید. در php.ini، `pdo_mysql`، `fileinfo` و `gd` باید فعال باشند. GD باید JPEG، PNG و WebP را بخواند؛ پشتیبانی encoder WebP توصیه می‌شود. پس از تغییر php.ini سرور PHP را مجدداً راه‌اندازی کنید. نصب Node.js یا Python برای اجرای معمول سایت لازم نیست؛ فقط برای تست‌ها اختیاری‌اند.

## ۲. نصب تازه یا ارتقای دیتابیس

### نصب تازه

```powershell
mysql -u root -p
```

رمز حساب مدیریتی دیتابیس را در prompt وارد کنید، نه در آرگومان دستور. در MySQL:

```sql
SOURCE C:/projects/desinestoon/backend/database/schema.sql;
EXIT;
```

این فایل دیتابیس `designestoon_db` و ساختار کامل فعلی را می‌سازد. در نصب تازه هیچ migration جداگانه اجرا نکنید.

### ارتقای نسخه موجود

ابتدا نسخه پشتیبان بگیرید. فاز چهارم migration جدید ندارد:

- دیتابیس فاز اول: migrationهای `002_admin_security.sql` و سپس `003_content_management.sql`.
- دیتابیس فاز دوم: فقط `003_content_management.sql`.
- دیتابیس فاز سوم: هیچ migration لازم نیست.

هر migration فقط یک‌بار اجرا شود. مثلاً برای ارتقای فاز دوم، پس از ورود با `mysql -u root -p designestoon_db`:

```sql
SOURCE C:/projects/desinestoon/backend/database/migrations/003_content_management.sql;
EXIT;
```

schema دارای IF NOT EXISTS جای migration را نمی‌گیرد. در MySQL تغییرات ساختار تراکنش مشترک ندارند؛ پس از شکست، وضعیت واقعی ستون‌ها را قبل از تکرار بررسی کنید. هیچ رکورد یا تصویر موجودی را پاک نکنید.

## ۳. حساب‌های دیتابیس و تنظیم اتصال

در MySQL با حساب مدیریتی، دو حساب محلی بسازید. دو عبارت رمز زیر فقط جای‌نگهدارند؛ رمز واقعی انتخاب‌شده را فقط محلی وارد کنید و SQL دارای رمز را در مخزن ذخیره نکنید. اگر حساب‌ها از قبل موجودند آن‌ها را دوباره نسازید و مجوزهایشان را بازبینی کنید.

```sql
CREATE USER 'designestoon_app'@'127.0.0.1' IDENTIFIED BY 'CHOOSE_WEB_PASSWORD';
CREATE USER 'designestoon_setup'@'127.0.0.1' IDENTIFIED BY 'CHOOSE_CLI_PASSWORD';

GRANT SELECT ON designestoon_db.admins TO 'designestoon_app'@'127.0.0.1';
GRANT SELECT, UPDATE ON designestoon_db.admin_login_limits TO 'designestoon_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON designestoon_db.projects TO 'designestoon_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON designestoon_db.categories TO 'designestoon_app'@'127.0.0.1';
GRANT SELECT, INSERT ON designestoon_db.media TO 'designestoon_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON designestoon_db.settings TO 'designestoon_app'@'127.0.0.1';

GRANT SELECT, INSERT ON designestoon_db.categories TO 'designestoon_setup'@'127.0.0.1';
GRANT SELECT, INSERT ON designestoon_db.projects TO 'designestoon_setup'@'127.0.0.1';
GRANT SELECT, INSERT ON designestoon_db.media TO 'designestoon_setup'@'127.0.0.1';
GRANT SELECT, INSERT ON designestoon_db.admins TO 'designestoon_setup'@'127.0.0.1';
```

GRANT محدود، مجوز گسترده قبلی را حذف نمی‌کند؛ به حساب وب مجوز تغییر ساختار یا نوشتن admins ندهید.

در PowerShell ریشه مخزن:

```powershell
if (!(Test-Path backend/.env)) { Copy-Item backend/.env.example backend/.env }
notepad backend/.env
```

فایل را با مشخصات **حساب وب** تنظیم کنید:

```text
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=designestoon_db
DB_USER=designestoon_app
DB_PASSWORD=YOUR_WEB_PASSWORD
```

قالب فایل KEY=value بدون کوتیشن یا توضیح انتهای خط است. متغیر محیطی فرایند بر فایل اولویت دارد؛ تنظیمات باقی‌مانده ترمینال از اجرای تست را بررسی کنید. `.env` بیرون public و در Git نادیده گرفته شده است.

## ۴. Seed و ساخت تنها مدیر

اسکریپت Seed، هفت دسته و ۲۲ نمونه‌کار واقعی را وارد می‌کند. اجرای دوباره، ویرایش مدیر، وضعیت انتشار و بایگانی را تغییر نمی‌دهد.

برای اجرای CLI موقتاً از حساب setup استفاده کنید؛ اطلاعات حساب وب پس از پایان برگردانده می‌شود:

```powershell
$previousUser = $env:DB_USER
$previousPassword = $env:DB_PASSWORD
$secret = Read-Host 'رمز حساب designestoon_setup' -AsSecureString
try {
    $env:DB_USER = 'designestoon_setup'
    $env:DB_PASSWORD = [System.Net.NetworkCredential]::new('', $secret).Password
    php backend/scripts/seed.php
    if ($LASTEXITCODE -ne 0) { throw 'Seed انجام نشد.' }
    # فقط برای نصب تازه؛ اگر مدیر دارید این خط را اجرا نکنید.
    & ./backend/scripts/create-admin.ps1 -Username adminname
} finally {
    $env:DB_USER = $previousUser
    $env:DB_PASSWORD = $previousPassword
    $secret.Dispose()
}
```

نام `adminname` انتخابی شماست. رمز مدیر دو بار به‌صورت مخفی دریافت می‌شود؛ حداقل ۱۲ نویسه و حداکثر ۷۲ بایت، بدون خط جدید. رمز پیش‌فرض یا ثبت‌نام عمومی وجود ندارد و مدیر دوم ساخته نمی‌شود. رمز را در آرگومان، فایل موقت یا چت وارد نکنید. محدودیت اجرای PowerShell را مطابق سیاست سیستم خود حل کنید؛ سیاست امنیتی سراسری را غیرفعال نکنید.

## ۵. اجرای یکپارچه سایت و پنل

```powershell
New-Item -ItemType Directory -Force backend/storage/uploads
php -d upload_max_filesize=10M -d post_max_size=12M -d memory_limit=256M -d display_errors=0 -S 127.0.0.1:8080 -t backend/public backend/router.php
```

**وجود `backend/router.php` در انتهای دستور لازم است.** ریشه سرویس فقط public است؛ هیچ‌وقت کل مخزن را document root سرویس PHP قرار ندهید.

آدرس‌های محلی:

```text
سایت متصل به دیتابیس: http://127.0.0.1:8080/
پنل مدیریت:          http://127.0.0.1:8080/admin/login.php
API نمونه‌کارها:     http://127.0.0.1:8080/api/projects.php
API دسته‌ها:         http://127.0.0.1:8080/api/categories.php
API تماس:            http://127.0.0.1:8080/api/contact.php
```

پس از ورود، دسته و نمونه‌کار جدید را منتشر کنید. تصویر JPEG/PNG/WebP تا ۱۰ مگابایت انتخاب و ذخیره کنید. سایت را تازه کنید و فیلتر مناسب یا «همه» را بزنید؛ قبل از انتخاب فیلتر، گالری مثل نسخه قبلی بسته است. زیر تصویر جدید، باکس تماس با شماره دیتابیس ساخته می‌شود. برای مشاهده تغییرات پنل در صفحه‌ای که از قبل باز است، تازه‌سازی لازم است؛ این نسخه polling ندارد.

دسته غیرفعال، نمونه‌کار منتشرنشده و بایگانی‌شده در سایت نمایش داده نمی‌شوند. بازیابی، نمونه‌کار را منتشر نمی‌کند؛ انتشار مجدد تصمیم جداگانه مدیر است. فایل تصویر قبلی پس از تعویض باقی می‌ماند؛ جزئیات نگهداری و audit در CONTENT.md است.

## ۶. انتخاب حالت اجرا و خطای API

- `index.html` به همراه `js/site-config.js` با `mode: 'static'`: حالت پیش‌فرض GitHub Pages؛ فقط `js/projects.js` و فایل‌های تصاویر، بدون درخواست API.
- `backend/public/index.php`: همان HTML را بدون کپی می‌خواند و فقط منبع تنظیمات را به `site-config.php` تغییر می‌دهد؛ mode برابر api و هر دو مبنای سایت/API برابر ریشه سرویس جاری هستند.
- برای تنظیم دستی، `js/site-config.js` کلیدهای mode، apiBase، siteBase و timeoutMs دارد. apiBase ریشه عمومی Backend است، **نه پوشه /api/**. دو مبنا باید با `/` تمام شوند. تغییر پیش‌فرض static را برای انتشار Pages commit نکنید؛ ورودی PHP نیازی به تغییر این فایل ندارد.
- این نسخه سایت و Backend را روی یک origin اجرا می‌کند؛ هیچ CORS عمومی یا اعتبارنامه cross-origin اضافه نشده است. دامنه جدا در آینده نیازمند reverse proxy یا طراحی CORS محدود است.

سه پاسخ API به‌صورت یک مجموعه معتبر بارگذاری می‌شوند. طی بارگذاری، فیلترها غیرفعال‌اند. خطا، timeout یا پاسخ نامعتبر، گالری را خالی و دکمه «تلاش دوباره» را نشان می‌دهد؛ شماره نامعتبر وارد tel نمی‌شود. در این حالت شماره قبلی سایت نیز به‌عنوان شماره معتبر Backend ارائه نمی‌شود. هیچ cache محلی یا fallback استاتیک در حالت API وجود ندارد. پاسخ موفق `[]` معتبر است و هرگز نمونه‌کارهای قدیمی را برنمی‌گرداند. این انتخاب مانع بازگشت محتوای بایگانی‌شده در زمان قطعی API می‌شود.

تصاویر اولیه نسبت به siteBase و تصاویر جدید `media.php` نسبت به apiBase ساخته می‌شوند؛ هیچ‌کدام نسبت به `/api/` نیستند. مسیر storage یا مسیر فیزیکی ویندوز به مرورگر داده نمی‌شود. CSS، اندازه و کلاس کارت‌ها، منو و پنجره تصاویر حفظ شده‌اند.

## ۷. GitHub Pages و آزمایش استاتیک

انتشار فعلی Pages از index.html و mode استاتیک استفاده می‌کند. `_config.yml` پوشه backend را از ساخت استاندارد Jekyll حذف می‌کند؛ `.nojekyll` اضافه نکنید. اگر Actions سفارشی دارید، artifact فقط شامل index.html، css، js و assets باشد؛ هرگز کل مخزن و backend را upload نکنید. برای حالت Pages نه PHP لازم است و نه دیتابیس و نه کلید یا رمز.

برای اجرای محلی artifact استاتیک، فقط فایل‌های عمومی را به پوشه موقت کپی کنید تا Backend در مسیر عمومی نباشد. با Python نصب‌شده:

```powershell
$staticPath = Join-Path $env:TEMP ('designestoon-static-' + [guid]::NewGuid().ToString())
New-Item -ItemType Directory $staticPath
Copy-Item index.html $staticPath
Copy-Item css, js, assets $staticPath -Recurse
py -m http.server 5500 --bind 127.0.0.1 --directory $staticPath
```

حالت استاتیک تغییرات دیتابیس پنل را نمایش نمی‌دهد؛ برای گردش افزودن تصویر حتماً آدرس سرویس PHP بخش ۵ را باز کنید.

## ۸. تست‌های توسعه

Node.js برای تست منبع داده و Python/Playwright برای تست مرورگر اختیاری‌اند:

```powershell
# فقط اگر این ابزارهای اختیاری را ندارید:
winget install --id OpenJS.NodeJS.LTS --exact
winget install --id Python.Python.3.12 --exact
node backend/tests/source.test.js
node backend/tests/data-source.test.js
py -m pip install playwright
py -m playwright install chromium
```

تست PHP و مرورگر باید فقط روی دیتابیس جداگانه **designestoon_test** باشد. schema جدید را در فایل موقت با این نام بسازید و با حساب مدیریتی MySQL از طریق SOURCE اجرا کنید؛ دیتابیس واقعی را تغییر ندهید. حساب تست باید مجوزهای لازم برای fixtureها، از جمله CREATE TRIGGER و ALTER و DELETE، داشته باشد. سپس متغیرهای DB_NAME و DB_USER و DB_PASSWORD را فقط در ترمینال تست تنظیم کنید؛ رمز را با Read-Host -AsSecureString دریافت کنید.

```powershell
(Get-Content backend/database/schema.sql -Raw).Replace('designestoon_db','designestoon_test') | Set-Content -Encoding utf8 "$env:TEMP/designestoon-test.sql"
```

برای تست مرورگر، دیتابیس تازه باید با Seed دقیق اولیه پر شده و admins و settings خالی باشند. سرور بخش ۵ نیز باید به همین دیتابیس تست متصل باشد. سپس:

```powershell
php backend/scripts/seed.php
py backend/tests/site-browser.py
```

این تست مدیر تصادفی می‌سازد، با پنل واقعی محتوا را تغییر می‌دهد، و پس از پایان رکوردهای اولیه را بازیابی و فایل‌ها و حساب تست را پاک می‌کند. snapshot خصوصی در storage ذخیره می‌شود. در صورت قطع ناگهانی، با همان تنظیمات تست، `php backend/tests/site-fixture.php cleanup` را اجرا کنید. هرگز روی داده عملیاتی یا هم‌زمان با تغییر دستی پنل تست نکنید. برای پورت دیگر، DESIGNESTOON_TEST_URL را تنظیم کنید. برای PHP خارج PATH، DESIGNESTOON_TEST_PHP_COMMAND یک آرایه JSON از فرمان PHP است.

تست‌های قدیمی نیز در دیتابیس تست قابل اجرا هستند:

```powershell
php backend/tests/integration.php
php backend/tests/auth-integration.php
php -d memory_limit=256M backend/tests/content-integration.php
```

تست اول دیتابیس خالی می‌خواهد و داده‌های تست را تغییر می‌دهد. برای اجرای تست مرورگر بعد از این مجموعه، دیتابیس **تست** را دوباره از schema و Seed تازه بسازید؛ fixture مرورگر تطابق دقیق ۲۲ نمونه‌کار و هفت دسته را بررسی می‌کند.

## ۹. عیب‌یابی و محدودیت‌ها

- خطای گالری: پاسخ سه API، فعال‌بودن MySQL، .env و migrationهای لازم را بررسی کنید؛ API fallback استاتیک ندارد.
- خطای CSS/JS یا تصویر قدیمی: سرور داخلی باید با router اجرا شود؛ در Apache، mod_rewrite و AllowOverride لازم‌اند.
- تصویر جدید دیده نمی‌شود: نمونه‌کار و دسته هر دو منتشر باشند و فایل storage/uploads منتقل شده و قابل‌خواندن باشد.
- ورود پس از تلاش‌های ناموفق محدود می‌شود: شمارنده مشترک پنج تلاش در ۱۵ دقیقه است؛ پاک‌کردن کوکی محدودیت را دور نمی‌زند.
- نصب winget/Laragon، خود ویندوز و استقرار واقعی HTTPS در محیط Linux توسعه اجرا نشده‌اند. تست‌های مرورگر، PHP و MySQL روی Linux انجام می‌شوند. بازیابی رمز مدیر و مدیریت تاریخچه رسانه رابط ندارند.
