/**
 * SecurityGuard – debug.js
 * Loaded only when SECURITYGUARD_DEBUG = 1.
 * Populates the debug panel with AJAX diagnostics.
 */
(function () {
  'use strict';

  if (!window.SG_DEBUG) { return; }

  /** @param {string} id @returns {HTMLElement|null} */
  function byId(id) { return document.getElementById(id); }

  var elAjaxUrl     = byId('sg-debug-ajax-url');
  var elLastResp    = byId('sg-debug-last-response');
  var elLog         = byId('sg-debug-log');

  function appendLog(msg) {
    if (!elLog) { return; }
    elLog.textContent += '[' + new Date().toISOString() + '] ' + msg + '\n';
  }

  // Show the AJAX URL
  if (elAjaxUrl) {
    elAjaxUrl.textContent = (typeof SG_AJAX_URL !== 'undefined') ? SG_AJAX_URL : '(undefined)';
  }

  if (typeof SG_AJAX_URL === 'undefined' || !SG_AJAX_URL) {
    appendLog('ERROR: SG_AJAX_URL is not defined or empty.');
    return;
  }

  appendLog('SG_AJAX_URL = ' + SG_AJAX_URL);
  appendLog('Performing one-shot fetch…');

  // One-shot fetch for diagnostics
  fetch(SG_AJAX_URL)
    .then(function (response) {
      appendLog('HTTP status: ' + response.status + ' ' + response.statusText);
      return response.text();
    })
    .then(function (text) {
      if (elLastResp) { elLastResp.textContent = text; }
      appendLog('Response length: ' + text.length + ' chars');
      try {
        var parsed = JSON.parse(text);
        appendLog('JSON parsed OK. ok=' + parsed.ok);
        if (!parsed.ok) {
          appendLog('Error from server: ' + (parsed.error || '?'));
        }
      } catch (e) {
        appendLog('JSON parse error: ' + e.message);
      }
    })
    .catch(function (err) {
      appendLog('Fetch error: ' + err.message);
      if (elLastResp) { elLastResp.textContent = '(fetch failed: ' + err.message + ')'; }
    });
}());
