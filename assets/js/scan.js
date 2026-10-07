(function () {
    'use strict';

    var cfg = window.AISA_SCAN || {};
    var video = document.getElementById('cameraPreview');
    var canvas = document.getElementById('captureCanvas');
    var capturedPreview = document.getElementById('capturedPreview');
    var statusEl = document.getElementById('scanStatus');
    var statusText = document.getElementById('scanStatusText');
    var barcodeBanner = document.getElementById('barcodeBanner');
    var barcodeValue = document.getElementById('barcodeValue');
    var resultPanel = document.getElementById('resultPanel');
    var errorPanel = document.getElementById('errorPanel');
    var btnCapture = document.getElementById('btnCapture');
    var btnRetake = document.getElementById('btnRetake');
    var btnSwitch = document.getElementById('btnSwitchCamera');

    if (!video || !canvas) {
        return;
    }

    var stream = null;
    var facingMode = 'environment';
    var scanning = false;
    var busy = false;
    var lastBarcode = '';
    var lastBarcodeAt = 0;
    var codeReader = null;
    var scanTimer = null;

    function setStatus(message, spinning) {
        if (!statusText) return;
        statusText.textContent = message;
        if (statusEl) {
            var icon = statusEl.querySelector('i');
            if (icon) {
                icon.className = spinning
                    ? 'fa-solid fa-circle-notch fa-spin me-2'
                    : 'fa-solid fa-circle-info me-2';
            }
        }
    }

    function showError(message) {
        if (!errorPanel) return;
        errorPanel.classList.remove('d-none');
        errorPanel.innerHTML = '<strong>Unable to continue</strong><div class="mt-1">' + escapeHtml(message) + '</div>';
    }

    function hideError() {
        if (errorPanel) {
            errorPanel.classList.add('d-none');
            errorPanel.innerHTML = '';
        }
    }

    function hideResult() {
        if (resultPanel) {
            resultPanel.classList.add('d-none');
            resultPanel.innerHTML = '';
        }
        if (barcodeBanner) barcodeBanner.classList.add('d-none');
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function productImageUrl(path) {
        if (!path) return cfg.placeholderImage || '';
        if (/^https?:\/\//i.test(path)) return path;
        return (cfg.appUrl || '') + '/' + String(path).replace(/^\/+/, '');
    }

    function renderProduct(product, meta) {
        meta = meta || {};
        hideError();
        if (barcodeBanner) barcodeBanner.classList.add('d-none');

        var confidenceHtml = '';
        if (typeof meta.confidence === 'number' && meta.confidence > 0) {
            confidenceHtml =
                '<div class="confidence-pill"><i class="fa-solid fa-brain"></i> Confidence: ' +
                Math.round(meta.confidence * 100) +
                '%</div>';
        }

        var barcodeHtml = product.barcode
            ? '<div>Barcode: ' + escapeHtml(product.barcode) + '</div>'
            : '';

        resultPanel.innerHTML =
            '<div class="result-image"><img src="' +
            escapeHtml(productImageUrl(product.image)) +
            '" alt="' +
            escapeHtml(product.name) +
            '"></div>' +
            '<div class="result-name">' +
            escapeHtml(product.name) +
            '</div>' +
            '<div class="result-price">RM ' +
            escapeHtml(product.price) +
            '</div>' +
            '<div class="result-meta">' +
            '<div>SKU: ' +
            escapeHtml(product.sku) +
            '</div>' +
            barcodeHtml +
            '</div>' +
            (product.description
                ? '<div class="result-desc">' + escapeHtml(product.description) + '</div>'
                : '') +
            confidenceHtml;

        resultPanel.classList.remove('d-none');
        setStatus('Product found', false);
    }

    async function startCamera() {
        hideError();
        setStatus('Starting camera...', true);

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showError('This browser does not support camera access. Please use a modern mobile browser.');
            setStatus('Camera unavailable', false);
            return;
        }

        stopCamera();

        var constraints = {
            audio: false,
            video: {
                facingMode: { ideal: facingMode },
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        };

        try {
            stream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = stream;
            await video.play();
            video.classList.remove('d-none');
            capturedPreview.classList.add('d-none');
            btnCapture.disabled = false;
            btnRetake.disabled = true;
            setStatus('Camera ready · Looking for barcode...', false);
            startBarcodeLoop();
        } catch (err) {
            var msg = 'Unable to access your camera. Please allow camera permission and try again.';
            if (err && err.name === 'NotFoundError') {
                msg = 'No camera was found on this device.';
            } else if (err && err.name === 'NotAllowedError') {
                msg = 'Camera permission denied. Please allow camera access and reload the page.';
            } else if (location.protocol === 'http:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                msg = 'Camera access usually requires HTTPS on production sites. Localhost is allowed without HTTPS.';
            }
            showError(msg);
            setStatus('Camera unavailable', false);
        }
    }

    function stopCamera() {
        stopBarcodeLoop();
        if (stream) {
            stream.getTracks().forEach(function (track) {
                track.stop();
            });
            stream = null;
        }
        video.srcObject = null;
    }

    function getZXing() {
        return window.ZXing || window.ZXingBrowser || null;
    }

    function getZXingReader() {
        if (codeReader) return codeReader;
        var ZX = getZXing();
        if (!ZX || typeof ZX.BrowserMultiFormatReader !== 'function') {
            return null;
        }
        codeReader = new ZX.BrowserMultiFormatReader();
        return codeReader;
    }

    function startBarcodeLoop() {
        stopBarcodeLoop();
        scanning = true;

        var reader = getZXingReader();
        if (!reader) {
            setStatus('Camera ready · Capture to use AI (barcode library missing)', false);
            return;
        }

        var ZX = getZXing();

        // Preferred continuous decode from active stream
        if (stream && typeof reader.decodeFromStream === 'function') {
            reader.decodeFromStream(stream, video, function (result, err) {
                if (!scanning || busy) return;
                if (result) {
                    var text = typeof result.getText === 'function' ? result.getText() : result.text;
                    if (text) {
                        handleBarcode(text);
                    }
                    return;
                }
                if (err && ZX && err.name !== 'NotFoundException' && !(err instanceof ZX.NotFoundException)) {
                    // Ignore routine scan misses; log unexpected errors only
                }
            }).catch(function () {
                // Fall back to interval decode below
                startIntervalDecode(reader, ZX);
            });
            return;
        }

        startIntervalDecode(reader, ZX);
    }

    function startIntervalDecode(reader, ZX) {
        scanTimer = setInterval(async function () {
            if (!scanning || busy || !stream || video.readyState < 2) return;

            try {
                var w = video.videoWidth;
                var h = video.videoHeight;
                if (!w || !h) return;

                canvas.width = w;
                canvas.height = h;
                var ctx = canvas.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(video, 0, 0, w, h);

                var result = null;
                if (typeof reader.decodeFromCanvas === 'function') {
                    result = await reader.decodeFromCanvas(canvas);
                } else if (typeof reader.decodeFromImageUrl === 'function') {
                    result = await reader.decodeFromImageUrl(canvas.toDataURL('image/jpeg', 0.7));
                }

                if (result) {
                    var text = typeof result.getText === 'function' ? result.getText() : result.text;
                    if (text) {
                        await handleBarcode(text);
                    }
                }
            } catch (e) {
                if (ZX && e && (e.name === 'NotFoundException' || e instanceof ZX.NotFoundException)) {
                    return;
                }
            }
        }, 500);
    }

    function stopBarcodeLoop() {
        scanning = false;
        if (scanTimer) {
            clearInterval(scanTimer);
            scanTimer = null;
        }
        if (codeReader && typeof codeReader.reset === 'function') {
            try {
                codeReader.reset();
            } catch (e) {
                // ignore
            }
        }
        codeReader = null;
    }

    async function handleBarcode(raw) {
        var code = String(raw || '').trim();
        if (!code) return;

        var now = Date.now();
        if (code === lastBarcode && now - lastBarcodeAt < 4000) {
            return;
        }
        lastBarcode = code;
        lastBarcodeAt = now;

        busy = true;
        stopBarcodeLoop();
        hideResult();
        hideError();

        if (barcodeBanner && barcodeValue) {
            barcodeBanner.classList.remove('d-none');
            barcodeValue.textContent = code;
        }

        setStatus('Barcode detected · Looking up product...', true);

        try {
            var response = await fetch(cfg.barcodeApi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ barcode: code })
            });
            var data = await response.json();

            if (data.success && data.found && data.product) {
                renderProduct(data.product, {});
                btnRetake.disabled = false;
                btnCapture.disabled = true;
            } else {
                setStatus('Product not found', false);
                showError(
                    (data && data.message) ||
                        'Barcode detected, but this product is not in the catalog. You can Capture a photo for AI recognition.'
                );
                scanning = true;
                startBarcodeLoop();
            }
        } catch (e) {
            showError('Unable to look up barcode. Please try again.');
            setStatus('Lookup failed', false);
            startBarcodeLoop();
        } finally {
            busy = false;
        }
    }

    function captureFrameDataUrl() {
        var w = video.videoWidth;
        var h = video.videoHeight;
        if (!w || !h) {
            throw new Error('Camera is not ready yet.');
        }

        // Compress for mobile / AI
        var maxW = 1280;
        var scale = w > maxW ? maxW / w : 1;
        var cw = Math.round(w * scale);
        var ch = Math.round(h * scale);
        canvas.width = cw;
        canvas.height = ch;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, cw, ch);
        return canvas.toDataURL('image/jpeg', 0.8);
    }

    async function captureAndRecognize() {
        if (busy) return;
        hideError();
        hideResult();

        var dataUrl;
        try {
            dataUrl = captureFrameDataUrl();
        } catch (e) {
            showError('Camera is not ready. Please wait a moment and try again.');
            return;
        }

        busy = true;
        stopBarcodeLoop();
        btnCapture.disabled = true;
        btnRetake.disabled = false;

        capturedPreview.src = dataUrl;
        capturedPreview.classList.remove('d-none');
        video.classList.add('d-none');

        setStatus('Identifying product...', true);

        try {
            var response = await fetch(cfg.recognizeApi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ image: dataUrl })
            });
            var data = await response.json();

            if (data.success && data.found && data.product) {
                renderProduct(data.product, { confidence: data.confidence || (data.ai && data.ai.confidence) || 0 });
            } else {
                setStatus('AI recognition failed', false);
                showError(
                    (data && data.message) ||
                        'Unable to identify the product. Please try another photo.'
                );
            }
        } catch (e) {
            setStatus('AI recognition failed', false);
            showError('Unable to identify the product. Please try another photo.');
        } finally {
            busy = false;
        }
    }

    async function retake() {
        if (busy) return;
        hideError();
        hideResult();
        lastBarcode = '';
        busy = false;

        // ZXing reset / capture flow can detach the stream and leave a black video.
        // Always fully restart the camera on retake.
        capturedPreview.classList.add('d-none');
        capturedPreview.removeAttribute('src');
        video.classList.remove('d-none');
        btnCapture.disabled = true;
        btnRetake.disabled = true;
        setStatus('Restarting camera...', true);
        await startCamera();
    }

    async function switchCamera() {
        facingMode = facingMode === 'environment' ? 'user' : 'environment';
        await startCamera();
    }

    btnCapture.addEventListener('click', function () {
        captureAndRecognize();
    });
    btnRetake.addEventListener('click', function () {
        retake();
    });
    btnSwitch.addEventListener('click', function () {
        switchCamera();
    });

    window.addEventListener('beforeunload', function () {
        stopCamera();
    });

    startCamera();
})();
