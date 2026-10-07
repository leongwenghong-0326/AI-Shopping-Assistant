<?php
/**
 * One-time installer: creates database, tables, admin, settings, sample products.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/db.php';

$lockFile = __DIR__ . '/includes/installed.lock';
$messages = [];
$errors = [];
$done = false;

// Lock file alone is not enough (common after uploading a local installed.lock to cPanel).
$reallyInstalled = app_is_installed();
if (is_file($lockFile) && !$reallyInstalled) {
    @unlink($lockFile);
}
$alreadyInstalled = $reallyInstalled;

$form = [
    'db_host' => DB_HOST,
    'db_name' => DB_NAME,
    'db_user' => DB_USER,
    'db_pass' => DB_PASS,
];

/**
 * Persist DB credentials into includes/config.php.
 */
function install_save_db_config(string $host, string $name, string $user, string $pass): void
{
    $configPath = __DIR__ . '/includes/config.php';
    $contents = file_get_contents($configPath);
    if ($contents === false) {
        throw new RuntimeException('Unable to read includes/config.php');
    }

    $replacements = [
        'DB_HOST' => $host,
        'DB_NAME' => $name,
        'DB_USER' => $user,
        'DB_PASS' => $pass,
    ];

    foreach ($replacements as $const => $value) {
        $escaped = str_replace(['\\', '\''], ['\\\\', '\\\''], $value);
        $pattern = "/define\(\s*'" . $const . "'\s*,\s*'[^']*'\s*\)/";
        $replacement = "define('" . $const . "', '" . $escaped . "')";
        $updated = preg_replace($pattern, $replacement, $contents, 1, $count);
        if ($updated === null || $count !== 1) {
            throw new RuntimeException('Unable to update ' . $const . ' in config.php');
        }
        $contents = $updated;
    }

    if (file_put_contents($configPath, $contents) === false) {
        throw new RuntimeException('Unable to write includes/config.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled) {
    $form['db_host'] = trim((string) ($_POST['db_host'] ?? DB_HOST));
    $form['db_name'] = trim((string) ($_POST['db_name'] ?? DB_NAME));
    $form['db_user'] = trim((string) ($_POST['db_user'] ?? DB_USER));
    $form['db_pass'] = (string) ($_POST['db_pass'] ?? '');

    if ($form['db_host'] === '' || $form['db_name'] === '' || $form['db_user'] === '') {
        $errors[] = 'Database host, name, and user are required.';
    } elseif (!preg_match('/^[A-Za-z0-9_\- ]+$/', $form['db_name'])) {
        $errors[] = 'Database name may only contain letters, numbers, underscores, hyphens, and spaces.';
    } else {
        try {
            // Connect first (before writing config) so a bad password cannot break config.php
            $pdoOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $dbName = str_replace('`', '``', $form['db_name']);

            // Login first, then USE database (supports cPanel names that contain spaces)
            $pdo = new PDO(
                sprintf('mysql:host=%s;charset=%s', $form['db_host'], DB_CHARSET),
                $form['db_user'],
                $form['db_pass'],
                $pdoOptions
            );
            try {
                $pdo->exec(
                    "CREATE DATABASE IF NOT EXISTS `{$dbName}`
                     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );
            } catch (Throwable $createError) {
                // Ignore — cPanel DBs are usually pre-created.
            }
            $pdo->exec("USE `{$dbName}`");
            $messages[] = 'Database: Ready';

            install_save_db_config(
                $form['db_host'],
                $form['db_name'],
                $form['db_user'],
                $form['db_pass']
            );

            $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
            if ($schema === false) {
                throw new RuntimeException('Unable to read schema.sql');
            }
            $pdo->exec($schema);
            $messages[] = 'Tables: Ready';

            $hash = password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    password_hash = VALUES(password_hash),
                    role = VALUES(role)'
            );
            $stmt->execute([
                DEFAULT_ADMIN_NAME,
                DEFAULT_ADMIN_EMAIL,
                $hash,
                'admin',
            ]);
            $messages[] = 'Default Admin: Created';

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
            $messages[] = 'Default Settings: Created';

            foreach ([UPLOAD_PRODUCTS_PATH, UPLOAD_CACHE_PATH] as $dir) {
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
            }

            $messages[] = 'Products: Empty catalog ready (add your own in Admin)';

            file_put_contents(
                $lockFile,
                "installed_at=" . date('c') . "\napp_version=" . APP_VERSION . "\n"
            );

            $done = true;
            $alreadyInstalled = true;
            $messages[] = 'Installation Complete';
            $messages[] = 'Saved DB settings to includes/config.php';
        } catch (Throwable $e) {
            error_log('Install failed: ' . $e->getMessage());
            $detail = $e->getMessage();
            if (str_contains($detail, 'Access denied')) {
                $errors[] = 'MySQL rejected this username/password. On cPanel, open MySQL Databases and check: (1) DB name is exact, (2) DB user is exact, (3) user is added to that database with ALL PRIVILEGES, (4) password matches. Then try again.';
            } elseif (str_contains($detail, 'Unknown database')) {
                $errors[] = 'Database name not found. Copy the exact database name from cPanel → MySQL Databases.';
            } elseif (str_contains($detail, 'Connection refused') || str_contains($detail, 'actively refused')) {
                $errors[] = 'Cannot reach MySQL. Keep DB Host as localhost on most cPanel hosts.';
            } else {
                $errors[] = 'Installation failed: ' . $detail;
            }
        }
    }
}

$pageTitle = 'Install · ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Outfit:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(rtrim(APP_URL, '/') . '/assets/css/app.css') ?>" rel="stylesheet">
</head>
<body class="install-page">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="install-card">
                <div class="text-center mb-4">
                    <div class="brand-mark mx-auto mb-3"><i class="fa-solid fa-bag-shopping"></i></div>
                    <h1 class="display-title">Installation</h1>
                    <p class="text-muted mb-0"><?= e(APP_NAME) ?></p>
                </div>

                <?php if ($errors): ?>
                    <?php foreach ($errors as $error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($alreadyInstalled && !$done): ?>
                    <div class="alert alert-success">
                        The application is already installed.
                    </div>
                    <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                        <a class="btn btn-primary btn-lg" href="<?= e(rtrim(APP_URL, '/') . '/index.php') ?>">Open Application</a>
                        <a class="btn btn-outline-primary btn-lg" href="<?= e(rtrim(APP_URL, '/') . '/login.php') ?>">Admin Login</a>
                    </div>
                <?php elseif ($done): ?>
                    <div class="alert alert-success">
                        <strong>Installation Complete</strong>
                    </div>
                    <ul class="list-group mb-4">
                        <?php foreach ($messages as $msg): ?>
                            <li class="list-group-item d-flex align-items-center gap-2">
                                <i class="fa-solid fa-circle-check text-success"></i>
                                <span><?= e($msg) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="credential-box mb-4">
                        <div class="fw-semibold mb-2">Default Administrator</div>
                        <div>Email: <code><?= e(DEFAULT_ADMIN_EMAIL) ?></code></div>
                        <div>Password: <code><?= e(DEFAULT_ADMIN_PASSWORD) ?></code></div>
                        <div class="small text-warning mt-2 mb-0">
                            Change this password immediately after your first login.
                        </div>
                    </div>
                    <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                        <a class="btn btn-primary btn-lg" href="<?= e(rtrim(APP_URL, '/') . '/index.php') ?>">Open Application</a>
                        <a class="btn btn-outline-primary btn-lg" href="<?= e(rtrim(APP_URL, '/') . '/login.php') ?>">Admin Login</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        Enter your <strong>cPanel MySQL</strong> details, then run installation.
                    </div>
                    <div class="alert alert-light border small">
                        <strong>cPanel checklist</strong>
                        <ol class="mb-0 ps-3">
                            <li>MySQL Databases → confirm database name (copy/paste exactly)</li>
                            <li>Confirm MySQL username (copy/paste exactly)</li>
                            <li>In <em>Add User To Database</em>, assign this user to this database with <strong>ALL PRIVILEGES</strong></li>
                            <li>If unsure about the password, use <em>Change Password</em> on that MySQL user, then paste the new password here</li>
                        </ol>
                    </div>
                    <form method="post" class="row g-3" autocomplete="off">
                        <div class="col-md-6">
                            <label class="form-label" for="db_host">DB Host</label>
                            <input class="form-control" id="db_host" name="db_host" required
                                   value="<?= e($form['db_host'] !== '' ? $form['db_host'] : 'localhost') ?>">
                            <div class="form-text">Usually <code>localhost</code> on cPanel</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="db_name">Database Name</label>
                            <input class="form-control" id="db_name" name="db_name" required
                                   value="<?= e($form['db_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="db_user">DB User</label>
                            <input class="form-control" id="db_user" name="db_user" required
                                   value="<?= e($form['db_user']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="db_pass">DB Password</label>
                            <input class="form-control" type="password" id="db_pass" name="db_pass"
                                   value="" required
                                   placeholder="Paste MySQL password again" autofocus autocomplete="new-password">
                            <div class="form-text">Paste fresh each time (special characters like <code>&amp; # ^</code> are supported).</div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fa-solid fa-database me-2"></i>Run Installation
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
</body>
</html>
