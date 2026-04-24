<?php
declare(strict_types=1);
$pageTitle = 'Marketplace';
$dashDir   = dirname(__DIR__);
require $dashDir . '/partials/layout-header.php';
?>

<div id="marketplace-wrapper">
  <div class="toolbar">
    <button class="btn btn-secondary" id="refresh-marketplace">🔄 Refresh Directory</button>
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

  async function loadDirectory() {
    const grid = document.getElementById('marketplace-grid');
    grid.innerHTML = '<p class="text-muted">Loading…</p>';
    try {
      const res = await window.KronosDash.api('/marketplace/directory', 'GET');
      if (!res) throw new Error('No response from server — check PHP error logs.');
      const packages = res.data || [];
      if (!packages.length) {
        grid.innerHTML = '<p class="text-muted">No packages available.</p>';
        return;
      }
      grid.innerHTML = packages.map(pkg => `
        <div class="marketplace-card">
          <div class="mp-icon">${esc(pkg.icon || '📦')}</div>
          <div class="mp-name"><strong>${esc(pkg.name)}</strong> <span class="badge">${esc(pkg.version || '1.0.0')}</span></div>
          <div class="mp-desc">${esc(pkg.description || '')}</div>
          <div class="mp-meta">
            <span class="badge ${pkg.requires_license ? 'badge-premium' : 'badge-free'}">${pkg.requires_license ? '👑 Premium' : '✅ Free'}</span>
            <span class="badge">${esc(pkg.type || 'module')}</span>
          </div>
          ${pkg.download_url ? `<button class="btn btn-primary mp-install-btn"
            data-slug="${esc(pkg.slug)}"
            data-url="${esc(pkg.download_url)}"
            data-type="${esc(pkg.type || 'module')}"
            data-requires-license="${pkg.requires_license ? '1' : '0'}"
            data-license-tier="${esc(pkg.license_tier || 'free')}">
            Install
          </button>` : '<button class="btn btn-secondary" disabled>Unavailable</button>'}
        </div>
      `).join('');

      // Bind install buttons
      document.querySelectorAll('.mp-install-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
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

  loadDirectory();
  document.getElementById('refresh-marketplace').addEventListener('click', loadDirectory);
})();
</script>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
