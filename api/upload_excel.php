<?php
/**
 * Excel / CSV Upload API — No Composer required.
 *
 * POST action=preview       field:file              → preview first 5 rows (standard format)
 * POST action=import        field:temp_key           → upsert full row (standard format)
 * POST action=preview_dieno field:file              → preview first 5 rows (Die-No format)
 * POST action=import_dieno  field:temp_key           → update dates/remarks by Die No
 *
 * Preview response: { headers:[], rows:[[]], total_rows:N, temp_key:"xxx" }
 * Import  response: { success:true, imported:N, updated:N, errors:N, error_list:[] }
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

requireAdminApi();

$action    = trim($_POST['action'] ?? 'import');
$skipFirst = !empty($_POST['skip_first']);

require_once __DIR__ . '/../includes/db.php';

// Load the appropriate import library based on action
if (in_array($action, ['preview_dieno', 'import_dieno', 'preview_actual', 'import_actual'], true)) {
    require_once __DIR__ . '/../import/import_by_dieno.php';
} else {
    require_once __DIR__ . '/../import/import_excel.php';
}

// ── Temp directory ───────────────────────────────────────────────────
$tempDir = __DIR__ . '/../uploads/temp';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}
$tempDir = realpath($tempDir);

// ════════════════════════════════════════════════════════════════════
// ACTION: preview — upload file, save to temp, return first 5 rows
// ════════════════════════════════════════════════════════════════════
if ($action === 'preview') {

    $uploadErr = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadErr !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => uploadMsg($uploadErr)]);
        exit;
    }

    $orig = $_FILES['file']['name']     ?? 'upload';
    $tmp  = $_FILES['file']['tmp_name'] ?? '';
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'csv'], true)) {
        http_response_code(422);
        echo json_encode(['error' => 'Only .xlsx and .csv files are accepted.']);
        exit;
    }

    // Save to temp
    $key      = preg_replace('/[^a-z0-9]/', '', uniqid('imp', true));
    $destPath = $tempDir . DIRECTORY_SEPARATOR . $key . '.' . $ext;

    if (!move_uploaded_file($tmp, $destPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save uploaded file.']);
        exit;
    }

    try {
        $preview = previewFile($destPath, $skipFirst, 5);
        echo json_encode(array_merge($preview, ['temp_key' => $key . '.' . $ext]),
                         JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        @unlink($destPath);
        http_response_code(422);
        echo json_encode(['error' => 'Cannot read file: ' . $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════════════════════════════
// ACTION: import — process the already-saved temp file
// ════════════════════════════════════════════════════════════════════
if ($action === 'import') {

    $rawKey = trim($_POST['temp_key'] ?? '');

    // Validate key: only safe characters (no path traversal)
    if (!preg_match('/^[a-z0-9]+\.(xlsx|csv)$/i', $rawKey)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or missing temp_key.']);
        exit;
    }

    $filePath = $tempDir . DIRECTORY_SEPARATOR . $rawKey;

    if (!file_exists($filePath)) {
        http_response_code(400);
        echo json_encode(['error' => 'Upload session expired. Please re-upload the file.']);
        exit;
    }

    $clearAll = !empty($_POST['clear_all']) && $_POST['clear_all'] === '1';

    try {
        $pdo = getDB();

        if ($clearAll) {
            $clearPassword = trim($_POST['clear_password'] ?? '');
            if (!hash_equals(hash('sha256', 'ALUMET5902146'), hash('sha256', $clearPassword))) {
                http_response_code(403);
                echo json_encode(['error' => 'รหัสผ่านไม่ถูกต้อง ไม่สามารถลบข้อมูลเดิมได้']);
                if (file_exists($filePath)) @unlink($filePath);
                exit;
            }
            $pdo->exec('DELETE FROM dies');
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name='dies'");
        }

        $result = importFromFile($filePath, $pdo, $skipFirst);
        echo json_encode(array_merge(['success' => true], $result), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
    } finally {
        if (file_exists($filePath)) @unlink($filePath);
    }
    exit;
}

// ════════════════════════════════════════════════════════════════════
// ACTION: preview_dieno — preview Die-No-based update file
// ════════════════════════════════════════════════════════════════════
if ($action === 'preview_dieno') {

    $uploadErr = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadErr !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => uploadMsg($uploadErr)]);
        exit;
    }

    $orig = $_FILES['file']['name']     ?? 'upload';
    $tmp  = $_FILES['file']['tmp_name'] ?? '';
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'csv'], true)) {
        http_response_code(422);
        echo json_encode(['error' => 'Only .xlsx and .csv files are accepted.']);
        exit;
    }

    $key      = preg_replace('/[^a-z0-9]/', '', uniqid('dn', true));
    $destPath = $tempDir . DIRECTORY_SEPARATOR . $key . '.' . $ext;

    if (!move_uploaded_file($tmp, $destPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save uploaded file.']);
        exit;
    }

    try {
        $preview = previewFileByDieNo($destPath, $skipFirst, 5);
        echo json_encode(array_merge($preview, ['temp_key' => $key . '.' . $ext]),
                         JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        @unlink($destPath);
        http_response_code(422);
        echo json_encode(['error' => 'Cannot read file: ' . $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════════════════════════════
// ACTION: import_dieno — update dates/remarks by Die No
// ════════════════════════════════════════════════════════════════════
if ($action === 'import_dieno') {

    $rawKey = trim($_POST['temp_key'] ?? '');

    if (!preg_match('/^[a-z0-9]+\.(xlsx|csv)$/i', $rawKey)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or missing temp_key.']);
        exit;
    }

    $filePath = $tempDir . DIRECTORY_SEPARATOR . $rawKey;

    if (!file_exists($filePath)) {
        http_response_code(400);
        echo json_encode(['error' => 'Upload session expired. Please re-upload the file.']);
        exit;
    }

    try {
        $pdo    = getDB();
        $result = importFromFileByDieNo($filePath, $pdo, $skipFirst);
        echo json_encode(array_merge(['success' => true], $result), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
    } finally {
        if (file_exists($filePath)) @unlink($filePath);
    }
    exit;
}

// ════════════════════════════════════════════════════════════════════
// ACTION: preview_actual — preview 2-column actual-date file
// ════════════════════════════════════════════════════════════════════
if ($action === 'preview_actual') {

    $uploadErr = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadErr !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => uploadMsg($uploadErr)]);
        exit;
    }

    $orig = $_FILES['file']['name']     ?? 'upload';
    $tmp  = $_FILES['file']['tmp_name'] ?? '';
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'csv'], true)) {
        http_response_code(422);
        echo json_encode(['error' => 'Only .xlsx and .csv files are accepted.']);
        exit;
    }

    $key      = preg_replace('/[^a-z0-9]/', '', uniqid('act', true));
    $destPath = $tempDir . DIRECTORY_SEPARATOR . $key . '.' . $ext;

    if (!move_uploaded_file($tmp, $destPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save uploaded file.']);
        exit;
    }

    try {
        $preview = previewFileActualDate($destPath, $skipFirst, 5);
        echo json_encode(array_merge($preview, ['temp_key' => $key . '.' . $ext]),
                         JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        @unlink($destPath);
        http_response_code(422);
        echo json_encode(['error' => 'Cannot read file: ' . $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════════════════════════════
// ACTION: import_actual — update die_pc_actual_date by Die No
// ════════════════════════════════════════════════════════════════════
if ($action === 'import_actual') {

    $rawKey = trim($_POST['temp_key'] ?? '');

    if (!preg_match('/^[a-z0-9]+\.(xlsx|csv)$/i', $rawKey)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or missing temp_key.']);
        exit;
    }

    $filePath = $tempDir . DIRECTORY_SEPARATOR . $rawKey;

    if (!file_exists($filePath)) {
        http_response_code(400);
        echo json_encode(['error' => 'Upload session expired. Please re-upload the file.']);
        exit;
    }

    try {
        $pdo    = getDB();
        $result = importFromFileActualDate($filePath, $pdo, $skipFirst);
        echo json_encode(array_merge(['success' => true], $result), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
    } finally {
        if (file_exists($filePath)) @unlink($filePath);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'action must be "preview", "import", "preview_dieno", "import_dieno", "preview_actual", or "import_actual"']);

// ── Helper ────────────────────────────────────────────────────────────
function uploadMsg(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds size limit.',
        UPLOAD_ERR_PARTIAL    => 'Upload was incomplete.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing server temp directory.',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk.',
        default               => "Upload error (code {$code}).",
    };
}
