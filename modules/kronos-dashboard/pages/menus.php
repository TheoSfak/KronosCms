<?php
declare(strict_types=1);

$pageTitle = 'Menus';
$dashDir   = dirname(__DIR__);
$db        = $app->db();

kronos_ensure_menu_tables();

$notice = '';
$errors = [];

$makeUniqueSlug = function(string $name) use ($db): string {
    $base = kronos_sanitize_slug($name) ?: 'menu';
    $slug = $base;
    $i = 2;
    while ($db->getVar('SELECT id FROM kronos_menus WHERE slug = ? LIMIT 1', [$slug])) {
        $slug = $base . '-' . $i;
        $i++;
    }
    return $slug;
};

$cleanMenuUrl = function(string $url): string {
    $url = trim($url);
    if ($url === '') {
        return '#';
    }
    if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
        return $url;
    }
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return $url;
    }
    return '/' . ltrim(kronos_sanitize_slug($url), '/');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    kronos_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_menu') {
        $name = trim((string) ($_POST['menu_name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Menu name is required.';
        } else {
            $newId = (int) $db->insert('kronos_menus', [
                'name' => $name,
                'slug' => $makeUniqueSlug($name),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            kronos_redirect('/dashboard/menus?menu_id=' . $newId . '&created=1');
        }
    }

    $selectedMenuId = (int) ($_POST['menu_id'] ?? $_GET['menu_id'] ?? 0);
    $selectedMenu = $selectedMenuId > 0 ? $db->getRow('SELECT * FROM kronos_menus WHERE id = ?', [$selectedMenuId]) : null;

    if ($action !== 'create_menu' && !$selectedMenu) {
        $errors[] = 'Select or create a menu first.';
    }

    if (!$errors && $selectedMenu) {
        if ($action === 'save_locations') {
            kronos_set_option('menu_header_id', (int) ($_POST['header_menu_id'] ?? 0));
            kronos_set_option('menu_footer_id', (int) ($_POST['footer_menu_id'] ?? 0));
            $notice = 'Menu locations saved.';
        }

        if ($action === 'add_content') {
            $ids = array_map('intval', $_POST['content_ids'] ?? []);
            $nextOrder = (int) $db->getVar('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM kronos_menu_items WHERE menu_id = ?', [$selectedMenuId]);
            foreach ($ids as $id) {
                $post = $db->getRow("SELECT id, title, slug, post_type FROM kronos_posts WHERE id = ? AND post_type IN ('post','page')", [$id]);
                if (!$post) {
                    continue;
                }
                $db->insert('kronos_menu_items', [
                    'menu_id' => $selectedMenuId,
                    'parent_id' => null,
                    'title' => (string) $post['title'],
                    'url' => ($post['post_type'] === 'page' ? '/page/' : '/post/') . (string) $post['slug'],
                    'item_type' => 'post',
                    'object_type' => (string) $post['post_type'],
                    'object_id' => (int) $post['id'],
                    'target' => '_self',
                    'sort_order' => $nextOrder,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $nextOrder += 10;
            }
            $notice = 'Selected content added to the menu.';
        }

        if ($action === 'add_custom') {
            $title = trim((string) ($_POST['custom_title'] ?? ''));
            $url = $cleanMenuUrl((string) ($_POST['custom_url'] ?? ''));
            if ($title === '') {
                $errors[] = 'Custom link text is required.';
            } else {
                $nextOrder = (int) $db->getVar('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM kronos_menu_items WHERE menu_id = ?', [$selectedMenuId]);
                $db->insert('kronos_menu_items', [
                    'menu_id' => $selectedMenuId,
                    'parent_id' => null,
                    'title' => $title,
                    'url' => $url,
                    'item_type' => 'custom',
                    'object_type' => '',
                    'object_id' => null,
                    'target' => ($_POST['custom_target'] ?? '_self') === '_blank' ? '_blank' : '_self',
                    'sort_order' => $nextOrder,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $notice = 'Custom link added to the menu.';
            }
        }

        if ($action === 'save_items') {
            $deleteIds = array_map('intval', $_POST['delete'] ?? []);
            foreach ($deleteIds as $deleteId) {
                $db->delete('kronos_menu_items', ['id' => $deleteId, 'menu_id' => $selectedMenuId]);
            }

            foreach (array_map('intval', $_POST['item_id'] ?? []) as $itemId) {
                if (in_array($itemId, $deleteIds, true)) {
                    continue;
                }
                $title = trim((string) ($_POST['item_title'][$itemId] ?? ''));
                if ($title === '') {
                    $title = 'Menu item';
                }
                $parentId = (int) ($_POST['item_parent'][$itemId] ?? 0);
                if ($parentId === $itemId) {
                    $parentId = 0;
                }
                $db->update('kronos_menu_items', [
                    'parent_id' => $parentId > 0 ? $parentId : null,
                    'title' => $title,
                    'url' => $cleanMenuUrl((string) ($_POST['item_url'][$itemId] ?? '#')),
                    'target' => ($_POST['item_target'][$itemId] ?? '_self') === '_blank' ? '_blank' : '_self',
                    'sort_order' => (int) ($_POST['item_order'][$itemId] ?? 0),
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['id' => $itemId, 'menu_id' => $selectedMenuId]);
            }
            $notice = 'Menu items saved.';
        }
    }
}

$menus = $db->getResults('SELECT * FROM kronos_menus ORDER BY name ASC');
if (!$menus) {
    $primaryId = (int) $db->insert('kronos_menus', [
        'name' => 'Primary Menu',
        'slug' => 'primary-menu',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    kronos_set_option('menu_header_id', $primaryId);
    $menus = $db->getResults('SELECT * FROM kronos_menus ORDER BY name ASC');
}

$selectedMenuId = (int) ($_GET['menu_id'] ?? $_POST['menu_id'] ?? ($menus[0]['id'] ?? 0));
$selectedMenu = $selectedMenuId > 0 ? $db->getRow('SELECT * FROM kronos_menus WHERE id = ?', [$selectedMenuId]) : null;
$items = $selectedMenu ? $db->getResults('SELECT * FROM kronos_menu_items WHERE menu_id = ? ORDER BY sort_order ASC, id ASC', [$selectedMenuId]) : [];
$contentItems = $db->getResults(
    "SELECT id, title, slug, post_type, status FROM kronos_posts WHERE post_type IN ('post','page') ORDER BY post_type ASC, title ASC LIMIT 200"
);
$headerMenuId = (int) kronos_option('menu_header_id', 0);
$footerMenuId = (int) kronos_option('menu_footer_id', 0);

if (isset($_GET['created'])) {
    $notice = 'Menu created.';
}

require $dashDir . '/partials/layout-header.php';
?>

<?php if ($notice): ?>
<div class="alert alert-success"><?= kronos_e($notice) ?></div>
<?php endif; ?>
<?php if ($errors): ?>
<div class="alert alert-error"><?= kronos_e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<div class="menu-editor-layout">
  <aside class="menu-editor-side">
    <div class="card">
      <div class="card-header"><span class="card-title">Select Menu</span></div>
      <div class="card-body">
        <form method="get" action="<?= kronos_url('/dashboard/menus') ?>" class="menu-picker-form">
          <select name="menu_id">
            <?php foreach ($menus as $menu): ?>
              <option value="<?= (int) $menu['id'] ?>" <?= (int) $menu['id'] === $selectedMenuId ? 'selected' : '' ?>><?= kronos_e($menu['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-secondary" type="submit">Select</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Create Menu</span></div>
      <div class="card-body">
        <form method="post" action="<?= kronos_url('/dashboard/menus') ?>">
          <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
          <input type="hidden" name="action" value="create_menu">
          <div class="form-group">
            <label>Menu name</label>
            <input type="text" name="menu_name" placeholder="Footer Links">
          </div>
          <button type="submit" class="btn btn-primary btn-block">Create Menu</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Display Locations</span></div>
      <div class="card-body">
        <form method="post" action="<?= kronos_url('/dashboard/menus?menu_id=' . $selectedMenuId) ?>">
          <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
          <input type="hidden" name="action" value="save_locations">
          <input type="hidden" name="menu_id" value="<?= $selectedMenuId ?>">
          <div class="form-group">
            <label>Header menu</label>
            <select name="header_menu_id">
              <option value="0">Fallback links</option>
              <?php foreach ($menus as $menu): ?>
                <option value="<?= (int) $menu['id'] ?>" <?= (int) $menu['id'] === $headerMenuId ? 'selected' : '' ?>><?= kronos_e($menu['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Footer menu</label>
            <select name="footer_menu_id">
              <option value="0">Fallback links</option>
              <?php foreach ($menus as $menu): ?>
                <option value="<?= (int) $menu['id'] ?>" <?= (int) $menu['id'] === $footerMenuId ? 'selected' : '' ?>><?= kronos_e($menu['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-secondary btn-block">Save Locations</button>
        </form>
      </div>
    </div>
  </aside>

  <section class="menu-editor-main">
    <div class="page-header">
      <div>
        <h1><?= kronos_e($selectedMenu['name'] ?? 'Menu') ?></h1>
        <p class="text-muted">Build the links that appear in the site header and footer.</p>
      </div>
      <a href="<?= kronos_url('/') ?>" target="_blank" class="btn btn-ghost">View Site</a>
    </div>

    <div class="menu-builder-grid">
      <div class="card">
        <div class="card-header"><span class="card-title">Add Posts or Pages</span></div>
        <div class="card-body">
          <form method="post" action="<?= kronos_url('/dashboard/menus?menu_id=' . $selectedMenuId) ?>">
            <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
            <input type="hidden" name="action" value="add_content">
            <input type="hidden" name="menu_id" value="<?= $selectedMenuId ?>">
            <div class="menu-content-list">
              <?php foreach ($contentItems as $item): ?>
                <label class="menu-content-choice">
                  <input type="checkbox" name="content_ids[]" value="<?= (int) $item['id'] ?>">
                  <span>
                    <strong><?= kronos_e($item['title']) ?></strong>
                    <small><?= kronos_e(ucfirst($item['post_type'])) ?> / <?= kronos_e($item['slug']) ?> / <?= kronos_e($item['status']) ?></small>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-secondary">Add to Menu</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><span class="card-title">Add Custom Link</span></div>
        <div class="card-body">
          <form method="post" action="<?= kronos_url('/dashboard/menus?menu_id=' . $selectedMenuId) ?>">
            <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
            <input type="hidden" name="action" value="add_custom">
            <input type="hidden" name="menu_id" value="<?= $selectedMenuId ?>">
            <div class="form-group">
              <label>Link text</label>
              <input type="text" name="custom_title" placeholder="Contact">
            </div>
            <div class="form-group">
              <label>URL</label>
              <input type="text" name="custom_url" placeholder="/contact">
            </div>
            <label class="checkbox-line">
              <input type="checkbox" name="custom_target" value="_blank"> Open in a new tab
            </label>
            <button type="submit" class="btn btn-secondary">Add Custom Link</button>
          </form>
        </div>
      </div>
    </div>

    <form method="post" action="<?= kronos_url('/dashboard/menus?menu_id=' . $selectedMenuId) ?>">
      <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
      <input type="hidden" name="action" value="save_items">
      <input type="hidden" name="menu_id" value="<?= $selectedMenuId ?>">

      <div class="card menu-structure-card">
        <div class="card-header">
          <span class="card-title">Menu Structure</span>
          <button type="submit" class="btn btn-primary btn-sm">Save Menu</button>
        </div>
        <div class="card-body">
          <?php if (!$items): ?>
            <div class="menu-empty-state">Add posts, pages, or custom links to start building this menu.</div>
          <?php else: ?>
            <div class="menu-items-list">
              <?php foreach ($items as $item): ?>
                <div class="menu-item-row" draggable="true">
                  <input type="hidden" name="item_id[]" value="<?= (int) $item['id'] ?>">
                  <div class="menu-item-handle">=</div>
                  <div class="menu-item-fields">
                    <div class="form-row">
                      <div class="form-group">
                        <label>Navigation label</label>
                        <input type="text" name="item_title[<?= (int) $item['id'] ?>]" value="<?= kronos_e($item['title']) ?>">
                      </div>
                      <div class="form-group">
                        <label>URL</label>
                        <input type="text" name="item_url[<?= (int) $item['id'] ?>]" value="<?= kronos_e($item['url']) ?>">
                      </div>
                    </div>
                    <div class="menu-item-meta">
                      <span class="badge"><?= kronos_e($item['object_type'] ?: $item['item_type']) ?></span>
                      <label>Order <input type="number" name="item_order[<?= (int) $item['id'] ?>]" value="<?= (int) $item['sort_order'] ?>"></label>
                      <label>Parent
                        <select name="item_parent[<?= (int) $item['id'] ?>]">
                          <option value="0">Top level</option>
                          <?php foreach ($items as $parentChoice): ?>
                            <?php if ((int) $parentChoice['id'] === (int) $item['id']) continue; ?>
                            <option value="<?= (int) $parentChoice['id'] ?>" <?= (int) ($item['parent_id'] ?? 0) === (int) $parentChoice['id'] ? 'selected' : '' ?>>
                              <?= kronos_e($parentChoice['title']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                      <button type="button" class="mini-order-btn" data-menu-move="up">Move up</button>
                      <button type="button" class="mini-order-btn" data-menu-move="down">Move down</button>
                      <label><input type="checkbox" name="item_target[<?= (int) $item['id'] ?>]" value="_blank" <?= $item['target'] === '_blank' ? 'checked' : '' ?>> New tab</label>
                      <label class="menu-delete"><input type="checkbox" name="delete[]" value="<?= (int) $item['id'] ?>"> Remove</label>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </section>
</div>

<script>
(function(){
  var list = document.querySelector('.menu-items-list');
  if (!list) return;
  var dragged = null;

  function renumber() {
    list.querySelectorAll('.menu-item-row').forEach(function(row, index){
      var input = row.querySelector('input[type="number"][name^="item_order"]');
      if (input) input.value = String((index + 1) * 10);
    });
  }

  list.addEventListener('click', function(event){
    var btn = event.target.closest('[data-menu-move]');
    if (!btn) return;
    var row = btn.closest('.menu-item-row');
    if (!row) return;
    if (btn.dataset.menuMove === 'up' && row.previousElementSibling) {
      list.insertBefore(row, row.previousElementSibling);
    }
    if (btn.dataset.menuMove === 'down' && row.nextElementSibling) {
      list.insertBefore(row.nextElementSibling, row);
    }
    renumber();
  });

  list.addEventListener('dragstart', function(event){
    dragged = event.target.closest('.menu-item-row');
    if (dragged) dragged.classList.add('dragging');
  });

  list.addEventListener('dragend', function(){
    if (dragged) dragged.classList.remove('dragging');
    dragged = null;
    renumber();
  });

  list.addEventListener('dragover', function(event){
    if (!dragged) return;
    event.preventDefault();
    var target = event.target.closest('.menu-item-row');
    if (!target || target === dragged) return;
    var rect = target.getBoundingClientRect();
    var before = event.clientY < rect.top + rect.height / 2;
    list.insertBefore(dragged, before ? target : target.nextSibling);
  });
})();
</script>

<?php require $dashDir . '/partials/layout-footer.php'; ?>
