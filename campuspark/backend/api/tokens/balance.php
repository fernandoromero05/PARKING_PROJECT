<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$userId = require_auth();

$stmt = $pdo->prepare("SELECT tokens FROM users WHERE id=?");
$stmt->execute([$userId]);
$row = $stmt->fetch();

json_ok(['tokens' => (int)$row['tokens']]);