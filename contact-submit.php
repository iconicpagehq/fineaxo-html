<?php
declare(strict_types=1);

// ── Local config (git-ignored) ──────────────────────────────────────────────
$localConfig = __DIR__ . '/includes/config.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

// ── Dependencies ────────────────────────────────────────────────────────────
require __DIR__ . '/includes/db.php';

// ── Helpers ─────────────────────────────────────────────────────────────────

function redirect_with_status(string $status, array $extraQuery = []): void
{
    $query = array_merge(['form' => $status], $extraQuery);
    header('Location: index.php?' . http_build_query($query) . '#contact');
    exit;
}

function log_form_error(string $stage, Throwable $e): void
{
    $logPath = __DIR__ . '/contact-submit-error.log';
    $line = sprintf(
        "[%s] stage=%s message=%s\n",
        date('c'),
        $stage,
        str_replace(["\r", "\n"], [' ', ' '], $e->getMessage())
    );
    @file_put_contents($logPath, $line, FILE_APPEND);
}

// ── Guards ───────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_status('error');
}

// Honeypot spam trap
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    redirect_with_status('success'); // bots think it worked
}

// Sanitise inputs
$name    = trim((string) ($_POST['name']    ?? ''));
$email   = trim((string) ($_POST['email']   ?? ''));
$phone   = trim((string) ($_POST['phone']   ?? ''));
$service = trim((string) ($_POST['service'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

// Required fields
if ($name === '' || $email === '' || $phone === '' || $service === '' || $message === '') {
    redirect_with_status('error');
}

// Valid email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_status('error');
}

// Header injection guard
foreach ([$name, $email, $service] as $val) {
    if (preg_match('/\r|\n/', $val)) {
        redirect_with_status('error');
    }
}

$ip        = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent = isset($_SERVER['HTTP_USER_AGENT'])
    ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 512)
    : null;

// ── Save to database ─────────────────────────────────────────────────────────
$saved = false;
try {
    $pdo = db();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contact_submissions (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(190)    NOT NULL,
            email      VARCHAR(190)    NOT NULL,
            phone      VARCHAR(50)     NOT NULL,
            service    VARCHAR(190)    NOT NULL,
            message    TEXT            NOT NULL,
            ip_address VARCHAR(45)     NULL,
            user_agent VARCHAR(512)    NULL,
            created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("
        INSERT INTO contact_submissions
            (name, email, phone, service, message, ip_address, user_agent)
        VALUES
            (:name, :email, :phone, :service, :message, :ip, :ua)
    ");
    $stmt->execute([
        ':name'    => $name,
        ':email'   => $email,
        ':phone'   => $phone,
        ':service' => $service,
        ':message' => $message,
        ':ip'      => $ip,
        ':ua'      => $userAgent,
    ]);
    $saved = true;
} catch (Throwable $e) {
    log_form_error('db', $e);
}

// ── Redirect ──────────────────────────────────────────────────────────────────
if ($saved) {
    redirect_with_status('success');
} else {
    redirect_with_status('error');
}
