<?php
declare(strict_types=1);
$title = (string) ($post['title'] ?? kronos_option('app_name', 'KronosCMS'));
$content = $html ?? '';
require __DIR__ . '/base.php';
