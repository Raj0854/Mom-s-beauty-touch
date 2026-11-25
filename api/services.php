<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Public API: Services List
 * GET /api/services.php
 */

require_once __DIR__ . '/../includes/api_helpers.php';

require_http_method('GET');

try {
    $db = get_db();
    $stmt = $db->query(
        "SELECT id, slug, title, short, body, price, image_url, sort_order, active
         FROM services
         WHERE active = 1
         ORDER BY sort_order ASC, id ASC"
    );
    $services = $stmt->fetchAll();

    json_response([
        'status' => 'success',
        'count'  => count($services),
        'data'   => $services,
    ]);
} catch (Throwable $e) {
    error_log('Error in api/services.php: ' . $e->getMessage());
    json_error('Failed to load services.', 500);
}
