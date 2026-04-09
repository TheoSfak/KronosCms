<?php
declare(strict_types=1);

namespace Kronos\Core;

/**
 * UpdateChecker — polls GitHub releases API to detect new versions.
 * Results are cached in kronos_options for 24 h to avoid rate-limit hits.
 */
class UpdateChecker
{
    private const CACHE_KEY = 'kronos_update_cache';
    private const TTL       = 86400; // 24 h

    private KronosConfig  $config;
    private string        $currentVersion;
    private string        $repoSlug;       // e.g. "TheoSfak/KronosCms"
    private string        $userAgent;

    public function __construct(KronosConfig $config, string $repoSlug = 'TheoSfak/KronosCms')
    {
        $this->config         = $config;
        $this->currentVersion = KronosVersion::VERSION;
        $this->repoSlug       = $repoSlug;
        $this->userAgent      = 'KronosCMS/' . KronosVersion::VERSION . ' (github.com/TheoSfak/KronosCms)';
    }

    /**
     * Check for an available update.
     *
     * @return array{
     *   update_available: bool,
     *   current_version: string,
     *   latest_version: string|null,
     *   download_url: string|null,
     *   release_url: string|null,
     *   checked_at: int
     * }
     */
    public function check(): array
    {
        $cached = $this->getCached();
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->fetchLatestRelease();
        $this->setCache($result);

        return $result;
    }

    /**
     * Force a fresh check, bypassing the cache.
     */
    public function forceCheck(): array
    {
        $this->clearCache();
        return $this->check();
    }

    // ── Private ────────────────────────────────────────────────────

    private function getCached(): ?array
    {
        $raw = $this->config->get(self::CACHE_KEY);
        if (!$raw) return null;

        $data = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($data)) return null;

        $age = time() - (int) ($data['checked_at'] ?? 0);
        if ($age > self::TTL) return null;

        return $data;
    }

    private function setCache(array $data): void
    {
        $this->config->set(self::CACHE_KEY, $data);
    }

    private function clearCache(): void
    {
        $this->config->set(self::CACHE_KEY, null);
    }

    private function fetchLatestRelease(): array
    {
        $url = "https://api.github.com/repos/{$this->repoSlug}/releases/latest";

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 8,
                'header'  => implode("\r\n", [
                    'User-Agent: ' . $this->userAgent,
                    'Accept: application/vnd.github+json',
                    'X-GitHub-Api-Version: 2022-11-28',
                ]),
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $raw  = @file_get_contents($url, false, $ctx);
        $base = [
            'update_available' => false,
            'current_version'  => $this->currentVersion,
            'latest_version'   => null,
            'download_url'     => null,
            'release_url'      => null,
            'checked_at'       => time(),
        ];

        if ($raw === false) {
            return $base; // Network error — silently skip
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['tag_name'])) {
            return $base;
        }

        // Strip leading "v" from tag (e.g. "v0.2.0" → "0.2.0")
        $latestTag  = ltrim((string) $data['tag_name'], 'v');
        $downloadUrl = $this->findZipAsset($data);

        return [
            'update_available' => version_compare($latestTag, $this->currentVersion, '>'),
            'current_version'  => $this->currentVersion,
            'latest_version'   => $latestTag,
            'download_url'     => $downloadUrl ?? ($data['zipball_url'] ?? null),
            'release_url'      => $data['html_url'] ?? null,
            'checked_at'       => time(),
        ];
    }

    /**
     * Prefer an attached .zip release asset over the auto-generated zipball.
     */
    private function findZipAsset(array $release): ?string
    {
        foreach ($release['assets'] ?? [] as $asset) {
            if (str_ends_with((string) ($asset['name'] ?? ''), '.zip')) {
                return $asset['browser_download_url'] ?? null;
            }
        }
        return null;
    }
}
