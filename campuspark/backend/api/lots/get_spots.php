<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
require_auth();

$lotId = isset($_GET['lot_id']) ? (int)$_GET['lot_id'] : 0;
if ($lotId <= 0) json_fail('lot_id required');

$stmt = $pdo->prepare("
  SELECT
    s.id,
    s.spot_number,
    s.spot_type,
    s.status,
    s.occupied_since,
    s.reserved_until,
    s.vehicle_plate,
    s.vehicle_make,
    s.vehicle_type,
    s.occupied_by_user_id,
    s.reserved_by_user_id,
    u.username AS occupied_by,
    ru.username AS reserved_by
  FROM spots s
  LEFT JOIN users u ON u.id = s.occupied_by_user_id
  LEFT JOIN users ru ON ru.id = s.reserved_by_user_id
  WHERE s.lot_id=?
  ORDER BY s.spot_number
");
$stmt->execute([$lotId]);
$spots = $stmt->fetchAll();

json_ok(['spots' => $spots]);