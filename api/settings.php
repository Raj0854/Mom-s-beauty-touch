<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Public API: Business Settings
 * GET /api/settings.php
 */

require_once __DIR__ . '/../includes/api_helpers.php';

require_http_method('GET');

try {
    $settings = get_business_settings();

    // Whitelist only public fields
    $publicSettings = [
        'name'       => $settings['name'] ?? 'Moms Beauty Touch',
        'phone'      => $settings['phone'] ?? '+91 72198 84594',
        'phone_tel'  => $settings['phone_tel'] ?? '+917219884594',
        'whatsapp'   => $settings['whatsapp'] ?? '917219884594',
        'rating'     => (float)($settings['rating'] ?? 4.52),
        'hours'      => $settings['hours'] ?? 'Open 24 hours',
        'address'    => $settings['address'] ?? '',
        'map_query'  => $settings['map_query'] ?? '',
        'instagram'  => $settings['instagram'] ?? '',
        'facebook'   => $settings['facebook'] ?? '',
        'logo_url'   => file_exists(__DIR__ . '/../assets/images/logo.png') ? 'assets/images/logo.png' : '',
    ];

    json_response([
        'status' => 'success',
        'data'   => $publicSettings,
    ]);
} catch (Throwable $e) {
    error_log('Error in api/settings.php: ' . $e->getMessage());
    json_error('Failed to load business settings.', 500);
}
