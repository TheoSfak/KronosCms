<?php
/**
 * Hub Mock — local stub of the KronosHub marketplace API.
 *
 * Serves hub-mock/directory.json when HUB_API_URL is set to
 * http://localhost/hub-mock  (or whatever local path you configure).
 *
 * Routes:
 *   GET /hub-mock/         → 200 {"status":"ok","version":"1.0.0"}
 *   GET /hub-mock/directory → 200  directory.json contents
 *   All others             → 404
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

$uri     = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$segment = trim(preg_replace('#^/hub-mock#', '', $uri), '/');

switch ($segment) {
    case '':
    case 'index.php':
        echo json_encode(['status' => 'ok', 'version' => '1.0.0', 'name' => 'KronosHub Mock']);
        break;

    case 'directory':
        $file = __DIR__ . '/directory.json';
        if (!file_exists($file)) {
            http_response_code(500);
            echo json_encode(['error' => 'directory.json not found']);
            break;
        }
        readfile($file);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not found', 'path' => $segment]);
}
