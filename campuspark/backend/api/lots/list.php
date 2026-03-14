<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
require_auth();

$rows = $pdo->query("SELECT id, code, name, map_image FROM lots ORDER BY id")->fetchAll();
json_ok(['lots' => $rows]);