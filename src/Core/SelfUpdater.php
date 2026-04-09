<?php
declare(strict_types=1);

namespace Kronos\Core;

/**
 * SelfUpdater — downloads a release ZIP, extracts it, and replaces application files.
 *
 * Protected paths (never overwritten):
 *  - config/app.php
 *  - .env
 *  - storage/
 *  - vendor/
 */
class SelfUpdater
{
    /** Files/dirs that must never be overwritten during an update. */
    private const PROTECTED = [
        'config/app.php',
        '.env',
        'storage',
        'vendor',
    ];

    private string       $rootDir;
    private KronosConfig $config;

    public function __construct(string $rootDir, KronosConfig $config)
    {
        $this->rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR);
        $this->config  = $config;
    }

    /**
     * Download, extract, and apply a release ZIP.
     *
     * @param  string $downloadUrl URL to the release .zip
     * @param  string $version     Semantic version string (e.g. "0.2.0")
     * @return array{success: bool, message: string}
     */
    public function apply(string $downloadUrl, string $version): array
    {
        // Basic input validation
        if (!filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Invalid download URL.'];
        }

        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return ['success' => false, 'message' => 'Invalid version format.'];
        }

        // Only allow downloads from github.com
        $host = parse_url($downloadUrl, PHP_URL_HOST) ?? '';
        if (!in_array($host, ['github.com', 'codeload.github.com', 'objects.githubusercontent.com'], true)) {
            return ['success' => false, 'message' => 'Download URL must be from github.com.'];
        }

        $tmpDir  = $this->rootDir . '/storage/cache/update-tmp';
        $zipPath = $tmpDir . '/release.zip';

        try {
            $this->ensureDir($tmpDir);
            $this->download($downloadUrl, $zipPath);
            $extractDir = $this->extract($zipPath, $tmpDir);
            $this->copyFiles($extractDir);
            $this->bumpVersion($version);
            $this->runMigrations();
            $this->cleanup($tmpDir);

            return ['success' => true, 'message' => "Updated to v{$version} successfully."];
        } catch (\Throwable $e) {
            $this->cleanup($tmpDir);
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    // ── Private ────────────────────────────────────────────────────

    private function download(string $url, string $dest): void
    {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 60,
                'header'  => 'User-Agent: KronosCMS-Updater/' . KronosVersion::VERSION . "\r\n",
                'follow_location' => 1,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $bytes = file_put_contents($dest, fopen($url, 'r', false, $ctx));

        if ($bytes === false || $bytes === 0) {
            throw new \RuntimeException("Failed to download release from {$url}");
        }
    }

    private function extract(string $zipPath, string $tmpDir): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive extension is required for updates.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Failed to open release ZIP.');
        }

        $extractTo = $tmpDir . '/extracted';
        $this->ensureDir($extractTo);
        $zip->extractTo($extractTo);
        $zip->close();

        // GitHub ZIPs wrap everything in a top-level directory — detect it
        $entries = array_filter(
            scandir($extractTo) ?: [],
            static fn(string $e) => $e !== '.' && $e !== '..'
        );

        if (count($entries) === 1) {
            $first = reset($entries);
            $inner = $extractTo . '/' . $first;
            if (is_dir($inner)) {
                return $inner;
            }
        }

        return $extractTo;
    }

    private function copyFiles(string $srcDir): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $item) {
            /** @var \SplFileInfo $item */
            $rel  = ltrim(str_replace($srcDir, '', $item->getPathname()), DIRECTORY_SEPARATOR . '/');
            $dest = $this->rootDir . '/' . $rel;

            if ($this->isProtected($rel)) {
                continue;
            }

            if ($item->isDir()) {
                $this->ensureDir($dest);
            } else {
                $this->ensureDir(dirname($dest));
                copy($item->getPathname(), $dest);
            }
        }
    }

    private function bumpVersion(string $version): void
    {
        $filePath = $this->rootDir . '/src/Core/KronosVersion.php';
        if (!file_exists($filePath)) return;

        $content = file_get_contents($filePath);
        $content = preg_replace(
            "/const VERSION\s*=\s*'[^']+'/",
            "const VERSION = '{$version}'",
            (string) $content
        );
        file_put_contents($filePath, $content);
    }

    private function runMigrations(): void
    {
        $app = \Kronos\Core\KronosApp::getInstance();
        (new KronosInstaller($app->db()))->migrate();
    }

    private function isProtected(string $rel): bool
    {
        $rel = str_replace('\\', '/', $rel);
        foreach (self::PROTECTED as $guard) {
            // Exact match or directory prefix
            if ($rel === $guard || str_starts_with($rel, $guard . '/')) {
                return true;
            }
        }
        return false;
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory: {$dir}");
        }
    }

    private function cleanup(string $tmpDir): void
    {
        if (is_dir($tmpDir)) {
            $this->rmDir($tmpDir);
        }
    }

    private function rmDir(string $dir): void
    {
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
