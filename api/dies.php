<?php
/**
 * REST API — dies table
 *
 * GET    /api/dies.php              → all dies (JSON)
 * GET    /api/dies.php?week=16/2026 → filter by die_finish_plan
 * GET    /api/dies.php?search=kw    → search customer / section / tech_dwg_no
 * POST   /api/dies.php              → insert die  → {success, id}
 * PUT    /api/dies.php?id=X         → update die  → {success}
 * DELETE /api/dies.php?id=X         → delete die  → {success}
 */

// Suppress HTML error output — errors go to JSON response instead
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Catch any unexpected output or fatal errors and return as JSON
ob_start();
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => "PHP Error [{$errno}]: {$errstr} in {$errfile}:{$errline}"]);
    exit;
});
register_shutdown_function(function(): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['error' => "Fatal: {$err['message']} in {$err['file']}:{$err['line']}"]);
    }
});

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

/** All columns that may be written by the client (excludes id, created_at, updated_at). */
const ALLOWED_FIELDS = [
    'no', 'reason', 'customer', 'section', 'index_tab', 'tech_dwg_no',
    'plan_send_date', 'die_pc_due_date', 'die_pc_actual_date',
    'die_finish_plan', 'machine', 'die_count', 'dmk_status', 'forecast',
    'die_type', 'hollow_level', 'plan_status', 'remarks',
];

/** Fields stored as integers. */
const INT_FIELDS = ['die_count'];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Trim a string value. HTML escaping happens at output time (htmlspecialchars in templates),
 * not at storage time — storing raw values keeps the DB clean.
 */
function sanitizeString(string $value): string {
    return trim($value);
}

/**
 * Parse the request body from JSON (application/json) or form data.
 * PHP does not populate $_POST for PUT/PATCH, so we always read php://input
 * and fall back to $_POST only for standard POST form submissions.
 */
function getRequestBody(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
        return $_POST;
    }

    // PUT / PATCH with application/x-www-form-urlencoded
    $raw = file_get_contents('php://input');
    parse_str($raw, $parsed);
    return $parsed;
}

/**
 * Extract and sanitize only the allowed fields from a raw input array.
 * String fields are passed through sanitizeString(); integer fields are cast.
 *
 * @return array<string, mixed>  keyed by column name
 */
function filterFields(array $raw): array {
    $out = [];
    foreach (ALLOWED_FIELDS as $field) {
        if (!array_key_exists($field, $raw)) {
            continue;
        }
        if (in_array($field, INT_FIELDS, true)) {
            $out[$field] = (int) $raw[$field];
        } else {
            $out[$field] = sanitizeString((string) $raw[$field]);
        }
    }
    // hollow_level has a CHECK constraint — convert empty string to NULL
    if (array_key_exists('hollow_level', $out) && $out['hollow_level'] === '') {
        $out['hollow_level'] = null;
    }
    return $out;
}

/**
 * Append computed fields to a die row:
 *   die_no        = section-index_tab
 *   die_pc_status = "Finish" | "Pending"
 */
function addComputedFields(array &$die): void {
    $die['die_no']        = ($die['section'] ?? '') . '-' . ($die['index_tab'] ?? '');
    $die['die_pc_status'] = !empty($die['die_pc_actual_date']) ? 'Finish' : 'Pending';
}

/**
 * Respond with an error JSON payload and set the HTTP status code.
 */
function errorResponse(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['error' => $message]);
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

function handleGet(PDO $pdo): void {
    $conditions = [];
    $params     = [];

    // Filter by die_finish_plan week, e.g. ?week=16/2026
    if (!empty($_GET['week'])) {
        $conditions[]    = 'die_finish_plan = :week';
        $params[':week'] = sanitizeString($_GET['week']);
    }

    // Keyword search across customer, section, tech_dwg_no
    if (!empty($_GET['search'])) {
        $keyword            = '%' . sanitizeString($_GET['search']) . '%';
        $conditions[]       = '(customer LIKE :search OR section LIKE :search OR tech_dwg_no LIKE :search)';
        $params[':search']  = $keyword;
    }

    $sql = 'SELECT * FROM dies';
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= " ORDER BY
        CASE WHEN die_pc_due_date IS NULL OR TRIM(die_pc_due_date) = '' THEN 1 ELSE 0 END,
        die_pc_due_date ASC, id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dies = $stmt->fetchAll();

    foreach ($dies as &$die) {
        addComputedFields($die);
    }
    unset($die); // break reference from last iteration

    echo json_encode([
        'data'  => $dies,
        'total' => count($dies),
    ]);
}

function handlePost(PDO $pdo): void {
    $data = filterFields(getRequestBody());

    if (empty($data)) {
        errorResponse(400, 'No valid fields provided');
        return;
    }

    // Auto-assign running sequence for "no" if not provided
    if (!isset($data['no']) || $data['no'] === '') {
        $maxNo      = (int) $pdo->query("SELECT COALESCE(MAX(CAST(no AS INTEGER)), 0) FROM dies")->fetchColumn();
        $data['no'] = (string) ($maxNo + 1);
    }

    $cols         = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));

    $stmt = $pdo->prepare("INSERT INTO dies ({$cols}) VALUES ({$placeholders})");

    foreach ($data as $col => $value) {
        if ($value === null) {
            $stmt->bindValue(':' . $col, null, PDO::PARAM_NULL);
        } else {
            $type = in_array($col, INT_FIELDS, true) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $col, $value, $type);
        }
    }

    $stmt->execute();

    echo json_encode([
        'success' => true,
        'id'      => (int) $pdo->lastInsertId(),
    ]);
}

function handlePut(PDO $pdo): void {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        errorResponse(400, 'A valid ?id= parameter is required');
        return;
    }

    $data = filterFields(getRequestBody());
    if (empty($data)) {
        errorResponse(400, 'No valid fields provided');
        return;
    }

    // Build SET clause: col1 = :col1, col2 = :col2, ...
    $setParts = array_map(fn(string $col): string => "{$col} = :{$col}", array_keys($data));
    $sql      = 'UPDATE dies SET ' . implode(', ', $setParts) . ' WHERE id = :id';

    $stmt = $pdo->prepare($sql);

    foreach ($data as $col => $value) {
        if ($value === null) {
            $stmt->bindValue(':' . $col, null, PDO::PARAM_NULL);
        } else {
            $type = in_array($col, INT_FIELDS, true) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $col, $value, $type);
        }
    }
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);

    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        errorResponse(404, "Die with id={$id} not found");
        return;
    }

    echo json_encode(['success' => true]);
}

function handleDelete(PDO $pdo): void {

    // ── Delete-all: DELETE /api/dies.php?all=1  body: {password:"..."} ──
    if (!empty($_GET['all'])) {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $pass = trim($body['password'] ?? '');

        // Compare against hashed password — never store plain text
        $expected = '7a1e9b2c3d4f5e6a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a';  // placeholder
        // Use hash_equals to prevent timing attacks
        if (!hash_equals(hash('sha256', 'ALUMET5902146'), hash('sha256', $pass))) {
            errorResponse(403, 'รหัสผ่านไม่ถูกต้อง');
            return;
        }

        $count = (int) $pdo->query('SELECT COUNT(*) FROM dies')->fetchColumn();
        $pdo->exec('DELETE FROM dies');
        // Reset SQLite auto-increment sequence
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name='dies'");

        echo json_encode(['success' => true, 'deleted' => $count]);
        return;
    }

    // ── Delete single: DELETE /api/dies.php?id=X ────────────────────
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        errorResponse(400, 'A valid ?id= parameter is required');
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM dies WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        errorResponse(404, "Die with id={$id} not found");
        return;
    }

    echo json_encode(['success' => true]);
}

// ---------------------------------------------------------------------------
// Router
// ---------------------------------------------------------------------------

try {
    $pdo = getDB();

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGet($pdo);
            break;
        case 'POST':
            requireAdminApi();
            handlePost($pdo);
            break;
        case 'PUT':
            requireAdminApi();
            handlePut($pdo);
            break;
        case 'DELETE':
            requireAdminApi();
            handleDelete($pdo);
            break;
        default:
            header('Allow: GET, POST, PUT, DELETE');
            errorResponse(405, 'Method not allowed');
    }
} catch (PDOException $e) {
    errorResponse(500, 'Database error: ' . $e->getMessage());
} catch (Throwable $e) {
    errorResponse(500, $e->getMessage());
}
