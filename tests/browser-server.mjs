import {mkdtempSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join, resolve} from 'node:path';
import {spawn, execFileSync} from 'node:child_process';
const root = resolve(import.meta.dirname, '..');
const dir = mkdtempSync(join(tmpdir(), 'clickshot-browser-'));
const env = {...process.env, METRIKA_DB_PATH:join(dir,'browser.sqlite'),METRIKA_DASHBOARD_PASSWORD:'browser-fixture-only'};
execFileSync('php',[join(root,'bin/database.php'),'migrate'],{env});
execFileSync('php',['-r', `require ${JSON.stringify(join(root,'lib.php'))};
foreach (['browser_site','browser_conditional'] as $id) {
  $stmt=db()->prepare('INSERT INTO sites VALUES(?,?,?, ?,1)');
  $stmt->execute([$id,$id,'["127.0.0.1"]',date(DATE_ATOM)]);
  $stmt=db()->prepare("INSERT INTO site_collection_settings(site_id,slide_cookie_enabled,slide_cookie_policy_url,slide_cookie_param,slide_cookie_key,slide_cookie_ym_counter,updated_at) VALUES(?,1,'/privacy',?,?,'12345',?)");
  $stmt->execute([$id,$id==='browser_conditional'?'utm_source':'always',$id==='browser_conditional'?'ad':'',date(DATE_ATOM)]);
}`],{env});
const server=spawn('php',['-S','127.0.0.1:18743','-t',join(root,'public'),join(root,'tests/browser-router.php')],{env,stdio:'inherit'});
console.log('Fixture at http://127.0.0.1:18743/fixture; database: '+dir);
for (const signal of ['SIGINT','SIGTERM']) process.on(signal,()=>{server.kill(signal);});
server.on('exit',code=>process.exit(code ?? 0));
