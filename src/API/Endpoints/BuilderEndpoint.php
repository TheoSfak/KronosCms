<?php
declare(strict_types=1);

namespace Kronos\API\Endpoints;

use Kronos\API\KronosAPIRouter;
use Kronos\Core\KronosApp;
use Kronos\Core\KronosDB;

/**
 * BuilderEndpoint — CRUD for visual page builder layouts.
 * Routes: GET/POST/PUT/DELETE /api/kronos/v1/builder/layouts
 */
class BuilderEndpoint
{
    private KronosAPIRouter $api;
    private KronosDB $db;

    public function __construct(KronosAPIRouter $api)
    {
        $this->api = $api;
        $this->db  = KronosApp::getInstance()->db();
    }

    public function handle(array $params): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        match (true) {
            $method === 'GET' && isset($params['id'])  => $this->getOne((int) $params['id']),
            $method === 'GET'                          => $this->getAll(),
            $method === 'POST'                         => $this->create(),
            $method === 'PUT' && isset($params['id'])  => $this->update((int) $params['id']),
            $method === 'DELETE' && isset($params['id']) => $this->remove((int) $params['id']),
            default                                    => kronos_abort(405, 'Method not allowed'),
        };
    }

    private function getAll(): void
    {
        $rows = $this->db->getResults(
            'SELECT id, layout_name, layout_type, created_at, updated_at FROM kronos_builder_layouts ORDER BY updated_at DESC'
        );
        kronos_json(['data' => $rows]);
    }

    private function getOne(int $id): void
    {
        $row = $this->db->getRow(
            'SELECT * FROM kronos_builder_layouts WHERE id = ? LIMIT 1',
            [$id]
        );
        if ($row === null) {
            kronos_abort(404, 'Layout not found.');
        }
        $row['json_data'] = json_decode($row['json_data'], true) ?? [];
        kronos_json(['data' => $row]);
    }

    private function create(): void
    {
        $body = $this->getJsonBody();
        $name = trim((string) ($body['layout_name'] ?? ''));
        $type = trim((string) ($body['layout_type'] ?? 'page'));

        if ($name === '') {
            kronos_abort(422, 'layout_name is required.');
        }

        $jsonData = json_encode($body['json_data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $id = $this->db->insert('kronos_builder_layouts', [
            'layout_name' => $name,
            'layout_type' => $type,
            'json_data'   => $jsonData,
        ]);

        kronos_json(['success' => true, 'id' => $id], 201);
    }

    private function update(int $id): void
    {
        $existing = $this->db->getRow('SELECT id FROM kronos_builder_layouts WHERE id = ? LIMIT 1', [$id]);
        if ($existing === null) {
            kronos_abort(404, 'Layout not found.');
        }

        $body = $this->getJsonBody();
        $data = [];

        if (isset($body['layout_name'])) {
            $data['layout_name'] = trim((string) $body['layout_name']);
        }
        if (isset($body['layout_type'])) {
            $data['layout_type'] = trim((string) $body['layout_type']);
        }
        if (isset($body['json_data'])) {
            $data['json_data'] = json_encode($body['json_data'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        if (empty($data)) {
            kronos_abort(422, 'No updatable fields provided.');
        }

        $this->db->update('kronos_builder_layouts', $data, ['id' => $id]);
        kronos_json(['success' => true]);
    }

    private function remove(int $id): void
    {
        $existing = $this->db->getRow('SELECT id FROM kronos_builder_layouts WHERE id = ? LIMIT 1', [$id]);
        if ($existing === null) {
            kronos_abort(404, 'Layout not found.');
        }
        $this->db->delete('kronos_builder_layouts', ['id' => $id]);
        kronos_json(['success' => true]);
    }

    /** @return array<string, mixed> */
    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '{}';
        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            kronos_abort(400, 'Invalid JSON body.');
        }
        return is_array($decoded) ? $decoded : [];
    }
}
