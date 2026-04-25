<?php
// Single page / post template — renders the builder layout
/** @var array $post  Post record including layout_json */
/** @var string $html Already-rendered builder HTML */
$meta = [];
if (!empty($post['meta'])) {
    $decodedMeta = json_decode((string) $post['meta'], true);
    $meta = is_array($decodedMeta) ? $decodedMeta : [];
}
$featuredImage = trim((string) ($meta['featured_image_url'] ?? ''));
$featuredAlt = (string) ($meta['featured_image_alt'] ?? ($post['title'] ?? ''));
$classicContent = trim((string) ($post['content'] ?? ''));

ob_start();
?>
<div class="container mt-24">
  <article class="single-post">
    <header class="post-header mb-24">
      <h1><?= kronos_e($post['title'] ?? '') ?></h1>
      <p class="text-muted text-sm"><?= date('F j, Y', strtotime($post['created_at'] ?? 'now')) ?></p>
    </header>
    <?php if ($featuredImage !== ''): ?>
    <figure class="post-featured-image">
      <img src="<?= kronos_e($featuredImage) ?>" alt="<?= kronos_e($featuredAlt) ?>">
    </figure>
    <?php endif; ?>
    <div class="post-content">
      <?php if ($classicContent !== ''): ?>
      <div class="classic-content">
        <?= nl2br(kronos_e($classicContent)) ?>
      </div>
      <?php endif; ?>
      <?= $html ?? '' ?>
    </div>
  </article>
</div>
<?php
$content   = ob_get_clean();
$title     = ($post['title'] ?? '') . ' — ' . kronos_option('app_name', 'KronosCMS');
$bodyClass = 'single';

include __DIR__ . '/base.php';
