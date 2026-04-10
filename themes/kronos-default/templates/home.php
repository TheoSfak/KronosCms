<?php
// Home page template — hero, sections, and content grid
$app        = \Kronos\Core\KronosApp::getInstance();
$isEcom     = kronos_is_ecommerce();
$appName    = kronos_option('app_name', 'KronosCMS');
$tagline    = kronos_option('tagline', 'Build beautiful websites without limits.');
$heroStyle  = kronos_option('hero_style', 'full'); // full | compact | minimal

if ($isEcom) {
    $items = $app->db()->getResults(
        "SELECT * FROM kronos_posts WHERE status='published' AND post_type='product' ORDER BY created_at DESC LIMIT 8",
        []
    ) ?? [];
} else {
    $items = $app->db()->getResults(
        "SELECT * FROM kronos_posts WHERE status='published' AND post_type='post' ORDER BY created_at DESC LIMIT 6",
        []
    ) ?? [];
}

ob_start();
?>
<section class="site-hero hero-<?= kronos_e($heroStyle) ?>">
  <div class="container">
    <div class="hero-content">
      <h1 class="hero-title"><?= kronos_e($appName) ?></h1>
      <p class="hero-sub"><?= kronos_e($tagline) ?></p>
      <div class="hero-actions">
        <?php if ($isEcom): ?>
        <a href="<?= kronos_url('/shop') ?>" class="btn btn-primary btn-lg">🛍️ Shop Now</a>
        <a href="<?= kronos_url('/cart') ?>" class="btn btn-outline btn-lg">🛒 View Cart</a>
        <?php else: ?>
        <a href="#latest-posts" class="btn btn-primary btn-lg">📖 Read the Blog</a>
        <a href="<?= kronos_url('/dashboard/') ?>" class="btn btn-outline btn-lg">⚙ Dashboard</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php if ($isEcom): ?>

<section class="section">
  <div class="container">
    <h2 class="section-title">Featured Products</h2>
    <?php if (empty($items)): ?>
    <p class="text-muted">No products yet. <a href="<?= kronos_url('/dashboard/content') ?>">Add your first product →</a></p>
    <?php else: ?>
    <div class="products-grid">
      <?php foreach ($items as $item): ?>
      <article class="product-card">
        <div class="product-card-image"></div>
        <div class="product-card-body">
          <h3 class="product-card-title">
            <a href="<?= kronos_url('/product/' . kronos_e($item['slug'])) ?>"><?= kronos_e($item['title']) ?></a>
          </h3>
          <?php if (!empty($item['excerpt'])): ?>
          <p class="product-card-excerpt text-muted"><?= kronos_e($item['excerpt']) ?></p>
          <?php endif; ?>
          <a href="<?= kronos_url('/product/' . kronos_e($item['slug'])) ?>" class="btn btn-primary btn-sm">View Product</a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="cta-banner">
  <div class="container">
    <h2>Ready to start shopping?</h2>
    <p>Discover our full collection and find what you love.</p>
    <a href="<?= kronos_url('/shop') ?>" class="btn btn-white btn-lg">Browse All Products</a>
  </div>
</section>

<?php else: ?>

<section class="section section-alt">
  <div class="container">
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">📝</div>
        <h3>Rich Content</h3>
        <p>Craft beautiful posts and pages with the drag-and-drop visual builder.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🚀</div>
        <h3>Fast &amp; Modern</h3>
        <p>Built for speed, SEO-ready, and mobile-first from the ground up.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🎨</div>
        <h3>Customizable</h3>
        <p>Pick your color scheme and hero style from the Design settings tab.</p>
      </div>
    </div>
  </div>
</section>

<section class="section" id="latest-posts">
  <div class="container">
    <h2 class="section-title">Latest Posts</h2>
    <?php if (empty($items)): ?>
    <p class="text-muted">No posts yet. <a href="<?= kronos_url('/dashboard/content') ?>">Write your first post →</a></p>
    <?php else: ?>
    <div class="post-grid">
      <?php foreach ($items as $post): ?>
      <article class="post-card">
        <div class="post-card-image"></div>
        <div class="post-card-body">
          <p class="post-meta text-muted text-sm"><?= date('F j, Y', strtotime($post['created_at'])) ?></p>
          <h2 class="post-title">
            <a href="<?= kronos_url('/page/' . kronos_e($post['slug'])) ?>"><?= kronos_e($post['title']) ?></a>
          </h2>
          <?php if (!empty($post['excerpt'])): ?>
          <p class="post-excerpt text-muted"><?= kronos_e($post['excerpt']) ?></p>
          <?php endif; ?>
          <a href="<?= kronos_url('/page/' . kronos_e($post['slug'])) ?>" class="read-more">Read more →</a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="cta-banner">
  <div class="container">
    <h2>Start building today</h2>
    <p>Use the drag-and-drop builder to create stunning pages in minutes.</p>
    <a href="<?= kronos_url('/dashboard/') ?>" class="btn btn-white btn-lg">Open Dashboard</a>
  </div>
</section>

<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = $appName;

include __DIR__ . '/base.php';
