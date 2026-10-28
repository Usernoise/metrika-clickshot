// Real browser, temporary database: every Slide Cookie field must survive save + reload.
import {mkdtempSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join, resolve} from 'node:path';
import {spawn, execFileSync} from 'node:child_process';
import {createRequire} from 'node:module';
import net from 'node:net';
import assert from 'node:assert/strict';

const require = createRequire(import.meta.url);
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = resolve(import.meta.dirname, '..');
const dir = mkdtempSync(join(tmpdir(),'clickshot-slide-form-'));
const env = {...process.env, METRIKA_DB_PATH:join(dir,'form.sqlite'),METRIKA_DASHBOARD_PASSWORD:'form-test-only'};
execFileSync('php',[join(root,'bin/database.php'),'migrate'],{env});
execFileSync('php',['-r',`require ${JSON.stringify(join(root,'lib.php'))};
  db()->prepare('INSERT INTO sites VALUES(?,?,?,?,1)')->execute(['form_site','form_site','["example.test"]',date(DATE_ATOM)]);`],{env});
const reservation = net.createServer();
await new Promise(r=>reservation.listen(0,'127.0.0.1',r));
const port = reservation.address().port;
await new Promise(r=>reservation.close(r));
const server = spawn('php',['-S',`127.0.0.1:${port}`,'-t',join(root,'public')],{env,stdio:'ignore'});
const origin = `http://127.0.0.1:${port}`;
const auth = 'Basic ' + Buffer.from('admin:form-test-only').toString('base64');
const put = async slide_cookie => {
  const r = await fetch(origin+'/api/sites.php',{method:'PUT',headers:{'Content-Type':'application/json',Authorization:auth},body:JSON.stringify({id:'form_site',slide_cookie})});
  return {status:r.status, data:await r.json()};
};
let browser;
try {
  for (let i = 0; i < 50; i++) { try { await fetch(origin+'/health.php'); break; } catch { await new Promise(r=>setTimeout(r,100)); } }

  const all = await put({enabled:true,policy_url:'./privacy',operator_email:'почта@сайт.рф',consent_days:0,ym_counter:'0123'});
  assert.equal(all.status,400);
  for (const part of ['ссылка на политику','срок согласия','ID Метрики','email','оператора ПД','адрес оператора']) {
    assert.ok(all.data.error.includes(part),`All errors are reported at once, missing: ${part}\n${all.data.error}`);
  }
  const noKey = await put({enabled:true,policy_url:'/privacy',ym_counter:'12345',operator_name:'О',operator_address:'А',operator_email:'a@example.test',param:'utm_source',key:''});
  assert.ok(noKey.status === 400 && noKey.data.error.includes('значение параметра'),'Conditional display requires a value');

  browser = await chromium.launch(process.env.PLAYWRIGHT_CHANNEL ? {channel:process.env.PLAYWRIGHT_CHANNEL} : {});
  const context = await browser.newContext({httpCredentials:{username:'admin',password:'form-test-only'}});
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror',error=>errors.push(error.message));
  const open = async () => {
    await page.goto(origin+'/');
    await page.locator('[data-neu-tab="cookie"]').click();
    await page.waitForFunction(()=>document.querySelector('#site').value === 'form_site');
  };
  await open();
  await page.locator('#slide-cookie-enabled').check();
  const values = {
    '#slide-policy-url':'example.test/privacy', '#slide-ym-counter':'87 654 321',
    '#slide-operator-name':'ООО «Тест»', '#slide-operator-address':'Москва, ул. Тестовая, 1',
    '#slide-operator-email':'privacy@example.test', '#slide-consent-days':'90',
    '#slide-consent-text':'Дополнительное условие.\nВторая строка.', '#slide-key':'google',
  };
  await page.locator('#slide-param').selectOption('utm_source');
  for (const [selector, value] of Object.entries(values)) await page.locator(selector).fill(value);
  await page.locator('#slide-accent-color').fill('#112233');
  await page.locator('#save-slide-cookie').click();
  await page.locator('#slide-cookie-status').filter({hasText:'Сохранено'}).waitFor();

  await open();
  const saved = await page.evaluate(()=>Object.fromEntries(['policy-url','ym-counter','operator-name','operator-address','operator-email','consent-days','consent-text','key','param','accent-color']
    .map(name=>[name,document.querySelector('#slide-'+name).value]).concat([['enabled',document.querySelector('#slide-cookie-enabled').checked]])));
  assert.deepEqual(saved,{
    'policy-url':'https://example.test/privacy','ym-counter':'87654321','operator-name':'ООО «Тест»',
    'operator-address':'Москва, ул. Тестовая, 1','operator-email':'privacy@example.test','consent-days':'90',
    'consent-text':'Дополнительное условие.\nВторая строка.','key':'google','param':'utm_source','accent-color':'#112233',enabled:true,
  },'Every field survives save and reload');

  const before = (await put({})).data.slide_cookie.version;
  await put({enabled:false});
  assert.equal((await put({enabled:true})).data.slide_cookie.version,before,'Toggling the banner keeps visitor consent');

  await page.locator('#slide-consent-days').fill('');
  await page.locator('#save-slide-cookie').click();
  const status = page.locator('#slide-cookie-status');
  await status.filter({hasText:'срок согласия'}).waitFor();
  assert.equal(await status.evaluate(el=>el.classList.contains('is-error')),true,'Errors are highlighted');

  await page.route('**/api/sites.php',route=>route.request().method()==='PUT' ? route.fulfill({status:502,contentType:'text/html',body:'<html>502</html>'}) : route.continue());
  await page.locator('#slide-consent-days').fill('90');
  await page.locator('#save-slide-cookie').click();
  await status.filter({hasText:'HTTP 502'}).waitFor();
  assert.equal(errors.length,0,errors.join('\n'));
  console.log('PASS Slide Cookie form: all errors at once, every field survives reload, toggle keeps version, non-JSON errors readable');
} finally {
  await browser?.close();
  server.kill('SIGTERM');
}
