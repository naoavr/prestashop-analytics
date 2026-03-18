/**
 * SecurityGuard – dashboard.js
 * Polls SG_AJAX_URL every 5 seconds and updates KPIs + event feed.
 */
(function () {
  'use strict';

  /** @param {string} id @returns {HTMLElement|null} */
  function byId(id) { return document.getElementById(id); }

  var elToday      = byId('sg-kpi-today');
  var elBlocked    = byId('sg-kpi-blocked');
  var elPatterns   = byId('sg-kpi-patterns');
  var elLastMinute = byId('sg-kpi-last-minute');
  var elFeed       = byId('sg-realtime-feed');
  var elServerTime = byId('sg-server-time');

  // Mark JS as loaded in debug panel (if present)
  var elDbgLoaded = byId('sg-debug-js-loaded');
  if (elDbgLoaded) {
    elDbgLoaded.textContent = 'sim';
    elDbgLoaded.style.color = 'green';
  }

  // Bail out if the URL is not defined or empty
  if (typeof window.SG_AJAX_URL === 'undefined' || !SG_AJAX_URL) {
    return;
  }

  /** Keys already displayed in the feed (avoid duplicates). */
  var seen = Object.create(null);

  /**
   * Return a colour hex string based on threat score.
   * @param {number} score
   * @returns {string}
   */
  function colorByScore(score) {
    if (score >= 70) { return '#ef4444'; }
    if (score >= 40) { return '#f59e0b'; }
    return '#60a5fa';
  }

  /**
   * Prepend one feed entry, skipping duplicates.
   * @param {Object} item
   */
  function addLine(item) {
    var key = (item.date_add || '') + '|' + (item.ip || '') + '|' +
              (item.attack_type || '') + '|' + (item.form_type || '');
    if (seen[key]) { return; }
    seen[key] = true;

    var div = document.createElement('div');
    div.style.color = colorByScore(parseInt(item.threat_score || 0, 10));
    div.textContent =
      '[' + (item.date_add || '') + '] ' +
      (item.ip || '') +
      ' [' + (item.country || 'XX') + '] ' +
      '\u2192 ' + (item.form_type || '') +
      ' | ' + (item.attack_type || '') +
      ' | score=' + (item.threat_score || 0);

    // Clear placeholder text on first real entry
    if (elFeed && elFeed.childNodes.length === 1 &&
        elFeed.firstChild.nodeType === Node.TEXT_NODE) {
      elFeed.textContent = '';
    }

    if (elFeed) {
      elFeed.insertBefore(div, elFeed.firstChild);
      // Cap feed at 80 lines
      while (elFeed.children.length > 80) {
        elFeed.removeChild(elFeed.lastChild);
      }
    }
  }

  /**
   * Overwrite a KPI element only when we receive a real value.
   * @param {HTMLElement|null} el
   * @param {*} value
   */
  function setKpi(el, value) {
    if (el && value != null) {
      el.textContent = value;
    }
  }

  /** Update all KPI tiles from the kpis object. */
  function updateKpis(k) {
    if (!k) { return; }
    setKpi(elToday,      k.total_today);
    setKpi(elBlocked,    k.total_blocked);
    setKpi(elPatterns,   k.total_patterns);
    setKpi(elLastMinute, k.attacks_last_minute);
  }

  /** Store last raw response for the debug panel. */
  function storeDebugResponse(text) {
    var el = byId('sg-debug-last-response');
    if (el) { el.textContent = text; }
  }

  /** Perform one AJAX poll. */
  function poll() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', SG_AJAX_URL, true);

    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) { return; }

      storeDebugResponse(xhr.responseText);

      if (xhr.status !== 200) { return; }

      try {
        var data = JSON.parse(xhr.responseText);
        if (!data || !data.ok) { return; }

        if (elServerTime && data.server_time) {
          elServerTime.textContent = '(server: ' + data.server_time + ')';
        }

        updateKpis(data.kpis);

        if (Array.isArray(data.latest)) {
          for (var i = 0; i < data.latest.length; i++) {
            addLine(data.latest[i]);
          }
        }
      } catch (e) {
        // Silently ignore JSON parse errors
      }
    };

    xhr.send();
  }

  // Start polling immediately, then every 5 seconds
  poll();
  setInterval(poll, 5000);
}());
