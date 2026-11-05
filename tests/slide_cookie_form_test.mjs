// Real browser, temporary database: every Slide Cookie field must survive save + reload.
import {mkdtempSync, mkdirSync} from 'node:fs';
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
    '#slide-challenge-title':'Подтвердите, что вы человек', '#slide-policy-url':'example.test/privacy', '#slide-ym-counter':'87 654 321',
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
  const saved = await page.evaluate(()=>Object.fromEntries(['challenge-title','policy-url','ym-counter','operator-name','operator-address','operator-email','consent-days','consent-text','key','param','accent-color']
    .map(name=>[name,document.querySelector('#slide-'+name).value]).concat([['enabled',document.querySelector('#slide-cookie-enabled').checked]])));
  assert.deepEqual(saved,{
    'challenge-title':'Подтвердите, что вы человек','policy-url':'https://example.test/privacy','ym-counter':'87654321','operator-name':'ООО «Тест»',
    'operator-address':'Москва, ул. Тестовая, 1','operator-email':'privacy@example.test','consent-days':'90',
    'consent-text':'Дополнительное условие.\nВторая строка.','key':'google','param':'utm_source','accent-color':'#112233',enabled:true,
  },'Every field survives save and reload');

  // Both themes must keep native checkbox dimensions and fit laptop/tablet/mobile widths.
  const layoutOutput = join(root,'output/playwright/slide-layout');
  mkdirSync(layoutOutput,{recursive:true});
  for (const theme of ['classic','neu']) {
    await page.evaluate(theme=>document.documentElement.dataset.theme=theme,theme);
    for (const width of [1440,1024,375]) {
      await page.setViewportSize({width,height:900});
      const dimensions = await page.locator('#slide-cookie-panel').evaluate(panel=>({
        overflow:document.documentElement.scrollWidth>innerWidth,
        checkboxes:[...panel.querySelectorAll('input[type=checkbox]')].filter(el=>el.getClientRects().length).map(el=>({width:el.getBoundingClientRect().width,height:el.getBoundingClientRect().height,mask:getComputedStyle(el).maskImage})),
        controls:[...panel.querySelectorAll('.sc-field input,.sc-field select')].filter(el=>el.getClientRects().length).map(el=>el.getBoundingClientRect().height)
      }));
      assert.equal(dimensions.overflow,false,`${theme} ${width}: horizontal overflow`);
      assert.ok(dimensions.checkboxes.every(d=>d.width>=16 && d.width<=24 && d.height>=16 && d.height<=24 && d.mask==='none'),`${theme} ${width}: stretched checkbox`);
      assert.ok(dimensions.controls.every(height=>height===44),`${theme} ${width}: inconsistent control heights`);
      if(width!==1024) await page.locator('#slide-cookie-panel').screenshot({path:join(layoutOutput,`${theme}-${width}.png`)});
    }
  }
  await page.setViewportSize({width:1440,height:900});
  await page.evaluate(()=>document.documentElement.dataset.theme='neu');

  assert.equal(await page.locator('#slide-block-metrika').isEnabled(),true);
  await page.locator('#slide-block-metrika').uncheck();
  await page.locator('#save-slide-cookie').click();
  await page.locator('#slide-cookie-status').filter({hasText:'Сохранено'}).waitFor();
  await open();
  assert.equal(await page.locator('#slide-block-metrika').isChecked(),false);
  assert.ok((await page.locator('#slide-metrika-warning').innerText()).includes('нарушает'));
  assert.equal((await put({})).data.slide_cookie.block_metrika,false);
  await page.locator('#slide-cookie-panel').screenshot({path:join(layoutOutput,'neu-independent.png')});

  const before = (await put({})).data.slide_cookie.version;
  await put({enabled:false});
  assert.equal((await put({enabled:true})).data.slide_cookie.version,before,'Toggling the banner keeps visitor consent');

  await page.locator('#slide-consent-days').fill('');
  await page.locator('#save-slide-cookie').click();
  const status = page.locator('#slide-cookie-status');
  await status.filter({hasText:'срок согласия'}).waitFor();
  assert.equal(await status.evaluate(el=>el.classList.contains('is-error')),true,'Errors are highlighted');

  // Hidden consent fields must not prevent enabling the standalone challenge.
  await page.locator('#slide-policy-url').fill('./unfinished-policy');
  await page.locator('#slide-cookie-consent-enabled').uncheck();
  assert.equal(await page.locator('#slide-block-metrika').isVisible(),true);
  assert.equal(await page.locator('.sc-preview-dialog h3').innerText(),'Подтвердите, что вы человек');
  const standaloneResponse = page.waitForResponse(r=>r.url().endsWith('/api/sites.php') && r.request().method()==='PUT');
  await page.locator('#save-slide-cookie').click();
  const standalone = await standaloneResponse;
  assert.equal(standalone.status(),200,await standalone.text());
  await status.filter({hasText:'Сохранено'}).waitFor();
  await open();
  assert.equal(await page.locator('#slide-cookie-consent-enabled').isChecked(),false);
  await page.locator('#slide-cookie-panel').screenshot({path:join(layoutOutput,'neu-slider-only.png')});
  await page.locator('#slide-cookie-enabled').uncheck();
  assert.equal(await page.locator('#slide-cookie-config').isVisible(),false);
  assert.equal(await page.locator('#save-slide-cookie').isVisible(),true);
  await page.locator('#slide-cookie-enabled').check();
  await page.locator('#slide-cookie-consent-enabled').check();
  assert.equal(await page.locator('#slide-policy-url').inputValue(),'https://example.test/privacy','Disabling cookies preserves the last saved consent settings');
  assert.equal(await page.locator('#slide-consent-days').inputValue(),'90');

  await page.route('**/api/sites.php',route=>route.request().method()==='PUT' ? route.fulfill({status:502,contentType:'text/html',body:'<html>502</html>'}) : route.continue());
  await page.locator('#slide-consent-days').fill('90');
  await page.locator('#save-slide-cookie').click();
  await status.filter({hasText:'HTTP 502'}).waitFor();
  assert.equal(errors.length,0,errors.join('\n'));
  console.log('PASS Slide Cookie form: save/reload, error handling, mode toggles and layout in both themes at 1440/1024/375px');
} finally {
  await browser?.close();
  server.kill('SIGTERM');
}
