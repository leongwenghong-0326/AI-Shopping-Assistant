<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$pageTitle = APP_NAME;
$scanUrl = url('scan.php');
$extraScripts = [url('assets/js/vendor/qrcode.min.js')];

require __DIR__ . '/includes/header.php';
?>

<section class="hero-home">
    <div class="hero-atmosphere"></div>
    <div class="container hero-content">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-6">
                <p class="eyebrow"><i class="fa-solid fa-robot me-2"></i>Retail AI Demo</p>
                <h1 class="hero-brand"><?= e(APP_NAME) ?></h1>
                <p class="hero-lead">
                    Scan a product and instantly check its price and information.
                    No account required — open the scanner with your phone.
                </p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a class="btn btn-primary btn-lg" href="<?= e($scanUrl) ?>">
                        <i class="fa-solid fa-camera me-2"></i>Open Scanner
                    </a>
                    <a class="btn btn-outline-light btn-lg" href="<?= e(url('products.php')) ?>">
                        <i class="fa-solid fa-store me-2"></i>View Catalog
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="qr-panel">
                    <h2 class="h4 mb-2">Scan the QR code with your phone</h2>
                    <p class="text-muted mb-3">Point your camera at a product to check its price and information.</p>
                    <div id="qrcode" class="qrcode-box" aria-label="Customer scanner QR code"></div>
                    <div class="scan-url-box mt-3">
                        <div class="small text-muted mb-1">Customer scan URL</div>
                        <code id="scanUrlText"><?= e($scanUrl) ?></code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (is_admin()): ?>
<section class="container py-4">
    <div class="admin-quick">
        <h2 class="h5 mb-3"><i class="fa-solid fa-gauge-high me-2"></i>Admin shortcuts</h2>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-soft" href="<?= e(url('products.php')) ?>"><i class="fa-solid fa-boxes-stacked me-2"></i>Product Catalog</a>
            <a class="btn btn-soft" href="<?= e(url('admin/products.php')) ?>"><i class="fa-solid fa-pen-to-square me-2"></i>Manage Products</a>
            <a class="btn btn-soft" href="<?= e(url('admin/settings.php')) ?>"><i class="fa-solid fa-sliders me-2"></i>AI Settings</a>
            <a class="btn btn-soft" href="<?= e(url('logout.php')) ?>"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="container pb-5">
    <div class="row g-3 feature-row">
        <div class="col-md-4">
            <div class="feature-block">
                <i class="fa-solid fa-barcode"></i>
                <h3>Barcode Recognition</h3>
                <p>Scan EAN, UPC, Code 128 and more, then look up the official store price.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-block">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
                <h3>AI Image Recognition</h3>
                <p>Capture a product photo when no barcode is available and match it to the catalog.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-block">
                <i class="fa-solid fa-shield-halved"></i>
                <h3>Secure Admin Control</h3>
                <p>Manage products, images, barcodes, and AI providers without exposing API keys.</p>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('qrcode');
    var url = <?= json_encode($scanUrl, JSON_UNESCAPED_SLASHES) ?>;
    if (el && typeof QRCode !== 'undefined') {
        new QRCode(el, {
            text: url,
            width: 220,
            height: 220,
            correctLevel: QRCode.CorrectLevel.M
        });
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
