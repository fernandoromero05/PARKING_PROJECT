<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$reporterId = require_auth();

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$reporterId]);
$reporter = $stmt->fetch();
if (!$reporter || !in_array($reporter['role'], ['ENFORCER', 'ADMIN'], true)) {
    json_fail('Access denied', 403);
}

$spotId = isset($_POST['spot_id']) ? (int)$_POST['spot_id'] : 0;
$type   = isset($_POST['type'])    ? trim((string)$_POST['type'])  : '';
$note   = isset($_POST['note'])    ? trim((string)$_POST['note'])  : null;

if ($spotId <= 0) json_fail('Invalid spot_id');

$allowed = [
    'POORLY_PARKED',
    'DID_NOT_CLAIM',
    'DISRESPECTFUL_DRIVER',
    'EXCEEDS_TIME_LIMIT',
    'BLOCKING_SPOT',
    'PARKED_OVER_LINE',
    'UNAUTHORIZED_PARKING',
    'ABANDONED_VEHICLE',
    'OTHER'
];
if (!in_array($type, $allowed, true)) json_fail('Invalid report type');

$imageUrl = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) json_fail('File upload error');
    if ($file['size'] > 5 * 1024 * 1024) json_fail('Image exceeds 5 MB limit');

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMimes, true)) json_fail('Invalid image type (jpeg/png/webp/gif only)');

    $ext     = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
    $dir     = __DIR__ . '/../../uploads/reports/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) json_fail('Failed to save image');
    $imageUrl = '/campuspark/backend/uploads/reports/' . $filename;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT id, status, occupied_by_user_id FROM spots WHERE id=? FOR UPDATE");
    $stmt->execute([$spotId]);
    $spot = $stmt->fetch();
    if (!$spot) throw new Exception('Spot not found');

    $reportedUserId = null;
    if ($spot['status'] === 'OCCUPIED' && !empty($spot['occupied_by_user_id'])) {
        $reportedUserId = (int)$spot['occupied_by_user_id'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO reports (reporter_user_id, spot_id, reported_user_id, type, note, image_url)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$reporterId, $spotId, $reportedUserId, $type, $note ?: null, $imageUrl]);

    if ($reportedUserId !== null) {
        $penalty = 10;
        $reason  = "Penalty: " . strtolower(str_replace('_', ' ', $type));

        $stmt = $pdo->prepare("UPDATE users SET rating = GREATEST(1.0, rating - 0.5) WHERE id=?");
        $stmt->execute([$reportedUserId]);

        if (!in_array($type, ['POORLY_PARKED', 'DID_NOT_CLAIM'], true)) {
            $stmt = $pdo->prepare("
                UPDATE users
                SET tokens = tokens - ?,
                    token_recovery_updated_at = NOW()
                WHERE id=?
            ");
            $stmt->execute([$penalty, $reportedUserId]);

            $stmt = $pdo->prepare("INSERT INTO token_ledger (user_id, delta, reason) VALUES (?, ?, ?)");
            $stmt->execute([$reportedUserId, -$penalty, $reason]);
        }
    }

    if ($type === 'POORLY_PARKED' && $reportedUserId !== null) {
        $stmt = $pdo->prepare("
            UPDATE users
            SET tokens = tokens - 5,
                token_recovery_updated_at = NOW()
            WHERE id=?
        ");
        $stmt->execute([$reportedUserId]);

        $stmt = $pdo->prepare("INSERT INTO token_ledger (user_id, delta, reason) VALUES (?, -5, 'Penalty: poorly parked')");
        $stmt->execute([$reportedUserId]);
    }

    if ($type === 'DID_NOT_CLAIM' && $reportedUserId !== null) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS c
            FROM reports
            WHERE spot_id=? AND reported_user_id=? AND type='DID_NOT_CLAIM'
        ");
        $stmt->execute([$spotId, $reportedUserId]);
        $count = (int)$stmt->fetch()['c'];

        if ($count >= 4) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS c
                FROM token_ledger
                WHERE user_id=? AND reason='Penalty: did not claim (threshold)'
            ");
            $stmt->execute([$reportedUserId]);
            $already = (int)$stmt->fetch()['c'] > 0;

            if (!$already) {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET tokens = tokens - 15,
                        token_recovery_updated_at = NOW()
                    WHERE id=?
                ");
                $stmt->execute([$reportedUserId]);

                $stmt = $pdo->prepare("INSERT INTO token_ledger (user_id, delta, reason) VALUES (?, -15, 'Penalty: did not claim (threshold)')");
                $stmt->execute([$reportedUserId]);
            }
        }
    }

    if ($reportedUserId !== null) {
        $stmt = $pdo->prepare("SELECT tokens FROM users WHERE id=?");
        $stmt->execute([$reportedUserId]);
        $currentTokens = (int)$stmt->fetchColumn();

        if ($currentTokens <= 0) {
            $stmt = $pdo->prepare("
                UPDATE users
                SET is_banned = TRUE,
                    ban_expires_at = DATE_ADD(NOW(), INTERVAL 1 DAY),
                    rating = 1.0
                WHERE id=?
            ");
            $stmt->execute([$reportedUserId]);
        }
    }

    $pdo->commit();
    json_ok(['reported_user_id' => $reportedUserId, 'image_url' => $imageUrl]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_fail($e->getMessage(), 400);
}
