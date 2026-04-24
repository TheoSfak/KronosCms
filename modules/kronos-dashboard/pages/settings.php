<?php
declare(strict_types=1);
$pageTitle = 'Settings';
$dashDir   = dirname(__DIR__);
require $dashDir . '/partials/layout-header.php';

$cfg        = $app->config();
$activeMode = kronos_mode();
$appName    = $cfg->get('app_name', 'KronosCMS');
$appUrl     = $cfg->get('app_url', '');
$aiModel    = $cfg->get('openai_model', 'gpt-4o');
$tab        = $_GET['tab'] ?? 'general';
$allowedTabs = ['general', 'homepage', 'design', 'mode', 'ai', 'payments', 'update'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'general';
}
?>

<div class="settings-tabs">
  <?php foreach ($allowedTabs as $t): ?>
  <a href="<?= kronos_url('/dashboard/settings') ?>?tab=<?= $t ?>" class="settings-tab <?= $tab === $t ? 'active' : '' ?>">
    <?= match($t) {
        'general'  => '⚙️ General',
        'homepage' => '🏠 Homepage',
        'mode'     => '🔄 Mode',
        'ai'       => '🤖 AI',
        'payments' => '💳 Payments',
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

<?php elseif ($tab === 'update'): ?>
  <h2 class="card-title">Update KronosCMS</h2>
  <p class="text-muted">Current version: <strong>v<?= kronos_e(\Kronos\Core\KronosVersion::VERSION) ?></strong></p>
  <div id="update-status">
    <button class="btn btn-secondary" id="check-update-btn">🔍 Check for Updates</button>
  </div>
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
