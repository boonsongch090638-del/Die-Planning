<?php
/**
 * Image Management API
 *
 * GET  /api/image_management.php
 *   → { total_files, total_mb, folder_count, flat_count, db_matched, folders[] }
 *
 * POST /api/image_management.php   body: { "action": "clear" | "rescan" }
 *   clear  → Delete all images and return updated stats
 *   rescan → Return current stats with fresh DB match count
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/image_helper.php';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        echo json_encode(imageStats(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    case 'POST':
        requireAdminApi();
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $body['action'] ?? '';

        if ($action === 'clear') {
            clearAllImages();
            $stats = imageStats();
            echo json_encode(['success' => true] + $stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($action === 'rescan') {
            echo json_encode(imageStats(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action. Use "clear" or "rescan".']);
        }
        break;

    default:
        header('Allow: GET, POST');
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

// ── Helpers ──────────────────────────────────────────────────────

function imageStats(): array {
    if (!is_dir(IMG_DIR)) {
        return [
            'total_files'  => 0,
            'total_bytes'  => 0,
            'total_mb'     => '0.00',
            'folder_count' => 0,
            'flat_count'   => 0,
            'db_matched'   => 0,
            'folders'      => [],
        ];
    }

    $entries    = scandir(IMG_DIR, SCANDIR_SORT_ASCENDING) ?: [];
    $folders    = [];
    $flatCount  = 0;
    $totalBytes = 0;
    $folderNames = [];

    foreach ($entries as $entry) {
        if ($entry[0] === '.') continue;
        $full = IMG_DIR . DIRECTORY_SEPARATOR . $entry;

        if (is_dir($full)) {
            // Skip temp extraction dirs
            if ($entry === 'temp_extract' || str_starts_with($entry, 'temp_')) continue;

            $count    = 0;
            $subBytes = 0;
            $subs     = scandir($full) ?: [];
            foreach ($subs as $sf) {
                if ($sf[0] === '.') continue;
                $sfPath = $full . DIRECTORY_SEPARATOR . $sf;
                if (is_file($sfPath)) {
                    $ext = strtolower(pathinfo($sf, PATHINFO_EXTENSION));
                    if (in_array($ext, IMG_EXTS, true)) {
                        $count++;
                        $subBytes += filesize($sfPath) ?: 0;
                    }
                }
            }
            $folders[]   = ['name' => $entry, 'count' => $count];
            $totalBytes += $subBytes;
            $folderNames[] = $entry;
        } elseif (is_file($full)) {
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (in_array($ext, IMG_EXTS, true)) {
                $flatCount++;
                $totalBytes += filesize($full) ?: 0;
            }
        }
    }

    $totalFiles = $flatCount + (int) array_sum(array_column($folders, 'count'));

    // Count how many folders match a dies.tech_dwg_no
    $dbMatched = 0;
    if (!empty($folderNames)) {
        try {
            $pdo          = getDB();
            $placeholders = implode(',', array_fill(0, count($folderNames), '?'));
            $stmt         = $pdo->prepare(
                "SELECT COUNT(DISTINCT UPPER(TRIM(COALESCE(tech_dwg_no,'')))) AS c
                 FROM dies
                 WHERE UPPER(TRIM(COALESCE(tech_dwg_no,''))) IN ({$placeholders})"
            );
            $stmt->execute(array_map('strtoupper', $folderNames));
            $dbMatched = (int) ($stmt->fetchColumn() ?: 0);
        } catch (Throwable) {}
    }

    return [
        'total_files'  => $totalFiles,
        'total_bytes'  => $totalBytes,
        'total_mb'     => number_format($totalBytes / 1048576, 2),
        'folder_count' => count($folders),
        'flat_count'   => $flatCount,
        'db_matched'   => $dbMatched,
        'folders'      => $folders,
    ];
}

function clearAllImages(): void {
    if (!is_dir(IMG_DIR)) return;
    $entries = scandir(IMG_DIR) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $full = IMG_DIR . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($full)) {
            rmdirRec($full);
        } else {
            @unlink($full);
        }
    }
}

function rmdirRec(string $dir): void {
    if (!is_dir($dir)) return;
    $entries = scandir($dir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        is_dir($path) ? rmdirRec($path) : @unlink($path);
    }
    @rmdir($dir);
}
