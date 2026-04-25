<?php
declare(strict_types=1);

$dashDir = dirname(__DIR__);
$db = $app->db();
$mediaId = (int) ($params['id'] ?? 0);
$notice = '';
$errors = [];

kronos_ensure_media_table();

$media = $db->getRow('SELECT * FROM kronos_media WHERE id = ?', [$mediaId]);
if (!$media) {
    kronos_abort(404, 'Attachment not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    kronos_verify_csrf();
    $db->update('kronos_media', [
        'alt_text' => trim((string) ($_POST['alt_text'] ?? '')),
        'caption' => trim((string) ($_POST['caption'] ?? '')),
        'updated_at' => date('Y-m-d H:i:s'),
    ], ['id' => $mediaId]);
    $notice = 'Attachment details saved.';
    $media = $db->getRow('SELECT * FROM kronos_media WHERE id = ?', [$mediaId]);
}

$fileUrl = (string) ($media['file_url'] ?? '');
$likeUrl = '%' . $fileUrl . '%';
$usage = $fileUrl !== ''
    ? $db->getResults(
        "SELECT id, title, slug, post_type, status, updated_at
         FROM kronos_posts
         WHERE content LIKE ? OR meta LIKE ?
         ORDER BY updated_at DESC
         LIMIT 50",
        [$likeUrl, $likeUrl]
    )
    : [];

$ext = strtolower(pathinfo((string) $media['file_name'], PATHINFO_EXTENSION));
$isImage = in_array($ext, ['jpg','jpeg','png','gif','webp','svg'], true);
$pageTitle = 'Attachment: ' . (string) $media['file_name'];

require $dashDir . '/partials/layout-header.php';
?>

<?php if ($notice): ?><div class="alert alert-success"><?= kronos_e($notice) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><?= kronos_e(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="page-header">
  <div>
    <h1><?= kronos_e((string) $media['file_name']) ?></h1>
    <p class="text-muted">Attachment metadata, URL, and where this file is used.</p>
  </div>
  <a href="<?= kronos_url('/dashboard/media') ?>" class="btn btn-secondary">Back to Media</a>
</div>

<div class="attachment-detail-layout">
  <div class="card">
    <div class="attachment-preview">
      <?php if ($isImage): ?>
        <img src="<?= kronos_e($fileUrl) ?>" alt="<?= kronos_e($media['alt_text'] ?? '') ?>">
      <?php else: ?>
        <span><?= kronos_e(strtoupper($ext ?: 'FILE')) ?></span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <dl class="attachment-facts">
        <dt>URL</dt>
        <dd><input type="text" readonly value="<?= kronos_e($fileUrl) ?>"></dd>
        <dt>Type</dt>
        <dd><?= kronos_e((string) ($media['mime_type'] ?? '')) ?></dd>
        <dt>Size</dt>
        <dd><?= number_format(((int) ($media['file_size'] ?? 0)) / 1024, 1) ?> KB</dd>
        <?php if (!empty($media['width']) && !empty($media['height'])): ?>
        <dt>Dimensions</dt>
        <dd><?= (int) $media['width'] ?> x <?= (int) $media['height'] ?></dd>
        <?php endif; ?>
        <dt>Uploaded</dt>
        <dd><?= kronos_e((string) ($media['created_at'] ?? '')) ?></dd>
      </dl>
    </div>
  </div>

  <div class="attachment-side">
    <div class="card">
      <div class="card-header"><span class="card-title">Attachment Details</span></div>
      <div class="card-body">
        <form method="post" action="<?= kronos_url('/dashboard/media/' . $mediaId) ?>">
          <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
          <div class="form-group">
            <label>Alt text</label>
            <input type="text" name="alt_text" value="<?= kronos_e($media['alt_text'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Caption</label>
            <textarea name="caption" rows="4"><?= kronos_e($media['caption'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Save Attachment</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Used In</span></div>
      <div class="card-body">
        <?php if (!$usage): ?>
          <p class="text-muted">No posts or pages reference this file yet.</p>
        <?php else: ?>
          <div class="usage-list">
            <?php foreach ($usage as $post): ?>
              <a href="<?= kronos_url('/dashboard/content/' . (int) $post['id']) ?>" class="usage-item">
                <strong><?= kronos_e($post['title']) ?></strong>
                <small><?= kronos_e(ucfirst($post['post_type'])) ?> / <?= kronos_e($post['status']) ?> / updated <?= kronos_e(date('M j, Y', strtotime((string) $post['updated_at']))) ?></small>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
