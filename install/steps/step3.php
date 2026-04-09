<?php
// Step 3 — Admin Account
/** @var array<string> $errors */
$errors ??= [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KronosCMS — Install (Step 3/3)</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #1a1a2e; }
  .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 2.5rem; width: 100%; max-width: 480px; }
  .logo { font-size: 1.6rem; font-weight: 700; color: #2563eb; margin-bottom: .25rem; }
  .subtitle { color: #6b7280; font-size: .9rem; margin-bottom: 1.75rem; }
  .steps { display: flex; gap: .5rem; margin-bottom: 2rem; }
  .step { flex: 1; height: 4px; border-radius: 99px; background: #e5e7eb; }
  .step.done { background: #10b981; }
  .step.active { background: #2563eb; }
  h2 { font-size: 1.2rem; font-weight: 600; margin-bottom: 1.5rem; color: #111827; }
  label { display: block; font-size: .85rem; font-weight: 500; color: #374151; margin-bottom: .35rem; margin-top: 1rem; }
  input { width: 100%; padding: .6rem .85rem; border: 1.5px solid #d1d5db; border-radius: 7px; font-size: .95rem; color: #111827; }
  input:focus { outline: none; border-color: #2563eb; }
  .btn { display: block; width: 100%; margin-top: 1.75rem; padding: .75rem; background: #10b981; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
  .btn:hover { background: #059669; }
  .errors { background: #fee2e2; border: 1px solid #fca5a5; border-radius: 7px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .875rem; color: #991b1b; }
  .errors li { margin-left: 1rem; }
  .note { font-size: .8rem; color: #6b7280; margin-top: .5rem; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">⚡ KronosCMS</div>
  <div class="subtitle">Installation Wizard</div>
  <div class="steps">
    <div class="step done"></div>
    <div class="step done"></div>
    <div class="step active"></div>
  </div>
  <h2>Step 3 — Create Admin Account</h2>

  <?php if (!empty($errors)): ?>
  <div class="errors"><ul>
    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
  </ul></div>
  <?php endif; ?>

  <form method="POST" action="<?= defined('KRONOS_BASE') ? KRONOS_BASE : '' ?>/install/?step=3">
    <label>Username</label>
    <input type="text" name="admin_username" value="<?= htmlspecialchars($_POST['admin_username'] ?? 'admin', ENT_QUOTES) ?>" minlength="3" required autocomplete="username">

    <label>Email Address</label>
    <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '', ENT_QUOTES) ?>" required autocomplete="email">

    <label>Password</label>
    <input type="password" name="admin_password" minlength="8" required autocomplete="new-password">
    <p class="note">Minimum 8 characters.</p>

    <label>Confirm Password</label>
    <input type="password" name="admin_password_confirm" minlength="8" required autocomplete="new-password">

    <button type="submit" class="btn">🚀 Install KronosCMS</button>
  </form>
</div>
</body>
</html>
