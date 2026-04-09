<?php
declare(strict_types=1);

namespace Kronos\API\Endpoints;

use Kronos\API\KronosAPIRouter;
use Kronos\Core\KronosApp;
use Kronos\Core\KronosDB;
use OpenAI;

/**
 * AIEndpoint — OpenAI chat proxy.
 * Route: POST /api/kronos/v1/ai/chat
 * Logs all exchanges to kronos_ai_logs.
 */
class AIEndpoint
{
    private KronosAPIRouter $api;
    private KronosDB $db;
    private KronosApp $app;

    public function __construct(KronosAPIRouter $api)
    {
        $this->api = $api;
        $this->app = KronosApp::getInstance();
        $this->db  = $this->app->db();
    }

    public function handle(array $params): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            kronos_abort(405, 'Method not allowed.');
        }

        $body   = $this->getJsonBody();
        $user   = kronos_current_user();
        $userId = (int) ($user['id'] ?? 0);

        $message   = trim((string) ($body['message'] ?? ''));
        $sessionId = trim((string) ($body['session_id'] ?? ''));

        if ($message === '') {
            kronos_abort(422, 'message is required.');
        }
        if ($sessionId === '') {
            $sessionId = bin2hex(random_bytes(16));
        }

        // Retrieve conversation history for this session (last 20 messages)
        $history = $this->db->getResults(
            'SELECT role, content FROM kronos_ai_logs
             WHERE session_id = ? ORDER BY created_at ASC LIMIT 20',
            [$sessionId]
        );

        // Log the incoming user message
        $this->db->insert('kronos_ai_logs', [
            'user_id'    => $userId,
            'session_id' => $sessionId,
            'role'       => 'user',
            'content'    => $message,
            'model'      => '',
        ]);

        // Build messages array for OpenAI
        $messages = [];
        $systemPrompt = apply_filters(
            'kronos/ai/system_prompt',
            'You are KronosCMS AI assistant. Help the user manage their website content and answer questions concisely.'
        );
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        foreach ($history as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        // Call OpenAI
        $apiKey = (string) $this->app->env('OPENAI_API_KEY', '');
        if ($apiKey === '') {
            kronos_abort(503, 'OpenAI API key is not configured.');
        }

        $model = (string) $this->app->config()->get('openai_model', $this->app->env('OPENAI_MODEL', 'gpt-4o'));

        try {
            $client   = OpenAI::client($apiKey);
            $response = $client->chat()->create([
                'model'    => $model,
                'messages' => $messages,
            ]);

            $assistantContent = $response->choices[0]->message->content ?? '';

            // Log the assistant reply
            $this->db->insert('kronos_ai_logs', [
                'user_id'    => $userId,
                'session_id' => $sessionId,
                'role'       => 'assistant',
                'content'    => $assistantContent,
                'model'      => $model,
            ]);

            kronos_json([
                'success'    => true,
                'session_id' => $sessionId,
                'message'    => $assistantContent,
                'model'      => $model,
            ]);
        } catch (\Exception $e) {
            error_log('[KronosCMS AI] OpenAI error: ' . $e->getMessage());
            kronos_abort(502, 'AI service error. Please try again later.');
        }
    }

    /** @return array<string, mixed> */
    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '{}';
        try {
            $decoded = json_decode($raw, true, 10, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            kronos_abort(400, 'Invalid JSON body.');
        }
        return is_array($decoded) ? $decoded : [];
    }
}
