/**
 * Slide to Accept Cookie Consent — v2
 * Окно согласия на обработку cookie, совмещённое со слайд-капчей:
 * для согласия нужно провести слайдер вправо.
 *
 * Отличия от slide-cookie.js (v1):
 *   1. Динамические стили ставятся через style.setProperty(..., 'important').
 *      В v1 писалось `el.style.prop = 'value !important'` — CSSOM молча
 *      отбрасывает такое присваивание, поэтому анимация-подсказка не гасла
 *      во время драга, возврат слайдера был без transition.
 *   2. Цели Метрики: ID счётчика определяется в т.ч. из URL сниппета
 *      (в т.ч. перехваченного блокировкой), цели копятся в очереди и уходят
 *      только когда счётчику разрешено работать.
 *   3. Pointer Events + setPointerCapture; обработаны pointercancel /
 *      touchcancel / потеря окна — драг больше не «залипает».
 *   4. Ширина трека пересчитывается на resize / orientationchange.
 *   5. document.createElement восстанавливается корректно (без bind-обёртки).
 *   6. Доступность: role="dialog", фокус-ловушка, блокировка фона и скролла,
 *      управление слайдером с клавиатуры, live-region.
 *   7. Гард от двойного подключения, валидация data-policy-url,
 *      настраиваемая область хранения отказа.
 *
 * Подключать синхронно в <head>, ВЫШЕ сниппета Яндекс.Метрики.
 *
 * Атрибуты тега <script>:
 *   data-cookie-param      — имя GET-параметра для условного показа ('always')
 *   data-cookie-key        — ожидаемое значение параметра ('')
 *   data-ym-counter        — ID счётчика Метрики (иначе автоопределение)
 *   data-policy-url        — ссылка на политику (http(s):// или относительная)
 *   data-block-metrika     — 'false' | '0' | 'no' выключает блокировку счётчика
 *   data-accent-color      — акцент (прогресс)
 *   data-dark-color        — тёмный (фон слайдера, заголовок)
 *   data-accent-text-color — цвет иконки на тёмной кнопке
 */

(function () {
    'use strict';

    // ========== ГАРД ОТ ДВОЙНОГО ПОДКЛЮЧЕНИЯ ==========
    if (window.__slideCookieV2) {
        return;
    }
    window.__slideCookieV2 = true;

    // Уникальный префикс для избежания конфликтов
    const PREFIX = 'cc-' + Date.now() + '-';

    // Геометрия слайдера
    const INSET = 5;          // отступ круга внутри трека
    const TRACK_HEIGHT = 60;
    const KNOB_SIZE = TRACK_HEIGHT - INSET * 2;
    const ACCEPT_RATIO = 0.9; // доля пути, после которой согласие засчитано

    // ========== УТИЛИТЫ ==========

    // Единственный корректный способ задать inline-стиль с !important.
    // `el.style.prop = 'value !important'` невалиден и молча игнорируется.
    function setImportant(el, prop, value) {
        try {
            el.style.setProperty(prop, value, 'important');
        } catch (e) {
            // Экзотические окружения — не роняем виджет
        }
    }

    // Отсекаем javascript:/data: и прочие небезопасные схемы в data-policy-url
    function safeUrl(value) {
        if (!value) return '';
        const url = String(value).trim();
        if (/^(?:https?:\/\/|\/|\.{1,2}\/|#|\?)/i.test(url)) {
            return url;
        }
        return '';
    }

    // ========== ПОЛУЧЕНИЕ КОНФИГУРАЦИИ ИЗ АТРИБУТОВ СКРИПТА ==========
    function getConfig() {
        const injectedConfig = window.__clickShotSlideCookieConfig;
        if (injectedConfig && typeof injectedConfig === 'object') {
            return injectedConfig;
        }
        const currentScript = document.currentScript ||
                             document.querySelector('script[src*="slide-cookie-v2"]') ||
                             document.querySelector('script[src*="slide-cookie"]') ||
                             document.querySelector('script[data-cookie-param]');

        // Значения по умолчанию
        const defaults = {
            UTM_PARAM: 'always',
            UTM_KEY: '',
            YM_COUNTER: null,
            POLICY_URL: '',
            // По умолчанию блокируем Яндекс.Метрику до получения согласия
            BLOCK_METRIKA: true,
            // Основные цвета (фирменные): акцент и тёмный
            ACCENT_COLOR: '#C5FF1A', // лайм — прогресс, кнопка-галочка
            DARK_COLOR: '#0A0A0A',   // ink — фон слайдера, заголовок, текст согласия
            ACCENT_TEXT_COLOR: '#C5FF1A' // цвет иконки/галочки на тёмной кнопке
            , STORAGE_KEY: 'cookieConsentGiven'
        };

        if (!currentScript) {
            return defaults;
        }

        const attr = function (name) {
            return currentScript.getAttribute(name);
        };
        const isOff = function (value) {
            return value === 'false' || value === '0' || value === 'no';
        };

        const ymCounter = attr('data-ym-counter');
        const blockAttr = attr('data-block-metrika');

        return {
            UTM_PARAM: attr('data-cookie-param') || defaults.UTM_PARAM,
            UTM_KEY: attr('data-cookie-key') || defaults.UTM_KEY,
            YM_COUNTER: ymCounter ? parseInt(ymCounter, 10) || null : null,
            POLICY_URL: safeUrl(attr('data-policy-url')),
            BLOCK_METRIKA: blockAttr === null ? defaults.BLOCK_METRIKA : !isOff(blockAttr),
            ACCENT_COLOR: attr('data-accent-color') || defaults.ACCENT_COLOR,
            DARK_COLOR: attr('data-dark-color') || defaults.DARK_COLOR,
            // По умолчанию цвет иконки равен акценту, если явно не задан
            ACCENT_TEXT_COLOR: attr('data-accent-text-color')
                || attr('data-accent-color')
                || defaults.ACCENT_TEXT_COLOR,
            STORAGE_KEY: attr('data-cookie-storage-key') || defaults.STORAGE_KEY
        };
    }

    // ========== КОНФИГУРАЦИЯ ==========
    const CONFIG = getConfig();

    // ========== ПРОВЕРКА УСЛОВИЙ ПОКАЗА ==========
    function checkActivation() {
        // По умолчанию (always + пустой ключ) окно показывается всегда
        if (CONFIG.UTM_PARAM === 'always' && CONFIG.UTM_KEY === '') {
            return true;
        }

        try {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(CONFIG.UTM_PARAM) === CONFIG.UTM_KEY;
        } catch (e) {
            return false;
        }
    }

    // ========== РАБОТА С СОСТОЯНИЕМ ==========
    function isConsentGiven() {
        try {
            return localStorage.getItem(CONFIG.STORAGE_KEY) === 'true';
        } catch (e) {
            // Если localStorage недоступен, считаем что согласие не дано
            return false;
        }
    }

    function markConsentGiven() {
        try {
            localStorage.setItem(CONFIG.STORAGE_KEY, 'true');
        } catch (e) {
            // Игнорируем ошибки localStorage
        }
    }

    // ========== БЛОКИРОВКА ЯНДЕКС.МЕТРИКИ ДО СОГЛАСИЯ ==========
    // Перехватываем создание <script> и не даём загрузиться счётчику,
    // пока пользователь не дал согласие. Срабатывает только если этот скрипт
    // подключён ВЫШЕ сниппета Метрики на странице и сниппет создаёт тег
    // через document.createElement (так делает штатный сниппет Яндекса).
    // Разметочный <script src> в HTML и Document.prototype.createElement.call()
    // этим способом не перехватываются.

    let metrikaBlockInstalled = false;
    let originalCreateElement = null;
    let createElementWasOwnProp = false;
    let cachedSrcDescriptor = null;
    let srcDescriptorResolved = false;
    const blockedMetrikaScripts = [];

    function isMetrikaSrc(value) {
        if (!value) return false;
        return /mc\.yandex\.(ru|com)|metrika\/(tag|watch)\.js|mc\.webvisor\.(org|com)/i.test(String(value));
    }

    function getScriptSrcDescriptor() {
        if (srcDescriptorResolved) return cachedSrcDescriptor;
        srcDescriptorResolved = true;
        try {
            const proto = (typeof HTMLScriptElement !== 'undefined' && HTMLScriptElement.prototype) || null;
            cachedSrcDescriptor = proto ? Object.getOwnPropertyDescriptor(proto, 'src') : null;
        } catch (e) {
            cachedSrcDescriptor = null;
        }
        return cachedSrcDescriptor;
    }

    function installMetrikaBlock() {
        if (metrikaBlockInstalled) return;
        if (typeof document === 'undefined' || typeof document.createElement !== 'function') return;

        const srcDescriptor = getScriptSrcDescriptor();
        if (!srcDescriptor || !srcDescriptor.set || !srcDescriptor.get) return;

        metrikaBlockInstalled = true;
        // Сохраняем ИМЕННО ссылку на метод, без bind: иначе при восстановлении
        // на document навсегда остаётся собственное свойство — связанная функция,
        // теряющая переданный this.
        originalCreateElement = document.createElement;
        createElementWasOwnProp = Object.prototype.hasOwnProperty.call(document, 'createElement');

        document.createElement = function (tagName, options) {
            const element = originalCreateElement.call(this, tagName, options);
            try {
                if (typeof tagName === 'string' && tagName.toLowerCase() === 'script') {
                    // Подменяем свойство src у создаваемого скрипта
                    Object.defineProperty(element, 'src', {
                        configurable: true,
                        enumerable: true,
                        get() {
                            return srcDescriptor.get.call(this);
                        },
                        set(value) {
                            if (isMetrikaSrc(value)) {
                                // Запоминаем намерение загрузить счётчик, но не загружаем
                                blockedMetrikaScripts.push({ element: this, src: String(value) });
                                return;
                            }
                            srcDescriptor.set.call(this, value);
                        }
                    });
                }
            } catch (e) {
                // Не ломаем создание элемента в экзотических окружениях
            }
            return element;
        };
    }

    // Снимаем перехватчик с document.createElement, возвращая исходное состояние
    function restoreCreateElement() {
        if (!metrikaBlockInstalled) return;
        metrikaBlockInstalled = false;

        try {
            if (createElementWasOwnProp) {
                document.createElement = originalCreateElement;
            } else {
                // Метод был унаследован от Document.prototype — возвращаем как было
                delete document.createElement;
                if (document.createElement !== originalCreateElement) {
                    document.createElement = originalCreateElement;
                }
            }
        } catch (e) {
            document.createElement = originalCreateElement;
        }
    }

    function unblockMetrika() {
        restoreCreateElement();

        const srcDescriptor = getScriptSrcDescriptor();

        // Дозагружаем отложенные скрипты Метрики.
        // Пустой <script> без src при вставке в DOM не получает флаг
        // «already started», поэтому назначение src запускает загрузку.
        blockedMetrikaScripts.splice(0).forEach(function (item) {
            try {
                // Снимаем перехватчик src с конкретного элемента
                delete item.element.src;
                if (srcDescriptor && srcDescriptor.set) {
                    srcDescriptor.set.call(item.element, item.src);
                } else {
                    item.element.setAttribute('src', item.src);
                }
            } catch (e) {
                try { item.element.setAttribute('src', item.src); } catch (e2) {}
            }
        });
    }

    // ========== РАННЯЯ БЛОКИРОВКА МЕТРИКИ ==========
    // Ставим перехватчик максимально рано — до выполнения сниппета счётчика
    // (но уже после объявлений выше, чтобы не словить temporal dead zone).
    // Вся IIFE отрабатывает синхронно до сниппета Метрики, поэтому «ранность»
    // не теряется от переноса вызова сюда.
    if (CONFIG.BLOCK_METRIKA && checkActivation() && !isConsentGiven()) {
        installMetrikaBlock();
    }

    // Можно ли вообще обращаться к счётчику. Если блокировка не встала
    // (выключена, согласие уже есть или окружение без нужного дескриптора) —
    // Метрика работает штатно и цели можно слать сразу.
    let metrikaAllowed = !metrikaBlockInstalled;

    // ========== ЯНДЕКС МЕТРИКА: ЦЕЛИ ==========

    const pendingGoals = [];
    let counterWaitTimer = null;

    // Достаём ID счётчика из URL вида .../metrika/tag.js?id=12345678
    function counterIdFromSrc(src) {
        const match = /[?&]id=(\d+)/.exec(String(src || ''));
        return match ? parseInt(match[1], 10) : null;
    }

    // Получение ID счетчика с приоритетом
    function getMetrikaCounterId() {
        // Приоритет 1: явно указанный ID в data-атрибуте
        if (CONFIG.YM_COUNTER) {
            return CONFIG.YM_COUNTER;
        }

        // Приоритет 2: URL сниппета — работает раньше всего и даже тогда,
        // когда сам счётчик заблокирован нами (v1 в этом случае ID не находил
        // и молча терял все цели)
        try {
            for (let i = 0; i < blockedMetrikaScripts.length; i++) {
                const id = counterIdFromSrc(blockedMetrikaScripts[i].src);
                if (id) return id;
            }
        } catch (e) {}

        try {
            const scripts = document.getElementsByTagName('script');
            for (let i = 0; i < scripts.length; i++) {
                const src = scripts[i].src;
                if (src && /mc\.yandex\.(ru|com)\/metrika\/tag\.js/i.test(src)) {
                    const id = counterIdFromSrc(src);
                    if (id) return id;
                }
            }
        } catch (e) {}

        // Приоритет 3: автоопределение через window.Ya
        try {
            if (window.Ya && window.Ya._metrika && window.Ya._metrika.counters) {
                const counters = window.Ya._metrika.counters();
                if (counters && counters.length > 0) {
                    return counters[0].id;
                }
            }
        } catch (e) {
            // Продолжаем поиск
        }

        // Приоритет 4: глобальные объекты старых версий счётчика.
        // Читаем только имена ключей — значения не трогаем, чтобы не
        // споткнуться о геттеры, бросающие исключение.
        try {
            const keys = Object.keys(window);
            for (let i = 0; i < keys.length; i++) {
                if (/^yaCounter\d+$/.test(keys[i])) {
                    const digits = keys[i].match(/\d+/);
                    if (digits) return parseInt(digits[0], 10);
                }
            }
        } catch (e) {
            // Счетчик не найден
        }

        return null;
    }

    // Ждём, пока станут доступны и функция ym, и ID счётчика.
    // Проверять только `typeof ym !== 'undefined'` бессмысленно: сниппет
    // объявляет ym-заглушку сразу, до загрузки tag.js.
    function waitForCounter(callback, maxAttempts) {
        const limit = maxAttempts || 40; // 40 × 300мс ≈ 12 секунд
        let attempts = 0;

        const tryOnce = function () {
            const counterId = getMetrikaCounterId();
            if (counterId && typeof window.ym === 'function') {
                callback(counterId);
                return true;
            }
            return false;
        };

        if (tryOnce()) return;
        if (counterWaitTimer) return;

        counterWaitTimer = setInterval(function () {
            attempts++;
            if (tryOnce() || attempts >= limit) {
                clearInterval(counterWaitTimer);
                counterWaitTimer = null;
            }
        }, 300);
    }

    function flushGoals() {
        if (!metrikaAllowed || pendingGoals.length === 0) return;

        waitForCounter(function (counterId) {
            // ym-заглушка складывает вызовы в очередь и выполнит их после
            // загрузки tag.js, поэтому ждать саму загрузку не требуется
            pendingGoals.splice(0).forEach(function (goal) {
                try {
                    window.ym(counterId, 'reachGoal', goal);
                } catch (e) {
                    // Игнорируем ошибки отправки
                }
            });
        });
    }

    function trackGoal(goalName) {
        pendingGoals.push(goalName);
        flushGoals();
    }

    // ========== СОЗДАНИЕ ОКНА СОГЛАСИЯ ==========
    function createBanner() {
        const animationName = PREFIX + 'slideHint';
        const titleId = PREFIX + 'title';
        const descId = PREFIX + 'desc';
        const hintId = PREFIX + 'hint';
        const sliderId = PREFIX + 'slider';

        // Стили: keyframes, фокус-кольца, sr-only, reduced motion
        const style = document.createElement('style');
        style.id = PREFIX + 'styles';
        style.textContent = `
            @keyframes ${animationName} {
                0%   { transform: translateX(0); }
                10%  { transform: translateX(10px); }
                20%  { transform: translateX(0); }
                30%  { transform: translateX(10px); }
                40%  { transform: translateX(0); }
                100% { transform: translateX(0); }
            }
            #${sliderId}:focus-visible {
                outline: 3px solid ${CONFIG.ACCENT_COLOR} !important;
                outline-offset: 3px !important;
            }
            .${PREFIX}sr-only {
                position: absolute !important;
                width: 1px !important;
                height: 1px !important;
                margin: -1px !important;
                padding: 0 !important;
                overflow: hidden !important;
                clip: rect(0 0 0 0) !important;
                clip-path: inset(50%) !important;
                white-space: nowrap !important;
                border: 0 !important;
            }
            @media (prefers-reduced-motion: reduce) {
                #${sliderId} { animation: none !important; }
            }
        `;
        document.head.appendChild(style);

        // Оверлей
        const overlay = document.createElement('div');
        overlay.id = PREFIX + 'overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', titleId);
        overlay.setAttribute('aria-describedby', descId);
        overlay.style.cssText = `
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background: rgba(10, 10, 10, 0.55) !important;
            -webkit-backdrop-filter: blur(3px) !important;
            backdrop-filter: blur(3px) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            z-index: 2147483647 !important;
            margin: 0 !important;
            padding: 20px !important;
            border: none !important;
            box-sizing: border-box !important;
        `;

        // Контейнер
        const container = document.createElement('div');
        container.style.cssText = `
            background: #FFFFFF !important;
            border-radius: 22px !important;
            padding: 32px !important;
            box-shadow: 0 32px 64px rgba(10, 10, 10, 0.14) !important;
            max-width: 380px !important;
            width: 100% !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            border: 1px solid rgba(10, 10, 10, 0.06) !important;
        `;

        // Заголовок
        const title = document.createElement('h2');
        title.id = titleId;
        title.textContent = 'Сайт использует cookie';
        title.style.cssText = `
            margin: 0 0 10px 0 !important;
            padding: 0 !important;
            font-size: 22px !important;
            font-weight: 600 !important;
            letter-spacing: -0.02em !important;
            color: ${CONFIG.DARK_COLOR} !important;
            text-align: center !important;
            font-family: 'Geologica', 'Onest', system-ui, -apple-system, sans-serif !important;
            line-height: 1.2 !important;
            border: none !important;
            background: none !important;
        `;

        // Описание. Формулировка v1 («Продолжая пользоваться сайтом, вы даёте
        // согласие») описывала подразумеваемое согласие и противоречила самой
        // механике окна, где согласие даётся явным действием.
        const description = document.createElement('p');
        description.id = descId;
        const policyText = 'Политикой обработки персональных данных';
        const beforePolicy = 'Мы используем файлы cookie и обрабатываем персональные данные. Проведите слайдер вправо, чтобы дать согласие и подтвердить, что Вы ознакомлены с ';
        description.style.cssText = `
            margin: 0 0 24px 0 !important;
            padding: 0 !important;
            font-size: 14px !important;
            font-weight: 400 !important;
            color: #525252 !important;
            text-align: center !important;
            font-family: 'Onest', system-ui, -apple-system, sans-serif !important;
            line-height: 1.5 !important;
            border: none !important;
            background: none !important;
        `;
        description.appendChild(document.createTextNode(beforePolicy));
        if (CONFIG.POLICY_URL) {
            const policyLink = document.createElement('a');
            policyLink.href = CONFIG.POLICY_URL;
            policyLink.target = '_blank';
            policyLink.rel = 'noopener noreferrer';
            policyLink.textContent = policyText;
            policyLink.style.cssText = `
                color: #525252 !important;
                text-decoration: underline !important;
                text-underline-offset: 2px !important;
            `;
            description.appendChild(policyLink);
        } else {
            description.appendChild(document.createTextNode(policyText));
        }
        description.appendChild(document.createTextNode('.'));

        // Трек слайдера
        const track = document.createElement('div');
        track.style.cssText = `
            position: relative !important;
            width: 100% !important;
            height: ${TRACK_HEIGHT}px !important;
            background: #F5F5F5 !important;
            border-radius: 999px !important;
            overflow: hidden !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            box-shadow: inset 0 0 0 1.5px #D4D4D4 !important;
            transition: box-shadow 0.22s cubic-bezier(0.2,0.8,0.2,1) !important;
        `;

        // Прогресс-бар
        const progress = document.createElement('div');
        progress.style.cssText = `
            position: absolute !important;
            left: ${INSET}px !important;
            top: ${INSET}px !important;
            height: ${KNOB_SIZE}px !important;
            width: ${KNOB_SIZE}px !important;
            background: ${CONFIG.ACCENT_COLOR} !important;
            border-radius: 999px !important;
            transition: none !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            z-index: 2 !important;
        `;

        // Слайдер (кнопка). role="slider" + tabindex делают его доступным
        // с клавиатуры — в v1 согласие можно было дать только мышью/пальцем.
        const slider = document.createElement('div');
        slider.id = sliderId;
        slider.setAttribute('role', 'slider');
        slider.setAttribute('tabindex', '0');
        slider.setAttribute('aria-valuemin', '0');
        slider.setAttribute('aria-valuemax', '100');
        slider.setAttribute('aria-valuenow', '0');
        slider.setAttribute('aria-valuetext', '0 процентов');
        slider.setAttribute('aria-label', 'Проведите вправо, чтобы принять cookie');
        slider.setAttribute('aria-describedby', hintId);
        slider.style.cssText = `
            position: absolute !important;
            left: ${INSET}px !important;
            top: ${INSET}px !important;
            width: ${KNOB_SIZE}px !important;
            height: ${KNOB_SIZE}px !important;
            background: ${CONFIG.DARK_COLOR} !important;
            color: ${CONFIG.ACCENT_TEXT_COLOR} !important;
            border-radius: 50% !important;
            cursor: grab !important;
            box-shadow: 0 6px 18px rgba(10, 10, 10, 0.18), 0 1px 2px rgba(10, 10, 10, 0.12) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 20px !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            touch-action: none !important;
            transition: none !important;
            animation: ${animationName} 2s ease-out infinite !important;
            z-index: 10 !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            font-family: 'Onest', system-ui, -apple-system, sans-serif !important;
            line-height: ${KNOB_SIZE}px !important;
        `;

        const sliderIcon = document.createElement('span');
        sliderIcon.setAttribute('aria-hidden', 'true');
        sliderIcon.textContent = '→';
        slider.appendChild(sliderIcon);

        // Текст внутри трека — декоративный дубль, для скринридера скрыт
        const trackText = document.createElement('div');
        trackText.textContent = 'Проведите вправо для согласия';
        trackText.setAttribute('aria-hidden', 'true');
        trackText.style.cssText = `
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            height: ${TRACK_HEIGHT}px !important;
            line-height: ${TRACK_HEIGHT}px !important;
            text-align: center !important;
            color: #404040 !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            letter-spacing: -0.01em !important;
            pointer-events: none !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            padding: 0 0 0 46px !important;
            border: none !important;
            background: none !important;
            font-family: 'Onest', system-ui, -apple-system, sans-serif !important;
            z-index: 1 !important;
        `;

        // Подсказка по клавиатуре и live-region для статуса
        const hint = document.createElement('span');
        hint.id = hintId;
        hint.className = PREFIX + 'sr-only';
        hint.textContent = 'Стрелка вправо или клавиша End доводят слайдер до конца. Enter или пробел принимают cookie.';

        const liveRegion = document.createElement('span');
        liveRegion.className = PREFIX + 'sr-only';
        liveRegion.setAttribute('role', 'status');
        liveRegion.setAttribute('aria-live', 'polite');

        // Собираем трек
        track.appendChild(progress);
        track.appendChild(trackText);
        track.appendChild(slider);

        // Собираем контейнер
        container.appendChild(title);
        container.appendChild(description);
        container.appendChild(track);
        container.appendChild(hint);
        container.appendChild(liveRegion);

        overlay.appendChild(container);
        document.body.appendChild(overlay);

        // Цель «окно согласия показано». Пока Метрика заблокирована, цель
        // ждёт в очереди и уйдёт вместе с согласием.
        trackGoal('cookie_consent_shown');

        return { overlay, container, slider, sliderIcon, progress, track, trackText, style, liveRegion };
    }

    // ========== ИЗОЛЯЦИЯ ФОНА (a11y) ==========
    const backgroundState = {
        hidden: [],
        bodyOverflow: '',
        bodyPaddingRight: '',
        htmlOverflow: '',
        lastFocused: null,
        locked: false
    };

    function lockBackground(overlay) {
        backgroundState.lastFocused = document.activeElement;
        backgroundState.locked = true;

        // Скролл фона
        const scrollbar = window.innerWidth - document.documentElement.clientWidth;
        backgroundState.bodyOverflow = document.body.style.overflow;
        backgroundState.bodyPaddingRight = document.body.style.paddingRight;
        backgroundState.htmlOverflow = document.documentElement.style.overflow;
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';
        if (scrollbar > 0) {
            const current = parseFloat(window.getComputedStyle(document.body).paddingRight) || 0;
            document.body.style.paddingRight = (current + scrollbar) + 'px';
        }

        // Остальное содержимое страницы недоступно скринридеру и табу
        Array.prototype.slice.call(document.body.children).forEach(function (el) {
            if (el === overlay) return;
            backgroundState.hidden.push({
                el: el,
                ariaHidden: el.getAttribute('aria-hidden'),
                hadInert: el.hasAttribute('inert')
            });
            el.setAttribute('aria-hidden', 'true');
            el.setAttribute('inert', '');
        });
    }

    function unlockBackground() {
        if (!backgroundState.locked) return;
        backgroundState.locked = false;

        document.body.style.overflow = backgroundState.bodyOverflow;
        document.body.style.paddingRight = backgroundState.bodyPaddingRight;
        document.documentElement.style.overflow = backgroundState.htmlOverflow;

        backgroundState.hidden.splice(0).forEach(function (item) {
            if (item.ariaHidden === null) {
                item.el.removeAttribute('aria-hidden');
            } else {
                item.el.setAttribute('aria-hidden', item.ariaHidden);
            }
            if (!item.hadInert) {
                item.el.removeAttribute('inert');
            }
        });

        const last = backgroundState.lastFocused;
        if (last && typeof last.focus === 'function' && document.contains(last)) {
            try { last.focus({ preventScroll: true }); } catch (e) { try { last.focus(); } catch (e2) {} }
        }
        backgroundState.lastFocused = null;
    }

    // ========== ЗАКРЫТИЕ ОКНА ==========
    function dismissBanner(elements, teardown) {
        const overlay = elements.overlay;
        overlay.style.transition = 'opacity 0.3s ease';
        overlay.style.opacity = '0';

        if (typeof teardown === 'function') teardown();

        setTimeout(function () {
            overlay.remove();
            if (elements.style && elements.style.parentNode) {
                elements.style.remove();
            }
            unlockBackground();
        }, 300);
    }

    // ========== ЛОГИКА СЛАЙДЕРА ==========
    function initSlider(elements) {
        const { overlay, slider, sliderIcon, progress, track, trackText, liveRegion } = elements;

        const supportsPointer = typeof window.PointerEvent === 'function';

        let isDragging = false;
        let accepted = false;
        let activePointerId = null;
        let startX = 0;
        let currentX = 0;
        let maxDistance = 0;
        let knobSize = KNOB_SIZE;

        // --- Геометрия -----------------------------------------------------
        // v1 измерял трек один раз: после resize/поворота порог 90% съезжал.
        function measure() {
            knobSize = slider.offsetWidth || KNOB_SIZE;
            const available = track.offsetWidth - knobSize - INSET * 2;
            maxDistance = available > 0 ? available : 0;
        }

        function render() {
            setImportant(slider, 'left', (currentX + INSET) + 'px');
            setImportant(progress, 'width', (currentX + knobSize) + 'px');

            const ratio = maxDistance > 0 ? currentX / maxDistance : 0;
            setImportant(trackText, 'opacity', String(1 - ratio));

            const percent = Math.round(ratio * 100);
            slider.setAttribute('aria-valuenow', String(percent));
            slider.setAttribute('aria-valuetext', percent + ' процентов');
        }

        function resetPosition() {
            currentX = 0;
            render();
        }

        function onResize() {
            if (accepted) return;
            measure();
            resetPosition();
        }

        // --- Приём и отказ --------------------------------------------------
        function teardownListeners() {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onEnd);
            document.removeEventListener('touchmove', onMove);
            document.removeEventListener('touchend', onEnd);
            document.removeEventListener('touchcancel', onCancel);
            slider.removeEventListener('pointermove', onMove);
            slider.removeEventListener('pointerup', onEnd);
            slider.removeEventListener('pointercancel', onCancel);
            slider.removeEventListener('lostpointercapture', onCancel);
            window.removeEventListener('resize', onResize);
            window.removeEventListener('orientationchange', onResize);
            window.removeEventListener('blur', onCancel);
            document.removeEventListener('keydown', onDocumentKeydown, true);
            if (resizeObserver) {
                try { resizeObserver.disconnect(); } catch (e) {}
            }
        }

        function accept() {
            if (accepted) return;
            accepted = true;
            isDragging = false;

            currentX = maxDistance;
            render();

            sliderIcon.textContent = '✓';
            setImportant(slider, 'animation', 'none');
            setImportant(slider, 'cursor', 'default');
            setImportant(slider, 'background', CONFIG.DARK_COLOR);
            setImportant(slider, 'color', CONFIG.ACCENT_TEXT_COLOR);
            setImportant(track, 'box-shadow', 'inset 0 0 0 1.5px ' + CONFIG.DARK_COLOR);
            trackText.textContent = 'Согласие принято';
            setImportant(trackText, 'color', CONFIG.DARK_COLOR);
            setImportant(trackText, 'opacity', '1');
            slider.setAttribute('aria-disabled', 'true');
            liveRegion.textContent = 'Согласие принято';

            markConsentGiven();

            // Снимаем блокировку и дозагружаем счётчик Яндекс.Метрики
            unblockMetrika();
            metrikaAllowed = true;

            trackGoal('cookie_consent_accepted');

            setTimeout(function () {
                dismissBanner(elements, teardownListeners);
            }, 500);
        }

        // --- Указатель ------------------------------------------------------
        function clientXOf(e) {
            if (e.touches && e.touches.length) return e.touches[0].clientX;
            if (e.changedTouches && e.changedTouches.length) return e.changedTouches[0].clientX;
            if (typeof e.clientX === 'number') return e.clientX;
            return null;
        }

        function beginDrag(x) {
            isDragging = true;
            startX = x - currentX;
            setImportant(slider, 'cursor', 'grabbing');
            // Ключевое отличие от v1: анимация-подсказка действительно гаснет,
            // иначе её translateX дёргает кружок прямо под пальцем.
            setImportant(slider, 'animation', 'none');
            setImportant(slider, 'transition', 'none');
            setImportant(progress, 'transition', 'none');
        }

        function onStart(e) {
            if (accepted || isDragging) return;

            const x = clientXOf(e);
            if (x === null) return;

            if (e.pointerType === 'mouse' || e.type === 'mousedown') {
                if (e.button !== 0) return;
            }

            measure();
            if (maxDistance <= 0) return;

            // preventDefault гасит автоматическую фокусировку — ставим её сами,
            // чтобы после драга работали клавиатурные стрелки
            e.preventDefault();
            try { slider.focus({ preventScroll: true }); } catch (err) {}
            beginDrag(x);

            if (supportsPointer && e.pointerId !== undefined) {
                activePointerId = e.pointerId;
                try { slider.setPointerCapture(e.pointerId); } catch (err) {}
                slider.addEventListener('pointermove', onMove);
                slider.addEventListener('pointerup', onEnd);
                slider.addEventListener('pointercancel', onCancel);
                slider.addEventListener('lostpointercapture', onCancel);
            } else if (e.type === 'touchstart') {
                document.addEventListener('touchmove', onMove, { passive: false });
                document.addEventListener('touchend', onEnd);
                document.addEventListener('touchcancel', onCancel);
            } else {
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onEnd);
            }
        }

        function onMove(e) {
            if (!isDragging) return;
            if (activePointerId !== null && e.pointerId !== undefined && e.pointerId !== activePointerId) return;

            // Мышь отпустили за пределами окна — кнопок больше нет,
            // в v1 слайдер после такого тянулся за курсором «сам по себе»
            if (e.type === 'mousemove' && e.buttons === 0) {
                onCancel();
                return;
            }

            const x = clientXOf(e);
            if (x === null) return;

            if (e.cancelable) e.preventDefault();

            currentX = Math.max(0, Math.min(x - startX, maxDistance));
            render();
        }

        function releasePointer() {
            if (activePointerId !== null) {
                try { slider.releasePointerCapture(activePointerId); } catch (e) {}
                activePointerId = null;
            }
            slider.removeEventListener('pointermove', onMove);
            slider.removeEventListener('pointerup', onEnd);
            slider.removeEventListener('pointercancel', onCancel);
            slider.removeEventListener('lostpointercapture', onCancel);
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onEnd);
            document.removeEventListener('touchmove', onMove);
            document.removeEventListener('touchend', onEnd);
            document.removeEventListener('touchcancel', onCancel);
        }

        function restoreIdleStyles() {
            setImportant(slider, 'cursor', 'grab');
            setImportant(slider, 'transition', 'left 0.3s ease');
            setImportant(progress, 'transition', 'width 0.3s ease');
        }

        function onEnd() {
            if (!isDragging) return;
            isDragging = false;
            releasePointer();
            restoreIdleStyles();

            if (maxDistance > 0 && currentX >= maxDistance * ACCEPT_RATIO) {
                accept();
            } else {
                resetPosition();
            }
        }

        // Отмена жеста (системный жест, потеря окна, touchcancel):
        // возвращаем слайдер, ничего не засчитывая. В v1 после такого
        // isDragging оставался true и ломал прокрутку страницы.
        function onCancel() {
            if (!isDragging) return;
            isDragging = false;
            releasePointer();
            restoreIdleStyles();
            resetPosition();
        }

        // --- Клавиатура -----------------------------------------------------
        function nudge(deltaRatio) {
            if (accepted) return;
            measure();
            if (maxDistance <= 0) return;
            setImportant(slider, 'animation', 'none');
            setImportant(slider, 'transition', 'left 0.2s ease');
            setImportant(progress, 'transition', 'width 0.2s ease');
            currentX = Math.max(0, Math.min(currentX + maxDistance * deltaRatio, maxDistance));
            render();
            if (currentX >= maxDistance * ACCEPT_RATIO) {
                accept();
            }
        }

        function onSliderKeydown(e) {
            if (accepted) return;
            switch (e.key) {
                case 'ArrowRight':
                case 'ArrowUp':
                    e.preventDefault();
                    nudge(0.1);
                    break;
                case 'ArrowLeft':
                case 'ArrowDown':
                    e.preventDefault();
                    nudge(-0.1);
                    break;
                case 'Home':
                    e.preventDefault();
                    currentX = 0;
                    render();
                    break;
                case 'End':
                    e.preventDefault();
                    nudge(1);
                    break;
                case 'Enter':
                case ' ':
                case 'Spacebar':
                    e.preventDefault();
                    accept();
                    break;
                default:
                    break;
            }
        }

        // --- Фокус-ловушка ---------------------------------------------------
        function focusables() {
            return Array.prototype.slice.call(
                overlay.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')
            ).filter(function (el) {
                return el.offsetWidth > 0 || el.offsetHeight > 0 || el === document.activeElement;
            });
        }

        function onDocumentKeydown(e) {
            if (e.key !== 'Tab') return;
            const items = focusables();
            if (items.length === 0) return;

            const first = items[0];
            const last = items[items.length - 1];

            if (!overlay.contains(document.activeElement)) {
                e.preventDefault();
                first.focus();
                return;
            }
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }

        // --- Подписки --------------------------------------------------------
        measure();
        render();

        if (supportsPointer) {
            slider.addEventListener('pointerdown', onStart);
        } else {
            slider.addEventListener('mousedown', onStart);
            slider.addEventListener('touchstart', onStart, { passive: false });
        }

        slider.addEventListener('keydown', onSliderKeydown);
        window.addEventListener('resize', onResize);
        window.addEventListener('orientationchange', onResize);
        window.addEventListener('blur', onCancel);
        document.addEventListener('keydown', onDocumentKeydown, true);

        let resizeObserver = null;
        if (typeof window.ResizeObserver === 'function') {
            resizeObserver = new ResizeObserver(function () {
                if (!isDragging) onResize();
            });
            try { resizeObserver.observe(track); } catch (e) { resizeObserver = null; }
        }

        // Фокус внутрь модалки. Escape намеренно не обрабатываем:
        // случайное нажатие не должно трактоваться ни как согласие, ни как отказ.
        try { slider.focus({ preventScroll: true }); } catch (e) { try { slider.focus(); } catch (e2) {} }
    }

    // ========== ИНИЦИАЛИЗАЦИЯ ==========
    function start() {
        const elements = createBanner();
        lockBackground(elements.overlay);
        initSlider(elements);
    }

    function init() {
        if (!checkActivation()) return;

        // Согласие уже дано — окно не показываем
        if (isConsentGiven()) return;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
    }

    init();
})();
