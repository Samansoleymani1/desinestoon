-- یک‌بار، پس از migration فاز دوم، روی دیتابیس انتخاب‌شده اجرا شود.
ALTER TABLE projects
 ADD COLUMN archived_at DATETIME NULL DEFAULT NULL,
 ADD INDEX projects_visible_order (archived_at, is_published, sort_order, id);
ALTER TABLE media ADD COLUMN byte_size BIGINT UNSIGNED NULL DEFAULT NULL;
