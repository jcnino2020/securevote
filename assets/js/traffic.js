/* First-party link analytics: paths and external hostnames only. */
(() => {
  const config = document.getElementById('traffic-config');
  if (!config || !navigator.sendBeacon) return;
  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented) return;
    const raw = link.getAttribute('href');
    if (!raw || /^(?:javascript:|mailto:|tel:)/i.test(raw)) return;
    let destination;
    try { destination = new URL(raw, window.location.href); } catch { return; }
    if (!/^https?:$/.test(destination.protocol)) return;
    const target = destination.origin === window.location.origin
      ? destination.pathname
      : `external: ${destination.hostname}`;
    const body = JSON.stringify({ token: config.dataset.token, page: window.location.pathname, target });
    navigator.sendBeacon(config.dataset.endpoint, new Blob([body], { type: 'application/json' }));
  }, { capture: true });
})();
