import {mkdtempSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join, resolve} from 'node:path';
import {spawn, execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import net from 'node:net';

const root = resolve(import.meta.dirname, '..');
const dir = mkdtempSync(join(tmpdir(), 'clickshot-new-feat-'));
const env = {
  ...process.env,
  METRIKA_DB_PATH: join(dir, 'test.sqlite'),
  METRIKA_DASHBOARD_PASSWORD: 'test-features-pw',
  METRIKA_HITS_PER_MINUTE: '1000'
};

execFileSync('php', [join(root, 'bin/database.php'), 'migrate'], {env});

const reservation = net.createServer();
await new Promise(r => reservation.listen(0, '127.0.0.1', r));
const port = reservation.address().port;
await new Promise(r => reservation.close(r));
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', join(root, 'public')], {env, stdio: 'ignore'});
const base = `http://127.0.0.1:${port}`;
const auth = 'Basic ' + Buffer.from('admin:test-features-pw').toString('base64');

async function request(path, method = 'GET', body, headers = {}) {
  const r = await fetch(base + path, {
    method,
    headers: {'Content-Type': 'application/json', ...headers},
    body: body === undefined ? undefined : JSON.stringify(body)
  });
  const text = await r.text();
  return {status: r.status, data: text ? JSON.parse(text) : null};
}

try {
  // Wait for server to become responsive
  for (let i = 0; i < 100; i++) {
    try {
      await fetch(base + '/api/sites.php');
      break;
    } catch {
      await new Promise(r => setTimeout(r, 30));
    }
  }

  // 1. Create site
  const siteRes = await request('/api/sites.php', 'POST', {
    id: 'feat_site',
    name: 'Feature Site',
    domains: ['feat.example']
  }, {Authorization: auth});
  assert.equal(siteRes.status, 201, 'Site creation failed');

  // 2. Hit with UTM parameters and newVisit
  const hit1 = await request('/api/hit.php', 'POST', {
    site: 'feat_site',
    path: '/landing',
    newVisit: true,
    utm_source: 'yandex_direct',
    utm_medium: 'cpc',
    utm_campaign: 'autumn_sale'
  }, {Origin: 'https://feat.example'});
  assert.equal(hit1.status, 204, 'Hit with UTM failed');

  // Verify campaign_daily_stats and bounce in stats
  let stats = await request('/api/stats.php?site=feat_site', 'GET', undefined, {Authorization: auth});
  assert.equal(stats.status, 200);
  assert.equal(stats.data.totals.pageviews, 1);
  assert.equal(stats.data.totals.visits, 1);
  assert.equal(stats.data.totals.bounces, 1);
  assert.equal(stats.data.totals.bounce_rate, 100);
  assert.equal(stats.data.campaigns.length, 1);
  assert.equal(stats.data.campaigns[0].source, 'yandex_direct');
  assert.equal(stats.data.campaigns[0].medium, 'cpc');
  assert.equal(stats.data.campaigns[0].campaign, 'autumn_sale');
  assert.equal(stats.data.campaigns[0].visits, 1);
  assert.equal(stats.data.campaigns[0].pageviews, 1);

  // 3. Standalone engaged ping (user spent > 15s)
  const engagedPing = await request('/api/hit.php', 'POST', {
    site: 'feat_site',
    engaged: true
  }, {Origin: 'https://feat.example'});
  assert.equal(engagedPing.status, 204, 'Engaged ping failed');

  stats = await request('/api/stats.php?site=feat_site', 'GET', undefined, {Authorization: auth});
  // Pageviews and visits must NOT change, but bounces must decrement to 0!
  assert.equal(stats.data.totals.pageviews, 1);
  assert.equal(stats.data.totals.visits, 1);
  assert.equal(stats.data.totals.bounces, 0);
  assert.equal(stats.data.totals.bounce_rate, 0);

  // 4. Test goal conversion rate (CR)
  const goalHit = await request('/api/hit.php', 'POST', {
    site: 'feat_site',
    event: 'order_completed',
    newVisit: true
  }, {Origin: 'https://feat.example'});
  assert.equal(goalHit.status, 204, 'Goal hit failed');

  stats = await request('/api/stats.php?site=feat_site', 'GET', undefined, {Authorization: auth});
  assert.equal(stats.data.events.length, 1);
  assert.equal(stats.data.events[0].name, 'order_completed');
  assert.equal(stats.data.events[0].events, 1);
  assert.equal(stats.data.events[0].visits, 1);
  assert.equal(stats.data.events[0].cr, 100); // 1 visit with goal / 1 total visit = 100%

  // 5. Test counter.js logic in VM: SPA route change & 30m timeout
  const beacons = [];
  const storage = new Map();
  const historyListeners = [];
  const fakeHistory = {
    pushState: (state, title, url) => {}
  };
  const context = {
    URL, Blob,
    location: {href: 'https://feat.example/catalog?utm_source=tg&utm_campaign=promo', pathname: '/catalog'},
    document: {
      currentScript: {src: base + '/counter.js?id=feat_site', dataset: {}},
      readyState: 'complete',
      referrer: 'https://telegram.org',
      addEventListener: () => {}
    },
    history: fakeHistory,
    window: {
      __clickShotBootstrap: {site: 'feat_site', collection: {visits: true, pages: true, referrers: true}},
      addEventListener: (evt, fn) => { if (evt === 'popstate') historyListeners.push(fn); }
    },
    navigator: {sendBeacon: (url, blob) => { beacons.push(blob); return true; }},
    sessionStorage: {
      getItem: k => storage.get(k),
      setItem: (k, v) => storage.set(k, v),
      removeItem: k => storage.delete(k),
      get length() { return storage.size; },
      key: i => [...storage.keys()][i] ?? null
    },
    fetch: () => Promise.resolve({ok: true}),
    setTimeout: (fn, ms) => {},
    clearTimeout: () => {},
    Date
  };

  const counterCode = execFileSync('cat', [join(root, 'public/counter.js')], {encoding: 'utf8'});
  vm.runInNewContext(counterCode, context);

  // Wait for promise resolution
  await new Promise(r => setTimeout(r, 50));
  assert.ok(beacons.length >= 1, 'Initial pageview beacon not queued');
  const beaconPayload = JSON.parse(await beacons[0].text());
  assert.equal(beaconPayload.site, 'feat_site');
  assert.equal(beaconPayload.path, '/catalog');
  assert.equal(beaconPayload.newVisit, true);
  assert.equal(beaconPayload.utm_source, 'tg');
  assert.equal(beaconPayload.utm_campaign, 'promo');

  // Test SPA navigation via pushState
  context.location.pathname = '/catalog/shoes';
  context.history.pushState({}, '', '/catalog/shoes');
  await new Promise(r => setTimeout(r, 50));

  assert.ok(beacons.length >= 2, 'SPA navigation did not trigger second pageview');
  const spaPayload = JSON.parse(await beacons[1].text());
  assert.equal(spaPayload.path, '/catalog/shoes');
  assert.equal(spaPayload.newVisit, false, 'SPA subsequent page must not be new visit');
  assert.equal(spaPayload.engaged, true, 'Subsequent pageview must be marked engaged');

  // 6. Engagement is claimed once per visit: a goal after the engaged
  // pageview must not decrement bounces of other visits again.
  const goalBeaconsBefore = beacons.length;
  await context.window.ClickShotMetrika.goal('lead_sent');
  const goalPayload = JSON.parse(await beacons[goalBeaconsBefore].text());
  assert.equal(goalPayload.event, 'lead_sent');
  assert.equal(goalPayload.newVisit, true, 'First goal in visit counts a goal visit');
  assert.equal(goalPayload.engaged, undefined, 'Visit was already engaged by the SPA pageview');

  // 7. A new visit resets goal markers, so goal CR stays per visit.
  for (const key of storage.keys()) if (key.endsWith(':started')) storage.set(key, String(Date.now() - 31 * 60 * 1000));
  context.location.pathname = '/after-break';
  context.history.pushState({}, '', '/after-break');
  await new Promise(r => setTimeout(r, 50));
  const afterBreak = JSON.parse(await beacons.at(-1).text());
  assert.equal(afterBreak.newVisit, true, 'Visit expires after 30 minutes of inactivity');
  const goalAgain = beacons.length;
  await context.window.ClickShotMetrika.goal('lead_sent');
  const goalAgainPayload = JSON.parse(await beacons[goalAgain].text());
  assert.equal(goalAgainPayload.newVisit, true, 'Goal must count once in the new visit');

  // 8. With page paths disabled, a non-first pageview is still a pageview,
  // not an engagement ping.
  const settingsRes = await request('/api/sites.php', 'PUT', {
    id: 'feat_site', collection: {pageviews: true, pages: false, referrers: false, visits: true, tech: false}
  }, {Authorization: auth, Origin: base});
  assert.equal(settingsRes.status, 200, 'Settings update failed: ' + JSON.stringify(settingsRes.data));
  stats = await request('/api/stats.php?site=feat_site', 'GET', undefined, {Authorization: auth});
  const pvBefore = stats.data.totals.pageviews;
  const secondPage = await request('/api/hit.php', 'POST', {
    site: 'feat_site', newVisit: false, engaged: true
  }, {Origin: 'https://feat.example'});
  assert.equal(secondPage.status, 204);
  stats = await request('/api/stats.php?site=feat_site', 'GET', undefined, {Authorization: auth});
  assert.equal(stats.data.totals.pageviews, pvBefore + 1, 'Pageview without path must be counted');

  // 9. Bounces are not stored per source, so a filtered report must not
  // claim a 0% bounce rate.
  await request('/api/sites.php', 'PUT', {
    id: 'feat_site', collection: {pageviews: true, pages: true, referrers: true, visits: true, tech: false}
  }, {Authorization: auth, Origin: base});
  stats = await request('/api/stats.php?site=feat_site&sources=' + encodeURIComponent('(прямой заход)'), 'GET', undefined, {Authorization: auth});
  assert.equal(stats.status, 200);
  assert.equal(stats.data.totals.bounce_rate, null, 'Bounce rate is unknown for a source filter');

  // 10. Engagement for a visit that started on another day must not push the
  // day's bounces below zero (PDO binds TEXT; '-1' > 0 is true in SQLite).
  await request('/api/sites.php', 'POST', {id: 'fresh_site', name: 'Fresh', domains: ['fresh.example']}, {Authorization: auth});
  await request('/api/hit.php', 'POST', {site: 'fresh_site', path: '/', newVisit: false, engaged: true}, {Origin: 'https://fresh.example'});
  await request('/api/hit.php', 'POST', {site: 'fresh_site', path: '/', newVisit: false, engaged: true}, {Origin: 'https://fresh.example'});
  stats = await request('/api/stats.php?site=fresh_site', 'GET', undefined, {Authorization: auth});
  assert.equal(stats.data.totals.pageviews, 2);
  assert.equal(stats.data.totals.bounces, 0, 'Bounces must never be negative');

  console.log('PASS All new analytics features verified successfully!');
} finally {
  server.kill('SIGTERM');
}
