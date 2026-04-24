/**
 * KronosCMS Dashboard — dashboard.js
 * Exposes: window.KronosDash.api(), window.KronosDash.saveOptions()
 * Also: settings tabs, status selects, search/filter tables, SSE reconnect
 */
(function () {
  'use strict';

  const cfg = window.KronosConfig || {};

  /* ── Core API helper ──────────────────────────────────────────── */
  async function api(path, method = 'GET', body = null, opts = {}) {
    const headers = {
      'Content-Type': 'application/json',
      'X-Kronos-CSRF': cfg.csrf || '',
    };

    const init = {
      method,
      credentials: 'include',
      headers,
    };

    if (body !== null && method !== 'GET') {
      init.body = JSON.stringify(body);
    }

    const url = (cfg.apiBase || '/api/kronos/v1') + path;

    try {
      const res = await fetch(url, init);

      if (res.status === 401) {
        // Attempt silent token refresh first
        const refresh = await fetch((cfg.apiBase || '/api/kronos/v1') + '/auth/refresh', {
          method: 'POST',
          credentials: 'include',
          headers: { 'X-Kronos-CSRF': cfg.csrf || '' },
        });
        if (refresh.ok) {
          // Retry original request
          const retry = await fetch(url, init);
          return await readApiResponse(retry);
        } else {
          window.location.href = (cfg.appUrl || '') + '/dashboard/login';
          return null;
        }
      }

      return await readApiResponse(res);
    } catch (err) {
      if (!opts.silent) console.error('[KronosDash]', method, path, err);
      return normalizeResponse({ success: false, message: err.message || 'Request failed.' });
    }
  }

  /* ── Save options helper ──────────────────────────────────────── */
  async function saveOptions(data) {
    const btn = document.querySelector('[data-save-options]');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Saving…';
    }
    const res = await api('/settings', 'POST', data);
    if (btn) {
      btn.disabled = false;
      btn.textContent = 'Save Settings';
    }
    if (res && res.success) {
      showToast('Settings saved.', 'success');
    } else {
      showToast((res && res.message) || 'Failed to save.', 'error');
    }
    return res;
  }

  /* ── Toast notification ───────────────────────────────────────── */
  function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.cssText = `
      position: fixed; bottom: 20px; right: 20px; z-index: 9999;
      min-width: 200px; max-width: 360px;
      box-shadow: 0 4px 12px rgba(0,0,0,.15);
      animation: fadeIn .2s ease;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity .3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  }

  /* ── Settings Tabs ────────────────────────────────────────────── */
  function initSettingsTabs() {
    const tabs = document.querySelectorAll('.settings-tab');
    if (!tabs.length) return;

    tabs.forEach(tab => {
      tab.addEventListener('click', function () {
        const target = this.dataset.tab;

        tabs.forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        document.querySelectorAll('.tab-panel').forEach(panel => {
          panel.classList.toggle('active', panel.dataset.tab === target);
        });

        // Persist selected tab in URL hash
        history.replaceState(null, '', '#' + target);
      });
    });

    // Restore from URL hash
    const hash = location.hash.replace('#', '');
    if (hash) {
      const target = document.querySelector(`.settings-tab[data-tab="${hash}"]`);
      if (target) target.click();
    }
  }

  /* ── Mode card selector (install wizard or settings) ─────────── */
  function initModeCards() {
    const cards = document.querySelectorAll('.mode-card');
    cards.forEach(card => {
      card.addEventListener('click', function () {
        cards.forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        const input = document.getElementById('mode');
        if (input) input.value = this.dataset.mode || '';
      });
    });
  }

  /* ── Live status select (orders table) ───────────────────────── */
  function initStatusSelects() {
    document.addEventListener('change', async function (e) {
      if (!e.target.classList.contains('status-select')) return;
      const select = e.target;
      const orderId = select.dataset.orderId;
      if (!orderId) return;

      select.disabled = true;
      const res = await api(`/commerce/orders/${orderId}/status`, 'PUT', { status: select.value });
      select.disabled = false;

      if (!res || !res.success) {
        showToast('Failed to update status.', 'error');
      } else {
        showToast('Status updated.', 'success');
      }
    });
  }

  /* ── Client-side table search ─────────────────────────────────── */
  function initTableSearch() {
    document.querySelectorAll('.search-box').forEach(input => {
      const tableSelector = input.dataset.table || '.data-table';
      const table = document.querySelector(tableSelector);
      if (!table) return;

      input.addEventListener('input', function () {
        const term = this.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
          const text = row.textContent.toLowerCase();
          row.style.display = (!term || text.includes(term)) ? '' : 'none';
        });
      });
    });
  }

  /* ── Confirm delete buttons ───────────────────────────────────── */
  function initDeleteButtons() {
    document.addEventListener('click', async function (e) {
      const btn = e.target.closest('[data-delete-url]');
      if (!btn) return;

      const message = btn.dataset.confirm || `Delete ${btn.dataset.confirmLabel || 'this item'}? This cannot be undone.`;
      if (!confirm(message)) return;

      btn.disabled = true;
      const rawPath = btn.dataset.deleteUrl || '';
      const path = rawPath.replace(/^\/api\/kronos\/v1/, '');
      const res   = await api(path, 'DELETE');
      if (res && res.success) {
        const target = btn.dataset.deleteTarget ? document.querySelector(btn.dataset.deleteTarget) : null;
        const row = target || btn.closest('tr');
        if (row) row.remove();
        showToast('Deleted.', 'success');
      } else {
        btn.disabled = false;
        showToast((res && res.message) || 'Delete failed.', 'error');
      }
    });
  }

  /* ── SSE Activity feed (dashboard home) ──────────────────────── */
  function initActivityFeed() {
    const feed = document.getElementById('activity-feed');
    if (!feed) return;

    function prependItem(icon, text) {
      const now  = new Date();
      const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      const item = document.createElement('div');
      item.className = 'activity-item';
      item.innerHTML = `
        <span class="activity-icon">${icon}</span>
        <span class="activity-text">${escHtml(text)}</span>
        <span class="activity-time">${escHtml(time)}</span>
      `;
      feed.prepend(item);
      // Keep max 20 items
      const items = feed.querySelectorAll('.activity-item');
      if (items.length > 20) items[items.length - 1].remove();
    }

    function openSSE() {
      const src = new EventSource((cfg.appUrl || '') + '/api/kronos/v1/stream', { withCredentials: true });

      src.addEventListener('order_update', function (e) {
        try {
          const data = JSON.parse(e.data);
          prependItem('🛒', `Order #${data.order_number || data.id} status changed to ${data.status}`);
        } catch (_) {}
      });

      src.addEventListener('notification', function (e) {
        try {
          const data = JSON.parse(e.data);
          prependItem('🔔', data.message || 'New notification');
        } catch (_) {}
      });

      src.onerror = function () {
        src.close();
        // Reconnect after 5 s
        setTimeout(openSSE, 5000);
      };

      return src;
    }

    openSSE();
  }

  /* ── AI Chat ──────────────────────────────────────────────────── */
  function initAIChat() {
    const messages = document.getElementById('ai-messages');
    const input    = document.getElementById('ai-input');
    const sendBtn  = document.getElementById('ai-send');
    const typing   = document.getElementById('ai-typing');

    if (!messages || !input || !sendBtn) return;

    const sessionId = 'sess_' + Math.random().toString(36).slice(2, 10);

    async function send() {
      const text = input.value.trim();
      if (!text) return;

      appendMessage('user', text);
      input.value = '';
      input.style.height = 'auto';
      sendBtn.disabled = true;
      if (typing) typing.classList.add('visible');

      // Check if SSE streaming is enabled
      const streamEnabled = cfg.aiStreaming === true;
      if (streamEnabled) {
        await sendStream(text, sessionId);
      } else {
        const res = await api('/ai/chat', 'POST', { message: text, session_id: sessionId });
        if (typing) typing.classList.remove('visible');
        if (res && (res.message || res.content)) {
          appendMessage('assistant', res.message || res.content);
        }
      }
      sendBtn.disabled = false;
    }

    async function sendStream(text, sid) {
      const url = (cfg.appUrl || '') + '/api/kronos/v1/ai/chat?stream=1';

      const msgDiv = appendMessage('assistant', '');

      try {
        const resp = await fetch(url, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'X-Kronos-CSRF': cfg.csrf || '',
          },
          body: JSON.stringify({ message: text, session_id: sid }),
        });

        if (!resp.ok || !resp.body) {
          if (typing) typing.classList.remove('visible');
          msgDiv.querySelector('.ai-bubble').textContent = 'Error: could not reach AI.';
          return;
        }

        const reader  = resp.body.getReader();
        const decoder = new TextDecoder();
        const bubble  = msgDiv.querySelector('.ai-bubble');
        let   full    = '';

        while (true) {
          const { done, value } = await reader.read();
          if (done) break;

          const chunk = decoder.decode(value, { stream: true });
          // SSE format: "data: ...\n\n"
          chunk.split('\n').forEach(line => {
            if (line.startsWith('data: ')) {
              const text = line.slice(6);
              if (text === '[DONE]') return;
              try {
                const parsed = JSON.parse(text);
                const delta  = parsed.choices?.[0]?.delta?.content || '';
                full += delta;
                bubble.textContent = full;
              } catch (_) {
                full += text;
                bubble.textContent = full;
              }
              messages.scrollTop = messages.scrollHeight;
            }
          });
        }
      } catch (err) {
        msgDiv.querySelector('.ai-bubble').textContent = 'Connection error.';
      } finally {
        if (typing) typing.classList.remove('visible');
      }
    }

    function appendMessage(role, text) {
      const wrap = document.createElement('div');
      wrap.className = `ai-message ${role}`;
      const initial = role === 'assistant' ? '🤖' : (cfg.user?.display_name || 'U')[0].toUpperCase();
      wrap.innerHTML = `
        <div class="ai-avatar">${escHtml(String(initial))}</div>
        <div class="ai-bubble">${escHtml(text)}</div>
      `;
      messages.appendChild(wrap);
      messages.scrollTop = messages.scrollHeight;
      return wrap;
    }

    sendBtn.addEventListener('click', send);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
    });

    // Auto-grow textarea
    input.addEventListener('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 160) + 'px';
    });
  }

  /* ── Builder DnD canvas ───────────────────────────────────────── */
  const KronosWidgets = {};

  function initBuilder() {
    const canvas = document.getElementById('builder-canvas');
    if (!canvas) return;

    // Widget palette drag start — builder.js owns canvas drop/render
    document.querySelectorAll('.widget-item[draggable]').forEach(item => {
      item.addEventListener('dragstart', function (e) {
        e.dataTransfer.setData('widget-type', this.dataset.widgetType || '');
        e.dataTransfer.effectAllowed = 'copy';
      });
    });
  }

  function renderInspector(block, ast, canvas) {
    const inspector = document.getElementById('builder-inspector');
    if (!inspector) return;
    const widget = KronosWidgets[block.type];
    const controls = widget ? widget.getControls() : [];

    inspector.innerHTML = `<div class="builder-panel-header">${escHtml(block.type)} Settings</div>`;
    const form = document.createElement('div');
    form.style.padding = '12px';

    controls.forEach(ctrl => {
      const group = document.createElement('div');
      group.className = 'form-group';
      group.innerHTML = `<label>${escHtml(ctrl.label)}</label>`;

      let input;
      if (ctrl.type === 'textarea') {
        input = document.createElement('textarea');
        input.value = block.attrs[ctrl.key] || ctrl.default || '';
      } else if (ctrl.type === 'select') {
        input = document.createElement('select');
        (ctrl.options || []).forEach(opt => {
          const o = document.createElement('option');
          o.value = opt.value; o.textContent = opt.label;
          if (block.attrs[ctrl.key] === opt.value) o.selected = true;
          input.appendChild(o);
        });
      } else {
        input = document.createElement('input');
        input.type = ctrl.type || 'text';
        input.value = block.attrs[ctrl.key] || ctrl.default || '';
      }

      input.addEventListener('input', function () {
        block.attrs[ctrl.key] = this.value;
        // Re-render the block on canvas
        const el = canvas.querySelector(`[data-block-id="${block.id}"]`);
        if (el && widget) {
          const del = el.querySelector('.action-btn.danger');
          el.innerHTML = widget.render(block.attrs);
          if (del) el.appendChild(del);
        }
        triggerSave(ast);
      });

      group.appendChild(input);
      form.appendChild(group);
    });

    inspector.appendChild(form);
  }

  function triggerSave(ast) {
    // Debounced auto-save
    clearTimeout(triggerSave._t);
    triggerSave._t = setTimeout(async function () {
      const layoutId = document.getElementById('builder-canvas')?.dataset.layoutId;
      if (!layoutId) return;
      await api(`/builder/layouts/${layoutId}`, 'PUT', { content: JSON.stringify(ast) });
    }, 800);
  }

  /* ── Widget Registry API ──────────────────────────────────────── */
  const KronosAPI = {
    Widgets: {
      register(type, def) {
        KronosWidgets[type] = {
          render:      def.render      || (() => `<div>[${type}]</div>`),
          getControls: def.getControls || (() => []),
        };
      },
    },
  };

  // Register built-in widgets
  KronosAPI.Widgets.register('heading', {
    render(attrs) {
      const tag  = attrs.tag  || 'h2';
      const text = attrs.text || 'Heading';
      return `<${tag} style="margin:0">${escHtml(text)}</${tag}>`;
    },
    getControls() {
      return [
        { key: 'text', label: 'Text', type: 'text', default: 'Heading' },
        {
          key: 'tag', label: 'Tag', type: 'select', default: 'h2',
          options: [
            { value: 'h1', label: 'H1' }, { value: 'h2', label: 'H2' },
            { value: 'h3', label: 'H3' }, { value: 'h4', label: 'H4' },
          ],
        },
      ];
    },
  });

  KronosAPI.Widgets.register('text', {
    render(attrs) { return `<p style="margin:0">${escHtml(attrs.text || 'Paragraph text.')}</p>`; },
    getControls() { return [{ key: 'text', label: 'Content', type: 'textarea', default: 'Paragraph text.' }]; },
  });

  KronosAPI.Widgets.register('button', {
    render(attrs) {
      return `<button class="btn btn-primary">${escHtml(attrs.label || 'Click Me')}</button>`;
    },
    getControls() {
      return [
        { key: 'label', label: 'Label', type: 'text', default: 'Click Me' },
        { key: 'url',   label: 'URL',   type: 'url',  default: '#' },
      ];
    },
  });

  KronosAPI.Widgets.register('image', {
    render(attrs) {
      if (!attrs.src) return `<div class="text-muted text-sm" style="padding:20px;text-align:center">📷 No image selected</div>`;
      return `<img src="${escAttr(attrs.src)}" alt="${escAttr(attrs.alt || '')}" style="max-width:100%">`;
    },
    getControls() {
      return [
        { key: 'src', label: 'Image URL', type: 'url', default: '' },
        { key: 'alt', label: 'Alt Text',  type: 'text', default: '' },
      ];
    },
  });

  /* ── Update check (settings) ──────────────────────────────────── */
  function initUpdateCheck() {
    const checkBtn  = document.getElementById('btn-check-update');
    const applyBtn  = document.getElementById('btn-apply-update');
    const statusEl  = document.getElementById('update-status');

    if (!checkBtn) return;

    checkBtn.addEventListener('click', async function () {
      checkBtn.disabled = true;
      if (statusEl) statusEl.innerHTML = '<span class="spinner"></span> Checking…';

      const res = await api('/system/update/check');
      checkBtn.disabled = false;

      if (!res) {
        if (statusEl) statusEl.textContent = 'Check failed.';
        return;
      }

      if (res.update_available) {
        if (statusEl) {
          statusEl.innerHTML = `
            <span class="text-success">✓ Update available: <strong>${escHtml(res.latest_version)}</strong></span>
          `;
        }
        if (applyBtn) {
          applyBtn.style.display = 'inline-flex';
          applyBtn.dataset.downloadUrl = res.download_url || '';
          applyBtn.dataset.version     = res.latest_version || '';
        }
      } else {
        if (statusEl) statusEl.innerHTML = '<span class="text-muted">You are on the latest version.</span>';
        if (applyBtn) applyBtn.style.display = 'none';
      }
    });

    if (applyBtn) {
      applyBtn.addEventListener('click', async function () {
        if (!confirm('Apply update now? The site may be briefly unavailable.')) return;
        applyBtn.disabled = true;
        applyBtn.textContent = 'Updating…';

        const res = await api('/system/update/apply', 'POST', {
          download_url: applyBtn.dataset.downloadUrl,
          version:      applyBtn.dataset.version,
        });

        if (res && res.success) {
          showToast('Update applied! Reloading…', 'success');
          setTimeout(() => location.reload(), 2000);
        } else {
          applyBtn.disabled = false;
          applyBtn.textContent = 'Update Now';
          showToast((res && res.message) || 'Update failed.', 'error');
        }
      });
    }
  }

  /* ── Marketplace install ──────────────────────────────────────── */
  function initMarketplaceInstall() {
    document.addEventListener('click', async function (e) {
      const btn = e.target.closest('[data-install-slug]');
      if (!btn) return;
      const slug = btn.dataset.installSlug;
      const url  = btn.dataset.installUrl || '';
      const type = btn.dataset.installType || 'module';

      if (!slug) return;
      if (!confirm(`Install "${slug}"?`)) return;

      btn.disabled = true;
      btn.textContent = 'Installing…';

      const res = await api('/marketplace/install', 'POST', {
        slug, download_url: url, type,
      });

      if (res && res.success) {
        btn.textContent = 'Installed ✓';
        btn.classList.replace('btn-primary', 'btn-success');
        showToast(`${slug} installed successfully.`, 'success');
      } else {
        btn.disabled = false;
        btn.textContent = 'Install';
        showToast((res && res.message) || 'Install failed.', 'error');
      }
    });
  }

  /* ── Mode switch (settings) ───────────────────────────────────── */
  function initModeSwitch() {
    document.querySelectorAll('[data-mode-switch]').forEach(btn => {
      btn.addEventListener('click', async function () {
        const newMode = this.dataset.modeSwitch;
        if (!confirm(`Switch to ${newMode} mode? Dashboard will reload.`)) return;
        this.disabled = true;

        const form = new URLSearchParams({ mode: newMode, _kronos_csrf: cfg.csrf || '' });
        const res  = await fetch((cfg.appUrl || '') + '/dashboard/mode-switch', {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Kronos-CSRF': cfg.csrf || '',
          },
          body: form.toString(),
        });

        if (res.ok) {
          location.reload();
        } else {
          this.disabled = false;
          showToast('Mode switch failed.', 'error');
        }
      });
    });
  }

  /* ── Helpers ──────────────────────────────────────────────────── */
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escAttr(str) { return escHtml(str); }

  function normalizeResponse(data) {
    if (data && data.error && !data.message) {
      data.message = data.error;
    }
    if (data && typeof data.success === 'undefined' && data.ok === false) {
      data.success = false;
    }
    return data;
  }

  async function readApiResponse(res) {
    const text = await res.text();
    let data = {};

    if (text) {
      try {
        data = JSON.parse(text);
      } catch (_) {
        data = { message: text };
      }
    }

    data = normalizeResponse(data || {});
    data.ok = res.ok;
    data.status = res.status;

    if (!res.ok && !data.message) {
      data.message = res.statusText || 'Request failed.';
    }

    return data;
  }

  function uid() {
    return 'b_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
  }

  /* ── Expose public API ────────────────────────────────────────── */
  window.KronosDash = { api, saveOptions, showToast };
  window.KronosAPI  = KronosAPI;

  /* ── Bootstrap on DOMContentLoaded ───────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    initSettingsTabs();
    initModeCards();
    initStatusSelects();
    initTableSearch();
    initDeleteButtons();
    initActivityFeed();
    initAIChat();
    initBuilder();
    initUpdateCheck();
    initMarketplaceInstall();
    initModeSwitch();
  });
})();
