<?php
declare(strict_types=1);

namespace Kronos;

use Kronos\Core\KronosModule;
use Kronos\Core\KronosApp;
use Kronos\Marketplace\HubClient;
use Kronos\Marketplace\PackageInstaller;

/**
 * KronosMarketplaceModule — integrates the Hub package marketplace.
 *
 * This module is optional. If activated it registers the HubClient and
 * PackageInstaller with the app, and fires the `kronos/marketplace/init`
 * action so other modules can extend marketplace behaviour.
 */
class KronosMarketplaceModule extends KronosModule
{
    public function getName(): string
    {
        return 'kronos-marketplace';
    }

    public function boot(): void
    {
        $app = KronosApp::getInstance();

        // Make the HubClient available app-wide via a filter
        add_filter('kronos/marketplace/hub_client', function () use ($app) {
            return new HubClient($app->config());
        });

        // Make the PackageInstaller available app-wide via a filter
        add_filter('kronos/marketplace/package_installer', function () {
            return new PackageInstaller();
        });

        // Validate download URLs against the configured hub host before install
        add_filter('kronos/marketplace/pre_download_check', function (bool $allowed, array $package) {
            // Block packages flagged as requiring an unset license key
            if (!empty($package['requires_license'])) {
                $licenseKey = (string) kronos_option('hub_license_key', '');
                if ($licenseKey === '') {
                    return false;
                }
            }
            return $allowed;
        }, 10, 2);

        do_action('kronos/marketplace/init');
    }

    public function install(): void
    {
        // No extra tables needed; packages land in /modules/ or /themes/.
    }
}
