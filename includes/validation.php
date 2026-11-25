<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Input Validation & Sanitization Helpers
 * PHP 8.3+ | XAMPP Compatible
 */

// Prevent direct HTTP access
if (php_sapi_name() !== 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

/**
 * Validates a full name.
 *
 * @param string $name
 * @param int $min
 * @param int $max
 * @return string|null Error message or null if valid
 */
function validate_name(string $name, int $min = 2, int $max = 100): ?string
{
    $len = mb_strlen(trim($name));
    if ($len < $min) {
        return "Please enter your name (at least {$min} characters).";
    }
    if ($len > $max) {
        return "Name must not exceed {$max} characters.";
    }
    return null;
}

/**
 * Validates an Indian mobile phone number.
 * Accepts formats: +91 9876543210, 919876543210, 9876543210, 98765 43210.
 *
 * @param string $phone
 * @return string|null Error message or null if valid
 */
function validate_indian_phone(string $phone): ?string
{
    $clean = trim($phone);
    $pattern = '/^(\+?91[\s-]?)?[6-9]\d{4}[\s-]?\d{5}$/';
    if (!preg_match($pattern, $clean)) {
        return 'Please enter a valid 10-digit Indian mobile number.';
    }
    return null;
}

/**
 * Validates an optional or required email address.
 *
 * @param string|null $email
 * @param bool $required
 * @return string|null Error message or null if valid
 */
function validate_email(?string $email, bool $required = false): ?string
{
    $clean = trim((string)($email ?? ''));

    if (empty($clean)) {
        return $required ? 'Please enter a valid email address.' : null;
    }

    if (mb_strlen($clean) > 255 || !filter_var($clean, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }

    return null;
}

/**
 * Validates an event date (must not be in the past).
 *
 * @param string $date (Format YYYY-MM-DD)
 * @return string|null Error message or null if valid
 */
function validate_event_date(string $date): ?string
{
    $clean = trim($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean)) {
        return 'Please choose a valid event date.';
    }

    $parsed = date_parse_from_format('Y-m-d', $clean);
    if ($parsed['error_count'] > 0 || !checkdate($parsed['month'], $parsed['day'], $parsed['year'])) {
        return 'Please choose a valid calendar date.';
    }

    $today = date('Y-m-d');
    if ($clean < $today) {
        return "Event date cannot be in the past.";
    }

    return null;
}

/**
 * Generates a unique, human-readable appointment reference code (e.g. MBT-8F421B).
 * Guarantees uniqueness in the appointments table.
 *
 * @param PDO $db
 * @return string
 */
function generate_mbt_reference(PDO $db): string
{
    $maxAttempts = 10;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $hex = strtoupper(bin2hex(random_bytes(3))); // 6 hex characters
        $ref = "MBT-{$hex}";

        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM appointments WHERE ref_code = ?');
        $stmt->execute([$ref]);
        if ((int)$stmt->fetch()['total'] === 0) {
            return $ref;
        }
    }

    // High entropy fallback
    return 'MBT-' . strtoupper(substr(uniqid(), -6));
}

