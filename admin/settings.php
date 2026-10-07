<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/ai.php';

require_admin();

$settings = ai_settings();
$errors = [];
$testResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $postAction = (string) ($_POST['action'] ?? 'save');

    if ($postAction === 'test_agnes') {
        $testResults['agnes'] = test_ai_connection('agnes');
    } elseif ($postAction === 'test_gemini') {
        $testResults['gemini'] = test_ai_connection('gemini');
    } elseif ($postAction === 'test_both') {
        $testResults['agnes'] = test_ai_connection('agnes');
        $testResults['gemini'] = test_ai_connection('gemini');
    } else {
        $provider = strtolower(trim((string) ($_POST['ai_provider'] ?? 'agnes')));
        if (!in_array($provider, ['agnes', 'gemini'], true)) {
            $errors[] = 'Invalid AI provider.';
            $provider = 'agnes';
        }

        $agnesUrl = trim((string) ($_POST['agnes_api_url'] ?? ''));
        $agnesModel = trim((string) ($_POST['agnes_model'] ?? ''));
        $agnesFallback = trim((string) ($_POST['agnes_fallback_models'] ?? ''));
        $agnesKeyNew = trim((string) ($_POST['agnes_api_key'] ?? ''));

        $geminiUrl = trim((string) ($_POST['gemini_api_url'] ?? ''));
        $geminiModel = trim((string) ($_POST['gemini_model'] ?? ''));
        $geminiFallback = trim((string) ($_POST['gemini_fallback_models'] ?? ''));
        $geminiKeyNew = trim((string) ($_POST['gemini_api_key'] ?? ''));

        if ($provider === 'agnes' && ($agnesUrl === '' || $agnesModel === '')) {
            $errors[] = 'Agnes API URL and model are required when Agnes AI is selected.';
        }
        if ($provider === 'gemini' && ($geminiUrl === '' || $geminiModel === '')) {
            $errors[] = 'Gemini API URL and model are required when Gemini is selected.';
        }

        if ($errors === []) {
            setting_set('ai_provider', $provider);
            setting_set('agnes_api_url', $agnesUrl);
            setting_set('agnes_model', $agnesModel);
            setting_set('agnes_fallback_models', $agnesFallback);
            setting_set('gemini_api_url', $geminiUrl);
            setting_set('gemini_model', $geminiModel);
            setting_set('gemini_fallback_models', $geminiFallback);

            // Keep existing key if the field is left blank
            if ($agnesKeyNew !== '') {
                setting_set('agnes_api_key', $agnesKeyNew);
            }
            if ($geminiKeyNew !== '') {
                setting_set('gemini_api_key', $geminiKeyNew);
            }

            flash_set('success', 'AI settings saved.');
            redirect('/admin/settings.php');
        }

        $settings = array_merge($settings, [
            'ai_provider' => $provider,
            'agnes_api_url' => $agnesUrl,
            'agnes_model' => $agnesModel,
            'agnes_fallback_models' => $agnesFallback,
            'gemini_api_url' => $geminiUrl,
            'gemini_model' => $geminiModel,
            'gemini_fallback_models' => $geminiFallback,
        ]);
    }
}

$pageTitle = 'AI Settings · ' . APP_NAME;
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="container py-4 py-md-5">
    <div class="mb-4">
        <h1 class="page-title mb-1">AI Settings</h1>
        <p class="text-muted mb-0">Configure the vision provider used for product image recognition. API keys never leave the server.</p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php foreach ($testResults as $result): ?>
        <?php if (!empty($result['ok'])): ?>
            <div class="alert alert-success">
                <strong>Connection successful.</strong><br>
                Provider: <?= e((string) ($result['provider'] ?? '')) ?><br>
                Model: <?= e((string) ($result['model'] ?? '')) ?>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <strong>Connection failed.</strong>
                <?php if (!empty($result['provider'])): ?>
                    <br>Provider: <?= e((string) $result['provider']) ?>
                <?php endif; ?>
                <br>Please check:<br>
                - API URL<br>
                - API key<br>
                - Model<br>
                - Internet connection
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="admin-card">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">

            <div class="mb-4">
                <label class="form-label" for="ai_provider">AI Provider</label>
                <select class="form-select form-select-lg" id="ai_provider" name="ai_provider">
                    <option value="agnes" <?= ($settings['ai_provider'] ?? '') === 'agnes' ? 'selected' : '' ?>>Agnes AI</option>
                    <option value="gemini" <?= ($settings['ai_provider'] ?? '') === 'gemini' ? 'selected' : '' ?>>Gemini</option>
                </select>
            </div>

            <div class="settings-section mb-4">
                <h2 class="h5"><i class="fa-solid fa-microchip me-2"></i>Agnes AI / OpenAI-compatible</h2>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="agnes_api_url">API URL</label>
                        <input class="form-control" id="agnes_api_url" name="agnes_api_url"
                               value="<?= e((string) ($settings['agnes_api_url'] ?? '')) ?>"
                               placeholder="https://apihub.agnes-ai.com/v1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="agnes_model">Model</label>
                        <input class="form-control" id="agnes_model" name="agnes_model"
                               value="<?= e((string) ($settings['agnes_model'] ?? '')) ?>"
                               placeholder="agnes-3.0-flash">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="agnes_api_key">API Key</label>
                        <?php if (!empty($settings['agnes_api_key'])): ?>
                            <div class="masked-secret mb-2"><?= e(mask_secret($settings['agnes_api_key'])) ?></div>
                        <?php endif; ?>
                        <input class="form-control" type="password" id="agnes_api_key" name="agnes_api_key"
                               autocomplete="new-password" placeholder="Leave blank to keep current key">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="agnes_fallback_models">Fallback Models</label>
                        <textarea class="form-control" id="agnes_fallback_models" name="agnes_fallback_models" rows="3"
                                  placeholder="model-a&#10;model-b&#10;model-c"><?= e((string) ($settings['agnes_fallback_models'] ?? '')) ?></textarea>
                        <div class="form-text">One model per line (or comma-separated).</div>
                    </div>
                </div>
            </div>

            <div class="settings-section mb-4">
                <h2 class="h5"><i class="fa-solid fa-gem me-2"></i>Google Gemini-compatible</h2>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="gemini_api_url">API URL</label>
                        <input class="form-control" id="gemini_api_url" name="gemini_api_url"
                               value="<?= e((string) ($settings['gemini_api_url'] ?? '')) ?>"
                               placeholder="https://generativelanguage.googleapis.com/v1beta">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="gemini_model">Model</label>
                        <input class="form-control" id="gemini_model" name="gemini_model"
                               value="<?= e((string) ($settings['gemini_model'] ?? '')) ?>"
                               placeholder="gemini-3.8-flash">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="gemini_api_key">API Key</label>
                        <?php if (!empty($settings['gemini_api_key'])): ?>
                            <div class="masked-secret mb-2"><?= e(mask_secret($settings['gemini_api_key'])) ?></div>
                        <?php endif; ?>
                        <input class="form-control" type="password" id="gemini_api_key" name="gemini_api_key"
                               autocomplete="new-password" placeholder="Leave blank to keep current key">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="gemini_fallback_models">Fallback Models</label>
                        <textarea class="form-control" id="gemini_fallback_models" name="gemini_fallback_models" rows="3"
                                  placeholder="model-a&#10;model-b"><?= e((string) ($settings['gemini_fallback_models'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Save Settings
                </button>
            </div>
        </form>

        <hr class="my-4">

        <h2 class="h5 mb-3"><i class="fa-solid fa-plug me-2"></i>Test AI Connection</h2>
        <p class="text-muted mb-3">Test each provider separately. This does not change your active provider setting.</p>
        <div class="d-flex flex-wrap gap-2">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="test_agnes">
                <button type="submit" class="btn btn-outline-primary btn-lg">
                    <i class="fa-solid fa-microchip me-2"></i>Test Agnes AI
                </button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="test_gemini">
                <button type="submit" class="btn btn-outline-primary btn-lg">
                    <i class="fa-solid fa-gem me-2"></i>Test Google Gemini
                </button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="test_both">
                <button type="submit" class="btn btn-soft btn-lg">
                    <i class="fa-solid fa-vial me-2"></i>Test Both
                </button>
            </form>
        </div>
    </div>
</section>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
