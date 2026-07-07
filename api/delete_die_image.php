<?php
/**
 * Delete all images for a specific die (by die_id).
 * Uses getDieImages() so it finds images regardless of storage location
 * (subfolder, flat file, or section-prefix match).
 *
 * DELETE /api/delete_die_image.php?die_id=N
 * Response: { success:true, deleted:N }
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/image_helper.php';
requireAdminApi();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'DELETE required']); exit;
}

$dieId = (int)($_GET['die_id'] ?? 0);
if (!$dieId) {
    http_response_code(400);
    echo json_encode(['error' => 'die_id is required']); exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT id, tech_dwg_no, section FROM dies WHERE id = :id");
$stmt->execute([':id' => $dieId]);
$die  = $stmt->fetch();

if (!$die) {
    http_response_code(404);
    echo json_encode(['error' => 'Die not found']); exit;
}

$techDwgNo = trim($die['tech_dwg_no'] ?? '');
$section   = trim($die['section']     ?? '');

// Find actual image paths using the same lookup the display API uses
$imgData = getDieImages($techDwgNo, $section);

$deleted  = 0;
$cleanDirs = [];

foreach ($imgData as $img) {
    $path = $img['path'] ?? '';
    if ($path !== '' && is_file($path) && @unlink($path)) {
        $deleted++;
        $dir = dirname($path);
        $cleanDirs[$dir] = $dir;
    }
}

// Remove now-empty folders
foreach ($cleanDirs as $dir) {
    if (is_dir($dir)) {
        $remaining = array_diff(scandir($dir) ?: [], ['.', '..']);
        if (empty($remaining)) @rmdir($dir);
    }
}

echo json_encode(
    ['success' => true, 'deleted' => $deleted],
    JSON_UNESCAPED_UNICODE
);
