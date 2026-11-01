'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'js/data-source.js'), 'utf8');
const fixture = JSON.parse(fs.readFileSync(path.join(root, 'backend/database/initial-data.json')));
const settings = {contact_phone_primary: '09117901519', contact_phone_secondary: '09366208359', contact_instagram: 'https://www.instagram.com/designestoon_amol/'};
function provider(config, fetch) {
  const context = vm.createContext({window: {DesignestoonConfig: config}, document: {baseURI: 'https://example.test/site/'},
    location: new URL('https://example.test/site/'), URL, AbortController, setTimeout, clearTimeout, fetch});
  vm.runInContext(source, context);
  return context.window.DesignestoonData;
}
function api(overrides = {}) {
  return async url => {
    const key = url.pathname.split('/').pop().replace('.php', '');
    const values = {projects: fixture.projects, categories: fixture.categories, contact: settings, ...overrides};
    return {ok: true, json: async () => values[key]};
  };
}
(async () => {
  const config = {mode: 'api', apiBase: '/service/', siteBase: '/site/'};
  const data = await provider(config, api()).load(fixture.projects, fixture.categories);
  assert.equal(data.projects.length, 22);
  assert.equal(data.projects[0].image, 'https://example.test/site/assets/images/project-01.webp');
  assert.equal(data.contact.primary, '09117901519');
  const media = {...fixture.projects[0], image: '/media.php?file=' + 'a'.repeat(32) + '.webp'};
  const uploaded = await provider(config, api({projects: [media]})).load([], []);
  assert.equal(uploaded.projects[0].image, 'https://example.test/service/media.php?file=' + 'a'.repeat(32) + '.webp');
  const empty = await provider(config, api({projects: [], categories: []})).load(fixture.projects, fixture.categories);
  assert.equal(empty.projects.length, 0, 'پاسخ خالی نباید داده ثابت را برگرداند');
  const hidden = await provider(config, api({categories: fixture.categories.filter(c => c.slug !== 'birthday')})).load([], []);
  assert(!hidden.projects.some(p => p.category === 'birthday'));
  for (const image of ['javascript:alert(1)', '../storage/a.webp', '/storage/a.webp', 'https://evil.test/a.jpg']) {
    await assert.rejects(() => provider(config, api({projects: [{...media, image}]})).load([], []));
  }
  await assert.rejects(() => provider(config, api({contact: {...settings, contact_phone_primary: 'javascript:bad'}})).load([], []));
  await assert.rejects(() => provider(config, async () => ({ok: false})).load(fixture.projects, fixture.categories));
  await assert.rejects(() => provider(config, async () => { throw new Error('offline'); }).load(fixture.projects, fixture.categories));
  await assert.rejects(() => provider({...config, apiBase: 'https://other.test/'}, api()).load([], []));
  await assert.rejects(() => provider({...config, mode: 'unknown'}, api()).load([], []));
  await assert.rejects(() => provider({...config, timeoutMs: 500}, (url, options) => new Promise((resolve, reject) => {
    options.signal.addEventListener('abort', () => reject(new Error('timeout')));
  })).load([], []));
  let calls = 0;
  const staticData = await provider({mode: 'static'}, async () => { calls++; throw new Error('API نباید فراخوانی شود'); }).load(fixture.projects, fixture.categories);
  assert.equal(calls, 0);
  assert.equal(staticData.projects.length, 22);
  assert.equal(staticData.categories.length, 7);
  console.log('تست منبع داده: حالت استاتیک، API، مسیر تصاویر، تماس، خطا، timeout و پاسخ خالی موفق بود.');
})().catch(error => { console.error(error); process.exitCode = 1; });
