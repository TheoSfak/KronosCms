<?php
declare(strict_types=1);

namespace Kronos\Marketplace;

/**
 * PackageInstaller — downloads and installs a Hub package (module or theme).
 *
 * Supported types: 'module' → /modules/{slug}/
 *                  'theme'  → /themes/{slug}/
 *
 * Security notes:
 *  - Download URL must originate from github.com or the configured HUB_API_URL host.
 *  - Extracted paths are validated to prevent ZIP-slip attacks.
 *  - Slugs are restricted to [a-z0-9-] to prevent path traversal.
 */
class PackageInstaller
{
    private string $rootDir;

    public function __construct(string $rootDir = '')
    {
        $this->rootDir = rtrim($rootDir ?: (defined('KRONOS_ROOT') ? KRONOS_ROOT : dirname(__DIR__, 2)), DIRECTORY_SEPARATOR);
    }

    /**
     * Download and install a package.
     *
     * @param  string $slug        Package slug, e.g. "kronos-seo"
     * @param  string $downloadUrl URL to the release ZIP
     * @param  string $type        "module" | "theme"
     * @return array{success: bool, message: string}
     */
    public function install(string $slug, string $downloadUrl, string $type = 'module'): array
    {
        // ── Validate inputs ──────────────────────────────────────
        if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,63}$/', $slug)) {
            return ['success' => false, 'message' => 'Invalid package slug.'];
        }

        if (!in_array($type, ['module', 'theme'], true)) {
            return ['success' => false, 'message' => 'Invalid package type.'];
        }

        if (!filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Invalid download URL.'];
        }

        if (!$this->isAllowedHost($downloadUrl)) {
            return ['success' => false, 'message' => 'Download URL is not from an allowed host.'];
        }

        // ── Resolve destination ──────────────────────────────────
        $destBase = $type === 'theme'
            ? $this->rootDir . '/themes/' . $slug
            : $this->rootDir . '/modules/' . $slug;

        $localSource = $this->resolveLocalPackageSource($downloadUrl);
        if ($localSource !== null) {
            try {
                $this->copyPackage($localSource, $destBase);
                return ['success' => true, 'message' => "Package '{$slug}' installed successfully."];
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => 'Install failed: ' . $e->getMessage()];
            }
        }

        $tmpDir     = $this->rootDir . '/storage/cache/pkg-tmp/' . $slug . '_' . bin2hex(random_bytes(4));
        $zipPath    = $tmpDir . '/package.zip';

        try {
            $this->ensureDir($tmpDir);
            $this->download($downloadUrl, $zipPath);
            $srcDir = $this->extract($zipPath, $tmpDir);
            $this->copyPackage($srcDir, $destBase);
            $this->cleanup($tmpDir);

            return ['success' => true, 'message' => "Package '{$slug}' installed successfully."];
        } catch (\Throwable $e) {
            $this->cleanup($tmpDir);
            return ['success' => false, 'message' => 'Install failed: ' . $e->getMessage()];
        }
    }

    // ── Private ────────────────────────────────────────────────────

    private function isAllowedHost(string $url): bool
    {
        $host    = parse_url($url, PHP_URL_HOST) ?? '';
        $hubHost = parse_url((string) ($_ENV['HUB_API_URL'] ?? getenv('HUB_API_URL') ?: ''), PHP_URL_HOST) ?? '';

        $allowed = [
            'localhost',
            '127.0.0.1',
            '::1',
            'github.com',
            'codeload.github.com',
            'objects.githubusercontent.com',
            'raw.githubusercontent.com',
        ];

        if ($hubHost) {
            $allowed[] = $hubHost;
        }

        return in_array($host, $allowed, true);
    }

    private function resolveLocalPackageSource(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $marker = '/hub-mock/packages/';
        $pos = strpos($path, $marker);
        if ($pos === false) {
            return null;
        }

        $relative = trim(substr($path, $pos + strlen($marker)), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $candidate = $this->rootDir . '/public/hub-mock/packages/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
        $realBase = realpath($this->rootDir . '/public/hub-mock/packages');
        $realCandidate = realpath($candidate);

        if ($realBase === false || $realCandidate === false || !is_dir($realCandidate)) {
            return null;
        }

        return str_starts_with($realCandidate, $realBase . DIRECTORY_SEPARATOR) || $realCandidate === $realBase
            ? $realCandidate
            : null;
    }

    private function download(string $url, string $dest): void
    {
        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => 30,
                'header'          => 'User-Agent: KronosCMS-PackageInstaller/' . \Kronos\Core\KronosVersion::VERSION . "\r\n",
                'follow_location' => 1,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $fp = fopen($url, 'r', false, $ctx);
        if ($fp === false) {
            throw new \RuntimeException("Cannot open download URL: {$url}");
        }

        $bytes = file_put_contents($dest, $fp);
        fclose($fp);

        if ($bytes === false || $bytes === 0) {
            throw new \RuntimeException("Download produced an empty file.");
        }
    }

    private function extract(string $zipPath, string $tmpDir): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive extension is required.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Cannot open ZIP archive.');
        }

        $extractTo = $tmpDir . '/extracted';
        $this->ensureDir($extractTo);

        $realExtract = realpath($extractTo);
        if ($realExtract === false) {
            $zip->close();
            throw new \RuntimeException('Cannot resolve extraction directory.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) continue;

            // ZIP-slip protection: validate the normalized target path before extraction.
            $normalized = $realExtract . DIRECTORY_SEPARATOR . ltrim(
                str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $name),
                DIRECTORY_SEPARATOR
            );
            if (
                str_contains($normalized, DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR)
                || str_ends_with($normalized, DIRECTORY_SEPARATOR . '..')
                || (!str_starts_with($normalized, $realExtract . DIRECTORY_SEPARATOR) && $normalized !== $realExtract)
            ) {
                $zip->close();
                throw new \RuntimeException('ZIP contains unsafe path: ' . $name);
            }
        }

        $zip->extractTo($extractTo);
        $zip->close();

        // GitHub ZIPs have a top-level wrapper directory — unwrap it
        $entries = array_filter(
            scandir($extractTo) ?: [],
            static fn(string $e) => $e !== '.' && $e !== '..'
        );

        if (count($entries) === 1) {
            $first = reset($entries);
            $inner = $extractTo . '/' . $first;
            if (is_dir($inner)) return $inner;
        }

        return $extractTo;
    }

    private function copyPackage(string $src, string $dest): void
    {
        $this->ensureDir($dest);

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $realDest = realpath($dest);

        foreach ($it as $item) {
            /** @var \SplFileInfo $item */
            $rel      = ltrim(str_replace($src, '', $item->getPathname()), DIRECTORY_SEPARATOR . '/');
            $destPath = $dest . '/' . $rel;

            // Extra safety: ensure we stay within dest
            $resolvedDest = realpath(dirname($destPath));
            if ($resolvedDest === false || !str_starts_with($resolvedDest, $realDest)) {
                throw new \RuntimeException("Path traversal detected: {$rel}");
            }

            if ($item->isDir()) {
                $this->ensureDir($destPath);
            } else {
                $this->ensureDir(dirname($destPath));
                copy($item->getPathname(), $destPath);
            }
        }
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory: {$dir}");
        }
    }

    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) return;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
