<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Admin: Appointment Management
 * View, filter, update status, add internal notes, and delete appointments.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce authentication before any processing
require_admin();

$db = get_db();

// Handle Status & Notes Update (BEFORE any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token invalid or expired. Please try again.');
        safe_redirect('appointments.php');
    }

    $action = $_POST['action'];

    if ($action === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = clean_input($_POST['status'] ?? '');
        $internalNotes = clean_input($_POST['internal_notes'] ?? '');

        $allowedStatuses = ['pending', 'accepted', 'rejected', 'completed'];
        if ($id > 0 && in_array($status, $allowedStatuses, true)) {
            // Fetch ref_code for clear feedback message
            $stmtRef = $db->prepare("SELECT ref_code, name FROM appointments WHERE id = ?");
            $stmtRef->execute([$id]);
            $apptInfo = $stmtRef->fetch();
            $refCode = $apptInfo['ref_code'] ?? ('#' . $id);
            $clientName = $apptInfo['name'] ?? 'Client';

            $stmt = $db->prepare("
                UPDATE appointments 
                SET status = :status, internal_notes = :notes, updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt->execute([
                ':status' => $status,
                ':notes'  => $internalNotes,
                ':id'     => $id,
            ]);
            set_flash('success', "Appointment {$refCode} ({$clientName}) status successfully updated to " . ucfirst($status) . "!");
        } else {
            set_flash('error', 'Failed to update: invalid appointment status selected.');
        }

        $redirectUrl = 'appointments.php';
        $redirectParams = [];
        if (!empty($_POST['return_status']) && $_POST['return_status'] !== 'all') {
            $redirectParams['status'] = $_POST['return_status'];
        }
        if (!empty($_POST['return_search'])) {
            $redirectParams['search'] = $_POST['return_search'];
        }
        if (!empty($redirectParams)) {
            $redirectUrl .= '?' . http_build_query($redirectParams);
        }
        safe_redirect($redirectUrl);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM appointments WHERE id = :id");
            $stmt->execute([':id' => $id]);
            set_flash('success', 'Appointment record permanently removed.');
        }
        safe_redirect('appointments.php');
    }
}

// Filtering & Search
$statusFilter = clean_input($_GET['status'] ?? 'all');
$search = clean_input($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if ($statusFilter !== 'all' && in_array($statusFilter, ['pending', 'accepted', 'rejected', 'completed'], true)) {
    $whereClauses[] = "a.status = :status";
    $params[':status'] = $statusFilter;
}

if (!empty($search)) {
    $whereClauses[] = "(a.ref_code LIKE :search_ref OR a.name LIKE :search_name OR a.phone LIKE :search_phone OR a.location LIKE :search_loc)";
    $searchTerm = '%' . $search . '%';
    $params[':search_ref']   = $searchTerm;
    $params[':search_name']  = $searchTerm;
    $params[':search_phone'] = $searchTerm;
    $params[':search_loc']   = $searchTerm;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Count total matching
$countStmt = $db->prepare("SELECT COUNT(*) FROM appointments a {$whereSql}");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

// Query records
$querySql = "
    SELECT a.*, s.title AS service_title
    FROM appointments a
    LEFT JOIN services s ON a.service_id = s.id
    {$whereSql}
    ORDER BY a.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $db->prepare($querySql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// HTML Output Rendering (after all redirects and queries are resolved)
$pageTitle = 'Appointments';
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- Filter & Search Toolbar -->
<div class="card">
  <div class="card-body" style="padding: 1.25rem;">
    <form action="appointments.php" method="GET" class="filter-bar" style="margin: 0;">
      <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
        <span style="font-size: 0.85rem; font-weight: 600; color: var(--color-text-muted);">Status:</span>
        <a href="appointments.php?status=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">All</a>
        <a href="appointments.php?status=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">Pending</a>
        <a href="appointments.php?status=accepted<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="btn btn-sm <?= $statusFilter === 'accepted' ? 'btn-primary' : 'btn-secondary' ?>">Accepted</a>
        <a href="appointments.php?status=completed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="btn btn-sm <?= $statusFilter === 'completed' ? 'btn-primary' : 'btn-secondary' ?>">Completed</a>
        <a href="appointments.php?status=rejected<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-primary' : 'btn-secondary' ?>">Rejected</a>
      </div>

      <div class="search-box">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" name="search" class="form-control" placeholder="Search ref, name, phone..." value="<?= e($search) ?>">
      </div>
    </form>
  </div>
</div>

<!-- Appointments Table -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Inquiries & Bookings (<?= $totalRecords ?>)</h2>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Ref Code</th>
          <th>Client Name</th>
          <th>Service</th>
          <th>Date & Time</th>
          <th>Location</th>
          <th>Phone</th>
          <th>Status</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($appointments)): ?>
          <tr>
            <td colspan="8" style="text-align: center; color: var(--color-text-muted); padding: 2.5rem;">
              No appointment inquiries found matching your filters.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($appointments as $appt): ?>
            <tr>
              <td>
                <strong style="color: var(--color-primary);"><?= e($appt['ref_code']) ?></strong>
              </td>
              <td>
                <div style="font-weight: 600;"><?= e($appt['name']) ?></div>
                <?php if (!empty($appt['email'])): ?>
                  <div style="font-size: 0.75rem; color: var(--color-text-muted);"><?= e($appt['email']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e($appt['service_title'] ?? 'Custom Service') ?></td>
              <td>
                <div><?= e(date('d M Y', strtotime($appt['event_date']))) ?></div>
                <div style="font-size: 0.75rem; color: var(--color-text-muted);"><?= e($appt['preferred_time']) ?></div>
              </td>
              <td style="max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($appt['location']) ?>">
                <?= e($appt['location']) ?>
              </td>
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
              <td style="text-align: right;">
                <div class="btn-group">
                  <?php
                  $waDigits = preg_replace('/\D/', '', $appt['phone']);
                  $waMsg = "Hello " . $appt['name'] . ", this is Moms Beauty Touch regarding your booking inquiry (Ref: " . $appt['ref_code'] . ") for " . ($appt['service_title'] ?? 'our makeup service') . " on " . date('d M Y', strtotime($appt['event_date'])) . ".";
                  $waHref = "https://wa.me/" . (str_starts_with($waDigits, '91') ? $waDigits : '91' . $waDigits) . "?text=" . rawurlencode($waMsg);
                  ?>
                  <a href="<?= e($waHref) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm" title="WhatsApp client">
                    WhatsApp
                  </a>
                  <button type="button" class="btn btn-secondary btn-sm" data-modal-open="modal-appt-<?= $appt['id'] ?>">
                    Manage
                  </button>
                </div>

                <!-- Manage Modal for this appointment -->
                <div class="modal-backdrop" id="modal-appt-<?= $appt['id'] ?>">
                  <div class="modal-card" style="text-align: left;">
                    <div class="modal-header">
                      <h3 style="font-family: var(--font-serif); font-size: 1.25rem;">
                        Inquiry <?= e($appt['ref_code']) ?> &mdash; <?= e($appt['name']) ?>
                      </h3>
                      <button type="button" class="btn-icon" data-modal-close style="background: none; border: none; cursor: pointer; font-size: 1.25rem;">&times;</button>
                    </div>
                    <form action="appointments.php" method="POST">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_status">
                      <input type="hidden" name="id" value="<?= $appt['id'] ?>">
                      <input type="hidden" name="return_search" value="<?= e($search) ?>">
                      <input type="hidden" name="return_status" value="<?= e($statusFilter) ?>">

                      <div class="modal-body">
                        <!-- Details summary -->
                        <div style="background-color: var(--color-bg); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; font-size: 0.85rem; line-height: 1.6;">
                          <div><strong>Phone:</strong> <?= e($appt['phone']) ?></div>
                          <?php if (!empty($appt['email'])): ?>
                            <div><strong>Email:</strong> <?= e($appt['email']) ?></div>
                          <?php endif; ?>
                          <div><strong>Service:</strong> <?= e($appt['service_title'] ?? 'Custom Service') ?></div>
                          <div><strong>Event Date:</strong> <?= e(date('l, d F Y', strtotime($appt['event_date']))) ?> at <?= e($appt['preferred_time']) ?></div>
                          <div><strong>Location:</strong> <?= e($appt['location']) ?></div>
                          <?php if (!empty($appt['notes'])): ?>
                            <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed var(--color-border);">
                              <strong>Customer Notes:</strong><br>
                              <?= nl2br(e($appt['notes'])) ?>
                            </div>
                          <?php endif; ?>
                          <div style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--color-text-muted);">
                            Submitted: <?= e(date('d M Y, H:i:s', strtotime($appt['created_at']))) ?>
                          </div>
                        </div>

                        <!-- Update Status -->
                        <div class="form-group">
                          <label class="form-label" for="status-<?= $appt['id'] ?>">Appointment Status</label>
                          <select name="status" id="status-<?= $appt['id'] ?>" class="form-control">
                            <option value="pending" <?= $appt['status'] === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                            <option value="accepted" <?= $appt['status'] === 'accepted' ? 'selected' : '' ?>>Accepted / Confirmed</option>
                            <option value="completed" <?= $appt['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="rejected" <?= $appt['status'] === 'rejected' ? 'selected' : '' ?>>Rejected / Cancelled</option>
                          </select>
                        </div>

                        <!-- Internal Notes -->
                        <div class="form-group">
                          <label class="form-label" for="notes-<?= $appt['id'] ?>">Internal Notes (Private to Admin)</label>
                          <textarea name="internal_notes" id="notes-<?= $appt['id'] ?>" class="form-control" rows="3" placeholder="Add staff reminders, trial notes, advance payment details..."><?= e($appt['internal_notes']) ?></textarea>
                        </div>
                      </div>

                      <div class="modal-footer" style="justify-content: space-between;">
                        <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Permanently delete inquiry <?= e($appt['ref_code']) ?>?');">
                          Delete
                        </button>
                        <div style="display: flex; gap: 0.5rem;">
                          <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Close</button>
                          <button type="submit" name="action" value="update_status" class="btn btn-primary btn-sm">Save Changes</button>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>

              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination Controls -->
  <?php if ($totalPages > 1): ?>
    <div class="pagination" style="padding: 1rem 1.5rem;">
      <div>
        Showing page <?= $page ?> of <?= $totalPages ?> (<?= $totalRecords ?> total records)
      </div>
      <div class="pagination-links">
        <?php if ($page > 1): ?>
          <a href="appointments.php?page=<?= $page - 1 ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>" class="page-btn">&laquo; Prev</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="appointments.php?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
          <a href="appointments.php?page=<?= $page + 1 ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>" class="page-btn">Next &raquo;</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
