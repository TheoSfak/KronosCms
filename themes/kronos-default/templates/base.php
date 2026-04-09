<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $title ?? kronos_option('app_name', 'KronosCMS') ?></title>
<link rel="stylesheet" href="<?= kronos_asset('css/theme.css') ?>">
<?php do_action('kronos/theme/head'); ?>
</head>
<body class="<?= $bodyClass ?? '' ?>">

<header class="site-header">
  <div class="container">
    <a href="<?= kronos_url('/') ?>" class="site-logo"><?= kronos_e(kronos_option('app_name', 'KronosCMS')) ?></a>
    <nav class="site-nav">
      <?php do_action('kronos/theme/nav'); ?>
    </nav>
    <div style="display:flex;align-items:center;gap:1rem">
      <?php if (kronos_is_ecommerce()): ?>
      <a href="<?= kronos_url('/cart') ?>" class="cart-icon" aria-label="Cart">🛒</a>
      <?php endif; ?>
      <a href="<?= kronos_url('/dashboard/') ?>" style="font-size:.8rem;color:#6b7280;text-decoration:none;padding:.3rem .75rem;border:1px solid #e5e7eb;border-radius:6px">⚙ Admin</a>
    </div>
  </div>
</header>

<main class="site-main">
  <?= $content ?? '' ?>
</main>

<footer class="site-footer">
  <div class="container">
    <p>&copy; <?= date('Y') ?> <?= kronos_e(kronos_option('app_name', 'KronosCMS')) ?>.
       Powered by <a href="https://github.com/TheoSfak/KronosCms">KronosCMS</a>.</p>
  </div>
</footer>

<?php do_action('kronos/theme/footer'); ?>
<script src="<?= kronos_asset('js/theme.js') ?>"></script>
</body>
</html>
