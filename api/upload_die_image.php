<?php
/**
 * Single-image upload for a specific die (by die_id).
 *
 * POST multipart/form-data
 *   die_id  (int, required)   — which die to attach the image to
 *   image   (file, required)  — image file
 *
 * Response:
 *   { success:true, url:"…", filename:"…", die_id:N }
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/image_helper.php';
requireAdminApi();

const SINGLE_MAX_BYTES  = 10 * 1024 * 1024;
const SINGLE_ALLOWED_MIMES = ['image/jpeg','image/jpg','image/png','image/gif','image/webp','image/bmp'];

// Manually-added images are reference thumbnails only — re-encode to a
// small JPEG so ad-hoc uploads don't grow disk usage unbounded.
const UPLOAD_MAX_DIM       = 800;
const UPLOAD_JPEG_QUALITY  = 78;

/**
 * Resize (down only) and re-encode an uploaded image as JPEG to keep
 * on-disk size small. Falls back to copying the original file untouched
 * if GD isn't available or the source can't be decoded.
 */
function compressAndSaveImage(string $srcPath, string $mime, string $destPath): bool {
    if (!extension_loaded('gd')) {
        return copy($srcPath, $destPath);
    }

    $img = match ($mime) {
        'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($srcPath),
        'image/png'               => @imagecreatefrompng($srcPath),
        'image/gif'                => @imagecreatefromgif($srcPath),
        'image/webp'                => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false,
        'image/bmp'                  => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($srcPath) : false,
        default                        => false,
    };
    if (!$img) {
        return copy($srcPath, $destPath);
    }

    $srcW  = imagesx($img);
    $srcH  = imagesy($img);
    $scale = min(1, UPLOAD_MAX_DIM / max($srcW, $srcH));
    $dstW  = max(1, (int) round($srcW * $scale));
    $dstH  = max(1, (int) round($srcH * $scale));

    $dst = imagecreatetruecolor($dstW, $dstH);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

    $ok = imagejpeg($dst, $destPath, UPLOAD_JPEG_QUALITY);

    imagedestroy($img);
    imagedestroy($dst);
    return $ok;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']); exit;
}

$dieId = (int)($_POST['die_id'] ?? 0);
if (!$dieId) {
    http_response_code(400);
    echo json_encode(['error' => 'die_id is required']); exit;
}

// Look up die
$pdo  = getDB();
$stmt = $pdo->prepare("SELECT id, tech_dwg_no, section FROM dies WHERE id = :id");
$stmt->execute([':id' => $dieId]);
$die  = $stmt->fetch();

if (!$die) {
    http_response_code(404);
    echo json_encode(['error' => 'Die not found']); exit;
}

$techDwgNo = strtoupper(trim($die['tech_dwg_no'] ?? ''));
if ($techDwgNo === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Die has no tech_dwg_no — cannot determine image folder']); exit;
}

// Validate file
if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded (code ' . $code . ')']); exit;
}

$f = $_FILES['image'];

if (($f['size'] ?? 0) > SINGLE_MAX_BYTES) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large (max 10 MB)']); exit;
}

// MIME check
$mime = 'unknown';
if (function_exists('finfo_open')) {
    $fi   = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($fi, $f['tmp_name']);
    finfo_close($fi);
}
if (!in_array($mime, SINGLE_ALLOWED_MIMES, true)) {
    http_response_code(400);
    echo json_encode(['error' => "Invalid file type ({$mime})"]); exit;
}

// Always re-encoded to JPEG by compressAndSaveImage() below, regardless of
// the original format, to keep manually-added images small on disk.
$ext = 'jpg';

// Create subfolder
$subDir = IMG_DIR . DIRECTORY_SEPARATOR . $techDwgNo;
if (!is_dir($subDir) && !mkdir($subDir, 0755, true)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not create image folder']); exit;
}

// Name: {techDwgNo}_1.ext, then _2, _3, …
$counter  = 1;
$filename = $techDwgNo . '_' . $counter . '.' . $ext;
$destPath = $subDir . DIRECTORY_SEPARATOR . $filename;
while (file_exists($destPath)) {
    $counter++;
    $filename = $techDwgNo . '_' . $counter . '.' . $ext;
    $destPath = $subDir . DIRECTORY_SEPARATOR . $filename;
}

if (!compressAndSaveImage($f['tmp_name'], $mime, $destPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']); exit;
}

$url = IMG_URL_BASE . '/' . rawurlencode($techDwgNo) . '/' . rawurlencode($filename);

echo json_encode(
    ['success' => true, 'url' => $url, 'filename' => $filename, 'die_id' => $dieId],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
