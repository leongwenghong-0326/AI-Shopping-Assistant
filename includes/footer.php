<?php
declare(strict_types=1);
?>
</main>
<footer class="app-footer">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <div>
            <strong><?= e(APP_NAME) ?></strong>
            <span class="text-muted"> · Student / retail demo</span>
        </div>
        <div class="small text-muted">
            Camera access works best on HTTPS (or localhost for development).
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($extraScripts)): ?>
    <?php foreach ($extraScripts as $script): ?>
        <script src="<?= e($script) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
