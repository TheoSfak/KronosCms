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
$allowedTabs = ['general', 'mode', 'ai', 'payments', 'update'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'general';
}
?>

<div class="settings-tabs">
  <?php foreach ($allowedTabs as $t): ?>
  <a href="<?= kronos_url('/dashboard/settings') ?>?tab=<?= $t ?>" class="settings-tab <?= $tab === $t ? 'active' : '' ?>">
    <?= match($t) {
        'general'  => '⚙️ General',
        'mode'     => '🔄 Mode',
        'ai'       => '🤖 AI',
        'payments' => '💳 Payments',
        'update'   => '🔁 Update',
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
    <button type="submit" class="btn btn-primary">Save General Settings</button>
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
      const res = await fetch('/dashboard/mode-switch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Kronos-CSRF': window.KronosConfig.csrf },
        body: 'mode=' + mode,
        credentials: 'include',
      });
      if (res.ok) location.reload();
    });
  });

  // Update check
  const checkBtn = document.getElementById('check-update-btn');
  if (checkBtn) {
    checkBtn.addEventListener('click', async function() {
      this.disabled = true;
      this.textContent = 'Checking…';
      const statusDiv = document.getElementById('update-status');
      try {
        const res = await window.KronosDash.api('/system/update/check', 'GET');
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
      } catch(err) {
        statusDiv.innerHTML = `<p class="text-danger">Could not check for updates: ${err.message}</p>`;
      }
    });
  }
})();
</script>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
