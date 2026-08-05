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
    const key = `clickshot-metrika:${siteId}:started`;
    let newVisit = true;

    try {
      newVisit = sessionStorage.getItem(key) !== "1";
      sessionStorage.setItem(key, "1");
    } catch (_) {
      newVisit = true;
    }

    const payload = JSON.stringify({
      site: siteId,
      path: location.pathname || "/",
      referrer: document.referrer || "",
      newVisit
    });

    const send = () => {
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
