<?php
declare(strict_types=1);

/**
 * KronosCMS Debug Inspector — accessible at GET /debug
 * Only works when APP_DEBUG=true in .env
 */

use Kronos\Core\KronosApp;

$app = KronosApp::getInstance();

if (strtolower((string)$app->env('APP_DEBUG', 'false')) !== 'true') {
    http_response_code(403);
    echo '<h1>403 — Debug mode is disabled.</h1><p>Set <code>APP_DEBUG=true</code> in your <code>.env</code> to enable this page.</p>';
    exit;
}

// ─────────────────────────────────────────────
// Run checks
// ─────────────────────────────────────────────

$checks = [];

function dbg_check(string $label, callable $fn): array
{
    try {
        $result = $fn();
        return ['label' => $label, 'status' => 'ok', 'detail' => $result];
    } catch (\Throwable $e) {
        return ['label' => $label, 'status' => 'error', 'detail' => $e->getMessage()];
    }
}

// 1. PHP version
$checks[] = dbg_check('PHP Version', function () {
    $v = PHP_VERSION;
    if (version_compare($v, '8.0.0', '<')) {
        throw new \RuntimeException("PHP {$v} — requires 8.0+");
    }
    return "PHP {$v} ✓";
});

// 2. Required extensions
foreach (['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl'] as $ext) {
    $checks[] = dbg_check("Extension: {$ext}", function () use ($ext) {
        if (!extension_loaded($ext)) {
            throw new \RuntimeException("Missing");
        }
        return 'Loaded ✓';
    });
}

// 3. .env loaded
$checks[] = dbg_check('.env loaded', function () use ($app) {
    $url = $app->env('APP_URL', '');
    if ($url === '') throw new \RuntimeException('APP_URL is empty');
    return "APP_URL = {$url}";
});

// 4. DB connection
$checks[] = dbg_check('Database connection', function () use ($app) {
    $db = $app->db();
    $v  = $db->getVar('SELECT VERSION()');
    return "MySQL {$v} ✓";
});

// 5. Core tables
$coreTables = [
    'kronos_options', 'kronos_users', 'kronos_posts',
    'kronos_themes', 'kronos_modules', 'kronos_orders',
    'kronos_products', 'kronos_builder_layouts',
];
foreach ($coreTables as $table) {
    $checks[] = dbg_check("Table: {$table}", function () use ($app, $table) {
        $count = (int) $app->db()->getVar("SELECT COUNT(*) FROM {$table}");
        return "{$count} rows ✓";
    });
}

// 6. kronos_options values
foreach (['app_url', 'app_name', 'kronos_active_mode', 'active_theme'] as $key) {
    $checks[] = dbg_check("Option: {$key}", function () use ($key) {
        $val = kronos_option($key, '__MISSING__');
        if ($val === '__MISSING__') throw new \RuntimeException('Not set in DB');
        return var_export($val, true);
    });
}

// 7. Helper functions available
foreach (['kronos_url', 'kronos_option', 'kronos_redirect', 'kronos_current_user', 'kronos_user_can', 'kronos_mode', 'kronos_asset', 'kronos_e', 'kronos_csrf_token'] as $fn) {
    $checks[] = dbg_check("Function: {$fn}()", function () use ($fn) {
        if (!function_exists($fn)) throw new \RuntimeException('Not defined');
        return 'Defined ✓';
    });
}

// 8. Modules directory
$modulesDir = $app->rootDir() . '/modules';
$checks[] = dbg_check('Modules directory', function () use ($modulesDir) {
    $dirs = glob($modulesDir . '/*', GLOB_ONLYDIR);
    $slugs = array_map('basename', $dirs ?: []);
    return implode(', ', $slugs);
});

// 9. Each module entry file + class
foreach (glob($modulesDir . '/*', GLOB_ONLYDIR) as $moduleDir) {
    $slug  = basename($moduleDir);
    $file  = $moduleDir . '/' . $slug . '.php';
    $class = str_replace(' ', '', ucwords(str_replace('-', ' ', $slug))) . 'Module';
    $checks[] = dbg_check("Module: {$slug}", function () use ($file, $class) {
        if (!file_exists($file)) throw new \RuntimeException("Entry file missing: {$file}");
        // Already required by loader — just check class exists
        if (!class_exists($class)) throw new \RuntimeException("Class {$class} not found (namespace issue?)");
        return "{$class} ✓";
    });
}

// 10. Theme
$checks[] = dbg_check('Active theme', function () use ($app) {
    $tm    = $app->themeManager();
    $slug  = $tm->getActiveSlug();
    $theme = $tm->getActiveTheme();
    if (!$theme) throw new \RuntimeException('No active theme returned');
    return "{$slug} — " . ($theme['name'] ?? '?');
});

// 11. Public assets
foreach (['css/dashboard.css', 'js/dashboard.js'] as $asset) {
    $checks[] = dbg_check("Asset: {$asset}", function () use ($app, $asset) {
        $path = $app->rootDir() . '/public/assets/' . $asset;
        if (!file_exists($path)) throw new \RuntimeException("File not found: {$path}");
        $kb = round(filesize($path) / 1024, 1);
        return "{$kb} KB ✓";
    });
}

// 12. JWT_SECRET set
$checks[] = dbg_check('JWT_SECRET configured', function () use ($app) {
    $secret = $app->env('JWT_SECRET', '');
    if (strlen($secret) < 16) throw new \RuntimeException('Too short or not set (min 16 chars)');
    return strlen($secret) . ' chars ✓';
});

// 13. Writable paths
foreach (['storage', 'public/assets'] as $rel) {
    $checks[] = dbg_check("Writable: {$rel}/", function () use ($app, $rel) {
        $path = $app->rootDir() . '/' . $rel;
        if (!is_dir($path)) throw new \RuntimeException("Directory missing");
        if (!is_writable($path)) throw new \RuntimeException("Not writable");
        return 'Writable ✓';
    });
}

// ─────────────────────────────────────────────
// Render
// ─────────────────────────────────────────────

$pass  = count(array_filter($checks, fn($c) => $c['status'] === 'ok'));
$fail  = count(array_filter($checks, fn($c) => $c['status'] === 'error'));
$total = count($checks);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>KronosCMS Debug Inspector</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#0f1117;color:#e2e8f0;padding:32px;font-size:14px;line-height:1.6}
h1{font-size:1.5rem;font-weight:700;margin-bottom:4px;color:#f8fafc}
.subtitle{color:#64748b;margin-bottom:24px;font-size:.85rem}
.summary{display:flex;gap:12px;margin-bottom:24px}
.pill{padding:6px 16px;border-radius:999px;font-weight:700;font-size:.85rem}
.pill-pass{background:#14532d;color:#4ade80}
.pill-fail{background:#450a0a;color:#f87171}
.pill-total{background:#1e293b;color:#94a3b8}
table{width:100%;border-collapse:collapse;background:#1e293b;border-radius:10px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.4)}
thead th{background:#0f172a;padding:10px 16px;text-align:left;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:600}
tbody tr{border-bottom:1px solid #0f1117}
tbody tr:last-child{border:none}
tbody tr:hover{background:#263145}
td{padding:10px 16px;vertical-align:top}
.status-ok{color:#4ade80;font-weight:700}
.status-error{color:#f87171;font-weight:700}
.label{color:#e2e8f0}
.detail{color:#94a3b8;font-family:'JetBrains Mono','Fira Code',monospace;font-size:.8rem;word-break:break-all}
.detail.err{color:#fca5a5}
.footer{margin-top:20px;color:#334155;font-size:.75rem}
</style>
</head>
<body>
<h1>⚡ KronosCMS Debug Inspector</h1>
<p class="subtitle">Generated <?= date('Y-m-d H:i:s') ?> — <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Apache') ?></p>

<div class="summary">
  <span class="pill pill-pass">✓ <?= $pass ?> passed</span>
  <?php if ($fail > 0): ?>
  <span class="pill pill-fail">✗ <?= $fail ?> failed</span>
  <?php endif; ?>
  <span class="pill pill-total"><?= $total ?> total</span>
</div>

<table>
<thead>
  <tr><th>Check</th><th>Status</th><th>Detail</th></tr>
</thead>
<tbody>
<?php foreach ($checks as $c): ?>
<tr>
  <td class="label"><?= htmlspecialchars($c['label']) ?></td>
  <td class="status-<?= $c['status'] ?>"><?= $c['status'] === 'ok' ? '✓ OK' : '✗ FAIL' ?></td>
  <td class="detail <?= $c['status'] === 'error' ? 'err' : '' ?>"><?= htmlspecialchars((string)$c['detail']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<p class="footer">Remove or disable by setting <code>APP_DEBUG=false</code> in .env before going to production.</p>
</body>
</html>
<?php
