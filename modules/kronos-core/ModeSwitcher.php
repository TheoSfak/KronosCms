<?php
declare(strict_types=1);

use Kronos\Core\KronosApp;

/**
 * KronosModeSwitcher — Switches between CMS and E-Commerce mode.
 * Handles POST /dashboard/mode-switch (requires app_manager role).
 */
class KronosModeSwitcher
{
    private KronosApp $app;

    public function __construct(KronosApp $app)
    {
        $this->app = $app;
    }

    public function handle(): void
    {
        // Must be authenticated app_manager
        if (!kronos_user_can('app_manager')) {
            kronos_abort(403, 'Forbidden.');
        }

        $mode = trim($_POST['mode'] ?? '');
        if (!in_array($mode, ['cms', 'ecommerce'], true)) {
            kronos_abort(422, 'Invalid mode. Use "cms" or "ecommerce".');
        }

        $this->app->config()->set('kronos_active_mode', $mode);

        do_action('kronos/mode/switched', $mode);

        kronos_json([
            'success' => true,
            'mode'    => $mode,
            'message' => 'Mode switched to ' . $mode . '. Reloading dashboard.',
        ]);
    }
}
