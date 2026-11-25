<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Public API: Gallery Items
 * GET /api/gallery.php[?category=bridal]
 */

require_once __DIR__ . '/../includes/api_helpers.php';

require_http_method('GET');

$category = isset($_GET['category']) ? trim((string)$_GET['category']) : '';

try {
    $db = get_db();

    if ($category !== '' && $category !== 'all') {
        $stmt = $db->prepare(
            "SELECT id, category, image_url, alt_text, sort_order
             FROM gallery
             WHERE category = :cat
             ORDER BY sort_order ASC, id DESC"
        );
        $stmt->execute([':cat' => $category]);
    } else {
        $stmt = $db->query(
            "SELECT id, category, image_url, alt_text, sort_order
             FROM gallery
             ORDER BY sort_order ASC, id DESC"
        );
    }

    $items = $stmt->fetchAll();

    json_response([
        'status'   => 'success',
        'category' => $category !== '' ? $category : 'all',
        'count'    => count($items),
        'data'     => $items,
    ]);
} catch (Throwable $e) {
    error_log('Error in api/gallery.php: ' . $e->getMessage());
    json_error('Failed to load gallery items.', 500);
}
