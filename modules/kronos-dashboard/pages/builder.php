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
        'name'       => $name,
        'content'    => '[]',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $layout = ['id' => $layoutId, 'name' => $name, 'content' => '[]'];
}

$ast  = json_decode((string) ($layout['content'] ?? '[]'), true);
if (!is_array($ast)) $ast = [];

// Available widgets for the palette
$widgets = [
    ['type' => 'heading',   'label' => 'Heading',   'icon' => '𝗛'],
    ['type' => 'text',      'label' => 'Text',       'icon' => '¶'],
    ['type' => 'image',     'label' => 'Image',      'icon' => '🖼'],
    ['type' => 'button',    'label' => 'Button',     'icon' => '⬛'],
    ['type' => 'container', 'label' => 'Container',  'icon' => '⬜'],
];

do_action('kronos/builder/widgets', $widgets);

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Builder — <?= kronos_e($layout['name']) ?> — <?= kronos_e(kronos_option('app_name', 'KronosCMS')) ?></title>
<link rel="stylesheet" href="<?= kronos_asset('css/dashboard.css') ?>">
<style>
/* Override body layout for full-screen builder */
body.builder-page .main-content { overflow: hidden; }
</style>
</head>
<body class="dashboard-body builder-page">

<?php
// Render sidebar via layout-header partial (but we need $app injected already)
require __DIR__ . '/../partials/layout-header.php';
?>

<!-- ── Builder Topbar (replaces normal topbar) ── -->
<div class="topbar" style="gap:10px;">
  <a href="/dashboard/content" class="btn btn-ghost btn-sm">← Exit Builder</a>

  <div style="flex:1;padding:0 12px;">
    <input type="text" id="builder-layout-name"
           value="<?= kronos_e($layout['name']) ?>"
           style="border:none;background:transparent;font-weight:600;font-size:.95rem;width:300px;padding:4px 0;"
           placeholder="Layout name…">
  </div>

  <div class="topbar-actions">
    <span id="builder-save-status" class="text-muted text-sm">Auto-saved</span>
    <button id="builder-save-btn" class="btn btn-primary btn-sm">Save</button>
  </div>
</div>

<!-- ── Three-panel Builder Layout ── -->
<div class="builder-layout">

  <!-- Widget Palette -->
  <div class="builder-panel" id="builder-palette">
    <div class="builder-panel-header">Widgets</div>
    <?php foreach ($widgets as $w): ?>
    <div class="widget-item" draggable="true" data-widget-type="<?= kronos_e($w['type']) ?>">
      <span><?= kronos_e($w['icon']) ?></span>
      <span><?= kronos_e($w['label']) ?></span>
    </div>
    <?php endforeach; ?>

    <div class="builder-panel-header" style="margin-top:12px;">Layouts</div>
    <div class="widget-item" draggable="true" data-widget-type="container">
      <span>⬜</span><span>Section</span>
    </div>
  </div>

  <!-- Canvas -->
  <div class="builder-canvas">
    <div class="builder-canvas-inner"
         id="builder-canvas"
         data-layout-id="<?= (int) $layout['id'] ?>">
      <?php if (empty($ast)): ?>
      <div class="canvas-drop-zone" id="builder-empty-hint">
        <span>Drag a widget here to start building</span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Inspector -->
  <div class="builder-panel" id="builder-inspector">
    <div class="builder-panel-header">Inspector</div>
    <div style="padding:16px;color:var(--text-muted);font-size:.8rem;">
      Click a block on the canvas to edit its properties.
    </div>
  </div>

</div>

<!-- Inject builder state -->
<script>
window.KronosBuilderAST  = <?= json_encode($ast, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.KronosBuilderMeta = {
  id:   <?= (int) $layout['id'] ?>,
  name: <?= json_encode($layout['name']) ?>
};
</script>

<?php
// layout-footer injects window.KronosConfig and loads dashboard.js (which includes builder init)
require __DIR__ . '/../partials/layout-footer.php';
?>

<script src="<?= kronos_asset('js/builder.js') ?>"></script>

<script>
// Layout name save
(function(){
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
      if (status) status.textContent = 'Saved';
      setTimeout(() => { if (status) status.textContent = 'Auto-saved'; }, 2000);
    }
  });

  document.getElementById('builder-save-btn').addEventListener('click', async function(){
    this.disabled = true;
    this.textContent = 'Saving…';
    const res = await window.KronosDash.api(
      '/builder/layouts/' + window.KronosBuilderMeta.id,
      'PUT',
      {
        name:    nameInput.value,
        content: JSON.stringify(window.KronosBuilderAST || [])
      }
    );
    this.disabled = false;
    this.textContent = 'Save';
    if (status) {
      status.textContent = (res && res.success) ? 'Saved ✓' : 'Error saving';
      setTimeout(() => { status.textContent = 'Auto-saved'; }, 2000);
    }
  });
})();

// Remove empty-state hint once first block is dropped
document.addEventListener('drop', function(){
  var hint = document.getElementById('builder-empty-hint');
  if (hint) hint.remove();
}, { once: true, capture: true });
</script>
</body>
</html>
