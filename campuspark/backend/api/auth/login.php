<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
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

json_ok([
  'user' => [
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'tokens' => (int)$user['tokens'],
    'vehicle_plate' => $user['vehicle_plate'],
    'vehicle_make' => $user['vehicle_make'],
    'vehicle_type' => $user['vehicle_type']
  ]
]);