<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';

enable_cors();

// Cleanup expired reservations
$stmt = $pdo->prepare("
  UPDATE spots
  SET status='AVAILABLE',
      reserved_by_user_id=NULL,
      reserved_until=NULL
  WHERE status='RESERVED' AND reserved_until < NOW()
");
$stmt->execute();

json_ok(['cleaned' => $stmt->rowCount()]);