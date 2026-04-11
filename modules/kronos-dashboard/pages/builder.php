<?php
declare(strict_types=1);

/**
 * Page Builder Dashboard page.
 * Route: GET /dashboard/builder[?layout_id=N]
 *
 * Renders the drag-and-drop builder shell. The actual canvas logic lives in
 * builder.js (loaded by layout-footer.php). This page:
 *  - Injects window.KronosBuilderAST  (existing layout JSON or empty array)
 *  - Injects window.KronosBuilderMeta (id, name)
 *  - Renders the three-panel layout: Widgets | Canvas | Inspector
 */

$user = $_REQUEST['_kronos_user'] ?? null;
$app  = \Kronos\Core\KronosApp::getInstance();
$db   = $app->db();

$layoutId = (int) ($_GET['layout_id'] ?? 0);
$layout   = null;

if ($layoutId > 0) {
    $layout = $db->getRow('SELECT * FROM kronos_builder_layouts WHERE id = ?', [$layoutId]);
}

// If no layout given or not found, create a new one on GET
if (!$layout) {
    $name     = 'Untitled Layout ' . date('Y-m-d H:i');
    $layoutId = (int) $db->insert('kronos_builder_layouts', [
        'layout_name' => $name,
        'layout_type' => 'page',
        'json_data'   => '[]',
        'created_at'  => date('Y-m-d H:i:s'),
        'updated_at'  => date('Y-m-d H:i:s'),
    ]);
    $layout = ['id' => $layoutId, 'layout_name' => $name, 'json_data' => '[]'];
}

$ast  = json_decode((string) ($layout['json_data'] ?? '[]'), true);
if (!is_array($ast)) $ast = [];

// Widget categories for the palette
$widgetCategories = [
    'Basic' => [
        ['type' => 'heading',    'label' => 'Heading',    'icon' => 'H',   'color' => '#6366f1', 'desc' => 'Title or subtitle'],
        ['type' => 'text',       'label' => 'Text',       'icon' => '¶',   'color' => '#8b5cf6', 'desc' => 'Paragraph of text'],
        ['type' => 'button',     'label' => 'Button',     'icon' => '⬡',   'color' => '#0891b2', 'desc' => 'CTA or link button'],
        ['type' => 'divider',    'label' => 'Divider',    'icon' => '—',   'color' => '#64748b', 'desc' => 'Horizontal separator'],
        ['type' => 'spacer',     'label' => 'Spacer',     'icon' => '↕',   'color' => '#475569', 'desc' => 'Empty vertical space'],
    ],
    'Media' => [
        ['type' => 'image',      'label' => 'Image',      'icon' => '🖼',  'color' => '#10b981', 'desc' => 'Photo or graphic'],
        ['type' => 'video',      'label' => 'Video',      'icon' => '▶',   'color' => '#ef4444', 'desc' => 'Embed YouTube / Vimeo'],
        ['type' => 'icon',       'label' => 'Icon',       'icon' => '★',   'color' => '#f59e0b', 'desc' => 'Emoji or icon symbol'],
    ],
    'Layout' => [
        ['type' => 'columns',    'label' => 'Columns',    'icon' => '⊞',   'color' => '#0e7490', 'desc' => '2 or 3 column layout'],
        ['type' => 'container',  'label' => 'Container',  'icon' => '▤',   'color' => '#334155', 'desc' => 'Wrapper / section'],
        ['type' => 'hero-block', 'label' => 'Hero',       'icon' => '⚡',   'color' => '#7c3aed', 'desc' => 'Big hero banner'],
    ],
    'Content' => [
        ['type' => 'card',       'label' => 'Card',       'icon' => '▭',   'color' => '#0f766e', 'desc' => 'Bordered content card'],
        ['type' => 'list',       'label' => 'List',       'icon' => '≡',   'color' => '#15803d', 'desc' => 'Bullet or numbered list'],
        ['type' => 'html',       'label' => 'HTML',       'icon' => '<>',  'color' => '#b45309', 'desc' => 'Custom HTML / embed'],
    ],
];

// Flattened for JS injection
$widgetsFlat = array_merge(...array_values($widgetCategories));

$pageTitle   = 'Builder — ' . ($layout['layout_name'] ?? 'Untitled');
$builderPage = true;
$topbarExtra = '
  <input type="text" id="builder-layout-name"
    value="' . htmlspecialchars($layout['layout_name'] ?? '', ENT_QUOTES) . '"
    placeholder="Layout name…">
  <span id="builder-save-status" class="builder-save-status">Auto-saved</span>
  <button id="builder-preview-btn" class="btn btn-ghost btn-sm topbar-btn" title="Preview (P)">👁 Preview</button>
  <button id="builder-save-btn" class="btn btn-primary btn-sm">💾 Save</button>
  <a href="' . kronos_url('/dashboard/content') . '" class="btn btn-ghost btn-sm topbar-btn">← Exit</a>';
$dashDir     = dirname(__DIR__);
require $dashDir . '/partials/layout-header.php';
?>

<!-- ── Three-panel Builder Layout ── -->
<div class="builder-layout">

  <!-- ① Widget Palette -->
  <div class="builder-panel" id="builder-palette">
    <div class="palette-search-wrap">
      <input type="search" id="palette-search" class="palette-search" placeholder="Search blocks…" autocomplete="off">
    </div>
    <div class="palette-tabs">
      <button class="palette-tab active" data-cat="">All</button>
      <?php foreach (array_keys($widgetCategories) as $cat): ?>
      <button class="palette-tab" data-cat="<?= kronos_e($cat) ?>"><?= kronos_e($cat) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="palette-body">
      <div class="widget-grid" id="widget-grid">
        <?php foreach ($widgetCategories as $cat => $widgets): ?>
          <?php foreach ($widgets as $w): ?>
          <div class="widget-tile" draggable="true"
               data-widget-type="<?= kronos_e($w['type']) ?>"
               data-cat="<?= kronos_e($cat) ?>"
               data-label="<?= strtolower(kronos_e($w['label'])) ?>"
               title="<?= kronos_e($w['desc']) ?>">
            <div class="widget-tile-icon" style="background:<?= kronos_e($w['color']) ?>1e;border-color:<?= kronos_e($w['color']) ?>33;color:<?= kronos_e($w['color']) ?>"><?= kronos_e($w['icon']) ?></div>
            <span class="widget-tile-label"><?= kronos_e($w['label']) ?></span>
          </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ② Canvas -->
  <div class="builder-canvas">
    <div class="canvas-topbar">
      <div class="canvas-vp-group">
        <button class="canvas-vp-btn active" data-vp="desktop" title="Desktop (D)">🖥</button>
        <button class="canvas-vp-btn" data-vp="tablet"  title="Tablet (T)">⬛</button>
        <button class="canvas-vp-btn" data-vp="mobile"  title="Mobile (M)">📱</button>
      </div>
      <div class="canvas-topbar-center" id="canvas-topbar-zoom">100%</div>
      <div class="canvas-topbar-right">
        <button class="canvas-vp-btn" id="canvas-zoom-out" title="Zoom out">−</button>
        <button class="canvas-vp-btn" id="canvas-zoom-in"  title="Zoom in">+</button>
      </div>
    </div>
    <div class="canvas-scroll" id="canvas-scroll">
      <div class="canvas-scale-wrapper" id="canvas-scale-wrapper">
        <div class="builder-canvas-inner"
             id="builder-canvas"
             data-layout-id="<?= (int) $layout['id'] ?>">
          <?php if (empty($ast)): ?>
          <div class="canvas-empty-state" id="builder-empty-hint">
            <div class="canvas-empty-icon">✦</div>
            <strong>Drag a block here to start</strong>
            <span>or click <kbd>+</kbd> between blocks</span>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ③ Inspector -->
  <div class="builder-panel" id="builder-inspector">
    <div class="inspector-empty" id="inspector-empty-state">
      <div class="inspector-empty-icon">🎨</div>
      <div class="inspector-empty-title">No block selected</div>
      <div class="inspector-empty-label">Click any block on the canvas<br>to edit its properties</div>
    </div>
  </div>

</div>

<!-- quick-add popover -->
<div class="quick-add-popover" id="quick-add-popover">
  <div class="qap-header">Add block</div>
  <div class="qap-grid" id="qap-grid"></div>
</div>

<!-- Inject builder state -->
<script>
window.KronosBuilderAST  = <?= json_encode($ast, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.KronosBuilderMeta = {
  id:   <?= (int) $layout['id'] ?>,
  name: <?= json_encode($layout['layout_name']) ?>
};
window.KronosBuilderWidgets = <?= json_encode(array_map(fn($w) => ['type'=>$w['type'],'label'=>$w['label'],'icon'=>$w['icon'],'color'=>$w['color']], $widgetsFlat), JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php
require __DIR__ . '/../partials/layout-footer.php';
?>

<script src="<?= kronos_asset('js/builder.js') ?>"></script>
