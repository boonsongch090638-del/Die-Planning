<?php
/**
 * Bulk Image Upload API
 *
 * POST /api/upload_images.php
 *   Files field: images[]   (multipart/form-data, multiple files)
 *
 * Response: {
 *   "success": true,
 *   "uploaded": 3,
 *   "failed":   1,
 *   "results":  [
 *     { "name": "A80008-001.jpg", "status": "ok",     "url": "/PlanningDie/uploads/images/A80008-001.jpg" },
 *     { "name": "bad.exe",        "status": "error",  "error": "Invalid file type" }
 *   ]
 * }
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/image_helper.php';
requireAdminApi();

const MAX_IMAGE_BYTES = 10 * 1024 * 1024;    // 10 MB per file
const ALLOWED_IMG_MIMES = [
    'image/jpeg', 'image/jpg', 'image/png',
    'image/gif',  'image/webp', 'image/bmp',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['error' => 'POST method required']);
    exit;
}

if (empty($_FILES['images'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No files received. Use field name "images[]".']);
    exit;
}

// Normalize $_FILES['images'] into a flat array of individual file entries
$raw     = $_FILES['images'];
$fileList = [];

if (is_array($raw['name'])) {
    for ($i = 0; $i < count($raw['name']); $i++) {
        $fileList[] = [
            'name'     => $raw['name'][$i],
            'type'     => $raw['type'][$i],
            'tmp_name' => $raw['tmp_name'][$i],
            'error'    => $raw['error'][$i],
            'size'     => $raw['size'][$i],
        ];
    }
} else {
    $fileList[] = $raw;
}

// Ensure base uploads/images directory exists
if (!is_dir(IMG_DIR) && !mkdir(IMG_DIR, 0755, true)) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not create uploads/images directory.']);
    exit;
}

$results  = [];
$uploaded = 0;
$failed   = 0;

foreach ($fileList as $file) {
    $origName = $file['name']     ?? 'upload';
    $tmpPath  = $file['tmp_name'] ?? '';
    $errCode  = $file['error']    ?? UPLOAD_ERR_NO_FILE;

    // PHP upload error
    if ($errCode !== UPLOAD_ERR_OK) {
        $results[] = ['name' => $origName, 'status' => 'error', 'error' => uploadErrMsg($errCode)];
        $failed++;
        continue;
    }

    // Size check
    if (($file['size'] ?? 0) > MAX_IMAGE_BYTES || filesize($tmpPath) > MAX_IMAGE_BYTES) {
        $results[] = ['name' => $origName, 'status' => 'error', 'error' => 'File too large (max 10 MB)'];
        $failed++;
        continue;
    }

    // MIME check via finfo
    $mime = 'unknown';
    if (function_exists('finfo_open')) {
        $fi   = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($fi, $tmpPath);
        finfo_close($fi);
    }

    if (!in_array($mime, ALLOWED_IMG_MIMES, true)) {
        $results[] = ['name' => $origName, 'status' => 'error', 'error' => "Invalid file type ({$mime})"];
        $failed++;
        continue;
    }

    // Sanitize filename
    $safeName = sanitizeUploadFilename($origName);
    if ($safeName === '' || $safeName === '.') {
        $results[] = ['name' => $origName, 'status' => 'error', 'error' => 'Invalid filename'];
        $failed++;
        continue;
    }

    // Derive subfolder from base die no (e.g. "A20829-001_1.jpg" → folder "A20829-001")
    $stem      = pathinfo($safeName, PATHINFO_FILENAME);
    $baseDieNo = strtoupper(extractBaseDieNo($stem));
    $subDir    = IMG_DIR . DIRECTORY_SEPARATOR . $baseDieNo;

    if (!is_dir($subDir) && !mkdir($subDir, 0755, true)) {
        $results[] = ['name' => $origName, 'status' => 'error', 'error' => 'Could not create subfolder'];
        $failed++;
        continue;
    }

    // Deduplicate: if file exists, append a counter
    $destPath = $subDir . DIRECTORY_SEPARATOR . $safeName;
    if (file_exists($destPath)) {
        $ext     = pathinfo($safeName, PATHINFO_EXTENSION);
        $base    = pathinfo($safeName, PATHINFO_FILENAME);
        $counter = 1;
        do {
            $safeName = "{$base}({$counter}).{$ext}";
            $destPath = $subDir . DIRECTORY_SEPARATOR . $safeName;
            $counter++;
        } while (file_exists($destPath));
    }

    if (!move_uploaded_file($tmpPath, $destPath)) {
        $results[] = ['name' => $origName, 'status' => 'error', 'error' => 'Failed to save file'];
        $failed++;
        continue;
    }

    $uploaded++;
    $results[] = [
        'name'   => $safeName,
        'status' => 'ok',
        'url'    => IMG_URL_BASE . '/' . rawurlencode($baseDieNo) . '/' . rawurlencode($safeName),
    ];
}

echo json_encode(
    ['success' => $failed === 0, 'uploaded' => $uploaded, 'failed' => $failed, 'results' => $results],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

function uploadErrMsg(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large.',
        UPLOAD_ERR_PARTIAL    => 'Upload was partial.',
        UPLOAD_ERR_NO_FILE    => 'No file uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory.',
        UPLOAD_ERR_CANT_WRITE => 'Could not write to disk.',
        UPLOAD_ERR_EXTENSION  => 'Blocked by server extension.',
        default               => "Upload error code {$code}.",
    };
}
