<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Admin: Business Settings Configuration
 * Manage salon contact information, Google Maps details, social links, and hours.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/validation.php';

require_admin();

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Security token invalid or expired.');
        safe_redirect('settings.php');
    }

    $name       = clean_input($_POST['name'] ?? '');
    $phone      = clean_input($_POST['phone'] ?? '');
    $phoneTel   = clean_input($_POST['phone_tel'] ?? '');
    $whatsapp   = clean_input($_POST['whatsapp'] ?? '');
    $rating     = (float)($_POST['rating'] ?? 4.52);
    $hours      = clean_input($_POST['hours'] ?? '');
    $address    = clean_input($_POST['address'] ?? '');
    $mapQuery   = clean_input($_POST['map_query'] ?? '');
    $instagram  = clean_input($_POST['instagram'] ?? '');
    $facebook   = clean_input($_POST['facebook'] ?? '');

    $errors = [];
    if (empty($name)) {
        $errors[] = 'Business name is required.';
    }
    if (empty($phone)) {
        $errors[] = 'Display phone is required.';
    }
    if (empty($whatsapp)) {
        $errors[] = 'WhatsApp number is required.';
    }

    if (empty($errors)) {
        // Handle Salon Logo upload if provided
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = handle_image_upload($_FILES['logo'], 'uploads/logo');
            if ($uploadResult['success']) {
                $uploadedLogoPath = __DIR__ . '/../' . $uploadResult['path'];
                $targetLogoPath = __DIR__ . '/../assets/images/logo.png';
                @copy($uploadedLogoPath, $targetLogoPath);
            } else {
                $errors[] = 'Logo upload error: ' . $uploadResult['error'];
            }
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE business_settings
                SET name = :name, phone = :phone, phone_tel = :phone_tel,
                    whatsapp = :whatsapp, rating = :rating, hours = :hours,
                    address = :address, map_query = :map_query,
                    instagram = :instagram, facebook = :facebook,
                    updated_at = NOW()
                WHERE id = 1
            ");
            $stmt->execute([
                ':name'       => $name,
                ':phone'      => $phone,
                ':phone_tel'  => $phoneTel,
                ':whatsapp'   => $whatsapp,
                ':rating'     => $rating,
                ':hours'      => $hours,
                ':address'    => $address,
                ':map_query'  => $mapQuery,
                ':instagram'  => $instagram,
                ':facebook'   => $facebook,
            ]);
            set_flash('success', 'Business settings updated successfully.');
        } catch (Throwable $e) {
            error_log('Error saving business settings: ' . $e->getMessage());
            set_flash('error', 'Failed to save business settings.');
        }
    } else {
        set_flash('error', implode(' ', $errors));
    }
    safe_redirect('settings.php');
}

$settings = get_business_settings();

$pageTitle = 'Business Settings';
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- Settings Form Card -->
<div class="card" style="max-width: 860px;">
  <div class="card-header">
    <h2 class="card-title">Business Profile & Contact Settings</h2>
  </div>
  <div class="card-body">
    <form action="settings.php" method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <h3 style="font-family: var(--font-serif); font-size: 1.15rem; color: var(--color-primary); margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
        Salon Logo / Brand Icon
      </h3>

      <div style="display: flex; gap: 1.5rem; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <?php if (file_exists(__DIR__ . '/../assets/images/logo.png')): ?>
          <div style="text-align: center;">
            <img src="../assets/images/logo.png?v=<?= time() ?>" alt="Current Logo" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 2px solid var(--color-border); display: block; margin: 0 auto;">
            <span style="font-size: 0.72rem; color: var(--color-muted); display: block; margin-top: 0.25rem;">Current Logo</span>
          </div>
        <?php endif; ?>
        <div class="form-group" style="flex: 1; min-width: 250px;">
          <label class="form-label" for="biz-logo">Upload New Logo (PNG, JPG, WEBP)</label>
          <input type="file" name="logo" id="biz-logo" class="form-control" accept="image/png,image/jpeg,image/webp">
          <small class="form-hint" style="font-size: 0.75rem; color: var(--color-muted); display: block; margin-top: 0.25rem;">
            Uploading a new logo instantly updates the website header, favicon, and admin branding across the entire site.
          </small>
        </div>
      </div>

      <h3 style="font-family: var(--font-serif); font-size: 1.15rem; color: var(--color-primary); margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
        General Information
      </h3>

      <div class="form-grid">
        <div class="form-group">
          <label class="form-label" for="biz-name">Business Name <span class="required">*</span></label>
          <input type="text" name="name" id="biz-name" class="form-control" value="<?= e($settings['name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="biz-hours">Operating Hours <span class="required">*</span></label>
          <input type="text" name="hours" id="biz-hours" class="form-control" value="<?= e($settings['hours'] ?? '') ?>" placeholder="e.g. Open 24 hours / Mon-Sun 9AM - 8PM" required>
        </div>
      </div>

      <div class="form-grid">
        <div class="form-group">
          <label class="form-label" for="biz-rating">Customer Rating (e.g. 4.52)</label>
          <input type="number" step="0.01" min="1" max="5" name="rating" id="biz-rating" class="form-control" value="<?= e((string)($settings['rating'] ?? '4.52')) ?>">
        </div>
      </div>

      <h3 style="font-family: var(--font-serif); font-size: 1.15rem; color: var(--color-primary); margin-top: 1.5rem; margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
        Contact Numbers & WhatsApp
      </h3>

      <div class="form-grid">
        <div class="form-group">
          <label class="form-label" for="biz-phone">Display Phone <span class="required">*</span></label>
          <input type="text" name="phone" id="biz-phone" class="form-control" value="<?= e($settings['phone'] ?? '') ?>" placeholder="+91 72198 84594" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="biz-phone-tel">Tel Link Format <span class="required">*</span></label>
          <input type="text" name="phone_tel" id="biz-phone-tel" class="form-control" value="<?= e($settings['phone_tel'] ?? '') ?>" placeholder="+917219884594" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="biz-wa">WhatsApp Direct Number (Digits only, including country code) <span class="required">*</span></label>
        <input type="text" name="whatsapp" id="biz-wa" class="form-control" value="<?= e($settings['whatsapp'] ?? '') ?>" placeholder="917219884594" required>
        <span class="form-text">Used to generate all one-click WhatsApp appointment links.</span>
      </div>

      <h3 style="font-family: var(--font-serif); font-size: 1.15rem; color: var(--color-primary); margin-top: 1.5rem; margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
        Studio Location & Google Maps
      </h3>

      <div class="form-group">
        <label class="form-label" for="biz-address">Physical Studio Address <span class="required">*</span></label>
        <textarea name="address" id="biz-address" class="form-control" rows="2" required><?= e($settings['address'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="biz-map">Google Maps Query / Landmark Search</label>
        <input type="text" name="map_query" id="biz-map" class="form-control" value="<?= e($settings['map_query'] ?? '') ?>">
        <span class="form-text">Used for the "Get Directions" link on the contact and footer pages.</span>
      </div>

      <h3 style="font-family: var(--font-serif); font-size: 1.15rem; color: var(--color-primary); margin-top: 1.5rem; margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
        Social Media Links
      </h3>

      <div class="form-grid">
        <div class="form-group">
          <label class="form-label" for="biz-insta">Instagram Profile URL</label>
          <input type="url" name="instagram" id="biz-insta" class="form-control" value="<?= e($settings['instagram'] ?? 'https://www.instagram.com/moms_beauty_touch') ?>" placeholder="https://www.instagram.com/moms_beauty_touch">
        </div>

        <div class="form-group">
          <label class="form-label" for="biz-fb">Facebook Page URL</label>
          <input type="url" name="facebook" id="biz-fb" class="form-control" value="<?= e($settings['facebook'] ?? '') ?>" placeholder="https://facebook.com/momsbeautytouch">
        </div>
      </div>

      <div style="margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end;">
        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem;">
          Save Settings
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

