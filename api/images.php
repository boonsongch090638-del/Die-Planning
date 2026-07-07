<?php
/**
 * Images API
 *
 * GET  /api/images.php?die_no=A80008
 *   → Scan uploads/images/ for files whose name starts with "A80008"
 *   → Return JSON:
 *     {
 *       "die_no": "A80008",
 *       "count":  2,
 *       "files":  [
 *         { "filename": "A80008-001_1.jpg", "url": "/PlanningDie/uploads/images/A80008-001_1.jpg" },
 *         ...
 *       ]
 *     }
 *
 * POST /api/images.php  (multipart/form-data, field: "file")
 *   → Accept single image upload, validate JPG/PNG, max 10 MB
 *   → Save to uploads/images/
 *   → Return JSON:
 *     { "success": true, "filename": "A80008-001.jpg", "url": "..." }
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/image_helper.php';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        requireAdminApi();
        handlePost();
        break;
    default:
        header('Allow: GET, POST');
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed. Use GET or POST.']);
}

// ── GET: list matching images ────────────────────────────────────

function handleGet(): void {
    $techDwgNo = trim($_GET['tech_dwg_no'] ?? $_GET['die_no'] ?? '');

    if ($techDwgNo === '') {
        http_response_code(400);
        echo json_encode(['error' => 'tech_dwg_no parameter is required']);
        return;
    }

    $section = trim($_GET['section'] ?? '');
    $imgs    = getDieImages($techDwgNo, $section);

    $files = array_map(
        static fn(array $img): array => [
            'filename' => $img['filename'],
            'url'      => $img['url'],
        ],
        $imgs
    );

    echo json_encode(
        [
            'tech_dwg_no'  => $techDwgNo,
            'count'        => count($files),
            'files'        => $files,
            'all_variants' => array_column($files, 'url'),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

// ── POST: upload a single image ──────────────────────────────────

function handlePost(): void {
    // Expect field name "file"
    if (empty($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded. Use multipart field name "file".']);
        return;
    }

    $f       = $_FILES['file'];
    $errCode = $f['error'] ?? UPLOAD_ERR_NO_FILE;

    // PHP-level upload error
    if ($errCode !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => uploadErrMsg($errCode)]);
        return;
    }

    // ── Size: max 10 MB ──────────────────────────────────────────
    $maxBytes = 10 * 1024 * 1024;
    if (($f['size'] ?? 0) > $maxBytes || filesize($f['tmp_name']) > $maxBytes) {
        http_response_code(422);
        echo json_encode(['error' => 'File too large. Maximum size is 10 MB.']);
        return;
    }

    // ── MIME: JPG / PNG only ─────────────────────────────────────
    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png'];

    if (!function_exists('finfo_open')) {
        http_response_code(500);
        echo json_encode(['error' => 'finfo extension is not available on this server.']);
        return;
    }

    $fi   = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($fi, $f['tmp_name']);
    finfo_close($fi);

    if (!in_array($mime, $allowedMimes, true)) {
        http_response_code(422);
        echo json_encode([
            'error'     => 'Only JPG and PNG files are accepted.',
            'mime_found' => $mime,
        ]);
        return;
    }

    // ── Extension: .jpg / .jpeg / .png ───────────────────────────
    $ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        http_response_code(422);
        echo json_encode(['error' => 'File extension must be .jpg, .jpeg or .png.']);
        return;
    }

    // ── Sanitize filename ─────────────────────────────────────────
    $safeName = sanitizeUploadFilename($f['name'] ?? 'upload.jpg');
    if ($safeName === '' || $safeName === '.') {
        http_response_code(400);
        echo json_encode(['error' => 'Filename is invalid after sanitization.']);
        return;
    }

    // ── Ensure uploads/images/ exists ────────────────────────────
    $destDir = IMG_DIR;
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not create uploads/images directory.']);
        return;
    }

    // ── Deduplicate: append (1), (2), … if name already exists ───
    $destPath = $destDir . DIRECTORY_SEPARATOR . $safeName;
    if (file_exists($destPath)) {
        $base    = pathinfo($safeName, PATHINFO_FILENAME);
        $extPart = pathinfo($safeName, PATHINFO_EXTENSION);
        $counter = 1;
        do {
            $safeName = "{$base}({$counter}).{$extPart}";
            $destPath = $destDir . DIRECTORY_SEPARATOR . $safeName;
            $counter++;
        } while (file_exists($destPath) && $counter < 1000);
    }

    // ── Move file ─────────────────────────────────────────────────
    if (!move_uploaded_file($f['tmp_name'], $destPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save uploaded file.']);
        return;
    }

    echo json_encode(
        [
            'success'  => true,
            'filename' => $safeName,
            'url'      => IMG_URL_BASE . '/' . rawurlencode($safeName),
        ],
        JSON_UNESCAPED_SLASHES
    );
}

// ── Shared helper ────────────────────────────────────────────────

function uploadErrMsg(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE specified in the form.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing server temporary directory.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by a PHP extension.',
        default               => "Upload error (code {$code}).",
    };
}
