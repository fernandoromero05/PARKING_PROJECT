<?php
declare(strict_types=1);

function require_auth(): int {
  start_session();
  if (!isset($_SESSION['user_id'])) {
    json_fail('Not authenticated', 401);
  }

  $userId = (int)$_SESSION['user_id'];

  // Check ban status if PDO is available
  global $pdo;
  if (isset($pdo)) {
    $stmt = $pdo->prepare("SELECT is_banned, ban_expires_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user && $user['is_banned']) {
      $now = new DateTime();
      $expires = $user['ban_expires_at'] ? new DateTime($user['ban_expires_at']) : null;

      if (!$expires || $expires > $now) {
        json_fail('Your account is temporarily banned due to poor ranking or low token balance.', 403);
      } else {
        // Ban expired, lift it automatically and reset tokens to 5
        $stmt = $pdo->prepare("
          UPDATE users 
          SET is_banned = FALSE, 
              ban_expires_at = NULL,
              tokens = 5,
              token_recovery_updated_at = NOW() 
          WHERE id = ?
        ");
        $stmt->execute([$userId]);

        // Log the recovery in the ledger
        $stmt = $pdo->prepare("
          INSERT INTO token_ledger (user_id, delta, reason)
          VALUES (?, 5, 'Account restored after ban: Token reset to 5')
        ");
        $stmt->execute([$userId]);
      }
    }
  }

  return $userId;
}