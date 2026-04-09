<?php
declare(strict_types=1);
$pageTitle = 'Templates';
$dashDir   = dirname(__DIR__);

// ─── Handle import POST ───────────────────────────────────────────────
$importedId      = null;
$importError     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['template'])) {
    kronos_verify_csrf();

    $slug = preg_replace('/[^a-z0-9\-]/', '', $_POST['template'] ?? '');
    if ($slug !== '') {
        // KRONOS_ROOT is defined as dirname(__DIR__) from public/index.php
        $layoutsDir   = rtrim(KRONOS_ROOT, '/\\') . '/themes/kronos-default/layouts/';
        $templateFile = $layoutsDir . $slug . '.json';

        if (is_file($templateFile)) {
            $raw = file_get_contents($templateFile);
            $data = $raw !== false ? json_decode($raw, true) : null;

            if (is_array($data)) {
                $db       = $app->db();
                $now      = date('Y-m-d H:i:s');
                $title    = $data['title'] ?? ucwords(str_replace(['-', '_'], ' ', $slug));

                $importedId = $db->insert('kronos_builder_layouts', [
                    'layout_name' => $title,
                    'layout_type' => 'page',
                    'json_data'   => json_encode($data),
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            } else {
                $importError = 'Template file is invalid or corrupt.';
            }
        } else {
            $importError = 'Template not found.';
        }
    }

    if ($importedId) {
        kronos_redirect('/dashboard/builder/' . (int) $importedId);
    }
}

// ─── Scan available templates ─────────────────────────────────────────
$layoutsDir = rtrim(KRONOS_ROOT, '/\\') . '/themes/kronos-default/layouts/';
$templates  = [];

if (is_dir($layoutsDir)) {
    foreach (glob($layoutsDir . '*.json') ?: [] as $file) {
        $raw  = file_get_contents($file);
        $data = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            continue;
        }
        $meta = $data['meta'] ?? [];
        $slug = $data['slug'] ?? basename($file, '.json');

        $templates[] = [
            'slug'        => $slug,
            'title'       => $data['title'] ?? $meta['title'] ?? ucwords(str_replace('-', ' ', $slug)),
            'description' => $meta['description'] ?? '',
            'mode'        => $meta['mode'] ?? 'cms',
            'emoji'       => $meta['emoji'] ?? '📄',
        ];
    }
}

// Group by mode
$cmsTemplates   = array_values(array_filter($templates, fn($t) => $t['mode'] === 'cms'));
$ecomTemplates  = array_values(array_filter($templates, fn($t) => $t['mode'] === 'ecommerce'));

require $dashDir . '/partials/layout-header.php';
?>

<?php if ($importError): ?>
<div class="alert alert-error" style="margin-bottom:20px">
  <?= kronos_e($importError) ?>
</div>
<?php endif; ?>

<div class="templates-intro" style="margin-bottom:2rem">
  <p style="color:var(--text-muted);margin:0">
    Pick a pre-built layout to get started fast. Importing opens it instantly in the Builder so you can
    customise every block, colour, and font.
  </p>
</div>

<?php
$csrf = kronos_csrf_token();

$renderSection = function(string $title, array $items) use ($csrf): void {
    if (empty($items)) return;
    echo '<h2 class="templates-section-title">' . kronos_e($title) . '</h2>';
    echo '<div class="templates-grid">';
    foreach ($items as $t):
        $slug  = kronos_e($t['slug']);
        $label = kronos_e($t['title']);
        $desc  = kronos_e($t['description']);
        $emoji = kronos_e($t['emoji']);
        $mode  = kronos_e($t['mode']);
        $badge = $mode === 'ecommerce' ? 'badge-commerce' : 'badge-cms';
        $modeLabel = $mode === 'ecommerce' ? 'Ecommerce' : 'CMS';
        ?>
        <div class="template-card">
          <div class="template-thumb"><?= $emoji ?></div>
          <div class="template-info">
            <h3><?= $label ?></h3>
            <p><?= $desc ?></p>
            <span class="badge <?= $badge ?>"><?= $modeLabel ?></span>
          </div>
          <div class="template-footer">
            <form method="post" action="<?= kronos_url('/dashboard/templates') ?>">
              <input type="hidden" name="_kronos_csrf" value="<?= kronos_e(kronos_csrf_token()) ?>">
              <input type="hidden" name="template" value="<?= $slug ?>">
              <button type="submit" class="btn btn-primary btn-sm">Use Template</button>
            </form>
          </div>
        </div>
        <?php
    endforeach;
    echo '</div>';
};

if ($cmsTemplates) {
    $renderSection('CMS Templates', $cmsTemplates);
}

if ($ecomTemplates) {
    $renderSection('Ecommerce Templates', $ecomTemplates);
}

if (empty($templates)): ?>
<div class="empty-state">
  <p>No templates found in <code>themes/kronos-default/layouts/</code>.</p>
</div>
<?php endif; ?>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
