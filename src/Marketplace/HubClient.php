<?php
declare(strict_types=1);

namespace Kronos\Marketplace;

/**
 * HubClient — fetches the package directory from the Hub API.
 *
 * The Hub URL is configured via HUB_API_URL in .env.
 * Falls back to a bundled local stub (hub-mock/) when the env var is absent
 * or the remote call fails, so local development works without an internet
 * connection.
 */
class HubClient
{
    private string $hubUrl;
    private string $userAgent;
    /** Seconds to cache the directory response in kronos_options. */
    private int    $cacheTtl;

    private \Kronos\Core\KronosConfig $config;

    public function __construct(\Kronos\Core\KronosConfig $config, string $hubUrl = '')
    {
        $this->config    = $config;
        $this->hubUrl    = rtrim($hubUrl ?: (string) ($_ENV['HUB_API_URL'] ?? getenv('HUB_API_URL') ?: ''), '/');
        $this->userAgent = 'KronosCMS/' . \Kronos\Core\KronosVersion::VERSION;
        $this->cacheTtl  = 3600; // 1 h
    }

    /**
     * Return the package directory as an array of package descriptors.
     *
     * Each descriptor looks like:
     * [
     *   'slug'        => 'kronos-seo',
     *   'name'        => 'Kronos SEO',
     *   'description' => '...',
     *   'version'     => '1.0.0',
     *   'type'        => 'module',   // 'module' | 'theme'
     *   'author'      => 'TheoSfak',
     *   'download_url'=> 'https://...',
     *   'license'     => 'free',     // dormant: 'premium' | 'free'
     * ]
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchDirectory(): array
    {
        $cached = $this->getCached();
        if ($cached !== null) {
            return $cached;
        }

        $packages = $this->hubUrl ? $this->fetchRemote() : null;

        if ($packages === null) {
            $packages = $this->fetchLocalMock();
        }

        if ($packages !== null) {
            $this->setCache($packages);
        }

        return $packages ?? [];
    }

    /** Force a fresh fetch, bypassing the cache. */
    public function refreshDirectory(): array
    {
        $this->config->set('kronos_hub_cache', null);
        return $this->fetchDirectory();
    }

    // ── Private ────────────────────────────────────────────────────

    private function fetchRemote(): ?array
    {
        $url = $this->hubUrl . '/directory';

        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => 8,
                'header'          => "User-Agent: {$this->userAgent}\r\nAccept: application/json\r\n",
                'ignore_errors'   => true,
                'follow_location' => 1,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) return null;

        $data = json_decode($raw, true);
        if (!is_array($data)) return null;

        // API may return { packages: [...] } or a bare array
        return isset($data['packages']) && is_array($data['packages'])
            ? $data['packages']
            : array_values($data);
    }

    private function fetchLocalMock(): ?array
    {
        // Look for hub-mock/directory.json relative to the project root
        $root     = defined('KRONOS_ROOT') ? KRONOS_ROOT : dirname(__DIR__, 2);
        $mockFile = $root . '/hub-mock/directory.json';

        if (!file_exists($mockFile)) return null;

        $raw  = file_get_contents($mockFile);
        $data = json_decode((string) $raw, true);

        if (!is_array($data)) return null;

        return isset($data['packages']) && is_array($data['packages'])
            ? $data['packages']
            : array_values($data);
    }

    private function getCached(): ?array
    {
        $raw = $this->config->get('kronos_hub_cache');
        if (!$raw) return null;

        $data = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($data)) return null;

        $age = time() - (int) ($data['_cached_at'] ?? 0);
        if ($age > $this->cacheTtl) return null;

        return $data['packages'] ?? null;
    }

    private function setCache(array $packages): void
    {
        $this->config->set('kronos_hub_cache', [
            '_cached_at' => time(),
            'packages'   => $packages,
        ]);
    }
}
