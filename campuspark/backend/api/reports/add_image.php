<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$actorId = require_auth();

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$actorId]);
$actor = $stmt->fetch();
if (!$actor || !in_array($actor['role'], ['ENFORCER', 'ADMIN'], true)) {
    json_fail('Access denied', 403);
}

$reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
if ($reportId <= 0) json_fail('Invalid report_id');

if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
    json_fail('Image is required');
}

$file = $_FILES['image'];
if ($file['error'] !== UPLOAD_ERR_OK) json_fail('File upload error');
if ($file['size'] > 5 * 1024 * 1024)  json_fail('Image exceeds 5 MB limit');

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowedMimes, true)) json_fail('Invalid image type (jpeg/png/webp/gif only)');

$ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
$dir      = __DIR__ . '/../../uploads/reports/';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$filename = bin2hex(random_bytes(16)) . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) json_fail('Failed to save image');

$imageUrl = '/campuspark/backend/uploads/reports/' . $filename;

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT id, image_url FROM reports WHERE id = ? FOR UPDATE");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();
    if (!$report) throw new Exception('Report not found');
    if ($report['image_url']) throw new Exception('This report already has an image attached');

    $stmt = $pdo->prepare("
        UPDATE reports
        SET image_url = ?, enforcer_user_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$imageUrl, $actorId, $reportId]);

    $pdo->commit();
    json_ok(['image_url' => $imageUrl]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_fail($e->getMessage(), 400);
}
