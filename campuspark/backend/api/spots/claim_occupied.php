<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/utils/validate.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$userId = require_auth();

$config = require __DIR__ . '/../../config/config.php';
$data = require_json_body();
require_fields($data, ['code', 'plate', 'make', 'vehicle_type']);

$code = trim((string)$data['code']);
$plate = strtoupper(trim((string)$data['plate']));
$make = trim((string)$data['make']);
$vehicleType = trim((string)$data['vehicle_type']);

if ($code === '') json_fail('Empty code');
if ($plate === '') json_fail('License plate required');
if ($make === '') json_fail('Vehicle make required');

$allowedVehicleTypes = ['ELECTRIC', 'HYBRID', 'DIESEL_GAS'];
if (!in_array($vehicleType, $allowedVehicleTypes, true)) {
  json_fail('Invalid vehicle type');
}

$pyUrl = rtrim($config['python_api_base'], '/') . '/validate_code';
$payload = json_encode(['code' => $code]);

$ch = curl_init($pyUrl);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => $payload,
  CURLOPT_TIMEOUT => 4,
]);

$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($res === false || $http !== 200) {
  json_fail('Python API validation failed', 502, [
    'http' => $http,
    'curl_error' => $curlError,
    'response' => $res
  ]);
}

$decoded = json_decode($res, true);

if (!is_array($decoded)) {
  json_fail('Invalid validation response', 502, ['raw' => $res]);
}

if (empty($decoded['ok'])) {
  json_fail('Validation failed', 400, ['python' => $decoded]);
}

if (empty($decoded['lot_code']) || empty($decoded['spot_number'])) {
  json_fail('Invalid validation response', 502, ['python' => $decoded]);
}

$lotCode = $decoded['lot_code'];
$spotNumber = (int)$decoded['spot_number'];

$pdo->beginTransaction();

try {
  $stmt = $pdo->prepare("SELECT id FROM lots WHERE code=? LIMIT 1");
  $stmt->execute([$lotCode]);
  $lot = $stmt->fetch();

  if (!$lot) {
    throw new Exception('Lot not found');
  }

  $stmt = $pdo->prepare("
    SELECT
      id,
      status,
      spot_type,
      occupied_by_user_id,
      reserved_by_user_id
    FROM spots
    WHERE lot_id=? AND spot_number=?
    FOR UPDATE
  ");
  $stmt->execute([(int)$lot['id'], $spotNumber]);
  $spot = $stmt->fetch();

  if (!$spot) {
    throw new Exception('Spot not found');
  }

  // Check if user already has an active spot (occupied OR reserved)
  $stmt = $pdo->prepare("
    SELECT id FROM spots 
    WHERE (occupied_by_user_id = ? OR (reserved_by_user_id = ? AND status = 'RESERVED')) 
      AND id != ?
  ");
  $stmt->execute([$userId, $userId, (int)$spot['id']]);
  if ($stmt->fetch()) {
    $pdo->rollBack();
    json_fail('You already have an active spot. Release it first.', 400);
  }

  // Check if user already has a reserved spot (optional, but good for UX)
  // If they are claiming THEIR OWN reserved spot, that's fine.

  if ($spot['spot_type'] === 'EV_ONLY' && !in_array($vehicleType, ['ELECTRIC', 'HYBRID'], true)) {
    $pdo->rollBack();
    json_fail('This spot is for electric or hybrid cars only', 403);
  }

  if ($spot['status'] === 'OCCUPIED') {
    $pdo->rollBack();
    json_fail('Spot already occupied', 409, [
      'occupied_by_user_id' => $spot['occupied_by_user_id']
    ]);
  }

  if ($spot['status'] === 'RESERVED' && (int)$spot['reserved_by_user_id'] !== $userId) {
    $pdo->rollBack();
    json_fail('Spot is reserved by another user', 403);
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
    'lot_code' => $lotCode,
    'spot_number' => $spotNumber
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  json_fail($e->getMessage(), 400);
}