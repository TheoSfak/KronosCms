<?php
$appName = kronos_option('app_name', 'KronosCMS');
ob_start();
?>

<section class="site-hero hero-compact">
  <div class="container">
    <div class="hero-content">
      <p class="hero-breadcrumb">Home &rsaquo; Services</p>
      <h1 class="hero-title">Our Services</h1>
      <p class="hero-sub">Everything you need to publish, manage, and grow — in one powerful platform.</p>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow">What We Offer</span>
      <h2 class="section-title">Built for every type of website</h2>
    </div>
    <div class="services-grid">
      <div class="service-card">
        <div class="service-icon">📝</div>
        <h3>Content Management</h3>
        <p>Create, edit, and organise posts and pages with a beautiful drag-and-drop visual builder. No coding required.</p>
        <a href="<?= kronos_url('/dashboard/content') ?>" class="service-link">Explore →</a>
      </div>
      <div class="service-card">
        <div class="service-icon">🛒</div>
        <h3>E-Commerce</h3>
        <p>Sell products online with a fully integrated shop. Product pages, cart, checkout, and payment gateways included.</p>
        <a href="<?= kronos_url('/dashboard/') ?>" class="service-link">Explore →</a>
      </div>
      <div class="service-card">
        <div class="service-icon">🎨</div>
        <h3>Themes &amp; Design</h3>
        <p>Pick a color scheme, hero style, and layout from the Design settings. Make it yours in seconds.</p>
        <a href="<?= kronos_url('/dashboard/settings') ?>?tab=design" class="service-link">Explore →</a>
      </div>
      <div class="service-card">
        <div class="service-icon">🤖</div>
        <h3>AI Assistant</h3>
        <p>Generate page copy, product descriptions, and blog posts with the built-in AI chat powered by GPT-4o.</p>
        <a href="<?= kronos_url('/dashboard/ai') ?>" class="service-link">Explore →</a>
      </div>
      <div class="service-card">
        <div class="service-icon">📊</div>
        <h3>Analytics</h3>
        <p>Track page views, understand your audience, and make data-driven decisions — right inside the dashboard.</p>
        <a href="<?= kronos_url('/dashboard/analytics') ?>" class="service-link">Explore →</a>
      </div>
      <div class="service-card">
        <div class="service-icon">🔌</div>
        <h3>Marketplace &amp; Modules</h3>
        <p>Extend your site's functionality with modules from the marketplace. Install in one click, activate instantly.</p>
        <a href="<?= kronos_url('/dashboard/marketplace') ?>" class="service-link">Explore →</a>
      </div>
    </div>
  </div>
</section>

<section class="section section-alt">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow">How It Works</span>
      <h2 class="section-title">Get started in three steps</h2>
    </div>
    <div class="steps-grid">
      <div class="step-card">
        <div class="step-number">01</div>
        <h3>Set Up Your Site</h3>
        <p>Configure your app name, mode, and design from the Settings dashboard. Takes under two minutes.</p>
      </div>
      <div class="step-card">
        <div class="step-number">02</div>
        <h3>Build Your Pages</h3>
        <p>Use the drag-and-drop builder to create stunning pages. Choose from templates or start from scratch.</p>
      </div>
      <div class="step-card">
        <div class="step-number">03</div>
        <h3>Publish &amp; Grow</h3>
        <p>Hit publish and your content goes live instantly. Track your progress with the built-in analytics.</p>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-header">
      <span class="section-eyebrow">Pricing</span>
      <h2 class="section-title">Simple, transparent plans</h2>
    </div>
    <div class="pricing-grid">
      <div class="pricing-card">
        <div class="pricing-tier">Starter</div>
        <div class="pricing-price">Free</div>
        <div class="pricing-period">forever</div>
        <ul class="pricing-features">
          <li>✓ Unlimited posts &amp; pages</li>
          <li>✓ Visual builder</li>
          <li>✓ 1 theme</li>
          <li>✓ Community support</li>
          <li class="dimmed">✗ AI Assistant</li>
          <li class="dimmed">✗ E-Commerce</li>
        </ul>
        <a href="<?= kronos_url('/dashboard/') ?>" class="btn btn-outline-dark">Get Started</a>
      </div>
      <div class="pricing-card pricing-featured">
        <div class="pricing-badge">Most Popular</div>
        <div class="pricing-tier">Pro</div>
        <div class="pricing-price">$29</div>
        <div class="pricing-period">per month</div>
        <ul class="pricing-features">
          <li>✓ Everything in Starter</li>
          <li>✓ AI-powered copywriting</li>
          <li>✓ E-Commerce + payments</li>
          <li>✓ Advanced analytics</li>
          <li>✓ Priority support</li>
          <li>✓ All themes &amp; modules</li>
        </ul>
        <a href="<?= kronos_url('/contact') ?>" class="btn btn-white">Start Free Trial</a>
      </div>
      <div class="pricing-card">
        <div class="pricing-tier">Enterprise</div>
        <div class="pricing-price">Custom</div>
        <div class="pricing-period">contact us</div>
        <ul class="pricing-features">
          <li>✓ Everything in Pro</li>
          <li>✓ Dedicated hosting</li>
          <li>✓ Custom modules</li>
          <li>✓ SLA guarantee</li>
          <li>✓ Onboarding &amp; training</li>
          <li>✓ White-label options</li>
        </ul>
        <a href="<?= kronos_url('/contact') ?>" class="btn btn-outline-dark">Contact Sales</a>
      </div>
    </div>
  </div>
</section>

<section class="cta-banner">
  <div class="container">
    <h2>Still have questions?</h2>
    <p>We're happy to walk you through every feature personally.</p>
    <a href="<?= kronos_url('/contact') ?>" class="btn btn-white btn-lg">Talk to Us</a>
  </div>
</section>

<?php
$content = ob_get_clean();
$title   = 'Services — ' . $appName;
include __DIR__ . '/base.php';
