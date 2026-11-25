<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Secure Image Upload Service
 * PHP 8.3+ | XAMPP Compatible
 */

// Prevent direct HTTP access
if (php_sapi_name() !== 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

/**
 * Handles secure image upload with strict MIME, size, dimension and path validation.
 *
 * @param array<string, mixed> $file PHP $_FILES entry
 * @param string $destinationDir Subdirectory path inside uploads/ (e.g. 'services' or 'gallery')
 * @param int $maxBytes Maximum file size in bytes (defaults to 5 MB)
 * @return array{success: bool, path?: string, error?: string}
 */
function handle_image_upload(array $file, string $destinationDir, int $maxBytes = 5242880): array
{
    // 1. Check upload error codes
    $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($errorCode !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'Image exceeds upload_max_filesize limit.',
            UPLOAD_ERR_FORM_SIZE  => 'Image exceeds maximum form limit.',
            UPLOAD_ERR_PARTIAL    => 'Image was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No image file was selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write image to disk.',
            UPLOAD_ERR_EXTENSION  => 'A server extension stopped the upload.',
        ];
        return ['success' => false, 'error' => $messages[$errorCode] ?? 'Image upload error occurred.'];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmpPath)) {
        return ['success' => false, 'error' => 'Invalid upload request.'];
    }

    // 2. Validate maximum file size
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        return ['success' => false, 'error' => 'Image size must be 5 MB or less.'];
    }

    // 3. Inspect actual binary MIME type using finfo_file (do not trust user-provided type)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        return ['success' => false, 'error' => 'Server MIME detection is unavailable.'];
    }

    $mimeType = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimes[$mimeType])) {
        return ['success' => false, 'error' => 'Allowed image formats: JPEG, PNG, or WebP.'];
    }

    $extension = $allowedMimes[$mimeType];

    // 4. Verify image dimensions to ensure file is a valid graphic
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
        return ['success' => false, 'error' => 'Corrupt or unreadable image file.'];
    }

    // Maximum dimension check (10,000px limit to prevent decompression bombs)
    if ($imageInfo[0] > 10000 || $imageInfo[1] > 10000) {
        return ['success' => false, 'error' => 'Image resolution is excessively large.'];
    }

    // 5. Secure target destination path
    $baseUploadDir = realpath(__DIR__ . '/../uploads');
    if (!$baseUploadDir || !is_dir($baseUploadDir)) {
        return ['success' => false, 'error' => 'Uploads root directory is not configured.'];
    }

    $targetFolder = $baseUploadDir . DIRECTORY_SEPARATOR . trim($destinationDir, "\\/ ");
    if (!is_dir($targetFolder)) {
        if (!mkdir($targetFolder, 0755, true) && !is_dir($targetFolder)) {
            return ['success' => false, 'error' => 'Failed to prepare upload destination folder.'];
        }
    }

    // 6. Generate cryptographically random filename (never use original filename)
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $targetPath = $targetFolder . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        return ['success' => false, 'error' => 'Failed to save uploaded image.'];
    }

    // Set safe permissions
    chmod($targetPath, 0644);

    // Relative web path to store in database (e.g., uploads/services/abcdef.jpg)
    $relativePath = 'uploads/' . trim($destinationDir, "\\/ ") . '/' . $filename;

    return [
        'success' => true,
        'path'    => $relativePath,
    ];
}

/**
 * Safely deletes a previously uploaded image file from disk.
 * Strictly prevents path traversal attacks by validating realpath against uploads root.
 *
 * @param string|null $relativePath Relative path stored in database (e.g. 'uploads/services/...')
 * @return bool True if deleted or already absent, False on failure
 */
function delete_uploaded_image(?string $relativePath): bool
{
    if (empty($relativePath)) {
        return true;
    }

    // Only allow deletion if path starts with uploads/ (never delete from assets/images/)
    if (!str_starts_with($relativePath, 'uploads/')) {
        return true;
    }

    $baseUploadDir = realpath(__DIR__ . '/../uploads');
    if (!$baseUploadDir) {
        return false;
    }

    $fullPath = realpath(__DIR__ . '/../' . $relativePath);
    if (!$fullPath || !file_exists($fullPath)) {
        return true; // Already gone
    }

    // Enforce that file resides strictly within the uploads/ tree
    if (!str_starts_with($fullPath, $baseUploadDir . DIRECTORY_SEPARATOR)) {
        error_log('[Upload Security Warning] Attempted deletion outside uploads directory: ' . $relativePath);
        return false;
    }

    return @unlink($fullPath);
}

