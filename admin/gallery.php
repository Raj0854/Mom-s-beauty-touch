<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Admin: Gallery / Portfolio Management
 * Upload, organize, categorize, reorder, and delete portfolio images.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/validation.php';

require_admin();

$db = get_db();

$validCategories = ['Bridal', 'Party', 'Special Occasions', 'Engagement', 'Wedding'];

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token invalid or expired.');
        safe_redirect('gallery.php');
    }

    $action = $_POST['action'];

    // 1. ADD NEW PHOTO
    if ($action === 'create') {
        $category  = clean_input($_POST['category'] ?? '');
        $altText   = clean_input($_POST['alt_text'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        $errors = [];
        if (!in_array($category, $validCategories, true)) {
            $errors[] = 'Please select a valid portfolio category.';
        }

        if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please choose an image file to upload.';
        } else {
            $uploadResult = handle_image_upload($_FILES['image'], 'uploads/gallery');
            if (!$uploadResult['success']) {
                $errors[] = 'Upload error: ' . $uploadResult['error'];
            } else {
                $imageUrl = $uploadResult['path'];
            }
        }

        if (empty($errors)) {
            $stmt = $db->prepare("
                INSERT INTO gallery (image_url, category, alt_text, sort_order, created_at)
                VALUES (:image_url, :category, :alt_text, :sort_order, NOW())
            ");
            $stmt->execute([
                ':image_url'  => $imageUrl,
                ':category'   => $category,
                ':alt_text'   => $altText,
                ':sort_order' => $sortOrder,
            ]);
            set_flash('success', 'New portfolio photo added successfully.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        safe_redirect('gallery.php');
    }

    // 2. UPDATE PHOTO METADATA
    if ($action === 'update') {
        $id        = (int)($_POST['id'] ?? 0);
        $category  = clean_input($_POST['category'] ?? '');
        $altText   = clean_input($_POST['alt_text'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($id > 0 && in_array($category, $validCategories, true)) {
            $stmt = $db->prepare("
                UPDATE gallery 
                SET category = :cat, alt_text = :alt, sort_order = :sort, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':cat'  => $category,
                ':alt'  => $altText,
                ':sort' => $sortOrder,
                ':id'   => $id,
            ]);
            set_flash('success', 'Photo details updated.');
        } else {
            set_flash('error', 'Invalid photo update parameters.');
        }
        safe_redirect('gallery.php');
    }

    // 3. DELETE PHOTO
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Fetch image URL to safely remove file from disk
            $stmtImg = $db->prepare("SELECT image_url FROM gallery WHERE id = ?");
            $stmtImg->execute([$id]);
            $img = $stmtImg->fetchColumn();

            if (!empty($img) && str_starts_with((string)$img, 'uploads/')) {
                delete_uploaded_image((string)$img);
            }

            $stmtDel = $db->prepare("DELETE FROM gallery WHERE id = ?");
            $stmtDel->execute([$id]);
            set_flash('success', 'Portfolio photo removed.');
        }
        safe_redirect('gallery.php');
    }
}

// Category filter
$catFilter = clean_input($_GET['category'] ?? 'all');
$whereSql = '';
$params = [];

if ($catFilter !== 'all' && in_array($catFilter, $validCategories, true)) {
    $whereSql = 'WHERE category = :cat';
    $params[':cat'] = $catFilter;
}

$stmt = $db->prepare("SELECT * FROM gallery {$whereSql} ORDER BY sort_order ASC, id DESC");
$stmt->execute($params);
$items = $stmt->fetchAll();

$pageTitle = 'Gallery';
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- Header Toolbar -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
  <div>
    <h2 style="font-family: var(--font-serif); font-size: 1.5rem; color: var(--color-primary);">Portfolio & Gallery</h2>
    <p style="font-size: 0.85rem; color: var(--color-text-muted);">Organize high-resolution bridal and event makeup photography.</p>
  </div>
  <button type="button" class="btn btn-primary" data-modal-open="modal-add-photo">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    <span>Upload New Photo</span>
  </button>
</div>

<!-- Category Filter Pills -->
<div class="card">
  <div class="card-body" style="padding: 1rem 1.25rem;">
    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
      <span style="font-size: 0.85rem; font-weight: 600; color: var(--color-text-muted);">Category:</span>
      <a href="gallery.php?category=all" class="btn btn-sm <?= $catFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">All (<?= count($items) ?>)</a>
      <?php foreach ($validCategories as $c): ?>
        <a href="gallery.php?category=<?= urlencode($c) ?>" class="btn btn-sm <?= $catFilter === $c ? 'btn-primary' : 'btn-secondary' ?>">
          <?= e($c) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Gallery Grid / Table -->
<div class="card">
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Preview</th>
          <th>Category</th>
          <th>Alt Text / Caption</th>
          <th>Sort Order</th>
          <th>Added</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
          <tr>
            <td colspan="6" style="text-align: center; color: var(--color-text-muted); padding: 2.5rem;">
              No portfolio photos found in this category.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <img src="../<?= e($item['image_url']) ?>" alt="<?= e($item['alt_text']) ?>" class="table-thumb" style="width: 64px; height: 64px; border-radius: var(--radius-md);" onerror="this.src='../assets/images/hero.jpg'">
              </td>
              <td>
                <span class="status-badge badge-accepted"><?= e($item['category']) ?></span>
              </td>
              <td>
                <div style="font-weight: 500;"><?= !empty($item['alt_text']) ? e($item['alt_text']) : '<span style="color: var(--color-text-light);">No caption</span>' ?></div>
                <div style="font-size: 0.75rem; color: var(--color-text-muted);"><?= e($item['image_url']) ?></div>
              </td>
              <td><?= (int)$item['sort_order'] ?></td>
              <td style="font-size: 0.8rem; color: var(--color-text-muted);">
                <?= e(date('d M Y', strtotime($item['created_at']))) ?>
              </td>
              <td style="text-align: right;">
                <div class="btn-group">
                  <button type="button" class="btn btn-secondary btn-sm" data-modal-open="modal-edit-photo-<?= $item['id'] ?>">
                    Edit
                  </button>
                  <form action="gallery.php" method="POST" style="margin: 0;" onsubmit="return confirm('Permanently remove this portfolio image?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </div>

                <!-- Edit Photo Modal -->
                <div class="modal-backdrop" id="modal-edit-photo-<?= $item['id'] ?>">
                  <div class="modal-card" style="text-align: left;">
                    <div class="modal-header">
                      <h3 style="font-family: var(--font-serif); font-size: 1.25rem;">Edit Photo Details</h3>
                      <button type="button" class="btn-icon" data-modal-close style="background: none; border: none; cursor: pointer; font-size: 1.25rem;">&times;</button>
                    </div>
                    <form action="gallery.php" method="POST">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= $item['id'] ?>">

                      <div class="modal-body">
                        <div style="text-align: center; margin-bottom: 1.25rem;">
                          <img src="../<?= e($item['image_url']) ?>" alt="Preview" style="max-height: 160px; border-radius: var(--radius-md); object-fit: cover; border: 1px solid var(--color-border);" onerror="this.src='../assets/images/hero.jpg'">
                        </div>

                        <div class="form-group">
                          <label class="form-label" for="edit-cat-<?= $item['id'] ?>">Category <span class="required">*</span></label>
                          <select name="category" id="edit-cat-<?= $item['id'] ?>" class="form-control" required>
                            <?php foreach ($validCategories as $c): ?>
                              <option value="<?= e($c) ?>" <?= $item['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>

                        <div class="form-group">
                          <label class="form-label" for="edit-alt-<?= $item['id'] ?>">Caption / Alt Description</label>
                          <input type="text" name="alt_text" id="edit-alt-<?= $item['id'] ?>" class="form-control" value="<?= e($item['alt_text']) ?>">
                        </div>

                        <div class="form-group">
                          <label class="form-label" for="edit-sort-<?= $item['id'] ?>">Sort Order</label>
                          <input type="number" name="sort_order" id="edit-sort-<?= $item['id'] ?>" class="form-control" value="<?= (int)$item['sort_order'] ?>">
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

<!-- Upload New Photo Modal -->
<div class="modal-backdrop" id="modal-add-photo">
  <div class="modal-card" style="text-align: left;">
    <div class="modal-header">
      <h3 style="font-family: var(--font-serif); font-size: 1.25rem;">Upload Portfolio Photo</h3>
      <button type="button" class="btn-icon" data-modal-close style="background: none; border: none; cursor: pointer; font-size: 1.25rem;">&times;</button>
    </div>
    <form action="gallery.php" method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="new-photo-file">Select Image File <span class="required">*</span></label>
          <div class="upload-preview-wrapper">
            <img src="../assets/images/hero.jpg" alt="Preview" id="preview-new-gallery" class="upload-preview-img">
            <div>
              <input type="file" name="image" id="new-photo-file" class="form-control" accept="image/jpeg,image/png,image/webp" data-preview="#preview-new-gallery" required>
              <span class="form-text">JPEG, PNG, WebP format (Max 5MB). High resolution recommended.</span>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="new-category">Category <span class="required">*</span></label>
          <select name="category" id="new-category" class="form-control" required>
            <?php foreach ($validCategories as $c): ?>
              <option value="<?= e($c) ?>"><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="new-alt">Caption / Alt Description</label>
          <input type="text" name="alt_text" id="new-alt" class="form-control" placeholder="e.g. Royal Rajasthani bridal look with smokey eye">
        </div>

        <div class="form-group">
          <label class="form-label" for="new-sort">Sort Order</label>
          <input type="number" name="sort_order" id="new-sort" class="form-control" value="0">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Upload Photo</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

