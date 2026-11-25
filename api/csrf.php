<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Public API: CSRF Token Provider
 * GET /api/csrf.php
 */

require_once __DIR__ . '/../includes/api_helpers.php';

require_http_method('GET');

json_response([
    'status'     => 'success',
    'csrf_token' => csrf_token(),
]);

