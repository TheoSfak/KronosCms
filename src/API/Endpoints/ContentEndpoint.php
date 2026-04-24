<?php
declare(strict_types=1);

namespace Kronos\API\Endpoints;

use Kronos\API\KronosAPIRouter;
use Kronos\Core\KronosApp;
use Kronos\Core\KronosDB;

/**
 * ContentEndpoint — dashboard content operations used by the backoffice.
 */
class ContentEndpoint extends ApiEndpoint
{
    private KronosDB $db;

    public function __construct(KronosAPIRouter $api)
    {
        $this->db = KronosApp::getInstance()->db();
    }

    public function handle(array $params): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        match (true) {
            $method === 'DELETE' && isset($params['id']) => $this->deletePost((int) $params['id']),
            default => kronos_abort(405, 'Method not allowed.'),
        };
    }

    private function deletePost(int $id): void
    {
        if ($id <= 0) {
            kronos_abort(400, 'Invalid post ID.');
        }

        $post = $this->db->getRow('SELECT id, layout_id FROM kronos_posts WHERE id = ? LIMIT 1', [$id]);
        if ($post === null) {
            kronos_abort(404, 'Post not found.');
        }

        $this->db->delete('kronos_posts', ['id' => $id]);
        kronos_json(['success' => true]);
    }
}
