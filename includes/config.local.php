<?php
/**
 * Copy this file to `includes/config.local.php` and set real values.
 * This file is git-ignored (see `.gitignore`).
 *
 * NOTE: For Gmail SMTP, you usually need an **App Password** (not your normal password),
 * especially if 2FA is enabled.
 */

putenv('SMTP_HOST=smtp.gmail.com');
putenv('SMTP_PORT=587');
putenv('SMTP_SECURE=starttls'); // starttls (587) or ssl (465)

// Your workspace/purchased email credentials
putenv('SMTP_USER=Info@finexasolution.com');
putenv('SMTP_PASS=Infor@@2026');

// From name shown in emails
putenv('SMTP_FROM=Info@finexasolution.com');
putenv('SMTP_FROM_NAME=Finexa Solution');

// Where lead notification should go (admin)
putenv('ADMIN_TO=Info@finexasolution.com');

