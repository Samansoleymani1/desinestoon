"""آزمون واقعی سایت/پنل؛ فقط دیتابیس تازه designestoon_test و سرور فعال روی همان دیتابیس."""
import functools
import http.server
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import sys
import tempfile
import threading

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2]
BASE = os.environ.get('DESIGNESTOON_TEST_URL', 'http://127.0.0.1:8080/').rstrip('/') + '/'
PHP = json.loads(os.environ.get('DESIGNESTOON_TEST_PHP_COMMAND', '["php"]'))
FIXTURE = json.loads((ROOT / 'backend/database/initial-data.json').read_text(encoding='utf-8'))


def php(*args, value=None):
    return subprocess.run(PHP + list(args), cwd=ROOT, input=value, text=True, capture_output=True, check=True)


def ready(page):
    page.locator('#portfolio-grid[aria-busy=false]').wait_for()
    assert page.locator('[data-filter=all]').is_enabled()
    assert page.locator('.portfolio-card').count() == 0


def all_cards(page, count):
    page.locator('[data-filter=all]').click()
    assert page.locator('.portfolio-card').count() == count
    assert page.locator('.rental-product-contact').count() == count


def published(panel, project_id, checked):
    panel.goto(BASE + 'admin/project-form.php?id=' + project_id)
    panel.locator('input[name=is_published]').set_checked(checked)
    panel.get_by_role('button', name='ذخیره نمونه‌کار').click()
    panel.wait_for_load_state('networkidle')


def category_active(panel, category_id, checked):
    panel.goto(BASE + 'admin/category-form.php?id=' + category_id)
    panel.locator('input[name=is_published]').set_checked(checked)
    panel.get_by_role('button', name='ذخیره دسته‌بندی').click()
    panel.wait_for_load_state('networkidle')


class QuietHandler(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *args):
        pass


php('backend/tests/site-fixture.php', 'prepare')
try:
    password = secrets.token_hex(20)
    php('backend/scripts/create-admin.php', 'sitebrowser', '--password-stdin', value=password + '\n')
    with sync_playwright() as playwright:
        executable = os.environ.get('DESIGNESTOON_CHROMIUM') or ('/usr/bin/chromium' if Path('/usr/bin/chromium').exists() else None)
        browser = playwright.chromium.launch(executable_path=executable, headless=True,
                                             args=['--no-sandbox'] if sys.platform.startswith('linux') else [])
        context = browser.new_context(viewport={'width': 1280, 'height': 900})
        site = context.new_page()
        errors = []
        site.on('pageerror', lambda error: errors.append(str(error)))
        pending = []
        site.route('**/api/projects.php', lambda route: pending.append(route))
        site.goto(BASE, wait_until='domcontentloaded')
        assert site.locator('#portfolio-grid').get_attribute('aria-busy') == 'true'
        assert site.locator('[data-filter=all]').is_disabled()
        assert 'در حال دریافت' in site.locator('#portfolio-grid').inner_text()
        site.wait_for_function('document.readyState === "complete"')
        assert pending
        pending.pop().continue_()
        site.unroute('**/api/projects.php')
        ready(site)
        assert site.evaluate('window.DesignestoonConfig.mode') == 'api'
        all_cards(site, 22)
        for category in FIXTURE['categories']:
            site.locator('[data-filter="' + category['slug'] + '"]').click()
            expected = sum(project['category'] == category['slug'] for project in FIXTURE['projects'])
            assert site.locator('.portfolio-card').count() == expected
        for rental in site.locator('[data-rental-filter]').all():
            slug = rental.get_attribute('data-rental-filter')
            rental.click()
            assert site.locator('.filter-btn.active').get_attribute('data-filter') == slug
        all_cards(site, 22)
        site.locator('.portfolio-card-image').first.click()
        site.wait_for_function('document.querySelector("#lightbox-image").naturalWidth > 0')
        assert '/assets/images/' in site.locator('#lightbox-image').get_attribute('src')
        first_image = site.locator('#lightbox-image').get_attribute('src')
        site.keyboard.press('ArrowLeft')
        assert site.locator('#lightbox-image').get_attribute('src') != first_image
        site.keyboard.press('ArrowRight')
        assert site.locator('#lightbox-image').get_attribute('src') == first_image
        site.keyboard.press('Escape')
        assert site.locator('#portfolio-lightbox').is_hidden()

        panel = context.new_page()
        panel.goto(BASE + 'admin/login.php')
        panel.locator('#username').fill('sitebrowser')
        panel.locator('#password').fill(password)
        panel.get_by_role('button', name='ورود به پنل').click()
        panel.wait_for_url('**/admin/')
        panel.goto(BASE + 'admin/category-form.php')
        panel.locator('#title').fill('دسته آزمایش سایت')
        panel.locator('#slug').fill('site-test')
        panel.locator('#order').fill('0')
        panel.get_by_role('button', name='ذخیره دسته‌بندی').click()
        panel.wait_for_url('**/admin/categories.php')
        category_row = panel.get_by_role('row').filter(has_text='site-test')
        category_id = category_row.get_by_role('link', name='ویرایش').get_attribute('href').split('id=')[1]
        panel.goto(BASE + 'admin/project-form.php')
        panel.locator('#title').fill('عکس جدید پنل برای سایت')
        panel.locator('#category').select_option(category_id)
        panel.locator('#order').fill('0')
        panel.locator('input[name=is_published]').check()
        panel.locator('#image').set_input_files(str(ROOT / 'assets/images/project-01.webp'))
        panel.wait_for_function('document.querySelector("#image-preview").naturalWidth > 0')
        panel.get_by_role('button', name='ذخیره نمونه‌کار').click()
        panel.wait_for_url('**/admin/project-form.php?id=*')
        project_id = panel.url.split('id=')[1]
        site.reload()
        ready(site)
        assert site.locator('.filter-btn').nth(1).get_attribute('data-filter') == 'site-test'
        all_cards(site, 23)
        assert site.locator('.portfolio-card h3').first.inner_text() == 'عکس جدید پنل برای سایت'
        new_image = site.locator('.portfolio-card-image img').first.get_attribute('src')
        assert '/media.php?file=' in new_image and '/storage/' not in new_image
        site.locator('.portfolio-card-image').first.click()
        site.wait_for_function('document.querySelector("#lightbox-image").naturalWidth > 0')
        assert site.locator('#lightbox-image').get_attribute('src') == new_image
        site.keyboard.press('Escape')
        site.locator('[data-filter=site-test]').click()
        assert site.locator('.portfolio-card').count() == 1
        assert site.locator('.rental-product-contact').get_attribute('href') == 'tel:+989117901519'
        panel.goto(BASE + 'admin/contact.php')
        panel.locator('#phone1').fill('09123456789')
        panel.get_by_role('button', name='ذخیره اطلاعات تماس').click()
        panel.wait_for_load_state('networkidle')
        site.reload()
        ready(site)
        all_cards(site, 23)
        assert all(link.get_attribute('href') == 'tel:+989123456789' for link in site.locator('.rental-product-contact').all())
        assert site.locator('.footer-contact-link').first.get_attribute('href') == 'tel:+989123456789'
        published(panel, project_id, False)
        site.reload(); ready(site); all_cards(site, 22)
        assert context.request.get(new_image).status == 404
        published(panel, project_id, True)
        category_active(panel, category_id, False)
        site.reload(); ready(site); all_cards(site, 22)
        assert site.locator('[data-filter=site-test]').count() == 0
        category_active(panel, category_id, True)
        panel.goto(BASE + 'admin/projects.php')
        row = panel.get_by_role('row').filter(has_text='عکس جدید پنل برای سایت')
        row.locator('[name=confirm]').check()
        row.get_by_role('button', name='بایگانی', exact=True).click()
        panel.wait_for_load_state('networkidle')
        site.reload(); ready(site); all_cards(site, 22)
        row = panel.get_by_role('row').filter(has_text='عکس جدید پنل برای سایت')
        row.locator('[name=confirm]').check()
        row.get_by_role('button', name='بازیابی به‌صورت منتشرنشده').click()
        panel.wait_for_load_state('networkidle')
        site.reload(); ready(site); all_cards(site, 22)
        published(panel, project_id, True)
        for width in [1280, 390]:
            site.set_viewport_size({'width': width, 'height': 844})
            site.reload(); ready(site); all_cards(site, 23)
            assert site.evaluate('document.documentElement.scrollWidth <= innerWidth')
            if width == 390:
                site.locator('#menu-toggle').click()
                assert site.locator('#menu-toggle').get_attribute('aria-expanded') == 'true'
                site.locator('#primary-navigation a').first.click()
                assert site.locator('#menu-toggle').get_attribute('aria-expanded') == 'false'

        # پاسخ خالی موفق و شکست هر API هرگز داده ثابت را نمایش نمی‌دهند.
        site.route('**/api/projects.php', lambda route: route.fulfill(status=200, content_type='application/json', body='[]'))
        site.reload(); ready(site); all_cards(site, 0)
        site.unroute('**/api/projects.php')
        for resource in ['projects', 'categories', 'contact']:
            pattern = '**/api/' + resource + '.php'
            site.route(pattern, lambda route: route.abort())
            site.reload()
            site.get_by_role('button', name='تلاش دوباره').wait_for()
            assert site.locator('.portfolio-card').count() == 0
            assert site.locator('[data-filter=all]').is_disabled()
            site.unroute(pattern)
            site.get_by_role('button', name='تلاش دوباره').click()
            ready(site)
        site.route('**/api/projects.php', lambda route: route.fulfill(status=503, content_type='application/json', body='{"error":"service_unavailable"}'))
        site.reload(); site.get_by_role('button', name='تلاش دوباره').wait_for()
        assert site.locator('.portfolio-card').count() == 0
        site.unroute('**/api/projects.php')
        bad_contact = {'contact_phone_primary': 'javascript:alert(1)', 'contact_phone_secondary': '', 'contact_instagram': ''}
        site.route('**/api/contact.php', lambda route: route.fulfill(status=200, content_type='application/json', body=json.dumps(bad_contact)))
        site.reload(); site.get_by_role('button', name='تلاش دوباره').wait_for()
        assert site.locator('.rental-product-contact').count() == 0
        assert not site.locator('.footer-contact-link').first.get_attribute('href')
        site.unroute('**/api/contact.php')
        malicious = dict(FIXTURE['projects'][0], title='<img src=x onerror=alert(1)>')
        site.route('**/api/projects.php', lambda route: route.fulfill(status=200, content_type='application/json', body=json.dumps([malicious])))
        site.reload(); ready(site); all_cards(site, 1)
        assert site.locator('.portfolio-card h3').inner_text() == malicious['title']
        assert site.locator('.portfolio-card h3 img').count() == 0
        site.unroute('**/api/projects.php')
        for private in ['/.env', '/app/bootstrap.php', '/storage/uploads/test.webp', '/site-assets.php?path=backend/.env']:
            assert context.request.get(BASE.rstrip('/') + private).status == 404
        assert not errors, errors

        # شبیه‌سازی artifact استاتیک در زیرمسیر مشابه GitHub Pages، بدون Backend.
        with tempfile.TemporaryDirectory() as directory:
            target = Path(directory) / 'desinestoon'
            target.mkdir()
            shutil.copy(ROOT / 'index.html', target)
            for name in ['css', 'js', 'assets']:
                shutil.copytree(ROOT / name, target / name)
            server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), functools.partial(QuietHandler, directory=directory))
            threading.Thread(target=server.serve_forever, daemon=True).start()
            try:
                static = context.new_page()
                calls = []
                static.on('request', lambda request: calls.append(request.url))
                static.goto(f'http://127.0.0.1:{server.server_port}/desinestoon/')
                ready(static); all_cards(static, 22)
                assert static.evaluate('window.DesignestoonConfig.mode') == 'static'
                assert not any('/api/' in call for call in calls)
                assert '/desinestoon/assets/images/' in static.locator('.portfolio-card-image img').first.get_attribute('src')
                static.locator('.portfolio-card-image').first.click()
                static.wait_for_function('document.querySelector("#lightbox-image").naturalWidth > 0')
                static.keyboard.press('Escape')
                static.close()
            finally:
                server.shutdown(); server.server_close()
        browser.close()
    print('تست مرورگر سایت: ۱۵ سناریوی فاز چهارم، پنل/دیتابیس، استاتیک، خطا، پاسخ خالی و XSS موفق بود.')
finally:
    php('backend/tests/site-fixture.php', 'cleanup')
