<?php
declare(strict_types=1);

// Local (git-ignored) config to set env vars via putenv()
$localConfig = __DIR__ . '/includes/config.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

require __DIR__ . '/includes/db.php';
require __DIR__ . '/lib/PHPMailer/Exception.php';
require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

function redirect_with_status(string $status, array $extraQuery = []): void {
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

function make_mailer(): PHPMailer
{
    $host = (string) (getenv('SMTP_HOST') ?: 'smtp.gmail.com');
    $port = (int) (getenv('SMTP_PORT') ?: '587');
    $secure = strtolower((string) (getenv('SMTP_SECURE') ?: 'starttls')); // starttls|ssl
    $user = (string) (getenv('SMTP_USER') ?: '');
    $pass = (string) (getenv('SMTP_PASS') ?: '');
    $from = (string) (getenv('SMTP_FROM') ?: $user);
    $fromName = (string) (getenv('SMTP_FROM_NAME') ?: 'Finexa Solution');

    if ($user === '' || $pass === '' || $from === '') {
        throw new RuntimeException('SMTP is not configured (SMTP_USER/SMTP_PASS/SMTP_FROM missing).');
    }

    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $pass;
    $mail->Port = $port;

    if ($secure === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // 465
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // 587
        $mail->SMTPAutoTLS = true;
    }

    $mail->setFrom($from, $fromName);
    $mail->isHTML(false);

    return $mail;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_status('error');
}

// Honeypot spam trap
$honeypot = isset($_POST['website']) ? trim((string)$_POST['website']) : '';
if ($honeypot !== '') {
    redirect_with_status('success'); // pretend success for bots
}

$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$service = trim((string)($_POST['service'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($name === '' || $email === '' || $phone === '' || $service === '' || $message === '') {
    redirect_with_status('error');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_status('error');
}

// Basic header injection hardening
foreach ([$name, $email, $service] as $val) {
    if (preg_match('/\r|\n/', $val)) {
        redirect_with_status('error');
    }
}

$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 512) : null;

try {
    $pdo = db();

    // Create table if missing
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contact_submissions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            service VARCHAR(190) NOT NULL,
            message TEXT NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(512) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("
        INSERT INTO contact_submissions (name, email, phone, service, message, ip_address, user_agent)
        VALUES (:name, :email, :phone, :service, :message, :ip, :ua)
    ");
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phone,
        ':service' => $service,
        ':message' => $message,
        ':ip' => $ip,
        ':ua' => $userAgent,
    ]);
} catch (Throwable $e) {
    log_form_error('db', $e);
    // If DB fails, don't proceed (so you know it's not stored)
    redirect_with_status('error');
}

// SMTP / mail settings
$siteName = (string) (getenv('SMTP_FROM_NAME') ?: 'Finexa Solution');
$adminTo = (string) (getenv('ADMIN_TO') ?: (getenv('SMTP_FROM') ?: getenv('SMTP_USER') ?: ''));
if ($adminTo === '') {
    // still allow DB save even if mail isn't configured, but show error so you notice
    redirect_with_status('error');
}

// Email to admin (lead notification)
$adminSubject = 'New contact form submission';
$adminBody = "New contact form submission:\n\n"
    . "Name: {$name}\n"
    . "Email: {$email}\n"
    . "Phone: {$phone}\n"
    . "Service: {$service}\n\n"
    . "Message:\n{$message}\n";

// Email to user (confirmation)
$userTo = $email;
$userSubject = 'Thanks for your enquiry';
$userBody = "Hi {$name},\n\n"
    . "Thanks for reaching out to {$siteName}. We’ve received your message and will get back to you shortly.\n\n"
    . "Here are the details you submitted:\n"
    . "Service: {$service}\n"
    . "Phone: {$phone}\n\n"
    . "Message:\n{$message}\n\n"
    . "Regards,\n{$siteName}\n";

try {
    // Admin mail
    $m1 = make_mailer();
    $m1->addAddress($adminTo);
    $m1->Subject = $adminSubject;
    $m1->Body = $adminBody;
    $m1->addReplyTo($email, $name);
    $m1->send();

    // User auto-reply
    $m2 = make_mailer();
    $m2->addAddress($userTo, $name);
    $m2->Subject = $userSubject;
    $m2->Body = $userBody;
    $m2->send();
} catch (Throwable $e) {
    // DB save succeeded; don't block success UI, but log the mail failure.
    log_form_error('smtp', $e);
    redirect_with_status('success', ['mail' => 'failed']);
}

redirect_with_status('success');

