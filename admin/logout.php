<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Admin Sign-Out Handler
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

init_session();

// If POST, verify CSRF if token present
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    verify_csrf_token((string)$_POST['csrf_token']);
}

admin_logout();

init_session();
set_flash('info', 'You have been safely signed out.');
safe_redirect('login.php');

