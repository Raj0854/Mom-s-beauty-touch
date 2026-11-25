<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Admin Dashboard
 * Real-time operational overview, key metrics, and recent inquiries.
 */

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/admin_header.php';

try {
    $db = get_db();

    // 1. KPI Counts
    $kpiPending = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();
    $kpiTotalAppt = (int)$db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    $kpiServices = (int)$db->query("SELECT COUNT(*) FROM services WHERE active = 1")->fetchColumn();
    $kpiGallery = (int)$db->query("SELECT COUNT(*) FROM gallery")->fetchColumn();
    $kpiMessages = (int)$db->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();

    // 2. Recent 5 Appointments
    $stmtRecentAppt = $db->query("
        SELECT a.id, a.ref_code, a.name, a.phone, a.event_date, a.preferred_time, a.status, a.created_at,
               s.title AS service_title
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $recentAppointments = $stmtRecentAppt->fetchAll();

    // 3. Recent 5 Contact Messages
    $stmtRecentMsgs = $db->query("
        SELECT id, name, phone, email, message, is_read, created_at
        FROM contact_messages
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentMessages = $stmtRecentMsgs->fetchAll();

    // Fetch business WhatsApp for direct links
    $bizSettings = get_business_settings();
    $bizWa = $bizSettings['whatsapp'] ?? '917219884594';

} catch (Throwable $e) {
    error_log('Dashboard data load error: ' . $e->getMessage());
    $error = 'Failed to load dashboard statistics from the database.';
}
?>

<!-- KPI Metrics Grid -->
<div class="kpi-grid">
  <div class="kpi-card">
    <div>
      <div class="kpi-value"><?= $kpiPending ?></div>
      <div class="kpi-label">Pending Inquiries</div>
    </div>
    <div class="kpi-icon pending">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
  </div>

  <div class="kpi-card">
    <div>
      <div class="kpi-value"><?= $kpiTotalAppt ?></div>
      <div class="kpi-label">Total Inquiries</div>
    </div>
    <div class="kpi-icon total">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    </div>
  </div>

  <div class="kpi-card">
    <div>
      <div class="kpi-value"><?= $kpiServices ?></div>
      <div class="kpi-label">Active Services</div>
    </div>
    <div class="kpi-icon services">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
    </div>
  </div>

  <div class="kpi-card">
    <div>
      <div class="kpi-value"><?= $kpiGallery ?></div>
      <div class="kpi-label">Portfolio Items</div>
    </div>
    <div class="kpi-icon gallery">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    </div>
  </div>

  <div class="kpi-card">
    <div>
      <div class="kpi-value"><?= $kpiMessages ?></div>
      <div class="kpi-label">Unread Messages</div>
    </div>
    <div class="kpi-icon inquiries">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
    </div>
  </div>
</div>

<!-- Recent Appointments Section -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Recent Appointment Inquiries</h2>
    <a href="appointments.php" class="btn btn-secondary btn-sm">View All Inquiries &rarr;</a>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Ref Code</th>
          <th>Client Name</th>
          <th>Service</th>
          <th>Event Date</th>
          <th>Phone</th>
          <th>Status</th>
          <th>Submitted</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recentAppointments)): ?>
          <tr>
            <td colspan="8" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
              No appointment inquiries received yet.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($recentAppointments as $appt): ?>
            <tr>
              <td><strong style="color: var(--color-primary);"><?= e($appt['ref_code']) ?></strong></td>
              <td><?= e($appt['name']) ?></td>
              <td><?= e($appt['service_title'] ?? 'Custom Service') ?></td>
              <td><?= e(date('d M Y', strtotime($appt['event_date']))) ?> (<?= e($appt['preferred_time']) ?>)</td>
              <td>
                <a href="tel:<?= e($appt['phone']) ?>" style="color: inherit; text-decoration: none;">
                  <?= e($appt['phone']) ?>
                </a>
              </td>
              <td>
                <span class="status-badge badge-<?= e($appt['status']) ?>">
                  <?= e(ucfirst($appt['status'])) ?>
                </span>
              </td>
              <td style="color: var(--color-text-muted); font-size: 0.8rem;">
                <?= e(date('d M, H:i', strtotime($appt['created_at']))) ?>
              </td>
              <td style="text-align: right;">
                <div class="btn-group">
                  <?php
                  $waDigits = preg_replace('/\D/', '', $appt['phone']);
                  $waMsg = "Hello " . $appt['name'] . ", regarding your appointment inquiry (" . $appt['ref_code'] . ") at Moms Beauty Touch:";
                  $waHref = "https://wa.me/" . (str_starts_with($waDigits, '91') ? $waDigits : '91' . $waDigits) . "?text=" . rawurlencode($waMsg);
                  ?>
                  <a href="<?= e($waHref) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm" title="Reply on WhatsApp">
                    WhatsApp
                  </a>
                  <a href="appointments.php?search=<?= e($appt['ref_code']) ?>" class="btn btn-secondary btn-sm">
                    Manage
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Recent Contact Messages Section -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Recent Contact Messages</h2>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Contact Info</th>
          <th>Message</th>
          <th>Date</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recentMessages)): ?>
          <tr>
            <td colspan="5" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
              No contact messages received yet.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($recentMessages as $msg): ?>
            <tr style="<?= empty($msg['is_read']) ? 'background-color: rgba(198, 164, 106, 0.05); font-weight: 500;' : '' ?>">
              <td><?= e($msg['name']) ?></td>
              <td>
                <?php if (!empty($msg['phone'])): ?>
                  <div><?= e($msg['phone']) ?></div>
                <?php endif; ?>
                <?php if (!empty($msg['email'])): ?>
                  <div style="font-size: 0.8rem; color: var(--color-text-muted);"><?= e($msg['email']) ?></div>
                <?php endif; ?>
              </td>
              <td style="max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= e($msg['message']) ?>
              </td>
              <td style="color: var(--color-text-muted); font-size: 0.8rem;">
                <?= e(date('d M Y, H:i', strtotime($msg['created_at']))) ?>
              </td>
              <td style="text-align: right;">
                <?php if (!empty($msg['phone'])): ?>
                  <?php
                  $pDigits = preg_replace('/\D/', '', $msg['phone']);
                  $pHref = "https://wa.me/" . (str_starts_with($pDigits, '91') ? $pDigits : '91' . $pDigits) . "?text=" . rawurlencode("Hello " . $msg['name'] . ", thanks for contacting Moms Beauty Touch!");
                  ?>
                  <a href="<?= e($pHref) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm">
                    Reply
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

