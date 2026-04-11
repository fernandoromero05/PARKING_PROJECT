<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/utils/token_recovery.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$userId = require_auth();

function compute_level(int $tokens, float $rating): int {
  if ($tokens <= 0) return 1;
  $tokenLevel = (int)floor($tokens / 10) + 1;
  
  // Cap level based on rating (e.g., if rating is 1.0, cap is Level 1)
  // A rating of 5.0 allows up to Level 10+
  $ratingCap = (int)max(1, floor($rating * 2));
  
  return min($tokenLevel, $ratingCap);
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
  $rating = (float)$user['rating'];
  $level = compute_level($tokens, $rating);

  $stmt = $pdo->prepare("
    SELECT COUNT(*) AS c
    FROM reports
    WHERE reported_user_id=?
  ");
  $stmt->execute([$userId]);
  $complaints = (int)$stmt->fetch()['c'];

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