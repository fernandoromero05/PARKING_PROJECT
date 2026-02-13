<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$userId = require_auth();

$stmt = $pdo->prepare("SELECT id, username, email, tokens FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
json_ok(['user' => ['id'=>(int)$user['id'], 'username'=>$user['username'], 'email'=>$user['email'], 'tokens'=>(int)$user['tokens']]]);