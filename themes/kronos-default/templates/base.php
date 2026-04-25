<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $title ?? kronos_option('app_name', 'KronosCMS') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
<link rel="stylesheet" href="<?= kronos_asset('css/theme.css') ?>">
<?php do_action('kronos/theme/head'); ?>
<style>
  :root {
    --font: <?= json_encode(kronos_option('body_font', 'Inter')) ?>, system-ui, -apple-system, sans-serif;
    --heading-font: <?= json_encode(kronos_option('heading_font', 'Inter')) ?>, system-ui, -apple-system, sans-serif;
    <?php if (kronos_option('brand_primary_color', '') !== ''): ?>
    --accent: <?= kronos_e((string) kronos_option('brand_primary_color', '#4f46e5')) ?>;
    --accent-rgb: <?= kronos_e(kronos_hex_to_rgb_csv((string) kronos_option('brand_primary_color', '#4f46e5'))) ?>;
    <?php endif; ?>
    <?php if (kronos_option('brand_accent_color', '') !== ''): ?>
    --accent-h: <?= kronos_e((string) kronos_option('brand_accent_color', '#4338ca')) ?>;
    <?php endif; ?>
    <?php if (kronos_option('site_background_color', '') !== ''): ?>
    --bg: <?= kronos_e((string) kronos_option('site_background_color', '#ffffff')) ?>;
    <?php endif; ?>
  }
</style>
</head>
<body class="<?= kronos_e(trim(($bodyClass ?? '') . ' header-' . kronos_option('header_layout', 'default') . ' footer-' . kronos_option('footer_layout', 'columns'))) ?>" data-scheme="<?= kronos_e(kronos_option('color_scheme', 'default')) ?>">

<header class="site-header">
  <div class="container">
    <a href="<?= kronos_url('/') ?>" class="site-logo">
      <?php if (kronos_option('site_logo_url', '') !== ''): ?>
        <img src="<?= kronos_e(kronos_option('site_logo_url', '')) ?>" alt="<?= kronos_e(kronos_option('site_logo_alt', kronos_option('app_name', 'KronosCMS'))) ?>">
      <?php else: ?>
        <?= kronos_e(kronos_option('app_name', 'KronosCMS')) ?>
      <?php endif; ?>
    </a>
    <nav class="site-nav">
      <?php do_action('kronos/theme/nav'); ?>
    </nav>
    <div class="header-actions">
      <?php if (kronos_is_ecommerce()): ?>
      <a href="<?= kronos_url('/cart') ?>" class="cart-icon" aria-label="Cart">🛒</a>
      <?php endif; ?>
      <a href="<?= kronos_url('/dashboard/') ?>" class="btn-admin">⚙ Admin</a>
    </div>
  </div>
</header>

<main class="site-main">
  <?= $content ?? '' ?>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-inner">
      <div class="footer-brand">
        <a href="<?= kronos_url('/') ?>" class="footer-logo">
          <?php if (kronos_option('site_logo_url', '') !== ''): ?>
            <img src="<?= kronos_e(kronos_option('site_logo_url', '')) ?>" alt="<?= kronos_e(kronos_option('site_logo_alt', kronos_option('app_name', 'KronosCMS'))) ?>">
          <?php else: ?>
            <?= kronos_e(kronos_option('app_name', 'KronosCMS')) ?>
          <?php endif; ?>
        </a>
        <p class="text-muted"><?= kronos_e(kronos_option('tagline', '')) ?></p>
      </div>
      <div class="footer-links">
        <nav>
          <?php do_action('kronos/theme/footer-nav'); ?>
        </nav>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> <?= kronos_e(kronos_option('app_name', 'KronosCMS')) ?>.
         Powered by <a href="https://github.com/TheoSfak/KronosCms">KronosCMS</a>.</p>
    </div>
  </div>
</footer>

<?php do_action('kronos/theme/footer'); ?>
<script src="<?= kronos_asset('js/theme.js') ?>"></script>
</body>
</html>
