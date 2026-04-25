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
        'tagline',
        'openai_model',
        'kronos_active_mode',
        // Homepage content
        'color_scheme',
        'hero_style',
        'homepage_about_title',
        'homepage_about_text',
        'homepage_stat1_num',  'homepage_stat1_label',
        'homepage_stat2_num',  'homepage_stat2_label',
        'homepage_stat3_num',  'homepage_stat3_label',
        'homepage_stat4_num',  'homepage_stat4_label',
        'homepage_cta_title',
        'homepage_cta_sub',
        // Permalinks and theme customizer
        'permalink_page_base',
        'permalink_post_base',
        'site_logo_url',
        'site_logo_alt',
        'header_layout',
        'footer_layout',
        'body_font',
        'heading_font',
        'brand_primary_color',
        'brand_accent_color',
        'site_background_color',
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
            if (in_array($key, ['brand_primary_color', 'brand_accent_color', 'site_background_color'], true)) {
                $value = trim((string) $value);
                if ($value !== '' && !preg_match('/^#[a-f0-9]{6}$/i', $value)) {
                    kronos_abort(422, $key . ' must be a hex color like #4f46e5.');
                }
            }

            $cfg->set($key, (string) $value);
            $saved[] = $key;
        }

        kronos_json(['success' => true, 'saved' => $saved]);
    }
}
