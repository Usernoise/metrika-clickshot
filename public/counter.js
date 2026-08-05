(() => {
  "use strict";

  const script = document.currentScript;
  if (!script) return;

  let siteId = script.dataset.id || "";

  try {
    const scriptUrl = new URL(script.src, location.href);
    siteId = siteId || scriptUrl.searchParams.get("id") || "";
    if (!siteId) return;

    const endpoint = `${scriptUrl.origin}/api/hit.php`;
    const configEndpoint = `${scriptUrl.origin}/api/config.php?site=${encodeURIComponent(siteId)}`;
    const bootstrap = window.__clickShotBootstrap;

    const send = async () => {
      let collection = bootstrap && bootstrap.site === siteId ? bootstrap.collection : null;
      if (!collection) {
        try {
          const response = await fetch(configEndpoint, {mode: "cors", credentials: "omit", cache: "no-store"});
          const config = await response.json();
          if (!response.ok || !config || !config.collection) return;
          collection = config.collection;
        } catch (_) { return; }
      }

      let newVisit = false;
      if (collection.visits) {
        const key = `clickshot-metrika:${siteId}:started`;
        try {
          newVisit = sessionStorage.getItem(key) !== "1";
          sessionStorage.setItem(key, "1");
        } catch (_) {
          newVisit = true;
        }
      }

      const event = { site: siteId, newVisit };
      if (collection.pages) event.path = location.pathname || "/";
      if (collection.referrers) event.referrer = document.referrer || "";
      const payload = JSON.stringify(event);

      if (navigator.sendBeacon) {
        navigator.sendBeacon(
          endpoint,
          new Blob([payload], { type: "application/json" })
        );
      } else {
        fetch(endpoint, {
          method: "POST",
          mode: "cors",
          credentials: "omit",
          keepalive: true,
          headers: { "Content-Type": "application/json" },
          body: payload
        }).catch(() => {});
      }
    };

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", send, { once: true });
    } else {
      send();
    }
  } catch (_) {}
})();
