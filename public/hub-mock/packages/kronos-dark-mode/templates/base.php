<?php
declare(strict_types=1);
$title = $title ?? kronos_option('app_name', 'KronosCMS');
$content = $content ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= kronos_e($title) ?></title>
<link rel="stylesheet" href="<?= kronos_asset('css/theme.css') ?>">
</head>
<body>
<main class="dark-theme-page"><?= $content ?></main>
</body>
</html>
