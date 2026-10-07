<?php
/**
 * Example configuration — copy to config.php and fill in your values.
 *
 *   copy includes/config.example.php includes/config.php
 */

declare(strict_types=1);

define('APP_NAME', 'AI Smart Shopping Assistant');
define('APP_VERSION', '1.0.0');

// Local XAMPP example (change for cPanel / production)
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'ai_shopping_assistant');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('SESSION_NAME', 'aisa_session');
define('CSRF_TOKEN_KEY', '_csrf_token');

define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024); // 5 MB
define('UPLOAD_ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/webp']);
define('UPLOAD_ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'webp']);
define('UPLOAD_MAX_WIDTH', 2000);
define('UPLOAD_MAX_HEIGHT', 2000);

define('AI_IMAGE_MAX_WIDTH', 1280);
define('AI_IMAGE_JPEG_QUALITY', 80);
define('AI_REQUEST_TIMEOUT', 45);

define('DEFAULT_ADMIN_EMAIL', 'admin@example.com');
define('DEFAULT_ADMIN_PASSWORD', 'Admin123!');
define('DEFAULT_ADMIN_NAME', 'Administrator');

/**
 * Detect base application URL without hard-coding the domain.
 */
function detect_app_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));

    $dir = preg_replace('#/(admin|api)$#', '', $dir) ?? $dir;
    $dir = rtrim($dir, '/');

    if ($dir === '' || $dir === '.') {
        return $scheme . '://' . $host;
    }

    return $scheme . '://' . $host . $dir;
}

if (!defined('APP_URL')) {
    define('APP_URL', detect_app_url());
}

define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PRODUCTS_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products');
define('UPLOAD_CACHE_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'cache');
define('UPLOAD_PRODUCTS_URL', rtrim(APP_URL, '/') . '/uploads/products');
