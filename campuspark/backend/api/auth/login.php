<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/utils/token_recovery.php';
require_once __DIR__ . '/../../src/utils/validate.php';

enable_cors();
start_session();

$data = require_json_body();
require_fields($data, ['username_or_email', 'password']);

$u = trim($data['username_or_email']);
$p = (string)$data['password'];

$stmt = $pdo->prepare("
  SELECT
    id,
    username,
    email,
    password_hash,
    tokens,
    vehicle_plate,
    vehicle_make,
    vehicle_type
  FROM users
  WHERE username=? OR email=?
  LIMIT 1
");
$stmt->execute([$u, $u]);
$user = $stmt->fetch();

if (!$user || !password_verify($p, $user['password_hash'])) {
  json_fail('Invalid credentials', 401);
}

$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = $user['username'];

$pdo->beginTransaction();
try {
  $recovery = apply_token_recovery($pdo, (int)$user['id']);

  $stmt = $pdo->prepare("
    SELECT
      id,
      username,
      email,
      tokens,
      vehicle_plate,
      vehicle_make,
      vehicle_type
    FROM users
    WHERE id=?
    FOR UPDATE
  ");
  $stmt->execute([(int)$user['id']]);
  $updatedUser = $stmt->fetch();

  if (!$updatedUser) {
    throw new Exception('User not found');
  }

  $pdo->commit();

  json_ok([
    'user' => [
      'id' => (int)$updatedUser['id'],
      'username' => $updatedUser['username'],
      'email' => $updatedUser['email'],
      'tokens' => (int)$updatedUser['tokens'],
      'vehicle_plate' => $updatedUser['vehicle_plate'],
      'vehicle_make' => $updatedUser['vehicle_make'],
      'vehicle_type' => $updatedUser['vehicle_type']
    ],
    'recovered' => (int)$recovery['recovered']
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  json_fail($e->getMessage(), 400);
}