<?php
/**
 * Secure product image upload and temporary AI image helpers.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

/**
 * Validate and store an uploaded product image.
 *
 * @return array{ok:bool, path?:string, error?:string}
 */
function save_product_image(array $file, ?string $oldRelativePath = null): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No image uploaded.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Image upload failed.'];
    }

    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Image must be under 5 MB.'];
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    if (!in_array($mime, UPLOAD_ALLOWED_MIME, true)) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, and WEBP images are allowed.'];
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $ext = $extMap[$mime] ?? null;
    if ($ext === null) {
        return ['ok' => false, 'error' => 'Unsupported image type.'];
    }

    $info = @getimagesize($tmp);
    if ($info === false) {
        return ['ok' => false, 'error' => 'File is not a valid image.'];
    }

    [$width, $height] = $info;
    if ($width < 1 || $height < 1 || $width > UPLOAD_MAX_WIDTH || $height > UPLOAD_MAX_HEIGHT) {
        return ['ok' => false, 'error' => 'Image dimensions are invalid or too large.'];
    }

    if (!is_dir(UPLOAD_PRODUCTS_PATH)) {
        mkdir(UPLOAD_PRODUCTS_PATH, 0755, true);
    }

    $filename = 'p' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = UPLOAD_PRODUCTS_PATH . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $dest)) {
        return ['ok' => false, 'error' => 'Unable to save image.'];
    }

    @chmod($dest, 0644);

    if ($oldRelativePath) {
        delete_product_image($oldRelativePath);
    }

    return [
        'ok' => true,
        'path' => 'uploads/products/' . $filename,
    ];
}

function delete_product_image(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === '') {
        return;
    }

    $relativePath = str_replace(['\\', '..'], ['/', ''], $relativePath);
    if (!str_starts_with($relativePath, 'uploads/products/')) {
        return;
    }

    $full = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * Decode a data URL / base64 image, resize, save temporarily for AI, return path + mime.
 *
 * @return array{ok:bool, path?:string, mime?:string, error?:string}
 */
function save_temp_capture_image(string $dataUrl): array
{
    if (!preg_match('#^data:(image/(jpeg|png|webp));base64,#i', $dataUrl, $m)) {
        // Also accept raw base64 jpeg
        if (!preg_match('#^[A-Za-z0-9+/=\s]+$#', $dataUrl)) {
            return ['ok' => false, 'error' => 'Invalid image data.'];
        }
        $binary = base64_decode($dataUrl, true);
        $mime = 'image/jpeg';
    } else {
        $mime = strtolower($m[1]);
        $binary = base64_decode(substr($dataUrl, strlen($m[0])), true);
    }

    if ($binary === false || strlen($binary) < 32) {
        return ['ok' => false, 'error' => 'Unable to decode image.'];
    }

    if (strlen($binary) > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Captured image is too large.'];
    }

    if (!is_dir(UPLOAD_CACHE_PATH)) {
        mkdir(UPLOAD_CACHE_PATH, 0755, true);
    }

    $src = @imagecreatefromstring($binary);
    if ($src === false) {
        return ['ok' => false, 'error' => 'Invalid captured image.'];
    }

    $width = imagesx($src);
    $height = imagesy($src);
    $maxW = AI_IMAGE_MAX_WIDTH;

    if ($width > $maxW) {
        $newW = $maxW;
        $newH = (int) max(1, round($height * ($maxW / $width)));
        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($src);
        $src = $dst;
    }

    $filename = 'cap-' . bin2hex(random_bytes(8)) . '.jpg';
    $path = UPLOAD_CACHE_PATH . DIRECTORY_SEPARATOR . $filename;

    if (!imagejpeg($src, $path, AI_IMAGE_JPEG_QUALITY)) {
        imagedestroy($src);
        return ['ok' => false, 'error' => 'Unable to process image.'];
    }
    imagedestroy($src);

    return [
        'ok' => true,
        'path' => $path,
        'mime' => 'image/jpeg',
    ];
}

function delete_temp_image(?string $path): void
{
    if ($path === null || $path === '') {
        return;
    }
    $cache = realpath(UPLOAD_CACHE_PATH);
    $real = realpath($path);
    if ($cache && $real && str_starts_with($real, $cache) && is_file($real)) {
        @unlink($real);
    }
}

/**
 * Convert image file to base64 for AI providers.
 */
function image_file_to_base64(string $path): string
{
    $data = file_get_contents($path);
    if ($data === false) {
        throw new RuntimeException('Unable to read image.');
    }
    return base64_encode($data);
}
