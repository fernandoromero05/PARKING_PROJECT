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
if (!$actor || $actor['role'] !== 'ADMIN') {
    json_fail('Access denied', 403);
}

$data     = require_json_body();
require_fields($data, ['user_id']);
$targetId = (int)$data['user_id'];
if ($targetId <= 0) json_fail('Invalid user_id');

$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$targetId]);
if (!$stmt->fetch()) json_fail('User not found', 404);

$stmt = $pdo->prepare("
    UPDATE users
    SET is_banned = FALSE, ban_expires_at = NULL
    WHERE id = ?
");
$stmt->execute([$targetId]);

json_ok(['unbanned_user_id' => $targetId]);
