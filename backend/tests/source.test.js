// بررسی تطابق داده اولیه با فایل‌های واقعی و حفاظت از انتشار Pages
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const data = JSON.parse(read('backend/database/initial-data.json'));
const projects = JSON.parse(JSON.stringify(vm.runInNewContext(read('js/projects.js') + '; projects')));
assert.deepEqual(data.projects, projects);
const categories = [...read('index.html').matchAll(/data-filter="([^"]+)"[^>]*>([^<]+)<\/button>/g)]
  .filter(m => m[1] !== 'all').map((m, i) => ({id:i + 1, slug:m[1], title:m[2], sort_order:i + 1}));
assert.deepEqual(data.categories, categories);
assert.equal(projects.length, 22);
assert.equal(categories.length, 7);
assert.equal(new Set(projects.map(p => p.id)).size, 22);
for (const p of projects) {
  assert(categories.some(c => c.slug === p.category));
  assert(fs.existsSync(path.join(root, p.image)));
  assert(p.width > 0 && p.height > 0);
}
assert.match(read('_config.yml'), /exclude:\s*\n\s*- backend/);
assert(!fs.existsSync(path.join(root, '.nojekyll')), 'برای انتشار از شاخه، پردازش Jekyll باید فعال باشد');
console.log('تطابق ۲۲ نمونه‌کار، ۷ دسته، مسیر تصاویر و تنظیم حذف Backend از Pages تأیید شد.');
