'use strict';
// پیش‌نمایش محلی؛ هیچ فایل پیش از ارسال فرم به سرور فرستاده نمی‌شود.
const fileInput = document.querySelector('#image');
const preview = document.querySelector('#image-preview');
const message = document.querySelector('#image-message');
let previewUrl;
fileInput?.addEventListener('change', () => {
  if (previewUrl) URL.revokeObjectURL(previewUrl);
  preview.hidden = true;
  message.textContent = '';
  const file = fileInput.files[0];
  if (!file) return;
  if (file.size > 10 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    message.textContent = 'یک تصویر JPEG، PNG یا WebP با حجم حداکثر ۱۰ مگابایت انتخاب کنید.';
    fileInput.value = '';
    return;
  }
  previewUrl = URL.createObjectURL(file);
  preview.src = previewUrl;
  preview.hidden = false;
});
