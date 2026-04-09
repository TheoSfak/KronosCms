<?php
declare(strict_types=1);

// dashboard/pages/login.php — standalone login page, no layout wrapper
if (!empty($_COOKIE['kronos_token'])) {
    kronos_redirect('/dashboard');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        // Call internal login logic
        try {
            $db   = $app->db();
            $user = $db->getRow(
                'SELECT id, username, email, password_hash, role, display_name FROM kronos_users WHERE (username = ? OR email = ?) LIMIT 1',
                [$username, $username]
            );

            if ($user && password_verify($password, $user['password_hash'])) {
                $db->update('kronos_users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

                $userData = [
                    'id'           => $user['id'],
                    'username'     => $user['username'],
                    'email'        => $user['email'],
                    'role'         => $user['role'],
                    'display_name' => $user['display_name'],
                ];

                $secret = (string) $app->env('JWT_SECRET', '');
                $expiry = (int)   $app->env('JWT_EXPIRY', 86400);
                $jwt    = new \Kronos\Auth\KronosJWT($secret, $expiry);
                $mw     = new \Kronos\Auth\KronosMiddleware($jwt);
                $mw->issueToken($userData);

                kronos_redirect('/dashboard');
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (\Exception $e) {
            $error = 'Login error. Please try again.';
        }
    } else {
        $error = 'Username and password are required.';
    }
}

$appName = kronos_option('app_name', 'KronosCMS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= kronos_e($appName) ?></title>
<link rel="stylesheet" href="<?= kronos_asset('css/dashboard.css') ?>">
</head>
<body class="login-body">
<div class="login-card">
  <div class="login-logo">⚡ <?= kronos_e($appName) ?></div>
  <p class="login-subtitle">Sign in to your dashboard</p>

  <?php if ($error !== ''): ?>
  <div class="alert alert-error"><?= kronos_e($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="<?= kronos_url('/dashboard/login') ?>" class="login-form">
    <div class="form-group">
      <label>Username or Email</label>
      <input type="text" name="username" value="<?= kronos_e($_POST['username'] ?? '') ?>" required autocomplete="username" autofocus>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Sign In →</button>
  </form>
</div>
</body>
</html>
