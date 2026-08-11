<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ClickShot Metrika</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Geologica:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&family=Onest:wght@400;500;600&display=swap');
:root{--ink:#0a0a0a;--muted:#737373;--line:#e5e5e5;--soft:#f5f5f5;--lime:#c5ff1a;--lime-soft:#f1ffcc;--danger:#b42318;--font:'Onest',sans-serif;--mono:'JetBrains Mono',monospace;--display:'Geologica',sans-serif}*{box-sizing:border-box}body.fc-app{margin:0;background:#fff;color:var(--ink);font:13px/1.5 var(--font)}button,input,select{font:inherit}button{cursor:pointer}.wrap{max-width:1280px;margin:auto;padding:0 32px}.app-nav{height:66px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between}.brand{display:flex;align-items:center;gap:10px;font:600 15px var(--display)}.logo{width:30px;height:30px;background:var(--ink);color:var(--lime);display:grid;place-items:center;border-radius:7px;font:500 10px var(--mono);letter-spacing:-.1em}.nav-tag,.eyebrow,.label{font:500 10px var(--mono);letter-spacing:.09em;text-transform:uppercase}.nav-tag{color:var(--muted)}header{padding:48px 0 26px;display:flex;align-items:flex-end;justify-content:space-between;gap:24px;background-image:radial-gradient(#e5e5e5 .75px,transparent .75px);background-size:14px 14px;background-position:0 14px}h1,h2{font-family:var(--display);letter-spacing:-.035em}h1{font-size:38px;line-height:1;margin:8px 0 10px}.eyebrow{display:flex;align-items:center;gap:8px;color:#404040}.eyebrow:before{content:'';width:6px;height:6px;border-radius:50%;background:var(--lime);box-shadow:0 0 0 3px rgba(197,255,26,.35)}.muted{color:var(--muted);font-size:13px}.controls{display:flex;align-items:center;justify-content:flex-end;gap:7px;flex-wrap:wrap}.fc-input,select,input{border:1px solid #d4d4d4;border-radius:6px;background:#fff;padding:9px 10px;outline:none;min-height:36px}.fc-input:focus,select:focus,input:focus{border-color:var(--lime);box-shadow:0 0 0 3px rgba(197,255,26,.28)}#site{min-width:190px;font-weight:600}.fc-btn{border-radius:6px;padding:9px 12px;border:1px solid transparent;font-weight:500;min-height:36px;transition:.18s ease}.fc-btn:hover{transform:translateY(-1px)}.fc-btn-primary{background:var(--lime);border-color:var(--lime);color:#181b25}.fc-btn-outline{background:#fff;border-color:var(--line);color:#404040}.btn-delete{color:var(--danger);border-color:#f0c7c3;background:#fff}.main{padding:0 0 64px}.error{display:none;margin:0 0 16px;padding:11px 13px;border:1px solid #f0c7c3;border-radius:8px;background:#fff3f2;color:var(--danger);font:12px var(--mono)}.cards{display:grid;grid-template-columns:repeat(3,1fr);border:1px solid var(--line);border-radius:12px;overflow:hidden;margin-bottom:16px}.card{padding:17px 18px;border-right:1px solid var(--line)}.card:last-child{border:0}.label{color:#737373}.value{font:600 30px/1 var(--display);margin-top:10px;letter-spacing:-.04em}.panel{border:1px solid var(--line);border-radius:12px;background:#fff;margin-bottom:16px;min-width:0}.panel-header{display:flex;justify-content:space-between;align-items:center;padding:13px 14px;border-bottom:1px solid var(--line);gap:12px}.panel-header h2,.panel h2{font-size:15px;margin:0}.panel-header .muted{font-size:11px}.chart-panel{padding-bottom:14px}.chart{margin:20px 16px 0;height:198px;display:flex;align-items:flex-end;gap:5px;border-bottom:1px solid #d4d4d4}.bar-wrap{flex:1;min-width:3px;height:100%;display:flex;align-items:flex-end;position:relative}.bar{width:100%;min-height:2px;background:#181b25;border-radius:3px 3px 0 0;transition:background .15s}.bar-wrap:hover .bar{background:var(--lime)}.bar-wrap:hover:after{content:attr(data-tip);position:absolute;left:50%;bottom:calc(100% + 7px);transform:translateX(-50%);padding:5px 7px;background:#181b25;color:#fff;border-radius:4px;white-space:nowrap;font:10px var(--mono);z-index:3}.btn-group{border:0;border-bottom:2px solid transparent;background:transparent;color:#737373;padding:7px 8px;font:10px var(--mono);text-transform:uppercase}.btn-group.active{color:#0a0a0a;border-color:var(--lime)}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.grid .panel{margin:0}.table-panel{overflow:hidden}.table-panel>h2{padding:14px 14px 6px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{padding:9px 14px;border-bottom:1px solid #ededed;text-align:left}tbody tr:last-child td{border-bottom:0}th{font:500 10px var(--mono);letter-spacing:.06em;text-transform:uppercase;color:#737373}th.num,td.num{text-align:right}.tech-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:16px}.collection-panel{padding:16px}.settings-head{padding-bottom:12px;border-bottom:1px dashed var(--line);margin-bottom:12px}.collection-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}.collection-item{border:1px solid var(--line);border-radius:8px;padding:10px;display:flex;gap:8px;cursor:pointer;background:#fff;transition:border-color .15s}.collection-item:hover{border-color:#a3a3a3}.collection-item input{min-height:auto;width:15px;height:15px;margin:2px 0 0;accent-color:#a7dc00}.collection-item strong{display:block;font-size:12px}.collection-item span span{display:block;margin-top:3px;color:#737373;font-size:10px;line-height:1.35}.collection-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px}.collection-status{font-size:11px;color:var(--muted)}.slide-grid{display:grid;grid-template-columns:1.15fr 1fr;gap:16px;padding:16px}.cookie-config{display:grid;grid-template-columns:1fr 1fr;gap:8px}.cookie-config label{display:grid;gap:4px;font:10px var(--mono);text-transform:uppercase;color:#525252}.cookie-config input{width:100%;padding:7px 8px;min-height:32px}.cookie-config .collection-item{font:12px var(--font);text-transform:none}.cookie-config[hidden]{display:none!important}.cookie-note{background:#fafafa;border-left:3px solid var(--lime);padding:10px 11px;color:#525252;font-size:11px}.site-form{display:grid;grid-template-columns:1fr 2fr auto;gap:8px;padding:14px}.snippet-wrap{padding:14px}.snippet{padding:13px;background:#181b25;color:#efffc7;border-radius:7px;font:11px/1.55 var(--mono);word-break:break-all}.snippet-actions{display:flex;justify-content:flex-end;margin-top:9px}.btn-copy{background:var(--lime);border:0;border-radius:6px;padding:8px 11px;color:#181b25;font-weight:500}.btn-copy.copied{background:#c5ff1a}.section-title{display:flex;justify-content:space-between;align-items:center;margin:32px 0 12px}.section-title h2{font-size:18px;margin:0}.section-title span{font:10px var(--mono);color:var(--muted);text-transform:uppercase}.add-panel{margin-top:32px}.add-panel h2,.snippet-panel h2{padding:14px 14px 0}#custom-range{gap:5px;align-items:center}.footnote{text-align:center;color:#737373;font:10px var(--mono);margin-top:24px}[hidden]{display:none!important}@media(max-width:980px){.collection-grid{grid-template-columns:repeat(2,1fr)}.slide-grid{grid-template-columns:1fr}.tech-grid{grid-template-columns:1fr}.wrap{padding:0 20px}}@media(max-width:680px){header{padding-top:34px;align-items:flex-start;flex-direction:column}h1{font-size:31px}.cards,.grid,.site-form{grid-template-columns:1fr}.card{border-right:0;border-bottom:1px solid var(--line)}.card:last-child{border-bottom:0}.controls{justify-content:flex-start}.collection-grid{grid-template-columns:1fr}.collection-footer{align-items:flex-start;flex-direction:column}.cookie-config{grid-template-columns:1fr}.wrap{padding:0 14px}.app-nav{height:56px}}
</style>
<style>
.sc-preview{margin-top:12px;border:1px solid var(--line);border-radius:8px;overflow:hidden;background:#f7f7f7}.sc-preview-head{display:flex;justify-content:space-between;align-items:center;padding:7px 9px;border-bottom:1px solid var(--line);font:10px var(--mono);color:var(--muted);letter-spacing:.06em;text-transform:uppercase}.sc-preview-stage{position:relative;min-height:175px;padding:18px 12px 12px;display:flex;align-items:flex-end;background:radial-gradient(#ddd .7px,transparent .7px);background-size:10px 10px}.sc-banner{width:100%;border-radius:8px;padding:12px;background:var(--sc-dark,#0a0a0a);color:#fff;box-shadow:0 8px 18px rgba(10,10,10,.16);transition:background .2s ease}.sc-banner-top{display:flex;align-items:center;gap:7px;font:500 10px var(--mono);letter-spacing:.06em;text-transform:uppercase}.sc-banner-mark{width:8px;height:8px;border-radius:50%;background:var(--sc-accent,#c5ff1a);box-shadow:0 0 0 3px color-mix(in srgb,var(--sc-accent,#c5ff1a) 22%,transparent)}.sc-banner p{font-size:11px;line-height:1.4;margin:9px 0 11px;color:#e5e5e5}.sc-banner-actions{display:flex;gap:7px}.sc-banner button{border:0;border-radius:4px;padding:6px 8px;font:500 10px var(--font);background:var(--sc-accent,#c5ff1a);color:var(--sc-accent-text,#0a0a0a)}.sc-banner .sc-link{background:transparent;color:#fff;text-decoration:underline;text-underline-offset:2px}.sc-preview.is-off{opacity:.48}.sc-preview.is-off .sc-preview-stage:after{content:'Включите Slide Cookie для показа баннера';position:absolute;font:10px var(--mono);color:var(--muted)}
.site-modal{position:fixed;inset:0;z-index:20;display:grid;place-items:center;padding:20px;background:rgba(10,10,10,.44);backdrop-filter:blur(3px);opacity:0;transition:opacity .2s ease}.site-modal.is-open{opacity:1}.site-modal-card{width:min(100%,460px);padding:22px;border-radius:12px;background:#fff;box-shadow:0 24px 60px rgba(10,10,10,.22);transform:translateY(10px);transition:transform .22s ease}.site-modal.is-open .site-modal-card{transform:translateY(0)}.site-modal-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.site-modal-head h2{font-size:24px;margin:7px 0 0}.site-modal-close{width:28px;height:28px;border:1px solid var(--line);border-radius:6px;background:#fff;color:var(--muted);font-size:21px;line-height:1}.site-modal-card>.muted{margin:14px 0 18px}.modal-field{display:grid;gap:6px;margin:12px 0;font:500 10px var(--mono);letter-spacing:.06em;text-transform:uppercase;color:#404040}.modal-field span{font:400 10px var(--font);letter-spacing:0;text-transform:none;color:var(--muted)}.site-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:20px}
.snippet-actions{align-items:center;gap:8px}.snippet-status{margin-right:auto;font:10px var(--mono);color:var(--muted)}.snippet-status.is-ok{color:#4b6d00}.snippet-status.is-error{color:var(--danger)}
.cards{grid-template-columns:repeat(auto-fit,minmax(0,1fr))}.cards .card:last-child{border-right:0}.collection-item.is-dependent{opacity:.48;cursor:not-allowed}.collection-item.is-dependent input{pointer-events:none}.collection-status.is-warning{color:var(--danger)}
.chart-controls{display:flex;align-items:center;justify-content:flex-end;gap:8px;min-height:30px}.chart-controls>div:last-child{display:flex;align-items:center;height:30px}.metric-switch{display:inline-flex;align-items:center;gap:2px;height:30px;padding:2px;border:1px solid var(--line);border-radius:6px;background:var(--soft)}.metric-tab{display:inline-flex;align-items:center;height:24px;border:0;border-radius:4px;padding:0 8px;background:transparent;color:var(--muted);font:500 10px var(--mono);cursor:pointer}.metric-tab.active{background:#fff;color:var(--ink);box-shadow:0 1px 3px rgba(10,10,10,.08)}.chart-controls .btn-group{display:inline-flex;align-items:center;height:30px;padding:0 9px}
@media(max-width:760px){.chart-controls{flex-wrap:wrap;gap:6px}.panel-header:has(.chart-controls){align-items:flex-start}.chart-controls>div:last-child{margin-left:auto}}@media(max-width:480px){.chart-controls{justify-content:flex-start}.chart-controls>div:last-child{margin-left:0}}
.source-filter{position:relative;font:10px var(--mono);color:var(--muted)}.source-filter summary{display:flex;align-items:center;height:30px;list-style:none;cursor:pointer;border:1px solid var(--line);border-radius:6px;padding:0 9px;background:#fff;color:var(--ink);white-space:nowrap}.source-filter summary::-webkit-details-marker{display:none}.source-filter summary:after{content:'⌄';margin-left:7px;color:var(--muted)}.source-filter[open] summary{border-color:#a3a3a3}.source-options{position:absolute;right:0;top:calc(100% + 5px);z-index:5;min-width:210px;max-height:220px;overflow:auto;padding:7px;background:#fff;border:1px solid var(--line);border-radius:7px;box-shadow:0 10px 24px rgba(10,10,10,.12)}.source-option{display:flex;align-items:center;gap:7px;padding:6px 5px;color:#404040;cursor:pointer}.source-option:hover{background:var(--soft)}.source-option input{width:14px;height:14px;min-height:auto;accent-color:#a7dc00}.source-filter[hidden]{display:none!important}
header h1{margin-top:0}
</style>
</head>
<body class="fc-app">
<div class="wrap">
  <nav class="app-nav"><div class="brand"><div class="logo">CS</div>ClickShot <span class="muted">/ Metrika</span></div><span class="nav-tag">privacy-first analytics</span></nav>
  <header>
    <div><h1>Статистика сайта</h1><div class="muted">Агрегированные данные о трафике — без пользовательских идентификаторов.</div></div>
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
    <button id="add-site-open" class="fc-btn fc-btn-primary">+ Добавить сайт</button>
    <button id="reload" class="fc-btn fc-btn-outline">Обновить</button>
    <button id="delete-site" class="fc-btn btn-delete" title="Удалить выбранный сайт">Удалить</button>
  </div>
</header>

<main class="main">
<div class="error" id="error"></div>

<section class="cards" id="metrics-cards" aria-label="Ключевые метрики">
  <div class="card" id="pageviews-card"><div class="label">Просмотры</div><div class="value" id="pageviews">0</div></div>
  <div class="card" id="visits-card"><div class="label">Приблизительные визиты</div><div class="value" id="visits">0</div></div>
  <div class="card" id="depth-card"><div class="label">Глубина просмотра</div><div class="value" id="depth">0</div></div>
</section>

<section class="panel chart-panel" id="chart-panel">
  <div class="panel-header"><h2>Динамика</h2><div class="chart-controls"><details class="source-filter" id="source-filter"><summary id="source-filter-label">Источники: все</summary><div class="source-options" id="source-options"></div></details><div class="metric-switch" role="tablist" aria-label="Метрика графика"><button class="metric-tab active" data-metric="visits" role="tab" aria-selected="true">Визиты</button><button class="metric-tab" data-metric="pageviews" role="tab" aria-selected="false">Просмотры</button></div><div><button class="btn-group active" data-group="day">Дни</button><button class="btn-group" data-group="week">Недели</button><button class="btn-group" data-group="month">Месяцы</button></div></div></div><div class="chart" id="chart"></div>
</section>

<div class="grid" id="detail-panels">
<section class="panel table-panel" id="pages-panel"><h2>Популярные страницы</h2><table><thead><tr><th>Путь / шаблон</th><th class="num">Просмотры</th><th class="num">Визиты</th></tr></thead><tbody id="pages"></tbody></table></section>
<section class="panel table-panel" id="referrers-panel"><h2>Источники</h2><table><thead><tr><th>Источник</th><th class="num">Просмотры</th><th class="num">Визиты</th></tr></thead><tbody id="referrers"></tbody></table></section>
</div>
<div class="tech-grid" id="tech-panels" hidden>
<section class="panel table-panel"><h2>Браузеры</h2><table><thead><tr><th>Браузер</th><th class="num">Просмотры</th></tr></thead><tbody id="browsers"></tbody></table></section>
<section class="panel table-panel"><h2>Операционные системы</h2><table><thead><tr><th>ОС</th><th class="num">Просмотры</th></tr></thead><tbody id="operating-systems"></tbody></table></section>
<section class="panel table-panel"><h2>Устройства</h2><table><thead><tr><th>Тип</th><th class="num">Просмотры</th></tr></thead><tbody id="devices"></tbody></table></section>
</div>
<section class="panel table-panel events-panel" id="events-panel" hidden><h2>События</h2><table><thead><tr><th>Событие</th><th class="num">Срабатывания</th><th class="num">Визиты</th><th class="num">Конверсия</th></tr></thead><tbody id="events"></tbody></table></section>

<div class="section-title"><h2>Настройки счётчика</h2><span>Применяются к новым событиям</span></div>
<section class="panel collection-panel" id="collection-panel">
  <div class="settings-head"><h2>Состав собираемой статистики</h2><div class="muted">Сырые IP и User-Agent не сохраняются.</div></div>
  <div class="collection-grid">
    <label class="collection-item"><input type="checkbox" id="collect-pageviews"><span><strong>Просмотры и динамика</strong>Общий дневной и почасовой счётчик без данных о пользователе.</span></label>
    <label class="collection-item"><input type="checkbox" id="collect-pages"><span><strong>Страницы</strong>Шаблон пути без query-параметров; распознаваемые ID маскируются.</span></label>
    <label class="collection-item"><input type="checkbox" id="collect-referrers"><span><strong>Источники</strong>Только домен перехода, без полного URL и параметров.</span></label>
    <label class="collection-item"><input type="checkbox" id="collect-visits"><span><strong>Приблизительные визиты</strong>Маркер вкладки в sessionStorage; без постоянных cookie.</span></label>
    <label class="collection-item"><input type="checkbox" id="collect-tech"><span><strong>Технические категории</strong>Только браузер, ОС и тип устройства — без полной строки User-Agent.</span></label>
  </div>
  <div class="collection-footer"><span class="collection-status" id="collection-status"></span><button id="save-collection" class="fc-btn fc-btn-primary">Сохранить настройки</button></div>
</section>

<section class="panel" id="slide-cookie-panel">
  <div class="panel-header"><div><h2>Slide Cookie</h2><div class="muted">Баннер согласия и опциональная блокировка Яндекс.Метрики.</div></div></div>
  <div class="slide-grid"><div><label class="collection-item"><input type="checkbox" id="slide-cookie-enabled"><span><strong>Включить Slide Cookie</strong><span>Подключается одним сниппетом вместе со счётчиком.</span></span></label><div class="cookie-note">При включении вставьте обновлённый код в <code>&lt;head&gt;</code> без <code>async</code>: так баннер успеет остановить Яндекс.Метрику.</div><aside class="sc-preview" id="slide-preview" aria-label="Предпросмотр баннера Slide Cookie"><div class="sc-preview-head"><span>Предпросмотр</span><span id="slide-preview-state">выключен</span></div><div class="sc-preview-stage"><div class="sc-banner"><div class="sc-banner-top"><span class="sc-banner-mark"></span>cookie notice</div><p>Мы используем cookie, чтобы улучшать работу сайта и собирать обезличенную статистику.</p><div class="sc-banner-actions"><button type="button">Принять</button><button type="button" class="sc-link">Подробнее</button></div></div></div></aside></div>
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
  </div></div>
  <div class="collection-footer" style="padding:0 16px 16px"><span class="collection-status" id="slide-cookie-status"></span><button id="save-slide-cookie" class="fc-btn fc-btn-primary">Сохранить Slide Cookie</button></div>
</section>

<section class="panel snippet-panel">
  <h2>Код подключения для выбранного сайта</h2>
  <div class="snippet-wrap">
    <div class="snippet" id="snippet"></div>
    <div class="snippet-actions">
      <span class="snippet-status" id="check-install-status" aria-live="polite"></span>
      <button class="fc-btn fc-btn-outline" id="check-install">Проверить установку</button>
      <button class="btn-copy" id="copy-btn">Скопировать код</button>
    </div>
  </div>
</section>
<p class="footnote">ClickShot Metrika · Агрегированная аналитика без пользовательских идентификаторов</p>
</main>
</div>
<div class="site-modal" id="site-modal" hidden role="dialog" aria-modal="true" aria-labelledby="site-modal-title">
  <form class="site-modal-card" id="site-form">
    <div class="site-modal-head"><div><div class="eyebrow">Новый проект</div><h2 id="site-modal-title">Добавить сайт</h2></div><button type="button" class="site-modal-close" id="site-modal-close" aria-label="Закрыть">×</button></div>
    <p class="muted">Создадим отдельный счётчик. После добавления вы получите готовый код подключения.</p>
    <label class="modal-field">Название сайта<input id="new-name" required placeholder="Например, Мой Блог" autocomplete="off"></label>
    <label class="modal-field">Домены<input id="new-domains" required placeholder="blog.ru, www.blog.ru" autocomplete="off"><span>Несколько доменов укажите через запятую.</span></label>
    <div class="site-modal-actions"><button type="button" class="fc-btn fc-btn-outline" id="site-modal-cancel">Отмена</button><button id="add-site" class="fc-btn fc-btn-primary">Создать сайт</button></div>
  </form>
</div>

<script>
let currentGroup = 'day';
let currentMetric = 'visits';
let selectedSources = null;
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

function syncSourceFilter(referrers, enabled){
  const filter = document.querySelector('#source-filter');
  const options = document.querySelector('#source-options');
  const names = (referrers || []).map(item => item.referrer);
  filter.hidden = !enabled || names.length === 0;
  if(!enabled || names.length === 0) return;
  if(selectedSources === null) selectedSources = new Set(names);
  else selectedSources = new Set(names.filter(name => selectedSources.has(name)));
  options.innerHTML = names.map(name => '<label class="source-option"><input type="checkbox" value="' + esc(name) + '"' + (selectedSources.has(name) ? ' checked' : '') + '>' + esc(name) + '</label>').join('');
  options.querySelectorAll('input').forEach(input => input.addEventListener('change', () => {
    const checked = Array.from(options.querySelectorAll('input:checked')).map(item => item.value);
    selectedSources = checked.length === names.length || checked.length === 0 ? null : new Set(checked);
    document.querySelector('#source-filter-label').textContent = selectedSources === null ? 'Источники: все' : 'Источники: ' + selectedSources.size;
    loadStats();
  }));
  document.querySelector('#source-filter-label').textContent = selectedSources === null || selectedSources.size === names.length ? 'Источники: все' : 'Источники: ' + selectedSources.size;
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

function applyCollectionVisibility(collection){
  const enabled = collection || {pageviews:true, pages:true, referrers:true, visits:true, tech:false};
  document.querySelector('#pageviews-card').hidden = !enabled.pageviews;
  document.querySelector('#visits-card').hidden = !enabled.visits;
  document.querySelector('#depth-card').hidden = !(enabled.pageviews && enabled.visits);
  document.querySelector('#metrics-cards').hidden = !(enabled.pageviews || enabled.visits);
  document.querySelector('#chart-panel').hidden = !enabled.pageviews;
  document.querySelector('#pages-panel').hidden = !enabled.pages;
  document.querySelector('#referrers-panel').hidden = !enabled.referrers;
  document.querySelector('#detail-panels').hidden = !(enabled.pages || enabled.referrers);
  document.querySelector('#tech-panels').hidden = !enabled.tech;
}

function updateSnippet(){
  const select = document.querySelector('#site');
  const id = (select && select.value) ? select.value : 'SITE_ID';
  const cookieToggle = document.querySelector('#slide-cookie-enabled');
  const cookieEnabled = cookieToggle ? cookieToggle.checked : !!currentSlideCookie().enabled;
  document.querySelector('#snippet').textContent = '<script src="https://metrika.clickshot.ru/counter.js?id=' + id + '"' + (cookieEnabled ? '' : ' async') + '><\/script>';
}

function currentSlideCookie(){
  const site = sitesById[document.querySelector('#site').value];
  return site && site.slide_cookie ? site.slide_cookie : {enabled:false, policy_url:'', param:'always', key:'', block_metrika:true, ym_counter:'', accent_color:'#C5FF1A', dark_color:'#0A0A0A', accent_text_color:'#C5FF1A', version:1};
}

function updateSlidePreview(){
  const preview = document.querySelector('#slide-preview');
  if(!preview) return;
  const enabled = document.querySelector('#slide-cookie-enabled').checked;
  preview.classList.toggle('is-off', !enabled);
  preview.style.setProperty('--sc-accent', document.querySelector('#slide-accent-color').value || '#C5FF1A');
  preview.style.setProperty('--sc-dark', document.querySelector('#slide-dark-color').value || '#0A0A0A');
  preview.style.setProperty('--sc-accent-text', document.querySelector('#slide-accent-text-color').value || '#C5FF1A');
  document.querySelector('#slide-preview-state').textContent = enabled ? 'как на сайте' : 'выключен';
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
  updateSlidePreview();
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
  if(selectedSources && selectedSources.size) url += '&sources=' + encodeURIComponent(Array.from(selectedSources).join(','));

  try{
    const r = await fetch(url, {cache:'no-store'});
    if(!r.ok) throw new Error('HTTP ' + r.status);
    const d = await r.json();
    applyCollectionVisibility(d.collection);
    syncSourceFilter(d.referrers, !!(d.collection && d.collection.referrers));
    document.querySelector('#pageviews').textContent = fmt(d.totals.pageviews);
    document.querySelector('#visits').textContent = fmt(d.totals.visits);
    document.querySelector('#depth').textContent = String(d.totals.depth).replace('.',',');
    
    const max = Math.max(1, ...(d.daily || []).map(x => Number(x[currentMetric] || 0)));
    
    document.querySelector('#chart').innerHTML = (d.daily || []).map(x => 
      '<div class="bar-wrap" data-tip="' + esc(x.label) + ': ' + fmt(x[currentMetric]) + '"><div class="bar" style="height:' + Math.max(1, Math.round(Number(x[currentMetric] || 0)/max*100)) + '%"></div></div>'
    ).join('');
    
    document.querySelector('#pages').innerHTML = (d.pages || []).map(x => 
      '<tr><td>' + esc(x.path) + '</td><td class="num">' + fmt(x.pageviews) + '</td><td class="num">' + fmt(x.visits) + '</td></tr>'
    ).join('');
    
    document.querySelector('#referrers').innerHTML = (d.referrers || []).map(x => 
      '<tr><td>' + esc(x.referrer) + '</td><td class="num">' + fmt(x.pageviews) + '</td><td class="num">' + fmt(x.visits) + '</td></tr>'
    ).join('');

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

document.querySelectorAll('.metric-tab').forEach(btn => {
  btn.addEventListener('click', (e) => {
    document.querySelectorAll('.metric-tab').forEach(tab => {
      tab.classList.remove('active');
      tab.setAttribute('aria-selected', 'false');
    });
    e.currentTarget.classList.add('active');
    e.currentTarget.setAttribute('aria-selected', 'true');
    currentMetric = e.currentTarget.dataset.metric;
    loadStats();
  });
});

document.querySelector('#site').addEventListener('change', () => {
  selectedSources = null;
  renderCollectionSettings();
  renderSlideCookieSettings();
  updateSnippet();
  const checkStatus = document.querySelector('#check-install-status');
  checkStatus.textContent = '';
  checkStatus.className = 'snippet-status';
  loadStats();
});

document.querySelector('#slide-cookie-enabled').addEventListener('change', (e) => {
  document.querySelector('#slide-cookie-config').hidden = !e.target.checked;
  updateSlidePreview();
  updateSnippet();
});

['#slide-accent-color', '#slide-dark-color', '#slide-accent-text-color'].forEach(selector => {
  document.querySelector(selector).addEventListener('input', updateSlidePreview);
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

document.querySelector('#check-install').addEventListener('click', async () => {
  const siteId = document.querySelector('#site').value;
  const button = document.querySelector('#check-install');
  const status = document.querySelector('#check-install-status');
  if (!siteId) return;
  button.disabled = true;
  status.className = 'snippet-status';
  status.textContent = 'Проверяем главную страницу…';
  try {
    const response = await fetch('/api/check-install.php?site=' + encodeURIComponent(siteId), {cache: 'no-store'});
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.error || 'Не удалось проверить установку');
    const found = result.checks.filter(item => item.status === 'installed').map(item => item.domain);
    if (found.length) {
      status.className = 'snippet-status is-ok';
      status.textContent = 'Код найден: ' + found.join(', ');
    } else {
      const unavailable = result.checks.every(item => item.status === 'unavailable');
      status.className = 'snippet-status is-error';
      status.textContent = unavailable ? 'Сайт недоступен для проверки' : 'Код не найден на главной странице';
    }
  } catch (error) {
    status.className = 'snippet-status is-error';
    status.textContent = error.message || 'Не удалось проверить установку';
  } finally {
    button.disabled = false;
  }
});

const siteModal = document.querySelector('#site-modal');
function openSiteModal(){
  siteModal.hidden = false;
  requestAnimationFrame(() => siteModal.classList.add('is-open'));
  document.querySelector('#new-name').focus();
}
function closeSiteModal(){
  siteModal.classList.remove('is-open');
  setTimeout(() => { siteModal.hidden = true; }, 220);
}
document.querySelector('#add-site-open').addEventListener('click', openSiteModal);
document.querySelector('#site-modal-close').addEventListener('click', closeSiteModal);
document.querySelector('#site-modal-cancel').addEventListener('click', closeSiteModal);
siteModal.addEventListener('click', (e) => { if(e.target === siteModal) closeSiteModal(); });
document.addEventListener('keydown', (e) => { if(e.key === 'Escape' && !siteModal.hidden) closeSiteModal(); });
document.querySelector('#site-form').addEventListener('submit', (e) => {
  e.preventDefault();
  document.querySelector('#add-site').click();
});

document.querySelector('#add-site').addEventListener('click', async (e) => {
  e.preventDefault();
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
    closeSiteModal();
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
