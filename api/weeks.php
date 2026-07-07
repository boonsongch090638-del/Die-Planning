<?php
/**
 * Weekly Summary API
 *
 * GET /api/weeks.php
 *
 * Returns a JSON array of aggregated rows:
 *   - One row per unique die_finish_plan value (plan_status = 'normal')
 *   - One row for all 'waiting' dies (regardless of die_finish_plan)
 *   - One row for all 'hold' dies
 *
 * Each row shape:
 * {
 *   "week":          "16/2026",                   // key; "waiting"/"hold" for special rows
 *   "label":         "",                           // set for special rows only
 *   "date_range":    "13-17/4/2026",              // Mon–Fri of ISO week; empty for special
 *   "solid":         6,
 *   "hollow_easy":   2,
 *   "hollow_medium": 5,
 *   "hollow_hard":   2,
 *   "heatsink":      0,
 *   "total":         15
 * }
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = getDB();
    echo json_encode(buildWeeklySummary($pdo), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// ── Main builder ─────────────────────────────────────────────────────────────

function buildWeeklySummary(PDO $pdo): array {

    // ── 1. Normal weeks (plan_status = 'normal' or unset) ────────────────────
    $stmt = $pdo->query("
        SELECT
            die_finish_plan,
            die_type,
            hollow_level,
            COUNT(*) AS cnt
        FROM dies
        WHERE plan_status = 'normal'
           OR plan_status IS NULL
           OR plan_status = ''
        GROUP BY die_finish_plan, die_type, hollow_level
        ORDER BY die_finish_plan
    ");
    $normalRows = $stmt->fetchAll();

    $weekMap = [];
    foreach ($normalRows as $row) {
        $key = trim($row['die_finish_plan'] ?? '');
        if (!isset($weekMap[$key])) {
            $weekMap[$key] = makeRow($key);
        }
        addTypeCount($weekMap[$key], $row['die_type'], $row['hollow_level'], (int) $row['cnt']);
    }

    // ── 1b. Count pending (no actual date) per week ──────────────────
    $pendingStmt = $pdo->query("
        SELECT die_finish_plan, COUNT(*) AS cnt
        FROM dies
        WHERE (plan_status = 'normal' OR plan_status IS NULL OR plan_status = '')
          AND (die_pc_actual_date IS NULL OR TRIM(die_pc_actual_date) = '')
        GROUP BY die_finish_plan
    ");
    foreach ($pendingStmt->fetchAll() as $pRow) {
        $pKey = trim($pRow['die_finish_plan'] ?? '');
        if (isset($weekMap[$pKey])) {
            $weekMap[$pKey]['pending'] = (int) $pRow['cnt'];
        }
    }

    // Sort by year then week number, pushing blank keys to the end
    uksort($weekMap, 'compareWeeks');

    // Resolve date ranges after sorting
    foreach ($weekMap as $key => &$row) {
        $row['date_range'] = weekToDateRange($key);
    }
    unset($row);

    $result = array_values($weekMap);

    // ── 2. Special plan_status rows ───────────────────────────────────────────
    $stmt = $pdo->query("
        SELECT
            plan_status,
            die_type,
            hollow_level,
            COUNT(*) AS cnt
        FROM dies
        WHERE plan_status IN ('waiting', 'hold')
        GROUP BY plan_status, die_type, hollow_level
    ");
    $specialRows = $stmt->fetchAll();

    $specialMap = [];
    foreach ($specialRows as $row) {
        $s = $row['plan_status'];
        if (!isset($specialMap[$s])) {
            $specialMap[$s] = makeRow($s);
        }
        addTypeCount($specialMap[$s], $row['die_type'], $row['hollow_level'], (int) $row['cnt']);
    }

    // Append in fixed order: waiting → hold
    foreach (['waiting' => 'รอยืนยันแผนผลิต Die', 'hold' => 'Hold'] as $status => $label) {
        if (!isset($specialMap[$status])) {
            // Always emit both rows, even when count is 0
            $specialMap[$status] = makeRow($status);
        }
        $specialMap[$status]['label'] = $label;
        $result[] = $specialMap[$status];
    }

    return $result;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Create a zero-initialised summary row.
 */
function makeRow(string $week): array {
    return [
        'week'          => $week,
        'label'         => '',
        'date_range'    => '',
        'solid'         => 0,
        'hollow_easy'   => 0,
        'hollow_medium' => 0,
        'hollow_hard'   => 0,
        'heatsink'      => 0,
        'total'         => 0,
        'pending'       => 0,
    ];
}

/**
 * Accumulate a count from one DB GROUP-BY row into the summary row.
 * Always increments `total`; type/level determines which sub-count increases.
 */
function addTypeCount(array &$row, ?string $type, ?string $level, int $cnt): void {
    $type  = trim($type  ?? '');
    $level = trim($level ?? '');

    if ($type === 'Solid') {
        $row['solid'] += $cnt;
    } elseif ($type === 'Hollow') {
        if ($level === 'easy')   { $row['hollow_easy']   += $cnt; }
        elseif ($level === 'medium') { $row['hollow_medium'] += $cnt; }
        elseif ($level === 'hard')   { $row['hollow_hard']   += $cnt; }
    } elseif ($type === 'Heatsink') {
        $row['heatsink'] += $cnt;
    }
    // Always count in total (includes untyped/unclassified dies)
    $row['total'] += $cnt;
}

/**
 * Comparator for uksort: sorts "ww/yyyy" keys chronologically.
 * Blank keys sort to the end.
 */
function compareWeeks(string $a, string $b): int {
    if ($a === $b)  return 0;
    if ($a === '')  return 1;
    if ($b === '')  return -1;

    $pa = explode('/', $a, 2);
    $pb = explode('/', $b, 2);

    // Non-standard keys (e.g. 'waiting', 'hold') — sort alphabetically
    if (count($pa) < 2 || count($pb) < 2) {
        return strcmp($a, $b);
    }

    [$wa, $ya] = [(int) $pa[0], (int) $pa[1]];
    [$wb, $yb] = [(int) $pb[0], (int) $pb[1]];

    return $ya !== $yb ? $ya <=> $yb : $wa <=> $wb;
}

/**
 * Convert "ww/yyyy" → "d-d/m/yyyy" (Mon–Fri of that ISO week).
 *
 * Cross-month example:  week 18/2026 → "27/4-1/5/2026"
 * Same-month example:   week 16/2026 → "13-17/4/2026"
 */
function weekToDateRange(string $weekKey): string {
    if ($weekKey === '' || !str_contains($weekKey, '/')) {
        return '';
    }

    $parts = explode('/', $weekKey, 2);
    if (count($parts) !== 2) return '';

    $week = (int) $parts[0];
    $year = (int) $parts[1];

    if ($week < 1 || $week > 53 || $year < 2000 || $year > 2100) {
        return '';
    }

    try {
        $monday = new DateTime();
        $monday->setISODate($year, $week, 1); // ISO day 1 = Monday

        $friday = new DateTime();
        $friday->setISODate($year, $week, 5); // ISO day 5 = Friday

        $d1 = (int) $monday->format('j');
        $m1 = (int) $monday->format('n');
        $y1 = (int) $monday->format('Y');
        $d2 = (int) $friday->format('j');
        $m2 = (int) $friday->format('n');
        $y2 = (int) $friday->format('Y');

        if ($m1 === $m2 && $y1 === $y2) {
            // Simple case: same month
            return "{$d1}-{$d2}/{$m1}/{$y1}";
        }
        // Spans a month (or year) boundary
        return "{$d1}/{$m1}-{$d2}/{$m2}/{$y2}";

    } catch (Exception) {
        return '';
    }
}
