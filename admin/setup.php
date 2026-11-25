<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Admin First-Time Setup Wizard
 * Creates initial super administrator when no accounts exist.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation.php';

init_session();

// Lock wizard permanently if any admin already exists
if (!is_first_time_setup()) {
    set_flash('info', 'Admin accounts already exist. Please sign in.');
    safe_redirect('login.php');
}

$errors = [];
$username = '';
$email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors['csrf'] = 'Invalid security token. Please try again.';
    }

    $username = clean_input($_POST['username'] ?? null);
    $email    = clean_input($_POST['email'] ?? null);
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['password_confirm'] ?? '');

    if ($err = validate_name($username, 3, 50)) {
        $errors['username'] = 'Username ' . lcfirst($err);
    }

    if ($err = validate_email($email, true)) {
        $errors['email'] = $err;
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $db = get_db();
            $hash = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $db->prepare(
                "INSERT INTO admins (username, email, password_hash, created_at)
                 VALUES (:username, :email, :hash, NOW())"
            );
            $stmt->execute([
                ':username' => $username,
                ':email'    => $email,
                ':hash'     => $hash,
            ]);

            set_flash('success', 'Super Administrator account created successfully! Please log in.');
            safe_redirect('login.php');
        } catch (Throwable $e) {
            error_log('Admin setup error: ' . $e->getMessage());
            $errors['general'] = 'Failed to create admin account. Please check database connectivity.';
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
  <title>First-Time Setup | Moms Beauty Touch Admin</title>
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
    <p class="auth-subtitle">Initial Administrator Setup</p>
  </div>

  <?php if (!empty($errors['general'])): ?>
    <div class="alert alert-error" style="margin-bottom: 1.25rem;">
      <span><?= e($errors['general']) ?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors['csrf'])): ?>
    <div class="alert alert-error" style="margin-bottom: 1.25rem;">
      <span><?= e($errors['csrf']) ?></span>
    </div>
  <?php endif; ?>

  <form action="setup.php" method="POST" novalidate>
    <?= csrf_field() ?>

    <div class="form-group">
      <label class="form-label" for="username">Username <span class="required">*</span></label>
      <input type="text" id="username" name="username" class="form-control" value="<?= e($username) ?>" required autofocus>
      <?php if (!empty($errors['username'])): ?>
        <span class="form-text" style="color: #DC2626;"><?= e($errors['username']) ?></span>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label class="form-label" for="email">Admin Email <span class="required">*</span></label>
      <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required>
      <?php if (!empty($errors['email'])): ?>
        <span class="form-text" style="color: #DC2626;"><?= e($errors['email']) ?></span>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Password (min. 8 characters) <span class="required">*</span></label>
      <input type="password" id="password" name="password" class="form-control" required minlength="8">
      <?php if (!empty($errors['password'])): ?>
        <span class="form-text" style="color: #DC2626;"><?= e($errors['password']) ?></span>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label class="form-label" for="password_confirm">Confirm Password <span class="required">*</span></label>
      <input type="password" id="password_confirm" name="password_confirm" class="form-control" required minlength="8">
      <?php if (!empty($errors['password_confirm'])): ?>
        <span class="form-text" style="color: #DC2626;"><?= e($errors['password_confirm']) ?></span>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem; padding: 0.75rem;">
      Create Super Administrator
    </button>
  </form>
</div>

</body>
</html>