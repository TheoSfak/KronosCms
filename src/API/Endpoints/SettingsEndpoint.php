<?php
declare(strict_types=1);

namespace Kronos\API\Endpoints;

use Kronos\API\KronosAPIRouter;
use Kronos\Core\KronosApp;

/**
 * SettingsEndpoint — Save / retrieve app settings.
 * Routes:
 *   GET  /api/kronos/v1/settings        → return current settings
 *   POST /api/kronos/v1/settings        → save one or more option keys
 */
class SettingsEndpoint
{
    private KronosAPIRouter $api;
    private KronosApp $app;

    /** Keys that the settings API is allowed to read/write. */
    private const ALLOWED_KEYS = [
        'app_name',
        'app_url',
        'openai_model',
        'kronos_active_mode',
    ];

    public function __construct(KronosAPIRouter $api)
    {
        $this->api = $api;
        $this->app = KronosApp::getInstance();
    }

    public function handle(array $params): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        match ($method) {
            'GET'  => $this->get(),
            'POST' => $this->save(),
            default => kronos_abort(405, 'Method not allowed.'),
        };
    }

    private function get(): void
    {
        $cfg  = $this->app->config();
        $data = [];
        foreach (self::ALLOWED_KEYS as $key) {
            $data[$key] = $cfg->get($key);
        }
        kronos_json(['data' => $data]);
    }

    private function save(): void
    {
        $raw = file_get_contents('php://input') ?: '{}';
        try {
            $body = json_decode($raw, true, 5, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            kronos_abort(400, 'Invalid JSON body.');
        }
        if (!is_array($body)) {
            kronos_abort(400, 'Expected JSON object.');
        }

        $cfg  = $this->app->config();
        $saved = [];

        foreach ($body as $key => $value) {
            if (!in_array($key, self::ALLOWED_KEYS, true)) {
                continue; // silently skip unknown keys
            }

            // Extra validation
            if ($key === 'kronos_active_mode' && !in_array($value, ['cms', 'ecommerce'], true)) {
                kronos_abort(422, 'Invalid mode value.');
            }
            if ($key === 'app_url') {
                $value = rtrim((string) $value, '/');
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    kronos_abort(422, 'app_url must be a valid URL.');
                }
            }

            $cfg->set($key, (string) $value);
            $saved[] = $key;
        }

        kronos_json(['success' => true, 'saved' => $saved]);
    }
}
