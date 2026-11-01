# استقرار آینده روی هاست

1. PHP 8.2+ با PDO MySQL، Fileinfo و GD و MySQL 8.0.16+ لازم‌اند؛ پیکربندی تست‌شده PHP 8.4 و MySQL 8.4 است.
2. کل ساختار مخزن را بیرون از ریشه عمومی نگه دارید و document root دامنه را فقط روی `backend/public` قرار دهید. index.php همان index.html اصلی را می‌خواند. `.env`، app، database، scripts، tests و storage عمومی نیستند. حساب دیتابیس وب را مطابق SETUP-WINDOWS.md محدود کنید.
3. Apache باید mod_rewrite، AllowOverride مناسب و DirectoryIndex index.php داشته باشد؛ `.htaccess` موجود، css/js/assets عمومی را با gateway محدود می‌خواند. Nginx باید فقط مسیرهای `/css/`، `/js/` و `/assets/` را به `site-assets.php?path=<مسیر بدون اسلش ابتدایی>` هدایت کند و PHP-FPM فقط اسکریپت‌های موجود public را اجرا کند. مسیر dotfileها بسته باشد. router.php فقط برای سرور توسعه PHP است و در تولید استفاده نمی‌شود.
4. روش جایگزین برای دارایی‌ها، کپی فقط css/js/assets به public در فرایند انتشار است؛ هرگز backend یا کل مخزن را به public کپی نکنید. در این روش نیز درخت اصلی مخزن برای خواندن index.html و تصاویر پیش‌نمایش اولیه موجود بماند. فایل‌های آپلود همچنان در storage و فقط از media.php ارائه می‌شوند.
5. دیتابیس و کل storage/uploads را با هم منتقل کنید؛ برای ارتقا migration مناسب را فقط یک‌بار اجرا کنید. فاز چهارم migration جدید ندارد. account مدیر موجود و تصاویر اولیه را حفظ کنید؛ Seed رکوردهای موجود را بازنویسی نمی‌کند.
6. HTTPS اجباری است. اگر reverse proxy دارید، فقط پراکسی مورد اعتماد HTTPS را در متغیر وب‌سرور منعکس کند؛ header دلخواه کاربر قابل اعتماد نیست. اتصال مستقیم به upstream بسته باشد. کد نباید قابل‌نوشتن توسط PHP باشد؛ فقط storage/uploads و محل نشست مجوز لازم را داشته باشند.
7. upload_max_filesize=10M، post_max_size=12M، memory_limit=256M و display_errors=Off را در PHP و محدودیت مناسب بدنه را در وب‌سرور اعمال کنید. `.user.ini` فقط در تنظیمات سازگار FPM/CGI اعمال می‌شود.
8. سایت و API را روی همان origin و ریشه دامنه اجرا کنید. این نسخه پیکربندی خودکار نصب زیرپوشه یا CORS دامنه جدا ندارد؛ برای دامنه جدا reverse proxy یا تغییر کنترل‌شده لازم است. هیچ credential عمومی در site-config.php قرار نمی‌گیرد.
9. پس از استقرار، CSS/JS، همه APIها، تصویر قدیم و جدید، ورود/خروج، CSRF و رد مسیر خصوصی را smoke-test کنید. تست واقعی این هاست و TLS باید هنگام استقرار انجام شود؛ موفقیت تست محلی جای آن را نمی‌گیرد.

نسخه GitHub Pages مستقل باقی می‌ماند: mode پیش‌فرض static، بدون API و بدون backend در artifact. تا قبل از تأیید استقرار نسخه جدید، نسخه Pages را نگه دارید.
