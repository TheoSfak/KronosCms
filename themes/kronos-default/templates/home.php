<?php
// Home page template — shows recent posts
$app   = \Kronos\Core\KronosApp::getInstance();
$posts = $app->db()->getResults(
    "SELECT * FROM kronos_posts WHERE status = 'published' AND post_type = 'post' ORDER BY created_at DESC LIMIT 10",
    []
) ?? [];

$appName = kronos_option('app_name', 'KronosCMS');

ob_start();
?>
<div class="container mt-24">
  <h1 class="page-heading"><?= kronos_e($appName) ?></h1>
  <p class="text-muted mb-24"><?= kronos_e(kronos_option('tagline', 'Welcome to our site.')) ?></p>

  <?php if (empty($posts)): ?>
  <p class="text-muted">No posts yet.</p>
  <?php else: ?>
  <div class="post-grid">
    <?php foreach ($posts as $post): ?>
    <article class="post-card">
      <h2 class="post-title"><a href="<?= kronos_url('/page/' . $post['slug']) ?>"><?= kronos_e($post['title']) ?></a></h2>
      <p class="post-meta text-muted text-sm"><?= date('F j, Y', strtotime($post['created_at'])) ?></p>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$title   = $appName;

include __DIR__ . '/base.php';
