<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_bootstrap.php';
require_admin_auth();

header('Content-Type: application/json; charset=UTF-8');

function json_fail(string $error, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail('Method not allowed', 405);
}

if (!isset($_FILES['csv']) || !is_array($_FILES['csv'])) {
    json_fail('Missing CSV file.');
}

$f = $_FILES['csv'];
if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_fail('Upload failed.');
}

$tmp = (string) ($f['tmp_name'] ?? '');
if ($tmp === '' || !is_file($tmp)) {
    json_fail('Upload failed.');
}

$size = (int) ($f['size'] ?? 0);
if ($size <= 0) {
    json_fail('CSV file is empty.');
}
if ($size > 5 * 1024 * 1024) {
    json_fail('CSV file too large (max 5MB).');
}

$pdo = db();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `contact_submissions` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(190) NOT NULL,
        `email` VARCHAR(190) NOT NULL,
        `phone` VARCHAR(50) NOT NULL,
        `service` VARCHAR(190) NOT NULL,
        `message` TEXT NOT NULL,
        `ip_address` VARCHAR(45) NULL,
        `user_agent` VARCHAR(512) NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$fh = fopen($tmp, 'rb');
if (!$fh) {
    json_fail('Failed to read CSV.');
}

// Remove UTF-8 BOM if present
$firstBytes = fread($fh, 3);
if ($firstBytes !== "\xEF\xBB\xBF") {
    rewind($fh);
}

/** @return array<string, int>|null */
function header_map(array $row): ?array
{
    $norm = array_map(static function ($v) {
        $s = strtolower(trim((string) $v));
        $s = preg_replace('/\s+/', '_', $s);
        return $s;
    }, $row);

    $wanted = ['name', 'email', 'phone', 'service', 'message'];
    $map = [];
    foreach ($norm as $i => $col) {
        if (in_array($col, $wanted, true)) {
            $map[$col] = (int) $i;
        }
    }
    return isset($map['email']) ? $map : null;
}

$insert = $pdo->prepare("
    INSERT INTO contact_submissions (name, email, phone, service, message, ip_address, user_agent)
    VALUES (:name, :email, :phone, :service, :message, NULL, 'csv-import')
");

$inserted = 0;
$skipped = 0;
$errors = [];

$rowNum = 0;
$map = null;

while (($row = fgetcsv($fh)) !== false) {
    $rowNum++;
    if ($rowNum === 1) {
        $maybe = header_map($row);
        if ($maybe) {
            $map = $maybe;
            continue;
        }
    }

    // Skip fully empty rows
    $nonEmpty = false;
    foreach ($row as $cell) {
        if (trim((string) $cell) !== '') {
            $nonEmpty = true;
            break;
        }
    }
    if (!$nonEmpty) {
        continue;
    }

    $get = static function (string $key, int $fallbackIndex) use ($row, $map): string {
        $idx = $map[$key] ?? $fallbackIndex;
        return isset($row[$idx]) ? trim((string) $row[$idx]) : '';
    };

    $name = $get('name', 0);
    $email = $get('email', 1);
    $phone = $get('phone', 2);
    $service = $get('service', 3);
    $message = $get('message', 4);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $skipped++;
        if (count($errors) < 10) {
            $errors[] = "Row {$rowNum}: invalid email";
        }
        continue;
    }
    if ($name === '' && $phone === '' && $service === '' && $message === '') {
        $skipped++;
        if (count($errors) < 10) {
            $errors[] = "Row {$rowNum}: empty row";
        }
        continue;
    }

    // Enforce NOT NULL columns with safe defaults
    $name = $name !== '' ? $name : '-';
    $phone = $phone !== '' ? $phone : '-';
    $service = $service !== '' ? $service : '-';
    $message = $message !== '' ? $message : '-';

    try {
        $insert->execute([
            ':name' => mb_substr($name, 0, 190, 'UTF-8'),
            ':email' => mb_substr($email, 0, 190, 'UTF-8'),
            ':phone' => mb_substr($phone, 0, 50, 'UTF-8'),
            ':service' => mb_substr($service, 0, 190, 'UTF-8'),
            ':message' => $message,
        ]);
        $inserted++;
    } catch (Throwable $e) {
        $skipped++;
        if (count($errors) < 10) {
            $errors[] = "Row {$rowNum}: insert failed";
        }
    }
}

fclose($fh);

echo json_encode([
    'ok' => true,
    'inserted' => $inserted,
    'skipped' => $skipped,
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

