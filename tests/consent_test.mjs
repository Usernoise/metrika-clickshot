// Real HTTP + isolated SQLite: validates evidence, versioning, withdrawal and failures.
import {mkdtempSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join, resolve} from 'node:path';
import {spawn, execFileSync} from 'node:child_process';
import net from 'node:net';
import assert from 'node:assert/strict';

const root = resolve(import.meta.dirname, '..');
const dir = mkdtempSync(join(tmpdir(), 'clickshot-consent-'));
const env = {...process.env, METRIKA_DB_PATH:join(dir,'test.sqlite'), METRIKA_DASHBOARD_PASSWORD:'consent-test-only'};
const php = code => execFileSync('php',['-r',`require ${JSON.stringify(join(root,'lib.php'))};${code}`],{env,encoding:'utf8'}).trim();
execFileSync('php',[join(root,'bin/database.php'),'migrate'],{env});
const reservation = net.createServer();
await new Promise(r => reservation.listen(0,'127.0.0.1',r));
const port = reservation.address().port;
await new Promise(r => reservation.close(r));
const server = spawn('php',['-S',`127.0.0.1:${port}`,'-t',join(root,'public')],{env,stdio:'ignore'});
const base = `http://127.0.0.1:${port}`;
const auth = 'Basic ' + Buffer.from('admin:consent-test-only').toString('base64');
async function request(path, body, headers = {}) {
  const response = await fetch(base + path,{method:'POST',headers:{'Content-Type':'application/json',...headers},body:JSON.stringify(body)});
  return {status:response.status,data:await response.json()};
}
async function update(settings) {
  const r = await fetch(base+'/api/sites.php',{method:'PUT',headers:{'Content-Type':'application/json',Authorization:auth,Origin:base},body:JSON.stringify({id:'consent_site',slide_cookie:settings})});
  return {status:r.status,data:await r.json()};
}
const evidence = () => JSON.parse(php("echo json_encode(consent_document(site_by_id('consent_site')['slide_cookie']));"));
const consent = body => request('/api/consent.php?site=consent_site',body,{Origin:'https://consent.example'});
const settings = {enabled:true,policy_url:'/privacy',ym_counter:'12345',operator_name:'Тестовый оператор',
  operator_address:'Тестовый адрес',operator_email:'privacy@example.test',consent_days:180};
try {
  for (let i=0;i<100;i++) { try {await fetch(base+'/health.php');break;} catch {await new Promise(r=>setTimeout(r,25));} }
  assert.equal((await request('/api/sites.php',{id:'consent_site',name:'Test',domains:['consent.example']},{Authorization:auth})).status,201);
  assert.equal((await update({enabled:true,policy_url:'#'})).status,400);
  assert.equal((await update({...settings,operator_email:''})).status,400);
  assert.equal((await update({...settings,block_metrika:false})).status,400);
  assert.equal((await update(settings)).status,200);
  const doc = evidence();
  const first = {action:'granted',method:'slider',version:doc.version,hash:doc.hash};
  assert.equal((await consent({...first,hash:'0'.repeat(64)})).status,409,'Never grant stale terms');
  assert.equal((await consent({...first,method:'link'})).status,400);
  assert.equal((await request('/api/consent.php?site=consent_site',first,{Origin:'https://foreign.example'})).status,403);
  assert.equal((await request('/api/consent.php?site=consent_site',first,{Origin:'null'})).status,403);
  const preflight = await fetch(base+'/api/consent.php?site=consent_site',{method:'OPTIONS',headers:{Origin:'https://foreign.example'}});
  assert.equal(preflight.status,403);
  let response = await consent(first);
  assert.equal(response.status,200);
  const grant = response.data.choice;
  assert.equal(grant.categories.analytics,true);
  assert.equal(grant.version,doc.version);
  assert.equal(grant.expires_at-grant.created_at,180*86400000);
  assert.equal((await consent({action:'verify',receipt:grant.receipt})).data.valid,true);
  assert.equal((await consent({action:'verify',receipt:'f'.repeat(64)})).data.valid,false);
  const rows = JSON.parse(php("echo json_encode(db()->query('SELECT * FROM consent_receipts')->fetchAll());"));
  assert.equal(rows.length,1,'Invalid grants do not create evidence');
  assert.ok(!('ip' in rows[0]) && !('user_agent' in rows[0]));
  const archive = JSON.parse(php("echo db()->query('SELECT document_json FROM consent_documents')->fetchColumn();"));
  assert.equal(archive.hash,doc.hash);
  assert.ok(archive.text.includes('Тестовый оператор'));
  // Different customer: documents, origin checks and receipts must never cross sites.
  const secondSettings = {...settings,operator_name:'Владелец второго сайта',operator_email:'second@example.test',policy_url:'/second-privacy',ym_counter:'54321'};
  assert.equal((await request('/api/sites.php',{id:'second_site',name:'Second',domains:['second.example'],slide_cookie:secondSettings},{Authorization:auth})).status,201);
  const secondDocument = JSON.parse(php("echo json_encode(consent_document(site_by_id('second_site')['slide_cookie']));"));
  assert.equal(secondDocument.operator,'Владелец второго сайта');
  assert.ok(secondDocument.text.includes('second@example.test') && !secondDocument.text.includes('privacy@example.test'));
  assert.notEqual(secondDocument.hash,doc.hash);
  assert.equal((await request('/api/consent.php?site=second_site',{action:'verify',receipt:grant.receipt},{Origin:'https://second.example'})).data.valid,false);
  assert.equal((await request('/api/consent.php?site=second_site',{...first},{Origin:'https://second.example'})).status,409);
  assert.equal((await request('/api/consent.php?site=second_site',{action:'denied',method:'link',receipt:grant.receipt},{Origin:'https://second.example'})).status,200);
  assert.equal((await consent({action:'verify',receipt:grant.receipt})).data.valid,true,'Second site cannot revoke the first site receipt');
  assert.equal((await request('/api/consent.php?site=consent_site',first,{Origin:'https://second.example'})).status,403);
  const color = await update({accent_color:'#ff9900'});
  assert.equal(color.data.slide_cookie.version,doc.version,'Cosmetic changes preserve consent');
  assert.equal(evidence().hash,doc.hash);
  const terms = await update({consent_text:'Отдельные дополнительные условия.'});
  assert.equal(terms.data.slide_cookie.version,doc.version+1,'Material changes require new consent');
  assert.equal((await consent({action:'verify',receipt:grant.receipt})).data.valid,false);
  assert.equal((await consent(first)).status,409);
  // Withdrawal must work even for an old version.
  response = await consent({action:'withdrawn',method:'link',receipt:grant.receipt});
  assert.equal(response.status,200);
  assert.equal(response.data.choice.categories.analytics,false);
  assert.equal((await consent({action:'verify',receipt:grant.receipt})).data.valid,false);
  assert.ok(php(`echo db()->query("SELECT revoked_at FROM consent_receipts WHERE decision='granted'")->fetchColumn();`));
  const nextDoc = evidence();
  response = await consent({action:'granted',method:'keyboard',version:nextDoc.version,hash:nextDoc.hash});
  const nextGrant = response.data.choice;
  assert.equal((await consent({action:'verify',receipt:nextGrant.receipt})).data.valid,true);
  php(`db()->exec("UPDATE consent_receipts SET expires_at=1 WHERE receipt='${nextGrant.receipt}'");`);
  assert.equal((await consent({action:'verify',receipt:nextGrant.receipt})).data.valid,false,'Expired grants are refused');
  await consent({action:'denied',method:'link'});
  const deletion = php("$pdo=db();$pdo->beginTransaction();$pdo->exec(\"DELETE FROM sites WHERE id='consent_site'\");foreach(['site_consent_settings','consent_documents','consent_receipts','consent_budget'] as $table) if($pdo->query(\"SELECT COUNT(*) FROM {$table} WHERE site_id='consent_site'\")->fetchColumn()) throw new RuntimeException('Orphan evidence');$pdo->rollBack();echo 'ok';");
  assert.equal(deletion,'ok','Deleting a site cascades to consent settings and evidence');
  php('db()->exec("UPDATE consent_receipts SET created_at=1");');
  execFileSync('php',[join(root,'bin/database.php'),'prune-consent'],{env});
  assert.equal(php("echo db()->query('SELECT count(*) FROM consent_receipts')->fetchColumn();"),'0');
  php("db()->exec('DROP TABLE consent_documents');");
  assert.equal((await consent({action:'granted',method:'slider',version:nextDoc.version,hash:nextDoc.hash})).status,503,'No grant if evidence cannot be written');
  assert.equal(php("echo db()->query('SELECT count(*) FROM consent_receipts')->fetchColumn();"),'0');
  console.log('PASS Consent HTTP: origins, snapshots, explicit grants, versioning, withdrawal, expiry, retention and storage failure');
} finally { server.kill('SIGTERM'); }
