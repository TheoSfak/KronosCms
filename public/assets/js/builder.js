/**
 * KronosCMS — builder.js v2
 *
 * Full Elementor-style builder:
 *  - 14 widget types with live canvas rendering
 *  - Inspector: Content / Style / Advanced tabs
 *  - Control types: text, textarea, select, color, range, align, toggle, url
 *  - Add-between-blocks "+" buttons with quick-add popover
 *  - Undo / Redo (Ctrl+Z / Ctrl+Y)
 *  - Viewport responsive preview
 *  - Zoom in/out
 *  - Canvas drag-and-drop reorder + palette drop
 *  - Keyboard shortcuts (Ctrl+Z/Y/S/D, Delete)
 */
(function () {
  'use strict';

  const API  = window.KronosDash;
  const meta = window.KronosBuilderMeta || {};

  /* ══════════════════════════════════════════════════════
     WIDGET REGISTRY
     ══════════════════════════════════════════════════════ */
  const Widgets = {};

  function reg(type, def) {
    Widgets[type] = {
      render:      def.render      || (() => `<div>[${type}]</div>`),
      getControls: def.getControls || (() => []),
      label:       def.label || type,
      icon:        def.icon  || '▫',
    };
  }

  // ── Heading ─────────────────────────────────────────
  reg('heading', {
    label: 'Heading', icon: 'H',
    render: a => {
      const tag = a.tag || 'h2';
      const st  = buildInlineStyle(a);
      return `<${tag} style="margin:0;${st}">${escHtml(a.text || 'Your Heading')}</${tag}>`;
    },
    getControls: () => [
      { tab: 'content', key: 'text', label: 'Text',  type: 'textarea', default: 'Your Heading' },
      { tab: 'content', key: 'tag',  label: 'Tag',   type: 'select',   default: 'h2',
        options: [{value:'h1',label:'H1'},{value:'h2',label:'H2'},{value:'h3',label:'H3'},{value:'h4',label:'H4'},{value:'h5',label:'H5'}] },
    ],
  });

  // ── Text ─────────────────────────────────────────────
  reg('text', {
    label: 'Text', icon: '¶',
    render: a => {
      const st = buildInlineStyle(a);
      return `<p style="margin:0;${st}">${escHtml(a.text || 'Paragraph text.')}</p>`;
    },
    getControls: () => [
      { tab: 'content', key: 'text', label: 'Content', type: 'textarea', default: 'Paragraph text.' },
    ],
  });

  // ── Button ───────────────────────────────────────────
  reg('button', {
    label: 'Button', icon: '⬡',
    render: a => {
      const bg     = a.btnColor  || '#6366f1';
      const tColor = a.btnText   || '#ffffff';
      const size   = a.size      || 'md';
      const full   = a.fullWidth === 'true' ? 'display:block;width:100%;text-align:center;' : 'display:inline-block;';
      const sizeMap = { sm: 'padding:6px 14px;font-size:.8rem;', md: 'padding:10px 22px;font-size:.9rem;', lg: 'padding:13px 30px;font-size:1rem;' };
      return `<div style="text-align:${a._align||'left'}">
        <a href="${escHtml(a.url||'#')}" style="${full}${sizeMap[size]||sizeMap.md}background:${escHtml(bg)};color:${escHtml(tColor)};border-radius:8px;text-decoration:none;font-weight:600;letter-spacing:.01em">${escHtml(a.label||'Click Me')}</a>
      </div>`;
    },
    getControls: () => [
      { tab: 'content', key: 'label',     label: 'Label',       type: 'text',   default: 'Click Me' },
      { tab: 'content', key: 'url',       label: 'URL',         type: 'url',    default: '#' },
      { tab: 'content', key: 'size',      label: 'Size',        type: 'select', default: 'md',
        options: [{value:'sm',label:'Small'},{value:'md',label:'Medium'},{value:'lg',label:'Large'}] },
      { tab: 'style',   key: 'btnColor',  label: 'Button Color', type: 'color', default: '#6366f1' },
      { tab: 'style',   key: 'btnText',   label: 'Text Color',  type: 'color',  default: '#ffffff' },
      { tab: 'style',   key: '_align',    label: 'Alignment',   type: 'align',  default: 'left' },
      { tab: 'style', key: 'fullWidth',   label: 'Full Width',  type: 'toggle', default: 'false' },
    ],
  });

  // ── Divider ──────────────────────────────────────────
  reg('divider', {
    label: 'Divider', icon: '—',
    render: a => {
      const color  = a.color  || '#e2e8f0';
      const style  = a.dStyle || 'solid';
      const weight = a.weight || '1';
      const width  = a.width  || '100';
      return `<div style="padding:8px 0 8px">
        <hr style="border:none;border-top:${escHtml(weight)}px ${escHtml(style)} ${escHtml(color)};width:${escHtml(width)}%;margin:0 auto">
      </div>`;
    },
    getControls: () => [
      { tab: 'content', key: 'dStyle',  label: 'Style',  type: 'select', default: 'solid',
        options: [{value:'solid',label:'Solid'},{value:'dashed',label:'Dashed'},{value:'dotted',label:'Dotted'}] },
      { tab: 'style',   key: 'color',  label: 'Color',   type: 'color',  default: '#e2e8f0' },
      { tab: 'style',   key: 'weight', label: 'Weight (px)', type: 'range', default: '1', min: 1, max: 10 },
      { tab: 'style',   key: 'width',  label: 'Width %', type: 'range',  default: '100', min: 10, max: 100 },
    ],
  });

  // ── Spacer ───────────────────────────────────────────
  reg('spacer', {
    label: 'Spacer', icon: '↕',
    render: a => {
      const h = parseInt(a.height) || 40;
      return `<div style="height:${h}px;display:flex;align-items:center;justify-content:center;opacity:.2;font-size:.72rem;color:#94a3b8;pointer-events:none;user-select:none">↕ ${h}px</div>`;
    },
    getControls: () => [
      { tab: 'content', key: 'height', label: 'Height (px)', type: 'range', default: '40', min: 8, max: 300 },
    ],
  });

  // ── Image ────────────────────────────────────────────
  reg('image', {
    label: 'Image', icon: '🖼',
    render: a => {
      const align = a._align || 'center';
      if (!a.src) return `<div style="padding:28px;text-align:center;border:2px dashed #d1d5db;border-radius:8px;color:#94a3b8;font-size:.85rem">🖼 Add an image URL in the inspector</div>`;
      return `<div style="text-align:${escHtml(align)}">
        <img src="${escHtml(a.src)}" alt="${escHtml(a.alt||'')}"
          style="max-width:100%;border-radius:${escHtml(a.radius||'0')}px;${a.shadow==='true'?'box-shadow:0 8px 32px rgba(0,0,0,.18)':''}">
        ${a.caption ? `<p style="font-size:.8rem;color:#6b7280;margin-top:6px;text-align:center">${escHtml(a.caption)}</p>` : ''}
      </div>`;
    },
    getControls: () => [
      { tab: 'content', key: 'src',     label: 'Image URL',  type: 'url',    default: '' },
      { tab: 'content', key: 'alt',     label: 'Alt Text',   type: 'text',   default: '' },
      { tab: 'content', key: 'caption', label: 'Caption',    type: 'text',   default: '' },
      { tab: 'style',   key: '_align',  label: 'Align',      type: 'align',  default: 'center' },
      { tab: 'style',   key: 'radius',  label: 'Radius (px)',type: 'range',  default: '0', min: 0, max: 48 },
      { tab: 'style',   key: 'shadow',  label: 'Drop Shadow',type: 'toggle', default: 'false' },
    ],
  });

  // ── Video ────────────────────────────────────────────
  reg('video', {
    label: 'Video', icon: '▶',
    render: a => {
      if (!a.url) return `<div style="padding:28px;text-align:center;border:2px dashed #d1d5db;border-radius:8px;color:#94a3b8;font-size:.85rem">▶ Add a YouTube or Vimeo URL in the inspector</div>`;
      const embedUrl = toEmbedUrl(a.url);
      const aspectPad = a.aspect === '4/3' ? '75' : '56.25';
      return `<div style="position:relative;padding-bottom:${aspectPad}%;height:0;overflow:hidden;border-radius:${escHtml(a.radius||'8')}px">
        <iframe src="${escHtml(embedUrl)}" style="position:absolute;inset:0;width:100%;height:100%;border:none" allowfullscreen loading="lazy"></iframe>
      </div>`;
    },
    getControls: () => [
      { tab: 'content', key: 'url',    label: 'YouTube / Vimeo URL', type: 'url',    default: '' },
      { tab: 'style',   key: 'aspect', label: 'Aspect Ratio', type: 'select', default: '16/9',
        options: [{value:'16/9',label:'16:9 (widescreen)'},{value:'4/3',label:'4:3 (classic)'}] },
      { tab: 'style',   key: 'radius', label: 'Radius (px)', type: 'range', default: '8', min: 0, max: 24 },
    ],
  });

  // ── Icon ─────────────────────────────────────────────
  reg('icon', {
    label: 'Icon', icon: '★',
    render: a => {
      const size  = parseInt(a.size) || 48;
      const color = a.color || '#6366f1';
      const bg    = a.bg    || 'transparent';
      const pad   = bg !== 'transparent' ? `padding:${Math.round(size*.35)}px;border-radius:${Math.round(size*.3)}px;background:${escHtml(bg)}` : '';
      return `<div style="text-align:${escHtml(a._align||'center')};line-height:1">
        <span style="font-size:${size}px;color:${escHtml(color)};display:inline-block;${pad}">${escHtml(a.icon||'★')}</span>
        ${a.label ? `<p style="margin-top:10px;font-size:.9rem;font-weight:600;text-align:${escHtml(a._align||'center')}">${escHtml(a.label)}</p>` : ''}
      </div>`;
    },
    getControls: () => [
      { tab: 'content', key: 'icon',   label: 'Icon / Emoji', type: 'text',  default: '★' },
      { tab: 'content', key: 'label',  label: 'Label',        type: 'text',  default: '' },
      { tab: 'style',   key: 'size',   label: 'Size (px)',    type: 'range', default: '48', min: 16, max: 128 },
      { tab: 'style',   key: 'color',  label: 'Icon Color',   type: 'color', default: '#6366f1' },
      { tab: 'style',   key: 'bg',     label: 'Background',   type: 'color', default: '#eef2ff' },
      { tab: 'style',   key: '_align', label: 'Align',        type: 'align', default: 'center' },
    ],
  });

  // ── Columns ──────────────────────────────────────────
  reg('columns', {
    label: 'Columns', icon: '⊞',
    render: a => {
      const cols = parseInt(a.cols) || 2;
      const gap  = parseInt(a.gap)  || 24;
      const colHtml = Array.from({length: cols}, (_, i) => {
        const content = a['col'+i] || `<p style="color:#94a3b8;font-size:.82rem;text-align:center;padding:20px 0">Column ${i+1}</p>`;
        return `<div style="flex:1;min-width:0;border:1px dashed #e2e8f0;border-radius:6px;padding:12px">${content}</div>`;
      }).join('');
      return `<div style="display:flex;gap:${gap}px;align-items:${escHtml(a.valign||'stretch')}">${colHtml}</div>`;
    },
    getControls: () => [
      { tab: 'content', key: 'cols',   label: 'Columns', type: 'select', default: '2',
        options: [{value:'2',label:'2 Columns'},{value:'3',label:'3 Columns'},{value:'4',label:'4 Columns'}] },
      { tab: 'content', key: 'col0',   label: 'Column 1 HTML', type: 'textarea-html', default: '' },
      { tab: 'content', key: 'col1',   label: 'Column 2 HTML', type: 'textarea-html', default: '' },
      { tab: 'content', key: 'col2',   label: 'Column 3 HTML', type: 'textarea-html', default: '' },
      { tab: 'style',   key: 'gap',    label: 'Gap (px)', type: 'range', default: '24', min: 0, max: 80 },
      { tab: 'style',   key: 'valign', label: 'Vertical Align', type: 'select', default: 'stretch',
        options: [{value:'stretch',label:'Stretch'},{value:'flex-start',label:'Top'},{value:'center',label:'Middle'},{value:'flex-end',label:'Bottom'}] },
    ],
  });

  // ── Container ────────────────────────────────────────
  reg('container', {
    label: 'Container', icon: '▤',
    render: a => {
      const bg   = a._bg     || 'transparent';
      const pad  = parseInt(a._pad) || 24;
      const br   = parseInt(a._radius) || 8;
      const maxw = a.maxw    || '';
      return `<div style="background:${escHtml(bg)};padding:${pad}px;border-radius:${br}px;${maxw?`max-width:${escHtml(maxw)}px;margin:0 auto`:''};${a.border==='true'?'border:1px solid #e2e8f0':''}">
        ${a.content || '<p style="color:#94a3b8;font-size:.82rem;text-align:center;margin:0">Container — add HTML content in the inspector</p>'}
      </div>`;
    },
    getControls: () => [
      { tab: 'content', key: 'content', label: 'HTML Content',  type: 'textarea-html', default: '' },
      { tab: 'style',   key: '_bg',     label: 'Background',    type: 'color',  default: '#f8fafc' },
      { tab: 'style',   key: '_pad',    label: 'Padding (px)',  type: 'range',  default: '24', min: 0, max: 80 },
      { tab: 'style',   key: '_radius', label: 'Radius (px)',   type: 'range',  default: '8',  min: 0, max: 48 },
      { tab: 'style',   key: 'maxw',    label: 'Max Width (px)',type: 'text',   default: '' },
      { tab: 'style',   key: 'border',  label: 'Show Border',   type: 'toggle', default: 'false' },
    ],
  });

  // ── Hero Block ───────────────────────────────────────
  reg('hero-block', {
    label: 'Hero', icon: '⚡',
    render: a => {
      const bg  = a.bg  || 'linear-gradient(135deg,#1e1b4b,#312e81)';
      const pad = parseInt(a.pad) || 80;
      return `<div style="background:${escHtml(bg)};padding:${pad}px 40px;border-radius:12px;text-align:${escHtml(a._align||'center')}">
        ${a.pretitle ? `<p style="font-size:.8rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.6);margin:0 0 12px">${escHtml(a.pretitle)}</p>` : ''}
        <h1 style="font-size:clamp(1.8rem,4vw,3rem);font-weight:800;color:${escHtml(a.titleColor||'#ffffff')};line-height:1.15;margin:0 0 16px">${escHtml(a.title||'Your Big Headline')}</h1>
        ${a.subtitle ? `<p style="font-size:1.1rem;color:rgba(255,255,255,.7);max-width:560px;margin:0 auto 28px;line-height:1.6">${escHtml(a.subtitle)}</p>` : ''}
        ${a.btnLabel ? `<a href="${escHtml(a.btnUrl||'#')}" style="display:inline-block;background:${escHtml(a.btnColor||'#6366f1')};color:#fff;padding:13px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:.95rem">${escHtml(a.btnLabel)}</a>` : ''}
      </div>`;
    },
    getControls: () => [
      { tab: 'content', key: 'pretitle',   label: 'Pre-title',    type: 'text',     default: '' },
      { tab: 'content', key: 'title',      label: 'Headline',     type: 'textarea', default: 'Your Big Headline' },
      { tab: 'content', key: 'subtitle',   label: 'Subheading',   type: 'textarea', default: '' },
      { tab: 'content', key: 'btnLabel',   label: 'Button Text',  type: 'text',     default: '' },
      { tab: 'content', key: 'btnUrl',     label: 'Button URL',   type: 'url',      default: '#' },
      { tab: 'style',   key: 'bg',         label: 'Background (CSS)', type: 'text', default: 'linear-gradient(135deg,#1e1b4b,#312e81)' },
      { tab: 'style',   key: 'titleColor', label: 'Title Color',  type: 'color',    default: '#ffffff' },
      { tab: 'style',   key: 'btnColor',   label: 'Button Color', type: 'color',    default: '#6366f1' },
      { tab: 'style',   key: '_align',     label: 'Alignment',    type: 'align',    default: 'center' },
      { tab: 'style',   key: 'pad',        label: 'Padding (px)', type: 'range',    default: '80', min: 20, max: 200 },
    ],
  });

  // ── Card ─────────────────────────────────────────────
  reg('card', {
    label: 'Card', icon: '▭',
    render: a => {
      const bg     = a._bg     || '#ffffff';
      const border = a.borderColor || '#e2e8f0';
      const accent = a.accent  || '#6366f1';
      const br     = parseInt(a._radius) || 12;
      return `<div style="background:${escHtml(bg)};border:1px solid ${escHtml(border)};border-radius:${br}px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.07)">
        ${a.icon ? `<div style="font-size:2rem;margin-bottom:14px;color:${escHtml(accent)}">${escHtml(a.icon)}</div>` : ''}
        ${a.title ? `<h3 style="font-size:1.05rem;font-weight:700;margin:0 0 8px;color:${escHtml(a._color||'#111827')}">${escHtml(a.title)}</h3>` : ''}
        ${a.body  ? `<p style="font-size:.9rem;color:#6b7280;line-height:1.6;margin:0">${escHtml(a.body)}</p>` : ''}
        ${a.link  ? `<a href="${escHtml(a.linkUrl||'#')}" style="display:inline-block;margin-top:14px;color:${escHtml(accent)};font-size:.85rem;font-weight:600;text-decoration:none">${escHtml(a.link)} →</a>` : ''}
      </div>`;
    },
    getControls: () => [
      { tab: 'content', key: 'icon',        label: 'Icon / Emoji', type: 'text',     default: '' },
      { tab: 'content', key: 'title',       label: 'Title',        type: 'text',     default: 'Card Title' },
      { tab: 'content', key: 'body',        label: 'Body Text',    type: 'textarea', default: 'Card body text.' },
      { tab: 'content', key: 'link',        label: 'Link Text',    type: 'text',     default: '' },
      { tab: 'content', key: 'linkUrl',     label: 'Link URL',     type: 'url',      default: '#' },
      { tab: 'style',   key: '_bg',         label: 'Background',   type: 'color',    default: '#ffffff' },
      { tab: 'style',   key: 'accent',      label: 'Accent Color', type: 'color',    default: '#6366f1' },
      { tab: 'style',   key: '_color',      label: 'Title Color',  type: 'color',    default: '#111827' },
      { tab: 'style',   key: 'borderColor', label: 'Border Color', type: 'color',    default: '#e2e8f0' },
      { tab: 'style',   key: '_radius',     label: 'Radius (px)',  type: 'range',    default: '12', min: 0, max: 32 },
    ],
  });

  // ── List ─────────────────────────────────────────────
  reg('list', {
    label: 'List', icon: '≡',
    render: a => {
      const items = (a.items || 'Item one\nItem two\nItem three').split('\n').filter(Boolean);
      const tag   = a.ordered === 'true' ? 'ol' : 'ul';
      const st    = buildInlineStyle(a);
      const icon  = a.listIcon || '';
      const rows  = items.map(i => icon
        ? `<li style="display:flex;align-items:flex-start;gap:8px;list-style:none"><span style="flex-shrink:0">${escHtml(icon)}</span><span>${escHtml(i)}</span></li>`
        : `<li>${escHtml(i)}</li>`
      ).join('');
      return `<${tag} style="${st}list-style-position:inside;padding-left:${a.ordered==='true'||!icon?'20':'0'}px;margin:0;display:flex;flex-direction:column;gap:${escHtml(a.gap||'6')}px">${rows}</${tag}>`;
    },
    getControls: () => [
      { tab: 'content', key: 'items',    label: 'Items (one per line)', type: 'textarea', default: 'Item one\nItem two\nItem three' },
      { tab: 'content', key: 'ordered',  label: 'Ordered (numbered)',   type: 'toggle',   default: 'false' },
      { tab: 'content', key: 'listIcon', label: 'Custom Icon / Emoji',  type: 'text',     default: '' },
      { tab: 'style',   key: 'gap',      label: 'Item Spacing (px)',    type: 'range',    default: '6', min: 0, max: 24 },
      { tab: 'style',   key: '_color',   label: 'Text Color',           type: 'color',    default: '' },
      { tab: 'style',   key: '_fontSize',label: 'Font Size (px)',       type: 'range',    default: '15', min: 12, max: 32 },
    ],
  });

  // ── HTML ─────────────────────────────────────────────
  reg('html', {
    label: 'HTML', icon: '<>',
    render: a => a.html || '<p style="color:#94a3b8;font-size:.82rem;text-align:center;margin:0;padding:12px">HTML block — paste your code in the inspector</p>',
    getControls: () => [
      { tab: 'content', key: 'html', label: 'HTML Code', type: 'textarea-html', default: '' },
    ],
  });

  /* ══════════════════════════════════════════════════════
     STYLE HELPERS
     ══════════════════════════════════════════════════════ */
  function buildInlineStyle(a) {
    let s = '';
    if (a._color)      s += `color:${a._color};`;
    if (a._bg)         s += `background:${a._bg};`;
    if (a._fontSize)   s += `font-size:${a._fontSize}px;`;
    if (a._fontWeight) s += `font-weight:${a._fontWeight};`;
    if (a._align)      s += `text-align:${a._align};`;
    if (a._pad)        s += `padding:${a._pad}px;`;
    if (a._radius)     s += `border-radius:${a._radius}px;`;
    if (a._opacity)    s += `opacity:${a._opacity/100};`;
    return s;
  }

  function toEmbedUrl(url) {
    const yt = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]+)/);
    if (yt) return `https://www.youtube.com/embed/${yt[1]}`;
    const vi = url.match(/vimeo\.com\/(\d+)/);
    if (vi) return `https://player.vimeo.com/video/${vi[1]}`;
    return url;
  }

  /* ══════════════════════════════════════════════════════
     COMMON STYLE + ADVANCED CONTROLS
     ══════════════════════════════════════════════════════ */
  const COMMON_STYLE = [
    { tab:'style', key:'_color',      label:'Text Color',    type:'color',  default:'' },
    { tab:'style', key:'_bg',         label:'Background',    type:'color',  default:'' },
    { tab:'style', key:'_fontSize',   label:'Font Size (px)',type:'range',  default:'15', min:10, max:72 },
    { tab:'style', key:'_fontWeight', label:'Font Weight',   type:'select', default:'',
      options:[{value:'',label:'Default'},{value:'400',label:'Regular'},{value:'500',label:'Medium'},{value:'600',label:'Semi-Bold'},{value:'700',label:'Bold'},{value:'800',label:'Extra-Bold'}] },
    { tab:'style', key:'_align',      label:'Text Align',    type:'align',  default:'' },
    { tab:'style', key:'_pad',        label:'Padding (px)',  type:'range',  default:'0', min:0, max:80 },
    { tab:'style', key:'_radius',     label:'Border Radius', type:'range',  default:'0', min:0, max:48 },
    { tab:'style', key:'_opacity',    label:'Opacity %',     type:'range',  default:'100', min:10, max:100 },
  ];

  const COMMON_ADVANCED = [
    { tab:'advanced', key:'_class',     label:'CSS Class(es)',  type:'text',          default:'' },
    { tab:'advanced', key:'_customCss', label:'Custom CSS',     type:'textarea-code', default:'' },
    { tab:'advanced', key:'_hideTablet',label:'Hide on Tablet', type:'toggle',        default:'false' },
    { tab:'advanced', key:'_hideMobile',label:'Hide on Mobile', type:'toggle',        default:'false' },
  ];

  /* ══════════════════════════════════════════════════════
     UNDO / REDO
     ══════════════════════════════════════════════════════ */
  const history = {
    stack: [JSON.stringify(window.KronosBuilderAST || [])],
    pointer: 0,
    push(ast) {
      this.stack = this.stack.slice(0, this.pointer + 1);
      this.stack.push(JSON.stringify(ast));
      this.pointer = this.stack.length - 1;
      this.updateBtns();
    },
    undo() { if (this.pointer > 0)                    { this.pointer--; this.apply(); } },
    redo() { if (this.pointer < this.stack.length - 1){ this.pointer++; this.apply(); } },
    apply() {
      window.KronosBuilderAST = JSON.parse(this.stack[this.pointer]);
      rebuildCanvas(window.KronosBuilderAST);
      this.updateBtns();
    },
    updateBtns() {
      const u = document.getElementById('builder-undo');
      const r = document.getElementById('builder-redo');
      if (u) u.disabled = this.pointer <= 0;
      if (r) r.disabled = this.pointer >= this.stack.length - 1;
    },
  };

  /* ══════════════════════════════════════════════════════
     CANVAS REBUILD
     ══════════════════════════════════════════════════════ */
  function rebuildCanvas(ast) {
    const canvas = document.getElementById('builder-canvas');
    if (!canvas) return;
    canvas.innerHTML = '';
    if (!ast.length) {
      canvas.innerHTML = `<div class="canvas-empty-state" id="builder-empty-hint">
        <div class="canvas-empty-icon">✦</div>
        <strong>Drag a block here to start</strong>
        <span>or click <kbd>+</kbd> to add one</span>
      </div>`;
      return;
    }
    renderAddSlot(ast, -1, canvas);
    ast.forEach((block, i) => { renderBlock(block, canvas, ast); renderAddSlot(ast, i, canvas); });
  }

  function renderAddSlot(ast, insertAfterIdx, canvas) {
    const slot        = document.createElement('div');
    slot.className    = 'canvas-add-slot';
    slot.dataset.insertAfter = String(insertAfterIdx);
    slot.innerHTML    = '<button class="canvas-add-btn" tabindex="-1" title="Add block here">+</button>';

    slot.addEventListener('dragover', e => {
      if (e.dataTransfer.types.includes('widget-type')) { e.preventDefault(); slot.classList.add('drag-over'); }
    });
    slot.addEventListener('dragleave', () => slot.classList.remove('drag-over'));
    slot.addEventListener('drop', e => {
      const type = e.dataTransfer.getData('widget-type');
      if (!type) return;
      e.preventDefault();
      slot.classList.remove('drag-over');
      addBlockAt(type, parseInt(slot.dataset.insertAfter) + 1, ast);
    });
    slot.querySelector('.canvas-add-btn').addEventListener('click', e => {
      e.stopPropagation();
      showQuickAddPopover(slot, parseInt(slot.dataset.insertAfter) + 1, ast);
    });
    canvas.appendChild(slot);
  }

  function addBlockAt(type, idx, ast) {
    const hint = document.getElementById('builder-empty-hint');
    if (hint) hint.remove();
    const block = { id: uid(), type, attrs: {} };
    ast.splice(idx, 0, block);
    window.KronosBuilderAST = ast;
    rebuildCanvas(ast);
    saveAst(ast);
    setTimeout(() => {
      const el = document.querySelector(`[data-block-id="${block.id}"]`);
      if (el) el.click();
    }, 60);
  }

  /* ══════════════════════════════════════════════════════
     RENDER BLOCK
     ══════════════════════════════════════════════════════ */
  function renderBlock(block, canvas, ast) {
    const widget = Widgets[block.type] || null;
    const el     = document.createElement('div');
    el.className = 'canvas-block';
    el.dataset.blockId = block.id;
    el.draggable = true;

    // Action bar
    const bar = document.createElement('div');
    bar.className = 'block-actions';
    const lbl = document.createElement('span');
    lbl.className = 'block-label';
    lbl.innerHTML = `<span class="block-label-dot"></span>${escHtml(widget?.label||block.type)}`;
    bar.appendChild(lbl);
    const right = document.createElement('div');
    right.className = 'block-actions-right';
    right.appendChild(makeBtn('↑','Move up',    () => moveBlock(block.id,-1,ast)));
    right.appendChild(makeBtn('↓','Move down',  () => moveBlock(block.id,1,ast)));
    right.appendChild(makeBtn('⧉','Duplicate',   () => dupBlock(block.id,ast)));
    const del = makeBtn('✕','Delete block', () => delBlock(block.id,ast));
    del.classList.add('btn-del');
    right.appendChild(del);
    bar.appendChild(right);
    el.appendChild(bar);

    // Content
    const content = document.createElement('div');
    content.className = 'block-content';
    content.innerHTML = widget ? widget.render(block.attrs||{}) : `<span style="color:#9ca3af;font-size:.8rem">[unknown: ${escHtml(block.type)}]</span>`;
    if (block.attrs?._class) el.classList.add(...block.attrs._class.split(' ').filter(Boolean));
    if (block.attrs?._customCss) {
      const s = document.createElement('style');
      s.textContent = block.attrs._customCss;
      content.appendChild(s);
    }
    el.appendChild(content);

    // Drag-reorder
    el.addEventListener('dragstart', e => {
      e.dataTransfer.setData('block-id', block.id);
      e.dataTransfer.effectAllowed = 'move';
      setTimeout(() => el.style.opacity = '0.35', 0);
    });
    el.addEventListener('dragend', () => el.style.opacity = '');
    el.addEventListener('dragover', e => { if (e.dataTransfer.types.includes('block-id')) { e.preventDefault(); el.classList.add('drag-over-block'); }});
    el.addEventListener('dragleave', () => el.classList.remove('drag-over-block'));
    el.addEventListener('drop', e => {
      e.preventDefault(); el.classList.remove('drag-over-block');
      const srcId = e.dataTransfer.getData('block-id');
      if (!srcId || srcId === block.id) return;
      const si = ast.findIndex(b=>b.id===srcId), di = ast.findIndex(b=>b.id===block.id);
      if (si<0||di<0) return;
      const [m] = ast.splice(si,1); ast.splice(di,0,m);
      rebuildCanvas(ast); saveAst(ast);
    });

    el.addEventListener('click', e => {
      if (e.target.closest('.block-actions button')) return;
      document.querySelectorAll('.canvas-block').forEach(b=>b.classList.remove('selected'));
      el.classList.add('selected');
      renderInspector(block, ast);
    });

    canvas.appendChild(el);
  }

  function moveBlock(id, dir, ast) {
    const i = ast.findIndex(b=>b.id===id), j = i+dir;
    if (j<0||j>=ast.length) return;
    [ast[i],ast[j]] = [ast[j],ast[i]];
    rebuildCanvas(ast); saveAst(ast);
  }
  function dupBlock(id, ast) {
    const i = ast.findIndex(b=>b.id===id); if(i<0) return;
    const nb = {...ast[i], id:uid(), attrs:{...ast[i].attrs}};
    ast.splice(i+1,0,nb); rebuildCanvas(ast); saveAst(ast);
  }
  function delBlock(id, ast) {
    const i = ast.findIndex(b=>b.id===id); if(i<0) return;
    ast.splice(i,1); rebuildCanvas(ast); saveAst(ast); clearInspector();
  }

  /* ══════════════════════════════════════════════════════
     INSPECTOR — 3 TABS: Content | Style | Advanced
     ══════════════════════════════════════════════════════ */
  let inspectorTab = 'content';

  function clearInspector() {
    const insp = document.getElementById('builder-inspector');
    if (!insp) return;
    insp.innerHTML = `<div class="inspector-empty" id="inspector-empty-state">
      <div class="inspector-empty-icon">🎨</div>
      <div class="inspector-empty-title">No block selected</div>
      <div class="inspector-empty-label">Click any block on the canvas<br>to edit its properties</div>
    </div>`;
  }

  function renderInspector(block, ast) {
    const insp   = document.getElementById('builder-inspector');
    if (!insp) return;
    const widget = Widgets[block.type];
    const widgetCtrl = widget ? widget.getControls() : [];
    const widgetStyleKeys = new Set(widgetCtrl.filter(c=>c.tab==='style').map(c=>c.key));
    const allCtrl = [
      ...widgetCtrl.filter(c=>c.tab==='content'),
      ...widgetCtrl.filter(c=>c.tab==='style'),
      ...COMMON_STYLE.filter(c=>!widgetStyleKeys.has(c.key)),
      ...COMMON_ADVANCED,
    ];

    insp.innerHTML = '';

    // Block header
    const hdr = document.createElement('div');
    hdr.className = 'inspector-block-type';
    hdr.innerHTML = `<span class="inspector-type-pill">${escHtml(widget?.label||block.type)}</span>`;
    insp.appendChild(hdr);

    // Tabs
    const tabBar = document.createElement('div');
    tabBar.className = 'inspector-tabs';
    ['content','style','advanced'].forEach(t => {
      const btn = document.createElement('button');
      btn.className = 'inspector-tab'+(inspectorTab===t?' active':'');
      btn.textContent = t.charAt(0).toUpperCase()+t.slice(1);
      btn.dataset.tab = t;
      btn.addEventListener('click', () => { inspectorTab = t; renderInspector(block, ast); });
      tabBar.appendChild(btn);
    });
    insp.appendChild(tabBar);

    const activeCtrl = allCtrl.filter(c=>c.tab===inspectorTab);
    if (!activeCtrl.length) {
      const msg = document.createElement('div');
      msg.className = 'inspector-tab-empty';
      msg.textContent = 'No controls on this tab.';
      insp.appendChild(msg);
      return;
    }

    activeCtrl.forEach(ctrl => buildControl(ctrl, block, ast, insp));
  }

  function buildControl(ctrl, block, ast, insp) {
    const group = document.createElement('div');
    group.className = 'inspector-form-group';
    const cur = block.attrs[ctrl.key] !== undefined ? String(block.attrs[ctrl.key]) : String(ctrl.default ?? '');

    const lbl = document.createElement('label');
    lbl.textContent = ctrl.label;

    switch (ctrl.type) {
      case 'color': {
        const wrap = document.createElement('div'); wrap.className = 'color-control';
        const ci   = document.createElement('input'); ci.type = 'color'; ci.value = /^#[0-9a-f]{6}$/i.test(cur) ? cur : '#ffffff';
        const hi   = document.createElement('input'); hi.type = 'text';  hi.value = cur; hi.placeholder = 'e.g. #6366f1'; hi.className = 'color-hex-input';
        ci.addEventListener('input', () => { hi.value = ci.value; updateAttr(block, ctrl.key, ci.value, ast); });
        hi.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(hi.value)) ci.value = hi.value; updateAttr(block, ctrl.key, hi.value, ast); });
        wrap.appendChild(ci); wrap.appendChild(hi);
        group.appendChild(lbl); group.appendChild(wrap); insp.appendChild(group); return;
      }
      case 'range': {
        const wrap = document.createElement('div'); wrap.className = 'range-control';
        const ri   = document.createElement('input'); ri.type = 'range'; ri.min = String(ctrl.min??0); ri.max = String(ctrl.max??100); ri.value = cur;
        const num  = document.createElement('span');  num.className = 'range-num'; num.textContent = cur;
        ri.addEventListener('input', () => { num.textContent = ri.value; updateAttr(block, ctrl.key, ri.value, ast); });
        wrap.appendChild(ri); wrap.appendChild(num);
        group.appendChild(lbl); group.appendChild(wrap); insp.appendChild(group); return;
      }
      case 'align': {
        const wrap = document.createElement('div'); wrap.className = 'align-control';
        ['left','center','right','justify'].forEach(v => {
          const b = document.createElement('button');
          b.className = 'align-btn'+(cur===v?' active':'');
          b.dataset.val = v; b.title = v;
          b.textContent = {left:'⬅',center:'↔',right:'➡',justify:'⇔'}[v];
          b.addEventListener('click', () => {
            wrap.querySelectorAll('.align-btn').forEach(x=>x.classList.remove('active'));
            b.classList.add('active'); updateAttr(block, ctrl.key, v, ast);
          });
          wrap.appendChild(b);
        });
        group.appendChild(lbl); group.appendChild(wrap); insp.appendChild(group); return;
      }
      case 'toggle': {
        const wrap = document.createElement('label'); wrap.className = 'toggle-control';
        const cb   = document.createElement('input'); cb.type = 'checkbox'; cb.checked = cur === 'true';
        const track= document.createElement('span');  track.className = 'toggle-track';
        const ltxt = document.createElement('span');  ltxt.className = 'toggle-label-text'; ltxt.textContent = ctrl.label;
        cb.addEventListener('change', () => updateAttr(block, ctrl.key, String(cb.checked), ast));
        wrap.appendChild(cb); wrap.appendChild(track); wrap.appendChild(ltxt);
        group.appendChild(wrap); insp.appendChild(group); return;
      }
      case 'select': {
        const sel = document.createElement('select');
        (ctrl.options||[]).forEach(o => {
          const opt = document.createElement('option'); opt.value = o.value; opt.textContent = o.label;
          if (cur === String(o.value)) opt.selected = true;
          sel.appendChild(opt);
        });
        sel.addEventListener('change', () => updateAttr(block, ctrl.key, sel.value, ast));
        group.appendChild(lbl); group.appendChild(sel); insp.appendChild(group); return;
      }
      case 'textarea': case 'textarea-html': case 'textarea-code': {
        const ta = document.createElement('textarea'); ta.value = cur; ta.rows = ctrl.type === 'textarea' ? 3 : 5;
        if (ctrl.type !== 'textarea') ta.classList.add('code-textarea');
        ta.addEventListener('input', () => updateAttr(block, ctrl.key, ta.value, ast));
        group.appendChild(lbl); group.appendChild(ta); insp.appendChild(group); return;
      }
      default: {
        const inp = document.createElement('input'); inp.type = ctrl.type||'text'; inp.value = cur; inp.placeholder = ctrl.default??'';
        inp.addEventListener('input', () => updateAttr(block, ctrl.key, inp.value, ast));
        group.appendChild(lbl); group.appendChild(inp); insp.appendChild(group); return;
      }
    }
  }

  function updateAttr(block, key, value, ast) {
    block.attrs[key] = value;
    const el     = document.querySelector(`[data-block-id="${block.id}"] .block-content`);
    const widget = Widgets[block.type];
    if (el && widget) el.innerHTML = widget.render(block.attrs);
    saveAst(ast);
  }

  /* ══════════════════════════════════════════════════════
     QUICK-ADD POPOVER
     ══════════════════════════════════════════════════════ */
  let _qapIdx = 0, _qapAst = null;

  function showQuickAddPopover(anchorEl, insertIdx, ast) {
    _qapIdx = insertIdx; _qapAst = ast;
    const pop  = document.getElementById('quick-add-popover');
    const grid = document.getElementById('qap-grid');
    if (!pop||!grid) return;
    grid.innerHTML = '';
    (window.KronosBuilderWidgets||[]).forEach(w => {
      const btn = document.createElement('button');
      btn.className = 'qap-btn';
      btn.innerHTML = `<span class="qap-icon" style="background:${w.color}1e;color:${w.color};border-color:${w.color}33">${w.icon}</span><span>${w.label}</span>`;
      btn.addEventListener('click', () => { addBlockAt(w.type, _qapIdx, _qapAst); closePop(); });
      grid.appendChild(btn);
    });

    const rect   = anchorEl.getBoundingClientRect();
    const scroll = document.getElementById('canvas-scroll');
    const sRect  = scroll ? scroll.getBoundingClientRect() : {left:0, top:0};
    pop.style.top  = (rect.bottom - sRect.top + 6) + 'px';
    pop.style.left = Math.max(8, rect.left - sRect.left - 40) + 'px';
    pop.style.display = 'block';
    setTimeout(() => document.addEventListener('click', closePop, {once:true}), 50);
  }
  function closePop() {
    const pop = document.getElementById('quick-add-popover');
    if (pop) pop.style.display = 'none';
  }

  /* ══════════════════════════════════════════════════════
     CANVAS DROP FROM PALETTE
     ══════════════════════════════════════════════════════ */
  function initCanvasDrop() {
    const canvas = document.getElementById('builder-canvas');
    if (!canvas) return;
    const ast = window.KronosBuilderAST || [];
    canvas.addEventListener('dragover', e => {
      if (e.dataTransfer.types.includes('widget-type')) { e.preventDefault(); canvas.classList.add('drag-over'); }
    });
    canvas.addEventListener('dragleave', e => { if (!canvas.contains(e.relatedTarget)) canvas.classList.remove('drag-over'); });
    canvas.addEventListener('drop', e => {
      const type = e.dataTransfer.getData('widget-type');
      if (!type) return;
      e.preventDefault(); canvas.classList.remove('drag-over');
      addBlockAt(type, ast.length, ast);
    });
    rebuildCanvas(ast);
  }

  /* ══════════════════════════════════════════════════════
     PALETTE FILTER + DRAG
     ══════════════════════════════════════════════════════ */
  function initPalette() {
    document.querySelectorAll('.palette-tab').forEach(tab => {
      tab.addEventListener('click', function() {
        document.querySelectorAll('.palette-tab').forEach(t=>t.classList.remove('active'));
        this.classList.add('active');
        const cat = this.dataset.cat;
        document.querySelectorAll('.widget-tile').forEach(tile => {
          tile.hidden = cat !== '' && tile.dataset.cat !== cat;
        });
      });
    });

    const search = document.getElementById('palette-search');
    if (search) {
      search.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('.widget-tile').forEach(tile => { tile.hidden = q !== '' && !tile.dataset.label.includes(q); });
        if (q) document.querySelectorAll('.palette-tab').forEach(t=>t.classList.remove('active'));
      });
    }

    document.querySelectorAll('.widget-tile').forEach(tile => {
      tile.addEventListener('dragstart', e => { e.dataTransfer.setData('widget-type', tile.dataset.widgetType); e.dataTransfer.effectAllowed = 'copy'; tile.classList.add('dragging'); });
      tile.addEventListener('dragend',   () => tile.classList.remove('dragging'));
    });
  }

  /* ══════════════════════════════════════════════════════
     SAVE (debounced)
     ══════════════════════════════════════════════════════ */
  function saveAst(ast) {
    history.push([...ast]);
    clearTimeout(saveAst._t);
    saveAst._t = setTimeout(async () => {
      const status = document.getElementById('builder-save-status');
      if (status) { status.textContent = 'Saving…'; status.className = 'builder-save-status saving'; }
      const res = API ? await API.api('/builder/layouts/'+meta.id, 'PUT', { content: JSON.stringify(ast) }) : null;
      if (status) {
        const ok = res?.success;
        status.textContent = ok ? 'Saved ✓' : 'Error saving';
        status.className   = 'builder-save-status '+(ok?'saved':'error');
        setTimeout(() => { status.textContent = 'Auto-saved'; status.className = 'builder-save-status'; }, 2000);
      }
    }, 600);
  }

  /* ══════════════════════════════════════════════════════
     ZOOM
     ══════════════════════════════════════════════════════ */
  let zoom = 100;
  function setZoom(z) {
    zoom = Math.min(150, Math.max(50, z));
    const w = document.getElementById('canvas-scale-wrapper');
    const l = document.getElementById('canvas-topbar-zoom');
    if (w) { w.style.transform = `scale(${zoom/100})`; w.style.transformOrigin = 'top center'; }
    if (l) l.textContent = zoom + '%';
  }

  /* ══════════════════════════════════════════════════════
     TOPBAR: undo/redo + name + save + preview + viewport + zoom
     ══════════════════════════════════════════════════════ */
  function initTopbar() {
    const area = document.querySelector('.topbar-actions');
    if (area) {
      const undo = document.createElement('button');
      undo.id = 'builder-undo'; undo.className = 'btn btn-ghost btn-sm'; undo.innerHTML = '↩'; undo.title = 'Undo (Ctrl+Z)'; undo.disabled = true;
      undo.addEventListener('click', () => history.undo());
      const redo = document.createElement('button');
      redo.id = 'builder-redo'; redo.className = 'btn btn-ghost btn-sm'; redo.innerHTML = '↪'; redo.title = 'Redo (Ctrl+Y)'; redo.disabled = true;
      redo.addEventListener('click', () => history.redo());
      area.prepend(redo); area.prepend(undo);
    }

    const nameInput = document.getElementById('builder-layout-name');
    const status    = document.getElementById('builder-save-status');
    nameInput?.addEventListener('change', async () => {
      const res = API ? await API.api('/builder/layouts/'+meta.id, 'PUT', { name: nameInput.value }) : null;
      if (res?.success && status) { status.textContent = 'Saved ✓'; setTimeout(() => status.textContent = 'Auto-saved', 2000); }
    });

    document.getElementById('builder-save-btn')?.addEventListener('click', async () => {
      const btn = document.getElementById('builder-save-btn');
      btn.disabled = true; btn.textContent = '…';
      const ast = window.KronosBuilderAST || [];
      const res = API ? await API.api('/builder/layouts/'+meta.id, 'PUT', { name: nameInput?.value||'', content: JSON.stringify(ast) }) : null;
      btn.disabled = false; btn.textContent = '💾 Save';
      if (status) { status.textContent = res?.success ? 'Saved ✓' : 'Error'; setTimeout(() => status.textContent = 'Auto-saved', 2000); }
    });

    document.getElementById('builder-preview-btn')?.addEventListener('click', () => {
      const canvas = document.getElementById('builder-canvas');
      if (!canvas) return;
      const win = window.open('', '_blank', 'width=1200,height=800');
      win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Preview</title><style>body{font-family:-apple-system,sans-serif;margin:0;padding:24px 40px;line-height:1.6;color:#111}*{box-sizing:border-box}</style></head><body>${canvas.innerHTML}</body></html>`);
      win.document.close();
    });

    document.querySelectorAll('.canvas-vp-btn[data-vp]').forEach(btn => {
      btn.addEventListener('click', function() {
        document.querySelectorAll('.canvas-vp-btn[data-vp]').forEach(b=>b.classList.remove('active'));
        this.classList.add('active');
        const inner = document.getElementById('builder-canvas');
        if (!inner) return;
        inner.classList.remove('vp-tablet','vp-mobile');
        if (this.dataset.vp === 'tablet') inner.classList.add('vp-tablet');
        if (this.dataset.vp === 'mobile') inner.classList.add('vp-mobile');
      });
    });

    document.getElementById('canvas-zoom-in')?.addEventListener('click',  () => setZoom(zoom + 10));
    document.getElementById('canvas-zoom-out')?.addEventListener('click', () => setZoom(zoom - 10));
  }

  /* ══════════════════════════════════════════════════════
     KEYBOARD SHORTCUTS
     ══════════════════════════════════════════════════════ */
  function initKeyboard() {
    document.addEventListener('keydown', e => {
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
      const mod = e.ctrlKey || e.metaKey;
      if (mod && e.key==='z' && !e.shiftKey) { e.preventDefault(); history.undo(); }
      if (mod && (e.key==='y'||(e.key==='z'&&e.shiftKey))) { e.preventDefault(); history.redo(); }
      if (mod && e.key==='s') { e.preventDefault(); saveAst(window.KronosBuilderAST||[]); }
      if (mod && e.key==='d') {
        e.preventDefault();
        const sel = document.querySelector('.canvas-block.selected');
        if (sel) dupBlock(sel.dataset.blockId, window.KronosBuilderAST||[]);
      }
      if (!mod && (e.key==='Delete'||e.key==='Backspace')) {
        const sel = document.querySelector('.canvas-block.selected');
        if (sel && document.activeElement === document.body) { e.preventDefault(); delBlock(sel.dataset.blockId, window.KronosBuilderAST||[]); }
      }
    });
  }

  /* ══════════════════════════════════════════════════════
     UTILITIES
     ══════════════════════════════════════════════════════ */
  function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }
  function uid() { return 'b_' + Date.now().toString(36) + Math.random().toString(36).slice(2,7); }
  function makeBtn(icon, title, onClick) {
    const btn = document.createElement('button');
    btn.className = 'block-action-btn'; btn.textContent = icon; btn.title = title;
    btn.addEventListener('click', e => { e.stopPropagation(); onClick(); });
    return btn;
  }

  /* ══════════════════════════════════════════════════════
     BOOTSTRAP
     ══════════════════════════════════════════════════════ */
  document.addEventListener('DOMContentLoaded', () => {
    initTopbar();
    initCanvasDrop();
    initPalette();
    initKeyboard();
  });
})();
