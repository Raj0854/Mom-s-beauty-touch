<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Admin: Services Management
 * Create, edit, reorder, upload images, and manage service offerings.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/validation.php';

require_admin();

$db = get_db();

// Handle Form Submissions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token invalid or expired.');
        safe_redirect('services.php');
    }

    $action = $_POST['action'];

    // 1. ADD NEW SERVICE
    if ($action === 'create') {
        $title     = clean_input($_POST['title'] ?? '');
        $slug      = clean_input($_POST['slug'] ?? '');
        $short     = clean_input($_POST['short'] ?? '');
        $body      = clean_input($_POST['body'] ?? '');
        $price     = clean_input($_POST['price'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $active    = isset($_POST['active']) ? 1 : 0;

        $errors = [];
        if (empty($title) || mb_strlen($title) > 150) {
            $errors[] = 'Service title is required (up to 150 characters).';
        }

        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        }

        // Check slug uniqueness
        $stmtSlug = $db->prepare("SELECT COUNT(*) FROM services WHERE slug = ?");
        $stmtSlug->execute([$slug]);
        if ($stmtSlug->fetchColumn() > 0) {
            $errors[] = 'The slug "' . $slug . '" is already in use. Please enter a unique slug.';
        }

        if (empty($short)) {
            $errors[] = 'A short description is required for card display.';
        }

        if (empty($body)) {
            $errors[] = 'Full service details / body description is required.';
        }

        $imageUrl = 'assets/images/hero.jpg'; // default fallback
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = handle_image_upload($_FILES['image'], 'uploads/services');
            if ($uploadResult['success']) {
                $imageUrl = $uploadResult['path'];
            } else {
                $errors[] = 'Image upload error: ' . $uploadResult['error'];
            }
        }

        if (empty($errors)) {
            $stmt = $db->prepare("
                INSERT INTO services (slug, title, short, body, price, image_url, active, sort_order, created_at)
                VALUES (:slug, :title, :short, :body, :price, :image_url, :active, :sort_order, NOW())
            ");
            $stmt->execute([
                ':slug'       => $slug,
                ':title'      => $title,
                ':short'      => $short,
                ':body'       => $body,
                ':price'      => $price,
                ':image_url'  => $imageUrl,
                ':active'     => $active,
                ':sort_order' => $sortOrder,
            ]);
            set_flash('success', 'Service "' . $title . '" added successfully.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        safe_redirect('services.php');
    }

    // 2. UPDATE EXISTING SERVICE
    if ($action === 'update') {
        $id        = (int)($_POST['id'] ?? 0);
        $title     = clean_input($_POST['title'] ?? '');
        $slug      = clean_input($_POST['slug'] ?? '');
        $short     = clean_input($_POST['short'] ?? '');
        $body      = clean_input($_POST['body'] ?? '');
        $price     = clean_input($_POST['price'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $active    = isset($_POST['active']) ? 1 : 0;

        $errors = [];
        if ($id <= 0) {
            $errors[] = 'Invalid service ID.';
        }

        if (empty($title) || mb_strlen($title) > 150) {
            $errors[] = 'Title is required.';
        }

        // Slug uniqueness excluding self
        $stmtSlug = $db->prepare("SELECT COUNT(*) FROM services WHERE slug = ? AND id != ?");
        $stmtSlug->execute([$slug, $id]);
        if ($stmtSlug->fetchColumn() > 0) {
            $errors[] = 'The slug "' . $slug . '" is already in use by another service.';
        }

        // Fetch current service
        $stmtCurr = $db->prepare("SELECT image_url FROM services WHERE id = ?");
        $stmtCurr->execute([$id]);
        $currImg = $stmtCurr->fetchColumn();

        $imageUrl = $currImg ?: 'assets/images/hero.jpg';
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = handle_image_upload($_FILES['image'], 'uploads/services');
            if ($uploadResult['success']) {
                // If old image was in uploads/, clean it up
                if (!empty($currImg) && str_starts_with($currImg, 'uploads/')) {
                    delete_uploaded_image($currImg);
                }
                $imageUrl = $uploadResult['path'];
            } else {
                $errors[] = 'Image upload error: ' . $uploadResult['error'];
            }
        }

        if (empty($errors)) {
            $stmt = $db->prepare("
                UPDATE services
                SET slug = :slug, title = :title, short = :short, body = :body,
                    price = :price, image_url = :image_url, active = :active,
                    sort_order = :sort_order, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':slug'       => $slug,
                ':title'      => $title,
                ':short'      => $short,
                ':body'       => $body,
                ':price'      => $price,
                ':image_url'  => $imageUrl,
                ':active'     => $active,
                ':sort_order' => $sortOrder,
                ':id'         => $id,
            ]);
            set_flash('success', 'Service "' . $title . '" updated successfully.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        safe_redirect('services.php');
    }

    // 3. DELETE SERVICE (With Integrity Check)
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Check if any appointment is linked
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM appointments WHERE service_id = ?");
            $stmtCheck->execute([$id]);
            $linkedCount = (int)$stmtCheck->fetchColumn();

            if ($linkedCount > 0) {
                set_flash('error', "Cannot delete this service because {$linkedCount} appointment(s) are linked to it. To hide it from the website without breaking booking records, please edit and deactivate it instead.");
            } else {
                // Fetch image to delete file if in uploads
                $stmtImg = $db->prepare("SELECT image_url FROM services WHERE id = ?");
                $stmtImg->execute([$id]);
                $img = $stmtImg->fetchColumn();

                if (!empty($img) && str_starts_with((string)$img, 'uploads/')) {
                    delete_uploaded_image((string)$img);
                }

                $stmtDel = $db->prepare("DELETE FROM services WHERE id = ?");
                $stmtDel->execute([$id]);
                set_flash('success', 'Service deleted permanently.');
            }
        }
        safe_redirect('services.php');
    }
}

// Fetch all services
$services = $db->query("SELECT * FROM services ORDER BY sort_order ASC, id ASC")->fetchAll();

$pageTitle = 'Services';
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- Header Toolbar -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h2 style="font-family: var(--font-serif); font-size: 1.5rem; color: var(--color-primary);">Services Catalog</h2>
    <p style="font-size: 0.85rem; color: var(--color-text-muted);">Manage bridal, party, and bespoke makeup offerings shown across the website.</p>
  </div>
  <button type="button" class="btn btn-primary" data-modal-open="modal-add-service">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    <span>Add New Service</span>
  </button>
</div>

<!-- Services Table Card -->
<div class="card">
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Image</th>
          <th>Service Title</th>
          <th>Slug</th>
          <th>Starting Price</th>
          <th>Sort Order</th>
          <th>Status</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($services)): ?>
          <tr>
            <td colspan="7" style="text-align: center; color: var(--color-text-muted); padding: 2.5rem;">
              No services found. Click "Add New Service" to create one.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($services as $svc): ?>
            <tr>
              <td>
                <img src="../<?= e($svc['image_url']) ?>" alt="<?= e($svc['title']) ?>" class="table-thumb" onerror="this.src='../assets/images/hero.jpg'">
              </td>
              <td>
                <strong style="color: var(--color-primary);"><?= e($svc['title']) ?></strong>
                <div style="font-size: 0.775rem; color: var(--color-text-muted); max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                  <?= e($svc['short']) ?>
                </div>
              </td>
              <td><code><?= e($svc['slug']) ?></code></td>
              <td><?= !empty($svc['price']) ? e($svc['price']) : '<span style="color: var(--color-text-light);">&mdash;</span>' ?></td>
              <td><?= (int)$svc['sort_order'] ?></td>
              <td>
                <span class="status-badge <?= $svc['active'] ? 'badge-active' : 'badge-inactive' ?>">
                  <?= $svc['active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td style="text-align: right;">
                <div class="btn-group">
                  <button type="button" class="btn btn-secondary btn-sm" data-modal-open="modal-edit-<?= $svc['id'] ?>">
                    Edit
                  </button>
                  <form action="services.php" method="POST" style="margin: 0;" onsubmit="return confirm('Permanently delete service &quot;<?= e($svc['title']) ?>&quot;?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $svc['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </div>

                <!-- Edit Service Modal -->
                <div class="modal-backdrop" id="modal-edit-<?= $svc['id'] ?>">
                  <div class="modal-card" style="text-align: left;">
                    <div class="modal-header">
                      <h3 style="font-family: var(--font-serif); font-size: 1.25rem;">Edit Service: <?= e($svc['title']) ?></h3>
                      <button type="button" class="btn-icon" data-modal-close style="background: none; border: none; cursor: pointer; font-size: 1.25rem;">&times;</button>
                    </div>
                    <form action="services.php" method="POST" enctype="multipart/form-data">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= $svc['id'] ?>">

                      <div class="modal-body">
                        <div class="form-grid">
                          <div class="form-group">
                            <label class="form-label" for="edit-title-<?= $svc['id'] ?>">Title <span class="required">*</span></label>
                            <input type="text" name="title" id="edit-title-<?= $svc['id'] ?>" class="form-control" value="<?= e($svc['title']) ?>" required>
                          </div>
                          <div class="form-group">
                            <label class="form-label" for="edit-slug-<?= $svc['id'] ?>">Slug <span class="required">*</span></label>
                            <input type="text" name="slug" id="edit-slug-<?= $svc['id'] ?>" class="form-control" value="<?= e($svc['slug']) ?>" required>
                          </div>
                        </div>

                        <div class="form-grid">
                          <div class="form-group">
                            <label class="form-label" for="edit-price-<?= $svc['id'] ?>">Price Label (e.g. Starting at ₹12,000)</label>
                            <input type="text" name="price" id="edit-price-<?= $svc['id'] ?>" class="form-control" value="<?= e($svc['price']) ?>" placeholder="Starting at ₹...">
                          </div>
                          <div class="form-group">
                            <label class="form-label" for="edit-sort-<?= $svc['id'] ?>">Display Sort Order</label>
                            <input type="number" name="sort_order" id="edit-sort-<?= $svc['id'] ?>" class="form-control" value="<?= (int)$svc['sort_order'] ?>">
                          </div>
                        </div>

                        <div class="form-group">
                          <label class="form-label" for="edit-short-<?= $svc['id'] ?>">Short Summary (Card excerpt) <span class="required">*</span></label>
                          <textarea name="short" id="edit-short-<?= $svc['id'] ?>" class="form-control" rows="2" required><?= e($svc['short']) ?></textarea>
                        </div>

                        <div class="form-group">
                          <label class="form-label" for="edit-body-<?= $svc['id'] ?>">Full Description <span class="required">*</span></label>
                          <textarea name="body" id="edit-body-<?= $svc['id'] ?>" class="form-control" rows="4" required><?= e($svc['body']) ?></textarea>
                        </div>

                        <div class="form-group">
                          <label class="form-label">Service Image</label>
                          <div class="upload-preview-wrapper">
                            <img src="../<?= e($svc['image_url']) ?>" alt="Current preview" id="preview-svc-<?= $svc['id'] ?>" class="upload-preview-img" onerror="this.src='../assets/images/hero.jpg'">
                            <div>
                              <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp" data-preview="#preview-svc-<?= $svc['id'] ?>">
                              <span class="form-text">Choose a new photo to replace the current image (JPEG, PNG, WebP up to 5MB).</span>
                            </div>
                          </div>
                        </div>

                        <div class="form-group">
                          <label class="form-check">
                            <input type="checkbox" name="active" value="1" <?= $svc['active'] ? 'checked' : '' ?>>
                            <span>Active (display on website and allow inquiries)</span>
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

<!-- Add Service Modal -->
<div class="modal-backdrop" id="modal-add-service">
  <div class="modal-card" style="text-align: left;">
    <div class="modal-header">
      <h3 style="font-family: var(--font-serif); font-size: 1.25rem;">Create New Service</h3>
      <button type="button" class="btn-icon" data-modal-close style="background: none; border: none; cursor: pointer; font-size: 1.25rem;">&times;</button>
    </div>
    <form action="services.php" method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label" for="service-title">Service Title <span class="required">*</span></label>
            <input type="text" name="title" id="service-title" class="form-control" placeholder="e.g. Reception & Sangeet Makeup" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="service-slug">URL Slug <span class="required">*</span></label>
            <input type="text" name="slug" id="service-slug" class="form-control" placeholder="e.g. reception-sangeet" required>
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label class="form-label" for="service-price">Starting Price Label</label>
            <input type="text" name="price" id="service-price" class="form-control" placeholder="Starting at ₹10,000">
          </div>
          <div class="form-group">
            <label class="form-label" for="service-sort">Sort Order</label>
            <input type="number" name="sort_order" id="service-sort" class="form-control" value="0">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="service-short">Short Summary (Card excerpt) <span class="required">*</span></label>
          <textarea name="short" id="service-short" class="form-control" rows="2" placeholder="Brief 1-2 sentence overview shown in service grids." required></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="service-body">Full Description <span class="required">*</span></label>
          <textarea name="body" id="service-body" class="form-control" rows="4" placeholder="Detailed service description..." required></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Service Image</label>
          <div class="upload-preview-wrapper">
            <img src="../assets/images/hero.jpg" alt="Preview" id="preview-new-svc" class="upload-preview-img">
            <div>
              <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp" data-preview="#preview-new-svc">
              <span class="form-text">Upload a high-quality photo (JPEG, PNG, WebP up to 5MB).</span>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-check">
            <input type="checkbox" name="active" value="1" checked>
            <span>Active (display on website)</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Create Service</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

