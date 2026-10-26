import {mkdtempSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join, resolve} from 'node:path';
import {execFile, execFileSync} from 'node:child_process';
import {promisify} from 'node:util';
import assert from 'node:assert/strict';
const root=resolve(import.meta.dirname,'..');
const dir=mkdtempSync(join(tmpdir(),'clickshot-migration-'));
const env={...process.env,METRIKA_DB_PATH:join(dir,'legacy.sqlite')};
execFileSync('php',['-r',`
$pdo=new PDO('sqlite:'.getenv('METRIKA_DB_PATH'));
$pdo->exec(<<<'SQL'
CREATE TABLE sites(id TEXT PRIMARY KEY,name TEXT NOT NULL,domains_json TEXT NOT NULL,created_at TEXT NOT NULL,is_active INTEGER NOT NULL DEFAULT 1);
CREATE TABLE site_collection_settings(site_id TEXT PRIMARY KEY,collect_pages INTEGER NOT NULL DEFAULT 1,collect_referrers INTEGER NOT NULL DEFAULT 1,collect_visits INTEGER NOT NULL DEFAULT 1,collect_tech INTEGER NOT NULL DEFAULT 0,updated_at TEXT NOT NULL);
CREATE TABLE daily_stats(site_id TEXT NOT NULL,stat_date TEXT NOT NULL,pageviews INTEGER NOT NULL DEFAULT 0,visits INTEGER NOT NULL DEFAULT 0,PRIMARY KEY(site_id,stat_date));
INSERT INTO sites VALUES('legacy','Legacy','[\"legacy.example\"]','2026-01-01',1);
INSERT INTO site_collection_settings VALUES('legacy',0,1,0,1,'2026-01-01');
INSERT INTO daily_stats VALUES('legacy','2026-01-01',42,7)
SQL);
`],{env});
await Promise.all([1,2].map(()=>promisify(execFile)('php',[join(root,'bin/database.php'),'migrate'],{env})));
const data=JSON.parse(execFileSync('php',['-r',`require ${JSON.stringify(join(root,'lib.php'))};
echo json_encode(['sites'=>count(all_sites()),'collection'=>site_by_id('legacy')['collection'],'totals'=>db()->query('SELECT pageviews,visits FROM daily_stats')->fetch()]);`],{env,encoding:'utf8'}));
assert.equal(data.sites,1);
assert.deepEqual(data.collection,{pageviews:true,pages:false,referrers:true,visits:false,tech:true});
assert.deepEqual(data.totals,{pageviews:42,visits:7});
// Model the immediately previous v2 schema: same aggregates, no consent tables.
execFileSync('php',['-r',`require ${JSON.stringify(join(root,'lib.php'))};
foreach (['site_consent_settings','consent_documents','consent_receipts','consent_budget'] as $table) db()->exec('DROP TABLE '.$table);
db()->exec('PRAGMA user_version=2');`],{env});
execFileSync('php',[join(root,'bin/database.php'),'migrate'],{env});
const upgraded=JSON.parse(execFileSync('php',['-r',`require ${JSON.stringify(join(root,'lib.php'))};echo json_encode(db()->query('SELECT pageviews,visits FROM daily_stats')->fetch());`],{env,encoding:'utf8'}));
assert.deepEqual(upgraded,{pageviews:42,visits:7},'v2 to v3 must preserve existing statistics');
const integrity=execFileSync('php',['-r',`require ${JSON.stringify(join(root,'lib.php'))};
try {db()->exec("INSERT INTO source_event_daily_stats VALUES('missing','2026-01-01','source','goal',1,1)");exit(2);} catch(PDOException $e) {}
db()->exec("DELETE FROM sites WHERE id='legacy'");
foreach(site_data_tables() as $table) if(db()->query("SELECT COUNT(*) FROM ".$table)->fetchColumn()!=0) exit(3);
echo 'ok';`],{env,encoding:'utf8'});
assert.equal(integrity,'ok');
console.log('Legacy/v2 migration, concurrent migration, preserved aggregates and cascading deletion passed');
