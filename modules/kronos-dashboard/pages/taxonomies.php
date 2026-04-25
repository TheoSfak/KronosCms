<?php
declare(strict_types=1);

$dashDir   = dirname(__DIR__);
$db        = $app->db();

kronos_ensure_taxonomy_tables();

$taxonomy = ($_GET['taxonomy'] ?? $_POST['taxonomy'] ?? 'category') === 'tag' ? 'tag' : 'category';
$labelSingular = $taxonomy === 'tag' ? 'Tag' : 'Category';
$labelPlural = $taxonomy === 'tag' ? 'Tags' : 'Categories';
$pageTitle = $labelPlural;
$notice = '';
$errors = [];

$uniqueSlug = function(string $name, string $taxonomy, int $ignoreId = 0) use ($db): string {
    $base = kronos_sanitize_slug($name) ?: 'term';
    $slug = $base;
    $i = 2;
    while (true) {
        $existing = $db->getRow(
            'SELECT id FROM kronos_terms WHERE slug = ? AND taxonomy = ? LIMIT 1',
            [$slug, $taxonomy]
        );
        if (!$existing || (int) $existing['id'] === $ignoreId) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    kronos_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_term') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $errors[] = $labelSingular . ' name is required.';
        } else {
            $db->insert('kronos_terms', [
                'name' => $name,
                'slug' => $uniqueSlug($name, $taxonomy),
                'taxonomy' => $taxonomy,
                'parent_id' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $notice = $labelSingular . ' added.';
        }
    }

    if ($action === 'save_terms') {
        foreach (array_map('intval', $_POST['term_id'] ?? []) as $termId) {
            if (!empty($_POST['delete'][$termId])) {
                $db->delete('kronos_term_relationships', ['term_id' => $termId]);
                $db->delete('kronos_terms', ['id' => $termId, 'taxonomy' => $taxonomy]);
                continue;
            }
            $name = trim((string) ($_POST['term_name'][$termId] ?? ''));
            if ($name === '') {
                $name = $labelSingular;
            }
            $db->update('kronos_terms', [
                'name' => $name,
                'slug' => $uniqueSlug((string) ($_POST['term_slug'][$termId] ?? $name), $taxonomy, $termId),
            ], ['id' => $termId, 'taxonomy' => $taxonomy]);
        }
        $notice = $labelPlural . ' saved.';
    }
}

$terms = $db->getResults(
    "SELECT t.*, COUNT(r.post_id) AS usage_count
     FROM kronos_terms t
     LEFT JOIN kronos_term_relationships r ON r.term_id = t.id
     WHERE t.taxonomy = ?
     GROUP BY t.id
     ORDER BY t.name ASC",
    [$taxonomy]
);

require $dashDir . '/partials/layout-header.php';
?>

<?php if ($notice): ?><div class="alert alert-success"><?= kronos_e($notice) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><?= kronos_e(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="wp-list-header">
  <div>
    <h2><?= kronos_e($labelPlural) ?></h2>
    <div class="wp-view-links">
      <a href="<?= kronos_url('/dashboard/taxonomies?taxonomy=category') ?>" class="<?= $taxonomy === 'category' ? 'current' : '' ?>">Categories</a>
      <a href="<?= kronos_url('/dashboard/taxonomies?taxonomy=tag') ?>" class="<?= $taxonomy === 'tag' ? 'current' : '' ?>">Tags</a>
    </div>
  </div>
  <a href="<?= kronos_url('/dashboard/posts') ?>" class="btn btn-ghost">Back to Posts</a>
</div>

<div class="taxonomy-layout">
  <div class="card">
    <div class="card-header"><span class="card-title">Add New <?= kronos_e($labelSingular) ?></span></div>
    <div class="card-body">
      <form method="post" action="<?= kronos_url('/dashboard/taxonomies?taxonomy=' . $taxonomy) ?>">
        <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
        <input type="hidden" name="action" value="add_term">
        <input type="hidden" name="taxonomy" value="<?= kronos_e($taxonomy) ?>">
        <div class="form-group">
          <label>Name</label>
          <input type="text" name="name" placeholder="<?= kronos_e($labelSingular) ?> name">
          <small>The name is how it appears on your site.</small>
        </div>
        <button type="submit" class="btn btn-primary">Add <?= kronos_e($labelSingular) ?></button>
      </form>
    </div>
  </div>

  <form method="post" action="<?= kronos_url('/dashboard/taxonomies?taxonomy=' . $taxonomy) ?>">
    <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
    <input type="hidden" name="action" value="save_terms">
    <input type="hidden" name="taxonomy" value="<?= kronos_e($taxonomy) ?>">
    <div class="card">
      <div class="card-header">
        <span class="card-title">Manage <?= kronos_e($labelPlural) ?></span>
        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Slug</th>
            <th>Count</th>
            <th>Remove</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$terms): ?>
          <tr><td colspan="4" class="text-center text-muted">No <?= kronos_e(strtolower($labelPlural)) ?> yet.</td></tr>
          <?php else: ?>
          <?php foreach ($terms as $term): ?>
          <tr>
            <td>
              <input type="hidden" name="term_id[]" value="<?= (int) $term['id'] ?>">
              <input type="text" name="term_name[<?= (int) $term['id'] ?>]" value="<?= kronos_e($term['name']) ?>">
            </td>
            <td><input type="text" name="term_slug[<?= (int) $term['id'] ?>]" value="<?= kronos_e($term['slug']) ?>"></td>
            <td><span class="badge"><?= (int) $term['usage_count'] ?></span></td>
            <td><label class="menu-delete"><input type="checkbox" name="delete[<?= (int) $term['id'] ?>]" value="1"> Delete</label></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </form>
</div>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
