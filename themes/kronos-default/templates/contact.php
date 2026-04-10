<?php
$appName = kronos_option('app_name', 'KronosCMS');
$successMsg = isset($_GET['sent']) && $_GET['sent'] === '1';
$errorMsg   = isset($_GET['error']) ? htmlspecialchars(urldecode($_GET['error']), ENT_QUOTES) : '';
ob_start();
?>

<section class="site-hero hero-compact">
  <div class="container">
    <div class="hero-content">
      <p class="hero-breadcrumb">Home &rsaquo; Contact</p>
      <h1 class="hero-title">Get in Touch</h1>
      <p class="hero-sub">Have a question or just want to say hello? We'd love to hear from you.</p>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="contact-layout">

      <div class="contact-form-wrap">
        <h2 class="contact-form-title">Send a message</h2>

        <?php if ($successMsg): ?>
        <div class="alert alert-success">
          ✅ Thanks! Your message has been sent. We'll get back to you shortly.
        </div>
        <?php elseif ($errorMsg): ?>
        <div class="alert alert-error">
          ❌ <?= htmlspecialchars($errorMsg, ENT_QUOTES) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= kronos_url('/contact') ?>" class="contact-form">
          <div class="form-row">
            <div class="form-group">
              <label for="cf-name">Full Name <span class="req">*</span></label>
              <input type="text" id="cf-name" name="name" placeholder="Jane Smith" required>
            </div>
            <div class="form-group">
              <label for="cf-email">Email Address <span class="req">*</span></label>
              <input type="email" id="cf-email" name="email" placeholder="jane@example.com" required>
            </div>
          </div>
          <div class="form-group">
            <label for="cf-subject">Subject</label>
            <input type="text" id="cf-subject" name="subject" placeholder="What's this about?">
          </div>
          <div class="form-group">
            <label for="cf-message">Message <span class="req">*</span></label>
            <textarea id="cf-message" name="message" rows="6" placeholder="Tell us what's on your mind…" required></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-lg">Send Message →</button>
        </form>
      </div>

      <div class="contact-info">
        <h2 class="contact-info-title">Other ways to reach us</h2>
        <div class="contact-info-items">
          <div class="contact-info-item">
            <div class="contact-info-icon">✉️</div>
            <div>
              <strong>Email</strong>
              <p class="text-muted">hello@<?= strtolower(preg_replace('/\s+/', '', $appName)) ?>.com</p>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="contact-info-icon">💬</div>
            <div>
              <strong>GitHub Discussions</strong>
              <p class="text-muted"><a href="https://github.com/TheoSfak/KronosCms/discussions" target="_blank" rel="noopener">Open a discussion</a></p>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="contact-info-icon">🐛</div>
            <div>
              <strong>Bug Reports</strong>
              <p class="text-muted"><a href="https://github.com/TheoSfak/KronosCms/issues" target="_blank" rel="noopener">Open an issue on GitHub</a></p>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="contact-info-icon">⏱️</div>
            <div>
              <strong>Response Time</strong>
              <p class="text-muted">Typically within 24–48 hours on business days.</p>
            </div>
          </div>
        </div>

        <div class="contact-social">
          <a href="https://github.com/TheoSfak/KronosCms" target="_blank" rel="noopener" class="social-btn">
            <span>⭐</span> Star on GitHub
          </a>
        </div>
      </div>

    </div>
  </div>
</section>

<section class="section section-alt">
  <div class="container">
    <div class="faq-grid">
      <div class="faq-title-col">
        <span class="section-eyebrow">FAQs</span>
        <h2 class="section-title-sm">Common questions</h2>
      </div>
      <div class="faq-items">
        <details class="faq-item">
          <summary>Is KronosCMS free to use?</summary>
          <p>Yes — the core platform is open source and free forever. Premium modules and support plans are available for those who need them.</p>
        </details>
        <details class="faq-item">
          <summary>Can I use my own hosting?</summary>
          <p>Absolutely. KronosCMS runs on any PHP 8.0+ server with MySQL/MariaDB. XAMPP, shared hosting, VPS — all work great.</p>
        </details>
        <details class="faq-item">
          <summary>How do I switch between CMS and E-Commerce mode?</summary>
          <p>Go to Settings → Mode tab and click the mode button. All your content is preserved when you switch.</p>
        </details>
        <details class="faq-item">
          <summary>Can I create custom themes?</summary>
          <p>Yes! Create a folder in <code>/themes/</code>, add a <code>theme.json</code> manifest, PHP templates, and CSS. Activate it from the dashboard.</p>
        </details>
      </div>
    </div>
  </div>
</section>

<?php
$content = ob_get_clean();
$title   = 'Contact — ' . $appName;
include __DIR__ . '/base.php';
