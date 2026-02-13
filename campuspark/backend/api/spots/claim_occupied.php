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
require_fields($data, ['code']);

$code = trim((string)$data['code']);
if ($code === '') json_fail('Empty code');

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
curl_close($ch);

if ($res === false || $http !== 200) json_fail('Python API validation failed', 502);

$decoded = json_decode($res, true);
if (!is_array($decoded) || empty($decoded['ok']) || empty($decoded['lot_code']) || empty($decoded['spot_number'])) {
  json_fail('Invalid validation response', 502);
}

$lotCode = $decoded['lot_code'];
$spotNumber = (int)$decoded['spot_number'];

$pdo->beginTransaction();

try {
  $stmt = $pdo->prepare("SELECT id FROM lots WHERE code=? LIMIT 1");
  $stmt->execute([$lotCode]);
  $lot = $stmt->fetch();
  if (!$lot) throw new Exception('Lot not found');

  $stmt = $pdo->prepare("SELECT id, status, occupied_by_user_id FROM spots WHERE lot_id=? AND spot_number=? FOR UPDATE");
  $stmt->execute([(int)$lot['id'], $spotNumber]);
  $spot = $stmt->fetch();
  if (!$spot) throw new Exception('Spot not found');

  if ($spot['status'] === 'OCCUPIED') {
    $pdo->rollBack();
    json_fail('Spot already occupied', 409, ['occupied_by_user_id' => $spot['occupied_by_user_id']]);
  }

  $stmt = $pdo->prepare("UPDATE spots SET status='OCCUPIED', occupied_by_user_id=?, occupied_since=NOW() WHERE id=?");
  $stmt->execute([$userId, (int)$spot['id']]);

  $stmt = $pdo->prepare("INSERT INTO claims (user_id, spot_id, action) VALUES (?, ?, 'CLAIM')");
  $stmt->execute([$userId, (int)$spot['id']]);

  // Reward: +2 tokens for a normal claim
  $stmt = $pdo->prepare("UPDATE users SET tokens = tokens + 2 WHERE id=?");
  $stmt->execute([$userId]);

  $stmt = $pdo->prepare("INSERT INTO token_ledger (user_id, delta, reason) VALUES (?, 2, 'Clean parking claim')");
  $stmt->execute([$userId]);

  $pdo->commit();
  json_ok(['lot_code' => $lotCode, 'spot_number' => $spotNumber]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_fail($e->getMessage(), 400);
}