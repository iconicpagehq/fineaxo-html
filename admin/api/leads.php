<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_bootstrap.php';
require_admin_auth();

header('Content-Type: application/json; charset=UTF-8');

$pdo = db();

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = isset($_GET['perPage']) ? (int) $_GET['perPage'] : 6;
$perPage = max(1, min(100, $perPage));
$offset = ($page - 1) * $perPage;

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$service = isset($_GET['service']) ? trim((string) $_GET['service']) : '';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(name LIKE :q OR email LIKE :q OR phone LIKE :q OR message LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($service !== '') {
    $where[] = "service = :service";
    $params[':service'] = $service;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM contact_submissions {$whereSql}");
$countStmt->execute($params);
$totalRows = (int) ($countStmt->fetch()['c'] ?? 0);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT id, name, email, phone, service, message, user_agent, created_at
    FROM contact_submissions
    {$whereSql}
    ORDER BY id DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$servicesStmt = $pdo->query("SELECT DISTINCT service FROM contact_submissions WHERE service <> '' ORDER BY service ASC");
$services = array_map(static fn($r) => (string) $r['service'], $servicesStmt ? $servicesStmt->fetchAll() : []);

echo json_encode([
    'ok' => true,
    'page' => $page,
    'perPage' => $perPage,
    'totalRows' => $totalRows,
    'totalPages' => $totalPages,
    'services' => $services,
    'rows' => array_map(static function (array $r): array {
        return [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'email' => (string) $r['email'],
            'phone' => (string) $r['phone'],
            'service' => (string) $r['service'],
            'message' => trim_ellipsis((string) $r['message'], 140),
            'user_agent' => trim_ellipsis((string) ($r['user_agent'] ?? ''), 80),
            'created_at' => (string) $r['created_at'],
        ];
    }, $rows),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

