/* ClickShot Metrika — поведение неоморфической темы «Softline».
 *
 * Файл подключается всегда, но работает только в теме data-theme="neu":
 * классическая тема живёт на разметке и скриптах из public/index.php.
 *
 * Данные приходят двумя путями: событие clickshot:stats (статистика загрузилась)
 * и window.clickshotStats (последний ответ API — чтобы перерисовать график
 * сразу после переключения темы, не дожидаясь нового запроса).
 */
(function () {
  'use strict';

  var RING_RADIUS = 46;
  var RING_LENGTH = 2 * Math.PI * RING_RADIUS;
  var LIVE_INTERVAL = 3000;

  var liveTimer = null;
  var agoTimer = null;
  var loadedAt = null;
  var live = true;

  function q(selector, root) {
    return (root || document).querySelector(selector);
  }

  function qa(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function fmt(value) {
    return new Intl.NumberFormat('ru-RU').format(Number(value || 0));
  }

  function esc(value) {
    if (value === null || value === undefined) return '';
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function isNeu() {
    return document.documentElement.getAttribute('data-theme') === 'neu';
  }

  /* ── График динамики ─────────────────────────────────────────── */

  function renderChart(data) {
    var svg = q('#neu-chart-svg');
    if (!svg) return;

    var daily = (data && data.daily) || [];
    var width = 600;
    var height = 220;
    var visits = daily.map(function (row) { return Number(row.visits || 0); });
    var pageviews = daily.map(function (row) { return Number(row.pageviews || 0); });
    var max = Math.max(1, Math.max.apply(null, visits.concat(pageviews).concat([0])));

    function points(values) {
      if (!values.length) return [];
      return values.map(function (value, index) {
        var x = values.length === 1 ? width / 2 : (index * width / (values.length - 1));
        var y = height - 8 - (value / max) * (height - 28);
        return [x, y];
      });
    }

    function line(list) {
      return list.map(function (point, index) {
        return (index ? 'L' : 'M') + point[0].toFixed(1) + ',' + point[1].toFixed(1);
      }).join('');
    }

    var pathA = line(points(visits));
    var pathB = line(points(pageviews));

    q('#neuLineA').setAttribute('d', pathA);
    q('#neuLineB').setAttribute('d', pathB);
    q('#neuAreaA').setAttribute('d', pathA ? pathA + 'L' + width + ',' + height + 'L0,' + height + 'Z' : '');
    q('#neuAreaB').setAttribute('d', pathB ? pathB + 'L' + width + ',' + height + 'L0,' + height + 'Z' : '');

    var lastA = points(visits).slice(-1)[0];
    q('#neuDotA').setAttribute('cx', lastA ? lastA[0].toFixed(1) : 0);
    q('#neuDotA').setAttribute('cy', lastA ? lastA[1].toFixed(1) : 0);

    var axis = q('#neu-axis');
    if (axis) {
      if (!daily.length) {
        axis.innerHTML = '';
        return;
      }
      var count = Math.min(5, daily.length);
      var indexes = [];
      for (var i = 0; i < count; i++) {
        indexes.push(Math.round(i * (daily.length - 1) / (count > 1 ? count - 1 : 1)));
      }
      axis.innerHTML = indexes.map(function (index) {
        return '<span>' + esc(daily[index].label) + '</span>';
      }).join('');
    }
  }

  /* ── Донат «Источники трафика» ───────────────────────────────── */

  function renderDonut(data, animate) {
    var referrers = (data && data.referrers) || [];
    var totals = (data && data.totals) || {};
    var total = Number(totals.visits || 0);
    var arcs = qa('.neu-ring-arc');
    var segments = q('#neu-segments');
    var percent = q('#neu-ring-pct');
    if (!arcs.length || !segments) return;

    var top = referrers.slice(0, 2).map(function (row) {
      return { name: row.referrer, value: Number(row.visits || 0) };
    });
    var used = top.reduce(function (sum, row) { return sum + row.value; }, 0);
    var rest = Math.max(0, total - used);

    var parts = [
      { name: top[0] ? top[0].name : 'Топ-источник', value: top[0] ? top[0].value : 0 },
      { name: top[1] ? top[1].name : 'Второй источник', value: top[1] ? top[1].value : 0 },
      { name: 'Остальные источники', value: rest }
    ];

    var offset = 0;
    parts.forEach(function (part, index) {
      var share = total > 0 ? part.value / total : 0;
      var length = RING_LENGTH * share;
      var arc = arcs[index];
      if (!arc) return;
      arc.setAttribute('stroke-dashoffset', (-offset).toFixed(2));
      arc.dataset.length = length.toFixed(2);
      if (animate) {
        arc.style.transition = 'none';
        arc.setAttribute('stroke-dasharray', '0 ' + RING_LENGTH.toFixed(2));
      } else {
        arc.style.transition = 'none';
        arc.setAttribute('stroke-dasharray', length.toFixed(2) + ' ' + (RING_LENGTH - length).toFixed(2));
      }
      offset += length;
    });

    if (animate) {
      requestAnimationFrame(function () {
        arcs.forEach(function (arc, index) {
          setTimeout(function () {
            arc.style.transition = 'stroke-dasharray 1.2s cubic-bezier(.4,1.2,.4,1)';
            var length = Number(arc.dataset.length || 0);
            arc.setAttribute('stroke-dasharray', length.toFixed(2) + ' ' + (RING_LENGTH - length).toFixed(2));
          }, index * 120);
        });
      });
    }

    var shareTop = total > 0 && parts[0].value > 0 ? Math.round(parts[0].value / total * 100) : null;
    if (percent) percent.textContent = shareTop === null ? '—' : shareTop + '%';

    segments.innerHTML = parts.map(function (part, index) {
      var share = total > 0 ? Math.round(part.value / total * 100) : 0;
      var tone = index === 0 ? 'is-a' : index === 1 ? 'is-b' : 'is-c';
      return '<div class="neu-seg"><i class="' + tone + '"></i><span>' + esc(part.name) + '</span><b>' + share + '%</b></div>';
    }).join('');
  }

  /* ── Список активности по дням ──────────────────────────────── */

  function renderActivity(data) {
    var rows = q('#neu-rows');
    if (!rows) return;

    var daily = (data && data.daily) || [];
    var recent = daily.slice(-4).reverse();
    if (!recent.length) {
      rows.innerHTML = '<div class="neu-empty">Нет данных за выбранный период.</div>';
      return;
    }

    var average = recent.reduce(function (sum, row) { return sum + Number(row.visits || 0); }, 0) / recent.length;
    var grouped = data && data.group && data.group !== 'day';

    rows.innerHTML = recent.map(function (row, index) {
      var visits = Number(row.visits || 0);
      var pageviews = Number(row.pageviews || 0);
      var empty = visits === 0 && pageviews === 0;
      var diff = average > 0 ? (visits - average) / average : 0;
      var tone = empty ? 'is-flat' : diff > 0.1 ? 'is-up' : diff < -0.1 ? 'is-down' : 'is-flat';
      var label = empty ? 'нет данных' : diff > 0.1 ? 'Рост' : diff < -0.1 ? 'Спад' : 'Стабильно';
      var glyph = empty ? '–' : diff > 0.1 ? '↑' : diff < -0.1 ? '↓' : '→';
      var color = empty ? 'var(--neu-muted)' : diff > 0.1 ? 'var(--neu-teal)' : diff < -0.1 ? 'var(--neu-alert)' : 'var(--neu-amber)';
      var when = grouped ? esc(row.label) : relativeDay(index);
      return '<div class="neu-row">' +
        '<div class="neu-row-ic" style="color:' + color + '">' + glyph + '</div>' +
        '<div><div class="neu-row-nm">' + esc(row.label) + '</div>' +
        '<div class="neu-row-sb">' + fmt(visits) + ' визитов · ' + fmt(pageviews) + ' просмотров</div></div>' +
        '<span class="neu-pill ' + tone + '">' + label + '</span>' +
        '<span class="neu-time">' + when + '</span>' +
        '</div>';
    }).join('');
  }

  function relativeDay(index) {
    if (index === 0) return 'сегодня';
    if (index === 1) return 'вчера';
    return index + ' дн. назад';
  }

  /* ── Live-режим и счётчик «обновлено N назад» ────────────────── */

  function setLive(on) {
    live = !!on;
    var button = q('#neu-live');
    if (button) {
      button.classList.toggle('is-on', live);
      button.setAttribute('aria-pressed', String(live));
      var label = q('.neu-toggle-label', button);
      if (label) {
        label.textContent = live
          ? 'Live — обновление каждые 3 с'
          : 'Пауза — нажмите, чтобы возобновить';
      }
    }
    if (live && isNeu()) startLive(); else stopLive();
  }

  function startLive() {
    stopLive();
    liveTimer = setInterval(function () {
      if (typeof window.loadStats === 'function') window.loadStats();
    }, LIVE_INTERVAL);
  }

  function stopLive() {
    if (liveTimer) {
      clearInterval(liveTimer);
      liveTimer = null;
    }
  }

  function updateAgo() {
    var element = q('#neu-ago');
    if (!element) return;
    if (!loadedAt) {
      element.textContent = '—';
      return;
    }
    var seconds = Math.floor((Date.now() - loadedAt) / 1000);
    element.textContent = seconds < 5
      ? 'только что'
      : seconds < 60 ? seconds + ' с назад' : Math.floor(seconds / 60) + ' мин назад';
  }

  function updatePeriod(data) {
    var element = q('#neu-period');
    if (!element || !data) return;
    var from = data.from || '';
    var to = data.to || '';
    element.textContent = from && to ? from + ' — ' + to : 'Текущий период';
  }

  /* ── Общий рендер ───────────────────────────────────────────── */

  function render(data, animate) {
    if (!data) return;
    renderChart(data);
    renderDonut(data, animate);
    renderActivity(data);
    updatePeriod(data);
    loadedAt = Date.now();
    updateAgo();
  }

  /* ── Привязки рельса ────────────────────────────────────────── */

  function bindRail() {
    qa('[data-neu-tab]').forEach(function (item) {
      item.addEventListener('click', function () {
        if (typeof window.switchTab === 'function') window.switchTab(item.dataset.neuTab);
      });
    });
  }

  function boot() {
    bindRail();

    var liveButton = q('#neu-live');
    if (liveButton) {
      liveButton.addEventListener('click', function () {
        setLive(!live);
      });
    }

    agoTimer = setInterval(updateAgo, 10000);

    document.addEventListener('clickshot:stats', function (event) {
      render(event.detail, false);
    });

    document.addEventListener('clickshot:theme', function (event) {
      var theme = event.detail && event.detail.theme;
      if (theme === 'neu') {
        setLive(live);
        render(window.clickshotStats, true);
      } else {
        stopLive();
      }
    });

    if (window.clickshotStats) render(window.clickshotStats, true);
    setLive(live);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
