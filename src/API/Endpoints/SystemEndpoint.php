<?php
declare(strict_types=1);

namespace Kronos\API\Endpoints;

use Kronos\API\KronosAPIRouter;
use Kronos\Core\KronosApp;
use Kronos\Core\UpdateChecker;
use Kronos\Core\SelfUpdater;

/**
 * SystemEndpoint — App update check & apply.
 * Routes: GET  /api/kronos/v1/system/update/check
 *         POST /api/kronos/v1/system/update/apply
 */
class SystemEndpoint
{
    private KronosAPIRouter $api;

    public function __construct(KronosAPIRouter $api)
    {
        $this->api = $api;
    }

    public function handle(array $params): void
    {
        $uri    = $_SERVER['REQUEST_URI'] ?? '';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (str_ends_with($uri, '/check')) {
            $this->check();
        } elseif (str_ends_with($uri, '/apply') && $method === 'POST') {
            $this->apply();
        } else {
            kronos_abort(404, 'Unknown system action.');
        }
    }

    private function check(): void
    {
        $app     = KronosApp::getInstance();
        $checker = new UpdateChecker($app);
        $result  = $checker->check();
        kronos_json($result);
    }

    private function apply(): void
    {
        $app     = KronosApp::getInstance();
        $checker = new UpdateChecker($app);
        $info    = $checker->check();

        if (!($info['update_available'] ?? false)) {
            kronos_json(['success' => false, 'message' => 'Already up to date.']);
        }

        $updater = new SelfUpdater($app);
        try {
            $updater->apply((string) $info['download_url'], (string) $info['latest_version']);
            kronos_json(['success' => true, 'version' => $info['latest_version']]);
        } catch (\Exception $e) {
            error_log('[KronosCMS SelfUpdater] ' . $e->getMessage());
            kronos_abort(500, 'Update failed: ' . $e->getMessage());
        }
    }
}
