<?php
/**
 * ZIP Upload API
 *
 * POST /api/upload_zip.php  (multipart/form-data, field: zip_file)
 * Max: 200 MB
 *
 * Extracts the ZIP and moves each image file into:
 *   /uploads/images/{BASE_DIE_NO}/{filename}
 *
 * BASE_DIE_NO is derived from the filename stem:
 *   "A20829-001_1"  → "A20829-001"
 *   "A20829-001"    → "A20829-001"
 *
 * Returns:
 * {
 *   "total_files":  150,
 *   "matched":       45,
 *   "unmatched":    105,
 *   "errors":        [],
 *   "matched_list": ["A20829-001", ...]
 * }
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/image_helper.php';
requireAdminApi();

const MAX_ZIP_BYTES = 200 * 1024 * 1024;   // 200 MB
const ALLOWED_ZIP_MIMES = [
    'image/jpeg', 'image/jpg', 'image/png',
    'image/gif',  'image/webp', 'image/bmp',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['error' => 'POST method required']);
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo json_encode(['error' => 'ZipArchive extension is not available on this server.']);
    exit;
}

$upload = $_FILES['zip_file'] ?? null;
if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded. Use multipart field name "zip_file".']);
    exit;
}

$errCode = $upload['error'];
if ($errCode !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => zipErrMsg($errCode)]);
    exit;
}

if (($upload['size'] ?? 0) > MAX_ZIP_BYTES || filesize($upload['tmp_name']) > MAX_ZIP_BYTES) {
    http_response_code(422);
    echo json_encode(['error' => 'ZIP file too large. Maximum is 200 MB.']);
    exit;
}

// Ensure destination directory exists
if (!is_dir(IMG_DIR) && !mkdir(IMG_DIR, 0755, true)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not create uploads/images directory.']);
    exit;
}

// Create unique temp extraction directory
$tempDir = IMG_DIR . DIRECTORY_SEPARATOR . 'temp_' . uniqid('zip_', true);
if (!mkdir($tempDir, 0755, true)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not create temporary extraction directory.']);
    exit;
}

// Open and extract ZIP
$zip    = new ZipArchive();
$opened = $zip->open($upload['tmp_name']);
if ($opened !== true) {
    rmdirRecursive($tempDir);
    http_response_code(422);
    echo json_encode(['error' => 'Could not open ZIP file (code ' . $opened . '). It may be corrupted.']);
    exit;
}
$zip->extractTo($tempDir);
$zip->close();

// Recursively collect all extracted file paths
$allPaths = [];
collectFiles($tempDir, $allPaths);

// Process each image file
$totalFiles         = 0;
$errors             = [];
$processedBaseDieNos = [];

foreach ($allPaths as $srcPath) {
    $origName = basename($srcPath);

    // Skip hidden / system files
    if ($origName[0] === '.') continue;

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, IMG_EXTS, true)) continue;

    // MIME verification (skip files that aren't actual images)
    if (function_exists('finfo_open')) {
        $fi   = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($fi, $srcPath);
        finfo_close($fi);
        if (!in_array($mime, ALLOWED_ZIP_MIMES, true)) continue;
    }

    $totalFiles++;
    $safeName = sanitizeUploadFilename($origName);
    if ($safeName === '' || $safeName === '.') {
        $errors[] = "Skipped (invalid name): {$origName}";
        $totalFiles--;
        continue;
    }

    $stem      = pathinfo($safeName, PATHINFO_FILENAME);
    $baseDieNo = strtoupper(extractBaseDieNo($stem));
    $subDir    = IMG_DIR . DIRECTORY_SEPARATOR . $baseDieNo;

    if (!is_dir($subDir) && !mkdir($subDir, 0755, true)) {
        $errors[] = "Could not create folder: {$baseDieNo}";
        $totalFiles--;
        continue;
    }

    // Deduplicate if destination already exists
    $destPath = $subDir . DIRECTORY_SEPARATOR . $safeName;
    if (file_exists($destPath)) {
        $base    = pathinfo($safeName, PATHINFO_FILENAME);
        $extPart = pathinfo($safeName, PATHINFO_EXTENSION);
        $counter = 1;
        do {
            $safeName = "{$base}({$counter}).{$extPart}";
            $destPath = $subDir . DIRECTORY_SEPARATOR . $safeName;
            $counter++;
        } while (file_exists($destPath) && $counter < 100);
    }

    if (!rename($srcPath, $destPath)) {
        $errors[] = "Failed to move: {$origName}";
        continue;
    }

    $processedBaseDieNos[] = $baseDieNo;
}

// Clean up temp directory
rmdirRecursive($tempDir);

// Check which base die nos match dies table (via tech_dwg_no)
$uniqueBaseDieNos = array_unique($processedBaseDieNos);
$matchedList      = [];

if (!empty($uniqueBaseDieNos)) {
    try {
        $pdo          = getDB();
        $placeholders = implode(',', array_fill(0, count($uniqueBaseDieNos), '?'));
        $stmt         = $pdo->prepare(
            "SELECT DISTINCT tech_dwg_no FROM dies
             WHERE UPPER(TRIM(COALESCE(tech_dwg_no,''))) IN ({$placeholders})"
        );
        $stmt->execute(array_map('strtoupper', $uniqueBaseDieNos));
        $matchedList = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'tech_dwg_no');
    } catch (Throwable $e) {
        $errors[] = 'DB check failed: ' . $e->getMessage();
    }
}

$matchedUpper = array_map('strtoupper', $matchedList);
$matchedCount = 0;
foreach ($processedBaseDieNos as $bdn) {
    if (in_array($bdn, $matchedUpper, true)) {
        $matchedCount++;
    }
}
$unmatchedCount = max(0, $totalFiles - $matchedCount - count($errors));

echo json_encode([
    'total_files'  => $totalFiles,
    'matched'      => $matchedCount,
    'unmatched'    => $unmatchedCount,
    'errors'       => $errors,
    'matched_list' => $matchedList,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ── Helpers ──────────────────────────────────────────────────────

function collectFiles(string $dir, array &$out): void {
    $entries = scandir($dir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path)) {
            collectFiles($path, $out);
        } else {
            $out[] = $path;
        }
    }
}

function rmdirRecursive(string $dir): void {
    if (!is_dir($dir)) return;
    $entries = scandir($dir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        is_dir($path) ? rmdirRecursive($path) : @unlink($path);
    }
    @rmdir($dir);
}

function zipErrMsg(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds server upload limit. Increase post_max_size / upload_max_filesize in php.ini.',
        UPLOAD_ERR_PARTIAL    => 'Upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary upload directory.',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by a PHP extension.',
        default               => "Upload error (code {$code}).",
    };
}
