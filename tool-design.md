# Tool design — переносимая дизайн-система инструментальных страниц ClickShot

Источник: `landing-php/tool/aicolumns-batch/` (эталонный, самый насыщенный по компонентам инструмент проекта) +
общая библиотека `landing-php/css/tool-page.css` и `landing-php/css/base.css`.

Этот файл — **самодостаточный** референс. Скопируйте его в любой другой проект (не обязательно PHP) и
воссоздайте вид «инструментов ClickShot» — парсеров, анализаторов, конвертеров, дашбордов, API-исследователей.
Технологии значения не имеют: важны только CSS-токены, разметка и конвенции ниже.

---

## 1. Философия

Инструментальная страница — это отдельный визуальный язык поверх основного лендинга: тот же бренд
(тот же акцент, те же шрифты), но **плотнее** и **функциональнее**. Аудитория — специалисты, которым нужна
плотность информации, а не «маркетинговый театр» большого лендинга.

Три принципа:

1. **Белый фон, один акцент.** Никаких градиентов и декоративных цветов — вся палитра нейтральная
   (шкала «ink»), и только один сигнальный цвет — лаймовый — используется для интерактивных/успешных состояний.
2. **Плотность 13px.** Базовый размер шрифта внутри инструмента — 13px (не 16px, как на лендинге). Мелкие
   моно-лейблы капсом, компактные паддинги, много данных на экран без скролла.
3. **Форма слева, результат справа.** Каноничная раскладка «постройте запрос → увидьте ответ»: `380px` панель
   формы + гибкая панель результата, с общим статусом, вкладками и пустым/ошибочным состоянием.

---

## 2. Дизайн-токены (копировать как есть)

### 2.1 Базовые токены бренда

```css
@import url('https://fonts.googleapis.com/css2?family=Geologica:wght@300;400;500;600;700;800&family=Onest:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

:root {
  /* Ink — нейтральная шкала, основа всего интерфейса */
  --ink-950: #0A0A0A;
  --ink-900: #171717;
  --ink-800: #262626;
  --ink-700: #404040;
  --ink-600: #525252;
  --ink-500: #737373;
  --ink-400: #A3A3A3;
  --ink-300: #D4D4D4;
  --ink-200: #E5E5E5;
  --ink-150: #EDEDED;
  --ink-100: #F5F5F5;
  --ink-50:  #FAFAFA;
  --white:   #FFFFFF;

  /* Signal — единственный акцент, electric lime */
  --lime:      #C5FF1A;
  --lime-600:  #B7F500;
  --lime-400:  #b5ff51;
  --lime-200:  #E6FF99;
  --lime-100:  #F1FFCC;
  --lime-glow: rgba(197,255,26,0.55);

  /* Accent — структурный тёмно-синий, для текста поверх лайма */
  --accent:      #181b25;
  --accent-600:  #232735;
  --accent-tint: #eef0f5;
  --accent-on:   #ffffff;

  /* Радиусы */
  --r-2: 6px; --r-3: 10px; --r-4: 14px; --r-5: 18px; --r-pill: 999px;

  /* Тени (для базового лендинга; см. §2.2 fc-shadow-* для внутренних панелей тула) */
  --shadow-1: 0 1px 2px rgba(10,10,10,0.04);
  --shadow-2: 0 6px 18px rgba(10,10,10,0.06), 0 1px 2px rgba(10,10,10,0.04);
  --shadow-3: 0 18px 40px rgba(10,10,10,0.08), 0 2px 8px rgba(10,10,10,0.04);
  --shadow-4: 0 32px 64px rgba(10,10,10,0.14);

  /* Шрифты */
  --font-display: 'Geologica', 'Onest', system-ui, sans-serif;
  --font-body:    'Onest', system-ui, -apple-system, sans-serif;
  --font-mono:    'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace;

  /* Движение */
  --ease-out:    cubic-bezier(0.2,0.8,0.2,1);
  --ease-spring: cubic-bezier(0.34,1.56,0.64,1);
  --ease-in-out: cubic-bezier(0.65,0,0.35,1);
  --dur-1: 120ms; --dur-2: 220ms; --dur-3: 360ms; --dur-4: 560ms;
}

*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; background: var(--white); color: var(--ink-950);
  font-family: var(--font-body); line-height: 1.55; -webkit-font-smoothing: antialiased; }
.container { max-width: 1280px; margin: 0 auto; padding: 0 32px; }
```

### 2.2 Токены самого тула (`.fc-app` на `<body>`)

Более мелкая шкала поверх базовой — активируется классом `fc-app`, который **обязателен** на `<body>`
инструментальной страницы:

```css
.fc-app {
  --fc-fs-xs:   11px;
  --fc-fs-sm:   12px;
  --fc-fs-base: 13px;
  --fc-fs-md:   14px;
  --fc-fs-lg:   16px;
  --fc-fs-xl:   22px;

  --fc-p-1: 4px; --fc-p-2: 6px; --fc-p-3: 8px; --fc-p-4: 10px; --fc-p-5: 14px; --fc-p-6: 18px; --fc-p-7: 24px;
  --fc-r-1: 4px; --fc-r-2: 6px; --fc-r-3: 8px; --fc-r-4: 12px;

  --fc-lime-ring: rgba(197, 255, 26, 0.28);
  --fc-border:            1px solid var(--ink-200);
  --fc-border-strong:     1px solid var(--ink-300);
  --fc-shadow-card:       0 1px 2px rgba(10,10,10,0.04), 0 12px 28px rgba(10,10,10,0.05);
  --fc-shadow-card-hover: 0 2px 4px rgba(10,10,10,0.05), 0 18px 36px rgba(10,10,10,0.07);

  font-size: var(--fc-fs-base);
}
```

Префикс `fc-` — исторический (Firecrawl, первый тул). **В новом проекте выберите свой короткий префикс**
(2–4 буквы) и замените его во всех классах ниже — сама система токенов и паттернов универсальна.

---

## 3. Скелет страницы

```
<body class="fc-app">
  <nav>...</nav>

  <header class="fc-hero">                      ← белый хиро с дот-сеткой на фоне (опционально)
    <div class="container fc-hero-inner">
      <div>
        <p class="fc-eyebrow">Категория · подзаголовок</p>
        <h1 class="fc-title">Название инструмента</h1>
        <p class="fc-sub">Что делает, для кого — 1 фраза.</p>
      </div>
      <div class="fc-hero-meta">                 ← ссылки/кнопка «Как пользоваться» справа
        <button class="fc-help-btn">Как пользоваться?</button>
        <a class="fc-hero-link" href="...">Docs →</a>
      </div>
    </div>
  </header>

  <main class="fc-main">
    <div class="container">

      <section class="fc-topbar">                ← опционально: API-ключ + режимы/модель
        <div class="fc-key">...</div>
        <div class="fc-mode" role="tablist">...</div>
      </section>

      <section class="fc-grid">                  ← ядро: форма | результат
        <form class="fc-panel fc-form">...</form>
        <div class="fc-panel fc-results">...</div>
      </section>

      <p class="fc-foot-note">Служебная информация о хранении данных, ключах и т.п.</p>
    </div>
  </main>

  <div class="fc-help-overlay">...</div>          ← модалка «Как пользоваться»
</body>
```

`.fc-grid` — двухколоночная сетка `380px 1fr`, схлопывается в одну колонку до `980px`.

---

## 4. Библиотека компонентов (CSS)

Копируйте блоками — все зависят только от токенов из §2.

### 4.1 Хиро и заголовки

```css
.fc-hero { padding: 120px 0 56px; background: var(--white); position: relative; overflow: hidden; isolation: isolate; }
.fc-hero-inner { position: relative; z-index: 1; display: flex; justify-content: space-between; align-items: flex-end; gap: 32px; }
.fc-eyebrow {
  font-family: var(--font-mono); font-size: var(--fc-fs-xs); font-weight: 500;
  letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink-700);
  margin: 0 0 14px; display: inline-flex; align-items: center; gap: 8px;
}
.fc-eyebrow::before { content: ''; width: 6px; height: 6px; background: var(--lime); border-radius: 50%;
  box-shadow: 0 0 0 3px var(--fc-lime-ring); }
.fc-title {
  font-family: var(--font-display); font-weight: 600; font-size: clamp(28px, 3.4vw, 40px);
  letter-spacing: -0.035em; line-height: 1; margin: 0 0 10px; color: var(--ink-950);
}
.fc-sub { font-size: var(--fc-fs-md); color: var(--ink-600); margin: 0; max-width: 560px; }
.fc-hero-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
.fc-hero-link {
  font-family: var(--font-mono); font-size: var(--fc-fs-sm); color: var(--ink-500);
  display: inline-flex; align-items: center; gap: 6px; border-bottom: 1px solid var(--ink-200);
  transition: color var(--dur-2) var(--ease-out), border-color var(--dur-2) var(--ease-out);
}
.fc-hero-link:hover { color: var(--ink-700); border-bottom-color: var(--lime); }
.fc-main { padding: 28px 0 80px; background: var(--white); }

@media (max-width: 720px) {
  .fc-hero { padding: 100px 0 32px; }
  .fc-hero-inner { flex-direction: column; align-items: flex-start; gap: 18px; }
  .fc-hero-meta { flex-direction: row; align-items: center; }
}
```

### 4.2 Панель и сетка формы/результата

Есть два варианта раскладки тела страницы (выбор — по объёму формы):

- **A. Форма слева, результат справа** — `.fc-grid` ниже. Когда у формы заметный набор
  полей (API-ключ, режимы, чипы, тумблеры, доп-настройки).
- **B. Полоса ввода сверху, результат на всю ширину** — когда формы мало (1–2 поля:
  URL/файл + кнопка). Форма становится горизонтальной полосой
  (`display:flex; align-items:center; gap:12px; flex-wrap:wrap`), под ней — панель результата
  на всю ширину. Больше места под таблицы, длинные URL и код. В полосе верни primary-кнопке
  `flex:0 0 auto` (в библиотеке у неё `flex:1`), иначе растянется на всю строку.

```css
.fc-grid { display: grid; grid-template-columns: 380px 1fr; gap: 16px; align-items: start; }
@media (max-width: 980px) { .fc-grid { grid-template-columns: 1fr; } }

/* Раскладка B: вертикальный стек, результат на всю ширину */
.fc-stack { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.fc-inputbar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 14px; }
.fc-inputbar .fc-input-slot { flex: 1 1 320px; min-width: 240px; }
.fc-inputbar .fc-btn-primary { flex: 0 0 auto; }

.fc-panel {
  background: var(--white); border: var(--fc-border); border-radius: var(--fc-r-4);
  box-shadow: var(--fc-shadow-card); transition: box-shadow var(--dur-3) var(--ease-out);
}
.fc-panel:hover { box-shadow: var(--fc-shadow-card-hover); }

.fc-panel-head {
  display: flex; justify-content: space-between; align-items: baseline; gap: 12px;
  padding-bottom: 12px; border-bottom: 1px dashed var(--ink-200);
}
.fc-panel-eyebrow {
  font-family: var(--font-mono); font-size: var(--fc-fs-xs); font-weight: 500; color: var(--ink-700);
  background: var(--lime-100); padding: 2px 6px; border-radius: var(--fc-r-1);
}
.fc-panel-hint { font-size: var(--fc-fs-xs); color: var(--ink-500); }
```

### 4.3 Форма: поля, инпуты, чипы, тумблеры

```css
.fc-form { padding: 16px; display: flex; flex-direction: column; gap: 14px; }
.fc-form[hidden] { display: none; }   /* display:flex выше перебивает [hidden] — гасить явно */

.fc-field { display: flex; flex-direction: column; gap: 6px; }
.fc-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.fc-label {
  font-family: var(--font-mono); font-size: var(--fc-fs-xs); font-weight: 500;
  letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink-700);
  display: inline-flex; align-items: center; gap: 6px;
}
.fc-req  { color: #d4351c; }
.fc-hint { font-family: var(--font-body); font-size: 10px; text-transform: none; color: var(--ink-500); font-weight: 400; }

.fc-input {
  font-family: var(--font-body); font-size: var(--fc-fs-base); padding: 8px 10px;
  border: 1px solid var(--ink-300); border-radius: var(--fc-r-2); background: var(--white);
  color: var(--ink-950); outline: none; width: 100%;
  transition: border-color var(--dur-2) var(--ease-out);
}
.fc-input::placeholder { color: var(--ink-400); }
.fc-input:focus { border-color: var(--lime); box-shadow: 0 0 0 3px var(--fc-lime-ring); }
.fc-textarea { resize: vertical; min-height: 64px; line-height: 1.5; }
.fc-mono { font-family: var(--font-mono); font-size: var(--fc-fs-sm); }
.fc-select {
  appearance: none;
  background-image: linear-gradient(45deg, transparent 50%, var(--ink-700) 50%),
                     linear-gradient(135deg, var(--ink-700) 50%, transparent 50%);
  background-position: calc(100% - 14px) 50%, calc(100% - 10px) 50%;
  background-size: 4px 4px, 4px 4px; background-repeat: no-repeat; padding-right: 28px;
}

/* Чипы: <label class="fc-chip"><input type="checkbox"><span>json</span></label> */
.fc-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.fc-chip { position: relative; cursor: pointer; }
.fc-chip input { position: absolute; opacity: 0; pointer-events: none; }
.fc-chip span {
  display: inline-block; font-family: var(--font-mono); font-size: var(--fc-fs-xs);
  padding: 5px 9px; border: 1px solid var(--ink-300); border-radius: var(--fc-r-1);
  color: var(--ink-700); background: var(--white); transition: all var(--dur-2) var(--ease-out);
}
.fc-chip:hover span { border-color: var(--lime); color: var(--ink-950); }
.fc-chip input:checked + span { background: var(--lime-400); color: var(--accent); border-color: var(--lime-400); }

/* Тумблер: <label class="fc-toggle"><input type="checkbox"><span>label</span></label> */
.fc-toggles { display: flex; flex-direction: column; gap: 6px; }
.fc-toggle { display: inline-flex; align-items: center; gap: 8px; font-family: var(--font-mono);
  font-size: var(--fc-fs-sm); color: var(--ink-700); cursor: pointer; }
.fc-toggle input {
  appearance: none; width: 28px; height: 16px; background: var(--ink-200); border-radius: 999px;
  position: relative; cursor: pointer; transition: background var(--dur-2) var(--ease-out); flex: none;
}
.fc-toggle input::after {
  content: ''; position: absolute; top: 2px; left: 2px; width: 12px; height: 12px;
  background: var(--white); border-radius: 50%; transition: transform var(--dur-2) var(--ease-out);
}
.fc-toggle input:checked { background: var(--lime-400); }
.fc-toggle input:checked::after { transform: translateX(12px); }

/* Сворачиваемый блок допнастроек: <details class="fc-collapse"><summary>Дополнительно</summary>... */
.fc-collapse { border-top: 1px dashed var(--ink-200); padding-top: 12px; }
.fc-collapse summary {
  font-family: var(--font-mono); font-size: var(--fc-fs-xs); font-weight: 500; letter-spacing: 0.06em;
  text-transform: uppercase; color: var(--ink-700); cursor: pointer; list-style: none;
  display: inline-flex; align-items: center; gap: 6px;
}
.fc-collapse summary::-webkit-details-marker { display: none; }
.fc-collapse summary::before {
  content: '+'; font-family: var(--font-mono); font-weight: 600; width: 14px; height: 14px;
  text-align: center; line-height: 13px; border: 1px solid var(--ink-700); border-radius: 3px;
}
.fc-collapse[open] summary::before { content: '−'; }
.fc-collapse-body { display: flex; flex-direction: column; gap: 12px; padding-top: 12px; }

.fc-form-foot { display: flex; gap: 8px; align-items: center; padding-top: 4px; }
```

### 4.4 Кнопки

```css
.fc-btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  font-family: var(--font-body); font-size: var(--fc-fs-base); font-weight: 500;
  padding: 9px 14px; border-radius: var(--fc-r-2); border: 1px solid transparent; cursor: pointer;
  transition: transform var(--dur-2) var(--ease-out), background var(--dur-2) var(--ease-out),
              color var(--dur-2) var(--ease-out), border-color var(--dur-2) var(--ease-out);
  white-space: nowrap;
}
.fc-btn:hover  { transform: translateY(-1px); }
.fc-btn:active { transform: translateY(0); }
.fc-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

/* Primary — лаймовый CTA, обычно flex:1 в подвале формы */
.fc-btn-primary {
  background: var(--lime-400); color: var(--accent); border-color: var(--lime-400); flex: 1;
  box-shadow: 0 1px 2px rgba(181,255,81,0.25), 0 4px 12px rgba(181,255,81,0.18);
}
.fc-btn-primary:hover:not(:disabled) { background: var(--lime); border-color: var(--lime); }

/* Ghost — «голая» вторичная кнопка (текст без рамки) */
.fc-btn-ghost { background: transparent; color: var(--ink-700); }
.fc-btn-ghost:hover { color: var(--ink-950); }

/* Outline — вторичная кнопка, которая должна читаться как кнопка. НЕ переопределяйте
   .fc-btn-ghost локально ради рамки — берите этот вариант. */
.fc-btn-outline { background: var(--white); color: var(--ink-700); border-color: var(--ink-200); }
.fc-btn-outline:hover:not(:disabled) { background: var(--ink-50); color: var(--ink-950); border-color: var(--ink-400); }

/* Иконка-кнопка — квадрат 26×26, для тулбаров/полей ввода */
.fc-icon-btn {
  background: transparent; border: 1px solid transparent; color: var(--ink-500);
  width: 26px; height: 26px; border-radius: var(--fc-r-1); cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center; transition: all var(--dur-2) var(--ease-out);
}
.fc-icon-btn:hover { color: var(--ink-950); background: var(--ink-100); border-color: var(--ink-200); }
```

### 4.5 API-ключ и переключатель режимов (топбар)

```css
.fc-topbar {
  display: grid; grid-template-columns: 1fr auto; gap: 16px; align-items: center;
  background: var(--white); border: var(--fc-border); border-radius: var(--fc-r-4);
  padding: 12px 14px; margin-bottom: 16px; box-shadow: var(--fc-shadow-card);
}
.fc-key { display: grid; grid-template-columns: auto 1fr auto; gap: 10px; align-items: center; }
.fc-key-label { display: inline-flex; align-items: center; gap: 6px; font-family: var(--font-mono);
  font-size: var(--fc-fs-xs); letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink-700); }
.fc-key-input-wrap {
  display: flex; align-items: center; gap: 4px; background: var(--ink-50);
  border: 1px solid var(--ink-200); border-radius: var(--fc-r-2); padding: 2px 4px 2px 8px;
  transition: border-color var(--dur-2) var(--ease-out), background var(--dur-2) var(--ease-out);
}
.fc-key-input-wrap:focus-within { border-color: var(--lime); background: var(--white); box-shadow: 0 0 0 3px var(--fc-lime-ring); }
.fc-key-input { flex: 1; border: none !important; background: transparent !important; font-family: var(--font-mono); }
.fc-key-status {
  font-family: var(--font-mono); font-size: var(--fc-fs-xs); padding: 0 10px; border-radius: var(--fc-r-1);
  background: var(--ink-100); color: var(--ink-600); white-space: nowrap; display: inline-flex; align-items: center;
}
.fc-key-status[data-state="saved"] { background: var(--lime-400); color: var(--accent); }

.fc-mode { display: inline-flex; background: var(--ink-100); border-radius: var(--fc-r-2); padding: 3px; gap: 2px; }
.fc-mode-tab {
  display: inline-flex; align-items: center; gap: 6px; font-size: var(--fc-fs-base); font-weight: 500;
  color: var(--ink-600); background: transparent; border: none; border-radius: var(--fc-r-1); padding: 6px 12px; cursor: pointer;
}
.fc-mode-tab.is-active { background: var(--white); color: var(--ink-950); box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
```

### 4.6 Результат: статус, вкладки, вывод

```css
.fc-results { display: flex; flex-direction: column; min-height: 520px; }
.fc-results-head { display: flex; justify-content: space-between; align-items: center; gap: 10px;
  padding: 12px 14px; border-bottom: 1px solid var(--ink-200); }
.fc-results-actions { display: flex; gap: 4px; }

/* Статус-индикатор: idle (серый) / loading (лайм, пульс) / ok (зелёный) / error (красный) */
.fc-status { display: inline-flex; align-items: center; gap: 8px; font-family: var(--font-mono); font-size: var(--fc-fs-sm); color: var(--ink-700); }
.fc-status-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ink-400); }
.fc-status[data-state="idle"]    .fc-status-dot { background: var(--ink-400); }
.fc-status[data-state="loading"] .fc-status-dot { background: var(--lime); animation: fcPulse 1.2s ease-in-out infinite; }
.fc-status[data-state="ok"]      .fc-status-dot { background: #10b981; }
.fc-status[data-state="error"]   .fc-status-dot { background: #d4351c; }
@keyframes fcPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(197,255,26,0.55); } 50% { box-shadow: 0 0 0 6px rgba(197,255,26,0); } }

/* Вкладки результата */
.fc-tabs { display: flex; flex-wrap: wrap; padding: 0 14px; border-bottom: 1px solid var(--ink-200); background: var(--ink-50); }
.fc-tab {
  font-family: var(--font-mono); font-size: var(--fc-fs-xs); letter-spacing: 0.04em; background: transparent;
  border: none; border-bottom: 2px solid transparent; color: var(--ink-500); padding: 9px 10px; cursor: pointer; margin-bottom: -1px;
}
.fc-tab.is-active { color: var(--ink-950); border-bottom-color: var(--lime); font-weight: 500; }

/* Область вывода + пустое/ошибочное состояние */
.fc-output { flex: 1; padding: 14px; overflow: auto; max-height: 70vh; background: var(--white); }
.fc-empty { padding: 48px 16px; text-align: center; color: var(--ink-500); }
.fc-empty-title { font-family: var(--font-display); font-size: var(--fc-fs-lg); font-weight: 600; color: var(--ink-700); margin: 0 0 8px; }
.fc-empty-sub { font-size: var(--fc-fs-sm); line-height: 1.6; margin: 0; }
.fc-empty kbd { font-family: var(--font-mono); font-size: 11px; background: var(--ink-100); border: 1px solid var(--ink-200); border-radius: 3px; padding: 1px 5px; }
.fc-pane { margin: 0; font-family: var(--font-mono); font-size: var(--fc-fs-sm); line-height: 1.55; white-space: pre-wrap; word-break: break-word; }
.fc-pane[hidden] { display: none; }
.fc-error {
  margin: 0; padding: 14px 16px; background: #fff1f0; border: 1px solid #fbb4ad; border-radius: var(--fc-r-2);
  color: #8a1c10; font-family: var(--font-mono); font-size: var(--fc-fs-sm); white-space: pre-wrap;
}
.fc-foot-note { margin: 24px 0 0; font-family: var(--font-mono); font-size: var(--fc-fs-xs); color: var(--ink-500); text-align: center; }
```

### 4.7 Модалка «Как пользоваться»

```css
.fc-help-overlay {
  position: fixed; inset: 0; background: rgba(10,10,10,0.45); z-index: 200;
  display: flex; align-items: center; justify-content: center; padding: 24px;
  opacity: 0; pointer-events: none; transition: opacity var(--dur-3) var(--ease-out);
}
.fc-help-overlay.is-open { opacity: 1; pointer-events: auto; }
.fc-help-panel {
  background: var(--white); border-radius: var(--fc-r-4); box-shadow: var(--shadow-4);
  width: 100%; max-width: 560px; max-height: 85vh; overflow-y: auto; padding: 28px 28px 24px;
  transform: translateY(8px); transition: transform var(--dur-3) var(--ease-out);
}
.fc-help-overlay.is-open .fc-help-panel { transform: translateY(0); }
.fc-help-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 20px; }
.fc-help-title { font-family: var(--font-display); font-weight: 600; font-size: 18px; margin: 0; }
.fc-help-close { background: none; border: none; color: var(--ink-500); cursor: pointer; }
.fc-help-body { display: flex; flex-direction: column; gap: 20px; }
.fc-help-section h3 {
  font-family: var(--font-mono); font-size: var(--fc-fs-xs); letter-spacing: 0.1em; text-transform: uppercase;
  color: var(--ink-700); margin: 0 0 8px; display: inline-flex; align-items: center; gap: 6px;
}
.fc-help-section h3::before { content: ''; width: 5px; height: 5px; background: var(--lime); border-radius: 50%; }
.fc-help-steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; counter-reset: help-step; }
.fc-help-steps li { counter-increment: help-step; display: flex; align-items: baseline; gap: 10px; }
.fc-help-steps li::before {
  content: counter(help-step); font-family: var(--font-mono); font-size: 11px; font-weight: 600;
  min-width: 20px; height: 20px; background: var(--lime-100); color: var(--accent); border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.fc-help-table { width: 100%; border-collapse: collapse; font-size: var(--fc-fs-base); }
.fc-help-table th { font-family: var(--font-mono); font-size: var(--fc-fs-xs); color: var(--ink-500); text-align: left; padding: 0 10px 6px 0; border-bottom: 1px solid var(--ink-200); }
.fc-help-table td { padding: 7px 10px 7px 0; border-bottom: 1px solid var(--ink-150); color: var(--ink-700); vertical-align: top; }
.fc-help-table td:first-child { font-family: var(--font-mono); font-size: var(--fc-fs-xs); color: var(--ink-950); white-space: nowrap; }
.fc-help-note { background: var(--ink-50); border-left: 3px solid var(--lime-400); padding: 10px 12px; font-size: var(--fc-fs-sm); color: var(--ink-600); margin: 0; }
```

JS (одинаков для всех страниц, IIFE):

```js
(function () {
  var overlay = document.getElementById('fc-help-overlay');
  var openBtn = document.getElementById('fc-help-open');
  var closeBtn = document.getElementById('fc-help-close');
  function open()  { overlay.classList.add('is-open');    document.body.style.overflow = 'hidden'; }
  function close() { overlay.classList.remove('is-open'); document.body.style.overflow = ''; }
  if (openBtn)  openBtn.addEventListener('click', open);
  if (closeBtn) closeBtn.addEventListener('click', close);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
```

### 4.8 Паттерн «фоновая/асинхронная задача» (батч, джоба, очередь)

Взято из `aicolumns-batch` — понадобится в любом инструменте с длительной серверной операцией
(batch API, очередь, экспорт, фоновая генерация). Не часть базовой `tool-page.css`, но воспроизводимый паттерн:

```css
/* Карточка состояния задачи: eyebrow + id + статус-пилюля справа */
.__pfx-job { display: flex; flex-direction: column; gap: 12px; padding: 14px 16px;
  background: var(--ink-50); border: 1px solid var(--ink-150); border-radius: var(--fc-r-3); }
.__pfx-job-head { display: flex; align-items: center; gap: 10px; }
.__pfx-job-id { flex: 1 1 auto; min-width: 0; font-family: var(--font-mono); font-size: var(--fc-fs-sm);
  color: var(--ink-700); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Статус-пилюля: нейтральная по умолчанию, лайм на success, красная на terminal-ошибках */
.__pfx-job-state {
  font-family: var(--font-mono); font-size: var(--fc-fs-xs); text-transform: uppercase;
  padding: 3px 10px; border-radius: 99px; background: var(--ink-150); color: var(--ink-600);
}
.__pfx-job-state[data-state="completed"] { background: var(--lime); color: var(--ink-900); }
.__pfx-job-state[data-state="failed"],
.__pfx-job-state[data-state="cancelled"] { background: #ffe3e3; color: #b40000; }

/* Тонкий прогресс-бар */
.__pfx-progress { height: 4px; background: var(--ink-150); border-radius: 99px; overflow: hidden; }
.__pfx-progress-bar { height: 100%; width: 0; background: var(--lime-600); transition: width .2s ease; }

/* Строка метрик через разделитель | */
.__pfx-stat { display: inline-flex; align-items: baseline; gap: 8px; }
.__pfx-stat + .__pfx-stat { padding-left: 14px; border-left: 1px solid var(--ink-200); }
```

Правила состояний-пилюль, которые стоит повторять везде: **пусто/неизвестно** → нейтральный текст без
заливки; **успех/готово** → заливка лаймом, тёмный текст; **ошибка/отмена/просрочено** → красная заливка
(`#ffe3e3` / `#b40000`). Так пользователь считывает статус боковым зрением, не читая текст.

### 4.9 Паттерн «таблица результата»

Из `aicolumns-batch`: скроллируемая таблица с sticky-заголовком, ячейками успеха/ошибки/ожидания.

```css
.__pfx-table-wrap { overflow: auto; max-height: 60vh; max-width: 100%; }
.__pfx-table-wrap table { border-collapse: collapse; width: max-content; min-width: 100%; font-size: var(--fc-fs-sm); }
.__pfx-table-wrap th, .__pfx-table-wrap td {
  border: 1px solid var(--ink-150); padding: 5px 8px; text-align: left; vertical-align: top;
  min-width: 90px; max-width: 280px; word-break: break-word;
}
.__pfx-table-wrap th { position: sticky; top: 0; background: var(--ink-50); z-index: 1; }
.__pfx-table-wrap td.is-ok  { background: color-mix(in srgb, var(--lime) 14%, transparent); }
.__pfx-table-wrap td.is-err { background: color-mix(in srgb, #ff3b3b 12%, transparent); color: #b40000; }
.__pfx-table-wrap td.is-pending { color: var(--ink-400); }
```

---

## 5. Конвенции (обязательны в любом проекте)

1. **Свой префикс на каждый инструмент.** Общие компоненты — без префикса (как `fc-*` в референсе) или с
   префиксом библиотеки; уникальная вёрстка конкретной страницы/фичи — со своим коротким префиксом
   (`aic-`, `da-`, ...). Никогда не вводите глобальные классы без префикса — риск коллизий.
2. **Иконки — не эмодзи.** Только контурные SVG-иконки (в ClickShot — Lucide через `icon()`). Это касается
   и кнопок «скопировать/скачать», и статусов, и футер-ссылок.
3. **Кнопки — три уровня, не больше.** `primary` (лайм, одна на форму) / `ghost` (голый текст) / `outline`
   (белая с рамкой — вторичная кнопка, которая должна «читаться» как кнопка). Не переопределяйте `ghost`
   локально ради рамки — берите `outline`.
4. **Токены, не сырые значения.** Цвета/отступы/радиусы — только через переменные. Так тема остаётся
   консистентной и легко эволюционирует одним местом правки.
5. **`[hidden]` и flex/grid.** Классы с `display:flex/grid` перебивают атрибут `hidden`. Если переключаете
   видимость через `hidden`, добавьте `[hidden] { display: none !important; }` в локальный CSS страницы.
6. **Состояния — вокабуляр `is-*`.** `.is-active`, `.is-open`, `.is-current` — всегда в связке с классом
   компонента, не отдельно.
7. **Статус через `data-state`, не через отдельные классы.** `data-state="idle|loading|ok|error"` на одном
   элементе — переключается в JS одной строкой (`el.dataset.state = 'ok'`), CSS реагирует сам.
8. **`min-width: 0` против переполнения.** Панель результата в grid/flex-колонке по умолчанию имеет
   `min-width: auto` и распирается широким контентом (длинные URL с `white-space: nowrap`, широкие таблицы,
   ряды кнопок) за край экрана. Задайте `min-width: 0` панели результата и её потомкам, чтобы колонка
   ужималась, а внутренний контент обрезался (`text-overflow: ellipsis`) или скроллился сам.
9. **Один вертикальный скролл.** Не вкладывайте скролл-области друг в друга (свой скролл у таблицы + у кода +
   общий). Основной вертикальный скролл — страничный; широким таблицам и коду давайте только `overflow-x: auto`.

---

## 6. Как применить в новом проекте (чек-лист)

1. Подключите шрифты (Google Fonts import из §2.1) и вставьте блок токенов `:root` + `.fc-app` (или
   переименуйте scope-класс под своё имя проекта).
2. Скопируйте секции 4.1–4.7 в общий стиль библиотеки компонентов инструмента (один файл на всё приложение,
   аналог `tool-page.css`).
3. Соберите страницу по скелету из §3: хиро → (опционально) топбар → `fc-grid` (форма | результат) → футнот.
4. Для длительных операций (батч/очередь/фон) — паттерн §4.8; для табличного вывода — §4.9.
5. Не копируйте компоненты в page-specific CSS — там только уникальные для конкретной страницы правила
   (см. `aicolumns.css` в ClickShot как эталон тонкого override-файла).
6. Замените плейсхолдеры `.fc-*`/`.__pfx-*` на префикс вашего проекта, если хотите изолировать неймспейс от
   ClickShot-специфичных классов.

---

## 7. Что сознательно не включено

- Контентные правила ClickShot (русский, «Вы», длинное тире, sentence case) — это бренд-специфика ClickShot,
  а не часть визуального дизайна тула. Не переносите в проект с другим языком/тоном.
- PHP-специфика (`icon()`, `require_once sections/*.php`, роутинг папками) — реализационная деталь
  ClickShot; в другом стеке замените на свой способ инклюдить SVG-иконки и партиалы.
- Полный `tool-page.css` (1733 строки, включает ещё блок «документационных» компонентов для тулов-гайдов) —
  если нужен именно он целиком, скопируйте файл `landing-php/css/tool-page.css` напрямую.
