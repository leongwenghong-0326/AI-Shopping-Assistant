<?php
/**
 * Authentication, authorization, and CSRF helpers.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cached = null;
    if (is_array($cached) && (int) $cached['id'] === (int) $_SESSION['user_id']) {
        return $cached;
    }

    try {
        $stmt = db()->prepare(
            'SELECT id, name, email, role, created_at FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([(int) $_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user) {
            unset($_SESSION['user_id']);
            return null;
        }
        $cached = $user;
        return $user;
    } catch (Throwable $e) {
        error_log('current_user failed: ' . $e->getMessage());
        return null;
    }
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && ($user['role'] ?? '') === 'admin';
}

function require_admin(): void
{
    if (!is_admin()) {
        flash_set('danger', 'Please log in to access the admin area.');
        redirect('/login.php');
    }
}

function attempt_login(string $email, string $password): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || $password === '') {
        return false;
    }

    $stmt = db()->prepare(
        'SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];

    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_KEY]) || !is_string($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token = null): bool
{
    $token = $token ?? ($_POST['_csrf'] ?? '');
    $sessionToken = $_SESSION[CSRF_TOKEN_KEY] ?? '';

    if (!is_string($token) || $token === '' || !is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function require_csrf(): void
{
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}
