<?php
/**
 * CLI helper to run installation without HTTP.
 * Usage: php tools/cli_install.php
 */

declare(strict_types=1);

$_SERVER['HTTPS'] = 'off';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/ai_shopping_assistant/install.php';
$_SERVER['REQUEST_METHOD'] = 'POST';

require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/helpers.php';
require dirname(__DIR__) . '/includes/db.php';

$lockFile = dirname(__DIR__) . '/includes/installed.lock';

try {
    $pdo = db_server();
    $pdo->exec(
        'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', DB_NAME) . '`
         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    $pdo->exec('USE `' . str_replace('`', '``', DB_NAME) . '`');
    echo "Database: Ready\n";

    $schema = file_get_contents(dirname(__DIR__) . '/sql/schema.sql');
    $pdo->exec($schema);
    echo "Tables: Ready\n";

    $hash = password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            password_hash = VALUES(password_hash),
            role = VALUES(role)'
    );
    $stmt->execute([DEFAULT_ADMIN_NAME, DEFAULT_ADMIN_EMAIL, $hash, 'admin']);
    echo "Default Admin: Created\n";

    $defaults = [
        'ai_provider' => 'agnes',
        'agnes_api_url' => 'https://apihub.agnes-ai.com/v1',
        'agnes_api_key' => '',
        'agnes_model' => 'agnes-3.0-flash',
        'agnes_fallback_models' => "agnes-2.5-flash\nagnes-2.0-flash",
        'gemini_api_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'gemini_api_key' => '',
        'gemini_model' => 'gemini-3.8-flash',
        'gemini_fallback_models' => "gemini-3.7-flash\ngemini-3.6-flash\ngemini-3.5-flash",
    ];
    $setStmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($defaults as $key => $value) {
        $setStmt->execute([$key, $value]);
    }
    echo "Default Settings: Created\n";

    foreach ([UPLOAD_PRODUCTS_PATH, UPLOAD_CACHE_PATH] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    echo "Products: Empty catalog ready\n";

    file_put_contents($lockFile, "installed_at=" . date('c') . "\napp_version=" . APP_VERSION . "\n");
    echo "Installation Complete\n";
    echo "Admin: " . DEFAULT_ADMIN_EMAIL . " / " . DEFAULT_ADMIN_PASSWORD . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "INSTALL FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
