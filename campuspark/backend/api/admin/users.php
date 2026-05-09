<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$actorId = require_auth();

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$actorId]);
$actor = $stmt->fetch();
if (!$actor || $actor['role'] !== 'ADMIN') {
    json_fail('Access denied', 403);
}

$role   = isset($_GET['role'])   ? strtoupper(trim($_GET['role']))   : '';
$search = isset($_GET['search']) ? trim($_GET['search'])              : '';

$where  = [];
$params = [];

if (in_array($role, ['STUDENT', 'ENFORCER', 'ADMIN'], true)) {
    $where[]  = "role = ?";
    $params[] = $role;
}
if ($search !== '') {
    $where[]  = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

$stmt = $pdo->prepare("
    SELECT
        id,
        username,
        email,
        role,
        tokens,
        rating,
        is_banned,
        ban_expires_at,
        created_at
    FROM users
    $whereClause
    ORDER BY created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

json_ok(['users' => $users]);
