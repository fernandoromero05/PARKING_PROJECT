<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/utils/token_recovery.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$userId = require_auth();

function compute_level(int $tokens): int {
  if ($tokens <= 0) return 1;
  return (int)floor($tokens / 10) + 1;
}

function compute_rating(int $complaints): int {
  if ($complaints === 0) return 5;
  if ($complaints <= 3) return 4;
  if ($complaints <= 6) return 3;
  if ($complaints <= 10) return 2;
  return 1;
}

$pdo->beginTransaction();
try {
  apply_token_recovery($pdo, $userId);

  $stmt = $pdo->prepare("
    SELECT
      id,
      username,
      email,
      tokens,
      role,
      is_banned,
      rating,
      vehicle_plate,
      vehicle_make,
      vehicle_type
    FROM users
    WHERE id=?
    FOR UPDATE
  ");
  $stmt->execute([$userId]);
  $user = $stmt->fetch();

  if (!$user) {
    throw new Exception('User not found');
  }

  $tokens = (int)$user['tokens'];
  $level = compute_level($tokens);

  $stmt = $pdo->prepare("
    SELECT COUNT(*) AS c
    FROM reports
    WHERE reported_user_id=?
  ");
  $stmt->execute([$userId]);
  $complaints = (int)$stmt->fetch()['c'];

  // Use the database rating if it's set, otherwise fallback to computed
  $rating = (float)$user['rating'];

  $stmt = $pdo->prepare("
    SELECT COUNT(*) + 1 AS rank_position
    FROM users
    WHERE tokens > ?
  ");
  $stmt->execute([$tokens]);
  $rank = (int)$stmt->fetch()['rank_position'];

  $stmt = $pdo->query("SELECT COUNT(*) AS total_users FROM users");
  $totalUsers = (int)$stmt->fetch()['total_users'];

  $pdo->commit();

  json_ok([
    'user' => [
      'id' => (int)$user['id'],
      'username' => $user['username'],
      'email' => $user['email'],
      'tokens' => $tokens,
      'role' => $user['role'],
      'is_banned' => (bool)$user['is_banned'],
      'vehicle_plate' => $user['vehicle_plate'],
      'vehicle_make' => $user['vehicle_make'],
      'vehicle_type' => $user['vehicle_type'],
      'level' => $level,
      'rating' => $rating,
      'complaints' => $complaints,
      'rank' => $rank,
      'total_users' => $totalUsers
    ]
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  json_fail($e->getMessage(), 400);
}