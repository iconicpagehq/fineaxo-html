<?php
declare(strict_types=1);

$root = dirname(__DIR__);

// Local config (git-ignored)
$localConfig = $root . '/includes/config.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

require $root . '/includes/db.php';

function ensure_admin_users_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `admin_users` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(64) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `last_login_at` DATETIME NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_admin_users_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function admin_user_count(): int
{
    try {
        $pdo = db();
        ensure_admin_users_table($pdo);
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM admin_users");
        $row = $stmt ? $stmt->fetch() : null;
        return (int) ($row['c'] ?? 0);
    } catch (Throwable) {
        return 0;
    }
}

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('finexa_admin');
    session_start();
}

function admin_base_path(): string
{
    // Works whether the project is at domain root or nested (e.g. /Office/.../fineaxo-html/)
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/admin/index.php';
    $adminDir = str_replace('\\', '/', dirname($scriptName));
    return rtrim($adminDir, '/');
}

function admin_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = admin_base_path();
    if ($path === '') {
        return $base . '/';
    }
    return $base . '/' . $path;
}

function site_url(string $path = ''): string
{
    $base = dirname(admin_base_path());
    $base = str_replace('\\', '/', $base);
    $base = rtrim($base, '/');
    $path = ltrim($path, '/');
    return $path === '' ? ($base . '/') : ($base . '/' . $path);
}

function is_admin_logged_in(): bool
{
    admin_session_start();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function require_admin_auth(): void
{
    if (!is_admin_logged_in()) {
        $to = $_SERVER['REQUEST_URI'] ?? admin_url('index.php');
        header('Location: ' . admin_url('login.php?to=' . rawurlencode($to)));
        exit;
    }
}

function admin_credentials_are_configured(): bool
{
    $user = (string) (getenv('ADMIN_USER') ?: '');
    $hash = (string) (getenv('ADMIN_PASS_HASH') ?: '');
    return $user !== '' && $hash !== '';
}

function attempt_admin_login_from_db(string $username, string $password): bool
{
    try {
        $pdo = db();
        ensure_admin_users_table($pdo);
        $stmt = $pdo->prepare("
            SELECT id, username, password_hash, is_active
            FROM admin_users
            WHERE username = :u
            LIMIT 1
        ");
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }
        if ((int) $row['is_active'] !== 1) {
            return false;
        }
        $stored = (string) $row['password_hash'];

        // Backward compatible: if someone stored a plain password by mistake,
        // allow one successful login and upgrade it to a real password hash.
        $verified = password_verify($password, $stored);
        if (!$verified && $stored !== '' && !str_starts_with($stored, '$2y$') && !str_starts_with($stored, '$argon2')) {
            $verified = hash_equals($stored, $password);
            if ($verified) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $rehash = $pdo->prepare("UPDATE admin_users SET password_hash = :h WHERE id = :id");
                $rehash->execute([':h' => $newHash, ':id' => (int) $row['id']]);
            }
        }

        if (!$verified) {
            return false;
        }

        $upd = $pdo->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = :id");
        $upd->execute([':id' => (int) $row['id']]);
        return true;
    } catch (Throwable) {
        return false;
    }
}

function attempt_admin_login(string $username, string $password): bool
{
    // Prefer DB-backed users (admin_users) if available.
    if (attempt_admin_login_from_db($username, $password)) {
        return true;
    }

    $expectedUser = (string) (getenv('ADMIN_USER') ?: '');
    $hash = (string) (getenv('ADMIN_PASS_HASH') ?: '');

    if ($expectedUser === '' || $hash === '') {
        return false;
    }

    if (!hash_equals($expectedUser, $username)) {
        return false;
    }

    return password_verify($password, $hash);
}

function admin_login(string $username): void
{
    admin_session_start();
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_login_at'] = time();
}

function admin_logout(): void
{
    admin_session_start();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function trim_ellipsis(string $text, int $maxChars = 140): string
{
    $text = trim($text);
    if ($text === '' || $maxChars <= 0) {
        return '';
    }

    if (function_exists('mb_strimwidth')) {
        return (string) mb_strimwidth($text, 0, $maxChars, '…', 'UTF-8');
    }

    if (strlen($text) <= $maxChars) {
        return $text;
    }
    return substr($text, 0, $maxChars - 1) . '…';
}

