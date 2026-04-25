<?php
declare(strict_types=1);

use Kronos\Auth\KronosJWT;
use Kronos\Core\KronosApp;
use Kronos\Core\KronosModule;

class KronosSeoModule extends KronosModule
{
    public function getName(): string
    {
        return 'kronos-seo';
    }

    public function boot(): void
    {
        $this->ensureDefaults();

        add_action('kronos/theme/head', [$this, 'renderHeadTags']);
        add_action('kronos/dashboard/nav/tools', function (string $currentUri): void {
            $active = str_starts_with($currentUri, '/dashboard/seo') ? 'active' : '';
            echo '<a href="' . kronos_url('/dashboard/seo') . '" class="nav-item ' . $active . '"><span class="nav-icon">SEO</span> SEO</a>';
        });

        $router = KronosApp::getInstance()->router();
        $auth = fn(array $params, callable $next) => $this->requireDashboardAuth($params, $next);
        $router->get('/dashboard/seo', [$this, 'dashboard'], [$auth]);
        $router->post('/dashboard/seo', [$this, 'dashboard'], [$auth]);
        $router->get('/sitemap.xml', [$this, 'sitemap']);
    }

    public function renderHeadTags(): void
    {
        $app = KronosApp::getInstance();
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $description = (string) kronos_option('seo_default_description', kronos_option('tagline', ''));
        $title = (string) kronos_option('app_name', 'KronosCMS');
        $image = (string) kronos_option('seo_default_image', '');

        $basePath = rtrim(parse_url(kronos_option('app_url', '/'), PHP_URL_PATH) ?? '', '/');
        $relative = $basePath !== '' && str_starts_with($path, $basePath)
            ? substr($path, strlen($basePath))
            : $path;
        $slug = trim((string) $relative, '/');
        if ($slug === '') {
            $slug = 'home';
        }
        $slug = basename($slug);

        try {
            $post = $app->db()->getRow(
                "SELECT title, content, meta FROM kronos_posts WHERE slug = ? AND post_type IN ('page','post') AND status = 'published' LIMIT 1",
                [$slug]
            );
            if ($post) {
                $title = (string) $post['title'];
                $meta = json_decode((string) ($post['meta'] ?? ''), true) ?: [];
                $description = trim((string) ($meta['seo_description'] ?? '')) ?: $this->excerpt((string) ($post['content'] ?? '')) ?: $description;
            }
        } catch (Throwable) {
        }

        $canonical = kronos_url($relative === '/' ? '/' : '/' . trim((string) $relative, '/'));
        echo '<meta name="description" content="' . kronos_e($description) . '">' . PHP_EOL;
        echo '<link rel="canonical" href="' . kronos_e($canonical) . '">' . PHP_EOL;
        echo '<meta property="og:title" content="' . kronos_e($title) . '">' . PHP_EOL;
        echo '<meta property="og:description" content="' . kronos_e($description) . '">' . PHP_EOL;
        echo '<meta property="og:url" content="' . kronos_e($canonical) . '">' . PHP_EOL;
        echo '<meta property="og:type" content="website">' . PHP_EOL;
        if ($image !== '') {
            echo '<meta property="og:image" content="' . kronos_e($image) . '">' . PHP_EOL;
        }
    }

    public function dashboard(array $params = []): void
    {
        $notice = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            kronos_verify_csrf();
            kronos_set_option('seo_default_description', trim((string) ($_POST['seo_default_description'] ?? '')));
            kronos_set_option('seo_default_image', trim((string) ($_POST['seo_default_image'] ?? '')));
            $notice = 'SEO settings saved.';
        }

        $pageTitle = 'SEO';
        $dashDir = KronosApp::getInstance()->rootDir() . '/modules/kronos-dashboard';
        require $dashDir . '/partials/layout-header.php';
        ?>
        <?php if ($notice): ?><div class="alert alert-success"><?= kronos_e($notice) ?></div><?php endif; ?>
        <div class="card">
          <h2>SEO Settings</h2>
          <form method="post" action="<?= kronos_url('/dashboard/seo') ?>">
            <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
            <div class="form-group">
              <label>Default Meta Description</label>
              <textarea name="seo_default_description" rows="4"><?= kronos_e(kronos_option('seo_default_description', '')) ?></textarea>
            </div>
            <div class="form-group">
              <label>Default Open Graph Image URL</label>
              <input type="url" name="seo_default_image" value="<?= kronos_e(kronos_option('seo_default_image', '')) ?>">
            </div>
            <button class="btn btn-primary" type="submit">Save SEO Settings</button>
            <a class="btn btn-secondary" target="_blank" href="<?= kronos_url('/sitemap.xml') ?>">View Sitemap</a>
          </form>
        </div>
        <?php require $dashDir . '/partials/layout-footer.php';
    }

    public function sitemap(array $params = []): void
    {
        $rows = KronosApp::getInstance()->db()->getResults(
            "SELECT slug, post_type, updated_at FROM kronos_posts WHERE post_type IN ('page','post') AND status = 'published' ORDER BY updated_at DESC"
        );
        header('Content-Type: application/xml; charset=UTF-8');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($rows as $row) {
            $path = ($row['post_type'] === 'page' && $row['slug'] === 'home')
                ? '/'
                : (($row['post_type'] === 'page' ? '/page/' : '/post/') . $row['slug']);
            echo "  <url><loc>" . htmlspecialchars(kronos_url($path), ENT_XML1) . "</loc><lastmod>" . date('c', strtotime((string) $row['updated_at'])) . "</lastmod></url>\n";
        }
        echo "</urlset>";
    }

    private function ensureDefaults(): void
    {
        if (kronos_option('seo_default_description', '') === '') {
            kronos_set_option('seo_default_description', (string) kronos_option('tagline', 'A modern KronosCMS website.'));
        }
    }

    private function excerpt(string $text): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
        return substr($clean, 0, 155);
    }

    private function requireDashboardAuth(array $params, callable $next): void
    {
        if (empty($_COOKIE['kronos_token'])) {
            kronos_redirect('/dashboard/login');
        }
        try {
            $app = KronosApp::getInstance();
            $jwt = new KronosJWT((string) $app->env('JWT_SECRET', ''), (int) $app->env('JWT_EXPIRY', 86400));
            $payload = $jwt->decode((string) $_COOKIE['kronos_token']);
            $_REQUEST['_kronos_user'] = $payload['data'] ?? [];
        } catch (Throwable) {
            kronos_redirect('/dashboard/login');
        }
        $next();
    }
}
