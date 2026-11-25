<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Admin: Testimonials & Client Reviews
 * Add, edit, publish/unpublish, and remove client reviews.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation.php';

require_admin();

$db = get_db();

// Handle Actions (Create, Update, Toggle, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token invalid or expired.');
        safe_redirect('testimonials.php');
    }

    $action = $_POST['action'];

    // 1. ADD TESTIMONIAL
    if ($action === 'create') {
        $name        = clean_input($_POST['name'] ?? '');
        $reviewText  = clean_input($_POST['review_text'] ?? '');
        $rating      = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $isPublished = isset($_POST['is_published']) ? 1 : 0;

        $errors = [];
        if (empty($name) || mb_strlen($name) > 100) {
            $errors[] = 'Client name is required (up to 100 characters).';
        }

        if (empty($reviewText)) {
            $errors[] = 'Review text is required.';
        }

        if (empty($errors)) {
            $stmt = $db->prepare("
                INSERT INTO testimonials (name, review_text, rating, is_published, created_at)
                VALUES (:name, :review_text, :rating, :is_published, NOW())
            ");
            $stmt->execute([
                ':name'         => $name,
                ':review_text'  => $reviewText,
                ':rating'       => $rating,
                ':is_published' => $isPublished,
            ]);
            set_flash('success', 'Testimonial from "' . $name . '" added.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        safe_redirect('testimonials.php');
    }

    // 2. UPDATE TESTIMONIAL
    if ($action === 'update') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = clean_input($_POST['name'] ?? '');
        $reviewText  = clean_input($_POST['review_text'] ?? '');
        $rating      = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $isPublished = isset($_POST['is_published']) ? 1 : 0;

        if ($id > 0 && !empty($name) && !empty($reviewText)) {
            $stmt = $db->prepare("
                UPDATE testimonials 
                SET name = :name, review_text = :review, rating = :rating, 
                    is_published = :pub, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':name'   => $name,
                ':review' => $reviewText,
                ':rating' => $rating,
                ':pub'    => $isPublished,
                ':id'     => $id,
            ]);
            set_flash('success', 'Testimonial updated successfully.');
        } else {
            set_flash('error', 'Please fill in all required fields.');
        }
        safe_redirect('testimonials.php');
    }

    // 3. TOGGLE PUBLISHED STATUS
    if ($action === 'toggle_publish') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE testimonials SET is_published = NOT is_published WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('success', 'Testimonial visibility toggled.');
        }
        safe_redirect('testimonials.php');
    }

    // 4. DELETE TESTIMONIAL
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM testimonials WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('success', 'Testimonial removed.');
        }
        safe_redirect('testimonials.php');
    }
}

// Fetch all testimonials
$testimonials = $db->query("SELECT * FROM testimonials ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Testimonials';
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- Header Toolbar -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h2 style="font-family: var(--font-serif); font-size: 1.5rem; color: var(--color-primary);">Client Testimonials</h2>
    <p style="font-size: 0.85rem; color: var(--color-text-muted);">Manage real bride reviews and ratings displayed on the website.</p>
  </div>
  <button type="button" class="btn btn-primary" data-modal-open="modal-add-testimonial">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    <span>Add Testimonial</span>
  </button>
</div>

<!-- Testimonials Table Card -->
<div class="card">
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Client Name</th>
          <th>Rating</th>
          <th>Review Snippet</th>
          <th>Status</th>
          <th>Date</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($testimonials)): ?>
          <tr>
            <td colspan="6" style="text-align: center; color: var(--color-text-muted); padding: 2.5rem;">
              No testimonials recorded yet. Click "Add Testimonial" to enter client reviews.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($testimonials as $t): ?>
            <tr>
              <td><strong style="color: var(--color-primary);"><?= e($t['name']) ?></strong></td>
              <td style="color: #D97706; font-size: 1.1rem; letter-spacing: 0.1em;">
                <?= str_repeat('★', (int)$t['rating']) ?><?= str_repeat('☆', 5 - (int)$t['rating']) ?>
              </td>
              <td style="max-width: 340px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                &ldquo;<?= e($t['review_text']) ?>&rdquo;
              </td>
              <td>
                <span class="status-badge <?= $t['is_published'] ? 'badge-active' : 'badge-inactive' ?>">
                  <?= $t['is_published'] ? 'Published' : 'Hidden' ?>
                </span>
              </td>
              <td style="font-size: 0.8rem; color: var(--color-text-muted);">
                <?= e(date('d M Y', strtotime($t['created_at']))) ?>
              </td>
              <td style="text-align: right;">
                <div class="btn-group">
                  <form action="testimonials.php" method="POST" style="margin: 0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_publish">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" title="Toggle visibility">
                      <?= $t['is_published'] ? 'Hide' : 'Publish' ?>
                    </button>
                  </form>

                  <button type="button" class="btn btn-secondary btn-sm" data-modal-open="modal-edit-t-<?= $t['id'] ?>">
                    Edit
                  </button>

                  <form action="testimonials.php" method="POST" style="margin: 0;" onsubmit="return confirm('Delete testimonial from <?= e($t['name']) ?>?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </div>

                <!-- Edit Modal -->
                <div class="modal-backdrop" id="modal-edit-t-<?= $t['id'] ?>">
                  <div class="modal-card" style="text-align: left;">
                    <div class="modal-header">
                      <h3 style="font-family: var(--font-serif); font-size: 1.25rem;">Edit Testimonial</h3>
                      <button type="button" class="btn-icon" data-modal-close style="background: none; border: none; cursor: pointer; font-size: 1.25rem;">&times;</button>
                    </div>
                    <form action="testimonials.php" method="POST">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= $t['id'] ?>">

                      <div class="modal-body">
                        <div class="form-grid">
                          <div class="form-group">
                            <label class="form-label" for="edit-tname-<?= $t['id'] ?>">Client Name <span class="required">*</span></label>
                            <input type="text" name="name" id="edit-tname-<?= $t['id'] ?>" class="form-control" value="<?= e($t['name']) ?>" required>
                          </div>
                          <div class="form-group">
                            <label class="form-label" for="edit-trating-<?= $t['id'] ?>">Rating (1-5 Stars)</label>
                            <select name="rating" id="edit-trating-<?= $t['id'] ?>" class="form-control">
                              <option value="5" <?= (int)$t['rating'] === 5 ? 'selected' : '' ?>>5 Stars (★★★★★)</option>
                              <option value="4" <?= (int)$t['rating'] === 4 ? 'selected' : '' ?>>4 Stars (★★★★☆)</option>
                              <option value="3" <?= (int)$t['rating'] === 3 ? 'selected' : '' ?>>3 Stars (★★★☆☆)</option>
                              <option value="2" <?= (int)$t['rating'] === 2 ? 'selected' : '' ?>>2 Stars (★★☆☆☆)</option>
                              <option value="1" <?= (int)$t['rating'] === 1 ? 'selected' : '' ?>>1 Star (★☆☆☆☆)</option>
                            </select>
                          </div>
                        </div>

                        <div class="form-group">
                          <label class="form-label" for="edit-treview-<?= $t['id'] ?>">Review Text <span class="required">*</span></label>
                          <textarea name="review_text" id="edit-treview-<?= $t['id'] ?>" class="form-control" rows="4" required><?= e($t['review_text']) ?></textarea>
                        </div>

                        <div class="form-group">
                          <label class="form-check">
                            <input type="checkbox" name="is_published" value="1" <?= $t['is_published'] ? 'checked' : '' ?>>
                            <span>Published (visible on website)</span>
                          </label>
                        </div>
                      </div>

                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
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
</div>

<!-- Add Testimonial Modal -->
<div class="modal-backdrop" id="modal-add-testimonial">
  <div class="modal-card" style="text-align: left;">
    <div class="modal-header">
      <h3 style="font-family: var(--font-serif); font-size: 1.25rem;">Add Client Testimonial</h3>
      <button type="button" class="btn-icon" data-modal-close style="background: none; border: none; cursor: pointer; font-size: 1.25rem;">&times;</button>
    </div>
    <form action="testimonials.php" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label" for="new-tname">Client Name <span class="required">*</span></label>
            <input type="text" name="name" id="new-tname" class="form-control" placeholder="e.g. Priya Sharma" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="new-trating">Rating</label>
            <select name="rating" id="new-trating" class="form-control">
              <option value="5" selected>5 Stars (★★★★★)</option>
              <option value="4">4 Stars (★★★★☆)</option>
              <option value="3">3 Stars (★★★☆☆)</option>
              <option value="2">2 Stars (★★☆☆☆)</option>
              <option value="1">1 Star (★☆☆☆☆)</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="new-treview">Review Text <span class="required">*</span></label>
          <textarea name="review_text" id="new-treview" class="form-control" rows="4" placeholder="Client's review of their bridal or event makeup experience..." required></textarea>
        </div>

        <div class="form-group">
          <label class="form-check">
            <input type="checkbox" name="is_published" value="1" checked>
            <span>Publish immediately</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Add Testimonial</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

