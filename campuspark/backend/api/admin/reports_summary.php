<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/utils/response.php';
require_once __DIR__ . '/../../src/middleware/auth_guard.php';

enable_cors();
$userId = require_auth();

// Role-based access control: Check if user is an ADMIN
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'ADMIN') {
    json_fail('Access denied: Administrator privileges required', 403);
}

try {
    // Aggregate report counts by type
    $stmt = $pdo->query("
        SELECT type, COUNT(*) as total 
        FROM reports 
        GROUP BY type
    ");
    $reportStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total unique problems (reports)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reports");
    $totalReports = (int)$stmt->fetchColumn();

    // Get summary of reported users (top offenders)
    $stmt = $pdo->query("
        SELECT u.username, COUNT(r.id) as report_count 
        FROM reports r 
        JOIN users u ON r.reported_user_id = u.id 
        GROUP BY u.id 
        ORDER BY report_count DESC 
        LIMIT 10
    ");
    $topOffenders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    json_ok([
        'summary' => [
            'total_reports' => $totalReports,
            'stats_by_type' => $reportStats,
            'top_offenders' => $topOffenders
        ]
    ]);
} catch (Throwable $e) {
    json_fail($e->getMessage(), 500);
}