<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/utils/validate.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$reporterId = require_auth();

$data = require_json_body();
require_fields($data, ['spot_id', 'type']);

$spotId = (int)$data['spot_id'];
$type = (string)$data['type'];
$note = isset($data['note']) ? trim((string)$data['note']) : null;

$allowed = ['POORLY_PARKED','DID_NOT_CLAIM','OTHER'];
if (!in_array($type, $allowed, true)) json_fail('Invalid report type');
if ($spotId <= 0) json_fail('Invalid spot_id');

$pdo->beginTransaction();
try {
  $stmt = $pdo->prepare("SELECT id, status, occupied_by_user_id FROM spots WHERE id=? FOR UPDATE");
  $stmt->execute([$spotId]);
  $spot = $stmt->fetch();
  if (!$spot) throw new Exception('Spot not found');

  $reportedUserId = null;
  if ($spot['status'] === 'OCCUPIED' && !empty($spot['occupied_by_user_id'])) {
    $reportedUserId = (int)$spot['occupied_by_user_id'];
  }

  $stmt = $pdo->prepare("INSERT INTO reports (reporter_user_id, spot_id, reported_user_id, type, note) VALUES (?, ?, ?, ?, ?)");
  $stmt->execute([$reporterId, $spotId, $reportedUserId, $type, $note]);

  // Immediate penalty for POORLY_PARKED if we have a target
  if ($type === 'POORLY_PARKED' && $reportedUserId !== null) {
    $stmt = $pdo->prepare("UPDATE users SET tokens = tokens - 5 WHERE id=?");
    $stmt->execute([$reportedUserId]);

    $stmt = $pdo->prepare("INSERT INTO token_ledger (user_id, delta, reason) VALUES (?, -5, 'Penalty: poorly parked')");
    $stmt->execute([$reportedUserId]);
  }

  // Threshold penalty for DID_NOT_CLAIM
  if ($type === 'DID_NOT_CLAIM' && $reportedUserId !== null) {
    // Count DID_NOT_CLAIM reports for this occupant on this spot (while occupied)
    $stmt = $pdo->prepare("
      SELECT COUNT(*) AS c
      FROM reports
      WHERE spot_id=? AND reported_user_id=? AND type='DID_NOT_CLAIM'
    ");
    $stmt->execute([$spotId, $reportedUserId]);
    $count = (int)$stmt->fetch()['c'];

    // Apply threshold penalty once when crossing >=4, by checking ledger marker
    if ($count >= 4) {
      $stmt = $pdo->prepare("
        SELECT COUNT(*) AS c
        FROM token_ledger
        WHERE user_id=? AND reason='Penalty: did not claim (threshold)'
      ");
      $stmt->execute([$reportedUserId]);
      $already = (int)$stmt->fetch()['c'] > 0;

      if (!$already) {
        $stmt = $pdo->prepare("UPDATE users SET tokens = tokens - 15 WHERE id=?");
        $stmt->execute([$reportedUserId]);

        $stmt = $pdo->prepare("INSERT INTO token_ledger (user_id, delta, reason) VALUES (?, -15, 'Penalty: did not claim (threshold)')");
        $stmt->execute([$reportedUserId]);
      }
    }
  }

  $pdo->commit();
  json_ok(['reported_user_id' => $reportedUserId]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_fail($e->getMessage(), 400);
}