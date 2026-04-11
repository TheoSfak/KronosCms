<?php
declare(strict_types=1);

namespace Kronos\Core;

/**
 * KronosConfig — Persistent key-value option store.
 * Backed by the `kronos_options` database table.
 * Supports a runtime in-memory cache to minimize DB reads.
 */
class KronosConfig
{
    private KronosDB $db;

    /** @var array<string, mixed> In-memory cache */
    private array $cache = [];

    /** @var bool Whether the full options table has been preloaded */
    private bool $preloaded = false;

    public function __construct(KronosDB $db)
    {
        $this->db = $db;
    }

    /**
     * Pre-load all options into memory in a single query.
     */
    public function preload(): void
    {
        if ($this->preloaded) {
            return;
        }
        $rows = $this->db->getResults('SELECT option_key, option_value FROM kronos_options');
        foreach ($rows as $row) {
            $this->cache[$row['option_key']] = $this->maybeUnserialize($row['option_value']);
        }
        $this->preloaded = true;
    }

    /**
     * Get an option value. Returns $default if not found.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $row = $this->db->getRow(
            'SELECT option_value FROM kronos_options WHERE option_key = ? LIMIT 1',
            [$key]
        );

        if ($row === null) {
            return $default;
        }

        $value = $this->maybeUnserialize($row['option_value']);
        $this->cache[$key] = $value;
        return $value;
    }

    /**
     * Set (insert or update) an option value.
     * Uses INSERT … ON DUPLICATE KEY UPDATE to avoid a read-then-write race condition.
     */
    public function set(string $key, mixed $value): void
    {
        $serialized = $this->maybeSerialize($value);

        // Atomic upsert — requires UNIQUE key on option_key (enforced by schema)
        $this->db->query(
            'INSERT INTO `kronos_options` (`option_key`, `option_value`)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `option_value` = VALUES(`option_value`)',
            [$key, $serialized]
        );

        $this->cache[$key] = $value;
    }

    /**
     * Delete an option.
     */
    public function delete(string $key): void
    {
        $this->db->delete('kronos_options', ['option_key' => $key]);
        unset($this->cache[$key]);
    }

    /**
     * Serialize arrays/objects to JSON for storage.
     */
    private function maybeSerialize(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        return (string) $value;
    }

    /**
     * Attempt to decode stored JSON back to array, or return as-is.
     */
    private function maybeUnserialize(string $value): mixed
    {
        if ($value === '' || ($value[0] !== '{' && $value[0] !== '[')) {
            return $value;
        }
        $decoded = json_decode($value, true);
        return $decoded !== null ? $decoded : $value;
    }
}
