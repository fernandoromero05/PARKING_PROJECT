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
    role,
    rating,
    is_banned,
    ban_expires_at,
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

// Check for active ban
if ($user['is_banned']) {
  $now = new DateTime('now', new DateTimeZone('UTC'));
  $expires = $user['ban_expires_at']
    ? new DateTime($user['ban_expires_at'], new DateTimeZone('UTC'))
    : null;
  if (!$expires || $expires > $now) {
    $expiresIso = $expires ? $expires->format(DateTime::ATOM) : null;
    json_fail("Your account is banned.", 403, ['ban_expires_at' => $expiresIso]);
  }
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
      role,
      rating,
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
      'role' => $updatedUser['role'],
      'rating' => (float)$updatedUser['rating'],
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