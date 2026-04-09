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
  .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 2.5rem; width: 100%; max-width: 480px; }
  .logo { font-size: 1.6rem; font-weight: 700; color: #2563eb; margin-bottom: .25rem; }
  .subtitle { color: #6b7280; font-size: .9rem; margin-bottom: 1.75rem; }
  .steps { display: flex; gap: .5rem; margin-bottom: 2rem; }
  .step { flex: 1; height: 4px; border-radius: 99px; background: #e5e7eb; }
  .step.active { background: #2563eb; }
  .step.done { background: #10b981; }
  h2 { font-size: 1.2rem; font-weight: 600; margin-bottom: 1.5rem; color: #111827; }
  label { display: block; font-size: .85rem; font-weight: 500; color: #374151; margin-bottom: .35rem; margin-top: 1rem; }
  input { width: 100%; padding: .6rem .85rem; border: 1.5px solid #d1d5db; border-radius: 7px; font-size: .95rem; color: #111827; transition: border-color .15s; }
  input:focus { outline: none; border-color: #2563eb; }
  .row { display: grid; grid-template-columns: 1fr 100px; gap: .75rem; }
  .btn { display: block; width: 100%; margin-top: 1.75rem; padding: .75rem; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background .15s; }
  .btn:hover { background: #1d4ed8; }
  .errors { background: #fee2e2; border: 1px solid #fca5a5; border-radius: 7px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .875rem; color: #991b1b; }
  .errors li { margin-left: 1rem; }
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
    <label>Database Host</label>
    <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? '127.0.0.1', ENT_QUOTES) ?>" placeholder="127.0.0.1" required>

    <div class="row">
      <div>
        <label>Database Name</label>
        <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'kronos_cms', ENT_QUOTES) ?>" placeholder="kronos_cms" required>
        <small style="color:#6b7280;display:block;margin-top:.25rem">The database will be created automatically if it doesn't exist.</small>
      </div>
      <div>
        <label>Port</label>
        <input type="number" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306', ENT_QUOTES) ?>" placeholder="3306" required>
      </div>
    </div>

    <label>Database User</label>
    <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root', ENT_QUOTES) ?>" placeholder="root" required>

    <label>Database Password</label>
    <input type="password" name="db_pass" value="" placeholder="Leave empty if none">

    <button type="submit" class="btn">Create Database & Continue →</button>
  </form>
</div>
</body>
</html>
