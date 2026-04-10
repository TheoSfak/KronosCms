<?php
$appName = kronos_option('app_name', 'KronosCMS');
ob_start();
?>

<section class="site-hero hero-compact">
  <div class="container">
    <div class="hero-content">
      <p class="hero-breadcrumb">Home &rsaquo; About</p>
      <h1 class="hero-title">About Us</h1>
      <p class="hero-sub">We believe great software should be simple. Here's the story behind <?= kronos_e($appName) ?>.</p>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="about-split">
      <div class="about-text">
        <span class="section-eyebrow">Our Story</span>
        <h2 class="section-title-sm">We build tools people love to use</h2>
        <p>Founded with a single goal — make content management fast, beautiful, and effortless — <?= kronos_e($appName) ?> has grown into a full-featured platform trusted by creators, businesses, and developers worldwide.</p>
        <p>We obsess over user experience. Every feature is built with real users in mind, every UI decision is made to reduce friction, and every release brings something that genuinely improves the way you work.</p>
        <a href="<?= kronos_url('/contact') ?>" class="btn btn-primary">Get in Touch</a>
      </div>
      <div class="about-visual">
        <div class="about-browser-mockup">
          <div class="about-browser-bar">
            <span class="mb-dot red"></span>
            <span class="mb-dot yellow"></span>
            <span class="mb-dot green"></span>
            <span class="about-browser-url"><?= kronos_e($appName) ?> &mdash; Dashboard</span>
          </div>
          <div class="about-browser-body">
            <div class="about-page-hero">
              <div class="about-page-hero-title"></div>
              <div class="about-page-hero-sub"></div>
              <div class="about-page-hero-btn"></div>
            </div>
            <div class="about-page-body">
              <div class="about-page-card">
                <div class="about-page-card-dot" style="background:rgba(99,102,241,.5)"></div>
                <div class="about-page-card-line"></div>
                <div class="about-page-card-line short"></div>
              </div>
              <div class="about-page-card">
                <div class="about-page-card-dot" style="background:rgba(239,68,68,.5)"></div>
                <div class="about-page-card-line"></div>
                <div class="about-page-card-line short"></div>
              </div>
              <div class="about-page-card">
                <div class="about-page-card-dot" style="background:rgba(245,158,11,.5)"></div>
                <div class="about-page-card-line"></div>
                <div class="about-page-card-line short"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section section-alt">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow">Our Values</span>
      <h2 class="section-title">What drives us every day</h2>
    </div>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon-wrap rose">🎯</div>
        <h3>Purpose-Built</h3>
        <p>Every feature exists because users asked for it. We don't build for the sake of complexity.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon-wrap violet">🔒</div>
        <h3>Security First</h3>
        <p>CSRF protection, parameterised queries, role-based access — security is baked in from day one, not added later.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon-wrap amber">⚡</div>
        <h3>Performance</h3>
        <p>No bloat. No unnecessary requests. Pages load fast because every millisecond matters for your visitors.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon-wrap emerald">🌍</div>
        <h3>Open &amp; Extensible</h3>
        <p>Themes, modules, hooks, filters — it's your platform. Extend it any way you need.</p>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow">The Team</span>
      <h2 class="section-title">People behind the product</h2>
    </div>
    <div class="team-grid">
      <div class="team-card">
        <div class="team-avatar">TH</div>
        <h3 class="team-name">Theo S.</h3>
        <p class="team-role">Founder &amp; Lead Developer</p>
        <p class="team-bio text-muted">Built KronosCMS from scratch. Passionate about developer experience and beautiful UIs.</p>
      </div>
      <div class="team-card">
        <div class="team-avatar">DE</div>
        <h3 class="team-name">Design Lead</h3>
        <p class="team-role">UI &amp; UX Designer</p>
        <p class="team-bio text-muted">Responsible for the clean, modern aesthetic you see across the entire platform.</p>
      </div>
      <div class="team-card">
        <div class="team-avatar">BE</div>
        <h3 class="team-name">Backend Engineer</h3>
        <p class="team-role">Core &amp; API</p>
        <p class="team-bio text-muted">Keeps the engine running smoothly. Loves PHP, databases, and clean architecture.</p>
      </div>
    </div>
  </div>
</section>

<section class="stats-strip">
  <div class="container">
    <div class="stats-grid">
      <div class="stat-item">
        <span class="stat-number">10+</span>
        <span class="stat-label">Modules</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">∞</span>
        <span class="stat-label">Possibilities</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">1</span>
        <span class="stat-label">Platform</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">100%</span>
        <span class="stat-label">Open Source</span>
      </div>
    </div>
  </div>
</section>

<section class="cta-banner">
  <div class="container">
    <h2>Ready to get started?</h2>
    <p>Jump into the dashboard and build your first page today.</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="<?= kronos_url('/dashboard/') ?>" class="btn btn-white btn-lg">Open Dashboard</a>
      <a href="<?= kronos_url('/contact') ?>" class="btn btn-outline btn-lg">Contact Us</a>
    </div>
  </div>
</section>

<?php
$content   = ob_get_clean();
$title     = 'About — ' . $appName;
include __DIR__ . '/base.php';
