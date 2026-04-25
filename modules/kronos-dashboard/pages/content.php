<?php
declare(strict_types=1);
$dashDir   = dirname(__DIR__);

$db         = $app->db();
kronos_ensure_editor_tables();

$allowedStatuses = ['draft', 'published', 'scheduled', 'private', 'archived'];
$forcedType = in_array($params['post_type'] ?? '', ['post', 'page'], true) ? (string) $params['post_type'] : '';
$status     = in_array($_GET['status'] ?? '', $allowedStatuses, true) ? (string) $_GET['status'] : '';
$type       = $forcedType ?: (in_array($_GET['post_type'] ?? '', ['post', 'page'], true) ? (string) $_GET['post_type'] : '');
$search     = trim((string) ($_GET['s'] ?? ''));
$perPage    = in_array((int) ($_GET['per_page'] ?? 20), [20, 50, 100], true) ? (int) $_GET['per_page'] : 20;
$baseUrl    = $forcedType === 'post' ? '/dashboard/posts' : ($forcedType === 'page' ? '/dashboard/pages' : '/dashboard/content');
$newUrl     = $forcedType === 'post' ? '/dashboard/posts/new' : ($forcedType === 'page' ? '/dashboard/pages/new' : '/dashboard/content/new');
$labelSingular = $type === 'page' ? 'Page' : 'Post';
$labelPlural   = $forcedType === 'page' ? 'Pages' : ($forcedType === 'post' ? 'Posts' : 'Posts & Pages');
$notice = isset($_GET['bulk_updated']) ? 'Bulk status update applied.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    kronos_verify_csrf();
    if (($_POST['action'] ?? '') === 'bulk_status') {
        $bulkStatus = in_array($_POST['bulk_status'] ?? '', $allowedStatuses, true) ? (string) $_POST['bulk_status'] : '';
        $bulkIds = array_values(array_unique(array_filter(array_map('intval', $_POST['bulk_ids'] ?? []))));
        if ($bulkStatus !== '' && $bulkIds) {
            foreach ($bulkIds as $bulkId) {
                $data = [
                    'status' => $bulkStatus,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                if (in_array($bulkStatus, ['published', 'scheduled'], true)) {
                    $data['published_at'] = date('Y-m-d H:i:s');
                }
                $db->update('kronos_posts', $data, ['id' => $bulkId]);
            }
            $query = $_GET;
            $query['bulk_updated'] = 1;
            kronos_redirect($baseUrl . '?' . http_build_query($query));
        }
    }
}

$countWhere = $forcedType !== '' ? ' WHERE post_type = ?' : '';
$countArgs  = $forcedType !== '' ? [$forcedType] : [];
$countStatus = function(string $countStatus) use ($db, $forcedType): int {
    $args = [$countStatus];
    $where = 'WHERE status = ?';
    if ($forcedType !== '') {
        $where .= ' AND post_type = ?';
        $args[] = $forcedType;
    }
    return (int) $db->getVar("SELECT COUNT(*) FROM kronos_posts {$where}", $args);
};

$counts = [
    'all'       => (int) $db->getVar('SELECT COUNT(*) FROM kronos_posts' . $countWhere, $countArgs),
    'published' => $countStatus('published'),
    'draft'     => $countStatus('draft'),
    'scheduled' => $countStatus('scheduled'),
    'private'   => $countStatus('private'),
    'archived'  => $countStatus('archived'),
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
     ORDER BY p.updated_at DESC LIMIT {$perPage}",
    $args
);

$statusUrl = function(string $labelStatus) use ($type, $forcedType, $search, $baseUrl): string {
    $query = [];
    if ($labelStatus !== '') $query['status'] = $labelStatus;
    if ($type !== '' && $forcedType === '') $query['post_type'] = $type;
    if ($search !== '') $query['s'] = $search;
    return kronos_url($baseUrl) . ($query ? '?' . http_build_query($query) : '');
};

$pageTitle = $labelPlural;
require $dashDir . '/partials/layout-header.php';
?>

<?php if ($notice): ?><div class="alert alert-success"><?= kronos_e($notice) ?></div><?php endif; ?>

<div class="wp-list-header">
  <div>
    <h2><?= kronos_e($labelPlural) ?></h2>
    <div class="wp-view-links">
      <a href="<?= $statusUrl('') ?>" class="<?= $status === '' ? 'current' : '' ?>">All <span><?= $counts['all'] ?></span></a>
      <a href="<?= $statusUrl('published') ?>" class="<?= $status === 'published' ? 'current' : '' ?>">Published <span><?= $counts['published'] ?></span></a>
      <a href="<?= $statusUrl('draft') ?>" class="<?= $status === 'draft' ? 'current' : '' ?>">Drafts <span><?= $counts['draft'] ?></span></a>
      <a href="<?= $statusUrl('scheduled') ?>" class="<?= $status === 'scheduled' ? 'current' : '' ?>">Scheduled <span><?= $counts['scheduled'] ?></span></a>
      <a href="<?= $statusUrl('private') ?>" class="<?= $status === 'private' ? 'current' : '' ?>">Private <span><?= $counts['private'] ?></span></a>
      <a href="<?= $statusUrl('archived') ?>" class="<?= $status === 'archived' ? 'current' : '' ?>">Archived <span><?= $counts['archived'] ?></span></a>
    </div>
  </div>
  <a href="<?= kronos_url($newUrl) ?>" class="btn btn-primary">Add New</a>
</div>

<div class="toolbar wp-list-toolbar">
  <form method="get" action="<?= kronos_url($baseUrl) ?>" class="wp-filter-form">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= kronos_e($status) ?>"><?php endif; ?>
    <?php if ($forcedType !== ''): ?><input type="hidden" name="post_type" value="<?= kronos_e($forcedType) ?>"><?php endif; ?>
    <?php if ($forcedType === ''): ?>
    <select name="post_type">
      <option value="">All types</option>
      <option value="post" <?= $type === 'post' ? 'selected' : '' ?>>Posts</option>
      <option value="page" <?= $type === 'page' ? 'selected' : '' ?>>Pages</option>
    </select>
    <?php endif; ?>
    <input type="search" name="s" class="search-box" value="<?= kronos_e($search) ?>" placeholder="Search content...">
    <select name="per_page">
      <option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20 per page</option>
      <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50 per page</option>
      <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100 per page</option>
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <?php if ($status !== '' || ($forcedType === '' && $type !== '') || $search !== ''): ?>
    <a href="<?= kronos_url($baseUrl) ?>" class="btn btn-ghost">Reset</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <form method="post" action="<?= kronos_url($baseUrl) . '?' . http_build_query($_GET) ?>">
    <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
    <input type="hidden" name="action" value="bulk_status">
    <div class="list-bulk-bar">
      <select name="bulk_status">
        <option value="">Bulk change status...</option>
        <option value="draft">Draft</option>
        <option value="published">Published</option>
        <option value="scheduled">Scheduled</option>
        <option value="private">Private</option>
        <option value="archived">Archived</option>
      </select>
      <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
    </div>
    <table class="data-table">
      <thead>
        <tr>
          <th class="check-column"><input type="checkbox" data-check-all></th>
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
      <tr><td colspan="7" class="text-center text-muted">No <?= kronos_e(strtolower($labelPlural)) ?> matched this view. <a href="<?= kronos_url($newUrl) ?>">Create a new <?= kronos_e(strtolower($labelSingular)) ?></a></td></tr>
      <?php else: ?>
      <?php foreach ($posts as $post): ?>
      <?php
        $publicPath = ($post['post_type'] ?? 'post') === 'page'
            ? '/page/' . (string) $post['slug']
            : '/post/' . (string) $post['slug'];
      ?>
      <tr id="post-row-<?= (int)$post['id'] ?>">
        <td class="check-column"><input type="checkbox" name="bulk_ids[]" value="<?= (int) $post['id'] ?>"></td>
        <td>
          <strong><a href="<?= kronos_url('/dashboard/content/' . (int)$post['id']) ?>"><?= kronos_e($post['title']) ?></a></strong>
          <div class="row-actions">
            <a href="<?= kronos_url('/dashboard/content/' . (int)$post['id']) ?>">Edit</a>
            <?php if (($post['status'] ?? '') === 'published'): ?>
            <span>|</span><a href="<?= kronos_url($publicPath) ?>" target="_blank">View</a>
            <?php else: ?>
            <span>|</span><a href="<?= kronos_url($publicPath) ?>?preview=1" target="_blank">Preview</a>
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
  </form>
</div>

<script>
(function(){
  var toggle = document.querySelector('[data-check-all]');
  if (!toggle) return;
  toggle.addEventListener('change', function(){
    document.querySelectorAll('input[name="bulk_ids[]"]').forEach(function(input){
      input.checked = toggle.checked;
    });
  });
})();
</script>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
