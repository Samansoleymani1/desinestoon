# زیرساخت دیزاینستون — فاز اول

این بخش مستقل از سایت استاتیک است. هیچ تغییری در HTML، JavaScript، CSS و تصاویر فعلی لازم نیست. ورود امن و پنل فقط‌خواندنی در فاز دوم اضافه شده‌اند؛ راهنمای آن‌ها در [ADMIN.md](ADMIN.md) است. آپلود و CRUD محتوا هنوز پیاده‌سازی نشده‌اند. سقف طراحی آپلود آینده ۱۰ مگابایت (۱۰٬۴۸۵٬۷۶۰ بایت) است؛ تنظیمات PHP و وب‌سرور و اعتبارسنجی محتوا باید در همان فاز اعمال شوند.

## ساختار و انتشار امن

- `app/`: تنظیمات، PDO، خواندن کاتالوگ و پاسخ HTTP.
- `database/schema.sql`: ساخت دیتابیس و جدول‌های محتوا و امنیت، مناسب MySQL 8.0.16 به بعد (برای اعمال CHECK).
- `database/initial-data.json`: نسخه دقیق داده‌های فعلی و ترتیب دسته‌های HTML؛ اطلاعات محرمانه ندارد.
- `scripts/seed.php`: ورود اولیه از خط فرمان، بدون بازنویسی رکورد موجود.
- `public/api/`: تنها ورودی‌های عمومی فعلی.
- `storage/`: خروجی خصوصی آینده؛ محتویات آن وارد Git نمی‌شود.
- `tests/`: آزمون تطابق منبع و یکپارچه دیتابیس/API.

فقط `backend/public` باید document root سرویس PHP باشد. `.env`، `app`، `database`، `scripts`، `tests` و `storage` بیرون از مسیر عمومی می‌مانند. هیچ لینک نمادین به فایل خصوصی نسازید. فایل `.htaccess` در ریشه Backend دفاع اضافی Apache است، جایگزین document root صحیح نیست؛ Nginx و سرور داخلی PHP آن را پردازش نمی‌کنند. فهرست پوشه‌ها را در وب‌سرور غیرفعال کنید. مسیر `public/admin` شامل پنل فارسی و احراز هویت فاز دوم است؛ migration و مجوزهای لازم در ADMIN.md توضیح داده شده‌اند.

`_config.yml` ریشه مخزن پوشه `backend` را از ساخت استاندارد Jekyll در GitHub Pages حذف می‌کند. فایل `.nojekyll` اضافه نکنید. اگر انتشار با GitHub Actions سفارشی انجام می‌شود، این تنظیم به‌تنهایی کافی نیست: artifact باید فقط `index.html`، `css/`، `js/` و `assets/` را شامل شود؛ هرگز کل مخزن را upload نکنید. تا زمان آماده‌شدن هاست، سایت همچنان داده را از `js/projects.js` می‌خواند و هیچ درخواست API نمی‌فرستد. فایل PHP روی Pages اجرا نمی‌شود و نباید در خروجی آن باشد. این تغییر نباید پیش از بررسی روش انتشار واقعی ادغام شود.

هنگام استقرار نهایی، سایت استاتیک از ریشه عمومی خودش و API از `backend/public` سرو شود. مسیر تصویر در API عمداً مثل نسخه فعلی `./assets/images/...` است و نسبت به صفحه سایت تفسیر می‌شود، نه نسبت به URL خود API. در فاز اتصال، نشانی پایه تصاویر باید نسبت به دامنه سایت تنظیم شود. روی سرور توسعه API به‌تنهایی تصاویر سایت سرو نمی‌شوند.

## اجرای محلی ویندوز — PowerShell

پیش‌نیاز: PHP 8.2 یا بالاتر با `pdo_mysql`، MySQL 8.0 یا بالاتر و سرویس MySQL فعال؛ Node.js فقط برای آزمون تطابق منبع. می‌توانید PHP و MySQL را با Laragon نصب کنید؛ اگر از XAMPP استفاده می‌کنید توجه کنید دیتابیس همراه آن معمولاً MariaDB است، درحالی‌که هدف این فاز MySQL است. مسیر `php.exe` و `mysql.exe` را در PATH اضافه کنید. در `php.ini` افزونه `extension=pdo_mysql` فعال باشد.

از ریشه مخزن:

```powershell
php --version
php -m | Select-String 'PDO|pdo_mysql'
mysql --version
mysql -u root -p
```

داخل محیط MySQL این دستور را اجرا کنید (در ویندوز نیز مسیر با `/` و مطابق مسیر واقعی مخزن باشد):

```sql
SOURCE C:/projects/desinestoon/backend/database/schema.sql;
CREATE USER 'designestoon_app'@'127.0.0.1' IDENTIFIED BY 'YOUR_LOCAL_PASSWORD';
GRANT SELECT, INSERT ON designestoon_db.* TO 'designestoon_app'@'127.0.0.1';
EXIT;
```

رمز بالا فقط جای‌نگهدار است؛ رمز واقعی را در فایل نسخه‌بندی‌شده یا چت ننویسید. ساخت جدول با حساب مدیریتی انجام می‌شود؛ API به حساب root نیاز ندارد. بعد از Seed در محیط عملیاتی مجوز INSERT حساب API را بردارید:

```sql
REVOKE INSERT ON designestoon_db.* FROM 'designestoon_app'@'127.0.0.1';
```

برای حساب اجرایی، مجوز SELECT روی `categories`، `projects` و `media` کافی است؛ به `admins` نیازی ندارد. برای Seed بعدی از حساب جداگانه با SELECT و INSERT استفاده کنید. مجوزهای نوشتن پنل در فاز بعد جدا تعریف می‌شوند.

```powershell
Copy-Item backend/.env.example backend/.env
notepad backend/.env
php backend/scripts/seed.php
php backend/scripts/seed.php
php -S 127.0.0.1:8080 -t backend/public
```

در `.env` مقدار DB_PASSWORD را وارد کنید. قالب فایل فقط `KEY=value` است، کوتیشن و توضیح انتهای خط پردازش نمی‌شود. متغیر محیطی فرایند بر فایل اولویت دارد. فایل واقعی در Git نادیده گرفته می‌شود. نمونه داده محلی:

```text
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=designestoon_db
DB_USER=designestoon_app
DB_PASSWORD=YOUR_LOCAL_PASSWORD
```

در ترمینال دوم:

```powershell
Invoke-RestMethod http://127.0.0.1:8080/api/categories.php
Invoke-RestMethod http://127.0.0.1:8080/api/projects.php
node backend/tests/source.test.js
```

برای خود سایت همچنان Live Server را از ریشه مخزن اجرا کنید؛ سایت در این فاز به API متصل نیست.

## قرارداد API

- `GET /api/categories.php`: آرایه دسته‌های منتشرشده، مرتب‌شده برحسب `sort_order` سپس `id`.
- `GET /api/projects.php`: آرایه نمونه‌کارهای منتشرشده در دسته منتشرشده، با همان شش فیلد `projects.js` و همان ترتیب.
- پاسخ موفق: HTTP 200 و JSON با UTF-8؛ فهرست خالی `[]` معتبر است.
- روش‌های غیر GET: HTTP 405 با `Allow: GET`.
- خطای اتصال یا خواندن: HTTP 503 و `{"error":"service_unavailable"}` بدون جزئیات داخلی.
- هیچ مسیر نوشتن، CORS عمومی، دریافت رمز یا اطلاعات مدیر وجود ندارد. برای اتصال آینده استفاده از یک دامنه توصیه می‌شود.

نمونه یک عضو از پاسخ دسته‌ها:

```json
{"id":1,"slug":"birthday","title":"جشن تولد","sort_order":1}
```

نمونه یک عضو از پاسخ نمونه‌کارها:

```json
{"id":1,"title":"دیزاین تولد با تم مشکی و طلایی","category":"birthday","image":"./assets/images/project-01.webp","width":1050,"height":1400}
```

## دیتابیس و رفتار Seed

`projects.category_id` به `categories.id` و `projects.media_id` به `media.id` متصل است. حذف مرجع مورد استفاده با `RESTRICT` رد می‌شود. هر نمونه‌کار یک دسته و یک تصویر دارد؛ رسانه می‌تواند مشترک باشد. شناسه متنی دسته و مسیر رسانه یکتا هستند. ایندکس‌ها نمایش منتشرشده، ترتیب و فیلتر دسته را پشتیبانی می‌کنند. `admins` فقط هش رمز را می‌پذیرد؛ هیچ حساب یا رمز پیش‌فرضی ایجاد نمی‌شود. `settings` کلید/مقدار است و هیچ تنظیمی به‌صورت عمومی منتشر نمی‌شود.

Seed تراکنشی است و فقط رکوردهای غایب را درج می‌کند؛ شناسه ۱ تا ۲۲ پروژه‌ها، عنوان، مسیر، ابعاد اعلام‌شده و ترتیب فعلی حفظ می‌شوند. شناسه دسته‌ها مطابق ترتیب دکمه‌های فعلی ۱ تا ۷ است. تصاویر جابه‌جا یا تبدیل نمی‌شوند. داده اولیه از JSON نسخه‌بندی‌شده خوانده می‌شود تا اجرای PHP به Node.js وابسته نباشد؛ آزمون منبع تضمین می‌کند با داده واقعی مخزن منطبق است.

اجرای مجدد هیچ عنوان، دسته، مسیر، ابعاد، وضعیت انتشار یا ترتیب موجودی را تغییر نمی‌دهد. برای غیرفعال‌کردن رکورد از `is_published=0` استفاده کنید؛ حذف فیزیکی رکورد اولیه باعث ورود مجدد آن در Seed بعدی می‌شود. تعارض شناسه/slug به‌جای نادیده‌گرفتن خطا، کل تراکنش را ناموفق می‌کند. Seed را هم‌زمان با Seed دیگر اجرا نکنید. ساختار SQL نصب اولیه است؛ `IF NOT EXISTS` جایگزین migration تغییرات آینده نیست.

## تست یکپارچه

این تست فقط روی دیتابیس خالی و جداگانه `designestoon_test` اجرا می‌شود و داده‌های آزمایشی را تغییر می‌دهد. هرگز آن را روی دیتابیس عملیاتی اجرا نکنید. ابتدا با حساب مدیریتی یک دیتابیس تست خالی بسازید. برای ساخت جداول، نسخه موقت schema را با نام تست تولید کنید؛ schema اصلی تغییر نکند:

```powershell
(Get-Content backend/database/schema.sql -Raw).Replace('designestoon_db','designestoon_test') | Set-Content -Encoding utf8 "$env:TEMP/designestoon-test.sql"
mysql -u root -p
```

در MySQL فایل موقت را با `SOURCE` و مسیر واقعی آن اجرا کنید. به حساب تست فقط روی `designestoon_test` مجوزهای لازم از جمله CREATE، ALTER، SELECT، INSERT، UPDATE و DELETE بدهید. سپس در PowerShell مشخصات حساب تست را به‌صورت محیطی تنظیم کنید (رمز را محلی و امن وارد کنید):

```powershell
$env:DB_NAME='designestoon_test'
$env:DB_USER='YOUR_TEST_USER'
$secret=Read-Host 'رمز حساب تست' -AsSecureString
$env:DB_PASSWORD=[System.Net.NetworkCredential]::new('', $secret).Password
php backend/tests/integration.php
Remove-Item Env:DB_NAME, Env:DB_USER, Env:DB_PASSWORD
```

تست، Seed اولیه و تکراری، حفظ ویرایش مدیر، عدم نمایش رکوردهای غیرفعال، روابط خارجی، پاسخ واقعی HTTP، رد نوشتن، عدم دسترسی به فایل‌های خصوصی و پاسخ امن هنگام خرابی دیتابیس را بررسی می‌کند. پورت ۱۸۰۸۱ باید آزاد باشد. برای تکرار کامل تست، فقط دیتابیس تست را پاک و دوباره ایجاد کنید. هیچ تستی حساب مدیر واقعی ایجاد نمی‌کند.
