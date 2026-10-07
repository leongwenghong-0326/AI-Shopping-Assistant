<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Scanner · ' . APP_NAME;
$bodyClass = 'scan-page';
$extraScripts = [
    url('assets/js/vendor/zxing.min.js'),
    url('assets/js/scan.js'),
];

require __DIR__ . '/includes/header.php';
?>

<section class="scanner-shell">
    <div class="container-fluid px-0 px-sm-3">
        <div class="scanner-layout">
            <div class="scanner-top">
                <a class="back-link" href="<?= e(url('index.php')) ?>"><i class="fa-solid fa-arrow-left"></i></a>
                <div>
                    <h1 class="scanner-title">Product Scanner</h1>
                    <p class="scanner-sub">Point your camera at a barcode or capture a photo for AI recognition.</p>
                </div>
            </div>

            <div class="camera-stage" id="cameraStage">
                <video id="cameraPreview" playsinline muted autoplay></video>
                <canvas id="captureCanvas" class="d-none"></canvas>
                <img id="capturedPreview" class="captured-preview d-none" alt="Captured product">
                <div class="scan-frame" aria-hidden="true"></div>
                <div class="scan-status" id="scanStatus">
                    <i class="fa-solid fa-circle-notch fa-spin me-2"></i>
                    <span id="scanStatusText">Starting camera...</span>
                </div>
            </div>

            <div class="scanner-controls">
                <button type="button" class="btn btn-control" id="btnSwitchCamera" title="Switch camera">
                    <i class="fa-solid fa-camera-rotate"></i>
                    <span>Switch</span>
                </button>
                <button type="button" class="btn btn-capture" id="btnCapture" title="Capture for AI">
                    <i class="fa-solid fa-camera"></i>
                    <span>Capture</span>
                </button>
                <button type="button" class="btn btn-control" id="btnRetake" title="Retake" disabled>
                    <i class="fa-solid fa-rotate-left"></i>
                    <span>Retake</span>
                </button>
            </div>

            <div id="barcodeBanner" class="barcode-banner d-none">
                <div class="fw-semibold">Barcode detected</div>
                <code id="barcodeValue"></code>
            </div>

            <div id="resultPanel" class="result-panel d-none"></div>

            <div id="errorPanel" class="error-panel d-none"></div>

            <div class="scanner-help">
                <div><i class="fa-solid fa-barcode me-2"></i>Barcode lookup runs automatically.</div>
                <div><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Use Capture if no barcode is found.</div>
            </div>
        </div>
    </div>
</section>

<script>
window.AISA_SCAN = {
    barcodeApi: <?= json_encode(url('api/barcode.php'), JSON_UNESCAPED_SLASHES) ?>,
    recognizeApi: <?= json_encode(url('api/recognize.php'), JSON_UNESCAPED_SLASHES) ?>,
    placeholderImage: <?= json_encode(url('assets/img/product-placeholder.svg'), JSON_UNESCAPED_SLASHES) ?>,
    appUrl: <?= json_encode(rtrim(APP_URL, '/'), JSON_UNESCAPED_SLASHES) ?>
};
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
