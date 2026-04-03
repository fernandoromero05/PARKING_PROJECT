<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/utils/validate.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$userId = require_auth();

$data = require_json_body();
require_fields($data, ['spot_id']);
$spotId = (int)$data['spot_id'];
if ($spotId <= 0) json_fail('Invalid spot_id');

$pdo->beginTransaction();
try {
  $stmt = $pdo->prepare("SELECT id, status, occupied_by_user_id, reserved_by_user_id FROM spots WHERE id=? FOR UPDATE");
  $stmt->execute([$spotId]);
  $spot = $stmt->fetch();
  if (!$spot) throw new Exception('Spot not found');

  // Check if user is the one who has it
  $isOwner = ((int)$spot['occupied_by_user_id'] === $userId || (int)$spot['reserved_by_user_id'] === $userId);
  if (!$isOwner) throw new Exception('This is not your spot');

  $stmt = $pdo->prepare("
    UPDATE spots
    SET status='AVAILABLE',
        occupied_by_user_id=NULL,
        occupied_since=NULL,
        reserved_by_user_id=NULL,
        reserved_until=NULL,
        vehicle_plate=NULL,
        vehicle_make=NULL,
        vehicle_type=NULL
    WHERE id=?
  ");
  $stmt->execute([$spotId]);

  $stmt = $pdo->prepare("INSERT INTO claims (user_id, spot_id, action) VALUES (?, ?, 'RELEASE')");
  $stmt->execute([$userId, $spotId]);

  $pdo->commit();
  json_ok();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_fail($e->getMessage(), 400);
}