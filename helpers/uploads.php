<?php
// ============================================================
// FILE: helpers/uploads.php
// PURPOSE: Safely handles image uploads (plants, avatars, etc.)
// ============================================================

require_once __DIR__ . '/../config/app.php';

function handle_image_upload(array $file, string $subfolder = 'products', int $maxSizeBytes = 3145728): array {
    $result = [
        'success'  => false,
        'filename' => '',
        'filepath' => '',
        'error'    => ''
    ];

    // Step 1: Check for basic upload errors
    if (!isset($file['error']) || is_array($file['error'])) {
        $result['error'] = 'Invalid file upload parameters.';
        return $result;
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        $result['error'] = 'No file was uploaded.';
        return $result;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'File upload failed with error code: ' . $file['error'];
        return $result;
    }

    // Step 2: Validate file size (default 3MB)
    if ($file['size'] > $maxSizeBytes) {
        $maxMb = round($maxSizeBytes / (1024 * 1024), 1);
        $result['error'] = 'File size exceeds maximum allowed limit of ' . $maxMb . 'MB.';
        return $result;
    }

    // Step 3: Check MIME type using PHP Fileinfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedExt = '';
    if ($mimeType === 'image/jpeg') {
        $allowedExt = 'jpg';
    } elseif ($mimeType === 'image/png') {
        $allowedExt = 'png';
    } elseif ($mimeType === 'image/webp') {
        $allowedExt = 'webp';
    } elseif ($mimeType === 'image/gif') {
        $allowedExt = 'gif';
    } else {
        $result['error'] = 'Invalid image format. Only JPG, PNG, WEBP, and GIF images are allowed.';
        return $result;
    }

    // Step 4: Map subfolder names
    $cleanSub = 'plants';
    $sub = trim($subfolder, '/');
    if ($sub === 'vendors' || $sub === 'nurseries') {
        $cleanSub = 'nurseries';
    } elseif ($sub === 'avatars') {
        $cleanSub = 'avatars';
    } elseif ($sub === 'branding') {
        $cleanSub = 'branding';
    } elseif ($sub === 'categories') {
        $cleanSub = 'categories';
    } else {
        $cleanSub = 'plants';
    }

    // Step 5: Ensure target directory exists
    $targetDir = ROOT_PATH . '/assets/images/' . $cleanSub;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    // Step 6: Generate randomized safe filename
    $randomString = bin2hex(random_bytes(8));
    $randomName   = $cleanSub . '_' . $randomString . '.' . $allowedExt;
    $targetPath   = $targetDir . '/' . $randomName;

    // Step 7: Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $result['error'] = 'Failed to move uploaded file to target directory.';
        return $result;
    }

    $result['success']  = true;
    $result['filename'] = $randomName;
    $result['filepath'] = 'assets/images/' . $cleanSub . '/' . $randomName;
    return $result;
}
