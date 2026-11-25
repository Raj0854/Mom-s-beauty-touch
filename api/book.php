<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Public API: Appointment Booking Submission
 * POST /api/book.php
 */

require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/validation.php';

require_http_method('POST');
verify_api_csrf();

$data = get_request_data();

$name     = clean_input($data['name'] ?? null);
$phone    = clean_input($data['phone'] ?? null);
$email    = clean_input($data['email'] ?? null);
$service  = clean_input($data['service'] ?? null);
$date     = clean_input($data['date'] ?? null);
$time     = clean_input($data['time'] ?? null);
$location = clean_input($data['location'] ?? null);
$notes    = clean_input($data['notes'] ?? null);

$errors = [];

// 1. Validation
if ($err = validate_name($name, 2, 100)) {
    $errors['name'] = $err;
}

if ($err = validate_indian_phone($phone)) {
    $errors['phone'] = $err;
}

if (!empty($email) && ($err = validate_email($email, false))) {
    $errors['email'] = $err;
}

if (empty($service)) {
    $errors['service'] = 'Please choose a service.';
}

if ($err = validate_event_date($date)) {
    $errors['date'] = $err;
}

if (empty($time) || mb_strlen($time) > 20) {
    $errors['time'] = 'Please choose a preferred time.';
}

if (empty($location) || mb_strlen($location) < 2) {
    $errors['location'] = 'Please enter the event location.';
} elseif (mb_strlen($location) > 200) {
    $errors['location'] = 'Event location must not exceed 200 characters.';
}

if (!empty($notes) && mb_strlen($notes) > 1000) {
    $errors['notes'] = 'Additional requirements must not exceed 1000 characters.';
}

if (!empty($errors)) {
    json_error('Validation failed. Please correct the highlighted errors.', 422, $errors);
}

// 2. Database Lookup & Insertion
try {
    $db = get_db();

    // Verify service exists and is active
    $stmtService = $db->prepare('SELECT id, title FROM services WHERE slug = ? AND active = 1 LIMIT 1');
    $stmtService->execute([$service]);
    $serviceRow = $stmtService->fetch();

    if (!$serviceRow) {
        json_error('The selected service is not available. Please select another service.', 422, [
            'service' => 'Selected service is invalid or unavailable.'
        ]);
    }

    $serviceId = (int)$serviceRow['id'];
    $serviceTitle = (string)$serviceRow['title'];

    // Clean phone number digits for standardized storage
    $phoneDigits = preg_replace('/\D/', '', $phone);
    if (str_starts_with($phoneDigits, '91') && strlen($phoneDigits) === 12) {
        $phoneDigits = substr($phoneDigits, 2);
    }

    // Duplicate check: Prevent duplicate submissions within 5 minutes
    $stmtDup = $db->prepare('
        SELECT ref_code FROM appointments 
        WHERE phone = ? AND service_id = ? AND event_date = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        LIMIT 1
    ');
    $stmtDup->execute([$phoneDigits, $serviceId, $date]);
    $existingRef = $stmtDup->fetchColumn();

    $db->beginTransaction();

    if ($existingRef) {
        $refCode = (string)$existingRef;
        $db->rollBack();
    } else {
        $refCode = generate_mbt_reference($db);

        $stmtInsert = $db->prepare('
            INSERT INTO appointments (
                ref_code, service_id, name, phone, email, event_date, preferred_time, location, notes, status, created_at
            ) VALUES (
                :ref_code, :service_id, :name, :phone, :email, :event_date, :preferred_time, :location, :notes, "pending", NOW()
            )
        ');

        $stmtInsert->execute([
            ':ref_code'       => $refCode,
            ':service_id'     => $serviceId,
            ':name'           => $name,
            ':phone'          => $phoneDigits,
            ':email'          => !empty($email) ? $email : null,
            ':event_date'     => $date,
            ':preferred_time' => $time,
            ':location'       => $location,
            ':notes'          => !empty($notes) ? $notes : null,
        ]);

        $db->commit();
    }

    // 3. Build WhatsApp pre-filled message URL
    $lines = [
        "Appointment enquiry (Ref: {$refCode})",
        "Name: {$name}",
        "Phone: {$phone}",
    ];
    if (!empty($email)) {
        $lines[] = "Email: {$email}";
    }
    $lines[] = "Service: {$serviceTitle}";
    $lines[] = "Date: {$date}";
    $lines[] = "Time: {$time}";
    $lines[] = "Location: {$location}";
    if (!empty($notes)) {
        $lines[] = "Notes: {$notes}";
    }

    $waMessage = implode("\n", $lines);
    $waUrl = wa_link($waMessage);

    json_response([
        'status'       => 'success',
        'message'      => 'Your appointment enquiry has been recorded successfully.',
        'ref_code'     => $refCode,
        'whatsapp_url' => $waUrl,
        'appointment'  => [
            'service'  => $serviceTitle,
            'date'     => $date,
            'time'     => $time,
            'location' => $location,
        ],
    ], 201);

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[Booking API Error] ' . $e->getMessage());
    json_error('A temporary database error occurred while saving your appointment. Please try again or contact us directly on WhatsApp.', 500);
}

