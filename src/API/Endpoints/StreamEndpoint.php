<?php
declare(strict_types=1);

namespace Kronos\API\Endpoints;

use Kronos\API\KronosAPIRouter;
use Kronos\Core\KronosApp;
use Kronos\Core\KronosDB;

/**
 * StreamEndpoint — Server-Sent Events (SSE) real-time stream.
 * Route: GET /api/kronos/v1/stream
 *
 * Emits events: order_update, notification, ai_stream, ping
 * Clients connect with EventSource and receive a persistent stream.
 */
class StreamEndpoint
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
        // Allow the SSE stream to run up to the connection timeout
        set_time_limit(0);
        ignore_user_abort(false); // Stop processing when client disconnects — prevents resource leak

        // SSE headers — disable all buffering
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no'); // Nginx: disable proxy buffering
        header('Connection: keep-alive');

        // Disable PHP output buffering
        if (ob_get_level() > 0) {
            ob_end_flush();
        }

        $user      = kronos_current_user();
        $userId    = (int) ($user['id'] ?? 0);
        $lastPing  = time();
        $lastCheck = 0;

        // Stream loop — runs until client disconnects or timeout
        $timeout = 60 * 2; // 2 minutes max per connection (reconnect via EventSource retry)
        $start   = time();

        while (!connection_aborted() && (time() - $start) < $timeout) {
            $now = time();

            // Send a ping every 15 seconds to keep connection alive
            if ($now - $lastPing >= 15) {
                $this->emit('ping', ['timestamp' => $now]);
                $lastPing = $now;
            }

            // Check for new events every 3 seconds
            if ($now - $lastCheck >= 3) {
                $this->checkOrderUpdates($userId);
                $this->checkNotifications($userId);
                $lastCheck = $now;
            }

            // Flush output to client
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            sleep(1);
        }

        // Advise client to reconnect after 3 seconds
        echo "retry: 3000\n\n";
        flush();
    }

    /**
     * Emit a named SSE event with JSON data.
     *
     * @param array<string, mixed> $data
     */
    public static function emit(string $event, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        echo "event: {$event}\n";
        echo "data: {$json}\n\n";
    }

    // ------------------------------------------------------------------
    // Event sources
    // ------------------------------------------------------------------

    private function checkOrderUpdates(int $userId): void
    {
        // Emit orders that changed status in the last 5 seconds
        $rows = $this->db->getResults(
            "SELECT id, order_number, status, total, updated_at
             FROM kronos_orders
             WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
             ORDER BY updated_at DESC LIMIT 10"
        );

        foreach ($rows as $row) {
            $this->emit('order_update', $row);
        }
    }

    private function checkNotifications(int $userId): void
    {
        // Placeholder: extend with a kronos_notifications table later
        // apply_filters allows modules to push custom notifications into the stream
        $notifications = apply_filters('kronos/stream/notifications', [], $userId);
        foreach ($notifications as $notification) {
            $this->emit('notification', $notification);
        }
    }
}
