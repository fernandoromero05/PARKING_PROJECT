<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$userId = require_auth();

$stmt = $pdo->prepare("SELECT delta, reason, created_at FROM token_ledger WHERE user_id=? ORDER BY id DESC LIMIT 50");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll();

json_ok(['ledger' => $rows]);