<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (is_admin()) {
    redirect('/admin/products.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $error = 'Email and password are required.';
        } elseif (!attempt_login($email, $password)) {
            $error = 'Invalid email or password.';
        } else {
            flash_set('success', 'Welcome back.');
            redirect('/admin/products.php');
        }
    }
}

$pageTitle = 'Admin Login · ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<section class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="auth-card">
                <div class="text-center mb-4">
                    <div class="brand-mark mx-auto mb-3"><i class="fa-solid fa-user-shield"></i></div>
                    <h1 class="h3 mb-1">Admin Login</h1>
                    <p class="text-muted mb-0">Sign in to manage products and AI settings.</p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" autocomplete="on">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control form-control-lg" type="email" name="email" id="email"
                               value="<?= e($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control form-control-lg" type="password" name="password" id="password" required>
                    </div>

                    <button type="button" class="btn btn-soft w-100 mb-3" id="btnDemoAccount">
                        <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Use demo account
                    </button>
                    <div class="small text-muted text-center mb-3">
                        Demo: <code><?= e(DEFAULT_ADMIN_EMAIL) ?></code> / <code><?= e(DEFAULT_ADMIN_PASSWORD) ?></code>
                    </div>

                    <button class="btn btn-primary btn-lg w-100" type="submit">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Log in
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
document.getElementById('btnDemoAccount')?.addEventListener('click', function () {
    var email = document.getElementById('email');
    var password = document.getElementById('password');
    if (email) email.value = <?= json_encode(DEFAULT_ADMIN_EMAIL) ?>;
    if (password) password.value = <?= json_encode(DEFAULT_ADMIN_PASSWORD) ?>;
    email?.focus();
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
