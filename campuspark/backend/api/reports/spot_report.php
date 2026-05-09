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
if (!$actor || !in_array($actor['role'], ['ENFORCER', 'ADMIN'], true)) {
    json_fail('Access denied', 403);
}

$spotId = isset($_GET['spot_id']) ? (int)$_GET['spot_id'] : 0;
if ($spotId <= 0) json_fail('Invalid spot_id');

// Return the most recent report for this spot that has no image yet
$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.type,
        r.note,
        r.created_at,
        u.username AS reporter_name,
        u.role     AS reporter_role
    FROM reports r
    JOIN users u ON r.reporter_user_id = u.id
    WHERE r.spot_id = ?
      AND r.image_url IS NULL
    ORDER BY r.created_at DESC
    LIMIT 1
");
$stmt->execute([$spotId]);
$report = $stmt->fetch();

json_ok(['report' => $report ?: null]);
