<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
require_auth();

$lotId = isset($_GET['lot_id']) ? (int)$_GET['lot_id'] : 0;
if ($lotId <= 0) json_fail('lot_id required');

$stmt = $pdo->prepare("SELECT id, code, name, map_image FROM lots WHERE id=?");
$stmt->execute([$lotId]);
$lot = $stmt->fetch();
if (!$lot) json_fail('Lot not found', 404);

json_ok(['lot' => $lot]);