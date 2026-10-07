<?php
declare(strict_types=1);

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/init.php';
}

$pageTitle = $pageTitle ?? APP_NAME;
$bodyClass = $bodyClass ?? '';
$hideNav = $hideNav ?? false;
$user = current_user();
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b3d2e">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="<?= e($bodyClass) ?>">
<?php if (!$hideNav): ?>
<nav class="navbar navbar-expand-lg app-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= e(url('index.php')) ?>">
            <span class="brand-mark"><i class="fa-solid fa-bag-shopping"></i></span>
            <span class="brand-text">AI Smart Shopping</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php')) ?>">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('products.php')) ?>">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('scan.php')) ?>"><i class="fa-solid fa-camera me-1"></i>Scanner</a></li>
                <?php if (is_admin()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('admin/products.php')) ?>">Manage Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('admin/settings.php')) ?>">AI Settings</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('logout.php')) ?>">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link btn-nav-login" href="<?= e(url('login.php')) ?>">Admin Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="app-main">
    <?php if ($flash): ?>
        <div class="container mt-3">
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>
