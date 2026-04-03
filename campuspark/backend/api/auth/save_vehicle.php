<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/utils/validate.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$userId = require_auth();

$data = require_json_body();
require_fields($data, ['plate', 'make', 'vehicle_type']);

$plate = strtoupper(trim((string)$data['plate']));
$make = trim((string)$data['make']);
$vehicleType = trim((string)$data['vehicle_type']);

if ($plate === '') json_fail('Matricula requerida');
if ($make === '') json_fail('Marca requerida');

$allowedVehicleTypes = ['ELECTRIC', 'HYBRID', 'DIESEL_GAS'];
if (!in_array($vehicleType, $allowedVehicleTypes, true)) {
  json_fail('Tipo de vehículo inválido');
}

$stmt = $pdo->prepare("
  UPDATE users
  SET vehicle_plate=?, vehicle_make=?, vehicle_type=?
  WHERE id=?
");
$stmt->execute([$plate, $make, $vehicleType, $userId]);

json_ok([
  'vehicle' => [
    'plate' => $plate,
    'make' => $make,
    'type' => $vehicleType
  ]
]);