<?php
declare(strict_types=1);

// Error reporting — display only when APP_DEBUG=true, always log
error_reporting(E_ALL);
if (filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN)) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
}

/**
 * KronosCMS Front Controller
 * All requests are routed here via Apache mod_rewrite.
 * IMPORTANT: The web server's DocumentRoot must point to /public.
 */

// Define root — one level above /public
define('KRONOS_ROOT', dirname(__DIR__));
define('KRONOS_PUBLIC', __DIR__);
define('KRONOS_START', microtime(true));

// Block direct file access to sensitive directories
$uri = $_SERVER['REQUEST_URI'] ?? '/';
if (preg_match('#^/(vendor|config|storage|src|modules|install)#', $uri)) {
    http_response_code(403);
    exit('Forbidden');
}

// Autoloader
require KRONOS_ROOT . '/vendor/autoload.php';

// Bootstrap and dispatch
\Kronos\Core\KronosApp::getInstance()->bootstrap(KRONOS_ROOT);
