<?php
declare(strict_types=1);
$pageTitle = 'Users';
$dashDir   = dirname(__DIR__);
require $dashDir . '/partials/layout-header.php';

if (!kronos_user_can('app_manager')) {
    kronos_redirect('/dashboard');
}

$db    = $app->db();
$users = $db->getResults(
    'SELECT id, username, email, role, display_name, last_login_at, created_at FROM kronos_users ORDER BY created_at DESC'
);
?>

<div class="toolbar">
  <button class="btn btn-primary" id="open-add-user">+ Add User</button>
</div>

<div class="card">
  <table class="data-table">
    <thead>
      <tr><th>Username</th><th>Email</th><th>Role</th><th>Last Login</th><th>Joined</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><strong><?= kronos_e($u['display_name'] ?: $u['username']) ?></strong><br><small class="text-muted"><?= kronos_e($u['username']) ?></small></td>
        <td><?= kronos_e($u['email']) ?></td>
        <td><span class="badge"><?= kronos_e(str_replace('app_', '', $u['role'])) ?></span></td>
        <td><?= $u['last_login_at'] ? kronos_e(date('Y-m-d H:i', strtotime($u['last_login_at']))) : '—' ?></td>
        <td><?= kronos_e(date('Y-m-d', strtotime($u['created_at']))) ?></td>
        <td>
          <button class="action-btn danger" data-delete-user="<?= (int)$u['id'] ?>">Delete</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
