<?php
declare(strict_types=1);

/**
 * KronosCMS Installation Wizard — Entry Point
 * Step 1: Database  |  Step 2: App Mode  |  Step 3: Admin Account
 */

define('KRONOS_ROOT', dirname(__DIR__));

// Base URL prefix for subdirectory installs (e.g. /KronosCMS when under XAMPP)
// SCRIPT_NAME = /KronosCMS/install/index.php  → base = /KronosCMS
define('KRONOS_BASE', rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/'));

// Block if already installed
if (file_exists(KRONOS_ROOT . '/config/app.php')) {
    http_response_code(403);
    echo '<h2>KronosCMS is already installed. Remove /install/ directory or /config/app.php to re-run setup.</h2>';
    exit;
}

require_once KRONOS_ROOT . '/install/InstallController.php';

$controller = new InstallController(KRONOS_ROOT);
$controller->dispatch();
