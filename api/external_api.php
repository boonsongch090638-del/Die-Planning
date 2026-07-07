<?php
/**
 * External API Integration — DMK Status Sync
 *
 * API: http://almdc.alumetgroup.com/alm/api/v1/alm_getDiemagStatus/
 *   Returns a JSON array of all dies:
 *   [{ "DieNo": "4011-446", "DieMagOrderProcessName": "W/C",
 *      "DieMagOrderStatusName": "ระหว่างดำเนินการ", ... }, ...]
 *
 * POST { action: "sync" }
 *   Fetch all statuses from the API in one call,
 *   match each die by (section + '-' + index_tab) = DieNo,
 *   update dies.dmk_status, write sync_log row.
 *   Response: { success, total, updated, failed, timestamp, details[] }
 *
 * POST { action: "test", api_url?, headers? }
 *   Call the API and return a sample of the response.
 *   Response: { success, url_called, http_code, sample[] }
 *
 * GET  ?action=logs[&limit=10]
 *   Return last N sync_log rows.
 *   Response: { logs: [...] }
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

define('DEFAULT_API_URL', 'http://almdc.alumetgroup.com/alm/api/v1/alm_getDiemagStatus/');
// Field names in the API response
define('API_DIE_KEY',         'DieNo');                  // identifier field
define('API_PROCESS_FIELD',   'DieMagOrderProcessName'); // primary status field
define('API_STATUS_FIELD',    'DieMagOrderStatusName');  // fallback when process is null

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getDB();

// ── GET: logs only ───────────────────────────────────────────────
if ($method === 'GET') {
    $action = trim($_GET['action'] ?? '');
    if ($action === 'logs') {
        handleLogs($pdo);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Use GET ?action=logs']);
    }
    exit;
}

// ── POST (admin only) ────────────────────────────────────────────
if ($method !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
requireAdminApi();

$ct   = $_SERVER['CONTENT_TYPE'] ?? '';
$body = str_contains($ct, 'application/json')
    ? (json_decode(file_get_contents('php://input'), true) ?? [])
    : $_POST;

$action = trim($body['action'] ?? '');

switch ($action) {
    case 'sync': handleSync($pdo);            break;
    case 'test': handleTest($pdo, $body);     break;
    default:
        http_response_code(400);
        echo json_encode(['error' => '"action" must be "sync" or "test"']);
}

// ════════════════════════════════════════════════════════════════
// Handlers
// ════════════════════════════════════════════════════════════════

function handleSync(PDO $pdo): void {
    // Load active API config (fall back to hardcoded default if none configured)
    $cfg     = $pdo->query("SELECT * FROM api_settings WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch();
    $apiUrl  = $cfg ? trim($cfg['api_url'] ?? '') : '';
    $headers = $cfg ? (json_decode($cfg['headers'] ?? '{}', true) ?: []) : [];
    if ($apiUrl === '') $apiUrl = DEFAULT_API_URL;

    // ── Fetch all statuses from the API in one call ──────────────
    try {
        $apiData = fetchAllJson($apiUrl, $headers);
    } catch (RuntimeException $e) {
        http_response_code(502);
        echo json_encode(['error' => 'API call failed: ' . $e->getMessage()]);
        return;
    }

    if (!is_array($apiData) || empty($apiData)) {
        http_response_code(502);
        echo json_encode(['error' => 'API returned empty or invalid data.']);
        return;
    }

    // ── Build lookup map: DieNo (uppercase) → status string ─────
    // Use uppercase keys so matching is case-insensitive
    // (e.g. DB "iC-0102-4" matches API "IC-0102-4")
    $statusMap = [];
    foreach ($apiData as $item) {
        $dieNo = strtoupper(trim((string)($item[API_DIE_KEY] ?? '')));
        if ($dieNo === '') continue;

        // Use process name when available; fall back to overall status name
        $process = $item[API_PROCESS_FIELD] ?? null;
        $overall = $item[API_STATUS_FIELD]  ?? null;
        $statusMap[$dieNo] = ($process !== null && trim((string)$process) !== '')
            ? trim((string)$process)
            : ($overall !== null ? trim((string)$overall) : null);
    }

    // ── Load all dies (need section + index_tab to compute die_no) ─
    $dies = $pdo->query(
        "SELECT id, section, index_tab, dmk_status FROM dies ORDER BY id"
    )->fetchAll();

    $total   = count($dies);
    $updated = 0;
    $failed  = 0;
    $details = [];

    $updateStmt = $pdo->prepare("UPDATE dies SET dmk_status = :status WHERE id = :id");

    foreach ($dies as $die) {
        $dieNo    = trim($die['section'] ?? '') . '-' . trim($die['index_tab'] ?? '');
        $dieNoKey = strtoupper($dieNo);   // case-insensitive lookup

        if (array_key_exists($dieNoKey, $statusMap)) {
            $raw   = $statusMap[$dieNoKey];
            $clean = ($raw !== null && $raw !== '')
                ? htmlspecialchars($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                : '#N/A';

            $updateStmt->execute([':status' => $clean, ':id' => (int)$die['id']]);
            $details[] = [
                'die_id'     => (int)$die['id'],
                'die_no'     => $dieNo,
                'old_status' => $die['dmk_status'] ?? '',
                'new_status' => $clean,
                'success'    => true,
            ];
            $updated++;
        } else {
            // Not found in DMK system — mark as #N/A
            $updateStmt->execute([':status' => '#N/A', ':id' => (int)$die['id']]);
            $details[] = [
                'die_id'     => (int)$die['id'],
                'die_no'     => $dieNo,
                'old_status' => $die['dmk_status'] ?? '',
                'new_status' => '#N/A',
                'success'    => false,
                'error'      => 'ไม่พบใน DMK',
            ];
            $failed++;
        }
    }

    // ── Write sync_log row ────────────────────────────────────────
    $pdo->prepare(
        "INSERT INTO sync_log (total, success, failed, details)
         VALUES (:total, :success, :failed, :details)"
    )->execute([
        ':total'   => $total,
        ':success' => $updated,
        ':failed'  => $failed,
        ':details' => json_encode($details, JSON_UNESCAPED_UNICODE),
    ]);

    echo json_encode([
        'success'   => true,
        'total'     => $total,
        'updated'   => $updated,
        'failed'    => $failed,
        'timestamp' => date('Y-m-d H:i:s'),
        'details'   => array_slice($details, 0, 20),
    ], JSON_UNESCAPED_UNICODE);
}

function handleTest(PDO $pdo, array $body): void {
    $apiUrl  = trim($body['api_url'] ?? '');
    $rawHdrs = trim($body['headers'] ?? '');
    $headers = [];

    if ($rawHdrs !== '' && $rawHdrs !== '{}') {
        $decoded = json_decode($rawHdrs, true);
        if (is_array($decoded)) $headers = $decoded;
    }

    // Fall back to active DB settings or hardcoded default
    if ($apiUrl === '') {
        $cfg = $pdo->query("SELECT * FROM api_settings WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch();
        $apiUrl  = $cfg ? trim($cfg['api_url'] ?? '') : '';
        $headers = $cfg ? (json_decode($cfg['headers'] ?? '{}', true) ?: []) : [];
    }
    if ($apiUrl === '') $apiUrl = DEFAULT_API_URL;

    try {
        $data = fetchAllJson($apiUrl, $headers);
        echo json_encode([
            'success'    => true,
            'url_called' => $apiUrl,
            'total'      => count($data),
            'sample'     => array_slice($data, 0, 5),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } catch (RuntimeException $e) {
        echo json_encode([
            'success'    => false,
            'url_called' => $apiUrl,
            'error'      => $e->getMessage(),
        ]);
    }
}

function handleLogs(PDO $pdo): void {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
    $stmt  = $pdo->prepare("SELECT * FROM sync_log ORDER BY id DESC LIMIT :lim");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();

    foreach ($logs as &$log) {
        $log['details'] = json_decode($log['details'] ?? '[]', true) ?: [];
    }
    unset($log);

    echo json_encode(['logs' => $logs], JSON_UNESCAPED_UNICODE);
}

// ════════════════════════════════════════════════════════════════
// HTTP client helpers
// ════════════════════════════════════════════════════════════════

/**
 * Fetch the bulk API response (one call, returns all die statuses).
 *
 * @throws RuntimeException on network error, bad HTTP status, or invalid JSON
 */
function fetchAllJson(string $url, array $extraHeaders = []): array {
    $hdrs = ['Accept: application/json', 'User-Agent: DiePlanning/1.0'];
    foreach ($extraHeaders as $k => $v) {
        $hdrs[] = "{$k}: {$v}";
    }

    // ── cURL (preferred) ──────────────────────────────────────────
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => $hdrs,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch); // @phpstan-ignore-line (deprecated in PHP 8.0, harmless)

        if ($raw === false) throw new RuntimeException("cURL error: {$curlErr}");
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("HTTP {$httpCode}: " . substr($raw, 0, 200));
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Response is not valid JSON: ' . substr($raw, 0, 200));
        }
        return is_array($decoded) ? $decoded : [$decoded];
    }

    // ── file_get_contents fallback ────────────────────────────────
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => implode("\r\n", $hdrs),
            'timeout'       => 30,
            'ignore_errors' => true,
        ],
        'ssl'  => ['verify_peer' => false],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        throw new RuntimeException('Request failed (file_get_contents returned false)');
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Response is not valid JSON: ' . substr($raw, 0, 200));
    }
    return is_array($decoded) ? $decoded : [$decoded];
}
