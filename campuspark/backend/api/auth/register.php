<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/utils/validate.php';

enable_cors();
start_session();

$data = require_json_body();
require_fields($data, ['username', 'email', 'password', 'vehicle_plate', 'vehicle_make', 'vehicle_type']);

$username = trim($data['username']);
$email = trim($data['email']);
$password = (string)$data['password'];
$vehiclePlate = strtoupper(trim((string)$data['vehicle_plate']));
$vehicleMake = trim((string)$data['vehicle_make']);
$vehicleType = trim((string)$data['vehicle_type']);

if (strlen($username) < 3) json_fail('Username too short');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_fail('Invalid email');
if (strlen($password) < 6) json_fail('Password too short (min 6)');
if ($vehiclePlate === '') json_fail('License plate required');
if ($vehicleMake === '') json_fail('Vehicle make/model required');

$allowedVehicleTypes = ['ELECTRIC', 'HYBRID', 'DIESEL_GAS'];
if (!in_array($vehicleType, $allowedVehicleTypes, true)) {
  json_fail('Invalid vehicle type');
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
  $stmt = $pdo->prepare("
    INSERT INTO users (username, email, password_hash, vehicle_plate, vehicle_make, vehicle_type)
    VALUES (?, ?, ?, ?, ?, ?)
  ");
  $stmt->execute([$username, $email, $hash, $vehiclePlate, $vehicleMake, $vehicleType]);

  $userId = (int)$pdo->lastInsertId();

  $_SESSION['user_id'] = $userId;
  $_SESSION['username'] = $username;

  json_ok([
    'user' => [
      'id' => $userId,
      'username' => $username,
      'email' => $email,
      'vehicle_plate' => $vehiclePlate,
      'vehicle_make' => $vehicleMake,
      'vehicle_type' => $vehicleType
    ]
  ]);
} catch (PDOException $e) {
  json_fail('Username or email already exists', 409);
}