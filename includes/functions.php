<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Core Helper & Security Functions
 * PHP 8+ | XAMPP Compatible
 */

// Prevent direct HTTP access
if (php_sapi_name() !== 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

require_once __DIR__ . '/../config/database.php';

/**
 * Initializes a secure PHP session if not already active.
 * Configures modern security cookie attributes (HttpOnly, SameSite=Lax).
 */
function init_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        if (php_sapi_name() === 'cli') {
            @session_start();
            return;
        }

        if (!headers_sent()) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_secure'   => $isHttps,
                'use_strict_mode' => true,
            ]);
        }
    }
}

/**
 * Escapes strings for safe HTML output (XSS defense).
 *
 * @param string|null $value
 * @return string
 */
function e(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generates or retrieves the active session CSRF token.
 *
 * @return string
 */
function csrf_token(): string
{
    init_session();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Renders a hidden CSRF token input field for forms.
 *
 * @return string
 */
function csrf_field(): string
{
    return sprintf('<input type="hidden" name="csrf_token" value="%s">', e(csrf_token()));
}

/**
 * Verifies a submitted CSRF token using timing-attack safe comparison.
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token): bool
{
    init_session();

    if (empty($token) || empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Safely redirects the user to an internal URL, preventing open redirect attacks.
 *
 * @param string $url Target relative path or URL
 * @return never
 */
function safe_redirect(string $url): never
{
    // Restrict redirects to local paths or internal scripts
    if (preg_match('#^https?://#i', $url)) {
        // Disallow arbitrary external domain redirects unless matching localhost/current host
        $parsed = parse_url($url);
        $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (($parsed['host'] ?? '') !== $currentHost && ($parsed['host'] ?? '') !== 'localhost') {
            $url = 'index.php';
        }
    }

    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }

    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
    exit;
}

/**
 * Sets a flash message to display on the next page load.
 *
 * @param string $type success | error | info
 * @param string $message
 */
function set_flash(string $type, string $message): void
{
    init_session();
    $_SESSION['flash'][$type][] = $message;
}

/**
 * Retrieves and clears all pending flash messages.
 *
 * @return array<string, array<int, string>>
 */
function get_flashes(): array
{
    init_session();
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * Trims and sanitizes standard text input.
 *
 * @param string|null $value
 * @return string
 */
function clean_input(?string $value): string
{
    return trim((string)($value ?? ''));
}

/**
 * Formats a pre-filled direct WhatsApp communication URL.
 *
 * @param string $text Message content
 * @param string|null $phone Custom phone number (defaults to business WhatsApp)
 * @return string
 */
function wa_link(string $text = '', ?string $phone = null): string
{
    $settings = get_business_settings();
    $targetPhone = preg_replace('/\D/', '', $phone ?: ($settings['whatsapp'] ?? '917219884594'));

    if (empty($text)) {
        return "https://wa.me/{$targetPhone}";
    }

    return "https://wa.me/{$targetPhone}?text=" . rawurlencode($text);
}

/**
 * Fetches the singleton business profile settings row from MySQL.
 * Caches in memory and falls back to original default content if DB is unavailable.
 *
 * @return array
 */
function get_business_settings(): array
{
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    // Default fallback values matching the original verified business details
    $fallback = [
        'name'        => 'Moms Beauty Touch',
        'phone'       => '+91 72198 84594',
        'phone_tel'   => '+917219884594',
        'whatsapp'    => '917219884594',
        'rating'      => '4.52',
        'hours'       => 'Open 24 hours',
        'address'     => 'Near Manti Memorial High School, Naleswar Nagar, Kaner, Vasai-Virar, Maharashtra 401303, India',
        'map_query'   => 'Moms Beauty Touch, Near Manti Memorial High School, Naleswar Nagar, Kaner, Vasai-Virar, Maharashtra 401303',
        'instagram'   => 'https://www.instagram.com/moms_beauty_touch',
        'facebook'    => '',
    ];

    try {
        $db = get_db();
        $stmt = $db->query('SELECT * FROM business_settings WHERE id = 1 LIMIT 1');
        $row = $stmt->fetch();
        if ($row && is_array($row)) {
            $settings = array_merge($fallback, $row);
            return $settings;
        }
    } catch (Throwable $e) {
        // Fallback gracefully without breaking public frontend rendering
        error_log('[Business Settings Warning] ' . $e->getMessage());
    }

    $settings = $fallback;
    return $settings;
}
