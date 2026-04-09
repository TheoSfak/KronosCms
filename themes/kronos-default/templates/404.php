<?php
ob_start();
?>
<div class="container mt-24" style="text-align:center;padding:80px 20px;">
  <h1 style="font-size:4rem;line-height:1">404</h1>
  <p class="text-muted">The page you're looking for doesn't exist.</p>
  <a href="/" class="btn btn-primary mt-16">← Go Home</a>
</div>
<?php
$content   = ob_get_clean();
$title     = '404 Not Found — ' . kronos_option('app_name', 'KronosCMS');
$bodyClass = 'error-404';

include __DIR__ . '/base.php';
