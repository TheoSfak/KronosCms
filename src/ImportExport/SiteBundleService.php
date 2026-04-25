<?php
declare(strict_types=1);

namespace Kronos\ImportExport;

use Kronos\Core\KronosApp;
use Kronos\Core\KronosDB;

class SiteBundleService
{
    private const SCHEMA = 'kronos-site-bundle-v1';

    private KronosApp $app;
    private KronosDB $db;

    /** @var array<int, string> */
    private array $allowedSettingsKeys;

    /**
     * @param array<int, string> $allowedSettingsKeys
     */
    public function __construct(KronosApp $app, array $allowedSettingsKeys)
    {
        $this->app = $app;
        $this->db = $app->db();
        $this->allowedSettingsKeys = $allowedSettingsKeys;
    }

    /**
     * @return array<string, mixed>
     */
    public function export(): array
    {
        $this->ensureTables();

        $settings = [];
        foreach ($this->allowedSettingsKeys as $key) {
            $settings[$key] = kronos_option($key, '');
        }

        $content = [];
        $posts = $this->db->getResults(
            'SELECT p.*, l.layout_name, l.json_data AS layout_json
             FROM kronos_posts p
             LEFT JOIN kronos_builder_layouts l ON l.id = p.layout_id
             WHERE p.post_type IN ("post","page")
             ORDER BY p.post_type ASC, p.id ASC'
        );
        foreach ($posts as $post) {
            $post['terms'] = $this->db->getResults(
                'SELECT t.slug, t.taxonomy FROM kronos_terms t
                 INNER JOIN kronos_term_relationships tr ON tr.term_id = t.id
                 WHERE tr.post_id = ?
                 ORDER BY t.taxonomy ASC, t.slug ASC',
                [(int) $post['id']]
            );
            $content[] = $post;
        }

        $menus = [];
        foreach ($this->db->getResults('SELECT * FROM kronos_menus ORDER BY id ASC') as $menu) {
            $menu['items'] = $this->db->getResults(
                'SELECT * FROM kronos_menu_items WHERE menu_id = ? ORDER BY sort_order ASC, id ASC',
                [(int) $menu['id']]
            );
            $menus[] = $menu;
        }

        return [
            'schema' => self::SCHEMA,
            'version' => \Kronos\Core\KronosVersion::VERSION,
            'exported_at' => date('c'),
            'settings' => $settings,
            'taxonomies' => $this->db->getResults('SELECT * FROM kronos_terms ORDER BY taxonomy ASC, id ASC'),
            'media' => $this->db->getResults('SELECT * FROM kronos_media ORDER BY id ASC'),
            'content' => $content,
            'menus' => $menus,
        ];
    }

    /**
     * @param array<string, mixed> $bundle
     * @return array<string, int>
     */
    public function import(array $bundle): array
    {
        $this->ensureTables();
        $this->validateBundle($bundle);

        return $this->db->transaction(function () use ($bundle): array {
            $counts = ['settings' => 0, 'taxonomies' => 0, 'media' => 0, 'content' => 0, 'menus' => 0];
            $now = date('Y-m-d H:i:s');

            foreach (($bundle['settings'] ?? []) as $key => $value) {
                if (in_array((string) $key, $this->allowedSettingsKeys, true) && is_scalar($value)) {
                    $this->app->config()->set((string) $key, (string) $value);
                    $counts['settings']++;
                }
            }

            foreach (($bundle['taxonomies'] ?? []) as $term) {
                if (!is_array($term)) {
                    continue;
                }
                $slug = kronos_sanitize_slug((string) ($term['slug'] ?? $term['name'] ?? ''));
                $taxonomy = in_array(($term['taxonomy'] ?? ''), ['category', 'tag'], true) ? (string) $term['taxonomy'] : 'category';
                if ($slug === '') {
                    continue;
                }
                $existing = $this->db->getRow('SELECT id FROM kronos_terms WHERE slug = ? AND taxonomy = ? LIMIT 1', [$slug, $taxonomy]);
                $data = [
                    'name' => trim((string) ($term['name'] ?? $slug)) ?: $slug,
                    'slug' => $slug,
                    'taxonomy' => $taxonomy,
                    'parent_id' => null,
                ];
                if ($existing) {
                    $this->db->update('kronos_terms', $data, ['id' => (int) $existing['id']]);
                } else {
                    $data['created_at'] = $now;
                    $this->db->insert('kronos_terms', $data);
                }
                $counts['taxonomies']++;
            }

            foreach (($bundle['media'] ?? []) as $media) {
                if (!is_array($media) || empty($media['file_url'])) {
                    continue;
                }
                $existing = $this->db->getRow('SELECT id FROM kronos_media WHERE file_url = ? LIMIT 1', [(string) $media['file_url']]);
                $data = [
                    'file_name' => (string) ($media['file_name'] ?? basename((string) $media['file_url'])),
                    'file_path' => (string) ($media['file_path'] ?? ''),
                    'file_url' => (string) $media['file_url'],
                    'mime_type' => (string) ($media['mime_type'] ?? ''),
                    'file_size' => (int) ($media['file_size'] ?? 0),
                    'width' => !empty($media['width']) ? (int) $media['width'] : null,
                    'height' => !empty($media['height']) ? (int) $media['height'] : null,
                    'alt_text' => (string) ($media['alt_text'] ?? ''),
                    'caption' => (string) ($media['caption'] ?? ''),
                    'uploaded_by' => null,
                    'updated_at' => $now,
                ];
                if ($existing) {
                    $this->db->update('kronos_media', $data, ['id' => (int) $existing['id']]);
                } else {
                    $data['created_at'] = $now;
                    $this->db->insert('kronos_media', $data);
                }
                $counts['media']++;
            }

            foreach (($bundle['content'] ?? []) as $post) {
                if (!is_array($post)) {
                    continue;
                }
                $postType = in_array(($post['post_type'] ?? ''), ['post', 'page'], true) ? (string) $post['post_type'] : 'post';
                $slug = kronos_sanitize_slug((string) ($post['slug'] ?? $post['title'] ?? ''));
                if ($slug === '') {
                    continue;
                }
                $layoutId = null;
                if (!empty($post['layout_json'])) {
                    $layoutId = (int) $this->db->insert('kronos_builder_layouts', [
                        'layout_name' => (string) ($post['layout_name'] ?? $post['title'] ?? 'Imported Layout'),
                        'layout_type' => 'page',
                        'json_data' => (string) $post['layout_json'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $existing = $this->db->getRow('SELECT id FROM kronos_posts WHERE slug = ? AND post_type = ? LIMIT 1', [$slug, $postType]);
                $status = in_array(($post['status'] ?? ''), ['draft', 'published', 'scheduled', 'private', 'archived'], true) ? (string) $post['status'] : 'draft';
                $data = [
                    'title' => trim((string) ($post['title'] ?? $slug)) ?: $slug,
                    'slug' => $slug,
                    'content' => (string) ($post['content'] ?? ''),
                    'post_type' => $postType,
                    'status' => $status,
                    'layout_id' => $layoutId ?: null,
                    'meta' => !empty($post['meta']) ? (string) $post['meta'] : null,
                    'published_at' => !empty($post['published_at']) ? (string) $post['published_at'] : null,
                    'updated_at' => $now,
                ];
                if ($existing) {
                    $this->db->update('kronos_posts', $data, ['id' => (int) $existing['id']]);
                    $postId = (int) $existing['id'];
                } else {
                    $data['created_at'] = $now;
                    $postId = (int) $this->db->insert('kronos_posts', $data);
                }

                $this->db->delete('kronos_term_relationships', ['post_id' => $postId]);
                $seenTerms = [];
                foreach (($post['terms'] ?? []) as $termRef) {
                    if (!is_array($termRef)) {
                        continue;
                    }
                    $term = $this->db->getRow(
                        'SELECT id FROM kronos_terms WHERE slug = ? AND taxonomy = ? LIMIT 1',
                        [kronos_sanitize_slug((string) ($termRef['slug'] ?? '')), (string) ($termRef['taxonomy'] ?? 'category')]
                    );
                    if ($term) {
                        $termId = (int) $term['id'];
                        if (!isset($seenTerms[$termId])) {
                            $this->db->insert('kronos_term_relationships', ['post_id' => $postId, 'term_id' => $termId]);
                            $seenTerms[$termId] = true;
                        }
                    }
                }
                $counts['content']++;
            }

            foreach (($bundle['menus'] ?? []) as $menu) {
                if (!is_array($menu)) {
                    continue;
                }
                $slug = kronos_sanitize_slug((string) ($menu['slug'] ?? $menu['name'] ?? ''));
                if ($slug === '') {
                    continue;
                }
                $existing = $this->db->getRow('SELECT id FROM kronos_menus WHERE slug = ? LIMIT 1', [$slug]);
                $data = [
                    'name' => trim((string) ($menu['name'] ?? $slug)) ?: $slug,
                    'slug' => $slug,
                    'updated_at' => $now,
                ];
                if ($existing) {
                    $menuId = (int) $existing['id'];
                    $this->db->update('kronos_menus', $data, ['id' => $menuId]);
                    $oldIds = array_map('intval', array_column(
                        $this->db->getResults('SELECT id FROM kronos_menu_items WHERE menu_id = ?', [$menuId]),
                        'id'
                    ));
                } else {
                    $data['created_at'] = $now;
                    $menuId = (int) $this->db->insert('kronos_menus', $data);
                    $oldIds = [];
                }

                $itemMap = [];
                $pendingParents = [];
                foreach (($menu['items'] ?? []) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $newId = (int) $this->db->insert('kronos_menu_items', [
                        'menu_id' => $menuId,
                        'parent_id' => null,
                        'title' => trim((string) ($item['title'] ?? 'Menu item')) ?: 'Menu item',
                        'url' => (string) ($item['url'] ?? '#'),
                        'item_type' => (string) ($item['item_type'] ?? 'custom'),
                        'object_type' => (string) ($item['object_type'] ?? ''),
                        'object_id' => !empty($item['object_id']) ? (int) $item['object_id'] : null,
                        'target' => (($item['target'] ?? '_self') === '_blank') ? '_blank' : '_self',
                        'sort_order' => (int) ($item['sort_order'] ?? 0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    if (!empty($item['id'])) {
                        $itemMap[(int) $item['id']] = $newId;
                    }
                    if (!empty($item['parent_id'])) {
                        $pendingParents[$newId] = (int) $item['parent_id'];
                    }
                }

                foreach ($pendingParents as $newId => $oldParentId) {
                    $this->db->update('kronos_menu_items', [
                        'parent_id' => $itemMap[$oldParentId] ?? null,
                        'updated_at' => $now,
                    ], ['id' => $newId, 'menu_id' => $menuId]);
                }

                foreach ($oldIds as $oldId) {
                    $this->db->delete('kronos_menu_items', ['id' => $oldId, 'menu_id' => $menuId]);
                }
                $counts['menus']++;
            }

            return $counts;
        });
    }

    /**
     * @param array<string, mixed> $bundle
     */
    public function validateBundle(array $bundle): void
    {
        if (($bundle['schema'] ?? '') !== self::SCHEMA) {
            throw new \RuntimeException('Unsupported bundle schema.');
        }

        foreach (['settings', 'taxonomies', 'media', 'content', 'menus'] as $key) {
            if (isset($bundle[$key]) && !is_array($bundle[$key])) {
                throw new \RuntimeException("Bundle field {$key} must be an array.");
            }
        }

        $seenContent = [];
        foreach (($bundle['content'] ?? []) as $index => $post) {
            if (!is_array($post)) {
                throw new \RuntimeException("Content item {$index} must be an object.");
            }
            $postType = in_array(($post['post_type'] ?? ''), ['post', 'page'], true) ? (string) $post['post_type'] : 'post';
            $slug = kronos_sanitize_slug((string) ($post['slug'] ?? $post['title'] ?? ''));
            if ($slug === '') {
                throw new \RuntimeException("Content item {$index} is missing a usable slug or title.");
            }
            $key = $postType . ':' . $slug;
            if (isset($seenContent[$key])) {
                throw new \RuntimeException("Duplicate content slug in bundle: {$slug} ({$postType}).");
            }
            $seenContent[$key] = true;
        }

        foreach (($bundle['menus'] ?? []) as $menuIndex => $menu) {
            if (!is_array($menu)) {
                throw new \RuntimeException("Menu {$menuIndex} must be an object.");
            }
            $slug = kronos_sanitize_slug((string) ($menu['slug'] ?? $menu['name'] ?? ''));
            if ($slug === '') {
                throw new \RuntimeException("Menu {$menuIndex} is missing a usable slug or name.");
            }
            if (isset($menu['items']) && !is_array($menu['items'])) {
                throw new \RuntimeException("Menu {$slug} items must be an array.");
            }
            $itemIds = [];
            foreach (($menu['items'] ?? []) as $itemIndex => $item) {
                if (!is_array($item)) {
                    throw new \RuntimeException("Menu {$slug} item {$itemIndex} must be an object.");
                }
                if (!empty($item['id'])) {
                    $itemIds[(int) $item['id']] = true;
                }
            }
            foreach (($menu['items'] ?? []) as $itemIndex => $item) {
                if (!is_array($item) || empty($item['parent_id'])) {
                    continue;
                }
                if (empty($itemIds[(int) $item['parent_id']])) {
                    throw new \RuntimeException("Menu {$slug} item {$itemIndex} references a missing parent.");
                }
            }
        }
    }

    private function ensureTables(): void
    {
        kronos_ensure_editor_tables();
        kronos_ensure_default_site_pages();
        kronos_ensure_taxonomy_tables();
        kronos_ensure_media_table();
        kronos_ensure_menu_tables();
    }
}
