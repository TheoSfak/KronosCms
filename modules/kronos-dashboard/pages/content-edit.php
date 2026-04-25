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
kronos_ensure_taxonomy_tables();
kronos_ensure_media_table();
kronos_ensure_editor_tables();

// Determine if we're editing an existing post
$postId = (int) ($params['id'] ?? 0);
$isEdit = $postId > 0;
$defaultType = in_array($params['post_type'] ?? '', ['post', 'page'], true) ? (string) $params['post_type'] : 'post';

$post = null;
if ($isEdit) {
    $post = $db->getRow('SELECT * FROM kronos_posts WHERE id = ?', [$postId]);
    if (!$post) {
        kronos_abort(404, 'Post not found');
    }
}
$postMeta = [];
if ($post && !empty($post['meta'])) {
    $decodedMeta = json_decode((string) $post['meta'], true);
    $postMeta = is_array($decodedMeta) ? $decodedMeta : [];
}

// ── Handle form submission ──────────────────────────────────────────
$errors   = [];
$success  = false;

$savePostTerms = function(int $savedPostId, array $termIds) use ($db): void {
    $db->delete('kronos_term_relationships', ['post_id' => $savedPostId]);
    $termIds = array_values(array_unique(array_filter(array_map('intval', $termIds))));
    if (!$termIds) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($termIds), '?'));
    $validIds = $db->getResults(
        "SELECT id FROM kronos_terms WHERE taxonomy IN ('category','tag') AND id IN ({$placeholders})",
        $termIds
    );
    foreach ($validIds as $term) {
        $db->insert('kronos_term_relationships', [
            'post_id' => $savedPostId,
            'term_id' => (int) $term['id'],
        ]);
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    kronos_verify_csrf(); // aborts with 403 JSON on CSRF mismatch

    $title    = trim($_POST['title'] ?? '');
    $slug     = kronos_sanitize_slug($_POST['slug'] ?? $title);
    $postType = in_array($_POST['post_type'] ?? '', ['post', 'page'], true) ? $_POST['post_type'] : $defaultType;
    $status   = in_array($_POST['status'] ?? '', ['published', 'draft', 'scheduled', 'private', 'archived'], true) ? $_POST['status'] : 'draft';
    $layoutId = !empty($_POST['layout_id']) ? (int) $_POST['layout_id'] : null;
    $meta = $postMeta;
    $featuredUrl = trim((string) ($_POST['featured_image_url'] ?? ''));
    $featuredAlt = trim((string) ($_POST['featured_image_alt'] ?? ''));
    if ($featuredUrl !== '') {
        $meta['featured_image_url'] = $featuredUrl;
        $meta['featured_image_alt'] = $featuredAlt;
    } else {
        unset($meta['featured_image_url'], $meta['featured_image_alt']);
    }

    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($slug === '') {
        $errors[] = 'Slug is required.';
    }

    if (empty($errors)) {
        $publishedAtInput = trim((string) ($_POST['published_at'] ?? ''));
        $publishedAt = $publishedAtInput !== '' ? date('Y-m-d H:i:s', strtotime($publishedAtInput)) : null;
        if ($status === 'published' && !$publishedAt) {
            $publishedAt = !empty($post['published_at']) ? (string) $post['published_at'] : date('Y-m-d H:i:s');
        }
        if ($status === 'scheduled' && !$publishedAt) {
            $publishedAt = date('Y-m-d H:i:s', strtotime('+1 day'));
        }
        if (!in_array($status, ['published', 'scheduled'], true)) {
            $publishedAt = null;
        }
        $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $data = [
            'title'      => $title,
            'slug'       => $slug,
            'content'    => (string) ($_POST['content'] ?? ($post['content'] ?? '')),
            'post_type'  => $postType,
            'status'     => $status,
            'layout_id'  => $layoutId,
            'meta'       => $metaJson,
            'published_at' => $publishedAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($isEdit) {
            if (
                (string) ($post['title'] ?? '') !== $data['title']
                || (string) ($post['content'] ?? '') !== $data['content']
                || (string) ($post['meta'] ?? '') !== (string) ($data['meta'] ?? '')
            ) {
                $db->insert('kronos_post_revisions', [
                    'post_id' => $postId,
                    'user_id' => (int) ($user['id'] ?? 0) ?: null,
                    'title' => (string) ($post['title'] ?? ''),
                    'content' => (string) ($post['content'] ?? ''),
                    'meta' => $post['meta'] ?: null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $db->update('kronos_posts', $data, ['id' => $postId]);
            $savePostTerms($postId, $postType === 'post' ? ($_POST['term_ids'] ?? []) : []);
            $success = true;
            // Refresh data
            $post = $db->getRow('SELECT * FROM kronos_posts WHERE id = ?', [$postId]);
            $postMeta = $meta;
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['author_id'] = (int) ($user['id'] ?? 0) ?: null;
            $newId = $db->insert('kronos_posts', $data);
            if ($newId) {
                $savePostTerms((int) $newId, $postType === 'post' ? ($_POST['term_ids'] ?? []) : []);
                kronos_redirect('/dashboard/content/' . $newId . '?created=1');
            } else {
                $errors[] = 'Failed to create post. Please try again.';
            }
        }
    }
}

// Fetch layouts for selector
$layouts = $db->getResults('SELECT id, layout_name AS name FROM kronos_builder_layouts ORDER BY layout_name ASC', []) ?? [];
$categories = $db->getResults("SELECT id, name FROM kronos_terms WHERE taxonomy = 'category' ORDER BY name ASC") ?? [];
$tags = $db->getResults("SELECT id, name FROM kronos_terms WHERE taxonomy = 'tag' ORDER BY name ASC") ?? [];
$mediaItems = $db->getResults("SELECT file_url, file_name, alt_text FROM kronos_media WHERE mime_type LIKE 'image/%' OR file_name LIKE '%.svg' ORDER BY created_at DESC, id DESC LIMIT 100") ?? [];
$selectedTermIds = [];
$revisions = [];
if ($isEdit) {
    $selectedTermIds = array_map(
        'intval',
        array_column($db->getResults('SELECT term_id FROM kronos_term_relationships WHERE post_id = ?', [$postId]), 'term_id')
    );
    $revisions = $db->getResults(
        'SELECT r.*, u.display_name FROM kronos_post_revisions r LEFT JOIN kronos_users u ON u.id = r.user_id WHERE r.post_id = ? ORDER BY r.created_at DESC LIMIT 8',
        [$postId]
    );
}

$currentType = (string) ($post['post_type'] ?? $defaultType);
$typeLabel = $currentType === 'page' ? 'Page' : 'Post';
$backUrl = $currentType === 'page' ? '/dashboard/pages' : '/dashboard/posts';
$newActionUrl = $currentType === 'page' ? '/dashboard/pages/new' : '/dashboard/posts/new';
$publicPath = $isEdit
    ? (($currentType === 'page' ? '/page/' : '/post/') . (string) ($post['slug'] ?? ''))
    : '';
$publishedAtValue = !empty($post['published_at']) ? date('Y-m-d\TH:i', strtotime((string) $post['published_at'])) : '';
$pageTitle = $isEdit ? 'Edit: ' . kronos_e($post['title']) : 'New ' . $typeLabel;

require __DIR__ . '/../partials/layout-header.php';
?>
<div class="page-content">
  <div class="page-header">
    <h1><?= $pageTitle ?></h1>
    <div class="page-actions">
      <a href="<?= kronos_url($backUrl) ?>" class="btn btn-secondary">← Back to <?= kronos_e($typeLabel === 'Page' ? 'Pages' : 'Posts') ?></a>
      <?php if ($isEdit): ?>
        <a href="<?= kronos_url($publicPath) ?>?preview=1" target="_blank" class="btn btn-secondary">Preview</a>
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
  <div class="alert alert-success"><?= kronos_e($typeLabel) ?> saved successfully.</div>
  <?php endif; ?>

  <?php if (isset($_GET['created'])): ?>
  <div class="alert alert-success"><?= kronos_e($typeLabel) ?> created! You can now open the Builder to design its layout.</div>
  <?php endif; ?>

  <form method="POST" action="<?= $isEdit ? kronos_url("/dashboard/content/{$postId}") : kronos_url($newActionUrl) ?>">
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

            <div class="form-group">
              <label>Content</label>
              <div class="classic-editor-toolbar">
                <button type="button" class="btn btn-secondary btn-sm" data-editor-insert="## Heading">Heading</button>
                <button type="button" class="btn btn-secondary btn-sm" data-editor-insert="Paragraph text...">Paragraph</button>
                <button type="button" class="btn btn-secondary btn-sm" data-editor-insert="> Quote text">Quote</button>
                <button type="button" class="btn btn-secondary btn-sm" data-editor-insert="Image: https://example.com/image.jpg">Image URL</button>
                <span id="classic-autosave-status" class="editor-save-state">Local autosave ready</span>
              </div>
              <textarea name="content" id="post-content" rows="14" placeholder="Write content here, or use the builder for a richer layout."><?= kronos_e($post['content'] ?? '') ?></textarea>
              <small>Classic content supports local browser autosave. Full visual layouts still live in the builder.</small>
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
              <small>Assign a page builder layout to this <?= kronos_e(strtolower($typeLabel)) ?>.</small>
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
                <option value="scheduled" <?= (($post['status'] ?? '') === 'scheduled') ? 'selected' : '' ?>>Scheduled</option>
                <option value="private"   <?= (($post['status'] ?? '') === 'private') ? 'selected' : '' ?>>Private</option>
                <option value="archived"  <?= (($post['status'] ?? '') === 'archived') ? 'selected' : '' ?>>Archived</option>
              </select>
            </div>
            <div class="form-group">
              <label>Publish date</label>
              <input type="datetime-local" name="published_at" value="<?= kronos_e($publishedAtValue) ?>">
              <small>Used for published and scheduled content.</small>
            </div>
            <div class="form-group">
              <label>Type</label>
              <select name="post_type">
                <option value="post" <?= ($currentType === 'post') ? 'selected' : '' ?>>Post</option>
                <option value="page" <?= ($currentType === 'page') ? 'selected' : '' ?>>Page</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary btn-block">
              <?= $isEdit ? 'Update ' . kronos_e($typeLabel) : 'Create ' . kronos_e($typeLabel) ?>
            </button>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">Featured Image</span></div>
          <div class="card-body">
            <div class="form-group">
              <label>Choose from Media</label>
              <select id="featured-image-select">
                <option value="">Manual URL or no image</option>
                <?php foreach ($mediaItems as $media): ?>
                <option value="<?= kronos_e($media['file_url']) ?>" data-alt="<?= kronos_e($media['alt_text'] ?? '') ?>" <?= (($postMeta['featured_image_url'] ?? '') === $media['file_url']) ? 'selected' : '' ?>>
                  <?= kronos_e($media['file_name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
              <small>Upload images in Media, then select them here.</small>
            </div>
            <div class="form-group">
              <label>Image URL</label>
              <input type="text" name="featured_image_url" id="featured-image-url" value="<?= kronos_e($postMeta['featured_image_url'] ?? '') ?>" placeholder="<?= kronos_url('/uploads/example.jpg') ?>">
            </div>
            <div class="form-group">
              <label>Alt text</label>
              <input type="text" name="featured_image_alt" id="featured-image-alt" value="<?= kronos_e($postMeta['featured_image_alt'] ?? '') ?>" placeholder="Describe the image">
            </div>
            <?php if (!empty($postMeta['featured_image_url'])): ?>
              <img class="featured-preview" src="<?= kronos_e($postMeta['featured_image_url']) ?>" alt="<?= kronos_e($postMeta['featured_image_alt'] ?? '') ?>">
            <?php endif; ?>
          </div>
        </div>

        <?php if ($currentType === 'post'): ?>
        <div class="card">
          <div class="card-header"><span class="card-title">Categories</span></div>
          <div class="card-body">
            <?php if (!$categories): ?>
              <p class="text-muted">No categories yet. <a href="<?= kronos_url('/dashboard/taxonomies?taxonomy=category') ?>">Create categories</a>.</p>
            <?php else: ?>
              <div class="term-check-list">
                <?php foreach ($categories as $term): ?>
                <label>
                  <input type="checkbox" name="term_ids[]" value="<?= (int) $term['id'] ?>" <?= in_array((int) $term['id'], $selectedTermIds, true) ? 'checked' : '' ?>>
                  <?= kronos_e($term['name']) ?>
                </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">Tags</span></div>
          <div class="card-body">
            <?php if (!$tags): ?>
              <p class="text-muted">No tags yet. <a href="<?= kronos_url('/dashboard/taxonomies?taxonomy=tag') ?>">Create tags</a>.</p>
            <?php else: ?>
              <div class="term-check-list">
                <?php foreach ($tags as $term): ?>
                <label>
                  <input type="checkbox" name="term_ids[]" value="<?= (int) $term['id'] ?>" <?= in_array((int) $term['id'], $selectedTermIds, true) ? 'checked' : '' ?>>
                  <?= kronos_e($term['name']) ?>
                </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($isEdit): ?>
        <div class="card">
          <div class="card-header"><span class="card-title">Revisions</span></div>
          <div class="card-body">
            <?php if (!$revisions): ?>
              <p class="text-muted">No revisions yet. Kronos will keep snapshots when content changes.</p>
            <?php else: ?>
              <div class="revision-list">
                <?php foreach ($revisions as $revision): ?>
                  <div class="revision-item">
                    <strong><?= kronos_e(date('M j, Y H:i', strtotime((string) $revision['created_at']))) ?></strong>
                    <small><?= kronos_e($revision['display_name'] ?: 'System') ?></small>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

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

(function(){
  var select = document.getElementById('featured-image-select');
  var url = document.getElementById('featured-image-url');
  var alt = document.getElementById('featured-image-alt');
  if (!select || !url || !alt) return;
  select.addEventListener('change', function(){
    var option = this.options[this.selectedIndex];
    if (!option || !option.value) return;
    url.value = option.value;
    if (!alt.value && option.dataset.alt) alt.value = option.dataset.alt;
  });
})();

(function(){
  var form = document.querySelector('form[method="POST"]');
  var content = document.getElementById('post-content');
  var title = document.getElementById('post-title');
  var slug = document.getElementById('post-slug');
  var status = document.getElementById('classic-autosave-status');
  if (!form || !content || !title || !slug || !status) return;
  var storageKey = 'kronos:autosave:<?= $isEdit ? 'post-' . (int) $postId : 'new-' . kronos_e($currentType) ?>';
  var restored = false;
  try {
    var saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
    if (saved && saved.updatedAt && saved.content && saved.content !== content.value) {
      restored = confirm('A local autosave from ' + saved.updatedAt + ' exists. Restore it?');
      if (restored) {
        title.value = saved.title || title.value;
        slug.value = saved.slug || slug.value;
        content.value = saved.content || content.value;
      }
    }
  } catch (err) {}

  function saveLocal() {
    var now = new Date();
    try {
      localStorage.setItem(storageKey, JSON.stringify({
        title: title.value,
        slug: slug.value,
        content: content.value,
        updatedAt: now.toLocaleString()
      }));
      status.textContent = 'Local autosaved ' + now.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
    } catch (err) {
      status.textContent = 'Local autosave unavailable';
    }
  }
  [title, slug, content].forEach(function(input){ input.addEventListener('input', saveLocal); });
  window.setInterval(saveLocal, 12000);
  form.addEventListener('submit', function(){ try { localStorage.removeItem(storageKey); } catch (err) {} });

  document.querySelectorAll('[data-editor-insert]').forEach(function(button){
    button.addEventListener('click', function(){
      var snippet = button.dataset.editorInsert || '';
      var start = content.selectionStart || content.value.length;
      var end = content.selectionEnd || content.value.length;
      var before = content.value.slice(0, start);
      var after = content.value.slice(end);
      var prefix = before && !before.endsWith('\n') ? '\n\n' : '';
      content.value = before + prefix + snippet + '\n' + after;
      content.focus();
      saveLocal();
    });
  });
})();
</script>

<?php require __DIR__ . '/../partials/layout-footer.php'; ?>
