/**
 * KronosCMS — stream.js
 *
 * Connects to the SSE endpoint (/api/kronos/v1/stream) and broadcasts
 * received events to the rest of the dashboard via a simple pub/sub bus.
 *
 * Usage:
 *   // Listen for any event type:
 *   KronosStream.on('order_update', function(data){ ... });
 *   KronosStream.on('notification',  function(data){ ... });
 *
 *   // Unsubscribe:
 *   const unsub = KronosStream.on('order_update', handler);
 *   unsub();
 *
 * The module is auto-included by layout-footer.php and starts connecting
 * once the DOM is ready. Reconnection is automatic with exponential back-off.
 */
(function (global) {
  'use strict';

  const cfg = global.KronosConfig || {};
  const URL = (cfg.appUrl || '') + '/api/kronos/v1/stream';

  /** @type {Record<string, Array<Function>>} */
  const listeners = {};

  /** Current EventSource instance */
  let sse = null;

  /** Retry delay in ms (doubles on each failure, caps at 30 s) */
  let retryDelay = 2000;

  // ── Pub/Sub ──────────────────────────────────────────────────────

  /**
   * Subscribe to an SSE event type.
   * @param  {string}   event   Event name (e.g. 'order_update', 'notification')
   * @param  {Function} handler Called with parsed JSON data object
   * @returns {Function} Unsubscribe function
   */
  function on(event, handler) {
    if (!listeners[event]) listeners[event] = [];
    listeners[event].push(handler);
    return function () {
      listeners[event] = (listeners[event] || []).filter(h => h !== handler);
    };
  }

  function emit(event, data) {
    (listeners[event] || []).forEach(function (h) {
      try { h(data); } catch (e) { console.error('[KronosStream] handler error', e); }
    });
    // Also emit wildcard '*' listeners
    (listeners['*'] || []).forEach(function (h) {
      try { h(event, data); } catch (e) {}
    });
  }

  // ── SSE Connection ────────────────────────────────────────────────

  function connect() {
    if (!global.EventSource) {
      console.warn('[KronosStream] EventSource not supported in this browser.');
      return;
    }

    sse = new EventSource(URL, { withCredentials: true });

    sse.addEventListener('open', function () {
      retryDelay = 2000; // reset back-off on successful connect
      emit('_connected', {});
    });

    // Named SSE events ─ server sends "event: order_update\ndata: {...}\n\n"
    const knownEvents = ['order_update', 'notification', 'ai_stream', 'ping'];
    knownEvents.forEach(function (evtName) {
      sse.addEventListener(evtName, function (e) {
        let data = {};
        try { data = JSON.parse(e.data); } catch (_) { data = { raw: e.data }; }
        emit(evtName, data);
      });
    });

    // Catch-all message (unnamed events)
    sse.addEventListener('message', function (e) {
      let data = {};
      try { data = JSON.parse(e.data); } catch (_) { data = { raw: e.data }; }
      emit('message', data);
    });

    sse.onerror = function () {
      sse.close();
      sse = null;
      emit('_disconnected', { retry: retryDelay });
      setTimeout(function () {
        retryDelay = Math.min(retryDelay * 2, 30000);
        connect();
      }, retryDelay);
    };
  }

  function disconnect() {
    if (sse) { sse.close(); sse = null; }
  }

  // ── Live UI integrations ──────────────────────────────────────────

  /**
   * Attach default UI handlers when the DOM is ready.
   * These update counters / badges without requiring custom code.
   */
  function attachDefaultHandlers() {
    // Order status badge update
    on('order_update', function (data) {
      // Update any inline status select that matches the order ID
      const sel = document.querySelector(`.status-select[data-order-id="${data.id}"]`);
      if (sel && data.status) sel.value = data.status;

      // Increment the "new orders" counter badge if shown
      const badge = document.getElementById('orders-new-count');
      if (badge) {
        const count = parseInt(badge.textContent || '0', 10);
        badge.textContent = String(count + 1);
        badge.style.display = 'inline';
      }
    });

    // Notification toast
    on('notification', function (data) {
      if (global.KronosDash && global.KronosDash.showToast) {
        global.KronosDash.showToast(data.message || 'New notification', 'info');
      }
    });

    // AI streaming chunk (appended to the active AI bubble)
    on('ai_stream', function (data) {
      const bubble = document.getElementById('ai-stream-bubble');
      if (bubble && data.delta) {
        bubble.textContent += data.delta;
        const messages = bubble.closest('.ai-messages');
        if (messages) messages.scrollTop = messages.scrollHeight;
      }
    });
  }

  // ── Bootstrap ─────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', function () {
    connect();
    attachDefaultHandlers();
  });

  // ── Public API ────────────────────────────────────────────────────

  global.KronosStream = { on, emit, connect, disconnect };

})(window);
