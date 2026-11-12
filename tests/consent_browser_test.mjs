// Real browser, real consent API, temporary database. All Yandex traffic is mocked.
import {mkdtempSync, mkdirSync, writeFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join, resolve} from 'node:path';
import {spawn, execFileSync} from 'node:child_process';
import {createRequire} from 'node:module';
import net from 'node:net';
import assert from 'node:assert/strict';

const require = createRequire(import.meta.url);
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = resolve(import.meta.dirname, '..');
const dir = mkdtempSync(join(tmpdir(),'clickshot-consent-browser-'));
const output = join(root,'output/playwright/consent');
mkdirSync(output,{recursive:true});
const env = {...process.env, METRIKA_DB_PATH:join(dir,'browser.sqlite'),METRIKA_DASHBOARD_PASSWORD:'browser-test-only'};
const php = code => execFileSync('php',['-r',`require ${JSON.stringify(join(root,'lib.php'))};${code}`],{env,encoding:'utf8'}).trim();
execFileSync('php',[join(root,'bin/database.php'),'migrate'],{env});
php(`foreach (['browser_site','browser_conditional','browser_other'] as $id) {
  db()->prepare('INSERT INTO sites VALUES(?,?,?, ?,1)')->execute([$id,$id,'["127.0.0.1"]',date(DATE_ATOM)]);
  db()->prepare("INSERT INTO site_collection_settings(site_id,slide_cookie_enabled,slide_cookie_policy_url,slide_cookie_param,slide_cookie_key,slide_cookie_ym_counter,updated_at) VALUES(?,1,'/privacy',?,?,'12345',?)")
    ->execute([$id,$id==='browser_conditional'?'utm_source':'always',$id==='browser_conditional'?'rsya':'',date(DATE_ATOM)]);
  save_consent_details($id,array_replace(consent_defaults(),['operator_name'=>$id==='browser_other'?'Другой владелец сайта':'Тестовый оператор','operator_address'=>'Тестовый адрес','operator_email'=>'privacy@example.test']));
}`);
const reservation = net.createServer();
await new Promise(r=>reservation.listen(0,'127.0.0.1',r));
const port = reservation.address().port;
await new Promise(r=>reservation.close(r));
const server = spawn('php',['-S',`127.0.0.1:${port}`,'-t',join(root,'public'),join(root,'tests/browser-router.php')],{env,stdio:'ignore'});
const origin = `http://127.0.0.1:${port}`;
const results = [];
let browser;
const mockTag = `window.fixtureYandexCalls=(window.ym.a||[]).map(args=>Array.from(args));
window.ym=function(...args){window.fixtureYandexCalls.push(args)};
document.cookie='_ym_uid=fixture-only; Path=/';try{localStorage.setItem('_ym_uid','fixture-only')}catch(_){}`;
async function setup(options = {}) {
  const context = await browser.newContext({...(options.storageState ? {storageState:options.storageState} : {}),...(options.admin ? {httpCredentials:{username:'admin',password:'browser-test-only'}} : {}),viewport:options.mobile ? {width:375,height:740} : {width:1280,height:900}});
  const external = [], hits = [], errors = [];
  await context.route('**/*',async route=>{
    const url = new URL(route.request().url());
    if (url.origin !== origin) {
      external.push({host:url.hostname,path:url.pathname});
      if (url.hostname === 'mc.yandex.ru' && url.pathname === '/metrika/tag.js') return route.fulfill({contentType:'application/javascript',body:mockTag});
      return route.abort();
    }
    if (url.pathname === '/api/hit.php') hits.push(JSON.parse(route.request().postData() || '{}'));
    if (options.delayGrant && url.pathname === '/api/consent.php' && route.request().postDataJSON()?.action === 'granted') {
      const response = await route.fetch();
      await new Promise(resolve=>setTimeout(resolve,250));
      return route.fulfill({response});
    }
    if (options.loaderFailure && url.pathname === '/api/loader.php') return route.fulfill({status:503,body:''});
    if (options.offlineConsent && url.pathname === '/api/consent.php') return route.fulfill({status:503,contentType:'application/json',body:'{"ok":false}'});
    return route.continue();
  });
  if (options.noStorage) await context.addInitScript(()=>{
    for (const name of ['localStorage','sessionStorage']) Object.defineProperty(window,name,{get(){throw new Error('storage unavailable')}});
  });
  if (options.timeOffset) await context.addInitScript(offset=>{
    const realNow = Date.now.bind(Date);
    Date.now = () => realNow() + offset;
  },options.timeOffset);
  const page = await context.newPage();
  page.on('pageerror',error=>errors.push(error.message));
  return {context,page,external,hits,errors,options};
}
async function ready(page, query = '') {
  await page.goto(origin+'/fixture'+query);
  await page.waitForFunction(()=>!!window.ClickShotConsent);
}
async function grant(page) {
  await page.getByRole('slider').press('Enter');
  await page.waitForFunction(()=>window.ClickShotConsent.getState().analytics);
  await page.getByRole('dialog').waitFor({state:'detached'});
}
async function open(page) {
  await page.locator('[data-clickshot-settings]').click();
  await page.getByRole('dialog').waitFor();
}
const record = (name, data = {}) => { results.push({name,ok:true,...data});console.log('PASS '+name); };
try {
  for (let i=0;i<100;i++) {try {await fetch(origin+'/health.php');break;}catch {await new Promise(r=>setTimeout(r,25));}}
  browser = await chromium.launch({headless:true,...(process.env.CHROME_PATH ? {executablePath:process.env.CHROME_PATH} : {})});

  // Initial state, refusal links, policy access and persistence.
  let test = await setup();
  await ready(test.page);
  await test.page.getByRole('dialog').waitFor();
  assert.equal(test.external.length,0);
  assert.equal(await test.page.evaluate(()=>sessionStorage.length),0,'No analytics storage before consent');
  assert.ok(test.hits.some(h=>h.event==='cookie_consent_shown'));
  assert.equal(await test.page.getByRole('link',{name:'Продолжить без аналитики'}).evaluate(el=>el.tagName),'A');
  await test.page.getByRole('link',{name:'Согласие на аналитику'}).click();
  await test.page.locator('[id$="document"]').waitFor({state:'visible'});
  assert.ok(await test.page.locator('[id$="document"]').innerText().then(t=>t.includes('Тестовый оператор')));
  await test.page.getByRole('link',{name:'Согласие на аналитику'}).click();
  const policyUrl = await test.page.getByRole('link',{name:'Политика обработки данных'}).getAttribute('href');
  assert.equal((await test.page.request.get(origin+policyUrl)).status(),200);
  await test.page.screenshot({path:join(output,'desktop.png')});
  await test.page.getByRole('link',{name:'Продолжить без аналитики'}).click();
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  await test.page.waitForFunction(()=>JSON.parse(localStorage.getItem('clickshot:cookie-choice:browser_site'))?.receipt);
  await test.page.locator('#client-action').click();
  await test.page.reload({waitUntil:'networkidle'});
  assert.equal(await test.page.getByRole('dialog').count(),0);
  assert.equal(test.external.length,0);
  record('Initial state, text links, separate consent, accessible policy, refusal and reload');

  // Explicit acceptance, one initialization, no retrospective events.
  await open(test.page);
  await grant(test.page);
  await test.page.waitForFunction(()=>!!window.fixtureYandexCalls);
  assert.equal(test.external.filter(x=>x.path==='/metrika/tag.js').length,1);
  const yandexCalls = await test.page.evaluate(()=>window.fixtureYandexCalls);
  assert.equal(yandexCalls.filter(x=>x[1]==='init').length,1);
  assert.ok(!yandexCalls.some(x=>x[1]==='reachGoal'),'Pre-consent and banner goals never replay');
  const grantedState = await test.page.evaluate(()=>JSON.parse(localStorage.getItem('clickshot:cookie-choice:browser_site')));
  assert.ok(grantedState.receipt && grantedState.created_at && grantedState.hash);
  await test.page.reload({waitUntil:'networkidle'});
  assert.equal(await test.page.getByRole('dialog').count(),0);
  assert.equal(await test.page.evaluate(()=>window.ClickShotConsent.getState().analytics),true);
  record('Explicit acceptance, server receipt, one SDK initialization, no replay and saved-consent verification');
  const outage = await setup({offlineConsent:true});
  await outage.context.addInitScript(value=>localStorage.setItem('clickshot:cookie-choice:browser_site',JSON.stringify(value)),grantedState);
  await ready(outage.page);
  await outage.page.getByRole('dialog').waitFor();
  assert.equal(outage.external.length,0);
  assert.equal(await outage.page.evaluate(()=>window.ClickShotConsent.getState().analytics),false);
  record('Saved grant never activates SDK when server verification fails');
  await outage.context.close();

  // Other tab must stop after withdrawal; own tab reloads and clears accessible vendor storage.
  const second = await test.context.newPage();
  await ready(second);
  await second.waitForFunction(()=>window.ClickShotConsent.getState().analytics);
  await open(test.page);
  const nav = test.page.waitForEvent('load');
  await test.page.getByRole('link',{name:'Отозвать согласие'}).click();
  await nav;
  await test.page.waitForFunction(()=>window.ClickShotConsent && !window.ClickShotConsent.getState().analytics);
  await second.waitForFunction(()=>window.ClickShotConsent && !window.ClickShotConsent.getState().analytics);
  assert.equal(await test.page.evaluate(()=>document.cookie.includes('_ym')),false);
  assert.equal(await test.page.evaluate(()=>localStorage.getItem('_ym_uid')),null);
  const beforeReload = test.external.length;
  await test.page.reload({waitUntil:'networkidle'});
  assert.equal(test.external.length,beforeReload,'No new vendor request after withdrawal');
  assert.equal(test.errors.length,0,test.errors.join('\n'));
  record('Withdrawal, cookie cleanup, reload and synchronization across tabs');
  await test.context.close();

  // Refusal must remain usable when receipts cannot be written.
  test = await setup({offlineConsent:true});
  await ready(test.page);
  await test.page.getByRole('slider').press('Enter');
  await test.page.getByRole('dialog').locator('[role="alert"]').filter({hasText:'Не удалось сохранить'}).waitFor();
  assert.equal(test.external.length,0);
  await test.page.getByRole('link',{name:'Продолжить без аналитики'}).click();
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  assert.equal(await test.page.evaluate(()=>window.ClickShotConsent.getState().analytics),false);
  assert.equal(await test.page.evaluate(()=>JSON.parse(localStorage.getItem('clickshot:cookie-choice:browser_site')).pending),true);
  await test.page.reload({waitUntil:'networkidle'});
  assert.equal(test.external.length,0);
  record('Receipt-service failure keeps analytics off and refusal available');
  await test.context.close();

  // No persistent storage: reject still works, acceptance requires server evidence, reload asks again.
  test = await setup({noStorage:true});
  await ready(test.page);
  await test.page.getByRole('link',{name:'Продолжить без аналитики'}).click();
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  await open(test.page);
  await grant(test.page);
  assert.ok(test.external.some(x=>x.path==='/metrika/tag.js'));
  const beforeNoStorageReload = test.external.length;
  await test.page.reload();
  await test.page.getByRole('dialog').waitFor();
  assert.equal(test.external.length,beforeNoStorageReload);
  record('Unavailable localStorage/sessionStorage: refusal, explicit acceptance and fresh decision on reload');
  await test.context.close();

  // Never trust old boolean grants or fabricated receipts.
  for (const legacy of [true,false]) {
    test = await setup();
    const document = JSON.parse(php("echo json_encode(consent_document(site_by_id('browser_site')['slide_cookie']));"));
    await test.context.addInitScript(({legacy,document})=>{
      localStorage.setItem('clickshot:cookie-consent:browser_site:v1','true');
      if (!legacy) localStorage.setItem('clickshot:cookie-choice:browser_site',JSON.stringify({format:1,decision:'granted',
        receipt:'f'.repeat(64),version:document.version,hash:document.hash,expires_at:Date.now()+86400000}));
    },{legacy,document});
    await ready(test.page);
    await test.page.getByRole('dialog').waitFor();
    assert.equal(test.external.length,0);
    await test.context.close();
  }
  record('Legacy boolean grants and fabricated receipts do not activate analytics');

  test = await setup({loaderFailure:true});
  await test.page.goto(origin+'/fixture',{waitUntil:'networkidle'});
  assert.equal(test.external.length,0,'Managed installation fails closed even if loader fails');
  record('Managed installation stays off when ClickShot loader fails');
  await test.context.close();

  test = await setup();
  await ready(test.page,'?mode=conditional');
  await test.page.waitForTimeout(100);
  assert.equal(await test.page.getByRole('dialog').count(),0);
  assert.equal(test.external.length,0);
  await open(test.page);
  await test.page.getByRole('dialog').waitFor();
  await ready(test.page,'?mode=conditional&utm_source=rsya');
  await test.page.getByRole('heading',{name:'Сайт использует cookie'}).waitFor();
  assert.equal(await test.page.getByRole('link',{name:'Согласие на аналитику'}).count(),1,'A matching UTM must retain cookie mode in a fresh browser');
  record('UTM mismatch does not authorize analytics; settings link remains accessible');
  await test.context.close();

  test = await setup({delayGrant:true});
  await ready(test.page);
  await test.page.getByRole('slider').press('Enter');
  await test.page.getByRole('link',{name:'Продолжить без аналитики'}).click();
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  await test.page.waitForTimeout(350);
  assert.equal(await test.page.evaluate(()=>window.ClickShotConsent.getState().analytics),false);
  assert.equal(test.external.length,0);
  record('Refusal wins over an in-flight acceptance request');
  await test.context.close();

  // Touch-sized layout and actual pointer drag.
  test = await setup({mobile:true});
  await ready(test.page);
  await test.page.getByRole('dialog').waitFor();
  assert.equal(await test.page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  const linkStyle = await test.page.getByRole('link',{name:'Продолжить без аналитики'}).evaluate(el=>({font:getComputedStyle(el).fontSize,tag:el.tagName}));
  assert.equal(linkStyle.tag,'A');
  await test.page.screenshot({path:join(output,'mobile.png')});
  const slider = await test.page.getByRole('slider').boundingBox();
  await test.page.mouse.move(slider.x+slider.width/2,slider.y+slider.height/2);
  await test.page.mouse.down();
  await test.page.mouse.move(slider.x+slider.width/2+250,slider.y+slider.height/2,{steps:12});
  await test.page.mouse.up();
  await test.page.waitForFunction(()=>window.ClickShotConsent.getState().analytics);
  record('375px viewport, compact readable links and pointer-slider acceptance',linkStyle);
  await test.context.close();

  test = await setup();
  await ready(test.page);
  await grant(test.page);
  const firstSiteRequests = test.external.length;
  await ready(test.page,'?client=other');
  await test.page.getByRole('dialog').waitFor();
  const customerBanner = await test.page.getByRole('dialog').innerText();
  assert.ok(customerBanner.includes('Другой владелец сайта') && !customerBanner.includes('Тестовый оператор'));
  assert.equal(await test.page.evaluate(()=>window.ClickShotConsent.getState().analytics),false);
  assert.equal(test.external.length,firstSiteRequests,'Consent of one customer cannot enable another customer');
  await test.page.getByRole('link',{name:'Продолжить без аналитики'}).click();
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  await ready(test.page);
  await test.page.waitForFunction(()=>window.ClickShotConsent.getState().analytics);
  record('Different customer sites keep their own operator, consent text, storage key and permission');
  await test.context.close();

  test = await setup({admin:true});
  await test.page.goto(origin+'/');
  await test.page.locator('[data-neu-tab="cookie"]').click();
  await test.page.locator('#site').selectOption('browser_site');
  await test.page.waitForFunction(()=>document.querySelector('#slide-operator-name').value === 'Тестовый оператор');
  await test.page.locator('#slide-consent-text').fill('Проверка сохранения условий в панели.');
  await test.page.locator('#slide-reject-action').selectOption('slider');
  await test.page.locator('#save-slide-cookie').click();
  await test.page.locator('#slide-cookie-status').filter({hasText:'Сохранено'}).waitFor();
  await test.page.reload();
  await test.page.locator('[data-neu-tab="cookie"]').click();
  await test.page.locator('#site').selectOption('browser_site');
  await test.page.waitForFunction(()=>document.querySelector('#slide-consent-text').value === 'Проверка сохранения условий в панели.');
  assert.equal(await test.page.locator('#slide-reject-action').inputValue(),'slider');
  await test.page.setViewportSize({width:375,height:812});
  assert.equal(await test.page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Admin settings fit mobile width');
  assert.equal(test.errors.length,0,test.errors.join('\n'));
  record('Admin settings save/reload and mobile layout preserve the existing dashboard');
  await test.context.close();

  // Refusal must not bypass the optional continuation challenge, even after reload.
  test = await setup();
  await ready(test.page);
  await test.page.getByRole('link',{name:'Продолжить без аналитики'}).click();
  await test.page.getByRole('heading',{name:'Вы не робот?'}).waitFor();
  assert.ok((await test.page.getByRole('dialog').innerText()).includes('без сбора cookie'));
  assert.equal(await test.page.locator('#client-action').evaluate(el=>el.closest('[inert]') !== null),true);
  await test.page.reload();
  await test.page.getByRole('heading',{name:'Вы не робот?'}).waitFor();
  assert.equal(await test.page.getByRole('dialog').getByRole('link').count(),0);
  await test.page.getByRole('slider').press('Enter');
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  assert.equal(await test.page.evaluate(()=>window.ClickShotConsent.getState().analytics),false);
  assert.equal(test.external.length,0);
  assert.equal(await test.page.evaluate(()=>JSON.parse(localStorage.getItem('clickshot:cookie-choice:browser_site')).decision),'denied');
  await test.page.reload();
  await test.page.waitForTimeout(400);
  assert.equal(await test.page.getByRole('dialog').count(),0);
  assert.equal(await test.page.locator('#client-action').evaluate(el=>el.closest('[inert]') !== null),false);
  record('Refusal requires a slider; reload cannot bypass it; completion never grants analytics');
  await test.context.close();

  test = await setup({offlineConsent:true,noStorage:true,mobile:true});
  await ready(test.page);
  await test.page.getByRole('link',{name:'Продолжить без аналитики'}).click();
  await test.page.getByRole('heading',{name:'Вы не робот?'}).waitFor();
  assert.equal(await test.page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await test.page.getByRole('slider').press('Enter');
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  assert.equal(await test.page.evaluate(()=>window.ClickShotConsent.getState().analytics),false);
  assert.equal(test.external.length,0);
  await test.page.reload();
  await test.page.getByRole('link',{name:'Продолжить без аналитики'}).click();
  await test.page.getByRole('heading',{name:'Вы не робот?'}).waitFor();
  assert.equal(test.errors.length,0,test.errors.join('\n'));
  record('Refusal challenge works on mobile with consent service and browser storage unavailable');
  await test.context.close();

  test = await setup({delayGrant:true});
  await ready(test.page);
  const grantRequest = test.page.waitForRequest(r=>r.url().includes('/api/consent.php') && r.postDataJSON()?.action==='granted');
  await test.page.getByRole('slider').press('Enter');
  await grantRequest;
  await test.page.getByRole('link',{name:'Продолжить без аналитики'}).click();
  await test.page.getByRole('heading',{name:'Вы не робот?'}).waitFor();
  await test.page.waitForTimeout(800);
  assert.equal(await test.page.locator('#client-action').evaluate(el=>el.closest('[inert]') !== null),true);
  await test.page.getByRole('slider').press('Enter');
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  assert.equal(await test.page.evaluate(()=>window.ClickShotConsent.getState().analytics),false);
  assert.equal(test.external.length,0);
  assert.equal(test.errors.length,0,test.errors.join('\n'));
  record('Delayed acceptance cannot close or authorize the refusal challenge');
  await test.context.close();

  // Reproduce a UTM-only visit after a saved refusal, keeping cookie mode enabled.
  php("$s=site_by_id('browser_conditional')['slide_cookie'];$s['reject_action']='slider';save_consent_details('browser_conditional',$s);");
  test = await setup();
  await ready(test.page,'?mode=conditional&utm_source=rsya');
  await test.page.getByRole('heading',{name:'Сайт использует cookie'}).waitFor();
  await test.page.getByRole('link',{name:'Продолжить без аналитики'}).click();
  await test.page.getByRole('heading',{name:'Вы не робот?'}).waitFor();
  await ready(test.page,'?mode=conditional&utm_source=rsya');
  await test.page.getByRole('heading',{name:'Вы не робот?'}).waitFor();
  assert.equal(await test.page.getByRole('dialog').getByRole('link').count(),0);
  assert.ok((await test.page.getByRole('dialog').innerText()).includes('без сбора cookie'));
  assert.equal(test.errors.length,0,test.errors.join('\n'));
  record('A matching UTM shows cookie mode for a fresh visitor and only the continuation slider after a saved refusal');
  await test.context.close();

  // The standalone slider needs no operator, policy or Metrika settings.
  test = await setup({admin:true});
  const update = await test.context.request.put(origin+'/api/sites.php',{data:{id:'browser_site',slide_cookie:{cookie_enabled:false,operator_name:'',operator_address:'',operator_email:'',policy_url:'',ym_counter:''}}});
  assert.equal(update.status(),200,await update.text());
  await test.page.goto(origin+'/');
  await test.page.locator('[data-neu-tab="cookie"]').click();
  await test.page.locator('#site').selectOption('browser_site');
  await test.page.waitForFunction(()=>!document.querySelector('#slide-cookie-consent-enabled').checked);
  assert.equal(await test.page.locator('#slide-policy-url').isVisible(),false);
  assert.equal(await test.page.locator('#slide-ym-counter').isVisible(),true);
  await test.page.locator('#slide-ym-counter').fill('54321');
  assert.ok((await test.page.locator('.sc-preview-dialog').innerText()).includes('Проведите слайдер вправо для продолжения'));
  await test.page.locator('#save-slide-cookie').click();
  await test.page.locator('#slide-cookie-status').filter({hasText:'Сохранено'}).waitFor();
  await test.page.reload();
  await test.page.locator('[data-neu-tab="cookie"]').click();
  await test.page.waitForFunction(()=>document.querySelector('#slide-ym-counter').value === '54321');
  test.external.length = 0; // Discard the admin page's font request.
  await ready(test.page);
  await test.page.getByRole('dialog').waitFor();
  assert.equal(await test.page.getByRole('dialog').getByRole('link').count(),0);
  assert.equal(await test.page.locator('[data-clickshot-settings]').count(),0);
  assert.equal((await test.page.getByRole('dialog').innerText()).includes('cookie'),false);
  const knob = await test.page.getByRole('slider').boundingBox();
  await test.page.mouse.move(knob.x+knob.width/2,knob.y+knob.height/2);
  await test.page.mouse.down();
  await test.page.mouse.move(knob.x+knob.width/2+250,knob.y+knob.height/2,{steps:12});
  await test.page.mouse.up();
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  assert.equal(await test.page.evaluate(()=>window.ClickShotConsent.getState().analytics),false);
  assert.equal(await test.page.evaluate(()=>localStorage.getItem('clickshot:cookie-choice:browser_site')),null);
  await test.page.reload();
  await test.page.waitForTimeout(400);
  assert.equal(await test.page.getByRole('dialog').count(),0);
  assert.equal(test.external.length,0);
  assert.equal(test.errors.length,0,test.errors.join('\n'));
  const passRecord = await test.page.evaluate(()=>{
    const key = Object.keys(localStorage).find(key=>key.includes(':challenge:') && key.endsWith(':only'));
    return {key,expiresAt:Number(localStorage.getItem(key)),now:Date.now()};
  });
  const week = 7*24*60*60*1000;
  assert.ok(passRecord.expiresAt-passRecord.now > week-10000 && passRecord.expiresAt-passRecord.now <= week);
  const persistedPass = await test.context.storageState();
  await test.page.close();
  test.page = await test.context.newPage();
  await ready(test.page);
  assert.equal(await test.page.getByRole('dialog').count(),0,'Closing the tab and opening another retains completion');
  assert.equal(await test.page.evaluate(key=>Number(localStorage.getItem(key)),passRecord.key),passRecord.expiresAt,'Visits never extend the seven-day deadline');
  const later = await setup({storageState:persistedPass,timeOffset:6*24*60*60*1000});
  await ready(later.page);
  assert.equal(await later.page.getByRole('dialog').count(),0,'Browser restart retains completion on day six');
  await later.context.close();
  const expired = await setup({storageState:persistedPass,timeOffset:week+1000});
  await ready(expired.page);
  await expired.page.getByRole('slider').waitFor();
  await expired.page.getByRole('slider').press('Enter');
  await expired.page.getByRole('dialog').waitFor({state:'detached'});
  assert.ok(await expired.page.evaluate(({key,old})=>Number(localStorage.getItem(key))>old,{key:passRecord.key,old:passRecord.expiresAt}),'Passing again renews the deadline');
  await expired.context.close();
  record('Challenge persists across tabs and browser restart for seven days, expires, and renews only on completion');
  const reset = await test.context.request.put(origin+'/api/sites.php',{data:{id:'browser_site',slide_cookie:{reset_consent:true}}});
  assert.equal(reset.status(),200,await reset.text());
  await ready(test.page);
  await test.page.getByRole('heading',{name:'Вы не робот?'}).waitFor();
  await test.page.getByRole('slider').press('End');
  await test.page.getByRole('slider').press('Enter');
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  assert.equal(await test.page.evaluate(()=>window.ClickShotConsent.getState().analytics),false);
  record('Standalone slider saves without cookie details, supports dragging, persists for seven days, resets by settings version and never enables Metrika');
  await test.context.close();

  // With blocking disabled, leave the site's own tag, queue and storage alone.
  test = await setup({admin:true});
  const independent = await test.context.request.put(origin+'/api/sites.php',{data:{id:'browser_site',slide_cookie:{cookie_enabled:true,block_metrika:false,operator_name:'Тестовый оператор',operator_address:'Тестовый адрес',operator_email:'privacy@example.test',policy_url:'/privacy',ym_counter:'12345',reject_action:'slider'}}});
  assert.equal(independent.status(),200,await independent.text());
  await ready(test.page,'?mode=attribute');
  await test.page.waitForFunction(()=>document.cookie.includes('_ym_uid'));
  assert.equal(test.external.length,1,'The independently installed tag can load before consent');
  assert.ok(await test.page.evaluate(()=>window.fixtureYandexCalls.some(args=>args[1]==='reachGoal' && args[2]==='fixture_goal')),'Existing queue must be preserved');
  assert.equal(await test.page.locator('script[data-clickshot-metrika]').count(),0);
  await test.page.getByRole('link',{name:'Не принимать',exact:true}).click();
  await test.page.getByRole('heading',{name:'Вы не робот?'}).waitFor();
  assert.equal((await test.page.getByRole('dialog').innerText()).includes('без сбора cookie'),false);
  assert.ok(await test.page.evaluate(()=>document.cookie.includes('_ym_uid')),'Refusal must not delete independently managed cookie storage');
  await test.page.getByRole('slider').press('Enter');
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  await open(test.page);
  await grant(test.page);
  assert.equal(test.external.length,1,'Consent must not inject a second Metrika tag');
  await open(test.page);
  await test.page.getByRole('link',{name:'Отозвать согласие'}).click();
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  assert.ok(await test.page.evaluate(()=>document.cookie.includes('_ym_uid')));
  assert.equal(await test.page.evaluate(()=>window.fixtureYandexCalls.some(args=>args[1]==='destruct')),false);
  assert.equal(test.errors.length,0,test.errors.join('\n'));
  record('Unchecked Metrika blocking preserves external tags, queues and cookies before consent, after refusal and withdrawal');
  await test.context.close();

  test = await setup();
  php("$s=site_by_id('browser_site')['slide_cookie'];$s['cookie_enabled']=false;save_consent_details('browser_site',$s);");
  await ready(test.page,'?mode=attribute');
  await test.page.getByRole('heading',{name:'Вы не робот?'}).waitFor();
  await test.page.waitForFunction(()=>document.cookie.includes('_ym_uid'));
  assert.equal(test.external.length,1);
  await test.page.getByRole('slider').press('Enter');
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  assert.equal(await test.page.evaluate(()=>window.ClickShotConsent.getState().analytics),false,'Standalone challenge must not grant consent');
  assert.equal(test.external.length,1);
  assert.equal(test.errors.length,0,test.errors.join('\n'));
  assert.deepEqual(await test.page.evaluate(()=>window.fixtureYandexCalls.filter(args=>args[1]==='reachGoal' && args[2].startsWith('slider_challenge_'))), [
    [12345,'reachGoal','slider_challenge_shown'],
    [12345,'reachGoal','slider_challenge_started'],
    [12345,'reachGoal','slider_challenge_passed'],
  ],'Keyboard challenge sends all three goals exactly once to the configured counter');
  record('Independent standalone captcha sends shown, started and passed goals to Yandex');
  await test.context.close();

  // A different configured target is respected; failures cannot block completion.
  php("db()->exec(\"UPDATE site_collection_settings SET slide_cookie_ym_counter='54321' WHERE site_id='browser_site'\");");
  test = await setup();
  await ready(test.page,'?mode=attribute');
  await test.page.waitForFunction(()=>window.fixtureYandexCalls?.some(args=>args[2]==='slider_challenge_shown'));
  const goalKnob = await test.page.getByRole('slider').boundingBox();
  await test.page.mouse.move(goalKnob.x+goalKnob.width/2,goalKnob.y+goalKnob.height/2);
  await test.page.mouse.down();
  await test.page.mouse.move(goalKnob.x+goalKnob.width/2+250,goalKnob.y+goalKnob.height/2,{steps:12});
  await test.page.mouse.up();
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  assert.deepEqual(await test.page.evaluate(()=>window.fixtureYandexCalls.filter(args=>args[1]==='reachGoal' && args[2].startsWith('slider_challenge_'))), [
    [54321,'reachGoal','slider_challenge_shown'],
    [54321,'reachGoal','slider_challenge_started'],
    [54321,'reachGoal','slider_challenge_passed'],
  ]);
  await test.context.close();
  test = await setup();
  await ready(test.page,'?mode=attribute');
  await test.page.waitForFunction(()=>window.fixtureYandexCalls);
  await test.page.evaluate(()=>{window.ym=()=>{throw new Error('Unavailable counter')};});
  await test.page.getByRole('slider').press('Enter');
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  assert.equal(test.errors.length,0);
  await test.context.close();
  php("db()->exec(\"UPDATE site_collection_settings SET slide_cookie_ym_counter='' WHERE site_id='browser_site'\");");
  test = await setup();
  await ready(test.page,'?mode=attribute');
  await test.page.waitForFunction(()=>window.fixtureYandexCalls);
  await test.page.getByRole('slider').press('Enter');
  await test.page.getByRole('dialog').waitFor({state:'detached'});
  assert.equal(await test.page.evaluate(()=>window.fixtureYandexCalls.filter(args=>args[2]?.startsWith('slider_challenge_')).length),0);
  await test.context.close();
  php("db()->exec(\"UPDATE site_collection_settings SET slide_cookie_ym_counter='12345' WHERE site_id='browser_site'\");");
  record('Pointer goals respect the configured counter; blank ID disables delivery and SDK errors do not block the slider');

  test = await setup({admin:true});
  const customTitle = 'Проверка «Человек?» <img src=x onerror=alert(1)>';
  const custom = await test.context.request.put(origin+'/api/sites.php',{data:{id:'browser_site',slide_cookie:{challenge_title:customTitle,cookie_enabled:false,reset_consent:true}}});
  assert.equal(custom.status(),200,await custom.text());
  await ready(test.page);
  await test.page.getByRole('heading',{name:customTitle,exact:true}).waitFor();
  assert.equal(await test.page.getByRole('dialog').locator('img').count(),0,'Custom titles render as plain text');
  const withCookie = await test.context.request.put(origin+'/api/sites.php',{data:{id:'browser_site',slide_cookie:{cookie_enabled:true,reset_consent:true}}});
  assert.equal(withCookie.status(),200,await withCookie.text());
  await ready(test.page);
  await test.page.getByRole('heading',{name:'Сайт использует cookie'}).waitFor();
  await test.page.getByRole('link',{name:'Не принимать',exact:true}).click();
  await test.page.getByRole('heading',{name:customTitle,exact:true}).waitFor();
  assert.equal(await test.page.getByRole('dialog').locator('img').count(),0);
  assert.equal(test.errors.length,0,test.errors.join('\n'));
  record('Custom captcha title persists and renders safely in standalone and refusal challenges');
  await test.context.close();

  writeFileSync(join(output,'results.json'),JSON.stringify(results,null,2));
  console.log(`PASS ${results.length} browser consent scenarios; screenshots: ${output}`);
} finally {
  await browser?.close();
  server.kill('SIGTERM');
}
