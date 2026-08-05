<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ClickShot Metrika</title>
<style>
:root{font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f5f5f5;color:#171717}
*{box-sizing:border-box}body{margin:0}.wrap{max-width:1180px;margin:auto;padding:28px}
header{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;margin-bottom:24px}
.brand{display:flex;align-items:center;gap:12px}.logo{width:42px;height:42px;border-radius:12px;background:#111;color:#fff;display:grid;place-items:center;font-weight:800}
h1{margin:0 0 4px;font-size:28px}.muted{color:#737373;font-size:14px}
.controls{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
#site{min-width:180px;font-weight:600}
select,button,input{border:1px solid #ddd;border-radius:10px;background:#fff;padding:9px 12px;font:inherit}
button{cursor:pointer;background:#111;color:#fff;border-color:#111;transition:all .15s ease}button:hover{opacity:.9}
.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px}
.card,.panel{background:#fff;border:1px solid #e7e7e7;border-radius:16px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
.card{padding:20px}.label{color:#777;font-size:13px}.value{margin-top:7px;font-size:32px;font-weight:750}
.panel{padding:20px;margin-bottom:16px}.panel-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.panel h2{margin:0;font-size:17px}
.chart{height:230px;display:flex;align-items:flex-end;gap:5px;border-bottom:1px solid #ddd}
.bar-wrap{flex:1;height:100%;min-width:3px;display:flex;align-items:flex-end;position:relative}
.bar{width:100%;min-height:1px;background:#222;border-radius:4px 4px 0 0}
.bar-wrap:hover:after{content:attr(data-tip);position:absolute;left:50%;bottom:calc(100% + 7px);transform:translateX(-50%);padding:6px 8px;border-radius:7px;background:#111;color:#fff;white-space:nowrap;font-size:12px;z-index:5}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}table{width:100%;border-collapse:collapse;font-size:14px}
th,td{padding:10px 8px;border-bottom:1px solid #eee;text-align:left}th{color:#777;font-size:12px;font-weight:600}
th.num,td.num{text-align:right}.error{display:none;padding:14px;margin-bottom:16px;border-radius:12px;background:#fff0f0;border:1px solid #efcaca}
.site-form{display:grid;grid-template-columns:1fr 2fr auto;gap:8px}
.snippet-wrap{display:flex;flex-direction:column;gap:10px}
.snippet{background:#111;color:#fff;padding:14px;border-radius:10px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;overflow:auto;white-space:pre-wrap;word-break:break-all}
.snippet-actions{display:flex;justify-content:flex-end}
.btn-copy{background:#222;color:#fff;font-size:13px;padding:8px 16px;border-radius:8px;border:none}
.btn-copy.copied{background:#16a34a}
.btn-delete{background:#ef4444;color:#fff;border-color:#ef4444;margin-left:4px}.btn-delete:hover{background:#dc2626}
.btn-group{background:#f5f5f5;color:#111;border:1px solid #ddd;padding:5px 10px;font-size:13px}
.btn-group.active{background:#111;color:#fff;border-color:#111}
@media(max-width:760px){.wrap{padding:18px}header{align-items:flex-start;flex-direction:column}.cards,.grid{grid-template-columns:1fr}.site-form{grid-template-columns:1fr}.value{font-size:27px}}
</style>
</head>
<body>
<div class="wrap">
<header>
  <div class="brand">
    <div class="logo">CS</div>
    <div>
      <h1>ClickShot Metrika</h1>
      <div class="muted">Агрегированная статистика без пользовательских идентификаторов</div>
    </div>
  </div>
  <div class="controls">
    <select id="site"></select>
    <select id="days">
      <option value="7">7 дней</option>
      <option value="30" selected>30 дней</option>
      <option value="90">90 дней</option>
      <option value="365">365 дней</option>
      <option value="custom">Свой период...</option>
    </select>
    <div id="custom-range" style="display:none;align-items:center;gap:6px">
      <input type="date" id="date-from" title="С">
      <span class="muted">—</span>
      <input type="date" id="date-to" title="По">
    </div>
    <button id="reload" style="background:#fff;color:#111;border-color:#ddd">Обновить</button>
    <button id="delete-site" class="btn-delete" title="Удалить выбранный сайт">Удалить сайт</button>
  </div>
</header>

<div class="error" id="error"></div>

<section class="cards">
  <div class="card"><div class="label">Просмотры</div><div class="value" id="pageviews">0</div></div>
  <div class="card"><div class="label">Приблизительные визиты</div><div class="value" id="visits">0</div></div>
  <div class="card"><div class="label">Страниц за визит</div><div class="value" id="depth">0</div></div>
</section>

<section class="panel">
  <div class="panel-header">
    <h2>Динамика просмотров</h2>
    <div style="display:flex;gap:4px;">
      <button class="btn-group active" data-group="day">По дням</button>
      <button class="btn-group" data-group="week">По неделям</button>
      <button class="btn-group" data-group="month">По месяцам</button>
    </div>
  </div>
  <div class="chart" id="chart"></div>
</section>

<div class="grid">
<section class="panel">
  <h2>Популярные страницы</h2>
  <table><thead><tr><th>Страница</th><th class="num">Просмотры</th><th class="num">Визиты</th></tr></thead><tbody id="pages"></tbody></table>
</section>
<section class="panel">
  <h2>Источники</h2>
  <table><thead><tr><th>Источник</th><th class="num">Просмотры</th><th class="num">Визиты</th></tr></thead><tbody id="referrers"></tbody></table>
</section>
</div>

<section class="panel">
  <h2>Добавить новый сайт</h2>
  <div class="site-form">
    <input id="new-name" placeholder="Название сайта (напр. Мой Блог)">
    <input id="new-domains" placeholder="Домены через запятую (напр. blog.ru, www.blog.ru)">
    <button id="add-site">Добавить сайт</button>
  </div>
</section>

<section class="panel">
  <h2>Код подключения для выбранного сайта</h2>
  <div class="snippet-wrap">
    <div class="snippet" id="snippet"></div>
    <div class="snippet-actions">
      <button class="btn-copy" id="copy-btn">Скопировать код</button>
    </div>
  </div>
</section>
</div>

<script>
let currentGroup = 'day';

function fmt(v){
  return new Intl.NumberFormat("ru-RU").format(Number(v||0));
}

function esc(v){
  if(v === null || v === undefined) return '';
  return String(v)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

async function loadSites(){
  try {
    const r = await fetch('/api/sites.php', {cache: 'no-store'});
    const d = await r.json();
    const select = document.querySelector('#site');
    const previousValue = select.value;
    
    const sites = (d && d.sites) ? d.sites.filter(s => s.is_active) : [];
    
    select.innerHTML = sites.map(s => {
      const label = s.name ? esc(s.name) : esc(s.id);
      return '<option value="' + esc(s.id) + '">' + label + '</option>';
    }).join('');
    
    if(previousValue && sites.some(s => s.id === previousValue)){
      select.value = previousValue;
    } else if(sites.length > 0) {
      select.value = sites[0].id;
    }
  } catch(e) {
    console.error('Ошибка загрузки сайтов:', e);
  }
  updateSnippet();
}

function updateSnippet(){
  const select = document.querySelector('#site');
  const id = (select && select.value) ? select.value : 'SITE_ID';
  document.querySelector('#snippet').textContent = '<script src="https://metrika.clickshot.ru/counter.js?id=' + id + '" async><\/script>';
}

function toISO(d){
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return y + '-' + m + '-' + day;
}

function initCustomDatesIfNeeded(){
  const fromEl = document.querySelector('#date-from');
  const toEl = document.querySelector('#date-to');
  if(!toEl.value){
    toEl.value = toISO(new Date());
  }
  if(!fromEl.value){
    const d = new Date();
    d.setDate(d.getDate() - 29);
    fromEl.value = toISO(d);
  }
}

async function loadStats(){
  const error = document.querySelector('#error');
  error.style.display = 'none';
  const site = document.querySelector('#site').value;
  const daysSelect = document.querySelector('#days');
  const daysVal = daysSelect.value;

  if(!site){
    document.querySelector('#pageviews').textContent='0';
    document.querySelector('#visits').textContent='0';
    document.querySelector('#depth').textContent='0';
    document.querySelector('#chart').innerHTML='';
    document.querySelector('#pages').innerHTML='';
    document.querySelector('#referrers').innerHTML='';
    return;
  }

  let url = '/api/stats.php?site=' + encodeURIComponent(site) + '&group=' + currentGroup + '&t=' + Date.now();
  if(daysVal === 'custom'){
    initCustomDatesIfNeeded();
    const from = document.querySelector('#date-from').value;
    const to = document.querySelector('#date-to').value;
    if(from) url += '&from=' + encodeURIComponent(from);
    if(to) url += '&to=' + encodeURIComponent(to);
  } else {
    url += '&days=' + encodeURIComponent(daysVal);
  }

  try{
    const r = await fetch(url, {cache:'no-store'});
    if(!r.ok) throw new Error('HTTP ' + r.status);
    const d = await r.json();
    document.querySelector('#pageviews').textContent = fmt(d.totals.pageviews);
    document.querySelector('#visits').textContent = fmt(d.totals.visits);
    document.querySelector('#depth').textContent = String(d.totals.depth).replace('.',',');
    
    const max = Math.max(1, ...(d.daily || []).map(x => x.pageviews));
    
    document.querySelector('#chart').innerHTML = (d.daily || []).map(x => 
      '<div class="bar-wrap" data-tip="' + esc(x.label) + ': ' + fmt(x.pageviews) + '"><div class="bar" style="height:' + Math.max(1, Math.round(x.pageviews/max*100)) + '%"></div></div>'
    ).join('');
    
    document.querySelector('#pages').innerHTML = (d.pages || []).map(x => 
      '<tr><td>' + esc(x.path) + '</td><td class="num">' + fmt(x.pageviews) + '</td><td class="num">' + fmt(x.visits) + '</td></tr>'
    ).join('');
    
    document.querySelector('#referrers').innerHTML = (d.referrers || []).map(x => 
      '<tr><td>' + esc(x.referrer) + '</td><td class="num">' + fmt(x.pageviews) + '</td><td class="num">' + fmt(x.visits) + '</td></tr>'
    ).join('');
  } catch(e) {
    console.error('Ошибка загрузки статистики:', e);
    error.textContent = 'Не удалось загрузить статистику';
    error.style.display = 'block';
  }
}

document.querySelectorAll('.btn-group').forEach(btn => {
  btn.addEventListener('click', (e) => {
    document.querySelectorAll('.btn-group').forEach(b => b.classList.remove('active'));
    e.target.classList.add('active');
    currentGroup = e.target.dataset.group;
    loadStats();
  });
});

document.querySelector('#site').addEventListener('change', () => {
  updateSnippet();
  loadStats();
});

const daysSelect = document.querySelector('#days');
const customRange = document.querySelector('#custom-range');

daysSelect.addEventListener('change', () => {
  if(daysSelect.value === 'custom'){
    customRange.style.display = 'inline-flex';
    initCustomDatesIfNeeded();
  } else {
    customRange.style.display = 'none';
  }
  loadStats();
});

document.querySelector('#date-from').addEventListener('change', () => {
  if(daysSelect.value === 'custom') loadStats();
});

document.querySelector('#date-to').addEventListener('change', () => {
  if(daysSelect.value === 'custom') loadStats();
});

document.querySelector('#reload').addEventListener('click', loadStats);

document.querySelector('#delete-site').addEventListener('click', async () => {
  const select = document.querySelector('#site');
  const siteId = select.value;
  const siteName = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : siteId;
  if(!siteId) return;
  
  if(!confirm('Вы действительно хотите удалить сайт "' + siteName + '" и всю его статистику?')) return;

  const r = await fetch('/api/sites.php', {
    method: 'DELETE',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({id: siteId})
  });
  const res = await r.json();
  if(r.ok && res.ok){
    await loadSites();
    loadStats();
  } else {
    alert(res.error || 'Не удалось удалить сайт');
  }
});

document.querySelector('#copy-btn').addEventListener('click', async () => {
  const code = document.querySelector('#snippet').textContent;
  if(!code) return;
  try {
    await navigator.clipboard.writeText(code);
    const btn = document.querySelector('#copy-btn');
    btn.textContent = 'Скопировано!';
    btn.classList.add('copied');
    setTimeout(() => {
      btn.textContent = 'Скопировать код';
      btn.classList.remove('copied');
    }, 2000);
  } catch(e) {
    alert('Не удалось скопировать код');
  }
});

document.querySelector('#add-site').addEventListener('click', async () => {
  const nameVal = document.querySelector('#new-name').value.trim();
  const domainsVal = document.querySelector('#new-domains').value.split(',').map(x => x.trim()).filter(Boolean);
  
  if(!nameVal || !domainsVal.length){
    alert('Укажите название и хотя бы один домен');
    return;
  }
  
  const payload = { name: nameVal, domains: domainsVal };
  const r = await fetch('/api/sites.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(payload)
  });
  const res = await r.json();
  if(r.ok && res.ok){
    document.querySelector('#new-name').value = '';
    document.querySelector('#new-domains').value = '';
    await loadSites();
    if(res.id) document.querySelector('#site').value = res.id;
    updateSnippet();
    loadStats();
  } else {
    alert(res.error || 'Не удалось добавить сайт');
  }
});

(async () => {
  await loadSites();
  await loadStats();
})();
</script>
</body>
</html>
