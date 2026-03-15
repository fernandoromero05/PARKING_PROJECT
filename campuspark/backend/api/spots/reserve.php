<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/utils/validate.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$userId = require_auth();

$data = require_json_body();
require_fields($data, ['spot_id', 'minutes']);

$spotId = (int)$data['spot_id'];
$minutes = (int)$data['minutes'];
if ($minutes <= 0) $minutes = 5;

// Max reservation 15 min for MVP
if ($minutes > 15) $minutes = 15;

$pdo->beginTransaction();
try {
  // Check if user already has an active spot (occupied OR reserved)
  $stmt = $pdo->prepare("
    SELECT id FROM spots 
    WHERE (occupied_by_user_id = ? OR reserved_by_user_id = ?) 
      AND id != ?
  ");
  $stmt->execute([$userId, $userId, $spotId]);
  if ($stmt->fetch()) {
    throw new Exception('You already have an active spot. Release it first.');
  }

  // Get spot details
  $stmt = $pdo->prepare("SELECT id, status, spot_type FROM spots WHERE id=? FOR UPDATE");
  $stmt->execute([$spotId]);
  $spot = $stmt->fetch();
  if (!$spot) throw new Exception('Spot not found');

  if ($spot['status'] !== 'AVAILABLE') {
    throw new Exception('Spot is not available');
  }

  // Check vehicle type for EV spots
  $stmt = $pdo->prepare("SELECT vehicle_type FROM users WHERE id=?");
  $stmt->execute([$userId]);
  $user = $stmt->fetch();
  $isEVUser = ($user['vehicle_type'] === 'ELECTRIC' || $user['vehicle_type'] === 'HYBRID');
  
  if ($spot['spot_type'] === 'EV_ONLY' && !$isEVUser) {
    throw new Exception('This spot is for electric or hybrid cars only');
  }

  // Reserve it
  $stmt = $pdo->prepare("
    UPDATE spots
    SET status='RESERVED',
        reserved_by_user_id=?,
        reserved_until=DATE_ADD(NOW(), INTERVAL ? MINUTE)
    WHERE id=?
  ");
  $stmt->execute([$userId, $minutes, $spotId]);

  $pdo->commit();
  json_ok(['minutes' => $minutes]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_fail($e->getMessage(), 400);
}