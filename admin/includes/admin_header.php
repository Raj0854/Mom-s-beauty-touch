<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Admin Portal Header & Shell Template
 * PHP 8.3+ | XAMPP Compatible
 */

// Deny direct HTTP access
if (php_sapi_name() !== 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce admin authentication
require_admin();

$adminUser = current_admin();
$adminUsername = $adminUser['username'] ?? 'Admin';
$adminInitial = strtoupper(substr($adminUsername, 0, 1));

// Current active page tracking for navigation highlighting
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

// Fetch pending appointments count for sidebar badge
$pendingCount = 0;
try {
    $db = get_db();
    $pendingStmt = $db->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'");
    $pendingCount = (int)$pendingStmt->fetchColumn();
} catch (Throwable $e) {
    error_log('Failed to fetch pending appointments count: ' . $e->getMessage());
}

$pageTitle = $pageTitle ?? 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($pageTitle) ?> | Moms Beauty Touch Admin</title>
  <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">

  <!-- Typography -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Karla:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

  <!-- Admin Stylesheet -->
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

<div class="admin-wrapper">
  <!-- Sidebar -->
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
      <a href="dashboard.php" class="sidebar-brand" style="display: flex; align-items: center; gap: 0.5rem;">
        <img src="../assets/images/logo.png" alt="Logo" style="width: 2rem; height: 2rem; border-radius: 50%; object-fit: cover;">
        <span>Moms Beauty Touch</span>
        <span class="brand-badge">Admin</span>
      </a>
    </div>

    <nav class="sidebar-nav">
      <span class="nav-section-title">Overview</span>
      <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
        <span class="nav-link-content">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          <span>Dashboard</span>
        </span>
      </a>

      <span class="nav-section-title">Operations</span>
      <a href="appointments.php" class="nav-link <?= $currentPage === 'appointments.php' ? 'active' : '' ?>">
        <span class="nav-link-content">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          <span>Appointments</span>
        </span>
        <?php if ($pendingCount > 0): ?>
          <span class="nav-badge"><?= $pendingCount ?></span>
        <?php endif; ?>
      </a>

      <span class="nav-section-title">Content Management</span>
      <a href="services.php" class="nav-link <?= $currentPage === 'services.php' ? 'active' : '' ?>">
        <span class="nav-link-content">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
          <span>Services</span>
        </span>
      </a>

      <a href="gallery.php" class="nav-link <?= $currentPage === 'gallery.php' ? 'active' : '' ?>">
        <span class="nav-link-content">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          <span>Gallery</span>
        </span>
      </a>

      <a href="testimonials.php" class="nav-link <?= $currentPage === 'testimonials.php' ? 'active' : '' ?>">
        <span class="nav-link-content">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
          <span>Testimonials</span>
        </span>
      </a>

      <span class="nav-section-title">Configuration</span>
      <a href="settings.php" class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
        <span class="nav-link-content">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          <span>Business Settings</span>
        </span>
      </a>

      <a href="../index.html" target="_blank" rel="noopener" class="nav-link" style="margin-top: 1rem; border: 1px dashed rgba(255, 255, 255, 0.2);">
        <span class="nav-link-content">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
          <span>View Public Site</span>
        </span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar"><?= e($adminInitial) ?></div>
        <div class="user-meta">
          <div class="user-name"><?= e($adminUsername) ?></div>
          <div class="user-role">Administrator</div>
        </div>
      </div>
      <form action="logout.php" method="POST" style="margin: 0;">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-icon btn-secondary" title="Sign out" style="background: rgba(255,255,255,0.1); border: none; color: #FAF7F2; padding: 0.4rem;">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 1.1rem; height: 1.1rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        </button>
      </form>
    </div>
  </aside>

  <!-- Main View Area -->
  <main class="admin-main">
    <header class="admin-topbar">
      <div class="topbar-left">
        <button type="button" class="menu-toggle" aria-label="Toggle navigation menu">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 1.5rem; height: 1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
      </div>

      <div class="topbar-actions">
        <a href="../index.html" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
          <span>Live Site</span>
        </a>
      </div>
    </header>

    <div class="admin-content">
      <?php
      $flashes = get_flashes();
      if (!empty($flashes)):
      ?>
        <div class="alert-container">
          <?php foreach ($flashes as $type => $messages): ?>
            <?php foreach ($messages as $msg): ?>
              <div class="alert alert-<?= e($type) ?>" role="alert">
                <span><?= e($msg) ?></span>
                <button type="button" class="alert-close" aria-label="Dismiss">&times;</button>
              </div>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

