<?php
declare(strict_types=1);

function apply_token_recovery(PDO $pdo, int $userId): array
{
    $RECOVERY_INTERVAL_MINUTES = 1; // +1 token cada 30 min
    $MAX_RECOVERY_TARGET = 0;        // solo recupera hasta 0

    $stmt = $pdo->prepare("
        SELECT id, tokens, token_recovery_updated_at
        FROM users
        WHERE id=?
        FOR UPDATE
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    $tokens = (int)$user['tokens'];

    if ($tokens >= $MAX_RECOVERY_TARGET) {
        return ['tokens' => $tokens, 'recovered' => 0];
    }

    $lastUpdate = new DateTime($user['token_recovery_updated_at']);
    $now = new DateTime();

    $elapsedSeconds = $now->getTimestamp() - $lastUpdate->getTimestamp();
    $intervalSeconds = $RECOVERY_INTERVAL_MINUTES * 60;

    if ($elapsedSeconds < $intervalSeconds) {
        return ['tokens' => $tokens, 'recovered' => 0];
    }

    $steps = intdiv($elapsedSeconds, $intervalSeconds);
    $recoverAmount = min($steps, abs($tokens));
    $newTokens = min($tokens + $recoverAmount, $MAX_RECOVERY_TARGET);

    $usedSeconds = $recoverAmount * $intervalSeconds;
    $newTimestamp = (clone $lastUpdate)->modify("+{$usedSeconds} seconds")->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        UPDATE users
        SET tokens=?, token_recovery_updated_at=?
        WHERE id=?
    ");
    $stmt->execute([$newTokens, $newTimestamp, $userId]);

    if ($recoverAmount > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO token_ledger (user_id, delta, reason)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $recoverAmount, 'Automatic token recovery']);
    }

    return ['tokens' => $newTokens, 'recovered' => $recoverAmount];
}