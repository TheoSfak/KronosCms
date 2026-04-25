<?php
declare(strict_types=1);

namespace Kronos\Marketplace;

use Kronos\Core\KronosApp;

class PluginInventoryService
{
    private KronosApp $app;

    public function __construct(KronosApp $app)
    {
        $this->app = $app;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function all(): array
    {
        $packages = (new HubClient($this->app->config()))->fetchDirectory();
        $loadedModules = $this->app->moduleLoader()->getLoaded();
        $packageBySlug = [];
        foreach ($packages as $package) {
            if (is_array($package) && !empty($package['slug'])) {
                $packageBySlug[(string) $package['slug']] = $package;
            }
        }

        $inventory = [];
        foreach (glob(rtrim($this->app->rootDir(), '/\\') . '/modules/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
            $slug = basename($moduleDir);
            $pkg = $packageBySlug[$slug] ?? [];
            $loaded = $loadedModules[$slug] ?? null;
            $inventory[] = [
                'name' => (string) ($pkg['name'] ?? ($loaded ? $loaded->getName() : $slug)),
                'slug' => $slug,
                'type' => 'module',
                'status' => $loaded ? 'Active' : 'Installed',
                'version' => (string) ($pkg['version'] ?? 'local'),
                'rollback' => 'Keep the previous /modules/' . $slug . ' folder before replacing.',
            ];
        }

        foreach (glob(rtrim($this->app->rootDir(), '/\\') . '/themes/*', GLOB_ONLYDIR) ?: [] as $themeDir) {
            $slug = basename($themeDir);
            $pkg = $packageBySlug[$slug] ?? [];
            $inventory[] = [
                'name' => (string) ($pkg['name'] ?? $slug),
                'slug' => $slug,
                'type' => 'theme',
                'status' => $this->app->themeManager()->getActiveSlug() === $slug ? 'Active' : 'Installed',
                'version' => (string) ($pkg['version'] ?? 'local'),
                'rollback' => 'Keep the previous /themes/' . $slug . ' folder before replacing.',
            ];
        }

        return $inventory;
    }
}
