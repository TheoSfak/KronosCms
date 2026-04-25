<?php
declare(strict_types=1);
$pageTitle = 'Settings';
$dashDir   = dirname(__DIR__);
$cfg        = $app->config();
$db         = $app->db();
$activeMode = kronos_mode();
$appName    = $cfg->get('app_name', 'KronosCMS');
$appUrl     = $cfg->get('app_url', '');
$aiModel    = $cfg->get('openai_model', 'gpt-4o');
$tab        = $_GET['tab'] ?? 'general';
$settingsNotice = '';
$settingsError = '';
$allowedSettingsKeys = [
    'app_name', 'app_url', 'tagline', 'color_scheme', 'hero_style',
    'homepage_about_title', 'homepage_about_text',
    'homepage_stat1_num', 'homepage_stat1_label', 'homepage_stat2_num', 'homepage_stat2_label',
    'homepage_stat3_num', 'homepage_stat3_label', 'homepage_stat4_num', 'homepage_stat4_label',
    'homepage_cta_title', 'homepage_cta_sub',
    'permalink_page_base', 'permalink_post_base',
    'site_logo_url', 'site_logo_alt', 'header_layout', 'footer_layout', 'body_font', 'heading_font',
    'brand_primary_color', 'brand_accent_color', 'site_background_color',
];
$allowedTabs = ['general', 'homepage', 'customizer', 'permalinks', 'design', 'mode', 'ai', 'payments', 'tools', 'update'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'general';
}

kronos_ensure_editor_tables();
kronos_ensure_default_site_pages();
kronos_ensure_taxonomy_tables();
kronos_ensure_media_table();
kronos_ensure_menu_tables();

$siteBundleService = new \Kronos\ImportExport\SiteBundleService($app, $allowedSettingsKeys);
$pluginInventoryService = new \Kronos\Marketplace\PluginInventoryService($app);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_settings') {
    kronos_verify_csrf();
    $rawJson = trim((string) ($_POST['settings_json'] ?? ''));
    try {
        $decoded = json_decode($rawJson, true, 20, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Expected a JSON object.');
        }
        $saved = 0;
        foreach ($decoded as $key => $value) {
            if (in_array((string) $key, $allowedSettingsKeys, true) && is_scalar($value)) {
                kronos_set_option((string) $key, (string) $value);
                $saved++;
            }
        }
        $settingsNotice = $saved . ' setting(s) imported.';
    } catch (\Throwable $e) {
        $settingsError = 'Import failed: ' . $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_site_bundle') {
    kronos_verify_csrf();
    $rawJson = trim((string) ($_POST['site_bundle_json'] ?? ''));
    try {
        $decoded = json_decode($rawJson, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Expected a JSON object.');
        }
        $counts = $siteBundleService->import($decoded);
        $settingsNotice = 'Imported bundle: '
            . $counts['settings'] . ' settings, '
            . $counts['taxonomies'] . ' taxonomies, '
            . $counts['media'] . ' media records, '
            . $counts['content'] . ' content records, '
            . ($counts['comments'] ?? 0) . ' comments, '
            . $counts['menus'] . ' menus.';
    } catch (\Throwable $e) {
        $settingsError = 'Bundle import failed: ' . $e->getMessage();
    }
}

require $dashDir . '/partials/layout-header.php';
?>

<?php if ($settingsNotice): ?><div class="alert alert-success"><?= kronos_e($settingsNotice) ?></div><?php endif; ?>
<?php if ($settingsError): ?><div class="alert alert-error"><?= kronos_e($settingsError) ?></div><?php endif; ?>

<div class="settings-tabs">
  <?php foreach ($allowedTabs as $t): ?>
  <a href="<?= kronos_url('/dashboard/settings') ?>?tab=<?= $t ?>" class="settings-tab <?= $tab === $t ? 'active' : '' ?>">
    <?= match($t) {
        'general'  => '⚙️ General',
        'homepage' => '🏠 Homepage',
        'customizer' => '🖌 Customizer',
        'permalinks' => '🔗 Permalinks',
        'mode'     => '🔄 Mode',
        'ai'       => '🤖 AI',
        'payments' => '💳 Payments',
        'tools'    => '↕ Tools',
        'update'   => '🔁 Update',
        'design'   => '🎨 Design',
    } ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="card settings-panel">

<?php if ($tab === 'general'): ?>
  <h2 class="card-title">General Settings</h2>
  <form id="settings-general-form" class="settings-form">
    <div class="form-group">
      <label>App Name</label>
      <input type="text" name="app_name" value="<?= kronos_e($appName) ?>" required>
    </div>
    <div class="form-group">
      <label>App URL</label>
      <input type="url" name="app_url" value="<?= kronos_e($appUrl) ?>">
    </div>
    <div class="form-group">
      <label>Tagline</label>
      <input type="text" name="tagline" value="<?= kronos_e(kronos_option('tagline', 'Build beautiful websites without limits.')) ?>" placeholder="Your homepage hero subtitle">
    </div>
    <button type="submit" class="btn btn-primary">Save General Settings</button>
  </form>

<?php elseif ($tab === 'homepage'): ?>
  <?php
    $hAboutTitle = kronos_option('homepage_about_title', 'A platform built around you');
    $hAboutText  = kronos_option('homepage_about_text', '');
    $stats = [
      ['10+',  'Modules'],
      ['5',    'Color Schemes'],
      ['∞',    'Possibilities'],
      ['100%', 'Open Source'],
    ];
    for ($i = 1; $i <= 4; $i++) {
      $stats[$i-1][0] = kronos_option('homepage_stat'.$i.'_num',   $stats[$i-1][0]);
      $stats[$i-1][1] = kronos_option('homepage_stat'.$i.'_label', $stats[$i-1][1]);
    }
    $ctaTitle = kronos_option('homepage_cta_title', 'Start building today');
    $ctaSub   = kronos_option('homepage_cta_sub',   'Use the drag-and-drop builder to create stunning pages in minutes.');
  ?>
  <h2 class="card-title">Homepage Content</h2>
  <form id="settings-homepage-form" class="settings-form">

    <h3 style="margin:0 0 12px;font-size:1rem;font-weight:700;color:var(--text-muted)">About Section</h3>
    <div class="form-group">
      <label>About Headline</label>
      <input type="text" name="homepage_about_title" value="<?= kronos_e($hAboutTitle) ?>">
    </div>
    <div class="form-group">
      <label>About Text</label>
      <textarea name="homepage_about_text" rows="3" style="width:100%;resize:vertical"><?= kronos_e($hAboutText) ?></textarea>
      <small class="text-muted">Leave blank to use the default.</small>
    </div>

    <h3 style="margin:24px 0 12px;font-size:1rem;font-weight:700;color:var(--text-muted)">Stats Bar</h3>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px">
      <?php for ($i = 1; $i <= 4; $i++): ?>
      <div style="display:flex;flex-direction:column;gap:8px;padding:14px;background:var(--bg-alt);border-radius:10px;border:1px solid var(--border)">
        <label style="font-weight:600;font-size:.85rem">Stat <?= $i ?></label>
        <input type="text" name="homepage_stat<?= $i ?>_num" value="<?= kronos_e($stats[$i-1][0]) ?>" placeholder="Number / value" style="margin-bottom:6px">
        <input type="text" name="homepage_stat<?= $i ?>_label" value="<?= kronos_e($stats[$i-1][1]) ?>" placeholder="Label">
      </div>
      <?php endfor; ?>
    </div>

    <h3 style="margin:24px 0 12px;font-size:1rem;font-weight:700;color:var(--text-muted)">CTA Banner</h3>
    <div class="form-group">
      <label>CTA Headline</label>
      <input type="text" name="homepage_cta_title" value="<?= kronos_e($ctaTitle) ?>">
    </div>
    <div class="form-group">
      <label>CTA Subtext</label>
      <input type="text" name="homepage_cta_sub" value="<?= kronos_e($ctaSub) ?>">
    </div>

    <button type="submit" class="btn btn-primary">Save Homepage Settings</button>
  </form>

<?php elseif ($tab === 'mode'): ?>
  <h2 class="card-title">Mode Switcher</h2>
  <p class="text-muted">Current active mode: <strong><?= kronos_e(ucfirst($activeMode)) ?></strong></p>
  <p>Switching mode will change the dashboard navigation and available features. Your existing content is preserved.</p>
  <div class="mode-switch-grid">
    <button class="btn <?= $activeMode === 'cms' ? 'btn-primary' : 'btn-secondary' ?>" data-switch-mode="cms">📝 CMS Mode</button>
    <button class="btn <?= $activeMode === 'ecommerce' ? 'btn-primary' : 'btn-secondary' ?>" data-switch-mode="ecommerce">🛒 E-Commerce Mode</button>
  </div>

<?php elseif ($tab === 'customizer'): ?>
  <h2 class="card-title">Theme Customizer</h2>
  <p class="text-muted">Live preview updates as you adjust logo, colors, typography, and header/footer layout.</p>
  <div class="customizer-workbench">
  <form id="settings-customizer-form" class="settings-form customizer-controls">
      <div class="form-group">
        <label>Logo URL</label>
        <input type="text" name="site_logo_url" value="<?= kronos_e(kronos_option('site_logo_url', '')) ?>" placeholder="<?= kronos_url('/uploads/logo.png') ?>">
      </div>
      <div class="form-group">
        <label>Logo alt text</label>
        <input type="text" name="site_logo_alt" value="<?= kronos_e(kronos_option('site_logo_alt', $appName)) ?>">
      </div>
      <div class="form-row">
      <div class="form-group">
        <label>Header layout</label>
        <select name="header_layout">
          <?php foreach (['default' => 'Default', 'centered' => 'Centered navigation', 'compact' => 'Compact'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= kronos_option('header_layout', 'default') === $value ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Footer layout</label>
        <select name="footer_layout">
          <?php foreach (['columns' => 'Brand + links', 'simple' => 'Simple centered', 'stacked' => 'Stacked'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= kronos_option('footer_layout', 'columns') === $value ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Body font</label>
        <select name="body_font">
          <?php foreach (['Inter' => 'Inter', 'Arial' => 'Arial', 'Georgia' => 'Georgia', 'system-ui' => 'System UI'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= kronos_option('body_font', 'Inter') === $value ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Heading font</label>
        <select name="heading_font">
          <?php foreach (['Inter' => 'Inter', 'Arial' => 'Arial', 'Georgia' => 'Georgia', 'system-ui' => 'System UI'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= kronos_option('heading_font', 'Inter') === $value ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Primary color</label>
        <input type="color" name="brand_primary_color" value="<?= kronos_e(kronos_option('brand_primary_color', '#4f46e5') ?: '#4f46e5') ?>">
      </div>
      <div class="form-group">
        <label>Accent hover</label>
        <input type="color" name="brand_accent_color" value="<?= kronos_e(kronos_option('brand_accent_color', '#4338ca') ?: '#4338ca') ?>">
      </div>
      <div class="form-group">
        <label>Background</label>
        <input type="color" name="site_background_color" value="<?= kronos_e(kronos_option('site_background_color', '#ffffff') ?: '#ffffff') ?>">
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Save Customizer Settings</button>
  </form>
  <section class="customizer-preview" id="customizer-preview"
    data-app-name="<?= kronos_e($appName) ?>"
    data-tagline="<?= kronos_e(kronos_option('tagline', 'Build beautiful websites without limits.')) ?>">
    <div class="customizer-preview-header">
      <strong class="customizer-preview-logo"><?= kronos_e($appName) ?></strong>
      <nav><span>Home</span><span>About</span><span>Services</span></nav>
      <button type="button">Admin</button>
    </div>
    <div class="customizer-preview-hero">
      <p>Live preview</p>
      <h3><?= kronos_e($appName) ?></h3>
      <span><?= kronos_e(kronos_option('tagline', 'Build beautiful websites without limits.')) ?></span>
    </div>
    <div class="customizer-preview-footer">
      <strong><?= kronos_e($appName) ?></strong>
      <span><?= kronos_e(kronos_option('footer_layout', 'columns')) ?> footer</span>
    </div>
  </section>
  </div>

<?php elseif ($tab === 'permalinks'): ?>
  <h2 class="card-title">Permalink Settings</h2>
  <p class="text-muted">Public post and page URLs use these bases. Legacy /page/ and /post/ routes remain available for compatibility.</p>
  <form id="settings-permalinks-form" class="settings-form">
    <div class="form-row">
      <div class="form-group">
        <label>Page base</label>
        <input type="text" name="permalink_page_base" value="<?= kronos_e(kronos_option('permalink_page_base', 'page')) ?>" placeholder="page">
      </div>
      <div class="form-group">
        <label>Post base</label>
        <input type="text" name="permalink_post_base" value="<?= kronos_e(kronos_option('permalink_post_base', 'post')) ?>" placeholder="post">
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Save Permalinks</button>
  </form>

<?php elseif ($tab === 'ai'): ?>
  <h2 class="card-title">AI Settings</h2>
  <form id="settings-ai-form" class="settings-form">
    <div class="form-group">
      <label>OpenAI Model</label>
      <select name="openai_model">
        <?php foreach (['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'] as $m): ?>
        <option value="<?= $m ?>" <?= $aiModel === $m ? 'selected' : '' ?>><?= $m ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Save AI Settings</button>
  </form>

<?php elseif ($tab === 'payments'): ?>
  <h2 class="card-title">Payment Gateways</h2>
  <p class="text-muted">API keys are stored in your <code>.env</code> file. Update that file to change keys.</p>
  <div class="form-group">
    <label>Stripe Public Key</label>
    <input type="text" value="<?= kronos_e((string)($app->env('STRIPE_PUBLIC_KEY', ''))) ?>" readonly>
  </div>
  <div class="form-group">
    <label>PayPal Mode</label>
    <input type="text" value="<?= kronos_e((string)($app->env('PAYPAL_MODE', 'sandbox'))) ?>" readonly>
  </div>

<?php elseif ($tab === 'design'): ?>
  <?php
    $colorScheme = kronos_option('color_scheme', 'default');
    $heroStyle   = kronos_option('hero_style', 'full');
  ?>
  <h2 class="card-title">Design Settings</h2>
  <form id="settings-design-form" class="settings-form">

    <div class="form-group">
      <label>Color Scheme</label>
      <div class="scheme-grid">
        <?php foreach ([
          'default' => ['label' => 'Default', 'accent' => '#4f46e5', 'bg' => '#fff'],
          'dark'    => ['label' => 'Dark',    'accent' => '#818cf8', 'bg' => '#0f172a'],
          'ocean'   => ['label' => 'Ocean',   'accent' => '#0891b2', 'bg' => '#fff'],
          'rose'    => ['label' => 'Rose',    'accent' => '#e11d48', 'bg' => '#fff'],
          'forest'  => ['label' => 'Forest',  'accent' => '#16a34a', 'bg' => '#fff'],
        ] as $slug => $meta): ?>
        <label class="scheme-card <?= $colorScheme === $slug ? 'active' : '' ?>">
          <input type="radio" name="color_scheme" value="<?= $slug ?>" <?= $colorScheme === $slug ? 'checked' : '' ?> hidden>
          <span class="scheme-swatch" style="background:<?= $meta['bg'] ?>;border:3px solid <?= $meta['accent'] ?>"></span>
          <span class="scheme-name"><?= $meta['label'] ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-group">
      <label>Hero Style</label>
      <div class="hero-style-grid">
        <?php foreach (['full' => 'Full (120px)', 'compact' => 'Compact (72px)', 'minimal' => 'Minimal (48px)'] as $v => $label): ?>
        <label class="hero-style-card <?= $heroStyle === $v ? 'active' : '' ?>">
          <input type="radio" name="hero_style" value="<?= $v ?>" <?= $heroStyle === $v ? 'checked' : '' ?> hidden>
          <span class="hero-style-label"><?= $label ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Save Design Settings</button>
  </form>

  <style>
    .scheme-grid { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; }
    .scheme-card { display: flex; flex-direction: column; align-items: center; gap: 6px; cursor: pointer;
      padding: 10px 14px; border-radius: 10px; border: 2px solid transparent;
      transition: border-color .15s; background: var(--bg-alt); }
    .scheme-card.active, .scheme-card:hover { border-color: var(--accent); }
    .scheme-swatch { width: 48px; height: 48px; border-radius: 50%; display: block; }
    .scheme-name { font-size: .8rem; font-weight: 600; }
    .hero-style-grid { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; }
    .hero-style-card { padding: 12px 22px; border-radius: 8px; border: 2px solid var(--border);
      cursor: pointer; transition: border-color .15s; background: var(--bg-alt); }
    .hero-style-card.active, .hero-style-card:hover { border-color: var(--accent); }
    .hero-style-label { font-size: .9rem; font-weight: 600; }
  </style>

<?php elseif ($tab === 'tools'): ?>
  <?php
    $exportData = $siteBundleService->export();
  ?>
  <h2 class="card-title">Import / Export</h2>
  <div class="tool-grid">
    <div>
      <h3>Export Site Bundle</h3>
      <p class="text-muted">Includes settings, posts, pages, comments, builder layout links, menus, media metadata, categories, and tags.</p>
      <p class="text-muted">Bundle schema: <code>kronos-site-bundle-v1</code></p>
      <textarea rows="18" readonly><?= kronos_e(json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>
    </div>
    <div>
      <h3>Import Site Bundle</h3>
      <form method="post" action="<?= kronos_url('/dashboard/settings?tab=tools') ?>">
        <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
        <input type="hidden" name="action" value="import_site_bundle">
        <textarea name="site_bundle_json" rows="12" placeholder='{"schema":"kronos-site-bundle-v1",...}'></textarea>
        <button type="submit" class="btn btn-primary">Import Posts, Pages, Comments, Menus, Media, Taxonomies</button>
      </form>
      <hr class="settings-divider">
      <h3>Import Settings Only</h3>
      <form method="post" action="<?= kronos_url('/dashboard/settings?tab=tools') ?>">
        <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
        <input type="hidden" name="action" value="import_settings">
        <textarea name="settings_json" rows="10" placeholder='{"tagline":"My site"}'></textarea>
        <button type="submit" class="btn btn-secondary">Import Allowed Settings</button>
      </form>
    </div>
  </div>

<?php elseif ($tab === 'update'): ?>
  <h2 class="card-title">Update KronosCMS</h2>
  <p class="text-muted">Current version: <strong>v<?= kronos_e(\Kronos\Core\KronosVersion::VERSION) ?></strong></p>
  <div id="update-status">
    <button class="btn btn-secondary" id="check-update-btn">🔍 Check for Updates</button>
  </div>
  <?php
    $installedInventory = $pluginInventoryService->all();
  ?>
  <h3 class="mt-24">Installed Plugin & Theme Inventory</h3>
  <p class="text-muted">Rollback Note explains what folder to preserve before replacing a package.</p>
  <table class="data-table">
    <thead><tr><th>Name</th><th>Type</th><th>Status</th><th>Available Version</th><th>Rollback Note</th></tr></thead>
    <tbody>
      <?php foreach ($installedInventory as $item): ?>
      <tr>
        <td><strong><?= kronos_e($item['name']) ?></strong><br><code><?= kronos_e($item['slug']) ?></code></td>
        <td><span class="badge"><?= kronos_e($item['type']) ?></span></td>
        <td><span class="badge <?= $item['status'] === 'Active' ? 'badge-success' : 'badge-draft' ?>"><?= kronos_e($item['status']) ?></span></td>
        <td><?= kronos_e($item['version']) ?></td>
        <td><?= kronos_e($item['rollback']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

</div>

<script>
(function() {
  // General settings save
  const gForm = document.getElementById('settings-general-form');
  if (gForm) {
    gForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(this));
      // Direct POST to set options via a lightweight settings endpoint (handled by dashboard JS)
      await window.KronosDash.saveOptions(data);
      alert('Settings saved.');
    });
  }

  // Homepage settings save
  const hpForm = document.getElementById('settings-homepage-form');
  if (hpForm) {
    hpForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(this));
      await window.KronosDash.saveOptions(data);
      alert('Homepage settings saved.');
    });
  }

  // AI settings save
  const aiForm = document.getElementById('settings-ai-form');
  if (aiForm) {
    aiForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(this));
      await window.KronosDash.saveOptions(data);
      alert('AI settings saved.');
    });
  }

  const customizerForm = document.getElementById('settings-customizer-form');
  if (customizerForm) {
    const preview = document.getElementById('customizer-preview');
    const previewLogo = preview ? preview.querySelector('.customizer-preview-logo') : null;
    const previewFooter = preview ? preview.querySelector('.customizer-preview-footer span') : null;
    const applyCustomizerPreview = () => {
      if (!preview) return;
      const data = Object.fromEntries(new FormData(customizerForm));
      preview.style.setProperty('--preview-primary', data.brand_primary_color || '#4f46e5');
      preview.style.setProperty('--preview-accent', data.brand_accent_color || '#4338ca');
      preview.style.setProperty('--preview-bg', data.site_background_color || '#ffffff');
      preview.style.fontFamily = (data.body_font || 'Inter') + ', system-ui, sans-serif';
      preview.className = 'customizer-preview header-' + (data.header_layout || 'default') + ' footer-' + (data.footer_layout || 'columns');
      if (previewLogo) {
        previewLogo.textContent = data.site_logo_alt || preview.dataset.appName || 'KronosCMS';
      }
      if (previewFooter) {
        previewFooter.textContent = (data.footer_layout || 'columns') + ' footer';
      }
    };
    customizerForm.querySelectorAll('input, select').forEach(input => {
      input.addEventListener('input', applyCustomizerPreview);
      input.addEventListener('change', applyCustomizerPreview);
    });
    applyCustomizerPreview();
    customizerForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(this));
      await window.KronosDash.saveOptions(data);
      alert('Customizer settings saved.');
    });
  }

  const permalinkForm = document.getElementById('settings-permalinks-form');
  if (permalinkForm) {
    permalinkForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(this));
      data.permalink_page_base = (data.permalink_page_base || 'page').toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/^-+|-+$/g, '') || 'page';
      data.permalink_post_base = (data.permalink_post_base || 'post').toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/^-+|-+$/g, '') || 'post';
      await window.KronosDash.saveOptions(data);
      alert('Permalink settings saved.');
    });
  }

  // Mode switch
  document.querySelectorAll('[data-switch-mode]').forEach(btn => {
    btn.addEventListener('click', async function() {
      const mode = this.dataset.switchMode;
      if (!confirm(`Switch to ${mode} mode? The page will reload.`)) return;
      const res = await fetch((window.KronosConfig.appUrl || '') + '/dashboard/mode-switch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Kronos-CSRF': window.KronosConfig.csrf },
        body: 'mode=' + mode,
        credentials: 'include',
      });
      if (res.ok) location.reload();
    });
  });

  // Design settings save
  const dForm = document.getElementById('settings-design-form');
  if (dForm) {
    // Highlight scheme/hero cards on radio change
    dForm.querySelectorAll('.scheme-card input, .hero-style-card input').forEach(radio => {
      radio.addEventListener('change', function() {
        const grid = this.closest('.scheme-grid, .hero-style-grid');
        grid.querySelectorAll('.scheme-card, .hero-style-card').forEach(c => c.classList.remove('active'));
        this.closest('.scheme-card, .hero-style-card').classList.add('active');
      });
    });
    dForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(this));
      await window.KronosDash.saveOptions(data);
      alert('Design settings saved. Reload the site to see changes.');
    });
  }

  // Update check
  const checkBtn = document.getElementById('check-update-btn');
  if (checkBtn) {
    checkBtn.addEventListener('click', async function() {
      this.disabled = true;
      this.textContent = 'Checking…';
      const statusDiv = document.getElementById('update-status');
      try {
        const res = await window.KronosDash.api('/system/update/check', 'GET');
        if (!res) { statusDiv.innerHTML = '<p class="text-danger">Could not reach update server.</p>'; this.disabled = false; this.textContent = 'Check for Updates'; return; }
        if (res.update_available) {
          statusDiv.innerHTML = `
            <div class="update-banner">
              <p>✅ Update available: <strong>v${res.latest_version}</strong></p>
              <button class="btn btn-primary" id="apply-update-btn">⬆️ Update Now</button>
            </div>`;
          document.getElementById('apply-update-btn').addEventListener('click', async function() {
            if (!confirm('Apply update? KronosCMS will replace its files. Backup first!')) return;
            this.disabled = true;
            this.textContent = 'Updating…';
            try {
              const upd = await window.KronosDash.api('/system/update/apply', 'POST', {});
              statusDiv.innerHTML = `<p class="text-success">✅ Updated to v${upd.version}. Reload to continue.</p>`;
            } catch(err) {
              statusDiv.innerHTML = `<p class="text-danger">❌ Update failed: ${err.message}</p>`;
            }
          });
        } else {
          statusDiv.innerHTML = '<p class="text-success">✅ You are on the latest version.</p>';
        }
        this.disabled = false;
        this.textContent = 'Check for Updates';
      } catch(err) {
        statusDiv.innerHTML = `<p class="text-danger">Could not check for updates: ${err.message}</p>`;
        this.disabled = false;
        this.textContent = 'Check for Updates';
      }
    });
  }
})();
</script>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
