<?php
declare(strict_types=1);
$pageTitle = 'Content';
$dashDir   = dirname(__DIR__);
require $dashDir . '/partials/layout-header.php';

$db     = $app->db();
$status = in_array($_GET['status'] ?? '', ['draft', 'published', 'archived'], true) ? (string) $_GET['status'] : '';
$type   = in_array($_GET['post_type'] ?? '', ['post', 'page'], true) ? (string) $_GET['post_type'] : '';
$search = trim((string) ($_GET['s'] ?? ''));

$counts = [
    'all'       => (int) $db->getVar('SELECT COUNT(*) FROM kronos_posts'),
    'published' => (int) $db->getVar("SELECT COUNT(*) FROM kronos_posts WHERE status = 'published'"),
    'draft'     => (int) $db->getVar("SELECT COUNT(*) FROM kronos_posts WHERE status = 'draft'"),
    'archived'  => (int) $db->getVar("SELECT COUNT(*) FROM kronos_posts WHERE status = 'archived'"),
];

$where = [];
$args  = [];
if ($status !== '') {
    $where[] = 'p.status = ?';
    $args[] = $status;
}
if ($type !== '') {
    $where[] = 'p.post_type = ?';
    $args[] = $type;
}
if ($search !== '') {
    $where[] = '(p.title LIKE ? OR p.slug LIKE ? OR p.content LIKE ?)';
    $like = '%' . $search . '%';
    array_push($args, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$posts = $db->getResults(
    "SELECT p.id, p.title, p.slug, p.post_type, p.status, p.published_at, p.updated_at, u.display_name AS author
     FROM kronos_posts p
     LEFT JOIN kronos_users u ON u.id = p.author_id
     {$whereSql}
     ORDER BY p.updated_at DESC LIMIT 100",
    $args
);

$statusUrl = function(string $labelStatus) use ($type, $search): string {
    $query = [];
    if ($labelStatus !== '') $query['status'] = $labelStatus;
    if ($type !== '') $query['post_type'] = $type;
    if ($search !== '') $query['s'] = $search;
    return kronos_url('/dashboard/content') . ($query ? '?' . http_build_query($query) : '');
};
?>

<div class="wp-list-header">
  <div>
    <h2>Posts & Pages</h2>
    <div class="wp-view-links">
      <a href="<?= $statusUrl('') ?>" class="<?= $status === '' ? 'current' : '' ?>">All <span><?= $counts['all'] ?></span></a>
      <a href="<?= $statusUrl('published') ?>" class="<?= $status === 'published' ? 'current' : '' ?>">Published <span><?= $counts['published'] ?></span></a>
      <a href="<?= $statusUrl('draft') ?>" class="<?= $status === 'draft' ? 'current' : '' ?>">Drafts <span><?= $counts['draft'] ?></span></a>
      <a href="<?= $statusUrl('archived') ?>" class="<?= $status === 'archived' ? 'current' : '' ?>">Archived <span><?= $counts['archived'] ?></span></a>
    </div>
  </div>
  <a href="<?= kronos_url('/dashboard/content/new') ?>" class="btn btn-primary">Add New</a>
</div>

<div class="toolbar wp-list-toolbar">
  <form method="get" action="<?= kronos_url('/dashboard/content') ?>" class="wp-filter-form">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= kronos_e($status) ?>"><?php endif; ?>
    <select name="post_type">
      <option value="">All types</option>
      <option value="post" <?= $type === 'post' ? 'selected' : '' ?>>Posts</option>
      <option value="page" <?= $type === 'page' ? 'selected' : '' ?>>Pages</option>
    </select>
    <input type="search" name="s" class="search-box" value="<?= kronos_e($search) ?>" placeholder="Search content...">
    <button type="submit" class="btn btn-secondary">Filter</button>
    <?php if ($status !== '' || $type !== '' || $search !== ''): ?>
    <a href="<?= kronos_url('/dashboard/content') ?>" class="btn btn-ghost">Reset</a>
    <?php endif; ?>
  </form>
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
      <tr><td colspan="6" class="text-center text-muted">No content matched this view. <a href="<?= kronos_url('/dashboard/content/new') ?>">Create a new item</a></td></tr>
      <?php else: ?>
      <?php foreach ($posts as $post): ?>
      <?php
        $publicPath = ($post['post_type'] ?? 'post') === 'page'
            ? '/page/' . (string) $post['slug']
            : '/post/' . (string) $post['slug'];
      ?>
      <tr id="post-row-<?= (int)$post['id'] ?>">
        <td>
          <strong><a href="<?= kronos_url('/dashboard/content/' . (int)$post['id']) ?>"><?= kronos_e($post['title']) ?></a></strong>
          <div class="row-actions">
            <a href="<?= kronos_url('/dashboard/content/' . (int)$post['id']) ?>">Edit</a>
            <?php if (($post['status'] ?? '') === 'published'): ?>
            <span>|</span><a href="<?= kronos_url($publicPath) ?>" target="_blank">View</a>
            <?php endif; ?>
            <span>|</span><button class="link-danger"
                  data-delete-url="/api/kronos/v1/content/posts/<?= (int)$post['id'] ?>"
                  data-delete-target="#post-row-<?= (int)$post['id'] ?>"
                  data-confirm-label="<?= kronos_e($post['title']) ?>">Trash</button>
          </div>
          <small class="text-muted">/<?= kronos_e($post['slug']) ?> · updated <?= kronos_e(date('M j, Y', strtotime($post['updated_at']))) ?></small>
        </td>
        <td><span class="badge"><?= kronos_e($post['post_type']) ?></span></td>
        <td><span class="badge badge-<?= kronos_e($post['status']) ?>"><?= kronos_e($post['status']) ?></span></td>
        <td><?= kronos_e($post['author'] ?? '—') ?></td>
        <td><?= $post['published_at'] ? kronos_e(date('Y-m-d', strtotime($post['published_at']))) : '—' ?></td>
        <td>
          <a href="<?= kronos_url('/dashboard/content/' . (int)$post['id']) ?>" class="action-btn">Edit</a>
          <button class="action-btn danger"
                  data-delete-url="/api/kronos/v1/content/posts/<?= (int)$post['id'] ?>"
                  data-delete-target="#post-row-<?= (int)$post['id'] ?>"
                  data-confirm-label="<?= kronos_e($post['title']) ?>">
            Delete
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
