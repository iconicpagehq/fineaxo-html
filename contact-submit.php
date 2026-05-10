<?php
declare(strict_types=1);

// ── Local config (git-ignored) ──────────────────────────────────────────────
$localConfig = __DIR__ . '/includes/config.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

// ── Dependencies ────────────────────────────────────────────────────────────
require __DIR__ . '/includes/db.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

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

// ── PHPMailer factory ────────────────────────────────────────────────────────

function make_mailer(): PHPMailer
{
    $host     = (string) (getenv('SMTP_HOST')      ?: 'mail.finexasolution.com');
    $port     = (int)    (getenv('SMTP_PORT')      ?: '587');
    $secure   = strtolower((string) (getenv('SMTP_SECURE') ?: 'starttls'));
    $user     = (string) (getenv('SMTP_USER')      ?: '');
    $pass     = (string) (getenv('SMTP_PASS')      ?: '');
    $from     = (string) (getenv('SMTP_FROM')      ?: $user);
    $fromName = (string) (getenv('SMTP_FROM_NAME') ?: 'Finexa Solution');
    $debug    = (int)    (getenv('SMTP_DEBUG')     ?: '0');

    if ($user === '' || $pass === '' || $from === '') {
        throw new RuntimeException(
            'SMTP is not configured — set SMTP_USER, SMTP_PASS, and SMTP_FROM in includes/config.local.php'
        );
    }

    $mail = new PHPMailer(true); // true = throw exceptions
    $mail->CharSet  = PHPMailer::CHARSET_UTF8;
    $mail->SMTPDebug = $debug; // 0=off | 2=verbose (useful for debugging)
    $mail->Debugoutput = 'error_log';

    $mail->isSMTP();
    $mail->Host      = $host;
    $mail->SMTPAuth  = true;
    $mail->Username  = $user;
    $mail->Password  = $pass;
    $mail->Port      = $port;

    if ($secure === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;    // port 465
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // port 587
        $mail->SMTPAutoTLS = true;
    }

    $mail->setFrom($from, $fromName);
    return $mail;
}

// ── Email templates ──────────────────────────────────────────────────────────

function make_admin_email(string $name, string $email, string $phone, string $service, string $message): array
{
    $subject = "🔔 New Enquiry from {$name} — {$service}";

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>New Enquiry</title>
</head>
<body style="margin:0;padding:0;font-family:'Segoe UI',Arial,sans-serif;background:#f4f6f8;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:40px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#0d9488,#0f766e);padding:32px 40px;text-align:center;">
            <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-0.3px;">
              Finexa Solution
            </h1>
            <p style="margin:6px 0 0;color:#ccfbf1;font-size:13px;">New Contact Form Submission</p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:36px 40px;">
            <p style="margin:0 0 24px;color:#374151;font-size:15px;line-height:1.6;">
              You have received a new enquiry. Here are the details:
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
              <tr>
                <td style="padding:12px 16px;background:#f0fdf4;border-left:4px solid #0d9488;border-radius:0 6px 6px 0;margin-bottom:8px;">
                  <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:0.8px;color:#6b7280;font-weight:600;">Name</p>
                  <p style="margin:4px 0 0;font-size:15px;color:#111827;font-weight:600;">{$name}</p>
                </td>
              </tr>
              <tr><td style="height:8px;"></td></tr>
              <tr>
                <td style="padding:12px 16px;background:#f0fdf4;border-left:4px solid #0d9488;border-radius:0 6px 6px 0;">
                  <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:0.8px;color:#6b7280;font-weight:600;">Email</p>
                  <p style="margin:4px 0 0;font-size:15px;color:#0d9488;">
                    <a href="mailto:{$email}" style="color:#0d9488;text-decoration:none;">{$email}</a>
                  </p>
                </td>
              </tr>
              <tr><td style="height:8px;"></td></tr>
              <tr>
                <td style="padding:12px 16px;background:#f0fdf4;border-left:4px solid #0d9488;border-radius:0 6px 6px 0;">
                  <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:0.8px;color:#6b7280;font-weight:600;">Phone</p>
                  <p style="margin:4px 0 0;font-size:15px;color:#111827;font-weight:600;">{$phone}</p>
                </td>
              </tr>
              <tr><td style="height:8px;"></td></tr>
              <tr>
                <td style="padding:12px 16px;background:#f0fdf4;border-left:4px solid #0d9488;border-radius:0 6px 6px 0;">
                  <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:0.8px;color:#6b7280;font-weight:600;">Service Interested In</p>
                  <p style="margin:4px 0 0;font-size:15px;color:#111827;font-weight:600;">{$service}</p>
                </td>
              </tr>
              <tr><td style="height:8px;"></td></tr>
              <tr>
                <td style="padding:12px 16px;background:#f0fdf4;border-left:4px solid #0d9488;border-radius:0 6px 6px 0;">
                  <p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:0.8px;color:#6b7280;font-weight:600;">Message</p>
                  <p style="margin:4px 0 0;font-size:15px;color:#374151;line-height:1.6;">{$message}</p>
                </td>
              </tr>
            </table>

            <div style="margin-top:32px;text-align:center;">
              <a href="mailto:{$email}?subject=Re: Your enquiry with Finexa Solution"
                 style="display:inline-block;background:linear-gradient(135deg,#0d9488,#0f766e);color:#fff;text-decoration:none;padding:13px 32px;border-radius:50px;font-size:14px;font-weight:600;letter-spacing:0.3px;">
                Reply to {$name}
              </a>
            </div>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:20px 40px;background:#f9fafb;border-top:1px solid #f0f0f0;text-align:center;">
            <p style="margin:0;color:#9ca3af;font-size:12px;">
              This email was generated automatically by the Finexa Solution contact form.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $plain = "New enquiry from {$name}\n\n"
           . "Email:   {$email}\n"
           . "Phone:   {$phone}\n"
           . "Service: {$service}\n\n"
           . "Message:\n{$message}\n";

    return ['subject' => $subject, 'html' => $html, 'plain' => $plain];
}

function make_user_email(string $name, string $service, string $phone, string $message, string $siteName): array
{
    $subject = "✅ We've received your enquiry — {$siteName}";

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Thank You</title>
</head>
<body style="margin:0;padding:0;font-family:'Segoe UI',Arial,sans-serif;background:#f4f6f8;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:40px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#0d9488,#0f766e);padding:32px 40px;text-align:center;">
            <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-0.3px;">
              {$siteName}
            </h1>
            <p style="margin:6px 0 0;color:#ccfbf1;font-size:13px;">Precision Financial Bookkeeping</p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:36px 40px;">
            <h2 style="margin:0 0 8px;color:#111827;font-size:20px;">Hi {$name} 👋</h2>
            <p style="margin:0 0 24px;color:#374151;font-size:15px;line-height:1.7;">
              Thank you for reaching out! We've received your enquiry and our team will get back to you within <strong>24 hours</strong>.
            </p>

            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:24px;margin-bottom:28px;">
              <p style="margin:0 0 12px;color:#065f46;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;">Your Submission Details</p>
              <table width="100%" cellpadding="6" cellspacing="0">
                <tr>
                  <td style="color:#6b7280;font-size:13px;width:110px;vertical-align:top;">Service</td>
                  <td style="color:#111827;font-size:13px;font-weight:600;">{$service}</td>
                </tr>
                <tr>
                  <td style="color:#6b7280;font-size:13px;vertical-align:top;">Phone</td>
                  <td style="color:#111827;font-size:13px;font-weight:600;">{$phone}</td>
                </tr>
                <tr>
                  <td style="color:#6b7280;font-size:13px;vertical-align:top;">Message</td>
                  <td style="color:#374151;font-size:13px;line-height:1.6;">{$message}</td>
                </tr>
              </table>
            </div>

            <p style="margin:0 0 6px;color:#374151;font-size:14px;line-height:1.7;">
              In the meantime, feel free to explore our services or connect with us.
            </p>

            <div style="margin-top:28px;text-align:center;">
              <a href="https://finexasolution.com"
                 style="display:inline-block;background:linear-gradient(135deg,#0d9488,#0f766e);color:#fff;text-decoration:none;padding:13px 32px;border-radius:50px;font-size:14px;font-weight:600;letter-spacing:0.3px;">
                Visit Our Website
              </a>
            </div>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:20px 40px;background:#f9fafb;border-top:1px solid #f0f0f0;text-align:center;">
            <p style="margin:0 0 4px;color:#374151;font-size:13px;font-weight:600;">{$siteName}</p>
            <p style="margin:0;color:#9ca3af;font-size:12px;">
              Precision Financial Bookkeeping &bull; Info@finexasolution.com
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $plain = "Hi {$name},\n\n"
           . "Thank you for contacting {$siteName}!\n\n"
           . "We have received your enquiry and will respond within 24 hours.\n\n"
           . "Your details:\n"
           . "Service: {$service}\n"
           . "Phone:   {$phone}\n\n"
           . "Message:\n{$message}\n\n"
           . "Regards,\n{$siteName}\nInfo@finexasolution.com\n";

    return ['subject' => $subject, 'html' => $html, 'plain' => $plain];
}

// ── Send helper ──────────────────────────────────────────────────────────────

function send_email(string $to, string $toName, array $email, ?string $replyTo = null, string $replyToName = ''): void
{
    $mail = make_mailer();
    $mail->isHTML(true);

    $mail->addAddress($to, $toName);
    $mail->Subject  = $email['subject'];
    $mail->Body     = $email['html'];
    $mail->AltBody  = $email['plain'];

    if ($replyTo !== null && $replyTo !== '') {
        $mail->addReplyTo($replyTo, $replyToName);
    }

    $mail->send();
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
} catch (Throwable $e) {
    log_form_error('db', $e);
    // Continue — email delivery is more critical than DB storage
}

// ── Send emails ───────────────────────────────────────────────────────────────
$siteName = (string) (getenv('SMTP_FROM_NAME') ?: 'Finexa Solution');
$adminTo  = (string) (getenv('ADMIN_TO') ?: (getenv('SMTP_FROM') ?: getenv('SMTP_USER') ?: ''));

$adminMailSent = false;
$userMailSent  = false;

// 1. Admin notification
if ($adminTo !== '') {
    try {
        $adminEmail = make_admin_email($name, $email, $phone, $service, $message);
        send_email($adminTo, $siteName, $adminEmail, $email, $name);
        $adminMailSent = true;
    } catch (Throwable $e) {
        log_form_error('smtp_admin', $e);
    }
}

// 2. User auto-reply / thank-you
try {
    $userEmail = make_user_email($name, $service, $phone, $message, $siteName);
    send_email($email, $name, $userEmail, $adminTo, $siteName);
    $userMailSent = true;
} catch (Throwable $e) {
    log_form_error('smtp_user', $e);
}

// ── Redirect ──────────────────────────────────────────────────────────────────
if ($userMailSent || $adminMailSent) {
    redirect_with_status('success');
} else {
    redirect_with_status('success', ['mail' => 'failed']);
}
