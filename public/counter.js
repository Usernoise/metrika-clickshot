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
    let configRequest;
    const inFlightVisits = new Set();
    const getCollection = () => {
      if (collection) return Promise.resolve(collection);
      if (!configRequest) {
        configRequest = fetch(`${scriptUrl.origin}/api/config.php?site=${encodeURIComponent(siteId)}`,
          {mode: "cors", credentials: "omit", cache: "no-store"})
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
        if (navigator.sendBeacon && navigator.sendBeacon(endpoint, new Blob([body], {type: "application/json"}))) {
          return true; // Queued by the browser; not a server delivery receipt.
        }
      } catch (_) {}
      try {
        const response = await fetch(endpoint, {method: "POST", mode: "cors", credentials: "omit",
          keepalive: true, headers: {"Content-Type": "application/json"}, body});
        return response.ok;
      } catch (_) { return false; }
    };
    // ClickShot aggregates, including banner goals, are independent of Yandex consent.
    const send = async (name = "") => {
      const settings = await getCollection();
      if (!settings) return false;
      const key = `clickshot-metrika:${siteId}:${name ? `event:${name}` : "started"}`;
      let newVisit = false;
      if (settings.visits) {
        try { newVisit = sessionStorage.getItem(key) !== "1"; }
        catch (_) { newVisit = true; }
        newVisit = newVisit && !inFlightVisits.has(key);
      }
      if (newVisit) inFlightVisits.add(key);
      const payload = {site: siteId, newVisit};
      if (name) payload.event = name;
      else if (settings.pages) payload.path = location.pathname || "/";
      if (settings.referrers) payload.referrer = document.referrer || "";
      const queued = await post(payload);
      if (newVisit) {
        if (queued) {
          try { sessionStorage.setItem(key, "1"); } catch (_) {}
        }
        inFlightVisits.delete(key);
      }
      return queued;
    };
    window.ClickShotMetrika = window.ClickShotMetrika || {};
    window.ClickShotMetrika.goal = name => {
      if (typeof name !== "string" || !/^[a-z][a-z0-9_]{1,63}$/.test(name)) return Promise.resolve(false);
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
        // Return a normal writable global after the Metrika startup period.
        try {
          const current = window.ym;
          Object.defineProperty(window, "ym", {configurable: true, enumerable: true, writable: true, value: current});
        } catch (_) {}
      }, 30000);
    }
    (window.__clickShotPendingGoals || []).splice(0, 100).forEach(name => window.ClickShotMetrika.goal(name));
    const pageview = () => { send().catch(() => {}); };
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", pageview, {once: true});
    else pageview();
  } catch (_) {}
})();
