<?php
// Step 1 — Database Configuration
/** @var array<string> $errors */
$errors ??= [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KronosCMS — Install (Step 1/3)</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #1a1a2e; }
  .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 2.5rem; width: 100%; max-width: 520px; }
  .logo { font-size: 1.6rem; font-weight: 700; color: #2563eb; margin-bottom: .25rem; }
  .subtitle { color: #6b7280; font-size: .9rem; margin-bottom: 1.75rem; }
  .steps { display: flex; gap: .5rem; margin-bottom: 2rem; }
  .step { flex: 1; height: 4px; border-radius: 99px; background: #e5e7eb; }
  .step.active { background: #2563eb; }
  .step.done { background: #10b981; }
  h2 { font-size: 1.2rem; font-weight: 600; margin-bottom: 1.5rem; color: #111827; }
  h3 { font-size: .95rem; font-weight: 600; color: #374151; margin: 1.5rem 0 .5rem; }
  label { display: block; font-size: .85rem; font-weight: 500; color: #374151; margin-bottom: .35rem; margin-top: 1rem; }
  input { width: 100%; padding: .6rem .85rem; border: 1.5px solid #d1d5db; border-radius: 7px; font-size: .95rem; color: #111827; transition: border-color .15s; }
  input:focus { outline: none; border-color: #2563eb; }
  .row { display: grid; grid-template-columns: 1fr 100px; gap: .75rem; }
  .btn { display: block; width: 100%; margin-top: 1.75rem; padding: .75rem; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background .15s; }
  .btn:hover { background: #1d4ed8; }
  .errors { background: #fee2e2; border: 1px solid #fca5a5; border-radius: 7px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .875rem; color: #991b1b; }
  .errors li { margin-left: 1rem; }
  .divider { border: none; border-top: 1px solid #e5e7eb; margin: 1.75rem 0 0; }
  .section-toggle { display: flex; align-items: center; gap: .5rem; cursor: pointer; font-size: .875rem; font-weight: 600; color: #6b7280; margin-top: 1.25rem; user-select: none; }
  .section-toggle:hover { color: #374151; }
  .section-toggle svg { transition: transform .2s; }
  .section-toggle.open svg { transform: rotate(90deg); }
  .advanced-section { display: none; }
  .hint { font-size: .8rem; color: #6b7280; margin-top: .3rem; line-height: 1.4; }
  .badge { display: inline-block; font-size: .7rem; font-weight: 700; padding: .15rem .5rem; border-radius: 99px; vertical-align: middle; margin-left: .4rem; }
  .badge-prod { background: #dcfce7; color: #166534; }
  .badge-local { background: #dbeafe; color: #1e40af; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">⚡ KronosCMS</div>
  <div class="subtitle">Installation Wizard</div>
  <div class="steps">
    <div class="step active"></div>
    <div class="step"></div>
    <div class="step"></div>
  </div>
  <h2>Step 1 — Database Connection</h2>

  <?php if (!empty($errors)): ?>
  <div class="errors"><ul>
    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
  </ul></div>
  <?php endif; ?>

  <form method="POST" action="<?= defined('KRONOS_BASE') ? KRONOS_BASE : '' ?>/install/?step=1">

    <!-- ── App Database Credentials ─────────────────────────────── -->
    <p class="hint" style="margin-bottom:.5rem">
      Enter the credentials the application will use to connect to the database.
      <span class="badge badge-prod">Production</span> These must already exist on your server.
    </p>

    <label>Database Host</label>
    <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? '127.0.0.1', ENT_QUOTES) ?>" placeholder="127.0.0.1" required>

    <div class="row" style="margin-top:1rem">
      <div>
        <label style="margin-top:0">Database Name</label>
        <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'kronos_cms', ENT_QUOTES) ?>" placeholder="kronos_cms" required>
      </div>
      <div>
        <label style="margin-top:0">Port</label>
        <input type="number" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306', ENT_QUOTES) ?>" placeholder="3306" required>
      </div>
    </div>

    <label>Database User</label>
    <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '', ENT_QUOTES) ?>" placeholder="kronos_user" required>

    <label>Database Password</label>
    <input type="password" name="db_pass" placeholder="Leave empty if none">

    <!-- ── Advanced: Auto-create (local/dev only) ────────────────── -->
    <hr class="divider">
    <div class="section-toggle" id="adv-toggle" onclick="toggleAdvanced()">
      <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M4 2l4 4-4 4" stroke="#6b7280" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Auto-create database &amp; user
      <span class="badge badge-local">Local / Dev</span>
    </div>
    <div class="advanced-section" id="adv-section">
      <p class="hint" style="margin-top:.75rem">
        If the database or user above don't exist yet, provide a MySQL admin account (e.g. <code>root</code>) and the installer will create them automatically.<br>
        <strong>Leave blank on production</strong> — the DB and user must already exist there.
      </p>

      <label>MySQL Admin User</label>
      <input type="text" name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? 'root', ENT_QUOTES) ?>" placeholder="root">

      <label>MySQL Admin Password</label>
      <input type="password" name="admin_pass" placeholder="Leave empty if none (XAMPP default)">
    </div>

    <button type="submit" class="btn">Connect & Continue →</button>
  </form>
</div>
<script>
function toggleAdvanced() {
    var toggle  = document.getElementById('adv-toggle');
    var section = document.getElementById('adv-section');
    var open    = section.style.display === 'block';
    section.style.display = open ? 'none' : 'block';
    toggle.classList.toggle('open', !open);
}
// If there were POST errors and admin fields were filled, keep section open
<?php if (!empty($_POST['admin_user'])): ?>
toggleAdvanced();
<?php endif; ?>
</script>
</body>
</html>
