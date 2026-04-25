<?php
declare(strict_types=1);

use Kronos\Auth\KronosJWT;
use Kronos\Core\KronosApp;
use Kronos\Core\KronosModule;

class KronosAnalyticsPlusModule extends KronosModule
{
    public function getName(): string
    {
        return 'kronos-analytics-plus';
    }

    public function boot(): void
    {
        add_action('kronos/dashboard/nav/tools', function (string $currentUri): void {
            $active = str_starts_with($currentUri, '/dashboard/analytics-plus') ? 'active' : '';
            echo '<a href="' . kronos_url('/dashboard/analytics-plus') . '" class="nav-item ' . $active . '"><span class="nav-icon">∿</span> Analytics Plus</a>';
        });

        $router = KronosApp::getInstance()->router();
        $auth = fn(array $params, callable $next) => $this->requireDashboardAuth($params, $next);
        $router->get('/dashboard/analytics-plus', [$this, 'dashboard'], [$auth]);
        $router->get('/dashboard/analytics-plus/export.csv', [$this, 'exportCsv'], [$auth]);
    }

    public function dashboard(array $params = []): void
    {
        $db = KronosApp::getInstance()->db();
        $events = $db->getResults("SELECT event_type, COUNT(*) AS total FROM kronos_analytics GROUP BY event_type ORDER BY total DESC LIMIT 12");
        $daily = $db->getResults("SELECT DATE(created_at) AS day, COUNT(*) AS total FROM kronos_analytics GROUP BY DATE(created_at) ORDER BY day DESC LIMIT 14");
        $topPages = $db->getResults(
            "SELECT p.title, p.slug, COUNT(a.id) AS views
             FROM kronos_analytics a
             LEFT JOIN kronos_posts p ON p.id = a.entity_id
             WHERE a.event_type = 'page_view'
             GROUP BY p.id, p.title, p.slug
             ORDER BY views DESC LIMIT 10"
        );

        $pageTitle = 'Analytics Plus';
        $dashDir = KronosApp::getInstance()->rootDir() . '/modules/kronos-dashboard';
        require $dashDir . '/partials/layout-header.php';
        ?>
        <div class="wp-list-header">
          <div><h2>Analytics Plus</h2><p class="text-muted">Charts, top content, and CSV export for tracked site events.</p></div>
          <a class="btn btn-secondary" href="<?= kronos_url('/dashboard/analytics-plus/export.csv') ?>">Export CSV</a>
        </div>
        <div class="stats-grid">
          <?php foreach ($events as $event): ?>
          <div class="stat-card"><div class="stat-value"><?= number_format((int) $event['total']) ?></div><div class="stat-label"><?= kronos_e($event['event_type']) ?></div></div>
          <?php endforeach; ?>
          <?php if (!$events): ?><div class="card" style="grid-column:1/-1"><p class="text-muted text-center">No analytics events yet.</p></div><?php endif; ?>
        </div>
        <div class="content-editor-layout mt-4">
          <div class="card editor-main">
            <h3>Daily Events</h3>
            <?php foreach (array_reverse($daily) as $row): ?>
            <?php $width = min(100, max(4, (int) $row['total'] * 12)); ?>
            <div style="display:grid;grid-template-columns:110px 1fr 48px;gap:12px;align-items:center;margin:10px 0">
              <span><?= kronos_e($row['day']) ?></span>
              <span style="height:10px;background:#dbeafe;border-radius:999px;overflow:hidden"><span style="display:block;width:<?= $width ?>%;height:100%;background:#2563eb"></span></span>
              <strong><?= (int) $row['total'] ?></strong>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="card editor-sidebar">
            <h3>Top Pages</h3>
            <?php foreach ($topPages as $page): ?>
            <div class="activity-item"><strong><?= kronos_e($page['title'] ?? $page['slug'] ?? 'Unknown') ?></strong><small><?= (int) $page['views'] ?> views</small></div>
            <?php endforeach; ?>
            <?php if (!$topPages): ?><p class="text-muted">No page views yet.</p><?php endif; ?>
          </div>
        </div>
        <?php require $dashDir . '/partials/layout-footer.php';
    }

    public function exportCsv(array $params = []): void
    {
        $rows = KronosApp::getInstance()->db()->getResults('SELECT event_type, entity_id, user_id, meta, created_at FROM kronos_analytics ORDER BY created_at DESC LIMIT 1000');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="kronos-analytics.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['event_type', 'entity_id', 'user_id', 'meta', 'created_at']);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
    }

    private function requireDashboardAuth(array $params, callable $next): void
    {
        if (empty($_COOKIE['kronos_token'])) {
            kronos_redirect('/dashboard/login');
        }
        try {
            $app = KronosApp::getInstance();
            $jwt = new KronosJWT((string) $app->env('JWT_SECRET', ''), (int) $app->env('JWT_EXPIRY', 86400));
            $jwt->decode((string) $_COOKIE['kronos_token']);
        } catch (Throwable) {
            kronos_redirect('/dashboard/login');
        }
        $next();
    }
}
