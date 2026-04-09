<?php
declare(strict_types=1);

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
