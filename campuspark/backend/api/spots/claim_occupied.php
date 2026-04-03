<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/utils/validate.php';
require_once __DIR__ . '/../../src/utils/token_recovery.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$userId = require_auth();

$data = require_json_body();
require_fields($data, ['spot_id', 'plate', 'make', 'vehicle_type']);

$spotId = (int)$data['spot_id'];
$plate = strtoupper(trim((string)$data['plate']));
$make = trim((string)$data['make']);
$vehicleType = trim((string)$data['vehicle_type']);

if ($spotId <= 0) json_fail('Invalid spot_id');
if ($plate === '') json_fail('License plate required');
if ($make === '') json_fail('Vehicle make required');

$allowedVehicleTypes = ['ELECTRIC', 'HYBRID', 'DIESEL_GAS'];
if (!in_array($vehicleType, $allowedVehicleTypes, true)) {
  json_fail('Invalid vehicle type');
}

$pdo->beginTransaction();

try {
  // Apply automatic token recovery first
  $recovery = apply_token_recovery($pdo, $userId);

  // Reload user tokens after recovery
  $stmt = $pdo->prepare("
    SELECT tokens
    FROM users
    WHERE id=?
    FOR UPDATE
  ");
  $stmt->execute([$userId]);
  $userRow = $stmt->fetch();

  if (!$userRow) {
    throw new Exception('User not found');
  }

  // Block parking if still negative after recovery
  if ((int)$userRow['tokens'] < 0) {
    throw new Exception('You cannot park while your token balance is negative.');
  }

  $stmt = $pdo->prepare("
    SELECT
      id,
      lot_id,
      spot_number,
      status,
      spot_type,
      occupied_by_user_id,
      reserved_by_user_id
    FROM spots
    WHERE id=?
    FOR UPDATE
  ");
  $stmt->execute([$spotId]);
  $spot = $stmt->fetch();

  if (!$spot) {
    throw new Exception('Spot not found');
  }

  $stmt = $pdo->prepare("
    SELECT id
    FROM spots
    WHERE (occupied_by_user_id = ? OR (reserved_by_user_id = ? AND status = 'RESERVED'))
      AND id != ?
  ");
  $stmt->execute([$userId, $userId, (int)$spot['id']]);
  if ($stmt->fetch()) {
    throw new Exception('You already have an active spot. Release it first.');
  }

  if ($spot['spot_type'] === 'EV_ONLY' && !in_array($vehicleType, ['ELECTRIC', 'HYBRID'], true)) {
    throw new Exception('This spot is for electric or hybrid cars only');
  }

  if ($spot['status'] === 'OCCUPIED') {
    json_fail('Spot already occupied', 409, [
      'occupied_by_user_id' => $spot['occupied_by_user_id']
    ]);
  }

  if ($spot['status'] === 'RESERVED' && (int)$spot['reserved_by_user_id'] !== $userId) {
    throw new Exception('Spot is reserved by another user');
  }

  $stmt = $pdo->prepare("
    UPDATE spots
    SET status='OCCUPIED',
        occupied_by_user_id=?,
        occupied_since=NOW(),
        reserved_by_user_id=NULL,
        reserved_until=NULL,
        vehicle_plate=?,
        vehicle_make=?,
        vehicle_type=?
    WHERE id=?
  ");
  $stmt->execute([$userId, $plate, $make, $vehicleType, (int)$spot['id']]);

  $stmt = $pdo->prepare("
    INSERT INTO claims (user_id, spot_id, action)
    VALUES (?, ?, 'CLAIM')
  ");
  $stmt->execute([$userId, (int)$spot['id']]);

  $stmt = $pdo->prepare("UPDATE users SET tokens = tokens + 2 WHERE id=?");
  $stmt->execute([$userId]);

  $stmt = $pdo->prepare("
    INSERT INTO token_ledger (user_id, delta, reason)
    VALUES (?, 2, 'Clean parking claim')
  ");
  $stmt->execute([$userId]);

  $pdo->commit();
  json_ok([
    'spot_id' => $spotId,
    'spot_number' => (int)$spot['spot_number'],
    'tokens' => (int)$userRow['tokens'] + 2,
    'recovered' => (int)$recovery['recovered']
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  json_fail($e->getMessage(), 400);
}