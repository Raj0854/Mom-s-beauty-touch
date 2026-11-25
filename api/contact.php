<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Public API: Contact Message Submission
 * POST /api/contact.php
 */

require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/validation.php';

require_http_method('POST');
verify_api_csrf();

$data = get_request_data();

$name    = clean_input($data['name'] ?? null);
$message = clean_input($data['message'] ?? null);
$phone   = clean_input($data['phone'] ?? null);
$email   = clean_input($data['email'] ?? null);

$errors = [];

if ($err = validate_name($name, 2, 100)) {
    $errors['name'] = $err;
}

if (empty($message) || mb_strlen($message) < 2) {
    $errors['message'] = 'Please enter your message.';
} elseif (mb_strlen($message) > 1000) {
    $errors['message'] = 'Message must not exceed 1000 characters.';
}

if (!empty($phone) && ($err = validate_indian_phone($phone))) {
    $errors['phone'] = $err;
}

if (!empty($email) && ($err = validate_email($email, false))) {
    $errors['email'] = $err;
}

if (!empty($errors)) {
    json_error('Validation failed. Please correct the highlighted errors.', 422, $errors);
}

try {
    $db = get_db();

    $stmt = $db->prepare('
        INSERT INTO contact_messages (name, phone, email, message, is_read, created_at)
        VALUES (:name, :phone, :email, :message, 0, NOW())
    ');

    $stmt->execute([
        ':name'    => $name,
        ':phone'   => !empty($phone) ? $phone : null,
        ':email'   => !empty($email) ? $email : null,
        ':message' => $message,
    ]);

    // Build WhatsApp forwarding link
    $waText = "Hi, I'm {$name}. {$message}";
    $waUrl = wa_link($waText);

    json_response([
        'status'       => 'success',
        'message'      => 'Your message has been sent successfully.',
        'whatsapp_url' => $waUrl,
    ], 201);

} catch (Throwable $e) {
    error_log('[Contact API Error] ' . $e->getMessage());
    json_error('A temporary database error occurred while saving your message. Please try again or chat with us directly on WhatsApp.', 500);
}

