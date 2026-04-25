<?php
declare(strict_types=1);

$pageTitle = 'Media Library';
$dashDir   = dirname(__DIR__);
$notice    = '';
$errors    = [];
$db        = $app->db();
$user      = $_REQUEST['_kronos_user'] ?? [];

kronos_ensure_media_table();

$uploadDir = rtrim(KRONOS_PUBLIC, '/\\') . DIRECTORY_SEPARATOR . 'uploads';
$uploadUrl = kronos_url('/uploads');
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    kronos_verify_csrf();

    if (($_POST['action'] ?? '') === 'save_media') {
        foreach (array_map('intval', $_POST['media_id'] ?? []) as $mediaId) {
            $db->update('kronos_media', [
                'alt_text' => trim((string) ($_POST['alt_text'][$mediaId] ?? '')),
                'caption' => trim((string) ($_POST['caption'][$mediaId] ?? '')),
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $mediaId]);
        }
        $notice = 'Media details saved.';
    } elseif (empty($_FILES['media_file']) || !is_uploaded_file($_FILES['media_file']['tmp_name'])) {
        $errors[] = 'Choose a file to upload.';
    } else {
        $file = $_FILES['media_file'];
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $errors[] = 'Unsupported file type.';
        } elseif (($file['size'] ?? 0) > 8 * 1024 * 1024) {
            $errors[] = 'File is larger than 8 MB.';
        } else {
            $base = kronos_sanitize_slug(pathinfo((string) $file['name'], PATHINFO_FILENAME)) ?: 'media';
            $filename = $base . '-' . date('YmdHis') . '.' . $ext;
            $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $url = $uploadUrl . '/' . rawurlencode($filename);
                $imageSize = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? @getimagesize($target) : null;
                $db->insert('kronos_media', [
                    'file_name' => $filename,
                    'file_path' => 'public/uploads/' . $filename,
                    'file_url' => $url,
                    'mime_type' => (string) ($file['type'] ?? mime_content_type($target) ?: ''),
                    'file_size' => (int) filesize($target),
                    'width' => is_array($imageSize) ? (int) $imageSize[0] : null,
                    'height' => is_array($imageSize) ? (int) $imageSize[1] : null,
                    'alt_text' => trim((string) ($_POST['alt_text'] ?? '')),
                    'caption' => trim((string) ($_POST['caption'] ?? '')),
                    'uploaded_by' => (int) ($user['id'] ?? 0) ?: null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $notice = 'Media uploaded.';
            } else {
                $errors[] = 'Upload failed.';
            }
        }
    }
}

foreach (glob($uploadDir . DIRECTORY_SEPARATOR . '*') ?: [] as $filePath) {
    if (!is_file($filePath)) {
        continue;
    }
    $name = basename($filePath);
    $url = $uploadUrl . '/' . rawurlencode($name);
    if (!$db->getVar('SELECT id FROM kronos_media WHERE file_url = ? LIMIT 1', [$url])) {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $imageSize = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? @getimagesize($filePath) : null;
        $db->insert('kronos_media', [
            'file_name' => $name,
            'file_path' => 'public/uploads/' . $name,
            'file_url' => $url,
            'mime_type' => mime_content_type($filePath) ?: '',
            'file_size' => (int) filesize($filePath),
            'width' => is_array($imageSize) ? (int) $imageSize[0] : null,
            'height' => is_array($imageSize) ? (int) $imageSize[1] : null,
            'created_at' => date('Y-m-d H:i:s', filemtime($filePath) ?: time()),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

$files = $db->getResults('SELECT * FROM kronos_media ORDER BY created_at DESC, id DESC');

require $dashDir . '/partials/layout-header.php';
?>

<?php if ($notice): ?><div class="alert alert-success"><?= kronos_e($notice) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><?= kronos_e(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="wp-list-header">
  <div>
    <h2>Media Library</h2>
    <div class="wp-view-links">
      <a class="current" href="<?= kronos_url('/dashboard/media') ?>">All <span><?= count($files) ?></span></a>
    </div>
  </div>
</div>

<div class="media-layout">
  <div class="card">
    <div class="card-header"><span class="card-title">Upload New Media</span></div>
    <div class="card-body">
      <form method="post" action="<?= kronos_url('/dashboard/media') ?>" enctype="multipart/form-data">
        <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
        <div class="form-group">
          <label>File</label>
          <input type="file" name="media_file" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,image/*,application/pdf">
          <small>Images and PDFs up to 8 MB.</small>
        </div>
        <div class="form-group">
          <label>Alt text</label>
          <input type="text" name="alt_text" placeholder="Describe the image">
        </div>
        <div class="form-group">
          <label>Caption</label>
          <textarea name="caption" rows="3" placeholder="Optional caption"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Upload</button>
      </form>
    </div>
  </div>

  <form method="post" action="<?= kronos_url('/dashboard/media') ?>">
    <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
    <input type="hidden" name="action" value="save_media">
  <div class="media-grid">
    <?php if (!$files): ?>
      <div class="menu-empty-state">No media yet. Upload images here, then use their URLs in the builder or content.</div>
    <?php else: ?>
      <?php foreach ($files as $file): ?>
      <?php $ext = strtolower(pathinfo((string) $file['file_name'], PATHINFO_EXTENSION)); ?>
      <div class="media-card">
        <div class="media-thumb">
          <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'], true)): ?>
            <img src="<?= kronos_e($file['file_url']) ?>" alt="<?= kronos_e($file['alt_text'] ?? '') ?>">
          <?php else: ?>
            <span><?= kronos_e(strtoupper($ext)) ?></span>
          <?php endif; ?>
        </div>
        <div class="media-meta">
          <input type="hidden" name="media_id[]" value="<?= (int) $file['id'] ?>">
          <strong><?= kronos_e($file['file_name']) ?></strong>
          <small><?= number_format(((int) $file['file_size']) / 1024, 1) ?> KB / <?= kronos_e(substr((string) $file['created_at'], 0, 10)) ?></small>
          <input type="text" readonly value="<?= kronos_e($file['file_url']) ?>">
          <a href="<?= kronos_url('/dashboard/media/' . (int) $file['id']) ?>" class="btn btn-secondary btn-sm">Attachment Details</a>
          <input type="text" name="alt_text[<?= (int) $file['id'] ?>]" value="<?= kronos_e($file['alt_text'] ?? '') ?>" placeholder="Alt text">
          <textarea name="caption[<?= (int) $file['id'] ?>]" rows="2" placeholder="Caption"><?= kronos_e($file['caption'] ?? '') ?></textarea>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
    <?php if ($files): ?>
    <div class="media-save-bar">
      <button type="submit" class="btn btn-primary">Save Media Details</button>
    </div>
    <?php endif; ?>
  </form>
</div>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
