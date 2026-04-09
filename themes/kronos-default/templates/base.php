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
    <?php if (kronos_is_ecommerce()): ?>
    <a href="/cart" class="cart-icon" aria-label="Cart">🛒</a>
    <?php endif; ?>
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
