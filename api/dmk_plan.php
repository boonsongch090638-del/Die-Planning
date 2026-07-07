<?php
/**
 * DMK Plan API
 *
 * GET /api/dmk_plan.php?week=16/2026
 *
 * Returns:
 * {
 *   "week":  "16/2026",
 *   "total": 12,
 *   "data":  [ { ...die fields, die_no, die_pc_status, images[], image_path } ]
 * }
 *
 * Image lookup: scans uploads/images/ for filenames that begin with the
 * die's `no` field (e.g. "A80008" matches "A80008-001_1.jpg").
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/image_helper.php';

try {
    $pdo  = getDB();
    $week = trim($_GET['week'] ?? '');

    $allWeeks = ($week === '' || strtolower($week) === 'all');

    // Default to current ISO week only when no explicit param given via direct API access
    if ($week === '') {
        $now  = new DateTime();
        $week = ltrim($now->format('W'), '0') . '/' . $now->format('Y');
        $allWeeks = false;
    }

    $orderBy = "ORDER BY
        CASE WHEN die_pc_due_date IS NULL OR TRIM(die_pc_due_date) = '' THEN 1 ELSE 0 END,
        die_pc_due_date ASC, machine ASC, id ASC";

    if ($allWeeks) {
        $stmt = $pdo->query("SELECT * FROM dies {$orderBy}");
        $dies = $stmt->fetchAll();
        $week = 'all';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM dies WHERE TRIM(die_finish_plan) = :week {$orderBy}");
        $stmt->execute([':week' => $week]);
        $dies = $stmt->fetchAll();
    }

    foreach ($dies as &$die) {
        // Computed fields
        $die['die_no']        = trim($die['section'] ?? '') . '-' . trim($die['index_tab'] ?? '');
        $die['die_pc_status'] = !empty($die['die_pc_actual_date']) ? 'Finish' : 'Pending';

        // Image lookup: subfolder first, then flat fallback by tech_dwg_no, then section prefix
        $techDwgNo = trim($die['tech_dwg_no'] ?? '');
        $section   = trim($die['section'] ?? '');
        $die['images']     = findImageUrlsForDie($techDwgNo, $section);
        $die['image_path'] = $die['images'][0] ?? null;
    }
    unset($die);

    echo json_encode(
        ['week' => $week, 'total' => count($dies), 'data' => $dies],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
