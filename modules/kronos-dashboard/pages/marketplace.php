<?php
declare(strict_types=1);
$pageTitle = 'Marketplace';
$dashDir   = dirname(__DIR__);
$loadedModules = $app->moduleLoader()->getLoaded();
$packageNames = [];
foreach ((new \Kronos\Marketplace\HubClient($app->config()))->fetchDirectory() as $package) {
    $slug = (string) ($package['slug'] ?? '');
    if ($slug !== '') {
        $packageNames[$slug] = (string) ($package['name'] ?? $slug);
    }
}
$installedModules = [];
$modulesDir = rtrim($app->rootDir(), '/\\') . '/modules';
foreach (glob($modulesDir . '/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
    $slug = basename($moduleDir);
    $entryFile = $moduleDir . '/' . $slug . '.php';
    $loaded = isset($loadedModules[$slug]);
    $installedModules[] = [
        'slug' => $slug,
        'name' => $packageNames[$slug] ?? ($loaded ? $loadedModules[$slug]->getName() : $slug),
        'entry_ok' => is_file($entryFile),
        'loaded' => $loaded,
    ];
}
usort($installedModules, fn(array $a, array $b): int => (int) $b['loaded'] <=> (int) $a['loaded'] ?: $a['slug'] <=> $b['slug']);
require $dashDir . '/partials/layout-header.php';
?>

<div id="marketplace-wrapper">
  <div class="card mb-4">
    <div class="card-header">
      <div>
        <h3>Installed Plugins</h3>
        <p class="text-muted">Local modules discovered from the <code>modules</code> folder.</p>
      </div>
    </div>
    <table class="data-table">
      <thead>
        <tr>
          <th>Plugin</th>
          <th>Folder</th>
          <th>Entry File</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($installedModules as $module): ?>
        <tr>
          <td><strong><?= kronos_e($module['name']) ?></strong></td>
          <td><code><?= kronos_e($module['slug']) ?></code></td>
          <td><span class="badge <?= $module['entry_ok'] ? 'badge-success' : 'badge-danger' ?>"><?= $module['entry_ok'] ? 'Found' : 'Missing' ?></span></td>
          <td><span class="badge <?= $module['loaded'] ? 'badge-success' : 'badge-draft' ?>"><?= $module['loaded'] ? 'Loaded' : 'Not loaded' ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="toolbar">
    <button class="btn btn-secondary" id="refresh-marketplace">🔄 Refresh Directory</button>
    <span class="text-muted">Compatibility checked against KronosCMS v<?= kronos_e(\Kronos\Core\KronosVersion::VERSION) ?>.</span>
  </div>
  <div id="marketplace-grid" class="marketplace-grid">
    <p class="text-muted">Loading packages…</p>
  </div>
</div>

<script>
(function() {
  function esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  async function loadDirectory(refresh = false) {
    const grid = document.getElementById('marketplace-grid');
    const coreVersion = <?= json_encode(\Kronos\Core\KronosVersion::VERSION) ?>;
    function versionCompare(a, b) {
      const pa = String(a || '0').split('.').map(Number);
      const pb = String(b || '0').split('.').map(Number);
      for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
        const da = pa[i] || 0;
        const db = pb[i] || 0;
        if (da > db) return 1;
        if (da < db) return -1;
      }
      return 0;
    }
    function compatibility(pkg) {
      const min = pkg.min_core_version || pkg.requires_core || '';
      const max = pkg.max_core_version || '';
      const tooOld = min && versionCompare(coreVersion, min) < 0;
      const tooNew = max && versionCompare(coreVersion, max) > 0;
      if (tooOld) return {ok: false, label: 'Requires v' + min + '+'};
      if (tooNew) return {ok: false, label: 'Supports up to v' + max};
      return {ok: true, label: min || max ? 'Compatible' : 'No version limits'};
    }
    grid.innerHTML = '<p class="text-muted">Loading…</p>';
    try {
      const res = await window.KronosDash.api('/marketplace/directory' + (refresh ? '?refresh=1' : ''), 'GET');
      if (!res) throw new Error('No response from server — check PHP error logs.');
      const packages = res.data || [];
      if (!packages.length) {
        grid.innerHTML = '<p class="text-muted">No packages available.</p>';
        return;
      }
      grid.innerHTML = packages.map(pkg => {
        const compat = compatibility(pkg);
        const status = pkg.install_status || (pkg.installed ? 'installed' : 'available');
        const installedLabel = status === 'active' ? 'Active' : (status === 'installed' ? 'Installed' : 'Not installed');
        const canInstall = !pkg.installed && pkg.download_url;
        const entryWarning = pkg.installed && pkg.entry_file_ok === false ? '<span class="badge badge-danger">Missing entry</span>' : '';
        return `
        <div class="marketplace-card">
          <div class="mp-icon">${esc(pkg.icon || '📦')}</div>
          <div class="mp-name"><strong>${esc(pkg.name)}</strong> <span class="badge">${esc(pkg.version || '1.0.0')}</span></div>
          <div class="mp-desc">${esc(pkg.description || '')}</div>
          <div class="mp-meta">
            <span class="badge ${pkg.requires_license ? 'badge-premium' : 'badge-free'}">${pkg.requires_license ? '👑 Premium' : '✅ Free'}</span>
            <span class="badge">${esc(pkg.type || 'module')}</span>
            <span class="badge ${compat.ok ? 'badge-success' : 'badge-draft'}">${esc(compat.label)}</span>
            <span class="badge ${status === 'active' ? 'badge-success' : (pkg.installed ? 'badge-draft' : '')}">${esc(installedLabel)}</span>
            ${entryWarning}
          </div>
          ${canInstall ? `<button class="btn btn-primary mp-install-btn"
            data-slug="${esc(pkg.slug)}"
            data-url="${esc(pkg.download_url)}"
            data-type="${esc(pkg.type || 'module')}"
            data-requires-license="${pkg.requires_license ? '1' : '0'}"
            data-license-tier="${esc(pkg.license_tier || 'free')}"
            data-compatible="${compat.ok ? '1' : '0'}">
            Install
          </button>` : `<button class="btn btn-secondary" disabled>${esc(pkg.installed ? installedLabel : 'Unavailable')}</button>`}
        </div>
      `;
      }).join('');

      // Bind install buttons
      document.querySelectorAll('.mp-install-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
          if (this.dataset.compatible !== '1' && !confirm('This package may not be compatible with this KronosCMS version. Install anyway?')) {
            return;
          }
          this.disabled = true;
          this.textContent = 'Installing…';
          try {
            const res = await window.KronosDash.api('/marketplace/install', 'POST', {
              slug: this.dataset.slug,
              download_url: this.dataset.url,
              type: this.dataset.type,
              requires_license: this.dataset.requiresLicense === '1',
              license_tier: this.dataset.licenseTier,
            });
            if (res && res.success) {
              this.textContent = 'Installed';
              this.classList.replace('btn-primary', 'btn-success');
              loadDirectory(true);
            } else {
              throw new Error((res && res.message) || 'Install failed.');
            }
          } catch(err) {
            this.disabled = false;
            this.textContent = 'Install';
            alert('Install failed: ' + err.message);
          }
        });
      });
    } catch(err) {
      grid.innerHTML = '<p class="text-danger">Failed to load directory: ' + esc(err.message) + '</p>';
    }
  }

  loadDirectory(true);
  document.getElementById('refresh-marketplace').addEventListener('click', () => loadDirectory(true));
})();
</script>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
