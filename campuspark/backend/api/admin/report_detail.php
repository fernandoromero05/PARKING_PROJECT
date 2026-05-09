<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$userId = require_auth();

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user || !in_array($user['role'], ['ADMIN', 'ENFORCER'], true)) {
    json_fail('Access denied', 403);
}

$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($reportId <= 0) json_fail('Invalid report id');

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.type,
        r.note,
        r.image_url,
        r.created_at,
        reporter.id            AS reporter_id,
        reporter.username      AS reporter_name,
        reporter.role          AS reporter_role,
        reported.id            AS reported_id,
        reported.username      AS reported_name,
        reported.tokens        AS reported_tokens,
        reported.rating        AS reported_rating,
        reported.is_banned     AS reported_is_banned,
        enforcer.id            AS enforcer_id,
        enforcer.username      AS enforcer_name,
        s.id                   AS spot_id,
        s.spot_number,
        s.spot_type,
        l.name                 AS lot_name,
        l.code                 AS lot_code
    FROM reports r
    JOIN users reporter ON r.reporter_user_id = reporter.id
    LEFT JOIN users reported ON r.reported_user_id = reported.id
    LEFT JOIN users enforcer ON r.enforcer_user_id = enforcer.id
    JOIN spots s ON r.spot_id = s.id
    JOIN lots l ON s.lot_id = l.id
    WHERE r.id = ?
");
$stmt->execute([$reportId]);
$report = $stmt->fetch();

if (!$report) json_fail('Report not found', 404);

json_ok(['report' => $report]);
