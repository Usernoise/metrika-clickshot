(() => {
  "use strict";
  const script = document.currentScript;
  if (!script) return;
  try {
    const scriptUrl = new URL(script.src, location.href);
    const siteId = script.dataset.id || scriptUrl.searchParams.get("id") || "";
    if (!/^[a-zA-Z0-9_-]{2,64}$/.test(siteId)) return;
    const endpoint = `${scriptUrl.origin}/api/hit.php`;
    const bootstrap = window.__clickShotBootstrap;
    let collection = bootstrap && bootstrap.site === siteId ? bootstrap.collection : null;
    const consentEnabled = !!(bootstrap && bootstrap.site === siteId && bootstrap.slide_cookie?.enabled);
    const strictPreconsent = consentEnabled && bootstrap.slide_cookie.strict_preconsent !== false;
    const memory = new Map();
    const useMemory = () => strictPreconsent && !window.ClickShotConsent?.getState().analytics;
    const analyticsStorage = {
      getItem: key => useMemory() ? memory.get(key) ?? null : sessionStorage.getItem(key),
      setItem: (key, value) => useMemory() ? memory.set(key, value) : sessionStorage.setItem(key, value),
      removeItem: key => useMemory() ? memory.delete(key) : sessionStorage.removeItem(key),
      get length() { return useMemory() ? memory.size : sessionStorage.length; },
      key: index => useMemory() ? [...memory.keys()][index] ?? null : sessionStorage.key(index),
    };
    let configRequest;
    const inFlightVisits = new Set();
    const getCollection = () => {
      if (collection) return Promise.resolve(collection);
      if (!configRequest) {
        configRequest = fetch(`${scriptUrl.origin}/api/config.php?site=${encodeURIComponent(siteId)}`,
          {mode: "cors", credentials: "omit", cache: "no-store", referrerPolicy:"no-referrer"})
          .then(async response => {
            const data = await response.json();
            if (!response.ok || !data.collection) return null;
            collection = data.collection;
            return collection;
          }).catch(() => null);
      }
      return configRequest;
    };
    const post = async payload => {
      const body = JSON.stringify(payload);
      try {
        if (!consentEnabled && navigator.sendBeacon && navigator.sendBeacon(endpoint, new Blob([body], {type: "application/json"}))) {
          return true; // Queued by the browser; not a server delivery receipt.
        }
      } catch (_) {}
      try {
        const response = await fetch(endpoint, {method: "POST", mode: "cors", credentials: "omit",
          referrerPolicy: "no-referrer", keepalive: true, headers: {"Content-Type": "application/json"}, body});
        return response.ok;
      } catch (_) { return false; }
    };
    if (navigator.webdriver && !window.__clickShotAllowWebdriver
      && !["localhost", "127.0.0.1"].includes(location.hostname || "")) return;
    const VISIT_TIMEOUT_MS = 30 * 60 * 1000;
    const ENGAGED_AFTER_MS = 15000;
    const storagePrefix = `clickshot-metrika:${siteId}:`;
    // Holds the last activity time; the legacy value "1" means an open visit.
    const visitKey = `${storagePrefix}started`;
    const engagedKey = `${storagePrefix}engaged`;
    const goalPrefix = `${storagePrefix}event:`;
    const isNewVisit = key => {
      try {
        const raw = analyticsStorage.getItem(key);
        if (raw === "1") return false;
        return key !== visitKey || !(Date.now() - Number(raw) <= VISIT_TIMEOUT_MS);
      } catch (_) { return true; }
    };
    // Goal and engagement markers belong to one visit, so goal conversion and
    // bounces are counted per visit rather than per tab session.
    const resetVisitState = () => {
      try {
        for (let i = analyticsStorage.length - 1; i >= 0; i--) {
          const key = analyticsStorage.key(i);
          if (key === engagedKey || (key && key.startsWith(goalPrefix))) analyticsStorage.removeItem(key);
        }
      } catch (_) {}
    };
    // The visit's first pageview counts a bounce on the server; the first later
    // activity cancels it. The in-flight flag keeps concurrent pageviews, goals
    // and the engagement timer from cancelling it twice; the marker is stored
    // only once a report has been queued.
    let engagementInFlight = false;
    const claimEngagement = () => {
      if (engagementInFlight) return false;
      try {
        if (analyticsStorage.getItem(engagedKey) === "1") return false;
      } catch (_) { return false; }
      return engagementInFlight = true;
    };
    const settleEngagement = queued => {
      engagementInFlight = false;
      if (queued) try { analyticsStorage.setItem(engagedKey, "1"); } catch (_) {}
    };
    const safePath = value => {
      const parts = String(value || '/').split('/');
      return parts.map((raw, index) => {
        let part = raw;
        try { for (let i=0;i<3;i++) { const decoded=decodeURIComponent(part); if(decoded===part) break; part=decoded; } }
        catch (_) { return ':id'; }
        if (/[\\/%@\u0000-\u001f]/.test(part) || /\d{5,}/.test(part)
          || /^[a-f0-9]{8}-[a-f0-9-]{27}$/i.test(part)
          || /^[A-Za-z0-9_-]{24,}$/.test(part)
          || /^(?:token|reset-password|access-token|email|phone)$/i.test(parts[index-1] || '')) return ':id';
        return part;
      }).join('/').slice(0,500);
    };
    const safeReferrer = () => {
      try { const url = new URL(document.referrer); return /^https?:$/.test(url.protocol) ? url.origin + '/' : ''; }
      catch (_) { return ''; }
    };
    const utmParams = () => {
      const utm = {};
      try {
        const params = new URL(location.href).searchParams;
        for (const name of ["utm_source", "utm_medium", "utm_campaign"]) {
          const value = (params.get(name) || "").trim();
          if (/^[a-zA-Z0-9_.-]{1,64}$/.test(value)) utm[name] = value;
        }
      } catch (_) {}
      return utm;
    };
    // ClickShot aggregates, including banner goals, are independent of Yandex consent.
    const send = async (name = "") => {
      const settings = await getCollection();
      if (!settings) return false;
      const key = name ? goalPrefix + name : visitKey;
      const newVisit = !!settings.visits && isNewVisit(key) && !inFlightVisits.has(key);
      if (newVisit) {
        inFlightVisits.add(key);
        if (!name) resetVisitState();
      }
      const engaged = !!settings.visits && !(newVisit && !name) && claimEngagement();
      const payload = {site: siteId, newVisit};
      if (name) payload.event = name;
      else {
        if (settings.pages) payload.path = safePath(location.pathname);
        if (settings.referrers) Object.assign(payload, utmParams());
      }
      if (engaged) payload.engaged = true;
      if (settings.referrers) payload.referrer = safeReferrer();
      const queued = await post(payload);
      if (settings.visits && queued) {
        try { analyticsStorage.setItem(key, name ? "1" : String(Date.now())); } catch (_) {}
      }
      if (engaged) settleEngagement(queued);
      if (newVisit) inFlightVisits.delete(key);
      return queued;
    };
    let counterStarted = false;
    window.ClickShotMetrika = window.ClickShotMetrika || {};
    window.ClickShotMetrika.goal = name => {
      if (typeof name !== "string" || !/^[a-z][a-z0-9_]{1,63}$/.test(name)) return Promise.resolve(false);
      if (!counterStarted) {
        (window.__clickShotPendingGoals = window.__clickShotPendingGoals || []).push(name);
        return Promise.resolve(true);
      }
      return send(name).catch(() => false);
    };
    // Existing Yandex Metrika targets become ClickShot events without adding a
    // second call at every conversion point. The original ym call and return
    // value are preserved; only a valid reachGoal command is observed.
    const wrappedYm = new WeakSet();
    const bridgeYandexGoal = args => {
      if (window.__clickShotSuppressYandexGoalBridge) return;
      const [, command, goal] = args;
      if (command === "reachGoal" && typeof goal === "string") {
        window.ClickShotMetrika.goal(goal);
      }
    };
    const makeWrappedYm = original => {
      const wrapped = function (...args) {
        const result = original.apply(this, args);
        try { bridgeYandexGoal(args); } catch (_) {}
        return result;
      };
      wrappedYm.add(wrapped);
      return wrapped;
    };
    const wrapYm = () => {
      const original = window.ym;
      if (typeof original !== "function" || wrappedYm.has(original)) return;
      try { window.ym = makeWrappedYm(original); } catch (_) {}
    };
    // When ClickShot loads before the standard Metrika snippet, capture the
    // snippet's assignment synchronously. A short polling fallback covers
    // non-configurable global properties and tag.js replacing its queue.
    const ymDescriptor = Object.getOwnPropertyDescriptor(window, "ym");
    if ((!ymDescriptor || "value" in ymDescriptor) && (!ymDescriptor || ymDescriptor.configurable)) {
      let ymValue = window.ym;
      const assignYm = value => {
        ymValue = typeof value === "function" && !wrappedYm.has(value) ? makeWrappedYm(value) : value;
      };
      try {
        Object.defineProperty(window, "ym", {
          configurable: true,
          enumerable: ymDescriptor ? ymDescriptor.enumerable : true,
          get: () => ymValue,
          set: assignYm,
        });
        assignYm(ymValue);
      } catch (_) { wrapYm(); }
    } else {
      wrapYm();
    }
    // The standard Metrika snippet can replace its queue function when tag.js
    // loads, so observe the short startup window and wrap each replacement.
    wrapYm();
    if (typeof setInterval === "function" && typeof setTimeout === "function") {
      const watcher = setInterval(wrapYm, 250);
      setTimeout(() => {
        clearInterval(watcher);
        // Consent may arrive much later: preserve its assignment hook without polling.
        if (consentEnabled) return;
        // Return a normal writable global after the Metrika startup period.
        try {
          const current = window.ym;
          Object.defineProperty(window, "ym", {configurable: true, enumerable: true, writable: true, value: current});
        } catch (_) {}
      }, 30000);
    }
    if (consentEnabled) window.addEventListener('clickshot:consent', event => {
      if (event.detail.analytics) return;
      memory.clear();
      try {
        Object.keys(sessionStorage).filter(key => key.startsWith(storagePrefix)).forEach(key => sessionStorage.removeItem(key));
      } catch (_) {}
    });
    let engagedTimer = null;
    const scheduleEngagement = () => {
      if (typeof setTimeout !== "function") return;
      clearTimeout(engagedTimer);
      engagedTimer = setTimeout(async () => {
        const settings = await getCollection();
        if (!settings || !settings.visits) return;
        if (document.visibilityState === "hidden") {
          // Time in a background tab is not engagement; wait for the user to return.
          document.addEventListener("visibilitychange", scheduleEngagement, {once: true});
          return;
        }
        // A ping without newVisit is the server's signal for engagement only.
        if (claimEngagement()) settleEngagement(await post({site: siteId, engaged: true}));
      }, ENGAGED_AFTER_MS);
    };

    // One pageview per distinct path: the initial load and SPA route changes.
    let trackedPath = null;
    const trackPageview = () => {
      const path = location.pathname || "/";
      if (path === trackedPath) return;
      trackedPath = path;
      send().catch(() => {});
      scheduleEngagement();
    };
    // Goals queued before load go after the pageview, so they cannot report
    // engagement ahead of the visit that they belong to.
    const start = () => {
      counterStarted = true;
      trackPageview();
      (window.__clickShotPendingGoals || []).splice(0, 100).forEach(name => window.ClickShotMetrika.goal(name));
    };
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start, {once: true});
    else start();
    try {
      for (const method of ["pushState", "replaceState"]) {
        const original = history[method];
        if (typeof original !== "function") continue;
        history[method] = function (...args) {
          const result = original.apply(this, args);
          try { trackPageview(); } catch (_) {}
          return result;
        };
      }
      window.addEventListener("popstate", trackPageview);
    } catch (_) {}
  } catch (_) {}
})();
