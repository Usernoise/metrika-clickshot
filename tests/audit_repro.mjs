// Isolated diagnostic harness. Never opens the project's storage database.
import {mkdtempSync, readFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join, resolve} from 'node:path';
import {spawn, execFileSync} from 'node:child_process';
import vm from 'node:vm';
import net from 'node:net';

const root = resolve(import.meta.dirname, '..');
const dir = mkdtempSync(join(tmpdir(), 'clickshot-audit-'));
const env = {...process.env, METRIKA_DB_PATH: join(dir, 'audit.sqlite'), METRIKA_DASHBOARD_PASSWORD: 'isolated-audit-only',METRIKA_HITS_PER_MINUTE:'10'};
execFileSync('php', [join(root, 'bin/database.php'), 'migrate'], {env});
const reservation = net.createServer();
await new Promise(r => reservation.listen(0, '127.0.0.1', r));
const port = reservation.address().port;
await new Promise(r => reservation.close(r));
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', join(root, 'public')], {env, stdio: 'ignore'});
const base = `http://127.0.0.1:${port}`;
const auth = 'Basic ' + Buffer.from('admin:isolated-audit-only').toString('base64');
const php = code => execFileSync('php', ['-r', `require ${JSON.stringify(join(root, 'lib.php'))}; ${code}`], {env, encoding:'utf8'}).trim();
const sql = query => JSON.parse(php(`echo json_encode(db()->query(${JSON.stringify(query)})->fetchAll());`));
const results = [];
const check = (name, ok, evidence) => { results.push({name, ok, evidence}); console.log(`${ok ? 'PASS' : 'FAIL'} ${name}: ${JSON.stringify(evidence)}`); };
async function request(path, method='GET', body, headers={}) {
  const r = await fetch(base+path, {method, headers:{'Content-Type':'application/json', ...headers}, body:body === undefined ? undefined : JSON.stringify(body)});
  const text = await r.text();
  return {status:r.status, data:text ? JSON.parse(text) : null};
}
const admin = (method, body) => request('/api/sites.php', method, body, {Authorization:auth});
const hit = (body, headers={'Origin':'https://audit.example'}) => request('/api/hit.php', 'POST', {site:'audit_site', ...body}, headers);
try {
  for (let i=0;i<100;i++) {
    try {await fetch(base+'/api/sites.php'); break;} catch {await new Promise(r => setTimeout(r, 30));}
  }
  const created = await admin('POST', {id:'audit_site',name:'Audit',domains:['audit.example'],collection:{tech:true}});
  check('Create isolated site', created.status === 201, created);
  const view = await hit({path:'/page', referrer:'https://search.example/query',newVisit:true});
  check('Accept allowed pre-consent pageview', view.status === 204, view.status);
  const originCases = [
    [{Origin:'https://foreign.example',Referer:'https://audit.example/'},403],
    [{Origin:'null'},403], [{Origin:'broken'},403],
    [{Referer:'https://audit.example/page'},204],
    [{Origin:'https://audit.example:8443'},204]
  ];
  const originResults=[];
  for (const [headers,expected] of originCases) originResults.push([(await hit({path:'/page'},headers)).status,expected]);
  check('Origin and Referer precedence matrix',originResults.every(([a,b])=>a===b),originResults);
  const absent = await hit({path:'/no-origin'}, {});
  const foreign = await hit({path:'/foreign-referer'}, {Referer:'https://foreign.example/'});
  check('Reject missing Origin and foreign Referer', absent.status >= 400 && foreign.status >=400, [absent.status, foreign.status]);
  const before = sql("SELECT SUM(pageviews) n FROM page_daily_stats WHERE site_id='audit_site'")[0].n;
  await hit({event:'test_goal',referrer:'https://search.example/query',newVisit:true});
  const after = sql("SELECT SUM(pageviews) n FROM page_daily_stats WHERE site_id='audit_site'")[0].n;
  check('Goals must not increment pageviews', before === after, {before,after});
  const sourceStats = await request('/api/stats.php?site=audit_site&sources=search.example','GET',undefined,{Authorization:auth});
  check('Source report contains goal without extra pageviews',sourceStats.data.totals.pageviews===1 && sourceStats.data.events[0]?.events===1,sourceStats.data.totals);
  const badQuery = await request('/api/stats.php?site=audit_site&from[]=x','GET',undefined,{Authorization:auth});
  check('Reject non-scalar query without fatal error',badQuery.status===400,badQuery.status);
  const csrf = await request('/api/sites.php','POST',{name:'CSRF',domains:['csrf.example']},{Authorization:auth,Origin:'https://foreign.example'});
  check('Reject foreign administrative Origin',csrf.status===403,csrf.status);
  const textPost = await request('/api/sites.php','POST',{name:'Text',domains:['text.example']},{Authorization:auth,'Content-Type':'text/plain'});
  check('Reject simple cross-site POST content type',textPost.status===415,textPost.status);
  await admin('DELETE', {id:'audit_site'});
  const orphanTables = ['source_page_daily_stats','source_browser_daily_stats','source_os_daily_stats','source_device_daily_stats','source_event_daily_stats'];
  const orphans = Object.fromEntries(orphanTables.map(t => [t,sql(`SELECT COUNT(*) n FROM ${t} WHERE site_id='audit_site'`)[0].n]));
  check('Delete all source aggregates', Object.values(orphans).every(n => n===0), orphans);
  await admin('DELETE', {id:'neural_courses'});
  const sites = await admin('GET');
  check('Deleting last site leaves empty list', sites.data.sites.length===0, sites.data.sites.map(s => s.id));
  const long = await admin('POST',{name:'Long domain',domains:['a'.repeat(60)+'.example.com']});
  const longStats = await request('/api/stats.php?site='+long.data.id,'GET',undefined,{Authorization:auth});
  check('Generated site ID is usable', longStats.status===200, {idLength:long.data.id?.length,create:long.status,stats:longStats.status});
  const empty = await admin('POST',{name:'Empty domains',domains:[' ']});
  check('Reject normalized empty domains', empty.status>=400, {status:empty.status});
  const matrixFailures=[];
  for (let mask=0;mask<32;mask++) {
    const collection=Object.fromEntries(['pageviews','pages','referrers','visits','tech'].map((k,i)=>[k,Boolean(mask & (1<<i))]));
    const id='matrix_'+mask;
    const c=await admin('POST',{id,name:id,domains:['audit.example'],collection});
    const h=await hit({site:id,path:'/sample',referrer:'https://search.example/query',newVisit:true});
    const g=await hit({site:id,event:'sample_goal',referrer:'https://search.example/query',newVisit:true});
    const s=await request('/api/stats.php?site='+id,'GET',undefined,{Authorization:auth});
    const d=s.data;
    if(c.status!==201 || h.status!==204 || g.status!==204 || s.status!==200
      || d.totals.pageviews!==Number(collection.pageviews) || d.totals.visits!==Number(collection.visits)
      || d.pages.length!==Number(collection.pages) || (collection.pages && d.pages[0].pageviews!==1)
      || d.events[0]?.events!==1 || d.events[0]?.visits!==Number(collection.visits)
      || d.referrers.length!==Number(collection.referrers)
      || (collection.referrers && d.referrers[0].pageviews!==1)
      || d.tech.browsers.length!==Number(collection.tech)
      || (collection.tech && d.tech.browsers[0].pageviews!==1)) matrixFailures.push(mask);
  }
  check('All 32 collection combinations keep goals separate',matrixFailures.length===0,matrixFailures);
  await admin('POST',{id:'rate_site',name:'Rate',domains:['audit.example']});
  let rate;
  for(let i=0;i<11;i++) rate=await hit({site:'rate_site',path:'/rate'});
  check('Per-site budget rejects excess without counting it',rate.status===429 && sql("SELECT pageviews FROM daily_stats WHERE site_id='rate_site'")[0].pageviews===10,rate.status);
  const paths = ['/user/%31%32%33%34%35/','/token/ABCDEFGHIJKLMNOPQRSTUVWXYZabcdef/','/phone/+79991234567/'];
  const cleaned = paths.map(p => php(`echo clean_path(${JSON.stringify(p)});`));
  check('Mask encoded IDs, letter tokens and phone segments',cleaned.every(p=>p.includes(':id')),cleaned);

  const beacons = []; let fetches=0;
  const storage = new Map();
  const context = {
    URL, Blob, location:{href:'https://audit.example/page',pathname:'/page'},
    document:{currentScript:{src:base+'/counter.js?id=audit_site',dataset:{}},readyState:'complete',referrer:'https://search.example/query'},
    window:{__clickShotBootstrap:{site:'audit_site',collection:{visits:true,pages:true,referrers:true}},ym:(...args)=>args},
    navigator:{sendBeacon:(url,blob)=>{beacons.push(blob);return false;}},
    sessionStorage:{getItem:k=>storage.get(k),setItem:(k,v)=>storage.set(k,v)},
    fetch:()=>{fetches++;return Promise.resolve({ok:true});},
  };
  vm.runInNewContext(readFileSync(join(root,'public/counter.js'),'utf8'),context);
  await context.window.ClickShotMetrika.goal('test_goal');
  const goal = JSON.parse(await beacons[1].text());
  check('Goal carries enabled source context',goal.referrer===context.document.referrer,goal);
  check('sendBeacon false triggers fetch fallback',fetches>0,{beacons:beacons.length,fetches,visitMarked:storage.has('clickshot-metrika:audit_site:started')});
  await context.window.ym(12345, 'reachGoal', 'metrika_goal');
  const bridged = JSON.parse(await beacons.at(-1).text());
  check('Existing Yandex reachGoal is collected without a second call',bridged.event === 'metrika_goal',bridged);
  context.window.__clickShotSuppressYandexGoalBridge = 1;
  const beforeSuppressed = beacons.length;
  await context.window.ym(12345, 'reachGoal', 'cookie_consent_shown');
  check('Slide Cookie Yandex forwarding does not duplicate its event',beacons.length === beforeSuppressed,{before:beforeSuppressed,after:beacons.length});
  context.window.__clickShotSuppressYandexGoalBridge = 0;
  const disabledStorage = new Map();
  context.window.__clickShotBootstrap.collection={visits:false,pages:false,referrers:false};
  context.sessionStorage={getItem:k=>disabledStorage.get(k),setItem:(k,v)=>disabledStorage.set(k,v)};
  vm.runInNewContext(readFileSync(join(root,'public/counter.js'),'utf8'),context);
  await context.window.ClickShotMetrika.goal('disabled_goal');
  check('Disabled visits prevents goal storage writes',disabledStorage.size===0,[...disabledStorage.keys()]);
  context.window.__clickShotBootstrap.collection={visits:true,pages:true,referrers:true};
  context.fetch=()=>Promise.reject(new Error('offline'));
  vm.runInNewContext(readFileSync(join(root,'public/counter.js'),'utf8'),context);
  const delivered=await context.window.ClickShotMetrika.goal('offline_goal');
  check('Failed transports do not mark visits as sent',delivered===false && disabledStorage.size===0,{delivered,markers:disabledStorage.size});
  const assigned = [];
  const assignmentContext = {
    URL, Blob, location:{href:'https://audit.example/page',pathname:'/page'},
    document:{currentScript:{src:base+'/counter.js?id=audit_site',dataset:{}},readyState:'complete',referrer:''},
    window:{__clickShotBootstrap:{site:'audit_site',collection:{visits:false,pages:false,referrers:false}}},
    navigator:{sendBeacon:(url,blob)=>{assigned.push(blob);return true;}}, sessionStorage:{getItem:()=>null,setItem:()=>{}},
    fetch:()=>Promise.resolve({ok:true}), Object,
  };
  vm.runInNewContext(readFileSync(join(root,'public/counter.js'),'utf8'),assignmentContext);
  assignmentContext.window.ym = () => {};
  assignmentContext.window.ym(12345, 'reachGoal', 'assigned_goal');
  await new Promise(resolve => setTimeout(resolve, 0));
  const assignedEvents = await Promise.all(assigned.map(blob => blob.text().then(text => JSON.parse(text))));
  check('Yandex goal immediately after ym assignment is collected',assignedEvents.some(event => event.event === 'assigned_goal'),assignedEvents);
  const elements = new Map();
  const element = key => {
    if (!elements.has(key)) elements.set(key,{value:'',style:{},textContent:'',innerHTML:''});
    return elements.get(key);
  };
  element('#site').value='site_a'; element('#days').value='30';
  const pending=[];
  const ui = {
    document:{querySelector:element}, currentGroup:'day',currentMetric:'visits',selectedSources:null,
    Date, console, encodeURIComponent,fmt:String,fmtPercent:String,esc:String,
    applyCollectionVisibility:()=>{},syncSourceFilter:()=>{},
    fetch:url=>new Promise(resolve=>pending.push({url,resolve}))
  };
  const dashboard = readFileSync(join(root,'public/index.php'),'utf8');
  const loadStats = dashboard.slice(dashboard.indexOf('async function loadStats(){'),dashboard.indexOf("document.querySelectorAll('.btn-group')"));
  vm.createContext(ui); vm.runInContext(loadStats,ui);
  const a = ui.loadStats(); element('#site').value='site_b'; const b = ui.loadStats();
  const response = pageviews => ({ok:true,json:async()=>({collection:{},totals:{pageviews,visits:1,depth:pageviews}})});
  pending[1].resolve(response(20)); await b;
  pending[0].resolve(response(10)); await a;
  check('Late response cannot overwrite selected site stats',element('#pageviews').textContent==='20',{selected:element('#site').value,displayed:element('#pageviews').textContent});
  const backup=join(dir,'backup.sqlite');
  execFileSync('php',[join(root,'bin/database.php'),'backup',backup],{env});
  const backupCount=execFileSync('php',['-r',`$pdo=new PDO('sqlite:'.getenv('METRIKA_DB_PATH'));echo $pdo->query('SELECT COUNT(*) FROM sites')->fetchColumn();`],{env:{...env,METRIKA_DB_PATH:backup},encoding:'utf8'}).trim();
  check('WAL-safe backup preserves site count',Number(backupCount)===sql('SELECT COUNT(*) n FROM sites')[0].n,Number(backupCount));
  execFileSync('php',[join(root,'bin/database.php'),'migrate'],{env});
  check('Repeated migration preserves data',sql('SELECT COUNT(*) n FROM sites')[0].n===Number(backupCount),Number(backupCount));
  console.log(`Summary: ${results.filter(r=>!r.ok).length} failed contracts / ${results.length}; temporary evidence: ${dir}`);
  process.exitCode = results.some(r=>!r.ok) ? 1 : 0;
} finally {
  server.kill('SIGTERM');
}
