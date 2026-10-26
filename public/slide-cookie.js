/** Slide Cookie v3. One optional purpose: analytics. No pre-consent Yandex queue. */
(function () {
    'use strict';
    if (window.__slideCookieV3) return;
    window.__slideCookieV3 = true;
    const script = document.currentScript;
    const CONFIG = Object.assign({ACCENT_COLOR:'#C5FF1A', DARK_COLOR:'#0A0A0A', ACCENT_TEXT_COLOR:'#C5FF1A', UTM_PARAM:'always', UTM_KEY:''}, window.__clickShotSlideCookieConfig || {});
    CONFIG.POLICY_URL = safeUrl(CONFIG.POLICY_URL);
    CONFIG.STORAGE_KEY = CONFIG.STORAGE_KEY || 'clickshot:cookie-choice';
    const endpoint = CONFIG.SITE_ID && script && script.src
        ? new URL('/api/consent.php?site=' + encodeURIComponent(CONFIG.SITE_ID), script.src).href : '';
    const PREFIX = 'cc-' + Date.now() + '-';
    const INSET = 5, TRACK_HEIGHT = 60, KNOB_SIZE = 50, ACCEPT_RATIO = 0.9;
    let choice = null, verified = false, sequence = 0, activeBanner = null, settingsLink = null;
    let sdkStarted = false, expiryTimer = null, pendingRequest = false;
    const domReady = document.readyState === 'loading'
        ? new Promise(resolve => document.addEventListener('DOMContentLoaded', resolve, {once:true})) : Promise.resolve();

    function setImportant(el, prop, value) { el.style.setProperty(prop, value, 'important'); }
    function safeUrl(value) {
        if (!value || typeof value !== 'string' || /[\s\\]/.test(value) || value.startsWith('//')) return '';
        return /^(?:https?:\/\/[^/]+|\/(?!\/)[^?#]+|\.{1,2}\/[^?#]+)/i.test(value) ? value : '';
    }
    function checkActivation() {
        return (CONFIG.UTM_PARAM === 'always' && !CONFIG.UTM_KEY)
            || new URLSearchParams(location.search).get(CONFIG.UTM_PARAM) === CONFIG.UTM_KEY;
    }
    function readChoice() {
        try {
            const value = JSON.parse(localStorage.getItem(CONFIG.STORAGE_KEY));
            if (value && value.format === 1 && ['granted','denied','withdrawn'].includes(value.decision)) return value;
        } catch (_) {}
        return null;
    }
    function current(value) {
        return value && CONFIG.DOCUMENT && value.version === CONFIG.DOCUMENT.version
            && value.hash === CONFIG.DOCUMENT.hash && Number.isFinite(value.expires_at) && value.expires_at > Date.now();
    }
    function allowed() { return verified && choice && choice.decision === 'granted' && current(choice); }
    function store(value) {
        choice = value;
        try { localStorage.setItem(CONFIG.STORAGE_KEY, JSON.stringify(value)); } catch (_) {}
        publish();
    }
    function publish() {
        if (settingsLink) settingsLink.textContent = 'Настройки cookie';
        window.dispatchEvent(new CustomEvent('clickshot:consent', {detail: {decision:choice?.decision || 'unknown', analytics:!!allowed()}}));
        clearTimeout(expiryTimer);
        if (allowed()) expiryTimer = setTimeout(checkExpiry, Math.min(choice.expires_at - Date.now(), 2147483647));
    }
    function checkExpiry() {
        if (current(choice)) { publish(); return; }
        verified = false;
        const reload = stopAnalytics();
        publish();
        if (reload) location.reload();
        else domReady.then(() => start());
    }
    async function request(action, receipt = '', method = 'link') {
        if (!endpoint) throw new Error('Разрешение аналитики временно недоступно. Можно продолжить без неё.');
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 6000);
        try {
            const response = await fetch(endpoint, {method:'POST', mode:'cors', credentials:'omit', cache:'no-store',
                referrerPolicy:'no-referrer', signal:controller.signal, headers:{'Content-Type':'application/json'},
                body:JSON.stringify({action, receipt, method, version:CONFIG.DOCUMENT?.version, hash:CONFIG.DOCUMENT?.hash})});
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'Не удалось сохранить выбор. Можно продолжить без аналитики.');
            return data;
        } finally { clearTimeout(timeout); }
    }
    function trackGoal(name) {
        // Banner aggregates stay in ClickShot. Nothing is replayed into Yandex.
        if (window.ClickShotMetrika?.goal) window.ClickShotMetrika.goal(name);
        else (window.__clickShotPendingGoals = window.__clickShotPendingGoals || []).push(name);
    }
    function startAnalytics() {
        if (!allowed() || sdkStarted || !CONFIG.READY) return;
        unblockMetrika();
        sdkStarted = true;
        // Drop commands queued before opt-in, including legacy init options/goals.
        if (window.ym && Array.isArray(window.ym.a)) window.ym.a.length = 0;
        if (typeof window.ym !== 'function') {
            window.ym = function () { (window.ym.a = window.ym.a || []).push(arguments); };
            window.ym.l = Date.now();
        }
        window.ym(CONFIG.YM_COUNTER, 'init', {webvisor:false, clickmap:false, trackLinks:false,
            accurateTrackBounce:false, ecommerce:false, trackHash:false, sendTitle:false, disableYtm:true});
        const tag = document.createElement('script');
        tag.async = true;
        tag.dataset.clickshotMetrika = 'true';
        tag.referrerPolicy = 'no-referrer';
        tag.src = 'https://mc.yandex.ru/metrika/tag.js?id=' + encodeURIComponent(CONFIG.YM_COUNTER);
        document.head.appendChild(tag);
    }
    function cleanYandexStorage() {
        // Only accessible first-party values; never claim to delete vendor data.
        const paths = new Set(['/']);
        let path = '';
        for (const part of location.pathname.split('/').filter(Boolean)) { path += '/' + part; paths.add(path); paths.add(path + '/'); }
        const domains = ['', location.hostname];
        const labels = location.hostname.split('.');
        for (let i = 0; i < labels.length - 1; i++) domains.push('.' + labels.slice(i).join('.'));
        let cookies = '';
        try { cookies = document.cookie; } catch (_) {}
        for (const raw of cookies.split(';')) {
            const name = raw.trim().split('=')[0];
            if (!/^_ym/.test(name)) continue;
            for (const cookiePath of paths) for (const domain of domains) {
                try { document.cookie = name + '=; Max-Age=0; Path=' + cookiePath + (domain ? '; Domain=' + domain : '') + '; SameSite=Lax'; } catch (_) {}
            }
        }
        for (const storage of ['localStorage','sessionStorage']) {
            try {
                const target = window[storage];
                Object.keys(target).filter(key => /^_ym/.test(key)).forEach(key => target.removeItem(key));
            } catch (_) {}
        }
    }
    function stopAnalytics() {
        metrikaAllowed = false;
        const wasStarted = sdkStarted;
        sdkStarted = false;
        if (wasStarted) {
            try { window.ym(CONFIG.YM_COUNTER, 'destruct'); } catch (_) {}
        }
        if (window.ym && Array.isArray(window.ym.a)) window.ym.a.length = 0;
        document.querySelectorAll('script[data-clickshot-metrika]').forEach(el => el.remove());
        cleanYandexStorage();
        return wasStarted;
    }
    async function grant(method) {
        if (pendingRequest) return false;
        if (!CONFIG.READY) throw new Error('Разрешение аналитики временно недоступно. Можно продолжить без неё.');
        const turn = ++sequence;
        pendingRequest = true;
        try {
            const data = await request('granted', choice?.pending_receipt || choice?.receipt || '', method);
            if (turn !== sequence) {
                await request('withdrawn', data.choice.receipt).catch(() => {});
                return false;
            }
            verified = true;
            store(data.choice);
            startAnalytics();
            return true;
        } finally { pendingRequest = false; }
    }
    async function reject() {
        ++sequence;
        const receipt = choice?.pending_receipt || choice?.receipt || '';
        const action = choice?.decision === 'granted' || choice?.pending_receipt ? 'withdrawn' : 'denied';
        verified = false;
        const reload = stopAnalytics();
        // Refusal takes effect locally even if the evidence service is offline.
        store({format:1, decision:action, categories:{analytics:false}, version:CONFIG.DOCUMENT?.version,
            hash:CONFIG.DOCUMENT?.hash, created_at:Date.now(), expires_at:Date.now() + (CONFIG.DOCUMENT?.days || 180) * 86400000,
            pending_receipt:receipt, pending:true});
        trackGoal(action === 'withdrawn' ? 'cookie_consent_withdrawn' : 'cookie_consent_denied');
        closeBanner();
        try {
            const data = await request(action, receipt);
            if (choice?.pending && choice.pending_receipt === receipt) store(data.choice);
        } catch (_) { /* The persisted pending revocation is retried on next load. */ }
        if (reload) location.reload();
    }
    function makeLink(text, fn) {
        const link = document.createElement('a');
        link.href = '#clickshot-cookie-settings';
        link.dataset.clickshotLink = 'true';
        link.textContent = text;
        link.style.cssText = 'font:inherit!important;font-size:13px!important;line-height:1.6!important;color:#525252!important;text-decoration:underline!important;text-underline-offset:3px!important;background:none!important;border:0!important;box-shadow:none!important;cursor:pointer!important;opacity:1!important;display:inline-block!important;padding:2px 0!important;';
        link.addEventListener('click', event => { event.preventDefault(); fn(); });
        return link;
    }
    function closeBanner() {
        if (!activeBanner || activeBanner.closing) return;
        activeBanner.closing = true;
        dismissBanner(activeBanner, activeBanner.teardown);
    }
    async function restore() {
        const turn = sequence;
        choice = readChoice();
        if (choice?.pending) {
            try {
                const data = await request(choice.decision, choice.pending_receipt || '');
                if (turn === sequence) store(data.choice);
            } catch (_) {}
        } else if (choice?.decision === 'granted' && current(choice)) {
            try {
                const data = await request('verify', choice.receipt || '');
                if (turn === sequence && data.valid) { verified = true; startAnalytics(); }
            } catch (_) {}
        }
        if (turn !== sequence) return;
        publish();
        await domReady;
        if (turn !== sequence) return;
        if (current(choice) && (choice.decision !== 'granted' || verified)) closeBanner();
        else if (checkActivation()) start();
    }

    let metrikaBlockInstalled = false;
    let originalCreateElement = null;
    let cachedSrcDescriptor = null;
    let srcDescriptorResolved = false;

    function isMetrikaSrc(value) {
        if (!value) return false;
        try {
            const url = new URL(String(value), location.href);
            return /^(?:mc\.yandex\.(?:ru|com)|mc\.webvisor\.(?:org|com))$/i.test(url.hostname);
        } catch (_) { return false; }
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
        // Preserve the original method and receiver for third-party DOM code.
        originalCreateElement = document.createElement;

        document.createElement = function (tagName, options) {
            const element = originalCreateElement.call(this, tagName, options);
            try {
                if (typeof tagName === 'string' && tagName.toLowerCase() === 'script') {
                    const originalSetAttribute = element.setAttribute;
                    element.setAttribute = function (name, value) {
                        if (String(name).toLowerCase() === 'src') this.src = value;
                        else originalSetAttribute.call(this, name, value);
                    };
                    // Подменяем свойство src у создаваемого скрипта
                    Object.defineProperty(element, 'src', {
                        configurable: true,
                        enumerable: true,
                        get() {
                            return srcDescriptor.get.call(this);
                        },
                        set(value) {
                            if (!metrikaAllowed && isMetrikaSrc(value)) return;
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

    function unblockMetrika() {
        metrikaAllowed = true;
        // ClickShot initializes its own counter; legacy snippets are not replayed.
    }

    let metrikaAllowed = false;
    installMetrikaBlock();
    if (typeof window.ym !== 'function') {
        window.ym = function () {
            if (metrikaAllowed) (window.ym.a = window.ym.a || []).push(arguments);
        };
        window.ym.l = Date.now();
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
            padding: 28px !important;
            max-height: 88vh !important;
            overflow-y: auto !important;
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

        const description = document.createElement('p');
        description.id = descId;
        description.style.cssText = "margin:0 0 16px!important;font:14px/1.5 system-ui,sans-serif!important;color:#525252!important;text-align:center!important;";
        description.textContent = CONFIG.READY
            ? (CONFIG.DOCUMENT?.operator ? CONFIG.DOCUMENT.operator + ' использует Яндекс.Метрику для анализа посещаемости. ' : '')
                + 'Аналитика включится с вашего согласия. Проведите слайдер вправо, чтобы разрешить её. Отказ не ограничивает доступ к сайту.'
            : 'Разрешение аналитики временно недоступно. Вы можете продолжить пользоваться сайтом без неё.';
        const documents = document.createElement('div');
        documents.style.cssText = 'margin:0 0 18px!important;text-align:center!important;font:13px/1.6 system-ui,sans-serif!important;';
        const consentContent = document.createElement('div');
        consentContent.id = PREFIX + 'document';
        consentContent.hidden = true;
        consentContent.tabIndex = 0;
        consentContent.style.cssText = 'white-space:pre-wrap!important;max-height:220px!important;overflow:auto!important;text-align:left!important;font:12px/1.6 system-ui,sans-serif!important;color:#404040!important;margin-top:12px!important;';
        consentContent.textContent = CONFIG.DOCUMENT?.text || '';
        if (CONFIG.READY) {
            const consentLink = makeLink('Согласие на аналитику', () => {
                consentContent.hidden = !consentContent.hidden;
                consentLink.setAttribute('aria-expanded', String(!consentContent.hidden));
            });
            consentLink.setAttribute('aria-expanded', 'false');
            consentLink.setAttribute('aria-controls', consentContent.id);
            documents.appendChild(consentLink);
        }
        if (CONFIG.POLICY_URL) {
            if (documents.childNodes.length) documents.appendChild(document.createTextNode(' · '));
            const policy = makeLink('Политика обработки данных', () => {});
            // A document link navigates normally; it never confirms policy acceptance.
            const policyLink = policy.cloneNode(true);
            policyLink.href = CONFIG.POLICY_URL;
            policyLink.target = '_blank';
            policyLink.rel = 'noopener noreferrer';
            policyLink.addEventListener('click', () => trackGoal('cookie_policy_opened'));
            documents.appendChild(policyLink);
        }
        documents.appendChild(consentContent);

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
        slider.setAttribute('aria-label', 'Проведите вправо, чтобы разрешить аналитику');
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
        trackText.textContent = 'Сдвиньте для согласия';
        if (!CONFIG.READY) { track.hidden = true; setImportant(track, 'display', 'none'); }
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
        container.appendChild(documents);
        container.appendChild(track);
        container.appendChild(hint);
        container.appendChild(liveRegion);
        const refusal = document.createElement('div');
        refusal.style.cssText = 'margin:16px 0 0!important;text-align:center!important;font:13px/1.6 system-ui,sans-serif!important;';
        const rejectLink = makeLink(allowed() ? 'Отозвать согласие' : 'Продолжить без аналитики', () => { reject(); });
        refusal.appendChild(rejectLink);
        container.appendChild(refusal);
        const errorMessage = document.createElement('p');
        errorMessage.setAttribute('role', 'alert');
        errorMessage.style.cssText = 'margin:12px 0 0!important;font:13px/1.5 system-ui,sans-serif!important;color:#9f2929!important;text-align:center!important;';
        container.appendChild(errorMessage);

        overlay.appendChild(container);
        document.body.appendChild(overlay);

        // Only anonymous ClickShot banner aggregates; never sent to Yandex.
        trackGoal('cookie_consent_shown');

        return { overlay, container, slider, sliderIcon, progress, track, trackText, style, liveRegion, rejectLink, errorMessage };
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
            if (activeBanner === elements) activeBanner = null;
        }, 300);
    }

    // ========== ЛОГИКА СЛАЙДЕРА ==========
    function initSlider(elements) {
        const { overlay, slider, sliderIcon, progress, track, trackText, liveRegion } = elements;

        const supportsPointer = typeof window.PointerEvent === 'function';

        let isDragging = false;
        let accepted = false;
        let startedTracked = false;
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

        async function accept(method = 'slider') {
            if (accepted || !CONFIG.READY || pendingRequest || elements.closing) return;
            isDragging = false;
            slider.setAttribute('aria-disabled', 'true');
            elements.errorMessage.textContent = '';
            trackText.textContent = 'Сохраняем выбор…';
            try {
                if (!await grant(method)) return;
                if (elements.closing) return;
                accepted = true;
                currentX = maxDistance;
                render();
                sliderIcon.textContent = '✓';
                setImportant(slider, 'animation', 'none');
                trackText.textContent = 'Согласие принято';
                liveRegion.textContent = 'Согласие принято';
                trackGoal('cookie_consent_accepted');
                closeBanner();
            } catch (error) {
                elements.errorMessage.textContent = error.message || 'Не удалось сохранить согласие. Можно продолжить без аналитики.';
                resetPosition();
            } finally {
                if (!accepted) {
                    slider.setAttribute('aria-disabled', 'false');
                    trackText.textContent = 'Сдвиньте для согласия';
                }
            }
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
            if (accepted || isDragging || pendingRequest || !CONFIG.READY) return;

            if (!startedTracked) {
                startedTracked = true;
                trackGoal('cookie_consent_started');
            }

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
                accept('keyboard');
            }
        }

        function onSliderKeydown(e) {
            if (accepted || pendingRequest) return;
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
                    accept('keyboard');
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

        elements.teardown = teardownListeners;
        // Фокус внутрь модалки. Escape намеренно не обрабатываем:
        // случайное нажатие не должно трактоваться ни как согласие, ни как отказ.
        const firstControl = CONFIG.READY ? slider : elements.rejectLink;
        try { firstControl.focus({ preventScroll: true }); } catch (_) { firstControl.focus(); }
    }

    function start() {
        if (activeBanner) return;
        const elements = createBanner();
        activeBanner = elements;
        lockBackground(elements.overlay);
        initSlider(elements);
    }
    window.ClickShotConsent = {
        open: () => domReady.then(start),
        withdraw: reject,
        getState: () => ({decision:choice?.decision || 'unknown', analytics:!!allowed()})
    };
    domReady.then(() => {
        const style = document.createElement('style');
        style.textContent = '[data-clickshot-link]:focus-visible{outline:2px solid #525252!important;outline-offset:4px!important;}';
        document.head.appendChild(style);
        settingsLink = makeLink('Настройки cookie', start);
        settingsLink.dataset.clickshotSettings = 'true';
        settingsLink.style.cssText += 'position:fixed!important;bottom:12px!important;left:16px!important;z-index:2147483645!important;background:#fff!important;padding:2px 4px!important;font-family:system-ui,sans-serif!important;';
        document.body.appendChild(settingsLink);
    });
    window.addEventListener('storage', event => {
        if (event.key !== CONFIG.STORAGE_KEY && event.key !== null) return;
        ++sequence;
        verified = false;
        const reload = stopAnalytics();
        choice = readChoice();
        publish();
        if (reload) location.reload();
        else restore();
    });
    window.addEventListener('pagehide', event => {
        if (event.persisted) { verified = false; stopAnalytics(); }
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted) { ++sequence; restore(); }
    });
    restore();
})();
