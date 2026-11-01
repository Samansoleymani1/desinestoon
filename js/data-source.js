/* منبع داده مشترک؛ خطای API هرگز داده استاتیک را فعال نمی‌کند. */
(function () {
  'use strict';
  function phone(value) {
    if (typeof value !== 'string') throw new Error('شماره تماس معتبر نیست.');
    const normalized = value.replace(/[۰-۹٠-٩]/g, digit =>
      String('۰۱۲۳۴۵۶۷۸۹'.includes(digit) ? '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit) : '٠١٢٣٤٥٦٧٨٩'.indexOf(digit)))
      .replace(/[ ()-]/g, '').replace(/^\+98/, '0');
    if (!/^0[1-9][0-9]{9}$/.test(normalized)) throw new Error('شماره تماس معتبر نیست.');
    return normalized;
  }
  function configuration() {
    const config = window.DesignestoonConfig;
    if (!config || !['static', 'api'].includes(config.mode)) throw new Error('حالت اجرای سایت معتبر نیست.');
    const siteBase = new URL(config.siteBase || './', document.baseURI);
    const apiBase = new URL(config.apiBase || './', document.baseURI);
    for (const base of [siteBase, apiBase]) {
      if (!['http:', 'https:', 'file:'].includes(base.protocol) || base.username || base.password ||
          base.search || base.hash || !base.pathname.endsWith('/')) throw new Error('مبنای آدرس معتبر نیست.');
    }
    if (config.mode === 'api' && (apiBase.origin !== location.origin || !['http:', 'https:'].includes(apiBase.protocol))) {
      throw new Error('سایت و Backend باید روی یک مبدأ اجرا شوند.');
    }
    return {...config, siteBase, apiBase};
  }
  function imageUrl(path, config) {
    if (typeof path !== 'string') throw new Error('مسیر تصویر معتبر نیست.');
    if (/^\.\/assets\/images\/[A-Za-z0-9_-]+\.(webp|png|jpe?g)$/.test(path)) {
      return new URL(path.slice(2), config.siteBase).href;
    }
    if (/^\/media\.php\?file=[a-f0-9]{32}\.(webp|jpg|png)$/.test(path)) {
      return new URL(path.slice(1), config.apiBase).href;
    }
    throw new Error('مسیر تصویر مجاز نیست.');
  }
  function categories(rows) {
    if (!Array.isArray(rows)) throw new Error('اطلاعات دسته‌ها معتبر نیست.');
    const slugs = new Set();
    return rows.map(row => {
      if (!row || typeof row.slug !== 'string' || row.slug === 'all' ||
          !/^[a-z][a-z0-9-]{0,63}$/.test(row.slug) || slugs.has(row.slug) ||
          typeof row.title !== 'string' || !row.title.trim() || row.title.length > 510) {
        throw new Error('اطلاعات دسته‌ها معتبر نیست.');
      }
      slugs.add(row.slug);
      return {slug: row.slug, title: row.title};
    });
  }
  function catalog(rows, groups, config) {
    if (!Array.isArray(rows)) throw new Error('اطلاعات گالری معتبر نیست.');
    const ids = new Set();
    const slugs = new Set(groups.map(group => group.slug));
    return rows.map(row => {
      if (!row || !Number.isSafeInteger(row.id) || row.id < 1 || ids.has(row.id) ||
          typeof row.title !== 'string' || !row.title.trim() || row.title.length > 510 ||
          typeof row.category !== 'string' ||
          !Number.isSafeInteger(row.width) || !Number.isSafeInteger(row.height) || row.width < 1 || row.height < 1 || row.width > 8192 || row.height > 8192) {
        throw new Error('اطلاعات گالری معتبر نیست.');
      }
      ids.add(row.id);
      return {...row, image: imageUrl(row.image, config)};
    }).filter(row => slugs.has(row.category));
  }
  function contact(row) {
    if (!row || typeof row !== 'object' || Array.isArray(row)) throw new Error('اطلاعات تماس معتبر نیست.');
    const primary = phone(row.contact_phone_primary);
    const secondary = row.contact_phone_secondary === '' ? '' : phone(row.contact_phone_secondary);
    const instagram = row.contact_instagram;
    if (typeof instagram !== 'string' || (instagram !== '' &&
        !/^https:\/\/(www\.)?instagram\.com\/[A-Za-z0-9_.]+\/?$/.test(instagram))) {
      throw new Error('اطلاعات تماس معتبر نیست.');
    }
    return {primary, secondary, instagram};
  }
  async function load(staticProjects, staticCategories) {
    const config = configuration();
    if (config.mode === 'static') {
      const groups = categories(staticCategories);
      return {mode: 'static', projects: catalog(staticProjects, groups, config), categories: groups,
        contact: contact({contact_phone_primary: '09117901519', contact_phone_secondary: '09366208359',
          contact_instagram: 'https://www.instagram.com/designestoon_amol/'})};
    }
    const controller = new AbortController();
    const timeout = Number.isFinite(config.timeoutMs) ? Math.min(30000, Math.max(500, config.timeoutMs)) : 8000;
    const timer = setTimeout(() => controller.abort(), timeout);
    try {
      const get = async resource => {
        const response = await fetch(new URL('api/' + resource + '.php', config.apiBase), {
          signal: controller.signal, cache: 'no-store', credentials: 'omit', redirect: 'error',
          headers: {Accept: 'application/json'}
        });
        if (!response.ok) throw new Error('دریافت اطلاعات انجام نشد.');
        return response.json();
      };
      const [items, groups, settings] = await Promise.all([get('projects'), get('categories'), get('contact')]);
      const normalizedCategories = categories(groups);
      return {mode: 'api', projects: catalog(items, normalizedCategories, config),
        categories: normalizedCategories, contact: contact(settings)};
    } finally {
      clearTimeout(timer);
      controller.abort();
    }
  }
  window.DesignestoonData = Object.freeze({load, phone, configuration, imageUrl});
})();
