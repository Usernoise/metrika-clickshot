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
.collection-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.collection-item{display:flex;align-items:flex-start;gap:10px;padding:12px;border:1px solid #e7e7e7;border-radius:12px;background:#fafafa;cursor:pointer}.collection-item input{margin:3px 0 0;width:16px;height:16px;accent-color:#111}.collection-item strong{display:block;font-size:14px}.collection-item span{display:block;margin-top:3px;color:#737373;font-size:12px;line-height:1.35}.collection-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:14px}.collection-status{font-size:13px;color:#737373}.tech-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.cookie-config{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:12px}.cookie-config label{display:grid;gap:5px;color:#444;font-size:13px}.cookie-config input{width:100%}.cookie-note{margin-top:12px;padding:10px 12px;border-left:3px solid #111;background:#f7f7f7;color:#555;font-size:13px;line-height:1.45}.cookie-config[hidden]{display:none}
@media(max-width:760px){.wrap{padding:18px}header{align-items:flex-start;flex-direction:column}.cards,.grid,.tech-grid,.collection-grid,.cookie-config{grid-template-columns:1fr}.site-form{grid-template-columns:1fr}.value{font-size:27px}.collection-footer{align-items:flex-start;flex-direction:column}}
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

<section class="panel" id="collection-panel">
  <div class="panel-header"><div><h2>Состав собираемой статистики</h2><div class="muted" style="margin-top:4px">Настройки применяются к новым событиям. Сырые IP и User-Agent не сохраняются.</div></div></div>
  <div class="collection-grid">
    <label class="collection-item"><input type="checkbox" id="collect-pageviews"><span><strong>Просмотры и динамика</strong>Общий дневной и почасовой счётчик без данных о пользователе.</span></label>
    <label class="collection-item"><input type="checkbox" id="collect-pages"><span><strong>Страницы</strong>Шаблон пути без query-параметров; распознаваемые ID маскируются.</span></label>
    <label class="collection-item"><input type="checkbox" id="collect-referrers"><span><strong>Источники</strong>Только домен перехода, без полного URL и параметров.</span></label>
    <label class="collection-item"><input type="checkbox" id="collect-visits"><span><strong>Приблизительные визиты</strong>Маркер вкладки в sessionStorage; без постоянных cookie.</span></label>
    <label class="collection-item"><input type="checkbox" id="collect-tech"><span><strong>Технические категории</strong>Только браузер, ОС и тип устройства — без полной строки User-Agent.</span></label>
  </div>
  <div class="collection-footer"><span class="collection-status" id="collection-status"></span><button id="save-collection">Сохранить настройки</button></div>
</section>

<section class="panel" id="slide-cookie-panel">
  <div class="panel-header"><div><h2>Slide Cookie</h2><div class="muted" style="margin-top:4px">Баннер согласия со слайдером и опциональной блокировкой Яндекс.Метрики.</div></div></div>
  <label class="collection-item"><input type="checkbox" id="slide-cookie-enabled"><span><strong>Включить Slide Cookie</strong>Подключается одним сниппетом вместе со счётчиком.</span></label>
  <div class="cookie-config" id="slide-cookie-config" hidden>
    <label>Ссылка на политику<input id="slide-policy-url" type="url" placeholder="/privacy или https://site.ru/privacy"></label>
    <label>ID Яндекс.Метрики <span class="muted">необязательно</span><input id="slide-ym-counter" inputmode="numeric" placeholder="12345678"></label>
    <label>Параметр условия показа<input id="slide-param" placeholder="always"></label>
    <label>Значение параметра<input id="slide-key" placeholder="пусто для always"></label>
    <label>Акцент<input id="slide-accent-color" type="color" value="#C5FF1A"></label>
    <label>Тёмный цвет<input id="slide-dark-color" type="color" value="#0A0A0A"></label>
    <label>Цвет иконки<input id="slide-accent-text-color" type="color" value="#C5FF1A"></label>
    <label class="collection-item"><input type="checkbox" id="slide-block-metrika"><span><strong>Блокировать Яндекс.Метрику</strong>До подтверждения согласия.</span></label>
    <label class="collection-item"><input type="checkbox" id="slide-reset-consent"><span><strong>Запросить согласие заново</strong>Создаёт новую версию ключа согласия.</span></label>
  </div>
  <div class="cookie-note">При включении вставьте обновлённый код в <code>&lt;head&gt;</code> без <code>async</code>: только так баннер успеет остановить Яндекс.Метрику.</div>
  <div class="collection-footer"><span class="collection-status" id="slide-cookie-status"></span><button id="save-slide-cookie">Сохранить Slide Cookie</button></div>
</section>

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

<div class="tech-grid" id="tech-panels" hidden>
<section class="panel"><h2>Браузеры</h2><table><thead><tr><th>Браузер</th><th class="num">Просмотры</th></tr></thead><tbody id="browsers"></tbody></table></section>
<section class="panel"><h2>Операционные системы</h2><table><thead><tr><th>ОС</th><th class="num">Просмотры</th></tr></thead><tbody id="operating-systems"></tbody></table></section>
<section class="panel"><h2>Устройства</h2><table><thead><tr><th>Тип</th><th class="num">Просмотры</th></tr></thead><tbody id="devices"></tbody></table></section>
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
let sitesById = {};

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
    sitesById = Object.fromEntries(sites.map(s => [s.id, s]));
    
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
  renderCollectionSettings();
  renderSlideCookieSettings();
  updateSnippet();
}

function renderCollectionSettings(){
  const site = sitesById[document.querySelector('#site').value];
  const collection = site && site.collection ? site.collection : {pageviews:true, pages:true, referrers:true, visits:true, tech:false};
  document.querySelector('#collect-pageviews').checked = !!collection.pageviews;
  document.querySelector('#collect-pages').checked = !!collection.pages;
  document.querySelector('#collect-referrers').checked = !!collection.referrers;
  document.querySelector('#collect-visits').checked = !!collection.visits;
  document.querySelector('#collect-tech').checked = !!collection.tech;
  document.querySelector('#collection-status').textContent = site
    ? 'Включённые категории сохраняются только в виде агрегатов.'
    : 'Сначала выберите сайт.';
}

function updateSnippet(){
  const select = document.querySelector('#site');
  const id = (select && select.value) ? select.value : 'SITE_ID';
  const cookieEnabled = !!currentSlideCookie().enabled;
  document.querySelector('#snippet').textContent = '<script src="https://metrika.clickshot.ru/counter.js?id=' + id + '"' + (cookieEnabled ? '' : ' async') + '><\/script>';
}

function currentSlideCookie(){
  const site = sitesById[document.querySelector('#site').value];
  return site && site.slide_cookie ? site.slide_cookie : {enabled:false, policy_url:'', param:'always', key:'', block_metrika:true, ym_counter:'', accent_color:'#C5FF1A', dark_color:'#0A0A0A', accent_text_color:'#C5FF1A', version:1};
}

function renderSlideCookieSettings(){
  const c = currentSlideCookie();
  document.querySelector('#slide-cookie-enabled').checked = !!c.enabled;
  document.querySelector('#slide-policy-url').value = c.policy_url || '';
  document.querySelector('#slide-param').value = c.param || 'always';
  document.querySelector('#slide-key').value = c.key || '';
  document.querySelector('#slide-ym-counter').value = c.ym_counter || '';
  document.querySelector('#slide-accent-color').value = c.accent_color || '#C5FF1A';
  document.querySelector('#slide-dark-color').value = c.dark_color || '#0A0A0A';
  document.querySelector('#slide-accent-text-color').value = c.accent_text_color || '#C5FF1A';
  document.querySelector('#slide-block-metrika').checked = !!c.block_metrika;
  document.querySelector('#slide-reset-consent').checked = false;
  document.querySelector('#slide-cookie-config').hidden = !c.enabled;
  document.querySelector('#slide-cookie-status').textContent = c.enabled ? 'Версия согласия: ' + (c.version || 1) : 'Slide Cookie выключен.';
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

    const techEnabled = !!(d.collection && d.collection.tech);
    document.querySelector('#tech-panels').hidden = !techEnabled;
    const techRows = (rows) => (rows || []).map(x =>
      '<tr><td>' + esc(x.label) + '</td><td class="num">' + fmt(x.pageviews) + '</td></tr>'
    ).join('');
    document.querySelector('#browsers').innerHTML = techRows(d.tech && d.tech.browsers);
    document.querySelector('#operating-systems').innerHTML = techRows(d.tech && d.tech.os);
    document.querySelector('#devices').innerHTML = techRows(d.tech && d.tech.devices);
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
  renderCollectionSettings();
  renderSlideCookieSettings();
  updateSnippet();
  loadStats();
});

document.querySelector('#slide-cookie-enabled').addEventListener('change', (e) => {
  document.querySelector('#slide-cookie-config').hidden = !e.target.checked;
});

document.querySelector('#save-slide-cookie').addEventListener('click', async () => {
  const site = document.querySelector('#site').value;
  if(!site) return;
  const button = document.querySelector('#save-slide-cookie');
  const status = document.querySelector('#slide-cookie-status');
  const slide_cookie = {
    enabled: document.querySelector('#slide-cookie-enabled').checked,
    policy_url: document.querySelector('#slide-policy-url').value.trim(),
    param: document.querySelector('#slide-param').value.trim() || 'always',
    key: document.querySelector('#slide-key').value.trim(),
    ym_counter: document.querySelector('#slide-ym-counter').value.trim(),
    block_metrika: document.querySelector('#slide-block-metrika').checked,
    accent_color: document.querySelector('#slide-accent-color').value,
    dark_color: document.querySelector('#slide-dark-color').value,
    accent_text_color: document.querySelector('#slide-accent-text-color').value,
    reset_consent: document.querySelector('#slide-reset-consent').checked
  };
  button.disabled = true; status.textContent = 'Сохраняем…';
  try {
    const r = await fetch('/api/sites.php', {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id:site, slide_cookie})});
    const d = await r.json();
    if(!r.ok || !d.ok) throw new Error(d.error || 'Не удалось сохранить Slide Cookie');
    sitesById[site].slide_cookie = d.slide_cookie;
    renderSlideCookieSettings(); updateSnippet();
    status.textContent = 'Сохранено. Скопируйте обновлённый код подключения.';
  } catch(e) { status.textContent = e.message || 'Не удалось сохранить Slide Cookie'; }
  finally { button.disabled = false; }
});

document.querySelector('#save-collection').addEventListener('click', async () => {
  const site = document.querySelector('#site').value;
  if(!site) return;
  const button = document.querySelector('#save-collection');
  const status = document.querySelector('#collection-status');
  const collection = {
    pageviews: document.querySelector('#collect-pageviews').checked,
    pages: document.querySelector('#collect-pages').checked,
    referrers: document.querySelector('#collect-referrers').checked,
    visits: document.querySelector('#collect-visits').checked,
    tech: document.querySelector('#collect-tech').checked
  };
  button.disabled = true;
  status.textContent = 'Сохраняем…';
  try {
    const r = await fetch('/api/sites.php', {
      method: 'PUT', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({id: site, collection})
    });
    const d = await r.json();
    if(!r.ok || !d.ok) throw new Error(d.error || 'Не удалось сохранить настройки');
    sitesById[site].collection = d.collection;
    status.textContent = 'Сохранено. Новые события будут собираться по этим правилам.';
    loadStats();
  } catch(e) {
    status.textContent = e.message || 'Не удалось сохранить настройки';
  } finally {
    button.disabled = false;
  }
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
