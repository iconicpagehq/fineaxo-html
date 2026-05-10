<?php
/**
 * SMTP config for Google Workspace (Gmail SMTP)
 * Email: Info@finexasolution.com
 *
 * ⚠️  IMPORTANT — App Password required:
 *   Google Workspace blocks regular passwords for SMTP.
 *   You MUST generate a 16-character App Password:
 *
 *   1. Go to: https://myaccount.google.com/security
 *   2. Enable "2-Step Verification" (required first)
 *   3. Go to: https://myaccount.google.com/apppasswords
 *   4. Select app → "Mail", device → "Other" → type "XAMPP"
 *   5. Copy the 16-char password (e.g. abcd efgh ijkl mnop)
 *   6. Paste it below (no spaces) in SMTP_PASS
 *
 *   If 2FA is managed by your Google Workspace Admin,
 *   ask them to generate the App Password for you.
 */

// ── Google Workspace SMTP ─────────────────────────────────────
putenv('SMTP_HOST=smtp.gmail.com');
putenv('SMTP_PORT=587');
putenv('SMTP_SECURE=starttls');

// ── Credentials ───────────────────────────────────────────────
putenv('SMTP_USER=Info@finexasolution.com');
putenv('SMTP_PASS=YOUR_16_CHAR_APP_PASSWORD_HERE'); // ← paste App Password here

// ── Sender identity ───────────────────────────────────────────
putenv('SMTP_FROM=Info@finexasolution.com');
putenv('SMTP_FROM_NAME=Finexa Solution');

// ── Admin recipient (receives lead notifications) ─────────────
putenv('ADMIN_TO=Info@finexasolution.com');

// ── Debug (set to 2 for verbose SMTP log, 0 for production) ──
putenv('SMTP_DEBUG=0');

// ── Admin panel auth (/admin) ──────────────────────────────────
// IMPORTANT:
// - Set a strong password (do NOT keep "admin123")
// - Generate hash quickly in PHP:
//     echo password_hash('your-password', PASSWORD_DEFAULT);
//
// Example (replace hash):
// putenv('ADMIN_USER=admin');
// putenv('ADMIN_PASS_HASH=$2y$10$REPLACE_WITH_PASSWORD_HASH');
