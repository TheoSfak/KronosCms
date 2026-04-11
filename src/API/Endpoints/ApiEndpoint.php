<?php
declare(strict_types=1);

namespace Kronos\API\Endpoints;

/**
 * ApiEndpoint — Base class for all API endpoint handlers.
 * Provides shared utilities (JSON body parsing, etc.) to avoid duplication.
 */
abstract class ApiEndpoint
{
    /**
     * Parse the raw request body as JSON and return an associative array.
     * Aborts with 400 if the body is not valid JSON or not an object.
     *
     * @return array<string, mixed>
     */
    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '{}';
        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            kronos_abort(400, 'Invalid JSON body: ' . $e->getMessage());
        }
        return is_array($decoded) ? $decoded : [];
    }
}
