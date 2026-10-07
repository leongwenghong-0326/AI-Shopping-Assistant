<?php
/**
 * General helper functions.
 */

declare(strict_types=1);

/**
 * Escape for HTML output.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect and exit.
 */
function redirect(string $path): never
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        header('Location: ' . $path);
        exit;
    }

    $base = rtrim(APP_URL, '/');
    $path = '/' . ltrim($path, '/');
    header('Location: ' . $base . $path);
    exit;
}

/**
 * Flash message helpers.
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['_flash'])) {
        return null;
    }
    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $flash;
}

/**
 * JSON response helper for APIs.
 */
function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Read JSON body from request.
 */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Normalize barcode (digits and common barcode characters).
 */
function normalize_barcode(string $barcode): string
{
    $barcode = trim($barcode);
    $barcode = preg_replace('/\s+/', '', $barcode) ?? '';
    return $barcode;
}

/**
 * Validate barcode characters.
 */
function is_valid_barcode(string $barcode): bool
{
    if ($barcode === '' || strlen($barcode) > 64) {
        return false;
    }
    return (bool) preg_match('/^[A-Za-z0-9\-\.\/\+\s]+$/', $barcode);
}

/**
 * Normalize SKU.
 */
function normalize_sku(string $sku): string
{
    return strtoupper(trim($sku));
}

/**
 * Validate SKU.
 */
function is_valid_sku(string $sku): bool
{
    return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_]{1,39}$/', $sku);
}

/**
 * Normalize text for fuzzy matching.
 */
function normalize_match_text(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    return trim($text);
}

/**
 * Format price for display (MYR).
 */
function format_price(float|string $price): string
{
    return 'RM ' . number_format((float) $price, 2);
}

/**
 * Mask API key for display (show last 4 chars).
 */
function mask_secret(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $len = strlen($value);
    if ($len <= 4) {
        return str_repeat('*', $len);
    }
    return str_repeat('*', max(12, $len - 4)) . substr($value, -4);
}

/**
 * Check whether the application appears installed.
 */
function app_is_installed(): bool
{
    $flag = ROOT_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'installed.lock';
    if (!is_file($flag)) {
        return false;
    }

    try {
        $pdo = db();
        $pdo->query('SELECT 1 FROM users LIMIT 1');
        $pdo->query('SELECT 1 FROM products LIMIT 1');
        $pdo->query('SELECT 1 FROM settings LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Require installation before using the app.
 */
function require_installed(): void
{
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === 'install.php') {
        return;
    }

    if (!app_is_installed()) {
        redirect('/install.php');
    }
}

/**
 * Safe absolute URL helper.
 */
function url(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Public product image URL or placeholder.
 */
function product_image_url(?string $imagePath): string
{
    if ($imagePath === null || $imagePath === '') {
        return url('assets/img/product-placeholder.svg');
    }

    // Stored as relative path like uploads/products/file.jpg
    return url(ltrim($imagePath, '/'));
}
