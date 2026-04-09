<?php
// Installation complete screen
/** @var string $appUrl */
$appUrl ??= '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KronosCMS — Installed!</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #1a1a2e; }
  .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 2.5rem; width: 100%; max-width: 480px; text-align: center; }
  .icon { font-size: 4rem; margin-bottom: 1rem; }
  h1 { font-size: 1.6rem; font-weight: 700; color: #111827; margin-bottom: .5rem; }
  p { color: #6b7280; margin-bottom: 1.5rem; }
  .steps { display: flex; gap: .5rem; margin: 1.5rem 0; }
  .step { flex: 1; height: 4px; border-radius: 99px; background: #10b981; }
  .btn { display: inline-block; padding: .75rem 2rem; background: #2563eb; color: #fff; border-radius: 8px; font-size: 1rem; font-weight: 600; text-decoration: none; margin: .5rem; }
  .btn:hover { background: #1d4ed8; }
  .btn.secondary { background: #e5e7eb; color: #374151; }
  .btn.secondary:hover { background: #d1d5db; }
  .security-note { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: .85rem 1rem; font-size: .85rem; color: #92400e; margin-top: 1.5rem; text-align: left; }
</style>
</head>
<body>
<div class="card">
  <div class="icon">🎉</div>
  <div class="steps">
    <div class="step"></div>
    <div class="step"></div>
    <div class="step"></div>
  </div>
  <h1>KronosCMS Installed!</h1>
  <p>Your CMS is ready. Head to your dashboard to start building.</p>

  <a href="<?= htmlspecialchars($appUrl, ENT_QUOTES) ?>/dashboard/" class="btn">Go to Dashboard →</a>
  <a href="<?= htmlspecialchars($appUrl, ENT_QUOTES) ?>/" class="btn secondary">View Site</a>

  <div class="security-note">
    ⚠️ <strong>Security:</strong> Please delete or rename the <code>/install/</code> directory
    from your server to prevent re-running the installer.
  </div>
</div>
</body>
</html>
