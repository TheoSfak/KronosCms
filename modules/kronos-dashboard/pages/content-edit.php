<?php
declare(strict_types=1);

/**
 * Content editor — create or edit a post / page.
 * Route: GET|POST /dashboard/content/new
 *        GET|POST /dashboard/content/{id}/edit
 */

$user = $_REQUEST['_kronos_user'] ?? null;
$app  = \Kronos\Core\KronosApp::getInstance();
$db   = $app->db();

// Determine if we're editing an existing post
$postId = (int) ($routeParams['id'] ?? 0);
$isEdit = $postId > 0;

$post = null;
if ($isEdit) {
    $post = $db->getRow('SELECT * FROM kronos_posts WHERE id = ?', [$postId]);
    if (!$post) {
        kronos_abort(404, 'Post not found');
    }
}

// ── Handle form submission ──────────────────────────────────────────
$errors   = [];
$success  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!kronos_verify_csrf($_POST['_kronos_csrf'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $title    = trim($_POST['title'] ?? '');
        $slug     = kronos_sanitize_slug($_POST['slug'] ?? $title);
        $postType = in_array($_POST['post_type'] ?? '', ['post', 'page'], true) ? $_POST['post_type'] : 'post';
        $status   = in_array($_POST['status'] ?? '', ['published', 'draft'], true) ? $_POST['status'] : 'draft';
        $layoutId = !empty($_POST['layout_id']) ? (int) $_POST['layout_id'] : null;

        if ($title === '') {
            $errors[] = 'Title is required.';
        }
        if ($slug === '') {
            $errors[] = 'Slug is required.';
        }

        if (empty($errors)) {
            $data = [
                'title'      => $title,
                'slug'       => $slug,
                'post_type'  => $postType,
                'status'     => $status,
                'layout_id'  => $layoutId,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($isEdit) {
                $db->update('kronos_posts', $data, ['id' => $postId]);
                $success = true;
                // Refresh data
                $post = $db->getRow('SELECT * FROM kronos_posts WHERE id = ?', [$postId]);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $newId = $db->insert('kronos_posts', $data);
                if ($newId) {
                    kronos_redirect('/dashboard/content/' . $newId . '/edit?created=1');
                } else {
                    $errors[] = 'Failed to create post. Please try again.';
                }
            }
        }
    }
}

// Fetch layouts for selector
$layouts = $db->getResults('SELECT id, layout_name AS name FROM kronos_builder_layouts ORDER BY layout_name ASC', []) ?? [];

$pageTitle = $isEdit ? 'Edit: ' . kronos_e($post['title']) : 'New Post';

require __DIR__ . '/../partials/layout-header.php';
?>
<div class="page-content">
  <div class="page-header">
    <h1><?= $pageTitle ?></h1>
    <div class="page-actions">
      <a href="<?= kronos_url('/dashboard/content') ?>" class="btn btn-secondary">← Back to Content</a>
      <?php if ($isEdit): ?>
        <a href="<?= kronos_url('/dashboard/builder/' . (int)($post['layout_id'] ?? 1)) ?>" class="btn btn-ghost">🔨 Open Builder</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $e) echo kronos_e($e) . '<br>'; ?>
  </div>
  <?php endif; ?>

  <?php if ($success): ?>
  <div class="alert alert-success">Post saved successfully.</div>
  <?php endif; ?>

  <?php if (isset($_GET['created'])): ?>
  <div class="alert alert-success">Post created! You can now open the Builder to design its layout.</div>
  <?php endif; ?>

  <form method="POST" action="<?= $isEdit ? "/dashboard/content/{$postId}/edit" : '/dashboard/content/new' ?>">
    <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">

    <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;">

      <!-- ── Main fields ── -->
      <div>
        <div class="card">
          <div class="card-body">
            <div class="form-group">
              <label>Title</label>
              <input type="text" name="title" id="post-title"
                     value="<?= kronos_e($post['title'] ?? '') ?>"
                     placeholder="Post title…" required>
            </div>
            <div class="form-group">
              <label>Slug</label>
              <input type="text" name="slug" id="post-slug"
                     value="<?= kronos_e($post['slug'] ?? '') ?>"
                     placeholder="post-slug" pattern="[a-z0-9\-]+" required>
              <small>URL-friendly identifier (lowercase letters, numbers, hyphens).</small>
            </div>

            <?php if ($isEdit): ?>
            <div class="form-group">
              <label>Linked Builder Layout</label>
              <select name="layout_id" id="layout-select">
                <option value="">— No layout —</option>
                <?php foreach ($layouts as $layout): ?>
                  <option value="<?= (int)$layout['id'] ?>"
                    <?= ((int)($post['layout_id'] ?? 0) === (int)$layout['id']) ? 'selected' : '' ?>>
                    <?= kronos_e($layout['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small>Assign a page builder layout to this post.</small>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ── Sidebar meta ── -->
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div class="card">
          <div class="card-header"><span class="card-title">Publish</span></div>
          <div class="card-body">
            <div class="form-group">
              <label>Status</label>
              <select name="status">
                <option value="draft"     <?= (($post['status'] ?? 'draft') === 'draft')     ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= (($post['status'] ?? '') === 'published') ? 'selected' : '' ?>>Published</option>
              </select>
            </div>
            <div class="form-group">
              <label>Type</label>
              <select name="post_type">
                <option value="post" <?= (($post['post_type'] ?? 'post') === 'post') ? 'selected' : '' ?>>Post</option>
                <option value="page" <?= (($post['post_type'] ?? '') === 'page') ? 'selected' : '' ?>>Page</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary btn-block">
              <?= $isEdit ? 'Update Post' : 'Create Post' ?>
            </button>
          </div>
        </div>

        <?php if ($isEdit): ?>
        <div class="card">
          <div class="card-body">
            <p class="text-sm text-muted">Created: <?= kronos_e($post['created_at'] ?? '—') ?></p>
            <p class="text-sm text-muted mt-4">Updated: <?= kronos_e($post['updated_at'] ?? '—') ?></p>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </form>
</div>

<script>
// Auto-generate slug from title on new posts
(function(){
  var titleInput = document.getElementById('post-title');
  var slugInput  = document.getElementById('post-slug');
  if (!titleInput || !slugInput) return;
  var edited = <?= $isEdit ? 'true' : 'false' ?>;
  if (edited) return; // Don't auto-change slug when editing

  titleInput.addEventListener('input', function(){
    slugInput.value = this.value
      .toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .trim()
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-');
  });
})();
</script>

<?php require __DIR__ . '/../partials/layout-footer.php'; ?>
