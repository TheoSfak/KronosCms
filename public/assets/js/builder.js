/**
 * KronosCMS — builder.js
 *
 * Standalone builder JS. Loaded only on the /dashboard/builder page, after
 * dashboard.js. Extends the canvas behaviour registered in dashboard.js with
 * additional features: undo/redo, block reordering, keyboard shortcuts.
 *
 * Depends on:
 *  - window.KronosDash  (from dashboard.js)
 *  - window.KronosAPI   (from dashboard.js)
 *  - window.KronosBuilderAST   (injected by builder.php)
 *  - window.KronosBuilderMeta  (injected by builder.php)
 */
(function () {
  'use strict';

  const API  = window.KronosDash;
  const meta = window.KronosBuilderMeta || {};

  /* ── Undo / Redo ──────────────────────────────────────────────── */
  const history = {
    stack:   [JSON.stringify(window.KronosBuilderAST || [])],
    pointer: 0,

    push(ast) {
      // Trim redo history
      this.stack = this.stack.slice(0, this.pointer + 1);
      this.stack.push(JSON.stringify(ast));
      this.pointer = this.stack.length - 1;
      this.updateButtons();
    },

    undo() {
      if (this.pointer > 0) {
        this.pointer--;
        this.apply();
      }
    },

    redo() {
      if (this.pointer < this.stack.length - 1) {
        this.pointer++;
        this.apply();
      }
    },

    apply() {
      const ast = JSON.parse(this.stack[this.pointer]);
      window.KronosBuilderAST = ast;
      rebuildCanvas(ast);
      this.updateButtons();
    },

    updateButtons() {
      const u = document.getElementById('builder-undo');
      const r = document.getElementById('builder-redo');
      if (u) u.disabled = this.pointer <= 0;
      if (r) r.disabled = this.pointer >= this.stack.length - 1;
    },
  };

  /* ── Rebuild canvas from scratch ─────────────────────────────── */
  function rebuildCanvas(ast) {
    const canvas = document.getElementById('builder-canvas');
    if (!canvas) return;
    canvas.innerHTML = '';

    if (ast.length === 0) {
      const hint = document.createElement('div');
      hint.className = 'canvas-drop-zone';
      hint.id = 'builder-empty-hint';
      hint.innerHTML = '<span>Drag a widget here to start building</span>';
      canvas.appendChild(hint);
    }

    ast.forEach(block => renderBlock(block, canvas, ast));
  }

  /* ── Block renderer (shared with dashboard.js canvas init) ────── */
  function renderBlock(block, canvas, ast) {
    const widget = (window.KronosAPI && window.KronosAPI.Widgets._registry)
      ? window.KronosAPI.Widgets._registry[block.type]
      : null;

    const el = document.createElement('div');
    el.className    = 'canvas-block';
    el.dataset.blockId = block.id;
    el.draggable    = true;
    el.style.position = 'relative';

    // Render content
    if (widget) {
      el.innerHTML = widget.render(block.attrs || {});
    } else {
      el.innerHTML = `<span class="text-muted text-sm">[${escHtml(block.type)}]</span>`;
    }

    // Block toolbar
    const toolbar = document.createElement('div');
    toolbar.style.cssText = 'position:absolute;top:6px;right:6px;display:flex;gap:4px;opacity:0;transition:opacity .15s';
    el.addEventListener('mouseenter', () => { toolbar.style.opacity = '1'; });
    el.addEventListener('mouseleave', () => { toolbar.style.opacity = '0'; });

    // Duplicate
    const dupBtn = makeBtn('⊕', 'Duplicate', () => {
      const idx      = ast.findIndex(b => b.id === block.id);
      const newBlock = { ...block, id: uid(), attrs: { ...block.attrs } };
      ast.splice(idx + 1, 0, newBlock);
      history.push([...ast]);
      rebuildCanvas(ast);
      saveAst(ast);
    });

    // Delete
    const delBtn = makeBtn('✕', 'Delete', () => {
      const idx = ast.findIndex(b => b.id === block.id);
      if (idx > -1) {
        ast.splice(idx, 1);
        history.push([...ast]);
        rebuildCanvas(ast);
        saveAst(ast);
      }
    });
    delBtn.style.color = 'var(--danger)';

    toolbar.appendChild(dupBtn);
    toolbar.appendChild(delBtn);
    el.appendChild(toolbar);

    // ── Block drag-to-reorder ──
    el.addEventListener('dragstart', e => {
      e.dataTransfer.setData('block-id', block.id);
      e.dataTransfer.effectAllowed = 'move';
      el.style.opacity = '0.5';
    });
    el.addEventListener('dragend', () => { el.style.opacity = '1'; });

    el.addEventListener('dragover', e => {
      e.preventDefault();
      const src = e.dataTransfer.getData('block-id');
      if (src && src !== block.id) {
        el.style.outline = '2px solid var(--primary)';
      }
    });
    el.addEventListener('dragleave', () => { el.style.outline = ''; });

    el.addEventListener('drop', e => {
      e.preventDefault();
      el.style.outline = '';
      const srcId = e.dataTransfer.getData('block-id');
      if (!srcId || srcId === block.id) return;

      const srcIdx  = ast.findIndex(b => b.id === srcId);
      const destIdx = ast.findIndex(b => b.id === block.id);
      if (srcIdx < 0 || destIdx < 0) return;

      const [moved] = ast.splice(srcIdx, 1);
      ast.splice(destIdx, 0, moved);
      history.push([...ast]);
      rebuildCanvas(ast);
      saveAst(ast);
    });

    // Click → select + open inspector
    el.addEventListener('click', e => {
      if (e.target.closest('button')) return;
      document.querySelectorAll('.canvas-block').forEach(b => b.classList.remove('selected'));
      el.classList.add('selected');
      renderInspector(block, ast, canvas);
    });

    canvas.appendChild(el);
  }

  /* ── Inspector ────────────────────────────────────────────────── */
  function renderInspector(block, ast, canvas) {
    const inspector = document.getElementById('builder-inspector');
    if (!inspector) return;

    const widget = (window.KronosAPI && window.KronosAPI.Widgets._registry)
      ? window.KronosAPI.Widgets._registry[block.type]
      : null;

    const controls = widget ? widget.getControls() : [];

    inspector.innerHTML = `<div class="builder-panel-header">${escHtml(block.type)} Settings</div>`;
    const form = document.createElement('div');
    form.style.padding = '12px';

    if (controls.length === 0) {
      const note = document.createElement('p');
      note.className = 'text-sm text-muted';
      note.textContent = 'No editable properties.';
      form.appendChild(note);
    }

    controls.forEach(ctrl => {
      const group = document.createElement('div');
      group.className = 'form-group';

      const label = document.createElement('label');
      label.textContent = ctrl.label;
      group.appendChild(label);

      let input;
      if (ctrl.type === 'textarea') {
        input = document.createElement('textarea');
        input.value = block.attrs[ctrl.key] ?? ctrl.default ?? '';
      } else if (ctrl.type === 'select') {
        input = document.createElement('select');
        (ctrl.options || []).forEach(opt => {
          const o = document.createElement('option');
          o.value = opt.value;
          o.textContent = opt.label;
          if ((block.attrs[ctrl.key] ?? ctrl.default) === opt.value) o.selected = true;
          input.appendChild(o);
        });
      } else {
        input = document.createElement('input');
        input.type  = ctrl.type || 'text';
        input.value = block.attrs[ctrl.key] ?? ctrl.default ?? '';
      }

      input.addEventListener('input', function () {
        block.attrs[ctrl.key] = this.value;

        // Re-render just the affected block
        const el = canvas.querySelector(`[data-block-id="${block.id}"]`);
        if (el && widget) {
          const tools = el.querySelector(':scope > div[style*="position:absolute"]');
          el.innerHTML = widget.render(block.attrs);
          if (tools) el.appendChild(tools);
        }

        saveAst(ast);
      });

      group.appendChild(input);
      form.appendChild(group);
    });

    inspector.appendChild(form);
  }

  /* ── Debounced save ───────────────────────────────────────────── */
  function saveAst(ast) {
    history.push([...ast]);
    clearTimeout(saveAst._t);
    saveAst._t = setTimeout(async () => {
      const status = document.getElementById('builder-save-status');
      if (status) status.textContent = 'Saving…';
      const res = await API.api('/builder/layouts/' + meta.id, 'PUT', {
        content: JSON.stringify(ast),
      });
      if (status) {
        status.textContent = (res && res.success) ? 'Saved ✓' : 'Error saving';
        setTimeout(() => { status.textContent = 'Auto-saved'; }, 2000);
      }
    }, 600);
  }

  /* ── Widget-palette → canvas drop handler ─────────────────────── */
  function initCanvasDrop() {
    const canvas = document.getElementById('builder-canvas');
    if (!canvas) return;

    const ast = window.KronosBuilderAST || [];

    canvas.addEventListener('dragover', e => {
      // Only handle new widgets (not block reorders)
      if (e.dataTransfer.types.includes('widget-type')) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
      }
    });

    canvas.addEventListener('drop', e => {
      const type = e.dataTransfer.getData('widget-type');
      if (!type) return;

      e.preventDefault();
      const hint = document.getElementById('builder-empty-hint');
      if (hint) hint.remove();

      const block = { id: uid(), type, attrs: {} };
      ast.push(block);
      window.KronosBuilderAST = ast;
      renderBlock(block, canvas, ast);
      saveAst(ast);
    });

    // Initial render from injected AST
    ast.forEach(block => renderBlock(block, canvas, ast));
  }

  /* ── Keyboard shortcuts ───────────────────────────────────────── */
  function initKeyboardShortcuts() {
    document.addEventListener('keydown', e => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) { e.preventDefault(); history.undo(); }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); history.redo(); }
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        const ast = window.KronosBuilderAST || [];
        saveAst(ast);
      }
    });
  }

  /* ── Expose _registry so renderBlock / inspector can access it ── */
  if (window.KronosAPI && window.KronosAPI.Widgets && !window.KronosAPI.Widgets._registry) {
    // Proxy: intercept .register() calls and store in _registry
    const original = window.KronosAPI.Widgets.register.bind(window.KronosAPI.Widgets);
    window.KronosAPI.Widgets._registry = {};
    window.KronosAPI.Widgets.register  = function (type, def) {
      window.KronosAPI.Widgets._registry[type] = {
        render:      def.render      || (() => `<div>[${type}]</div>`),
        getControls: def.getControls || (() => []),
      };
      original(type, def);
    };

    // Re-register defaults already registered by dashboard.js
    // (They're already in KronosWidgets inside dashboard.js IIFE — we need to
    //  duplicate enough for this module to function standalone.)
    window.KronosAPI.Widgets._registry['heading'] = {
      render: a => `<${a.tag||'h2'}>${escHtml(a.text||'Heading')}</${a.tag||'h2'}>`,
      getControls: () => [
        { key:'text', label:'Text', type:'text',   default:'Heading' },
        { key:'tag',  label:'Tag',  type:'select',  default:'h2',
          options:[{value:'h1',label:'H1'},{value:'h2',label:'H2'},{value:'h3',label:'H3'},{value:'h4',label:'H4'}] },
      ],
    };
    window.KronosAPI.Widgets._registry['text'] = {
      render: a => `<p>${escHtml(a.text||'Paragraph text.')}</p>`,
      getControls: () => [{ key:'text', label:'Content', type:'textarea', default:'Paragraph text.' }],
    };
    window.KronosAPI.Widgets._registry['button'] = {
      render: a => `<a href="${escHtml(a.url||'#')}" class="btn btn-primary">${escHtml(a.label||'Click Me')}</a>`,
      getControls: () => [
        { key:'label', label:'Label', type:'text', default:'Click Me' },
        { key:'url',   label:'URL',   type:'url',  default:'#' },
      ],
    };
    window.KronosAPI.Widgets._registry['image'] = {
      render: a => a.src ? `<img src="${escHtml(a.src)}" alt="${escHtml(a.alt||'')}" style="max-width:100%">` : `<div class="text-muted text-sm" style="padding:20px;text-align:center">📷 No image</div>`,
      getControls: () => [
        { key:'src', label:'Image URL', type:'url',  default:'' },
        { key:'alt', label:'Alt Text',  type:'text', default:'' },
      ],
    };
    window.KronosAPI.Widgets._registry['container'] = {
      render: (a, inner) => `<div style="${escHtml(a.style||'')}">${inner||''}</div>`,
      getControls: () => [
        { key:'style', label:'CSS Style', type:'text', default:'' },
      ],
    };
  }

  /* ── Utilities ────────────────────────────────────────────────── */
  function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function uid() {
    return 'b_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
  }

  function makeBtn(icon, title, onClick) {
    const btn = document.createElement('button');
    btn.className   = 'action-btn';
    btn.textContent = icon;
    btn.title       = title;
    btn.style.cssText = 'padding:2px 6px;font-size:.75rem;';
    btn.addEventListener('click', e => { e.stopPropagation(); onClick(); });
    return btn;
  }

  /* ── Undo/Redo toolbar buttons (injected if builder-toolbar exists) */
  function injectUndoRedoButtons() {
    const actionsArea = document.querySelector('.topbar-actions');
    if (!actionsArea) return;

    const undoBtn = document.createElement('button');
    undoBtn.id        = 'builder-undo';
    undoBtn.className = 'btn btn-ghost btn-sm';
    undoBtn.textContent = '↩ Undo';
    undoBtn.title     = 'Undo (Ctrl+Z)';
    undoBtn.disabled  = true;
    undoBtn.addEventListener('click', () => history.undo());

    const redoBtn = document.createElement('button');
    redoBtn.id        = 'builder-redo';
    redoBtn.className = 'btn btn-ghost btn-sm';
    redoBtn.textContent = '↪ Redo';
    redoBtn.title     = 'Redo (Ctrl+Y)';
    redoBtn.disabled  = true;
    redoBtn.addEventListener('click', () => history.redo());

    actionsArea.prepend(redoBtn);
    actionsArea.prepend(undoBtn);
  }

  /* ── Bootstrap ────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', () => {
    injectUndoRedoButtons();
    initCanvasDrop();
    initKeyboardShortcuts();
  });
})();
