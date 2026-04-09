<?php
declare(strict_types=1);
$pageTitle = 'Content';
$dashDir   = dirname(__DIR__);
require $dashDir . '/partials/layout-header.php';

$db    = $app->db();
$posts = $db->getResults(
    "SELECT p.id, p.title, p.slug, p.post_type, p.status, p.published_at, u.display_name AS author
     FROM kronos_posts p
     LEFT JOIN kronos_users u ON u.id = p.author_id
     ORDER BY p.updated_at DESC LIMIT 50"
);
?>

<div class="toolbar">
  <a href="<?= kronos_url('/dashboard/content/new') ?>" class="btn btn-primary">+ New Post</a>
</div>

<div class="card">
  <table class="data-table">
    <thead>
      <tr>
        <th>Title</th>
        <th>Type</th>
        <th>Status</th>
        <th>Author</th>
        <th>Published</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($posts)): ?>
      <tr><td colspan="6" class="text-center text-muted">No posts yet. <a href="<?= kronos_url('/dashboard/content/new') ?>">Create your first post →</a></td></tr>
      <?php else: ?>
      <?php foreach ($posts as $post): ?>
      <tr>
        <td><strong><?= kronos_e($post['title']) ?></strong><br><small class="text-muted">/<?= kronos_e($post['slug']) ?></small></td>
        <td><span class="badge"><?= kronos_e($post['post_type']) ?></span></td>
        <td><span class="badge badge-<?= kronos_e($post['status']) ?>"><?= kronos_e($post['status']) ?></span></td>
        <td><?= kronos_e($post['author'] ?? '—') ?></td>
        <td><?= $post['published_at'] ? kronos_e(date('Y-m-d', strtotime($post['published_at']))) : '—' ?></td>
        <td>
          <a href="<?= kronos_url('/dashboard/content/' . (int)$post['id']) ?>" class="action-btn">Edit</a>
          <button class="action-btn danger" data-delete-post="<?= (int)$post['id'] ?>">Delete</button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
