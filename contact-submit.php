<?php
declare(strict_types=1);

require __DIR__ . '/includes/db.php';

function redirect_with_status(string $status): void {
    header('Location: index.php?form=' . rawurlencode($status) . '#contact');
    exit;
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
    // If DB fails, don't proceed (so you know it's not stored)
    redirect_with_status('error');
}

$fromEmail = 'noreply@finexasolution.com';
$siteName = 'Finexa Solution';

// Email to admin (lead notification)
$adminTo = $fromEmail;
$adminSubject = 'New contact form submission';
$adminBody = "New contact form submission:\n\n"
    . "Name: {$name}\n"
    . "Email: {$email}\n"
    . "Phone: {$phone}\n"
    . "Service: {$service}\n\n"
    . "Message:\n{$message}\n";

$adminHeaders = [];
$adminHeaders[] = 'MIME-Version: 1.0';
$adminHeaders[] = 'Content-Type: text/plain; charset=UTF-8';
$adminHeaders[] = 'From: ' . $siteName . ' <' . $fromEmail . '>';
$adminHeaders[] = 'Reply-To: ' . $name . ' <' . $email . '>';

// Email to user (confirmation)
$userTo = $email;
$userSubject = 'We received your message';
$userBody = "Hi {$name},\n\n"
    . "Thanks for reaching out to {$siteName}. We’ve received your message and will get back to you shortly.\n\n"
    . "Here are the details you submitted:\n"
    . "Service: {$service}\n"
    . "Phone: {$phone}\n\n"
    . "Message:\n{$message}\n\n"
    . "Regards,\n{$siteName}\n";

$userHeaders = [];
$userHeaders[] = 'MIME-Version: 1.0';
$userHeaders[] = 'Content-Type: text/plain; charset=UTF-8';
$userHeaders[] = 'From: ' . $siteName . ' <' . $fromEmail . '>';

// Email (best-effort on local XAMPP; DB save is the source of truth)
@mail($adminTo, $adminSubject, $adminBody, implode("\r\n", $adminHeaders));
@mail($userTo, $userSubject, $userBody, implode("\r\n", $userHeaders));

redirect_with_status('success');

