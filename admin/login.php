<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Admin Sign-In Portal
 * Secure authentication with brute-force rate limiting and CSRF protection.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

init_session();

// If no admin accounts exist yet, redirect to setup wizard
if (is_first_time_setup()) {
    safe_redirect('setup.php');
}

// If already logged in, redirect straight to dashboard
if (is_admin_logged_in()) {
    safe_redirect('dashboard.php');
}

$error = null;
$username = '';

// Check IP rate limiting
$isRateLimited = is_ip_rate_limited();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isRateLimited) {
        $error = 'Too many failed login attempts. For security, your IP is temporarily blocked for 15 minutes.';
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $username = clean_input($_POST['username'] ?? null);
        $password = (string)($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Please enter both your username/email and password.';
        } else {
            $admin = admin_login($username, $password);
            if ($admin !== null) {
                clear_login_attempts();
                set_flash('success', 'Welcome back, ' . ($admin['username'] ?? 'Administrator') . '!');
                safe_redirect('dashboard.php');
            } else {
                record_login_attempt();
                $error = 'Invalid credentials. Please verify your username and password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Admin Sign In | Moms Beauty Touch</title>
  <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="auth-body">

<div class="auth-card">
  <div class="auth-header">
    <h1 class="auth-logo" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
      <img src="../assets/images/logo.png" alt="Logo" style="width: 2.25rem; height: 2.25rem; border-radius: 50%; object-fit: cover;">
      <span>Moms Beauty Touch</span>
    </h1>
    <p class="auth-subtitle">Administration Portal</p>
  </div>

  <?php
  $flashes = get_flashes();
  if (!empty($flashes)):
  ?>
    <div class="alert-container">
      <?php foreach ($flashes as $type => $messages): ?>
        <?php foreach ($messages as $msg): ?>
          <div class="alert alert-<?= e($type) ?>" role="alert">
            <span><?= e($msg) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($error !== null): ?>
    <div class="alert alert-error" style="margin-bottom: 1.25rem;">
      <span><?= e($error) ?></span>
    </div>
  <?php endif; ?>

  <form action="login.php" method="POST" novalidate>
    <?= csrf_field() ?>

    <div class="form-group">
      <label class="form-label" for="username">Username or Email</label>
      <input type="text" id="username" name="username" class="form-control" value="<?= e($username) ?>" required autofocus autocomplete="username">
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Password</label>
      <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem; padding: 0.75rem;" <?= $isRateLimited ? 'disabled' : '' ?>>
      Sign In
    </button>
  </form>

  <div style="margin-top: 1.5rem; text-align: center;">
    <a href="../index.html" style="font-size: 0.8rem; color: var(--color-text-muted); text-decoration: none;">&larr; Back to Moms Beauty Touch</a>
  </div>
</div>

</body>
</html>

