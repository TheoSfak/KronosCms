<?php
// Home page template — turn-key ready with hero, about, services, posts/products, testimonials, CTA, contact strip
$app        = \Kronos\Core\KronosApp::getInstance();
$isEcom     = kronos_is_ecommerce();
$appName    = kronos_option('app_name', 'KronosCMS');
$tagline    = kronos_option('tagline', 'Build beautiful websites without limits.');
$heroStyle  = kronos_option('hero_style', 'full');

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

<!-- ── Hero ───────────────────────────────────────────── -->
<section class="site-hero hero-<?= kronos_e($heroStyle) ?>">
  <div class="container">
    <div class="hero-content">
      <h1 class="hero-title"><?= kronos_e($appName) ?></h1>
      <p class="hero-sub"><?= kronos_e($tagline) ?></p>
      <div class="hero-actions">
        <?php if ($isEcom): ?>
        <a href="<?= kronos_url('/services') ?>" class="btn btn-primary btn-lg">🛍️ Our Services</a>
        <a href="<?= kronos_url('/contact') ?>" class="btn btn-outline btn-lg">✉️ Contact Us</a>
        <?php else: ?>
        <a href="#latest-posts" class="btn btn-primary btn-lg">📖 Read the Blog</a>
        <a href="<?= kronos_url('/about') ?>" class="btn btn-outline btn-lg">👋 About Us</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- ── Stats strip ─────────────────────────────────── -->
<div class="stats-strip">
  <div class="container">
    <div class="stats-grid">
      <div class="stat-item"><span class="stat-number">10+</span><span class="stat-label">Modules</span></div>
      <div class="stat-item"><span class="stat-number">5</span><span class="stat-label">Color Schemes</span></div>
      <div class="stat-item"><span class="stat-number">∞</span><span class="stat-label">Possibilities</span></div>
      <div class="stat-item"><span class="stat-number">100%</span><span class="stat-label">Open Source</span></div>
    </div>
  </div>
</div>

<!-- ── About teaser ────────────────────────────────── -->
<section class="section">
  <div class="container">
    <div class="about-split">
      <div class="about-visual">
        <div class="about-image-placeholder">
          <span><?= $isEcom ? '🛒' : '🏗️' ?></span>
          <p><?= $isEcom ? 'Your store, your rules' : 'Build without limits' ?></p>
        </div>
      </div>
      <div class="about-text">
        <span class="section-eyebrow">Who We Are</span>
        <h2 class="section-title-sm"><?= $isEcom ? 'A store built around your customers' : 'A platform built around you' ?></h2>
        <p><?= $isEcom
            ? 'We built ' . kronos_e($appName) . ' because managing an online store should be effortless. Product pages, checkout flows, payment gateways — it all just works.'
            : kronos_e($appName) . ' gives you the tools to create beautiful content without touching code. A visual builder, AI assistant, analytics, and themes — all in one dashboard.'
        ?></p>
        <p>Everything is customisable. Your domain, your design, your content, your rules.</p>
        <a href="<?= kronos_url('/about') ?>" class="btn btn-primary">Learn More About Us</a>
      </div>
    </div>
  </div>
</section>

<!-- ── Services / Features ─────────────────────────── -->
<section class="section section-alt">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow"><?= $isEcom ? 'What We Sell' : 'What We Do' ?></span>
      <h2 class="section-title"><?= $isEcom ? 'Our Services' : 'Everything you need' ?></h2>
    </div>
    <?php if ($isEcom): ?>
    <div class="services-grid">
      <div class="service-card">
        <div class="service-icon">📦</div>
        <h3>Products</h3>
        <p>Browse our full catalogue of carefully curated products, ready to ship to your door.</p>
        <a href="<?= kronos_url('/services') ?>" class="service-link">Shop Now →</a>
      </div>
      <div class="service-card">
        <div class="service-icon">🚚</div>
        <h3>Fast Delivery</h3>
        <p>We partner with reliable couriers to make sure your order arrives safely and on time.</p>
        <a href="<?= kronos_url('/services') ?>" class="service-link">Learn More →</a>
      </div>
      <div class="service-card">
        <div class="service-icon">🔄</div>
        <h3>Easy Returns</h3>
        <p>Not happy? No problem. Our hassle-free return policy has you covered within 30 days.</p>
        <a href="<?= kronos_url('/contact') ?>" class="service-link">Contact Us →</a>
      </div>
    </div>
    <?php else: ?>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">📝</div>
        <h3>Visual Builder</h3>
        <p>Drag, drop, and publish. Create stunning pages without writing a single line of code.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🤖</div>
        <h3>AI Writing</h3>
        <p>Generate copy, headlines, and entire posts with GPT-4o built directly into the dashboard.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📊</div>
        <h3>Analytics</h3>
        <p>Real-time page views and audience insights baked in — no third-party trackers needed.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🎨</div>
        <h3>Design Control</h3>
        <p>Five colour schemes, multiple hero styles, and full CSS control — make it yours.</p>
      </div>
    </div>
    <?php endif; ?>
    <div style="text-align:center;margin-top:40px">
      <a href="<?= kronos_url('/services') ?>" class="btn btn-primary">View All Services</a>
    </div>
  </div>
</section>

<!-- ── Latest posts / Featured products ───────────── -->
<section class="section" id="latest-posts">
  <div class="container">
    <h2 class="section-title"><?= $isEcom ? 'Featured Products' : 'Latest Posts' ?></h2>
    <?php if (empty($items)): ?>
    <p class="text-muted"><?= $isEcom
        ? 'No products yet. <a href="' . kronos_url('/dashboard/content') . '">Add your first product →</a>'
        : 'No posts yet. <a href="' . kronos_url('/dashboard/content') . '">Write your first post →</a>'
    ?></p>
    <?php elseif ($isEcom): ?>
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

<!-- ── Testimonials ────────────────────────────────── -->
<section class="section section-alt">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow">Testimonials</span>
      <h2 class="section-title">What people are saying</h2>
    </div>
    <div class="testimonials-grid">
      <div class="testimonial-card">
        <div class="testimonial-stars">★★★★★</div>
        <p class="testimonial-text">"KronosCMS is the cleanest CMS I've ever used. Went from zero to published in under an hour."</p>
        <div class="testimonial-author">
          <div class="testimonial-avatar">AJ</div>
          <div>
            <strong>Alex J.</strong>
            <span class="text-muted text-sm">Blogger &amp; Content Creator</span>
          </div>
        </div>
      </div>
      <div class="testimonial-card">
        <div class="testimonial-stars">★★★★★</div>
        <p class="testimonial-text">"The visual builder is genuinely impressive. My entire team can now manage content without any training."</p>
        <div class="testimonial-author">
          <div class="testimonial-avatar">MC</div>
          <div>
            <strong>Maria C.</strong>
            <span class="text-muted text-sm">Marketing Manager</span>
          </div>
        </div>
      </div>
      <div class="testimonial-card">
        <div class="testimonial-stars">★★★★★</div>
        <p class="testimonial-text">"Switched from WordPress and never looked back. Lighter, faster, and the code is actually clean."</p>
        <div class="testimonial-author">
          <div class="testimonial-avatar">SR</div>
          <div>
            <strong>Sam R.</strong>
            <span class="text-muted text-sm">Full-Stack Developer</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── CTA banner ──────────────────────────────────── -->
<section class="cta-banner">
  <div class="container">
    <h2><?= $isEcom ? 'Start selling today' : 'Start building today' ?></h2>
    <p><?= $isEcom ? 'Your store is one click away. Set up, customise, and launch.' : 'Use the drag-and-drop builder to create stunning pages in minutes.' ?></p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="<?= kronos_url('/dashboard/') ?>" class="btn btn-white btn-lg">Open Dashboard</a>
      <a href="<?= kronos_url('/contact') ?>" class="btn btn-outline btn-lg">Contact Us</a>
    </div>
  </div>
</section>

<!-- ── Quick contact strip ─────────────────────────── -->
<section class="section">
  <div class="container">
    <div class="contact-strip">
      <div class="contact-strip-text">
        <h2 class="section-title-sm">Have a question?</h2>
        <p class="text-muted">We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
      </div>
      <div class="contact-strip-action">
        <a href="<?= kronos_url('/contact') ?>" class="btn btn-primary btn-lg">✉️ Get in Touch</a>
        <a href="<?= kronos_url('/about') ?>" class="btn btn-secondary btn-lg">👋 About Us</a>
      </div>
    </div>
  </div>
</section>

<?php
$content = ob_get_clean();
$title   = $appName;
include __DIR__ . '/base.php';


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
