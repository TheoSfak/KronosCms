<?php
declare(strict_types=1);

namespace Kronos\API\Endpoints;

use Kronos\API\KronosAPIRouter;
use Kronos\Core\KronosApp;
use Kronos\Marketplace\HubClient;
use Kronos\Marketplace\PackageInstaller;

/**
 * MarketplaceEndpoint — Browse & install packages from the Hub.
 * Routes: GET  /api/kronos/v1/marketplace/directory
 *         POST /api/kronos/v1/marketplace/install
 */
class MarketplaceEndpoint
{
    private KronosAPIRouter $api;

    public function __construct(KronosAPIRouter $api)
    {
        $this->api = $api;
    }

    public function handle(array $params): void
    {
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (str_ends_with($uri, '/directory') && $method === 'GET') {
            $this->directory();
        } elseif (str_ends_with($uri, '/install') && $method === 'POST') {
            $this->install();
        } else {
            kronos_abort(404, 'Unknown marketplace action.');
        }
    }

    private function directory(): void
    {
        $app    = KronosApp::getInstance();
        $client = new HubClient($app->config());
        $data   = isset($_GET['refresh'])
            ? $client->refreshDirectory()
            : $client->fetchDirectory();
        $data = array_map(fn(array $package): array => $this->withInstallStatus($package), $data);
        kronos_json(['data' => $data]);
    }

    /**
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    private function withInstallStatus(array $package): array
    {
        $app = KronosApp::getInstance();
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($package['slug'] ?? '')));
        $type = (string) ($package['type'] ?? 'module');
        $type = $type === 'theme' ? 'theme' : 'module';

        if ($slug === '') {
            return $package + [
                'installed' => false,
                'active' => false,
                'install_status' => 'invalid',
            ];
        }

        if ($type === 'theme') {
            $dir = $app->rootDir() . '/themes/' . $slug;
            $installed = is_dir($dir);
            $active = $installed && $app->themeManager()->getActiveSlug() === $slug;
        } else {
            $dir = $app->rootDir() . '/modules/' . $slug;
            $installed = is_dir($dir);
            $active = isset($app->moduleLoader()->getLoaded()[$slug]);
        }

        $entry = $type === 'theme'
            ? $dir . '/theme.json'
            : $dir . '/' . $slug . '.php';

        return array_merge($package, [
            'installed' => $installed,
            'active' => $active,
            'install_status' => $active ? 'active' : ($installed ? 'installed' : 'available'),
            'entry_file_ok' => $installed ? is_file($entry) : null,
        ]);
    }

    private function install(): void
    {
        $raw = file_get_contents('php://input') ?: '{}';
        try {
            $body = json_decode($raw, true, 5, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            kronos_abort(400, 'Invalid JSON.');
        }

        $slug        = trim((string) ($body['slug'] ?? ''));
        $downloadUrl = trim((string) ($body['download_url'] ?? ''));
        $packageType = trim((string) ($body['type'] ?? 'module'));

        if ($slug === '' || $downloadUrl === '') {
            kronos_abort(422, 'slug and download_url are required.');
        }

        // Pre-download filter hook — allows license plugins to intercept
        $approved = apply_filters('kronos/marketplace/pre_download_check', true, [
            'slug'             => $slug,
            'download_url'     => $downloadUrl,
            'type'             => $packageType,
            'requires_license' => $body['requires_license'] ?? false,
            'license_tier'     => $body['license_tier'] ?? 'free',
        ]);

        if ($approved !== true) {
            kronos_abort(403, 'Package download not permitted: ' . (is_string($approved) ? $approved : 'check failed'));
        }

        $app       = KronosApp::getInstance();
        $installer = new PackageInstaller($app->rootDir());

        try {
            $result = $installer->install($slug, $downloadUrl, $packageType);
            if (!($result['success'] ?? false)) {
                kronos_abort(500, $result['message'] ?? 'Install failed.');
            }
            kronos_json(['success' => true, 'message' => $result['message'] ?? "Package '{$slug}' installed successfully."]);
        } catch (\Exception $e) {
            error_log('[KronosCMS Marketplace] Install error: ' . $e->getMessage());
            kronos_abort(500, 'Install failed: ' . $e->getMessage());
        }
    }
}
