<?php
// Single page / post template — renders the builder layout
/** @var array $post  Post record including layout_json */
/** @var string $html Already-rendered builder HTML */

ob_start();
?>
<div class="container mt-24">
  <article class="single-post">
    <header class="post-header mb-24">
      <h1><?= kronos_e($post['title'] ?? '') ?></h1>
      <p class="text-muted text-sm"><?= date('F j, Y', strtotime($post['created_at'] ?? 'now')) ?></p>
    </header>
    <div class="post-content">
      <?= $html ?? '' ?>
    </div>
  </article>
</div>
<?php
$content   = ob_get_clean();
$title     = ($post['title'] ?? '') . ' — ' . kronos_option('app_name', 'KronosCMS');
$bodyClass = 'single';

include __DIR__ . '/base.php';
