<?php
declare(strict_types=1);

namespace Kronos\Content;

use Kronos\Core\KronosConfig;
use Kronos\Core\KronosDB;

class ScheduledPublisher
{
    private KronosDB $db;
    private KronosConfig $config;

    public function __construct(KronosDB $db, KronosConfig $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * @return array{published:int, ids:array<int, int>, skipped:bool}
     */
    public function run(int $limit = 50): array
    {
        kronos_ensure_editor_tables();

        $limit = max(1, min(200, $limit));
        $lockName = 'kronos_scheduled_publisher';
        $lock = (int) $this->db->getVar('SELECT GET_LOCK(?, 0)', [$lockName]);
        if ($lock !== 1) {
            return ['published' => 0, 'ids' => [], 'skipped' => true];
        }

        try {
            $due = $this->db->getResults(
                "SELECT id FROM kronos_posts
                 WHERE status = 'scheduled'
                   AND published_at IS NOT NULL
                   AND published_at <= NOW()
                 ORDER BY published_at ASC
                 LIMIT {$limit}"
            );

            $ids = [];
            foreach ($due as $post) {
                $id = (int) $post['id'];
                $this->db->update('kronos_posts', [
                    'status' => 'published',
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['id' => $id]);
                $ids[] = $id;
            }

            $this->config->set('scheduled_publish_last_run', [
                'ran_at' => date('c'),
                'published' => count($ids),
                'ids' => $ids,
            ]);
            return ['published' => count($ids), 'ids' => $ids, 'skipped' => false];
        } finally {
            $this->db->getVar('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }
}
