<?php
declare(strict_types=1);

$pageTitle = 'Comments';
$dashDir = dirname(__DIR__);
$db = $app->db();

kronos_ensure_comment_tables();

$allowedStatuses = ['pending', 'approved', 'spam', 'trash'];
$status = in_array($_GET['status'] ?? '', $allowedStatuses, true) ? (string) $_GET['status'] : '';
$search = trim((string) ($_GET['s'] ?? ''));
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    kronos_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $ids = array_values(array_unique(array_filter(array_map('intval', $_POST['comment_ids'] ?? []))));
    $singleId = (int) ($_POST['comment_id'] ?? 0);
    if ($singleId > 0) {
        $ids = [$singleId];
    }

    if (!$ids) {
        $error = 'Select at least one comment.';
    } elseif (in_array($action, ['approve', 'pending', 'spam', 'trash'], true)) {
        $nextStatus = $action === 'approve' ? 'approved' : $action;
        foreach ($ids as $id) {
            $db->update('kronos_comments', [
                'status' => $nextStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);
        }
        $notice = count($ids) . ' comment(s) updated.';
    } elseif ($action === 'delete') {
        foreach ($ids as $id) {
            $db->delete('kronos_comments', ['id' => $id]);
        }
        $notice = count($ids) . ' comment(s) deleted permanently.';
    }
}

$counts = kronos_comment_counts();
$where = [];
$args = [];
if ($status !== '') {
    $where[] = 'c.status = ?';
    $args[] = $status;
}
if ($search !== '') {
    $where[] = '(c.author_name LIKE ? OR c.author_email LIKE ? OR c.content LIKE ? OR p.title LIKE ?)';
    $like = '%' . $search . '%';
    array_push($args, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$comments = $db->getResults(
    "SELECT c.*, p.title AS post_title, p.slug AS post_slug, p.post_type
     FROM kronos_comments c
     LEFT JOIN kronos_posts p ON p.id = c.post_id
     {$whereSql}
     ORDER BY c.created_at DESC
     LIMIT 100",
    $args
);

$statusUrl = function(string $filterStatus) use ($search): string {
    $query = [];
    if ($filterStatus !== '') {
        $query['status'] = $filterStatus;
    }
    if ($search !== '') {
        $query['s'] = $search;
    }
    return kronos_url('/dashboard/comments') . ($query ? '?' . http_build_query($query) : '');
};

require $dashDir . '/partials/layout-header.php';
?>

<?php if ($notice): ?><div class="alert alert-success"><?= kronos_e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= kronos_e($error) ?></div><?php endif; ?>

<div class="wp-list-header">
  <div>
    <h2>Comments</h2>
    <div class="wp-view-links">
      <a href="<?= $statusUrl('') ?>" class="<?= $status === '' ? 'current' : '' ?>">All <span><?= $counts['all'] ?></span></a>
      <a href="<?= $statusUrl('pending') ?>" class="<?= $status === 'pending' ? 'current' : '' ?>">Pending <span><?= $counts['pending'] ?></span></a>
      <a href="<?= $statusUrl('approved') ?>" class="<?= $status === 'approved' ? 'current' : '' ?>">Approved <span><?= $counts['approved'] ?></span></a>
      <a href="<?= $statusUrl('spam') ?>" class="<?= $status === 'spam' ? 'current' : '' ?>">Spam <span><?= $counts['spam'] ?></span></a>
      <a href="<?= $statusUrl('trash') ?>" class="<?= $status === 'trash' ? 'current' : '' ?>">Trash <span><?= $counts['trash'] ?></span></a>
    </div>
  </div>
</div>

<div class="toolbar wp-list-toolbar">
  <form method="get" action="<?= kronos_url('/dashboard/comments') ?>" class="wp-filter-form">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= kronos_e($status) ?>"><?php endif; ?>
    <input type="search" name="s" class="search-box" value="<?= kronos_e($search) ?>" placeholder="Search comments...">
    <button type="submit" class="btn btn-secondary">Search</button>
    <?php if ($status !== '' || $search !== ''): ?>
    <a href="<?= kronos_url('/dashboard/comments') ?>" class="btn btn-ghost">Reset</a>
    <?php endif; ?>
  </form>
</div>

<form id="comment-bulk-form" method="post" action="<?= kronos_url('/dashboard/comments') . '?' . http_build_query($_GET) ?>">
  <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
  <div class="card">
    <div class="list-bulk-bar">
      <select name="action">
        <option value="">Bulk action...</option>
        <option value="approve">Approve</option>
        <option value="pending">Mark pending</option>
        <option value="spam">Mark spam</option>
        <option value="trash">Move to trash</option>
        <option value="delete">Delete permanently</option>
      </select>
      <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
    </div>

    <table class="data-table comments-table">
      <thead>
        <tr>
          <th class="check-column"><input type="checkbox" data-check-all></th>
          <th>Author</th>
          <th>Comment</th>
          <th>In Response To</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$comments): ?>
        <tr><td colspan="6" class="text-center text-muted">No comments matched this view.</td></tr>
        <?php else: ?>
        <?php foreach ($comments as $comment): ?>
        <?php
          $publicPath = kronos_public_content_path([
              'post_type' => (string) ($comment['post_type'] ?? 'post'),
              'slug' => (string) ($comment['post_slug'] ?? ''),
          ]);
        ?>
        <tr>
          <td class="check-column"><input type="checkbox" name="comment_ids[]" value="<?= (int) $comment['id'] ?>"></td>
          <td>
            <strong><?= kronos_e((string) $comment['author_name']) ?></strong><br>
            <a href="mailto:<?= kronos_e((string) $comment['author_email']) ?>"><?= kronos_e((string) $comment['author_email']) ?></a>
            <?php if (!empty($comment['author_url'])): ?><br><a href="<?= kronos_e((string) $comment['author_url']) ?>" target="_blank" rel="noopener">Website</a><?php endif; ?>
          </td>
          <td>
            <p class="comment-excerpt"><?= nl2br(kronos_e((string) $comment['content'])) ?></p>
            <div class="row-actions">
              <?php foreach (['approve' => 'Approve', 'pending' => 'Pending', 'spam' => 'Spam', 'trash' => 'Trash', 'delete' => 'Delete'] as $action => $label): ?>
              <button type="submit" name="action" value="<?= $action ?>" formaction="<?= kronos_url('/dashboard/comments') . '?' . http_build_query($_GET) ?>" onclick="this.form.comment_id.value='<?= (int) $comment['id'] ?>';">
                <?= kronos_e($label) ?>
              </button>
              <?php endforeach; ?>
            </div>
          </td>
          <td>
            <strong><?= kronos_e((string) ($comment['post_title'] ?? 'Unknown post')) ?></strong><br>
            <?php if (!empty($comment['post_slug'])): ?>
            <a href="<?= kronos_url($publicPath) ?>#comments" target="_blank">View post</a>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-<?= kronos_e((string) $comment['status']) ?>"><?= kronos_e((string) $comment['status']) ?></span></td>
          <td><?= kronos_e(date('Y-m-d H:i', strtotime((string) $comment['created_at']))) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    <input type="hidden" name="comment_id" value="">
  </div>
</form>

<script>
(function(){
  var toggle = document.querySelector('[data-check-all]');
  if (!toggle) return;
  toggle.addEventListener('change', function(){
    document.querySelectorAll('input[name="comment_ids[]"]').forEach(function(input){
      input.checked = toggle.checked;
    });
  });
})();
</script>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
