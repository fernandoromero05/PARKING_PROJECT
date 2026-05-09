<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/utils/validate.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$actorId = require_auth();

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$actorId]);
$actor = $stmt->fetch();
if (!$actor || !in_array($actor['role'], ['ENFORCER', 'ADMIN'], true)) {
    json_fail('Access denied', 403);
}

$data          = require_json_body();
require_fields($data, ['user_id']);
$targetId      = (int)$data['user_id'];
$durationHours = isset($data['duration_hours']) ? (int)$data['duration_hours'] : 24;

if ($targetId <= 0)      json_fail('Invalid user_id');
if ($durationHours < 1)  $durationHours = 1;
if ($targetId === $actorId) json_fail('You cannot ban yourself');

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$targetId]);
$target = $stmt->fetch();
if (!$target) json_fail('User not found');

if ($actor['role'] === 'ENFORCER' && in_array($target['role'], ['ENFORCER', 'ADMIN'], true)) {
    json_fail('Enforcers cannot ban other enforcers or admins', 403);
}

$stmt = $pdo->prepare("
    UPDATE users
    SET is_banned = TRUE,
        ban_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? HOUR)
    WHERE id = ?
");
$stmt->execute([$durationHours, $targetId]);

json_ok(['banned_user_id' => $targetId, 'duration_hours' => $durationHours]);
