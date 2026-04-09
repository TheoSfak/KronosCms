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

// Available widgets for the palette
$widgets = [
    ['type' => 'heading',   'label' => 'Heading',    'icon' => 'H₁',     'desc' => 'Title or subtitle'],
    ['type' => 'text',      'label' => 'Text',        'icon' => '¶',      'desc' => 'Paragraph content'],
    ['type' => 'image',     'label' => 'Image',       'icon' => '🖼',     'desc' => 'Photo or graphic'],
    ['type' => 'button',    'label' => 'Button',      'icon' => '⬡',      'desc' => 'CTA or link'],
    ['type' => 'container', 'label' => 'Container',   'icon' => '▤',      'desc' => 'Layout wrapper'],
];

do_action('kronos/builder/widgets', $widgets);

$pageTitle   = 'Builder — ' . ($layout['layout_name'] ?? 'Untitled');
$builderPage = true;
$topbarExtra = '
  <input type="text" id="builder-layout-name"
    value="' . htmlspecialchars($layout['layout_name'] ?? '', ENT_QUOTES) . '"
    placeholder="Layout name…">
  <span id="builder-save-status" style="font-size:.8rem">Auto-saved</span>
  <button id="builder-save-btn" class="btn btn-primary btn-sm">💾 Save</button>
  <a href="' . kronos_url('/dashboard/content') . '" class="btn btn-ghost btn-sm topbar-btn">← Exit</a>';
$dashDir     = dirname(__DIR__);
require $dashDir . '/partials/layout-header.php';
?>

<!-- ── Three-panel Builder Layout ── -->
<div class="builder-layout">

  <!-- ① Widget Palette -->
  <div class="builder-panel" id="builder-palette">
    <div class="builder-panel-header">🧩 Blocks</div>

    <div class="palette-section">Content</div>
    <?php foreach ($widgets as $w): ?>
    <div class="widget-item" draggable="true"
         data-widget-type="<?= kronos_e($w['type']) ?>"
         title="<?= kronos_e($w['desc'] ?? '') ?>">
      <span class="widget-icon"><?= kronos_e($w['icon']) ?></span>
      <span><?= kronos_e($w['label']) ?></span>
    </div>
    <?php endforeach; ?>

    <div class="palette-section" style="margin-top:8px">Layout</div>
    <div class="widget-item" draggable="true" data-widget-type="container" title="Full-width section">
      <span class="widget-icon">▤</span>
      <span>Section</span>
    </div>
  </div>

  <!-- ② Canvas -->
  <div class="builder-canvas">
    <!-- viewport switcher -->
    <div class="canvas-topbar">
      <button class="canvas-vp-btn active" data-vp="desktop" title="Desktop">🖥</button>
      <button class="canvas-vp-btn" data-vp="tablet"  title="Tablet">⬛</button>
      <button class="canvas-vp-btn" data-vp="mobile"  title="Mobile">📱</button>
      <div class="canvas-vp-sep"></div>
      <button class="canvas-vp-btn" id="canvas-zoom-fit" title="Fit to screen" style="width:auto;padding:0 8px;font-size:.68rem;letter-spacing:.04em;color:#475569">FIT</button>
    </div>
    <div class="canvas-scroll">
      <div class="builder-canvas-inner"
           id="builder-canvas"
           data-layout-id="<?= (int) $layout['id'] ?>">
        <?php if (empty($ast)): ?>
        <div class="canvas-drop-zone" id="builder-empty-hint">
          <span class="drop-icon">✦</span>
          <strong style="color:#374151;font-size:.9rem">Drop a block to start building</strong>
          <span style="font-size:.8rem">Drag anything from the left panel</span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ③ Inspector -->
  <div class="builder-panel" id="builder-inspector">
    <div class="builder-panel-header">🔧 Inspector</div>
    <div class="inspector-empty">
      <div class="inspector-empty-icon">↖</div>
      <div class="inspector-empty-label">Select a block on<br>the canvas to edit it</div>
    </div>
  </div>

</div>

<!-- Inject builder state -->
<script>
window.KronosBuilderAST  = <?= json_encode($ast, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.KronosBuilderMeta = {
  id:   <?= (int) $layout['id'] ?>,
  name: <?= json_encode($layout['layout_name']) ?>
};
</script>

<?php
require __DIR__ . '/../partials/layout-footer.php';
?>

<script src="<?= kronos_asset('js/builder.js') ?>"></script>

<script>
(function(){
  // ── Layout name save ──
  var nameInput = document.getElementById('builder-layout-name');
  var status    = document.getElementById('builder-save-status');
  if (!nameInput) return;

  nameInput.addEventListener('change', async function(){
    const res = await window.KronosDash.api(
      '/builder/layouts/' + window.KronosBuilderMeta.id,
      'PUT',
      { name: this.value }
    );
    if (res && res.success) {
      if (status) status.textContent = 'Saved ✓';
      setTimeout(() => { if (status) status.textContent = 'Auto-saved'; }, 2000);
    }
  });

  document.getElementById('builder-save-btn').addEventListener('click', async function(){
    this.disabled = true;
    this.textContent = '…';
    const res = await window.KronosDash.api(
      '/builder/layouts/' + window.KronosBuilderMeta.id,
      'PUT',
      { name: nameInput.value, content: JSON.stringify(window.KronosBuilderAST || []) }
    );
    this.disabled = false;
    this.textContent = '💾 Save';
    if (status) {
      status.textContent = (res && res.success) ? 'Saved ✓' : 'Error saving';
      setTimeout(() => { status.textContent = 'Auto-saved'; }, 2000);
    }
  });

  // ── Viewport switcher ──
  document.querySelectorAll('.canvas-vp-btn[data-vp]').forEach(btn => {
    btn.addEventListener('click', function(){
      document.querySelectorAll('.canvas-vp-btn[data-vp]').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      const inner = document.getElementById('builder-canvas');
      if (inner) {
        inner.classList.remove('vp-tablet', 'vp-mobile');
        if (this.dataset.vp === 'tablet') inner.classList.add('vp-tablet');
        if (this.dataset.vp === 'mobile') inner.classList.add('vp-mobile');
      }
    });
  });
})();
</script>
