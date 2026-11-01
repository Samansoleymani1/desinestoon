-- روی دیتابیس انتخاب‌شده اجرا شود؛ عمداً USE ندارد تا برای تست هم قابل استفاده باشد.
-- اگر بیش از یک مدیر وجود داشته باشد، افزودن قید یکتا شکست می‌خورد؛ هیچ حسابی حذف نمی‌شود.
ALTER TABLE admins
 ADD COLUMN singleton TINYINT GENERATED ALWAYS AS (1) STORED,
 ADD UNIQUE KEY admins_single_account (singleton);

CREATE TABLE admin_login_limits (
 id TINYINT UNSIGNED PRIMARY KEY,
 failures INT UNSIGNED NOT NULL DEFAULT 0,
 window_started BIGINT UNSIGNED NOT NULL DEFAULT 0,
 CONSTRAINT admin_login_limit_singleton CHECK (id = 1)
) ENGINE=InnoDB;
INSERT INTO admin_login_limits (id) VALUES (1);
