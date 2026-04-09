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
      <tr id="user-row-<?= (int)$u['id'] ?>">
        <td><strong><?= kronos_e($u['display_name'] ?: $u['username']) ?></strong><br><small class="text-muted"><?= kronos_e($u['username']) ?></small></td>
        <td><?= kronos_e($u['email']) ?></td>
        <td><span class="badge"><?= kronos_e(str_replace('app_', '', $u['role'])) ?></span></td>
        <td><?= $u['last_login_at'] ? kronos_e(date('Y-m-d H:i', strtotime($u['last_login_at']))) : '—' ?></td>
        <td><?= kronos_e(date('Y-m-d', strtotime($u['created_at']))) ?></td>
        <td>
          <button class="action-btn danger"
                  data-delete-url="/api/kronos/v1/users/<?= (int)$u['id'] ?>"
                  data-delete-target="#user-row-<?= (int)$u['id'] ?>"
                  data-confirm="Delete user &ldquo;<?= kronos_e($u['username']) ?>&rdquo;? This cannot be undone.">
            Delete
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ── Add User Modal ── -->
<div class="modal-overlay" id="add-user-modal" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <h3>Add User</h3>
      <button class="modal-close" id="close-add-user" aria-label="Close">&times;</button>
    </div>
    <form id="add-user-form" novalidate>
      <div class="form-group">
        <label for="new-username">Username <span class="required">*</span></label>
        <input type="text" id="new-username" name="username" required minlength="3" maxlength="40" autocomplete="off">
      </div>
      <div class="form-group">
        <label for="new-display">Display Name</label>
        <input type="text" id="new-display" name="display_name" maxlength="80" autocomplete="off">
      </div>
      <div class="form-group">
        <label for="new-email">Email</label>
        <input type="email" id="new-email" name="email" autocomplete="off">
      </div>
      <div class="form-group">
        <label for="new-password">Password <span class="required">*</span></label>
        <input type="password" id="new-password" name="password" required minlength="8" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label for="new-role">Role</label>
        <select id="new-role" name="role">
          <option value="app_editor">Editor</option>
          <option value="app_viewer">Viewer</option>
          <option value="app_manager">Manager</option>
        </select>
      </div>
      <div class="form-error" id="add-user-error" style="display:none"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" id="cancel-add-user">Cancel</button>
        <button type="submit" class="btn btn-primary" id="add-user-submit">Create User</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const overlay = document.getElementById('add-user-modal');
  const form    = document.getElementById('add-user-form');
  const errBox  = document.getElementById('add-user-error');
  const submitBtn = document.getElementById('add-user-submit');

  function openModal() { overlay.style.display = 'flex'; form.reset(); errBox.style.display = 'none'; }
  function closeModal() { overlay.style.display = 'none'; }

  document.getElementById('open-add-user').addEventListener('click', openModal);
  document.getElementById('close-add-user').addEventListener('click', closeModal);
  document.getElementById('cancel-add-user').addEventListener('click', closeModal);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    errBox.style.display = 'none';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating…';

    const body = {
      username:     form.username.value.trim(),
      display_name: form.display_name.value.trim(),
      email:        form.email.value.trim(),
      password:     form.password.value,
      role:         form.role.value,
    };

    try {
      const res = await window.KronosDash.api('/users', 'POST', body);
      if (res.success) {
        closeModal();
        location.reload();
      } else {
        throw new Error(res.message || 'Failed to create user.');
      }
    } catch (err) {
      errBox.textContent = err.message || 'Server error.';
      errBox.style.display = 'block';
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Create User';
    }
  });

  // Delete buttons — delegate via data-delete-url
  document.querySelectorAll('[data-delete-url]').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      const url    = btn.dataset.deleteUrl;
      const target = document.querySelector(btn.dataset.deleteTarget || '');
      const msg    = btn.dataset.confirm || 'Delete this user?';
      if (!confirm(msg)) return;
      btn.disabled = true;
      try {
        await window.KronosDash.api(url.replace('/api/kronos/v1', ''), 'DELETE');
        if (target) target.remove();
      } catch (err) {
        alert(err.message || 'Delete failed.');
        btn.disabled = false;
      }
    });
  });
}());
</script>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
