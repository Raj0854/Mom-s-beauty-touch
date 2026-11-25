<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — JSON API Response & Request Helpers
 * PHP 8.3+ | XAMPP Compatible
 */

// Prevent direct HTTP access
if (php_sapi_name() !== 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

require_once __DIR__ . '/functions.php';

/**
 * Sends a structured JSON response and terminates execution.
 *
 * @param array<string, mixed> $data
 * @param int $statusCode
 * @return never
 */
function json_response(array $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sends an error JSON response and terminates execution.
 *
 * @param string $message
 * @param int $statusCode
 * @param array<string, string> $errors
 * @return never
 */
function json_error(string $message, int $statusCode = 400, array $errors = []): never
{
    $response = [
        'status'  => 'error',
        'message' => $message,
    ];

    if (!empty($errors)) {
        $response['errors'] = $errors;
    }

    json_response($response, $statusCode);
}

/**
 * Restricts an endpoint to a specific HTTP method (e.g. POST or GET).
 *
 * @param string $expectedMethod
 */
function require_http_method(string $expectedMethod): void
{
    $actualMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($actualMethod !== strtoupper($expectedMethod)) {
        header('Allow: ' . strtoupper($expectedMethod));
        json_error("Method {$actualMethod} not allowed. Please use {$expectedMethod}.", 405);
    }
}

/**
 * Extracts and parses request payload from standard POST or raw JSON body.
 *
 * @return array<string, mixed>
 */
function get_request_data(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

    if (str_contains(strtolower($contentType), 'application/json')) {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $parsed = json_decode($raw, true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }
    }

    return $_POST;
}

/**
 * Validates CSRF token from either HTTP Header (X-CSRF-Token) or body field (csrf_token).
 */
function verify_api_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    if (empty($token)) {
        $data = get_request_data();
        $token = $data['csrf_token'] ?? null;
    }

    if (!is_string($token) || !verify_csrf_token($token)) {
        json_error('Invalid or expired security token (CSRF). Please refresh the page and try again.', 403);
    }
}

